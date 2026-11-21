<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoBanner extends Model
{
    protected $fillable = [
        'title',
        'bg_typography',
        'subtitle',
        'description',
        'button_text',
        'image',
        'media_type',
        'video_path',
        'link',
        'placement',
        'is_active',
        'order',
    ];
}