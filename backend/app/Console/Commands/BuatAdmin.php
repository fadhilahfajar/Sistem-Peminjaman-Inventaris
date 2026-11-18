<?php

namespace App\Console\Commands;

use App\Services\AkunAdminService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class BuatAdmin extends Command
{
    protected $signature = 'inventaris:buat-admin {username : Username admin}';

    protected $description = 'Membuat akun admin (nama dan kata sandi diminta secara interaktif)';

    public function handle(AkunAdminService $akun): int
    {
        $nama = (string) $this->ask('Nama admin');
        $sandi = (string) $this->secret('Kata sandi');

        if ($sandi !== (string) $this->secret('Ulangi kata sandi')) {
            $this->error('Kata sandi tidak sama.');

            return self::FAILURE;
        }

        try {
            $user = $akun->buat($nama, (string) $this->argument('username'), $sandi);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Akun admin {$user->username} dibuat.");

        return self::SUCCESS;
    }
}
