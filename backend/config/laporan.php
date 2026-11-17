<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batas baris laporan PDF
    |--------------------------------------------------------------------------
    |
    | Nilai bawaan sementara (Catatan Log A, 6.2 bagian 1). Hitungan dilakukan
    | sebelum data diambil; laporan yang lebih besar ditolak dengan 422.
    |
    */
    'maks_baris' => env('LAPORAN_MAKS_BARIS', 2000),

];
