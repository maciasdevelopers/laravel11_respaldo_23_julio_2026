<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiFormasPagoModelo extends Model{
    use HasFactory;
    protected $table = 'api_formas_de_pago';
    protected $connection = 'apis_externas';
    protected $hidden = ['id',];
}
