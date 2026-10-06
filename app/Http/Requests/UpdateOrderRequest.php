<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weight_or_qty' => ['sometimes', 'numeric', 'min:0.1', 'max:1000'],
            'payment_status' => ['sometimes', 'string', 'in:unpaid,paid'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
        ];
    }
}
