<?php
namespace App\Services;
use App\Models\PesadaIndividual;

class CreacionPesadasIndividuales{

    const MAX_GAP_SEG            = 5 * 3600;  // hueco > 5h entre acumulados = la linea estuvo parada
    const LOOKAHEAD_SIGUIENTES   = 11;        // max tramos SIGUIENTES para calcular la cadencia media
    const LOOKAHEAD_ANTERIORES   = 3;         // max tramos ANTERIORES si no hay siguientes
    const SEG_POR_BOLSA_DEFECTO  = 30;        // fallback si la OF no tiene ningun tramo valido

    /*
        Cadencia real de la OF en SEGUNDOS POR BOLSA, con la media de los tramos vecinos.
        direccion =  1 -> mira los tramos siguientes a partir de desde_index: [i, i+1], [i+1, i+2]...
        direccion = -1 -> mira los tramos anteriores desde desde_index hacia atras
        max_tramos      -> cuantos tramos validos como maximo usamos (si hay menos, los que haya)
        Se saltan otros huecos grandes y registros incoherentes para no contaminar la media.
        Devuelve null si no encuentra ni un tramo valido.
    */
    private static function segundosPorBolsa(array $lines, int $desde_index, int $direccion, int $max_tramos): ?float {
        $seg_total    = 0;
        $bolsas_total = 0;
        $tramos       = 0;

        for($i = $desde_index; $i >= 0 && $i < count($lines) - 1 && $tramos < $max_tramos; $i += $direccion){
            $t1 = strtotime((string) $lines[$i]->fin_of);
            $t2 = strtotime((string) $lines[$i + 1]->fin_of);
            $db = (int)   $lines[$i + 1]->bolsas_buenas - (int)   $lines[$i]->bolsas_buenas;
            $dk = (float) $lines[$i + 1]->kg            - (float) $lines[$i]->kg;

            if($t1 === false || $t2 === false){
                continue;
            }
            $gap = $t2 - $t1;

            /* Saltamos otros parones de +5h y registros no validos */
            if($db <= 0 || $dk <= 0 || $gap <= 0 || $gap > self::MAX_GAP_SEG){
                continue;
            }

            $seg_total    += $gap;
            $bolsas_total += $db;
            $tramos++;
        }

        return $bolsas_total > 0 ? $seg_total / $bolsas_total : null;
    }

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
    }



    /*
        Creamos pesadas individuales si hay una bolsa=1 pesada, si es acumulado->destribuido
    */
    public static function procesarProduccionNormal(array $wProd, ?\stdClass $linea_anterior_inicio=null): void{
        $lines = $wProd['lines'];

        /*
            Saneamos la serie: un acumulado no puede bajar. Si un registro tiene mas
            bolsas o kg que el registro SIGUIENTE, es un pico/lectura corrupta -> fuera.
            Asi un solo registro malo no atasca linea_anterior y arrastra toda la OF.
        */
        $limpias = [];
        for($i = 0; $i < count($lines); $i++){
            if($i < count($lines) - 1 
                && ((int) $lines[$i]->bolsas_buenas > (int) $lines[$i+1]->bolsas_buenas
                 || (float) $lines[$i]->kg > (float) $lines[$i+1]->kg)){
                file_put_contents('log/alarma.log', date('Y-m-d H:i:s').' PICO DESCARTADO '.json_encode($lines[$i]).PHP_EOL, FILE_APPEND);
                continue;
            }
            $limpias[] = $lines[$i];
        }
        $lines = $limpias;

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
            
            /* Muestra intermedia: los kg avanzan pero el contador de bolsas aun no.
               Ruido normal del poller (2 muestras en el mismo segundo). El siguiente
               delta absorbe estos kg. No logueamos: es esperado y frecuente. */
            if($num_bolasas_buenas == 0){
                continue;
            }

            /* Registro incoherente de verdad: contador que baja o fechas rotas */
            if($num_bolasas_buenas < 0 || $diff_kg <= 0 || $inicio_time == false || $fin_time == false || $fin_time < $inicio_time ){
                 file_put_contents('log/alarma.log', date('Y-m-d H:i:s').' REGISTRO NO VALIDO 1 '.json_encode($linea_actual).PHP_EOL, FILE_APPEND);
                continue;
            }


            /*
                PARADA LARGA: hueco de +5h entre este acumulado y el anterior.
                No repartimos las bolsas por todo el hueco (nos inventariamos un fin de
                semana trabajado). Calculamos la cadencia real (seg/bolsa):
                  1º con hasta 11 tramos SIGUIENTES de la misma OF (si hay menos, los que haya)
                  2º si no hay ninguno (somos el ultimo tramo), con los 3 tramos ANTERIORES
                     sin contar el propio hueco
                  3º si tampoco, valor por defecto
                y replantamos hacia atras desde el fin_of que cierra el hueco.
                Si el delta es de 1 sola bolsa no hace falta: se planta en su fin_of tal cual.
            */
            $gap = $fin_time - $inicio_time;
            if($gap > self::MAX_GAP_SEG && $num_bolasas_buenas > 1){
                $spb = self::segundosPorBolsa($lines, $trabajo_index, 1, self::LOOKAHEAD_SIGUIENTES)
                    ?? self::segundosPorBolsa($lines, $trabajo_index - 2, -1, self::LOOKAHEAD_ANTERIORES)
                    ?? self::SEG_POR_BOLSA_DEFECTO;

                /* Hacia atras desde fin_of, sin pisar nunca la pesada anterior */
                $inicio_time = max($inicio_time, $fin_time - (int) ceil($spb * $num_bolasas_buenas));
            }

            /* Una sola bolsa de diferencia - creamo 1 pesada */
            if ($num_bolasas_buenas == 1){
                $ref_id = $linea_actual->id.'-1';

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