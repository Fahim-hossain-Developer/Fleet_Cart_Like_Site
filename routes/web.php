<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'themes.default.home')->name('home');
Route::post('/locale', function (Request $request) {
    $data = $request->validate(['locale' => 'required|in:en,bn']);
    $request->session()->put('locale', $data['locale']);

    return redirect()->route('home');
})->middleware('throttle:30,1')->name('locale.update');
