<?php

namespace App\Support;

/**
 * Customer-facing text for each alert rule, keyed by the rule code.
 * Parameter keys (menit, batas, ...) are stored in the database and must not change.
 */
class AlertRuleCatalog
{
    /** code => [name, description, uses "readings in a row", [[param key, label, unit], ...]] */
    public const RULES = [
        'offline' => [
            'Meter offline',
            'Alert when no data is received for N minutes.',
            false,
            [['menit', 'No data for', 'min']],
        ],
        'error_code' => [
            'Meter error code',
            'Alert when the meter reports an error code.',
            true,
            [],
        ],
        'signal_q' => [
            'Low signal quality',
            'Alert when signal quality drops below the minimum.',
            true,
            [['batas', 'Minimum signal quality', '0–100']],
        ],
        'weak_signal' => [
            'Weak upstream / downstream signal',
            'Alert when the upstream or downstream signal drops below the minimum. Leave empty to turn off.',
            true,
            [['batas', 'Minimum signal', '']],
        ],
        'time_ratio' => [
            'Time ratio out of range',
            'Alert when the time ratio is outside the allowed range.',
            true,
            [['min', 'Minimum', '%'], ['max', 'Maximum', '%']],
        ],
        'backflow' => [
            'Reverse flow',
            'Alert when reverse flow during one day is more than the limit.',
            false,
            [['neg_naik_m3', 'Reverse flow more than', 'm³']],
        ],
        'zero_flow' => [
            'No flow for too long',
            'Alert when the flow stays at zero for N minutes.',
            false,
            [['menit', 'Zero flow for', 'min']],
        ],
        'incomplete_window' => [
            'Incomplete daily data',
            'Alert when a day has missing or inconsistent readings.',
            false,
            [],
        ],
        'clock_drift' => [
            'Meter clock is off',
            'Alert when the meter clock differs from the server by more than N seconds.',
            false,
            [['detik', 'Difference more than', 'sec']],
        ],
    ];

    public static function name(string $code, ?string $fallback = null): string
    {
        return self::RULES[$code][0] ?? $fallback ?? ucfirst(str_replace('_', ' ', $code));
    }

    public static function description(string $code): ?string
    {
        return self::RULES[$code][1] ?? null;
    }

    public static function usesCount(string $code): bool
    {
        return self::RULES[$code][2] ?? false;
    }

    /** @return array<int, array{0: string, 1: string, 2: string}> */
    public static function fields(string $code): array
    {
        return self::RULES[$code][3] ?? [];
    }
}
