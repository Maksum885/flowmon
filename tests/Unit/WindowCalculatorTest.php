<?php

namespace Tests\Unit;

use App\Services\WindowCalculator;
use PHPUnit\Framework\TestCase;

class WindowCalculatorTest extends TestCase
{
    private int $start = 1_700_000_000;
    private int $end = 1_700_086_400;

    private function make(callable $pos, ?callable $neg = null, float $flow = 120.0, ?int $from = null, ?array $skip = null): array
    {
        $from ??= $this->start - 3600;
        $rows = [];
        for ($t = $from; $t <= $this->end + 3600; $t += 10) {
            if ($skip && $t > $skip[0] && $t < $skip[1]) {
                continue;
            }
            $rows[] = ['t' => $t, 'pos' => $pos($t), 'neg' => $neg ? $neg($t) : 1.2, 'flow' => $flow];
        }

        return $rows;
    }

    private function linear(float $rate = 120.0): callable
    {
        return fn(int $t) => 1000 + ($t - $this->start) * $rate / 3600;
    }

    private function calc(array $rows): array
    {
        return (new WindowCalculator())->compute($rows, $this->start, $this->end);
    }

    public function test_aliran_normal_lengkap(): void
    {
        $r = $this->calc($this->make($this->linear()));
        $this->assertEqualsWithDelta(2880.0, $r['volume_m3'], 0.01);
        $this->assertSame('complete', $r['quality']);
        $this->assertSame([], $r['flags']);
        $this->assertEqualsWithDelta(2880.0, $r['integrated_m3'], 0.5);
    }

    public function test_aliran_balik_mengurangi_volume(): void
    {
        $neg = fn(int $t) => $t <= $this->start + 43200 ? 1.2 : 1.6;
        $r = $this->calc($this->make($this->linear(), $neg));
        $this->assertEqualsWithDelta(2879.6, $r['volume_m3'], 0.01);
        $this->assertTrue($r['flags']['backflow']);
    }

    public function test_lonjakan_sesaat_dibuang(): void
    {
        $lin = $this->linear();
        $pos = fn(int $t) => $lin($t) + ($t === $this->start + 3600 ? 1.0 : 0.0);
        $r = $this->calc($this->make($pos));
        $this->assertEqualsWithDelta(2880.0, $r['volume_m3'], 0.01);
        $this->assertGreaterThanOrEqual(1, $r['flags']['spike_removed']);
    }

    public function test_celah_data_menandai_kualitas_gap(): void
    {
        $r = $this->calc($this->make($this->linear(), null, 120.0, null, [$this->start + 40000, $this->start + 40600]));
        $this->assertSame('gap', $r['quality']);
        $this->assertEqualsWithDelta(2880.0, $r['volume_m3'], 0.01);
        $this->assertSame(600, $r['max_gap_seconds']);
    }

    public function test_tanpa_data_di_awal_jendela_adalah_no_data(): void
    {
        $r = $this->calc($this->make($this->linear(), null, 120.0, $this->start + 600));
        $this->assertSame('no_data', $r['quality']);
        $this->assertNull($r['volume_m3']);
    }

    public function test_reset_totalizer_tetap_dihitung(): void
    {
        $lin = $this->linear();
        $reset = $this->start + 43200;
        $pos = fn(int $t) => $t < $reset ? $lin($t) : ($t - $reset) * 120 / 3600;
        $r = $this->calc($this->make($pos));
        $this->assertTrue($r['flags']['reset']);
        $this->assertEqualsWithDelta(2880.0, $r['volume_m3'], 1.0);
        $this->assertSame('complete', $r['quality']);
    }

    public function test_penurunan_tak_wajar_tidak_menambah_volume(): void
    {
        $lin = $this->linear();
        $at = $this->start + 43200;
        $pos = fn(int $t) => $lin($t) - ($t >= $at ? 5.0 : 0.0);
        $r = $this->calc($this->make($pos));
        $this->assertTrue($r['flags']['unexpected_drop']);
        $this->assertEqualsWithDelta(2880.0, $r['volume_m3'], 1.0);
    }

    public function test_tidak_cocok_dengan_integrasi_aliran(): void
    {
        $r = $this->calc($this->make($this->linear(130.0)));
        $this->assertSame('mismatch', $r['quality']);
    }
}
