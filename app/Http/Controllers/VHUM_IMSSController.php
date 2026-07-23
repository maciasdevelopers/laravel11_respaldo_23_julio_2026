<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\AportacionesIMSSModelo;
use App\Models\OrdenPagoModelo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VHUM_IMSSController extends Controller{
  public function registraAportacionSeguridadSocial(Request $request){
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
      'fecha_presentacion' => 'required|string',
      'registro_patronal' => 'required|string', 
      'periodo_pago_seguros_imss_anio' => 'required|numeric', 
      'periodo_pago_seguros_imss_mes' => 'required|numeric',
      'pago_rcv_infonavit_inicio' => 'nullable|string',
      'pago_rcv_infonavit_fin' => 'nullable|string',
      'folio_sua' => 'required|string',
      'clave_recepcion_archivo_pago' => 'required|string', 
      'propuesta_fecha_limite_pago' => 'required|string', 
      'linea_captura_sipare' => 'required|string', 
      'propuesta_s_m_g_d_f' => 'required|string', 
      'propuesta_fecha_salario_minimo_pago' => 'required|string', 
      'propuesta_valor_uma' => 'required|string', 
      'propuesta_num_de_cotizantes' => 'required|string', 
      'propuesta_num_dias_a_cotizar' => 'required|string', 
      'propuesta_num_de_acreditados' => 'required|string', 
      'desglose_total_cuotas' => 'required|string', 
      'observaciones' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Faltan campos obligatorios o el formato es incorrecto.',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $fecha_presentacion = $request->input('fecha_presentacion');
      $registro_patronal = $request->input('registro_patronal');
      $periodo_pago_seguros_imss_anio = $request->input('periodo_pago_seguros_imss_anio');
      $periodo_pago_seguros_imss_mes = $request->input('periodo_pago_seguros_imss_mes');
      $pago_rcv_infonavit_inicio = $request->input('pago_rcv_infonavit_inicio');
      $pago_rcv_infonavit_fin = $request->input('pago_rcv_infonavit_fin');
      $folio_sua = $request->input('folio_sua');
      $clave_recepcion_archivo_pago = $request->input('clave_recepcion_archivo_pago');
      $propuesta_fecha_limite_pago = $request->input('propuesta_fecha_limite_pago');
      $linea_captura_sipare = $request->input('linea_captura_sipare');
      $propuesta_s_m_g_d_f = $request->input('propuesta_s_m_g_d_f');
      $propuesta_fecha_salario_minimo_pago = $request->input('propuesta_fecha_salario_minimo_pago');
      $propuesta_valor_uma = $request->input('propuesta_valor_uma');
      $propuesta_num_de_cotizantes = $request->input('propuesta_num_de_cotizantes');
      $propuesta_num_dias_a_cotizar = $request->input('propuesta_num_dias_a_cotizar');
      $propuesta_num_de_acreditados = $request->input('propuesta_num_de_acreditados');
      $desglose_total_cuotas = json_decode($request->input('desglose_total_cuotas'), true);
      
      // 3. Verifica que el JSON sea válido antes de seguir
      if (json_last_error() !== JSON_ERROR_NONE) {
          return response()->json(['status' => 'error', 'message' => 'Error en el formato de cuotas'], 200);
      }

      $observaciones = $request->input('observaciones');
      
      $OKFechaCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $OKFechaPresentacion = isset($fecha_presentacion) && !empty($fecha_presentacion) && preg_match($JwtAuth->filtroFecha(),$fecha_presentacion);
      $OKRegistroPatronal = isset($registro_patronal) && !empty($registro_patronal) && preg_match($JwtAuth->filtroAlfaNumerico(),$registro_patronal);

      $OKPeriodoPagoSegIMSSAnio = isset($periodo_pago_seguros_imss_anio) && !empty($periodo_pago_seguros_imss_anio) && preg_match($JwtAuth->filtroNumericoSimple(),$periodo_pago_seguros_imss_anio);
      $OKPeriodoPagoSegIMSSMes = isset($periodo_pago_seguros_imss_mes) && !empty($periodo_pago_seguros_imss_mes) && preg_match($JwtAuth->filtroNumericoSimple(),$periodo_pago_seguros_imss_mes);
      $OKPeriodoPagoSegIMSS = $OKPeriodoPagoSegIMSSAnio && $OKPeriodoPagoSegIMSSMes;

      $OKRcvInfonInicio = isset($pago_rcv_infonavit_inicio) && !empty($pago_rcv_infonavit_inicio) && preg_match($JwtAuth->filtroFecha(),$pago_rcv_infonavit_inicio);
      $OKRcvInfonFin = isset($pago_rcv_infonavit_fin) && !empty($pago_rcv_infonavit_fin) && preg_match($JwtAuth->filtroFecha(),$pago_rcv_infonavit_fin);
      $OKPagoRCVInfonavit = $OKRcvInfonInicio && $OKRcvInfonFin && ($JwtAuth->convierteFechaEpoc($pago_rcv_infonavit_fin) >= $JwtAuth->convierteFechaEpoc($pago_rcv_infonavit_inicio));

      $OKFolioSua = isset($folio_sua) && !empty($folio_sua) && preg_match($JwtAuth->filtroAlfaNumerico(),$folio_sua);
      $OKClaveRecepcionArchivoPago = isset($clave_recepcion_archivo_pago) && !empty($clave_recepcion_archivo_pago) && preg_match($JwtAuth->filtroNumerico(),$clave_recepcion_archivo_pago);
      $OKPropuestaFechaLimitePago = isset($propuesta_fecha_limite_pago) && !empty($propuesta_fecha_limite_pago) && preg_match($JwtAuth->filtroFecha(),$propuesta_fecha_limite_pago);
      $OKPropuestaRefDEPagoSIPARE = isset($linea_captura_sipare) && !empty($linea_captura_sipare) && preg_match($JwtAuth->filtroAlfaNumerico(),$linea_captura_sipare);
      $OKPropuestaSMGDF = isset($propuesta_s_m_g_d_f) && is_numeric($propuesta_s_m_g_d_f) && preg_match($JwtAuth->filtroCostoPrecio(),$propuesta_s_m_g_d_f);
      $OKPropuestaFechaSalarioMinimoPago = isset($propuesta_fecha_salario_minimo_pago) && !empty($propuesta_fecha_salario_minimo_pago) && preg_match($JwtAuth->filtroFecha(),$propuesta_fecha_salario_minimo_pago);
      $OKNominaPropuestaValorUMA = isset($propuesta_valor_uma) && is_numeric($propuesta_valor_uma) && preg_match($JwtAuth->filtroCostoPrecio(),$propuesta_valor_uma);
      $OKPropuestaNumCotizantes = isset($propuesta_num_de_cotizantes) && !empty($propuesta_num_de_cotizantes) && preg_match($JwtAuth->filtroNumericoSimple(),$propuesta_num_de_cotizantes);
      $OKPropuestaNumDiasCotizar = isset($propuesta_num_dias_a_cotizar) && !empty($propuesta_num_dias_a_cotizar) && preg_match($JwtAuth->filtroNumericoSimple(),$propuesta_num_dias_a_cotizar);
      $OKPropuestaNumAcreditados = isset($propuesta_num_de_acreditados) && !empty($propuesta_num_de_acreditados) && preg_match($JwtAuth->filtroNumericoSimple(),$propuesta_num_de_acreditados);
      $OKDesgloseTotalCuotas = isset($desglose_total_cuotas) && is_array($desglose_total_cuotas) && count($desglose_total_cuotas);
      $OKObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

      if ($OKFechaCont && $OKFechaPresentacion && $OKRegistroPatronal && $OKFolioSua && $OKClaveRecepcionArchivoPago && $OKPropuestaFechaLimitePago && $OKPropuestaRefDEPagoSIPARE && $OKPropuestaSMGDF && $OKPropuestaFechaSalarioMinimoPago && 
        $OKNominaPropuestaValorUMA && $OKPropuestaNumCotizantes && $OKPropuestaNumDiasCotizar && $OKDesgloseTotalCuotas && $OKObservacion) {
        $fechaSistema = time();
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

        foreach ($queryEmp as $vEmp) {
          $folioSistema = DB::select("SELECT aprtimss.aport_ssocial_folio_interior+1 AS folio,aport_ssocial_subfolio FROM vhum_aportaciones_seguridad_social_main AS aprtimss JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
            JOIN teci_usuarios_catalogo AS users WHERE aprtimss.aport_ssocial_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? 
            ORDER BY aprtimss.aport_ssocial_folio_interior DESC LIMIT 1",[$empresa,$usuario]);
          //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
          if (count($folioSistema) == 1) {
            if ($folioSistema[0]->folio == 1000000000) {
                $post_folio_db = DB::select("SELECT aport_ssocial_subfolio FROM vhum_aportaciones_seguridad_social_main WHERE id = (SELECT Max(aprtimss.id) FROM vhum_aportaciones_seguridad_social_main AS aprtimss JOIN main_empresas AS emp 
                  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE aprtimss.aport_ssocial_empresa = emp.id AND emp.empresa_token = ?
                  AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$empresa,$usuario]);
                
                $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->aport_ssocial_subfolio);
                $folio_nuevo = 1;
            } else {
                $post_folio = NULL;
                $folio_nuevo = $folioSistema[0]->folio;
            }
          } else {
            $post_folio = NULL;
            $folio_nuevo = 1;
          }
          $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');
          $tokenImpuestosNomina = $JwtAuth->encriptarToken($fecha_contabilizacion.$folio_sua.$clave_recepcion_archivo_pago.$linea_captura_sipare);
          //vhum_aportaciones_seguridad_social_main
          $ssocial_fecha_registro = time();
          $newAportIMSS = new AportacionesIMSSModelo();
          $newAportIMSS->aport_ssocial_token = $tokenImpuestosNomina;
          $newAportIMSS->aport_ssocial_fecha_registro = $ssocial_fecha_registro;
          $newAportIMSS->aport_ssocial_folio_interior = $folio_nuevo;
          $newAportIMSS->aport_ssocial_subfolio = $post_folio;
          $newAportIMSS->aport_ssocial_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
          $newAportIMSS->aport_ssocial_fecha_presentacion = $JwtAuth->convierteFechaEpoc($fecha_presentacion);
          $newAportIMSS->aport_ssocial_registro_patronal = DB::table("vhum_centros_de_trabajo_catalogo")->where('centrotrab_clave_registro_patronal_imss',$registro_patronal)->value('id');
          $newAportIMSS->periodo_pago_seguros_imss_anio = $OKPeriodoPagoSegIMSS ? $periodo_pago_seguros_imss_anio : NULL;
          $newAportIMSS->periodo_pago_seguros_imss_mes = $OKPeriodoPagoSegIMSS ? $periodo_pago_seguros_imss_mes : NULL;
          $newAportIMSS->pago_rcv_infonavit_inicio = $OKPagoRCVInfonavit ? $JwtAuth->convierteFechaEpoc($pago_rcv_infonavit_inicio) : NULL;
          $newAportIMSS->pago_rcv_infonavit_fin = $OKPagoRCVInfonavit ? $JwtAuth->convierteFechaEpoc($pago_rcv_infonavit_fin) : NULL;
          $newAportIMSS->folio_sua = $folio_sua;
          $newAportIMSS->clave_recepcion_archivo_pago = $clave_recepcion_archivo_pago;
          $newAportIMSS->propuesta_fecha_limite_pago = $JwtAuth->convierteFechaEpoc($propuesta_fecha_limite_pago);
          $newAportIMSS->linea_captura_sipare = $linea_captura_sipare;
          $newAportIMSS->propuesta_s_m_g_d_f = $propuesta_s_m_g_d_f;
          $newAportIMSS->propuesta_fecha_salario_minimo_pago = $JwtAuth->convierteFechaEpoc($propuesta_fecha_salario_minimo_pago);
          $newAportIMSS->propuesta_valor_uma = $propuesta_valor_uma;
          $newAportIMSS->propuesta_num_de_cotizantes = $propuesta_num_de_cotizantes;
          $newAportIMSS->propuesta_num_dias_a_cotizar = $propuesta_num_dias_a_cotizar;
          $newAportIMSS->propuesta_num_de_acreditados = $OKPropuestaNumAcreditados ? $propuesta_num_de_acreditados : 0;
          $newAportIMSS->observaciones = $JwtAuth->encriptar($observaciones);
          $newAportIMSS->aport_ssocial_status = TRUE;
          $newAportIMSS->aport_ssocial_empresa = $vEmp->id;
          $savedAportIMSS = $newAportIMSS->save();
          
          if ($savedAportIMSS) {
            $newAportID = $newAportIMSS->id;
            
            $cuotasList = collect($desglose_total_cuotas)
            ->filter(function ($item) {
              return array_key_exists('patronal',$item) || array_key_exists('obrera',$item) || array_key_exists('total',$item);
            })
            ->map(function ($item) {
              return [
                'label'     => $item['label'] ?? null,
                'patronal'  => $item['patronal'] ?? 0,
                'obrera'    => $item['obrera'] ?? 0,
                'total'     => $item['total'] ?? 0,
              ];
            })
            ->values()
            ->toArray();

            foreach ($cuotasList as $desg) {
              DB::table('imss_cuotas_detalle')
              ->insert(array(
                "aportaciones_main" => $newAportID,
                "type" => NULL, // si no lo usas, déjalo null
                "label" => $desg['label'],	
                "patronal" => $desg['patronal'],	
                "obrera" => $desg['obrera'],	
                "total" => $desg['total'],	
              ));
            }

            //ALTER TABLE `fnzs_pagos_orden` ADD `nomina_main` INT(10) NULL AFTER `reembolso_solicitud`;
            $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
              AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

            $tknOrder = $JwtAuth->encriptarToken(time(),$folioOrden[0]->folio,$newAportID);
            $orderpay = new OrdenPagoModelo();
            $orderpay->token_ordenPago = $tknOrder;
            $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
            $orderpay->fecha_sistema_ordenp = $fechaSistema;
            $orderpay->aportacion_seguridad_social = $newAportID;
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

            $filepath = $vEmp->root_tkn . "/0004-vhm/aportaciones_seguridad_social/$ssocial_fecha_registro-$folio_aport/anexos/";
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
                  $token_documento = $JwtAuth->encriptarToken($newAportID,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                  $insertDocSoli = DB::table("sos_documentos")->insert(
                    array(
                      "token_documento" => $token_documento,
                      "fecha_carga" => time(),
                      "modulo" => "pagos",
                      "folio_modulo" => "IMP-NOMI-EVID".$select_folio_doc[0]->folio,
                      "tipo_documento" => "an",
                      "nombre_documento" => $JwtAuth->encriptar($doc_name),
                      "aportacion_seguridad_social" => $newAportID,
                      "status_documento" => TRUE,
                    )
                  );
                }
              }
            }

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Reporte de aportación de seguridad social registrado satisfactoriamente con el folio $folio_aport"
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
        if (!$OKFechaCont) $mensaje_error = "Error en fecha de contabilización, intentelo nuevamente o comuniquese a soporte";
        if (!$OKFechaPresentacion) $mensaje_error = "Error en fecha de presentación, intentelo nuevamente o comuniquese a soporte";
        if (!$OKRegistroPatronal) $mensaje_error = "Error al seleccionar clave de registro patronal del IMSS, intentelo nuevamente o comuniquese a soporte";
        if (!$OKFolioSua) $mensaje_error = "Error al registrar folio del SUA, intentelo nuevamente o comuniquese a soporte";
        if (!$OKClaveRecepcionArchivoPago) $mensaje_error = "Error al registrar clave de recepción de archivo de pago, intentelo nuevamente o comuniquese a soporte";
        if (!$OKPropuestaFechaLimitePago) $mensaje_error = "Error al registrar fecha límite de pago, intentelo nuevamente o comuniquese a soporte";
        if (!$OKPropuestaRefDEPagoSIPARE) $mensaje_error = "Error al registrar referencia de pago (Línea de Captura SIPARE), intentelo nuevamente o comuniquese a soporte";
        if (!$OKPropuestaSMGDF) $mensaje_error = "Error al registrar S.M.G.D.F, intentelo nuevamente o comuniquese a soporte";
        if (!$OKPropuestaFechaSalarioMinimoPago) $mensaje_error = "Error al registrar Fecha SAL. MIN., intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaPropuestaValorUMA) $mensaje_error = "Error al registrar complementarias (Impuesto a cargo), intentelo nuevamente o comuniquese a soporte";
        if (!$OKPropuestaNumCotizantes) $mensaje_error = "Error al registrar valor UMA, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDesgloseTotalCuotas) $mensaje_error = "Error al registrar la información detallada del importe total de cuotas, intentelo nuevamente o comuniquese a soporte";
        if (!$OKObservacion) $mensaje_error = "Error al registrar observaciones de este reporte, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaRegAportacionSeguridadSocial(Request $request){
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
      
      $queryIMSSAportacion = AportacionesIMSSModelo::join("main_empresas AS emp", "vhum_aportaciones_seguridad_social_main.aport_ssocial_empresa", "emp.id")
      ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "vhum_aportaciones_seguridad_social_main.aport_ssocial_registro_patronal", "c_trab.id")
      ->where([
        'vhum_aportaciones_seguridad_social_main.aport_ssocial_status' => TRUE,
        'emp.empresa_token' => $empresa,
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("vhum_aportaciones_seguridad_social_main.aport_ssocial_fecha_contabilizacion", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('vhum_aportaciones_seguridad_social_main.id', 'DESC')->get();
      
      if ($queryIMSSAportacion->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron aportaciones de seguridad social registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $lista_aports = array();
        
        foreach ($queryIMSSAportacion as $vIMSSAp) {
          $folio_interior = $vIMSSAp->aport_ssocial_folio_interior;
          $post_folio = $vIMSSAp->aport_ssocial_subfolio;
          $aport_ssocial_moneda = $vIMSSAp->aport_ssocial_moneda;
          $aport_ssocial_moneda_decimales = $JwtAuth->getMonedaAPI($vIMSSAp->aport_ssocial_moneda);
  
          $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');
          $periodo_pago_seguros_imss = !is_null($vIMSSAp->periodo_pago_seguros_imss_anio) && !is_null($vIMSSAp->periodo_pago_seguros_imss_mes) ? ucfirst(Carbon::create($vIMSSAp->periodo_pago_seguros_imss_anio, $vIMSSAp->periodo_pago_seguros_imss_mes, 1)->locale('es')->isoFormat('MMMM YYYY')) : '';
          $pago_rcv_infonavit_inicio = !is_null($vIMSSAp->pago_rcv_infonavit_inicio) ? ucfirst(Carbon::createFromTimestamp($vIMSSAp->pago_rcv_infonavit_inicio)->locale('es')->translatedFormat('F')) : '';
          $pago_rcv_infonavit_fin = !is_null($vIMSSAp->pago_rcv_infonavit_fin) ? ucfirst(Carbon::createFromTimestamp($vIMSSAp->pago_rcv_infonavit_fin)->locale('es')->translatedFormat('F')) : '';
          $pago_rcv_infonavit = $pago_rcv_infonavit_inicio != '' && $pago_rcv_infonavit_fin != '' ? "$pago_rcv_infonavit_inicio - $pago_rcv_infonavit_fin" : '';
  
          $aport_ssocial_factura_doc_xml = !is_null($vIMSSAp->aport_ssocial_fact_xml) ? $JwtAuth->desencriptar($vIMSSAp->aport_ssocial_fact_xml) : null;
          $aport_ssocial_url_doc_xml = !is_null($vIMSSAp->aport_ssocial_fact_xml) ? "https://downloads.sos-mexico.com.mx/aportaciones_de_seguridad_social_imss_fact_xml/$vIMSSAp->aport_ssocial_token" : null;
          $aport_ssocial_factura_doc_pdf = !is_null($vIMSSAp->aport_ssocial_fact_pdf) ? $JwtAuth->desencriptar($vIMSSAp->aport_ssocial_fact_pdf) : null;
          $aport_ssocial_url_doc_pdf = !is_null($vIMSSAp->aport_ssocial_fact_pdf) ? "https://downloads.sos-mexico.com.mx/aportaciones_de_seguridad_social_imss_fact_pdf/$vIMSSAp->aport_ssocial_token" : null;

          $fct_infonavit_periodos = !is_null($vIMSSAp->pago_rcv_infonavit_inicio) && !is_null($vIMSSAp->pago_rcv_infonavit_fin); 
          $fct_infonavit_factura_doc_xml = $fct_infonavit_periodos && !is_null($vIMSSAp->aport_ssocial_infonavit_xml) ? $JwtAuth->desencriptar($vIMSSAp->aport_ssocial_infonavit_xml) : null;
          $fct_infonavit_url_doc_xml = $fct_infonavit_periodos && !is_null($vIMSSAp->aport_ssocial_infonavit_xml) ? "https://downloads.sos-mexico.com.mx/aportaciones_de_seguridad_social_infonavit_fact_xml/$vIMSSAp->aport_ssocial_token" : null;
          $fct_infonavit_factura_doc_pdf = $fct_infonavit_periodos && !is_null($vIMSSAp->aport_ssocial_infonavit_pdf) ? $JwtAuth->desencriptar($vIMSSAp->aport_ssocial_infonavit_pdf) : null;
          $fct_infonavit_url_doc_pdf = $fct_infonavit_periodos && !is_null($vIMSSAp->aport_ssocial_infonavit_pdf) ? "https://downloads.sos-mexico.com.mx/aportaciones_de_seguridad_social_infonavit_fact_pdf/$vIMSSAp->aport_ssocial_token" : null;
  
          $totales_cuotas_patronales = DB::table("imss_cuotas_detalle AS imsDet")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
          ->sum('imsDet.patronal');
  
          $totales_cuotas_obreras = DB::table("imss_cuotas_detalle AS imsDet")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
          ->sum('imsDet.obrera');
  
          $totales_cuotas_totales = DB::table("imss_cuotas_detalle AS imsDet")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
          ->sum('imsDet.total');
  
          $queryAportSegSocialPagoDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "order.aportacion_seguridad_social", "=", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
  
          $totalesAportSegSocialPago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "order.aportacion_seguridad_social", "=", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->sum('pay.monto_pago');
  
          $totalesAportSegSocialSaldo = $totales_cuotas_totales - $totalesAportSegSocialPago;
  
          $queryAportSegSocialOrdPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "order.aportacion_seguridad_social", "=", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->select('order.token_ordenPago', 'order.folio_ordenPago')
          ->first();
          $AportSegSocialOrdPagoToken = $queryAportSegSocialOrdPago ? $queryAportSegSocialOrdPago->token_ordenPago :'';
          $AportSegSocialOrdPagoFolio = $queryAportSegSocialOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryAportSegSocialOrdPago->folio_ordenPago) :'';
  
          $socialImssPrv = DB::table("vhum_aportaciones_seguridad_social_main AS social_main")
          ->join("fnzs_catalogos_fed_estados_municipios AS fedEst", "social_main.proveedor_imss", "=", "fedEst.id")
          ->where("social_main.aport_ssocial_token",$vIMSSAp->aport_ssocial_token)
          ->select(
            'fedEst.fed_est_mun_token',
            'fedEst.fed_est_mun_folio',
            'fedEst.fed_est_mun_subfolio',
            'fedEst.fed_est_mun_entidad',
            'fedEst.fed_est_mun_rfc'
          )
          ->first();
          $prv_imss_token = $socialImssPrv ? $socialImssPrv->fed_est_mun_token : '';
          $prv_imss_folio = $socialImssPrv ? 'FEM-'.$JwtAuth->generarFolio($socialImssPrv->fed_est_mun_folio).(!is_null($socialImssPrv->fed_est_mun_subfolio) ? '-'.$socialImssPrv->fed_est_mun_subfolio : '') : '';
          $prv_imss_entidad = $socialImssPrv ? $JwtAuth->desencriptar($socialImssPrv->fed_est_mun_entidad) : '';
          $prv_imss_rfc = $socialImssPrv ? $socialImssPrv->fed_est_mun_rfc : '';
  
          $socialInfonavitPrv = DB::table("vhum_aportaciones_seguridad_social_main AS social_main")
          ->join("fnzs_catalogos_fed_estados_municipios AS fedEst", "social_main.proveedor_infonavit", "=", "fedEst.id")
          ->where("social_main.aport_ssocial_token",$vIMSSAp->aport_ssocial_token)
          ->select(
            'fedEst.fed_est_mun_token',
            'fedEst.fed_est_mun_folio',
            'fedEst.fed_est_mun_subfolio',
            'fedEst.fed_est_mun_entidad',
            'fedEst.fed_est_mun_rfc'
          )
          ->first();
          $prv_infonavit_token = $pago_rcv_infonavit_inicio != '' && $pago_rcv_infonavit_fin != '' && $socialInfonavitPrv ? $socialInfonavitPrv->fed_est_mun_token : '';
          $fed_est_mun_folio_infnt = 'FEM-'.$JwtAuth->generarFolio($socialInfonavitPrv->fed_est_mun_folio).(!is_null($socialInfonavitPrv->fed_est_mun_subfolio) ? '-'.$socialInfonavitPrv->fed_est_mun_subfolio : '');
          $prv_infonavit_folio = $pago_rcv_infonavit_inicio != '' && $pago_rcv_infonavit_fin != '' && $socialInfonavitPrv ? $fed_est_mun_folio_infnt : '';
          $prv_infonavit_entidad = $pago_rcv_infonavit_inicio != '' && $pago_rcv_infonavit_fin != '' && $socialInfonavitPrv ? $JwtAuth->desencriptar($socialInfonavitPrv->fed_est_mun_entidad) : '';
          $prv_infonavit_rfc = $pago_rcv_infonavit_inicio != '' && $pago_rcv_infonavit_fin != '' && $socialInfonavitPrv ? $socialInfonavitPrv->fed_est_mun_rfc : '';
  
          $row = array(
            "aport_ssocial_token" => $vIMSSAp->aport_ssocial_token,
            "aport_ssocial_folio" => $folio_aport,
            "aport_ssocial_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vIMSSAp->aport_ssocial_fecha_contabilizacion),
            "aport_ssocial_fecha_presentacion" => $JwtAuth->mostrarUnixAFechaMexico($vIMSSAp->aport_ssocial_fecha_presentacion),
            "aport_ssocial_registro_patronal_uuid" => $vIMSSAp->centrotrab_uuid,
            "aport_ssocial_registro_patronal_serie" => $vIMSSAp->centrotrab_clave_registro_patronal_imss,
            "periodo_pago_seguros_imss" => $periodo_pago_seguros_imss,
            "pago_rcv_infonavit" => $pago_rcv_infonavit,
            "folio_sua" => $vIMSSAp->folio_sua,
            "clave_recepcion_archivo_pago" => $vIMSSAp->clave_recepcion_archivo_pago,
            "propuesta_fecha_limite_pago" => $JwtAuth->mostrarUnixAFechaMexico($vIMSSAp->propuesta_fecha_limite_pago),
            "linea_captura_sipare" => $vIMSSAp->linea_captura_sipare,
            "propuesta_s_m_g_d_f" => "$ ".number_format($vIMSSAp->propuesta_s_m_g_d_f,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
            "propuesta_fecha_salario_minimo_pago" => $JwtAuth->mostrarUnixAFechaMexico($vIMSSAp->propuesta_fecha_salario_minimo_pago),
            "propuesta_valor_uma" => "$ ".number_format($vIMSSAp->propuesta_valor_uma,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
            "propuesta_num_de_cotizantes" => $vIMSSAp->propuesta_num_de_cotizantes,
            "propuesta_num_dias_a_cotizar" => $vIMSSAp->propuesta_num_dias_a_cotizar,
            "propuesta_num_de_acreditados" => $vIMSSAp->propuesta_num_de_acreditados,
            "cuotas_patronales" => "$ ".number_format($totales_cuotas_patronales,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
            "cuotas_obreras" => "$ ".number_format($totales_cuotas_obreras,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
            "cuotas_totales" => "$ ".number_format($totales_cuotas_totales,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
            'aport_ssocial_pago' => "$".number_format($totalesAportSegSocialPago,$aport_ssocial_moneda_decimales,'.', ',')." $aport_ssocial_moneda",
            'aport_ssocial_saldo' => "$".number_format($totalesAportSegSocialSaldo,$aport_ssocial_moneda_decimales,'.', ',')." $aport_ssocial_moneda",
            'aport_ssocial_ord_pago_token' => $AportSegSocialOrdPagoToken,
            'aport_ssocial_ord_pago_folio' => $AportSegSocialOrdPagoFolio,
            "observaciones" => $JwtAuth->desencriptar($vIMSSAp->observaciones),
  
            "prv_imss_token" => $prv_imss_token,
            "prv_imss_folio" => $prv_imss_folio,
            "prv_imss_entidad" => $prv_imss_entidad,
            "prv_imss_rfc" => $prv_imss_rfc,
            "aport_ssocial_habilita_carga_docs" => $queryAportSegSocialPagoDone ? true : false,
            "aport_ssocial_factura_doc_xml" => $aport_ssocial_factura_doc_xml,
            "aport_ssocial_url_doc_xml" => $aport_ssocial_url_doc_xml,
            "aport_ssocial_factura_doc_pdf" => $aport_ssocial_factura_doc_pdf,
            "aport_ssocial_url_doc_pdf" => $aport_ssocial_url_doc_pdf,
            "aport_ssocial_fact_new_xml" => null,
            "aport_ssocial_fact_new_pdf" => null,
            "aport_ssocial_valida_xml" => '',
            "aport_ssocial_cfdi_comprobante" => [],
            "aport_ssocial_cfdi_emisor" => [],
            "aport_ssocial_cfdi_receptor" => [],
            "aport_ssocial_cfdi_conceptos" => [],
            "aport_ssocial_cfdi_complemento" => [],
  
            "prv_infonavit_token" => $prv_infonavit_token,
            "prv_infonavit_folio" => $prv_infonavit_folio,
            "prv_infonavit_entidad" => $prv_infonavit_entidad,
            "prv_infonavit_rfc" => $prv_infonavit_rfc,
            "aport_infonavit_habilita_carga_docs" => $queryAportSegSocialPagoDone && $fct_infonavit_periodos ? true : false,
            "aport_infonavit_factura_doc_xml" => $fct_infonavit_factura_doc_xml,
            "aport_infonavit_url_doc_xml" => $fct_infonavit_url_doc_xml,
            "aport_infonavit_factura_doc_pdf" => $fct_infonavit_factura_doc_pdf,
            "aport_infonavit_url_doc_pdf" => $fct_infonavit_url_doc_pdf,
            "aport_infonavit_fact_new_xml" => null,
            "aport_infonavit_fact_new_pdf" => null,
            "aport_infonavit_valida_xml" => '',
            "aport_infonavit_cfdi_comprobante" => [],
            "aport_infonavit_cfdi_emisor" => [],
            "aport_infonavit_cfdi_receptor" => [],
            "aport_infonavit_cfdi_conceptos" => [],
            "aport_infonavit_cfdi_complemento" => [],
            "puede_eliminar" => !$queryAportSegSocialPagoDone ? true : false,
          );
          $lista_aports[] = $row;
        }
  
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'aportaciones' => $lista_aports
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function aportacionSeguridadSocialImpuestosSeguimientoOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'aport_ssocial_token' => 'required|string',
      'aport_ssocial_ord_pago_token' => 'required|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parametros de busqueda recibidos son incorrectos',
				'errors' => $validate->errors()
			);
    } else {
      $aport_ssocial_token = $request->input('aport_ssocial_token');
			$aport_ssocial_ord_pago_token = $request->input('aport_ssocial_ord_pago_token');

      $queryIMSSOrdenPago = DB::table("vhum_aportaciones_seguridad_social_main AS social_main")
      ->join("fnzs_pagos_orden AS order", "social_main.id", "=", "order.aportacion_seguridad_social")
      ->join("main_empresas AS emp", "social_main.aport_ssocial_empresa", "=", "emp.id")
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'social_main.aport_ssocial_status' => TRUE,
        'social_main.aport_ssocial_token' => $aport_ssocial_token,
        'order.token_ordenPago' => $aport_ssocial_ord_pago_token,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();
      
      if ($queryIMSSOrdenPago->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron aportaciones de seguridad social registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $orden_pago_registro = array();

				foreach ($queryIMSSOrdenPago as $rOrdPag) {
					//da_te_default_timezone_set($rOrdPag->zona_horaria);
          $folio_interior = $rOrdPag->aport_ssocial_folio_interior;
          $post_folio = $rOrdPag->aport_ssocial_subfolio;
					$autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;
					$fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? gmdate('Y-m-d H:i:s', $rOrdPag->fecha_autorizacion_pay) : "---";
					$status_pay_bool = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? true : false;

					$orden_emisor_emp = $rOrdPag->abrev_nombre;

          $importe_total_anticipo = 0;
					$importe_total_inicial = 0;
					$orden_moneda_inicial_name = $rOrdPag->aport_ssocial_moneda;
					$orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->aport_ssocial_moneda);

					$importe_autorizado_inicial = 0;
					$orden_moneda_autorizado_inicial_tkn = $rOrdPag->aport_ssocial_moneda;
					$orden_moneda_autorizado_inicial_name = $rOrdPag->aport_ssocial_moneda;
					$orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($rOrdPag->aport_ssocial_moneda);

					$importe_autorizado_final = 0;
					$orden_moneda_autorizado_final_name = $rOrdPag->aport_ssocial_moneda;
					$orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($rOrdPag->aport_ssocial_moneda);
          
          $totales_cuotas_totales = DB::table("imss_cuotas_detalle AS imsDet")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
          ->where('social_main.aport_ssocial_token',$rOrdPag->aport_ssocial_token)
          ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
          ->sum('imsDet.total');

          $importe_total_inicial = $totales_cuotas_totales;
          $importe_autorizado_inicial = $totales_cuotas_totales;
          $importe_autorizado_final = $totales_cuotas_totales;

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
            "factura_relacionada_token" => $rOrdPag->aport_ssocial_token,
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

        $pagos_realizados_registro = $JwtAuth->pagosDoneBYOrdenDesglose($aport_ssocial_ord_pago_token,$empresa,$usuario);

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

  public function desgloseAportacionSeguridadSocial(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'aport_ssocial_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parametros de busqueda recibidos son incorrectos',
				'errors' => $validate->errors()
			);
    } else {
      $aport_ssocial_token = $request->input('aport_ssocial_token');
      
      $queryIMSSAportacion = AportacionesIMSSModelo::join("main_empresas AS emp", "vhum_aportaciones_seguridad_social_main.aport_ssocial_empresa", "emp.id")
      ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "vhum_aportaciones_seguridad_social_main.aport_ssocial_registro_patronal", "c_trab.id")
      ->where([
        'vhum_aportaciones_seguridad_social_main.aport_ssocial_token' => $aport_ssocial_token,
        'vhum_aportaciones_seguridad_social_main.aport_ssocial_status' => TRUE,
        'emp.empresa_token' => $empresa,
      ])
      ->get();

      if ($queryIMSSAportacion->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron aportaciones de seguridad social registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $lista_aports = array();

        foreach ($queryIMSSAportacion as $vIMSSAp) {
          $folio_interior = $vIMSSAp->aport_ssocial_folio_interior;
          $post_folio = $vIMSSAp->aport_ssocial_subfolio;
          $aport_ssocial_moneda = $vIMSSAp->aport_ssocial_moneda;
          $aport_ssocial_moneda_decimales = $JwtAuth->getMonedaAPI($vIMSSAp->aport_ssocial_moneda);

          $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');
          $periodo_pago_seguros_imss = !is_null($vIMSSAp->periodo_pago_seguros_imss_anio) && !is_null($vIMSSAp->periodo_pago_seguros_imss_mes) ? ucfirst(Carbon::create($vIMSSAp->periodo_pago_seguros_imss_anio, $vIMSSAp->periodo_pago_seguros_imss_mes, 1)->locale('es')->isoFormat('MMMM YYYY')) : '';
          $pago_rcv_infonavit_inicio = !is_null($vIMSSAp->pago_rcv_infonavit_inicio) ? ucfirst(Carbon::createFromTimestamp($vIMSSAp->pago_rcv_infonavit_inicio)->locale('es')->translatedFormat('F')) : '';
          $pago_rcv_infonavit_fin = !is_null($vIMSSAp->pago_rcv_infonavit_fin) ? ucfirst(Carbon::createFromTimestamp($vIMSSAp->pago_rcv_infonavit_fin)->locale('es')->translatedFormat('F')) : '';
          $pago_rcv_infonavit = $pago_rcv_infonavit_inicio != '' && $pago_rcv_infonavit_fin != '' ? "$pago_rcv_infonavit_inicio - $pago_rcv_infonavit_fin" : '';

          $listCuotasDesglose = array();
          $queryCuotasDesglose = DB::table("imss_cuotas_detalle AS imsDet")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->get();

          foreach ($queryCuotasDesglose as $vDesgCuot) {
            $tipo_label = (stripos($vDesgCuot->label, 'SUBTOTAL') !== false) ? 'subtotal' : 'input';
            $desg_row = array(
              "type" => $tipo_label,
              "label" => $vDesgCuot->label,
              "patronal" => number_format($vDesgCuot->patronal,$aport_ssocial_moneda_decimales,'.',''),
              "obrera" => number_format($vDesgCuot->obrera,$aport_ssocial_moneda_decimales,'.',''),
              "total" => number_format($vDesgCuot->total,$aport_ssocial_moneda_decimales,'.',''),
            );
            $listCuotasDesglose[] = $desg_row;
          }

          array_unshift($listCuotasDesglose,[
            "type" => "section",
            "label" => "ENFERMEDADES Y MATERNIDAD",
          ]);

          for ($dc=0; $dc < count($listCuotasDesglose); $dc++) { 
            if (isset($listCuotasDesglose[$dc]['label']) && $listCuotasDesglose[$dc]['label'] === 'SUBTOTAL RCV') {
              array_splice($listCuotasDesglose,$dc + 1, 0, [[
                "type" => "label_aport",
                "label" => ""
              ]]);
              break;
            }
          }

          $totales_cuotas_patronales = DB::table("imss_cuotas_detalle AS imsDet")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
          ->sum('imsDet.patronal');

          $totales_cuotas_obreras = DB::table("imss_cuotas_detalle AS imsDet")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
          ->sum('imsDet.obrera');

          $totales_cuotas_totales = DB::table("imss_cuotas_detalle AS imsDet")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
          ->sum('imsDet.total');

          $imssAportAnexos = array();
          $queryDocsIMSSAport = DB::table("sos_documentos AS docs")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "docs.aportacion_seguridad_social", "=", "social_main.id")
          ->where([
            "docs.status_documento" => TRUE,
            "social_main.aport_ssocial_token" => $vIMSSAp->aport_ssocial_token
          ])
          ->get();

          foreach ($queryDocsIMSSAport as $xDoc) {
            $nombre = $JwtAuth->desencriptar($xDoc->nombre_documento);
            $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

            $rowXML = array(
              "token_documento" => $xDoc->token_documento,
              "tipo_documental" => $xDoc->tipo_documento,
              "extension" => $extension,
              "name_documento" => $nombre,
              "url" => "https://downloads.sos-mexico.com.mx/aportaciones_de_seguridad_social/$folio_aport/$xDoc->token_documento",
              "eliminacion_proceso" => false
            );
            $imssAportAnexos[] = $rowXML;
          }

          $row = array(
            "aport_ssocial_token" => $vIMSSAp->aport_ssocial_token,
            "aport_ssocial_folio" => $folio_aport,
            "aport_ssocial_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vIMSSAp->aport_ssocial_fecha_contabilizacion),
            "aport_ssocial_fecha_presentacion" => $JwtAuth->mostrarUnixAFechaMexico($vIMSSAp->aport_ssocial_fecha_presentacion),
            "aport_ssocial_registro_patronal_uuid" => $vIMSSAp->centrotrab_uuid,
            "aport_ssocial_registro_patronal_serie" => $vIMSSAp->centrotrab_clave_registro_patronal_imss,

            "periodo_pago_seguros_imss_all" => $periodo_pago_seguros_imss,
            "periodo_pago_seguros_imss_anio" => !is_null($vIMSSAp->periodo_pago_seguros_imss_anio) ? $vIMSSAp->periodo_pago_seguros_imss_anio : '',
            "periodo_pago_seguros_imss_mes" => !is_null($vIMSSAp->periodo_pago_seguros_imss_mes) ? $vIMSSAp->periodo_pago_seguros_imss_mes : '',

            "pago_rcv_infonavit_all" => $pago_rcv_infonavit,
            "pago_rcv_infonavit_inicio" => !is_null($vIMSSAp->pago_rcv_infonavit_inicio) ? date('Y-m-d',$vIMSSAp->pago_rcv_infonavit_inicio) : '',
            "pago_rcv_infonavit_fin" => !is_null($vIMSSAp->pago_rcv_infonavit_fin) ? date('Y-m-d',$vIMSSAp->pago_rcv_infonavit_fin) : '',
            
            "folio_sua" => $vIMSSAp->folio_sua,
            "clave_recepcion_archivo_pago" => $vIMSSAp->clave_recepcion_archivo_pago,
            "propuesta_fecha_limite_pago" => $JwtAuth->mostrarUnixAFechaMexico($vIMSSAp->propuesta_fecha_limite_pago),
            "linea_captura_sipare" => $vIMSSAp->linea_captura_sipare,
            
            "propuesta_s_m_g_d_f_edit" => number_format($vIMSSAp->propuesta_s_m_g_d_f,$aport_ssocial_moneda_decimales,'.',''),
            "propuesta_s_m_g_d_f_format" => "$ ".number_format($vIMSSAp->propuesta_s_m_g_d_f,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",

            "propuesta_fecha_salario_minimo_pago" => $JwtAuth->mostrarUnixAFechaMexico($vIMSSAp->propuesta_fecha_salario_minimo_pago),

            "propuesta_valor_uma_edit" => number_format($vIMSSAp->propuesta_valor_uma,$aport_ssocial_moneda_decimales,'.',''),
            "propuesta_valor_uma_format" => "$ ".number_format($vIMSSAp->propuesta_valor_uma,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",

            "propuesta_num_de_cotizantes" => $vIMSSAp->propuesta_num_de_cotizantes,
            "propuesta_num_dias_a_cotizar" => $vIMSSAp->propuesta_num_dias_a_cotizar,
            "propuesta_num_de_acreditados" => $vIMSSAp->propuesta_num_de_acreditados,
            "cuotasDesglose" => $listCuotasDesglose,
            "cuotas_patronales" => "$ ".number_format($totales_cuotas_patronales,$aport_ssocial_moneda_decimales,'.','')." $aport_ssocial_moneda",
            "cuotas_obreras" => "$ ".number_format($totales_cuotas_obreras,$aport_ssocial_moneda_decimales,'.','')." $aport_ssocial_moneda",
            "cuotas_totales" => "$ ".number_format($totales_cuotas_totales,$aport_ssocial_moneda_decimales,'.','')." $aport_ssocial_moneda",
            "observaciones" => $JwtAuth->desencriptar($vIMSSAp->observaciones),
            "vinculacion_a_pagos" => false,//$queryNominaPago > 0 ? true : false,
            "docsAnexos" => $imssAportAnexos
          );
          $lista_aports[] = $row;
        }
        
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'aportaciones' => $lista_aports
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaAportacionSeguridadSocial(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'aport_ssocial_token' => 'required|string',
      'fecha_contabilizacion' => 'required|string',
      'fecha_presentacion' => 'required|string',
      'registro_patronal' => 'required|string', 
      'periodo_pago_seguros_imss_anio' => 'numeric', 
      'periodo_pago_seguros_imss_mes' => 'numeric',
      'pago_rcv_infonavit_inicio' => 'nullable|string',
      'pago_rcv_infonavit_fin' => 'nullable|string',
      'folio_sua' => 'required|string',
      'clave_recepcion_archivo_pago' => 'required|string', 
      'propuesta_fecha_limite_pago' => 'required|string', 
      'linea_captura_sipare' => 'required|string', 
      'propuesta_s_m_g_d_f' => 'required|string', 
      'propuesta_fecha_salario_minimo_pago' => 'required|string', 
      'propuesta_valor_uma' => 'required|string', 
      'propuesta_num_de_cotizantes' => 'required|numeric', 
      'propuesta_num_dias_a_cotizar' => 'required|numeric', 
      'propuesta_num_de_acreditados' => 'required|numeric', 
      'desglose_total_cuotas' => 'required|string',
      'observaciones' => 'required|string',
      'docs_eliminar' => 'array',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Faltan campos obligatorios o el formato es incorrecto.'.$validate->errors(),
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $aport_ssocial_token = $request->input('aport_ssocial_token');
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $fecha_presentacion = $request->input('fecha_presentacion');
      $registro_patronal = $request->input('registro_patronal');
      $periodo_pago_seguros_imss_anio = $request->input('periodo_pago_seguros_imss_anio');
      $periodo_pago_seguros_imss_mes = $request->input('periodo_pago_seguros_imss_mes');
      $pago_rcv_infonavit_inicio = $request->input('pago_rcv_infonavit_inicio');
      $pago_rcv_infonavit_fin = $request->input('pago_rcv_infonavit_fin');
      $folio_sua = $request->input('folio_sua');
      $clave_recepcion_archivo_pago = $request->input('clave_recepcion_archivo_pago');
      $propuesta_fecha_limite_pago = $request->input('propuesta_fecha_limite_pago');
      $linea_captura_sipare = $request->input('linea_captura_sipare');
      $propuesta_s_m_g_d_f = $request->input('propuesta_s_m_g_d_f');
      $propuesta_fecha_salario_minimo_pago = $request->input('propuesta_fecha_salario_minimo_pago');
      $propuesta_valor_uma = $request->input('propuesta_valor_uma');
      $propuesta_num_de_cotizantes = $request->input('propuesta_num_de_cotizantes');
      $propuesta_num_dias_a_cotizar = $request->input('propuesta_num_dias_a_cotizar');
      $propuesta_num_de_acreditados = $request->input('propuesta_num_de_acreditados');
      $desglose_total_cuotas = json_decode($request->input('desglose_total_cuotas'), true);
      // 3. Verifica que el JSON sea válido antes de seguir
      if (json_last_error() !== JSON_ERROR_NONE) {
          return response()->json(['status' => 'error', 'message' => 'Error en el formato de cuotas'], 200);
      }
      $observaciones = $request->input('observaciones');
      $docs_eliminar = $request->input('docs_eliminar');

      $OKFechaCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $OKFechaPresentacion = isset($fecha_presentacion) && !empty($fecha_presentacion) && preg_match($JwtAuth->filtroFecha(),$fecha_presentacion);
      $OKRegistroPatronal = isset($registro_patronal) && !empty($registro_patronal) && preg_match($JwtAuth->filtroAlfaNumerico(),$registro_patronal);

      $OKPeriodoPagoSegIMSSAnio = isset($periodo_pago_seguros_imss_anio) && !empty($periodo_pago_seguros_imss_anio) && preg_match($JwtAuth->filtroNumericoSimple(),$periodo_pago_seguros_imss_anio);
      $OKPeriodoPagoSegIMSSMes = isset($periodo_pago_seguros_imss_mes) && !empty($periodo_pago_seguros_imss_mes) && preg_match($JwtAuth->filtroNumericoSimple(),$periodo_pago_seguros_imss_mes);
      $OKPeriodoPagoSegIMSS = $OKPeriodoPagoSegIMSSAnio && $OKPeriodoPagoSegIMSSMes;

      $OKRcvInfonInicio = isset($pago_rcv_infonavit_inicio) && !empty($pago_rcv_infonavit_inicio) && preg_match($JwtAuth->filtroFecha(),$pago_rcv_infonavit_inicio);
      $OKRcvInfonFin = isset($pago_rcv_infonavit_fin) && !empty($pago_rcv_infonavit_fin) && preg_match($JwtAuth->filtroFecha(),$pago_rcv_infonavit_fin);
      $OKPagoRCVInfonavit = $OKRcvInfonInicio && $OKRcvInfonFin && ($JwtAuth->convierteFechaEpoc($pago_rcv_infonavit_fin) >= $JwtAuth->convierteFechaEpoc($pago_rcv_infonavit_inicio));

      $OKFolioSua = isset($folio_sua) && !empty($folio_sua) && preg_match($JwtAuth->filtroNumerico(),$folio_sua);
      $OKClaveRecepcionArchivoPago = isset($clave_recepcion_archivo_pago) && !empty($clave_recepcion_archivo_pago) && preg_match($JwtAuth->filtroNumerico(),$clave_recepcion_archivo_pago);
      $OKPropuestaFechaLimitePago = isset($propuesta_fecha_limite_pago) && !empty($propuesta_fecha_limite_pago) && preg_match($JwtAuth->filtroFecha(),$propuesta_fecha_limite_pago);
      $OKPropuestaRefDEPagoSIPARE = isset($linea_captura_sipare) && !empty($linea_captura_sipare) && preg_match($JwtAuth->filtroAlfaNumerico(),$linea_captura_sipare);
      $OKPropuestaSMGDF = isset($propuesta_s_m_g_d_f) && is_numeric($propuesta_s_m_g_d_f) && preg_match($JwtAuth->filtroCostoPrecio(),$propuesta_s_m_g_d_f);
      $OKPropuestaFechaSalarioMinimoPago = isset($propuesta_fecha_salario_minimo_pago) && !empty($propuesta_fecha_salario_minimo_pago) && preg_match($JwtAuth->filtroFecha(),$propuesta_fecha_salario_minimo_pago);
      $OKNominaPropuestaValorUMA = isset($propuesta_valor_uma) && is_numeric($propuesta_valor_uma) && preg_match($JwtAuth->filtroCostoPrecio(),$propuesta_valor_uma);
      $OKPropuestaNumCotizantes = isset($propuesta_num_de_cotizantes) && !empty($propuesta_num_de_cotizantes) && preg_match($JwtAuth->filtroNumericoSimple(),$propuesta_num_de_cotizantes);
      $OKPropuestaNumDiasCotizar = isset($propuesta_num_dias_a_cotizar) && !empty($propuesta_num_dias_a_cotizar) && preg_match($JwtAuth->filtroNumericoSimple(),$propuesta_num_dias_a_cotizar);
      $OKPropuestaNumAcreditados = isset($propuesta_num_de_acreditados) && !empty($propuesta_num_de_acreditados) && preg_match($JwtAuth->filtroNumericoSimple(),$propuesta_num_de_acreditados);
      $OKDesgloseTotalCuotas = isset($desglose_total_cuotas) && is_array($desglose_total_cuotas) && count($desglose_total_cuotas);
      $OKObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);
      $OKDocsEliminar = isset($docs_eliminar) && is_array($docs_eliminar) && count($docs_eliminar) > 0;

      if ($OKFechaCont && $OKFechaPresentacion && $OKRegistroPatronal && $OKFolioSua && $OKClaveRecepcionArchivoPago && $OKPropuestaFechaLimitePago && $OKPropuestaRefDEPagoSIPARE && $OKPropuestaSMGDF && $OKPropuestaFechaSalarioMinimoPago && 
        $OKNominaPropuestaValorUMA && $OKPropuestaNumCotizantes && $OKPropuestaNumDiasCotizar && $OKDesgloseTotalCuotas && $OKObservacion) {
        $fechaSistema = time();
        $queryIMSSAportacion = AportacionesIMSSModelo::join("main_empresas AS emp", "vhum_aportaciones_seguridad_social_main.aport_ssocial_empresa", "emp.id")
        ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "vhum_aportaciones_seguridad_social_main.aport_ssocial_registro_patronal", "c_trab.id")
        ->where([
          'vhum_aportaciones_seguridad_social_main.aport_ssocial_token' => $aport_ssocial_token,
          'vhum_aportaciones_seguridad_social_main.aport_ssocial_status' => TRUE,
          'emp.empresa_token' => $empresa,
        ])
        ->get();
        
        foreach ($queryIMSSAportacion as $vIMSSAp) {
          $folio_interior = $vIMSSAp->aport_ssocial_folio_interior;
          $post_folio = $vIMSSAp->aport_ssocial_subfolio;
          $aport_ssocial_moneda = $vIMSSAp->aport_ssocial_moneda;
          $aport_ssocial_moneda_decimales = $JwtAuth->getMonedaAPI($vIMSSAp->aport_ssocial_moneda);
          $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');
          $filepath = $vIMSSAp->root_tkn . "/0004-vhm/aportaciones_seguridad_social/$vIMSSAp->aport_ssocial_fecha_registro-$folio_aport/anexos/";

          $queryUpdateAportMain = DB::table('vhum_aportaciones_seguridad_social_main')
          ->where('aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->limit(1)->update(array(
            "aport_ssocial_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
            "aport_ssocial_fecha_presentacion" => $JwtAuth->convierteFechaEpoc($fecha_presentacion),
            "aport_ssocial_registro_patronal" => DB::table("vhum_centros_de_trabajo_catalogo")->where('centrotrab_clave_registro_patronal_imss',$registro_patronal)->value('id'),
            "periodo_pago_seguros_imss_anio" => $OKPeriodoPagoSegIMSS ? $periodo_pago_seguros_imss_anio : NULL,
            "periodo_pago_seguros_imss_mes" => $OKPeriodoPagoSegIMSS ? $periodo_pago_seguros_imss_mes : NULL,
            "pago_rcv_infonavit_inicio" => $OKPagoRCVInfonavit ? $JwtAuth->convierteFechaEpoc($pago_rcv_infonavit_inicio) : NULL,
            "pago_rcv_infonavit_fin" => $OKPagoRCVInfonavit ? $JwtAuth->convierteFechaEpoc($pago_rcv_infonavit_fin) : NULL,
            "folio_sua" => $folio_sua,
            "clave_recepcion_archivo_pago" => $clave_recepcion_archivo_pago,
            "propuesta_fecha_limite_pago" => $JwtAuth->convierteFechaEpoc($propuesta_fecha_limite_pago),
            "linea_captura_sipare" => $linea_captura_sipare,
            "propuesta_s_m_g_d_f" => $propuesta_s_m_g_d_f,
            "propuesta_fecha_salario_minimo_pago" => $JwtAuth->convierteFechaEpoc($propuesta_fecha_salario_minimo_pago),
            "propuesta_valor_uma" => $propuesta_valor_uma,
            "propuesta_num_de_cotizantes" => $propuesta_num_de_cotizantes,
            "propuesta_num_dias_a_cotizar" => $propuesta_num_dias_a_cotizar,
            "propuesta_num_de_acreditados" => $OKPropuestaNumAcreditados ? $propuesta_num_de_acreditados : 0,
            "observaciones" => $JwtAuth->encriptar($observaciones),
          ));

          $queryNominaPago = DB::table("fnzs_pagos_orden AS order")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "order.aportacion_seguridad_social", "=", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->limit(1)->update(
            array(
              "order.doc_anterior_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
            )
          );

          $aporteMainID = DB::table("vhum_aportaciones_seguridad_social_main")->where('aport_ssocial_token',$vIMSSAp->aport_ssocial_token)->value('id');
          
          if ($OKDocsEliminar) {
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
                $token_documento = $JwtAuth->encriptarToken($aporteMainID,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                $insertDocSoli = DB::table("sos_documentos")->insert(
                  array(
                    "token_documento" => $token_documento,
                    "fecha_carga" => time(),
                    "modulo" => "pagos",
                    "folio_modulo" => "IMP-NOMI-EVID".$select_folio_doc[0]->folio,
                    "tipo_documento" => "an",
                    "nombre_documento" => $JwtAuth->encriptar($doc_name),
                    "aportacion_seguridad_social" => $aporteMainID,
                    "status_documento" => TRUE,
                  )
                );
              }
            }
          }

          $cuotasAngular = collect($desglose_total_cuotas)
          ->filter(function ($item) {
            return array_key_exists('patronal',$item) || array_key_exists('obrera',$item) || array_key_exists('total',$item);
          })
          ->map(function ($item) {
            return [
              'label'     => $item['label'] ?? null,
              'patronal'  => $item['patronal'] ?? 0,
              'obrera'    => $item['obrera'] ?? 0,
              'total'     => $item['total'] ?? 0,
            ];
          })
          ->keyBy('label');

          $queryCuotasDesglose = DB::table("imss_cuotas_detalle AS imsDet")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->get(['imsDet.id','imsDet.label','imsDet.patronal','imsDet.obrera','imsDet.total'])
          ->keyBy('label');
          
          foreach ($cuotasAngular as $label => $data) {
            // 👉 Si ya existe en BD
            if ($queryCuotasDesglose->has($label)) {
              $row = $queryCuotasDesglose[$label];
              // 🔍 Detectar cambios reales
              if (
                (float)$row->patronal !== $data['patronal'] ||
                (float)$row->obrera   !== $data['obrera'] ||
                (float)$row->total    !== $data['total']
              ) {
                DB::table('imss_cuotas_detalle')
                ->where('id', $row->id)
                ->update([
                  'patronal' => $data['patronal'],
                  'obrera'   => $data['obrera'],
                  'total'    => $data['total'],
                ]);
              }
            } 
            //else {
            //  DB::table('imss_cuotas_detalle')->insert([
            //    'aportaciones_main' => $aporteMainID,
            //    'type'      => null,
            //    'label'     => $label,
            //    'patronal'  => $data['patronal'],
            //    'obrera'    => $data['obrera'],
            //    'total'     => $data['total'],
            //  ]);
            //}
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Reporte de aportación de seguridad social con el folio $folio_aport ha sido actualizado satisfactoriamente"
          );
        }
      } else {
        $mensaje_error = "";
        if (!$OKFechaCont) $mensaje_error = "Error en fecha de contabilización, intentelo nuevamente o comuniquese a soporte";
        if (!$OKFechaPresentacion) $mensaje_error = "Error en fecha de presentación, intentelo nuevamente o comuniquese a soporte";
        if (!$OKRegistroPatronal) $mensaje_error = "Error al seleccionar clave de registro patronal del IMSS, intentelo nuevamente o comuniquese a soporte";
        if (!$OKFolioSua) $mensaje_error = "Error al registrar folio del SUA, intentelo nuevamente o comuniquese a soporte";
        if (!$OKClaveRecepcionArchivoPago) $mensaje_error = "Error al registrar clave de recepción de archivo de pago, intentelo nuevamente o comuniquese a soporte";
        if (!$OKPropuestaFechaLimitePago) $mensaje_error = "Error al registrar fecha límite de pago, intentelo nuevamente o comuniquese a soporte";
        if (!$OKPropuestaRefDEPagoSIPARE) $mensaje_error = "Error al registrar referencia de pago (Línea de Captura SIPARE), intentelo nuevamente o comuniquese a soporte";
        if (!$OKPropuestaSMGDF) $mensaje_error = "Error al registrar S.M.G.D.F, intentelo nuevamente o comuniquese a soporte";
        if (!$OKPropuestaFechaSalarioMinimoPago) $mensaje_error = "Error al registrar Fecha SAL. MIN., intentelo nuevamente o comuniquese a soporte";
        if (!$OKNominaPropuestaValorUMA) $mensaje_error = "Error al registrar complementarias (Impuesto a cargo), intentelo nuevamente o comuniquese a soporte";
        if (!$OKPropuestaNumCotizantes) $mensaje_error = "Error al registrar valor UMA, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDesgloseTotalCuotas) $mensaje_error = "Error al registrar la información detallada del importe total de cuotas, intentelo nuevamente o comuniquese a soporte";
        if (!$OKObservacion) $mensaje_error = "Error al registrar observaciones de este reporte, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }  

  public function aportacionSeguridadSocialCargaCFDIS(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'aport_ssocial_token' => 'required',
      'imss' => 'required|array'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parametros de busqueda recibidos son incorrectos',
				'errors' => $validate->errors()
			);
    } else {
		  $aport_ssocial_token = $request->input('aport_ssocial_token');
		  $imss = $request->input('imss');

      $queryIMSSAportacion = AportacionesIMSSModelo::join("main_empresas AS emp", "vhum_aportaciones_seguridad_social_main.aport_ssocial_empresa", "emp.id")
      ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "vhum_aportaciones_seguridad_social_main.aport_ssocial_registro_patronal", "c_trab.id")
      ->where([
        'vhum_aportaciones_seguridad_social_main.aport_ssocial_token' => $aport_ssocial_token,
        'vhum_aportaciones_seguridad_social_main.aport_ssocial_status' => TRUE,
        'emp.empresa_token' => $empresa,
      ])
      ->get();
      
      if ($queryIMSSAportacion->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron aportaciones de seguridad social registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        foreach ($queryIMSSAportacion as $vIMSSAp) {
          $aporteMainID = DB::table("vhum_aportaciones_seguridad_social_main")->where('aport_ssocial_token',$vIMSSAp->aport_ssocial_token)->value('id');
          $folio_interior = $vIMSSAp->aport_ssocial_folio_interior;
          $post_folio = $vIMSSAp->aport_ssocial_subfolio;
          $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');
          $count_imss = 0;
          
          foreach ($imss as $r_aport => $rAport) {
            $archivo_xml = $request->file("imss.$r_aport.aport_ssocial_fact_new_xml");
            $archivo_pdf = $request->file("imss.$r_aport.aport_ssocial_fact_new_pdf");
    
            $aport_ssocial_cfdi_comprobante = $rAport["aport_ssocial_cfdi_comprobante"];
            $aport_ssocial_cfdi_emisor = $rAport["aport_ssocial_cfdi_emisor"];
            $aport_ssocial_cfdi_receptor = $rAport["aport_ssocial_cfdi_receptor"];
            $aport_ssocial_cfdi_conceptos = $rAport["aport_ssocial_cfdi_conceptos"];
            $aport_ssocial_cfdi_complemento = $rAport["aport_ssocial_cfdi_complemento"];
            
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
    
            $data_comprobante = json_decode($aport_ssocial_cfdi_comprobante, true);
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
              $data_emisor = json_decode($aport_ssocial_cfdi_emisor, true);
              foreach ($data_emisor as $CFDIe) {
                $cfdi_emisor_rfc = $CFDIe["EmisorRfc"];
                $cfdi_emisor_nombre = $CFDIe["EmisorNombre"];
                $cfdi_emisor_regimen_fiscal = $CFDIe["EmisorRegimenFiscal"];
              }
    
              $cfdi_receptor_rfc = '';
              $cfdi_receptor_domicilio_fiscal = '';
              $cfdi_receptor_regimen_fiscal = '';
              $cfdi_receptor_uso_del_cfdi = '';
              $data_receptor = json_decode($aport_ssocial_cfdi_receptor, true);
              foreach ($data_receptor as $CFDIReceptor) {
                $cfdi_receptor_rfc = $CFDIReceptor["ReceptorRfc"];
                $cfdi_receptor_domicilio_fiscal = $CFDIReceptor["ReceptorDomicilioFiscal"];
                $cfdi_receptor_regimen_fiscal = $CFDIReceptor["ReceptorRegimenFiscal"];
                $cfdi_receptor_uso_del_cfdi = $CFDIReceptor["ReceptorUsoCFDI"];
              }
    
              $data_complemento = json_decode($aport_ssocial_cfdi_complemento, true);
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
                $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$aporteMainID.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
                $insertCFDIAport = DB::table('cfdi_comprobantes_fiscales')
                ->insert(array(
                  "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
                  "origen_proceso" => "aportaciones",
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
                $insertCFDIVincNomina = DB::table('cfdi_vinculacion_aport_seg_social_imss')//cfdi__estructura
                ->insert(array(
                  "comprobante_fiscal" => $comprobante_fiscal_reg,
                  "comprobante_tipo" => "imss",	
                  "aport_seg_social_vinculado" => $aporteMainID,
                ));
    
                $data_conceptos = json_decode($aport_ssocial_cfdi_conceptos, true);
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
            $filepath = $vIMSSAp->root_tkn."/0004-vhm/aportaciones_seguridad_social/$vIMSSAp->aport_ssocial_fecha_registro-$folio_aport/anexos/";
            
            if ($archivo_xml) {
              $nombre_original = $archivo_xml->getClientOriginalName();
              $ext_doc = $archivo_xml->getClientOriginalExtension();
    
              $documento_crypt = $JwtAuth->encriptar($nombre_original);
              $token_documento = $JwtAuth->encriptarToken($aporteMainID, $ext_doc, $nombre_original);
    
              $insertDocSoli = DB::table("sos_documentos")->insert([
                "token_documento" => $token_documento,
                "fecha_carga" => time(),
                "modulo" => "reembolsos",
                "folio_modulo" => "APORT-IMSS-CFDI-XML",
                "tipo_documento" => "xml",
                "nombre_documento" => $documento_crypt,
                "extension_documento" => $ext_doc,
                "aportacion_seguridad_social" => $aporteMainID,
                "status_documento" => true,
              ]);
    
              if ($insertDocSoli) {
                DB::table('vhum_aportaciones_seguridad_social_main')->where("aport_ssocial_token", $vIMSSAp->aport_ssocial_token)
                ->limit(1)->update(array("aport_ssocial_fact_xml" => $documento_crypt));
    
                $archivo_xml->storeAs("public/root/$filepath", $nombre_original);
              }
            }
    
            if ($archivo_pdf) {
              $nombre_original = $archivo_pdf->getClientOriginalName();
              $ext_doc = $archivo_pdf->getClientOriginalExtension();
    
              $documento_crypt = $JwtAuth->encriptar($nombre_original);
              $token_documento = $JwtAuth->encriptarToken($aporteMainID, $ext_doc, $nombre_original);
    
              $insertDocSoli = DB::table("sos_documentos")->insert([
                "token_documento" => $token_documento,
                "fecha_carga" => time(),
                "modulo" => "reembolsos",
                "folio_modulo" => "APORT-IMSS-CFDI-PDF",
                "tipo_documento" => "pdf",
                "nombre_documento" => $documento_crypt,
                "extension_documento" => $ext_doc,
                "aportacion_seguridad_social" => $aporteMainID,
                "status_documento" => true,
              ]);
    
              if ($insertDocSoli) {
                DB::table('vhum_aportaciones_seguridad_social_main')->where("aport_ssocial_token", $vIMSSAp->aport_ssocial_token)
                ->limit(1)->update(array("aport_ssocial_fact_pdf" => $documento_crypt));
                $archivo_pdf->storeAs("public/root/$filepath", $nombre_original);
              }
            }
            ++$count_imss;
            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$token_nomina_recibo]);
          }
    
          if ($count_imss == count($imss)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "CFDI de la aportación de seguridad social con folio $folio_aport ha sido cargado correctamente"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Error al cargar CFDI de la aportación de seguridad social con el folio $folio_aport, intente nuevamente o comuniquese a soporte"
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  } 

  public function aportacionInfonavitCargaCFDIS(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'aport_ssocial_token' => 'required|string',
      'infonavit' => 'required|array',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parametros de busqueda recibidos son incorrectos',
				'errors' => $validate->errors()
			);
    } else {
		  $aport_ssocial_token = $request->input('aport_ssocial_token');
		  $infonavit = $request->input('infonavit');

      $queryIMSSAportacion = AportacionesIMSSModelo::join("main_empresas AS emp", "vhum_aportaciones_seguridad_social_main.aport_ssocial_empresa", "emp.id")
      ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "vhum_aportaciones_seguridad_social_main.aport_ssocial_registro_patronal", "c_trab.id")
      ->where([
        'vhum_aportaciones_seguridad_social_main.aport_ssocial_token' => $aport_ssocial_token,
        'vhum_aportaciones_seguridad_social_main.aport_ssocial_status' => TRUE,
        'emp.empresa_token' => $empresa,
      ])
      ->get();
      
      if ($queryIMSSAportacion->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron aportaciones de seguridad social registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        foreach ($queryIMSSAportacion as $vInfonavitAp) {
          $aporteMainID = DB::table("vhum_aportaciones_seguridad_social_main")->where('aport_ssocial_token',$vInfonavitAp->aport_ssocial_token)->value('id');
          $folio_interior = $vInfonavitAp->aport_ssocial_folio_interior;
          $post_folio = $vInfonavitAp->aport_ssocial_subfolio;
          $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');
          $count_infonavit = 0;
          
          foreach ($infonavit as $r_aport => $rAport) {
            $archivo_xml = $request->file("infonavit.$r_aport.aport_infonavit_fact_new_xml");
            $archivo_pdf = $request->file("infonavit.$r_aport.aport_infonavit_fact_new_pdf");
    
            $aport_infonavit_cfdi_comprobante = $rAport["aport_infonavit_cfdi_comprobante"];
            $aport_infonavit_cfdi_emisor = $rAport["aport_infonavit_cfdi_emisor"];
            $aport_infonavit_cfdi_receptor = $rAport["aport_infonavit_cfdi_receptor"];
            $aport_infonavit_cfdi_conceptos = $rAport["aport_infonavit_cfdi_conceptos"];
            $aport_infonavit_cfdi_complemento = $rAport["aport_infonavit_cfdi_complemento"];
            
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
    
            $data_comprobante = json_decode($aport_infonavit_cfdi_comprobante, true);
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
              $data_emisor = json_decode($aport_infonavit_cfdi_emisor, true);
              foreach ($data_emisor as $CFDIe) {
                $cfdi_emisor_rfc = $CFDIe["EmisorRfc"];
                $cfdi_emisor_nombre = $CFDIe["EmisorNombre"];
                $cfdi_emisor_regimen_fiscal = $CFDIe["EmisorRegimenFiscal"];
              }
    
              $cfdi_receptor_rfc = '';
              $cfdi_receptor_domicilio_fiscal = '';
              $cfdi_receptor_regimen_fiscal = '';
              $cfdi_receptor_uso_del_cfdi = '';
              $data_receptor = json_decode($aport_infonavit_cfdi_receptor, true);
              foreach ($data_receptor as $CFDIReceptor) {
                $cfdi_receptor_rfc = $CFDIReceptor["ReceptorRfc"];
                $cfdi_receptor_domicilio_fiscal = $CFDIReceptor["ReceptorDomicilioFiscal"];
                $cfdi_receptor_regimen_fiscal = $CFDIReceptor["ReceptorRegimenFiscal"];
                $cfdi_receptor_uso_del_cfdi = $CFDIReceptor["ReceptorUsoCFDI"];
              }
    
              $data_complemento = json_decode($aport_infonavit_cfdi_complemento, true);
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
                $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$aporteMainID.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
                $insertCFDIAport = DB::table('cfdi_comprobantes_fiscales')
                ->insert(array(
                  "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
                  "origen_proceso" => "aportaciones",
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
                $insertCFDIVincNomina = DB::table('cfdi_vinculacion_aport_seg_social_imss')//cfdi__estructura
                ->insert(array(
                  "comprobante_fiscal" => $comprobante_fiscal_reg,
                  "comprobante_tipo" => "infonavit",	
                  "aport_seg_social_vinculado" => $aporteMainID,
                ));
    
                $data_conceptos = json_decode($aport_infonavit_cfdi_conceptos, true);
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
            $filepath = $vInfonavitAp->root_tkn."/0004-vhm/aportaciones_seguridad_social/$vInfonavitAp->aport_ssocial_fecha_registro-$folio_aport/anexos/";
            
            if ($archivo_xml) {
              $nombre_original = $archivo_xml->getClientOriginalName();
              $ext_doc = $archivo_xml->getClientOriginalExtension();
    
              $documento_crypt = $JwtAuth->encriptar($nombre_original);
              $token_documento = $JwtAuth->encriptarToken($aporteMainID, $ext_doc, $nombre_original);
    
              $insertDocSoli = DB::table("sos_documentos")->insert([
                "token_documento" => $token_documento,
                "fecha_carga" => time(),
                "modulo" => "reembolsos",
                "folio_modulo" => "APORT-INFONAVIT-CFDI-XML",
                "tipo_documento" => "xml",
                "nombre_documento" => $documento_crypt,
                "extension_documento" => $ext_doc,
                "aportacion_seguridad_social" => $aporteMainID,
                "status_documento" => true,
              ]);
    
              if ($insertDocSoli) {
                DB::table('vhum_aportaciones_seguridad_social_main')->where("aport_ssocial_token", $vInfonavitAp->aport_ssocial_token)
                ->limit(1)->update(array("aport_ssocial_infonavit_xml" => $documento_crypt));
                $archivo_xml->storeAs("public/root/$filepath", $nombre_original);
              }
            }
    
            if ($archivo_pdf) {
              $nombre_original = $archivo_pdf->getClientOriginalName();
              $ext_doc = $archivo_pdf->getClientOriginalExtension();
    
              $documento_crypt = $JwtAuth->encriptar($nombre_original);
              $token_documento = $JwtAuth->encriptarToken($aporteMainID, $ext_doc, $nombre_original);
    
              $insertDocSoli = DB::table("sos_documentos")->insert([
                "token_documento" => $token_documento,
                "fecha_carga" => time(),
                "modulo" => "reembolsos",
                "folio_modulo" => "APORT-INFONAVIT-CFDI-PDF",
                "tipo_documento" => "pdf",
                "nombre_documento" => $documento_crypt,
                "extension_documento" => $ext_doc,
                "aportacion_seguridad_social" => $aporteMainID,
                "status_documento" => true,
              ]);
    
              if ($insertDocSoli) {
                DB::table('vhum_aportaciones_seguridad_social_main')->where("aport_ssocial_token", $vInfonavitAp->aport_ssocial_token)
                ->limit(1)->update(array("aport_ssocial_infonavit_pdf" => $documento_crypt));
                $archivo_pdf->storeAs("public/root/$filepath", $nombre_original);
              }
            }
            ++$count_infonavit;
            //return response()->json(['status' => 'error','code' => 200,'message' => 'reem '.$token_nomina_recibo]);
          }
    
          if ($count_infonavit == count($infonavit)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "CFDI de la aportación de seguridad social con folio $folio_aport ha sido cargado correctamente"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Error al cargar CFDI de la aportación de seguridad social con el folio $folio_aport, intente nuevamente o comuniquese a soporte"
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaAportacionSeguridadSocial(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'aport_ssocial_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parametros de busqueda recibidos son incorrectos',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $aport_ssocial_token = $request->input('aport_ssocial_token');

      $OKAportSsocialTkn = isset($aport_ssocial_token) && !empty($aport_ssocial_token);
      if ($OKAportSsocialTkn) {
        $queryIMSSAportacion = AportacionesIMSSModelo::join("main_empresas AS emp", "vhum_aportaciones_seguridad_social_main.aport_ssocial_empresa", "emp.id")
        ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "vhum_aportaciones_seguridad_social_main.aport_ssocial_registro_patronal", "c_trab.id")
        ->where([
          'vhum_aportaciones_seguridad_social_main.aport_ssocial_token' => $aport_ssocial_token,
          'vhum_aportaciones_seguridad_social_main.aport_ssocial_status' => TRUE,
          'emp.empresa_token' => $empresa,
        ])
        ->get();
        
        foreach ($queryIMSSAportacion as $vIMSSAp) {
          $folio_interior = $vIMSSAp->aport_ssocial_folio_interior;
          $post_folio = $vIMSSAp->aport_ssocial_subfolio;
          $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');

          $queryUpdateAportMain = DB::table('vhum_aportaciones_seguridad_social_main')
          ->where('aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->limit(1)->update(array(
            "aport_ssocial_status" => FALSE,
            "aport_ssocial_fecha_delete" => time(),
          ));

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Reporte de aportación de seguridad social con el folio $folio_aport ha sido eliminado satisfactoriamente"
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error', 
          'code' => 200, 
          'message' => 'Error en fecha de contabilización, intentelo nuevamente o comuniquese a soporte'
        );
      }

    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  } 

  public function listaDeletedAportacionSeguridadSocial(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryIMSSAportacion = AportacionesIMSSModelo::join("main_empresas AS emp", "vhum_aportaciones_seguridad_social_main.aport_ssocial_empresa", "emp.id")
    ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "vhum_aportaciones_seguridad_social_main.aport_ssocial_registro_patronal", "c_trab.id")
    ->where([
      'vhum_aportaciones_seguridad_social_main.aport_ssocial_status' => FALSE,
      'emp.empresa_token' => $empresa,
    ])
    ->orderBy('vhum_aportaciones_seguridad_social_main.id', 'DESC')->get();

    if ($queryIMSSAportacion->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron aportaciones de seguridad social registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $lista_aports = array();
      
      foreach ($queryIMSSAportacion as $vIMSSAp) {
        $folio_interior = $vIMSSAp->aport_ssocial_folio_interior;
        $post_folio = $vIMSSAp->aport_ssocial_subfolio;
        $aport_ssocial_moneda = $vIMSSAp->aport_ssocial_moneda;
        $aport_ssocial_moneda_decimales = $JwtAuth->getMonedaAPI($vIMSSAp->aport_ssocial_moneda);

        $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');
        $periodo_pago_seguros_imss = !is_null($vIMSSAp->periodo_pago_seguros_imss_anio) && !is_null($vIMSSAp->periodo_pago_seguros_imss_mes) ? ucfirst(Carbon::create($vIMSSAp->periodo_pago_seguros_imss_anio, $vIMSSAp->periodo_pago_seguros_imss_mes, 1)->locale('es')->isoFormat('MMMM YYYY')) : '';
        $pago_rcv_infonavit_inicio = !is_null($vIMSSAp->pago_rcv_infonavit_inicio) ? ucfirst(Carbon::createFromTimestamp($vIMSSAp->pago_rcv_infonavit_inicio)->locale('es')->translatedFormat('F')) : '';
        $pago_rcv_infonavit_fin = !is_null($vIMSSAp->pago_rcv_infonavit_fin) ? ucfirst(Carbon::createFromTimestamp($vIMSSAp->pago_rcv_infonavit_fin)->locale('es')->translatedFormat('F')) : '';
        $pago_rcv_infonavit = $pago_rcv_infonavit_inicio != '' && $pago_rcv_infonavit_fin != '' ? "$pago_rcv_infonavit_inicio - $pago_rcv_infonavit_fin" : '';

        $totales_cuotas_patronales = DB::table("imss_cuotas_detalle AS imsDet")
        ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
        ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
        ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
        ->sum('imsDet.patronal');

        $totales_cuotas_obreras = DB::table("imss_cuotas_detalle AS imsDet")
        ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
        ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
        ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
        ->sum('imsDet.obrera');

        $totales_cuotas_totales = DB::table("imss_cuotas_detalle AS imsDet")
        ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
        ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
        ->whereNotIn('imsDet.label', ['SUBTOTAL', 'SUBTOTAL SEGUROS IMSS', 'SUBTOTAL RCV','SUBTOTAL VIVIENDA Y ACV'])
        ->sum('imsDet.total');

        $row = array(
          "aport_ssocial_token" => $vIMSSAp->aport_ssocial_token,
          "aport_ssocial_folio" => $folio_aport,
          "aport_ssocial_fecha_contabilizacion" => $JwtAuth->mostrarUnixAFechaMexico($vIMSSAp->aport_ssocial_fecha_contabilizacion),
          "aport_ssocial_registro_patronal_uuid" => $vIMSSAp->centrotrab_uuid,
          "aport_ssocial_registro_patronal_serie" => $vIMSSAp->centrotrab_clave_registro_patronal_imss,
          "periodo_pago_seguros_imss" => $periodo_pago_seguros_imss,
          "pago_rcv_infonavit" => $pago_rcv_infonavit,
          "folio_sua" => $vIMSSAp->folio_sua,
          "clave_recepcion_archivo_pago" => $vIMSSAp->clave_recepcion_archivo_pago,
          "propuesta_fecha_limite_pago" => $JwtAuth->mostrarUnixAFechaMexico($vIMSSAp->propuesta_fecha_limite_pago),
          "linea_captura_sipare" => $vIMSSAp->linea_captura_sipare,
          "propuesta_s_m_g_d_f" => "$ ".number_format($vIMSSAp->propuesta_s_m_g_d_f,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
          "propuesta_fecha_salario_minimo_pago" => $JwtAuth->mostrarUnixAFechaMexico($vIMSSAp->propuesta_fecha_salario_minimo_pago),
          "propuesta_valor_uma" => "$ ".number_format($vIMSSAp->propuesta_valor_uma,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
          "propuesta_num_de_cotizantes" => $vIMSSAp->propuesta_num_de_cotizantes,
          "propuesta_num_dias_a_cotizar" => $vIMSSAp->propuesta_num_dias_a_cotizar,
          "propuesta_num_de_acreditados" => $vIMSSAp->propuesta_num_de_acreditados,
          "cuotas_patronales" => "$ ".number_format($totales_cuotas_patronales,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
          "cuotas_obreras" => "$ ".number_format($totales_cuotas_obreras,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
          "cuotas_totales" => "$ ".number_format($totales_cuotas_totales,$aport_ssocial_moneda_decimales,'.',',')." $aport_ssocial_moneda",
          "observaciones" => $JwtAuth->desencriptar($vIMSSAp->observaciones),
        );
        $lista_aports[] = $row;
      }
      
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'aportaciones' => $lista_aports
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restauraAportacionSeguridadSocial(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'aport_ssocial_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parametros de busqueda recibidos son incorrectos',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $aport_ssocial_token = $request->input('aport_ssocial_token');

      $OKAportSsocialTkn = isset($aport_ssocial_token) && !empty($aport_ssocial_token);
      if ($OKAportSsocialTkn) {
        $queryIMSSAportacion = AportacionesIMSSModelo::join("main_empresas AS emp", "vhum_aportaciones_seguridad_social_main.aport_ssocial_empresa", "emp.id")
        ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "vhum_aportaciones_seguridad_social_main.aport_ssocial_registro_patronal", "c_trab.id")
        ->where([
          'vhum_aportaciones_seguridad_social_main.aport_ssocial_token' => $aport_ssocial_token,
          'vhum_aportaciones_seguridad_social_main.aport_ssocial_status' => FALSE,
          'emp.empresa_token' => $empresa,
        ])
        ->get();
        
        foreach ($queryIMSSAportacion as $vIMSSAp) {
          $folio_interior = $vIMSSAp->aport_ssocial_folio_interior;
          $post_folio = $vIMSSAp->aport_ssocial_subfolio;
          $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');

          $queryUpdateAportMain = DB::table('vhum_aportaciones_seguridad_social_main')
          ->where('aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->limit(1)->update(array(
            "aport_ssocial_status" => TRUE,
            "aport_ssocial_fecha_delete" => NULL,
          ));

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Reporte de aportación de seguridad social con el folio $folio_aport ha sido eliminado satisfactoriamente"
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error', 
          'code' => 200, 
          'message' => 'Error en fecha de contabilización, intentelo nuevamente o comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  } 

  public function eliminaPermAportacionSeguridadSocial(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'aport_ssocial_token' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los parametros de busqueda recibidos son incorrectos',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $aport_ssocial_token = $request->input('aport_ssocial_token');

      $OKAportSsocialTkn = isset($aport_ssocial_token) && !empty($aport_ssocial_token);        
      if ($OKAportSsocialTkn) {
        $queryIMSSAportacion = AportacionesIMSSModelo::join("main_empresas AS emp", "vhum_aportaciones_seguridad_social_main.aport_ssocial_empresa", "emp.id")
        ->join("vhum_centros_de_trabajo_catalogo AS c_trab", "vhum_aportaciones_seguridad_social_main.aport_ssocial_registro_patronal", "c_trab.id")
        ->where([
          'vhum_aportaciones_seguridad_social_main.aport_ssocial_token' => $aport_ssocial_token,
          'vhum_aportaciones_seguridad_social_main.aport_ssocial_status' => FALSE,
          'emp.empresa_token' => $empresa,
        ])
        ->get();
        
        foreach ($queryIMSSAportacion as $vIMSSAp) {
          $folio_interior = $vIMSSAp->aport_ssocial_folio_interior;
          $post_folio = $vIMSSAp->aport_ssocial_subfolio;
          $folio_aport = 'APORT-IMSS-'.$JwtAuth->generarFolio($folio_interior).(!is_null($post_folio) ? '-'.$post_folio : '');

          $queryNominaPago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "order.aportacion_seguridad_social", "=", "nomImp.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->get();
          
          if (count($queryNominaPago) == 0) {
            $queryNominaPago = DB::table("fnzs_pagos_orden AS order")
            ->join("vhum_aportaciones_seguridad_social_main AS social_main", "order.aportacion_seguridad_social", "=", "nomImp.id")
            ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
            ->limit(1)->delete();
          }

          $queryDeleteImmsDet = DB::table("imss_cuotas_detalle AS imsDet")
          ->join("vhum_aportaciones_seguridad_social_main AS social_main", "imsDet.aportaciones_main", "social_main.id")
          ->where('social_main.aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->delete();

          $queryDeleteSOCIAL = DB::table("vhum_aportaciones_seguridad_social_main")
          ->where('aport_ssocial_token',$vIMSSAp->aport_ssocial_token)
          ->limit(1)->delete();

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Reporte de aportación de seguridad social con el folio $folio_aport ha sido eliminado satisfactoriamente"
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error', 
          'code' => 200, 
          'message' => 'Error en fecha de contabilización, intentelo nuevamente o comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  } 
}