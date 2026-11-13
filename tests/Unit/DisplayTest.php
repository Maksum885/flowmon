<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Support\Display;
use Carbon\Carbon;
use Tests\TestCase;

class DisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-12 14:20:00', 'UTC')); // 21:20 in Jakarta
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_numbers_use_thousand_separators_units_and_a_dash_for_empty(): void
    {
        $this->assertSame('12,345.679 m³', Display::volume(12345.6789));
        $this->assertSame('1,234.50', Display::num(1234.5, 2));
        $this->assertSame('98.0 %', Display::num(98, 1, '%'));
        $this->assertSame('–', Display::num(null, 2, 'm³'));
        $this->assertSame('–', Display::volume(''));
    }

    public function test_dates_are_english_and_in_display_timezone(): void
    {
        $d = Carbon::parse('2026-11-12 02:00:00', 'UTC');
        $this->assertSame('Thu, 12 Nov 2026 09:00', Display::dateTime($d));
        $this->assertSame('12 Nov 09:00', Display::short($d));
        $this->assertSame('09:00', Display::smart($d));
        $this->assertSame('11 Nov 09:00', Display::smart($d->copy()->subDay()));
    }

    public function test_durations_and_ages(): void
    {
        $this->assertSame('45 sec', Display::duration(45));
        $this->assertSame('5 min', Display::duration(329));
        $this->assertSame('2 h 31 min', Display::duration(9060));
        $this->assertSame('1 d 3 h', Display::duration(97200));
        $this->assertSame('2 min ago', Display::ago(150));
        $this->assertSame('just now', Display::ago(3));
    }

    public function test_quality_badges(): void
    {
        $this->assertSame(['ok', 'Complete', null], Display::quality('complete'));
        $this->assertSame('Data gaps', Display::quality('gap')[1]);
        $this->assertSame('Needs review', Display::quality('mismatch')[1]);
        $this->assertSame('No data', Display::quality('no_data')[1]);
        $this->assertNotNull(Display::quality('gap')[2]);
    }

    public function test_alert_detail_is_human_readable(): void
    {
        $offline = new Alert(['detail' => ['age_seconds' => 329, 'last_reading_at' => '2026-11-12T14:11:31+00:00']]);
        $this->assertSame('No data for 5 min (last reading 21:11)', Display::alertDetail($offline));

        $window = new Alert(['detail' => ['quality' => 'no_data', 'max_gap_seconds' => null]]);
        $this->assertSame('No data in this window', Display::alertDetail($window));

        $signal = new Alert(['detail' => ['signal_quality' => 42, 'measured_at' => '2026-11-12T14:00:00+00:00']]);
        $this->assertSame('Signal quality 42 (at 21:00)', Display::alertDetail($signal));
    }

    public function test_alert_status_and_duration(): void
    {
        $open = new Alert(['opened_at' => Carbon::parse('2026-11-12 11:49:00', 'UTC')]);
        $this->assertSame(['bad', 'Ongoing'], Display::alertStatus($open));
        $this->assertSame('since 18:49, 2 h 31 min', Display::alertDuration($open));

        $closed = new Alert(['opened_at' => Carbon::parse('2026-11-12 11:49:00', 'UTC'), 'closed_at' => Carbon::parse('2026-11-12 12:19:00', 'UTC')]);
        $this->assertSame(['warn', 'Resolved'], Display::alertStatus($closed));
        $this->assertSame('18:49 – 19:19 (30 min)', Display::alertDuration($closed));
    }

    public function test_message_status_and_old_indonesian_reasons_are_translated(): void
    {
        $this->assertSame('Processed', Display::messageStatus('ok')[1]);
        $this->assertSame('Ignored', Display::messageStatus('diabaikan')[1]);
        $this->assertSame('Failed', Display::messageStatus('gagal')[1]);
        $this->assertSame('Meter not responding (all values zero)', Display::messageReason('Semua nilai nol: meter kemungkinan tidak merespons'));
        $this->assertSame('Value for flow_rate is not a number', Display::messageReason('Nilai flow_rate bukan angka'));
        $this->assertNull(Display::messageReason(null));
    }
}
