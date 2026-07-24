# Wire Handshake Protocol v1

All integers are little-endian unless stated otherwise.
All messages are sent as binary WebSocket frames.

## Stage 1 — Client Hello

**Client → Server**

| offset | size | field        | note                     |
|--------|------|--------------|---------------------------|
| 0      | 4    | cmd          | always `1` (u32 LE)       |
| 4      | 16   | client_nonce | random bytes (CSPRNG)     |

Total: 20 bytes.

**Server → Client**

| offset | size | field        | note                                               |
|--------|------|--------------|-----------------------------------------------------|
| 0      | 16   | server_nonce | random bytes                                        |
| 16     | 256  | signature    | RSA-PKCS1v15-SHA256(client_nonce ‖ server_nonce)    |

Total: 272 bytes.

Client verification: `RSA_Verify(server_pubkey, sig, client_nonce ‖ server_nonce)`.
On failure — close the connection, do not retry with the same nonce.

## Stage 2 — Key Exchange

**Client → Server**

| offset | size | field         | note                                                                |
|--------|------|---------------|----------------------------------------------------------------------|
| 0      | 4    | cmd           | always `2` (u32 LE)                                                 |
| 4      | 65   | client_pubkey | ECDH P-256, uncompressed (X9.63/ANSI X9.62 format: `0x04 ‖ X ‖ Y`)   |

**Server → Client**

| offset | size | field         |
|--------|------|---------------|
| 0      | 65   | server_pubkey | same format |

## Key Derivation

```
shared_secret = ECDH(own_private, peer_public)   // 32 bytes, P-256
kdf_input     = shared_secret ‖ client_nonce ‖ server_nonce
session_key   = SHA256(kdf_input)                 // 32 bytes → AES-256-GCM key
```

The concatenation order (`shared_secret`, then `client_nonce`, then
`server_nonce`) is FIXED. Getting the order wrong on either side produces a
different key = a silent connection break.

## Stage 3+ — Secure Channel (AES-256-GCM)

Every message in either direction:

| offset | size | field      | note                                                                        |
|--------|------|------------|-------------------------------------------------------------------------------|
| 0      | 8    | seq        | u64 **big-endian** (not LE! differs from stage 1/2)                          |
| 8      | 12   | nonce      | random, per-message                                                           |
| 20     | N    | ciphertext |                                                                                |
| 20+N   | 16   | tag        | GCM auth tag (may be appended to ciphertext depending on the library — see note below) |

AAD (additional authenticated data) = `seq` (8 bytes, big-endian), not the
nonce itself.

⚠️ **Known discrepancy in the current codebase**, called out explicitly so
it's a documented fact rather than an assumption:
- Go/`wirecrypto.EncryptAESGCM`: seq is big-endian; the tag is appended
  automatically inside the ciphertext by GCM's `Seal`.
- Web `encryptSecure`: seq is big-endian (matches).
- Swift `computeResumeProof` (resume proof, not to be confused with
  framing!): `authKeyID` is **little-endian**.

In other words, the AEAD framing itself (stage 3+) agrees across all three
implementations (big-endian seq). The **resume proof** (HMAC chain below)
is the odd one out: both Swift and web use little-endian for
`authKeyID` (`setBigUint64(0, authKeyID, true)` — the third parameter
`true` means little-endian, despite the function's name). This is the one
place that must be checked byte-for-byte across all three implementations
before splitting them into separate packages — the spec should state this
as a verified fact, not "presumably the same order everywhere."

## Resume Session (HMAC chain)

```
proof_A = HMAC-SHA256(key=master_key, data=session_salt)
proof_B = HMAC-SHA256(key=proof_A,   data=auth_key_id_bytes ‖ server_nonce)
```

`auth_key_id_bytes`: 8 bytes, **little-endian** (stated explicitly since it
differs from the seq field in the AEAD framing above).

## Versioning

The current protocol carries no version number in the packet itself
(cmd=1/2 are stages, not versions). If the format ever needs to change,
proposal: reserve cmd ≥ 100 for "handshake v2", so the server can tell old
and new clients apart from the very first byte it reads.