<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedimentosModelo extends Model
{
    use HasFactory;
    protected $table = 'inventarios_catalogo_pedimento_aduanal';
    protected $hidden = [
        'id',	
        'administrador'
    ];
}
