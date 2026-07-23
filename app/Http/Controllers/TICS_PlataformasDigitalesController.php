<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\MonedElectModelo;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;
use App\Models\PersonalModelo;
use App\Models\PlataformasDigitalesModelo;

class TICS_PlataformasDigitalesController extends Controller{
  public function listPlataformas(){
    $JwtAuth = new \JwtAuth();
    $arrayMonElectr = array();

    $list_plat = PlataformasDigitalesModelo::all();
    foreach ($list_plat as $valPlat) {
      $arrayMonedero = array(
        "token_plataforma_digital" => $valPlat->token_plataforma_digital,
        "nombre" => $valPlat->nombre
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
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    $detalleMonedero = array();
    $arrayOpcionAdicional = array();

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

        $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,people.nacionalidad,emp.zona_horaria FROM empresas AS emp JOIN personas AS people 
                JOIN empresapersonal AS emppers JOIN personal AS pers JOIN usuarios AS users WHERE emp.emp_token = ? 
                AND emp.persona = people.id AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                AND pers.usuario = users.id AND users.user_token= ?", [$usuario->emp_token, $usuario->user_token]);

        $respMonedero = CuentaMonederoModelo::join("monedero_electronico AS mon", "cuenta_monedero.monedero", "mon.id")
          ->join("personal", "cuenta_monedero.responsable", "personal.id")
          ->join("usuarios", "personal.usuario", "usuarios.id")
          /*->where([
                        'cuenta_monedero.status' => TRUE,
                        'cuenta_monedero.ingresos' => FALSE,
                        'cuenta_monedero.empresa' => $selectEmp[0]->id,
                        'usuarios.user_token' => $usuario->user_token
                    ])->get();*/

          ->where([
            'cuenta_monedero.status' => TRUE,
            'cuenta_monedero.empresa' => $selectEmp[0]->id,
            'usuarios.user_token' => $usuario->user_token
          ])
          ->where([
            'cuenta_monedero.egresos' => TRUE
          ])
          ->orwhere([
            'cuenta_monedero.v_humano' => TRUE
          ])->get();
        //echo 'coun caja '.count($respMonedero); 
        if (count($respMonedero) != 0) {
          foreach ($respMonedero as $resMonedero) {
            $cuenta_bancaria = '';
            $name_cuenta = '';
            $token_caja = '';
            $folio_caja = '';
            $alias_caja = '';

            //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

            if ($resMonedero->cuenta_banco != '') {
              $tknCount = DB::select("SELECT token_cuenta FROM cuenta WHERE id = ? ", [$resMonedero->cuenta_banco]);
              $cuentaBancoMon = CuentBancModelo::join("empresas AS emp", "cuenta.empresa", "emp.id")
                ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
                ->join("personal", "empresapersonal.personal", "personal.id")
                ->join("usuarios", "personal.usuario", "usuarios.id")
                ->where([
                  'cuenta.status' => TRUE,
                  'cuenta.token_cuenta' => $tknCount[0]->token_cuenta,
                  'emp.emp_token' => $usuario->emp_token,
                  'usuarios.user_token' => $usuario->user_token
                ])->get();
              foreach ($cuentaBancoMon as $resCuentaMon) {
                $cuenta_bancaria = $resCuentaMon->token_cuenta;
                $name_cuenta = $JwtAuth->desencriptar($resCuentaMon->cuenta);
              }
            }

            if ($resMonedero->caja != '') {
              $tokenCaja = DB::select("SELECT token_caja FROM caja WHERE id = ? ", [$resMonedero->caja]);
              $cajaMonedero = CajaModelo::join("empresas AS emp", "caja.empresa", "emp.id")
                ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
                ->join("personal", "empresapersonal.personal", "personal.id")
                ->join("usuarios", "personal.usuario", "usuarios.id")
                ->where([
                  'caja.status' => TRUE,
                  'caja.token_caja' => $tokenCaja[0]->token_caja,
                  'emp.emp_token' => $usuario->emp_token,
                  'usuarios.user_token' => $usuario->user_token
                ])->get();

              foreach ($cajaMonedero as $resCajaMon) {
                $token_caja = $resCajaMon->token_caja;
                $folio_caja = $JwtAuth->generar($resCajaMon->no_caja);
                $alias_caja = $JwtAuth->desencriptar($resCajaMon->alias_caja);
              }
            }

            $referencia = $JwtAuth->desencriptar($resMonedero->referencia);
            $cuenta_monedero = $JwtAuth->desencriptar($resMonedero->cuenta);
            $clabeInter = $JwtAuth->desencriptar($resMonedero->clabe_inter);
            $titular = $JwtAuth->desencriptar($resMonedero->titular);

            $moneda = DB::select("SELECT codigo,moneda FROM catalogo_monedas WHERE id = ?", [$resMonedero->moneda]);
            $resMoneda = $moneda[0]->codigo . "-" . $moneda[0]->moneda;

            if ($resMonedero->egresos == TRUE) {
              $egresos = true;
            } else {
              $egresos = false;
            }

            if ($resMonedero->v_humano == TRUE) {
              $v_humano = true;
            } else {
              $v_humano = false;
            }

            $selectManejCuenta = DB::table('manejo_cuentas')
              ->join("cuenta_monedero AS countMon", "manejo_cuentas.cuenta_monedero", "countMon.id")
              ->join("empresas AS emp", "manejo_cuentas.empresa", "emp.id")
              ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
              ->join("personal", "empresapersonal.personal", "personal.id")
              ->join("personas AS people", "personal.personal", "people.id")
              ->join("usuarios", "personal.usuario", "usuarios.id")
              ->where([
                'manejo_cuentas.cuenta_bancaria' => NULL,
                'countMon.token_cuentamonedero' => $resMonedero->token_cuentamonedero,
                'emp.emp_token' => $usuario->emp_token,
                'usuarios.user_token' => $usuario->user_token
              ])->get();

            foreach ($selectManejCuenta as $resOpciones) {
              if ($resOpciones->chequera == TRUE) {
                $chequera = true;
              } else {
                $chequera = false;
              }

              if ($resOpciones->credito == TRUE) {
                $credito = true;
              } else {
                $credito = false;
              }

              if ($resOpciones->debito == TRUE) {
                $debito = true;
              } else {
                $debito = false;
              }

              $arrayOptions = array(
                "token_manejocuentas" => $resOpciones->token_manejocuentas,
                "chequera" => $chequera,
                "credito" => $credito,
                "debito" => $debito,
                "valorManejo" => $resOpciones->clave_referencia,
                "token_personal" => $resOpciones->pers_token,
                "nombre_completo" => $JwtAuth->desencriptar($resOpciones->paterno)
                  . " " . $JwtAuth->desencriptar($resOpciones->materno)
                  . " " . $JwtAuth->desencriptar($resOpciones->nombre),
              );
              $arrayOpcionAdicional[] = $arrayOptions;
            }

            $decimalesMoneda = DB::select(
              "SELECT catmon.decimales FROM catalogo_monedas AS catmon 
                            JOIN empresas AS emp JOIN empresapersonal AS emppers JOIN personal AS pers 
                            JOIN usuarios AS users WHERE emp.moneda = catmon.id AND emp.emp_token = ?
                            AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                            AND pers.usuario = users.id AND users.user_token = ?",
              [$usuario->emp_token, $usuario->user_token]
            );

            //suman
            $cobroVenta = DB::select(
              "SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM actividad_movimientos AS movim 
                                JOIN cobros AS cobrar JOIN cuenta_monedero AS moned JOIN empresas AS emp JOIN empresapersonal AS emppers 
                                JOIN personal AS pers JOIN usuarios AS users WHERE movim.tipo_movimiento = TRUE 
                                AND movim.subtipo_movimiento = 'V' AND movim.cobro = cobrar.id AND movim.cuenta_monedero = moned.id 
                                AND cobrar.cuenta_monedero = moned.id AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id 
                                AND cobrar.empresa = emp.id AND moned.empresa = emp.id AND emp.emp_token = ?
                                AND emp.id = emppers.empresa AND emppers.personal = pers.id AND moned.responsable = pers.id 
                                AND pers.usuario = users.id AND users.user_token = ?",
              [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $usuario->emp_token, $usuario->user_token]
            );

            $devolucionCompra = DB::select(
              "SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM actividad_movimientos AS movim 
                                JOIN cobros AS cobrar JOIN cuenta_monedero AS moned JOIN empresas AS emp JOIN empresapersonal AS emppers 
                                JOIN personal AS pers JOIN usuarios AS users WHERE movim.tipo_movimiento = FALSE 
                                AND movim.subtipo_movimiento = 'D' AND movim.cobro = cobrar.id AND movim.cuenta_monedero = moned.id 
                                AND cobrar.cuenta_monedero = moned.id AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id 
                                AND cobrar.empresa = emp.id AND moned.empresa = emp.id AND emp.emp_token = ?
                                AND emp.id = emppers.empresa AND emppers.personal = pers.id AND moned.responsable = pers.id 
                                AND pers.usuario = users.id AND users.user_token = ?",
              [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $usuario->emp_token, $usuario->user_token]
            );

            //restan
            $pagoCompra = DB::select(
              "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM actividad_movimientos AS movim 
                                JOIN pagos AS payment JOIN cuenta_monedero AS moned JOIN empresas AS emp JOIN empresapersonal AS emppers 
                                JOIN personal AS pers JOIN usuarios AS users WHERE movim.tipo_movimiento = FALSE AND movim.subtipo_movimiento = 'C' 
                                AND movim.pago = payment.id AND movim.cuenta_monedero = moned.id AND payment.cuenta_monedero = moned.id
                                AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id AND payment.empresa = emp.id AND moned.empresa = emp.id 
                                AND emp.emp_token = ? AND emp.id = emppers.empresa AND emppers.personal = pers.id AND moned.responsable = pers.id 
                                AND pers.usuario = users.id AND users.user_token = ?",
              [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $usuario->emp_token, $usuario->user_token]
            );

            $devolucionVenta = DB::select(
              "SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM actividad_movimientos AS movim 
                                JOIN pagos AS payment JOIN cuenta_monedero AS moned JOIN empresas AS emp JOIN empresapersonal AS emppers 
                                JOIN personal AS pers JOIN usuarios AS users WHERE movim.tipo_movimiento = TRUE AND movim.subtipo_movimiento = 'D' 
                                AND movim.pago = payment.id AND movim.cuenta_monedero = moned.id AND payment.cuenta_monedero = moned.id
                                AND moned.token_cuentamonedero = ? AND movim.empresa = emp.id AND payment.empresa = emp.id AND moned.empresa = emp.id 
                                AND emp.emp_token = ? AND emp.id = emppers.empresa AND emppers.personal = pers.id AND moned.responsable = pers.id 
                                AND pers.usuario = users.id AND users.user_token = ?",
              [$decimalesMoneda[0]->decimales, $resMonedero->token_cuentamonedero, $usuario->emp_token, $usuario->user_token]
            );

            $resultsalDoCuenta = $cobroVenta[0]->total + $devolucionCompra[0]->total - $pagoCompra[0]->total - $devolucionVenta[0]->total;
            $salDoCuenta = DB::select("SELECT FORMAT(?,?) AS saldo", [$resultsalDoCuenta, $decimalesMoneda[0]->decimales]);

            $arrayMonedero = array(
              'token_cuentaMon' => $resMonedero->token_cuentamonedero,
              'fecha_alta_cuentamoned' => gmdate('Y-m-d H:i:s', $resMonedero->fecha_alta_cuentamoned),
              'folio' => $JwtAuth->generar($resMonedero->folio_cuentmon),

              'cuenta_bancaria' =>  $cuenta_bancaria,
              'name_cuenta_bancaria' =>  $name_cuenta,

              'token_caja' => $token_caja,
              'folio_caja' => $folio_caja,
              'alias_caja' => $alias_caja,

              'referencia_encrypt' => $referencia,
              'referencia' => $referencia,
              'cuenta_monedero_encrypt' => $cuenta_monedero,
              'cuenta_monedero' => $cuenta_monedero,
              'clabe_inter_encrypt' => $clabeInter,
              'clabe_inter' => $clabeInter,
              'titular' => $titular,
              'moneda' => $resMoneda,
              'egresos' => $egresos,
              'v_humano' => $v_humano,
              'vigencia' => gmdate('Y-m-d H:i:s', $resMonedero->vigencia),
              'opciones_adicionales' => $arrayOpcionAdicional,
              'saldofloat' => $salDoCuenta[0]->saldo,
              'salDoCuenta' => "$" . $salDoCuenta[0]->saldo,
            );

            $detalleMonedero[] = $arrayMonedero;
          }
          $dataMensaje = array(
            'monedero' => $detalleMonedero,
            'code' => 200,
            'status' => 'success'
          );
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
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    $folioMonedero = DB::select("SELECT 
            IF (max(folio_cuentmon) IS NOT NULL,(max(folio_cuentmon)+1),1) AS folio
            FROM cuenta_monedero AS monedero JOIN empresas AS emp JOIN empresapersonal AS empper 
            JOIN personal AS pers JOIN usuarios AS users
            WHERE monedero.empresa = emp.id AND emp.emp_token = ?
            AND emp.id = empper.empresa AND empper.personal = pers.id
            AND pers.usuario = users.id AND users.user_token = ?", [$usuario->emp_token, $usuario->user_token]);


    return response()->json([
      'monedero' => $JwtAuth->generar($folioMonedero[0]->folio),
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function ListaMonederoVig(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $listaMonedero = array();
    $verMonedero = CuentaMonederoModelo::join("monedero_electronico AS mon", "cuenta_monedero.monedero", "mon.id")
      ->join("empresas AS emp", "cuenta_monedero.empresa", "emp.id")
      ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
      ->join("personal", "empresapersonal.personal", "personal.id")
      ->join("usuarios", "personal.usuario", "usuarios.id")
      ->where([
        'cuenta_monedero.status' => TRUE,
        'emp.emp_token' => $usuario->emp_token,
        'usuarios.user_token' => $usuario->user_token
      ])->orderBy('cuenta_monedero.id', 'DESC')->get();

    foreach ($verMonedero  as $resMonedero) {
      if ($resMonedero->egresos == TRUE) {
        $egresos = true;
      } else {
        $egresos = false;
      }

      if ($resMonedero->ingresos == TRUE) {
        $ingresos = true;
      } else {
        $ingresos = false;
      }

      if ($resMonedero->v_humano == TRUE) {
        $v_humano = true;
      } else {
        $v_humano = false;
      }

      $arrayMonedero = array(
        "token_cuentaMon" => $resMonedero->token_cuentamonedero,
        "cuenta" => $JwtAuth->desencriptar($resMonedero->cuenta),
        "egresos" => $egresos,
        "ingresos" => $ingresos,
        "v_humano" => $v_humano,
        "monedero" => $resMonedero->nombre,
        "token_monelectronico" => $resMonedero->token_monelectronico
      );

      $listaMonedero[] = $arrayMonedero;
    }

    return response()->json([
      'mondero' => $listaMonedero,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function ListaMonederoDel(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $listaMonedero = array();
    $verMonedero = CuentaMonederoModelo::join("monedero_electronico AS mon", "cuenta_monedero.monedero", "mon.id")
      ->join("empresas AS emp", "cuenta_monedero.empresa", "emp.id")
      ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
      ->join("personal", "empresapersonal.personal", "personal.id")
      ->join("usuarios", "personal.usuario", "usuarios.id")
      ->where([
        'cuenta_monedero.status' => FALSE,
        'emp.emp_token' => $usuario->emp_token,
        'usuarios.user_token' => $usuario->user_token
      ])->orderBy('cuenta_monedero.id', 'DESC')->get();

    foreach ($verMonedero  as $resMonedero) {
      //da_te_default_timezone_set($resMonedero->zona_horaria);

      if ($resMonedero->egresos == TRUE) {
        $egresos = true;
      } else {
        $egresos = false;
      }

      if ($resMonedero->ingresos == TRUE) {
        $ingresos = true;
      } else {
        $ingresos = false;
      }

      if ($resMonedero->v_humano == TRUE) {
        $v_humano = true;
      } else {
        $v_humano = false;
      }

      $arrayMonedero = array(
        "token_cuentaMon" => $resMonedero->token_cuentamonedero,
        "referencia" => $JwtAuth->desencriptar($resMonedero->referencia),
        "cuenta" => $JwtAuth->desencriptar($resMonedero->cuenta),
        "egresos" => $egresos,
        "ingresos" => $ingresos,
        "v_humano" => $v_humano,
        "monedero" => $resMonedero->nombre,
        "token_monelectronico" => $resMonedero->token_monelectronico,
        "fecha_delete" => gmdate('Y-m-d H:i:s', $resMonedero->fecha_delete_mon)
      );

      $listaMonedero[] = $arrayMonedero;
    }

    return response()->json([
      'mondero' => $listaMonedero,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function detalleMonederoVig(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    $detalleMonedero = array();
    $lisMonElectronicos = array();
    $arrayOpcionAdicional = array();
    $cuentaBancaria = array();
    $caaJa = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'token_monedero' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'Monedero electrónico invalido',
          'errors' => $validate->errors()
        );
      } else {
        $monedero = CuentaMonederoModelo::join("monedero_electronico AS mon", "cuenta_monedero.monedero", "mon.id")
          ->join("empresas AS emp", "cuenta_monedero.empresa", "emp.id")
          ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "personal.id")
          ->join("usuarios", "personal.usuario", "usuarios.id")
          ->where([
            'cuenta_monedero.status' => TRUE,
            'cuenta_monedero.token_cuentamonedero' => $parametrosArray['token_monedero'],
            'emp.emp_token' => $usuario->emp_token,
            'usuarios.user_token' => $usuario->user_token
          ])->get();

        foreach ($monedero as $resMonedero) {
          $monederoElectr = MonedElectModelo::all();
          foreach ($monederoElectr as  $valMonElect) {
            $relMonedero = false;
            if ($valMonElect->token_monelectronico == $resMonedero->token_monelectronico) {
              $relMonedero = true;
            } else {
              $relMonedero = false;
            }

            $arrayMonedero = array(
              "token_monelectronico" => $valMonElect->token_monelectronico,
              "nombreMon" => $valMonElect->nombre,
              "status" => $relMonedero
            );
            $lisMonElectronicos[] = $arrayMonedero;
          }

          $cuentaBancoMon = CuentBancModelo::join("empresas AS emp", "cuenta.empresa", "emp.id")
            ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
            ->join("personal", "empresapersonal.personal", "personal.id")
            ->join("usuarios", "personal.usuario", "usuarios.id")
            ->where([
              'cuenta.status' => TRUE,
              //'cuenta.token_cuenta' => $tknCount,
              'emp.emp_token' => $usuario->emp_token,
              'usuarios.user_token' => $usuario->user_token
            ])->get();

          foreach ($cuentaBancoMon as $resCuentaMon) {
            $relCuntaBancaria = false;
            if ($resMonedero->cuenta_banco != NULL) {
              $tknCount = DB::select("SELECT token_cuenta FROM cuenta WHERE id = ? ", [$resMonedero->cuenta_banco]);
              if ($resCuentaMon->token_cuenta == $tknCount[0]->token_cuenta) {
                $relCuntaBancaria = true;
              } else {
                $relCuntaBancaria = false;
              }
            } else {
              $relCuntaBancaria = false;
            }

            $arrayCuenta = array(
              "token_cuenta" => $resCuentaMon->token_cuenta,
              "cuenta" => $JwtAuth->desencriptar($resCuentaMon->cuenta),
              "relStatus" => $relCuntaBancaria
            );
            $cuentaBancaria[] = $arrayCuenta;
          }

          $cajaMonedero = CajaModelo::join("empresas AS emp", "caja.empresa", "emp.id")
            ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
            ->join("personal", "empresapersonal.personal", "personal.id")
            ->join("usuarios", "personal.usuario", "usuarios.id")
            ->where([
              'caja.status' => TRUE,
              'emp.emp_token' => $usuario->emp_token,
              'usuarios.user_token' => $usuario->user_token
            ])->get();

          foreach ($cajaMonedero as $resCajaMon) {
            $relacionCaja = false;
            if ($resMonedero->caja != NULL) {
              $tokenCaja = DB::select("SELECT token_caja FROM caja WHERE id = ? ", [$resMonedero->caja]);
              if ($resCajaMon->token_caja == $tokenCaja[0]->token_caja) {
                $relacionCaja = true;
              } else {
                $relacionCaja = false;
              }
            } else {
              $relacionCaja = false;
            }

            $arrayCaja = array(
              "token_caja" => $resCajaMon->token_caja,
              "caja" => $JwtAuth->generar($resCajaMon->no_caja),
              "alias" => $JwtAuth->desencriptar($resCajaMon->alias_caja),
              "relStatus" => $relacionCaja
            );
            $caaJa[] = $arrayCaja;
          }

          $referencia = $JwtAuth->desencriptar($resMonedero->referencia);
          $cuenta = $JwtAuth->desencriptar($resMonedero->cuenta);
          $clabeInter = $JwtAuth->desencriptar($resMonedero->clabe_inter);
          $titular = $JwtAuth->desencriptar($resMonedero->titular);

          $moneda = DB::select("SELECT codigo,moneda FROM catalogo_monedas WHERE id = ?", [$resMonedero->moneda]);
          $resMoneda = $moneda[0]->codigo . "-" . $moneda[0]->moneda;

          if ($resMonedero->egresos == TRUE) {
            $egresos = true;
          } else {
            $egresos = false;
          }

          if ($resMonedero->ingresos == TRUE) {
            $ingresos = true;
          } else {
            $ingresos = false;
          }

          if ($resMonedero->v_humano == TRUE) {
            $v_humano = true;
          } else {
            $v_humano = false;
          }

          $selectManejCuenta = DB::table('manejo_cuentas')
            ->join("cuenta_monedero AS countMon", "manejo_cuentas.cuenta_monedero", "countMon.id")
            ->join("empresas AS emp", "manejo_cuentas.empresa", "emp.id")
            ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
            ->join("personal", "empresapersonal.personal", "personal.id")
            ->join("personas AS people", "personal.personal", "people.id")
            ->join("usuarios", "personal.usuario", "usuarios.id")
            ->where([
              'manejo_cuentas.cuenta_bancaria' => NULL,
              'countMon.token_cuentamonedero' => $parametrosArray['token_monedero'],
              'emp.emp_token' => $usuario->emp_token,
              'usuarios.user_token' => $usuario->user_token
            ])->get();

          foreach ($selectManejCuenta as $resOpciones) {
            if ($resOpciones->chequera == TRUE) {
              $chequera = true;
            } else {
              $chequera = false;
            }

            if ($resOpciones->credito == TRUE) {
              $credito = true;
            } else {
              $credito = false;
            }

            if ($resOpciones->debito == TRUE) {
              $debito = true;
            } else {
              $debito = false;
            }

            $arrayOptions = array(
              "token_manejocuentas" => $resOpciones->token_manejocuentas,
              "chequera" => $chequera,
              "credito" => $credito,
              "debito" => $debito,
              "valorManejo" => $resOpciones->clave_referencia,
              "token_personal" => $resOpciones->pers_token,
              "nombre_completo" => $JwtAuth->desencriptar($resOpciones->paterno)
                . " " . $JwtAuth->desencriptar($resOpciones->materno)
                . " " . $JwtAuth->desencriptar($resOpciones->nombre),
            );
            $arrayOpcionAdicional[] = $arrayOptions;
          }

          $arrayMonedero = array(
            'token_cuentaMon' => $resMonedero->token_cuentamonedero,
            'folio' => $JwtAuth->generar($resMonedero->folio_cuentmon),
            'monedero' =>  $lisMonElectronicos,
            'referencia' => $referencia,
            'cuenta' => $cuenta,
            'clabe_inter' => $clabeInter,
            'titular' => $titular,
            'moneda' => $resMoneda,
            'egresos' => $egresos,
            'ingresos' => $ingresos,
            'v_humano' => $v_humano,
            'vigencia' => gmdate('Y-m-d H:i:s', $resMonedero->vigencia),
            'opciones_adicionales' => $arrayOpcionAdicional,
            'cuenta_banco' => $cuentaBancaria,
            'caja' =>  $caaJa
          );

          $detalleMonedero[] = $arrayMonedero;
        }

        $dataMensaje = array(
          'mondero' => $detalleMonedero,
          'code' => 200,
          'status' => 'success'
        );
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

  public function registrarMonederoElctronico(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'monedero.token_monelectronico' => 'required|string',
        'monedero.no_referencia' => 'required|string',
        'monedero.cuenta' => 'required|string',
        'monedero.clabe_inter' => 'required|string',
        'monedero.vigencia' => 'required|string',
        'monedero.titularCuenta' => 'required|string',
        'monedero.moneda' => 'required|string',
        'monedero.areaEgresos' => 'required|boolean',
        'monedero.areaIngresos' => 'required|boolean',
        'monedero.areaValHumano' => 'required|boolean'
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

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp  
                JOIN empresapersonal AS emppers JOIN personal AS pers JOIN usuarios AS users WHERE emp.emp_token = ? 
                AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                AND pers.usuario = users.id AND users.user_token= ?", [$usuario->emp_token, $usuario->user_token]);

        $valMoneda = explode('-', $parametrosArray['monedero']['moneda']);
        //echo $valMoneda." ".$valMoneda[0];
        $idMoneda = DB::select("SELECT id FROM catalogo_monedas WHERE codigo = ?", [$valMoneda[0]]);
        //echo $idMoneda[0]->id;

        $tknMonedero = DB::select("SELECT id FROM monedero_electronico WHERE token_monelectronico = ?", [$parametrosArray['monedero']['token_monelectronico']]);
        $tknMonedero[0]->id;

        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

        $tokenMonedero = $JwtAuth->encriptarToken(
          time(),
          $parametrosArray['monedero']['token_monelectronico'],
          $parametrosArray['monedero']['no_referencia'],
          $parametrosArray['monedero']['cuenta'],
          $parametrosArray['monedero']['clabe_inter']
        );

        $folioMonedero = DB::select("SELECT 
                IF (max(folio_cuentmon) IS NOT NULL,(max(folio_cuentmon)+1),1) AS folio
                FROM cuenta_monedero AS monedero JOIN empresas AS emp JOIN empresapersonal AS empper 
                JOIN personal AS pers JOIN usuarios AS users
                WHERE monedero.empresa = emp.id AND emp.emp_token = ?
                AND emp.id = empper.empresa AND empper.personal = pers.id
                AND pers.usuario = users.id AND users.user_token = ?", [$usuario->emp_token, $usuario->user_token]);

        $referenciaEnconde = $JwtAuth->encriptar($parametrosArray['monedero']['no_referencia']);
        $cuentaEncode = $JwtAuth->encriptar($parametrosArray['monedero']['cuenta']);
        $clabeInterEncode = $JwtAuth->encriptar($parametrosArray['monedero']['clabe_inter']);
        $titularEncode = $JwtAuth->encriptar($parametrosArray['monedero']['titularCuenta']);

        if ($parametrosArray['monedero']['token_cuentaBanc'] == '') {
          $cuenta_banco = NULL;
        } else {
          $tokenCuentaBanc = DB::select("SELECT id FROM cuenta WHERE token_cuenta = ?", [$parametrosArray['monedero']['token_cuentaBanc']]);
          $cuenta_banco = $tokenCuentaBanc[0]->id;
        }

        if ($parametrosArray['monedero']['token_caja'] == '') {
          $caja = NULL;
        } else {
          $tokenCaja = DB::select("SELECT id FROM caja WHERE token_caja = ?", [$parametrosArray['monedero']['token_caja']]);
          $caja = $tokenCaja[0]->id;
        }

        if ($parametrosArray['monedero']['vigencia'] > time()) {
          $vigencia = $JwtAuth->convierteFechaEpoc($parametrosArray['monedero']['vigencia']);
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'La vigencia del monedero electrónico ha vencido'
          );
        }

        $newMonedero = new CuentaMonederoModelo();
        $newMonedero->token_cuentamonedero = $tokenMonedero;
        $newMonedero->folio_cuentmon = $folioMonedero[0]->folio;
        $newMonedero->monedero = $tknMonedero[0]->id;
        $newMonedero->referencia = $referenciaEnconde;
        $newMonedero->cuenta = $cuentaEncode;
        $newMonedero->clabe_inter = $clabeInterEncode;
        $newMonedero->titular = $titularEncode;
        $newMonedero->moneda = $idMoneda[0]->id;
        $newMonedero->egresos = $parametrosArray['monedero']['areaEgresos'];
        $newMonedero->ingresos = $parametrosArray['monedero']['areaIngresos'];
        $newMonedero->v_humano = $parametrosArray['monedero']['areaValHumano'];
        $newMonedero->cuenta_banco = $cuenta_banco;
        $newMonedero->caja = $caja;
        $newMonedero->vigencia = $vigencia;
        $newMonedero->status = TRUE;
        $newMonedero->fecha_delete_mon = '';
        $newMonedero->empresa = $selectEmp[0]->id;
        $savedMonedero = $newMonedero->save();

        if ($savedMonedero) {
          $contador = 0;
          $contadorManejo = 0;
          if (count($parametrosArray['monedero']['opciones_adicionales']) != 0) {
            for ($i = 0; $i < count($parametrosArray['monedero']['opciones_adicionales']); $i++) {
              if (
                $parametrosArray['monedero']['opciones_adicionales'][$i]['clave'] != '' &&
                $parametrosArray['monedero']['opciones_adicionales'][$i]['valor'] != ''  &&
                $parametrosArray['monedero']['opciones_adicionales'][$i]['responsable'] != ''
              ) {
                $contador++;
              } else {
                //validacion de posiciones
                if ($parametrosArray['monedero']['opciones_adicionales'][$i]['clave'] == '') {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Error en manejo de opciones adicionales'
                  );
                }

                if ($parametrosArray['monedero']['opciones_adicionales'][$i]['valor'] == '') {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Error en la referencia de opciones adicionales'
                  );
                }

                if ($parametrosArray['monedero']['opciones_adicionales'][$i]['responsable'] == '') {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Error en el responsable de opciones adicionales'
                  );
                }
              }
            }

            if ($contador == count($parametrosArray['monedero']['opciones_adicionales'])) {

              $tknManejo = $JwtAuth->encriptarToken(
                time(),
                $parametrosArray['monedero']['token_caja'],
                $referenciaEnconde,
                $cuentaEncode,
                $clabeInterEncode,
                $titularEncode
              );

              $cuentaMon = DB::select(
                "SELECT monedero.id 
                                FROM cuenta_monedero AS monedero 
                                JOIN empresas AS emp 
                                JOIN empresapersonal AS emppers 
                                JOIN personal AS pers 
                                JOIN usuarios AS users 
                                WHERE monedero.token_cuentamonedero = ? 
                                AND monedero.empresa = emp.id 
                                AND emp.emp_token = ?
                                AND emp.id = emppers.empresa 
                                AND emppers.personal = pers.id 
                                AND pers.usuario = users.id 
                                AND users.user_token = ?",
                [$tokenMonedero, $usuario->emp_token, $usuario->user_token]
              );

              if ($parametrosArray['monedero']['opciones_adicionales']['clave'] == 'Chequera') {
                $chequera = true;
              } else {
                $chequera = false;
              }

              if ($parametrosArray['monedero']['opciones_adicionales']['clave'] == 'Tarjetas de crédito') {
                $credito = true;
              } else {
                $credito = false;
              }

              if ($parametrosArray['monedero']['opciones_adicionales']['clave'] == 'Tarjetas de débito') {
                $debito = true;
              } else {
                $debito = false;
              }

              $idPersonal = DB::select(
                "SELECT id FROM personal WHERE pers_token = ?",
                [$parametrosArray['monedero']['opciones_adicionales']['responsable']]
              );

              $insertOpciones = DB::table('manejo_cuentas')
                ->insert(array(
                  "token_manejocuentas" => $tknManejo,
                  "cuenta_bancaria" => NULL,
                  "cuenta_monedero" => $cuentaMon[0]->id,
                  "chequera" => $chequera,
                  "credito" => $credito,
                  "debito" => $debito,
                  "referencia" => $parametrosArray['monedero']['opciones_adicionales']['valor'],
                  "responsable" => $idPersonal,
                  "empresa" => $selectEmp[0]->id,
                ));

              for ($j = 0; $j < count($parametrosArray['monedero']['opciones_adicionales']); $j++) {
                if ($insertOpciones) {
                  $contadorManejo++;
                } else {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Manejo de cuentas no valido'
                  );
                }
              }

              if ($contadorManejo == count($parametrosArray['monedero']['opciones_adicionales'])) {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'Manejo de cuentas registrado correctamente'
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 400,
                  'message' => 'Manejo de cuentas no valido'
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Error en la información de opciones adicionales'
              );
            }
          } else {
            if ($contador == count($parametrosArray['monedero']['opciones_adicionales'])) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Cuenta de monedero electrónico registrada satisfactoriamente'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Esta cuenta de monedero electrónico no fue registrada debido a problemas internos, comuniquese a soporte para más información'
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Los datos del monedero electrónico no son correctos, error al intentar registrar'
          );
        }
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

  public function updateMonederoElectronico(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'token_cuentaMon' => 'required|string',
        'monedero.token_monelectronico' => 'required|string',
        'monedero.no_referencia' => 'required|string',
        'monedero.cuenta' => 'required|string',
        'monedero.clabe_inter' => 'required|string',
        'monedero.titularCuenta' => 'required|string',
        'monedero.moneda' => 'required|string',
        'monedero.areaEgresos' => 'required|boolean',
        'monedero.areaIngresos' => 'required|boolean',
        'monedero.areaValHumano' => 'required|boolean',
        'monedero.token_cuentaBanc' => 'required|string',
        'monedero.token_caja' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'Monedero electrónico invalido',
          'errors' => $validate->errors()
        );
      } else {
        $selectMonElect = DB::select(
          "SELECT id FROM monedero_electronico WHERE token_monelectronico = ?",
          [$parametrosArray['monedero']['token_monelectronico']]
        );

        $valMoneda = explode('-', $parametrosArray['monedero']['moneda']);
        $idMoneda = DB::select("SELECT id FROM catalogo_monedas WHERE codigo = ?", [$valMoneda[0]]);

        $selectCuenta = DB::select("SELECT id FROM cuenta WHERE token_cuenta = ?", [$parametrosArray['monedero']['token_cuentaBanc']]);
        $selectaja = DB::select("SELECT id FROM caja WHERE token_caja = ?", [$parametrosArray['monedero']['token_caja']]);

        if ($parametrosArray['monedero']['vigencia'] > time()) {
          $vigencia = $JwtAuth->convierteFechaEpoc($parametrosArray['monedero']['vigencia']);
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'La vigencia del dispositivo ha vencido'
          );
        }

        if ($parametrosArray['monedero']['operaciones_adicionales'] == '') {
          $opcion_adicional = NULL;
        } else {
          $contador = 0;
          for ($i = 0; $i < count($parametrosArray['monedero']['operaciones_adicionales']); $i++) {
            //echo "mensaje ".count($parametrosArray['monedero']['operaciones_adicionales'][$i]);
            if (
              $parametrosArray['monedero']['operaciones_adicionales'][$i]['clave'] != '' &&
              $parametrosArray['monedero']['operaciones_adicionales'][$i]['valor'] != '' &&
              $parametrosArray['monedero']['operaciones_adicionales'][$i]['responsable'] != ''
            ) {
              $contador++;
            }
          }
          //echo $contador; 
          if ($contador == count($parametrosArray['monedero']['operaciones_adicionales'])) {
            $opcion_adicional = $JwtAuth->encriptar(json_encode($parametrosArray['monedero']['operaciones_adicionales']));
          }
        }

        $consultCuentaMon = CuentaMonederoModelo::join("empresas AS emp", "cuenta_monedero.empresa", "emp.id")
          ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "personal.id")
          ->join("usuarios", "personal.usuario", "usuarios.id")
          ->where([
            'cuenta_monedero.token_cuentamonedero' => $parametrosArray['token_cuentaMon'],
            'cuenta_monedero.status' => TRUE,
            'emp.emp_token' => $usuario->emp_token,
            'usuarios.user_token' => $usuario->user_token
          ])->count();

        if ($consultCuentaMon == 1) {
          $updateMon = DB::table('cuenta_monedero')
            ->where(
              ['token_cuentamonedero' => $parametrosArray['token_cuentaMon']]
            )
            ->limit(1)->update(
              array(
                'monedero' => $selectMonElect[0]->id,
                'referencia' => $JwtAuth->encriptar($parametrosArray['monedero']['no_referencia']),
                'cuenta' => $JwtAuth->encriptar($parametrosArray['monedero']['cuenta']),
                'clabe_inter' => $JwtAuth->encriptar($parametrosArray['monedero']['clabe_inter']),
                'titular' => $JwtAuth->encriptar($parametrosArray['monedero']['titularCuenta']),
                'moneda' => $idMoneda[0]->id,
                'egresos' => $parametrosArray['monedero']['areaEgresos'],
                'ingresos' => $parametrosArray['monedero']['areaIngresos'],
                'v_humano' => $parametrosArray['monedero']['areaValHumano'],
                'vigencia' => $vigencia,
                'opciones_adicionales' => $opcion_adicional,
                'cuenta_banco' => $selectCuenta[0]->id,
                'caja' => $selectaja[0]->id,
              )

            );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'El monedero electrónico que intenta modificar no existe'
          );
        }
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

  public function registrarNewManejoCuentasMon(Request $request){
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

        $idCuentaMonedero = DB::select(
          "SELECT id FROM cuenta_monedero WHERE token_cuentamonedero = ?",
          [$parametrosArray['token_cuentaMon']]
        );

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp  
                    JOIN empresapersonal AS emppers JOIN personal AS pers JOIN usuarios AS users WHERE emp.emp_token = ? 
                    AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                    AND pers.usuario = users.id AND users.user_token= ?", [$usuario->emp_token, $usuario->user_token]);
        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

        $token_manejo = $JwtAuth->encriptarToken(
          time(),
          $usuario->emp_token,
          $parametrosArray['arrayManejo'],
          $parametros,
          $parametrosArray['token_cuentaMon']
        );

        if ($parametrosArray['arrayManejo'] != '') {
          $contador = 0;

          for ($i = 0; $i < count($parametrosArray['arrayManejo']); $i++) {
            if (
              $parametrosArray['arrayManejo'][$i]['clave'] != '' &&
              $parametrosArray['arrayManejo'][$i]['valor'] != '' &&
              $parametrosArray['arrayManejo'][$i]['responsable'] != ''
            ) {

              if ($parametrosArray['arrayManejo'][$i]['clave'] == 'chequera') {
                $chequera = true;
              } else {
                $chequera = false;
              }

              if ($parametrosArray['arrayManejo'][$i]['clave'] == 'Tarjetas de credito') {
                $credito = true;
              } else {
                $credito = false;
              }

              if ($parametrosArray['arrayManejo'][$i]['clave'] == 'Tarjetas de debito') {
                $debito = true;
              } else {
                $debito = false;
              }

              $encriptRef = $JwtAuth->encriptar($parametrosArray['arrayManejo'][$i]['valor']);
              $encriptRespons = $JwtAuth->encriptar($parametrosArray['arrayManejo'][$i]['responsable']);

              $contador++;
            } else {
              if ($parametrosArray['arrayManejo'][$i]['clave'] == '') {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 400,
                  'message' => 'Error en manejo de opciones adicionales'
                );
              }

              if ($parametrosArray['arrayManejo'][$i]['valor'] == '') {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 400,
                  'message' => 'Error en la referencia de opciones adicionales'
                );
              }

              if ($parametrosArray['arrayManejo'][$i]['responsable'] == '') {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 400,
                  'message' => 'Error en el responsable de opciones adicionales'
                );
              }
            }

            $insertManejo = DB::table('manejo_cuentas')
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

          if ($contador == count($parametrosArray['arrayManejo'])) {
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'El contenido de las opciones adicionales esta vacio'
          );
        }
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
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'token_monedero' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que ha intantado actualizar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $consultcuentaMon = CuentaMonederoModelo::join("empresas AS emp", "cuenta_monedero.empresa", "emp.id")
          ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "personal.id")
          ->join("usuarios", "personal.usuario", "usuarios.id")
          ->where([
            'cuenta_monedero.token_cuentamonedero' => $parametrosArray['token_monedero'],
            'cuenta_monedero.status' => TRUE,
            'emp.emp_token' => $usuario->emp_token,
            'usuarios.user_token' => $usuario->user_token
          ])->count();

        if ($consultcuentaMon == 1) {
          $updateStatusMonedero = DB::table('cuenta_monedero')
            ->where(
              [
                'token_cuentamonedero' => $parametrosArray['token_monedero']
              ]
            )
            ->limit(1)->update(
              array(
                'fecha_delete_mon' => time(),
                'status' => FALSE
              )
            );

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
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'La cuenta de monedero electrónico que intenta eliminar no existe'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'La información que intenta guardar es incorrecta'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restaurarMonederoElctronico(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'token_monedero' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que ha intantado actualizar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $consultcuentaMon = CuentaMonederoModelo::join("empresas AS emp", "cuenta_monedero.empresa", "emp.id")
          ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "personal.id")
          ->join("usuarios", "personal.usuario", "usuarios.id")
          ->where([
            'cuenta_monedero.token_cuentamonedero' => $parametrosArray['token_monedero'],
            'cuenta_monedero.status' => FALSE,
            'emp.emp_token' => $usuario->emp_token,
            'usuarios.user_token' => $usuario->user_token
          ])->count();

        if ($consultcuentaMon == 1) {
          $updateStatusMonedero = DB::table('cuenta_monedero')
            ->where(
              [
                'token_cuentamonedero' => $parametrosArray['token_monedero']
              ]
            )
            ->limit(1)->update(
              array(
                'fecha_delete_mon' => '',
                'status' => TRUE
              )
            );

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
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'La cuenta de monedero electrónico que intenta restaurar no existe'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'La información que intenta guardar es incorrecta'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deletPermMonederoElctronico(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'token_monedero' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La infomación que ha intantado eliminar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $consultcuentaMon = CuentaMonederoModelo::join("empresas AS emp", "cuenta_monedero.empresa", "emp.id")
          ->join("empresapersonal", "emp.id", "empresapersonal.empresa")
          ->join("personal", "empresapersonal.personal", "personal.id")
          ->join("usuarios", "personal.usuario", "usuarios.id")
          ->where([
            'cuenta_monedero.token_cuentamonedero' => $parametrosArray['token_monedero'],
            'cuenta_monedero.status' => FALSE,
            'emp.emp_token' => $usuario->emp_token,
            'usuarios.user_token' => $usuario->user_token
          ])->count();

        if ($consultcuentaMon == 1) {
          $updateStatusMonedero = DB::table('cuenta_monedero')
            ->where(
              [
                'token_cuentamonedero' => $parametrosArray['token_monedero']
              ]
            )
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
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'La cuenta de monedero electrónico que intenta eliminar no existe'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'La información que intenta guardar es incorrecta'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
