<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegimenFiscalModelo extends Model{
    use HasFactory;
    protected $table = 'sos_regimen_fiscal';
    protected $hidden = [
        'id',	
    ];
}
