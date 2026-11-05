<?php

use App\Services\PemeriksaInvarian;

/**
 * Task 2.13: dipanggil di akhir tes Service. Gunakan $denganBuku = false hanya bila
 * barang pada tes itu dibuat langsung lewat model tanpa buku besar.
 */
function assertInvarian(bool $denganBuku = true): void
{
    $pelanggaran = (new PemeriksaInvarian)->periksa($denganBuku);

    test()->assertSame([], $pelanggaran, "Invarian stok dilanggar:\n".implode("\n", $pelanggaran));
}
