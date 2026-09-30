<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Metrics\MetricsRegistry;
use EzPhp\MetricsStatsd\StatsdMetricsListener;
use EzPhp\MetricsStatsd\StatsdSender;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * StatsdMetricsListener end to end: registry → listener → UDP datagram.
 *
 * @package Tests
 */
#[CoversClass(StatsdMetricsListener::class)]
#[UsesClass(StatsdSender::class)]
final class StatsdMetricsListenerTest extends TestCase
{
    /** @var resource */
    private $server;

    private MetricsRegistry $registry;

    protected function setUp(): void
    {
        $server = stream_socket_server('udp://127.0.0.1:0', $errorCode, $errorMessage, STREAM_SERVER_BIND);
        $this->assertNotFalse($server, "Could not bind UDP test socket: {$errorMessage}");
        $this->server = $server;
        $name = (string) stream_socket_get_name($server, false);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);

        $this->registry = new MetricsRegistry();
        $this->registry->listen(new StatsdMetricsListener(new StatsdSender('127.0.0.1', $port)));
    }

    protected function tearDown(): void
    {
        fclose($this->server);
    }

    private function receive(): string
    {
        $read = [$this->server];
        $write = null;
        $except = null;
        $this->assertSame(1, stream_select($read, $write, $except, 1), 'No datagram received.');

        return (string) stream_socket_recvfrom($this->server, 65535);
    }

    public function test_counter_increments_become_counts_with_tags(): void
    {
        $this->registry->counter('orders_total', 'Orders')->incBy(3.0, ['shop' => 'eu']);

        $this->assertSame('orders_total:3|c|#shop:eu', $this->receive());
    }

    public function test_gauges_send_the_resulting_value(): void
    {
        $gauge = $this->registry->gauge('workers', 'Workers');
        $gauge->set(4.0);
        $this->receive();
        $gauge->inc();

        $this->assertSame('workers:5|g', $this->receive());
    }

    public function test_histogram_observations_become_timings_in_milliseconds(): void
    {
        $this->registry->histogram('latency_seconds', 'Latency')->observe(0.25);

        $this->assertSame('latency_seconds:250|ms', $this->receive());
    }

    public function test_an_unsendable_name_is_dropped_without_throwing(): void
    {
        $this->registry->counter('bad name|x', 'Bad')->inc();
        $this->registry->counter('good_total', 'Good')->inc();

        $this->assertSame('good_total:1|c', $this->receive());
    }
}
