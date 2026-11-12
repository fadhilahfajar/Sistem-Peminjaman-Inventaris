<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DaftarBarangRequest;
use App\Http\Requests\Admin\SimpanBarangRequest;
use App\Http\Requests\Admin\UbahBarangRequest;
use App\Http\Resources\AdminItemResource;
use App\Models\Item;
use App\Services\BarangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ItemController extends Controller
{
    public function __construct(private readonly BarangService $barangService) {}

    public function index(DaftarBarangRequest $request): AnonymousResourceCollection
    {
        $items = $this->barangService->daftar(
            q: $request->input('q'),
            kategoriId: $request->filled('kategori') ? (int) $request->input('kategori') : null,
            pageSize: (int) ($request->input('page_size') ?: 25),
        );

        return AdminItemResource::collection($items);
    }

    public function simpan(SimpanBarangRequest $request): JsonResponse
    {
        $item = $this->barangService->buat(
            $request->validated(),
            $request->user()->id,
        );

        return (new AdminItemResource($item))
            ->response()
            ->setStatusCode(201);
    }

    public function tampil(Item $item): AdminItemResource
    {
        return new AdminItemResource($item->load('kategori'));
    }

    public function ubah(UbahBarangRequest $request, Item $item): AdminItemResource
    {
        $itemDiubah = $this->barangService->ubah(
            $item,
            $request->validated(),
            $request->user()->id,
        );

        return new AdminItemResource($itemDiubah);
    }

    public function hapus(Item $item): JsonResponse
    {
        $this->barangService->hapus($item, request()->user()->id);

        return response()->json(['pesan' => 'Barang berhasil dihapus.']);
    }
}
