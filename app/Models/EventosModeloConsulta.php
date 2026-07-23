<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventosModeloConsulta extends Model
{
    use HasFactory;
    protected $table = 'module_proyectos_eventos AS evnt';
    protected $hidden = [
        'id',
    ];
}
