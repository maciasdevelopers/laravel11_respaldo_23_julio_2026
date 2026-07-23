<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DescargablesModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_descargables';
    protected $hidden = [ 
        'id'
    ];
}
