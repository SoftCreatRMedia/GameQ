# Nuclear Option capture provenance

Captured on 2026-11-27 from the official Linux Nuclear Option Dedicated Server
(Steam tool 3930080), Steam build 26327503, game version 0.34.6,
build hash `3f729f837227`. The anonymous SteamCMD download was validated.

The controlled server ran locally with a synthetic server name. No client
joined. No firewall rules or router forwarding were added.

- `default`: vanilla, no join password, eight slots, built-in mission
  `Escalation`, default gameplay UDP 7777 and query UDP 7778.
- `custom`: `ModdedServer=true`, a disposable join password, four slots,
  built-in mission `Terminal Control`, gameplay UDP 14077 and independently
  configured query UDP 15078. The flag labels the server; no third-party mod
  was installed.
- `challenged`: the same custom configuration, queried through the
  controlled host's LAN address rather than loopback. INFO, PLAYER and RULES
  each returned a challenge followed by the requested response. The provider
  target is normalized to loopback; no external server was involved.

Each raw provider contains the actual INFO, empty PLAYER, and RULES responses,
separated by the shared Base provider delimiter. The expected JSON preserves
native Source fields. The test separately verifies normalized fields against
the controlled configuration. Server Steam IDs and dynamic tags are public
protocol metadata; there are no client identities, credentials or save files.

The first two cases answered without a challenge. The LAN case verifies actual
challenge replies. Malformed frames,
packet splitting and compression are inherited unchanged from Source and covered
by the shared Source regressions; these fixtures do not claim to capture those
conditions from Nuclear Option. A hidden configuration (`Hidden=true`) bound
7778 but did not respond to any of the three requests.

`map` / `mi` is the mission; native rule `ma` is the terrain. `s=no-game`
describes an idle server waiting for players, not a failed query. Native tags
and rules are preserved without guessing their undocumented meanings. The
advertised password bit is not proof of client-side password enforcement.

Populated/full rosters, Workshop/custom missions, a joining client and independent
public servers have not been verified. No join URI is advertised. Support is beta.

Official server/configuration documentation:
https://github.com/Shockfront-Studios/Nuclear-Option-Server-Tools/blob/main/DedicatedServerGuide.md
