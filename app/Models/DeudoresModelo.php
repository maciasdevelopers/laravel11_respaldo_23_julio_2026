<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeudoresModelo extends Model
{
    use HasFactory;
    protected $table = 'fnzs_catalogo_deudores';
    protected $hidden = [
        'id',	
        'empresa'
    ];
}
