<?php

declare(strict_types=1);

namespace EzPhp\MetricsStatsd;

use RuntimeException;

/**
 * Base exception for the ez-php/metrics-statsd module.
 *
 * Thrown only for programmer errors (invalid host, port, metric name or tag);
 * transport failures never throw — see StatsdSender.
 *
 * @package EzPhp\MetricsStatsd
 */
final class StatsdException extends RuntimeException
{
}
