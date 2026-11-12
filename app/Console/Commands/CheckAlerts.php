<?php

namespace App\Console\Commands;

use App\Services\AlertChecker;
use Illuminate\Console\Command;

class CheckAlerts extends Command
{
    protected $signature = 'flowmon:check-alerts';
    protected $description = 'Periksa aturan alert terhadap data terbaru';

    public function handle(AlertChecker $checker): int
    {
        $checker->run();
        $this->info("Alert dibuka: {$checker->opened}, ditutup: {$checker->closed}");

        return self::SUCCESS;
    }
}
