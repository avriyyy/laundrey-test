<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_name' => ['sometimes', 'string', 'max:100'],
            'price_per_unit' => ['sometimes', 'numeric', 'min:0'],
            'unit_type' => ['sometimes', 'string', 'in:kg,pcs'],
            'estimated_hours' => ['sometimes', 'integer', 'min:1', 'max:720'],
        ];
    }
}
