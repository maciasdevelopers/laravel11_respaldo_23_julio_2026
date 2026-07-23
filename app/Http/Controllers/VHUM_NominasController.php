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
use App\Models\NominasModelo;
use App\Models\OrdenPagoModelo;
use App\Services\FirebaseService;
use App\Models\CuentaMonederoModelo;
use App\Models\CuentBancModelo;
use App\Models\CajaModelo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VHUM_NominasController extends Controller{
  public function reportesNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
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
        $nominas = array();
        
        $queryRepNomina = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "emp.id")
        ->whereIn('vhum_nominas_main.id', function ($query) {
          $query->select('nomina_main')->from('vhum_nominas_recibos');
        })
        ->where([
          'vhum_nominas_main.nomina_status' => TRUE,
          'emp.empresa_token' => $usuario->empresa_token,
        ])
        ->orderBy('vhum_nominas_main.id', 'DESC')->get();

        foreach ($queryRepNomina as $vNomina) {
          //da_te_default_timezone_set('UTC');
          $totales_nomina_reporte_efectivo = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('nrec.total_efectivo');

          $moneda_nomina_recibos = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->value('nrec.nomina_moneda');

          $totales_nomina_pago_efectivo = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('pay.monto_pago');

					$totales_nomina_saldo_efectivo = $totales_nomina_reporte_efectivo - $totales_nomina_pago_efectivo;

          $queryNominaEfectOrdPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
					$nomina_efectivo_ord_pago_token = $queryNominaEfectOrdPago ? $queryNominaEfectOrdPago->token_ordenPago :'';
					$nomina_efectivo_ord_pago_folio = $queryNominaEfectOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryNominaEfectOrdPago->folio_ordenPago) :'';

          $totales_nomina_reporte_especie = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('nrec.total_en_especie');

          $totales_nomina_pago_especie = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNotNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('pay.monto_pago');

          $totales_nomina_saldo_especie = $totales_nomina_reporte_especie - $totales_nomina_pago_especie;

          $queryNominaEspeOrdPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNotNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
					$nomina_especie_ord_pago_token = $queryNominaEspeOrdPago ? $queryNominaEspeOrdPago->token_ordenPago :'';
					$nomina_especie_ord_pago_folio = $queryNominaEspeOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryNominaEspeOrdPago->folio_ordenPago) :'';

          $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->count();

          $nominas[] = array(
            'token_nominas_periodos' => $vNomina->token_nominas_periodos,
            'folio_interior' => 'NOM-EF-'.$JwtAuth->generarFolio($vNomina->nomina_folio_interior).(!is_null($vNomina->nomina_subfolio) ? '-'.$vNomina->nomina_subfolio : ''),
            'nomina_numero' => $vNomina->nomina_numero,
            //'nomina_moneda' => $vNomina->nomina_moneda,
            'nomina_fecha_contabilizacion' => gmdate('Y-m-d H:i:s',$vNomina->nomina_fecha_contabilizacion),
            //'centrotrab_uuid' => $vNomina->centrotrab_uuid,
            //'centrotrab_registro_patronal_imss' => $vNomina->centrotrab_clave_registro_patronal_imss,
            //'nomina_periodo_pago' => date('d/m/Y',$vNomina->nomina_periodo_fecha_inicio)." - ".date('d/m/Y',$vNomina->nomina_periodo_fecha_fin),
            //'nomina_periodicidad' => $vNomina->nomina_periodicidad,
            //efectivo
            'nomina_reporte_efectivo' => "$".number_format($totales_nomina_reporte_efectivo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_pago_efectivo' => "$".number_format($totales_nomina_pago_efectivo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_saldo_efectivo' => "$".number_format($totales_nomina_saldo_efectivo,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_efectivo_ord_pago_token' => $nomina_efectivo_ord_pago_token,
            'nomina_efectivo_ord_pago_folio' => $nomina_efectivo_ord_pago_folio,
            //especie
            'nomina_reporte_especie' => "$".number_format($totales_nomina_reporte_especie,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_pago_especie' => "$".number_format($totales_nomina_pago_especie,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_saldo_especie' => "$".number_format($totales_nomina_saldo_especie,$JwtAuth->getMonedaAPI($moneda_nomina_recibos),'.', ',')." $moneda_nomina_recibos",
            'nomina_especie_ord_pago_token' => $nomina_especie_ord_pago_token,
            'nomina_especie_ord_pago_folio' => $nomina_especie_ord_pago_folio,
            'vinculacion_a_pagos' => $queryNominaPago > 0 ? true : false
          );
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'nominas' => $nominas
        );
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

  public function nominaEfectivoSeguimientoOrdenPago(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_nominas_periodos' => 'required|string',
        'nomina_efectivo_ord_pago_token' => 'required|string',
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
				$token_nominas_periodos = $parametrosArray['token_nominas_periodos'];
				$nomina_efectivo_ord_pago_token = $parametrosArray['nomina_efectivo_ord_pago_token'];
        $orden_pago_nomina = array();
        $pagos_realizados_nomina = array();
        
        $queryNominaOrdenPago = NominasModelo::join("fnzs_pagos_orden AS order", "vhum_nominas_main.id", "=", "order.nomina_main")
        ->join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
        ->join("sos_personas AS people", "emp.persona", "=", "people.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->whereIn('vhum_nominas_main.id', function ($query) {
          $query->select('nomina_main')->from('vhum_nominas_recibos');
        })
        ->whereNull("order.nomina_en_especie")
        ->where([
          'vhum_nominas_main.nomina_status' => TRUE,
          'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
          'order.token_ordenPago' => $nomina_efectivo_ord_pago_token,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();
        //echo count($queryNominaPagos);
				foreach ($queryNominaOrdenPago as $rOrdPag) {
					//da_te_default_timezone_set($rOrdPag->zona_horaria);
					$autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;
					$fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? gmdate('Y-m-d H:i:s', $rOrdPag->fecha_autorizacion_pay) : "---";
					$status_pay_bool = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? true : false;

					$orden_emisor_emp = $rOrdPag->abrev_nombre;

          $importe_total_anticipo = 0;
					$importe_total_inicial = 0;
					$orden_moneda_inicial_name = $rOrdPag->nomina_moneda;
					$orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomina_moneda);

					$importe_autorizado_inicial = 0;
					$orden_moneda_autorizado_inicial_tkn = $rOrdPag->nomina_moneda;
					$orden_moneda_autorizado_inicial_name = $rOrdPag->nomina_moneda;
					$orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomina_moneda);

					$importe_autorizado_final = 0;
					$orden_moneda_autorizado_final_name = $rOrdPag->nomina_moneda;
					$orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomina_moneda);
          
          $importe_concepto_simple = floatval(DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$rOrdPag->token_nominas_periodos)
          ->sum('nrec.total_efectivo'));
          $importe_total_inicial = $importe_total_inicial + $importe_concepto_simple;
          $importe_autorizado_inicial = $importe_autorizado_inicial + $importe_concepto_simple;
          $importe_autorizado_final = $importe_autorizado_final + $importe_concepto_simple;

					//pagos_realizados
          $status_pay_date = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? gmdate('Y-m-d H:i:s', $rOrdPag->orden_terminada_fecha) : "---";
          $pagos_realizados = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=", "vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->where(["order.token_ordenPago" => $rOrdPag->token_ordenPago])
          ->sum('vinc.orden_pago_monto');

          $pagos_realizados_orden = $JwtAuth->pagosDoneBYOrden($rOrdPag->token_ordenPago);

					$pago_restante = count($pagos_realizados_orden) > 0 ? $importe_autorizado_final - $pagos_realizados : $importe_autorizado_final;

          $lpr = $pagos_realizados_orden;
          $pago_rr_forma_metodo_pago_cfdi = '';
          if (count($lpr) > 0) {
            if ($lpr[0]['forma_pago_cfdi'] != '' && $lpr[0]['metodo_pago_cfdi'] != '') {
              $pago_rr_forma_metodo_pago_cfdi = $lpr[0]['forma_pago_cfdi']." / ".$lpr[0]['metodo_pago_cfdi'];
            } elseif ($lpr[0]['forma_pago_cfdi'] != '' && $lpr[0]['metodo_pago_cfdi'] == '') {
              $pago_rr_forma_metodo_pago_cfdi = $lpr[0]['forma_pago_cfdi'];
            } elseif ($lpr[0]['forma_pago_cfdi'] == '' && $lpr[0]['metodo_pago_cfdi'] != '') {
              $pago_rr_forma_metodo_pago_cfdi = $lpr[0]['metodo_pago_cfdi'];
            } else {
              $pago_rr_forma_metodo_pago_cfdi = '';
            }
          }
          
          $row_ordenPay = array(
            "id" => 1,
            "token_ordenPago" => $rOrdPag->token_ordenPago,
            "folio_ordenPago" => "ORDP-".$JwtAuth->generarFolio($rOrdPag->folio_ordenPago),
            "fecha_contabilizacion_doc_anterior" => gmdate('Y-m-d H:i:s',$rOrdPag->nomina_fecha_contabilizacion),
            "fecha_contabilizacion_orden_pago" => $rOrdPag->fecha_contabilizacion_ordenPago ? gmdate('Y-m-d H:i:s',$rOrdPag->fecha_contabilizacion_ordenPago) : '',
            "fecha_registro" => gmdate('Y-m-d H:i:s', $rOrdPag->fecha_sistema_ordenp),
            "orden_bloqueada" => $rOrdPag->orden_bloqueada ? true : false,
            "autorizacion_pay" => $autorizacion_pay,
            "autorizacion_pay_translate" => $autorizacion_pay ? 'yes_auth' : 'not_auth',
            "autorizacion_pay_text" => "",
            "fecha_autorizacion_pay" => $fecha_autorizacion_pay,
            "factura_relacionada_typo" => "nominas",
            "factura_relacionada_token" => $rOrdPag->token_nominas_periodos,
            "factura_relacionada_string" => 'NOM-EF-'.$JwtAuth->generarFolio($rOrdPag->nomina_folio_interior).(!is_null($rOrdPag->nomina_subfolio) ? '-'.$rOrdPag->nomina_subfolio : ''),
            "orden_emisor_emp" => $orden_emisor_emp,

            "importe_total_inicial_simple" => $importe_total_inicial,
            "orden_moneda_inicial_name" => $orden_moneda_inicial_name,
            "importe_total_inicial" => $JwtAuth->muestraCantidadesConMoneda($importe_total_inicial,$orden_moneda_inicial_name,$orden_moneda_inicial_decimales),
            "importe_autorizado_inicial_simple" => number_format($importe_autorizado_inicial, $orden_moneda_autorizado_inicial_decimales, '.', ''),
            "orden_moneda_inicial_autorizada_tkn" => $orden_moneda_autorizado_inicial_tkn,
            "orden_moneda_inicial_autorizada_name" => $orden_moneda_autorizado_inicial_name,
            "importe_autorizado_inicial_format" => $JwtAuth->muestraCantidadesConMoneda($importe_autorizado_inicial,$orden_moneda_autorizado_inicial_name,$orden_moneda_autorizado_inicial_decimales),
            //$orden_moneda_inicial_decimales = 0;
            "importe_autorizado_final_simple" => number_format($importe_autorizado_final, $orden_moneda_autorizado_final_decimales, '.', ''),
            "importe_autorizado_final" => $JwtAuth->muestraCantidadesConMoneda($importe_autorizado_final,$orden_moneda_autorizado_final_name,$orden_moneda_autorizado_final_decimales),
            "orden_moneda_final_autorizada_name" => $orden_moneda_autorizado_final_name,
            "importe_restante" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
            "importe_restante_format" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
            "importe_por_pagar" => "0.00",
            "debe_simple" => number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ''),
            "debe_format" => "$".number_format($pago_restante, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
            "pago_anticipado" => "$".number_format($importe_total_anticipo, $orden_moneda_autorizado_final_decimales, '.', ',')." $orden_moneda_autorizado_final_name",
            //$orden_moneda_final_decimales = 0;
            "status_pago" => $status_pay_bool,
            "status_pago_date" => $status_pay_date,
            "empresa" => "", //empresa
            "comprador" => "", //comprador
            "open_inside" => false, //comprador
            "detail_orden" => [], //comprador
            "autorizacion_proceso" => false, //comprador
            //pagos_realizados
            //"pagos_realizados_orden" => $pagos_realizados_orden,
            "pago_realizado_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['token_pagos'] : '',
            "pago_realizado_folio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['folio_pagos'] : '',
            "pago_realizado_status" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['status_pago'] : '',
            "pago_realizado_folio_operacion" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['folio_operacion'] : '',
            "pago_realizado_fecha_pago" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['fecha_pago'] : '',
            "pago_realizado_fecha_contabilizacion" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['fecha_contabilizacion'] : '',
            "pago_realizado_monto" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['monto_pago'] : '',
            "pago_realizado_observaciones" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['observacionesPago'] : '',
            "pago_realizado_tipo_cambio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['tipo_cambio'] : '',
            "pago_realizado_moneda" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['p_moneda'] : '',
            "pago_realizado_destino" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['destino'] : '',
            "pago_realizado_concepto" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['concepto'] : '',
            //forma_pago
            "pago_realizado_forma_pago_vinculada" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['forma_pago_vinculada'] : '',
            "pago_realizado_forma_pago_cfdi" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['forma_pago_cfdi'] : '',
            "pago_realizado_metodo_pago_cfdi" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['metodo_pago_cfdi'] : '',
            "pago_realizado_forma_metodo_pago_cfdi" => $pago_rr_forma_metodo_pago_cfdi,
            //proveedor
            "pago_realizado_proveedor_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['proveedor_token'] : '',
            "pago_realizado_proveedor_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['proveedor_name'] : '',
            //cliente
            "pago_realizado_cliente_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['cliente_token'] : '',
            "pago_realizado_cliente_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['cliente_name'] : '',
            //empleado
            "pago_realizado_empleado_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['empleado_token'] : '',
            "pago_realizado_empleado_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['empleado_name'] : '',
            //acreedor
            "pago_realizado_acreedor_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['acreedor_token'] : '',
            "pago_realizado_acreedor_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['acreedor_name'] : '',
            //personal_pago
            "pago_realizado_personal_pago_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_pago_token'] : '',
            "pago_realizado_personal_pago_folio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_pago_folio'] : '',
            "pago_realizado_personal_pago_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_pago_name'] : '',
            "pago_realizado_pago_autorizado" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['pago_autorizado'] : '',
            "pago_realizado_fecha_pago_auth" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['fecha_pago_auth'] : '',
            //personal_autoriza
            "pago_realizado_personal_autoriza_token" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_autoriza_token'] : '',
            "pago_realizado_personal_autoriza_folio" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_autoriza_folio'] : '',
            "pago_realizado_personal_autoriza_name" => count($pagos_realizados_orden) > 0 ? $pagos_realizados_orden[0]['personal_autoriza_name'] : '',
          );
          $orden_pago_nomina[] = $row_ordenPay;
				}

				$pagos_realizados_nomina = $JwtAuth->pagosDoneBYOrdenDesglose($nomina_efectivo_ord_pago_token,$usuario->empresa_token,$usuario->user_token);

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'seguimiento_orden_pago' => $orden_pago_nomina,
          'pagos_realizados' => $pagos_realizados_nomina,
        );
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

  public function nominaEspecieSeguimientoOrdenPago(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
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
        $nominas = array();
        
        $queryRepNomina = NominasModelo::join("vhum_centros_de_trabajo_catalogo AS c_trab", "vhum_nominas_main.nomina_registro_patronal", "c_trab.id")
        ->join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "emp.id")
        ->where([
          'vhum_nominas_main.nomina_status' => TRUE,
          'emp.empresa_token' => $usuario->empresa_token,
        ])
        ->orderBy('vhum_nominas_main.id', 'DESC')->get();

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'nominas' => $nominas
        );
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

  public function nominaDesgloseDispersion(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_nominas_periodos' => 'required|string',
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
				$token_nominas_periodos = $parametrosArray['token_nominas_periodos'];
        $detalleNominaLista = array();
        
        $queryNominaDesglose = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'vhum_nominas_main.nomina_status' => TRUE,
          'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();
        
        foreach ($queryNominaDesglose as $vNom) {
          $queryNominaEfectOrdPago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
          
          $detalleNominaQuery = DB::table("vhum_nominas_recibos AS recibos")
          ->join("vhum_empleados_catalogo AS nomi_trab", "recibos.trabajador", "=", "nomi_trab.id")
          ->join("sos_personas AS people", "nomi_trab.empleado_name", "=", "people.id")
          ->join("teci_bancos AS bank", "nomi_trab.trabcuentabanc_banco", "=", "bank.id")
          ->join("vhum_nominas_main AS nomi", "recibos.nomina_main", "=", "nomi.id")
          ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "recibos.nomina_registro_patronal", "c_trab.id")
          ->where('nomi.token_nominas_periodos',$vNom->token_nominas_periodos)
          ->get();
          $contador = 1;
          foreach ($detalleNominaQuery as $vNomRec) {
            $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vNomRec->folio_pers).(!is_null($vNomRec->post_folio_pers) ? '-'.$vNomRec->post_folio_pers : '');
            $trabajador_name_paterno = ucwords($JwtAuth->desencriptar($vNomRec->paterno));
            $trabajador_name_materno = ucwords($JwtAuth->desencriptar($vNomRec->materno));
            $trabajador_name_nombre = ucwords($JwtAuth->desencriptar($vNomRec->nombre));
            $trabajador_nombre = "$trabajador_name_paterno $trabajador_name_materno $trabajador_name_nombre";
            $numero_de_seguridad_social = !is_null($vNomRec->numero_de_seguridad_social) && $vNomRec->numero_de_seguridad_social != '' ? $vNomRec->numero_de_seguridad_social : '';
            $rfc = !is_null($vNomRec->rfc) && $vNomRec->rfc != '' ? $JwtAuth->desencriptar($vNomRec->rfc) : '';
            $curp = !is_null($vNomRec->curp) && $vNomRec->curp != '' ? $JwtAuth->desencriptar($vNomRec->curp) : '';
            $fecha_alta_en_empresa = !is_null($vNomRec->fecha_alta_en_empresa) && $vNomRec->fecha_alta_en_empresa != '' ? gmdate('Y-m-d H:i:s', $vNomRec->fecha_alta_en_empresa) : '';
            $salario_tipo = !is_null($vNomRec->salario_tipo) && $vNomRec->salario_tipo != '' ? $vNomRec->salario_tipo : '';

            $cuenta_descifrada = '';
            $cuenta_descifrada_last_digitos = '';
            if (!is_null($vNomRec->trabcuentabanc_cuenta) && $vNomRec->trabcuentabanc_cuenta != '') {
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($vNomRec->trabcuentabanc_cuenta);
              $cuenta_descifrada_substr = substr($JwtAuth->decryptBankAccount($vNomRec->trabcuentabanc_cuenta), -4);
              $cuenta_descifrada_last_digitos = "**** **** **** $cuenta_descifrada_substr";
            }
            
            $clabe_descifrada = '';
            $clabe_descifrada_last_digitos = '';
            if (!is_null($vNomRec->trabcuentabanc_clabe) && $vNomRec->trabcuentabanc_clabe != '') {
              $clabe_descifrada = $JwtAuth->decryptBankAccount($vNomRec->trabcuentabanc_clabe);
              $clabe_descifrada_substr = substr($JwtAuth->decryptBankAccount($vNomRec->trabcuentabanc_clabe), -4);
              $clabe_descifrada_last_digitos = "**** **** **** $clabe_descifrada_substr";
            }

            $nomina_moneda_name = $vNomRec->nomina_moneda;
            $nomina_moneda_decimales = $JwtAuth->getMonedaAPI($vNomRec->nomina_moneda);

            $detNomRow = array(
              "nomina_clave" => $contador,
              "token_nomina_recibo" => $vNomRec->token_nomina_recibo,
              //nomina_registro_patronal
              "centrotrab_uuid" => $vNomRec->centrotrab_uuid,
              "centrotrab_registro_patronal_imss" => $vNomRec->centrotrab_clave_registro_patronal_imss,
              
              //nomina_empleado_nombre
              "nomina_empleado_token" => $vNomRec->empleado_token,
              "nomina_empleado_folio" => $folio_empleado,
              "nomina_empleado_nombre" => $trabajador_nombre,
              //nomina_periodicidad
              "nomina_periodicidad" => $vNomRec->nomina_periodicidad,
              //nomina_periodo_inicio
              "nomina_periodo_pago" => date('d/m/Y',$vNomRec->nomina_periodo_fecha_inicio)." - ".date('d/m/Y',$vNomRec->nomina_periodo_fecha_fin),
              "nomina_periodo_fecha_inicio" => date('Y-m-d',$vNomRec->nomina_periodo_fecha_inicio),
              "nomina_periodo_fecha_fin" => date('Y-m-d',$vNomRec->nomina_periodo_fecha_fin),
              //nomina_moneda
              "nomina_moneda" => $vNomRec->nomina_moneda,
              //nomina_empleado_cbankBanco
              "nomina_empleado_cbankBancoToken" => $vNomRec->token_bancos,
              "nomina_empleado_cbankBancoNombre" => $vNomRec->clave." ".$vNomRec->nombre_comercial,
              "nomina_empleado_cbankCuenta" => $cuenta_descifrada,
              "nomina_empleado_cbankCuentaMin" => $cuenta_descifrada_last_digitos,
              "cuenta_view" => false,
              "nomina_empleado_cbankCuentaClabeInter" => $clabe_descifrada,
              "nomina_empleado_cbankCuentaClabeInterMin" => $clabe_descifrada_last_digitos,
              "clabe_inter_view" => false,
              //nomina_empleado_nss
              "nomina_empleado_nss" => $numero_de_seguridad_social,
              //nomina_empleado_rfc
              "nomina_empleado_rfc" => $rfc,
              //nomina_empleado_curp
              "nomina_empleado_curp" => $curp,
              //nomina_empleado_fecha_alta
              "nomina_empleado_fecha_alta" => $fecha_alta_en_empresa,
              //nomina_empleado_departamento
              "nomina_empleado_departamento" => !is_null($vNomRec->departamento) ? $JwtAuth->desencriptar($vNomRec->departamento) : '',
              //nomina_empleado_puesto
              "nomina_empleado_puesto" => !is_null($vNomRec->puesto) ? $JwtAuth->desencriptar($vNomRec->puesto) : '',
              //nomina_empleado_tipo_salario
              "nomina_empleado_tipo_salario" => $salario_tipo,
              //nomina_salario_diario
              "nomina_salario_diario" => number_format($vNomRec->salario_diario,$nomina_moneda_decimales,'.',''),
              "nomina_salario_diario_format" => "$".number_format($vNomRec->salario_diario,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_salario_integrado
              "nomina_salario_integrado" => number_format($vNomRec->salario_integrado,$nomina_moneda_decimales,'.',''),
              "nomina_salario_integrado_format" => "$".number_format($vNomRec->salario_integrado,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_dias_trabajados
              "nomina_dias_trabajados" => $vNomRec->dias_trabajados,
              //nomina_faltas
              "nomina_faltas" => intval($vNomRec->faltas),
              //nomina_sueldo
              "nomina_sueldo" => number_format($vNomRec->sueldo,$nomina_moneda_decimales,'.',''),
              "nomina_sueldo_format" => "$".number_format($vNomRec->sueldo,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_horas_extras_dobles
              "nomina_horas_extras_dobles" => number_format($vNomRec->horas_extras_dobles,$nomina_moneda_decimales,'.',''),
              "nomina_horas_extras_dobles_format" => "$".number_format($vNomRec->horas_extras_dobles,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_aguinaldo
              "nomina_aguinaldo" => number_format($vNomRec->aguinaldo,$nomina_moneda_decimales,'.',''),
              "nomina_aguinaldo_format" => "$".number_format($vNomRec->aguinaldo,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_horas_extras_triples
              "nomina_horas_extras_triples" => number_format($vNomRec->horas_extras_triples,$nomina_moneda_decimales,'.',''),
              "nomina_horas_extras_triples_format" => "$".number_format($vNomRec->horas_extras_triples,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_vacaciones
              "nomina_vacaciones" => number_format($vNomRec->vacaciones,$nomina_moneda_decimales,'.',''),
              "nomina_vacaciones_format" => "$".number_format($vNomRec->vacaciones,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_prima_vacacional
              "nomina_prima_vacacional" => number_format($vNomRec->prima_vacacional,$nomina_moneda_decimales,'.',''),
              "nomina_prima_vacacional_format" => "$".number_format($vNomRec->prima_vacacional,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_reparto_de_utilidades
              "nomina_reparto_de_utilidades" => number_format($vNomRec->reparto_de_utilidades,$nomina_moneda_decimales,'.',''),
              "nomina_reparto_de_utilidades_format" => "$".number_format($vNomRec->reparto_de_utilidades,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_despensa
              "nomina_despensa" => number_format($vNomRec->despensa,$nomina_moneda_decimales,'.',''),
              "nomina_despensa_format" => "$".number_format($vNomRec->despensa,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_premios_de_asistencia
              "nomina_premios_de_asistencia" => number_format($vNomRec->premios_de_asistencia,$nomina_moneda_decimales,'.',''),
              "nomina_premios_de_asistencia_format" => "$".number_format($vNomRec->premios_de_asistencia,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_premios_de_puntualidad
              "nomina_premios_de_puntualidad" => number_format($vNomRec->premios_de_puntualidad,$nomina_moneda_decimales,'.',''),
              "nomina_premios_de_puntualidad_format" => "$".number_format($vNomRec->premios_de_puntualidad,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_prima_dominical
              "nomina_prima_dominical" => number_format($vNomRec->prima_dominical,$nomina_moneda_decimales,'.',''),
              "nomina_prima_dominical_format" => "$".number_format($vNomRec->prima_dominical,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_bno_extra_x_comision_otro_edo
              "nomina_bno_extra_x_comision_otro_edo" => number_format($vNomRec->bno_extra_x_comision_otro_edo,$nomina_moneda_decimales,'.',''),
              "nomina_bno_extra_x_comision_otro_edo_format" => "$".number_format($vNomRec->bno_extra_x_comision_otro_edo,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_indemnizacion
              "nomina_indemnizacion" => number_format($vNomRec->indemnizacion,$nomina_moneda_decimales,'.',''),
              "nomina_indemnizacion_format" => "$".number_format($vNomRec->indemnizacion,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_prima_de_antiguedad
              "nomina_prima_de_antiguedad" => number_format($vNomRec->prima_de_antiguedad,$nomina_moneda_decimales,'.',''),
              "nomina_prima_de_antiguedad_format" => "$".number_format($vNomRec->prima_de_antiguedad,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_otras_percepciones
              "nomina_otras_percepciones" => number_format($vNomRec->otras_percepciones,$nomina_moneda_decimales,'.',''),
              "nomina_otras_percepciones_format" => "$".number_format($vNomRec->otras_percepciones,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_otros_pagos
              "nomina_otros_pagos" => number_format($vNomRec->otros_pagos,$nomina_moneda_decimales,'.',''),
              "nomina_otros_pagos_format" => "$".number_format($vNomRec->otros_pagos,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_percepciones
              "nomina_total_percepciones" => number_format($vNomRec->total_percepciones,$nomina_moneda_decimales,'.',''),
              "nomina_total_percepciones_format" => "$".number_format($vNomRec->total_percepciones,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_isr_ajustado_por_subsidio
              "nomina_isr_ajustado_por_subsidio" => number_format($vNomRec->isr_ajustado_por_subsidio,$nomina_moneda_decimales,'.',''),
              "nomina_isr_ajustado_por_subsidio_format" => "$".number_format($vNomRec->isr_ajustado_por_subsidio,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_isr
              "nomina_total_isr" => number_format($vNomRec->total_isr,$nomina_moneda_decimales,'.',''),
              "nomina_total_isr_format" => "$".number_format($vNomRec->total_isr,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_imss
              "nomina_total_imss" => number_format($vNomRec->total_imss,$nomina_moneda_decimales,'.',''),
              "nomina_total_imss_format" => "$".number_format($vNomRec->total_imss,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_credito_fonacot
              "nomina_credito_fonacot" => number_format($vNomRec->credito_fonacot,$nomina_moneda_decimales,'.',''),
              "nomina_credito_fonacot_format" => "$".number_format($vNomRec->credito_fonacot,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_credito_infonavit
              "nomina_credito_infonavit" => number_format($vNomRec->credito_infonavit,$nomina_moneda_decimales,'.',''),
              "nomina_credito_infonavit_format" => "$".number_format($vNomRec->credito_infonavit,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_subsidio_empleo
              "nomina_subsidio_empleo" => number_format($vNomRec->subsidio_empleo,$nomina_moneda_decimales,'.',''),
              "nomina_subsidio_empleo_format" => "$".number_format($vNomRec->subsidio_empleo,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_subsidio_empleo_aplicado
              //"nomina_subsidio_empleo_aplicado" => number_format($vNomRec->subsidio_para_el_empleo_aplicado,$nomina_moneda_decimales,'.',''),
              //"nomina_subsidio_empleo_aplicado_format" => "$".number_format($vNomRec->subsidio_para_el_empleo_aplicado,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_otras_deducciones
              "nomina_otras_deducciones" => number_format($vNomRec->otras_deducciones,$nomina_moneda_decimales,'.',''),
              "nomina_otras_deducciones_format" => "$".number_format($vNomRec->otras_deducciones,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_deducciones
              "nomina_total_deducciones" => number_format($vNomRec->total_deducciones,$nomina_moneda_decimales,'.',''),
              "nomina_total_deducciones_format" => "$".number_format($vNomRec->total_deducciones,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_efectivo
              "nomina_total_efectivo" => number_format($vNomRec->total_efectivo,$nomina_moneda_decimales,'.',''),
              "nomina_total_efectivo_format" => "$".number_format($vNomRec->total_efectivo,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_total_en_especie
              "nomina_total_en_especie" => number_format($vNomRec->total_en_especie,$nomina_moneda_decimales,'.',''),
              "nomina_total_en_especie_format" => "$".number_format($vNomRec->total_en_especie,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_neto_pagado
              "nomina_neto_pagado" => number_format($vNomRec->neto_pagado,$nomina_moneda_decimales,'.',''),
              "nomina_neto_pagado_format" => "$".number_format($vNomRec->neto_pagado,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_horas_por_dia
              "nomina_horas_por_dia" => intval($vNomRec->horas_por_dia),
              //nomina_salario_por_hora
              "nomina_salario_por_hora" => number_format($vNomRec->salario_por_hora,$nomina_moneda_decimales,'.',''),
              "nomina_salario_por_hora_format" => "$".number_format($vNomRec->salario_por_hora,$nomina_moneda_decimales,'.',',')." $nomina_moneda_name",
              //nomina_dias_jornada
              "nomina_dias_jornada" => !is_null($vNomRec->nomina_jornada) && $vNomRec->nomina_jornada != '' ? $vNomRec->nomina_jornada : '',
              "nomina_habilita_carga_docs" => $queryNominaEfectOrdPago ? true : false,
              "nomina_factura_doc_xml" => !is_null($vNomRec->xml_url) ? $JwtAuth->desencriptar($vNomRec->xml_url) : null,
              "nomina_factura_doc_pdf" => !is_null($vNomRec->pdf_url) ? $JwtAuth->desencriptar($vNomRec->pdf_url) : null,
              "nomina_factura_xml" => null,
              "nomina_factura_pdf" => null,
              "nomina_valida_xml" => '',
              "nomina_cfdi_comprobante" => [],
              "nomina_cfdi_emisor" => [],
              "nomina_cfdi_receptor" => [],
              "nomina_cfdi_conceptos" => [],
              "nomina_cfdi_complemento" => [],
              "nomina_cfdi_nomina" => []
            );
            $contador++;
            $detalleNominaLista[] = $detNomRow;
          }
				}

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'desglose' => $detalleNominaLista
        );
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

  public function nominaCargaCFDIS(Request $request){
    $JwtAuth = new \JwtAuth();
		$user_token = $request->input('user_token');
		$token_nominas_periodos = $request->input('token_nominas_periodos');
		$nomina_reportada = $request->input('nomina_reportada');

    $validate = \Validator::make($request->all(), [
      'user_token' => 'required',
      'token_nominas_periodos' => 'required|string',
      'nomina_reportada' => 'required|array',
    ]);
    if ($validate->fails()) {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametros de busqueda recibidos son incorrectos',
        'errors' => $validate->errors()
      );
    } else {
      $usuario = $JwtAuth->checkToken($user_token, true);
      
      $queryNominaDesglose = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'vhum_nominas_main.nomina_status' => TRUE,
        'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
        'emp.empresa_token' => $usuario->empresa_token,
        'users.usuario_token' => $usuario->user_token,
      ])->get();

      foreach ($queryNominaDesglose as $vNom) {
        $nomina_main = DB::table('vhum_nominas_main')->where("token_nominas_periodos", $vNom->token_nominas_periodos)->value("id");
        $folio_interior = 'NOM-EF-'.$JwtAuth->generarFolio($vNom->nomina_folio_interior).(!is_null($vNom->nomina_subfolio) ? '-'.$vNom->nomina_subfolio : '');
        $nomina_moneda_name = $vNom->nomina_moneda;
        $nomina_moneda_decimales = $JwtAuth->getMonedaAPI($vNom->nomina_moneda);
        $count_nomina_reportada = 0;
        
        foreach ($nomina_reportada as $r_nomina => $rNomi) {
          $nomina_recibo = DB::table('vhum_nominas_recibos')->where("token_nomina_recibo", $rNomi["token_nomina_recibo"])->value("id");
          $folio_nomina_recibo = $JwtAuth->generarFolio(DB::table('vhum_nominas_recibos')->where("token_nomina_recibo", $rNomi["token_nomina_recibo"])->value("nomina_recibo_folio"));
          $empleado_referenciado = DB::table('vhum_empleados_catalogo')->where("empleado_token", $rNomi["nomina_empleado_token"])->value("id");
          //$nomina_factura_xml = $rNomi["nomina_factura_xml"];
          //$nomina_factura_pdf = $rNomi["nomina_factura_pdf"];

          $archivo_xml = $request->file("nomina_reportada.$r_nomina.nomina_factura_xml");
          $archivo_pdf = $request->file("nomina_reportada.$r_nomina.nomina_factura_pdf");

          $nomina_cfdi_comprobante = $rNomi["nomina_cfdi_comprobante"];
          $nomina_cfdi_emisor = $rNomi["nomina_cfdi_emisor"];
          $nomina_cfdi_receptor = $rNomi["nomina_cfdi_receptor"];
          $nomina_cfdi_conceptos = $rNomi["nomina_cfdi_conceptos"];
          $nomina_cfdi_complemento = $rNomi["nomina_cfdi_complemento"];
          $nomina_cfdi_nomina = $rNomi["nomina_cfdi_nomina"];
          
          $cfdi_comprobante_version = '';
          $cfdi_comprobante_serie = '';
          $cfdi_comprobante_folio = '';
          $cfdi_comprobante_fecha = '';
          $cfdi_comprobante_forma_de_pago = '';
          $cfdi_comprobante_metodo_de_pago = '';
          $cfdi_comprobante_subtotal = '';
          $cfdi_comprobante_descuento = '';
          $cfdi_comprobante_moneda = '';
          $cfdi_comprobante_tipo_de_cambio = '';
          $cfdi_comprobante_total = '';
          $cfdi_comprobante_confirmacion = '';
          $cfdi_comprobante_tipo_de_comprobante = '';
          $cfdi_comprobante_lugar_de_expedicion = '';
          $cfdi_comprobante_no_de_certificado = '';
          $cfdi_comprobante_sello = '';
          $cfdi_comprobante_certificado = '';

          $cfdi_complementoUUID = '';
          $cfdi_complementoFechaTimbrado = '';
          $cfdi_complementoRfcProvCertif = '';
          $cfdi_complementoVersion = '';
          $cfdi_complementoNoCertificadoSAT = '';
          $cfdi_complementoSelloCFD = '';
          $cfdi_complementoSelloSAT = '';

          $data_comprobante = json_decode($nomina_cfdi_comprobante, true);
          if (json_last_error() === JSON_ERROR_NONE && is_array($data_comprobante)) {

            foreach ($data_comprobante as $vComp) {
              $cfdi_comprobante_version = $vComp["Version"];
              $cfdi_comprobante_serie = $vComp["Serie"];
              $cfdi_comprobante_folio = $vComp["Folio"];
              $cfdi_comprobante_fecha = $vComp["Fecha"];
              $cfdi_comprobante_forma_de_pago = $vComp["FormaDePago"];
              $cfdi_comprobante_subtotal = $vComp["Subtotal"];
              $cfdi_comprobante_descuento = $vComp["Descuento"];
              $cfdi_comprobante_moneda = $vComp["Moneda"];
              $cfdi_comprobante_tipo_de_cambio = $vComp["TipoDeCambio"];
              $cfdi_comprobante_total = $vComp["Total"];
              $cfdi_comprobante_confirmacion = $vComp["Confirmacion"];
              $cfdi_comprobante_tipo_de_comprobante = $vComp["TipoDeComprobante"];
              $cfdi_comprobante_metodo_de_pago = $vComp["MetodoDePago"];
              $cfdi_comprobante_lugar_de_expedicion = $vComp["LugarDeExpedición"];
              $cfdi_comprobante_no_de_certificado = $vComp["NoDeCertificado"];
              $cfdi_comprobante_sello = $vComp["Sello"];
              $cfdi_comprobante_certificado = $vComp["Certificado"];
            }

            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.CFDI2']);
            $cfdi_emisor_rfc = '';
            $cfdi_emisor_nombre = '';
            $cfdi_emisor_regimen_fiscal = '';
            $data_emisor = json_decode($nomina_cfdi_emisor, true);
            foreach ($data_emisor as $CFDIe) {
              $cfdi_emisor_rfc = $CFDIe["EmisorRfc"];
              $cfdi_emisor_nombre = $CFDIe["EmisorNombre"];
              $cfdi_emisor_regimen_fiscal = $CFDIe["EmisorRegimenFiscal"];
            }

            $cfdi_receptor_rfc = '';
            $cfdi_receptor_uso_del_cfdi = '';
            $data_receptor = json_decode($nomina_cfdi_receptor, true);
            foreach ($data_receptor as $CFDIReceptor) {
              $cfdi_receptor_rfc = $CFDIReceptor["ReceptorRfc"];
              $cfdi_receptor_uso_del_cfdi = $CFDIReceptor["ReceptorUsoCFDI"];
            }

            $data_complemento = json_decode($nomina_cfdi_complemento, true);
            foreach ($data_complemento as $vComplemento) {
              $cfdi_complementoUUID = $vComplemento["UUID"];
              $cfdi_complementoFechaTimbrado = $vComplemento["FechaTimbrado"];
              $cfdi_complementoRfcProvCertif = $vComplemento["RfcProvCertif"];
              $cfdi_complementoNoCertificadoSAT = $vComplemento["NoCertificadoSAT"];
              $cfdi_complementoSelloCFD = $vComplemento["SelloCFD"];
              $cfdi_complementoSelloSAT = $vComplemento["SelloSAT"];
            }

            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$cfdi_comprobante_version]);
            $comprobante_fiscal_reg = "";
            if ($cfdi_comprobante_version != '') {
              $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$nomina_main.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
              $insertCFDINomina = DB::table('cfdi_comprobantes_fiscales')
              ->insert(array(
                "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
                "origen_proceso" => "nomina",
                "cfdi_comprobante_version" => $cfdi_comprobante_version,	
                "cfdi_comprobante_serie" => $cfdi_comprobante_serie,	
                "cfdi_comprobante_folio" => $cfdi_comprobante_folio,	
                "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,	
                "cfdi_comprobante_sello" => $cfdi_comprobante_sello,	
                "cfdi_comprobante_no_de_certificado" => $cfdi_comprobante_no_de_certificado,	
                "cfdi_comprobante_certificado" => $cfdi_comprobante_certificado,	
                "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,	
                "cfdi_comprobante_descuento" => $cfdi_comprobante_descuento,	
                "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,	
                "cfdi_comprobante_total" => $cfdi_comprobante_total,	
                "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,	
                "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,	
                "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,	
                "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,	
                "cfdi_comprobante_lugar_de_expedicion" => $cfdi_comprobante_lugar_de_expedicion,
                
                "cfdi_emisor_rfc" => $cfdi_emisor_rfc,	
                "cfdi_emisor_nombre" => $cfdi_emisor_nombre,	
                "cfdi_emisor_regimen_fiscal" => $cfdi_emisor_regimen_fiscal,
                
                "cfdi_receptor_rfc" => $cfdi_receptor_rfc,	
                "cfdi_receptor_uso_del_cfdi" => $cfdi_receptor_uso_del_cfdi,	
                //"cfdi_receptor_regimen_fiscal" => $select_reembolso_main,	
                //"cfdi_receptor_domicilio_fiscal" => $select_reembolso_main,	
                
                "cfdi_complementoSelloSAT" => $cfdi_complementoSelloSAT,	
                "cfdi_complementoNoCertificadoSAT" => $cfdi_complementoNoCertificadoSAT,	
                "cfdi_complementoSelloCFD" => $cfdi_complementoSelloCFD,	
                "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,	
                "cfdi_complementoUUID" => $cfdi_complementoUUID,	
                "cfdi_complementoVersion" => $cfdi_complementoVersion,	
                "cfdi_complementoRfcProvCertif" => $cfdi_complementoRfcProvCertif,
              ));
              
              $comprobante_fiscal_reg = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");
              $insertCFDIVincNomina = DB::table('cfdi_vinculacion_nomina')//cfdi__estructura
              ->insert(array(
                "comprobante_fiscal" => $comprobante_fiscal_reg,
                "nomina_main" => $nomina_main,	
                "nomina_recibo" => $nomina_recibo,	
                "empleado_referenciado" => $empleado_referenciado,
              ));

              $data_conceptos = json_decode($nomina_cfdi_conceptos, true);
              for ($lrdc = 0; $lrdc < count($data_conceptos); $lrdc++) {
                $uuid_cfdi_detalle = Str::uuid()->toString();
                $insertConceptCFDINominas = DB::table('cfdi_comprobantes_conceptos')
                ->insert(array(
                  "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                  "comprobante_fiscal" => $comprobante_fiscal_reg,
                  //"nomina_main" => $nomina_main,
                  //"nomina_recibo" => $nomina_recibo,
                  //"empleado_referenciado" => $empleado_referenciado,
                  "ClaveProdServ" => $data_conceptos[$lrdc]['ClaveProdServ'],
                  "Cantidad" => $data_conceptos[$lrdc]['Cantidad'],
                  "ClaveUnidad" => $data_conceptos[$lrdc]['ClaveUnidad'],
                  "Descripcion" => $data_conceptos[$lrdc]['Descripcion'],
                  "ValorUnitario" => $data_conceptos[$lrdc]['ValorUnitario'],
                  "Importe" => $data_conceptos[$lrdc]['Importe'],
                  "Descuento" => $data_conceptos[$lrdc]['Descuento'],
                  "ObjetoImp" => $data_conceptos[$lrdc]['ObjetoImp']
                ));
              }
            }
            //return response()->json(['status' => 'error','code' => 200,'message' => "FechaFinalPago"]);
            $data_cfdi_nomina = json_decode($nomina_cfdi_nomina, true);
            foreach ($data_cfdi_nomina as $CFDINomi) {
              //return response()->json(['status' => 'error','code' => 200,'message' => $CFDINomi["FechaFinalPago"]]);
              $cfdi_nominaEmisor = $CFDINomi["Emisor"]; 
              $cfdi_nominaReceptor = $CFDINomi["Receptor"]; 
              $cfdi_nominaPercepciones = $CFDINomi["Percepciones"];
              $cfdi_nominaDeducciones = $CFDINomi["Deducciones"]; 
              $cfdi_nominaOtrosPagos = $CFDINomi["OtrosPagos"];
              
              $uuid_nomina_nomina = Str::uuid()->toString();
              $insertNominaCFDINominas = DB::table('cfdi_nomina_nomina')
              ->insert(array(
                "uuid_nomina_nomina" => $uuid_nomina_nomina,
                "nomina_main" => $nomina_main,
                "nomina_recibo" => $nomina_recibo,
                "empleado_referenciado" => $empleado_referenciado,
                //"nomina_estructura" => $nomina_estructura,

                "FechaFinalPago" => $CFDINomi["FechaFinalPago"],
                "FechaInicialPago" => $CFDINomi["FechaInicialPago"],
                "FechaPago" => $CFDINomi["FechaPago"],
                "NumDiasPagados" => $CFDINomi["NumDiasPagados"],
                "TipoNomina" => $CFDINomi["TipoNomina"],
                "TotalDeducciones" => $CFDINomi["TotalDeducciones"],
                "TotalOtrosPagos" => $CFDINomi["TotalOtrosPagos"],
                "TotalPercepciones" => $CFDINomi["TotalPercepciones"],
                "Version" => $CFDINomi["Version"]
              ));

              foreach ($cfdi_nominaEmisor as $EmisorCFDINomi) {
                $uuid_nomina_emisor = Str::uuid()->toString();
                DB::table('cfdi_nomina_nomina_emisor')
                ->insert(array(
                  "uuid_nomina_emisor" => $uuid_nomina_emisor,
                  "nomina_main" => $nomina_main,
                  "nomina_recibo" => $nomina_recibo,
                  "empleado_referenciado" => $empleado_referenciado,
                  "nomina_estructura" => $comprobante_fiscal_reg,
                  "nomina_nomina" => $uuid_nomina_nomina,
  
                  "registro_patronal" => $EmisorCFDINomi["RegistroPatronal"]
                ));
              }

              foreach ($cfdi_nominaReceptor as $ReceptorCFDINomi) {
                $uuid_nomina_receptor = Str::uuid()->toString();
                DB::table('cfdi_nomina_nomina_receptor')
                ->insert(array(
                  "uuid_nomina_receptor" => $uuid_nomina_receptor,
                  "nomina_main" => $nomina_main,
                  "nomina_recibo" => $nomina_recibo,
                  "empleado_referenciado" => $empleado_referenciado,
                  "nomina_estructura" => $comprobante_fiscal_reg,
                  "nomina_nomina" => $uuid_nomina_nomina,

                  "Antigüedad" => $ReceptorCFDINomi["Antigüedad"],
                  "Banco" => $ReceptorCFDINomi["Banco"],
                  "ClaveEntFed" => $ReceptorCFDINomi["ClaveEntFed"],
                  "CuentaBancaria" => $ReceptorCFDINomi["CuentaBancaria"],
                  "Curp" => $ReceptorCFDINomi["Curp"],
                  "Departamento" => $ReceptorCFDINomi["Departamento"],
                  "FechaInicioRelLaboral" => $ReceptorCFDINomi["FechaInicioRelLaboral"],
                  "NumEmpleado" => $ReceptorCFDINomi["NumEmpleado"],
                  "NumSeguridadSocial" => $ReceptorCFDINomi["NumSeguridadSocial"],
                  "PeriodicidadPago" => $ReceptorCFDINomi["PeriodicidadPago"],
                  "Puesto" => $ReceptorCFDINomi["Puesto"],
                  "RiesgoPuesto" => $ReceptorCFDINomi["RiesgoPuesto"],
                  "SalarioBaseCotApor" => $ReceptorCFDINomi["SalarioBaseCotApor"],
                  "SalarioDiarioIntegrado" => $ReceptorCFDINomi["SalarioDiarioIntegrado"],
                  "Sindicalizado" => $ReceptorCFDINomi["Sindicalizado"],
                  "TipoContrato" => $ReceptorCFDINomi["TipoContrato"],
                  "TipoJornada" => $ReceptorCFDINomi["TipoJornada"],
                  "TipoRegimen" => $ReceptorCFDINomi["TipoRegimen"],
                ));
              }

              foreach ($cfdi_nominaPercepciones as $PercepcionesCFDINomi) {
                $nominaPercepcionesPercepcion = $PercepcionesCFDINomi["Percepcion"];
                $uuid_nomina_percepciones = Str::uuid()->toString();
                DB::table('cfdi_nomina_nomina_percepciones')
                ->insert(array(
                  "uuid_nomina_percepciones" => $uuid_nomina_percepciones,
                  "nomina_main" => $nomina_main,
                  "nomina_recibo" => $nomina_recibo,
                  "empleado_referenciado" => $empleado_referenciado,
                  "nomina_estructura" => $comprobante_fiscal_reg,
                  "nomina_nomina" => $uuid_nomina_nomina,

                  "TotalExento" => $PercepcionesCFDINomi["TotalExento"],
                  "TotalGravado" => $PercepcionesCFDINomi["TotalGravado"],
                  "TotalSueldos" => $PercepcionesCFDINomi["TotalSueldos"],
                ));
                
                foreach ($nominaPercepcionesPercepcion as $PercepcionNomi) {
                  $uuid_nomina_percepcion = Str::uuid()->toString();
                  DB::table('cfdi_nomina_nomina_percepciones_percepcion')
                  ->insert(array(
                    "uuid_nomina_percepcion" => $uuid_nomina_percepcion,
                    "nomina_main" => $nomina_main,
                    "nomina_recibo" => $nomina_recibo,
                    "empleado_referenciado" => $empleado_referenciado,
                    "nomina_estructura" => $comprobante_fiscal_reg,
                    "nomina_nomina" => $uuid_nomina_nomina,
                    "nomina_percepciones" => $uuid_nomina_percepciones,
  
                    "Clave" => $PercepcionNomi["Clave"],
                    "Concepto" => $PercepcionNomi["Concepto"],
                    "ImporteExento" => $PercepcionNomi["ImporteExento"],
                    "ImporteGravado" => $PercepcionNomi["ImporteGravado"],
                    "TipoPercepcion" => $PercepcionNomi["TipoPercepcion"],
                  ));
                }
              }

              foreach ($cfdi_nominaDeducciones as $DeduccionesCFDINomi) {
                $nominaDeduccionesDeduccion = $DeduccionesCFDINomi["Deduccion"];
                $uuid_nomina_deducciones = Str::uuid()->toString();
                DB::table('cfdi_nomina_nomina_deducciones')
                ->insert(array(
                  "uuid_nomina_deducciones" => $uuid_nomina_deducciones,
                  "nomina_main" => $nomina_main,
                  "nomina_recibo" => $nomina_recibo,
                  "empleado_referenciado" => $empleado_referenciado,
                  "nomina_estructura" => $comprobante_fiscal_reg,
                  "nomina_nomina" => $uuid_nomina_nomina,

                  "TotalImpuestosRetenidos" => $DeduccionesCFDINomi["TotalImpuestosRetenidos"],
                  "TotalOtrasDeducciones" => $DeduccionesCFDINomi["TotalOtrasDeducciones"],
                ));
                
                foreach ($nominaDeduccionesDeduccion as $DeduccionNomi) {
                  $uuid_nomina_deduccion = Str::uuid()->toString();
                  DB::table('cfdi_nomina_nomina_deducciones_deduccion')
                  ->insert(array(
                    "uuid_nomina_deduccion" => $uuid_nomina_deduccion,
                    "nomina_main" => $nomina_main,
                    "nomina_recibo" => $nomina_recibo,
                    "empleado_referenciado" => $empleado_referenciado,
                    "nomina_estructura" => $comprobante_fiscal_reg,
                    "nomina_nomina" => $uuid_nomina_nomina,
                    "nomina_deducciones" => $uuid_nomina_deducciones,
                    "Clave" => $DeduccionNomi["Clave"],
                    "Concepto" => $DeduccionNomi["Concepto"],
                    "Importe" => $DeduccionNomi["Importe"],
                    "TipoDeduccion" => $DeduccionNomi["TipoDeduccion"],
                  ));
                }
              }

              foreach ($cfdi_nominaOtrosPagos as $OtrosPagosCFDINomi) {
                $nominaOtrosPagosSubsidioAlEmpleo = $OtrosPagosCFDINomi["SubsidioAlEmpleo"];
                $uuid_nomina_otros_pagos = Str::uuid()->toString();
                DB::table('cfdi_nomina_nomina_otros_pagos')
                ->insert(array(
                  "uuid_nomina_otros_pagos" => $uuid_nomina_otros_pagos,
                  "nomina_main" => $nomina_main,
                  "nomina_recibo" => $nomina_recibo,
                  "empleado_referenciado" => $empleado_referenciado,
                  "nomina_estructura" => $comprobante_fiscal_reg,
                  "nomina_nomina" => $uuid_nomina_nomina,

                  "Clave" => $OtrosPagosCFDINomi["Clave"],
                  "Concepto" => $OtrosPagosCFDINomi["Concepto"],
                  "Importe" => $OtrosPagosCFDINomi["Importe"],
                  "TipoOtroPago" => $OtrosPagosCFDINomi["TipoOtroPago"],
                ));
                
                foreach ($nominaOtrosPagosSubsidioAlEmpleo as $SubsidioAlEmpleoNomi) {
                  $uuid_nomina_subsidio_empleo = Str::uuid()->toString();
                  DB::table('cfdi_nomina_nomina_otros_pagos_subsidio_empleo')
                  ->insert(array(
                    "uuid_nomina_subsidio_empleo" => $uuid_nomina_subsidio_empleo,
                    "nomina_main" => $nomina_main,
                    "nomina_recibo" => $nomina_recibo,
                    "empleado_referenciado" => $empleado_referenciado,
                    "nomina_estructura" => $comprobante_fiscal_reg,
                    "nomina_nomina" => $uuid_nomina_nomina,
                    "nomina_otros_pagos" => $uuid_nomina_otros_pagos,

                    "SubsidioCausado" => $SubsidioAlEmpleoNomi["SubsidioCausado"],
                  ));
                }
              }
            }

            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.3 '.$cfdi_comprobante_version]);
          }
          $filepath = $vNom->root_tkn."/0004-vhm/nominas/$folio_interior/$folio_nomina_recibo";
          
          if ($archivo_xml) {
            $nombre_original = $archivo_xml->getClientOriginalName();
            $ext_doc = $archivo_xml->getClientOriginalExtension();

            $documento_crypt = $JwtAuth->encriptar($nombre_original);
            $token_documento = $JwtAuth->encriptarToken($nomina_recibo, $ext_doc, $nombre_original);

            $insertDocSoli = DB::table("sos_documentos")->insert([
              "token_documento" => $token_documento,
              "fecha_carga" => time(),
              "modulo" => "reembolsos",
              "folio_modulo" => "NOMINA-CFDI-XML",
              "tipo_documento" => "xml",
              "nombre_documento" => $documento_crypt,
              "extension_documento" => $ext_doc,
              "nomina_main" => $nomina_main,
              "nomina_recibo" => $nomina_recibo,
              "status_documento" => true,
            ]);

            if ($insertDocSoli) {
              DB::table('vhum_nominas_recibos')
              ->where("token_nomina_recibo", $rNomi["token_nomina_recibo"])
              ->limit(1)->update(array(
                "folio_fiscal" => $cfdi_complementoUUID,
                "serie_folio" => "$cfdi_comprobante_serie - $cfdi_comprobante_folio",
                "xml_url" => $documento_crypt,
                "fecha_emision" => $cfdi_comprobante_fecha,
                "fecha_timbrado" => $cfdi_complementoFechaTimbrado
              ));

              $archivo_xml->storeAs("public/root/$filepath", $nombre_original);
            }
          }

          if ($archivo_pdf) {
            $nombre_original = $archivo_pdf->getClientOriginalName();
            $ext_doc = $archivo_pdf->getClientOriginalExtension();

            $documento_crypt = $JwtAuth->encriptar($nombre_original);
            $token_documento = $JwtAuth->encriptarToken($nomina_recibo, $ext_doc, $nombre_original);

            $insertDocSoli = DB::table("sos_documentos")->insert([
              "token_documento" => $token_documento,
              "fecha_carga" => time(),
              "modulo" => "reembolsos",
              "folio_modulo" => "NOMINA-CFDI-PDF",
              "tipo_documento" => "pdf",
              "nombre_documento" => $documento_crypt,
              "extension_documento" => $ext_doc,
              "nomina_main" => $nomina_main,
              "nomina_recibo" => $nomina_recibo,
              "status_documento" => true,
            ]);

            if ($insertDocSoli) {
              $regFolder = DB::table('vhum_nominas_recibos')
              ->where("token_nomina_recibo", $rNomi["token_nomina_recibo"])
              ->limit(1)->update(array(
                "pdf_url" => $documento_crypt,
              ));
              $archivo_pdf->storeAs("public/root/$filepath", $nombre_original);
            }
          }
          ++$count_nomina_reportada;
          //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$token_nomina_recibo]);
        }

        if ($count_nomina_reportada == count($nomina_reportada)) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'CFDIs de la nómina han sido cargados correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error al cargar los CFDIs de la nómina, intente nuevamente o comuniquese a soporte'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'numero_de_nomina' => 'required|string',
        'fecha_contabilizacion' => 'required|string',
        'nomina_observacion' => 'required|string',
        'nomina_reportada' => 'required|array',
        'nomina_en_especie' => 'array',
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
        $numero_de_nomina = $parametrosArray['numero_de_nomina'];
        $fecha_contabilizacion = $parametrosArray['fecha_contabilizacion'];
        $nomina_observacion = $parametrosArray['nomina_observacion'];
        $nomina_reportada = $parametrosArray['nomina_reportada'];
        $nomina_en_especie = $parametrosArray['nomina_en_especie'];

        $OKNominaNumero = isset($numero_de_nomina) && !empty($numero_de_nomina) && preg_match($JwtAuth->filtroNumerico(),$numero_de_nomina);
        $OKNominaFCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
        $OKNominaObservacion = isset($nomina_observacion) && !empty($nomina_observacion) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_observacion);
        $OKNominaReportada = isset($nomina_reportada) && is_array($nomina_reportada) && count($nomina_reportada) > 0;
        $OKNominaEspecie = isset($nomina_en_especie) && is_array($nomina_en_especie) && count($nomina_en_especie) > 0;

        if ($OKNominaNumero  && $OKNominaFCont && $OKNominaObservacion && $OKNominaReportada) {
          $fechaSistema = time();
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

          foreach ($queryEmp as $vEmp) {
            $folioSistema = DB::select("SELECT nomina.nomina_folio_interior+1 AS folio,nomina_subfolio FROM vhum_nominas_main AS nomina JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
              JOIN teci_usuarios_catalogo AS users WHERE nomina.nomina_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? 
              ORDER BY nomina.nomina_folio_interior DESC LIMIT 1",[$usuario->empresa_token,$usuario->user_token]);
            //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
            if (count($folioSistema) == 1) {
              if ($folioSistema[0]->folio == 1000000000) {
                  $post_folio_db = DB::select("SELECT nomina_subfolio FROM vhum_nominas_main WHERE id = (SELECT Max(nomina.id) FROM vhum_nominas_main AS nomina JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE nomina.nomina_empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token,$usuario->user_token]);
                  
                  $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->nomina_subfolio);
                  $folio_nuevo = 1;
              } else {
                  $post_folio = NULL;
                  $folio_nuevo = $folioSistema[0]->folio;
              }
            } else {
              $post_folio = NULL;
              $folio_nuevo = 1;
            }
            $folio_nomina = 'NOM-EF-'.$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');
            $tokenMainNomina = $JwtAuth->encriptarToken($numero_de_nomina.$nomina_observacion.count($nomina_reportada));
            
            $creaMainNomina = new NominasModelo();
            $creaMainNomina->token_nominas_periodos = $tokenMainNomina;
            $creaMainNomina->nomina_folio_interior = $folio_nuevo;
            $creaMainNomina->nomina_subfolio = $post_folio;
            $creaMainNomina->nomina_numero = $numero_de_nomina;
            $creaMainNomina->nomina_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
            $creaMainNomina->nomina_observaciones = $JwtAuth->encriptar($nomina_observacion);
            $creaMainNomina->nomina_empresa = $vEmp->id;
            $savedNomnina = $creaMainNomina->save();
                        
            $nomina_id = $creaMainNomina->id;

            if ($OKNominaReportada) {
              foreach ($nomina_reportada as $e_nom_v => $e_nom_d) {
                $nomina_clave = $e_nom_d["nomina_clave"];
                $nomina_empleado_token = $e_nom_d["nomina_empleado_token"];
                $nomina_trabajador = DB::table("vhum_empleados_catalogo")->where("empleado_token",$nomina_empleado_token)->value("id");
                
                $nomina_registro_patronal = $e_nom_d['nomina_registro_patronal'];
                $OKRegistroPatronal = isset($nomina_registro_patronal) && !empty($nomina_registro_patronal) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_registro_patronal);
                $imss_registro_patronal = $OKRegistroPatronal ? DB::table("vhum_centros_de_trabajo_catalogo")->where('centrotrab_clave_registro_patronal_imss',$nomina_registro_patronal)->value('id') : NULL;
                
                $nomina_periodicidad = $e_nom_d['nomina_periodicidad'];
                $OKPeriodicidad = isset($nomina_periodicidad) && !empty($nomina_periodicidad) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_periodicidad);
                
                $nomina_periodo_inicio = $e_nom_d['nomina_periodo_inicio'];
                $OKPeriodoInicio = isset($nomina_periodo_inicio) && !empty($nomina_periodo_inicio) && preg_match($JwtAuth->filtroFecha(),$nomina_periodo_inicio);
                
                $nomina_periodo_fin = $e_nom_d['nomina_periodo_fin'];
                $OKPeriodoFin = isset($nomina_periodo_fin) && !empty($nomina_periodo_fin) && preg_match($JwtAuth->filtroFecha(),$nomina_periodo_fin);
                
                $nomina_moneda = $e_nom_d['nomina_moneda'];
                $OKMoneda = isset($nomina_moneda) && !empty($nomina_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_moneda);
                
                $nomina_empleado_nombre = $e_nom_d["nomina_empleado_nombre"];
                $nomina_dias_trabajados = $e_nom_d["nomina_dias_trabajados"];
                $nomina_sueldo = $e_nom_d["nomina_sueldo"];
                $nomina_otras_percepciones = $e_nom_d["nomina_otras_percepciones"];
                $nomina_otros_pagos = $e_nom_d["nomina_otros_pagos"];
                $nomina_total_percepciones = $e_nom_d["nomina_total_percepciones"];
                $nomina_neto_pagado = $e_nom_d["nomina_neto_pagado"];
                $nomina_total_en_especie = $e_nom_d["nomina_total_en_especie"];
                $nomina_salario_por_hora = $e_nom_d["nomina_salario_por_hora"];
                $nomina_total_imss = $e_nom_d["nomina_total_imss"];
                $nomina_horas_por_dia = $e_nom_d["nomina_horas_por_dia"];
                $nomina_total_isr = $e_nom_d["nomina_total_isr"];
                $nomina_subsidio_empleo = $e_nom_d["nomina_subsidio_empleo"];
                $nomina_otras_deducciones = $e_nom_d["nomina_otras_deducciones"];
                $nomina_total_deducciones = $e_nom_d["nomina_total_deducciones"];
                $nomina_total_efectivo = $e_nom_d["nomina_total_efectivo"];
                $nomina_salario_diario = $e_nom_d["nomina_salario_diario"];
                $nomina_salario_integrado = $e_nom_d["nomina_salario_integrado"];
                $nomina_faltas = $e_nom_d["nomina_faltas"];
                $horas_extras_dobles = $e_nom_d["nomina_horas_extras_dobles"];
                $aguinaldo = $e_nom_d["nomina_aguinaldo"];
                $horas_extras_triples = $e_nom_d["nomina_horas_extras_triples"];
                $vacaciones = $e_nom_d["nomina_vacaciones"];
                $prima_vacacional = $e_nom_d["nomina_prima_vacacional"];
                $reparto_de_utilidades = $e_nom_d["nomina_reparto_de_utilidades"];
                $despensa = $e_nom_d["nomina_despensa"];
                $premios_de_asistencia = $e_nom_d["nomina_premios_de_asistencia"];
                $premios_de_puntualidad = $e_nom_d["nomina_premios_de_puntualidad"];
                $prima_dominical = $e_nom_d["nomina_prima_dominical"];
                $bno_extra_x_comision_otro_edo = $e_nom_d["nomina_bno_extra_x_comision_otro_edo"];
                $indemnizacion = $e_nom_d["nomina_indemnizacion"];
                $prima_de_antiguedad = $e_nom_d["nomina_prima_de_antiguedad"];
                $isr_ajustado_por_subsidio = $e_nom_d["nomina_isr_ajustado_por_subsidio"];
                $credito_fonacot = $e_nom_d["nomina_credito_fonacot"];
                $credito_infonavit = $e_nom_d["nomina_credito_infonavit"];
                //$subsidio_para_el_empleo_aplicado = $e_nom_d["nomina_subsidio_empleo_aplicado"];
                
                $tokenNominaRecibo = $JwtAuth->encriptarToken($nomina_clave.$nomina_id.$nomina_trabajador.$nomina_empleado_nombre.$nomina_dias_trabajados.$nomina_sueldo.$nomina_otras_percepciones.$nomina_total_percepciones.$nomina_neto_pagado);

                DB::table("vhum_nominas_recibos")
                ->insert(array(
                  "token_nomina_recibo" => $tokenNominaRecibo,
                  "nomina_recibo_folio" => $nomina_clave,
                  "trabajador" => $nomina_trabajador,
                  "nomina_main" => $nomina_id,

                  "nomina_registro_patronal" => $imss_registro_patronal,
                  "nomina_periodo_fecha_inicio" => $OKPeriodoInicio ? $JwtAuth->convierteFechaEpoc($nomina_periodo_inicio) : NULL,
                  "nomina_periodo_fecha_fin" => $OKPeriodoFin ? $JwtAuth->convierteFechaEpoc($nomina_periodo_fin) : NULL,
                  "nomina_periodicidad" => $OKPeriodicidad ? $nomina_periodicidad : NULL,
                  "nomina_moneda" => $OKMoneda ? $nomina_moneda : NULL,

                  "dias_trabajados" => $nomina_dias_trabajados,
                  "sueldo" => $nomina_sueldo,
                  "otras_percepciones" => $nomina_otras_percepciones,
                  "otros_pagos" => $nomina_otros_pagos,
                  "total_percepciones" => $nomina_total_percepciones,
                  "neto_pagado" => $nomina_neto_pagado,
                  "total_en_especie" => $nomina_total_en_especie,
                  "salario_por_hora" => $nomina_salario_por_hora,
                  "total_imss" => $nomina_total_imss,
                  "horas_por_dia" => $nomina_horas_por_dia,
                  "total_isr" => $nomina_total_isr,
                  "subsidio_empleo" => $nomina_subsidio_empleo,
                  "otras_deducciones" => $nomina_otras_deducciones,
                  "total_deducciones" => $nomina_total_deducciones,
                  "total_efectivo" => $nomina_total_efectivo,
                  "salario_diario" => $nomina_salario_diario,
                  "salario_integrado" => $nomina_salario_integrado,
                  
                  "horas_extras_dobles" => $horas_extras_dobles,
                  "aguinaldo" => $aguinaldo,
                  "horas_extras_triples" => $horas_extras_triples,
                  "vacaciones" => $vacaciones,
                  "prima_vacacional" => $prima_vacacional,
                  "reparto_de_utilidades" => $reparto_de_utilidades,
                  "despensa" => $despensa,
                  "premios_de_asistencia" => $premios_de_asistencia,
                  "premios_de_puntualidad" => $premios_de_puntualidad,
                  "prima_dominical" => $prima_dominical,
                  "bno_extra_x_comision_otro_edo" => $bno_extra_x_comision_otro_edo,
                  "indemnizacion" => $indemnizacion,
                  "prima_de_antiguedad" => $prima_de_antiguedad,
                  "isr_ajustado_por_subsidio" => $isr_ajustado_por_subsidio,
                  "credito_fonacot" => $credito_fonacot,
                  "credito_infonavit" => $credito_infonavit,
                  //"subsidio_para_el_empleo_aplicado" => $subsidio_para_el_empleo_aplicado,
                  "faltas" => $nomina_faltas,
                ));
              }
            }
            
            //ALTER TABLE `fnzs_pagos_orden` ADD `nomina_main` INT(10) NULL AFTER `reembolso_solicitud`;
            $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
              AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

            $tknOrder = $JwtAuth->encriptarToken(time(),$folioOrden[0]->folio,$nomina_id);
            $orderpay = new OrdenPagoModelo();
            $orderpay->token_ordenPago = $tknOrder;
            $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
            $orderpay->fecha_sistema_ordenp = $fechaSistema;
            $orderpay->nomina_main = $nomina_id;
            $orderpay->doc_anterior_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
            $orderpay->orden_bloqueada = FALSE;
            $orderpay->autorizacion_pay = FALSE;
            $orderpay->fecha_autorizacion_pay = NULL;
            $orderpay->tentativa_pago = NULL;
            $orderpay->orden_terminada_bool = FALSE;
            $orderpay->orden_terminada_fecha = NULL;
            $orderpay->status_ordenPago = TRUE;
            $orderpay->empresa = $vEmp->id;
            $orderpay->comprador = $vEmp->userr;
            $insertOrder = $orderpay->save();

            if ($OKNominaEspecie) {
              $folioEspecie = DB::select("SELECT espnom.nomina_esp_folio_interior+1 AS folio,nomina_esp_subfolio FROM vhum_nominas_especie AS espnom JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                JOIN teci_usuarios_catalogo AS users WHERE espnom.nomina_esp_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? 
                ORDER BY espnom.nomina_esp_folio_interior DESC LIMIT 1",[$usuario->empresa_token,$usuario->user_token]);
              //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
              if (count($folioEspecie) == 1) {
                if ($folioEspecie[0]->folio == 1000000000) {
                    $post_folio_esp = DB::select("SELECT nomina_esp_subfolio FROM vhum_nominas_especie WHERE id = (SELECT Max(espnom.id) FROM vhum_nominas_especie AS espnom JOIN main_empresas AS emp 
                      JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE espnom.nomina_esp_empresa = emp.id AND emp.empresa_token = ?
                      AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token,$usuario->user_token]);
                    
                    $esp_post_folio = $JwtAuth->generarPostFolio($post_folio_esp[0]->nomina_esp_subfolio);
                    $esp_folio_nuevo = 1;
                } else {
                    $esp_post_folio = NULL;
                    $esp_folio_nuevo = $folioEspecie[0]->folio;
                }
              } else {
                $esp_post_folio = NULL;
                $esp_folio_nuevo = 1;
              }

              $tokenEspecieNomina = $JwtAuth->encriptarToken($nomina_id.$numero_de_nomina.$fechaSistema.count($nomina_en_especie));
              
              DB::table("vhum_nominas_especie")
              ->insert(array(
                "token_nominas_especie" => $tokenEspecieNomina,
                "nomina_esp_folio_interior" => $esp_folio_nuevo,
                "nomina_esp_subfolio" => $esp_post_folio,
                "nomina_esp_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
                "nomina_main" => $nomina_id,
                "nomina_esp_empresa" => $vEmp->id
              ));
              $id_nomina_en_especie = DB::table("vhum_nominas_especie")->where('token_nominas_especie',$tokenEspecieNomina)->value('id');

              foreach ($nomina_en_especie as $especie_v => $especie_d) {
                $espnom_empleado_token = $especie_d["nomina_empleado_token"];
                $espnom_trabajador = DB::table("vhum_empleados_catalogo")->where("empleado_token",$espnom_empleado_token)->value("id");
                $espnom_total_especie = $especie_d["nomina_total_en_especie"];
                $espnom_moneda = $especie_d['nomina_moneda'];
                
                $tokenEspNomDesg = $JwtAuth->encriptarToken($espnom_trabajador.$id_nomina_en_especie.$espnom_total_especie);

                DB::table("vhum_nominas_especie_desglose")
                ->insert(array(
                  "token_especie_desglose" => $tokenEspNomDesg,
                  "trabajador" => $espnom_trabajador,
                  "periodo_nomina" => $nomina_id,
                  "nomina_especie" => $id_nomina_en_especie,
                  "total_en_especie" => $espnom_total_especie,
                  "nomina_esp_moneda" => $espnom_moneda,
                ));
              }

              $folioEspOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
                AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

              $espOrdenPago = $JwtAuth->encriptarToken(time(),$folioEspOrden[0]->folio,$id_nomina_en_especie);
              $ordEspPay = new OrdenPagoModelo();
              $ordEspPay->token_ordenPago = $espOrdenPago;
              $ordEspPay->folio_ordenPago = $folioEspOrden[0]->folio; //falta generar
              $ordEspPay->fecha_sistema_ordenp = $fechaSistema;
              $ordEspPay->nomina_main = $nomina_id;
              $ordEspPay->nomina_en_especie = $id_nomina_en_especie;
              $orderpay->doc_anterior_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
              $ordEspPay->orden_bloqueada = FALSE;
              $ordEspPay->autorizacion_pay = FALSE;
              $ordEspPay->fecha_autorizacion_pay = NULL;
              $ordEspPay->tentativa_pago = NULL;
              $ordEspPay->orden_terminada_bool = FALSE;
              $ordEspPay->orden_terminada_fecha = NULL;
              $ordEspPay->status_ordenPago = TRUE;  //cifrado
              $ordEspPay->empresa = $vEmp->id; //cifrado
              $ordEspPay->comprador = $vEmp->userr; //cifrado
              $insertOrder = $ordEspPay->save();
            }
            
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Nomina registrada satisfactoriamente con el folio $folio_nomina"
            );
          }
        } else {
          $mensaje_error = "";
          if (!$OKNominaNumero) $mensaje_error = "Error al registrar número de nómina, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaFCont) $mensaje_error = "Error en fecha de contabilización de nómina, verifique su información";
          if (!$OKNominaObservacion) $mensaje_error = "Error al registrar observaciones de nómina, intentelo nuevamente o comuniquese a soporte";
          if (!$OKNominaReportada) $mensaje_error = "Error al registrar lista de nóminas, intentelo nuevamente o comuniquese a soporte";
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

  public function eliminaNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_nominas_periodos' => 'required|string'
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
        $token_nominas_periodos = $parametrosArray['token_nominas_periodos'];

        $OKNominaPeriodo = isset($token_nominas_periodos) && !empty($token_nominas_periodos);

        if ($OKNominaPeriodo) {
          $queryNominaMain = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'vhum_nominas_main.nomina_status' => TRUE,
            'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
          
          foreach ($queryNominaMain as $vNom) {
            $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
            ->whereNull("order.nomina_en_especie")
            ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
            ->get();
            
            if (count($queryNominaPago) == 0) {
              $queryDeleteNomina = DB::table("vhum_nominas_main")
              ->where("token_nominas_periodos",$vNom->token_nominas_periodos)
              ->limit(1)->update(array(
                "nomina_status" => FALSE,
                "nomina_fecha_delete" => time()
              ));

              if ($queryDeleteNomina) {
                $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Esta nómina ha sido eliminada satisfactoriamente');
              } else {
                $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Esta nómina no se puede eliminar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
              }
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Esta nómina no se puede eliminar, se encuentra vinculada a pagos realizados, intentelo nuevamente o comuniquese a soporte');
            }
            
            
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Error al seleccionar nómina, intentelo nuevamente o comuniquese a soporte');
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

  public function reportesDeletedNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
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
        $nominas = array();
        
        $queryRepNomina = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "emp.id")
        ->whereIn('vhum_nominas_main.id', function ($query) {
          $query->select('nomina_main')->from('vhum_nominas_recibos');
        })
        ->where([
          'vhum_nominas_main.nomina_status' => FALSE,
          'emp.empresa_token' => $usuario->empresa_token,
        ])
        ->orderBy('vhum_nominas_main.id', 'DESC')->get();

        foreach ($queryRepNomina as $vNomina) {
          //da_te_default_timezone_set('UTC');
          $totales_nomina_reporte_efectivo = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('nrec.total_efectivo');

          $moneda_nomina_recibos = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->value('nrec.nomina_moneda');

          $totales_nomina_pago_efectivo = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('pay.monto_pago');

					$totales_nomina_saldo_efectivo = $totales_nomina_reporte_efectivo - $totales_nomina_pago_efectivo;

          $queryNominaEfectOrdPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
					$nomina_efectivo_ord_pago_token = $queryNominaEfectOrdPago ? $queryNominaEfectOrdPago->token_ordenPago :'';
					$nomina_efectivo_ord_pago_folio = $queryNominaEfectOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryNominaEfectOrdPago->folio_ordenPago) :'';

          $totales_nomina_reporte_especie = DB::table("vhum_nominas_recibos AS nrec")
          ->join("vhum_nominas_main AS nmain", "nrec.nomina_main", "nmain.id")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('nrec.total_en_especie');

          $totales_nomina_pago_especie = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNotNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->sum('pay.monto_pago');

          $totales_nomina_saldo_especie = $totales_nomina_reporte_especie - $totales_nomina_pago_especie;

          $queryNominaEspeOrdPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
          ->whereNotNull("order.nomina_en_especie")
          ->where('nmain.token_nominas_periodos',$vNomina->token_nominas_periodos)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
					$nomina_especie_ord_pago_token = $queryNominaEspeOrdPago ? $queryNominaEspeOrdPago->token_ordenPago :'';
					$nomina_especie_ord_pago_folio = $queryNominaEspeOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryNominaEspeOrdPago->folio_ordenPago) :'';

          $nominas[] = array(
            'token_nominas_periodos' => $vNomina->token_nominas_periodos,
            'folio_interior' => 'NOM-EF-'.$JwtAuth->generarFolio($vNomina->nomina_folio_interior).(!is_null($vNomina->nomina_subfolio) ? '-'.$vNomina->nomina_subfolio : ''),
            'nomina_numero' => $vNomina->nomina_numero,
            'nomina_fecha_contabilizacion' => gmdate('Y-m-d H:i:s',$vNomina->nomina_fecha_contabilizacion),
            'nomina_fecha_delete' => gmdate('Y-m-d H:i:s',$vNomina->nomina_fecha_delete)
          );
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'nominas' => $nominas
        );
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

  public function restauraNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_nominas_periodos' => 'required|string'
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
        $token_nominas_periodos = $parametrosArray['token_nominas_periodos'];

        $OKNominaPeriodo = isset($token_nominas_periodos) && !empty($token_nominas_periodos);

        if ($OKNominaPeriodo) {
          $queryNominaMain = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'vhum_nominas_main.nomina_status' => FALSE,
            'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
          
          foreach ($queryNominaMain as $vNom) {
            $queryDeleteNomina = DB::table("vhum_nominas_main")
            ->where("token_nominas_periodos",$vNom->token_nominas_periodos)
            ->limit(1)->update(array(
              "nomina_status" => TRUE,
              "nomina_fecha_delete" => NULL
            ));

            if ($queryDeleteNomina) {
              $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Esta nómina ha sido restaurada satisfactoriamente');
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Esta nómina no se puede restaurar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
            }
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Error al seleccionar nómina, intentelo nuevamente o comuniquese a soporte');
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

  public function eliminaPermanenteNominaTrabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_nominas_periodos' => 'required|string'
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
        $token_nominas_periodos = $parametrosArray['token_nominas_periodos'];

        $OKNominaPeriodo = isset($token_nominas_periodos) && !empty($token_nominas_periodos);

        if ($OKNominaPeriodo) {
          $queryNominaMain = NominasModelo::join("main_empresas AS emp", "vhum_nominas_main.nomina_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'vhum_nominas_main.nomina_status' => FALSE,
            'vhum_nominas_main.token_nominas_periodos' => $token_nominas_periodos,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
          
          foreach ($queryNominaMain as $vNom) {
            $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
            ->whereNull("order.nomina_en_especie")
            ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
            ->get();
            
            if (count($queryNominaPago) == 0) {
              $queryNominaPago = DB::table("fnzs_pagos_orden AS order")
              ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
              ->whereNull("order.nomina_en_especie")
              ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
              ->limit(1)->delete();
            }
            
            $queryNominaEspeciePago = DB::table("fnzs_pagos_pago AS pay")
            ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
            ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
            ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
            ->join("vhum_nominas_especie AS n_espe", "order.nomina_en_especie", "=", "n_espe.id")
            ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
            ->get();
            
            if (count($queryNominaEspeciePago) > 0) {
              $queryNominaPago = DB::table("fnzs_pagos_orden AS order")
              ->join("vhum_nominas_main AS nmain", "order.nomina_main", "=", "nmain.id")
              ->join("vhum_nominas_especie AS n_espe", "order.nomina_en_especie", "=", "n_espe.id")
              ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
              ->limit(1)->delete();
            }

            $queryNominaEspecie = DB::table("vhum_nominas_especie AS n_espe")
            ->join("vhum_nominas_main AS nmain", "n_espe.nomina_main", "=", "nmain.id")
            ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
            ->get();

            if (count($queryNominaEspecie) > 0) {
              $queryNominaEspecieDesglose = DB::table("vhum_nominas_especie_desglose AS desglo")
              ->join("vhum_nominas_especie AS n_espe", "desglo.nomina_especie", "=", "n_espe.id")
              ->join("vhum_nominas_main AS nmain", "n_espe.nomina_main", "=", "nmain.id")
              ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
              ->get();

              foreach ($queryNominaEspecieDesglose as $dDesg) {
                $queryNominaEspecieDesglose = DB::table("vhum_nominas_especie_desglose")
                ->where('token_especie_desglose',$dDesg->token_especie_desglose)
                ->limit(1)->delete();
              }

              $queryNominaEspDesglosar = DB::table("vhum_nominas_especie AS n_espe")
              ->join("vhum_nominas_main AS nmain", "n_espe.nomina_main", "=", "nmain.id")
              ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
              ->limit(1)->delete();
            }
            
            $queryNominaEspecieRecibos = DB::table("vhum_nominas_recibos AS recibos")
            ->join("vhum_nominas_main AS nmain", "recibos.nomina_main", "=", "nmain.id")
            ->where('nmain.token_nominas_periodos',$vNom->token_nominas_periodos)
            ->get();

            foreach ($queryNominaEspecieRecibos as $dnRec) {
              $queryNominaEspecieDesglose = DB::table("vhum_nominas_recibos")
              ->where('token_nomina_recibo',$dnRec->token_nomina_recibo)
              ->limit(1)->delete();
            }

            $queryDeleteNomina = DB::table("vhum_nominas_main")
            ->where("token_nominas_periodos",$vNom->token_nominas_periodos)
            ->limit(1)->delete();

            if ($queryDeleteNomina) {
              $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Esta nómina ha sido eliminada satisfactoriamente');
            } else {
              $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Esta nómina no se puede eliminar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
            }
            
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Error al seleccionar nómina, intentelo nuevamente o comuniquese a soporte');
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
}