<?php

declare(strict_types=1);

namespace Pepito\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Pepito\Client;
use Pepito\Enums\UpdateKind;
use Pepito\Enums\Way;
use Pepito\Event\Heartbeat;
use Pepito\Event\Sighting;
use Pepito\Event\Update;
use Pepito\Tests\Support\ArrayCache;
use Pepito\Tests\Support\CannedHttpClient;
use Pepito\Tests\Support\CollectingDispatcher;
use Pepito\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

/**
 * The PSR collaborators. Every dependency added has to be proven by something.
 */
final class PsrIntegrationTest extends TestCase
{
    public function test_psr20_clock_decides_the_cache_age(): void
    {
        $psr = new Client;
        $psr->setClock(new FrozenClock(1725715521 + 300));
        $psr->feed('data: {"event":"pepito","type":"in","time":1725715521}'."\n");

        self::assertSame(300, $psr->state()['age']);
    }

    public function test_psr14_events_reach_the_dispatcher_with_the_right_types(): void
    {
        $bus = new CollectingDispatcher;
        $psr14 = new Client;
        $psr14->setEventDispatcher($bus);
        $psr14->feed('data: {"event":"heartbeat","time":10}'."\n".'data: {"event":"pepito","type":"out","time":20,"img":"b.jpg"}'."\n");

        self::assertCount(2, $bus->seen);
        self::assertInstanceOf(Heartbeat::class, $bus->seen[0]);
        self::assertInstanceOf(Sighting::class, $bus->seen[1]);
        self::assertInstanceOf(Update::class, $bus->seen[1]);
        self::assertSame(Way::Out, $bus->seen[1]->way);
        self::assertSame('b.jpg', $bus->seen[1]->img);
    }

    public function test_psr16_snapshot_round_trips_without_touching_disk(): void
    {
        $cache = new ArrayCache;
        $writer = new Client;
        $writer->setCache($cache, 'cat');
        $writer->feed('data: {"event":"pepito","type":"in","time":1725715521}'."\n");
        $writer->save();

        self::assertTrue($cache->has('cat'));

        $reader = new Client;
        $reader->setCache($cache, 'cat');

        self::assertTrue($reader->restoreFromCache());
        self::assertSame('home', $reader->state()['state']);
    }

    public function test_psr18_refresh_goes_through_the_injected_client(): void
    {
        $canned = new CannedHttpClient('{"event":"pepito","type":"out","time":1725714575,"img":"c.jpg"}');
        $psr18 = new Client;
        $psr18->setHttpClient($canned, new Psr17Factory);

        $update = $psr18->refresh();

        self::assertSame(UpdateKind::Sighting, $update?->kind);
        self::assertSame('away', $psr18->state()['state']);
        self::assertSame(Client::REST_URL, (string) $canned->lastRequest->getUri());
        self::assertSame('GET', $canned->lastRequest->getMethod());
    }
}
