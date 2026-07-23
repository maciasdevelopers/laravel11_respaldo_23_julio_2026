<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UMedidaModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_unidad_medida';

    protected $hidden = [
        'id',	
    ];

}
