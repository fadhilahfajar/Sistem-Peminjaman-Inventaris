<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\KatalogRequest;
use App\Http\Resources\ItemKioskResource;
use App\Models\Item;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ItemController extends Controller
{
    public function index(KatalogRequest $request): AnonymousResourceCollection
    {
        $items = Item::query()
            ->katalogKiosk($request->validated('q'), $request->validated('kategori'))
            ->get();

        return ItemKioskResource::collection($items);
    }
}
