<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClasificacionModelo extends Model{
    use HasFactory;
    protected $table = 'sos_ps_clasificacion';
    protected $fillable = [
        'concepto',
        'codigo'
    ];

    protected $hidden = [
        'id',	
    ];
}
