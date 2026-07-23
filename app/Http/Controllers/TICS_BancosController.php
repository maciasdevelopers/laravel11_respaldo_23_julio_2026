<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\BancosModelo;
use App\Models\CuentBancModelo;

class TICS_BancosController extends Controller
{
    public function bancos(){
        $JwtAuth = new \JwtAuth();
        $arrayBancos = array();
        $bancos = BancosModelo::all();
        foreach ($bancos as  $value) {
            $arrayEach = array(
                "token_bancos" => $value->token_bancos,
                "clave" => $value->clave,
                "nombre_comercial" => $value->nombre_comercial,
                "imagen" => $value->img
            );
            $arrayBancos[] = $arrayEach;
        }
        return response()->json([
            'banco' => $arrayBancos,
            'codigo' => 200,
            'status' => 'success'
        ]); 
    }
}