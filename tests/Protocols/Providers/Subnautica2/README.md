# Subnautica 2 / BeaconServer captures

Captured locally from the official Steam game, build 0.1.2.3 / CL-131439,
with the independently maintained BeaconServer 0.3.135 runtime. These are real
UDP A2S INFO, PLAYER and RULES replies, not a simulated responder.

- `empty` / `populated`: no join password configured; zero / one real client.
- `passworded` / `passworded-populated`: join password configured; zero / one
  real client. The fixtures test the advertised A2S password bit only, not
  whether the third-party runtime enforces that password.
- `stale-heartbeat`: the game host was stopped while the query supervisor kept
  running. All three query stages were silent after its 30-second heartbeat
  timeout. Silence is not an empty online server.

The visible client joined the real world using Beacon's client runtime and an
isolated Steam/IP-driver configuration. Counts and names were checked against
the game's local roster. The public player display name has been replaced with
`GameQ_Test_Player`; challenge values and packet data are otherwise retained.
No account IDs, credentials, roster files or private configuration are included.

Beacon advertises a fixed `Awake` map label even when the loaded level is
`/Game/Maps/Main/L_Main`. Scores are always zero. Preserve those wire values;
neither proves meaningful map selection or scoring support.

The inherited Source tests cover shared challenge sequencing, split responses,
compression and size validation. The Subnautica 2 tests additionally exercise
the captured EDF GameID and truncated INFO, PLAYER and RULES replies.

Upstream responder and runtime documentation:
<https://github.com/HumanGenome/BeaconServer>
