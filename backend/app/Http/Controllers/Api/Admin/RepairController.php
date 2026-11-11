<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminRepairResource;
use App\Services\BarangService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RepairController extends Controller
{
    public function __construct(private readonly BarangService $barangService) {}

    public function index(): AnonymousResourceCollection
    {
        return AdminRepairResource::collection($this->barangService->daftarPerbaikan());
    }
}
