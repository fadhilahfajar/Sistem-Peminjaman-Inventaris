<?php

namespace App\Services;

use App\Exceptions\GalatBisnis;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\ReturnClaim;
use App\Support\Tanggal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data laporan PDF bersifat baca-saja. Mengembalikan array terstruktur;
 * rendering HTML/PDF dilakukan controller (D-17).
 */
class LaporanService
{
    private const FILTER_REKAP = [
        'status', 'barang', 'kategori', 'divisi', 'tipe_peminjam',
        'dari', 'sampai', 'q', 'ada_catatan', 'ada_kerusakan', 'belum_kembali',
    ];

    private const FILTER_BELUM_KEMBALI = ['kategori', 'barang', 'divisi', 'tipe_peminjam'];

    private const FILTER_STOK = ['kategori'];

    private const FILTER_PENGEMBALIAN = ['dari', 'sampai', 'status', 'barang'];

    private const FILTER_BOOL = ['ada_catatan', 'ada_kerusakan', 'belum_kembali'];

    public function __construct(
        private readonly PeminjamanAdminService $peminjaman,
        private readonly StatusPeminjaman $status,
    ) {}

    /** @param array<string, mixed> $filter */
    public function rekap(array $filter, string $dicetakOleh, string $waktuCetak): array
    {
        $query = $this->peminjaman->queryDaftar($filter);
        $jumlah = $query->count();
        $this->jagaBatas($jumlah);

        $baris = [];
        $ringkasan = array_fill_keys(PeminjamanAdminService::STATUS, 0);

        $query->chunk(500, function ($loans) use (&$baris, &$ringkasan) {
            foreach ($loans as $loan) {
                $status = $this->status->dariModel($loan, Tanggal::hariIni());
                $ringkasan[$status['status']] = ($ringkasan[$status['status']] ?? 0) + 1;

                $baris[] = [
                    'kode_pinjam' => $loan->kode_pinjam,
                    'nama_peminjam' => $loan->nama_peminjam,
                    'divisi' => $loan->divisi?->nama ?? '—',
                    'tipe_peminjam' => $this->labelTipe($loan->tipe_peminjam),
                    'tanggal_pinjam' => $loan->tanggal_pinjam->format('Y-m-d'),
                    'batas_kembali' => $loan->batas_kembali->format('Y-m-d'),
                    'status' => $status['status'],
                    'hari_terlambat' => $status['hari_terlambat'],
                    'barang' => $this->daftarBarang($loan, false),
                    'ada_catatan' => in_array(StatusPeminjaman::LENCANA_CATATAN, $status['lencana'], true),
                ];
            }
        });

        return $this->dasar('rekap', 'Laporan Rekap Peminjaman', $filter, $dicetakOleh, $waktuCetak, $jumlah) + [
            'ringkasan' => $ringkasan,
            'baris' => $baris,
        ];
    }

    /** @param array<string, mixed> $filter */
    public function belumKembali(array $filter, string $dicetakOleh, string $waktuCetak): array
    {
        $filterInti = array_intersect_key($filter, array_flip(self::FILTER_BELUM_KEMBALI));
        $hariIni = Tanggal::hariIni();

        $kueri = [];
        $jumlah = 0;
        foreach (['dipinjam', 'terlambat', 'dipesan'] as $status) {
            $kueri[$status] = $this->peminjaman->queryDaftar($filterInti + ['status' => $status]);
            $jumlah += $kueri[$status]->count();
        }
        $this->jagaBatas($jumlah);

        $aktif = [];
        foreach (['dipinjam', 'terlambat'] as $status) {
            $kueri[$status]->chunk(500, function ($loans) use (&$aktif, $status, $hariIni) {
                foreach ($loans as $loan) {
                    $turunan = $this->status->dariModel($loan, $hariIni);

                    $aktif[] = [
                        'kode_pinjam' => $loan->kode_pinjam,
                        'nama_peminjam' => $loan->nama_peminjam,
                        'divisi' => $loan->divisi?->nama ?? '—',
                        'tipe_peminjam' => $this->labelTipe($loan->tipe_peminjam),
                        'tanggal_pinjam' => $loan->tanggal_pinjam->format('Y-m-d'),
                        'batas_kembali' => $loan->batas_kembali->format('Y-m-d'),
                        'status' => $status,
                        'hari_terlambat' => $turunan['hari_terlambat'],
                        'barang' => $this->daftarBarang($loan, true),
                    ];
                }
            });
        }

        usort($aktif, fn (array $a, array $b) => [
            $b['hari_terlambat'], $a['batas_kembali'], $a['kode_pinjam'],
        ] <=> [
            $a['hari_terlambat'], $b['batas_kembali'], $b['kode_pinjam'],
        ]);

        $pemesanan = [];
        $kueri['dipesan']->chunk(500, function ($loans) use (&$pemesanan) {
            foreach ($loans as $loan) {
                $pemesanan[] = [
                    'kode_pinjam' => $loan->kode_pinjam,
                    'nama_peminjam' => $loan->nama_peminjam,
                    'tanggal_pinjam' => $loan->tanggal_pinjam->format('Y-m-d'),
                    'batas_kembali' => $loan->batas_kembali->format('Y-m-d'),
                    'barang' => $this->daftarBarang($loan, false),
                ];
            }
        });

        return $this->dasar('belum-kembali', 'Laporan Belum Kembali', $filterInti, $dicetakOleh, $waktuCetak, $jumlah) + [
            'aktif' => $aktif,
            'pemesanan' => $pemesanan,
            'ringkasan' => [
                'dipinjam' => count(array_filter($aktif, fn ($b) => $b['status'] === 'dipinjam')),
                'terlambat' => count(array_filter($aktif, fn ($b) => $b['status'] === 'terlambat')),
                'dipesan' => count($pemesanan),
            ],
        ];
    }

    /** @param array<string, mixed> $filter */
    public function stok(array $filter, string $dicetakOleh, string $waktuCetak): array
    {
        $filterInti = array_intersect_key($filter, array_flip(self::FILTER_STOK));
        $query = $this->queryStok($filterInti);
        $jumlah = $query->count();
        $this->jagaBatas($jumlah);

        $kelompok = [];
        $total = [
            'total' => 0,
            'tersedia' => 0,
            'dipinjam' => 0,
            'menunggu_verifikasi' => 0,
            'rusak' => 0,
        ];

        $query->chunk(500, function ($items) use (&$kelompok, &$total) {
            foreach ($items as $item) {
                $mv = (int) $item->menunggu_verifikasi;
                $dipinjam = (int) $item->total - (int) $item->tersedia - (int) $item->rusak - $mv;
                $kondisi = match (true) {
                    (int) $item->rusak === 0 => 'Baik',
                    (int) $item->rusak === (int) $item->total && (int) $item->tersedia === 0 => 'Semua rusak',
                    default => 'Sebagian rusak',
                };

                $baris = [
                    'nama' => $item->nama,
                    'total' => (int) $item->total,
                    'tersedia' => (int) $item->tersedia,
                    'dipinjam' => $dipinjam,
                    'menunggu_verifikasi' => $mv,
                    'rusak' => (int) $item->rusak,
                    'kondisi' => $kondisi,
                ];

                $kategori = $item->kategori_nama ?: 'Tanpa Kategori';
                if (! isset($kelompok[$kategori])) {
                    $kelompok[$kategori] = [
                        'kategori' => $kategori,
                        'baris' => [],
                        'subtotal' => [
                            'total' => 0,
                            'tersedia' => 0,
                            'dipinjam' => 0,
                            'menunggu_verifikasi' => 0,
                            'rusak' => 0,
                        ],
                    ];
                }

                $kelompok[$kategori]['baris'][] = $baris;
                foreach (['total', 'tersedia', 'dipinjam', 'menunggu_verifikasi', 'rusak'] as $kunci) {
                    $kelompok[$kategori]['subtotal'][$kunci] += $baris[$kunci];
                    $total[$kunci] += $baris[$kunci];
                }
            }
        });

        return $this->dasar('stok', 'Laporan Stok Barang', $filterInti, $dicetakOleh, $waktuCetak, $jumlah) + [
            'posisiStok' => $waktuCetak,
            'kelompok' => array_values($kelompok),
            'total' => $total,
        ];
    }

    /** @param array<string, mixed> $filter */
    public function pengembalian(array $filter, string $dicetakOleh, string $waktuCetak): array
    {
        $filterInti = array_intersect_key($filter, array_flip(self::FILTER_PENGEMBALIAN));
        $query = $this->queryPengembalian($filterInti);
        $jumlah = $query->count();
        $this->jagaBatas($jumlah);

        $baris = [];
        $query->chunk(500, function ($claims) use (&$baris) {
            foreach ($claims as $claim) {
                $claim->loadMissing(['loan', 'lines.loanItem.item', 'verifikator']);
                $hasil = [];
                foreach ($claim->lines->sortBy('id') as $line) {
                    $nama = $line->loanItem?->item?->nama ?? 'Barang';
                    if ($claim->status === 'diverifikasi') {
                        $hasil[] = sprintf(
                            '%s: baik %d, rusak %d, hilang %d, ditolak %d',
                            $nama,
                            (int) $line->qty_baik,
                            (int) $line->qty_rusak,
                            (int) $line->qty_hilang,
                            (int) $line->qty_ditolak,
                        );
                    } else {
                        $hasil[] = sprintf('%s: %s %d', $nama, $line->kondisi_dilaporkan, (int) $line->qty);
                    }
                }

                $baris[] = [
                    'kode_klaim' => $claim->kode_klaim,
                    'kode_pinjam' => $claim->loan?->kode_pinjam ?? '—',
                    'nama_peminjam' => $claim->loan?->nama_peminjam ?? '—',
                    'diklaim_pada' => $claim->diklaim_pada?->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s') ?? '—',
                    'hari_terlambat' => (int) $claim->hari_terlambat,
                    'status' => $claim->status,
                    'hasil' => $hasil === [] ? '—' : implode('; ', $hasil),
                    'catatan_peminjam' => $claim->catatan_peminjam,
                    'catatan_admin' => $claim->catatan_admin,
                    'diverifikasi_oleh' => $claim->verifikator?->name ?? '—',
                    'diverifikasi_pada' => $claim->diverifikasi_pada?->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s') ?? '—',
                ];
            }
        });

        return $this->dasar('pengembalian', 'Laporan Riwayat Pengembalian', $filterInti, $dicetakOleh, $waktuCetak, $jumlah) + [
            'baris' => $baris,
        ];
    }

    /** @param array<string, mixed> $filter */
    private function queryStok(array $filter): Builder
    {
        $menungguSub = DB::table('return_claim_lines as rcl')
            ->join('return_claims as rc', 'rc.id', '=', 'rcl.claim_id')
            ->join('loan_items as li', 'li.id', '=', 'rcl.loan_item_id')
            ->where('rc.status', 'menunggu')
            ->select('li.item_id', DB::raw('COALESCE(SUM(rcl.qty), 0) as menunggu_verifikasi'))
            ->groupBy('li.item_id');

        return Item::query()
            ->leftJoinSub($menungguSub, 'mv', 'mv.item_id', '=', 'items.id')
            ->leftJoin('categories', 'categories.id', '=', 'items.kategori_id')
            ->select('items.*', DB::raw('COALESCE(mv.menunggu_verifikasi, 0) as menunggu_verifikasi'), 'categories.nama as kategori_nama')
            ->when($filter['kategori'] ?? null, fn ($query, $id) => $query->where('items.kategori_id', (int) $id))
            ->orderBy('categories.nama')
            ->orderBy('items.nama');
    }

    /** @param array<string, mixed> $filter */
    private function queryPengembalian(array $filter): Builder
    {
        $query = ReturnClaim::query()
            ->with(['loan', 'lines.loanItem.item', 'verifikator'])
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filter['barang'] ?? null, fn ($q, $id) => $q->whereHas(
                'lines.loanItem',
                fn ($line) => $line->where('item_id', (int) $id),
            ))
            ->orderByDesc('diklaim_pada')
            ->orderByDesc('id');

        if (! empty($filter['dari'])) {
            $awal = Carbon::parse((string) $filter['dari'], 'Asia/Jakarta')->startOfDay()->utc();
            $query->where('diklaim_pada', '>=', $awal);
        }

        if (! empty($filter['sampai'])) {
            $akhir = Carbon::parse((string) $filter['sampai'], 'Asia/Jakarta')->endOfDay()->utc();
            $query->where('diklaim_pada', '<=', $akhir);
        }

        return $query;
    }

    /** @param array<string, mixed> $filter */
    private function dasar(string $jenis, string $judul, array $filter, string $dicetakOleh, string $waktuCetak, int $jumlah): array
    {
        return [
            'jenis' => $jenis,
            'judul' => $judul,
            'orientasi' => in_array($jenis, ['rekap', 'pengembalian'], true) ? 'landscape' : 'portrait',
            'periode' => $this->periode($filter),
            'waktuCetak' => $waktuCetak,
            'dicetakOleh' => $dicetakOleh,
            'filterLabel' => $this->labelFilter($filter),
            'filterRingkas' => $this->ringkas($filter),
            'jumlahBaris' => $jumlah,
        ];
    }

    /** @param array<string, mixed> $filter */
    private function periode(array $filter): string
    {
        $dari = $filter['dari'] ?? null;
        $sampai = $filter['sampai'] ?? null;

        return match (true) {
            $dari && $sampai => "{$dari} s.d. {$sampai}",
            $dari => "Mulai {$dari}",
            $sampai => "Sampai {$sampai}",
            default => 'Semua periode',
        };
    }

    /** @param array<string, mixed> $filter */
    private function labelFilter(array $filter): array
    {
        $label = [];
        foreach (self::FILTER_REKAP as $kunci) {
            $nilai = $filter[$kunci] ?? null;
            if ($nilai === null || $nilai === '') {
                continue;
            }

            $label[] = [
                'label' => str_replace('_', ' ', ucfirst($kunci)),
                'nilai' => match ($kunci) {
                    'barang' => Item::withTrashed()->find($nilai)?->nama ?? (string) $nilai,
                    'kategori' => Category::find($nilai)?->nama ?? (string) $nilai,
                    'divisi' => Division::find($nilai)?->nama ?? (string) $nilai,
                    'tipe_peminjam' => $this->labelTipe((string) $nilai),
                    'status' => str_replace('_', ' ', (string) $nilai),
                    // Kata kunci bisa berisi telepon; nilainya sengaja tidak dicetak.
                    'q' => 'Diterapkan',
                    in_array($kunci, self::FILTER_BOOL, true) => filter_var($nilai, FILTER_VALIDATE_BOOLEAN) ? 'Ya' : 'Tidak',
                    default => (string) $nilai,
                },
            ];
        }

        return $label;
    }

    /** @param array<string, mixed> $filter */
    private function ringkas(array $filter): array
    {
        $hasil = [];
        foreach (self::FILTER_REKAP as $kunci) {
            if (! array_key_exists($kunci, $filter) || $filter[$kunci] === null || $filter[$kunci] === '') {
                continue;
            }

            $hasil[$kunci] = in_array($kunci, self::FILTER_BOOL, true)
                ? filter_var($filter[$kunci], FILTER_VALIDATE_BOOLEAN)
                : $filter[$kunci];
        }

        return $hasil;
    }

    private function jagaBatas(int $jumlah): void
    {
        $batas = (int) config('laporan.maks_baris', 2000);
        if ($jumlah > $batas) {
            throw new GalatBisnis(422, 'laporan_terlalu_besar', "Laporan memuat {$jumlah} baris, melebihi batas {$batas}. Persempit periode atau filter.");
        }
    }

    private function labelTipe(string $tipe): string
    {
        return match ($tipe) {
            'karyawan' => 'Karyawan',
            'magang' => 'Magang',
            default => $tipe,
        };
    }

    /** "Nama x qty" dipisah koma. $sisa=true memakai unit belum diklaim (BR-04). */
    private function daftarBarang(Loan $loan, bool $sisa): string
    {
        $rincian = $this->status->rincianItem($loan);
        $nama = [];

        foreach ($loan->items->sortBy('item_id') as $baris) {
            $qty = $sisa
                ? max(0, (int) ($rincian[$baris->id]['sisa'] ?? 0))
                : (int) $baris->qty_dipinjam;

            if ($qty <= 0) {
                continue;
            }

            $nama[] = ($baris->item?->nama ?? 'Barang').' x '.$qty;
        }

        return $nama === [] ? '—' : implode(', ', $nama);
    }
}
