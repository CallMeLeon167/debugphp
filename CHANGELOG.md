# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-03-24

### Added
- `Debug::init()` — initialize with session token and optional configuration
- `Debug::send()` — send any PHP value (strings, arrays, objects, exceptions) to the dashboard
- `Debug::startTimer()` / `Debug::stopTimer()` — measure and display execution time
- `Debug::table()` — send tabular data with auto-detected or custom headers
- `Debug::metric()` — display live key/value chips in the dashboard toolbar
- `Debug::clear()` — clear all entries in the current session
- `Debug::pause()` / `Debug::resume()` — temporarily suppress debug output
- Fluent API via `->color()` and `->type()` for labels and categorization
- Typed PHP descriptor system (`buildTyped()`) preserving native type information for var_dump-style rendering
- Automatic source file and line resolution via `resolveCaller()`
- Request lifecycle IDs for auto-clear on new requests
- Deferred dispatch via `__destruct` for single HTTP request per entry chain
- Throwable support with message, code, file, line, and condensed stack trace
- Silent failure mode — never throws exceptions or disrupts the host application
- Zero runtime dependencies (only `ext-curl`)
- PHPStan Level 10 compliance on all source files

[1.0.0]: https://github.com/CallMeLeon167/debugphp/releases/tag/v1.0.0