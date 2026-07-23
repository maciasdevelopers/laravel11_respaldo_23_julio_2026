<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiMonedasModelo extends Model{
    use HasFactory;
    protected $table = 'api_monedas';
    protected $connection = 'apis_externas';
    protected $hidden = ['id',];
}
