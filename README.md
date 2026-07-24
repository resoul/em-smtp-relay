# WireAuth

A Swift package implementing the client side of the `wireauth` handshake
protocol for iOS and macOS: RSA-signed challenge/response, ECDH (P-256) key
exchange, and an AES-256-GCM secured channel afterward, plus an HMAC-based
session-resume proof. Built on CryptoKit and Security — no third-party
dependencies.

This is the same protocol implemented by the companion
[Go server package](../wireauth) and [JS/TS client package](../wireauth-js)
— all three speak an identical wire format, documented in
[`HANDSHAKE_SPEC.md`](./HANDSHAKE_SPEC.md).

Like its Go/JS counterparts, this is **transport security**, not end-to-end
encryption between users. It secures the link between this client and your
server (similar in spirit to TLS) and is meant to sit alongside your own
authentication — not replace it. If you need E2E encryption between users,
look at libsignal instead.

## Install

Add via Swift Package Manager:

```swift
dependencies: [
    .package(url: "https://github.com/resoul/wireauth-swift.git", from: "0.1.0")
]
```

Or in Xcode: **File → Add Package Dependencies…** and paste the repo URL.

Supports iOS 13+ and macOS 10.15+.

## Setup

Call `WireAuth.configure` once at app startup, before performing any
handshake — typically in your `AppDelegate`/`App` init:

```swift
import WireAuth

WireAuth.configure(
    serverRSAPublicKeyB64: "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...", // your server's public key
    resumeProofSalt: mySaltData // optional — only needed for computeResumeProof
)
```

`serverRSAPublicKeyB64` is the server's RSA **public** key (SPKI/DER,
base64). It is not a secret, but make sure your app obtains it
authentically — bundle it in your app's config/Info.plist, pin it, fetch it
once over a trusted channel, etc. — rather than trusting an unauthenticated
source at runtime. (An earlier version of this code shipped the key
XOR-obfuscated inside the binary; that gave no real protection — public
keys aren't secrets to begin with, and the obfuscation is trivially
reversible with a disassembler. Explicit configuration is simpler and
equally secure, since what actually matters is whether the app got the
*authentic* key, not whether it's hidden.)

## Usage

WireAuth gives you the building blocks for each protocol stage — it doesn't
assume WebSocket, `URLSessionWebSocketTask`, or any specific transport.
Here's a full example using `URLSessionWebSocketTask`:

```swift
import WireAuth
import CryptoKit

let task = URLSession.shared.webSocketTask(with: URL(string: "wss://your-server/ws")!)
task.resume()

func sendAndReceive(_ packet: Data) async throws -> Data {
    try await task.send(.data(packet))
    let message = try await task.receive()
    guard case .data(let data) = message else {
        throw NSError(domain: "wireauth-example", code: 1)
    }
    return data
}

// --- Stage 1: RSA challenge/response ---
let rsaKey = try WireAuth.importServerRSAKey()
let clientNonce = WireAuth.generateClientNonce()
let stage1Response = try await sendAndReceive(WireAuth.buildStage1Packet(clientNonce: clientNonce))
let (serverNonce, signature) = try WireAuth.parseStage1Response(stage1Response)

guard WireAuth.verifyServerSignature(
    rsaKey: rsaKey, clientNonce: clientNonce, serverNonce: serverNonce, signature: signature
) else {
    throw WireAuth.HandshakeError.invalidSignature
}

// --- Stage 2: ECDH key exchange ---
let clientPrivateKey = P256.KeyAgreement.PrivateKey()
let stage2Packet = WireAuth.buildStage2Packet(clientPublicKeyRaw: clientPrivateKey.publicKey.x963Representation)
let serverPublicKeyRaw = try await sendAndReceive(stage2Packet)

let derived = try WireAuth.deriveSharedAESKey(
    serverPublicKeyRaw: serverPublicKeyRaw,
    clientPrivateKey: clientPrivateKey,
    clientNonce: clientNonce,
    serverNonce: serverNonce
)

// --- Secure channel ---
let outgoing = try WireAuth.encryptSecure(key: derived.key, seq: 1, payload: "hello".data(using: .utf8)!)
try await task.send(.data(outgoing))

let incomingRaw = try await sendAndReceive(Data()) // or however your receive loop works
let (plaintext, seq) = try WireAuth.decryptSecure(key: derived.key, packet: incomingRaw)
print("message #\(seq): \(String(data: plaintext, encoding: .utf8) ?? "")")
```

## API reference

```swift
// Configuration — call once at startup.
WireAuth.configure(serverRSAPublicKeyB64: String, resumeProofSalt: Data? = nil)

// Stage 1 — RSA challenge/response
try WireAuth.importServerRSAKey() -> SecKey
WireAuth.generateClientNonce() -> Data                    // 16 random bytes
WireAuth.buildStage1Packet(clientNonce: Data) -> Data
try WireAuth.parseStage1Response(_ response: Data) -> (serverNonce: Data, signature: Data)
WireAuth.verifyServerSignature(rsaKey:clientNonce:serverNonce:signature:) -> Bool

// Stage 2 — ECDH key exchange
WireAuth.buildStage2Packet(clientPublicKeyRaw: Data) -> Data
try WireAuth.deriveSharedAESKey(serverPublicKeyRaw:clientPrivateKey:clientNonce:serverNonce:) -> DerivedAESKey
// DerivedAESKey.key: SymmetricKey, .raw: Data

// Secure channel (AES-256-GCM)
try WireAuth.encryptSecure(key: SymmetricKey, seq: UInt64, payload: Data) -> Data
try WireAuth.decryptSecure(key: SymmetricKey, packet: Data) -> (plaintext: Data, seq: UInt64)

// Resume proof (HMAC chain) — requires resumeProofSalt in configure(_:_:)
try WireAuth.computeResumeProof(authKeyID: UInt64, masterKey: SymmetricKey, serverNonce: Data) -> ResumeProof
// ResumeProof.authKeyIDBytes / .proofA / .proofB: Data
```

## What you're responsible for

- **The transport.** WireAuth doesn't assume `URLSessionWebSocketTask`,
  `Network.framework`, or anything else — wire it up to whatever you use.
- **Sequence numbers.** `seq` passed to `encryptSecure` must be unique and
  increasing per direction (a counter is enough). Reusing a seq with the
  same key is a nonce-reuse risk for GCM's associated data.
- **Session storage**, if your app supports resuming sessions later. This
  package computes the resume proof but doesn't decide where you persist
  `authKeyID`/the HMAC master key — use the Keychain (not `UserDefaults`)
  for anything derived from key material.
- **Obtaining `serverRSAPublicKeyB64` authentically.** It's not a secret,
  but if an attacker can substitute their own key at configuration time,
  the handshake's signature check protects you from nothing.

## Errors

- `WireAuth.HandshakeError` — `.notConfigured` (call `configure` first),
  `.invalidRSAKey`, `.packetTooShort`, `.invalidSignature`,
  `.invalidServerPublicKey`, `.resumeSaltNotConfigured`
- `WireAuth.SecureChannelError` — `.packetTooShort`, `.decryptionFailed`

## Testing

`Tests/WireAuthTests` covers packet framing, the encrypt/decrypt round trip,
ECDH key agreement symmetry, and resume-proof determinism — run with
`swift test` (or Cmd+U in Xcode) on macOS with an Xcode toolchain installed.
This package was developed and code-reviewed in a Linux environment without
a Swift toolchain available, so **run the test suite once yourself before
relying on this in production** — the logic was ported carefully and
line-by-line from the original, tested implementation, but it has not been
compiled or executed in this environment. If anything fails to compile,
it's most likely a small syntax slip worth a quick fix rather than a
logic issue.

## FAQ

**Is this the Signal protocol?**
No — see the note at the top. No forward secrecy across messages, no
per-user identity keys, no E2E. It's a transport-security handshake, closer
to a lightweight custom TLS than to Signal.

**Why isn't the RSA key obfuscated in the binary anymore?**
Because it never needed to be — it's a public key, and obfuscation only
makes reverse-engineering marginally slower without adding real security.
What matters is that your app gets the *authentic* public key; use App
Transport Security, key pinning, or a trusted config channel for that, not
obfuscation.

**Can I use this on watchOS/tvOS?**
The package only declares iOS 13+ and macOS 10.15+ platforms in
`Package.swift`. CryptoKit and Security are available on watchOS/tvOS too,
so adding those platforms to `Package.swift` should work, but it hasn't
been tested here — add the platform declarations and verify yourself.