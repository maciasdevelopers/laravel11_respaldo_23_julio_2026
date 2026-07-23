<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaisModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_pais';
    protected $hidden = [
        'id'
    ];
}
