<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivosFijosModelo extends Model
{
    use HasFactory;
    protected $table = 'eegr_activos_fijos_catalogo';
    protected $hidden = [
        'id',	
        'clasificacion',
        'administrador',
        'empresa'
    ];
}
