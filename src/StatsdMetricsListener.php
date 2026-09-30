<?php

declare(strict_types=1);

namespace EzPhp\MetricsStatsd;

use EzPhp\Metrics\MetricRecorded;
use EzPhp\Metrics\MetricsListenerInterface;
use EzPhp\Metrics\MetricType;

/**
 * Class StatsdMetricsListener
 *
 * Bridges ez-php/metrics into StatsD: register it with
 * `MetricsRegistry::listen()` and every recorded value is sent as it happens —
 * counter increments as `count` (rounded to an integer, StatsD counters are
 * integral), gauge values as `gauge`, histogram observations as `timing`
 * (multiplied by `$histogramToMilliseconds`, 1000 for the usual seconds-based
 * histograms). Labels become tags.
 *
 * Requires ez-php/metrics (a `suggest` of this module). Send failures are
 * swallowed: StatsD is fire-and-forget, and the listener must not throw.
 *
 * @package EzPhp\MetricsStatsd
 */
final readonly class StatsdMetricsListener implements MetricsListenerInterface
{
    /**
     * @param StatsdSender $sender
     * @param float        $histogramToMilliseconds Factor from histogram units to StatsD milliseconds.
     */
    public function __construct(
        private StatsdSender $sender,
        private float $histogramToMilliseconds = 1000.0,
    ) {
    }

    /**
     * @param MetricRecorded $event
     *
     * @return void
     */
    public function recorded(MetricRecorded $event): void
    {
        try {
            match ($event->type) {
                MetricType::COUNTER => $this->sender->count($event->name, (int) round($event->value), $event->labels),
                MetricType::GAUGE => $this->sender->gauge($event->name, $event->value, $event->labels),
                MetricType::HISTOGRAM => $this->sender->timing($event->name, $event->value * $this->histogramToMilliseconds, $event->labels),
            };
        } catch (StatsdException) {
            // A name or tag StatsD can't carry: drop the sample, never the request.
        }
    }
}
