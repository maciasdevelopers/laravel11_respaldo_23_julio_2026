<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MonedElectModelo;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;
use App\Models\MovimientosBancariosModelo;

class FNZS_MonedElectController extends Controller{
  public function monederosElectronicos(){
    $arrayMonElectr = array();
    $monederoElectr = MonedElectModelo::all();
    foreach ($monederoElectr as  $valMonElect) {
      $arrayMonedero = array(
        "token_monelectronico" => $valMonElect->token_monelectronico,
        "nombre" => $valMonElect->nombre
      );
      $arrayMonElectr[] = $arrayMonedero;
    }

    return response()->json([
      'monedero' => $arrayMonElectr,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function responsableMonedero(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $selectEmp = DB::table("main_empresas AS emp")
    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->where([
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->select('emp.id AS id_emp','emp.zona_horaria')
    ->first();
    
    $respMonedero = CuentaMonederoModelo::join("teci_plataformas_digitales AS pdig", "fnzs_catalogos_cuentas_monedero.monedero", "pdig.id")
    ->join("vhum_empleados_catalogo AS pers", "fnzs_catalogos_cuentas_monedero.responsable", "pers.id")
    ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
    ->where([
      'fnzs_catalogos_cuentas_monedero.status' => TRUE,
      'fnzs_catalogos_cuentas_monedero.empresa' => $selectEmp->id_emp,
      'users.usuario_token' => $usuario
    ])
    ->where([
      'fnzs_catalogos_cuentas_monedero.egresos' => TRUE
    ])
    ->orwhere([
      'fnzs_catalogos_cuentas_monedero.v_humano' => TRUE
    ])->get();

    if ($respMonedero->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No existe cuenta de monedero electrónico asociada a este usuario'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $detalleMonedero = array();
      $arrayOpcionAdicional = array();
      
      foreach ($respMonedero as $resMonedero) {
        $cuenta_bancaria = '';
        $name_cuenta = '';
        $token_caja = '';
        $folio_caja = '';
        $alias_caja = '';

        //da_te_default_timezone_set($selectEmp->zona_horaria);

        if ($resMonedero->cuenta_banco != '') {
          $tknCount = DB::select("SELECT token_cuenta FROM cuenta WHERE id = ? ", [$resMonedero->cuenta_banco]);
          $cuentaBancoMon = CuentBancModelo::join("main_empresas AS emp", "cuenta.empresa", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
            ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
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
            ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
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

        $titular = $JwtAuth->desencriptar($resMonedero->titular);

        $moneda = DB::select("SELECT codigo,moneda FROM teci_catalogo_monedas WHERE id = ?", [$resMonedero->moneda]);
        $resMoneda = $moneda[0]->codigo . "-" . $moneda[0]->moneda;

        $egresos = (bool)$resMonedero->egresos;
        $v_humano = (bool)$resMonedero->v_humano;

        $selectManejCuenta = DB::table('fnzs_catalogos_cuentas_manejo')
          ->join("fnzs_catalogos_cuentas_monedero AS countMon", "fnzs_catalogos_cuentas_manejo.cuenta_monedero", "countMon.id")
          ->join("main_empresas AS emp", "fnzs_catalogos_cuentas_manejo.empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "pers.id")
          ->join("sos_personas AS people", "pers.personal", "people.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
          ->where([
            'fnzs_catalogos_cuentas_manejo.cuenta_bancaria' => NULL,
            'countMon.token_cuentamonedero' => $resMonedero->token_cuentamonedero,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario
          ])->get();

        foreach ($selectManejCuenta as $resOpciones) {
          $chequera = (bool)$resOpciones->chequera;
          $credito = (bool)$resOpciones->credito;
          $debito = (bool)$resOpciones->debito;

          $arrayOptions = array(
            "token_manejocuentas" => $resOpciones->token_manejocuentas,
            "chequera" => $chequera,
            "credito" => $credito,
            "debito" => $debito,
            "valorManejo" => $resOpciones->clave_referencia,
            "token_personal" => $resOpciones->pers_token,
            "nombre_completo" => $JwtAuth->desencriptar($resOpciones->paterno)." ".$JwtAuth->desencriptar($resOpciones->materno)." ".$JwtAuth->desencriptar($resOpciones->nombre),
          );
          $arrayOpcionAdicional[] = $arrayOptions;
        }

        $decimalesMoneda = DB::select(
          "SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.e_moneda = catmon.id AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token = ?",
          [$empresa, $usuario]
        );

        //suman
        $cobroVenta = DB::select(
          "SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_cobros_cobro AS cobrar JOIN fnzs_catalogos_cuentas_monedero AS moned JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = TRUE 
                            AND movim.subtipo_movimiento = 'V' AND movim.cobro = cobrar.id AND movim.cuenta_monedero = moned.id 
                            AND cobrar.cuenta_monedero = moned.id AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id 
                            AND cobrar.empresa = emp.id AND moned.empresa = emp.id AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa AND empuser.personal = pers.id AND moned.responsable = pers.id 
                            AND pers.usuario = users.id AND users.usuario_token = ?",
          [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $empresa, $usuario]
        );

        $devolucionCompra = DB::select(
          "SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_cobros_cobro AS cobrar JOIN fnzs_catalogos_cuentas_monedero AS moned JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = FALSE 
                            AND movim.subtipo_movimiento = 'D' AND movim.cobro = cobrar.id AND movim.cuenta_monedero = moned.id 
                            AND cobrar.cuenta_monedero = moned.id AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id 
                            AND cobrar.empresa = emp.id AND moned.empresa = emp.id AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa AND empuser.personal = pers.id AND moned.responsable = pers.id 
                            AND pers.usuario = users.id AND users.usuario_token = ?",
          [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $empresa, $usuario]
        );

        //restan
        $pagoCompra = DB::select(
          "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_cuentas_monedero AS moned JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = FALSE AND movim.subtipo_movimiento = 'C' 
                            AND movim.pago = payment.id AND movim.cuenta_monedero = moned.id AND payment.cuenta_monedero = moned.id
                            AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id AND payment.empresa = emp.id AND moned.empresa = emp.id 
                            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND moned.responsable = pers.id 
                            AND pers.usuario = users.id AND users.usuario_token = ?",
          [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $empresa, $usuario]
        );

        $devolucionVenta = DB::select(
          "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_cuentas_monedero AS moned JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.tipo_movimiento = TRUE AND movim.subtipo_movimiento = 'D' 
                            AND movim.pago = payment.id AND movim.cuenta_monedero = moned.id AND payment.cuenta_monedero = moned.id
                            AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id AND payment.empresa = emp.id AND moned.empresa = emp.id 
                            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND moned.responsable = pers.id 
                            AND pers.usuario = users.id AND users.usuario_token = ?",
          [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $empresa, $usuario]
        );

        $resultsalDoCuenta = $cobroVenta[0]->total + $devolucionCompra[0]->total - $pagoCompra[0]->total - $devolucionVenta[0]->total;
        $salDoCuenta = DB::select(
          "SELECT ROUND(?,?) AS saldoRound,FORMAT(?,?) AS saldoFormat",
          [$resultsalDoCuenta, $decimalesMoneda[0]->decimales, $resultsalDoCuenta, $decimalesMoneda[0]->decimales]
        );

        $arrayMonedero = array(
          'token_cuentaMon' => $resMonedero->token_cuentamonedero,
          'fecha_alta_cuentamoned' => gmdate('Y-m-d H:i:s', $resMonedero->fecha_alta_cuentamoned),
          'folio' => $JwtAuth->generar($resMonedero->folio_cuentmon),

          'cuenta_bancaria' =>  $cuenta_bancaria,
          'name_cuenta_bancaria' =>  $name_cuenta,

          'token_caja' => $token_caja,
          'folio_caja' => $folio_caja,
          'alias_caja' => $alias_caja,

          'referencia_encrypt' => $resMonedero->referencia,
          'referencia' => $resMonedero->referencia,
          'cuenta_monedero_encrypt' => $resMonedero->cuenta,
          'cuenta_monedero' => $resMonedero->cuenta,
          'clabe_inter_encrypt' => $resMonedero->clabe_inter,
          'clabe_inter' => $resMonedero->clabe_inter,
          'titular' => $titular,
          'moneda' => $resMoneda,
          'egresos' => $egresos,
          'v_humano' => $v_humano,
          'vigencia' => gmdate('Y-m-d H:i:s', $resMonedero->vigencia),
          'opciones_adicionales' => $arrayOpcionAdicional,
          'saldofloat' => $salDoCuenta[0]->saldoRound,
          'salDoCuenta' => "$" . $salDoCuenta[0]->saldoFormat,
        );

        $detalleMonedero[] = $arrayMonedero;
      }
      $dataMensaje = array(
        'monedero' => $detalleMonedero,
        'code' => 200,
        'status' => 'success'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);


    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);



    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'Monedero electrónico invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);




        //echo 'coun caja '.count($respMonedero); 
        if (count($respMonedero) != 0) {

        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'No existe cuenta de monedero electrónico asociada a este usuario',
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'Los datos no son correctos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function folioMonederoElectronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    $folioMonedero = DB::select("SELECT IF (max(folio_cuentmon) IS NOT NULL,(max(folio_cuentmon)+1),1) AS folio FROM cuenta_monedero AS monedero 
      JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE monedero.empresa = emp.id 
      AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
      [$empresa, $usuario]
    );

    return response()->json([
      'monedero' => $JwtAuth->generar($folioMonedero[0]->folio),
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function ListaMonederoVig(Request $request){
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
      
      $queryMonederos = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas_monedero.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("fnzs_catalogos_cuentas_monedero.fecha_alta_cuentamoned", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('fnzs_catalogos_cuentas_monedero.id', 'DESC')
      ->get();

      if ($queryMonederos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron monederos electrónicos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $listaMonedero = array();

        foreach ($queryMonederos as $vMoned) {
          $moneda_decimales = $JwtAuth->getMonedaAPI($vMoned->moneda);
          $cuenta_result_saldo = $this->saldoMonederoByToken($vMoned->token_cuentamonedero, $empresa);
          $folio_cuenta = "CUENTM-" . $JwtAuth->generarFolio($vMoned->folio_cuentmon);
          $cuenta_descifrada_substr = substr(substr($JwtAuth->decryptBankAccount($vMoned->cuenta), -4), -4);
          $arrayMonedero = array(
            "folio_cuenta" => $folio_cuenta,
            "token_cuentaMon" => $vMoned->token_cuentamonedero,
            "cuenta_monedero" => "**** **** **** $cuenta_descifrada_substr",
            "egresos" => $vMoned->egresos ? true : false,
            "ingresos" => $vMoned->ingresos ? true : false,
            "v_humano" => $vMoned->v_humano ? true : false,
            "plataforma_electronica" => $JwtAuth->desencriptar($vMoned->plataforma_electronica),
            "saldo_cuenta" => $cuenta_result_saldo,
            "saldo_cuenta_format" => "$" . number_format($cuenta_result_saldo, $moneda_decimales, '.', ',') . " $vMoned->moneda",
            "aplicable_disabled" => true,
            "select_for_pagos" => false,
            //"disponible" => $vSal->disponible ? true : false,
            "monto_aplicar" => 0,
            "_filtro_busqueda" => "$folio_cuenta **** **** **** $cuenta_descifrada_substr",
          );
          $listaMonedero[] = $arrayMonedero;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'monedero' => $listaMonedero,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function ListaMonederoDel(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryMonedero = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->where([
      'fnzs_catalogos_cuentas_monedero.status' => FALSE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->orderBy('fnzs_catalogos_cuentas_monedero.id', 'DESC')
    ->get();

    if ($queryMonedero->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron monederos electrónicos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaMonedero = array();
      
      foreach ($queryMonedero as $vMoned) {
        //da_te_default_timezone_set($vMoned->zona_horaria);
        $arrayMonedero = array(
          "folio_cuenta" => "CUENTM-" . $JwtAuth->generarFolio($vMoned->folio_cuentmon),
          "token_cuentaMon" => $vMoned->token_cuentamonedero,
          "cuenta_backend" => $vMoned->cuenta,
          "cuenta_frontend" => $vMoned->cuenta,
          "egresos" => $vMoned->egresos ? true : false,
          "ingresos" => $vMoned->ingresos ? true : false,
          "v_humano" => $vMoned->v_humano ? true : false,
          "plataforma_electronica" => $JwtAuth->desencriptar($vMoned->plataforma_electronica),
          "fecha_delete" => gmdate('Y-m-d H:i:s', $vMoned->fecha_delete_mon)
        );
        $listaMonedero[] = $arrayMonedero;
      }
      
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'monedero' => $listaMonedero,
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleMonederoVig(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_monedero' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_monedero = $request->input('token_monedero');
      
      $queryMonedero = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas_monedero.status' => TRUE,
        'fnzs_catalogos_cuentas_monedero.token_cuentamonedero' => $token_monedero,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();

      if ($queryMonedero->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron monederos electrónicos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $detalleMonedero = array();

        foreach ($queryMonedero as $vMoned) {
          $arrayOpcionAdicional = array();

          $selectManejo = DB::table("fnzs_catalogos_cuentas_medios_operacion AS medOper")
          ->join("fnzs_catalogos_cuentas_monedero AS countMon", "medOper.cuenta_monedero", "countMon.id")
          ->where('countMon.token_cuentamonedero', $vMoned->token_cuentamonedero)
          ->get();

          $num_lista = 1;
          foreach ($selectManejo as $vMan) {
            $optionAddc = array(
              "token_medio_operacion" => $vMan->token_medio_operacion,
              "num_lista" => $num_lista,
              "clave" => $vMan->medio_operacion,
              "valor" => $vMan->referencia_operacion,
              "vigencia" => !empty($vMan->vigencia) ? $vMan->vigencia : '---',
              "proceso_eliminacion" => false,
            );
            ++$num_lista;
            $arrayOpcionAdicional[] = $optionAddc;
          }

          $queryEmpResp = DB::table("sos_personas AS people")
          ->join("vhum_empleados_catalogo AS pers", "people.id", "pers.empleado_name")
          ->join("fnzs_catalogos_cuentas_monedero AS cmoned", "pers.id", "cmoned.responsable")
          ->where('cmoned.token_cuentamonedero',$vMoned->token_cuentamonedero)
          ->select('pers.empleado_token','pers.folio_pers','people.paterno','people.materno', 'people.nombre')
          ->first();

          $p_responsable_folio = $queryEmpResp ? "TRB-" . $JwtAuth->generarFolio($queryEmpResp->folio_pers) : "";
          $p_responsable_paterno = $queryEmpResp ? ucwords($JwtAuth->desencriptar($queryEmpResp->paterno)) : "";
          $p_responsable_materno = $queryEmpResp ? ucwords($JwtAuth->desencriptar($queryEmpResp->materno)) : "";
          $p_responsable_nombre = $queryEmpResp ? ucwords($JwtAuth->desencriptar($queryEmpResp->nombre)) : "";
          $responsable_name = $queryEmpResp ? "$p_responsable_folio $p_responsable_paterno $p_responsable_materno $p_responsable_nombre" : "";

          $caja_folio = $vMoned->caja ? "CAJ-" . $JwtAuth->generarFolio(DB::table("fnzs_catalogos_caja")->where("id", $vMoned->caja)->value("no_caja")) : '';
          $caja_alias = $vMoned->caja ? $JwtAuth->desencriptar(DB::table("fnzs_catalogos_caja")->where("id", $vMoned->caja)->value("alias_caja")) : '';

          $cuenta_folio = $vMoned->cuenta_banco ? 'CUENT-' . $JwtAuth->generarFolio(DB::table("fnzs_catalogos_cuentas")->where("id", $vMoned->cuenta_banco)->value("folio_cuenta")) : '';
          $banco_nombre_comercial = $vMoned->cuenta_banco ? DB::table("teci_bancos AS bank")->join("fnzs_catalogos_cuentas AS acc", "bank.id", "acc.banco")->where("acc.id", $vMoned->cuenta_banco)->value("bank.nombre_comercial") : '';
          $cuenta_descifrada = $vMoned->cuenta_banco ? substr($JwtAuth->decryptBankAccount(DB::table("fnzs_catalogos_cuentas")->where("id", $vMoned->cuenta_banco)->value("cuenta")), -4) : '';

          $arrayMonedero = array(
            'token_cuentaMon' => $vMoned->token_cuentamonedero,
            'folio' => $JwtAuth->generar($vMoned->folio_cuentmon),
            'plataforma_electronica' => $JwtAuth->desencriptar($vMoned->plataforma_electronica),
            'referencia' => $JwtAuth->decryptBankAccount($vMoned->referencia),
            'cuenta' => $JwtAuth->decryptBankAccount($vMoned->cuenta),
            'clabe_inter' => $JwtAuth->decryptBankAccount($vMoned->clabe_inter),
            'titular' => $JwtAuth->decryptBankAccount($vMoned->titular),
            'cuenta_contable' => $vMoned->mon_cuenta_contable,
            'moneda' => $vMoned->moneda,
            'mon_egresos' => $vMoned->egresos ? true : false,
            'mon_ingresos' => $vMoned->ingresos ? true : false,
            'mon_v_humano' => $vMoned->v_humano ? true : false,
            'vigencia' => date('Y-m', $vMoned->vigencia),
            'medios_operacion' => $arrayOpcionAdicional,
            'responsable_token' => $queryEmpResp ? $queryEmpResp->empleado_token : '',
            'responsable_name' => $responsable_name,
            'caja_token' => $vMoned->caja ? DB::table("fnzs_catalogos_caja")->where("id", $vMoned->caja)->value("token_caja") : '',
            'caja_alias' => "$caja_folio $caja_alias",
            'cuenta_banco_token' => $vMoned->cuenta_banco ? DB::table("fnzs_catalogos_cuentas")->where("id", $vMoned->cuenta_banco)->value("token_cuenta") : '',
            'cuenta_banco_numero' => $vMoned->cuenta_banco ? "**** **** **** $cuenta_descifrada" : '',
            'cuenta_filtro' => $vMoned->cuenta_banco ? "$banco_nombre_comercial $cuenta_folio **** **** **** $cuenta_descifrada" : ''
          );
          $detalleMonedero[] = $arrayMonedero;
        }

        $dataMensaje = array(
          'monedero' => $detalleMonedero,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registrarMonederoElectronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'plataforma_electronica' => 'required|string',
      'no_referencia' => 'required|string',
      'cuenta' => 'required|string',
      'clabe_inter' => 'required|string',
      'titularCuenta' => 'required|string',
      'cuenta_contable' => 'required|string',
      'moneda' => 'required|string',
      'egresos' => 'required|boolean',
      'ingresos' => 'required|boolean',
      'v_Humano' => 'required|boolean',
      'mediosOperacion' => 'array',
      'token_responsable' => 'string',
      'token_cuenta_bancaria' => 'string',
      'caja' => 'string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $plataforma_electronica = $request->input('plataforma_electronica');
      $no_referencia = $request->input('no_referencia');
      $cuenta = $request->input('cuenta');
      $clabe_inter = $request->input('clabe_inter');
      $titularCuenta = $request->input('titularCuenta');
      $cuenta_contable = $request->input('cuenta_contable');
      $moneda = $request->input('moneda');
      $egresos = $request->input('egresos');
      $ingresos = $request->input('ingresos');
      $v_Humano = $request->input('v_Humano');
      $medios_operacion = $request->input('mediosOperacion');
      $token_responsable = $request->input('token_responsable');
      $token_cuentaBanc = $request->input('token_cuenta_bancaria');
      $token_caja = $request->input('caja');
      
      $selectEmp = DB::table("main_empresas AS emp")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select('emp.id AS id_emp','emp.zona_horaria')
      ->first();

      //da_te_default_timezone_set($selectEmp->zona_horaria);

      $folioMonedero = DB::select("SELECT IF (max(folio_cuentmon) IS NOT NULL,(max(folio_cuentmon)+1),1) AS folio 
        FROM fnzs_catalogos_cuentas_monedero AS monedero JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
        JOIN teci_usuarios_catalogo AS users WHERE monedero.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
        AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);
      $folio_cuenta = "CUENTM-".$JwtAuth->generarFolio($folioMonedero[0]->folio);

      $pers_responsable = DB::table("vhum_empleados_catalogo")->where("empleado_token", $token_responsable)->value("id");
      $cuenta_banco = !empty($token_cuentaBanc) ? DB::table("fnzs_catalogos_cuentas")->where("token_cuenta", $token_cuentaBanc)->value("id") : NULL;
      $caja = !empty($token_caja) ? DB::table("fnzs_catalogos_caja")->where("token_caja", $token_caja)->value("id") : NULL;

      if (count($medios_operacion) != 0) {
        for ($i = 0; $i < count($medios_operacion); $i++) {
          $error_medios_operacion = "";
          if ($medios_operacion[$i]['clave'] == '') $error_medios_operacion = "Error en manejo de medios de operación";
          if ($medios_operacion[$i]['valor'] == '') $error_medios_operacion = "Error en la referencia de opciones adicionales";
          $dataMensaje = array('status' => 'error', 'code' => 400, 'message' => $error_medios_operacion);
        }
      }

      $tokenMonedero = $JwtAuth->encriptarToken(time(), $plataforma_electronica, $cuenta, $folioMonedero[0]->folio, $moneda, $egresos, $ingresos, $v_Humano, $pers_responsable, $cuenta_banco);
      $newMonedero = new CuentaMonederoModelo();
      $newMonedero->token_cuentamonedero = $tokenMonedero;
      $newMonedero->folio_cuentmon = $folioMonedero[0]->folio;
      $newMonedero->fecha_alta_cuentamoned = time();
      $newMonedero->plataforma_electronica = $JwtAuth->encriptar($plataforma_electronica);
      $newMonedero->referencia = $JwtAuth->encryptBankAccount($no_referencia);
      $newMonedero->cuenta = $JwtAuth->encryptBankAccount($cuenta);
      $newMonedero->clabe_inter = $JwtAuth->encryptBankAccount($clabe_inter);
      $newMonedero->titular = $JwtAuth->encryptBankAccount($titularCuenta);
      $newMonedero->mon_cuenta_contable = $cuenta_contable;
      $newMonedero->moneda = $moneda;
      $newMonedero->egresos = $egresos;
      $newMonedero->ingresos = $ingresos;
      $newMonedero->v_humano = $v_Humano;
      $newMonedero->responsable = $pers_responsable;
      $newMonedero->cuenta_banco = $cuenta_banco;
      $newMonedero->caja = $caja;
      $newMonedero->status = TRUE;
      $newMonedero->fecha_delete_mon = '';
      $newMonedero->empresa = $selectEmp[0]->id;
      $savedMonedero = $newMonedero->save();

      if ($savedMonedero) {
        $cuentaMon = $newMonedero->id;
        if (count($medios_operacion) != 0) {
          for ($i = 0; $i < count($medios_operacion); $i++) {
            $clave = $medios_operacion[$i]['clave'];
            $valor = $medios_operacion[$i]['valor'];
            $vigencia = isset($medios_operacion[$i]['vigencia']) && !empty($medios_operacion[$i]['vigencia']) ? $medios_operacion[$i]['vigencia'] : NULL;

            DB::table('fnzs_catalogos_cuentas_medios_operacion')
            ->insert(array(
              "token_medio_operacion" => $JwtAuth->encriptarToken($cuentaMon . $clave . $valor . $vigencia),
              "cuenta_monedero" => $cuentaMon,
              "medio_operacion" => $clave,
              "referencia_operacion" => $valor,
              "vigencia" => !empty($vigencia) ? $vigencia : NULL,
              "empresa" => $selectEmp[0]->id,
            ));
          }
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => "Monedero electrónico registrado correctamente con el folio $folio_cuenta"
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'Los datos del monedero electrónico no son correctos, error al intentar registrar'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateMonederoElectronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cuentaMon' => 'required|string',
      'plataforma_electronica' => 'required|string',
      'no_referencia' => 'required|string',
      'cuenta' => 'required|string',
      'clabe_inter' => 'required|string',
      'titularCuenta' => 'required|string',
      'cuenta_contable' => 'required|string',
      'moneda' => 'required|string',
      'mediosOperacionNuevos' => 'array',
      'mediosOperacionDelete' => 'array',
      'egresos' => 'required|boolean',
      'ingresos' => 'required|boolean',
      'v_Humano' => 'required|boolean',
      'token_responsable' => 'string',
      'token_cuenta_bancaria' => 'string',
      'caja' => 'string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_cuentaMon = $request->input('token_cuentaMon');
      $plataforma_electronica = $request->input('plataforma_electronica');
      $no_referencia = $request->input('no_referencia');
      $cuenta = $request->input('cuenta');
      $clabe_inter = $request->input('clabe_inter');
      $titularCuenta = $request->input('titularCuenta');
      $cuenta_contable = $request->input('cuenta_contable');
      $moneda = $request->input('moneda');
      $mediosOperacionNuevos = $request->input('mediosOperacionNuevos');
      $mediosOperacionDelete = $request->input('mediosOperacionDelete');
      $egresos = $request->input('egresos');
      $ingresos = $request->input('ingresos');
      $v_Humano = $request->input('v_Humano');
      $token_responsable = $request->input('token_responsable');
      $token_cuenta_bancaria = $request->input('token_cuenta_bancaria');
      $caja = $request->input('caja');

      $OKCuentaMon = isset($token_cuentaMon) && !empty($token_cuentaMon);
      $OKPlatElect = isset($plataforma_electronica) && !empty($plataforma_electronica) && preg_match($JwtAuth->filtroAlfaNumerico(),$plataforma_electronica);
      $OKNoReferen = isset($no_referencia) && !empty($no_referencia) && preg_match($JwtAuth->filtroNumericoSimple(),$no_referencia);
      $OKCuenta = isset($cuenta) && !empty($cuenta) && preg_match($JwtAuth->filtroNumericoSimple(),$cuenta);
      $OKClabeInte = isset($clabe_inter) && !empty($clabe_inter) && preg_match($JwtAuth->filtroNumericoSimple(),$clabe_inter);
      $OKTitularCu = isset($titularCuenta) && !empty($titularCuenta) && preg_match($JwtAuth->filtroAlfaNumerico(),$titularCuenta);
      $OKMoneda = isset($moneda) && !empty($moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$moneda);
      $OKMedOpeNew = isset($mediosOperacionNuevos) && is_array($mediosOperacionNuevos) && count($mediosOperacionNuevos) > 0;
      $OKMedOpeDel = isset($mediosOperacionDelete) && is_array($mediosOperacionDelete) && count($mediosOperacionDelete) > 0;
      $OKMedOperac = $OKMedOpeNew || $OKMedOpeDel;
      $OKEgresos = isset($egresos) && is_bool($egresos);
      $OKIngresos = isset($ingresos) && is_bool($ingresos);
      $OKV_Humano = isset($v_Humano) && is_bool($v_Humano);
      $OKResponsab = isset($token_responsable) && !empty($token_responsable);
      $OKCuentaBan = isset($token_cuenta_bancaria) && !empty($token_cuenta_bancaria);
      $OKCaja = isset($caja) && !empty($caja);

      if ($OKCuentaMon || $OKPlatElect || $OKNoReferen || $OKCuenta || $OKClabeInte || $OKTitularCu || $OKMoneda || $OKMedOperac || $OKEgresos || $OKIngresos || $OKV_Humano || $OKResponsab || $OKCuentaBan || $OKCaja) {
        $consultCuentaMon = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            'fnzs_catalogos_cuentas_monedero.token_cuentamonedero' => $token_cuentaMon,
            'fnzs_catalogos_cuentas_monedero.status' => TRUE,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario
          ])->count();

        if ($consultCuentaMon == 1) {
          $cuentaMon = DB::table("fnzs_catalogos_cuentas_monedero")->where("token_cuentamonedero", $token_cuentaMon)->value("id");
          $pers_responsable = DB::table("vhum_empleados_catalogo")->where("empleado_token", $token_responsable)->value("id");
          $cuenta_banco_id = $OKCuentaBan ? DB::table("fnzs_catalogos_cuentas")->where("token_cuenta", $token_cuenta_bancaria)->value("id") : NULL;
          $caja_id = $OKCaja ? DB::table("fnzs_catalogos_caja")->where("token_caja", $caja)->value("id") : NULL;
          DB::table('fnzs_catalogos_cuentas_monedero')
          ->where('token_cuentamonedero',$token_cuentaMon)
          ->limit(1)->update(array(
            'plataforma_electronica' => $JwtAuth->encriptar($plataforma_electronica),
            'referencia' => $JwtAuth->encryptBankAccount($no_referencia),
            'cuenta' => $JwtAuth->encryptBankAccount($cuenta),
            'clabe_inter' => $JwtAuth->encryptBankAccount($clabe_inter),
            'titular' => $JwtAuth->encryptBankAccount($titularCuenta),
            'mon_cuenta_contable' => $cuenta_contable,
            'moneda' => $moneda,
            'egresos' => $egresos,
            'ingresos' => $ingresos,
            'v_humano' => $v_Humano,
            'responsable' => $pers_responsable,
            'cuenta_banco' => $cuenta_banco_id,
            'caja' => $caja_id,
          ));
            
          if ($OKMedOpeNew) {
            for ($i = 0; $i < count($mediosOperacionNuevos); $i++) {
              $clave = $mediosOperacionNuevos[$i]['clave'];
              $valor = $mediosOperacionNuevos[$i]['valor'];
              $vigencia = isset($mediosOperacionNuevos[$i]['vigencia']) && !empty($mediosOperacionNuevos[$i]['vigencia']) ? $mediosOperacionNuevos[$i]['vigencia'] : NULL;

              DB::table('fnzs_catalogos_cuentas_medios_operacion')
              ->insert(array(
                "token_medio_operacion" => $JwtAuth->encriptarToken($cuentaMon . $clave . $valor . $vigencia),
                "cuenta_monedero" => $cuentaMon,
                "medio_operacion" => $clave,
                "referencia_operacion" => $valor,
                "vigencia" => !empty($vigencia) ? $vigencia : NULL,
                "empresa" => DB::table("main_empresas")->where("empresa_token", $empresa)->value("id"),
              ));
            }
          }

          if ($OKMedOpeDel) {
            for ($i = 0; $i < count($mediosOperacionDelete); $i++) {
              $token_medio_operacion = $mediosOperacionDelete[$i]['token_medio_operacion'];
              DB::table('fnzs_catalogos_cuentas_medios_operacion')
              ->where("token_medio_operacion",$token_medio_operacion)
              ->limit(1)->delete();
            }
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Monedero electrónico actualizado'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 404,
            'message' => 'El monedero electrónico que intenta modificar no existe'
          );
        }
      } else {
        $mensaje_error = "";
        if (!$OKCuentaMon) $mensaje_error = "Error al seleccionar monedero electrónico, intentelo nuevamente o comuniquese a soporte";
        if (!$OKPlatElect) $mensaje_error = "Error al registrar plataforma electrónica, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKNoReferen) $mensaje_error = "Error al registrar número de referencia, intentelo nuevamente o comuniquese a soporte"; 

        if (!$OKCuenta) $mensaje_error = "Error al registrar número de cuenta, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKClabeInte) $mensaje_error = "Error al registrar clabe interbancaria, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKTitularCu) $mensaje_error = "Error al registrar titular, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKMoneda) $mensaje_error = "Error al seleccionar moneda, intentelo nuevamente o comuniquese a soporte";
        if (!$OKMedOpeNew) $mensaje_error = "Error al registrar nuevos medios de operación, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKMedOpeDel) $mensaje_error = "Error al seleccionar medios de operación para eliminar, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKEgresos) $mensaje_error = "Error al seleccionar si este monedero estara destinado al sector de egresos, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKIngresos) $mensaje_error = "Error al seleccionar si este monedero estara destinado al sector de ingresos, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKV_Humano) $mensaje_error = "Error al seleccionar si este monedero estara destinado al sector de valor humano, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKResponsab) $mensaje_error = "Error al seleccionar responsable vinculado, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKCuentaBan) $mensaje_error = "Error al seleccionar cuenta bancaria vinculada, intentelo nuevamente o comuniquese a soporte"; 
        if (!$OKCaja) $mensaje_error = "Error al seleccionar caja vinculada, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registrarNewManejoCuentasMon(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'user_token' => 'required|string',
      'token_cuentaMon' => 'required|string',
      'arrayManejo' => 'array',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_cuentaMon = $request->input('token_cuentaMon');
      $arrayManejo = $request->input('arrayManejo');
      $idCuentaMonedero = DB::table("cuenta_monedero")->where("token_cuentamonedero",$token_cuentaMon)->value("id");
      
      $selectEmp = DB::table("main_empresas AS emp")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select('emp.id AS id_emp','emp.zona_horaria')
      ->first();

      //da_te_default_timezone_set($selectEmp->zona_horaria);

      $token_manejo = $JwtAuth->encriptarToken(time(),$empresa,$arrayManejo,$token_cuentaMon);

      if ($arrayManejo != '') {
        $contador = 0;

        for ($i = 0; $i < count($arrayManejo); $i++) {
          if ($arrayManejo[$i]['clave'] != '' && $arrayManejo[$i]['valor'] != '' && $arrayManejo[$i]['responsable'] != '') {
            $chequera = $arrayManejo[$i]['clave'] == 'chequera' ? true : false;
            $credito = $arrayManejo[$i]['clave'] == 'Tarjetas de credito' ? true : false;
            $debito = $arrayManejo[$i]['clave'] == 'Tarjetas de debito' ? true : false;

            $encriptRef = $JwtAuth->encriptar($arrayManejo[$i]['valor']);
            $encriptRespons = $JwtAuth->encriptar($arrayManejo[$i]['responsable']);

            $contador++;
          } else {
            if ($arrayManejo[$i]['clave'] == '') {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Error en manejo de opciones adicionales'
              );
            }

            if ($arrayManejo[$i]['valor'] == '') {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Error en la referencia de opciones adicionales'
              );
            }

            if ($arrayManejo[$i]['responsable'] == '') {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Error en el responsable de opciones adicionales'
              );
            }
          }

          $insertManejo = DB::table('fnzs_catalogos_cuentas_manejo')
            ->insert(array(
              "token_manejocuentas" => $token_manejo,
              "cuenta_bancaria" => NULL,
              "cuenta_monedero" => $idCuentaMonedero[0]->id,
              "chequera" => $chequera,
              "credito" => $credito,
              "debito" => $debito,
              "referencia" => $encriptRef,
              "responsable" => $encriptRespons,
              "empresa" => $selectEmp[0]->id,
            ));

          if ($insertManejo) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Opción adicional registrada satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 400,
              'message' => 'Los datos de la opción adicional no son correctos, error al intentar registrar'
            );
          }
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'El contenido de las opciones adicionales esta vacio'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);


    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cuentaMon' => 'required|string',
        'arrayManejo' => 'required',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'Monedero electrónico invalido',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametros->user_token, true);


      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 404,
        'message' => 'Los datos no son correctos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminarMonederoElctronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_monedero' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_monedero = $request->input('token_monedero');
      
      $consultcuentaMon = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas_monedero.token_cuentamonedero' => $token_monedero,
        'fnzs_catalogos_cuentas_monedero.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();

      if ($consultcuentaMon->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La cuenta de monedero electrónico que intenta eliminar no existe'
        );
      } else {
        $updateStatusMonedero = DB::table('fnzs_catalogos_cuentas_monedero')
        ->where('token_cuentamonedero', $token_monedero)
        ->limit(1)->update(array(
          'fecha_delete_mon' => time(),
          'status' => FALSE
        ));

        if ($updateStatusMonedero) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La cuenta de monedero electrónico se ha eliminado correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Error al eliminar la cuenta de monedero electrónico, comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restaurarMonederoElctronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_monedero' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_monedero = $request->input('token_monedero');
      
      $consultcuentaMon = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas_monedero.token_cuentamonedero' => $token_monedero,
        'fnzs_catalogos_cuentas_monedero.status' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();

      if ($consultcuentaMon->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La cuenta de monedero electrónico que intenta restaurar no existe'
        );
      } else {
        $updateStatusMonedero = DB::table('fnzs_catalogos_cuentas_monedero')
        ->where('token_cuentamonedero', $token_monedero)
        ->limit(1)->update(array(
          'fecha_delete_mon' => '',
          'status' => TRUE
        ));

        if ($updateStatusMonedero) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La cuenta de monedero electrónico se ha restaurado correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Error al restaurar la cuenta de monedero electrónico, comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deletPermMonederoElctronico(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_monedero' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_monedero = $request->input('token_monedero');

      $consultcuentaMon = CuentaMonederoModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas_monedero.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas_monedero.token_cuentamonedero' => $token_monedero,
        'fnzs_catalogos_cuentas_monedero.status' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();
      
      if ($consultcuentaMon->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La cuenta de monedero electrónico que intenta eliminar no existe'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $updateStatusMonedero = DB::table('fnzs_catalogos_cuentas_monedero')
        ->where('token_cuentamonedero', $token_monedero)
        ->limit(1)->delete();

        if ($updateStatusMonedero) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La cuenta de monedero electrónico se ha eliminado correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Error al eliminar la cuenta de monedero electrónico, comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function saldoMonederoByToken($token_cuentamonedero, $empresa){
    $queryMovimientos = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
    ->join("fnzs_catalogos_cuentas_monedero AS countMon", "fnzs_actividad_movimientos.cuenta_monedero", "=", "countMon.id")
    ->where([
      "countMon.token_cuentamonedero" => $token_cuentamonedero,
      "emp.empresa_token" => $empresa
    ])
    ->orderBy('fnzs_actividad_movimientos.fecha_contabilizacion_movimiento', 'ASC')
    ->get();
    $empresaData = DB::table("main_empresas")->where("empresa_token",$empresa)->first();
    $codeMoneda = $empresaData->e_moneda_code;
    $decimalesMoneda = $empresaData->e_moneda_decimales;
    $saldo_total_acumulado = 0;

    foreach ($queryMovimientos as $cMov) {
      $monto_applc = (float)$cMov->monto_aplicado * ($cMov->tipo_cambio_movimiento ? $cMov->tipo_cambio_movimiento : 1);
      $movimiento_debe = $cMov->tipo_movimiento == 'S' ? $monto_applc : 0;
      $movimiento_haber = $cMov->tipo_movimiento == 'R' ? $monto_applc : 0;
      
      if (!is_null($cMov->pago)) {
        $idPagoByMovAcreedor = DB::table("fnzs_catalogo_acreedores_movimientos AS acrMov")
        ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS ampv", "acrMov.id", "=", "ampv.mov_realizado")
        ->where("ampv.pago_vinculado",$cMov->pago)
        ->exists();

        $idPagoByMovDeudor = DB::table("fnzs_catalogo_deudores_movimientos AS deuMov")
        ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS dmpv", "deuMov.id", "=", "dmpv.mov_realizado")
        ->where("dmpv.pago_vinculado",$cMov->pago)
        ->exists();

        if ($idPagoByMovAcreedor || $idPagoByMovDeudor) {
          continue;
        }
      }
      
      if (!is_null($cMov->acreedor_movimiento)) {
        $movimiento_debe = $cMov->tipo_movimiento == 'R' ? $cMov->monto_aplicado : 0;
        $movimiento_haber = $cMov->tipo_movimiento == 'S' ? $cMov->monto_aplicado : 0;
      }

      $saldo_total_acumulado += ($movimiento_debe - $movimiento_haber);
    }
    return $saldo_total_acumulado;
  }
}
