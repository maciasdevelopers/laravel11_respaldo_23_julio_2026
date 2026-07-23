<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\ReembolsoModelo;
use App\Models\JustificacionEmpleadoModelo;
use App\Models\CajaModelo;
use App\Models\CuentBancModelo;
use App\Models\CuentaMonederoModelo;

class CONT_PoliticasController extends Controller{
  //comisiones
  public function politicaComisionesLista(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_politicas_list = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);

        $selectComiPolit = DB::table("cont_politicas_contables AS polit")
          ->join("main_empresas AS emp", "polit.empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          //->join("vhum_empleados_catalogo AS pers","empuser.personal","=","pers.id")
          ->join("teci_catalogo_usuarios AS users", "empuser.usuario", "=", "users.id")
          ->where(["polit.tipo_politica" => "TEC", "polit.status_politicas" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.user_token" => $usuario->user_token])
          ->orderBy("polit.folio_politicas", "DESC")->get();

        foreach ($selectComiPolit as $vPolC) {

          $listAnexosPolit = array();
          $anexos_politica_comi = DB::table("sos_documentos AS docs")
            ->join("cont_politicas_contables AS polit", "docs.politicas_contables", "polit.id")
            ->where(["polit.token_politicas" => $vPolC->token_politicas])->get();

          foreach ($anexos_politica_comi as $vPDoc) {
            $token_docs = $vPDoc->token_documento;
            $tipo_doc = $vPDoc->tipo_documento;
            $ext_doc = $vPDoc->extension_documento;

            $filepath = $vPolC->root_tkn . "/0005-cnt/politicas/comisiones/" . $JwtAuth->generarFolio($vPolC->folio_politicas) . "/";
            $archivo = Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vPDoc->nombre_documento));
            $extension = pathinfo($archivo, PATHINFO_EXTENSION);

            if (file_exists($archivo)) {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento);
              if ($extension == 'pdf') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = '<iframe src="' . $base64 . '" style="border-radius:25px!important;" width="100%" height="100%"></iframe>';
              }

              if ($extension == 'xml') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = file_get_contents($archivo);
              }

              if ($extension == 'jpg' || $extension == 'png') {
                $base64 = $JwtAuth->encriptaBase64($archivo);
                $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
              }
            } else {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento) . " (inexistente)";
              $archivo = Storage::path('public/settings/dont_exist_evidencia.png');
              $base64 = $JwtAuth->encriptaBase64($archivo);
              $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
            }

            $row_doc = array(
              "token_docs" => $token_docs,
              "ext_doc" => $extension,
              "name_documento" => $name_documento,
              "html" => $html,
            );
            $listAnexosPolit[] = $row_doc;
          }


          $row_polit = array(
            "token_politicas" => $vPolC->token_politicas,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($vPolC->fecha_registro),
            "folio_politicas" => "POLIT-" . $JwtAuth->generarFolio($vPolC->folio_politicas),
            "tipo_politica" => $vPolC->tipo_politica,
            "concepto_politica" => $JwtAuth->desencriptar($vPolC->concepto_politica),
            "anexos" => $listAnexosPolit
          );
          $array_politicas_list[] = $row_polit;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "politicas_list" => $array_politicas_list,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function politicaComisionesLast(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_politicas_list = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);

        $selectComiPolit = DB::table("cont_politicas_contables AS polit")
          ->join("main_empresas AS emp", "polit.empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          //->join("vhum_empleado_catalogo AS pers","empuser.personal","=","pers.id")
          ->join("teci_catalogo_usuarios AS users", "empuser.usuario", "=", "users.id")
          ->where(["polit.tipo_politica" => "TEC", "polit.status_politicas" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.user_token" => $usuario->user_token])
          ->orderBy("polit.folio_politicas", "DESC")->limit(1)->get();

        foreach ($selectComiPolit as $vPolC) {
          $anexos_num_list = 1;
          $listAnexosPolit = array();
          $anexos_politica_comi = DB::table("sos_documentos AS docs")
            ->join("cont_politicas_contables AS polit", "docs.politicas_contables", "polit.id")
            ->where(["polit.token_politicas" => $vPolC->token_politicas])->get();

          foreach ($anexos_politica_comi as $vPDoc) {
            $token_docs = $vPDoc->token_documento;
            $tipo_doc = $vPDoc->tipo_documento;
            $ext_doc = $vPDoc->extension_documento;

            $filepath = $vPolC->root_tkn . "/0005-cnt/politicas/comisiones/" . $JwtAuth->generarFolio($vPolC->folio_politicas) . "/";
            $archivo = Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vPDoc->nombre_documento));
            $extension = pathinfo($archivo, PATHINFO_EXTENSION);

            if (file_exists($archivo)) {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento);
              if ($extension == 'pdf') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = '<iframe src="' . $base64 . '" style="border-radius:25px!important;" width="100%" height="500"></iframe>';
              }

              if ($extension == 'xml') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = file_get_contents($archivo);
              }

              if ($extension == 'jpg' || $extension == 'png') {
                $base64 = $JwtAuth->encriptaBase64($archivo);
                $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
              }
            } else {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento) . " (inexistente)";
              $archivo = Storage::path('public/settings/dont_exist_evidencia.png');
              $base64 = $JwtAuth->encriptaBase64($archivo);
              $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
            }

            $row_doc = array(
              "num_list" => $anexos_num_list,
              "token_docs" => $token_docs,
              "ext_doc" => $extension,
              "name_documento" => $name_documento,
              "html" => $html,
            );
            $listAnexosPolit[] = $row_doc;
            ++$anexos_num_list;
          }


          $row_polit = array(
            "token_politicas" => $vPolC->token_politicas,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($vPolC->fecha_registro),
            "folio_politicas" => "POLIT-" . $JwtAuth->generarFolio($vPolC->folio_politicas),
            "tipo_politica" => $vPolC->tipo_politica,
            "concepto_politica" => $JwtAuth->desencriptar($vPolC->concepto_politica),
            "anexos" => $listAnexosPolit
          );
          $array_politicas_list[] = $row_polit;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "politicas_list" => $array_politicas_list,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //reembolsos
  public function politicaReembolsosLista(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_comisiones_true = array();
    $array_comisiones_false = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "token_back_ter" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["token_back_ter"], true);

        $selectComission = DB::table("terc_comisiones_main AS comi")
          ->join("main_empresas AS emp", "comi.empresa", "emp.id")
          ->where(["comi.status" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
          ->orderBy("comi.folio_comision", "DESC")->get();

        foreach ($selectComission as $vComi) {
          $expideComission = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
          foreach ($expideComission as $vExpide) {
            $user_expide = $JwtAuth->desencriptar($vExpide->paterno) . " " .
              $JwtAuth->desencriptar($vExpide->materno) . " " . $JwtAuth->desencriptar($vExpide->nombre);
          }

          $comisionadoQuery = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empleados_catalogos AS pers", "comi.usuario_comision", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
          foreach ($comisionadoQuery as $vComiU) {
            $comisionadoUser = $JwtAuth->desencriptar($vComiU->paterno) . " " .
              $JwtAuth->desencriptar($vComiU->materno) . " " . $JwtAuth->desencriptar($vComiU->nombre);
          }

          if ($vComi->recibe_dinero == TRUE) {
            $sql_recibe_dinero = true;
            $comisionMoneda = DB::table("terc_comisiones_main AS comi")
              ->join("teci_catalogo_monedas AS money", "comi.comision_moneda", "money.id")
              ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
            //var_dump($comisionMoneda);
            foreach ($comisionMoneda as $monCom) {
              $sql_moneda_tkn = $monCom->token_monedas;
              $sql_moneda_name = $monCom->codigo . " " . $monCom->moneda;
              $sql_dinero_recibido = "$" . number_format($vComi->dinero_recibido, $monCom->decimales, '.', ',');
              $sql_dinero_recibido_simple = $vComi->dinero_recibido;
            }
          } else {
            $sql_recibe_dinero = false;
            $sql_moneda_tkn = null;
            $sql_moneda_name = null;
            $sql_dinero_recibido = null;
            $sql_dinero_recibido_simple = null;
          }

          if ($vComi->egresos == TRUE) {
            $sql_califica_egresos = true;
          } else {
            $sql_califica_egresos = false;
          }

          if ($vComi->valor_humano == TRUE) {
            $sql_califica_vhum = true;
          } else {
            $sql_califica_vhum = false;
          }

          $row_comi = array(
            "token_comision_main" => $vComi->token_comision_main,
            "folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
            "fecha_comision" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_comision),
            "comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
            "usuario_expide" => $user_expide,
            "usuario_comision" => $comisionadoUser,
            "especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
            "fecha_programada" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_programada),
            "duracion" => $vComi->duracion,
            "recibe_dinero" => $sql_recibe_dinero,
            "dinero_recibido" => $sql_dinero_recibido,
            "dinero_recibido_simple" => $sql_dinero_recibido_simple,
            "comision_moneda_tkn" => $sql_moneda_tkn,
            "comision_moneda_name" => $sql_moneda_name,
            "comi_tiempo_respuesta" => $vComi->tiempo_respuesta,
            //"ingresos" =>
            "egresos" => $sql_califica_egresos,
            //"finanzas" =>
            "valor_humano" => $sql_califica_vhum,
            //"contabilidad" =>
            //"tec_info" =>
            "ubicacion_latitud" => $vComi->ubicacion_latitud,
            "ubicacion_longitud" => $vComi->ubicacion_longitud,
            "ubicacion_display_name" => $JwtAuth->desencriptar($vComi->ubicacion_display_name),
            //"ubicacion_address" => $vComi->ubicacion_address,
          );
          if ($vComi->concluida == TRUE) {
            $array_comisiones_true[] = $row_comi;
          } else {
            $array_comisiones_false[] = $row_comi;
          }
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "comisiones_true" => $array_comisiones_true,
          "comisiones_false" => $array_comisiones_false,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function politicaReembolsosLast(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_politicas_list = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);

        $selectComiPolit = DB::table("cont_politicas_contables AS polit")
          ->join("main_empresas AS emp", "polit.empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          //->join("vhum_empleados_catalogos AS pers","empuser.personal","=","pers.id")
          ->join("teci_catalogo_usuarios AS users", "empuser.usuario", "=", "users.id")
          ->where(["polit.tipo_politica" => "TER", "polit.status_politicas" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.user_token" => $usuario->user_token])
          ->orderBy("polit.folio_politicas", "DESC")->limit(1)->get();

        foreach ($selectComiPolit as $vPolC) {
          $anexos_num_list = 1;
          $listAnexosPolit = array();
          $anexos_politica_comi = DB::table("sos_documentos AS docs")
            ->join("cont_politicas_contables AS polit", "docs.politicas_contables", "polit.id")
            ->where(["polit.token_politicas" => $vPolC->token_politicas])->get();

          foreach ($anexos_politica_comi as $vPDoc) {
            $token_docs = $vPDoc->token_documento;
            $tipo_doc = $vPDoc->tipo_documento;
            $ext_doc = $vPDoc->extension_documento;

            $filepath = $vPolC->root_tkn . "/0005-cnt/politicas/comisiones/" . $JwtAuth->generarFolio($vPolC->folio_politicas) . "/";
            $archivo = Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vPDoc->nombre_documento));
            $extension = pathinfo($archivo, PATHINFO_EXTENSION);

            if (file_exists($archivo)) {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento);
              if ($extension == 'pdf') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = '<iframe src="' . $base64 . '" style="border-radius:25px!important;" width="100%" height="500"></iframe>';
              }

              if ($extension == 'xml') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = file_get_contents($archivo);
              }

              if ($extension == 'jpg' || $extension == 'png') {
                $base64 = $JwtAuth->encriptaBase64($archivo);
                $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
              }
            } else {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento) . " (inexistente)";
              $archivo = Storage::path('public/settings/dont_exist_evidencia.png');
              $base64 = $JwtAuth->encriptaBase64($archivo);
              $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
            }

            $row_doc = array(
              "num_list" => $anexos_num_list,
              "token_docs" => $token_docs,
              "ext_doc" => $extension,
              "name_documento" => $name_documento,
              "html" => $html,
            );
            $listAnexosPolit[] = $row_doc;
            ++$anexos_num_list;
          }


          $row_polit = array(
            "token_politicas" => $vPolC->token_politicas,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($vPolC->fecha_registro),
            "folio_politicas" => "POLIT-" . $JwtAuth->generarFolio($vPolC->folio_politicas),
            "tipo_politica" => $vPolC->tipo_politica,
            "concepto_politica" => $JwtAuth->desencriptar($vPolC->concepto_politica),
            "anexos" => $listAnexosPolit
          );
          $array_politicas_list[] = $row_polit;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "politicas_list" => $array_politicas_list,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //justificaciones
  public function politicaJustificacionesLista(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_comisiones_true = array();
    $array_comisiones_false = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "token_back_ter" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["token_back_ter"], true);

        $selectComission = DB::table("terc_comisiones_main AS comi")
          ->join("main_empresas AS emp", "comi.empresa", "emp.id")
          ->where(["comi.status" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
          ->orderBy("comi.folio_comision", "DESC")->get();

        foreach ($selectComission as $vComi) {
          $expideComission = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empresa_usuario AS pers", "comi.usuario_expide", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
          foreach ($expideComission as $vExpide) {
            $user_expide = $JwtAuth->desencriptar($vExpide->paterno) . " " .
              $JwtAuth->desencriptar($vExpide->materno) . " " . $JwtAuth->desencriptar($vExpide->nombre);
          }

          $comisionadoQuery = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empleados_catalogos AS pers", "comi.usuario_comision", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
          foreach ($comisionadoQuery as $vComiU) {
            $comisionadoUser = $JwtAuth->desencriptar($vComiU->paterno) . " " .
              $JwtAuth->desencriptar($vComiU->materno) . " " . $JwtAuth->desencriptar($vComiU->nombre);
          }

          if ($vComi->recibe_dinero == TRUE) {
            $sql_recibe_dinero = true;
            $comisionMoneda = DB::table("terc_comisiones_main AS comi")
              ->join("teci_catalogo_monedas AS money", "comi.comision_moneda", "money.id")
              ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
            //var_dump($comisionMoneda);
            foreach ($comisionMoneda as $monCom) {
              $sql_moneda_tkn = $monCom->token_monedas;
              $sql_moneda_name = $monCom->codigo . " " . $monCom->moneda;
              $sql_dinero_recibido = "$" . number_format($vComi->dinero_recibido, $monCom->decimales, '.', ',');
              $sql_dinero_recibido_simple = $vComi->dinero_recibido;
            }
          } else {
            $sql_recibe_dinero = false;
            $sql_moneda_tkn = null;
            $sql_moneda_name = null;
            $sql_dinero_recibido = null;
            $sql_dinero_recibido_simple = null;
          }

          if ($vComi->egresos == TRUE) {
            $sql_califica_egresos = true;
          } else {
            $sql_califica_egresos = false;
          }

          if ($vComi->valor_humano == TRUE) {
            $sql_califica_vhum = true;
          } else {
            $sql_califica_vhum = false;
          }

          $row_comi = array(
            "token_comision_main" => $vComi->token_comision_main,
            "folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
            "fecha_comision" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_comision),
            "comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
            "usuario_expide" => $user_expide,
            "usuario_comision" => $comisionadoUser,
            "especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
            "fecha_programada" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_programada),
            "duracion" => $vComi->duracion,
            "recibe_dinero" => $sql_recibe_dinero,
            "dinero_recibido" => $sql_dinero_recibido,
            "dinero_recibido_simple" => $sql_dinero_recibido_simple,
            "comision_moneda_tkn" => $sql_moneda_tkn,
            "comision_moneda_name" => $sql_moneda_name,
            "comi_tiempo_respuesta" => $vComi->tiempo_respuesta,
            //"ingresos" =>
            "egresos" => $sql_califica_egresos,
            //"finanzas" =>
            "valor_humano" => $sql_califica_vhum,
            //"contabilidad" =>
            //"tec_info" =>
            "ubicacion_latitud" => $vComi->ubicacion_latitud,
            "ubicacion_longitud" => $vComi->ubicacion_longitud,
            "ubicacion_display_name" => $JwtAuth->desencriptar($vComi->ubicacion_display_name),
            //"ubicacion_address" => $vComi->ubicacion_address,
          );
          if ($vComi->concluida == TRUE) {
            $array_comisiones_true[] = $row_comi;
          } else {
            $array_comisiones_false[] = $row_comi;
          }
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "comisiones_true" => $array_comisiones_true,
          "comisiones_false" => $array_comisiones_false,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function politicaJustificacionesLast(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_politicas_list = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);

        $selectComiPolit = DB::table("cont_politicas_contables AS polit")
          ->join("main_empresas AS emp", "polit.empresa", "emp.id")
          ->join("main_empresa_usuario AS emppers", "emp.id", "=", "emppers.empresa")
          ->join("vhum_empleados_catalogos AS pers", "emppers.empleado_name", "=", "pers.id")
          ->join("main_usuarios AS users", "pers.usuario", "=", "users.id")
          ->where(["polit.tipo_politica" => "TEJ", "polit.status_politicas" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.user_token" => $usuario->user_token])
          ->orderBy("polit.folio_politicas", "DESC")->limit(1)->get();

        foreach ($selectComiPolit as $vPolC) {
          $anexos_num_list = 1;
          $listAnexosPolit = array();
          $anexos_politica_comi = DB::table("sos_documentos AS docs")
            ->join("cont_politicas_contables AS polit", "docs.politicas_contables", "polit.id")
            ->where(["polit.token_politicas" => $vPolC->token_politicas])->get();

          foreach ($anexos_politica_comi as $vPDoc) {
            $token_docs = $vPDoc->token_documento;
            $tipo_doc = $vPDoc->tipo_documento;
            $ext_doc = $vPDoc->extension_documento;

            $filepath = $vPolC->root_tkn . "/0005-cnt/politicas/comisiones/" . $JwtAuth->generarFolio($vPolC->folio_politicas) . "/";
            $archivo = Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vPDoc->nombre_documento));
            $extension = pathinfo($archivo, PATHINFO_EXTENSION);

            if (file_exists($archivo)) {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento);
              if ($extension == 'pdf') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = '<iframe src="' . $base64 . '" style="border-radius:25px!important;" width="100%" height="500"></iframe>';
              }

              if ($extension == 'xml') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = file_get_contents($archivo);
              }

              if ($extension == 'jpg' || $extension == 'png') {
                $base64 = $JwtAuth->encriptaBase64($archivo);
                $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
              }
            } else {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento) . " (inexistente)";
              $archivo = Storage::path('public/settings/dont_exist_evidencia.png');
              $base64 = $JwtAuth->encriptaBase64($archivo);
              $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
            }

            $row_doc = array(
              "num_list" => $anexos_num_list,
              "token_docs" => $token_docs,
              "ext_doc" => $extension,
              "name_documento" => $name_documento,
              "html" => $html,
            );
            $listAnexosPolit[] = $row_doc;
            ++$anexos_num_list;
          }


          $row_polit = array(
            "token_politicas" => $vPolC->token_politicas,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($vPolC->fecha_registro),
            "folio_politicas" => "POLIT-" . $JwtAuth->generarFolio($vPolC->folio_politicas),
            "tipo_politica" => $vPolC->tipo_politica,
            "concepto_politica" => $JwtAuth->desencriptar($vPolC->concepto_politica),
            "anexos" => $listAnexosPolit
          );
          $array_politicas_list[] = $row_polit;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "politicas_list" => $array_politicas_list,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //proveedores
  public function politicaProveedoresLista(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_comisiones_true = array();
    $array_comisiones_false = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "token_back_ter" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["token_back_ter"], true);

        $selectComission = DB::table("terc_comisiones_main AS comi")
          ->join("main_empresas AS emp", "comi.empresa", "emp.id")
          ->where(["comi.status" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
          ->orderBy("comi.folio_comision", "DESC")->get();

        foreach ($selectComission as $vComi) {
          $expideComission = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
          foreach ($expideComission as $vExpide) {
            $user_expide = $JwtAuth->desencriptar($vExpide->paterno) . " " .
              $JwtAuth->desencriptar($vExpide->materno) . " " . $JwtAuth->desencriptar($vExpide->nombre);
          }

          $comisionadoQuery = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empleados_catalogos AS pers", "comi.usuario_comision", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
          foreach ($comisionadoQuery as $vComiU) {
            $comisionadoUser = $JwtAuth->desencriptar($vComiU->paterno) . " " .
              $JwtAuth->desencriptar($vComiU->materno) . " " . $JwtAuth->desencriptar($vComiU->nombre);
          }

          if ($vComi->recibe_dinero == TRUE) {
            $sql_recibe_dinero = true;
            $comisionMoneda = DB::table("terc_comisiones_main AS comi")
              ->join("teci_catalogo_monedas AS money", "comi.comision_moneda", "money.id")
              ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
            //var_dump($comisionMoneda);
            foreach ($comisionMoneda as $monCom) {
              $sql_moneda_tkn = $monCom->token_monedas;
              $sql_moneda_name = $monCom->codigo . " " . $monCom->moneda;
              $sql_dinero_recibido = "$" . number_format($vComi->dinero_recibido, $monCom->decimales, '.', ',');
              $sql_dinero_recibido_simple = $vComi->dinero_recibido;
            }
          } else {
            $sql_recibe_dinero = false;
            $sql_moneda_tkn = null;
            $sql_moneda_name = null;
            $sql_dinero_recibido = null;
            $sql_dinero_recibido_simple = null;
          }

          if ($vComi->egresos == TRUE) {
            $sql_califica_egresos = true;
          } else {
            $sql_califica_egresos = false;
          }

          if ($vComi->valor_humano == TRUE) {
            $sql_califica_vhum = true;
          } else {
            $sql_califica_vhum = false;
          }

          $row_comi = array(
            "token_comision_main" => $vComi->token_comision_main,
            "folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
            "fecha_comision" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_comision),
            "comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
            "usuario_expide" => $user_expide,
            "usuario_comision" => $comisionadoUser,
            "especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
            "fecha_programada" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_programada),
            "duracion" => $vComi->duracion,
            "recibe_dinero" => $sql_recibe_dinero,
            "dinero_recibido" => $sql_dinero_recibido,
            "dinero_recibido_simple" => $sql_dinero_recibido_simple,
            "comision_moneda_tkn" => $sql_moneda_tkn,
            "comision_moneda_name" => $sql_moneda_name,
            "comi_tiempo_respuesta" => $vComi->tiempo_respuesta,
            //"ingresos" =>
            "egresos" => $sql_califica_egresos,
            //"finanzas" =>
            "valor_humano" => $sql_califica_vhum,
            //"contabilidad" =>
            //"tec_info" =>
            "ubicacion_latitud" => $vComi->ubicacion_latitud,
            "ubicacion_longitud" => $vComi->ubicacion_longitud,
            "ubicacion_display_name" => $JwtAuth->desencriptar($vComi->ubicacion_display_name),
            //"ubicacion_address" => $vComi->ubicacion_address,
          );
          if ($vComi->concluida == TRUE) {
            $array_comisiones_true[] = $row_comi;
          } else {
            $array_comisiones_false[] = $row_comi;
          }
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "comisiones_true" => $array_comisiones_true,
          "comisiones_false" => $array_comisiones_false,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function politicaProveedoresLast(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_politicas_list = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);

        $selectComiPolit = DB::table("cont_politicas_contables AS polit")
          ->join("main_empresas AS emp", "polit.empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          //->join("vhum_empleados_catalogos AS pers","empuser.empleado_name","=","pers.id")
          ->join("teci_catalogo_usuarios AS users", "empuser.usuario", "=", "users.id")
          ->where(["polit.tipo_politica" => "TEP", "polit.status_politicas" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.user_token" => $usuario->user_token])
          ->orderBy("polit.folio_politicas", "DESC")->limit(1)->get();

        foreach ($selectComiPolit as $vPolC) {
          $anexos_num_list = 1;
          $listAnexosPolit = array();
          $anexos_politica_comi = DB::table("sos_documentos AS docs")
            ->join("cont_politicas_contables AS polit", "docs.politicas_contables", "polit.id")
            ->where(["polit.token_politicas" => $vPolC->token_politicas])->get();

          foreach ($anexos_politica_comi as $vPDoc) {
            $token_docs = $vPDoc->token_documento;
            $tipo_doc = $vPDoc->tipo_documento;
            $ext_doc = $vPDoc->extension_documento;

            $filepath = $vPolC->root_tkn . "/0005-cnt/politicas/comisiones/" . $JwtAuth->generarFolio($vPolC->folio_politicas) . "/";
            $archivo = Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vPDoc->nombre_documento));
            $extension = pathinfo($archivo, PATHINFO_EXTENSION);

            if (file_exists($archivo)) {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento);
              if ($extension == 'pdf') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = '<iframe src="' . $base64 . '" style="border-radius:25px!important;" width="100%" height="500"></iframe>';
              }

              if ($extension == 'xml') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = file_get_contents($archivo);
              }

              if ($extension == 'jpg' || $extension == 'png') {
                $base64 = $JwtAuth->encriptaBase64($archivo);
                $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
              }
            } else {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento) . " (inexistente)";
              $archivo = Storage::path('public/settings/dont_exist_evidencia.png');
              $base64 = $JwtAuth->encriptaBase64($archivo);
              $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
            }

            $row_doc = array(
              "num_list" => $anexos_num_list,
              "token_docs" => $token_docs,
              "ext_doc" => $extension,
              "name_documento" => $name_documento,
              "html" => $html,
            );
            $listAnexosPolit[] = $row_doc;
            ++$anexos_num_list;
          }


          $row_polit = array(
            "token_politicas" => $vPolC->token_politicas,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($vPolC->fecha_registro),
            "folio_politicas" => "POLIT-" . $JwtAuth->generarFolio($vPolC->folio_politicas),
            "tipo_politica" => $vPolC->tipo_politica,
            "concepto_politica" => $JwtAuth->desencriptar($vPolC->concepto_politica),
            "anexos" => $listAnexosPolit
          );
          $array_politicas_list[] = $row_polit;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "politicas_list" => $array_politicas_list,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //detalle    
  public function politicasDetalle(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $politicas_info_array = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "tknPolit" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $tknPolit = $parametrosArray["tknPolit"];

        $selectComiPolit = DB::table("cont_politicas_contables AS polit")
          ->join("main_empresas AS emp", "polit.empresa", "emp.id")
          ->join("main_empresa_usuario AS emppers", "emp.id", "=", "emppers.empresa")
          ->join("vhum_empleados_catalogos AS pers", "emppers.empleado_name", "=", "pers.id")
          ->join("main_usuarios AS users", "pers.usuario", "=", "users.id")
          ->where(["polit.token_politicas" => $tknPolit, "polit.status_politicas" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.user_token" => $usuario->user_token])
          ->orderBy("polit.folio_politicas", "DESC")->get();

        foreach ($selectComiPolit as $vPolC) {

          $listAnexosPolit = array();
          $anexos_num_list = 1;
          $anexos_politica_comi = DB::table("sos_documentos AS docs")
            ->join("cont_politicas_contables AS polit", "docs.politicas_contables", "polit.id")
            ->where(["polit.token_politicas" => $vPolC->token_politicas])->get();

          foreach ($anexos_politica_comi as $vPDoc) {
            $token_docs = $vPDoc->token_documento;
            $tipo_doc = $vPDoc->tipo_documento;
            $ext_doc = $vPDoc->extension_documento;

            $filepath = $vPolC->root_tkn . "/0005-cnt/politicas/comisiones/" . $JwtAuth->generarFolio($vPolC->folio_politicas) . "/";
            $archivo = Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vPDoc->nombre_documento));
            $extension = pathinfo($archivo, PATHINFO_EXTENSION);

            if (file_exists($archivo)) {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento);
              if ($extension == 'pdf') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = '<iframe src="' . $base64 . '" style="border-radius:25px!important;" width="100%" height="500"></iframe>';
              }

              if ($extension == 'xml') {
                $base64 = $JwtAuth->encriptaBase64Pdf($archivo);
                $html = file_get_contents($archivo);
              }

              if ($extension == 'jpg' || $extension == 'jpeg' || $extension == 'png') {
                $base64 = $JwtAuth->encriptaBase64($archivo);
                $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
              }
            } else {
              $name_documento = $JwtAuth->desencriptar($vPDoc->nombre_documento) . " (inexistente)";
              $archivo = Storage::path('public/settings/dont_exist_evidencia.png');
              $base64 = $JwtAuth->encriptaBase64($archivo);
              $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
            }

            $row_doc = array(
              "anexos_num_list" => $anexos_num_list,
              "token_docs" => $token_docs,
              "ext_doc" => $extension,
              "name_documento" => $name_documento,
              "html" => $html,
            );
            $listAnexosPolit[] = $row_doc;
            ++$anexos_num_list;
          }

          if ($vPolC->tipo_politica == "TEC") {
            $tipo_politica = "Comisiones";
          } else if ($vPolC->tipo_politica == "TER") {
            $tipo_politica = "Reembolsos";
          } else if ($vPolC->tipo_politica == "TEJ") {
            $tipo_politica = "Justificación de gastos";
          } else if ($vPolC->tipo_politica == "TEP") {
            $tipo_politica = "Proveedores";
          }

          $row_polit = array(
            "token_politicas" => $vPolC->token_politicas,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($vPolC->fecha_registro),
            "folio_politicas" => "POLIT-" . $JwtAuth->generarFolio($vPolC->folio_politicas),
            "tipo_politica" => $vPolC->tipo_politica,
            "tipo_politica_extend" => $tipo_politica,
            "concepto_politica" => $JwtAuth->desencriptar($vPolC->concepto_politica),
            "anexos" => $listAnexosPolit
          );
          $politicas_info_array[] = $row_polit;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "politica_info" => $politicas_info_array,
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function politica_update(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('solicitud');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "tknPolit" => "required|string",
        "tipo_politica" => "required|string",
        "politica_concepto" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $tknPolit = $parametrosArray["tknPolit"];
        $tipo_politica = $parametrosArray["tipo_politica"];
        $politica_concepto = $parametrosArray["politica_concepto"];
        //$_FILES["docs_anexos_politica"]
        if (
          isset($politica_concepto) && !empty($politica_concepto) && preg_match($JwtAuth->filtroAlfaNumerico(), $politica_concepto) &&
          isset($tipo_politica) && !empty($tipo_politica) && preg_match($JwtAuth->filtroAlfaNumerico(), $tipo_politica) &&
          !empty($_FILES['docs_anexos_politica'])
        ) {

          $selectComiPolit = DB::table("cont_politicas_contables AS polit")
            ->join("main_empresas AS emp", "polit.empresa", "emp.id")
            ->join("main_empresapersonal AS emppers", "emp.id", "=", "emppers.empresa")
            ->join("vhum_empleados_catalogos AS pers", "emppers.empleado_name", "=", "pers.id")
            ->join("main_usuarios AS users", "pers.usuario", "=", "users.id")
            ->where(["polit.token_politicas" => $tknPolit, "polit.status_politicas" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.user_token" => $usuario->user_token])
            ->orderBy("polit.folio_politicas", "DESC")->get();

          foreach ($selectComiPolit as $vPolC) {
            $updated_first = false;
            if ($JwtAuth->desencriptar($vPolC->concepto_politica) != $politica_concepto) {
              $update_polit = DB::table("cont_politicas_contables")->where(["token_politicas" => $vPolC->token_politicas])
                ->limit(1)->update(array("concepto_politica" => $JwtAuth->encriptar($politica_concepto)));
              if ($update_polit) $updated_first = true;
            } else {
              $updated_first = true;
            }


            if ($updated_first == true) {
              $folio_politica = $JwtAuth->generarFolio($vPolC->folio_politicas);
              $folio_politica_completo = "POLIT-" . $JwtAuth->generarFolio($vPolC->folio_politicas);

              $select_id_polit = DB::select("SELECT id FROM cont_politicas_contables WHERE token_politicas = ?", [$vPolC->token_politicas]);
              //return response()->json(['status' => 'error','code' => 200,'message' => 'true3']);
              if ($tipo_politica == "TEC") {
                $filepath = $vPolC->root_tkn . "/0005-cnt/politicas/comisiones/" . $JwtAuth->generarFolio($folio_politica) . "/";
              } else if ($tipo_politica == "TER") {
                $filepath = $vPolC->root_tkn . "/0005-cnt/politicas/reembolsos/" . $JwtAuth->generarFolio($folio_politica) . "/";
              } else if ($tipo_politica == "TEJ") {
                $filepath = $vPolC->root_tkn . "/0005-cnt/politicas/justificaciones/" . $JwtAuth->generarFolio($folio_politica) . "/";
              } else if ($tipo_politica == "TEP") {
                $filepath = $vPolC->root_tkn . "/0005-cnt/politicas/proveedores/" . $JwtAuth->generarFolio($folio_politica) . "/";
              }

              if (!file_exists(storage_path("/root/" . $filepath))) {
                Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
              }

              $evidencias = $_FILES["docs_anexos_politica"];
              $string_name_evid = json_encode($_FILES["docs_anexos_politica"]["name"]);
              if (count(json_decode($string_name_evid)) != 0) {
                //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.3']);
                $evidencia_nombre = json_decode($string_name_evid);
                //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.4']);
                $contador_document = 0;
                for ($i = 0; $i < count($evidencia_nombre); $i++) {
                  $temporal = $evidencias["tmp_name"][$i];
                  $doc_name = $evidencias["name"][$i];

                  Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$i]);

                  if ($tipo_politica == "TEC") {
                    $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%POLIT-TEC%'");
                    $new_folio_doc = "POLIT-TEC" . end($select_folio_doc)->folio;
                  } else if ($tipo_politica == "TER") {
                    $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%POLIT-TER%'");
                    $new_folio_doc = "POLIT-TER" . end($select_folio_doc)->folio;
                  } else if ($tipo_politica == "TEJ") {
                    $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%POLIT-TEJ%'");
                    $new_folio_doc = "POLIT-TEJ" . end($select_folio_doc)->folio;
                  } else if ($tipo_politica == "TEP") {
                    $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%POLIT-TEP%'");
                    $new_folio_doc = "POLIT-TEP" . end($select_folio_doc)->folio;
                  }
                  //return response()->json(['status' => 'error','code' => 200,'message' => 'true4'.$new_folio_doc]); 
                  $token_documento = $JwtAuth->encriptarToken(end($select_id_polit)->id, $usuario->empresa_token, $usuario->user_token, $doc_name, $new_folio_doc);
                  $insertDocSoli = DB::table("sos_documentos")->insert(
                    array(
                      "token_documento" => $token_documento,
                      "fecha_carga" => time(),
                      "modulo" => "contab",
                      "folio_modulo" => $new_folio_doc,
                      "tipo_documento" => "an",
                      "nombre_documento" => $JwtAuth->encriptar($doc_name),
                      "politicas_contables" => end($select_id_polit)->id,
                      "status_documento" => TRUE,
                    )
                  );
                  ++$contador_document;
                }

                if ($contador_document == count($evidencia_nombre)) {
                  $dataMensaje = array('status' => 'success', 'code' => 200, 'message' => 'polit_updated', 'folio_polit' => $folio_politica_completo,);
                }
              } else {
                $dataMensaje = array("status" => "error", "code" => 200, "message" => "polit_reg_fail");
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Error en actualización de politicas'
              );
            }
          }
        } else {
          if (!isset($politica_concepto) || empty($politica_concepto) || !preg_match($JwtAuth->filtroAlfaNumerico(), $politica_concepto)) $mensaje_error = "Error en titulo de política, verifique su información";
          if (empty($_FILES['docs_anexos_politica'])) $mensaje_error = "No hay documentos para cargar, verifique su información";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function politicaNewRegistro(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('solicitud');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_comisiones_true = array();
    $array_comisiones_false = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "politica_concepto" => "required|string",
        "tipo_politica" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $politica_concepto = $parametrosArray["politica_concepto"];
        $tipo_politica = $parametrosArray["tipo_politica"];
        //$_FILES["docs_anexos_politica"]
        if (
          isset($politica_concepto) && !empty($politica_concepto) && preg_match($JwtAuth->filtroAlfaNumerico(), $politica_concepto) &&
          isset($tipo_politica) && !empty($tipo_politica) && preg_match($JwtAuth->filtroAlfaNumerico(), $tipo_politica) &&
          !empty($_FILES['docs_anexos_politica'])
        ) {

          $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,pers.jerarquia FROM main_empresas AS emp  
                        JOIN main_empresapersonal AS emppers JOIN vhum_empleados_catalogos AS pers JOIN main_usuarios AS users WHERE emp.empresa_token = ? 
                        AND emp.id = emppers.empresa AND emppers.empleado_name = pers.id 
                        AND pers.usuario = users.id AND users.user_token = ?", [$usuario->empresa_token, $usuario->user_token]);
          //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

          $new_folio_politica = 1;
          if ($tipo_politica == "TEC") {
            $folioPoliticaComi = DB::select(
              "SELECT polit.folio_politicas+1 AS folio FROM cont_politicas_contables AS polit 
                            JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_empleados_catalogos AS pers JOIN main_usuarios AS users 
                            WHERE polit.tipo_politica = 'TEC' AND polit.status_politicas = TRUE AND polit.empresa = emp.id AND emp.empresa_token = ? 
                            AND emp.id = emppers.empresa AND emppers.empleado_name = pers.id AND pers.usuario = users.id AND users.user_token = ?",
              [$usuario->empresa_token, $usuario->user_token]
            );
            if (count($folioPoliticaComi) == 1) $new_folio_politica = end($folioPoliticaComi)->folio;
          } else if ($tipo_politica == "TER") {
            $folioPoliticaReem = DB::select(
              "SELECT polit.folio_politicas+1 AS folio FROM cont_politicas_contables AS polit 
                            JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_empleados_catalogos AS pers JOIN main_usuarios AS users 
                            WHERE polit.tipo_politica = 'TER' AND polit.status_politicas = TRUE AND polit.empresa = emp.id AND emp.empresa_token = ? 
                            AND emp.id = emppers.empresa AND emppers.empleado_name = pers.id AND pers.usuario = users.id AND users.user_token = ?",
              [$usuario->empresa_token, $usuario->user_token]
            );
            if (count($folioPoliticaReem) == 1) $new_folio_politica = end($folioPoliticaReem)->folio;
          } else if ($tipo_politica == "TEJ") {
            $folioPoliticaJust = DB::select(
              "SELECT polit.folio_politicas+1 AS folio FROM cont_politicas_contables AS polit 
                            JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_empleados_catalogos AS pers JOIN main_usuarios AS users 
                            WHERE polit.tipo_politica = 'TEJ' AND polit.status_politicas = TRUE AND polit.empresa = emp.id AND emp.empresa_token = ? 
                            AND emp.id = emppers.empresa AND emppers.empleado_name = pers.id AND pers.usuario = users.id AND users.user_token = ?",
              [$usuario->empresa_token, $usuario->user_token]
            );
            if (count($folioPoliticaJust) == 1) $new_folio_politica = end($folioPoliticaJust)->folio;
          } else if ($tipo_politica == "TEP") {
            $folioPoliticaProv = DB::select(
              "SELECT polit.folio_politicas+1 AS folio FROM cont_politicas_contables AS polit 
                            JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_empleados_catalogos AS pers JOIN main_usuarios AS users 
                            WHERE polit.tipo_politica = 'TEP' AND polit.status_politicas = TRUE AND polit.empresa = emp.id AND emp.empresa_token = ? 
                            AND emp.id = emppers.empresa AND emppers.empleado_name = pers.id AND pers.usuario = users.id AND users.user_token = ?",
              [$usuario->empresa_token, $usuario->user_token]
            );
            if (count($folioPoliticaProv) == 1) $new_folio_politica = end($folioPoliticaProv)->folio;
          }

          $token_new_politica_all = $JwtAuth->encriptarToken(rand(5, 15) . $new_folio_politica . time() . $politica_concepto . $usuario->empresa_token, $usuario->user_token);

          $insert_new_polit = DB::table('cont_politicas_contables')->insert(
            array(
              "token_politicas" => $token_new_politica_all,
              "fecha_registro" => time(),
              "folio_politicas" => $new_folio_politica,
              "tipo_politica" => $tipo_politica,
              "concepto_politica" => $JwtAuth->encriptar($politica_concepto),
              "status_politicas" => TRUE,
              "empresa" => end($selectEmp)->id
            )
          );

          if ($insert_new_polit) {
            $select_new_polit = DB::select("SELECT id FROM cont_politicas_contables WHERE token_politicas = ?", [$token_new_politica_all]);

            if ($tipo_politica == "TEC") {
              $filepath = $selectEmp[0]->root_tkn . "/0005-cnt/politicas/comisiones/" . $JwtAuth->generarFolio($new_folio_politica) . "/";
            } else if ($tipo_politica == "TER") {
              $filepath = $selectEmp[0]->root_tkn . "/0005-cnt/politicas/reembolsos/" . $JwtAuth->generarFolio($new_folio_politica) . "/";
            } else if ($tipo_politica == "TEJ") {
              $filepath = $selectEmp[0]->root_tkn . "/0005-cnt/politicas/justificaciones/" . $JwtAuth->generarFolio($new_folio_politica) . "/";
            } else if ($tipo_politica == "TEP") {
              $filepath = $selectEmp[0]->root_tkn . "/0005-cnt/politicas/proveedores/" . $JwtAuth->generarFolio($new_folio_politica) . "/";
            }

            if (!file_exists(storage_path("/root/" . $filepath))) {
              Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
            }

            $evidencias = $_FILES["docs_anexos_politica"];
            $string_name_evid = json_encode($_FILES["docs_anexos_politica"]["name"]);
            if (count(json_decode($string_name_evid)) != 0) {
              //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.3']);
              $evidencia_nombre = json_decode($string_name_evid);
              //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.4']);
              $contador_document = 0;
              for ($i = 0; $i < count($evidencia_nombre); $i++) {
                $temporal = $evidencias["tmp_name"][$i];
                $doc_name = $evidencias["name"][$i];
                Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$i]);

                if ($tipo_politica == "TEC") {
                  $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%POLIT-TEC%'");
                  $new_folio_doc = "POLIT-TEC" . end($select_folio_doc)->folio;
                } else if ($tipo_politica == "TER") {
                  $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%POLIT-TER%'");
                  $new_folio_doc = "POLIT-TER" . end($select_folio_doc)->folio;
                } else if ($tipo_politica == "TEJ") {
                  $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%POLIT-TEJ%'");
                  $new_folio_doc = "POLIT-TEJ" . end($select_folio_doc)->folio;
                } else if ($tipo_politica == "TEP") {
                  $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%POLIT-TEP%'");
                  $new_folio_doc = "POLIT-TEP" . end($select_folio_doc)->folio;
                }

                $token_documento = $JwtAuth->encriptarToken(end($select_new_polit)->id, $usuario->empresa_token, $usuario->user_token, $doc_name, $new_folio_doc);
                $insertDocSoli = DB::table("sos_documentos")->insert(
                  array(
                    "token_documento" => $token_documento,
                    "fecha_carga" => time(),
                    "modulo" => "contab",
                    "folio_modulo" => $new_folio_doc,
                    "tipo_documento" => "an",
                    "nombre_documento" => $JwtAuth->encriptar($doc_name),
                    "politicas_contables" => end($select_new_polit)->id,
                    "status_documento" => TRUE,
                  )
                );
                ++$contador_document;
              }

              if ($contador_document == count($evidencia_nombre)) {
                $dataMensaje = array('status' => 'success', 'code' => 200, 'message' => 'polit_saved', 'folio_polit' => "POLIT-" . $JwtAuth->generarFolio($new_folio_politica),);
              }
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "polit_reg_fail");
            }
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "polit_reg_fail");
          }
        } else {
          if (!isset($politica_concepto) || empty($politica_concepto) || !preg_match($JwtAuth->filtroAlfaNumerico(), $politica_concepto)) $mensaje_error = "Error en titulo de política, verifique su información";
          if (empty($_FILES['docs_anexos_politica'])) $mensaje_error = "No hay documentos para cargar, verifique su información";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
