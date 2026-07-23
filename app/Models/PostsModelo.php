<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostsModelo extends Model
{
    use HasFactory;
    protected $table = 'posts';
    public function user(){
        //relacion de muchos a uno  
        return $this->belongsTo('App\Models\User','usuario');
    }

    public function categoria(){
        //relacion de muchos a uno  
        return $this->belongsTo('App\Models\CategoriaModelo','categoria');
    }

}
