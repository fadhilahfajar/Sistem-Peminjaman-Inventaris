<?php

namespace App\Services;

use App\Exceptions\GalatBisnis;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Support\Audit;
use App\Support\BukuBesarStok;
use App\Support\KodeAcak;
use App\Support\Tanggal;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PeminjamanService
{
    public function __construct(private PengaturanService $pengaturan) {}

    /**
     * Membuat peminjaman atau pemesanan dari kiosk (DESAIN 7.1, BR-02, BR-20, BR-22, BR-23).
     *
     * @param  array{nama_peminjam: string, division_id: int, tipe_peminjam: string, telepon?: ?string, tanggal_pinjam: string, batas_kembali: string, idempotency_key: string, items: list<array{item_id: int, qty: int}>}  $data
     */
    public function buat(array $data): Loan
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
            // Dua permintaan dengan kunci sama bersamaan: yang kalah mengambil hasil yang menang.
            $lama = $this->cariLama($kunci);

            if ($lama === null) {
                throw $e;
            }

            return $this->ulang($lama, $n);
        }
    }

    private function simpan(array $n, string $kunci): Loan
    {
        $loan = Loan::create([
            'kode_pinjam' => $this->kodeUnik(),
            'nama_peminjam' => $n['nama'],
            'division_id' => $n['division_id'],
            'tipe_peminjam' => $n['tipe'],
            'telepon' => $n['telepon'],
            'tanggal_pinjam' => $n['tanggal_pinjam'],
            'batas_kembali_awal' => $n['batas_kembali'],
            'batas_kembali' => $n['batas_kembali'],
            'idempotency_key' => $kunci,
        ]);

        // Urutan kunci: loans dulu, lalu items menurut id menaik (AGENTS 5).
        $urut = collect($n['items'])->sortBy('item_id')->values();

        foreach ($urut as $it) {
            $item = Item::query()->whereKey($it['item_id'])->lockForUpdate()->first();

            if ($item === null) {
                throw ValidationException::withMessages(['items' => ['Barang tidak ditemukan atau sudah dihapus.']]);
            }

            if ($item->tersedia < $it['qty']) {
                throw GalatBisnis::konflik(
                    "Stok barang '{$item->nama}' tidak mencukupi atau sudah berubah (tersedia {$item->tersedia}). Muat ulang lalu coba lagi.",
                    'stok_tidak_cukup',
                );
            }

            $loan->items()->create(['item_id' => $item->id, 'qty_dipinjam' => $it['qty']]);
            $this->catatPinjam($item, $it['qty'], $loan->id);
        }

        Audit::catat('buat_peminjaman', 'loans', $loan->id, null, [
            'kode_pinjam' => $loan->kode_pinjam,
            'jumlah_barang' => $urut->count(),
            'pemesanan' => $n['tanggal_pinjam'] > Tanggal::hariIni(),
        ]);

        return $loan->load(['items.item', 'divisi']);
    }

    /**
     * Mengurangi tersedia lalu mencatat mutasi PINJAM di transaksi yang sama (AGENTS 4).
     * $item sudah dikunci lockForUpdate dan stoknya sudah diperiksa oleh pemanggil.
     * BukuBesarStok::catat hanya menulis stock_movements, jadi items diubah di sini.
     */
    private function catatPinjam(Item $item, int $qty, int $loanId): void
    {
        $item->decrement('tersedia', $qty);

        BukuBesarStok::catat($item, 'PINJAM', 0, -$qty, 0, 'loans', $loanId);
    }

    private function validasi(array $n): void
    {
        $hariIni = Tanggal::hariIni();
        $maksBooking = $this->pengaturan->angka('maks_booking_hari');
        $maksDurasi = $this->pengaturan->angka('maks_durasi_awal_hari');
        $galat = [];

        $panjang = mb_strlen($n['nama']);
        if ($panjang < 2 || $panjang > 100) {
            $galat['nama_peminjam'][] = 'Nama harus 2 sampai 100 karakter.';
        }

        if (! in_array($n['tipe'], ['karyawan', 'magang'], true)) {
            $galat['tipe_peminjam'][] = 'Tipe peminjam wajib dipilih (karyawan atau magang).';
        }

        if ($n['telepon'] !== null && preg_match('/^[0-9]{8,15}$/', $n['telepon']) !== 1) {
            $galat['telepon'][] = 'Telepon harus 8 sampai 15 digit.';
        }

        if ($n['division_id'] === null || ! Division::whereKey($n['division_id'])->where('aktif', 1)->exists()) {
            $galat['division_id'][] = 'Divisi wajib dipilih dari daftar divisi aktif.';
        }

        $tglSah = $this->tanggalSah($n['tanggal_pinjam']);
        $batasSah = $this->tanggalSah($n['batas_kembali']);

        if (! $tglSah) {
            $galat['tanggal_pinjam'][] = 'Tanggal pinjam harus berformat YYYY-MM-DD dan ada di kalender.';
        } elseif ($n['tanggal_pinjam'] < $hariIni) {
            $galat['tanggal_pinjam'][] = 'Tanggal pinjam tidak boleh di masa lalu.';
        } elseif ($n['tanggal_pinjam'] > $this->tambahHari($hariIni, $maksBooking)) {
            $galat['tanggal_pinjam'][] = "Tanggal pinjam paling jauh {$maksBooking} hari dari hari ini.";
        }

        if (! $batasSah) {
            $galat['batas_kembali'][] = 'Batas kembali harus berformat YYYY-MM-DD dan ada di kalender.';
        } elseif ($tglSah && $n['batas_kembali'] < $n['tanggal_pinjam']) {
            $galat['batas_kembali'][] = 'Batas kembali tidak boleh sebelum tanggal pinjam.';
        } elseif ($tglSah && $n['batas_kembali'] > $this->tambahHari($n['tanggal_pinjam'], $maksDurasi)) {
            $galat['batas_kembali'][] = "Batas kembali paling jauh {$maksDurasi} hari dari tanggal pinjam.";
        }

        if ($n['items'] === []) {
            $galat['items'][] = 'Pilih minimal satu barang.';
        }

        $dilihat = [];
        foreach ($n['items'] as $i => $it) {
            if ($it['item_id'] === null) {
                $galat["items.{$i}.item_id"][] = 'Barang tidak valid.';
            } elseif (isset($dilihat[$it['item_id']])) {
                $galat["items.{$i}.item_id"][] = 'Barang yang sama tidak boleh muncul dua kali.';
            } else {
                $dilihat[$it['item_id']] = $i;
            }

            if ($it['qty'] === null || $it['qty'] < 1) {
                $galat["items.{$i}.qty"][] = 'Jumlah harus bilangan bulat minimal 1.';
            }
        }

        if ($dilihat !== []) {
            $ada = Item::whereIn('id', array_keys($dilihat))->pluck('id')->all();

            foreach ($dilihat as $itemId => $i) {
                if (! in_array($itemId, $ada, true)) {
                    $galat["items.{$i}.item_id"][] = 'Barang tidak ditemukan atau sudah dihapus.';
                }
            }
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }
    }

    /** Normalisasi longgar (tidak melempar), supaya sama dipakai untuk membandingkan permintaan ulang. */
    private function normalisasi(array $d): array
    {
        $teks = fn ($v) => is_scalar($v) ? trim((string) $v) : '';
        $bulat = fn ($v) => is_int($v) ? $v : ((is_string($v) && preg_match('/^-?[0-9]+$/', $v) === 1) ? (int) $v : null);

        $items = [];
        foreach (is_array($d['items'] ?? null) ? $d['items'] : [] as $it) {
            $it = is_array($it) ? $it : [];
            $items[] = ['item_id' => $bulat($it['item_id'] ?? null), 'qty' => $bulat($it['qty'] ?? null)];
        }

        $telepon = $teks($d['telepon'] ?? null);

        return [
            'nama' => $teks($d['nama_peminjam'] ?? null),
            'division_id' => $bulat($d['division_id'] ?? null),
            'tipe' => $teks($d['tipe_peminjam'] ?? null),
            'telepon' => $telepon === '' ? null : $telepon,
            'tanggal_pinjam' => $teks($d['tanggal_pinjam'] ?? null),
            'batas_kembali' => $teks($d['batas_kembali'] ?? null),
            'items' => $items,
        ];
    }

    private function kunci(mixed $nilai): string
    {
        if (! is_string($nilai) || ! Str::isUuid($nilai)) {
            throw ValidationException::withMessages(['idempotency_key' => ['Kunci idempotensi wajib berupa UUID.']]);
        }

        return strtolower($nilai);
    }

    private function cariLama(string $kunci): ?Loan
    {
        return Loan::with('items')->where('idempotency_key', $kunci)->first();
    }

    private function ulang(Loan $lama, array $n): Loan
    {
        if (! $this->samaIsi($lama, $n)) {
            throw GalatBisnis::konflik(
                'Kunci idempotensi sudah dipakai oleh permintaan dengan isi berbeda.',
                'kunci_idempotensi_bentrok',
            );
        }

        return $lama->load(['items.item', 'divisi']);
    }

    private function samaIsi(Loan $lama, array $n): bool
    {
        $tersimpan = $lama->items->mapWithKeys(fn ($li) => [(int) $li->item_id => (int) $li->qty_dipinjam])->sortKeys()->all();
        $diminta = collect($n['items'])->mapWithKeys(fn ($it) => [$it['item_id'] => $it['qty']])->sortKeys()->all();

        return $tersimpan === $diminta
            && $lama->nama_peminjam === $n['nama']
            && (int) $lama->division_id === $n['division_id']
            && $lama->tipe_peminjam === $n['tipe']
            && $lama->telepon === $n['telepon']
            && $lama->tanggal_pinjam->format('Y-m-d') === $n['tanggal_pinjam']
            && $lama->batas_kembali_awal->format('Y-m-d') === $n['batas_kembali'];
    }

    private function kodeUnik(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $kode = KodeAcak::kodePinjam();

            if (! Loan::where('kode_pinjam', $kode)->exists()) {
                return $kode;
            }
        }

        throw new RuntimeException('Gagal membuat kode pinjam yang unik.');
    }

    private function tanggalSah(string $tanggal): bool
    {
        if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $tanggal, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private function tambahHari(string $tanggal, int $hari): string
    {
        return (new DateTimeImmutable($tanggal, new DateTimeZone('Asia/Jakarta')))
            ->modify("+{$hari} days")
            ->format('Y-m-d');
    }
}
