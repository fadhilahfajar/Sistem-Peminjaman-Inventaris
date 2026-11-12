<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanPenggunaRequest;
use App\Http\Requests\Admin\UbahPenggunaRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\PenggunaAdminService;

class UserController extends Controller
{
    public function __construct(private readonly PenggunaAdminService $pengguna) {}

    public function index()
    {
        return AdminUserResource::collection($this->pengguna->daftar());
    }

    public function simpan(SimpanPenggunaRequest $request): AdminUserResource
    {
        $user = $this->pengguna->buat(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
            $request->user()->id,
        );

        return new AdminUserResource($user);
    }

    public function ubah(UbahPenggunaRequest $request, User $user): AdminUserResource
    {
        $user = $this->pengguna->ubah($user, $request->validated(), $request->user()->id);

        return new AdminUserResource($user);
    }
}
