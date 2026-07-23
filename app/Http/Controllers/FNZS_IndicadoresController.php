<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\PublicacionesModelo;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

class FNZS_IndicadoresController extends Controller{
  public function listaIndicadores(){
    $JwtAuth = new \JwtAuth();

    $inpc = "0.00";
    $tasa_recargos = "0.00";
    $tipo_cmb_pdp = "0.00";
    $salario_minimo = "0.00";
    $salario_min_fronterizo = "0.00";
    $uma = "0.00";
    $udi = "0.00";
    $tiie = "0.00";

    $query_encabezados_inpc = DB::table("fnzs_indicadores_inpc")->orderBy('id', 'DESC')->limit(1)->get();
    foreach ($query_encabezados_inpc as $vInpc) {
      //$update_auth_true = DB::table("fnzs_indicadores_inpc")->where(["id" => $vInpc->id])->limit(1)->update(array("indic_inpc_token" => $JwtAuth->encriptarToken($vInpc->id.$vInpc->indic_inpc_valor.time())));
      $inpc = $vInpc->indic_inpc_valor;
    }

    $query_tasa_recargos = DB::table("fnzs_indicadores_tasa_recargos")->orderBy('id', 'DESC')->limit(1)->get();
    foreach ($query_tasa_recargos as $vRec) {
      //$update_auth_true = DB::table("fnzs_indicadores_tasa_recargos")->where(["id" => $vRec->id])->limit(1)->update(array("indic_tasa_recargos_token" => $JwtAuth->encriptarToken($vRec->id.$vRec->indic_tasa_recargos_valor.time())));
      $tasa_recargos = $vRec->indic_tasa_recargos_valor;
    }

    $query_tipo_cmb = DB::table("fnzs_indicadores_tipo_cmb_pdp")->orderBy('id', 'DESC')->limit(1)->get();
    foreach ($query_tipo_cmb as $vTC) {
      //$update_auth_true = DB::table("fnzs_indicadores_tipo_cmb_pdp")->where(["id" => $vTC->id])->limit(1)->update(array("indic_tipo_cmb_pdp_token" => $JwtAuth->encriptarToken($vTC->id.$vTC->indic_tipo_cmb_pdp_valor.time())));
      $tipo_cmb_pdp = $vTC->indic_tipo_cmb_pdp_valor;
    }

    $query_salario_minimo = DB::table("fnzs_indicadores_salario_minimo")->orderBy('id', 'DESC')->limit(1)->get();
    foreach ($query_salario_minimo as $vSM) {
      //$update_auth_true = DB::table("fnzs_indicadores_salario_minimo")->where(["id" => $vSM->id])->limit(1)->update(array("indic_salario_minimo_token" => $JwtAuth->encriptarToken($vSM->id.$vSM->indic_salario_minimo_valor.time())));
      $salario_minimo = $vSM->indic_salario_minimo_valor;
    }

    $query_salario_min_front = DB::table("fnzs_indicadores_salario_min_front")->orderBy('id', 'DESC')->limit(1)->get();
    foreach ($query_salario_min_front as $vSFr) {
      //$update_auth_true = DB::table("fnzs_indicadores_salario_min_front")->where(["id" => $vSFr->id])->limit(1)->update(array("indic_salario_min_fronterizo_token" => $JwtAuth->encriptarToken($vSFr->id.$vSFr->indic_salario_min_fronterizo_valor.time())));
      $salario_min_fronterizo = $vSFr->indic_salario_min_fronterizo_valor;
    }

    $query_uma = DB::table("fnzs_indicadores_uma")->orderBy('id', 'DESC')->limit(1)->get();
    foreach ($query_uma as $vuma) {
      //$update_auth_true = DB::table("fnzs_indicadores_uma")->where(["id" => $vuma->id])->limit(1)->update(array("indic_uma_token" => $JwtAuth->encriptarToken($vuma->id.$vuma->indic_uma_valor.time())));
      $uma = $vuma->indic_uma_valor;
    }

    $query_udi = DB::table("fnzs_indicadores_udi")->orderBy('id', 'DESC')->limit(1)->get();
    foreach ($query_udi as $vudi) {
      //$update_auth_true = DB::table("fnzs_indicadores_udi")->where(["id" => $vudi->id])->limit(1)->update(array("indic_udi_token" => $JwtAuth->encriptarToken($vudi->id.$vudi->indic_udi_valor.time())));
      $udi = $vudi->indic_udi_valor;
    }

    $query_tiie = DB::table("fnzs_indicadores_tiie")->orderBy('id', 'DESC')->limit(1)->get();
    foreach ($query_tiie as $vtiie) {
      //$update_auth_true = DB::table("fnzs_indicadores_tiie")->where(["id" => $vtiie->id])->limit(1)->update(array("indic_tiie_token" => $JwtAuth->encriptarToken($vtiie->id.$vtiie->indic_tiie_valor.time())));
      $tiie = $vtiie->indic_tiie_valor;
    }

    $dataMensaje = array(
      "status" => "success",
      "code" => 200,
      "inpc" => "$" . $inpc,
      "tasa_recargos" => "$" . $tasa_recargos,
      "tipo_cmb_pdp" => "$" . $tipo_cmb_pdp,
      "salario_minimo" => "$" . $salario_minimo,
      "salario_min_fronterizo" => "$" . $salario_min_fronterizo,
      "uma" => "$" . $uma,
      "udi" => "$" . $udi,
      "tiie" => "$" . $tiie
    );

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function indicadores_inpc(){
    $JwtAuth = new \JwtAuth();
    $indicadorContent = array();

    $query_indic_inpc = DB::table("fnzs_indicadores_inpc")->orderBy('id', 'DESC')->get();
    foreach ($query_indic_inpc as $vInpc) {
      $row = array(
        "token" => $vInpc->indic_inpc_token,
        "fecha" => gmdate('Y-m-d H:i:s', $vInpc->indic_inpc_fecha),
        "valor" => $vInpc->indic_inpc_valor,
      );
      $indicadorContent[] = $row;
    }

    $dataMensaje = array("status" => "success", "code" => 200, "indicador" => $indicadorContent);
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function indicadores_tasa_recargos(){
    $JwtAuth = new \JwtAuth();
    $indicadorContent = array();

    $query_indic_tasa_recargos = DB::table("fnzs_indicadores_tasa_recargos")->orderBy('id', 'DESC')->get();
    foreach ($query_indic_tasa_recargos as $vInTR) {
      $row = array(
        "token" => $vInTR->indic_tasa_recargos_token,
        "fecha" => gmdate('Y-m-d H:i:s', $vInTR->indic_tasa_recargos_fecha),
        "valor" => $vInTR->indic_tasa_recargos_valor,
      );
      $indicadorContent[] = $row;
    }

    $dataMensaje = array("status" => "success", "code" => 200, "indicador" => $indicadorContent);
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function indicadores_tipo_cambio(){
    $JwtAuth = new \JwtAuth();
    $indicadorContent = array();

    $query_indic_tipo_cmb = DB::table("fnzs_indicadores_tipo_cmb_pdp")->orderBy('id', 'DESC')->get();
    foreach ($query_indic_tipo_cmb as $vInTC) {
      $row = array(
        "token" => $vInTC->indic_tipo_cmb_pdp_token,
        "fecha" => gmdate('Y-m-d H:i:s', $vInTC->indic_tipo_cmb_pdp_fecha),
        "valor" => $vInTC->indic_tipo_cmb_pdp_valor,
      );
      $indicadorContent[] = $row;
    }

    $dataMensaje = array("status" => "success", "code" => 200, "indicador" => $indicadorContent);
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function indicadores_salario_minimo(){
    $JwtAuth = new \JwtAuth();
    $indicadorContent = array();

    $query_indic_salario_minimo = DB::table("fnzs_indicadores_salario_minimo")->orderBy('id', 'DESC')->get();
    foreach ($query_indic_salario_minimo as $vInSM) {
      $row = array(
        "token" => $vInSM->indic_salario_minimo_token,
        "fecha" => gmdate('Y-m-d H:i:s', $vInSM->indic_salario_minimo_fecha),
        "valor" => $vInSM->indic_salario_minimo_valor,
      );
      $indicadorContent[] = $row;
    }

    $dataMensaje = array("status" => "success", "code" => 200, "indicador" => $indicadorContent);
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function indicadores_salario_min_front(){
    $JwtAuth = new \JwtAuth();
    $indicadorContent = array();

    $query_indic_sal_min_front = DB::table("fnzs_indicadores_salario_min_front")->orderBy('id', 'DESC')->get();
    foreach ($query_indic_sal_min_front as $vInSF) {
      $row = array(
        "token" => $vInSF->indic_salario_min_fronterizo_token,
        "fecha" => gmdate('Y-m-d H:i:s', $vInSF->indic_salario_min_fronterizo_fecha),
        "valor" => $vInSF->indic_salario_min_fronterizo_valor,
      );
      $indicadorContent[] = $row;
    }

    $dataMensaje = array("status" => "success", "code" => 200, "indicador" => $indicadorContent);
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function indicadores_uma(){
    $JwtAuth = new \JwtAuth();
    $indicadorContent = array();

    $query_indic_uma = DB::table("fnzs_indicadores_uma")->orderBy('id', 'DESC')->get();
    foreach ($query_indic_uma as $vInUMA) {
      $row = array(
        "token" => $vInUMA->indic_uma_token,
        "fecha" => gmdate('Y-m-d H:i:s', $vInUMA->indic_uma_fecha),
        "valor" => $vInUMA->indic_uma_valor,
      );
      $indicadorContent[] = $row;
    }

    $dataMensaje = array("status" => "success", "code" => 200, "indicador" => $indicadorContent);
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function indicadores_udi(){
    $JwtAuth = new \JwtAuth();
    $indicadorContent = array();

    $query_indic_udi = DB::table("fnzs_indicadores_udi")->orderBy('id', 'DESC')->get();
    foreach ($query_indic_udi as $vInpc) {
      $row = array(
        "token" => $vInpc->indic_udi_token,
        "fecha" => gmdate('Y-m-d H:i:s', $vInpc->indic_udi_fecha),
        "valor" => $vInpc->indic_udi_valor,
      );
      $indicadorContent[] = $row;
    }

    $dataMensaje = array("status" => "success", "code" => 200, "indicador" => $indicadorContent);
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function indicadores_tiie(){
    $JwtAuth = new \JwtAuth();
    $indicadorContent = array();

    $query_indic_tiie = DB::table("fnzs_indicadores_tiie")->orderBy('id', 'DESC')->get();
    foreach ($query_indic_tiie as $vTIIE) {
      $row = array(
        "token" => $vTIIE->indic_tiie_token,
        "fecha" => gmdate('Y-m-d H:i:s', $vTIIE->indic_tiie_fecha),
        "valor" => $vTIIE->indic_tiie_valor,
      );
      $indicadorContent[] = $row;
    }

    $dataMensaje = array("status" => "success", "code" => 200, "indicador" => $indicadorContent);
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function indicadores_inpc_banxico(){
    $response = Http::withHeaders([
      'Bmx-Token' => config('services.banxico.token'),
      'Accept' => 'application/json',
    ])
    ->get('https://www.banxico.org.mx/SieAPIRest/service/v1/series/SP1/datos/oportuno');
    
    $dato = $response['bmx']['series'][0]['datos'][0]['dato'] ?? null;
    $fecha = $response['bmx']['series'][0]['datos'][0]['fecha'] ?? null;
    
    return response()->json([
      'status' => 'success',
      'clave' => 'INPC',
      'valor' => (float) $dato,
      'fecha' => $fecha,
      'fuente' => 'Banxico'
    ]);
  }

  public function indicadores_salario_minimo_general_banxico(){
    $response = Http::withHeaders([
      'Bmx-Token' => config('services.banxico.token'),
      'Accept' => 'application/json',
    ])
    ->get('https://www.banxico.org.mx/SieAPIRest/service/v1/series/SL11298/datos/oportuno');
    
    $dato = $response['bmx']['series'][0]['datos'][0]['dato'] ?? null;
    $fecha = $response['bmx']['series'][0]['datos'][0]['fecha'] ?? null;
    
    return response()->json([
      'status' => 'success',
      'clave' => 'Salario mínimo general',
      'valor' => (float) $dato,
      'fecha' => $fecha,
      'fuente' => 'Banxico'
    ]);
  }

//Salario mínimo fronterizo SL11299
  public function indicadores_salario_minimo_fronterizo_banxico(){
    $response = Http::withHeaders([
      'Bmx-Token' => config('services.banxico.token'),
      'Accept' => 'application/json',
    ])
    ->get('https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF63528/datos/oportuno');
    
    $dato = $response['bmx']['series'][0]['datos'][0]['dato'] ?? null;
    $fecha = $response['bmx']['series'][0]['datos'][0]['fecha'] ?? null;
    
    return response()->json([
      'status' => 'success',
      'clave' => 'INPC',
      'valor' => (float) $dato,
      'fecha' => $fecha,
      'fuente' => 'Banxico'
    ]);
  }
//UMA 735504
  public function indicadores_uma_banxico(){
    $response = Http::withHeaders([
      'Bmx-Token' => config('services.banxico.token'),
      'Accept' => 'application/json',
    ])
    ->get('https://www.banxico.org.mx/SieAPIRest/service/v1/series/735504/datos/oportuno');
    
    $dato = $response['bmx']['series'][0]['datos'][0]['dato'] ?? null;
    $fecha = $response['bmx']['series'][0]['datos'][0]['fecha'] ?? null;
    
    return response()->json([
      'status' => 'success',
      'clave' => 'UMA',
      'valor' => (float) $dato,
      'fecha' => $fecha,
      'fuente' => 'Banxico'
    ]);
  }

  public function indicadores_udi_banxico(){
    $response = Http::withHeaders([
      'Bmx-Token' => config('services.banxico.token'),
      'Accept' => 'application/json',
    ])
    ->get('https://www.banxico.org.mx/SieAPIRest/service/v1/series/SP68257/datos/oportuno');
    
    $dato = $response['bmx']['series'][0]['datos'][0]['dato'] ?? null;
    $fecha = $response['bmx']['series'][0]['datos'][0]['fecha'] ?? null;
    
    return response()->json([
      'status' => 'success',
      'clave' => 'UDI',
      'valor' => (float) $dato,
      'fecha' => $fecha,
      'fuente' => 'Banxico'
    ]);
  }

  public function indicadores_tipo_de_cambio_banxico(){
    $response = Http::withHeaders([
      'Bmx-Token' => config('services.banxico.token'),
      'Accept' => 'application/json',
    ])
    ->get('https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718/datos/oportuno');
    
    $dato = $response['bmx']['series'][0]['datos'][0]['dato'] ?? null;
    $fecha = $response['bmx']['series'][0]['datos'][0]['fecha'] ?? null;
    
    return response()->json([
      'status' => 'success',
      'clave' => 'Tipo de cambio',
      'valor' => (float) $dato,
      'fecha' => $fecha,
      'fuente' => 'Banxico'
    ]);
  }

  public function indicadores_tiie_banxico(){
    $response = Http::withHeaders([
      'Bmx-Token' => config('services.banxico.token'),
      'Accept' => 'application/json',
    ])
    ->get('https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF60648/datos/oportuno');
    
    $dato = $response['bmx']['series'][0]['datos'][0]['dato'] ?? null;
    $fecha = $response['bmx']['series'][0]['datos'][0]['fecha'] ?? null;
    
    return response()->json([
      'status' => 'success',
      'clave' => 'TIIE',
      'valor' => (float) $dato,
      'fecha' => $fecha,
      'fuente' => 'Banxico'
    ]);
  }
}
