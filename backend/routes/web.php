<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $spa = public_path('index.html');

    if (is_file($spa)) {
        return response()->file($spa);
    }

    return view('welcome');
});
