<?php

use App\Http\Controllers\PesadasIndividualesController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {

    return response()->json([
        'ok' => true,
        'message' => 'API funcionando API ROUTE',
    ]);
});


# calculation-rhythm-production-lines
# sudo -u postgres psql -d opcua_prod
# psql -h 127.0.0.1 -p 5432 -U springuser -d opcua_prod
# http://192.168.14.1/api/create-individual-weights?__year=2026&__month=06

Route::get('/create-individual-weights', [PesadasIndividualesController::class, 'crearPesadasIndividuales']);

# http://192.168.14.1/api/get-pesadas-individuales?year=2026&month=06
Route::get('/get-pesadas-individuales', [PesadasIndividualesController::class, 'getPesadasIndividuales']);


