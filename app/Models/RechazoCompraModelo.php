<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RechazoCompraModelo extends Model
{
    use HasFactory;
    protected $table = 'eegr_compras_rechazo';
    protected $hidden = [
        'id'
    ];
}
