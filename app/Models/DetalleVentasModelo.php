<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleVentasModelo extends Model
{
    use HasFactory;
    protected $table = 'detalle_venta';
    protected $hidden = ['id'];
}
