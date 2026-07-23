<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ActivosFijosModelo;
use Illuminate\Support\Str;

class CONT_ActivosFijosDeprecController extends Controller{
  public function procesaActivoFijoLista($dataActivos,$JwtAuth){
    $activos_procesados = array();
    $periodos = [86400 => 'Por día',604800 => 'Por semana',2629743 => 'Por mes',31556926 => 'Por año'];

    $contador = 1;
    foreach ($dataActivos as $vActivos) {
      //da_te_default_timezone_set($vActivos->zona_horaria);
      $deprec_contable_importe = $vActivos->deprec_contable_tipo == 'cuota' ? "$".number_format($vActivos->deprec_contable_importe,$JwtAuth->getMonedaAPI('MXN'),'.', ',')." MXN" : number_format($vActivos->deprec_contable_importe,$JwtAuth->getMonedaAPI('MXN'),'.','').'%';
      $deprec_fiscal_importe = $vActivos->deprec_fiscal_tipo == 'cuota' ? "$".number_format($vActivos->deprec_fiscal_importe,$JwtAuth->getMonedaAPI('MXN'),'.', ',')." MXN" : number_format($vActivos->deprec_fiscal_importe,$JwtAuth->getMonedaAPI('MXN'),'.','').'%';

      $detalles_relacionados = DB::table('eegr_activos_fijos_detalle AS actdet')
      ->join("eegr_activos_fijos_catalogo AS actf", "actdet.activo_fijo", "=", "actf.id")
      ->where("actf.token_act_fijos", $vActivos->token_act_fijos)
      ->count();

      $hay_deprec_pendiente = !is_null($vActivos->fecha_inicio_depreciacion) && ($vActivos->fecha_proximo_corte_contable <= time() || $vActivos->fecha_proximo_corte_fiscal <= time());

      $arrayEach = array(
        "num_act" => $contador,
        "token_act_fijos" => $vActivos->token_act_fijos,
        "token_det_activo_fijo" => $vActivos->token_det_activo_fijo,
        "token_activof_unidad" => $vActivos->token_activof_unidad,
        "folio_activo" => "ACTF-".$JwtAuth->generarFolio($vActivos->folio_activo),
        "fechaAlta" => $JwtAuth->mostrarUnixAFechaMexico($vActivos->fechaAlta),
        "categoria" => $JwtAuth->desencriptar($vActivos->categoria),
        "categoria_cuenta_contable" => $vActivos->categoria_cuenta_contable,

        "articulo" => $JwtAuth->desencriptar($vActivos->concepto),
        "folio_activof_unidad" => $vActivos->folio_activof_unidad,
        "cantidad" => $vActivos->cantidad_recibida,
        "unidad_medida" => $vActivos->unidad_medida_recibida,
        "fecha_recep" => $JwtAuth->mostrarUnixAFechaMexico($vActivos->fecha_recep),
        "folio_recep" => $JwtAuth->generarFolio($vActivos->folio_recep),
        "unidad_observaciones" => !is_null($vActivos->unidad_observaciones) ? $JwtAuth->desencriptar($vActivos->unidad_observaciones) : '',
        "fecha_inicio_depreciacion" => !is_null($vActivos->fecha_inicio_depreciacion) ? $JwtAuth->mostrarUnixAFechaMexico($vActivos->fecha_inicio_depreciacion) : '',
        "fecha_iniciar_depreciacion" => '',
        
        "deprec_contable_tipo" => $vActivos->deprec_contable_tipo,
        "deprec_contable_periodo" => $periodos[$vActivos->deprec_contable_periodo] ?? '',
        "deprec_contable_importe" => $deprec_contable_importe,
        "deprec_contable_cuenta" => $vActivos->deprec_contable_cuenta,
        "deprec_contable_cuenta_dos" => $vActivos->deprec_contable_cuenta_dos,
        "deprec_fiscal_tipo" => $vActivos->deprec_fiscal_tipo,
        "deprec_fiscal_periodo" => $periodos[$vActivos->deprec_fiscal_periodo] ?? '',
        "deprec_fiscal_importe" => $deprec_fiscal_importe,
        "deprec_fiscal_cuenta" => $vActivos->deprec_fiscal_cuenta,
        "deprec_fiscal_cuenta_dos" => $vActivos->deprec_fiscal_cuenta_dos,
        "activo_observaciones" => $JwtAuth->desencriptar($vActivos->activo_observaciones),
        "puede_eliminar" => $detalles_relacionados == 0 ? true : false,
        "hay_deprec_pendiente" => $hay_deprec_pendiente,
        "depreciacion_bloqueada" => (bool)$vActivos->depreciacion_bloqueada,
        "date_bloqueo_desbloqueo_prorrateo" => $JwtAuth->mostrarUnixAFechaMexico($vActivos->date_bloqueo_desbloqueo_prorrateo),
      );
      ++$contador;
      $activos_procesados[] = $arrayEach;
    }
    return $activos_procesados;
  }

  public function contGetActivosFijos(Request $request){
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
      
      $queryActivos = ActivosFijosModelo::join("eegr_activos_fijos_detalle AS actfDet","eegr_activos_fijos_catalogo.id","=","actfDet.activo_fijo")
      ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
      ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
      ->join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_activos_fijos_catalogo.activo_status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("eegr_activos_fijos_catalogo.fechaAlta", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('eegr_activos_fijos_catalogo.id', 'DESC')
      ->get();

      if ($queryActivos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'datosActivo' => $this->procesaActivoFijoLista($queryActivos,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function activoFijoRegistraFechaDepreciacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_activof_unidad' => 'required|string',
      'fecha_iniciar_depreciacion' => 'required|date_format:Y-m-d',
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
      $token_activof_unidad = $request->input('token_activof_unidad');
      $fecha_iniciar_depreciacion = $request->input('fecha_iniciar_depreciacion');

      $queryActivos = ActivosFijosModelo::join("eegr_activos_fijos_detalle AS actfDet","eegr_activos_fijos_catalogo.id","=","actfDet.activo_fijo")
      ->join("eegr_compras_detalle AS detBuy","actfDet.compra_detalle","=","detBuy.id")
      ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
      ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
      ->join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'actfUnid.token_activof_unidad' => $token_activof_unidad,
        'eegr_activos_fijos_catalogo.activo_status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->select('detBuy.precio_unitario','eegr_activos_fijos_catalogo.deprec_contable_periodo','eegr_activos_fijos_catalogo.deprec_fiscal_periodo')
      ->first();
  
      if (!$queryActivos) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $epoc_inicio_depreciacion = $JwtAuth->convierteFechaEpoc($fecha_iniciar_depreciacion);
        $deprec_contable_periodo = $queryActivos->deprec_contable_periodo;
        $deprec_fiscal_periodo = $queryActivos->deprec_fiscal_periodo;
        
        $mejoras = DB::table("eegr_compras_prorrateos_incrementos AS prorratInc")
        ->join("eegr_activos_fijos_unidades AS actfUnid", "prorratInc.activof_unidad", "=", "actfUnid.id")
        ->join("main_empresas AS emp", "prorratInc.empresa", "=", "emp.id")
        ->where('prorratInc.fecha_contabilizacion_incremento','>',$fecha_iniciar_depreciacion)
        ->where([
          'actfUnid.token_activof_unidad' => $token_activof_unidad,
          'emp.empresa_token' => $empresa
        ])
        ->sum('prorratInc.incremento_monto');

        $monto_original_inicial = $queryActivos->precio_unitario + $mejoras;

        $upDateUnidadActivo = DB::table("eegr_activos_fijos_unidades")
        ->where('token_activof_unidad',$token_activof_unidad)
        ->limit(1)->update(array(
          "costo_adquisicion" => $monto_original_inicial,
          "fecha_inicio_depreciacion" => $epoc_inicio_depreciacion,
          "fecha_ultimo_corte_contable" => $epoc_inicio_depreciacion,
          "fecha_proximo_corte_contable" => $epoc_inicio_depreciacion + $deprec_contable_periodo,
          "fecha_ultimo_corte_fiscal" => $epoc_inicio_depreciacion,
          "fecha_proximo_corte_fiscal" => $epoc_inicio_depreciacion + $deprec_fiscal_periodo,
        ));

        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'message' => "Fecha de inicio de depreciación registrada con exito"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function activoFijoBloqueaDepreciacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_activof_unidad' => 'required|string',
      'fecha_bloqueo_deprec' => 'required|date_format:Y-m-d',
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
      $token_activof_unidad = $request->input('token_activof_unidad');
      $fecha_bloqueo_deprec = $JwtAuth->convierteFechaEpoc($request->input('fecha_bloqueo_deprec'));

      $queryActivos = ActivosFijosModelo::join("eegr_activos_fijos_detalle AS actfDet","eegr_activos_fijos_catalogo.id","=","actfDet.activo_fijo")
      ->join("eegr_compras_detalle AS detBuy","actfDet.compra_detalle","=","detBuy.id")
      ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
      ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
      ->join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'actfUnid.token_activof_unidad' => $token_activof_unidad,
        'eegr_activos_fijos_catalogo.activo_status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->get();
  
      if (!$queryActivos) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $fUNIid = DB::table('eegr_activos_fijos_unidades')->where('token_activof_unidad',$token_activof_unidad)->value("id");
        $main_empresa_id = DB::table('main_empresas')->where('empresa_token',$empresa)->value("id");

        $queryBloqueo = DB::table("eegr_activos_fijos_unidades")
        ->where('token_activof_unidad',$token_activof_unidad)
        ->limit(1)->update(array(
          "depreciacion_bloqueada" => TRUE,
          "date_bloqueo_desbloqueo_prorrateo" => $fecha_bloqueo_deprec,
        ));
        
        if ($queryBloqueo) {
          DB::table('eegr_activos_fijos_depreciaciones')
          ->insert(array(                
            'token_activof_deprec' => Str::uuid(),
            'activof_unidad' => $fUNIid,
            'deprec_concepto' => 'bloqueo',
            'deprec_tipo' => 'natural',
            'deprec_subtipo' => 'otro',
            'fecha_cont_deprec_periodo' => $fecha_bloqueo_deprec,
            'periodo' => $fecha_bloqueo_deprec, // El Unix Timestamp confirmado
            'importe' => 0,
            'valor_libros_final' => 0,
            'empresa' => $main_empresa_id,
            'depreciado' => 1 // Marcamos como aplicado
          ));
        }

        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'message' => "La depreciación del activo ha sido bloqueada con exito"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function activoFijoDesbloqueaDepreciacion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_activof_unidad' => 'required|string',
      'fecha_de_desbloqueo' => 'required|date_format:Y-m-d',
      'fecha_reinicio_deprec' => 'required|date_format:Y-m-d',
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
      $token_activof_unidad = $request->input('token_activof_unidad');
      $fecha_de_desbloqueo = $JwtAuth->convierteFechaEpoc($request->input('fecha_de_desbloqueo'));
      $fecha_reinicio_deprec = $JwtAuth->convierteFechaEpoc($request->input('fecha_reinicio_deprec'));

      $queryActivos = ActivosFijosModelo::join("eegr_activos_fijos_detalle AS actfDet","eegr_activos_fijos_catalogo.id","=","actfDet.activo_fijo")
      ->join("eegr_compras_detalle AS detBuy","actfDet.compra_detalle","=","detBuy.id")
      ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
      ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
      ->join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'actfUnid.token_activof_unidad' => $token_activof_unidad,
        'eegr_activos_fijos_catalogo.activo_status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->get();
  
      if (!$queryActivos) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $fUNIid = DB::table('eegr_activos_fijos_unidades')->where('token_activof_unidad',$token_activof_unidad)->value("id");
        $main_empresa_id = DB::table('main_empresas')->where('empresa_token',$empresa)->value("id");

        $queryDesbloqueo = DB::table("eegr_activos_fijos_unidades")
        ->where('token_activof_unidad',$token_activof_unidad)
        ->limit(1)->update(array(
          "depreciacion_bloqueada" => FALSE,
          "date_bloqueo_desbloqueo_prorrateo" => $fecha_de_desbloqueo,
          "fecha_proximo_corte_contable" => $fecha_reinicio_deprec,
          "fecha_proximo_corte_fiscal" => $fecha_reinicio_deprec,
        ));

        if ($queryDesbloqueo) {
          DB::table('eegr_activos_fijos_depreciaciones')
          ->insert(array(                
            'token_activof_deprec' => Str::uuid(),
            'activof_unidad' => $fUNIid,
            'deprec_concepto' => 'desbloqueo',
            'deprec_tipo' => 'natural',
            'deprec_subtipo' => 'otro',
            'fecha_cont_deprec_periodo' => $fecha_de_desbloqueo,
            'periodo' => $fecha_de_desbloqueo, // El Unix Timestamp confirmado
            'importe' => 0,
            'valor_libros_final' => 0,
            'empresa' => $main_empresa_id,
            'depreciado' => 1 // Marcamos como aplicado
          ));
        }

        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'message' => "La depreciación del activo ha sido bloqueada con exito"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function contActivoFijoDetalleToDeprec(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_activof_unidad' => 'required|string',
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
      $token_activof_unidad = $request->input('token_activof_unidad');
      $lista_pendientes = [];

      $idActivoUnid = DB::table("eegr_activos_fijos_unidades")->where("token_activof_unidad",$token_activof_unidad)->value("id");

      $queryActivo = ActivosFijosModelo::join("eegr_activos_fijos_detalle AS actfDet","eegr_activos_fijos_catalogo.id","=","actfDet.activo_fijo")
      ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
      ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
      ->join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_activos_fijos_catalogo.activo_status' => TRUE,
        'actfUnid.token_activof_unidad' => $token_activof_unidad,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->get();
  
      if ($queryActivo->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        foreach ($queryActivo as $vPend) {
          $mejoras_entre_deprec = DB::table("eegr_compras_prorrateos_incrementos AS prorratInc")
          ->join("eegr_activos_fijos_unidades AS actfUnid", "prorratInc.activof_unidad", "=", "actfUnid.id")
          ->join("main_empresas AS emp", "prorratInc.empresa", "=", "emp.id")
          ->whereBetween("prorratInc.fecha_contabilizacion_incremento", [$vPend->fecha_ultimo_corte_contable, $vPend->fecha_proximo_corte_contable])
          ->whereBetween("prorratInc.fecha_contabilizacion_incremento", [$vPend->fecha_ultimo_corte_fiscal, $vPend->fecha_proximo_corte_fiscal])
          ->where([
            'actfUnid.token_activof_unidad' => $token_activof_unidad,
            'emp.empresa_token' => $empresa
          ])
          ->sum('prorratInc.incremento_monto');
          
          $ultima_deprec_contable = DB::table("eegr_activos_fijos_depreciaciones")
          ->where([
            'activof_unidad' => $idActivoUnid, 
            'deprec_concepto' => 'depreciación', 
            'deprec_tipo' => 'natural',
            'deprec_subtipo' => 'contable'
          ])
          ->latest('id')
          ->first();
          $ultimo_deprec_importe_contable = $ultima_deprec_contable ? $ultima_deprec_contable->importe : 0;

          $ultima_deprec_fiscal = DB::table("eegr_activos_fijos_depreciaciones")
          ->where([
            'activof_unidad' => $idActivoUnid, 
            'deprec_concepto' => 'depreciación', 
            'deprec_tipo' => 'natural',
            'deprec_subtipo' => 'fiscal'
          ])
          ->latest('id')
          ->first();
          $ultimo_deprec_importe_fiscal = $ultima_deprec_fiscal ? $ultima_deprec_fiscal->importe : 0;
          
          $lista_pendientes[] = [
            "token_activof_unidad" => $vPend->token_activof_unidad,
            "folio_activof_unidad" => $vPend->folio_activof_unidad,
            "unidad_serie" => $vPend->unidad_serie,
            "unidad_otros" => $JwtAuth->desencriptar($vPend->unidad_otros),
            "unidad_observaciones" => $JwtAuth->desencriptar($vPend->unidad_observaciones),
            "status_unidad_activo" => $vPend->status_unidad_activo,
            "costo_adquisicion" => $vPend->costo_adquisicion,
            "fecha_inicio_depreciacion" => $JwtAuth->mostrarUnixAFechaMexico($vPend->fecha_inicio_depreciacion),
            "fecha_ultimo_corte_contable" => $JwtAuth->mostrarUnixAFechaMexico($vPend->fecha_ultimo_corte_contable),
            "fecha_proximo_corte_contable" => $JwtAuth->mostrarUnixAFechaMexico($vPend->fecha_proximo_corte_contable),
            "ultimo_deprec_importe_contable" => $ultimo_deprec_importe_contable,
            "fecha_ultimo_corte_fiscal" => $JwtAuth->mostrarUnixAFechaMexico($vPend->fecha_ultimo_corte_fiscal),
            "fecha_proximo_corte_fiscal" => $JwtAuth->mostrarUnixAFechaMexico($vPend->fecha_proximo_corte_fiscal),
            "ultimo_deprec_importe_fiscal" => $ultimo_deprec_importe_fiscal,
            "depreciacion_bloqueada" => $vPend->depreciacion_bloqueada,
            "date_bloqueo_desbloqueo_prorrateo" => $JwtAuth->mostrarUnixAFechaMexico($vPend->date_bloqueo_desbloqueo_prorrateo),
            "mejoras_entre_deprec" => $mejoras_entre_deprec
          ];
        }
        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'lista_pendientes' => $lista_pendientes
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function checkNotificacionesDepreciacion(Request $request) {
    $lista_pendientes = [];
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    $ahora = time();
    $JwtAuth = new \App\Helpers\JwtAuth();
    $pendientes = DB::table('eegr_activos_fijos_unidades AS actFUNI')
    ->join("main_empresas AS emp", "actFUNI.empresa", "=", "emp.id")
    //->where('estatus_contable', 'Activo')
    ->where('emp.empresa_token',$empresa)
    ->where(function ($a) {
      $a->where("fecha_proximo_corte_contable", '<=', time())
      ->orWhere(function ($b){
        $b->where("fecha_proximo_corte_fiscal", '<=', time());
      });
    })
    ->select(
      'costo_adquisicion',
      'token_activof_unidad',
      'folio_activof_unidad',
      'unidad_serie',
      'unidad_otros',
      'unidad_observaciones',
      'status_unidad_activo',
      'costo_adquisicion',
      'fecha_inicio_depreciacion',
      'fecha_ultimo_corte_contable',
      'fecha_proximo_corte_contable',
      'fecha_ultimo_corte_fiscal',
      'fecha_proximo_corte_fiscal'
    )
    ->get();
  
    foreach ($pendientes as $vPend) {
      $lista_pendientes[] = [
        "token_activof_unidad" => $vPend->token_activof_unidad,
        "folio_activof_unidad" => $vPend->folio_activof_unidad,
        "unidad_serie" => $vPend->unidad_serie,
        "unidad_otros" => $JwtAuth->desencriptar($vPend->unidad_otros),
        "unidad_observaciones" => $JwtAuth->desencriptar($vPend->unidad_observaciones),
        "status_unidad_activo" => $vPend->status_unidad_activo,
        "costo_adquisicion" => $vPend->costo_adquisicion,
        "fecha_inicio_depreciacion" => $JwtAuth->mostrarUnixAFechaMexico($vPend->fecha_inicio_depreciacion),
        "fecha_ultimo_corte_contable" => $JwtAuth->mostrarUnixAFechaMexico($vPend->fecha_ultimo_corte_contable),
        "fecha_proximo_corte_contable" => $JwtAuth->mostrarUnixAFechaMexico($vPend->fecha_proximo_corte_contable),
        "fecha_ultimo_corte_fiscal" => $JwtAuth->mostrarUnixAFechaMexico($vPend->fecha_ultimo_corte_fiscal),
        "fecha_proximo_corte_fiscal" => $JwtAuth->mostrarUnixAFechaMexico($vPend->fecha_proximo_corte_fiscal)
      ];
    }

    return response()->json([
      'total' => $pendientes->count(),
      'activos' => $lista_pendientes
    ]);
  }

  private function extraeActivoUnidad($token_activof_unidad,$empresa,$usuario) {
    return ActivosFijosModelo::join("eegr_activos_fijos_detalle AS actfDet","eegr_activos_fijos_catalogo.id","=","actfDet.activo_fijo")
    ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
    ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
    ->join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      'eegr_activos_fijos_catalogo.activo_status' => TRUE,
      'actfUnid.token_activof_unidad' => $token_activof_unidad,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])
    ->select(
      'actfUnid.id AS FUNIID',
      'actfUnid.token_activof_unidad',
      'actfUnid.costo_adquisicion',

      'actfUnid.fecha_ultimo_corte_fiscal',
      'actfUnid.fecha_proximo_corte_fiscal',
      'eegr_activos_fijos_catalogo.deprec_fiscal_tipo',
      'eegr_activos_fijos_catalogo.deprec_fiscal_periodo',
      'eegr_activos_fijos_catalogo.deprec_fiscal_importe',
      
      'actfUnid.fecha_ultimo_corte_contable',
      'actfUnid.fecha_proximo_corte_contable',
      'eegr_activos_fijos_catalogo.deprec_contable_tipo',
      'eegr_activos_fijos_catalogo.deprec_contable_periodo',
      'eegr_activos_fijos_catalogo.deprec_contable_importe'
    )
    ->first();
  }

  private function guardaDepreciacionFiscal($vActDeprec,$fecha_contabilizacion,$main_empresa_id,$observaciones,$gasto_contable_manual) {
    $valor_minimo_fiscal = 1.00;

    $lastRegistroFiscal = DB::table("eegr_activos_fijos_depreciaciones")
    ->where([
      'activof_unidad' => $vActDeprec->FUNIID, 
      'deprec_subtipo' => 'fiscal'
    ])
    ->latest('id')
    ->first();

    $es_inicial_fiscal = false;
    if ($lastRegistroFiscal && $lastRegistroFiscal->deprec_tipo === 'inicial') {
      $es_inicial_fiscal = true;
    }

    $ultimo_deprec_ajustada = DB::table("eegr_activos_fijos_depreciaciones")
    ->where(['activof_unidad' => $vActDeprec->FUNIID, 'deprec_tipo' => 'inicial', 'deprec_subtipo' => 'fiscal'])
    ->latest('id')
    ->first();

    $ultimo_deprec_inicial = DB::table("eegr_activos_fijos_depreciaciones")
    ->where(['activof_unidad' => $vActDeprec->FUNIID, 'deprec_tipo' => 'inicial', 'deprec_subtipo' => 'otro'])
    ->latest('id')
    ->first();
    
    $monto_base_calculo = 0;
    if ($ultimo_deprec_ajustada) {
      $monto_base_calculo = $ultimo_deprec_ajustada->valor_libros_final;
    } else if ($ultimo_deprec_inicial) {
      $monto_base_calculo = $ultimo_deprec_inicial->valor_libros_final;
    }

    $ultimo_valor_libros = 0;
    if ($es_inicial_fiscal) {
      $ultimo_valor_libros = $ultimo_deprec_ajustada->valor_libros_final;
    } else {
      $ultimo_registro_fiscal = DB::table("eegr_activos_fijos_depreciaciones")
      ->where(['activof_unidad' => $vActDeprec->FUNIID, 'deprec_tipo' => 'natural', 'deprec_subtipo' => 'fiscal'])
      ->latest('periodo')
      ->first();
      $ultimo_valor_libros = $ultimo_registro_fiscal ? $ultimo_registro_fiscal->valor_libros_final : $monto_base_calculo;
    }

    if ($vActDeprec->deprec_fiscal_tipo === 'cuota') {
      $gasto_mensual_teorico = $vActDeprec->deprec_fiscal_importe;
    } else {
      $fiscal_tasa_anual_decimal = $vActDeprec->deprec_fiscal_importe / 100;
      $gasto_mensual_teorico = ($monto_base_calculo * $fiscal_tasa_anual_decimal) / 12;
    }

    // 5. Controlar que no nos pasemos del valor del activo
    $pendiente_por_depreciar_fiscal = $monto_base_calculo - $valor_minimo_fiscal;
    $gasto_final = max(0, min($gasto_mensual_teorico, $pendiente_por_depreciar_fiscal));

    $valor_libros_al_cierre = $ultimo_valor_libros - $gasto_final;
    //echo $gasto_final." ".$valor_libros_al_cierre;exit;
    
    if ($gasto_final > 0) {
      DB::table('eegr_activos_fijos_depreciaciones')
      ->insert(array(                
        'token_activof_deprec' => Str::uuid(),
        'activof_unidad' => $vActDeprec->FUNIID,
        'deprec_concepto' => 'depreciación',
        'deprec_tipo' => 'natural',
        'deprec_subtipo' => 'fiscal',
        'fecha_cont_deprec_periodo' => $fecha_contabilizacion,
        'periodo' => $vActDeprec->fecha_proximo_corte_fiscal, // El Unix Timestamp confirmado
        'importe' => $gasto_contable_manual == 0 ? $gasto_final : $gasto_contable_manual,
        'valor_libros_final' => $valor_libros_al_cierre,
        'deprec_comentarios' => $observaciones,
        'empresa' => $main_empresa_id,
        'depreciado' => 1 // Marcamos como aplicado
      ));
    }
  }

  private function guardaDepreciacionContable($vActDeprec,$fecha_contabilizacion,$main_empresa_id,$observaciones,$deduccion_de_inversion_manual) {
    $valor_rescate = 0;

    $lastRegistroContable = DB::table("eegr_activos_fijos_depreciaciones")
    ->where([
      'activof_unidad' => $vActDeprec->FUNIID, 
      'deprec_subtipo' => 'contable'
    ])
    ->latest('id')
    ->first();

    $es_inicial_contable = false;
    if ($lastRegistroContable && $lastRegistroContable->deprec_tipo === 'inicial') {
      $es_inicial_contable = true;
    }


    $ultimo_deprec_ajustada = DB::table("eegr_activos_fijos_depreciaciones")
    ->where(['activof_unidad' => $vActDeprec->FUNIID, 'deprec_tipo' => 'inicial', 'deprec_subtipo' => 'contable'])
    ->latest('id')
    ->first();

    $ultimo_deprec_inicial = DB::table("eegr_activos_fijos_depreciaciones")
    ->where(['activof_unidad' => $vActDeprec->FUNIID, 'deprec_tipo' => 'inicial'])
    ->latest('id')
    ->first();

    $monto_base_calculo = 0;
    if ($ultimo_deprec_ajustada) {
      $monto_base_calculo = $ultimo_deprec_ajustada->valor_libros_final;
    } else if ($ultimo_deprec_inicial) {
      $monto_base_calculo = $ultimo_deprec_inicial->valor_libros_final;
    }
    
    $monto_original_activo = $monto_base_calculo - $valor_rescate;

    $ultimo_valor_libros = 0;
    if ($es_inicial_contable) {
      $ultimo_valor_libros = $ultimo_deprec_ajustada->valor_libros_final;
    } else {
      $ultimo_registro_contable = DB::table("eegr_activos_fijos_depreciaciones")
      ->where(['activof_unidad' => $vActDeprec->FUNIID, 'deprec_tipo' => 'natural', 'deprec_subtipo' => 'contable'])
      ->latest('periodo')
      ->first();
      $ultimo_valor_libros = $ultimo_registro_contable ? $ultimo_registro_contable->valor_libros_final : $monto_base_calculo;
    }
  
    // 4. Calcular el gasto del mes sobre la base completa
    if ($vActDeprec->deprec_contable_tipo === 'cuota') {
      $gasto_mensual_teorico = $vActDeprec->deprec_contable_importe;
    } else {
      $contable_tasa_anual_decimal = $vActDeprec->deprec_contable_importe / 100;
      $gasto_mensual_teorico = ($monto_original_activo * $contable_tasa_anual_decimal) / 12;
    }
    
    // 5. Controlar que no nos pasemos del valor del activo
    $pendiente_por_depreciar_contable = $monto_original_activo;
    $gasto_final = max(0, min($gasto_mensual_teorico, $pendiente_por_depreciar_contable));
  
    // 6. Calcular valores para el cierre
    //$valor_libros_final = ($mejoras_periodo > 0 && $ultimo_valor_libros > 0 ? $monto_base_calculo : $ultimo_valor_libros) - $gasto_final;
    $valor_libros_final = $ultimo_valor_libros - $gasto_final;
    //echo $gasto_final." ".$valor_libros_final;exit;
    DB::table('eegr_activos_fijos_depreciaciones')
    ->insert(array(
      'token_activof_deprec' => Str::uuid(),
      'activof_unidad' => $vActDeprec->FUNIID,
      'deprec_concepto' => 'depreciación',
      'deprec_tipo' => 'natural',
      'deprec_subtipo' => 'contable',
      'fecha_cont_deprec_periodo' => $fecha_contabilizacion,
      'periodo' => $vActDeprec->fecha_proximo_corte_contable, // El Unix Timestamp confirmado
      'importe' => $deduccion_de_inversion_manual == 0 ? $gasto_final : $deduccion_de_inversion_manual,
      'valor_libros_final' => $valor_libros_final,
      'deprec_comentarios' => $observaciones,
      'empresa' => $main_empresa_id,
      'depreciado' => 1 // Marcamos como aplicado
    ));
  }

  private function actualizarFechasCorte($vActDeprec) {
    DB::table("eegr_activos_fijos_unidades")
    ->where('id',$vActDeprec->FUNIID)
    ->limit(1)->update(array(
      "fecha_ultimo_corte_contable" => $vActDeprec->fecha_proximo_corte_contable,
      "fecha_proximo_corte_contable" => $vActDeprec->fecha_proximo_corte_contable + $vActDeprec->deprec_contable_periodo,
      "fecha_ultimo_corte_fiscal" => $vActDeprec->fecha_proximo_corte_fiscal,
      "fecha_proximo_corte_fiscal" => $vActDeprec->fecha_proximo_corte_fiscal + $vActDeprec->deprec_fiscal_periodo,
    ));
  }

  public function storeDepreciation(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_activof_unidad' => 'required|string',
      'fecha_contabilizacion' => 'required|date_format:Y-m-d',
      'observaciones' => 'required|string',
      'gasto_contable_manual' => 'numeric',
      'deduccion_de_inversion_manual' => 'numeric'
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
      $token_activof_unidad = $request->input('token_activof_unidad');
      $fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($request->input('fecha_contabilizacion'));
      $observaciones = $JwtAuth->encriptar($request->input('observaciones'));
      $gasto_contable_manual = $request->input('gasto_contable_manual');
      $deduccion_de_inversion_manual = $request->input('deduccion_de_inversion_manual');
      $vActDeprec = $this->extraeActivoUnidad($token_activof_unidad,$empresa,$usuario);

      if (!$vActDeprec) {
        return response()->json(['status' => 'error', 'message' => 'Activo no encontrado'], 404);
      }

      $main_empresa_id = DB::table("main_empresas")->where("empresa_token",$empresa)->value("id");

      DB::beginTransaction();
      try {
        $exist_deprec = DB::table('eegr_activos_fijos_depreciaciones AS d')
        ->join('eegr_activos_fijos_unidades AS actfUnid', 'd.activof_unidad', '=', 'actfUnid.id')
        ->join("main_empresas AS emp", "actfUnid.empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'actfUnid.token_activof_unidad' => $token_activof_unidad,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->exists();

        if (!$exist_deprec) {
          $costo_adquisicion = (float)$vActDeprec->costo_adquisicion;
          $mejoras_periodo = DB::table('eegr_compras_prorrateos_incrementos')
          ->whereBetween("fecha_contabilizacion_incremento", [$vActDeprec->fecha_ultimo_corte_fiscal, $vActDeprec->fecha_proximo_corte_fiscal])
          ->where('empresa', $main_empresa_id)
          ->where('activof_unidad', $vActDeprec->FUNIID)
          ->sum('incremento_monto');
          
          $valor_libros_inicial = $costo_adquisicion + $mejoras_periodo;

          DB::table('eegr_activos_fijos_depreciaciones')
          ->insert(array(
            'token_activof_deprec' => Str::uuid(),
            'activof_unidad' => $vActDeprec->FUNIID,
            'deprec_concepto' => 'depreciación inicial',
            'deprec_tipo' => 'inicial',
            'deprec_subtipo' => 'otro',
            'fecha_cont_deprec_periodo' => $fecha_contabilizacion,
            'periodo' => $vActDeprec->fecha_proximo_corte_contable, // El Unix Timestamp confirmado
            'importe' => 0,
            'valor_libros_final' => $valor_libros_inicial,
            'deprec_comentarios' => $observaciones,
            'empresa' => $main_empresa_id,
            'depreciado' => 1 // Marcamos como aplicado
          ));
        }

        $this->guardaDepreciacionFiscal($vActDeprec,$fecha_contabilizacion,$main_empresa_id,$observaciones,$gasto_contable_manual);
        $this->guardaDepreciacionContable($vActDeprec,$fecha_contabilizacion,$main_empresa_id,$observaciones,$deduccion_de_inversion_manual);
        $this->actualizarFechasCorte($vActDeprec);

        DB::commit(); // Si llegamos aquí, todo se guarda permanentemente
        return response()->json(['status' => 'success','message' => 'Depreciaciones aplicadas correctamente'], 200);
      } catch (\Exception $e) {
        DB::rollBack();
        // 1. Guardar el error real en storage/logs/laravel.log
        \Log::error("Error al recibir activo: " . $e->getMessage());
        // 2. Responder al usuario con algo genérico
        return response()->json(['status' => 'error','message' => 'Ocurrió un error interno al procesar la solicitud. Contacte a soporte.'. $e->getMessage()], 500);
      }

    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function getDepreciacionReporte(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_activof_unidad' => 'required|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      //da_te_default_timezone_set('America/Mexico_City');
      $token_activof_unidad = $request->input('token_activof_unidad');

      $reporte = DB::table('eegr_activos_fijos_depreciaciones AS d')
      ->join('eegr_activos_fijos_unidades AS actfUnid', 'd.activof_unidad', '=', 'actfUnid.id')
      ->join("main_empresas AS emp", "actfUnid.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'actfUnid.token_activof_unidad' => $token_activof_unidad,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select(
        //'d.id AS id_deprec',fecha_cont_deprec_periodo
        'd.deprec_concepto',
        'd.periodo',
        //'d.deprec_tipo',
        //'d.deprec_subtipo',

        DB::raw("
          SUM(
            CASE WHEN d.deprec_tipo = 'incremento' AND d.deprec_subtipo = 'contable' 
            THEN d.valor_libros_final ELSE 
            0 END
          ) as mejoras_contables
        "),
        DB::raw("
          SUM(
            CASE WHEN 
              (d.deprec_tipo = 'natural' AND d.deprec_subtipo = 'contable') 
              OR 
              (d.deprec_tipo = 'inicial' AND d.deprec_subtipo = 'otro') 
            THEN d.importe ELSE 
            0 END
          ) as contable_monto
        "),
        DB::raw("
          MAX(
            CASE WHEN d.deprec_subtipo = 'contable' OR (d.deprec_tipo = 'inicial' AND d.deprec_subtipo = 'otro') 
            THEN d.valor_libros_final ELSE 
            0 END
          ) 
          as valor_en_libros_contable
        "),
        DB::raw("
          SUM(
            CASE WHEN d.deprec_tipo = 'incremento' AND d.deprec_subtipo = 'fiscal' 
            THEN d.valor_libros_final ELSE 
            0 END
          ) as mejoras_de_inversion
        "),
        DB::raw("
          SUM(
            CASE WHEN 
              (d.deprec_tipo = 'natural' AND d.deprec_subtipo = 'fiscal') 
              OR 
              (d.deprec_tipo = 'inicial' AND d.deprec_subtipo = 'otro') 
            THEN d.importe ELSE 
            0 END
          ) as fiscal_monto
        "),
        DB::raw("
          MAX(
            CASE WHEN d.deprec_subtipo = 'fiscal' OR (d.deprec_tipo = 'inicial' AND d.deprec_subtipo = 'otro') 
            THEN d.valor_libros_final ELSE 
            0 END
          ) as valor_actualizado_de_inversion
        ")
      )
      ->groupBy('d.periodo','d.deprec_concepto')
      ->orderBy("d.id", "ASC")
      ->get();

      $reporte->transform(function($item) {
        $item->fecha_legible = date('M Y', $item->periodo);
        $item->periodo = $JwtAuth->mostrarUnixAFechaMexico($item->periodo);
        return $item;
      });

      $dataMensaje = array(
        'code' => 200,
        'status' => 'success',
        'depreciaciones' => $reporte
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function getMejorasReporte(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_activof_unidad' => 'required|string',
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
      $token_activof_unidad = $request->input('token_activof_unidad');
      $mejoras_lista = [];

      $queryMejoras = DB::table('eegr_compras_prorrateos_incrementos AS mej')
      ->join('eegr_compras_prorrateos AS prort', 'mej.prorrateo', '=', 'prort.id')
      ->join('eegr_compras as comp', 'mej.compra', '=', 'comp.id')
      ->join('eegr_activos_fijos_unidades AS actfUnid', 'mej.activof_unidad', '=', 'actfUnid.id')
      ->join("main_empresas AS emp", "actfUnid.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'actfUnid.token_activof_unidad' => $token_activof_unidad,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select([
        'prort.folio_prorrateo',
        'comp.folio_compra AS buy_folio',
        'comp.post_folio AS buy_subfolio',
        'mej.*'
      ])
      ->get();

      foreach ($queryMejoras as $vMej) {
        $mejoras_lista[] = [
          "token_rel_prort" => $vMej->token_rel_prort,
          "folio_incremento" => "MEJ-".$JwtAuth->generarFolio($vMej->folio_incremento),
          "fecha_contabilizacion_incremento" => $JwtAuth->mostrarUnixAFechaMexico($vMej->fecha_contabilizacion_incremento),
          "prorrateo" => "PRT-".$JwtAuth->generarFolio($vMej->folio_prorrateo),
          "compra" => "COMP-".$JwtAuth->generarFolio($vMej->buy_folio).($vMej->buy_subfolio != NULL ? '-'.$vMej->buy_subfolio : ''),
          "incremento" => $vMej->incremento_monto
        ];
      }

      $dataMensaje = array(
        'code' => 200,
        'status' => 'success',
        'mejoras' => $mejoras_lista
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}