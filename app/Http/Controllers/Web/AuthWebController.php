<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthWebController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records'])->onlyInput('email');
        }

        if (! in_array(Auth::user()->role, ['admin', 'superadmin'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Customer accounts are managed by the laundry counter'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $target = Auth::user()->role === 'superadmin' ? route('admin.dashboard') : route('dashboard');

        return redirect()->intended($target)->with('sukses', 'Signed in. Welcome back.');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $admin = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['laundry_name'],
                'prefix' => $data['prefix'],
            ]);

            return User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'admin',
                'phone' => $data['phone'] ?? null,
            ]);
        });

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('sukses', 'Laundry registered. Welcome.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('sukses', 'Signed out.');
    }
}
