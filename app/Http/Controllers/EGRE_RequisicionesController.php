<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\RequisicionesModelo;

class EGRE_RequisicionesController extends Controller{
  public function totalRequisicionesPendientes(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $folioMax = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
      ->where([
        "emp.empresa_token" => $usuario->empresa_token,
        "users.usuario_token" => $usuario->user_token,
      ])
      ->where('eegr_compras_requisicion.status', '!=', '2')->count();
    return response()->json([
      'totalReq' => $folioMax,
      'codigo' => 200,
      "status" => "success"
    ]);
  }

  public function folioReqMax(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $folioMax = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "emp.empresa_token" => $usuario->empresa_token,
        "users.usuario_token" => $usuario->user_token,
      ])->max('folio');
    return response()->json([
      'folioCompleto' => $JwtAuth->generar($folioMax + 1),
      'folio' => $folioMax + 1,
      'codigo' => 200,
      "status" => "success"
    ]);
  }

  public function catalogoRequisiciones(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayRequisiciones = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);

        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
          $queryRequisiciones = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
            ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
            ->where(["eegr_compras_requisicion.status" => TRUE,"emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])
            ->orderBy('eegr_compras_requisicion.folio', 'DESC')->get();
        } else {
          $queryRequisiciones = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "eegr_compras_requisicion.usuario_requisita", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
            ->where(["eegr_compras_requisicion.status" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])
            ->orderBy('eegr_compras_requisicion.folio', 'DESC')->get();
        }
        //echo count($queryRequisiciones);
        foreach ($queryRequisiciones as $vReq) {
          $token_requisicion = $vReq->token_requisicion;
          $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vReq->folio);
          $requisicion_fecha_registro = gmdate('Y-m-d H:i:s', $vReq->fecha);
          $requisicion_proyecto = $JwtAuth->desencriptar($vReq->proyecto);

          switch ($vReq->prioridad) {
            case 'baj':
              $requisicion_prioridad = "baja";
              break;
            case 'med':
              $requisicion_prioridad = "media";
              break;
            case 'alt':
              $requisicion_prioridad = "alta";
              break;            
            default:
              $requisicion_prioridad = "";
              break;
          }

          $userReqD = DB::table("eegr_compras_requisicion AS req")
            ->join("vhum_empleados_catalogo AS pers", "req.usuario_requisita", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["req.token_requisicion" => $token_requisicion])->get();

          $usuario_requisita = $userReqD[0]->denominacion_rs ? $JwtAuth->desencriptar($userReqD[0]->denominacion_rs) : $JwtAuth->desencriptarNombres($userReqD[0]->paterno, $userReqD[0]->materno, $userReqD[0]->nombre);

          $requisicion_autorizacion = $vReq->autorizacion == TRUE ? "Requisición autorizada (" . gmdate('Y-m-d H:i:s', $vReq->fecha_autorizacion) . ")" : "Requisición no autorizada";
          $requisicion_autorizacion_fecha = $vReq->autorizacion == TRUE ? gmdate('Y-m-d H:i:s', $vReq->fecha_autorizacion) : "---";

          $persona_autoriza = "---";
          if ($vReq->autorizacion == TRUE && $vReq->autoriza_user != NULL) {
            $queryAutoriza = DB::table("vhum_empleados_catalogo AS pers")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where(["pers.id" => $vReq->autoriza_user])->get();

            foreach ($queryAutoriza as $rAutoriza) {
              $emp_den = $rAutoriza->denominacion_rs;
              $persona_autoriza = $emp_den != NULL ? $JwtAuth->desencriptar($emp_den) : $JwtAuth->desencriptarNombres($rAutoriza->paterno, $rAutoriza->materno, $rAutoriza->nombre);
            }
          }

          $selectDetalleAll = DB::table("eegr_compras_requisicion_detalle AS reqDet")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->join("main_empresas AS emp", "reqMain.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "reqDet.status_req" => TRUE,
              "reqMain.token_requisicion" => $token_requisicion,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          $selectDetalleAuthTrue = DB::table("eegr_compras_requisicion_detalle AS reqDet")
            ->join("vhum_empleados_catalogo AS authPers", "reqDet.des_autoriza_user", "=", "authPers.id")
            ->join("sos_personas AS people", "authPers.empleado_name", "=", "people.id")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->join("main_empresas AS emp", "reqMain.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "reqDet.des_autorizacion" => "A",
              "reqMain.token_requisicion" => $token_requisicion,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          $selectDetalleAuthFalse = DB::table("eegr_compras_requisicion_detalle AS reqDet")
            ->join("vhum_empleados_catalogo AS authPers", "reqDet.des_autoriza_user", "=", "authPers.id")
            ->join("sos_personas AS people", "authPers.empleado_name", "=", "people.id")
            ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->join("main_empresas AS emp", "reqMain.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "reqDet.des_autorizacion" => "D",
              "reqMain.token_requisicion" => $token_requisicion,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          $selectDetalleAuthNull = DB::table("eegr_compras_requisicion_detalle AS reqDet")
            ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->join("main_empresas AS emp", "reqMain.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "reqDet.des_autorizacion" => "N",
              "reqMain.token_requisicion" => $token_requisicion,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          $listaSoliCot = DB::table("eegr_compras_cotizacion_solicitud AS soliCot")
            ->join("eegr_compras_requisicion AS reqMain", "soliCot.requisicion", "=", "reqMain.id")
            ->join("main_empresas AS emp", "soliCot.empresa", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "reqMain.token_requisicion" => $token_requisicion,
              "soliCot.status_cotizacion_solicitud" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token
            ])->get();

          $listaCot = DB::table("eegr_compras_cotizacion AS cotList")
            ->join("eegr_compras_requisicion AS reqMain", "cotList.requisicion", "=", "reqMain.id")
            ->join("main_empresas AS emp", "cotList.empresa", "emp.id")
            ->where(["reqMain.token_requisicion" => $token_requisicion, "emp.empresa_token" => $usuario->empresa_token])->get();

          $listaCotAuth = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
            ->join("eegr_compras_cotizacion_detalle AS cotDet", "deskCot.detalle_cotizacion", "=", "cotDet.id")
            ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
            ->join("eegr_compras_requisicion AS reqMain", "cotMain.requisicion", "=", "reqMain.id")
            ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
            //->join("teci_catalogo_monedas AS mon", "emp.e_moneda", "=", "mon.id")
            ->where(["deskCot.coti_desc_autorizacion" => TRUE, "reqMain.token_requisicion" => $token_requisicion, "emp.empresa_token" => $usuario->empresa_token])->get();

          $listaDocumentos = array();
          $queryDocumentos = DB::table("sos_documentos AS docs")
            ->join("eegr_compras_requisicion AS reqMain", "docs.requisicion", "reqMain.id")
            ->where(["docs.status_documento" => TRUE, "reqMain.token_requisicion" => $vReq->token_requisicion])->get();
          //echo count($queryDocumentos);
          foreach ($queryDocumentos as $vDoc) {
            $rowDocs = array(
              "doc_token" => $vDoc->token_documento,
              "doc_name" => $JwtAuth->desencriptar($vDoc->nombre_documento),
              "doc_url" => "https://downloads.sos-mexico.com.mx/compras/requisiciones/" . $requisicion_folio . "/" . $vDoc->token_documento,
            );
            $listaDocumentos[] = $rowDocs;
          }

          $row = array(
            "requisicion_token" => $token_requisicion,
            "requisicion_folio" => $requisicion_folio,
            "requisicion_modal" => "REQ-" . $JwtAuth->generarFolio($vReq->folio) . "_" . date('d-m-Y_H:i:s', $vReq->fecha),
            "requisicion_fecha_registro" => $requisicion_fecha_registro,
            "requisicion_proyecto" => $requisicion_proyecto,
            "requisicion_prioridad" => $requisicion_prioridad,
            "requisicion_usuario_requisita" => $usuario_requisita,
            "requisicion_autorizacion" => $requisicion_autorizacion,
            "requisicion_autorizacion_fecha" => $requisicion_autorizacion_fecha,
            "requisicion_persona_autoriza" => $persona_autoriza,
            "desglose_true" => [],
            "desglose_false" => [],
            "abierto" => false,
            "coments_rechazo_bool" => false,
            "coments_rechazo_text" => "",
            "valida_autorizar" => false,
            "url_pdf" => "https://downloads.sos-mexico.com.mx/requisicion_pdf/" . $token_requisicion,
            "partidasTotal" => count($selectDetalleAll),
            "partidasAuthTrue" => count($selectDetalleAuthTrue),
            "partidasAuthFalse" => count($selectDetalleAuthFalse),
            "partidasAuthNull" => count($selectDetalleAuthNull),
            "cotizaciones_solicitud" => count($listaSoliCot),
            "cotizaciones_list" => count($listaCot),
            "cotizacionesAuth" => count($listaCotAuth),
            "listaDocumentos" => $listaDocumentos,
          );
          $arrayRequisiciones[] = $row;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "requisiciones" => $arrayRequisiciones,
        );
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleRequisicion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $desglose_requi_true = array();
    $desglose_requi_false = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_requisicion = $parametrosArray["token_requisicion"];

        $selectDetalleAuthTrue = DB::table("eegr_compras_requisicion_detalle AS reqDet")
          ->join("vhum_empleados_catalogo AS authPers", "reqDet.des_autoriza_user", "=", "authPers.id")
          ->join("sos_personas AS people", "authPers.empleado_name", "=", "people.id")
          ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
          ->join("main_empresas AS emp", "reqMain.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            "reqDet.des_autorizacion" => "A",
            "reqMain.token_requisicion" => $token_requisicion,
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
          ])->get();

        foreach ($selectDetalleAuthTrue as $vDet) {
          date_default_timezone_set($vDet->zona_horaria);
          $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vDet->folio);
          $det_requi_tipo = $vDet->tipo_necesidad == "Merc" ? "Mercancia" : ($vDet->tipo_necesidad == "Gast" ? "Gastos" : ($vDet->tipo_necesidad == "Acti" ? "Activos" : "Mixto"));

          $list_caract_array = array();
          $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
            ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
            ->where(["reqMain.token_requisicion" => $token_requisicion, "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion])->get();
          //echo count($selectDetReqCaractList);
          foreach ($selectDetReqCaractList as $vCaract) {
            $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
            $descif_valor = $descif_clave == "Precio" ? "$" . number_format($JwtAuth->desencriptar($vCaract->valor), 2, '.', ',') : $JwtAuth->desencriptar($vCaract->valor);
            $row_CaractList = array("token_caract" => $vCaract->token_caract, "clave" => $descif_clave, "valorFront" => $descif_valor, "valorBack" => $JwtAuth->desencriptar($vCaract->valor));
            $list_caract_array[] = $row_CaractList;
          }
          $txt_other_caract = $vDet->caracteristicas_extend != NULL ? $JwtAuth->desencriptar($vDet->caracteristicas_extend) : null;
          $det_requi_unidad_medida_small = $vDet->medida_unidad;
          $det_requi_marca = $vDet->marca != NULL ? $JwtAuth->desencriptar($vDet->marca) :  "no hay marca referida";
          $des_persona_autTrue = $JwtAuth->desencriptarNombres($vDet->paterno, $vDet->materno, $vDet->nombre);

          $archivosPartidaArray = array();
          $selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento 
                        FROM sos_documentos AS evd JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet 
                        WHERE evd.requisicion = reqMain.id AND reqMain.token_requisicion = ?
                        AND evd.detalle_requisicion = reqDet.id AND reqDet.token_detalle_requisicion = ?
                        AND evd.status_documento = TRUE", [$token_requisicion, $vDet->token_detalle_requisicion]);

          if (count($selectIdEvid) > 0) {
            foreach ($selectIdEvid as $vDoc) {
              $each = array(
                "token_documento" => $vDoc->token_documento,
                "tipo_documento" => $vDoc->tipo_documento,
                "nombre_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                "url" => "https://downloads.sos-mexico.com.mx/compras/requisiciones/" . $requisicion_folio . "/" . $vDoc->token_documento,
              );
              $archivosPartidaArray[] = $each;
            }
          }

          $listAuthDetail = array();
          $queryAuthDetail = DB::table("eegr_compras_requisicion_auth AS r_auth")
            ->join("eegr_compras_requisicion AS reqMain", "r_auth.requisicion", "=", "reqMain.id")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "r_auth.partida", "=", "reqDet.id")
            ->join("vhum_empleados_catalogo AS pers", "r_auth.autoriza", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where([
              "reqMain.token_requisicion" => $vDet->token_requisicion,
              "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion,
            ])->get();

          foreach ($queryAuthDetail as $vAuth) {
            $rowAuth = array(
              "folio_auth_requi" => $JwtAuth->generar($vAuth->folio_auth_requi),
              "autorizacion_req" => $vAuth->autorizacion_req,
              "fecha_registro" => gmdate('Y-m-d H:i:s', $vAuth->fecha_registro),
              "autoriza" => $JwtAuth->desencriptarNombres($vAuth->paterno, $vAuth->materno, $vAuth->nombre),
            );
            $listAuthDetail[] = $rowAuth;
          }

          $listCotizacionDetalle = DB::table("eegr_compras_cotizacion_detalle AS c_det")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "c_det.detalle_requisicion", "=", "reqDet.id")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->where([
              "reqMain.token_requisicion" => $vDet->token_requisicion,
              "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion,
            ])->get();

          $rowDet = array(
            "token_detalle_requisicion" => $vDet->token_detalle_requisicion,
            "requi_tipo_back" => $vDet->tipo_necesidad,
            "requi_tipo_front" => $det_requi_tipo,
            "requi_necesidad" => $JwtAuth->desencriptar($vDet->necesidad),
            "requi_caracteristicas_view" => false,
            "requi_caracteristicas_list" => $list_caract_array,
            "requi_caracteristicas_other" => $txt_other_caract,
            "requi_cantidad" => $vDet->cantidad,
            "requi_cantidad_autorizada" => $vDet->cantidad_autorizada,
            //"requi_unidad_medida_token" => $vDet->token_unidad_medida,
            "requi_unidad_medida_name_small" => $det_requi_unidad_medida_small,
            //"requi_unidad_medida_name_extend" => $det_requi_unidad_medida_extend,
            "requi_marca" => $det_requi_marca,
            "bool_requi_autorizacion" => true,
            "char_requi_autorizacion" => "A",
            "date_requi_autorizacion" => gmdate('Y-m-d H:i:s', $vDet->des_fecha_autorizacion),
            "requi_autorizacion_coments_done" => $vDet->autorizacion_comentarios != NULL ? $JwtAuth->desencriptar($vDet->autorizacion_comentarios) : "",
            "requi_autorizacion_coments_write" => "",
            "requi_coments_rechazo_bool" => false,
            "requi_persona_autoriza" => $des_persona_autTrue,
            "archivosPartida" => $archivosPartidaArray,
            "listAuthDetail" => $listAuthDetail,
            "cotizacion_rows" => count($listCotizacionDetalle) > 0 ? true : false,
          );

          if ($vDet->status_req == TRUE) {
            $desglose_requi_true[] = $rowDet;
          } else {
            $desglose_requi_false[] = $rowDet;
          }
        }

        $selectDetalleAuthFalse = DB::table("eegr_compras_requisicion_detalle AS reqDet")
          ->join("vhum_empleados_catalogo AS authPers", "reqDet.des_autoriza_user", "=", "authPers.id")
          ->join("sos_personas AS people", "authPers.empleado_name", "=", "people.id")
          ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
          ->join("main_empresas AS emp", "reqMain.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            "reqDet.des_autorizacion" => "D",
            "reqMain.token_requisicion" => $token_requisicion,
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
          ])->get();

        foreach ($selectDetalleAuthFalse as $vDet) {
          date_default_timezone_set($vDet->zona_horaria);
          $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vDet->folio);
          $det_requi_tipo = $vDet->tipo_necesidad == "Merc" ? "Mercancia" : ($vDet->tipo_necesidad == "Gast" ? "Gastos" : ($vDet->tipo_necesidad == "Acti" ? "Activos" : "Mixto"));

          $list_caract_array = array();
          $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
            ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
            ->where(["reqMain.token_requisicion" => $token_requisicion, "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion])->get();
          //echo count($selectDetReqCaractList);
          foreach ($selectDetReqCaractList as $vCaract) {
            $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
            $descif_valor = $descif_clave == "Precio" ? "$" . number_format($JwtAuth->desencriptar($vCaract->valor), 2, '.', ',') : $JwtAuth->desencriptar($vCaract->valor);
            $row_CaractList = array("token_caract" => $vCaract->token_caract, "clave" => $descif_clave, "valorFront" => $descif_valor, "valorBack" => $JwtAuth->desencriptar($vCaract->valor));
            $list_caract_array[] = $row_CaractList;
          }
          $txt_other_caract = $vDet->caracteristicas_extend != NULL ? $JwtAuth->desencriptar($vDet->caracteristicas_extend) : null;
          $det_requi_unidad_medida_small = $vDet->medida_unidad;
          $det_requi_marca = $vDet->marca != NULL ? $JwtAuth->desencriptar($vDet->marca) :  "no hay marca referida";
          $des_persona_authFalse = $JwtAuth->desencriptarNombres($vDet->paterno, $vDet->materno, $vDet->nombre);

          $archivosPartidaArray = array();
          $selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento 
                        FROM sos_documentos AS evd JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet 
                        WHERE evd.requisicion = reqMain.id AND reqMain.token_requisicion = ?
                        AND evd.detalle_requisicion = reqDet.id AND reqDet.token_detalle_requisicion = ?
                        AND evd.status_documento = TRUE", [$token_requisicion, $vDet->token_detalle_requisicion]);

          if (count($selectIdEvid) > 0) {
            foreach ($selectIdEvid as $vDoc) {
              $each = array(
                "token_documento" => $vDoc->token_documento,
                "tipo_documento" => $vDoc->tipo_documento,
                "nombre_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                "url" => "https://downloads.sos-mexico.com.mx/compras/requisiciones/" . $requisicion_folio . "/" . $vDoc->token_documento,
              );
              $archivosPartidaArray[] = $each;
            }
          }

          $listAuthDetail = array();
          $queryAuthDetail = DB::table("eegr_compras_requisicion_auth AS r_auth")
            ->join("eegr_compras_requisicion AS reqMain", "r_auth.requisicion", "=", "reqMain.id")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "r_auth.partida", "=", "reqDet.id")
            ->join("vhum_empleados_catalogo AS pers", "r_auth.autoriza", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where([
              "reqMain.token_requisicion" => $vDet->token_requisicion,
              "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion,
            ])->get();

          foreach ($queryAuthDetail as $vAuth) {
            $rowAuth = array(
              "folio_auth_requi" => $JwtAuth->generar($vAuth->folio_auth_requi),
              "autorizacion_req" => $vAuth->autorizacion_req,
              "fecha_registro" => gmdate('Y-m-d H:i:s', $vAuth->fecha_registro),
              "autoriza" => $JwtAuth->desencriptarNombres($vAuth->paterno, $vAuth->materno, $vAuth->nombre),
            );
            $listAuthDetail[] = $rowAuth;
          }

          $listCotizacionDetalle = DB::table("eegr_compras_cotizacion_detalle AS c_det")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "c_det.detalle_requisicion", "=", "reqDet.id")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->where([
              "reqMain.token_requisicion" => $vDet->token_requisicion,
              "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion,
            ])->get();

          $rowDet = array(
            "token_detalle_requisicion" => $vDet->token_detalle_requisicion,
            "requi_tipo_back" => $vDet->tipo_necesidad,
            "requi_tipo_front" => $det_requi_tipo,
            "requi_necesidad" => $JwtAuth->desencriptar($vDet->necesidad),
            "requi_caracteristicas_view" => false,
            "requi_caracteristicas_list" => $list_caract_array,
            "requi_caracteristicas_other" => $txt_other_caract,
            "requi_cantidad" => $vDet->cantidad,
            "requi_cantidad_autorizada" => $vDet->cantidad_autorizada,
            //"requi_unidad_medida_token" => $vDet->token_unidad_medida,
            "requi_unidad_medida_name_small" => $det_requi_unidad_medida_small,
            //"requi_unidad_medida_name_extend" => $det_requi_unidad_medida_extend,
            "requi_marca" => $det_requi_marca,
            "bool_requi_autorizacion" => true,
            "char_requi_autorizacion" => "D",
            "date_requi_autorizacion" => gmdate('Y-m-d H:i:s', $vDet->des_fecha_autorizacion),
            "requi_autorizacion_coments_done" => $vDet->autorizacion_comentarios != NULL ? $JwtAuth->desencriptar($vDet->autorizacion_comentarios) : "",
            "requi_autorizacion_coments_write" => "",
            "requi_coments_rechazo_bool" => false,
            "requi_persona_autoriza" => $des_persona_authFalse,
            "archivosPartida" => $archivosPartidaArray,
            "listAuthDetail" => $listAuthDetail,
            "cotizacion_rows" => count($listCotizacionDetalle) > 0 ? true : false,
          );

          if ($vDet->status_req == TRUE) {
            $desglose_requi_true[] = $rowDet;
          } else {
            $desglose_requi_false[] = $rowDet;
          }
        }

        $selectDetalleAuthNull = DB::table("eegr_compras_requisicion_detalle AS reqDet")
          ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
          ->join("main_empresas AS emp", "reqMain.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            "reqDet.des_autorizacion" => "N",
            "reqMain.token_requisicion" => $token_requisicion,
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
          ])->get();

        foreach ($selectDetalleAuthNull as $vDet) {
          date_default_timezone_set($vDet->zona_horaria);
          $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vDet->folio);
          $det_requi_tipo = $vDet->tipo_necesidad == "Merc" ? "Mercancia" : ($vDet->tipo_necesidad == "Gast" ? "Gastos" : ($vDet->tipo_necesidad == "Acti" ? "Activos" : "Mixto"));

          $list_caract_array = array();
          $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
            ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
            ->where(["reqMain.token_requisicion" => $token_requisicion, "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion])->get();
          //echo count($selectDetReqCaractList);
          foreach ($selectDetReqCaractList as $vCaract) {
            $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
            //echo $descif_clave;
            $descif_valor = $descif_clave == "Precio" ? "$" . number_format($JwtAuth->desencriptar($vCaract->valor), 2, '.', ',') : $JwtAuth->desencriptar($vCaract->valor);
            $row_CaractList = array("token_caract" => $vCaract->token_caract, "clave" => $descif_clave, "valorFront" => $descif_valor, "valorBack" => $JwtAuth->desencriptar($vCaract->valor));
            $list_caract_array[] = $row_CaractList;
          }
          $txt_other_caract = $vDet->caracteristicas_extend != NULL ? $JwtAuth->desencriptar($vDet->caracteristicas_extend) : null;
          $det_requi_unidad_medida_small = $vDet->medida_unidad;
          $det_requi_marca = $vDet->marca != NULL ? $JwtAuth->desencriptar($vDet->marca) :  "no hay marca referida";

          $archivosPartidaArray = array();
          $selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento 
                        FROM sos_documentos AS evd JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet 
                        WHERE evd.requisicion = reqMain.id AND reqMain.token_requisicion = ?
                        AND evd.detalle_requisicion = reqDet.id AND reqDet.token_detalle_requisicion = ?
                        AND evd.status_documento = TRUE", [$token_requisicion, $vDet->token_detalle_requisicion]);

          if (count($selectIdEvid) > 0) {
            foreach ($selectIdEvid as $vDoc) {
              $each = array(
                "token_documento" => $vDoc->token_documento,
                "tipo_documento" => $vDoc->tipo_documento,
                "nombre_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                "url" => "https://downloads.sos-mexico.com.mx/compras/requisiciones/" . $requisicion_folio . "/" . $vDoc->token_documento,
              );
              $archivosPartidaArray[] = $each;
            }
          }

          $listCotizacionDetalle = DB::table("eegr_compras_cotizacion_detalle AS c_det")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "c_det.detalle_requisicion", "=", "reqDet.id")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->where([
              "reqMain.token_requisicion" => $vDet->token_requisicion,
              "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion,
            ])->get();

          $rowDet = array(
            "token_detalle_requisicion" => $vDet->token_detalle_requisicion,
            "requi_tipo_back" => $vDet->tipo_necesidad,
            "requi_tipo_front" => $det_requi_tipo,
            "requi_necesidad" => $JwtAuth->desencriptar($vDet->necesidad),
            "requi_caracteristicas_view" => false,
            "requi_caracteristicas_list" => $list_caract_array,
            "requi_caracteristicas_other" => $txt_other_caract,
            "requi_cantidad" => $vDet->cantidad,
            "requi_cantidad_autorizada" => $vDet->cantidad_autorizada,
            //"requi_unidad_medida_token" => $vDet->token_unidad_medida,
            "requi_unidad_medida_name_small" => $det_requi_unidad_medida_small,
            //"requi_unidad_medida_name_extend" => $det_requi_unidad_medida_extend,
            "requi_marca" => $det_requi_marca,
            "bool_requi_autorizacion" => false,
            "char_requi_autorizacion" => "N",
            "requi_autorizacion_coments_write" => "",
            "requi_coments_rechazo_bool" => false,
            "archivosPartida" => $archivosPartidaArray,
          );

          if ($vDet->status_req == TRUE) {
            $desglose_requi_true[] = $rowDet;
          } else {
            $desglose_requi_false[] = $rowDet;
          }
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "desglose_true" => $desglose_requi_true,
          "desglose_false" => $desglose_requi_false,
        );
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleRequisicionCompleto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataRequisicion = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];

        $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
            "eegr_compras_requisicion.status" => TRUE,
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
          ])->get();

        foreach ($queryRequisicionMain as $vReq) {
          $token_requisicion = $vReq->token_requisicion;
          $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vReq->folio);
          $requisicion_fecha_registro = gmdate('Y-m-d H:i:s', $vReq->fecha);
          $requisicion_proyecto = $JwtAuth->desencriptar($vReq->proyecto);

          if ($vReq->prioridad == "baj") {
            $requisicion_prioridad = "baja";
          }
          if ($vReq->prioridad == "med") {
            $requisicion_prioridad = "media";
          }
          if ($vReq->prioridad == "alt") {
            $requisicion_prioridad = "alta";
          }

          $userReqD = DB::table("eegr_compras_requisicion AS req")
            ->join("vhum_empleados_catalogo AS pers", "req.usuario_requisita", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["req.token_requisicion" => $token_requisicion])->get();

          $usuario_requisita = $userReqD[0]->denominacion_rs ? $JwtAuth->desencriptar($userReqD[0]->denominacion_rs) :
            $JwtAuth->desencriptarNombres($userReqD[0]->paterno, $userReqD[0]->materno, $userReqD[0]->nombre);

          $requisicion_autorizacion = $vReq->autorizacion == TRUE ? "Requisición autorizada (" . gmdate('Y-m-d H:i:s', $vReq->fecha_autorizacion) . ")" : "Requisición no autorizada";

          $persona_autoriza = "---";
          if ($vReq->autorizacion == TRUE && $vReq->autoriza_user != NULL) {
            $queryAutoriza = DB::table("vhum_empleados_catalogo AS pers")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where(["pers.id" => $vReq->autoriza_user])->get();

            foreach ($queryAutoriza as $rAutoriza) {
              $persona_autoriza = $rAutoriza->denominacion_rs ? $JwtAuth->desencriptar($rAutoriza->denominacion_rs) : $JwtAuth->desencriptarNombres($rAutoriza->paterno, $rAutoriza->materno, $rAutoriza->nombre);
            }
          }

          $desglose_requi_true = array();
          $selectDetReqTrue = DB::table("eegr_compras_requisicion_detalle AS reqDet")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
            ->where(["reqMain.token_requisicion" => $token_requisicion, "reqDet.status_req" => TRUE])->get();

          foreach ($selectDetReqTrue as $vDet) {
            if ($vDet->tipo_necesidad == "Merc") {
              $det_requi_tipo = "Mercancia";
            }
            if ($vDet->tipo_necesidad == "Gast") {
              $det_requi_tipo = "Gastos";
            }
            if ($vDet->tipo_necesidad == "Acti") {
              $det_requi_tipo = "Activos";
            }
            if ($vDet->tipo_necesidad == "Mixt") {
              $det_requi_tipo = "Mixto";
            }

            $list_caract_array = array();
            $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
              ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
              ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
              ->where(["reqMain.token_requisicion" => $token_requisicion, "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion])->get();
            //echo count($selectDetReqCaractList);
            foreach ($selectDetReqCaractList as $vCaract) {
              $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
              $descif_valor = $descif_clave == "Precio" ? "$" . number_format($JwtAuth->desencriptar($vCaract->valor), 2, '.', ',') : $JwtAuth->desencriptar($vCaract->valor);
              $row_CaractList = array("token_caract" => $vCaract->token_caract, "clave" => $descif_clave, "valorFront" => $descif_valor, "valorBack" => $JwtAuth->desencriptar($vCaract->valor));
              $list_caract_array[] = $row_CaractList;
            }
            $txt_other_caract = $vDet->caracteristicas_extend != NULL ? $JwtAuth->desencriptar($vDet->caracteristicas_extend) : null;
            $det_requi_unidad_medida = $vDet->unidad_medida . " - " . $vDet->sat_clave . ", representa " . $vDet->representa;
            $det_requi_marca = $vDet->marca != NULL ? $JwtAuth->desencriptar($vDet->marca) :  "no hay marca referida";

            $des_persona_autoriza = "---";
            if ($vDet->des_autorizacion == TRUE && $vDet->des_autoriza_user != NULL) {
              $queryAutoriza = DB::table("vhum_empleados_catalogo AS pers")
                ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
                ->where(["pers.id" => $vDet->des_autoriza_user])->get();

              foreach ($queryAutoriza as $rAutoriza) {
                $denominacion_rs = $rAutoriza->denominacion_rs;
                $des_persona_autoriza = $denominacion_rs ? $JwtAuth->desencriptar($denominacion_rs) : $JwtAuth->desencriptarNombres($rAutoriza->paterno, $rAutoriza->materno, $rAutoriza->nombre);
              }
            }

            if ($vDet->des_autorizacion == TRUE) {
              $des_bool_requisicion_autorizacion = true;
              $des_requisicion_autorizacion = "Requisición autorizada por " . $des_persona_autoriza . " (" . gmdate('Y-m-d H:i:s', $vDet->des_fecha_autorizacion) . ")";
            } else {
              $des_bool_requisicion_autorizacion = false;
              $des_requisicion_autorizacion = "Requisición no autorizada";
            }

            $archivosPartidaArray = array();
            $selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento 
                            FROM sos_documentos AS evd JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet 
                            WHERE evd.requisicion = reqMain.id AND reqMain.token_requisicion = ?
                            AND evd.detalle_requisicion = reqDet.id AND reqDet.token_detalle_requisicion = ?
                            AND evd.status_documento = TRUE", [$token_requisicion, $vDet->token_detalle_requisicion]);

            if (count($selectIdEvid) > 0) {
              $filepath = $vReq->root_tkn . "/0002-cpp/compras/requisiciones/" . $requisicion_folio;
              foreach ($selectIdEvid as $valEvid) {
                $token_documento = $valEvid->token_documento;

                if ($valEvid->tipo_documento == "file") {
                  $name_evidencia = $JwtAuth->desencriptar($valEvid->nombre_documento);
                  //$logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/root/'.$filepath.'/'.$evidencia_decode[$i]));
                  $nombre_documento = Storage::path('public/root/' . $filepath . '/' . $name_evidencia);
                  $extension = pathinfo($nombre_documento, PATHINFO_EXTENSION);

                  if ($extension == 'pdf') {
                    $each = array(
                      "token_evidencia" => $token_documento,
                      "tipo_evidencia" => $valEvid->tipo_documento,
                      "name_evidencia" => $name_evidencia,
                      "extension" => $extension,
                      "crudo" => $JwtAuth->encriptaBase64Pdf($nombre_documento),
                      "html" => "",
                    );
                    $archivosPartidaArray[] = $each;
                  }

                  if (
                    $extension == 'doc' ||
                    $extension == 'dot' ||
                    $extension == 'docx' ||
                    $extension == 'dotx' ||
                    $extension == 'docm' ||
                    $extension == 'dotm' ||
                    //excel
                    $extension == 'xls' ||
                    $extension == 'xlt' ||
                    $extension == 'xla' ||
                    $extension == 'xlsx' ||
                    $extension == 'xltx' ||
                    $extension == 'xlsm' ||
                    $extension == 'xltm' ||
                    $extension == 'xlam' ||
                    $extension == 'xlsb'
                  ) {

                    //https://view.officeapps.live.com/op/view.aspx?src=https%3A%2F%2Fbackend.sos-mexico.com.mx%2Fstorage%2Fapp%2Fpublic%2Froot%2FrootSTZTMzhQUG9ZSmlXVWVQd2dLN3JJRnQyMGYvSmhn%2F0008-proyectos%2FPROY-000000000%2FTAR-000000002%2FINF-000000047%2FEscrito%2520Inicial%2520de%2520Denuncia%2520de%2520Hechos%2520ultimo.docx&wdOrigin=BROWSELINK
                    //http://backend.sos-mexico.com.mx/storage/app/public/root/rootSTZTMzhQUG9ZSmlXVWVQd2dLN3JJRnQyMGYvSmhn/0008-proyectos/PROY-000000000/TAR-000000002/INF-000000047/Escrito%20Inicial%20de%20Denuncia%20de%20Hechos%20ultimo.docx

                    $url_codigo = "http://backend.sos-mexico.com.mx/storage/app/root/" . $filepath . "/" . $name_evidencia;
                    //echo $url_codigo;exit; 
                    $each = array(
                      "token_evidencia" => $token_documento,
                      "tipo_evidencia" => $valEvid->tipo_documento,
                      "name_evidencia" => $name_evidencia,
                      "extension" => $extension,
                      //"crudo" => "https://docs.google.com/gview?src=".$url_codigo,
                      "crudo" => $url_codigo,
                      "html" => "",
                    );
                    $archivosPartidaArray[] = $each;
                  }

                  if ($extension == 'jpg' || $extension == 'jpeg' || $extension == 'png') {
                    $each = array(
                      "token_evidencia" => $token_documento,
                      "tipo_evidencia" => $valEvid->tipo_documento,
                      "name_evidencia" => $name_evidencia,
                      "extension" => $extension,
                      "crudo" => $JwtAuth->encriptaBase64($nombre_documento),
                    );
                    $archivosPartidaArray[] = $each;
                  }
                }

                if ($valEvid->tipo_documento == "link") {
                  $name_evidencia = $JwtAuth->desencriptar($valEvid->nombre_documento);
                  $extension = pathinfo($name_evidencia, PATHINFO_EXTENSION);
                  $f = explode("/", $name_evidencia);
                  $doc_name = $f[count(explode("/", $name_evidencia)) - 1];
                  $doc_name = str_replace("%20", " ", $doc_name);
                  //echo $arch." "; str_replace("world","Peter","Hello world!")

                  $each = array(
                    "token_evidencia" => $token_documento,
                    "tipo_evidencia" => $valEvid->tipo_documento,
                    "name_evidencia" => $doc_name,
                    "crudo" => $name_evidencia,
                    "extension" => $extension,
                    "html" => "",
                  );
                  $archivosPartidaArray[] = $each;
                }
              }
            }

            $cotizacionesListaArray = array();

            $rowDet = array(
              "token_detalle_requisicion" => $vDet->token_detalle_requisicion,
              "requi_tipo_back" => $vDet->tipo,
              "requi_tipo_front" => $det_requi_tipo,
              "requi_necesidad" => $JwtAuth->desencriptar($vDet->necesidad),
              "requi_caracteristicas_view" => false,
              "requi_caracteristicas_list" => $list_caract_array,
              "requi_caracteristicas_other" => $txt_other_caract,
              "requi_cantidad" => $vDet->cantidad,
              "requi_unidad_medida_token" => $vDet->token_unidad_medida,
              "requi_unidad_medida_name" => $det_requi_unidad_medida,
              "requi_marca" => $det_requi_marca,
              "bool_requi_autorizacion" => $des_bool_requisicion_autorizacion,
              "requi_autorizacion" => $des_requisicion_autorizacion,
              "requi_persona_autoriza" => $des_persona_autoriza,
              "archivosPartida" => $archivosPartidaArray,
              "auth_cotizacion" => false,
            );
            $desglose_requi_true[] = $rowDet;
          }

          $desglose_requi_false = array();
          $selectDetReqFalse = DB::table("eegr_compras_requisicion_detalle AS reqDet")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
            ->where(["reqMain.token_requisicion" => $token_requisicion, "reqDet.status_req" => FALSE])->get();

          foreach ($selectDetReqFalse as $vDef) {
            if ($vDef->tipo == "Merc") {
              $det_requi_tipo = "Mercancia";
            }
            if ($vDef->tipo == "Gast") {
              $det_requi_tipo = "Gastos";
            }
            if ($vDef->tipo == "Acti") {
              $det_requi_tipo = "Activos";
            }
            if ($vDef->tipo == "Mixt") {
              $det_requi_tipo = "Mixto";
            }

            $list_caract = NULL;
            if ($vDef->caracteristicas != NULL) $list_caract = json_decode($JwtAuth->desencriptar($vDef->caracteristicas));

            $txt_other_caract = NULL;
            if ($vDef->caracteristicas_extend != NULL) $txt_other_caract = $JwtAuth->desencriptar($vDef->caracteristicas_extend);

            $det_requi_unidad_medida = $vDef->unidad_medida . " - " . $vDef->sat_clave . ", representa " . $vDef->representa;

            $det_requi_marca = NULL;
            if ($vDef->marca != NULL) $det_requi_marca = $JwtAuth->desencriptar($vDef->marca);

            $rowDet = array(
              "token_detalle_requisicion" => $vDef->token_detalle_requisicion,
              "requi_tipo_back" => $vDef->tipo,
              "requi_tipo_front" => $det_requi_tipo,
              "requi_necesidad" => $JwtAuth->desencriptar($vDef->necesidad),
              "requi_caracteristicas_list" => $list_caract,
              "requi_caracteristicas_other" => $txt_other_caract,
              "requi_cantidad" => $vDef->cantidad,
              "requi_unidad_medida_token" => $vDef->token_unidad_medida,
              "requi_unidad_medida_name" => $det_requi_unidad_medida,
              "requi_marca" => $det_requi_marca,
            );
            $desglose_requi_false[] = $rowDet;
          }

          $row = array(
            "requisicion_token" => $token_requisicion,
            "requisicion_folio" => $requisicion_folio,
            "requisicion_fecha_registro" => $requisicion_fecha_registro,
            "requisicion_proyecto" => $requisicion_proyecto,
            "requisicion_prioridad" => $requisicion_prioridad,
            "requisicion_usuario_requisita" => $usuario_requisita,
            "requisicion_autorizacion" => $requisicion_autorizacion,
            "requisicion_persona_autoriza" => $persona_autoriza,
            "desglose_true" => $desglose_requi_true,
            "desglose_false" => $desglose_requi_false,
            //"class_desglose" => "col s12",
            //"class_show" => "col s12 noneView",
            "class_desglose" => "col s12 m8 l9 xl9",
            "class_show" => "col s12 m4 l3 xl3",
          );
          $dataRequisicion[] = $row;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "requisiciones" => $dataRequisicion,
        );
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleRequisicionWithCotizaciones(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataRequisicion = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];

        $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
            "eegr_compras_requisicion.status" => TRUE,
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
          ])->get();

        foreach ($queryRequisicionMain as $vReq) {
          $token_requisicion = $vReq->token_requisicion;
          $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vReq->folio);
          $requisicion_fecha_registro = gmdate('Y-m-d H:i:s', $vReq->fecha);
          $requisicion_proyecto = $JwtAuth->desencriptar($vReq->proyecto);

          if ($vReq->prioridad == "baj") {
            $requisicion_prioridad = "baja";
          }
          if ($vReq->prioridad == "med") {
            $requisicion_prioridad = "media";
          }
          if ($vReq->prioridad == "alt") {
            $requisicion_prioridad = "alta";
          }

          $reqUserRequisita = DB::table("eegr_compras_requisicion AS req")
            ->join("vhum_empleados_catalogo AS pers", "req.usuario_requisita", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["req.token_requisicion" => $token_requisicion])->get();

          if ($reqUserRequisita[0]->denominacion_rs) {
            $usuario_requisita = $JwtAuth->desencriptar($reqUserRequisita[0]->denominacion_rs);
          } else {
            $usuario_requisita = $JwtAuth->desencriptar($reqUserRequisita[0]->paterno) . " " .
              $JwtAuth->desencriptar($reqUserRequisita[0]->materno) . " " . $JwtAuth->desencriptar($reqUserRequisita[0]->nombre);
          }

          if ($vReq->autorizacion == TRUE) {
            $requisicion_autorizacion = "Requisición autorizada (" . gmdate('Y-m-d H:i:s', $vReq->fecha_autorizacion) . ")";
          }
          if ($vReq->autorizacion == FALSE) {
            $requisicion_autorizacion = "Requisición no autorizada";
          }

          $persona_autoriza = "---";
          if ($vReq->autorizacion == TRUE && $vReq->autoriza_user != NULL) {
            $queryAutoriza = DB::table("vhum_empleados_catalogo AS pers")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where(["pers.id" => $vReq->autoriza_user])->get();

            foreach ($queryAutoriza as $rAutoriza) {
              if ($rAutoriza->denominacion_rs) {
                $persona_autoriza = $JwtAuth->desencriptar($rAutoriza->denominacion_rs);
              } else {
                $persona_autoriza = $JwtAuth->desencriptar($rAutoriza->paterno) . " " . $JwtAuth->desencriptar($rAutoriza->materno) . " " . $JwtAuth->desencriptar($rAutoriza->nombre);
              }
            }
          }

          $desglose_requi_true = array();
          $selectDetReqTrue = DB::table("eegr_compras_requisicion_detalle AS reqDet")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
            ->where(["reqMain.token_requisicion" => $token_requisicion, "reqDet.status_req" => TRUE, "reqDet.des_autorizacion" => TRUE])->get();

          foreach ($selectDetReqTrue as $vDet) {
            if ($vDet->tipo_necesidad == "Merc") {
              $det_requi_tipo = "Mercancia";
            }
            if ($vDet->tipo_necesidad == "Gast") {
              $det_requi_tipo = "Gastos";
            }
            if ($vDet->tipo_necesidad == "Acti") {
              $det_requi_tipo = "Activos";
            }
            if ($vDet->tipo_necesidad == "Mixt") {
              $det_requi_tipo = "Mixto";
            }

            $list_caract_array = array();
            $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
              ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
              ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
              ->where(["reqMain.token_requisicion" => $token_requisicion, "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion])->get();
            //echo count($selectDetReqCaractList);
            foreach ($selectDetReqCaractList as $vCaract) {
              $descif_clave = $JwtAuth->desencriptar($vCaract->clave);

              if ($descif_clave == "Precio") {
                $descif_valor = "$" . number_format($JwtAuth->desencriptar($vCaract->valor), 2, '.', ',');
              } else {
                $descif_valor = $JwtAuth->desencriptar($vCaract->valor);
              }

              $row_CaractList = array("token_caract" => $vCaract->token_caract, "clave" => $descif_clave, "valorFront" => $descif_valor, "valorBack" => $JwtAuth->desencriptar($vCaract->valor));
              $list_caract_array[] = $row_CaractList;
            }

            $txt_other_caract = null;
            if ($vDet->caracteristicas_extend != NULL) $txt_other_caract = $JwtAuth->desencriptar($vDet->caracteristicas_extend);

            $det_requi_unidad_medida = $vDet->unidad_medida . " - " . $vDet->sat_clave . ", representa " . $vDet->representa;

            $det_requi_marca = "no hay marca referida";
            if ($vDet->marca != NULL) $det_requi_marca = $JwtAuth->desencriptar($vDet->marca);

            $des_persona_autoriza = "---";
            if ($vDet->des_autorizacion == TRUE && $vDet->des_autoriza_user != NULL) {
              $queryAutoriza = DB::table("vhum_empleados_catalogo AS pers")
                ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
                ->where(["pers.id" => $vDet->des_autoriza_user])->get();

              foreach ($queryAutoriza as $rAutoriza) {
                if ($rAutoriza->denominacion_rs) {
                  $des_persona_autoriza = $JwtAuth->desencriptar($rAutoriza->denominacion_rs);
                } else {
                  $des_persona_autoriza = $JwtAuth->desencriptar($rAutoriza->paterno) . " " . $JwtAuth->desencriptar($rAutoriza->materno) . " " . $JwtAuth->desencriptar($rAutoriza->nombre);
                }
              }
            }

            if ($vDet->des_autorizacion == TRUE) {
              $des_requisicion_autorizacion = "Requisición autorizada por " . $des_persona_autoriza . " (" . gmdate('Y-m-d H:i:s', $vDet->des_fecha_autorizacion) . ")";
            }
            if ($vDet->des_autorizacion == FALSE) {
              $des_requisicion_autorizacion = "Requisición no autorizada";
            }

            $archivosPartidaArray = array();
            $selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento 
                            FROM sos_documentos AS evd JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet 
                            WHERE evd.requisicion = reqMain.id AND reqMain.token_requisicion = ?
                            AND evd.detalle_requisicion = reqDet.id AND reqDet.token_detalle_requisicion = ?
                            AND evd.status_documento = TRUE", [$token_requisicion, $vDet->token_detalle_requisicion]);

            if (count($selectIdEvid) > 0) {
              $filepath = $vReq->root_tkn . "/0002-cpp/compras/requisiciones/" . $requisicion_folio . "/";
              foreach ($selectIdEvid as $valEvid) {
                $token_documento = $valEvid->token_documento;

                if ($valEvid->tipo_documento == "file") {
                  $name_evidencia = $JwtAuth->desencriptar($valEvid->nombre_documento);
                  //$logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/root/'.$filepath.'/'.$evidencia_decode[$i]));
                  $nombre_documento = Storage::path('public/root/' . $filepath . $name_evidencia);
                  $extension = pathinfo($nombre_documento, PATHINFO_EXTENSION);

                  if (file_exists($nombre_documento)) {
                    if ($extension == 'pdf') {
                      $each = array(
                        "token_evidencia" => $token_documento,
                        "tipo_evidencia" => $valEvid->tipo_documento,
                        "name_evidencia" => $name_evidencia,
                        "extension" => $extension,
                        "crudo" => $JwtAuth->encriptaBase64Pdf($nombre_documento),
                        "html" => "",
                      );
                      $archivosPartidaArray[] = $each;
                    }

                    if (
                      $extension == 'doc' || $extension == 'dot' || $extension == 'docx' || $extension == 'dotx' || $extension == 'docm' ||
                      $extension == 'dotm' || $extension == 'xls' || $extension == 'xlt' || $extension == 'xla' || $extension == 'xlsx' ||
                      $extension == 'xltx' || $extension == 'xlsm' || $extension == 'xltm' || $extension == 'xlam' || $extension == 'xlsb'
                    ) {

                      //https://view.officeapps.live.com/op/view.aspx?src=https%3A%2F%2Fbackend.sos-mexico.com.mx%2Fstorage%2Fapp%2Fpublic%2Froot%2FrootSTZTMzhQUG9ZSmlXVWVQd2dLN3JJRnQyMGYvSmhn%2F0008-proyectos%2FPROY-000000000%2FTAR-000000002%2FINF-000000047%2FEscrito%2520Inicial%2520de%2520Denuncia%2520de%2520Hechos%2520ultimo.docx&wdOrigin=BROWSELINK
                      //http://backend.sos-mexico.com.mx/storage/app/public/root/rootSTZTMzhQUG9ZSmlXVWVQd2dLN3JJRnQyMGYvSmhn/0008-proyectos/PROY-000000000/TAR-000000002/INF-000000047/Escrito%20Inicial%20de%20Denuncia%20de%20Hechos%20ultimo.docx

                      $url_codigo = "http://backend.sos-mexico.com.mx/storage/app/root/" . $filepath . "/" . $name_evidencia;
                      //echo $url_codigo;exit; 
                      $each = array(
                        "token_evidencia" => $token_documento,
                        "tipo_evidencia" => $valEvid->tipo_documento,
                        "name_evidencia" => $name_evidencia,
                        "extension" => $extension,
                        //"crudo" => "https://docs.google.com/gview?src=".$url_codigo,
                        "crudo" => $url_codigo,
                        "html" => "",
                      );
                      $archivosPartidaArray[] = $each;
                    }

                    if ($extension == 'jpg' || $extension == 'jpeg' || $extension == 'png') {
                      $each = array(
                        "token_evidencia" => $token_documento,
                        "tipo_evidencia" => $valEvid->tipo_documento,
                        "name_evidencia" => $name_evidencia,
                        "extension" => $extension,
                        "crudo" => $JwtAuth->encriptaBase64($nombre_documento),
                      );
                      $archivosPartidaArray[] = $each;
                    }
                  } else {
                    $name_evidencia = $JwtAuth->desencriptar($valEvid->nombre_documento) . " (inexistente)";
                    $archivo = Storage::path('public/settings/dont_exist_evidencia.png');
                    $base64 = $JwtAuth->encriptaBase64($archivo);
                    $html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
                    $each = array(
                      "token_evidencia" => $token_documento,
                      "tipo_evidencia" => $valEvid->tipo_documento,
                      "name_evidencia" => $name_evidencia,
                      "extension" => $extension,
                      "crudo" => $JwtAuth->encriptaBase64($archivo),
                    );
                    $archivosPartidaArray[] = $each;
                  }
                }

                if ($valEvid->tipo_documento == "link") {
                  $name_evidencia = $JwtAuth->desencriptar($valEvid->nombre_documento);
                  $extension = pathinfo($name_evidencia, PATHINFO_EXTENSION);
                  $f = explode("/", $name_evidencia);
                  $doc_name = $f[count(explode("/", $name_evidencia)) - 1];
                  $doc_name = str_replace("%20", " ", $doc_name);
                  //echo $arch." "; str_replace("world","Peter","Hello world!")

                  $each = array(
                    "token_evidencia" => $token_documento,
                    "tipo_evidencia" => $valEvid->tipo_documento,
                    "name_evidencia" => $doc_name,
                    "crudo" => $name_evidencia,
                    "extension" => $extension,
                    "html" => "",
                  );
                  $archivosPartidaArray[] = $each;
                }
              }
            }

            $cotizacionesListaArray = array();

            /*$selectcotizacionesDone = DB::table("eegr_compras_cotizacion_detalle_descripcion AS descDetCot")
                        ->join("eegr_catalogo_proveedores AS catprov","descDetCot.proveedor","=","catprov.id")
                        ->join("sos_personas AS prov","catprov.proveedor","=","prov.id")
                        ->join("teci_catalogo_monedas AS money","descDetCot.coti_moneda","=","money.id")
                        ->join("eegr_compras_cotizacion_detalle AS dettCot","descDetCot.detalle_cotizacion","=","dettCot.id")
                        ->join("eegr_compras_cotizacion AS cotMain","dettCot.cotizacion","=","cotMain.id")
                        ->join("eegr_compras_requisicion_detalle AS reqDet","dettCot.detalle_requisicion","=","reqDet.id")
                        ->join("eegr_compras_requisicion AS reqMain","reqDet.requisicion","=","reqMain.id")
                        ->where(["reqMain.token_requisicion" => $token_requisicion,"reqDet.status_req" => TRUE])->get();*/

            $selectcotizacionesDone = DB::table("eegr_compras_cotizacion_detalle_descripcion AS descDetCot")
              ->join("eegr_catalogo_proveedores AS catprov", "descDetCot.coti_proveedor", "=", "catprov.id")
              ->join("sos_personas AS prov", "catprov.proveedor", "=", "prov.id")
              ->join("teci_catalogo_monedas AS money", "descDetCot.coti_moneda", "=", "money.id")
              ->join("eegr_compras_cotizacion_detalle AS dettCot", "descDetCot.detalle_cotizacion", "=", "dettCot.id")
              ->join("eegr_compras_cotizacion AS cotMain", "dettCot.cotizacion", "=", "cotMain.id")
              ->join("eegr_compras_requisicion_detalle AS reqDet", "dettCot.detalle_requisicion", "=", "reqDet.id")
              ->where(["reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion])
              ->orderBy('cotMain.coti_folio', 'DESC')->get();
            //echo count($selectcotizacionesDone); 

            foreach ($selectcotizacionesDone as $vCott) {
              if ($vCott->denominacion_rs != '') {
                $nombreProv = $JwtAuth->desencriptar($vCott->denominacion_rs);
              } else {
                $nombreProv = $JwtAuth->desencriptar($vCott->paterno) . " " .
                  $JwtAuth->desencriptar($vCott->materno) . " " .
                  $JwtAuth->desencriptar($vCott->nombre);
              }

              $rfc_generico = $vCott->rfc_generico;

              if ($vCott->rfc != NULL) {
                $rfc_prov = $JwtAuth->desencriptar($vCott->rfc);
              } else {
                $rfc_prov = '---';
              }

              if ($vCott->tax_id != NULL) {
                $tax_id_prov = $JwtAuth->desencriptar($vCott->tax_id);
              } else {
                $tax_id_prov = '---';
              }

              if ($vCott->temp_folio != NULL) {
                $folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($vCott->temp_folio);
              } else {
                $folio_prov = 'PRV-' . $JwtAuth->generarFolio($vCott->folio);
                if ($vCott->post_folio != NULL) $folio_prov = $folio_prov . '-' . $vCott->post_folio;
              }

              $coti_desc_autorizacion = false;
              if ($vCott->coti_desc_autorizacion == TRUE) {
                $coti_desc_autorizacion = true;
              }
              //coti_desc_fecha_autorizacion	
              //coti_desc_pers_autoriza

              $rowcot = array(
                "token_cotizacion" => $vCott->token_cotizacion,
                "coti_fecha_sistema" => gmdate('Y-m-d H:i:s', $vCott->coti_fecha_sistema),
                "coti_folio" => "COT-" . $JwtAuth->generarFolio($vCott->coti_folio),
                "token_detalle_cotizacion" => $vCott->token_detalle_cotizacion,
                "token_desc_detalle_cotiza" => $vCott->token_desc_detalle_cotiza,
                "folio_prov" => $folio_prov,
                "rfc_generico" => $rfc_generico,
                "rfc_prov" => $rfc_prov,
                "tax_id_prov" => $tax_id_prov,
                "nombre_prov" => $nombreProv,
                "coti_necesidad" => $JwtAuth->desencriptar($vCott->coti_necesidad),
                "coti_marca" => $JwtAuth->desencriptar($vCott->coti_marca),
                "coti_cantidad" => $vCott->coti_cantidad,
                "coti_precio" => "$" . number_format($vCott->coti_precio, $vCott->decimales, '.', ','),
                "coti_moneda_token_monedas" => $vCott->token_monedas,
                "coti_moneda_codigo" => $vCott->codigo,
                "coti_moneda" => $vCott->moneda,
                "coti_desc_autorizacion" => $coti_desc_autorizacion,
              );
              $cotizacionesListaArray[] = $rowcot;
            }

            $rowDet = array(
              "token_detalle_requisicion" => $vDet->token_detalle_requisicion,
              "requi_tipo_back" => $vDet->tipo,
              "requi_tipo_front" => $det_requi_tipo,
              "requi_necesidad" => $JwtAuth->desencriptar($vDet->necesidad),
              "requi_caracteristicas_view" => false,
              "requi_caracteristicas_list" => $list_caract_array,
              "requi_caracteristicas_other" => $txt_other_caract,
              "requi_cantidad" => $vDet->cantidad,
              "requi_unidad_medida_token" => $vDet->token_unidad_medida,
              "requi_unidad_medida_name" => $det_requi_unidad_medida,
              "requi_marca" => $det_requi_marca,
              "requi_autorizacion" => $des_requisicion_autorizacion,
              "requi_persona_autoriza" => $des_persona_autoriza,
              "archivosPartida" => $archivosPartidaArray,
              "cotizaciones_view" => false,
              "cotizacionesLista" => $cotizacionesListaArray,
            );
            $desglose_requi_true[] = $rowDet;
          }

          $desglose_requi_false = array();
          $selectDetReqFalse = DB::table("eegr_compras_requisicion_detalle AS reqDet")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
            ->where(["reqMain.token_requisicion" => $token_requisicion, "reqDet.status_req" => FALSE])->get();

          foreach ($selectDetReqFalse as $vDef) {
            if ($vDef->tipo == "Merc") {
              $det_requi_tipo = "Mercancia";
            }
            if ($vDef->tipo == "Gast") {
              $det_requi_tipo = "Gastos";
            }
            if ($vDef->tipo == "Acti") {
              $det_requi_tipo = "Activos";
            }
            if ($vDef->tipo == "Mixt") {
              $det_requi_tipo = "Mixto";
            }

            $list_caract = NULL;
            if ($vDef->caracteristicas != NULL) $list_caract = json_decode($JwtAuth->desencriptar($vDef->caracteristicas));

            $txt_other_caract = NULL;
            if ($vDef->caracteristicas_extend != NULL) $txt_other_caract = $JwtAuth->desencriptar($vDef->caracteristicas_extend);

            $det_requi_unidad_medida = $vDef->unidad_medida . " - " . $vDef->sat_clave . ", representa " . $vDef->representa;

            $det_requi_marca = NULL;
            if ($vDef->marca != NULL) $det_requi_marca = $JwtAuth->desencriptar($vDef->marca);

            $rowDet = array(
              "token_detalle_requisicion" => $vDef->token_detalle_requisicion,
              "requi_tipo_back" => $vDef->tipo,
              "requi_tipo_front" => $det_requi_tipo,
              "requi_necesidad" => $JwtAuth->desencriptar($vDef->necesidad),
              "requi_caracteristicas_list" => $list_caract,
              "requi_caracteristicas_other" => $txt_other_caract,
              "requi_cantidad" => $vDef->cantidad,
              "requi_unidad_medida_token" => $vDef->token_unidad_medida,
              "requi_unidad_medida_name" => $det_requi_unidad_medida,
              "requi_marca" => $det_requi_marca,
            );
            $desglose_requi_false[] = $rowDet;
          }

          $row = array(
            "requisicion_token" => $token_requisicion,
            "requisicion_folio" => $requisicion_folio,
            "requisicion_fecha_registro" => $requisicion_fecha_registro,
            "requisicion_proyecto" => $requisicion_proyecto,
            "requisicion_prioridad" => $requisicion_prioridad,
            "requisicion_usuario_requisita" => $usuario_requisita,
            "requisicion_autorizacion" => $requisicion_autorizacion,
            "requisicion_persona_autoriza" => $persona_autoriza,
            "desglose_true" => $desglose_requi_true,
            "desglose_false" => $desglose_requi_false,
          );
          $dataRequisicion[] = $row;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "requisiciones" => $dataRequisicion,
        );
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminarRequisicionDetalle(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "token_detalle_requisicion" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $token_detalle_requisicion_ = $parametrosArray["token_detalle_requisicion"];

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);

        $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
            "eegr_compras_requisicion.status" => TRUE,
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
          ])->get();

        foreach ($queryRequisicionMain as $vReq) {
          $authRequisiDet = DB::table("eegr_compras_requisicion_detalle AS reqDet")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->where(["reqDet.des_autorizacion" => FALSE, "reqDet.token_detalle_requisicion" => $token_detalle_requisicion_, "reqMain.token_requisicion" => $vReq->token_requisicion])
            ->limit(1)->delete();

          if ($authRequisiDet) {
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Requisición eliminada");
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Requisición no eliminada");
          }
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function autorizaRequisicion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "token_detalle_requisicion" => "required|string",
        "cantidad_autorizada" => "required|string",
        "comentarios" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $token_detalle_requisicion_ = $parametrosArray["token_detalle_requisicion"];
        $cantidad_autorizada_ = $parametrosArray["cantidad_autorizada"];
        $comentarios_ = $parametrosArray["comentarios"];
        //autorizacion_comentarios

        if (
          isset($requisicion_token_) && !empty($requisicion_token_) && isset($token_detalle_requisicion_) && !empty($token_detalle_requisicion_) &&
          isset($cantidad_autorizada_) && !empty($cantidad_autorizada_) && preg_match($JwtAuth->filtroNumerico(), $cantidad_autorizada_) &&
          isset($comentarios_) && !empty($comentarios_) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_)
        ) {
          $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);

          $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
              "eegr_compras_requisicion.status" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          foreach ($queryRequisicionMain as $vReq) {
            $requisiDent = DB::select("SELECT id FROM eegr_compras_requisicion WHERE token_requisicion = ?", [$vReq->token_requisicion]);
            $authRequiMain = DB::table("eegr_compras_requisicion")
              ->where(["token_requisicion" => $vReq->token_requisicion])
              ->limit(1)->update(
                array(
                  "autorizacion" => TRUE,
                  "fecha_autorizacion" => time(),
                  "autoriza_user" => $selectEmp[0]->userr
                )
              );

            $folioSistema = DB::select("SELECT soli.id FROM eegr_compras_cotizacion_solicitud AS soli JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser  
                            JOIN teci_usuarios_catalogo AS users WHERE soli.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                            AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

            $folio_coti_soli = count($folioSistema) + 1;
            $token_solicitud_cotizacion = $JwtAuth->encriptarToken(time() . $folio_coti_soli . $vReq->token_requisicion . end($requisiDent)->id);
            $insert_data = DB::table("eegr_compras_cotizacion_solicitud")
              ->insert(
                array(
                  "token_solicitud_cotizacion" => $token_solicitud_cotizacion,
                  "fecha_registro" => time(),
                  "folio_registro" => $folio_coti_soli,
                  "requisicion" => end($requisiDent)->id,
                  "empresa" => end($selectEmp)->id,
                  "usuario_expide" => end($selectEmp)->userr,
                  "status_cotizacion_solicitud" => TRUE
                )
              );

            $queryRequisicionDetail = DB::table("eegr_compras_requisicion_detalle AS reqDet")
              ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
              ->where(["reqDet.token_detalle_requisicion" => $token_detalle_requisicion_, "reqMain.token_requisicion" => $vReq->token_requisicion])
              ->get();
            foreach ($queryRequisicionDetail as $vDet) {
              $selectSoliCoti = DB::select("SELECT id FROM eegr_compras_cotizacion_solicitud WHERE token_solicitud_cotizacion = ?", [$token_solicitud_cotizacion]);
              $detail_tkn = $vDet->token_detalle_requisicion;
              $authRequisiDet = DB::table("eegr_compras_requisicion_detalle AS reqDet")
                ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
                ->where(["reqDet.token_detalle_requisicion" => $detail_tkn, "reqMain.token_requisicion" => $vReq->token_requisicion])
                ->limit(1)->update(array(
                  "reqDet.des_autorizacion" => "A",
                  "reqDet.des_autoriza_user" => $selectEmp[0]->userr,
                  "reqDet.autorizacion_comentarios" => $JwtAuth->encriptar($comentarios_),
                  "reqDet.des_fecha_autorizacion" => time()
                ));

              $authRequisiDet = DB::table("eegr_compras_requisicion_detalle AS reqDet")
                ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
                ->where(["reqDet.token_detalle_requisicion" => $detail_tkn, "reqMain.token_requisicion" => $vReq->token_requisicion])
                ->limit(1)->update(
                  array(
                    "reqDet.cantidad_autorizada" => $cantidad_autorizada_,
                    "reqDet.des_autorizacion" => TRUE,
                    "reqDet.des_autoriza_user" => $selectEmp[0]->userr,
                    "reqDet.des_fecha_autorizacion" => time()
                  )
                );

              if ($authRequisiDet) {
                $requisDetailIDent = DB::select("SELECT id FROM eegr_compras_requisicion_detalle WHERE token_detalle_requisicion = ?", [$detail_tkn]);

                $token_relacion = $JwtAuth->encriptarToken(time() . $token_solicitud_cotizacion . $detail_tkn);
                $insert_Rel = DB::table("eegr_compras_cotizacion_solicitud_requi")
                  ->insert(
                    array(
                      "token_relacion" => $token_relacion,
                      "cotizacion_solicitud" => end($selectSoliCoti)->id,
                      "requisicion" => end($requisiDent)->id,
                      "requisicion_detalle" => end($requisDetailIDent)->id
                    )
                  );

                $query_folio_auth = DB::select("SELECT r_auth.id FROM eegr_compras_requisicion_auth AS r_auth 
                                    JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet
                                    WHERE r_auth.requisicion = reqMain.id AND reqMain.token_requisicion = ? AND r_auth.partida = reqDet.id 
                                    AND reqDet.token_detalle_requisicion = ?", [$vReq->token_requisicion, $token_detalle_requisicion_]);

                if (count($query_folio_auth) == 0) {
                  $folio_auth = 1;
                } else {
                  $select_folio_auth = DB::select("SELECT folio_auth_requi FROM eegr_compras_requisicion_auth 
                                        WHERE id = (SELECT MAX(r_auth.id) FROM eegr_compras_requisicion_auth AS r_auth 
                                        JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet
                                        WHERE r_auth.requisicion = reqMain.id AND reqMain.token_requisicion = ? AND r_auth.partida = reqDet.id 
                                        AND reqDet.token_detalle_requisicion = ?)", [$vReq->token_requisicion, $token_detalle_requisicion_]);
                  $folio_auth = $select_folio_auth[0]->folio_auth_requi + 1;
                }

                $insertAuthList = DB::table('eegr_compras_requisicion_auth')
                  ->insert(
                    array(
                      "folio_auth_requi" => $folio_auth,
                      "fecha_registro" => time(),
                      "requisicion" => $requisiDent[0]->id,
                      "partida" => $requisDetailIDent[0]->id,
                      "autorizacion_req" => "A",
                      "comentarios" => $JwtAuth->encriptar($comentarios_),
                      "autoriza" => $selectEmp[0]->userr,
                    )
                  );

                if ($insert_Rel && $insertAuthList) {
                  $dataMensaje = array("status" => "success", "code" => 200, "message" => "Requisición autorizada, se generó una solicitud de cotización con el folio " . $JwtAuth->generarFolio($folio_coti_soli));
                } else {
                  $dataMensaje = array("status" => "error", "code" => 200, "message" => "Requisición no autorizada");
                }
              } else {
                $dataMensaje = array("status" => "error", "code" => 200, "message" => "Requisición no autorizada");
              }
            }
          }

          /*CREATE TABLE eegr_compras_requisicion_auth (
                      id int(10) primary key not null auto_increment,
                      folio_auth_requi int(10),
                      fecha_registro varchar(10),
                      requisicion int(10),
                      partida int(10),
                      autorizacion_req char(1),
                      comentarios text,
                      autoriza int(10),
                      foreign key (requisicion) references eegr_compras_requisicion (id),
                      foreign key (partida) references eegr_compras_requisicion_detalle (id),
                      foreign key (autoriza) references vhum_empleados_catalogo (id)
                    );*/
        } else {
          $mensaje_error = "";
          if (!isset($requisicion_token_) || empty($requisicion_token_)) {
            $mensaje_error = "Error al obtener datos de la requisición seleccionada";
          }
          if (!isset($listado) || empty($listado)) {
            $mensaje_error = "Error al obtener datos de la partida seleccionada";
          }
          if (!isset($cantidad_autorizada_) || empty($cantidad_autorizada_) || !preg_match($JwtAuth->filtroNumerico(), $cantidad_autorizada_)) {
            $mensaje_error = "Error en cantidad autorizada para la partida seleccionada";
          }
          if (!isset($comentarios_) || empty($comentarios_) || !preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_)) {
            $mensaje_error = "Error en comentarios sobre la autorización de la partida seleccionada";
          }

          $dataMensaje = array(
            "status" => "error",
            'code' => 404,
            "message" => $mensaje_error
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function autorizaRequisicionAll(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "coments_rechazo" => "string",
        "desglose" => "required|array",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $requisicion_coments_rechazo_ = $parametrosArray["coments_rechazo"];
        $requisicion_desglose_ = $parametrosArray["desglose"];
        $requisicion_desglose_total = count($parametrosArray["desglose"]);
        $requisicion_desglose_true = 0;
        $requisicion_desglose_count = 0;
        $requisicion_desglose_acept = 0;
        $requisicion_desglose_rechas = 0;
        //autorizacion_comentarios

        if (isset($requisicion_token_) && !empty($requisicion_token_) && isset($requisicion_desglose_) && !empty($requisicion_desglose_) && $requisicion_desglose_total > 0) {
          $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);

          for ($d = 0; $d < $requisicion_desglose_total; $d++) {
            $bool_requi_autorizacion_ = $requisicion_desglose_[$d]["bool_requi_autorizacion"];
            $char_requi_autorizacion_ = $requisicion_desglose_[$d]["char_requi_autorizacion"];
            if ($bool_requi_autorizacion_ == false && $char_requi_autorizacion_ != "N") {
              ++$requisicion_desglose_true;
            }
          }

          $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
              "eegr_compras_requisicion.status" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          foreach ($queryRequisicionMain as $vReq) {
            $authComents = isset($requisicion_coments_rechazo_) && !empty($requisicion_coments_rechazo_) && preg_match($JwtAuth->filtroAlfaNumerico(), $requisicion_coments_rechazo_) ? $JwtAuth->encriptar($requisicion_coments_rechazo_) : NULL;
            $requisicion_coments_rechazo_ = $parametrosArray["coments_rechazo"];
            $requisiDent = DB::select("SELECT id FROM eegr_compras_requisicion WHERE token_requisicion = ?", [$vReq->token_requisicion]);
            $authRequiMain = DB::table("eegr_compras_requisicion")
              ->where(["token_requisicion" => $vReq->token_requisicion])
              ->limit(1)->update(
                array(
                  "autorizacion" => TRUE,
                  "fecha_autorizacion" => time(),
                  "autoriza_user" => $selectEmp[0]->userr,
                  "autoriza_coments" => $authComents,
                )
              );

            $folioSistema = DB::select("SELECT soli.id FROM eegr_compras_cotizacion_solicitud AS soli JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                            WHERE soli.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

            $folio_coti_soli = count($folioSistema) + 1;
            $token_solicitud_cotizacion = $JwtAuth->encriptarToken(time() . $folio_coti_soli . $vReq->token_requisicion . end($requisiDent)->id);
            $insert_data = DB::table("eegr_compras_cotizacion_solicitud")
              ->insert(
                array(
                  "token_solicitud_cotizacion" => $token_solicitud_cotizacion,
                  "fecha_registro" => time(),
                  "folio_registro" => $folio_coti_soli,
                  "requisicion" => end($requisiDent)->id,
                  "empresa" => end($selectEmp)->id,
                  "usuario_expide" => end($selectEmp)->userr,
                  "status_cotizacion_solicitud" => TRUE
                )
              );

            for ($d = 0; $d < $requisicion_desglose_total; $d++) {
              $selectSoliCoti = DB::select("SELECT id FROM eegr_compras_cotizacion_solicitud WHERE token_solicitud_cotizacion = ?", [$token_solicitud_cotizacion]);
              $token_detalle_requisicion_ = $requisicion_desglose_[$d]["token_detalle_requisicion"];
              $requi_cantidad_autorizada_ = $requisicion_desglose_[$d]["requi_cantidad_autorizada"];
              $requi_autorizacion_coments_write_ = $requisicion_desglose_[$d]["requi_autorizacion_coments_write"] != "" ? $JwtAuth->encriptar($requisicion_desglose_[$d]["requi_autorizacion_coments_write"]) : NULL;
              $bool_requi_autorizacion_ = $requisicion_desglose_[$d]["bool_requi_autorizacion"];
              $char_requi_autorizacion_ = $requisicion_desglose_[$d]["char_requi_autorizacion"];

              $queryRequisicionDetail = DB::table("eegr_compras_requisicion_detalle AS reqDet")
                ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
                ->where(["reqDet.token_detalle_requisicion" => $token_detalle_requisicion_, "reqMain.token_requisicion" => $vReq->token_requisicion])
                ->get();
              foreach ($queryRequisicionDetail as $vDet) {
                if ($bool_requi_autorizacion_ == false) {
                  if ($char_requi_autorizacion_ == "A") {
                    $detail_tkn = $vDet->token_detalle_requisicion;
                    $authRequisiDet = DB::table("eegr_compras_requisicion_detalle AS reqDet")
                      ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
                      ->where(["reqDet.token_detalle_requisicion" => $detail_tkn, "reqMain.token_requisicion" => $vReq->token_requisicion])
                      ->limit(1)->update(array(
                        "reqDet.cantidad_autorizada" => $requi_cantidad_autorizada_,
                        "reqDet.des_autorizacion" => "A",
                        "reqDet.des_autoriza_user" => $selectEmp[0]->userr,
                        "reqDet.autorizacion_comentarios" => $requi_autorizacion_coments_write_,
                        "reqDet.des_fecha_autorizacion" => time()
                      ));

                    if ($authRequisiDet) {
                      $requisDetailIDent = DB::select("SELECT id FROM eegr_compras_requisicion_detalle WHERE token_detalle_requisicion = ?", [$detail_tkn]);

                      $token_relacion = $JwtAuth->encriptarToken(time() . end($selectSoliCoti)->id . $detail_tkn);
                      $insert_Rel = DB::table("eegr_compras_cotizacion_solicitud_requi")
                        ->insert(
                          array(
                            "token_relacion" => $token_relacion,
                            "cotizacion_solicitud" => end($selectSoliCoti)->id,
                            "requisicion" => end($requisiDent)->id,
                            "requisicion_detalle" => end($requisDetailIDent)->id
                          )
                        );

                      $query_folio_auth = DB::select("SELECT r_auth.id FROM eegr_compras_requisicion_auth AS r_auth 
                                                JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet
                                                WHERE r_auth.requisicion = reqMain.id AND reqMain.token_requisicion = ? AND r_auth.partida = reqDet.id 
                                                AND reqDet.token_detalle_requisicion = ?", [$vReq->token_requisicion, $token_detalle_requisicion_]);

                      if (count($query_folio_auth) == 0) {
                        $folio_auth = 1;
                      } else {
                        $select_folio_auth = DB::select("SELECT folio_auth_requi FROM eegr_compras_requisicion_auth 
                                                    WHERE id = (SELECT MAX(r_auth.id) FROM eegr_compras_requisicion_auth AS r_auth 
                                                    JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet
                                                    WHERE r_auth.requisicion = reqMain.id AND reqMain.token_requisicion = ? AND r_auth.partida = reqDet.id 
                                                    AND reqDet.token_detalle_requisicion = ?)", [$vReq->token_requisicion, $token_detalle_requisicion_]);
                        $folio_auth = $select_folio_auth[0]->folio_auth_requi + 1;
                      }

                      $insertAuthList = DB::table('eegr_compras_requisicion_auth')
                        ->insert(
                          array(
                            "folio_auth_requi" => $folio_auth,
                            "fecha_registro" => time(),
                            "requisicion" => $requisiDent[0]->id,
                            "partida" => $requisDetailIDent[0]->id,
                            "autorizacion_req" => "A",
                            "comentarios" => $requi_autorizacion_coments_write_,
                            "autoriza" => $selectEmp[0]->userr,
                          )
                        );

                      if ($insert_Rel && $insertAuthList) {
                        ++$requisicion_desglose_count;
                        ++$requisicion_desglose_acept;
                      } else {
                        $dataMensaje = array("status" => "error", "code" => 200, "message" => "Requisición no autorizada");
                      }
                    } else {
                      $dataMensaje = array("status" => "error", "code" => 200, "message" => "Requisición no autorizada");
                    }
                  } else if ($char_requi_autorizacion_ == "D") {
                    $authRequisiDet = DB::table("eegr_compras_requisicion_detalle AS reqDet")
                      ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
                      ->where(["reqDet.token_detalle_requisicion" => $token_detalle_requisicion_, "reqMain.token_requisicion" => $vReq->token_requisicion])
                      ->limit(1)->update(array(
                        "reqDet.des_autorizacion" => "D",
                        "reqDet.des_autoriza_user" => $selectEmp[0]->userr,
                        "reqDet.autorizacion_comentarios" => $requi_autorizacion_coments_write_,
                        "reqDet.des_fecha_autorizacion" => time()
                      ));

                    if ($authRequisiDet) {
                      ++$requisicion_desglose_count;
                      ++$requisicion_desglose_rechas;
                      $requisiDent = DB::select("SELECT id FROM eegr_compras_requisicion WHERE token_requisicion = ?", [$vReq->token_requisicion]);
                      $requisDetailIDent = DB::select("SELECT id FROM eegr_compras_requisicion_detalle WHERE token_detalle_requisicion = ?", [$token_detalle_requisicion_]);

                      $query_folio_auth = DB::select("SELECT r_auth.id FROM eegr_compras_requisicion_auth AS r_auth 
                                                JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet
                                                WHERE r_auth.requisicion = reqMain.id AND reqMain.token_requisicion = ? AND r_auth.partida = reqDet.id 
                                                AND reqDet.token_detalle_requisicion = ?", [$vReq->token_requisicion, $token_detalle_requisicion_]);

                      if (count($query_folio_auth) == 0) {
                        $folio_auth = 1;
                      } else {
                        $select_folio_auth = DB::select("SELECT folio_auth_requi FROM eegr_compras_requisicion_auth 
                                                    WHERE id = (SELECT MAX(r_auth.id) FROM eegr_compras_requisicion_auth AS r_auth 
                                                    JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet
                                                    WHERE r_auth.requisicion = reqMain.id AND reqMain.token_requisicion = ? AND r_auth.partida = reqDet.id 
                                                    AND reqDet.token_detalle_requisicion = ?)", [$vReq->token_requisicion, $token_detalle_requisicion_]);
                        $folio_auth = $select_folio_auth[0]->folio_auth_requi + 1;
                      }

                      $insertEquipo = DB::table('eegr_compras_requisicion_auth')
                        ->insert(
                          array(
                            "folio_auth_requi" => $folio_auth,
                            "fecha_registro" => time(),
                            "requisicion" => $requisiDent[0]->id,
                            "partida" => $requisDetailIDent[0]->id,
                            "autorizacion_req" => "D",
                            "comentarios" => $requi_autorizacion_coments_write_,
                            "autoriza" => $selectEmp[0]->userr,
                          )
                        );
                    } else {
                      $dataMensaje = array("status" => "error", "code" => 200, "message" => "Requisición no desautorizada");
                    }
                  }
                } else {
                  ++$requisicion_desglose_count;
                  //++$requisicion_desglose_acept;
                }
              }
            }
            //echo $requisicion_desglose_count." ".$requisicion_desglose_true;
            if ($requisicion_desglose_count == $requisicion_desglose_total) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "Requisición autorizada, se generó una solicitud de cotización con el folio " . $JwtAuth->generarFolio($folio_coti_soli) . ", se autorizaron $requisicion_desglose_acept partidas y se rechazaron $requisicion_desglose_rechas");
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "Requisición no autorizada");
            }
          }

          /*CREATE TABLE eegr_compras_requisicion_auth (
                      id int(10) primary key not null auto_increment,
                      folio_auth_requi int(10),
                      fecha_registro varchar(10),
                      requisicion int(10),
                      partida int(10),
                      autorizacion_req char(1),
                      comentarios text,
                      autoriza int(10),
                      foreign key (requisicion) references eegr_compras_requisicion (id),
                      foreign key (partida) references eegr_compras_requisicion_detalle (id),
                      foreign key (autoriza) references vhum_empleados_catalogo (id)
                    );*/
        } else {
          $mensaje_error = "";
          if (!isset($requisicion_token_) || empty($requisicion_token_)) {
            $mensaje_error = "Error al obtener datos de la requisición seleccionada";
          }
          if (!isset($listado) || empty($listado)) {
            $mensaje_error = "Error al obtener datos de la partida seleccionada";
          }
          if (!isset($cantidad_autorizada_) || empty($cantidad_autorizada_) || !preg_match($JwtAuth->filtroNumerico(), $cantidad_autorizada_)) {
            $mensaje_error = "Error en cantidad autorizada para la partida seleccionada";
          }
          if (!isset($comentarios_) || empty($comentarios_) || !preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_)) {
            $mensaje_error = "Error en comentarios sobre la autorización de la partida seleccionada";
          }

          $dataMensaje = array(
            "status" => "error",
            'code' => 404,
            "message" => $mensaje_error
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function desautorizaRequisicion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "token_detalle_requisicion" => "required|string",
        "comentarios" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $token_detalle_requisicion_ = $parametrosArray["token_detalle_requisicion"];
        $comentarios_ = $parametrosArray["comentarios"];
        //autorizacion_comentarios

        if (
          isset($requisicion_token_) && !empty($requisicion_token_) && isset($token_detalle_requisicion_) && !empty($token_detalle_requisicion_) &&
          isset($comentarios_) && !empty($comentarios_) && preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_)
        ) {
          $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);

          $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
              "eegr_compras_requisicion.status" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          foreach ($queryRequisicionMain as $vReq) {
            $authRequisiDet = DB::table("eegr_compras_requisicion_detalle AS reqDet")
              ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
              ->where(["reqDet.token_detalle_requisicion" => $token_detalle_requisicion_, "reqMain.token_requisicion" => $vReq->token_requisicion])
              ->limit(1)->update(array(
                "reqDet.des_autorizacion" => "D",
                "reqDet.des_autoriza_user" => $selectEmp[0]->userr,
                "reqDet.autorizacion_comentarios" => $JwtAuth->encriptar($comentarios_),
                "reqDet.des_fecha_autorizacion" => time()
              ));

            if ($authRequisiDet) {
              $requisiDent = DB::select("SELECT id FROM eegr_compras_requisicion WHERE token_requisicion = ?", [$vReq->token_requisicion]);
              $requisDetailIDent = DB::select("SELECT id FROM eegr_compras_requisicion_detalle WHERE token_detalle_requisicion = ?", [$token_detalle_requisicion_]);

              $query_folio_auth = DB::select("SELECT r_auth.id FROM eegr_compras_requisicion_auth AS r_auth 
                                JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet
                                WHERE r_auth.requisicion = reqMain.id AND reqMain.token_requisicion = ? AND r_auth.partida = reqDet.id 
                                AND reqDet.token_detalle_requisicion = ?", [$vReq->token_requisicion, $token_detalle_requisicion_]);

              if (count($query_folio_auth) == 0) {
                $folio_auth = 1;
              } else {
                $select_folio_auth = DB::select("SELECT folio_auth_requi FROM eegr_compras_requisicion_auth 
                                    WHERE id = (SELECT MAX(r_auth.id) FROM eegr_compras_requisicion_auth AS r_auth 
                                    JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet
                                    WHERE r_auth.requisicion = reqMain.id AND reqMain.token_requisicion = ? AND r_auth.partida = reqDet.id 
                                    AND reqDet.token_detalle_requisicion = ?)", [$vReq->token_requisicion, $token_detalle_requisicion_]);
                $folio_auth = $select_folio_auth[0]->folio_auth_requi + 1;
              }

              $insertEquipo = DB::table('eegr_compras_requisicion_auth')
                ->insert(
                  array(
                    "folio_auth_requi" => $folio_auth,
                    "fecha_registro" => time(),
                    "requisicion" => $requisiDent[0]->id,
                    "partida" => $requisDetailIDent[0]->id,
                    "autorizacion_req" => "D",
                    "comentarios" => $JwtAuth->encriptar($comentarios_),
                    "autoriza" => $selectEmp[0]->userr,
                  )
                );

              $selectDetReqTrue = DB::table("eegr_compras_requisicion_detalle AS reqDet")
                ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
                ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
                ->where(["reqMain.token_requisicion" => $vReq->token_requisicion, "reqDet.status_req" => TRUE, "reqDet.des_autorizacion" => TRUE])->get();

              if (count($selectDetReqTrue) == 0) {
                $authRequiMain = DB::table("eegr_compras_requisicion")
                  ->where(["token_requisicion" => $vReq->token_requisicion])
                  ->limit(1)->update(array("autorizacion" => FALSE, "fecha_autorizacion" => time(), "autoriza_user" => $selectEmp[0]->userr));
                if ($authRequiMain) {
                  $dataMensaje = array("status" => "success", "code" => 200, "message" => "Requisición desautorizada");
                } else {
                  $dataMensaje = array("status" => "error", "code" => 200, "message" => "Requisición no desautorizada");
                }
              } else {
                $dataMensaje = array("status" => "success", "code" => 200, "message" => "Requisición desautorizada");
              }
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "Requisición no desautorizada");
            }
          }

          /*CREATE TABLE eegr_compras_requisicion_auth (
                      id int(10) primary key not null auto_increment,
                      folio_auth_requi int(10),
                      fecha_registro varchar(10),
                      requisicion int(10),
                      partida int(10),
                      autorizacion_req char(1),
                      comentarios text,
                      autoriza int(10),
                      foreign key (requisicion) references eegr_compras_requisicion (id),
                      foreign key (partida) references eegr_compras_requisicion_detalle (id),
                      foreign key (autoriza) references vhum_empleados_catalogo (id)
                    );*/
        } else {
          $mensaje_error = "";
          if (!isset($requisicion_token_) || empty($requisicion_token_)) {
            $mensaje_error = "Error al obtener datos de la requisición seleccionada";
          }
          if (!isset($listado) || empty($listado)) {
            $mensaje_error = "Error al obtener datos de la partida seleccionada";
          }
          if (!isset($comentarios_) || empty($comentarios_) || !preg_match($JwtAuth->filtroAlfaNumerico(), $comentarios_)) {
            $mensaje_error = "Error en comentarios sobre la autorización de la partida seleccionada";
          }

          $dataMensaje = array(
            "status" => "error",
            'code' => 404,
            "message" => $mensaje_error
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateRequisicionProyecto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataRequisicion = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "requi_proyecto" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $requisicion_proyecto_ = $parametrosArray["requi_proyecto"];

        $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

        if (isset($requisicion_token_) && !empty($requisicion_token_) && isset($requisicion_proyecto_) && !empty($requisicion_proyecto_) && preg_match($JwtAuth->filtroAlfaNumerico(), $requisicion_proyecto_)) {
          $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
              "eegr_compras_requisicion.status" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          foreach ($queryRequisicionMain as $vReq) {
            $token_requisicion = $vReq->token_requisicion;
            $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vReq->folio);

            $authRequisi = DB::table("eegr_compras_requisicion")->where(["token_requisicion" => $token_requisicion])
              ->limit(1)->update(array("proyecto" => $JwtAuth->encriptar($requisicion_proyecto_)));

            if ($authRequisi) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Actualizacion correcta de requisición con folio " . $requisicion_folio . " se realizó correctamente",
                "pdflink" => "https://downloads.sos-mexico.com.mx/requisicion_pdf/" . $token_requisicion
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => 'Esta requisición no fue actualizada debido a errores internos, intente nuevamente'
              );
            }
          }
        } else {
          if (!isset($requisicion_token_) || empty($requisicion_token_)) {
            $mensaje_error = "No existe referencia de requisición para actualizar";
          }
          if (!isset($requisicion_proyecto_) || empty($requisicion_proyecto_) || !preg_match($JwtAuth->filtroAlfaNumerico(), $requisicion_proyecto_)) {
            $mensaje_error = "Error en nombre de proyecto para requisición";
          }
          $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateRequisicionPrioridad(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataRequisicion = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "requi_prioridad" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $requisicion_prioridad_ = $parametrosArray["requi_prioridad"];

        $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

        if (isset($requisicion_token_) && !empty($requisicion_token_) && isset($requisicion_prioridad_) && !empty($requisicion_prioridad_) && preg_match($JwtAuth->filtroAlfaNumerico(), $requisicion_prioridad_)) {
          $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
              "eegr_compras_requisicion.status" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          foreach ($queryRequisicionMain as $vReq) {
            $token_requisicion = $vReq->token_requisicion;
            $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vReq->folio);

            $authRequisi = DB::table("eegr_compras_requisicion")->where(["token_requisicion" => $token_requisicion])
              ->limit(1)->update(array("prioridad" => $requisicion_prioridad_));

            if ($authRequisi) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Actualizacion correcta de requisición con folio " . $requisicion_folio . " se realizó correctamente",
                "pdflink" => "https://downloads.sos-mexico.com.mx/requisicion_pdf/" . $token_requisicion
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => 'Esta requisición no fue actualizada debido a errores internos, intente nuevamente'
              );
            }
          }
        } else {
          if (!isset($requisicion_token_) || empty($requisicion_token_)) {
            $mensaje_error = "No existe referencia de requisición para actualizar";
          }
          if (!isset($requisicion_prioridad_) || empty($requisicion_prioridad_) || !preg_match($JwtAuth->filtroAlfaNumerico(), $requisicion_prioridad_)) {
            $mensaje_error = "Error en prioridad de proyecto para requisición";
          }
          $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateRequisicionListTipo(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataRequisicion = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "token_detalle_requisicion" => "required|string",
        "requi_tipo" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $detalle_requisicion_token_ = $parametrosArray["token_detalle_requisicion"];
        $detalle_requisicion_tipo_ = $parametrosArray["requi_tipo"];

        $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

        if (
          isset($requisicion_token_) && !empty($requisicion_token_) && isset($detalle_requisicion_token_) && !empty($detalle_requisicion_token_) &&
          isset($detalle_requisicion_tipo_) && !empty($detalle_requisicion_tipo_) && preg_match($JwtAuth->filtroAlfaNumerico(), $detalle_requisicion_tipo_)
        ) {
          if ($detalle_requisicion_tipo_ == "Mercancia") $inside_tipo_req = "Merc";
          if ($detalle_requisicion_tipo_ == "Gastos") $inside_tipo_req = "Gast";
          if ($detalle_requisicion_tipo_ == "Activos") $inside_tipo_req = "Acti";
          if ($detalle_requisicion_tipo_ == "Mixto") $inside_tipo_req = "Mixt";

          $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
              "eegr_compras_requisicion.status" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          foreach ($queryRequisicionMain as $vReq) {
            $token_requisicion = $vReq->token_requisicion;

            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);
            date_default_timezone_set($selectEmp[0]->zona_horaria);

            $authDetReqTrue = DB::table("eegr_compras_requisicion_detalle AS reqDet")
              ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
              ->where(["reqDet.token_detalle_requisicion" => $detalle_requisicion_token_, "reqMain.token_requisicion" => $token_requisicion])
              ->limit(1)->update(array("reqDet.tipo" => $inside_tipo_req));

            if ($authDetReqTrue) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "requisición autorizada satisfactoriamente",
                "pdflink" => "https://downloads.sos-mexico.com.mx/requisicion_pdf/" . $requisicion_token_
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "requisición no autorizada",
              );
            }
          }
        } else {
          if (!isset($requisicion_token_) || empty($requisicion_token_)) {
            $mensaje_error = "No existe referencia de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_token_) || empty($detalle_requisicion_token_)) {
            $mensaje_error = "No existe referencia de partida de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_tipo_) || empty($detalle_requisicion_tipo_) || !preg_match($JwtAuth->filtroAlfaNumerico(), $detalle_requisicion_tipo_)) {
            $mensaje_error = "Error en tipo de partida de requisición para actualizar";
          }
          $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateRequisicionListConcepto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataRequisicion = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "token_detalle_requisicion" => "required|string",
        "requi_concepto" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $detalle_requisicion_token_ = $parametrosArray["token_detalle_requisicion"];
        $detalle_requisicion_concepto_ = $parametrosArray["requi_concepto"];

        $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

        if (
          isset($requisicion_token_) && !empty($requisicion_token_) && isset($detalle_requisicion_token_) && !empty($detalle_requisicion_token_) &&
          isset($detalle_requisicion_concepto_) && !empty($detalle_requisicion_concepto_) && preg_match($JwtAuth->filtroAlfaNumerico(), $detalle_requisicion_concepto_)
        ) {

          $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
              "eegr_compras_requisicion.status" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          foreach ($queryRequisicionMain as $vReq) {
            $token_requisicion = $vReq->token_requisicion;

            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);
            date_default_timezone_set($selectEmp[0]->zona_horaria);

            $authDetReqTrue = DB::table("eegr_compras_requisicion_detalle AS reqDet")
              ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
              ->where(["reqDet.token_detalle_requisicion" => $detalle_requisicion_token_, "reqMain.token_requisicion" => $token_requisicion])
              ->limit(1)->update(array("reqDet.necesidad" => $JwtAuth->encriptar($detalle_requisicion_concepto_)));

            if ($authDetReqTrue) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "requisición autorizada satisfactoriamente",
                "pdflink" => "https://downloads.sos-mexico.com.mx/requisicion_pdf/" . $requisicion_token_
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "requisición no autorizada",
              );
            }
          }
        } else {
          if (!isset($requisicion_token_) || empty($requisicion_token_)) {
            $mensaje_error = "No existe referencia de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_token_) || empty($detalle_requisicion_token_)) {
            $mensaje_error = "No existe referencia de partida de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_concepto_) || empty($detalle_requisicion_concepto_) || !preg_match($JwtAuth->filtroAlfaNumerico(), $detalle_requisicion_concepto_)) {
            $mensaje_error = "Error en concepto de partida de requisición para actualizar";
          }
          $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateRequisicionAddCaractList(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataRequisicion = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "token_detalle_requisicion" => "required|string",
        "clave" => "required|string",
        "valor" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $detalle_requisicion_token_ = $parametrosArray["token_detalle_requisicion"];
        $detrequi_caract_clave = $parametrosArray["clave"];
        $detrequi_caract_valor = $parametrosArray["valor"];

        if (
          isset($requisicion_token_) && !empty($requisicion_token_) && isset($detalle_requisicion_token_) && !empty($detalle_requisicion_token_) &&
          isset($detrequi_caract_clave) && !empty($detrequi_caract_clave) && preg_match($JwtAuth->filtroAlfaNumerico(), $detrequi_caract_clave) &&
          isset($detrequi_caract_valor) && !empty($detrequi_caract_valor) && preg_match($JwtAuth->filtroAlfaNumerico(), $detrequi_caract_valor)
        ) {

          $queryRequisicionMain = DB::select("SELECT mainreq.id AS idMain,mainreq.autorizacion AS authMain,detreq.id AS idDet,detreq.des_autorizacion AS authDet 
                        FROM eegr_compras_requisicion_detalle AS detreq JOIN eegr_compras_requisicion AS mainreq WHERE detreq.token_detalle_requisicion = ?
                        AND detreq.requisicion = mainreq.id AND mainreq.token_requisicion = ?", [$detalle_requisicion_token_, $requisicion_token_]);

          foreach ($queryRequisicionMain as $vReq) {
            $token_caract = $JwtAuth->encriptarToken($vReq->idMain . $vReq->idDet . $detrequi_caract_clave . $detrequi_caract_valor);

            $insertDetReqCaract = DB::table("eegr_compras_requisicion_detalle_caract")
              ->insert(
                array(
                  "token_caract" => $token_caract,
                  "requisicion_main" => $vReq->idMain,
                  "requisicion_detalle" => $vReq->idDet,
                  "clave" => $JwtAuth->encriptar($detrequi_caract_clave),
                  "valor" => $JwtAuth->encriptar($detrequi_caract_valor)
                )
              );

            if ($insertDetReqCaract) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "caracterisiticas registradas satisfactoriamente",
                "token_registrado" => $token_caract,
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "caracterisiticas no registradas",
              );
            }
          }
        } else {
          if (!isset($requisicion_token_) || empty($requisicion_token_)) {
            $mensaje_error = "No existe referencia de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_token_) || empty($detalle_requisicion_token_)) {
            $mensaje_error = "No existe referencia de partida de requisición para actualizar";
          }
          if (!isset($detrequi_caract_clave) || empty($detrequi_caract_clave) || !preg_match($JwtAuth->filtroAlfaNumerico(), $detrequi_caract_clave)) {
            $mensaje_error = "Error en clave de característica de partida de requisición para actualizar";
          }
          if (!isset($detrequi_caract_valor) || empty($detrequi_caract_valor) || !preg_match($JwtAuth->filtroAlfaNumerico(), $detrequi_caract_valor)) {
            $mensaje_error = "Error en valor de característica de partida de requisición para actualizar";
          }
          $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateRequisicionDeleteCaractList(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataRequisicion = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "token_detalle_requisicion" => "required|string",
        "token_caract" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token = $parametrosArray["token_requisicion"];
        $detalle_requisicion_token = $parametrosArray["token_detalle_requisicion"];
        $detalle_requisicion_caract_token = $parametrosArray["token_caract"];

        if (
          isset($requisicion_token) && !empty($requisicion_token) && isset($detalle_requisicion_token) && !empty($detalle_requisicion_token) &&
          isset($detalle_requisicion_caract_token) && !empty($detalle_requisicion_caract_token)
        ) {
          $authRequisiDet = DB::table("eegr_compras_requisicion_detalle_caract AS reqDetCaract")
            ->join("eegr_compras_requisicion AS reqMain", "reqDetCaract.requisicion_main", "=", "reqMain.id")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "reqDetCaract.requisicion_detalle", "=", "reqDet.id")
            ->where([
              "reqDet.des_autorizacion" => FALSE,
              "reqDetCaract.token_caract" => $detalle_requisicion_caract_token,
              "reqDet.token_detalle_requisicion" => $detalle_requisicion_token,
              "reqMain.token_requisicion" => $requisicion_token
            ])
            ->limit(1)->delete();

          if ($authRequisiDet) {
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Caracteristica de detalle de requisición eliminada");
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Caracteristica de detalle de requisición no eliminada");
          }
        } else {
          if (!isset($requisicion_token) || empty($requisicion_token)) {
            $mensaje_error = "No existe referencia de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_token) || empty($detalle_requisicion_token)) {
            $mensaje_error = "No existe referencia de partida de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_caract_token) || empty($detalle_requisicion_caract_token)) {
            $mensaje_error = "Error en concepto de partida de requisición para actualizar";
          }
          $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateRequisicionListCantidad(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataRequisicion = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "token_detalle_requisicion" => "required|string",
        "requi_cantidad" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $detalle_requisicion_token_ = $parametrosArray["token_detalle_requisicion"];
        $detalle_requisicion_cantidad = $parametrosArray["requi_cantidad"];

        if (
          isset($requisicion_token_) && !empty($requisicion_token_) && isset($detalle_requisicion_token_) && !empty($detalle_requisicion_token_) &&
          isset($detalle_requisicion_cantidad) && !empty($detalle_requisicion_cantidad) && preg_match($JwtAuth->filtroNumerico(), $detalle_requisicion_cantidad)
        ) {

          $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
              "eegr_compras_requisicion.status" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          foreach ($queryRequisicionMain as $vReq) {
            $token_requisicion = $vReq->token_requisicion;

            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);
            date_default_timezone_set($selectEmp[0]->zona_horaria);

            $authDetReqTrue = DB::table("eegr_compras_requisicion_detalle AS reqDet")
              ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
              ->where(["reqDet.token_detalle_requisicion" => $detalle_requisicion_token_, "reqMain.token_requisicion" => $token_requisicion])
              ->limit(1)->update(array("reqDet.cantidad" => $detalle_requisicion_cantidad));

            if ($authDetReqTrue) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "requisición autorizada satisfactoriamente",
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "requisición no autorizada",
              );
            }
          }
        } else {
          if (!isset($requisicion_token_) || empty($requisicion_token_)) {
            $mensaje_error = "No existe referencia de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_token_) || empty($detalle_requisicion_token_)) {
            $mensaje_error = "No existe referencia de partida de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_cantidad) || empty($detalle_requisicion_cantidad) || !preg_match($JwtAuth->filtroNumerico(), $detalle_requisicion_cantidad)) {
            $mensaje_error = "Error en cantidad de partida de requisición para actualizar";
          }
          $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateRequisicionListUnidadMedida(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataRequisicion = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "token_detalle_requisicion" => "required|string",
        "requi_unidad_medida" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $detalle_requisicion_token_ = $parametrosArray["token_detalle_requisicion"];
        $detalle_requisicion_requi_unidad_medida_ = $parametrosArray["requi_unidad_medida"];

        $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

        if (
          isset($requisicion_token_) && !empty($requisicion_token_) && isset($detalle_requisicion_token_) && !empty($detalle_requisicion_token_) &&
          isset($detalle_requisicion_requi_unidad_medida_) && !empty($detalle_requisicion_requi_unidad_medida_)
        ) {

          $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
              "eegr_compras_requisicion.status" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          foreach ($queryRequisicionMain as $vReq) {
            $token_requisicion = $vReq->token_requisicion;

            $selectUnidadMedMain = DB::select("SELECT id FROM teci_unidad_medida WHERE token_unidad_medida = ?", [$detalle_requisicion_requi_unidad_medida_]);

            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);
            date_default_timezone_set($selectEmp[0]->zona_horaria);

            $authDetReqTrue = DB::table("eegr_compras_requisicion_detalle AS reqDet")
              ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
              ->where(["reqDet.token_detalle_requisicion" => $detalle_requisicion_token_, "reqMain.token_requisicion" => $token_requisicion])
              ->limit(1)->update(array("reqDet.medida_unidad" => $selectUnidadMedMain[0]->id));

            if ($authDetReqTrue) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "requisición autorizada satisfactoriamente",
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "requisición no autorizada",
              );
            }
          }
        } else {
          if (!isset($requisicion_token_) || empty($requisicion_token_)) {
            $mensaje_error = "No existe referencia de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_token_) || empty($detalle_requisicion_token_)) {
            $mensaje_error = "No existe referencia de partida de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_requi_unidad_medida_) || empty($detalle_requisicion_requi_unidad_medida_)) {
            $mensaje_error = "Error en unidad de medida de partida de requisición para actualizar";
          }
          $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateRequisicionListMarca(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $dataRequisicion = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "token_detalle_requisicion" => "required|string",
        "requi_marca" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $requisicion_token_ = $parametrosArray["token_requisicion"];
        $detalle_requisicion_token_ = $parametrosArray["token_detalle_requisicion"];
        $detalle_requisicion_marca_ = $parametrosArray["requi_marca"];

        $patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';

        if (
          isset($requisicion_token_) && !empty($requisicion_token_) && isset($detalle_requisicion_token_) && !empty($detalle_requisicion_token_) &&
          isset($detalle_requisicion_marca_) && !empty($detalle_requisicion_marca_) && preg_match($JwtAuth->filtroAlfaNumerico(), $detalle_requisicion_marca_)
        ) {

          $queryRequisicionMain = RequisicionesModelo::join("main_empresas AS emp", "eegr_compras_requisicion.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "eegr_compras_requisicion.token_requisicion" => $requisicion_token_,
              "eegr_compras_requisicion.status" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token,
            ])->get();

          foreach ($queryRequisicionMain as $vReq) {
            $token_requisicion = $vReq->token_requisicion;

            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id ", [$usuario->empresa_token, $usuario->user_token]);
            date_default_timezone_set($selectEmp[0]->zona_horaria);

            $authDetReqTrue = DB::table("eegr_compras_requisicion_detalle AS reqDet")
              ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
              ->where(["reqDet.token_detalle_requisicion" => $detalle_requisicion_token_, "reqMain.token_requisicion" => $token_requisicion])
              ->limit(1)->update(array("reqDet.marca" => $JwtAuth->encriptar($detalle_requisicion_marca_)));

            if ($authDetReqTrue) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "requisición autorizada satisfactoriamente",
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "requisición no autorizada",
              );
            }
          }
        } else {
          if (!isset($requisicion_token_) || empty($requisicion_token_)) {
            $mensaje_error = "No existe referencia de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_token_) || empty($detalle_requisicion_token_)) {
            $mensaje_error = "No existe referencia de partida de requisición para actualizar";
          }
          if (!isset($detalle_requisicion_marca_) || empty($detalle_requisicion_marca_) || !preg_match($JwtAuth->filtroAlfaNumerico(), $detalle_requisicion_marca_)) {
            $mensaje_error = "Error en marca de partida de requisición para actualizar";
          }
          $dataMensaje = array("status" => "error", "code" => 200, "message" => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        'code' => 404,
        "message" => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaCaracteristicas()
  {
    $array(
      array("type" => "number", "class" => "inputCaract0", "name" => "reqCaractPrecio[]", "id" => "reqCaractPrecio", "valor" => "Precio"),
      array("type" => "text", "class" => "inputCaract1", "name" => "reqCaractColor[]", "id" => "reqCaractColor", "valor" => "Color"),
      array("type" => "text", "class" => "inputCaract2", "name" => "reqCaractTamaño[]", "id" => "reqCaractTamaño", "valor" => "Tamaño"),
      array("type" => "text", "class" => "inputCaract3", "name" => "reqCaractTalla[]", "id" => "reqCaractTalla", "valor" => "Talla"),
      array("type" => "text", "class" => "inputCaract4", "name" => "reqCaractMaterial[]", "id" => "reqCaractMaterial", "valor" => "Material"),
      array("type" => "text", "class" => "inputCaract5", "name" => "reqCaractTipo[]", "id" => "reqCaractTipo", "valor" => "Tipo"),
      array("type" => "text", "class" => "inputCaract6", "name" => "reqCaractForma[]", "id" => "reqCaractForma", "valor" => "Forma"),
      array("type" => "number", "class" => "inputCaract7", "name" => "reqCaractPeso[]", "id" => "reqCaractPeso", "valor" => "Peso (Kg)"),
      array("type" => "number", "class" => "inputCaract8", "name" => "reqCaractAltura[]", "id" => "reqCaractAltura", "valor" => "Altura (Mts)"),
      array("type" => "text", "class" => "inputCaract9", "name" => "reqCaractTextura[]", "id" => "reqCaractTextura", "valor" => "Textura")
    );

    return response()->json([
      "requisiciones" => $arrayCaract,
      "code" => 200,
      "status" => "success"
    ]);
  }

  public function registraRequisicionLista(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        //'empresa_token' => 'required|string',
        "user_token" => "required|string",
        "proyecto" => "required|string",
        "prioridad" => "required|string",
        "justificacion" => "required|string",
        "lista_articulos" => "required|array",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => 'Usuario incorrecto' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        //echo "dat"; exit;
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $proyecto = $parametrosArray["proyecto"];
        $prioridad = $parametrosArray["prioridad"];
        $justificacion = $parametrosArray["justificacion"];
        $lista_articulos = $parametrosArray["lista_articulos"];

        if (
          isset($proyecto) && !empty($proyecto) && preg_match($JwtAuth->filtroAlfaNumerico(), $proyecto) &&
          isset($prioridad) && !empty($prioridad) && preg_match($JwtAuth->filtroAlfaNumerico(), $prioridad) &&
          isset($justificacion) && !empty($justificacion) && preg_match($JwtAuth->filtroAlfaNumerico(), $justificacion) &&
          isset($lista_articulos) && !empty($lista_articulos)
        ) {

          $fecha_sistema = time();

          $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id ", [$usuario->empresa_token, $usuario->user_token]);
          date_default_timezone_set($selectEmp[0]->zona_horaria);

          $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                        JOIN teci_usuarios_catalogo AS users WHERE fold.egr_requisiciones = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

          $folio_nuevo = count($folioSistema) == 1 ? $folioSistema[0]->folio : 1;
          $token_requisicion = $JwtAuth->encriptarToken($fecha_sistema . $proyecto . $prioridad . json_encode($lista_articulos));

          $insertRequisicion = DB::table("eegr_compras_requisicion")
            ->insert(
              array(
                "token_requisicion" => $token_requisicion,
                "folio" => $folio_nuevo,
                "fecha" => $fecha_sistema,
                "proyecto" => $JwtAuth->encriptar($proyecto),
                "prioridad" => $prioridad,
                "justificacion" => $JwtAuth->encriptar($justificacion),
                "empresa" => $selectEmp[0]->id,
                "usuario_requisita" => $selectEmp[0]->userr,
                "autorizacion" => FALSE,
                "autoriza_user" => NULL,
                "status" => TRUE
              )
            );

          if ($insertRequisicion) {
            $selectRequisicionMain = DB::select("SELECT id FROM eegr_compras_requisicion WHERE token_requisicion = ?", [$token_requisicion]);
            $countdetalleInsert = 0;
            for ($a = 0; $a < count($lista_articulos); $a++) {
              $requi_tipo_back = $lista_articulos[$a]["requi_tipo_back"];
              $requi_necesidad = $lista_articulos[$a]["requi_necesidad"];
              $requi_necesidad_caracteristicas = $lista_articulos[$a]["requi_necesidad_caracteristicas"];
              $requi_necesidad_otras_caracteristicas = $lista_articulos[$a]["requi_necesidad_otras_caracteristicas"];
              $txt_other_caract = $requi_necesidad_otras_caracteristicas != "" ? $JwtAuth->encriptar($requi_necesidad_otras_caracteristicas) : NULL;
              $requi_cantidad = $lista_articulos[$a]["requi_cantidad"];
              $requi_uni_med_back = $lista_articulos[$a]["requi_uni_med_front"];
              $requi_marca_back = $lista_articulos[$a]["requi_marca_back"];
              $txt_marca = $requi_marca_back != "" ? $JwtAuth->encriptar($requi_marca_back) : NULL;

              $token_det_requisicion = $JwtAuth->encriptarToken($token_requisicion . $requi_tipo_back . $requi_necesidad . $txt_other_caract . $requi_cantidad . $requi_uni_med_back . $requi_marca_back);
              $insertDetRequisicion = DB::table("eegr_compras_requisicion_detalle")
                ->insert(
                  array(
                    "token_detalle_requisicion"  => $token_det_requisicion,
                    "requisicion" => $selectRequisicionMain[0]->id,
                    "tipo_necesidad" => $requi_tipo_back,
                    "necesidad" => $JwtAuth->encriptar($requi_necesidad),
                    "caracteristicas_extend" => $txt_other_caract,
                    "cantidad" => $requi_cantidad,
                    "cantidad_autorizada" => $requi_cantidad,
                    "medida_unidad" => $requi_uni_med_back,
                    "marca" => $txt_marca,
                    "status_req" => TRUE
                  )
                );
              if ($insertDetRequisicion) {
                ++$countdetalleInsert;
                $seledtDetalleReq = DB::select("SELECT detreq.id FROM eegr_compras_requisicion_detalle AS detreq 
                                    JOIN eegr_compras_requisicion AS mainreq WHERE detreq.token_detalle_requisicion = ?
                                    AND detreq.requisicion = mainreq.id AND mainreq.token_requisicion = ?", [$token_det_requisicion, $token_requisicion]);

                if (count($requi_necesidad_caracteristicas) > 0) {
                  for ($i = 0; $i < count($requi_necesidad_caracteristicas); $i++) {
                    $requiNecCarClave = $requi_necesidad_caracteristicas[$i]["clave"];
                    $requiNecCarValor = $requi_necesidad_caracteristicas[$i]["valorBack"];
                    $token_caract = $JwtAuth->encriptarToken($token_det_requisicion, $requiNecCarClave, $requiNecCarValor);
                    $insertDetReqCaract = DB::table("eegr_compras_requisicion_detalle_caract")
                      ->insert(
                        array(
                          "token_caract" => $token_caract,
                          "requisicion_main" => $selectRequisicionMain[0]->id,
                          "requisicion_detalle" => $seledtDetalleReq[0]->id,
                          "clave" => $JwtAuth->encriptar($requiNecCarClave),
                          "valor" => $JwtAuth->encriptar($requiNecCarValor)
                        )
                      );
                  }
                }
              }
            }

            if ($countdetalleInsert == count($lista_articulos)) {
              if (count($folioSistema) == 0) {
                $insertSistema = DB::table('sos_last_folders')
                  ->insert(
                    array(
                      "egr_requisiciones" => TRUE,
                      "folder" => 1,
                      "empresa" => $selectEmp[0]->id,
                    )
                  );
              } else {
                $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                  ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                  ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                  ->where([
                    'sos_last_folders.egr_requisiciones' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                  ])
                  ->limit(1)->update(
                    array(
                      'sos_last_folders.folder' => $folio_nuevo,
                    )
                  );
              }

              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "El registro de requisición se realizó correctamente con el folio REQ-" . $JwtAuth->generarFolio($folio_nuevo),
                "pdflink" => "https://downloads.sos-mexico.com.mx/requisicion_pdf/" . $token_requisicion,
                "requisicion_identificador" => $token_requisicion
              );
            }
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => 'Esta requisición no fue registrada debido a errores internos, intente nuevamente'
            );
          }
        } else {
          if (!isset($token_proyecto) || empty($token_proyecto) || !preg_match($JwtAuth->filtroAlfaNumerico(), $proyecto)) {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => 'Error en nombre de proyecto para requisición'
            );
          }
          if (!isset($prioridad) || empty($prioridad) || !preg_match($JwtAuth->filtroAlfaNumerico(), $prioridad)) {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => 'Error en prioridad para requisición'
            );
          }
          if (!isset($lista_articulos) || empty($lista_articulos)) {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => 'error en lista de articulos para requisición'
            );
          }
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function requisicionLoadDocs(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('solicitud');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayJust = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "requisicion" => "required|string",
        "partida" => "required|string",
        "partidaNames" => "required|array"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $fecha_sistema = time();
        $requisicionToken = $parametrosArray["requisicion"];
        $requisicionPartida = $parametrosArray["partida"];
        $partida_names = $parametrosArray["partidaNames"];

        //if (isset($requisicionToken) && !empty($requisicionToken) && isset($requisicionPartida) && !empty($requisicionPartida) && isset($_FILES["partidaAnexos"]) && !empty($_FILES["partidaAnexos"])) {
        if (isset($requisicionToken) && !empty($requisicionToken) && isset($requisicionPartida) && !empty($requisicionPartida)) {

          $queryRequisicion = DB::table("eegr_compras_requisicion_detalle AS reqDet")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->join("main_empresas AS emp", "reqMain.empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "reqDet.token_detalle_requisicion" => $requisicionPartida,
              "reqMain.token_requisicion" => $requisicionToken,
              "reqMain.status" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token,
            ])->get();

          foreach ($queryRequisicion as $vReq) {
            date_default_timezone_set($vReq->zona_horaria);
            $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vReq->folio);
            $select_requisicion_main = DB::select("SELECT id FROM eegr_compras_requisicion WHERE token_requisicion = ?", [$vReq->token_requisicion]);
            $select_requi_partida = DB::select("SELECT id FROM eegr_compras_requisicion_detalle WHERE token_detalle_requisicion = ?", [$vReq->token_detalle_requisicion]);

            $filepath = $vReq->root_tkn . "/0002-cpp/compras/requisiciones/" . $requisicion_folio . "/";
            if (!file_exists(storage_path("/root/" . $filepath))) {
              Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
            }

            $bool_docs_continue = false;
            $count_docs_deleted = 0;
            $list_docs = DB::table("sos_documentos AS docs")
              ->join("eegr_compras_requisicion AS reqMain", "docs.requisicion", "=", "reqMain.id")
              ->join("eegr_compras_requisicion_detalle AS reqDet", "docs.detalle_requisicion", "=", "reqDet.id")
              ->where([
                "docs.status_documento" => TRUE,
                "reqMain.token_requisicion" => $vReq->token_requisicion,
                "reqDet.token_detalle_requisicion" => $vReq->token_detalle_requisicion
              ])->get();
            //return response()->json(["status" => "error","code" => 200,'message' => "count ".count($list_docs)]);
            if (count($list_docs) > 0) {
              $count_all_docs = 0;
              foreach ($list_docs as $rDocs) {
                $archivo = Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($rDocs->nombre_documento));
                $delete_doc = DB::table("sos_documentos")->where(["token_documento" => $rDocs->token_documento])
                  ->limit(1)->update(array("status_documento" => FALSE, "fecha_delete_documento" => time()));
                if (file_exists($archivo)) {
                  Storage::delete("public/root/" . $filepath . "/" . $JwtAuth->desencriptar($rDocs->nombre_documento));
                  ++$count_docs_deleted;
                } else {
                  ++$count_docs_deleted;
                }
                ++$count_all_docs;
              }

              if ($count_all_docs == count($list_docs)) {
                $bool_docs_continue = true;
              }
            } else {
              $bool_docs_continue = true;
            }
            if ($bool_docs_continue == true) {
              $countdocs_insertados = 0;
              $anexos = $_FILES["partidaAnexos"];
              $docs_nombre = json_decode(json_encode($_FILES["partidaAnexos"]["name"]));
              for ($i = 0; $i < count($docs_nombre); $i++) {
                $ext_doc = $partida_names[$i]["typoElement"];
                $documento_crypt = $JwtAuth->encriptar($partida_names[$i]["nameFile"]);
                $temporal = $anexos["tmp_name"][$i];
                $token_documento = $JwtAuth->encriptarToken($requisicionPartida, $ext_doc, $documento_crypt);
                //return response()->json(["status" => "error","code" => 200,'message' => $temporal]);
                $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%CR-EVID%'");

                $insertDocSoli = DB::table("sos_documentos")->insert(
                  array(
                    "token_documento" => $token_documento,
                    "fecha_carga" => time(),
                    "modulo" => "compras",
                    "folio_modulo" => "CR-EVID" . end($select_folio_doc)->folio,
                    "tipo_documento" => "file",
                    "nombre_documento" => $documento_crypt,
                    "extension_documento" => $ext_doc,
                    "requisicion" => end($select_requisicion_main)->id,
                    "detalle_requisicion" => end($select_requi_partida)->id,
                    "status_documento" => TRUE,
                    "fecha_delete_documento" => NULL,
                  )
                );
                //return response()->json(["status" => "error","code" => 200,'message' => "test k"]);
                if ($insertDocSoli) {
                  ++$countdocs_insertados;
                  Storage::putFileAs("/public/root/" . $filepath, $temporal, $partida_names[$i]["nameFile"]);
                }
              }

              if ($countdocs_insertados == count($partida_names)) {
                $dataMensaje = array(
                  "status" => "success",
                  "code" => 200,
                  "message" => "Reembolso " . $requisicion_folio . " fue actualizado, se cargaron " . $countdocs_insertados . " nuevos documentos y se eliminaron " . $count_docs_deleted . " documentos no encontrados"
                );
              } else {
                $dataMensaje = array(
                  "status" => 'error',
                  "code" => 200,
                  "message" => 'Error en actualización de solicitud'
                );
              }
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                'message' => 'Error: no se observó el listado total se archivos vinculados'
              );
            }

            //return response()->json(["status" => "error","code" => 200,'message' => "cfdi_solicitud_18"]);
            //return response()->json(["status" => "error","code" => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
          }
        } else {
          if (!isset($requisicionToken) || empty($requisicionToken)) {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              'message' => 'Folio de reembolso incorrecto'
            );
          }
          if (!isset($requisicionPartida) || empty($requisicionPartida)) {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              'message' => 'La solicitud de reembolso es invalida'
            );
          }
          //if(!isset($_FILES["partidaAnexos"]) || empty($_FILES["partidaAnexos"])) {
          //    $dataMensaje = array(
          //        "status" => "error",
          //        "code" => 200,
          //        'message' => 'Error en los archivos que intenta cargar'
          //    );
          //}
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        'message' => 'La informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
