<?php

namespace App\Console\Commands;

use App\Models\Reading;
use Illuminate\Console\Command;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class PublishTest extends Command
{
    protected $signature = 'flowmon:publish-test {count=1}';

    protected $description = 'Mengirim pesan contoh seolah-olah dari R40A (hanya untuk uji lokal)';

    public function handle(): int
    {
        $cfg = config('flowmon.mqtt');
        $last = Reading::orderByDesc('measured_at')->first();
        $total = $last ? (float) $last->pos_total : 321440.0;
        $count = max(1, (int) $this->argument('count'));

        $client = new MqttClient($cfg['host'], $cfg['port'], 'flowmon-test-publisher');
        $settings = (new ConnectionSettings())
            ->setUsername($cfg['test_username'])
            ->setPassword($cfg['test_password']);
        $client->connect($settings, true);

        for ($i = 0; $i < $count; $i++) {
            $total += 0.04;
            $int = (int) floor($total);
            $payload = json_encode([
                'measured_at' => time(),
                'flow_rate' => 142.6,
                'velocity' => 0.718,
                'pos_int' => $int,
                'pos_frac' => round($total - $int, 6),
                'neg_int' => 1,
                'neg_frac' => 0.2,
                'signal_quality' => 87,
                'signal_up' => 70.8,
                'signal_down' => 68.5,
                'time_ratio' => 100.3,
            ]);
            $client->publish('r40a/flowmeter', $payload, 0);
            $this->info("Terkirim: {$payload}");
            if ($i < $count - 1) {
                sleep(1);
            }
        }
        $client->disconnect();

        return self::SUCCESS;
    }
}
