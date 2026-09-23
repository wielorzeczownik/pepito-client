<?php

declare(strict_types=1);

namespace Pepito\Tests;

use GuzzleHttp\Client as GuzzleClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Pepito\Client;
use Pepito\Enums\UpdateKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Hits the real Pepito API. Excluded by default (see phpunit.xml), run with:
 * vendor/bin/phpunit --group live
 */
#[Group('live')]
final class LiveTest extends TestCase
{
    public function test_guzzle_as_a_psr18_implementation_works_unchanged(): void
    {
        if (! class_exists(GuzzleClient::class)) {
            self::markTestSkipped('guzzlehttp/guzzle is not installed');
        }

        $viaGuzzle = new Client;
        $viaGuzzle->setHttpClient(new GuzzleClient(['timeout' => 10]), new Psr17Factory);

        $update = $viaGuzzle->refresh();

        self::assertSame(UpdateKind::Sighting, $update?->kind);
        self::assertContains($viaGuzzle->state()['state'], ['home', 'away']);
    }

    public function test_refresh_against_the_real_api(): void
    {
        $live = new Client;
        $live->refresh();

        self::assertContains($live->state()['state'], ['home', 'away']);
    }
}
