<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\RingkasanAdminService;

class SummaryController extends Controller
{
    public function __construct(private readonly RingkasanAdminService $ringkasan) {}

    public function index()
    {
        return response()->json(['data' => $this->ringkasan->ringkasan()]);
    }
}
