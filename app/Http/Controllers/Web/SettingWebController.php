<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingWebController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        if ($request->input('prefix')) {
            $request->merge(['prefix' => strtoupper((string) $request->input('prefix'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'prefix' => ['required', 'string', 'size:3', 'regex:/^[A-Z]+$/', 'unique:tenants,prefix,'.auth()->user()->tenant_id],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'prefix.regex' => 'Prefix must be 3 capital letters',
            'prefix.unique' => 'Prefix already taken by another laundry',
        ]);

        auth()->user()->tenant->update($data);

        return back()->with('sukses', 'Settings saved. New receipts use the new prefix.');
    }
}
