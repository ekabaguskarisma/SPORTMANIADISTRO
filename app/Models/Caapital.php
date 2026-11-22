<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Capital extends Model
{
    protected $fillable = [
        'note',
        'amount',
        'recorded_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'recorded_at' => 'date',
    ];
}