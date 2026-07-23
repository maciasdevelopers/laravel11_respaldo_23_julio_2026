<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImpuestosModelo extends Model
{
    use HasFactory;
    protected $table = 'cont_impuestos_catalogo';
    protected $hidden = [
        'id',
        'empresa'
    ];
}
