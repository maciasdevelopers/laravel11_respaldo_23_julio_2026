<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListaPreciosModelo extends Model
{
    use HasFactory;
    protected $table = 'ingr_catalogo_lista_precios';
    protected $hidden = [
        'id',	
    ];
}
