<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // users: login pakai username, tanpa email (Tahap 4)
        Schema::dropIfExists('password_reset_tokens');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('email_verified_at');
            $t->dropColumn('email');
            $t->renameColumn('name', 'username');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->unique('username');
        });

        Schema::create('mqtt_messages', function (Blueprint $t) {
            $t->id();
            $t->timestampTz('received_at');
            $t->string('topic');
            $t->text('payload');
            $t->string('status', 16)->default('baru');
            $t->text('error')->nullable();
            $t->timestampTz('processed_at')->nullable();
            $t->index('received_at');
        });
        DB::statement("ALTER TABLE mqtt_messages ADD CONSTRAINT mqtt_messages_status_check CHECK (status IN ('baru','ok','gagal','diabaikan'))");
        DB::statement("CREATE INDEX mqtt_messages_baru_idx ON mqtt_messages (received_at) WHERE status = 'baru'");

        Schema::create('readings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->nullable()->constrained('mqtt_messages')->nullOnDelete();
            $t->timestampTz('measured_at')->unique();
            $t->decimal('flow_rate', 10, 3)->nullable();
            $t->decimal('velocity', 8, 3)->nullable();
            $t->decimal('sound_speed', 8, 2)->nullable();
            $t->bigInteger('pos_int')->nullable();
            $t->decimal('pos_frac', 9, 6)->nullable();
            $t->decimal('pos_total', 15, 6)->storedAs('pos_int + pos_frac');
            $t->bigInteger('neg_int')->nullable();
            $t->decimal('neg_frac', 9, 6)->nullable();
            $t->decimal('neg_total', 15, 6)->storedAs('neg_int + neg_frac');
            $t->smallInteger('error_code')->nullable();
            $t->smallInteger('working_step')->nullable();
            $t->smallInteger('signal_quality')->nullable();
            $t->decimal('signal_up', 5, 2)->nullable();
            $t->decimal('signal_down', 5, 2)->nullable();
            $t->decimal('time_ratio', 6, 2)->nullable();
            $t->decimal('net_acc', 15, 3)->nullable();
            $t->decimal('net_today', 12, 3)->nullable();
            $t->index('message_id');
        });

        Schema::create('usage_windows', function (Blueprint $t) {
            $t->id();
            $t->timestampTz('window_start_at')->unique();
            $t->timestampTz('window_end_at');
            $t->decimal('volume_m3', 12, 3)->nullable();
            $t->decimal('pos_delta_m3', 12, 3)->nullable();
            $t->decimal('neg_delta_m3', 12, 3)->nullable();
            $t->decimal('integrated_m3', 12, 3)->nullable();
            $t->string('quality', 16);
            $t->jsonb('flags')->default(DB::raw("'{}'::jsonb"));
            $t->unsignedInteger('reading_count')->default(0);
            $t->unsignedInteger('max_gap_seconds')->nullable();
            $t->timestampTz('max_gap_start_at')->nullable();
            $t->smallInteger('calc_version');
            $t->timestampTz('calculated_at');
        });
        DB::statement("ALTER TABLE usage_windows ADD CONSTRAINT usage_windows_quality_check CHECK (quality IN ('complete','gap','mismatch','no_data'))");
        DB::statement("ALTER TABLE usage_windows ADD CONSTRAINT usage_windows_range_check CHECK (window_end_at > window_start_at)");

        Schema::create('alert_rules', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 100);
            $t->boolean('enabled')->default(true);
            $t->jsonb('params')->default(DB::raw("'{}'::jsonb"));
            $t->smallInteger('consecutive_count')->default(1);
        });
        DB::statement("ALTER TABLE alert_rules ADD CONSTRAINT alert_rules_consecutive_check CHECK (consecutive_count >= 1)");

        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rule_id')->constrained('alert_rules')->restrictOnDelete();
            $t->foreignId('window_id')->nullable()->constrained('usage_windows')->nullOnDelete();
            $t->timestampTz('opened_at');
            $t->timestampTz('closed_at')->nullable();
            $t->timestampTz('seen_at')->nullable();
            $t->jsonb('detail')->default(DB::raw("'{}'::jsonb"));
        });
        DB::statement("CREATE UNIQUE INDEX alerts_open_unique ON alerts (rule_id, COALESCE(window_id, 0)) WHERE closed_at IS NULL");
        DB::statement("CREATE INDEX alerts_unseen_idx ON alerts (seen_at) WHERE seen_at IS NULL");

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 64)->primary();
            $t->jsonb('value');
            $t->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('usage_windows');
        Schema::dropIfExists('readings');
        Schema::dropIfExists('mqtt_messages');
    }
};
