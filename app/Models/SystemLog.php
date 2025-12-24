<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'channel',
        'level',
        'message',
        'context',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'context' => 'json',
    ];
}
