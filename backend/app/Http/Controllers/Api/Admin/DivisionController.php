<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanDivisiRequest;
use App\Http\Requests\Admin\UbahDivisiRequest;
use App\Http\Resources\AdminDivisionResource;
use App\Models\Division;
use App\Services\DivisiService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DivisionController extends Controller
{
    public function __construct(private readonly DivisiService $divisi) {}

    public function index(): AnonymousResourceCollection
    {
        return AdminDivisionResource::collection($this->divisi->daftar());
    }

    public function simpan(SimpanDivisiRequest $request): AdminDivisionResource
    {
        return new AdminDivisionResource($this->divisi->buat($request->validated()));
    }

    public function ubah(UbahDivisiRequest $request, Division $divisi): AdminDivisionResource
    {
        return new AdminDivisionResource($this->divisi->ubah($divisi, $request->validated()));
    }
}
