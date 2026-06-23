<?php

namespace RPillz\LaravelVisitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use RPillz\LaravelVisitor\LaravelVisitor;

class VisitorIgnore extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['type', 'value'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function getConnectionName(): string
    {
        return LaravelVisitor::resolveConnection();
    }

    protected static function booted(): void
    {
        static::created(function (self $ignore) {
            Cache::forget('visitor.ignore_list.'.LaravelVisitor::resolveConnection());

            $column = match ($ignore->type) {
                'ip' => 'ip_address',
                'user_id' => 'user_id',
                default => null,
            };

            if ($column !== null) {
                Visit::where($column, $ignore->value)->delete();
            }
        });

        static::updated(function () {
            Cache::forget('visitor.ignore_list.'.LaravelVisitor::resolveConnection());
        });

        static::deleted(function () {
            Cache::forget('visitor.ignore_list.'.LaravelVisitor::resolveConnection());
        });
    }
}
