<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $pengguna = User::where('email', $data['email'])->first();

        if ($pengguna === null || Hash::check($data['password'], $pengguna->password) === false) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Email atau kata sandi tidak sesuai',
            ], 401);
        }

        if ($pengguna->role !== 'admin') {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Customer accounts are managed by the laundry counter',
            ], 403);
        }

        $abilities = match ($pengguna->role) {
            'admin' => ['order:tulis', 'service:tulis', 'track:tulis'],
            default => [],
        };

        $token = $pengguna->createToken('token-perangkat', $abilities)->plainTextToken;

        return response()->json([
            'sukses' => true,
            'pesan' => 'Login berhasil',
            'data' => [
                'pengguna' => [
                    'id' => $pengguna->id,
                    'name' => $pengguna->name,
                    'email' => $pengguna->email,
                    'role' => $pengguna->role,
                ],
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'sukses' => true,
            'pesan' => 'Logout berhasil',
        ]);
    }
}
