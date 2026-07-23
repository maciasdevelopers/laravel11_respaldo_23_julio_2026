<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JustificacionEmpleadoModelo extends Model
{
    use HasFactory;
    protected $table = 'terc_justificacion_main';

    protected $hidden = [
        'id'
    ];
}
