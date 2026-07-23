<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeclaracionesFederalesModelo extends Model{
  use HasFactory;
  protected $table = 'cont_reg_fisc_declaraciones_imp_federales';
  protected $hidden = ['id'];
}