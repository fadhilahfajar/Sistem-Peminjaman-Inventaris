<?php

namespace App\Http\Middleware;

use App\Services\PengaturanService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Mengakhiri sesi admin yang idle melebihi settings.sesi_idle_menit (BR-19, K-7). */
class SesiIdle
{
    public const KUNCI = 'aktivitas_terakhir';

    public function __construct(private PengaturanService $pengaturan) {}

    public function handle(Request $request, Closure $next): Response
    {
        $sekarang = CarbonImmutable::now('UTC')->timestamp;
        $terakhir = $request->session()->get(self::KUNCI);
        $batasDetik = $this->pengaturan->angka('sesi_idle_menit') * 60;

        if ($terakhir !== null && $sekarang - (int) $terakhir > $batasDetik) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'kode' => 'sesi_berakhir',
                'pesan' => 'Sesi berakhir karena tidak ada aktivitas. Silakan masuk lagi.',
            ], 401);
        }

        $request->session()->put(self::KUNCI, $sekarang);

        return $next($request);
    }
}
