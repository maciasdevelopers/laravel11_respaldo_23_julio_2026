<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\MovimientosBancariosModelo;
use Carbon\Carbon;

class FNZS_MovimientosDineroController extends Controller{
  public function movimiento_cuentas_propias_catalogo(Request $request){
    //da_te_default_timezone_set('America/Mexico_City');
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
      
      $queryCPMovimientos = DB::table("fnzs_movimientos_cuentas_propias AS mcp")
      ->join("main_empresas AS emp", "mcp.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("mcp.movimiento_cp_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy("mcp.id","DESC")
      ->get();

      if ($queryCPMovimientos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron movimientos entre cuentas propias registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayMovimientos = array();

        foreach ($queryCPMovimientos as $vMov) {
          $movimiento_concepto = "";
          $movimiento_observaciones = "";

          $origen_catalogo_tipo = "";
          $origen_catalogo_token = "";
          $origen_catalogo_folio = "";
          $origen_catalogo_name = "";

          $destino_catalogo_tipo = "";
          $destino_catalogo_token = "";
          $destino_catalogo_folio = "";
          $destino_catalogo_name = "";
          $movimiento_monto = "";
          $movimiento_moneda = "";
          $movimiento_tipo_cambio = "";

          //$movimiento_origen = array();
          $queryCPMovimientoOrigen = DB::table("fnzs_actividad_movimientos AS mov")
          ->join("fnzs_movimientos_cuentas_propias AS mcp", "mov.id", "=", "mcp.movimiento_cp_origen")
          ->join("main_empresas AS emp", "mcp.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where('mcp.movimiento_cp_token',$vMov->movimiento_cp_token)
          ->where('emp.empresa_token',$empresa)
          ->where('users.usuario_token',$usuario)
          ->get();
          foreach ($queryCPMovimientoOrigen as $origen) {
            //caja
            $movCaja = DB::table("fnzs_catalogos_caja AS caj")
            ->join("fnzs_actividad_movimientos AS mov", "caj.id", "mov.caja")
            ->where('mov.token_movimiento',$origen->token_movimiento)
            ->where('caj.status',TRUE)
            ->select("caj.token_caja","caj.no_caja","caj.alias_caja")
            ->first();

            //banco
            $movCuentas = DB::table("fnzs_catalogos_cuentas AS account")
            ->join("fnzs_actividad_movimientos AS mov", "account.id", "mov.cuenta_bancaria")
            ->where('mov.token_movimiento',$origen->token_movimiento)
            ->where('account.status',TRUE)
            ->select('account.token_cuenta','account.folio_cuenta','account.cuenta')
            ->first();

            //monederos
            $movMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS moned")
            ->join("fnzs_actividad_movimientos AS mov","moned.id","mov.cuenta_monedero")
            ->where('mov.token_movimiento',$origen->token_movimiento)
            ->where('moned.status',TRUE)
            ->select('moned.token_cuentamonedero','moned.folio_cuentmon','moned.cuenta')
            ->first();

            if ($movCaja) {
              $origen_catalogo_tipo = "caja";
              $origen_catalogo_token = $movCaja->token_caja;
              $origen_catalogo_folio = "CAJ-" . $JwtAuth->generarFolio($movCaja->no_caja);
              $origen_catalogo_name = $JwtAuth->desencriptar($movCaja->alias_caja);
            } elseif ($movCuentas) {
              $origen_catalogo_tipo = "banco";
              $origen_catalogo_token = $movCuentas->token_cuenta;
              $origen_catalogo_folio = 'CUENT-'.$JwtAuth->generarFolio($movCuentas->folio_cuenta);
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($movCuentas->cuenta);
              $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
              $origen_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
            } elseif ($movMonedero) {
              $origen_catalogo_tipo = "monedero";
              $origen_catalogo_token = $movMonedero->token_cuentamonedero;
              $origen_catalogo_folio = "CUENTM-" . $JwtAuth->generarFolio($movMonedero->folio_cuentmon);
              $cuenta_descifrada_substr = substr(substr($JwtAuth->decryptBankAccount($movMonedero->cuenta), -4), -4);
              $origen_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
            }

            $movimiento_concepto = $JwtAuth->desencriptar($origen->concepto_movimiento);
            $movimiento_observaciones = $JwtAuth->desencriptar($origen->observaciones_movimiento);
            
            //$row_origen = array(
            //  "token_movimiento" => $origen->token_movimiento,
            //  "folio_movimiento" => "MOV-".$JwtAuth->generarFolio($origen->folio_movimiento),
            //  "fecha_contabilizacion_movimiento" => $JwtAuth->mostrarUnixAFechaMexico($origen->fecha_contabilizacion_movimiento),
            //  "tipo_movimiento" => $origen->tipo_movimiento,
            //  "subtipo_movimiento" => $origen->subtipo_movimiento,
            //  "concepto_movimiento" => $JwtAuth->desencriptar($origen->concepto_movimiento),
            //  //"responsable" => $origen-> $vEmp->userr,
            //  "origen_catalogo_tipo" => $origen_catalogo_tipo,
            //  "origen_catalogo_token" => $origen_catalogo_token,
            //  "origen_catalogo_folio" => $origen_catalogo_folio,
            //  "origen_catalogo_name" => $origen_catalogo_name,
            //  "monto_aplicado" => "$".number_format($origen->monto_aplicado * $origen->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($origen->moneda_movimiento), '.', ','),
            //  "moneda_movimiento" => $origen->moneda_movimiento,
            //  "tipo_cambio_movimiento" => "$".number_format($origen->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($origen->moneda_movimiento), '.', ','),
            //  "observaciones_movimiento" => $JwtAuth->desencriptar($origen->observaciones_movimiento),
            //);
            //$movimiento_origen[] = $row_origen;
          }

          //$movimiento_destino = array();
          $queryCPMovimientoDestino = DB::table("fnzs_actividad_movimientos AS mov")
          ->join("fnzs_movimientos_cuentas_propias AS mcp", "mov.id", "=", "mcp.movimiento_cp_destino")
          ->join("main_empresas AS emp", "mcp.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where('mcp.movimiento_cp_token',$vMov->movimiento_cp_token)
          ->where('emp.empresa_token',$empresa)
          ->where('users.usuario_token',$usuario)
          ->get();
          foreach ($queryCPMovimientoDestino as $final) {
            //caja
            $movCaja = DB::table("fnzs_catalogos_caja AS caj")
            ->join("fnzs_actividad_movimientos AS mov", "caj.id", "mov.caja")
            ->where('mov.token_movimiento',$final->token_movimiento)
            ->where('caj.status',TRUE)
            ->select("caj.token_caja","caj.no_caja","caj.alias_caja")
            ->first();

            //banco
            $movCuentas = DB::table("fnzs_catalogos_cuentas AS account")
            ->join("fnzs_actividad_movimientos AS mov", "account.id", "mov.cuenta_bancaria")
            ->where('mov.token_movimiento',$final->token_movimiento)
            ->where('account.status',TRUE)
            ->select('account.token_cuenta','account.folio_cuenta','account.cuenta')
            ->first();

            //monederos
            $movMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS moned")
            ->join("fnzs_actividad_movimientos AS mov","moned.id","mov.cuenta_monedero")
            ->where('mov.token_movimiento',$final->token_movimiento)
            ->where('moned.status',TRUE)
            ->select('moned.token_cuentamonedero','moned.folio_cuentmon','moned.cuenta')
            ->first();

            if ($movCaja) {
              $destino_catalogo_tipo = "caja";
              $destino_catalogo_token = $movCaja->token_caja;
              $destino_catalogo_folio = "CAJ-" . $JwtAuth->generarFolio($movCaja->no_caja);
              $destino_catalogo_name = $JwtAuth->desencriptar($movCaja->alias_caja);
            } elseif ($movCuentas) {
              $destino_catalogo_tipo = "banco";
              $destino_catalogo_token = $movCuentas->token_cuenta;
              $destino_catalogo_folio = 'CUENT-'.$JwtAuth->generarFolio($movCuentas->folio_cuenta);
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($movCuentas->cuenta);
              $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
              $destino_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
            } elseif ($movMonedero) {
              $destino_catalogo_tipo = "monedero";
              $destino_catalogo_token = $movMonedero->token_cuentamonedero;
              $destino_catalogo_folio = "CUENTM-" . $JwtAuth->generarFolio($movMonedero->folio_cuentmon);
              $cuenta_descifrada_substr = substr(substr($JwtAuth->decryptBankAccount($movMonedero->cuenta), -4), -4);
              $destino_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
            }

            $movimiento_monto = "$".number_format($final->monto_aplicado * $final->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($final->moneda_movimiento), '.', ',');
            $movimiento_moneda = $final->moneda_movimiento;
            $movimiento_tipo_cambio = "$".number_format($final->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($final->moneda_movimiento), '.', ',');

            //$row_destino = array(
            //  "token_movimiento" => $final->token_movimiento,
            //  "folio_movimiento" => "MOV-".$JwtAuth->generarFolio($final->folio_movimiento),
            //  "fecha_contabilizacion_movimiento" => $JwtAuth->mostrarUnixAFechaMexico($final->fecha_contabilizacion_movimiento),
            //  "tipo_movimiento" => $final->tipo_movimiento,
            //  "subtipo_movimiento" => $final->subtipo_movimiento,
            //  "concepto_movimiento" => $JwtAuth->desencriptar($final->concepto_movimiento),
            //  //"responsable" => $final-> $vEmp->userr,
            //  "destino_catalogo_tipo" => $destino_catalogo_tipo,
            //  "destino_catalogo_token" => $destino_catalogo_token,
            //  "destino_catalogo_folio" => $destino_catalogo_folio,
            //  "destino_catalogo_name" => $destino_catalogo_name,
            //  "monto_aplicado" => "$".number_format($final->monto_aplicado * $final->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($final->moneda_movimiento), '.', ','),
            //  "moneda_movimiento" => $final->moneda_movimiento,
            //  "tipo_cambio_movimiento" => "$".number_format($final->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($final->moneda_movimiento), '.', ','),
            //  "observaciones_movimiento" => $JwtAuth->desencriptar($final->observaciones_movimiento),
            //);
            //$movimiento_destino[] = $row_destino;
          }
          $fecha_contabilizacion = " date ".date('Y-m-d H:i:s', $vMov->movimiento_cp_fecha_contabilizacion)." gmdate ".gmdate('Y-m-d H:i:s', $vMov->movimiento_cp_fecha_contabilizacion);
          $correct_fecha_cont = $JwtAuth->corregirTimestampUnixHistorico($vMov->movimiento_cp_fecha_contabilizacion);
          $row_mov_main = array(
            "movimiento_cp_token" => $vMov->movimiento_cp_token,
            "movimiento_cp_folio" => $vMov->movimiento_cp_folio ? "MCP-" . $JwtAuth->generarFolio($vMov->movimiento_cp_folio) : '',
            "movimiento_cp_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vMov->movimiento_cp_fecha_contabilizacion),//gmdate('Y-m-d H:i:s',$vMov->movimiento_cp_fecha_contabilizacion),
            //"movimiento_cp_origen" => $movimiento_origen,
            "origen_catalogo_tipo" => $origen_catalogo_tipo,
            "origen_catalogo_token" => $origen_catalogo_token,
            "origen_catalogo_folio" => $origen_catalogo_folio,
            "origen_catalogo_name" => $origen_catalogo_name,
            "origen_catalogo_complete" => "$origen_catalogo_folio $origen_catalogo_name",
            "movimiento_concepto" => $movimiento_concepto,
            "movimiento_observaciones" => $movimiento_observaciones,
            //"movimiento_cp_destino" => $movimiento_destino,
            "destino_catalogo_tipo" => $destino_catalogo_tipo,
            "destino_catalogo_token" => $destino_catalogo_token,
            "destino_catalogo_folio" => $destino_catalogo_folio,
            "destino_catalogo_name" => $destino_catalogo_name,
            "destino_catalogo_complete" => "$destino_catalogo_folio $destino_catalogo_name",
            //montos
            "movimiento_monto" => $movimiento_monto,
            "movimiento_moneda" => $movimiento_moneda,
            "movimiento_tipo_cambio" => $movimiento_tipo_cambio,
            "movimiento_cp_observaciones" => $JwtAuth->desencriptar($vMov->movimiento_cp_observaciones),
            //montos
            "movimiento_cp_cancelado" => is_null($vMov->movimiento_cp_cancelado) || !$vMov->movimiento_cp_cancelado ? false : true,
          );
          $arrayMovimientos[] = $row_mov_main;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'movimientos' => $arrayMovimientos
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function movimiento_cuentas_propias_cancelar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'movimiento_cp_token' => 'required|string',
      'fecha_contabilizacion' => 'required|string',
      'observaciones' => 'required|string',
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
      $movimiento_cp_token = $request->input('movimiento_cp_token');
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $observaciones = $request->input('observaciones');
      
      $OKCPMToken = isset($movimiento_cp_token) && !empty($movimiento_cp_token);
      $OKfechaContabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $OKObservaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

      if ($OKCPMToken && $OKfechaContabilizacion && $OKObservaciones) {
        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr')
        ->first();
        if ($vEmp) {
          DB::beginTransaction();
          try {
            
            $id_mcp = DB::table('fnzs_movimientos_cuentas_propias')->where('movimiento_cp_token',$movimiento_cp_token)->value('id');

            $maxFolio = DB::table('fnzs_mov_cuent_propias_cancelacion')
            ->where('mcp_cancel_empresa', $vEmp->id)
            ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
            ->max('folio_cancel_mcp');

            $folioSoliNuevo = $maxFolio ? $maxFolio + 1 : 1;
            $folioSoliCan = 'MCP-SOLI-CANC-'.$JwtAuth->generarFolio($folioSoliNuevo);
            
            DB::table("fnzs_mov_cuent_propias_cancelacion")
            ->insert(
              array(
                "token_cancel_mcp" => Str::uuid()->toString(),
                "folio_cancel_mcp" => $folioSoliNuevo,
                "fecha_cancel_mcp" => time(),
                "fecha_cont_cancel_mcp" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
                "mcp_cancel" => $id_mcp,
                "mcp_cancel_observaciones_mov" => $JwtAuth->encriptar($observaciones),
                "mcp_cancel_realizada" => FALSE,
                "mcp_cancel_empresa" => $vEmp->id,
                "mcp_cancel_status" => TRUE
              )
            );

            DB::commit();
      
            return response()->json([
              'status'  => 'success',
              'code'    => 200,
              'message' => 'Solicitud de cancelación de movimiento entre cuentas propias ha sido registrada con el folio '.$folioSoliCan
            ]);
          } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
              'status'  => 'error',
              'code'    => 500,
              'message' => 'Error interno al procesar la actualización: ' . $e->getMessage()
            ], 500);
          }
        }
      } else {
        $mensaje_error = '';
				if (!$OKCPMToken) $mensaje_error = 'Error en movimiento vinculado, verifique su información';
        if (!$OKfechaContabilizacion) $mensaje_error = "Error en fecha de contabilización de pago, verifique su información";
				if (!$OKObservaciones) $mensaje_error = 'Error en observaciones finales, verifique su información';
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function movimiento_cuentas_propias_cancelados(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryCPMovimientos = DB::table("fnzs_movimientos_cuentas_propias AS mcp")
    ->join("main_empresas AS emp", "mcp.empresa", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where('mcp.movimiento_cp_cancelado',TRUE)
    ->where('emp.empresa_token',$empresa)
    ->where('users.usuario_token',$usuario)
    ->get();

    if ($queryCPMovimientos->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron movimientos entre cuentas propias registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayMovimientos = array();      

      foreach ($queryCPMovimientos as $vMov) {
        $movimiento_concepto = "";
        $movimiento_observaciones = "";

        $origen_catalogo_tipo = "";
        $origen_catalogo_token = "";
        $origen_catalogo_folio = "";
        $origen_catalogo_name = "";

        $destino_catalogo_tipo = "";
        $destino_catalogo_token = "";
        $destino_catalogo_folio = "";
        $destino_catalogo_name = "";
        $movimiento_monto = "";
        $movimiento_moneda = "";
        $movimiento_tipo_cambio = "";

        //$movimiento_origen = array();
        $queryCPMovimientoOrigen = DB::table("fnzs_actividad_movimientos AS mov")
        ->join("fnzs_movimientos_cuentas_propias AS mcp", "mov.id", "=", "mcp.movimiento_cp_origen")
        ->join("main_empresas AS emp", "mcp.empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where('mcp.movimiento_cp_token',$vMov->movimiento_cp_token)
        ->where('emp.empresa_token',$empresa)
        ->where('users.usuario_token',$usuario)
        ->get();
        foreach ($queryCPMovimientoOrigen as $origen) {
          //caja
          $movCaja = DB::table("fnzs_catalogos_caja AS caj")
          ->join("fnzs_actividad_movimientos AS mov", "caj.id", "mov.caja")
          ->where('mov.token_movimiento',$origen->token_movimiento)
          ->where('caj.status',TRUE)
          ->select("caj.token_caja","caj.no_caja","caj.alias_caja")
          ->first();

          //banco
          $movCuentas = DB::table("fnzs_catalogos_cuentas AS account")
          ->join("fnzs_actividad_movimientos AS mov", "account.id", "mov.cuenta_bancaria")
          ->where('mov.token_movimiento',$origen->token_movimiento)
          ->where('account.status',TRUE)
          ->select('account.token_cuenta','account.folio_cuenta','account.cuenta')
          ->first();

          //monederos
          $movMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS moned")
          ->join("fnzs_actividad_movimientos AS mov","moned.id","mov.cuenta_monedero")
          ->where('mov.token_movimiento',$origen->token_movimiento)
          ->where('moned.status',TRUE)
          ->select('moned.token_cuentamonedero','moned.folio_cuentmon','moned.cuenta')
          ->first();

          if ($movCaja) {
            $origen_catalogo_tipo = "caja";
            $origen_catalogo_token = $movCaja->token_caja;
            $origen_catalogo_folio = "CAJ-" . $JwtAuth->generarFolio($movCaja->no_caja);
            $origen_catalogo_name = $JwtAuth->desencriptar($movCaja->alias_caja);
          } elseif ($movCuentas) {
            $origen_catalogo_tipo = "banco";
            $origen_catalogo_token = $movCuentas->token_cuenta;
            $origen_catalogo_folio = 'CUENT-'.$JwtAuth->generarFolio($movCuentas->folio_cuenta);
            $cuenta_descifrada = $JwtAuth->decryptBankAccount($movCuentas->cuenta);
            $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
            $origen_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
          } elseif ($movMonedero) {
            $origen_catalogo_tipo = "monedero";
            $origen_catalogo_token = $movMonedero->token_cuentamonedero;
            $origen_catalogo_folio = "CUENTM-" . $JwtAuth->generarFolio($movMonedero->folio_cuentmon);
            $cuenta_descifrada_substr = substr(substr($JwtAuth->decryptBankAccount($movMonedero->cuenta), -4), -4);
            $origen_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
          }

          $movimiento_concepto = $JwtAuth->desencriptar($origen->concepto_movimiento);
          $movimiento_observaciones = $JwtAuth->desencriptar($origen->observaciones_movimiento);
        }

        //$movimiento_destino = array();
        $queryCPMovimientoDestino = DB::table("fnzs_actividad_movimientos AS mov")
        ->join("fnzs_movimientos_cuentas_propias AS mcp", "mov.id", "=", "mcp.movimiento_cp_destino")
        ->join("main_empresas AS emp", "mcp.empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where('mcp.movimiento_cp_token',$vMov->movimiento_cp_token)
        ->where('emp.empresa_token',$empresa)
        ->where('users.usuario_token',$usuario)
        ->get();
        foreach ($queryCPMovimientoDestino as $final) {
          //caja
          $movCaja = DB::table("fnzs_catalogos_caja AS caj")
          ->join("fnzs_actividad_movimientos AS mov", "caj.id", "mov.caja")
          ->where('mov.token_movimiento',$final->token_movimiento)
          ->where('caj.status',TRUE)
          ->select("caj.token_caja","caj.no_caja","caj.alias_caja")
          ->first();

          //banco
          $movCuentas = DB::table("fnzs_catalogos_cuentas AS account")
          ->join("fnzs_actividad_movimientos AS mov", "account.id", "mov.cuenta_bancaria")
          ->where('mov.token_movimiento',$final->token_movimiento)
          ->where('account.status',TRUE)
          ->select('account.token_cuenta','account.folio_cuenta','account.cuenta')
          ->first();

          //monederos
          $movMonedero = DB::table("fnzs_catalogos_cuentas_monedero AS moned")
          ->join("fnzs_actividad_movimientos AS mov","moned.id","mov.cuenta_monedero")
          ->where('mov.token_movimiento',$final->token_movimiento)
          ->where('moned.status',TRUE)
          ->select('moned.token_cuentamonedero','moned.folio_cuentmon','moned.cuenta')
          ->first();

          if ($movCaja) {
            $destino_catalogo_tipo = "caja";
            $destino_catalogo_token = $movCaja->token_caja;
            $destino_catalogo_folio = "CAJ-" . $JwtAuth->generarFolio($movCaja->no_caja);
            $destino_catalogo_name = $JwtAuth->desencriptar($movCaja->alias_caja);
          } elseif ($movCuentas) {
            $destino_catalogo_tipo = "banco";
            $destino_catalogo_token = $movCuentas->token_cuenta;
            $destino_catalogo_folio = 'CUENT-'.$JwtAuth->generarFolio($movCuentas->folio_cuenta);
            $cuenta_descifrada = $JwtAuth->decryptBankAccount($movCuentas->cuenta);
            $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
            $destino_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
          } elseif ($movMonedero) {
            $destino_catalogo_tipo = "monedero";
            $destino_catalogo_token = $movMonedero->token_cuentamonedero;
            $destino_catalogo_folio = "CUENTM-" . $JwtAuth->generarFolio($movMonedero->folio_cuentmon);
            $cuenta_descifrada_substr = substr(substr($JwtAuth->decryptBankAccount($movMonedero->cuenta), -4), -4);
            $destino_catalogo_name = "**** **** **** $cuenta_descifrada_substr";
          }

          $movimiento_monto = "$".number_format($final->monto_aplicado * $final->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($final->moneda_movimiento), '.', ',');
          $movimiento_moneda = $final->moneda_movimiento;
          $movimiento_tipo_cambio = "$".number_format($final->tipo_cambio_movimiento, $JwtAuth->getMonedaAPI($final->moneda_movimiento), '.', ',');
        }
        
        $row_mov_main = array(
          "movimiento_cp_token" => $vMov->movimiento_cp_token,
          "movimiento_cp_folio" => $vMov->movimiento_cp_folio ? "MCP-" . $JwtAuth->generarFolio($vMov->movimiento_cp_folio) : '',
          "movimiento_cp_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vMov->movimiento_cp_fecha_contabilizacion),
          //"movimiento_cp_origen" => $movimiento_origen,
          "origen_catalogo_tipo" => $origen_catalogo_tipo,
          "origen_catalogo_token" => $origen_catalogo_token,
          "origen_catalogo_folio" => $origen_catalogo_folio,
          "origen_catalogo_name" => $origen_catalogo_name,
          "origen_catalogo_complete" => "$origen_catalogo_folio $origen_catalogo_name",
          "movimiento_concepto" => $movimiento_concepto,
          "movimiento_observaciones" => $movimiento_observaciones,
          //"movimiento_cp_destino" => $movimiento_destino,
          "destino_catalogo_tipo" => $destino_catalogo_tipo,
          "destino_catalogo_token" => $destino_catalogo_token,
          "destino_catalogo_folio" => $destino_catalogo_folio,
          "destino_catalogo_name" => $destino_catalogo_name,
          "destino_catalogo_complete" => "$destino_catalogo_folio $destino_catalogo_name",
          //montos
          "movimiento_monto" => $movimiento_monto,
          "movimiento_moneda" => $movimiento_moneda,
          "movimiento_tipo_cambio" => $movimiento_tipo_cambio,
          "movimiento_cp_observaciones" => $JwtAuth->desencriptar($vMov->movimiento_cp_observaciones)
        );
        $arrayMovimientos[] = $row_mov_main;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'movimientos' => $arrayMovimientos
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function movimiento_cuentas_propias_registro(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'origen_tipo' => 'required|string',
      'origen_token' => 'required|string',
      'fecha_contabilizacion' => 'required|string',
      'concepto' => 'required|string',
      'destino_tipo' => 'required|string',
      'destino_token' => 'required|string',
      'monto' => 'required|numeric',
      'moneda_code' => 'required|string',
      'tipo_cambio' => 'required|numeric',
      'observaciones' => 'required|string',
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
      $origen_tipo = $request->input('origen_tipo');
      $origen_token = $request->input('origen_token');
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $concepto = $request->input('concepto');
      $destino_tipo = $request->input('destino_tipo');
      $destino_token = $request->input('destino_token');
      $monto = $request->input('monto');
      $moneda_code = $request->input('moneda_code');
      $tipo_cambio = $request->input('tipo_cambio');
      $observaciones = $request->input('observaciones');
      
      $validar_origen_tipo = isset($origen_tipo) && !empty($origen_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$origen_tipo);
      $validar_origen_token = isset($origen_token) && !empty($origen_token);
      $validar_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $validar_concepto = isset($concepto) && !empty($concepto) && preg_match($JwtAuth->filtroAlfaNumerico(),$concepto);
      $validar_destino_tipo = isset($destino_tipo) && !empty($destino_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$destino_tipo);
      $validar_destino_token = isset($destino_token) && !empty($destino_token);
      $validar_monto = isset($monto) && !empty($monto) && preg_match($JwtAuth->filtroNumericoSimple(),$monto);
      $validar_moneda_code = isset($moneda_code) && !empty($moneda_code) && preg_match($JwtAuth->filtroAlfaNumerico(),$moneda_code);
      $validar_tipo_cambio = isset($tipo_cambio) && !empty($tipo_cambio) && preg_match($JwtAuth->filtroNumericoSimple(),$tipo_cambio);
      $validar_observaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

      if ($validar_origen_tipo && $validar_origen_token && $validar_fecha_contabilizacion && $validar_concepto && $validar_destino_tipo && 
        $validar_destino_token && $validar_monto && $validar_moneda_code && $validar_tipo_cambio && $validar_observaciones) {
        $fecha_registro = time();

        //return response()->json(['message' => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion)." ".$fecha_contabilizacion,'code' => 200,'status' => 'error']);

        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
        WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

        foreach ($queryEmp as $vEmp) {
          //movimentos_de origen
          $origen_caja_id = $origen_tipo == "caja" ? DB::table("fnzs_catalogos_caja")->where("token_caja",$origen_token)->value("id") : NULL;
          $origen_cuentas_id = $origen_tipo == "banco" ? DB::table("fnzs_catalogos_cuentas")->where("token_cuenta",$origen_token)->value("id") : NULL;
          $origen_cuentas_monedero_id = $origen_tipo == "monedero" ? DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero",$origen_token)->value("id") : NULL;

          $folioMovimOrigen = DB::select("SELECT IF (max(movim.folio_movimiento) IS NOT NULL,(max(movim.folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS movim JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
            JOIN teci_usuarios_catalogo AS users WHERE movim.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", 
            [$empresa, $usuario]);

          $token_movimiento_origen = $JwtAuth->encriptarToken($origen_tipo.$fecha_contabilizacion. $folioMovimOrigen[0]->folio.'R');
          $insertMovimientosOrigen = DB::table("fnzs_actividad_movimientos")->insert(
            array(
              "token_movimiento" => $token_movimiento_origen,
              "folio_movimiento" => $folioMovimOrigen[0]->folio,
              "fecha_sistema" => $fecha_registro,
              "seccion_movimiento" => 'tesorería',
              "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
              "tipo_movimiento" => "R",
              "subtipo_movimiento" => "P",
              "concepto_movimiento" => $JwtAuth->encriptar($concepto),
              "responsable" => $vEmp->userr,
              "caja" => $origen_caja_id,
              "cuenta_bancaria" => $origen_cuentas_id,
              "cuenta_monedero" => $origen_cuentas_monedero_id,
              "monto_aplicado" => $monto,
              "moneda_movimiento" => $moneda_code,
              "tipo_cambio_movimiento" => $tipo_cambio,
              "observaciones_movimiento" => $JwtAuth->encriptar($observaciones),
              "empresa" => $vEmp->id
            )
          );

          //movimentos_de destino
          $destino_caja_id = $destino_tipo == "caja" ? DB::table("fnzs_catalogos_caja")->where("token_caja",$destino_token)->value("id") : NULL;
          $destino_cuentas_id = $destino_tipo == "banco" ? DB::table("fnzs_catalogos_cuentas")->where("token_cuenta",$destino_token)->value("id") : NULL;
          $destino_cuentas_monedero_id = $destino_tipo == "monedero" ? DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero",$destino_token)->value("id") : NULL;

          $folioMovimDestino = DB::select("SELECT IF (max(movim.folio_movimiento) IS NOT NULL,(max(movim.folio_movimiento)+1),1) AS folio FROM fnzs_actividad_movimientos AS movim JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
            JOIN teci_usuarios_catalogo AS users WHERE movim.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", 
            [$empresa, $usuario]);

          $token_movimiento_destino = $JwtAuth->encriptarToken($destino_tipo.$fecha_contabilizacion.$folioMovimDestino[0]->folio.'S');
          $insertMovimientosDestino = DB::table("fnzs_actividad_movimientos")->insert(
            array(
              "token_movimiento" => $token_movimiento_destino,
              "folio_movimiento" => $folioMovimDestino[0]->folio,
              "fecha_sistema" => $fecha_registro,
              "seccion_movimiento" => 'tesorería',
              "fecha_contabilizacion_movimiento" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
              "tipo_movimiento" => "S",
              "subtipo_movimiento" => "P",
              "concepto_movimiento" => $JwtAuth->encriptar($concepto),
              "responsable" => $vEmp->userr,
              "caja" => $destino_caja_id,
              "cuenta_bancaria" => $destino_cuentas_id,
              "cuenta_monedero" => $destino_cuentas_monedero_id,
              "monto_aplicado" => $monto,
              "moneda_movimiento" => $moneda_code,
              "tipo_cambio_movimiento" => $tipo_cambio,
              "observaciones_movimiento" => $JwtAuth->encriptar($observaciones),
              "empresa" => $vEmp->id
            )
          );
          $movim_registrado_origen = DB::table("fnzs_actividad_movimientos")->where("token_movimiento",$token_movimiento_origen)->value("id");
          $movim_registrado_destino = DB::table("fnzs_actividad_movimientos")->where("token_movimiento",$token_movimiento_destino)->value("id");
          
          $folioMovimCP = DB::select("SELECT IF (max(mcp.movimiento_cp_folio) IS NOT NULL,(max(mcp.movimiento_cp_folio)+1),1) AS folio FROM fnzs_movimientos_cuentas_propias AS mcp JOIN main_empresas AS emp 
            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE mcp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
            AND users.usuario_token = ?",[$empresa, $usuario]);
          $token_movimiento_cp = $JwtAuth->encriptarToken($fecha_contabilizacion.$movim_registrado_origen.$movim_registrado_destino);
          $insertCPMovimientos = DB::table("fnzs_movimientos_cuentas_propias")->insert(
            array(
              "movimiento_cp_token" => $token_movimiento_cp,
              "movimiento_cp_folio" => $folioMovimCP[0]->folio,
              "movimiento_cp_fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
              "movimiento_cp_origen" => $movim_registrado_origen,
              "movimiento_cp_destino" => $movim_registrado_destino,
              "movimiento_cp_observaciones" => $JwtAuth->encriptar($observaciones),
              "empresa" => $vEmp->id
            )
          );

          if ($insertCPMovimientos) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Movimiento entre cuentas propias ha sido registrado"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Movimiento entre cuentas propias no registrado, intente más tarde o comuniquese a soporte"
            );
          }
        }
      } else {
        $mensaje_error = '';
        if (!$validar_origen_tipo || !$validar_origen_token) {$mensaje_error = 'Error en selección de origen de movimiento, verifique su información';}
        if (!$validar_fecha_contabilizacion) {$mensaje_error = 'Error en fecha de contabilización, verifique su información';}
        if (!$validar_concepto) {$mensaje_error = 'Error en concepto de movimiento, verifique su información';}
        if (!$validar_destino_tipo || !$validar_destino_token) {$mensaje_error = 'Error en selección de destino de movimiento, verifique su información';}
        if (!$validar_monto) {$mensaje_error = 'Error en monto de movimiento, verifique su información';}
        if (!$validar_moneda_code) {$mensaje_error = 'Error en moneda de movimiento, proveedor o empleado, verifique su información';}
        if (!$validar_tipo_cambio) {$mensaje_error = 'Error en tipo de cambio de movimiento, verifique su información';}
        if (!$validar_observaciones) {$mensaje_error = 'Error en observaciones de movimiento, verifique su información';}
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function movimientosBancariosCuentasAll(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $decimalesMoneda = DB::select("SELECT emp.e_moneda_code,emp.e_moneda_decimales FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
      JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
      [$empresa, $usuario]);

    $list_movimientos = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
      ->join("vhum_empleados_catalogo AS resp_pers", "fnzs_actividad_movimientos.responsable", "=", "resp_pers.id")
      ->join("sos_personas AS people", "resp_pers.personal", "=", "people.id")
      ->join("fnzs_catalogos_cuentas AS count_cat", "fnzs_actividad_movimientos.cuenta_bancaria", "=", "count_cat.id")
      ->join("teci_bancos AS bank", "count_cat.banco", "=", "bank.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "=", "pers.id")
      ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
      ->where([
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])->orderBy('fnzs_actividad_movimientos.folio_movimiento', 'DESC')->get();

    if ($list_movimientos->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron movimientos entre cuentas propias registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayMovimientos = array();
      
      foreach ($list_movimientos as $v_mov) {
        //$v_mov->e_moneda_code,
        //$v_mov->e_moneda_decimales,


        //da_te_default_timezone_set($v_mov->zona_horaria);
        $token_movimiento = $v_mov->token_movimiento;
        $folio_movimiento = 'M-' . $JwtAuth->generarFolio($v_mov->folio_movimiento);
        $fecha_movimiento = $JwtAuth->mostrarUnixAFechaMexico($v_mov->fecha_sistema);

        $realizo_movimiento = $JwtAuth->desencriptar($v_mov->paterno) . " " . $JwtAuth->desencriptar($v_mov->materno) . " " . $JwtAuth->desencriptar($v_mov->nombre);

        $token_cuenta = $v_mov->token_cuenta;
        $folio_cuenta = 'CBAN-' . $JwtAuth->generarFolio($v_mov->folio_cuenta);
        //banco
        $token_bancos = $v_mov->token_bancos;
        $banco_clave = $v_mov->clave;
        $banco_nombre_comercial = $v_mov->nombre_comercial;
        $banco_razon_social = $v_mov->razon_social;
        $banco_imagen = $v_mov->img;
        $numero_cuenta = $v_mov->cuenta;

        $subtipo_movimiento = "";
        //SUMAN
        if ($v_mov->tipo_movimiento == "D" && $v_mov->subtipo_movimiento == "V") $subtipo_movimiento = "Venta realizada";
        if ($v_mov->tipo_movimiento == "D" && $v_mov->subtipo_movimiento == "D") $subtipo_movimiento = "Devolución de compras";

        //RESTAN
        if ($v_mov->tipo_movimiento == "R" && $v_mov->subtipo_movimiento == "C") $subtipo_movimiento = "Compra realizada";
        if ($v_mov->tipo_movimiento == "R" && $v_mov->subtipo_movimiento == "D") $subtipo_movimiento = "Devolución de venta";
        if ($v_mov->tipo_movimiento == "R" && $v_mov->subtipo_movimiento == "R") $subtipo_movimiento = "Reembolso";
        if ($v_mov->tipo_movimiento == "R" && $v_mov->subtipo_movimiento == "J") $subtipo_movimiento = "Justificación";

        $pago_folio = "";
        $pago_realizado = "0.00";
        if ($v_mov->pago != NULL) {
          $query_pago = DB::table("fnzs_pagos_pago AS payment")
            ->join("fnzs_catalogos_cuentas AS count_cat", "payment.cuenta_bancaria", "=", "count_cat.id")
            ->where(["payment.id" => $v_mov->pago, "count_cat.token_cuenta" => $token_cuenta])->get();
          foreach ($query_pago as $vPago) {
            $pago_folio = $JwtAuth->generarFolio($vPago->folio_pagos);
            $select_pago = DB::select("SELECT FORMAT(?,?) AS total", [$vPago->monto_pago, $decimalesMoneda[0]->decimales]);
            $pago_realizado = $select_pago[0]->total;
          }
        }

        $cobro_folio = "";
        $cobro_realizado = "0.00";
        if ($v_mov->cobro != NULL) {
          $query_cobro = DB::table("fnzs_cobros_cobro AS cobrar")
            ->join("fnzs_catalogos_cuentas AS count_cat", "cobrar.cuenta_bancaria", "=", "count_cat.id")
            ->where(["cobrar.id" => $v_mov->cobro, "count_cat.token_cuenta" => $token_cuenta])->get();
          foreach ($query_cobro as $vCobro) {
            $cobro_folio = $JwtAuth->generarFolio($vCobro->folio_cobros);
            $select_cobro = DB::select("SELECT FORMAT(?,?) AS total", [$vCobro->monto_cobro, $decimalesMoneda[0]->decimales]);
            $cobro_realizado = $select_cobro[0]->total;
          }
        }

        $row = array(
          "token_movimiento" => $token_movimiento,
          //"token_movimiento" => "token_movimiento",
          "folio_movimiento" => $folio_movimiento,
          "fecha_movimiento" => $fecha_movimiento,
          "tipo_movimiento" => $v_mov->tipo_movimiento,
          "sub_tipo_mov" => $v_mov->subtipo_movimiento,
          "subtipo_movimiento" => $subtipo_movimiento,
          "realizo_movimiento" => $realizo_movimiento,
          //cuenta
          "token_cuenta" => $token_cuenta,
          "folio_cuenta" => $folio_cuenta,
          //bancos
          "token_bancos" => $token_bancos,
          "banco_clave" => $banco_clave,
          "banco_nombre_comercial" => $banco_nombre_comercial,
          "banco_razon_social" => $banco_razon_social,
          "banco_imagen" => $banco_imagen,
          "numero_cuenta_back" => $numero_cuenta,
          "numero_cuenta" => "",
          //movimientos
          //pagos
          "pago_folio" => $pago_folio,
          "pago_realizado" => "$" . $pago_realizado,
          //cobros
          "cobro_folio" => $cobro_folio,
          "cobro_realizado" => "$" . $cobro_realizado,
          "cobro_realizado" => "$" . $cobro_realizado,
        );
        $arrayMovimientos[] = $row;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'movimientos' => $arrayMovimientos,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function movimientosBancariosCuentaToken(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cuenta' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_cuenta = $request->input('token_cuenta');
      
      $decimalesMoneda = DB::select("SELECT emp.e_moneda_code,emp.e_moneda_decimales FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
        [$empresa, $usuario]);

      $saldo_cuenta = "0.00";
      $list_movimientos = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
      ->join("vhum_empleados_catalogo AS resp_pers", "fnzs_actividad_movimientos.responsable", "=", "resp_pers.id")
      ->join("sos_personas AS people", "resp_pers.empleado_name", "=", "people.id")
      ->join("fnzs_catalogos_cuentas AS count_cat", "fnzs_actividad_movimientos.cuenta_bancaria", "=", "count_cat.id")
      ->join("teci_bancos AS bank", "count_cat.banco", "=", "bank.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "count_cat.token_cuenta" => $token_cuenta,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->orderBy('fnzs_actividad_movimientos.folio_movimiento', 'DESC')->get();

      if ($list_movimientos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron ordenes de dispersión de nómina registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $cuentaControl = new FNZS_CuentBancController();
        $arrayMovimientos = array();
        foreach ($list_movimientos as $v_mov) {
          //da_te_default_timezone_set($v_mov->zona_horaria);
          $token_movimiento = $v_mov->token_movimiento;
          $folio_movimiento = 'M-' . $JwtAuth->generarFolio($v_mov->folio_movimiento);
          $fecha_movimiento = $JwtAuth->mostrarUnixAFechaMexico($v_mov->fecha_sistema);

          $realizo_movimiento = $JwtAuth->desencriptar($v_mov->paterno) . " " . $JwtAuth->desencriptar($v_mov->materno) . " " . $JwtAuth->desencriptar($v_mov->nombre);

          $token_cuenta = $v_mov->token_cuenta;
          $folio_cuenta = 'CBAN-' . $JwtAuth->generarFolio($v_mov->folio_cuenta);
          //banco
          $token_bancos = $v_mov->token_bancos;
          $banco_clave = $v_mov->clave;
          $banco_nombre_comercial = $v_mov->nombre_comercial;
          $banco_razon_social = $v_mov->razon_social;
          $banco_imagen = $v_mov->img;
          $numero_cuenta = $v_mov->cuenta;

          $subtipo_movimiento = "";
          //SUMAN
          if ($v_mov->tipo_movimiento == "D" && $v_mov->subtipo_movimiento == "V") $subtipo_movimiento = "Venta realizada";
          if ($v_mov->tipo_movimiento == "D" && $v_mov->subtipo_movimiento == "D") $subtipo_movimiento = "Devolución de compras";
          if ($v_mov->tipo_movimiento == "D" && $v_mov->subtipo_movimiento == "A") $subtipo_movimiento = "Movimiento de ajuste";

          //RESTAN
          if ($v_mov->tipo_movimiento == "R" && $v_mov->subtipo_movimiento == "C") $subtipo_movimiento = "Compra realizada";
          if ($v_mov->tipo_movimiento == "R" && $v_mov->subtipo_movimiento == "D") $subtipo_movimiento = "Devolución de venta";
          if ($v_mov->tipo_movimiento == "R" && $v_mov->subtipo_movimiento == "R") $subtipo_movimiento = "Reembolso";
          if ($v_mov->tipo_movimiento == "R" && $v_mov->subtipo_movimiento == "J") $subtipo_movimiento = "Justificación";
          if ($v_mov->tipo_movimiento == "R" && $v_mov->subtipo_movimiento == "A") $subtipo_movimiento = "Movimiento de ajuste";

          $ajuste_folio = "";
          $pago_folio = "";
          $cobro_folio = "";
          $mov_monto = "0.00";
          $mov_beneficiario = "";

          if ($v_mov->ajuste != NULL) {
            $query_ajuste = DB::table("fnzs_catalogos_cuentas_ajustes AS ajus")
              ->join("fnzs_catalogos_cuentas AS count_cat", "ajus.cuenta_bancaria", "=", "count_cat.id")
              ->where(["ajus.id" => $v_mov->ajuste, "count_cat.token_cuenta" => $token_cuenta])->get();
            foreach ($query_ajuste as $vAjus) {
              //echo "vAjus->cliente ".$vAjus->aj_cliente;
              if ($vAjus->aj_proveedor != NULL) {
                $query_prov = DB::table("eegr_catalogo_proveedores AS catprov")
                  ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
                  ->where(["catprov.id" => $vAjus->aj_proveedor])->get();
                foreach ($query_prov as $vProv) {
                  if ($vProv->denominacion_rs != NULL) {
                    $mov_beneficiario = $JwtAuth->desencriptar($vProv->denominacion_rs);
                  } else {
                    $mov_beneficiario = $JwtAuth->desencriptar($vProv->paterno) . " " . $JwtAuth->desencriptar($vProv->materno) . " " . $JwtAuth->desencriptar($vProv->nombre);
                  }
                }
              }

              if ($vAjus->aj_cliente != NULL) {
                $query_cliente = DB::table("ingr_catalogo_clientes AS catcli")
                  ->join("sos_personas AS people", "catcli.cliente", "=", "people.id")
                  ->where(["catcli.id" => $vAjus->aj_cliente])->get();
                foreach ($query_cliente as $vClient) {
                  if ($vClient->denominacion_rs != NULL) {
                    $mov_beneficiario = $JwtAuth->desencriptar($vClient->denominacion_rs);
                  } else {
                    $mov_beneficiario = $JwtAuth->desencriptar($vClient->paterno) . " " . $JwtAuth->desencriptar($vClient->materno) . " " . $JwtAuth->desencriptar($vClient->nombre);
                  }
                }
              }

              if ($vAjus->aj_empleado != NULL) {
                $query_personal = DB::table("vhum_empleados_catalogo AS emple")
                  ->join("sos_personas AS people", "emple.personal", "=", "people.id")
                  ->where(["emple.id" => $vAjus->aj_empleado])->get();
                foreach ($query_personal as $vPers) {
                  $mov_beneficiario = $JwtAuth->desencriptar($vPers->paterno) . " " . $JwtAuth->desencriptar($vPers->materno) . " " . $JwtAuth->desencriptar($vPers->nombre);
                }
              }

              $ajuste_folio = $JwtAuth->generarFolio($vAjus->folio_ajuste);
              $select_ajuste = DB::select("SELECT FORMAT(?,?) AS total", [$vAjus->saldo_ajuste, $decimalesMoneda[0]->decimales]);
              $mov_monto = $select_ajuste[0]->total;
            }
          }

          $pago_folio = "";
          if ($v_mov->pago != NULL) {
            $query_pago = DB::table("fnzs_pagos_pago AS payment")
              ->join("fnzs_catalogos_cuentas AS count_cat", "payment.cuenta_bancaria", "=", "count_cat.id")
              ->where(["payment.id" => $v_mov->pago, "count_cat.token_cuenta" => $token_cuenta])->get();
            foreach ($query_pago as $vPago) {

              if ($vPago->proveedor != NULL) {
                $query_prov = DB::table("eegr_catalogo_proveedores AS catprov")
                  ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
                  ->where(["catprov.id" => $vPago->proveedor])->get();
                foreach ($query_prov as $vProv) {
                  if ($vProv->denominacion_rs != NULL) {
                    $mov_beneficiario = $JwtAuth->desencriptar($vProv->denominacion_rs);
                  } else {
                    $mov_beneficiario = $JwtAuth->desencriptar($vProv->paterno) . " " . $JwtAuth->desencriptar($vProv->materno) . " " . $JwtAuth->desencriptar($vProv->nombre);
                  }
                }
              }

              if ($vPago->cliente != NULL) {
                $query_cliente = DB::table("ingr_catalogo_clientes AS catcli")
                  ->join("sos_personas AS people", "catcli.clientes", "=", "people.id")
                  ->where(["catcli.id" => $vPago->cliente])->get();
                foreach ($query_cliente as $vClient) {
                  if ($vClient->denominacion_rs != NULL) {
                    $mov_beneficiario = $JwtAuth->desencriptar($vClient->denominacion_rs);
                  } else {
                    $mov_beneficiario = $JwtAuth->desencriptar($vClient->paterno) . " " . $JwtAuth->desencriptar($vClient->materno) . " " . $JwtAuth->desencriptar($vClient->nombre);
                  }
                }
              }

              if ($vPago->empleado != NULL) {
                $query_personal = DB::table("vhum_empleados_catalogo AS emple")
                  ->join("sos_personas AS people", "emple.personal", "=", "people.id")
                  ->where(["emple.id" => $vPago->empleado])->get();
                foreach ($query_personal as $vPers) {
                  $mov_beneficiario = $JwtAuth->desencriptar($vPers->paterno) . " " . $JwtAuth->desencriptar($vPers->materno) . " " . $JwtAuth->desencriptar($vPers->nombre);
                }
              }

              $pago_folio = $JwtAuth->generarFolio($vPago->folio_pagos);
              $select_pago = DB::select("SELECT FORMAT(?,?) AS total", [$vPago->monto_pago, $decimalesMoneda[0]->decimales]);
              $mov_monto = $select_pago[0]->total;
            }
          }

          $cobro_folio = "";
          if ($v_mov->cobro != NULL) {
            $query_cobro = DB::table("fnzs_cobros_cobro AS cobrar")
              ->join("fnzs_catalogos_cuentas AS count_cat", "cobrar.cuenta_bancaria", "=", "count_cat.id")
              ->where(["cobrar.id" => $v_mov->cobro, "count_cat.token_cuenta" => $token_cuenta])->get();
            foreach ($query_cobro as $vCobro) {

              if ($vCobro->proveedor != NULL) {
                $query_prov = DB::table("eegr_catalogo_proveedores AS catprov")
                  ->join("sos_personas AS people", "catprov.proveedor", "=", "people.id")
                  ->where(["catprov.id" => $vCobro->proveedor])->get();
                foreach ($query_prov as $vProv) {
                  if ($vProv->denominacion_rs != NULL) {
                    $mov_beneficiario = $JwtAuth->desencriptar($vProv->denominacion_rs);
                  } else {
                    $mov_beneficiario = $JwtAuth->desencriptar($vProv->paterno) . " " . $JwtAuth->desencriptar($vProv->materno) . " " . $JwtAuth->desencriptar($vProv->nombre);
                  }
                }
              }

              if ($vCobro->cliente != NULL) {
                $query_cliente = DB::table("ingr_catalogo_clientes AS catcli")
                  ->join("sos_personas AS people", "catcli.clientes", "=", "people.id")
                  ->where(["catcli.id" => $vCobro->cliente])->get();
                foreach ($query_cliente as $vClient) {
                  if ($vProv->denominacion_rs != NULL) {
                    $mov_beneficiario = $JwtAuth->desencriptar($vClient->denominacion_rs);
                  } else {
                    $mov_beneficiario = $JwtAuth->desencriptar($vClient->paterno) . " " . $JwtAuth->desencriptar($vClient->materno) . " " . $JwtAuth->desencriptar($vClient->nombre);
                  }
                }
              }

              if ($vCobro->empleado != NULL) {
                $query_personal = DB::table("vhum_empleados_catalogo AS emple")
                  ->join("sos_personas AS people", "emple.personal", "=", "people.id")
                  ->where(["emple.id" => $vCobro->empleado])->get();
                foreach ($query_personal as $vPers) {
                  $mov_beneficiario = $JwtAuth->desencriptar($vPers->paterno) . " " . $JwtAuth->desencriptar($vPers->materno) . " " . $JwtAuth->desencriptar($vPers->nombre);
                }
              }

              $cobro_folio = $JwtAuth->generarFolio($vCobro->folio_cobros);
              $select_cobro = DB::select("SELECT FORMAT(?,?) AS total", [$vCobro->monto_cobro, $decimalesMoneda[0]->decimales]);
              $mov_monto = $select_cobro[0]->total;
            }
          }

          $row = array(
            "token_movimiento" => $token_movimiento,
            //"token_movimiento" => "token_movimiento",
            "folio_movimiento" => $folio_movimiento,
            "fecha_movimiento" => $fecha_movimiento,
            "tipo_movimiento" => $v_mov->tipo_movimiento,
            "sub_tipo_mov" => $v_mov->subtipo_movimiento,
            "subtipo_movimiento" => $subtipo_movimiento,
            "realizo_movimiento" => $realizo_movimiento,
            //cuenta
            "token_cuenta" => $token_cuenta,
            "folio_cuenta" => $folio_cuenta,
            //bancos
            "token_bancos" => $token_bancos,
            "banco_clave" => $banco_clave,
            "banco_nombre_comercial" => $banco_nombre_comercial,
            "banco_razon_social" => $banco_razon_social,
            "banco_imagen" => $banco_imagen,
            "numero_cuenta_back" => $numero_cuenta,
            "numero_cuenta" => "",
            //movimientos
            "mov_monto" => "$" . $mov_monto,
            "mov_beneficiario" => $mov_beneficiario,
            //ajustes
            "ajuste_folio" => $ajuste_folio,
            //pagos
            "pago_folio" => $pago_folio,
            //cobros
            "cobro_folio" => $cobro_folio,
          );
          $arrayMovimientos[] = $row;
        }
        $cuenta_result_saldo = $cuentaControl->saldoCuentaByToken($decimalesMoneda[0]->e_moneda_decimales, $token_cuenta, $empresa);
        $cuenta_query_saldo = DB::select("SELECT FORMAT(?,?) AS total", [$cuenta_result_saldo, $decimalesMoneda[0]->e_moneda_decimales]);
        $saldo_cuenta = $cuenta_query_saldo[0]->total;
        //echo $cuenta_result_saldo;

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "saldo_cuenta" => "$" . $saldo_cuenta,
          "movimientos" => $arrayMovimientos,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registra_ajuste_cuenta_autorizado(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cuenta' => 'required|string',
      'tipo_de_poliza' => 'required|string',
      'forma_operacion' => 'required|string',
      'fecha_movimiento' => 'required|string',
      'origen_destino_movimiento' => 'required|string',
      'token_cliente' => 'nullable|string',
      'token_proveedor' => 'nullable|string',
      'token_empleado' => 'nullable|string',
      'cfdi_data' => 'nullable|array',
      'saldo_ajuste' => 'required|numeric',
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
      $token_cuenta = $request->input('token_cuenta');
      $tipo_de_poliza = $request->input('tipo_de_poliza');
      $forma_operacion = $request->input('forma_operacion');
      $fecha_movimiento = $request->input('fecha_movimiento');
      $origen_destino_movimiento = $request->input('origen_destino_movimiento');
      $token_cliente = $request->input('token_cliente');
      $token_proveedor = $request->input('token_proveedor');
      $token_empleado = $request->input('token_empleado');
      $cfdi_data = $request->input('cfdi_data');
      $saldo_ajuste = $request->input('saldo_ajuste');

      $OKTokenCuenta = isset($token_cuenta) && !empty($token_cuenta);
      $OKTipoDePoliza = isset($tipo_de_poliza) && !empty($tipo_de_poliza) && preg_match($JwtAuth->filtroAlfaNumerico(), $tipo_de_poliza);
      $OKFormaOperacion = isset($forma_operacion) && !empty($forma_operacion) && preg_match($JwtAuth->filtroAlfaNumerico(), $forma_operacion);
      $OKFechaMovimiento = isset($fecha_movimiento) && !empty($fecha_movimiento) && preg_match($JwtAuth->filtroFecha(), $fecha_movimiento);
      $OKOrigenDestinoMovimiento = isset($origen_destino_movimiento) && !empty($origen_destino_movimiento) && preg_match($JwtAuth->filtroAlfaNumerico(), $origen_destino_movimiento);
      $OKClientProvEmple = ((isset($token_cliente) && !empty($token_cliente)) || (isset($token_proveedor) && !empty($token_proveedor)) || (isset($token_empleado) && !empty($token_empleado)));
      $OKSaldoAjuste = isset($saldo_ajuste) && !empty($saldo_ajuste) && preg_match($JwtAuth->filtroCostoPrecio(), $saldo_ajuste);

      if ($OKTokenCuenta && $OKTipoDePoliza && $OKFormaOperacion && $OKFechaMovimiento && $OKOrigenDestinoMovimiento && $OKClientProvEmple && $OKSaldoAjuste) {
        $cuentaControl = new FNZS_CuentBancController();
        $validaDesglose = false;
        if (count($cfdi_data) > 0) {
          $countValidate = 0;
          for ($i = 0; $i < count($cfdi_data); $i++) {
            $fecha_emision = $cfdi_data[$i]["fecha_emision"];
            $folio_interno = $cfdi_data[$i]["folio_interno"];
            $folio_fiscal = $cfdi_data[$i]["folio_fiscal"];
            $metodo_pago_token = $cfdi_data[$i]["metodo_pago_token"];
            $forma_pago_token = $cfdi_data[$i]["forma_pago_token"];
            $moneda_token = $cfdi_data[$i]["moneda_token"];
            $importe_total = $cfdi_data[$i]["importe_total"];
            $importe_aplicado = $cfdi_data[$i]["importe_aplicado"];

            if (
              isset($fecha_emision) && !empty($fecha_emision) && preg_match($JwtAuth->filtroFecha(), $fecha_emision) &&
              isset($folio_interno) && !empty($folio_interno) && preg_match($JwtAuth->filtroAlfaNumerico(), $folio_interno) &&
              isset($folio_fiscal) && !empty($folio_fiscal) && preg_match($JwtAuth->filtroAlfaNumerico(), $folio_fiscal) &&
              isset($metodo_pago_token) && !empty($metodo_pago_token) && isset($forma_pago_token) && !empty($forma_pago_token) &&
              isset($moneda_token) && !empty($moneda_token) &&
              isset($importe_total) && !empty($importe_total) && preg_match($JwtAuth->filtroCostoPrecio(), $importe_total) &&
              isset($importe_aplicado) && !empty($importe_aplicado) && preg_match($JwtAuth->filtroCostoPrecio(), $importe_aplicado)
            ) {
              ++$countValidate;
            } else {
              if (!isset($fecha_emision) || empty($fecha_emision) || !preg_match($JwtAuth->filtroFecha(), $fecha_emision)) {
                $mensaje_error = 'error en Fecha de emisión (CFDI), verifique su información';
              }

              if (!isset($folio_interno) || empty($folio_interno) || !preg_match($JwtAuth->filtroAlfaNumerico(), $folio_interno)) {
                $mensaje_error = 'error en folio interno (CFDI), verifique su información';
              }

              if (!isset($folio_fiscal) || empty($folio_fiscal) || !preg_match($JwtAuth->filtroAlfaNumerico(), $folio_fiscal)) {
                $mensaje_error = 'error en UIDD (FOLIO FISCAL CFDI), verifique su información';
              }

              if (!isset($metodo_pago_token) || empty($metodo_pago_token)) {
                $mensaje_error = 'error en método de pago (CFDI), verifique su información';
              }

              if (!isset($forma_pago_token) || empty($forma_pago_token)) {
                $mensaje_error = 'error en forma de pago (CFDI), verifique su información';
              }

              if (!isset($moneda_token) || empty($moneda_token)) {
                $mensaje_error = 'error en moneda (CFDI), verifique su información';
              }

              if (!isset($importe_total) || empty($importe_total) || !preg_match($JwtAuth->filtroCostoPrecio(), $importe_total)) {
                $mensaje_error = 'error en importe total de factura, verifique su información';
              }

              if (!isset($importe_aplicado) || empty($importe_aplicado) || !preg_match($JwtAuth->filtroCostoPrecio(), $importe_aplicado)) {
                $mensaje_error = 'error en importe aplicado de factura, verifique su información';
              }
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => $mensaje_error
              );
              break;
            }
          }

          if ($countValidate == count($cfdi_data)) {
            $validaDesglose = true;
          } else {
            $validaDesglose = false;
          }
        } else {
          $validaDesglose = true;
        }

        if ($validaDesglose == true) {
          $decimalesMoneda = DB::select(
            "SELECT catmon.token_monedas,catmon.decimales FROM teci_catalogo_monedas AS catmon 
                          JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                          JOIN teci_usuarios_catalogo AS users WHERE emp.e_moneda = catmon.id AND emp.empresa_token = ?
                          AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                          AND pers.usuario = users.id AND users.usuario_token = ?",
            [$empresa, $usuario]
          );

          $cuenta_result_saldo = $cuentaControl->saldoCuentaByToken($decimalesMoneda[0]->token_monedas, $token_cuenta, $empresa, $usuario);
          if ($cuenta_result_saldo > 0 && $cuenta_result_saldo > $saldo_ajuste) {
            $fechaSistema = time();

            $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr FROM main_empresas AS emp
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.personal = pers.id
                    AND pers.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

            $saldo_cuenta = "0.00";

            if ($tipo_de_poliza == "ing") {
              $save_tipo_poliza = "D";
            } else if ($tipo_de_poliza == "egr") {
              $save_tipo_poliza = "R";
            } else {
              $save_tipo_poliza = "P";
            }

            $save_fecha_mov = $JwtAuth->convierteFechaEpoc($fecha_movimiento);

            if ($origen_destino_movimiento == "cliente") {
              $selectCliente = DB::select("SELECT id FROM ingr_catalogo_clientes WHERE token_cat_clientes = ?", [$token_cliente]);
              $save_cliente = $selectCliente[0]->id;
              $save_proveedor = NULL;
              $save_empleado = NULL;
            } else if ($origen_destino_movimiento == "proveedor") {
              $save_cliente = NULL;
              $selectProveedor = DB::select("SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?", [$token_proveedor]);
              $save_proveedor = $selectProveedor[0]->id;
              $save_empleado = NULL;
            } else if ($origen_destino_movimiento == "empleado") {
              $save_cliente = NULL;
              $save_proveedor = NULL;
              $selectPers = DB::select("SELECT id FROM vhum_empleados_catalogo WHERE pers_token = ?", [$token_empleado]);
              $save_empleado = $selectPers[0]->id;
            }

            $folioAjuste = DB::select("SELECT IF (max(ajust.folio_ajuste) IS NOT NULL,(max(ajust.folio_ajuste)+1),1) AS folio
                              FROM fnzs_catalogos_cuentas_ajustes AS ajust JOIN fnzs_catalogos_cuentas AS acount 
                              WHERE ajust.cuenta_bancaria = acount.id AND acount.token_cuenta = ?", [$token_cuenta]);

            $selectCuenta = DB::select(
              "SELECT cuent.id FROM fnzs_catalogos_cuentas AS cuent JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                              JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE cuent.token_cuenta = ? AND cuent.empresa = emp.id AND emp.empresa_token = ? 
                              AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
              [$token_cuenta, $empresa, $usuario]
            );

            $tokenAjuste = $JwtAuth->encriptarToken($fechaSistema . $token_cuenta . $tipo_de_poliza . $forma_operacion . $fecha_movimiento .
              $origen_destino_movimiento . $token_cliente . $token_proveedor . $token_empleado . $saldo_ajuste);

            $insertAjuste = DB::table("fnzs_catalogos_cuentas_ajustes")->insert(
              array(
                "token_ajuste" => $tokenAjuste,
                "fecha_sistema" => $fechaSistema,
                "folio_ajuste" => $folioAjuste[0]->folio,
                "cuenta_bancaria" => $selectCuenta[0]->id,
                "tipo_movimiento" => $save_tipo_poliza,
                "forma_operacion" => $forma_operacion,
                "fecha_ajuste" => $save_fecha_mov,
                "origen_destino" => $origen_destino_movimiento,
                "aj_cliente" => $save_cliente,
                "aj_proveedor" => $save_proveedor,
                "aj_empleado" => $save_empleado,
                "saldo_ajuste" => $saldo_ajuste,
              )
            );

            if ($insertAjuste) {
              $selectAjuste = DB::select("SELECT id FROM fnzs_catalogos_cuentas_ajustes WHERE token_ajuste = ?", [$tokenAjuste]);
              if (count($cfdi_data) > 0) {
                for ($i = 0; $i < count($cfdi_data); $i++) {
                  $fecha_emision = $cfdi_data[$i]["fecha_emision"];
                  $folio_interno = $cfdi_data[$i]["folio_interno"];
                  $folio_fiscal = $cfdi_data[$i]["folio_fiscal"];
                  $metodo_pago_token = $cfdi_data[$i]["metodo_pago_token"];
                  $forma_pago_token = $cfdi_data[$i]["forma_pago_token"];
                  $moneda_token = $cfdi_data[$i]["moneda_token"];
                  $importe_total = $cfdi_data[$i]["importe_total"];
                  $importe_aplicado = $cfdi_data[$i]["importe_aplicado"];

                  $tokenDesglose = $JwtAuth->encriptarToken($tokenAjuste . $fecha_emision . $folio_interno . $folio_fiscal . $metodo_pago_token .
                    $forma_pago_token . $moneda_token . $importe_total . $importe_aplicado);

                  $insertDesglose = DB::table("fnzs_catalogos_cuentas_ajustes_desglose")->insert(
                    array(
                      "token_desglose_aj" => $tokenDesglose,
                      "cuentas_ajustes" => $selectAjuste[0]->id,
                      "fecha_emision_cfdi" => $JwtAuth->convierteFechaEpoc($fecha_emision),
                      "folio_interno_cfdi" => $folio_interno,
                      "uuid_folio_fiscal" => $folio_fiscal,
                      "metodo_pago_cfdi" => $JwtAuth->getMetodoPago($metodo_pago_token),
                      "forma_pago_cfdi" => $JwtAuth->getFormaPago($forma_pago_token),
                      "moneda_cfdi" => $JwtAuth->getMoneda($moneda_token),
                      "importe_total" => $importe_total,
                      "importe_aplicado" => $importe_aplicado,
                    )
                  );
                }
              }

              $folioMovim = DB::select("SELECT IF (max(folio_movimiento) IS NOT NULL,(max(folio_movimiento)+1),1) AS folio
                                  FROM fnzs_actividad_movimientos AS movim JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                                  JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.empresa = emp.id 
                                  AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                  AND pers.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

              $insertMovimientos = DB::table("fnzs_actividad_movimientos")->insert(
                array(
                  "token_movimiento" => $JwtAuth->encriptarToken($tokenAjuste . $selectAjuste[0]->id . $folioMovim[0]->folio . 'A'),
                  "folio_movimiento" => $folioMovim[0]->folio,
                  "fecha_sistema" => $fechaSistema,
                  "seccion_movimiento" => 'tesorería',
                  "tipo_movimiento" => $save_tipo_poliza,
                  "subtipo_movimiento" => 'A',
                  "responsable" => $selectEmp[0]->userr,
                  "caja" => NULL,
                  "cuenta_bancaria" => $selectCuenta[0]->id,
                  "monto_aplicado" => $importe_aplicado,
                  "tipo_cambio_movimiento" => 1.000000,
                  "moneda_movimiento" => $JwtAuth->getMoneda($moneda_token),
                  "observaciones_movimiento" => NULL,
                  "cuenta_monedero" => NULL,
                  "pago" => NULL,
                  "cobro" => NULL,
                  "ajuste" => $selectAjuste[0]->id,
                  "empresa" => $selectEmp[0]->id
                )
              );

              if ($insertMovimientos) {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => "El registro de movimientos bancarios de la cuenta seleccionada se realizó correctamente con el folio M-" . $JwtAuth->generarFolio($folioMovim[0]->folio)
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => "El registro de movimientos bancarios de la cuenta seleccionada no se realizó correctamente, intente mas tarde o comuniquese a soporte"
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "El registro de ajuste de la cuenta seleccionada no se realizó correctamente, intente mas tarde o comuniquese a soporte"
              );
            }
          } else {
            $saldoCuenta = DB::select("SELECT FORMAT(?,?) AS total", [$cuenta_result_saldo, $moneda_datos[0]->decimales]);
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "cuenta bancaria " . $alias_cuenta . " sin fondos, saldo total: $" . $saldoCuenta[0]->total . ", saldo mínimo requerido: " . $pago_recibido_format
            );
          }
        }
      } else {
        if (!$OKTokenCuenta) {
          $mensaje_error = 'error en cuenta bancaria seleccionada, verifique su información';
        }

        if (!$OKTipoDePoliza) {
          $mensaje_error = 'error en tipo de poliza, verifique su información';
        }

        if (!$OKFormaOperacion) {
          $mensaje_error = 'error en forma de operación, verifique su información';
        }

        if (!$OKFechaMovimiento) {
          $mensaje_error = 'error en fecha de movimiento, verifique su información';
        }

        if (!$OKOrigenDestinoMovimiento) {
          $mensaje_error = 'error en origen/destino de movimiento, verifique su información';
        }

        if (!$OKClientProvEmple) {
          $mensaje_error = 'error en selección de cliente, proveedor o empleado, verifique su información';
        }

        if (!$OKSaldoAjuste) {
          $mensaje_error = 'error en monto de ajuste, verifique su información';
        }
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
