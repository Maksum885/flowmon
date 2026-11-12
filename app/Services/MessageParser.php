<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class MessageParser
{
    public const INTS = ['pos_int', 'neg_int', 'error_code', 'working_step', 'signal_quality'];

    public const FLOATS = [
        'flow_rate',
        'velocity',
        'sound_speed',
        'pos_frac',
        'neg_frac',
        'signal_up',
        'signal_down',
        'time_ratio',
        'net_acc',
        'net_today',
    ];

    /**
     * @param array<string,string> $mapping nama kolom => jalur di JSON (boleh bertitik, mis. "data.flow")
     * @return array|null null bila pesan valid tetapi tidak berisi nilai yang dikenali
     */
    public function parse(string $payload, array $mapping, Carbon $receivedAt): ?array
    {
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Isi pesan bukan JSON yang valid');
        }

        $attrs = [];
        $found = false;
        foreach (array_merge(self::INTS, self::FLOATS) as $col) {
            $v = Arr::get($data, $mapping[$col] ?? $col);
            if ($v === null || $v === '') {
                $attrs[$col] = null;
                continue;
            }
            if (!is_numeric($v)) {
                throw new InvalidArgumentException("Nilai {$col} bukan angka");
            }
            $attrs[$col] = in_array($col, self::INTS, true) ? (int) round((float) $v) : (float) $v;
            $found = true;
        }
        if (!$found) {
            return null;
        }

        $rawTs = Arr::get($data, $mapping['measured_at'] ?? 'measured_at');
        $attrs['measured_at'] = $this->timestamp($rawTs) ?? $receivedAt->copy()->utc()->startOfSecond();

        return $attrs;
    }

    private function timestamp(mixed $v): ?Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            $n = (float) $v;
            if ($n > 1e11) {
                $n /= 1000; // milidetik
            }

            return Carbon::createFromTimestampUTC((int) round($n));
        }
        if (is_string($v)) {
            try {
                return Carbon::parse($v, 'UTC')->utc()->startOfSecond();
            } catch (\Throwable) {
                throw new InvalidArgumentException('Format waktu pembacaan tidak dikenali');
            }
        }
        throw new InvalidArgumentException('Format waktu pembacaan tidak dikenali');
    }
}
