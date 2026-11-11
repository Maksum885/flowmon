<?php

namespace App\Services;

class WindowCalculator
{
    public const TOL = 0.5;           // toleransi derau (m3)
    public const CAP_PER_SEC = 0.05;  // kenaikan wajar (m3 per detik) di luar toleransi
    public const SPIKE_RUN = 3;       // lonjakan yang kembali dalam <= 3 pembacaan dibuang
    public const MAX_GAP = 300;       // jeda antar pembacaan yang masih wajar (detik)
    public const RESET_RATIO = 0.5;   // reset hanya bila total jatuh ke bawah 50%
    public const BACKFLOW_M3 = 0.1;   // NEG naik lebih dari ini = aliran balik
    public const CROSS_PCT = 2.0;     // toleransi cek silang dengan integrasi laju alir
    public const FLOW_COVER = 0.99;   // cek silang hanya bila laju alir tercakup >= 99%

    /**
     * @param array<int, array{t:int, pos:?float, neg:?float, flow:?float}> $readings urut naik menurut waktu
     */
    public function compute(array $readings, int $start, int $end): array
    {
        $flags = [];
        $negFlags = [];
        $removed = 0;
        $pos = $this->despike($this->series($readings, 'pos'), $removed);
        $neg = $this->despike($this->series($readings, 'neg'), $removed);

        $count = 0;
        foreach ($readings as $r) {
            if ($r['t'] >= $start && $r['t'] <= $end) {
                $count++;
            }
        }
        [$gap, $gapStart] = $this->maxGap(array_column($readings, 't'), $start, $end);

        $result = [
            'volume_m3' => null,
            'pos_delta_m3' => null,
            'neg_delta_m3' => null,
            'integrated_m3' => null,
            'quality' => 'no_data',
            'flags' => [],
            'reading_count' => $count,
            'max_gap_seconds' => $gap,
            'max_gap_start_at' => $gapStart,
        ];

        $p = $this->delta($pos, $start, $end, $flags);
        if ($p === null) {
            $result['flags'] = $flags;

            return $result;
        }
        $posDelta = $p[0];

        $n = $this->delta($neg, $start, $end, $negFlags);
        $negDelta = $n === null ? 0.0 : $n[0];
        if ($n === null) {
            $flags['neg_missing'] = true;
        }
        if (isset($negFlags['reset'])) {
            $flags['neg_reset'] = true;
        }
        if (isset($negFlags['unexpected_drop'])) {
            $flags['neg_unexpected_drop'] = true;
        }
        if ($negDelta > self::BACKFLOW_M3) {
            $flags['backflow'] = true;
        }

        $volume = $posDelta - $negDelta;

        [$integ, $covered] = $this->integrateFlow($readings, $start, $end);
        $window = $end - $start;
        $integrated = null;
        $mismatch = false;
        if ($window > 0 && $covered >= self::FLOW_COVER * $window) {
            $integrated = $integ;
            if ($volume > 0 && abs($volume - $integ) / $volume * 100 > self::CROSS_PCT) {
                $mismatch = true;
            }
        }

        if ($removed > 0) {
            $flags['spike_removed'] = $removed;
        }

        // Urutan: no_data > mismatch > gap > complete
        $quality = 'complete';
        if (($gap !== null && $gap > self::MAX_GAP) || isset($flags['gap_at_boundary'])) {
            $quality = 'gap';
        }
        if ($mismatch) {
            $quality = 'mismatch';
        }

        $result['volume_m3'] = round($volume, 3);
        $result['pos_delta_m3'] = round($posDelta, 3);
        $result['neg_delta_m3'] = round($negDelta, 3);
        $result['integrated_m3'] = $integrated === null ? null : round($integrated, 3);
        $result['quality'] = $quality;
        $result['flags'] = $flags;

        return $result;
    }

    /** Deret [waktu, nilai] untuk satu kolom, tanpa nilai kosong. */
    private function series(array $readings, string $key): array
    {
        $s = [];
        foreach ($readings as $r) {
            if ($r[$key] !== null) {
                $s[] = [$r['t'], (float) $r[$key]];
            }
        }

        return $s;
    }

    private function plausible(float $prev, float $v, int $dt): bool
    {
        return $v >= $prev - self::TOL && $v <= $prev + self::TOL + self::CAP_PER_SEC * $dt;
    }

    /** Buang lonjakan atau cekungan sesaat (maksimal 3 pembacaan berturut-turut) yang langsung kembali wajar. */
    private function despike(array $s, int &$removed): array
    {
        $n = count($s);
        if ($n < 3) {
            return $s;
        }
        $out = [$s[0]];
        $i = 1;
        while ($i < $n) {
            [$pt, $pv] = $out[count($out) - 1];
            [$t, $v] = $s[$i];
            if ($this->plausible($pv, $v, max($t - $pt, 0))) {
                $out[] = $s[$i];
                $i++;
                continue;
            }
            $back = null;
            for ($run = 1; $run <= self::SPIKE_RUN; $run++) {
                $k = $i + $run;
                if ($k >= $n) {
                    break;
                }
                if ($this->plausible($pv, $s[$k][1], $s[$k][0] - $pt)) {
                    $back = $run;
                    break;
                }
            }
            if ($back !== null) {
                $removed += $back;
                $i += $back;
                continue;
            }
            $out[] = $s[$i]; // perubahan menetap, disimpan
            $i++;
        }

        return $out;
    }

    /** Nilai pada waktu $t lewat interpolasi linear: [nilai, panjang jeda, ada penurunan di batas]. */
    private function valueAt(array $s, int $t): array
    {
        $lo = 0;
        $hi = count($s);
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($s[$mid][0] <= $t) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }
        $i = $lo; // indeks pertama yang waktunya lebih besar dari $t
        if ($i === 0) {
            return [null, null, false];
        }
        [$pt, $pv] = $s[$i - 1];
        if ($pt === $t) {
            return [$pv, 0, false];
        }
        if ($i === count($s)) {
            return [null, null, false];
        }
        [$nt, $nv] = $s[$i];
        $span = $nt - $pt;
        if ($nv < $pv - self::TOL) {
            return [$pv, $span, true];
        }

        return [$pv + ($nv - $pv) * (($t - $pt) / $span), $span, false];
    }

    /** Selisih total antara $start dan $end: [selisih, nilai awal, nilai akhir] atau null bila data tidak cukup. */
    private function delta(array $s, int $start, int $end, array &$flags): ?array
    {
        if (count($s) < 2) {
            return null;
        }
        [$v0, $g0, $d0] = $this->valueAt($s, $start);
        [$v1, $g1, $d1] = $this->valueAt($s, $end);
        if ($v0 === null || $v1 === null) {
            return null;
        }
        if (($g0 ?? 0) > self::MAX_GAP || ($g1 ?? 0) > self::MAX_GAP) {
            $flags['gap_at_boundary'] = true;
        }
        if ($d0 || $d1) {
            $flags['drop_at_boundary'] = true;
        }

        $pts = [[$start, $v0]];
        foreach ($s as [$t, $v]) {
            if ($t > $start && $t < $end) {
                $pts[] = [$t, $v];
            }
        }
        $pts[] = [$end, $v1];

        $total = 0.0;
        for ($i = 1, $n = count($pts); $i < $n; $i++) {
            $a = $pts[$i - 1][1];
            $b = $pts[$i][1];
            $d = $b - $a;
            if ($d >= -self::TOL) {
                $total += max($d, 0.0);
            } elseif ($b < self::RESET_RATIO * $a) {
                $flags['reset'] = true;   // total di-reset: mulai lagi dari 0
                $total += $b;
            } else {
                $flags['unexpected_drop'] = true; // turun tak wajar: tidak menambah volume
            }
        }

        return [$total, $v0, $v1];
    }

    /** Jeda terpanjang antar pembacaan yang menyentuh jendela: [detik, waktu awal jeda]. */
    private function maxGap(array $times, int $start, int $end): array
    {
        $best = null;
        $bestStart = null;
        for ($i = 1, $n = count($times); $i < $n; $i++) {
            $a = $times[$i - 1];
            $b = $times[$i];
            if ($b <= $start || $a >= $end) {
                continue;
            }
            if ($best === null || $b - $a > $best) {
                $best = $b - $a;
                $bestStart = max($a, $start);
            }
        }

        return [$best, $bestStart];
    }

    /** Integrasi laju alir (m3/jam) dengan trapesium: [m3, detik tercakup]. Jeda panjang tidak diintegrasikan. */
    private function integrateFlow(array $readings, int $start, int $end): array
    {
        $pts = [];
        foreach ($readings as $r) {
            if ($r['flow'] !== null) {
                $pts[] = [$r['t'], (float) $r['flow']];
            }
        }
        $total = 0.0;
        $covered = 0;
        for ($i = 1, $n = count($pts); $i < $n; $i++) {
            [$ta, $fa] = $pts[$i - 1];
            [$tb, $fb] = $pts[$i];
            if ($tb <= $start || $ta >= $end) {
                continue;
            }
            $dt = $tb - $ta;
            if ($dt <= 0 || $dt > self::MAX_GAP) {
                continue;
            }
            $ca = max($ta, $start);
            $cb = min($tb, $end);
            $fca = $fa + ($fb - $fa) * (($ca - $ta) / $dt);
            $fcb = $fa + ($fb - $fa) * (($cb - $ta) / $dt);
            $seg = $cb - $ca;
            $total += ($fca + $fcb) / 2 * $seg / 3600;
            $covered += $seg;
        }

        return [$total, $covered];
    }
}
