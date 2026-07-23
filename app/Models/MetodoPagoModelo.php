<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetodoPagoModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_metodo_pago';
    protected $hidden = [
        'id',
    ];
}
