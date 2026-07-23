<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\OrdenPagoModelo;

class EGRE_ReembolsosController extends Controller{
	public function reembolso_lista_general_partidas(Request $request){
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
      $JwtAuth = new \App\Helpers\JwtAuth();
      date_default_timezone_set('America/Mexico_City');
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
      
      if ($JwtAuth->usersAdmins($usuario)) {
        $list_reembolso = DB::table("terc_reembolso_solicitud AS reem_soli")
        ->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
        ->join("sos_reembolsos_comisiones_rel AS reem_comi", "reem_main.id", "=", "reem_comi.reembolso_main")
        ->join("terc_comisiones_main AS comi_soli", "reem_comi.comision", "=", "comi_soli.id")
        ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
        ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
          return $query->whereBetween("reem_main.fecha_sistema", [$fechaInicio, $fechaFin]);
        })
        ->whereNotNull("reem_main.user_receptor_egr")
        ->where([
          "reem_soli.status_activacion" => TRUE,
          "reem_main.status_reem" => TRUE,
          "reem_main.borrador_reem" => FALSE,
          "emp.empresa_token" => $empresa
        ])
        ->orderBy('reem_main.folio_reem', 'DESC')->get();
      } else {
        $list_reembolso = DB::table("terc_reembolso_solicitud AS reem_soli")
        ->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
        ->join("sos_reembolsos_comisiones_rel AS reem_comi", "reem_main.id", "=", "reem_comi.reembolso_main")
        ->join("terc_comisiones_main AS comi_soli", "reem_comi.comision", "=", "comi_soli.id")
        ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
        ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
        ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
        ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
          return $query->whereBetween("reem_main.fecha_sistema", [$fechaInicio, $fechaFin]);
        })
        ->where([
          "reem_soli.status_activacion" => TRUE,
          "reem_main.status_reem" => TRUE,
          "reem_main.borrador_reem" => FALSE,
          "emp.empresa_token" => $empresa,
          "users.usuario_token" => $usuario
        ])
        ->orderBy('reem_main.folio_reem', 'DESC')->get();
      }

      if ($list_reembolso->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron reembolsos registrados'
        );
      } else {
        $reembolsos_lista_general = array();
        foreach ($list_reembolso as $vremb) {
					date_default_timezone_set($vremb->zona_horaria);
					$folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).(!is_null($vremb->post_folio_reem) ? '-'.$vremb->post_folio_reem : '');

					$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("sos_personas AS people", "emp.persona", "=", "people.id")
					->where("reem_main.token_reem",$vremb->token_reem)
          ->select("people.abrev_nombre")
          ->first();
          $name_emisor = $selectNameEmpEmi->abrev_nombre;

					$queryPersEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
					->where("reem_main.token_reem",$vremb->token_reem)
          ->select("catAcree.acr_titular")
          ->first();
          $nombreEmiPers = $queryPersEmi->acr_titular ? $JwtAuth->desencriptar($queryPersEmi->acr_titular) : '';

					$soli_r_prov = DB::table("terc_reembolso_solicitud AS reem_soli")
					->join("terc_reembolso_main AS rmain", "reem_soli.reembolso_main", "=", "rmain.id")
					->join("eegr_catalogo_proveedores AS cprov", "reem_soli.proveedor", "=", "cprov.id")
					->join("sos_personas AS prov", "cprov.proveedor", "=", "prov.id")
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->where("rmain.token_reem", $vremb->token_reem)
					->select('cprov.folio','cprov.post_folio','cprov.token_cat_proveedores','prov.nombre_extendido','prov.nombre_com','prov.rfc_generico', 'prov.rfc', 'prov.tax_id')
					->first();

					$prov_tkn = $soli_r_prov ? $soli_r_prov->token_cat_proveedores : "";
					$prov_folio = $soli_r_prov ? 'PRV-'.$JwtAuth->generarFolio($soli_r_prov->folio).(!is_null($soli_r_prov->post_folio) ? '-'.$soli_r_prov->post_folio : '') : '';
					$prov_name = $soli_r_prov ? $JwtAuth->desencriptar($soli_r_prov->nombre_extendido) : "";
          $prov_nombre_comercial = $soli_r_prov && !is_null($soli_r_prov->nombre_com) ? $JwtAuth->desencriptar($soli_r_prov->nombre_com) : "";
					$prov_rfc_generico = $soli_r_prov ? $soli_r_prov->rfc_generico : "";
					$prov_rfc = $soli_r_prov && !is_null($soli_r_prov->rfc) ? $JwtAuth->desencriptar($soli_r_prov->rfc) : "";
					$prov_taxid = $soli_r_prov && !is_null($soli_r_prov->tax_id) ? $JwtAuth->desencriptar($soli_r_prov->tax_id) : "";

          $moneda_entrante_string = $vremb->moneda_entrante;
					$moneda_entrante_string_min = $vremb->moneda_entrante;
					$moneda_entrante_decimales = $JwtAuth->getMonedaAPI($vremb->moneda_entrante);

					//importe
					$importe_total = $vremb->importe_entrante;
					$importe_total_conversion = $vremb->importe_entrante * $vremb->tipo_cambio;
					if (($vremb->autorizacion_vh == "A" || $vremb->autorizacion_vh == "N") && $vremb->autorizacion_egr == "A" && $vremb->terminado == TRUE) {
						$total_reembolsado = $vremb->importe_entrante;
						$total_reembolsado_conversion = $vremb->importe_entrante * $vremb->tipo_cambio;
					}

					$importe_requ_info_entr = "$".number_format($vremb->importe_entrante,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min;
					$importe_requ_info_sali = "$".number_format($vremb->importe_entrante * $vremb->tipo_cambio,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min;

					$autorizacion_vh = null;
					if ($vremb->autorizacion_vh == "A" || $vremb->autorizacion_vh == "N") $autorizacion_vh = true;
					if ($vremb->autorizacion_vh == "D") $autorizacion_vh = false;
					if ($vremb->autorizacion_vh != NULL) $autorizacion_vh = $vremb->autorizacion_vh;

					$select_list_auth_vh = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_vh,r_auth.comentarios FROM terc_reembolso_autorizacion_vh AS r_auth JOIN terc_reembolso_main AS r_main 
            JOIN terc_reembolso_solicitud AS s_soli WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?", 
            [$vremb->token_reem, $vremb->token_solicitud_reem]);

					$max_auth_vh = null;
					$fecha_registro_auth_vh = "";
					$hora_registro_auth_vh = "";
					$comments_auth_vh = "";

					if ($autorizacion_vh != null && $autorizacion_vh != "N" && count($select_list_auth_vh) > 0) {
						if (end($select_list_auth_vh)->autorizacion_vh == "A") $max_auth_vh = true;
						if (end($select_list_auth_vh)->autorizacion_vh == "D") $max_auth_vh = false;
						$fecha_registro_auth_vh = gmdate('Y-m-d H:i:s', end($select_list_auth_vh)->fecha_registro);
						$hora_registro_auth_vh = date('H:i:s', end($select_list_auth_vh)->fecha_registro);
						$comments_auth_vh = $JwtAuth->desencriptar(end($select_list_auth_vh)->comentarios);
					}

					switch ($vremb->autorizacion_egr) {
            case 'A':
              $autorizacion_egr = true;
              break;
            case 'D':
              $autorizacion_egr = false;
              break;
            default:
              $autorizacion_egr = null;
              break;
          }

					$select_list_auth_egr = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_egr,r_auth.comentarios FROM terc_reembolso_autorizacion_egr AS r_auth JOIN terc_reembolso_main AS r_main 
            JOIN terc_reembolso_solicitud AS s_soli WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?", 
            [$vremb->token_reem, $vremb->token_solicitud_reem]);

					$max_auth_egr = null;
					$fecha_registro_auth_egr = "";
					$hora_registro_auth_egr = "";
					$comments_auth_egr = "";
					$auth_egr_list_array = array();

					if (count($select_list_auth_egr) > 0) {
						foreach ($select_list_auth_egr as $l_auth) {
							$row_auth_vh = array(
								"autorizacion_egr" => $l_auth->autorizacion_egr,
								"registro_auth_egr" => date('d-m-Y - H:i:s', $l_auth->fecha_registro),
								"comentarios" => !is_null($l_auth->comentarios) ? $JwtAuth->desencriptar($l_auth->comentarios) : ''
							);
							$auth_egr_list_array[] = $row_auth_vh;
						}
						if (end($select_list_auth_egr)->autorizacion_egr == "A") $max_auth_egr = true;
						if (end($select_list_auth_egr)->autorizacion_egr == "D") $max_auth_egr = false;
						$fecha_registro_auth_egr = gmdate('Y-m-d H:i:s', end($select_list_auth_egr)->fecha_registro);
						$hora_registro_auth_egr = date('H:i:s', end($select_list_auth_egr)->fecha_registro);
						$comments_auth_egr = !is_null(end($select_list_auth_egr)->comentarios) ? $JwtAuth->desencriptar(end($select_list_auth_egr)->comentarios) : '';
					}

          $terminado = $vremb->terminado ? true : false;

					$fecha_respuesta_autorizacion = gmdate('Y-m-d H:i:s', $vremb->tiempo_respuesta_autorizacion);
					$time_respuesta_autorizacion = "";
					if ($vremb->tiempo_respuesta_autorizacion > time()) {
						$time_inicial_autorizacion = $vremb->tiempo_respuesta_autorizacion - time();
						$days_autorizacion = floor($time_inicial_autorizacion / (60 * 60 * 24));
						$time_inicial_autorizacion %= (60 * 60 * 24);
						$hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
						$time_inicial_autorizacion %= (60 * 60);
						$min_autorizacion = floor($time_inicial_autorizacion / 60);
						$time_inicial_autorizacion %= 60;
						$sec_autorizacion = $time_inicial_autorizacion;
						$time_respuesta_autorizacion = $days_autorizacion . " días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion"; // 
					} else {
						$time_respuesta_autorizacion = "tiempo de respuesta terminado";
					}

					$queryCFDIDataXMLReem = DB::table("cfdi_comprobantes_fiscales AS cfd")
					->join("cfdi_vinculacion_reembolsos AS vinc_reem", "cfd.id", "=", "vinc_reem.comprobante_fiscal")
					->join("terc_reembolso_main AS main", "vinc_reem.reembolso_vinculado_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "vinc_reem.reembolso_vinculado_soli", "=", "reem_soli.id")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->select("cfd.cfdi_comprobante_moneda","cfd.cfdi_comprobante_total","cfd.cfdi_comprobante_tipo_de_comprobante","cfd.cfdi_complementoUUID")
          ->first();
            
          $reem_cfdi_comprobante_total = $queryCFDIDataXMLReem ? "$".number_format($queryCFDIDataXMLReem->cfdi_comprobante_total, $JwtAuth->getMonedaAPI($queryCFDIDataXMLReem->cfdi_comprobante_moneda), '.', ',')." ".$queryCFDIDataXMLReem->cfdi_comprobante_moneda : "";
          $reem_cfdi_comprobante_tipo_de_comprobante = $queryCFDIDataXMLReem ? $queryCFDIDataXMLReem->cfdi_comprobante_tipo_de_comprobante : "";
          $reem_cfdi_complementoUUID = $queryCFDIDataXMLReem ? $queryCFDIDataXMLReem->cfdi_complementoUUID : "";

					$xmlFacturaContent = array();
					$xmlFacturaDesglose = null;
					$queryFacturaXMLReem = DB::table("sos_documentos AS docs")
					->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
					->where("docs.status_documento", TRUE)
					->where("docs.tipo_documento", "xml")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->get();

					foreach ($queryFacturaXMLReem as $xDoc) {
						$token_documento = $xDoc->token_documento;
						$name_documento = $JwtAuth->desencriptar($xDoc->nombre_documento);
						$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $xDoc->token_documento;
						$rowXML = array(
							"token_documento" => $token_documento,
							"ext_doc" => $xDoc->tipo_documento,
							"name_documento" => $name_documento,
							"url" => $ruta_alterna
						);
						$xmlFacturaContent[] = $rowXML;
					}

					$pdfFacturaContent = array();
					$queryFacturaPDFReem = DB::table("sos_documentos AS docs")
					->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
					->where("docs.status_documento", TRUE)
					->where("docs.tipo_documento", "pdf")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)->get();

					foreach ($queryFacturaPDFReem as $pdfDoc) {
						$token_documento = $pdfDoc->token_documento;
						$name_documento = $JwtAuth->desencriptar($pdfDoc->nombre_documento);
						$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $pdfDoc->token_documento;
						$rowDet = array(
							"token_documento" => $token_documento,
							"ext_doc" => $pdfDoc->tipo_documento,
							"name_documento" => $name_documento,
							"url" => $ruta_alterna
						);
						$pdfFacturaContent[] = $rowDet;
					}

					$docsAnexosArray = array();
					$selectAnexosReem = DB::table("sos_documentos AS docs")
					->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
					->where("docs.status_documento", TRUE)
					->where("docs.tipo_documento", "an")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->get();

					foreach ($selectAnexosReem as $vDoc) {
						$token_docs = $vDoc->token_documento;
						$tipo_doc = $vDoc->tipo_documento;
						$ext_doc = $vDoc->extension_documento;
						$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
						$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $vDoc->token_documento;
            
						$rowDet = array(
							"token_docs" => $token_docs,
							"name_documento" => $name_documento,
							"ext_doc" => $tipo_doc,
							"url" => $ruta_alterna
						);
						$docsAnexosArray[] = $rowDet;
					}

					$buyCompras = DB::table("eegr_compras AS buy")
					->join("terc_reembolso_main AS main", "buy.reembolso_vinculado_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "buy.reembolso_vinculado_soli", "=", "reem_soli.id")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->count();

          $listComprasParaVincular = array();
          $uuid_coincidencias = 0;
					if (count($xmlFacturaContent) == 1 && count($pdfFacturaContent) == 1) {
						$buyComprasParaVincular = DB::table("eegr_compras AS buy")
						->join("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
						->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")
						->join("fnzs_pagos_orden AS ordp", "buy.id", "=", "ordp.factura_compra")
	          ->whereNull("buy.reembolso_vinculado_main")
	          ->whereNull("buy.reembolso_vinculado_soli")
	          ->where("cfdi.cfdi_complementoUUID",$reem_cfdi_complementoUUID)
						->get();

	          $uuid_coincidencias = count($buyComprasParaVincular);
					} else {
						$buyComprasParaVincular = DB::table("eegr_compras AS buy")
						->join("fnzs_pagos_orden AS ordp", "buy.id", "=", "ordp.factura_compra")
	          ->whereNull("buy.reembolso_vinculado_main")
	          ->whereNull("buy.reembolso_vinculado_soli")
						->get();

          	$uuid_coincidencias = count($buyComprasParaVincular);
					}

          $listComprasVinculadas = array();
					$buyComprasVinculadas = DB::table("eegr_compras AS buy")
          ->join("terc_reembolso_main AS main", "buy.reembolso_vinculado_main", "=", "main.id")
          ->join("terc_reembolso_solicitud AS reem_soli", "buy.reembolso_vinculado_soli", "=", "reem_soli.id")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->get();

          foreach ($buyComprasVinculadas as $vCPV) {
            $cfdiCompras = DB::table("eegr_compras AS buy")
            ->join("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
						->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")
            ->where("buy.token_compras",$vCPV->token_compras)
            ->select('cfdi.cfdi_comprobante_total','cfdi.cfdi_comprobante_moneda','cfdi.cfdi_comprobante_tipo_de_comprobante','cfdi.cfdi_complementoUUID')
            ->first();

            if ($cfdiCompras) {
              $cfdi_comprobante_total = "$".number_format($cfdiCompras->cfdi_comprobante_total, $JwtAuth->getMonedaAPI($cfdiCompras->cfdi_comprobante_moneda), '.', ',')." ".$cfdiCompras->cfdi_comprobante_moneda;
            } else {
              $compra_importe = 0;
              $queryDEtailsTotal = DB::table("eegr_compras AS buy")
              ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
              ->where("buy.token_compras",$vCPV->token_compras)
              ->get();
            
              foreach ($queryDEtailsTotal as $vDet) {
                $resultante = 0;
                $det_subtotal = ($vDet->precio_unitario * $vDet->cantidad) - $vDet->descuento;
                $resultante = $det_subtotal + $vDet->traslados_total - $vDet->retenciones_total;
                $compra_importe += $resultante;
              }
              $cfdi_comprobante_total = "$".number_format($compra_importe, $JwtAuth->getMonedaAPI($vCPV->moneda), '.', ',')." ".$vCPV->moneda;
            }
            
            $cfdi_comprobante_tipo_de_comprobante = $cfdiCompras ? $cfdiCompras->cfdi_comprobante_tipo_de_comprobante : '---';
            $cfdi_complementoUUID = $cfdiCompras ? $cfdiCompras->cfdi_complementoUUID : '---';
					  $queryProveedor = DB::table("sos_personas AS people")
            ->join("eegr_catalogo_proveedores AS catprov", "people.id", "catprov.proveedor")
            ->join("eegr_compras AS buy", "catprov.id", "buy.proveedor")
					  ->where("buy.token_compras",$vCPV->token_compras)
					  ->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido')
					  ->first();
					  $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
            $proveedor_folio = $queryProveedor ? ('PRV-'.$JwtAuth->generarFolio($queryProveedor->folio).(!is_null($queryProveedor->post_folio) ? '-'.$queryProveedor->post_folio : '')) : "";
					  $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
            $rowCPV = array(
              "token_compras" => $vCPV->token_compras,
              "folio_compra" => "COMP-".$JwtAuth->generarFolio($vCPV->folio_compra).($vCPV->post_folio != NULL ? '-'.$vCPV->post_folio : ''),
              "cfdi_comprobante_total" => $cfdi_comprobante_total,
              "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
              "cfdi_complementoUUID" => $cfdi_complementoUUID,
              "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vCPV->fecha_contabilizacion),
              "fecha_contabilizacion_html" => date('Y-m-d', $vCPV->fecha_contabilizacion),
              "proveedor_token" => $proveedor_token,
              "proveedor_folio" => $proveedor_folio,
              "proveedor_name" => $proveedor_name,
            );
            $listComprasVinculadas[] = $rowCPV;
          }

          $autorizacion_vh = null;

					$row_main = array(
						"token_reem" => $vremb->token_reem,
            "folio_reem" => $folio_reem,
						"token_solicitud_reem" => $vremb->token_solicitud_reem,
						"folio_solicitud" => $JwtAuth->generarFolio($vremb->folio_solicitud),
            
						"comision_token" => $vremb->token_comision_main,
            "comision_folio" => "COMI-".$JwtAuth->generarFolio($vremb->folio_comision),
            "comision_proyecto" => $JwtAuth->desencriptar($vremb->comision_proyecto),
						
						"nombreEmiPers" => $nombreEmiPers,
            "company" => $name_emisor,

            "fecha_solicitud" => gmdate('Y-m-d H:i:s', $vremb->fecha_solicitud),
            "fecha_gasto" => gmdate('Y-m-d H:i:s', $vremb->fecha_gasto),
            "fecha_gasto_html" => $JwtAuth->convierteEpocFechaHtml($vremb->zona_horaria,$vremb->fecha_gasto),
            "ticket_gasto" => $JwtAuth->desencriptar($vremb->ticket_gasto),
            //proveedor
						  "pagado_a" => $vremb->pagado_a,
						  "prov_tkn" => $vremb->pagado_a == 'prov' ? $prov_tkn : '',
						  "prov_folio" => $vremb->pagado_a == 'prov' ? $prov_folio : '',
						  "prov_name" => $vremb->pagado_a == 'prov' ? $prov_name : '',
						  "prov_nombre_comercial" => $vremb->pagado_a == 'prov' ? $prov_nombre_comercial : '',
						  "prov_rfc_generico" => $vremb->pagado_a == 'prov' ? $prov_rfc_generico : '',
						  "prov_rfc" => $vremb->pagado_a == 'prov' ? $prov_rfc : '',
						  "prov_taxid" => $vremb->pagado_a == 'prov' ? $prov_taxid : '',
						//forma de pago
              "fpago_clave" => $vremb->forma_pago,
              "fpago_forma" => $JwtAuth->getFormasPagoAPI($vremb->forma_pago),
						//importe
							"moneda_code" => $moneda_entrante_string,
							"moneda_decimales" => $moneda_entrante_decimales,
							"importe_requerido" => floatval($vremb->importe_entrante),
						
							"importe_requ_info_entr" => floatval($vremb->importe_entrante),
							"importe_requ_info_entr_format" => "$".number_format($vremb->importe_entrante,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min,

							"importe_requ_info_sali" => floatval($vremb->importe_entrante * $vremb->tipo_cambio),
							"importe_requ_info_sali_format" => "$".number_format($vremb->importe_entrante * $vremb->tipo_cambio,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min,
							"tipo_cambio_soli" => $vremb->tipo_cambio,
							"tipo_cambio_soli_format" => "$".number_format($vremb->tipo_cambio,$moneda_entrante_decimales,'.',',')." $moneda_entrante_string_min",
						//observaciones
							"observaciones" => $JwtAuth->desencriptar($vremb->motivo_reem),
							"autorizacion_vh" => $autorizacion_vh,
							"max_auth_vh" => $max_auth_vh,
							"comments_auth_vh" => $comments_auth_vh,
							"comments_auth_vh_back" => $comments_auth_vh,
							"fecha_registro_auth_vh" => $fecha_registro_auth_vh,
							"hora_registro_auth_vh" => $hora_registro_auth_vh,
							"autorizacion_egr" => $autorizacion_egr,
							"max_auth_egr" => $max_auth_egr,
							"comments_auth_egr" => $comments_auth_egr,
							"comments_auth_egr_write" => "",
							"fecha_registro_auth_egr" => $fecha_registro_auth_egr,
							"hora_registro_auth_egr" => $hora_registro_auth_egr,
							"auth_egr_list_array" => $auth_egr_list_array,
							"terminado" => $terminado,
							"fecha_respuesta_autorizacion" => $fecha_respuesta_autorizacion,
							"time_respuesta_autorizacion" => $time_respuesta_autorizacion,
							"reem_cfdi_comprobante_total" => $reem_cfdi_comprobante_total,
							"reem_cfdi_comprobante_tipo_de_comprobante" => $reem_cfdi_comprobante_tipo_de_comprobante,
							"reem_cfdi_complementoUUID" => $reem_cfdi_complementoUUID,
							"xmlFacturaContent" => $xmlFacturaContent,
							"pdfFacturaContent" => $pdfFacturaContent,
							"anexos" => $docsAnexosArray,
							"viewModalDocumentosAdjuntos" => false,
							"viewModalCompraVinculacion" => false,
							"compra_vinculada" => $buyCompras > 0 ? true : false,
							"compras_vincular" => [],
              "uuid_coincidencias" => $uuid_coincidencias,
							"viewModalCompraSoliCancelaVinculacion" => false,
							"soli_cancela_vinc_comentarios" => "",
              "compras_vinculadas" => $listComprasVinculadas,
              "compras_vinculadas_total" => "vinculado a ".count($listComprasVinculadas)." compras",
							"viewModalCompraAuth" => false,
							"viewModalListadoDeAutorizaciones" => false,
					);
          $reembolsos_lista_general[] = $row_main;
        }

        $dataMensaje = array(
					"status" => "success", 
					"code" => 200, 
					"reem_lista_general" => $reembolsos_lista_general,
					/*"reem_lista_general_by_reembolso" => collect($reembolsos_lista_general)
						->groupBy('folio_reem')
						->map(function($items,$key) {
							return [
								'token_reem' => $items->first()['token_reem'],
								'folio_reem' => $key,
								'moneda_code' => $items->first()['moneda_code'],
								'importe_requ_info_entr' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_entr'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'tipo_cambio_soli' => $items->first()['tipo_cambio_soli_format'],

								'importe_requ_info_sali' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_sali'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'partidas' => $items->values()
							];
						})
						->values(),

					"reem_lista_general_by_comision" => collect($reembolsos_lista_general)
						->groupBy('comision_folio')
						->map(function($items,$key) {
							return [
								'comision_token' => $items->first()['comision_token'],
                'comision_folio' => $key,
                'comision_proyecto' => $items->first()['comision_proyecto'],

								'moneda_code' => $items->first()['moneda_code'],
								'importe_requ_info_entr' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_entr'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'tipo_cambio_soli' => $items->first()['tipo_cambio_soli_format'],

								'importe_requ_info_sali' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_sali'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'partidas' => $items->values()
							];
						})
						->values(),
					"reem_lista_general_by_proveedor" => collect($reembolsos_lista_general)
						->groupBy('prov_folio')
						->filter(function($items){
								return $items->first()['pagado_a'] === "prov";
							}
						)
						->map(function($items,$key) {
							return [
								'prov_tkn' => $items->first()['prov_tkn'],
                'prov_folio' => $key,
                'prov_name' => $items->first()['prov_name'],
                'prov_nombre_comercial' => $items->first()['prov_nombre_comercial'],
                'prov_rfc_generico' => $items->first()['prov_rfc_generico'],
                'prov_rfc' => $items->first()['prov_rfc'],
                'prov_taxid' => $items->first()['prov_taxid'],

								'moneda_code' => $items->first()['moneda_code'],
								'importe_requ_info_entr' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_entr'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'tipo_cambio_soli' => $items->first()['tipo_cambio_soli_format'],

								'importe_requ_info_sali' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_sali'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'partidas' => $items->values()
							];
						})
						->values(),
					"reem_lista_general_auth_egresos" => collect($reembolsos_lista_general)->filter(fn($reem) => $reem["autorizacion_egr"] && $reem["max_auth_egr"])->values(),
					"reem_lista_general_no_auth_egresos" => collect($reembolsos_lista_general)->filter(fn($reem) => !$reem["autorizacion_egr"] || !$reem["max_auth_egr"])->values(),
					"reem_lista_general_no_vinc_compras" => collect($reembolsos_lista_general)->filter(fn($reem) => count($reem["compras_vinculadas"]) === 0)->values(),
					"reem_lista_general_vinc_compras" => collect($reembolsos_lista_general)->filter(fn($reem) => count($reem["compras_vinculadas"]) > 0)->values(),*/
				);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function reembolso_lista_general_por_raiz(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$reembolsos_lista_general = array();

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

				if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
					$list_reembolso = DB::table("terc_reembolso_main AS reem_main")
						->join("sos_reembolsos_comisiones_rel AS reem_comi", "reem_main.id", "=", "reem_comi.reembolso_main")
						->join("terc_comisiones_main AS comi_soli", "reem_comi.comision", "=", "comi_soli.id")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.last_version", "=", "reem_soli.id")
						->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
						->where("reem_main.user_receptor_egr", "!=", NULL)
						->where([
						    "reem_soli.status_activacion" => TRUE,
							"reem_main.status_reem" => TRUE,
							"reem_main.borrador_reem" => FALSE,
							"emp.empresa_token" => $usuario->empresa_token,
						])
						->orderBy('reem_main.folio_reem', 'DESC')->get();
				} else {
					$list_reembolso = DB::table("terc_reembolso_main AS reem_main")
						->join("sos_reembolsos_comisiones_rel AS reem_comi", "reem_main.id", "=", "reem_comi.reembolso_main")
						->join("terc_comisiones_main AS comi_soli", "reem_comi.comision", "=", "comi_soli.id")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.last_version", "=", "reem_soli.id")
						->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
						->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
						->where([
						    "reem_soli.status_activacion" => TRUE,
							"reem_main.status_reem" => TRUE,
							"reem_main.borrador_reem" => FALSE,
							"emp.empresa_token" => $usuario->empresa_token,
							"users.usuario_token" => $usuario->user_token
						])
						->orderBy('reem_main.folio_reem', 'DESC')->get();
				}

				foreach ($list_reembolso as $vremb) {
					date_default_timezone_set($vremb->zona_horaria);

					$fecha_solicitud = $vremb->fecha_sistema;
					$date_solicitud = gmdate('Y-m-d H:i:s', $vremb->fecha_sistema);

					$fecha_respuesta_autorizacion = gmdate('Y-m-d H:i:s', $vremb->tiempo_respuesta_autorizacion);
					$time_inicial_autorizacion = $vremb->tiempo_respuesta_autorizacion - time();
					$days_autorizacion = floor($time_inicial_autorizacion / (60 * 60 * 24));
					$time_inicial_autorizacion %= (60 * 60 * 24);
					$hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
					$time_inicial_autorizacion %= (60 * 60);
					$min_autorizacion = floor($time_inicial_autorizacion / 60);
					$time_inicial_autorizacion %= 60;
					$sec_autorizacion = $time_inicial_autorizacion;
					$time_respuesta_autorizacion = $days_autorizacion . " días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion"; //

					$iva_final = 0;
					$importe_final = 0;

					if ($vremb->post_folio_reem == NULL) {
						$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem);
					} else {
						$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem) . '-' . $vremb->post_folio_reem;
					}

					$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("sos_personas AS people", "emp.persona", "=", "people.id")
						->where(["reem_main.token_reem" => $vremb->token_reem])->get();

					foreach ($selectNameEmpEmi as $vEmisor) {
						$name_emisor = $vEmisor->abrev_nombre;
						$rfc_gen_emi = $vEmisor->rfc_generico;
						$rfc_emp_emi = $vEmisor->rfc != NULL ? $JwtAuth->desencriptar($vEmisor->rfc) : "---";
						$taxid_emp_emi = $vEmisor->tax_id != NULL ? $JwtAuth->desencriptar($vEmisor->tax_id) : "---";
					}

					$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
					->where(["reem_main.token_reem" => $vremb->token_reem])->get();

					foreach ($selectPersEmpEmi as $vPemi) {
						$nombreEmiPers = $vPemi->acr_titular ? $JwtAuth->desencriptar($vPemi->acr_titular) : '';
					}

					$soli_reem = DB::table("terc_reembolso_main AS reem_main")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
						->where(["reem_main.token_reem" => $vremb->token_reem])
						->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

					$reem_total = 0;
					$total_tipo_cambio = 0;
					$moneda_entrante_string = "";
					$moneda_entrante_decimales = 0;
					$total_reem_saliente = 0;

					$reem_soli_all = 0;
					$reem_soli_all_auth = 0;
					$reem_soli_auth_style = "";
					foreach ($soli_reem as $vSoliR) {
						$reem_total = $reem_total + $vSoliR->importe_entrante;

						$moneda_entrante_string = $vSoliR->moneda_entrante;
						$moneda_entrante_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);

						$total_tipo_cambio = $vSoliR->tipo_cambio;
						$resultante = $vSoliR->importe_entrante * $vSoliR->tipo_cambio;
						$total_reem_saliente = $total_reem_saliente + $resultante;

						++$reem_soli_all;
						if ($vSoliR->autorizacion_egr == "A") {
							++$reem_soli_all_auth;
						}
					}

					if ($reem_soli_all_auth != 0) {
						//$reem_soli_auth_style = 100 * ($reem_soli_all/$reem_soli_all_auth);
						$reem_soli_auth_style = (100 * $reem_soli_all_auth) / $reem_soli_all;
					} else {
						$reem_soli_auth_style = 0;
					}

					$reem_evd = DB::table("sos_documentos AS docs")
						->join("terc_reembolso_main AS reem_main", "docs.reembolso_main", "=", "reem_main.id")
						->where(["reem_main.token_reem" => $vremb->token_reem, "docs.status_documento" => TRUE])->get();

					$row_main = array(
						"token_reem" => $vremb->token_reem,
						"folio_reem" => $folio_reem,

						"fecha_solicitud" => $fecha_solicitud,
						"date_solicitud" => $date_solicitud,
						"fecha_respuesta_autorizacion_vhegr" => $fecha_respuesta_autorizacion,
						"time_respuesta_autorizacion_vhegr" => $time_respuesta_autorizacion,

						"name_emisor" => $name_emisor,
						"rfc_gen_emi" => $rfc_gen_emi,
						"rfc_emp_emi" => $rfc_emp_emi,
						"taxid_emp_emi" => $taxid_emp_emi,
						"nombreEmiPers" => $nombreEmiPers,

						"reem_soli_all" => $reem_soli_all,
						"reem_soli_all_auth" => $reem_soli_all_auth,
						"reem_soli_auth_style" => $reem_soli_auth_style . "%",
						"importe_total" => "$" . number_format($reem_total, 2, '.', ','),
						"moneda_entrante" => $moneda_entrante_string,
						"moneda_entrante_decimales" => $moneda_entrante_decimales,
						"total_tipo_cambio" => "$" . $total_tipo_cambio,
						"total_reem_saliente" => "$" . number_format($total_reem_saliente, $moneda_entrante_decimales, '.', ','),
						"comision_folio" => "COMI-" . $JwtAuth->generarFolio($vremb->folio_comision),
						"comision_proyecto" => $JwtAuth->desencriptar($vremb->comision_proyecto),
						"total_evd" => count($reem_evd),
					);

					$reembolsos_lista_general[] = $row_main;
				}
				$dataMensaje = array("status" => "success", "code" => 200, "reem_lista_general" => $reembolsos_lista_general);
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La informacion que intenta registrar no es valida'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function reembolso_lista_pendientes(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$reembolsos_lista_pendientes = array();

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

				if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
					$list_reembolso = DB::table("terc_reembolso_solicitud AS reem_soli")
					->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
					->join("sos_reembolsos_comisiones_rel AS reem_comi", "reem_main.id", "=", "reem_comi.reembolso_main")
					->join("terc_comisiones_main AS comi_soli", "reem_comi.comision", "=", "comi_soli.id")
					->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
					->where(function($egr) {
                      $egr->where("reem_soli.autorizacion_egr", "!=","A")
                      ->orWhereNull("reem_soli.autorizacion_egr");
                    })
					->where("reem_soli.status_activacion",TRUE)
					->where("reem_main.user_receptor_egr", "!=", NULL)
					->where("reem_main.status_reem",TRUE)
					->where("reem_main.borrador_reem",FALSE)
					->where("emp.empresa_token",$usuario->empresa_token)
					->orderBy('reem_main.folio_reem', 'DESC')->get();
				} else {
					$list_reembolso = DB::table("terc_reembolso_solicitud AS reem_soli")
					->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
					->join("sos_reembolsos_comisiones_rel AS reem_comi", "reem_main.id", "=", "reem_comi.reembolso_main")
					->join("terc_comisiones_main AS comi_soli", "reem_comi.comision", "=", "comi_soli.id")
					->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
					->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
					->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
					->where(function($egr) {
                      $egr->where("reem_soli.autorizacion_egr", "!=","A")
                      ->orWhereNull("reem_soli.autorizacion_egr");
                    })
					->where("reem_soli.status_activacion",TRUE)
					->where("reem_main.status_reem",TRUE)
					->where("reem_main.borrador_reem",FALSE)
					->where("emp.empresa_token",$usuario->empresa_token)
					->where("users.usuario_token",$usuario->user_token)
					->orderBy('reem_main.folio_reem', 'DESC')->get();
				}
        
				foreach ($list_reembolso as $vremb) {
					date_default_timezone_set($vremb->zona_horaria);
					$folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).(!is_null($vremb->post_folio_reem) ? '-'.$vremb->post_folio_reem : '');

					$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("sos_personas AS people", "emp.persona", "=", "people.id")
					->where("reem_main.token_reem",$vremb->token_reem)
          ->select("people.abrev_nombre")
          ->first();
          $name_emisor = $selectNameEmpEmi->abrev_nombre;

					$queryPersEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
					->where("reem_main.token_reem",$vremb->token_reem)
          ->select("catAcree.acr_titular")
          ->first();
          $nombreEmiPers = $queryPersEmi->acr_titular ? $JwtAuth->desencriptar($queryPersEmi->acr_titular) : '';

					$soli_r_prov = DB::table("terc_reembolso_solicitud AS reem_soli")
					->join("terc_reembolso_main AS rmain", "reem_soli.reembolso_main", "=", "rmain.id")
					->join("eegr_catalogo_proveedores AS cprov", "reem_soli.proveedor", "=", "cprov.id")
					->join("sos_personas AS prov", "cprov.proveedor", "=", "prov.id")
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->where("rmain.token_reem", $vremb->token_reem)
					->select('cprov.folio','cprov.post_folio','cprov.token_cat_proveedores','prov.nombre_extendido','prov.nombre_com','prov.rfc_generico', 'prov.rfc', 'prov.tax_id')
					->first();

					$prov_tkn = $soli_r_prov ? $soli_r_prov->token_cat_proveedores : "";
					$prov_folio = 'PRV-'.$JwtAuth->generarFolio($soli_r_prov->folio).(!is_null($soli_r_prov->post_folio) ? '-'.$soli_r_prov->post_folio : '');
					$prov_name = $soli_r_prov ? $JwtAuth->desencriptar($soli_r_prov->nombre_extendido) : "";
          $prov_nombre_comercial = $soli_r_prov && !is_null($soli_r_prov->nombre_com) ? $JwtAuth->desencriptar($soli_r_prov->nombre_com) : "";
					$prov_rfc_generico = $soli_r_prov ? $soli_r_prov->rfc_generico : "";
					$prov_rfc = $soli_r_prov && !is_null($soli_r_prov->rfc) ? $JwtAuth->desencriptar($soli_r_prov->rfc) : "";
					$prov_taxid = $soli_r_prov && !is_null($soli_r_prov->tax_id) ? $JwtAuth->desencriptar($soli_r_prov->tax_id) : "";

          $moneda_entrante_string = $vremb->moneda_entrante;
					$moneda_entrante_string_min = $vremb->moneda_entrante;
					$moneda_entrante_decimales = $JwtAuth->getMonedaAPI($vremb->moneda_entrante);

					//importe
					$importe_total = $vremb->importe_entrante;
					$importe_total_conversion = $vremb->importe_entrante * $vremb->tipo_cambio;
					if (($vremb->autorizacion_vh == "A" || $vremb->autorizacion_vh == "N") && $vremb->autorizacion_egr == "A" && $vremb->terminado == TRUE) {
						$total_reembolsado = $vremb->importe_entrante;
						$total_reembolsado_conversion = $vremb->importe_entrante * $vremb->tipo_cambio;
					}

					//$importe_requ_info_entr = "$".number_format($vremb->importe_entrante,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min;
					//$importe_requ_info_sali = "$".number_format($vremb->importe_entrante * $vremb->tipo_cambio,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min;

					$autorizacion_vh = null;
					if ($vremb->autorizacion_vh == "A" || $vremb->autorizacion_vh == "N") $autorizacion_vh = true;
					if ($vremb->autorizacion_vh == "D") $autorizacion_vh = false;
					if ($vremb->autorizacion_vh != NULL) $autorizacion_vh = $vremb->autorizacion_vh;

					$select_list_auth_vh = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_vh,r_auth.comentarios FROM terc_reembolso_autorizacion_vh AS r_auth JOIN terc_reembolso_main AS r_main 
            JOIN terc_reembolso_solicitud AS s_soli WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?", 
            [$vremb->token_reem, $vremb->token_solicitud_reem]);

					$max_auth_vh = null;
					$fecha_registro_auth_vh = "";
					$hora_registro_auth_vh = "";
					$comments_auth_vh = "";

					if ($autorizacion_vh != null && $autorizacion_vh != "N" && count($select_list_auth_vh) > 0) {
						if (end($select_list_auth_vh)->autorizacion_vh == "A") $max_auth_vh = true;
						if (end($select_list_auth_vh)->autorizacion_vh == "D") $max_auth_vh = false;
						$fecha_registro_auth_vh = gmdate('Y-m-d H:i:s', end($select_list_auth_vh)->fecha_registro);
						$hora_registro_auth_vh = date('H:i:s', end($select_list_auth_vh)->fecha_registro);
						$comments_auth_vh = $JwtAuth->desencriptar(end($select_list_auth_vh)->comentarios);
					}

					switch ($vremb->autorizacion_egr) {
            case 'A':
              $autorizacion_egr = true;
              break;
            case 'D':
              $autorizacion_egr = false;
              break;
            default:
              $autorizacion_egr = null;
              break;
          }

					$select_list_auth_egr = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_egr,r_auth.comentarios FROM terc_reembolso_autorizacion_egr AS r_auth JOIN terc_reembolso_main AS r_main 
            JOIN terc_reembolso_solicitud AS s_soli WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?", 
            [$vremb->token_reem, $vremb->token_solicitud_reem]);

					$max_auth_egr = null;
					$fecha_registro_auth_egr = "";
					$hora_registro_auth_egr = "";
					$comments_auth_egr = "";
					$auth_egr_list_array = array();
					//echo count($select_list_auth_egr);
					//$select_list_auth_egr = DB::table('terc_reembolso_autorizacion_egr AS r_auth')
    			//->join('terc_reembolso_main AS r_main', 'r_auth.reembolso_main', '=', 'r_main.id')
    			//->join('terc_reembolso_solicitud AS s_soli', 'r_auth.reembolso_solicitud', '=', 's_soli.id')
    			//->where('r_main.token_reem', $vremb->token_reem)
    			//->where('s_soli.token_solicitud_reem', $vremb->token_solicitud_reem)
    			//->select('r_auth.fecha_registro', 'r_auth.autorizacion_egr', 'r_auth.comentarios')
    			//->orderBy('r_auth.fecha_registro', 'desc')
    			//->first();

					if (count($select_list_auth_egr) > 0) {
						foreach ($select_list_auth_egr as $l_auth) {
							$row_auth_vh = array(
								"autorizacion_egr" => $l_auth->autorizacion_egr,
								"registro_auth_egr" => date('d-m-Y - H:i:s', $l_auth->fecha_registro),
								"comentarios" => !is_null($l_auth->comentarios) ? $JwtAuth->desencriptar($l_auth->comentarios) : ''
							);
							$auth_egr_list_array[] = $row_auth_vh;
						}
						if (end($select_list_auth_egr)->autorizacion_egr == "A") $max_auth_egr = true;
						if (end($select_list_auth_egr)->autorizacion_egr == "D") $max_auth_egr = false;
						$fecha_registro_auth_egr = gmdate('Y-m-d H:i:s', end($select_list_auth_egr)->fecha_registro);
						$hora_registro_auth_egr = date('H:i:s', end($select_list_auth_egr)->fecha_registro);
						$comments_auth_egr = !is_null(end($select_list_auth_egr)->comentarios) ? $JwtAuth->desencriptar(end($select_list_auth_egr)->comentarios) : '';
					}

          $terminado = $vremb->terminado ? true : false;

					$fecha_respuesta_autorizacion = gmdate('Y-m-d H:i:s', $vremb->tiempo_respuesta_autorizacion);
					$time_respuesta_autorizacion = "";
					if ($vremb->tiempo_respuesta_autorizacion > time()) {
						$time_inicial_autorizacion = $vremb->tiempo_respuesta_autorizacion - time();
						$days_autorizacion = floor($time_inicial_autorizacion / (60 * 60 * 24));
						$time_inicial_autorizacion %= (60 * 60 * 24);
						$hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
						$time_inicial_autorizacion %= (60 * 60);
						$min_autorizacion = floor($time_inicial_autorizacion / 60);
						$time_inicial_autorizacion %= 60;
						$sec_autorizacion = $time_inicial_autorizacion;
						$time_respuesta_autorizacion = $days_autorizacion . " días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion"; // 
					} else {
						$time_respuesta_autorizacion = "tiempo de respuesta terminado";
					}

					$queryCFDIDataXMLReem = DB::table("cfdi_comprobantes_fiscales AS cfd")
					->join("cfdi_vinculacion_reembolsos AS vinc_reem", "cfd.id", "=", "vinc_reem.comprobante_fiscal")
					->join("terc_reembolso_main AS main", "vinc_reem.reembolso_vinculado_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "vinc_reem.reembolso_vinculado_soli", "=", "reem_soli.id")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->select("cfd.cfdi_comprobante_moneda","cfd.cfdi_comprobante_total","cfd.cfdi_comprobante_tipo_de_comprobante","cfd.cfdi_complementoUUID")
          ->first();
            
          $reem_cfdi_comprobante_total = $queryCFDIDataXMLReem ? "$".number_format($queryCFDIDataXMLReem->cfdi_comprobante_total, $JwtAuth->getMonedaAPI($queryCFDIDataXMLReem->cfdi_comprobante_moneda), '.', ',')." ".$queryCFDIDataXMLReem->cfdi_comprobante_moneda : "";
          $reem_cfdi_comprobante_tipo_de_comprobante = $queryCFDIDataXMLReem ? $queryCFDIDataXMLReem->cfdi_comprobante_tipo_de_comprobante : "";
          $reem_cfdi_complementoUUID = $queryCFDIDataXMLReem ? $queryCFDIDataXMLReem->cfdi_complementoUUID : "";

					$xmlFacturaContent = array();
					$xmlFacturaDesglose = null;
					$queryFacturaXMLReem = DB::table("sos_documentos AS docs")
					->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
					->where("docs.status_documento", TRUE)
					->where("docs.tipo_documento", "xml")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->get();

					foreach ($queryFacturaXMLReem as $xDoc) {
						$token_documento = $xDoc->token_documento;
						$name_documento = $JwtAuth->desencriptar($xDoc->nombre_documento);
						$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $xDoc->token_documento;
						$rowXML = array(
							"token_documento" => $token_documento,
							"ext_doc" => $xDoc->tipo_documento,
							"name_documento" => $name_documento,
							"url" => $ruta_alterna
						);
						$xmlFacturaContent[] = $rowXML;
						$filepath = $vremb->root_tkn . "/0010-reem/$folio_reem/" . $JwtAuth->generarFolio($vremb->folio_solicitud) . "/anexos";
						$rutaArchivo = storage_path("app/public/root/$filepath/$name_documento");
						$xmlFacturaDesglose = file_get_contents($rutaArchivo);
					}

					$pdfFacturaContent = array();
					$queryFacturaPDFReem = DB::table("sos_documentos AS docs")
					->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
					->where("docs.status_documento", TRUE)
					->where("docs.tipo_documento", "pdf")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)->get();

					foreach ($queryFacturaPDFReem as $pdfDoc) {
						$token_documento = $pdfDoc->token_documento;
						$name_documento = $JwtAuth->desencriptar($pdfDoc->nombre_documento);
						$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $pdfDoc->token_documento;
						$rowDet = array(
							"token_documento" => $token_documento,
							"ext_doc" => $pdfDoc->tipo_documento,
							"name_documento" => $name_documento,
							"url" => $ruta_alterna
						);
						$pdfFacturaContent[] = $rowDet;
					}

					$docsAnexosArray = array();
					$selectAnexosReem = DB::table("sos_documentos AS docs")
					->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
					->where("docs.status_documento", TRUE)
					->where("docs.tipo_documento", "an")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->get();

					foreach ($selectAnexosReem as $vDoc) {
						$token_docs = $vDoc->token_documento;
						$tipo_doc = $vDoc->tipo_documento;
						$ext_doc = $vDoc->extension_documento;
						$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
						$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $vDoc->token_documento;
            
						$rowDet = array(
							"token_docs" => $token_docs,
							"name_documento" => $name_documento,
							"ext_doc" => $tipo_doc,
							"url" => $ruta_alterna
						);
						$docsAnexosArray[] = $rowDet;
					}

					$buyCompras = DB::table("eegr_compras AS buy")
					->join("terc_reembolso_main AS main", "buy.reembolso_vinculado_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "buy.reembolso_vinculado_soli", "=", "reem_soli.id")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->count();

          $listComprasParaVincular = array();
          $uuid_coincidencias = 0;
					if (count($xmlFacturaContent) == 1 && count($pdfFacturaContent) == 1) {
						$buyComprasParaVincular = DB::table("eegr_compras AS buy")
						->join("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
						->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")
						->join("fnzs_pagos_orden AS ordp", "buy.id", "=", "ordp.factura_compra")
	          ->whereNull("buy.reembolso_vinculado_main")
	          ->whereNull("buy.reembolso_vinculado_soli")
	          ->where("cfdi.cfdi_complementoUUID",$reem_cfdi_complementoUUID)
						->get();

	          $uuid_coincidencias = count($buyComprasParaVincular);
					} else {
						$buyComprasParaVincular = DB::table("eegr_compras AS buy")
						->join("fnzs_pagos_orden AS ordp", "buy.id", "=", "ordp.factura_compra")
	          ->whereNull("buy.reembolso_vinculado_main")
	          ->whereNull("buy.reembolso_vinculado_soli")
						->get();

          	$uuid_coincidencias = count($buyComprasParaVincular);
					}

          $listComprasVinculadas = array();
					$buyComprasVinculadas = DB::table("eegr_compras AS buy")
					//->join("cfdi__estructura AS cfdi", "buy.id", "=", "cfdi.compra_vinculada")
          ->join("terc_reembolso_main AS main", "buy.reembolso_vinculado_main", "=", "main.id")
          ->join("terc_reembolso_solicitud AS reem_soli", "buy.reembolso_vinculado_soli", "=", "reem_soli.id")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->get();

          foreach ($buyComprasVinculadas as $vCPV) {
            $cfdiCompras = DB::table("eegr_compras AS buy")
						->join("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
						->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")
            ->where("buy.token_compras",$vCPV->token_compras)
            ->select('cfdi.cfdi_comprobante_total','cfdi.cfdi_comprobante_moneda','cfdi.cfdi_comprobante_tipo_de_comprobante','cfdi.cfdi_complementoUUID')
            ->first();

            if ($cfdiCompras) {
              $cfdi_comprobante_total = "$".number_format($cfdiCompras->cfdi_comprobante_total, $JwtAuth->getMonedaAPI($cfdiCompras->cfdi_comprobante_moneda), '.', ',')." ".$cfdiCompras->cfdi_comprobante_moneda;
            } else {
              $compra_importe = 0;
              $queryDEtailsTotal = DB::table("eegr_compras AS buy")
              ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
              ->where("buy.token_compras",$vCPV->token_compras)
              ->get();
            
              foreach ($queryDEtailsTotal as $vDet) {
                $resultante = 0;
                $det_subtotal = ($vDet->precio_unitario * $vDet->cantidad) - $vDet->descuento;
                $resultante = $det_subtotal + $vDet->traslados_total - $vDet->retenciones_total;
                $compra_importe += $resultante;
              }
              $cfdi_comprobante_total = "$".number_format($compra_importe, $JwtAuth->getMonedaAPI($vCPV->moneda), '.', ',')." ".$vCPV->moneda;
            }
            
            $cfdi_comprobante_tipo_de_comprobante = $cfdiCompras ? $cfdiCompras->cfdi_comprobante_tipo_de_comprobante : '---';
            $cfdi_complementoUUID = $cfdiCompras ? $cfdiCompras->cfdi_complementoUUID : '---';
					  $queryProveedor = DB::table("sos_personas AS people")
            ->join("eegr_catalogo_proveedores AS catprov", "people.id", "catprov.proveedor")
            ->join("eegr_compras AS buy", "catprov.id", "buy.proveedor")
					  ->where("buy.token_compras",$vCPV->token_compras)
					  ->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido')
					  ->first();
					  $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
            $proveedor_folio = $queryProveedor ? ('PRV-'.$JwtAuth->generarFolio($queryProveedor->folio).(!is_null($queryProveedor->post_folio) ? '-'.$queryProveedor->post_folio : '')) : "";
					  $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
            $rowCPV = array(
              "token_compras" => $vCPV->token_compras,
              "folio_compra" => "COMP-".$JwtAuth->generarFolio($vCPV->folio_compra).($vCPV->post_folio != NULL ? '-'.$vCPV->post_folio : ''),
              "cfdi_comprobante_total" => $cfdi_comprobante_total,
              "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
              "cfdi_complementoUUID" => $cfdi_complementoUUID,
              "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vCPV->fecha_contabilizacion),
              "fecha_contabilizacion_html" => date('Y-m-d', $vCPV->fecha_contabilizacion),
              "proveedor_token" => $proveedor_token,
              "proveedor_folio" => $proveedor_folio,
              "proveedor_name" => $proveedor_name,
            );
            $listComprasVinculadas[] = $rowCPV;
          }

          //$autorizacion_vh = null;

					$row_main = array(
						"token_reem" => $vremb->token_reem,
            "folio_reem" => $folio_reem,
						"token_solicitud_reem" => $vremb->token_solicitud_reem,
						"folio_solicitud" => $JwtAuth->generarFolio($vremb->folio_solicitud),
            
						"comision_token" => $vremb->token_comision_main,
            "comision_folio" => "COMI-".$JwtAuth->generarFolio($vremb->folio_comision),
            "comision_proyecto" => $JwtAuth->desencriptar($vremb->comision_proyecto),
						
						"nombreEmiPers" => $nombreEmiPers,
            "company" => $name_emisor,

            "fecha_solicitud" => gmdate('Y-m-d H:i:s', $vremb->fecha_solicitud),
            "fecha_gasto" => gmdate('Y-m-d H:i:s', $vremb->fecha_gasto),
            "fecha_gasto_html" => $JwtAuth->convierteEpocFechaHtml($vremb->zona_horaria,$vremb->fecha_gasto),
            "ticket_gasto" => $JwtAuth->desencriptar($vremb->ticket_gasto),
            //proveedor
						  "pagado_a" => $vremb->pagado_a,
						  "prov_tkn" => $vremb->pagado_a == 'prov' ? $prov_tkn : '',
						  "prov_folio" => $vremb->pagado_a == 'prov' ? $prov_folio : '',
						  "prov_name" => $vremb->pagado_a == 'prov' ? $prov_name : '',
						  "prov_nombre_comercial" => $vremb->pagado_a == 'prov' ? $prov_nombre_comercial : '',
						  "prov_rfc_generico" => $vremb->pagado_a == 'prov' ? $prov_rfc_generico : '',
						  "prov_rfc" => $vremb->pagado_a == 'prov' ? $prov_rfc : '',
						  "prov_taxid" => $vremb->pagado_a == 'prov' ? $prov_taxid : '',
						//forma de pago
              "fpago_clave" => $vremb->forma_pago,
              "fpago_forma" => $JwtAuth->getFormasPagoAPI($vremb->forma_pago),
						//importe
							"moneda_code" => $moneda_entrante_string,
							"moneda_decimales" => $moneda_entrante_decimales,
							"importe_requerido" => floatval($vremb->importe_entrante),
							
							"importe_requ_info_entr" => floatval($vremb->importe_entrante),
							"importe_requ_info_entr_format" => "$".number_format($vremb->importe_entrante,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min,

							"importe_requ_info_sali" => floatval($vremb->importe_entrante * $vremb->tipo_cambio),
							"importe_requ_info_sali_format" => "$".number_format($vremb->importe_entrante * $vremb->tipo_cambio,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min,
							"tipo_cambio_soli" => $vremb->tipo_cambio,
							"tipo_cambio_soli_format" => "$".number_format($vremb->tipo_cambio,$moneda_entrante_decimales,'.',',')." $moneda_entrante_string_min",
						//observaciones
							"observaciones" => $JwtAuth->desencriptar($vremb->motivo_reem),
							"autorizacion_vh" => $autorizacion_vh,
							"max_auth_vh" => $max_auth_vh,
							"comments_auth_vh" => $comments_auth_vh,
							"comments_auth_vh_back" => $comments_auth_vh,
							"fecha_registro_auth_vh" => $fecha_registro_auth_vh,
							"hora_registro_auth_vh" => $hora_registro_auth_vh,
							"autorizacion_egr" => $autorizacion_egr,
							"max_auth_egr" => $max_auth_egr,
							"comments_auth_egr" => $comments_auth_egr,
							"comments_auth_egr_write" => "",
							"fecha_registro_auth_egr" => $fecha_registro_auth_egr,
							"hora_registro_auth_egr" => $hora_registro_auth_egr,
							"auth_egr_list_array" => $auth_egr_list_array,
							"terminado" => $terminado,
							"fecha_respuesta_autorizacion" => $fecha_respuesta_autorizacion,
							"time_respuesta_autorizacion" => $time_respuesta_autorizacion,
							"reem_cfdi_comprobante_total" => $reem_cfdi_comprobante_total,
							"reem_cfdi_comprobante_tipo_de_comprobante" => $reem_cfdi_comprobante_tipo_de_comprobante,
							"reem_cfdi_complementoUUID" => $reem_cfdi_complementoUUID,
							"xmlFacturaContent" => $xmlFacturaContent,
							"xmlFacturaDesglose" => $xmlFacturaDesglose,
							"pdfFacturaContent" => $pdfFacturaContent,
							"anexos" => $docsAnexosArray,
							"viewModalDocumentosAdjuntos" => false,
							"viewModalCompraVinculacion" => false,
							"compra_vinculada" => $buyCompras > 0 ? true : false,
							"compras_vincular" => [],
              "uuid_coincidencias" => $uuid_coincidencias,
							"viewModalCompraSoliCancelaVinculacion" => false,
							"soli_cancela_vinc_comentarios" => "",
              "compras_vinculadas" => $listComprasVinculadas,
              "compras_vinculadas_total" => "vinculado a ".count($listComprasVinculadas)." compras",
							"viewModalCompraAuth" => false,
							"viewModalListadoDeAutorizaciones" => false,
					);
          $reembolsos_lista_pendientes[] = $row_main;
				}
				//$dataMensaje = array("status" => "success", "code" => 200,"reem_lista_pend" => $reembolsos_lista_pendientes);
        $dataMensaje = array(
					"status" => "success", 
					"code" => 200, 
					"reem_lista_pend_by_id" => $reembolsos_lista_pendientes,
					"reem_lista_pend_by_reembolso" => collect($reembolsos_lista_pendientes)
						->groupBy('folio_reem')
						->map(function($items,$key) {
							return [
								'token_reem' => $items->first()['token_reem'],
								'folio_reem' => $key,
								'moneda_code' => $items->first()['moneda_code'],
								'importe_requ_info_entr' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_entr'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'tipo_cambio_soli' => $items->first()['tipo_cambio_soli_format'],

								'importe_requ_info_sali' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_sali'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'partidas' => $items->values()
							];
						})
						->values(),

					"reem_lista_pend_by_comision" => collect($reembolsos_lista_pendientes)
						->groupBy('comision_folio')
						->map(function($items,$key) {
							return [
								'comision_token' => $items->first()['comision_token'],
                'comision_folio' => $key,
                'comision_proyecto' => $items->first()['comision_proyecto'],

								'moneda_code' => $items->first()['moneda_code'],
								'importe_requ_info_entr' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_entr'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'tipo_cambio_soli' => $items->first()['tipo_cambio_soli_format'],

								'importe_requ_info_sali' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_sali'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'partidas' => $items->values()
							];
						})
						->values(),
					"reem_lista_pend_by_proveedor" => collect($reembolsos_lista_pendientes)
						->groupBy('prov_folio')
						->filter(function($items){
								return $items->first()['pagado_a'] === "prov";
							}
						)
						->map(function($items,$key) {
							return [
								'prov_tkn' => $items->first()['prov_tkn'],
                'prov_folio' => $key,
                'prov_name' => $items->first()['prov_name'],
                'prov_nombre_comercial' => $items->first()['prov_nombre_comercial'],
                'prov_rfc_generico' => $items->first()['prov_rfc_generico'],
                'prov_rfc' => $items->first()['prov_rfc'],
                'prov_taxid' => $items->first()['prov_taxid'],

								'moneda_code' => $items->first()['moneda_code'],
								'importe_requ_info_entr' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_entr'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'tipo_cambio_soli' => $items->first()['tipo_cambio_soli_format'],

								'importe_requ_info_sali' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_sali'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'partidas' => $items->values()
							];
						})
						->values(),
					"reem_lista_pend_no_vinc_compras" => collect($reembolsos_lista_pendientes)->filter(fn($reem) => count($reem["compras_vinculadas"]) === 0)->values(),
					"reem_lista_pend_vinc_compras" => collect($reembolsos_lista_pendientes)->filter(fn($reem) => count($reem["compras_vinculadas"]) > 0)->values(),
				);
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La informacion que intenta registrar no es valida'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function reembolso_compras_para_vincular(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
			'token_solicitud_reem' => 'required|string',
			'token_reem' => 'required|string',
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
      $JwtAuth = new \App\Helpers\JwtAuth();
      date_default_timezone_set('America/Mexico_City');
			$token_solicitud_reem = $request->input('token_solicitud_reem');
			$token_reem = $request->input('token_reem');
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
      
      $reembolsoData = DB::table("terc_reembolso_solicitud AS reem_soli")
      ->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
      ->join("sos_reembolsos_comisiones_rel AS reem_comi", "reem_main.id", "=", "reem_comi.reembolso_main")
      ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        "reem_soli.status_activacion" => TRUE,
        "reem_main.status_reem" => TRUE,
        "reem_main.borrador_reem" => FALSE,
        "reem_soli.token_solicitud_reem" => $token_solicitud_reem,
        "reem_main.token_reem" => $token_reem,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->select('emp.zona_horaria','reem_main.folio_reem','reem_main.post_folio_reem')
      ->first();

      if (!$reembolsoData) {
        return response()->json(['status' => 'error','message' => 'No se encontraron reembolsos registrados'], 428);
      }
      
      date_default_timezone_set($reembolsoData->zona_horaria);

      $queryFacturaDocs = DB::table("sos_documentos AS docs")
      ->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
      ->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
      ->whereIn("docs.tipo_documento", ["xml","pdf"])
      ->where([
        "docs.status_documento" => TRUE,
        "main.token_reem" => $token_reem,
        "reem_soli.token_solicitud_reem" => $token_solicitud_reem
      ])
      ->count();

      $listComprasParaVincular = array();
      if ($queryFacturaDocs == 1) {
        $buyComprasParaVincular = DB::table("eegr_compras AS buy")
        ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "catprov.id")
        ->join("sos_personas AS people", "catprov.proveedor", "people.id")
        ->join("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
        ->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")
        ->join("fnzs_pagos_orden AS ordp", "buy.id", "=", "ordp.factura_compra")
        ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
        ->where("emp.empresa_token",$empresa)
        ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
          return $query->whereBetween("buy.fecha_contabilizacion", [$fechaInicio, $fechaFin]);
        })
        ->select(
          'buy.*', 
          'cfdi.*',
          'catprov.token_cat_proveedores', 'catprov.folio as prov_folio', 'catprov.post_folio as prov_post_folio',
          'people.nombre_extendido'
        )
        ->whereNull("buy.reembolso_vinculado_main")
        ->whereNull("buy.reembolso_vinculado_soli")
        ->orderBy("buy.id", "DESC")
        ->get();

        foreach ($buyComprasParaVincular as $vCPV) {
          $rowCPV = array(
            "token_compras" => $vCPV->token_compras,
            "folio_compra" => "COMP-".$JwtAuth->generarFolio($vCPV->folio_compra).($vCPV->post_folio != NULL ? '-'.$vCPV->post_folio : ''),
            "cfdi_comprobante_total" => "$".number_format($vCPV->cfdi_comprobante_total, $JwtAuth->getMonedaAPI($vCPV->cfdi_comprobante_moneda), '.', ',')." ".$vCPV->cfdi_comprobante_moneda,
            "cfdi_comprobante_tipo_de_comprobante" => $vCPV->cfdi_comprobante_tipo_de_comprobante,
            "cfdi_complementoUUID" => $vCPV->cfdi_complementoUUID,
            "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vCPV->fecha_contabilizacion),
            "fecha_contabilizacion_html" => date('Y-m-d', $vCPV->fecha_contabilizacion),
            "proveedor_token" => $vCPV->token_cat_proveedores,
            "proveedor_folio" => 'PRV-'.$JwtAuth->generarFolio($vCPV->prov_folio).(!is_null($vCPV->prov_post_folio) ? '-'.$vCPV->prov_post_folio : ''),
            "proveedor_name" => $JwtAuth->desencriptar($vCPV->nombre_extendido),
            "compra_observaciones" => "",
          );
          $listComprasParaVincular[] = $rowCPV;
        }
      } else {
        $buyComprasParaVincular = DB::table("eegr_compras AS buy")
        ->join("eegr_catalogo_proveedores AS catprov", "buy.proveedor", "catprov.id")
        ->join("sos_personas AS people", "catprov.proveedor", "people.id")
        ->join("fnzs_pagos_orden AS ordp", "buy.id", "=", "ordp.factura_compra")
        ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
          return $query->whereBetween("buy.fecha_contabilizacion", [$fechaInicio, $fechaFin]);
        })
        ->select(
          'buy.*',
          'catprov.token_cat_proveedores', 'catprov.folio as prov_folio', 'catprov.post_folio as prov_post_folio',
          'people.nombre_extendido'
        )
        ->whereNull("buy.reembolso_vinculado_main")
        ->whereNull("buy.reembolso_vinculado_soli")
        ->orderBy("buy.id", "DESC")
        ->get();

        foreach ($buyComprasParaVincular as $vCPV) {
          $compra_importe = DB::table("eegr_compras AS buy")
          ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
          ->where("buy.token_compras",$vCPV->token_compras)
          ->selectRaw(
            "SUM(
              (detBuy.precio_unitario * detBuy.cantidad)
              - detBuy.descuento
              + detBuy.traslados_total
              - detBuy.retenciones_total
            ) as importe_compra"
          )
          ->value('importe_compra') ?? 0;

          $rowCPV = array(
            "token_compras" => $vCPV->token_compras,
            "folio_compra" => "COMP-".$JwtAuth->generarFolio($vCPV->folio_compra).($vCPV->post_folio != NULL ? '-'.$vCPV->post_folio : ''),
            "cfdi_comprobante_total" => "$".number_format($compra_importe, $JwtAuth->getMonedaAPI($vCPV->moneda), '.', ',')." ".$vCPV->moneda,
            "cfdi_comprobante_tipo_de_comprobante" => "$".number_format($vCPV->tipo_de_cambio, $JwtAuth->getMonedaAPI($vCPV->moneda), '.', ',')." ".$vCPV->moneda,
            "cfdi_complementoUUID" => "---",
            "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vCPV->fecha_contabilizacion),
            "fecha_contabilizacion_html" => date('Y-m-d', $vCPV->fecha_contabilizacion),
            "proveedor_token" => $vCPV->token_cat_proveedores,
            "proveedor_folio" => 'PRV-'.$JwtAuth->generarFolio($vCPV->prov_folio).(!is_null($vCPV->prov_post_folio) ? '-'.$vCPV->prov_post_folio : ''),
            "proveedor_name" => $JwtAuth->desencriptar($vCPV->nombre_extendido),
            "compra_observaciones" => "",
          );
          $listComprasParaVincular[] = $rowCPV;
        }
      }
      $dataMensaje = array(
        "status" => "success", 
        "code" => 200, 
        "compras_vincular" => $listComprasParaVincular,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function reembolso_lista_concluidos(Request $request){
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
      $JwtAuth = new \App\Helpers\JwtAuth();
      date_default_timezone_set('America/Mexico_City');
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
      
      if ($JwtAuth->usersAdmins($usuario)) {
        $list_reembolso = DB::table("terc_reembolso_solicitud AS reem_soli")
        ->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
        ->join("sos_reembolsos_comisiones_rel AS reem_comi", "reem_main.id", "=", "reem_comi.reembolso_main")
        ->join("terc_comisiones_main AS comi_soli", "reem_comi.comision", "=", "comi_soli.id")
        ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
        ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
          return $query->whereBetween("reem_main.fecha_sistema", [$fechaInicio, $fechaFin]);
        })
        ->whereNotNull("reem_main.user_receptor_egr")
        ->where([
          "reem_soli.autorizacion_egr" => "A",
          "reem_soli.status_activacion" => TRUE,
          "reem_main.status_reem" => TRUE,
          "reem_main.borrador_reem" => FALSE,
          "emp.empresa_token" => $empresa
        ])
        ->orderBy('reem_main.folio_reem', 'DESC')->get();
      } else {
        $list_reembolso = DB::table("terc_reembolso_solicitud AS reem_soli")
        ->join("terc_reembolso_main AS reem_main", "reem_soli.reembolso_main", "=", "reem_main.id")
        ->join("sos_reembolsos_comisiones_rel AS reem_comi", "reem_main.id", "=", "reem_comi.reembolso_main")
        ->join("terc_comisiones_main AS comi_soli", "reem_comi.comision", "=", "comi_soli.id")
        ->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
        ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
        ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
        ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
          return $query->whereBetween("reem_main.fecha_sistema", [$fechaInicio, $fechaFin]);
        })
        ->where([
          "reem_soli.autorizacion_egr" => "A",
          "reem_soli.status_activacion" => TRUE,
          "reem_main.status_reem" => TRUE,
          "reem_main.borrador_reem" => FALSE,
          "emp.empresa_token" => $empresa,
          "users.usuario_token" => $usuario
        ])
        ->orderBy('reem_main.folio_reem', 'DESC')->get();
      }

      if ($list_reembolso->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron reembolsos registrados'
        );
      } else {
        $reembolsos_lista_autorizados = array();
				foreach ($list_reembolso as $vremb) {
					date_default_timezone_set($vremb->zona_horaria);
					$folio_reem = 'REEM-'.$JwtAuth->generarFolio($vremb->folio_reem).(!is_null($vremb->post_folio_reem) ? '-'.$vremb->post_folio_reem : '');

					$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("sos_personas AS people", "emp.persona", "=", "people.id")
					->where("reem_main.token_reem",$vremb->token_reem)
          ->select("people.abrev_nombre")
          ->first();
          $name_emisor = $selectNameEmpEmi->abrev_nombre;

					$queryPersEmi = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
					->join("fnzs_catalogo_acreedores AS catAcree", "reem_main.user_acreedor", "=", "catAcree.id")
					->where("reem_main.token_reem",$vremb->token_reem)
          ->select("catAcree.acr_titular")
          ->first();
          $nombreEmiPers = $queryPersEmi->acr_titular ? $JwtAuth->desencriptar($queryPersEmi->acr_titular) : '';

					$soli_r_prov = DB::table("terc_reembolso_solicitud AS reem_soli")
					->join("terc_reembolso_main AS rmain", "reem_soli.reembolso_main", "=", "rmain.id")
					->join("eegr_catalogo_proveedores AS cprov", "reem_soli.proveedor", "=", "cprov.id")
					->join("sos_personas AS prov", "cprov.proveedor", "=", "prov.id")
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->where("rmain.token_reem", $vremb->token_reem)
					->select('cprov.folio','cprov.post_folio','cprov.token_cat_proveedores','prov.nombre_extendido','prov.nombre_com','prov.rfc_generico', 'prov.rfc', 'prov.tax_id')
					->first();

					$prov_tkn = $soli_r_prov ? $soli_r_prov->token_cat_proveedores : "";
					$prov_folio = 'PRV-'.$JwtAuth->generarFolio($soli_r_prov->folio).(!is_null($soli_r_prov->post_folio) ? '-'.$soli_r_prov->post_folio : '');
					$prov_name = $soli_r_prov ? $JwtAuth->desencriptar($soli_r_prov->nombre_extendido) : "";
          $prov_nombre_comercial = $soli_r_prov && !is_null($soli_r_prov->nombre_com) ? $JwtAuth->desencriptar($soli_r_prov->nombre_com) : "";
					$prov_rfc_generico = $soli_r_prov ? $soli_r_prov->rfc_generico : "";
					$prov_rfc = $soli_r_prov && !is_null($soli_r_prov->rfc) ? $JwtAuth->desencriptar($soli_r_prov->rfc) : "";
					$prov_taxid = $soli_r_prov && !is_null($soli_r_prov->tax_id) ? $JwtAuth->desencriptar($soli_r_prov->tax_id) : "";

          $moneda_entrante_string = $vremb->moneda_entrante;
					$moneda_entrante_string_min = $vremb->moneda_entrante;
					$moneda_entrante_decimales = $JwtAuth->getMonedaAPI($vremb->moneda_entrante);

					//importe
					$importe_total = $vremb->importe_entrante;
					$importe_total_conversion = $vremb->importe_entrante * $vremb->tipo_cambio;
					if (($vremb->autorizacion_vh == "A" || $vremb->autorizacion_vh == "N") && $vremb->autorizacion_egr == "A" && $vremb->terminado == TRUE) {
						$total_reembolsado = $vremb->importe_entrante;
						$total_reembolsado_conversion = $vremb->importe_entrante * $vremb->tipo_cambio;
					}

					//$importe_requ_info_entr = "$".number_format($vremb->importe_entrante,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min;
					//$importe_requ_info_sali = "$".number_format($vremb->importe_entrante * $vremb->tipo_cambio,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min;

					$autorizacion_vh = null;
					if ($vremb->autorizacion_vh == "A" || $vremb->autorizacion_vh == "N") $autorizacion_vh = true;
					if ($vremb->autorizacion_vh == "D") $autorizacion_vh = false;
					if ($vremb->autorizacion_vh != NULL) $autorizacion_vh = $vremb->autorizacion_vh;

					$select_list_auth_vh = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_vh,r_auth.comentarios FROM terc_reembolso_autorizacion_vh AS r_auth JOIN terc_reembolso_main AS r_main 
            JOIN terc_reembolso_solicitud AS s_soli WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?", 
            [$vremb->token_reem, $vremb->token_solicitud_reem]);

					$max_auth_vh = null;
					$fecha_registro_auth_vh = "";
					$hora_registro_auth_vh = "";
					$comments_auth_vh = "";

					if ($autorizacion_vh != null && $autorizacion_vh != "N" && count($select_list_auth_vh) > 0) {
						if (end($select_list_auth_vh)->autorizacion_vh == "A") $max_auth_vh = true;
						if (end($select_list_auth_vh)->autorizacion_vh == "D") $max_auth_vh = false;
						$fecha_registro_auth_vh = gmdate('Y-m-d H:i:s', end($select_list_auth_vh)->fecha_registro);
						$hora_registro_auth_vh = date('H:i:s', end($select_list_auth_vh)->fecha_registro);
						$comments_auth_vh = $JwtAuth->desencriptar(end($select_list_auth_vh)->comentarios);
					}

					switch ($vremb->autorizacion_egr) {
            case 'A':
              $autorizacion_egr = true;
              break;
            case 'D':
              $autorizacion_egr = false;
              break;
            default:
              $autorizacion_egr = null;
              break;
          }

					$select_list_auth_egr = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_egr,r_auth.comentarios FROM terc_reembolso_autorizacion_egr AS r_auth JOIN terc_reembolso_main AS r_main 
            JOIN terc_reembolso_solicitud AS s_soli WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?", 
            [$vremb->token_reem, $vremb->token_solicitud_reem]);

					$max_auth_egr = null;
					$fecha_registro_auth_egr = "";
					$hora_registro_auth_egr = "";
					$comments_auth_egr = "";
					$auth_egr_list_array = array();
					//echo count($select_list_auth_egr);
					//$select_list_auth_egr = DB::table('terc_reembolso_autorizacion_egr AS r_auth')
    			//->join('terc_reembolso_main AS r_main', 'r_auth.reembolso_main', '=', 'r_main.id')
    			//->join('terc_reembolso_solicitud AS s_soli', 'r_auth.reembolso_solicitud', '=', 's_soli.id')
    			//->where('r_main.token_reem', $vremb->token_reem)
    			//->where('s_soli.token_solicitud_reem', $vremb->token_solicitud_reem)
    			//->select('r_auth.fecha_registro', 'r_auth.autorizacion_egr', 'r_auth.comentarios')
    			//->orderBy('r_auth.fecha_registro', 'desc')
    			//->first();

					if (count($select_list_auth_egr) > 0) {
						foreach ($select_list_auth_egr as $l_auth) {
							$row_auth_vh = array(
								"autorizacion_egr" => $l_auth->autorizacion_egr,
								"registro_auth_egr" => date('d-m-Y - H:i:s', $l_auth->fecha_registro),
								"comentarios" => !is_null($l_auth->comentarios) ? $JwtAuth->desencriptar($l_auth->comentarios) : ''
							);
							$auth_egr_list_array[] = $row_auth_vh;
						}
						if (end($select_list_auth_egr)->autorizacion_egr == "A") $max_auth_egr = true;
						if (end($select_list_auth_egr)->autorizacion_egr == "D") $max_auth_egr = false;
						$fecha_registro_auth_egr = gmdate('Y-m-d H:i:s', end($select_list_auth_egr)->fecha_registro);
						$hora_registro_auth_egr = date('H:i:s', end($select_list_auth_egr)->fecha_registro);
						$comments_auth_egr = !is_null(end($select_list_auth_egr)->comentarios) ? $JwtAuth->desencriptar(end($select_list_auth_egr)->comentarios) : '';
					}

          $terminado = $vremb->terminado ? true : false;

					$fecha_respuesta_autorizacion = gmdate('Y-m-d H:i:s', $vremb->tiempo_respuesta_autorizacion);
					$time_respuesta_autorizacion = "";
					if ($vremb->tiempo_respuesta_autorizacion > time()) {
						$time_inicial_autorizacion = $vremb->tiempo_respuesta_autorizacion - time();
						$days_autorizacion = floor($time_inicial_autorizacion / (60 * 60 * 24));
						$time_inicial_autorizacion %= (60 * 60 * 24);
						$hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
						$time_inicial_autorizacion %= (60 * 60);
						$min_autorizacion = floor($time_inicial_autorizacion / 60);
						$time_inicial_autorizacion %= 60;
						$sec_autorizacion = $time_inicial_autorizacion;
						$time_respuesta_autorizacion = $days_autorizacion . " días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion"; // 
					} else {
						$time_respuesta_autorizacion = "tiempo de respuesta terminado";
					}

					$queryCFDIDataXMLReem = DB::table("cfdi_comprobantes_fiscales AS cfd")
					->join("cfdi_vinculacion_reembolsos AS vinc_reem", "cfd.id", "=", "vinc_reem.comprobante_fiscal")
					->join("terc_reembolso_main AS main", "vinc_reem.reembolso_vinculado_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "vinc_reem.reembolso_vinculado_soli", "=", "reem_soli.id")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->select("cfd.cfdi_comprobante_moneda","cfd.cfdi_comprobante_total","cfd.cfdi_comprobante_tipo_de_comprobante","cfd.cfdi_complementoUUID")
          ->first();
            
          $reem_cfdi_comprobante_total = $queryCFDIDataXMLReem ? "$".number_format($queryCFDIDataXMLReem->cfdi_comprobante_total, $JwtAuth->getMonedaAPI($queryCFDIDataXMLReem->cfdi_comprobante_moneda), '.', ',')." ".$queryCFDIDataXMLReem->cfdi_comprobante_moneda : "";
          $reem_cfdi_comprobante_tipo_de_comprobante = $queryCFDIDataXMLReem ? $queryCFDIDataXMLReem->cfdi_comprobante_tipo_de_comprobante : "";
          $reem_cfdi_complementoUUID = $queryCFDIDataXMLReem ? $queryCFDIDataXMLReem->cfdi_complementoUUID : "";

					$xmlFacturaContent = array();
					$xmlFacturaDesglose = null;
					$queryFacturaXMLReem = DB::table("sos_documentos AS docs")
					->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
					->where("docs.status_documento", TRUE)
					->where("docs.tipo_documento", "xml")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->get();

					foreach ($queryFacturaXMLReem as $xDoc) {
						$token_documento = $xDoc->token_documento;
						$name_documento = $JwtAuth->desencriptar($xDoc->nombre_documento);
						$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $xDoc->token_documento;
						$rowXML = array(
							"token_documento" => $token_documento,
							"ext_doc" => $xDoc->tipo_documento,
							"name_documento" => $name_documento,
							"url" => $ruta_alterna
						);
						$xmlFacturaContent[] = $rowXML;
						$filepath = $vremb->root_tkn . "/0010-reem/$folio_reem/" . $JwtAuth->generarFolio($vremb->folio_solicitud) . "/anexos";
						$rutaArchivo = storage_path("app/public/root/$filepath/$name_documento");
						$xmlFacturaDesglose = file_get_contents($rutaArchivo);
					}

					$pdfFacturaContent = array();
					$queryFacturaPDFReem = DB::table("sos_documentos AS docs")
					->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
					->where("docs.status_documento", TRUE)
					->where("docs.tipo_documento", "pdf")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)->get();

					foreach ($queryFacturaPDFReem as $pdfDoc) {
						$token_documento = $pdfDoc->token_documento;
						$name_documento = $JwtAuth->desencriptar($pdfDoc->nombre_documento);
						$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $pdfDoc->token_documento;
						$rowDet = array(
							"token_documento" => $token_documento,
							"ext_doc" => $pdfDoc->tipo_documento,
							"name_documento" => $name_documento,
							"url" => $ruta_alterna
						);
						$pdfFacturaContent[] = $rowDet;
					}

					$docsAnexosArray = array();
					$selectAnexosReem = DB::table("sos_documentos AS docs")
					->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
					->where("docs.status_documento", TRUE)
					->where("docs.tipo_documento", "an")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->get();

					foreach ($selectAnexosReem as $vDoc) {
						$token_docs = $vDoc->token_documento;
						$tipo_doc = $vDoc->tipo_documento;
						$ext_doc = $vDoc->extension_documento;
						$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
						$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $vDoc->token_documento;
            
						$rowDet = array(
							"token_docs" => $token_docs,
							"name_documento" => $name_documento,
							"ext_doc" => $tipo_doc,
							"url" => $ruta_alterna
						);
						$docsAnexosArray[] = $rowDet;
					}

					$buyCompras = DB::table("eegr_compras AS buy")
					->join("terc_reembolso_main AS main", "buy.reembolso_vinculado_main", "=", "main.id")
					->join("terc_reembolso_solicitud AS reem_soli", "buy.reembolso_vinculado_soli", "=", "reem_soli.id")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->count();

          $listComprasParaVincular = array();
          $uuid_coincidencias = 0;
					if (count($xmlFacturaContent) == 1 && count($pdfFacturaContent) == 1) {
						$buyComprasParaVincular = DB::table("eegr_compras AS buy")
						->join("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
						->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")
						->join("fnzs_pagos_orden AS ordp", "buy.id", "=", "ordp.factura_compra")
	          ->whereNull("buy.reembolso_vinculado_main")
	          ->whereNull("buy.reembolso_vinculado_soli")
	          ->where("cfdi.cfdi_complementoUUID",$reem_cfdi_complementoUUID)
						->get();

	          $uuid_coincidencias = count($buyComprasParaVincular);
					} else {
						$buyComprasParaVincular = DB::table("eegr_compras AS buy")
						->join("fnzs_pagos_orden AS ordp", "buy.id", "=", "ordp.factura_compra")
	          ->whereNull("buy.reembolso_vinculado_main")
	          ->whereNull("buy.reembolso_vinculado_soli")
						->get();

          	$uuid_coincidencias = count($buyComprasParaVincular);
					}

          $listComprasVinculadas = array();
					$buyComprasVinculadas = DB::table("eegr_compras AS buy")
					//->join("cfdi__estructura AS cfdi", "buy.id", "=", "cfdi.compra_vinculada")
          ->join("terc_reembolso_main AS main", "buy.reembolso_vinculado_main", "=", "main.id")
          ->join("terc_reembolso_solicitud AS reem_soli", "buy.reembolso_vinculado_soli", "=", "reem_soli.id")
					->where("main.token_reem", $vremb->token_reem)
					->where("reem_soli.token_solicitud_reem", $vremb->token_solicitud_reem)
					->get();

          foreach ($buyComprasVinculadas as $vCPV) {
            $cfdiCompras = DB::table("eegr_compras AS buy")
						->join("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
						->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")
            ->where("buy.token_compras",$vCPV->token_compras)
            ->select('cfdi.cfdi_comprobante_total','cfdi.cfdi_comprobante_moneda','cfdi.cfdi_comprobante_tipo_de_comprobante','cfdi.cfdi_complementoUUID')
            ->first();

            if ($cfdiCompras) {
              $cfdi_comprobante_total = "$".number_format($cfdiCompras->cfdi_comprobante_total, $JwtAuth->getMonedaAPI($cfdiCompras->cfdi_comprobante_moneda), '.', ',')." ".$cfdiCompras->cfdi_comprobante_moneda;
            } else {
              $compra_importe = 0;
              $queryDEtailsTotal = DB::table("eegr_compras AS buy")
              ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
              ->where("buy.token_compras",$vCPV->token_compras)
              ->get();
            
              foreach ($queryDEtailsTotal as $vDet) {
                $resultante = 0;
                $det_subtotal = ($vDet->precio_unitario * $vDet->cantidad) - $vDet->descuento;
                $resultante = $det_subtotal + $vDet->traslados_total - $vDet->retenciones_total;
                $compra_importe += $resultante;
              }
              $cfdi_comprobante_total = "$".number_format($compra_importe, $JwtAuth->getMonedaAPI($vCPV->moneda), '.', ',')." ".$vCPV->moneda;
            }
            
            $cfdi_comprobante_tipo_de_comprobante = $cfdiCompras ? $cfdiCompras->cfdi_comprobante_tipo_de_comprobante : '---';
            $cfdi_complementoUUID = $cfdiCompras ? $cfdiCompras->cfdi_complementoUUID : '---';
					  $queryProveedor = DB::table("sos_personas AS people")
            ->join("eegr_catalogo_proveedores AS catprov", "people.id", "catprov.proveedor")
            ->join("eegr_compras AS buy", "catprov.id", "buy.proveedor")
					  ->where("buy.token_compras",$vCPV->token_compras)
					  ->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido')
					  ->first();
					  $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
            $proveedor_folio = $queryProveedor ? ('PRV-'.$JwtAuth->generarFolio($queryProveedor->folio).(!is_null($queryProveedor->post_folio) ? '-'.$queryProveedor->post_folio : '')) : "";
					  $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";
            $rowCPV = array(
              "token_compras" => $vCPV->token_compras,
              "folio_compra" => "COMP-".$JwtAuth->generarFolio($vCPV->folio_compra).($vCPV->post_folio != NULL ? '-'.$vCPV->post_folio : ''),
              "cfdi_comprobante_total" => $cfdi_comprobante_total,
              "cfdi_comprobante_tipo_de_comprobante" => $cfdi_comprobante_tipo_de_comprobante,
              "cfdi_complementoUUID" => $cfdi_complementoUUID,
              "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vCPV->fecha_contabilizacion),
              "fecha_contabilizacion_html" => date('Y-m-d', $vCPV->fecha_contabilizacion),
              "proveedor_token" => $proveedor_token,
              "proveedor_folio" => $proveedor_folio,
              "proveedor_name" => $proveedor_name,
            );
            $listComprasVinculadas[] = $rowCPV;
          }

          //$autorizacion_vh = null;

					$row_main = array(
						"token_reem" => $vremb->token_reem,
            "folio_reem" => $folio_reem,
						"token_solicitud_reem" => $vremb->token_solicitud_reem,
						"folio_solicitud" => $JwtAuth->generarFolio($vremb->folio_solicitud),
            
						"comision_token" => $vremb->token_comision_main,
            "comision_folio" => "COMI-".$JwtAuth->generarFolio($vremb->folio_comision),
            "comision_proyecto" => $JwtAuth->desencriptar($vremb->comision_proyecto),
						
						"nombreEmiPers" => $nombreEmiPers,
            "company" => $name_emisor,

            "fecha_solicitud" => gmdate('Y-m-d H:i:s', $vremb->fecha_solicitud),
            "fecha_gasto" => gmdate('Y-m-d H:i:s', $vremb->fecha_gasto),
            "fecha_gasto_html" => $JwtAuth->convierteEpocFechaHtml($vremb->zona_horaria,$vremb->fecha_gasto),
            "ticket_gasto" => $JwtAuth->desencriptar($vremb->ticket_gasto),
            //proveedor
						  "pagado_a" => $vremb->pagado_a,
						  "prov_tkn" => $vremb->pagado_a == 'prov' ? $prov_tkn : '',
						  "prov_folio" => $vremb->pagado_a == 'prov' ? $prov_folio : '',
						  "prov_name" => $vremb->pagado_a == 'prov' ? $prov_name : '',
						  "prov_nombre_comercial" => $vremb->pagado_a == 'prov' ? $prov_nombre_comercial : '',
						  "prov_rfc_generico" => $vremb->pagado_a == 'prov' ? $prov_rfc_generico : '',
						  "prov_rfc" => $vremb->pagado_a == 'prov' ? $prov_rfc : '',
						  "prov_taxid" => $vremb->pagado_a == 'prov' ? $prov_taxid : '',
						//forma de pago
              "fpago_clave" => $vremb->forma_pago,
              "fpago_forma" => $JwtAuth->getFormasPagoAPI($vremb->forma_pago),
						//importe
							"moneda_code" => $moneda_entrante_string,
							"moneda_decimales" => $moneda_entrante_decimales,
							"importe_requerido" => floatval($vremb->importe_entrante),
							
							"importe_requ_info_entr" => floatval($vremb->importe_entrante),
							"importe_requ_info_entr_format" => "$".number_format($vremb->importe_entrante,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min,

							"importe_requ_info_sali" => floatval($vremb->importe_entrante * $vremb->tipo_cambio),
							"importe_requ_info_sali_format" => "$".number_format($vremb->importe_entrante * $vremb->tipo_cambio,$moneda_entrante_decimales,'.',',')." ".$moneda_entrante_string_min,
							"tipo_cambio_soli" => $vremb->tipo_cambio,
							"tipo_cambio_soli_format" => "$".number_format($vremb->tipo_cambio,$moneda_entrante_decimales,'.',',')." $moneda_entrante_string_min",
						//observaciones
							"observaciones" => $JwtAuth->desencriptar($vremb->motivo_reem),
							"autorizacion_vh" => $autorizacion_vh,
							"max_auth_vh" => $max_auth_vh,
							"comments_auth_vh" => $comments_auth_vh,
							"comments_auth_vh_back" => $comments_auth_vh,
							"fecha_registro_auth_vh" => $fecha_registro_auth_vh,
							"hora_registro_auth_vh" => $hora_registro_auth_vh,
							"autorizacion_egr" => $autorizacion_egr,
							"max_auth_egr" => $max_auth_egr,
							"comments_auth_egr" => $comments_auth_egr,
							"comments_auth_egr_write" => "",
							"fecha_registro_auth_egr" => $fecha_registro_auth_egr,
							"hora_registro_auth_egr" => $hora_registro_auth_egr,
							"auth_egr_list_array" => $auth_egr_list_array,
							"terminado" => $terminado,
							"fecha_respuesta_autorizacion" => $fecha_respuesta_autorizacion,
							"time_respuesta_autorizacion" => $time_respuesta_autorizacion,
							"reem_cfdi_comprobante_total" => $reem_cfdi_comprobante_total,
							"reem_cfdi_comprobante_tipo_de_comprobante" => $reem_cfdi_comprobante_tipo_de_comprobante,
							"reem_cfdi_complementoUUID" => $reem_cfdi_complementoUUID,
							"xmlFacturaContent" => $xmlFacturaContent,
							"xmlFacturaDesglose" => $xmlFacturaDesglose,
							"pdfFacturaContent" => $pdfFacturaContent,
							"anexos" => $docsAnexosArray,
							"viewModalDocumentosAdjuntos" => false,
							"viewModalCompraVinculacion" => false,
							"compra_vinculada" => $buyCompras > 0 ? true : false,
							"compras_vincular" => [],
              "uuid_coincidencias" => $uuid_coincidencias,
							"viewModalCompraSoliCancelaVinculacion" => false,
							"soli_cancela_vinc_comentarios" => "",
              "compras_vinculadas" => $listComprasVinculadas,
              "compras_vinculadas_total" => "vinculado a ".count($listComprasVinculadas)." compras",
							"viewModalCompraAuth" => false,
							"viewModalListadoDeAutorizaciones" => false,
					);
          $reembolsos_lista_autorizados[] = $row_main;
				}

        $dataMensaje = array(
					"status" => "success", 
					"code" => 200, 
					"reem_lista_autorizados" => $reembolsos_lista_autorizados,
					/*"reem_lista_autorizados_by_reembolso" => collect($reembolsos_lista_autorizados)
						->groupBy('folio_reem')
						->map(function($items,$key) {
							return [
								'token_reem' => $items->first()['token_reem'],
								'folio_reem' => $key,
								'moneda_code' => $items->first()['moneda_code'],
								'importe_requ_info_entr' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_entr'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'tipo_cambio_soli' => $items->first()['tipo_cambio_soli_format'],

								'importe_requ_info_sali' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_sali'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'partidas' => $items->values()
							];
						})
						->values(),

					"reem_lista_autorizados_by_comision" => collect($reembolsos_lista_autorizados)
						->groupBy('comision_folio')
						->map(function($items,$key) {
							return [
								'comision_token' => $items->first()['comision_token'],
                'comision_folio' => $key,
                'comision_proyecto' => $items->first()['comision_proyecto'],

								'moneda_code' => $items->first()['moneda_code'],
								'importe_requ_info_entr' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_entr'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'tipo_cambio_soli' => $items->first()['tipo_cambio_soli_format'],

								'importe_requ_info_sali' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_sali'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'partidas' => $items->values()
							];
						})
						->values(),
					"reem_lista_autorizados_by_proveedor" => collect($reembolsos_lista_autorizados)
						->groupBy('prov_folio')
						->filter(function($items){
								return $items->first()['pagado_a'] === "prov";
							}
						)
						->map(function($items,$key) {
							return [
								'prov_tkn' => $items->first()['prov_tkn'],
                'prov_folio' => $key,
                'prov_name' => $items->first()['prov_name'],
                'prov_nombre_comercial' => $items->first()['prov_nombre_comercial'],
                'prov_rfc_generico' => $items->first()['prov_rfc_generico'],
                'prov_rfc' => $items->first()['prov_rfc'],
                'prov_taxid' => $items->first()['prov_taxid'],

								'moneda_code' => $items->first()['moneda_code'],
								'importe_requ_info_entr' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_entr'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'tipo_cambio_soli' => $items->first()['tipo_cambio_soli_format'],

								'importe_requ_info_sali' => "$".number_format(
									$items->sum(function($item) {return (float) $item['importe_requ_info_sali'];}),
									$items->first()['moneda_decimales'] ?? 2,'.',','
								)." ".$items->first()['moneda_code'],

								'partidas' => $items->values()
							];
						})
						->values(),
					"reem_lista_autorizados_no_vinc_compras" => collect($reembolsos_lista_autorizados)->filter(fn($reem) => count($reem["compras_vinculadas"]) === 0)->values(),
					"reem_lista_autorizados_vinc_compras" => collect($reembolsos_lista_autorizados)->filter(fn($reem) => count($reem["compras_vinculadas"]) > 0)->values(),*/
				);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function egr_reembolso_detalle(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayReem = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"token_reem" => "required|string",
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
				$token_reem = $parametrosArray["token_reem"];

				if ($JwtAuth->usersAdmins($usuario->user_token) == true) {
					$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
						->join("sos_reembolsos_comisiones_rel AS reem_comi", "reem_main.id", "=", "reem_comi.reembolso_main")
						->join("terc_comisiones_main AS comi_soli", "reem_comi.comision", "=", "comi_soli.id")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.last_version", "=", "reem_soli.id")
						->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
						->where([
						    "reem_soli.status_activacion" => TRUE, 
							"reem_main.token_reem" => $token_reem, 
							"reem_main.status_reem" => TRUE,
							"reem_main.borrador_reem" => FALSE,
							"emp.empresa_token" => $usuario->empresa_token,
						])
						->orderBy('reem_main.folio_reem', 'DESC')->get();
				} else {
					$reembolso_main_selected = DB::table("terc_reembolso_main AS reem_main")
						->join("sos_reembolsos_comisiones_rel AS reem_comi", "reem_main.id", "=", "reem_comi.reembolso_main")
						->join("terc_comisiones_main AS comi_soli", "reem_comi.comision", "=", "comi_soli.id")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.last_version", "=", "reem_soli.id")
						->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
						->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
						->where([
						    "reem_soli.status_activacion" => TRUE, 
							"reem_main.token_reem" => $token_reem,
							"reem_main.status_reem" => TRUE,
							"reem_main.borrador_reem" => FALSE,
							"emp.empresa_token" => $usuario->empresa_token,
							"users.usuario_token" => $usuario->user_token
						])
						->orderBy('reem_main.folio_reem', 'DESC')->get();
				}

				foreach ($reembolso_main_selected as $vremb) {
					$root_main_emisor = "";
					date_default_timezone_set($vremb->zona_horaria);
					$fecha_solicitud = gmdate('Y-m-d H:i:s', $vremb->fecha_sistema);
					$token_reem = $vremb->token_reem;

					$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vremb->folio_reem) . (!is_null($vremb->post_folio_reem) ? '-' . $vremb->post_folio_reem : '');

					//emisor
					$selectNameEmpEmi = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.emisor", "=", "emp.id")
						->join("sos_personas AS people", "emp.persona", "=", "people.id")
						->where(["reem_main.token_reem" => $vremb->token_reem])->get();

					foreach ($selectNameEmpEmi as $vEmisor) {
						$name_emisor = $vEmisor->abrev_nombre;
						$root_main_emisor = $vEmisor->root_tkn;
						$rfc_gen_emi = $vEmisor->rfc_generico;
						$rfc_emp_emi = $vEmisor->rfc != NULL ? $JwtAuth->desencriptar($vEmisor->rfc) : "---";
						$taxid_emp_emi = $vEmisor->tax_id != NULL ? $JwtAuth->desencriptar($vEmisor->tax_id) : "---";
					}

					$selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
						->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
						->where(["reem_main.token_reem" => $vremb->token_reem])->get();

					foreach ($selectPersEmpEmi as $vPemi) {
						$name_pers_emisor = $JwtAuth->desencriptarNombres($vPemi->paterno, $vPemi->materno, $vPemi->nombre);
					}

					//receptor 
					$selectNameEmpRec = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
						->join("sos_personas AS people", "emp.persona", "=", "people.id")
						->where(["reem_main.token_reem" => $vremb->token_reem])->get();

					$txt_folio_solicitud = "0";

					foreach ($selectNameEmpRec as $vReceptor) {
						$name_receptor = $vReceptor->abrev_nombre;
						$tkn_receptor = $vReceptor->empresa_token;
						$rfc_gen_receptor = $vReceptor->rfc_generico;
						$rfc_emp_receptor = $vReceptor->rfc != NULL ? $JwtAuth->desencriptar($vReceptor->rfc) : "---";
						$taxid_emp_receptor = $vReceptor->tax_id != NULL ? $JwtAuth->desencriptar($vReceptor->tax_id) : "---";
					}

					$selectPersEmpReceptor = DB::table("terc_reembolso_main AS reem_main")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
						->join("sos_personas AS people", "pers.empleado_name", "=", "people.id")
						->where(["reem_main.token_reem" => $vremb->token_reem])->get();

					foreach ($selectPersEmpReceptor as $vPrec) {
						$name_pers_receptor = $JwtAuth->desencriptarNombres($vPrec->paterno, $vPrec->materno, $vPrec->nombre);
					}

					$arraySoliReem = array();
					$soli_reem = DB::table("terc_reembolso_main AS reem_main")
						->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
						->where(["reem_main.token_reem" => $vremb->token_reem])
						->orderBy('reem_soli.folio_solicitud', 'DESC')->get();

					$importe_total = 0;
					$importe_total_conversion = 0;
					$total_reembolsado = 0;
					$total_reembolsado_conversion = 0;

					$total_tipo_cambio = 0;
					$moneda_entrante_string = "";
					$moneda_entrante_string_min = "";
					$moneda_entrante_decimales = 0;
					$total_reem_saliente = 0;
					$num_posicion = 0;

					foreach ($soli_reem as $vSoliR) {
						$soli_r_prov = DB::table("terc_reembolso_solicitud AS reem_soli")
							->join("terc_reembolso_main AS rmain", "reem_soli.reembolso_main", "=", "rmain.id")
							->join("eegr_catalogo_proveedores AS cprov", "reem_soli.proveedor", "=", "cprov.id")
							->join("sos_personas AS prov", "cprov.proveedor", "=", "prov.id")
							->where("reem_soli.token_solicitud_reem", $vSoliR->token_solicitud_reem)
							->where("rmain.token_reem", $vremb->token_reem)
							->select('cprov.token_cat_proveedores', 'prov.nombre_extendido', 'prov.rfc_generico', 'prov.rfc', 'prov.tax_id')
							->first();

						$tkn_prov = $soli_r_prov ? $soli_r_prov->token_cat_proveedores : "";
						$name_prov = $soli_r_prov ? $JwtAuth->desencriptar($soli_r_prov->nombre_extendido) : "";
						$rfc_generico_prov = $soli_r_prov ? $soli_r_prov->rfc_generico : "";
						$rfc_prov = $soli_r_prov && !is_null($soli_r_prov->rfc) ? $JwtAuth->desencriptar($soli_r_prov->rfc) : "";
						$taxid_prov = $soli_r_prov && !is_null($soli_r_prov->tax_id) ? $JwtAuth->desencriptar($soli_r_prov->tax_id) : "";

						$moneda_entrante_string = $vSoliR->moneda_entrante;
						$moneda_entrante_string_min = $vSoliR->moneda_entrante;
						$moneda_entrante_decimales = $JwtAuth->getMonedaAPI($vSoliR->moneda_entrante);

						//importe
						$importe_total = $importe_total + $vSoliR->importe_entrante;
						$importe_total_conversion = $importe_total_conversion + ($vSoliR->importe_entrante * $vSoliR->tipo_cambio);
						if (($vSoliR->autorizacion_vh == "A" || $vSoliR->autorizacion_vh == "N") && $vSoliR->autorizacion_egr == "A" && $vSoliR->terminado == TRUE) {
							$total_reembolsado = $total_reembolsado + $vSoliR->importe_entrante;
							$total_reembolsado_conversion = $total_reembolsado_conversion + ($vSoliR->importe_entrante * $vSoliR->tipo_cambio);
						}

						$importe_requ_info_entr = "$" . number_format($vSoliR->importe_entrante, $moneda_entrante_decimales, '.', ',') . " " . $moneda_entrante_string_min;
						$importe_requ_info_sali = "$" . number_format($vSoliR->importe_entrante * $vSoliR->tipo_cambio, $moneda_entrante_decimales, '.', ',') . " " . $moneda_entrante_string_min;

						$autorizacion_vh = null;
						//if ($vSoliR->autorizacion_vh == "A" || $vSoliR->autorizacion_vh == "N") $autorizacion_vh = true;
						//if ($vSoliR->autorizacion_vh == "D") $autorizacion_vh = false;
						if ($vSoliR->autorizacion_vh != NULL) $autorizacion_vh = $vSoliR->autorizacion_vh;

						$select_list_auth_vh = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_vh,r_auth.comentarios 
                                FROM terc_reembolso_autorizacion_vh AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                                WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id 
                                AND s_soli.token_solicitud_reem = ?", [$token_reem, $vSoliR->token_solicitud_reem]);

						$max_auth_vh = null;
						$fecha_registro_auth_vh = "";
						$hora_registro_auth_vh = "";
						$comments_auth_vh = "";

						if ($autorizacion_vh != null && $autorizacion_vh != "N" && count($select_list_auth_vh) > 0) {
							if (end($select_list_auth_vh)->autorizacion_vh == "A") $max_auth_vh = true;
							if (end($select_list_auth_vh)->autorizacion_vh == "D") $max_auth_vh = false;
							$fecha_registro_auth_vh = gmdate('Y-m-d H:i:s', end($select_list_auth_vh)->fecha_registro);
							$hora_registro_auth_vh = date('H:i:s', end($select_list_auth_vh)->fecha_registro);
							$comments_auth_vh = $JwtAuth->desencriptar(end($select_list_auth_vh)->comentarios);
						}

						$autorizacion_egr = null;
						if ($vSoliR->autorizacion_egr == "A") $autorizacion_egr = true;
						if ($vSoliR->autorizacion_egr == "D") $autorizacion_egr = false;

						$select_list_auth_egr = DB::select("SELECT r_auth.fecha_registro,r_auth.autorizacion_egr,r_auth.comentarios 
                            FROM terc_reembolso_autorizacion_egr AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
                            WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id 
                            AND s_soli.token_solicitud_reem = ?", [$token_reem, $vSoliR->token_solicitud_reem]);

						$max_auth_egr = null;
						$fecha_registro_auth_egr = "";
						$hora_registro_auth_egr = "";
						$comments_auth_egr = "";
						$auth_egr_list_array = array();
						if (count($select_list_auth_egr) > 0) {
							foreach ($select_list_auth_egr as $l_auth) {
								$row_auth_vh = array(
									"autorizacion_egr" => $l_auth->autorizacion_egr,
									"registro_auth_egr" => date('d-m-Y - H:i:s', $l_auth->fecha_registro),
									"comentarios" => $JwtAuth->desencriptar($l_auth->comentarios)
								);
								$auth_egr_list_array[] = $row_auth_vh;
							}

							if (end($select_list_auth_egr)->autorizacion_egr == "A") $max_auth_egr = true;
							if (end($select_list_auth_egr)->autorizacion_egr == "D") $max_auth_egr = false;
							$fecha_registro_auth_egr = gmdate('Y-m-d H:i:s', end($select_list_auth_egr)->fecha_registro);
							$hora_registro_auth_egr = date('H:i:s', end($select_list_auth_egr)->fecha_registro);
							$comments_auth_egr = $JwtAuth->desencriptar(end($select_list_auth_egr)->comentarios);
						}

						$terminado = false;
						if ($vSoliR->terminado == TRUE) $terminado = true;

						$fecha_respuesta_autorizacion = gmdate('Y-m-d H:i:s', $vremb->tiempo_respuesta_autorizacion);
						$time_respuesta_autorizacion = "";
						if ($vSoliR->tiempo_respuesta_autorizacion > time()) {
							$time_inicial_autorizacion = $vremb->tiempo_respuesta_autorizacion - time();
							$days_autorizacion = floor($time_inicial_autorizacion / (60 * 60 * 24));
							$time_inicial_autorizacion %= (60 * 60 * 24);
							$hours_autorizacion = floor($time_inicial_autorizacion / (60 * 60));
							$time_inicial_autorizacion %= (60 * 60);
							$min_autorizacion = floor($time_inicial_autorizacion / 60);
							$time_inicial_autorizacion %= 60;
							$sec_autorizacion = $time_inicial_autorizacion;
							$time_respuesta_autorizacion = $days_autorizacion . " días,$hours_autorizacion:$min_autorizacion:$sec_autorizacion"; // 
						} else {
							$time_respuesta_autorizacion = "tiempo de respuesta terminado";
						}

						$queryCFDIDataXMLReem = DB::table("cfdi_comprobantes_fiscales AS cfd")
						->join("cfdi_vinculacion_reembolsos AS vinc_reem", "cfd.id", "=", "vinc_reem.comprobante_fiscal")
						->join("terc_reembolso_main AS main", "vinc_reem.reembolso_vinculado_main", "=", "main.id")
						->join("terc_reembolso_solicitud AS reem_soli", "vinc_reem.reembolso_vinculado_soli", "=", "reem_soli.id")
						->where("main.token_reem", $token_reem)
						->where("reem_soli.token_solicitud_reem", $vSoliR->token_solicitud_reem)
						->select("cfd.cfdi_comprobante_moneda","cfd.cfdi_comprobante_total","cfd.cfdi_comprobante_tipo_de_comprobante","cfd.cfdi_complementoUUID")
            ->first();
            
            $reem_cfdi_comprobante_total = $queryCFDIDataXMLReem ? "$".number_format($queryCFDIDataXMLReem->cfdi_comprobante_total, $JwtAuth->getMonedaAPI($queryCFDIDataXMLReem->cfdi_comprobante_moneda), '.', ',')." ".$queryCFDIDataXMLReem->cfdi_comprobante_moneda : "";
            $reem_cfdi_comprobante_tipo_de_comprobante = $queryCFDIDataXMLReem ? $queryCFDIDataXMLReem->cfdi_comprobante_tipo_de_comprobante : "";
            $reem_cfdi_complementoUUID = $queryCFDIDataXMLReem ? $queryCFDIDataXMLReem->cfdi_complementoUUID : "";

						$xmlFacturaContent = array();
						$xmlFacturaDesglose = null;

						$queryFacturaXMLReem = DB::table("sos_documentos AS docs")
							->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
							->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
							->where("docs.status_documento", TRUE)
							->where("docs.tipo_documento", "xml")
							->where("main.token_reem", $token_reem)
							->where("reem_soli.token_solicitud_reem", $vSoliR->token_solicitud_reem)
							->get();

						foreach ($queryFacturaXMLReem as $xDoc) {
							$token_documento = $xDoc->token_documento;
							$name_documento = $JwtAuth->desencriptar($xDoc->nombre_documento);
							$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $xDoc->token_documento;

							$rowXML = array(
								"token_documento" => $token_documento,
								"ext_doc" => $xDoc->tipo_documento,
								"name_documento" => $name_documento,
								"url" => $ruta_alterna
							);
							$xmlFacturaContent[] = $rowXML;
							$filepath = $vremb->root_tkn . "/0010-reem/$folio_reem/" . $JwtAuth->generarFolio($vSoliR->folio_solicitud) . "/anexos";
							$rutaArchivo = storage_path("app/public/root/$filepath/$name_documento");
              //echo $xDoc->token_documento." ".$xDoc->nombre_documento;
							$xmlFacturaDesglose = file_get_contents($rutaArchivo);
							//return response()->json(['status' => 'error','code' => 200,'message' => $xmlFacturaDesglose]);
						}

						$pdfFacturaContent = array();
						$queryFacturaPDFReem = DB::table("sos_documentos AS docs")
							->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
							->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
							->where("docs.status_documento", TRUE)
							->where("docs.tipo_documento", "pdf")
							->where("main.token_reem", $token_reem)
							->where("reem_soli.token_solicitud_reem", $vSoliR->token_solicitud_reem)->get();

						foreach ($queryFacturaPDFReem as $pdfDoc) {
							$token_documento = $pdfDoc->token_documento;
							$name_documento = $JwtAuth->desencriptar($pdfDoc->nombre_documento);
							$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $pdfDoc->token_documento;

							$rowDet = array(
								"token_documento" => $token_documento,
								"ext_doc" => $pdfDoc->tipo_documento,
								"name_documento" => $name_documento,
								"url" => $ruta_alterna
							);
							$pdfFacturaContent[] = $rowDet;
						}

						$docsAnexosArray = array();
						$selectAnexosReem = DB::table("sos_documentos AS docs")
							->join("terc_reembolso_main AS main", "docs.reembolso_main", "=", "main.id")
							->join("terc_reembolso_solicitud AS reem_soli", "docs.reembolso_solicitud", "=", "reem_soli.id")
							->where("docs.status_documento", TRUE)
							->where("docs.tipo_documento", "an")
							->where("main.token_reem", $token_reem)
							->where("reem_soli.token_solicitud_reem", $vSoliR->token_solicitud_reem)
							->get();

						foreach ($selectAnexosReem as $vDoc) {
							$token_docs = $vDoc->token_documento;
							$tipo_doc = $vDoc->tipo_documento;
							$ext_doc = $vDoc->extension_documento;
							$name_documento = $JwtAuth->desencriptar($vDoc->nombre_documento);
							$ruta_alterna = "https://downloads.sos-mexico.com.mx/reembolsos_anexos/" . $folio_reem . "/" . $vDoc->token_documento;

							$rowDet = array(
								"token_docs" => $token_docs,
								"name_documento" => $name_documento,
								"ext_doc" => $tipo_doc,
								"url" => $ruta_alterna
							);
							$docsAnexosArray[] = $rowDet;
						}

						$buyCompras = DB::table("eegr_compras AS buy")
							->join("terc_reembolso_main AS main", "buy.reembolso_vinculado_main", "=", "main.id")
							->join("terc_reembolso_solicitud AS reem_soli", "buy.reembolso_vinculado_soli", "=", "reem_soli.id")
							->where("main.token_reem", $vremb->token_reem)
							->where("reem_soli.token_solicitud_reem", $vSoliR->token_solicitud_reem)
							->count();

            $listComprasParaVincular = array();
						$buyComprasParaVincular = DB::table("eegr_compras AS buy")
						->join("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
						->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")
						->join("fnzs_pagos_orden AS ordp", "buy.id", "=", "ordp.factura_compra")
            ->whereNull("buy.reembolso_vinculado_main")
            ->whereNull("buy.reembolso_vinculado_soli")
						->get();

            foreach ($buyComprasParaVincular as $vCPV) {

					    $queryProveedor = DB::table("sos_personas AS people")
              ->join("eegr_catalogo_proveedores AS catprov", "people.id", "catprov.proveedor")
              ->join("eegr_compras AS buy", "catprov.id", "buy.proveedor")
					    ->where("buy.token_compras",$vCPV->token_compras)
					    ->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido')
					    ->first();
					    $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
              $proveedor_folio = $queryProveedor ? ('PRV-'.$JwtAuth->generarFolio($queryProveedor->folio).(!is_null($queryProveedor->post_folio) ? '-'.$queryProveedor->post_folio : '')) : "";
					    $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";

              $rowCPV = array(
                "token_compras" => $vCPV->token_compras,
                "folio_compra" => "COMP-".$JwtAuth->generarFolio($vCPV->folio_compra).($vCPV->post_folio != NULL ? '-'.$vCPV->post_folio : ''),
                "cfdi_comprobante_total" => "$".number_format($vCPV->cfdi_comprobante_total, $JwtAuth->getMonedaAPI($vCPV->cfdi_comprobante_moneda), '.', ',')." ".$vCPV->cfdi_comprobante_moneda,
                "cfdi_comprobante_tipo_de_comprobante" => $vCPV->cfdi_comprobante_tipo_de_comprobante,
                "cfdi_complementoUUID" => $vCPV->cfdi_complementoUUID,
                "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vCPV->fecha_contabilizacion),
                "fecha_contabilizacion_html" => date('Y-m-d', $vCPV->fecha_contabilizacion),
                "proveedor_token" => $proveedor_token,
                "proveedor_folio" => $proveedor_folio,
                "proveedor_name" => $proveedor_name,
              );
              $listComprasParaVincular[] = $rowCPV;
            }

            $uuid_coincidencias = array_filter($listComprasParaVincular,function ($buy) use ($reem_cfdi_complementoUUID){
              return $buy['cfdi_complementoUUID'] === $reem_cfdi_complementoUUID;
            });

            $listComprasVinculadas = array();
						$buyComprasVinculadas = DB::table("eegr_compras AS buy")
						->join("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
						->join("cfdi_comprobantes_fiscales AS cfdi", "vinc_buy.comprobante_fiscal", "=", "cfdi.id")
            ->join("terc_reembolso_main AS main", "buy.reembolso_vinculado_main", "=", "main.id")
            ->join("terc_reembolso_solicitud AS reem_soli", "buy.reembolso_vinculado_soli", "=", "reem_soli.id")
						->where("main.token_reem", $vremb->token_reem)
						->where("reem_soli.token_solicitud_reem", $vSoliR->token_solicitud_reem)
						->get();

            foreach ($buyComprasVinculadas as $vCPV) {
					    $queryProveedor = DB::table("sos_personas AS people")
              ->join("eegr_catalogo_proveedores AS catprov", "people.id", "catprov.proveedor")
              ->join("eegr_compras AS buy", "catprov.id", "buy.proveedor")
					    ->where("buy.token_compras",$vCPV->token_compras)
					    ->select('catprov.token_cat_proveedores','catprov.folio','catprov.post_folio','people.nombre_extendido')
					    ->first();
					    $proveedor_token = $queryProveedor ? $queryProveedor->token_cat_proveedores : "";
              $proveedor_folio = $queryProveedor ? ('PRV-'.$JwtAuth->generarFolio($queryProveedor->folio).(!is_null($queryProveedor->post_folio) ? '-'.$queryProveedor->post_folio : '')) : "";
					    $proveedor_name = $queryProveedor ? $JwtAuth->desencriptar($queryProveedor->nombre_extendido) : "";

              $rowCPV = array(
                "token_compras" => $vCPV->token_compras,
                "folio_compra" => "COMP-".$JwtAuth->generarFolio($vCPV->folio_compra).($vCPV->post_folio != NULL ? '-'.$vCPV->post_folio : ''),
                "cfdi_comprobante_total" => "$".number_format($vCPV->cfdi_comprobante_total, $JwtAuth->getMonedaAPI($vCPV->cfdi_comprobante_moneda), '.', ',')." ".$vCPV->cfdi_comprobante_moneda,
                "cfdi_comprobante_tipo_de_comprobante" => $vCPV->cfdi_comprobante_tipo_de_comprobante,
                "cfdi_complementoUUID" => $vCPV->cfdi_complementoUUID,
                "fecha_contabilizacion" => gmdate('Y-m-d H:i:s', $vCPV->fecha_contabilizacion),
                "fecha_contabilizacion_html" => date('Y-m-d', $vCPV->fecha_contabilizacion),
                "proveedor_token" => $proveedor_token,
                "proveedor_folio" => $proveedor_folio,
                "proveedor_name" => $proveedor_name,
              );
              $listComprasVinculadas[] = $rowCPV;
            }

						$row_soli = array(
							"posicion" => $num_posicion,
              "token_reem" => $vremb->token_reem,
							"token_solicitud_reem" => $vSoliR->token_solicitud_reem,
							"folio_solicitud" => $JwtAuth->generarFolio($vSoliR->folio_solicitud),
							"fecha_solicitud" => gmdate('Y-m-d H:i:s', $vSoliR->fecha_solicitud),
							"fecha_gasto" => gmdate('Y-m-d H:i:s', $vSoliR->fecha_gasto),
							"fecha_gasto_html" => $JwtAuth->convierteEpocFechaHtml($vremb->zona_horaria, $vSoliR->fecha_gasto),
							"ticket_gasto" => $JwtAuth->desencriptar($vSoliR->ticket_gasto),
							"pagado_a" => $vSoliR->pagado_a,
							//proveedor
							"tkn_prov" => $tkn_prov,
							"proveedor" => $name_prov,
							"rfc_generico_prov" => $rfc_generico_prov,
							"rfc_prov" => $rfc_prov,
							"taxid_prov" => $taxid_prov,
							//forma de pago
							//"fpago_token" => $vSoliR->token_formapago,
              "fpago_clave" => $vSoliR->forma_pago,
              "fpago_forma" => $JwtAuth->getFormasPagoAPI($vSoliR->forma_pago),
							//importe
							"importe_requerido" => floatval($vSoliR->importe_entrante),
							//"importe_requerido_info" => $importe_requerido_info,

							"importe_requ_info_entr" => $importe_requ_info_entr,
							"importe_requ_info_sali" => $importe_requ_info_sali,

							"tipo_cambio_soli" => $vSoliR->tipo_cambio,
							//observaciones
							"observaciones" => $JwtAuth->desencriptar($vSoliR->motivo_reem),

							"autorizacion_vh" => $autorizacion_vh,
							"max_auth_vh" => $max_auth_vh,
							"comments_auth_vh" => $comments_auth_vh,
							"comments_auth_vh_back" => $comments_auth_vh,
							"fecha_registro_auth_vh" => $fecha_registro_auth_vh,
							"hora_registro_auth_vh" => $hora_registro_auth_vh,

							"autorizacion_egr" => $autorizacion_egr,
							"max_auth_egr" => $max_auth_egr,
							"comments_auth_egr" => $comments_auth_egr,
							"comments_auth_egr_write" => "",
							"fecha_registro_auth_egr" => $fecha_registro_auth_egr,
							"hora_registro_auth_egr" => $hora_registro_auth_egr,
							"auth_egr_list_array" => $auth_egr_list_array,
							"terminado" => $terminado,
							"fecha_respuesta_autorizacion" => $fecha_respuesta_autorizacion,
							"time_respuesta_autorizacion" => $time_respuesta_autorizacion,
							"reem_cfdi_comprobante_total" => $reem_cfdi_comprobante_total,
							"reem_cfdi_comprobante_tipo_de_comprobante" => $reem_cfdi_comprobante_tipo_de_comprobante,
							"reem_cfdi_complementoUUID" => $reem_cfdi_complementoUUID,
							"xmlFacturaContent" => $xmlFacturaContent,
							"xmlFacturaDesglose" => $xmlFacturaDesglose,
							"pdfFacturaContent" => $pdfFacturaContent,
							"anexos" => $docsAnexosArray,
							"viewModalCompraVinculacion" => false,
							"compra_vinculada" => $buyCompras > 0 ? true : false,
							"compras_vincular" => $listComprasParaVincular,
              "uuid_coincidencias" => count($uuid_coincidencias),
							"viewModalCompraSoliCancelaVinculacion" => false,
							"soli_cancela_vinc_comentarios" => "",
              "compras_vinculadas" => $listComprasVinculadas,
              "compras_vinculadas_total" => "vinculado a ".count($listComprasVinculadas)." compras",
							"viewModalCompraAuth" => false,
							"viewModalListadoDeAutorizaciones" => false,
						);
						$arraySoliReem[] = $row_soli;
						++$num_posicion;
					}

					$total_restante = $importe_total - $total_reembolsado;
					$total_restante_conversion = $importe_total_conversion - $total_reembolsado_conversion;

					$countOrdenesPagoReem = DB::table("terc_reembolso_main AS reem_main")
					->join("fnzs_pagos_orden AS order","reem_main.id","=","order.reembolso_main")
					->where("reem_main.token_reem",$vremb->token_reem)->count();

					$row = array(
						"token_reem" => $vremb->token_reem,
						"folio_reem" => $folio_reem,
						"fecha_solicitud" => $fecha_solicitud,
						//emisor
						"emisor_company" => $name_emisor,
						"nombreEmiPers" => $name_pers_emisor,
						//receptor
						"receptor_company" => $name_receptor,
						"nombreReceptorPers" => $name_pers_receptor,

						"total_reembolsado" => "$" . number_format($total_reembolsado, $moneda_entrante_decimales, '.', ',') . " " . $moneda_entrante_string,
						"total_reembolsado_conversion" => "$" . number_format($total_reembolsado_conversion, $moneda_entrante_decimales, '.', ',') . " " . $moneda_entrante_string,
						"total_restante" => "$" . number_format($total_restante, $moneda_entrante_decimales, '.', ',') . " " . $moneda_entrante_string,
						"total_restante_conversion" => "$" . number_format($total_restante_conversion, $moneda_entrante_decimales, '.', ',') . " " . $moneda_entrante_string,
						"total_importe" => "$" . number_format($importe_total, $moneda_entrante_decimales, '.', ',') . " " . $moneda_entrante_string,
						"total_importe_conversion" => "$" . number_format($importe_total_conversion, $moneda_entrante_decimales, '.', ',') . " " . $moneda_entrante_string,
						"comision_folio" => "COMI-" . $JwtAuth->generarFolio($vremb->folio_comision),
						"comision_proyecto" => $JwtAuth->desencriptar($vremb->comision_proyecto),
						"ordenes_pago_vinculadas" => $countOrdenesPagoReem,
						"soliReem" => $arraySoliReem,
					);

					$arrayReem[] = $row;
				}

				$dataMensaje = array(
					'status' => 'success',
					'code' => 200,
					'reem_det' => $arrayReem,
				);
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La informacion que intenta registrar no es valida'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function egr_reembolso_auth(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"tkn_solicitud" => "required|string",
				"autorizacion" => "required|boolean",
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
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$tkn_solicitud = $parametrosArray["tkn_solicitud"];
				$autorizacion = $parametrosArray["autorizacion"];

				$valida_reem = isset($tokenReembolso) && !empty($tokenReembolso);
				$valida_solicitud = isset($tkn_solicitud) && !empty($tkn_solicitud);
				$valida_autorizacion = isset($autorizacion) && is_bool($autorizacion);

				if ($valida_reem && $valida_solicitud && $valida_autorizacion) {
					$list_reem = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
					->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
					->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
					->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
					->where("reem_main.token_reem",$tokenReembolso)
					->where("reem_soli.status_activacion",TRUE)
					->where("reem_soli.token_solicitud_reem",$tkn_solicitud)
					->where("reem_main.status_reem",TRUE)
					->where("reem_main.borrador_reem",FALSE)
					->where("emp.empresa_token",$usuario->empresa_token)
          ->get();

					foreach ($list_reem as $vReem) {
						if ($vReem->autorizacion_vh == "N" || $vReem->autorizacion_vh == "A") {
							date_default_timezone_set($vReem->zona_horaria);
							$auth_bd = $autorizacion ? "A" : "D";

							$folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem).(!is_null($vReem->post_folio_reem) ? '-'.$vReem->post_folio_reem : '');
							$folio_soli_reem = $JwtAuth->generarFolio($vReem->folio_solicitud);

							$update_auth_true = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
							->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
							->where("reem_main.token_reem",$vReem->token_reem)
							->where("reem_soli.token_solicitud_reem",$vReem->token_solicitud_reem)
							->where("reem_main.status_reem",TRUE)
							->where("emp.empresa_token",$usuario->empresa_token)
							->limit(1)->update(array("reem_soli.autorizacion_egr" => $auth_bd));
							if ($update_auth_true) {
								$select_reembolso_main = DB::table("terc_reembolso_main")->where("token_reem",$vReem->token_reem)->value("id");
								$select_reem_soli = DB::table("terc_reembolso_solicitud")->where("token_solicitud_reem",$vReem->token_solicitud_reem)->value("id");

								$all_soli_reem = DB::table("terc_reembolso_main AS reem_main")
								->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
								->where("reem_main.token_reem",$vReem->token_reem)
                ->get();

								$approv_soli_reem = DB::table("terc_reembolso_main AS reem_main")
								->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
								->where(["reem_main.token_reem" => $vReem->token_reem, "reem_soli.autorizacion_egr" => "A"])
								->orwhere(["reem_main.token_reem" => $vReem->token_reem, "reem_soli.autorizacion_egr" => "D"])->get();

								if (count($approv_soli_reem) == count($all_soli_reem)) {
									DB::table("terc_reembolso_main")->where(["token_reem" => $vReem->token_reem])->limit(1)->update(array("last_revision_egr" => time()));
								}

								$aprov_desaprov = $autorizacion ? "aprobada" : "desaprobada";
								$mensaje_sistema = "Solicitud de reembolso con folio $folio_reem y subfolio $folio_soli_reem fue $aprov_desaprov satisfactoriamente";
								$mensaje_user = "Solicitud de reembolso con folio $folio_reem y subfolio $folio_soli_reem fue ".$aprov_desaprov;
								
								$token_auth = $JwtAuth->encriptarToken(time(),$select_reembolso_main.$select_reem_soli.$autorizacion.time() - 500);

								$select_folio_auth_egr = DB::select("SELECT r_auth.id FROM terc_reembolso_autorizacion_egr AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
									WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",[$tokenReembolso, $tkn_solicitud]);

								$select_folio_auth_max_egr = DB::select("SELECT folio_auth_reem FROM terc_reembolso_autorizacion_egr WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_egr AS r_auth
									JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id 
									AND s_soli.token_solicitud_reem = ?)",[$tokenReembolso, $tkn_solicitud]);

								$folio_auth = count($select_folio_auth_egr) == 0 ? 1 : $select_folio_auth_max_egr[0]->folio_auth_reem + 1;

								DB::table('terc_reembolso_autorizacion_egr')
								->insert(
									array(
										"token_auth_reem" => $token_auth,
										"folio_auth_reem" => $folio_auth,
										"fecha_registro" => time(),
										"reembolso_main" => $select_reembolso_main,
										"reembolso_solicitud" => $select_reem_soli,
										"autorizacion_egr" => $auth_bd,
									)
								);

								$JwtAuth->notificacionPushDevices($vReem->usuario_token, "Revisión de solicitud de reembolso por egresos", $mensaje_user);

								$vhPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
								->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
								->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
								->where(["reem_main.token_reem" => $vReem->token_reem])->get();

								foreach ($vhPersEmpEmi as $vpVH) {
									$JwtAuth->notificacionPushDevices($vpVH->usuario_token, "SOS-México - Portal para empleados", $mensaje_user);
								}

								$eegrPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
								->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
								->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
								->where(["reem_main.token_reem" => $vReem->token_reem])->get();

								foreach ($eegrPersEmpEmi as $vpEGR) {
									$JwtAuth->notificacionPushDevices($vpEGR->usuario_token, "SOS-México - Portal para empleados", $mensaje_user);
								}

								$dataMensaje = array('message' => $mensaje_sistema,'code' => 200,'status' => 'success');
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'Error en autorización'
								);
							}
							
						} else {
							$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Reembolso no autorizado por el departamento de valor humano');
						}
					}
				} else {
          $mensaje_error = '';
					if (!$valida_reem) {$mensaje_error = 'Folio de reembolso incorrecto';}
					if (!$valida_solicitud) {$mensaje_error = 'La solicitud de reembolso es invalida';}
					if (!$valida_autorizacion) {$mensaje_error = 'Error en validación de autorización';}
					//if (!$valida_observaciones) {$mensaje_error = 'Error en observaciones';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La informacion que intenta registrar no es valida'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function egr_reembolso_observaciones_auth(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"tkn_solicitud" => "required|string",
				"observaciones" => "required|string",
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
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$tkn_solicitud = $parametrosArray["tkn_solicitud"];
				$observaciones = $parametrosArray["observaciones"];
				//return response()->json(['status' => 'error','code' => 200,'message' => $observaciones]);

				$valida_reem = isset($tokenReembolso) && !empty($tokenReembolso);
				$valida_solicitud = isset($tkn_solicitud) && !empty($tkn_solicitud);
				$valida_observaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones);

				if ($valida_reem && $valida_solicitud && $valida_observaciones) {

					$list_reem = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
					->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
					->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
					->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
					->where("reem_main.token_reem",$tokenReembolso)
					->where("reem_soli.token_solicitud_reem",$tkn_solicitud)
					->where("reem_soli.status_activacion",TRUE)
					->where("reem_main.status_reem",TRUE)
					->where("reem_main.borrador_reem",FALSE)
					->where("emp.empresa_token",$usuario->empresa_token)
          ->get();

					foreach ($list_reem as $vReem) {
						if ($vReem->autorizacion_vh == "N" || $vReem->autorizacion_vh == "A") {
							date_default_timezone_set($vReem->zona_horaria);

							$query_list_auth_egr = DB::table('terc_reembolso_autorizacion_egr AS r_auth')
    					->join('terc_reembolso_main AS r_main', 'r_auth.reembolso_main', '=', 'r_main.id')
    					->join('terc_reembolso_solicitud AS s_soli', 'r_auth.reembolso_solicitud', '=', 's_soli.id')
    					->where('r_main.token_reem', $vReem->token_reem)
    					->where('s_soli.token_solicitud_reem', $vReem->token_solicitud_reem)
    					->select('r_auth.token_auth_reem')
    					->orderBy('r_auth.fecha_registro', 'desc')
    					->first();

							$update_comentarios_auth = DB::table("terc_reembolso_autorizacion_egr")->where("token_auth_reem",$query_list_auth_egr->token_auth_reem)->limit(1)->update(array("comentarios" => $JwtAuth->encriptar($observaciones)));
							if ($update_comentarios_auth) {
								$dataMensaje = array('status' => 'success', 'code' => 200, 'message' => 'Comentarios sobre reembolso han sido registrados');
							} else {
								$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Comentarios sobre reembolso no registrados, intente nuevamente o comuníquese a soporte');
							}
						} else {
							$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Reembolso no autorizado por el departamento de valor humano');
						}
					}
				} else {
          $mensaje_error = '';
					if (!$valida_reem) {$mensaje_error = 'Folio de reembolso incorrecto';}
					if (!$valida_solicitud) {$mensaje_error = 'La solicitud de reembolso es invalida';}
					if (!$valida_observaciones) {$mensaje_error = 'Error en observaciones';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La informacion que intenta registrar no es valida'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function egr_reembolso_auth_pagar_a_acreedor(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"tkn_solicitud" => "required|string",
				"token_compras" => "required|string",
        "fecha_contabilizacion" => "required|string",
				"observaciones" => "required|string",
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

				$fecha_sistema = time();
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$tkn_solicitud = $parametrosArray["tkn_solicitud"];
				$token_compras = $parametrosArray["token_compras"];
				$fecha_contabilizacion = $parametrosArray["fecha_contabilizacion"];
				$observaciones = $parametrosArray["observaciones"];
        //exit;

				$validar_tokenReembolso = isset($tokenReembolso) && !empty($tokenReembolso);
				$validar_tkn_solicitud = isset($tkn_solicitud) && !empty($tkn_solicitud);
				$validar_token_compras = isset($token_compras) && !empty($token_compras);
				$validar_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
				$validar_observaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

				if ($validar_tokenReembolso && $validar_tkn_solicitud && $validar_token_compras && $validar_fecha_contabilizacion && $validar_observaciones) {
					$list_reem = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
					->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
					->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
					->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
					->where("reem_main.token_reem",$tokenReembolso)
					->where("reem_main.status_reem",TRUE)
					->where("reem_main.borrador_reem",FALSE)
					->where("reem_soli.token_solicitud_reem",$tkn_solicitud)
					->where("reem_soli.status_activacion",TRUE)
					->where("reem_soli.autorizacion_egr","A")
					->where("emp.empresa_token",$usuario->empresa_token)
          ->get();

					foreach ($list_reem as $vReem) {
            //echo $fecha_contabilizacion;
						if ($vReem->autorizacion_vh == "N" || $vReem->autorizacion_vh == "A") {
							date_default_timezone_set($vReem->zona_horaria);
              $folio_reem = 'REEM-' . $JwtAuth->generarFolio($vReem->folio_reem).(!is_null($vReem->post_folio_reem) ? '-'.$vReem->post_folio_reem : '');
							$folio_soli_reem = $JwtAuth->generarFolio($vReem->folio_solicitud);

							$fpago_clave = $vReem->forma_pago;
              $fpago_forma = $JwtAuth->getFormasPagoAPI($vReem->forma_pago);
              $select_reembolso_main = DB::table("terc_reembolso_main")->where("token_reem",$vReem->token_reem)->value("id");
              $select_reem_soli = DB::table("terc_reembolso_solicitud")->where("token_solicitud_reem",$vReem->token_solicitud_reem)->value("id");

              $all_soli_reem = DB::table("terc_reembolso_main AS reem_main")
              ->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
              ->where("reem_main.token_reem",$vReem->token_reem)->get();

              $approv_soli_reem = DB::table("terc_reembolso_main AS reem_main")
              ->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
              ->where(["reem_main.token_reem" => $vReem->token_reem, "reem_soli.autorizacion_egr" => "A"])
              ->orwhere(["reem_main.token_reem" => $vReem->token_reem, "reem_soli.autorizacion_egr" => "D"])->get();

              if (count($approv_soli_reem) == count($all_soli_reem)) {
                $update_revision_vh = DB::table("terc_reembolso_main")->where(["token_reem" => $vReem->token_reem])->limit(1)->update(array("last_revision_egr" => time()));
              }

              $listaCompras = DB::table("eegr_compras AS buy")
							->leftJoin("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
							->leftJoin("cfdi_comprobantes_fiscales AS cfd", "vinc_buy.comprobante_fiscal", "=", "cfd.id")
              ->join("main_empresas AS emp", "buy.comprador", "=", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
              ->where('buy.status_autorizacion',TRUE)
              ->where('buy.token_compras',$token_compras)
              ->where('emp.empresa_token',$usuario->empresa_token)
              ->where('users.usuario_token',$usuario->user_token)
              ->get();

              foreach ($listaCompras as $vBuy) {
                $empresa_main = DB::table("main_empresas")->where("empresa_token",$usuario->empresa_token)->value("id");
                $personal_main = DB::table("vhum_empleados_catalogo AS pers")
                ->join("main_empresa_usuario AS empuser", "pers.id", "=", "empuser.empleado")
                ->join("main_empresas AS emp", "empuser.empresa", "=", "emp.id")
                ->where("emp.empresa_token",$usuario->empresa_token)->value("pers.id");

                //$fecha_contabilizacion = $vBuy->fecha_contabilizacion;
                $order_importe = 0;

                if ($vBuy->compra_vinculada) {
                  $order_importe = $vBuy->cfdi_comprobante_total;
                } else {
                  //$order_importe = $vBuy->compra_vinculada ? $vBuy->cfdi_comprobante_total : 0;
                  $queryDEtailsTotal = DB::table("eegr_compras AS buy")
                  ->join("eegr_compras_detalle AS detBuy", "buy.id", "=", "detBuy.numero_compra")
                  ->where("buy.token_compras",$vBuy->token_compras)
                  ->get();
                
                  foreach ($queryDEtailsTotal as $vDet) {
                    $resultante = 0;
                    $det_subtotal = ($vDet->precio_unitario * $vDet->cantidad) - $vDet->descuento;
                    $resultante = $det_subtotal + $vDet->traslados_total - $vDet->retenciones_total;
                    $order_importe += $resultante;
                  }
                }
                //echo $order_importe;

                $order_tipo_cambio = $vBuy->compra_vinculada ? $vBuy->cfdi_comprobante_tipo_de_cambio : $vBuy->tipo_de_cambio;
                $order_moneda = $vBuy->compra_vinculada ? $vBuy->cfdi_comprobante_moneda : $vBuy->moneda;
                $buy_compra_id = DB::table('eegr_compras')->where("token_compras",$vBuy->token_compras)->value("id");
                $vincReemBuy = DB::table('eegr_compras')->where("token_compras",$vBuy->token_compras)
                ->limit(1)->update(array("reembolso_vinculado_main" => $select_reembolso_main,"reembolso_vinculado_soli" => $select_reem_soli));

                $queryOrdenPagoCompras = DB::table("fnzs_pagos_orden AS orden")
                ->join("eegr_compras AS buy", "orden.factura_compra", "=", "buy.id")
                ->where('buy.token_compras',$token_compras)
                ->get();

                foreach ($queryOrdenPagoCompras as $rOrdPag) {
                  $update_auth_true = DB::table("fnzs_pagos_orden")->where("token_ordenPago",$rOrdPag->token_ordenPago)
                  ->limit(1)->update(array(
                    "fecha_contabilizacion_ordenPago" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
                    "orden_bloqueada" => FALSE,
                    "autorizacion_pay" => TRUE,
                    "fecha_autorizacion_pay" => $fecha_sistema,
                    "orden_terminada_bool" => TRUE, 
                    "orden_terminada_fecha" => time()
                  ));
                  
                  //$acreedor = DB::table("fnzs_catalogo_acreedores")->where("token_cat_acreedores",$select_acreedor)->value("id");
                  $select_acreedor = DB::table("terc_reembolso_main AS reem")
                  ->join("fnzs_catalogo_acreedores AS catAcree", "reem.user_acreedor", "catAcree.id")
                  ->where("reem.token_reem",$tokenReembolso)->value("catAcree.id");

                  $queryPagoVinc = DB::table("fnzs_pagos_pago_ordenes_vinculadas AS vinc")
                  ->join("fnzs_pagos_orden AS order", "vinc.orden_pago_vinculada", "order.id")
                  ->join("fnzs_pagos_pago AS pay", "vinc.pago_realizado", "pay.id")
                  ->where([
                    "pay.pago_cancelado" => FALSE,
                    "order.token_ordenPago" => $rOrdPag->token_ordenPago
                  ])
                  ->get();
                  //echo "count(queryPagoVinc) ".count($queryPagoVinc);
                  if (count($queryPagoVinc) == 0) {
                    $folioPagos = DB::select("SELECT IF (max(folio_pagos) IS NOT NULL,(max(folio_pagos)+1),1) AS folio FROM fnzs_pagos_pago AS payment JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                      JOIN teci_usuarios_catalogo AS users WHERE payment.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                      [$usuario->empresa_token, $usuario->user_token]
                    );

                    $tokenPago = $JwtAuth->encriptarToken($order_importe.$order_tipo_cambio.$fecha_sistema);
                    $folio_pago_generar = "PAY-".$JwtAuth->generarFolio($folioPagos[0]->folio);

                    $insertPagoMon = DB::table("fnzs_pagos_pago")
                    ->insert(
                      array(
                        "token_pagos" => $tokenPago,
                        "folio_pagos" => $folioPagos[0]->folio,
                        "folio_operacion" => "",
                        "fecha_sistema" => $fecha_sistema,
                        "fecha_pago" => time(),
                        "fecha_contabilizacion" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
                        "monto_pago" => $order_importe,
                        "observacionesPago" => $JwtAuth->encriptar($observaciones),
                        "tipo_cambio" => $order_tipo_cambio,
                        "p_moneda" => $order_moneda,
                        "forma_pago_pago" => $fpago_clave,
                        "vinc_acreedor" => $select_acreedor ? $select_acreedor : NULL,
                        "compra" => $buy_compra_id,	
                        "reembolso_main" => $select_reembolso_main,	
                        "reembolso_solicitud" => $select_reem_soli,	
                        "concepto" => $JwtAuth->encriptar("Pago por concepto de reembolso"),	
                        //almacen Índice	int(10)			Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
                        "personal_pago" => $personal_main,
                        "pago_autorizado" => TRUE,
                        "fecha_pago_auth" => time(),
                        "personal_autoriza" => $personal_main,
                        "empresa" => $empresa_main,
                        "status_pagos" => TRUE,
                        "fecha_deletePagos" => ''
                      )
                    );

                    $id_pago_realizado = DB::table("fnzs_pagos_pago")->where("token_pagos",$tokenPago)->value("id");
                    $id_ord_pago = DB::table("fnzs_pagos_orden")->where("token_ordenPago",$rOrdPag->token_ordenPago)->value("id");

                    $insertPagoVinc = DB::table("fnzs_pagos_pago_ordenes_vinculadas")
                    ->insert(array("pago_realizado" => $id_pago_realizado,"orden_pago_vinculada" => $id_ord_pago,"orden_pago_monto" => $order_importe));
                  } else {
                    foreach ($queryPagoVinc as $pVinc) {
                      $insertPagoMon = DB::table("fnzs_pagos_pago")
                      ->where('token_pagos',$pVinc->token_pagos)
                      ->limit(1)->update(
                        array(
                          'vinc_acreedor' => $select_acreedor ? $select_acreedor : NULL,
                        )
                      );
                    }
                  }

                  $vhPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
                    ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
                    ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
                    ->where(["reem_main.token_reem" => $vReem->token_reem])->get();
                      
                  foreach ($vhPersEmpEmi as $vpVH) {
                    $titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem . " y solicitud: " . $folio_soli_reem . " fue aprobada satisfactoriamente para el pago correspondiente";
                    $JwtAuth->notificacionPushDevices($vpVH->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
                  }
                
                  $eegrPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
                    ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
                    ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
                    ->where(["reem_main.token_reem" => $vReem->token_reem])->get();
                
                  foreach ($eegrPersEmpEmi as $vpEGR) {
                    $titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem . " y solicitud: " . $folio_soli_reem . " fue aprobada satisfactoriamente para el pago correspondiente";
                    $JwtAuth->notificacionPushDevices($vpEGR->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
                  }
                
                  $selectPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
                    ->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
                    ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
                    ->where(["reem_main.token_reem" => $vReem->token_reem])->get();
                
                  foreach ($selectPersEmpEmi as $vPemi) {
                    $titulo_alerta = "Solicitud de reembolso registrada con el folio: " . $folio_reem . " y solicitud: " . $folio_soli_reem . " fue aprobada satisfactoriamente para el pago correspondiente";
                    $JwtAuth->notificacionPushDevices($vPemi->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
                  }
                }
              }

              $relacionCompras = DB::table("eegr_compras AS buy")
							->join("cfdi_vinculacion_compras AS vinc_buy", "buy.id", "=", "vinc_buy.compra_vinculada")
							->join("cfdi_comprobantes_fiscales AS cfd", "vinc_buy.comprobante_fiscal", "=", "cfd.id")
              ->where("buy.token_compras",$token_compras)->select("buy.folio_compra","cfd.cfdi_comprobante_total")->first();

              $factura_relacionada_string = $relacionCompras ? "COMP-".$JwtAuth->generarFolio($relacionCompras->folio_compra) : '';
              $importe_por_pagar = $relacionCompras ? $vBuy->cfdi_comprobante_total : '0.00';

              $mensaje_user = "Recibiste un pago del reembolso con folio $factura_relacionada_string por un total de: $$importe_por_pagar $order_moneda";
              $JwtAuth->notificacionPushDevices($vReem->usuario_token, "Revisión de solicitud de reembolso por egresos", $mensaje_user);
              $vhPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
                ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
                ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
                ->where(["reem_main.token_reem" => $vReem->token_reem])->get();

              foreach ($vhPersEmpEmi as $vpVH) {
                $JwtAuth->notificacionPushDevices($vpVH->usuario_token, "SOS-México - Portal para empleados", $mensaje_user); 
              }

              $eegrPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
                ->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
                ->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
                ->where(["reem_main.token_reem" => $vReem->token_reem])->get();

              foreach ($eegrPersEmpEmi as $vpEGR) {
                $JwtAuth->notificacionPushDevices($vpEGR->usuario_token, "SOS-México - Portal para empleados", $mensaje_user); 
              }

              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => '¡Pago realizado existosamente, revise su información y comuniquese con al área correspondiente al pago realizado!'
              );
						} else {
							$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Reembolso no autorizado por el departamento de valor humano');
						}
					}
				} else {
          $mensaje_error = '';
					if (!$validar_tokenReembolso) {$mensaje_error = 'Folio de reembolso incorrecto';}
					if (!$validar_tkn_solicitud) {$mensaje_error = 'La solicitud de reembolso es invalida';}
					if (!$validar_token_compras) {$mensaje_error = 'Error en Compra relacionada';}
					if (!$validar_fecha_contabilizacion) {$mensaje_error = 'Error en fecha de contabilización de compra relacionada';}
					if (!$validar_observaciones) {$mensaje_error = 'Error en observaciones';}
					$dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La informacion que intenta registrar no es valida'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function egr_reembolso_solicita_cancelacion_vinc(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"token_solicitud_reem" => "required|string",
				"token_compras" => "required|string",
				"observaciones" => "required|string"
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

				$fecha_sistema = time();
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$token_solicitud_reem = $parametrosArray["token_solicitud_reem"];
				$token_compras = $parametrosArray["token_compras"];
				$observaciones = $parametrosArray["observaciones"];
        //exit;

				$validar_tokenReembolso = isset($tokenReembolso) && !empty($tokenReembolso);
				$validar_token_solicitud_reem = isset($token_solicitud_reem) && !empty($token_solicitud_reem);
				$validar_token_compras = isset($token_compras) && !empty($token_compras);
				$validar_observaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

				if ($validar_tokenReembolso && $validar_token_solicitud_reem && $validar_token_compras && $validar_observaciones) {
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

          foreach ($queryEmp as $vEmp) {
						$fecha_registro = time();
						$folioCanc = DB::select("SELECT IF (max(canc.folio_cancel_reem) IS NOT NULL,(max(canc.folio_cancel_reem)+1),1) AS folio FROM terc_reembolsos_cancelaciones AS canc JOIN main_empresas AS emp 
							JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE canc.reem_cancel_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
							AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

						$id_reembolso = DB::table("terc_reembolso_main")->where(["borrador_reem" => FALSE,"token_reem" => $tokenReembolso])->value("id");
						$id_solicitud_reem = DB::table("terc_reembolso_solicitud")->where(["status_activacion" => TRUE,"token_solicitud_reem" => $token_solicitud_reem])->value("id");
						$id_compras = DB::table("eegr_compras")->where("token_compras",$token_compras)->value("id");
						//$validar_observaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);
						$token_cancel_reem = $JwtAuth->encriptarToken($fecha_registro,$id_reembolso,$id_solicitud_reem,$id_compras);

            $insertSoliReemCancelacion = DB::table("terc_reembolsos_cancelaciones")
            ->insert(
              array(
                "token_cancel_reem" => $token_cancel_reem,
                "folio_cancel_reem" => $folioCanc[0]->folio,
                "fecha_cancel_reem" => $fecha_sistema,
                "reem_cancel_main" => $id_reembolso,
                "reem_cancel_soli" => $id_solicitud_reem,
                "reem_cancel_compra_vinc" => $id_compras,
								"reem_cancel_observaciones_mov" => $JwtAuth->encriptar($observaciones),
                "reem_cancel_realizada" => FALSE,
                "reem_cancel_empresa" => $vEmp->id,
                "reem_cancel_status" => TRUE,
                //"reem_cancel_fecha_delete" => $order_moneda,
              )
            );
            if ($insertSoliReemCancelacion) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Solicitud de cancelación de pago del reembolso ha sido registrada con el folio REEM-CANC-'.$JwtAuth->generarFolio($folioCanc[0]->folio)
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'La solicitud de cancelación de pago del reembolso no fue terminada debido a errores internos, para mayor información comuniquese a soporte'
              );
            }
					}
				} else {
          $mensaje_error = '';
					if (!$validar_tokenReembolso) {$mensaje_error = 'Folio de reembolso incorrecto';}
					if (!$validar_token_solicitud_reem) {$mensaje_error = 'La solicitud de reembolso es invalida';}
					if (!$validar_token_compras) {$mensaje_error = 'Error en Compra relacionada';}
					if (!$validar_observaciones) {$mensaje_error = 'Error en observaciones';}
					$dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La informacion que intenta registrar no es valida'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function egr_reembolso_compras_auth(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayJust = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
				"tkn_solicitud" => "required|string",
				"autorizacion" => "required|boolean",
				"observaciones" => "required|string",
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

				$patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
				$patronFecha = '/^[0-9-]*$/';
				$patronNum = '/^[0-9$,.-]*$/';

				$fecha_sistema = time();
				$tokenReembolso = $parametrosArray["tokenReembolso"];
				$tkn_solicitud = $parametrosArray["tkn_solicitud"];
				$autorizacion = $parametrosArray["autorizacion"];
				$observaciones = $parametrosArray["observaciones"];

				$validacion_tokenReembolso = isset($tokenReembolso) && !empty($tokenReembolso);
				$validacion_tkn_solicitud = isset($tkn_solicitud) && !empty($tkn_solicitud);
				$validacion_autorizacion = isset($autorizacion) && is_bool($autorizacion);
				$validacion_observaciones = isset($observaciones) && !empty($observaciones) && preg_match($patron, $observaciones);

				if ($validacion_tokenReembolso && $validacion_tkn_solicitud && $validacion_autorizacion && $validacion_observaciones) {
					$list_reem = DB::table("terc_reembolso_main AS reem_main")
					->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
					->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
					->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
					->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
					->where("reem_main.token_reem",$tokenReembolso)
					->where("reem_soli.token_solicitud_reem",$tkn_solicitud)
					->where("reem_soli.status_activacion",TRUE)
					->where("reem_main.status_reem",TRUE)
					->where("reem_main.borrador_reem",FALSE)
					->where("emp.empresa_token",$usuario->empresa_token)
					->get();

					foreach ($list_reem as $vReem) {
						if ($vReem->autorizacion_vh == "N" || $vReem->autorizacion_vh == "A") {
							date_default_timezone_set($vReem->zona_horaria);
							$auth_bd = "A";
							if ($autorizacion == false) $auth_bd = "D";

							$folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem).(!is_null($vReem->post_folio_reem) ? '-'.$vReem->post_folio_reem : '');
							$folio_soli_reem = $JwtAuth->generarFolio($vReem->folio_solicitud);

							$update_auth_true = DB::table("terc_reembolso_main AS reem_main")
							->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
							->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
							->where(["reem_main.token_reem" => $vReem->token_reem, "reem_soli.token_solicitud_reem" => $vReem->token_solicitud_reem, "reem_main.status_reem" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
							->limit(1)->update(array("reem_soli.autorizacion_egr" => $auth_bd));

							if ($update_auth_true) {
								$select_reembolso_main = DB::select("SELECT id FROM terc_reembolso_main WHERE token_reem = ?", [$tokenReembolso]);
								$select_reem_soli = DB::select("SELECT id FROM terc_reembolso_solicitud WHERE token_solicitud_reem = ?", [$tkn_solicitud]);

								$all_soli_reem = DB::table("terc_reembolso_main AS reem_main")
								->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
								->where(["reem_main.token_reem" => $vReem->token_reem])->get();

								$approv_soli_reem = DB::table("terc_reembolso_main AS reem_main")
								->join("terc_reembolso_solicitud AS reem_soli", "reem_main.id", "=", "reem_soli.reembolso_main")
								->where(["reem_main.token_reem" => $vReem->token_reem, "reem_soli.autorizacion_egr" => "A"])
								->orwhere(["reem_main.token_reem" => $vReem->token_reem, "reem_soli.autorizacion_egr" => "D"])->get();

								if (count($approv_soli_reem) == count($all_soli_reem)) {
									DB::table("terc_reembolso_main")->where(["token_reem" => $vReem->token_reem])->limit(1)->update(array("last_revision_egr" => time()));
								}

								$mensaje_sistema = "Solicitud de reembolso con folio $folio_reem y subfolio $folio_soli_reem fue aprobada satisfactoriamente";
								$mensaje_user = "Solicitud de reembolso con folio $folio_reem y subfolio $folio_soli_reem fue aprobada";

								$token_auth = $JwtAuth->encriptarToken(time(), $tokenReembolso . $tkn_solicitud . $autorizacion . $observaciones . time() - 500);

								$select_folio_auth_egr = DB::select("SELECT r_auth.id FROM terc_reembolso_autorizacion_egr AS r_auth JOIN terc_reembolso_main AS r_main JOIN terc_reembolso_solicitud AS s_soli
									WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?",[$tokenReembolso, $tkn_solicitud]);

								if (count($select_folio_auth_egr) == 0) {
									$folio_auth = 1;
								} else {
									$select_folio_auth_egr = DB::select("SELECT folio_auth_reem FROM terc_reembolso_autorizacion_egr WHERE id = (SELECT MAX(r_auth.id) FROM terc_reembolso_autorizacion_egr AS r_auth JOIN terc_reembolso_main AS r_main 
										JOIN terc_reembolso_solicitud AS s_soli WHERE r_auth.reembolso_main = r_main.id AND r_main.token_reem = ? AND r_auth.reembolso_solicitud = s_soli.id AND s_soli.token_solicitud_reem = ?)",[$tokenReembolso, $tkn_solicitud]);
									$folio_auth = $select_folio_auth_egr[0]->folio_auth_reem + 1;
								}

								$insertEquipo = DB::table('terc_reembolso_autorizacion_egr')
								->insert(
									array(
										"token_auth_reem" => $token_auth,
										"folio_auth_reem" => $folio_auth,
										"fecha_registro" => time(),
										"reembolso_main" => $select_reembolso_main[0]->id,
										"reembolso_solicitud" => $select_reem_soli[0]->id,
										"autorizacion_egr" => $auth_bd,
										"comentarios" => $JwtAuth->encriptar($observaciones),
									)
								);

								$JwtAuth->notificacionPushDevices($vReem->usuario_token, "Revisión de solicitud de reembolso por egresos", $mensaje_user);

								$vhPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
								->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
								->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
								->where(["reem_main.token_reem" => $vReem->token_reem])->get();

								foreach ($vhPersEmpEmi as $vpVH) {
									$JwtAuth->notificacionPushDevices($vpVH->usuario_token, "SOS-México - Portal para empleados", $mensaje_user);
								}

								$eegrPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
								->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
								->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
								->where(["reem_main.token_reem" => $vReem->token_reem])->get();

								foreach ($eegrPersEmpEmi as $vpEGR) {
									$JwtAuth->notificacionPushDevices($vpEGR->usuario_token, "SOS-México - Portal para empleados", $mensaje_user);
								}

								$dataMensaje = array('message' => $mensaje_sistema,'code' => 200,'status' => 'success');
							} else {
								$dataMensaje = array('status' => 'error','code' => 200,'message' => 'Error en autorización');
							}
						} else {
							$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Reembolso no autorizado por el departamento de valor humano');
						}
					}
				} else {
					$alerta_mensaje = '';
					if (!$validacion_tokenReembolso) {$alerta_mensaje = 'Folio de reembolso incorrecto';}
					if (!$validacion_tkn_solicitud) {$alerta_mensaje = 'La solicitud de reembolso es invalida';}
					if (!$validacion_autorizacion) {$alerta_mensaje = 'Error en validación de autorización';}
					if (!$validacion_observaciones) {$alerta_mensaje = 'Error en observaciones';}
					$dataMensaje = array('status' => 'error','code' => 200,'message' => $alerta_mensaje);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La informacion que intenta registrar no es valida'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function egr_reembolso_compras_genera_orden_pago(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayJust = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"tokenReembolso" => "required|string",
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

				$patron = '/[0-9A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()\/\%&$¡!¨*]/';
				$patronFecha = '/^[0-9-]*$/';
				$patronNum = '/^[0-9$,.-]*$/';

				$fecha_sistema = time();
				$tokenReembolso = $parametrosArray["tokenReembolso"];

				$validacion_tokenReembolso = isset($tokenReembolso) && !empty($tokenReembolso);
				if ($validacion_tokenReembolso) {
					$queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp  
          JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
          AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

					foreach ($queryEmp as $vEmp) {
						$list_reem = DB::table("terc_reembolso_main AS reem_main")
						->join("main_empresas AS emp", "reem_main.receptor", "=", "emp.id")
						->join("vhum_empleados_catalogo AS pers", "reem_main.user_emisor", "=", "pers.id")
						->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
						->where("reem_main.token_reem",$tokenReembolso)
						->where("reem_main.status_reem",TRUE)
						->where("reem_main.borrador_reem",FALSE)
						->where("emp.empresa_token",$usuario->empresa_token)->get();
						foreach ($list_reem as $vReem) {
							$folio_reem = 'REEM-'.$JwtAuth->generarFolio($vReem->folio_reem).(!is_null($vReem->post_folio_reem) ? '-'.$vReem->post_folio_reem : '');
							$select_reembolso_main = DB::table("terc_reembolso_main")->where("token_reem",$tokenReembolso)->value("id");

							$termina_comission = DB::table("terc_comisiones_main AS comi_main")
							->join("sos_reembolsos_comisiones_rel AS comi_reem", "comi_main.id", "=", "comi_reem.comision")
							->join("terc_reembolso_main AS reem_main", "comi_reem.reembolso_main", "=", "reem_main.id")
							->where(["reem_main.token_reem" => $tokenReembolso])
							->limit(1)->update(array("comi_main.concluida" => TRUE, "comi_main.concluida_fecha" => time()));


							$folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP JOIN main_empresas AS emp 
								JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
								AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

							$token_orden = $JwtAuth->encriptarToken(time(), $folioOrden[0]->folio, $select_reembolso_main);
							$orderpay = new OrdenPagoModelo();
							$orderpay->token_ordenPago = $token_orden;
							$orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
							$orderpay->fecha_sistema_ordenp = time();
							$orderpay->reembolso_main = $select_reembolso_main;
							//$orderpay->reembolso_solicitud = $select_reem_soli[0]->id;
							//$orderpay->tentativa_pago = time()+(86400*3);
							$orderpay->tentativa_pago = time() + (259200);
							$orderpay->status_ordenPago = TRUE;  //cifrado
							$orderpay->doc_anterior_fecha_contabilizacion = DB::table("terc_reembolso_main")->where("token_reem",$tokenReembolso)->value("fecha_sistema");
							//$orderpay->fecha_delete_ordenPago = '';  //cifrado
							//$orderpay->status_pago = FALSE; //cifrado
							$orderpay->autorizacion_pay = FALSE;
							$orderpay->empresa = $vEmp->id;    //cifrado
							$orderpay->comprador = $vEmp->userr; //cifrado
							$insertOrder = $orderpay->save();
							$mensaje_sistema = "Solicitud de reembolso con folio $folio_reem fue aprobada satisfactoriamente, orden de pago generada con el folio: " . $JwtAuth->generarFolio($folioOrden[0]->folio);
							$mensaje_user = "Solicitud de reembolso con folio $folio_reem fue aprobada, orden de pago generada con el folio: " . $JwtAuth->generarFolio($folioOrden[0]->folio);

							$JwtAuth->notificacionPushDevices($vReem->usuario_token, "Revisión de solicitud de reembolso por egresos", $mensaje_user);
							$vhPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_vh", "=", "pers.id")
							->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
							->where("reem_main.token_reem",$vReem->token_reem)
							->select("users.usuario_token")
							->first();
							$vhPersEmpEmi ? $JwtAuth->notificacionPushDevices($vhPersEmpEmi->usuario_token, "SOS-México - Portal para empleados", $mensaje_user) : null;

							$eegrPersEmpEmi = DB::table("terc_reembolso_main AS reem_main")
							->join("vhum_empleados_catalogo AS pers", "reem_main.user_receptor_egr", "=", "pers.id")
							->join("teci_usuarios_catalogo AS users", "pers.id", "=", "users.empleado")
							->where("reem_main.token_reem",$vReem->token_reem)
							->select("users.usuario_token")
							->first();
							$eegrPersEmpEmi ? $JwtAuth->notificacionPushDevices($eegrPersEmpEmi->usuario_token, "SOS-México - Portal para empleados", $mensaje_user) : null;

							$dataMensaje = array(
								'message' => $mensaje_sistema,
								'code' => 200,
								'status' => 'success'
							);
						}
					}

				} else {
					$alerta_mensaje = '';
					if (!$validacion_tokenReembolso) {$alerta_mensaje = 'Folio de reembolso incorrecto';}
					$dataMensaje = array('status' => 'error','code' => 200,'message' => $alerta_mensaje);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La informacion que intenta registrar no es valida'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}
}
