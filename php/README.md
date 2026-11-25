<h1 align="center">pepito-client</h1>

<p align="center">
  <a href="https://packagist.org/packages/wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/packagist/v/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="Packagist"/></picture></a> <a href="https://packagist.org/packages/wielorzeczownik/pepito-client"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/packagist/dt/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/packagist/dt/wielorzeczownik/pepito-client?style=flat-square&color=2ea043"/><img src="https://img.shields.io/packagist/dt/wielorzeczownik/pepito-client?style=flat-square&labelColor=2d333b&color=3fb950" alt="Packagist downloads"/></picture></a> <a href="https://github.com/wielorzeczownik/pepito-client/blob/main/LICENSE"><picture><source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b"/><source media="(prefers-color-scheme: light)" srcset="https://img.shields.io/badge/License-MIT-2ea043?style=flat-square"/><img src="https://img.shields.io/badge/License-MIT-3fb950?style=flat-square&labelColor=2d333b" alt="License: MIT"/></picture></a>
  <br/>
  <img src="https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP"/>
</p>

<p align="center">
  <img src="https://raw.githubusercontent.com/wielorzeczownik/pepito-client/main/assets/logo.svg" alt="pepito-client logo" width="200" />
</p>

A [Pepito API](https://github.com/Clement87/Pepito-API) client in plain PHP. It needs PHP 8.2+ with the `curl` extension and nothing else. The Rust core through FFI is available as an opt-in, see [FFI](#ffi).

```sh
composer require wielorzeczownik/pepito-client
```

```php
require 'vendor/autoload.php';

use Pepito\Client;
use Pepito\Event\Sighting;

$cacheFile = sys_get_temp_dir() . '/pepito.json';
$pepito = Client::load($cacheFile); // state survives between requests

if ($pepito->state()['age'] > 300) {
    $pepito->refresh();
    $pepito->save($cacheFile);
}
echo $pepito->state()['state']; // away
```

For a long-running process, `watch()` follows the stream and reconnects on its own:

```php
$pepito->watch(function ($update) use ($pepito, $cacheFile) {
    if ($update instanceof Sighting && $update->changed) {
        echo $update->way->value, PHP_EOL;
        $pepito->save($cacheFile);
    }
});
```

## PSR

Every integration is optional. Without them the client uses curl, the system clock and files.

| Standard        | Used for                                     | Set with               |
| --------------- | -------------------------------------------- | ---------------------- |
| PSR-3           | reconnect and error logs                     | `setLogger()`          |
| PSR-14          | events from `feed()`, `watch()`, `refresh()` | `setEventDispatcher()` |
| PSR-16          | storing the snapshot instead of a file       | `setCache()`           |
| PSR-18 + PSR-17 | `refresh()`                                  | `setHttpClient()`      |
| PSR-20          | the clock behind `state()`                   | `setClock()`           |

```php
$pepito->setEventDispatcher($bus)->setClock($clock)->setCache($redis, 'cat');
$pepito->setHttpClient($guzzle, $psr17);
```

Events are typed, so listeners bind by class: `Pepito\Event\Sighting` and `Pepito\Event\Heartbeat`, both extending `Pepito\Event\Update`.

`watch()` always uses curl, not PSR-18. PSR-18 has no way to ask for a stream, so a client that buffers the response would hang forever on an endless SSE feed.

## Archive

```php
$stats = Client::historyStats(file_get_contents('tweets.json'));
// total, outings, avg_outing_secs, median_outing_secs, by_hour_out, by_hour_in, by_weekday_out
```

An outing is the last `out` before an `in`. When the archive has several `out` in a row (a return got lost), the extra ones count as `unpaired`. Input that isn't an array of tweets throws `Pepito\Exception\InvalidArchiveException`.

## FFI

The same client on top of the Rust core, faster on large archives. It needs the `ffi` extension with `ffi.enable=1`.

```php
$pepito = Client::withFfi($snapshot); // or Client::load($cacheFile, ffi: true)
$stats = Client::historyStats($json, ffi: true);
```

On install, a Composer plugin downloads the prebuilt library for your platform from the GitHub release and checks its SHA-256, so you don't need Rust. Composer asks once whether to trust the plugin (`allow-plugins`).

If the extension or the library is missing you get a `LibraryNotFoundException`. It doesn't fall back to the plain PHP version. Snapshots are the same in both, so either one restores what the other saved.

This is one of three packages built from the same repository. See the [main README](https://github.com/wielorzeczownik/pepito-client#readme) for how they fit together.
