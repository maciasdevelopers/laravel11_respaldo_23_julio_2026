<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PostsModelo;
use App\Models\CategoriaModelo;


class _PruebaController extends Controller
{
    public function index(){
        $titulo = 'animales';
        $animales = ['perro','gato','tigre'];
        return view('pruebas.index',array(
            'titulo' => $titulo,
            'animales' => $animales
        ));
    }

    public function testOrm(){
        $posts = PostsModelo::all();
        //var_dump($posts);
        foreach ($posts as $post) {
            //echo "<pre>";
            //print_r($post);
            //echo "</pre>";
            echo '<h1>'.$post->titulo.'</h1>';
            echo '<h1>'.$post->contenido.'</h1>';
            echo "<span>{$post->user->name}</span>";
            echo "<span>{$post->categoria->name}</span>";
        }
        die();
    }

}
