<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonedasModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_catalogo_monedas';
  
    protected $fillable = [	
        'c_token',	
        'codigo',	
        'moneda',
    ];

    protected $hidden = [
        'id',
    ];

}
