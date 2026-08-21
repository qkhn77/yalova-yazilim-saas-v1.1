<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterMailTemplate extends Model
{
    protected $fillable = [
        'title',
        'subject',
        'content',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
