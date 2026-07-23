<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionesModelo extends Model
{
    use HasFactory;
    protected $table = 'eegr_compras_cotizacion';
    protected $hidden = [
        'id',
        'empresa',
        'usuario_cotizador',
    ];
}
