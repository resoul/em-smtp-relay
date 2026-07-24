import XCTest
import CryptoKit
@testable import WireAuth

final class WireAuthTests: XCTestCase {

    override func setUp() {
        super.setUp()
        // A throwaway 2048-bit RSA keypair's public key, SPKI/DER, base64 —
        // generated once for these tests. Replace with your own if you
        // need to exercise importServerRSAKey against a specific key.
        WireAuth.configure(
            serverRSAPublicKeyB64: Self.testServerPublicKeyB64,
            resumeProofSalt: "test-session-salt".data(using: .utf8)
        )
    }

    func testImportServerRSAKeySucceedsWithValidKey() throws {
        let key = try WireAuth.importServerRSAKey()
        XCTAssertNotNil(key)
    }

    func testStage1PacketFormat() {
        let nonce = WireAuth.generateClientNonce()
        XCTAssertEqual(nonce.count, 16)

        let packet = WireAuth.buildStage1Packet(clientNonce: nonce)
        XCTAssertEqual(packet.count, 4 + 16)

        // cmd field must be little-endian 1
        let cmd = packet.prefix(4).withUnsafeBytes { $0.load(as: UInt32.self) }
        XCTAssertEqual(UInt32(littleEndian: cmd), 1)
    }

    func testParseStage1ResponseRejectsShortPackets() {
        XCTAssertThrowsError(try WireAuth.parseStage1Response(Data(count: 10))) { error in
            XCTAssertEqual(error as? WireAuth.HandshakeError, .packetTooShort)
        }
    }

    func testEncryptDecryptRoundTrip() throws {
        let key = SymmetricKey(size: .bits256)
        let plaintext = "hello over the secure channel".data(using: .utf8)!

        let packet = try WireAuth.encryptSecure(key: key, seq: 42, payload: plaintext)
        let (decrypted, seq) = try WireAuth.decryptSecure(key: key, packet: packet)

        XCTAssertEqual(decrypted, plaintext)
        XCTAssertEqual(seq, 42)
    }

    func testDecryptSecureRejectsShortPackets() {
        XCTAssertThrowsError(try WireAuth.decryptSecure(key: SymmetricKey(size: .bits256), packet: Data(count: 5))) { error in
            XCTAssertEqual(error as? WireAuth.SecureChannelError, .packetTooShort)
        }
    }

    func testECDHKeyExchangeDerivesMatchingKeys() throws {
        // Simulate both sides of stage 2 to confirm the KDF concatenation
        // order (shared_secret ‖ client_nonce ‖ server_nonce) is applied
        // identically regardless of which side is "client" vs "server" in
        // this test — the real client/server distinction only matters for
        // who initiates.
        let clientPrivate = P256.KeyAgreement.PrivateKey()
        let serverPrivate = P256.KeyAgreement.PrivateKey()

        let clientNonce = WireAuth.generateClientNonce()
        let serverNonce = WireAuth.generateClientNonce() // any 16 random bytes

        let clientDerived = try WireAuth.deriveSharedAESKey(
            serverPublicKeyRaw: serverPrivate.publicKey.x963Representation,
            clientPrivateKey: clientPrivate,
            clientNonce: clientNonce,
            serverNonce: serverNonce
        )

        let serverDerived = try WireAuth.deriveSharedAESKey(
            serverPublicKeyRaw: clientPrivate.publicKey.x963Representation,
            clientPrivateKey: serverPrivate,
            clientNonce: clientNonce,
            serverNonce: serverNonce
        )

        XCTAssertEqual(clientDerived.raw, serverDerived.raw)
    }

    func testComputeResumeProofRequiresSalt() {
        WireAuth.configure(serverRSAPublicKeyB64: Self.testServerPublicKeyB64, resumeProofSalt: nil)
        XCTAssertThrowsError(
            try WireAuth.computeResumeProof(
                authKeyID: 1,
                masterKey: SymmetricKey(size: .bits256),
                serverNonce: Data(count: 16)
            )
        ) { error in
            XCTAssertEqual(error as? WireAuth.HandshakeError, .resumeSaltNotConfigured)
        }
    }

    func testComputeResumeProofIsDeterministic() throws {
        WireAuth.configure(
            serverRSAPublicKeyB64: Self.testServerPublicKeyB64,
            resumeProofSalt: "fixed-salt".data(using: .utf8)
        )
        let masterKey = SymmetricKey(size: .bits256)
        let serverNonce = Data(repeating: 0xAB, count: 16)

        let proof1 = try WireAuth.computeResumeProof(authKeyID: 7, masterKey: masterKey, serverNonce: serverNonce)
        let proof2 = try WireAuth.computeResumeProof(authKeyID: 7, masterKey: masterKey, serverNonce: serverNonce)

        XCTAssertEqual(proof1.proofB, proof2.proofB)

        // authKeyID must be little-endian per HANDSHAKE_SPEC.md
        let expectedBytes = withUnsafeBytes(of: UInt64(7).littleEndian) { Data($0) }
        XCTAssertEqual(proof1.authKeyIDBytes, expectedBytes)
    }

    // Generated once via `openssl genrsa -out k.pem 2048 && openssl rsa -in k.pem -pubout -outform DER | base64`
    static let testServerPublicKeyB64 = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvpjDcmWXSPVTEQWrYMzNPoHvzF9i5f2XPobr58FVMuz0LTcuSStj28C3iHKiY/Si0B4Px/l7wGWlsHH/0/8IiBCBpOCR2WmMOmWoRaGh9niXSUS01Yw6u+SftI0DRqnyroFmsmVg3nvwGoMt231o3Iwk/bQFjBiEU4L+alDO0Db7TDZRzE6u9qceuMQ7rLxj8qguxTCxHYr50CeCBPEC9WUvc1Fg3Xa67Hc3YxKr+vkKYlVXbH59mVG2tWbw+XicvqnNZy2iPNzy0Q2XYQ1vvrGSLW5LXjoE4NOWEp7qlqytYXbXvlUSYNt42T93BhYLKTyGpiyob1b6KxYCYYoBywIDAQAB"
}