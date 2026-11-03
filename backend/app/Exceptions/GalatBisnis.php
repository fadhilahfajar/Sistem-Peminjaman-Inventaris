<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Pelanggaran aturan bisnis yang diketahui (409). Pesannya aman ditampilkan ke pengguna. */
class GalatBisnis extends HttpException
{
    public function __construct(int $status, public readonly string $kode, string $pesan)
    {
        parent::__construct($status, $pesan);
    }

    public static function konflik(string $pesan, string $kode = 'konflik'): self
    {
        return new self(409, $kode, $pesan);
    }
}
