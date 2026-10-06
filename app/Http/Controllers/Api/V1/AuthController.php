<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['laundry_name'],
                'prefix' => $data['prefix'],
            ]);

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'admin',
                'phone' => $data['phone'] ?? null,
            ]);

            $token = $admin->createToken('token-perangkat', ['order:tulis', 'service:tulis', 'track:tulis'])->plainTextToken;

            return [$tenant, $admin, $token];
        });

        [$tenant, $admin, $token] = $result;

        return response()->json([
            'sukses' => true,
            'pesan' => 'Laundry registered',
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'prefix' => $tenant->prefix,
                ],
                'pengguna' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                ],
                'token' => $token,
            ],
        ], 201);
    }

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
