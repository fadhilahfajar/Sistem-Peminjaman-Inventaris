<?php

use App\Http\Requests\Kiosk\MemilikiKunciIdempotensi;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

it('memvalidasi aturan kunci idempotensi', function () {
    $obj = new class
    {
        use MemilikiKunciIdempotensi;

        public function aturan(): array
        {
            return $this->aturanIdempotensi();
        }

        public function pesan(): array
        {
            return $this->pesanIdempotensi();
        }
    };

    $validatorGagal = Validator::make([], $obj->aturan(), $obj->pesan());
    expect($validatorGagal->fails())->toBeTrue()
        ->and($validatorGagal->errors()->first('idempotency_key'))->toBe('Kunci idempotensi wajib diisi.');

    $validatorBukanUuid = Validator::make(['idempotency_key' => 'bukan-uuid'], $obj->aturan(), $obj->pesan());
    expect($validatorBukanUuid->fails())->toBeTrue()
        ->and($validatorBukanUuid->errors()->first('idempotency_key'))->toBe('Kunci idempotensi harus berupa format UUID yang valid.');

    $validatorLolos = Validator::make([
        'idempotency_key' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
    ], $obj->aturan(), $obj->pesan());
    expect($validatorLolos->passes())->toBeTrue();
});
