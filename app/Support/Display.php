<?php

namespace App\Support;

use App\Models\Alert;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Formatting helpers for everything the customer sees: numbers, dates,
 * durations, status badges and human-readable alert / message text.
 */
class Display
{
    public const DASH = '–';

    public static function tz(): string
    {
        return config('app.display_timezone', 'Asia/Jakarta');
    }

    // ---------- numbers ----------

    public static function num(mixed $v, int $dec = 2, ?string $unit = null): string
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return self::DASH;
        }
        $s = number_format((float) $v, $dec, '.', ',');

        return $unit ? "{$s} {$unit}" : $s;
    }

    public static function volume(mixed $v, bool $unit = true): string
    {
        return self::num($v, 3, $unit ? 'm³' : null);
    }

    // ---------- dates ----------

    public static function local(?CarbonInterface $d): ?Carbon
    {
        return $d ? Carbon::instance($d)->setTimezone(self::tz())->locale('en') : null;
    }

    /** "Thu, 12 Nov 2026 09:00" */
    public static function dateTime(?CarbonInterface $d): string
    {
        return $d ? self::local($d)->format('D, j M Y H:i') : self::DASH;
    }

    /** "12 Nov 09:00" (adds the year when it is not the current year) */
    public static function short(?CarbonInterface $d): string
    {
        if (!$d) {
            return self::DASH;
        }
        $l = self::local($d);

        return $l->format($l->year === now(self::tz())->year ? 'j M H:i' : 'j M Y H:i');
    }

    /** "14:31" for today, otherwise "11 Nov 14:31" */
    public static function smart(?CarbonInterface $d): string
    {
        if (!$d) {
            return self::DASH;
        }
        $l = self::local($d);

        return $l->isSameDay(now(self::tz())) ? $l->format('H:i') : self::short($d);
    }

    /** "just now", "45 sec ago", "2 min ago", "3 h ago", "2 days ago" */
    public static function ago(?int $sec): string
    {
        if ($sec === null) {
            return self::DASH;
        }
        $sec = max(0, $sec);

        return match (true) {
            $sec < 10 => 'just now',
            $sec < 60 => $sec . ' sec ago',
            $sec < 3600 => intdiv($sec, 60) . ' min ago',
            $sec < 172800 => intdiv($sec, 3600) . ' h ago',
            default => intdiv($sec, 86400) . ' days ago',
        };
    }

    public static function agoFrom(?CarbonInterface $d): string
    {
        return $d ? self::ago(now()->timestamp - $d->timestamp) : self::DASH;
    }

    /** "45 sec", "5 min", "2 h 31 min", "3 d 4 h" */
    public static function duration(?int $sec): string
    {
        if ($sec === null) {
            return self::DASH;
        }
        $sec = max(0, $sec);
        if ($sec < 60) {
            return $sec . ' sec';
        }
        $m = intdiv($sec, 60);
        if ($m < 60) {
            return $m . ' min';
        }
        $h = intdiv($m, 60);
        if ($h < 24) {
            return $h . ' h' . ($m % 60 ? ' ' . ($m % 60) . ' min' : '');
        }

        return intdiv($h, 24) . ' d' . ($h % 24 ? ' ' . ($h % 24) . ' h' : '');
    }

    // ---------- usage window quality ----------

    /** @return array{0: string, 1: string, 2: ?string} [css class, label, tooltip] */
    public static function quality(?string $q): array
    {
        return match ($q) {
            'complete' => ['ok', 'Complete', null],
            'gap' => ['warn', 'Data gaps', 'Some readings were missing during this day, so the volume may be slightly off.'],
            'mismatch' => ['bad', 'Needs review', 'The meter total and the measured flow rate disagree for this day. Please check the meter.'],
            'no_data' => ['info', 'No data', 'No readings were received during this day.'],
            null, '' => ['info', self::DASH, null],
            default => ['info', ucfirst(str_replace('_', ' ', $q)), null],
        };
    }

    // ---------- alerts ----------

    /** @return array{0: string, 1: string} */
    public static function alertStatus(Alert $a): array
    {
        if (!$a->closed_at) {
            return ['bad', 'Ongoing'];
        }

        return $a->seen_at ? ['ok', 'Seen'] : ['warn', 'Resolved'];
    }

    /** "since 21:17, 2 h 31 min" or "21:17 – 23:48 (2 h 31 min)" */
    public static function alertDuration(Alert $a): string
    {
        $end = $a->closed_at ?? now();
        $len = self::duration($end->timestamp - $a->opened_at->timestamp);
        if (!$a->closed_at) {
            return 'since ' . self::smart($a->opened_at) . ', ' . $len;
        }
        $sameDay = self::local($a->opened_at)->isSameDay(self::local($a->closed_at));

        return self::smart($a->opened_at) . ' – '
            . ($sameDay ? self::local($a->closed_at)->format('H:i') : self::short($a->closed_at))
            . " ({$len})";
    }

    /** Plain-English explanation of an alert, built from its stored detail. */
    public static function alertDetail(Alert $a): string
    {
        $d = is_array($a->detail) ? $a->detail : (json_decode((string) $a->detail, true) ?: []);
        $at = fn (string $iso) => self::smart(Carbon::parse($iso));
        $parts = [];

        if (array_key_exists('age_seconds', $d)) {
            $s = 'No data for ' . self::duration((int) $d['age_seconds']);
            if (!empty($d['last_reading_at'])) {
                $s .= ' (last reading ' . $at($d['last_reading_at']) . ')';
            }
            $parts[] = $s;
        }
        if (array_key_exists('quality', $d)) {
            $parts[] = match ($d['quality']) {
                'no_data' => 'No data in this window',
                'gap' => 'Readings missing'
                    . (isset($d['max_gap_seconds']) ? ' for up to ' . self::duration((int) $d['max_gap_seconds']) : ''),
                'mismatch' => 'Meter total and flow rate disagree',
                default => 'Data quality: ' . self::quality($d['quality'])[1],
            };
        }
        if (array_key_exists('neg_delta_m3', $d)) {
            $parts[] = 'Reverse flow of ' . self::volume($d['neg_delta_m3']);
        }
        if (array_key_exists('minutes', $d)) {
            $parts[] = 'No flow for ' . self::duration((int) $d['minutes'] * 60);
        }
        if (array_key_exists('signal_quality', $d)) {
            $parts[] = 'Signal quality ' . self::num($d['signal_quality'], 0);
        }
        if (array_key_exists('signal_up', $d) || array_key_exists('signal_down', $d)) {
            $parts[] = 'Upstream / downstream signal ' . self::num($d['signal_up'] ?? null, 1)
                . ' / ' . self::num($d['signal_down'] ?? null, 1);
        }
        if (array_key_exists('time_ratio', $d)) {
            $parts[] = 'Time ratio ' . self::num($d['time_ratio'], 1, '%');
        }
        if (array_key_exists('error_code', $d)) {
            $parts[] = 'Error code ' . ($d['error_code'] ?? self::DASH);
        }
        if (!empty($d['measured_at']) && $parts) {
            $parts[count($parts) - 1] .= ' (at ' . $at($d['measured_at']) . ')';
        }

        $known = ['age_seconds', 'last_reading_at', 'quality', 'max_gap_seconds', 'neg_delta_m3', 'minutes',
            'signal_quality', 'signal_up', 'signal_down', 'time_ratio', 'error_code', 'measured_at'];
        foreach (array_diff_key($d, array_flip($known)) as $k => $v) {
            $parts[] = ucfirst(str_replace('_', ' ', (string) $k)) . ': ' . (is_scalar($v) ? $v : json_encode($v));
        }

        $text = implode(' · ', $parts);
        if ($a->window_id && $a->relationLoaded('window') && $a->window) {
            $day = self::short($a->window->window_start_at) . ' – ' . self::short($a->window->window_end_at);
            $text = $text ? "Day {$day}: {$text}" : "Day {$day}";
        }

        return $text !== '' ? $text : self::DASH;
    }

    // ---------- incoming MQTT messages ----------

    /** Stored status values are fixed by the database; only the labels are translated. */
    public const MESSAGE_STATUSES = [
        'ok' => ['ok', 'Processed'],
        'diabaikan' => ['warn', 'Ignored'],
        'gagal' => ['bad', 'Failed'],
        'baru' => ['info', 'Waiting'],
    ];

    /** @return array{0: string, 1: string} */
    public static function messageStatus(?string $s): array
    {
        return self::MESSAGE_STATUSES[$s] ?? ['info', (string) $s];
    }

    /** Translate reasons stored by older versions (Indonesian) into plain English. */
    public static function messageReason(?string $e): ?string
    {
        if ($e === null || $e === '') {
            return null;
        }
        $map = [
            'Semua nilai nol: meter kemungkinan tidak merespons' => 'Meter not responding (all values zero)',
            'Isi pesan bukan JSON yang valid' => 'Message is not valid JSON',
            'Format waktu pembacaan tidak dikenali' => 'Reading time format not recognised',
            'Tidak ada nilai pembacaan yang dikenali' => 'No meter values found in the message',
            'Pembacaan dengan waktu yang sama sudah ada' => 'Duplicate: a reading with the same time already exists',
        ];
        if (isset($map[$e])) {
            return $map[$e];
        }
        if (preg_match('/^Nilai (\S+) bukan angka$/u', $e, $m)) {
            return "Value for {$m[1]} is not a number";
        }

        return $e;
    }
}
