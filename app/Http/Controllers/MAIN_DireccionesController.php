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
use App\Models\DireccionesModelo;
use App\Models\CodPostalModelo;

class MAIN_DireccionesController extends Controller{
  private function cPostalDipomex($cod_postal){
    $curl = curl_init();
    curl_setopt_array(
      $curl,
      array(
        CURLOPT_URL => "https://api.tau.com.mx/dipomex/v1/codigo_postal?cp=".$cod_postal,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json",
          "APIKEY:ca71ff87013b185cee369cd6eb5c6c5ddd1f4acd"
        )
      )
    );
  
    $curl_response = curl_exec($curl);
    curl_close($curl);
    return json_decode($curl_response);
  }

  private function cPostalZippopotam($cod_postal){
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => "https://api.zippopotam.us/mx/" . $cod_postal,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return ($httpCode == 200) ? json_decode($response, true) : null;
  }

  public function listacodDipomex(Request $request){
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "clave_cod_postal" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $claveCodPostal = $parametrosArray["clave_cod_postal"];
        //echo $claveCodPostal;
        // 1. zippopotam
        $resZippo = $this->cPostalZippopotam($claveCodPostal);
    
        if ($resZippo) {
          // Homologación de campos para que tu HTML/Angular no note la diferencia
          $colonias = array_map(function($place) {return $place['place name'];}, $resZippo['places']);
    
          $data_homologada = [
            "estado" => $resZippo['places'][0]['state'] ?: $resZippo['places'][0]['state'],
            "estado_abreviatura" => $resZippo['places'][0]['state abbreviation'] ?: $resZippo['places'][0]['state abbreviation'],
            "municipio" => '---',
            "codigo_postal" => $claveCodPostal,
            "colonias" => $colonias
          ];
    
          return response()->json([
            "status" => "success",
            "code" => 200,
            "cod_postal" => $data_homologada
          ], 200);
        }
    
        // 2. dipomex
        $responseDipomex = $this->cPostalDipomex($claveCodPostal);
        if ($responseDipomex) {
          // Estructura original de Dipomex
          return response()->json([
            "status" => "success",
            "code" => 200,
            "cod_postal" => $responseDipomex->codigo_postal
          ], 200);
        }

        return response()->json([
          "status" => "error",
          "message" => "postal_empty"
        ], 200);
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaLocationIQ(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "direccion" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        //$listaCodPostal = DB::select("SELECT cod_postal FROM codpostal GROUP BY cod_postal");
        $claveCodPostal = $parametrosArray["direccion"];

        if (isset($parametrosArray["direccion"]) && !empty($parametrosArray["direccion"]) && preg_match($JwtAuth->filtroAlfaNumerico(), $parametrosArray["direccion"])) {
          $url = "https://us1.locationiq.com/v1/search?key=pk.0dc230e7bb563cb5189f8d8b944d3e1b&q=" . urlencode($claveCodPostal) . "&format=json";
          $curl = curl_init($url);
          curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER    =>  true,
            CURLOPT_FOLLOWLOCATION    =>  true,
            CURLOPT_MAXREDIRS         =>  10,
            CURLOPT_TIMEOUT           =>  30,
            CURLOPT_CUSTOMREQUEST     =>  'GET',
          ));
          $response = curl_exec($curl);
          $err = curl_error($curl);

          curl_close($curl);

          if ($err) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => $err
            );
          } else {
            $json_respuesta = json_decode($response);
            $list_direcciones = array();
            foreach ($json_respuesta as $vDir) {
              //https://my.locationiq.com/dashboard#playground
              $row_dir = array(
                "place_id" => $vDir->place_id,
                "licence" => $vDir->licence,
                "osm_type" => $vDir->osm_type,
                "osm_id" => $vDir->osm_id,
                "boundingbox" => $vDir->boundingbox,
                "lat" => $vDir->lat,
                "lon" => $vDir->lon,
                "display_name" => $vDir->display_name
              );
              $list_direcciones[] = $row_dir;
            }
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'direcciones' => $list_direcciones
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'direccion vacia ó invalida'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listacodDipomex2(){
    $curl = curl_init();
    curl_setopt_array(
      $curl,
      array(
        CURLOPT_URL => "https://api.tau.com.mx/dipomex/v1/codigo_postal?cp=09000",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 3000,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        //CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json",
          "APIKEY:ca71ff87013b185cee369cd6eb5c6c5ddd1f4acd"
        )
      )
    );

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    return $response;
  }

  public function listacodPostal(){
    $JwtAuth = new \JwtAuth();
    $arraycpostal = array();
    //$listaCodPostal = DB::select("SELECT cod_postal FROM codpostal GROUP BY cod_postal");
    $listaCodPostal = DB::table("teci_direcciones_codigos_postales")->take(10)->get();

    foreach ($listaCodPostal as $valPostal) {
      $rrayeach = array(
        //"cod_postal" => $valPostal->cod_postal
        "token_codigos_postales" => $valPostal->token_codigos_postales,
        "codigo_postal" => $valPostal->codigo_postal,
        "asentamiento" => $valPostal->asentamiento,
        "tipo_asentamiento" => $valPostal->tipo_asentamiento,
        "deleg_mun" => $valPostal->deleg_mun,
        "estado" => $valPostal->estado,
        "ciudad" => $valPostal->ciudad,
        "d_CP" => $valPostal->d_CP,
        "codigo_estado" => $valPostal->codigo_estado,
        "c_oficina" => $valPostal->c_oficina,
        "codigo_CP" => $valPostal->codigo_CP,
        "codigo_tipo_asentamiento" => $valPostal->codigo_tipo_asentamiento,
        "codigo_deleg_mun" => $valPostal->codigo_deleg_mun,
        "id_asenta_cpcons" => $valPostal->id_asenta_cpcons,
        "d_zona" => $valPostal->d_zona,
        "codigo_cve_ciudad" => $valPostal->codigo_cve_ciudad,
        "class" => "",
      );

      $arraycpostal[] = $rrayeach;
    }
    return response()->json([
      'cod_postal' => $arraycpostal,
      'codigo' => 200,
      'status' => "success"
    ]);
  }

  public function listacodPostalLike(Request $request){
    $JwtAuth = new \JwtAuth();
    $arraycpostal = array();
    //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'clave' => 'required',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los datos del usuario son incorrectos, favor de verificarlos' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        //$listaCodPostal = DB::select("SELECT cod_postal FROM codpostal GROUP BY cod_postal");
        $listaCodPostal = DB::table("codigos_postales")
          ->where('codigo_postal', 'LIKE', '%' . $parametrosArray['clave'] . '%')
          ->orwhere('asentamiento', 'LIKE', '%' . $parametrosArray['clave'] . '%')
          ->orwhere('deleg_mun', 'LIKE', '%' . $parametrosArray['clave'] . '%')
          ->orwhere('estado', 'LIKE', '%' . $parametrosArray['clave'] . '%')
          ->get();

        foreach ($listaCodPostal as $valPostal) {
          $rrayeach = array(
            //"cod_postal" => $valPostal->cod_postal
            "token_codigos_postales" => $valPostal->token_codigos_postales,
            "codigo_postal" => $valPostal->codigo_postal,
            "asentamiento" => $valPostal->asentamiento,
            "tipo_asentamiento" => $valPostal->tipo_asentamiento,
            "deleg_mun" => $valPostal->deleg_mun,
            "estado" => $valPostal->estado,
            "ciudad" => $valPostal->ciudad,
            "d_CP" => $valPostal->d_CP,
            "codigo_estado" => $valPostal->codigo_estado,
            "c_oficina" => $valPostal->c_oficina,
            "codigo_CP" => $valPostal->codigo_CP,
            "codigo_tipo_asentamiento" => $valPostal->codigo_tipo_asentamiento,
            "codigo_deleg_mun" => $valPostal->codigo_deleg_mun,
            "id_asenta_cpcons" => $valPostal->id_asenta_cpcons,
            "d_zona" => $valPostal->d_zona,
            "codigo_cve_ciudad" => $valPostal->codigo_cve_ciudad,
            "class" => "",
          );

          $arraycpostal[] = $rrayeach;
        }
        $dataMensaje = array(
          'cod_postal' => $arraycpostal,
          'code' => 200,
          'status' => "success"
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listacolonias(Request $request){
    $JwtAuth = new \JwtAuth();
    $arraycolonias = array();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    $listaColoniasCodPostal = CodPostalModelo::join('colonias AS col', 'codpostal.colonia', 'col.id')
      ->where([
        'codpostal.cod_postal' => $parametros->cod_postal
      ])->get();
    //
    foreach ($listaColoniasCodPostal as $valPostal) {
      $rrayeach = array(
        "token_colonia" => $valPostal->token_colonia,
        "colonia" => $valPostal->colonia
      );

      $arraycolonias[] = $rrayeach;
    }
    return response()->json([
      'colonias' => $arraycolonias,
      'codigo' => 200,
      'status' => "success"
    ]);
  }

  public function selectentfed(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayentidadDeleg = array();
    $listaentidadDeleg = CodPostalModelo::join('colonias AS col', 'codpostal.colonia', 'col.id')
      ->join('deleg_mun AS delmun', 'col.delegacion', 'delmun.id')
      ->join('entidad_federativa AS entfed', 'delmun.entidad', 'entfed.id')
      ->where([
        'codpostal.cod_postal' => $parametros->cod_postal,
        'col.token_colonia' => $parametros->token_colonia
      ])->get();
    //
    foreach ($listaentidadDeleg as $valentidad) {
      $rrayeach = array(
        "deleg_mun" => $valentidad->deleg_mun,
        "entidad" => $valentidad->entidad
      );

      $arrayentidadDeleg[] = $rrayeach;
    }
    return response()->json([
      'entidad' => $arrayentidadDeleg,
      'codigo' => 200,
      'status' => "success"
    ]);
  }

  public function getEntidadesFederativas(Request $request){
    $JwtAuth = new \JwtAuth();
    $listEntidadesFed = array();
    $queryEntidadesFed = DB::table('teci_direcciones_entidad_federativa')->get();
    //
    foreach ($queryEntidadesFed as $vEnt) {
      $rrayeach = array(
        "token_entidad_federativa" => $vEnt->token_entidad_federativa,
        "entidad" => $vEnt->entidad,
        "rfc_gobierno" => $vEnt->rfc_gobierno,
      );

      $listEntidadesFed[] = $rrayeach;
    }
    return response()->json([
      'entidades_federativas' => $listEntidadesFed,
      'codigo' => 200,
      'status' => "success"
    ]);
  }
}
