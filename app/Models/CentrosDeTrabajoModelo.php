<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentrosDeTrabajoModelo extends Model
{
    use HasFactory;
    protected $table = 'vhum_centros_de_trabajo_catalogo';
    protected $hidden = ['id'];
}
