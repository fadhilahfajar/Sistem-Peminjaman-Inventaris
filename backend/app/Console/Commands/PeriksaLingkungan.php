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
                'SELECT VERSION() AS version, @@sql_mode AS sql_mode, @@character_set_database AS character_set_database'
            );
        } catch (QueryException) {
            $this->error('Koneksi database gagal. Periksa konfigurasi dan ketersediaan server.');

            return self::FAILURE;
        }

        if ($data === null) {
            $this->error('Pemeriksaan database tidak mengembalikan hasil.');

            return self::FAILURE;
        }

        $hasil = $pemeriksa->periksa((array) $data);

        foreach ($hasil['pemeriksaan'] as $pemeriksaan) {
            $baris = sprintf(
                '%s: %s — %s',
                $pemeriksaan['nama'],
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
