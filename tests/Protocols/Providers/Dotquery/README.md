# DQP capture provenance

These are actual UDP responses from a disposable **g2gfast** dedicated server,
not hand-authored query snapshots. The game's own `examples/dedicated.gd` boot
loaded its `g2g_module.gd`, the `bhop_g2g_intro` map and `DotQueryHost`.

Sources:

- https://github.com/zees-games/game-g2gfast
- https://github.com/modcommunity/dot-server-query
- https://github.com/modcommunity/dot-server-query/blob/main/addons/dot_server_query/PROTOCOL.md

Captured on 2026-10-23 using official Godot 4.7.2 on Linux, gameplay UDP 28766
and query UDP 27099. The upstream query self-test passed 185 checks with zero
failures. The v0.1.6 release archive SHA-256 was independently verified as
`a992adbd9188ab9abd6354ce90a10ac5cb7817becee5892d1c83fcc0254ee123`.

- `empty-*`: no clients. Plain response is fragmented; gzip is unfragmented.
- `populated-*`: a separate process using the upstream `DotClientLink` completed
  the actual ENet handshake and entered PLAYING as **GameQ Test Player**. The
  query reported one spawned, non-bot session with its name and connection time.
- `empty-unchanged`: a subsequent query supplied the previous revision.
- `quota-plain`: deliberate negative test. The operator registered a public
  `FLAG_NOTIFY` console variable containing 30,000 bytes of test text. The real
  responder refused the oversized plain response with an ERROR packet.
- `quota-gzip`: the same controlled configuration fits when compressed, and
  exercises fragmented gzip with a decompressed body larger than one datagram.

JSON stores requests, challenge and response datagrams as base64 alongside the
decoded body for inspection. All names and values originate in this isolated
test; no account tokens, passwords, external addresses or user saves are included.
Challenge cookies are ephemeral, address/port-bound protocol data, not persistent
administration credentials. Replay tests replace only the transaction and cookie
bytes to match the new query; response payload and fragmentation remain unchanged.

This establishes interoperability with one actual integrating game and one
protocol release. It does **not** satisfy the two-release stable-support gate.
