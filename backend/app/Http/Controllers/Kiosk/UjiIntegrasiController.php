<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** SEMENTARA (1.19b): hanya untuk membuktikan cookie, CSRF, dan CORS. HAPUS setelah terbukti. */
class UjiIntegrasiController extends Controller
{
    public function kirim(): JsonResponse
    {
        return response()->json(['pesan' => 'POST kiosk sementara diterima.']);
    }
}
