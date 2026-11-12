<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PerbaruiPengaturanRequest;
use App\Services\PengaturanAdminService;

class SettingController extends Controller
{
    public function __construct(private readonly PengaturanAdminService $pengaturan) {}

    public function index()
    {
        return response()->json(['data' => $this->pengaturan->daftar()]);
    }

    public function perbarui(PerbaruiPengaturanRequest $request)
    {
        $hasil = $this->pengaturan->perbarui($request->validated(), $request->user()->id);

        return response()->json(['data' => $hasil]);
    }
}
