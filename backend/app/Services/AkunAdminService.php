<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AkunAdminService
{
    /** Bawaan sementara usulan B, belum dikonfirmasi A. */
    public const PANJANG_MIN_SANDI = 12;

    public function buat(string $nama, string $email, string $sandi): User
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

        return User::create([
            'name' => $nama,
            'email' => $email,
            'password' => $sandi,
            'aktif' => true,
        ]);
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
