<?php
namespace App\Repository;

use App\Services\CreacionPesadasIndividuales;
use Illuminate\Support\Facades\DB;

class PesadasLineasRepository{

    public function create_new(string $year, string $month){

    # Rescatamos las pesadas en bruto de linea 3
    $year_to    = (int) $year;
    $month_from = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
    $month_to   = (int) $month + 1;
    if($month_to == 13) {
        $month_to = 1;
        $year_to++;
    }
    $month_to = str_pad((string) $month_to, 2, '0', STR_PAD_LEFT);

    $date_from = $year.'-'.$month_from.'-01 00:00:00';
    $date_to   = $year_to.'-'.$month_to.'-01 00:00:00';

    $ps_lines = DB::select(
        'SELECT * 
        FROM pesadora_lineas pl
        WHERE pl.fin_of >= ? AND pl.fin_of < ?
           -- and pl.bolsas_buenas > 0 and pl.kg > 0 -- comentado de momento, luego lo quito
        ORDER BY pl.fin_of ASC, pl.id ASC',
       [$date_from, $date_to] 
    );

    /*
        Revisemos haber si hay pesadas acumuladas que pasan de un mes para otro con lo que podrian volver a imputarse
        Solucion aqui
    */
    $primera_linea_mes    = $ps_lines[0] ?? null;
    $ultima_linea_mes_ant = DB::selectOne(
        'SELECT *
        FROM pesadora_lineas
        WHERE fin_of < ?
        ORDER BY fin_of DESC, id DESC
        LIMIT 1', 
        [$date_from]
    );
    $continua_of_mes_ant = false;
    if($primera_linea_mes != null && $ultima_linea_mes_ant != null){
        $continua_of_mes_ant = (string) $primera_linea_mes->art_erp == (string) $ultima_linea_mes_ant->art_erp 
            && (string) $primera_linea_mes->inicio_of == (string) $ultima_linea_mes_ant->inicio_of
            && (string) $primera_linea_mes->inicio_of != '' && (string) $primera_linea_mes->inicio_of != '0000-00-00 00:00:00';
    }


    // var_dump($ps_lines);

  
    $work_data        = []; 

    /* Busco las producciones individuales */
    foreach($ps_lines as $line){
        $leyenda = $line->inicio_of.'__'.$line->art_erp;
        
        /* Si no existe la produccion creamo el grupo */
        if(!isset($work_data[$leyenda]) ){
            $work_data[$leyenda] = [
                'leyenda' => $leyenda,
                'lines'   => [] 
            ];   
        }
        /* Añadimos la linea de produccion */
        $work_data[$leyenda]['lines'][] = $line;  

    }

    $es_primer_grupo = true;

    foreach($work_data as $obj){
        /*
            Solamente la primea OF del mes puede ser continuacion de la ultima OF del mes anterior
        */
        if($es_primer_grupo && $continua_of_mes_ant){
            CreacionPesadasIndividuales::procesarProduccionNormal($obj, $ultima_linea_mes_ant);
            $es_primer_grupo = false;
            continue;
        }

        /*
            OF Nueva con 1 solo registro acumulado
        */
        if(count($obj['lines']) == 1 ){
            // creamos las pesadas desde unico regisro acumulado y destribuyendo entre la fecha inicio y fecha fin
            CreacionPesadasIndividuales::crearDesdeUnicoRegistro($obj['lines'][0]);
            $es_primer_grupo = false;
            continue;
        }

        /*
            OF Nueva con varios registros acumulados
            Creamos pesadas individuales si hay una bolsa=1 pesada, si es acumulado->destribuido
        */
        CreacionPesadasIndividuales::procesarProduccionNormal($obj);
        $es_primer_grupo = false;
    } 


    return response()->json([
            'work_data' => $work_data,
            'ps_lines'=> $ps_lines
        ]);
    }
}
