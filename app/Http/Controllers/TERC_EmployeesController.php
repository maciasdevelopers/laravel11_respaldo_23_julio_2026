<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\ReembolsoModelo;
use App\Models\CajaModelo;
use App\Models\CuentBancModelo;
use App\Models\CuentaMonederoModelo;

class TERC_EmployeesController extends Controller{
	public function comisionReemListas(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'usuario_acreedor_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $usuario_acreedor_token = $request->input('usuario_acreedor_token');
      
      $selectComission = DB::table("terc_comisiones_main AS comi")
      ->whereIn('comi.usuario_comision', function ($sub) use($empresa,$usuario_acreedor_token,$usuario) {
        $sub->select('pers.id')->from('vhum_empleados_catalogo AS pers')
        //->join("main_empresa_usuario AS empuser", "pers.id", "=", "empuser.empleado")
        ->join("main_empresas AS emp", "pers.empleado_empresa", "emp.id")
        ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
        ->join("fnzs_catalogo_acreedores AS acree", "users.acreedor", "=", "acree.id")
        ->where("emp.empresa_token",$empresa)
        ->where('acree.token_cat_acreedores',$usuario_acreedor_token)
        ->where("users.usuario_token",$usuario)
        ->where("comi.concluida",FALSE)
        ->where("comi.status",TRUE);
      })
      ->orWhereIn('comi.proveedor_comisionado', function ($sub) use($empresa,$usuario_acreedor_token,$usuario) {
        $sub->select('catprov.id')->from('eegr_catalogo_proveedores AS catprov')
        ->join("fnzs_catalogo_acreedores AS acree", "catprov.id", "=", "acree.acr_proveedor_vinculado")
        ->where('acree.token_cat_acreedores',$usuario_acreedor_token)
        ->join("main_proveedor_usuario AS relpu", "catprov.id", "=", "relpu.proveedor")
        ->join("teci_usuarios_catalogo AS users", "relpu.usuario", "=", "users.id")
        ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
        ->where("emp.empresa_token",$empresa)
        ->where('acree.acr_habilita_reembolsos',TRUE)
        ->where('catprov.habilitado_para_reembolsos',TRUE)
        ->where('acree.token_cat_acreedores',$usuario_acreedor_token)
        ->where("users.usuario_token",$usuario)
      ->where("comi.concluida",FALSE)
      ->where("comi.status",TRUE);
      })
      //->where("comi.concluida",FALSE)
      //->where("comi.status",TRUE)
      ->orderBy("comi.folio_comision", "DESC")->get();

      if ($selectComission->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron comisiones registradas'
        );
      } else {
				foreach ($selectComission as $vComi) {
					$expideComission = DB::table("terc_comisiones_main AS comi")
					->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
					foreach ($expideComission as $vExpide) {
						$user_expide = $JwtAuth->desencriptarNombres($vExpide->paterno,$vExpide->materno,$vExpide->nombre);
					}

					$comisionadoTrabQuery = DB::table("terc_comisiones_main AS comi")
					->join("vhum_empleados_catalogo AS pers", "comi.usuario_comision", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where(["comi.token_comision_main" => $vComi->token_comision_main])
					->select("people.paterno","people.materno","people.nombre")
          ->first();

					$comisionadoProvQuery = DB::table("terc_comisiones_main AS comi")
					->join("eegr_catalogo_proveedores AS catprov", "comi.proveedor_comisionado", "catprov.id")
          ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
					->where(["comi.token_comision_main" => $vComi->token_comision_main])
					->select("prov.nombre_extendido")
          ->first();

          if ($comisionadoTrabQuery && ! $comisionadoProvQuery) {
            $comisionOrigen = "acreedor";
            $comisionadoUser = $JwtAuth->desencriptarNombres($comisionadoTrabQuery->paterno,$comisionadoTrabQuery->materno,$comisionadoTrabQuery->nombre);
          } else {
            $comisionOrigen = "proveedor";
            $comisionadoUser = $JwtAuth->desencriptar($comisionadoProvQuery->nombre_extendido);
          }

					$sql_recibe_dinero = $vComi->recibe_dinero ? true : false;
					$sql_moneda_tkn = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
					$sql_moneda_name = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
					$sql_dinero_recibido = $vComi->recibe_dinero ? "$" . number_format($vComi->dinero_recibido, $JwtAuth->getMonedaAPI($vComi->comision_moneda), '.', ',') : null;
					$sql_dinero_recibido_simple = $vComi->recibe_dinero ? $vComi->dinero_recibido : null;

					$sql_califica_egresos = $vComi->egresos ? true : false;
					$sql_califica_vhum = $vComi->valor_humano ? true : false;
					$bool_reapertura_fecha = !is_null($vComi->reapertura_fecha) ? true : false;

					$sql_concluida_fecha = $vComi->concluida ? gmdate('Y-m-d H:i:s', $vComi->concluida_fecha) : (!is_null($vComi->reapertura_fecha) ? gmdate('Y-m-d H:i:s', $vComi->concluida_fecha) . " reabierto (" . gmdate('Y-m-d H:i:s', $vComi->reapertura_fecha) . ")" : '');

					$row_comi = array(
						"token_comision_main" => $vComi->token_comision_main,
						"folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
						"fecha_comision" => gmdate('Y-m-d H:i:s', $vComi->fecha_comision),
						"comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
						"usuario_expide" => $user_expide,
						"comisionOrigen" => $comisionOrigen,
						"usuario_comision" => $comisionadoUser,
						"especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
						"fecha_programada" => gmdate('Y-m-d H:i:s', $vComi->fecha_programada),
						"duracion" => $vComi->duracion,
						"recibe_dinero" => $sql_recibe_dinero,
						"dinero_recibido" => $sql_dinero_recibido,
						"dinero_recibido_simple" => $sql_dinero_recibido_simple,
						"comision_moneda_tkn" => $sql_moneda_tkn,
						"comision_moneda_name" => $sql_moneda_name,
						"comi_tiempo_respuesta" => $vComi->tiempo_respuesta,
						"egresos" => $sql_califica_egresos,
						"valor_humano" => $sql_califica_vhum,

						"ubicacion_latitud" => !is_null($vComi->ubicacion_latitud) ? $vComi->ubicacion_latitud : '',
						"ubicacion_longitud" => !is_null($vComi->ubicacion_longitud) ? $vComi->ubicacion_longitud : '',
						"ubicacion_display_name" => !is_null($vComi->ubicacion_display_name) ? $JwtAuth->desencriptar($vComi->ubicacion_display_name) : '',

            "ubicacion_estado" => !is_null($vComi->ubicacion_estado) ? $JwtAuth->desencriptar($vComi->ubicacion_estado) : '',
            "ubicacion_municipio" => !is_null($vComi->ubicacion_municipio) ? $JwtAuth->desencriptar($vComi->ubicacion_municipio) : '',
            "ubicacion_codigo_postal" => !is_null($vComi->ubicacion_codigo_postal) ? $vComi->ubicacion_codigo_postal : '',
            "ubicacion_colonia" => !is_null($vComi->ubicacion_colonia) ? $JwtAuth->desencriptar($vComi->ubicacion_colonia) : '',

						"bool_reapertura_fecha" => $bool_reapertura_fecha,
						"concluida_fecha" => $sql_concluida_fecha,
						"busqueda_completa" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision) . "-" . $JwtAuth->desencriptar($vComi->comision_proyecto),
					);
					$array_comisiones[] = $row_comi;
				}

				$dataMensaje = array(
					"status" => "success",
					'code' => 200,
					"comisiones_lista" => $array_comisiones,
				);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	//reembolsos
	public function reembolso_lista_indicadores(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayReem = array();

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

				if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
					$list_reembolso = DB::table("terc_reembolso_main AS reem_main")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.last_version", "=", "reem_soli.id")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->where(["reem_soli.status_activacion" => TRUE, "reem_main.status_reem" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
						->orderBy("reem_main.folio_reem", "DESC")->get();
				} else {
					$list_reembolso = DB::table("terc_reembolso_main AS reem_main")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.last_version", "=", "reem_soli.id")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
						->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
						->where(["reem_soli.status_activacion" => TRUE, "reem_main.status_reem" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])
						->orderBy("reem_main.folio_reem", "DESC")->get();
				}
				//echo count($list_reembolso);
				foreach ($list_reembolso as $vremb) {
					//da_te_default_timezone_set($vremb->zona_horaria);

					$token_reem = $vremb->token_reem;
					$tkn_reem_soli = $vremb->token_solicitud_reem;

					$fecha_solicitud = $vremb->fecha_sistema;
					$date_solicitud = gmdate('Y-m-d H:i:s', $vremb->fecha_sistema);

					$fecha_respuesta_autorizacion = gmdate('Y-m-d H:i:s', $vremb->tiempo_respuesta_autorizacion);
					$time_inicial_autorizacion = $vremb->tiempo_respuesta_autorizacion - time();
					$days_autorizacion = floor($time_inicial_autorizacion / (60 * 60 * 24));
					$time_inicial_autorizacion %= (60 * 60 * 24);
					$hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
					$time_inicial_autorizacion %= (60 * 60);
					$min_autorizacion = floor($time_inicial_autorizacion / 60);
					$time_inicial_autorizacion %= 60;
					$sec_autorizacion = $time_inicial_autorizacion;
					$time_respuesta_autorizacion = $days_autorizacion . " días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion"; //

					$time_horas_autorizacion = ($vremb->tiempo_respuesta_autorizacion - time()) / 3600;
					echo $time_horas_autorizacion . " ";
					if ($time_horas_autorizacion > 24) {
						$btnp_horas_autorizacion = "btn btn_extend bg-green-600";
					} else if ($time_horas_autorizacion > 0 && $time_horas_autorizacion < 24) {
						$btnp_horas_autorizacion = "btn btn_extend bg-yellow-500";
					} else if ($time_horas_autorizacion <= 0) {
						$btnp_horas_autorizacion = "btn btn_extend text-bg-danger rounded-3";
					}

					$iva_final = 0;
					$importe_final = 0;

					$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem) . (!is_null($vremb->post_folio_reem) ? '-' . $vremb->post_folio_reem : '');

					$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("sos_personas AS people", "emp.persona", "=", "people.id")
						->where(["reem_main.token_reem" => $token_reem])->get();

					foreach ($selectNameEmpEmi as $vEmisor) {
						$name_emisor = $vEmisor->abrev_nombre;
						$rfc_gen_emi = $vEmisor->rfc_generico;
						$rfc_emp_emi = !is_null($vEmisor->rfc) ? $JwtAuth->desencriptar($vEmisor->rfc) : "---";
						$taxid_emp_emi = !is_null($vEmisor->tax_id) ? $JwtAuth->desencriptar($vEmisor->tax_id) : "---";
					}

					$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
						->join("sos_personas AS people", "catAcree.acreedor", "=", "people.id")
						->where(["reem_main.token_reem" => $token_reem])->get();

					foreach ($selectPersEmpEmi as $vPemi) {
						$nombreEmiPers = $vPemi->nombre_extendido ? $JwtAuth->desencriptar($vPemi->nombre_extendido) : $JwtAuth->desencriptarNombres($vPemi->paterno, $vPemi->materno, $vPemi->nombre);
					}

					$selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
						->join("sos_personas AS people", "emp.persona", "=", "people.id")
						->where(["reem_main.token_reem" => $token_reem])->get();

					foreach ($selectNameEmpRec as $vReceptor) {
						$name_receptor = $vReceptor->abrev_nombre;
						$rfc_gen_rec = $vReceptor->rfc_generico;
						$rfc_emp_rec = !is_null($vReceptor->rfc) ? $JwtAuth->desencriptar($vReceptor->rfc) : "---";
						$taxid_emp_rec = !is_null($vReceptor->tax_id) ? $JwtAuth->desencriptar($vReceptor->tax_id) : "---";
					}
					$nombreRecPersVH = null;
					$nombreRecPersEGR = null;
					if ($vremb->user_receptor_vh != NULL) {
						$selectPersVHEmpRec = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
							->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
							->where(["reem_main.token_reem" => $token_reem])->get();

						foreach ($selectPersVHEmpRec as $vPrec) {
							$nombreRecPersVH = $JwtAuth->desencriptar($vPrec->paterno, $vPrec->materno, $vPrec->nombre);
						}
					}

					if ($vremb->user_receptor_egr != NULL) {
						$selectPersEGREmpRec = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
							->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
							->where(["reem_main.token_reem" => $token_reem])->get();

						foreach ($selectPersEGREmpRec as $vPrec) {
							$nombreRecPersEGR = $JwtAuth->desencriptar($vPrec->paterno, $vPrec->materno, $vPrec->nombre);
						}
					}

					$soli_reem = DB::table("terc_reembolso_main AS reem_main")
					->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
					->where(["reem_soli.status_activacion" => TRUE, "reem_main.token_reem" => $token_reem])
					->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

					$soli_reem_list = count($soli_reem);
					$soli_reem_auth_vhm = 0;
					$soli_reem_auth_egr = 0;
					$soli_reem_extemp = 0;
					$soli_reem_finish = 0;

					$total_reem = 0;
					$total_tipo_cambio = 0;
					$moneda_entrante_string = "";
					$moneda_entrante_decimales = 0;
					$total_reem_saliente = 0;
					foreach ($soli_reem as $vSoliR) {
						$total_reem = $total_reem + $vSoliR->importe_entrante;

						if ($vSoliR->terminado == TRUE) ++$soli_reem_finish;

						if ($vSoliR->autorizacion_vh == "A" || $vSoliR->autorizacion_vh == "N") ++$soli_reem_auth_vhm;
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

						$moneda_entrante_string = $vSoliR->moneda_entrante;
						$moneda_entrante_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);

						//$soli_mon_entrante = DB::table("teci_catalogo_monedas")->where(["id" => $vSoliR->moneda_entrante])->get();
						//foreach ($soli_mon_entrante as $mon_in) {
						//  $regFolder = DB::table('terc_reembolso_solicitud')->where('token_solicitud_reem',$vSoliR->token_solicitud_reem)
						//  ->limit(1)->update(
						//    array(
						//      'moneda_entrante' => $mon_in->codigo,
						//    )
						//  );
						//}

						$total_tipo_cambio = $vSoliR->tipo_cambio;
						$resultante = $vSoliR->importe_entrante * $vSoliR->tipo_cambio;
						$total_reem_saliente = $total_reem_saliente + $resultante;
					}

					if ($soli_reem_auth_vhm == 0) {
						$reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(137,4,0,0.7)95%,rgba(255,41,34,0.7)100%)!important;";
					} else if ($soli_reem_auth_vhm != $soli_reem_list) {
						$reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(180,161,0,0.7)95%,rgba(255,235,99,0.7)100%)!important;";
					} else if ($soli_reem_auth_vhm == $soli_reem_list) {
						$reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255, 0.2)80%,rgba(37,92,0,0.7)95%,rgba(56,139,1,0.7)100%)!important;";
					}

					if ($soli_reem_auth_egr == 0) {
						$reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(137,4,0,0.7)95%,rgba(255,41,34,0.7)100%)!important;";
					} else if ($soli_reem_auth_egr != $soli_reem_list) {
						$reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(180,161,0,0.7)95%,rgba(255,235,99,0.7)100%)!important;";
					} else if ($soli_reem_auth_egr == $soli_reem_list) {
						$reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255, 0.2)80%,rgba(37,92,0,0.7)95%,rgba(56,139,1,0.7)100%)!important;";
					}

					$reem_canceled = "";
					if ($soli_reem_extemp == $soli_reem_list) {
						$reem_canceled = "background-color: gray!important;";
					}

					if ($soli_reem_finish > 0) {
						$percent_result = (100 * $soli_reem_finish) / $soli_reem_list;
						$percent_terminado = $percent_result . "%";
					} else {
						$percent_terminado = "0%";
					}

					$sql_total_reem = DB::select("SELECT FORMAT(?,2) AS final_format", [$total_reem]);
					$final_importe = $sql_total_reem[0]->final_format;

					$fecha_autorizacion_pago = DB::select("SELECT MAX(pay_ord.fecha_autorizacion_pay) AS fecha_autorizacion_pay 
                        FROM fnzs_pagos_orden AS pay_ord JOIN terc_reembolso_main AS reem_main WHERE pay_ord.reembolso_main = reem_main.id
                        AND reem_main.token_reem = ?", [$token_reem]);
					//var_dump($fecha_autorizacion_pago);

					foreach ($fecha_autorizacion_pago as $fauthPay) {
						$fecha_auth_pay = !is_null($fauthPay->fecha_autorizacion_pay) ? gmdate('Y-m-d H:i:s', $fauthPay->fecha_autorizacion_pay) : null;
					}

					$fecha_respuesta_pago = null;
					$time_respuesta_pago = null;
					$fecha_pago_tentativa = DB::select("SELECT MAX(pay_ord.tentativa_pago) AS tentativa FROM fnzs_pagos_orden AS pay_ord JOIN terc_reembolso_main AS reem_main 
                        WHERE pay_ord.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);
					//var_dump($fecha_autorizacion_pago);

					foreach ($fecha_pago_tentativa as $fPayTent) {
						if ($fPayTent->tentativa != NULL) {
							$fecha_respuesta_pago = gmdate('Y-m-d H:i:s', $fPayTent->tentativa);
							$time_inicial_pago = $fPayTent->tentativa - time();
							$days_pago = floor($time_inicial_pago / (60 * 60 * 24));
							$time_inicial_pago %= (60 * 60 * 24);
							$hours_pago = floor($time_inicial_pago / (60 * 60));
							$time_inicial_pago %= (60 * 60);
							$min_pago = floor($time_inicial_pago / 60);
							$time_inicial_pago %= 60;
							$sec_pago = $time_inicial_pago;
							$time_respuesta_pago = $days_pago . " días,$hours_pago:$min_pago:$sec_pago"; //
						}
					}

					$fecha_pago_realizado = DB::select("SELECT MAX(payment.fecha_pago) AS fecha_pago FROM fnzs_pagos_pago AS payment 
                        JOIN fnzs_pagos_orden AS pay_ord JOIN terc_reembolso_main AS reem_main WHERE payment.orden_pago = pay_ord.id 
                        AND pay_ord.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);
					//var_dump($fecha_autorizacion_pago);

					foreach ($fecha_pago_realizado as $fPayMent) {
						$fecha_pago_done = !is_null($fPayMent->fecha_pago) ? gmdate('Y-m-d H:i:s', $fauthPay->fecha_autorizacion_pay) : null;
					}

					$row = array(
						"token_reem" => $token_reem,
						"folio_reem" => $folio_reem,
						"fecha_solicitud" => $fecha_solicitud,
						"date_solicitud" => $date_solicitud,
						"name_emisor" => $name_emisor,
						"rfc_gen_emi" => $rfc_gen_emi,
						"rfc_emp_emi" => $rfc_emp_emi,
						"taxid_emp_emi" => $taxid_emp_emi,
						"nombreEmiPers" => $nombreEmiPers,
						"importe_total" => "$" . number_format($total_reem, $moneda_entrante_decimales, '.', ','),
						"moneda_entrante" => $moneda_entrante_string,
						"moneda_entrante_decimales" => $moneda_entrante_decimales,
						"total_tipo_cambio" => "$" . $total_tipo_cambio,
						"total_reem_saliente" => "$" . number_format($total_reem_saliente, $moneda_entrante_decimales, '.', ','),
						"name_receptor" => $name_receptor,
						"rfc_gen_rec" => $rfc_gen_rec,
						"rfc_emp_rec" => $rfc_emp_rec,
						"taxid_emp_rec" => $taxid_emp_rec,
						"nombreRecPersVH" => $nombreRecPersVH,
						"nombreRecPersEGR" => $nombreRecPersEGR,
						"fecha_respuesta_autorizacion_vhegr" => $fecha_respuesta_autorizacion,
						"time_respuesta_autorizacion_vhegr" => $time_respuesta_autorizacion,
						"fecha_respuesta_pago_ord_auth" => $fecha_auth_pay,
						"fecha_respuesta_pago_tentativa" => $fecha_respuesta_pago,
						"time_respuesta_pago_tentativa" => $time_respuesta_pago,
						"fecha_respuesta_pago_realizado" => $fecha_pago_done,
						"soli_reem_list" => $soli_reem_list,
						"soli_reem_auth_vhm" => $soli_reem_auth_vhm,
						"reem_soli_auth_vhm_style" => $reem_soli_auth_vhm_style,
						"soli_reem_auth_egr" => $soli_reem_auth_egr,
						"reem_soli_auth_egr_style" => $reem_soli_auth_egr_style,
						"reem_canceled" => $reem_canceled,
						"reem_percent_terminado" => $percent_terminado,
						"btnp_horas_autorizacion" => $btnp_horas_autorizacion,
						//"payment_auth_date" => "---",
						//"payment_date" => "---",
					);
					$arrayReem[] = $row;
				}

				$dataMensaje = array(
					'status' => 'success',
					'code' => 200,
					'list_reem' => $arrayReem,
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

	public function reembolso_lista_true(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayReem = array();

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

				//if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
				//	$list_reembolso = DB::table("terc_reembolso_main AS reem_main")
				//	->join("terc_reembolso_solicitud AS reem_soli", "reem_main.last_version", "=", "reem_soli.id")
				//	->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
				//	->where("reem_main.status_reem",TRUE)
				//	->where("emp.empresa_token",$usuario->empresa_token)
				//	->orderBy("reem_main.folio_reem", "DESC")->get();
				//} else {
        //}
        $list_reembolso = DB::table("terc_reembolso_main AS reem_main")
        ->join("terc_reembolso_solicitud AS reem_soli", "reem_main.last_version", "=", "reem_soli.id")
        ->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
        ->join("fnzs_catalogo_acreedores AS acree", "reem_main.user_acreedor", "=", "acree.id")
        //->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
        ->where(["reem_soli.status_activacion" => TRUE, "reem_main.status_reem" => TRUE])
        ->whereIn('acree.id', function ($sub) use($usuario) {
          $sub->select('acreedor')->from('teci_usuarios_catalogo')
          ->where('acree.acr_habilita_reembolsos',TRUE)
          ->where("emp.empresa_token",$usuario->empresa_token)
          ->where('teci_usuarios_catalogo.usuario_token',$usuario->user_token);
        })
        ->orWhereIn('acree.acr_proveedor_vinculado', function ($sub) use($usuario) {
          $sub->select('catprov.id')->from('eegr_catalogo_proveedores AS catprov')
          ->join("main_proveedor_usuario AS relpu", "catprov.id", "=", "relpu.proveedor")
          ->join("teci_usuarios_catalogo AS users", "relpu.usuario", "=", "users.id")
          ->where("emp.empresa_token",$usuario->empresa_token)
          ->where('acree.acr_habilita_reembolsos',TRUE)
          ->where('catprov.habilitado_para_reembolsos',TRUE)
          ->where('users.usuario_token',$usuario->user_token);
        })
        ->orderBy("reem_main.folio_reem", "DESC")->get();
        
				foreach ($list_reembolso as $vremb) {
					//da_te_default_timezone_set($vremb->zona_horaria);

					$token_reem = $vremb->token_reem;
					$tkn_reem_soli = $vremb->token_solicitud_reem;

					$fecha_solicitud = $vremb->fecha_sistema;
					$date_solicitud = gmdate('Y-m-d H:i:s', $vremb->fecha_sistema);

					$iva_final = 0;
					$importe_final = 0;

					$folio_reem = $vremb->post_folio_reem == NULL ? 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem) : 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem) . '-' . $vremb->post_folio_reem;

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

          $nombreEmiPers = $JwtAuth->desencriptar($vremb->acr_titular);

					$selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
					->join("sos_personas AS people", "emp.persona", "=", "people.id")
					->where(["reem_main.token_reem" => $token_reem])->get();

					foreach ($selectNameEmpRec as $vReceptor) {
						$name_receptor = $vReceptor->abrev_nombre;
						$rfc_gen_rec = $vReceptor->rfc_generico;
						$rfc_emp_rec = $vReceptor->rfc != NULL ? $JwtAuth->desencriptar($vReceptor->rfc) : "---";
						$taxid_emp_rec = $vReceptor->tax_id != NULL ? $JwtAuth->desencriptar($vReceptor->tax_id) : "---";
					}

					$soli_reem = DB::table("terc_reembolso_main AS reem_main")
					->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
					->where(["reem_soli.status_activacion" => TRUE, "reem_main.token_reem" => $token_reem])
					->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

					$soli_reem_list = count($soli_reem);
					$soli_reem_auth_vhm = 0;
					$soli_reem_auth_egr = 0;
					$soli_reem_extemp = 0;
					$soli_reem_finish = 0;

					$total_reem = 0;
					$total_tipo_cambio = 0;
					$moneda_entrante_string = "";
					$moneda_entrante_decimales = 0;
					$total_reem_saliente = 0;
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

						$moneda_entrante_string = $vSoliR->moneda_entrante;
						$moneda_entrante_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);

						$total_tipo_cambio = $vSoliR->tipo_cambio;
						$resultante = $vSoliR->importe_entrante * $vSoliR->tipo_cambio;
						$total_reem_saliente = $total_reem_saliente + $resultante;
					}

					if ($soli_reem_auth_vhm == 0) {
						$reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(137,4,0,0.7)95%,rgba(255,41,34,0.7)100%)!important;";
					} else if ($soli_reem_auth_vhm != $soli_reem_list) {
						$reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(180,161,0,0.7)95%,rgba(255,235,99,0.7)100%)!important;";
					} else if ($soli_reem_auth_vhm == $soli_reem_list) {
						$reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255, 0.2)80%,rgba(37,92,0,0.7)95%,rgba(56,139,1,0.7)100%)!important;";
					}

					if ($soli_reem_auth_egr == 0) {
						$reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(137,4,0,0.7)95%,rgba(255,41,34,0.7)100%)!important;";
					} else if ($soli_reem_auth_egr != $soli_reem_list) {
						$reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(180,161,0,0.7)95%,rgba(255,235,99,0.7)100%)!important;";
					} else if ($soli_reem_auth_egr == $soli_reem_list) {
						$reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255, 0.2)80%,rgba(37,92,0,0.7)95%,rgba(56,139,1,0.7)100%)!important;";
					}

					$reem_canceled = $soli_reem_extemp == $soli_reem_list ? "background-color: lightgray!important;" : "";

					$sql_total_reem = DB::select("SELECT FORMAT(?,2) AS final_format", [$total_reem]);
					$final_importe = $sql_total_reem[0]->final_format;

					$fecha_auth_pay = null;
					$fecha_auth_pago_by_reem = DB::select("SELECT pay_ord.fecha_autorizacion_pay AS fecha_autorizacion_pay FROM fnzs_pagos_orden AS pay_ord JOIN terc_reembolso_main AS reem_main 
            WHERE pay_ord.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);
					$fecha_auth_pago_by_buy = DB::select("SELECT pay_ord.fecha_autorizacion_pay FROM fnzs_pagos_orden AS pay_ord JOIN eegr_compras AS buy 
             JOIN terc_reembolso_main AS reem_main WHERE pay_ord.factura_compra = buy.id AND buy.reembolso_vinculado_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);
					
          if (!empty($fecha_auth_pago_by_reem)) {
  					foreach ($fecha_auth_pago_by_reem as $fAuthOrd) {
              //echo $fAuthOrd->fecha_autorizacion_pay;
              $fecha_auth_pay = $fAuthOrd->fecha_autorizacion_pay != NULL ? gmdate('Y-m-d H:i:s', $fAuthOrd->fecha_autorizacion_pay) : null;
            }
          } else if (!empty($fecha_auth_pago_by_buy)) {
            foreach ($fecha_auth_pago_by_buy as $fAuthBuy) {
              //echo $fAuthBuy->fecha_autorizacion_pay;
              $fecha_auth_pay = $fAuthBuy->fecha_autorizacion_pay != NULL ? gmdate('Y-m-d H:i:s', $fAuthBuy->fecha_autorizacion_pay) : null;
            }
          }
          
					$pago_tent_date = null;
					$pago_tent_fecha = null;
					$pago_tent_time = null;
					$btnp_pago_tent_icon = null;
					$btnp_pago_tent_color = null;

					$fecha_pago_tentativa_by_reem = DB::select("SELECT pay_ord.tentativa_pago AS tentativa FROM fnzs_pagos_orden AS pay_ord JOIN terc_reembolso_main AS reem_main 
            WHERE pay_ord.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);
					$fecha_pago_tentativa_by_buy = DB::select("SELECT pay_ord.tentativa_pago AS tentativa FROM fnzs_pagos_orden AS pay_ord JOIN eegr_compras AS buy 
             JOIN terc_reembolso_main AS reem_main WHERE pay_ord.factura_compra = buy.id AND buy.reembolso_vinculado_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);
          //var_dump($fecha_pago_tentativa_by_reem);
					//var_dump($fecha_autorizacion_pago);

          if (!empty($fecha_pago_tentativa_by_reem)) {
            foreach ($fecha_pago_tentativa_by_reem as $fPayTent) {
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
                  $btnp_pago_tent_color = "btn btn_extend bg-green-600";
                } else if ($pago_tent_horas > 0 && $pago_tent_horas < 24) {
                  $btnp_pago_tent_color = "btn btn_extend bg-yellow-500";
                } else if ($pago_tent_horas <= 0) {
                  $btnp_pago_tent_color = "btn btn_extend text-bg-danger rounded-3";
                }
              }
            }
          } else if (!empty($fecha_pago_tentativa_by_buy)) {
            foreach ($fecha_pago_tentativa_by_buy as $fpTentBuy) {
              if ($fpTentBuy->tentativa != NULL) {
                $pago_tent_date = $fpTentBuy->tentativa;
                $pago_tent_fecha = gmdate('Y-m-d H:i:s', $fpTentBuy->tentativa);
                $pago_tent_inicial_time = $fpTentBuy->tentativa - time();
                $days_pago_tent = floor($pago_tent_inicial_time / (60 * 60 * 24));
                $pago_tent_inicial_time %= (60 * 60 * 24);
                $hours_pago_tent = floor($pago_tent_inicial_time / (60 * 60));
                $pago_tent_inicial_time %= (60 * 60);
                $min_pago_tent = floor($pago_tent_inicial_time / 60);
                $pago_tent_inicial_time %= 60;
                $sec_pago_tent = $pago_tent_inicial_time;
                $pago_tent_time = $days_pago_tent . " días,$hours_pago_tent:$min_pago_tent:$sec_pago_tent"; //
  
                $pago_tent_horas = ($fpTentBuy->tentativa - time()) / 3600;
                $btnp_pago_tent_icon = "fa-solid fa-traffic-light";
                if ($pago_tent_horas > 24) {
                  $btnp_pago_tent_color = "btn btn_extend bg-green-600";
                } else if ($pago_tent_horas > 0 && $pago_tent_horas < 24) {
                  $btnp_pago_tent_color = "btn btn_extend bg-yellow-500";
                } else if ($pago_tent_horas <= 0) {
                  $btnp_pago_tent_color = "btn btn_extend text-bg-danger rounded-3";
                }
              }
            }
          }

					$pago_done_fecha = null;
					$pago_done_icon = null;
					$pago_done_color = null;
					$fecha_pago_realizado_by_reem = DB::select("SELECT pay_ord.fecha_contabilizacion_ordenPago AS fecha_pago FROM fnzs_pagos_orden AS pay_ord JOIN terc_reembolso_main AS reem_main 
            WHERE pay_ord.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);
					//var_dump($fecha_autorizacion_pago);
					/*$fecha_pago_realizado_by_buy = DB::select("SELECT pay_ord.fecha_contabilizacion_ordenPago AS fecha_pago FROM fnzs_pagos_orden AS pay_ord JOIN eegr_compras AS buy 
            JOIN terc_reembolso_main AS reem_main WHERE pay_ord.factura_compra = buy.id AND buy.reembolso_vinculado_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);*/
					$fecha_pago_realizado_by_buy = DB::select("SELECT buy.fecha_contabilizacion AS fecha_pago FROM eegr_compras AS buy JOIN terc_reembolso_main AS reem_main 
            WHERE buy.reembolso_vinculado_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);

          if (!empty($fecha_pago_realizado_by_reem)) {
            foreach ($fecha_pago_realizado_by_reem as $fPayMent) {
              if ($fPayMent->fecha_pago != NULL) {
                $pago_done_fecha = gmdate('Y-m-d H:i:s', $fPayMent->fecha_pago);
                $pago_done_icon = "fa-solid fa-check-double";
                $pago_done_color = "btn btn_extend bg-green-600";
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
                $btnp_horas_pago_done_color = "btn btn_extend bg-green-600";
  
                $pago_done_horas = ($pago_tent_date - $fPayMent->fecha_pago) / 3600;
                $btnp_pago_tent_icon = "fa-solid fa-traffic-light";
                if ($pago_done_horas > 24) {
                  $btnp_pago_tent_color = "btn btn_extend bg-green-600";
                } else if ($pago_done_horas > 0 && $pago_done_horas < 24) {
                  $btnp_pago_tent_color = "btn btn_extend bg-yellow-500";
                } else if ($pago_done_horas <= 0) {
                  $btnp_pago_tent_color = "btn btn_extend text-bg-danger rounded-3";
                }
              }
            }
          } else if (!empty($fecha_pago_realizado_by_buy)) {
            foreach ($fecha_pago_realizado_by_buy as $fPayMent) {
              if ($fPayMent->fecha_pago != NULL) {
                $pago_done_fecha = gmdate('Y-m-d H:i:s', $fPayMent->fecha_pago);
                $pago_done_icon = "fa-solid fa-check-double";
                $pago_done_color = "btn btn_extend bg-green-600";
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
                $btnp_horas_pago_done_color = "btn btn_extend bg-green-600";
  
                $pago_done_horas = ($pago_tent_date - $fPayMent->fecha_pago) / 3600;
                $btnp_pago_tent_icon = "fa-solid fa-traffic-light";
                if ($pago_done_horas > 24) {
                  $btnp_pago_tent_color = "btn btn_extend bg-green-600";
                } else if ($pago_done_horas > 0 && $pago_done_horas < 24) {
                  $btnp_pago_tent_color = "btn btn_extend bg-yellow-500";
                } else if ($pago_done_horas <= 0) {
                  $btnp_pago_tent_color = "btn btn_extend text-bg-danger rounded-3";
                }
              }
            }
          }

					$nombreRecPersVH = null;
					$fecha_respuesta_auth_vh = null;
					$time_respuesta_auth_vh = null;
					$btnp_horas_auth_vh_icon = null;
					$btnp_horas_auth_vh_color = null;

					$nombreRecPersEGR = null;
					$fecha_respuesta_auth_egr = null;
					$time_respuesta_auth_egr = null;
					$btnp_horas_auth_egr_icon = null;
					$btnp_horas_auth_egr_color = null;

					if ($vremb->user_receptor_vh != NULL) {
						$selectPersVHEmpRec = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
							->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
							->where(["reem_main.token_reem" => $token_reem])->get();

						foreach ($selectPersVHEmpRec as $vPrec) {
							$nombreRecPersVH = $JwtAuth->desencriptar($vPrec->paterno) .
								" " . $JwtAuth->desencriptar($vPrec->materno) .
								" " . $JwtAuth->desencriptar($vPrec->nombre);
						}
						if ($soli_reem_auth_vhm == $soli_reem_list) {
							$fecha_respuesta_auth_vh = gmdate('Y-m-d H:i:s', $vremb->last_revision_vh);
							$time_inicial_auth_vh = $vremb->last_revision_vh - $vremb->fecha_sistema;
							$days_auth_vh = floor($time_inicial_auth_vh / (60 * 60 * 24));
							$time_inicial_auth_vh %= (60 * 60 * 24);
							$hours_auth_vh = floor($time_inicial_auth_vh / (60 * 60));
							$time_inicial_auth_vh %= (60 * 60);
							$min_auth_vh = floor($time_inicial_auth_vh / 60);
							$time_inicial_auth_vh %= 60;
							$sec_auth_vh = $time_inicial_auth_vh;
							$time_respuesta_auth_vh = $days_auth_vh . " días,$hours_auth_vh:$min_auth_vh:$sec_auth_vh"; //

							//$time_horas_autorizacion = ($vremb->tiempo_respuesta_autorizacion - time())/3600;
							$btnp_horas_auth_vh_icon = "fa-solid fa-check-double";
							$btnp_horas_auth_vh_color = "btn btn_extend bg-green-600";
						} else {
							$fecha_respuesta_auth_vh = gmdate('Y-m-d H:i:s', $vremb->tiempo_respuesta_auth_vh);
							$time_inicial_auth_vh = $vremb->tiempo_respuesta_auth_vh - time();
							$days_auth_vh = floor($time_inicial_auth_vh / (60 * 60 * 24));
							$time_inicial_auth_vh %= (60 * 60 * 24);
							$hours_auth_vh = floor($time_inicial_auth_vh / (60 * 60));
							$time_inicial_auth_vh %= (60 * 60);
							$min_auth_vh = floor($time_inicial_auth_vh / 60);
							$time_inicial_auth_vh %= 60;
							$sec_auth_vh = $time_inicial_auth_vh;
							$time_respuesta_auth_vh = $days_auth_vh . " días,$hours_auth_vh:$min_auth_vh:$sec_auth_vh"; //

							$time_horas_auth_vh = ($vremb->tiempo_respuesta_auth_vh - time()) / 3600;
							$btnp_horas_auth_vh_icon = "fa-solid fa-traffic-light";
							if ($time_horas_auth_vh > 24) {
								$btnp_horas_auth_vh_color = "btn btn_extend bg-green-600";
							} else if ($time_horas_auth_vh > 0 && $time_horas_auth_vh < 24) {
								$btnp_horas_auth_vh_color = "btn btn_extend bg-yellow-500";
							} else if ($time_horas_auth_vh <= 0) {
								$btnp_horas_auth_vh_color = "btn btn_extend text-bg-danger rounded-3";
							}
						}
					}

					if ($vremb->user_receptor_egr != NULL) {
						$selectPersEGREmpRec = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
							->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
							->where(["reem_main.token_reem" => $token_reem])->get();

						foreach ($selectPersEGREmpRec as $vPrec) {
							$nombreRecPersEGR = $JwtAuth->desencriptar($vPrec->paterno) .
								" " . $JwtAuth->desencriptar($vPrec->materno) .
								" " . $JwtAuth->desencriptar($vPrec->nombre);
						}
						if ($soli_reem_auth_egr == $soli_reem_list) {
							$fecha_respuesta_auth_egr = gmdate('Y-m-d H:i:s', $vremb->last_revision_egr);
							$time_inicial_auth_egr = $vremb->last_revision_egr - $vremb->fecha_sistema;
							$days_auth_egr = floor($time_inicial_auth_egr / (60 * 60 * 24));
							$time_inicial_auth_egr %= (60 * 60 * 24);
							$hours_auth_egr = floor($time_inicial_auth_egr / (60 * 60));
							$time_inicial_auth_egr %= (60 * 60);
							$min_auth_egr = floor($time_inicial_auth_egr / 60);
							$time_inicial_auth_egr %= 60;
							$sec_auth_egr = $time_inicial_auth_egr;
							$time_respuesta_auth_egr = $days_auth_egr . " días,$hours_auth_egr:$min_auth_egr:$sec_auth_egr"; //

							//$time_horas_autorizacion = ($vremb->tiempo_respuesta_autorizacion - time())/3600;
							$btnp_horas_auth_egr_icon = "fa-solid fa-check-double";
							$btnp_horas_auth_egr_color = "btn btn_extend bg-green-600";
						} else {
							$fecha_respuesta_auth_egr = gmdate('Y-m-d H:i:s', $vremb->tiempo_respuesta_auth_egr);
							$time_inicial_auth_egr = $vremb->tiempo_respuesta_auth_egr - time();
							$days_auth_egr = floor($time_inicial_auth_egr / (60 * 60 * 24));
							$time_inicial_auth_egr %= (60 * 60 * 24);
							$hours_auth_egr = floor($time_inicial_auth_egr / (60 * 60));
							$time_inicial_auth_egr %= (60 * 60);
							$min_auth_egr = floor($time_inicial_auth_egr / 60);
							$time_inicial_auth_egr %= 60;
							$sec_auth_egr = $time_inicial_auth_egr;
							$time_respuesta_auth_egr = $days_auth_egr . " días,$hours_auth_egr:$min_auth_egr:$sec_auth_egr"; //

							$time_horas_auth_egr = ($vremb->tiempo_respuesta_auth_egr - time()) / 3600;
							$btnp_horas_auth_egr_icon = "fa-solid fa-traffic-light";
							if ($time_horas_auth_egr > 24) {
								$btnp_horas_auth_egr_color = "btn btn_extend bg-green-600";
							} else if ($time_horas_auth_egr > 0 && $time_horas_auth_egr < 24) {
								$btnp_horas_auth_egr_color = "btn btn_extend bg-yellow-500";
							} else if ($time_horas_auth_egr <= 0) {
								$btnp_horas_auth_egr_color = "btn btn_extend text-bg-danger rounded-3";
							}
						}
					}

					if ($vremb->user_receptor_vh != NULL) {
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

					$eliminacion_disponbible = $soli_reem_auth_vhm == 0 && $soli_reem_auth_egr == 0 ? true : false;

					$total_anexos = DB::table("sos_documentos AS docs")
          ->join("terc_reembolso_main AS reem_main", "docs.reembolso_main", "=", "reem_main.id")
          ->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
          ->where(["docs.status_documento" => TRUE,"reem_soli.status_activacion" => TRUE,"reem_main.token_reem" => $token_reem])
          ->count();

					$row = array(
						"token_reem" => $token_reem,
						"folio_reem" => $folio_reem,
						"fecha_solicitud" => $fecha_solicitud,
						"date_solicitud" => $date_solicitud,
						"name_emisor" => $name_emisor,
						"rfc_gen_emi" => $rfc_gen_emi,
						"rfc_emp_emi" => $rfc_emp_emi,
						"taxid_emp_emi" => $taxid_emp_emi,
						"nombreEmiPers" => $nombreEmiPers,
						"importe_total" => "$" . number_format($total_reem, $moneda_entrante_decimales, '.', ','),
						"moneda_entrante" => $moneda_entrante_string,
						"moneda_entrante_decimales" => $moneda_entrante_decimales,
						"total_tipo_cambio" => "$" . $total_tipo_cambio,
						"total_reem_saliente" => "$" . number_format($total_reem_saliente, $moneda_entrante_decimales, '.', ','),
						"name_receptor" => $name_receptor,
						"rfc_gen_rec" => $rfc_gen_rec,
						"rfc_emp_rec" => $rfc_emp_rec,
						"taxid_emp_rec" => $taxid_emp_rec,
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
						"reem_canceled" => $reem_canceled,
						"fecha_respuesta_auth_vh" => $fecha_respuesta_auth_vh,
						"time_respuesta_auth_vh" => $time_respuesta_auth_vh,
						"btnp_horas_auth_vh_icon" => $btnp_horas_auth_vh_icon,
						"btnp_horas_auth_vh_color" => $btnp_horas_auth_vh_color,
						"fecha_respuesta_auth_egr" => $fecha_respuesta_auth_egr,
						"time_respuesta_auth_egr" => $time_respuesta_auth_egr,
						"btnp_horas_auth_egr_icon" => $btnp_horas_auth_egr_icon,
						"btnp_horas_auth_egr_color" => $btnp_horas_auth_egr_color,
						"eliminacion_disponbible" => $eliminacion_disponbible,
						"progress" => $percent_terminado,
						"total_anexos" => $total_anexos,
						//"payment_auth_date" => "---",
						//"payment_date" => "---",
					);
					$arrayReem[] = $row;
				}

				$dataMensaje = array(
					'status' => 'success',
					'code' => 200,
					'list_reem' => $arrayReem,
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

	public function reembolso_deshabilitar(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"token_reem" => "required|string",
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
				$token_reem = $parametrosArray["token_reem"];

				if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
					$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->where(["reem_main.status_reem" => TRUE, "reem_main.token_reem" => $token_reem, "emp.empresa_token" => $usuario->empresa_token])->get();
				} else {
					$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
						->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
						->where(["reem_main.token_reem" => $token_reem, "reem_main.status_reem" => TRUE, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
				}
				//echo count($reembolso_main_selected);

				if (count($reembolso_main_selected) == 1) {
					foreach ($reembolso_main_selected as $vremb) {
						$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main ,people.paterno,people.materno,people.nombre FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.empleado = pers.id AND pers.id = users.empleado
                            AND pers.empleado_name = people.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);
						foreach ($selectEmp as $vUser) {
							$pers_ejecuta = $JwtAuth->desencriptarNombres($vUser->paterno, $vUser->materno, $vUser->nombre);
						}
						//da_te_default_timezone_set($vremb->zona_horaria);

						$fecha_solicitud = gmdate('Y-m-d H:i:s', $vremb->fecha_sistema);

						if ($vremb->post_folio_reem == NULL) {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem);
						} else {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem) . '-' . $vremb->post_folio_reem;
						}

						//emisor
						$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
							->join("sos_personas AS people", "emp.persona", "=", "people.id")
							->where(["reem_main.token_reem" => $vremb->token_reem])->get();

						foreach ($selectNameEmpEmi as $vEmisor) {
							$name_emisor = $vEmisor->abrev_nombre;
						}

						$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
							->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
							->where(["reem_main.token_reem" => $vremb->token_reem])->get();

						foreach ($selectPersEmpEmi as $vPemi) {
							$name_pers_emisor = $JwtAuth->desencriptarNombres($vPemi->paterno, $vPemi->materno, $vPemi->nombre);
						}

						//receptor 
						$selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
							->join("sos_personas AS people", "emp.persona", "=", "people.id")
							->where(["reem_main.token_reem" => $vremb->token_reem])->get();

						foreach ($selectNameEmpRec as $vReceptor) {
							$name_receptor = $vReceptor->abrev_nombre;
						}

						$countador_soli_reem = 0;
						$soli_reem = DB::table("terc_reembolso_solicitud AS reem_soli")
						->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
						->where(["reem_soli.status_activacion" => TRUE, "reem_main.token_reem" => $token_reem])
						->get();

						foreach ($soli_reem as $vSoliR) {
							if (($vSoliR->orden_pago_auth == NULL || $vSoliR->orden_pago_auth == FALSE) && ($vSoliR->terminado == NULL || $vSoliR->terminado == FALSE) &&
								($vSoliR->autorizacion_vh == NULL || $vSoliR->autorizacion_vh == "N") && $vSoliR->autorizacion_egr == NULL
							) {
								++$countador_soli_reem;
							}
						}

						$listaPagos = DB::table("fnzs_pagos_pago AS pay")
          	->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          	->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          	->join("terc_reembolso_main AS reem_main", "order.reembolso_main", "=", "reem_main.id")
          	->where(["reem_main.token_reem" => $token_reem])
						->select("pay.id")
						->count();

						if ($countador_soli_reem == count($soli_reem) && $listaPagos == 0) {

							$delete_reem = DB::table("terc_reembolso_main")->where(["token_reem" => $vremb->token_reem])->limit(1)->update(array("status_reem" => FALSE, "fecha_delete" => time()));
							if ($delete_reem) {
								$titulo_alerta = "El reembolso con folio " . $folio_reem . " ha sido eliminado por " . $pers_ejecuta;
								if ($vremb->user_receptor_vh != NULL) {
									$selectPersEmpReceptorVH = DB::table("terc_reembolso_main AS reem_main")
									->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
									->join("teci_usuarios_catalogo AS rec_user", "pers.id", "=", "rec_user.empleado")
									->where("reem_main.token_reem",$vremb->token_reem)
									->select("rec_user.usuario_token")
									->get();

									foreach ($selectPersEmpReceptorVH as $vPrecVH) {
										$JwtAuth->notificacionPushDevices($vPrecVH->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
									}
								}

								if ($vremb->user_receptor_egr != NULL) {
									$selectPersEmpReceptorEGR = DB::table("terc_reembolso_main AS reem_main")
									->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
									->join("teci_usuarios_catalogo AS rec_user", "pers.id", "=", "rec_user.empleado")
									->where("reem_main.token_reem",$vremb->token_reem)
									->select("rec_user.usuario_token")
									->get();

									foreach ($selectPersEmpReceptorEGR as $vPrecEGR) {
										$JwtAuth->notificacionPushDevices($vPrecEGR->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
									}
								}

								$dataMensaje = array("status" => "success", 'code' => 200, "message" => "Reembolso con folio " . $folio_reem . " fue eliminado satisfactoriamente");
							} else {
								$dataMensaje = array('status' => 'error', 'code' => 200, "message" => "Error en eliminación de reembolso");
							}
						} else {
							$dataMensaje = array("status" => "success", 'code' => 200, "message" => "Error en eliminación de reembolso, hay registros que ya han sido verificados / pagos realizados");
						}
					}
				} else {
					$dataMensaje = array('status' => 'error', 'code' => 200, "message" => "Reembolso no registrado, intente nuevamente o comuniquese a soporte");
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

	public function reembolso_lista_false(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayReem = array();

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

				if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
					$list_reembolso = DB::table("terc_reembolso_main AS reem_main")
					->join("terc_reembolso_solicitud AS reem_soli", "reem_main.last_version", "=", "reem_soli.id")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->where([
					    "reem_soli.status_activacion" => TRUE,
						"reem_main.status_reem" => FALSE, 
						"emp.empresa_token" => $usuario->empresa_token
					])
					->orderBy("reem_main.folio_reem", "DESC")->get();
				} else {
					$list_reembolso = DB::table("terc_reembolso_main AS reem_main")
					->join("terc_reembolso_solicitud AS reem_soli", "reem_main.last_version", "=", "reem_soli.id")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
					->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
					->where([
					    "reem_soli.status_activacion" => TRUE,
						"reem_main.status_reem" => FALSE,
						"emp.empresa_token" => $usuario->empresa_token,
						"users.usuario_token" => $usuario->user_token
					])
					->orderBy("reem_main.folio_reem", "DESC")->get();
				}
				//echo count($list_reembolso);
				foreach ($list_reembolso as $vremb) {
					//da_te_default_timezone_set($vremb->zona_horaria);

					$token_reem = $vremb->token_reem;
					$tkn_reem_soli = $vremb->token_solicitud_reem;

					$fecha_solicitud = $vremb->fecha_sistema;
					$date_solicitud = gmdate('Y-m-d H:i:s', $vremb->fecha_sistema);

					$iva_final = 0;
					$importe_final = 0;

					$folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).(!is_null($vremb->post_folio_reem) ? '-'.$vremb->post_folio_reem : '');

					$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("sos_personas AS people", "emp.persona", "=", "people.id")
					->where("reem_main.token_reem",$token_reem)->get();

					foreach ($selectNameEmpEmi as $vEmisor) {
						$name_emisor = $vEmisor->abrev_nombre;
						$rfc_gen_emi = $vEmisor->rfc_generico;
						$rfc_emp_emi = !is_null($vEmisor->rfc) ? $JwtAuth->desencriptar($vEmisor->rfc) : "---";
						$taxid_emp_emi = !is_null($vEmisor->tax_id) ? $JwtAuth->desencriptar($vEmisor->tax_id) : "---";
					}

					$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
					->where(["reem_main.token_reem" => $token_reem])->get();

					foreach ($selectPersEmpEmi as $vPemi) {
						$nombreEmiPers = $vPemi->acr_titular ? $JwtAuth->desencriptar($vPemi->acr_titular) : '';
					}

					$selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
						->join("sos_personas AS people", "emp.persona", "=", "people.id")
						->where(["reem_main.token_reem" => $token_reem])->get();

					foreach ($selectNameEmpRec as $vReceptor) {
						$name_receptor = $vReceptor->abrev_nombre;
						$rfc_gen_rec = $vReceptor->rfc_generico;
						$rfc_emp_rec = !is_null($vReceptor->rfc) ? $JwtAuth->desencriptar($vReceptor->rfc) : "---";
						$taxid_emp_rec = !is_null($vReceptor->tax_id) != NULL ? $JwtAuth->desencriptar($vReceptor->tax_id) : "---";
					}

					$nombreRecPersVH = null;
					$nombreRecPersEGR = null;
					if ($vremb->user_receptor_vh != NULL) {
						$selectPersVHEmpRec = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
							->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
							->where(["reem_main.token_reem" => $token_reem])->get();

						foreach ($selectPersVHEmpRec as $vPrec) {
							$nombreRecPersVH = $JwtAuth->desencriptar($vPrec->paterno) .
								" " . $JwtAuth->desencriptar($vPrec->materno) .
								" " . $JwtAuth->desencriptar($vPrec->nombre);
						}
					}

					if ($vremb->user_receptor_egr != NULL) {
						$selectPersEGREmpRec = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
							->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
							->where(["reem_main.token_reem" => $token_reem])->get();

						foreach ($selectPersEGREmpRec as $vPrec) {
							$nombreRecPersEGR = $JwtAuth->desencriptar($vPrec->paterno) .
								" " . $JwtAuth->desencriptar($vPrec->materno) .
								" " . $JwtAuth->desencriptar($vPrec->nombre);
						}
					}

					$soli_reem = DB::table("terc_reembolso_main AS reem_main")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
						->where(["reem_soli.status_activacion" => TRUE, "reem_main.token_reem" => $token_reem])
						->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

					$soli_reem_list = count($soli_reem);
					$soli_reem_auth_vhm = 0;
					$soli_reem_auth_egr = 0;
					$soli_reem_extemp = 0;
					$soli_reem_finish = 0;

					$total_reem = 0;
					$total_tipo_cambio = 0;
					$moneda_entrante_string = "";
					$moneda_entrante_decimales = 0;
					$total_reem_saliente = 0;
					foreach ($soli_reem as $vSoliR) {
						$total_reem = $total_reem + $vSoliR->importe_entrante;

						if ($vSoliR->terminado == TRUE) ++$soli_reem_finish;

						if ($vSoliR->autorizacion_vh == "A" || $vSoliR->autorizacion_vh == "N") ++$soli_reem_auth_vhm;
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

						$moneda_entrante_string = $vSoliR->moneda_entrante;
						$moneda_entrante_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);

						//$soli_mon_entrante = DB::table("teci_catalogo_monedas")->where(["id" => $vSoliR->moneda_entrante])->get();
						//foreach ($soli_mon_entrante as $mon_in) {
						//  $regFolder = DB::table('terc_reembolso_solicitud')->where('token_solicitud_reem',$vSoliR->token_solicitud_reem)
						//  ->limit(1)->update(
						//    array(
						//      'moneda_entrante' => $mon_in->codigo,
						//    )
						//  );
						//}

						$total_tipo_cambio = $vSoliR->tipo_cambio;
						$resultante = $vSoliR->importe_entrante * $vSoliR->tipo_cambio;
						$total_reem_saliente = $total_reem_saliente + $resultante;
					}

					if ($soli_reem_auth_vhm == 0) {
						$reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(137,4,0,0.7)95%,rgba(255,41,34,0.7)100%)!important;";
					} else if ($soli_reem_auth_vhm != $soli_reem_list) {
						$reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(180,161,0,0.7)95%,rgba(255,235,99,0.7)100%)!important;";
					} else if ($soli_reem_auth_vhm == $soli_reem_list) {
						$reem_soli_auth_vhm_style = "background: linear-gradient(180deg,rgba(255,255,255, 0.2)80%,rgba(37,92,0,0.7)95%,rgba(56,139,1,0.7)100%)!important;";
					}

					if ($soli_reem_auth_egr == 0) {
						$reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(137,4,0,0.7)95%,rgba(255,41,34,0.7)100%)!important;";
					} else if ($soli_reem_auth_egr != $soli_reem_list) {
						$reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255,0.2)80%,rgba(180,161,0,0.7)95%,rgba(255,235,99,0.7)100%)!important;";
					} else if ($soli_reem_auth_egr == $soli_reem_list) {
						$reem_soli_auth_egr_style = "background: linear-gradient(180deg,rgba(255,255,255, 0.2)80%,rgba(37,92,0,0.7)95%,rgba(56,139,1,0.7)100%)!important;";
					}

					$reem_canceled = "";
					if ($soli_reem_extemp == $soli_reem_list) {
						$reem_canceled = "background-color: lightgray!important;";
					}

					if ($soli_reem_finish > 0) {
						$percent_result = (100 * $soli_reem_finish) / $soli_reem_list;
						$percent_terminado = $percent_result . "%";
					} else {
						$percent_terminado = "0%";
					}

					$sql_total_reem = DB::select("SELECT FORMAT(?,2) AS final_format", [$total_reem]);
					$final_importe = $sql_total_reem[0]->final_format;

					$fecha_autorizacion_pago = DB::select("SELECT MAX(pay_ord.fecha_autorizacion_pay) AS fecha_autorizacion_pay 
                        FROM fnzs_pagos_orden AS pay_ord JOIN terc_reembolso_main AS reem_main WHERE pay_ord.reembolso_main = reem_main.id
                        AND reem_main.token_reem = ?", [$token_reem]);
					//var_dump($fecha_autorizacion_pago);

					foreach ($fecha_autorizacion_pago as $fauthPay) {
						if ($fauthPay->fecha_autorizacion_pay != NULL) {
							$fecha_auth_pay = gmdate('Y-m-d H:i:s', $fauthPay->fecha_autorizacion_pay);
						} else {
							$fecha_auth_pay = null;
						}
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
								$btnp_pago_tent_color = "btn btn_extend bg-green-600";
							} else if ($pago_tent_horas > 0 && $pago_tent_horas < 24) {
								$btnp_pago_tent_color = "btn btn_extend bg-yellow-500";
							} else if ($pago_tent_horas <= 0) {
								$btnp_pago_tent_color = "btn btn_extend text-bg-danger rounded-3";
							}
						}
					}

					$pago_done_fecha = null;
					$pago_done_icon = null;
					$pago_done_color = null;

					$fecha_pago_realizado = DB::select("SELECT MAX(payment.fecha_pago) AS fecha_pago FROM fnzs_pagos_pago AS payment JOIN fnzs_pagos_pago_ordenes_vinculadas AS vinc
            JOIN fnzs_pagos_orden AS pay_ord JOIN terc_reembolso_main AS reem_main WHERE payment.id = vinc.pago_realizado AND vinc.orden_pago_vinculada = pay_ord.id 
						AND pay_ord.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$token_reem]);
					//var_dump($fecha_autorizacion_pago);

					foreach ($fecha_pago_realizado as $fPayMent) {
						if ($fPayMent->fecha_pago != NULL) {
							$pago_done_fecha = gmdate('Y-m-d H:i:s', $fPayMent->fecha_pago);
							$pago_done_icon = "fa-solid fa-check-double";
							$pago_done_color = "btn btn_extend bg-green-600";
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
							$btnp_horas_pago_done_color = "btn btn_extend bg-green-600";

							$pago_done_horas = ($pago_tent_date - $fPayMent->fecha_pago) / 3600;
							$btnp_pago_tent_icon = "fa-solid fa-traffic-light";
							if ($pago_done_horas > 24) {
								$btnp_pago_tent_color = "btn btn_extend bg-green-600";
							} else if ($pago_done_horas > 0 && $pago_done_horas < 24) {
								$btnp_pago_tent_color = "btn btn_extend bg-yellow-500";
							} else if ($pago_done_horas <= 0) {
								$btnp_pago_tent_color = "btn btn_extend text-bg-danger rounded-3";
							}
						}
					}

					//echo $time_horas_autorizacion." ";
					if ($soli_reem_auth_vhm == $soli_reem_list && $soli_reem_auth_egr == $soli_reem_list) {
						$fecha_respuesta_autorizacion = gmdate('Y-m-d H:i:s', $vremb->last_revision);
						$time_inicial_autorizacion = $vremb->last_revision - $vremb->fecha_sistema;
						$days_autorizacion = floor($time_inicial_autorizacion / (60 * 60 * 24));
						$time_inicial_autorizacion %= (60 * 60 * 24);
						$hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
						$time_inicial_autorizacion %= (60 * 60);
						$min_autorizacion = floor($time_inicial_autorizacion / 60);
						$time_inicial_autorizacion %= 60;
						$sec_autorizacion = $time_inicial_autorizacion;
						$time_respuesta_autorizacion = $days_autorizacion . " días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion"; //

						//$time_horas_autorizacion = ($vremb->tiempo_respuesta_autorizacion - time())/3600;
						$btnp_horas_auth_icon = "fa-solid fa-check-double";
						$btnp_horas_auth_color = "btn btn_extend bg-green-600";
					} else {
						$fecha_respuesta_autorizacion = gmdate('Y-m-d H:i:s', $vremb->tiempo_respuesta_autorizacion);
						$time_inicial_autorizacion = $vremb->tiempo_respuesta_autorizacion - time();
						$days_autorizacion = floor($time_inicial_autorizacion / (60 * 60 * 24));
						$time_inicial_autorizacion %= (60 * 60 * 24);
						$hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
						$time_inicial_autorizacion %= (60 * 60);
						$min_autorizacion = floor($time_inicial_autorizacion / 60);
						$time_inicial_autorizacion %= 60;
						$sec_autorizacion = $time_inicial_autorizacion;
						$time_respuesta_autorizacion = $days_autorizacion . " días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion"; //

						$time_horas_autorizacion = ($vremb->tiempo_respuesta_autorizacion - time()) / 3600;
						$btnp_horas_auth_icon = "fa-solid fa-traffic-light";
						if ($time_horas_autorizacion > 24) {
							$btnp_horas_auth_color = "btn btn_extend bg-green-600";
						} else if ($time_horas_autorizacion > 0 && $time_horas_autorizacion < 24) {
							$btnp_horas_auth_color = "btn btn_extend bg-yellow-500";
						} else if ($time_horas_autorizacion <= 0) {
							$btnp_horas_auth_color = "btn btn_extend text-bg-danger rounded-3";
						}
					}

					$row = array(
						"token_reem" => $token_reem,
						"folio_reem" => $folio_reem,
						"fecha_solicitud" => $fecha_solicitud,
						"date_solicitud" => $date_solicitud,

						"name_emisor" => $name_emisor,
						"rfc_gen_emi" => $rfc_gen_emi,
						"rfc_emp_emi" => $rfc_emp_emi,
						"taxid_emp_emi" => $taxid_emp_emi,
						"nombreEmiPers" => $nombreEmiPers,

						"importe_total" => "$" . number_format($total_reem, $moneda_entrante_decimales, '.', ','),
						"moneda_entrante" => $moneda_entrante_string,
						"moneda_entrante_decimales" => $moneda_entrante_decimales,
						"total_tipo_cambio" => "$" . $total_tipo_cambio,
						"total_reem_saliente" => "$" . number_format($total_reem_saliente, $moneda_entrante_decimales, '.', ','),

						"name_receptor" => $name_receptor,
						"rfc_gen_rec" => $rfc_gen_rec,
						"rfc_emp_rec" => $rfc_emp_rec,
						"taxid_emp_rec" => $taxid_emp_rec,
						"nombreRecPersVH" => $nombreRecPersVH,
						"nombreRecPersEGR" => $nombreRecPersEGR,

						"fecha_respuesta_autorizacion_vhegr" => $fecha_respuesta_autorizacion,
						"time_respuesta_autorizacion_vhegr" => $time_respuesta_autorizacion,

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
						"reem_canceled" => $reem_canceled,
						"reem_percent_terminado" => $percent_terminado,
						"btnp_horas_auth_icon" => $btnp_horas_auth_icon,
						"btnp_horas_auth_color" => $btnp_horas_auth_color,
						"fecha_delete" => gmdate('Y-m-d H:i:s', $vremb->fecha_delete)
						//"payment_date" => "---",
					);
					$arrayReem[] = $row;
				}

				$dataMensaje = array(
					'status' => 'success',
					'code' => 200,
					'list_reem' => $arrayReem,
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

	public function reembolso_rehabilitar(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"token_reem" => "required|string",
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
				$token_reem = $parametrosArray["token_reem"];

				if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
					$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->where(["reem_main.status_reem" => FALSE, "reem_main.token_reem" => $token_reem, "emp.empresa_token" => $usuario->empresa_token])->get();
				} else {
					$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
						->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
						->where(["reem_main.token_reem" => $token_reem, "reem_main.status_reem" => FALSE, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
				}
				//echo count($reembolso_main_selected);
				if (count($reembolso_main_selected) == 1) {
					foreach ($reembolso_main_selected as $vremb) {
						$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main ,people.paterno,people.materno,people.nombre FROM main_empresas AS emp  
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.empleado = pers.id AND pers.id = users.empleado
                            AND pers.empleado_name = people.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);
						foreach ($selectEmp as $vUser) {
							$pers_ejecuta = $JwtAuth->desencriptarNombres($vUser->paterno, $vUser->materno, $vUser->nombre);
						}
						//da_te_default_timezone_set($vremb->zona_horaria);

						$fecha_solicitud = gmdate('Y-m-d H:i:s', $vremb->fecha_sistema);

						if ($vremb->post_folio_reem == NULL) {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem);
						} else {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem) . '-' . $vremb->post_folio_reem;
						}

						//emisor
						$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
							->join("sos_personas AS people", "emp.persona", "=", "people.id")
							->where(["reem_main.token_reem" => $vremb->token_reem])->get();

						foreach ($selectNameEmpEmi as $vEmisor) {
							$name_emisor = $vEmisor->abrev_nombre;
						}

						$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
							->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
							->where(["reem_main.token_reem" => $vremb->token_reem])->get();

						foreach ($selectPersEmpEmi as $vPemi) {
							$name_pers_emisor = $JwtAuth->desencriptarNombres($vPemi->paterno, $vPemi->materno, $vPemi->nombre);
						}

						//receptor 
						$selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
							->join("sos_personas AS people", "emp.persona", "=", "people.id")
							->where(["reem_main.token_reem" => $vremb->token_reem])->get();

						foreach ($selectNameEmpRec as $vReceptor) {
							$name_receptor = $vReceptor->abrev_nombre;
						}

						$delete_reem = DB::table("terc_reembolso_main")->where(["token_reem" => $vremb->token_reem])->limit(1)->update(array("status_reem" => TRUE, "fecha_delete" => NULL));
						if ($delete_reem) {
							$titulo_alerta = "El reembolso con folio " . $folio_reem . " ha sido restaurado por " . $pers_ejecuta;
							if ($vremb->user_receptor_vh != NULL) {
								$selectPersEmpReceptorVH = DB::table("terc_reembolso_main AS reem_main")
									->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
									->join("teci_usuarios_catalogo AS rec_user", "pers.id", "=", "rec_user.empleado")
									->where(["reem_main.token_reem" => $vremb->token_reem])->get();

								foreach ($selectPersEmpReceptorVH as $vPrecVH) {
									$JwtAuth->notificacionPushDevices($vPrecVH->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
								}
							}

							if ($vremb->user_receptor_egr != NULL) {
								$selectPersEmpReceptorEGR = DB::table("terc_reembolso_main AS reem_main")
									->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
									->join("teci_usuarios_catalogo AS rec_user", "pers.id", "=", "rec_user.empleado")
									->where(["reem_main.token_reem" => $vremb->token_reem])->get();

								foreach ($selectPersEmpReceptorEGR as $vPrecEGR) {
									$JwtAuth->notificacionPushDevices($vPrecEGR->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
								}
							}

							$dataMensaje = array("status" => "success", 'code' => 200, "message" => "Reembolso con folio " . $folio_reem . " fue restaurado satisfactoriamente");
						} else {
							$dataMensaje = array('status' => 'error', 'code' => 200, "message" => "Error en restauración de reembolso");
						}
						//status_reem
						//fecha_delete
					}
				} else {
					$dataMensaje = array('status' => 'error', 'code' => 200, "message" => "Reembolso no registrado, intente nuevamente o comuniquese a soporte");
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

	public function reembolso_detalle(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayReem = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"token_reem" => "required|string",
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
				$token_reem = $parametrosArray["token_reem"];

				$decimalesMoneda = DB::select("SELECT emp.e_moneda_code,emp.e_moneda_decimales FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                    WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

				if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
					$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->where(["reem_main.token_reem" => $token_reem, "emp.empresa_token" => $usuario->empresa_token])->get();
				} else {
					$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
						->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
						->where(["reem_main.token_reem" => $token_reem, "emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token])->get();
				}
				//echo count($reembolso_main_selected);
				foreach ($reembolso_main_selected as $vremb) {
					//da_te_default_timezone_set($vremb->zona_horaria);
					$fecha_solicitud = gmdate('Y-m-d H:i:s', $vremb->fecha_sistema);
					$token_reem = $vremb->token_reem;

					$folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).(!is_null($vremb->post_folio_reem) ? '-'.$vremb->post_folio_reem : '');
					$pagado_bool = false;

					//emisor
					$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("sos_personas AS people", "emp.persona", "=", "people.id")
						->where(["reem_main.token_reem" => $vremb->token_reem])->get();

					foreach ($selectNameEmpEmi as $vEmisor) {
						$name_emisor = $vEmisor->abrev_nombre;
						$rfc_gen_emi = $vEmisor->rfc_generico;
						$rfc_emp_emi = $vEmisor->rfc != NULL ? $JwtAuth->desencriptar($vEmisor->rfc) : "---";
						$taxid_emp_emi = $vEmisor->tax_id != NULL ? $JwtAuth->desencriptar($vEmisor->tax_id) : "---";
					}

					$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
						->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
						->where(["reem_main.token_reem" => $vremb->token_reem])->get();

					foreach ($selectPersEmpEmi as $vPemi) {
						$name_pers_emisor = $JwtAuth->desencriptarNombres($vPemi->paterno,$vPemi->materno,$vPemi->nombre);
					}

					//receptor 
					$selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
						->join("sos_personas AS people", "emp.persona", "=", "people.id")
						->where(["reem_main.token_reem" => $vremb->token_reem])->get();

					$txt_folio_solicitud = "0";

					foreach ($selectNameEmpRec as $vReceptor) {
						$tkn_receptor = $vReceptor->empresa_token;
						$name_receptor = $vReceptor->abrev_nombre;
						$rfc_gen_receptor = $vReceptor->rfc_generico;
						$rfc_emp_receptor = $vReceptor->rfc != NULL ? $JwtAuth->desencriptar($vReceptor->rfc) : "---";
						$taxid_emp_receptor = $vReceptor->tax_id != NULL ? $JwtAuth->desencriptar($vReceptor->tax_id) : "---";
					}

					if ($vremb->user_receptor_vh != NULL) {
						$selectPersEmpReceptorVH = DB::table("terc_reembolso_main AS reem_main")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
							->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
							->where(["reem_main.token_reem" => $vremb->token_reem])->get();

						foreach ($selectPersEmpReceptorVH as $vPrecVH) {
							$name_pers_receptor_vh = $JwtAuth->desencriptarNombres($vPrecVH->paterno, $vPrecVH->materno, $vPrecVH->nombre);
						}
					} else {
						$name_pers_receptor_vh = "N/A";
					}

					if ($vremb->user_receptor_egr != NULL) {
						$selectPersEmpReceptorEGR = DB::table("terc_reembolso_main AS reem_main")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
							->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
							->where(["reem_main.token_reem" => $vremb->token_reem])->get();

						foreach ($selectPersEmpReceptorEGR as $vPrecEGR) {
							$name_pers_receptor_egr = $JwtAuth->desencriptarNombres($vPrecEGR->paterno, $vPrecEGR->materno, $vPrecEGR->nombre);
						}
					} else {
						$name_pers_receptor_egr = "N/A";
					}

					$arraySoliReem = array();
					$soli_reem = DB::table("terc_reembolso_solicitud AS reem_soli")
					->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
					->where(["reem_soli.status_activacion" => TRUE, "reem_main.token_reem" => $token_reem])
					->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

					$importe_total = 0;
					$total_reembolsado = 0;
					$total_restante = 0;
					$moneda_entrante = "";

					$num_listado = 1;
					foreach ($soli_reem as $vSoliR) {
						$importe_total = $importe_total + $vSoliR->importe_entrante;
						$fecha_registro = gmdate('Y-m-d H:i:s', $vSoliR->fecha_solicitud);
						$fecha_gasto = gmdate('Y-m-d H:i:s', $vSoliR->fecha_gasto);
						$fecha_gasto_html = $JwtAuth->convierteEpocFechaHtml($vremb->zona_horaria, $vSoliR->fecha_gasto);

						//proveedor
						$soli_r_prov = DB::table("terc_reembolso_solicitud AS reem_soli")
						->join("terc_reembolso_main AS rmain", "reem_soli.reembolso_main", "=", "rmain.id")
						->join("eegr_catalogo_proveedores AS cprov", "reem_soli.proveedor", "=", "cprov.id")
						->join("sos_personas AS prov", "cprov.proveedor", "=", "prov.id")
						->where("reem_soli.token_solicitud_reem",$vSoliR->token_solicitud_reem)
						->where("rmain.token_reem",$token_reem)
						->select('cprov.token_cat_proveedores','prov.nombre_extendido','prov.rfc_generico','prov.rfc','prov.tax_id')
						->first();

						$tkn_prov = $soli_r_prov ? $soli_r_prov->token_cat_proveedores : "";
						$name_prov = $soli_r_prov ? $JwtAuth->desencriptar($soli_r_prov->nombre_extendido) : "";
						$rfc_generico_prov = $soli_r_prov ? $soli_r_prov->rfc_generico : "";
						$rfc_prov = $soli_r_prov && !is_null($soli_r_prov->rfc) ? $JwtAuth->desencriptar($soli_r_prov->rfc) : "";
						$taxid_prov = $soli_r_prov && !is_null($soli_r_prov->tax_id) ? $JwtAuth->desencriptar($soli_r_prov->tax_id) : "";

						$fecha_respuesta_autorizacion = gmdate('Y-m-d H:i:s', $vSoliR->tiempo_respuesta_autorizacion);
						$time_respuesta_autorizacion = "";
						if ($vSoliR->tiempo_respuesta_autorizacion > time()) {
							$time_inicial_autorizacion = $vSoliR->tiempo_respuesta_autorizacion - time();

							$days_autorizacion = floor($time_inicial_autorizacion / (60 * 60 * 24));
							$time_inicial_autorizacion %= (60 * 60 * 24);

							$hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
							$time_inicial_autorizacion %= (60 * 60);

							$min_autorizacion = floor($time_inicial_autorizacion / 60);
							$time_inicial_autorizacion %= 60;

							$sec_autorizacion = $time_inicial_autorizacion;
							$time_respuesta_autorizacion = $days_autorizacion . " días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion"; // 
						} else {
							$time_respuesta_autorizacion = "tiempo de respuesta terminado";
						}

						//echo $JwtAuth->encriptar($vSoliR->ticket_gasto);
						$xmlFacturaContent = array();
						$queryFacturaXMLReem = DB::table("sos_documentos AS docs")
						->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
						->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
						->where("docs.status_documento",TRUE)
						->where("docs.tipo_documento","xml")
						->where("main.token_reem",$token_reem)
						->where("reem_soli.token_solicitud_reem",$vSoliR->token_solicitud_reem)
						->get();

						foreach ($queryFacturaXMLReem as $xDoc) {
							$token_documento = $xDoc->token_documento;
							$name_documento = $JwtAuth->desencriptar($xDoc->nombre_documento);
							$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $xDoc->token_documento;

							$rowXML = array(
								"token_documento" => $token_documento,
								"ext_doc" => $xDoc->tipo_documento,
								"name_documento" => $name_documento,
								"url" => $ruta_alterna
							);
							$xmlFacturaContent[] = $rowXML;
						}

						$pdfFacturaContent = array();
						$queryFacturaPDFReem = DB::table("sos_documentos AS docs")
						->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
						->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
						->where("docs.status_documento",TRUE)
						->where("docs.tipo_documento","pdf")
						->where("main.token_reem",$token_reem)
						->where("reem_soli.token_solicitud_reem",$vSoliR->token_solicitud_reem)->get();

						foreach ($queryFacturaPDFReem as $pdfDoc) {
							$token_documento = $pdfDoc->token_documento;
							$name_documento = $JwtAuth->desencriptar($pdfDoc->nombre_documento);
							$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $pdfDoc->token_documento;

							$rowDet = array(
								"token_documento" => $token_documento,
								"ext_doc" => $pdfDoc->tipo_documento,
								"name_documento" => $name_documento,
								"url" => $ruta_alterna
							);
							$pdfFacturaContent[] = $rowDet;
						}

						$docsAnexosArray = array();
						$selectAnexosReem = DB::table("sos_documentos AS docs")
							->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
							->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
							->where([
								"docs.status_documento" => TRUE,
								"docs.tipo_documento" => "an",
								"main.token_reem" => $token_reem,
								"reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem,
							])->get();

						foreach ($selectAnexosReem as $vDoc) {
							$token_documento = $vDoc->token_documento;
							$tipo_doc = $vDoc->tipo_documento;
							$ext_doc = $vDoc->extension_documento;
							$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
							$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $vDoc->token_documento;
							$filepath_old = $vremb->root_tkn . "/0010-reem/" . $folio_reem . "/anexos";
							$filepath_new = $vremb->root_tkn . "/0010-reem/" . $folio_reem . "/" . $JwtAuth->generarFolio($vSoliR->folio_solicitud) . "/anexos";
							$archivo_old = Storage::path('public/root/' . $filepath_old . '/' . $JwtAuth->desencriptar($vDoc->nombre_documento));
							$archivo_new = Storage::path('public/root/' . $filepath_new . '/' . $JwtAuth->desencriptar($vDoc->nombre_documento));

							if (file_exists($archivo_old)) {
								$extension = pathinfo($archivo_old, PATHINFO_EXTENSION);
								$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
								if ($extension == 'pdf') {
									$base64 = $JwtAuth->encriptaBase64Pdf($archivo_old);
									$html = '<iframe src="' . $base64 . '" style="border-radius:25px!important;" width="100%" height="100%"></iframe>';
								}

								if ($extension == 'xml') {
									$base64 = $JwtAuth->encriptaBase64Pdf($archivo_old);
									$html = file_get_contents($archivo_old);
								}

								if ($extension == 'jpg' || $extension == 'png') {
									$base64 = $JwtAuth->encriptaBase64($archivo_old);
									$html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
								}
							} else if (file_exists($archivo_new)) {
								$extension = pathinfo($archivo_new, PATHINFO_EXTENSION);
								$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
								if ($extension == 'pdf' || $extension == 'PDF') {
									$base64 = $JwtAuth->encriptaBase64Pdf($archivo_new);
									$html = '<iframe src="' . $base64 . '" style="border-radius:25px!important;" width="100%" height="100%"></iframe>';
								}

								if ($extension == 'xml') {
									$base64 = $JwtAuth->encriptaBase64Pdf($archivo_new);
									$html = file_get_contents($archivo_new);
								}

								if ($extension == 'jpg' || $extension == 'png') {
									$base64 = $JwtAuth->encriptaBase64($archivo_new);
									$html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
								}
							} else {
								$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento) . " (inexistente)";
								$archivo = Storage::path('public/settings/dont_exist_evidencia.png');
								$base64 = $JwtAuth->encriptaBase64($archivo);
								$html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
							}

							$rowDet = array(
								"token_documento" => $vDoc->token_documento,
								"ext_doc" => $vDoc->tipo_documento,
								"name_documento" => $name_documento,
								"url" => $ruta_alterna
							);
							$docsAnexosArray[] = $rowDet;
						}

						$docsRespuestaArray = array();
						$selectDocsRespReem = DB::table("sos_documentos AS docs")
							->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
							->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
							->where([
								"docs.tipo_documento" => "re",
								"main.token_reem" => $token_reem,
								"reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem,
							])->get();

						foreach ($selectDocsRespReem as $vDoc) {
							$token_documento = $vDoc->token_documento;
							$tipo_doc = $vDoc->tipo_documento;
							$ext_doc = $vDoc->extension_documento;
							$nombre_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);

							$filepath = $vremb->root_tkn . "/0010-reem/" . $folio_reem . "/anexos";
							$archivo = Storage::path('public/root/' . $filepath . '/' . $nombre_documento);
							$extension = pathinfo($archivo, PATHINFO_EXTENSION);

							if ($extension == 'pdf') {
								$base64 = $JwtAuth->encriptaBase64Pdf($archivo);
								$html = '<iframe src="' . $base64 . '" style="border-radius:25px!important;" width="100%" height="100%"></iframe>';
							}

							if ($extension == 'jpg' || $extension == 'png') {
								$base64 = $JwtAuth->encriptaBase64($archivo);
								$html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
							}

							$rowDet = array(
								"token_documento" => $token_documento,
								"ext_doc" => $extension,
								"name_documento" => $nombre_documento,
								"html" => $html,
							);
							$docsRespuestaArray[] = $rowDet;
						}

						$autorizacion_vh = null;
						if ($vSoliR->autorizacion_vh != NULL) $autorizacion_vh = $vSoliR->autorizacion_vh;

						$select_list_auth_vh = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_vh,r_auth.comentarios 
                                FROM terc_reembolso_autorizacion_vh AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                                WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id 
                                AND s_soli.token_solicitud_reem = ?", [$token_reem, $vSoliR->token_solicitud_reem]);

						$max_auth_vh = null;
						$fecha_registro_auth_vh = "";
						$hora_registro_auth_vh = "";
						$comments_auth_vh = "";

						if ($autorizacion_vh != null && $autorizacion_vh != "N" && count($select_list_auth_vh) > 0) {
							if (end($select_list_auth_vh)->autorizacion_vh == "A") $max_auth_vh = true;
							if (end($select_list_auth_vh)->autorizacion_vh == "D") $max_auth_vh = false;
							$fecha_registro_auth_vh = gmdate('Y-m-d H:i:s', end($select_list_auth_vh)->fecha_registro);
							$hora_registro_auth_vh = date('H:i:s', end($select_list_auth_vh)->fecha_registro);
							$comments_auth_vh = $JwtAuth->desencriptar(end($select_list_auth_vh)->comentarios);
						}

            $autorizacion_egr = $vSoliR->autorizacion_egr ? true : false;

						$select_folio_auth_egr = DB::select(
							"SELECT r_auth.id FROM terc_reembolso_autorizacion_egr AS r_auth 
                            JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                            AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",
							[$token_reem, $vSoliR->token_solicitud_reem]
						);

						if (count($select_folio_auth_egr) == 0) {
							$max_auth_egr = false;
							$fecha_registro_auth_egr = "";
							$hora_registro_auth_egr = "";
							$comments_auth_egr = "";
						} else {
							$select_max_auth_egr = DB::select(
								"SELECT fecha_registro,autorizacion_egr,comentarios 
                                FROM terc_reembolso_autorizacion_egr WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_egr AS r_auth 
                                JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                                WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                                AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",
								[$token_reem, $vSoliR->token_solicitud_reem]
							);
							$max_auth_egr = $select_max_auth_egr[0]->autorizacion_egr ? true : false;

							$fecha_registro_auth_egr = gmdate('Y-m-d H:i:s', $select_max_auth_egr[0]->fecha_registro);
							$hora_registro_auth_egr = date('H:i:s', $select_max_auth_egr[0]->fecha_registro);
							$comments_auth_egr = $JwtAuth->desencriptar($select_max_auth_egr[0]->comentarios);
						}

            $terminado = $vSoliR->terminado ? true : false;
            $pagado_bool = $vSoliR->terminado ? true : false;

						if ($vSoliR->autorizacion_vh == TRUE && $vSoliR->autorizacion_egr == TRUE && $vSoliR->terminado == TRUE) {
							$total_reembolsado = $total_reembolsado + $vSoliR->importe_entrante;
						}

						$moneda_origen_tkn = $vSoliR->moneda_entrante;
						$moneda_origen_codigo = $vSoliR->moneda_entrante;
						$moneda_origen_name = $vSoliR->moneda_entrante;
						$moneda_entrante = $vSoliR->moneda_entrante;
						$moneda_origen_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);

						$reem_importe_resultante = number_format($vSoliR->importe_entrante * $vSoliR->tipo_cambio, $moneda_origen_decimales, '.', ',');

						$soli_row = array(
							"token_solicitud_reem" => $vSoliR->token_solicitud_reem,
							"folio_solicitud" => $JwtAuth->generarFolio($vSoliR->folio_solicitud),
							"fecha_solicitud" => gmdate('Y-m-d H:i:s', $vSoliR->fecha_solicitud),
							"num_lista" => $num_listado,

							"fecha_gasto" => $fecha_gasto,
							"fecha_gasto_html" => $fecha_gasto_html,
							"ticket_gasto" => $JwtAuth->desencriptar($vSoliR->ticket_gasto),
							"pagado_a" => $vSoliR->pagado_a,
							//proveedor
							"tkn_prov" => $tkn_prov,
							"proveedor" => $name_prov,
							"rfc_generico_prov" => $rfc_generico_prov,
							"rfc_prov" => $rfc_prov,
							"taxid_prov" => $taxid_prov,
							//forma de pago
							//"fpago_token" => $vSoliR->token_formapago,
							"fpago_clave" => $vSoliR->forma_pago,
							"fpago_forma" => $JwtAuth->getFormasPagoAPI($vSoliR->forma_pago),
							//importe, moneda y tipo de cambio
							"importe_requerido" => $vSoliR->importe_entrante,
							"importe_requerido_info" => "$" . number_format($vSoliR->importe_entrante, $moneda_origen_decimales, '.', ','),

							"moneda_origen_tkn" => $moneda_origen_tkn,
							"moneda_origen_codigo" => $moneda_origen_codigo,
							"moneda_origen_name" => $moneda_origen_name,
							"moneda_origen_decimales" => $moneda_origen_decimales,

							"tipo_cambio_string" => $vSoliR->tipo_cambio,
							"tipo_cambio_format" => "$" . number_format($vSoliR->tipo_cambio, $moneda_origen_decimales, '.', ','),

							"reem_importe_resultante_simple" => $reem_importe_resultante,
							"reem_importe_resultante" => "$" . $reem_importe_resultante,

							"autorizacion_vh" => $autorizacion_vh,
							//"autorizacion_vh" => true,
							"max_auth_vh" => $max_auth_vh,
							"comments_auth_vh" => $comments_auth_vh,
							"comments_auth_vh_back" => $comments_auth_vh,
							"fecha_registro_auth_vh" => $fecha_registro_auth_vh,
							"hora_registro_auth_vh" => $hora_registro_auth_vh,

							"autorizacion_egr" => $autorizacion_egr,
							//"autorizacion_egr" => true,
							"max_auth_egr" => $max_auth_egr,
							"comments_auth_egr" => $comments_auth_egr,
							"comments_auth_egr_back" => $comments_auth_egr,
							"fecha_registro_auth_egr" => $fecha_registro_auth_egr,
							"hora_registro_auth_egr" => $hora_registro_auth_egr,

							"terminado" => $terminado,

							"observaciones" => $JwtAuth->desencriptar($vSoliR->motivo_reem),
							"fecha_respuesta" => $fecha_respuesta_autorizacion,
							"time_respuesta" => $time_respuesta_autorizacion,
							"xmlFacturaContent" => $xmlFacturaContent,
							"pdfFacturaContent" => $pdfFacturaContent,
							"anexos" => $docsAnexosArray,
              "carga_docs_modal" => false,
							"docsRespuesta" => $docsRespuestaArray,
						);
						++$num_listado;
						$arraySoliReem[] = $soli_row;
					}

					$total_restante = $importe_total - $total_reembolsado;

					$arrayReemRelComi = array();
					$query_reem_comi = DB::table("sos_reembolsos_comisiones_rel AS comi_rel")
					->join("terc_comisiones_main AS comi_main", "comi_rel.comision", "=", "comi_main.id")
					->join("terc_reembolso_main AS reem_main", "comi_rel.reembolso_main", "=", "reem_main.id")
					->where(["reem_main.token_reem" => $token_reem])->orderBy('comi_main.folio_comision', 'DESC')->get();

					foreach ($query_reem_comi as $vComi) {
						$expideComission = DB::table("terc_comisiones_main AS comi")
						->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
						->join("sos_personas AS people", "pers.empleado_name", "people.id")
						->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
						foreach ($expideComission as $vExpide) {
							$user_expide = $JwtAuth->desencriptar($vExpide->paterno)." ".$JwtAuth->desencriptar($vExpide->materno)." ".$JwtAuth->desencriptar($vExpide->nombre);
						}

            $comisionadoUser = "";
					  $comisionadoTrabQuery = DB::table("terc_comisiones_main AS comi")
					  ->join("vhum_empleados_catalogo AS pers", "comi.usuario_comision", "pers.id")
					  ->join("sos_personas AS people", "pers.empleado_name", "people.id")
					  ->where("comi.token_comision_main",$vComi->token_comision_main)
            ->select('people.paterno','people.materno','people.nombre')
            ->first();

            $comisionadoProvQuery = DB::table("terc_comisiones_main AS comi")
            ->join("eegr_catalogo_proveedores AS catprov", "comi.proveedor_comisionado", "catprov.id")
            ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
            ->where("comi.token_comision_main",$vComi->token_comision_main)
            ->select('prov.nombre_extendido')
            ->first();
            
            if ($comisionadoTrabQuery) {
              $comisionadoUser = $JwtAuth->desencriptarNombres($comisionadoTrabQuery->paterno,$comisionadoTrabQuery->materno,$comisionadoTrabQuery->nombre);
            } elseif ($comisionadoProvQuery) {
              $comisionadoUser = $JwtAuth->desencriptar($comisionadoProvQuery->nombre_extendido);
            }

            $sql_recibe_dinero = $vComi->recibe_dinero ? true : false;
						$sql_moneda = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
						$sql_dinero_recibido = "$".($vComi->recibe_dinero ? number_format($vComi->dinero_recibido, $JwtAuth->getMonedaAPI($vComi->comision_moneda), '.', ',') : number_format(0,2, '.', ','));
						$sql_dinero_recibido_simple = $vComi->recibe_dinero ? $vComi->dinero_recibido : 0;

						$sql_califica_egresos = $vComi->egresos == TRUE ? true : false;
						$sql_califica_vhum = $vComi->valor_humano == TRUE ? true : false;

						$row_comi = array(
							"token_comision_main" => $vComi->token_comision_main,
							"folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
							"fecha_comision" => gmdate('Y-m-d H:i:s', $vComi->fecha_comision),
							"comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
							"usuario_expide" => $user_expide,
							"usuario_comision" => $comisionadoUser,
							"especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
							"fecha_programada" => gmdate('Y-m-d H:i:s', $vComi->fecha_programada),
							"duracion" => $vComi->duracion,
							"recibe_dinero" => $sql_recibe_dinero,
							"dinero_recibido" => $sql_dinero_recibido,
							"dinero_recibido_simple" => $sql_dinero_recibido_simple,
							"comision_moneda_name" => $sql_moneda,
							"egresos" => $sql_califica_egresos,
							"valor_humano" => $sql_califica_vhum,
							"ubicacion_latitud" => !is_null($vComi->ubicacion_latitud) ? $vComi->ubicacion_latitud : '',
						  "ubicacion_longitud" => !is_null($vComi->ubicacion_longitud) ? $vComi->ubicacion_longitud : '',
						  "ubicacion_display_name" => !is_null($vComi->ubicacion_display_name) ? $JwtAuth->desencriptar($vComi->ubicacion_display_name) : '',
              "ubicacion_estado" => !is_null($vComi->ubicacion_estado) ? $JwtAuth->desencriptar($vComi->ubicacion_estado) : '',
              "ubicacion_municipio" => !is_null($vComi->ubicacion_municipio) ? $JwtAuth->desencriptar($vComi->ubicacion_municipio) : '',
              "ubicacion_codigo_postal" => !is_null($vComi->ubicacion_codigo_postal) ? $vComi->ubicacion_codigo_postal : '',
              "ubicacion_colonia" => !is_null($vComi->ubicacion_colonia) ? $JwtAuth->desencriptar($vComi->ubicacion_colonia) : ''
						);
						$arrayReemRelComi[] = $row_comi;
					}

					$arrayPagosRegistrados = array();
					$num_lista_pagos = 1;
					$listaPagos = DB::select("SELECT payment.token_pagos,payment.folio_pagos,payment.fecha_sistema,payment.fecha_pago,
            FORMAT(payment.monto_pago,?) AS formatMonto,payment.monto_pago,payment.tipo_cambio,payment.p_moneda,payment.concepto,
            payment.almacen,payment.personal_pago,payment.personal_autoriza,payment.empresa,payment.status_pagos,
            payment.fecha_deletePagos,payment.pago_autorizado,ordenp.fecha_sistema_ordenp,ordenp.folio_ordenPago	
            FROM fnzs_pagos_pago AS payment JOIN fnzs_pagos_pago_ordenes_vinculadas AS vinc JOIN fnzs_pagos_orden AS ordenp 
            JOIN terc_reembolso_main AS reem_main WHERE payment.id = vinc.pago_realizado AND vinc.orden_pago_vinculada = ordenp.id 
            AND ordenp.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$moneda_origen_decimales, $token_reem]);

					foreach ($listaPagos as $resListaPagos) {
						$token_forma_pago = null;
						$clave_forma_pago = null;
						$name_forma_pago = null;
						//echo "forma_pago ".$resListaPagos->forma_pago;
						if ($resListaPagos->forma_pago != NULL) {
							$pagosformaPago = DB::select("SELECT token_formapago,clave,forma FROM teci_forma_pago WHERE id = ?", [$resListaPagos->forma_pago]);
							$token_forma_pago = end($pagosformaPago)->token_formapago;
							$clave_forma_pago = end($pagosformaPago)->clave;
							$name_forma_pago = end($pagosformaPago)->forma;
						}

						$token_metodopago = null;
						$abrev_metodo_pago = null;
						$metodo_pago = null;
						if ($resListaPagos->metodo_pago != NULL) {
							$pagosmetodoPago = DB::select("SELECT token_metodopago,abrev,metodo FROM teci_metodo_pago WHERE id = ?", [$resListaPagos->metodo_pago]);
							$token_metodopago = end($pagosmetodoPago)->token_metodopago;
							$abrev_metodo_pago = end($pagosmetodoPago)->abrev;
							$metodo_pago = end($pagosmetodoPago)->metodo;
						}

						$pagosmoneda = DB::select("SELECT token_monedas,codigo,moneda FROM teci_catalogo_monedas WHERE id = ?", [$resListaPagos->p_moneda]);

						$fecha_sistema_orden_pago = $JwtAuth->generar($resListaPagos->fecha_sistema_ordenp);
						$folio_orden_pago = $JwtAuth->generarFolio($resListaPagos->folio_ordenPago);

						$namePersonalPaga = DB::table("vhum_empleados_catalogo AS pers")
						->join("sos_personas AS people", "pers.empleado_name", "people.id")
						->where('pers.id',$resListaPagos->personal_pago)
            ->get();

						if ($JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil) == 'default-profile.png') {
							$img_perfil_paga = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil)));
						} else {
							$img_perfil_paga = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $vremb->root_tkn . '/0004-vhm/catalogos/employees/' .
								$JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil) . '/' . $JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil) . '-profile.png'));
						}

						$nombre_completo_paga = $JwtAuth->desencriptar($namePersonalPaga[0]->paterno)." ".$JwtAuth->desencriptar($namePersonalPaga[0]->materno)." ".$JwtAuth->desencriptar($namePersonalPaga[0]->nombre);

						$namePersonalAutoriza = DB::table("vhum_empleados_catalogo AS pers")
						->join("sos_personas AS people", "pers.empleado_name", "people.id")
						->where('pers.id',$resListaPagos->personal_autoriza)
            ->get();

						if ($JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil) == 'default-profile.png') {
							$img_perfil_autoriza = $JwtAuth->encriptaBase64(Storage::path('public/settings/'.$JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil)));
						} else {
							$img_perfil_autoriza = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $vremb->root_tkn . '/0004-vhm/catalogos/employees/' .
								$JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil) . '/' . $JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil) . '-profile.png'));
						}

						$nombre_completo_autoriza = $JwtAuth->desencriptar($namePersonalAutoriza[0]->paterno)." ".$JwtAuth->desencriptar($namePersonalAutoriza[0]->materno)." ".$JwtAuth->desencriptar($namePersonalAutoriza[0]->nombre);

						$selectCuentas = array();
						$detalleMonedero = array();
						$cajacaja = array();
						$medio_pago = "";
						$medio_de_pago = "";
						$name_caja = "";
						$name_cuenta_banc = "";
						$name_cuenta_mone = "";

						$queryCajaRelacionada = DB::table("fnzs_pagos_pago AS pay")
						->join("fnzs_pagos_cajas_pago AS cajp", "pay.id", "=", "cajp.pago_realizado")
						->join("fnzs_catalogos_caja AS caja", "cajp.caja_relacionada", "=", "caja.id")
						->where("pay.token_pagos", $resListaPagos->token_pagos)
						->select('cajp.token_caja')->first();

						if ($queryCajaRelacionada) {
							$medio_pago = "caja";
							$medio_de_pago = "caja";
							$cajaPago = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "caja.almacen", "alm.id")
							->join("teci_direcciones AS dirubica", "alm.ubicacion", "dirubica.id")
							->join("in_egr_establecimientos_responsables AS respons", "caja.id", "respons.caja")
							->join("main_empresas AS emp", "caja.empresa", "emp.id")
							->join("vhum_empleados_catalogo AS persnl", "respons.responsable", "persnl.id")
							->join("sos_personas AS people", "persnl.personal", "people.id")
							->join("teci_usuarios_catalogo AS users", "persnl.usuario", "users.id")
							->where("caja.serv_ingresos", TRUE)
							->where("caja.token_caja", $queryCajaRelacionada->token_caja)
							->where("emp.empresa_token", $usuario->empresa_token)
							->where("users.usuario_token", $usuario->user_token)
							->get();

							foreach ($cajaPago as $resultCaja) {
								$name_caja = $JwtAuth->generar($resultCaja->no_caja) . " (" . $JwtAuth->desencriptar($resultCaja->alias_caja) . ")";
								$arrayCaja = array(
									"token_caja" => $resultCaja->token_caja,
									"alias_caja" => $JwtAuth->desencriptar($resultCaja->alias_caja),
									"caja" => $JwtAuth->generar($resultCaja->no_caja),
								);

								$cajacaja[] = $arrayCaja;
							}
						}

						$queryCuentaRelacionada = DB::table("fnzs_pagos_pago AS pay")
							->join("fnzs_pagos_cuentas_pago AS cuentp", "pay.id", "=", "cuentp.pago_realizado")
							->join("fnzs_catalogos_cuentas AS cuenta", "cuentp.cuenta_relacionada", "=", "cuenta.id")
							->where("pay.token_pagos", $resListaPagos->token_pagos)
							->select('cuentp.token_cuenta')->first();

						if ($queryCuentaRelacionada) {
							$medio_pago = "cuenta_bancaria";
							$medio_de_pago = "cuenta bancaria";

							$arrayContrato = array();
							$arrayCuenta = array();
							$arrayClabeInetr = array();
							$arraySucursal = array();
							$arrayTitular = array();
							$arrayOpcionAdicional = array();

							$respCuenta = CuentBancModelo::join("teci_bancos AS bank", "fnzs_catalogos_cuentas.banco", "bank.id")
							->join("main_empresas AS emp", "fnzs_catalogos_cuentas.empresa", "emp.id")
							->join("vhum_empleados_catalogo AS pers", "fnzs_catalogos_cuentas.responsable", "pers.id")
							->join("teci_usuarios_catalogo AS users", "pers.id", "users.empleado")
							->where([
								"fnzs_catalogos_cuentas.status" => TRUE,
								"fnzs_catalogos_cuentas.egresos" => TRUE,
								"fnzs_catalogos_cuentas.token_cuenta" => $queryCuentaRelacionada->token_cuenta,
								"emp.empresa_token" => $usuario->empresa_token,
								"users.usuario_token" => $usuario->user_token
							])
							->orwhere([
								"fnzs_catalogos_cuentas.status" => TRUE,
								"fnzs_catalogos_cuentas.v_humano" => TRUE,
								"fnzs_catalogos_cuentas.token_cuenta" => $queryCuentaRelacionada->token_cuenta,
								"emp.empresa_token" => $usuario->empresa_token,
								"users.usuario_token" => $usuario->user_token
							])->get();

							if (count($respCuenta) != 0) {
								foreach ($respCuenta as $resCuentas) {
									//da_te_default_timezone_set($vremb->zona_horaria);
									$claveBanco = $resCuentas->clave;
									$tknBancos = $resCuentas->token_bancos;

									$arrayStatusContrato = array(
										"status" => false,
										"no_contrato" => $resCuentas->contrato,
										"no_contrato_encrypt" => $resCuentas->contrato,
									);
									$arrayContrato[] = $arrayStatusContrato;

									$arrayStatusCuenta = array(
										"status" => false,
										"no_cuenta" => $resCuentas->cuenta,
										"no_cuenta_encrypt" => $resCuentas->cuenta,
									);
									$name_cuenta_banc = $JwtAuth->generar($resCuentas->folio_cuenta);
									$arrayCuenta[] = $arrayStatusCuenta;

									$arrayStatusClabInt = array(
										"status" => false,
										"clabe_inter" => $resCuentas->clabe_inter,
										"clabe_inter_encrypt" => $resCuentas->clabe_inter,
									);
									$arrayClabeInetr[] = $arrayStatusClabInt;

									if ($resCuentas->titular == '') {
										$titular = utf8_decode($JwtAuth->desencriptar('---'));
									} else {
										$titular = utf8_decode($JwtAuth->desencriptar($resCuentas->titular));
									}

									if ($resCuentas->opciones_adicionales != '-') {
										$optAdicional = json_decode($JwtAuth->desencriptar($resCuentas->opciones_adicionales));
										for ($i = 0; $i < count($optAdicional); $i++) {
											$optionAddc = array(
												"clave" => $optAdicional[$i]->clave,
												"valor" => $optAdicional[$i]->valor
											);
											$arrayOpcionAdicional[] = $optionAddc;
										}
									}

                  $egresos = $resCuentas->egresos ? true : false;

                  $v_humano = $resCuentas->v_humano ? true : false;

									$sucursal = utf8_decode($JwtAuth->desencriptar($resCuentas->sucursal));

									$moneda = DB::select("SELECT codigo,moneda FROM teci_catalogo_monedas WHERE id = ?", [$resCuentas->moneda]);
									$resMoneda = $moneda[0]->codigo . "-" . $moneda[0]->moneda;

									$arrayCuentas = array(
										"token_cuenta" => $resCuentas->token_cuenta,
										"token_bancos" => $resCuentas->token_bancos,
										"nameBanco" => $resCuentas->clave . " - " . $resCuentas->nombre_comercial,
										"alta_cuenta" => gmdate('Y-m-d H:i:s', $resCuentas->fecha_alta_cuenta),
										"folio" => $JwtAuth->generar($resCuentas->folio_cuenta),
										"contrato" => $arrayContrato,
										"cuenta" => $arrayCuenta,
										"clabe_inter" => $arrayClabeInetr,
										"sucursal" => $sucursal,
										"titular" => $titular,
										"moneda" =>  $resMoneda,
										"egresos" => $egresos,
										"v_humano" => $v_humano,
										"vigencia" => gmdate('Y-m-d H:i:s', $resCuentas->vigencia),
										"opciones_adicionales" => $arrayOpcionAdicional,
									);

									$selectCuentas[] = $arrayCuentas;
								}
							}
						}

						$queryMonederoRelacionado = DB::table("fnzs_pagos_pago AS pay")
							->join("fnzs_pagos_monederos_pago AS monedp", "pay.id", "=", "monedp.pago_realizado")
							->join("fnzs_catalogos_cuentas_monedero AS moned", "monedp.cuenta_relacionada", "=", "moned.id")
							->where("pay.token_pagos", $resListaPagos->token_pagos)
							->select('monedp.token_cuentamonedero')->first();

						if ($queryMonederoRelacionado) {
							$medio_pago = "cuenta_monedero_elect";
							$medio_de_pago = "cuenta de monedero electrónico";
							$arrayOpcionAdicionalMon = array();

							$respMonedero = CuentaMonederoModelo::join("teci_plataformas_digitales AS pdig", "fnzs_catalogos_cuentas_monedero.monedero", "pdig.id")
								->join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
								->join("vhum_empleados_catalogo AS pers", "fnzs_catalogos_cuentas_monedero.responsable", "pers.id")
								->join("teci_usuarios_catalogo AS users", "pers.id", "users.empleado")
								->where([
									"fnzs_catalogos_cuentas_monedero.status" => TRUE,
									"fnzs_catalogos_cuentas_monedero.token_cuentamonedero" => $queryMonederoRelacionado->token_cuentamonedero,
									"emp.empresa_token" => $usuario->empresa_token,
									"users.usuario_token" => $usuario->user_token
								])
								->where([
									'fnzs_catalogos_cuentas_monedero.egresos' => TRUE
								])
								->orwhere([
									'fnzs_catalogos_cuentas_monedero.v_humano' => TRUE
								])->get();

							foreach ($respMonedero as $resMonedero) {
								$cuenta_bancaria = '';
								$name_cuenta = '';
								$token_caja = '';
								$folio_caja = '';
								$alias_caja = '';

								if ($resMonedero->cuenta_banco != '') {
									$tknCount = DB::select("SELECT token_cuenta FROM fnzs_catalogos_cuentas WHERE id = ?", [$resMonedero->cuenta_banco]);
									$cuentaBancoMon = CuentBancModelo::join("main_empresas AS emp", "cuenta.empresa", "emp.id")
										->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
										->join("vhum_empleados_catalogo AS pers", "empuser.empleado", "pers.id")
										->join("teci_usuarios_catalogo AS users", "pers.id", "users.empleado")
										->where([
											'cuenta.status' => TRUE,
											'cuenta.token_cuenta' => $tknCount[0]->token_cuenta,
											'emp.empresa_token' => $usuario->empresa_token,
											'users.usuario_token' => $usuario->user_token
										])->get();
									foreach ($cuentaBancoMon as $resCuentaMon) {
										$cuenta_bancaria = $resCuentaMon->token_cuenta;
										$name_cuenta = $JwtAuth->desencriptar($resCuentaMon->cuenta);
									}
								}

								if ($resMonedero->caja != '') {
									$tokenCaja = DB::select("SELECT token_caja FROM caja WHERE id = ? ", [$resMonedero->caja]);
									$cajaMonedero = CajaModelo::join("main_empresas AS emp", "caja.empresa", "emp.id")
										->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
										->join("vhum_empleados_catalogo AS pers", "empuser.empleado", "pers.id")
										->join("teci_usuarios_catalogo AS users", "pers.id", "users.empleado")
										->where([
											'caja.status' => TRUE,
											'caja.token_caja' => $tokenCaja[0]->token_caja,
											'emp.empresa_token' => $usuario->empresa_token,
											'users.usuario_token' => $usuario->user_token
										])->get();

									foreach ($cajaMonedero as $resCajaMon) {
										$token_caja = $resCajaMon->token_caja;
										$folio_caja = $JwtAuth->generar($resCajaMon->no_caja);
										$alias_caja = $JwtAuth->desencriptar($resCajaMon->alias_caja);
									}
								}

								$referencia = $resMonedero->referencia;
								$cuenta_monedero = $resMonedero->cuenta;
								$clabeInter = $resMonedero->clabe_inter;
								$titular = $JwtAuth->desencriptar($resMonedero->titular);

								$moneda = DB::select("SELECT codigo,moneda FROM teci_catalogo_monedas WHERE id = ?", [$resMonedero->moneda]);
								$resMoneda = $moneda[0]->codigo . "-" . $moneda[0]->moneda;

								$egresos = $resMonedero->egresos ? true : false;
								$v_humano = $resMonedero->v_humano ? true : false;

								$selectManejCuenta = DB::table('fnzs_catalogos_cuentas_manejo AS man_count')
									->join("fnzs_catalogos_cuentas_monedero AS countMon", "man_count.cuenta_monedero", "countMon.id")
									->join("main_empresas AS emp", "man_count.empresa", "emp.id")
									->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
									->join("vhum_empleados_catalogo AS pers", "empuser.empleado", "pers.id")
									->join("sos_personas AS people", "pers.empleado_name", "people.id")
									->join("teci_usuarios_catalogo AS users", "pers.id", "users.empleado")
									->where([
										'man_count.cuenta_bancaria' => NULL,
										'countMon.token_cuentamonedero' => $resMonedero->token_cuentamonedero,
										'emp.empresa_token' => $usuario->empresa_token,
										'users.usuario_token' => $usuario->user_token
									])->get();

								foreach ($selectManejCuenta as $resOpciones) {
									$chequera = $resOpciones->chequera ? true : false;
									$credito = $resOpciones->credito ? true : false;
									$debito = $resOpciones->debito ? true : false;

									$arrayOptions = array(
										"token_manejocuentas" => $resOpciones->token_manejocuentas,
										"chequera" => $chequera,
										"credito" => $credito,
										"debito" => $debito,
										"valorManejo" => $resOpciones->clave_referencia,
										"token_personal" => $resOpciones->empleado_token,
										"nombre_completo" => $JwtAuth->desencriptar($resOpciones->paterno)
											. " " . $JwtAuth->desencriptar($resOpciones->materno)
											. " " . $JwtAuth->desencriptar($resOpciones->nombre),
									);
									$arrayOpcionAdicional[] = $arrayOptions;
								}

								$arrayMonedero = array(
									'token_cuentaMon' => $resMonedero->token_cuentamonedero,
									'fecha_alta_cuentamoned' => gmdate('Y-m-d H:i:s', $resMonedero->fecha_alta_cuentamoned),
									'folio' => $JwtAuth->generar($resMonedero->folio_cuentmon),

									'cuenta_bancaria' =>  $cuenta_bancaria,
									'name_cuenta_bancaria' =>  $name_cuenta,

									'token_caja' => $token_caja,
									'folio_caja' => $folio_caja,
									'alias_caja' => $alias_caja,

									'referencia' => $referencia,
									'cuenta_monedero' => $cuenta_monedero,
									'cuenta_monedero_encrypt' => $cuenta_monedero,
									'clabe_inter' => $clabeInter,
									'titular' => $titular,
									'moneda' => $resMoneda,
									'egresos' => $egresos,
									'v_humano' => $v_humano,
									'vigencia' => gmdate('Y-m-d H:i:s', $resMonedero->vigencia),
									'opciones_adicionales' => $arrayOpcionAdicionalMon,
								);
								$name_cuenta_mone = $JwtAuth->generar($resMonedero->folio_cuentmon);
								$detalleMonedero[] = $arrayMonedero;
							}
						}

						$arrayEvidencias = array();

						//$vOrd->fecha_sistema_ordenp."-".$JwtAuth->generarFolio($vOrd->folio_ordenPago)."-pago_evidencias
						//$evidenciasPagos = DB::select("SELECT evidence.nombre_documento FROM sos_documentos AS evidence JOIN fnzs_pagos_pago AS payment WHERE evidence.pago = payment.id AND payment.token_pagos = ?",[$resListaPagos->token_pagos]);

						$evidenciasPagos = DB::table("sos_documentos AS docs")
							->join("fnzs_pagos_pago AS payment", "docs.pago", "=", "payment.id")
							->where(["payment.token_pagos" => $resListaPagos->token_pagos])->get();

						foreach ($evidenciasPagos as $evidFor) {
							$token_docs = $vDoc->token_documento;
							$tipo_doc = $vDoc->tipo_documento;
							$ext_doc = $vDoc->extension_documento;

							$filepath = $vremb->root_tkn . '/0003-tes/ordenes_pagos/' . $fecha_sistema_orden_pago . '-' . $folio_orden_pago . '/pago_evidencias';
							$extension = pathinfo($archivo, PATHINFO_EXTENSION);
							$archivo = Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($evidFor->nombre_documento));

							if (file_exists($archivo)) {
								$name_documento = $JwtAuth->desencriptar($evidFor->nombre_documento);
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
								$name_documento = $JwtAuth->desencriptar($evidFor->nombre_documento) . " (inexistente)";
								$archivo = Storage::path('public/settings/dont_exist_evidencia.png');
								$base64 = $JwtAuth->encriptaBase64($archivo);
								$html = '<img class="responsive-img materialboxed imag2" style="border-radius: 25px!important;" src="' . $base64 . '">';
							}

							$rowEachEvd = array(
								"token_docs" => $token_docs,
								"ext_doc" => $extension,
								"name_documento" => $name_documento,
								"html" => $html,
							);
							$arrayEvidencias[] = $rowEachEvd;
						}

						$saldo_tipo_cambio = DB::select("SELECT FORMAT(?,?) AS saldoFormat", [$resListaPagos->tipo_cambio, $decimalesMoneda[0]->decimales]);

						$pago_autorizado = null;
						if ($resListaPagos->pago_autorizado == "F") $pago_autorizado = false;
						if ($resListaPagos->pago_autorizado == "V") $pago_autorizado = true;

						$arrayEachPagohs = array(
							"num_lista" => $num_lista_pagos,
							//"nombre_documento" => $JwtAuth->desencriptar($resListaPagos->nombre_documento),
							"token_pagos" => $resListaPagos->token_pagos,
							"folio_pagos" => $JwtAuth->generar($resListaPagos->folio_pagos),
							"fecha_sistema" => gmdate('Y-m-d H:i:s', $resListaPagos->fecha_sistema),
							"fecha_pago" => gmdate('Y-m-d H:i:s', $resListaPagos->fecha_pago),
							"medio_pago" => $medio_pago,
							"medio_de_pago" => $medio_de_pago,
							"name_caja" => $name_caja,
							"name_cuenta_banc" => $name_cuenta_banc,
							"name_cuenta_mone" => $name_cuenta_mone,
							"caja" => $cajacaja,
							"cuenta_bancaria" => $selectCuentas,
							"cuenta_monedero" => $detalleMonedero,
							"formatMonto" => "$" . $resListaPagos->formatMonto,
							"monto_pago" => $resListaPagos->monto_pago,
							"tipo_cambio" => $saldo_tipo_cambio[0]->saldoFormat,
							"token_forma_pago" => $token_forma_pago,
							"clave_forma_pago" => $clave_forma_pago,
							"forma_pago" => $name_forma_pago,
							"token_metodopago" => $token_metodopago,
							"abrev_metodo_pago" => $abrev_metodo_pago,
							"metodo_pago" => $metodo_pago,
							"moneda_token_monedas" => $pagosmoneda[0]->token_monedas,
							"moneda_codigo" => $pagosmoneda[0]->codigo,
							"moneda" => $pagosmoneda[0]->moneda,
							//"img_perfil_paga" => $img_perfil_paga,
							"nombre_completo_paga" => $nombre_completo_paga,
							"personal_autoriza" => $img_perfil_autoriza,
							"pago_autorizado" => $pago_autorizado,
							"personal_autoriza" => $nombre_completo_autoriza,
							"evidencias" => $arrayEvidencias,
						);
						$arrayPagosRegistrados[] = $arrayEachPagohs;
						++$num_lista_pagos;
					}

					if ($vremb->status_reem == TRUE) {
						$status_reem_bool = "habilitado";
						$status_reem_date = "---";
					} else {
						$status_reem_bool = "deshabilitado";
						$status_reem_date = gmdate('Y-m-d H:i:s', $vremb->fecha_delete);
					}

					if ($vremb->post_folio_reem == NULL) {
						$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem);
					} else {
						$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem) . '-' . $vremb->post_folio_reem;
					}
					//echo "moneda_entrante $moneda_entrante";

					$row = array(
						"token_reem" => $token_reem,
						"folio_reem" => $folio_reem,
						"fecha_solicitud" => $fecha_solicitud,
						//emisor
						"emisor_company" => $name_emisor,
						"nombreEmiPers" => $name_pers_emisor,
						//receptor
						"receptor_company" => $name_receptor,
						"nombreReceptorPers_vh" => $name_pers_receptor_vh,
						"nombreReceptorPers_egr" => $name_pers_receptor_egr,

						//$sql_total_reembolsado = DB::select("SELECT FORMAT(?,2) AS final_format",[$total_reembolsado]);
						//$sql_total_restante = DB::select("SELECT FORMAT(?,2) AS final_format",[$total_restante]);
						//$sql_total_importe = DB::select("SELECT FORMAT(?,2) AS final_format",[$importe_total]);

						"total_reembolsado" => "$" . number_format($total_reembolsado, $JwtAuth->getMonedaAPI($moneda_entrante), '.', ',') . " " . $moneda_entrante,
						"total_restante" => "$" . number_format($total_restante, $JwtAuth->getMonedaAPI($moneda_entrante), '.', ',') . " " . $moneda_entrante,
						"total_importe" => "$" . number_format($importe_total, $JwtAuth->getMonedaAPI($moneda_entrante), '.', ',') . " " . $moneda_entrante,
						"soliReem" => $arraySoliReem,
						"comisiones" => $arrayReemRelComi,
						"pagosRegistrados" => $arrayPagosRegistrados,
						"pagado_bool" => $pagado_bool,
						"status_reem_bool" => $status_reem_bool,
						"status_reem_date" => $status_reem_date,
					);

					$arrayReem[] = $row;
				}

				$dataMensaje = array(
					'status' => 'success',
					'code' => 200,
					'reem_det' => $arrayReem,
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

	public function reembolso_load_xml_fact(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayJust = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"tkn_solicitud" => "required|string",
				"proveedor_tkn" => "required|string",
				"dataCFDI_comprobante" => "array",
				"dataCFDIRelacionados" => "array",
				"dataCFDIEmisor" => "array",
				"dataCFDIReceptor" => "array",
				"dataCFDI_conceptos" => "array",
				"dataCFDI_impuestos_retenidos_lista" => "array",
				"dataCFDI_impuestos_trasladados_lista" => "array",
				"dataCFDIComplemento" => "array",
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
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$tkn_solicitud = $parametrosArray["tkn_solicitud"];
				$proveedor_tkn = $parametrosArray["proveedor_tkn"];
				$cfdi_comprobante = $parametrosArray["dataCFDI_comprobante"];
				$cfdi_relacionados = $parametrosArray["dataCFDIRelacionados"];
				$cfdi_emisor = $parametrosArray["dataCFDIEmisor"];
				$cfdi_receptor = $parametrosArray["dataCFDIReceptor"];
				$cfdi_conceptos = $parametrosArray["dataCFDI_conceptos"];
				$dataCFDI_impuestos_retenidos_lista = $parametrosArray["dataCFDI_impuestos_retenidos_lista"];
				$dataCFDI_impuestos_trasladados_lista = $parametrosArray["dataCFDI_impuestos_trasladados_lista"];
				$cfdi_complemento = $parametrosArray["dataCFDIComplemento"];

        $valida_reem = isset($tokenReembolso) && !empty($tokenReembolso);
        $valida_solicitud = isset($tkn_solicitud) && !empty($tkn_solicitud);

				if ($valida_reem && $valida_solicitud) {

					$list_reem = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
						->where([
						    "reem_soli.status_activacion" => TRUE,
							"reem_main.token_reem" => $tokenReembolso,
							"reem_soli.token_solicitud_reem" => $tkn_solicitud,
							"reem_main.status_reem" => TRUE,
							"emp.empresa_token" => $usuario->empresa_token,
							//"users.usuario_token" => $usuario->user_token
						])->get();

					foreach ($list_reem as $vReem) {
						//da_te_default_timezone_set($vReem->zona_horaria);
            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem).(!is_null($vReem->post_folio_reem) ? '-'.$vReem->post_folio_reem : '');
						$reembolso_main_id = DB::table("terc_reembolso_main")->where("token_reem", $vReem->token_reem)->value("id");
            $reembolso_reem_soli = DB::table("terc_reembolso_solicitud")->where("token_solicitud_reem", $vReem->token_solicitud_reem)->value("id");
            $main_empresa_id = DB::table("main_empresas")->where("empresa_token", $usuario->empresa_token)->value("id");
						$filepath = "$vReem->root_tkn/0010-reem/$folio_reem/".$JwtAuth->generarFolio($vReem->folio_solicitud)."/anexos";
          
						if (!file_exists(storage_path("/root/" . $filepath))) {
							Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
						}
            
            if (is_array($cfdi_comprobante)) {
              $cfdi_comprobante_version = '';
              $cfdi_comprobante_serie = '';
              $cfdi_comprobante_folio = '';
              $cfdi_comprobante_fecha = '';
              $cfdi_comprobante_forma_de_pago = '';
              $cfdi_comprobante_metodo_de_pago = '';
              $cfdi_comprobante_subtotal = '';
              $cfdi_comprobante_moneda = '';
              $cfdi_comprobante_tipo_de_cambio = '';
              $cfdi_comprobante_total = '';
              $cfdi_comprobante_confirmacion = '';
              $cfdi_comprobante_tipo_de_comprobante = '';
              $cfdi_comprobante_lugar_de_expedicion = '';
              $cfdi_comprobante_no_de_certificado = '';
              $cfdi_comprobante_sello = '';
              $cfdi_comprobante_certificado = '';

              foreach ($cfdi_comprobante as $vComp) {
                switch ($vComp["title"]) {
                  case 'Versión':
                    $cfdi_comprobante_version = $vComp["content"];
                    break;

                  case 'Serie':
                    $cfdi_comprobante_serie = $vComp["content"];
                    break;

                  case 'Folio':
                    $cfdi_comprobante_folio = $vComp["content"];
                    break;

                  case 'Fecha':
                    $cfdi_comprobante_fecha = $vComp["content"];
                    break;

                  case 'Forma de pago':
                    $cfdi_comprobante_forma_de_pago = $vComp["content"];
                    break;

                  case 'Subtotal':
                    $cfdi_comprobante_subtotal = $vComp["content"];
                    break;

                  case 'Moneda':
                    $cfdi_comprobante_moneda = $vComp["content"];
                    break;

                  case 'Tipo de cambio':
                    $cfdi_comprobante_tipo_de_cambio = $vComp["content"];
                    break;

                  case 'Total':
                    $cfdi_comprobante_total = $vComp["content"];
                    break;

                  case 'Confirmación':
                    $cfdi_comprobante_confirmacion = $vComp["content"];
                    break;

                  case 'Tipo de comprobante':
                    $cfdi_comprobante_tipo_de_comprobante = $vComp["content"];
                    break;

                  case 'Método de Pago':
                    $cfdi_comprobante_metodo_de_pago = $vComp["content"];
                    break;

                  case 'Lugar de Expedición':
                    $cfdi_comprobante_lugar_de_expedicion = $vComp["content"];
                    break;

                  case 'No de certificado':
                    $cfdi_comprobante_no_de_certificado = $vComp["content"];
                    break;

                  case 'Sello':
                    $cfdi_comprobante_sello = $vComp["content"];
                    break;

                  case 'Certificado':
                    $cfdi_comprobante_certificado = $vComp["content"];
                    break;

                  default:
                    # code...
                    break;
                }
              }

              $cfdi_relacionados_tipo_de_relacion = '';
              $cfdi_relacionados_uuid = '';
              foreach ($cfdi_relacionados as $CFDIr) {
                switch ($CFDIr["title"]) {
                  case 'Tipo de relación':
                    $cfdi_relacionados_tipo_de_relacion = $CFDIr["content"];
                    break;
                  case 'UUID':
                    $cfdi_relacionados_uuid = $CFDIr["content"];
                    break;

                  default:
                    //--
                    break;
                }
              }
              //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.CFDI2']);
              $cfdi_emisor_rfc = '';
              $cfdi_emisor_nombre = '';
              $cfdi_emisor_regimen_fiscal = '';
              foreach ($cfdi_emisor as $CFDIe) {
                switch ($CFDIe["title"]) {
                  case 'Rfc del emisor':
                    $cfdi_emisor_rfc = $CFDIe["content"];
                    break;
                  case 'Nombre del emisor':
                    $cfdi_emisor_nombre = $CFDIe["content"];
                    break;
                  case 'Regimen fiscal del emisor':
                    $cfdi_emisor_regimen_fiscal = $CFDIe["content"];
                    break;
                  default:
                    //--
                    break;
                }
              }

              $cfdi_receptor_rfc = '';
              $cfdi_receptor_uso_del_cfdi = '';
              foreach ($cfdi_receptor as $CFDIReceptor) {
                switch ($CFDIReceptor["title"]) {
                  case 'Rfc del receptor':
                    $cfdi_receptor_rfc = $CFDIReceptor["content"];
                    break;
                  case 'Uso del CFDI':
                    $cfdi_receptor_uso_del_cfdi = $CFDIReceptor["content"];
                    break;
                  default:
                    //--
                    break;
                }
              }

              //foreach ($cfdi_impuestos_retenidos as $vComp) {}
              //foreach ($cfdi_impuestos_trasladados as $vComp) {}

              $cfdi_complementoUUID = '';
              $cfdi_complementoFechaTimbrado = '';
              $cfdi_complementoRfcProvCertif = '';
              $cfdi_complementoNoCertificadoSAT = '';
              $cfdi_complementoSelloCFD = '';
              $cfdi_complementoSelloSAT = '';
              foreach ($cfdi_complemento as $vComplemento) {
                switch ($vComplemento["title"]) {
                  case 'UUID':
                    $cfdi_complementoUUID = $vComplemento["content"];
                    break;
                  case 'FechaTimbrado':
                    $cfdi_complementoFechaTimbrado = $vComplemento["content"];
                    break;
                  case 'RfcProvCertif':
                    $cfdi_complementoRfcProvCertif = $vComplemento["content"];
                    break;
                  case 'NoCertificadoSAT':
                    $cfdi_complementoNoCertificadoSAT = $vComplemento["content"];
                    break;
                  case 'SelloCFD':
                    $cfdi_complementoSelloCFD = $vComplemento["content"];
                    break;
                  case 'SelloSAT':
                    $cfdi_complementoSelloSAT = $vComplemento["content"];
                    break;
                  default:
                    //--
                    break;
                }
              }

              //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$cfdi_comprobante_version]);
              if ($cfdi_comprobante_version != '') {
                $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$reembolso_main_id.$reembolso_reem_soli.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
                $insertCFDIEstructura = DB::table('cfdi_comprobantes_fiscales')
                ->insert(array(
                  "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
                  "origen_proceso" => "reembolso",
                  //"reembolso_vinculado_main" => $reembolso_main_id,
                  //"reembolso_vinculado_soli" => $reembolso_reem_soli,
                  "cfdi_comprobante_version" => $cfdi_comprobante_version,
                  "cfdi_comprobante_serie" => $cfdi_comprobante_serie,
                  "cfdi_comprobante_folio" => $cfdi_comprobante_folio,
                  "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
                  "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
                  "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,
                  "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,
                  "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,
                  "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,
                  "cfdi_comprobante_total" => $cfdi_comprobante_total,
                  "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
                  "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
                  "cfdi_comprobante_lugar_de_expedicion" => $cfdi_comprobante_lugar_de_expedicion,
                  "cfdi_comprobante_no_de_certificado" => $cfdi_comprobante_no_de_certificado,
                  "cfdi_comprobante_sello" => $cfdi_comprobante_sello,
                  "cfdi_comprobante_certificado" => $cfdi_comprobante_certificado,
                  "cfdi_relacionados_tipo_de_relacion" => $cfdi_relacionados_tipo_de_relacion,
                  "cfdi_relacionados_uuid" => $cfdi_relacionados_uuid,
                  "cfdi_emisor_rfc" => $cfdi_emisor_rfc,
                  "cfdi_emisor_nombre" => $cfdi_emisor_nombre,
                  "cfdi_emisor_regimen_fiscal" => $cfdi_emisor_regimen_fiscal,
                  "cfdi_receptor_rfc" => $cfdi_receptor_rfc,
                  "cfdi_receptor_uso_del_cfdi" => $cfdi_receptor_uso_del_cfdi,
                  "cfdi_complementoUUID" => $cfdi_complementoUUID,
                  "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,
                  "cfdi_complementoRfcProvCertif" => $cfdi_complementoRfcProvCertif,
                  "cfdi_complementoNoCertificadoSAT" => $cfdi_complementoNoCertificadoSAT,
                  "cfdi_complementoSelloCFD" => $cfdi_complementoSelloCFD,
                  "cfdi_complementoSelloSAT" => $cfdi_complementoSelloSAT,
                ));

                $comprobante_fiscal_reem = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");
                $insertCFDIVincReem = DB::table('cfdi_vinculacion_reembolsos')
                ->insert(array(
                  "comprobante_fiscal" => $comprobante_fiscal_reem,
                  "reembolso_vinculado_main" => $reembolso_main_id,
                  "reembolso_vinculado_soli" => $reembolso_reem_soli,
                ));

                for ($lrdc = 0; $lrdc < count($cfdi_conceptos); $lrdc++) {
                  $NoIdentificacion = $cfdi_conceptos[$lrdc]['NoIdentificacion'];
                  $ObjetoImp = $cfdi_conceptos[$lrdc]['ObjetoImp'];
                  $ClaveProdServ = $cfdi_conceptos[$lrdc]['ClaveProdServ'];
                  $cantidad = $cfdi_conceptos[$lrdc]['Cantidad'];
                  $ClaveUnidad = $cfdi_conceptos[$lrdc]['ClaveUnidad'];
                  $Unidad = $cfdi_conceptos[$lrdc]['Unidad'];
                  $concepto = $cfdi_conceptos[$lrdc]['Descripcion'];
                  //return response()->json(['status' => 'error','code' => 200,'message' => $concepto.' reem true5.3 '.$cfdi_comprobante_version]);
                  $precioUnitario = $cfdi_conceptos[$lrdc]['ValorUnitario'];
                  $descuentoXUni = $cfdi_conceptos[$lrdc]['Descuento'];
                  $importe = $cfdi_conceptos[$lrdc]['Importe'];
                  $retenciones = $cfdi_conceptos[$lrdc]['retenciones'];
                  $TotalRetenciones = $cfdi_conceptos[$lrdc]['TotalRetenciones'];
                  $traslados = $cfdi_conceptos[$lrdc]['traslados'];
                  $TotalTraslados = $cfdi_conceptos[$lrdc]['TotalTraslados'];
                  $Subtotal = $cfdi_conceptos[$lrdc]['Subtotal'];

                  $uuid_cfdi_detalle = Str::uuid()->toString();
                  $insertDetCFDICompra = DB::table('cfdi_comprobantes_conceptos')
                    ->insert(array(
                      "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                      "comprobante_fiscal" => $comprobante_fiscal_reem,
                      //"reembolso_vinculado_main" => $reembolso_main_id,
                      //"reembolso_vinculado_soli" => $reembolso_reem_soli,
                      "NoIdentificacion" => $NoIdentificacion,
                      "ObjetoImp" => $ObjetoImp,
                      "ClaveProdServ" => $ClaveProdServ,
                      "Cantidad" => $cantidad,
                      "ClaveUnidad" => $ClaveUnidad,
                      "Unidad" => $Unidad,
                      "Descripcion" => $concepto,
                      "ValorUnitario" => $precioUnitario,
                      "Descuento" => $descuentoXUni,
                      "Importe" => $importe,
                      "TotalRetenciones" => $TotalRetenciones,
                      "TotalTraslados" => $TotalTraslados,
                      "Subtotal" => $Subtotal,
                      "empresa" => $main_empresa_id
                    ));

                  if (count($retenciones) != 0) {
                    for ($rreten = 0; $rreten < count($retenciones); $rreten++) {
                      $retencion_traslado = "rete";
                      $Base = $retenciones[$rreten]["Base"] ? $retenciones[$rreten]["Base"] : 0.00;
                      $Impuesto = $retenciones[$rreten]["Impuesto"] ? $retenciones[$rreten]["Impuesto"] : 000;
                      $TipoFactor = $retenciones[$rreten]["TipoFactor"] ? $retenciones[$rreten]["TipoFactor"] : NULL;
                      $TasaOCuota = $retenciones[$rreten]["TasaOCuota"] ? $retenciones[$rreten]["TasaOCuota"] : NULL;
                      $importe = $retenciones[$rreten]["Importe"] ? $retenciones[$rreten]["Importe"] : 0.00;
                      $impuesto_relacionado = $retenciones[$rreten]["impuesto_relacionado"];
                      $rete_homologada = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $impuesto_relacionado)->value("id") : NULL;

                      $insertDetCFDIImpuestoCompra = DB::table('eegr_compras_cfdi_detalle_impuestos')
                        ->insert(array(
                          "uuid_buydet_impuestos" => Str::uuid()->toString(),
                          "reembolso_vinculado_main" => $reembolso_main_id,
                          "reembolso_vinculado_soli" => $reembolso_reem_soli,
                          "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                          "retencion_traslado" => $retencion_traslado,
                          "base" => $Base,
                          "impuesto" => $Impuesto,
                          "tipoFactor" => $TipoFactor,
                          "tasaOCuota" => $TasaOCuota,
                          "importe" => $importe
                        ));
                    }
                  }

                  if (count($traslados) != 0) {
                    for ($rtras = 0; $rtras < count($traslados); $rtras++) {
                      $retencion_traslado = "tras";
                      $Base = $traslados[$rtras]["Base"] ? $traslados[$rtras]["Base"] : 0.00;
                      $Impuesto = $traslados[$rtras]["Impuesto"] ? $traslados[$rtras]["Impuesto"] : 000;
                      $TipoFactor = $traslados[$rtras]["TipoFactor"] ? $traslados[$rtras]["TipoFactor"] : NULL;
                      $TasaOCuota = $traslados[$rtras]["TasaOCuota"] ? $traslados[$rtras]["TasaOCuota"] : NULL;
                      $importe = $traslados[$rtras]["Importe"] ? $traslados[$rtras]["Importe"] : 0.00;
                      $impuesto_relacionado = $traslados[$rtras]["impuesto_relacionado"];
                      $tras_homologado = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $impuesto_relacionado)->value("id") : NULL;

                      $insertDetCFDIImpuestoCompra = DB::table('eegr_compras_cfdi_detalle_impuestos')
                        ->insert(array(
                          "uuid_buydet_impuestos" => Str::uuid()->toString(),
                          "reembolso_vinculado_main" => $reembolso_main_id,
                          "reembolso_vinculado_soli" => $reembolso_reem_soli,
                          "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                          "retencion_traslado" => $retencion_traslado,
                          "base" => $Base,
                          "impuesto" => $Impuesto,
                          "tipoFactor" => $TipoFactor,
                          "tasaOCuota" => $TasaOCuota,
                          "importe" => $importe
                        ));
                    }
                  }
                }
              }
              //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.3 '.$cfdi_comprobante_version]);
            }
            
            if ($request->file('factura_xml')) {
              $xmlFile = $request->file('factura_xml');
              $xmlName = $xmlFile->getClientOriginalName();
              $xmlExt = $xmlFile->getClientOriginalExtension();
              $xmlTmpPath = $xmlFile->getPathname();
              Storage::putFileAs("/public/root/" . $filepath, $xmlTmpPath, $xmlName);
              $select_folio_xml = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-CFDI-XML%'");
              $token_documento = $JwtAuth->encriptarToken($reembolso_reem_soli, $xmlExt, $xmlName);
              $insertXMLFact = DB::table("sos_documentos")->insert([
                "token_documento" => $token_documento,
                "fecha_carga" => time(),
                "modulo" => "reembolsos",
                "folio_modulo" => "REEM-CFDI-XML".$select_folio_xml[0]->folio,
                "tipo_documento" => "xml",
                "nombre_documento" => $JwtAuth->encriptar($xmlName),
                "extension_documento" => $xmlExt,
                "reembolso_main" => $reembolso_main_id,
                "reembolso_solicitud" => $reembolso_reem_soli,
                "status_documento" => true,
              ]);
            }

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Factura actualizada',
            );

						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_solicitud_18"]);
						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
					}
				} else {
					if (!isset($tokenReembolso) || empty($tokenReembolso)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Folio de reembolso incorrecto'
						);
					}
					if (!isset($tkn_solicitud) || empty($tkn_solicitud)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'La solicitud de reembolso es invalida'
						);
					}
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

	public function reembolso_load_pdf_fact(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayJust = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"tkn_solicitud" => "required|string",
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
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$tkn_solicitud = $parametrosArray["tkn_solicitud"];

        $valida_reem = isset($tokenReembolso) && !empty($tokenReembolso);
        $valida_solicitud = isset($tkn_solicitud) && !empty($tkn_solicitud);

				if ($valida_reem && $valida_solicitud) {

					$list_reem = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
						->where([
						    "reem_soli.status_activacion" => TRUE,
							"reem_main.token_reem" => $tokenReembolso,
							"reem_soli.token_solicitud_reem" => $tkn_solicitud,
							"reem_main.status_reem" => TRUE,
							"emp.empresa_token" => $usuario->empresa_token,
							//"users.usuario_token" => $usuario->user_token
						])->get();

					foreach ($list_reem as $vReem) {
						//da_te_default_timezone_set($vReem->zona_horaria);
            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem).(!is_null($vReem->post_folio_reem) ? '-'.$vReem->post_folio_reem : '');
						$reembolso_main_id = DB::table("terc_reembolso_main")->where("token_reem", $vReem->token_reem)->value("id");
            $reembolso_reem_soli = DB::table("terc_reembolso_solicitud")->where("token_solicitud_reem", $vReem->token_solicitud_reem)->value("id");
            $main_empresa_id = DB::table("main_empresas")->where("empresa_token", $usuario->empresa_token)->value("id");
						$filepath = "$vReem->root_tkn/0010-reem/$folio_reem/".$JwtAuth->generarFolio($vReem->folio_solicitud)."/anexos";
          
						if (!file_exists(storage_path("/root/" . $filepath))) {
							Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
						}
            
            if ($request->file('factura_pdf')) {
              $pdfFile = $request->file('factura_pdf');
              $pdfName = $pdfFile->getClientOriginalName();
              $pdfExt  = $pdfFile->getClientOriginalExtension();
              $pdfTmpPath = $pdfFile->getPathname();
              
              Storage::putFileAs("/public/root/" . $filepath, $pdfTmpPath, $pdfName);
              $select_folio_pdf = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-CFDI-PDF%'");
              $token_documento = $JwtAuth->encriptarToken($reembolso_reem_soli, $pdfExt, $pdfName);
              
              $insertPDFFact = DB::table("sos_documentos")->insert([
                "token_documento" => $token_documento,
                "fecha_carga" => time(),
                "modulo" => "reembolsos",
                "folio_modulo" => "REEM-CFDI-PDF".$select_folio_pdf[0]->folio,
                "tipo_documento" => "pdf",
                "nombre_documento" => $JwtAuth->encriptar($pdfName),
                "extension_documento" => $pdfExt,
                "reembolso_main" => $reembolso_main_id,
                "reembolso_solicitud" => $reembolso_reem_soli,
                "status_documento" => true,
              ]);
              //return response()->json(['status' => 'error','code' => 200,'message' => 'test de PDF2']);
            }

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Factura actualizada',
            );

						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_solicitud_18"]);
						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
					}
				} else {
					if (!isset($tokenReembolso) || empty($tokenReembolso)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Folio de reembolso incorrecto'
						);
					}
					if (!isset($tkn_solicitud) || empty($tkn_solicitud)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'La solicitud de reembolso es invalida'
						);
					}
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

	public function reembolso_load_docs(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('solicitud');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayJust = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"tkn_solicitud" => "required|string",
				"reemAnexosNames" => "required|array"
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
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$tkn_solicitud = $parametrosArray["tkn_solicitud"];
				$anexosNames = $parametrosArray["reemAnexosNames"];

				if (isset($tokenReembolso) && !empty($tokenReembolso) && isset($tkn_solicitud) && !empty($tkn_solicitud)) {
					$list_reem = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
					->where([
					  "reem_soli.status_activacion" => TRUE,
						"reem_main.token_reem" => $tokenReembolso,
						"reem_soli.token_solicitud_reem" => $tkn_solicitud,
						"reem_main.status_reem" => TRUE,
						"emp.empresa_token" => $usuario->empresa_token
					])->get();

					foreach ($list_reem as $vReem) {
						//da_te_default_timezone_set($vReem->zona_horaria);

						$select_reembolso_main = DB::select("SELECT id FROM terc_reembolso_main WHERE token_reem = ?", [$tokenReembolso]);
						$select_reem_soli = DB::select("SELECT id FROM terc_reembolso_solicitud WHERE token_solicitud_reem = ?", [$tkn_solicitud]);

						if ($vReem->post_folio_reem == NULL) {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem);
						} else {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem) . '-' . $vReem->post_folio_reem;
						}

						$filepath = $vReem->root_tkn . "/0010-reem/" . $folio_reem . "/" . $JwtAuth->generarFolio($vReem->folio_solicitud) . "/anexos";

						if (!file_exists(storage_path("/root/" . $filepath))) {
							Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
						}

						$bool_docs_continue = false;
						$count_docs_deleted = 0;
						$list_docs = DB::table("sos_documentos AS docs")
						->join("terc_reembolso_main AS reem_main", "docs.reembolso_main", "=", "reem_main.id")
						->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
						->where(["docs.tipo_documento" => "an", "reem_main.token_reem" => $tokenReembolso, "reem_soli.token_solicitud_reem" => $tkn_solicitud])->get();

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
							$anexos = $_FILES["docsReemAnexos"];
							$docs_nombre = json_decode(json_encode($_FILES["docsReemAnexos"]["name"]));
							for ($i = 0; $i < count($docs_nombre); $i++) {
								$ext_doc = $anexosNames[$i]["typoElement"];
								$documento_crypt = $JwtAuth->encriptar($anexosNames[$i]["nameFile"]);
								$temporal = $anexos["tmp_name"][$i];
								$token_documento = $JwtAuth->encriptarToken($tkn_solicitud, $ext_doc, $documento_crypt);
								//return response()->json(['status' => 'error','code' => 200,'message' => $temporal]);
								$select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-EVID%'");
								$insertDocSoli = DB::table("sos_documentos")->insert(
									array(
										"token_documento" => $token_documento,
										"fecha_carga" => time(),
										"modulo" => "reembolsos",
										"folio_modulo" => "REEM-EVID" . end($select_folio_doc)->folio,
										"tipo_documento" => "an",
										"nombre_documento" => $documento_crypt,
										"extension_documento" => $ext_doc,
										"reembolso_main" => end($select_reembolso_main)->id,
										"reembolso_solicitud" => end($select_reem_soli)->id,
										"status_documento" => TRUE,
									)
								);
								if ($insertDocSoli) {
									++$countdocs_insertados;
									Storage::putFileAs("/public/root/" . $filepath, $temporal, $anexosNames[$i]["nameFile"]);
								}
							}

							if ($countdocs_insertados == count($anexosNames)) {
								$dataMensaje = array(
									"status" => "success",
									'code' => 200,
									"message" => "Reembolso " . $folio_reem . " fue actualizado, se cargaron " . $countdocs_insertados . " nuevos documentos y se eliminaron " . $count_docs_deleted . " documentos no encontrados"
								);
							} else {
								$dataMensaje = array(
									"status" => 'error',
									'code' => 200,
									"message" => 'Error en actualización de solicitud'
								);
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error: no se observó el listado total se archivos vinculados'
							);
						}

						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_solicitud_18"]);
						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
					}
				} else {
					if (!isset($tokenReembolso) || empty($tokenReembolso)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Folio de reembolso incorrecto'
						);
					}
					if (!isset($tkn_solicitud) || empty($tkn_solicitud)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'La solicitud de reembolso es invalida'
						);
					}
					//if(!isset($_FILES["docsReemAnexos"]) || empty($_FILES["docsReemAnexos"])) {
					//    $dataMensaje = array(
					//        'status' => 'error',
					//        'code' => 200,
					//        'message' => 'Error en los archivos que intenta cargar'
					//    );
					//}
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

	public function reembolso_load_docs_(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('solicitud');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayJust = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"tkn_solicitud" => "required|string",
				"reemAnexosNames" => "required|array"
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

				$patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
				$patronFecha = '/^[0-9-]*$/';
				$patronNum = '/^[0-9$,.-]*$/';

				$fecha_sistema = time();
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$tkn_solicitud = $parametrosArray["tkn_solicitud"];

				if (
					isset($tokenReembolso) && !empty($tokenReembolso) &&
					isset($tkn_solicitud) && !empty($tkn_solicitud) &&
					isset($_FILES["docsReemAnexos"]) && !empty($_FILES["docsReemAnexos"])
				) {

					$list_reem = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
						->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
						->where([
							"reem_main.token_reem" => $tokenReembolso,
							"reem_soli.token_solicitud_reem" => $tkn_solicitud,
							"reem_main.status_reem" => TRUE,
							"emp.empresa_token" => $usuario->empresa_token,
							"users.usuario_token" => $usuario->user_token
						])->get();

					foreach ($list_reem as $vReem) {
						//da_te_default_timezone_set($vReem->zona_horaria);
						$countdocs = 0;
						$anexos = $_FILES["docsReemAnexos"];

						$select_reembolso_main = DB::select("SELECT id FROM reembolso_main WHERE token_reem = ?", [$tokenReembolso]);
						$select_reem_soli = DB::select("SELECT id FROM reembolso_solicitud WHERE token_solicitud_reem = ?", [$tkn_solicitud]);

						if ($vReem->post_folio_reem == NULL) {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem);
						} else {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem) . '-' . $vReem->post_folio_reem;
						}

						$filepath = $vReem->root_tkn . "/0010-reem/" . $folio_reem . "/anexos";

						if (!file_exists(storage_path("/root/" . $filepath))) {
							Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
						}
						//return response()->json(['status' => 'error','code' => 200,'message' => $filepath]);

						$docs_nombre = json_decode(json_encode($_FILES["docsReemAnexos"]["name"]));
						for ($i = 0; $i < count($docs_nombre); $i++) {
							$nombre_documento = $docs_nombre[$i];
							//return response()->json(['status' => 'error','code' => 200,'message' => $folio_reem]);
							$type = $anexos["type"][$i];
							if ($type == "application/pdf") {
								$ext_doc = "pdf";
							} else if ($type == "image/jpeg") {
								$ext_doc = "jpg";
							} else if ($type == "image/jpg") {
								$ext_doc = "jpg";
							} else if ($type == "image/png") {
								$ext_doc = "png";
							}

							$temporal = $anexos["tmp_name"][$i];
							$documento_crypt = $JwtAuth->encriptar($nombre_documento);
							$token_documento = $JwtAuth->encriptarToken($tkn_solicitud, $ext_doc, $documento_crypt);
							//return response()->json(['status' => 'error','code' => 200,'message' => $nombre_documento]);

							$rowsDocSoli = DB::select(
								"SELECT id FROM sos_documentos WHERE nombre_documento = ? 
                                AND reembolso_main = ? AND reembolso_solicitud = ?",
								[$documento_crypt, $select_reembolso_main[0]->id, $select_reem_soli[0]->id]
							);

							if (count($rowsDocSoli) == 0) {
								$select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-EVID%'");
								$insertDocSoli = DB::table("sos_documentos")->insert(
									array(
										"token_documento" => $token_documento,
										"fecha_carga" => $fecha_sistema,
										"modulo" => "reembolsos",
										"folio_modulo" => "REEM-EVID" . $select_folio_doc[0]->folio,
										"tipo_documento" => "an",
										"nombre_documento" => $documento_crypt,
										"extension_documento" => $ext_doc,
										"reembolso_main" => $select_reembolso_main[0]->id,
										"reembolso_solicitud" => $select_reem_soli[0]->id,
									)
								);

								if ($insertDocSoli) {
									$countdocs++;
									Storage::putFileAs("/public/root/" . $filepath, $temporal, $nombre_documento);
								}
							} else {
								$countdocs++;
								Storage::putFileAs("/public/root/" . $filepath, $temporal, $nombre_documento);
							}
						}

						if ($countdocs == count($docs_nombre)) {
							$dataMensaje = array(
								'message' => 'Reembolso ' . $folio_reem . ' fue actualizado',
								'code' => 200,
								'status' => 'success'
							);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en actualización de solicitud'
							);
						}
						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_solicitud_18"]);
						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
					}
				} else {
					if (!isset($tokenReembolso) || empty($tokenReembolso)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Folio de reembolso incorrecto'
						);
					}
					if (!isset($tkn_solicitud) || empty($tkn_solicitud)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'La solicitud de reembolso es invalida'
						);
					}
					if (!isset($_FILES["docsReemAnexos"]) || empty($_FILES["docsReemAnexos"])) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en los archivos que intenta cargar'
						);
					}
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

	public function reembolso_delete_docs(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayJust = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"tkn_solicitud" => "required|string",
				"token_documento" => "required|string",
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

				$patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
				$patronFecha = '/^[0-9-]*$/';
				$patronNum = '/^[0-9$,.-]*$/';

				$fecha_sistema = time();
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$tkn_solicitud = $parametrosArray["tkn_solicitud"];
				$token_documento = $parametrosArray["token_documento"];

				if (
					isset($tokenReembolso) && !empty($tokenReembolso) &&
					isset($tkn_solicitud) && !empty($tkn_solicitud) &&
					isset($token_documento) && !empty($token_documento)
				) {

					$list_reem = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
						->join("sos_documentos AS docs", "reem_soli.id", "=", "docs.reembolso_solicitud")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
						->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
						->where([
						    "reem_soli.status_activacion" => TRUE,
							"reem_main.token_reem" => $tokenReembolso,
							"reem_soli.token_solicitud_reem" => $tkn_solicitud,
							"docs.token_documento" => $token_documento,
							"reem_main.status_reem" => TRUE,
							"emp.empresa_token" => $usuario->empresa_token,
							"users.usuario_token" => $usuario->user_token
						])->get();

					foreach ($list_reem as $vReem) {
						//da_te_default_timezone_set($vReem->zona_horaria);

						if ($vReem->post_folio_reem == NULL) {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem);
						} else {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem) . '-' . $vReem->post_folio_reem;
						}

						$deleteDoc = DB::table('sos_documentos')->where(["token_documento" => $vReem->token_documento])->limit(1)->delete();

						if ($deleteDoc) {
							$selectAnexosReem = DB::table("sos_documentos AS docs")
								->join("reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
								->where([
									"docs.tipo_documento" => "an",
									"docs.nombre_documento" => $vReem->nombre_documento,
									"main.token_reem" => $tokenReembolso,
								])->get();

							if (count($selectAnexosReem) == 0) {
								$filepath = $vReem->root_tkn . "/0010-reem/" . $folio_reem . "/anexos/" . $JwtAuth->desencriptar($vReem->nombre_documento);
								Storage::delete("/public/root/" . $filepath);
							}

							$dataMensaje = array(
								'message' => 'Anexo de reembolso ' . $folio_reem . ' fue eliminado',
								'code' => 200,
								'status' => 'success'
							);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en eliminación de anexo'
							);
						}
						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_solicitud_18"]);
						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
					}
				} else {
					if (!isset($tokenReembolso) || empty($tokenReembolso)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Folio de reembolso incorrecto'
						);
					}
					if (!isset($tkn_solicitud) || empty($tkn_solicitud)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'La solicitud de reembolso es invalida'
						);
					}
					if (!isset($token_documento) || empty($token_documento)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en los archivos que intenta eliminar'
						);
					}
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

	public function reembolso_soli_update(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayJust = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"tkn_solicitud" => "required|string",
				"fecha_gasto" => "required|string",
				"ticket_gasto" => "required|string",
				"pagado_a" => "required|string",
				"tkn_proveedor" => "string",
				"forma_pago" => "required|string",
				"importe_requerido" => "required|numeric",
				"reem_moneda_tkn" => "required|string",
				"reem_tipo_cambio_string" => "required|string",
				"motivo_reem" => "required|string"
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

				$patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
				$patronFecha = '/^[0-9-]*$/';
				$patronNum = '/^[0-9$,.-]*$/';

				$fecha_sistema = time();
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$tkn_solicitud = $parametrosArray["tkn_solicitud"];
				$fecha_gasto = $parametrosArray["fecha_gasto"];
				$ticket_gasto = $parametrosArray["ticket_gasto"];
				$pagado_a = $parametrosArray["pagado_a"];
				$tkn_proveedor = $parametrosArray["tkn_proveedor"];
				$forma_pago = $parametrosArray["forma_pago"];
				$importe_requerido = $parametrosArray["importe_requerido"];
				$reem_moneda_tkn = $parametrosArray["reem_moneda_tkn"];
				$reem_tipo_cambio_string = $parametrosArray["reem_tipo_cambio_string"];
				$motivo_reem = $parametrosArray["motivo_reem"];
				//echo $reem_tipo_cambio_string;

				if (
					isset($tokenReembolso) && !empty($tokenReembolso) &&
					isset($tkn_solicitud) && !empty($tkn_solicitud) &&
					isset($fecha_gasto) && !empty($fecha_gasto) && preg_match($patronFecha, $fecha_gasto) &&
					isset($ticket_gasto) && !empty($ticket_gasto) && preg_match($patron, $ticket_gasto) &&
					isset($pagado_a) && !empty($pagado_a) && preg_match($patron, $pagado_a) &&
					isset($importe_requerido) && !empty($importe_requerido) && preg_match($patronNum, $importe_requerido) &&
					isset($forma_pago) && !empty($forma_pago) &&
					isset($reem_moneda_tkn) && !empty($reem_moneda_tkn) &&
					isset($reem_tipo_cambio_string) && !empty($reem_tipo_cambio_string) && preg_match($patron, $reem_tipo_cambio_string) &&
					isset($motivo_reem) && !empty($motivo_reem) && preg_match($patron, $motivo_reem)
				) {

					if ($reem_tipo_cambio_string == "1") {
						$tipo_cambio_sql = "1.00";
					} else {
						$tipo_cambio_sql = $reem_tipo_cambio_string;
					}

					if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
						$list_reem = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
							->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
							->where([
							    "reem_soli.status_activacion" => TRUE,
								"reem_main.token_reem" => $tokenReembolso,
								"reem_soli.token_solicitud_reem" => $tkn_solicitud,
								"reem_main.status_reem" => TRUE,
								"emp.empresa_token" => $usuario->empresa_token
							])->get();
					} else { //proy.status = TRUE AND 
						$list_reem = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
							->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
							->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
							->where([
							    "reem_soli.status_activacion" => TRUE,
								"reem_main.token_reem" => $tokenReembolso,
								"reem_soli.token_solicitud_reem" => $tkn_solicitud,
								"reem_main.status_reem" => TRUE,
								"emp.empresa_token" => $usuario->empresa_token,
								"users.usuario_token" => $usuario->user_token
							])->get();
					}

					foreach ($list_reem as $vReem) {
						//da_te_default_timezone_set($vReem->zona_horaria);
						$select_reembolso_main = DB::select("SELECT id FROM terc_reembolso_main WHERE token_reem = ?", [$tokenReembolso]);
						if ($vReem->post_folio_reem == NULL) {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem);
						} else {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem) . '-' . $vReem->post_folio_reem;
						}

						$prov_sql = NULL;
						if ($pagado_a == "prov") {
							if (isset($tkn_proveedor) && !empty($tkn_proveedor)) {
								$selectProv = DB::select("SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?", [$tkn_proveedor]);
								foreach ($selectProv as $vProv) {
									$prov_sql = $vProv->id;
								}
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'Error en proveedor seleccionado'
								);
							}
						}

						$selectFPago = DB::select("SELECT id FROM teci_forma_pago WHERE token_formapago = ?", [$forma_pago]);
						foreach ($selectFPago as $vfpag) {
							$fpag_sql = $vfpag->id;
						}

						$regUpdate = DB::table('terc_reembolso_solicitud AS reem_soli')
							->join("terc_reembolso_main AS rmain", "reem_soli.reembolso_main", "=", "rmain.id")
							->where([
								"reem_soli.token_solicitud_reem" => $vReem->token_solicitud_reem,
								"rmain.token_reem" => $vReem->token_reem,
							])
							->limit(1)->update(
								array(
									"reem_soli.fecha_gasto" => $JwtAuth->convierteFechaEpoc($fecha_gasto),
									"reem_soli.ticket_gasto" => $JwtAuth->encriptar($ticket_gasto),
									"reem_soli.pagado_a" => $pagado_a,
									"reem_soli.proveedor" => $prov_sql,
									"reem_soli.forma_pago" => $fpag_sql,
									"reem_soli.moneda_entrante" => $reem_moneda_tkn,
									"reem_soli.tipo_cambio" => $tipo_cambio_sql,
									"reem_soli.importe_entrante" => $importe_entrante,
									"reem_soli.motivo_reem" => $JwtAuth->encriptar($motivo_reem),
								)
							);

						if ($regUpdate) {
							$dataMensaje = array(
								'message' => 'Reembolso ' . $folio_reem . ' fue actualizado',
								'code' => 200,
								'status' => 'success'
							);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en actualización de solicitud'
							);
						}
						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_solicitud_18"]);
						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
					}
				} else {
					if (!isset($tokenReembolso) || empty($tokenReembolso)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Folio de reembolso incorrecto'
						);
					}
					if (!isset($tkn_solicitud) || empty($tkn_solicitud)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'La solicitud de reembolso es invalida'
						);
					}
					if (!isset($fecha_gasto) || empty($fecha_gasto) || !preg_match($patronFecha, $fecha_gasto)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en fecha de gasto'
						);
					}
					if (!isset($ticket_gasto) || empty($ticket_gasto) || !preg_match($patron, $ticket_gasto)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en ticket de comprobación de gasto'
						);
					}
					if (!isset($pagado_a) || empty($pagado_a) || !preg_match($patron, $pagado_a)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en campo "pagado a:"'
						);
					}
					if (!isset($importe_requerido) || empty($importe_requerido) || !preg_match($patronNum, $importe_requerido)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en reembolso total'
						);
					}
					if (!isset($forma_pago) || empty($forma_pago)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en forma de pago'
						);
					}
					if (!isset($reem_moneda_tkn) || empty($reem_moneda_tkn)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en forma de moneda'
						);
					}
					if (!isset($reem_tipo_cambio_string) || empty($reem_tipo_cambio_string) || !preg_match($patronNum, $reem_tipo_cambio_string)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en tipo de cambio'
						);
					}
					if (!isset($motivo_reem) || empty($motivo_reem) || !preg_match($patron, $motivo_reem)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en motivos de reembolso / observaciones'
						);
					}
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

	public function reembolso_agregar(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('solicitud');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayJust = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"fecha_gasto" => "required|string",
				"ticket_gasto" => "required|string",
				"pagado_a" => "required|string",
				"tkn_proveedor" => "required|string",
				"forma_pago" => "required|string",
				"importe_requerido" => "required|numeric",
				"motivo_reem" => "required|string"
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

				$patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
				$patronFecha = '/^[0-9-]*$/';
				$patronNum = '/^[0-9$,.-]*$/';

				$fecha_sistema = time();
				$tiempo_respuesta = $fecha_sistema + (86400 * 5);
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$fecha_gasto = $parametrosArray["fecha_gasto"];
				$ticket_gasto = $parametrosArray["ticket_gasto"];
				$pagado_a = $parametrosArray["pagado_a"];
				$tkn_proveedor = $parametrosArray["tkn_proveedor"];
				$forma_pago = $parametrosArray["forma_pago"];
				$importe_requerido = $parametrosArray["importe_requerido"];
				$motivo_reem = $parametrosArray["motivo_reem"];

				//if(!empty($_FILES["docsReemAnexos"])){
				//    return response()->json(['status' => 'error','code' => 200,'message' => "hay_documento"]);
				//}

				if (
					isset($tokenReembolso) && !empty($tokenReembolso) &&
					isset($fecha_gasto) && !empty($fecha_gasto) && preg_match($patronFecha, $fecha_gasto) &&
					isset($ticket_gasto) && !empty($ticket_gasto) && preg_match($patron, $ticket_gasto) &&
					isset($pagado_a) && !empty($pagado_a) && preg_match($patron, $pagado_a) &&
					isset($forma_pago) && !empty($forma_pago) &&
					isset($importe_requerido) && !empty($importe_requerido) && preg_match($patronNum, $importe_requerido) &&
					isset($motivo_reem) && !empty($motivo_reem) && preg_match($patron, $motivo_reem)
				) {

					$list_reem = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
						->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
						->where([
							"reem_main.token_reem" => $tokenReembolso,
							"reem_main.status_reem" => TRUE,
							"emp.empresa_token" => $usuario->empresa_token,
							"users.usuario_token" => $usuario->user_token
						])->get();

					foreach ($list_reem as $vReem) {
						//da_te_default_timezone_set($vReem->zona_horaria);

						if ($vReem->post_folio_reem == NULL) {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem);
						} else {
							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem) . '-' . $vReem->post_folio_reem;
						}

						$select_r_main = DB::select("SELECT id FROM reembolso_main WHERE token_reem = ?", [$vReem->token_reem]);

						$query_fol_max = DB::select(
							"SELECT MAX(rSoli.folio_solicitud)+1 AS jFolio FROM reembolso_solicitud AS rSoli 
                            JOIN reembolso_main AS rMain WHERE rSoli.reembolso_main = rMain.id AND rMain.token_reem = ?",
							[$tokenReembolso]
						);

						$new_folio_solicitud = $query_fol_max[0]->jFolio;
						$new_folio_soli_all = $JwtAuth->generarFolio($query_fol_max[0]->jFolio);

						$token_reem_soli = $JwtAuth->encriptarToken($tokenReembolso . $fecha_gasto . $ticket_gasto . $motivo_reem .
							$tkn_proveedor . $pagado_a . $importe_requerido . $forma_pago . $motivo_reem);

						$prov_sql = NULL;
						if ($pagado_a == "prov") {
							if (isset($tkn_proveedor) && !empty($tkn_proveedor)) {
								$selectProv = DB::select("SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?", [$tkn_proveedor]);
								foreach ($selectProv as $vProv) {
									$prov_sql = $vProv->id;
								}
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'Error en proveedor seleccionado'
								);
							}
						}

						$selectFPago = DB::select("SELECT id FROM teci_forma_pago WHERE token_formapago = ?", [$forma_pago]);
						foreach ($selectFPago as $vfpag) {
							$fpag_sql = $vfpag->id;
						}

						$insert_reem_soli = DB::table('reembolso_solicitud')->insert(
							array(
								"token_solicitud_reem" => $token_reem_soli,
								"folio_solicitud" => $new_folio_solicitud,
								"fecha_solicitud" => $fecha_sistema,
								"reembolso_main" => $select_r_main[0]->id,
								"fecha_gasto" => $JwtAuth->convierteFechaEpoc($fecha_gasto),
								"ticket_gasto" => $JwtAuth->encriptar($ticket_gasto),
								"pagado_a" => $pagado_a,
								"proveedor" => $prov_sql,
								"forma_pago" => $fpag_sql,
								"importe_entrante" => $importe_entrante,
								"motivo_reem" => $JwtAuth->encriptar($motivo_reem),
								"tiempo_respuesta" => $tiempo_respuesta,
								"version" => TRUE,
								"fecha_delete" => NULL,
							)
						);
						if ($insert_reem_soli) {
							if (!empty($_FILES["docsReemAnexos"])) {
								$select_reem_soli = DB::select("SELECT id FROM reembolso_solicitud WHERE token_solicitud_reem = ?", [$token_reem_soli]);
								//return response()->json(['status' => 'error','code' => 200,'message' => "name_documento1"]);
								$anexos = $_FILES["docsReemAnexos"];
								//return response()->json(['status' => 'error','code' => 200,'message' => "name_documento2"]);
								$filepath = $vReem->root_tkn . "/0010-reem/" . $folio_reem . "/anexos";
								//return response()->json(['status' => 'error','code' => 200,'message' => "name_documento3"]);
								if (!file_exists(storage_path("/root/" . $filepath))) {
									Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
									//return response()->json(['status' => 'error','code' => 200,'message' => "name_documento4"]);
								}
								//return response()->json(['status' => 'error','code' => 200,'message' => "name_documento5"]);
								$docs_nombre = json_decode(json_encode($_FILES["docsReemAnexos"]["name"]));
								//return response()->json(['status' => 'error','code' => 200,'message' => "name_documento6"]);
								for ($i = 0; $i < count($docs_nombre); $i++) {
									$nombre_documento = $docs_nombre[$i];

									$type = $anexos["type"][$i];
									if ($type == "application/pdf") {
										$ext_doc = "pdf";
									} else if ($type == "image/jpeg") {
										$ext_doc = "jpg";
									} else if ($type == "image/jpg") {
										$ext_doc = "jpg";
									} else if ($type == "image/png") {
										$ext_doc = "png";
									}

									$temporal = $anexos["tmp_name"][$i];
									$token_documento = $JwtAuth->encriptarToken($token_reem_soli, $ext_doc, $nombre_documento);
									$select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-EVID%'");
									//return response()->json(['status' => 'error','code' => 200,'message' => $nombre_documento]);
									$insertDocSoli = DB::table("sos_documentos")->insert(
										array(
											"token_documento" => $token_documento,
											"fecha_carga" => $fecha_sistema,
											"modulo" => "reembolsos",
											"folio_modulo" => "REEM-EVID" . $select_folio_doc[0]->folio,
											"tipo_documento" => "an",
											"nombre_documento" => $JwtAuth->encriptar($nombre_documento),
											"extension_documento" => $ext_doc,
											"reembolso_main" => $select_r_main[0]->id,
											"reembolso_solicitud" => $select_reem_soli[0]->id,
										)
									);
									//return response()->json(['status' => 'error','code' => 200,'message' => $nombre_documento]);
									if ($insertDocSoli) {
										Storage::putFileAs("/public/root/" . $filepath, $temporal, $nombre_documento);
									}
								}
							}

							$dataMensaje = array(
								'message' => 'Solicitud añadida con el folio ' . $new_folio_soli_all,
								'code' => 200,
								'status' => 'success'
							);
						}
						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_solicitud_18"]);
						//return response()->json(['status' => 'error','code' => 200,'message' => "cfdi_detalle_solicitud ".$sql_m_pago]); 
					}
				} else {
					if (!isset($tokenReembolso) || empty($tokenReembolso)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Folio de reembolso incorrecto'
						);
					}
					if (!isset($fecha_gasto) || empty($fecha_gasto) || !preg_match($patronFecha, $fecha_gasto)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en fecha de gasto'
						);
					}
					if (!isset($ticket_gasto) || empty($ticket_gasto) || !preg_match($patron, $ticket_gasto)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en ticket de comprobación de gasto'
						);
					}
					if (!isset($pagado_a) || empty($pagado_a) || !preg_match($patron, $pagado_a)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en campo "pagado a:"'
						);
					}
					if (!isset($importe_requerido) || empty($importe_requerido) || !preg_match($patronNum, $importe_requerido)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en reembolso total'
						);
					}
					if (!isset($forma_pago) || empty($forma_pago)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en forma de pago'
						);
					}
					if (!isset($motivo_reem) || empty($motivo_reem) || !preg_match($patron, $motivo_reem)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en motivos de reembolso / observaciones'
						);
					}
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

	public function reembolso_registro_fase_uno(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
			  'acreedor' => 'required|string',
			  'comisiones' => 'required|array',
			  'tiempo_respuesta_reem_comi' => 'required|numeric',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
			  $acreedor = $parametrosArray['acreedor'];
			  $comisiones = $parametrosArray['comisiones'];
			  $tiempo_respuesta_reem_comi = $parametrosArray['tiempo_respuesta_reem_comi'];

			  $valida_comi = isset($comisiones) && !empty($comisiones);
			  $valida_acreedor = isset($acreedor) && !empty($acreedor);
        //echo $tiempo_respuesta_reem_comi;
			  $valida_tiempo_respuesta = isset($tiempo_respuesta_reem_comi) && is_numeric($tiempo_respuesta_reem_comi) && preg_match($JwtAuth->filtroNumerico(), $tiempo_respuesta_reem_comi);
        
        if ($valida_comi && $valida_acreedor) {
          $queryHabReem = DB::table("fnzs_catalogo_acreedores")->where("token_cat_acreedores",$acreedor)->value("acr_habilita_reembolsos");

          if ($queryHabReem) {
            $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
            AND emp.id = empuser.empresa AND empuser.empleado = pers.id 
            AND pers.id = users.empleado AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

            foreach ($queryEmp as $vEmp) {
              $acreedor_id = DB::table("fnzs_catalogo_acreedores")->where("token_cat_acreedores",$acreedor)->value("id");
              //da_te_default_timezone_set($vEmp->zona_horaria);
              $tiempo_respuesta = time() + (86400 * ($tiempo_respuesta_reem_comi / 24));
              
              $folioSistema = DB::select("SELECT fold.folder+1 AS folio,fold.post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE fold.reembolsos = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.empleado = pers.id AND pers.id = users.empleado AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

              if (count($folioSistema) == 1) {
                if ($folioSistema[0]->folio == 1000000000) {
                  $post_folio_db = DB::select("SELECT post_folio_reem FROM reembolso_main WHERE id = (SELECT Max(reem.id) FROM reembolso_main AS reem JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE reem.emisor = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                    AND empuser.usuario = users.id AND users.usuario_token = ?)", [$usuario->empresa_token, $usuario->user_token]);
                  $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio_reem);
                  $folio_nuevo = 1;
                } else {
                  $post_folio = NULL;
                  $folio_nuevo = $folioSistema[0]->folio;
                }
              } else {
                $post_folio = NULL;
                $folio_nuevo = 1;
              }
            
              $folio_reem = "REEM-".$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');
              $selectEmisorPers = DB::table("vhum_empleados_catalogo AS pers")
                ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
                ->where("users.usuario_token", $usuario->user_token)
                ->select('pers.id', 'users.usuario_token')->first();
              $emisor_pers = $selectEmisorPers ? $selectEmisorPers->id : '';
              //$emisor_tkn_user = $selectEmisorPers ? $selectEmisorPers->usuario_token : '';
            
              $emisor_emp = DB::table("main_empresas")->where("empresa_token", $usuario->empresa_token)->value("id");
            
              $selectPowerVhum = DB::table("configuracion_systema_vhum AS conf_vhum")
              ->join("main_empresas AS emp", "conf_vhum.empresa", "=", "emp.id")
              ->where("conf_vhum.jerarquia", "P")
              ->where("conf_vhum.reembolsos", TRUE)
              ->where("emp.empresa_token", $usuario->empresa_token)
              ->select('conf_vhum.usuario')->first();
              $receptor_pers = $selectPowerVhum ? $selectPowerVhum->usuario : '';
            
              $token_reembolso_main = $JwtAuth->encriptarToken(rand(5, 15) . $folio_reem . time() . $emisor_emp . $emisor_pers . $usuario->empresa_token, $usuario->user_token);
            
              $comision_identificador = NULL;
              $egresos_valua = FALSE;
              $egresos_aplica = NULL;
              $egresos_user = NULL;
              $egresos_tiempo_respuesta = null;
            
              $valor_humano_valua = FALSE;
              $valor_humano_aplica = NULL;
              $valor_humano_user = NULL;
              $valor_humano_tiempo_respuesta = null;
            
              foreach ($comisiones as $r_comi => $rComi) {
                $token_comision_main = $rComi["token_comision_main"];
                $comisionData = DB::table("terc_comisiones_main")->where(["token_comision_main" => $token_comision_main])->get();
                foreach ($comisionData as $forC) {
                  if ($forC->valor_humano == TRUE) {
                    $valor_humano_valua = TRUE;
                    $valor_humano_user = 3;
                    $valor_humano_tiempo_respuesta = $tiempo_respuesta;
                  } else {
                    $valor_humano_aplica = "N";
                  }
                  if ($forC->egresos == TRUE) {
                    $egresos_valua = TRUE;
                    $egresos_user = 3;
                    $egresos_tiempo_respuesta = $tiempo_respuesta;
                  } else {
                    $egresos_aplica = "N";
                  }
                }
              }

              $newReem = new ReembolsoModelo();
              $newReem->token_reem = $token_reembolso_main;
              $newReem->folio_reem = $folio_nuevo;
              $newReem->post_folio_reem = $post_folio;
              $newReem->fecha_sistema = time();
              $newReem->emisor = $emisor_emp;
              $newReem->receptor = $emisor_emp;
              $newReem->status_reem = TRUE;
              $newReem->fecha_delete = NULL;
              $newReem->user_emisor = $emisor_pers;
              $newReem->user_acreedor = $acreedor_id;
              $newReem->user_receptor_vh = $valor_humano_user;
              $newReem->tiempo_respuesta_auth_vh = $valor_humano_tiempo_respuesta;
              $newReem->user_receptor_egr = $egresos_user;
              $newReem->tiempo_respuesta_auth_egr = $egresos_tiempo_respuesta;
              $insertReem = $newReem->save();

              if ($insertReem) {
                $select_reembolso_main = $newReem->id;
                foreach ($comisiones as $l_comi => $com_i) {
                  $token_comision_main = $com_i["token_comision_main"];
                  $comisionID = DB::table("terc_comisiones_main")->where("token_comision_main", $token_comision_main)->value("id");
                
                  if ($comisionID) {
                    DB::table('sos_reembolsos_comisiones_rel')->insert([
                      "token_rel_comi_reem" => $JwtAuth->encriptarToken($token_reembolso_main.$token_comision_main.$comisionID.$select_reembolso_main), 
                      "comision" => $comisionID, 
                      "reembolso_main" => $select_reembolso_main
                    ]);
                  }
                }

                if (count($folioSistema) == 0) {
                  $insertSistema = DB::table('sos_last_folders')
                  ->insert(
                    array(
                      "reembolsos" => TRUE,
                      "folder" => 1,
                      "post_folder" => $post_folio,
                      "empresa" => $emisor_emp,
                    )
                  );
                } else {
                  $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                  ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                  ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                  ->where([
                    'sos_last_folders.reembolsos' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                  ])
                  ->limit(1)->update(
                    array(
                      'sos_last_folders.folder' => $folio_nuevo,
                      'sos_last_folders.post_folder' => $post_folio,
                    )
                  );
                }

                $dataMensaje = array(
                  'message' => 'reem_saved',
                  'folio_reem' => $folio_reem,
                  'token_reembolso_main' => $token_reembolso_main,
                  'valor_humano_valua' => $valor_humano_valua,
                  'valor_humano_aplica' => $valor_humano_aplica,
                  'egresos_valua' => $egresos_valua,
                  'egresos_aplica' => $egresos_aplica,
                  'code' => 200,
                  'status' => 'success'
                );
              } else {
                $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'reem_fail_inside');
              }
            }
          } else {
            $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Usuario sin acceso a registro de reembolsos');
          }
        } else {
				  $mensaje_error = '';
				  if (!$valida_comi || !$valida_acreedor) {$mensaje_error = 'reem_list_fail';}
				  if (!$valida_acreedor) {$mensaje_error = 'Usuario no registrado como acreedor';}
				  //if (!$valida_tiempo_respuesta) {$mensaje_error = 'El tiempo de respuesta es invalido';}
				  $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function reembolso_registro_fase_dos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_reembolso_main' => 'required|string',
        'autorizacion_vh' => 'string|nullable',
        'autorizacion_egr' => 'string|nullable',
        'tiempo_respuesta_autorizacion' => 'required|numeric',
			  'reem_fecha' => 'required|string',
			  'reem_folio_ticket' => 'required|string',
			  'reem_pagado_a' => 'required|string',
			  'proveedor_tkn' => 'required|string',
			  'tkn_forma_pago' => 'required|string',
			  'reem_importe_total' => 'required|string',
			  'reem_tipo_cambio' => 'required|string',
			  'reem_moneda_nombre' => 'required|string',
			  'dataCFDI_comprobante' => 'array',
			  'dataCFDIRelacionados' => 'array',
			  'dataCFDIEmisor' => 'array',
			  'dataCFDIReceptor' => 'array',
			  'dataCFDI_conceptos' => 'array',
			  'dataCFDI_impuestos_retenidos_lista' => 'array',
			  'dataCFDI_impuestos_trasladados_lista' => 'array',
			  'dataCFDIComplemento' => 'array',
			  'reem_observacion' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
			  $token_reembolso_main = $parametrosArray['token_reembolso_main'];
			  $autorizacion_vh = $parametrosArray['autorizacion_vh'];
			  $autorizacion_egr = $parametrosArray['autorizacion_egr'];
			  $tiempo_respuesta_autorizacion = $parametrosArray['tiempo_respuesta_autorizacion'];
		    $reem_fecha = $parametrosArray['reem_fecha'];
		    $reem_folio_ticket = $parametrosArray['reem_folio_ticket'];
		    $reem_pagado_a = $parametrosArray['reem_pagado_a'];
		    $proveedor_tkn = $parametrosArray['proveedor_tkn'];
		    $tkn_forma_pago = $parametrosArray['tkn_forma_pago'];
		    $reem_importe_total = $parametrosArray['reem_importe_total'];
		    $reem_tipo_cambio = $parametrosArray['reem_tipo_cambio'];
		    $reem_moneda_nombre = $parametrosArray['reem_moneda_nombre'];
		    $cfdi_comprobante = $parametrosArray['dataCFDI_comprobante'];
		    $cfdi_relacionados = $parametrosArray['dataCFDIRelacionados'];
		    $cfdi_emisor = $parametrosArray['dataCFDIEmisor'];
		    $cfdi_receptor = $parametrosArray['dataCFDIReceptor'];
		    $cfdi_conceptos = $parametrosArray['dataCFDI_conceptos'];
		    $dataCFDI_impuestos_retenidos_lista = $parametrosArray['dataCFDI_impuestos_retenidos_lista'];
		    $dataCFDI_impuestos_trasladados_lista = $parametrosArray['dataCFDI_impuestos_trasladados_lista'];
		    $cfdi_complemento = $parametrosArray['dataCFDIComplemento'];
		    $reem_observacion = $parametrosArray['reem_observacion'];
        
			  $valida_reem_fecha = isset($reem_fecha) && !empty($reem_fecha) && preg_match($JwtAuth->filtroFecha(),$reem_fecha);
			  $valida_reem_folio_ticket = isset($reem_folio_ticket) && !empty($reem_folio_ticket) && preg_match($JwtAuth->filtroAlfaNumerico(),$reem_folio_ticket); 
			  $valida_reem_pagado_a = isset($reem_pagado_a) && !empty($reem_pagado_a) && preg_match($JwtAuth->filtroAlfaNumerico(),$reem_pagado_a) && ($reem_pagado_a != "prov" || ($reem_pagado_a == "prov" && $proveedor_tkn != ""));
			  $valida_reem_forma_pago = isset($tkn_forma_pago) && !empty($tkn_forma_pago);
			  $valida_reem_importe_total = isset($reem_importe_total) && !empty($reem_importe_total) && preg_match($JwtAuth->filtroCostoPrecio(),$reem_importe_total);
			  $valida_reem_tipo_cambio = isset($reem_tipo_cambio) && !empty($reem_tipo_cambio) && preg_match($JwtAuth->filtroCostoPrecio(),$reem_tipo_cambio);
			  $valida_reem_moneda_entrante = isset($reem_moneda_nombre) && !empty($reem_moneda_nombre) && preg_match($JwtAuth->filtroAlfaNumerico(),$reem_moneda_nombre);
			  $valida_reem_observacion = isset($reem_observacion) && !empty($reem_observacion) && preg_match($JwtAuth->filtroAlfaNumerico(),$reem_observacion);

        if ($valida_reem_fecha && $valida_reem_folio_ticket && $valida_reem_pagado_a && $valida_reem_forma_pago && $valida_reem_importe_total && $valida_reem_tipo_cambio && $valida_reem_moneda_entrante && $valida_reem_observacion) {
					$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->where([
            "reem_main.token_reem" => $token_reembolso_main, 
            "emp.empresa_token" => $usuario->empresa_token
          ])->get();

          foreach ($reembolso_main_selected as $vremb) {
            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).(!is_null($vremb->post_folio_reem) ? '-'.$vremb->post_folio_reem : '');
            $tiempo_respuesta = time() + (86400 * ($tiempo_respuesta_autorizacion / 24));
            $reembolso_main_id = DB::table("terc_reembolso_main")->where("token_reem", $vremb->token_reem)->value("id");
            $main_empresa_id = DB::table("main_empresas")->where("empresa_token", $usuario->empresa_token)->value("id");

					  $soli_registradas = DB::table("terc_reembolso_solicitud AS reem_soli")
					  ->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
					  ->where("reem_main.token_reem",$vremb->token_reem)
					  ->orderBy('reem_soli.folio_solicitud', 'DESC')->count();
            $new_folio_solicitud = $soli_registradas + 1;
            
            $new_folio_all_solicitud = $JwtAuth->generarFolio($soli_registradas + 1);
            $proveedor_id = $proveedor_tkn != "" ? DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $proveedor_tkn)->value("id") : NULL;
            $token_reem_soli = $JwtAuth->encriptarToken($reembolso_main_id.$new_folio_solicitud.$reem_fecha.$reem_folio_ticket.$reem_pagado_a.$tkn_forma_pago.$reem_importe_total.$reem_observacion);

            $insert_reem_soli = DB::table('terc_reembolso_solicitud')->insert(
              array(
                "token_solicitud_reem" => $token_reem_soli,
                "folio_solicitud" => $new_folio_solicitud,
                "fecha_solicitud" => time(),
                "reembolso_main" => $reembolso_main_id,
                "fecha_gasto" => $JwtAuth->convierteFechaEpoc($reem_fecha),
                "ticket_gasto" => $JwtAuth->encriptar($reem_folio_ticket),
                "pagado_a" => $reem_pagado_a,
                "proveedor" => $proveedor_id,
                "forma_pago" => $tkn_forma_pago,
                "importe_entrante" => $reem_importe_total,
                "moneda_entrante" => $reem_moneda_nombre,
                "tipo_cambio" => $reem_tipo_cambio,
                "motivo_reem" => $JwtAuth->encriptar($reem_observacion),
                "autorizacion_vh" => $autorizacion_vh,
                "autorizacion_egr" => $autorizacion_egr,
                "tiempo_respuesta_autorizacion" => $tiempo_respuesta,
                "version" => TRUE,
              )
            );
            
            if ($insert_reem_soli) {
              $filepath = "$vremb->root_tkn/0010-reem/$folio_reem/".$JwtAuth->generarFolio($new_folio_solicitud)."/anexos";

              if (!file_exists(storage_path("/root/" . $filepath))) {
                Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
              }
        
              $reembolso_soli_list = DB::table('terc_reembolso_solicitud')->where("token_solicitud_reem", $token_reem_soli)->value("id");
              if (is_array($cfdi_comprobante)) {
                $cfdi_comprobante_version = '';
                $cfdi_comprobante_serie = '';
                $cfdi_comprobante_folio = '';
                $cfdi_comprobante_fecha = '';
                $cfdi_comprobante_forma_de_pago = '';
                $cfdi_comprobante_metodo_de_pago = '';
                $cfdi_comprobante_subtotal = '';
                $cfdi_comprobante_moneda = '';
                $cfdi_comprobante_tipo_de_cambio = '';
                $cfdi_comprobante_total = '';
                $cfdi_comprobante_confirmacion = '';
                $cfdi_comprobante_tipo_de_comprobante = '';
                $cfdi_comprobante_lugar_de_expedicion = '';
                $cfdi_comprobante_no_de_certificado = '';
                $cfdi_comprobante_sello = '';
                $cfdi_comprobante_certificado = '';

                foreach ($cfdi_comprobante as $vComp) {
                  switch ($vComp["title"]) {
                    case 'Versión':
                      $cfdi_comprobante_version = $vComp["content"];
                      break;

                    case 'Serie':
                      $cfdi_comprobante_serie = $vComp["content"];
                      break;

                    case 'Folio':
                      $cfdi_comprobante_folio = $vComp["content"];
                      break;

                    case 'Fecha':
                      $cfdi_comprobante_fecha = $vComp["content"];
                      break;

                    case 'Forma de pago':
                      $cfdi_comprobante_forma_de_pago = $vComp["content"];
                      break;

                    case 'Subtotal':
                      $cfdi_comprobante_subtotal = $vComp["content"];
                      break;

                    case 'Moneda':
                      $cfdi_comprobante_moneda = $vComp["content"];
                      break;

                    case 'Tipo de cambio':
                      $cfdi_comprobante_tipo_de_cambio = $vComp["content"];
                      break;

                    case 'Total':
                      $cfdi_comprobante_total = $vComp["content"];
                      break;

                    case 'Confirmación':
                      $cfdi_comprobante_confirmacion = $vComp["content"];
                      break;

                    case 'Tipo de comprobante':
                      $cfdi_comprobante_tipo_de_comprobante = $vComp["content"];
                      break;

                    case 'Método de Pago':
                      $cfdi_comprobante_metodo_de_pago = $vComp["content"];
                      break;

                    case 'Lugar de Expedición':
                      $cfdi_comprobante_lugar_de_expedicion = $vComp["content"];
                      break;

                    case 'No de certificado':
                      $cfdi_comprobante_no_de_certificado = $vComp["content"];
                      break;

                    case 'Sello':
                      $cfdi_comprobante_sello = $vComp["content"];
                      break;

                    case 'Certificado':
                      $cfdi_comprobante_certificado = $vComp["content"];
                      break;

                    default:
                      # code...
                      break;
                  }
                }

                $cfdi_relacionados_tipo_de_relacion = '';
                $cfdi_relacionados_uuid = '';
                foreach ($cfdi_relacionados as $CFDIr) {
                  switch ($CFDIr["title"]) {
                    case 'Tipo de relación':
                      $cfdi_relacionados_tipo_de_relacion = $CFDIr["content"];
                      break;
                    case 'UUID':
                      $cfdi_relacionados_uuid = $CFDIr["content"];
                      break;

                    default:
                      //--
                      break;
                  }
                }
                //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.CFDI2']);
                $cfdi_emisor_rfc = '';
                $cfdi_emisor_nombre = '';
                $cfdi_emisor_regimen_fiscal = '';
                foreach ($cfdi_emisor as $CFDIe) {
                  switch ($CFDIe["title"]) {
                    case 'Rfc del emisor':
                      $cfdi_emisor_rfc = $CFDIe["content"];
                      break;
                    case 'Nombre del emisor':
                      $cfdi_emisor_nombre = $CFDIe["content"];
                      break;
                    case 'Regimen fiscal del emisor':
                      $cfdi_emisor_regimen_fiscal = $CFDIe["content"];
                      break;
                    default:
                      //--
                      break;
                  }
                }

                $cfdi_receptor_rfc = '';
                $cfdi_receptor_uso_del_cfdi = '';
                foreach ($cfdi_receptor as $CFDIReceptor) {
                  switch ($CFDIReceptor["title"]) {
                    case 'Rfc del receptor':
                      $cfdi_receptor_rfc = $CFDIReceptor["content"];
                      break;
                    case 'Uso del CFDI':
                      $cfdi_receptor_uso_del_cfdi = $CFDIReceptor["content"];
                      break;
                    default:
                      //--
                      break;
                  }
                }

                //foreach ($cfdi_impuestos_retenidos as $vComp) {}
                //foreach ($cfdi_impuestos_trasladados as $vComp) {}

                $cfdi_complementoUUID = '';
                $cfdi_complementoFechaTimbrado = '';
                $cfdi_complementoRfcProvCertif = '';
                $cfdi_complementoNoCertificadoSAT = '';
                $cfdi_complementoSelloCFD = '';
                $cfdi_complementoSelloSAT = '';
                foreach ($cfdi_complemento as $vComplemento) {
                  switch ($vComplemento["title"]) {
                    case 'UUID':
                      $cfdi_complementoUUID = $vComplemento["content"];
                      break;
                    case 'FechaTimbrado':
                      $cfdi_complementoFechaTimbrado = $vComplemento["content"];
                      break;
                    case 'RfcProvCertif':
                      $cfdi_complementoRfcProvCertif = $vComplemento["content"];
                      break;
                    case 'NoCertificadoSAT':
                      $cfdi_complementoNoCertificadoSAT = $vComplemento["content"];
                      break;
                    case 'SelloCFD':
                      $cfdi_complementoSelloCFD = $vComplemento["content"];
                      break;
                    case 'SelloSAT':
                      $cfdi_complementoSelloSAT = $vComplemento["content"];
                      break;
                    default:
                      //--
                      break;
                  }
                }

                //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$cfdi_comprobante_version]);
                if ($cfdi_comprobante_version != '') {
                  $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$reembolso_main_id.$reembolso_soli_list.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
                  $insertCFDIEstructura = DB::table('cfdi_comprobantes_fiscales')
                  ->insert(array(
                    "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
                    "origen_proceso" => "reembolso",
                    //"reembolso_vinculado_main" => $reembolso_main_id,
                    //"reembolso_vinculado_soli" => $reembolso_soli_list,
                    "cfdi_comprobante_version" => $cfdi_comprobante_version,
                    "cfdi_comprobante_serie" => $cfdi_comprobante_serie,
                    "cfdi_comprobante_folio" => $cfdi_comprobante_folio,
                    "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
                    "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
                    "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,
                    "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,
                    "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,
                    "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,
                    "cfdi_comprobante_total" => $cfdi_comprobante_total,
                    "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
                    "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
                    "cfdi_comprobante_lugar_de_expedicion" => $cfdi_comprobante_lugar_de_expedicion,
                    "cfdi_comprobante_no_de_certificado" => $cfdi_comprobante_no_de_certificado,
                    "cfdi_comprobante_sello" => $cfdi_comprobante_sello,
                    "cfdi_comprobante_certificado" => $cfdi_comprobante_certificado,
                    "cfdi_relacionados_tipo_de_relacion" => $cfdi_relacionados_tipo_de_relacion,
                    "cfdi_relacionados_uuid" => $cfdi_relacionados_uuid,
                    "cfdi_emisor_rfc" => $cfdi_emisor_rfc,
                    "cfdi_emisor_nombre" => $cfdi_emisor_nombre,
                    "cfdi_emisor_regimen_fiscal" => $cfdi_emisor_regimen_fiscal,
                    "cfdi_receptor_rfc" => $cfdi_receptor_rfc,
                    "cfdi_receptor_uso_del_cfdi" => $cfdi_receptor_uso_del_cfdi,
                    "cfdi_complementoUUID" => $cfdi_complementoUUID,
                    "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,
                    "cfdi_complementoRfcProvCertif" => $cfdi_complementoRfcProvCertif,
                    "cfdi_complementoNoCertificadoSAT" => $cfdi_complementoNoCertificadoSAT,
                    "cfdi_complementoSelloCFD" => $cfdi_complementoSelloCFD,
                    "cfdi_complementoSelloSAT" => $cfdi_complementoSelloSAT,
                  ));

                  $comprobante_fiscal_reem = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");
                  $insertCFDIVincReem = DB::table('cfdi_vinculacion_reembolsos')
                  ->insert(array(
                    "comprobante_fiscal" => $comprobante_fiscal_reem,
                    "reembolso_vinculado_main" => $reembolso_main_id,
                    "reembolso_vinculado_soli" => $reembolso_soli_list,
                  ));

                  for ($lrdc = 0; $lrdc < count($cfdi_conceptos); $lrdc++) {
                    $NoIdentificacion = $cfdi_conceptos[$lrdc]['NoIdentificacion'];
                    $ObjetoImp = $cfdi_conceptos[$lrdc]['ObjetoImp'];
                    $ClaveProdServ = $cfdi_conceptos[$lrdc]['ClaveProdServ'];
                    $cantidad = $cfdi_conceptos[$lrdc]['Cantidad'];
                    $ClaveUnidad = $cfdi_conceptos[$lrdc]['ClaveUnidad'];
                    $Unidad = $cfdi_conceptos[$lrdc]['Unidad'];
                    $concepto = $cfdi_conceptos[$lrdc]['Descripcion'];
                    //return response()->json(['status' => 'error','code' => 200,'message' => $concepto.' reem true5.3 '.$cfdi_comprobante_version]);
                    $precioUnitario = $cfdi_conceptos[$lrdc]['ValorUnitario'];
                    $descuentoXUni = $cfdi_conceptos[$lrdc]['Descuento'];
                    $importe = $cfdi_conceptos[$lrdc]['Importe'];
                    $retenciones = $cfdi_conceptos[$lrdc]['retenciones'];
                    $TotalRetenciones = $cfdi_conceptos[$lrdc]['TotalRetenciones'];
                    $traslados = $cfdi_conceptos[$lrdc]['traslados'];
                    $TotalTraslados = $cfdi_conceptos[$lrdc]['TotalTraslados'];
                    $Subtotal = $cfdi_conceptos[$lrdc]['Subtotal'];

                    $uuid_cfdi_detalle = Str::uuid()->toString();
                    $insertDetCFDICompra = DB::table('cfdi_comprobantes_conceptos')
                      ->insert(array(
                        "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                        "comprobante_fiscal" => $comprobante_fiscal_reem,
                        //"reembolso_vinculado_main" => $reembolso_main_id,
                        //"reembolso_vinculado_soli" => $reembolso_soli_list,
                        "NoIdentificacion" => $NoIdentificacion,
                        "ObjetoImp" => $ObjetoImp,
                        "ClaveProdServ" => $ClaveProdServ,
                        "Cantidad" => $cantidad,
                        "ClaveUnidad" => $ClaveUnidad,
                        "Unidad" => $Unidad,
                        "Descripcion" => $concepto,
                        "ValorUnitario" => $precioUnitario,
                        "Descuento" => $descuentoXUni,
                        "Importe" => $importe,
                        "TotalRetenciones" => $TotalRetenciones,
                        "TotalTraslados" => $TotalTraslados,
                        "Subtotal" => $Subtotal,
                        "empresa" => $main_empresa_id
                      ));

                    if (count($retenciones) != 0) {
                      for ($rreten = 0; $rreten < count($retenciones); $rreten++) {
                        $retencion_traslado = "rete";
                        $Base = $retenciones[$rreten]["Base"] ? $retenciones[$rreten]["Base"] : 0.00;
                        $Impuesto = $retenciones[$rreten]["Impuesto"] ? $retenciones[$rreten]["Impuesto"] : 000;
                        $TipoFactor = $retenciones[$rreten]["TipoFactor"] ? $retenciones[$rreten]["TipoFactor"] : NULL;
                        $TasaOCuota = $retenciones[$rreten]["TasaOCuota"] ? $retenciones[$rreten]["TasaOCuota"] : NULL;
                        $importe = $retenciones[$rreten]["Importe"] ? $retenciones[$rreten]["Importe"] : 0.00;
                        $impuesto_relacionado = $retenciones[$rreten]["impuesto_relacionado"];
                        $rete_homologada = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $impuesto_relacionado)->value("id") : NULL;

                        $insertDetCFDIImpuestoCompra = DB::table('eegr_compras_cfdi_detalle_impuestos')
                          ->insert(array(
                            "uuid_buydet_impuestos" => Str::uuid()->toString(),
                            "reembolso_vinculado_main" => $reembolso_main_id,
                            "reembolso_vinculado_soli" => $reembolso_soli_list,
                            "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                            "retencion_traslado" => $retencion_traslado,
                            "base" => $Base,
                            "impuesto" => $Impuesto,
                            "tipoFactor" => $TipoFactor,
                            "tasaOCuota" => $TasaOCuota,
                            "importe" => $importe
                          ));
                      }
                    }

                    if (count($traslados) != 0) {
                      for ($rtras = 0; $rtras < count($traslados); $rtras++) {
                        $retencion_traslado = "tras";
                        $Base = $traslados[$rtras]["Base"] ? $traslados[$rtras]["Base"] : 0.00;
                        $Impuesto = $traslados[$rtras]["Impuesto"] ? $traslados[$rtras]["Impuesto"] : 000;
                        $TipoFactor = $traslados[$rtras]["TipoFactor"] ? $traslados[$rtras]["TipoFactor"] : NULL;
                        $TasaOCuota = $traslados[$rtras]["TasaOCuota"] ? $traslados[$rtras]["TasaOCuota"] : NULL;
                        $importe = $traslados[$rtras]["Importe"] ? $traslados[$rtras]["Importe"] : 0.00;
                        $impuesto_relacionado = $traslados[$rtras]["impuesto_relacionado"];
                        $tras_homologado = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $impuesto_relacionado)->value("id") : NULL;

                        $insertDetCFDIImpuestoCompra = DB::table('eegr_compras_cfdi_detalle_impuestos')
                          ->insert(array(
                            "uuid_buydet_impuestos" => Str::uuid()->toString(),
                            "reembolso_vinculado_main" => $reembolso_main_id,
                            "reembolso_vinculado_soli" => $reembolso_soli_list,
                            "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                            "retencion_traslado" => $retencion_traslado,
                            "base" => $Base,
                            "impuesto" => $Impuesto,
                            "tipoFactor" => $TipoFactor,
                            "tasaOCuota" => $TasaOCuota,
                            "importe" => $importe
                          ));
                      }
                    }
                  }
                }
                //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.3 '.$cfdi_comprobante_version]);
              }
              
              if ($request->file('factura_xml')) {
                $xmlFile = $request->file('factura_xml');
                $xmlName = $xmlFile->getClientOriginalName();
                $xmlExt = $xmlFile->getClientOriginalExtension();
                $xmlTmpPath = $xmlFile->getPathname();
                Storage::putFileAs("/public/root/" . $filepath, $xmlTmpPath, $xmlName);
                $select_folio_xml = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-CFDI-XML%'");
                $token_documento = $JwtAuth->encriptarToken($reembolso_soli_list, $xmlExt, $xmlName);
                $insertXMLFact = DB::table("sos_documentos")->insert([
                  "token_documento" => $token_documento,
                  "fecha_carga" => time(),
                  "modulo" => "reembolsos",
                  "folio_modulo" => "REEM-CFDI-XML".$select_folio_xml[0]->folio,
                  "tipo_documento" => "xml",
                  "nombre_documento" => $JwtAuth->encriptar($xmlName),
                  "extension_documento" => $xmlExt,
                  "reembolso_main" => $reembolso_main_id,
                  "reembolso_solicitud" => $reembolso_soli_list,
                  "status_documento" => true,
                ]);
              }
      
              if ($request->file('factura_pdf')) {
                $pdfFile = $request->file('factura_pdf');
                $pdfName = $pdfFile->getClientOriginalName();
                $pdfExt  = $pdfFile->getClientOriginalExtension();
                $pdfTmpPath = $pdfFile->getPathname();
                
                Storage::putFileAs("/public/root/" . $filepath, $pdfTmpPath, $pdfName);
                $select_folio_pdf = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-CFDI-PDF%'");
                $token_documento = $JwtAuth->encriptarToken($reembolso_soli_list, $pdfExt, $pdfName);
                
                $insertPDFFact = DB::table("sos_documentos")->insert([
                  "token_documento" => $token_documento,
                  "fecha_carga" => time(),
                  "modulo" => "reembolsos",
                  "folio_modulo" => "REEM-CFDI-PDF".$select_folio_pdf[0]->folio,
                  "tipo_documento" => "pdf",
                  "nombre_documento" => $JwtAuth->encriptar($pdfName),
                  "extension_documento" => $pdfExt,
                  "reembolso_main" => $reembolso_main_id,
                  "reembolso_solicitud" => $reembolso_soli_list,
                  "status_documento" => true,
                ]);
                //return response()->json(['status' => 'error','code' => 200,'message' => 'test de PDF2']);
              }

              if (!empty($_FILES['reembolsos_anexos'])) {
                $doc_ads = $_FILES["reembolsos_anexos"];
                $string_ads_evid = json_encode($_FILES["reembolsos_anexos"]["name"]);
                if (count(json_decode($string_ads_evid)) != 0) {
                  $ads_nombre = json_decode($string_ads_evid);
                  for ($docADS = 0; $docADS < count($ads_nombre); $docADS++) {
                    $ads_temporal = $doc_ads["tmp_name"][$docADS];
                    $ads_name = $doc_ads["name"][$docADS];
                    $ads_ext = $doc_ads["type"][$docADS];
                    Storage::putFileAs("/public/root/" . $filepath, $ads_temporal, $ads_nombre[$docADS]);
                    $select_folio_ads = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-CFDI-ADS%'");
                    $token_documento = $JwtAuth->encriptarToken($reembolso_soli_list, $ads_ext, $ads_name);
                    
                    $insertXMLFact = DB::table("sos_documentos")->insert([
                      "token_documento" => $token_documento,
                      "fecha_carga" => time(),
                      "modulo" => "reembolsos",
                      "folio_modulo" => "REEM-CFDI-ADS".$select_folio_ads[0]->folio,
                      "tipo_documento" => "an",
                      "nombre_documento" => $JwtAuth->encriptar($ads_name),
                      "extension_documento" => $ads_ext,
                      "reembolso_main" => $reembolso_main_id,
                      "reembolso_solicitud" => $reembolso_soli_list,
                      "status_documento" => true,
                    ]);
                  }
                }
              }

              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Factura registrada',
                "token_solicitud_reem" => $token_reem_soli
              );
            }

          }
        } else {
				  $mensaje_error = '';
				  if (!$valida_reem_fecha) {$mensaje_error = 'Fecha de gasto incorrecta';}
				  if (!$valida_reem_folio_ticket) {$mensaje_error = 'Error en ticket de comprobación de gasto';}
				  if (!$valida_reem_pagado_a) {$mensaje_error = 'Error en pagado a';}
				  if (!$valida_reem_forma_pago) {$mensaje_error = 'Error en forma de pago';}
				  if (!$valida_reem_importe_total) {$mensaje_error = 'Error en importe total de reembolso';}
				  if (!$valida_reem_tipo_cambio) {$mensaje_error = 'Error en tipo de cambio';}
				  if (!$valida_reem_moneda_entrante) {$mensaje_error = 'Error en moneda';}
				  if (!$valida_reem_observacion) {$mensaje_error = 'Error en observaciones del reembolso';}
				  $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function reembolso_registro_fase_dos_delete(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_reembolso_main' => 'required|string',
        'token_solicitud_reem' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
			  $token_reembolso_main = $parametrosArray['token_reembolso_main'];
			  $token_solicitud_reem = $parametrosArray['token_solicitud_reem'];
        
			  $valida_reembolso_main = isset($token_reembolso_main) && !empty($token_reembolso_main);
			  $valida_solicitud_reem = isset($token_solicitud_reem) && !empty($token_solicitud_reem);

        if ($valida_reembolso_main && $valida_solicitud_reem) {
					$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->where([
            "reem_main.token_reem" => $token_reembolso_main, 
            "emp.empresa_token" => $usuario->empresa_token
          ])->get();

          foreach ($reembolso_main_selected as $vremb) {
            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).(!is_null($vremb->post_folio_reem) ? '-'.$vremb->post_folio_reem : '');
            $reembolso_main_id = DB::table("terc_reembolso_main")->where("token_reem", $vremb->token_reem)->value("id");
            
            $delete_reem_soli = DB::table('terc_reembolso_solicitud')
            ->where(["reembolso_main" => $reembolso_main_id,"token_solicitud_reem" => $token_solicitud_reem])
            ->limit(1)->update(array("status_activacion" => FALSE));

            if ($delete_reem_soli) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Factura eliminada'
              );
            }
          }
        } else {
				  $mensaje_error = '';
				  if (!$valida_reembolso_main) {$mensaje_error = 'Error en reembolso seleccionado';}
				  if (!$valida_solicitud_reem) {$mensaje_error = 'Error en factura/partida seleccionado';}
				  $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function reembolso_registro_fase_tres(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_reembolso_main' => 'required|string',
        'egresos_valua' => 'required|boolean',
        'valor_humano_valua' => 'required|boolean',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
			  $token_reembolso_main = $parametrosArray['token_reembolso_main'];
        $egresos_valua = $parametrosArray['egresos_valua'];
        $valor_humano_valua = $parametrosArray['valor_humano_valua'];
        
			  $valida_reembolso_main = isset($token_reembolso_main) && !empty($token_reembolso_main);
			  $valida_egresos_valua = isset($egresos_valua) && is_bool($egresos_valua); 
			  $valida_valor_humano_valua = isset($valor_humano_valua) && is_bool($valor_humano_valua);

        if ($valida_reembolso_main && $valida_egresos_valua && $valida_valor_humano_valua) {
					$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->where([
            "reem_main.token_reem" => $token_reembolso_main, 
            "emp.empresa_token" => $usuario->empresa_token
          ])->get();

          foreach ($reembolso_main_selected as $vremb) {
            $selectEmisorPers = DB::table("vhum_empleados_catalogo AS pers")
            ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
            ->where("users.usuario_token", $usuario->user_token)
            ->select('pers.id', 'users.usuario_token')->first();
            $emisor_pers = $selectEmisorPers ? $selectEmisorPers->id : '';
            $emisor_tkn_user = $selectEmisorPers ? $selectEmisorPers->usuario_token : '';

            $emisor_emp = DB::table("main_empresas")->where("empresa_token", $usuario->empresa_token)->value("id");

            $reembolso_main_id = DB::table("terc_reembolso_main")->where("token_reem", $vremb->token_reem)->value("id");
            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).(!is_null($vremb->post_folio_reem) ? '-'.$vremb->post_folio_reem : '');

            $select_last_version = DB::select("SELECT MAX(soli.id) AS version_last FROM terc_reembolso_solicitud AS soli JOIN terc_reembolso_main AS reem_main 
            WHERE soli.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$token_reembolso_main]);

            foreach ($select_last_version as $val_ver) {
              $regFolder = DB::table('terc_reembolso_main AS reem_main')
              ->where(["token_reem" => $token_reembolso_main])
              ->limit(1)->update(array("last_version" => $val_ver->version_last));
            }
            
            $titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem;
            //$select_reembolso_main = DB::select("SELECT id
            $JwtAuth->insertNotificacionSistema("Reembolsos", "ver_reembolso", $titulo_alerta, $reembolso_main_id, NULL, $emisor_emp, $emisor_pers, $emisor_pers);
            //$JwtAuth->notificacionPushDevices($emisor_tkn_user, "SOS-México - Portal para empleados", $titulo_alerta);

            if ($egresos_valua == TRUE) {
              //$egresos_user = 6;
              $JwtAuth->insertNotificacionSistema("Reembolsos", "ver_reembolso", $titulo_alerta, $reembolso_main_id, NULL, $emisor_emp, $emisor_pers, $vremb->user_receptor_egr);
              //$selectDTUserEgr = DB::select("SELECT users.usuario_token FROM teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers WHERE users.empleado = pers.id 
              //    AND pers.id = ?", [$vremb->user_receptor_egr]);
              //foreach ($selectDTUserEgr as $vuedt) {
              //  $JwtAuth->notificacionPushDevices($vuedt->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
              //}
            }

            if ($valor_humano_valua == TRUE) {
              //$valor_humano_user = 7;
              $JwtAuth->insertNotificacionSistema("Reembolsos", "ver_reembolso", $titulo_alerta, $reembolso_main_id, NULL, $emisor_emp, $emisor_pers, $vremb->user_receptor_vh);
              //$selectDTUserVHUM = DB::select("SELECT users.usuario_token FROM teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers WHERE users.empleado = pers.id 
              //    AND pers.id = ?", [$vremb->user_receptor_vh]);
              //foreach ($selectDTUserVHUM as $vuvhdt) {
              //  $JwtAuth->notificacionPushDevices($vuvhdt->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
              //}
            }

						DB::table("terc_reembolso_main")
						->where("token_reem", $vremb->token_reem)
						->limit(1)->update(array("borrador_reem" => FALSE));

            $dataMensaje = array(
              'message' => 'reem_saved',
              'folio_reem' => $folio_reem,
              'token_reembolso_main' => $token_reembolso_main,
              'code' => 200,
              'status' => 'success'
            );

          }
        } else {
				  $mensaje_error = '';
				  if (!$valida_reembolso_main) {$mensaje_error = 'Error en reembolso seleccionado';}
				  if (!$valida_egresos_valua) {$mensaje_error = 'Error al comprobar si Egresos Evalua este reembolso';}
				  if (!$valida_valor_humano_valua) {$mensaje_error = 'Error al comprobar si Valor humano Evalua este reembolso';}
				  $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function reembolso_registro(Request $request){
		$JwtAuth = new \JwtAuth();
		$user_token = $request->input('user_token');
		$reembolsos = $request->input('reembolsos');
		$comisiones = json_decode($request->input('comisiones'), true);
		$acreedor = $request->input('acreedor');
		$tiempo_respuesta_reem_comi = $request->input('tiempo_respuesta_reem_comi');
		//return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1r'.count($reembolsos)]);
		$validate = \Validator::make($request->all(), [
			"user_token" => "required|string",
			"reembolsos" => "required|array",
			"comisiones" => "required|string",
			"tiempo_respuesta_reem_comi" => "required|numeric",
			"acreedor" => "required|string",
		]);
		if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
				'errors' => $validate->errors()
			);
		} else {
      //exit;
			$usuario = $JwtAuth->checkToken($user_token, true);
			$emisor_emp = "";

			$valida_reem = isset($reembolsos) && !empty($reembolsos);
			$valida_comi = isset($comisiones) && !empty($comisiones);
			$valida_acreedor = isset($acreedor) && !empty($acreedor);
			$valida_tiempo_respuesta = isset($tiempo_respuesta_reem_comi) && !empty($tiempo_respuesta_reem_comi) && preg_match($JwtAuth->filtroNumerico(), $tiempo_respuesta_reem_comi);

			if ($valida_reem && $valida_comi && $valida_tiempo_respuesta && $valida_acreedor) {
				$queryHabReem = DB::table("fnzs_catalogo_acreedores")->where("token_cat_acreedores",$acreedor)->value("acr_habilita_reembolsos");
        
        if ($queryHabReem) {
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
            AND emp.id = empuser.empresa AND empuser.empleado = pers.id 
            AND pers.id = users.empleado AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

          foreach ($queryEmp as $vEmp) {
            $acreedor_id = DB::table("fnzs_catalogo_acreedores")->where("token_cat_acreedores",$acreedor)->value("id");
            //da_te_default_timezone_set($vEmp->zona_horaria);
            $tiempo_respuesta = time() + (86400 * ($tiempo_respuesta_reem_comi / 24));
            
            $folioSistema = DB::select("SELECT fold.folder+1 AS folio,fold.post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
              JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE fold.reembolsos = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
              AND empuser.empleado = pers.id AND pers.id = users.empleado AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);
  
            if (count($folioSistema) == 1) {
              if ($folioSistema[0]->folio == 1000000000) {
                $post_folio_db = DB::select("SELECT post_folio_reem FROM reembolso_main WHERE id = (SELECT Max(reem.id) FROM reembolso_main AS reem JOIN main_empresas AS emp 
                  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE reem.emisor = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                  AND empuser.usuario = users.id AND users.usuario_token = ?)", [$usuario->empresa_token, $usuario->user_token]);
                $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio_reem);
                $folio_nuevo = 1;
              } else {
                $post_folio = NULL;
                $folio_nuevo = $folioSistema[0]->folio;
              }
            } else {
              $post_folio = NULL;
              $folio_nuevo = 1;
            }
  
            $folio_reem = "REEM-".$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');
            $selectEmisorPers = DB::table("vhum_empleados_catalogo AS pers")
              ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
              ->where("users.usuario_token", $usuario->user_token)
              ->select('pers.id', 'users.usuario_token')->first();
            $emisor_pers = $selectEmisorPers ? $selectEmisorPers->id : '';
            $emisor_tkn_user = $selectEmisorPers ? $selectEmisorPers->usuario_token : '';
  
            $emisor_emp = DB::table("main_empresas")->where("empresa_token", $usuario->empresa_token)->value("id");
  
            $selectPowerVhum = DB::table("configuracion_systema_vhum AS conf_vhum")
              ->join("main_empresas AS emp", "conf_vhum.empresa", "=", "emp.id")
              ->where("conf_vhum.jerarquia", "P")
              ->where("conf_vhum.reembolsos", TRUE)
              ->where("emp.empresa_token", $usuario->empresa_token)
              ->select('conf_vhum.usuario')->first();
            $receptor_pers = $selectPowerVhum ? $selectPowerVhum->usuario : '';
  
            $token_reembolso_main = $JwtAuth->encriptarToken(rand(5, 15) . $folio_reem . time() . $emisor_emp . $emisor_pers . $usuario->empresa_token, $usuario->user_token);
  
            $comision_identificador = NULL;
            $egresos_valua = FALSE;
            $egresos_aplica = NULL;
            $egresos_user = NULL;
            $egresos_tiempo_respuesta = null;
  
            $valor_humano_valua = FALSE;
            $valor_humano_aplica = NULL;
            $valor_humano_user = NULL;
            $valor_humano_tiempo_respuesta = null;
  
            foreach ($comisiones as $r_comi => $rComi) {
              $token_comision_main = $rComi["token_comision_main"];
              $comisionData = DB::table("terc_comisiones_main")->where(["token_comision_main" => $token_comision_main])->get();
              foreach ($comisionData as $forC) {
                if ($forC->valor_humano == TRUE) {
                  $valor_humano_valua = TRUE;
                  $valor_humano_user = 3;
                  $valor_humano_tiempo_respuesta = $tiempo_respuesta;
                } else {
                  $valor_humano_aplica = "N";
                }
                if ($forC->egresos == TRUE) {
                  $egresos_valua = TRUE;
                  $egresos_user = 4;
                  $egresos_tiempo_respuesta = $tiempo_respuesta;
                } else {
                  $egresos_aplica = "N";
                }
              }
            }

            $newReem = new ReembolsoModelo();
            $newReem->token_reem = $token_reembolso_main;
            $newReem->folio_reem = $folio_nuevo;
            $newReem->post_folio_reem = $post_folio;
            $newReem->fecha_sistema = time();
            $newReem->emisor = $emisor_emp;
            $newReem->receptor = $emisor_emp;
            $newReem->status_reem = TRUE;
            $newReem->fecha_delete = NULL;
            $newReem->user_emisor = $emisor_pers;
            $newReem->user_acreedor = $acreedor_id;
            $newReem->user_receptor_vh = $valor_humano_user;
            $newReem->tiempo_respuesta_auth_vh = $valor_humano_tiempo_respuesta;
            $newReem->user_receptor_egr = $egresos_user;
            $newReem->tiempo_respuesta_auth_egr = $egresos_tiempo_respuesta;
            $insertReem = $newReem->save();
            if ($insertReem) {
              $select_reembolso_main = $newReem->id;
              foreach ($comisiones as $l_comi => $com_i) {
                $token_comision_main = $com_i["token_comision_main"];
                $comisionID = DB::table("terc_comisiones_main")->where("token_comision_main", $token_comision_main)->value("id");
  
                if ($comisionID) {
                  DB::table('sos_reembolsos_comisiones_rel')->insert([
                    "token_rel_comi_reem" => $JwtAuth->encriptarToken($token_reembolso_main.$token_comision_main.$comisionID.$select_reembolso_main), 
                    "comision" => $comisionID, 
                    "reembolso_main" => $select_reembolso_main
                  ]);
                }
              }
  
              $countReembolsos = 0;
              foreach ($reembolsos as $lreemk => $reemFacts) {
                $new_folio_solicitud = $lreemk + 1;
                $new_folio_all_solicitud = $JwtAuth->generarFolio($lreemk + 1);
  
                $reem_fecha = $reemFacts["reem_fecha"];
                $reem_folio_ticket = $reemFacts["reem_folio_ticket"];
                $reem_pagado_a = $reemFacts["reem_pagado_a"];
                $proveedor_tkn = $reemFacts["proveedor_tkn"];
                $tkn_forma_pago = $reemFacts["tkn_forma_pago"];
                $reem_importe_total = $reemFacts["reem_importe_total"];
                $reem_observacion = $reemFacts["reem_observacion"];
                $reem_moneda_nombre = $reemFacts["reem_moneda_nombre"];
                $reem_tipo_cambio = $reemFacts["reem_tipo_cambio_string"];
                $cfdi_comprobante = $reemFacts['dataCFDI_comprobante'];
                $cfdi_emisor = $reemFacts['dataCFDIEmisor'];
                $cfdi_receptor = $reemFacts['dataCFDIReceptor'];
                $cfdi_relacionados = $reemFacts['dataCFDIRelacionados'];
                $cfdi_conceptos = $reemFacts['dataCFDI_conceptos'];
                $cfdi_impuestos_retenidos = $reemFacts['dataCFDI_impuestos_retenidos_lista'];
                $cfdi_impuestos_trasladados = $reemFacts['dataCFDI_impuestos_trasladados_lista'];
                $cfdi_complemento = $reemFacts['dataCFDIComplemento'];
  
                $proveedor_id = $proveedor_tkn != "" ? DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $proveedor_tkn)->value("id") : NULL;
  
                //ARCHIVOS
                $archivo_xml = $request->file("reembolsos.$lreemk.factura_xml");
                $archivo_pdf = $request->file("reembolsos.$lreemk.factura_pdf");
                $anexos = $request->file("reembolsos.$lreemk.reembolsos_anexos");
                //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.003']);
  
                $token_reem_soli = $JwtAuth->encriptarToken($select_reembolso_main . $new_folio_all_solicitud . $reem_fecha . $reem_folio_ticket . $reem_pagado_a .
                  $tkn_forma_pago . $reem_importe_total . $reem_observacion);
  
                $insert_reem_soli = DB::table('terc_reembolso_solicitud')->insert(
                  array(
                    "token_solicitud_reem" => $token_reem_soli,
                    "folio_solicitud" => $new_folio_solicitud,
                    "fecha_solicitud" => time(),
                    "reembolso_main" => $select_reembolso_main,
                    "fecha_gasto" => $JwtAuth->convierteFechaEpoc($reem_fecha),
                    "ticket_gasto" => $JwtAuth->encriptar($reem_folio_ticket),
                    "pagado_a" => $reem_pagado_a,
                    "proveedor" => $proveedor_id,
                    "forma_pago" => $tkn_forma_pago,
                    "importe_entrante" => $reem_importe_total,
                    "moneda_entrante" => $reem_moneda_nombre,
                    "tipo_cambio" => $reem_tipo_cambio,
                    "motivo_reem" => $JwtAuth->encriptar($reem_observacion),
                    "autorizacion_vh" => $valor_humano_aplica,
                    "autorizacion_egr" => $egresos_aplica,
                    "tiempo_respuesta_autorizacion" => $tiempo_respuesta,
                    "version" => TRUE,
                    "fecha_delete" => NULL,
                  )
                );
                if ($insert_reem_soli) {
                  $filepath = $vEmp->root_tkn . "/0010-reem/" . $folio_reem . "/" . $JwtAuth->generarFolio($new_folio_solicitud) . "/anexos";
  
                  if (!file_exists(storage_path("/root/" . $filepath))) {
                    Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
                  }
  
                  $reembolso_soli_list = DB::table('terc_reembolso_solicitud')->where("token_solicitud_reem", $token_reem_soli)->value("id");
                  $data_comprobante = json_decode($cfdi_comprobante, true);
                  if (json_last_error() === JSON_ERROR_NONE && is_array($data_comprobante)) {
                    $cfdi_comprobante_version = '';
                    $cfdi_comprobante_serie = '';
                    $cfdi_comprobante_folio = '';
                    $cfdi_comprobante_fecha = '';
                    $cfdi_comprobante_forma_de_pago = '';
                    $cfdi_comprobante_metodo_de_pago = '';
                    $cfdi_comprobante_subtotal = '';
                    $cfdi_comprobante_moneda = '';
                    $cfdi_comprobante_tipo_de_cambio = '';
                    $cfdi_comprobante_total = '';
                    $cfdi_comprobante_confirmacion = '';
                    $cfdi_comprobante_tipo_de_comprobante = '';
                    $cfdi_comprobante_lugar_de_expedicion = '';
                    $cfdi_comprobante_no_de_certificado = '';
                    $cfdi_comprobante_sello = '';
                    $cfdi_comprobante_certificado = '';
  
                    foreach ($data_comprobante as $vComp) {
                      switch ($vComp["title"]) {
                        case 'Versión':
                          $cfdi_comprobante_version = $vComp["content"];
                          break;
  
                        case 'Serie':
                          $cfdi_comprobante_serie = $vComp["content"];
                          break;
  
                        case 'Folio':
                          $cfdi_comprobante_folio = $vComp["content"];
                          break;
  
                        case 'Fecha':
                          $cfdi_comprobante_fecha = $vComp["content"];
                          break;
  
                        case 'Forma de pago':
                          $cfdi_comprobante_forma_de_pago = $vComp["content"];
                          break;
  
                        case 'Subtotal':
                          $cfdi_comprobante_subtotal = $vComp["content"];
                          break;
  
                        case 'Moneda':
                          $cfdi_comprobante_moneda = $vComp["content"];
                          break;
  
                        case 'Tipo de cambio':
                          $cfdi_comprobante_tipo_de_cambio = $vComp["content"];
                          break;
  
                        case 'Total':
                          $cfdi_comprobante_total = $vComp["content"];
                          break;
  
                        case 'Confirmación':
                          $cfdi_comprobante_confirmacion = $vComp["content"];
                          break;
  
                        case 'Tipo de comprobante':
                          $cfdi_comprobante_tipo_de_comprobante = $vComp["content"];
                          break;
  
                        case 'Método de Pago':
                          $cfdi_comprobante_metodo_de_pago = $vComp["content"];
                          break;
  
                        case 'Lugar de Expedición':
                          $cfdi_comprobante_lugar_de_expedicion = $vComp["content"];
                          break;
  
                        case 'No de certificado':
                          $cfdi_comprobante_no_de_certificado = $vComp["content"];
                          break;
  
                        case 'Sello':
                          $cfdi_comprobante_sello = $vComp["content"];
                          break;
  
                        case 'Certificado':
                          $cfdi_comprobante_certificado = $vComp["content"];
                          break;
  
                        default:
                          # code...
                          break;
                      }
                    }
  
                    $cfdi_relacionados_tipo_de_relacion = '';
                    $cfdi_relacionados_uuid = '';
                    $data_relacionados = json_decode($cfdi_relacionados, true);
                    foreach ($data_relacionados as $CFDIr) {
                      switch ($CFDIr["title"]) {
                        case 'Tipo de relación':
                          $cfdi_relacionados_tipo_de_relacion = $CFDIr["content"];
                          break;
                        case 'UUID':
                          $cfdi_relacionados_uuid = $CFDIr["content"];
                          break;
  
                        default:
                          //--
                          break;
                      }
                    }
                    //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.CFDI2']);
                    $cfdi_emisor_rfc = '';
                    $cfdi_emisor_nombre = '';
                    $cfdi_emisor_regimen_fiscal = '';
                    $data_emisor = json_decode($cfdi_emisor, true);
                    foreach ($data_emisor as $CFDIe) {
                      switch ($CFDIe["title"]) {
                        case 'Rfc del emisor':
                          $cfdi_emisor_rfc = $CFDIe["content"];
                          break;
                        case 'Nombre del emisor':
                          $cfdi_emisor_nombre = $CFDIe["content"];
                          break;
                        case 'Regimen fiscal del emisor':
                          $cfdi_emisor_regimen_fiscal = $CFDIe["content"];
                          break;
                        default:
                          //--
                          break;
                      }
                    }
  
                    $cfdi_receptor_rfc = '';
                    $cfdi_receptor_uso_del_cfdi = '';
                    $data_receptor = json_decode($cfdi_receptor, true);
                    foreach ($data_receptor as $CFDIReceptor) {
                      switch ($CFDIReceptor["title"]) {
                        case 'Rfc del receptor':
                          $cfdi_receptor_rfc = $CFDIReceptor["content"];
                          break;
                        case 'Uso del CFDI':
                          $cfdi_receptor_uso_del_cfdi = $CFDIReceptor["content"];
                          break;
                        default:
                          //--
                          break;
                      }
                    }
  
                    //foreach ($cfdi_impuestos_retenidos as $vComp) {}
                    //foreach ($cfdi_impuestos_trasladados as $vComp) {}
  
                    $cfdi_complementoUUID = '';
                    $cfdi_complementoFechaTimbrado = '';
                    $cfdi_complementoRfcProvCertif = '';
                    $cfdi_complementoNoCertificadoSAT = '';
                    $cfdi_complementoSelloCFD = '';
                    $cfdi_complementoSelloSAT = '';
                    $data_complemento = json_decode($cfdi_complemento, true);
                    foreach ($data_complemento as $vComplemento) {
                      switch ($vComplemento["title"]) {
                        case 'UUID':
                          $cfdi_complementoUUID = $vComplemento["content"];
                          break;
                        case 'FechaTimbrado':
                          $cfdi_complementoFechaTimbrado = $vComplemento["content"];
                          break;
                        case 'RfcProvCertif':
                          $cfdi_complementoRfcProvCertif = $vComplemento["content"];
                          break;
                        case 'NoCertificadoSAT':
                          $cfdi_complementoNoCertificadoSAT = $vComplemento["content"];
                          break;
                        case 'SelloCFD':
                          $cfdi_complementoSelloCFD = $vComplemento["content"];
                          break;
                        case 'SelloSAT':
                          $cfdi_complementoSelloSAT = $vComplemento["content"];
                          break;
                        default:
                          //--
                          break;
                      }
                    }
  
                    //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$cfdi_comprobante_version]);
                    if ($cfdi_comprobante_version != '') {
                      $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$select_reembolso_main.$reembolso_soli_list.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
                      $insertCFDIEstructura = DB::table('cfdi_comprobantes_fiscales')
                        ->insert(array(
                          "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
                          "origen_proceso" => "reembolso",
                          //"reembolso_vinculado_main" => $select_reembolso_main,
                          //"reembolso_vinculado_soli" => $reembolso_soli_list,
                          "cfdi_comprobante_version" => $cfdi_comprobante_version,
                          "cfdi_comprobante_serie" => $cfdi_comprobante_serie,
                          "cfdi_comprobante_folio" => $cfdi_comprobante_folio,
                          "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
                          "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
                          "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,
                          "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,
                          "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,
                          "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,
                          "cfdi_comprobante_total" => $cfdi_comprobante_total,
                          "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
                          "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
                          "cfdi_comprobante_lugar_de_expedicion" => $cfdi_comprobante_lugar_de_expedicion,
                          "cfdi_comprobante_no_de_certificado" => $cfdi_comprobante_no_de_certificado,
                          "cfdi_comprobante_sello" => $cfdi_comprobante_sello,
                          "cfdi_comprobante_certificado" => $cfdi_comprobante_certificado,
                          "cfdi_relacionados_tipo_de_relacion" => $cfdi_relacionados_tipo_de_relacion,
                          "cfdi_relacionados_uuid" => $cfdi_relacionados_uuid,
                          "cfdi_emisor_rfc" => $cfdi_emisor_rfc,
                          "cfdi_emisor_nombre" => $cfdi_emisor_nombre,
                          "cfdi_emisor_regimen_fiscal" => $cfdi_emisor_regimen_fiscal,
                          "cfdi_receptor_rfc" => $cfdi_receptor_rfc,
                          "cfdi_receptor_uso_del_cfdi" => $cfdi_receptor_uso_del_cfdi,
                          "cfdi_complementoUUID" => $cfdi_complementoUUID,
                          "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,
                          "cfdi_complementoRfcProvCertif" => $cfdi_complementoRfcProvCertif,
                          "cfdi_complementoNoCertificadoSAT" => $cfdi_complementoNoCertificadoSAT,
                          "cfdi_complementoSelloCFD" => $cfdi_complementoSelloCFD,
                          "cfdi_complementoSelloSAT" => $cfdi_complementoSelloSAT,
                        ));
    
                      $comprobante_fiscal_reem = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");
                      $insertCFDIVincReem = DB::table('cfdi_vinculacion_reembolsos')
                      ->insert(array(
                        "comprobante_fiscal" => $comprobante_fiscal_reem,
                        "reembolso_vinculado_main" => $select_reembolso_main,
                        "reembolso_vinculado_soli" => $reembolso_soli_list,
                      ));
                        
                      $data_conceptos = json_decode($cfdi_conceptos, true);
                      for ($lrdc = 0; $lrdc < count($data_conceptos); $lrdc++) {
                        $NoIdentificacion = $data_conceptos[$lrdc]['NoIdentificacion'];
                        $ObjetoImp = $data_conceptos[$lrdc]['ObjetoImp'];
                        $ClaveProdServ = $data_conceptos[$lrdc]['ClaveProdServ'];
                        $cantidad = $data_conceptos[$lrdc]['Cantidad'];
                        $ClaveUnidad = $data_conceptos[$lrdc]['ClaveUnidad'];
                        $Unidad = $data_conceptos[$lrdc]['Unidad'];
                        $concepto = $data_conceptos[$lrdc]['Descripcion'];
                        //return response()->json(['status' => 'error','code' => 200,'message' => $concepto.' reem true5.3 '.$cfdi_comprobante_version]);
                        $precioUnitario = $data_conceptos[$lrdc]['ValorUnitario'];
                        $descuentoXUni = $data_conceptos[$lrdc]['Descuento'];
                        $importe = $data_conceptos[$lrdc]['Importe'];
                        $retenciones = $data_conceptos[$lrdc]['retenciones'];
                        $TotalRetenciones = $data_conceptos[$lrdc]['TotalRetenciones'];
                        $traslados = $data_conceptos[$lrdc]['traslados'];
                        $TotalTraslados = $data_conceptos[$lrdc]['TotalTraslados'];
                        $Subtotal = $data_conceptos[$lrdc]['Subtotal'];
    
                        $uuid_cfdi_detalle = Str::uuid()->toString();
                        $insertDetCFDICompra = DB::table('cfdi_comprobantes_conceptos')
                          ->insert(array(
                            "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                            "comprobante_fiscal" => $comprobante_fiscal_reem,
                            //"reembolso_vinculado_main" => $select_reembolso_main,
                            //"reembolso_vinculado_soli" => $reembolso_soli_list,
                            "NoIdentificacion" => $NoIdentificacion,
                            "ObjetoImp" => $ObjetoImp,
                            "ClaveProdServ" => $ClaveProdServ,
                            "Cantidad" => $cantidad,
                            "ClaveUnidad" => $ClaveUnidad,
                            "Unidad" => $Unidad,
                            "Descripcion" => $concepto,
                            "ValorUnitario" => $precioUnitario,
                            "Descuento" => $descuentoXUni,
                            "Importe" => $importe,
                            "TotalRetenciones" => $TotalRetenciones,
                            "TotalTraslados" => $TotalTraslados,
                            "Subtotal" => $Subtotal,
                            "empresa" => $vEmp->id
                          ));
    
                        if (count($retenciones) != 0) {
                          for ($rreten = 0; $rreten < count($retenciones); $rreten++) {
                            $retencion_traslado = "rete";
                            $Base = $retenciones[$rreten]["Base"] ? $retenciones[$rreten]["Base"] : 0.00;
                            $Impuesto = $retenciones[$rreten]["Impuesto"] ? $retenciones[$rreten]["Impuesto"] : 000;
                            $TipoFactor = $retenciones[$rreten]["TipoFactor"] ? $retenciones[$rreten]["TipoFactor"] : NULL;
                            $TasaOCuota = $retenciones[$rreten]["TasaOCuota"] ? $retenciones[$rreten]["TasaOCuota"] : NULL;
                            $importe = $retenciones[$rreten]["Importe"] ? $retenciones[$rreten]["Importe"] : 0.00;
                            $impuesto_relacionado = $retenciones[$rreten]["impuesto_relacionado"];
                            $rete_homologada = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $impuesto_relacionado)->value("id") : NULL;
    
                            $insertDetCFDIImpuestoCompra = DB::table('eegr_compras_cfdi_detalle_impuestos')
                              ->insert(array(
                                "uuid_buydet_impuestos" => Str::uuid()->toString(),
                                "reembolso_vinculado_main" => $select_reembolso_main,
                                "reembolso_vinculado_soli" => $reembolso_soli_list,
                                "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                                "retencion_traslado" => $retencion_traslado,
                                "base" => $Base,
                                "impuesto" => $Impuesto,
                                "tipoFactor" => $TipoFactor,
                                "tasaOCuota" => $TasaOCuota,
                                "importe" => $importe
                              ));
                          }
                        }
    
                        if (count($traslados) != 0) {
                          for ($rtras = 0; $rtras < count($traslados); $rtras++) {
                            $retencion_traslado = "tras";
                            $Base = $traslados[$rtras]["Base"] ? $traslados[$rtras]["Base"] : 0.00;
                            $Impuesto = $traslados[$rtras]["Impuesto"] ? $traslados[$rtras]["Impuesto"] : 000;
                            $TipoFactor = $traslados[$rtras]["TipoFactor"] ? $traslados[$rtras]["TipoFactor"] : NULL;
                            $TasaOCuota = $traslados[$rtras]["TasaOCuota"] ? $traslados[$rtras]["TasaOCuota"] : NULL;
                            $importe = $traslados[$rtras]["Importe"] ? $traslados[$rtras]["Importe"] : 0.00;
                            $impuesto_relacionado = $traslados[$rtras]["impuesto_relacionado"];
                            $tras_homologado = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $impuesto_relacionado)->value("id") : NULL;
    
                            $insertDetCFDIImpuestoCompra = DB::table('eegr_compras_cfdi_detalle_impuestos')
                              ->insert(array(
                                "uuid_buydet_impuestos" => Str::uuid()->toString(),
                                "reembolso_vinculado_main" => $select_reembolso_main,
                                "reembolso_vinculado_soli" => $reembolso_soli_list,
                                "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                                "retencion_traslado" => $retencion_traslado,
                                "base" => $Base,
                                "impuesto" => $Impuesto,
                                "tipoFactor" => $TipoFactor,
                                "tasaOCuota" => $TasaOCuota,
                                "importe" => $importe
                              ));
                          }
                        }
                      }
                    }
                    //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.3 '.$cfdi_comprobante_version]);
                  }
                  if ($archivo_xml) {
                    $nombre_original = $archivo_xml->getClientOriginalName();
                    $ext_doc = $archivo_xml->getClientOriginalExtension();
  
                    $documento_crypt = $JwtAuth->encriptar($nombre_original);
                    $token_documento = $JwtAuth->encriptarToken($reembolso_soli_list, $ext_doc, $nombre_original);
  
                    $insertXMLFact = DB::table("sos_documentos")->insert([
                      "token_documento" => $token_documento,
                      "fecha_carga" => time(),
                      "modulo" => "reembolsos",
                      "folio_modulo" => "REEM-CFDI-XML",
                      "tipo_documento" => "xml",
                      "nombre_documento" => $documento_crypt,
                      "extension_documento" => $ext_doc,
                      "reembolso_main" => $select_reembolso_main,
                      "reembolso_solicitud" => $reembolso_soli_list,
                      "status_documento" => true,
                    ]);
  
                    if ($insertXMLFact) {
                      $archivo_xml->storeAs("public/root/$filepath", $nombre_original);
                    }
                  }
  
                  if ($archivo_pdf) {
                    $nombre_original = $archivo_pdf->getClientOriginalName();
                    $ext_doc = $archivo_pdf->getClientOriginalExtension();
  
                    $documento_crypt = $JwtAuth->encriptar($nombre_original);
                    $token_documento = $JwtAuth->encriptarToken($reembolso_soli_list, $ext_doc, $nombre_original);
  
                    $insertPDFFact = DB::table("sos_documentos")->insert([
                      "token_documento" => $token_documento,
                      "fecha_carga" => time(),
                      "modulo" => "reembolsos",
                      "folio_modulo" => "REEM-CFDI-PDF",
                      "tipo_documento" => "pdf",
                      "nombre_documento" => $documento_crypt,
                      "extension_documento" => $ext_doc,
                      "reembolso_main" => $select_reembolso_main,
                      "reembolso_solicitud" => $reembolso_soli_list,
                      "status_documento" => true,
                    ]);
  
                    if ($insertPDFFact) {
                      $archivo_pdf->storeAs("public/root/$filepath", $nombre_original);
                    }
                  }
  
                  if ($anexos && is_array($anexos)) {
                    foreach ($anexos as $anexDoc) {
                      $originalName = $anexDoc->getClientOriginalName();
                      $ext_doc = $anexDoc->getClientOriginalExtension();
                      $documento_crypt = $JwtAuth->encriptar($originalName);
                      $temporal = $anexDoc->getPathname();
                      $token_documento = $JwtAuth->encriptarToken($select_reembolso_main . $reembolso_soli_list . $ext_doc . $originalName);
                      $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-EVID%'");
                      $insertAdsFact = DB::table("sos_documentos")->insert(
                        array(
                          "token_documento" => $token_documento,
                          "fecha_carga" => time(),
                          "modulo" => "reembolsos",
                          "folio_modulo" => "REEM-EVID" . end($select_folio_doc)->folio,
                          "tipo_documento" => "an",
                          "nombre_documento" => $documento_crypt,
                          "extension_documento" => $ext_doc,
                          "reembolso_main" => $select_reembolso_main,
                          "reembolso_solicitud" => $reembolso_soli_list,
                          "status_documento" => TRUE,
                        )
                      );
  
                      if ($insertAdsFact) {
                        Storage::putFileAs("/public/root/" . $filepath, $temporal, $originalName);
                      }
                    }
                  }
                  $countReembolsos++;
                }
              }
  
              $select_last_version = DB::select("SELECT MAX(soli.id) AS version_last FROM terc_reembolso_solicitud AS soli JOIN terc_reembolso_main AS reem_main 
                  WHERE soli.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$token_reembolso_main]);
  
              foreach ($select_last_version as $val_ver) {
                $regFolder = DB::table('terc_reembolso_main AS reem_main')
                  ->where(["token_reem" => $token_reembolso_main])
                  ->limit(1)->update(array("last_version" => $val_ver->version_last));
              }
  
              if ($countReembolsos == count($reembolsos)) {
                if (count($folioSistema) == 0) {
                  $insertSistema = DB::table('sos_last_folders')
                    ->insert(
                      array(
                        "reembolsos" => TRUE,
                        "folder" => 1,
                        "post_folder" => $post_folio,
                        "empresa" => $emisor_emp,
                      )
                    );
                } else {
                  $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                    ->where([
                      'sos_last_folders.reembolsos' => TRUE,
                      'emp.empresa_token' => $usuario->empresa_token,
                      'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                      array(
                        'sos_last_folders.folder' => $folio_nuevo,
                        'sos_last_folders.post_folder' => $post_folio,
                      )
                    );
                }
  
                $titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem;
                //$select_reembolso_main = DB::select("SELECT id
                $JwtAuth->insertNotificacionSistema("Reembolsos", "Registro de reembolso", $titulo_alerta, $select_reembolso_main, NULL, $emisor_emp, $emisor_pers, $emisor_pers);
                $JwtAuth->notificacionPushDevices($emisor_tkn_user, "SOS-México - Portal para empleados", $titulo_alerta);
  
                if ($egresos_valua == TRUE) {
                  //$egresos_user = 6;
                  $JwtAuth->insertNotificacionSistema("Reembolsos", "Registro de reembolso", $titulo_alerta, $select_reembolso_main, NULL, $emisor_emp, $emisor_pers, $egresos_user);
                  $selectDTUserEgr = DB::select("SELECT users.usuario_token FROM teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers WHERE users.empleado = pers.id 
                      AND pers.id = ?", [$egresos_user]);
                  foreach ($selectDTUserEgr as $vuedt) {
                    $JwtAuth->notificacionPushDevices($vuedt->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
                  }
                }
  
                if ($valor_humano_valua == TRUE) {
                  //$valor_humano_user = 7;
                  $JwtAuth->insertNotificacionSistema("Reembolsos", "Registro de reembolso", $titulo_alerta, $select_reembolso_main, NULL, $emisor_emp, $emisor_pers, $valor_humano_user);
                  $selectDTUserVHUM = DB::select("SELECT users.usuario_token FROM teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers WHERE users.empleado = pers.id 
                      AND pers.id = ?", [$valor_humano_user]);
                  foreach ($selectDTUserVHUM as $vuvhdt) {
                    $JwtAuth->notificacionPushDevices($vuvhdt->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
                  }
                }
  
                $dataMensaje = array(
                  'message' => 'reem_saved',
                  'folio_reem' => $folio_reem,
                  'token_reembolso_main' => $token_reembolso_main,
                  'code' => 200,
                  'status' => 'success'
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'reem_fail_inside'
                );
              }
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'reem_fail_inside');
            }
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Usuario sin acceso a registro de reembolsos');
        }
			} else {
				$mensaje_error = '';
				//if (!$valida_habilita_reembolsos) {$mensaje_error = 'Usuario sin acceso a registro de reembolsos';}
				if (!$valida_reem || !$valida_comi) {$mensaje_error = 'reem_list_fail';}
				if (!$valida_acreedor) {$mensaje_error = 'Usuario no registrado como acreedor';}
				$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
			}
		}

		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function reembolso_old_registro(Request $request){
		$JwtAuth = new \JwtAuth();
		$user_token = $request->input('user_token');
		$reembolsos = $request->input('reembolsos');
		$comisiones = json_decode($request->input('comisiones'), true);
		//$habilita_reembolsos = json_decode($request->input('habilita_reembolsos'), true);
		$acreedor = $request->input('acreedor');
		$tiempo_respuesta_reem_comi = $request->input('tiempo_respuesta_reem_comi');

		$validate = \Validator::make($request->all(), [
			"user_token" => "required|string",
			"reembolsos" => "required|array",
			"comisiones" => "required|string",
			"tiempo_respuesta_reem_comi" => "required|numeric",
			//"habilita_reembolsos" => "required|boolean",
			"acreedor" => "required|string",
		]);
		if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
				'errors' => $validate->errors()
			);
		} else {
			$usuario = $JwtAuth->checkToken($user_token, true);
			$patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
			$patronRfc = '/[aA0-zZ9]/';
			$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
			$patronFecha = '/^[0-9-]*$/';
			$patronPrecio = '/^[0-9$,.-]*$/';

			$emisor_emp = "";
			$nacionalidad = "";
			$rfc_generico_emi = "";
			$rfc_emp_emi = "";
			$count_rfc_emp_emi = 0;
			$taxid_emp_emi = "";
			$tipo_factura = "";

			$valida_reem = isset($reembolsos) && !empty($reembolsos);
			$valida_comi = isset($comisiones) && !empty($comisiones);
			$valida_acreedor = isset($acreedor) && !empty($acreedor);
      //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1r'.$acreedor]);
			$valida_tiempo_respuesta = isset($tiempo_respuesta_reem_comi) && !empty($tiempo_respuesta_reem_comi) && preg_match($JwtAuth->filtroNumerico(), $tiempo_respuesta_reem_comi);
			//$valida_habilita_reembolsos = isset($habilita_reembolsos) && is_bool($habilita_reembolsos) && $habilita_reembolsos == true;
			//$valida_reem && $valida_comi && $valida_tiempo_respuesta && $valida_habilita_reembolsos && $valida_acreedor
			//return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1r'.$tiempo_respuesta_reem_comi]);

			if ($valida_reem && $valida_comi && $valida_tiempo_respuesta && $valida_acreedor) {
				$queryHabReem = DB::table("fnzs_catalogo_acreedores")->where("token_cat_acreedores",$acreedor)->value("acr_habilita_reembolsos");
        
        if ($queryHabReem) {
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
            AND emp.id = empuser.empresa AND empuser.empleado = pers.id 
            AND pers.id = users.empleado AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);
          foreach ($queryEmp as $vEmp) {
            $acreedor_id = DB::table("fnzs_catalogo_acreedores")->where("token_cat_acreedores",$acreedor)->value("id");
            //da_te_default_timezone_set($vEmp->zona_horaria);
            $tiempo_respuesta = time() + (86400 * ($tiempo_respuesta_reem_comi / 24));
            //echo $tiempo_respuesta_reem_comi/24;
            //echo $tiempo_respuesta." ".date('d-m-Y H:i:s',$tiempo_respuesta);exit;
            $folioSistema = DB::select("SELECT fold.folder+1 AS folio,fold.post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE fold.reembolsos = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                AND empuser.empleado = pers.id AND pers.id = users.empleado AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);
  
            if (count($folioSistema) == 1) {
              if ($folioSistema[0]->folio == 1000000000) {
                $post_folio_db = DB::select("SELECT post_folio_reem FROM reembolso_main WHERE id = (SELECT Max(reem.id) FROM reembolso_main AS reem JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE reem.emisor = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                    AND empuser.usuario = users.id AND users.usuario_token = ?)", [$usuario->empresa_token, $usuario->user_token]);
                $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio_reem);
                $folio_nuevo = 1;
              } else {
                $post_folio = NULL;
                $folio_nuevo = $folioSistema[0]->folio;
              }
            } else {
              $post_folio = NULL;
              $folio_nuevo = 1;
            }
  
            $folio_reem = $post_folio == NULL ? "REEM-" . $JwtAuth->generarFolio($folio_nuevo) : "REEM-" . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
            $selectEmisorPers = DB::table("vhum_empleados_catalogo AS pers")
              ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
              ->where("users.usuario_token", $usuario->user_token)
              ->select('pers.id', 'users.usuario_token')->first();
            $emisor_pers = $selectEmisorPers ? $selectEmisorPers->id : '';
            $emisor_tkn_user = $selectEmisorPers ? $selectEmisorPers->usuario_token : '';
  
            $emisor_emp = DB::table("main_empresas")->where("empresa_token", $usuario->empresa_token)->value("id");
  
            $selectPowerVhum = DB::table("configuracion_systema_vhum AS conf_vhum")
              ->join("main_empresas AS emp", "conf_vhum.empresa", "=", "emp.id")
              ->where("conf_vhum.jerarquia", "P")
              ->where("conf_vhum.reembolsos", TRUE)
              ->where("emp.empresa_token", $usuario->empresa_token)
              ->select('conf_vhum.usuario')->first();
            $receptor_pers = $selectPowerVhum ? $selectPowerVhum->usuario : '';
  
            $token_reembolso_main = $JwtAuth->encriptarToken(rand(5, 15) . $folio_reem . time() . $emisor_emp . $emisor_pers . $usuario->empresa_token, $usuario->user_token);
  
            $comision_identificador = NULL;
            $egresos_valua = FALSE;
            $egresos_aplica = NULL;
            $egresos_user = NULL;
            $egresos_tiempo_respuesta = null;
  
            $valor_humano_valua = FALSE;
            $valor_humano_aplica = NULL;
            $valor_humano_user = NULL;
            $valor_humano_tiempo_respuesta = null;
  
            for ($i = 0; $i < count($comisiones); $i++) {
              $token_comision_main = $comisiones[$i]["token_comision_main"];
              $comisionData = DB::table("terc_comisiones_main")->where(["token_comision_main" => $token_comision_main])->get();
              foreach ($comisionData as $forC) {
                if ($forC->valor_humano == TRUE) {
                  $valor_humano_valua = TRUE;
                  $valor_humano_user = 3;
                  $valor_humano_tiempo_respuesta = $tiempo_respuesta;
                } else {
                  $valor_humano_aplica = "N";
                }
                if ($forC->egresos == TRUE) {
                  $egresos_valua = TRUE;
                  $egresos_user = 4;
                  $egresos_tiempo_respuesta = $tiempo_respuesta;
                } else {
                  $egresos_aplica = "N";
                }
              }
            }
            $newReem = new ReembolsoModelo();
            $newReem->token_reem = $token_reembolso_main;
            $newReem->folio_reem = $folio_nuevo;
            $newReem->post_folio_reem = $post_folio;
            $newReem->fecha_sistema = time();
            $newReem->emisor = $emisor_emp;
            $newReem->receptor = $emisor_emp;
            $newReem->status_reem = TRUE;
            $newReem->fecha_delete = NULL;
            $newReem->user_emisor = $emisor_pers;
            $newReem->user_acreedor = $acreedor_id;
            $newReem->user_receptor_vh = $valor_humano_user;
            $newReem->tiempo_respuesta_auth_vh = $valor_humano_tiempo_respuesta;
            $newReem->user_receptor_egr = $egresos_user;
            $newReem->tiempo_respuesta_auth_egr = $egresos_tiempo_respuesta;
            $insertReem = $newReem->save();
            if ($insertReem) {
              $select_reembolso_main = $newReem->id;
  
              for ($i = 0; $i < count($comisiones); $i++) {
                $token_comision_main = $comisiones[$i]["token_comision_main"];
                $comisionID = DB::table("terc_comisiones_main")->where("token_comision_main", $token_comision_main)->value("id");
  
                $token_rel_comi_reem = $JwtAuth->encriptarToken($token_reembolso_main . $token_comision_main . $comisionID . $select_reembolso_main);
  
                $insert_reem_comi_rel = DB::table('sos_reembolsos_comisiones_rel')->insert(
                  array("token_rel_comi_reem" => $token_rel_comi_reem, "comision" => $comisionID, "reembolso_main" => $select_reembolso_main)
                );
              }
  
              $countReembolsos = 0;
              for ($i = 0; $i < count($reembolsos); $i++) {
                $new_folio_solicitud = $i + 1;
                $new_folio_all_solicitud = $JwtAuth->generarFolio($i + 1);
  
                $reem_fecha = $reembolsos[$i]["reem_fecha"];
                $reem_folio_ticket = $reembolsos[$i]["reem_folio_ticket"];
                $reem_pagado_a = $reembolsos[$i]["reem_pagado_a"];
                $proveedor_tkn = $reembolsos[$i]["proveedor_tkn"];
                $tkn_forma_pago = $reembolsos[$i]["tkn_forma_pago"];
                $reem_importe_total = $reembolsos[$i]["reem_importe_total"];
                $reem_observacion = $reembolsos[$i]["reem_observacion"];
                $reem_moneda_nombre = $reembolsos[$i]["reem_moneda_nombre"];
                $reem_tipo_cambio = $reembolsos[$i]["reem_tipo_cambio_string"];
                $cfdi_comprobante = $reembolsos[$i]['dataCFDI_comprobante'];
                //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true'.count($cfdi_comprobante)]);
                $cfdi_emisor = $reembolsos[$i]['dataCFDIEmisor'];
                $cfdi_receptor = $reembolsos[$i]['dataCFDIReceptor'];
                $cfdi_relacionados = $reembolsos[$i]['dataCFDIRelacionados'];
                $cfdi_conceptos = $reembolsos[$i]['dataCFDI_conceptos'];
                $cfdi_impuestos_retenidos = $reembolsos[$i]['dataCFDI_impuestos_retenidos_lista'];
                $cfdi_impuestos_trasladados = $reembolsos[$i]['dataCFDI_impuestos_trasladados_lista'];
                $cfdi_complemento = $reembolsos[$i]['dataCFDIComplemento'];
  
                $proveedor_id = $proveedor_tkn != "" ? DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $proveedor_tkn)->value("id") : NULL;
  
                // ARCHIVOS
                $archivo_xml = $request->file("reembolsos.$i.factura_xml");
                $archivo_pdf = $request->file("reembolsos.$i.factura_pdf");
                $anexos = $request->file("reembolsos.$i.reembolsos_anexos");
                //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.003']);
  
                $token_reem_soli = $JwtAuth->encriptarToken($select_reembolso_main . $new_folio_all_solicitud . $reem_fecha . $reem_folio_ticket . $reem_pagado_a .
                  $tkn_forma_pago . $reem_importe_total . $reem_observacion);
  
                $insert_reem_soli = DB::table('terc_reembolso_solicitud')->insert(
                  array(
                    "token_solicitud_reem" => $token_reem_soli,
                    "folio_solicitud" => $new_folio_solicitud,
                    "fecha_solicitud" => time(),
                    "reembolso_main" => $select_reembolso_main,
                    "fecha_gasto" => $JwtAuth->convierteFechaEpoc($reem_fecha),
                    "ticket_gasto" => $JwtAuth->encriptar($reem_folio_ticket),
                    "pagado_a" => $reem_pagado_a,
                    "proveedor" => $proveedor_id,
                    "forma_pago" => $tkn_forma_pago,
                    "importe_entrante" => $reem_importe_total,
                    "moneda_entrante" => $reem_moneda_nombre,
                    "tipo_cambio" => $reem_tipo_cambio,
                    "motivo_reem" => $JwtAuth->encriptar($reem_observacion),
                    "autorizacion_vh" => $valor_humano_aplica,
                    "autorizacion_egr" => $egresos_aplica,
                    "tiempo_respuesta_autorizacion" => $tiempo_respuesta,
                    "version" => TRUE,
                    "fecha_delete" => NULL,
                  )
                );
                if ($insert_reem_soli) {
                  $filepath = $vEmp->root_tkn . "/0010-reem/" . $folio_reem . "/" . $JwtAuth->generarFolio($new_folio_solicitud) . "/anexos";
  
                  if (!file_exists(storage_path("/root/" . $filepath))) {
                    Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
                  }
  
                  $reembolso_soli_list = DB::table('terc_reembolso_solicitud')->where("token_solicitud_reem", $token_reem_soli)->value("id");
                  //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.005']);
                  //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true'.count($cfdi_comprobante)]);
                  //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.3'.$cfdi_comprobante]);
                  $data_comprobante = json_decode($cfdi_comprobante, true);
                  if (json_last_error() === JSON_ERROR_NONE && is_array($data_comprobante)) {
                    //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.CFDI1']);
                    $cfdi_comprobante_version = '';
                    $cfdi_comprobante_serie = '';
                    $cfdi_comprobante_folio = '';
                    $cfdi_comprobante_fecha = '';
                    $cfdi_comprobante_forma_de_pago = '';
                    $cfdi_comprobante_metodo_de_pago = '';
                    $cfdi_comprobante_subtotal = '';
                    $cfdi_comprobante_moneda = '';
                    $cfdi_comprobante_tipo_de_cambio = '';
                    $cfdi_comprobante_total = '';
                    $cfdi_comprobante_confirmacion = '';
                    $cfdi_comprobante_tipo_de_comprobante = '';
                    $cfdi_comprobante_lugar_de_expedicion = '';
                    $cfdi_comprobante_no_de_certificado = '';
                    $cfdi_comprobante_sello = '';
                    $cfdi_comprobante_certificado = '';
  
                    foreach ($data_comprobante as $vComp) {
                      switch ($vComp["title"]) {
                        case 'Versión':
                          $cfdi_comprobante_version = $vComp["content"];
                          break;
  
                        case 'Serie':
                          $cfdi_comprobante_serie = $vComp["content"];
                          break;
  
                        case 'Folio':
                          $cfdi_comprobante_folio = $vComp["content"];
                          break;
  
                        case 'Fecha':
                          $cfdi_comprobante_fecha = $vComp["content"];
                          break;
  
                        case 'Forma de pago':
                          $cfdi_comprobante_forma_de_pago = $vComp["content"];
                          break;
  
                        case 'Subtotal':
                          $cfdi_comprobante_subtotal = $vComp["content"];
                          break;
  
                        case 'Moneda':
                          $cfdi_comprobante_moneda = $vComp["content"];
                          break;
  
                        case 'Tipo de cambio':
                          $cfdi_comprobante_tipo_de_cambio = $vComp["content"];
                          break;
  
                        case 'Total':
                          $cfdi_comprobante_total = $vComp["content"];
                          break;
  
                        case 'Confirmación':
                          $cfdi_comprobante_confirmacion = $vComp["content"];
                          break;
  
                        case 'Tipo de comprobante':
                          $cfdi_comprobante_tipo_de_comprobante = $vComp["content"];
                          break;
  
                        case 'Método de Pago':
                          $cfdi_comprobante_metodo_de_pago = $vComp["content"];
                          break;
  
                        case 'Lugar de Expedición':
                          $cfdi_comprobante_lugar_de_expedicion = $vComp["content"];
                          break;
  
                        case 'No de certificado':
                          $cfdi_comprobante_no_de_certificado = $vComp["content"];
                          break;
  
                        case 'Sello':
                          $cfdi_comprobante_sello = $vComp["content"];
                          break;
  
                        case 'Certificado':
                          $cfdi_comprobante_certificado = $vComp["content"];
                          break;
  
                        default:
                          # code...
                          break;
                      }
                    }
  
                    $cfdi_relacionados_tipo_de_relacion = '';
                    $cfdi_relacionados_uuid = '';
                    $data_relacionados = json_decode($cfdi_relacionados, true);
                    foreach ($data_relacionados as $CFDIr) {
                      switch ($CFDIr["title"]) {
                        case 'Tipo de relación':
                          $cfdi_relacionados_tipo_de_relacion = $CFDIr["content"];
                          break;
                        case 'UUID':
                          $cfdi_relacionados_uuid = $CFDIr["content"];
                          break;
  
                        default:
                          //--
                          break;
                      }
                    }
                    //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.CFDI2']);
                    $cfdi_emisor_rfc = '';
                    $cfdi_emisor_nombre = '';
                    $cfdi_emisor_regimen_fiscal = '';
                    $data_emisor = json_decode($cfdi_emisor, true);
                    foreach ($data_emisor as $CFDIe) {
                      switch ($CFDIe["title"]) {
                        case 'Rfc del emisor':
                          $cfdi_emisor_rfc = $CFDIe["content"];
                          break;
                        case 'Nombre del emisor':
                          $cfdi_emisor_nombre = $CFDIe["content"];
                          break;
                        case 'Regimen fiscal del emisor':
                          $cfdi_emisor_regimen_fiscal = $CFDIe["content"];
                          break;
                        default:
                          //--
                          break;
                      }
                    }
  
                    $cfdi_receptor_rfc = '';
                    $cfdi_receptor_uso_del_cfdi = '';
                    $data_receptor = json_decode($cfdi_receptor, true);
                    foreach ($data_receptor as $CFDIReceptor) {
                      switch ($CFDIReceptor["title"]) {
                        case 'Rfc del receptor':
                          $cfdi_receptor_rfc = $CFDIReceptor["content"];
                          break;
                        case 'Uso del CFDI':
                          $cfdi_receptor_uso_del_cfdi = $CFDIReceptor["content"];
                          break;
                        default:
                          //--
                          break;
                      }
                    }
  
                    //foreach ($cfdi_impuestos_retenidos as $vComp) {}
                    //foreach ($cfdi_impuestos_trasladados as $vComp) {}
  
                    $cfdi_complementoUUID = '';
                    $cfdi_complementoFechaTimbrado = '';
                    $cfdi_complementoRfcProvCertif = '';
                    $cfdi_complementoNoCertificadoSAT = '';
                    $cfdi_complementoSelloCFD = '';
                    $cfdi_complementoSelloSAT = '';
                    $data_complemento = json_decode($cfdi_complemento, true);
                    foreach ($data_complemento as $vComplemento) {
                      switch ($vComplemento["title"]) {
                        case 'UUID':
                          $cfdi_complementoUUID = $vComplemento["content"];
                          break;
                        case 'FechaTimbrado':
                          $cfdi_complementoFechaTimbrado = $vComplemento["content"];
                          break;
                        case 'RfcProvCertif':
                          $cfdi_complementoRfcProvCertif = $vComplemento["content"];
                          break;
                        case 'NoCertificadoSAT':
                          $cfdi_complementoNoCertificadoSAT = $vComplemento["content"];
                          break;
                        case 'SelloCFD':
                          $cfdi_complementoSelloCFD = $vComplemento["content"];
                          break;
                        case 'SelloSAT':
                          $cfdi_complementoSelloSAT = $vComplemento["content"];
                          break;
                        default:
                          //--
                          break;
                      }
                    }
  
                    //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$cfdi_comprobante_version]);
                    if ($cfdi_comprobante_version != '') {
                      $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$select_reembolso_main.$reembolso_soli_list.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
                      $insertCFDIEstructura = DB::table('cfdi_comprobantes_fiscales')
                        ->insert(array(
                          "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
                          "origen_proceso" => "reembolso",
                          //"reembolso_vinculado_main" => $select_reembolso_main,
                          //"reembolso_vinculado_soli" => $reembolso_soli_list,
                          "cfdi_comprobante_version" => $cfdi_comprobante_version,
                          "cfdi_comprobante_serie" => $cfdi_comprobante_serie,
                          "cfdi_comprobante_folio" => $cfdi_comprobante_folio,
                          "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
                          "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
                          "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,
                          "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,
                          "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,
                          "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,
                          "cfdi_comprobante_total" => $cfdi_comprobante_total,
                          "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
                          "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
                          "cfdi_comprobante_lugar_de_expedicion" => $cfdi_comprobante_lugar_de_expedicion,
                          "cfdi_comprobante_no_de_certificado" => $cfdi_comprobante_no_de_certificado,
                          "cfdi_comprobante_sello" => $cfdi_comprobante_sello,
                          "cfdi_comprobante_certificado" => $cfdi_comprobante_certificado,
                          "cfdi_relacionados_tipo_de_relacion" => $cfdi_relacionados_tipo_de_relacion,
                          "cfdi_relacionados_uuid" => $cfdi_relacionados_uuid,
                          "cfdi_emisor_rfc" => $cfdi_emisor_rfc,
                          "cfdi_emisor_nombre" => $cfdi_emisor_nombre,
                          "cfdi_emisor_regimen_fiscal" => $cfdi_emisor_regimen_fiscal,
                          "cfdi_receptor_rfc" => $cfdi_receptor_rfc,
                          "cfdi_receptor_uso_del_cfdi" => $cfdi_receptor_uso_del_cfdi,
                          "cfdi_complementoUUID" => $cfdi_complementoUUID,
                          "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,
                          "cfdi_complementoRfcProvCertif" => $cfdi_complementoRfcProvCertif,
                          "cfdi_complementoNoCertificadoSAT" => $cfdi_complementoNoCertificadoSAT,
                          "cfdi_complementoSelloCFD" => $cfdi_complementoSelloCFD,
                          "cfdi_complementoSelloSAT" => $cfdi_complementoSelloSAT,
                        ));
   
                      $comprobante_fiscal_reem = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");
                      $insertCFDIVincReem = DB::table('cfdi_vinculacion_reembolsos')
                      ->insert(array(
                        "comprobante_fiscal" => $comprobante_fiscal_reem,
                        "reembolso_vinculado_main" => $select_reembolso_main,
                        "reembolso_vinculado_soli" => $reembolso_soli_list,
                      ));

                      $data_conceptos = json_decode($cfdi_conceptos, true);
                      for ($i = 0; $i < count($data_conceptos); $i++) {
                        $NoIdentificacion = $data_conceptos[$i]['NoIdentificacion'];
                        $ObjetoImp = $data_conceptos[$i]['ObjetoImp'];
                        $ClaveProdServ = $data_conceptos[$i]['ClaveProdServ'];
                        $cantidad = $data_conceptos[$i]['Cantidad'];
                        $ClaveUnidad = $data_conceptos[$i]['ClaveUnidad'];
                        $Unidad = $data_conceptos[$i]['Unidad'];
                        $concepto = $data_conceptos[$i]['Descripcion'];
                        //return response()->json(['status' => 'error','code' => 200,'message' => $concepto.' reem true5.3 '.$cfdi_comprobante_version]);
                        $precioUnitario = $data_conceptos[$i]['ValorUnitario'];
                        $descuentoXUni = $data_conceptos[$i]['Descuento'];
                        $importe = $data_conceptos[$i]['Importe'];
                        $retenciones = $data_conceptos[$i]['retenciones'];
                        $TotalRetenciones = $data_conceptos[$i]['TotalRetenciones'];
                        $traslados = $data_conceptos[$i]['traslados'];
                        $TotalTraslados = $data_conceptos[$i]['TotalTraslados'];
                        $Subtotal = $data_conceptos[$i]['Subtotal'];
    
                        $uuid_cfdi_detalle = Str::uuid()->toString();
                        $insertDetCFDICompra = DB::table('cfdi_comprobantes_conceptos')
                          ->insert(array(
                            "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                            "comprobante_fiscal" => $comprobante_fiscal_reem,
                            //"reembolso_vinculado_main" => $select_reembolso_main,
                            //"reembolso_vinculado_soli" => $reembolso_soli_list,
                            "NoIdentificacion" => $NoIdentificacion,
                            "ObjetoImp" => $ObjetoImp,
                            "ClaveProdServ" => $ClaveProdServ,
                            "Cantidad" => $cantidad,
                            "ClaveUnidad" => $ClaveUnidad,
                            "Unidad" => $Unidad,
                            "Descripcion" => $concepto,
                            "ValorUnitario" => $precioUnitario,
                            "Descuento" => $descuentoXUni,
                            "Importe" => $importe,
                            "TotalRetenciones" => $TotalRetenciones,
                            "TotalTraslados" => $TotalTraslados,
                            "Subtotal" => $Subtotal,
                            "empresa" => $vEmp->id
                          ));
    
                        if (count($retenciones) != 0) {
                          for ($r = 0; $r < count($retenciones); $r++) {
                            $retencion_traslado = "rete";
                            $Base = $retenciones[$r]["Base"] ? $retenciones[$r]["Base"] : 0.00;
                            $Impuesto = $retenciones[$r]["Impuesto"] ? $retenciones[$r]["Impuesto"] : 000;
                            $TipoFactor = $retenciones[$r]["TipoFactor"] ? $retenciones[$r]["TipoFactor"] : NULL;
                            $TasaOCuota = $retenciones[$r]["TasaOCuota"] ? $retenciones[$r]["TasaOCuota"] : NULL;
                            $importe = $retenciones[$r]["Importe"] ? $retenciones[$r]["Importe"] : 0.00;
                            $impuesto_relacionado = $retenciones[$r]["impuesto_relacionado"];
                            $rete_homologada = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $impuesto_relacionado)->value("id") : NULL;
    
                            $insertDetCFDIImpuestoCompra = DB::table('eegr_compras_cfdi_detalle_impuestos')
                              ->insert(array(
                                "uuid_buydet_impuestos" => Str::uuid()->toString(),
                                "reembolso_vinculado_main" => $select_reembolso_main,
                                "reembolso_vinculado_soli" => $reembolso_soli_list,
                                "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                                "retencion_traslado" => $retencion_traslado,
                                "base" => $Base,
                                "impuesto" => $Impuesto,
                                "tipoFactor" => $TipoFactor,
                                "tasaOCuota" => $TasaOCuota,
                                "importe" => $importe
                              ));
                          }
                        }
    
                        if (count($traslados) != 0) {
                          for ($t = 0; $t < count($traslados); $t++) {
                            $retencion_traslado = "tras";
                            $Base = $traslados[$t]["Base"] ? $traslados[$t]["Base"] : 0.00;
                            $Impuesto = $traslados[$t]["Impuesto"] ? $traslados[$t]["Impuesto"] : 000;
                            $TipoFactor = $traslados[$t]["TipoFactor"] ? $traslados[$t]["TipoFactor"] : NULL;
                            $TasaOCuota = $traslados[$t]["TasaOCuota"] ? $traslados[$t]["TasaOCuota"] : NULL;
                            $importe = $traslados[$t]["Importe"] ? $traslados[$t]["Importe"] : 0.00;
                            $impuesto_relacionado = $traslados[$t]["impuesto_relacionado"];
                            $tras_homologado = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $impuesto_relacionado)->value("id") : NULL;
    
                            $insertDetCFDIImpuestoCompra = DB::table('eegr_compras_cfdi_detalle_impuestos')
                              ->insert(array(
                                "uuid_buydet_impuestos" => Str::uuid()->toString(),
                                "reembolso_vinculado_main" => $select_reembolso_main,
                                "reembolso_vinculado_soli" => $reembolso_soli_list,
                                "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                                "retencion_traslado" => $retencion_traslado,
                                "base" => $Base,
                                "impuesto" => $Impuesto,
                                "tipoFactor" => $TipoFactor,
                                "tasaOCuota" => $TasaOCuota,
                                "importe" => $importe
                              ));
                          }
                        }
                      }
                    }
                    //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.3 '.$cfdi_comprobante_version]);
                  }
                  if ($archivo_xml) {
                    $nombre_original = $archivo_xml->getClientOriginalName(); // ejemplo: "factura123.xml"
                    $ext_doc = $archivo_xml->getClientOriginalExtension(); // ejemplo: "xml"
  
                    $documento_crypt = $JwtAuth->encriptar($nombre_original);
                    $token_documento = $JwtAuth->encriptarToken($reembolso_soli_list, $ext_doc, $nombre_original);
  
                    $insertDocSoli = DB::table("sos_documentos")->insert([
                      "token_documento" => $token_documento,
                      "fecha_carga" => time(),
                      "modulo" => "reembolsos",
                      "folio_modulo" => "REEM-CFDI-XML",
                      "tipo_documento" => "xml",
                      "nombre_documento" => $documento_crypt,
                      "extension_documento" => $ext_doc,
                      "reembolso_main" => $select_reembolso_main,
                      "reembolso_solicitud" => $reembolso_soli_list,
                      "status_documento" => true,
                    ]);
  
                    if ($insertDocSoli) {
                      $archivo_xml->storeAs("public/root/$filepath", $nombre_original);
                    }
  
                    //$pathXml = $archivo_xml->store("reembolsos/xml", 'public');
                    // guardar en la base de datos si quieres
                  }
  
                  if ($archivo_pdf) {
                    $nombre_original = $archivo_pdf->getClientOriginalName(); // ejemplo: "factura123.xml"
                    $ext_doc = $archivo_pdf->getClientOriginalExtension(); // ejemplo: "xml"
  
                    $documento_crypt = $JwtAuth->encriptar($nombre_original);
                    $token_documento = $JwtAuth->encriptarToken($reembolso_soli_list, $ext_doc, $nombre_original);
  
                    $insertDocSoli = DB::table("sos_documentos")->insert([
                      "token_documento" => $token_documento,
                      "fecha_carga" => time(),
                      "modulo" => "reembolsos",
                      "folio_modulo" => "REEM-CFDI-PDF",
                      "tipo_documento" => "pdf",
                      "nombre_documento" => $documento_crypt,
                      "extension_documento" => $ext_doc,
                      "reembolso_main" => $select_reembolso_main,
                      "reembolso_solicitud" => $reembolso_soli_list,
                      "status_documento" => true,
                    ]);
  
                    if ($insertDocSoli) {
                      $archivo_pdf->storeAs("public/root/$filepath", $nombre_original);
                    }
                  }
  
                  if ($anexos && is_array($anexos)) {
                    foreach ($anexos as $anexDoc) {
                      $originalName = $anexDoc->getClientOriginalName();
                      $ext_doc = $anexDoc->getClientOriginalExtension();
                      $documento_crypt = $JwtAuth->encriptar($originalName);
                      $temporal = $anexDoc->getPathname();
                      $token_documento = $JwtAuth->encriptarToken($select_reembolso_main . $reembolso_soli_list . $ext_doc . $originalName);
                      $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%REEM-EVID%'");
                      $insertDocSoli = DB::table("sos_documentos")->insert(
                        array(
                          "token_documento" => $token_documento,
                          "fecha_carga" => time(),
                          "modulo" => "reembolsos",
                          "folio_modulo" => "REEM-EVID" . end($select_folio_doc)->folio,
                          "tipo_documento" => "an",
                          "nombre_documento" => $documento_crypt,
                          "extension_documento" => $ext_doc,
                          "reembolso_main" => $select_reembolso_main,
                          "reembolso_solicitud" => $reembolso_soli_list,
                          "status_documento" => TRUE,
                        )
                      );
  
                      if ($insertDocSoli) {
                        Storage::putFileAs("/public/root/" . $filepath, $temporal, $originalName);
                      }
                    }
                  }
                  //return response()->json(['status' => 'error','code' => 200,'message' => 'reembolso true5.1 ALL']);
  
                  $countReembolsos++;
                }
              }
  
              $select_last_version = DB::select("SELECT MAX(soli.id) AS version_last FROM terc_reembolso_solicitud AS soli JOIN terc_reembolso_main AS reem_main 
                  WHERE soli.reembolso_main = reem_main.id AND reem_main.token_reem = ?", [$token_reembolso_main]);
  
              foreach ($select_last_version as $val_ver) {
                $regFolder = DB::table('terc_reembolso_main AS reem_main')
                  ->where(["token_reem" => $token_reembolso_main])
                  ->limit(1)->update(array("last_version" => $val_ver->version_last));
              }
  
              if ($countReembolsos == count($reembolsos)) {
                if (count($folioSistema) == 0) {
                  $insertSistema = DB::table('sos_last_folders')
                    ->insert(
                      array(
                        "reembolsos" => TRUE,
                        "folder" => 1,
                        "post_folder" => $post_folio,
                        "empresa" => $emisor_emp,
                      )
                    );
                } else {
                  $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                    ->where([
                      'sos_last_folders.reembolsos' => TRUE,
                      'emp.empresa_token' => $usuario->empresa_token,
                      'users.usuario_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                      array(
                        'sos_last_folders.folder' => $folio_nuevo,
                        'sos_last_folders.post_folder' => $post_folio,
                      )
                    );
                }
  
                //$titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem;
                ////$select_reembolso_main = DB::select("SELECT id
                //$JwtAuth->insertNotificacionSistema("Reembolsos", "Registro de reembolso", $titulo_alerta, $select_reembolso_main, NULL, $emisor_emp, $emisor_pers, $emisor_pers);
                //$JwtAuth->notificacionPushDevices($emisor_tkn_user, "SOS-México - Portal para empleados", $titulo_alerta);
  
                //if ($egresos_valua == TRUE) {
                //  //$egresos_user = 6;
                //  $JwtAuth->insertNotificacionSistema("Reembolsos", "Registro de reembolso", $titulo_alerta, $select_reembolso_main, NULL, $emisor_emp, $emisor_pers, $egresos_user);
                //  $selectDTUserEgr = DB::select("SELECT users.usuario_token FROM teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers WHERE users.empleado = pers.id 
                //      AND pers.id = ?", [$egresos_user]);
                //  foreach ($selectDTUserEgr as $vuedt) {
                //    $JwtAuth->notificacionPushDevices($vuedt->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
                //  }
                //}
  
                //if ($valor_humano_valua == TRUE) {
                //  //$valor_humano_user = 7;
                //  $JwtAuth->insertNotificacionSistema("Reembolsos", "Registro de reembolso", $titulo_alerta, $select_reembolso_main, NULL, $emisor_emp, $emisor_pers, $valor_humano_user);
                //  $selectDTUserVHUM = DB::select("SELECT users.usuario_token FROM teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers WHERE users.empleado = pers.id 
                //      AND pers.id = ?", [$valor_humano_user]);
                //  foreach ($selectDTUserVHUM as $vuvhdt) {
                //    $JwtAuth->notificacionPushDevices($vuvhdt->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
                //  }
                //}
  
                $dataMensaje = array(
                  'message' => 'reem_saved',
                  'folio_reem' => $folio_reem,
                  'token_reembolso_main' => $token_reembolso_main,
                  'code' => 200,
                  'status' => 'success'
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'reem_fail_inside'
                );
              }
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'reem_fail_inside');
            }
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Usuario sin acceso a registro de reembolsos');
        }
			} else {
				$mensaje_error = '';
				//if (!$valida_habilita_reembolsos) {$mensaje_error = 'Usuario sin acceso a registro de reembolsos';}
				if (!$valida_reem || !$valida_comi) {$mensaje_error = 'reem_list_fail';}
				if (!$valida_acreedor) {$mensaje_error = 'Usuario no registrado como acreedor';}
				$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
			}
		}

		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function verReembolsoPdfHtml(Request $request){
		$JwtAuth = new \JwtAuth();
		$tokenReem = $request->tokenReem;
		if (!empty($tokenReem) && !empty($tokenReem)) {

			$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
				->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
				->join("teci_catalogo_monedas AS catmon", "emp.e_moneda", "=", "catmon.id")
				->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
				->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
				->where(["reem_main.token_reem" => $tokenReem, "reem_main.status_reem" => TRUE])->get();

			foreach ($reembolso_main_selected as $vremb) {
				$nameEmp = "SOLUCIONES OPORTUNAS SIMPLES";
				$logoEmp = "";
				$importe_total = 0;
				$total_reembolsado = 0;
				$total_restante = 0;

				//da_te_default_timezone_set($vremb->zona_horaria);
				$fecha_solicitud = gmdate('Y-m-d H:i:s', $vremb->fecha_sistema);
				$token_reem = $vremb->token_reem;

				if ($vremb->post_folio_reem == NULL) {
					$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem);
				} else {
					$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem) . '-' . $vremb->post_folio_reem;
				}

				//emisor 
				$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("sos_personas AS people", "emp.persona", "=", "people.id")
					->where(["reem_main.token_reem" => $vremb->token_reem])->get();

				foreach ($selectNameEmpEmi as $vEmisor) {
					$name_emisor = $vEmisor->abrev_nombre;

					$rfc_gen_emi = $vEmisor->rfc_generico;

					if ($vEmisor->rfc != NULL) {
						$rfc_emp_emi = $JwtAuth->desencriptar($vEmisor->rfc);
					} else {
						$rfc_emp_emi = "---";
					}

					if ($vEmisor->tax_id != NULL) {
						$taxid_emp_emi = $JwtAuth->desencriptar($vEmisor->tax_id);
					} else {
						$taxid_emp_emi = "---";
					}

					$logoEmp = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $vEmisor->root_tkn . '/0007-core/' . $JwtAuth->desencriptar($vEmisor->img_perfil)));
				}

				$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
					->where(["reem_main.token_reem" => $vremb->token_reem])->get();

				foreach ($selectPersEmpEmi as $vPemi) {
					$name_pers_emisor = $JwtAuth->desencriptar($vPemi->paterno) .
						" " . $JwtAuth->desencriptar($vPemi->materno) .
						" " . $JwtAuth->desencriptar($vPemi->nombre);
				}

				//receptor                
				$selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
					->join("sos_personas AS people", "emp.persona", "=", "people.id")
					->where(["reem_main.token_reem" => $vremb->token_reem])->get();

				$txt_folio_solicitud = "0";

				foreach ($selectNameEmpRec as $vReceptor) {
					$tkn_receptor = $vReceptor->empresa_token;
					$name_receptor = $vReceptor->abrev_nombre;

					$rfc_gen_receptor = $vReceptor->rfc_generico;

					if ($vReceptor->rfc != NULL) {
						$rfc_emp_receptor = $JwtAuth->desencriptar($vReceptor->rfc);
					} else {
						$rfc_emp_receptor = "---";
					}

					if ($vReceptor->tax_id != NULL) {
						$taxid_emp_receptor = $JwtAuth->desencriptar($vReceptor->tax_id);
					} else {
						$taxid_emp_receptor = "---";
					}
				}

				$name_pers_receptor_vh = "N/A";
				$selectPersEmpReceptorVH = DB::table("terc_reembolso_main AS reem_main")
					->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
					->where(["reem_main.token_reem" => $vremb->token_reem])->get();

				if (count($selectPersEmpReceptorVH) == 1) {
					foreach ($selectPersEmpReceptorVH as $vPrec) {
						$name_pers_receptor_vh = $JwtAuth->desencriptar($vPrec->paterno) .
							" " . $JwtAuth->desencriptar($vPrec->materno) .
							" " . $JwtAuth->desencriptar($vPrec->nombre);
					}
				}

				$selectPersEmpReceptorEGR = DB::table("terc_reembolso_main AS reem_main")
					->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
					->where(["reem_main.token_reem" => $vremb->token_reem])->get();

				foreach ($selectPersEmpReceptorEGR as $vPrec) {
					$name_pers_receptor_egr = $JwtAuth->desencriptar($vPrec->paterno) .
						" " . $JwtAuth->desencriptar($vPrec->materno) .
						" " . $JwtAuth->desencriptar($vPrec->nombre);
				}

				$arraySoliReem = array();
				$soli_reem = DB::table("terc_reembolso_main AS reem_main")
					->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
					->where(["reem_soli.status_activacion" => TRUE, "reem_main.token_reem" => $token_reem])
					->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

				$desglose = '';
				foreach ($soli_reem as $vSoliR) {
					$importe_total = $importe_total + $vSoliR->importe_entrante;

					//proveedor
					$tkn_prov = "";
					$name_prov = "";
					$rfc_generico_prov = "";
					$rfc_prov = "";
					$taxid_prov = "";
					if ($vSoliR->proveedor != NULL) {
						$soli_r_prov = DB::table("terc_reembolso_solicitud AS reem_soli")
							->join("terc_reembolso_main AS rmain", "reem_soli.reembolso_main", "=", "rmain.id")
							->join("eegr_catalogo_proveedores AS cprov", "reem_soli.proveedor", "=", "cprov.id")
							->join("sos_personas AS prov", "cprov.proveedor", "=", "prov.id")
							->where([
								"reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem,
								"rmain.token_reem" => $token_reem
							])->get();

						foreach ($soli_r_prov as $sr_prov) {
							$tkn_prov = $sr_prov->token_cat_proveedores;
							$name_prov = $JwtAuth->desencriptar($sr_prov->nombre_extendido);

							$rfc_generico_prov = $sr_prov->rfc_generico;

							if ($sr_prov->rfc != NULL) {
								$rfc_prov = $JwtAuth->desencriptar($sr_prov->rfc);
							} else {
								$rfc_prov = "---";
							}

							if ($sr_prov->tax_id != NULL) {
								$taxid_prov = $JwtAuth->desencriptar($sr_prov->tax_id);
							} else {
								$taxid_prov = "---";
							}
						}
					}

					if ($vSoliR->pagado_a == "pubgeneral") {
						$pagado_a = "público general";
					} else {
						$pagado_a = "proveedor (" . $rfc_prov . " " . $name_prov . ")";
					}

					$requerido_importe = number_format($vSoliR->importe_entrante, $vremb->decimales, '.', ',');

					$select_folio_auth_vh = DB::select(
						"SELECT r_auth.id FROM terc_reembolso_autorizacion_vh AS r_auth 
                        JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                        WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                        AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",
						[$token_reem, $vSoliR->token_solicitud_reem]
					);

					if (count($select_folio_auth_vh) == 0) {
						$max_auth_vh = false;
						$time_registro_auth_vh = "";
						$comments_auth_vh = "";
					} else {
						$select_max_auth_vh = DB::select(
							"SELECT fecha_registro,autorizacion_vh,comentarios 
                            FROM terc_reembolso_autorizacion_vh WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_vh AS r_auth 
                            JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                            AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",
							[$token_reem, $vSoliR->token_solicitud_reem]
						);
						if ($select_max_auth_vh[0]->autorizacion_vh == TRUE) {
							$max_auth_vh = true;
						} else {
							$max_auth_vh = false;
						}
						$time_registro_auth_vh = date('d-m-Y - H:i:s', $select_max_auth_vh[0]->fecha_registro);
						$comments_auth_vh = $JwtAuth->desencriptar($select_max_auth_vh[0]->comentarios);
					}

					if ($vSoliR->autorizacion_vh == TRUE) {
						$autorizacion_vh = "si (" . $time_registro_auth_vh . ")";
					} else {
						$autorizacion_vh = "no";
					}

					$select_folio_auth_egr = DB::select(
						"SELECT r_auth.id FROM terc_reembolso_autorizacion_egr AS r_auth 
                        JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                        WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                        AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",
						[$token_reem, $vSoliR->token_solicitud_reem]
					);

					if (count($select_folio_auth_egr) == 0) {
						$max_auth_egr = false;
						$time_registro_auth_egr = "";
						$comments_auth_egr = "";
					} else {
						$select_max_auth_egr = DB::select(
							"SELECT fecha_registro,autorizacion_egr,comentarios 
                            FROM terc_reembolso_autorizacion_egr WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_egr AS r_auth 
                            JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ?
                            AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",
							[$token_reem, $vSoliR->token_solicitud_reem]
						);
						if ($select_max_auth_egr[0]->autorizacion_egr == TRUE) {
							$max_auth_egr = true;
						} else {
							$max_auth_egr = false;
						}

						$time_registro_auth_egr = date('d-m-Y - H:i:s', $select_max_auth_egr[0]->fecha_registro);
						$comments_auth_egr = $JwtAuth->desencriptar($select_max_auth_egr[0]->comentarios);
					}

					if ($vSoliR->autorizacion_egr == TRUE) {
						$autorizacion_egr = "si (" . $time_registro_auth_egr . ")";
					} else {
						$autorizacion_egr = "no";
					}

					$thml_docs_asoc = "";
					$selectAnexosReem = DB::table("sos_documentos AS docs")
						->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
						->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
						->where([
							"docs.tipo_documento" => "an",
							"main.token_reem" => $token_reem,
							"reem_soli.token_solicitud_reem" => $vSoliR->token_solicitud_reem,
						])->get();

					if (count($selectAnexosReem) > 0) {
						foreach ($selectAnexosReem as $vDoc) {
							$thml_docs_asoc = $thml_docs_asoc . "<tr><td>" . $JwtAuth->desencriptar($vDoc->nombre_documento) . "</td></tr>";
						}
					} else {
						$thml_docs_asoc = $thml_docs_asoc . "<tr><td>!NO HAY REGISTROS¡</td></tr>";
					}

					$html_pagos_list = '';
					$arrayPagosRegistrados = array();
					$num_lista_pagos = 1;
					$listaPagos = DB::select("SELECT payment.token_pagos,payment.folio_pagos,payment.fecha_sistema,payment.fecha_pago,
                        payment.cuenta_bancaria,payment.cuenta_monedero,payment.caja,payment.monto_pago,payment.tipo_cambio,
                        payment.forma_pago,payment.metodo_pago,payment.p_moneda,payment.concepto,payment.almacen,payment.personal_pago,
                        payment.personal_autoriza,payment.empresa,payment.status_pagos,payment.fecha_deletePagos,payment.pago_autorizado,
                        ordenp.fecha_sistema_ordenp,ordenp.folio_ordenPago FROM fnzs_pagos_pago AS payment JOIN fnzs_pagos_orden AS ordenp 
                        JOIN terc_reembolso_main AS reem_main JOIN terc_reembolso_solicitud AS reem_soli WHERE payment.orden_pago = ordenp.id 
                        AND ordenp.reembolso_main = reem_main.id AND reem_main.token_reem = ? AND ordenp.reembolso_solicitud = reem_soli.id
                        AND reem_soli.token_solicitud_reem = ?", [$token_reem, $vSoliR->token_solicitud_reem]);

					if (count($listaPagos) > 0) {
						$total_pagado = 0;
						foreach ($listaPagos as $resListaPagos) {
							$total_reembolsado = $total_reembolsado + $resListaPagos->monto_pago;
							$total_pagado = $total_pagado + $resListaPagos->monto_pago;

							$forma_pago_text = "-";
							if ($resListaPagos->forma_pago != NULL) {
								$pagosformaPago = DB::select("SELECT token_formapago,clave,forma FROM teci_forma_pago WHERE id = ?", [$resListaPagos->forma_pago]);
								$forma_pago_text = $pagosformaPago[0]->clave . ' - ' . $pagosformaPago[0]->forma;
							}

							$metodo_pago_text = "-";
							if ($resListaPagos->metodo_pago != NULL) {
								$pagosmetodoPago = DB::select("SELECT token_metodopago,abrev,metodo FROM teci_metodo_pago WHERE id = ?", [$resListaPagos->metodo_pago]);
								$metodo_pago_text = $pagosmetodoPago[0]->abrev . ' - ' . $pagosmetodoPago[0]->metodo;
							}
							$pagosmoneda = DB::select("SELECT token_monedas,codigo,moneda FROM teci_catalogo_monedas WHERE id = ?", [$resListaPagos->p_moneda]);

							$medio_de_pago = "";
							$name_caja = "---";
							$name_cuenta_banc = "---";
							$name_cuenta_mone = "---";

							if ($resListaPagos->caja != NULL) {
								$medio_de_pago = "caja";
								$cajaPago = DB::table("fnzs_catalogos_caja")->where(["id" => $resListaPagos->caja])->get();
								foreach ($cajaPago as $resultCaja) {
									$name_caja = $JwtAuth->generar($resultCaja->no_caja) . " (" . $JwtAuth->desencriptar($resultCaja->alias_caja) . ")";
								}
							}

							if ($resListaPagos->cuenta_bancaria != NULL) {
								$medio_de_pago = "cuenta bancaria";
								$tknCuenta = DB::select("SELECT token_cuenta FROM fnzs_catalogos_cuentas WHERE id = ?", [$resListaPagos->cuenta_bancaria]);

								$respCuenta = DB::table("fnzs_catalogos_cuentas AS account")
									->join("teci_bancos AS bank", "account.banco", "bank.id")
									->where(["account.id" => $resListaPagos->cuenta_bancaria])->get();

								if (count($respCuenta) != 0) {
									foreach ($respCuenta as $resCuentas) {
										$name_cuenta_banc = $JwtAuth->generar($resCuentas->folio_cuenta) . " (" . $resCuentas->clave . " - " . $resCuentas->nombre_comercial . ")";
									}
								}
							}

							if ($resListaPagos->cuenta_monedero != NULL) {
								$medio_de_pago = "cuenta de monedero electrónico";
								$arrayOpcionAdicionalMon = array();
								$idCuentaMonedero = DB::select("SELECT token_cuentamonedero FROM fnzs_catalogos_cuentas_monedero WHERE id = ?", [$resListaPagos->cuenta_monedero]);

								$respMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS accMon")
									->join("teci_plataformas_digitales AS pdig", "accMon.monedero", "pdig.id")
									->where(["accMon.id" => $resListaPagos->cuenta_monedero])->get();

								foreach ($respMonedero as $resMonedero) {
									$name_cuenta_mone = $JwtAuth->generar($resMonedero->folio_cuentmon) . " (" . $resMonedero->nombre . ")";
								}
							}

							$html_pagos_list = $html_pagos_list . '<tr>
                            <td>' . $num_lista_pagos . '</td>
                            <td>' . $medio_de_pago . '</td>
                            <td>' . $name_caja . '</td>
                            <td>' . $name_cuenta_banc . '</td>
                            <td>' . $name_cuenta_mone . '</td>
                            <td>' . $forma_pago_text . '</td>
                            <td>' . $metodo_pago_text . '</td>
                            <td>' . $pagosmoneda[0]->codigo . ' - ' . $pagosmoneda[0]->moneda . '</td>
                            <td>$' . number_format($resListaPagos->tipo_cambio, $vremb->decimales, '.', ',') . '</td>
                            <td>$' . number_format($resListaPagos->monto_pago, $vremb->decimales, '.', ',') . '</td>
                            </tr>';

							++$num_lista_pagos;
						}
						$html_pagos_list = $html_pagos_list . '<tr><td colspan="8"></td><td>Total:</td><td>$' . number_format($total_pagado, $vremb->decimales, '.', ',') . '</td></tr>';
					} else {
						$html_pagos_list = $html_pagos_list . '<tr><td colspan="10">!NO HAY REGISTROS¡</td></tr>';
					}

					$desglose = $desglose . '<div class="card">
                        <h4>' . $JwtAuth->generarFolio($vSoliR->folio_solicitud) . '</h4>
                        <table>
   							<thead>
						    	<tr>
						    		<th>fecha de solicitud</th>
						    		<th>Fecha de gasto</th>
						    		<th>Ticket de comprobación de gasto</th>
						    		<th>Pagado a:</th>
						    	</tr>
						    </thead>
							<tbody>
								<tr>
								    <td>' . gmdate('Y-m-d H:i:s', $vSoliR->fecha_solicitud) . '</td>
								    <td>' . gmdate('Y-m-d H:i:s', $vSoliR->fecha_gasto) . '</td>
								    <td>' . $JwtAuth->desencriptar($vSoliR->ticket_gasto) . '</td>
								    <td>' . $pagado_a . '</td>
								</tr>
							</tbody>
                        </table>
                        <table>
   							<thead>
						    	<tr>
						    		<th>forma de pago</th>
						    		<th>Reembolso total</th>
						    		<th>observaciones / comentarios</th>
						    	</tr>
						    </thead>
							<tbody>
								<tr>
								    <td>' . $vSoliR->forma_pago . ' ' . $JwtAuth->getFormasPagoAPI($vSoliR->forma_pago) . '</td>
								    <td>$' . $requerido_importe . '</td>
								    <td>' . $JwtAuth->desencriptar($vSoliR->motivo_reem) . '</td>
								</tr>
							</tbody>
                        </table>
                        <h4>autorizado por</h4>
                        <table>
   							<thead>
						    	<tr>
						    		<th>valor humano</th>
						    		<th>Egresos</th>
						    	</tr>
						    </thead>
							<tbody>
								<tr>
								    <td>' . $autorizacion_vh . '</td>
								    <td>' . $autorizacion_egr . '</td>
								</tr>
							</tbody>
                        </table>
                        <h4>DOCUMENTOS ASOCIADOS</h4>
                        <table>
   							<thead>
						    	<tr>
						    		<th>archivo</th>
						    	</tr>
						    </thead>
							<tbody>' . $thml_docs_asoc . '</tbody>
                        </table>
                        <h4>PAGOS REALIZADOS</h4>
                        <table>
   							<thead>
						    	<tr>
                                    <th class="ultimo"></th>
                                    <th>medio de pago</th>
                                    <th>caja (folio)</th>
                                    <th>cuenta (folio)</th>
                                    <th>monedero (folio)</th>
                                    <th>forma de pago</th>
                                    <th>metodo de pago</th>
                                    <th>moneda</th>
                                    <th>tipo de cambio</th>
                                    <th>pago recibido</th>
						    	</tr>
						    </thead>
							<tbody>' . $html_pagos_list . '</tbody>
                        </table>
                    </div>';
				}
				//echo $desglose;
				$total_restante = $importe_total - $total_reembolsado;
				$sql_total_reembolsado = DB::select("SELECT FORMAT(?,2) AS final_format", [$total_reembolsado]);
				$sql_total_restante = DB::select("SELECT FORMAT(?,2) AS final_format", [$total_restante]);
				$sql_total_importe = DB::select("SELECT FORMAT(?,2) AS final_format", [$importe_total]);

				$cargaPDFAuth = '<!doctype html>
                    <html lang="en">
                        <head>
                            <meta charset="UTF-8">
                            <title>Invoice - #123</title>
                            <style type="text/css">
                                @page {margin: 20px 20px;}
                                body{font-family: sans-serif;margin: 0px;}
                                header { 
                                    border-radius: 8px;
                                    position: fixed;
                                    left: 0px;
                                    height: 105px;
                                    top: 0px;
                                    right: 0px;
                                    text-align: center;
                                }
                                header h1{margin: 10px 0;}
                                header h2{margin: 0 0 10px 0;}
                                * {
                                    font-family: Verdana, Arial, sans-serif;
                                }
                                a {color: #fff;text-decoration: none;}
                                
                                table{font-size: x-small;}
                                
                                main table{
                                  width: 100%;
                                  color: #353535;
                                  margin-top: 5px;
                                  margin-bottom: 1%;
                                  border-radius: 8px;
                                  box-shadow: 2px 2px 10px #353535!important;
                                }

                                main table.transparent_table{
                                  background-color: rgba(53,53,53,0.2)!important;
                                }

                                /*thead*/
                                main table thead tr th{
                                  text-align: center;
                                  font-size: 13px;
                                  height: 30px;
                                  margin: 0;
                                  padding: 0;
                                  background-color: #e7e7ea!important;
                                  color: #353535!important;
                                  text-transform: uppercase;
                                  border-radius: 0px;
                                }
                                
                                main table thead tr th:first-child{
                                  border-radius: 8px 0 0 0;
                                }
                                
                                main table thead tr th:last-child{
                                  border-radius: 0 8px 0 0;
                                }
                                
                                /*tbody*/
                                div.card{
                                    border: 2px solid #D3D3D3;
                                    border-radius: 8px;
                                    padding:5px;
                                    margin-bottom: 5px;
                                }
                                main table tbody tr{
                                  border-bottom: 1px solid #353535;
                                }
                                
                                main table tbody tr:last-child{
                                  border-bottom: none;
                                }
                                
                                main table tbody tr td{
                                  color: #353535;
                                  min-height: 30px!important;
                                  text-align: center;
                                  padding: 0px 5px;
                                  margin-bottom: 0;
                                  font-size: 15px;
                                }
                                
                                main table tbody tr td p{
                                  width: 100%!important;
                                  margin: 0;
                                  text-align: center;
                                }
                                
                                main table tbody tr:last-child td:first-child{
                                  border-radius: 0 0 0 8px;
                                }
                                
                                main table tbody tr:last-child td:last-child{
                                  border-radius: 0 0 8px 0;
                                }
                                
                                main table tfoot tr th,
                                main table tfoot tr td{
                                	color: #e7e7ea;
                                }
                                
                                main table tfoot tr th,
                                main table tfoot tr td{
                                	text-align: center;
                                	padding: 5px!important;
                                }
                                
                                main table tfoot{
                                  display:none;
                                }
                                
                                table.contenido{
                                    color: #353535;
                                }
                                table.contenido thead{
                                    background-color: lightblue;
                                }
                                table.contenido tbody{
                                    background-color: #e7e7ea;
                                }
                                table.contenido thead tr th,
                                table.contenido tbody tr td{
                                    text-align: center;
                                }
                                table.contenido tbody tr td{
                                    text-transform: lowercase;
                                }
                                table.contenido tbody tr:last-child td:first-child{
                                    border-radius: 0 0 0 4px;
                                }
                                table.contenido tbody tr:last-child td:last-child{
                                    border-radius: 0 0 4px 0;
                                }
                                tfoot tr td {
                                    font-weight: bold;
                                    font-size: x-small;
                                }
                                .invoice table {
                                    margin: 15px;
                                }
                                .invoice h3 {
                                    margin-left: 15px;
                                }
                                .information {
                                    color: #FFF;
                                }
                                .information-cpp{
                                    background-color: #353535;
                                }
                                .information .logo {
                                    margin: 5px;
                                }
                                .information table {
                                    padding: 10px;
                                }
                                .divLogo img{
                                    width: 100px;
                                    height: 100px;
                                }
                                .divLogo img.logotipo{
                                    border-radius: 50%;
                                    border: 1px ouset #353535;
                                }
                                main {
                                    position: absolute;
                                    left: 0px;
                                    top: 105px;
                                    right: 0px;
                                }
                                main article h1,
                                main article h2,
                                main article h3,
                                main article h4,
                                main article h5,
                                main article h6 {
                                  width: 100%;
                                  color: #353535;
                                  text-align: center;
                                  margin: 3px;
                                  height: auto;
                                  text-transform: uppercase;
                                  font-family: ubuntu, FontAwesome;
                                  font-family: sans-serif, FontAwesome;
                                }
                                
                                main article table{
                                    border: 1px solid #353535!important;
                                }
                                
                                footer {
                                    position: fixed;
                                    left: 0px;
                                    bottom: 0px;
                                    right: 0px;
                                    border-bottom: 2px solid #ddd;
                                }
                                footer .page:after {
                                    content: counter(page);
                                }
                                footer table {
                                    width: 100%;
                                    font-size: 15px;
                                }
                                footer p {
                                    text-align: right;
                                }
                                footer .izq {
                                    text-align: left;
                                }
                            </style>
                        </head>
                        <body>
                            <header class="information information-cpp">
                                <table width="100%" style="margin:0!important;padding:0!important;">
                                    <tr>
                                        <td colspan="3" style="margin:0!important;padding:0!important;" align="center">
                                            <img src="' . $logoEmp . '" alt="Logo" height="50" class="logo"/>
                                            <h4 style="margin:0!important;padding:0!important;">' . $nameEmp . '</h4>
                                        </td>
                                    </tr>
                                
                                    <tr>
                                        <td align="center" style="width: 20%;">
                                            <h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">Módulo de empleados</h3>
                                        </td>
                                        <td align="center" style="width: 60%;">
                                            <h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">Reporte de reembolsos</h3>
                                        </td>
                                        <td align="center" style="width: 20%;">
                                            <h3 style="margin:0!important;margin-top:5px!important;padding:0!important;">' . date('d M, Y H:i:s', time()) . '</h3>
                                        </td>
                                    </tr>
                                </table>
                            </header>
                            <main>
                                <h3 style="text-align:center;">' . $folio_reem . '</h3>
                                <article style="margin-top:20px;">
                                    <table>
   								    	<thead>
								        	<tr>
								        	    <th>FECHA DE REGISTRO</th>
								        		<th>REEMBOLSADO</th>
								        		<th>RESTANTE</th>
								        		<th>Total</th>
								        	</tr>
								        </thead>
								    	<tbody>
								    		<tr>
								    		    <td>' . $fecha_solicitud . '</td>
								    		    <td>$' . $sql_total_reembolsado[0]->final_format . '</td>
								    		    <td>$' . $sql_total_restante[0]->final_format . '</td>
								    		    <td>$' . $sql_total_importe[0]->final_format . '</td>
								    		</tr>
								    	</tbody>
                                    </table>
                                    
                                    <table>
   								    	<thead>
								        	<tr>
								        		<th>EMISOR</th>
								        		<th>VALOR HUMANO (RECEPTOR)</th>
								        		<th>EGRESOS (RECEPTOR)</th>
								        	</tr>
								        </thead>
								    	<tbody>
								    		<tr>
								    		    <td>' . $name_pers_emisor . ' (' . $name_emisor . ')</td>
								    		    <td>' . $name_pers_receptor_vh . ' (' . $name_receptor . ')</td>
								    		    <td>' . $name_pers_receptor_egr . ' (' . $name_receptor . ')</td>
								    		</tr>
								    	</tbody>
                                    </table>
                                </article>
                                <article style="margin-top:20px;"><h4>Listado de solicitudes</h4>' . $desglose . '</article>
                            </main>
                            <footer style="display:flex;">
                                <table width="100%"><tr>
                                <td align="left" style="width: 50%;">sos-mexico.com.mx</td>
                                <td align="right" style="width: 50%;">página <span class="page"></span></td>
                                </tr></table>
                            </footer>
                        </body>
                    </html>';
				$dompdf = \PDF::loadHtml($cargaPDFAuth);
				$dompdf->setPaper("A2", "portrait");
				//$dompdf->setPaper('A4', 'landscape');
				$dompdf->setPaper('A2', 'landscape');
				$contenidoPDF = $dompdf->stream();
				return $contenidoPDF;
			}

			//$pdfGenerado = $JwtAuth->generaPdf("information-fnz","compras","requisiciones","alta de requisición");
			//$dompdf = \PDF::loadHtml($pdfGenerado);
			//$dompdf->setPaper("A2", "portrait");
			////$contenidoPDF = $dompdf->output();
			////$contenidoPDF = $dompdf->download('cert.pdf');
			//$contenidoPDF = $dompdf->stream();
			//return $contenidoPDF;
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				"message" => 'La información que intenta registrar no es valida'
			);
			return response()->json($dataMensaje, $dataMensaje['code']);
		}
	}
}
