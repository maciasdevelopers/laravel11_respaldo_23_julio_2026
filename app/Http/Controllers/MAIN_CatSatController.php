<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\CatSatModelo;

class MAIN_CatSatController extends Controller
{
    public function listaCatalogo(){
        $arraySat = array();
        //$listaCat = CatSatModelo::where('id','!=','1')->take(5)->get();
        $listaCat = CatSatModelo::where('id','!=','1')->get();
        foreach ($listaCat as $valSat) {
            $list = array(
                "token_prodservsat" => $valSat->token_prodservsat,
                "clave" => $valSat->clave,
                "descripcion" => $valSat->descripcion,
                "class" => "",
            );
            $arraySat[] = $list;
        }
        return response()->json([
            'catSat' => $arraySat,
            'codigo' => 200,
            'status' => 'success'
        ]);
        
    }

    public function listaCatalogoPClave(Request $request){
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $arraySat = array();
        $listaCat = CatSatModelo::where('id','!=','1',)->where('clave','LIKE','%'.$parametros->clave.'%')->get();
        $num_list = 1;
        foreach ($listaCat as $valSat) {
            $list = array(
                "num_list" => $num_list,
                "token_prodservsat" => $valSat->token_prodservsat,
                "clave" => $valSat->clave,
                "descripcion" => $valSat->descripcion,
                "selected" => false,
                "class" => "",
            );
            $arraySat[] = $list;
            ++$num_list;
        }
        return response()->json([
            'catSat' => $arraySat,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }

    public function listaCatalogoPdesc(Request $request){
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $arraySat = array();
        $listaCat = CatSatModelo::where('id','!=','1',)->where('descripcion','LIKE','%'.$parametros->descripcion.'%')->get();
        $num_list = 1;
        foreach ($listaCat as $valSat) {
            $list = array(
                "num_list" => $num_list,
                "token_prodservsat" => $valSat->token_prodservsat,
                "clave" => $valSat->clave,
                "descripcion" => $valSat->descripcion,
                "selected" => false,
                "class" => "",
            );
            $arraySat[] = $list;
            ++$num_list;
        }
        return response()->json([
            'catSat' => $arraySat,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }

    public function listaCatalogoPInput(Request $request){
        //$JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        //echo $parametros->clave;
        //$usuario = $JwtAuth->checkToken(,true);
        $listaCat = CatSatModelo::where('id','!=','1',)
        ->where('clave','=',$parametros->clave)->get();

        return response()->json([
            'catSatToken' => $listaCat[0]->token_prodservsat,
            'catSatDescripcion' => $listaCat[0]->descripcion,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }
}
