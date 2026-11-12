<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DaftarPeminjamanRequest;
use App\Http\Requests\Admin\KoreksiPeminjamanRequest;
use App\Http\Requests\Admin\PembatalanAdminRequest;
use App\Http\Requests\Admin\PerpanjanganAdminRequest;
use App\Http\Resources\AdminLoanDetailResource;
use App\Http\Resources\AdminLoanResource;
use App\Models\Loan;
use App\Services\KoreksiService;
use App\Services\PembatalanService;
use App\Services\PeminjamanAdminService;
use App\Services\PerpanjanganService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoanController extends Controller
{
    public function __construct(
        private readonly PeminjamanAdminService $peminjamanService,
        private readonly KoreksiService $koreksiService,
        private readonly PerpanjanganService $perpanjanganService,
        private readonly PembatalanService $pembatalanService,
    ) {}

    public function index(DaftarPeminjamanRequest $request): AnonymousResourceCollection
    {
        $loans = $this->peminjamanService->daftar(
            $request->validated(),
            (int) ($request->input('page_size') ?: 25),
        );

        return AdminLoanResource::collection($loans);
    }

    public function tampil(Loan $loan): AdminLoanDetailResource
    {
        return new AdminLoanDetailResource($this->peminjamanService->detail($loan));
    }

    public function koreksi(KoreksiPeminjamanRequest $request, Loan $loan): AdminLoanDetailResource
    {
        $hasil = $this->koreksiService->koreksi(
            $loan->id,
            $request->user()->id,
            $request->validated(),
        );

        return new AdminLoanDetailResource($this->peminjamanService->detail($hasil));
    }

    public function perpanjang(PerpanjanganAdminRequest $request, Loan $loan): AdminLoanDetailResource
    {
        $hasil = $this->perpanjanganService->olehAdmin(
            $loan->id,
            (int) $request->validated('tambahan_hari'),
            $request->user()->id,
            $request->validated('alasan'),
        );

        return new AdminLoanDetailResource($this->peminjamanService->detail($hasil));
    }

    public function batal(PembatalanAdminRequest $request, Loan $loan): AdminLoanDetailResource
    {
        $hasil = $this->pembatalanService->olehAdmin(
            $loan->id,
            $request->user()->id,
            $request->validated('alasan'),
        );

        return new AdminLoanDetailResource($this->peminjamanService->detail($hasil));
    }
}
