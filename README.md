# WireAuth

A Swift package implementing the client side of the `wireauth` handshake
protocol for iOS, macOS, and watchOS: RSA-signed challenge/response, ECDH (P-256) key
exchange, and an AES-256-GCM secured channel with direction-separated traffic keys,
plus an HMAC-based session-resume proof. Built on CryptoKit and Security — no
third-party dependencies.

This is the same protocol implemented by the companion
[Go server package](../wireauth) and [JS/TS client package](../wireauth-js)
— all three speak an identical wire format, documented in
[`HANDSHAKE_SPEC.md`](./HANDSHAKE_SPEC.md).

Like its Go/JS counterparts, this is **transport security**, not end-to-end
encryption between users. It secures the link between this client and your
server (similar in spirit to TLS) and is meant to sit alongside your own
authentication — not replace it. If you need E2E encryption between users,
look at libsignal instead.

> **Security note (protocol v2):** The handshake now signs the full
> transcript — both nonces *and* both ECDH public keys — closing a gap in
> the original protocol where the RSA signature covered only the nonces,
> leaving the ECDH exchange itself unauthenticated against an active
> network attacker. See the security advisory at the top of
> `HANDSHAKE_SPEC.md`. **Protocol v2 is used by default.** If you are
> communicating with a legacy server during migration, pass `useLegacyV1: true`
> (only over TLS).

## Install

Add via Swift Package Manager:

```swift
dependencies: [
    .package(url: "https://github.com/resoul/wireauth-swift.git", from: "0.2.0")
]
```

Or in Xcode: **File → Add Package Dependencies…** and paste the repo URL.

Supports iOS 13+, macOS 10.15+, and watchOS 8+.

## Quick start (High-Level Client)

```swift
import WireAuth

// 1. Create a client with the server's public key (SPKI/DER, base64)
let client = WireAuthClient(
    serverRSAPublicKeyB64: "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A..."
)

// 2. Implement HandshakeTransport for your connection (WebSocket, etc.)
struct WebSocketTransport: HandshakeTransport {
    let task: URLSessionWebSocketTask

    func sendAndReceive(_ packet: Data) async throws -> Data {
        try await task.send(.data(packet))
        let message = try await task.receive()
        guard case .data(let data) = message else {
            throw WireAuth.HandshakeError.packetTooShort
        }
        return data
    }
}

// 3. Establish the secure session (v2 by default)
let session = try await client.establish(transport: WebSocketTransport(task: wsTask))

// 4. Encrypt outgoing traffic (seq must be strictly increasing)
let outgoing = try session.encrypt(seq: 1, payload: "hello".data(using: .utf8)!)
try await wsTask.send(.data(outgoing))

// 5. Decrypt incoming traffic
let (plaintext, seq) = try session.decrypt(packet: incomingData)
print("Received #\(seq): \(String(data: plaintext, encoding: .utf8) ?? "")")
```

## Low-Level Primitives (Manual Flow)

If you prefer step-by-step control instead of `WireAuthClient`:

```swift
import WireAuth
import CryptoKit

let rsaKey = try WireAuth.importServerRSAKey(serverRSAPublicKeyB64: serverKeyB64)

// --- Stage 1: Nonce exchange ---
let clientNonce = WireAuth.generateClientNonce()
let stage1Packet = WireAuth.buildStage1PacketV2(clientNonce: clientNonce)
let stage1Response = try await transport.sendAndReceive(stage1Packet)
let serverNonce = try WireAuth.parseStage1ResponseV2(stage1Response)

// --- Stage 2: ECDH key exchange + transcript signature ---
let clientPrivateKey = P256.KeyAgreement.PrivateKey()
let clientPublicKeyRaw = clientPrivateKey.publicKey.x963Representation
let stage2Packet = WireAuth.buildStage2PacketV2(clientPublicKeyRaw: clientPublicKeyRaw)
let stage2Response = try await transport.sendAndReceive(stage2Packet)
let (serverPublicKeyRaw, signature) = try WireAuth.parseStage2ResponseV2(stage2Response)

// Verify transcript signature over: client_nonce ‖ server_nonce ‖ client_pub ‖ server_pub
guard WireAuth.verifyTranscriptSignature(
    rsaKey: rsaKey,
    clientNonce: clientNonce,
    serverNonce: serverNonce,
    clientPublicKeyRaw: clientPublicKeyRaw,
    serverPublicKeyRaw: serverPublicKeyRaw,
    signature: signature
) else {
    throw WireAuth.HandshakeError.invalidSignature
}

// Derive directional keys
let keys = try WireAuth.deriveDirectionalAESKeys(
    serverPublicKeyRaw: serverPublicKeyRaw,
    clientPrivateKey: clientPrivateKey,
    clientNonce: clientNonce,
    serverNonce: serverNonce
)

// Encrypt with clientToServerKey, decrypt with serverToClientKey
let encrypted = try WireAuth.encryptSecure(key: keys.clientToServerKey, seq: 1, payload: payload)
let (decrypted, seq) = try WireAuth.decryptSecure(key: keys.serverToClientKey, packet: response)
```

## API reference

### High-level Client

```swift
let client = WireAuthClient(
    serverRSAPublicKeyB64: String,
    resumeProofSalt: Data? = nil,
    useLegacyV1: Bool = false
)

let session = try await client.establish(transport: HandshakeTransport)
session.clientToServerKey // SymmetricKey (AES-256-GCM)
session.serverToClientKey // SymmetricKey (AES-256-GCM)
session.serverNonce       // Data (16 bytes)
try session.encrypt(seq: UInt64, payload: Data) -> Data
try session.decrypt(packet: Data) -> (plaintext: Data, seq: UInt64)

// Resume proof computation:
let proof = try client.computeResumeProofFor(authKeyID: UInt64, masterKey: SymmetricKey, serverNonce: Data)
// proof.authKeyIDBytes, proof.proofA, proof.proofB
```

### Protocol v2 Primitives

```swift
// Stage 1 (cmd: 101)
WireAuth.generateClientNonce() -> Data // 16 random bytes
WireAuth.buildStage1PacketV2(clientNonce: Data) -> Data // 20 bytes
try WireAuth.parseStage1ResponseV2(_ response: Data) -> Data // 16 bytes server_nonce

// Stage 2 (cmd: 102)
WireAuth.buildStage2PacketV2(clientPublicKeyRaw: Data) -> Data // 69 bytes
try WireAuth.parseStage2ResponseV2(_ response: Data) -> (serverPublicKeyRaw: Data, signature: Data)
WireAuth.verifyTranscriptSignature(
    rsaKey: SecKey,
    clientNonce: Data,
    serverNonce: Data,
    clientPublicKeyRaw: Data,
    serverPublicKeyRaw: Data,
    signature: Data
) -> Bool

// Key derivation
try WireAuth.deriveDirectionalAESKeys(
    serverPublicKeyRaw: Data,
    clientPrivateKey: P256.KeyAgreement.PrivateKey,
    clientNonce: Data,
    serverNonce: Data
) -> DerivedDirectionalAESKeys
// .clientToServerKey: SymmetricKey
// .serverToClientKey: SymmetricKey
```

### AEAD Framing & Session Resume

```swift
try WireAuth.encryptSecure(key: SymmetricKey, seq: UInt64, payload: Data) -> Data
try WireAuth.decryptSecure(key: SymmetricKey, packet: Data) -> (plaintext: Data, seq: UInt64)

try WireAuth.computeResumeProof(authKeyID: UInt64, masterKey: SymmetricKey, serverNonce: Data) -> WireAuth.ResumeProof
```

## Migrating from v1

Protocol v2 is enabled by default. To connect to an unmigrated server during a transition period:

```swift
let client = WireAuthClient(
    serverRSAPublicKeyB64: "...",
    useLegacyV1: true // Only temporary, over TLS!
)
```

Deprecated v1 functions (`buildStage1Packet`, `parseStage1Response`, `verifyServerSignature`, `buildStage2Packet`, `deriveSharedAESKey`) remain available for compatibility.

## What you're responsible for

- **The transport.** WireAuth doesn't assume `URLSessionWebSocketTask`,
  `Network.framework`, or anything else — wire it up to whatever you use.
- **Sequence numbers.** `seq` passed to `encrypt` must be unique and
  increasing per direction (a counter is enough). Reusing a seq with the
  same key is a nonce-reuse risk for GCM's associated data.
- **Session storage**, if your app supports resuming sessions later. This
  package computes the resume proof but doesn't decide where you persist
  `authKeyID`/the HMAC master key — use the Keychain (not `UserDefaults`)
  for anything derived from key material.
- **Obtaining `serverRSAPublicKeyB64` authentically.** It's not a secret,
  but if an attacker can substitute their own key at configuration time,
  the handshake's signature check protects you from nothing.

## Testing

Run tests with `swift test` (or `Cmd+U` in Xcode):

```bash
swift test
```