<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class 
MovimientosBancariosModelo extends Model{
    use HasFactory;
    protected $table = 'fnzs_actividad_movimientos';
    protected $hidden = ['id'];
}
