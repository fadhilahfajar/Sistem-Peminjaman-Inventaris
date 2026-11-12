<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Baca dan ubah parameter sistem (FR-A-12). Rentang divalidasi lewat PengaturanService.
 */
class PengaturanAdminService
{
    public function __construct(private readonly PengaturanService $pengaturan) {}

    /** @return list<array{kunci: string, nilai: int, min: int, maks: int}> */
    public function daftar(): array
    {
        $hasil = [];

        foreach (PengaturanService::ATURAN as $kunci => $aturan) {
            $nilai = Setting::query()->whereKey($kunci)->value('nilai');

            $hasil[] = [
                'kunci' => $kunci,
                'nilai' => is_numeric($nilai) ? (int) $nilai : (int) $aturan['bawaan'],
                'min' => (int) $aturan['min'],
                'maks' => (int) $aturan['maks'],
            ];
        }

        return $hasil;
    }

    /** @param array<string, mixed> $nilaiBaru */
    public function perbarui(array $nilaiBaru, int $adminId): array
    {
        $kunciDikenal = array_keys(PengaturanService::ATURAN);
        $perubahan = [];

        return DB::transaction(function () use ($nilaiBaru, $adminId, $kunciDikenal, &$perubahan) {
            foreach ($nilaiBaru as $kunci => $nilai) {
                if (! in_array($kunci, $kunciDikenal, true)) {
                    continue;
                }

                $lama = Setting::query()->whereKey($kunci)->value('nilai');

                try {
                    $this->pengaturan->validasi($kunci, (int) $nilai);
                } catch (\InvalidArgumentException $e) {
                    throw ValidationException::withMessages([$kunci => [$e->getMessage()]]);
                }

                Setting::query()->whereKey($kunci)->update(['nilai' => (string) $nilai]);

                if ((string) $lama !== (string) $nilai) {
                    $perubahan[$kunci] = ['lama' => $lama, 'baru' => $nilai];
                }
            }

            if ($perubahan !== []) {
                Audit::catat(
                    aksi: 'pengaturan.diubah',
                    entitas: 'settings',
                    entitasId: null,
                    userId: $adminId,
                    detail: $perubahan,
                );
            }

            return $this->daftar();
        }, 3);
    }
}
