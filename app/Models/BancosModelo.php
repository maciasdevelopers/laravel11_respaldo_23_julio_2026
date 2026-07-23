<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BancosModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_bancos';
    protected $hidden = [
        'id',	
    ];
}
