<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermisosModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_permisos_usuario';
    protected $hidden = [
        'id',
    ];
}
