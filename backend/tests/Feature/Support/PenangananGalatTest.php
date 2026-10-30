<?php

use App\Support\PenangananGalat;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function galatApi(Throwable $e): array
{
    $r = PenangananGalat::tangani($e, Request::create('/api/uji', 'GET'));

    return ['status' => $r->getStatusCode(), 'isi' => $r->getData(true)];
}

it('memetakan status ke bentuk {kode, pesan}', function (Throwable $e, int $status, string $kode) {
    $hasil = galatApi($e);

    expect($hasil['status'])->toBe($status)
        ->and($hasil['isi']['kode'])->toBe($kode)
        ->and($hasil['isi'])->toHaveKeys(['kode', 'pesan'])
        ->and($hasil['isi'])->not->toHaveKey('errors');
})->with([
    '401' => [fn () => new AuthenticationException, 401, 'tidak_terautentikasi'],
    '403' => [fn () => new AccessDeniedHttpException, 403, 'dilarang'],
    '404' => [fn () => new NotFoundHttpException, 404, 'tidak_ditemukan'],
    '409' => [fn () => new ConflictHttpException, 409, 'konflik'],
    '419' => [fn () => new HttpException(419), 419, 'sesi_kedaluwarsa'],
    '429' => [fn () => new HttpException(429), 429, 'terlalu_banyak_percobaan'],
]);

it('422 menyertakan errors per kolom', function () {
    $v = ValidationException::withMessages(['nama' => ['Nama wajib diisi.']]);

    $hasil = galatApi($v);

    expect($hasil['status'])->toBe(422)
        ->and($hasil['isi']['kode'])->toBe('validasi_gagal')
        ->and($hasil['isi']['errors'])->toBe(['nama' => ['Nama wajib diisi.']]);
});

it('500 tidak membocorkan pesan, kelas, atau jejak internal', function () {
    $hasil = galatApi(new RuntimeException('SELECT * FROM users password=rahasia'));
    $json = json_encode($hasil['isi']);

    expect($hasil['status'])->toBe(500)
        ->and($hasil['isi']['kode'])->toBe('galat_server')
        ->and($json)->not->toContain('rahasia')
        ->and($json)->not->toContain('RuntimeException')
        ->and($json)->not->toContain('trace');
});

it('tidak mengubah penanganan rute non-API', function () {
    $r = PenangananGalat::tangani(new NotFoundHttpException, Request::create('/halaman', 'GET'));

    expect($r)->toBeNull();
});
