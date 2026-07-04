<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesadaIndividual extends Model
{
    protected $table   = 'pesadas_individuales';
    public $timestamps = false;
    protected $fillable = [
        'ref_id',
        'weight_value',
        'creacion_date',
        'article_code',
        'batch_number',
        'article_name'
    ];
}
