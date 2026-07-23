<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenCobroModelo extends Model{
    use HasFactory;
    protected $table = 'fnzs_cobros_orden';
    protected $hidden = ['id'];
}