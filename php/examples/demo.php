<?php

declare(strict_types=1);

// php php/examples/demo.php            state + statistics from the archive
// php php/examples/demo.php watch      the live stream
require __DIR__.'/../../vendor/autoload.php';

use Pepito\Client;
use Pepito\Enums\State;
use Pepito\Event\Sighting;

$cacheFile = sys_get_temp_dir().'/pepito.json';
$pepito = Client::load($cacheFile);

if (($argv[1] ?? '') === 'watch') {
    $pepito->watch(function ($update) use ($pepito, $cacheFile) {
        if ($update instanceof Sighting && $update->changed) {
            printf("%s -> %s\n", date('H:i:s', $update->time), $update->way->value);
            $pepito->save($cacheFile);
        }
    });
}

$state = $pepito->state();
if ($state['state'] === State::Unknown->value || $state['age'] > 300) {
    $pepito->refresh();
    $pepito->save($cacheFile);
    $state = $pepito->state();
}
printf("the cat is %s since %s\n", $state['state'], date('c', (int) $state['since']));

$stats = Client::historyStats((string) file_get_contents(Client::ARCHIVE_URL));
printf(
    "archive: %d events, %d outings, median %d min\n",
    $stats['total'],
    $stats['outings'],
    (int) round($stats['median_outing_secs'] / 60)
);
