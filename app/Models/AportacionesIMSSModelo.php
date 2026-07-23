<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AportacionesIMSSModelo extends Model{
  use HasFactory;
  protected $table = 'vhum_aportaciones_seguridad_social_main';
  protected $hidden = ['id'];
}