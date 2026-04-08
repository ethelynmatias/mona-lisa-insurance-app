<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDiscoveredField extends Model
{
    protected $fillable = ['form_id', 'fields'];

    protected $casts = [
        'fields' => 'array',
    ];
}
