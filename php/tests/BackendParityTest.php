<?php

declare(strict_types=1);

namespace Pepito\Tests;

use Pepito\Client;
use Pepito\Event\Sighting;
use Pepito\Exception\ExceptionInterface;
use Pepito\Exception\InvalidArchiveException;
use Pepito\Exception\LibraryNotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * The plain PHP backend against libpepito through FFI: the same input has to
 * give the same output, down to key order and types.
 */
final class BackendParityTest extends TestCase
{
    private const TWEETS = <<<'JSON'
        [
          {"full_text":"Pepito est sorti (12:47:41)","way":"out","created_at":"Sun Nov 13 10:47:15 +0000 2011","media":""},
          {"full_text":"Pepito est sorti (17:20:30)","way":"out","created_at":"Sun Nov 13 15:20:04 +0000 2011","media":""},
          {"full_text":"Pepito est rentre (17:30:30)","way":"in","created_at":"Sun Nov 13 15:30:04 +0000 2011","media":"m.jpg"},
          {"full_text":"broken","way":"sideways","created_at":"Sun Nov 13 15:30:04 +0000 2011","media":""},
          {"full_text":"wrong weekday","way":"in","created_at":"Mon Nov 13 15:30:04 +0000 2011"},
          {"full_text":"no such day","way":"in","created_at":"Wed Feb 30 15:30:04 +0000 2011"},
          {"full_text":"offset","way":"in","created_at":"Sun Nov 13 17:30:04 +0200 2011"}
        ]
        JSON;

    private const STREAM = [
        ": a comment\nevent: message\n",
        "data: {\"event\":\"heartbeat\",\"time\":100}\r\n",
        "data: {\"event\":\"pepito\",\"type\":\"out\",\"time\":110,\"img\":\"a/b.jpg\"}\n",
        "data: {\"event\":\"pepito\",\"type\":\"out\",\"time\":110,\"img\":\"a/b.jpg\"}\n",
        "data: {\"event\":\"pepito\",\"type\":\"out\",\"time\":150,\"img\":null}\n",
        'data:{"event":"pepi',
        "to\",\"type\":\"in\",\"time\":90}\n{\"event\":\"heartbeat\",\"time\":50}\n",
        "data: {\"event\":\"pepito\",\"type\":\"sideways\",\"time\":1}\ndata: {\"event\":\"pepito\",\"type\":\"in\",\"time\":1.5}\n",
        "data: {\"event\":\"pepito\",\"type\":\"in\",\"time\":200,\"img\":7}\n",
        "data: {\"event\":\"pepito\",\"type\":\"in\",\"time\":200}\ndata: {invalid json}\n",
    ];

    private const BROKEN_ARCHIVES = [
        '{',
        '{}',
        '[{"way":"in"}]',
        '[{"full_text":"","way":"in","created_at":"","media":null}]',
    ];

    public function test_history_pairs_outings(): void
    {
        $stats = Client::historyStats(self::TWEETS);

        self::assertSame([2, 2, 1, 1], [$stats['outs'], $stats['ins'], $stats['outings'], $stats['unpaired']]);
        self::assertSame(600, $stats['longest']['secs']);
        self::assertSame(1, $stats['by_hour_out'][10]);
        self::assertSame(2, $stats['by_weekday_out'][0], '13 Nov 2011 was a Sunday');

        $sightings = Client::historyParse(self::TWEETS);
        self::assertContainsOnlyInstancesOf(Sighting::class, $sightings);
        self::assertSame(1321181235, $sightings[0]->time);
        self::assertSame('m.jpg', $sightings[2]->img);
    }

    public function test_a_broken_archive_throws(): void
    {
        foreach (self::BROKEN_ARCHIVES as $json) {
            try {
                Client::historyStats($json);
                self::fail("$json should have thrown");
            } catch (InvalidArchiveException $error) {
                self::assertInstanceOf(ExceptionInterface::class, $error);
            }
        }
    }

    public function test_stream_state_and_snapshot_match_ffi(): void
    {
        self::requireFfi();
        $php = new Client;
        $ffi = Client::withFfi();

        foreach (self::STREAM as $chunk) {
            self::assertEquals($ffi->feed($chunk), $php->feed($chunk), $chunk);
            self::assertSame($ffi->state(), $php->state(), $chunk);
        }
        self::assertSame($ffi->snapshot(), $php->snapshot());
        foreach ([$php->snapshot(), '', 'garbage', '{"count_in":-1,"count_out":0}', '{"last":null,"count_in":1,"count_out":2}'] as $snapshot) {
            self::assertSame(Client::withFfi($snapshot)->state(), (new Client($snapshot))->state(), $snapshot);
        }
    }

    public function test_archive_matches_ffi(): void
    {
        self::requireFfi();

        self::assertSame(Client::historyStats(self::TWEETS, ffi: true), Client::historyStats(self::TWEETS));
        self::assertEquals(Client::historyParse(self::TWEETS, ffi: true), Client::historyParse(self::TWEETS));
        foreach (self::BROKEN_ARCHIVES as $json) {
            try {
                Client::historyStats($json, ffi: true);
                self::fail("$json should have thrown");
            } catch (InvalidArchiveException) {
                self::addToAssertionCount(1);
            }
        }

        $archive = \dirname(__DIR__, 2).'/tweets.json';
        if (is_file($archive)) {
            $json = (string) file_get_contents($archive);
            self::assertSame(Client::historyStats($json, ffi: true), Client::historyStats($json));
        }
    }

    private static function requireFfi(): void
    {
        try {
            Client::lib();
        } catch (LibraryNotFoundException $error) {
            self::markTestSkipped($error->getMessage());
        }
    }
}
