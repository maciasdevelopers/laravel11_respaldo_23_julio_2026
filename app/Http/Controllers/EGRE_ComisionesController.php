<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\CajaModelo;
use App\Models\CuentBancModelo;
use App\Models\CuentaMonederoModelo;
use App\Models\PersonalModelo;

class EGRE_ComisionesController extends Controller{
	//salidas de comision
	public function comisionListaGeneral(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'periodo' => 'required|string',
      'periodo_inicio' => 'nullable|string',
      'periodo_fin' => 'nullable|string',
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
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = date('Y-m-d', strtotime('monday this week'));
          $fechaInicio = strtotime(date($lunes.' 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'este_mes':
          $fechaInicio = strtotime(date('Y-m-01 00:00:00'));
          $fechaFin = strtotime(date('Y-m-t 23:59:59'));
          break;
        case 'mes_anterior':
          $fechaInicio = strtotime("first day of last month 00:00:00");
          $fechaFin = strtotime("last day of last month 23:59:59");
          break;
        case 'otras_fechas':
          $periodo_inicio = $request->input('periodo_inicio');
          $periodo_fin = $request->input('periodo_fin');
          $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
          $fechaFin = strtotime($periodo_fin . " 23:59:59");
          break;
        case 'all_partidas':
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
        default:
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
      }

      $selectComission = DB::table("terc_comisiones_main AS comi")
      ->join("main_empresas AS emp", "comi.empresa", "emp.id")
      ->where([
        "comi.status" => TRUE,
        "emp.empresa_token" => $empresa
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("comi.fecha_comision", [$fechaInicio, $fechaFin]);
      })
      ->orderBy("comi.folio_comision", "DESC")
      ->get();
          
      if ($selectComission->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron comisiones registrados'
        );
      } else {
				foreach ($selectComission as $vComi) {
					$expideComission = DB::table("terc_comisiones_main AS comi")
					->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where("comi.token_comision_main",$vComi->token_comision_main)->get();
					foreach ($expideComission as $vExpide) {
						$user_expide = $JwtAuth->desencriptarNombres($vExpide->paterno,$vExpide->materno,$vExpide->nombre);
					}

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
            $comisionadoNombre = $JwtAuth->desencriptarNombres($comisionadoTrabQuery->paterno,$comisionadoTrabQuery->materno,$comisionadoTrabQuery->nombre);
          } elseif ($comisionadoProvQuery) {
            $comisionadoNombre = $JwtAuth->desencriptar($comisionadoProvQuery->nombre_extendido);
          }

					$sql_recibe_dinero = $vComi->recibe_dinero ? true : false;
					$sql_moneda_tkn = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
					$sql_moneda_name = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
					$sql_dinero_recibido = $vComi->recibe_dinero ? "$".number_format($vComi->dinero_recibido, $JwtAuth->getMonedaAPI($vComi->comision_moneda), '.', ',') : null;
					$sql_dinero_recibido_simple = $vComi->recibe_dinero ? $vComi->dinero_recibido : null;

					$sql_califica_egresos = $vComi->egresos ? true : false;
					$sql_califica_vhum = $vComi->valor_humano ? true : false;
					$bool_reapertura_fecha = !is_null($vComi->reapertura_fecha) ? true : false;

          $sql_concluida_fecha = $vComi->concluida ? $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha) : (!is_null($vComi->reapertura_fecha) ? $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha) . " reabierto (" . $JwtAuth->mostrarUnixAFechaMexico($vComi->reapertura_fecha) . ")" : '');
					
          $comision_relaciones = DB::table("sos_reembolsos_comisiones_rel AS reem_comi")
					->join("terc_comisiones_main AS comi", "reem_comi.comision", "comi.id")
					->join("terc_reembolso_main AS reem_main", "reem_comi.reembolso_main", "reem_main.id")
					->where("comi.token_comision_main",$vComi->token_comision_main)->get();

					$row_comi = array(
						"token_comision_main" => $vComi->token_comision_main,
						"folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
						"fecha_comision" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_comision),
						"comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
						"usuario_expide" => $user_expide,
						"comisionado" => $comisionadoNombre,
						"especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
						"fecha_programada" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_programada),
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
						"comision_relaciones_num" => count($comision_relaciones),
            
            "ubicacion_estado" => !is_null($vComi->ubicacion_estado) ? $JwtAuth->desencriptar($vComi->ubicacion_estado) : '',
            "ubicacion_municipio" => !is_null($vComi->ubicacion_municipio) ? $JwtAuth->desencriptar($vComi->ubicacion_municipio) : '',
            "ubicacion_codigo_postal" => !is_null($vComi->ubicacion_codigo_postal) ? $vComi->ubicacion_codigo_postal : '',
            "ubicacion_colonia" => !is_null($vComi->ubicacion_colonia) ? $JwtAuth->desencriptar($vComi->ubicacion_colonia) : '',

						"bool_reapertura_fecha" => $bool_reapertura_fecha,
            "concluida" => $vComi->concluida ? true : false, 
						"concluida_fecha" => $sql_concluida_fecha,
					);
					$array_comisiones[] = $row_comi;
				}

				$dataMensaje = array("status" => "success", 'code' => 200, "comi_listado" => $array_comisiones);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function comisionListasNoConcluidas(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'periodo' => 'required|string',
      'periodo_inicio' => 'nullable|string',
      'periodo_fin' => 'nullable|string',
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
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = date('Y-m-d', strtotime('monday this week'));
          $fechaInicio = strtotime(date($lunes.' 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'este_mes':
          $fechaInicio = strtotime(date('Y-m-01 00:00:00'));
          $fechaFin = strtotime(date('Y-m-t 23:59:59'));
          break;
        case 'mes_anterior':
          $fechaInicio = strtotime("first day of last month 00:00:00");
          $fechaFin = strtotime("last day of last month 23:59:59");
          break;
        case 'otras_fechas':
          $periodo_inicio = $request->input('periodo_inicio');
          $periodo_fin = $request->input('periodo_fin');
          $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
          $fechaFin = strtotime($periodo_fin . " 23:59:59");
          break;
        case 'all_partidas':
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
        default:
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
      }

			$selectComission = DB::table("terc_comisiones_main AS comi")
			->join("main_empresas AS emp", "comi.empresa", "emp.id")
			->where([
        "comi.status" => TRUE,
			  "comi.concluida" => FALSE,
			  "emp.empresa_token" => $empresa
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("comi.fecha_comision", [$fechaInicio, $fechaFin]);
      })
			->orderBy("comi.folio_comision", "DESC")->get();
  
      if ($selectComission->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
				foreach ($selectComission as $vComi) {
					$expideComission = DB::table("terc_comisiones_main AS comi")
					->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where("comi.token_comision_main",$vComi->token_comision_main)->get();
					foreach ($expideComission as $vExpide) {
						$user_expide = $JwtAuth->desencriptarNombres($vExpide->paterno,$vExpide->materno,$vExpide->nombre);
					}

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
            $comisionadoNombre = $JwtAuth->desencriptarNombres($comisionadoTrabQuery->paterno,$comisionadoTrabQuery->materno,$comisionadoTrabQuery->nombre);
          } elseif ($comisionadoProvQuery) {
            $comisionadoNombre = $JwtAuth->desencriptar($comisionadoProvQuery->nombre_extendido);
          }

					$sql_recibe_dinero = $vComi->recibe_dinero ? true : false;
					$sql_moneda_tkn = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
					$sql_moneda_name = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
					$sql_dinero_recibido = $vComi->recibe_dinero ? "$".number_format($vComi->dinero_recibido, $JwtAuth->getMonedaAPI($vComi->comision_moneda), '.', ',') : null;
					$sql_dinero_recibido_simple = $vComi->recibe_dinero ? $vComi->dinero_recibido : null;

					$sql_califica_egresos = $vComi->egresos ? true : false;
					$sql_califica_vhum = $vComi->valor_humano ? true : false;
					$bool_reapertura_fecha = !is_null($vComi->reapertura_fecha) ? true : false;

          $sql_concluida_fecha = $vComi->concluida ? $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha) : (!is_null($vComi->reapertura_fecha) ? $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha) . " reabierto (" . $JwtAuth->mostrarUnixAFechaMexico($vComi->reapertura_fecha) . ")" : '');
					
          $comision_relaciones = DB::table("sos_reembolsos_comisiones_rel AS reem_comi")
					->join("terc_comisiones_main AS comi", "reem_comi.comision", "comi.id")
					->join("terc_reembolso_main AS reem_main", "reem_comi.reembolso_main", "reem_main.id")
					->where("comi.token_comision_main",$vComi->token_comision_main)->get();

					$row_comi = array(
						"token_comision_main" => $vComi->token_comision_main,
						"folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
						"fecha_comision" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_comision),
						"comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
						//"usuario_expide" => $user_expide,
						"comisionado" => $comisionadoNombre,
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
						"ubicacion_latitud" => !is_null($vComi->ubicacion_latitud) ? $vComi->ubicacion_latitud : '',
						"ubicacion_longitud" => !is_null($vComi->ubicacion_longitud) ? $vComi->ubicacion_longitud : '',
						"ubicacion_display_name" => !is_null($vComi->ubicacion_display_name) ? $JwtAuth->desencriptar($vComi->ubicacion_display_name) : '',
						"comision_relaciones_num" => count($comision_relaciones),
            
            "ubicacion_estado" => !is_null($vComi->ubicacion_estado) ? $JwtAuth->desencriptar($vComi->ubicacion_estado) : '',
            "ubicacion_municipio" => !is_null($vComi->ubicacion_municipio) ? $JwtAuth->desencriptar($vComi->ubicacion_municipio) : '',
            "ubicacion_codigo_postal" => !is_null($vComi->ubicacion_codigo_postal) ? $vComi->ubicacion_codigo_postal : '',
            "ubicacion_colonia" => !is_null($vComi->ubicacion_colonia) ? $JwtAuth->desencriptar($vComi->ubicacion_colonia) : '',

						"bool_reapertura_fecha" => $bool_reapertura_fecha,
            "concluida" => $vComi->concluida ? true : false, 
						"concluida_fecha" => $sql_concluida_fecha,
					);
					$array_comisiones[] = $row_comi;
				}

				$dataMensaje = array("status" => "success", 'code' => 200, "comi_listado" => $array_comisiones);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function comisionListasConcluidas(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'periodo' => 'required|string',
      'periodo_inicio' => 'nullable|string',
      'periodo_fin' => 'nullable|string',
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
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');

      switch ($periodo) {
        case 'hoy':
          $fechaInicio = strtotime(date('Y-m-d 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'esta_semana':
          $lunes = date('Y-m-d', strtotime('monday this week'));
          $fechaInicio = strtotime(date($lunes.' 00:00:00'));
          $fechaFin = strtotime(date('Y-m-d 23:59:59'));
          break;
        case 'este_mes':
          $fechaInicio = strtotime(date('Y-m-01 00:00:00'));
          $fechaFin = strtotime(date('Y-m-t 23:59:59'));
          break;
        case 'mes_anterior':
          $fechaInicio = strtotime("first day of last month 00:00:00");
          $fechaFin = strtotime("last day of last month 23:59:59");
          break;
        case 'otras_fechas':
          $periodo_inicio = $request->input('periodo_inicio');
          $periodo_fin = $request->input('periodo_fin');
          $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
          $fechaFin = strtotime($periodo_fin . " 23:59:59");
          break;
        case 'all_partidas':
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
        default:
          $fechaInicio = NULL;
          $fechaFin = NULL;
          break;
      }
      
      $selectComission = DB::table("terc_comisiones_main AS comi")
      ->join("main_empresas AS emp", "comi.empresa", "emp.id")
      ->where([
        "comi.status" => TRUE,
        "comi.concluida" => TRUE,
        "emp.empresa_token" => $empresa
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("comi.fecha_comision", [$fechaInicio, $fechaFin]);
      })
      ->orderBy("comi.folio_comision", "DESC")->get();

      if ($selectComission->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
				foreach ($selectComission as $vComi) {
					$expideComission = DB::table("terc_comisiones_main AS comi")
					->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where("comi.token_comision_main",$vComi->token_comision_main)->get();
					foreach ($expideComission as $vExpide) {
						$user_expide = $JwtAuth->desencriptarNombres($vExpide->paterno,$vExpide->materno,$vExpide->nombre);
					}

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
            $comisionadoNombre = $JwtAuth->desencriptarNombres($comisionadoTrabQuery->paterno,$comisionadoTrabQuery->materno,$comisionadoTrabQuery->nombre);
          } elseif ($comisionadoProvQuery) {
            $comisionadoNombre = $JwtAuth->desencriptar($comisionadoProvQuery->nombre_extendido);
          }

					$sql_recibe_dinero = $vComi->recibe_dinero ? true : false;
					$sql_moneda_tkn = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
					$sql_moneda_name = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
					$sql_dinero_recibido = $vComi->recibe_dinero ? "$".number_format($vComi->dinero_recibido, $JwtAuth->getMonedaAPI($vComi->comision_moneda), '.', ',') : null;
					$sql_dinero_recibido_simple = $vComi->recibe_dinero ? $vComi->dinero_recibido : null;

					$sql_califica_egresos = $vComi->egresos ? true : false;
					$sql_califica_vhum = $vComi->valor_humano ? true : false;
					$bool_reapertura_fecha = !is_null($vComi->reapertura_fecha) ? true : false;

          $sql_concluida_fecha = $vComi->concluida ? $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha) : (!is_null($vComi->reapertura_fecha) ? $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha) . " reabierto (" . $JwtAuth->mostrarUnixAFechaMexico($vComi->reapertura_fecha) . ")" : '');
					
          $comision_relaciones = DB::table("sos_reembolsos_comisiones_rel AS reem_comi")
					->join("terc_comisiones_main AS comi", "reem_comi.comision", "comi.id")
					->join("terc_reembolso_main AS reem_main", "reem_comi.reembolso_main", "reem_main.id")
					->where("comi.token_comision_main",$vComi->token_comision_main)->get();

					$selectMaxSoli = DB::table("terc_comisiones_main AS comi")
					->join("terc_comisiones_soli_auth AS soli_auth", "comi.id", "=", "soli_auth.comision")
					->where("soli_auth.soli_aprobada",FALSE)
					->where("comi.token_comision_main",$vComi->token_comision_main)
					->orderBy("soli_auth.id", "DESC")->get();

					$enable_send_apert_soli = $vComi->concluida_fecha + 2629743 > time() && count($selectMaxSoli) == 0 ? true : false;

					$row_comi = array(
						"token_comision_main" => $vComi->token_comision_main,
						"folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
						"fecha_comision" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_comision),
						"comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
						"usuario_expide" => $user_expide,
						"comisionado" => $comisionadoNombre,
						"especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
						"fecha_programada" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_programada),
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
						"comision_relaciones_num" => count($comision_relaciones),
            
            "ubicacion_estado" => !is_null($vComi->ubicacion_estado) ? $JwtAuth->desencriptar($vComi->ubicacion_estado) : '',
            "ubicacion_municipio" => !is_null($vComi->ubicacion_municipio) ? $JwtAuth->desencriptar($vComi->ubicacion_municipio) : '',
            "ubicacion_codigo_postal" => !is_null($vComi->ubicacion_codigo_postal) ? $vComi->ubicacion_codigo_postal : '',
            "ubicacion_colonia" => !is_null($vComi->ubicacion_colonia) ? $JwtAuth->desencriptar($vComi->ubicacion_colonia) : '',

						"bool_reapertura_fecha" => $bool_reapertura_fecha,
            "concluida" => $vComi->concluida ? true : false, 
						"concluida_fecha" => $sql_concluida_fecha,
						"enable_send_apert_soli" => $enable_send_apert_soli,
					);
					$array_comisiones[] = $row_comi;
				}

				$dataMensaje = array("status" => "success", 'code' => 200, "comi_listado" => $array_comisiones);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function comisionDeshabilitadas(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    //da_te_default_timezone_set('America/Mexico_City');
    
    $selectComission = DB::table("terc_comisiones_main AS comi")
    ->join("main_empresas AS emp", "comi.empresa", "emp.id")
    ->where([
      'comi.status' => FALSE,
      'emp.empresa_token' => $empresa
    ])
    ->orderBy("comi.folio_comision", "DESC")->get();

    if ($selectComission->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron comisiones registradas'
      );
    } else {
      $array_comisiones = array();
      foreach ($selectComission as $vComi) {
        $expideComission = DB::table("terc_comisiones_main AS comi")
        ->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where("comi.token_comision_main",$vComi->token_comision_main)->get();
        foreach ($expideComission as $vExpide) {
          $user_expide = $JwtAuth->desencriptarNombres($vExpide->paterno,$vExpide->materno,$vExpide->nombre);
        }

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
          $comisionadoNombre = $JwtAuth->desencriptarNombres($comisionadoTrabQuery->paterno,$comisionadoTrabQuery->materno,$comisionadoTrabQuery->nombre);
        } elseif ($comisionadoProvQuery) {
          $comisionadoNombre = $JwtAuth->desencriptar($comisionadoProvQuery->nombre_extendido);
        }

        $sql_recibe_dinero = $vComi->recibe_dinero ? true : false;
        $sql_moneda_tkn = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
        $sql_moneda_name = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
        $sql_dinero_recibido = $vComi->recibe_dinero ? "$".number_format($vComi->dinero_recibido, $JwtAuth->getMonedaAPI($vComi->comision_moneda), '.', ',') : null;
        $sql_dinero_recibido_simple = $vComi->recibe_dinero ? $vComi->dinero_recibido : null;

        $sql_califica_egresos = $vComi->egresos ? true : false;
        $sql_califica_vhum = $vComi->valor_humano ? true : false;
        $bool_reapertura_fecha = !is_null($vComi->reapertura_fecha) ? true : false;

        $sql_concluida_fecha = $vComi->concluida ? $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha) : (!is_null($vComi->reapertura_fecha) ? $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha) . " reabierto (" . $JwtAuth->mostrarUnixAFechaMexico($vComi->reapertura_fecha) . ")" : '');
        
        $comision_relaciones = DB::table("sos_reembolsos_comisiones_rel AS reem_comi")
        ->join("terc_comisiones_main AS comi", "reem_comi.comision", "comi.id")
        ->join("terc_reembolso_main AS reem_main", "reem_comi.reembolso_main", "reem_main.id")
        ->where("comi.token_comision_main",$vComi->token_comision_main)->get();

        $row_comi = array(
          "token_comision_main" => $vComi->token_comision_main,
          "folio_comision" => "COMI-".$JwtAuth->generarFolio($vComi->folio_comision),
          "fecha_comision" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_comision),
          "comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
          "usuario_expide" => $user_expide,
          "comisionado" => $comisionadoNombre,
          "especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
          "fecha_programada" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_programada),
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
          "comision_relaciones_num" => count($comision_relaciones),
          
          "ubicacion_estado" => !is_null($vComi->ubicacion_estado) ? $JwtAuth->desencriptar($vComi->ubicacion_estado) : '',
          "ubicacion_municipio" => !is_null($vComi->ubicacion_municipio) ? $JwtAuth->desencriptar($vComi->ubicacion_municipio) : '',
          "ubicacion_codigo_postal" => !is_null($vComi->ubicacion_codigo_postal) ? $vComi->ubicacion_codigo_postal : '',
          "ubicacion_colonia" => !is_null($vComi->ubicacion_colonia) ? $JwtAuth->desencriptar($vComi->ubicacion_colonia) : '',

          "bool_reapertura_fecha" => $bool_reapertura_fecha,
          "concluida" => $vComi->concluida ? true : false, 
          "concluida_fecha" => $sql_concluida_fecha,
          "fecha_delete_comission" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_delete_comission),
        );
        $array_comisiones[] = $row_comi;
      }

      $dataMensaje = array("status" => "success", 'code' => 200, "comi_listado" => $array_comisiones);
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function comisionTerminar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_comision' => 'required|string',
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
      //da_te_default_timezone_set('America/Mexico_City');
      $token_comision = $request->input('token_comision');
      
      $selectComission = DB::table("terc_comisiones_main AS comi_main")
      ->join("main_empresas AS emp", "comi_main.empresa", "emp.id")
      ->where([
        "comi_main.token_comision_main" => $token_comision,
        "comi_main.status" => TRUE,
        "emp.empresa_token" => $empresa
      ])
      ->get();      

      if ($selectComission->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Comisión no registrada, intente nuevamente o comuniquese a soporte'
        );
      } else {
        foreach ($selectComission as $vComi) {
          $folio_comision = "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision);

          $finish_comi = DB::table("terc_comisiones_main")->where(["token_comision_main" => $vComi->token_comision_main])
          ->limit(1)->update(array("concluida" => TRUE, "concluida_fecha" => time()));

          if ($finish_comi) {
            $titulo_alerta = "La comisión con folio $folio_comision que te fue asignada, ha sido terminada";
            
            $trabComissionadoQuery = DB::table("terc_comisiones_main AS comi_main")
            ->join("vhum_empleados_catalogo AS p_comi", "comi_main.usuario_comision", "p_comi.id")
            ->join("teci_usuarios_catalogo AS u_comi", "p_comi.id", "u_comi.empleado")
            ->where("comi_main.token_comision_main",$vComi->token_comision_main)
            ->select('u_comi.usuario_token')
            ->first();
            
            if ($trabComissionadoQuery) {
              $JwtAuth->notificacionPushDevices($trabComissionadoQuery->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
            }

            $dataMensaje = array("status" => "success", 'code' => 200, "message" => "Comisión con folio $folio_comision fue terminada satisfactoriamente");
          } else {
            $dataMensaje = array('status' => 'error', 'code' => 200, "message" => "Error en eliminación de comisión");
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}
  
  public function comisionSolicitarApertura(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_comision' => 'required|string'
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
      $token_comision = $request->input('token_comision');
      $observaciones = "permiso de prueba";
  
      $selectComission = DB::table("terc_comisiones_main AS comi")
      ->join("main_empresas AS emp","comi.empresa","emp.id")
      ->where([
        "comi.status" => TRUE,
        "comi.token_comision_main" => $token_comision,
        "emp.empresa_token" => $empresa
      ])
      ->orderBy("comi.folio_comision","DESC")->get();

      if ($selectComission->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Comisión no registrada, intente nuevamente o comuniquese a soporte'
        );
      } else {
        foreach ($selectComission as $vComi) {
          //da_te_default_timezone_set($vComi->zona_horaria);
          $folio_comision = "COMI-".$JwtAuth->generarFolio($vComi->folio_comision);
          $select_id_comi = DB::select("SELECT id FROM terc_comisiones_main WHERE token_comision_main = ?",[$vComi->token_comision_main]);
              
          $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp WHERE people.id = emp.persona AND emp.empresa_token = ?",[$empresa]); 
                          
          $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
            WHERE people.id = pers.empleado_name AND pers.id = users.empleado AND users.usuario_token = ?",[$usuario]);    
  
          $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno,end($select_usuario)->materno,end($select_usuario)->nombre);
          $selectMaxSoli = DB::table("terc_comisiones_soli_auth AS soli_auth")
          ->join("main_empresas AS emp","soli_auth.user_emp","emp.id")
          ->where(["emp.empresa_token" => $empresa])->count();
          
          $token_auth = $JwtAuth->encriptarToken(time(),end($select_empresa)->id.end($select_usuario)->id.$observaciones.time()-500,$token_comision);
          
          $insertSoliPerm = DB::table("terc_comisiones_soli_auth")
          ->insert(
            array(
              "token_comision_soli_auth" => $token_auth,
              "folio_comision_soli_auth" => $selectMaxSoli+1,
              "fecha_comision_soli_auth" => time(),
              "user_emp" => end($select_empresa)->id, 
              "user_user" => end($select_usuario)->id, 
              "comision" => end($select_id_comi)->id, 
              "observaciones" => $JwtAuth->encriptar($observaciones),
              "receptor" => 3,
              "solicitud_comi_status" => TRUE,
            )
          ); 
          
          if ($insertSoliPerm) {
            //$fireReceptor = DB::select("SELECT token_dispositivo_movil,token_dispositivo_web FROM teci_usuarios_catalogo WHERE user_token = ?",[$JwtAuth->userAdminMain()]);
            $titulo_ = "Validación de comisiones";
            $mensaje_user = "El usuario ".$nombre_user." de la empresa ".end($select_empresa)->abrev_nombre." ha solicitado validación para el comisión con el folio ".$folio_comision;
              
            $JwtAuth->notificacionPushDevices($JwtAuth->userAdminMain(),$titulo_,$mensaje_user);
              
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "Solicitud de permiso generada con el folio PERM-".$JwtAuth->generarFolio($selectMaxSoli+1),
            ); 
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
            ); 
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function comisionAperturaReabrir(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_comision' => 'required|string'
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
      $token_comision = $request->input('token_comision');

      $selectComission = DB::table("terc_comisiones_main AS comi")
      ->join("main_empresas AS emp","comi.empresa","emp.id")
      ->where([
        "comi.status" => TRUE,
        "comi.token_comision_main" => $token_comision,
        "emp.empresa_token" => $empresa
      ])
      ->orderBy("comi.folio_comision","DESC")->get();
  
      if ($selectComission->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Comisión no registrada, intente nuevamente o comuniquese a soporte'
        );
      } else {
        foreach ($selectComission as $vComi) {
          $folio_comision = "COMI-".$JwtAuth->generarFolio($vComi->folio_comision);
          $reabreComi = DB::table("terc_comisiones_main")->where(["token_comision_main" => $vComi->token_comision_main])->limit(1)->update(array("concluida" => FALSE,"reapertura_fecha" => time()));

          if ($reabreComi) {
            $titulo_ = "Validación de proveedor";
            $mensaje_user = "La comisión con folio $folio_comision ha sido reabierta";
            //$soliValidate = DB::table("terc_comisiones_main AS comi")
            //->join("terc_comisiones_soli_auth AS soli_auth","comi.id","=","soli_auth.comision")
            //->join("teci_usuarios_catalogo AS users","soli_auth.user_user","=","users.id")
            //->where(["soli_auth.soli_aprobada" => FALSE,"comi.token_comision_main" => $vComi->token_comision_main])->get();
            //foreach ($soliValidate as $mSoli) {
            //  $soliValidAprob = DB::table("terc_comisiones_soli_auth")
            //  ->where(["token_comision_soli_auth" => $mSoli->token_comision_soli_auth])
            //  ->limit(1)->update(array("soli_aprobada" => TRUE));
            //  $JwtAuth->notificacionPushDevices($mSoli->usuario_token,$titulo_,$mensaje_user);
            //}
            $dataMensaje = array("status" => "success","code" => 200,"message" => $mensaje_user);          
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Reapertura de comisión no registrada, intentelo nuevamente o comuniquese a soporte",
            ); 
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

	public function comisionDeshabilitar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_comision' => 'required|string'
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
      //da_te_default_timezone_set('America/Mexico_City');
      $periodo = $request->input('periodo');
      $token_comision = $request->input('token_comision');
      
      $selectComission = DB::table("terc_comisiones_main AS comi_main")
      ->join("main_empresas AS emp", "comi_main.empresa", "emp.id")
      ->where([
        "comi_main.token_comision_main" => $token_comision,
        "comi_main.status" => TRUE,
        "emp.empresa_token" => $empresa
      ])
      ->get();
  
      if ($selectComission->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Comisión no registrada, intente nuevamente o comuniquese a soporte'
        );
      } else {        
        foreach ($selectComission as $vComi) {
          $folio_comision = "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision);

          $rel_reem_comi = DB::table("terc_comisiones_main AS comi_main")
          ->join("sos_reembolsos_comisiones_rel AS comi_rel", "comi_main.id", "=", "comi_rel.comision")
          ->where("comi_main.token_comision_main",$vComi->token_comision_main)->get();

          if (!$vComi->concluida && count($rel_reem_comi) == 0) {
            $delete_comi = DB::table("terc_comisiones_main")->where(["token_comision_main" => $vComi->token_comision_main])->limit(1)->update(array("status" => FALSE, "fecha_delete_comission" => time()));
            if ($delete_comi) {
              $titulo_alerta = "La comisión con folio " . $folio_comision . " que te fue asignada, ha sido eliminada";

              $trabComissionadoQuery = DB::table("terc_comisiones_main AS comi_main")
              ->join("vhum_empleados_catalogo AS p_comi", "comi_main.usuario_comision", "p_comi.id")
              ->join("teci_usuarios_catalogo AS u_comi", "p_comi.id", "u_comi.empleado")
              ->where("comi_main.token_comision_main",$vComi->token_comision_main)
              ->select('u_comi.usuario_token')
              ->first();
              
              if ($trabComissionadoQuery) {
                $JwtAuth->notificacionPushDevices($trabComissionadoQuery->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
              }

              $dataMensaje = array("status" => "success", 'code' => 200, "message" => "Comisión con folio " . $folio_comision . " fue eliminada satisfactoriamente");
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, "message" => "Error en eliminación de comisión");
            }
          } else {
            if ($vComi->concluida) {
              $error_return = "Error en eliminación de comisión, no se pueden eliminar comisiones que ya han sido concluidas, ésta comisión se encuentra relacionada con ".count($rel_reem_comi)." reembolso(s)";
              $dataMensaje = array('status' => 'error', 'code' => 200, "message" => $error_return);
            } else if (count($rel_reem_comi) > 0) {
              $error_return = "Error en eliminación de comisión, se encuentra relacionada con ".count($rel_reem_comi)." reembolso(s)";
              $dataMensaje = array('status' => 'error', 'code' => 200, "message" => $error_return);
            }
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function comisionRehabilitar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_comision' => 'required|string'
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
      $token_comision = $request->input('token_comision');
  
			$selectComission = DB::table("terc_comisiones_main AS comi_main")
			->join("main_empresas AS emp", "comi_main.empresa", "emp.id")
			->where([
        "comi_main.token_comision_main" => $token_comision,
			  "comi_main.status" => FALSE,
			  "emp.empresa_token" => $empresa
      ])
      ->get();

      if ($selectComission->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Comisión no registrada, intente nuevamente o comuniquese a soporte'
        );
      } else {
        foreach ($selectComission as $vComi) {
          $folio_comision = "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision);

          $rel_reem_comi = DB::table("terc_comisiones_main AS comi_main")
          ->join("sos_reembolsos_comisiones_rel AS comi_rel", "comi_main.id", "=", "comi_rel.comision")
          ->where("comi_main.token_comision_main",$vComi->token_comision_main)->get();

          $delete_comi = DB::table("terc_comisiones_main")->where(["token_comision_main" => $vComi->token_comision_main])->limit(1)->update(array("status" => TRUE, "fecha_delete_comission" => NULL));
          if ($delete_comi) {
            $titulo_alerta = "La comisión con folio $folio_comision que te fue asignada, ha sido restaurada";
            $trabComissionadoQuery = DB::table("terc_comisiones_main AS comi_main")
            ->join("vhum_empleados_catalogo AS p_comi", "comi_main.usuario_comision", "p_comi.id")
            ->join("teci_usuarios_catalogo AS u_comi", "p_comi.id", "u_comi.empleado")
            ->where("comi_main.token_comision_main",$vComi->token_comision_main)
            ->select('u_comi.usuario_token')
            ->first();
            
            if ($trabComissionadoQuery) {
              $JwtAuth->notificacionPushDevices($trabComissionadoQuery->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
            }
            $dataMensaje = array("status" => "success", 'code' => 200, "message" => "Comisión con folio $folio_comision fue restaurada satisfactoriamente");
          } else {
            $dataMensaje = array('status' => 'error', 'code' => 200, "message" => "Error en restauración de comisión");
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function comisionDetalleUpdate(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_comision' => 'required|string'
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
			$token_comision = $request->input('token_comision');

			$selectComission = DB::table("terc_comisiones_main AS comi")
			->join("main_empresas AS emp", "comi.empresa", "emp.id")
			->where([
        "comi.token_comision_main" => $token_comision,
			  "emp.empresa_token" => $empresa
      ])
			->orderBy("comi.folio_comision", "DESC")->get();
  
      if ($selectComission->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Comisión no registrada, intente nuevamente o comuniquese a soporte'
        );
      } else {
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
            $comisionadoUserTkn = $vComiU->empleado_token;
            $comisionadoUserName = $JwtAuth->desencriptarNombres($vComiU->paterno, $vComiU->materno, $vComiU->nombre);
          }

          $sql_recibe_dinero = $vComi->recibe_dinero ? true : false;
          $sql_moneda_tkn = $vComi->recibe_dinero ? $vComi->comision_moneda : '';
          $sql_moneda_name = $vComi->recibe_dinero ? $vComi->comision_moneda : '';
          $sql_dinero_recibido = $vComi->recibe_dinero ? "$".number_format($vComi->dinero_recibido, $JwtAuth->getMonedaAPI($vComi->comision_moneda), '.', ',') : '';
          $sql_dinero_recibido_simple = $vComi->recibe_dinero ? $vComi->dinero_recibido : null;

          $sql_califica_egresos = $vComi->egresos ? true : false;
          $sql_califica_vhum = $vComi->valor_humano ? true : false;

          $comision_relaciones = DB::table("sos_reembolsos_comisiones_rel AS reem_comi")
            ->join("terc_comisiones_main AS comi", "reem_comi.comision", "comi.id")
            ->join("terc_reembolso_main AS reem_main", "reem_comi.reembolso_main", "reem_main.id")
            ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
          foreach ($comision_relaciones as $vComiR) {
            //$comisionadoUserTkn = $vComiU->empleado_token;
            //$comisionadoUserName = $JwtAuth->desencriptarNombres($vComiU->paterno,$vComiU->materno,$vComiU->nombre);
          }

          $sql_concluida_bool = $vComi->concluida ? false : true;
          $sql_concluida_fecha = $vComi->concluida ? null : $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha);

          $dataMensaje = array(
            "status" => "success",
            'code' => 200,
            "token_comision_main" => $vComi->token_comision_main,
            "folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
            "fecha_comision" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_comision),
            //edicion
            "comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
            "usuario_expide" => $user_expide,
            "usuario_comision_tkn" => $comisionadoUserTkn,
            "usuario_comision_name" => $comisionadoUserName,
            "especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
            "fecha_programada_text" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_programada),
            "fecha_programada_html" => $JwtAuth->convierteEpocFechaHtml($vComi->zona_horaria, $vComi->fecha_programada),
            "duracion" => $vComi->duracion,
            "recibe_dinero" => $sql_recibe_dinero,
            "dinero_recibido" => $sql_dinero_recibido,
            "dinero_recibido_simple" => $sql_dinero_recibido_simple,
            "comision_moneda_tkn" => $sql_moneda_tkn,
            "comision_moneda_name" => $sql_moneda_name,
            "comi_tiempo_respuesta" => $vComi->tiempo_respuesta,
            "egresos" => $sql_califica_egresos,
            "valor_humano" => $sql_califica_vhum,
            "ubicacion_latitud" => $vComi->ubicacion_latitud,
            "ubicacion_longitud" => $vComi->ubicacion_longitud,
            "ubicacion_display_name" => $JwtAuth->desencriptar($vComi->ubicacion_display_name),
            //relaciones
            "comision_relaciones_num" => count($comision_relaciones),
            //concluida
            "concluida_bool" => $sql_concluida_bool,
            "concluida_fecha" => $sql_concluida_fecha,
            //"ubicacion_address" => $vComi->ubicacion_address,
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function comisionDetalleGetData(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_comision' => 'required|string'
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
      $token_comision = $request->input('token_comision');
      
      $selectComission = DB::table("terc_comisiones_main AS comi")
      ->join("main_empresas AS emp", "comi.empresa", "emp.id")
      ->where([
        "comi.token_comision_main" => $token_comision, 
        "emp.empresa_token" => $empresa
      ])
      ->orderBy("comi.folio_comision", "DESC")->get();

      if ($selectComission->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Comisión no registrada, intente nuevamente o comuniquese a soporte'
        );
      } else {
        $array_comisiones = array();
				foreach ($selectComission as $vComi) {
					$expideComission = DB::table("terc_comisiones_main AS comi")
					->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
					foreach ($expideComission as $vExpide) {
						$user_expide = $JwtAuth->desencriptarNombres($vExpide->paterno, $vExpide->materno, $vExpide->nombre);
					}

					$comisionadoTrabQuery = DB::table("terc_comisiones_main AS comi")
					->join("vhum_empleados_catalogo AS pers", "comi.usuario_comision", "pers.id")
					->join("sos_personas AS people", "pers.empleado_name", "people.id")
					->where("comi.token_comision_main",$vComi->token_comision_main)
          ->select('pers.empleado_token','people.paterno','people.materno','people.nombre')
          ->first();

          $comisionadoProvQuery = DB::table("terc_comisiones_main AS comi")
          ->join("eegr_catalogo_proveedores AS catprov", "comi.proveedor_comisionado", "catprov.id")
          ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
          ->where("comi.token_comision_main",$vComi->token_comision_main)
          ->select('catprov.token_cat_proveedores','prov.nombre_extendido')
          ->first();
          
          if ($comisionadoTrabQuery) {
            $comisionadoTipo = "trabajador";
            $comisionadoTkn = $comisionadoTrabQuery->empleado_token;
            $comisionadoNombre = $JwtAuth->desencriptarNombres($comisionadoTrabQuery->paterno,$comisionadoTrabQuery->materno,$comisionadoTrabQuery->nombre);
          } elseif ($comisionadoProvQuery) {
            $comisionadoTipo = "proveedor";
            $comisionadoTkn = $comisionadoProvQuery->token_cat_proveedores;
            $comisionadoNombre = $JwtAuth->desencriptar($comisionadoProvQuery->nombre_extendido);
          }

					$sql_recibe_dinero = $vComi->recibe_dinero ? true : false;
					$sql_moneda = $vComi->recibe_dinero ? $vComi->comision_moneda : '';
					$sql_moneda_decimales = $vComi->recibe_dinero ? $JwtAuth->getMonedaAPI($vComi->comision_moneda) : '';
					$sql_dinero_recibido = $vComi->recibe_dinero ? "$".number_format($vComi->dinero_recibido, $JwtAuth->getMonedaAPI($vComi->comision_moneda), '.', ',') : '';
					$sql_dinero_recibido_simple = $vComi->recibe_dinero ? $vComi->dinero_recibido : null;

					$sql_califica_egresos = $vComi->egresos ? true : false;
					$sql_califica_vhum = $vComi->valor_humano ? true : false;

					$arrayReem = array();
					$comision_relaciones = DB::table("sos_reembolsos_comisiones_rel AS reem_comi")
					->join("terc_comisiones_main AS comi", "reem_comi.comision", "comi.id")
					->join("terc_reembolso_main AS reem_main", "reem_comi.reembolso_main", "reem_main.id")
					->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
					foreach ($comision_relaciones as $vremb) {
						$fecha_solicitud = $JwtAuth->mostrarUnixAFechaMexico($vremb->fecha_sistema);
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
            ->where(["reem_main.token_reem" => $token_reem])
            ->orderBy('reem_soli.folio_solicitud', 'DESC')->get();
  
            $importe_total = 0;
            $total_reembolsado = 0;
            $total_restante = 0;
            $moneda_entrante = "";
  
            $num_listado = 1;
            foreach ($soli_reem as $vSoliR) {
              $importe_total = $importe_total + $vSoliR->importe_entrante;
              $fecha_registro = $JwtAuth->mostrarUnixAFechaMexico($vSoliR->fecha_solicitud);
              $fecha_gasto = $JwtAuth->mostrarUnixAFechaMexico($vSoliR->fecha_gasto);
              $fecha_gasto_html = $JwtAuth->convierteEpocFechaHtml($vComi->zona_horaria, $vSoliR->fecha_gasto);
  
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
  
              $fecha_respuesta_autorizacion = $JwtAuth->mostrarUnixAFechaMexico($vSoliR->tiempo_respuesta_autorizacion);
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
                $filepath_old = $vComi->root_tkn . "/0010-reem/" . $folio_reem . "/anexos";
                $filepath_new = $vComi->root_tkn . "/0010-reem/" . $folio_reem . "/" . $JwtAuth->generarFolio($vSoliR->folio_solicitud) . "/anexos";
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
  
              $select_list_auth_vh = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_vh,r_auth.comentarios FROM terc_reembolso_autorizacion_vh AS r_auth 
                JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? 
                AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?", [$token_reem, $vSoliR->token_solicitud_reem]);
  
              $max_auth_vh = null;
              $fecha_registro_auth_vh = "";
              $hora_registro_auth_vh = "";
              $comments_auth_vh = "";
  
              if ($autorizacion_vh != null && $autorizacion_vh != "N" && count($select_list_auth_vh) > 0) {
                if (end($select_list_auth_vh)->autorizacion_vh == "A") $max_auth_vh = true;
                if (end($select_list_auth_vh)->autorizacion_vh == "D") $max_auth_vh = false;
                $fecha_registro_auth_vh = $JwtAuth->mostrarUnixAFechaMexico(end($select_list_auth_vh)->fecha_registro);
                $hora_registro_auth_vh = date('H:i:s', end($select_list_auth_vh)->fecha_registro);
                $comments_auth_vh = $JwtAuth->desencriptar(end($select_list_auth_vh)->comentarios);
              }
  
              $autorizacion_egr = $vSoliR->autorizacion_egr ? true : false;
  
              $select_folio_auth_egr = DB::select("SELECT r_auth.id FROM terc_reembolso_autorizacion_egr AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",
                [$token_reem, $vSoliR->token_solicitud_reem]);
  
              if (count($select_folio_auth_egr) == 0) {
                $max_auth_egr = false;
                $fecha_registro_auth_egr = "";
                $hora_registro_auth_egr = "";
                $comments_auth_egr = "";
              } else {
                $select_max_auth_egr = DB::select("SELECT fecha_registro,autorizacion_egr,comentarios FROM terc_reembolso_autorizacion_egr WHERE id = (SELECT MAX(r_auth.id) 
                  FROM terc_reembolso_autorizacion_egr AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli WHERE r_auth.reembolso_main = r_main.id 
                  AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",[$token_reem, $vSoliR->token_solicitud_reem]);
                $max_auth_egr = $select_max_auth_egr[0]->autorizacion_egr ? true : false;
  
                $fecha_registro_auth_egr = $select_max_auth_egr[0]->fecha_registro ? $JwtAuth->mostrarUnixAFechaMexico($select_max_auth_egr[0]->fecha_registro) : '';
                $hora_registro_auth_egr = $select_max_auth_egr[0]->fecha_registro ? date('H:i:s', $select_max_auth_egr[0]->fecha_registro) : '';
                $comments_auth_egr = $select_max_auth_egr[0]->comentarios ? $JwtAuth->desencriptar($select_max_auth_egr[0]->comentarios) : '';
              }
  
              $terminado = false;
              if ($vSoliR->terminado == TRUE) {
                $pagado_bool = true;
                $terminado = true;
              }
  
              if ($vSoliR->autorizacion_vh == TRUE && $vSoliR->autorizacion_egr == TRUE && $vSoliR->terminado == TRUE) {
                $total_reembolsado = $total_reembolsado + $vSoliR->importe_entrante;
              }
  
              $moneda_origen_codigo = $vSoliR->moneda_entrante;
              $moneda_entrante = $vSoliR->moneda_entrante;
              $moneda_origen_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);
  
              $reem_importe_resultante = number_format($vSoliR->importe_entrante * $vSoliR->tipo_cambio,$moneda_origen_decimales,'.', ',');
  
              $row = array(
                "token_solicitud_reem" => $vSoliR->token_solicitud_reem,
                "folio_solicitud" => $JwtAuth->generarFolio($vSoliR->folio_solicitud),
                "fecha_solicitud" => $JwtAuth->mostrarUnixAFechaMexico($vSoliR->fecha_solicitud),
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
                "importe_requerido_info" => "$".number_format($vSoliR->importe_entrante,$moneda_origen_decimales, '.', ',')." $moneda_origen_codigo",
  
                "moneda_origen_codigo" => $moneda_origen_codigo,
                "moneda_origen_decimales" => $moneda_origen_decimales,
  
                "tipo_cambio_string" => $vSoliR->tipo_cambio,
                "tipo_cambio_format" => "$".number_format($vSoliR->tipo_cambio,$moneda_origen_decimales, '.', ',')." $moneda_origen_codigo",
  
                "reem_importe_resultante_simple" => $reem_importe_resultante,
                "reem_importe_resultante" => "$$reem_importe_resultante $moneda_origen_codigo",
  
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
                "docsRespuesta" => $docsRespuestaArray,
              );
              ++$num_listado;
              $arraySoliReem[] = $row;
            }
  
            $total_restante = $importe_total - $total_reembolsado;

            $arrayPagosRegistrados = array();
            $num_lista_pagos = 1;
            //payment.cuenta_bancaria,payment.cuenta_monedero,payment.caja, payment.forma_pago,payment.metodo_pago,
            //$queryPagosDone = DB::table("fnzs_pagos_pago AS pay")
            //->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            //->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            //->where(["order.token_ordenPago" => $rOrdPag->token_ordenPago])->get();
            $listaPagos = DB::select("SELECT payment.token_pagos,payment.folio_pagos,payment.fecha_sistema,payment.fecha_pago,FORMAT(payment.monto_pago,?) AS formatMonto,
              payment.monto_pago,payment.tipo_cambio,payment.p_moneda,payment.concepto,payment.almacen,payment.personal_pago,payment.personal_autoriza,payment.empresa,
              payment.status_pagos,payment.fecha_deletePagos,payment.pago_autorizado,ordenp.fecha_sistema_ordenp,ordenp.folio_ordenPago	
              FROM fnzs_pagos_pago AS payment JOIN fnzs_pagos_pago_ordenes_vinculadas AS vinc JOIN fnzs_pagos_orden AS ordenp JOIN terc_reembolso_main AS reem_main 
              WHERE payment.id = vinc.pago_realizado AND vinc.orden_pago_vinculada = ordenp.id AND ordenp.reembolso_main = reem_main.id AND reem_main.token_reem = ?", 
              [$moneda_origen_decimales, $token_reem]);
  
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
                ->where([
                  'pers.id' => $resListaPagos->personal_pago,
                ])->get();
  
              if ($JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil) == 'default-profile.png') {
                $img_perfil_paga = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil)));
              } else {
                $img_perfil_paga = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $vremb->root_tkn . '/0004-vhm/catalogos/employees/' .
                  $JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil) . '/' . $JwtAuth->desencriptar($namePersonalPaga[0]->img_perfil) . '-profile.png'));
              }
  
              $nombre_completo_paga = $JwtAuth->desencriptar($namePersonalPaga[0]->paterno) . " " .
                $JwtAuth->desencriptar($namePersonalPaga[0]->materno) . " " . $JwtAuth->desencriptar($namePersonalPaga[0]->nombre);
  
              $namePersonalAutoriza = DB::table("vhum_empleados_catalogo AS pers")
                ->join("sos_personas AS people", "pers.empleado_name", "people.id")
                ->where([
                  'pers.id' => $resListaPagos->personal_autoriza,
                ])->get();
  
              if ($JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil) == 'default-profile.png') {
                $img_perfil_autoriza = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil)));
              } else {
                $img_perfil_autoriza = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $vremb->root_tkn . '/0004-vhm/catalogos/employees/' .
                  $JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil) . '/' . $JwtAuth->desencriptar($namePersonalAutoriza[0]->img_perfil) . '-profile.png'));
              }
  
              $nombre_completo_autoriza = $JwtAuth->desencriptar($namePersonalAutoriza[0]->paterno) . " " .
                $JwtAuth->desencriptar($namePersonalAutoriza[0]->materno) . " " . $JwtAuth->desencriptar($namePersonalAutoriza[0]->nombre);
  
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
                  //->where('respons.almacen','alm.id')
                  ->where("caja.serv_ingresos", TRUE)
                  ->where("caja.token_caja", $queryCajaRelacionada->token_caja)
                  ->where("emp.empresa_token", $empresa)
                  ->where("users.usuario_token", $usuario)
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
                    "emp.empresa_token" => $empresa,
                    "users.usuario_token" => $usuario
                  ])
                  ->orwhere([
                    "fnzs_catalogos_cuentas.status" => TRUE,
                    "fnzs_catalogos_cuentas.v_humano" => TRUE,
                    "fnzs_catalogos_cuentas.token_cuenta" => $queryCuentaRelacionada->token_cuenta,
                    "emp.empresa_token" => $empresa,
                    "users.usuario_token" => $usuario
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
  
                    $titular = utf8_decode($resCuentas->titular != '' ? $JwtAuth->desencriptar($resCuentas->titular) : '---');
  
                    if ($resCuentas->opciones_adicionales != '-') {
                      //echo $JwtAuth->desencriptar($resCuentas->opciones_adicionales);
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
                      "alta_cuenta" => $JwtAuth->mostrarUnixAFechaMexico($resCuentas->fecha_alta_cuenta),
                      "folio" => $JwtAuth->generar($resCuentas->folio_cuenta),
                      "contrato" => $arrayContrato,
                      "cuenta" => $arrayCuenta,
                      "clabe_inter" => $arrayClabeInetr,
                      "sucursal" => $sucursal,
                      "titular" => $titular,
                      "moneda" =>  $resMoneda,
                      "egresos" => $egresos,
                      "v_humano" => $v_humano,
                      "vigencia" => $JwtAuth->mostrarUnixAFechaMexico($resCuentas->vigencia),
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
  
                $respMonedero = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
                  ->join("vhum_empleados_catalogo AS pers", "fnzs_catalogos_cuentas_monedero.responsable", "pers.id")
                  ->join("teci_usuarios_catalogo AS users", "pers.id", "users.empleado")
                  ->where([
                    "fnzs_catalogos_cuentas_monedero.status" => TRUE,
                    "fnzs_catalogos_cuentas_monedero.token_cuentamonedero" => $queryMonederoRelacionado->token_cuentamonedero,
                    "emp.empresa_token" => $empresa,
                    "users.usuario_token" => $usuario
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
                        'emp.empresa_token' => $empresa,
                        'users.usuario_token' => $usuario
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
                        'emp.empresa_token' => $empresa,
                        'users.usuario_token' => $usuario
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
                      'emp.empresa_token' => $empresa,
                      'users.usuario_token' => $usuario
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
                    'fecha_alta_cuentamoned' => $JwtAuth->mostrarUnixAFechaMexico($resMonedero->fecha_alta_cuentamoned),
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
                    'vigencia' => $JwtAuth->mostrarUnixAFechaMexico($resMonedero->vigencia),
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
                "fecha_sistema" => $JwtAuth->mostrarUnixAFechaMexico($resListaPagos->fecha_sistema),
                "fecha_pago" => $JwtAuth->mostrarUnixAFechaMexico($resListaPagos->fecha_pago),
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

            $status_reem_bool = $vremb->status_reem ? "habilitado" : "deshabilitado";
            $status_reem_date = $vremb->status_reem ? "---" : $JwtAuth->mostrarUnixAFechaMexico($vremb->fecha_delete);
  
            $folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).(!is_null($vremb->post_folio_reem) ? '-'.$vremb->post_folio_reem : '');
  
            $row_reem = array(
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
  
              "total_reembolsado" => "$".number_format($total_reembolsado, $JwtAuth->getMonedaAPI($moneda_entrante), '.', ',')." $moneda_entrante",
              "total_restante" => "$".number_format($total_restante, $JwtAuth->getMonedaAPI($moneda_entrante), '.', ',')." $moneda_entrante",
              "total_importe" => "$".number_format($importe_total, $JwtAuth->getMonedaAPI($moneda_entrante), '.', ',')." $moneda_entrante",
              "soliReem" => $arraySoliReem,
              "pagosRegistrados" => $arrayPagosRegistrados,
              "pagado_bool" => $pagado_bool,
              "status_reem_bool" => $status_reem_bool,
              "status_reem_date" => $status_reem_date,
            );
						$arrayReem[] = $row_reem;
					}

					$sql_concluida_bool = $vComi->concluida ? true : false;
					$sql_concluida_fecha = $vComi->concluida ? $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha) : null;

					$row_comi = array(
						"token_comision_main" => $vComi->token_comision_main,
						"folio_comision" => "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision),
						"fecha_comision" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_comision),
						//edicion
						"comision_proyecto" => $JwtAuth->desencriptar($vComi->comision_proyecto),
						"usuario_expide" => $user_expide,
						"comisionado_tipo" => $comisionadoTipo,
						"comisionado_tkn" => $comisionadoTkn,
						"comisionado_name" => $comisionadoNombre,
						"especificaciones" => $JwtAuth->desencriptar($vComi->observaciones),
						"fecha_programada_text" => $JwtAuth->mostrarUnixAFechaMexico($vComi->fecha_programada),
						"fecha_programada_html" => $JwtAuth->convierteEpocFechaHtml($vComi->zona_horaria, $vComi->fecha_programada),
						"duracion" => $vComi->duracion,
						"recibe_dinero" => $sql_recibe_dinero,
						"dinero_recibido" => $sql_dinero_recibido,
						"dinero_recibido_simple" => $sql_dinero_recibido_simple,
						"comision_moneda" => $sql_moneda,
						"comision_moneda_decimales" => $sql_moneda_decimales,
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
						//relaciones
						"comision_relaciones_total" => count($comision_relaciones),
						"comision_relaciones" => $arrayReem,
						//concluida
						"concluida_bool" => $sql_concluida_bool,
						"concluida_fecha" => $sql_concluida_fecha,
						//"ubicacion_address" => $vComi->ubicacion_address,
					);
					$array_comisiones[] = $row_comi;
				}

				$dataMensaje = array("status" => "success", 'code' => 200, "comi_contenido" => $array_comisiones);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function comisionUpdate(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'token_comision' => 'required|string',
			'comi_proyecto' => 'required|string',
			'comi_comisionado_tipo' => 'required|string',
			'comi_comisionado_token' => 'required|string',
			'comi_especificaciones' => 'required|string',
			'comi_fecha_salida' => 'required|string',
			'comi_time_duracion' => 'required|string',
			'comi_recibe_dinero' => 'required|boolean',
			'comi_dinero_recibido' => 'string|nullable',
			'comi_moneda' => 'string',
			'comi_tiempo_respuesta' => 'required|numeric',
			'comi_califica_vhum' => 'boolean',
			'comi_califica_egresos' => 'required|boolean',
			'dipomex_cod_postal_estado' => 'required|string',
      'dipomex_cod_postal_municipio' => 'required|string',
      'dipomex_cod_postal_cp' => 'required|string',
      'dipomex_cod_postal_colonia_vinculada' => 'required|string',
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
			$token_comision = $request->input('token_comision');
			$comi_proyecto = $request->input('comi_proyecto');
			$comi_comisionado_tipo = $request->input('comi_comisionado_tipo');
			$comi_comisionado_token = $request->input('comi_comisionado_token');
			$comi_especificaciones = $request->input('comi_especificaciones');
			$comi_fecha_salida = $request->input('comi_fecha_salida');
			$comi_time_duracion = $request->input('comi_time_duracion');
			$comi_recibe_dinero = $request->input('comi_recibe_dinero');
			$comi_dinero_recibido = $request->input('comi_dinero_recibido');
			$comi_moneda = $request->input('comi_moneda');
			$comi_tiempo_respuesta = $request->input('comi_tiempo_respuesta');
			$comi_califica_vhum = $request->input('comi_califica_vhum');
			$comi_califica_egresos = $request->input('comi_califica_egresos');
			$dipomex_cod_postal_estado = $request->input('dipomex_cod_postal_estado');
			$dipomex_cod_postal_municipio = $request->input('dipomex_cod_postal_municipio');
			$dipomex_cod_postal_cp = $request->input('dipomex_cod_postal_cp');
			$dipomex_cod_postal_colonia_vinculada = $request->input('dipomex_cod_postal_colonia_vinculada');
      
      $comi_validate_to_save = false;
      $mensaje_error = "";

      $valida_comi_proyecto = isset($comi_proyecto) && !empty($comi_proyecto) && preg_match($JwtAuth->filtroAlfaNumerico(), $comi_proyecto);
      $valida_comisionado_tipo = isset($comi_comisionado_tipo) && !empty($comi_comisionado_tipo);
      $valida_comisionado_token = isset($comi_comisionado_token) && !empty($comi_comisionado_token);
      $valida_comi_especificaciones = isset($comi_especificaciones) && !empty($comi_especificaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $comi_especificaciones) && strlen($comi_especificaciones) >= 4;

      $valida_comi_fecha_salida = isset($comi_fecha_salida) && !empty($comi_fecha_salida) && preg_match($JwtAuth->filtroFecha(), $comi_fecha_salida);
      $valida_comi_time_duracion = isset($comi_time_duracion) && !empty($comi_time_duracion) && preg_match($JwtAuth->filtroNumerico(), $comi_time_duracion);
      $valida_comi_recibe_dinero = isset($comi_recibe_dinero) && is_bool($comi_recibe_dinero); 
      $valida_comi_tiempo_respuesta = isset($comi_tiempo_respuesta) && !empty($comi_tiempo_respuesta) && preg_match($JwtAuth->filtroNumerico(), $comi_tiempo_respuesta);
      $valida_comi_califica_egresos = isset($comi_califica_egresos) && is_bool($comi_califica_egresos); 

      $valida_comi_cod_postal_estado = isset($dipomex_cod_postal_estado) && !empty($dipomex_cod_postal_estado) && preg_match($JwtAuth->filtroAlfaNumerico(),$dipomex_cod_postal_estado);
      $valida_comi_cod_postal_municipio = isset($dipomex_cod_postal_municipio) && !empty($dipomex_cod_postal_municipio) && preg_match($JwtAuth->filtroAlfaNumerico(),$dipomex_cod_postal_municipio);
      $valida_comi_cod_postal_cp = isset($dipomex_cod_postal_cp) && !empty($dipomex_cod_postal_cp) && preg_match($JwtAuth->filtroAlfaNumerico(),$dipomex_cod_postal_cp);
      $valida_comi_cod_postal_colonia_vinculada = isset($dipomex_cod_postal_colonia_vinculada) && !empty($dipomex_cod_postal_colonia_vinculada) && preg_match($JwtAuth->filtroAlfaNumerico(),$dipomex_cod_postal_colonia_vinculada);

      if ($valida_comi_proyecto && $valida_comisionado_tipo && $valida_comisionado_token && $valida_comi_especificaciones && $valida_comi_fecha_salida && $valida_comi_time_duracion && $valida_comi_recibe_dinero && $valida_comi_tiempo_respuesta && 
        $valida_comi_califica_egresos && $valida_comi_cod_postal_estado && $valida_comi_cod_postal_municipio && $valida_comi_cod_postal_cp && $valida_comi_cod_postal_colonia_vinculada) {
        if ($comi_recibe_dinero) {
          $valida_comi_dinero_recibido = isset($comi_dinero_recibido) && !empty($comi_dinero_recibido) && preg_match($JwtAuth->filtroCostoPrecio(), $comi_dinero_recibido);
          $valida_comi_moneda_tkn = isset($comi_moneda_tkn) && !empty($comi_moneda_tkn);
          if ($valida_comi_dinero_recibido && $valida_comi_moneda_tkn) {
            $comi_validate_to_save = true;
          } else {
            $comi_validate_to_save = false;
            if (!$valida_comi_dinero_recibido) {$mensaje_error = "Error en dinero recibido para comisión";}
            if (!$valida_comi_moneda_tkn) {$mensaje_error = "Error en moneda de dinero recibido para comisión";}
            $dataMensaje = array('status' => 'error', 'code' => 200, "message" => $mensaje_error);
          }
        } else {
          $comi_validate_to_save = true;
        }

        $queryComission = DB::table("terc_comisiones_main AS comi")
        ->join("main_empresas AS emp", "comi.empresa", "emp.id")
        ->where("comi.token_comision_main",$token_comision)
        ->where("emp.empresa_token",$empresa)
        ->orderBy("comi.folio_comision", "DESC")->get();

        foreach ($queryComission as $vComi) {
          //da_te_default_timezone_set($vComi->zona_horaria);
          //$userComi = DB::select("SELECT pers.id,users.usuario_token FROM vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE pers.empleado_token = ? AND pers.id = users.empleado", [$comi_empleado_token]);
          $usuario_comision = $comi_comisionado_tipo == "trabajador" ? DB::table("vhum_empleados_catalogo")->where("empleado_token",$comi_comisionado_token)->value("id") : NULL;
          $proveedor_comisionado = $comi_comisionado_tipo == "proveedor" ? DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$comi_comisionado_token)->value("id") : NULL;
          $folio_comision = "COMI-" . $JwtAuth->generarFolio($vComi->folio_comision);
          
          $comisionadoTrabQuery = DB::table("terc_comisiones_main AS comi")
          ->join("vhum_empleados_catalogo AS pers", "comi.usuario_comision", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "people.id")
          ->where("comi.token_comision_main",$vComi->token_comision_main)
          ->select('pers.id')
          ->first();

          $comisionadoProvQuery = DB::table("terc_comisiones_main AS comi")
          ->join("eegr_catalogo_proveedores AS catprov", "comi.proveedor_comisionado", "catprov.id")
          ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
          ->where("comi.token_comision_main",$vComi->token_comision_main)
          ->select('catprov.id')
          ->first();
          
          $userComisionTkn = '';
          if ($comisionadoTrabQuery) {
            $userComisionOldId = $comisionadoTrabQuery->id;
          } elseif ($comisionadoProvQuery) {
            $userComisionOldId = $comisionadoProvQuery->id;
          }

          $updateComission = DB::table("terc_comisiones_main")->where(["token_comision_main" => $vComi->token_comision_main])
            ->limit(1)->update(
              array(
                "comision_proyecto" => $JwtAuth->encriptar($comi_proyecto),
                "usuario_comision" => $usuario_comision,
                "proveedor_comisionado" => $proveedor_comisionado,
                "observaciones" => $JwtAuth->encriptar($comi_especificaciones),
                "fecha_programada" => $JwtAuth->convierteFechaEpoc($comi_fecha_salida),
                "duracion" => $comi_time_duracion,
                "recibe_dinero" => $comi_recibe_dinero ? TRUE : FALSE,
                "dinero_recibido" => $comi_recibe_dinero ? $comi_dinero_recibido : NULL,
                "comision_moneda" => $comi_recibe_dinero ? $comi_moneda : NULL,
                "tiempo_respuesta" => $comi_tiempo_respuesta,
                "egresos" => $comi_califica_egresos ? TRUE : FALSE,
                "valor_humano" => $comi_califica_vhum ? TRUE : FALSE,
                "ubicacion_latitud" => NULL,
                "ubicacion_longitud" => NULL,
                "ubicacion_display_name" => NULL,
                "ubicacion_estado" => $JwtAuth->encriptar($dipomex_cod_postal_estado),
                "ubicacion_municipio" => $JwtAuth->encriptar($dipomex_cod_postal_municipio),
                "ubicacion_codigo_postal" => $dipomex_cod_postal_cp,
                "ubicacion_colonia" => $JwtAuth->encriptar($dipomex_cod_postal_colonia_vinculada),
              )
            );

          if ($updateComission) {
            $titulo_alerta = "Se ha registrado una nueva comisión con folio " . $folio_comision . ", de ésta se recibiran solicitudes de comprobación de gastos y/o compras";

            $trabComissionadoQuery = DB::table("terc_comisiones_main AS comi_main")
            ->join("vhum_empleados_catalogo AS p_comi", "comi_main.usuario_comision", "p_comi.id")
            ->join("teci_usuarios_catalogo AS u_comi", "p_comi.id", "u_comi.empleado")
            ->where("comi_main.token_comision_main",$vComi->token_comision_main)
            ->select('u_comi.usuario_token')
            ->first();
            if ($userComisionOldId != '' && $userComisionTkn != '' && $userComisionOldId != $usuario_comision && $trabComissionadoQuery) {
              $JwtAuth->notificacionPushDevices($userComisionTkn, "SOS-México - Empleados", "Has sido eliminado de la comisión con folio " . $folio_comision);
              $JwtAuth->notificacionPushDevices($trabComissionadoQuery->usuario_token, "SOS-México - Empleados", "Has sido asignado a la comisión con folio " . $folio_comision);
            }

            if ($comi_recibe_dinero) {
              $link_aviso_fnzs = "https://sos-mexico.com.mx/sos_inside/finanzas/comisiones_registro/" . urlencode($vComi->token_comision_main);
              $users_fnzs = DB::table("configuracion_systema_fnzs AS fnzs")
                ->join("teci_usuarios_catalogo AS users", "fnzs.usuario", "users.id")
                ->where(["fnzs.acceso" => TRUE, "fnzs.jerarquia" => "P"])
                ->where("users.id", ">", 3)
                ->get();

              foreach ($users_fnzs as $uFnzs) {
                $JwtAuth->notificacionPushDevicesLink($uFnzs->usuario_token, "SOS-México - Finanzas", $link_aviso_fnzs, $titulo_alerta);
              }
            }

            if ($comi_califica_vhum) {
              $link_aviso_vhum = "https://sos-mexico.com.mx/sos_inside/valor_humano/comisiones_registro/" . urlencode($vComi->token_comision_main);
              $users_vhum = DB::table("configuracion_systema_vhum AS vhum")
                ->join("teci_usuarios_catalogo AS users", "vhum.usuario", "users.id")
                ->where(["vhum.acceso" => TRUE, "vhum.jerarquia" => "P"])
                ->where("users.id", ">", 3)
                ->get();

              foreach ($users_vhum as $uVhum) {
                $JwtAuth->notificacionPushDevicesLink($uVhum->usuario_token, "SOS-México - Valor humano", $link_aviso_vhum, $titulo_alerta);
              }
            }

            $dataMensaje = array("status" => "success", 'code' => 200, "message" => "La comisión con el folio " . $folio_comision . " ha sido actualizada");
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              "message" => "comisión no actualizada, intentelo nuevamente o comuniquese a soporte",
            );
          }
        }
      } else {
        $mensaje_error = '';
        if (!$valida_comi_proyecto) {$mensaje_error = 'Error en proyecto de comisión';}
        if (!$valida_comisionado_tipo || !$valida_comisionado_token) {$mensaje_error = 'Error en comisionado seleccionado para comisión';}
        if (!$valida_comi_especificaciones) {$mensaje_error = 'Error en especificaciones de comisión';}
        if (!$valida_comi_fecha_salida) {$mensaje_error = 'Error en fecha de salida de comisión';}
        if (!$valida_comi_time_duracion) {$mensaje_error = 'Error en tiempo de duración de comisión';}
        if (!$valida_comi_tiempo_respuesta) {$mensaje_error = 'Error en tiempo de respuesta asignado para ésta comisión';}
        if (!$valida_comi_califica_egresos) {$mensaje_error = 'Error al verificar si el empleado recibe dinero para comisión';}
        if (!$valida_comi_califica_egresos) {$mensaje_error = 'Error al verifica si los reembolsos de esta comisión serán calificados por el departamento de egresos';}
        if (!$valida_comi_cod_postal_estado && !$valida_comi_cod_postal_municipio && !$valida_comi_cod_postal_cp && !$valida_comi_cod_postal_colonia_vinculada) {$mensaje_error = 'Error en ubicación de comisión';}
        $dataMensaje = array('status' => 'error', 'code' => 200, "message" => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function comisionReemListas(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    if ($JwtAuth->usersAdmins($usuario)) {
      $selectComission = DB::table("terc_comisiones_main AS comi")
      ->join("main_empresas AS emp", "comi.empresa", "emp.id")
      ->where([
        "comi.concluida" => FALSE,
        "comi.status" => TRUE,
        "emp.empresa_token" => $empresa
      ])
      ->orderBy("comi.folio_comision", "DESC")->get();
    } else {
      $selectComission = DB::table("terc_comisiones_main AS comi")
      ->join("main_empresas AS emp", "comi.empresa", "emp.id")
      ->join("vhum_empleados_catalogo AS pers", "comi.usuario_comision", "=", "pers.id")
      ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
      ->where([
        "comi.concluida" => FALSE,
        "comi.status" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->orderBy("comi.folio_comision", "DESC")->get();
    }
    
    if ($selectComission->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'Comisión no registrada, intente nuevamente o comuniquese a soporte'
      );
    } else {
      $array_comisiones = array();
      foreach ($selectComission as $vComi) {
        $expideComission = DB::table("terc_comisiones_main AS comi")
        ->join("vhum_empleados_catalogo AS pers", "comi.usuario_expide", "pers.id")
        ->join("sos_personas AS people", "pers.empleado_name", "people.id")
        ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
        foreach ($expideComission as $vExpide) {
          $user_expide = $JwtAuth->desencriptarNombres($vExpide->paterno,$vExpide->materno,$vExpide->nombre);
        }

        $comisionadoQuery = DB::table("terc_comisiones_main AS comi")
          ->join("vhum_empleados_catalogo AS pers", "comi.usuario_comision", "pers.id")
          ->join("sos_personas AS people", "pers.empleado_name", "people.id")
          ->where(["comi.token_comision_main" => $vComi->token_comision_main])->get();
        foreach ($comisionadoQuery as $vComiU) {
          $comisionadoUser = $JwtAuth->desencriptarNombres($vComiU->paterno,$vComiU->materno,$vComiU->nombre);
        }

        $sql_recibe_dinero = $vComi->recibe_dinero ? true : false;
        $sql_moneda_tkn = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
        $sql_moneda_name = $vComi->recibe_dinero ? $vComi->comision_moneda : null;
        $sql_dinero_recibido = $vComi->recibe_dinero ? "$" . number_format($vComi->dinero_recibido, $JwtAuth->getMonedaAPI($vComi->comision_moneda), '.', ',') : null;
        $sql_dinero_recibido_simple = $vComi->recibe_dinero ? $vComi->dinero_recibido : null;

        $sql_califica_egresos = $vComi->egresos ? true : false;
        $sql_califica_vhum = $vComi->valor_humano ? true : false;
        $bool_reapertura_fecha = !is_null($vComi->reapertura_fecha) ? true : false;

        $sql_concluida_fecha = $vComi->concluida ? $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha) : (!is_null($vComi->reapertura_fecha) ? $JwtAuth->mostrarUnixAFechaMexico($vComi->concluida_fecha) . " reabierto (" . $JwtAuth->mostrarUnixAFechaMexico($vComi->reapertura_fecha) . ")" : '');

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
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function comision_registro(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'comi_proyecto' => 'required|string',
			'comi_comisionado_tipo' => 'required|string',
			'comi_comisionado_token' => 'required|string',
			'comi_especificaciones' => 'required|string',
			'comi_fecha_salida' => 'required|string',
			'comi_time_duracion' => 'required|string',
			'comi_recibe_dinero' => 'required|boolean',
			'comi_dinero_recibido' => 'string',
			'comi_moneda_tkn' => 'string',
			'comi_tiempo_respuesta' => 'required|numeric',
			'comi_califica_vhum' => 'boolean',
			'comi_califica_egresos' => 'required|boolean',
			'dipomex_cod_postal_estado' => 'required|string',
			'dipomex_cod_postal_municipio' => 'required|string',
			'dipomex_cod_postal_cp' => 'required|string',
			'dipomex_cod_postal_colonia_vinculada' => 'required|string',
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
      $comi_proyecto = $request->input('comi_proyecto');
      $comi_comisionado_tipo = $request->input('comi_comisionado_tipo');
      $comi_comisionado_token = $request->input('comi_comisionado_token');
      $comi_especificaciones = $request->input('comi_especificaciones');
      $comi_fecha_salida = $request->input('comi_fecha_salida');
      $comi_time_duracion = $request->input('comi_time_duracion');
      $comi_recibe_dinero = $request->input('comi_recibe_dinero');
      $comi_dinero_recibido = $request->input('comi_dinero_recibido');
      $comi_moneda_tkn = $request->input('comi_moneda_tkn');
      $comi_tiempo_respuesta = $request->input('comi_tiempo_respuesta');
      $comi_califica_vhum = $request->input('comi_califica_vhum');
      $comi_califica_egresos = $request->input('comi_califica_egresos');
      $dipomex_cod_postal_estado = $request->input('dipomex_cod_postal_estado');
      $dipomex_cod_postal_municipio = $request->input('dipomex_cod_postal_municipio');
      $dipomex_cod_postal_cp = $request->input('dipomex_cod_postal_cp');
      $dipomex_cod_postal_colonia_vinculada = $request->input('dipomex_cod_postal_colonia_vinculada');

      $comi_validate_to_save = false;
      $mensaje_error = "";

      $valida_comi_proyecto = isset($comi_proyecto) && !empty($comi_proyecto) && preg_match($JwtAuth->filtroAlfaNumerico(), $comi_proyecto);
      $valida_comisionado_tipo = isset($comi_comisionado_tipo) && !empty($comi_comisionado_tipo);
      $valida_comisionado_token = isset($comi_comisionado_token) && !empty($comi_comisionado_token);
      $valida_comi_especificaciones = isset($comi_especificaciones) && !empty($comi_especificaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $comi_especificaciones) && strlen($comi_especificaciones) >= 4;
      
      $valida_comi_fecha_salida = isset($comi_fecha_salida) && !empty($comi_fecha_salida) && preg_match($JwtAuth->filtroFecha(), $comi_fecha_salida);
      $valida_comi_time_duracion = isset($comi_time_duracion) && !empty($comi_time_duracion) && preg_match($JwtAuth->filtroNumerico(), $comi_time_duracion);
      $valida_comi_tiempo_respuesta = isset($comi_tiempo_respuesta) && !empty($comi_tiempo_respuesta) && preg_match($JwtAuth->filtroNumerico(), $comi_tiempo_respuesta);
      $valida_comi_recibe_dinero = isset($comi_recibe_dinero) && is_bool($comi_recibe_dinero); 
      $valida_comi_califica_egresos = isset($comi_califica_egresos) && is_bool($comi_califica_egresos); 

      $valida_comi_cod_postal_estado = isset($dipomex_cod_postal_estado) && !empty($dipomex_cod_postal_estado) && preg_match($JwtAuth->filtroAlfaNumerico(),$dipomex_cod_postal_estado);
      $valida_comi_cod_postal_municipio = isset($dipomex_cod_postal_municipio) && !empty($dipomex_cod_postal_municipio) && preg_match($JwtAuth->filtroAlfaNumerico(),$dipomex_cod_postal_municipio);
      $valida_comi_cod_postal_cp = isset($dipomex_cod_postal_cp) && !empty($dipomex_cod_postal_cp) && preg_match($JwtAuth->filtroAlfaNumerico(),$dipomex_cod_postal_cp);
      $valida_comi_cod_postal_colonia_vinculada = isset($dipomex_cod_postal_colonia_vinculada) && !empty($dipomex_cod_postal_colonia_vinculada) && preg_match($JwtAuth->filtroAlfaNumerico(),$dipomex_cod_postal_colonia_vinculada);

      if ($valida_comi_proyecto && $valida_comisionado_tipo && $valida_comisionado_token && $valida_comi_especificaciones && $valida_comi_fecha_salida && $valida_comi_time_duracion && $valida_comi_recibe_dinero && $valida_comi_tiempo_respuesta &&
        $valida_comi_califica_egresos && $valida_comi_cod_postal_estado && $valida_comi_cod_postal_municipio && $valida_comi_cod_postal_cp && $valida_comi_cod_postal_colonia_vinculada) {
        if ($comi_recibe_dinero) {
          $valida_comi_dinero_recibido = isset($comi_dinero_recibido) && !empty($comi_dinero_recibido) && preg_match($JwtAuth->filtroCostoPrecio(), $comi_dinero_recibido);
          $valida_comi_moneda_tkn = isset($comi_moneda_tkn) && !empty($comi_moneda_tkn);
          if ($valida_comi_dinero_recibido && $valida_comi_moneda_tkn) {
            $comi_validate_to_save = true;
          } else {
            $comi_validate_to_save = false;
            if (!$valida_comi_dinero_recibido) {$mensaje_error = "Error en dinero recibido para comisión";}
            if (!$valida_comi_moneda_tkn) {$mensaje_error = "Error en moneda de dinero recibido para comisión";}
            $dataMensaje = array('status' => 'error', 'code' => 200, "message" => $mensaje_error);
          }
        } else {
          $comi_validate_to_save = true;
        }

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

        //return response()->json(['message' => count($selectEmp),'codigo' => 200,'status' => 'error']);

        foreach ($selectEmp as $vEmp) {
          //da_te_default_timezone_set($vEmp->zona_horaria);
          
          $folioSistema = DB::select("SELECT max(folio_comision) AS folio_comision FROM terc_comisiones_main AS comi JOIN main_empresas AS emp WHERE comi.empresa = emp.id AND emp.empresa_token = ?", [$empresa]);
          $sql_folio = count($folioSistema) == 0 ? 1 : $folioSistema[0]->folio_comision + 1;

          $sql_recibe_dinero = $comi_recibe_dinero ? TRUE : FALSE;
          $sql_dinero_recibido = $comi_recibe_dinero ? $comi_dinero_recibido : NULL;
          $sql_moneda_tkn = $comi_recibe_dinero ? $comi_moneda_tkn : NULL;
          # code...
          $sql_califica_egresos = $comi_califica_egresos ? TRUE : FALSE;
          $sql_califica_vhum = $comi_califica_vhum ? TRUE : FALSE;

          $token_comision = $JwtAuth->encriptarToken(
            $comi_proyecto,
            $comi_comisionado_tipo,
            $comi_especificaciones,
            $comi_fecha_salida,
            $comi_time_duracion,
            $comi_recibe_dinero,
            $comi_califica_egresos,
            $dipomex_cod_postal_estado, 
            $dipomex_cod_postal_municipio, 
            $dipomex_cod_postal_cp, 
            $dipomex_cod_postal_colonia_vinculada
          );
          
          $usuario_comision = $comi_comisionado_tipo == "trabajador" ? DB::table("vhum_empleados_catalogo")->where("empleado_token",$comi_comisionado_token)->value("id") : NULL;
          $proveedor_comisionado = $comi_comisionado_tipo == "proveedor" ? DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$comi_comisionado_token)->value("id") : NULL;
          //echo "usuario_comision $usuario_comision, proveedor_comisionado $proveedor_comisionado";
          //exit;

          $insertComission = DB::table("terc_comisiones_main")->insert(
            array(
              "token_comision_main" => $token_comision,
              "folio_comision" => $sql_folio,
              "fecha_comision" => time(),
              "comision_proyecto" => $JwtAuth->encriptar($comi_proyecto),
              "usuario_expide" => $vEmp->userr,
              "usuario_comision" => $usuario_comision,
              "proveedor_comisionado" => $proveedor_comisionado,
              "observaciones" => $JwtAuth->encriptar($comi_especificaciones),
              "fecha_programada" => $JwtAuth->convierteFechaEpoc($comi_fecha_salida),
              "duracion" => $comi_time_duracion,
              "recibe_dinero" => $sql_recibe_dinero,
              "dinero_recibido" => $sql_dinero_recibido,
              "comision_moneda" => $sql_moneda_tkn,
              "tiempo_respuesta" => $comi_tiempo_respuesta,
              "egresos" => $sql_califica_egresos,
              "valor_humano" => $sql_califica_vhum,

              "ubicacion_estado" => $JwtAuth->encriptar($dipomex_cod_postal_estado),
              "ubicacion_municipio" => $JwtAuth->encriptar($dipomex_cod_postal_municipio),
              "ubicacion_codigo_postal" => $dipomex_cod_postal_cp,
              "ubicacion_colonia" => $JwtAuth->encriptar($dipomex_cod_postal_colonia_vinculada),

              "empresa" => $selectEmp[0]->id,
              "status" => TRUE,
            )
          );

          if ($insertComission) {
            $titulo_alerta = "Se ha registrado una nueva comisión con folio COMI-" . $JwtAuth->generarFolio($sql_folio) . ", de ésta se recibiran solicitudes de comprobación de gastos y/o compras";
            $alerta_usComi = "Has sido asignado a la comisión con folio COMI-" . $JwtAuth->generarFolio($sql_folio);
            
            if ($comi_comisionado_tipo == "trabajador") {
              //$userComi = DB::select("SELECT pers.id,users.usuario_token FROM vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE pers.empleado_token = ? AND pers.id = users.empleado", [$comi_empleado_token]);
              $usuario_comisionado_token = DB::table("vhum_empleados_catalogo AS trab")->join("teci_usuarios_catalogo AS users", "trab.id", "users.empleado")
              ->where("trab.empleado_token",$comi_comisionado_token)->value("users.usuario_token");
              $JwtAuth->notificacionPushDevices($usuario_comisionado_token, "SOS-México - Finanzas", $alerta_usComi);
            }

            if ($comi_recibe_dinero == true) {
              $link_aviso_fnzs = "https://sos-mexico.com.mx/sos_inside/finanzas/comisiones_registro/" . urlencode($token_comision);
              $users_fnzs = DB::table("configuracion_systema_fnzs AS fnzs")
                ->join("teci_usuarios_catalogo AS users", "fnzs.usuario", "users.id")
                ->where(["fnzs.acceso" => TRUE, "fnzs.jerarquia" => "P"])
                ->where("users.id", ">", 3)
                ->get();

              foreach ($users_fnzs as $uFnzs) {
                $JwtAuth->notificacionPushDevicesLink($uFnzs->usuario_token, "SOS-México - Finanzas", $link_aviso_fnzs, $titulo_alerta);
              }
            }

            if ($comi_califica_egresos == true) {
              $link_aviso_eegr = "https://sos-mexico.com.mx/sos_inside/egresos/comisiones_registro/" . urlencode($token_comision);
              $users_eegr = DB::table("configuracion_systema_eegr AS eegr")
                ->join("teci_usuarios_catalogo AS users", "eegr.usuario", "users.id")
                ->where(["eegr.acceso" => TRUE, "eegr.jerarquia" => "P"])
                ->where("users.id", ">", 3)
                ->get();

              foreach ($users_eegr as $uEegr) {
                $JwtAuth->notificacionPushDevicesLink($uEegr->usuario_token, "SOS-México - Egresos", $link_aviso_eegr, $titulo_alerta);
              }
            }

            if ($comi_califica_vhum == true) {
              $link_aviso_vhum = "https://sos-mexico.com.mx/sos_inside/valor_humano/comisiones_registro/" . urlencode($token_comision);
              $users_vhum = DB::table("configuracion_systema_vhum AS vhum")
                ->join("teci_usuarios_catalogo AS users", "vhum.usuario", "users.id")
                ->where(["vhum.acceso" => TRUE, "vhum.jerarquia" => "P"])
                ->where("users.id", ">", 3)
                ->get();

              foreach ($users_vhum as $uVhum) {
                $JwtAuth->notificacionPushDevicesLink($uVhum->usuario_token, "SOS-México - Valor humano", $link_aviso_vhum, $titulo_alerta);
              }
            }

            $dataMensaje = array(
              "status" => "success",
              'code' => 200,
              "message" => "comisión registrada con el folio COMI-" . $JwtAuth->generarFolio($sql_folio),
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              "message" => "comisión no registrada, intentelo nuevamente o comuniquese a soporte",
            );
          }
        }

      } else {
        $mensaje_error = '';
        if (!$valida_comi_proyecto) {$mensaje_error = 'Error en proyecto de comisión';}
        if (!$valida_comisionado_tipo || !$valida_comisionado_token) {$mensaje_error = 'Error en comisionado seleccionado para comisión';}
        if (!$valida_comi_especificaciones) {$mensaje_error = 'Error en especificaciones de comisión';}
        if (!$valida_comi_fecha_salida) {$mensaje_error = 'Error en fecha de salida de comisión';}
        if (!$valida_comi_time_duracion) {$mensaje_error = 'Error en tiempo de duración de comisión';}
        if (!$valida_comi_tiempo_respuesta) {$mensaje_error = 'Error en tiempo de respuesta asignado para ésta comisión';}
        if (!$valida_comi_recibe_dinero) {$mensaje_error = 'Error al verificar si el comisionado recibe dinero para comisión';}
        if (!$valida_comi_califica_egresos) {$mensaje_error = 'Error al verifica si los reembolsos de esta comisión serán calificados por el departamento de egresos';}
        if (!$valida_comi_cod_postal_estado && !$valida_comi_cod_postal_municipio && !$valida_comi_cod_postal_cp && !$valida_comi_cod_postal_colonia_vinculada) {$mensaje_error = 'Error en ubicación de comisión';}
        $dataMensaje = array('status' => 'error', 'code' => 200, "message" => $mensaje_error);
      }
  
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function comisionadosListas(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
    ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->where("vhum_empleados_catalogo.folio_pers", "!=", 0)
    ->where([
      'vhum_empleados_catalogo.status' => TRUE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->get();
    
    $listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
    ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
    ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
    ->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      'catprov.status' => TRUE,
      'catprov.subClase' => 'PF',
      'catprov.habilitado_para_reembolsos' => TRUE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->get();
    
    if ($listEmpleados->isEmpty() && $listaProveedores->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron comisionados registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayComisionados = array();
      foreach ($listEmpleados as $vEmploy) {
        $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');
        $nombre_completo = ucwords($JwtAuth->desencriptar($vEmploy->paterno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->materno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->nombre));

        $rowEmpleado = array(
          "comisionado_token" => $vEmploy->empleado_token,
          "comisionado_folio" => $folio_empleado,
          "comisionado_nombre" => ucwords($nombre_completo),
          "comisionado_tipo" => "trabajador"
        );
        $arrayComisionados[] = $rowEmpleado;
      }

      foreach ($listaProveedores as $resListProv) {
        $nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

        $folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio).(!is_null($resListProv->post_folio) ? '-' . $resListProv->post_folio : '');

        $rowPrv = array(
          "comisionado_token" => $resListProv->token_cat_proveedores,
          "comisionado_folio" => $folio_prov,
          "comisionado_nombre" => ucwords($nombreProv),
          "comisionado_tipo" => "proveedor"
        );
        $arrayComisionados[] = $rowPrv;
      }

      $dataMensaje = array(
        "status" => "success",
        'code' => 200,
        "comisionados_lista" => $arrayComisionados,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}
}
