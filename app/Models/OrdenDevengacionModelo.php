<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenDevengacionModelo extends Model
{
    use HasFactory;
    protected $table = 'eegr_compras_orden_devengacion';
    protected $hidden = ['id'];
}