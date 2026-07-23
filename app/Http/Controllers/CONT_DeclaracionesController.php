<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\DeclaracionesFederalesModelo;
use App\Models\OrdenPagoModelo;
use Carbon\Carbon;

class CONT_DeclaracionesController extends Controller{
  public function declaracionRegistro(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'fecha_contabilizacion' => 'required|string',
      'tipo_declaracion' => 'required|string',
      'periodicidad' => 'required|string',
      'ejercicio' => 'required|numeric',
      'periodo_inicio' => 'required|string',
      'periodo_fin' => 'required|string',
      'fecha_presentacion' => 'required|string',
      'medio_presentacion' => 'required|string',
      'fecha_vencimiento' => 'required|string',
      'linea_de_captura' => 'required|string',
      'version' => 'required|string',
      'numero_operacion' => 'required|string',
      'moneda' => 'required|string',
      'declaraciones_lista_pagar' => 'required|array',
      'observaciones' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos que desea registrar',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $tipo_declaracion = $request->input('tipo_declaracion');
      $periodicidad = $request->input('periodicidad');
      $ejercicio = $request->input('ejercicio');
      $periodo_inicio = $request->input('periodo_inicio');
      $periodo_fin = $request->input('periodo_fin');
      $fecha_presentacion = $request->input('fecha_presentacion');
      $medio_presentacion = $request->input('medio_presentacion');
      $fecha_vencimiento = $request->input('fecha_vencimiento');
      $version = $request->input('version');
      $numero_operacion = $request->input('numero_operacion');
      $linea_de_captura = $request->input('linea_de_captura');
      $moneda = $request->input('moneda');
      $declaraciones_lista_pagar = $request->input('declaraciones_lista_pagar');
      $observaciones = $request->input('observaciones');
      
      $OKFechaCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $OKTipoDeclaracion = isset($tipo_declaracion) && !empty($tipo_declaracion) && preg_match($JwtAuth->filtroAlfaNumerico(),$tipo_declaracion);
      $OKPeriodicidad = isset($periodicidad) && !empty($periodicidad) && preg_match($JwtAuth->filtroAlfaNumerico(),$periodicidad);
  
      $OKEjercicio = isset($ejercicio) && !empty($ejercicio) && preg_match($JwtAuth->filtroNumerico(),$ejercicio);
      $OKPeriodoInicio = isset($periodo_inicio) && !empty($periodo_inicio) && preg_match($JwtAuth->filtroFecha(),$periodo_inicio);
      $OKPeriodoFin = isset($periodo_fin) && !empty($periodo_fin) && preg_match($JwtAuth->filtroFecha(),$periodo_fin);
      $OKPeriodo = $OKPeriodoInicio && $OKPeriodoFin && ($JwtAuth->convierteFechaEpoc($periodo_fin) >= $JwtAuth->convierteFechaEpoc($periodo_inicio));

      $OKFechaPresentacion = isset($fecha_presentacion) && !empty($fecha_presentacion) && preg_match($JwtAuth->filtroFecha(),$fecha_presentacion);
      $OKMedioPresentacion = isset($medio_presentacion) && !empty($medio_presentacion) && preg_match($JwtAuth->filtroAlfaNumerico(),$medio_presentacion);
      $OKFechaVencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
      $OKVersion = isset($version) && !empty($version) && preg_match($JwtAuth->filtroAlfaNumerico(),$version);
      $OKNumeroOperacion = isset($numero_operacion) && !empty($numero_operacion) && preg_match($JwtAuth->filtroNumerico(),$numero_operacion);
      $OKLineaCaptura = isset($linea_de_captura) && !empty($linea_de_captura) && preg_match($JwtAuth->filtroAlfaNumerico(),$linea_de_captura);
      $OKListaPagar = isset($declaraciones_lista_pagar) && is_array($declaraciones_lista_pagar) && count($declaraciones_lista_pagar) > 0;
      $OKObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

      if ($OKFechaCont && $OKTipoDeclaracion && $OKPeriodicidad && $OKEjercicio && $OKPeriodo && $OKFechaPresentacion && 
        $OKMedioPresentacion && $OKFechaVencimiento && $OKVersion && $OKNumeroOperacion && $OKLineaCaptura && $OKListaPagar && $OKObservacion) {
        $fechaSistema = time();
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);
        
        foreach ($queryEmp as $vEmp) { 
          $folioSistema = DB::select("SELECT fiscDec.declaracion_folio_interior+1 AS folio,declaracion_subfolio FROM cont_reg_fisc_declaraciones_imp_federales AS fiscDec JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
            JOIN teci_usuarios_catalogo AS users WHERE fiscDec.declaracion_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? 
            ORDER BY fiscDec.declaracion_folio_interior DESC LIMIT 1",[$empresa,$usuario]);
          //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
          if (count($folioSistema) == 1) {
            if ($folioSistema[0]->folio == 1000000000) {
              $post_folio_db = DB::select("SELECT declaracion_subfolio FROM cont_reg_fisc_declaraciones_imp_federales WHERE id = (SELECT Max(fiscDec.id) FROM cont_reg_fisc_declaraciones_imp_federales AS fiscDec JOIN main_empresas AS emp 
                JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE fiscDec.declaracion_empresa = emp.id AND emp.empresa_token = ?
                AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$empresa,$usuario]);
              
              $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->declaracion_subfolio);
              $folio_nuevo = 1;
            } else {
              $post_folio = NULL;
              $folio_nuevo = $folioSistema[0]->folio;
            }
          } else {
            $post_folio = NULL;
            $folio_nuevo = 1;
          }
          $folio_imp_fed = 'DEC-IMPFED-'.$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');
          $declaracion_token = $JwtAuth->encriptarToken($fecha_contabilizacion.$OKNumeroOperacion.$folio_nuevo.$linea_de_captura);

          $fecha_registro = time();
          $newDecFed = new DeclaracionesFederalesModelo();
          $newDecFed->declaracion_token = $declaracion_token;
          $newDecFed->declaracion_fecha_registro = $fecha_registro;
          $newDecFed->declaracion_folio_interior = $folio_nuevo;
          $newDecFed->declaracion_subfolio = $post_folio;
          $newDecFed->declaracion_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
          $newDecFed->declaracion_tipo = $tipo_declaracion;
          $newDecFed->declaracion_periodicidad = $periodicidad;
          $newDecFed->declaracion_ejercicio = $ejercicio;
          $newDecFed->declaracion_periodo_inicio = $JwtAuth->convierteFechaEpoc($periodo_inicio);
          $newDecFed->declaracion_periodo_fin = $JwtAuth->convierteFechaEpoc($periodo_fin);
          $newDecFed->declaracion_fecha_presentacion = $JwtAuth->convierteFechaEpoc($fecha_presentacion);
          $newDecFed->declaracion_medio_presentacion = $medio_presentacion;
          $newDecFed->declaracion_fecha_vencimiento = $JwtAuth->convierteFechaEpoc($fecha_vencimiento);
          $newDecFed->declaracion_version = $version;
          $newDecFed->declaracion_numero_operacion = $numero_operacion;
          $newDecFed->declaracion_linea_de_captura = $linea_de_captura;
          $newDecFed->declaracion_moneda = $moneda;
          $newDecFed->declaracion_observaciones = $JwtAuth->encriptar($observaciones);
          $newDecFed->declaracion_status = TRUE;
          $newDecFed->declaracion_empresa = $vEmp->id;
          $savedDecFed = $newDecFed->save();
          if ($savedDecFed) {
            $decFedID = $newDecFed->id;
            $filepath = $vEmp->root_tkn . "/0005-cnt/declaraciones/impuestos_federales/$fecha_registro-$folio_imp_fed/anexos/";

            $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
              AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

            $tknOrder = $JwtAuth->encriptarToken(time(),$folioOrden[0]->folio,$decFedID);
            $orderpay = new OrdenPagoModelo();
            $orderpay->token_ordenPago = $tknOrder;
            $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
            $orderpay->fecha_sistema_ordenp = $fechaSistema;
            $orderpay->declaracion_imp_federales = $decFedID;
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

            foreach ($declaraciones_lista_pagar as $e_dec_v => $e_dec_l) {
              $concepto_pago_token = $e_dec_l["concepto_pago_token"];
              $impuesto_id = DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$concepto_pago_token)->value("id");
              $concepto_pago_name = $e_dec_l["concepto_pago_name"];
              $importe_a_favor = $e_dec_l["importe_a_favor"];
              $a_cargo = $e_dec_l["a_cargo"];
              $actualizaciones = $e_dec_l["actualizaciones"];
              $recargos = $e_dec_l["recargos"];
              $otros_cargos = $e_dec_l["otros_cargos"];
              $otros_abonos = $e_dec_l["otros_abonos"];
              $cantidad_a_pagar = $e_dec_l["cantidad_a_pagar"];
              
              $dec_desglose_new_token = $JwtAuth->encriptarToken($impuesto_id.$importe_a_favor.$a_cargo.$recargos.$otros_abonos.$cantidad_a_pagar);

              DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose")
              ->insert(array(
                "declaracion" => $decFedID,
                "dec_desglose_token" => $dec_desglose_new_token,
                "dec_desglose_impuesto" => $impuesto_id,
                "dec_desglose_impuesto_importe_a_favor" => $importe_a_favor,
                "dec_desglose_impuesto_a_cargo" => $a_cargo,
                "dec_desglose_impuesto_actualizaciones" => $actualizaciones,
                "dec_desglose_impuesto_recargos" => $recargos,
                "dec_desglose_impuesto_otros_cargos" => $otros_cargos,
                "dec_desglose_impuesto_otros_abonos" => $otros_abonos,
                "dec_desglose_impuesto_cantidad_a_pagar" => $cantidad_a_pagar,
              ));
            }

            if (!empty($_FILES['documentos_evidencia'])) {
              $evidencias = $_FILES["documentos_evidencia"];
              //return response()->json(['status' => 'error','code' => 200,'message' => json_decode($evidencias]));
              //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1']);
              $string_name_evid = json_encode($_FILES["documentos_evidencia"]["name"]);
              if (count(json_decode($string_name_evid)) != 0) {
                $evidencia_nombre = json_decode($string_name_evid);
                for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                  $temporal = $evidencias["tmp_name"][$doc];
                  $doc_name = $evidencias["name"][$doc];
                  Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);
                  $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%DEC-IMP-FED-EVID%'");
                  $token_documento = $JwtAuth->encriptarToken($decFedID,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                  $insertDocSoli = DB::table("sos_documentos")->insert(
                    array(
                      "token_documento" => $token_documento,
                      "fecha_carga" => time(),
                      "modulo" => "pagos",
                      "folio_modulo" => "DEC-IMP-FED-EVID".$select_folio_doc[0]->folio,
                      "tipo_documento" => "an",
                      "nombre_documento" => $JwtAuth->encriptar($doc_name),
                      "declaracion_imp_federales" => $decFedID,
                      "status_documento" => TRUE,
                    )
                  );
                }
              }
            }

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Declaración de inpuestos federales registrada satisfactoriamente con el folio $folio_imp_fed"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Datos generales de este reporte no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
            );
          }
        }
      } else {
        $mensaje_error = "";
        if (!$OKFechaCont) $mensaje_error = "Error en fecha de contabilización, por favor verifique su información o comuniquese a soporte"; 
        if (!$OKTipoDeclaracion) $mensaje_error = "Error en tipo de declaración, por favor verifique su información o comuniquese a soporte";
        if (!$OKPeriodicidad) $mensaje_error = "Error en periodicidad, por favor verifique su información o comuniquese a soporte";
        if (!$OKEjercicio) $mensaje_error = "Error en ejercicio, por favor verifique su información o comuniquese a soporte";
        if (!$OKPeriodo) $mensaje_error = "Error en periodo, por favor verifique su información o comuniquese a soporte";
        if (!$OKFechaPresentacion) $mensaje_error = "Error en fecha de presentación, por favor verifique su información o comuniquese a soporte"; 
        if (!$OKMedioPresentacion) $mensaje_error = "Error en medio de presentación, por favor verifique su información o comuniquese a soporte";
        if (!$OKFechaVencimiento) $mensaje_error = "Error en fecha de vencimiento, por favor verifique su información o comuniquese a soporte";
        if (!$OKVersion) $mensaje_error = "Error en version, por favor verifique su información o comuniquese a soporte";
        if (!$OKNumeroOperacion) $mensaje_error = "Error en número de operación, por favor verifique su información o comuniquese a soporte";
        if (!$OKLineaCaptura) $mensaje_error = "Error en línea de captura, por favor verifique su información o comuniquese a soporte";
        if (!$OKListaPagar) $mensaje_error = "Error en la lista de impuestos que declara, por favor verifique su información o comuniquese a soporte";
        if (!$OKObservacion) $mensaje_error = "Error en observación, por favor verifique su información o comuniquese a soporte";
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoGeneralDeclaraciones(Request $request){
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
      
      $queryDeclaraciones = DeclaracionesFederalesModelo::join('main_empresas AS emp', 'cont_reg_fisc_declaraciones_imp_federales.declaracion_empresa', 'emp.id')
      ->where([
        'cont_reg_fisc_declaraciones_imp_federales.declaracion_status' => TRUE, 
        'emp.empresa_token' => $empresa
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("cont_reg_fisc_declaraciones_imp_federales.declaracion_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy("cont_reg_fisc_declaraciones_imp_federales.id","DESC")
      ->get();
  
      if ($queryDeclaraciones->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron declaraciones registradas'
        );
      } else {
        $listaDeclaraciones = array();
        $JwtAuth = new \App\Helpers\JwtAuth();
        
        foreach ($queryDeclaraciones as $vDec) {
          //da_te_default_timezone_set('UTC');
          $folio_imp_fed = 'DEC-IMPFED-'.$JwtAuth->generarFolio($vDec->declaracion_folio_interior).(!is_null($vDec->declaracion_subfolio) ? '-'.$vDec->declaracion_subfolio : '');
          $declaracion_moneda = $vDec->declaracion_moneda;
          $declaracion_moneda_decimales = $JwtAuth->getMonedaAPI($vDec->declaracion_moneda);

          $queryImpEstado = DB::table("cont_reg_fisc_declaraciones_imp_federales AS fedMain")
          ->join("fnzs_catalogos_fed_estados_municipios AS ent", "fedMain.proveedor_sat", "ent.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->select('ent.fed_est_mun_rfc','ent.fed_est_mun_entidad')
          ->first();

          $estado_rfc = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc : '';
          $estado_entidad = $queryImpEstado ? $JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';
          $estado_all_info = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc.' '.$JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';

          $impuesto_a_cargo = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose AS fedDes")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "fedDes.declaracion", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->sum('fedDes.dec_desglose_impuesto_a_cargo');

          $actualizaciones = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose AS fedDes")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "fedDes.declaracion", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->sum('fedDes.dec_desglose_impuesto_actualizaciones');

          $recargos = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose AS fedDes")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "fedDes.declaracion", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->sum('fedDes.dec_desglose_impuesto_recargos');

          $otros_cargos = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose AS fedDes")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "fedDes.declaracion", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->sum('fedDes.dec_desglose_impuesto_otros_cargos');

          $otros_abonos = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose AS fedDes")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "fedDes.declaracion", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->sum('fedDes.dec_desglose_impuesto_otros_abonos');

          $cantidad_a_pagar = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose AS fedDes")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "fedDes.declaracion", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->sum('fedDes.dec_desglose_impuesto_cantidad_a_pagar');

          $queryDecFedPagoDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "order.declaracion_imp_federales", "=", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();

          $totales_dec_pago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "order.declaracion_imp_federales", "=", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->sum('pay.monto_pago');

					$totales_dec_saldo = $cantidad_a_pagar - $totales_dec_pago;

          $queryDECOrdPago = DB::table("fnzs_pagos_orden AS order")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "order.declaracion_imp_federales", "=", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
					$dec_ord_pago_token = $queryDECOrdPago ? $queryDECOrdPago->token_ordenPago :'';
					$dec_ord_pago_folio = $queryDECOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryDECOrdPago->folio_ordenPago) :'';

          $row = array(
            'declaracion_token' => $vDec->declaracion_token,
            'declaracion_folio' => $folio_imp_fed,
            'sat_rfc' => $estado_rfc,
            'sat_entidad' => $estado_entidad,
            'sat_all_info' => $estado_all_info,
            'declaracion_fecha_contabilizacion' => $JwtAuth->mostrarUnixAFechaMexico($vDec->declaracion_fecha_contabilizacion),
            'declaracion_tipo' => $vDec->declaracion_tipo,
            'declaracion_periodicidad' => $vDec->declaracion_periodicidad,
            'declaracion_ejercicio' => $vDec->declaracion_ejercicio,
            'declaracion_periodo_inicio' => ucfirst(Carbon::createFromTimestamp($vDec->declaracion_periodo_inicio)->locale('es')->translatedFormat('F')),
            'declaracion_periodo_fin' => ucfirst(Carbon::createFromTimestamp($vDec->declaracion_periodo_fin)->locale('es')->translatedFormat('F')),
            'declaracion_fecha_presentacion' => $JwtAuth->mostrarUnixAFechaMexico($vDec->declaracion_fecha_presentacion),
            'declaracion_medio_presentacion' => $vDec->declaracion_medio_presentacion,
            'declaracion_fecha_vencimiento' => $JwtAuth->mostrarUnixAFechaMexico($vDec->declaracion_fecha_vencimiento),
            'declaracion_version' => $vDec->declaracion_version,
            'declaracion_numero_operacion' => $vDec->declaracion_numero_operacion,
            'declaracion_linea_de_captura' => $vDec->declaracion_linea_de_captura,
            'declaracion_moneda' => $declaracion_moneda,
            'cantidad_a_pagar' => "$".number_format($cantidad_a_pagar,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
            'declaracion_observaciones' => $JwtAuth->desencriptar($vDec->declaracion_observaciones),
            'dec_pago' => "$".number_format($totales_dec_pago,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
            'dec_saldo' => "$".number_format($totales_dec_saldo,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
            'dec_ord_pago_token' => $dec_ord_pago_token,
            'dec_ord_pago_folio' => $dec_ord_pago_folio,
            'dec_habilita_carga_docs' => $queryDecFedPagoDone ? true : false,
            'puede_eliminar' => !$queryDecFedPagoDone ? true : false
          );
          $listaDeclaraciones[] = $row;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "declaraciones" => $listaDeclaraciones
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function declaracionImpFederalesSeguimientoOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'declaracion_token' => 'required|string',
      'dec_ord_pago_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
			$declaracion_token = $request->input('declaracion_token');
			$dec_ord_pago_token = $request->input('dec_ord_pago_token');
      
      $queryIMSSOrdenPago = DB::table("cont_reg_fisc_declaraciones_imp_federales AS fedMain")
      ->join("fnzs_pagos_orden AS order", "fedMain.id", "=", "order.declaracion_imp_federales")
      ->join("main_empresas AS emp", "fedMain.declaracion_empresa", "=", "emp.id")
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'fedMain.declaracion_status' => TRUE,
        'fedMain.declaracion_token' => $declaracion_token,
        'order.token_ordenPago' => $dec_ord_pago_token,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();

      if ($queryIMSSOrdenPago->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron declaraciones registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $orden_pago_registro = array();

				foreach ($queryIMSSOrdenPago as $rOrdPag) {
					//da_te_default_timezone_set($rOrdPag->zona_horaria);
          $folio_interior = $rOrdPag->declaracion_folio_interior;
          $post_folio = $rOrdPag->declaracion_subfolio;
					$autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;
					$fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->fecha_autorizacion_pay) : "---";
					$status_pay_bool = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? true : false;

					$orden_emisor_emp = $rOrdPag->abrev_nombre;

          $importe_total_anticipo = 0;
					$importe_total_inicial = 0;
					$orden_moneda_inicial_name = $rOrdPag->declaracion_moneda;
					$orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->declaracion_moneda);

					$importe_autorizado_inicial = 0;
					$orden_moneda_autorizado_inicial_tkn = $rOrdPag->declaracion_moneda;
					$orden_moneda_autorizado_inicial_name = $rOrdPag->declaracion_moneda;
					$orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->declaracion_moneda);

					$importe_autorizado_final = 0;
					$orden_moneda_autorizado_final_name = $rOrdPag->declaracion_moneda;
					$orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($rOrdPag->declaracion_moneda);
          
          $cantidad_a_pagar = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose AS fedDes")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "fedDes.declaracion", "fedMain.id")
          ->where('fedMain.declaracion_token',$rOrdPag->declaracion_token)
          ->sum('fedDes.dec_desglose_impuesto_cantidad_a_pagar');

          $importe_total_inicial = $cantidad_a_pagar;
          $importe_autorizado_inicial = $cantidad_a_pagar;
          $importe_autorizado_final = $cantidad_a_pagar;

					//pagos_realizados
          $status_pay_date = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->orden_terminada_fecha) : "---";

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
            "fecha_contabilizacion_doc_anterior" => gmdate('Y-m-d H:i:s',$rOrdPag->doc_anterior_fecha_contabilizacion),
            "fecha_contabilizacion_orden_pago" => $rOrdPag->fecha_contabilizacion_ordenPago ? gmdate('Y-m-d H:i:s',$rOrdPag->fecha_contabilizacion_ordenPago) : '',
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($rOrdPag->fecha_sistema_ordenp),
            "orden_bloqueada" => $rOrdPag->orden_bloqueada ? true : false,
            "autorizacion_pay" => $autorizacion_pay,
            "autorizacion_pay_translate" => $autorizacion_pay ? 'yes_auth' : 'not_auth',
            "autorizacion_pay_text" => "",
            "fecha_autorizacion_pay" => $fecha_autorizacion_pay,
            "factura_relacionada_typo" => "nominas",
            "factura_relacionada_token" => $rOrdPag->declaracion_token,
            "factura_relacionada_string" => 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : ''),
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
          $orden_pago_registro[] = $row_ordenPay;
				}

        $pagos_realizados_registro = $JwtAuth->pagosDoneBYOrdenDesglose($dec_ord_pago_token,$empresa,$usuario);

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'seguimiento_orden_pago' => $orden_pago_registro,
          'pagos_realizados' => $pagos_realizados_registro,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function desgloseDeclaracionImpFederales(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'declaracion_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      //da_te_default_timezone_set('America/Mexico_City');
      $declaracion_token = $request->input('declaracion_token');
      
      $queryDeclaraciones = DeclaracionesFederalesModelo::join('main_empresas AS emp', 'cont_reg_fisc_declaraciones_imp_federales.declaracion_empresa', 'emp.id')
      ->where([
        'cont_reg_fisc_declaraciones_imp_federales.declaracion_token' => $declaracion_token,
        'cont_reg_fisc_declaraciones_imp_federales.declaracion_status' => TRUE,
        'emp.empresa_token' => $empresa
      ])
      ->get();
  
      if ($queryDeclaraciones->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $declaracion = array();

        foreach ($queryDeclaraciones as $vDec) {
          //da_te_default_timezone_set('UTC');
          $folio_imp_fed = 'DEC-IMPFED-'.$JwtAuth->generarFolio($vDec->declaracion_folio_interior).(!is_null($vDec->declaracion_subfolio) ? '-'.$vDec->declaracion_subfolio : '');
          $declaracion_moneda = $vDec->declaracion_moneda;
          $declaracion_moneda_decimales = $JwtAuth->getMonedaAPI($vDec->declaracion_moneda);
          $periodoCarbonI = ucfirst(Carbon::createFromTimestamp($vDec->declaracion_periodo_inicio)->locale('es')->translatedFormat('F'));
          $periodoCarbonF = ucfirst(Carbon::createFromTimestamp($vDec->declaracion_periodo_fin)->locale('es')->translatedFormat('F'));

          $queryImpEstado = DB::table("cont_reg_fisc_declaraciones_imp_federales AS fedMain")
          ->join("fnzs_catalogos_fed_estados_municipios AS ent", "fedMain.proveedor_sat", "ent.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->select('ent.fed_est_mun_rfc','ent.fed_est_mun_entidad')
          ->first();

          $estado_rfc = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc : '';
          $estado_entidad = $queryImpEstado ? $JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';
          $estado_all_info = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc.' '.$JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';

          $desglose_dec = array();
          $queryDecImpFedDesglose = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose AS fedDes")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "fedDes.declaracion", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->get();

          foreach ($queryDecImpFedDesglose as $dVec) {
            $catImp = DB::table('cont_impuestos_catalogo AS catImp')
            ->join('cont_reg_fisc_declaraciones_imp_federales_desglose AS fedDes', 'catImp.id', 'fedDes.dec_desglose_impuesto')
            ->where('fedDes.dec_desglose_token',$dVec->dec_desglose_token)
            ->select('catImp.token_catalogo_impuesto','catImp.folio_impuesto','catImp.post_folio','catImp.concepto_impuesto','catImp.abreviacion_impuesto')
            ->first();
            $folio_impuesto = $catImp ?'IMP-'.$JwtAuth->generarFolio($catImp->folio_impuesto).(!is_null($catImp->post_folio) ? '-'.$catImp->post_folio : '') : '';
            
            $rddeg = array(
              "dec_desglose_token" => $dVec->dec_desglose_token,
              "concepto_pago_token" => $catImp ? $catImp->token_catalogo_impuesto : '',
              "concepto_pago_name" => $catImp ? $folio_impuesto." ".$JwtAuth->desencriptar($catImp->concepto_impuesto)." (". $JwtAuth->desencriptar($catImp->abreviacion_impuesto).")" : '',
              
              "importe_a_favor" => number_format($dVec->dec_desglose_impuesto_importe_a_favor, $declaracion_moneda_decimales, '.', ''),
              "importe_a_favor_format" => "$".number_format($dVec->dec_desglose_impuesto_importe_a_favor,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",

              "a_cargo" => number_format($dVec->dec_desglose_impuesto_a_cargo, $declaracion_moneda_decimales, '.', ''),
              "a_cargo_format" => "$".number_format($dVec->dec_desglose_impuesto_a_cargo,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",

              "actualizaciones" => number_format($dVec->dec_desglose_impuesto_actualizaciones, $declaracion_moneda_decimales, '.', ''),
              "actualizaciones_format" => "$".number_format($dVec->dec_desglose_impuesto_actualizaciones,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",

              "recargos" => number_format($dVec->dec_desglose_impuesto_recargos, $declaracion_moneda_decimales, '.', ''),
              "recargos_format" => "$".number_format($dVec->dec_desglose_impuesto_recargos,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",

              "otros_cargos" => number_format($dVec->dec_desglose_impuesto_otros_cargos, $declaracion_moneda_decimales, '.', ''),
              "otros_cargos_format" => "$".number_format($dVec->dec_desglose_impuesto_otros_cargos,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",

              "otros_abonos" => number_format($dVec->dec_desglose_impuesto_otros_abonos, $declaracion_moneda_decimales, '.', ''),
              "otros_abonos_format" => "$".number_format($dVec->dec_desglose_impuesto_otros_abonos,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",

              "cantidad_a_pagar" => number_format($dVec->dec_desglose_impuesto_cantidad_a_pagar, $declaracion_moneda_decimales, '.', ''),
              "cantidad_a_pagar_format" => "$".number_format($dVec->dec_desglose_impuesto_cantidad_a_pagar,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",

              "proceso_eliminacion" => false,
            );
            $desglose_dec[] = $rddeg;
          }

          $queryDecFedPago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "order.declaracion_imp_federales", "=", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->count();

          $decAnexos = array();
          $queryDocsDecFed = DB::table("sos_documentos AS docs")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "docs.declaracion_imp_federales", "=", "fedMain.id")
          ->where([
            "docs.status_documento" => TRUE,
            "fedMain.declaracion_token" => $vDec->declaracion_token
          ])
          ->get();

          foreach ($queryDocsDecFed as $xDoc) {
            $nombre = $JwtAuth->desencriptar($xDoc->nombre_documento);
            $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

            $rowXML = array(
              "token_documento" => $xDoc->token_documento,
              "tipo_documental" => $xDoc->tipo_documento,
              "extension" => $extension,
              "name_documento" => $nombre,
              "url" => "https://downloads.sos-mexico.com.mx/impuestos_federales/$folio_imp_fed/$xDoc->token_documento",
              "eliminacion_proceso" => false
            );
            $decAnexos[] = $rowXML;
          }

          $row = array(
            "declaracion_token" => $vDec->declaracion_token,
            "declaracion_folio" => $folio_imp_fed,
            "sat_rfc" => $estado_rfc,
            "sat_entidad" => $estado_entidad,
            "sat_all_info" => $estado_all_info,
            "declaracion_fecha_contabilizacion" => date('Y-m-d', $vDec->declaracion_fecha_contabilizacion),
            "declaracion_tipo" => $vDec->declaracion_tipo,
            "declaracion_periodicidad" => $vDec->declaracion_periodicidad,
            
            "declaracion_ejercicio" => $vDec->declaracion_ejercicio,
            "declaracion_periodo" => $periodoCarbonI == $periodoCarbonF ? $periodoCarbonF : $periodoCarbonI." - ".$periodoCarbonF,
            "declaracion_periodo_inicio" => date('Y-m-d', $vDec->declaracion_periodo_inicio),
            "declaracion_periodo_fin" => date('Y-m-d', $vDec->declaracion_periodo_fin),
            
            "declaracion_fecha_presentacion" => date('Y-m-d', $vDec->declaracion_fecha_presentacion),
            "declaracion_medio_presentacion" => $vDec->declaracion_medio_presentacion,
            "declaracion_fecha_vencimiento" => date('Y-m-d', $vDec->declaracion_fecha_vencimiento),
            "declaracion_version" => $vDec->declaracion_version,
            "declaracion_numero_operacion" => $vDec->declaracion_numero_operacion,
            "declaracion_linea_de_captura" => $vDec->declaracion_linea_de_captura,
            "declaracion_moneda" => $declaracion_moneda,
            "desglose_dec" => $desglose_dec,
            "declaracion_observaciones" => $JwtAuth->desencriptar($vDec->declaracion_observaciones),
            "decAnexos" => $decAnexos,
            'vinculacion_a_pagos' => $queryDecFedPago > 0 ? true : false
          );
          $declaracion[] = $row;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "declaracion" => $declaracion
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaDeclaracion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'declaracion_token' => 'required|string',
      'fecha_contabilizacion' => 'required|string',
      'tipo_declaracion' => 'required|string',
      'periodicidad' => 'required|string',
      'ejercicio' => 'required|numeric',
      'periodo_inicio' => 'required|string',
      'periodo_fin' => 'required|string',
      'fecha_presentacion' => 'required|string',
      'medio_presentacion' => 'required|string',
      'fecha_vencimiento' => 'required|string',
      'linea_de_captura' => 'required|string',
      'version' => 'required|string',
      'numero_operacion' => 'required|string',
      'moneda' => 'required|string',
      'declaraciones_lista_eliminar' => 'nullable|array',
      'declaraciones_lista_pagar' => 'nullable|array',
      'observaciones' => 'required|string',
      'anexos_lista_eliminar' => 'nullable|array',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos que desea actualizar',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $fecha_sistema = time();
      $declaracion_token = $request->input('declaracion_token');
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $tipo_declaracion = $request->input('tipo_declaracion');
      $periodicidad = $request->input('periodicidad');
      $ejercicio = $request->input('ejercicio');
      $periodo_inicio = $request->input('periodo_inicio');
      $periodo_fin = $request->input('periodo_fin');
      $fecha_presentacion = $request->input('fecha_presentacion');
      $medio_presentacion = $request->input('medio_presentacion');
      $fecha_vencimiento = $request->input('fecha_vencimiento');
      $version = $request->input('version');
      $numero_operacion = $request->input('numero_operacion');
      $linea_de_captura = $request->input('linea_de_captura');
      $moneda = $request->input('moneda');
      $declaraciones_lista_eliminar = $request->input('declaraciones_lista_eliminar');
      $declaraciones_lista_pagar = $request->input('declaraciones_lista_pagar');
      $observaciones = $request->input('observaciones');
      $anexos_lista_eliminar = $request->input('anexos_lista_eliminar');

      $OKFechaCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $OKTipoDeclaracion = isset($tipo_declaracion) && !empty($tipo_declaracion) && preg_match($JwtAuth->filtroAlfaNumerico(),$tipo_declaracion);
      $OKPeriodicidad = isset($periodicidad) && !empty($periodicidad) && preg_match($JwtAuth->filtroAlfaNumerico(),$periodicidad);
  
      $OKEjercicio = isset($ejercicio) && !empty($ejercicio) && preg_match($JwtAuth->filtroNumerico(),$ejercicio);
      $OKPeriodoInicio = isset($periodo_inicio) && !empty($periodo_inicio) && preg_match($JwtAuth->filtroFecha(),$periodo_inicio);
      $OKPeriodoFin = isset($periodo_fin) && !empty($periodo_fin) && preg_match($JwtAuth->filtroFecha(),$periodo_fin);
      $OKPeriodo = $OKPeriodoInicio && $OKPeriodoFin && ($JwtAuth->convierteFechaEpoc($periodo_fin) >= $JwtAuth->convierteFechaEpoc($periodo_inicio));

      $OKFechaPresentacion = isset($fecha_presentacion) && !empty($fecha_presentacion) && preg_match($JwtAuth->filtroFecha(),$fecha_presentacion);
      $OKMedioPresentacion = isset($medio_presentacion) && !empty($medio_presentacion) && preg_match($JwtAuth->filtroAlfaNumerico(),$medio_presentacion);
      $OKFechaVencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
      $OKVersion = isset($version) && preg_match($JwtAuth->filtroAlfaNumerico(),$version);
      $OKNumeroOperacion = isset($numero_operacion) && !empty($numero_operacion) && preg_match($JwtAuth->filtroNumerico(),$numero_operacion);
      $OKLineaCaptura = isset($linea_de_captura) && !empty($linea_de_captura) && preg_match($JwtAuth->filtroAlfaNumerico(),$linea_de_captura);
      $OKListaPagar = isset($declaraciones_lista_pagar) && is_array($declaraciones_lista_pagar) && count($declaraciones_lista_pagar) > 0;
      $OKObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

      if ($OKFechaCont && $OKTipoDeclaracion && $OKPeriodicidad && $OKEjercicio && $OKPeriodo && $OKFechaPresentacion && 
        $OKMedioPresentacion && $OKFechaVencimiento && $OKVersion && $OKNumeroOperacion && $OKLineaCaptura && $OKObservacion) {
          
        $queryDeclaraciones = DeclaracionesFederalesModelo::join('main_empresas AS emp', 'cont_reg_fisc_declaraciones_imp_federales.declaracion_empresa', 'emp.id')
        ->where([
          'cont_reg_fisc_declaraciones_imp_federales.declaracion_token' => $declaracion_token,
          'cont_reg_fisc_declaraciones_imp_federales.declaracion_status' => TRUE,
          'emp.empresa_token' => $empresa
        ])
        ->get();
        
        foreach ($queryDeclaraciones as $vDec) {
          $decFedID = DB::table("cont_reg_fisc_declaraciones_imp_federales")->where("declaracion_token",$vDec->declaracion_token)->value("id");
          //da_te_default_timezone_set('UTC');
          $folio_imp_fed = 'DEC-IMPFED-'.$JwtAuth->generarFolio($vDec->declaracion_folio_interior).(!is_null($vDec->declaracion_subfolio) ? '-'.$vDec->declaracion_subfolio : '');
          $filepath = "$vDec->root_tkn/0005-cnt/declaraciones/impuestos_federales/$vDec->declaracion_fecha_registro-$folio_imp_fed/anexos/";
          $declaracion_moneda = $vDec->declaracion_moneda;

          $queryUpdateAportMain = DB::table('cont_reg_fisc_declaraciones_imp_federales')
          ->where('declaracion_token',$vDec->declaracion_token)
          ->limit(1)->update(array(
            "declaracion_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
            "declaracion_tipo" => $tipo_declaracion,
            "declaracion_periodicidad" => $periodicidad,
            "declaracion_ejercicio" => $ejercicio,
            "declaracion_periodo_inicio" => $JwtAuth->convierteFechaEpoc($periodo_inicio),
            "declaracion_periodo_fin" => $JwtAuth->convierteFechaEpoc($periodo_fin),
            "declaracion_fecha_presentacion" => $JwtAuth->convierteFechaEpoc($fecha_presentacion),
            "declaracion_medio_presentacion" => $medio_presentacion,
            "declaracion_fecha_vencimiento" => $JwtAuth->convierteFechaEpoc($fecha_vencimiento),
            "declaracion_version" => $version,
            "declaracion_numero_operacion" => $numero_operacion,
            "declaracion_linea_de_captura" => $linea_de_captura,
            "declaracion_observaciones" => $JwtAuth->encriptar($observaciones),
          ));

          if (!is_null($declaraciones_lista_eliminar) && count($declaraciones_lista_eliminar) > 0) {
            foreach ($declaraciones_lista_eliminar as $e_del_v => $e_del) {
              $dec_desglose_token = $e_del["dec_desglose_token"];
              DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose")
              ->where("dec_desglose_token",$dec_desglose_token)
              ->limit(1)->delete();
            }
          }

          if (!is_null($declaraciones_lista_pagar) && count($declaraciones_lista_pagar) > 0) {
            foreach ($declaraciones_lista_pagar as $e_dec_v => $e_dec_l) {
              $concepto_pago_token = $e_dec_l["concepto_pago_token"];
              $impuesto_id = DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$concepto_pago_token)->value("id");
              $importe_a_favor = $e_dec_l["importe_a_favor"] ?? 0;
              $a_cargo = $e_dec_l["a_cargo"] ?? 0;
              $actualizaciones = $e_dec_l["actualizaciones"] ?? 0;
              $recargos = $e_dec_l["recargos"] ?? 0;
              $otros_cargos = $e_dec_l["otros_cargos"] ?? 0;
              $otros_abonos = $e_dec_l["otros_abonos"] ?? 0;
              $cantidad_a_pagar = $e_dec_l["cantidad_a_pagar"] ?? 0;
              
              $dec_desglose_new_token = $JwtAuth->encriptarToken($impuesto_id.$a_cargo.$recargos.$otros_abonos.$cantidad_a_pagar);

              DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose")
              ->insert(array(
                "declaracion" => $decFedID,
                "dec_desglose_token" => $dec_desglose_new_token,
                "dec_desglose_impuesto" => $impuesto_id,
                "dec_desglose_impuesto_importe_a_favor" => $importe_a_favor,
                "dec_desglose_impuesto_a_cargo" => $a_cargo,
                "dec_desglose_impuesto_actualizaciones" => $actualizaciones,
                "dec_desglose_impuesto_recargos" => $recargos,
                "dec_desglose_impuesto_otros_cargos" => $otros_cargos,
                "dec_desglose_impuesto_otros_abonos" => $otros_abonos,
                "dec_desglose_impuesto_cantidad_a_pagar" => $cantidad_a_pagar,
              ));
            }
          }
          
          if (!is_null($anexos_lista_eliminar) && count($anexos_lista_eliminar) > 0) {
            foreach ($anexos_lista_eliminar as $anex_del_v => $anex_del) {
              $token_documento = $anex_del['token_documento'];
              $nombre_documento = $anex_del['name_documento'];
              $rutaCompleta = "/public/root/".$filepath.$nombre_documento;
              if (Storage::exists($rutaCompleta)) {
                  Storage::delete($rutaCompleta);
              }
              DB::table("sos_documentos")->where("token_documento",$token_documento)->limit(1)->delete();
            }
          }

          if (!empty($_FILES['documentos_evidencia'])) {
            $evidencias = $_FILES["documentos_evidencia"];
            //return response()->json(['status' => 'error','code' => 200,'message' => json_decode($evidencias]));
            //return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1']);
            $string_name_evid = json_encode($_FILES["documentos_evidencia"]["name"]);
            if (count(json_decode($string_name_evid)) != 0) {
              $evidencia_nombre = json_decode($string_name_evid);
              for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                $temporal = $evidencias["tmp_name"][$doc];
                $doc_name = $evidencias["name"][$doc];
                Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);
                $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%DEC-IMP-FED-EVID%'");
                $token_documento = $JwtAuth->encriptarToken($decFedID,$empresa,$doc_name,$select_folio_doc[0]->folio);
                $insertDocSoli = DB::table("sos_documentos")->insert(
                  array(
                    "token_documento" => $token_documento,
                    "fecha_carga" => time(),
                    "modulo" => "pagos",
                    "folio_modulo" => "DEC-IMP-FED-EVID".$select_folio_doc[0]->folio,
                    "tipo_documento" => "an",
                    "nombre_documento" => $JwtAuth->encriptar($doc_name),
                    "declaracion_imp_federales" => $decFedID,
                    "status_documento" => TRUE,
                  )
                );
              }
            }
          }

          $dataMensaje = array(
            "status" => "success",
            "code" => 200,
            "message" => "La declaración de impuestos federales con folio $folio_imp_fed ha sido actualizada satisfactoriamente"
          );
        }
      } else {
        $mensaje_error = "";
        if (!$OKFechaCont) $mensaje_error = "Error en fecha de contabilización, por favor verifique su información o comuniquese a soporte"; 
        if (!$OKTipoDeclaracion) $mensaje_error = "Error en tipo de declaración, por favor verifique su información o comuniquese a soporte";
        if (!$OKPeriodicidad) $mensaje_error = "Error en periodicidad, por favor verifique su información o comuniquese a soporte";
        if (!$OKEjercicio) $mensaje_error = "Error en ejercicio, por favor verifique su información o comuniquese a soporte";
        if (!$OKPeriodo) $mensaje_error = "Error en periodo, por favor verifique su información o comuniquese a soporte";
        if (!$OKFechaPresentacion) $mensaje_error = "Error en fecha de presentación, por favor verifique su información o comuniquese a soporte"; 
        if (!$OKMedioPresentacion) $mensaje_error = "Error en medio de presentación, por favor verifique su información o comuniquese a soporte";
        if (!$OKFechaVencimiento) $mensaje_error = "Error en fecha de vencimiento, por favor verifique su información o comuniquese a soporte";
        if (!$OKVersion) $mensaje_error = "Error en version, por favor verifique su información o comuniquese a soporte";
        if (!$OKNumeroOperacion) $mensaje_error = "Error en número de operación, por favor verifique su información o comuniquese a soporte";
        if (!$OKLineaCaptura) $mensaje_error = "Error en línea de captura, por favor verifique su información o comuniquese a soporte";
        //if (!$OKListaPagar) $mensaje_error = "Error en la lista de impuestos que declara, por favor verifique su información o comuniquese a soporte";
        if (!$OKObservacion) $mensaje_error = "Error en observación, por favor verifique su información o comuniquese a soporte";
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteDeclaracion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'declaracion_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $declaracion_token = $request->input('declaracion_token');
      
      if ($declaracion_token) {
        $queryDeclaraciones = DeclaracionesFederalesModelo::join('main_empresas AS emp', 'cont_reg_fisc_declaraciones_imp_federales.declaracion_empresa', 'emp.id')
        ->where([
          'cont_reg_fisc_declaraciones_imp_federales.declaracion_token' => $declaracion_token,
          'cont_reg_fisc_declaraciones_imp_federales.declaracion_status' => TRUE,
          'emp.empresa_token' => $empresa
        ])
        ->get();
        
        foreach ($queryDeclaraciones as $vDec) {
          //da_te_default_timezone_set('UTC');
          $folio_imp_fed = 'DEC-IMPFED-'.$JwtAuth->generarFolio($vDec->declaracion_folio_interior).(!is_null($vDec->declaracion_subfolio) ? '-'.$vDec->declaracion_subfolio : '');
          
          $decFedPagoDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "order.declaracion_imp_federales", "=", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->count();

          if ($decFedPagoDone == 0) {
            $queryDeleteDeclaracion = DB::table('cont_reg_fisc_declaraciones_imp_federales')
            ->where('declaracion_token',$vDec->declaracion_token)->limit(1)
            ->update(array("declaracion_status" => FALSE,"declaracion_fecha_delete" => time()));

            if ($queryDeleteDeclaracion) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200, 
                'message' => "La declaración con folio $folio_imp_fed ha sido eliminada satisfactoriamente"
              );
            } else {
              $dataMensaje = array(
                'status' => 'error', 
                'code' => 200, 
                'message' => "La declaración con folio $folio_imp_fed no se puede eliminar debido a errores internos, intentelo nuevamente o comuniquese a soporte"
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error', 
              'code' => 200, 'message' => 
              "La declaración con folio $folio_imp_fed no se puede eliminar, se encuentra vinculada a pagos realizados, intentelo nuevamente o comuniquese a soporte"
            );
          }
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en declaracion seleccionada, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoDeclaracionesDeleted(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryDeclaraciones = DeclaracionesFederalesModelo::join('main_empresas AS emp', 'cont_reg_fisc_declaraciones_imp_federales.declaracion_empresa', 'emp.id')
    ->where([
      'cont_reg_fisc_declaraciones_imp_federales.declaracion_status' => FALSE, 
      'emp.empresa_token' => $empresa
    ])
    ->get();

    if ($queryDeclaraciones->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron declaraciones registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaDeclaraciones = array();
      
      foreach ($queryDeclaraciones as $vDec) {
        //da_te_default_timezone_set('UTC');
        $folio_imp_fed = 'DEC-IMPFED-'.$JwtAuth->generarFolio($vDec->declaracion_folio_interior).(!is_null($vDec->declaracion_subfolio) ? '-'.$vDec->declaracion_subfolio : '');
        $declaracion_moneda = $vDec->declaracion_moneda;
        $declaracion_moneda_decimales = $JwtAuth->getMonedaAPI($vDec->declaracion_moneda);

        $queryImpEstado = DB::table("cont_reg_fisc_declaraciones_imp_federales AS fedMain")
        ->join("fnzs_catalogos_fed_estados_municipios AS ent", "fedMain.proveedor_sat", "ent.id")
        ->where('fedMain.declaracion_token',$vDec->declaracion_token)
        ->select('ent.fed_est_mun_rfc','ent.fed_est_mun_entidad')
        ->first();

        $estado_rfc = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc : '';
        $estado_entidad = $queryImpEstado ? $JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';
        $estado_all_info = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc.' '.$JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';

        $cantidad_a_pagar = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose AS fedDes")
        ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "fedDes.declaracion", "fedMain.id")
        ->where('fedMain.declaracion_token',$vDec->declaracion_token)
        ->sum('fedDes.dec_desglose_impuesto_cantidad_a_pagar');

        $row = array(
          "declaracion_token" => $vDec->declaracion_token,
          "declaracion_folio" => $folio_imp_fed,
          "sat_rfc" => $estado_rfc,
          "sat_entidad" => $estado_entidad,
          "sat_all_info" => $estado_all_info,
          "declaracion_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vDec->declaracion_fecha_contabilizacion),
          "declaracion_tipo" => $vDec->declaracion_tipo,
          "declaracion_periodicidad" => $vDec->declaracion_periodicidad,
          "declaracion_ejercicio" => $vDec->declaracion_ejercicio,
          "declaracion_periodo_inicio" => ucfirst(Carbon::createFromTimestamp($vDec->declaracion_periodo_inicio)->locale('es')->translatedFormat('F')),
          "declaracion_periodo_fin" => ucfirst(Carbon::createFromTimestamp($vDec->declaracion_periodo_fin)->locale('es')->translatedFormat('F')),
          "declaracion_fecha_presentacion" => $JwtAuth->mostrarUnixAFechaMexico($vDec->declaracion_fecha_presentacion),
          "declaracion_medio_presentacion" => $vDec->declaracion_medio_presentacion,
          "declaracion_fecha_vencimiento" => $JwtAuth->mostrarUnixAFechaMexico($vDec->declaracion_fecha_vencimiento),
          "declaracion_version" => $vDec->declaracion_version,
          "declaracion_numero_operacion" => $vDec->declaracion_numero_operacion,
          "declaracion_linea_de_captura" => $vDec->declaracion_linea_de_captura,
          "declaracion_moneda" => $declaracion_moneda,
          "cantidad_a_pagar" => "$".number_format($cantidad_a_pagar,$declaracion_moneda_decimales,'.',',')." $declaracion_moneda",
          "declaracion_observaciones" => $JwtAuth->desencriptar($vDec->declaracion_observaciones),
          "declaracion_fecha_delete" => $JwtAuth->mostrarUnixAFechaMexico($vDec->declaracion_fecha_delete)
        );
        $listaDeclaraciones[] = $row;
      }

      $dataMensaje = array(
        "status" => "success",
        "code" => 200,
        "declaraciones" => $listaDeclaraciones
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restaurarDeclaracion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'declaracion_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $declaracion_token = $request->input('declaracion_token');
      
      if ($declaracion_token) {
        $queryDeclaraciones = DeclaracionesFederalesModelo::join('main_empresas AS emp', 'cont_reg_fisc_declaraciones_imp_federales.declaracion_empresa', 'emp.id')
        ->where([
          'cont_reg_fisc_declaraciones_imp_federales.declaracion_token' => $declaracion_token,
          'cont_reg_fisc_declaraciones_imp_federales.declaracion_status' => FALSE,
          'emp.empresa_token' => $empresa
        ])
        ->get();
        
        foreach ($queryDeclaraciones as $vDec) {
          //da_te_default_timezone_set('UTC');
          $folio_imp_fed = 'DEC-IMPFED-'.$JwtAuth->generarFolio($vDec->declaracion_folio_interior).(!is_null($vDec->declaracion_subfolio) ? '-'.$vDec->declaracion_subfolio : '');
          
          $queryRestoreDeclaracion = DB::table('cont_reg_fisc_declaraciones_imp_federales')
          ->where('declaracion_token',$vDec->declaracion_token)->limit(1)
          ->update(array(
            "declaracion_status" => TRUE,
            "declaracion_fecha_delete" => NULL
          ));

          if ($queryRestoreDeclaracion) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200, 
              'message' => "La declaración con folio $folio_imp_fed ha sido restaurada satisfactoriamente"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error', 
              'code' => 200, 
              'message' => "La declaración con folio $folio_imp_fed no se puede restaurar debido a errores internos, intentelo nuevamente o comuniquese a soporte"
            );
          }
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en declaracion seleccionada, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deletePermDeclaracion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'declaracion_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $declaracion_token = $request->input('declaracion_token');
      
      if ($declaracion_token) {
        $queryDeclaraciones = DeclaracionesFederalesModelo::join('main_empresas AS emp', 'cont_reg_fisc_declaraciones_imp_federales.declaracion_empresa', 'emp.id')
        ->where([
          'cont_reg_fisc_declaraciones_imp_federales.declaracion_token' => $declaracion_token,
          'cont_reg_fisc_declaraciones_imp_federales.declaracion_status' => FALSE,
          'emp.empresa_token' => $empresa
        ])
        ->get();
        
        foreach ($queryDeclaraciones as $vDec) {
          //da_te_default_timezone_set('UTC');
          $folio_imp_fed = 'DEC-IMPFED-'.$JwtAuth->generarFolio($vDec->declaracion_folio_interior).(!is_null($vDec->declaracion_subfolio) ? '-'.$vDec->declaracion_subfolio : '');
          
          $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "order.declaracion_imp_federales", "=", "fedMain.id")
          ->where("fedMain.declaracion_token",$vDec->declaracion_token)
          ->get();
          
          if (count($queryNominaPago) == 0) {
            $queryNominaPago = DB::table("fnzs_pagos_orden AS order")
            ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "order.declaracion_imp_federales", "=", "fedMain.id")
            ->where("fedMain.declaracion_token",$vDec->declaracion_token)
            ->limit(1)->delete();
          }

          $queryDeleteDecDocs = DB::table("sos_documentos AS docs")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "docs.declaracion_imp_federales", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->delete();

          $fecha_registro = $vDec->declaracion_fecha_registro;
          $filepath = $vDec->root_tkn."/0005-cnt/declaraciones/impuestos_federales/$fecha_registro-$folio_imp_fed/anexos";
          $rutaCompleta = "/public/root/".$filepath;
          if (Storage::exists($rutaCompleta)) {
              Storage::delete($rutaCompleta);
          }

          $queryDeleteDecDet = DB::table("cont_reg_fisc_declaraciones_imp_federales_desglose AS fedDes")
          ->join("cont_reg_fisc_declaraciones_imp_federales AS fedMain", "fedDes.declaracion", "fedMain.id")
          ->where('fedMain.declaracion_token',$vDec->declaracion_token)
          ->delete();

          $queryDeleteDeclaracion = DB::table('cont_reg_fisc_declaraciones_imp_federales')
          ->where('declaracion_token',$vDec->declaracion_token)
          ->limit(1)->delete();

          if ($queryDeleteDeclaracion) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200, 
              'message' => "La declaración con folio $folio_imp_fed ha sido eliminada satisfactoriamente"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error', 
              'code' => 200, 
              'message' => "La declaración con folio $folio_imp_fed no se puede eliminar debido a errores internos, intentelo nuevamente o comuniquese a soporte"
            );
          }
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en declaracion seleccionada, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}