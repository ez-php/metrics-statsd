# Changelog

All notable changes to `ez-php/metrics-statsd` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

### Added
- `StatsdSender` — fire-and-forget StatsD client over plain UDP; `count()`, `gauge()`, `timing()`, `close()`; optional prefix and DogStatsD tags; transport failures return `false`
- `StatsdException` — thrown for invalid host, port, metric name or tag
