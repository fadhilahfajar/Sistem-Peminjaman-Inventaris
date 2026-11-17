<?php

namespace App\Services;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AkunAdminService
{
    /** Bawaan sementara usulan B, belum dikonfirmasi A. */
    public const PANJANG_MIN_SANDI = 12;

    /** $userId null berarti jalur artisan tanpa sesi admin; panel mencatat aktornya sendiri. */
    public function buat(string $nama, string $email, string $sandi, ?int $userId = null): User
    {
        $nama = trim($nama);
        $email = mb_strtolower(trim($email));

        if ($nama === '' || mb_strlen($nama) > 100) {
            throw new InvalidArgumentException('Nama wajib diisi dan maksimal 100 karakter.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            throw new InvalidArgumentException('Email tidak valid.');
        }

        $this->validasiSandi($sandi);

        if (User::where('email', $email)->exists()) {
            throw new InvalidArgumentException('Email sudah dipakai.');
        }

        return DB::transaction(function () use ($nama, $email, $sandi, $userId) {
            $user = User::create([
                'name' => $nama,
                'email' => $email,
                'password' => $sandi,
                'aktif' => true,
            ]);

            if ($userId === null) {
                Audit::catat('akun_admin.dibuat', 'users', $user->id, null, [
                    'nama' => $user->name,
                    'email' => $user->email,
                ]);
            }

            return $user;
        }, 3);
    }

    public function resetSandi(string $email, string $sandi): User
    {
        $this->validasiSandi($sandi);

        $user = User::where('email', mb_strtolower(trim($email)))->first();

        if ($user === null) {
            throw new InvalidArgumentException('Akun admin dengan email itu tidak ditemukan.');
        }

        DB::transaction(function () use ($user, $sandi) {
            $user->password = $sandi;
            $user->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            Audit::catat('akun_admin.sandi_diganti', 'users', $user->id, null, [
                'email' => $user->email,
            ]);
        });

        return $user;
    }

    private function validasiSandi(string $sandi): void
    {
        if (mb_strlen($sandi) < self::PANJANG_MIN_SANDI) {
            throw new InvalidArgumentException('Kata sandi minimal '.self::PANJANG_MIN_SANDI.' karakter.');
        }
    }
}
