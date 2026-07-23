<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsimiladosModelo extends Model{
  use HasFactory;
  protected $table = 'vhum_reporte_asimilados_main';
  protected $hidden = ['id'];
}