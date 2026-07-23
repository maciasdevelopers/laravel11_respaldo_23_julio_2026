<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcreedoresModelo extends Model
{
    use HasFactory;
    protected $table = 'fnzs_catalogo_acreedores';
    protected $hidden = [
        'id',	
        'empresa'
    ];
}
