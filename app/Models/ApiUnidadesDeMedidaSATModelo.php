<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiUnidadesDeMedidaSATModelo extends Model{
    use HasFactory;
    protected $table = 'api_sat_unidad_medida';
    protected $connection = 'apis_externas';
    protected $hidden = ['id',];
}
