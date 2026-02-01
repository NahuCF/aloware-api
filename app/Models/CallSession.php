<?php

namespace App\Models;

use App\Enums\CallSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'path' => 'array',
        'context' => 'array',
        'status' => CallSessionStatus::class,
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }
}
