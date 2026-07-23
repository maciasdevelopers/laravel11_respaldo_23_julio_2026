<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaModelo extends Model
{
    use HasFactory;
    protected $table = 'categorias';
    protected $fillable = [
        //'id_usuario',
        'name',
    ];
    public function posts(){
        //relacion de uno a muchos 
        return $this->hasMany('App\Models\PostsModelo');
    }
}
