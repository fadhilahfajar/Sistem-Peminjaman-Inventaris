<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReturnClaim;
use App\Support\Tanggal;

/**
 * Service baca-saja untuk menyusun payload kartu peminjaman kiosk (FR-K-04, FR-K-09, DESAIN 4 dan 10.2).
 *
 * Murni membaca dan tidak pernah menulis ke database atau mengubah angka stok.
 */
class KartuPeminjamanService
{
    public function __construct(
        private PengaturanService $pengaturan,
        private StatusPeminjaman $statusPeminjaman,
    ) {}

    /**
     * Membangun payload kartu peminjaman dari model Loan.
     *
     * @return array<string, mixed>
     */
    public function bangun(Loan $loan, ?string $hariIni = null): array
    {
        $hariIni ??= Tanggal::hariIni();

        $loan->loadMissing([
            'divisi',
            'items.item',
            'items.claimLines.claim',
            'claims.lines.loanItem.item',
            'extensions',
        ]);

        $statusInfo = $this->statusPeminjaman->dariModel($loan, $hariIni);

        $tanggalPinjam = $loan->tanggal_pinjam->format('Y-m-d');
        $batasKembali = $loan->batas_kembali->format('Y-m-d');
        $batasKembaliAwal = $loan->batas_kembali_awal->format('Y-m-d');

        $isPemesanan = $tanggalPinjam > $hariIni;
        $isDibatalkan = $loan->dibatalkan_pada !== null;

        // Hitung per barang sesuai DESAIN bagian 4
        $items = [];
        $totalSisa = 0;
        $adaBarangTidakBolehDiperpanjang = false;
        $namaBarangTidakBolehDiperpanjang = null;

        foreach ($loan->items as $loanItem) {
            $menunggu = 0;
            $terverifikasi = 0;

            foreach ($loanItem->claimLines as $baris) {
                if ($baris->claim?->status === 'menunggu') {
                    $menunggu += (int) $baris->qty;
                } elseif ($baris->claim?->status === 'diverifikasi') {
                    // Unit yang ditolak tidak dihitung sebagai terverifikasi: kembali menjadi sisa (BR-04)
                    $terverifikasi += (int) $baris->qty_baik + (int) $baris->qty_rusak + (int) $baris->qty_hilang;
                }
            }

            $sisa = $loanItem->qty_dipinjam - $menunggu - $terverifikasi;
            $totalSisa += $sisa;

            $bolehDiperpanjang = (bool) ($loanItem->item?->boleh_diperpanjang ?? true);

            if ($sisa > 0 && ! $bolehDiperpanjang) {
                $adaBarangTidakBolehDiperpanjang = true;
                $namaBarangTidakBolehDiperpanjang ??= $loanItem->item?->nama ?? 'barang';
            }

            $items[] = [
                'item_id' => $loanItem->item_id,
                'nama' => $loanItem->item?->nama ?? '',
                'qty_dipinjam' => (int) $loanItem->qty_dipinjam,
                'menunggu' => $menunggu,
                'terverifikasi' => $terverifikasi,
                'sisa' => $sisa,
                'boleh_diperpanjang' => $bolehDiperpanjang,
            ];
        }

        // Hitung mundur (sisa_hari & hari_terlambat)
        if ($isPemesanan || $isDibatalkan) {
            $hitungMundur = [
                'sisa_hari' => null,
                'hari_terlambat' => null,
            ];
        } else {
            $hariTerlambat = $statusInfo['hari_terlambat'];
            $selisih = Tanggal::selisihHari($hariIni, $batasKembali);
            $sisaHari = max(0, $selisih);

            $hitungMundur = [
                'sisa_hari' => $hariTerlambat > 0 ? 0 : $sisaHari,
                'hari_terlambat' => $hariTerlambat,
            ];
        }

        // Parameter dan kuota perpanjangan
        $terpakai = $loan->extensions->count();
        $maksPerpanjangan = $this->pengaturan->angka('maks_perpanjangan');
        $sisaKuota = max(0, $maksPerpanjangan - $terpakai);
        $maksHariPerPerpanjangan = $this->pengaturan->angka('maks_hari_per_perpanjangan');

        $perpanjangan = [
            'terpakai' => $terpakai,
            'maks' => $maksPerpanjangan,
            'sisa_kuota' => $sisaKuota,
            'maks_hari_per_perpanjangan' => $maksHariPerPerpanjangan,
        ];

        // Riwayat klaim (terbaru dulu)
        $klaimList = [];
        $sortedClaims = $loan->claims->sortByDesc(fn (ReturnClaim $c) => $c->diklaim_pada?->timestamp ?? $c->id);

        foreach ($sortedClaims as $c) {
            $klaimItem = [
                'kode_klaim' => $c->kode_klaim,
                'diklaim_pada' => $c->diklaim_pada?->toISOString(),
                'status' => $c->status,
                'catatan_peminjam' => $c->catatan_peminjam,
                'hari_terlambat' => (int) $c->hari_terlambat,
            ];

            if ($c->status === 'diverifikasi') {
                $klaimItem['catatan_admin'] = $c->catatan_admin;
                $klaimItem['hasil'] = $c->lines->map(fn ($line) => [
                    'nama' => $line->loanItem?->item?->nama ?? '',
                    'kondisi_dilaporkan' => $line->kondisi_dilaporkan,
                    'qty' => (int) $line->qty,
                    'qty_baik' => (int) $line->qty_baik,
                    'qty_rusak' => (int) $line->qty_rusak,
                    'qty_hilang' => (int) $line->qty_hilang,
                    'qty_ditolak' => (int) $line->qty_ditolak,
                ])->values()->all();
            }

            $klaimList[] = $klaimItem;
        }

        // Evaluasi aksi (klaim, perpanjang, batal)
        $aksi = [
            'klaim' => $this->evaluasiAksiKlaim($isDibatalkan, $isPemesanan, $totalSisa),
            'perpanjang' => $this->evaluasiAksiPerpanjang(
                $isDibatalkan,
                $isPemesanan,
                $totalSisa,
                $hariIni,
                $batasKembali,
                $terpakai,
                $maksPerpanjangan,
                $adaBarangTidakBolehDiperpanjang,
                $namaBarangTidakBolehDiperpanjang
            ),
            'batal' => $this->evaluasiAksiBatal($isDibatalkan, $hariIni, $tanggalPinjam),
        ];

        return [
            'kode_pinjam' => $loan->kode_pinjam,
            'nama_peminjam' => $loan->nama_peminjam,
            'division' => [
                'nama' => $loan->divisi?->nama ?? '',
            ],
            'tipe_peminjam' => $loan->tipe_peminjam,
            'tanggal_pinjam' => $tanggalPinjam,
            'batas_kembali_awal' => $batasKembaliAwal,
            'batas_kembali' => $batasKembali,
            'status' => $statusInfo['status'],
            'lencana' => $statusInfo['lencana'],
            'pemesanan' => $isPemesanan,
            'hitung_mundur' => $hitungMundur,
            'perpanjangan' => $perpanjangan,
            'items' => $items,
            'klaim' => $klaimList,
            'aksi' => $aksi,
        ];
    }

    /**
     * @return array{boleh: bool, alasan: string|null}
     */
    private function evaluasiAksiKlaim(bool $isDibatalkan, bool $isPemesanan, int $totalSisa): array
    {
        if ($isDibatalkan) {
            return ['boleh' => false, 'alasan' => 'Peminjaman ini sudah dibatalkan dan tidak dapat diklaim.'];
        }

        if ($isPemesanan) {
            return ['boleh' => false, 'alasan' => 'Pemesanan belum dimulai, hanya dapat dibatalkan.'];
        }

        if ($totalSisa <= 0) {
            return ['boleh' => false, 'alasan' => 'Semua unit sudah diklaim.'];
        }

        return ['boleh' => true, 'alasan' => null];
    }

    /**
     * @return array{boleh: bool, alasan: string|null}
     */
    private function evaluasiAksiPerpanjang(
        bool $isDibatalkan,
        bool $isPemesanan,
        int $totalSisa,
        string $hariIni,
        string $batasKembali,
        int $terpakai,
        int $maksPerpanjangan,
        bool $adaBarangTidakBolehDiperpanjang,
        ?string $namaBarang
    ): array {
        if ($isDibatalkan) {
            return ['boleh' => false, 'alasan' => 'Peminjaman yang dibatalkan tidak dapat diperpanjang.'];
        }

        if ($isPemesanan) {
            return ['boleh' => false, 'alasan' => 'Pemesanan tidak dapat diperpanjang.'];
        }

        if ($totalSisa < 1) {
            return ['boleh' => false, 'alasan' => 'Semua unit sudah diklaim.'];
        }

        if ($batasKembali < $hariIni) {
            return ['boleh' => false, 'alasan' => 'Sudah terlambat, hubungi petugas IT.'];
        }

        if ($terpakai >= $maksPerpanjangan) {
            return ['boleh' => false, 'alasan' => 'Kuota perpanjangan habis, hubungi petugas IT.'];
        }

        if ($adaBarangTidakBolehDiperpanjang) {
            return ['boleh' => false, 'alasan' => "Barang {$namaBarang} tidak boleh diperpanjang."];
        }

        return ['boleh' => true, 'alasan' => null];
    }

    /**
     * @return array{boleh: bool, alasan: string|null}
     */
    private function evaluasiAksiBatal(bool $isDibatalkan, string $hariIni, string $tanggalPinjam): array
    {
        if ($isDibatalkan) {
            return ['boleh' => false, 'alasan' => 'Peminjaman ini sudah dibatalkan.'];
        }

        if ($hariIni >= $tanggalPinjam) {
            return ['boleh' => false, 'alasan' => 'Pemesanan hanya dapat dibatalkan sebelum tanggal pinjam.'];
        }

        return ['boleh' => true, 'alasan' => null];
    }
}
