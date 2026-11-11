<?php

use Illuminate\Support\Facades\Route;

// A API não serve páginas: o site é o Next.js (apps/web).
Route::get('/', fn () => response()->json(['name' => 'Dole Leilão API', 'docs' => url('/docs/api')]));
