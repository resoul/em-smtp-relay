import Foundation
import CryptoKit
import Security

/// A transport-agnostic client-side implementation of the wireauth
/// handshake protocol: RSA-signed challenge/response, ECDH (P-256) key
/// exchange, and an AES-256-GCM secured channel afterward, plus an
/// HMAC-based session-resume proof.
///
/// This is **transport security**, not end-to-end encryption between
/// users — it secures the link between this client and your server
/// (similar in spirit to TLS). See HANDSHAKE_SPEC.md for the exact wire
/// format this implements; the companion Go server package and JS client
/// package implement the identical protocol.
///
/// ## Setup
///
/// Call `WireAuth.configure` once at app startup, before performing any
/// handshake:
///
/// ```swift
/// WireAuth.configure(
///     serverRSAPublicKeyB64: "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...",
///     resumeProofSalt: mySaltData // only needed if you use computeResumeProof
/// )
/// ```
///
/// The RSA key is the server's **public** key (SPKI/DER, base64) — it is
/// not a secret, but you should obtain it authentically (bundle it in your
/// app's config, pin it, etc.) rather than trust an unauthenticated source
/// at runtime. Previous versions of this code shipped the key
/// XOR-obfuscated in the binary; that provided no real protection (public
/// keys aren't secrets, and the obfuscation is trivially reversible with a
/// disassembler) — explicit configuration is both simpler and equally
/// secure, since the actual trust boundary is "did this app get the
/// authentic key", not "is the key hidden."
public enum WireAuth {

    // MARK: - Configuration

    private struct Configuration {
        let rsaPublicKeyB64: String
        let resumeProofSalt: Data?
    }

    private static var configuration: Configuration?

    /// Configures WireAuth with the server's public key. Call this once at
    /// app startup before performing any handshake.
    ///
    /// - Parameters:
    ///   - serverRSAPublicKeyB64: The server's RSA public key, SPKI/DER
    ///     encoded and base64-stringified. Not a secret — but obtain it
    ///     authentically (app config, pinning, etc).
    ///   - resumeProofSalt: App-specific salt used in the resume-proof HMAC
    ///     chain, shared out-of-band with your server. Only required if you
    ///     call `computeResumeProof`.
    public static func configure(serverRSAPublicKeyB64: String, resumeProofSalt: Data? = nil) {
        configuration = Configuration(
            rsaPublicKeyB64: serverRSAPublicKeyB64,
            resumeProofSalt: resumeProofSalt
        )
    }

    public enum HandshakeError: Error {
        case notConfigured
        case invalidRSAKey
        case packetTooShort
        case invalidSignature
        case invalidServerPublicKey
        case resumeSaltNotConfigured
    }

    // MARK: - RSA (Stage 1)

    public static func importServerRSAKey() throws -> SecKey {
        guard let configuration else {
            throw HandshakeError.notConfigured
        }
        guard let derData = Data(base64Encoded: configuration.rsaPublicKeyB64) else {
            throw HandshakeError.invalidRSAKey
        }
        let attributes: [String: Any] = [
            kSecAttrKeyType as String: kSecAttrKeyTypeRSA,
            kSecAttrKeyClass as String: kSecAttrKeyClassPublic,
        ]
        var error: Unmanaged<CFError>?
        guard let key = SecKeyCreateWithData(derData as CFData, attributes as CFDictionary, &error) else {
            throw HandshakeError.invalidRSAKey
        }
        return key
    }

    public static func generateClientNonce() -> Data {
        var nonce = Data(count: 16)
        _ = nonce.withUnsafeMutableBytes { SecRandomCopyBytes(kSecRandomDefault, 16, $0.baseAddress!) }
        return nonce
    }

    public static func buildStage1Packet(clientNonce: Data) -> Data {
        var packet = Data()
        packet.append(uint32LE(1))
        packet.append(clientNonce)
        return packet
    }

    public static func parseStage1Response(_ response: Data) throws -> (serverNonce: Data, signature: Data) {
        guard response.count >= 16 + 256 else { throw HandshakeError.packetTooShort }
        let serverNonce = response.subdata(in: 0..<16)
        let signature = response.subdata(in: 16..<(16 + 256))
        return (serverNonce, signature)
    }

    public static func verifyServerSignature(
        rsaKey: SecKey,
        clientNonce: Data,
        serverNonce: Data,
        signature: Data
    ) -> Bool {
        var dataToVerify = Data()
        dataToVerify.append(clientNonce)
        dataToVerify.append(serverNonce)

        var error: Unmanaged<CFError>?
        let ok = SecKeyVerifySignature(
            rsaKey,
            .rsaSignatureMessagePKCS1v15SHA256,
            dataToVerify as CFData,
            signature as CFData,
            &error
        )
        return ok
    }

    // MARK: - ECDH (Stage 2)

    public static func buildStage2Packet(clientPublicKeyRaw: Data) -> Data {
        var packet = Data()
        packet.append(uint32LE(2))
        packet.append(clientPublicKeyRaw)
        return packet
    }

    public struct DerivedAESKey {
        public let key: SymmetricKey
        public let raw: Data

        public init(key: SymmetricKey, raw: Data) {
            self.key = key
            self.raw = raw
        }
    }

    public static func deriveSharedAESKey(
        serverPublicKeyRaw: Data,
        clientPrivateKey: P256.KeyAgreement.PrivateKey,
        clientNonce: Data,
        serverNonce: Data
    ) throws -> DerivedAESKey {
        let serverPublicKey: P256.KeyAgreement.PublicKey
        do {
            serverPublicKey = try P256.KeyAgreement.PublicKey(x963Representation: serverPublicKeyRaw)
        } catch {
            throw HandshakeError.invalidServerPublicKey
        }

        let sharedSecret = try clientPrivateKey.sharedSecretFromKeyAgreement(with: serverPublicKey)

        var kdfMaterial = Data()
        sharedSecret.withUnsafeBytes { kdfMaterial.append(contentsOf: $0) }
        kdfMaterial.append(clientNonce)
        kdfMaterial.append(serverNonce)

        let finalKeyHash = Data(SHA256.hash(data: kdfMaterial))
        let key = SymmetricKey(data: finalKeyHash)
        return DerivedAESKey(key: key, raw: finalKeyHash)
    }

    // MARK: - AES-GCM secure channel (Stage 3+)

    public enum SecureChannelError: Error {
        case packetTooShort
        case decryptionFailed
    }

    public static func encryptSecure(key: SymmetricKey, seq: UInt64, payload: Data) throws -> Data {
        var seqBytes = Data(count: 8)
        seqBytes.withUnsafeMutableBytes { $0.storeBytes(of: seq.bigEndian, as: UInt64.self) }

        let nonceData = generateNonce12()
        let nonce = try AES.GCM.Nonce(data: nonceData)

        let sealed = try AES.GCM.seal(payload, using: key, nonce: nonce, authenticating: seqBytes)

        var packet = Data()
        packet.append(seqBytes)
        packet.append(nonceData)
        packet.append(sealed.ciphertext)
        packet.append(sealed.tag)
        return packet
    }

    public static func decryptSecure(key: SymmetricKey, packet: Data) throws -> (plaintext: Data, seq: UInt64) {
        guard packet.count >= 8 + 12 + 16 else { throw SecureChannelError.packetTooShort }

        let seqBytes = packet.subdata(in: 0..<8)
        let nonceData = packet.subdata(in: 8..<20)
        let ciphertext = packet.subdata(in: 20..<(packet.count - 16))
        let tag = packet.subdata(in: (packet.count - 16)..<packet.count)

        let seq = seqBytes.withUnsafeBytes { $0.load(as: UInt64.self).bigEndian }

        let nonce = try AES.GCM.Nonce(data: nonceData)
        let sealedBox = try AES.GCM.SealedBox(nonce: nonce, ciphertext: ciphertext, tag: tag)

        do {
            let plaintext = try AES.GCM.open(sealedBox, using: key, authenticating: seqBytes)
            return (plaintext, seq)
        } catch {
            throw SecureChannelError.decryptionFailed
        }
    }

    // MARK: - Resume proof (HMAC chain)

    public struct ResumeProof {
        public let authKeyIDBytes: Data
        public let proofA: Data
        public let proofB: Data

        public init(authKeyIDBytes: Data, proofA: Data, proofB: Data) {
            self.authKeyIDBytes = authKeyIDBytes
            self.proofA = proofA
            self.proofB = proofB
        }
    }

    /// Computes the two-step HMAC resume proof to send to the server when
    /// resuming a previous session instead of re-running the full
    /// handshake.
    ///
    /// - Note: `authKeyID` is encoded **little-endian** here — this differs
    ///   from the big-endian `seq` used in the AEAD framing above. See
    ///   HANDSHAKE_SPEC.md for the full byte layout; this matches the Go
    ///   server and JS client implementations exactly.
    ///
    /// Requires `resumeProofSalt` to have been passed to `configure(_:_:)`.
    public static func computeResumeProof(
        authKeyID: UInt64,
        masterKey: SymmetricKey,
        serverNonce: Data
    ) throws -> ResumeProof {
        guard let configuration else {
            throw HandshakeError.notConfigured
        }
        guard let resumeProofSalt = configuration.resumeProofSalt else {
            throw HandshakeError.resumeSaltNotConfigured
        }

        var authKeyIDBytes = Data(count: 8)
        authKeyIDBytes.withUnsafeMutableBytes { $0.storeBytes(of: authKeyID.littleEndian, as: UInt64.self) }

        let proofA = Data(HMAC<SHA256>.authenticationCode(for: resumeProofSalt, using: masterKey))

        let keyA = SymmetricKey(data: proofA)
        var dataToSign = Data()
        dataToSign.append(authKeyIDBytes)
        dataToSign.append(serverNonce)
        let proofB = Data(HMAC<SHA256>.authenticationCode(for: dataToSign, using: keyA))

        return ResumeProof(authKeyIDBytes: authKeyIDBytes, proofA: proofA, proofB: proofB)
    }

    // MARK: - Helpers

    private static func uint32LE(_ value: UInt32) -> Data {
        var v = value.littleEndian
        return Data(bytes: &v, count: 4)
    }

    private static func generateNonce12() -> Data {
        var nonce = Data(count: 12)
        _ = nonce.withUnsafeMutableBytes { SecRandomCopyBytes(kSecRandomDefault, 12, $0.baseAddress!) }
        return nonce
    }
}