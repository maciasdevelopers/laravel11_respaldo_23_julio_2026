<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivosIntangiblesModelo extends Model
{
    use HasFactory;
    protected $table = 'eegr_activos_intangibles_catalogo';
    protected $hidden = [
        'id',	
        'catactintang.clasificacion',
        'administrador',
        'empresa'
    ];
}
