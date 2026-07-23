<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CFDITrasladoModelo extends Model{
  use HasFactory;
  protected $table = 'cfdi_comprobante_fiscal_traslado';
  protected $hidden = ['id'];
}
