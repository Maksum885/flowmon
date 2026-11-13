<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Keep at most one open alert per rule and usage window (matched by window
 * start). The oldest one is kept, later duplicates are deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $open = DB::table('alerts')
            ->leftJoin('usage_windows as w', 'w.id', '=', 'alerts.window_id')
            ->whereNull('alerts.closed_at')
            ->orderBy('alerts.opened_at')
            ->orderBy('alerts.id')
            ->get(['alerts.id', 'alerts.rule_id', 'alerts.window_id', 'w.window_start_at']);

        $kept = [];
        $duplicates = [];
        foreach ($open as $a) {
            $subject = $a->window_id === null ? '-' : ($a->window_start_at ?? 'w' . $a->window_id);
            $key = $a->rule_id . '|' . $subject;
            if (isset($kept[$key])) {
                $duplicates[] = $a->id;
            } else {
                $kept[$key] = true;
            }
        }

        foreach (array_chunk($duplicates, 500) as $ids) {
            DB::table('alerts')->whereIn('id', $ids)->delete();
        }
    }

    public function down(): void
    {
        // Deleted duplicates cannot be restored.
    }
};
