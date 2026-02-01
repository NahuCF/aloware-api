<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Line extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ivr_steps' => 'array',
        ];
    }
}
