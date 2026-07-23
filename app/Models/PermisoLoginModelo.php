<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermisoLoginModelo extends Model
{
    use HasFactory;
    protected $table = 'permiso_login';
    protected $hidden = [
        'id'
    ];
}
