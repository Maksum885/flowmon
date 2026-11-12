<?php

namespace Tests\Unit;

use App\Services\MessageParser;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MessageParserTest extends TestCase
{
    private function parse(string $payload, array $mapping = []): ?array
    {
        return (new MessageParser())->parse($payload, $mapping, Carbon::parse('2026-11-12 03:00:00', 'UTC'));
    }

    public function test_pemetaan_kosong_membaca_nama_kolom_langsung(): void
    {
        $a = $this->parse('{"measured_at":1700000000,"flow_rate":142.6,"pos_int":321442,"pos_frac":0.31}');
        $this->assertSame(1700000000, $a['measured_at']->getTimestamp());
        $this->assertSame(321442, $a['pos_int']);
        $this->assertSame(142.6, $a['flow_rate']);
        $this->assertNull($a['neg_int']);
    }

    public function test_pemetaan_dengan_jalur_bertitik_dan_waktu_iso(): void
    {
        $a = $this->parse(
            '{"ts":"2026-11-12T10:00:00+07:00","d":{"flow":150.5,"p":100}}',
            ['measured_at' => 'ts', 'flow_rate' => 'd.flow', 'pos_int' => 'd.p']
        );
        $this->assertSame('2026-11-12 03:00:00', $a['measured_at']->format('Y-m-d H:i:s'));
        $this->assertSame(150.5, $a['flow_rate']);
        $this->assertSame(100, $a['pos_int']);
    }

    public function test_waktu_milidetik_dikenali(): void
    {
        $a = $this->parse('{"measured_at":1700000000000,"pos_int":1}');
        $this->assertSame(1700000000, $a['measured_at']->getTimestamp());
    }

    public function test_tanpa_waktu_memakai_waktu_terima(): void
    {
        $a = $this->parse('{"pos_int":1}');
        $this->assertSame('2026-11-12 03:00:00', $a['measured_at']->format('Y-m-d H:i:s'));
    }

    public function test_json_rusak_menimbulkan_kesalahan(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parse('bukan json');
    }

    public function test_nilai_bukan_angka_menimbulkan_kesalahan(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parse('{"pos_int":"abc"}');
    }

    public function test_pesan_tanpa_nilai_dikenali_menghasilkan_null(): void
    {
        $this->assertNull($this->parse('{"hello":1}'));
    }
}
