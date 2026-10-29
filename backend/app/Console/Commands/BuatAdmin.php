<?php

namespace App\Console\Commands;

use App\Services\AkunAdminService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class BuatAdmin extends Command
{
    protected $signature = 'inventaris:buat-admin {email : Email admin} {nama : Nama admin}';

    protected $description = 'Membuat akun admin (kata sandi diminta secara interaktif)';

    public function handle(AkunAdminService $akun): int
    {
        $sandi = (string) $this->secret('Kata sandi');

        if ($sandi !== (string) $this->secret('Ulangi kata sandi')) {
            $this->error('Kata sandi tidak sama.');

            return self::FAILURE;
        }

        try {
            $user = $akun->buat($this->argument('nama'), $this->argument('email'), $sandi);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Akun admin {$user->email} dibuat.");

        return self::SUCCESS;
    }
}
