<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\SimpanPeminjamanRequest;
use App\Http\Resources\LoanKioskResource;
use App\Services\PeminjamanService;
use Illuminate\Http\JsonResponse;

class LoanController extends Controller
{
    public function __construct(
        private readonly PeminjamanService $peminjaman,
    ) {}

    public function buat(SimpanPeminjamanRequest $request): JsonResponse
    {
        $loan = $this->peminjaman->buat($request->validated());

        return (new LoanKioskResource($loan))
            ->response()
            ->setStatusCode($loan->wasRecentlyCreated ? 201 : 200);
    }
}
