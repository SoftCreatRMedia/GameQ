# Changelog

All notable changes to this project are documented in this file.

## [5.0.1] - 2026-08-03

### Changed

- Made `ext-bz2` optional; it is only required when a Source/A2S server returns a bzip2-compressed split response.

## [5.0.0] - 2026-08-02

### Added

- Support and CI coverage for PHP 8.1 through PHP 8.5, plus experimental PHP 8.6 coverage.
- BeamMP server-query support adapted from an upstream contribution and stable HytaleONE support based on the official OneQuery V2 protocol, including V1 fallback.
- Factorio matchmaking, Palworld REST API, Satisfactory lightweight-query, and UOX3 status protocols.
- Farming Simulator 2013, 2015, 2017, 2019, 2022, and 2025 dedicated-server web statistics, including players, mods, vehicles, and raw response data.
- Stable SCUM server discovery through the current master-server proxies.
- Beta support for the reverse-engineered CryoFall protocol.
- Source-query aliases for American Truck Simulator, Arma Reforger, Counter-Strike 2, Enshrouded, Euro Truck Simulator 2, Nova-Life, and Wreckfest.
- Response-driven follow-up query rounds for challenged or paginated protocols.
- Configurable server batching through the `max_servers_per_batch` option to bound concurrent socket and response memory usage.
- Per-socket response-size and packet-count limits for native queries.
- Maximum-level PHPStan analysis with strict, deprecation, and PHPUnit rules.
- Automated public API compatibility checks against the 5.0.0 baseline for subsequent 5.x releases.
- `Server::protocolInstance()` as the non-null protocol accessor for new code.

### Changed

- Modernized Composer metadata, PHPUnit, PHP_CodeSniffer, CI actions, and development dependencies.
- Adopted PSR-12 throughout source code, tests, and examples.
- Hardened binary, JSON, XML, HTTP, socket, and split-packet parsing against malformed responses.
- Restricted outbound cURL protocols, response sizes, status codes, and timeouts for EOS, BeamMP, Factorio, and Palworld queries.
- Completed PHPDoc parameter, return, property, and exception contracts and added native types to the modernized 5.0 extension API.
- Consolidated duplicated Quake, grouped-response, TeamSpeak, and Frostbite parsing paths.
- Reworked Source/Source 2 querying to negotiate server-driven A2S challenges sequentially without changing the initial A2S_INFO request or the legacy WON packet flow.
- Reassembled Source, compressed Source, Source 2006, and GoldSource split responses by their documented framing, with count, length, checksum, ordering, and bounded streaming-decompression validation.
- Updated Minecraft: Bedrock Edition querying to the current RakNet unconnected-ping/pong framing and validated its advertised payload length.
- Updated Squad's Epic Online Services session matching and retained its Source rules fallback.
- Reworked fixtures and tests for current PHPUnit versions while preserving their protocol coverage.
- Reduced player and team result assembly from repeated full-array scans to cursor-based linear aggregation.
- Let completed socket batches return after one idle interval while still giving non-responsive servers the configured overall timeout.
- Expanded FiveM/Cfx querying with HTTP fallback, server metadata, and player details, and updated RageMP querying for the current nested master-list format while retaining legacy responses.
- Made native TCP and TLS queries retain buffered records and wait for complete HTTP response bodies.
- Consolidated HTTP envelope validation for Cfx/FiveM, RageMP, and Farming Simulator responses, and shared result assembly between Farming Simulator's JSON and XML formats.
- Declared the `ext-libxml` runtime requirement used by XML-based protocols.
- Rebuilt the README and added a structured, fork-current wiki covering installation, configuration, results, established protocol families, protocol options, performance, security, troubleshooting, supported identifiers, development, testing, and migration from 4.x.

### Fixed

- Compressed Source responses now use a bzip2-compatible temporary file and accept both current split framing and legacy responses that omit the maximum packet-size field.
- Cfx/FiveM player and info subqueries now read nested results correctly and expose players, version, Discord, and locale data.
- EOS HTTP requests now negotiate and decode compressed responses through cURL.
- EOS authentication and device-token responses are no longer retained as protocol packet data.
- Native socket write failures now consistently raise `QueryException` without leaking PHP warnings.
- Oversized native responses are discarded instead of passing a truncated prefix to protocol parsers.
- Rust keywords and server-browser tags are parsed independently of their order.
- Epic Online Services session parsing now shares a validated list representation with ARK: Survival Ascended.
- Battlefield: Bad Company 2, Battlefield 3, ETQW, Frontlines: Fuel of War, GameSpy3, StarMade, and Ventrilo packet assembly now validates framing instead of accepting truncated or conflicting data.
- Call of Duty variants share consistent normalization, including player deaths and protocol-specific score fields.
- RakNet responses reject inconsistent advertised payload lengths.
- TeamSpeak invalid-header tests no longer pass a boolean as `explode()`'s limit.
- Protocols now fail predictably on truncated or invalid buffers instead of emitting PHP warnings.
- Negative and out-of-bounds buffer reads and skips are rejected, and configured or derived server ports must be within `1..65535`.
- The standalone autoloader rejects malformed relative class paths before resolving files.
- TShock responses without optional player or rule lists are parsed without undefined-property warnings.
- Quake 2-family and Modern Warfare 2 player lists handle empty trailing records and omitted optional addresses explicitly.
- Parser loops no longer build cumulative results with resource-greedy `array_merge()` calls.
- Resolved actionable PhpStorm and EA Extended findings, including missing exception contracts, strict fixture JSON decoding, race-safe fixture-directory creation, redundant test cleanup, and avoidable condition or string-access overhead.

### Deprecated

- `Server::protocol()`; use `Server::protocolInstance()` for a non-null return type.

### Compatibility

- Version 5.0 includes deliberate public and protected API changes; consumers extending GameQ should review the 4.x migration guide.
- PHP 8.1 or newer is now required.

[5.0.1]: https://github.com/SoftCreatRMedia/GameQ/compare/5.0.0...5.0.1
[5.0.0]: https://github.com/SoftCreatRMedia/GameQ/compare/4.0.0...5.0.0
