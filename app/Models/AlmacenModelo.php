<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlmacenModelo extends Model
{
    use HasFactory;
    protected $table = 'in_egr_establecimientos_catalogo';
    protected $hidden = [
        'id'
    ];
}
