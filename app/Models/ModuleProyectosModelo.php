<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleProyectosModelo extends Model
{
    use HasFactory;
    protected $table = 'module_proyectos AS mod_proy';
    protected $hidden = [
        'id',
    ];
}
