<?php

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

class MAIN_ComisionesController extends Controller{
  public function comisionListaGeneral(Request $request){
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_comisiones = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
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

        $selectComission = DB::table("terc_comisiones_main AS comi")
          ->join("main_empresas AS emp", "comi.empresa", "emp.id")
          ->where(["comi.status" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
          ->orderBy("comi.folio_comision", "DESC")->get();

        foreach ($selectComission as $vComi) {
          //da_te_default_timezone_set($vComi->zona_horaria);
          $expideComission = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
          foreach ($expideComission as $vExpide) {
            $user_expide = $JwtAuth->desencriptar($vExpide->paterno) . " " .
              $JwtAuth->desencriptar($vExpide->materno) . " " . $JwtAuth->desencriptar($vExpide->nombre);
          }

          $comisionadoQuery = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empleados_catalogo AS pers", "comi.usuario_comision", "pers.id")
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

          $sql_califica_egresos = $vComi->egresos == TRUE ? true : false;
          $sql_califica_vhum = $vComi->valor_humano == TRUE ? true : false;

          $sql_concluida_fecha = null;
          if ($vComi->concluida == TRUE && $vComi->concluida_fecha != NULL) $sql_concluida_fecha = gmdate('Y-m-d H:i:s', $vComi->concluida_fecha);

          $comportamiento_array = array();
          $comision_relaciones = DB::table("sos_reembolsos_comisiones_rel AS reem_comi")
            ->join("terc_comisiones_main AS comi", "reem_comi.comision", "comi.id")
            ->join("terc_reembolso_main AS reem_main", "reem_comi.reembolso_main", "reem_main.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();

          foreach ($comision_relaciones as $vComp) {
            $date_solicitud = gmdate('Y-m-d H:i:s', $vComp->fecha_sistema);
            $reem_fol = 'REEM-' . $JwtAuth->generarFolio($vComp->folio_reem);
            $folio_reem = $vComp->post_folio_reem == NULL ? $reem_fol : $reem_fol . '-' . $vComp->post_folio_reem;

            $reem_fmin = 'reem_' . $JwtAuth->generar($vComp->folio_reem);
            $fmin_reem = $vComp->post_folio_reem == NULL ? $reem_fmin : $reem_fmin . '_' . $vComp->post_folio_reem;

            $selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
              ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
              ->join("sos_personas AS people", "emp.persona", "=", "people.id")
              ->where(["reem_main.token_reem" => $vComp->token_reem])->get();

            foreach ($selectNameEmpEmi as $vEmisor) {
              $name_emisor = $vEmisor->abrev_nombre;
              $rfc_gen_emi = $vEmisor->rfc_generico;
              $rfc_emp_emi = $vEmisor->rfc != NULL ? $JwtAuth->desencriptar($vEmisor->rfc) : "---";
              $taxid_emp_emi = $vEmisor->tax_id != NULL ? $JwtAuth->desencriptar($vEmisor->tax_id) : "---";
            }

            $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
              ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
              ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where(["reem_main.token_reem" => $vComp->token_reem])->get();

            foreach ($selectPersEmpEmi as $vPemi) {
              $nombreEmiPers = $JwtAuth->desencriptarNombres($vPemi->paterno, $vPemi->materno, $vPemi->nombre);
            }

            $nivel_ordp_fase1 = array();
            $nivel_ordp_fase2 = array();
            $nivel_ordp_fase3 = array();

            $listOrdenes = DB::select(
              "SELECT orden.* FROM fnzs_pagos_orden AS orden JOIN terc_reembolso_main AS reem_main 
                            JOIN main_empresas AS emp WHERE orden.status_ordenPago = TRUE AND orden.reembolso_main = reem_main.id
                            AND reem_main.token_reem = ? AND orden.empresa = emp.id AND emp.empresa_token = ?",
              [$vComp->token_reem, $usuario->empresa_token]
            );

            foreach ($listOrdenes as $rOrdPag) {
              if ($rOrdPag->autorizacion_pay == TRUE) {
                if ($rOrdPag->orden_terminada_bool == TRUE) {
                  $ordp_fase3_row = array(
                    "status_pay_bool" => true,
                    "status_pay_date" => gmdate('Y-m-d H:i:s', $rOrdPag->orden_terminada_fecha),
                  );
                  $nivel_ordp_fase3[] = $ordp_fase3_row;
                }

                $ordp_fase2_row = array(
                  "autorizacion_pay" => true,
                  "fecha_autorizacion_pay" => gmdate('Y-m-d H:i:s', $rOrdPag->fecha_autorizacion_pay),
                  "nivel_ordp_fase3" => $nivel_ordp_fase3,
                );
                $nivel_ordp_fase2[] = $ordp_fase2_row;
              }

              $row_ordenPay = array(
                "folio_ordenPago" => "ORDP-" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago),
                "fecha_registro" => gmdate('Y-m-d H:i:s', $rOrdPag->fecha_sistema_ordenp),
                "nivel_ordp_fase2" => $nivel_ordp_fase2,
              );
              $nivel_ordp_fase1[] = $row_ordenPay;
            }

            $arraySoliList = array();
            $soli_reem = DB::table("terc_reembolso_solicitud AS reem_soli")
              ->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
              ->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
              ->where(["reem_main.token_reem" => $vComp->token_reem])
              ->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

            foreach ($soli_reem as $vSoliR) {
              $soli_r_prov = DB::table("terc_reembolso_solicitud AS reem_soli")
                ->join("terc_reembolso_main AS rmain", "reem_soli.reembolso_main", "=", "rmain.id")
                ->join("eegr_catalogo_proveedores AS cprov", "reem_soli.proveedor", "=", "cprov.id")
                ->join("sos_personas AS prov", "cprov.proveedor", "=", "prov.id")
                ->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
                ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem, "rmain.token_reem" => $vComp->token_reem])->get();
              //proveedor
              $name_prov = "";
              $rfc_generico_prov = "";
              $rfc_prov = "";
              $taxid_prov = "";
              if (count($soli_r_prov) > 0) {
                foreach ($soli_r_prov as $sr_prov) {
                  $name_prov = $sr_prov->nombre_extendido ? $JwtAuth->desencriptar($sr_prov->nombre_extendido) : ($sr_prov->denominacion_rs != '' ? $JwtAuth->desencriptar($sr_prov->denominacion_rs) : $JwtAuth->desencriptarNombres($sr_prov->paterno, $sr_prov->materno, $sr_prov->nombre));
                  $rfc_generico_prov = $sr_prov->rfc_generico;
                  $rfc_prov = $sr_prov->rfc != NULL ? $JwtAuth->desencriptar($sr_prov->rfc) : "---";
                  $taxid_prov = $sr_prov->tax_id != NULL ? $JwtAuth->desencriptar($sr_prov->tax_id) : "---";
                }
              }

              $autorizacion_egr = $vSoliR->autorizacion_egr != NULL ? $vSoliR->autorizacion_egr : null;

              $select_max_auth_egr = DB::select(
                "SELECT fecha_registro,autorizacion_egr,comentarios 
                                FROM terc_reembolso_autorizacion_egr WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_egr AS r_auth 
                                JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                                WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                                AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",
                [$vComp->token_reem, $vSoliR->token_solicitud_reem]
              );

              $fecha_registro_auth_egr = count($select_max_auth_egr) == 1 ? gmdate('Y-m-d H:i:s', $select_max_auth_egr[0]->fecha_registro) : '';
              $comments_auth_egr = count($select_max_auth_egr) == 1 ? $JwtAuth->desencriptar($select_max_auth_egr[0]->comentarios) : '';

              $nivel_dos_vhum = array();
              $nivel_dos_egre = array();

              if ($vSoliR->autorizacion_vh != NULL && $vSoliR->autorizacion_vh != "N") {
                $autorizacion_vh = $vSoliR->autorizacion_vh != NULL ? $vSoliR->autorizacion_vh : null;
                $select_list_auth_vh = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_vh,r_auth.comentarios 
                                    FROM terc_reembolso_autorizacion_vh AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                                    WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id 
                                    AND s_soli.token_solicitud_reem = ?", [$vComp->token_reem, $vSoliR->token_solicitud_reem]);
                $fecha_registro_auth_vh = gmdate('Y-m-d H:i:s', end($select_list_auth_vh)->fecha_registro);
                $comments_auth_vh = $JwtAuth->desencriptar(end($select_list_auth_vh)->comentarios);

                $n_dos_egre = array();
                if ($vSoliR->autorizacion_egr == "A") {
                  $n_dos_egre_row = array(
                    "autorizacion_egr" => $autorizacion_egr,
                    "fecha_registro_auth_egr" => $fecha_registro_auth_egr,
                    "comments_auth_egr" => $comments_auth_egr,
                    "nivel_ordp_fase1" => $nivel_ordp_fase1,
                  );
                } else {
                  $n_dos_egre_row = array(
                    "autorizacion_egr" => $autorizacion_egr,
                    "fecha_registro_auth_egr" => $fecha_registro_auth_egr,
                    "comments_auth_egr" => $comments_auth_egr,
                    "nivel_ordp_fase1" => [],
                  );
                }
                $n_dos_egre[] = $n_dos_egre_row;

                $nivel_dos_vhum_row = array(
                  "autorizacion_vh" => $autorizacion_vh,
                  "fecha_registro_auth_vh" => $fecha_registro_auth_vh,
                  "comments_auth_vh" => $comments_auth_vh,
                  "nivel_dos_egre" => $n_dos_egre,
                );
                $nivel_dos_vhum[] = $nivel_dos_vhum_row;
              } else {
                if ($vSoliR->autorizacion_egr == "A") {
                  $nivel_dos_egre_row = array(
                    "autorizacion_egr" => $autorizacion_egr,
                    "fecha_registro_auth_egr" => $fecha_registro_auth_egr,
                    "comments_auth_egr" => $comments_auth_egr,
                    "nivel_ordp_fase1" => $nivel_ordp_fase1,
                  );
                } else {
                  $nivel_dos_egre_row = array(
                    "autorizacion_egr" => $autorizacion_egr,
                    "fecha_registro_auth_egr" => $fecha_registro_auth_egr,
                    "comments_auth_egr" => $comments_auth_egr,
                    "nivel_ordp_fase1" => [],
                  );
                }
                $nivel_dos_egre[] = $nivel_dos_egre_row;
              }


              $row_nivel_dos = array(
                "folio_solicitud" => $JwtAuth->generarFolio($vSoliR->folio_solicitud),
                "fmin_solicitud" => $JwtAuth->generar($vSoliR->folio_solicitud),
                "fecha_solicitud" => gmdate('Y-m-d H:i:s', $vSoliR->fecha_solicitud),
                "pagado_a" => $vSoliR->pagado_a,
                //proveedor
                "proveedor" => $name_prov,
                "rfc_generico_prov" => $rfc_generico_prov,
                "rfc_prov" => $rfc_prov,
                "taxid_prov" => $taxid_prov,
                //forma de pago
                "fpago_clave" => $vSoliR->clave,
                "fpago_forma" => $vSoliR->forma,
                "nivel_dos_vhum" => $nivel_dos_vhum,
                "nivel_dos_egre" => $nivel_dos_egre,
              );
              $arraySoliList[] = $row_nivel_dos;
            }

            $row_root = array(
              "folio_reem" => $folio_reem,
              "fmin_reem" => $fmin_reem,
              "date_solicitud" => $date_solicitud,
              "name_emisor" => $name_emisor,
              "rfc_gen_emi" => $rfc_gen_emi,
              "rfc_emp_emi" => $rfc_emp_emi,
              "taxid_emp_emi" => $taxid_emp_emi,
              "nombreEmiPers" => $nombreEmiPers,
              "nivel_dos" => $arraySoliList,
            );
            $comportamiento_array[] = $row_root;
          }

          $row_comi = array(
            "token_comision_main" => $vComi->token_comision_main,
            "folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
            "fmin_comision" => "comi_" . $JwtAuth->generar($vComi->folio_comision),
            "fecha_comision" => gmdate('Y-m-d H:i:s', $vComi->fecha_comision),
            "comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
            "usuario_expide" => $user_expide,
            "usuario_comision" => $comisionadoUser,
            "especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
            "fecha_programada" => date('d-m-Y', $vComi->fecha_programada),
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
            
            "ubicacion_latitud" => !is_null($vComi->ubicacion_latitud) ? $vComi->ubicacion_latitud : '',
						"ubicacion_longitud" => !is_null($vComi->ubicacion_longitud) ? $vComi->ubicacion_longitud : '',
						"ubicacion_display_name" => !is_null($vComi->ubicacion_display_name) ? $JwtAuth->desencriptar($vComi->ubicacion_display_name) : '',

            "ubicacion_estado" => !is_null($vComi->ubicacion_estado) ? $JwtAuth->desencriptar($vComi->ubicacion_estado) : '',
            "ubicacion_municipio" => !is_null($vComi->ubicacion_municipio) ? $JwtAuth->desencriptar($vComi->ubicacion_municipio) : '',
            "ubicacion_codigo_postal" => !is_null($vComi->ubicacion_codigo_postal) ? $vComi->ubicacion_codigo_postal : '',
            "ubicacion_colonia" => !is_null($vComi->ubicacion_colonia) ? $JwtAuth->desencriptar($vComi->ubicacion_colonia) : '',

            "concluida_fecha" => $sql_concluida_fecha,
            "comision_relaciones_num" => count($comision_relaciones),
            "comportamiento" => $comportamiento_array,
            "isTreeView" => true,
            "isUbicacionView" => true,
          );
          $array_comisiones[] = $row_comi;
        }

        $dataMensaje = array("status" => "success", "code" => 200, "comi_listado" => $array_comisiones);
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

  public function comisionesMonitoreo(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_comisiones = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
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

        $selectComission = DB::table("terc_comisiones_main AS comi")
          ->join("sos_reembolsos_comisiones_rel AS reem_comi", "comi.id", "reem_comi.comision")
          ->join("terc_reembolso_main AS reem_main", "reem_comi.reembolso_main", "reem_main.id")
          ->join("main_empresas AS emp", "comi.empresa", "emp.id")
          ->where(["emp.empresa_token" => $usuario->empresa_token])
          ->orderBy("comi.folio_comision", "DESC")->get();
        $clave_arbol_main = 0;

        foreach ($selectComission as $vComi) {
          $token_reem = $vComi->token_reem;
          //da_te_default_timezone_set($vComi->zona_horaria);
          $folio_reem = $vComi->post_folio_reem == NULL ? 'REEM-' . $JwtAuth->generarFolio($vComi->folio_reem) : 'REEM-' . $JwtAuth->generarFolio($vComi->folio_reem) . '-' . $vComi->post_folio_reem;
          $fmin_reem = $vComi->post_folio_reem == NULL ? 'reem_' . $JwtAuth->generar($vComi->folio_reem) : 'reem_' . $JwtAuth->generar($vComi->folio_reem) . '_' . $vComi->post_folio_reem;

          $expideComission = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
          foreach ($expideComission as $vExpide) {
            $user_expide = $JwtAuth->desencriptar($vExpide->paterno) . " " .
              $JwtAuth->desencriptar($vExpide->materno) . " " . $JwtAuth->desencriptar($vExpide->nombre);
          }

          $comisionadoQuery = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empleados_catalogo AS pers", "comi.usuario_comision", "pers.id")
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

          $sql_califica_egresos = $vComi->egresos == TRUE ? true : false;
          $sql_califica_vhum = $vComi->valor_humano == TRUE ? true : false;
          $sql_concluida_fecha = $vComi->concluida == TRUE && $vComi->concluida_fecha != NULL ? gmdate('Y-m-d H:i:s', $vComi->concluida_fecha) : null;

          //reembolso
          //echo $vComi->token_reem;
          $reembolso_select = DB::table("terc_reembolso_main")->where(["token_reem" => $token_reem])->get();
          $reem_date_reg = gmdate('Y-m-d H:i:s', $reembolso_select[0]->fecha_sistema);

          $selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
            ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
            ->join("sos_personas AS people", "emp.persona", "=", "people.id")
            ->where(["reem_main.token_reem" => $token_reem])->get();

          foreach ($selectNameEmpEmi as $vEmisor) {
            $name_emisor = $vEmisor->abrev_nombre;
            $rfc_gen_emi = $vEmisor->rfc_generico;
            $rfc_emp_emi = $vEmisor->rfc != NULL ? $JwtAuth->desencriptar($vEmisor->rfc) : "---";
            $taxid_emp_emi = $vEmisor->tax_id != NULL ? $JwtAuth->desencriptar($vEmisor->tax_id) : "---";
          }

          $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
            ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
            ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
            ->where(["reem_main.token_reem" => $token_reem])->get();

          foreach ($selectPersEmpEmi as $vPemi) {
            $nombreEmiPers = $JwtAuth->desencriptarNombres($vPemi->paterno, $vPemi->materno, $vPemi->nombre);
          }

          $nivel_ordp_fase1 = array();
          $nivel_ordp_fase2 = array();
          $nivel_ordp_fase3 = array();
          $ordp_folio = "";
          $ordp_fecha_reg = "";
          $lista_pagos_realizados = array();

          $listOrdenes = DB::select(
            "SELECT orden.* FROM fnzs_pagos_orden AS orden JOIN terc_reembolso_main AS reem_main 
                        JOIN main_empresas AS emp WHERE orden.status_ordenPago = TRUE AND orden.reembolso_main = reem_main.id
                        AND reem_main.token_reem = ? AND orden.empresa = emp.id AND emp.empresa_token = ?",
            [$token_reem, $usuario->empresa_token]
          );

          foreach ($listOrdenes as $rOrdPag) {
            $ordp_folio = "ORDP-" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago);
            $ordp_fecha_reg = gmdate('Y-m-d H:i:s', $rOrdPag->fecha_sistema_ordenp);
            if ($rOrdPag->autorizacion_pay == TRUE) {
              if ($rOrdPag->orden_terminada_bool == TRUE) {
                $ordp_fase3_row = array(
                  "status_pay_bool" => true,
                  "status_pay_date" => gmdate('Y-m-d H:i:s', $rOrdPag->orden_terminada_fecha),
                );
                $nivel_ordp_fase3[] = $ordp_fase3_row;
              }

              $ordp_fase2_row = array(
                "autorizacion_pay" => true,
                "fecha_autorizacion_pay" => gmdate('Y-m-d H:i:s', $rOrdPag->fecha_autorizacion_pay),
                "nivel_ordp_fase3" => $nivel_ordp_fase3,
              );
              $nivel_ordp_fase2[] = $ordp_fase2_row;
            }

            $row_ordenPay = array(
              "folio_ordenPago" => "ORDP-" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago),
              "fecha_registro" => gmdate('Y-m-d H:i:s', $rOrdPag->fecha_sistema_ordenp),
              "nivel_ordp_fase2" => $nivel_ordp_fase2,
            );
            $nivel_ordp_fase1[] = $row_ordenPay;

            $query_pagos_realizados = DB::table("fnzs_pagos_pago AS payment")
              ->join("teci_catalogo_monedas AS mon", "payment.p_moneda", "mon.id")
              ->join("fnzs_pagos_orden AS order", "payment.orden_pago", "=", "order.id")
              ->join("main_empresas AS emp", "payment.empresa", "emp.id")
              ->where(["order.token_ordenPago" => $rOrdPag->token_ordenPago, "emp.empresa_token" => $usuario->empresa_token])->get();

            foreach ($query_pagos_realizados as $vPayment) {
              $pagos_fsimp = $vPayment->fecha_sistema_ordenp;
              $pagos_f_reg = gmdate('Y-m-d H:i:s', $vPayment->fecha_sistema);

              $pers_paga = null;
              if ($vPayment->personal_pago != NULL) {
                $query_personal_pago = DB::table("vhum_empleados_catalogo AS pers")
                  ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
                  ->where(["pers.id" => $vPayment->personal_pago])->get();
                foreach ($query_personal_pago as $v_persp) {
                  $pers_paga = $JwtAuth->desencriptarNombres($v_persp->paterno, $v_persp->materno, $v_persp->nombre);
                }
              }

              $pago_autorizado = false;
              $fecha_pago_auth = null;
              if ($vPayment->pago_autorizado == TRUE) {
                $pago_autorizado = true;
                $fecha_pago_auth = gmdate('Y-m-d H:i:s', $vPayment->fecha_pago_auth);
              }

              //personal_autoriza
              $pers_autoriza = null;
              if ($vPayment->personal_autoriza != NULL) {
                $query_personal_autoriza = DB::table("vhum_empleados_catalogo AS pers")
                  ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
                  ->where(["pers.id" => $vPayment->personal_autoriza])->get();
                foreach ($query_personal_autoriza as $v_persa) {
                  $pers_autoriza = $JwtAuth->desencriptarNombres($v_persa->paterno, $v_persa->materno, $v_persa->nombre);
                }
              }

              $docs_anexos = array();
              $query_docs_anexos = DB::table("sos_documentos AS docs")
                ->join("fnzs_pagos_orden AS order", "docs.orden_pago", "=", "order.id")
                ->where(["order.token_ordenPago" => $vPayment->token_ordenPago])->get();

              foreach ($query_docs_anexos as $vDoc) {
                $rowDet = array(
                  "url_doc" => "https://downloads.sos-mexico.com.mx/pago_realizado_docs/" . $JwtAuth->generarFolio($rOrdPag->folio_ordenPago) . "/" . $vDoc->token_documento,
                  "tipo_doc" => $vDoc->tipo_documento,
                  "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),
                );
                $docs_anexos[] = $rowDet;
              }

              $row_pay = array(
                "pagos_token" => $vPayment->token_pagos,
                "pagos_folio" => "PAYM-" . $JwtAuth->generarFolio($vPayment->folio_pagos),
                "pagos_f_reg" => $pagos_f_reg,
                "pagos_fecha" => gmdate('Y-m-d H:i:s', $vPayment->fecha_pago),
                "pagos_monto" => "$" . number_format($vPayment->monto_pago, $vPayment->decimales, '.', ','),
                "pagos_monco" => $vPayment->codigo,
                "pagos_monam" => $vPayment->moneda,
                "pagos_obser" => $JwtAuth->desencriptar($vPayment->observacionesPago),
                "pagos_persp" => $pers_paga,
                "pagos_pauth" => $pago_autorizado,
                "pagos_fauth" => $fecha_pago_auth,
                "pagos_persa" => $pers_autoriza,
                "pagos_anexos" => $docs_anexos,
              );
              $lista_pagos_realizados[] = $row_pay;
              $dataMensaje = array("status" => "success", "code" => 200, "pagos_realizados" => $lista_pagos_realizados);
            }
          }

          $arraySoliList = array();
          $arbolArray = array();
          $soli_reem_auth_vhm = 0;
          $soli_reem_auth_egr = 0;
          $soli_reem_extemp = 0;
          $soli_reem_finish = 0;
          $total_reem = 0;
          $total_tipo_cambio = 0;
          $moneda_entrante_string = "";
          $moneda_entrante_decimales = 0;
          $moneda_saliente_string = "";
          $moneda_saliente_decimales = 0;
          $total_reem_saliente = 0;

          $importe_autorizado_inicial = 0;
          $orden_moneda_autorizado_inicial_name = "---";
          $orden_moneda_autorizado_inicial_decimales = 0;

          $importe_autorizado_final = 0;
          $orden_moneda_autorizado_final_name = "---";
          $orden_moneda_autorizado_final_decimales = 0;
          $klave_dos = 0;
          $soli_reem = DB::table("terc_reembolso_solicitud AS reem_soli")
            ->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
            ->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
            ->where(["reem_main.token_reem" => $token_reem])
            ->orderBy('reem_soli.folio_solicitud', 'DESC')->get();
          $soli_reem_list = count($soli_reem);
          foreach ($soli_reem as $vSoliR) {
            $total_reem = $total_reem + $vSoliR->importe_entrante;
            if ($vSoliR->terminado == TRUE) ++$soli_reem_finish;
            if ($vSoliR->autorizacion_vh == "A") ++$soli_reem_auth_vhm;
            if ($vSoliR->autorizacion_egr == "A") ++$soli_reem_auth_egr;

            if ($vSoliR->status_cancelacion == "E") {
              ++$soli_reem_extemp;
            } else {
              if ($vSoliR->autorizacion_vh == "A" || $vSoliR->autorizacion_vh == "N") {
                if ($vSoliR->autorizacion_egr != "A") {
                  if (time() >= $vSoliR->tiempo_respuesta_autorizacion) {
                    $update_auth_true = DB::table("terc_reembolso_solicitud AS reem_soli")
                      ->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
                      ->where(["reem_main.token_reem" => $token_reem, "reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])
                      ->limit(1)->update(array("reem_soli.status_cancelacion" => "E"));
                    ++$soli_reem_extemp;
                  }
                }
              } else {
                if (time() >= $vSoliR->tiempo_respuesta_autorizacion) {
                  $update_auth_true = DB::table("terc_reembolso_solicitud AS reem_soli")
                    ->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
                    ->where(["reem_main.token_reem" => $token_reem, "reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])
                    ->limit(1)->update(array("reem_soli.status_cancelacion" => "E"));
                  ++$soli_reem_extemp;
                }
              }
            }

            $soli_mon_entrante = DB::table("teci_catalogo_monedas AS mon_in")
              ->join("terc_reembolso_solicitud AS reem_soli", "mon_in.id", "=", "reem_soli.moneda_entrante")
              ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])->get();
            foreach ($soli_mon_entrante as $mon_in) {
              $moneda_entrante_string = $mon_in->codigo;
              $moneda_entrante_decimales = $mon_in->decimales;
              if ($vSoliR->autorizacion_egr == "A") {
                $orden_moneda_autorizado_inicial_name = $mon_in->codigo;
                $orden_moneda_autorizado_inicial_decimales = $mon_in->decimales;
              }
            }

            $total_tipo_cambio = $vSoliR->tipo_cambio;
            $resultante = $vSoliR->importe_entrante * $vSoliR->tipo_cambio;
            $total_reem_saliente = $total_reem_saliente + $resultante;

            if ($vSoliR->autorizacion_egr == "A") {
              $importe_autorizado_inicial = $importe_autorizado_inicial + $vSoliR->importe_entrante;
              $importe_autorizado_final = $importe_autorizado_inicial * $vSoliR->tipo_cambio;
            }

            $soli_mon_saliente = DB::table("teci_catalogo_monedas AS mon_out")
              ->join("terc_reembolso_solicitud AS reem_soli", "mon_out.id", "=", "reem_soli.moneda_saliente")
              ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem])->get();
            foreach ($soli_mon_saliente as $mon_out) {
              $moneda_saliente_string = $mon_out->codigo; //." ".$mon_out->moneda
              $moneda_saliente_decimales = $mon_out->decimales;
              if ($vSoliR->autorizacion_egr == "A") {
                $orden_moneda_autorizado_final_name = $mon_out->codigo;
                $orden_moneda_autorizado_final_decimales = $mon_out->decimales;
              }
            }

            $soli_r_prov = DB::table("terc_reembolso_solicitud AS reem_soli")
              ->join("terc_reembolso_main AS rmain", "reem_soli.reembolso_main", "=", "rmain.id")
              ->join("eegr_catalogo_proveedores AS cprov", "reem_soli.proveedor", "=", "cprov.id")
              ->join("sos_personas AS prov", "cprov.proveedor", "=", "prov.id")
              ->join("teci_forma_pago AS fpago", "reem_soli.forma_pago", "=", "fpago.id")
              ->where(["reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem, "rmain.token_reem" => $vComi->token_reem])->get();
            //proveedor
            $name_prov = "";
            $rfc_generico_prov = "";
            $rfc_prov = "";
            $taxid_prov = "";
            if (count($soli_r_prov) > 0) {
              foreach ($soli_r_prov as $sr_prov) {
                $name_prov = $sr_prov->nombre_extendido ? $JwtAuth->desencriptar($sr_prov->nombre_extendido) : ($sr_prov->denominacion_rs != '' ? $JwtAuth->desencriptar($sr_prov->denominacion_rs) : $JwtAuth->desencriptarNombres($sr_prov->paterno, $sr_prov->materno, $sr_prov->nombre));
                $rfc_generico_prov = $sr_prov->rfc_generico;
                $rfc_prov = $sr_prov->rfc != NULL ? $JwtAuth->desencriptar($sr_prov->rfc) : "---";
                $taxid_prov = $sr_prov->tax_id != NULL ? $JwtAuth->desencriptar($sr_prov->tax_id) : "---";
              }
            }

            $autorizacion_egr = $vSoliR->autorizacion_egr != NULL ? $vSoliR->autorizacion_egr : null;

            $select_max_auth_egr = DB::select(
              "SELECT fecha_registro,autorizacion_egr,comentarios 
                            FROM terc_reembolso_autorizacion_egr WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_egr AS r_auth 
                            JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                            AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",
              [$vComi->token_reem, $vSoliR->token_solicitud_reem]
            );

            $fecha_registro_auth_egr = count($select_max_auth_egr) == 1 ? gmdate('Y-m-d H:i:s', $select_max_auth_egr[0]->fecha_registro) : '';
            $comments_auth_egr = count($select_max_auth_egr) == 1 ? $JwtAuth->desencriptar($select_max_auth_egr[0]->comentarios) : '';

            $nivel_dos_vhum = array();
            $nivel_dos_egre = array();

            if ($vSoliR->autorizacion_vh != NULL && $vSoliR->autorizacion_vh != "N") {
              $autorizacion_vh = $vSoliR->autorizacion_vh != NULL ? $vSoliR->autorizacion_vh : null;
              $select_list_auth_vh = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_vh,r_auth.comentarios 
                                FROM terc_reembolso_autorizacion_vh AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                                WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id 
                                AND s_soli.token_solicitud_reem = ?", [$vComi->token_reem, $vSoliR->token_solicitud_reem]);
              $fecha_registro_auth_vh = gmdate('Y-m-d H:i:s', end($select_list_auth_vh)->fecha_registro);
              $comments_auth_vh = $JwtAuth->desencriptar(end($select_list_auth_vh)->comentarios);

              $n_dos_egre = array();
              $n_dos_egre_row = array(
                "key" => $clave_arbol_main . "-" . $klave_dos . "-0-0",
                "label" => "",
                "autorizacion_egr" => $autorizacion_egr,
                "fecha_registro_auth_egr" => $fecha_registro_auth_egr,
                "comments_auth_egr" => $comments_auth_egr,
                "nivel_ordp_fase1" => $vSoliR->autorizacion_egr == "A" ? $nivel_ordp_fase1 : [],
              );
              $n_dos_egre[] = $n_dos_egre_row;

              $n_dos_vhum_row = array(
                "key" => $clave_arbol_main . "-" . $klave_dos . "-0",
                "label" => "",
                "autorizacion_vh" => $autorizacion_vh,
                "fecha_registro_auth_vh" => $fecha_registro_auth_vh,
                "comments_auth_vh" => $comments_auth_vh,
                "children" => [$n_dos_egre]
              );
              $nivel_dos_vhum[] = $n_dos_vhum_row;
            } else {
              $nivel_dos_egre_row = array(
                "key" => $clave_arbol_main . "-" . $klave_dos . "-0",
                "label" => "",
                "autorizacion_egr" => $autorizacion_egr,
                "fecha_registro_auth_egr" => $fecha_registro_auth_egr,
                "comments_auth_egr" => $comments_auth_egr,
                "nivel_ordp_fase1" => $vSoliR->autorizacion_egr == "A" ? $nivel_ordp_fase1 : [],
              );
              $nivel_dos_egre[] = $nivel_dos_egre_row;
            }

            $row_nivel_dos = array(
              "key" => $clave_arbol_main . "-" . $klave_dos,
              "label" => "",
              "folio_solicitud" => $JwtAuth->generarFolio($vSoliR->folio_solicitud),
              "fmin_solicitud" => $JwtAuth->generar($vSoliR->folio_solicitud),
              "fecha_solicitud" => gmdate('Y-m-d H:i:s', $vSoliR->fecha_solicitud),
              "pagado_a" => $vSoliR->pagado_a,
              //proveedor
              "proveedor" => $name_prov,
              "rfc_generico_prov" => $rfc_generico_prov,
              "rfc_prov" => $rfc_prov,
              "taxid_prov" => $taxid_prov,
              //forma de pago
              "fpago_clave" => $vSoliR->clave,
              "fpago_forma" => $vSoliR->forma,
              "nivel_dos_vhum" => $nivel_dos_vhum,
              "nivel_dos_egre" => $nivel_dos_egre,
              "children" => [count($nivel_dos_vhum) > 0 ? $nivel_dos_vhum : $nivel_dos_egre]
            );
            $arraySoliList[] = $row_nivel_dos;
            ++$klave_dos;
          }

          $selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
            ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
            ->join("sos_personas AS people", "emp.persona", "=", "people.id")
            ->where(["reem_main.token_reem" => $token_reem])->get();
          $name_receptor = $selectNameEmpRec[0]->abrev_nombre;

          if ($soli_reem_auth_vhm == 0) {
            $reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(137,4,0,0.7)95%,rgba(255,41,34,0.7)100%)!important;";
          } else if ($soli_reem_auth_vhm != $soli_reem_list) {
            $reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(180,161,0,0.7)95%,rgba(255,235,99,0.7)100%)!important;";
          } else if ($soli_reem_auth_vhm == $soli_reem_list) {
            $reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255, 0.2)80%,rgba(37,92,0,0.7)95%,rgba(56,139,1,0.7)100%)!important;";
          }

          $nombreRecPersVH = null;
          $fecha_respuesta_auth_vh = null;
          $time_respuesta_auth_vh = null;
          $btnp_horas_auth_vh_icon = null;
          $btnp_horas_auth_vh_color = null;

          if ($vComi->user_receptor_vh != NULL) {
            $selectPersVHEmpRec = DB::table("terc_reembolso_main AS reem_main")
              ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
              ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where(["reem_main.token_reem" => $token_reem])->get();

            foreach ($selectPersVHEmpRec as $vPrec) {
              $nombreRecPersVH = $JwtAuth->desencriptarNombres($vPrec->paterno, $vPrec->materno, $vPrec->nombre);
            }
            if ($soli_reem_auth_vhm == $soli_reem_list) {
              $fecha_respuesta_auth_vh = gmdate('Y-m-d H:i:s', $vComi->last_revision_vh);
              $time_inicial_auth_vh = $vComi->last_revision_vh - $vComi->fecha_sistema;
              $days_auth_vh = floor($time_inicial_auth_vh / (60 * 60 * 24));
              $time_inicial_auth_vh %= (60 * 60 * 24);
              $hours_auth_vh = floor($time_inicial_auth_vh / (60 * 60));
              $time_inicial_auth_vh %= (60 * 60);
              $min_auth_vh = floor($time_inicial_auth_vh / 60);
              $time_inicial_auth_vh %= 60;
              $sec_auth_vh = $time_inicial_auth_vh;
              $time_respuesta_auth_vh = $days_auth_vh . " días,$hours_auth_vh:$min_auth_vh:$sec_auth_vh"; //

              //$time_horas_autorizacion = ($vComi->tiempo_respuesta_autorizacion - time())/3600;
              $btnp_horas_auth_vh_icon = "fa-solid fa-check-double";
              $btnp_horas_auth_vh_color = "btn btn_extend text-bg-success rounded-3";
            } else {
              $fecha_respuesta_auth_vh = gmdate('Y-m-d H:i:s', $vComi->tiempo_respuesta_auth_vh);
              $time_inicial_auth_vh = $vComi->tiempo_respuesta_auth_vh - time();
              $days_auth_vh = floor($time_inicial_auth_vh / (60 * 60 * 24));
              $time_inicial_auth_vh %= (60 * 60 * 24);
              $hours_auth_vh = floor($time_inicial_auth_vh / (60 * 60));
              $time_inicial_auth_vh %= (60 * 60);
              $min_auth_vh = floor($time_inicial_auth_vh / 60);
              $time_inicial_auth_vh %= 60;
              $sec_auth_vh = $time_inicial_auth_vh;
              $time_respuesta_auth_vh = $days_auth_vh . " días,$hours_auth_vh:$min_auth_vh:$sec_auth_vh"; //

              $time_horas_auth_vh = ($vComi->tiempo_respuesta_auth_vh - time()) / 3600;
              $btnp_horas_auth_vh_icon = "fa-solid fa-traffic-light";
              if ($time_horas_auth_vh > 24) {
                $btnp_horas_auth_vh_color = "btn btn_extend text-bg-success rounded-3";
              } else if ($time_horas_auth_vh > 0 && $time_horas_auth_vh < 24) {
                $btnp_horas_auth_vh_color = "btn btn_extend bg-yellow-300";
              } else if ($time_horas_auth_vh <= 0) {
                $btnp_horas_auth_vh_color = "btn btn_extend btn_extend text-bg-danger rounded-3";
              }
            }
          }

          if ($soli_reem_auth_egr == 0) {
            $reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(137,4,0,0.7)95%,rgba(255,41,34,0.7)100%)!important;";
          } else if ($soli_reem_auth_egr != $soli_reem_list) {
            $reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(180,161,0,0.7)95%,rgba(255,235,99,0.7)100%)!important;";
          } else if ($soli_reem_auth_egr == $soli_reem_list) {
            $reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255, 0.2)80%,rgba(37,92,0,0.7)95%,rgba(56,139,1,0.7)100%)!important;";
          }

          $nombreRecPersEGR = null;
          $fecha_respuesta_auth_egr = null;
          $time_respuesta_auth_egr = null;
          $btnp_horas_auth_egr_icon = null;
          $btnp_horas_auth_egr_color = null;

          if ($vComi->user_receptor_egr != NULL) {
            $selectPersEGREmpRec = DB::table("terc_reembolso_main AS reem_main")
              ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
              ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
              ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
              ->where(["reem_main.token_reem" => $token_reem])->get();

            foreach ($selectPersEGREmpRec as $vPrec) {
              $nombreRecPersEGR = $JwtAuth->desencriptarNombres($vPrec->paterno, $vPrec->materno, $vPrec->nombre);
            }
            if ($soli_reem_auth_egr == $soli_reem_list) {
              $fecha_respuesta_auth_egr = gmdate('Y-m-d H:i:s', $vComi->last_revision_egr);
              $time_inicial_auth_egr = $vComi->last_revision_egr - $vComi->fecha_sistema;
              $days_auth_egr = floor($time_inicial_auth_egr / (60 * 60 * 24));
              $time_inicial_auth_egr %= (60 * 60 * 24);
              $hours_auth_egr = floor($time_inicial_auth_egr / (60 * 60));
              $time_inicial_auth_egr %= (60 * 60);
              $min_auth_egr = floor($time_inicial_auth_egr / 60);
              $time_inicial_auth_egr %= 60;
              $sec_auth_egr = $time_inicial_auth_egr;
              $time_respuesta_auth_egr = $days_auth_egr . " días,$hours_auth_egr:$min_auth_egr:$sec_auth_egr"; //

              //$time_horas_autorizacion = ($vComi->tiempo_respuesta_autorizacion - time())/3600;
              $btnp_horas_auth_egr_icon = "fa-solid fa-check-double";
              $btnp_horas_auth_egr_color = "btn btn_extend text-bg-success rounded-3";
            } else {
              $fecha_respuesta_auth_egr = gmdate('Y-m-d H:i:s', $vComi->tiempo_respuesta_auth_egr);
              $time_inicial_auth_egr = $vComi->tiempo_respuesta_auth_egr - time();
              $days_auth_egr = floor($time_inicial_auth_egr / (60 * 60 * 24));
              $time_inicial_auth_egr %= (60 * 60 * 24);
              $hours_auth_egr = floor($time_inicial_auth_egr / (60 * 60));
              $time_inicial_auth_egr %= (60 * 60);
              $min_auth_egr = floor($time_inicial_auth_egr / 60);
              $time_inicial_auth_egr %= 60;
              $sec_auth_egr = $time_inicial_auth_egr;
              $time_respuesta_auth_egr = $days_auth_egr . " días,$hours_auth_egr:$min_auth_egr:$sec_auth_egr"; //

              $time_horas_auth_egr = ($vComi->tiempo_respuesta_auth_egr - time()) / 3600;
              $btnp_horas_auth_egr_icon = "fa-solid fa-traffic-light";
              if ($time_horas_auth_egr > 24) {
                $btnp_horas_auth_egr_color = "btn btn_extend text-bg-success rounded-3";
              } else if ($time_horas_auth_egr > 0 && $time_horas_auth_egr < 24) {
                $btnp_horas_auth_egr_color = "btn btn_extend bg-yellow-300";
              } else if ($time_horas_auth_egr <= 0) {
                $btnp_horas_auth_egr_color = "btn btn_extend btn_extend text-bg-danger rounded-3";
              }
            }
          }

          if ($vComi->user_receptor_vh != NULL) {
            $percent_vhum = (100 * $soli_reem_auth_vhm) / $soli_reem_list;
            $percent_eegr = (100 * $soli_reem_auth_egr) / $soli_reem_list;
            $percent_fnzs = (100 * $soli_reem_finish) / $soli_reem_list;
            $percent_result = ($percent_vhum / 3) + ($percent_eegr / 3) + ($percent_fnzs / 3);
            $percent_terminado = $percent_result . "%";
          } else {
            $percent_eegr = (100 * $soli_reem_auth_egr) / $soli_reem_list;
            $percent_fnzs = (100 * $soli_reem_finish) / $soli_reem_list;
            $percent_result = ($percent_eegr / 2) + ($percent_fnzs / 2);
            $percent_terminado = $percent_result . "%";
          }

          $fecha_autorizacion_pago = DB::select("SELECT MAX(pay_ord.fecha_autorizacion_pay) AS fecha_autorizacion_pay 
                        FROM fnzs_pagos_orden AS pay_ord JOIN terc_reembolso_main AS reem_main WHERE pay_ord.reembolso_main = reem_main.id
                        AND reem_main.token_reem = ?", [$token_reem]);

          foreach ($fecha_autorizacion_pago as $fauthPay) {
            $fecha_auth_pay = $fauthPay->fecha_autorizacion_pay != NULL ? gmdate('Y-m-d H:i:s', $fauthPay->fecha_autorizacion_pay) : null;
          }

          $pago_tent_date = null;
          $pago_tent_fecha = null;
          $pago_tent_time = null;
          $btnp_pago_tent_icon = null;
          $btnp_pago_tent_color = null;
          $fecha_pago_tentativa = DB::select("SELECT MAX(pay_ord.tentativa_pago) AS tentativa FROM fnzs_pagos_orden AS pay_ord JOIN terc_reembolso_main AS reem_main 
                        WHERE pay_ord.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);
          //var_dump($fecha_autorizacion_pago);

          foreach ($fecha_pago_tentativa as $fPayTent) {
            if ($fPayTent->tentativa != NULL) {
              $pago_tent_date = $fPayTent->tentativa;
              $pago_tent_fecha = gmdate('Y-m-d H:i:s', $fPayTent->tentativa);
              $pago_tent_inicial_time = $fPayTent->tentativa - time();
              $days_pago_tent = floor($pago_tent_inicial_time / (60 * 60 * 24));
              $pago_tent_inicial_time %= (60 * 60 * 24);
              $hours_pago_tent = floor($pago_tent_inicial_time / (60 * 60));
              $pago_tent_inicial_time %= (60 * 60);
              $min_pago_tent = floor($pago_tent_inicial_time / 60);
              $pago_tent_inicial_time %= 60;
              $sec_pago_tent = $pago_tent_inicial_time;
              $pago_tent_time = $days_pago_tent . " días,$hours_pago_tent:$min_pago_tent:$sec_pago_tent"; //

              $pago_tent_horas = ($fPayTent->tentativa - time()) / 3600;
              $btnp_pago_tent_icon = "fa-solid fa-traffic-light";
              if ($pago_tent_horas > 24) {
                $btnp_pago_tent_color = "btn btn_extend text-bg-success rounded-3";
              } else if ($pago_tent_horas > 0 && $pago_tent_horas < 24) {
                $btnp_pago_tent_color = "btn btn_extend bg-yellow-300";
              } else if ($pago_tent_horas <= 0) {
                $btnp_pago_tent_color = "btn btn_extend btn_extend text-bg-danger rounded-3";
              }
            }
          }

          $pago_done_fecha = null;
          $pago_done_icon = null;
          $pago_done_color = null;
          $fecha_pago_realizado = DB::select("SELECT MAX(payment.fecha_pago) AS fecha_pago FROM fnzs_pagos_pago AS payment 
                        JOIN fnzs_pagos_orden AS pay_ord JOIN terc_reembolso_main AS reem_main WHERE payment.orden_pago = pay_ord.id 
                        AND pay_ord.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);
          //var_dump($fecha_autorizacion_pago);

          foreach ($fecha_pago_realizado as $fPayMent) {
            if ($fPayMent->fecha_pago != NULL) {
              $pago_done_fecha = gmdate('Y-m-d H:i:s', $fPayMent->fecha_pago);
              $pago_done_icon = "fa-solid fa-check-double";
              $pago_done_color = "btn btn_extend text-bg-success rounded-3";
              $time_pago_done = $pago_tent_date - $fPayMent->fecha_pago;
              $days_pago_done = floor($time_pago_done / (60 * 60 * 24));
              $time_pago_done %= (60 * 60 * 24);
              $hours_pago_done = floor($time_pago_done / (60 * 60));
              $time_pago_done %= (60 * 60);
              $min_pago_done = floor($time_pago_done / 60);
              $time_pago_done %= 60;
              $sec_pago_done = $time_pago_done;
              $time_pago_done = $days_pago_done . " días,$hours_pago_done:$min_pago_done:$sec_pago_done"; //

              $time_horas_pago_done = ($fPayMent->fecha_pago - time()) / 3600;
              $btnp_horas_pago_done_icon = "fa-solid fa-check-double";
              $btnp_horas_pago_done_color = "btn btn_extend text-bg-success rounded-3";

              $pago_done_horas = ($pago_tent_date - $fPayMent->fecha_pago) / 3600;
              $btnp_pago_tent_icon = "fa-solid fa-traffic-light";
              if ($pago_done_horas > 24) {
                $btnp_pago_tent_color = "btn btn_extend text-bg-success rounded-3";
              } else if ($pago_done_horas > 0 && $pago_done_horas < 24) {
                $btnp_pago_tent_color = "btn btn_extend bg-yellow-300";
              } else if ($pago_done_horas <= 0) {
                $btnp_pago_tent_color = "btn btn_extend btn_extend text-bg-danger rounded-3";
              }
            }
          }

          $arbolArray[] = ["key" => $clave_arbol_main, "label" => $folio_reem, "children" => $arraySoliList];

          $row_comi = array(
            "token_comision_main" => $vComi->token_comision_main,
            "folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
            "fmin_comision" => "comi_" . $JwtAuth->generar($vComi->folio_comision),
            "fecha_comision" => gmdate('Y-m-d H:i:s', $vComi->fecha_comision),
            "comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
            "usuario_expide" => $user_expide,
            "usuario_comision" => $comisionadoUser,
            "especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
            "fecha_programada" => date('d-m-Y', $vComi->fecha_programada),
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
            "concluida_fecha" => $sql_concluida_fecha,
            //"reembolso"
            "reem_folio" => $folio_reem,
            "reem_fmin" => $fmin_reem,
            "reem_date_reg" => $reem_date_reg,
            "reem_emisorEmp" => $name_emisor,
            "reem_emisorPers" => $nombreEmiPers,
            "reem_importe" => "$" . number_format($total_reem, $moneda_entrante_decimales, '.', ',') . " " . $moneda_entrante_string,
            "reem_tipo_cambio" => "$" . $total_tipo_cambio,
            "reem_importe_convert" => "$" . number_format($total_reem_saliente, $moneda_saliente_decimales, '.', ',') . " " . $moneda_saliente_string,
            "reem_moneda_entrante_decimales" => $moneda_entrante_decimales,
            "reem_moneda_saliente_decimales" => $moneda_saliente_decimales,

            "name_receptor" => $name_receptor,
            "nombreRecPersVH" => $nombreRecPersVH,
            "nombreRecPersEGR" => $nombreRecPersEGR,

            "fecha_respuesta_pago_ord_auth" => $fecha_auth_pay,

            "fecha_respuesta_pago_tentativa" => $pago_tent_fecha,
            "time_respuesta_pago_tentativa" => $pago_tent_time,
            "time_respuesta_pago_tent_icon" => $btnp_pago_tent_icon,
            "time_respuesta_pago_tent_color" => $btnp_pago_tent_color,

            "respuesta_pago_done_fecha" => $pago_done_fecha,
            "respuesta_pago_done_icon" => $pago_done_icon,
            "respuesta_pago_done_color" => $pago_done_color,

            "soli_reem_list" => $soli_reem_list,
            "soli_reem_auth_vhm" => $soli_reem_auth_vhm,
            "reem_soli_auth_vhm_style" => $reem_soli_auth_vhm_style,
            "soli_reem_auth_egr" => $soli_reem_auth_egr,
            "reem_soli_auth_egr_style" => $reem_soli_auth_egr_style,
            "fecha_respuesta_auth_vh" => $fecha_respuesta_auth_vh,
            "time_respuesta_auth_vh" => $time_respuesta_auth_vh,
            "btnp_horas_auth_vh_icon" => $btnp_horas_auth_vh_icon,
            "btnp_horas_auth_vh_color" => $btnp_horas_auth_vh_color,
            "fecha_respuesta_auth_egr" => $fecha_respuesta_auth_egr,
            "time_respuesta_auth_egr" => $time_respuesta_auth_egr,
            "btnp_horas_auth_egr_icon" => $btnp_horas_auth_egr_icon,
            "btnp_horas_auth_egr_color" => $btnp_horas_auth_egr_color,
            "importe_autorizado_inicial_format" => "$" . number_format($importe_autorizado_inicial, $orden_moneda_autorizado_inicial_decimales, '.', ','),
            "orden_moneda_inicial_autorizada_name" => $orden_moneda_autorizado_inicial_name,
            "importe_autorizado_final" => "$" . number_format($importe_autorizado_final, $orden_moneda_autorizado_final_decimales, '.', ','),
            "orden_moneda_final_autorizada_name" => $orden_moneda_autorizado_final_name,
            "ordp_folio" => $ordp_folio,
            "ordp_fecha_reg" => $ordp_fecha_reg,
            "pagos_realizados" => $lista_pagos_realizados,
            "nivel_dos" => $arraySoliList,
            "arbol_nivel_dos" => $arbolArray,
          );
          $array_comisiones[] = $row_comi;
          ++$clave_arbol_main;
        }

        $dataMensaje = array("status" => "success", "code" => 200, "comi_listado" => $array_comisiones);
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

  public function comisionesSolicitudApertura(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_comisiones = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
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

        $selectComission = DB::select("SELECT token_comision_main,folio_comision,comision_proyecto FROM terc_comisiones_main 
                    WHERE id IN (SELECT comision FROM terc_comisiones_soli_auth AS comi_sauth JOIN main_empresas AS emp 
                        WHERE comi_sauth.soli_aprobada = FALSE AND comi_sauth.user_emp = emp.id AND emp.empresa_token = ?)", [$usuario->empresa_token]);

        foreach ($selectComission as $vComi) {
          $selectMaxSoli = DB::table("terc_comisiones_main AS comi")
            ->join("terc_comisiones_soli_auth AS soli_auth", "comi.id", "=", "soli_auth.comision")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])
            ->orderBy("soli_auth.id", "DESC")->get();
          $total_num_solic = count($selectMaxSoli);
          $row_comi = array(
            "token_comision_main" => $vComi->token_comision_main,
            "folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision) . "-" . $JwtAuth->generarFolio($selectMaxSoli[0]->folio_comision_soli_auth),
            "comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
            "max_soli_date" => gmdate('Y-m-d H:i:s', $selectMaxSoli[0]->fecha_comision_soli_auth),
            "solicitudes" => $total_num_solic,
          );
          $array_comisiones[] = $row_comi;
        }

        $dataMensaje = array("status" => "success", "code" => 200, "comi_listado" => $array_comisiones);
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

  public function comisionAperturaReabrir(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayProveedores = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_comision" => "required|string",
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
        $token_comision = $parametrosArray["token_comision"];

        $selectComission = DB::table("terc_comisiones_main AS comi")
          ->join("main_empresas AS emp", "comi.empresa", "emp.id")
          ->where(["comi.status" => TRUE, "comi.token_comision_main" => $token_comision, "emp.empresa_token" => $usuario->empresa_token])
          ->orderBy("comi.folio_comision", "DESC")->get();

        if (count($selectComission) == 1) {
          foreach ($selectComission as $vComi) {
            $folio_comision = "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision);
            $reabreComi = DB::table("terc_comisiones_main")->where(["token_comision_main" => $vComi->token_comision_main])
              ->limit(1)->update(array("concluida" => FALSE, "reapertura_fecha" => time()));

            if ($reabreComi) {
              $titulo_ = "Validación de proveedor";
              $mensaje_user = "La comisión con folio " . $folio_comision . " ha sido reabierta";

              $soliValidate = DB::table("terc_comisiones_main AS comi")
                ->join("terc_comisiones_soli_auth AS soli_auth", "comi.id", "=", "soli_auth.comision")
                ->join("teci_usuarios_catalogo AS users", "soli_auth.user_user", "=", "users.id")
                ->where(["soli_auth.soli_aprobada" => FALSE, "comi.token_comision_main" => $vComi->token_comision_main])->get();

              foreach ($soliValidate as $mSoli) {
                $soliValidAprob = DB::table("terc_comisiones_soli_auth")
                  ->where(["token_comision_soli_auth" => $mSoli->token_comision_soli_auth])
                  ->limit(1)->update(array("soli_aprobada" => TRUE));

                $JwtAuth->notificacionPushDevices($mSoli->usuario_token, $titulo_, $mensaje_user);
              }

              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => $mensaje_user,
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Reapertura de comisión no registrada, intentelo nuevamente o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'el proveedor buscado no existe'
          );
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

  public function comisionListasRecibeDinero(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    //$docsAnexos = $request->file("docsAnexos");
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);
    $array_comisiones = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
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

        $selectComission = DB::table("terc_comisiones_main AS comi")
          ->join("main_empresas AS emp", "comi.empresa", "emp.id")
          ->where(["comi.status" => TRUE, "comi.concluida" => FALSE, "comi.recibe_dinero" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
          ->orderBy("comi.folio_comision", "DESC")->get();

        foreach ($selectComission as $vComi) {
          $expideComission = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
          foreach ($expideComission as $vExpide) {
            $user_expide = $JwtAuth->desencriptarNombres($vExpide->paterno, $vExpide->materno, $vExpide->nombre);
          }

          $comisionadoQuery = DB::table("terc_comisiones_main AS comi")
            ->join("vhum_empleados_catalogo AS pers", "comi.usuario_comision", "pers.id")
            ->join("sos_personas AS people", "pers.empleado_name", "people.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
          foreach ($comisionadoQuery as $vComiU) {
            $comisionadoUser = $JwtAuth->desencriptarNombres($vComiU->paterno, $vComiU->materno, $vComiU->nombre);
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

          $sql_califica_egresos = $vComi->egresos == TRUE ? true : false;
          $sql_califica_vhum = $vComi->valor_humano == TRUE ? true : false;
          $sql_concluida_fecha = $vComi->concluida == TRUE && $vComi->concluida_fecha != NULL ? gmdate('Y-m-d H:i:s', $vComi->concluida_fecha) : null;

          $row_comi = array(
            "token_comision_main" => $vComi->token_comision_main,
            "folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
            "fecha_comision" => gmdate('Y-m-d H:i:s', $vComi->fecha_comision),
            "comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
            "usuario_expide" => $user_expide,
            "usuario_comision" => $comisionadoUser,
            "especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
            "fecha_programada" => date('d-m-Y', $vComi->fecha_programada),
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
            "concluida_fecha" => $sql_concluida_fecha,
            //"ubicacion_address" => $vComi->ubicacion_address,
          );
          $array_comisiones[] = $row_comi;
        }

        $dataMensaje = array("status" => "success", "code" => 200, "comi_listado" => $array_comisiones);
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

  public function comisionRegistroAvisoFnzs(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "tokenComision" => "required|string",
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
        $tokenComision = $parametrosArray["tokenComision"];

        $selectComission = DB::table("terc_comisiones_main AS comi")
          ->join("main_empresas AS emp", "comi.empresa", "emp.id")
          ->where(["comi.status" => TRUE, "comi.token_comision_main" => $tokenComision, "emp.empresa_token" => $usuario->empresa_token])
          ->orderBy("comi.folio_comision", "DESC")->get();

        if (count($selectComission)) {
          foreach ($selectComission as $vComi) {
            $folio_comision = "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision);
            $updateAviso = DB::table("terc_comisiones_main")->where(["token_comision_main" => $vComi->token_comision_main])
              ->limit(1)->update(array("aviso_finanzas" => TRUE));

            if ($updateAviso) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "Confirmaste que estas enterado del registro de la comisión con folio " . $folio_comision);
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "No se pudo confirmar que fuiste avisado del registro de la comisión con folio " . $folio_comision);
            }
          }
        } else {
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "No se encontró comisión");
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

  public function comisionRegistroAvisoEegr(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "tokenComision" => "required|string",
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
        $tokenComision = $parametrosArray["tokenComision"];

        $selectComission = DB::table("terc_comisiones_main AS comi")
          ->join("main_empresas AS emp", "comi.empresa", "emp.id")
          ->where(["comi.status" => TRUE, "comi.token_comision_main" => $tokenComision, "emp.empresa_token" => $usuario->empresa_token])
          ->orderBy("comi.folio_comision", "DESC")->get();

        if (count($selectComission)) {
          foreach ($selectComission as $vComi) {
            $folio_comision = "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision);
            $updateAviso = DB::table("terc_comisiones_main")->where(["token_comision_main" => $vComi->token_comision_main])
              ->limit(1)->update(array("aviso_egresos" => TRUE));

            if ($updateAviso) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "Confirmaste que estas enterado del registro de la comisión con folio " . $folio_comision);
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "No se pudo confirmar que fuiste avisado del registro de la comisión con folio " . $folio_comision);
            }
          }
        } else {
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "No se encontró comisión");
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

  public function comisionRegistroAvisoVhum(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $json_reem = $request->input('json');
    $parametros = json_decode($json_reem);
    $parametrosArray = json_decode($json_reem, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "tokenComision" => "required|string",
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
        $tokenComision = $parametrosArray["tokenComision"];

        $selectComission = DB::table("terc_comisiones_main AS comi")
          ->join("main_empresas AS emp", "comi.empresa", "emp.id")
          ->where(["comi.status" => TRUE, "comi.token_comision_main" => $tokenComision, "emp.empresa_token" => $usuario->empresa_token])
          ->orderBy("comi.folio_comision", "DESC")->get();

        if (count($selectComission)) {
          foreach ($selectComission as $vComi) {
            $folio_comision = "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision);
            $updateAviso = DB::table("terc_comisiones_main")->where(["token_comision_main" => $vComi->token_comision_main])
              ->limit(1)->update(array("aviso_valor_humano" => TRUE));

            if ($updateAviso) {
              $dataMensaje = array("status" => "success", "code" => 200, "message" => "Confirmaste que estas enterado del registro de la comisión con folio " . $folio_comision);
            } else {
              $dataMensaje = array("status" => "error", "code" => 200, "message" => "No se pudo confirmar que fuiste avisado del registro de la comisión con folio " . $folio_comision);
            }
          }
        } else {
          $dataMensaje = array("status" => "error", "code" => 200, "message" => "No se encontró comisión");
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
