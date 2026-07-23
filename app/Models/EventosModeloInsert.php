<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventosModeloInsert extends Model
{
    use HasFactory;
    protected $table = 'module_proyectos_eventos';
    protected $hidden = [
        'id',
    ];
}
