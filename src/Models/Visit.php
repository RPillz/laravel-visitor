<?php

namespace RPillz\LaravelVisitor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RPillz\LaravelVisitor\Database\Factories\VisitFactory;
use RPillz\LaravelVisitor\LaravelVisitor;

class Visit extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'url',
        'path',
        'query',
        'referrer',
        'referrer_domain',
        'ip_address',
        'country',
        'city',
        'device_type',
        'browser',
        'os',
        'user_agent',
        'bot_name',
        'is_user',
        'user_id',
        'session_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'is_user' => 'boolean',
        'user_id' => 'integer',
    ];

    public function getConnectionName(): string
    {
        return LaravelVisitor::resolveConnection();
    }

    protected static function newFactory(): VisitFactory
    {
        return VisitFactory::new();
    }
}
