# Directory capture provenance

`captured-directory.txt` was captured on 2026-10-23 from the fixed public endpoint
`https://stats.chupathingy.org/v1/games.txt?fields=22` (HTTP 200, 6,020 original
bytes, 40 records). The field specification is https://halo.milenko.org/api.

Server invites were consistently replaced with SHA-256 fixture identifiers;
server names became `Captured server N`, and roster names became `Player N`.
All other fields, including engine zero before a match, optional field groups,
hosting classifications, OpenCE/Delta distinctions and map-pack metadata, retain
the captured values. Separate synthetic tests cover Unicode and malformed input.

Trailing tabs are intentional empty TSV fields. Do not trim them: doing so
changes the captured record widths and loses optional-field coverage.

The service provides invite-keyed public listings, **not** gameplay IP addresses,
query ports or measured game latency. No tunnel client or upstream probe binary
is needed or bundled. Directory availability is not proof that a particular
invite is joinable, and unlisted/private sessions cannot be queried this way.
