<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenRecepcionModelo extends Model
{
    use HasFactory;
    protected $table = 'eegr_compras_orden_recepcion';
    protected $hidden = ['id'];
}