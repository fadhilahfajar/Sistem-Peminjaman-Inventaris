<?php

namespace App\Console\Commands;

use App\Services\AkunAdminService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ResetPasswordAdmin extends Command
{
    protected $signature = 'inventaris:reset-password-admin {email : Email admin}';

    protected $description = 'Mengganti kata sandi admin dan mengakhiri semua sesinya';

    public function handle(AkunAdminService $akun): int
    {
        $sandi = (string) $this->secret('Kata sandi baru');

        if ($sandi !== (string) $this->secret('Ulangi kata sandi baru')) {
            $this->error('Kata sandi tidak sama.');

            return self::FAILURE;
        }

        try {
            $user = $akun->resetSandi($this->argument('email'), $sandi);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Kata sandi {$user->email} diganti dan semua sesinya diakhiri.");

        return self::SUCCESS;
    }
}
