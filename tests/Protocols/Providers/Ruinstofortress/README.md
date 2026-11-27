# Ruins To Fortress capture provenance

Captured on 2026-11-27 from the official Linux dedicated server (Steam tool
3145770), Steam build 26967148, game version **0.9.1**, newer than the briefing's
0.9.0. The anonymous SteamCMD download was validated. Configuration and saves
were isolated; no client joined and no firewall/router rules were added.

- `default`: default gameplay UDP 7777, query UDP 27015, ten slots, PvP, no
  password. The initial automatically generated name is synthetic.
- `pve`: same verified ports, synthetic name, six slots, PvE, no password.
- `passworded`: same PvE configuration with a disposable join password. The
  INFO password bit changes to one; the native `Password` rule contains the
  server's opaque marker, not the configured password.

INFO, empty PLAYER and eleven RULES are stored in these captures. A subsequent
live query also returned a twelfth Steam-ID rule; the rule set can change as the
server finishes registration. The actual response bytes are
retained. Public dedicated-server/world identifiers are protocol metadata. The
fixtures contain no client identities, join/admin passwords or world saves.

The query fields were cross-checked against `RTFServer_Data/server.cfg` and the
local server's read-only `server_info` command. That loopback administrative HTTP
interface is **not** used by this protocol and no administrative credential is
accepted. GameQ reads only A2S.

`map=PvP/PvE` is the game mode, not the actual world or seed. It normalizes to
`gq_gametype`; no `gq_mapname` or direct-join URI is claimed. Native rule names
and values, including plugin metadata, remain unchanged.

Changing gameplay to 8000 or 17779 caused the tested build to log query ports
27238 or 37017, respectively, but neither socket bound or answered in these
controlled runs. Returning to 7777 restored queries on 27015. Consequently no
custom offset is assumed: GameQ defaults to query UDP 27015 and offers an
explicit `query_port` override. This is not a guarantee that a server build
actually exposes its reported custom port.

Populated/full rosters, hidden/version-mismatched servers and custom query ports
remain unverified. Support remains beta. Shared Source tests cover challenges,
malformed frames and split/compressed responses.

Official operator documentation: https://ruinstofortress.com/wiki/#linux
