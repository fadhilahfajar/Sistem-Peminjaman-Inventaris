<?php

use Barryvdh\DomPDF\Facade\Pdf;

test('render PDF menghasilkan berkas PDF yang valid', function () {
    $pdf = Pdf::loadView('laporan.uji', dataUjiPdfDasar())->output();

    expect(str_starts_with($pdf, '%PDF'))->toBeTrue()
        ->and(strlen($pdf))->toBeGreaterThan(1024);
});

test('templat laporan hanya menampilkan data ter-escape', function () {
    $html = view('laporan.uji', dataUjiPdfDasar())->render();

    expect($html)->toContain('&lt;script&gt;')
        ->and($html)->not->toContain('<script>')
        ->and($html)->toContain('Laptop é')
        ->and($html)->toContain('Amat &amp; aman');
});

test('opsi dompdf aplikasi mematikan akses berbahaya', function () {
    $opsi = app('dompdf')->getOptions();

    expect($opsi->isRemoteEnabled())->toBeFalse()
        ->and($opsi->isPhpEnabled())->toBeFalse()
        ->and($opsi->isJavascriptEnabled())->toBeFalse();
});
