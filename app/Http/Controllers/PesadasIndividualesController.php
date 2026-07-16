<?php

namespace App\Http\Controllers;
use App\Models\PesadaIndividual;
use App\Repository\PesadasLineasRepository;
use App\Services\SearchPesadasIndividuales;
use Illuminate\Http\Request;


class PesadasIndividualesController extends Controller
{
    /*
        Creo las pesadas individuales a partir de los registros de opc-ua
        Recalculamos el mes pasado los dias 1,2,3 despues solo el mes en curso
    */
    public function crearPesadasIndividuales(Request $request, PesadasLineasRepository $plr){     
        $time_in     = date('Y-m-d H:i:s');   
        $year        = date('Y');
        $month       = date('m');
        $current_day = date('d');

        if(in_array($current_day, [1, 2, 3])){
            $month = $month - 1;
            if($month == 0){
                $month = 12;
                $year  = $year - 1;
            }
            $plr->create_new($year, $month);
        }

        $year  = date('Y');
        $month = date('m');
        $plr->create_new($year, $month);

        $pi = PesadaIndividual::latest('id')->first();
        echo json_encode(['time_in' => $time_in , 'time_out' => date('Y-m-d H:i:s'), 'pi' => $pi]);
    }

    /*
        Devuelvo las pesadas indivuduales para el servidor 98 para el calculo de ritmos
    */
    public function getPesadasIndividuales(Request $request, SearchPesadasIndividuales $spi){
        $year  = (string) $request->query('year');
        $month = (string) $request->query('month');
        $res   = $spi->returnPesadasIndividuales($year, $month);
        return response()->json($res);
    }
}
