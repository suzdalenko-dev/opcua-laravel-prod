<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class CalculateGastoMaquinas {
    public static function sum_kg_line3($fecha_desde, $articles){
        $kg = 0;
        /*
         * articles llega así:
         *
         * 61,54,8,6
         *
         * Lo convertimos en:
         *
         * ['61', '54', '8', '6']
         */
        $articleNumbers = array_values(
            array_filter(
                array_map('trim', explode(',', $articles)), function ($value) { return $value != '';}
            )
        );

        /*
         * Si no tenemos fecha o artículos no consultamos nada.
         */
        if ($fecha_desde === '' || count($articleNumbers) === 0) {
            return 0;
        }

        /*
         * Sumamos todas las pesadas individuales de línea 3
         * desde la fecha indicada y solamente para los artículos
         * solicitados.
         */
        $kg = DB::table('pesadas_individuales')->where('creacion_date', '>=', $fecha_desde)->whereIn('article_code', $articleNumbers)->sum('weight_value');
        return $kg;
    }
}