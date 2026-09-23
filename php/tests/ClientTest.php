<?php

declare(strict_types=1);

namespace Pepito\Tests;

use Pepito\Client;
use Pepito\Enums\State;
use Pepito\Enums\UpdateKind;
use Pepito\Enums\Way;
use Pepito\Event\Update;
use Pepito\Exception\ExceptionInterface;
use Pepito\Exception\MissingStorageException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function test_fresh_tracker_starts_unknown(): void
    {
        $pepito = new Client;
        self::assertSame(State::Unknown->value, $pepito->state()['state']);
    }

    public function test_stream_split_at_a_random_point_still_joins_up(): void
    {
        $pepito = new Client;

        // A stream split at a random point, the way the network does it.
        $pepito->feed('data: {"event":"heartbeat","time":1725714568}'."\n\n".'data: {"event":"pepi');
        $pepito->feed('to","type":"out","time":1725714575,"img":"a.jpg"}'."\n\n");

        self::assertSame(State::Away->value, $pepito->state()['state']);
        self::assertSame(1725714575, $pepito->state()['since']);
        self::assertSame('a.jpg', $pepito->state()['last']['img']);
    }

    public function test_repeat_after_reconnect_moves_neither_state_nor_counters(): void
    {
        $pepito = new Client;
        $pepito->feed('data: {"event":"pepito","type":"out","time":1725714575,"img":"a.jpg"}'."\n");

        $duplicate = $pepito->feed('data: {"event":"pepito","type":"out","time":1725714575,"img":"a.jpg"}'."\n");

        self::assertSame(UpdateKind::Duplicate, $duplicate[0]->kind);
        self::assertSame(1, $pepito->state()['count_out']);
    }

    public function test_in_moves_the_counter_and_state(): void
    {
        $pepito = new Client;
        $pepito->feed('data: {"event":"pepito","type":"out","time":1725714575,"img":"a.jpg"}'."\n");

        $pepito->feed('data: {"event":"pepito","type":"in","time":1725715521}'."\n");

        self::assertSame(State::Home->value, $pepito->state()['state']);
        self::assertSame(1, $pepito->state()['count_in']);
    }

    /**
     * The shape of status(): the contract shared with the JS binding.
     */
    public function test_status_carries_every_field(): void
    {
        $pepito = new Client;
        $pepito->feed('data: {"event":"pepito","type":"out","time":1725714575,"img":"a.jpg"}'."\n");

        foreach (['state', 'since', 'last', 'age', 'heartbeat_age', 'healthy', 'count_in', 'count_out'] as $key) {
            self::assertArrayHasKey($key, $pepito->state(), "status is missing field $key");
        }
    }

    public function test_no_image_means_no_field_instead_of_null(): void
    {
        $pepito = new Client;
        $pepito->feed('data: {"event":"pepito","type":"out","time":1725714575}'."\n");

        self::assertArrayNotHasKey('img', $pepito->state()['last']);
    }

    public function test_cache_survives_a_process_restart(): void
    {
        $pepito = new Client;
        $pepito->feed('data: {"event":"pepito","type":"out","time":1725714575,"img":"a.jpg"}'."\n");
        $pepito->feed('data: {"event":"pepito","type":"in","time":1725715521}'."\n");

        $file = tempnam(sys_get_temp_dir(), 'pepito');
        $pepito->save($file);
        $revived = Client::load($file);
        unlink($file);

        self::assertSame('home', $revived->state()['state']);
        self::assertSame(1725715521, $revived->state()['since']);
    }

    public function test_garbage_does_not_take_the_tracker_down(): void
    {
        $pepito = new Client;
        $pepito->feed(": a comment\nevent: message\ndata: {invalid json}\n");

        self::assertSame('unknown', $pepito->state()['state']);
    }

    public function test_missing_cache_file_means_a_fresh_tracker(): void
    {
        self::assertSame('unknown', Client::load('/no/such/path')->state()['state']);
    }

    // --- The enums and exceptions are contracts, so they are checked against the core. ---

    /**
     * Whatever the core emits has to be a value the enums actually name. This
     * is what turns them from decoration into a drift check. feed()'s
     * UpdateKind is covered by test_an_unknown_event_kind_fails_loudly() instead:
     * Update::from() throws on an unnamed one, so a mismatch fails loudly at
     * construction rather than needing an assertion here.
     */
    public function test_state_emits_a_named_state(): void
    {
        $pepito = new Client;
        $pepito->feed('data: {"event":"pepito","type":"out","time":1725714575}'."\n");

        self::assertNotNull(State::tryFrom($pepito->state()['state']));
        self::assertNotNull(Way::tryFrom($pepito->state()['last']['way']));
    }

    public function test_state_names_exactly_three_cases(): void
    {
        self::assertCount(3, State::cases());
    }

    public function test_way_from_maps_a_known_value(): void
    {
        self::assertSame(Way::Out, Way::from('out'));
    }

    public function test_way_try_from_refuses_an_unknown_value(): void
    {
        self::assertNull(Way::tryFrom('sideways'));
    }

    public function test_way_from_throws_on_an_unknown_value(): void
    {
        $this->expectException(\ValueError::class);
        Way::from('sideways');
    }

    /**
     * An event kind this build has never heard of means libpepito drifted away.
     */
    public function test_an_unknown_event_kind_fails_loudly(): void
    {
        $this->expectException(\ValueError::class);
        Update::from(['kind' => 'teleported', 'time' => 1]);
    }

    public function test_save_without_a_target_throws(): void
    {
        $this->expectException(MissingStorageException::class);
        (new Client)->save();
    }

    public function test_missing_storage_exception_is_catchable_as_ours(): void
    {
        try {
            (new Client)->save();
            self::fail('save() without a target should have thrown');
        } catch (MissingStorageException $error) {
            self::assertInstanceOf(ExceptionInterface::class, $error);
            self::assertInstanceOf(\LogicException::class, $error);
        }
    }
}
