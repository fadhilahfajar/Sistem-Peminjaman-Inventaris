<?php

namespace App\Services;

use App\Exceptions\GalatBisnis;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kelola akun admin dari panel (FR-A-02). Mutasi akun tetap lewat AkunAdminService (B).
 */
class PenggunaAdminService
{
    public function __construct(private readonly AkunAdminService $akun) {}

    public function daftar()
    {
        return User::query()->orderBy('name')->orderBy('id')->get();
    }

    public function buat(string $nama, string $email, string $sandi, int $actorId): User
    {
        try {
            $user = $this->akun->buat($nama, $email, $sandi);
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'Email sudah dipakai.') {
                throw ValidationException::withMessages([
                    'email' => [$e->getMessage()],
                ]);
            }

            throw $e;
        }

        Audit::catat(
            aksi: 'akun_admin.dibuat',
            entitas: 'users',
            entitasId: $user->id,
            userId: $actorId,
            detail: ['nama' => $user->name, 'email' => $user->email],
        );

        return $user;
    }

    public function ubah(User $user, array $data, int $actorId): User
    {
        return DB::transaction(function () use ($user, $data, $actorId) {
            $perubahan = [];

            if (array_key_exists('name', $data) && $data['name'] !== $user->name) {
                $perubahan['name'] = ['lama' => $user->name, 'baru' => $data['name']];
                $user->name = $data['name'];
            }

            if (array_key_exists('aktif', $data)) {
                $baru = (bool) $data['aktif'];

                if (! $baru && $user->id === $actorId) {
                    throw GalatBisnis::konflik('Akun sendiri tidak dapat dinonaktifkan.', 'tidak_dapat_menonaktifkan_diri');
                }

                if ($baru !== (bool) $user->aktif) {
                    $perubahan['aktif'] = ['lama' => (bool) $user->aktif, 'baru' => $baru];
                    $user->aktif = $baru;

                    if (! $baru) {
                        DB::table('sessions')->where('user_id', $user->id)->delete();
                    }
                }
            }

            $user->save();

            if ($perubahan !== []) {
                Audit::catat(
                    aksi: 'akun_admin.diubah',
                    entitas: 'users',
                    entitasId: $user->id,
                    userId: $actorId,
                    detail: $perubahan,
                );
            }

            return $user;
        }, 3);
    }
}
