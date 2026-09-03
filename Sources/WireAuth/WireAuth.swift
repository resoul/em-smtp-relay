import Foundation
import CryptoKit
import Security

/// A transport-agnostic client-side implementation of the wireauth
/// handshake protocol (v2 default, with legacy v1 support): RSA-signed
/// transcript / key exchange, ECDH (P-256) key agreement, and an AES-256-GCM
/// secured channel with direction-separated traffic keys, plus an HMAC-based
/// session-resume proof.
///
/// This is **transport security**, not end-to-end encryption between
/// users — it secures the link between this client and your server
/// (similar in spirit to TLS). See HANDSHAKE_SPEC.md for the exact wire
/// format this implements; the companion Go server package and JS client
/// package implement the identical protocol.
public enum WireAuth {

    // MARK: - Configuration

    private struct Configuration {
        let rsaPublicKeyB64: String
        let resumeProofSalt: Data?
    }

    private static var configuration: Configuration?

    /// Configures WireAuth globally with the server's public key. Call this
    /// once at app startup before performing any handshake with global helpers.
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

    public enum HandshakeError: Error, Equatable {
        case notConfigured
        case invalidRSAKey
        case packetTooShort
        case invalidSignature
        case invalidServerPublicKey
        case resumeSaltNotConfigured
    }

    // MARK: - RSA Key Import & Nonce

    /// Imports the globally configured server RSA public key.
    public static func importServerRSAKey() throws -> SecKey {
        guard let configuration else {
            throw HandshakeError.notConfigured
        }
        return try importServerRSAKey(serverRSAPublicKeyB64: configuration.rsaPublicKeyB64)
    }

    /// Imports a base64-encoded DER (SPKI) RSA public key for signature verification.
    public static func importServerRSAKey(serverRSAPublicKeyB64: String) throws -> SecKey {
        guard let derData = Data(base64Encoded: serverRSAPublicKeyB64) else {
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

    /// Generates 16 cryptographically secure random bytes for client nonce.
    public static func generateClientNonce() -> Data {
        var nonce = Data(count: 16)
        _ = nonce.withUnsafeMutableBytes { SecRandomCopyBytes(kSecRandomDefault, 16, $0.baseAddress!) }
        return nonce
    }

    // MARK: - Protocol v2 (Current)

    /// Builds the v2 Stage 1 packet: cmd (101 LE, 4 bytes) + clientNonce (16 bytes) = 20 bytes.
    public static func buildStage1PacketV2(clientNonce: Data) -> Data {
        var packet = Data(capacity: 4 + clientNonce.count)
        packet.append(uint32LE(101))
        packet.append(clientNonce)
        return packet
    }

    /// Parses the v2 Stage 1 response from server: 16 bytes serverNonce.
    public static func parseStage1ResponseV2(_ response: Data) throws -> Data {
        guard response.count == 16 else {
            throw HandshakeError.packetTooShort
        }
        return response
    }

    /// Builds the v2 Stage 2 packet: cmd (102 LE, 4 bytes) + clientPublicKeyRaw (65 bytes) = 69 bytes.
    public static func buildStage2PacketV2(clientPublicKeyRaw: Data) -> Data {
        var packet = Data(capacity: 4 + clientPublicKeyRaw.count)
        packet.append(uint32LE(102))
        packet.append(clientPublicKeyRaw)
        return packet
    }

    /// Parses the v2 Stage 2 response: serverPublicKeyRaw (65 bytes) + rsaSignature (256 bytes) = 321 bytes.
    public static func parseStage2ResponseV2(_ response: Data) throws -> (serverPublicKeyRaw: Data, signature: Data) {
        guard response.count == 65 + 256 else {
            throw HandshakeError.packetTooShort
        }
        let serverPublicKeyRaw = response.subdata(in: 0..<65)
        let signature = response.subdata(in: 65..<(65 + 256))
        return (serverPublicKeyRaw, signature)
    }

    /// Verifies the v2 transcript signature: RSA-PKCS1v15-SHA256 over
    /// `client_nonce ‖ server_nonce ‖ client_pubkey ‖ server_pubkey`.
    public static func verifyTranscriptSignature(
        rsaKey: SecKey,
        clientNonce: Data,
        serverNonce: Data,
        clientPublicKeyRaw: Data,
        serverPublicKeyRaw: Data,
        signature: Data
    ) -> Bool {
        var dataToVerify = Data(capacity: clientNonce.count + serverNonce.count + clientPublicKeyRaw.count + serverPublicKeyRaw.count)
        dataToVerify.append(clientNonce)
        dataToVerify.append(serverNonce)
        dataToVerify.append(clientPublicKeyRaw)
        dataToVerify.append(serverPublicKeyRaw)

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

    /// Derives direction-separated AES-256-GCM traffic keys for protocol v2:
    /// `c2s_key` and `s2c_key` via HKDF-SHA256 with salt `client_nonce ‖ server_nonce`.
    public static func deriveDirectionalAESKeys(
        serverPublicKeyRaw: Data,
        clientPrivateKey: P256.KeyAgreement.PrivateKey,
        clientNonce: Data,
        serverNonce: Data
    ) throws -> DerivedDirectionalAESKeys {
        let serverPublicKey: P256.KeyAgreement.PublicKey
        do {
            serverPublicKey = try P256.KeyAgreement.PublicKey(x963Representation: serverPublicKeyRaw)
        } catch {
            throw HandshakeError.invalidServerPublicKey
        }

        let sharedSecret = try clientPrivateKey.sharedSecretFromKeyAgreement(with: serverPublicKey)

        var salt = Data(capacity: clientNonce.count + serverNonce.count)
        salt.append(clientNonce)
        salt.append(serverNonce)

        let c2sInfo = "wireauth/v2/client-to-server".data(using: .utf8)!
        let s2cInfo = "wireauth/v2/server-to-client".data(using: .utf8)!

        let clientToServerKey = sharedSecret.hkdfDerivedSymmetricKey(
            using: SHA256.self,
            salt: salt,
            sharedInfo: c2sInfo,
            outputByteCount: 32
        )
        let serverToClientKey = sharedSecret.hkdfDerivedSymmetricKey(
            using: SHA256.self,
            salt: salt,
            sharedInfo: s2cInfo,
            outputByteCount: 32
        )

        let clientToServerKeyRaw = clientToServerKey.withUnsafeBytes { Data($0) }
        let serverToClientKeyRaw = serverToClientKey.withUnsafeBytes { Data($0) }

        return DerivedDirectionalAESKeys(
            clientToServerKey: clientToServerKey,
            serverToClientKey: serverToClientKey,
            clientToServerKeyRaw: clientToServerKeyRaw,
            serverToClientKeyRaw: serverToClientKeyRaw
        )
    }

    // MARK: - Protocol v1 (Deprecated)

    @available(*, deprecated, message: "Use buildStage1PacketV2. v1 does not authenticate ECDH public keys.")
    public static func buildStage1Packet(clientNonce: Data) -> Data {
        var packet = Data()
        packet.append(uint32LE(1))
        packet.append(clientNonce)
        return packet
    }

    @available(*, deprecated, message: "Use parseStage1ResponseV2. v1 does not authenticate ECDH public keys.")
    public static func parseStage1Response(_ response: Data) throws -> (serverNonce: Data, signature: Data) {
        guard response.count >= 16 + 256 else { throw HandshakeError.packetTooShort }
        let serverNonce = response.subdata(in: 0..<16)
        let signature = response.subdata(in: 16..<(16 + 256))
        return (serverNonce, signature)
    }

    @available(*, deprecated, message: "Use verifyTranscriptSignature. v1 does not authenticate ECDH public keys.")
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

    @available(*, deprecated, message: "Use buildStage2PacketV2. v1 does not authenticate ECDH public keys.")
    public static func buildStage2Packet(clientPublicKeyRaw: Data) -> Data {
        var packet = Data()
        packet.append(uint32LE(2))
        packet.append(clientPublicKeyRaw)
        return packet
    }

    @available(*, deprecated, message: "Use deriveDirectionalAESKeys. v1 derives a single bidirectional key.")
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

    public enum SecureChannelError: Error, Equatable {
        case packetTooShort
        case decryptionFailed
    }

    public static func encryptSecure(key: SymmetricKey, seq: UInt64, payload: Data) throws -> Data {
        var seqBytes = Data(count: 8)
        seqBytes.withUnsafeMutableBytes { $0.storeBytes(of: seq.bigEndian, as: UInt64.self) }

        let nonceData = generateNonce12()
        let nonce = try AES.GCM.Nonce(data: nonceData)

        let sealed = try AES.GCM.seal(payload, using: key, nonce: nonce, authenticating: seqBytes)

        var packet = Data(capacity: 8 + 12 + sealed.ciphertext.count + sealed.tag.count)
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

    public struct ResumeProof: Equatable {
        public let authKeyIDBytes: Data
        public let proofA: Data
        public let proofB: Data

        public init(authKeyIDBytes: Data, proofA: Data, proofB: Data) {
            self.authKeyIDBytes = authKeyIDBytes
            self.proofA = proofA
            self.proofB = proofB
        }
    }

    /// Computes the two-step HMAC resume proof using the globally configured resumeProofSalt.
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
        return computeResumeProof(authKeyID: authKeyID, masterKey: masterKey, serverNonce: serverNonce, sessionSalt: resumeProofSalt)
    }

    /// Computes the two-step HMAC resume proof with an explicit session salt.
    public static func computeResumeProof(
        authKeyID: UInt64,
        masterKey: SymmetricKey,
        serverNonce: Data,
        sessionSalt: Data
    ) -> ResumeProof {
        var authKeyIDBytes = Data(count: 8)
        authKeyIDBytes.withUnsafeMutableBytes { $0.storeBytes(of: authKeyID.littleEndian, as: UInt64.self) }

        let proofA = Data(HMAC<SHA256>.authenticationCode(for: sessionSalt, using: masterKey))

        let keyA = SymmetricKey(data: proofA)
        var dataToSign = Data(capacity: 8 + serverNonce.count)
        dataToSign.append(authKeyIDBytes)
        dataToSign.append(serverNonce)
        let proofB = Data(HMAC<SHA256>.authenticationCode(for: dataToSign, using: keyA))

        return ResumeProof(authKeyIDBytes: authKeyIDBytes, proofA: proofA, proofB: proofB)
    }

    // MARK: - High-Level Establish Flow

    /// Runs the handshake over the given transport using the globally configured RSA key.
    /// Uses protocol v2 by default (full-transcript signing). Set `useLegacyV1 = true` only
    /// during migration windows for legacy servers over TLS.
    public static func establish(
        transport: HandshakeTransport,
        useLegacyV1: Bool = false
    ) async throws -> EstablishedSession {
        let rsaKey = try importServerRSAKey()
        if useLegacyV1 {
            return try await establishV1(rsaKey: rsaKey, transport: transport)
        }
        return try await establishV2(rsaKey: rsaKey, transport: transport)
    }

    public static func establishV2(
        rsaKey: SecKey,
        transport: HandshakeTransport
    ) async throws -> EstablishedSession {
        // Stage 1: nonce exchange
        let clientNonce = generateClientNonce()
        let stage1Response = try await transport.sendAndReceive(buildStage1PacketV2(clientNonce: clientNonce))
        let serverNonce = try parseStage1ResponseV2(stage1Response)

        // Stage 2: ECDH key exchange + transcript signature
        let clientPrivateKey = P256.KeyAgreement.PrivateKey()
        let clientPublicKeyRaw = clientPrivateKey.publicKey.x963Representation
        let stage2Response = try await transport.sendAndReceive(buildStage2PacketV2(clientPublicKeyRaw: clientPublicKeyRaw))
        let (serverPublicKeyRaw, signature) = try parseStage2ResponseV2(stage2Response)

        guard verifyTranscriptSignature(
            rsaKey: rsaKey,
            clientNonce: clientNonce,
            serverNonce: serverNonce,
            clientPublicKeyRaw: clientPublicKeyRaw,
            serverPublicKeyRaw: serverPublicKeyRaw,
            signature: signature
        ) else {
            throw HandshakeError.invalidSignature
        }

        let derived = try deriveDirectionalAESKeys(
            serverPublicKeyRaw: serverPublicKeyRaw,
            clientPrivateKey: clientPrivateKey,
            clientNonce: clientNonce,
            serverNonce: serverNonce
        )

        return EstablishedSession(
            clientToServerKey: derived.clientToServerKey,
            serverToClientKey: derived.serverToClientKey,
            serverNonce: serverNonce
        )
    }

    @available(*, deprecated, message: "Use establishV2. v1 does not authenticate ECDH public keys.")
    public static func establishV1(
        rsaKey: SecKey,
        transport: HandshakeTransport
    ) async throws -> EstablishedSession {
        let clientNonce = generateClientNonce()
        let stage1Response = try await transport.sendAndReceive(buildStage1Packet(clientNonce: clientNonce))
        let (serverNonce, signature) = try parseStage1Response(stage1Response)

        guard verifyServerSignature(
            rsaKey: rsaKey,
            clientNonce: clientNonce,
            serverNonce: serverNonce,
            signature: signature
        ) else {
            throw HandshakeError.invalidSignature
        }

        let clientPrivateKey = P256.KeyAgreement.PrivateKey()
        let clientPublicKeyRaw = clientPrivateKey.publicKey.x963Representation
        let stage2Response = try await transport.sendAndReceive(buildStage2Packet(clientPublicKeyRaw: clientPublicKeyRaw))

        let derived = try deriveSharedAESKey(
            serverPublicKeyRaw: stage2Response,
            clientPrivateKey: clientPrivateKey,
            clientNonce: clientNonce,
            serverNonce: serverNonce
        )

        return EstablishedSession(
            clientToServerKey: derived.key,
            serverToClientKey: derived.key,
            serverNonce: serverNonce
        )
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

// MARK: - Supporting Types

/// Transport protocol abstraction: given an outgoing binary packet,
/// sends it and returns the next binary response received from the server.
public protocol HandshakeTransport {
    func sendAndReceive(_ packet: Data) async throws -> Data
}

/// Direction-separated AES-256-GCM traffic keys derived from P-256 ECDH in protocol v2.
public struct DerivedDirectionalAESKeys {
    public let clientToServerKey: SymmetricKey
    public let serverToClientKey: SymmetricKey
    public let clientToServerKeyRaw: Data
    public let serverToClientKeyRaw: Data

    /// Backward-compatibility alias for `clientToServerKey`.
    public var aesKey: SymmetricKey { clientToServerKey }

    public init(
        clientToServerKey: SymmetricKey,
        serverToClientKey: SymmetricKey,
        clientToServerKeyRaw: Data,
        serverToClientKeyRaw: Data
    ) {
        self.clientToServerKey = clientToServerKey
        self.serverToClientKey = serverToClientKey
        self.clientToServerKeyRaw = clientToServerKeyRaw
        self.serverToClientKeyRaw = serverToClientKeyRaw
    }
}

/// Legacy single bidirectional AES key from protocol v1.
@available(*, deprecated, message: "Use DerivedDirectionalAESKeys for protocol v2.")
public struct DerivedAESKey {
    public let key: SymmetricKey
    public let raw: Data

    public init(key: SymmetricKey, raw: Data) {
        self.key = key
        self.raw = raw
    }
}

/// An established secure session holding direction-separated keys and helpers
/// to encrypt outgoing packets and decrypt incoming packets.
public struct EstablishedSession {
    public let clientToServerKey: SymmetricKey
    public let serverToClientKey: SymmetricKey
    public let serverNonce: Data

    /// Backward-compatibility alias for `clientToServerKey`.
    public var aesKey: SymmetricKey { clientToServerKey }

    public init(
        clientToServerKey: SymmetricKey,
        serverToClientKey: SymmetricKey,
        serverNonce: Data
    ) {
        self.clientToServerKey = clientToServerKey
        self.serverToClientKey = serverToClientKey
        self.serverNonce = serverNonce
    }

    /// Encrypts an outgoing packet for client-to-server traffic.
    public func encrypt(seq: UInt64, payload: Data) throws -> Data {
        try WireAuth.encryptSecure(key: clientToServerKey, seq: seq, payload: payload)
    }

    /// Decrypts an incoming packet from server-to-client traffic.
    public func decrypt(packet: Data) throws -> (plaintext: Data, seq: UInt64) {
        try WireAuth.decryptSecure(key: serverToClientKey, packet: packet)
    }
}

/// Configurable client for running the wireauth handshake and managing sessions.
public struct WireAuthClient {
    public struct Configuration {
        public var serverRSAPublicKeyB64: String
        public var resumeProofSalt: Data?
        public var useLegacyV1: Bool

        public init(
            serverRSAPublicKeyB64: String,
            resumeProofSalt: Data? = nil,
            useLegacyV1: Bool = false
        ) {
            self.serverRSAPublicKeyB64 = serverRSAPublicKeyB64
            self.resumeProofSalt = resumeProofSalt
            self.useLegacyV1 = useLegacyV1
        }
    }

    public let configuration: Configuration

    public init(configuration: Configuration) {
        self.configuration = configuration
    }

    public init(
        serverRSAPublicKeyB64: String,
        resumeProofSalt: Data? = nil,
        useLegacyV1: Bool = false
    ) {
        self.configuration = Configuration(
            serverRSAPublicKeyB64: serverRSAPublicKeyB64,
            resumeProofSalt: resumeProofSalt,
            useLegacyV1: useLegacyV1
        )
    }

    /// Establishes a session over the given transport.
    public func establish(transport: HandshakeTransport) async throws -> EstablishedSession {
        let rsaKey = try WireAuth.importServerRSAKey(serverRSAPublicKeyB64: configuration.serverRSAPublicKeyB64)
        if configuration.useLegacyV1 {
            return try await WireAuth.establishV1(rsaKey: rsaKey, transport: transport)
        }
        return try await WireAuth.establishV2(rsaKey: rsaKey, transport: transport)
    }

    /// Computes a session-resume proof for a previously established session.
    public func computeResumeProofFor(
        authKeyID: UInt64,
        masterKey: SymmetricKey,
        serverNonce: Data
    ) throws -> WireAuth.ResumeProof {
        guard let salt = configuration.resumeProofSalt else {
            throw WireAuth.HandshakeError.resumeSaltNotConfigured
        }
        return WireAuth.computeResumeProof(
            authKeyID: authKeyID,
            masterKey: masterKey,
            serverNonce: serverNonce,
            sessionSalt: salt
        )
    }
}