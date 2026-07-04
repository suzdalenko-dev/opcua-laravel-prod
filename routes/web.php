<?php

use Illuminate\Support\Facades\Route;

Route::get('/web', function () {
    return response()->json([
        'ok' => true,
        'message' => 'API funcionando',
    ]);
});