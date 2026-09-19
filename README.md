# ez-php/metrics-statsd

Dependency-free StatsD exporter for the ez-php framework — a fire-and-forget UDP sender for counters, gauges and timings.

It complements [`ez-php/metrics`](https://github.com/ez-php/metrics) (Prometheus pull endpoint) for setups that push to a StatsD/DogStatsD agent instead. It has no runtime dependencies and can be used standalone.

---

## Installation

```bash
composer require ez-php/metrics-statsd
```

---

## Usage

```php
use EzPhp\MetricsStatsd\StatsdSender;

$statsd = new StatsdSender(host: '127.0.0.1', port: 8125, prefix: 'myapp.');

$statsd->count('http.requests');                        // myapp.http.requests:1|c
$statsd->count('bytes.sent', 1024);                     // myapp.bytes.sent:1024|c
$statsd->gauge('queue.depth', 17.0);                    // myapp.queue.depth:17|g
$statsd->timing('http.latency', 12.5);                  // myapp.http.latency:12.5|ms
$statsd->count('http.requests', 1, ['status' => '200']); // …|c|#status:200  (DogStatsD tags)
```

- One datagram per call; the socket opens lazily on first use. Call `close()` to release it.
- Each method returns `true` if the datagram was handed to the OS, `false` otherwise.
- Transport failures never throw, so instrumentation cannot break a request.
- Invalid names, tags, hosts or ports throw `StatsdException`; metric names, tag keys and tag values must be non-empty and free of `:`, `|`, `@`, `,`, `#` and whitespace.

### Alongside `ez-php/metrics`

`ez-php/metrics` needs no changes: call `StatsdSender` next to the `Metrics::counter()` / `Metrics::gauge()` calls you already make, or from a listener/middleware of your own.

---

## Development

```bash
./start.sh
composer full
```

## License

MIT
