<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatSatModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_catalogo_prodservsat';
    protected $hidden = [
        'id'
    ];
}
