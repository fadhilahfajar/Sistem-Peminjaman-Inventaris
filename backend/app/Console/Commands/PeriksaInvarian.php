<?php

namespace App\Console\Commands;

use App\Services\PemeriksaInvarian;
use Illuminate\Console\Command;

class PeriksaInvarian extends Command
{
    protected $signature = 'inventaris:periksa-invarian';

    protected $description = 'Menghitung ulang invarian stok BR-01 dan mencocokkannya dengan buku besar (FR-S-03).';

    public function handle(PemeriksaInvarian $pemeriksa): int
    {
        $pelanggaran = $pemeriksa->periksa();

        if ($pelanggaran === []) {
            $this->info('0 pelanggaran. Stok konsisten dengan buku besar.');

            return self::SUCCESS;
        }

        foreach ($pelanggaran as $baris) {
            $this->error($baris);
        }

        $this->error(count($pelanggaran).' pelanggaran.');

        return self::FAILURE;
    }
}
