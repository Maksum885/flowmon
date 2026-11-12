<?php

namespace App\Console\Commands;

use App\Services\MessageReceiver;
use Illuminate\Console\Command;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class ListenMqtt extends Command
{
    protected $signature = 'flowmon:listen';

    protected $description = 'Menerima pesan MQTT dari R40A dan menyimpannya (UC-11)';

    public function handle(MessageReceiver $receiver): int
    {
        $cfg = config('flowmon.mqtt');
        $delay = 1;

        while (true) {
            $client = null;
            try {
                $client = new MqttClient($cfg['host'], $cfg['port'], $cfg['client_id']);
                $settings = (new ConnectionSettings())
                    ->setUsername($cfg['username'])
                    ->setPassword($cfg['password'])
                    ->setKeepAliveInterval(30)
                    ->setConnectTimeout(10);
                $client->connect($settings, false);
                $this->info('Tersambung ke broker, mendengarkan topik ' . $cfg['topic']);
                $delay = 1;

                $client->subscribe($cfg['topic'], function (string $topic, string $message) use ($receiver) {
                    $receiver->handle($topic, $message);
                }, 1);

                $lastReplay = 0;
                $client->registerLoopEventHandler(function (MqttClient $c, float $elapsed) use ($receiver, &$lastReplay) {
                    if (time() - $lastReplay >= 60) {
                        $lastReplay = time();
                        $n = $receiver->replaySpool();
                        if ($n > 0) {
                            $this->info("Memproses ulang {$n} pesan dari file cadangan.");
                        }
                    }
                });

                $client->loop(true);
            } catch (\Throwable $e) {
                $this->error('Gangguan: ' . $e->getMessage() . " (coba lagi dalam {$delay} detik)");
            }

            try {
                $client?->disconnect();
            } catch (\Throwable) {
            }
            sleep($delay);
            $delay = min($delay * 2, 60);
        }
    }
}
