<?php

namespace App\Services;

use App\Models\User;
use App\Support\AturanUsername;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AkunAdminService
{
    /** Bawaan sementara usulan B, belum dikonfirmasi A. */
    public const PANJANG_MIN_SANDI = 12;

    /** $userId null berarti jalur artisan tanpa sesi admin; panel mencatat aktornya sendiri. */
    public function buat(string $nama, string $username, string $sandi, ?int $userId = null, ?string $email = null): User
    {
        $nama = trim($nama);
        $username = mb_strtolower(trim($username));
        $email = $email !== null ? mb_strtolower(trim($email)) : null;
        if ($email === '') {
            $email = null;
        }

        if ($nama === '' || mb_strlen($nama) > 100) {
            throw new InvalidArgumentException('Nama wajib diisi dan maksimal 100 karakter.');
        }

        $this->validasiUsername($username);

        if ($email !== null && (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) {
            throw new InvalidArgumentException('Email tidak valid.');
        }

        $this->validasiSandi($sandi);

        if (User::where('username', $username)->exists()) {
            throw new InvalidArgumentException('Username sudah dipakai.');
        }

        if ($email !== null && User::where('email', $email)->exists()) {
            throw new InvalidArgumentException('Email sudah dipakai.');
        }

        return DB::transaction(function () use ($nama, $username, $email, $sandi, $userId) {
            $user = User::create([
                'name' => $nama,
                'username' => $username,
                'email' => $email,
                'password' => $sandi,
                'aktif' => true,
            ]);

            if ($userId === null) {
                Audit::catat('akun_admin.dibuat', 'users', $user->id, null, [
                    'nama' => $user->name,
                    'username' => $user->username,
                ]);
            }

            return $user;
        }, 3);
    }

    public function resetSandi(string $username, string $sandi): User
    {
        $this->validasiSandi($sandi);

        $user = User::where('username', mb_strtolower(trim($username)))->first();

        if ($user === null) {
            throw new InvalidArgumentException('Akun admin dengan username itu tidak ditemukan.');
        }

        DB::transaction(function () use ($user, $sandi) {
            $user->password = $sandi;
            $user->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            Audit::catat('akun_admin.sandi_diganti', 'users', $user->id, null, [
                'username' => $user->username,
            ]);
        }, 3);

        return $user;
    }

    private function validasiSandi(string $sandi): void
    {
        if (mb_strlen($sandi) < self::PANJANG_MIN_SANDI) {
            throw new InvalidArgumentException('Kata sandi minimal '.self::PANJANG_MIN_SANDI.' karakter.');
        }
    }

    private function validasiUsername(string $username): void
    {
        if (mb_strlen($username) < 3 || mb_strlen($username) > 50 || ! preg_match(AturanUsername::POLA, $username)) {
            throw new InvalidArgumentException('Username tidak valid.');
        }
    }
}
