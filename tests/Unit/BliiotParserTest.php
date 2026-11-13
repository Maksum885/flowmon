<?php

namespace Tests\Unit;

use App\Services\MessageParser;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BliiotParserTest extends TestCase
{
    private function pesan(array $nilai, string $waktu = '1794500845'): string
    {
        $items = [];
        foreach ($nilai as $flag => $v) {
            $items[] = ['value' => (string) $v, 'flag' => $flag, 'status' => '1'];
        }

        return json_encode(['sensorDatas' => $items, 'time' => $waktu]);
    }

    public function test_pesan_r40a_dipetakan_dengan_benar(): void
    {
        $r = (new MessageParser)->parse($this->pesan([
            'REG21000' => '142.6',
            'REG21004' => '321442',
            'REG20001' => '855',
            'REG20002' => '0',
        ]), [], Carbon::now());

        $this->assertSame(142.6, $r['flow_rate']);
        $this->assertSame(321442, $r['pos_int']);
        $this->assertSame(3, $r['working_step']);
        $this->assertSame(87, $r['signal_quality']);
        $this->assertSame(1794500845, $r['measured_at']->timestamp);
    }

    public function test_semua_nol_diabaikan_dengan_alasan(): void
    {
        $p = new MessageParser;
        $this->assertNull($p->parse($this->pesan(['REG21000' => '0', 'REG20001' => '0']), [], Carbon::now()));
        $this->assertNotNull($p->ignoreReason);
    }

    public function test_pesan_io_diabaikan_tanpa_alasan(): void
    {
        $p = new MessageParser;
        $json = '{"sensorDatas":[{"switcher":"0","flag":"DI1"},{"value":"0","flag":"COUNT1"}],"time":"1"}';
        $this->assertNull($p->parse($json, [], Carbon::now()));
        $this->assertNull($p->ignoreReason);
    }

    public function test_pembagi_pada_pemetaan(): void
    {
        $r = (new MessageParser)->parse($this->pesan(['REG20003' => '708', 'REG21000' => '1']), ['signal_up' => 'REG20003/10'], Carbon::now());
        $this->assertEqualsWithDelta(70.8, $r['signal_up'], 1e-9);
    }

    public function test_titik_berstatus_nol_dibuang(): void
    {
        $json = json_encode(['sensorDatas' => [
            ['value' => '999', 'flag' => 'REG21000', 'status' => '0'],
            ['value' => '0.7', 'flag' => 'REG21002', 'status' => '1'],
        ], 'time' => '1794500845']);
        $r = (new MessageParser)->parse($json, [], Carbon::now());
        $this->assertNull($r['flow_rate']);
        $this->assertSame(0.7, $r['velocity']);
    }

    public function test_json_biasa_tetap_didukung(): void
    {
        $r = (new MessageParser)->parse('{"flow_rate":142.6,"pos_int":10}', [], Carbon::now());
        $this->assertSame(142.6, $r['flow_rate']);
    }

    public function test_json_rusak_melempar_galat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new MessageParser)->parse('bukan json', [], Carbon::now());
    }
}
