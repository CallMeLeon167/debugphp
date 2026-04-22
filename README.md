<div align="center">

<img src="https://github.com/CallMeLeon167/debugphp-art/blob/main/DebugPHP_logo.png?raw=true" alt="DebugPHP" width="400">

**Real-time PHP debugging in the browser.**

DebugPHP streams your debug output to a browser-based dashboard via Server-Sent Events — no page reloads, no desktop apps, no configuration headaches.

[![License: MIT](https://img.shields.io/badge/License-MIT-00e89d.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-^8.1-777BB4.svg)](https://php.net)
[![Packagist Downloads](https://img.shields.io/packagist/dt/callmeleon167/debugphp.svg)](https://packagist.org/packages/callmeleon167/debugphp)

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

## Self-Hosted

Want to keep your debug data on your own server? Use the [DebugPHP Server](https://github.com/CallMeLeon167/debugphp-server):

### Option A — PHP built-in server
```bash
git clone https://github.com/CallMeLeon167/debugphp-server.git
cd debugphp-server
composer install
php -S localhost:8787
```

Then point your configuration to your local server:

```php
Debug::init('your-session-token', [
    'host' => 'http://localhost:8787',
]);
```

### Option B — Docker

```bash
git clone https://github.com/CallMeLeon167/debugphp-server.git
cd debugphp-server
docker compose up
```

Enable auto-detection so the client finds the server automatically:

```php
Debug::init('your-session-token', [
    'dockerized' => true,
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
