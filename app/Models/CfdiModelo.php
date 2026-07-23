<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CfdiModelo extends Model
{
    use HasFactory;
    //protected $table = 'solicitud_cfdi';
    protected $table = 'sos_cfdi_main';
    protected $hidden = ['id'];
}
