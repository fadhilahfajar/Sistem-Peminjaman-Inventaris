<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UbahKataSandiRequest;
use App\Services\KataSandiService;
use Illuminate\Http\JsonResponse;

class KataSandiController extends Controller
{
    public function ubah(UbahKataSandiRequest $request, KataSandiService $service): JsonResponse
    {
        $service->ganti(
            $request->user(),
            $request->validated('password_baru'),
            $request->hasSession() ? $request->session()->getId() : null,
        );

        return response()->json(['pesan' => 'Kata sandi berhasil diganti.']);
    }
}
