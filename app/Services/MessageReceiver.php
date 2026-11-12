<?php

namespace App\Services;

use App\Models\MqttMessage;
use App\Models\Reading;
use App\Models\Setting;
use Carbon\Carbon;

class MessageReceiver
{
    public function __construct(private MessageParser $parser) {}

    public function handle(string $topic, string $payload, ?Carbon $receivedAt = null): void
    {
        $receivedAt = ($receivedAt ?? now())->copy()->utc();
        // Buang byte yang tidak bisa disimpan PostgreSQL
        $topic = str_replace("\0", '', mb_scrub($topic));
        $payload = str_replace("\0", '', mb_scrub($payload));

        try {
            $message = MqttMessage::create([
                'received_at' => $receivedAt,
                'topic' => $topic,
                'payload' => $payload,
                'status' => 'baru',
            ]);
        } catch (\Throwable) {
            $this->spool($topic, $payload, $receivedAt);

            return;
        }

        $this->process($message);
    }

    public function process(MqttMessage $message): void
    {
        try {
            $mapping = (array) Setting::read('mqtt_mapping', []);
            $attrs = $this->parser->parse($message->payload, $mapping, $message->received_at);

            if ($attrs === null) {
                $this->finish($message, 'diabaikan', 'Tidak ada nilai pembacaan yang dikenali');

                return;
            }

            $attrs['measured_at'] = $attrs['measured_at']->copy()->utc()->format('Y-m-d H:i:s');
            $attrs['message_id'] = $message->id;

            $inserted = Reading::insertOrIgnore([$attrs]);
            if ($inserted === 0) {
                $this->finish($message, 'diabaikan', 'Pembacaan dengan waktu yang sama sudah ada');

                return;
            }
            $this->finish($message, 'ok', null);
        } catch (\Throwable $e) {
            $this->finish($message, 'gagal', mb_substr($e->getMessage(), 0, 500));
        }
    }

    private function finish(MqttMessage $message, string $status, ?string $error): void
    {
        $message->update(['status' => $status, 'error' => $error, 'processed_at' => now()->utc()]);
    }

    private function spoolPath(): string
    {
        return storage_path('app/spool/mqtt.jsonl');
    }

    private function spool(string $topic, string $payload, Carbon $at): void
    {
        $path = $this->spoolPath();
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $line = json_encode(
            ['topic' => $topic, 'payload' => $payload, 'at' => $at->toIso8601String()],
            JSON_INVALID_UTF8_SUBSTITUTE
        );
        file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /** Proses ulang pesan dari file cadangan. Mengembalikan jumlah baris yang dicoba. */
    public function replaySpool(): int
    {
        $path = $this->spoolPath();
        if (!is_file($path)) {
            return 0;
        }
        $work = $path . '.' . getmypid() . '.work';
        if (!@rename($path, $work)) {
            return 0;
        }
        $n = 0;
        foreach (file($work, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $row = json_decode($line, true);
            if (!is_array($row)) {
                continue;
            }
            $this->handle($row['topic'], $row['payload'], Carbon::parse($row['at']));
            $n++;
        }
        @unlink($work);

        return $n;
    }
}
