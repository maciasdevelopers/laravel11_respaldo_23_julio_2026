<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\AsimiladosModelo;
use App\Models\OrdenPagoModelo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VHUM_AsimiladosController extends Controller{
  public function registraAsimiladoReporte(Request $request){
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
      'asimilado' => 'required|string',
      'periodo_inicio' => 'required|string',
      'periodo_fin' => 'required|string',
      'fecha_pago' => 'required|string',
      'moneda_code' => 'required|string',
      'dias_pagados' => 'required|string',
      'total_percepciones' => 'required|string',
      'percepciones_servicio' => 'required|string',
      'total_deducciones' => 'required|string',
      'deducciones_impuesto' => 'required|string',
      'observaciones' => 'required|string',

      'dataCFDI_comprobante_obj' => 'required|json',
      'dataCFDIRelacionados_obj' => 'required|json',
      'dataCFDIEmisor_obj' => 'required|json',
      'dataCFDIReceptor_obj' => 'required|json',
      'dataCFDI_conceptos' => 'required|json',
      'dataCFDIComplemento_obj' => 'required|json',
      'dataCFDIComplementoNomina_obj' => 'required|json',
      'dataCFDIComplementoNominaReceptor_obj' => 'required|json',
      'dataCFDIComplementoNominaPercepciones_obj' => 'required|json',
      'dataCFDIComplementoNominaPercepcion_obj' => 'required|json',
      'dataCFDIComplementoNominaDeducciones_obj' => 'required|json',
      'dataCFDIComplementoNominaDeduccion_obj' => 'required|json',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido'.$validate->errors(),
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $asimilado = $request->input('asimilado');
      $periodo_inicio = $request->input('periodo_inicio');
      $periodo_fin = $request->input('periodo_fin');
      $fecha_pago = $request->input('fecha_pago');
      $moneda_code = $request->input('moneda_code');
      $dias_pagados = $request->input('dias_pagados');
      $total_percepciones = $request->input('total_percepciones');
      $percepciones_servicio = $request->input('percepciones_servicio');
      $total_deducciones = (float)$request->input('total_deducciones');
      $deducciones_impuesto = $request->input('deducciones_impuesto');
      $observaciones = $request->input('observaciones');

      $dataCFDI_comprobante_obj = json_decode($request->input('dataCFDI_comprobante_obj'), true);
      $dataCFDIRelacionados_obj = json_decode($request->input('dataCFDIRelacionados_obj'), true);
      $dataCFDIEmisor_obj = json_decode($request->input('dataCFDIEmisor_obj'), true);
      $dataCFDIReceptor_obj = json_decode($request->input('dataCFDIReceptor_obj'), true);
      $dataCFDI_conceptos = json_decode($request->input('dataCFDI_conceptos'), true);
      $dataCFDIComplemento_obj = json_decode($request->input('dataCFDIComplemento_obj'), true);
      $dataCFDIComplementoNomina_obj = json_decode($request->input('dataCFDIComplementoNomina_obj'), true);
      $dataCFDIComplementoNominaReceptor_obj = json_decode($request->input('dataCFDIComplementoNominaReceptor_obj'), true);
      $dataCFDIComplementoNominaPercepciones_obj = json_decode($request->input('dataCFDIComplementoNominaPercepciones_obj'), true);
      $dataCFDIComplementoNominaPercepcion_obj = json_decode($request->input('dataCFDIComplementoNominaPercepcion_obj'), true);
      $dataCFDIComplementoNominaDeducciones_obj = json_decode($request->input('dataCFDIComplementoNominaDeducciones_obj'), true);
      $dataCFDIComplementoNominaDeduccion_obj = json_decode($request->input('dataCFDIComplementoNominaDeduccion_obj'), true);

      $OKAsimFCont = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $OKAsimFToken = isset($asimilado) && !empty($asimilado);
      $OKAsimPeriodoInicio = isset($periodo_inicio) && !empty($periodo_inicio) && preg_match($JwtAuth->filtroFecha(),$periodo_inicio);
      $OKAsimPeriodoFin = isset($periodo_fin) && !empty($periodo_fin) && preg_match($JwtAuth->filtroFecha(),$periodo_fin);
      $OKAsimFPago = isset($fecha_pago) && !empty($fecha_pago) && preg_match($JwtAuth->filtroFecha(),$fecha_pago);
      $OKAsimMoneda = isset($moneda_code) && !empty($moneda_code) && preg_match($JwtAuth->filtroAlfaNumerico(),$moneda_code);
      $OKAsimDiasPagados = isset($dias_pagados) && !empty($dias_pagados) && preg_match($JwtAuth->filtroCostoPrecio(),$dias_pagados);
      $OKAsimTotalPercepciones = isset($total_percepciones) && !empty($total_percepciones) && preg_match($JwtAuth->filtroCostoPrecio(),$total_percepciones);
      $OKAsimPercepcionesServicio = isset($percepciones_servicio) && !empty($percepciones_servicio);
      $OKAsimTotalDeducciones = isset($total_deducciones) && !empty($total_deducciones) && preg_match($JwtAuth->filtroCostoPrecio(),$total_deducciones);
      $OKAsimDeduccionesImpuesto = isset($deducciones_impuesto) && !empty($deducciones_impuesto);
      $OKAsimObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

      if ($OKAsimFCont && $OKAsimFToken && $OKAsimPeriodoInicio && $OKAsimPeriodoFin && $OKAsimFPago && $OKAsimMoneda && $OKAsimTotalPercepciones && $OKAsimTotalDeducciones && $OKAsimObservacion) {
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

        foreach ($queryEmp as $vEmp) {
          $folioSistema = DB::select("SELECT r_asim.asim_folio_interior+1 AS folio,asim_subfolio FROM vhum_reporte_asimilados_main AS r_asim JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
            JOIN teci_usuarios_catalogo AS users WHERE r_asim.asim_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? 
            ORDER BY r_asim.asim_folio_interior DESC LIMIT 1",[$empresa,$usuario]);
          //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
          if (count($folioSistema) == 1) {
            if ($folioSistema[0]->folio == 1000000000) {
                $post_folio_db = DB::select("SELECT asim_subfolio FROM vhum_reporte_asimilados_main WHERE id = (SELECT Max(nomina.id) FROM vhum_reporte_asimilados_main AS r_asim JOIN main_empresas AS emp 
                  JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE r_asim.asim_empresa = emp.id AND emp.empresa_token = ?
                  AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$empresa,$usuario]);
                
                $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->asim_subfolio);
                $folio_nuevo = 1;
            } else {
                $post_folio = NULL;
                $folio_nuevo = $folioSistema[0]->folio;
            }
          } else {
            $post_folio = NULL;
            $folio_nuevo = 1;
          }

          $asimilado_id = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$asimilado)->value("id");
          $cfdi_comprobante_moneda = $dataCFDI_comprobante_obj['moneda'] ?? 'MXN';

          $folio_reporte = 'ASIM-'.$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');
          $tokenMainNomina = Str::uuid()->toString();
          $asim_fecha_registro = time();
          $newAsimReport = new AsimiladosModelo();
          $newAsimReport->token_reporte_asim = $tokenMainNomina;
          $newAsimReport->asim_fecha_registro = $asim_fecha_registro;
          $newAsimReport->asim_folio_interior = $folio_nuevo;
          $newAsimReport->asim_subfolio = $post_folio;
          $newAsimReport->asim_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
          $newAsimReport->asim_main_moneda = $cfdi_comprobante_moneda;
          $newAsimReport->asim_observaciones = $JwtAuth->encriptar($observaciones);

          if ($request->hasFile('imagenEvidenciaXMl') && $request->file('imagenEvidenciaXMl')->isValid()) {
            $xmlFile = $request->file('imagenEvidenciaXMl');
            $nombreFisicoXML = $asim_fecha_registro . "-" . $folio_reporte . "_" . str_replace([' ', '#'], '_', $xmlFile->getClientOriginalName());
            $newAsimReport->asim_factura_xml = $JwtAuth->encriptar($nombreFisicoXML);
          }

          //Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaPdf'),$request->file('imagenEvidenciaPdf')->getClientOriginalName()); 
          if ($request->hasFile('imagenEvidenciaPdf') && $request->file('imagenEvidenciaPdf')->isValid()) {
            $pdfFile = $request->file('imagenEvidenciaPdf');
            $nombreFisicoPDF = $asim_fecha_registro . "-" . $folio_reporte . "_" . str_replace([' ', '#'], '_', $pdfFile->getClientOriginalName());
            $newAsimReport->asim_factura_pdf = $JwtAuth->encriptar($nombreFisicoPDF);
          }

          $newAsimReport->asim_empresa = $vEmp->id;
          $savedNomnina = $newAsimReport->save();
          $reporte_asim_id = $newAsimReport->id;
          
          $tokenDesgloseAsim = Str::uuid()->toString();
          DB::table("vhum_reporte_asimilados_desglose")
          ->insert(array(
            "asim_reporte" => $reporte_asim_id,
            "token_desglose_asim" => $tokenDesgloseAsim,
            "desglose_asim_receptor" => $asimilado_id,
            "desglose_asim_folio" => 1,
            "desglose_asim_periodo_inicio" => $JwtAuth->convierteFechaEpoc($periodo_inicio),
            "desglose_asim_periodo_fin" => $JwtAuth->convierteFechaEpoc($periodo_fin),
            "desglose_asim_fecha_pago" => $JwtAuth->convierteFechaEpoc($fecha_pago),
            "desglose_asim_moneda" => $moneda_code,
            "desglose_asim_dias_pagados" => $OKAsimDiasPagados ? $dias_pagados : NULL,
            "total_deducciones" => $total_deducciones,
            "deducciones_impuesto_asociado" => $OKAsimDeduccionesImpuesto ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$deducciones_impuesto)->value("id") : NULL,
            "total_percepciones" => $total_percepciones,
            "percepciones_servicio_asociado" => $OKAsimPercepcionesServicio ? DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios",$percepciones_servicio)->value("id") : NULL,
          ));

          //ALTER TABLE `fnzs_pagos_orden` ADD `nomina_main` INT(10) NULL AFTER `reembolso_solicitud`;
          $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

          $tknOrder = $JwtAuth->encriptarToken(time(),$folioOrden[0]->folio,$reporte_asim_id);
          $orderpay = new OrdenPagoModelo();
          $orderpay->token_ordenPago = $tknOrder;
          $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
          $orderpay->fecha_sistema_ordenp = $asim_fecha_registro;
          $orderpay->asimilados_reporte = $reporte_asim_id;
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
          $orderpay->save();
          
          $filepath = $vEmp->root_tkn . "/0004-vhm/asimilados/$asim_fecha_registro-$folio_reporte/anexos/";
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
                $token_documento = $JwtAuth->encriptarToken($reporte_asim_id,$empresa,$usuario,$doc_name,$select_folio_doc[0]->folio);
                $insertDocSoli = DB::table("sos_documentos")->insert(
                  array(
                    "token_documento" => $token_documento,
                    "fecha_carga" => time(),
                    "modulo" => "pagos",
                    "folio_modulo" => "IMP-NOMI-EVID".$select_folio_doc[0]->folio,
                    "tipo_documento" => "an",
                    "nombre_documento" => $JwtAuth->encriptar($doc_name),
                    "asimilados_reporte" => $reporte_asim_id,
                    "status_documento" => TRUE,
                  )
                );
              }
            }
          }

          if (isset($xmlFile)) Storage::putFileAs("/public/root/" . $filepath,$xmlFile,$nombreFisicoXML);
          if (isset($pdfFile)) Storage::putFileAs("/public/root/" . $filepath, $pdfFile, $nombreFisicoPDF);

          $cfdi_comprobante_version = $dataCFDI_comprobante_obj['version'] ?? '---';
          $cfdi_comprobante_serie = $dataCFDI_comprobante_obj['serie'] ?? '---';
          $cfdi_comprobante_folio = $dataCFDI_comprobante_obj['folio'] ?? '---';
          $cfdi_comprobante_fecha = $dataCFDI_comprobante_obj['fecha'] ?? '---';
          $cfdi_comprobante_forma_de_pago = $dataCFDI_comprobante_obj['forma_de_pago'] ?? '---';
          $cfdi_comprobante_subtotal = $dataCFDI_comprobante_obj['subtotal'] ?? '---';
          $cfdi_comprobante_descuento = $dataCFDI_comprobante_obj['Descuento'] ?? '0.00';
          $cfdi_comprobante_tipo_de_cambio = $dataCFDI_comprobante_obj['tipo_de_cambio'] ?? '1.00';
          $cfdi_comprobante_total = $dataCFDI_comprobante_obj['total'] ?? '---';
          $cfdi_comprobante_confirmacion = $dataCFDI_comprobante_obj['confirmacion'] ?? '---';
          $cfdi_comprobante_tipo_de_comprobante = $dataCFDI_comprobante_obj['tipo_de_comprobante'] ?? '---';
          $cfdi_comprobante_metodo_de_pago = $dataCFDI_comprobante_obj['metodo_de_pago'] ?? '---';
          $cfdi_comprobante_lugar_de_expedicion = $dataCFDI_comprobante_obj['lugar_de_expedicion'] ?? '---';
          $cfdi_comprobante_no_de_certificado = $dataCFDI_comprobante_obj['no_de_certificado'] ?? '---';
          $cfdi_comprobante_sello = $dataCFDI_comprobante_obj['sello'] ?? '---';
          $cfdi_comprobante_certificado = $dataCFDI_comprobante_obj['certificado'] ?? '---';

          $cfdi_relacionados_tipo_de_relacion = $dataCFDIRelacionados_obj['tipo_de_relacion'] ?? '---';
          $cfdi_relacionados_uuid = $dataCFDIRelacionados_obj['UUID'] ?? '---';

          $cfdi_emisor_rfc = $dataCFDIEmisor_obj['rfc_del_emisor'] ?? '---';
          $cfdi_emisor_nombre = $dataCFDIEmisor_obj['nombre_del_emisor'] ?? '---';
          $cfdi_emisor_regimen_fiscal = $dataCFDIEmisor_obj['regimen_fiscal_del_emisor'] ?? '---';
          
          $cfdi_receptor_Rfc = $dataCFDIReceptor_obj['Rfc'] ?? '---'; 
          $cfdi_receptor_Nombre = $dataCFDIReceptor_obj['Nombre'] ?? '---';
          $cfdi_receptor_UsoCFDI = $dataCFDIReceptor_obj['UsoCFDI'] ?? '---'; 
          $cfdi_receptor_RegimenFiscalReceptor = $dataCFDIReceptor_obj['RegimenFiscalReceptor'] ?? '---'; 
          $cfdi_receptor_DomicilioFiscalReceptor = $dataCFDIReceptor_obj['DomicilioFiscalReceptor'] ?? '---';

          $cfdi_complementoVersion = $dataCFDIComplemento_obj['Version'] ?? '---';
          $cfdi_complementoUUID = $dataCFDIComplemento_obj['UUID'] ?? '---';
          $cfdi_complementoFechaTimbrado = $dataCFDIComplemento_obj['FechaTimbrado'] ?? '---';
          $cfdi_complementoRfcProvCertif = $dataCFDIComplemento_obj['RfcProvCertif'] ?? '---';
          $cfdi_complementoNoCertificadoSAT = $dataCFDIComplemento_obj['NoCertificadoSAT'] ?? '---';
          $cfdi_complementoSelloCFD = $dataCFDIComplemento_obj['SelloCFD'] ?? '---';
          $cfdi_complementoSelloSAT = $dataCFDIComplemento_obj['SelloSAT'] ?? '---';

          $cfdi_complem_nomina_fechafinalpago = $dataCFDIComplementoNomina_obj['FechaFinalPago'] ?? '---';
          $cfdi_complem_nomina_fechainicialpago = $dataCFDIComplementoNomina_obj['FechaInicialPago'] ?? '---'; 
          $cfdi_complem_nomina_fechapago = $dataCFDIComplementoNomina_obj['FechaPago'] ?? '---';
          $cfdi_complem_nomina_numdiaspagados = $dataCFDIComplementoNomina_obj['NumDiasPagados'] ?? '---'; 
          $cfdi_complem_nomina_tiponomina = $dataCFDIComplementoNomina_obj['TipoNomina'] ?? '---';
          $cfdi_complem_nomina_totaldeducciones = $dataCFDIComplementoNomina_obj['TotalDeducciones'] ?? '0.00';
          $cfdi_complem_nomina_totalotrospagos = $dataCFDIComplementoNomina_obj['TotalOtrosPagos'] ?? '0.00';
          $cfdi_complem_nomina_totalpercepciones = $dataCFDIComplementoNomina_obj['TotalPercepciones'] ?? '0.00';
          $cfdi_complem_nomina_version = $dataCFDIComplementoNomina_obj['Version'] ?? '---';

          $cfdi_complem_nomirecept_claveentfed = $dataCFDIComplementoNominaReceptor_obj['ClaveEntFed'] ?? '---'; 
          $cfdi_complem_nomirecept_curp = $dataCFDIComplementoNominaReceptor_obj['Curp'] ?? '---'; 
          $cfdi_complem_nomirecept_numempleado = $dataCFDIComplementoNominaReceptor_obj['NumEmpleado'] ?? '---'; 
          $cfdi_complem_nomirecept_tipocontrato = $dataCFDIComplementoNominaReceptor_obj['TipoContrato'] ?? '---'; 
          $cfdi_complem_nomirecept_periodicidadpago = $dataCFDIComplementoNominaReceptor_obj['PeriodicidadPago'] ?? '---'; 
          $cfdi_complem_nomirecept_tiporegimen = $dataCFDIComplementoNominaReceptor_obj['TipoRegimen'] ?? '---';

          $dataCFDIComplementoNominaPercepciones_obj = json_decode($request->input('dataCFDIComplementoNominaPercepciones_obj'), true);
          $cfdi_complem_nomipercepciones_totalexento = $dataCFDIComplementoNominaPercepciones_obj['TotalExento'] ?? '---';
          $cfdi_complem_nomipercepciones_totalgravado = $dataCFDIComplementoNominaPercepciones_obj['TotalGravado'] ?? '---';
          $cfdi_complem_nomipercepciones_totalsueldos = $dataCFDIComplementoNominaPercepciones_obj['TotalSueldos'] ?? '---';

          $dataCFDIComplementoNominaPercepcion_obj = json_decode($request->input('dataCFDIComplementoNominaPercepcion_obj'), true);
          //<nomina12:Percepcion Clave="P018" Concepto="INGRESOS ASIMILADOS A SALARIOS" ImporteExento="0.00" ImporteGravado="8620.62" TipoPercepcion="046"></nomina12:Percepcion>

          $dataCFDIComplementoNominaDeducciones_obj = json_decode($request->input('dataCFDIComplementoNominaDeducciones_obj'), true);
          $cfdi_complem_nomideducciones_totalimpuestosretenidos = $dataCFDIComplementoNominaDeducciones_obj['TotalImpuestosRetenidos'] ?? '0.00';
          $cfdi_complem_nomideducciones_totalotrasdeducciones = $dataCFDIComplementoNominaDeducciones_obj['TotalOtrasDeducciones'] ?? '0.00';

          $dataCFDIComplementoNominaDeduccion_obj = json_decode($request->input('dataCFDIComplementoNominaDeduccion_obj'), true);
          //<nomina12:Deduccion Clave="D001" Concepto="ISR" Importe="620.62" TipoDeduccion="002"></nomina12:Deduccion>

          if ($cfdi_comprobante_version != '') {
            $cfdi_comprobantes_token = $JwtAuth->encriptarToken(time(),$reporte_asim_id.$cfdi_complementoUUID.$cfdi_comprobante_folio.time() - 500);
            
            $insertCFDIAport = DB::table('cfdi_comprobantes_fiscales')
            ->insert(array(
              "cfdi_comprobantes_token" => $cfdi_comprobantes_token,
              "origen_proceso" => "aportaciones",
              "cfdi_comprobante_fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
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
  
              "cfdi_receptor_rfc" => $cfdi_receptor_Rfc,
              "cfdi_receptor_domicilio_fiscal" => $cfdi_receptor_DomicilioFiscalReceptor,
              "cfdi_receptor_regimen_fiscal" => $cfdi_receptor_RegimenFiscalReceptor,
              "cfdi_receptor_uso_del_cfdi" => $cfdi_receptor_UsoCFDI,
              
              "cfdi_complementoVersion" => $cfdi_complementoVersion,	
              "cfdi_complementoUUID" => $cfdi_complementoUUID,	
              "cfdi_complementoFechaTimbrado" => $cfdi_complementoFechaTimbrado,	
              "cfdi_complementoRfcProvCertif" => $cfdi_complementoRfcProvCertif,
              "cfdi_complementoNoCertificadoSAT" => $cfdi_complementoNoCertificadoSAT,	
              "cfdi_complementoSelloCFD" => $cfdi_complementoSelloCFD,	
              "cfdi_complementoSelloSAT" => $cfdi_complementoSelloSAT,	
            ));

            $comprobante_fiscal_reg = DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id");
            $insertCFDIVincNomina = DB::table('cfdi_vinculacion_asimilados_reporte')//cfdi__estructura
            ->insert(array(
              "comprobante_fiscal" => $comprobante_fiscal_reg,
              "comprobante_tipo" => "asimrepor",	
              "asimilados_reporte_vinculado" => $reporte_asim_id,
            ));
            
            foreach ($dataCFDI_conceptos as $cfdi_concept) {
              $uuid_cfdi_detalle = Str::uuid()->toString();
              $insertConceptCFDINominas = DB::table('cfdi_comprobantes_conceptos')
              ->insert(array(
                "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                "comprobante_fiscal" => $comprobante_fiscal_reg,
                "ClaveProdServ" => $cfdi_concept['ClaveProdServ'],
                "Cantidad" => $cfdi_concept['Cantidad'],
                "ClaveUnidad" => $cfdi_concept['ClaveUnidad'],
                "Descripcion" => $cfdi_concept['Descripcion'],
                "ValorUnitario" => $cfdi_concept['ValorUnitario'],
                "Importe" => $cfdi_concept['Importe'],
                "Descuento" => $cfdi_concept['Descuento'],
                "ObjetoImp" => $cfdi_concept['ObjetoImp']
              ));
            }

            $asimilados_nomina_uuid = Str::uuid()->toString();
            $insertNominaCFDINominas = DB::table('cfdi_asimilados_nomina')
            ->insert(array(
              "uuid_asimilados_nomina" => $asimilados_nomina_uuid,
              "asimilados_reporte" => $reporte_asim_id,
              "empleado_referenciado" => $asimilado_id,
              "comprobante_fiscal" => $comprobante_fiscal_reg,

              "FechaFinalPago" => $cfdi_complem_nomina_fechafinalpago,
              "FechaInicialPago" => $cfdi_complem_nomina_fechainicialpago,
              "FechaPago" => $cfdi_complem_nomina_fechapago,
              "NumDiasPagados" => $cfdi_complem_nomina_numdiaspagados,
              "TipoNomina" => $cfdi_complem_nomina_tiponomina,
              "TotalDeducciones" => $cfdi_complem_nomina_totaldeducciones,
              "TotalOtrosPagos" => $cfdi_complem_nomina_totalotrospagos,
              "TotalPercepciones" => $cfdi_complem_nomina_totalpercepciones,
              "Version" => $cfdi_complem_nomina_version
            ));

            $uuid_nomina_receptor = Str::uuid()->toString();
            DB::table('cfdi_asimilados_nomina_receptor')
            ->insert(array(
              "uuid_nomina_receptor" => $uuid_nomina_receptor,
              "asimilados_reporte" => $reporte_asim_id,
              "empleado_referenciado" => $asimilado_id,
              "comprobante_fiscal" => $comprobante_fiscal_reg,
              "asimilados_nomina" => $asimilados_nomina_uuid,

              "ClaveEntFed" => $cfdi_complem_nomirecept_claveentfed,
              "Curp" => $cfdi_complem_nomirecept_curp,
              "NumEmpleado" => $cfdi_complem_nomirecept_numempleado,
              "TipoContrato" => $cfdi_complem_nomirecept_tipocontrato,
              "PeriodicidadPago" => $cfdi_complem_nomirecept_periodicidadpago,
              "TipoRegimen" => $cfdi_complem_nomirecept_tiporegimen
            ));

            $uuid_nomina_percepciones = Str::uuid()->toString();
            DB::table('cfdi_asimilados_nomina_percepciones')
            ->insert(array(
              "uuid_nomina_percepciones" => $uuid_nomina_percepciones,
              "asimilados_reporte" => $reporte_asim_id,
              "empleado_referenciado" => $asimilado_id,
              "comprobante_fiscal" => $comprobante_fiscal_reg,
              "asimilados_nomina" => $asimilados_nomina_uuid,

              "TotalExento" => $cfdi_complem_nomipercepciones_totalexento,
              "TotalGravado" => $cfdi_complem_nomipercepciones_totalgravado,
              "TotalSueldos" => $cfdi_complem_nomipercepciones_totalsueldos,
            ));
            
            foreach ($dataCFDIComplementoNominaPercepcion_obj as $PercepcionNomi) {
              $uuid_nomina_percepcion = Str::uuid()->toString();
              DB::table('cfdi_asimilados_nomina_percepciones_percepcion')
              ->insert(array(
                "uuid_nomina_percepcion" => $uuid_nomina_percepcion,
                "asimilados_reporte" => $reporte_asim_id,
                "empleado_referenciado" => $asimilado_id,
                "comprobante_fiscal" => $comprobante_fiscal_reg,
                "asimilados_nomina" => $asimilados_nomina_uuid,
                "nomina_percepciones" => $uuid_nomina_percepciones,

                "Clave" => $PercepcionNomi["Clave"],
                "Concepto" => $PercepcionNomi["Concepto"],
                "ImporteExento" => $PercepcionNomi["ImporteExento"],
                "ImporteGravado" => $PercepcionNomi["ImporteGravado"],
                "TipoPercepcion" => $PercepcionNomi["TipoPercepcion"],
              ));
            }

            $uuid_nomina_deducciones = Str::uuid()->toString();
            DB::table('cfdi_asimilados_nomina_deducciones')
            ->insert(array(
              "uuid_nomina_deducciones" => $uuid_nomina_deducciones,
              "asimilados_reporte" => $reporte_asim_id,
              "empleado_referenciado" => $asimilado_id,
              "comprobante_fiscal" => $comprobante_fiscal_reg,
              "asimilados_nomina" => $asimilados_nomina_uuid,

              "TotalImpuestosRetenidos" => $cfdi_complem_nomideducciones_totalimpuestosretenidos,
              "TotalOtrasDeducciones" => $cfdi_complem_nomideducciones_totalotrasdeducciones,
            ));
            
            foreach ($dataCFDIComplementoNominaDeduccion_obj as $DeduccionNomi) {
              $uuid_nomina_deduccion = Str::uuid()->toString();
              DB::table('cfdi_asimilados_nomina_deducciones_deduccion')
              ->insert(array(
                "uuid_nomina_deduccion" => $uuid_nomina_deduccion,
                "asimilados_reporte" => $reporte_asim_id,
                "empleado_referenciado" => $asimilado_id,
                "comprobante_fiscal" => $comprobante_fiscal_reg,
                "asimilados_nomina" => $asimilados_nomina_uuid,
                "nomina_deducciones" => $uuid_nomina_deducciones,

                "Clave" => $DeduccionNomi["Clave"],
                "Concepto" => $DeduccionNomi["Concepto"],
                "Importe" => $DeduccionNomi["Importe"],
                "TipoDeduccion" => $DeduccionNomi["TipoDeduccion"],
              ));
            }
          }
  
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Reporte registrado satisfactoriamente con el folio $folio_reporte"
          );
        }
      } else {
        $mensaje_error = "";
        if (!$OKAsimFCont) $mensaje_error = "Error en fecha de contabilización de reporte registrado, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAsimFToken) $mensaje_error = "Error en selección de asimilado, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAsimPeriodoInicio && !$OKAsimPeriodoFin) $mensaje_error = "Error en el periodo seleccionado, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAsimFPago) $mensaje_error = "Error en la fecha de pago seleccionada, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAsimMoneda) $mensaje_error = "Error en la moneda seleccionada, intentelo nuevamente o comuniquese a soporte";
        //if (!$OKAsimDiasPagados) $mensaje_error = "Error en fecha de contabilización de nómina, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAsimTotalPercepciones) $mensaje_error = "Error en el total de percepciones registrado, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAsimTotalDeducciones) $mensaje_error = "Error en el total de deducciones registrado, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAsimObservacion) $mensaje_error = "Error en las observaciones registradas, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function reportesAsimilados(Request $request){
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
      
      $queryRepAsim = AsimiladosModelo::join("main_empresas AS emp", "vhum_reporte_asimilados_main.asim_empresa", "emp.id")
      ->whereIn('vhum_reporte_asimilados_main.id', function ($query) {
        $query->select('asim_reporte')->from('vhum_reporte_asimilados_desglose');
      })
      ->where([
        'vhum_reporte_asimilados_main.asim_status' => TRUE,
        'emp.empresa_token' => $empresa,
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("vhum_reporte_asimilados_main.asim_fecha_registro", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('vhum_reporte_asimilados_main.id', 'DESC')
      ->select(
        'vhum_reporte_asimilados_main.id AS id_asim',
        'vhum_reporte_asimilados_main.*'
      )
      ->get();

      if ($queryRepAsim->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron reportes de asimilados registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $listRepAsim = [];

        $asimIDToken = $queryRepAsim->pluck('id_asim')->filter()->unique()->toArray();

        $asimOrdPagoMap = DB::table("fnzs_pagos_orden")
        ->whereIn('asimilados_reporte',$asimIDToken)
        //->select('token_ordenPago', 'folio_ordenPago')
        ->get()->keyBy('asimilados_reporte');

        foreach ($queryRepAsim as $vRepAsim) {
          $asim_moneda = "MXN";
          $asim_moneda_decimales = $JwtAuth->getMonedaAPI($asim_moneda);
          $folio_reporte = 'ASIM-'.$JwtAuth->generarFolio($vRepAsim->asim_folio_interior).(!is_null($vRepAsim->asim_subfolio) ? '-'.$vRepAsim->asim_subfolio : '');

          $total_percepciones = DB::table("vhum_reporte_asimilados_desglose")
          ->where('asim_reporte',$vRepAsim->id_asim)
          ->sum('total_percepciones');

          $total_deducciones = DB::table("vhum_reporte_asimilados_desglose")
          ->where('asim_reporte',$vRepAsim->id_asim)
          ->sum('total_deducciones');

					$asim_total_a_pagar = $total_percepciones - $total_deducciones;

          $totales_asim_pago = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->where('order.asimilados_reporte',$vRepAsim->id_asim)
          ->sum('pay.monto_pago');

					$totales_asim_saldo = $asim_total_a_pagar - $totales_asim_pago;

          $queryAsimOrdPago = $asimOrdPagoMap->get($vRepAsim->id_asim);
          $asim_ord_pago_token = $queryAsimOrdPago ? $queryAsimOrdPago->token_ordenPago :'';
					$asim_ord_pago_folio = $queryAsimOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryAsimOrdPago->folio_ordenPago) :'';

          $queryAsimPagoDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->where('order.asimilados_reporte',$vRepAsim->id_asim)
          ->exists();

          $listRepAsim[] = [
            "token_reporte_asim" => $vRepAsim->token_reporte_asim,
            "asim_fecha_registro" => date('Y-m-d',$vRepAsim->asim_fecha_registro),
            "asim_folio" => $folio_reporte,
            "asim_fecha_contabilizacion" => date('Y-m-d',$vRepAsim->asim_fecha_contabilizacion),
            "asim_observaciones" => $JwtAuth->desencriptar($vRepAsim->asim_observaciones),
            "asim_total_a_pagar" => "$".number_format($asim_total_a_pagar,$asim_moneda_decimales,'.',',')." $asim_moneda",
            'asim_pago' => "$".number_format($totales_asim_pago,$asim_moneda_decimales,'.', ',')." $asim_moneda",
            'asim_saldo' => "$".number_format($totales_asim_saldo,$asim_moneda_decimales,'.', ',')." $asim_moneda",
            "asim_ord_pago_token" => $asim_ord_pago_token,
            "asim_ord_pago_folio" => $asim_ord_pago_folio,
            "asim_habilita_carga_docs" => $queryAsimPagoDone ? true : false,
            "asim_factura_doc_xml" => !is_null($vRepAsim->asim_factura_xml) ? $JwtAuth->desencriptar($vRepAsim->asim_factura_xml) : null,
            "asim_url_doc_xml" => !is_null($vRepAsim->asim_factura_xml) ? "https://downloads.sos-mexico.com.mx/asimilados_fact_xml/$vRepAsim->token_reporte_asim" : null,
            "asim_factura_doc_pdf" => !is_null($vRepAsim->asim_factura_pdf) ? $JwtAuth->desencriptar($vRepAsim->asim_factura_pdf) : null,
            "asim_url_doc_pdf" => !is_null($vRepAsim->asim_factura_pdf) ? "https://downloads.sos-mexico.com.mx/asimilados_fact_pdf/$vRepAsim->token_reporte_asim" : null,
            "asim_factura_xml" => null,
            "asim_factura_pdf" => null,
            "asim_valida_xml" => '',
            "puede_eliminar" => !$queryAsimPagoDone ? true : false,
          ];
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'reportes' => $listRepAsim,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function asimiladoSeguimientoOrdenPago(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_reporte_asim' => 'required|string',
      'asim_ord_pago_token' => 'required|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_reporte_asim = $request->input('token_reporte_asim');
      $asim_ord_pago_token = $request->input('asim_ord_pago_token');
      
      $queryAsimOrdenPago = AsimiladosModelo::join("fnzs_pagos_orden AS order", "vhum_reporte_asimilados_main.id", "=", "order.asimilados_reporte")
      ->join("main_empresas AS emp", "vhum_reporte_asimilados_main.asim_empresa", "=", "emp.id")
      ->join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->whereIn('vhum_reporte_asimilados_main.id', function ($query) {
        $query->select('asim_reporte')->from('vhum_reporte_asimilados_desglose');
      })
      ->where([
        'vhum_reporte_asimilados_main.asim_status' => TRUE,
        'vhum_reporte_asimilados_main.token_reporte_asim' => $token_reporte_asim,
        'order.token_ordenPago' => $asim_ord_pago_token,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->select(
        'vhum_reporte_asimilados_main.id AS id_asim',
        'vhum_reporte_asimilados_main.*',
        'order.*',
        'emp.*',
        'people.*'
      )
      ->get();
      
      if ($queryAsimOrdenPago->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron reportes de asimilados registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $orden_pago_nomina = array();
        $pagos_realizados_nomina = array();

        foreach ($queryAsimOrdenPago as $rOrdPag) {
          //da_te_default_timezone_set($rOrdPag->zona_horaria);
          $autorizacion_pay = $rOrdPag->autorizacion_pay ? true : false;
          $fecha_autorizacion_pay = $rOrdPag->autorizacion_pay ? gmdate('Y-m-d H:i:s', $rOrdPag->fecha_autorizacion_pay) : "---";
          $status_pay_bool = $rOrdPag->autorizacion_pay && $rOrdPag->orden_terminada_bool ? true : false;
  
          $orden_emisor_emp = $rOrdPag->abrev_nombre;
  
          $importe_total_anticipo = 0;
          $importe_total_inicial = 0;

          $moneda_asimilados = DB::table("vhum_reporte_asimilados_desglose")
          ->where('asim_reporte',$rOrdPag->id_asim)
          ->select('desglose_asim_moneda')
          ->first();

          $orden_moneda_inicial_name = $moneda_asimilados ? $moneda_asimilados->desglose_asim_moneda : 'MXN';
          $orden_moneda_inicial_decimales = $JwtAuth->getMonedaAPI($orden_moneda_inicial_name);
  
          $importe_autorizado_inicial = 0;
          $orden_moneda_autorizado_inicial_tkn = $orden_moneda_inicial_name;
          $orden_moneda_autorizado_inicial_name = $orden_moneda_inicial_name;
          $orden_moneda_autorizado_inicial_decimales = $JwtAuth->getMonedaAPI($orden_moneda_inicial_name);
  
          $importe_autorizado_final = 0;
          $orden_moneda_autorizado_final_name = $orden_moneda_inicial_name;
          $orden_moneda_autorizado_final_decimales = $JwtAuth->getMonedaAPI($orden_moneda_inicial_name);
          
          $total_percepciones = DB::table("vhum_reporte_asimilados_desglose")
          ->where('asim_reporte',$rOrdPag->id_asim)
          ->sum('total_percepciones');

          $total_deducciones = DB::table("vhum_reporte_asimilados_desglose")
          ->where('asim_reporte',$rOrdPag->id_asim)
          ->sum('total_deducciones');

          $importe_concepto_simple = $total_percepciones - $total_deducciones;

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
            "fecha_contabilizacion_doc_anterior" => gmdate('Y-m-d H:i:s',$rOrdPag->asim_fecha_contabilizacion),
            "fecha_contabilizacion_orden_pago" => $rOrdPag->fecha_contabilizacion_ordenPago ? gmdate('Y-m-d H:i:s',$rOrdPag->fecha_contabilizacion_ordenPago) : '',
            "fecha_registro" => gmdate('Y-m-d H:i:s', $rOrdPag->fecha_sistema_ordenp),
            "orden_bloqueada" => $rOrdPag->orden_bloqueada ? true : false,
            "autorizacion_pay" => $autorizacion_pay,
            "autorizacion_pay_translate" => $autorizacion_pay ? 'yes_auth' : 'not_auth',
            "autorizacion_pay_text" => "",
            "fecha_autorizacion_pay" => $fecha_autorizacion_pay,
            "factura_relacionada_typo" => "asimilados",
            "factura_relacionada_token" => $rOrdPag->token_reporte_asim,
            "factura_relacionada_string" => 'ASIM-'.$JwtAuth->generarFolio($rOrdPag->asim_folio_interior).(!is_null($rOrdPag->asim_subfolio) ? '-'.$rOrdPag->asim_subfolio : ''),
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
  
        $pagos_realizados_nomina = $JwtAuth->pagosDoneBYOrdenDesglose($queryAsimOrdenPago,$empresa,$usuario);
  
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'seguimiento_orden_pago' => $orden_pago_nomina,
          'pagos_realizados' => $pagos_realizados_nomina,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function asimiladoDesglose(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_reporte_asim' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_reporte_asim = $request->input('token_reporte_asim');

      $queryRepAsim = AsimiladosModelo::join("main_empresas AS emp", "vhum_reporte_asimilados_main.asim_empresa", "emp.id")
      ->whereIn('vhum_reporte_asimilados_main.id', function ($query) {
        $query->select('asim_reporte')->from('vhum_reporte_asimilados_desglose');
      })
      ->where([
        'vhum_reporte_asimilados_main.token_reporte_asim' => $token_reporte_asim,
        'vhum_reporte_asimilados_main.asim_status' => TRUE,
        'emp.empresa_token' => $empresa,
      ])
      ->orderBy('vhum_reporte_asimilados_main.id', 'DESC')
      ->select(
        'vhum_reporte_asimilados_main.id AS id_asim',
        'vhum_reporte_asimilados_main.*'
      )
      ->get();

      if ($queryRepAsim->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron reportes de asimilados registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $listRepAsim = [];

        $asimIDToken = $queryRepAsim->pluck('id_asim')->filter()->unique()->toArray();

        $asimOrdPagoMap = DB::table("fnzs_pagos_orden")
        ->whereIn('asimilados_reporte',$asimIDToken)
        //->select('token_ordenPago', 'folio_ordenPago')
        ->get()->keyBy('asimilados_reporte');

        foreach ($queryRepAsim as $vRepAsim) {
          $folio_reporte = 'ASIM-'.$JwtAuth->generarFolio($vRepAsim->asim_folio_interior).(!is_null($vRepAsim->asim_subfolio) ? '-'.$vRepAsim->asim_subfolio : '');

          $data_desglose = [];
          $data_cfdi_comprobante = [];
          $data_cfdi_emisor = [];
          $data_cfdi_receptor = [];
          $data_cfdi_conceptos = [];
          $data_cfdi_relacionados = [];
          $data_cfdi_complemento = [];
          $data_cfdi_complementonomina = [];
          
          $queryAsimPagoDone = DB::table("fnzs_pagos_pago AS pay")
          ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
          ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
          ->where('order.asimilados_reporte',$vRepAsim->id_asim)
          ->exists();

          $queryCFDIMoneda = DB::table("cfdi_comprobantes_fiscales AS cfdi_comp")
          ->join("cfdi_vinculacion_asimilados_reporte AS vinc_asim", "cfdi_comp.id", "=", "vinc_asim.comprobante_fiscal")
          ->where('vinc_asim.asimilados_reporte_vinculado',$vRepAsim->id_asim)
          ->select('cfdi_comp.cfdi_comprobante_moneda')
          ->first();
          $decimalesMoneda = $JwtAuth->getMonedaAPI($queryCFDIMoneda->cfdi_comprobante_moneda ?? 'MXN');

          $queryAsmDesg = DB::table("vhum_reporte_asimilados_desglose")
          ->where('asim_reporte',$vRepAsim->id_asim)
          ->get();

          foreach ($queryAsmDesg as $vDesg) {
            $queryAsmDesgReceptor = DB::table("sos_personas AS prov")
            ->join("eegr_catalogo_proveedores AS catprov", "prov.id", "catprov.proveedor")
            ->where('catprov.id',$vDesg->desglose_asim_receptor)
            ->select('catprov.token_cat_proveedores','prov.nombre_extendido')
            ->first();
            $desglose_asim_receptor_token = $queryAsmDesgReceptor ? $queryAsmDesgReceptor->token_cat_proveedores : '';
            $desglose_asim_receptor_nombre = $queryAsmDesgReceptor ? $JwtAuth->desencriptar($queryAsmDesgReceptor->nombre_extendido) : '';

            $queryAsmDesgImpu = DB::table("cont_impuestos_catalogo")
            ->where('id',$vDesg->deducciones_impuesto_asociado)
            ->first();
            $folio_impuesto = 'IMP-' .$JwtAuth->generarFolio($queryAsmDesgImpu->folio_impuesto).(!is_null($queryAsmDesgImpu->post_folio) ? '-'.$queryAsmDesgImpu->post_folio : '');
            $abreviacion_impuesto = $JwtAuth->desencriptar($queryAsmDesgImpu->abreviacion_impuesto);
            $concepto_impuesto = $JwtAuth->desencriptar($queryAsmDesgImpu->concepto_impuesto);
            $impuesto_asociado_token = $queryAsmDesgImpu ? $queryAsmDesgImpu->token_catalogo_impuesto : '';
            $impuesto_asociado_nombre = $queryAsmDesgImpu ? "$folio_impuesto $abreviacion_impuesto $concepto_impuesto" : '';
            
            $queryAsmDesgServ = DB::table("in_egr_catalogo_servicios")
            ->where('id',$vDesg->percepciones_servicio_asociado)
            ->first();
            $folio_servicio = 'SERV-'.$JwtAuth->generarFolio($queryAsmDesgServ->folio_sistema).(!is_null($queryAsmDesgServ->post_folio) ? '-'.$queryAsmDesgServ->post_folio : '');
            $namee_servicio = $JwtAuth->desencriptar($queryAsmDesgServ->servicio);
            $servicio_asociado_token = $queryAsmDesgServ ? $queryAsmDesgServ->token_cat_servicios : '';
            $servicio_asociado_nombre = $queryAsmDesgServ ? "$folio_servicio $namee_servicio" : '';

            $data_desglose[] = [
              "desglose_asim_receptor_token" => $desglose_asim_receptor_token,
              "desglose_asim_receptor_nombre" => $desglose_asim_receptor_nombre,
              "desglose_asim_folio" => $vDesg->desglose_asim_folio,
              "desglose_asim_periodo_inicio" => !$queryAsimPagoDone ? $vDesg->desglose_asim_periodo_inicio : $vDesg->desglose_asim_periodo_inicio,
              "desglose_asim_periodo_fin" => !$queryAsimPagoDone ? $vDesg->desglose_asim_periodo_fin : $vDesg->desglose_asim_periodo_fin,
              "desglose_asim_fecha_pago" => !$queryAsimPagoDone ? $vDesg->desglose_asim_fecha_pago : $vDesg->desglose_asim_fecha_pago,
              "desglose_asim_moneda" => $vDesg->desglose_asim_moneda,
              "desglose_asim_dias_pagados" => $vDesg->desglose_asim_dias_pagados,
              "total_deducciones" => number_format($vDesg->total_deducciones,$decimalesMoneda,'.', ''),
              "impuesto_asociado_token" => $impuesto_asociado_token,
              "impuesto_asociado_nombre" => $impuesto_asociado_nombre,
              "total_percepciones" => number_format($vDesg->total_percepciones,$decimalesMoneda,'.', ''),
              "servicio_asociado_token" => $servicio_asociado_token,
              "servicio_asociado_nombre" => $servicio_asociado_nombre,
            ];
          }

          $queryCFDIInfo = DB::table("cfdi_comprobantes_fiscales AS cfdi_comp")
          ->join("cfdi_vinculacion_asimilados_reporte AS vinc_asim", "cfdi_comp.id", "=", "vinc_asim.comprobante_fiscal")
          ->where('vinc_asim.asimilados_reporte_vinculado',$vRepAsim->id_asim)
          ->select('cfdi_comp.*')
          ->get();

          foreach ($queryCFDIInfo as $vCFDI) {
            $receptor_token = "";
            $receptor_nombre = "";

            $data_cfdi_comprobante[] = [
              "cfdi_comprobantes_token" => $vCFDI->cfdi_comprobantes_token,
              "origen_proceso" => $vCFDI->origen_proceso,
              "cfdi_comprobante_fecha_contabilizacion" => $vCFDI->cfdi_comprobante_fecha_contabilizacion,
              "version" => $vCFDI->cfdi_comprobante_version,
              "serie" => $vCFDI->cfdi_comprobante_serie,
              "folio" => $vCFDI->cfdi_comprobante_folio,
              "fecha" => $vCFDI->cfdi_comprobante_fecha,
              "forma_de_pago" => $vCFDI->cfdi_comprobante_forma_de_pago,
              "subtotal" => number_format($vCFDI->cfdi_comprobante_subtotal,$decimalesMoneda,'.', ''),
              "Descuento" => number_format($vCFDI->cfdi_comprobante_descuento,$decimalesMoneda,'.', ''),
              "moneda" => $vCFDI->cfdi_comprobante_moneda,
              "tipo_de_cambio" => number_format($vCFDI->cfdi_comprobante_tipo_de_cambio,$decimalesMoneda,'.', ''),
              "total" => number_format($vCFDI->cfdi_comprobante_total,$decimalesMoneda,'.', ''),
              "confirmacion" => $vCFDI->cfdi_comprobante_confirmacion,
              "tipo_de_comprobante" => $vCFDI->cfdi_comprobante_tipo_de_comprobante,
              "metodo_de_pago" => $vCFDI->cfdi_comprobante_metodo_de_pago,
              "lugar_de_expedicion" => $vCFDI->cfdi_comprobante_lugar_de_expedicion,
              "no_de_certificado" => $vCFDI->cfdi_comprobante_no_de_certificado,
              "sello" => $vCFDI->cfdi_comprobante_sello,
              "certificado" => $vCFDI->cfdi_comprobante_certificado,
            ];

            $data_cfdi_emisor[] = [
              "rfc_del_emisor" => $vCFDI->cfdi_emisor_rfc,
              "nombre_del_emisor" => $vCFDI->cfdi_emisor_nombre,
              "regimen_fiscal_del_emisor" => $vCFDI->cfdi_emisor_regimen_fiscal
            ];

            $data_cfdi_complemento[] = [
              "Version" => $vCFDI->cfdi_complementoVersion,
              "UUID" => $vCFDI->cfdi_complementoUUID,
              "FechaTimbrado" => $vCFDI->cfdi_complementoFechaTimbrado,
              "RfcProvCertif" => $vCFDI->cfdi_complementoRfcProvCertif,
              "NoCertificadoSAT" => $vCFDI->cfdi_complementoNoCertificadoSAT,
              "SelloCFD" => $vCFDI->cfdi_complementoSelloCFD,
              "SelloSAT" => $vCFDI->cfdi_complementoSelloSAT
            ];
            
            $queryCFDIconceptos = DB::table("cfdi_comprobantes_conceptos")
            ->where('comprobante_fiscal',$vCFDI->id)
            ->get();

            foreach ($queryCFDIconceptos as $vCFDIcon) {
              //$cfdiTraslados = $vCFDIcon->total_traslados ? (float)$vCFDIcon->total_traslados : 0;
              //$cfdiRetenciones = $vCFDIcon->total_retenciones ? (float)$vCFDIcon->total_retenciones : 0;
              $cfdiSubtotal = $vCFDIcon->ValorUnitario - $vCFDIcon->Descuento;// + (float)$cfdiTraslados - (float)$vCFDIcon->total_retenciones;
              $data_cfdi_conceptos[] = [
                "uuid_cfdi_detalle" => $vCFDIcon->uuid_cfdi_detalle,
                "ClaveProdServ" => $vCFDIcon->ClaveProdServ,
                "Cantidad" => $vCFDIcon->Cantidad,
                "ClaveUnidad" => $vCFDIcon->ClaveUnidad,
                "Descripcion" => $vCFDIcon->Descripcion,
                "ValorUnitario" => number_format($vCFDIcon->ValorUnitario ,$decimalesMoneda,'.', ''),
                "Importe" => number_format($vCFDIcon->Importe ,$decimalesMoneda,'.', ''),
                "Descuento" => number_format($vCFDIcon->Descuento ,$decimalesMoneda,'.', ''),
                "Subtotal" => number_format($vCFDIcon->Importe ,$decimalesMoneda,'.', ''),
                "ObjetoImp" => $vCFDIcon->ObjetoImp
              ];
            }

            $queryCFDINominas = DB::table("cfdi_asimilados_nomina")
            ->where('comprobante_fiscal',$vCFDI->id)
            ->get();

            foreach ($queryCFDINominas as $vCFDInomi) {
              $data_cfdi_nomina_receptor = [];
              $queryCFDINomiReceptor = DB::table("cfdi_asimilados_nomina_receptor AS nom_recptor")
              ->join("eegr_catalogo_proveedores AS catprov", "nom_recptor.empleado_referenciado", "catprov.id")
              ->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
              ->where('nom_recptor.asimilados_nomina',$vCFDInomi->uuid_asimilados_nomina)
              ->select('nom_recptor.*','catprov.token_cat_proveedores','prov.nombre_extendido')
              ->get();

              foreach ($queryCFDINomiReceptor as $vNomRec) {
                $receptor_token = $vNomRec->token_cat_proveedores;
                $receptor_nombre = $JwtAuth->desencriptar($vNomRec->nombre_extendido);
                
                $data_cfdi_nomina_receptor[] = [
                  "uuid_nomina_receptor" => $vNomRec->uuid_nomina_receptor,
                  "ClaveEntFed" => $vNomRec->ClaveEntFed,
                  "Curp" => $vNomRec->Curp,
                  "NumEmpleado" => $vNomRec->NumEmpleado,
                  "TipoContrato" => $vNomRec->TipoContrato,
                  "PeriodicidadPago" => $vNomRec->PeriodicidadPago,
                  "TipoRegimen" => $vNomRec->TipoRegimen
                ];
              }

              $data_cfdi_nomina_percepciones = [];
              $queryCFDINomiPercepciones = DB::table("cfdi_asimilados_nomina_percepciones")
              ->where('asimilados_nomina',$vCFDInomi->uuid_asimilados_nomina)
              ->get();

              foreach ($queryCFDINomiPercepciones as $vNomPerm) {
                $data_cfdi_nomina_percepcion = [];
                $queryCFDINomiPercepcion = DB::table("cfdi_asimilados_nomina_percepciones_percepcion")
                ->where('asimilados_nomina',$vCFDInomi->uuid_asimilados_nomina)
                ->where('nomina_percepciones',$vNomPerm->uuid_nomina_percepciones)
                ->get();
  
                foreach ($queryCFDINomiPercepcion as $vNomPerd) {
                  $data_cfdi_nomina_percepcion[] = [
                    "uuid_nomina_percepcion" => $vNomPerd->uuid_nomina_percepcion,
                    "Clave" => $vNomPerd->Clave,
                    "Concepto" => $vNomPerd->Concepto,
                    "ImporteExento" => number_format($vNomPerd->ImporteExento,$decimalesMoneda,'.', ''),
                    "ImporteGravado" => number_format($vNomPerd->ImporteGravado,$decimalesMoneda,'.', ''),
                    "TipoPercepcion" => $vNomPerd->TipoPercepcion
                  ];
                }

                $data_cfdi_nomina_percepciones[] = [
                  "uuid_nomina_percepciones" => $vNomPerm->uuid_nomina_percepciones,
                  "TotalExento" => number_format($vNomPerm->TotalExento,$decimalesMoneda,'.', ''),
                  "TotalGravado" => number_format($vNomPerm->TotalGravado,$decimalesMoneda,'.', ''),
                  "TotalSueldos" => number_format($vNomPerm->TotalSueldos,$decimalesMoneda,'.', ''),
                  "nomina_percepcion" => $data_cfdi_nomina_percepcion
                ];
              }

              $data_cfdi_nomina_deducciones = [];
              $queryCFDINomiDeducciones = DB::table("cfdi_asimilados_nomina_deducciones")
              ->where('asimilados_nomina',$vCFDInomi->uuid_asimilados_nomina)
              ->get();

              foreach ($queryCFDINomiDeducciones as $vNomDedm) {
                $data_cfdi_nomina_deduccion = [];
                $queryCFDINomiDeduccion = DB::table("cfdi_asimilados_nomina_deducciones_deduccion")
                ->where('asimilados_nomina',$vCFDInomi->uuid_asimilados_nomina)
                ->where('nomina_deducciones',$vNomDedm->uuid_nomina_deducciones)
                ->get();
  
                foreach ($queryCFDINomiDeduccion as $vNomDedd) {
                  $data_cfdi_nomina_deduccion[] = [
                    "uuid_nomina_deduccion" => $vNomDedd->uuid_nomina_deduccion,
                    "Clave" => $vNomDedd->Clave,
                    "Concepto" => $vNomDedd->Concepto,
                    "Importe" => number_format($vNomDedd->Importe,$decimalesMoneda,'.', ''),
                    "TipoDeduccion" => $vNomDedd->TipoDeduccion
                  ];
                }

                $data_cfdi_nomina_deducciones[] = [
                  "uuid_nomina_deducciones" => $vNomDedm->uuid_nomina_deducciones,
                  "TotalImpuestosRetenidos" => number_format($vNomDedm->TotalImpuestosRetenidos,$decimalesMoneda,'.', ''),
                  "TotalOtrasDeducciones" => number_format($vNomDedm->TotalOtrasDeducciones,$decimalesMoneda,'.', ''),
                  "nomina_deduccion" => $data_cfdi_nomina_deduccion
                ];
              }
              
              $data_cfdi_complementonomina[] = [
                "uuid_asimilados_nomina" => $vCFDInomi->uuid_asimilados_nomina,
                "FechaFinalPago" => Carbon::parse($vCFDInomi->FechaFinalPago)->format('Y/m/d'),//date('Y-d-m', $vCFDInomi->FechaFinalPago),
                "FechaInicialPago" => Carbon::parse($vCFDInomi->FechaInicialPago)->format('Y/m/d'),
                "FechaPago" => Carbon::parse($vCFDInomi->FechaPago)->format('Y/m/d'),
                "NumDiasPagados" => $vCFDInomi->NumDiasPagados,
                "TipoNomina" => $vCFDInomi->TipoNomina,
                "TotalDeducciones" => number_format($vCFDInomi->TotalDeducciones,$decimalesMoneda,'.', ''),
                "TotalOtrosPagos" => number_format($vCFDInomi->TotalOtrosPagos,$decimalesMoneda,'.', ''),
                "TotalPercepciones" => number_format($vCFDInomi->TotalPercepciones,$decimalesMoneda,'.', ''),
                "Version" => $vCFDInomi->Version,
                "nomina_receptor" => $data_cfdi_nomina_receptor,
                "nomina_percepciones" => $data_cfdi_nomina_percepciones,
                "nomina_deducciones" => $data_cfdi_nomina_deducciones,
              ];
            }

            $data_cfdi_receptor[] = [
              "Rfc" => $vCFDI->cfdi_receptor_rfc,
              "token" => $receptor_token,
              "nombre" => $receptor_nombre,
              "UsoCFDI" => $vCFDI->cfdi_receptor_domicilio_fiscal,
              "RegimenFiscalReceptor" => $vCFDI->cfdi_receptor_regimen_fiscal,
              "DomicilioFiscalReceptor" => $vCFDI->cfdi_receptor_uso_del_cfdi
            ];
          }

          if ($queryAsimPagoDone) {
            $listRepAsim[] = [
              "asim_fecha_registro" => date('Y-m-d',$vRepAsim->asim_fecha_registro),
              "asim_folio" => $folio_reporte,
  
              "asim_fecha_contabilizacion" => date('Y-m-d',$vRepAsim->asim_fecha_contabilizacion),
              "asim_factura_doc_xml" => !is_null($vRepAsim->asim_factura_xml) ? $JwtAuth->desencriptar($vRepAsim->asim_factura_xml) : null,
              "asim_factura_doc_pdf" => !is_null($vRepAsim->asim_factura_pdf) ? $JwtAuth->desencriptar($vRepAsim->asim_factura_pdf) : null,
              "asim_desglose" => $data_desglose,
              "asim_cfdi_comprobante" => $data_cfdi_comprobante,
              "asim_cfdi_emisor" => $data_cfdi_emisor,
              "asim_cfdi_receptor" => $data_cfdi_receptor,
              "asim_cfdi_conceptos" => $data_cfdi_conceptos,
              "asim_cfdi_relacionados" => $data_cfdi_relacionados,
              "asim_cfdi_complemento" => $data_cfdi_complemento,
              "asim_cfdi_complementonomina" => $data_cfdi_complementonomina,
              "asim_observaciones" => $JwtAuth->desencriptar($vRepAsim->asim_observaciones)
            ];
          } else {
            $listRepAsim[] = [
              "token_reporte_asim" => $vRepAsim->token_reporte_asim,
              "asim_fecha_registro" => date('Y-m-d',$vRepAsim->asim_fecha_registro),
              "asim_folio" => $folio_reporte,
  
              "asim_fecha_contabilizacion" => date('Y-m-d',$vRepAsim->asim_fecha_contabilizacion),
              "asim_factura_doc_xml" => !is_null($vRepAsim->asim_factura_xml) ? $JwtAuth->desencriptar($vRepAsim->asim_factura_xml) : null,
              "asim_factura_doc_pdf" => !is_null($vRepAsim->asim_factura_pdf) ? $JwtAuth->desencriptar($vRepAsim->asim_factura_pdf) : null,
              "asim_desglose" => $data_desglose,
              "asim_cfdi_comprobante" => $data_cfdi_comprobante,
              "asim_cfdi_emisor" => $data_cfdi_emisor,
              "asim_cfdi_receptor" => $data_cfdi_receptor,
              "asim_cfdi_conceptos" => $data_cfdi_conceptos,
              "asim_cfdi_relacionados" => $data_cfdi_relacionados,
              "asim_cfdi_complemento" => $data_cfdi_complemento,
              "asim_cfdi_complementonomina" => $data_cfdi_complementonomina,
              "asim_observaciones" => $JwtAuth->desencriptar($vRepAsim->asim_observaciones)
            ];
          }
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'reporte' => $listRepAsim,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function actualizaAsimiladoReporte(Request $request) {
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $validate = \Validator::make($request->all(), [
      'token_reporte_asim' => 'required|string',
      'percepciones_servicio' => 'required|string', // Token del catálogo de servicios
      'deducciones_impuesto' => 'required|string',  // Token del catálogo de impuestos
      'observaciones' => 'required|string'
    ]);

    if ($validate->fails()) {
      return response()->json([
        'status' => 'error',
        'message' => 'Datos de actualización incompletos o inválidos',
        'errors' => $validate->errors()
      ], 428);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    $tokenReporte = $request->input('token_reporte_asim');

    // 3. Verificación de existencia y pertenencia a la empresa
    $reporteMain = AsimiladosModelo::join("main_empresas AS emp", "vhum_reporte_asimilados_main.asim_empresa", "emp.id")
    ->where('vhum_reporte_asimilados_main.token_reporte_asim', $tokenReporte)
    ->where('emp.empresa_token', $empresa)
    ->select('vhum_reporte_asimilados_main.*')
    ->first();

    if (!$reporteMain) {
        return response()->json(['status' => 'error', 'message' => 'El reporte no existe o no pertenece a esta empresa'], 404);
    }

    // 4. Regla de Oro: Bloquear si ya existe un pago realizado
    $pagoVinculado = DB::table("fnzs_pagos_pago_ordenes_vinculadas AS vinc")
    ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
    ->where('order.asimilados_reporte', $reporteMain->id)
    ->exists();

    if ($pagoVinculado) {
      return response()->json([
        'status' => 'error', 
        'message' => 'No es posible editar la clasificación de un reporte que ya cuenta con un pago vinculado.'
      ], 403);
    }

    // 5. Proceso de actualización
    DB::beginTransaction();
    try {
      // A. Obtener IDs internos de los catálogos mediante los tokens
      $idServicio = DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios", $request->input('percepciones_servicio'))->value("id");
      $idImpuesto = DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $request->input('deducciones_impuesto'))->value("id");

      if (!$idServicio || !$idImpuesto) {
        throw new \Exception("Uno de los catálogos seleccionados (Servicio/Impuesto) no es válido.");
      }

      // B. Actualizar tabla de Desglose (Relación operativa)
      DB::table("vhum_reporte_asimilados_desglose")
      ->where('asim_reporte', $reporteMain->id)
      ->update([
        "percepciones_servicio_asociado" => $idServicio,
        "deducciones_impuesto_asociado"  => $idImpuesto,
      ]);

      // C. Actualizar tabla Principal (Metadatos)
      $reporteMain->asim_observaciones = $JwtAuth->encriptar($request->input('observaciones'));
      $reporteMain->save();

      // D. Sincronizar Orden de Pago (Opcional: actualizar fecha si es necesario)
      DB::table("fnzs_pagos_orden")
      ->where('asimilados_reporte', $reporteMain->id)
      ->update([
        'doc_anterior_fecha_contabilizacion' => $reporteMain->asim_fecha_contabilizacion
      ]);

      DB::commit();

      return response()->json([
        'status'  => 'success',
        'code'    => 200,
        'message' => 'La clasificación del reporte y las observaciones se han actualizado correctamente.'
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

  public function eliminaAsimiladoReporte(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_reporte_asim' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_reporte_asim = $request->input('token_reporte_asim');
      
      $queryRepAsim = AsimiladosModelo::join("main_empresas AS emp", "vhum_reporte_asimilados_main.asim_empresa", "emp.id")
      ->whereIn('vhum_reporte_asimilados_main.id', function ($query) {
        $query->select('asim_reporte')->from('vhum_reporte_asimilados_desglose');
      })
      ->where([
        'vhum_reporte_asimilados_main.token_reporte_asim' => $token_reporte_asim,
        'vhum_reporte_asimilados_main.asim_status' => TRUE,
        'emp.empresa_token' => $empresa,
      ])
      ->select('vhum_reporte_asimilados_main.*')
      ->first();

      if (!$queryRepAsim) {
        return response()->json(['status' => 'error', 'message' => 'El reporte no existe o no pertenece a esta empresa'], 404);
      }
      
      $pagoVinculado = DB::table("fnzs_pagos_pago_ordenes_vinculadas AS vinc")
      ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
      ->where('order.asimilados_reporte', $queryRepAsim->id)
      ->exists();
  
      if ($pagoVinculado) {
        return response()->json([
          'status' => 'error', 
          'message' => 'No es posible editar la clasificación de un reporte que ya cuenta con un pago vinculado.'
        ], 403);
      }
      
      DB::beginTransaction();
      try {
        // C. Actualizar tabla Principal (Metadatos)
        $queryRepAsim->asim_status = FALSE;
        $queryRepAsim->asim_fecha_delete = time();
        $queryRepAsim->save();
  
        DB::commit();
  
        return response()->json([
          'status'  => 'success',
          'code'    => 200,
          'message' => 'El reporte ha sido eliminado correctamente.'
        ]);
      } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
          'status'  => 'error',
          'code'    => 500,
          'message' => 'Error interno al procesar la eliminación: ' . $e->getMessage()
        ], 500);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function reportesDeletedAsimilados(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryRepAsim = AsimiladosModelo::join("main_empresas AS emp", "vhum_reporte_asimilados_main.asim_empresa", "emp.id")
    ->whereIn('vhum_reporte_asimilados_main.id', function ($query) {
      $query->select('asim_reporte')->from('vhum_reporte_asimilados_desglose');
    })
    ->where([
      'vhum_reporte_asimilados_main.asim_status' => FALSE,
      'emp.empresa_token' => $empresa,
    ])
    ->orderBy('vhum_reporte_asimilados_main.id', 'DESC')
    ->select(
      'vhum_reporte_asimilados_main.id AS id_asim',
      'vhum_reporte_asimilados_main.*'
    )
    ->get();

    if ($queryRepAsim->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron reportes de asimilados registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listRepAsim = [];

      $asimIDToken = $queryRepAsim->pluck('id_asim')->filter()->unique()->toArray();

      $asimOrdPagoMap = DB::table("fnzs_pagos_orden")
      ->whereIn('asimilados_reporte',$asimIDToken)
      //->select('token_ordenPago', 'folio_ordenPago')
      ->get()->keyBy('asimilados_reporte');

      foreach ($queryRepAsim as $vRepAsim) {
        $asim_moneda = "MXN";
        $asim_moneda_decimales = $JwtAuth->getMonedaAPI($asim_moneda);
        $folio_reporte = 'ASIM-'.$JwtAuth->generarFolio($vRepAsim->asim_folio_interior).(!is_null($vRepAsim->asim_subfolio) ? '-'.$vRepAsim->asim_subfolio : '');

        $total_percepciones = DB::table("vhum_reporte_asimilados_desglose")
        ->where('asim_reporte',$vRepAsim->id_asim)
        ->sum('total_percepciones');

        $total_deducciones = DB::table("vhum_reporte_asimilados_desglose")
        ->where('asim_reporte',$vRepAsim->id_asim)
        ->sum('total_deducciones');

        $asim_total_a_pagar = $total_percepciones - $total_deducciones;

        $totales_asim_pago = DB::table("fnzs_pagos_pago AS pay")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
        ->where('order.asimilados_reporte',$vRepAsim->id_asim)
        ->sum('pay.monto_pago');

        $totales_asim_saldo = $asim_total_a_pagar - $totales_asim_pago;

        $queryAsimOrdPago = $asimOrdPagoMap->get($vRepAsim->id_asim);
        $asim_ord_pago_token = $queryAsimOrdPago ? $queryAsimOrdPago->token_ordenPago :'';
        $asim_ord_pago_folio = $queryAsimOrdPago ? "ORDP-".$JwtAuth->generarFolio($queryAsimOrdPago->folio_ordenPago) :'';

        $queryAsimPagoDone = DB::table("fnzs_pagos_pago AS pay")
        ->join("fnzs_pagos_pago_ordenes_vinculadas AS vinc", "pay.id", "=","vinc.pago_realizado")
        ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "=", "order.id")
        ->where('order.asimilados_reporte',$vRepAsim->id_asim)
        ->exists();

        $listRepAsim[] = [
          "token_reporte_asim" => $vRepAsim->token_reporte_asim,
          "asim_fecha_registro" => date('Y-m-d',$vRepAsim->asim_fecha_registro),
          "asim_folio" => $folio_reporte,
          "asim_fecha_contabilizacion" => date('Y-m-d',$vRepAsim->asim_fecha_contabilizacion),
          "asim_observaciones" => $JwtAuth->desencriptar($vRepAsim->asim_observaciones),
          "asim_total_a_pagar" => "$".number_format($asim_total_a_pagar,$asim_moneda_decimales,'.',',')." $asim_moneda",
          'asim_pago' => "$".number_format($totales_asim_pago,$asim_moneda_decimales,'.', ',')." $asim_moneda",
          'asim_saldo' => "$".number_format($totales_asim_saldo,$asim_moneda_decimales,'.', ',')." $asim_moneda",
          "asim_ord_pago_token" => $asim_ord_pago_token,
          "asim_ord_pago_folio" => $asim_ord_pago_folio,
          "asim_habilita_carga_docs" => $queryAsimPagoDone ? true : false,
          "asim_factura_doc_xml" => !is_null($vRepAsim->asim_factura_xml) ? $JwtAuth->desencriptar($vRepAsim->asim_factura_xml) : null,
          "asim_url_doc_xml" => !is_null($vRepAsim->asim_factura_xml) ? "https://downloads.sos-mexico.com.mx/asimilados_fact_xml/$vRepAsim->token_reporte_asim" : null,
          "asim_factura_doc_pdf" => !is_null($vRepAsim->asim_factura_pdf) ? $JwtAuth->desencriptar($vRepAsim->asim_factura_pdf) : null,
          "asim_url_doc_pdf" => !is_null($vRepAsim->asim_factura_pdf) ? "https://downloads.sos-mexico.com.mx/asimilados_fact_pdf/$vRepAsim->token_reporte_asim" : null,
          "asim_factura_xml" => null,
          "asim_factura_pdf" => null,
          "asim_valida_xml" => '',
          "puede_eliminar" => !$queryAsimPagoDone ? true : false,
        ];
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'reportes' => $listRepAsim,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restauraAsimiladoReporte(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_reporte_asim' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_reporte_asim = $request->input('token_reporte_asim');
      
      $queryRepAsim = AsimiladosModelo::join("main_empresas AS emp", "vhum_reporte_asimilados_main.asim_empresa", "emp.id")
      ->whereIn('vhum_reporte_asimilados_main.id', function ($query) {
        $query->select('asim_reporte')->from('vhum_reporte_asimilados_desglose');
      })
      ->where([
        'vhum_reporte_asimilados_main.token_reporte_asim' => $token_reporte_asim,
        'vhum_reporte_asimilados_main.asim_status' => FALSE,
        'emp.empresa_token' => $empresa,
      ])
      ->select('vhum_reporte_asimilados_main.*')
      ->first();

      if (!$queryRepAsim) {
        return response()->json(['status' => 'error', 'message' => 'El reporte no existe o no pertenece a esta empresa'], 404);
      }
      
      DB::beginTransaction();
      try {
        // C. Actualizar tabla Principal (Metadatos)
        $queryRepAsim->asim_status = TRUE;
        $queryRepAsim->asim_fecha_delete = NULL;
        $queryRepAsim->save();
  
        DB::commit();
  
        return response()->json([
          'status'  => 'success',
          'code'    => 200,
          'message' => 'El reporte ha sido restaurado correctamente.'
        ]);
      } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
          'status'  => 'error',
          'code'    => 500,
          'message' => 'Error interno al procesar la restauración: ' . $e->getMessage()
        ], 500);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaPermanenteAsimiladoReporte(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(), [
      'token_reporte_asim' => 'required|string'
    ]);

    if ($validate->fails()) {
      return response()->json(['status' => 'error', 'message' => 'Token de reporte requerido'], 428);
    }

    $tokenReporte = $request->input('token_reporte_asim');

    // 2. Verificar existencia y pertenencia
    $reporte = AsimiladosModelo::join("main_empresas AS emp", "vhum_reporte_asimilados_main.asim_empresa", "emp.id")
    ->where('vhum_reporte_asimilados_main.token_reporte_asim', $tokenReporte)
    ->where('emp.empresa_token', $empresaToken)
    ->select('vhum_reporte_asimilados_main.id', 'vhum_reporte_asimilados_main.asim_fecha_registro', 'vhum_reporte_asimilados_main.asim_folio_interior', 'emp.root_tkn')
    ->first();

    if (!$reporte) {
      return response()->json(['status' => 'error', 'message' => 'Reporte no encontrado'], 404);
    }

    // 3. BLOQUEO CRÍTICO: No eliminar si ya hay un pago real
    $pagoRealizado = DB::table("fnzs_pagos_pago_ordenes_vinculadas AS vinc")
    ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
    ->where('order.asimilados_reporte', $reporte->id)
    ->exists();

    if ($pagoRealizado) {
      return response()->json([
        'status' => 'error', 
        'message' => 'No se puede eliminar un reporte que ya tiene un pago aplicado en tesorería.'
      ], 403);
    }

    DB::beginTransaction();
    try {
      // --- A. LIMPIEZA DE ESTRUCTURA CFDI ---
      $cfdiComprobante = DB::table('cfdi_vinculacion_asimilados_reporte')
      ->where('asimilados_reporte_vinculado', $reporte->id)
      ->first();
      
      if ($cfdiComprobante) {
        $idFiscal = $cfdiComprobante->comprobante_fiscal;

        // Eliminar detalles de nómina (percepciones y deducciones)
        DB::table('cfdi_asimilados_nomina_deducciones_deduccion')->where('comprobante_fiscal', $idFiscal)->delete();
        DB::table('cfdi_asimilados_nomina_deducciones')->where('comprobante_fiscal', $idFiscal)->delete();
        DB::table('cfdi_asimilados_nomina_percepciones_percepcion')->where('comprobante_fiscal', $idFiscal)->delete();
        DB::table('cfdi_asimilados_nomina_percepciones')->where('comprobante_fiscal', $idFiscal)->delete();
        DB::table('cfdi_asimilados_nomina_receptor')->where('comprobante_fiscal', $idFiscal)->delete();
        DB::table('cfdi_asimilados_nomina')->where('comprobante_fiscal', $idFiscal)->delete();
          
        // Eliminar conceptos y vinculación
        DB::table('cfdi_comprobantes_conceptos')->where('comprobante_fiscal', $idFiscal)->delete();
        DB::table('cfdi_vinculacion_asimilados_reporte')->where('comprobante_fiscal', $idFiscal)->delete();
          
        // Eliminar cabecera fiscal
        DB::table('cfdi_comprobantes_fiscales')->where('id', $idFiscal)->delete();
      }

      // --- B. LIMPIEZA OPERATIVA ---
      // Eliminar Orden de Pago
      DB::table('fnzs_pagos_orden')->where('asimilados_reporte', $reporte->id)->delete();

      // Eliminar Documentos/Evidencias de la tabla sos_documentos
      DB::table('sos_documentos')->where('asimilados_reporte', $reporte->id)->delete();

      // Eliminar Desglose del reporte
      DB::table('vhum_reporte_asimilados_desglose')->where('asim_reporte', $reporte->id)->delete();

      // --- C. ELIMINAR REGISTRO PRINCIPAL ---
      DB::table('vhum_reporte_asimilados_main')->where('id', $reporte->id)->delete();

      // --- D. LIMPIEZA DE ARCHIVOS FÍSICOS (STORAGE) ---
      $JwtAuth = new \App\Helpers\JwtAuth();
      $folioReporte = 'ASIM-' . $JwtAuth->generarFolio($reporte->asim_folio_interior);
      $directoryPath = "public/root/" . $reporte->root_tkn . "/0004-vhm/asimilados/" . $reporte->asim_fecha_registro . "-" . $folioReporte;

      if (Storage::exists($directoryPath)) {
        Storage::deleteDirectory($directoryPath);
      }

      DB::commit();

      return response()->json([
        'status' => 'success',
        'message' => 'Reporte, archivos y estructura fiscal eliminados permanentemente.'
      ]);
    } catch (\Exception $e) {
      DB::rollBack();
      return response()->json([
        'status' => 'error',
        'message' => 'Error al intentar eliminar el reporte: ' . $e->getMessage()
      ], 500);
    }
  }
}