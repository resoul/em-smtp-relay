import XCTest
import CryptoKit
import Security
@testable import WireAuth

final class WireAuthTests: XCTestCase {

    override func setUp() {
        super.setUp()
        WireAuth.configure(
            serverRSAPublicKeyB64: Self.testServerPublicKeyB64,
            resumeProofSalt: "test-session-salt".data(using: .utf8)
        )
    }

    // MARK: - RSA & Nonce Tests

    func testImportServerRSAKeySucceedsWithValidKey() throws {
        let key = try WireAuth.importServerRSAKey()
        XCTAssertNotNil(key)
    }

    func testImportServerRSAKeyThrowsOnInvalidData() {
        XCTAssertThrowsError(try WireAuth.importServerRSAKey(serverRSAPublicKeyB64: "invalid-base-64!!!")) { error in
            XCTAssertEqual(error as? WireAuth.HandshakeError, .invalidRSAKey)
        }
    }

    func testClientNonceGeneration() {
        let nonce1 = WireAuth.generateClientNonce()
        let nonce2 = WireAuth.generateClientNonce()
        XCTAssertEqual(nonce1.count, 16)
        XCTAssertEqual(nonce2.count, 16)
        XCTAssertNotEqual(nonce1, nonce2)
    }

    // MARK: - Protocol v2 Packet Framing & Parsing

    func testStage1V2PacketFormat() {
        let nonce = WireAuth.generateClientNonce()
        let packet = WireAuth.buildStage1PacketV2(clientNonce: nonce)

        XCTAssertEqual(packet.count, 4 + 16)
        let cmd = packet.prefix(4).withUnsafeBytes { $0.load(as: UInt32.self) }
        XCTAssertEqual(UInt32(littleEndian: cmd), 101)
        XCTAssertEqual(packet.subdata(in: 4..<20), nonce)
    }

    func testParseStage1ResponseV2() throws {
        let serverNonce = WireAuth.generateClientNonce()
        let parsed = try WireAuth.parseStage1ResponseV2(serverNonce)
        XCTAssertEqual(parsed, serverNonce)

        XCTAssertThrowsError(try WireAuth.parseStage1ResponseV2(Data(count: 15))) { error in
            XCTAssertEqual(error as? WireAuth.HandshakeError, .packetTooShort)
        }
        XCTAssertThrowsError(try WireAuth.parseStage1ResponseV2(Data(count: 17))) { error in
            XCTAssertEqual(error as? WireAuth.HandshakeError, .packetTooShort)
        }
    }

    func testStage2V2PacketFormat() {
        let keyPair = P256.KeyAgreement.PrivateKey()
        let pubRaw = keyPair.publicKey.x963Representation
        XCTAssertEqual(pubRaw.count, 65)

        let packet = WireAuth.buildStage2PacketV2(clientPublicKeyRaw: pubRaw)
        XCTAssertEqual(packet.count, 4 + 65)

        let cmd = packet.prefix(4).withUnsafeBytes { $0.load(as: UInt32.self) }
        XCTAssertEqual(UInt32(littleEndian: cmd), 102)
        XCTAssertEqual(packet.subdata(in: 4..<69), pubRaw)
    }

    func testParseStage2ResponseV2() throws {
        let serverPub = Data(repeating: 0x04, count: 65)
        let sig = Data(repeating: 0xAA, count: 256)
        var response = Data()
        response.append(serverPub)
        response.append(sig)

        let (parsedPub, parsedSig) = try WireAuth.parseStage2ResponseV2(response)
        XCTAssertEqual(parsedPub, serverPub)
        XCTAssertEqual(parsedSig, sig)

        XCTAssertThrowsError(try WireAuth.parseStage2ResponseV2(Data(count: 320))) { error in
            XCTAssertEqual(error as? WireAuth.HandshakeError, .packetTooShort)
        }
        XCTAssertThrowsError(try WireAuth.parseStage2ResponseV2(Data(count: 322))) { error in
            XCTAssertEqual(error as? WireAuth.HandshakeError, .packetTooShort)
        }
    }

    // MARK: - Transcript Signature Verification & MITM Detection

    func testTranscriptSignatureVerificationAndMITMDetection() throws {
        // Generate ephemeral RSA keypair for testing full verification
        let attributes: [String: Any] = [
            kSecAttrKeyType as String: kSecAttrKeyTypeRSA,
            kSecAttrKeySizeInBits as String: 2048,
        ]
        var error: Unmanaged<CFError>?
        guard let privKey = SecKeyCreateRandomKey(attributes as CFDictionary, &error),
              let pubKey = SecKeyCopyPublicKey(privKey) else {
            XCTFail("Failed to create RSA key pair")
            return
        }

        let clientNonce = WireAuth.generateClientNonce()
        let serverNonce = WireAuth.generateClientNonce()
        let clientPriv = P256.KeyAgreement.PrivateKey()
        let serverPriv = P256.KeyAgreement.PrivateKey()
        let clientPub = clientPriv.publicKey.x963Representation
        let serverPub = serverPriv.publicKey.x963Representation

        var transcript = Data()
        transcript.append(clientNonce)
        transcript.append(serverNonce)
        transcript.append(clientPub)
        transcript.append(serverPub)
        XCTAssertEqual(transcript.count, 16 + 16 + 65 + 65)

        guard let signature = SecKeyCreateSignature(
            privKey,
            .rsaSignatureMessagePKCS1v15SHA256,
            transcript as CFData,
            &error
        ) as Data? else {
            XCTFail("Failed to create signature")
            return
        }
        XCTAssertEqual(signature.count, 256)

        // Valid signature passes
        let valid = WireAuth.verifyTranscriptSignature(
            rsaKey: pubKey,
            clientNonce: clientNonce,
            serverNonce: serverNonce,
            clientPublicKeyRaw: clientPub,
            serverPublicKeyRaw: serverPub,
            signature: signature
        )
        XCTAssertTrue(valid)

        // Attacker substitutes server ECDH public key (MITM)
        let attackerPriv = P256.KeyAgreement.PrivateKey()
        let attackerPub = attackerPriv.publicKey.x963Representation
        let mitmDetected = WireAuth.verifyTranscriptSignature(
            rsaKey: pubKey,
            clientNonce: clientNonce,
            serverNonce: serverNonce,
            clientPublicKeyRaw: clientPub,
            serverPublicKeyRaw: attackerPub,
            signature: signature
        )
        XCTAssertFalse(mitmDetected, "Signature verification MUST fail when server public key is substituted")

        // Attacker tampers with client public key
        let clientTampered = WireAuth.verifyTranscriptSignature(
            rsaKey: pubKey,
            clientNonce: clientNonce,
            serverNonce: serverNonce,
            clientPublicKeyRaw: attackerPub,
            serverPublicKeyRaw: serverPub,
            signature: signature
        )
        XCTAssertFalse(clientTampered, "Signature verification MUST fail when client public key is substituted")
    }

    // MARK: - Key Derivation (v2 Directional Keys)

    func testV2DeriveDirectionalKeys() throws {
        let clientPriv = P256.KeyAgreement.PrivateKey()
        let serverPriv = P256.KeyAgreement.PrivateKey()

        let clientNonce = WireAuth.generateClientNonce()
        let serverNonce = WireAuth.generateClientNonce()

        let clientDerived = try WireAuth.deriveDirectionalAESKeys(
            serverPublicKeyRaw: serverPriv.publicKey.x963Representation,
            clientPrivateKey: clientPriv,
            clientNonce: clientNonce,
            serverNonce: serverNonce
        )

        let serverDerived = try WireAuth.deriveDirectionalAESKeys(
            serverPublicKeyRaw: clientPriv.publicKey.x963Representation,
            clientPrivateKey: serverPriv,
            clientNonce: clientNonce,
            serverNonce: serverNonce
        )

        // Client and Server must agree on traffic keys
        XCTAssertEqual(clientDerived.clientToServerKeyRaw, serverDerived.clientToServerKeyRaw)
        XCTAssertEqual(clientDerived.serverToClientKeyRaw, serverDerived.serverToClientKeyRaw)

        // Directional keys must be distinct
        XCTAssertNotEqual(
            clientDerived.clientToServerKeyRaw,
            clientDerived.serverToClientKeyRaw,
            "c2s and s2c keys must not be identical"
        )
    }

    // MARK: - AES-GCM Framing & EstablishedSession

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

    func testDecryptSecureRejectsTamperedCiphertext() throws {
        let key = SymmetricKey(size: .bits256)
        let plaintext = "secure data".data(using: .utf8)!
        var packet = try WireAuth.encryptSecure(key: key, seq: 1, payload: plaintext)

        // Flip one byte in the ciphertext
        packet[packet.count - 1] ^= 0x01

        XCTAssertThrowsError(try WireAuth.decryptSecure(key: key, packet: packet)) { error in
            XCTAssertEqual(error as? WireAuth.SecureChannelError, .decryptionFailed)
        }
    }

    func testEstablishedSessionRoundTrip() throws {
        let c2sKey = SymmetricKey(size: .bits256)
        let s2cKey = SymmetricKey(size: .bits256)
        let serverNonce = WireAuth.generateClientNonce()

        let clientSession = EstablishedSession(
            clientToServerKey: c2sKey,
            serverToClientKey: s2cKey,
            serverNonce: serverNonce
        )

        // Client sends to server: encrypts with c2sKey
        let clientPayload = "hello from client".data(using: .utf8)!
        let clientPacket = try clientSession.encrypt(seq: 10, payload: clientPayload)

        // Server receives: decrypts with c2sKey
        let (serverDecrypted, serverSeq) = try WireAuth.decryptSecure(key: c2sKey, packet: clientPacket)
        XCTAssertEqual(serverDecrypted, clientPayload)
        XCTAssertEqual(serverSeq, 10)

        // Server sends to client: encrypts with s2cKey
        let serverPayload = "hello from server".data(using: .utf8)!
        let serverPacket = try WireAuth.encryptSecure(key: s2cKey, seq: 20, payload: serverPayload)

        // Client receives: decrypts with s2cKey
        let (clientDecrypted, clientSeq) = try clientSession.decrypt(packet: serverPacket)
        XCTAssertEqual(clientDecrypted, serverPayload)
        XCTAssertEqual(clientSeq, 20)
    }

    // MARK: - End-to-End Handshake Flow (v2)

    private final class MockTransport: HandshakeTransport {
        let serverRSAKey: SecKey
        let serverECDHPrivateKey = P256.KeyAgreement.PrivateKey()
        var stage = 0
        var recordedClientNonce: Data?
        var recordedServerNonce: Data?

        init(serverRSAKey: SecKey) {
            self.serverRSAKey = serverRSAKey
        }

        func sendAndReceive(_ packet: Data) async throws -> Data {
            if stage == 0 {
                stage = 1
                // Expect v2 Stage 1 (cmd 101, 20 bytes)
                XCTAssertEqual(packet.count, 20)
                let cmd = packet.prefix(4).withUnsafeBytes { $0.load(as: UInt32.self) }
                XCTAssertEqual(UInt32(littleEndian: cmd), 101)
                let clientNonce = packet.subdata(in: 4..<20)
                self.recordedClientNonce = clientNonce

                let serverNonce = WireAuth.generateClientNonce()
                self.recordedServerNonce = serverNonce
                return serverNonce
            } else if stage == 1 {
                stage = 2
                // Expect v2 Stage 2 (cmd 102, 69 bytes)
                XCTAssertEqual(packet.count, 69)
                let cmd = packet.prefix(4).withUnsafeBytes { $0.load(as: UInt32.self) }
                XCTAssertEqual(UInt32(littleEndian: cmd), 102)
                let clientPub = packet.subdata(in: 4..<69)

                guard let clientNonce = recordedClientNonce,
                      let serverNonce = recordedServerNonce else {
                    fatalError("Missing nonces")
                }
                let serverPub = serverECDHPrivateKey.publicKey.x963Representation

                var transcript = Data()
                transcript.append(clientNonce)
                transcript.append(serverNonce)
                transcript.append(clientPub)
                transcript.append(serverPub)

                var error: Unmanaged<CFError>?
                guard let sig = SecKeyCreateSignature(
                    serverRSAKey,
                    .rsaSignatureMessagePKCS1v15SHA256,
                    transcript as CFData,
                    &error
                ) as Data? else {
                    fatalError("Failed to sign transcript: \(String(describing: error))")
                }

                var response = Data()
                response.append(serverPub)
                response.append(sig)
                return response
            } else {
                fatalError("Unexpected stage: \(stage)")
            }
        }
    }

    func testEstablishV2EndToEnd() async throws {
        let attributes: [String: Any] = [
            kSecAttrKeyType as String: kSecAttrKeyTypeRSA,
            kSecAttrKeySizeInBits as String: 2048,
        ]
        var error: Unmanaged<CFError>?
        guard let privKey = SecKeyCreateRandomKey(attributes as CFDictionary, &error),
              let pubKey = SecKeyCopyPublicKey(privKey) else {
            XCTFail("Failed to generate RSA key")
            return
        }
        guard let pubDer = SecKeyCopyExternalRepresentation(pubKey, &error) as Data? else {
            XCTFail("Failed to export public key")
            return
        }
        let pubB64 = pubDer.base64EncodedString()

        let transport = MockTransport(serverRSAKey: privKey)
        let client = WireAuthClient(serverRSAPublicKeyB64: pubB64)
        let session = try await client.establish(transport: transport)

        XCTAssertEqual(session.serverNonce, transport.recordedServerNonce)

        // Session keys work
        let payload = "handshake complete".data(using: .utf8)!
        let encrypted = try session.encrypt(seq: 1, payload: payload)
        XCTAssertGreaterThan(encrypted.count, payload.count)
    }

    // MARK: - Legacy v1 Flow

    func testLegacyV1PacketFormatAndDerive() throws {
        let nonce = WireAuth.generateClientNonce()
        let packet = WireAuth.buildStage1Packet(clientNonce: nonce)
        XCTAssertEqual(packet.count, 20)
        let cmd = packet.prefix(4).withUnsafeBytes { $0.load(as: UInt32.self) }
        XCTAssertEqual(UInt32(littleEndian: cmd), 1)

        let clientPriv = P256.KeyAgreement.PrivateKey()
        let serverPriv = P256.KeyAgreement.PrivateKey()
        let serverNonce = WireAuth.generateClientNonce()

        let clientDerived = try WireAuth.deriveSharedAESKey(
            serverPublicKeyRaw: serverPriv.publicKey.x963Representation,
            clientPrivateKey: clientPriv,
            clientNonce: nonce,
            serverNonce: serverNonce
        )
        let serverDerived = try WireAuth.deriveSharedAESKey(
            serverPublicKeyRaw: clientPriv.publicKey.x963Representation,
            clientPrivateKey: serverPriv,
            clientNonce: nonce,
            serverNonce: serverNonce
        )
        XCTAssertEqual(clientDerived.raw, serverDerived.raw)
    }

    // MARK: - Resume Proof (HMAC chain)

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

        XCTAssertEqual(proof1.proofA, proof2.proofA)
        XCTAssertEqual(proof1.proofB, proof2.proofB)

        // authKeyID must be little-endian per HANDSHAKE_SPEC.md
        let expectedBytes = withUnsafeBytes(of: UInt64(7).littleEndian) { Data($0) }
        XCTAssertEqual(proof1.authKeyIDBytes, expectedBytes)
    }

    static let testServerPublicKeyB64 = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvpjDcmWXSPVTEQWrYMzNPoHvzF9i5f2XPobr58FVMuz0LTcuSStj28C3iHKiY/Si0B4Px/l7wGWlsHH/0/8IiBCBpOCR2WmMOmWoRaGh9niXSUS01Yw6u+SftI0DRqnyroFmsmVg3nvwGoMt231o3Iwk/bQFjBiEU4L+alDO0Db7TDZRzE6u9qceuMQ7rLxj8qguxTCxHYr50CeCBPEC9WUvc1Fg3Xa67Hc3YxKr+vkKYlVXbH59mVG2tWbw+XicvqnNZy2iPNzy0Q2XYQ1vvrGSLW5LXjoE4NOWEp7qlqytYXbXvlUSYNt42T93BhYLKTyGpiyob1b6KxYCYYoBywIDAQAB"
}