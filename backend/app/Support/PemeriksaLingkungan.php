<?php

namespace App\Support;

class PemeriksaLingkungan
{
    /**
     * @param  array{version?: string, sql_mode?: string, character_set_database?: string}  $data
     * @return array{status: string, pemeriksaan: list<array{nama: string, status: string, pesan: string}>}
     */
    public function periksa(array $data): array
    {
        $versi = (string) ($data['version'] ?? '');
        $modeSql = (string) ($data['sql_mode'] ?? '');
        $charset = (string) ($data['character_set_database'] ?? '');
        $mariaDb = str_contains(strtolower($versi), 'mariadb');

        preg_match('/^\d+(?:\.\d+)+/', trim($versi), $matches);

        $pemeriksaan = [
            $this->hasil(
                'Jenis server',
                ! $mariaDb,
                $mariaDb
                    ? 'Server terdeteksi sebagai MariaDB; MySQL diperlukan.'
                    : 'Server bukan MariaDB.'
            ),
            $this->hasil(
                'Versi MySQL',
                ! $mariaDb && isset($matches[0]) && version_compare($matches[0], '8.0.16', '>='),
                'Versi MySQL minimal 8.0.16.'
            ),
            $this->hasil(
                'Mode SQL',
                in_array('STRICT_TRANS_TABLES', array_map('trim', explode(',', strtoupper($modeSql))), true),
                'Mode SQL harus memuat STRICT_TRANS_TABLES.'
            ),
            $this->hasil(
                'Charset database',
                strtolower(trim($charset)) === 'utf8mb4',
                'Charset database harus utf8mb4.'
            ),
        ];

        return [
            'status' => count(array_filter(
                $pemeriksaan,
                fn (array $hasil): bool => $hasil['status'] === 'gagal'
            )) === 0
                ? 'lulus'
                : 'gagal',
            'pemeriksaan' => $pemeriksaan,
        ];
    }

    /**
     * @return array{nama: string, status: string, pesan: string}
     */
    private function hasil(string $nama, bool $lulus, string $pesan): array
    {
        return [
            'nama' => $nama,
            'status' => $lulus ? 'lulus' : 'gagal',
            'pesan' => $pesan,
        ];
    }
}
