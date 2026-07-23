<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\OrdenPagoModelo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VHUM_ImpuestosSobreNominaController extends Controller{
  public function registraNominaImpuestos(Request $request){
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
      'fecha_vencimiento' => 'required|string',
      'fecha_presentacion' => 'required|string',
      'estado' => 'required|string', 
      'ejercicio' => 'required|numeric',
      'periodo_inicio' => 'required|string',
      'periodo_fin' => 'required|string',
      'tipo_declaracion' => 'required|string',
      'total_remuneraciones_erogadas' => 'required|numeric', 
      'porcent_sobre_total_remuneraciones_erogadas' => 'required|numeric', 
      'complementarias_impuesto_a_cargo' => 'required|numeric', 
      'complementarias_saldo_a_favor' => 'required|numeric', 
      'impuesto_actualizado' => 'required|numeric', 
      'impuesto_descuento' => 'required|string', 
      'impuesto_recargos' => 'required|numeric', 
      'impuesto_recargos_condonados' => 'required|numeric', 
      'subsi_n_resolu_impuesto_pagar' => 'required|numeric', 
      'subsi_n_resolu_recargos' => 'required|numeric', 
      'compensa_n_resolucion' => 'required|numeric', 
      'compensa_n_resolu_recargos' => 'required|numeric', 
      'impuesto_total_a_pagar' => 'required|numeric', 
      'impuesto_saldo_a_favor' => 'required|numeric',
      'observaciones' => 'required|string', 
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos del impuesto sobre nómina no son válidos. Verifica las fechas, montos y observaciones',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $fecha_vencimiento = $request->input('fecha_vencimiento');
      $fecha_presentacion = $request->input('fecha_presentacion');
      $estado = $request->input('estado');
      $ejercicio = $request->input('ejercicio');
      $periodo_inicio = $request->input('periodo_inicio');
      $periodo_fin = $request->input('periodo_fin');
      //$fecha_pago = $request->input('fecha_pago');
      $tipo_declaracion = $request->input('tipo_declaracion');
      $total_remuneraciones_erogadas = $request->input('total_remuneraciones_erogadas');
      $porcen_sobre_total_remun_erog = $request->input('porcent_sobre_total_remuneraciones_erogadas');
      $complementarias_impuesto_a_cargo = $request->input('complementarias_impuesto_a_cargo');
      $complementarias_saldo_a_favor = $request->input('complementarias_saldo_a_favor');
      $impuesto_actualizado = $request->input('impuesto_actualizado');
      $impuesto_descuento = $request->input('impuesto_descuento');
      $impuesto_recargos = $request->input('impuesto_recargos');
      $impuesto_recargos_condonados = $request->input('impuesto_recargos_condonados');
      $subsi_n_resolu_impuesto_pagar = $request->input('subsi_n_resolu_impuesto_pagar');
      $subsi_n_resolu_recargos = $request->input('subsi_n_resolu_recargos');
      $compensa_n_resolucion = $request->input('compensa_n_resolucion');
      $compensa_n_resolu_recargos = $request->input('compensa_n_resolu_recargos');
      $impuesto_total_a_pagar = $request->input('impuesto_total_a_pagar');
      $impuesto_saldo_a_favor = $request->input('impuesto_saldo_a_favor');
      $observaciones = $request->input('observaciones');

      $OKNominaFCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $OKNominaFechaVencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
      $OKNominaFechaPresentacion = isset($fecha_presentacion) && !empty($fecha_presentacion) && preg_match($JwtAuth->filtroFecha(),$fecha_presentacion);
      $OKNominaEstado = isset($estado) && !empty($estado);
      $OKNominaEjercicio = isset($ejercicio) && !empty($ejercicio) && preg_match($JwtAuth->filtroNumerico(),$ejercicio);
      $OKNominaPeriodoInicio = isset($periodo_inicio) && !empty($periodo_inicio) && preg_match($JwtAuth->filtroFecha(),$periodo_inicio);
      $OKNominaPeriodoFin = isset($periodo_fin) && !empty($periodo_fin) && preg_match($JwtAuth->filtroFecha(),$periodo_fin);
      $OKNominaPeriodo = $OKNominaPeriodoInicio && $OKNominaPeriodoFin && ($JwtAuth->convierteFechaEpoc($periodo_fin) >= $JwtAuth->convierteFechaEpoc($periodo_inicio));
      $OKNominaTipoDeclaracion = isset($tipo_declaracion) && !empty($tipo_declaracion) && preg_match($JwtAuth->filtroAlfaNumerico(),$tipo_declaracion);
      
      $OKNominaTotalRemuneracionesErogadas = isset($total_remuneraciones_erogadas) && is_numeric($total_remuneraciones_erogadas) && preg_match($JwtAuth->filtroCostoPrecio(),$total_remuneraciones_erogadas);
      $OKNominaPorcenSobreTotalRemunErogad = isset($porcen_sobre_total_remun_erog) && is_numeric($porcen_sobre_total_remun_erog) && preg_match($JwtAuth->filtroCostoPrecio(),$porcen_sobre_total_remun_erog);
      $OKNominaComplementariasImpuestoACargo = isset($complementarias_impuesto_a_cargo) && is_numeric($complementarias_impuesto_a_cargo) && preg_match($JwtAuth->filtroCostoPrecio(),$complementarias_impuesto_a_cargo);
      $OKNominaComplementariasSaldoAFavor = isset($complementarias_saldo_a_favor) && is_numeric($complementarias_saldo_a_favor) && preg_match($JwtAuth->filtroCostoPrecio(),$complementarias_saldo_a_favor);
      $OKNominaImpuestoActualizado = isset($impuesto_actualizado) && is_numeric($impuesto_actualizado) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_actualizado);
      $OKNominaImpuestoDescuento = isset($impuesto_descuento) && is_numeric($impuesto_descuento) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_descuento);
      $OKNominaImpuestoRecargos = isset($impuesto_recargos) && is_numeric($impuesto_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_recargos);
      $OKNominaImpuestoRecargosCondonados = isset($impuesto_recargos_condonados) && is_numeric($impuesto_recargos_condonados) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_recargos_condonados);
      $OKNominaSubsiNResoluImpuestoPagar = isset($subsi_n_resolu_impuesto_pagar) && is_numeric($subsi_n_resolu_impuesto_pagar) && preg_match($JwtAuth->filtroCostoPrecio(),$subsi_n_resolu_impuesto_pagar);
      $OKNominaSubsiNResoluRecargos = isset($subsi_n_resolu_recargos) && is_numeric($subsi_n_resolu_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$subsi_n_resolu_recargos);
      $OKNominaimporteCompensaNResolucion = isset($compensa_n_resolucion) && is_numeric($compensa_n_resolucion) && preg_match($JwtAuth->filtroCostoPrecio(),$compensa_n_resolucion);
      $OKNominaimporteCompensaNResolucionRecargos = isset($compensa_n_resolu_recargos) && is_numeric($compensa_n_resolu_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$compensa_n_resolu_recargos);
      $OKNominaImpuestoTotalAPagar = isset($impuesto_total_a_pagar) && is_numeric($impuesto_total_a_pagar) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_total_a_pagar);
      $OKNominaImpuestoSaldoAFavor = isset($impuesto_saldo_a_favor) && is_numeric($impuesto_saldo_a_favor) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_saldo_a_favor);
      $OKNominaObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

      if ($OKNominaFCont && $OKNominaFechaVencimiento && $OKNominaFechaPresentacion && $OKNominaEstado && $OKNominaEjercicio && $OKNominaPeriodo && $OKNominaTipoDeclaracion && $OKNominaTotalRemuneracionesErogadas && $OKNominaPorcenSobreTotalRemunErogad && 
        $OKNominaComplementariasImpuestoACargo && $OKNominaComplementariasSaldoAFavor && $OKNominaImpuestoActualizado && $OKNominaImpuestoDescuento && $OKNominaImpuestoRecargos && $OKNominaImpuestoRecargosCondonados && 
        $OKNominaSubsiNResoluImpuestoPagar && $OKNominaSubsiNResoluRecargos && $OKNominaimporteCompensaNResolucion && $OKNominaimporteCompensaNResolucionRecargos && $OKNominaImpuestoTotalAPagar && 
        $OKNominaImpuestoSaldoAFavor && $OKNominaObservacion) {
        $fechaSistema = time();
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

        foreach ($queryEmp as $vEmp) {
          $folioSistema = DB::select("SELECT nomina.nomi_imp_folio_interior+1 AS folio,nomi_imp_subfolio FROM vhum_nominas_impuestos AS nomina JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
            JOIN teci_usuarios_catalogo AS users WHERE nomina.nomina_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? 
            ORDER BY nomina.nomi_imp_folio_interior DESC LIMIT 1",[$empresa,$usuario]);
          //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
          if (count($folioSistema) == 1) {
            if ($folioSistema[0]->folio == 1000000000) {
                $post_folio_db = DB::select("SELECT nomi_imp_subfolio FROM vhum_nominas_impuestos WHERE id = (SELECT Max(nomina.id) FROM vhum_nominas_impuestos AS nomina JOIN main_empresas AS emp 
                  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE nomina.nomina_empresa = emp.id AND emp.empresa_token = ?
                  AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$empresa,$usuario]);
                
                $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->nomi_imp_subfolio);
                $folio_nuevo = 1;
            } else {
                $post_folio = NULL;
                $folio_nuevo = $folioSistema[0]->folio;
            }
          } else {
            $post_folio = NULL;
            $folio_nuevo = 1;
          }
          $folio_nomina = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');
          $tokenImpuestosNomina = $JwtAuth->encriptarToken($ejercicio.$periodo_inicio.$periodo_fin.$observaciones.$impuesto_total_a_pagar);
          //vhum_nominas_impuestos
          DB::table("vhum_nominas_impuestos")
          ->insert(array(
            "nomi_imp_token" => $tokenImpuestosNomina,
            "nomi_imp_fecha_registro" => time(),
            "nomi_imp_folio_interior" => $folio_nuevo,
            "nomi_imp_subfolio" => $post_folio,
            "nomi_imp_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
            "nomi_imp_estado" => DB::table("fnzs_catalogos_fed_estados_municipios")->where("fed_est_mun_token", $estado)->value("id"),
            "nomi_imp_ejercicio" => $ejercicio,
            "nomi_imp_periodo_inicio" => $JwtAuth->convierteFechaEpoc($periodo_inicio),
            "nomi_imp_periodo_fin" => $JwtAuth->convierteFechaEpoc($periodo_fin),
            "nomi_imp_fecha_pago" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
            "nomi_imp_fecha_vencimiento" => $JwtAuth->convierteFechaEpoc($fecha_vencimiento),
            "nomi_imp_fecha_presentacion" => $JwtAuth->convierteFechaEpoc($fecha_presentacion),
            "nomi_imp_tipo_declaracion" => $tipo_declaracion,
            "nomi_imp_moneda" => "MXN",
            "nomi_imp_total_remuneraciones_erogadas" => $total_remuneraciones_erogadas,
            "nomi_imp_porcent_sobre_total_remuneraciones_erogadas" => $porcen_sobre_total_remun_erog,
            "nomi_imp_complementarias_impuesto_a_cargo" => $complementarias_impuesto_a_cargo,
            "nomi_imp_complementarias_saldo_a_favor" => $complementarias_saldo_a_favor,
            "nomi_imp_impuesto_actualizado" => $impuesto_actualizado,
            "nomi_imp_impuesto_descuento" => $impuesto_descuento,
            "nomi_imp_impuesto_recargos" => $impuesto_recargos,
            "nomi_imp_impuesto_recargos_condonados" => $impuesto_recargos_condonados,
            "nomi_imp_subsi_n_resolu_impuesto_pagar" => $subsi_n_resolu_impuesto_pagar,
            "nomi_imp_subsi_n_resolu_recargos" => $subsi_n_resolu_recargos,
            "nomi_imp_compensa_n_resolucion" => $compensa_n_resolucion,
            "nomi_imp_compensa_n_resolu_recargos" => $compensa_n_resolu_recargos,
            "nomi_imp_impuesto_total_a_pagar" => $impuesto_total_a_pagar,
            "nomi_imp_impuesto_saldo_a_favor" => $impuesto_saldo_a_favor,
            "observaciones" => $JwtAuth->encriptar($observaciones),
            "nomi_imp_status" => TRUE,
            //ALTER TABLE `vhum_nominas_impuestos` ADD `nomi_imp_status` BOOLEAN NOT NULL DEFAULT TRUE AFTER `observaciones`, ADD `nomi_imp_fecha_delete` VARCHAR(10) NULL AFTER `nomi_imp_status`;
            "nomina_empresa" => $vEmp->id,
          ));

          $nomina_id = DB::table("vhum_nominas_impuestos")->where("nomi_imp_token",$tokenImpuestosNomina)->value("id");
          
          //ALTER TABLE `fnzs_pagos_orden` ADD `nomina_main` INT(10) NULL AFTER `reembolso_solicitud`;
          $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

          $tknOrder = $JwtAuth->encriptarToken(time(),$folioOrden[0]->folio,$nomina_id);
          $orderpay = new OrdenPagoModelo();
          $orderpay->token_ordenPago = $tknOrder;
          $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
          $orderpay->fecha_sistema_ordenp = $fechaSistema;
          $orderpay->impuesto_sobre_nomina = $nomina_id;
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

          $fecha_sistema_ordenp = DB::table("vhum_nominas_impuestos")->where("nomi_imp_token",$tokenImpuestosNomina)->value("nomi_imp_fecha_registro");
          $filepath = $vEmp->root_tkn . "/0004-vhm/impuestos_sobre_nomina/$fecha_sistema_ordenp-$folio_nomina/anexos/";
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
                $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%IMP-NOMI-EVID%'");
                $token_documento = $JwtAuth->encriptarToken($nomina_id,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                $insertDocSoli = DB::table("sos_documentos")->insert(
                  array(
                    "token_documento" => $token_documento,
                    "fecha_carga" => time(),
                    "modulo" => "pagos",
                    "folio_modulo" => "IMP-NOMI-EVID".$select_folio_doc[0]->folio,
                    "tipo_documento" => "an",
                    "nombre_documento" => $JwtAuth->encriptar($doc_name),
                    "impuesto_sobre_nomina" => $nomina_id,
                    "status_documento" => TRUE,
                  )
                );
              }
            }
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Nomina registrada satisfactoriamente con el folio $folio_nomina"
          );
        }
      } else {
        $mensaje_error = "";
        if (!$OKNominaFCont) $mensaje_error = "Error en fecha de contabilización, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaFechaVencimiento) $mensaje_error = "Error en fecha de vencimiento, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaFechaPresentacion) $mensaje_error = "Error en fecha de presentación, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaEstado) $mensaje_error = "Error al seleccionar estado, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaEjercicio) $mensaje_error = "Error al registrar ejercicio, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaPeriodo) $mensaje_error = "Error al registrar periodo, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaTipoDeclaracion) $mensaje_error = "Error al registrar tipo de declaración, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaTotalRemuneracionesErogadas) $mensaje_error = "Error al registrar total de remuneraciones erogadas, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaPorcenSobreTotalRemunErogad) $mensaje_error = "Error al registrar % sobre el total de remuneraciones erogadas, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaComplementariasImpuestoACargo) $mensaje_error = "Error al registrar complementarias (Impuesto a cargo), intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaComplementariasSaldoAFavor) $mensaje_error = "Error al registrar complementarias (Saldo a favor), intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaImpuestoActualizado) $mensaje_error = "Error al registrar impuesto actualizado, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaImpuestoDescuento) $mensaje_error = "Error al registrar descuento, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaImpuestoRecargos) $mensaje_error = "Error al registrar recargos, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaImpuestoRecargosCondonados) $mensaje_error = "Error al registrar recargos condonados, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaSubsiNResoluImpuestoPagar) $mensaje_error = "Error al registrar Subsidio no. de resolución (Sobre el impuesto a pagar), intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaSubsiNResoluRecargos) $mensaje_error = "Error al registrar Subsidio no. de resolución (Sobre recargos (%)), intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaimporteCompensaNResolucion) $mensaje_error = "Error al registrar Compensación no. de resolución (Sobre el impuesto a pagar), intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaimporteCompensaNResolucionRecargos) $mensaje_error = "Error al registrar Compensación no. de resolución (Sobre recargos), intentelo nuevamente o comuniquese a soporte";

        if (!$OKNominaImpuestoTotalAPagar) $mensaje_error = "Error al registrar total a pagar, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaImpuestoSaldoAFavor) $mensaje_error = "Error al registrar saldo a favor, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaObservacion) $mensaje_error = "Error al registrar observaciones de nómina, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaRegNominaImpuestos(Request $request){
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
				'message' => 'Selecciona un período para consultar la lista de nóminas',
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
      
      $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
      ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
      ->where([
        'nomImp.nomi_imp_status' => TRUE,
        'emp.empresa_token' => $empresa,
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("nomImp.nomi_imp_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('nomImp.id', 'DESC')
      ->select(
        'nomImp.id AS id_isn',
        'nomImp.*'
      )
      ->get();

      if ($queryImpNomina->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron registros de impuestos sobre nómina para el período seleccionado'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $lista_imp_nomina = array();
        
        $idImpEstado = $queryImpNomina->pluck('nomi_imp_estado')->filter()->unique()->toArray();
        $impEstadoMap = DB::table('fnzs_catalogos_fed_estados_municipios')->whereIn('id', $idImpEstado)->get()->keyBy('id');

        $nomiIDImpToken = $queryImpNomina->pluck('id_isn')->filter()->unique()->toArray();

        $iSNOrdPagoMap = DB::table("fnzs_pagos_orden")
        ->whereIn('impuesto_sobre_nomina',$nomiIDImpToken)
        //->select('token_ordenPago', 'folio_ordenPago')
        ->get()->keyBy('impuesto_sobre_nomina');

        foreach ($queryImpNomina as $vImpNom) {
          $folio_nomina = $vImpNom->nomi_imp_folio_interior;
          $post_folio_nomina = $vImpNom->nomi_imp_subfolio;
          $nomi_imp_folio = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : '');
          $nomi_imp_moneda = $vImpNom->nomi_imp_moneda;
          $nomi_imp_moneda_decimales = $JwtAuth->getMonedaAPI($vImpNom->nomi_imp_moneda);
          $ejercicio = $vImpNom->nomi_imp_ejercicio;
          $periodo_inicio = $vImpNom->nomi_imp_periodo_inicio;
          $periodo_fin = $vImpNom->nomi_imp_periodo_fin;
          
          $queryImpEstado = $impEstadoMap->get($vImpNom->nomi_imp_estado);
          $estado_rfc = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc : '';
          $estado_entidad = $queryImpEstado ? $JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';
          $estado_all_info = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc.' '.$JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';

          $queryIMPNominaPagoDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
          ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();

          $totales_isn_pago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
          ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->sum('pay.monto_pago');

					$totales_isn_saldo = $vImpNom->nomi_imp_impuesto_total_a_pagar - $totales_isn_pago;

          $queryISNOrdPago = $iSNOrdPagoMap->get($vImpNom->id_isn);
          $isn_ord_pago_token = $queryISNOrdPago ? $queryISNOrdPago->token_ordenPago :'';
					$isn_ord_pago_folio = $queryISNOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryISNOrdPago->folio_ordenPago) :'';
          
          $row = array(
            "nomi_imp_token" => $vImpNom->nomi_imp_token,
            "nomi_imp_folio" => $nomi_imp_folio,
            "nomi_imp_fecha_contabilizacion" => gmdate('Y-m-d H:i:s',$vImpNom->nomi_imp_fecha_contabilizacion),
            "nomi_imp_estado_rfc" => $estado_rfc,
            "nomi_imp_estado_entidad" => $estado_entidad,
            "nomi_imp_estado_all_info" => $estado_all_info,
            "nomi_imp_ejercicio" => $ejercicio,
            "nomi_imp_periodo_inicio" => ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_inicio)->locale('es')->translatedFormat('F')),
            "nomi_imp_periodo_fin" => ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_fin)->locale('es')->translatedFormat('F')),
            "nomi_imp_fecha_vencimiento" => gmdate('Y-m-d H:i:s',$vImpNom->nomi_imp_fecha_vencimiento),
            "nomi_imp_fecha_presentacion" => gmdate('Y-m-d H:i:s',$vImpNom->nomi_imp_fecha_presentacion),
            "nomi_imp_tipo_declaracion" => $vImpNom->nomi_imp_tipo_declaracion == 'comple' ? "complementaria" : "normal",
            "nomi_imp_moneda" => "MXN",
            "nomi_imp_impuesto_total_a_pagar" => "$".number_format($vImpNom->nomi_imp_impuesto_total_a_pagar,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            'nomi_imp_pago' => "$".number_format($totales_isn_pago,$nomi_imp_moneda_decimales,'.', ',')." $nomi_imp_moneda",
            'nomi_imp_saldo' => "$".number_format($totales_isn_saldo,$nomi_imp_moneda_decimales,'.', ',')." $nomi_imp_moneda",
            'nomi_imp_ord_pago_token' => $isn_ord_pago_token,
            'nomi_imp_ord_pago_folio' => $isn_ord_pago_folio,
            "nomi_imp_habilita_carga_docs" => $queryIMPNominaPagoDone ? true : false,
            "nomi_imp_factura_doc_xml" => !is_null($vImpNom->nomi_imp_fact_xml) ? $JwtAuth->desencriptar($vImpNom->nomi_imp_fact_xml) : null,
            "nomi_imp_url_doc_xml" => !is_null($vImpNom->nomi_imp_fact_xml) ? "https://downloads.sos-mexico.com.mx/impuestos_sobre_nomina_fact_xml/$vImpNom->nomi_imp_token" : null,
            "nomi_imp_factura_doc_pdf" => !is_null($vImpNom->nomi_imp_fact_pdf) ? $JwtAuth->desencriptar($vImpNom->nomi_imp_fact_pdf) : null,
            "nomi_imp_url_doc_pdf" => !is_null($vImpNom->nomi_imp_fact_pdf) ? "https://downloads.sos-mexico.com.mx/impuestos_sobre_nomina_fact_pdf/$vImpNom->nomi_imp_token" : null,
            "nomi_imp_factura_xml" => null,
            "nomi_imp_factura_pdf" => null,
            "nomi_imp_valida_xml" => '',
            "nomi_imp_cfdi_comprobante" => [],
            "nomi_imp_cfdi_emisor" => [],
            "nomi_imp_cfdi_receptor" => [],
            "nomi_imp_cfdi_conceptos" => [],
            "nomi_imp_cfdi_complemento" => [],
            "puede_eliminar" => !$queryIMPNominaPagoDone ? true : false,
          );
          $lista_imp_nomina[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'isn_lista' => $lista_imp_nomina
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function nominaImpuestosSeguimientoOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'nomi_imp_token' => 'required|string',
      'nomi_imp_ord_pago_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Selecciona el impuesto sobre nómina y la orden de pago para ver el seguimiento',
				'errors' => $validate->errors()
			);
    } else {
			$nomi_imp_token = $request->input('nomi_imp_token');
			$nomi_imp_ord_pago_token = $request->input('nomi_imp_ord_pago_token');
      
      $queryISNOrdenPago = DB::table("vhum_nominas_impuestos AS nomImp")
      ->join("fnzs_pagos_orden AS order", "nomImp.id", "=", "order.impuesto_sobre_nomina")
      ->join("main_empresas AS emp", "nomImp.nomina_empresa", "=", "emp.id")
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'nomImp.nomi_imp_status' => TRUE,
        'nomImp.nomi_imp_token' => $nomi_imp_token,
        'order.token_ordenPago' => $nomi_imp_ord_pago_token,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();
      
      if ($queryISNOrdenPago->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontró el impuesto sobre nómina o la orden de pago seleccionada'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $orden_pago_isn = array();
        $pagos_realizados_isn = array();

				foreach ($queryISNOrdenPago as $rOrdPag) {
					//da_te_default_timezone_set($rOrdPag->zona_horaria);
          $folio_nomina = $rOrdPag->nomi_imp_folio_interior;
          $post_folio_nomina = $rOrdPag->nomi_imp_subfolio;
					$autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;
					$fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? gmdate('Y-m-d H:i:s', $rOrdPag->fecha_autorizacion_pay) : "---";
					$status_pay_bool = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? true : false;

					$orden_emisor_emp = $rOrdPag->abrev_nombre;

          $importe_total_anticipo = 0;
					$importe_total_inicial = 0;
					$orden_moneda_inicial_name = $rOrdPag->nomi_imp_moneda;
					$orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomi_imp_moneda);

					$importe_autorizado_inicial = 0;
					$orden_moneda_autorizado_inicial_tkn = $rOrdPag->nomi_imp_moneda;
					$orden_moneda_autorizado_inicial_name = $rOrdPag->nomi_imp_moneda;
					$orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomi_imp_moneda);

					$importe_autorizado_final = 0;
					$orden_moneda_autorizado_final_name = $rOrdPag->nomi_imp_moneda;
					$orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($rOrdPag->nomi_imp_moneda);
          
          $importe_total_inicial = $rOrdPag->nomi_imp_impuesto_total_a_pagar;
          $importe_autorizado_inicial = $rOrdPag->nomi_imp_impuesto_total_a_pagar;
          $importe_autorizado_final = $rOrdPag->nomi_imp_impuesto_total_a_pagar;

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
            "fecha_contabilizacion_doc_anterior" => gmdate('Y-m-d H:i:s',$rOrdPag->doc_anterior_fecha_contabilizacion),
            "fecha_contabilizacion_orden_pago" => $rOrdPag->fecha_contabilizacion_ordenPago ? gmdate('Y-m-d H:i:s',$rOrdPag->fecha_contabilizacion_ordenPago) : '',
            "fecha_registro" => gmdate('Y-m-d H:i:s', $rOrdPag->fecha_sistema_ordenp),
            "orden_bloqueada" => $rOrdPag->orden_bloqueada ? true : false,
            "autorizacion_pay" => $autorizacion_pay,
            "autorizacion_pay_translate" => $autorizacion_pay ? 'yes_auth' : 'not_auth',
            "autorizacion_pay_text" => "",
            "fecha_autorizacion_pay" => $fecha_autorizacion_pay,
            "factura_relacionada_typo" => "nominas",
            "factura_relacionada_token" => $rOrdPag->nomi_imp_token,
            "factura_relacionada_string" => 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : ''),
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
          $orden_pago_isn[] = $row_ordenPay;
				}

        $pagos_realizados_isn = $JwtAuth->pagosDoneBYOrdenDesglose($nomi_imp_ord_pago_token,$empresa,$usuario);

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'seguimiento_orden_pago' => $orden_pago_isn,
          'pagos_realizados' => $pagos_realizados_isn,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function desgloseNominaImpuestos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'nomi_imp_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Selecciona el impuesto sobre nómina para consultar el desglose',
				'errors' => $validate->errors()
			);
    } else {
      $nomi_imp_token = $request->input('nomi_imp_token');
      
      $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
      ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
      ->where([
        'nomImp.nomi_imp_token' => $nomi_imp_token,
        'nomImp.nomi_imp_status' => TRUE,
        'emp.empresa_token' => $empresa,
      ])
      ->get();
      
      if ($queryImpNomina->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontró el registro de impuestos sobre nómina seleccionado'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $lista_imp_nomina = array();

        foreach ($queryImpNomina as $vImpNom) {
          $folio_nomina = $vImpNom->nomi_imp_folio_interior;
          $post_folio_nomina = $vImpNom->nomi_imp_subfolio;
          $nomi_imp_folio = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : '');
          $nomi_imp_moneda = $vImpNom->nomi_imp_moneda;
          $nomi_imp_moneda_decimales = $JwtAuth->getMonedaAPI($vImpNom->nomi_imp_moneda);
          $ejercicio = $vImpNom->nomi_imp_ejercicio;
          $periodoCarbonI = ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_inicio)->locale('es')->translatedFormat('F'));
          $periodoCarbonF = ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_fin)->locale('es')->translatedFormat('F'));
          
          $queryImpEstado = DB::table("vhum_nominas_impuestos AS nomImp")
          ->join("fnzs_catalogos_fed_estados_municipios AS ent", "nomImp.nomi_imp_estado", "ent.id")
          ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->select('ent.fed_est_mun_token','ent.fed_est_mun_rfc','ent.fed_est_mun_entidad')
          ->first();

          $estado_token = $queryImpEstado ? $queryImpEstado->fed_est_mun_token : '';
          $estado_rfc = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc : '';
          $estado_entidad = $queryImpEstado ? $JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';
          $estado_name = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc.' '.$JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';

          $queryISNPago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_nominas_impuestos AS isn", "order.impuesto_sobre_nomina", "=", "isn.id")
          ->where('isn.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->count();
          
          $isnAnexos = array();
          $queryDocsISN = DB::table("sos_documentos AS docs")
          ->join("vhum_nominas_impuestos AS isn", "docs.impuesto_sobre_nomina", "=", "isn.id")
          ->where([
            "docs.status_documento" => TRUE,
            "isn.nomi_imp_token" => $vImpNom->nomi_imp_token
          ])
          ->get();

          foreach ($queryDocsISN as $xDoc) {
            $nombre = $JwtAuth->desencriptar($xDoc->nombre_documento);
            $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

            $rowXML = array(
              "token_documento" => $xDoc->token_documento,
              "tipo_documental" => $xDoc->tipo_documento,
              "extension" => $extension,
              "name_documento" => $nombre,
              "url" => "https://downloads.sos-mexico.com.mx/impuestos_sobre_nomina/$nomi_imp_folio/$xDoc->token_documento",
              "eliminacion_proceso" => false
            );
            $isnAnexos[] = $rowXML;
          }

          $row = array(
            "nomi_imp_token" => $vImpNom->nomi_imp_token,
            "nomi_imp_folio" => $nomi_imp_folio,
            "nomi_imp_fecha_contabilizacion_edit" => date('Y-m-d',$vImpNom->nomi_imp_fecha_contabilizacion),
            "nomi_imp_fecha_contabilizacion" => gmdate('Y-m-d H:i:s',$vImpNom->nomi_imp_fecha_contabilizacion),
            "nomi_imp_estado_token" => $estado_token,
            "nomi_imp_estado_rfc" => $estado_rfc,
            "nomi_imp_estado_entidad" => $estado_entidad,
            "nomi_imp_estado_name" => $estado_name,

            "nomi_imp_ejercicio_simple" => $ejercicio,
            "nomi_imp_periodo" => $periodoCarbonI == $periodoCarbonF ? $periodoCarbonF : $periodoCarbonI." - ".$periodoCarbonF,

            "nomi_imp_periodo_inicio_edit" => date('Y-m-d',$vImpNom->nomi_imp_periodo_inicio),
            "nomi_imp_periodo_fin_edit" => date('Y-m-d',$vImpNom->nomi_imp_periodo_fin),
            "nomi_imp_fecha_vencimiento_edit" => date('Y-m-d',$vImpNom->nomi_imp_fecha_vencimiento),
            "nomi_imp_fecha_vencimiento" => gmdate('Y-m-d H:i:s',$vImpNom->nomi_imp_fecha_vencimiento),
            "nomi_imp_fecha_presentacion_edit" => date('Y-m-d',$vImpNom->nomi_imp_fecha_presentacion),
            "nomi_imp_fecha_presentacion" => gmdate('Y-m-d H:i:s',$vImpNom->nomi_imp_fecha_presentacion),
            "nomi_imp_tipo_declaracion" => $vImpNom->nomi_imp_tipo_declaracion == 'comple' ? "Complementaria" : "Normal",
            
            "nomi_imp_total_remuneraciones_erogadas" => number_format($vImpNom->nomi_imp_total_remuneraciones_erogadas,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_porcent_sobre_total_remuneraciones_erogadas" => number_format($vImpNom->nomi_imp_porcent_sobre_total_remuneraciones_erogadas,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_complementarias_impuesto_a_cargo" => number_format($vImpNom->nomi_imp_complementarias_impuesto_a_cargo,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_complementarias_saldo_a_favor" => number_format($vImpNom->nomi_imp_complementarias_saldo_a_favor,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_actualizado" => number_format($vImpNom->nomi_imp_impuesto_actualizado,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_descuento" => number_format($vImpNom->nomi_imp_impuesto_descuento,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_recargos" => number_format($vImpNom->nomi_imp_impuesto_recargos,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_recargos_condonados" => number_format($vImpNom->nomi_imp_impuesto_recargos_condonados,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_subsi_n_resolu_impuesto_pagar" => number_format($vImpNom->nomi_imp_subsi_n_resolu_impuesto_pagar,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_subsi_n_resolu_recargos" => number_format($vImpNom->nomi_imp_subsi_n_resolu_recargos,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_compensa_n_resolucion" => number_format($vImpNom->nomi_imp_compensa_n_resolucion,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_compensa_n_resolu_recargos" => number_format($vImpNom->nomi_imp_compensa_n_resolu_recargos,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_total_a_pagar" => number_format($vImpNom->nomi_imp_impuesto_total_a_pagar,$nomi_imp_moneda_decimales,'.',''),
            "nomi_imp_impuesto_saldo_a_favor" => number_format($vImpNom->nomi_imp_impuesto_saldo_a_favor,$nomi_imp_moneda_decimales,'.',''),
            
            "nomi_imp_total_remuneraciones_erogadas_format" => "$".number_format($vImpNom->nomi_imp_total_remuneraciones_erogadas,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_porcent_sobre_total_remuneraciones_erogadas_format" => "$".number_format($vImpNom->nomi_imp_porcent_sobre_total_remuneraciones_erogadas,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_complementarias_impuesto_a_cargo_format" => "$".number_format($vImpNom->nomi_imp_complementarias_impuesto_a_cargo,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_complementarias_saldo_a_favor_format" => "$".number_format($vImpNom->nomi_imp_complementarias_saldo_a_favor,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_actualizado_format" => "$".number_format($vImpNom->nomi_imp_impuesto_actualizado,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_descuento_format" => "$".number_format($vImpNom->nomi_imp_impuesto_descuento,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_recargos_format" => "$".number_format($vImpNom->nomi_imp_impuesto_recargos,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_recargos_condonados_format" => "$".number_format($vImpNom->nomi_imp_impuesto_recargos_condonados,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_subsi_n_resolu_impuesto_pagar_format" => "$".number_format($vImpNom->nomi_imp_subsi_n_resolu_impuesto_pagar,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_subsi_n_resolu_recargos_format" => "$".number_format($vImpNom->nomi_imp_subsi_n_resolu_recargos,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_compensa_n_resolucion_format" => "$".number_format($vImpNom->nomi_imp_compensa_n_resolucion,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_compensa_n_resolu_recargos_format" => "$".number_format($vImpNom->nomi_imp_compensa_n_resolu_recargos,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_total_a_pagar_format" => "$".number_format($vImpNom->nomi_imp_impuesto_total_a_pagar,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            "nomi_imp_impuesto_saldo_a_favor_format" => "$".number_format($vImpNom->nomi_imp_impuesto_saldo_a_favor,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
            
            "observaciones" => $JwtAuth->desencriptar($vImpNom->observaciones),
            "isnAnexos" => $isnAnexos,
            'vinculacion_a_pagos' => $queryISNPago > 0 ? true : false
          );
          $lista_imp_nomina[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'isn_desglose' => $lista_imp_nomina
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function nominaImpuestosCargaCFDIS(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'nomi_imp_token' => 'required|string',
      'isn' => 'required|array',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Selecciona el impuesto sobre nómina y adjunta los archivos CFDI (XML y PDF)',
				'errors' => $validate->errors()
			);
    } else {
		  $nomi_imp_token = $request->input('nomi_imp_token');
		  $isn = $request->input('isn');

      $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
      ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
      ->where([
        'nomImp.nomi_imp_token' => $nomi_imp_token,
        'nomImp.nomi_imp_status' => TRUE,
        'emp.empresa_token' => $empresa,
      ])
      ->get();
      
      if ($queryImpNomina->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontró el registro de impuestos sobre nómina para cargar los CFDI'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        foreach ($queryImpNomina as $vIsn) {
          $isn_id = DB::table('vhum_nominas_impuestos')->where("nomi_imp_token", $vIsn->nomi_imp_token)->value("id");
          $folio_nomina = $vIsn->nomi_imp_folio_interior;
          $post_folio_nomina = $vIsn->nomi_imp_subfolio;
          $folio_interior = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : '');
          $count_isn = 0;
          
          foreach ($isn as $r_nomina => $rNomi) {
            $archivo_xml = $request->file("isn.$r_nomina.nomi_imp_factura_xml");
            $archivo_pdf = $request->file("isn.$r_nomina.nomi_imp_factura_pdf");
  
            $nomi_imp_cfdi_comprobante = $rNomi["nomi_imp_cfdi_comprobante"];
            $nomi_imp_cfdi_emisor = $rNomi["nomi_imp_cfdi_emisor"];
            $nomi_imp_cfdi_receptor = $rNomi["nomi_imp_cfdi_receptor"];
            $nomi_imp_cfdi_conceptos = $rNomi["nomi_imp_cfdi_conceptos"];
            $nomi_imp_cfdi_complemento = $rNomi["nomi_imp_cfdi_complemento"];
            
            $cfdi_comprobante_fecha_contabilizacion = '';
            $cfdi_comprobante_version = '';
            $cfdi_comprobante_serie = '';
            $cfdi_comprobante_folio = '';
            $cfdi_comprobante_fecha = '';
            $cfdi_comprobante_sello = '';
            $cfdi_comprobante_forma_de_pago = '';
            $cfdi_comprobante_no_de_certificado = '';
            $cfdi_comprobante_certificado = '';
            $cfdi_comprobante_subtotal = '';
            $cfdi_comprobante_descuento = '';
            $cfdi_comprobante_moneda = '';
            $cfdi_comprobante_tipo_de_cambio = '';
            $cfdi_comprobante_total = '';
            $cfdi_comprobante_confirmacion = '';
            $cfdi_comprobante_tipo_de_comprobante = '';
            $cfdi_comprobante_metodo_de_pago = '';
            $cfdi_comprobante_lugar_de_expedicion = '';
  
            $cfdi_complementoVersion = '';
            $cfdi_complementoUUID = '';
            $cfdi_complementoFechaTimbrado = '';
            $cfdi_complementoRfcProvCertif = '';
            $cfdi_complementoNoCertificadoSAT = '';
            $cfdi_complementoSelloCFD = '';
            $cfdi_complementoSelloSAT = '';
  
            $data_comprobante = json_decode($nomi_imp_cfdi_comprobante, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data_comprobante)) {
  
              foreach ($data_comprobante as $vComp) {
                $cfdi_comprobante_fecha = $vComp["Fecha"];
                $cfdi_comprobante_version = $vComp["Version"];
                $cfdi_comprobante_serie = $vComp["Serie"];
                $cfdi_comprobante_folio = $vComp["Folio"];
                $cfdi_comprobante_fecha = $vComp["Fecha"];
                $cfdi_comprobante_sello = $vComp["Sello"];
                $cfdi_comprobante_forma_de_pago = $vComp["FormaDePago"];
                $cfdi_comprobante_no_de_certificado = $vComp["NoDeCertificado"];
                $cfdi_comprobante_certificado = $vComp["Certificado"];
                $cfdi_comprobante_subtotal = $vComp["Subtotal"];
                $cfdi_comprobante_descuento = $vComp["Descuento"];
                $cfdi_comprobante_moneda = $vComp["Moneda"];
                $cfdi_comprobante_tipo_de_cambio = $vComp["TipoDeCambio"];
                $cfdi_comprobante_total = $vComp["Total"];
                $cfdi_comprobante_confirmacion = $vComp["Confirmacion"];
                $cfdi_comprobante_tipo_de_comprobante = $vComp["TipoDeComprobante"];
                $cfdi_comprobante_metodo_de_pago = $vComp["MetodoDePago"];
                $cfdi_comprobante_lugar_de_expedicion = $vComp["LugarDeExpedición"];
              }
  
              //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.CFDI2']);
              $cfdi_emisor_rfc = '';
              $cfdi_emisor_nombre = '';
              $cfdi_emisor_regimen_fiscal = '';
              $data_emisor = json_decode($nomi_imp_cfdi_emisor, true);
              foreach ($data_emisor as $CFDIe) {
                $cfdi_emisor_rfc = $CFDIe["EmisorRfc"];
                $cfdi_emisor_nombre = $CFDIe["EmisorNombre"];
                $cfdi_emisor_regimen_fiscal = $CFDIe["EmisorRegimenFiscal"];
              }
  
              $cfdi_receptor_rfc = '';
              $cfdi_receptor_domicilio_fiscal = '';
              $cfdi_receptor_regimen_fiscal = '';
              $cfdi_receptor_uso_del_cfdi = '';
              $data_receptor = json_decode($nomi_imp_cfdi_receptor, true);
              foreach ($data_receptor as $CFDIReceptor) {
                $cfdi_receptor_rfc = $CFDIReceptor["ReceptorRfc"];
                $cfdi_receptor_domicilio_fiscal = $CFDIReceptor["ReceptorDomicilioFiscal"];
                $cfdi_receptor_regimen_fiscal = $CFDIReceptor["ReceptorRegimenFiscal"];
                $cfdi_receptor_uso_del_cfdi = $CFDIReceptor["ReceptorUsoCFDI"];
              }
  
              $data_complemento = json_decode($nomi_imp_cfdi_complemento, true);
              foreach ($data_complemento as $vComplemento) {
                $cfdi_complementoVersion = $vComplemento["Version"];
                $cfdi_complementoUUID = $vComplemento["UUID"];
                $cfdi_complementoFechaTimbrado = $vComplemento["FechaTimbrado"];
                $cfdi_complementoRfcProvCertif = $vComplemento["RfcProvCertif"];
                $cfdi_complementoNoCertificadoSAT = $vComplemento["NoCertificadoSAT"];
                $cfdi_complementoSelloCFD = $vComplemento["SelloCFD"];
                $cfdi_complementoSelloSAT = $vComplemento["SelloSAT"];
              }
  
              //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$cfdi_comprobante_version]);
              if ($cfdi_comprobante_version != '') {
                $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$isn_id.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
                $insertCFDISN = DB::table('cfdi_comprobantes_fiscales')//cfdi__estructura
                ->insert(array(
                  "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
                  "origen_proceso" => "isn",
                  //"isn_vinculado" => $isn_id,
                  "cfdi_comprobante_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($cfdi_comprobante_fecha_contabilizacion),
                  "cfdi_comprobante_version" => $cfdi_comprobante_version,	
                  "cfdi_comprobante_serie" => $cfdi_comprobante_serie,	
                  "cfdi_comprobante_folio" => $cfdi_comprobante_folio,	
                  "cfdi_comprobante_fecha" => $cfdi_comprobante_fecha,
                  "cfdi_comprobante_sello" => $cfdi_comprobante_sello,	
                  "cfdi_comprobante_forma_de_pago" => $cfdi_comprobante_forma_de_pago,
                  "cfdi_comprobante_no_de_certificado" => $cfdi_comprobante_no_de_certificado,	
                  "cfdi_comprobante_certificado" => $cfdi_comprobante_certificado,	
                  "cfdi_comprobante_subtotal" => $cfdi_comprobante_subtotal,	
                  "cfdi_comprobante_descuento" => $cfdi_comprobante_descuento,	
                  "cfdi_comprobante_moneda" => $cfdi_comprobante_moneda,	
                  "cfdi_comprobante_tipo_de_cambio" => $cfdi_comprobante_tipo_de_cambio,	
                  "cfdi_comprobante_total" => $cfdi_comprobante_total,
                  "cfdi_comprobante_confirmacion" => $cfdi_comprobante_confirmacion,
                  "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,	
                  "cfdi_comprobante_metodo_de_pago" => $cfdi_comprobante_metodo_de_pago,	
                  "cfdi_comprobante_lugar_de_expedicion" => $cfdi_comprobante_lugar_de_expedicion,
  
                  "cfdi_emisor_rfc" => $cfdi_emisor_rfc,	
                  "cfdi_emisor_nombre" => $cfdi_emisor_nombre,	
                  "cfdi_emisor_regimen_fiscal" => $cfdi_emisor_regimen_fiscal,
  
                  "cfdi_receptor_rfc" => $cfdi_receptor_rfc,
                  "cfdi_receptor_domicilio_fiscal" => $cfdi_receptor_domicilio_fiscal,
                  "cfdi_receptor_regimen_fiscal" => $cfdi_receptor_regimen_fiscal,
                  "cfdi_receptor_uso_del_cfdi" => $cfdi_receptor_uso_del_cfdi,
                  
                  "cfdi_complementoVersion" => $cfdi_complementoVersion,	
                  "cfdi_complementoUUID" => $cfdi_complementoUUID,	
                  "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,	
                  "cfdi_complementoRfcProvCertif" => $cfdi_complementoRfcProvCertif,
                  "cfdi_complementoNoCertificadoSAT" => $cfdi_complementoNoCertificadoSAT,	
                  "cfdi_complementoSelloCFD" => $cfdi_complementoSelloCFD,	
                  "cfdi_complementoSelloSAT" => $cfdi_complementoSelloSAT,	
                ));
  
                $comprobante_fiscal_reg = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");
                $insertCFDIVincBuy = DB::table('cfdi_vinculacion_isn')//cfdi__estructura
                ->insert(array(
                  "comprobante_fiscal" => $comprobante_fiscal_reg,
                  "isn_vinculado" => $isn_id,
                ));
  
                $data_conceptos = json_decode($nomi_imp_cfdi_conceptos, true);
                for ($lrdc = 0; $lrdc < count($data_conceptos); $lrdc++) {
                  $uuid_cfdi_detalle = Str::uuid()->toString();
                  $insertConceptCFDINominas = DB::table('cfdi_comprobantes_conceptos')
                  ->insert(array(
                    "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                    "comprobante_fiscal" => $comprobante_fiscal_reg, 
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
              //return response()->json(['status' => 'error','code' => 200,'message' => 'reem true5.3 '.$cfdi_comprobante_version]);
            }
            $filepath = $vIsn->root_tkn."/0004-vhm/impuestos_sobre_nomina/$folio_interior";
            
            if ($archivo_xml) {
              $nombre_original = $archivo_xml->getClientOriginalName();
              $ext_doc = $archivo_xml->getClientOriginalExtension();
  
              $documento_crypt = $JwtAuth->encriptar($nombre_original);
              $token_documento = $JwtAuth->encriptarToken($isn_id, $ext_doc, $nombre_original);
  
              $insertDocSoli = DB::table("sos_documentos")->insert([
                "token_documento" => $token_documento,
                "fecha_carga" => time(),
                "modulo" => "reembolsos",
                "folio_modulo" => "NOMINA-CFDI-XML",
                "tipo_documento" => "xml",
                "nombre_documento" => $documento_crypt,
                "extension_documento" => $ext_doc,
                "impuesto_sobre_nomina_cfdi" => $isn_id,
                "status_documento" => true,
              ]);
  
              if ($insertDocSoli) {
                DB::table('vhum_nominas_impuestos')->where("nomi_imp_token", $vIsn->nomi_imp_token)
                ->limit(1)->update(array("nomi_imp_fact_xml" => $documento_crypt));
  
                $archivo_xml->storeAs("public/root/$filepath", $nombre_original);
              }
            }
  
            if ($archivo_pdf) {
              $nombre_original = $archivo_pdf->getClientOriginalName();
              $ext_doc = $archivo_pdf->getClientOriginalExtension();
  
              $documento_crypt = $JwtAuth->encriptar($nombre_original);
              $token_documento = $JwtAuth->encriptarToken($isn_id, $ext_doc, $nombre_original);
  
              $insertDocSoli = DB::table("sos_documentos")->insert([
                "token_documento" => $token_documento,
                "fecha_carga" => time(),
                "modulo" => "reembolsos",
                "folio_modulo" => "NOMINA-CFDI-PDF",
                "tipo_documento" => "pdf",
                "nombre_documento" => $documento_crypt,
                "extension_documento" => $ext_doc,
                "impuesto_sobre_nomina_cfdi" => $isn_id,
                "status_documento" => true,
              ]);
  
              if ($insertDocSoli) {
                DB::table('vhum_nominas_impuestos')->where("nomi_imp_token", $vIsn->nomi_imp_token)
                ->limit(1)->update(array("nomi_imp_fact_pdf" => $documento_crypt));
                $archivo_pdf->storeAs("public/root/$filepath", $nombre_original);
              }
            }
            ++$count_isn;
            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$token_nomina_recibo]);
          }
  
          if ($count_isn == count($isn)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'CFDIs del impuesto sobre nómina han sido cargados correctamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error al cargar los CFDIs del impuesto sobre nómina, intente nuevamente o comuniquese a soporte'
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaNominaImpuestos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'nomi_imp_token' => 'required|string',
      'fecha_contabilizacion' => 'required|string', 
      'fecha_vencimiento' => 'required|string',
      'fecha_presentacion' => 'required|string',
      'estado' => 'required|string', 
      'ejercicio' => 'required|numeric', 
      'periodo_inicio' => 'required|string',
      'periodo_fin' => 'required|string',
      //'fecha_pago' => 'required|string',
      'tipo_declaracion' => 'required|string',
      'total_remuneraciones_erogadas' => 'required|numeric', 
      'porcent_sobre_total_remuneraciones_erogadas' => 'required|numeric', 
      'complementarias_impuesto_a_cargo' => 'required|numeric', 
      'complementarias_saldo_a_favor' => 'required|numeric', 
      'impuesto_actualizado' => 'required|numeric', 
      'impuesto_descuento' => 'required|string', 
      'impuesto_recargos' => 'required|numeric', 
      'impuesto_recargos_condonados' => 'required|numeric', 
      'subsi_n_resolu_impuesto_pagar' => 'required|numeric', 
      'subsi_n_resolu_recargos' => 'required|numeric', 
      'compensa_n_resolucion' => 'required|numeric', 
      'compensa_n_resolu_recargos' => 'required|numeric', 
      'impuesto_total_a_pagar' => 'required|numeric', 
      'impuesto_saldo_a_favor' => 'required|numeric',
      'observaciones' => 'required|string', 
      'docs_eliminar' => 'array', 
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => '	Los datos del impuesto sobre nómina no son válidos. Verifica las fechas, montos y observaciones',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $nomi_imp_token = $request->input('nomi_imp_token');
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $fecha_vencimiento = $request->input('fecha_vencimiento');
      $fecha_presentacion = $request->input('fecha_presentacion');
      $estado = $request->input('estado');
      $ejercicio = $request->input('ejercicio');
      $periodo_inicio = $request->input('periodo_inicio');
      $periodo_fin = $request->input('periodo_fin');
      //$fecha_pago = $request->input('fecha_pago');
      $tipo_declaracion = $request->input('tipo_declaracion');
      $total_remuneraciones_erogadas = $request->input('total_remuneraciones_erogadas');
      $porcen_sobre_total_remun_erog = $request->input('porcent_sobre_total_remuneraciones_erogadas');
      $complementarias_impuesto_a_cargo = $request->input('complementarias_impuesto_a_cargo');
      $complementarias_saldo_a_favor = $request->input('complementarias_saldo_a_favor');
      $impuesto_actualizado = $request->input('impuesto_actualizado');
      $impuesto_descuento = $request->input('impuesto_descuento');
      $impuesto_recargos = $request->input('impuesto_recargos');
      $impuesto_recargos_condonados = $request->input('impuesto_recargos_condonados');
      $subsi_n_resolu_impuesto_pagar = $request->input('subsi_n_resolu_impuesto_pagar');
      $subsi_n_resolu_recargos = $request->input('subsi_n_resolu_recargos');
      $compensa_n_resolucion = $request->input('compensa_n_resolucion');
      $compensa_n_resolu_recargos = $request->input('compensa_n_resolu_recargos');
      $impuesto_total_a_pagar = $request->input('impuesto_total_a_pagar');
      $impuesto_saldo_a_favor = $request->input('impuesto_saldo_a_favor');
      $observaciones = $request->input('observaciones');
      $docs_eliminar = $request->input('docs_eliminar');

      $OKNominaTkn = isset($nomi_imp_token) && !empty($nomi_imp_token);
      $OKNominaFCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $OKNominaFechaVencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
      $OKNominaFechaPresentacion = isset($fecha_presentacion) && !empty($fecha_presentacion) && preg_match($JwtAuth->filtroFecha(),$fecha_presentacion);
      $OKNominaEstado = isset($estado) && !empty($estado);
      $OKNominaEjercicio = isset($ejercicio) && !empty($ejercicio) && preg_match($JwtAuth->filtroNumerico(),$ejercicio);
      $OKNominaPeriodoInicio = isset($periodo_inicio) && !empty($periodo_inicio) && preg_match($JwtAuth->filtroFecha(),$periodo_inicio);
      $OKNominaPeriodoFin = isset($periodo_fin) && !empty($periodo_fin) && preg_match($JwtAuth->filtroFecha(),$periodo_fin);
      $OKNominaPeriodo = $OKNominaPeriodoInicio && $OKNominaPeriodoFin && ($JwtAuth->convierteFechaEpoc($periodo_fin) >= $JwtAuth->convierteFechaEpoc($periodo_inicio));
      $OKNominaTipoDeclaracion = isset($tipo_declaracion) && !empty($tipo_declaracion) && preg_match($JwtAuth->filtroAlfaNumerico(),$tipo_declaracion);
      
      $OKNominaTotalRemuneracionesErogadas = isset($total_remuneraciones_erogadas) && is_numeric($total_remuneraciones_erogadas) && preg_match($JwtAuth->filtroCostoPrecio(),$total_remuneraciones_erogadas);
      $OKNominaPorcenSobreTotalRemunErogad = isset($porcen_sobre_total_remun_erog) && is_numeric($porcen_sobre_total_remun_erog) && preg_match($JwtAuth->filtroCostoPrecio(),$porcen_sobre_total_remun_erog);
      $OKNominaComplementariasImpuestoACargo = isset($complementarias_impuesto_a_cargo) && is_numeric($complementarias_impuesto_a_cargo) && preg_match($JwtAuth->filtroCostoPrecio(),$complementarias_impuesto_a_cargo);
      $OKNominaComplementariasSaldoAFavor = isset($complementarias_saldo_a_favor) && is_numeric($complementarias_saldo_a_favor) && preg_match($JwtAuth->filtroCostoPrecio(),$complementarias_saldo_a_favor);
      $OKNominaImpuestoActualizado = isset($impuesto_actualizado) && is_numeric($impuesto_actualizado) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_actualizado);
      $OKNominaImpuestoDescuento = isset($impuesto_descuento) && is_numeric($impuesto_descuento) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_descuento);
      $OKNominaImpuestoRecargos = isset($impuesto_recargos) && is_numeric($impuesto_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_recargos);
      $OKNominaImpuestoRecargosCondonados = isset($impuesto_recargos_condonados) && is_numeric($impuesto_recargos_condonados) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_recargos_condonados);
      $OKNominaSubsiNResoluImpuestoPagar = isset($subsi_n_resolu_impuesto_pagar) && is_numeric($subsi_n_resolu_impuesto_pagar) && preg_match($JwtAuth->filtroCostoPrecio(),$subsi_n_resolu_impuesto_pagar);
      $OKNominaSubsiNResoluRecargos = isset($subsi_n_resolu_recargos) && is_numeric($subsi_n_resolu_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$subsi_n_resolu_recargos);
      $OKNominaimporteCompensaNResolucion = isset($compensa_n_resolucion) && is_numeric($compensa_n_resolucion) && preg_match($JwtAuth->filtroCostoPrecio(),$compensa_n_resolucion);
      $OKNominaimporteCompensaNResolucionRecargos = isset($compensa_n_resolu_recargos) && is_numeric($compensa_n_resolu_recargos) && preg_match($JwtAuth->filtroCostoPrecio(),$compensa_n_resolu_recargos);
      $OKNominaImpuestoTotalAPagar = isset($impuesto_total_a_pagar) && is_numeric($impuesto_total_a_pagar) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_total_a_pagar);
      $OKNominaImpuestoSaldoAFavor = isset($impuesto_saldo_a_favor) && is_numeric($impuesto_saldo_a_favor) && preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_saldo_a_favor);
      $OKNominaObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);
      $OKNominaDocsEliminar = isset($docs_eliminar) && is_array($docs_eliminar) && count($docs_eliminar) > 0;

      if ($OKNominaTkn && $OKNominaFCont && $OKNominaFechaVencimiento && $OKNominaFechaPresentacion && $OKNominaEstado && $OKNominaEjercicio && $OKNominaPeriodo && $OKNominaTipoDeclaracion && $OKNominaTotalRemuneracionesErogadas && $OKNominaPorcenSobreTotalRemunErogad && 
        $OKNominaComplementariasImpuestoACargo && $OKNominaComplementariasSaldoAFavor && $OKNominaImpuestoActualizado && $OKNominaImpuestoDescuento && $OKNominaImpuestoRecargos && $OKNominaImpuestoRecargosCondonados && 
        $OKNominaSubsiNResoluImpuestoPagar && $OKNominaSubsiNResoluRecargos && $OKNominaimporteCompensaNResolucion && $OKNominaimporteCompensaNResolucionRecargos && $OKNominaImpuestoTotalAPagar && 
        $OKNominaImpuestoSaldoAFavor && $OKNominaObservacion) {
          
        $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
        ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
        ->where([
          'nomImp.nomi_imp_token' => $nomi_imp_token,
          'nomImp.nomi_imp_status' => TRUE,
          'emp.empresa_token' => $empresa,
        ])
        ->get();

        foreach ($queryImpNomina as $vImpNom) {
          $nomina_id = DB::table("vhum_nominas_impuestos")->where("nomi_imp_token",$vImpNom->nomi_imp_token)->value("id");
          $folio_nomina = $vImpNom->nomi_imp_folio_interior;
          $post_folio_nomina = $vImpNom->nomi_imp_subfolio;
          $nomi_imp_folio = 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : '');
          $filepath = $vImpNom->root_tkn . "/0004-vhm/impuestos_sobre_nomina/$vImpNom->nomi_imp_fecha_registro-$nomi_imp_folio/anexos/";

          $isnUpdate = DB::table("vhum_nominas_impuestos")
          ->where("nomi_imp_token",$vImpNom->nomi_imp_token)
          ->limit(1)->update(
            array(
              "nomi_imp_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
              "nomi_imp_estado" => DB::table("fnzs_catalogos_fed_estados_municipios")->where("fed_est_mun_token", $estado)->value("id"),
              "nomi_imp_ejercicio" => $ejercicio,
              "nomi_imp_periodo_inicio" => $JwtAuth->convierteFechaEpoc($periodo_inicio),
              "nomi_imp_periodo_fin" => $JwtAuth->convierteFechaEpoc($periodo_fin),
              "nomi_imp_fecha_pago" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
              "nomi_imp_fecha_vencimiento" => $JwtAuth->convierteFechaEpoc($fecha_vencimiento),
              "nomi_imp_fecha_presentacion" => $JwtAuth->convierteFechaEpoc($fecha_presentacion),
              "nomi_imp_tipo_declaracion" => $tipo_declaracion,
              "nomi_imp_moneda" => "MXN",
              "nomi_imp_total_remuneraciones_erogadas" => $total_remuneraciones_erogadas,
              "nomi_imp_porcent_sobre_total_remuneraciones_erogadas" => $porcen_sobre_total_remun_erog,
              "nomi_imp_complementarias_impuesto_a_cargo" => $complementarias_impuesto_a_cargo,
              "nomi_imp_complementarias_saldo_a_favor" => $complementarias_saldo_a_favor,
              "nomi_imp_impuesto_actualizado" => $impuesto_actualizado,
              "nomi_imp_impuesto_descuento" => $impuesto_descuento,
              "nomi_imp_impuesto_recargos" => $impuesto_recargos,
              "nomi_imp_impuesto_recargos_condonados" => $impuesto_recargos_condonados,
              "nomi_imp_subsi_n_resolu_impuesto_pagar" => $subsi_n_resolu_impuesto_pagar,
              "nomi_imp_subsi_n_resolu_recargos" => $subsi_n_resolu_recargos,
              "nomi_imp_compensa_n_resolucion" => $compensa_n_resolucion,
              "nomi_imp_compensa_n_resolu_recargos" => $compensa_n_resolu_recargos,
              "nomi_imp_impuesto_total_a_pagar" => $impuesto_total_a_pagar,
              "nomi_imp_impuesto_saldo_a_favor" => $impuesto_saldo_a_favor,
              "observaciones" => $JwtAuth->encriptar($observaciones),
            )
          );

          $queryNominaPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
          ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
          ->limit(1)->update(
            array(
              "order.doc_anterior_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
            )
          );

          if ($OKNominaDocsEliminar) {
            for ($de=0; $de < count($docs_eliminar); $de++) {
              $token_documento = $docs_eliminar[$de]['token_documento'];
              $nombre_documento = $docs_eliminar[$de]['name_documento'];
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
                $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%IMP-NOMI-EVID%'");
                $token_documento = $JwtAuth->encriptarToken($nomina_id,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                $insertDocSoli = DB::table("sos_documentos")->insert(
                  array(
                    "token_documento" => $token_documento,
                    "fecha_carga" => time(),
                    "modulo" => "pagos",
                    "folio_modulo" => "IMP-NOMI-EVID".$select_folio_doc[0]->folio,
                    "tipo_documento" => "an",
                    "nombre_documento" => $JwtAuth->encriptar($doc_name),
                    "impuesto_sobre_nomina" => $nomina_id,
                    "status_documento" => TRUE,
                  )
                );
              }
            }
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Reporte de isn con el folio $nomi_imp_folio ha sido actualizada satisfactoriamente"
          );
        }
      } else {
        $mensaje_error = "";
        if (!$OKNominaTkn) $mensaje_error = "Error al seleccionar reporte de isn, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaFCont) $mensaje_error = "Error en fecha de contabilización, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaFechaVencimiento) $mensaje_error = "Error en fecha de vencimiento, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaEstado) $mensaje_error = "Error al seleccionar estado, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaEjercicio) $mensaje_error = "Error al registrar ejercicio, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaPeriodo) $mensaje_error = "Error al registrar periodo, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaTipoDeclaracion) $mensaje_error = "Error al registrar tipo de declaración, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaTotalRemuneracionesErogadas) $mensaje_error = "Error al registrar total de remuneraciones erogadas, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaPorcenSobreTotalRemunErogad) $mensaje_error = "Error al registrar % sobre el total de remuneraciones erogadas, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaComplementariasImpuestoACargo) $mensaje_error = "Error al registrar complementarias (Impuesto a cargo), intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaComplementariasSaldoAFavor) $mensaje_error = "Error al registrar complementarias (Saldo a favor), intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaImpuestoActualizado) $mensaje_error = "Error al registrar impuesto actualizado, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaImpuestoDescuento) $mensaje_error = "Error al registrar descuento, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaImpuestoRecargos) $mensaje_error = "Error al registrar recargos, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaImpuestoRecargosCondonados) $mensaje_error = "Error al registrar recargos condonados, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaSubsiNResoluImpuestoPagar) $mensaje_error = "Error al registrar Subsidio no. de resolución (Sobre el impuesto a pagar), intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaSubsiNResoluRecargos) $mensaje_error = "Error al registrar Subsidio no. de resolución (Sobre recargos (%)), intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaimporteCompensaNResolucion) $mensaje_error = "Error al registrar Compensación no. de resolución (Sobre el impuesto a pagar), intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaimporteCompensaNResolucionRecargos) $mensaje_error = "Error al registrar Compensación no. de resolución (Sobre recargos), intentelo nuevamente o comuniquese a soporte";

        if (!$OKNominaImpuestoTotalAPagar) $mensaje_error = "Error al registrar total a pagar, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaImpuestoSaldoAFavor) $mensaje_error = "Error al registrar saldo a favor, intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaObservacion) $mensaje_error = "Error al registrar observaciones de nómina, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaNominaImpuestos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'nomi_imp_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Selecciona el impuesto sobre nómina que deseas eliminar',
				'errors' => $validate->errors()
			);
    } else {
      $nomi_imp_token = $request->input('nomi_imp_token');
      
      $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
      ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
      ->where([
        'nomImp.nomi_imp_token' => $nomi_imp_token,
        'nomImp.nomi_imp_status' => TRUE,
        'emp.empresa_token' => $empresa,
      ])
      ->get();
      
      if ($queryImpNomina->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron impuestos sobre nómina registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        
        $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
        ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
        ->where('nomImp.nomi_imp_token',$nomi_imp_token)
        ->get();
        
        if (count($queryNominaPago) == 0) {
          $queryDeleteNomina = DB::table("vhum_nominas_impuestos")->where("nomi_imp_token",$nomi_imp_token)
          ->limit(1)->update(array(
            "nomi_imp_status" => FALSE,
            "nomi_imp_fecha_delete" => time()
          ));

          if ($queryDeleteNomina) {
            $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Este reporte de isn ha sido eliminado satisfactoriamente');
          } else {
            $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Este reporte de isn no se puede eliminar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Este reporte de isn no se puede eliminar, se encuentra vinculado a pagos realizados, intentelo nuevamente o comuniquese a soporte');
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);

    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'nomi_imp_token' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Error al seleccionar reporte de isn, intentelo nuevamente o comuniquese a soporte',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $nomi_imp_token = $parametrosArray['nomi_imp_token'];

        $OKNominaPeriodo = isset($nomi_imp_token) && !empty($nomi_imp_token);

        if ($OKNominaPeriodo) {

          

        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => '');
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

  public function listaDeletedNominaImpuestos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
    ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
    ->where([
      'nomImp.nomi_imp_status' => FALSE,
      'emp.empresa_token' => $empresa,
    ])
    ->orderBy('nomImp.id', 'DESC')->get();
    
    if ($queryImpNomina->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron registros de impuestos sobre nómina eliminados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $lista_imp_nomina = array();
      
      foreach ($queryImpNomina as $vImpNom) {
        $folio_nomina = $vImpNom->nomi_imp_folio_interior;
        $post_folio_nomina = $vImpNom->nomi_imp_subfolio;
        $nomi_imp_moneda = $vImpNom->nomi_imp_moneda;
        $nomi_imp_moneda_decimales = $JwtAuth->getMonedaAPI($vImpNom->nomi_imp_moneda);
        $ejercicio = $vImpNom->nomi_imp_ejercicio;
        $periodo_inicio = $vImpNom->nomi_imp_periodo_inicio;
        $periodo_fin = $vImpNom->nomi_imp_periodo_fin;
        
        $queryImpEstado = DB::table("vhum_nominas_impuestos AS nomImp")
        ->join("fnzs_catalogos_fed_estados_municipios AS ent", "nomImp.nomi_imp_estado", "ent.id")
        ->where('nomImp.nomi_imp_token',$vImpNom->nomi_imp_token)
        ->select('ent.fed_est_mun_rfc','ent.fed_est_mun_entidad')
        ->first();

        $estado_all_info = $queryImpEstado ? $queryImpEstado->fed_est_mun_rfc.' '.$JwtAuth->desencriptar($queryImpEstado->fed_est_mun_entidad) : '';

        $row = array(
          "nomi_imp_token" => $vImpNom->nomi_imp_token,
          "nomi_imp_folio" => 'NOM-IMP-'.$JwtAuth->generarFolio($folio_nomina).(!is_null($post_folio_nomina) ? '-'.$post_folio_nomina : ''),
          "nomi_imp_fecha_contabilizacion" => gmdate('Y-m-d H:i:s',$vImpNom->nomi_imp_fecha_contabilizacion),
          "nomi_imp_estado_all_info" => $estado_all_info,
          "nomi_imp_ejercicio" => $ejercicio,
          "nomi_imp_periodo_inicio" => ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_inicio)->locale('es')->translatedFormat('F')),
          "nomi_imp_periodo_fin" => ucfirst(Carbon::createFromTimestamp($vImpNom->nomi_imp_periodo_fin)->locale('es')->translatedFormat('F')),
          "nomi_imp_fecha_vencimiento" => gmdate('Y-m-d H:i:s',$vImpNom->nomi_imp_fecha_vencimiento),
          "nomi_imp_tipo_declaracion" => $vImpNom->nomi_imp_tipo_declaracion == 'comple' ? "complementaria" : "normal",
          "nomi_imp_moneda" => "MXN",
          "nomi_imp_impuesto_total_a_pagar" => "$".number_format($vImpNom->nomi_imp_impuesto_total_a_pagar,$nomi_imp_moneda_decimales,'.',',')." $nomi_imp_moneda",
          "nomi_imp_fecha_delete" => gmdate('Y-m-d H:i:s',$vImpNom->nomi_imp_fecha_delete),
        );
        $lista_imp_nomina[] = $row;
      }
      
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'isn_lista' => $lista_imp_nomina
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restauraNominaImpuestos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'nomi_imp_token' => 'required|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Selecciona el impuesto sobre nómina que deseas restaurar',
				'errors' => $validate->errors()
			);
    } else {
      $nomi_imp_token = $request->input('nomi_imp_token');
      
      $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
      ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
      ->where([
        'nomImp.nomi_imp_token' => $nomi_imp_token,
        'nomImp.nomi_imp_status' => FALSE,
        'emp.empresa_token' => $empresa,
      ])
      ->get();
      
      if ($queryImpNomina->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron registros de impuestos sobre nómina para restaurar'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $queryDeleteNomina = DB::table("vhum_nominas_impuestos")->where("nomi_imp_token",$nomi_imp_token)
        ->limit(1)->update(array(
          "nomi_imp_status" => TRUE,
          "nomi_imp_fecha_delete" => NULL
        ));

        if ($queryDeleteNomina) {
          $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Este reporte de isn ha sido restaurado satisfactoriamente');
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Este reporte de isn no se puede restaurar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaPermanenteNominaImpuestos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'nomi_imp_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Selecciona el impuesto sobre nómina que deseas eliminar permanentemente',
				'errors' => $validate->errors()
			);
    } else {
      $nomi_imp_token = $request->input('nomi_imp_token');
      
      $queryImpNomina = DB::table("vhum_nominas_impuestos AS nomImp")
      ->join("main_empresas AS emp", "nomImp.nomina_empresa", "emp.id")
      ->where([
        'nomImp.nomi_imp_token' => $nomi_imp_token,
        'nomImp.nomi_imp_status' => FALSE,
        'emp.empresa_token' => $empresa,
      ])
      ->get();
      
      if ($queryImpNomina->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron registros de impuestos sobre nómina para borrar permanentemente'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        
        $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
        ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
        ->where('nomImp.nomi_imp_token',$nomi_imp_token)
        ->get();
        
        if (count($queryNominaPago) == 0) {
          $queryNominaPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_nominas_impuestos AS nomImp", "order.impuesto_sobre_nomina", "=", "nomImp.id")
          ->where('nomImp.nomi_imp_token',$nomi_imp_token)
          ->limit(1)->delete();
        }

        $queryDeleteNomina = DB::table("vhum_nominas_impuestos")
        ->where("nomi_imp_token",$nomi_imp_token)
        ->limit(1)->delete();

        if ($queryDeleteNomina) {
          $dataMensaje = array('status' => 'success','code' => 200, 'message' => 'Este reporte de isn ha sido eliminado satisfactoriamente');
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Este reporte de isn no se puede eliminar debido a errores internos, intentelo nuevamente o comuniquese a soporte');
        } 
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}