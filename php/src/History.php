<?php

declare(strict_types=1);

namespace Pepito;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Pepito\Exception\InvalidArchiveException;
use stdClass;

/**
 * The tweet archive (see Client::ARCHIVE_URL) reduced to the same sightings as
 * the live stream, plus statistics. Plain PHP, the default backend of Client.
 *
 * @internal Use Client::historyStats() and Client::historyParse().
 */
final class History
{
    private const MONTHS = [
        'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6,
        'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
    ];

    private const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    /**
     * Sightings sorted by time. Entries with an unknown direction or a broken
     * date are skipped; a file that is not an array of tweets throws.
     *
     * @return list<array{way: string, time: int, img?: string, text?: string}>
     */
    public static function parse(string $json): array
    {
        try {
            // Objects stay objects, so that `{}` is not mistaken for an empty list.
            $tweets = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArchiveException($error->getMessage(), previous: $error);
        }
        if (! \is_array($tweets)) {
            throw new InvalidArchiveException('expected an array of tweets');
        }
        $sightings = [];
        foreach ($tweets as $index => $tweet) {
            // A missing media means none, but a null one is a broken tweet.
            $media = $tweet instanceof stdClass && property_exists($tweet, 'media') ? $tweet->media : '';
            if (! $tweet instanceof stdClass
                || ! \is_string($tweet->full_text ?? null)
                || ! \is_string($tweet->way ?? null)
                || ! \is_string($tweet->created_at ?? null)
                || ! \is_string($media)) {
                throw new InvalidArchiveException("tweet $index is not a tweet");
            }
            $time = self::twitterTime($tweet->created_at);
            if ($time !== null && Tracker::isWay($tweet->way)) {
                $sightings[] = Tracker::sighting($tweet->way, $time, $media === '' ? null : $media, $tweet->full_text);
            }
        }
        usort($sightings, static fn (array $left, array $right): int => $left['time'] <=> $right['time']);

        return $sightings;
    }

    /**
     * Twitter's own format, `Sun Nov 13 10:47:15 +0000 2011`.
     */
    public static function twitterTime(string $text): ?int
    {
        if (! preg_match('/^(\w{3}) (\w{3}) (\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2}) ([+-])(\d{2}):?(\d{2}) (\d{4})$/', $text, $match)) {
            return null;
        }
        [, $weekday, $monthName, $day, $hour, $minute, $second, $sign, $offsetHours, $offsetMinutes, $year] = $match;
        $month = self::MONTHS[$monthName] ?? null;
        [$day, $hour, $minute, $second, $offsetHours, $offsetMinutes, $year]
            = array_map(intval(...), [$day, $hour, $minute, $second, $offsetHours, $offsetMinutes, $year]);
        if ($month === null || $hour > 23 || $minute > 59 || $second > 59 || $offsetMinutes > 59 || ! checkdate($month, $day, $year)) {
            return null;
        }
        $midnight = DateTimeImmutable::createFromFormat('!Y-n-j', \sprintf('%04d-%d-%d', $year, $month, $day), new DateTimeZone('UTC'));
        if ($midnight === false || self::WEEKDAYS[(int) $midnight->format('w')] !== $weekday) {
            return null;
        }
        $offset = ($sign === '-' ? -1 : 1) * ($offsetHours * 3600 + $offsetMinutes * 60);

        return $midnight->getTimestamp() + $hour * 3600 + $minute * 60 + $second - $offset;
    }

    /**
     * Pairs every `in` with the last `out` before it. Two `out` events in a row
     * overwrite each other, because they mean a return went missing.
     *
     * @param  list<array{way: string, time: int}>  $sightings
     * @return array<string, mixed>
     */
    public static function stats(array $sightings): array
    {
        $stats = [
            'total' => \count($sightings),
            'ins' => 0,
            'outs' => 0,
            'first' => $sightings[0]['time'] ?? null,
            'last' => $sightings === [] ? null : $sightings[array_key_last($sightings)]['time'],
            'outings' => 0,
            'unpaired' => 0,
            'avg_outing_secs' => 0,
            'median_outing_secs' => 0,
            'longest' => null,
            'shortest' => null,
            'by_hour_out' => array_fill(0, 24, 0),
            'by_hour_in' => array_fill(0, 24, 0),
            'by_weekday_out' => array_fill(0, 7, 0),
        ];
        $pending = null;
        $durations = [];

        foreach ($sightings as ['way' => $way, 'time' => $time]) {
            $hour = (int) gmdate('G', $time);
            if ($way === 'out') {
                $stats['outs']++;
                $stats['by_hour_out'][$hour]++;
                $stats['by_weekday_out'][(int) gmdate('w', $time)]++;
                if ($pending !== null) {
                    $stats['unpaired']++;
                }
                $pending = $time;

                continue;
            }
            $stats['ins']++;
            $stats['by_hour_in'][$hour]++;
            if ($pending === null) {
                continue;
            }
            $outing = ['out_at' => $pending, 'in_at' => $time, 'secs' => $time - $pending];
            $pending = null;
            $durations[] = $outing['secs'];
            if ($stats['longest'] === null || $outing['secs'] > $stats['longest']['secs']) {
                $stats['longest'] = $outing;
            }
            if ($stats['shortest'] === null || $outing['secs'] < $stats['shortest']['secs']) {
                $stats['shortest'] = $outing;
            }
        }
        if ($pending !== null) {
            $stats['unpaired']++;
        }
        $stats['outings'] = \count($durations);
        if ($durations !== []) {
            $stats['avg_outing_secs'] = intdiv(array_sum($durations), \count($durations));
            sort($durations);
            $stats['median_outing_secs'] = $durations[intdiv(\count($durations), 2)];
        }

        return $stats;
    }
}
