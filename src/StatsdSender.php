<?php

declare(strict_types=1);

namespace EzPhp\MetricsStatsd;

/**
 * Fire-and-forget StatsD client over plain UDP.
 *
 * Each call writes exactly one datagram (`<prefix><name>:<value>|<type>[|#tags]`).
 * The socket is opened lazily on first use. Transport problems (unreachable
 * host, closed port, full send buffer) never throw: the send methods return
 * `false` instead, so instrumentation can never break the request it observes.
 * Only invalid input (bad name, tag, host or port) raises StatsdException.
 *
 * Tags use the DogStatsD `|#key:value,...` extension; plain StatsD servers
 * ignore them, so omit the argument when targeting one.
 *
 * @package EzPhp\MetricsStatsd
 */
final class StatsdSender
{
    /**
     * Open UDP stream, or null before the first send / after a failed connect.
     *
     * Resources cannot be typed as a property in PHP.
     *
     * @var resource|null
     */
    private $socket = null;

    /**
     * @param string $host    StatsD server host name or IP address
     * @param int    $port    StatsD server UDP port (default 8125)
     * @param string $prefix  Prepended verbatim to every metric name, e.g. `myapp.`
     * @param float  $timeout Connect timeout in seconds
     *
     * @throws StatsdException If the host is empty, the port is out of range or the prefix is invalid
     */
    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8125,
        private readonly string $prefix = '',
        private readonly float $timeout = 0.1,
    ) {
        if ($host === '') {
            throw new StatsdException('StatsD host must not be empty.');
        }

        if ($port < 1 || $port > 65535) {
            throw new StatsdException("StatsD port must be between 1 and 65535, got {$port}.");
        }

        if ($prefix !== '') {
            self::assertSafe($prefix, 'prefix');
        }
    }

    /**
     * Adds $value to a counter.
     *
     * @param array<string, string> $tags
     *
     * @throws StatsdException
     */
    public function count(string $name, int $value = 1, array $tags = []): bool
    {
        return $this->send($name, (string) $value, 'c', $tags);
    }

    /**
     * Sets a gauge to an absolute value.
     *
     * @param array<string, string> $tags
     *
     * @throws StatsdException
     */
    public function gauge(string $name, float $value, array $tags = []): bool
    {
        return $this->send($name, self::formatFloat($value), 'g', $tags);
    }

    /**
     * Records a timing in milliseconds.
     *
     * @param array<string, string> $tags
     *
     * @throws StatsdException
     */
    public function timing(string $name, float $milliseconds, array $tags = []): bool
    {
        return $this->send($name, self::formatFloat($milliseconds), 'ms', $tags);
    }

    /**
     * Closes the socket. A later send reopens it.
     */
    public function close(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * @param array<string, string> $tags
     *
     * @throws StatsdException
     */
    private function send(string $name, string $value, string $type, array $tags): bool
    {
        self::assertSafe($name, 'metric name');

        $packet = "{$this->prefix}{$name}:{$value}|{$type}" . self::formatTags($tags);
        $socket = $this->connect();

        if ($socket === null) {
            return false;
        }

        return @fwrite($socket, $packet) === strlen($packet);
    }

    /**
     * @return resource|null
     */
    private function connect()
    {
        if ($this->socket === null) {
            $socket = @stream_socket_client(
                "udp://{$this->host}:{$this->port}",
                $errorCode,
                $errorMessage,
                $this->timeout,
            );

            if ($socket === false) {
                return null;
            }

            $this->socket = $socket;
        }

        return $this->socket;
    }

    /**
     * @param array<string, string> $tags
     *
     * @throws StatsdException
     */
    private static function formatTags(array $tags): string
    {
        if ($tags === []) {
            return '';
        }

        $parts = [];

        foreach ($tags as $key => $value) {
            $key = (string) $key;
            self::assertSafe($key, 'tag key');
            self::assertSafe($value, 'tag value');
            $parts[] = "{$key}:{$value}";
        }

        return '|#' . implode(',', $parts);
    }

    /**
     * Rejects empty values and characters that would corrupt the wire format.
     *
     * @throws StatsdException
     */
    private static function assertSafe(string $value, string $what): void
    {
        if ($value === '' || preg_match('/[:|@,#\s]/', $value) === 1) {
            throw new StatsdException(
                "Invalid StatsD {$what} '{$value}': must be non-empty and free of ':', '|', '@', ',', '#' and whitespace.",
            );
        }
    }

    private static function formatFloat(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }
}
