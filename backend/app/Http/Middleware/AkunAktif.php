<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Menolak akun yang dinonaktifkan saat sesinya masih berjalan. */
class AkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->aktif) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return response()->json([
                'kode' => 'akun_nonaktif',
                'pesan' => 'Akun ini tidak aktif. Hubungi administrator.',
            ], 403);
        }

        return $next($request);
    }
}
