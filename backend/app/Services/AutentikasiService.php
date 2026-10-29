<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AutentikasiService
{
    public const MAKS_GAGAL = 5;

    public const JENDELA_DETIK = 900;

    private static ?string $hashPalsu = null;

    public function masuk(string $email, string $sandi, string $ip): User
    {
        $email = Str::lower(trim($email));
        $kunci = Str::transliterate($email.'|'.$ip);

        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_GAGAL)) {
            throw new ThrottleRequestsException(
                'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.',
                null,
                ['Retry-After' => RateLimiter::availableIn($kunci)],
            );
        }

        $user = User::where('email', $email)->first();
        $cocok = Hash::check($sandi, $user?->password ?? $this->hashPalsu());

        if ($user === null || ! $cocok) {
            RateLimiter::hit($kunci, self::JENDELA_DETIK);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        if (! $user->aktif) {
            throw new AuthorizationException('Akun tidak aktif.');
        }

        RateLimiter::clear($kunci);

        DB::transaction(function () use ($user) {
            $user->login_terakhir = now();
            $user->save();

            AuditLog::create([
                'user_id' => $user->id,
                'aksi' => 'login',
                'entitas' => 'users',
                'entitas_id' => $user->id,
            ]);
        });

        return $user;
    }

    private function hashPalsu(): string
    {
        return self::$hashPalsu ??= Hash::make(Str::random(32));
    }
}
