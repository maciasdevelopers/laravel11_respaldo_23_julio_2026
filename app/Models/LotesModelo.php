<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LotesModelo extends Model
{
    use HasFactory;
    protected $table = 'inventarios_catalogo_lotes';
    protected $hidden = [
        'id',	
        'administrador'
    ];
}
