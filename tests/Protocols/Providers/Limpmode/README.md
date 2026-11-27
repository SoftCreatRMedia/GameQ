# LIMP MODE capture provenance

Captured on 2026-11-27 from the official Linux dedicated server (Steam tool
5602990), Steam build 26978446, game version 0.68. The anonymous SteamCMD
download was validated. Saves and configuration were isolated; no client joined.

- `private`: roleplay (`rp`), four slots, private with a disposable join password.
  Queries over Linux loopback answered INFO, empty PLAYER, and four RULES.
- `challenged`: the same server, queried through its WSL Ethernet address.
  Each stage required a four-byte Steam challenge before the data response.
- `drift`: public drift mode, four slots, no join password. A separate Windows
  UDP client queried the WSL server, with challenges at all three stages.

The raw providers retain actual response bytes and public dedicated-server IDs;
target addresses are replaced with loopback in expected results. No client
identities, join passwords, tickets, administrator settings or saves are included.

The official `HOSTING-GUIDE.md` describes relay-only gameplay and calls the
Steam UDP query socket a LAN-list socket. The tested socket bound all IPv4
interfaces, normally UDP 27016, and answered across Windows/WSL without joining
or using a Steam client session. This proves LAN/host monitoring, not public
Internet reachability. Operators must explicitly make a query socket reachable
for remote monitoring. GameQ does not expose or query a changing FakeIP, inspect
Steam client tickets, or select a server by its name.

The A2S `map` value is `rp` or `drift`, not a world/map name. It normalizes to
`gq_gametype`, not `gq_mapname`. Rules include mode and mod counters. Their
undocumented values are preserved. The private roleplay server advertised
`password=0`; the protocol preserves that bit and does not verify enforcement.
The INFO `port=27015` is not a validated direct-join port. No join URI is emitted.

Populated/full rosters, car-sharing settings, script-mod data and external
Internet monitoring have not been verified. Support remains beta. Shared Source
regressions cover challenge framing, malformed and split/compressed responses.
