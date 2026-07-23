<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\EntregasModelo;

class _EntregasController extends Controller
{
    public function listaEntregas(){
        $listEntregas = EntregasModelo::all();
        $arrayEntregas = array();
        foreach ($listEntregas as $vallistEntregas) {
            $arraInterno = array(
                'tknEntrega' => $vallistEntregas->token_entrega,
                'codEntrega' => $vallistEntregas->mini_token_entrega,
                'producto' => $vallistEntregas->producto,
                'almacen' => $vallistEntregas->almacen,
                'venta' => $vallistEntregas->venta,
                'resp_entrega' => $vallistEntregas->resp_entrega,
                'lugar_entrega' => $vallistEntregas->lugar_entrega,
                'tiempo_estimado' => $vallistEntregas->tiempo_estimado,
                'status_entrega' => $vallistEntregas->status_entrega,
            );
            $arrayEntregas[] = $arraInterno;
        }
    }
}
