<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComprasModelo extends Model
{
    use HasFactory;
    protected $table = 'eegr_compras';
    protected $hidden = ['id'];
}
