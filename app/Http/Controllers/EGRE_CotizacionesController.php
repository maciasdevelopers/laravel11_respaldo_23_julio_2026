<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\CotizacionesModelo;
use Illuminate\Support\Facades\DB;

class EGRE_CotizacionesController extends Controller{
  public function solicitudesCotizacion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $arrayCotizaciones = array();

    $listaSoliCot = DB::table("eegr_compras_cotizacion_solicitud AS soliCot")
      ->join("eegr_compras_requisicion AS reqMain", "soliCot.requisicion", "=", "reqMain.id")
      ->join("main_empresas AS emp", "soliCot.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "soliCot.status_cotizacion_solicitud" => TRUE,
        "emp.empresa_token" => $usuario->empresa_token,
        "users.usuario_token" => $usuario->user_token
      ])->get();

    foreach ($listaSoliCot as $rCot) {
      //da_te_default_timezone_set('America/Mexico_City');
      $token_requisicion = $rCot->token_requisicion;
      $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($rCot->folio);
      $requisicion_fecha_registro = gmdate('Y-m-d H:i:s', $rCot->fecha) . "";

      $queryExpide = DB::table("eegr_compras_cotizacion_solicitud AS soliCot")
        ->join("vhum_empleados_catalogo AS pers", "soliCot.usuario_expide", "=", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
        ->where([
          "soliCot.token_solicitud_cotizacion" => $rCot->token_solicitud_cotizacion
        ])->get();

      $usuario_expide = $JwtAuth->desencriptarNombres($queryExpide[0]->paterno, $queryExpide[0]->materno, $queryExpide[0]->nombre);

      $desglose_requi_true = array();
      $num_lista = 1;
      $selectDetReqTrue = DB::table("eegr_compras_requisicion_detalle AS reqDet")
        ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
        ->join("eegr_compras_cotizacion_solicitud_requi AS rel", "reqDet.id", "=", "rel.requisicion_detalle")
        ->join("eegr_compras_cotizacion_solicitud AS soliCot", "rel.cotizacion_solicitud", "=", "soliCot.id")
        ->where([
          "soliCot.token_solicitud_cotizacion" => $rCot->token_solicitud_cotizacion,
          "reqMain.token_requisicion" => $token_requisicion,
          "reqDet.status_req" => TRUE
        ])->get();

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
        $list_num_caract = 1;
        $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
          ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
          ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
          ->where(["reqMain.token_requisicion" => $token_requisicion, "reqDet.token_detalle_requisicion" => $vDet->token_detalle_requisicion])->get();
        //echo count($selectDetReqCaractList);
        foreach ($selectDetReqCaractList as $vCaract) {
          $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
          $db_valor = $JwtAuth->desencriptar($vCaract->valor);
          $fisrt_valor = $descif_clave == "Precio" ? "$" . number_format($db_valor, 2, '.', ',') : $db_valor;
          $descif_valor = $list_num_caract > count($selectDetReqCaractList) ? $fisrt_valor . "," : $fisrt_valor;
          $row_CaractList = array(
            "token_caract" => $vCaract->token_caract,
            "num_list" => $list_num_caract,
            "clave" => $descif_clave,
            "valorFront" => $descif_valor,
            "valorBack" => $db_valor
          );
          $list_caract_array[] = $row_CaractList;
          ++$list_num_caract;
        }
        $txt_other_caract = $vDet->caracteristicas_extend != NULL ? $JwtAuth->desencriptar($vDet->caracteristicas_extend) : null;
        $det_requi_unidad_medida = $vDet->medida_unidad;
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
          $filepath = $rCot->root_tkn . "/0002-cpp/compras/requisiciones/" . $requisicion_folio;
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

        $rowDet = array(
          "num_lista" => $num_lista,
          "token_detalle_requisicion" => $vDet->token_detalle_requisicion,
          "requi_tipo_back" => $vDet->tipo_necesidad,
          "requi_tipo_front" => $det_requi_tipo,
          "requi_necesidad" => $JwtAuth->desencriptar($vDet->necesidad),
          "requi_caracteristicas_view" => false,
          "requi_caracteristicas_list" => $list_caract_array,
          "requi_caracteristicas_other" => $txt_other_caract,
          "requi_cantidad_autorizada" => $vDet->cantidad_autorizada,
          //"requi_unidad_medida_token" => $vDet->token_unidad_medida,
          "requi_unidad_medida_name" => $det_requi_unidad_medida,
          "requi_marca" => $det_requi_marca,
          "bool_requi_autorizacion" => $des_bool_requisicion_autorizacion,
          "requi_autorizacion" => $des_requisicion_autorizacion,
          "requi_persona_autoriza" => $des_persona_autoriza,
          "archivosPartida" => $archivosPartidaArray,
          "open_desglose" => false,
          "nueva_clave_nombre" => "",
          "proveedores" => [],
          "proveedores_mejor_opcion" => [],
          "proveedores_opcion_bool" => false,
          "adicionales" => [],
          "comentarios_finales" => "",
        );
        $desglose_requi_true[] = $rowDet;
        ++$num_lista;
      }

      $row = array(
        "token_solicitud_cotizacion" => $rCot->token_solicitud_cotizacion,
        "fecha_registro" => gmdate('Y-m-d H:i:s', $rCot->fecha_registro),
        "folio_registro" => $JwtAuth->generarFolio($rCot->folio_registro),
        "requisicion_token" => $token_requisicion,
        "requisicion_folio" => $requisicion_folio,
        "requisicion_fecha" => $requisicion_fecha_registro,
        "usuario_expide" => $usuario_expide,
        "desglose" => $desglose_requi_true,
        "abierto" => false,
      );
      $arrayCotizaciones[] = $row;
    }

    return response()->json(['status' => 'success', 'codigo' => 200, 'solicitudes' => $arrayCotizaciones]);
  }

  public function solicitudesCotizacionCheck(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $listaSolicitudes = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string"
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
          $listaCot = DB::select("SELECT soliCot.*,reqMain.token_requisicion,reqMain.folio AS folioReq,reqMain.proyecto,reqMain.fecha AS dateReq 
                        FROM eegr_compras_cotizacion_solicitud AS soliCot 
                        JOIN eegr_compras_requisicion AS reqMain WHERE soliCot.status_cotizacion_solicitud	= TRUE AND soliCot.requisicion = reqMain.id 
                        AND soliCot.id IN (SELECT cotList.solicitud_cotizacion FROM eegr_compras_cotizacion AS cotList JOIN main_empresas AS emp
                        WHERE cotList.empresa = emp.id AND emp.empresa_token = ?)", [$usuario->empresa_token]);
        } else {
          $listaCot = DB::select("SELECT soliCot.*,reqMain.token_requisicion,reqMain.folio AS folioReq,reqMain.proyecto,reqMain.fecha AS dateReq 
                        FROM eegr_compras_cotizacion_solicitud AS soliCot 
                        JOIN eegr_compras_requisicion AS reqMain WHERE soliCot.status_cotizacion_solicitud	= TRUE AND soliCot.requisicion = reqMain.id 
                            AND soliCot.id IN (SELECT cotList.solicitud_cotizacion FROM eegr_compras_cotizacion AS cotList 
                            JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE cotList.empresa = emp.id AND emp.empresa_token = ? AND cotList.usuario_cotizador = pers.id
                            AND pers.id = users.empleado AND users.usuario_token = ?)", [$usuario->empresa_token, $usuario->user_token]);
        }

        if (count($listaCot) > 0) {
          foreach ($listaCot as $rCot) {
            //da_te_default_timezone_set('America/Mexico_City');
            //echo $resCot->token_solicitud_cotizacion." ".$resCot->fecha_registro." ".$resCot->folio_registro;
            $requisicion_tkn = $rCot->token_requisicion;
            $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($rCot->folioReq);
            $requisicion_proyecto = $requisicion_folio . " - " . $JwtAuth->desencriptar($rCot->proyecto) . " (" . gmdate('Y-m-d H:i:s', $rCot->dateReq) . ")";

            $queryExpide = DB::table("eegr_compras_cotizacion_solicitud AS soliCot")
              ->join("vhum_empleados_catalogo AS pers", "soliCot.usuario_expide", "=", "pers.id")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where([
                "soliCot.token_solicitud_cotizacion" => $rCot->token_solicitud_cotizacion
              ])->get();
            $usuario_expide = $JwtAuth->desencriptarNombres($queryExpide[0]->paterno, $queryExpide[0]->materno, $queryExpide[0]->nombre);

            $row = array(
              "token_solicitud_cotizacion" => $rCot->token_solicitud_cotizacion,
              "folio_registro" => $JwtAuth->generarFolio($rCot->folio_registro),
              "fecha_registro" => gmdate('Y-m-d H:i:s', $rCot->fecha_registro),
              "modal_cotizacion" => $JwtAuth->generarFolio($rCot->folio_registro) . "_" . date('d-m-Y_H:i:s', $rCot->fecha_registro),
              "requisicion_tkn" => $requisicion_tkn,
              "requisicion_folio" => $requisicion_folio,
              "requisicion_proyecto" => $requisicion_proyecto,
              "usuario_expide" => $usuario_expide,
            );
            $listaSolicitudes[] = $row;
          }

          $dataMensaje = array("status" => "success", "code" => 200, "solicitudes" => $listaSolicitudes);
        } else {
          $dataMensaje = array("status" => "error", "code" => 404, "message" => 'No hay solicitudes de cotización');
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

  public function solicitudCotizacionDetalle(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $listaSolicitudes = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_solicitud_cotizacion" => "required|string"
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
        $token_solicitud_cotizacion = $parametrosArray["token_solicitud_cotizacion"];

        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
          $querySoli = DB::select("SELECT soliCot.*,reqMain.token_requisicion,reqMain.folio AS folioReq,reqMain.proyecto,reqMain.fecha AS dateReq 
                        FROM eegr_compras_cotizacion_solicitud AS soliCot 
                        JOIN eegr_compras_requisicion AS reqMain WHERE soliCot.requisicion = reqMain.id AND soliCot.id IN (
                        SELECT cotList.solicitud_cotizacion FROM eegr_compras_cotizacion AS cotList JOIN main_empresas AS emp
                        WHERE cotList.empresa = emp.id AND emp.empresa_token = ?) AND soliCot.token_solicitud_cotizacion = ?", [$usuario->empresa_token, $token_solicitud_cotizacion]);
        } else {
          $querySoli = DB::select("SELECT soliCot.*,reqMain.token_requisicion,reqMain.folio AS folioReq,reqMain.proyecto,reqMain.fecha AS dateReq 
                        FROM eegr_compras_cotizacion_solicitud AS soliCot 
                        JOIN eegr_compras_requisicion AS reqMain WHERE soliCot.requisicion = reqMain.id AND soliCot.id IN (
                            SELECT cotList.solicitud_cotizacion FROM eegr_compras_cotizacion AS cotList 
                            JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE cotList.empresa = emp.id AND emp.empresa_token = ? AND cotList.usuario_cotizador = pers.id
                            AND pers.id = users.empleado AND users.usuario_token = ?) AND soliCot.token_solicitud_cotizacion = ?", [$usuario->empresa_token, $usuario->user_token, $token_solicitud_cotizacion]);
        }

        if (count($querySoli) > 0) {
          foreach ($querySoli as $sCot) {
            //da_te_default_timezone_set('America/Mexico_City');
            //echo $resCot->token_solicitud_cotizacion." ".$resCot->fecha_registro." ".$resCot->folio_registro;
            $requisicion_tkn = $sCot->token_requisicion;
            $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($sCot->folioReq);
            $requisicion_proyecto = $requisicion_folio . " - " . $JwtAuth->desencriptar($sCot->proyecto) . " (" . gmdate('Y-m-d H:i:s', $sCot->dateReq) . ")";

            $queryExpide = DB::table("eegr_compras_cotizacion_solicitud AS soliCot")
              ->join("vhum_empleados_catalogo AS pers", "soliCot.usuario_expide", "=", "pers.id")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where([
                "soliCot.token_solicitud_cotizacion" => $sCot->token_solicitud_cotizacion
              ])->get();
            $usuario_expide = $JwtAuth->desencriptarNombres($queryExpide[0]->paterno, $queryExpide[0]->materno, $queryExpide[0]->nombre);

            $listaCotizacionesHeaders = array();
            $listaCot = DB::table("eegr_compras_cotizacion AS cotList")
              ->join("eegr_compras_cotizacion_solicitud AS soliCot", "cotList.solicitud_cotizacion", "=", "soliCot.id")
              ->join("main_empresas AS emp", "cotList.empresa", "emp.id")
              ->where(["soliCot.token_solicitud_cotizacion" => $sCot->token_solicitud_cotizacion, "emp.empresa_token" => $usuario->empresa_token])->get();
            //echo count($listaCot);
            if (count($listaCot) > 0) {
              foreach ($listaCot as $rCot) {
                //$main_moneda_token = $rCot->token_monedas;
                //$main_moneda_codigo = $rCot->codigo;
                $main_moneda_name = $rCot->e_moneda_code;
                $main_moneda_decimales = $rCot->e_moneda_decimales;

                $persona_cotiza = "";
                $cotUser = DB::table("eegr_compras_cotizacion AS cotList")
                  ->join("vhum_empleados_catalogo AS pers", "cotList.usuario_cotizador", "=", "pers.id")
                  ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
                  ->where(["cotList.token_cotizacion" => $rCot->token_cotizacion])->get();

                foreach ($cotUser as $cUser) {
                  $persona_cotiza = $JwtAuth->desencriptarNombres($cUser->paterno, $cUser->materno, $cUser->nombre);
                }

                $rowCot = array(
                  "token_cotizacion" => $rCot->token_cotizacion,
                  "fecha_cotizacion" => gmdate('Y-m-d H:i:s', $rCot->coti_fecha_sistema),
                  "folio_cotizacion" => "COT-" . $JwtAuth->generarFolio($rCot->coti_folio),
                  "persona_cotiza" => $persona_cotiza,
                  "comentarios_finales" => $rCot->comentarios_finales != NULL ? $JwtAuth->desencriptar($rCot->comentarios_finales) : "",
                  "url_pdf" => "https://downloads.sos-mexico.com.mx/cotizacion_pdf/" . $requisicion_tkn . "/" . $rCot->coti_folio,
                  //"url_pdf" => "https://downloads.sos-mexico.com.mx/cotizacion_pdf/".$rCot->token_cotizacion,
                );
                $listaCotizacionesHeaders[] = $rowCot;
              }
            }

            $listaCotizacionesDetalle = array();
            $listaCotizacionesDetalleMejorOpcion = array();
            $num_lista = 1;

            $cotDetalleExtend = DB::table("eegr_compras_cotizacion_detalle_descripcion AS cotDesk")
              ->join("eegr_compras_cotizacion_detalle AS cotDet", "cotDesk.detalle_cotizacion", "=", "cotDet.id")
              ->join("eegr_catalogo_proveedores AS catprov", "cotDesk.coti_proveedor", "=", "catprov.id")
              ->join("sos_personas AS prv", "catprov.proveedor", "=", "prv.id")
              ->join("teci_forma_pago AS fpay", "cotDesk.coti_forma_pago", "=", "fpay.id")
              ->join("eegr_compras_requisicion_detalle AS reqDet", "cotDet.detalle_requisicion", "=", "reqDet.id")
              //->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
              ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
              ->join("eegr_compras_cotizacion_solicitud AS soliCot", "cotMain.solicitud_cotizacion", "=", "soliCot.id")
              ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
              ->where([
                "reqDet.status_req" => TRUE,
                "soliCot.token_solicitud_cotizacion" => $sCot->token_solicitud_cotizacion,
                "emp.empresa_token" => $usuario->empresa_token
              ])->get();

            if (count($cotDetalleExtend) > 0) {
              # code...
              foreach ($cotDetalleExtend as $cDet) {
                //$main_moneda_token = $cDet->token_monedas;
                //$main_moneda_codigo = $cDet->codigo;
                $main_moneda_name = $cDet->e_moneda_code;
                $main_moneda_decimales = $cDet->e_moneda_decimales;

                $tkn_detcot = $cDet->token_detalle_cotizacion;
                if ($cDet->tipo_necesidad == "Merc") {
                  $det_requi_tipo = "Mercancia";
                }
                if ($cDet->tipo_necesidad == "Gast") {
                  $det_requi_tipo = "Gastos";
                }
                if ($cDet->tipo_necesidad == "Acti") {
                  $det_requi_tipo = "Activos";
                }
                if ($cDet->tipo_necesidad == "Mixt") {
                  $det_requi_tipo = "Mixto";
                }

                $list_caract_array = array();
                $list_num_caract = 1;
                $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
                  ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
                  ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
                  ->where(["reqMain.token_requisicion" => $requisicion_tkn, "reqDet.token_detalle_requisicion" => $cDet->token_detalle_requisicion])->get();
                //echo count($selectDetReqCaractList);
                foreach ($selectDetReqCaractList as $vCaract) {
                  $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
                  $db_valor = $JwtAuth->desencriptar($vCaract->valor);
                  $fisrt_valor = $descif_clave == "Precio" ? "$" . number_format($db_valor, 2, '.', ',') : $db_valor;
                  $descif_valor = $list_num_caract > count($selectDetReqCaractList) ? $fisrt_valor . "," : $fisrt_valor;
                  $row_CaractList = array(
                    "token_caract" => $vCaract->token_caract,
                    "num_list" => $list_num_caract,
                    "clave" => $descif_clave,
                    "valorFront" => $descif_valor,
                    "valorBack" => $db_valor
                  );
                  $list_caract_array[] = $row_CaractList;
                  ++$list_num_caract;
                }

                $txt_other_caract = $cDet->caracteristicas_extend != NULL ? $JwtAuth->desencriptar($cDet->caracteristicas_extend) : null;
                $det_requi_unidad_medida = $cDet->coti_unidad_medida;
                $det_requi_marca = $cDet->marca != NULL ? $JwtAuth->desencriptar($cDet->marca) :  "no hay marca referida";

                $des_persona_autoriza = "---";
                if ($cDet->des_autorizacion == "A" && $cDet->des_autoriza_user != NULL) {
                  $queryAutoriza = DB::table("vhum_empleados_catalogo AS pers")
                    ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
                    ->where(["pers.id" => $cDet->des_autoriza_user])->get();

                  foreach ($queryAutoriza as $rAutoriza) {
                    $denominacion_rs = $rAutoriza->denominacion_rs;
                    $des_persona_autoriza = $denominacion_rs ? $JwtAuth->desencriptar($rAutoriza->denominacion_rs) : $JwtAuth->desencriptarNombres($rAutoriza->paterno, $rAutoriza->materno, $rAutoriza->nombre);
                  }
                }

                if ($cDet->des_autorizacion == TRUE) {
                  $des_bool_requisicion_autorizacion = true;
                  $des_requisicion_autorizacion = "Requisición autorizada por " . $des_persona_autoriza . " (" . gmdate('Y-m-d H:i:s', $cDet->des_fecha_autorizacion) . ")";
                } else {
                  $des_bool_requisicion_autorizacion = false;
                  $des_requisicion_autorizacion = "Requisición no autorizada";
                }

                $proveedor_tkn = $cDet->token_cat_proveedores;
                $proveedor_name = $JwtAuth->desencriptar($cDet->nombre_extendido);
                $proveedor_rfc_generico = $cDet->rfc_generico;
                $proveedor_rfc = $cDet->rfc != NULL ? $JwtAuth->desencriptar($cDet->rfc) : "---";
                $proveedor_taxId = $cDet->tax_id != NULL ? $JwtAuth->desencriptar($cDet->tax_id) : "---";

                if ($cDet->coti_entrega_tipo == "domi") {
                  $coti_entrega_tipo_extend = "Domicilio";
                } else if ($cDet->coti_entrega_tipo == "stre") {
                  $coti_entrega_tipo_extend = "Tienda";
                } else if ($cDet->coti_entrega_tipo == "ofna") {
                  $coti_entrega_tipo_extend = "Oficina";
                } else if ($cDet->coti_entrega_tipo == "dest") {
                  $coti_entrega_tipo_extend = "Destino";
                } else if ($cDet->coti_entrega_tipo == "cntr") {
                  $coti_entrega_tipo_extend = "Contra reembolso";
                }

                $cotDetDeskUMedida = DB::table("eegr_compras_cotizacion_detalle_descripcion AS cotDesk")
                  ->join("eegr_compras_cotizacion_detalle AS cotDet", "cotDesk.detalle_cotizacion", "=", "cotDet.id")
                  ->join("eegr_catalogo_proveedores AS catprov", "cotDesk.coti_proveedor", "=", "catprov.id")
                  ->join("sos_personas AS prv", "catprov.proveedor", "=", "prv.id")
                  ->join("teci_catalogo_monedas AS mon", "cotDesk.coti_moneda", "=", "mon.id")
                  ->join("teci_unidad_medida AS umed", "cotDesk.coti_unidad_medida", "=", "umed.id")
                  ->join("teci_forma_pago AS fpay", "cotDesk.coti_forma_pago", "=", "fpay.id")
                  //->join("teci_metodo_pago AS mpay","cotDesk.coti_metodo_pago","=","mpay.id")
                  ->join("eegr_compras_requisicion_detalle AS reqDet", "cotDet.detalle_requisicion", "=", "reqDet.id")
                  ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
                  ->where(["cotDet.token_detalle_cotizacion" => $tkn_detcot, "cotMain.token_cotizacion" => $cDet->token_cotizacion])->get();

                $coti_credito_otorga = $cDet->coti_credito_otorga == TRUE ? true : false;
                $coti_moneda_decimales = $JwtAuth->getMonedaAPI($cDet->coti_moneda);
                $coti_credito_time = $cDet->coti_credito_otorga == TRUE ? $JwtAuth->desencriptar($cDet->coti_credito_time) : null;
                //$coti_precio = "$".number_format($cDet->coti_precio,$cotDetDeskMoneda[0]->decimales,'.',',')." ".$cDet->codigo." ".$cDet->moneda;
                $coti_precio = "$" . number_format($cDet->coti_precio, $coti_moneda_decimales, '.', ',') . " " . $cDet->coti_moneda;

                if ($main_moneda_name == $cDet->coti_moneda) {
                  //$coti_conversion = "$".number_format($cotDetDeskMoneda[0]->coti_precio,$cotDetDeskMoneda[0]->decimales,'.',',')." ".$cotDetDeskMoneda[0]->codigo." ".$cotDetDeskMoneda[0]->moneda;
                  $coti_conversion = "$" . number_format($cDet->coti_precio, $coti_moneda_decimales, '.', ',') . " " . $cDet->coti_moneda;
                } else {
                  $convet = $cDet->coti_precio * $cDet->coti_tipo_cambio;
                  //$coti_conversion = "$".number_format($convet,$main_moneda_decimales,'.',',')." ".$main_moneda_codigo." ".$main_moneda_name;
                  $coti_conversion = "$" . number_format($convet, $main_moneda_decimales, '.', ',') . " " . $main_moneda_name;
                }

                $coti_desc_autorizacion = $cDet->coti_desc_autorizacion == TRUE ? true : false;
                $coti_desc_fecha_autorizacion = $cDet->coti_desc_autorizacion == TRUE ? gmdate('Y-m-d H:i:s', $cDet->coti_desc_fecha_autorizacion) : null;

                $coti_desc_pers_autoriza = "";
                if ($cDet->coti_desc_autorizacion == TRUE) {
                  $persAuthCoti = DB::table("sos_personas AS people")
                    ->join("vhum_empleados_catalogo AS persAuth", "people.id", "=", "persAuth.empleado_name")
                    ->join("eegr_compras_cotizacion_detalle_descripcion AS cotDesk", "persAuth.id", "=", "cotDesk.coti_desc_pers_autoriza")
                    ->where(["cotDesk.token_desc_detalle_cotiza" => $cDet->token_desc_detalle_cotiza])->get();
                  $coti_desc_pers_autoriza = $JwtAuth->desencriptarNombres($persAuthCoti[0]->paterno, $persAuthCoti[0]->materno, $persAuthCoti[0]->nombre);
                }

                $valoracion_stars = "";
                $valoracion_estrellas = array();
                $valoracion_posicion = "";
                $valoracion_comentarios = "";
                $queryCotimOP = DB::table("eegr_compras_cotizacion_detalle_mejor_opcion AS mOP")
                  ->join("eegr_compras_cotizacion AS cotMain", "mOP.cotizacion", "=", "cotMain.id")
                  ->join("eegr_compras_cotizacion_detalle AS cotDesk", "mOP.detalle_cotizacion", "=", "cotDesk.id")
                  ->join("eegr_catalogo_proveedores AS catprov", "mOP.proveedor", "=", "catprov.id")
                  ->where([
                    "cotMain.token_cotizacion" => $cDet->token_cotizacion,
                    "cotDesk.token_detalle_cotizacion" => $cDet->token_detalle_cotizacion,
                    "catprov.token_cat_proveedores" => $cDet->token_cat_proveedores
                  ])->get();

                if (count($queryCotimOP) == 1) {
                  foreach ($queryCotimOP as $vMop) {
                    $valoracion_stars = $vMop->posicion == 1 ? 3 : ($vMop->posicion == 2 ? 2 : ($vMop->posicion == 3 ? 1 : 0));
                    $valoracion_posicion = $vMop->posicion;
                    $valoracion_comentarios = $JwtAuth->desencriptar($vMop->observaciones);
                  }
                }

                for ($s = 0; $s < $valoracion_stars; $s++) {
                  $row_star = array("xclase" => "fa-solid fa-star");
                  $valoracion_estrellas[] = $row_star;
                }

                /*$insertCotizacionDescAdicionales = DB::table('eegr_compras_cotizacion_detalle_adicionales')->insert(
                                    array(
                                        "cotizacion" => end($selectCotizacionMain)->id,
                                        "detalle_cotizacion" => end($selectCotizacionDetalle)->id,
                                        "clave" => $JwtAuth->encriptar($adicionales_claves),
                                        "proveedor" => $row_prv_adi,
                                        "valor" => $JwtAuth->encriptar($adi_prv_val),
                                    ) 
                                );*/

                $adicionalesList = array();
                $queryCotiMore = DB::table("eegr_compras_cotizacion_detalle_adicionales AS more")
                  ->join("eegr_compras_cotizacion AS cotMain", "more.cotizacion", "=", "cotMain.id")
                  ->join("eegr_compras_cotizacion_detalle AS cotDesk", "more.detalle_cotizacion", "=", "cotDesk.id")
                  ->join("eegr_catalogo_proveedores AS catprov", "more.proveedor", "=", "catprov.id")
                  ->where([
                    "cotMain.token_cotizacion" => $cDet->token_cotizacion,
                    "cotDesk.token_detalle_cotizacion" => $cDet->token_detalle_cotizacion,
                    "catprov.token_cat_proveedores" => $cDet->token_cat_proveedores
                  ])->get();

                foreach ($queryCotiMore as $vMore) {
                  $rowMore = array(
                    "clave" => $JwtAuth->desencriptar($vMore->clave),
                    "valor" => $JwtAuth->desencriptar($vMore->valor),
                  );
                  $adicionalesList[] = $rowMore;
                }

                $rowDet = array(
                  "token_cotizacion" => $cDet->token_cotizacion,
                  "token_detalle_cotizacion" => $tkn_detcot,
                  "num_lista" => $num_lista,
                  "token_detalle_requisicion" => $cDet->token_detalle_requisicion,
                  "requi_tipo_back" => $cDet->tipo_necesidad,
                  "requi_tipo_front" => $det_requi_tipo,
                  "requi_necesidad" => $JwtAuth->desencriptar($cDet->necesidad),
                  "requi_caracteristicas_view" => false,
                  "requi_caracteristicas_list" => $list_caract_array,
                  "requi_caracteristicas_other" => $txt_other_caract,
                  "requi_cantidad_autorizada" => $cDet->cantidad_autorizada,
                  //"requi_unidad_medida_token" => $cDet->token_unidad_medida,
                  "requi_unidad_medida_name" => $det_requi_unidad_medida,
                  "requi_marca" => $det_requi_marca,
                  "bool_requi_autorizacion" => $des_bool_requisicion_autorizacion,
                  "requi_autorizacion" => $des_requisicion_autorizacion,
                  "requi_persona_autoriza" => $des_persona_autoriza,
                  "open_desglose" => false,
                  //"cotizaciones" => $detalleDeskList,
                  //"coty_mejor_opcion" => $detalleMejorOpcion,
                  "token_desc_detalle_cotiza" => $cDet->token_desc_detalle_cotiza,
                  "coti_fecha" => gmdate('Y-m-d H:i:s', $cDet->coti_fecha_sistema),
                  "coti_folio" => "COT-" . $JwtAuth->generarFolio($cDet->coti_folio),
                  "coti_proveedor_tkn" => $proveedor_tkn,
                  "coti_proveedor_name" => $proveedor_name,
                  "coti_proveedor_rfc_generico" => $proveedor_rfc_generico,
                  "coti_proveedor_rfc" => $proveedor_rfc,
                  "coti_proveedor_taxId" => $proveedor_taxId,
                  "valoracion_stars" => $valoracion_stars,
                  "valoracion_estrellas" => $valoracion_estrellas,
                  "valoracion_posicion" => $valoracion_posicion,
                  "valoracion_comentarios" => $valoracion_comentarios,

                  "coti_especificaciones" => $JwtAuth->desencriptar($cDet->coti_especificaciones),
                  "coti_cantidad" => $cDet->coti_cantidad,
                  //token_monedas
                  //"coti_moneda_token" => $cDet->token_monedas,
                  "coti_moneda_codigo" => $cDet->coti_moneda,
                  //"coti_moneda_name" => $cDet->moneda,
                  "coti_moneda_decimales" => $coti_moneda_decimales,

                  "coti_precio" => $coti_precio,
                  "coti_tipo_cambio" => $cDet->coti_tipo_cambio,
                  "coti_conversion" => $coti_conversion,

                  "coti_calidad" => $JwtAuth->desencriptar($cDet->coti_calidad),
                  "coti_servicio" => $JwtAuth->desencriptar($cDet->coti_servicio),
                  "coti_entrega_tipo" => $cDet->coti_entrega_tipo,
                  "coti_entrega_tipo_extend" => $coti_entrega_tipo_extend,
                  "coti_entrega_tiempo" => $JwtAuth->desencriptar($cDet->coti_entrega_tiempo),
                  "coti_descuento" => $JwtAuth->desencriptar($cDet->coti_descuento),
                  "coti_retenciones" => $JwtAuth->desencriptar($cDet->coti_retenciones),
                  "coti_traslados" => $JwtAuth->desencriptar($cDet->coti_traslados),
                  "coti_credito_otorga" => $coti_credito_otorga,
                  "coti_credito_time" => $coti_credito_time,
                  "coti_garantia" => $JwtAuth->desencriptar($cDet->coti_garantia),
                  //coti_unidad_medida
                  //"coti_umed_token" => $cDet->token_unidad_medida,
                  "coti_umed_name" => $cDet->coti_unidad_medida,
                  //"coti_umed_sat_clave" => $cDet->sat_clave,
                  //"coti_umed_representa" => $cDet->representa,
                  //coti_forma_pago
                  "coti_fpay_token" => $cDet->token_formapago,
                  "coti_fpay_clave" => $cDet->clave,
                  "coti_fpay_forma" => $cDet->forma,
                  "coti_valoracion" => $JwtAuth->desencriptar($cDet->coti_valoracion),
                  "coti_desc_open" => false,
                  "coti_especificaciones_open" => false,
                  "coti_desc_autorizacion" => $coti_desc_autorizacion,
                  "coti_desc_fecha_autorizacion" => $coti_desc_fecha_autorizacion,
                  "coti_desc_pers_autoriza" => $coti_desc_pers_autoriza,
                  "select_to_auth" => false,
                  "adicionales" => $adicionalesList,
                );
                $listaCotizacionesDetalle[] = $rowDet;
                if ($valoracion_stars == 3) {
                  $listaCotizacionesDetalleMejorOpcion[] = $rowDet;
                }
                ++$num_lista;
              }
            }

            $archivosPartidaArray = array();
            /*$selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento 
                            FROM sos_documentos AS evd JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet 
                            WHERE evd.requisicion = reqMain.id AND reqMain.token_requisicion = ?
                            AND evd.detalle_requisicion = reqDet.id AND reqDet.token_detalle_requisicion = ?
                            AND evd.status_documento = TRUE",[$requisicion_tkn,$cDet->token_detalle_requisicion]);*/
            $selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento FROM sos_documentos AS evd 
                            JOIN eegr_compras_requisicion AS reqMain WHERE evd.status_documento = TRUE AND evd.requisicion = reqMain.id 
                            AND reqMain.token_requisicion = ?", [$requisicion_tkn]);

            if (count($selectIdEvid) > 0) {
              $filepath = $rCot->root_tkn . "/0002-cpp/compras/requisiciones/" . $requisicion_folio;
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

            $row = array(
              "token_solicitud_cotizacion" => $sCot->token_solicitud_cotizacion,
              "folio_registro" => $JwtAuth->generarFolio($sCot->folio_registro),
              "fecha_registro" => gmdate('Y-m-d H:i:s', $sCot->fecha_registro),
              "modal_cotizacion" => $JwtAuth->generarFolio($sCot->folio_registro) . $sCot->fecha_registro,
              "requisicion_tkn" => $requisicion_tkn,
              "requisicion_folio" => $requisicion_folio,
              "requisicion_proyecto" => $requisicion_proyecto,
              "usuario_expide" => $usuario_expide,
              "cotizacionesHeaders" => $listaCotizacionesHeaders,
              //"cotizacionesDetalle" => count($cotDetalle),
              "cotizaciones_ver_mejor_opcion" => false,
              "cotizacionesDetalle" => $listaCotizacionesDetalle,
              "cotizacionesvalidate" => false,
              "cotizacionesDetalleMejorOpcion" => $listaCotizacionesDetalleMejorOpcion,
              "cotizacionesvalidateMejorOpcion" => false,
              "archivosPartida" => $archivosPartidaArray,
            );
            $listaSolicitudes[] = $row;
          }

          $dataMensaje = array("status" => "success", "code" => 200, "solicitudes" => $listaSolicitudes);
        } else {
          $dataMensaje = array("status" => "error", "code" => 404, "message" => 'No hay solicitudes de cotización');
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

  public function catalogoCotizaciones(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCotizaciones = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string"
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
          $listaCot = DB::table("eegr_compras_cotizacion AS cotList")
            ->join("main_empresas AS emp", "cotList.empresa", "emp.id")
            ->where(["emp.empresa_token" => $usuario->empresa_token])->get();
        } else {
          $listaCot = DB::table("eegr_compras_cotizacion AS cotList")
            ->join("main_empresas AS emp", "eegr_compras_cotizacion.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "cotList.usuario_cotizador", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
            ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        }

        foreach ($listaCot as $resCot) {
          //da_te_default_timezone_set('America/Mexico_City');
          $tkn_requisicion = "";
          $proyeto_requisicion = "";
          $requisicion_folio = "";
          if ($resCot->requisicion != NULL) {
            $select_reki = DB::select("SELECT token_requisicion,folio,proyecto,fecha FROM eegr_compras_requisicion WHERE id = ?", [$resCot->requisicion]);
            $tkn_requisicion = $select_reki[0]->token_requisicion;
            $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($select_reki[0]->folio);
            $proyeto_requisicion = "REQ-" . $JwtAuth->generarFolio($select_reki[0]->folio) . " - " . $JwtAuth->desencriptar($select_reki[0]->proyecto) . " (" . gmdate('Y-m-d H:i:s', $select_reki[0]->fecha) . ")";
          }

          $persona_cotiza = "";
          $cotUser = DB::table("eegr_compras_cotizacion AS cotList")
            ->join("vhum_empleados_catalogo AS pers", "cotList.usuario_cotizador", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["cotList.token_cotizacion" => $resCot->token_cotizacion])->get();

          foreach ($cotUser as $cUser) {
            $persona_cotiza = $JwtAuth->desencriptarNombres($cUser->paterno, $cUser->materno, $cUser->nombre);
          }

          $row = array(
            "token_cotizacion" => $resCot->token_cotizacion,
            "fecha_cotizacion" => gmdate('Y-m-d H:i:s', $resCot->coti_fecha_sistema),
            "folio_cotizacion" => "COT-" . $JwtAuth->generarFolio($resCot->coti_folio),
            "modal_cotizacion" => "COT-" . $JwtAuth->generarFolio($resCot->coti_folio) . "_" . date('d-m-Y_H:i:s', $resCot->coti_fecha_sistema),
            "requisicion" => $proyeto_requisicion,
            "persona_cotiza" => $persona_cotiza,
            "url_pdf" => "https://downloads.sos-mexico.com.mx/cotizacion_pdf/" . $JwtAuth->generarFolio($resCot->coti_folio),
          );
          $arrayCotizaciones[] = $row;
        }

        $dataMensaje = array("status" => "success", "code" => 200, "lista_cotizaciones" => $arrayCotizaciones);
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

  public function cotizacionDetalle(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCotizaciones = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cotizacion" => "required|string"
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
        $token_cotizacion = $parametrosArray["token_cotizacion"];

        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
          $listaCot = DB::table("eegr_compras_cotizacion AS cotList")
            ->join("main_empresas AS emp", "cotList.empresa", "emp.id")
            ->where(["cotList.token_cotizacion" => $token_cotizacion, "emp.empresa_token" => $usuario->empresa_token])->get();
        } else {
          $listaCot = DB::table("eegr_compras_cotizacion AS cotList")
            ->join("main_empresas AS emp", "eegr_compras_cotizacion.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "cotList.usuario_cotizador", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
            ->where(["cotList.token_cotizacion" => $token_cotizacion, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        }

        foreach ($listaCot as $resCot) {
          //da_te_default_timezone_set('America/Mexico_City');
          $main_moneda_token = $resCot->token_monedas;
          $main_moneda_codigo = $resCot->codigo;
          $main_moneda_name = $resCot->e_moneda_code;
          $main_moneda_decimales = $resCot->e_moneda_decimales;

          $tkn_requisicion = "";
          $proyeto_requisicion = "";
          $requisicion_folio = "";
          if ($resCot->requisicion != NULL) {
            $select_reki = DB::select("SELECT token_requisicion,folio,proyecto,fecha FROM eegr_compras_requisicion WHERE id = ?", [$resCot->requisicion]);
            $tkn_requisicion = $select_reki[0]->token_requisicion;
            $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($select_reki[0]->folio);
            $proyeto_requisicion = "REQ-" . $JwtAuth->generarFolio($select_reki[0]->folio) . " - " . $JwtAuth->desencriptar($select_reki[0]->proyecto) . " (" . gmdate('Y-m-d H:i:s', $select_reki[0]->fecha) . ")";
          }

          $persona_cotiza = "";
          $cotUser = DB::table("eegr_compras_cotizacion AS cotList")
            ->join("vhum_empleados_catalogo AS pers", "cotList.usuario_cotizador", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["cotList.token_cotizacion" => $resCot->token_cotizacion])->get();

          foreach ($cotUser as $cUser) {
            $persona_cotiza = $JwtAuth->desencriptarNombres($cUser->paterno, $cUser->materno, $cUser->nombre);
          }

          $detalle_content = array();
          $num_lista = 1;
          $cotDetalle = DB::table("eegr_compras_cotizacion_detalle AS cotDet")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "cotDet.detalle_requisicion", "=", "reqDet.id")
            ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
            ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
            ->where(["reqDet.status_req" => TRUE, "cotMain.token_cotizacion" => $resCot->token_cotizacion])->get();

          foreach ($cotDetalle as $cDet) {
            $tkn_detcot = $cDet->token_detalle_cotizacion;

            if ($cDet->tipo_necesidad == "Merc") {
              $det_requi_tipo = "Mercancia";
            }
            if ($cDet->tipo_necesidad == "Gast") {
              $det_requi_tipo = "Gastos";
            }
            if ($cDet->tipo_necesidad == "Acti") {
              $det_requi_tipo = "Activos";
            }
            if ($cDet->tipo_necesidad == "Mixt") {
              $det_requi_tipo = "Mixto";
            }

            $list_caract_array = array();
            $list_num_caract = 1;
            $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
              ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
              ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
              ->where(["reqMain.token_requisicion" => $tkn_requisicion, "reqDet.token_detalle_requisicion" => $cDet->token_detalle_requisicion])->get();
            //echo count($selectDetReqCaractList);
            foreach ($selectDetReqCaractList as $vCaract) {
              $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
              $db_valor = $JwtAuth->desencriptar($vCaract->valor);
              $fisrt_valor = $descif_clave == "Precio" ? "$" . number_format($db_valor, 2, '.', ',') : $db_valor;
              $descif_valor = $list_num_caract > count($selectDetReqCaractList) ? $fisrt_valor . "," : $fisrt_valor;
              $row_CaractList = array(
                "token_caract" => $vCaract->token_caract,
                "num_list" => $list_num_caract,
                "clave" => $descif_clave,
                "valorFront" => $descif_valor,
                "valorBack" => $db_valor
              );
              $list_caract_array[] = $row_CaractList;
              ++$list_num_caract;
            }
            $txt_other_caract = $cDet->caracteristicas_extend != NULL ? $JwtAuth->desencriptar($cDet->caracteristicas_extend) : null;
            $det_requi_unidad_medida = $cDet->unidad_medida . " - " . $cDet->sat_clave . ", representa " . $cDet->representa;
            $det_requi_marca = $cDet->marca != NULL ? $JwtAuth->desencriptar($cDet->marca) :  "no hay marca referida";

            $des_persona_autoriza = "---";
            if ($cDet->des_autorizacion == "A" && $cDet->des_autoriza_user != NULL) {
              $queryAutoriza = DB::table("vhum_empleados_catalogo AS pers")
                ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
                ->where(["pers.id" => $cDet->des_autoriza_user])->get();

              foreach ($queryAutoriza as $rAutoriza) {
                $denominacion_rs = $rAutoriza->denominacion_rs;
                $des_persona_autoriza = $denominacion_rs ? $JwtAuth->desencriptar($denominacion_rs) : $JwtAuth->desencriptarNombres($rAutoriza->paterno, $rAutoriza->materno, $rAutoriza->nombre);
              }
            }

            if ($cDet->des_autorizacion == TRUE) {
              $des_bool_requisicion_autorizacion = true;
              $des_requisicion_autorizacion = "Requisición autorizada por " . $des_persona_autoriza . " (" . gmdate('Y-m-d H:i:s', $cDet->des_fecha_autorizacion) . ")";
            } else {
              $des_bool_requisicion_autorizacion = false;
              $des_requisicion_autorizacion = "Requisición no autorizada";
            }

            $archivosPartidaArray = array();
            $selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento 
                            FROM sos_documentos AS evd JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet 
                            WHERE evd.requisicion = reqMain.id AND reqMain.token_requisicion = ?
                            AND evd.detalle_requisicion = reqDet.id AND reqDet.token_detalle_requisicion = ?
                            AND evd.status_documento = TRUE", [$tkn_requisicion, $cDet->token_detalle_requisicion]);

            if (count($selectIdEvid) > 0) {
              $filepath = $resCot->root_tkn . "/0002-cpp/compras/requisiciones/" . $requisicion_folio;
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

            $detalleDeskList = array();
            $coti_num_lista = 1;
            $cotDetalleDesk = DB::table("eegr_compras_cotizacion_detalle_descripcion AS cotDesk")
              ->join("eegr_compras_cotizacion_detalle AS cotDet", "cotDesk.detalle_cotizacion", "=", "cotDet.id")
              ->join("eegr_catalogo_proveedores AS catprov", "cotDesk.coti_proveedor", "=", "catprov.id")
              ->join("sos_personas AS prv", "catprov.proveedor", "=", "prv.id")
              ->join("teci_catalogo_monedas AS mon", "cotDesk.coti_moneda", "=", "mon.id")
              ->join("teci_unidad_medida AS umed", "cotDesk.coti_unidad_medida", "=", "umed.id")
              ->join("teci_forma_pago AS fpay", "cotDesk.coti_forma_pago", "=", "fpay.id")
              //->join("teci_metodo_pago AS mpay","cotDesk.coti_metodo_pago","=","mpay.id")
              ->join("eegr_compras_requisicion_detalle AS reqDet", "cotDet.detalle_requisicion", "=", "reqDet.id")
              ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
              ->where(["cotDet.token_detalle_cotizacion" => $tkn_detcot, "cotMain.token_cotizacion" => $resCot->token_cotizacion])->get();

            foreach ($cotDetalleDesk as $vDeskCot) {
              $proveedor_tkn = $vDeskCot->token_cat_proveedores;
              $proveedor_name = $JwtAuth->desencriptar($vDeskCot->nombre_extendido);
              $proveedor_rfc_generico = $vDeskCot->rfc_generico;
              $proveedor_rfc = $vDeskCot->rfc != NULL ? $JwtAuth->desencriptar($vDeskCot->rfc) : "---";
              $proveedor_taxId = $vDeskCot->tax_id != NULL ? $JwtAuth->desencriptar($vDeskCot->tax_id) : "---";

              if ($vDeskCot->coti_entrega_tipo == "domi") {
                $coti_entrega_tipo_extend = "Domicilio";
              } else if ($vDeskCot->coti_entrega_tipo == "stre") {
                $coti_entrega_tipo_extend = "Tienda";
              } else if ($vDeskCot->coti_entrega_tipo == "ofna") {
                $coti_entrega_tipo_extend = "Oficina";
              } else if ($vDeskCot->coti_entrega_tipo == "dest") {
                $coti_entrega_tipo_extend = "Destino";
              } else if ($vDeskCot->coti_entrega_tipo == "cntr") {
                $coti_entrega_tipo_extend = "Contra reembolso";
              }

              $coti_credito_otorga = $vDeskCot->coti_credito_otorga == TRUE ? true : false;
              $coti_credito_time = $vDeskCot->coti_credito_otorga == TRUE ? $JwtAuth->desencriptar($vDeskCot->coti_credito_time) : null;
              //$coti_precio = "$".number_format($vDeskCot->coti_precio,$vDeskCot->decimales,'.',',')." ".$vDeskCot->codigo." ".$vDeskCot->moneda;
              $coti_precio = "$" . number_format($vDeskCot->coti_precio, $vDeskCot->decimales, '.', ',') . " " . $vDeskCot->codigo;

              if ($main_moneda_token == $vDeskCot->token_monedas) {
                //$coti_conversion = "$".number_format($vDeskCot->coti_precio,$vDeskCot->decimales,'.',',')." ".$vDeskCot->codigo." ".$vDeskCot->moneda;
                $coti_conversion = "$" . number_format($vDeskCot->coti_precio, $vDeskCot->decimales, '.', ',') . " " . $vDeskCot->codigo;
              } else {
                $convet = $vDeskCot->coti_precio * $vDeskCot->coti_tipo_cambio;
                //$coti_conversion = "$".number_format($convet,$main_moneda_decimales,'.',',')." ".$main_moneda_codigo." ".$main_moneda_name;
                $coti_conversion = "$" . number_format($convet, $main_moneda_decimales, '.', ',') . " " . $main_moneda_codigo;
              }

              $coti_desc_autorizacion = $vDeskCot->coti_desc_autorizacion == TRUE ? true : false;
              $coti_desc_fecha_autorizacion = $vDeskCot->coti_desc_autorizacion == TRUE ? gmdate('Y-m-d H:i:s', $vDeskCot->coti_desc_fecha_autorizacion) : null;

              $coti_desc_pers_autoriza = "";
              if ($vDeskCot->coti_desc_autorizacion == TRUE) {
                $persAuthCoti = DB::table("sos_personas AS people")
                  ->join("vhum_empleados_catalogo AS persAuth", "people.id", "=", "persAuth.empleado_name")
                  ->join("eegr_compras_cotizacion_detalle_descripcion AS cotDesk", "persAuth.id", "=", "cotDesk.coti_desc_pers_autoriza")
                  ->where(["cotDesk.token_desc_detalle_cotiza" => $vDeskCot->token_desc_detalle_cotiza])->get();
                $coti_desc_pers_autoriza = $JwtAuth->desencriptarNombres($persAuthCoti[0]->paterno, $persAuthCoti[0]->materno, $persAuthCoti[0]->nombre);
              }

              $valoracion_stars = "";
              $valoracion_estrellas = array();
              $valoracion_posicion = "";
              $valoracion_comentarios = "";
              $queryCotimOP = DB::table("eegr_compras_cotizacion_detalle_mejor_opcion AS mOP")
                ->join("eegr_compras_cotizacion AS cotMain", "mOP.cotizacion", "=", "cotMain.id")
                ->join("eegr_compras_cotizacion_detalle AS cotDesk", "mOP.detalle_cotizacion", "=", "cotDesk.id")
                ->join("eegr_catalogo_proveedores AS catprov", "mOP.proveedor", "=", "catprov.id")
                ->where([
                  "cotMain.token_cotizacion" => $resCot->token_cotizacion,
                  "cotDesk.token_detalle_cotizacion" => $cDet->token_detalle_cotizacion,
                  "catprov.token_cat_proveedores" => $vDeskCot->token_cat_proveedores
                ])->get();

              if (count($queryCotimOP) == 1) {
                foreach ($queryCotimOP as $vMop) {
                  $valoracion_stars = $vMop->posicion == 1 ? 3 : ($vMop->posicion == 2 ? 2 : ($vMop->posicion == 3 ? 1 : 0));
                  $valoracion_posicion = $vMop->posicion;
                  $valoracion_comentarios = $JwtAuth->desencriptar($vMop->observaciones);
                }
              }

              for ($s = 0; $s < $valoracion_stars; $s++) {
                $row_star = array("xclase" => "fa-solid fa-star");
                $valoracion_estrellas[] = $row_star;
              }

              $rowCotList = array(
                "coti_num_lista" => $coti_num_lista,
                "token_desc_detalle_cotiza" => $vDeskCot->token_desc_detalle_cotiza,
                "coti_proveedor_tkn" => $proveedor_tkn,
                "coti_proveedor_name" => $proveedor_name,
                "coti_proveedor_rfc_generico" => $proveedor_rfc_generico,
                "coti_proveedor_rfc" => $proveedor_rfc,
                "coti_proveedor_taxId" => $proveedor_taxId,
                "valoracion_stars" => $valoracion_stars,
                "valoracion_estrellas" => $valoracion_estrellas,
                "valoracion_posicion" => $valoracion_posicion,
                "valoracion_comentarios" => $valoracion_comentarios,

                "coti_especificaciones" => $JwtAuth->desencriptar($vDeskCot->coti_especificaciones),
                "coti_cantidad" => $vDeskCot->coti_cantidad,
                //token_monedas
                "coti_moneda_token" => $vDeskCot->token_monedas,
                "coti_moneda_codigo" => $vDeskCot->codigo,
                "coti_moneda_name" => $vDeskCot->moneda,
                "coti_moneda_decimales" => $vDeskCot->decimales,
                "coti_precio" => $coti_precio,
                "coti_tipo_cambio" => $vDeskCot->coti_tipo_cambio,
                "coti_conversion" => $coti_conversion,

                "coti_calidad" => $JwtAuth->desencriptar($vDeskCot->coti_calidad),
                "coti_servicio" => $JwtAuth->desencriptar($vDeskCot->coti_servicio),
                "coti_entrega_tipo" => $vDeskCot->coti_entrega_tipo,
                "coti_entrega_tipo_extend" => $coti_entrega_tipo_extend,
                "coti_entrega_tiempo" => $JwtAuth->desencriptar($vDeskCot->coti_entrega_tiempo),
                "coti_descuento" => $JwtAuth->desencriptar($vDeskCot->coti_descuento),
                "coti_credito_otorga" => $coti_credito_otorga,
                "coti_credito_time" => $coti_credito_time,
                "coti_garantia" => $JwtAuth->desencriptar($vDeskCot->coti_garantia),
                //coti_unidad_medida
                "coti_umed_token" => $vDeskCot->token_unidad_medida,
                "coti_umed_name" => $vDeskCot->unidad_medida,
                "coti_umed_sat_clave" => $vDeskCot->sat_clave,
                "coti_umed_representa" => $vDeskCot->representa,
                //coti_forma_pago
                "coti_fpay_token" => $vDeskCot->token_formapago,
                "coti_fpay_clave" => $vDeskCot->clave,
                "coti_fpay_forma" => $vDeskCot->forma,
                //coti_metodo_pago
                //"coti_mpay_token" => $vDeskCot->token_metodopago,
                //"coti_mpay_abrev" => $vDeskCot->abrev,
                //"coti_mpay_metodo" => $vDeskCot->metodo,    
                "coti_valoracion" => $JwtAuth->desencriptar($vDeskCot->coti_valoracion),
                "coti_desc_open" => false,
                "coti_desc_autorizacion" => $coti_desc_autorizacion,
                "coti_desc_fecha_autorizacion" => $coti_desc_fecha_autorizacion,
                "coti_desc_pers_autoriza" => $coti_desc_pers_autoriza,
                "checkedFactura" => false,
                "receptFactura" => false,
                "imagenEvidenciaXMl" => null,
                "imagenNameEvidenciaXMl" => null,
                "imagenEvidenciaPdf" => null,
                "imagenNameEvidenciaPdf" => "",
                "resultXml" => "",
                "validXmlversion" => "",
                "validXmlserie" => "",
                "validXmlFolio" => "",
                "validXmlFecha" => "",
                "validXmlSello" => "",
                "validXmlformaPago" => "",
                "validXmlnoCertificado" => "",
                "validXmlcertificado" => "",
                "validXmlSubTotal" => "",
                "validXmlMoneda" => "",
                "validXmltipoCambio" => "",
                "validXmlTotal" => "",
                "validXmlconfirmacion" => "",
                "validXmlTipoDeComprobante" => "",
                "validXmlMetodoPago" => "",
                "validXmlLugarExpedicion" => "",
                "validXmltipoRelacion" => "",
                "validXmluuid" => "",
                "validXmlemisorRfc" => "",
                "validXmlemisorNombre" => "",
                "validXmlemisorRegimenFiscal" => "",
                "validXmlreceptorRfc" => "",
                "validXmlreceptorUsoCFDI" => "",
                "validXmlconceptos" => [],
                "selectvalidatexmlArticulos" => false,
                "totalimpuestosretenidos" => "",
                "totalimpuestostrasladados" => "",
                "validXmlimpuestosretenidosArray" => [],
                "validXmlimpuestostrasladadosArray" => [],
                "validXmlcompluuidComplemento" => "",
                "validXmlcomplfechaTimbrado" => "",
                "validXmlcomplRfcProvCertif" => "",
                "validXmlcomplSelloCFD" => "",
                "validXmlcomplNoCertificadoSAT" => "",
                "validXmlcomplSelloSAT" => "",
                "arrayErroresComprobante" => [],
                "arrayErroresEmisor" => [],
                "arrayErroresReceptor" => [],
                "arrayErroresCfdiRelacionados" => [],
                "arrayErroresConceptos" => [],
                "arrayErroresImpuestos" => [],
                "arrayErroresComplemento" => [],

              );
              $detalleDeskList[] = $rowCotList;
              ++$coti_num_lista;
            }

            $rowDet = array(
              "token_detalle_cotizacion" => $tkn_detcot,
              "num_lista" => $num_lista,
              "token_detalle_requisicion" => $cDet->token_detalle_requisicion,
              "requi_tipo_back" => $cDet->tipo_necesidad,
              "requi_tipo_front" => $det_requi_tipo,
              "requi_necesidad" => $JwtAuth->desencriptar($cDet->necesidad),
              "requi_caracteristicas_view" => false,
              "requi_caracteristicas_list" => $list_caract_array,
              "requi_caracteristicas_other" => $txt_other_caract,
              "requi_cantidad" => $cDet->cantidad,
              "requi_unidad_medida_token" => $cDet->token_unidad_medida,
              "requi_unidad_medida_name" => $det_requi_unidad_medida,
              "requi_marca" => $det_requi_marca,
              "bool_requi_autorizacion" => $des_bool_requisicion_autorizacion,
              "requi_autorizacion" => $des_requisicion_autorizacion,
              "requi_persona_autoriza" => $des_persona_autoriza,
              "archivosPartida" => $archivosPartidaArray,
              "open_desglose" => false,
              "cotizaciones" => $detalleDeskList,
            );
            $detalle_content[] = $rowDet;
            ++$num_lista;
          }

          $dataMensaje = array(
            "status" => "success",
            "code" => 200,
            "token_cotizacion" => $resCot->token_cotizacion,
            "fecha_cotizacion" => gmdate('Y-m-d H:i:s', $resCot->coti_fecha_sistema),
            "folio_cotizacion" => "COT-" . $JwtAuth->generarFolio($resCot->coti_folio),
            "requisicion" => $proyeto_requisicion,
            "persona_cotiza" => $persona_cotiza,
            "comentarios_finales" => $resCot->comentarios_finales != NULL ? $JwtAuth->desencriptar($resCot->comentarios_finales) : "",
            "url_pdf" => "https://downloads.sos-mexico.com.mx/cotizacion_pdf/" . $JwtAuth->generarFolio($resCot->coti_folio),
            "abierto" => false,
            "contenido" => $detalle_content,
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

  public function cotizacionAutorizar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCotizaciones = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cotizacion" => "required|string"
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
        $token_cotizacion = $parametrosArray["token_cotizacion"];

        if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
          $listaCot = DB::table("eegr_compras_cotizacion AS cotList")
            ->join("main_empresas AS emp", "cotList.empresa", "emp.id")
            ->where(["cotList.token_cotizacion" => $token_cotizacion, "emp.empresa_token" => $usuario->empresa_token])->get();
        } else {
          $listaCot = DB::table("eegr_compras_cotizacion AS cotList")
            ->join("main_empresas AS emp", "eegr_compras_cotizacion.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "cotList.usuario_cotizador", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
            ->where(["cotList.token_cotizacion" => $token_cotizacion, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        }

        foreach ($listaCot as $resCot) {
          //da_te_default_timezone_set('America/Mexico_City');
          $main_moneda_token = $resCot->token_monedas;
          $main_moneda_codigo = $resCot->codigo;
          $main_moneda_name = $resCot->e_moneda_code;
          $main_moneda_decimales = $resCot->e_moneda_decimales;

          $tkn_requisicion = "";
          $proyeto_requisicion = "";
          $requisicion_folio = "";
          if ($resCot->requisicion != NULL) {
            $select_reki = DB::select("SELECT token_requisicion,folio,proyecto,fecha FROM eegr_compras_requisicion WHERE id = ?", [$resCot->requisicion]);
            $tkn_requisicion = $select_reki[0]->token_requisicion;
            $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($select_reki[0]->folio);
            $proyeto_requisicion = "REQ-" . $JwtAuth->generarFolio($select_reki[0]->folio) . " - " . $JwtAuth->desencriptar($select_reki[0]->proyecto) . " (" . gmdate('Y-m-d H:i:s', $select_reki[0]->fecha) . ")";
          }

          $persona_cotiza = "";
          $cotUser = DB::table("eegr_compras_cotizacion AS cotList")
            ->join("vhum_empleados_catalogo AS pers", "cotList.usuario_cotizador", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["cotList.token_cotizacion" => $resCot->token_cotizacion])->get();

          foreach ($cotUser as $cUser) {
            $persona_cotiza = $JwtAuth->desencriptarNombres($cUser->paterno, $cUser->materno, $cUser->nombre);
          }

          $detalle_content = array();
          $num_lista = 1;
          $cotDetalle = DB::table("eegr_compras_cotizacion_detalle AS cotDet")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "cotDet.detalle_requisicion", "=", "reqDet.id")
            ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
            ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
            ->where(["reqDet.status_req" => TRUE, "cotMain.token_cotizacion" => $resCot->token_cotizacion])->get();

          foreach ($cotDetalle as $cDet) {
            $tkn_detcot = $cDet->token_detalle_cotizacion;

            if ($cDet->tipo_necesidad == "Merc") {
              $det_requi_tipo = "Mercancia";
            }
            if ($cDet->tipo_necesidad == "Gast") {
              $det_requi_tipo = "Gastos";
            }
            if ($cDet->tipo_necesidad == "Acti") {
              $det_requi_tipo = "Activos";
            }
            if ($cDet->tipo_necesidad == "Mixt") {
              $det_requi_tipo = "Mixto";
            }

            $list_caract_array = array();
            $list_num_caract = 1;
            $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
              ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
              ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
              ->where(["reqMain.token_requisicion" => $tkn_requisicion, "reqDet.token_detalle_requisicion" => $cDet->token_detalle_requisicion])->get();
            //echo count($selectDetReqCaractList);
            foreach ($selectDetReqCaractList as $vCaract) {
              $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
              $db_valor = $JwtAuth->desencriptar($vCaract->valor);
              $fisrt_valor = $descif_clave == "Precio" ? "$" . number_format($db_valor, 2, '.', ',') : $db_valor;
              $descif_valor = $list_num_caract > count($selectDetReqCaractList) ? $fisrt_valor . "," : $fisrt_valor;
              $row_CaractList = array(
                "token_caract" => $vCaract->token_caract,
                "num_list" => $list_num_caract,
                "clave" => $descif_clave,
                "valorFront" => $descif_valor,
                "valorBack" => $db_valor
              );
              $list_caract_array[] = $row_CaractList;
              ++$list_num_caract;
            }
            $txt_other_caract = $cDet->caracteristicas_extend != NULL ? $JwtAuth->desencriptar($cDet->caracteristicas_extend) : null;
            $det_requi_unidad_medida = $cDet->unidad_medida . " - " . $cDet->sat_clave . ", representa " . $cDet->representa;
            $det_requi_marca = $cDet->marca != NULL ? $JwtAuth->desencriptar($cDet->marca) : "no hay marca referida";

            $des_persona_autoriza = "---";
            if ($cDet->des_autorizacion == TRUE && $cDet->des_autoriza_user != NULL) {
              $queryAutoriza = DB::table("vhum_empleados_catalogo AS pers")
                ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
                ->where(["pers.id" => $cDet->des_autoriza_user])->get();

              foreach ($queryAutoriza as $rAutoriza) {
                $des_persona_autoriza = $JwtAuth->desencriptarNombres($rAutoriza->paterno, $rAutoriza->materno, $rAutoriza->nombre);
              }
            }

            if ($cDet->des_autorizacion == TRUE) {
              $des_bool_requisicion_autorizacion = true;
              $des_requisicion_autorizacion = "Requisición autorizada por " . $des_persona_autoriza . " (" . gmdate('Y-m-d H:i:s', $cDet->des_fecha_autorizacion) . ")";
            } else {
              $des_bool_requisicion_autorizacion = false;
              $des_requisicion_autorizacion = "Requisición no autorizada";
            }

            $archivosPartidaArray = array();
            $selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento 
                            FROM sos_documentos AS evd JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet 
                            WHERE evd.requisicion = reqMain.id AND reqMain.token_requisicion = ?
                            AND evd.detalle_requisicion = reqDet.id AND reqDet.token_detalle_requisicion = ?
                            AND evd.status_documento = TRUE", [$tkn_requisicion, $cDet->token_detalle_requisicion]);

            if (count($selectIdEvid) > 0) {
              $filepath = $resCot->root_tkn . "/0002-cpp/compras/requisiciones/" . $requisicion_folio;
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

            $detalleDeskList = array();
            $coti_num_lista = 1;
            $cotDetalleDesk = DB::table("eegr_compras_cotizacion_detalle_descripcion AS cotDesk")
              ->join("eegr_compras_cotizacion_detalle AS cotDet", "cotDesk.detalle_cotizacion", "=", "cotDet.id")
              ->join("eegr_catalogo_proveedores AS catprov", "cotDesk.coti_proveedor", "=", "catprov.id")
              ->join("sos_personas AS prv", "catprov.proveedor", "=", "prv.id")
              ->join("teci_catalogo_monedas AS mon", "cotDesk.coti_moneda", "=", "mon.id")
              ->join("teci_unidad_medida AS umed", "cotDesk.coti_unidad_medida", "=", "umed.id")
              ->join("teci_forma_pago AS fpay", "cotDesk.coti_forma_pago", "=", "fpay.id")
              ->join("teci_metodo_pago AS mpay", "cotDesk.coti_metodo_pago", "=", "mpay.id")
              ->join("eegr_compras_requisicion_detalle AS reqDet", "cotDet.detalle_requisicion", "=", "reqDet.id")
              ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
              ->where(["cotDet.token_detalle_cotizacion" => $tkn_detcot, "cotMain.token_cotizacion" => $resCot->token_cotizacion])->get();

            foreach ($cotDetalleDesk as $vDeskCot) {
              $proveedor_tkn = $vDeskCot->token_cat_proveedores;
              $proveedor_name = $JwtAuth->desencriptar($vDeskCot->nombre_extendido);
              $proveedor_rfc_generico = $vDeskCot->rfc_generico;
              $proveedor_rfc = $vDeskCot->rfc != NULL ? $JwtAuth->desencriptar($vDeskCot->rfc) : $vDeskCot->rfc_generico;
              $proveedor_taxId = $vDeskCot->tax_id != NULL ? $JwtAuth->desencriptar($vDeskCot->tax_id) : $vDeskCot->rfc_generico;

              if ($vDeskCot->coti_entrega_tipo == "domi") {
                $coti_entrega_tipo_extend = "Domicilio";
              } else if ($vDeskCot->coti_entrega_tipo == "stre") {
                $coti_entrega_tipo_extend = "Tienda";
              } else if ($vDeskCot->coti_entrega_tipo == "ofna") {
                $coti_entrega_tipo_extend = "Oficina";
              } else if ($vDeskCot->coti_entrega_tipo == "dest") {
                $coti_entrega_tipo_extend = "Destino";
              } else if ($vDeskCot->coti_entrega_tipo == "cntr") {
                $coti_entrega_tipo_extend = "Contra reembolso";
              }

              $coti_credito_otorga = $vDeskCot->coti_credito_otorga == TRUE ? true : false;
              $coti_credito_time = $vDeskCot->coti_credito_otorga == TRUE ? $JwtAuth->desencriptar($vDeskCot->coti_credito_time) : null;
              $coti_precio = "$" . number_format($vDeskCot->coti_precio, $vDeskCot->decimales, '.', ',') . " " . $vDeskCot->codigo . " " . $vDeskCot->moneda;

              if ($main_moneda_token == $vDeskCot->token_monedas) {
                $coti_conversion = "$" . number_format($vDeskCot->coti_precio, $vDeskCot->decimales, '.', ',') . " " . $vDeskCot->codigo . " " . $vDeskCot->moneda;
              } else {
                $convet = $vDeskCot->coti_precio * $vDeskCot->coti_tipo_cambio;
                $coti_conversion = "$" . number_format($convet, $main_moneda_decimales, '.', ',') . " " . $main_moneda_codigo . " " . $main_moneda_name;
              }

              $coti_desc_autorizacion = $vDeskCot->coti_desc_autorizacion == TRUE ? true : false;
              $coti_desc_fecha_autorizacion = $vDeskCot->coti_desc_autorizacion == TRUE ? gmdate('Y-m-d H:i:s', $vDeskCot->coti_desc_fecha_autorizacion) : null;

              $coti_desc_pers_autoriza = "";
              if ($vDeskCot->coti_desc_autorizacion == TRUE) {
                $persAuthCoti = DB::table("sos_personas AS people")
                  ->join("vhum_empleados_catalogo AS persAuth", "people.id", "=", "persAuth.empleado_name")
                  ->join("eegr_compras_cotizacion_detalle_descripcion AS cotDesk", "persAuth.id", "=", "cotDesk.coti_desc_pers_autoriza")
                  ->where(["cotDesk.token_desc_detalle_cotiza" => $vDeskCot->token_desc_detalle_cotiza])->get();
                $coti_desc_pers_autoriza = $JwtAuth->desencriptarNombres(end($persAuthCoti)->paterno, end($persAuthCoti)->materno, end($persAuthCoti)->nombre);
              }

              $rowCotList = array(
                "coti_num_lista" => $coti_num_lista,
                "token_desc_detalle_cotiza" => $vDeskCot->token_desc_detalle_cotiza,
                "coti_proveedor_tkn" => $proveedor_tkn,
                "coti_proveedor_name" => $proveedor_name,
                "coti_proveedor_rfc_generico" => $proveedor_rfc_generico,
                "coti_proveedor_rfc" => $proveedor_rfc,
                "coti_proveedor_taxId" => $proveedor_taxId,

                "coti_especificaciones" => $JwtAuth->desencriptar($vDeskCot->coti_especificaciones),
                "coti_cantidad" => $vDeskCot->coti_cantidad,
                //token_monedas
                "coti_moneda_token" => $vDeskCot->token_monedas,
                "coti_moneda_codigo" => $vDeskCot->codigo,
                "coti_moneda_name" => $vDeskCot->moneda,
                "coti_moneda_decimales" => $vDeskCot->decimales,
                "coti_precio" => $coti_precio,
                "coti_tipo_cambio" => $vDeskCot->coti_tipo_cambio,
                "coti_conversion" => $coti_conversion,

                "coti_calidad" => $JwtAuth->desencriptar($vDeskCot->coti_calidad),
                "coti_servicio" => $JwtAuth->desencriptar($vDeskCot->coti_servicio),
                "coti_entrega_tipo" => $vDeskCot->coti_entrega_tipo,
                "coti_entrega_tipo_extend" => $coti_entrega_tipo_extend,
                "coti_entrega_tiempo" => $JwtAuth->desencriptar($vDeskCot->coti_entrega_tiempo),
                "coti_descuento" => $JwtAuth->desencriptar($vDeskCot->coti_descuento),
                "coti_credito_otorga" => $coti_credito_otorga,
                "coti_credito_time" => $coti_credito_time,
                "coti_garantia" => $JwtAuth->desencriptar($vDeskCot->coti_garantia),
                //coti_unidad_medida
                "coti_umed_token" => $vDeskCot->token_unidad_medida,
                "coti_umed_name" => $vDeskCot->unidad_medida,
                "coti_umed_sat_clave" => $vDeskCot->sat_clave,
                "coti_umed_representa" => $vDeskCot->representa,
                //coti_forma_pago
                "coti_fpay_token" => $vDeskCot->token_formapago,
                "coti_fpay_clave" => $vDeskCot->clave,
                "coti_fpay_forma" => $vDeskCot->forma,
                //coti_metodo_pago
                "coti_mpay_token" => $vDeskCot->token_metodopago,
                "coti_mpay_abrev" => $vDeskCot->abrev,
                "coti_mpay_metodo" => $vDeskCot->metodo,
                "coti_valoracion" => $JwtAuth->desencriptar($vDeskCot->coti_valoracion),
                "coti_desc_open" => false,
                "coti_desc_autorizacion" => $coti_desc_autorizacion,
                "coti_desc_fecha_autorizacion" => $coti_desc_fecha_autorizacion,
                "coti_desc_pers_autoriza" => $coti_desc_pers_autoriza,
                "checkedFactura" => false,
                "receptFactura" => false,
                "imagenEvidenciaXMl" => null,
                "imagenNameEvidenciaXMl" => null,
                "imagenEvidenciaPdf" => null,
                "imagenNameEvidenciaPdf" => "",
                "resultXml" => "",
                "validXmlversion" => "",
                "validXmlserie" => "",
                "validXmlFolio" => "",
                "validXmlFecha" => "",
                "validXmlSello" => "",
                "validXmlformaPago" => "",
                "validXmlnoCertificado" => "",
                "validXmlcertificado" => "",
                "validXmlSubTotal" => "",
                "validXmlMoneda" => "",
                "validXmltipoCambio" => "",
                "validXmlTotal" => "",
                "validXmlconfirmacion" => "",
                "validXmlTipoDeComprobante" => "",
                "validXmlMetodoPago" => "",
                "validXmlLugarExpedicion" => "",
                "validXmltipoRelacion" => "",
                "validXmluuid" => "",
                "validXmlemisorRfc" => "",
                "validXmlemisorNombre" => "",
                "validXmlemisorRegimenFiscal" => "",
                "validXmlreceptorRfc" => "",
                "validXmlreceptorUsoCFDI" => "",
                "validXmlconceptos" => [],
                "selectvalidatexmlArticulos" => false,
                "totalimpuestosretenidos" => "",
                "totalimpuestostrasladados" => "",
                "validXmlimpuestosretenidosArray" => [],
                "validXmlimpuestostrasladadosArray" => [],
                "validXmlcompluuidComplemento" => "",
                "validXmlcomplfechaTimbrado" => "",
                "validXmlcomplRfcProvCertif" => "",
                "validXmlcomplSelloCFD" => "",
                "validXmlcomplNoCertificadoSAT" => "",
                "validXmlcomplSelloSAT" => "",
                "arrayErroresComprobante" => [],
                "arrayErroresEmisor" => [],
                "arrayErroresReceptor" => [],
                "arrayErroresCfdiRelacionados" => [],
                "arrayErroresConceptos" => [],
                "arrayErroresImpuestos" => [],
                "arrayErroresComplemento" => [],

              );
              $detalleDeskList[] = $rowCotList;
              ++$coti_num_lista;
            }

            $rowDet = array(
              "token_detalle_cotizacion" => $tkn_detcot,
              "num_lista" => $num_lista,
              "token_detalle_requisicion" => $cDet->token_detalle_requisicion,
              "requi_tipo_back" => $cDet->tipo_necesidad,
              "requi_tipo_front" => $det_requi_tipo,
              "requi_necesidad" => $JwtAuth->desencriptar($cDet->necesidad),
              "requi_caracteristicas_view" => false,
              "requi_caracteristicas_list" => $list_caract_array,
              "requi_caracteristicas_other" => $txt_other_caract,
              "requi_cantidad" => $cDet->cantidad,
              "requi_unidad_medida_token" => $cDet->token_unidad_medida,
              "requi_unidad_medida_name" => $det_requi_unidad_medida,
              "requi_marca" => $det_requi_marca,
              "bool_requi_autorizacion" => $des_bool_requisicion_autorizacion,
              "requi_autorizacion" => $des_requisicion_autorizacion,
              "requi_persona_autoriza" => $des_persona_autoriza,
              "archivosPartida" => $archivosPartidaArray,
              "open_desglose" => false,
              "cotizaciones" => $detalleDeskList,
            );
            $detalle_content[] = $rowDet;
            ++$num_lista;
          }

          $dataMensaje = array(
            "status" => "success",
            "code" => 200,
            "token_cotizacion" => $resCot->token_cotizacion,
            "fecha_cotizacion" => gmdate('Y-m-d H:i:s', $resCot->coti_fecha_sistema),
            "folio_cotizacion" => "COT-" . $JwtAuth->generarFolio($resCot->coti_folio),
            "requisicion" => $proyeto_requisicion,
            "persona_cotiza" => $persona_cotiza,
            "url_pdf" => "https://downloads.sos-mexico.com.mx/cotizacion_pdf/" . $JwtAuth->generarFolio($resCot->coti_folio),
            "abierto" => false,
            "contenido" => $detalle_content,
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

  public function totalCotizacionesPendientes(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $folioMax = CotizacionesModelo::join("main_empresas AS emp", "eegr_compras_cotizacion.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])
      ->where("eegr_compras_cotizacion.status", "!=", "2")->count();
    return response()->json(["status" => "success", "codigo" => 200, "totalCot" => $folioMax]);
  }

  public function catalogoCotizacionesReq(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $arrayCotizaciones = array();

    $listaCot = CotizacionesModelo::join("requisicioncompra AS resqcom", "eegr_compras_cotizacion.requision", "resqcom.id")
      ->join("main_empresas AS emp", "cotizacion.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();

    foreach ($listaCot as $resCot) {
      //da_te_default_timezone_set('America/Mexico_City');
      $cotizaciones = array(
        "token_cotizacion" => $resCot->token_cotizacion,
        "fecha" => "1621316909",
        "folio" => $JwtAuth->generar($resCot->folio),
        //"referencia" => ,
        "requisicion" => $resCot->proyecto
      );
    }

    return response()->json([
      'datosCotizacion' => $listaCot,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function detalleReqLastCotizacion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $cotizacionesListaArray = array();

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

        $selectcotizacionesDone = DB::table("eegr_compras_cotizacion_detalle_descripcion AS descDetCot")
          ->join("eegr_catalogo_proveedores AS catprov", "descDetCot.coti_proveedor", "=", "catprov.id")
          ->join("sos_personas AS prov", "catprov.proveedor", "=", "prov.id")
          ->join("teci_catalogo_monedas AS money", "descDetCot.coti_moneda", "=", "money.id")
          ->join("eegr_compras_cotizacion_detalle AS dettCot", "descDetCot.detalle_cotizacion", "=", "dettCot.id")
          ->join("eegr_compras_cotizacion AS cotMain", "dettCot.cotizacion", "=", "cotMain.id")
          ->join("eegr_compras_requisicion_detalle AS reqDet", "dettCot.detalle_requisicion", "=", "reqDet.id")
          ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
          ->where(["reqDet.token_detalle_requisicion" => $token_detalle_requisicion_, "reqMain.token_requisicion" => $requisicion_token_])
          ->orderBy('cotMain.coti_folio', 'DESC')->limit(1)->get();

        foreach ($selectcotizacionesDone as $vCott) {
          $nombreProv = $JwtAuth->desencriptar($vCott->nombre_extendido);

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
          );
          $cotizacionesListaArray[] = $rowcot;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "cotizaciones" => $cotizacionesListaArray,
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

  public function catalogoCotizacionDirecta(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $arrayCotizaciones = array();

    $selectcotizacionesDone = DB::table("eegr_compras_cotizacion_detalle_descripcion AS descDetCot")
      ->join("eegr_catalogo_proveedores AS catprov", "descDetCot.coti_proveedor", "=", "catprov.id")
      ->join("sos_personas AS prov", "catprov.proveedor", "=", "prov.id")
      ->join("teci_catalogo_monedas AS money", "descDetCot.coti_moneda", "=", "money.id")
      ->join("eegr_compras_cotizacion_detalle AS dettCot", "descDetCot.detalle_cotizacion", "=", "dettCot.id")
      ->join("eegr_compras_cotizacion AS cotMain", "dettCot.cotizacion", "=", "cotMain.id")
      ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "cotMain.requisicion" => NULL,
        "emp.empresa_token" => $usuario->empresa_token,
        "users.usuario_token" => $usuario->user_token,
      ])->orderBy('cotMain.coti_folio', 'DESC')->get();

    foreach ($selectcotizacionesDone as $vCott) {
      //da_te_default_timezone_set('America/Mexico_City');
      $nombreProv = $JwtAuth->desencriptar($vCott->nombre_extendido);
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

      $referencia = "";
      if ($vCott->referencia != NULL) $referencia = $vCott->referencia;

      $row = array(
        "token_cotizacion" => $vCott->token_cotizacion,
        "coti_fecha_sistema" => gmdate('Y-m-d H:i:s', $vCott->coti_fecha_sistema),
        "coti_folio" => "COT-" . $JwtAuth->generarFolio($vCott->coti_folio),
        //"referencia" => ,
        "referencia" => $referencia,
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
      $arrayCotizaciones[] = $row;
    }

    return response()->json([
      'cotizaciones' => $arrayCotizaciones,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function autorizarAllCotizacion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_solicitud_cotizacion" => "required|string",
        "listado" => "required|array",
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
        $token_solicitud_cotizacion = $parametrosArray["token_solicitud_cotizacion"];
        $listado_total = count($parametrosArray["listado"]);
        $listado = $parametrosArray["listado"];

        if (isset($token_solicitud_cotizacion) && !empty($token_solicitud_cotizacion) && isset($listado) && !empty($listado)) {
          if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
            $querySoli = DB::select("SELECT soliCot.*,reqMain.token_requisicion,reqMain.folio AS folioReq,reqMain.proyecto,reqMain.fecha AS dateReq 
                        FROM eegr_compras_cotizacion_solicitud AS soliCot 
                        JOIN eegr_compras_requisicion AS reqMain WHERE soliCot.id IN (
                        SELECT cotList.solicitud_cotizacion FROM eegr_compras_cotizacion AS cotList JOIN main_empresas AS emp
                        WHERE cotList.empresa = emp.id AND emp.empresa_token = ?) AND soliCot.token_solicitud_cotizacion = ?", [$usuario->empresa_token, $token_solicitud_cotizacion]);
          } else {
            $querySoli = DB::select("SELECT soliCot.*,reqMain.token_requisicion,reqMain.folio AS folioReq,reqMain.proyecto,reqMain.fecha AS dateReq 
                            FROM eegr_compras_cotizacion_solicitud AS soliCot 
                            JOIN eegr_compras_requisicion AS reqMain WHERE soliCot.id IN (
                                SELECT cotList.solicitud_cotizacion FROM eegr_compras_cotizacion AS cotList 
                                JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                                WHERE cotList.empresa = emp.id AND emp.empresa_token = ? AND cotList.usuario_cotizador = pers.id
                                AND pers.id = users.empleado AND users.usuario_token = ?) AND soliCot.token_solicitud_cotizacion = ?", [$usuario->empresa_token, $usuario->user_token, $token_solicitud_cotizacion]);
          }

          if (count($querySoli) > 0) {
            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);

            foreach ($querySoli as $sCot) {
              //da_te_default_timezone_set('America/Mexico_City');
              $contador = 0;
              for ($i = 0; $i < $listado_total; $i++) {
                $select_to_auth = $listado[$i]["select_to_auth"];
                $tkn_cotizacion = $listado[$i]["token_cotizacion"];
                $tkn_cotizacion_detalle = $listado[$i]["token_detalle_cotizacion"];
                $tkn_cotizacion_descdet = $listado[$i]["token_desc_detalle_cotiza"];

                $queryCotizacionMain = DB::table("eegr_compras_cotizacion_detalle_descripcion AS cotDesk")
                  ->join("eegr_compras_cotizacion_detalle AS cotDet", "cotDesk.detalle_cotizacion", "=", "cotDet.id")
                  ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
                  ->join("eegr_compras_cotizacion_solicitud AS soliCot", "cotMain.solicitud_cotizacion", "=", "soliCot.id")
                  ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
                  ->where([
                    "cotDesk.token_desc_detalle_cotiza" => $tkn_cotizacion_descdet,
                    "cotDesk.coti_desc_autorizacion" => FALSE,
                    "cotDet.token_detalle_cotizacion" => $tkn_cotizacion_detalle,
                    "cotMain.token_cotizacion" => $tkn_cotizacion,
                    "soliCot.token_solicitud_cotizacion" => $sCot->token_solicitud_cotizacion,
                    "emp.empresa_token" => $usuario->empresa_token
                  ])->get();
                //echo count($queryCotizacionMain);
                if (count($queryCotizacionMain) == 1) {
                  foreach ($queryCotizacionMain as $vCot) {
                    $authcotizacion = DB::table("eegr_compras_cotizacion_detalle_descripcion AS descDetCot")
                      ->join("eegr_compras_cotizacion_detalle AS dettCot", "descDetCot.detalle_cotizacion", "=", "dettCot.id")
                      ->join("eegr_compras_cotizacion AS cotMain", "dettCot.cotizacion", "=", "cotMain.id")
                      ->where(["descDetCot.token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza, "dettCot.token_detalle_cotizacion" => $vCot->token_detalle_cotizacion, "cotMain.token_cotizacion" => $vCot->token_cotizacion])
                      ->limit(1)->update(array("descDetCot.coti_desc_autorizacion" => TRUE, "descDetCot.coti_desc_fecha_autorizacion" => time(), "descDetCot.coti_desc_pers_autoriza" => $selectEmp[0]->userr));
                    ++$contador;
                  }
                } else {
                  ++$contador;
                }
              }

              if ($contador == $listado_total) {
                $dataMensaje = array("status" => "success", "code" => 200, "message" => "Proceso terminado, $listado_total cotizaciones autorizadas");
              } else {
                $dataMensaje = array("status" => "error", "code" => 200, "message" => "Cotización no autorizada");
              }
            }
          } else {
            $dataMensaje = array("status" => "error", "code" => 404, "message" => "No hay solicitudes de cotización");
          }
        } else {
          if (!isset($token_solicitud_cotizacion) || empty($token_solicitud_cotizacion)) {
            $mensaje_error = "Error en solicitud de cotización";
          }
          if (!isset($listado) || empty($listado)) {
            $mensaje_error = "Error en listado de cotizaciones";
          }
          $dataMensaje = array("status" => "error", "code" => 404, "message" => $mensaje_error);
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

  public function autorizaCotizacion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cotizacion" => "required|string",
        "token_detalle_cotizacion" => "required|string",
        "token_desc_detalle_cotiza" => "required|string",
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
        $tkn_cotizacion = $parametrosArray["token_cotizacion"];
        $tkn_cotizacion_detalle = $parametrosArray["token_detalle_cotizacion"];
        $tkn_cotizacion_descdet = $parametrosArray["token_desc_detalle_cotiza"];

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);

        $queryCotizacionMain = DB::table("eegr_compras_cotizacion")->where(["token_cotizacion" => $tkn_cotizacion])->get();

        foreach ($queryCotizacionMain as $vCot) {
          $authcotizacion = DB::table("eegr_compras_cotizacion_detalle_descripcion AS descDetCot")
            ->join("eegr_compras_cotizacion_detalle AS dettCot", "descDetCot.detalle_cotizacion", "=", "dettCot.id")
            ->join("eegr_compras_cotizacion AS cotMain", "dettCot.cotizacion", "=", "cotMain.id")
            ->where(["descDetCot.token_desc_detalle_cotiza" => $tkn_cotizacion_descdet, "dettCot.token_detalle_cotizacion" => $tkn_cotizacion_detalle, "cotMain.token_cotizacion" => $vCot->token_cotizacion])
            ->limit(1)->update(array("descDetCot.coti_desc_autorizacion" => TRUE, "descDetCot.coti_desc_fecha_autorizacion" => time(), "descDetCot.coti_desc_pers_autoriza" => $selectEmp[0]->userr));

          if ($authcotizacion) {
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Cotización autorizada");
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Cotización no autorizada");
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

  public function desautorizaCotizacion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_requisicion" => "required|string",
        "token_detalle_requisicion" => "required|string",
        "token_cotizacion" => "required|string",
        "token_detalle_cotizacion" => "required|string",
        "token_desc_detalle_cotiza" => "required|string",
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
        $tkn_requisicion = $parametrosArray["token_requisicion"];
        $tkn_requisicion_detalle = $parametrosArray["token_detalle_requisicion"];
        $tkn_cotizacion = $parametrosArray["token_cotizacion"];
        $tkn_cotizacion_detalle = $parametrosArray["token_detalle_cotizacion"];
        $tkn_cotizacion_descdet = $parametrosArray["token_desc_detalle_cotiza"];

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);

        $queryRequisicionMain = DB::table("eegr_compras_requisicion AS reqMain")
          ->join("main_empresas AS emp", "reqMain.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(["reqMain.token_requisicion" => $tkn_requisicion, "reqMain.status" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();

        foreach ($queryRequisicionMain as $vReq) {
          $authcotizacion = DB::table("eegr_compras_cotizacion_detalle_descripcion AS descDetCot")
            ->join("eegr_compras_cotizacion_detalle AS dettCot", "descDetCot.detalle_cotizacion", "=", "dettCot.id")
            ->join("eegr_compras_cotizacion AS cotMain", "dettCot.cotizacion", "=", "cotMain.id")
            ->join("eegr_compras_requisicion_detalle AS reqDet", "dettCot.detalle_requisicion", "=", "reqDet.id")
            ->join("eegr_compras_requisicion AS reqMain", "reqDet.requisicion", "=", "reqMain.id")
            ->where([
              "descDetCot.token_desc_detalle_cotiza" => $tkn_cotizacion_descdet,
              "dettCot.token_detalle_cotizacion" => $tkn_cotizacion_detalle,
              "cotMain.token_cotizacion" => $tkn_cotizacion,
              "reqDet.token_detalle_requisicion" => $tkn_requisicion_detalle,
              "reqMain.token_requisicion" => $tkn_requisicion
            ])
            ->limit(1)->update(array("descDetCot.coti_desc_autorizacion" => FALSE, "descDetCot.coti_desc_fecha_autorizacion" => time(), "descDetCot.coti_desc_pers_autoriza" => $selectEmp[0]->userr));

          if ($authcotizacion) {
            $dataMensaje = array("status" => "success", "code" => 200, "message" => "Cotización desautorizada");
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Cotización no desautorizada");
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

  public function registrarCotizacionPReq(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_solicitud" => "required|string",
        "token_detalle_requisicion" => "required|string",
        "prov_cotizaciones" => "required|array",
        "adicionales" => "array",
        "proveedores_mejor_opcion" => "required|array",
        "comentarios_finales" => "required|string",
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
        $token_solicitud = $parametrosArray["token_solicitud"];
        $token_detalle_requisicion = $parametrosArray["token_detalle_requisicion"];
        $prov_cotizaciones = $parametrosArray["prov_cotizaciones"];
        $prov_coti_total = count($parametrosArray["prov_cotizaciones"]);
        $adicionales = $parametrosArray["adicionales"];
        $adicionales_total = count($parametrosArray["adicionales"]);
        $proveedores_mejor_opcion = $parametrosArray["proveedores_mejor_opcion"];
        $proveedores_mejor_opcion_total = count($parametrosArray["proveedores_mejor_opcion"]);
        $comentarios_finales = $parametrosArray["comentarios_finales"];

        //$selectProvident = DB::select("SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?",[$tkn_proveedor]); 
        //echo $selectProvident[0]->id; exit; 

        if (
          isset($token_solicitud) && !empty($token_solicitud) && isset($token_detalle_requisicion) &&
          !empty($token_detalle_requisicion) && isset($prov_cotizaciones) && !empty($prov_cotizaciones) &&
          $prov_coti_total > 0
        ) {

          $selectReqData = DB::table("eegr_compras_cotizacion_solicitud AS soliCot")
            ->join("eegr_compras_requisicion AS reqMain", "soliCot.requisicion", "=", "reqMain.id")
            ->join("eegr_compras_cotizacion_solicitud_requi AS rel", "soliCot.id", "=", "rel.cotizacion_solicitud")
            ->join("eegr_compras_requisicion_detalle AS reqInside", "rel.requisicion_detalle", "=", "reqInside.id")
            ->join("main_empresas AS emp", "soliCot.empresa", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              "soliCot.status_cotizacion_solicitud" => TRUE,
              "soliCot.token_solicitud_cotizacion" => $token_solicitud,
              "reqMain.autorizacion" => TRUE,
              "reqInside.token_detalle_requisicion" => $token_detalle_requisicion,
              "reqInside.des_autorizacion" => "A",
              "emp.empresa_token" => $usuario->empresa_token,
              "users.usuario_token" => $usuario->user_token
            ])->get();

          if (count($selectReqData) > 0) {
            foreach ($selectReqData as $vReq) {
              $token_requisicion = $vReq->token_requisicion;
              $soliCotIdent = DB::select("SELECT id FROM eegr_compras_cotizacion_solicitud WHERE token_solicitud_cotizacion = ?", [$vReq->token_solicitud_cotizacion]);

              $reqIdent = DB::select("SELECT mainreq.id AS idMain,detreq.id AS idDet FROM eegr_compras_requisicion_detalle AS detreq 
                                JOIN eegr_compras_requisicion AS mainreq WHERE detreq.token_detalle_requisicion = ? AND detreq.requisicion = mainreq.id 
                                AND mainreq.token_requisicion = ?", [$token_detalle_requisicion, $token_requisicion]);

              $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                                JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                                AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);
              //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
              $fecha_sistema = time();

              $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE fold.egr_cotizaciones = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? 
                                AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);

              if (count($folioSistema) == 1) {
                $folio_nuevo = $folioSistema[0]->folio;
              } else {
                $folio_nuevo = 1;
              }

              $token_cotizacion = $JwtAuth->encriptarToken($fecha_sistema . $folio_nuevo . $token_requisicion . $token_detalle_requisicion . count($prov_cotizaciones));
              $insertCotizacionMain = DB::table('eegr_compras_cotizacion')->insert(
                array(
                  "token_cotizacion" => $token_cotizacion,
                  "coti_fecha_sistema" => $fecha_sistema,
                  "coti_folio" => $folio_nuevo,
                  "referencia" => "CCMDOD",
                  "requisicion" => end($reqIdent)->idMain,
                  "solicitud_cotizacion" => end($soliCotIdent)->id,
                  "empresa" => end($selectEmp)->id,
                  "usuario_cotizador" => end($selectEmp)->userr,
                  "comentarios_finales" => $JwtAuth->encriptar($comentarios_finales),
                  "status" => TRUE,
                )
              );

              if ($insertCotizacionMain) {
                $selectCotizacionMain = DB::select("SELECT id FROM eegr_compras_cotizacion WHERE token_cotizacion = ?", [$token_cotizacion]);
                $token_detalle_cotizacion = $JwtAuth->encriptarToken(substr($token_cotizacion, 0, 10) . substr($token_detalle_requisicion, 0, 10) . $folio_nuevo . $token_requisicion);

                $insertCotizacionDetalle = DB::table('eegr_compras_cotizacion_detalle')->insert(
                  array(
                    "token_detalle_cotizacion" => $token_detalle_cotizacion,
                    "cotizacion" => end($selectCotizacionMain)->id,
                    "detalle_requisicion" => end($reqIdent)->idDet,
                    "status" => TRUE,
                  )
                );
                if ($insertCotizacionDetalle) {
                  $selectCotizacionDetalle = DB::select("SELECT detCot.id FROM eegr_compras_cotizacion_detalle AS detCot 
                                        JOIN eegr_compras_cotizacion AS mainCot WHERE detCot.token_detalle_cotizacion = ? AND detCot.cotizacion = mainCot.id
                                        AND mainCot.token_cotizacion = ?", [$token_detalle_cotizacion, $token_cotizacion]);

                  $count_insert_cProv = 0;
                  for ($i = 0; $i < $prov_coti_total; $i++) {
                    $row_proveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$prov_cotizaciones[$i]["token_cat_proveedores"])->value("id");

                    $row_especificaciones = $prov_cotizaciones[$i]["especificaciones"];
                    $row_cantidad = $prov_cotizaciones[$i]["cantidad"];
                    $row_precio = $prov_cotizaciones[$i]["precio"];

                    $row_moneda = $prov_cotizaciones[$i]["moneda"];
                    $row_tipo_cambio = $prov_cotizaciones[$i]["tipo_cambio"];
                    $row_conversion = $prov_cotizaciones[$i]["conversion"];
                    $row_calidad = $prov_cotizaciones[$i]["calidad"];
                    $row_servicio = $prov_cotizaciones[$i]["servicio"];
                    $row_entrega_tipo = $prov_cotizaciones[$i]["entrega_tipo"];
                    $row_entrega_tiempo = $prov_cotizaciones[$i]["entrega_tiempo"];
                    $row_descuento = $prov_cotizaciones[$i]["descuento"];
                    $row_retenciones = $prov_cotizaciones[$i]["retenciones"];
                    $row_traslados = $prov_cotizaciones[$i]["traslados"];
                    $row_credito_otorga = $prov_cotizaciones[$i]["credito_otorga"] == true ? TRUE : FALSE;
                    $row_credito_time = $prov_cotizaciones[$i]["credito_otorga"] == true ? $JwtAuth->encriptar($prov_cotizaciones[$i]["credito_time"]) : NULL;
                    $row_garantia = $prov_cotizaciones[$i]["garantia"];
                    $row_unidad_medida = $prov_cotizaciones[$i]["unidad_medida"];

                    $DB_FPago = DB::select("SELECT id FROM teci_forma_pago WHERE token_formapago = ?", [$prov_cotizaciones[$i]["forma_pago"]]);
                    $row_forma_pago = end($DB_FPago)->id;

                    $row_valoracion = $prov_cotizaciones[$i]["valoracion"];

                    $token_desc_detalle_cotiza = $JwtAuth->encriptarToken(
                      $row_proveedor,
                      $row_especificaciones,
                      $row_cantidad,
                      $row_precio,
                      $row_moneda,
                      $row_tipo_cambio,
                      $row_conversion,
                      $row_calidad,
                      $row_servicio,
                      $row_entrega_tipo,
                      $row_entrega_tiempo,
                      $row_descuento,
                      $row_retenciones,
                      $row_traslados,
                      $row_credito_otorga,
                      $row_credito_time,
                      $row_garantia,
                      $row_unidad_medida,
                      $row_forma_pago,
                      $row_valoracion
                    );

                    $insertCotizacionDescDetalle = DB::table('eegr_compras_cotizacion_detalle_descripcion')->insert(
                      array(
                        "token_desc_detalle_cotiza" => $token_desc_detalle_cotiza,
                        "cotizacion" => end($selectCotizacionMain)->id,
                        "detalle_cotizacion" => end($selectCotizacionDetalle)->id,
                        "coti_proveedor" => $row_proveedor,
                        "coti_especificaciones" => $JwtAuth->encriptar($row_especificaciones),
                        "coti_cantidad" => $row_cantidad,
                        "coti_precio" => $row_precio,
                        "coti_moneda" => $row_moneda,
                        "coti_tipo_cambio" => $row_tipo_cambio,
                        "coti_calidad" => $JwtAuth->encriptar($row_calidad),
                        "coti_servicio" => $JwtAuth->encriptar($row_servicio),
                        "coti_entrega_tipo" => $row_entrega_tipo,
                        "coti_entrega_tiempo" => $JwtAuth->encriptar($row_entrega_tiempo),
                        "coti_descuento" => $JwtAuth->encriptar($row_descuento),
                        "coti_retenciones" => $JwtAuth->encriptar($row_retenciones),
                        "coti_traslados" => $JwtAuth->encriptar($row_traslados),
                        "coti_credito_otorga" => $row_credito_otorga,
                        "coti_credito_time" => $row_credito_time,
                        "coti_garantia" => $JwtAuth->encriptar($row_garantia),
                        "coti_unidad_medida" => $row_unidad_medida,
                        "coti_forma_pago" => $row_forma_pago,
                        "coti_valoracion" => $JwtAuth->encriptar($row_valoracion),
                      )
                    );

                    if ($insertCotizacionDescDetalle) {
                      ++$count_insert_cProv;
                    } else {
                      $deleteMainCot = DB::table('eegr_compras_cotizacion')->where(["token_cotizacion" => $token_cotizacion])->delete();
                      $deleteDetCot = DB::table('eegr_compras_cotizacion_detalle')->where(["token_detalle_cotizacion" => $token_detalle_cotizacion])->delete();
                      $dataMensaje = array("status" => "error", "code" => 200, "message" => "Desglose de cotización no registrado");
                      break;
                    }
                  }

                  if ($adicionales_total > 0) {
                    for ($i = 0; $i < $adicionales_total; $i++) {
                      $adicionales_claves = $adicionales[$i]["clave"];
                      $adicionales_proveedores = $adicionales[$i]["proveedores"];
                      for ($p = 0; $p < count($adicionales_proveedores); $p++) {
                        $adi_prv_tkn = $adicionales_proveedores[$p]["token_cat_proveedores"];
                        $adi_prv_val = $adicionales_proveedores[$p]["valor"];
                        $adi_prv_ident = DB::select("SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?", [$adi_prv_tkn]);
                        $row_prv_adi = end($adi_prv_ident)->id;

                        $insertCotizacionDescAdicionales = DB::table('eegr_compras_cotizacion_detalle_adicionales')->insert(
                          array(
                            "cotizacion" => end($selectCotizacionMain)->id,
                            "detalle_cotizacion" => end($selectCotizacionDetalle)->id,
                            "clave" => $JwtAuth->encriptar($adicionales_claves),
                            "proveedor" => $row_prv_adi,
                            "valor" => $JwtAuth->encriptar($adi_prv_val),
                          )
                        );
                      }
                    }
                  }

                  if ($proveedores_mejor_opcion_total > 0) {
                    for ($m = 0; $m < $proveedores_mejor_opcion_total; $m++) {
                      $mop_prv_tkn = $proveedores_mejor_opcion[$m]["token_cat_proveedores"];
                      $mop_prv_ident = DB::select("SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?", [$mop_prv_tkn]);
                      $row_prv_mop = end($mop_prv_ident)->id;
                      $mop_prv_posicion = $proveedores_mejor_opcion[$m]["posicion"];
                      $mop_prv_observ = $proveedores_mejor_opcion[$m]["selected_observaciones"];

                      $insertCotizacionDescAdicionales = DB::table('eegr_compras_cotizacion_detalle_mejor_opcion')->insert(
                        array(
                          "cotizacion" => end($selectCotizacionMain)->id,
                          "detalle_cotizacion" => end($selectCotizacionDetalle)->id,
                          "proveedor" => $row_prv_mop,
                          "posicion" => $mop_prv_posicion,
                          "observaciones" => $JwtAuth->encriptar($mop_prv_observ),
                        )
                      );
                    }
                  }

                  if ($count_insert_cProv == $prov_coti_total) {
                    if (count($folioSistema) == 0) {
                      $insertSistema = DB::table('sos_last_folders')
                        ->insert(
                          array(
                            "egr_cotizaciones" => TRUE,
                            "folder" => 1,
                            "empresa" => $selectEmp[0]->id,
                          )
                        );
                    } else {
                      $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                        ->where([
                          'sos_last_folders.egr_cotizaciones' => TRUE,
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
                      "message" => "El registro de cotización se realizó correctamente con el folio COT-" . $JwtAuth->generarFolio($folio_nuevo),
                      "pdflink" => "https://downloads.sos-mexico.com.mx/cotizacion_pdf/" . $token_cotizacion
                    );
                  } else {
                    $dataMensaje = array("status" => "error", "code" => 200, "message" => "Desglose de cotización no registrado");
                  }
                } else {
                  $deleteMainCot = DB::table('eegr_compras_cotizacion')->where(["token_cotizacion" => $token_cotizacion])->delete();
                  $dataMensaje = array("status" => "error", "code" => 200, "message" => "Cotización no registrada");
                }
              } else {
                $dataMensaje = array("status" => "error", "code" => 200, "message" => "Cotización no registrada");
              }
            }
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Requisición inexistente ó no autorizada");
          }
          //$dataMensaje = array("status" => "success","code" => 200,"message" => "listo");
        } else { //proy.status = TRUE AND 
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "error");
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

  public function registrarCotizacionDirecta(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "tkn_proveedor" => "required|string",
        "coti_concepto" => "required|string",
        "coti_marca" => "required|string",
        "coti_cantidad" => "required|string",
        "coti_precio" => "required|string",
        "coti_tkn_moneda" => "required|string",
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
        $tkn_proveedor = $parametrosArray["tkn_proveedor"];
        $coti_concepto = $parametrosArray["coti_concepto"];
        $coti_marca = $parametrosArray["coti_marca"];
        $coti_cantidad = $parametrosArray["coti_cantidad"];
        $coti_precio = $parametrosArray["coti_precio"];
        $coti_tkn_moneda = $parametrosArray["coti_tkn_moneda"];

        //$selectProvident = DB::select("SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?",[$tkn_proveedor]); 
        //echo $selectProvident[0]->id; exit; 

        if (
          isset($tkn_proveedor) && !empty($tkn_proveedor) &&
          isset($coti_concepto) && !empty($coti_concepto) && preg_match($JwtAuth->filtroAlfaNumerico(), $coti_concepto) &&
          isset($coti_marca) && !empty($coti_marca) && preg_match($JwtAuth->filtroAlfaNumerico(), $coti_marca) &&
          isset($coti_cantidad) && !empty($coti_cantidad) && preg_match($JwtAuth->filtroNumerico(), $coti_cantidad) &&
          isset($coti_precio) && !empty($coti_precio) && preg_match($JwtAuth->filtroCostoPrecio(), $coti_precio) &&
          isset($coti_tkn_moneda) && !empty($coti_tkn_moneda)
        ) {

          $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                        JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);
          //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

          $fecha_sistema = time();

          $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                        JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE fold.egr_cotizaciones = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? 
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);

          if (count($folioSistema) == 1) {
            $folio_nuevo = $folioSistema[0]->folio;
          } else {
            $folio_nuevo = 1;
          }

          $token_cotizacion = $JwtAuth->encriptarToken($fecha_sistema . $folio_nuevo . $tkn_proveedor);
          $insertCotizacionMain = DB::table('eegr_compras_cotizacion')->insert(
            array(
              "token_cotizacion" => $token_cotizacion,
              "coti_fecha_sistema" => $fecha_sistema,
              "coti_folio" => $folio_nuevo,
              "referencia" => "CCMDOD",
              "requisicion" => NULL,
              "empresa" => $selectEmp[0]->id,
              "usuario_cotizador" => $selectEmp[0]->userr,
              "status" => TRUE,
            )
          );

          if ($insertCotizacionMain) {
            $selectCotizacionMain = DB::select("SELECT id FROM eegr_compras_cotizacion WHERE token_cotizacion = ?", [$token_cotizacion]);
            $token_detalle_cotizacion = $JwtAuth->encriptarToken($token_cotizacion . $folio_nuevo);

            $insertCotizacionDetalle = DB::table('eegr_compras_cotizacion_detalle')->insert(
              array(
                "token_detalle_cotizacion" => $token_detalle_cotizacion,
                "cotizacion" => $selectCotizacionMain[0]->id,
                "detalle_requisicion" => NULL,
                "status" => TRUE,
                //"fecha_entrega" => $vReq->idMain,
                //"documento" => $selectEmp[0]->id
              )
            );

            if ($insertCotizacionDetalle) {
              $selectCotizacionDetalle = DB::select("SELECT detCot.id FROM eegr_compras_cotizacion_detalle AS detCot 
                                JOIN eegr_compras_cotizacion AS mainCot WHERE detCot.token_detalle_cotizacion = ? AND detCot.cotizacion = mainCot.id
                                AND mainCot.token_cotizacion = ?", [$token_detalle_cotizacion, $token_cotizacion]);

              $selectProvident = DB::select("SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?", [$tkn_proveedor]);
              $selectMonedaident = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?", [$coti_tkn_moneda]);

              $token_desc_detalle_cotiza = $JwtAuth->encriptarToken($token_detalle_cotizacion . $token_cotizacion . $tkn_proveedor . $coti_concepto .
                $coti_marca . $coti_cantidad . $coti_precio . $coti_tkn_moneda);

              $insertCotizacionDescDetalle = DB::table('eegr_compras_cotizacion_detalle_descripcion')->insert(
                array(
                  "token_desc_detalle_cotiza" => $token_desc_detalle_cotiza,
                  "detalle_cotizacion" => $selectCotizacionDetalle[0]->id,
                  "coti_proveedor" => $selectProvident[0]->id,
                  "coti_necesidad" => $JwtAuth->encriptar($coti_concepto),
                  "coti_marca" => $JwtAuth->encriptar($coti_marca),
                  "coti_cantidad" => $coti_cantidad,
                  "coti_precio" => $coti_precio,
                  "coti_moneda" => $selectMonedaident[0]->id,
                )
              );

              if ($insertCotizacionDescDetalle) {
                if (count($folioSistema) == 0) {
                  $insertSistema = DB::table('sos_last_folders')
                    ->insert(
                      array(
                        "egr_cotizaciones" => TRUE,
                        "folder" => 1,
                        "empresa" => $selectEmp[0]->id,
                      )
                    );
                } else {
                  $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                    ->where([
                      'sos_last_folders.egr_cotizaciones' => TRUE,
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
                  "message" => "El registro de cotización se realizó correctamente con el folio COT-" . $JwtAuth->generarFolio($folio_nuevo),
                  "pdflink" => "https://downloads.sos-mexico.com.mx/cotizacion_pdf/" . $token_cotizacion
                );
              } else {
                $deleteMainCot = DB::table('eegr_compras_cotizacion')->where(["token_cotizacion" => $token_cotizacion])->delete();
                $deleteDetCot = DB::table('eegr_compras_cotizacion_detalle')->where(["token_detalle_cotizacion" => $token_detalle_cotizacion])->delete();
                $dataMensaje = array("status" => "error", "code" => 200, "message" => "Desglose de cotización no registrado");
              }
            } else {
              $deleteMainCot = DB::table('eegr_compras_cotizacion')->where(["token_cotizacion" => $token_cotizacion])->delete();
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "Cotización no registrada");
            }
          } else {
            $dataMensaje = array("status" => "error", "code" => 200, "message" => "Cotización no registrada");
          }
        } else { //proy.status = TRUE AND 
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "error");
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

  public function autorizaCotizacionDirecta(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cotizacion" => "required|string",
        "token_detalle_cotizacion" => "required|string",
        "token_desc_detalle_cotiza" => "required|string",
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
        $tkn_cotizacion = $parametrosArray["token_cotizacion"];
        $tkn_cotizacion_detalle = $parametrosArray["token_detalle_cotizacion"];
        $tkn_cotizacion_descdet = $parametrosArray["token_desc_detalle_cotiza"];

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);

        $authcotizacion = DB::table("eegr_compras_cotizacion_detalle_descripcion AS descDetCot")
          ->join("eegr_compras_cotizacion_detalle AS dettCot", "descDetCot.detalle_cotizacion", "=", "dettCot.id")
          ->join("eegr_compras_cotizacion AS cotMain", "dettCot.cotizacion", "=", "cotMain.id")
          ->where(["descDetCot.token_desc_detalle_cotiza" => $tkn_cotizacion_descdet, "dettCot.token_detalle_cotizacion" => $tkn_cotizacion_detalle, "cotMain.token_cotizacion" => $tkn_cotizacion])
          ->limit(1)->update(array("descDetCot.coti_desc_autorizacion" => TRUE, "descDetCot.coti_desc_fecha_autorizacion" => time(), "descDetCot.coti_desc_pers_autoriza" => $selectEmp[0]->userr));

        if ($authcotizacion) {
          $dataMensaje = array("status" => "success", "code" => 200, "message" => "Cotización autorizada");
        } else {
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "Cotización no autorizada");
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

  public function desautorizaCotizacionDirecta(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cotizacion" => "required|string",
        "token_detalle_cotizacion" => "required|string",
        "token_desc_detalle_cotiza" => "required|string",
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
        $tkn_cotizacion = $parametrosArray["token_cotizacion"];
        $tkn_cotizacion_detalle = $parametrosArray["token_detalle_cotizacion"];
        $tkn_cotizacion_descdet = $parametrosArray["token_desc_detalle_cotiza"];

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? AND users.empleado = pers.id", [$usuario->empresa_token, $usuario->user_token]);

        $authcotizacion = DB::table("eegr_compras_cotizacion_detalle_descripcion AS descDetCot")
          ->join("eegr_compras_cotizacion_detalle AS dettCot", "descDetCot.detalle_cotizacion", "=", "dettCot.id")
          ->join("eegr_compras_cotizacion AS cotMain", "dettCot.cotizacion", "=", "cotMain.id")
          ->where(["descDetCot.token_desc_detalle_cotiza" => $tkn_cotizacion_descdet, "dettCot.token_detalle_cotizacion" => $tkn_cotizacion_detalle, "cotMain.token_cotizacion" => $tkn_cotizacion])
          ->limit(1)->update(array("descDetCot.coti_desc_autorizacion" => FALSE, "descDetCot.coti_desc_fecha_autorizacion" => time(), "descDetCot.coti_desc_pers_autoriza" => $selectEmp[0]->userr));
        if ($authcotizacion) {
          $dataMensaje = array("status" => "success", "code" => 200, "message" => "Cotización desautorizada");
        } else {
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "Cotización no desautorizada");
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

  public function cotizacionesAutorizadas(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCotizaciones = array();

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
          $listaCotAuth = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
            ->join("eegr_compras_cotizacion_detalle AS cotDet", "deskCot.detalle_cotizacion", "=", "cotDet.id")
            ->join("eegr_catalogo_proveedores AS catprov", "deskCot.coti_proveedor", "=", "catprov.id")
            //->join("teci_catalogo_monedas AS mon","deskCot.coti_moneda","=","mon.id")
            //->join("teci_unidad_medida AS umed", "deskCot.coti_unidad_medida", "=", "umed.id")
            ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
            ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
            ->where(["deskCot.coti_desc_autorizacion" => TRUE, "emp.empresa_token" => $usuario->empresa_token])->get();
        } else {
          $listaCotAuth = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
            ->join("eegr_compras_cotizacion_detalle AS cotDet", "deskCot.detalle_cotizacion", "=", "cotDet.id")
            ->join("eegr_catalogo_proveedores AS catprov", "deskCot.coti_proveedor", "=", "catprov.id")
            //->join("teci_catalogo_monedas AS mon","deskCot.coti_moneda","=","mon.id")
            //->join("teci_unidad_medida AS umed", "deskCot.coti_unidad_medida", "=", "umed.id")
            ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
            ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "cotMain.usuario_cotizador", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
            ->where(["deskCot.coti_desc_autorizacion" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        }

        if (count($listaCotAuth) > 0) {
          foreach ($listaCotAuth as $vCot) {
            //da_te_default_timezone_set('America/Mexico_City');
            //requisicion
            $requisicion_tkn = "";
            $requisicion_folio = "";
            $requisicion_proyecto = "";
            $requisicion_fecha_registro = "";
            $requisicion_empresa = "";
            $requisicion_pers_requiere = "";
            $requi_inside_tkn = "";
            $requi_inside_neces_tipo = "";
            $requi_inside_neces_concepto = "";
            $requi_inside_neces_caract_list = array();
            $requi_inside_neces_caract_other = null;
            $requi_inside_neces_cantidad = 0;
            $requi_inside_neces_umed_tkn = "";
            $requi_inside_neces_umed_name = "";
            $requi_inside_neces_marca = "";
            $requi_inside_neces_autorizacion = false;
            $requi_inside_neces_auth_pers = "";
            $requi_inside_neces_auth_mensaje = "";
            $requi_inside_neces_docs = array();

            $queryRequi = DB::table("eegr_compras_requisicion_detalle AS reqDet")
              //->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
              ->join("eegr_compras_requisicion AS requi", "reqDet.requisicion", "=", "requi.id")
              ->join("eegr_compras_cotizacion AS coti", "requi.id", "=", "coti.requisicion")
              ->join("eegr_compras_cotizacion_detalle AS cotDet", "coti.id", "=", "cotDet.cotizacion")
              ->join("vhum_empleados_catalogo AS pers", "reqDet.des_autoriza_user", "=", "pers.id")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->whereColumn("reqDet.id", "=", "cotDet.detalle_requisicion")
              ->where(["reqDet.des_autorizacion" => "A", "coti.token_cotizacion" => $vCot->token_cotizacion, "cotDet.token_detalle_cotizacion" => $vCot->token_detalle_cotizacion])->get();

            if (count($queryRequi) == 1) {
              foreach ($queryRequi as $vRequi) {
                $requisicion_tkn = $vRequi->token_requisicion;
                $requi_inside_tkn = $vRequi->token_detalle_requisicion;
                $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vRequi->folio);
                $requisicion_proyecto = $JwtAuth->desencriptar($vRequi->proyecto);
                $requisicion_fecha_registro = gmdate('Y-m-d H:i:s', $vRequi->fecha);
                $queryEmpRequi = DB::table("sos_personas AS people")
                  ->join("main_empresas AS emp", "people.id", "=", "emp.persona")
                  ->join("eegr_compras_requisicion AS requi", "emp.id", "=", "requi.empresa")
                  ->where(["requi.token_requisicion" => $vRequi->token_requisicion])->get();
                $requisicion_empresa = $queryEmpRequi[0]->abrev_nombre;

                $queryPersRequi = DB::table("sos_personas AS people")
                  ->join("vhum_empleados_catalogo AS pers", "people.id", "=", "pers.empleado_name")
                  ->join("eegr_compras_requisicion AS requi", "pers.id", "=", "requi.usuario_requisita")
                  ->where(["requi.token_requisicion" => $vRequi->token_requisicion])->get();
                $requisicion_pers_requiere = $JwtAuth->desencriptarNombres($queryPersRequi[0]->paterno, $queryPersRequi[0]->materno, $queryPersRequi[0]->nombre);

                if ($vRequi->tipo_necesidad == "Merc") {
                  $requi_inside_neces_tipo = "Mercancia";
                }
                if ($vRequi->tipo_necesidad == "Gast") {
                  $requi_inside_neces_tipo = "Gastos";
                }
                if ($vRequi->tipo_necesidad == "Acti") {
                  $requi_inside_neces_tipo = "Activos";
                }
                if ($vRequi->tipo_necesidad == "Mixt") {
                  $requi_inside_neces_tipo = "Mixto";
                }

                $requi_inside_neces_concepto = $JwtAuth->desencriptar($vRequi->necesidad);

                $list_num_caract = 1;
                $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
                  ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
                  ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
                  ->where(["reqMain.token_requisicion" => $vRequi->token_requisicion, "reqDet.token_detalle_requisicion" => $vRequi->token_detalle_requisicion])->get();
                //echo count($selectDetReqCaractList);
                foreach ($selectDetReqCaractList as $vCaract) {
                  $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
                  $db_valor = $JwtAuth->desencriptar($vCaract->valor);
                  $fisrt_valor = $descif_clave == "Precio" ? "$" . number_format($db_valor, $vCot->e_moneda_decimales, '.', ',') : $db_valor;
                  $descif_valor = $list_num_caract > count($selectDetReqCaractList) ? $fisrt_valor . "," : $fisrt_valor;
                  $row_CaractList = array(
                    "token_caract" => $vCaract->token_caract,
                    "num_list" => $list_num_caract,
                    "clave" => $descif_clave,
                    "valorFront" => $descif_valor,
                    "valorBack" => $db_valor
                  );
                  $requi_inside_neces_caract_list[] = $row_CaractList;
                  ++$list_num_caract;
                }

                $requi_inside_neces_caract_other = $vRequi->caracteristicas_extend != NULL ? $JwtAuth->desencriptar($vRequi->caracteristicas_extend) : null;
                $requi_inside_neces_cantidad = $vRequi->cantidad;
                //$requi_inside_neces_umed_tkn = $vRequi->token_unidad_medida;
                $requi_inside_neces_umed_name = $vRequi->medida_unidad;
                $requi_inside_neces_marca = $vRequi->marca != NULL ? $JwtAuth->desencriptar($vRequi->marca) : "no hay marca referida";
                $requi_inside_neces_autorizacion = $vRequi->des_autorizacion == TRUE ? true : false;
                $requi_inside_neces_auth_pers = $JwtAuth->desencriptarNombres($vRequi->paterno, $vRequi->materno, $vRequi->nombre);
                $requi_inside_neces_auth_mensaje = "Requisición autorizada por " . $requi_inside_neces_auth_pers . " (" . gmdate('Y-m-d H:i:s', $vRequi->des_fecha_autorizacion) . ")";

                $selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento 
                                        FROM sos_documentos AS evd JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet 
                                        WHERE evd.requisicion = reqMain.id AND reqMain.token_requisicion = ?
                                        AND evd.detalle_requisicion = reqDet.id AND reqDet.token_detalle_requisicion = ?
                                        AND evd.status_documento = TRUE", [$requisicion_tkn, $vRequi->token_detalle_requisicion]);

                if (count($selectIdEvid) > 0) {
                  $filepath = $vCot->root_tkn . "/0002-cpp/compras/requisiciones/" . $requisicion_folio;
                  foreach ($selectIdEvid as $vDoc) {
                    $each = array(
                      "token_documento" => $vDoc->token_documento,
                      "tipo_documento" => $vDoc->tipo_documento,
                      "nombre_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                      "url" => "https://downloads.sos-mexico.com.mx/compras/requisiciones/" . $requisicion_folio . "/" . $vDoc->token_documento,
                    );
                    $requi_inside_neces_docs[] = $each;
                  }
                }
              }
            }

            //monedas
            $coti_moneda_decimales = $JwtAuth->getMonedaAPI($vCot->coti_moneda); 
            $queryMonedas = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
              ->join("teci_catalogo_monedas AS mon", "deskCot.coti_moneda", "=", "mon.id")
              ->where(["deskCot.token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza])->get();

            //$cotti_moneda_token = $queryMonedas[0]->token_monedas;
            $cotti_moneda_codigo = $vCot->coti_moneda;
            $cotti_moneda_decimales = $coti_moneda_decimales;

            $persona_cotiza = "";
            $cotUser = DB::table("eegr_compras_cotizacion AS cotList")
              ->join("vhum_empleados_catalogo AS pers", "cotList.usuario_cotizador", "=", "pers.id")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where(["cotList.token_cotizacion" => $vCot->token_cotizacion])->get();

            foreach ($cotUser as $cUser) {
              $persona_cotiza = $JwtAuth->desencriptarNombres($cUser->paterno, $cUser->materno, $cUser->nombre);
            }

            if ($vCot->coti_entrega_tipo == "domi") {
              $coti_entrega_tipo_extend = "Domicilio";
            } else if ($vCot->coti_entrega_tipo == "stre") {
              $coti_entrega_tipo_extend = "Tienda";
            } else if ($vCot->coti_entrega_tipo == "ofna") {
              $coti_entrega_tipo_extend = "Oficina";
            } else if ($vCot->coti_entrega_tipo == "dest") {
              $coti_entrega_tipo_extend = "Destino";
            } else if ($vCot->coti_entrega_tipo == "cntr") {
              $coti_entrega_tipo_extend = "Contra reembolso";
            }

            $coti_credito_otorga = $vCot->coti_credito_otorga == TRUE ? true : false;
            $coti_credito_time = $vCot->coti_credito_otorga == TRUE ? $JwtAuth->desencriptar($vCot->coti_credito_time) : null;
            $coti_precio = "$" . number_format($vCot->coti_precio, $vCot->e_moneda_decimales, '.', ',') . " " . $vCot->e_moneda_code;

            if ($vCot->e_moneda_code == $cotti_moneda_codigo) {
              $coti_conversion = "$" . number_format($vCot->coti_precio, $vCot->e_moneda_decimales, '.', ',') . " " . $vCot->e_moneda_code;
            } else {
              $convet = $vCot->coti_precio * $vCot->coti_tipo_cambio;
              $coti_conversion = "$" . number_format($convet, $cotti_moneda_decimales, '.', ',') . " " . $cotti_moneda_codigo;
            }

            //forma de pago registrada desde cotización 
            $queryPayMent = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
              ->join("teci_forma_pago AS fpay", "deskCot.coti_forma_pago", "=", "fpay.id")
              ->where(["deskCot.token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza])->get();
            $coti_inside_fpay_token = $queryPayMent[0]->token_formapago;
            $coti_inside_fpay_forma = $queryPayMent[0]->clave . " - " . $queryPayMent[0]->forma;

            $coti_desc_pers_autoriza = "";
            if ($vCot->coti_desc_autorizacion == TRUE) {
              $persAuthCoti = DB::table("sos_personas AS people")
                ->join("vhum_empleados_catalogo AS persAuth", "people.id", "=", "persAuth.empleado_name")
                ->join("eegr_compras_cotizacion_detalle_descripcion AS cotDesk", "persAuth.id", "=", "cotDesk.coti_desc_pers_autoriza")
                ->where(["cotDesk.token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza])->get();
              $coti_desc_pers_autoriza = $JwtAuth->desencriptarNombres($persAuthCoti[0]->paterno, $persAuthCoti[0]->materno, $persAuthCoti[0]->nombre);
            }
            $coti_inside_auth_mensaje = "Cotización autorizada por " . $coti_desc_pers_autoriza . " (" . gmdate('Y-m-d H:i:s', $vCot->coti_desc_fecha_autorizacion) . ")";
            //echo $coti_inside_neces_auth_mensaje;

            $row = array(
              "requisicion_tkn" => $requisicion_tkn,
              "requisicion_folio" => $requisicion_folio,
              "requisicion_proyecto" => $requisicion_proyecto,
              "requisicion_fecha_registro" => $requisicion_fecha_registro,
              "requisicion_empresa" => $requisicion_empresa,
              "requisicion_pers_requiere" => $requisicion_pers_requiere,
              "token_detalle_requisicion" => $requi_inside_tkn,
              "requi_necesidad_tipo" => $requi_inside_neces_tipo,
              "requi_necesidad_concepto" => $requi_inside_neces_concepto,
              "requi_necesidad_caracteristicas_list" => $requi_inside_neces_caract_list,
              "requi_necesidad_caracteristicas_other" => $requi_inside_neces_caract_other,
              "requi_necesidad_cantidad" => $requi_inside_neces_cantidad,
              "requi_necesidad_umed_tkn" => $requi_inside_neces_umed_tkn,
              "requi_necesidad_umed_name" => $requi_inside_neces_umed_name,
              "requi_necesidad_marca" => $requi_inside_neces_marca,
              "requi_necesidad_autorizacion" => $requi_inside_neces_autorizacion,
              "requi_necesidad_auth_mensaje" => $requi_inside_neces_auth_mensaje,
              "requi_necesidad_auth_pers" => $requi_inside_neces_auth_pers,
              "requi_necesidad_docs" => $requi_inside_neces_docs,
              "cotizacion_tkn" => $vCot->token_cotizacion,
              "cotizacion_folio" => "COT-" . $JwtAuth->generarFolio($vCot->coti_folio),
              "cotizacion_fecha" => gmdate('Y-m-d H:i:s', $vCot->coti_fecha_sistema),
              "cotizacion_persona" => $persona_cotiza,
              "cotizacion_auth_mensaje" => $coti_inside_auth_mensaje,
              "cotizacion_pdf" => "https://downloads.sos-mexico.com.mx/cotizacion_pdf/" . $requisicion_tkn . "/" . $vCot->coti_folio,
              "coti_token_detalle_cotizacion" => $vCot->token_detalle_cotizacion,
              "coti_token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza,
              "coti_inside_especificaciones" => $JwtAuth->desencriptar($vCot->coti_especificaciones),
              "coti_inside_cantidad" => $vCot->coti_cantidad,
              //token_monedas
              //"coti_inside_moneda_token" => $vCot->token_monedas,
              "coti_inside_moneda_codigo" => $vCot->e_moneda_code,
              //"coti_inside_moneda_name" => $vCot->moneda,
              "coti_inside_moneda_decimales" => $vCot->e_moneda_decimales,
              "coti_inside_precio" => $coti_precio,
              "coti_inside_tipo_cambio" => $vCot->coti_tipo_cambio,
              "coti_inside_conversion" => $coti_conversion,
              "coti_inside_calidad" => $JwtAuth->desencriptar($vCot->coti_calidad),
              "coti_inside_servicio" => $JwtAuth->desencriptar($vCot->coti_servicio),
              "coti_inside_entrega_tipo_extend" => $coti_entrega_tipo_extend,
              "coti_inside_entrega_tiempo" => $JwtAuth->desencriptar($vCot->coti_entrega_tiempo),
              "coti_inside_descuento" => $JwtAuth->desencriptar($vCot->coti_descuento),
              "coti_inside_retenciones" => $JwtAuth->desencriptar($vCot->coti_retenciones),
              "coti_inside_traslados" => $JwtAuth->desencriptar($vCot->coti_traslados),
              "coti_inside_credito_otorga" => $coti_credito_otorga,
              "coti_inside_credito_time" => $coti_credito_time,
              "coti_inside_garantia" => $JwtAuth->desencriptar($vCot->coti_garantia),
              //coti_unidad_medida
              //"coti_inside_umed_token" => $vCot->token_unidad_medida,
              "coti_inside_umed_name" => $vCot->coti_unidad_medida,
              //"coti_inside_umed_representa" => $vCot->representa,
              //coti_forma_pago
              "coti_inside_fpay_token" => $coti_inside_fpay_token,
              "coti_inside_fpay_forma" => $coti_inside_fpay_forma,
              //coti_metodo_pago  
              "coti_inside_valoracion" => $JwtAuth->desencriptar($vCot->coti_valoracion),
              "coti_inside_contacto_proveedor" => $vCot->contacto_proveedor == TRUE ? true : false,
              "coti_inside_contacto_proveedor_fecha" => $vCot->contacto_proveedor == TRUE ? gmdate('Y-m-d H:i:s', $vCot->contacto_proveedor_fecha) : "",
              //proveedor
              "token_cat_proveedores" => $vCot->token_cat_proveedores,
              "proveedor_data" => [],
            );
            $arrayCotizaciones[] = $row;
          }
          $dataMensaje = array("status" => "success", "code" => 200, "lista_cotizaciones" => $arrayCotizaciones);
        } else {
          $dataMensaje = array("status" => "success", "code" => 200, "message" => "No hay cotizaciones autorizadas");
        }
      }
    } else {
      $dataMensaje = array("status" => "error", "code" => 404, "message" => "Los informacion que intenta registrar no es valida");
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cotizacionConfirmarContactoProv(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCotizaciones = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "cotizacion_tkn" => "required|string",
        "coti_token_detalle_cotizacion" => "required|string",
        "coti_token_desc_detalle_cotiza" => "required|string",
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
        $cotizacion_tkn = $parametrosArray["cotizacion_tkn"];
        $coti_token_detalle_cotizacion = $parametrosArray["coti_token_detalle_cotizacion"];
        $coti_token_desc_detalle_cotiza = $parametrosArray["coti_token_desc_detalle_cotiza"];

        $listaCotAuth = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
          ->join("eegr_compras_cotizacion_detalle AS cotDet", "deskCot.detalle_cotizacion", "=", "cotDet.id")
          ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
          ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
          ->join("vhum_empleados_catalogo AS pers", "cotMain.usuario_cotizador", "=", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
          ->where([
            "deskCot.coti_desc_autorizacion" => TRUE,
            "deskCot.token_desc_detalle_cotiza" => $coti_token_desc_detalle_cotiza,
            "cotDet.token_detalle_cotizacion" => $coti_token_detalle_cotizacion,
            "cotMain.token_cotizacion" => $cotizacion_tkn,
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token
          ])->get();

        if (count($listaCotAuth) == 1) {
          foreach ($listaCotAuth as $vCot) {
            $confirmQuery = DB::table("eegr_compras_cotizacion_detalle_descripcion")->where(["token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza])
              ->limit(1)->update(array("contacto_proveedor" => TRUE, "contacto_proveedor_fecha" => time()));
            if ($confirmQuery) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "Confirmación registrada");
            } else {
              $dataMensaje = array("status" => "error", "code" => 404, "message" => "Confirmación no registrada");
            }
          }
        } else {
          $dataMensaje = array("status" => "error", "code" => 404, "message" => "No hay cotizaciones autorizadas");
        }
      }
    } else {
      $dataMensaje = array("status" => "error", "code" => 404, "message" => "Los informacion que intenta registrar no es valida");
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cotizacionesPreordenCompra(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCotizaciones = array();

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
          $listaCotAuth = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
            ->join("eegr_compras_cotizacion_detalle AS cotDet", "deskCot.detalle_cotizacion", "=", "cotDet.id")
            ->join("eegr_catalogo_proveedores AS catprov", "deskCot.coti_proveedor", "=", "catprov.id")
            ->join("teci_unidad_medida AS umed", "deskCot.coti_unidad_medida", "=", "umed.id")
            ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
            ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
            ->where(["deskCot.contacto_proveedor" => TRUE, "deskCot.coti_desc_autorizacion" => TRUE, "emp.empresa_token" => $usuario->empresa_token])->get();
        } else {
          $listaCotAuth = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
            ->join("eegr_compras_cotizacion_detalle AS cotDet", "deskCot.detalle_cotizacion", "=", "cotDet.id")
            ->join("eegr_catalogo_proveedores AS catprov", "deskCot.coti_proveedor", "=", "catprov.id")
            ->join("teci_unidad_medida AS umed", "deskCot.coti_unidad_medida", "=", "umed.id")
            ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
            ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "cotMain.usuario_cotizador", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
            ->where(["deskCot.contacto_proveedor" => TRUE, "deskCot.coti_desc_autorizacion" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
        }

        if (count($listaCotAuth) > 0) {
          foreach ($listaCotAuth as $vCot) {
            //da_te_default_timezone_set('America/Mexico_City');
            //requisicion
            $requisicion_tkn = "";
            $requisicion_folio = "";
            $requisicion_proyecto = "";
            $requisicion_fecha_registro = "";
            $requisicion_empresa = "";
            $requisicion_pers_requiere = "";
            $requi_inside_tkn = "";
            $requi_inside_neces_tipo = "";
            $requi_inside_neces_concepto = "";
            $requi_inside_neces_caract_list = array();
            $requi_inside_neces_caract_other = null;
            $requi_inside_neces_cantidad = 0;
            $requi_inside_neces_umed_tkn = "";
            $requi_inside_neces_umed_name = "";
            $requi_inside_neces_marca = "";
            $requi_inside_neces_autorizacion = false;
            $requi_inside_neces_auth_pers = "";
            $requi_inside_neces_auth_mensaje = "";
            $requi_inside_neces_docs = array();

            $queryRequi = DB::table("eegr_compras_requisicion_detalle AS reqDet")
              ->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
              ->join("eegr_compras_requisicion AS requi", "reqDet.requisicion", "=", "requi.id")
              ->join("eegr_compras_cotizacion AS coti", "requi.id", "=", "coti.requisicion")
              ->join("eegr_compras_cotizacion_detalle AS cotDet", "coti.id", "=", "cotDet.cotizacion")
              ->join("vhum_empleados_catalogo AS pers", "reqDet.des_autoriza_user", "=", "pers.id")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->whereColumn("reqDet.id", "=", "cotDet.detalle_requisicion")
              ->where(["reqDet.des_autorizacion" => TRUE, "coti.token_cotizacion" => $vCot->token_cotizacion, "cotDet.token_detalle_cotizacion" => $vCot->token_detalle_cotizacion])->get();

            if (count($queryRequi) == 1) {
              foreach ($queryRequi as $vRequi) {
                $requisicion_tkn = $vRequi->token_requisicion;
                $requi_inside_tkn = $vRequi->token_detalle_requisicion;
                $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vRequi->folio);
                $requisicion_proyecto = $JwtAuth->desencriptar($vRequi->proyecto);
                $requisicion_fecha_registro = gmdate('Y-m-d H:i:s', $vRequi->fecha);
                $queryEmpRequi = DB::table("sos_personas AS people")
                  ->join("main_empresas AS emp", "people.id", "=", "emp.persona")
                  ->join("eegr_compras_requisicion AS requi", "emp.id", "=", "requi.empresa")
                  ->where(["requi.token_requisicion" => $vRequi->token_requisicion])->get();
                $requisicion_empresa = $queryEmpRequi[0]->abrev_nombre;

                $queryPersRequi = DB::table("sos_personas AS people")
                  ->join("vhum_empleados_catalogo AS pers", "people.id", "=", "pers.empleado_name")
                  ->join("eegr_compras_requisicion AS requi", "pers.id", "=", "requi.usuario_requisita")
                  ->where(["requi.token_requisicion" => $vRequi->token_requisicion])->get();
                $requisicion_pers_requiere = $JwtAuth->desencriptarNombres($queryPersRequi[0]->paterno, $queryPersRequi[0]->materno, $queryPersRequi[0]->nombre);

                if ($vRequi->tipo_necesidad == "Merc") {
                  $requi_inside_neces_tipo = "Mercancia";
                }
                if ($vRequi->tipo_necesidad == "Gast") {
                  $requi_inside_neces_tipo = "Gastos";
                }
                if ($vRequi->tipo_necesidad == "Acti") {
                  $requi_inside_neces_tipo = "Activos";
                }
                if ($vRequi->tipo_necesidad == "Mixt") {
                  $requi_inside_neces_tipo = "Mixto";
                }

                $requi_inside_neces_concepto = $JwtAuth->desencriptar($vRequi->necesidad);

                $list_num_caract = 1;
                $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
                  ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
                  ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
                  ->where(["reqMain.token_requisicion" => $vRequi->token_requisicion, "reqDet.token_detalle_requisicion" => $vRequi->token_detalle_requisicion])->get();
                //echo count($selectDetReqCaractList);
                foreach ($selectDetReqCaractList as $vCaract) {
                  $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
                  $db_valor = $JwtAuth->desencriptar($vCaract->valor);
                  $fisrt_valor = $descif_clave == "Precio" ? "$" . number_format($db_valor, $vCot->e_moneda_decimales, '.', ',') : $db_valor;
                  $descif_valor = $list_num_caract > count($selectDetReqCaractList) ? $fisrt_valor . "," : $fisrt_valor;
                  $row_CaractList = array(
                    "token_caract" => $vCaract->token_caract,
                    "num_list" => $list_num_caract,
                    "clave" => $descif_clave,
                    "valorFront" => $descif_valor,
                    "valorBack" => $db_valor
                  );
                  $requi_inside_neces_caract_list[] = $row_CaractList;
                  ++$list_num_caract;
                }

                $requi_inside_neces_caract_other = $vRequi->caracteristicas_extend != NULL ? $JwtAuth->desencriptar($vRequi->caracteristicas_extend) : null;
                $requi_inside_neces_cantidad = $vRequi->cantidad;
                $requi_inside_neces_umed_tkn = $vRequi->token_unidad_medida;
                $requi_inside_neces_umed_name = $vRequi->unidad_medida . " - " . $vRequi->sat_clave . ", representa " . $vRequi->representa;
                $requi_inside_neces_marca = $vRequi->marca != NULL ? $JwtAuth->desencriptar($vRequi->marca) : "no hay marca referida";
                $requi_inside_neces_autorizacion = $vRequi->des_autorizacion == TRUE ? true : false;
                $denominacion_rs = $vRequi->denominacion_rs;
                $requi_inside_neces_auth_pers = $denominacion_rs ? $JwtAuth->desencriptar($denominacion_rs) : $JwtAuth->desencriptarNombres($vRequi->paterno, $vRequi->materno, $vRequi->nombre);
                $requi_inside_neces_auth_mensaje = "Requisición autorizada por " . $requi_inside_neces_auth_pers . " (" . gmdate('Y-m-d H:i:s', $vRequi->des_fecha_autorizacion) . ")";

                $selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento 
                                        FROM sos_documentos AS evd JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet 
                                        WHERE evd.requisicion = reqMain.id AND reqMain.token_requisicion = ?
                                        AND evd.detalle_requisicion = reqDet.id AND reqDet.token_detalle_requisicion = ?
                                        AND evd.status_documento = TRUE", [$requisicion_tkn, $vRequi->token_detalle_requisicion]);

                if (count($selectIdEvid) > 0) {
                  $filepath = $vCot->root_tkn . "/0002-cpp/compras/requisiciones/" . $requisicion_folio;
                  foreach ($selectIdEvid as $vDoc) {
                    $each = array(
                      "token_documento" => $vDoc->token_documento,
                      "tipo_documento" => $vDoc->tipo_documento,
                      "nombre_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                      "url" => "https://downloads.sos-mexico.com.mx/compras/requisiciones/" . $requisicion_folio . "/" . $vDoc->token_documento,
                    );
                    $requi_inside_neces_docs[] = $each;
                  }
                }
              }
            }

            //monedas
            $queryMonedas = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
              ->join("teci_catalogo_monedas AS mon", "deskCot.coti_moneda", "=", "mon.id")
              ->where(["deskCot.token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza])->get();

            $cotti_moneda_token = $queryMonedas[0]->token_monedas;
            $cotti_moneda_codigo = $queryMonedas[0]->codigo;
            $cotti_moneda_name = $queryMonedas[0]->moneda;
            $cotti_moneda_decimales = $queryMonedas[0]->decimales;

            $persona_cotiza = "";
            $cotUser = DB::table("eegr_compras_cotizacion AS cotList")
              ->join("vhum_empleados_catalogo AS pers", "cotList.usuario_cotizador", "=", "pers.id")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where(["cotList.token_cotizacion" => $vCot->token_cotizacion])->get();

            foreach ($cotUser as $cUser) {
              $persona_cotiza = $JwtAuth->desencriptarNombres($cUser->paterno, $cUser->materno, $cUser->nombre);
            }

            if ($vCot->coti_entrega_tipo == "domi") {
              $coti_entrega_tipo_extend = "Domicilio";
            } else if ($vCot->coti_entrega_tipo == "stre") {
              $coti_entrega_tipo_extend = "Tienda";
            } else if ($vCot->coti_entrega_tipo == "ofna") {
              $coti_entrega_tipo_extend = "Oficina";
            } else if ($vCot->coti_entrega_tipo == "dest") {
              $coti_entrega_tipo_extend = "Destino";
            } else if ($vCot->coti_entrega_tipo == "cntr") {
              $coti_entrega_tipo_extend = "Contra reembolso";
            }

            $coti_credito_otorga = $vCot->coti_credito_otorga == TRUE ? true : false;
            $coti_credito_time = $vCot->coti_credito_otorga == TRUE ? $JwtAuth->desencriptar($vCot->coti_credito_time) : null;
            $coti_precio = "$" . number_format($vCot->coti_precio, $vCot->decimales, '.', ',') . " " . $vCot->codigo . " " . $vCot->moneda;

            if ($vCot->token_monedas == $vCot->token_monedas) {
              $coti_conversion = "$" . number_format($vCot->coti_precio, $vCot->decimales, '.', ',') . " " . $vCot->codigo . " " . $vCot->moneda;
            } else {
              $convet = $vCot->coti_precio * $vCot->coti_tipo_cambio;
              $coti_conversion = "$" . number_format($convet, $cotti_moneda_decimales, '.', ',') . " " . $cotti_moneda_codigo . " " . $cotti_moneda_name;
            }

            //forma de pago registrada desde cotización 
            $queryPayMent = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
              ->join("teci_forma_pago AS fpay", "deskCot.coti_forma_pago", "=", "fpay.id")
              ->join("teci_metodo_pago AS mpay", "deskCot.coti_metodo_pago", "=", "mpay.id")
              ->where(["deskCot.token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza])->get();
            $coti_inside_fpay_token = $queryPayMent[0]->token_formapago;
            $coti_inside_fpay_forma = $queryPayMent[0]->clave . " - " . $queryPayMent[0]->forma;
            $coti_mpay_token = $queryPayMent[0]->token_metodopago;
            $coti_mpay_metodo = $queryPayMent[0]->abrev . " - " . $queryPayMent[0]->metodo;

            $coti_desc_pers_autoriza = "";
            if ($vCot->coti_desc_autorizacion == TRUE) {
              $persAuthCoti = DB::table("sos_personas AS people")
                ->join("vhum_empleados_catalogo AS persAuth", "people.id", "=", "persAuth.empleado_name")
                ->join("eegr_compras_cotizacion_detalle_descripcion AS cotDesk", "persAuth.id", "=", "cotDesk.coti_desc_pers_autoriza")
                ->where(["cotDesk.token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza])->get();
              $coti_desc_pers_autoriza = $JwtAuth->desencriptarNombres($persAuthCoti[0]->paterno, $persAuthCoti[0]->materno, $persAuthCoti[0]->nombre);
            }
            $coti_inside_auth_mensaje = "Cotización autorizada por " . $coti_desc_pers_autoriza . " (" . gmdate('Y-m-d H:i:s', $vCot->coti_desc_fecha_autorizacion) . ")";
            //echo $coti_inside_neces_auth_mensaje;

            //proveedor
            $proveedor_tkn = $vCot->token_cat_proveedores;
            $proveedor_folio = "";
            $proveedor_name = "";
            $proveedor_clasificacion = "";
            $proveedor_nacionalidad = "";
            $pais_token = "";
            $pais_name = "";
            $proveedor_identif = "";
            $proveedor_redes = array();
            $proveedor_lista_precios = "";
            $proveedor_personalContacto = array();
            $proveedor_tiene_docs_fiscales = false;
            $proveedor_docs_sit_fiscal = array();
            $proveedor_docs_obl_fiscal = array();
            $proveedor_docs_contratos = array();
            $proveedor_docs_anexos = array();
            $proveedor_no_cuenta_fiscales = "-";
            $proveedor_creditos = array();
            $proveedor_formaPago = array();
            $proveedor_ubicacion = array();
            $proveedor_ubicacionDel = array();
            $proveedor_receptFacturaConcept = "";
            $proveedor_receptFacturaBool = false;
            $proveedor_recibeArtPagoConcept = "";
            $proveedor_recibeArtPagoBool = false;

            $selectProveedor = DB::table("eegr_catalogo_proveedores AS catprv")
              ->join("sos_personas AS perns", "catprv.proveedor", "=", "perns.id")
              ->join("teci_pais AS country", "perns.nacionalidad", "=", "country.id")
              ->join("main_empresas AS emp", "catprv.administrador", "=", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
              ->where([
                'catprv.token_cat_proveedores' => $proveedor_tkn,
                'emp.empresa_token' => $usuario->empresa_token,
                'users.usuario_token' => $usuario->user_token,
              ])->get();

            foreach ($selectProveedor as $vProv) {
              //da_te_default_timezone_set($vProv->zona_horaria);

              if ($vProv->folio != NULL && $vProv->folio != "") {
                $proveedor_folio = $vProv->post_folio == NULL ? 'PRV-' . $JwtAuth->generarFolio($vProv->folio) : 'PRV-' . $JwtAuth->generarFolio($vProv->folio) . '-' . $vProv->post_folio;
              } else {
                $proveedor_folio = 'PRV-TEMP-' . $JwtAuth->generarFolio($vProv->temp_folio);
              }

              $namePrvDen = $vProv->denominacion_rs;
              $proveedor_name = $vProv->nombre_com != '' && $vProv->nombre_com != '-' ? $JwtAuth->desencriptar($vProv->nombre_com) : ($namePrvDen == "" ? $JwtAuth->desencriptarNombres($vProv->paterno, $vProv->materno, $vProv->nombre) : $JwtAuth->desencriptar($vProv->denominacion_rs));
              $pfmx = "Persona física (México)";
              $pfext = "Persona física Extranjero";
              $pmmx = "Persona moral (México)";
              $pmext = "Persona moral Extranjero"; //$vProv->nacionalidad == 118 
              $proveedor_clasificacion = $namePrvDen == "" ? ($vProv->nacionalidad == 118 ? $pfmx : $pfext) : ($vProv->nacionalidad == 118 ? $pmmx : $pmext);
              $proveedor_nacionalidad = $vProv->nacionalidad;
              $pais_token = $vProv->token_pais;
              $pais_name = $vProv->pais;
              $proveedor_identif = $vProv->rfc != NULL ? $JwtAuth->desencriptar($vProv->rfc) : ($vProv->tax_id != NULL ? $JwtAuth->desencriptar($vProv->tax_id) : $vProv->rfc_generico);

              if ($vProv->redes_soc != '' && $vProv->redes_soc != NULL) {
                $listaRedes = json_decode($JwtAuth->desencriptar($vProv->redes_soc));
                for ($r = 0; $r < count($listaRedes); $r++) {
                  $proveedor_redes[$r] = $listaRedes[$r];
                }
              } else {
                $proveedor_redes = array('', '', '', '');
              }

              $proveedor_lista_precios = $vProv->lista_precios != '' ? $vProv->lista_precios : "";

              //contacto actual
              $queryContProv = DB::table("in_egr_contacto_cliente_proveedor AS empleado")
                ->join("vhum_personal_area AS areapers", "empleado.area", "=", "areapers.id")
                ->join("vhum_personal_cargo AS cargopers", "empleado.cargo", "=", "cargopers.id")
                ->join("sos_personas AS people", "empleado.nombre", "=", "people.id")
                ->join("eegr_catalogo_proveedores AS catprov", "empleado.cat_proveedores", "=", "catprov.id")
                ->where(["empleado.status" => TRUE, "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();

              if (count($queryContProv) > 0) {
                foreach ($queryContProv as $valContProv) {
                  $arrayTelefono = array();
                  $arrayTelefonoDeleted = array();
                  $telefonoProv = DB::table("sos_personas_telefonos AS tel")
                    ->join("in_egr_contacto_cliente_proveedor AS empleado", "tel.personal", "=", "empleado.id")
                    ->where(["tel.status_telefono" => TRUE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

                  if (count($telefonoProv) > 0) {
                    foreach ($telefonoProv as $valueTelPers) {
                      $telExtension = '';
                      if ($valueTelPers->extension != '') {
                        $telExtension = $JwtAuth->desencriptar($valueTelPers->extension);
                      }
                      $arrateleach = array(
                        'token_telefono' => $valueTelPers->token_telefono,
                        'telefono' => $JwtAuth->desencriptar($valueTelPers->telefono),
                        'extension' => $telExtension,
                        'icono' => $valueTelPers->icono,
                        'etiqueta' => $valueTelPers->etiqueta,
                        'validate' => false,
                      );
                      $arrayTelefono[] = $arrateleach;
                    }
                  }

                  $telefonoProvDeleted = DB::table("sos_personas_telefonos AS tel")
                    ->join("in_egr_contacto_cliente_proveedor AS empleado", "tel.personal", "=", "empleado.id")
                    ->where(["tel.status_telefono" => FALSE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

                  if (count($telefonoProvDeleted) > 0) {
                    foreach ($telefonoProvDeleted as $vTelPers) {
                      $telExtension = '';
                      if ($vTelPers->extension != '') {
                        $telExtension = $JwtAuth->desencriptar($vTelPers->extension);
                      }
                      $arrateleach = array(
                        'token_telefono' => $vTelPers->token_telefono,
                        'telefono' => $JwtAuth->desencriptar($vTelPers->telefono),
                        'extension' => $telExtension,
                        'icono' => $vTelPers->icono,
                        'etiqueta' => $vTelPers->etiqueta,
                        'validate' => false,
                      );
                      $arrayTelefonoDeleted[] = $arrateleach;
                    }
                  }

                  $arrayCorreo = array();
                  $arrayCorreoDel = array();

                  $queryMailProv = DB::table("sos_personas_correos AS mailpers")
                    ->join("in_egr_contacto_cliente_proveedor AS empleado", "mailpers.personal", "=", "empleado.id")
                    ->where(["mailpers.status_correo" => TRUE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

                  if (count($queryMailProv) > 0) {
                    foreach ($queryMailProv as $valueMailPers) {
                      $arrateleach = array(
                        'token_correo' => $valueMailPers->token_correo,
                        'correo' => $JwtAuth->desencriptar($valueMailPers->correo)
                      );
                      $arrayCorreo[] = $arrateleach;
                    }
                  }

                  $queryMailPrvD = DB::table("sos_personas_correos AS mailpers")
                    ->join("in_egr_contacto_cliente_proveedor AS empleado", "mailpers.personal", "=", "empleado.id")
                    ->where(["mailpers.status_correo" => FALSE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

                  if (count($queryMailPrvD) > 0) {
                    foreach ($queryMailPrvD as $vMailPers) {
                      $arrateleach = array(
                        'token_correo' => $vMailPers->token_correo,
                        'correo' => $JwtAuth->desencriptar($vMailPers->correo),
                        'fechaDelete' => gmdate('Y-m-d H:i:s', $vMailPers->fecha_delete_correo),
                      );
                      $arrayCorreoDel[] = $arrateleach;
                    }
                  }

                  $proveVig = array(
                    "token_contacto" => $valContProv->token_contacto,
                    "paterno" => strtolower($JwtAuth->desencriptar($valContProv->paterno)),
                    "materno" => strtolower($JwtAuth->desencriptar($valContProv->materno)),
                    "nombre" => strtolower($JwtAuth->desencriptar($valContProv->nombre)),
                    "areaemp" => strtolower($JwtAuth->desencriptar($valContProv->areaemp)),
                    "cargo" => strtolower($JwtAuth->desencriptar($valContProv->cargo)),
                    "telefono" => $arrayTelefono,
                    "telefonoDeleted" => $arrayTelefonoDeleted,
                    "correo" => $arrayCorreo,
                    "arrayCorreoDel" => $arrayCorreoDel
                  );
                  $proveedor_personalContacto[] = $proveVig;
                }
              }

              $proveedor_tiene_docs_fiscales = $vProv->tiene_docs_fiscales == TRUE ? true : false;
              if ($vProv->tiene_docs_fiscales == TRUE) {
                $selectSitFisDoc = DB::table("sos_documentos AS docs")
                  ->join("eegr_catalogo_proveedores AS catprov", "docs.proveedor", "=", "catprov.id")
                  ->where([
                    "docs.tipo_documento" => "fcsf",
                    "docs.status_documento" => TRUE,
                    "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores
                  ])->get();
                if (count($selectSitFisDoc) > 0) {
                  foreach ($selectSitFisDoc as $vDoc) {
                    $rowDocs = array(
                      "doc_token_" => $vDoc->token_documento,
                      "doc_name_" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                      "doc_url_" => "https://downloads.sos-mexico.com.mx/proveedores/" . $vDoc->token_documento,
                    );
                    $proveedor_docs_sit_fiscal[] = $rowDocs;
                  }
                }

                $selectOblgFisDoc = DB::table("sos_documentos AS docs")
                  ->join("eegr_catalogo_proveedores AS catprov", "docs.proveedor", "=", "catprov.id")
                  ->where([
                    "docs.tipo_documento" => "cuof",
                    "docs.status_documento" => TRUE,
                    "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores
                  ])->get();
                if (count($selectOblgFisDoc) > 0) {
                  foreach ($selectOblgFisDoc as $vDoc) {
                    $rowDocs = array(
                      "doc_token_" => $vDoc->token_documento,
                      "doc_name_" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                      "doc_url_" => "https://downloads.sos-mexico.com.mx/proveedores/" . $vDoc->token_documento,
                    );
                    $proveedor_docs_obl_fiscal[] = $rowDocs;
                  }
                }

                $selectContratoDoc = DB::table("sos_documentos AS docs")
                  ->join("eegr_catalogo_proveedores AS catprov", "docs.proveedor", "=", "catprov.id")
                  ->where([
                    "docs.tipo_documento" => "fcnt",
                    "docs.status_documento" => TRUE,
                    "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores
                  ])->get();
                if (count($selectContratoDoc) > 0) {
                  foreach ($selectContratoDoc as $vDoc) {
                    $rowDocs = array(
                      "doc_token_" => $vDoc->token_documento,
                      "doc_name_" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                      "doc_url_" => "https://downloads.sos-mexico.com.mx/proveedores/" . $vDoc->token_documento,
                    );
                    $proveedor_docs_contratos[] = $rowDocs;
                  }
                }

                $selectAnexosDoc = DB::table("sos_documentos AS docs")
                  ->join("eegr_catalogo_proveedores AS catprov", "docs.proveedor", "=", "catprov.id")
                  ->where([
                    "docs.tipo_documento" => "anex",
                    "docs.status_documento" => TRUE,
                    "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores
                  ])->get();
                if (count($selectAnexosDoc) > 0) {
                  foreach ($selectAnexosDoc as $vDoc) {
                    $rowDocs = array(
                      "doc_token_" => $vDoc->token_documento,
                      "doc_name_" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                      "doc_url_" => "https://downloads.sos-mexico.com.mx/proveedores/" . $vDoc->token_documento,
                    );
                    $proveedor_docs_anexos[] = $rowDocs;
                  }
                }
              }

              $no_cuenta_fiscales = $vProv->no_cuenta_fiscales != NULL && $vProv->no_cuenta_fiscales != '' ? $JwtAuth->desencriptar($vProv->no_cuenta_fiscales) : "-";

              //creditos
              $token_moneda = "";
              $credProveedor = DB::table("eegr_catalogo_proveedores AS catprv")
                ->join("in_egr_creditos AS cred", "catprv.id", "=", "cred.proveedor")
                ->where(["catprv.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();

              if (count($credProveedor) > 0) {
                $queryAsignCreditos = DB::select("SELECT cred.token_creditos,cred.aceptacredito,
                                IF (cred.moneda in (SELECT id FROM teci_catalogo_monedas),(SELECT token_monedas FROM teci_catalogo_monedas WHERE id = cred.moneda),'') AS token_monedas, 
                                IF (cred.moneda in (SELECT id FROM teci_catalogo_monedas),(SELECT codigo FROM teci_catalogo_monedas WHERE id = cred.moneda),'') AS codigo,
                                IF (cred.moneda in (SELECT id FROM teci_catalogo_monedas),(SELECT moneda FROM teci_catalogo_monedas WHERE id = cred.moneda),'') AS moneda, 
                                IF (cred.moneda in (SELECT id FROM teci_catalogo_monedas),cred.limite,'') AS limite,
                                   IF (cred.moneda in (SELECT id FROM teci_catalogo_monedas),cred.dias,'') AS diasPago,
                                   IF (cred.moneda in (SELECT id FROM teci_catalogo_monedas),cred.comienza,'') AS comienza
                                   FROM in_egr_creditos AS cred JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE cred.proveedor = catprov.id AND catprov.token_cat_proveedores = ? 
                                   AND catprov.administrador = emp.id AND emp.id = empuser.empresa AND emp.empresa_token = ? AND empuser.usuario = users.id AND users.usuario_token = ? 
                                   AND users.empleado = pers.id", [$vProv->token_cat_proveedores, $usuario->empresa_token, $usuario->user_token]);

                foreach ($queryAsignCreditos as $vCred) {
                  $diasPago = $vCred->aceptacredito == TRUE ? 86400 * $vCred->diasPago : 0;
                  $token_moneda = $vCred->aceptacredito == TRUE ? $vCred->token_monedas : "";
                  $fLimitePago = time() + $diasPago;

                  if ($vCred->aceptacredito == TRUE && $vCred->comienza == "cada.inicio.mes") $comienza_credito_text = "Cada inicio de mes";
                  if ($vCred->aceptacredito == TRUE && $vCred->comienza == "sistem.emite.orden.pago") $comienza_credito_text = "Se emite/envía orden de pago";
                  if ($vCred->aceptacredito == TRUE && $vCred->comienza == "serecibe.facturadel.proveedor") $comienza_credito_text = "Se recibe factura del proveedor";
                  if ($vCred->aceptacredito == TRUE && $vCred->comienza == "producto.sale.bodegas.proveedor") $comienza_credito_text = "El producto salga de las bodegas del proveedor";
                  if ($vCred->aceptacredito == TRUE && $vCred->comienza == "producto.recibido.nuestras.bodegas") $comienza_credito_text = "El producto es recibido en nuestras bodegas";

                  $creditosEach = array(
                    "token_creditos" => $vCred->token_creditos,
                    "acepta" => $vCred->aceptacredito == TRUE ? true : false,
                    "codigo_moneda" => $vCred->codigo,
                    "token_moneda" => $token_moneda,
                    "moneda" => $vCred->aceptacredito == TRUE ? $vCred->moneda : "",
                    "limite" => $vCred->aceptacredito == TRUE ? $vCred->limite : "",
                    "dias" => $vCred->aceptacredito == TRUE ? $vCred->diasPago : "",
                    "fechalimite" => $vCred->aceptacredito == TRUE ? gmdate('Y-m-d H:i:s', $fLimitePago) : "",
                    "comienza" => $vCred->aceptacredito == TRUE ? $vCred->comienza : "",
                    "comienza_credito_text" => $comienza_credito_text,
                  );
                  $proveedor_creditos[] = $creditosEach;
                }
              }
              //forma de pago
              $buscaFPagoProveedor = DB::table("teci_forma_pago AS fpago")
                ->join("eegr_catalogo_proveedores AS catprov", "fpago.id", "=", "catprov.forma_pago")
                ->where(["catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();

              if (count($buscaFPagoProveedor) == 1) {
                foreach ($buscaFPagoProveedor as $vFPago) {
                  if ($vProv->tipo_referencia_pago != NULL) {
                    if ($vProv->tipo_referencia_pago == 'ci') {
                      $tipo_referencia_pago_code = 'clabeInterbancaria';
                      $tipo_referencia_pago_text = 'Por clabe interbancaria';
                    } else if ($vProv->tipo_referencia_pago == 'co') {
                      $tipo_referencia_pago_code = 'convenio';
                      $tipo_referencia_pago_text = 'Por convenio';
                    } else if ($vProv->tipo_referencia_pago == 'lc') {
                      $tipo_referencia_pago_code = 'lineaCaptura';
                      $tipo_referencia_pago_text = 'Por linea de captura';
                    }
                  } else {
                    $tipo_referencia_pago_code = '';
                    $tipo_referencia_pago_text = '';
                  }

                  $docs_est_cuenta = array();
                  $selectEstadoCuentaDoc = DB::table("sos_documentos AS docs")
                    ->join("eegr_catalogo_proveedores AS catprov", "docs.proveedor", "=", "catprov.id")
                    ->where([
                      "docs.tipo_documento" => "ecue",
                      "docs.status_documento" => TRUE,
                      "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores
                    ])->get();
                  if (count($selectEstadoCuentaDoc) > 0) {
                    foreach ($selectEstadoCuentaDoc as $vDoc) {
                      $rowDocs = array(
                        "doc_token_" => $vDoc->token_documento,
                        "doc_name_" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                        "doc_url_" => "https://downloads.sos-mexico.com.mx/proveedores/" . $vDoc->token_documento,
                      );
                      $docs_est_cuenta[] = $rowDocs;
                    }
                  }

                  $forma_pago = array(
                    "token_formapago" => $vFPago->token_formapago,
                    "validateclint" => $vFPago->token_formapago == "RkxGMTRidG44ZWJJYVh0dUlDK1o4Zz09OjoxMjM0NTY3ODEyMzQ1Njc4" ? "transferencia" : "noneView",
                    "clabe_interbancaria" => $vFPago->clabe_interbancaria,
                    "forma_pago_concept" => $vFPago->clave . ' - ' . $vFPago->forma,
                    "tipo_referencia_pago_code" => $tipo_referencia_pago_code,
                    "tipo_referencia_pago_text" => $tipo_referencia_pago_text,
                    "docs_est_cuenta" => $docs_est_cuenta,
                  );
                  $proveedor_formaPago[] = $forma_pago;
                }
              }

              //ubicacion
              $listaUbicacion = DB::table("eegr_catalogo_proveedores AS catprov")
                ->join("teci_direcciones AS ubica", "catprov.id", "ubica.proveedor")
                ->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
                ->where(["catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();
              if (count($listaUbicacion) > 0) {
                foreach ($listaUbicacion as $vUbica) {
                  //echo $vUbica->pais;
                  $status_ubica = DB::select("SELECT status FROM teci_direcciones WHERE token_direccion = ?", [$vUbica->token_direccion]);
                  $old_direccion_alias = $vUbica->alias != NULL ? $JwtAuth->desencriptar($vUbica->alias) : "";
                  $old_direccion_calle = $vUbica->calle != NULL ? $JwtAuth->desencriptar($vUbica->calle) : "";
                  $old_direccion_num_ext = $vUbica->num_ext != NULL ? $JwtAuth->desencriptar($vUbica->num_ext) : "";
                  $old_direccion_codigo_postal = $vUbica->codigo_postal != NULL ? $vUbica->codigo_postal : "";
                  $new_direccion_estado = $vUbica->estado_edit != NULL ? $JwtAuth->desencriptar($vUbica->estado_edit) : "";
                  $new_direccion_municipio = $vUbica->municipio_edit != NULL ? $JwtAuth->desencriptar($vUbica->municipio_edit) : "";
                  $new_direccion_c_postal = $vUbica->c_postal_edit != NULL ? $vUbica->c_postal_edit : "";
                  $new_direccion_colonia = $vUbica->colonia_edit != NULL ? $JwtAuth->desencriptar($vUbica->colonia_edit) : "";
                  $new_direccion_adicional = $vUbica->adicional != NULL ? $vUbica->adicional : "";
                  if ($vProv->nacionalidad == 118) {
                    $eachUbicacion = array(
                      "token_direccion" => $vUbica->token_direccion,
                      "tipo_direccion" => $vUbica->tipo_direccion,
                      "clase" => $JwtAuth->desencriptar($vUbica->clase),
                      "pais" => 118,
                      "old_direccion_alias" => $old_direccion_alias,
                      "old_direccion_calle" => $old_direccion_calle,
                      "old_direccion_num_ext" => $old_direccion_num_ext,
                      "old_direccion_codigo_postal" => $old_direccion_codigo_postal,
                      "estado_edit" => $new_direccion_estado,
                      "municipio_edit" => $new_direccion_municipio,
                      "c_postal_edit" => $new_direccion_c_postal,
                      "colonia_edit" => $new_direccion_colonia,
                      "adicional" => $new_direccion_adicional
                    );
                  } else {
                    $eachUbicacion = array(
                      "token_direccion" => $vUbica->token_direccion,
                      "tipo_direccion" => $vUbica->tipo_direccion,
                      "clase" => $JwtAuth->desencriptar($vUbica->clase),
                      "pais" => $vUbica->pais,
                      "cod_postalext" => $JwtAuth->desencriptar($vUbica->cod_postalext),
                    );
                  }
                  if ($status_ubica[0]->status == TRUE) {
                    $proveedor_ubicacion[] = $eachUbicacion;
                  } else {
                    $proveedor_ubicacionDel[] = $eachUbicacion;
                  }
                }
              }

              $proveedor_receptFacturaConcept = $vProv->receptFactura == TRUE ? "Antes" : "Despues";
              $proveedor_receptFacturaBool = $vProv->receptFactura == TRUE ? true : false;
              $proveedor_recibeArtPagoConcept = $vProv->classRecibeArtPago == TRUE ? "Antes" : "Despues";
              $proveedor_recibeArtPagoBool = $vProv->classRecibeArtPago == TRUE ? true : false;
            }

            $row = array(
              "requisicion_tkn" => $requisicion_tkn,
              "requisicion_folio" => $requisicion_folio,
              "requisicion_proyecto" => $requisicion_proyecto,
              "requisicion_fecha_registro" => $requisicion_fecha_registro,
              "requisicion_empresa" => $requisicion_empresa,
              "requisicion_pers_requiere" => $requisicion_pers_requiere,
              "token_detalle_requisicion" => $requi_inside_tkn,
              "requi_necesidad_tipo" => $requi_inside_neces_tipo,
              "requi_necesidad_concepto" => $requi_inside_neces_concepto,
              "requi_necesidad_caracteristicas_list" => $requi_inside_neces_caract_list,
              "requi_necesidad_caracteristicas_other" => $requi_inside_neces_caract_other,
              "requi_necesidad_cantidad" => $requi_inside_neces_cantidad,
              "requi_necesidad_umed_tkn" => $requi_inside_neces_umed_tkn,
              "requi_necesidad_umed_name" => $requi_inside_neces_umed_name,
              "requi_necesidad_marca" => $requi_inside_neces_marca,
              "requi_necesidad_autorizacion" => $requi_inside_neces_autorizacion,
              "requi_necesidad_auth_mensaje" => $requi_inside_neces_auth_mensaje,
              "requi_necesidad_auth_pers" => $requi_inside_neces_auth_pers,
              "requi_necesidad_docs" => $requi_inside_neces_docs,
              "cotizacion_tkn" => $vCot->token_cotizacion,
              "cotizacion_folio" => "COT-" . $JwtAuth->generarFolio($vCot->coti_folio),
              "cotizacion_fecha" => gmdate('Y-m-d H:i:s', $vCot->coti_fecha_sistema),
              "cotizacion_persona" => $persona_cotiza,
              "cotizacion_auth_mensaje" => $coti_inside_auth_mensaje,
              "cotizacion_pdf" => "https://downloads.sos-mexico.com.mx/cotizacion_pdf/" . $JwtAuth->generarFolio($vCot->coti_folio),
              "coti_token_detalle_cotizacion" => $vCot->token_detalle_cotizacion,
              "coti_token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza,
              "coti_inside_especificaciones" => $JwtAuth->desencriptar($vCot->coti_especificaciones),
              "coti_inside_cantidad" => $vCot->coti_cantidad,
              //token_monedas
              "coti_inside_moneda_token" => $vCot->token_monedas,
              "coti_inside_moneda_codigo" => $vCot->codigo,
              "coti_inside_moneda_name" => $vCot->moneda,
              "coti_inside_moneda_decimales" => $vCot->decimales,
              "coti_inside_precio" => $coti_precio,
              "coti_inside_tipo_cambio" => $vCot->coti_tipo_cambio,
              "coti_inside_conversion" => $coti_conversion,
              "coti_inside_calidad" => $JwtAuth->desencriptar($vCot->coti_calidad),
              "coti_inside_servicio" => $JwtAuth->desencriptar($vCot->coti_servicio),
              "coti_inside_entrega_tipo_extend" => $coti_entrega_tipo_extend,
              "coti_inside_entrega_tiempo" => $JwtAuth->desencriptar($vCot->coti_entrega_tiempo),
              "coti_inside_descuento" => $JwtAuth->desencriptar($vCot->coti_descuento),
              "coti_inside_retenciones" => $JwtAuth->desencriptar($vCot->coti_retenciones),
              "coti_inside_traslados" => $JwtAuth->desencriptar($vCot->coti_traslados),
              "coti_inside_credito_otorga" => $coti_credito_otorga,
              "coti_inside_credito_time" => $coti_credito_time,
              "coti_inside_garantia" => $JwtAuth->desencriptar($vCot->coti_garantia),
              //coti_unidad_medida
              "coti_inside_umed_token" => $vCot->token_unidad_medida,
              "coti_inside_umed_name" => $vCot->unidad_medida . " " . $vCot->sat_clave,
              "coti_inside_umed_representa" => $vCot->representa,
              //coti_forma_pago
              "coti_inside_fpay_token" => $coti_inside_fpay_token,
              "coti_inside_fpay_forma" => $coti_inside_fpay_forma,
              //coti_metodo_pago
              "coti_inside_mpay_token" => $coti_mpay_token,
              "coti_inside_mpay_metodo" => $coti_mpay_metodo,
              "coti_inside_valoracion" => $JwtAuth->desencriptar($vCot->coti_valoracion),
              "coti_inside_contacto_proveedor" => $vCot->contacto_proveedor == TRUE ? true : false,
              "coti_inside_contacto_proveedor_fecha" => $vCot->contacto_proveedor == TRUE ? gmdate('Y-m-d H:i:s', $vCot->contacto_proveedor_fecha) : "",
              //proveedor
              "coti_inside_proveedor_tkn" => $proveedor_tkn,
              "coti_inside_proveedor_folio" => $proveedor_folio,
              "coti_inside_proveedor_name" => $proveedor_name,
              "coti_inside_proveedor_clasificacion" => $proveedor_clasificacion,
              "coti_inside_proveedor_nacionalidad" => $proveedor_nacionalidad,
              "coti_inside_proveedor_pais_token" => $pais_token,
              "coti_inside_proveedor_pais_name" => $pais_name,
              "coti_inside_proveedor_identificador" => $proveedor_identif,
              "coti_inside_proveedor_redes_sociales" => $proveedor_redes,
              "coti_inside_proveedor_lista_precios" => $proveedor_lista_precios,
              "coti_inside_proveedor_personal" => $proveedor_personalContacto,
              "coti_inside_proveedor_tiene_docs_fiscales" => $proveedor_tiene_docs_fiscales,
              "coti_inside_proveedor_docs_sit_fiscal" => $proveedor_docs_sit_fiscal,
              "coti_inside_proveedor_docs_obl_fiscal" => $proveedor_docs_obl_fiscal,
              "coti_inside_proveedor_docs_contratos" => $proveedor_docs_contratos,
              "coti_inside_proveedor_docs_anexos" => $proveedor_docs_anexos,
              "coti_inside_proveedor_noDocsFiscales" => $proveedor_no_cuenta_fiscales,
              "coti_inside_proveedor_creditos" => $proveedor_creditos,
              "coti_inside_proveedor_forma_pago" => $proveedor_formaPago,
              "coti_inside_proveedor_ubicacion" => $proveedor_ubicacion,
              "coti_inside_proveedor_ubicacionDel" => $proveedor_ubicacionDel,
              "coti_inside_proveedor_receptFacturaConcept" => $proveedor_receptFacturaConcept,
              "coti_inside_proveedor_receptFacturaBool" => $proveedor_receptFacturaBool,
              "coti_inside_proveedor_classRecibeArtPagoConcept" => $proveedor_recibeArtPagoConcept,
              "coti_inside_proveedor_classRecibeArtPagoBool" => $proveedor_recibeArtPagoBool,
              //xml carga 
              "receptFactura" => false,
              "imagenEvidenciaXMl" => null,
              "imagenNameEvidenciaXMl" => null,
              "imagenEvidenciaPdf" => null,
              "imagenNameEvidenciaPdf" => "",
              "resultXml" => "",
              "validXmlversion" => "",
              "validXmlserie" => "",
              "validXmlFolio" => "",
              "validXmlFecha" => "",
              "validXmlSello" => "",
              "validXmlformaPago" => "",
              "validXmlnoCertificado" => "",
              "validXmlcertificado" => "",
              "validXmlSubTotal" => "",
              "validXmlMoneda" => "",
              "validXmltipoCambio" => "",
              "validXmlTotal" => "",
              "validXmlconfirmacion" => "",
              "validXmlTipoDeComprobante" => "",
              "validXmlMetodoPago" => "",
              "validXmlLugarExpedicion" => "",
              "validXmltipoRelacion" => "",
              "validXmluuid" => "",
              "validXmlemisorRfc" => "",
              "validXmlemisorNombre" => "",
              "validXmlemisorRegimenFiscal" => "",
              "validXmlreceptorRfc" => "",
              "validXmlreceptorUsoCFDI" => "",
              "validXmlconceptos" => [],
              "selectvalidatexmlArticulos" => false,
              "totalimpuestosretenidos" => "",
              "totalimpuestostrasladados" => "",
              "validXmlimpuestosretenidosArray" => [],
              "validXmlimpuestostrasladadosArray" => [],
              "validXmlcompluuidComplemento" => "",
              "validXmlcomplfechaTimbrado" => "",
              "validXmlcomplRfcProvCertif" => "",
              "validXmlcomplSelloCFD" => "",
              "validXmlcomplNoCertificadoSAT" => "",
              "validXmlcomplSelloSAT" => "",
              "arrayErroresComprobante" => [],
              "arrayErroresEmisor" => [],
              "arrayErroresReceptor" => [],
              "arrayErroresCfdiRelacionados" => [],
              "arrayErroresConceptos" => [],
              "arrayErroresImpuestos" => [],
              "arrayErroresComplemento" => [],
            );
            $arrayCotizaciones[] = $row;
          }
          $dataMensaje = array("status" => "success", "code" => 200, "lista_cotizaciones" => $arrayCotizaciones);
        } else {
          $dataMensaje = array("status" => "error", "code" => 404, "message" => "No hay cotizaciones autorizadas");
        }
      }
    } else {
      $dataMensaje = array("status" => "error", "code" => 404, "message" => "Los informacion que intenta registrar no es valida");
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cotizacionesContactoProvBuyPrc(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayCotizaciones = array();

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
          $listaCotAuth = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
            ->join("eegr_compras_cotizacion_detalle AS cotDet", "deskCot.detalle_cotizacion", "=", "cotDet.id")
            ->join("eegr_catalogo_proveedores AS catprov", "deskCot.coti_proveedor", "=", "catprov.id")
            //->join("teci_catalogo_monedas AS mon","deskCot.coti_moneda","=","mon.id")
            //->join("teci_unidad_medida AS umed", "deskCot.coti_unidad_medida", "=", "umed.id")
            ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
            ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
            ->where([
              "deskCot.coti_desc_autorizacion" => TRUE, 
              "deskCot.contacto_proveedor" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token
              ])->get();
        } else {
          $listaCotAuth = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
            ->join("eegr_compras_cotizacion_detalle AS cotDet", "deskCot.detalle_cotizacion", "=", "cotDet.id")
            ->join("eegr_catalogo_proveedores AS catprov", "deskCot.coti_proveedor", "=", "catprov.id")
            //->join("teci_catalogo_monedas AS mon","deskCot.coti_moneda","=","mon.id")
            ->join("teci_unidad_medida AS umed", "deskCot.coti_unidad_medida", "=", "umed.id")
            ->join("eegr_compras_cotizacion AS cotMain", "cotDet.cotizacion", "=", "cotMain.id")
            ->join("main_empresas AS emp", "cotMain.empresa", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "cotMain.usuario_cotizador", "=", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
            ->where([
              "deskCot.coti_desc_autorizacion" => TRUE, 
              "deskCot.contacto_proveedor" => TRUE,
              "emp.empresa_token" => $usuario->empresa_token, 
              "users.usuario_token" => $usuario->user_token
            ])->get();
        }

        if (count($listaCotAuth) > 0) {
          foreach ($listaCotAuth as $vCot) {
            //da_te_default_timezone_set('America/Mexico_City');
            //requisicion
            $requisicion_tkn = "";
            $requisicion_folio = "";
            $requisicion_proyecto = "";
            $requisicion_fecha_registro = "";
            $requisicion_empresa = "";
            $requisicion_pers_requiere = "";
            $requi_inside_tkn = "";
            $requi_inside_neces_tipo = "";
            $requi_inside_neces_concepto = "";
            $requi_inside_neces_caract_list = array();
            $requi_inside_neces_caract_other = null;
            $requi_inside_neces_cantidad = 0;
            $requi_inside_neces_umed_tkn = "";
            $requi_inside_neces_umed_name = "";
            $requi_inside_neces_marca = "";
            $requi_inside_neces_autorizacion = false;
            $requi_inside_neces_auth_pers = "";
            $requi_inside_neces_auth_mensaje = "";
            $requi_inside_neces_docs = array();

            $queryRequi = DB::table("eegr_compras_requisicion_detalle AS reqDet")
              //->join("teci_unidad_medida AS reqMed", "reqDet.medida_unidad", "=", "reqMed.id")
              ->join("eegr_compras_requisicion AS requi", "reqDet.requisicion", "=", "requi.id")
              ->join("eegr_compras_cotizacion AS coti", "requi.id", "=", "coti.requisicion")
              ->join("eegr_compras_cotizacion_detalle AS cotDet", "coti.id", "=", "cotDet.cotizacion")
              ->join("vhum_empleados_catalogo AS pers", "reqDet.des_autoriza_user", "=", "pers.id")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->whereColumn("reqDet.id", "=", "cotDet.detalle_requisicion")
              ->where(["reqDet.des_autorizacion" => "A", "coti.token_cotizacion" => $vCot->token_cotizacion, "cotDet.token_detalle_cotizacion" => $vCot->token_detalle_cotizacion])->get();

            if (count($queryRequi) == 1) {
              foreach ($queryRequi as $vRequi) {
                $requisicion_tkn = $vRequi->token_requisicion;
                $requi_inside_tkn = $vRequi->token_detalle_requisicion;
                $requisicion_folio = "REQ-" . $JwtAuth->generarFolio($vRequi->folio);
                $requisicion_proyecto = $JwtAuth->desencriptar($vRequi->proyecto);
                $requisicion_fecha_registro = gmdate('Y-m-d H:i:s', $vRequi->fecha);
                $queryEmpRequi = DB::table("sos_personas AS people")
                  ->join("main_empresas AS emp", "people.id", "=", "emp.persona")
                  ->join("eegr_compras_requisicion AS requi", "emp.id", "=", "requi.empresa")
                  ->where(["requi.token_requisicion" => $vRequi->token_requisicion])->get();
                $requisicion_empresa = $queryEmpRequi[0]->abrev_nombre;

                $queryPersRequi = DB::table("sos_personas AS people")
                  ->join("vhum_empleados_catalogo AS pers", "people.id", "=", "pers.empleado_name")
                  ->join("eegr_compras_requisicion AS requi", "pers.id", "=", "requi.usuario_requisita")
                  ->where(["requi.token_requisicion" => $vRequi->token_requisicion])->get();
                $requisicion_pers_requiere = $JwtAuth->desencriptarNombres($queryPersRequi[0]->paterno, $queryPersRequi[0]->materno, $queryPersRequi[0]->nombre);

                if ($vRequi->tipo_necesidad == "Merc") {
                  $requi_inside_neces_tipo = "Mercancia";
                }
                if ($vRequi->tipo_necesidad == "Gast") {
                  $requi_inside_neces_tipo = "Gastos";
                }
                if ($vRequi->tipo_necesidad == "Acti") {
                  $requi_inside_neces_tipo = "Activos";
                }
                if ($vRequi->tipo_necesidad == "Mixt") {
                  $requi_inside_neces_tipo = "Mixto";
                }

                $requi_inside_neces_concepto = $JwtAuth->desencriptar($vRequi->necesidad);

                $list_num_caract = 1;
                $selectDetReqCaractList = DB::table("eegr_compras_requisicion_detalle_caract AS reqCaract")
                  ->join("eegr_compras_requisicion AS reqMain", "reqCaract.requisicion_main", "=", "reqMain.id")
                  ->join("eegr_compras_requisicion_detalle AS reqDet", "reqCaract.requisicion_detalle", "=", "reqDet.id")
                  ->where(["reqMain.token_requisicion" => $vRequi->token_requisicion, "reqDet.token_detalle_requisicion" => $vRequi->token_detalle_requisicion])->get();
                //echo count($selectDetReqCaractList);
                foreach ($selectDetReqCaractList as $vCaract) {
                  $descif_clave = $JwtAuth->desencriptar($vCaract->clave);
                  $db_valor = $JwtAuth->desencriptar($vCaract->valor);
                  $fisrt_valor = $descif_clave == "Precio" ? "$" . number_format($db_valor, $vCot->e_moneda_decimales, '.', ',') : $db_valor;
                  $descif_valor = $list_num_caract > count($selectDetReqCaractList) ? $fisrt_valor . "," : $fisrt_valor;
                  $row_CaractList = array(
                    "token_caract" => $vCaract->token_caract,
                    "num_list" => $list_num_caract,
                    "clave" => $descif_clave,
                    "valorFront" => $descif_valor,
                    "valorBack" => $db_valor
                  );
                  $requi_inside_neces_caract_list[] = $row_CaractList;
                  ++$list_num_caract;
                }

                $requi_inside_neces_caract_other = $vRequi->caracteristicas_extend != NULL ? $JwtAuth->desencriptar($vRequi->caracteristicas_extend) : null;
                $requi_inside_neces_cantidad = $vRequi->cantidad;
                //$requi_inside_neces_umed_tkn = $vRequi->token_unidad_medida;
                $requi_inside_neces_umed_name = $vRequi->medida_unidad;
                $requi_inside_neces_marca = $vRequi->marca != NULL ? $JwtAuth->desencriptar($vRequi->marca) : "no hay marca referida";
                $requi_inside_neces_autorizacion = $vRequi->des_autorizacion == TRUE ? true : false;
                $requi_inside_neces_auth_pers = $JwtAuth->desencriptarNombres($vRequi->paterno, $vRequi->materno, $vRequi->nombre);
                $requi_inside_neces_auth_mensaje = "Requisición autorizada por " . $requi_inside_neces_auth_pers . " (" . gmdate('Y-m-d H:i:s', $vRequi->des_fecha_autorizacion) . ")";

                $selectIdEvid = DB::select("SELECT evd.token_documento,evd.tipo_documento,evd.nombre_documento 
                                        FROM sos_documentos AS evd JOIN eegr_compras_requisicion AS reqMain JOIN eegr_compras_requisicion_detalle AS reqDet 
                                        WHERE evd.requisicion = reqMain.id AND reqMain.token_requisicion = ?
                                        AND evd.detalle_requisicion = reqDet.id AND reqDet.token_detalle_requisicion = ?
                                        AND evd.status_documento = TRUE", [$requisicion_tkn, $vRequi->token_detalle_requisicion]);

                if (count($selectIdEvid) > 0) {
                  $filepath = $vCot->root_tkn . "/0002-cpp/compras/requisiciones/" . $requisicion_folio;
                  foreach ($selectIdEvid as $vDoc) {
                    $each = array(
                      "token_documento" => $vDoc->token_documento,
                      "tipo_documento" => $vDoc->tipo_documento,
                      "nombre_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                      "url" => "https://downloads.sos-mexico.com.mx/compras/requisiciones/" . $requisicion_folio . "/" . $vDoc->token_documento,
                    );
                    $requi_inside_neces_docs[] = $each;
                  }
                }
              }
            }

            //monedas
            $queryMonedas = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
              ->join("teci_catalogo_monedas AS mon", "deskCot.coti_moneda", "=", "mon.id")
              ->where(["deskCot.token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza])->get();

            //$cotti_moneda_token = $queryMonedas[0]->token_monedas;
            $cotti_moneda_codigo = $vCot->coti_moneda;
            $cotti_moneda_decimales = $JwtAuth->getMonedaAPI($vCot->coti_moneda);
            //$cotti_moneda_name = $queryMonedas[0]->moneda;
            //$cotti_moneda_decimales = $queryMonedas[0]->decimales;

            $persona_cotiza = "";
            $cotUser = DB::table("eegr_compras_cotizacion AS cotList")
              ->join("vhum_empleados_catalogo AS pers", "cotList.usuario_cotizador", "=", "pers.id")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where(["cotList.token_cotizacion" => $vCot->token_cotizacion])->get();

            foreach ($cotUser as $cUser) {
              $persona_cotiza = $JwtAuth->desencriptarNombres($cUser->paterno, $cUser->materno, $cUser->nombre);
            }

            if ($vCot->coti_entrega_tipo == "domi") {
              $coti_entrega_tipo_extend = "Domicilio";
            } else if ($vCot->coti_entrega_tipo == "stre") {
              $coti_entrega_tipo_extend = "Tienda";
            } else if ($vCot->coti_entrega_tipo == "ofna") {
              $coti_entrega_tipo_extend = "Oficina";
            } else if ($vCot->coti_entrega_tipo == "dest") {
              $coti_entrega_tipo_extend = "Destino";
            } else if ($vCot->coti_entrega_tipo == "cntr") {
              $coti_entrega_tipo_extend = "Contra reembolso";
            }

            $coti_credito_otorga = $vCot->coti_credito_otorga == TRUE ? true : false;
            $coti_credito_time = $vCot->coti_credito_otorga == TRUE ? $JwtAuth->desencriptar($vCot->coti_credito_time) : null;
            $coti_precio = number_format($vCot->coti_precio, $vCot->e_moneda_decimales, '.', ',');

            if ($vCot->e_moneda_code == $cotti_moneda_codigo) {
              $coti_conversion = number_format($vCot->coti_precio, $vCot->e_moneda_decimales, '.', ',');
            } else {
              $convet = $vCot->coti_precio * $vCot->coti_tipo_cambio;
              $coti_conversion = number_format($convet, $cotti_moneda_decimales, '.', ',');
            }

            //forma de pago registrada desde cotización 
            $queryPayMent = DB::table("eegr_compras_cotizacion_detalle_descripcion AS deskCot")
              ->join("teci_forma_pago AS fpay", "deskCot.coti_forma_pago", "=", "fpay.id")
              ->where(["deskCot.token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza])->get();
            $coti_inside_fpay_token = $queryPayMent[0]->token_formapago;
            $coti_inside_fpay_forma = $queryPayMent[0]->clave . " - " . $queryPayMent[0]->forma;

            $coti_desc_pers_autoriza = "";
            if ($vCot->coti_desc_autorizacion == TRUE) {
              $persAuthCoti = DB::table("sos_personas AS people")
                ->join("vhum_empleados_catalogo AS persAuth", "people.id", "=", "persAuth.empleado_name")
                ->join("eegr_compras_cotizacion_detalle_descripcion AS cotDesk", "persAuth.id", "=", "cotDesk.coti_desc_pers_autoriza")
                ->where(["cotDesk.token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza])->get();
              $coti_desc_pers_autoriza = $JwtAuth->desencriptarNombres($persAuthCoti[0]->paterno, $persAuthCoti[0]->materno, $persAuthCoti[0]->nombre);
            }
            $coti_inside_auth_mensaje = "Cotización autorizada por " . $coti_desc_pers_autoriza . " (" . gmdate('Y-m-d H:i:s', $vCot->coti_desc_fecha_autorizacion) . ")";
            //echo $coti_inside_neces_auth_mensaje;

            $row = array(
              "requisicion_tkn" => $requisicion_tkn,
              "requisicion_folio" => $requisicion_folio,
              "requisicion_proyecto" => $requisicion_proyecto,
              "requisicion_fecha_registro" => $requisicion_fecha_registro,
              "requisicion_empresa" => $requisicion_empresa,
              "requisicion_pers_requiere" => $requisicion_pers_requiere,
              "token_detalle_requisicion" => $requi_inside_tkn,
              "requi_necesidad_tipo" => $requi_inside_neces_tipo,
              "requi_necesidad_concepto" => $requi_inside_neces_concepto,
              "requi_necesidad_caracteristicas_list" => $requi_inside_neces_caract_list,
              "requi_necesidad_caracteristicas_other" => $requi_inside_neces_caract_other,
              "requi_necesidad_cantidad" => $requi_inside_neces_cantidad,
              "requi_necesidad_umed_tkn" => $requi_inside_neces_umed_tkn,
              "requi_necesidad_umed_name" => $requi_inside_neces_umed_name,
              "requi_necesidad_marca" => $requi_inside_neces_marca,
              "requi_necesidad_autorizacion" => $requi_inside_neces_autorizacion,
              "requi_necesidad_auth_mensaje" => $requi_inside_neces_auth_mensaje,
              "requi_necesidad_auth_pers" => $requi_inside_neces_auth_pers,
              "requi_necesidad_docs" => $requi_inside_neces_docs,
              "cotizacion_tkn" => $vCot->token_cotizacion,
              "cotizacion_folio" => "COT-" . $JwtAuth->generarFolio($vCot->coti_folio),
              "cotizacion_fecha" => gmdate('Y-m-d H:i:s', $vCot->coti_fecha_sistema),
              "cotizacion_persona" => $persona_cotiza,
              "cotizacion_auth_mensaje" => $coti_inside_auth_mensaje,
              "cotizacion_pdf" => "https://downloads.sos-mexico.com.mx/cotizacion_pdf/" . $requisicion_tkn . "/" . $vCot->coti_folio,
              "coti_token_detalle_cotizacion" => $vCot->token_detalle_cotizacion,
              "coti_token_desc_detalle_cotiza" => $vCot->token_desc_detalle_cotiza,
              "coti_inside_especificaciones" => $JwtAuth->desencriptar($vCot->coti_especificaciones),
              "coti_inside_cantidad" => $vCot->coti_cantidad,
              //token_monedas
              //"coti_inside_moneda_token" => $vCot->token_monedas,
              "coti_inside_moneda_codigo" => $vCot->e_moneda_code,
              //"coti_inside_moneda_name" => $vCot->moneda,
              "coti_inside_moneda_decimales" => $vCot->e_moneda_decimales,
              "coti_inside_precio" => $coti_precio,
              "coti_inside_tipo_cambio" => $vCot->coti_tipo_cambio,
              "coti_inside_conversion" => $coti_conversion,
              "coti_inside_calidad" => $JwtAuth->desencriptar($vCot->coti_calidad),
              "coti_inside_servicio" => $JwtAuth->desencriptar($vCot->coti_servicio),
              "coti_inside_entrega_tipo_extend" => $coti_entrega_tipo_extend,
              "coti_inside_entrega_tiempo" => $JwtAuth->desencriptar($vCot->coti_entrega_tiempo),
              "coti_inside_descuento" => $JwtAuth->desencriptar($vCot->coti_descuento),
              "coti_inside_retenciones" => $JwtAuth->desencriptar($vCot->coti_retenciones),
              "coti_inside_traslados" => $JwtAuth->desencriptar($vCot->coti_traslados),
              "coti_inside_credito_otorga" => $coti_credito_otorga,
              "coti_inside_credito_time" => $coti_credito_time,
              "coti_inside_garantia" => $JwtAuth->desencriptar($vCot->coti_garantia),
              //coti_unidad_medida
              //"coti_inside_umed_token" => $vCot->token_unidad_medida,
              "coti_inside_umed_name" => $vCot->coti_unidad_medida,
              //"coti_inside_umed_representa" => $vCot->representa,
              //coti_forma_pago
              "coti_inside_fpay_token" => $coti_inside_fpay_token,
              "coti_inside_fpay_forma" => $coti_inside_fpay_forma,
              //coti_metodo_pago  
              "coti_inside_valoracion" => $JwtAuth->desencriptar($vCot->coti_valoracion),
              "coti_inside_contacto_proveedor_fecha" => $vCot->contacto_proveedor == TRUE ? gmdate('Y-m-d H:i:s', $vCot->contacto_proveedor_fecha) : "",
              //proveedor
              "token_cat_proveedores" => $vCot->token_cat_proveedores,
              "proveedor_data" => [],
              "selected" => false,
            );
            $arrayCotizaciones[] = $row;
          }
          $dataMensaje = array("status" => "success", "code" => 200, "lista_cotizaciones" => $arrayCotizaciones);
        } else {
          $dataMensaje = array("status" => "success", "code" => 200, "message" => "No hay cotizaciones autorizadas");
        }
      }
    } else {
      $dataMensaje = array("status" => "error", "code" => 404, "message" => "Los informacion que intenta registrar no es valida");
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
