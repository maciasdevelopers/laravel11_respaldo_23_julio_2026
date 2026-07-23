<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentBancModelo extends Model
{
    use HasFactory;
    protected $table = 'fnzs_catalogos_cuentas';
    protected $hidden = [
        'id',	
    ];
}
