<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AfkirRequest;
use App\Http\Requests\Admin\PenyesuaianStokRequest;
use App\Http\Requests\Admin\SelesaiPerbaikanRequest;
use App\Http\Requests\Admin\TandaiRusakRequest;
use App\Http\Resources\AdminItemResource;
use App\Models\Item;
use App\Services\StokService;

class ItemStockController extends Controller
{
    public function __construct(private readonly StokService $stokService) {}

    public function penyesuaian(PenyesuaianStokRequest $request, Item $item): AdminItemResource
    {
        $userId = $request->user()->id;
        $jumlah = (int) $request->validated('jumlah');
        $alasan = $request->validated('alasan');

        $updatedItem = $request->validated('arah') === 'tambah'
            ? $this->stokService->tambah($item->id, $jumlah, $userId, $alasan)
            : $this->stokService->kurangi($item->id, $jumlah, $userId, $alasan);

        return new AdminItemResource($updatedItem->load('kategori'));
    }

    public function tandaiRusak(TandaiRusakRequest $request, Item $item): AdminItemResource
    {
        $updatedItem = $this->stokService->tandaiRusak(
            $item->id,
            (int) $request->validated('jumlah'),
            $request->user()->id,
            $request->validated('alasan')
        );

        return new AdminItemResource($updatedItem->load('kategori'));
    }

    public function selesaiPerbaikan(SelesaiPerbaikanRequest $request, Item $item): AdminItemResource
    {
        $updatedItem = $this->stokService->selesaiPerbaikan(
            $item->id,
            (int) $request->validated('jumlah'),
            $request->user()->id,
            $request->validated('catatan')
        );

        return new AdminItemResource($updatedItem->load('kategori'));
    }

    public function afkir(AfkirRequest $request, Item $item): AdminItemResource
    {
        $updatedItem = $this->stokService->afkir(
            $item->id,
            (int) $request->validated('jumlah'),
            $request->user()->id,
            $request->validated('alasan')
        );

        return new AdminItemResource($updatedItem->load('kategori'));
    }
}
