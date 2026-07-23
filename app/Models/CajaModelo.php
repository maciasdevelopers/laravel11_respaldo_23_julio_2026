<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CajaModelo extends Model
{
    use HasFactory;
    protected $table = 'fnzs_catalogos_caja';
    protected $hidden = [
        'id',	
    ];
}
