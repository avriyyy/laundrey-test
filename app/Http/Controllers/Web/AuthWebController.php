<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (Auth::user()->role !== 'admin') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'Customer accounts are managed by the laundry counter'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('sukses', 'Signed in. Welcome back.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('sukses', 'Signed out.');
    }
}
