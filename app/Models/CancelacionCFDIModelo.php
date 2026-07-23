<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancelacionCFDIModelo extends Model{
    use HasFactory;
    protected $table = "cfdi_motivos_cancelacion";
    protected $hidden = ["id"];
}
