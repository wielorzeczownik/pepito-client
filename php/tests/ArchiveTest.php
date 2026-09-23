<?php

declare(strict_types=1);

namespace Pepito\Tests;

use Pepito\Client;
use PHPUnit\Framework\TestCase;

final class ArchiveTest extends TestCase
{
    public function test_archive_stats_parse_the_downloaded_tweets(): void
    {
        $stats = Client::historyStats((string) file_get_contents(Client::ARCHIVE_URL));

        self::assertGreaterThan(1000, $stats['outings']);
        self::assertGreaterThan(0, $stats['avg_outing_secs']);
    }
}
