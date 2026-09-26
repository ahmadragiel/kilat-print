<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Read-only-friendly representation of Laravel's database notification row.
 * The Notifiable trait continues to use Laravel's DatabaseNotification class;
 * this model is provided for domain queries and reporting.
 */
class Notification extends Model
{
    protected $table = 'notifications';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $notification): void {
            $notification->id ??= (string) Str::uuid();
        });
    }
}
