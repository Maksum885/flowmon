<?php

namespace App\Console\Commands;

use App\Models\MqttMessage;
use App\Services\MessageReceiver;
use Illuminate\Console\Command;

class ReprocessMessages extends Command
{
    protected $signature = 'flowmon:reprocess {--status=diabaikan,gagal : Status yang diproses ulang, pisahkan dengan koma}';

    protected $description = 'Proses ulang pesan MQTT yang sebelumnya diabaikan atau gagal';

    public function handle(MessageReceiver $receiver): int
    {
        $statuses = array_filter(explode(',', (string) $this->option('status')));
        $n = 0;

        MqttMessage::whereIn('status', $statuses)->orderBy('id')->chunkById(500, function ($rows) use ($receiver, &$n) {
            foreach ($rows as $m) {
                $receiver->process($m);
                $n++;
            }
        });

        $this->info("{$n} pesan diproses ulang.");

        return self::SUCCESS;
    }
}
