<h1 align="center">pepito-client</h1>

<p align="center">
  <a href="https://packagist.org/packages/wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="Packagist"/></picture></a> <a href="https://packagist.org/packages/wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/packagist/dt/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/packagist/dt/wielorzeczownik/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/packagist/dt/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="Packagist downloads"/></picture></a> <a href="https://github.com/wielorzeczownik/pepito-client/blob/main/LICENSE"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/badge/License-MIT-2ea043?style=flat-square"/><img src="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b" alt="License: MIT"/></picture></a>
  <br/>
  <img src="https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP"/>
</p>

[Pepito API](https://github.com/Clement87/Pepito-API) client: a Rust core loaded through FFI. Requires PHP 8.2+ with the `ffi` and `curl` extensions.

```sh
composer require wielorzeczownik/pepito-client
```

On install, the Composer plugin (`Pepito\Installer\LibraryInstallerPlugin`) downloads the prebuilt binary for your platform from the GitHub Release and verifies it by SHA-256, so you never need a Rust toolchain. Composer 2.2+ will ask on the first `require` whether to trust this plugin (`allow-plugins`). Without that consent the plugin will not run and you need to build manually, see below.

This keeps `php/` self-contained, and it also works unpacked in `vendor/`, where no `target/` exists. C declarations are read from `php/lib/pepito.h`, which cbindgen generates from `crates/ffi` on every `make dylib`, which is what keeps the prototype from silently drifting away from the ABI. A hand-copied signature would drift with no error at all, and a mismatched ABI is UB.

```php
require 'vendor/autoload.php';

use Pepito\Client;
use Pepito\Event\Sighting;

$cacheFile = sys_get_temp_dir() . '/pepito.json';
$pepito = Client::load($cacheFile);       // state survives across requests

if ($pepito->state()['age'] > 300) {
    $pepito->refresh();
    $pepito->save($cacheFile);
}
echo $pepito->state()['state'];           // away

// daemon:
$pepito->watch(function ($update) use ($pepito, $cacheFile) {
    if ($update instanceof Sighting && $update->changed) {
        echo $update->way->value, PHP_EOL;
        $pepito->save($cacheFile);
    }
});
```

## PSR

All integrations go through PSR interfaces and are **optional**. Without them the client uses curl, the system clock and files, so it works with nothing at all installed in `vendor/`.

| Standard        | Where                                        | How to provide it      |
| --------------- | -------------------------------------------- | ---------------------- |
| PSR-4           | autoloading, `Pepito\`                       | Composer               |
| PSR-12          | code style                                   | `composer format`      |
| PSR-3           | reconnect and error logs                     | `setLogger()`          |
| PSR-14          | events from `feed()`, `watch()`, `refresh()` | `setEventDispatcher()` |
| PSR-16          | snapshot instead of files                    | `setCache()`           |
| PSR-18 + PSR-17 | `refresh()`                                  | `setHttpClient()`      |
| PSR-20          | time source for `state()`                    | `setClock()`           |

```php
$pepito->setEventDispatcher($bus)->setClock($clock)->setCache($redis, 'cat');
$pepito->setHttpClient($guzzle, $psr17);
```

PSR-14 events are typed, so a listener binds by class: `Pepito\Event\Sighting`, `Pepito\Event\Heartbeat`, both extend `Pepito\Event\Update`.

**`watch()` deliberately does not use PSR-18.** That standard describes one request and one response, with no way to ask for a stream, so against an infinite SSE feed a buffering implementation just hangs forever. The stream is handled by curl instead: TLS, unchunking, splitting headers, and silence is detected via `CURLOPT_LOW_SPEED_*`.

## Archive

```php
$st = Pepito\Client::historyStats(file_get_contents('tweets.json'));
// total 23564, outings 8339, median outing 58 min, by_hour_out/by_hour_in/by_weekday_out distributions
```

Pairing outings: an outing is the last `out` before an `in`. Consecutive `out` events, which happen in the archive when a return got lost, count as `unpaired`.

More on the architecture of the whole repo (crates + npm + composer together):
see the [README](https://github.com/wielorzeczownik/pepito-client#readme) at the repo root.
