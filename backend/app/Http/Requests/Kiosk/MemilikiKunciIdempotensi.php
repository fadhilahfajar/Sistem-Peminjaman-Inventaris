<?php

namespace App\Http\Requests\Kiosk;

/**
 * Trait untuk Form Request kiosk yang membutuhkan kunci idempotensi (UUID).
 * Logika pencegahan duplikat ditangani di Service domain (BR-20).
 */
trait MemilikiKunciIdempotensi
{
    /** @return array<string, array<int, string>> */
    protected function aturanIdempotensi(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    protected function pesanIdempotensi(): array
    {
        return [
            'idempotency_key.required' => 'Kunci idempotensi wajib diisi.',
            'idempotency_key.string' => 'Kunci idempotensi harus berupa teks.',
            'idempotency_key.uuid' => 'Kunci idempotensi harus berupa format UUID yang valid.',
        ];
    }
}
