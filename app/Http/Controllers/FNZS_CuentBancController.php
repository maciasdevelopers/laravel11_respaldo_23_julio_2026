<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\BancosModelo;
use App\Models\CuentBancModelo;
use App\Models\MovimientosBancariosModelo;

class FNZS_CuentBancController extends Controller{
  public function bancos(){
    $arrayBancos = array();
    $bancos = BancosModelo::all();
    foreach ($bancos as  $value) {
      $arrayEach = array(
        "token_bancos" => $value->token_bancos,
        "clave" => $value->clave,
        "nombre_comercial" => $value->nombre_comercial,
        "imagen" => $value->img
      );
      $arrayBancos[] = $arrayEach;
    }
    return response()->json([
      'banco' => $arrayBancos,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function registraCuentaBanc(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_banco' => 'required|string',
      'clave_banco' => 'required|string',
      'contrato' => 'required|string',
      'cuenta' => 'required|string',
      'clabe_inter' => 'required|string',
      'titularCuenta' => 'required|string',
      'sucursal' => 'required|string',
      'moneda_code' => 'required|string',
      'moneda_decimales' => 'required|string',
      'cuenta_contable' => 'required|string',
      'areaEgresos' => 'required|boolean',
      'areaIngresos' => 'required|boolean',
      'areaValHumano' => 'required|boolean'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Cuenta bancaria inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $medios_operacion = $request->input('opciones_adicionales');
      $token_banco = $request->input('token_banco');
      $valMoneda = $request->input('moneda_code');
      $cuenta = $request->input('cuenta');
      $clabe_inter = $request->input('clabe_inter');
      $contrato = $request->input('contrato');
      $titularCuenta = $request->input('titularCuenta');
      $sucursal = $request->input('sucursal');
      $valCuentaContable = $request->input('cuenta_contable');
      $areaEgresos = $request->input('areaEgresos');
      $areaIngresos = $request->input('areaIngresos');
      $areaValHumano = $request->input('areaValHumano');

      $fecha_registro = time();
      $idBanco = DB::table("teci_bancos")->where("token_bancos",$token_banco)->value("id");
      //echo " id:".$tokenBanco[0]->id;

      $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM main_empresas AS emp JOIN main_empresa_usuario AS empusers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empusers.empresa AND empusers.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

      $folioCuenta = DB::select("SELECT IF (max(account.folio_cuenta) IS NOT NULL,(max(account.folio_cuenta)+1),1) AS folio
        FROM fnzs_catalogos_cuentas AS account JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
        JOIN teci_usuarios_catalogo AS users WHERE account.empresa = emp.id AND emp.empresa_token = ?
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

      $tokenCuenta = $JwtAuth->encriptarToken(time(),$idBanco,$cuenta,$clabe_inter);
      $contratoEnconde = $JwtAuth->encryptBankAccount($contrato);
      $cuentaEncode = $JwtAuth->encryptBankAccount($cuenta);
      $clabeInterEncode = $JwtAuth->encryptBankAccount($clabe_inter);
      $titularEncode = $JwtAuth->encryptBankAccount($titularCuenta);
      $sucursalEncode = $JwtAuth->encryptBankAccount($sucursal);
      //echo $cuentaEncode; exit; 
      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $cuentas = new CuentBancModelo();
      $cuentas->token_cuenta = $tokenCuenta;
      $cuentas->folio_cuenta = $folioCuenta[0]->folio;
      $cuentas->fecha_alta_cuenta = $fecha_registro;
      $cuentas->banco = $idBanco;
      $cuentas->contrato = $contratoEnconde;
      $cuentas->cuenta = $cuentaEncode;
      $cuentas->clabe_inter = $clabeInterEncode;
      $cuentas->sucursal = $sucursalEncode;
      $cuentas->titular = $titularEncode;
      $cuentas->moneda = $valMoneda;
      $cuentas->cuenta_contable_cuenta = $valCuentaContable;
      $cuentas->egresos = $areaEgresos;
      $cuentas->ingresos = $areaIngresos;
      $cuentas->v_humano = $areaValHumano;
      $cuentas->status = TRUE;
      $cuentas->fecha_delete_cuenta = '';
      $cuentas->empresa = $selectEmp[0]->id;
      $savedCuenta = $cuentas->save();

      if ($savedCuenta) {
        $obtenCuenta = $cuentas->id;
        
        if (count($medios_operacion) > 0) {
          for ($i = 0; $i < count($medios_operacion); $i++) {
            $clave = $medios_operacion[$i]['clave'];
            $valor = $medios_operacion[$i]['valor'];
            $vigencia = $medios_operacion[$i]['vigencia'];
            DB::table('fnzs_catalogos_cuentas_medios_operacion')
            ->insert(array(
              "token_medio_operacion" => $JwtAuth->encriptarToken($obtenCuenta.$clave.$valor.$vigencia),
              "cuenta_bancaria" => $obtenCuenta,
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
          'message' => 'Cuenta bancaria registrada correctamente'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'Cuenta bancaria no registrada, intente nuevamente o comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function folioCuentaBancaria(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    $folioCuenta = DB::select("SELECT IF (max(folio_cuenta) IS NOT NULL,(max(folio_cuenta)+1),1) AS folio FROM cuenta AS account JOIN main_empresas AS emp 
      JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE account.empresa = emp.id AND emp.empresa_token = ? 
      AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
      [$empresa, $usuario]
    );

    return response()->json([
      'cuenta' => $JwtAuth->generar($folioCuenta[0]->folio),
      'codigo' => 200,
      'status' => 'success'
    ]);
    
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function responsableCuenta(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $respCuenta = CuentBancModelo::join("teci_bancos AS bank", "fnzs_catalogos_cuentas.banco", "bank.id")
    ->join("vhum_empleados_catalogo AS pers", "fnzs_catalogos_cuentas.responsable", "pers.id")
    ->join("teci_usuarios_catalogo AS users", "pers.id", "users.empleado")
    ->where([
      'fnzs_catalogos_cuentas.status' => TRUE,
      'fnzs_catalogos_cuentas.egresos' => TRUE,
      'fnzs_catalogos_cuentas.empresa' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->orwhere([
      'fnzs_catalogos_cuentas.status' => TRUE,
      'fnzs_catalogos_cuentas.v_humano' => TRUE,
      'fnzs_catalogos_cuentas.empresa' => $empresa,
      'users.usuario_token' => $usuario
    ])->get();

    if ($respCuenta->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No existe cuenta bancaria asociada a este usuario'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $selectCuentas = array();
      $arrayContrato = array();
      $arrayCuenta = array();
      $arrayClabeInetr = array();
      $arraySucursal = array();
      $arrayTitular = array();
      $arrayOpcionAdicional = array();

      foreach ($respCuenta as $resCuentas) {
        $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,people.nacionalidad,emp.zona_horaria FROM main_empresas AS emp JOIN sos_personas AS people 
                        JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
                        AND emp.persona = people.id AND emp.id = empusers.empresa AND empusers.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
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

        if ($resCuentas->egresos == TRUE) {
          $egresos = true;
        } else {
          $egresos = false;
        }

        if ($resCuentas->v_humano == TRUE) {
          $v_humano = true;
        } else {
          $v_humano = false;
        }

        $sucursal = utf8_decode($JwtAuth->desencriptar($resCuentas->sucursal));

        $moneda = DB::select("SELECT codigo,moneda FROM teci_catalogo_monedas WHERE id = ?", [$resCuentas->moneda]);
        $resMoneda = $moneda[0]->codigo . "-" . $moneda[0]->moneda;

        $decimalesMoneda = DB::select(
          "SELECT catmon.token_monedas,catmon.decimales FROM teci_catalogo_monedas AS catmon 
                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.e_moneda = catmon.id AND emp.empresa_token = ?
                        AND emp.id = empusers.empresa AND empusers.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token = ?",
          [$empresa, $usuario]
        );

        $resultsalDoCuenta = $this->saldoCuentaByToken($decimalesMoneda[0]->token_monedas, $resCuentas->token_cuenta, $empresa, $usuario);

        $salDoCuenta = DB::select(
          "SELECT ROUND(?,?) AS saldoRound,FORMAT(?,?) AS saldoFormat",
          [$resultsalDoCuenta, $decimalesMoneda[0]->decimales, $resultsalDoCuenta, $decimalesMoneda[0]->decimales]
        );

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
          "saldofloat" => $salDoCuenta[0]->saldoRound,
          "salDoCuenta" => "$" . $salDoCuenta[0]->saldoFormat,
        );

        $selectCuentas[] = $arrayCuentas;
      }
      $dataMensaje = array(
        'cuenta' => $selectCuentas,
        'code' => 200,
        'status' => 'success'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cuentasVig(Request $request){
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
      
      $queryCuentaBancaria = CuentBancModelo::join("teci_bancos AS bank", "fnzs_catalogos_cuentas.banco", "bank.id")
      ->join("main_empresas AS emp", "fnzs_catalogos_cuentas.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("fnzs_catalogos_cuentas.fecha_alta_cuenta", [$fechaInicio, $fechaFin]);
      })
      ->where([
        'fnzs_catalogos_cuentas.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->orderBy('fnzs_catalogos_cuentas.id', 'DESC')
      ->get();

      if ($queryCuentaBancaria->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron cuentas bancarias registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $cuentas = array();
        foreach ($queryCuentaBancaria as $vCuent) {
          $moneda_decimales = $JwtAuth->getMonedaAPI($vCuent->moneda);
          $clavecrea = $JwtAuth->encriptar("U2FsdGVkX18zP0izEZi36/+pp4EgJ/QG1A//IQqcjsI=");
          //echo htmlspecialchars($clavedescrea, ENT_QUOTES)." ".$clavedescrea;
          $cuenta_result_saldo = $this->saldoCuentaByToken($vCuent->token_cuenta,$empresa);
          $folio_cuenta = 'CUENT-'.$JwtAuth->generarFolio($vCuent->folio_cuenta);
          $banco_nombre_comercial = $vCuent->nombre_comercial;
          $cuenta_descifrada = $JwtAuth->decryptBankAccount($vCuent->cuenta);
          $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
          $row = array(
            "token_cuenta" => $vCuent->token_cuenta,
            "folio_cuenta" => $folio_cuenta,
            "cuenta_bancaria" => "**** **** **** $cuenta_descifrada_substr",
            "cuenta_view" => false,
            "cuenta_time" => 0,
            "vigencia" => date('m-Y', $vCuent->vigencia),
            "egresos" => $vCuent->egresos ? true : false,
            "ingresos" => $vCuent->ingresos ? true : false,
            "v_humano" => $vCuent->v_humano ? true : false,
            //bancos
            "banco_clave" => $vCuent->clave,
            "banco_nombre_comercial" => $banco_nombre_comercial,
            "saldo_cuenta" => $cuenta_result_saldo,
            "saldo_cuenta_format" => "$".number_format($cuenta_result_saldo,$moneda_decimales, '.', ',')." $vCuent->moneda",
            "aplicable_disabled" => true,
            "select_for_pagos" => false,
            "monto_aplicar" => 0,
            "_filtro_busqueda" => "$banco_nombre_comercial $folio_cuenta **** **** **** $cuenta_descifrada_substr"
          );
          $cuentas[] = $row;
        }
    
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'cuentas' => $cuentas,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cuentaBancariaCompleta(Request $request){
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
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_cuenta = $request->input('token_cuenta');

      $seleCuentas = CuentBancModelo::join("teci_bancos AS bank", "fnzs_catalogos_cuentas.banco", "bank.id")
      ->join("main_empresas AS emp", "fnzs_catalogos_cuentas.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas.status' => TRUE,
        'fnzs_catalogos_cuentas.token_cuenta' => $token_cuenta,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();
  
      if ($seleCuentas->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron cuentas bancarias registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        foreach ($seleCuentas as $vCuenta) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'cuenta_bancaria' => $JwtAuth->decryptBankAccount($vCuenta->cuenta),
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cuentaBancaria4Digitos(Request $request){
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
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_cuenta = $request->input('token_cuenta');

      $seleCuentas = CuentBancModelo::join("teci_bancos AS bank", "fnzs_catalogos_cuentas.banco", "bank.id")
      ->join("main_empresas AS emp", "fnzs_catalogos_cuentas.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas.status' => TRUE,
        'fnzs_catalogos_cuentas.token_cuenta' => $token_cuenta,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->get();
  
      if ($seleCuentas->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron cuentas bancarias registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        foreach ($seleCuentas as $vCuenta) {
          $cuenta_descifrada = $JwtAuth->decryptBankAccount($vCuenta->cuenta);
          $cuenta_descifrada_substr = substr($cuenta_descifrada, -4);
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'cuenta_bancaria' => "**** **** **** $cuenta_descifrada_substr",
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cuentasDel(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $selectCuentas = CuentBancModelo::join("teci_bancos AS bank", "fnzs_catalogos_cuentas.banco", "bank.id")
    ->join("main_empresas AS emp", "fnzs_catalogos_cuentas.empresa", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->where([
      'fnzs_catalogos_cuentas.status' => FALSE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->orderBy('fnzs_catalogos_cuentas.fecha_delete_cuenta', 'DESC')->get();

    if ($selectCuentas->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron cuentas bancarias registradas'
      );
    } else {
      $cuentas = array();
      $JwtAuth = new \App\Helpers\JwtAuth();
      foreach ($selectCuentas as $resCuentas) {
        //da_te_default_timezone_set($resCuentas->zona_horaria);
        $arrayCuenta = array(
          "token_cuenta" => $resCuentas->token_cuenta,
          "cuenta" => $resCuentas->cuenta,
          "egresos" => $resCuentas->egresos ? true : false,
          "ingresos" => $resCuentas->ingresos ? true : false,
          "v_humano" => $resCuentas->v_humano ? true : false,
          "nombre_comercial" => $resCuentas->nombre_comercial,
          "fecha_delete" => gmdate('Y-m-d H:i:s', $resCuentas->fecha_delete_cuenta)
        );
        $cuentas[] = $arrayCuenta;
      }
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'cuentas' => $cuentas,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleCuentasVig(Request $request){
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
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_cuenta = $request->input('token_cuenta');
      
      $selectCuentas = CuentBancModelo::join("teci_bancos AS bank", "fnzs_catalogos_cuentas.banco", "bank.id")
      ->join("main_empresas AS emp", "fnzs_catalogos_cuentas.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas.status' => TRUE,
        'fnzs_catalogos_cuentas.token_cuenta' => $token_cuenta,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->get();
  
      if ($selectCuentas->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron cuentas bancarias registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $cuenta_informacion = array();
        foreach ($selectCuentas as $vCuenta) {
          $selectManejo = DB::table("fnzs_catalogos_cuentas_medios_operacion AS medOper")
          ->join("fnzs_catalogos_cuentas AS account","medOper.cuenta_bancaria", "account.id")
          ->where("account.token_cuenta",$token_cuenta)->get();
        
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
        
          $rowCuenta = array(
            "token_cuenta" => $vCuenta->token_cuenta,
            "folio" => $JwtAuth->generar($vCuenta->folio_cuenta),
            "contrato_view" => false,
            "contrato" => $JwtAuth->decryptBankAccount($vCuenta->contrato),
            "cuenta_view" => false,
            "cuenta" => $JwtAuth->decryptBankAccount($vCuenta->cuenta),
            "clabe_inter_view" => false,
            "clabe_inter" => $JwtAuth->decryptBankAccount($vCuenta->clabe_inter),
            "sucursal" => $JwtAuth->decryptBankAccount($vCuenta->sucursal),
            "titular" => !empty($vCuenta->titular) ? $JwtAuth->decryptBankAccount($vCuenta->titular) : '---',
            "moneda_code" => $vCuenta->moneda,
            "moneda_name" => "",
            "cuenta_contable" => !is_null($vCuenta->cuenta_contable_cuenta) && $vCuenta->cuenta_contable_cuenta != '' ? $vCuenta->cuenta_contable_cuenta : '',
            "egresos" => $vCuenta->egresos ? true : false,
            "ingresos" => $vCuenta->ingresos ? true : false,
            "v_humano" => $vCuenta->v_humano ? true : false,
            "vigencia" => date('Y-m', $vCuenta->vigencia),
            "opciones_adicionales" => $arrayOpcionAdicional,
            //bancos
            "banco_token" => $vCuenta->token_bancos,
            "banco_clave" => $vCuenta->clave,
            "banco_nombre_comercial" => $vCuenta->nombre_comercial
          );
        
          $cuenta_informacion[] = $rowCuenta;
        }

        $dataMensaje = array('status' => 'success','code' => 200,'cuenta' => $cuenta_informacion);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleCuentaMonederoCBancoVig(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cuentamonedero' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $cuentaMon = $request->input('token_cuentamonedero');
      
      $detalleCuenta = CuentBancModelo::join("cuenta AS kuenta", "bancos.id", "kuenta.banco")
      ->join("cuenta_monedero AS kuentaMon", "kuentaMon.cuenta_banco", "kuenta.id")
      ->join("main_empresas AS emp", "kuentaMon.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("vhum_empleados_catalogo AS pers", "empusers.personal", "pers.id")
      ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
      ->where([
        'kuentaMon.token_cuentamonedero' => $cuentaMon,
        'kuentaMon.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->get();
  
      if ($detalleCuenta->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron cuentas bancarias registradas'
        );
      } else {
        $detCuentBanco = array();
        foreach ($detalleCuenta as $resDetCuenta) {
          $primeraCuentaMon = substr($resDetCuenta->cuenta, 0, -4);
          $primeraCuentaMon = str_replace($primeraCuentaMon, '***************', $resDetCuenta->cuenta);
    
          $arrayDetCuenta = array(
            "cuenta" => $primeraCuentaMon,
            "img" => $resDetCuenta->img
          );
    
          $detCuentBanco[] = $arrayDetCuenta;
        }
        
        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'cuentas' => $detCuentBanco
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateCuentaBanc(Request $request){
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
      'token_banco' => 'required|string',
      'contrato' => 'required|string',
      'cuenta' => 'required|string',
      'clabe_inter' => 'required|string',
      'titularCuenta' => 'required|string',
      'sucursal' => 'required|string',
      'moneda_code' => 'required|string',
      'cuenta_contable' => 'required|string',
      'areaEgresos' => 'required|boolean',
      'areaIngresos' => 'required|boolean',
      'areaValHumano' => 'required|boolean',
      'eliminacion_proceso' => 'array',
      'medios_operacion' => 'array'
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
      //da_te_default_timezone_set('America/Mexico_City');
      $token_cuenta = $request->input('token_cuenta');
      $token_banco = $request->input('token_banco');
      $contrato = $request->input('contrato');
      $cuenta = $request->input('cuenta');
      $clabe_inter = $request->input('clabe_inter');
      $titularCuenta = $request->input('titularCuenta');
      $sucursal = $request->input('sucursal');
      $valMoneda = $request->input('moneda_code');
      $valCuentaContable = $request->input('cuenta_contable');
      $areaEgresos = $request->input('areaEgresos');
      $areaIngresos = $request->input('areaIngresos');
      $areaValHumano = $request->input('areaValHumano');
      $eliminacion_proceso = $request->input('eliminacion_proceso');
      $medios_operacion = $request->input('medios_operacion');
      
      $queryCuenta = CuentBancModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas.token_cuenta' => $token_cuenta,
        'fnzs_catalogos_cuentas.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->get();

      if ($queryCuenta->isEmpty()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La cuenta bancaria que intenta modificar no existe'
        );
      } else {
        foreach ($queryCuenta as $vCuent) {
          $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM main_empresas AS emp JOIN main_empresa_usuario AS empusers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
              AND emp.id = empusers.empresa AND empusers.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);

          $obtenCuenta = DB::table("fnzs_catalogos_cuentas")->where('token_cuenta',$vCuent->token_cuenta)->value("id");
          $selectBanco = DB::table("teci_bancos")->where("token_bancos",$token_banco)->value("id");
  
          $updateCuenta = DB::table('fnzs_catalogos_cuentas')->where('token_cuenta',$vCuent->token_cuenta)
          ->limit(1)->update(
            array(
              'banco' => $selectBanco,
              'contrato' => $JwtAuth->encryptBankAccount($contrato),
              'cuenta' => $JwtAuth->encryptBankAccount($cuenta),
              'clabe_inter' => $JwtAuth->encryptBankAccount($clabe_inter),
              'sucursal' => $JwtAuth->encryptBankAccount($sucursal),
              'titular' => $JwtAuth->encryptBankAccount($titularCuenta),
              'moneda' => $valMoneda,
              'cuenta_contable_cuenta' => $valCuentaContable,
              'egresos' => $areaEgresos,
              'ingresos' => $areaIngresos,
              'v_humano' => $areaValHumano,
            )
          );

          $counter_medios_operacion = 0;
          if (count($medios_operacion) > 0) {
            for ($i = 0; $i < count($medios_operacion); $i++) {
              $clave = $medios_operacion[$i]['clave'];
              $valor = $medios_operacion[$i]['valor'];
              $vigencia = $medios_operacion[$i]['vigencia'];
              $insertMediOperacion = DB::table('fnzs_catalogos_cuentas_medios_operacion')
              ->insert(array(
                "token_medio_operacion" => $JwtAuth->encriptarToken($obtenCuenta.$clave.$valor.$vigencia),
                "cuenta_bancaria" => $obtenCuenta,
                "medio_operacion" => $clave,
                "referencia_operacion" => $valor,
                "vigencia" => !empty($vigencia) ? $vigencia : NULL,
                "empresa" => $selectEmp[0]->id,
              ));
              if ($insertMediOperacion) {
                ++$counter_medios_operacion;
              }
            }
          }

          $counter_eliminacion_proceso = 0;
          if (count($eliminacion_proceso) > 0) {
            for ($i = 0; $i < count($eliminacion_proceso); $i++) {
              $token_medio_operacion = $eliminacion_proceso[$i]['token_medio_operacion'];
              $deleteMediOperacion = DB::table('fnzs_catalogos_cuentas_medios_operacion')
              ->where(["token_medio_operacion" => $token_medio_operacion,"cuenta_bancaria" => $obtenCuenta])
              ->delete();

              if ($deleteMediOperacion) {
                ++$counter_eliminacion_proceso;
              }
            }
          }

          if ($updateCuenta || $counter_medios_operacion == count($medios_operacion) || $counter_eliminacion_proceso == count($eliminacion_proceso)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'La cuenta bancaria se ha actualizado correctamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 400,
              'message' => 'La actualización de esta cuenta bancaria no se llevo a cabo satisfactoriamente debido a problemas internos, favor de comunicarse a soprte'
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteCuentaBancaria(Request $request){
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
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_cuenta = $request->input('token_cuenta');

      $consultcuenta = CuentBancModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas.token_cuenta' => $token_cuenta,
        'fnzs_catalogos_cuentas.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->count();
  
      $consultCajaDisp = CuentBancModelo::join("teci_dispositivos AS disp", "fnzs_catalogos_cuentas.id", "disp.cuenta")
      ->where('fnzs_catalogos_cuentas.token_cuenta', $token_cuenta)
      ->count();

      if ($consultcuenta->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La cuenta bancaria que intenta eliminar no existe'
        );
      } else if ($consultCajaDisp > 0) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La cuenta bancaria que intenta eliminar está vinculada a otros catálogos u operaciones'
        );
      } else {
        $updateStatuscuenta = DB::table('fnzs_catalogos_cuentas')
        ->where('token_cuenta',$token_cuenta)
        ->limit(1)->update(
          array(
            'fecha_delete_cuenta' => time(),
            'status' => FALSE
          )
        );

        if ($updateStatuscuenta) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La cuenta bancaria se ha eliminado correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Error al eliminar la cuenta bancaria, comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restaurarCuentaBancaria(Request $request){
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
				'message' => 'La infomación que hemos recibido es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_cuenta = $request->input('token_cuenta');

      $consultcuenta = CuentBancModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas.token_cuenta' => $token_cuenta,
        'fnzs_catalogos_cuentas.status' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->count();
  
      if ($consultcuenta->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La cuenta bancaria que intenta restaurar no existe'
        );
      } else {
        $updateStatuscuenta = DB::table('fnzs_catalogos_cuentas')
        ->where('token_cuenta',$token_cuenta)
        ->limit(1)->update(array(
          'fecha_delete_cuenta' => NULL,
          'status' => TRUE
        ));

        if ($updateStatuscuenta) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La cuenta bancaria se ha restaurado correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Error al restaurar la cuenta bancaria, comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deltPermanenteCuentaBancaria(Request $request){
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

      $consultCuenta = CuentBancModelo::join("main_empresas AS emp", "fnzs_catalogos_cuentas.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_cuentas.token_cuenta' => $token_cuenta,
        'fnzs_catalogos_cuentas.status' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->count();
  
      if ($consultCuenta->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La cuenta bancaria que intenta eliminar no existe'
        );
      } else {
        $updateStatusCuenta = DB::table('fnzs_catalogos_cuentas')->where('token_cuenta',$token_cuenta)->limit(1)->delete();
        if ($updateStatusCuenta) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La cuenta bancaria se ha eliminado correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Error al eliminar la cuenta bancaria, comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function saldoCuentaByToken($token_cuenta, $empresa){
    $queryMovimientos = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
    ->join("fnzs_catalogos_cuentas AS count_cat", "fnzs_actividad_movimientos.cuenta_bancaria", "=", "count_cat.id")
    ->join("teci_bancos AS bank", "count_cat.banco", "=", "bank.id")
    ->where([
      "count_cat.token_cuenta" => $token_cuenta,
      "emp.empresa_token" => $empresa
    ])
    ->orderBy('fnzs_actividad_movimientos.folio_movimiento', 'ASC')
    ->get();
    $empresaData = DB::table("main_empresas")->where("empresa_token",$empresa)->first();
    $codeMoneda = $empresaData->e_moneda_code;
    $decimalesMoneda = $empresaData->e_moneda_decimales;
    $saldo_total_acumulado = 0;
    
    foreach ($queryMovimientos as $vMov) {
      $monto_applc = (float)$vMov->monto_aplicado * ($vMov->tipo_cambio_movimiento ? $vMov->tipo_cambio_movimiento : 1);
      $movimiento_debe = $vMov->tipo_movimiento == 'S' ? $monto_applc : 0;
      $movimiento_haber = $vMov->tipo_movimiento == 'R' ? $monto_applc : 0;

      if (!is_null($vMov->pago)) {
        $idPagoByMovAcreedor = DB::table("fnzs_catalogo_acreedores_movimientos AS acrMov")
        ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS ampv", "acrMov.id", "=", "ampv.mov_realizado")
        ->where("ampv.pago_vinculado",$vMov->pago)
        ->exists();

        $idPagoByMovDeudor = DB::table("fnzs_catalogo_deudores_movimientos AS deuMov")
        ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS dmpv", "deuMov.id", "=", "dmpv.mov_realizado")
        ->where("dmpv.pago_vinculado",$vMov->pago)
        ->exists();

        if ($idPagoByMovAcreedor || $idPagoByMovDeudor) {
          continue;
        }
      }
      
      if (!is_null($vMov->acreedor_movimiento)) {
        $movimiento_debe = $vMov->tipo_movimiento == 'R' ? $vMov->monto_aplicado : 0;
        $movimiento_haber = $vMov->tipo_movimiento == 'S' ? $vMov->monto_aplicado : 0;
      }
      
      $saldo_total_acumulado += ($movimiento_debe - $movimiento_haber);
    }
    return $saldo_total_acumulado;
  }
}
