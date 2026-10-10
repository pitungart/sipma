<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengingat terjadwal yang sudah terkirim (lihat App\Console\Commands\SendReminders).
 */
class SentReminder extends Model
{
    public const SUBMISSION = 'submission';

    public const PAYMENT = 'payment';

    public $timestamps = false;

    protected $fillable = ['kind', 'key', 'user_id', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public static function lastSent(string $kind, string $key): ?self
    {
        return static::query()->where('kind', $kind)->where('key', $key)->latest('sent_at')->first();
    }
}
