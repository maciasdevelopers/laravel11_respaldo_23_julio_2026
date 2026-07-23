<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalModelo extends Model
{
    use HasFactory;
    protected $table = 'vhum_empleados_catalogo';
    protected $hidden = [
        'id',	
    ];
}
