<?php
namespace App\Services;
use App\Models\PesadaIndividual;

class CreacionPesadasIndividuales{
    /*
        Cuando las pesadas acumuladas solo tienen 1 registro
        Insertare las pesadas individuales correspondientes de este registro
    */
    public static function crearDesdeUnicoRegistro(\stdClass $line){
        if (isset($line->bolsas_buenas) && (int) $line->bolsas_buenas > 0 && (float) $line->kg > 0 
                && (string) $line->fin_of >= (string) $line->inicio_of
                && (string) $line->inicio_of != '0000-00-00 00:00:00' && (string) $line->fin_of != '0000-00-00 00:00:00'  ){

            $bolsas      = (int) $line->bolsas_buenas;
            $kg          = (float) $line->kg;
            $inicio_time = strtotime((string) $line->inicio_of);
            $fin_time    = strtotime((string) $line->fin_of);
            $diff_time   = $fin_time - $inicio_time;
            $weigth_step = $kg / $bolsas;

            if($inicio_time > $fin_time){
                file_put_contents('log/alarma.log', date('Y-m-d H:i:s').' ACUMULADO UNICO 2 '.json_encode($line).PHP_EOL, FILE_APPEND);
                return;
            }

            for($i=1; $i <= $bolsas; $i++){
                
                $second_from_start = intdiv($diff_time * $i, $bolsas);    // sumamos el paso del tiempo
                $pesada_time       = $inicio_time + $second_from_start;
                $ref_id = $line->id.'-'.$i;
                /*  Comentado de momento, luego quitare el comentario.. */
                PesadaIndividual::updateOrCreate(
                    ['ref_id' => $ref_id],
                    [
                        'weight_value'  => $weigth_step,
                        'creacion_date' => date('Y-m-d H:i:s', $pesada_time),
                        'article_code'  => $line->art_erp,
                        'article_name'  => $line->art_name
                    ]
                );
            
            }

        } else {
            file_put_contents('log/alarma.log', date('Y-m-d H:i:s').' ACUMULADO UNICO 1 '.json_encode($line).PHP_EOL, FILE_APPEND);
        }

        // dump($line);
    }



    /*
        Creamos pesadas individuales si hay una bolsa=1 pesada, si es acumulado->destribuido
    */
    public static function procesarProduccionNormal(array $wProd, ?\stdClass $linea_anterior_inicio=null): void{
        $lines = $wProd['lines'];

        if(count($lines) == 0){
            return;
        }

        /*
            La OF viene del mes anterior. Comprobamos la primera linea del mes actual con la ultima linea del mes anterior
        */
        if($linea_anterior_inicio != null){
            $linea_anterior = $linea_anterior_inicio;
            $trabajo_index  = 0;
        
        } else {
            /*
                OF Nueva del mes. El primer acumulado se procesa desde el inicio_of
            */
            self::crearDesdeUnicoRegistro($lines[0]);

            $linea_anterior = $lines[0];
            $trabajo_index  = 1; 
        }


        for(; $trabajo_index < count($lines); $trabajo_index++){
            $linea_actual       = $lines[$trabajo_index];
            $num_bolasas_buenas = (int) $linea_actual->bolsas_buenas - (int) $linea_anterior->bolsas_buenas;
            $diff_kg            = (float) $linea_actual->kg - (float) $linea_anterior->kg;
            $inicio_time        = strtotime($linea_anterior->fin_of);
            $fin_time           = strtotime($linea_actual->fin_of);
            
            /* Registro incoherente */
            if($num_bolasas_buenas <= 0 || $diff_kg <= 0 || $inicio_time == false || $fin_time == false || $fin_time < $inicio_time ){
                 file_put_contents('log/alarma.log', date('Y-m-d H:i:s').' REGISTRO NO VALIDO 1 '.json_encode($linea_actual).PHP_EOL, FILE_APPEND);
                continue;
            }

            /* Una sola bolsa de diferencia - creamo 1 pesada */
            if ($num_bolasas_buenas == 1){
                $ref_id = $linea_actual->id.'-1';
                /*  Comentado de momento, luego quitare el comentario.. */
                PesadaIndividual::updateOrCreate(
                    ['ref_id' => $ref_id],
                    [
                        'weight_value'  => $diff_kg,
                        'creacion_date' => $linea_actual->fin_of,
                        'article_code'  => $linea_actual->art_erp,
                        'article_name'  => $linea_actual->art_name
                    ]
                );
            
            } elseif ($num_bolasas_buenas > 1) {
                $diff_time   = $fin_time - $inicio_time;
                $weigth_step = $diff_kg / $num_bolasas_buenas;

                for($i = 1; $i <= $num_bolasas_buenas; $i++){
                    
                    $second_from_start = intdiv($diff_time * $i, $num_bolasas_buenas);
                    $pesada_time       = $inicio_time + $second_from_start;
                    $ref_id            = $linea_actual->id.'-'.$i;

                    /*  Comentado de momento, luego quitare el comentario.. */
                    PesadaIndividual::updateOrCreate(
                        ['ref_id' => $ref_id],
                        [
                            'weight_value'  => $weigth_step,
                            'creacion_date' => date('Y-m-d H:i:s', $pesada_time),
                            'article_code'  => $linea_actual->art_erp,
                            'article_name'  => $linea_actual->art_name
                        ]
                    );
                }
            } 
            $linea_anterior = $linea_actual;

        }
    }
}