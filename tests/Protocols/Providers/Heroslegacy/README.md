# The Hero's Legacy capture provenance

Captured on 2026-11-27 from the official Windows dedicated server (Steam tool
5677890), version 0.9.6. The anonymous SteamCMD download was validated and run
in an isolated temporary folder with the documented Steam runtime DLLs.

- `default`: public visibility, synthetic server name, a disposable join
  password, gameplay UDP 7777, query UDP 27016, four slots, world `Server World`,
  Softcore and difficulty 0. A2S advertises `password=1` and tags `sc,d0`.
- `custom`: public visibility, no password, gameplay UDP 17771 and independent
  query UDP 18772, six slots, new world `GameQ Hardcore World`, Hardcore and
  difficulty 2. A2S advertises `password=0` and tags `hc,d2`.

The actual INFO and empty PLAYER responses are retained. A2S_RULES timed out in
both configurations, so it is disabled by default. LAN-only visibility was also
tested and did not answer any A2S stage, despite the server log confirming Steam
initialization. No generic protocol parser was changed.

The fields were cross-checked against the generated `server.cfg`, documented
ports in `README-SERVER.txt`, and the server log. Hardcore/difficulty tags are
preserved as native `keywords`, not invented normalized fields. Server IDs are
public protocol metadata. There are no client identities, credentials or saves
in these fixtures; only public query responses are included.

No client joined. Populated/full states, private visibility and two simultaneous
instances have not been verified. A connection URI is not documented or tested.
Support remains beta. Source-family challenge, splitting/compression and invalid
response tests remain shared.
