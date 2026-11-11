<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DaftarKlaimRequest;
use App\Http\Requests\Admin\VerifikasiKlaimRequest;
use App\Http\Resources\AdminReturnClaimResource;
use App\Models\ReturnClaim;
use App\Services\KlaimAdminService;
use App\Services\VerifikasiService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReturnClaimController extends Controller
{
    public function __construct(
        private readonly KlaimAdminService $klaimService,
        private readonly VerifikasiService $verifikasiService,
    ) {}

    public function index(DaftarKlaimRequest $request): AnonymousResourceCollection
    {
        $klaim = $this->klaimService->daftar(
            $request->validated(),
            (int) ($request->input('page_size') ?: 25),
        );

        return AdminReturnClaimResource::collection($klaim);
    }

    public function tampil(ReturnClaim $klaim): AdminReturnClaimResource
    {
        return new AdminReturnClaimResource($this->klaimService->detail($klaim));
    }

    public function verifikasi(VerifikasiKlaimRequest $request, ReturnClaim $klaim): AdminReturnClaimResource
    {
        $hasil = $this->verifikasiService->proses(
            $klaim->id,
            $request->validated('lines'),
            $request->validated('catatan_admin'),
            $request->user()->id,
        );

        return new AdminReturnClaimResource($this->klaimService->detail($hasil));
    }
}
