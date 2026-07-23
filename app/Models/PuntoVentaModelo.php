<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PuntoVentaModelo extends Model
{
    use HasFactory;
    protected $table = 'sos_puntodeventa_catalogos';
    protected $hidden = [
        'id',
        'empresa'
    ];
}
