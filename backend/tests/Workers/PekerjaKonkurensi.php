<?php

use App\Exceptions\GalatBisnis;
use App\Services\PeminjamanService;
use App\Services\VerifikasiService;
use Illuminate\Contracts\Console\Kernel;

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return;
}

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$jenis = $argv[1] ?? null;
$payloadRaw = $argv[2] ?? '{}';
$barrierFile = $argv[3] ?? null;
$readyFile = $argv[4] ?? null;

$payload = json_decode($payloadRaw, true) ?: [];

// Sinyalkan bahwa pekerja sudah siap dan Laravel sudah boot
if ($readyFile) {
    file_put_contents($readyFile, 'siap');
}

// Tunggu hingga parent membuka kunci penghalang
if ($barrierFile && file_exists($barrierFile)) {
    $fp = fopen($barrierFile, 'r');
    if ($fp) {
        flock($fp, LOCK_SH);
        fclose($fp);
    }
}

try {
    if ($jenis === 'pinjam') {
        $loan = app(PeminjamanService::class)->buat($payload);
        echo json_encode([
            'sukses' => true,
            'status' => 200,
            'loan_id' => $loan->id,
            'kode_pinjam' => $loan->kode_pinjam,
        ]);
        exit(0);
    }

    if ($jenis === 'verifikasi') {
        $klaimId = (int) $payload['klaim_id'];
        $hasil = $payload['hasil'];
        $catatan = $payload['catatan_admin'] ?? null;
        $userId = (int) $payload['user_id'];

        $claim = app(VerifikasiService::class)->proses(
            $klaimId,
            $hasil,
            $catatan,
            $userId,
        );
        echo json_encode([
            'sukses' => true,
            'status' => 200,
            'claim_id' => $claim->id,
            'claim_status' => $claim->status,
        ]);
        exit(0);
    }

    throw new InvalidArgumentException("Jenis aksi tidak dikenal: {$jenis}");
} catch (GalatBisnis $e) {
    echo json_encode([
        'sukses' => false,
        'status' => $e->getStatusCode(),
        'kode' => $e->kode,
        'pesan' => $e->getMessage(),
    ]);
    exit(1);
} catch (Throwable $e) {
    echo json_encode([
        'sukses' => false,
        'status' => 500,
        'kode' => 'server_error',
        'pesan' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(2);
}
