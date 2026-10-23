# Lodestone / Delverium capture provenance

Captured from a real, owned Steam **Delverium Demo** (app 3426800, Unity
6000.0.51f1, game label 2.35) running **LodestoneServer 0.2.6**, on 2026-10-23.
The published server archive SHA-256 was verified as
`5916be6ec2a6e5791ebb2f73b3941b03cb09d0111e9ec6794b105d165a71321b`.

Source: https://github.com/HumanGenome/LodestoneServer

The server ran in an isolated game copy with a newly generated small world and
dedicated synthetic host identity. The upstream boot report confirmed HOSTING,
56/56 game patches, Steam dedicated-server logon, gameplay UDP 27016 and query
UDP 27017. Windows GameQ queried it successfully over loopback. A genuine second
game process running the published Lodestone client joined and spawned; the
local private roster independently confirmed the count and test character name.

- `empty.json`: empty, open server; INFO, PLAYER, RULES.
- `populated.json`: one actual spawned client, **GameQ Test Play** (the game's
  character-name length limit); INFO, PLAYER, RULES.
- `passworded.json`: Host.JoinPassword configured; INFO, PLAYER, RULES.
- `passworded-populated.json`: one actual client joined with the matching
  password; INFO, PLAYER, RULES. The same client was refused without the key.

Each file contains hexadecimal UDP replies grouped by stage. No user account
IDs, passwords, administrative data, save files or roster files are included.
The public dedicated-server Steam ID in INFO is protocol metadata, not the
test client's account ID. Captures were made on the same Windows host; no
firewall exceptions or public ports were added.

Observed limitations are deliberately retained:

- The reported map is the constant `Lodestone`, **not the configured world name**.
- `version` is Lodestone's version; the game label appears separately in tags.
- Scores are zero in the tested roster; this is not evidence of meaningful scores.
- A2S reports `password=0` even when Lodestone confirms its join password is set.
  A genuine client without that password was refused by the server.
  Both `password` and normalized `gq_password` retain that reported bit. GameQ
  reads the advertised state; it does not verify password enforcement.
- These captures cover one plugin release and the demo, not the paid game or
  a second compatible plugin release. Support therefore remains beta.
