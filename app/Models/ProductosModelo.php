<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductosModelo extends Model
{
    use HasFactory;
    protected $table = 'in_egr_catalogo_productos';
    protected $hidden = [ 
        'id',
        'empresa',
        'administrador'
    ];
}
