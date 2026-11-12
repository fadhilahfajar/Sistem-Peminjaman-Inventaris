<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('T-01: semua rute /api/admin menolak 401 tanpa sesi (dinamis)', function () {
    $ruteAdmin = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($rute) => str_starts_with($rute->uri(), 'api/admin'));

    expect($ruteAdmin)->not->toBeEmpty();

    foreach ($ruteAdmin as $rute) {
        $uri = preg_replace('/\{[^}]+\}/', '1', $rute->uri());
        $metode = array_diff($rute->methods(), ['HEAD', 'OPTIONS']);

        foreach ($metode as $m) {
            $method = strtolower($m);
            $this->withHeaders(['Accept' => 'application/json'])
                ->{$method}('/'.$uri)
                ->assertStatus(401);
        }
    }
});
