<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMappingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mappings'                   => ['required', 'array'],
            'mappings.*.cognito_field'   => ['required', 'string'],
            'mappings.*.nowcerts_entity' => ['nullable', 'string'],
            'mappings.*.nowcerts_field'  => ['nullable', 'string'],
        ];
    }
}
