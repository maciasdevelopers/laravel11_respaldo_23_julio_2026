<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventariosSeriesModelo extends Model
{
    use HasFactory;
    protected $table = 'inventarios_catalogo_series';
    protected $hidden = ['id'];
}
