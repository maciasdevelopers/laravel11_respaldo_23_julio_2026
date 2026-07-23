<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\OrdenPagoModelo;
use App\Models\DeudoresModelo;

class EGRE_AnticiposController extends Controller{
  public function anticipoTotalEach($anticipos){
    $proveedor_anticipo_total = 0;
    
    foreach ($anticipos as $vAnt) {
      $queryApplicacionAnticipo = DB::table("eegr_catalogo_proveedores_anticipo_aplicacion")->where("anticipo_registrado", $vAnt->uuid_anticipo)->get();
      $anticipoTotalAplicado = $queryApplicacionAnticipo->sum('monto_total_anticipo');
      $monto_real = $vAnt->monto_total - $anticipoTotalAplicado;
      $tipo_cambio = $vAnt->tipo_cambio;
      $proveedor_anticipo_total += ($monto_real * $tipo_cambio);
    }

    return $proveedor_anticipo_total;
  }

  public function anticipoEach($anticipos,$JwtAuth){
    $listaAnticipos = array();
    
    $idCancela = $anticipos->pluck('anticipo_cancel_user')->filter()->unique()->toArray();
    $UsuarioCancelaMap = DB::table("teci_usuarios_catalogo AS users")
    ->join("vhum_empleados_catalogo AS pers", "users.empleado", "=", "pers.id")
    ->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
    ->whereIn("users.id",$idCancela)
    ->select(
      'users.id AS auth_user',
      'people.paterno',
      'people.materno',
      'people.nombre'
    )
    ->get()->keyBy('auth_user');

    foreach ($anticipos as $vAnt) {
      //da_te_default_timezone_set($vAnt->zona_horaria);
      $queryApplicacionAnticipo = DB::table("eegr_catalogo_proveedores_anticipo_aplicacion")->where("anticipo_registrado", $vAnt->uuid_anticipo)->get();
      $anticipoTotalAplicado = $queryApplicacionAnticipo->sum('monto_total_anticipo');
      $monto_real = $vAnt->monto_total - $anticipoTotalAplicado;
      $tipo_cambio = $vAnt->tipo_cambio;
      //$proveedor_anticipo_total += ($monto_real * $tipo_cambio);

      $queryBuyProv = DB::table("eegr_catalogo_proveedores AS catprov")
      ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
      ->where("catprov.token_cat_proveedores",$vAnt->token_cat_proveedores)
      ->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido','people.nombre_com')
      ->first();
      $proveedor_token = $queryBuyProv ? $queryBuyProv->token_cat_proveedores : '';
      $proveedor_folio = $queryBuyProv ? 'PRV-'.$JwtAuth->generarFolio($queryBuyProv->folio).($queryBuyProv->post_folio != NULL ? '-' . $queryBuyProv->post_folio : '') : '';
      $proveedor_nombre = $queryBuyProv ? $JwtAuth->desencriptar($queryBuyProv->nombre_extendido) : '';
      $proveedor_nombre_comercial = $queryBuyProv && !is_null($queryBuyProv->nombre_com) ? $JwtAuth->desencriptar($queryBuyProv->nombre_com) : '';

      $anticipo_cancel_user = "";
      if ($vAnt->anticipo_cancelado) {
        $queryUserCancel = $UsuarioCancelaMap->get($vAnt->anticipo_cancel_user);
        $anticipo_cancel_user = $queryUserCancel ? $JwtAuth->desencriptarNombres($queryUserCancel->paterno, $queryUserCancel->materno, $queryUserCancel->nombre) : '';
      }

      $row = array(
        "anticipo_uuid" => $vAnt->uuid_anticipo,
        "anticipo_folio" => 'ANT-'.$JwtAuth->generarFolio($vAnt->folio_anticipo),
        "proveedor_token" => $proveedor_token,
        "proveedor_folio" => $proveedor_folio,
        "proveedor_nombre" => $proveedor_nombre,
        "proveedor_nombre_comercial" => $proveedor_nombre_comercial,
        "anticipo_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vAnt->ant_fecha_contabilizacion),
        "anticipo_forma_pago" => $vAnt->forma_pago_anticipo,
        "anticipo_moneda_code" => $vAnt->moneda_code,
        "anticipo_moneda_decimales" => $vAnt->moneda_decimales,
        "anticipo_tipo_cambio" => $tipo_cambio,
        "anticipo_tipo_cambio_format" => "$" . number_format($tipo_cambio, $vAnt->moneda_decimales, '.', ',') . ' ' . $vAnt->moneda_code,
        "anticipo_cantidad_anticipo" => $vAnt->monto_total,
        "anticipo_cantidad_anticipo_format" => "$" . number_format($vAnt->monto_total, $vAnt->moneda_decimales, '.', ',') . ' ' . $vAnt->moneda_code,
        
        "anticipo_cantidad_anticipo_real" => $vAnt->monto_total * (!empty($vSal->tipo_cambio) ? $tipo_cambio : 1.00),
        "anticipo_cantidad_anticipo_real_format" => "$" . number_format($vAnt->monto_total * (!empty($vSal->tipo_cambio) ? $tipo_cambio : 1.00), $vAnt->moneda_decimales, '.', ',') . ' ' . $vAnt->moneda_code,

        "anticipo_monto_real" => $monto_real,
        "anticipo_monto_real_format" => "$" . number_format($monto_real * (!empty($vSal->tipo_cambio) ? $tipo_cambio : 1.00), $vAnt->moneda_decimales, '.', ',') . ' ' . $vAnt->moneda_code,
        
        "anticipo_observaciones" => $JwtAuth->desencriptar($vAnt->observaciones),
        "select_for_pagos" => false,
        "disponible" => $vAnt->disponible,
        "ant_autorizado" => $vAnt->ant_autorizacion_decide ? true : false,
        "visible_for_autorizar" => false,
        "visible_for_rechazar" => false,
        "anticipo_procesos_fecha_contabilizacion" => "",
        "anticipo_procesos_moneda" => "",
        "anticipo_procesos_moneda_decimales" => 0,
        "anticipo_procesos_importe" => 0,
        "anticipo_procesos_importe_resultante" => 0,
        "anticipo_procesos_importe_resultante_string" => "0.00",
        "anticipo_procesos_tipo_cambio_number" => 0,
        "anticipo_procesos_tipo_cambio_string" => 0,
        "anticipo_procesos_f_pago" => "",
        "anticipo_procesos_f_pago_token" => "",
        "anticipo_procesos_comentarios" => "",
        "anticipo_cancelado" => (bool)$vAnt->anticipo_cancelado,
        "anticipo_folio_cancelacion" => $vAnt->anticipo_cancelado ? "PCAN-".$JwtAuth->generarFolio($vAnt->anticipo_folio_cancelacion) : "",
        "anticipo_cancel_user" => $vAnt->anticipo_cancelado ? $anticipo_cancel_user : "",
        "anticipo_cancel_fecha_cont" => $vAnt->anticipo_cancelado ? $JwtAuth->mostrarUnixAFechaMexico($vAnt->anticipo_cancel_fecha_cont) : "",
        "anticipo_cancel_comentarios" => $vAnt->anticipo_cancelado ? $JwtAuth->desencriptar($vAnt->anticipo_cancel_comentarios) : "",
      );
      $listaAnticipos[] = $row;
    }

    return $listaAnticipos;
  }

  public function anticipoCatalogoGeneral(Request $request){
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
      
      $queryAnticipos = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
      ->join("eegr_catalogo_proveedores AS catprv", "ant.proveedor", "catprv.id")
      ->join("main_empresas AS emp", "ant.empresa", "emp.id")
      ->where([
        "ant.estatus_anticipo" => TRUE,
        "emp.empresa_token" => $empresa
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("ant.ant_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy("ant.folio_anticipo", "DESC")
      ->get();

      if ($queryAnticipos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron anticipos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $proveedor_anticipo_total = $this->anticipoTotalEach($queryAnticipos);

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'anticipo_total' => "$" . number_format($proveedor_anticipo_total, $JwtAuth->getMonedaAPI("MXN"), '.', ',') . ' MXN',
          'anticipos_registrados' => $this->anticipoEach($queryAnticipos,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function anticipoAutorizados(Request $request){
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
      
      $queryAnticipos = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
      ->join("eegr_catalogo_proveedores AS catprv", "ant.proveedor", "catprv.id")
      ->join("main_empresas AS emp", "ant.empresa", "emp.id")
      ->where([
        "ant.ant_autorizacion_decide" => TRUE,
        "ant.estatus_anticipo" => TRUE,
        "emp.empresa_token" => $empresa
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("ant.ant_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy("ant.folio_anticipo", "DESC")
      ->get();

      if ($queryAnticipos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron anticipos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $proveedor_anticipo_total = $this->anticipoTotalEach($queryAnticipos);

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'anticipo_total' => "$" . number_format($proveedor_anticipo_total, $JwtAuth->getMonedaAPI("MXN"), '.', ',') . ' MXN',
          'anticipos_registrados' => $this->anticipoEach($queryAnticipos,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function anticipoSolicitudes(Request $request){
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
      
      $queryAnticipos = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
      ->join("eegr_catalogo_proveedores AS catprv", "ant.proveedor", "catprv.id")
      ->join("main_empresas AS emp", "ant.empresa", "emp.id")
      ->where([
        "ant.ant_autorizacion_decide" => FALSE,
        "ant.estatus_anticipo" => TRUE,
        "emp.empresa_token" => $empresa
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("ant.ant_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy("ant.folio_anticipo", "DESC")
      ->get();

      if ($queryAnticipos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron solicitudes de anticipo registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $proveedor_anticipo_total = $this->anticipoTotalEach($queryAnticipos);

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'anticipo_total' => "$" . number_format($proveedor_anticipo_total, $JwtAuth->getMonedaAPI("MXN"), '.', ',') . ' MXN',
          'anticipos_registrados' => $this->anticipoEach($queryAnticipos,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function anticipoAutorizar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'anticipo_uuid' => 'required|string',
			'anticipo_proveedor' => 'required|string',
			'anticipo_fecha_contabilizacion' => 'required|string',
			'anticipo_moneda' => 'required|string',
			'anticipo_moneda_decimales' => 'required|numeric',
			'anticipo_importe' => 'required|numeric',
			'anticipo_tipo_cambio_number' => 'required|numeric',
			'anticipo_f_pago' => 'required|string',
			'anticipo_caja' => 'array',
			'anticipo_cuenta_bancaria' => 'array',
			'anticipo_cuenta_monedero' => 'array',
			'anticipo_comentarios' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Datos de validación incorrectos',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $anticipo_uuid = $request->input('anticipo_uuid');
      $anticipo_proveedor = $request->input('anticipo_proveedor');
      $anticipo_fecha_contabilizacion = $request->input('anticipo_fecha_contabilizacion');
      $anticipo_moneda = $request->input('anticipo_moneda');
      $anticipo_moneda_decimales = $request->input('anticipo_moneda_decimales');
      $anticipo_importe = $request->input('anticipo_importe');
      $anticipo_tipo_cambio_number = $request->input('anticipo_tipo_cambio_number');
      $anticipo_f_pago = $request->input('anticipo_f_pago');
      $anticipo_caja = $request->input('anticipo_caja');
      $anticipo_cuenta_bancaria = $request->input('anticipo_cuenta_bancaria');
      $anticipo_cuenta_monedero = $request->input('anticipo_cuenta_monedero');
      $anticipo_comentarios = $request->input('anticipo_comentarios');
            
      $valide_anticipo_uuid = isset($anticipo_uuid) && !empty($anticipo_uuid);
      $valide_anticipo_proveedor = isset($anticipo_proveedor) && !empty($anticipo_proveedor);
      $valide_fecha_contabilizacion = isset($anticipo_fecha_contabilizacion) && !empty($anticipo_fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$anticipo_fecha_contabilizacion);
      $valide_moneda = isset($anticipo_moneda) && !empty($anticipo_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$anticipo_moneda);
      $valide_moneda_decimales = isset($anticipo_moneda_decimales) && !empty($anticipo_moneda_decimales) && preg_match($JwtAuth->filtroNumericoSimple(),$anticipo_moneda_decimales);
      $valide_importe = isset($anticipo_importe) && !empty($anticipo_importe) && preg_match($JwtAuth->filtroCostoPrecio(),$anticipo_importe);
      $valide_tipo_cambio = isset($anticipo_tipo_cambio_number) && !empty($anticipo_tipo_cambio_number) && preg_match($JwtAuth->filtroCostoPrecio(),$anticipo_tipo_cambio_number);
      $valide_forma_pago = isset($anticipo_f_pago) && !empty($anticipo_f_pago) && preg_match($JwtAuth->filtroAlfaNumerico(),$anticipo_f_pago);
      $valide_caja = isset($anticipo_caja) && !empty($anticipo_caja) && is_array($anticipo_caja);
      $valide_cuenta_bancaria = isset($anticipo_cuenta_bancaria) && !empty($anticipo_cuenta_bancaria) && is_array($anticipo_cuenta_bancaria);
      $valide_cuenta_monedero = isset($anticipo_cuenta_monedero) && !empty($anticipo_cuenta_monedero) && is_array($anticipo_cuenta_monedero);
      $valide_comentarios = isset($anticipo_comentarios) && !empty($anticipo_comentarios) && preg_match($JwtAuth->filtroAlfaNumerico(),$anticipo_comentarios);

      if ($valide_anticipo_uuid && $valide_anticipo_proveedor && $valide_fecha_contabilizacion && $valide_moneda && $valide_moneda_decimales && $valide_importe && $valide_tipo_cambio && $valide_forma_pago && $valide_comentarios) {
        //return response()->json(['message' => $anticipo_fecha_contabilizacion,'code' => 200,'status' => 'error']);
        //if ($valide_cuenta_bancaria && count($anticipo_cuenta_bancaria) > 0) {
        //  return response()->json(['message' => "existe cuenta",'code' => 200,'status' => 'error']);
        //} else {
        //  return response()->json(['message' => "no existe cuenta",'code' => 200,'status' => 'error']);
        //}exit;
				$vEmp = DB::table("main_empresas AS emp")
				->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
				->join("vhum_empleados_catalogo AS trab", "empuser.empleado", "=", "trab.id")
				->join("teci_usuarios_catalogo AS users", "trab.id", "=", "users.empleado")
				->where('emp.empresa_token',$empresa)
				->where('users.usuario_token',$usuario)
				->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
				->first();

				if (!$vEmp) return response()->json(['status' => 'error', 'message' => 'Entorno contable no encontrado'], 404);
        
        $anticipoBase = DB::table("eegr_catalogo_proveedores_anticipo")
        ->where("uuid_anticipo",$anticipo_uuid)
        ->first();
        
        if (!$anticipoBase) {
            return response()->json(['status' => 'error', 'message' => 'El anticipo solicitado no existe'], 404);
        }

        DB::beginTransaction();
        try {
          //da_te_default_timezone_set($vEmp->zona_horaria);
          $token_cat_deudores = "";
          $ant_fpago = $JwtAuth->getFormasPagoAPIByDescripcion(DB::table("eegr_catalogo_proveedores_anticipo")->where("uuid_anticipo",$anticipo_uuid)->value("forma_pago_anticipo"));
          //return response()->json(['status' => 'error','code' => 200,'message' => "ant_fpago $ant_fpago"]);
  
          $queryPrvDeudores = DB::table("fnzs_catalogo_deudores AS catDeu")
          ->join("eegr_catalogo_proveedores AS catPrv", "catDeu.deu_proveedor_vinculado", "catPrv.id")
          ->where("catDeu.deu_status",TRUE)
          ->where("catPrv.token_cat_proveedores",$anticipo_proveedor)
          ->get();
          if (count($queryPrvDeudores) > 0) {
            foreach ($queryPrvDeudores as $prvDeu) {
              $token_cat_deudores = $prvDeu->token_cat_deudores;
            }
          } else {
            $proveedor_data = DB::table("eegr_catalogo_proveedores AS catprov")
            ->join("sos_personas AS people", "catprov.proveedor", "people.id")
            ->where("token_cat_proveedores",$anticipo_proveedor)
            ->select("catprov.id","catprov.subClase","people.nombre_extendido","people.nombre_com","people.nacionalidad","people.rfc_generico","people.rfc","people.tax_id")
            ->first();
            
            $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp 
              JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE fold.fnzs_deudores = TRUE AND fold.empresa = emp.id 
              AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$empresa, $usuario]
            );
            
            $post_folio_db = DB::select("SELECT deu_post_folio FROM fnzs_catalogo_deudores WHERE id = (SELECT Max(catDeu.id) FROM fnzs_catalogo_deudores AS catDeu 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catDeu.deu_empresa = emp.id 
              AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",
              [$empresa, $usuario]
            );
            
            $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
            $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->deu_post_folio);
            $folio_deu = $post_folio == NULL ? 'DEU-' . $JwtAuth->generarFolio($folio_nuevo) : 'DEU-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
            $autorizado = TRUE;
            $autorizacion_fecha = time();
            $autorizacion_user = $vEmp->userr;
  
            //fnzs_catalogo_deudores
            //token_cat_deudores
            //deu_folio
            //deu_post_folio
            //deu_authorized
            //deu_authorized_fecha
            //deu_authorized_by
            //deu_fecha_contab_registro
            //deu_rfc_generico
            //deu_rfc
            //deu_taxId
            //deu_nacionalidad
            //deu_titular
            //deu_nombre_comercial
            //deu_cuenta_contable
            //deu_habilita_reembolsos
            //deu_fisica_moral
            //deu_empleado_vinculado
            //deu_proveedor_vinculado
            //deu_acreedor_vinculado
            //deu_regimen_fiscal
            //deu_status
            //deu_fecha_delete
            //deu_empresa
            //created_at
            //updated_at
  
            $token_cat_deudores = $JwtAuth->encriptarToken($proveedor_data->id.$autorizado.$autorizacion_fecha.$autorizacion_user.$folio_nuevo.$post_folio.$vEmp->id);
            $creaCatDeus = new deudoresModelo();
            $creaCatDeus->token_cat_deudores = $token_cat_deudores;
            $creaCatDeus->deu_folio = $folio_nuevo;
            $creaCatDeus->deu_post_folio = $post_folio;
            $creaCatDeus->deu_authorized = TRUE;
            $creaCatDeus->deu_authorized_fecha = time();
            $creaCatDeus->deu_authorized_by = $vEmp->userr;
            $creaCatDeus->deu_fecha_contab_registro = time();
            $creaCatDeus->deu_rfc_generico = $proveedor_data->rfc_generico;
            $creaCatDeus->deu_rfc = $proveedor_data->rfc;
            $creaCatDeus->deu_taxId = $proveedor_data->tax_id;
            $creaCatDeus->deu_nacionalidad = $proveedor_data->nacionalidad == 118 ? 'MEX' : 'EXT';
            $creaCatDeus->deu_titular = $proveedor_data->nombre_extendido;
            $creaCatDeus->deu_nombre_comercial = $proveedor_data->nombre_com;
            $creaCatDeus->deu_proveedor_vinculado = $proveedor_data->id;
            $creaCatDeus->deu_habilita_reembolsos = TRUE;
            $creaCatDeus->deu_fisica_moral = $proveedor_data->subClase;
            $creaCatDeus->deu_status = TRUE;
            $creaCatDeus->deu_empresa = $vEmp->id;
            $savednewDeus = $creaCatDeus->save();
  
            if (count($folioSistema) == 0) {
              $insertSistema = DB::table("sos_last_folders")
              ->insert(
                array(
                  "fnzs_deudores" => TRUE,
                  "folder" => 1,
                  "post_folder" => $post_folio,
                  "empresa" => $vEmp->id,
                )
              );
            } else {
              $regFolder = DB::table("sos_last_folders AS fold")
              ->join("main_empresas AS emp", "fold.empresa", "=", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
              ->where("fold.fnzs_deudores",TRUE)
              ->where("emp.empresa_token",$empresa)
              ->where("users.usuario_token",$usuario)
              ->limit(1)->update(
                array(
                  "fold.folder" => $folio_nuevo,
                  "fold.post_folder" => $post_folio,
                )
              );
            }
          }
  
          $ident_deudor = DB::table("fnzs_catalogo_deudores")->where("token_cat_deudores",$token_cat_deudores)->value("id");

          $updateProvValid = DB::table("eegr_catalogo_proveedores_anticipo")
          ->where("uuid_anticipo",$anticipo_uuid)
          ->update([
            "ant_autorizacion_decide" => TRUE,
            "ant_autorizacion_fecha" => time(),
            "ant_autorizacion_coments" => $JwtAuth->encriptar($anticipo_comentarios),
            "ant_deudor_vinculado" => $ident_deudor
          ]);
  
          //$folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
          //  JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
          //  AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

          $maxFolioOrdPago = DB::table('fnzs_pagos_orden')->where('empresa', $vEmp->id)
          ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
          ->max('folio_ordenPago');
          $folioOrden = $maxFolioOrdPago ? $maxFolioOrdPago + 1 : 1;

          $tknOrder = $JwtAuth->encriptarToken(time(), $folioOrden,$ident_deudor);
          $orden_de_pago_vinculada = $tknOrder;
          $orderpay = new OrdenPagoModelo();
          $orderpay->token_ordenPago = $tknOrder;
          $orderpay->folio_ordenPago = $folioOrden; //falta generar
          $orderpay->fecha_sistema_ordenp = time();
          $orderpay->fecha_contabilizacion_ordenPago =  $anticipo_fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($anticipo_fecha_contabilizacion) : NULL;
          $orderpay->ord_deudor = $ident_deudor;
          $orderpay->ord_anticipo = $anticipo_uuid;
          $orderpay->doc_anterior_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($anticipo_fecha_contabilizacion);
          $orderpay->orden_bloqueada = FALSE;
          $orderpay->autorizacion_pay = TRUE;
          $orderpay->fecha_autorizacion_pay = time();
          $orderpay->tentativa_pago = time();
          $orderpay->orden_terminada_bool = TRUE;
          $orderpay->orden_terminada_fecha = time();
          $orderpay->status_ordenPago = TRUE;  //cifrado
          $orderpay->empresa = $vEmp->id; //cifrado
          $orderpay->comprador = $vEmp->userr; //cifrado
          $insertOrder = $orderpay->save();
  
          //$folioPagos = DB::select("SELECT IF (max(folio_pagos) IS NOT NULL,(max(folio_pagos)+1),1) AS folio FROM fnzs_pagos_pago AS payment JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
          //  JOIN teci_usuarios_catalogo AS users WHERE payment.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
          //  [$empresa, $usuario]
          //);
          
          $maxFolioPagosPago = DB::table('fnzs_pagos_pago')->where('empresa', $vEmp->id)->lockForUpdate()->max('folio_pagos');
          $folioPagos = $maxFolioPagosPago ? $maxFolioPagosPago + 1 : 1;
          $folio_pago_generar = "PAY-".$JwtAuth->generarFolio($folioPagos);
          $tokenPago = $JwtAuth->encriptarToken($anticipo_importe.$anticipo_comentarios.$ident_deudor);
  
          $personal_main = DB::table("vhum_empleados_catalogo AS pers")
          ->join("main_empresa_usuario AS empuser", "pers.id", "=", "empuser.empleado")
          ->join("main_empresas AS emp", "empuser.empresa", "=", "emp.id")
          ->where("emp.empresa_token",$empresa)->value("pers.id");
  
          DB::table("fnzs_pagos_pago")
          ->insert(
            array(
              "token_pagos" => $tokenPago,
              "folio_pagos" => $folioPagos,
              "folio_operacion" => "",
              "fecha_sistema" => time(),
              "fecha_pago" => time(),
              "fecha_contabilizacion" => $anticipo_fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($anticipo_fecha_contabilizacion) : NULL,
              "monto_pago" => $anticipo_importe,
              "observacionesPago" => $JwtAuth->encriptar($anticipo_comentarios),
              "tipo_cambio" => $anticipo_tipo_cambio_number,
              "p_moneda" => $anticipo_moneda,
              "forma_pago_pago" => $ant_fpago,
              "vinc_deudor" => $ident_deudor,
              "concepto" => $JwtAuth->encriptar("Pago por concepto de anticipo"),
              "personal_pago" => $personal_main,
              "pago_autorizado" => TRUE,
              "fecha_pago_auth" => time(),
              "personal_autoriza" => $personal_main,
              "empresa" => $vEmp->id,
              "status_pagos" => TRUE,
              "fecha_deletePagos" => ''
            )
          );
  
          $id_pago_realizado = DB::table("fnzs_pagos_pago")->where("token_pagos",$tokenPago)->value("id");
          $id_ord_pago = $orderpay->id;
  
          DB::table("fnzs_pagos_pago_ordenes_vinculadas")
          ->insert(array(
            "pago_realizado" => $id_pago_realizado,
            "orden_pago_vinculada" => $id_ord_pago,
            "orden_pago_monto" => $anticipo_importe
          ));
  
          //$folioMovimientos = DB::select("SELECT IF (max(deumov.folio_deu_mov) IS NOT NULL,(max(deumov.folio_deu_mov)+1),1) AS folio FROM fnzs_catalogo_deudores_movimientos AS deumov JOIN main_empresas AS emp 
          //  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE deumov.deu_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
          //  [$empresa, $usuario]
          //);

          $maxFolioDeuMov = DB::table('fnzs_catalogo_deudores_movimientos')->where('deu_empresa', $vEmp->id)->lockForUpdate()->max('folio_deu_mov');
          $folioMovimientos = $maxFolioDeuMov ? $maxFolioDeuMov + 1 : 1;
          $folio_pago_generar = "DEUMOV-".$JwtAuth->generarFolio($folioMovimientos);
          $tokenDeuMov = $JwtAuth->encriptarToken($anticipo_importe.$anticipo_comentarios.time());
  
          DB::table("fnzs_catalogo_deudores_movimientos")
          ->insert(
            array(
              "token_deu_mov" => $tokenDeuMov,
              "folio_deu_mov" => $folioMovimientos,
              "deu_fecha_registro" => time(),
              "deu_fecha_contabilizacion" => $anticipo_fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($anticipo_fecha_contabilizacion) : NULL,
              "condicion_deu_mov" => "S",
              "deu_monto_mov" => $anticipo_importe,
              "deu_observaciones_mov" => $JwtAuth->encriptar($anticipo_comentarios),
              "deu_tipo_cambio" => $anticipo_tipo_cambio_number,
              "deu_mov_moneda" => $anticipo_moneda,
              "vinc_deudor" => $ident_deudor,
              "deu_personal_mov" => $vEmp->userr,
              "deu_mov_autorizado" => TRUE,
              "deu_fecha_mov_auth" => time(),
              "deu_personal_autoriza" => $vEmp->userr,
              "deu_empresa" => $vEmp->id,
              "deu_status_mov" => TRUE,
            )
          );
  
          $id_mov_realizado = DB::table("fnzs_catalogo_deudores_movimientos")->where("token_deu_mov",$tokenDeuMov)->value("id");
  
          if ($valide_caja && count($anticipo_caja) > 0) {
            for ($i=0; $i < count($anticipo_caja); $i++) { 
              $token_caja = $anticipo_caja[$i]["token_caja"];
              $monto_aplicar = $anticipo_caja[$i]["monto_aplicar"];
              $sql_caja = DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id");
              $insertPagoCaja = DB::table("fnzs_catalogo_deudores_movimientos_cajas")
              ->insert(
                array(
                  "mov_realizado" => $id_mov_realizado,
                  "caja_relacionada" => $sql_caja
                )
              );
  
              //$folioMovimiento = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS mov JOIN main_empresas AS emp 
              //  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mov.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
              //  AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

              $maxFolioActMov = DB::table('fnzs_actividad_movimientos')->where('empresa', $vEmp->id)->lockForUpdate()->max('folio_movimiento');
              $folioMovActividad = $maxFolioActMov ? $maxFolioActMov + 1 : 1;
              $token_movimiento = $JwtAuth->encriptarToken($id_mov_realizado,$sql_caja,$folioMovActividad);
  
              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovActividad,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $anticipo_fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($anticipo_fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => "R",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "caja" => $sql_caja,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $anticipo_tipo_cambio_number,
                  "moneda_movimiento" => $anticipo_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($anticipo_comentarios),
                  "deudor_movimiento" => $id_mov_realizado,
                  "empresa" => $vEmp->id
                )
              );
  
            }
          }
  
          if ($valide_cuenta_bancaria && count($anticipo_cuenta_bancaria) > 0) {
            for ($i=0; $i < count($anticipo_cuenta_bancaria); $i++) { 
              $token_cuenta = $anticipo_cuenta_bancaria[$i]["token_cuenta"];
              $monto_aplicar = $anticipo_cuenta_bancaria[$i]["monto_aplicar"];
              $sql_cuenta_bancaria = DB::table("fnzs_catalogos_cuentas")->where("token_cuenta",$token_cuenta)->value("id");
              $insertPagoCuenta = DB::table("fnzs_catalogo_deudores_movimientos_cuentas")
              ->insert(
                array(
                  "mov_realizado" => $id_mov_realizado,
                  "cuenta_relacionada" => $sql_cuenta_bancaria
                )
              );
  
              $maxFolioActMov = DB::table('fnzs_actividad_movimientos')->where('empresa', $vEmp->id)->lockForUpdate()->max('folio_movimiento');
              $folioMovActividad = $maxFolioActMov ? $maxFolioActMov + 1 : 1;
              $token_movimiento = $JwtAuth->encriptarToken($id_mov_realizado,$sql_cuenta_bancaria,$folioMovActividad);
  
              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovActividad,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $anticipo_fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($anticipo_fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => "R",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "cuenta_bancaria" => $sql_cuenta_bancaria,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $anticipo_tipo_cambio_number,
                  "moneda_movimiento" => $anticipo_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($anticipo_comentarios),
                  "deudor_movimiento" => $id_mov_realizado,
                  "empresa" => $vEmp->id
                )
              );
            }
          }
  
          if ($valide_cuenta_monedero && count($anticipo_cuenta_monedero) > 0) {
            for ($i=0; $i < count($anticipo_cuenta_monedero); $i++) { 
              $token_cuentaMon = $anticipo_cuenta_monedero[$i]["token_cuentaMon"];
              $monto_aplicar = $anticipo_cuenta_monedero[$i]["monto_aplicar"];
              $sql_cuenta_monedero = DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero",$token_cuentaMon)->value("id");
  
              $insertPagoCuenta = DB::table("fnzs_catalogo_deudores_movimientos_monederos")
              ->insert(
                array(
                  "mov_realizado" => $id_mov_realizado,
                  "cuenta_relacionada" => $sql_cuenta_monedero
                )
              );
  
              $maxFolioActMov = DB::table('fnzs_actividad_movimientos')->where('empresa', $vEmp->id)->lockForUpdate()->max('folio_movimiento');
              $folioMovActividad = $maxFolioActMov ? $maxFolioActMov + 1 : 1;
  
              $token_movimiento = $JwtAuth->encriptarToken($id_mov_realizado,$sql_caja,$folioMovActividad);
  
              $insertPagoMovimiento = DB::table("fnzs_actividad_movimientos")
              ->insert(
                array(
                  "token_movimiento" => $token_movimiento,
                  "folio_movimiento" => $folioMovActividad,
                  "fecha_sistema" => time(),
                  "fecha_contabilizacion_movimiento" => $anticipo_fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($anticipo_fecha_contabilizacion) : NULL,
                  "tipo_movimiento" => "R",
                  "subtipo_movimiento" => "C",
                  "responsable" => $vEmp->userr,
                  "cuenta_monedero" => $sql_cuenta_monedero,
                  "monto_aplicado" => $monto_aplicar,
                  "tipo_cambio_movimiento" => $anticipo_tipo_cambio_number,
                  "moneda_movimiento" => $anticipo_moneda,
                  "observaciones_movimiento" => $JwtAuth->encriptar($anticipo_comentarios),
                  "deudor_movimiento" => $id_mov_realizado,
                  "empresa" => $vEmp->id
                )
              ); 
            }
          }
          
          $insertPagoVinc = DB::table("fnzs_catalogo_deudores_movimientos_pagos_vinculados")
          ->insert(array(
            "mov_realizado" => $id_mov_realizado,
            "pago_vinculado" => DB::table("fnzs_pagos_pago")->where("token_pagos",$tokenPago)->value("id"),
            "mov_pago_monto" => $anticipo_importe
          ));
  
          $fecha_sistema_mov = DB::table("fnzs_catalogo_deudores_movimientos")->where("token_deu_mov",$tokenDeuMov)->value("deu_fecha_registro");
          $filepath = $vEmp->root_tkn . "/0003-fnzs/acreedores/movimientos/$fecha_sistema_mov-$folio_pago_generar/pago_evidencias/";
          if (!file_exists(storage_path("/root/$filepath"))) {
            Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
          }
          //"orden_pago" => $id_ord_pago,
          if (!empty($_FILES['evidencias_anticipo'])) {
            $evidencias = $_FILES["evidencias_anticipo"];
            $string_name_evid = json_encode($_FILES["evidencias_anticipo"]["name"]);
            if (count(json_decode($string_name_evid)) != 0) {
              $evidencia_nombre = json_decode($string_name_evid);
              for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                $temporal = $evidencias["tmp_name"][$doc];
                $doc_name = $evidencias["name"][$doc];
                Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);
  
                $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%ANT-EVID%'");
                $token_documento = $JwtAuth->encriptarToken($id_mov_realizado,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                $insertDocSoli = DB::table("sos_documentos")->insert(
                  array(
                    "token_documento" => $token_documento,
                    "fecha_carga" => time(),
                    "modulo" => "pagos",
                    "folio_modulo" => "ANT-EVID" . $select_folio_doc[0]->folio,
                    "tipo_documento" => "an",
                    "nombre_documento" => $JwtAuth->encriptar($doc_name),
                    "deudor_movimiento" => $id_mov_realizado,
                    "status_documento" => TRUE,
                  )
                );
                //return response()->json(['message' => 'pais5'.$doc_name,'codigo' => 200,'status' => 'error']);
              }
            }
          }
  
          DB::commit();
          
          return response()->json([
            'status'  => 'success',
            'code'    => 200,
            'message' => '¡Pago realizado existosamente, revise su información y comuníquese con al área correspondiente al pago realizado!'
          ]);
        } catch (\Exception $e) {
          DB::rollBack();
          return response()->json([
            'status'  => 'error',
            'code'    => 500,
            'message' => 'Error interno al procesar la actualización: ' . $e->getMessage()
          ], 500);
        }
      } else {
        if (!$valide_anticipo_uuid) $mensaje_error = "Error en anticipo solicitado, verifique su información o comuníquese a soporte";
        if (!$valide_anticipo_proveedor) $mensaje_error = "Error en proveedor seleccionado, verifique su información o comuníquese a soporte";
        if (!$valide_fecha_contabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información o comuníquese a soporte";
        if (!$valide_moneda || !$valide_moneda_decimales) $mensaje_error = "Error en moneda seleccionada, verifique su información o comuníquese a soporte";
        if (!$valide_importe) $mensaje_error = "Error en importe de pago, verifique su información o comuníquese a soporte";
        if (!$valide_tipo_cambio) $mensaje_error = "Error en tipo de cambio, verifique su información o comuníquese a soporte";
        if (!$valide_forma_pago) $mensaje_error = "Error en forma de pago seleccionada, verifique su información o comuníquese a soporte";
        if (!$valide_comentarios) $mensaje_error = "Error en observaciones finales, verifique su información o comuníquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function anticipoProveedorList(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Datos de validación incorrectos',
				'errors' => $validate->errors()
			);
    } else {
      $token_cat_proveedores = $request->input('token_cat_proveedores');

      $queryAnticipos = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
      ->join("eegr_catalogo_proveedores AS catprv", "ant.proveedor", "catprv.id")
      ->join("fnzs_pagos_orden AS order", "ant.uuid_anticipo", "order.anticipo_proveedor")
      ->join("main_empresas AS emp", "ant.empresa", "emp.id")
      ->where([
        "ant.estatus_anticipo" => TRUE, 
        "catprv.token_cat_proveedores" => $token_cat_proveedores, 
        "emp.empresa_token" => $empresa
      ])
      ->orderBy("ant.fecha_registro", "DESC")
      ->get();
      
      if ($queryAnticipos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron anticipos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $listaAnticipos = array();

        foreach ($queryAnticipos as $vAnt) {
          //da_te_default_timezone_set($vAnt->zona_horaria);
          $queryApplicacionAnticipo = DB::table("eegr_catalogo_proveedores_anticipo_aplicacion")->where("anticipo_registrado", $vAnt->uuid_anticipo)->get();
          $anticipoTotalAplicado = $queryApplicacionAnticipo->sum('monto_total_anticipo');
          $monto_real = $vAnt->monto_total - $anticipoTotalAplicado;
          $proveedor_anticipo_total += ($monto_real * $vAnt->tipo_cambio);

          $row = array(
            "uuid_anticipo" => $vAnt->uuid_anticipo,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($vAnt->fecha_registro),
            "folio" => 'ANT-'.$JwtAuth->generarFolio($vAnt->folio_anticipo),
            "fecha_aplicacion" => $JwtAuth->mostrarUnixAFechaMexico($vAnt->fecha_aplicacion),
            "forma_pago" => $vAnt->forma_pago_anticipo,
            "moneda_code" => $vAnt->moneda_code,
            "moneda_decimales" => $vAnt->moneda_decimales,
            "tipo_cambio" => $vAnt->tipo_cambio,
            "cantidad_anticipo" => $vAnt->monto_total * $vAnt->tipo_cambio,
            "cantidad_anticipo_format" => "$" . number_format($vAnt->monto_total * $vAnt->tipo_cambio, $vAnt->moneda_decimales, '.', ',') . ' ' . $vAnt->moneda_code,
            "monto_real" => $monto_real,
            "monto_real_format" => "$" . number_format($monto_real * (!empty($vSal->tipo_cambio) ? $vAnt->tipo_cambio : 1.00), $vAnt->moneda_decimales, '.', ',') . ' ' . $vAnt->moneda_code,
            "observaciones" => $JwtAuth->desencriptar($vAnt->observaciones),
            "select_for_pagos" => false,
            "disponible" => $vAnt->disponible,
          );
          $listaAnticipos[] = $row;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'anticipo_total' => "$" . number_format($proveedor_anticipo_total, $JwtAuth->getMonedaAPI("MXN"), '.', ',') . ' MXN',
          'anticipos_registrados' => $listaAnticipos
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function anticipoProveedorDisponibleList(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Datos de validación incorrectos',
				'errors' => $validate->errors()
			);
    } else {
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      
      $queryAnticipos = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
      ->join("fnzs_pagos_orden AS order", "ant.uuid_anticipo", "order.ord_anticipo")
      ->join("fnzs_pagos_pago_ordenes_vinculadas AS opvinc", "order.id", "opvinc.orden_pago_vinculada")
      ->join("fnzs_pagos_pago AS pag", "opvinc.pago_realizado", "pag.id")
      ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS pvinc", "pag.id", "pvinc.pago_vinculado")
      ->join("fnzs_catalogo_deudores_movimientos AS mov", "pvinc.mov_realizado", "mov.id")
      ->join("fnzs_catalogo_deudores AS catDeu", "ant.ant_deudor_vinculado", "catDeu.id")
      ->join("eegr_catalogo_proveedores AS catprv", "catDeu.deu_proveedor_vinculado", "catprv.id")
      ->join("main_empresas AS emp", "catprv.administrador", "emp.id")
      ->where([
        "order.orden_terminada_bool" => TRUE,
        "ant.estatus_anticipo" => TRUE,
        "ant.disponible" => TRUE,
        "mov.condicion_deu_mov" => "S",
        "catprv.token_cat_proveedores" => $token_cat_proveedores,
        "emp.empresa_token" => $empresa
      ])
      ->orderBy("ant.fecha_registro", "DESC")
      ->get();
      
      $queryMovimientos = DB::table("fnzs_catalogo_deudores_movimientos AS mov")
      ->leftJoin("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS vinc","mov.id","=","vinc.mov_realizado")
      ->leftJoin("fnzs_pagos_pago AS pago","vinc.pago_vinculado","=","pago.id")
      ->join("fnzs_catalogo_deudores AS catDeu","mov.vinc_deudor","=","catDeu.id")
      ->join("eegr_catalogo_proveedores AS catprv","catDeu.deu_proveedor_vinculado","=","catprv.id")
      ->join("main_empresas AS emp", "catprv.administrador", "emp.id")
      ->where("mov.condicion_deu_mov","R")
      ->where([
        "catprv.token_cat_proveedores" => $token_cat_proveedores,
        "emp.empresa_token" => $empresa
      ])
      ->select([
        "mov.token_deu_mov AS token_deu_mov",
        DB::raw("'MOVIMIENTO' AS tipo_registro_e_cuenta"),
        "mov.deu_fecha_registro AS f_reg_mov",
        DB::raw("'' AS token_pagos"),
        "mov.token_deu_mov AS id_registro",
        "mov.folio_deu_mov AS folio_movimiento",
        "mov.deu_fecha_contabilizacion AS fecha_contabilizacion",
        "mov.deu_observaciones_mov AS observaciones",
        DB::raw("'---' AS forma_pago_pago"),
        "mov.deu_monto_mov AS monto_movimiento",
        "mov.deu_tipo_cambio AS tipo_cambio_movimiento",
        "mov.deu_mov_moneda AS moneda_movimiento",
        "mov.id AS movimiento_id",
      ])->get();
      
      if ($queryAnticipos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron anticipos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $listaAnticipos = array();
        $proveedor_anticipo_moneda = "";
        $proveedor_anticipo_total = 0;

        foreach ($queryAnticipos as $vAnt) {
          //echo $vAnt->ant_deudor_vinculado;
          //da_te_default_timezone_set($vAnt->zona_horaria);
          $proveedor_anticipo_moneda = $vAnt->p_moneda;
          $queryApplicacionAnticipo = DB::table("eegr_catalogo_proveedores_anticipo_aplicacion")->where("anticipo_registrado", $vAnt->uuid_anticipo)->get();
          $anticipoTotalAplicado = $queryApplicacionAnticipo->sum('monto_total_anticipo');
          $monto_real = $vAnt->monto_total - $anticipoTotalAplicado;          
          $proveedor_anticipo_total += ($monto_real * $vAnt->tipo_cambio);

          $row = array(
            "uuid_anticipo" => $vAnt->uuid_anticipo,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($vAnt->fecha_registro),
            "anticipo_folio" => 'ANT-'.$JwtAuth->generarFolio($vAnt->folio_anticipo),
            "anticipo_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vAnt->ant_fecha_contabilizacion),
            "fecha_aplicacion" => $JwtAuth->mostrarUnixAFechaMexico($vAnt->fecha_aplicacion),
            "forma_pago" => $vAnt->forma_pago_anticipo,
            "moneda_code" => $vAnt->moneda_code,
            "moneda_decimales" => $vAnt->moneda_decimales,
            "anticipo_tipo_cambio" => $vAnt->tipo_cambio,
            "anticipo_tipo_cambio_format" => "$" . number_format($vAnt->tipo_cambio, $vAnt->moneda_decimales, '.', ',') . ' ' . $vAnt->moneda_code,

            "cantidad_anticipo" => $vAnt->monto_total * $vAnt->tipo_cambio,
            "cantidad_anticipo_format" => "$" . number_format($vAnt->monto_total * $vAnt->tipo_cambio, $vAnt->moneda_decimales, '.', ',') . ' ' . $vAnt->moneda_code,
            "monto_real" => $monto_real,
            "monto_real_format" => "$" . number_format($monto_real * (!empty($vSal->tipo_cambio) ? $vAnt->tipo_cambio : 1.00), $vAnt->moneda_decimales, '.', ',') . ' ' . $vAnt->moneda_code,
            "observaciones" => $JwtAuth->desencriptar($vAnt->observaciones),
            "select_for_pagos" => false,
          );
          $listaAnticipos[] = $row;
        }

        //echo count($queryMovimientos);
        foreach ($queryMovimientos as $vMov) {
          $proveedor_anticipo_total -= $vMov->monto_movimiento;
        }
        
        $anticipo_total_format = number_format($proveedor_anticipo_total, $JwtAuth->getMonedaAPI($proveedor_anticipo_moneda), '.', ',') . " $proveedor_anticipo_moneda";
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'anticipo_total' => $proveedor_anticipo_total,
          'anticipo_total_format' => "$".($proveedor_anticipo_total > 0 ? $anticipo_total_format : "0.00 MXN"),
          //'anticipos_registrados' => $listaAnticipos
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function anticipoProveedorRegist(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_proveedores' => 'required|string',
      'fecha_contabilizacion' => 'required|string',
      'forma_pago' => 'required|string',
      'moneda_codigo' => 'required|string',
      'moneda_decimales' => 'required|string',
      'tipo_cambio' => 'required|string',
      'cantidad_anticipo' => 'required|string',
      'observaciones' => 'required|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Datos de validación incorrectos',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $fecha_registro = time();
      //QRCode::text($parametrosArray['user_token'])->setOutfile(Storage::path('public/root/QRPersonal.png'))->png();
      $token_cat_proveedores = $request->input('token_cat_proveedores');
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $forma_pago = $request->input('forma_pago');
      $moneda_codigo = $request->input('moneda_codigo');
      $moneda_decimales = $request->input('moneda_decimales');
      $tipo_cambio = $request->input('tipo_cambio');
      $cantidad_anticipo = $request->input('cantidad_anticipo');
      $observaciones = $request->input('observaciones');

      $valida_prov = isset($token_cat_proveedores) && !empty($token_cat_proveedores);
      $valida_aplicacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroAlfaNumerico(), $fecha_contabilizacion);
      $valida_fpago = isset($forma_pago) && !empty($forma_pago) && preg_match($JwtAuth->filtroAlfaNumerico(), $forma_pago);
      $valida_moncod = isset($moneda_codigo) && !empty($moneda_codigo) && preg_match($JwtAuth->filtroAlfaNumerico(), $moneda_codigo);
      $valida_mondec = isset($moneda_decimales) && !empty($moneda_decimales) && preg_match($JwtAuth->filtroNumericoSimple(), $moneda_decimales);
      $valida_tipoc = isset($tipo_cambio) && !empty($tipo_cambio) && preg_match($JwtAuth->filtroNumericoSimple(), $tipo_cambio);
      $valida_cant = isset($cantidad_anticipo) && !empty($cantidad_anticipo) && preg_match($JwtAuth->filtroNumericoSimple(), $cantidad_anticipo);
      $valida_observ = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones);

      if ($valida_aplicacion && $valida_fpago && $valida_prov && $valida_moncod && $valida_mondec && $valida_tipoc && $valida_cant && $valida_observ) {
        $ident_empresa = DB::table("main_empresas")->where("empresa_token", $empresa)->value("id");
        $ident_usuario = DB::table("teci_usuarios_catalogo")->where("usuario_token", $usuario)->value("id");
        $ident_proveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $token_cat_proveedores)->value("id");

        $max_folio = DB::select("SELECT IF (max(ant.folio_anticipo) IS NOT NULL,(max(ant.folio_anticipo)+1),1) AS folio FROM eegr_catalogo_proveedores_anticipo AS ant 
          JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ant.empresa = emp.id AND emp.empresa_token = ?
          AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

        $folio_registrado = "ANT-" . $JwtAuth->generarFolio($max_folio[0]->folio);

        $uuid = Str::uuid()->toString();
        $encryptedUuid = $JwtAuth->encriptarToken($uuid);
        $insertAnticipo = DB::table('eegr_catalogo_proveedores_anticipo')
        ->insert(array(
          "uuid_anticipo" => $encryptedUuid,
          "fecha_registro" => $fecha_registro,
          "ant_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
          "folio_anticipo" => $max_folio[0]->folio,
          "proveedor" => $ident_proveedor,
          "forma_pago_anticipo" => $forma_pago,
          "moneda_code" => $moneda_codigo,
          "moneda_decimales" => $moneda_decimales,
          "tipo_cambio" => $tipo_cambio,
          "monto_total" => $cantidad_anticipo,
          "observaciones" => $JwtAuth->encriptar($observaciones),
          "estatus_anticipo" => TRUE,
          "empresa" => $ident_empresa,
          "usuario" => $ident_usuario
        ));

        if ($insertAnticipo) {
          $dataMensaje = array('status' => 'success', 'code' => 200, 'message' => "Anticipo registrado exitosamente con folio $folio_registrado");
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => "Error al registrar anticipo, intentelo nuevamente o comuniquese a soporte");
        }
      } else {
        $mensaje_error = "";
        if (!$valida_aplicacion) $mensaje_error = "Error al registrar fecha de contabilización, intentelo nuevamente o comuniquese a soporte";
        if (!$valida_fpago) $mensaje_error = "Error al seleccionar forma de pago, intentelo nuevamente o comuniquese a soporte";
        if (!$valida_prov) $mensaje_error = "Error al seleccionar proveedor, intentelo nuevamente o comuniquese a soporte";
        if (!$valida_moncod) $mensaje_error = "Error al seleccionar moneda (Código de moneda), intentelo nuevamente o comuniquese a soporte";
        if (!$valida_mondec) $mensaje_error = "Error al seleccionar moneda (decimales), intentelo nuevamente o comuniquese a soporte";
        if (!$valida_tipoc) $mensaje_error = "Error al registrar tipo de cambio, intentelo nuevamente o comuniquese a soporte";
        if (!$valida_cant) $mensaje_error = "Error al registrar importe de anticipo, intentelo nuevamente o comuniquese a soporte";
        if (!$valida_observ) $mensaje_error = "Error al registrar observaciones, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
