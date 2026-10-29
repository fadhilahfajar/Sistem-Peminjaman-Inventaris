<?php

use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mengisi divisi awal IT, Media, EO yang aktif', function () {
    $this->seed();

    expect(Division::orderBy('nama')->pluck('nama')->all())->toBe(['EO', 'IT', 'Media']);
    expect(Division::where('aktif', false)->count())->toBe(0);
});

it('mengisi kategori awal hanya Tanpa Kategori', function () {
    $this->seed();

    expect(Category::pluck('nama')->all())->toBe(['Tanpa Kategori']);
});

it('mengisi pengaturan tetapi tidak membuat data bisnis atau akun', function () {
    $this->seed();

    expect(Setting::count())->toBe(8);
    expect(User::count())->toBe(0);
    expect(Item::count())->toBe(0);
    expect(Loan::count())->toBe(0);
});

it('aman dijalankan ulang tanpa menggandakan data', function () {
    $this->seed();
    $this->seed();

    expect(Division::count())->toBe(3);
    expect(Category::count())->toBe(1);
    expect(Setting::count())->toBe(8);
});
