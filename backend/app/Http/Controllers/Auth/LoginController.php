<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SesiIdle;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AutentikasiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(LoginRequest $request, AutentikasiService $auth): JsonResponse
    {
        $data = $request->validated();

        $user = $auth->masuk($data['username'], $data['password'], (string) $request->ip());

        // Session guard login sudah menyimpan identitas, hash sandi, dan regenerasi sesi.
        Auth::guard('web')->login($user);
        $request->session()->put(SesiIdle::KUNCI, CarbonImmutable::now('UTC')->timestamp);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'aktif' => (bool) $user->aktif,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'aktif' => (bool) $user->aktif,
            ],
        ]);
    }

    public function logout(Request $request, AutentikasiService $auth): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $auth->keluar($user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['pesan' => 'Berhasil keluar.']);
    }
}
