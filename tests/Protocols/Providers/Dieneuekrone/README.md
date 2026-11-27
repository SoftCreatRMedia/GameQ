# Die Neue Krone directory evidence

Investigated on 2026-11-27 using the official Linux dedicated-server Steam tool
5704120, build 26916212, runtime version 0.9.0.0. The anonymous SteamCMD download
was validated. No game/server executable, source file, registration credential
or save is redistributed here.

Primary sources shipped in the official server package:

- `Anleitung_Dedicated_Server.html`
- `server/README_DEDICATED_SERVER.md`
- `server/kaiser-server.env.example`
- `server/public_directory/README_SERVERVERZEICHNIS_DE.md`
- `server/public_directory/api/{config,lib,heartbeat,servers}.php`
- `server/webadmin/README_WEBADMIN.md` (administration boundary only)

The public read interface is GET
`https://haas-lab.de/kaiser-serverlist/api/servers.php`, with optional `version`
text, no authentication, UTF-8 `application/json`, and schema 1. It returns an
`ok` boolean, integer Unix-second `generated_at`, `requested_version` text, and
a `servers` list. No pagination is implemented; the supplied API limits stored
masters to 500 and rooms per master to 32. It returns 405 for non-GET requests
and 500 for storage failures. It exports neither `host` nor `secret_hash`.

Master fields: public hexadecimal `server_id`, `server_name`, integer
`master_port`, textual `endpoint`, four-component `version`, `max_games`,
`room_count`, `running_rooms`, total human `players`, `rooms`, Unix-second
`last_seen`, and `age_seconds`. The shipped API adds boolean `stale`; the live
official deployment observed that day omitted it. Rooms expose `id`, `name`,
`status` (`starting`, `lobby`, `running`), `humans`, `seats`, `max_players` and
`year`. No master-wide player capacity, map, password state or player names are
specified. `max_games` is not a player capacity.

The supplied API marks entries stale after 90 seconds, retains them for a
60-second grace period, then removes them. GameQ rejects stale heartbeats rather
than treating the grace period as a live server. Live directory verification
also succeeded against an independent public master running version 0.8.9.0.

Controlled evidence:

- `controlled.json`: real isolated 0.9.0.0 master on loopback UDP 17777, with two
  available game instances. It sent heartbeats to the **local copy** of the
  supplied API, not the public directory. No client joined and no room existed.
  The public master ID is replaced by repeated `1` digits.
- `api-multi-room.json`: actual GET output from that same API after a deliberately
  **synthetic API-contract heartbeat**, not real clients. It tests two rooms and
  two masters sharing one address on different ports. The synthetic secret is
  not included. It is not evidence of populated game-client interoperability.
- Empty-before-registration and empty-after-expiry responses were also observed.
  Restart/room creation with a real client has not been verified.

The report channel is **internal-only UDP 7778**, not HTTP and not a public
status query. Gameplay masters normally use UDP 7777 and rooms 7780 upward.
The web admin has its own authenticated HTTPS service and is not queried.

GameQ uses the fixed official HTTPS endpoint through the existing injected
transport (TLS verification, no redirects, five-second default timeout, bounded
16 MiB body) and rejects excessive records, invalid field types/counts/ports,
duplicate master/room IDs, stale data and ambiguous endpoint matches. It never
follows URLs from a record or resolves directory-supplied DNS names. Optional
`directory_address` matches an operator-supplied advertised DNS spelling;
`server_id` pins a public master ID. Neither is an arbitrary service URL.

Unknown/private fields are excluded from results. The API's integer `players`
becomes native `num_players` so it cannot collide with GameQ's player roster.
Only hostname and current count are normalized; rooms remain native metadata.
No registration key, server secret, admin password, direct join URI or invented
map/password/player-capacity data is accepted or exposed. Support remains beta.
