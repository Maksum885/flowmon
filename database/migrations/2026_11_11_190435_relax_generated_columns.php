<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE readings ALTER COLUMN pos_total DROP NOT NULL');
        DB::statement('ALTER TABLE readings ALTER COLUMN neg_total DROP NOT NULL');
    }

    public function down(): void {}
};
