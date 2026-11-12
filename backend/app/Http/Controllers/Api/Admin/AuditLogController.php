<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DaftarAuditRequest;
use App\Http\Resources\AdminAuditLogResource;
use App\Services\AuditAdminService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditAdminService $audit) {}

    public function index(DaftarAuditRequest $request): AnonymousResourceCollection
    {
        $logs = $this->audit->daftar($request->validated(), (int) ($request->input('page_size') ?: 25));

        return AdminAuditLogResource::collection($logs);
    }
}
