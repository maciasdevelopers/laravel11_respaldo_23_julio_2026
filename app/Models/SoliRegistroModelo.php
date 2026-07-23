<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SoliRegistroModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_solicitud_registro';
    protected $hidden = [ 
        'id'
    ];
}
