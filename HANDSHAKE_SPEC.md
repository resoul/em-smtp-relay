# Wire Handshake Protocol

All integers are little-endian unless stated otherwise.
All messages are sent as binary WebSocket frames.

## Security advisory (2026) — v1 does not authenticate the key exchange

The original protocol (below, "v1") signs only `client_nonce ‖ server_nonce` in stage 1. It never signs the ECDH public keys exchanged in stage 2 — the server's stage-2 response is a bare, unsigned public key. An active network attacker sitting between client and server can therefore substitute either party's ECDH public key in transit (a classic unauthenticated-Diffie-Hellman MITM) without invalidating the stage-1 signature, since that signature was never computed over the keys being substituted. **v1's RSA signature proves the server holds the private key; it does not prove the key exchange itself wasn't tampered with.**

v1 must never be run outside TLS. It is deprecated, retained only for staged migrations, and only reachable by a server that explicitly opts in (`wireauth.AllowLegacyV1()` in the Go implementation) — it must not be a new deployment's default. Use v2 below for anything new.

## Protocol v2 (current) — full-transcript signing

v2 fixes the gap above by signing once, after both ECDH public keys are known, over the complete transcript: both nonces AND both public keys. Any substitution of either public key by an attacker invalidates the signature, closing the MITM gap. This costs the same single RSA sign operation per handshake as v1 — it is simply performed later (after stage 2's client message arrives) rather than right after stage 1.

### Stage 1 — Client Hello

**Client → Server**

| offset | size | field        | note                     |
|--------|------|--------------|---------------------------|
| 0      | 4    | cmd          | `101` (u32 LE) — v2 hello |
| 4      | 16   | client_nonce | random bytes (CSPRNG)     |

Total: 20 bytes.

**Server → Client**

| offset | size | field        | note          |
|--------|------|--------------|----------------|
| 0      | 16   | server_nonce | random bytes  |

Total: 16 bytes. No signature yet — deferred to stage 2, once both ECDH public keys are known, so it can cover all four transcript fields at once.

### Stage 2 — Key Exchange + Transcript Signature

**Client → Server**

| offset | size | field         | note                                                                |
|--------|------|---------------|----------------------------------------------------------------------|
| 0      | 4    | cmd           | `102` (u32 LE) — v2 key exchange                                    |
| 4      | 65   | client_pubkey | ECDH P-256, uncompressed (X9.63/ANSI X9.62 format: `0x04 ‖ X ‖ Y`)   |

Total: 69 bytes.

**Server → Client**

| offset | size | field         | note                                                                            |
|--------|------|---------------|-----------------------------------------------------------------------------------|
| 0      | 65   | server_pubkey | same format                                                                        |
| 65     | 256  | signature     | RSA-PKCS1v15-SHA256(client_nonce ‖ server_nonce ‖ client_pubkey ‖ server_pubkey)  |

Total: 321 bytes.

**Client verification (mandatory, before trusting the exchange):**
`RSA_Verify(server_rsa_key, sig, client_nonce ‖ server_nonce ‖ client_pubkey ‖ server_pubkey)`.
On failure — close the connection immediately, do not derive or use the shared secret, do not retry with the same nonce.

### Key Derivation

```
shared_secret = ECDH(own_private, peer_public) // 32 bytes, P-256
salt          = client_nonce ‖ server_nonce
c2s_key       = HKDF-SHA256(shared_secret, salt, "wireauth/v2/client-to-server", 32)
s2c_key       = HKDF-SHA256(shared_secret, salt, "wireauth/v2/server-to-client", 32)
```

The two keys are distinct. A client encrypts with `c2s_key` and decrypts with `s2c_key`; the server does the inverse. This prevents a valid packet from one direction being reflected into the other. The nonce concatenation order and the two ASCII HKDF `info` strings are fixed protocol constants.

## Protocol v1 (deprecated — see security advisory above)

### Stage 1 — Client Hello

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

Client verification: `RSA_Verify(server_rsa_key, sig, client_nonce ‖ server_nonce)`.
On failure — close the connection, do not retry with the same nonce.

⚠️ This signature does **not** cover the ECDH public keys exchanged in stage 2 below — see the security advisory at the top of this document.

### Stage 2 — Key Exchange

**Client → Server**

| offset | size | field         | note                                                                |
|--------|------|---------------|----------------------------------------------------------------------|
| 0      | 4    | cmd           | always `2` (u32 LE)                                                 |
| 4      | 65   | client_pubkey | ECDH P-256, uncompressed (X9.63/ANSI X9.62 format: `0x04 ‖ X ‖ Y`)   |

Total: 69 bytes.

**Server → Client**

| offset | size | field         | note                                        |
|--------|------|---------------|----------------------------------------------|
| 0      | 65   | server_pubkey | same format, **unsigned** — the v1 gap       |

Total: 65 bytes.

## Stage 3+ — Secure Channel (AES-256-GCM)

Every message in either direction:

| offset | size | field      | note                                                                        |
|--------|------|------------|-------------------------------------------------------------------------------|
| 0      | 8    | seq        | u64 **big-endian** (not LE! differs from stage 1/2)                          |
| 8      | 12   | nonce      | random, per-message                                                           |
| 20     | N    | ciphertext |                                                                                |
| 20+N   | 16   | tag        | GCM auth tag                                                                  |

AAD (additional authenticated data) = `seq` (8 bytes, big-endian), not the nonce itself.

Receivers must maintain an independent expected sequence counter for each direction and reject a packet whose `seq` is not exactly the next expected value. AES-GCM authenticates the field but does not itself prevent replay.

## Resume Session (HMAC chain)

```
proof_A = HMAC-SHA256(key=master_key, data=session_salt)
proof_B = HMAC-SHA256(key=proof_A,   data=auth_key_id_bytes ‖ server_nonce)
```

`auth_key_id_bytes`: 8 bytes, **little-endian** (stated explicitly since it differs from the seq field in the AEAD framing above).

## Versioning

`cmd` doubles as an implicit version signal: `1`/`2` are v1 stages, `101`/`102` are v2 stages. A server reads the first 4 bytes of the first message and dispatches accordingly — no separate version field was needed. A server should default to v2-only; v1 must be an explicit, logged opt-in for migration windows, never a silent fallback.