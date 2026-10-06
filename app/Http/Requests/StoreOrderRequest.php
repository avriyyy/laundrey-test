<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_name' => ['required_without:user_id', 'nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'promo_code' => ['nullable', 'string', 'max:20'],
            'weight_or_qty' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'payment_status' => ['sometimes', 'string', 'in:unpaid,paid'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.exists' => 'Layanan tidak ditemukan',
            'user_id.exists' => 'Pelanggan tidak ditemukan',
            'customer_name.required_without' => 'Pilih pelanggan atau isi nama walk-in',
        ];
    }
}
