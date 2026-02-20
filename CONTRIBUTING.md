# Contributing to DebugPHP

Thanks for your interest in contributing! DebugPHP is a small, focused library — contributions are very welcome, but please read this first to make the process smooth for everyone.

---

## Before You Start

- **For bugs:** Open a [Bug Report](https://github.com/CallMeLeon167/debugphp/issues/new?template=bug_report.yml) first so we can discuss whether it's actually a bug.
- **For features:** Open a [Feature Request](https://github.com/CallMeLeon167/debugphp/issues/new?template=feature_request.yml) first before writing code. Nothing is worse than a finished PR that doesn't get merged because the feature doesn't fit the project.
- **For small fixes** (typos, docs, obvious bugs): Just open a PR directly.

---

## Setup

**Requirements:** PHP 8.1+, Composer, the `curl` extension.

```bash
git clone https://github.com/CallMeLeon167/debugphp.git
cd debugphp
composer install
```

Run PHPStan to verify everything is clean:

```bash
composer analyse
```

---

## Rules

### PHPStan Level 10 is non-negotiable

Every file in `src/` must pass PHPStan at level 10 with zero errors. No exceptions. Run it before every commit:

```bash
composer analyse
```

### PHPDoc on everything

All classes, methods, and non-trivial properties need PHPDoc blocks. Look at the existing source files in `src/` for the expected style — they serve as the reference.

**Good:**
```php
/**
 * Sends debug data to the dashboard.
 *
 * @param mixed  $data  The data to debug.
 * @param string $label Optional label for categorization.
 *
 * @return Entry|null The created entry, or null if not ready.
 */
public static function send(mixed $data, string $label = ''): ?Entry
```

**Not acceptable:**
```php
// sends data
public static function send(mixed $data, string $label = ''): ?Entry
```

### Coding Style

- `declare(strict_types=1)` at the top of every PHP file
- PSR-4 autoloading, namespace `DebugPHP\`
- No external runtime dependencies — the `require` section in `composer.json` stays as-is
- `final` classes wherever possible
- Fail silently — DebugPHP must never throw exceptions or break the host application

---

## Pull Request Checklist

Before opening a PR, make sure:

- [ ] `composer analyse` passes with zero errors
- [ ] All new public methods/classes have complete PHPDoc
- [ ] `declare(strict_types=1)` is present in every new PHP file
- [ ] No new entries in the `require` section of `composer.json`

---

## Project Structure

```
src/
├── Config.php   — Immutable configuration value object
├── Client.php   — Lightweight cURL HTTP client
├── Entry.php    — Single debug entry with fluent API
└── Debug.php    — Static facade (the public API)
└── ...
```

Keep it simple. If you're adding a new file, think twice about whether it really needs to be its own class.
