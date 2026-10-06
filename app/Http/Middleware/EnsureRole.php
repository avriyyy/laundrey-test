<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Token tidak valid atau belum dikirim',
            ], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Akses ditolak untuk peran ini',
            ], 403);
        }

        return $next($request);
    }
}
