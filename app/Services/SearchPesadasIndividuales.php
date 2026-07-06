<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;

class SearchPesadasIndividuales{
    public function returnPesadasIndividuales(string $year, string $month): array{

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

        $pi_lines = DB::select(
            'SELECT pi.weight_value AS "ActualNetWeightValue",
                pi.creacion_date AS "CreationDate", 
                pi.article_code AS "ArticleNumber",
                pi.article_code AS "BatchNumber",
                pi.article_name AS "ArticleName"
            FROM pesadas_individuales pi
            WHERE pi.creacion_date >= ? AND pi.creacion_date < ?
            ORDER BY pi.creacion_date ASC, pi.id ASC',
            [$date_from, $date_to] 
        );

        return $pi_lines;
    }
}