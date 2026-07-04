<?php

namespace App\Http\Controllers;
use App\Repository\PesadasLineasRepository;
use Illuminate\Http\Request;


class PesadasIndividualesController extends Controller
{
    public function crearPesadasIndividuales(Request $request, PesadasLineasRepository $plr){
        $year  = (string) $request->query('__year');
        $month = (string) $request->query('__month');
        $plr->create_new($year, $month);
    }
}
