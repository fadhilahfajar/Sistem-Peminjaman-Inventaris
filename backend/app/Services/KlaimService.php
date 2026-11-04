<?php

namespace App\Services;

use App\Exceptions\GalatBisnis;
use App\Models\Loan;
use App\Models\ReturnClaim;
use App\Support\Audit;
use App\Support\KodeAcak;
use App\Support\Tanggal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class KlaimService
{
    private const KONDISI = ['baik', 'rusak', 'hilang'];

    public function __construct(private StatusPeminjaman $status) {}

    /**
     * Membuat klaim pengembalian dari kiosk (BR-03, BR-04, BR-10, BR-20, BR-26).
     * Klaim tidak mengubah total, tersedia, maupun rusak. Unit pindah dari Dipinjam ke Menunggu Verifikasi.
     *
     * @param  array{kode_pinjam: string, idempotency_key: string, catatan_peminjam?: ?string, baris: list<array{item_id: int, baik: int, rusak: int, hilang: int}>}  $data
     */
    public function buat(array $data): ReturnClaim
    {
        $kunci = $this->kunci($data['idempotency_key'] ?? null);
        $n = $this->normalisasi($data);

        $lama = $this->cariLama($kunci);
        if ($lama !== null) {
            return $this->ulang($lama, $n);
        }

        $this->validasi($n);

        try {
            return DB::transaction(fn () => $this->simpan($n, $kunci), 3);
        } catch (UniqueConstraintViolationException $e) {
            $lama = $this->cariLama($kunci);

            if ($lama === null) {
                throw $e;
            }

            return $this->ulang($lama, $n);
        }
    }

    private function simpan(array $n, string $kunci): ReturnClaim
    {
        $loan = Loan::query()->where('kode_pinjam', $n['kode'])->lockForUpdate()->firstOrFail();
        $loan->load('items.item', 'items.claimLines.claim', 'claims', 'extensions');

        $status = $this->status->dariModel($loan, Tanggal::hariIni());

        if ($status['status'] === StatusPeminjaman::DIBATALKAN) {
            throw GalatBisnis::konflik('Peminjaman ini sudah dibatalkan dan tidak dapat diklaim.', 'peminjaman_dibatalkan');
        }

        if ($status['status'] === StatusPeminjaman::DIPESAN) {
            throw GalatBisnis::konflik('Pemesanan belum dimulai, jadi belum dapat diklaim.', 'pemesanan_belum_dimulai');
        }

        $sisa = $this->sisaPerLoanItem($loan);
        $loanItems = $loan->items->keyBy('item_id');
        $baris = $this->barisBerisi($n);

        foreach ($baris as $b) {
            if (! $loanItems->has($b['item_id'])) {
                throw ValidationException::withMessages(['baris' => ['Ada barang yang bukan bagian dari peminjaman ini.']]);
            }
        }

        foreach ($baris as $b) {
            $loanItem = $loanItems->get($b['item_id']);
            $jumlah = $b['baik'] + $b['rusak'] + $b['hilang'];

            if ($jumlah > $sisa[$loanItem->id]) {
                $nama = $loanItem->item?->nama ?? 'barang';

                throw GalatBisnis::konflik(
                    "Jumlah klaim {$nama} melebihi sisa ({$sisa[$loanItem->id]} unit).",
                    'klaim_melebihi_sisa',
                );
            }
        }

        $klaim = ReturnClaim::create([
            'kode_klaim' => $this->kodeUnik(),
            'loan_id' => $loan->id,
            'catatan_peminjam' => $n['catatan'],
            'hari_terlambat' => $status['hari_terlambat'],
            'status' => 'menunggu',
            'idempotency_key' => $kunci,
        ]);

        $totalUnit = 0;
        foreach ($baris as $b) {
            $loanItem = $loanItems->get($b['item_id']);

            foreach (self::KONDISI as $kondisi) {
                if ($b[$kondisi] > 0) {
                    $klaim->lines()->create([
                        'loan_item_id' => $loanItem->id,
                        'kondisi_dilaporkan' => $kondisi,
                        'qty' => $b[$kondisi],
                    ]);
                    $totalUnit += $b[$kondisi];
                }
            }
        }

        Audit::catat(
            aksi: 'buat_klaim',
            entitas: 'return_claims',
            entitasId: $klaim->id,
            userId: null,
            detail: [
                'loan_id' => $loan->id,
                'kode_klaim' => $klaim->kode_klaim,
                'jumlah_unit' => $totalUnit,
                'hari_terlambat' => $status['hari_terlambat'],
            ],
        );

        return $klaim->load('lines');
    }

    /** Sisa per loan_item (BR-04): qty_dipinjam dikurangi menunggu dan terverifikasi. Unit yang ditolak kembali menjadi sisa. */
    private function sisaPerLoanItem(Loan $loan): array
    {
        $sisa = [];

        foreach ($loan->items as $loanItem) {
            $menunggu = 0;
            $terverifikasi = 0;

            foreach ($loanItem->claimLines as $baris) {
                if ($baris->claim->status === 'menunggu') {
                    $menunggu += (int) $baris->qty;
                } elseif ($baris->claim->status === 'diverifikasi') {
                    $terverifikasi += (int) $baris->qty_baik + (int) $baris->qty_rusak + (int) $baris->qty_hilang;
                }
            }

            $sisa[$loanItem->id] = $loanItem->qty_dipinjam - $menunggu - $terverifikasi;
        }

        return $sisa;
    }

    private function validasi(array $n): void
    {
        $galat = [];

        if ($n['kode'] === '') {
            $galat['kode_pinjam'] = ['Kode pinjam wajib diisi.'];
        }

        if ($n['catatan'] !== null && mb_strlen($n['catatan']) > 500) {
            $galat['catatan_peminjam'] = ['Catatan maksimal 500 karakter.'];
        }

        if ($n['baris'] === []) {
            $galat['baris'] = ['Isi minimal satu barang yang dikembalikan.'];
        }

        foreach ($n['baris'] as $i => $b) {
            if ($b['item_id'] === null || $b['item_id'] < 1) {
                $galat["baris.{$i}.item_id"] = ['Barang tidak valid.'];
            }

            foreach (self::KONDISI as $kondisi) {
                if ($b[$kondisi] === null || $b[$kondisi] < 0) {
                    $galat["baris.{$i}.{$kondisi}"] = ['Jumlah harus bilangan bulat nol atau lebih.'];
                }
            }
        }

        if ($galat === []) {
            $ids = array_column($n['baris'], 'item_id');

            if (count($ids) !== count(array_unique($ids))) {
                $galat['baris'] = ['Barang yang sama tidak boleh muncul dua kali.'];
            } elseif ($this->barisBerisi($n) === []) {
                $galat['baris'] = ['Jumlah klaim harus lebih dari nol.'];
            }
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }
    }

    /** Baris yang jumlah kondisinya lebih dari nol. Hanya dipanggil setelah nilai divalidasi. */
    private function barisBerisi(array $n): array
    {
        return array_values(array_filter(
            $n['baris'],
            fn ($b) => ((int) $b['baik'] + (int) $b['rusak'] + (int) $b['hilang']) > 0,
        ));
    }

    /** Normalisasi longgar (tidak melempar), supaya sama dipakai untuk membandingkan permintaan ulang. */
    private function normalisasi(array $d): array
    {
        $teks = fn ($v) => is_scalar($v) ? trim((string) $v) : '';
        $bulat = fn ($v) => is_int($v) ? $v : ((is_string($v) && preg_match('/^-?[0-9]+$/', $v) === 1) ? (int) $v : null);

        $baris = [];
        foreach (is_array($d['baris'] ?? null) ? $d['baris'] : [] as $b) {
            $b = is_array($b) ? $b : [];
            $baris[] = [
                'item_id' => $bulat($b['item_id'] ?? null),
                'baik' => $bulat($b['baik'] ?? 0),
                'rusak' => $bulat($b['rusak'] ?? 0),
                'hilang' => $bulat($b['hilang'] ?? 0),
            ];
        }

        $catatan = $teks($d['catatan_peminjam'] ?? null);

        return [
            'kode' => Str::upper($teks($d['kode_pinjam'] ?? null)),
            'catatan' => $catatan === '' ? null : $catatan,
            'baris' => $baris,
        ];
    }

    private function kunci(mixed $nilai): string
    {
        if (! is_string($nilai) || ! Str::isUuid($nilai)) {
            throw ValidationException::withMessages(['idempotency_key' => ['Kunci idempotensi wajib berupa UUID.']]);
        }

        return strtolower($nilai);
    }

    private function cariLama(string $kunci): ?ReturnClaim
    {
        return ReturnClaim::with(['loan', 'lines.loanItem'])->where('idempotency_key', $kunci)->first();
    }

    private function ulang(ReturnClaim $lama, array $n): ReturnClaim
    {
        if (! $this->samaIsi($lama, $n)) {
            throw GalatBisnis::konflik(
                'Kunci idempotensi sudah dipakai oleh permintaan dengan isi berbeda.',
                'kunci_idempotensi_bentrok',
            );
        }

        return $lama;
    }

    private function samaIsi(ReturnClaim $lama, array $n): bool
    {
        $tersimpan = [];
        foreach ($lama->lines as $baris) {
            $tersimpan[(int) $baris->loanItem->item_id][$baris->kondisi_dilaporkan] = (int) $baris->qty;
        }

        $diminta = [];
        foreach ($this->barisBerisi($n) as $b) {
            foreach (self::KONDISI as $kondisi) {
                if ((int) $b[$kondisi] > 0) {
                    $diminta[(int) $b['item_id']][$kondisi] = (int) $b[$kondisi];
                }
            }
        }

        return $lama->loan->kode_pinjam === $n['kode']
            && $lama->catatan_peminjam === $n['catatan']
            && $this->urut($tersimpan) === $this->urut($diminta);
    }

    private function urut(array $peta): array
    {
        ksort($peta);

        foreach ($peta as &$kondisi) {
            ksort($kondisi);
        }

        return $peta;
    }

    private function kodeUnik(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $kode = KodeAcak::kodeKlaim();

            if (! ReturnClaim::where('kode_klaim', $kode)->exists()) {
                return $kode;
            }
        }

        throw new RuntimeException('Gagal membuat kode klaim yang unik.');
    }
}
