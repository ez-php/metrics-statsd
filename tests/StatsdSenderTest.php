<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\MetricsStatsd\StatsdException;
use EzPhp\MetricsStatsd\StatsdSender;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Sends real datagrams to a local UDP socket bound on an ephemeral port.
 */
#[CoversClass(StatsdSender::class)]
final class StatsdSenderTest extends TestCase
{
    /** @var resource */
    private $server;

    private int $port;

    protected function setUp(): void
    {
        $server = stream_socket_server('udp://127.0.0.1:0', $errorCode, $errorMessage, STREAM_SERVER_BIND);
        $this->assertNotFalse($server, "Could not bind UDP test socket: {$errorMessage}");

        $this->server = $server;
        $name = stream_socket_get_name($server, false);
        $this->assertIsString($name);
        $this->port = (int) substr($name, (int) strrpos($name, ':') + 1);
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

        $packet = stream_socket_recvfrom($this->server, 65535);
        $this->assertIsString($packet);

        return $packet;
    }

    public function testCountSendsCounterPacket(): void
    {
        $sender = new StatsdSender('127.0.0.1', $this->port);

        $this->assertTrue($sender->count('requests'));
        $this->assertSame('requests:1|c', $this->receive());

        $this->assertTrue($sender->count('bytes', 42));
        $this->assertSame('bytes:42|c', $this->receive());
    }

    public function testGaugeFormatsFloats(): void
    {
        $sender = new StatsdSender('127.0.0.1', $this->port);

        $sender->gauge('load', 0.75);
        $this->assertSame('load:0.75|g', $this->receive());

        $sender->gauge('workers', 100.0);
        $this->assertSame('workers:100|g', $this->receive());

        $sender->gauge('zero', 0.0);
        $this->assertSame('zero:0|g', $this->receive());
    }

    public function testTimingSendsMilliseconds(): void
    {
        $sender = new StatsdSender('127.0.0.1', $this->port);

        $sender->timing('latency', 12.5);

        $this->assertSame('latency:12.5|ms', $this->receive());
    }

    public function testPrefixIsPrepended(): void
    {
        $sender = new StatsdSender('127.0.0.1', $this->port, 'myapp.');

        $sender->count('hits');

        $this->assertSame('myapp.hits:1|c', $this->receive());
    }

    public function testTagsUseDogStatsdFormat(): void
    {
        $sender = new StatsdSender('127.0.0.1', $this->port);

        $sender->count('http', 1, ['method' => 'GET', 'status' => '200']);

        $this->assertSame('http:1|c|#method:GET,status:200', $this->receive());
    }

    public function testCloseThenSendReopensSocket(): void
    {
        $sender = new StatsdSender('127.0.0.1', $this->port);

        $sender->count('a');
        $this->receive();
        $sender->close();
        $sender->close();

        $this->assertTrue($sender->count('b'));
        $this->assertSame('b:1|c', $this->receive());
    }

    public function testUnresolvableHostReturnsFalseWithoutThrowing(): void
    {
        $sender = new StatsdSender('statsd.invalid', 8125, '', 0.05);

        $this->assertFalse($sender->count('requests'));
    }

    public function testInvalidNameThrows(): void
    {
        $sender = new StatsdSender('127.0.0.1', $this->port);

        $this->expectException(StatsdException::class);

        $sender->count('bad|name');
    }

    public function testEmptyNameThrows(): void
    {
        $sender = new StatsdSender('127.0.0.1', $this->port);

        $this->expectException(StatsdException::class);

        $sender->gauge('', 1.0);
    }

    public function testInvalidTagThrows(): void
    {
        $sender = new StatsdSender('127.0.0.1', $this->port);

        $this->expectException(StatsdException::class);

        $sender->timing('t', 1.0, ['k' => 'a,b']);
    }

    public function testInvalidTagKeyThrows(): void
    {
        $sender = new StatsdSender('127.0.0.1', $this->port);

        $this->expectException(StatsdException::class);

        $sender->count('t', 1, ['bad key' => 'v']);
    }

    public function testEmptyHostThrows(): void
    {
        $this->expectException(StatsdException::class);

        new StatsdSender('');
    }

    public function testPortOutOfRangeThrows(): void
    {
        $this->expectException(StatsdException::class);

        new StatsdSender('127.0.0.1', 70000);
    }

    public function testInvalidPrefixThrows(): void
    {
        $this->expectException(StatsdException::class);

        new StatsdSender('127.0.0.1', 8125, 'bad prefix.');
    }
}
