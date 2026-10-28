<?php

namespace App\Console\Commands;

use App\Support\PemeriksaLingkungan;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PeriksaLingkungan extends Command
{
    protected $signature = 'inventaris:periksa-lingkungan';

    protected $description = 'Memeriksa versi dan konfigurasi server database';

    public function handle(PemeriksaLingkungan $pemeriksa): int
    {
        try {
            $data = DB::selectOne(
                'SELECT VERSION() AS version, @@global.sql_mode AS sql_mode, @@character_set_database AS charset'
            );
        } catch (QueryException) {
            $this->error('Koneksi database gagal. Periksa konfigurasi dan ketersediaan server.');

            return self::FAILURE;
        }

        if ($data === null) {
            $this->error('Pemeriksaan database tidak mengembalikan hasil.');

            return self::FAILURE;
        }

        // Laravel's strict connection mode overrides the session sql_mode, so inspect the server global value.
        $hasil = $pemeriksa->periksa([
            'version' => $data->version,
            'sql_mode' => $data->sql_mode,
            'character_set_database' => $data->charset,
        ]);

        foreach ($hasil['pemeriksaan'] as $pemeriksaan) {
            $nama = $pemeriksaan['nama'] === 'Mode SQL'
                ? 'sql_mode server (global)'
                : $pemeriksaan['nama'];
            $baris = sprintf(
                '%s: %s — %s',
                $nama,
                strtoupper($pemeriksaan['status']),
                $pemeriksaan['pesan']
            );

            if ($pemeriksaan['status'] === 'lulus') {
                $this->info($baris);
            } else {
                $this->error($baris);
            }
        }

        if ($hasil['status'] === 'gagal') {
            return self::FAILURE;
        }

        $this->info('Semua pemeriksaan lingkungan lulus.');

        return self::SUCCESS;
    }
}
