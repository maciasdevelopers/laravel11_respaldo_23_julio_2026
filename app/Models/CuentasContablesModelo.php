<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentasContablesModelo extends Model
{
    use HasFactory;
    protected $table = 'cont_catalogo_cuentas_contables';
    protected $hidden = [
        'id',   
    ];
}
