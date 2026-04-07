<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormFieldMapping extends Model
{
    protected $fillable = [
        'form_id',
        'cognito_field',
        'nowcerts_entity',
        'nowcerts_field',
    ];
}
