<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingModelo extends Model
{
    use HasFactory;
    protected $fillable = [
        'id_servicio',
        'c_token',
        'servicio',
        'clasificacion',
        'genero',
        'catalogoSAT',
        'medida_sat',
        'imagen',
        'empresa',
    ];
}
