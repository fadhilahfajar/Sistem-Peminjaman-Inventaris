<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KataSandiService
{
    /**
     * Mengganti kata sandi akun sendiri. Sesi lain milik akun yang sama diakhiri,
     * sesi yang sedang dipakai dipertahankan (bawaan sementara, belum dikonfirmasi A).
     * Mengembalikan jumlah sesi lain yang diakhiri.
     */
    public function ganti(User $user, string $passwordBaru, ?string $sesiSaatIni): int
    {
        return DB::transaction(function () use ($user, $passwordBaru, $sesiSaatIni) {
            $user->password = $passwordBaru; // di-hash oleh cast 'hashed'
            $user->save();

            $diakhiri = DB::table('sessions')
                ->where('user_id', $user->id)
                ->when($sesiSaatIni !== null, fn ($q) => $q->where('id', '!=', $sesiSaatIni))
                ->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'aksi' => 'ganti_password',
                'entitas' => 'users',
                'entitas_id' => $user->id,
                'detail' => ['sesi_lain_diakhiri' => $diakhiri],
            ]);

            return $diakhiri;
        });
    }
}
