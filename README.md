<div align="center">

<img src="https://github.com/CallMeLeon167/debugphp-art/blob/main/DebugPHP_logo.png?raw=true" alt="DebugPHP" width="400">

**Real-time PHP debugging in the browser.**

DebugPHP streams your debug output to a browser-based dashboard via Server-Sent Events — no page reloads, no desktop apps, no configuration headaches.

[![License: MIT](https://img.shields.io/badge/License-MIT-00e89d.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-^8.1-777BB4.svg)](https://php.net)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%2010-brightgreen.svg)](https://phpstan.org)

</div>

<p align="center">
  <img src="https://raw.githubusercontent.com/CallMeLeon167/debugphp-art/refs/heads/main/showcase.gif" alt="DebugPHP Demo" width="720">
</p>

---

## Why DebugPHP?

| | dd() / var_dump() | Xdebug | Spatie Ray | **DebugPHP** |
|---|---|---|---|---|
| Real-time output | ❌ | ❌ | ✅ | ✅ |
| No page disruption | ❌ | ✅ | ✅ | ✅ |
| Zero config | ❌ | ❌ | ❌ | ✅ |
| Browser-based | ❌ | ❌ | ❌ | ✅ |
| Free & open source | ✅ | ✅ | ❌ ($49/yr) | ✅ |
| No dependencies | ✅ | ❌ | ❌ | ✅ |

---

## Installation

```bash
composer require callmeleon167/debugphp --dev
```

> **Requirements:** PHP 8.1+ and the `curl` extension.

---

## Quick Start

```php
use DebugPHP\Debug;

// 1. Initialize with your session token from the dashboard
Debug::init('your-session-token');

// 2. Send anything
Debug::send('Hello DebugPHP!');
Debug::send($user);
Debug::send($request->all());
```

Open the [Dashboard](https://dashboard.debugphp.dev/) in your browser — your debug data appears in real-time.

---

## Usage

### Send Data

```php
// Strings, arrays, objects — anything goes
Debug::send('User created successfully');
Debug::send(['id' => 1, 'name' => 'Leon']);
Debug::send($user);

// Exceptions with full stack trace
try {
    $service->process();
} catch (\Throwable $e) {
    Debug::send($e);
}
```

### Labels & Colors

```php
Debug::send($query, 'SQL')->color('blue');
Debug::send($error, 'Error')->color('red');
Debug::send($response, 'API')->color('green');
Debug::send($data, 'Cache')->color('orange');
```

Available colors: `red`, `blue`, `green`, `orange`, `purple`, `gray`

### Timer

```php
Debug::startTimer('db-query');

$results = $db->query('SELECT * FROM users');

Debug::stopTimer('db-query');
// → Dashboard shows: "db-query: 23.4ms"
```

### Tables

```php
Debug::table([
    ['name' => 'Leon', 'role' => 'Developer'],
    ['name' => 'Sarah', 'role' => 'Designer'],
]);
```

### Metrics

```php
// Displayed as live chips in the dashboard toolbar
Debug::metric('Memory', memory_get_usage(true) / 1024 / 1024 . ' MB');
Debug::metric('Template', 'home.php');
Debug::metric('Maintenance'); // Label only, no value
```

### Clear & Pause

```php
// Clear the dashboard
Debug::clear();

// Temporarily pause all output
Debug::pause();

// Resume output
Debug::resume();
```

---

## Configuration

```php
Debug::init('your-session-token', [
    'host'    => 'https://dashboard.debugphp.dev/',  // Server URL
    'timeout' => 3,                        // cURL timeout in seconds
    'enabled' => true,                     // Set false to disable globally
]);
```

| Option | Type | Default | Description |
|---|---|---|---|
| `host` | string | `https://dashboard.debugphp.dev/` | The DebugPHP server URL |
| `timeout` | int | `3` | cURL request timeout in seconds |
| `enabled` | bool | `true` | Enable or disable debugging globally |

### Environment-Based Toggle

```php
Debug::init('your-session-token', [
    'enabled' => getenv('APP_DEBUG') === 'true',
]);
```

---

## Self-Hosted

Want to keep your debug data on your own server? Use the [DebugPHP Server](https://github.com/CallMeLeon167/debugphp-server):

```bash
git clone https://github.com/CallMeLeon167/debugphp-server.git
cd debugphp-server
composer install
php -S localhost:8080
```

Then point your configuration to your local server:

```php
Debug::init('your-session-token', [
    'host' => 'http://localhost:8080',
]);
```

---

## How It Works

1. Your PHP app sends debug data via HTTP to the DebugPHP server.
2. The server stores the entry and pushes it to the dashboard via SSE.
3. The browser dashboard renders the entry in real-time.

```
┌──────────────┐     POST /api/debug     ┌──────────────┐     SSE Stream     ┌──────────────┐
│   Your App   │ ──────────────────────→ │   DebugPHP   │ ────────────────→  │  Dashboard   │
│  Debug::send │                         │    Server    │                    │   (Browser)  │
└──────────────┘                         └──────────────┘                    └──────────────┘
```

---

## Static Analysis

DebugPHP is fully analyzed with [PHPStan](https://phpstan.org) at **level 10** (the strictest level).

```bash
./vendor/bin/phpstan analyse
```

---

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.

---

## License

MIT — see [LICENSE](LICENSE) for details.

---

## Links

- **Website:** [debugphp.dev](https://debugphp.dev)
- **Documentation:** [debugphp.dev/docs](https://debugphp.dev/docs)
- **Server:** [github.com/CallMeLeon167/debugphp-server](https://github.com/CallMeLeon167/debugphp-server)
- **Dashboard:** [dashboard.debugphp.dev](https://dashboard.debugphp.dev/)
