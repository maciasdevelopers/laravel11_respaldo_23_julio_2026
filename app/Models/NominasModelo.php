<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NominasModelo extends Model{
  use HasFactory;
  protected $table = 'vhum_nominas_main';
  protected $hidden = ['id'];
}