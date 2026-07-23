<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaMonederoModelo extends Model
{
    use HasFactory;
    protected $table = 'fnzs_catalogos_cuentas_monedero';
    protected $hidden = [
        'id',	
    ];
}
