<?php

namespace App\Console\Commands;

use App\Services\WindowService;
use Illuminate\Console\Command;

class RecalcWindows extends Command
{
    protected $signature = 'flowmon:recalc {days=3}';

    protected $description = 'Hitung ulang jendela volume untuk N jendela terakhir yang sudah selesai';

    public function handle(WindowService $service): int
    {
        $n = $service->recalcRecent((int) $this->argument('days'));
        $this->info("Selesai: {$n} jendela dihitung.");

        return self::SUCCESS;
    }
}
