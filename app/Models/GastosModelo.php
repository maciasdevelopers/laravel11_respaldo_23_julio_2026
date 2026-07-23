<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GastosModelo extends Model
{
    use HasFactory;
    protected $table = 'catalogo_gastos';
    protected $hidden = [
        'id',	
    ];
}
