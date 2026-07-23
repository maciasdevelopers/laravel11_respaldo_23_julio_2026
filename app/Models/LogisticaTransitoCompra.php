<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticaTransitoCompra extends Model{
  protected $table = 'logistica_transito_compras_relacionada';
    
  protected $fillable = ['transito_main','compra_relacionada'];
}