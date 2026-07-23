<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispositivosModelo extends Model
{
    use HasFactory;
    protected $table = 'teci_dispositivos';
    protected $hidden = [
        'id',	
    ];
}
