<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DireccionesModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_direcciones';
    protected $hidden = [
        'id'
    ];
}
