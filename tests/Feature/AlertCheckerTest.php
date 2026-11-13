<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Services\AlertChecker;
use App\Support\Display;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The production migrations use PostgreSQL-only SQL, so this test builds the
 * few tables AlertChecker needs directly in the in-memory SQLite database.
 */
class AlertCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-12 14:20:00', 'UTC'));

        Schema::create('alert_rules', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->boolean('enabled')->default(true);
            $t->json('params')->nullable();
            $t->smallInteger('consecutive_count')->default(1);
        });
        Schema::create('usage_windows', function (Blueprint $t) {
            $t->id();
            $t->timestamp('window_start_at')->unique();
            $t->timestamp('window_end_at');
            $t->decimal('volume_m3', 12, 3)->nullable();
            $t->decimal('neg_delta_m3', 12, 3)->nullable();
            $t->string('quality', 16);
            $t->unsignedInteger('max_gap_seconds')->nullable();
        });
        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rule_id');
            $t->foreignId('window_id')->nullable();
            $t->timestamp('opened_at');
            $t->timestamp('closed_at')->nullable();
            $t->timestamp('seen_at')->nullable();
            $t->json('detail')->nullable();
        });
        Schema::create('readings', function (Blueprint $t) {
            $t->id();
            $t->timestamp('measured_at')->unique();
            $t->decimal('flow_rate', 10, 3)->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rule(string $code, array $params = []): int
    {
        return DB::table('alert_rules')->insertGetId([
            'code' => $code, 'name' => $code, 'enabled' => true, 'params' => json_encode((object) $params),
        ]);
    }

    private function window(string $startUtc, string $quality, ?int $gap = null): int
    {
        $start = Carbon::parse($startUtc, 'UTC');

        return DB::table('usage_windows')->insertGetId([
            'window_start_at' => $start->format('Y-m-d H:i:s'),
            'window_end_at' => $start->copy()->addDay()->format('Y-m-d H:i:s'),
            'quality' => $quality,
            'max_gap_seconds' => $gap,
        ]);
    }

    private function check(): AlertChecker
    {
        $c = new AlertChecker();
        $c->run();

        return $c;
    }

    public function test_incomplete_window_opens_one_alert_per_window_over_repeated_checks(): void
    {
        $this->rule('incomplete_window');
        $this->window('2026-11-11 02:00:00', 'no_data');

        $this->assertSame(1, $this->check()->opened);
        $this->assertSame(0, $this->check()->opened);
        $this->assertSame(0, $this->check()->opened);

        $this->assertSame(1, DB::table('alerts')->count());
    }

    public function test_each_incomplete_window_gets_its_own_alert_and_shows_which_day(): void
    {
        $this->rule('incomplete_window');
        $this->window('2026-11-10 02:00:00', 'no_data');
        $this->window('2026-11-11 02:00:00', 'gap', 25480);

        $this->check();
        $this->check();

        $alerts = Alert::with('window')->orderBy('window_id')->get();
        $this->assertCount(2, $alerts);
        $this->assertSame('Day 10 Nov 09:00 – 11 Nov 09:00: No data in this window', Display::alertDetail($alerts[0]));
        $this->assertSame('Day 11 Nov 09:00 – 12 Nov 09:00: Readings missing for up to 7 h 4 min', Display::alertDetail($alerts[1]));
    }

    public function test_incomplete_window_alert_closes_when_the_day_becomes_complete(): void
    {
        $this->rule('incomplete_window');
        $w = $this->window('2026-11-11 02:00:00', 'gap', 4000);
        $this->check();

        DB::table('usage_windows')->where('id', $w)->update(['quality' => 'complete']);
        $this->assertSame(1, $this->check()->closed);
        $this->assertSame(0, DB::table('alerts')->whereNull('closed_at')->count());
    }

    public function test_offline_alert_is_opened_once_with_readable_detail(): void
    {
        $this->rule('offline', ['menit' => 5]);
        DB::table('readings')->insert(['measured_at' => '2026-11-12 14:11:31', 'flow_rate' => 1]);

        $this->check();
        $this->check();

        $alerts = Alert::all();
        $this->assertCount(1, $alerts);
        $this->assertSame('No data for 8 min (last reading 21:11)', Display::alertDetail($alerts[0]));
    }
}
