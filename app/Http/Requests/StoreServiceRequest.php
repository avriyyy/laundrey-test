<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_name' => ['required', 'string', 'max:100'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'unit_type' => ['required', 'string', 'in:kg,pcs'],
            'estimated_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ];
    }
}
