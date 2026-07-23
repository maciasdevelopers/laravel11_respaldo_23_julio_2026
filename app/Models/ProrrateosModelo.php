<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProrrateosModelo extends Model
{
    use HasFactory;
    protected $table = 'eegr_compras_prorrateos';
    protected $hidden = [
        'id',	
    ];
}
