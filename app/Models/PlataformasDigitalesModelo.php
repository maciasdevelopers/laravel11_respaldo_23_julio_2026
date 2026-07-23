<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlataformasDigitalesModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_plataformas_digitales';
    protected $hidden = [
        'id',	
    ];
}
