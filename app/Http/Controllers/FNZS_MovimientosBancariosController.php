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
use App\Models\AssociatesModelo;
use App\Models\MovimientosBancariosModelo;

class FNZS_MovimientosBancariosController extends Controller{

  public function movimientosBancariosCuentasAll(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayMovimientos = array();

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

        $decimalesMoneda = DB::select("SELECT emp.e_moneda_code,emp.e_moneda_decimales FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
          JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
          [$usuario->empresa_token, $usuario->user_token]);

        $list_movimientos = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
          ->join("vhum_empleados_catalogo AS resp_pers", "fnzs_actividad_movimientos.responsable", "=", "resp_pers.id")
          ->join("sos_personas AS people", "resp_pers.personal", "=", "people.id")
          ->join("fnzs_catalogos_cuentas AS count_cat", "fnzs_actividad_movimientos.cuenta_bancaria", "=", "count_cat.id")
          ->join("teci_bancos AS bank", "count_cat.banco", "=", "bank.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "=", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
          ->where([
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token
          ])->orderBy('fnzs_actividad_movimientos.folio_movimiento', 'DESC')->get();

        foreach ($list_movimientos as $v_mov) {
          //$v_mov->e_moneda_code,
          //$v_mov->e_moneda_decimales,


          //da_te_default_timezone_set($v_mov->zona_horaria);
          $token_movimiento = $v_mov->token_movimiento;
          $folio_movimiento = 'M-' . $JwtAuth->generarFolio($v_mov->folio_movimiento);
          $fecha_movimiento = gmdate('Y-m-d H:i:s', $v_mov->fecha_sistema);

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
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 404,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function movimientosBancariosCuentaToken(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $cuentaControl = new FNZS_CuentBancController();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayMovimientos = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cuenta" => "required|string",
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
        $token_cuenta = $parametrosArray["token_cuenta"];

        $decimalesMoneda = DB::select("SELECT emp.e_moneda_code,emp.e_moneda_decimales FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
          JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
          [$usuario->empresa_token, $usuario->user_token]);

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
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token
          ])->orderBy('fnzs_actividad_movimientos.folio_movimiento', 'DESC')->get();

        foreach ($list_movimientos as $v_mov) {
          //da_te_default_timezone_set($v_mov->zona_horaria);
          $token_movimiento = $v_mov->token_movimiento;
          $folio_movimiento = 'M-' . $JwtAuth->generarFolio($v_mov->folio_movimiento);
          $fecha_movimiento = gmdate('Y-m-d H:i:s', $v_mov->fecha_sistema);

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
        $cuenta_result_saldo = $cuentaControl->saldoCuentaByToken($decimalesMoneda[0]->e_moneda_decimales, $token_cuenta, $usuario->empresa_token);
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
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 404,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registra_ajuste_cuenta_autorizado(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $cuentaControl = new FNZS_CuentBancController();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayMovimientos = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cuenta" => "required|string",
        "tipo_de_poliza" => "required|string",
        "forma_operacion" => "required|string",
        "fecha_movimiento" => "required|string",
        "origen_destino_movimiento" => "required|string",
        "token_cliente" => "string",
        "token_proveedor" => "string",
        "token_empleado" => "string",
        "cfdi_data" => "array",
        "saldo_ajuste" => "required|numeric",
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
        $token_cuenta = $parametrosArray["token_cuenta"];
        $tipo_de_poliza = $parametrosArray["tipo_de_poliza"];
        $forma_operacion = $parametrosArray["forma_operacion"];
        $fecha_movimiento = $parametrosArray["fecha_movimiento"];
        $origen_destino_movimiento = $parametrosArray["origen_destino_movimiento"];
        $token_cliente = $parametrosArray["token_cliente"];
        $token_proveedor = $parametrosArray["token_proveedor"];
        $token_empleado = $parametrosArray["token_empleado"];
        $cfdi_data = $parametrosArray["cfdi_data"];
        $saldo_ajuste = $parametrosArray["saldo_ajuste"];

        if (
          isset($token_cuenta) && !empty($token_cuenta) &&
          isset($tipo_de_poliza) && !empty($tipo_de_poliza) && preg_match($JwtAuth->filtroAlfaNumerico(), $tipo_de_poliza) &&
          isset($forma_operacion) && !empty($forma_operacion) && preg_match($JwtAuth->filtroAlfaNumerico(), $forma_operacion) &&
          isset($fecha_movimiento) && !empty($fecha_movimiento) && preg_match($JwtAuth->filtroFecha(), $fecha_movimiento) &&
          isset($origen_destino_movimiento) && !empty($origen_destino_movimiento) && preg_match($JwtAuth->filtroAlfaNumerico(), $origen_destino_movimiento) &&
          ((isset($token_cliente) && !empty($token_cliente)) || (isset($token_proveedor) && !empty($token_proveedor)) || (isset($token_empleado) && !empty($token_empleado))) &&
          isset($saldo_ajuste) && !empty($saldo_ajuste) && preg_match($JwtAuth->filtroCostoPrecio(), $saldo_ajuste)
        ) {

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
              [$usuario->empresa_token, $usuario->user_token]
            );

            $cuenta_result_saldo = $cuentaControl->saldoCuentaByToken($decimalesMoneda[0]->token_monedas, $token_cuenta, $usuario->empresa_token, $usuario->user_token);
            if ($cuenta_result_saldo > 0 && $cuenta_result_saldo > $saldo_ajuste) {
              $fechaSistema = time();

              $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr FROM main_empresas AS emp
    				        	JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
    				        	AND emp.id = empuser.empresa AND empuser.personal = pers.id
    				        	AND pers.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

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
                [$token_cuenta, $usuario->empresa_token, $usuario->user_token]
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
                                    FROM fnzs_actividad_movimientos AS movim JOIN main_empresas AS emp JOIN main_empresa_usuario AS empper
                                    JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE movim.empresa = emp.id 
                                    AND emp.empresa_token = ? AND emp.id = empper.empresa AND empper.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

                $insertMovimientos = DB::table("fnzs_actividad_movimientos")->insert(
                  array(
                    "token_movimiento" => $JwtAuth->encriptarToken($tokenAjuste . $selectAjuste[0]->id . $folioMovim[0]->folio . 'A'),
                    "folio_movimiento" => $folioMovim[0]->folio,
                    "fecha_sistema" => $fechaSistema,
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
          if (!isset($token_cuenta) || empty($token_cuenta)) {
            $mensaje_error = 'error en cuenta bancaria seleccionada, verifique su información';
          }

          if (!isset($tipo_de_poliza) || empty($tipo_de_poliza) || !preg_match($JwtAuth->filtroAlfaNumerico(), $tipo_de_poliza)) {
            $mensaje_error = 'error en tipo de poliza, verifique su información';
          }

          if (!isset($forma_operacion) || empty($forma_operacion) || !preg_match($JwtAuth->filtroAlfaNumerico(), $forma_operacion)) {
            $mensaje_error = 'error en forma de operación, verifique su información';
          }

          if (!isset($fecha_movimiento) || empty($fecha_movimiento) || !preg_match($JwtAuth->filtroFecha(), $fecha_movimiento)) {
            $mensaje_error = 'error en fecha de movimiento, verifique su información';
          }

          if (!isset($origen_destino_movimiento) || empty($origen_destino_movimiento) || !preg_match($JwtAuth->filtroAlfaNumerico(), $origen_destino_movimiento)) {
            $mensaje_error = 'error en origen/destino de movimiento, verifique su información';
          }

          if ((!isset($token_cliente) || empty($token_cliente)) && (!isset($token_proveedor) || empty($token_proveedor)) && (!isset($token_empleado) || empty($token_empleado))) {
            $mensaje_error = 'error en selección de cliente, proveedor o empleado, verifique su información';
          }

          if (!isset($saldo_ajuste) || empty($saldo_ajuste) || !preg_match($JwtAuth->filtroCostoPrecio(), $saldo_ajuste)) {
            $mensaje_error = 'error en monto de ajuste, verifique su información';
          }
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => $mensaje_error
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 404,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
