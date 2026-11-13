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

    /** Nama titik bawaan dari R40A (kolom => nama titik di pesan). */
    public const BLIIOT_DEFAULT = [
        'flow_rate' => 'REG21000',
        'velocity' => 'REG21002',
        'pos_int' => 'REG21004',
        'pos_frac' => 'REG21006',
        'neg_int' => 'REG21008',
        'neg_frac' => 'REG21010',
        'time_ratio' => 'REG21012',
        'step_q' => 'REG20001',
        'error_code' => 'REG20002',
        'signal_up' => 'REG20003',
        'signal_down' => 'REG20004',
    ];

    /** Alasan pesan terakhir diabaikan (diisi saat parse() mengembalikan null). */
    public ?string $ignoreReason = null;

    private int $failedPoints = 0;

    /**
     * @param array<string,string> $mapping nama kolom => jalur di JSON (boleh bertitik, mis. "data.flow").
     *        Untuk pesan R40A, nilainya nama titik (mis. "REG21000"). Akhiran "/10" membagi nilai dengan 10.
     * @return array|null null bila pesan valid tetapi tidak berisi nilai yang dikenali
     */
    public function parse(string $payload, array $mapping, Carbon $receivedAt): ?array
    {
        $this->ignoreReason = null;
        $this->failedPoints = 0;

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Isi pesan bukan JSON yang valid');
        }

        // Format R40A: {"sensorDatas":[{"flag":"REG21000","value":"1.5","status":"0"}, ...],"time":"..."}
        $bliiot = isset($data['sensorDatas']) && is_array($data['sensorDatas']);
        if ($bliiot) {
            $data = $this->flatten($data);
        }

        $attrs = [];
        $found = false;
        foreach (array_merge(self::INTS, self::FLOATS) as $col) {
            [$path, $div] = $this->target($mapping[$col] ?? ($bliiot ? (self::BLIIOT_DEFAULT[$col] ?? $col) : $col));
            $v = Arr::get($data, $path);
            if ($v === null || $v === '') {
                $attrs[$col] = null;
                continue;
            }
            if (!is_numeric($v)) {
                throw new InvalidArgumentException("Nilai {$col} bukan angka");
            }
            $v = (float) $v;
            if ($div !== 1.0) {
                $v /= $div;
            }
            $attrs[$col] = in_array($col, self::INTS, true) ? (int) round($v) : $v;
            $found = true;
        }

        // Register 0092: byte atas = langkah kerja, byte bawah = kualitas sinyal
        [$path] = $this->target($mapping['step_q'] ?? ($bliiot ? self::BLIIOT_DEFAULT['step_q'] : 'step_q'));
        $raw = Arr::get($data, $path);
        if (is_numeric($raw) && $attrs['working_step'] === null && $attrs['signal_quality'] === null) {
            $n = (int) round((float) $raw);
            $attrs['working_step'] = ($n >> 8) & 0xFF;
            $attrs['signal_quality'] = $n & 0xFF;
            $found = true;
        }

        if (!$found) {
            if ($bliiot && $this->failedPoints > 0) {
                $this->ignoreReason = 'Pembacaan gagal: meter tidak merespons';
            }

            return null;
        }

        // R40A tetap mengirim nol bila meter tidak menjawab; semua nol berarti bukan pembacaan nyata
        if ($bliiot && collect($attrs)->filter(fn($x) => $x !== null)->every(fn($x) => $x == 0)) {
            $this->ignoreReason = 'Semua nilai nol: meter kemungkinan tidak merespons';

            return null;
        }

        $rawTs = Arr::get($data, $mapping['measured_at'] ?? 'measured_at');
        $attrs['measured_at'] = $this->timestamp($rawTs) ?? $receivedAt->copy()->utc()->startOfSecond();

        return $attrs;
    }

    /** Ubah daftar sensorDatas menjadi peta nama titik => nilai. Titik berstatus "0" (pembacaan gagal) dibuang. */
    private function flatten(array $data): array
    {
        $flat = [];
        foreach ($data['sensorDatas'] as $item) {
            if (!is_array($item) || !isset($item['flag']) || !array_key_exists('value', $item)) {
                continue;
            }
            if (isset($item['status']) && (string) $item['status'] === '0') {
                $this->failedPoints++;

                continue;
            }
            $flat[(string) $item['flag']] = $item['value'];
        }
        if (isset($data['time'])) {
            $flat['measured_at'] = $data['time'];
        }

        return $flat;
    }

    /** Pisahkan "REG20003/10" menjadi [jalur, pembagi]. */
    private function target(string $spec): array
    {
        if (preg_match('/^(.+)\/(\d+(?:\.\d+)?)$/', $spec, $m) && (float) $m[2] > 0) {
            return [$m[1], (float) $m[2]];
        }

        return [$spec, 1.0];
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
