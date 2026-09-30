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

Register the bridge once and every value recorded through the registry is also sent to StatsD:

```php
use EzPhp\MetricsStatsd\StatsdMetricsListener;
use EzPhp\MetricsStatsd\StatsdSender;

$registry->listen(new StatsdMetricsListener(new StatsdSender('127.0.0.1', 8125, 'app.')));
```

Counters become `count` (rounded to an integer), gauges `gauge` (the resulting value), histogram
observations `timing` in milliseconds (`histogramToMilliseconds`, default 1000 for seconds-based
histograms). Labels become DogStatsD tags.

---

## Development

```bash
./start.sh
composer full
```

## License

MIT
