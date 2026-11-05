<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanKategoriRequest;
use App\Http\Requests\Admin\UbahKategoriRequest;
use App\Http\Resources\AdminCategoryResource;
use App\Models\Category;
use App\Services\KategoriService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(private readonly KategoriService $kategori) {}

    public function index(): AnonymousResourceCollection
    {
        return AdminCategoryResource::collection($this->kategori->daftar());
    }

    public function simpan(SimpanKategoriRequest $request): AdminCategoryResource
    {
        return new AdminCategoryResource($this->kategori->buat($request->validated()));
    }

    public function ubah(UbahKategoriRequest $request, Category $kategori): AdminCategoryResource
    {
        return new AdminCategoryResource($this->kategori->ubah($kategori, $request->validated()));
    }
}
