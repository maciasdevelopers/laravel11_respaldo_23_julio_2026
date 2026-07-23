<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\ComprasModelo;
use App\Models\OrdenPagoModelo;
use App\Models\OrdenRecepcionModelo;

class EGRE_ComprasRegistroReembolsoController extends Controller{
  private function registrarCompraValidaConceptos($cfdi_conceptos,$moneda_decimales,$JwtAuth){
    $detalleErrores = "";
    foreach ($cfdi_conceptos as $vDet) {
      $tokenArticulo = $vDet['articulo_homologado_token'];
      $identificador = $vDet['articulo_homologado_identificador'];
      $concepto = $vDet['Descripcion'];
      $precioUnitario = $vDet['ValorUnitario'];
      $cantidad = $vDet['Cantidad'];
      //return response()->json(['status' => 'error','code' => 200,'message' => $cantidad]);
      $descuentoXUni = $vDet['Descuento'];
      $iva = $vDet['articulo_homologado_iva'];
      $retenciones = $vDet['retenciones'];
      $traslados = $vDet['traslados'];
      $usoArticulo = $vDet['articulo_homologado_uso'];
      $efectoFiscalArticulo = $vDet['articulo_homologado_efecto_fiscal'];
      $activoFijo = $vDet['articulo_homologado_activoFijo'];
      $activoIntangible = $vDet['articulo_homologado_activoDiferido'];
      $prorratea = $vDet['articulo_homologado_prorratea'];

      $importe = $JwtAuth->rellenaImportesCompras($vDet['Importe']);
      $validateActivos = false;
      $validatePeriodicidad = false;
      $validateDescuentos = false;
      $validateDecimalesMoneda = false;
      $validateForImpuRetenciones = false;
      $validateForImpuTraslados = false;

      $vItem_tokenArticulo = isset($tokenArticulo) && !empty($tokenArticulo);
      $vItem_identificador = isset($identificador) && !empty($identificador) && preg_match($JwtAuth->filtroAlfaNumerico(), $identificador);//$JwtAuth->filtroFecha()
      $vItem_precioUnitario = isset($precioUnitario) && !empty($precioUnitario) && preg_match($JwtAuth->filtroCostoPrecio(), $precioUnitario);
      $vItem_cantidad = isset($cantidad) && !empty($cantidad) && preg_match($JwtAuth->filtroCostoPrecio(), $cantidad);
      //&& isset($iva) && !empty($iva) && preg_match($patrónNumCosto,$iva)
      $vItem_usoArticulo = isset($usoArticulo) && !empty($usoArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $usoArticulo);
      $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
      //$vItem_periodicidadPc = isset($periodicidadPc) && !empty($periodicidadPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $periodicidadPc);
      $vItem_importe = isset($importe) && !empty($importe) && preg_match($JwtAuth->filtroCostoPrecio(), $importe);

      if ($vItem_tokenArticulo && $vItem_identificador && $vItem_precioUnitario && $vItem_cantidad && $vItem_usoArticulo /*&& $vItem_periodicidadPc*/ && $vItem_importe) {
        if (isset($descuentoXUni) && !empty($descuentoXUni)) {
          if ($descuentoXUni != '---') {
            if (preg_match($JwtAuth->filtroCostoPrecio(), $descuentoXUni)) {
              $strPosdescuentoXUni = strpos($descuentoXUni, '.');
              if ($strPosdescuentoXUni !== FALSE) {
                $expdescuentoXUni = explode('.', $descuentoXUni);
                if ($moneda_decimales == strlen($expdescuentoXUni[1])) {
                  $validateDescuentos = true;
                } else {
                  $validateDescuentos = false;
                  $detalleErrores = 'La cantidad de decimales del descuento no coincide con los decimales que soporta la moneda seleccionada';
                }
              } else {
                $validateDescuentos = false;
                $detalleErrores = 'La cantidad de decimales se encuentra precio unitario, descuento, importe no coincide con los decimales que soporta la moneda seleccionada';
              }
            } else {
              $validateDescuentos = false;
              $detalleErrores = 'Descuento invalido';
            }
          } else {
            $validateDescuentos = true;
          }
        } else {
          $validateDescuentos = false;
          $detalleErrores = 'La cantidad de descuento es invalida o inexistente';
        }

        if ($moneda_decimales != 0) {
          $strPosPrecioUnit = strpos($precioUnitario, '.');
          $strPosimporte = strpos($importe, '.');

          if ($strPosPrecioUnit !== FALSE && $strPosimporte !== FALSE) {
            $expUnitPrecio = explode('.', $precioUnitario);
            $expimporte = explode('.', $importe);

            if ((strlen($expUnitPrecio[1]) == 6 || strlen($expUnitPrecio[1]) == $moneda_decimales) &&
              (strlen($expimporte[1]) == 6 || strlen($expimporte[1]) == $moneda_decimales)
            ) {
              $validateDecimalesMoneda = true;
            } else {
              $validateDecimalesMoneda = false;
              $detalleErrores = 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada';
            }
          } else {
            $validateDecimalesMoneda = false;
            $detalleErrores = 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada';
          }
        }

        if ($moneda_decimales == 0) {
          $strPosPrecioUnit = strpos($precioUnitario, '.');
          $strPosimporte = strpos($importe, '.');
          if ($strPosPrecioUnit !== FALSE && $strPosimporte !== FALSE) {
            $validateDecimalesMoneda = false;
            $detalleErrores = 'El precio unitario del producto/servicio no tiene decimales';
          } else {
            $validateDecimalesMoneda = true;
          }
        }

        if ($usoArticulo == 'activo_fijo') {
          if (isset($activoFijo) && !empty($activoFijo) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoFijo)) {
            $validateActivos = true;
          } else {
            $validateActivos = false;
            $detalleErrores = 'El activo del producto/servicio '.$concepto.' es invalido o inexistente';
            break;
          }
        } else if ($usoArticulo == 'activo_diferido') {
          if (isset($activoIntangible) && !empty($activoIntangible) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoIntangible)) {
            $validateActivos = true;
          } else {
            $validateActivos = false;
            $detalleErrores = 'El descuento del producto/servicio '.$concepto.' es invalido o inexistente';
            break;
          }
        } else {
          $validateActivos = true;
        }

        if (count($retenciones) != 0) {
          $countValidateRetencionesConcept = 0;
          for ($t = 0; $t < count($retenciones); $t++) {
            $base = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Base"]);
            $explodeBase = explode('.', $base);
            $impuesto = $retenciones[$t]["Impuesto"];
            $tipoFactor = $retenciones[$t]["TipoFactor"];
            $TasaOCuota = $retenciones[$t]["TasaOCuota"] ?? null;
            $importe = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Importe"]);
            $importe = $retenciones[$t]["TipoFactor"] != "Exento" || (isset($retenciones[$t]["Importe"]) && $retenciones[$t]["Importe"] != 0) ? $JwtAuth->rellenaImportesCompras($retenciones[$t]["Importe"]) : "0.00";
            //return response()->json(['message' => $retenciones[$t]["Importe"],'codigo' => 200,'status' => 'error']);
            $explodeImporte = explode('.', $importe);

            $OKRetImpuesto = isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3;
            $OKRetTipoFactor = isset($tipoFactor) && !empty($tipoFactor);
            $OKRetTasaOCuota = ($tipoFactor === "Exento") ? true : (isset($TasaOCuota) && !empty($TasaOCuota));
            $OKRetImporte = isset($importe) && !empty($importe) && (strlen($explodeImporte[1]) == 6 || strlen($explodeImporte[1]) == $moneda_decimales);
            if ($OKRetImpuesto && $OKRetTipoFactor && $OKRetTasaOCuota && $OKRetImporte) {
              if (isset($base)) {
                if (!empty($base) && (strlen($explodeBase[1]) == 6 || strlen($explodeBase[1]) == $moneda_decimales)) {
                  ++$countValidateRetencionesConcept;
                } else {
                  $detalleErrores = 'Base de retención del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida';
                  break;
                }
              } else {
                ++$countValidateRetencionesConcept;
              }
              //return response()->json(['message' => $base,'codigo' => 200,'status' => 'error']);
            } else {
              if (!$OKRetImpuesto) {
                $detalleErrores = 'Impuesto de retención del producto/servicio '.$concepto.' no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)';
                break;
              }
              if (!$OKRetTipoFactor) {
                $detalleErrores = 'TipoFactor de retención del producto/servicio '.$concepto.' no existe o esta vacio';
                break;
              }
              if (!$OKRetTasaOCuota) {
                $detalleErrores = 'TasaOCuota de retención del producto/servicio '.$concepto.' no existe o esta vacio';
                break;
              }
              if (!$OKRetImporte) {
                $detalleErrores = 'Importe de retención del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida';
                break;
              }
            }
          }

          if ($countValidateRetencionesConcept == count($retenciones)) {
            $validateForImpuRetenciones = true;
          }
        } else {
          $validateForImpuRetenciones = true;
        }

        if (count($traslados) != 0) {
          $countValidateTrasladosConcept = 0;
          for ($t = 0; $t < count($traslados); $t++) {
            $base = $JwtAuth->rellenaImportesCompras($traslados[$t]["Base"]);
            $explodeBase = explode('.', $base);
            $impuesto = $traslados[$t]["Impuesto"];
            //return response()->json(['message' => $impuesto,'codigo' => 200,'status' => 'error']);
            $tipoFactor = $traslados[$t]["TipoFactor"];
            $TasaOCuota = $traslados[$t]["TasaOCuota"] ?? null;
            $importe = $traslados[$t]["TipoFactor"] != "Exento" || (isset($traslados[$t]["Importe"]) && $traslados[$t]["Importe"] != 0) ? $JwtAuth->rellenaImportesCompras($traslados[$t]["Importe"]) : "0.00";
            $explodeImporte = explode('.', $importe);

            $OKTrasImpuesto = isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3;
            $OKTrasTipoFactor = isset($tipoFactor) && !empty($tipoFactor);
            $OKTrasTasaOCuota = ($tipoFactor === "Exento") ? true : (isset($TasaOCuota) && !empty($TasaOCuota));
            $OKTrasImporte = isset($importe) && !empty($importe) && (strlen($explodeImporte[1]) == 6 || strlen($explodeImporte[1]) == $moneda_decimales);
            if ($OKTrasImpuesto && $OKTrasTipoFactor && $OKTrasTasaOCuota && $OKTrasImporte) {
              if (isset($base)) {
                //return response()->json(['message' => strlen($explodeBase[1]).' == '.$moneda_decimales,'codigo' => 200,'status' => 'error']);
                if (!empty($base) && (strlen($explodeBase[1]) == 6 || strlen($explodeBase[1]) == $moneda_decimales)) {
                  ++$countValidateTrasladosConcept;
                } else {
                  $detalleErrores = 'Base de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida';
                  break;
                }
              } else {
                ++$countValidateTrasladosConcept;
              }
            } else {
              if (!$OKTrasImpuesto) {
                $detalleErrores = 'Impuesto de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)';
                break;
              }
              if (!$OKTrasTipoFactor) {
                $detalleErrores = 'TipoFactor de traslado del producto/servicio '.$concepto.' no existe o esta vacio';
                break;
              }
              if (!$OKTrasTasaOCuota) {
                $detalleErrores = 'TasaOCuota de traslado del producto/servicio '.$concepto.' no existe o esta vacio';
                break;
              }
              if (!$OKTrasImporte) {
                $detalleErrores = 'Importe de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida (' . $moneda_decimales . ')';
                break;
              }
            }
          }
          if ($countValidateTrasladosConcept == count($traslados)) {
            $validateForImpuTraslados = true;
          }
        } else {
          $validateForImpuTraslados = true;
        }
      } else {
        if (!$vItem_tokenArticulo) {$detalleErrores = 'producto/servicio '.$concepto.' invalidado';}
        if (!$vItem_identificador) {$detalleErrores = 'identificador del producto/servicio '.$concepto.' es incorrecto o inexistente';}
        if (!$vItem_precioUnitario) {$detalleErrores = 'El precio unitario del producto/servicio '.$concepto.' es invalido o inexistente';}
        if (!$vItem_cantidad) {$detalleErrores = 'La cantidad del producto/servicio '.$concepto.' es invalida o inexistente';}
        if (!$vItem_usoArticulo) {$detalleErrores = 'El uso del producto/servicio '.$concepto.' es invalido o inexistente';}
        if (!$vItem_importe) {$detalleErrores = 'El importe del producto/servicio '.$concepto.' es invalido o inexistente';}
        break;
      }
    }
    return $detalleErrores;
  }

  private function registraAnticipoCompra($JwtAuth,$token_proveedor,$emp_id,$usuario,$user_id,$anticipo_aplicado,$compra_observaciones,$fecha_contabilizacion,$cfdi_comprobante_tipo_de_cambio,$cfdi_comprobante_moneda,$orden_de_pago_vinculada){
    $ident_deudor = DB::table("fnzs_catalogo_deudores AS catdeu")
    ->join("eegr_catalogo_proveedores AS catprov", "catdeu.proveedor_deudor", "=", "catprov.id")
    ->where("catprov.token_cat_proveedores",$token_proveedor)->value("catdeu.id");
  
    $id_pago_realizado = DB::table("fnzs_pagos_pago AS pag")
    ->join("fnzs_catalogo_deudores AS catdeu", "pag.vinc_deudor", "=", "catdeu.proveedor_deudor")
    ->join("eegr_catalogo_proveedores AS catprov", "catdeu.proveedor_deudor", "=", "catprov.id")
    ->where("catprov.token_cat_proveedores",$token_proveedor)
    ->where("pag.concepto",$JwtAuth->encriptar("Pago por concepto de anticipo")) 
    ->orderBy("pag.fecha_sistema", "asc")
    ->select("pag.id")
    ->first();
  
    $folioMovimientos = DB::select("SELECT IF (max(deumov.folio_deu_mov) IS NOT NULL,(max(deumov.folio_deu_mov)+1),1) AS folio FROM fnzs_catalogo_deudores_movimientos AS deumov JOIN main_empresas AS emp 
      JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE deumov.deu_empresa = emp.id AND emp.id = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
      [$emp_id, $usuario]
    );
  
    $tokenMov = $JwtAuth->encriptarToken($anticipo_aplicado.$compra_observaciones.time());
    $folio_pago_generar = "DEUMOV-".$JwtAuth->generarFolio($folioMovimientos[0]->folio);
  
    $insertPagoMon = DB::table("fnzs_catalogo_deudores_movimientos")
    ->insert(
      array(
        "token_deu_mov" => $tokenMov,
        "folio_deu_mov" => $folioMovimientos[0]->folio,
        "deu_fecha_registro" => time(),
        "deu_fecha_contabilizacion" => $fecha_contabilizacion != "" ? $JwtAuth->convierteFechaEpoc($fecha_contabilizacion) : NULL,
        //"orden_pago_vinculada" => DB::table("fnzs_pagos_orden")->where("token_ordenPago",$orden_de_pago_vinculada)->value("id"),
        "condicion_deu_mov" => "R",
        "deu_monto_mov" => $anticipo_aplicado,
        "deu_observaciones_mov" => $JwtAuth->encriptar($compra_observaciones),
        "deu_tipo_cambio" => $cfdi_comprobante_tipo_de_cambio,
        "deu_mov_moneda" => $cfdi_comprobante_moneda,
        "vinc_deudor" => $ident_deudor,
        "deu_personal_mov" => $user_id,
        "deu_mov_autorizado" => TRUE,
        "deu_fecha_mov_auth" => time(),
        "deu_personal_autoriza" => $user_id,
        "deu_empresa" => $emp_id,
        "deu_status_mov" => TRUE,
      )
    );
    $id_mov_realizado = DB::table("fnzs_catalogo_deudores_movimientos")->where("token_deu_mov",$tokenMov)->value("id");
    
    $insertMovVincPagosOrden = DB::table("fnzs_catalogo_deudores_movimientos_ordenpay_vinculo")
    ->insert(array("mov_realizado" => $id_mov_realizado,"orden_pago" => $orden_de_pago_vinculada));
  
    $insertPagoVinc = DB::table("fnzs_catalogo_deudores_movimientos_pagos_vinculados")
    ->insert(array(
      "mov_realizado" => $id_mov_realizado,
      "pago_vinculado" => $id_pago_realizado,
      "mov_pago_monto" => $anticipo_aplicado
    ));

    if (!$insertPagoMon || !$insertMovVincPagosOrden || !$insertPagoVinc) {
      throw new \Exception("No se pudo registrar el movimiento en a deudores.");
    }
  }

  private function registraArticuloCompra($JwtAuth,$detBuy,$emp_id,$obtenCompra,$cfdi_comprobante_moneda,$cfdi_comprobante_tipo_de_cambio){
    $validUpdtProd = false;
    $validUpdtServ = false;
    $NoIdentificacion = $detBuy['NoIdentificacion'];
    $ObjetoImp = $detBuy['ObjetoImp'];
    $ClaveProdServ = $detBuy['ClaveProdServ'];
    $tokenArticulo = $detBuy['articulo_homologado_token'];
    $identificador = $detBuy['articulo_homologado_identificador'];
    $concepto = $detBuy['Descripcion'];
    $precioUnitario = $detBuy['ValorUnitario'];
    $cantidad = $detBuy['Cantidad'];
    $ClaveUnidad = $detBuy['ClaveUnidad'];
    $Unidad = $detBuy['Unidad'];
    $descuentoXUni = $detBuy['Descuento'];
    $total_descuento = $descuentoXUni != '' && $descuentoXUni != '---' && $descuentoXUni != '0.00' ? $descuentoXUni : '0.00';
    $iva = $detBuy['articulo_homologado_iva'];
    $retenciones = $detBuy['retenciones'];
    $TotalRetenciones = $detBuy['TotalRetenciones'];
    $traslados = $detBuy['traslados'];
    $TotalTraslados = $detBuy['TotalTraslados'];
    $Subtotal = $detBuy['Subtotal'];
    $usoArticulo = $detBuy['articulo_homologado_uso'];
    $efectoFiscalArticulo = $detBuy['articulo_homologado_efecto_fiscal'];
    $alm_serie = $detBuy['articulo_homologado_serie_token'];
    $alm_lote = $detBuy['articulo_homologado_lote_token'];
    $alm_pedimento = $detBuy['articulo_homologado_pedimento_token'];
    $activoFijo = $detBuy['articulo_homologado_activoFijo'];
    $activoIntangible = $detBuy['articulo_homologado_activoDiferido'];
    $prorratea = $detBuy['articulo_homologado_prorratea'];
    
    $importe = $detBuy['Importe'];
    $token_unidad_medida = $detBuy['Unidad'];
  
    //$token_producto = '';
    //$token_servicio = '';
    //$activos_fijos = '';
    //$activos_intangibles = '';
    $pedimento_aduanal = NULL;
    $boolprorratea = FALSE;
  
    $catProdServ = DB::table(DB::raw('(SELECT
        CASE
            WHEN ? IN (SELECT token_cat_productos FROM in_egr_catalogo_productos WHERE status = TRUE AND admin_empresa = ?) THEN "Producto"
            WHEN ? IN (SELECT token_cat_servicios FROM in_egr_catalogo_servicios WHERE status = TRUE AND administrador = ?) THEN "Servicio"
        END AS identificador) AS subconsulta'))
    ->setBindings([$tokenArticulo, $emp_id, $tokenArticulo, $emp_id])
    ->value("identificador");

    // 3. Obtención de IDs reales
    $id_producto = ($catProdServ == 'Producto') ? DB::table("in_egr_catalogo_productos")->where("token_cat_productos", $tokenArticulo)->value("id") : NULL;
    $id_servicio = ($catProdServ != 'Producto') ? DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios", $tokenArticulo)->value("id") : NULL;
    $id_activo_fijo = ($usoArticulo == 'activo_fijo') ? DB::table("eegr_activos_fijos_catalogo")->where("token_act_fijos", $detBuy['articulo_homologado_activoFijo'])->value("id") : NULL;
    $id_activo_intangible = ($usoArticulo == 'activo_diferido') ? DB::table("eegr_activos_intangibles_catalogo")->where("token_act_intang", $detBuy['articulo_homologado_activoDiferido'])->value("id") : NULL;
  
    $tokenDetalleCompra = $JwtAuth->encriptarToken(time().$id_producto.$id_servicio.$tokenArticulo.$identificador.$concepto.$precioUnitario.$cantidad.$total_descuento.$iva.$usoArticulo.$alm_serie.
      $alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$importe);
  
    $total_retenciones = collect($retenciones)->sum(function($r) { return ($r['TipoFactor'] != 'Exento') ? ($r['Importe'] ?? 0) : 0; });
    $total_traslados = collect($traslados)->sum(function($t) { return ($t['TipoFactor'] != 'Exento') ? ($t['Importe'] ?? 0) : 0; });
  
    $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
    
    $id_compra_detalle = DB::table('eegr_compras_detalle')
    ->insertGetId([
      'token_detcompra'               => $tokenDetalleCompra,
      'numero_compra'                 => $obtenCompra,
      'concepto_cfdi'                 => $JwtAuth->encriptar($concepto),
      'producto'                      => $id_producto,
      'servicio'                      => $id_servicio,
      'moneda_detalle_compra'         => $cfdi_comprobante_moneda,
      'tipo_de_cambio_detalle_compra' => $cfdi_comprobante_tipo_de_cambio,
      'precio_unitario'               => $precioUnitario,
      'cantidad'                      => $cantidad,
      'unidad_medida'                 => $token_unidad_medida,
      'descuento'                     => $total_descuento,
      'retenciones_total'             => $total_retenciones,
      //'retencion_homologada'          => $rete_homologada,
      'traslados_total'               => $total_traslados,
      //'traslado_homologado'           => $tras_homologado,
      'destino'                       => $usoArticulo,
      'efecto_fiscal'                 => $vItem_efectoFiscalArticulo ? $efectoFiscalArticulo : NULL,
      'activo_fijo'                   => $id_activo_fijo,
      'activo_intangible'             => $id_activo_intangible,
      'prorrateo'                     => $prorratea ? TRUE : FALSE,
      'empresa'                       => $emp_id
    ]);
    
    if (!$id_compra_detalle) {
      throw new \Exception("No se pudo generar el registro de detalle para: " . $detBuy['Descripcion']);
    }

    $this->procesarImpuestos($id_compra_detalle, $retenciones ?? [], 'rete', $JwtAuth);
    $this->procesarImpuestos($id_compra_detalle, $traslados ?? [], 'tras', $JwtAuth);
    
    return $id_compra_detalle;
  }

  private function procesarImpuestos($idDetalle, $impuestos, $tipo, $JwtAuth) {
    if (empty($impuestos)) return;
    $dataToInsert = [];
    foreach ($impuestos as $imp) {
      $impRelacionado = $imp["impuesto_relacionado"] ?? "";
      $idHomonimo = null;

      // 1. Mejora: Validación mínima antes de consultar
      if (!empty($impRelacionado)) {
        $idHomonimo = DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $impRelacionado)->value("id");  
        // Opcional: Si el token existe pero no halló ID, podrías lanzar excepción
        if (!$idHomonimo) {
          throw new \Exception("El impuesto homologado con token {$impRelacionado} no existe.");
        }
      }

      // 2. Preparar el array para inserción masiva (Bulk Insert)
      $dataToInsert[] = [
        "token_imp_det_buy"    => $JwtAuth->encriptarToken(time() . uniqid() . $idDetalle),
        "detalle_compra"       => $idDetalle, 
        "retencion_traslado"   => $tipo, // 'rete' o 'tras'
        "base"                 => $imp["Base"] ?? 0.00,
        "impuesto"             => $imp["Impuesto"] ?? '000',
        "tipo_factor"          => $imp["TipoFactor"] ?? null,
        "tasa_cuota"           => $imp["TasaOCuota"] ?? null,
        "importe"              => $imp["Importe"] ?? 0.00,
        "impuesto_relacionado" => $idHomonimo,
          //"created_at"           => now() // Recomendado si usas timestamps
      ];
    }

    // 3. Inserción masiva: Una sola ejecución de SQL para todos los impuestos
    if (!empty($dataToInsert)) {
      $inserted = DB::table('eegr_compras_detalle_impuestos')->insert($dataToInsert);

      if (!$inserted) {
        throw new \Exception("Error crítico al registrar el bloque de impuestos de tipo: " . $tipo);
      }
    }
  }

  private function registraArticuloCFDICompra($detBuy,$obtenCompra,$comprobante_fiscal_reg,$emp_id){
    $retenciones = $detBuy['retenciones'];
    $traslados = $detBuy['traslados'];
    
    $uuid_cfdi_detalle = Str::uuid()->toString();
    $insertDetCFDICompra = DB::table('cfdi_comprobantes_conceptos')
    ->insert(array(
      "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
      "comprobante_fiscal" => $comprobante_fiscal_reg,
      "NoIdentificacion" => $detBuy['NoIdentificacion'],
      "ObjetoImp" => $detBuy['ObjetoImp'],
      "ClaveProdServ" => $detBuy['ClaveProdServ'],
      "Cantidad" => $detBuy['Cantidad'],
      "ClaveUnidad" => $detBuy['ClaveUnidad'],
      "Unidad" => $detBuy['Unidad'],
      "Descripcion" => $detBuy['Descripcion'],
      "ValorUnitario" => $detBuy['ValorUnitario'],
      "Descuento" => $detBuy['Descuento'],
      "Importe" => $detBuy['Importe'],
      "TotalRetenciones" => $detBuy['TotalRetenciones'],
      "TotalTraslados" => $detBuy['TotalTraslados'],
      "Subtotal" => $detBuy['Subtotal'],
      "empresa" => $emp_id
    ));

    if (!$insertDetCFDICompra) {
      throw new \Exception("No se pudo generar el registro de detalle de CFDI para: " . $detBuy['Descripcion']);
    }

    $this->insertarImpuestosCFDI($uuid_cfdi_detalle, $obtenCompra, $detBuy['retenciones'] ?? [], 'rete');
    $this->insertarImpuestosCFDI($uuid_cfdi_detalle, $obtenCompra, $detBuy['traslados'] ?? [], 'tras');

    return $uuid_cfdi_detalle;
  }

  private function insertarImpuestosCFDI($uuidDetalle, $numCompra, $impuestos, $tipo) {
    if (empty($impuestos)) return;
    $dataImpCFDIToInsert = [];
    foreach ($impuestos as $imp) {
      $dataImpCFDIToInsert[] = [
        'uuid_buydet_impuestos' => Str::uuid()->toString(),
        'numero_compra'         => $numCompra,  
        'uuid_cfdi_detalle'     => $uuidDetalle,
        'retencion_traslado'    => $tipo,
        'base'                  => $imp["Base"] ?? 0.00,
        'impuesto'              => $imp["Impuesto"] ?? '000',
        'tipoFactor'            => $imp["TipoFactor"] ?? NULL,
        'tasaOCuota'            => $imp["TasaOCuota"] ?? NULL,
        'importe'               => $imp["Importe"] ?? 0.00,
        //"created_at"            => now()
      ];
    }

    // 3. Inserción masiva: Una sola ejecución de SQL para todos los impuestos
    if (!empty($dataImpCFDIToInsert)) {
      $inserted = DB::table('eegr_compras_cfdi_detalle_impuestos')->insert($dataImpCFDIToInsert);

      if (!$inserted) {
        throw new \Exception("No se pudo registrar los impuestos de CFDI.");
      }
    }
  }
  
  private function procesarProrrateo($JwtAuth, $vEmp, $id_prod, $id_serv, $obtenCompra, $selectDetBuy, $fecha_contabilizacion) {
    $folioProrrateo = DB::selectOne("SELECT COALESCE(MAX(folder) + 1, 1) AS folio FROM sos_last_folders WHERE egr_prorrateos = TRUE AND empresa = ?", [$vEmp->id]);
    $tokenProrrateo = $JwtAuth->encriptarToken(time().$selectDetBuy.$id_prod.$id_serv.$obtenCompra);
  
    $id_p = DB::table('eegr_compras_prorrateos')->insertGetId([
      "token_prorrateo" => $tokenProrrateo,
      "folio_prorrateo" => $folioProrrateo->folio,
      "fecha_sistema_prorrateo" => time(),
      "fecha_prorrateo" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
      "producto" => $id_prod,
      "servicio" => $id_serv,
      "compra" => $obtenCompra,
      "detalle_compra" => $selectDetBuy,
      "empresa" => $vEmp->id,
      "status_prorrateo" => TRUE,
    ]);

    if (!$id_p) {
      throw new \Exception("No se pudo registrar prorrateos.");
    }

    // Actualizar el folio en la tabla de control
    DB::table('sos_last_folders')
    ->updateOrInsert(
      ['egr_prorrateos' => TRUE, 'empresa' => $vEmp->id],
      ['folder' => $folioProrrateo->folio]
    );

    $obten_prorrateo_ident =DB::table("eegr_compras_prorrateos")->where("token_prorrateo",$tokenProrrateo)->value("id");
    $tokenDetalleProrrt = $JwtAuth->encriptarToken(time().$obten_prorrateo_ident.$id_prod, $id_serv, $obtenCompra, $selectDetBuy);

    DB::table('eegr_compras_prorrateos_detalle')->insert([
      "token_detalle_prorrt" => $tokenDetalleProrrt,
      "prorrateo" => $id_p,
      "detalle_compra" => $selectDetBuy,
    ]);
  }
  
  private function procesarKardexProducto($JwtAuth, $vEmp, $id_producto, $tokenArticulo, $obtenCompra, $selectDetBuy, $cantidad, $precioUnitario, $tokenCompra) {
    // 1. Mejora en la obtención del folio: Más legible y usando el Query Builder de Laravel
    $ultimoFolio = DB::table('in_egr_productos_kardex as dexkar')
    ->join('in_egr_catalogo_productos as catprod', 'dexkar.producto_id', '=', 'catprod.id')
    ->join('main_empresas as emp', 'catprod.admin_empresa', '=', 'emp.id')
    ->where('catprod.token_cat_productos', $tokenArticulo)
    ->where('emp.empresa_token', $vEmp->id)
    ->max('dexkar.folio_kardex');

    $nuevoFolio = ($ultimoFolio ?? 0) + 1;

    // 2. Token con mayor entropía: Evitamos colisiones si ocurren procesos en el mismo segundo
    $token_kardex = $JwtAuth->encriptarToken(time() . $tokenCompra . $selectDetBuy . uniqid());

    // 3. Inserción en Kardex
    // Nota: Usamos una variable para validar el resultado
    $insertOk = DB::table("in_egr_productos_kardex")->insert([
      "token_kardex"      => $token_kardex,
      "folio_kardex"      => $nuevoFolio,
      "fecha_kardex"      => time(),
      "status_kardex"     => "por_recibir", // 2 = Por recibir
      "producto_id"       => $id_producto,
      "concepto"          => "por recibir",
      "tipo_documento"    => "COMPRA",
      "factura_compra"    => $obtenCompra,
      "detalle_compra"    => $selectDetBuy,
      "recibir_cantidad"  => $cantidad,
      "valor_unitario"    => $precioUnitario,
    ]);

    // 4. Validación inmediata (Throw) antes de cualquier otra operación
    if (!$insertOk) {
      throw new \Exception("Error al registrar movimiento en Kardex para el producto ID: $id_producto");
    }

    // 5. Actualización del catálogo: Solo ocurre si el Kardex fue exitoso
    $updateCat = DB::table('in_egr_catalogo_productos')
    ->where('id', $id_producto)
    ->update(["ultima_compra" => time()]);

    if (!$updateCat) {
      throw new \Exception("Error al registrar movimiento en ultima compra para el producto ID: $id_producto");
    }
  }

  private function procesarActivoFijo($JwtAuth, $selectDetBuy, $detBuy, $cfdi_moneda, $cfdi_tc, $emp_id) {
    $empData = DB::table("sos_personas AS people")
    ->join("main_empresas AS emp", "people.id", "=", "emp.persona")
    ->where("emp.id",$emp_id)
    ->select('people.abrev_nombre')
    ->first();
    $abrev = $empData ? strtoupper($empData->abrev_nombre) : 'EMP';

    // 1. Obtención de datos base e ID del catálogo
    $id_activo_fijo = DB::table("eegr_activos_fijos_catalogo")
    ->where("token_act_fijos", $detBuy['articulo_homologado_activoFijo'])
    ->value("id");
    
    // Limpieza de descuento
    $descuentoXUni = $detBuy['Descuento'] ?? '0.00';
    $total_descuento = ($descuentoXUni !== '' && $descuentoXUni !== '---' && $descuentoXUni !== '0.00') ? $descuentoXUni : '0.00';
    
    // Cálculo eficiente de impuestos
    $total_retenciones = collect($detBuy['retenciones'] ?? [])->sum(fn($r) => ($r['TipoFactor'] !== 'Exento') ? ($r['Importe'] ?? 0) : 0);
    $total_traslados = collect($detBuy['traslados'] ?? [])->sum(fn($t) => ($t['TipoFactor'] !== 'Exento') ? ($t['Importe'] ?? 0) : 0);
    
    $uuid_activo_fijo_det = Str::uuid()->toString();//(string) Str::uuid();
    
    // 2. Inserción del registro Maestro (Detalle de Activo)
    // Usamos insertGetId para obtener el ID real y evitar consultas extras dentro del bucle
    //echo $emp_id;
    $id_det_insertado = DB::table('eegr_activos_fijos_detalle')->insertGetId([
      "token_det_activo_fijo" => $uuid_activo_fijo_det,
      "activo_fijo"           => $id_activo_fijo,
      "compra_detalle"        => $selectDetBuy,
      "concepto"              => $JwtAuth->encriptar($detBuy['Descripcion']),
      "moneda"                => $cfdi_moneda,
      "tipo_de_cambio"        => $cfdi_tc,
      "precio_unitario"       => $detBuy['ValorUnitario'],
      "cantidad"              => $detBuy['Cantidad'],
      "unidad_medida"         => $detBuy['Unidad'],
      "descuento"             => $total_descuento,
      "retenciones_total"     => $total_retenciones,
      "traslados_total"       => $total_traslados,
      "empresa"               => $emp_id
    ]);

    if (!$id_det_insertado) {
      throw new \Exception("No se pudo generar el registro de detalle de activo para: " . $detBuy['Descripcion']);
    }

    // 3. Preparación de Unidades (Bulk Insert)
    //$activo_fijo_foliado = $detBuy['articulo_homologado_activo_foliado'] ?? [];
    $unidadesParaInsertar = [];

    $ultimoFolio = DB::table('eegr_activos_fijos_unidades')
    ->where('empresa', $emp_id)
    ->where('folio_activof_unidad', 'LIKE', "ACT-$abrev-%")
    ->orderBy('id', 'desc')
    ->value('folio_activof_unidad');
    
    // 3. Extraer el número y determinar el siguiente
    if ($ultimoFolio) {
      $partes = explode('-', $ultimoFolio);
      $consecutivo = (int)end($partes) + 1;
    } else {
      $consecutivo = 1;
    }
    $ua_fe_cantidad = (int) $detBuy['Cantidad'];
    for ($ufae = 0; $ufae < $ua_fe_cantidad; $ufae++) {
      $folioAutomatico = "ACT-" . $abrev . "-" . str_pad($consecutivo, 4, "0", STR_PAD_LEFT);
      $unidadesParaInsertar[] = [
        "token_activof_unidad" => Str::uuid()->toString(),
        "activof_detalle"      => $id_det_insertado, // Relación directa por ID
        "folio_activof_unidad"         => $folioAutomatico,
        //"serie"                => $folio['activo_serie'] ?? null,
        //"otros"                => $folio['activo_otros'] ?? null,
        //"observaciones"        => $folio['activo_observaciones'] ?? null,
        "empresa"              => $emp_id,
        //"created_at"           => now() // Recomendado para trazabilidad
      ];
      $consecutivo++;
    }

    // 4. Inserción masiva de una sola vez
    if (!empty($unidadesParaInsertar)) {
      $insertUnidades = DB::table('eegr_activos_fijos_unidades')->insert($unidadesParaInsertar);

      if (!$insertUnidades) {
        throw new \Exception("Error crítico al registrar las unidades individuales de los activos.");
      }
    }
  }

  private function procesarActivoDiferido($JwtAuth, $selectDetBuy, $detBuy, $cfdi_moneda, $cfdi_tc, $emp_id) {
    $empData = DB::table("sos_personas AS people")
    ->join("main_empresas AS emp", "people.id", "=", "emp.persona")
    ->where("emp.id",$emp_id)
    ->select('people.abrev_nombre')
    ->first();
    $abrev = $empData ? strtoupper($empData->abrev_nombre) : 'EMP';

    // 1. Obtención de datos base e ID del catálogo
    $id_activo_diferido = DB::table("eegr_activos_intangibles_catalogo")
    ->where("token_act_intang", $detBuy['articulo_homologado_activoDiferido'])
    ->value("id");
    
    // Limpieza de descuento
    $descuentoXUni = $detBuy['Descuento'] ?? '0.00';
    $total_descuento = ($descuentoXUni !== '' && $descuentoXUni !== '---' && $descuentoXUni !== '0.00') ? $descuentoXUni : '0.00';
    
    // Cálculo eficiente de impuestos
    $total_retenciones = collect($detBuy['retenciones'] ?? [])->sum(fn($r) => ($r['TipoFactor'] !== 'Exento') ? ($r['Importe'] ?? 0) : 0);
    $total_traslados = collect($detBuy['traslados'] ?? [])->sum(fn($t) => ($t['TipoFactor'] !== 'Exento') ? ($t['Importe'] ?? 0) : 0);
    
    $uuid_activo_fijo_det = Str::uuid()->toString();//(string) Str::uuid();
    
    // 2. Inserción del registro Maestro (Detalle de Activo)
    // Usamos insertGetId para obtener el ID real y evitar consultas extras dentro del bucle
    //echo $emp_id;
    $id_det_insertado = DB::table('eegr_activos_intangibles_detalle')->insertGetId([
      "token_det_act_intang"  => $uuid_activo_fijo_det,
      "activo_intang"         => $id_activo_diferido,
      "compra_detalle"        => $selectDetBuy,
      "concepto"              => $JwtAuth->encriptar($detBuy['Descripcion']),
      "moneda"                => $cfdi_moneda,
      "tipo_de_cambio"        => $cfdi_tc,
      "precio_unitario"       => $detBuy['ValorUnitario'],
      "cantidad"              => $detBuy['Cantidad'],
      "unidad_medida"         => $detBuy['Unidad'],
      "descuento"             => $total_descuento,
      "retenciones_total"     => $total_retenciones,
      "traslados_total"       => $total_traslados,
      "empresa"               => $emp_id
    ]);

    if (!$id_det_insertado) {
      throw new \Exception("No se pudo generar el registro de detalle de activo para: " . $detBuy['Descripcion']);
    }

    // 3. Preparación de Unidades (Bulk Insert)
    //$activo_fijo_foliado = $detBuy['articulo_homologado_activo_foliado'] ?? [];
    $unidadesParaInsertar = [];

    $ultimoFolio = DB::table('eegr_activos_intangibles_unidades')
    ->where('empresa', $emp_id)
    ->where('folio_activod_unidad', 'LIKE', "ACT-$abrev-%")
    ->orderBy('id', 'desc')
    ->value('folio_activod_unidad');
    
    // 3. Extraer el número y determinar el siguiente
    if ($ultimoFolio) {
      $partes = explode('-', $ultimoFolio);
      $consecutivo = (int)end($partes) + 1;
    } else {
      $consecutivo = 1;
    }
    $ua_fe_foliado = $detBuy['articulo_homologado_activo_diferido_foliado'];
    foreach ($ua_fe_foliado as $item) {
      $folioAutomatico = "ACT-" . $abrev . "-" . str_pad($consecutivo, 4, "0", STR_PAD_LEFT);
      $unidadesParaInsertar[] = [
        "token_activod_unidad" => Str::uuid()->toString(),
        "activod_detalle"      => $id_det_insertado, // Relación directa por ID
        "folio_activod_unidad"         => $folioAutomatico,
        //costo_adquisicion
        //fecha_inicio_amortizacion
        "amort_contable_periodo" => $item['amort_contable_periodo'],
        "amort_contable_tiempo" => $item['amort_contable_tiempo'],
        "amort_contable_fecha_apartir" => $item['amort_contable_fecha_apartir'],
        "amort_contable_observaciones" => $item['amort_contable_observaciones'],
        //fecha_ultimo_corte_contable
        //fecha_proximo_corte_contable
        "amort_fiscal_periodo" => $item['amort_fiscal_periodo'],
        "amort_fiscal_tiempo" => $item['amort_fiscal_tiempo'],
        "amort_fiscal_fecha_apartir" => $item['amort_fiscal_fecha_apartir'],
        "amort_fiscal_observaciones" => $item['amort_fiscal_observaciones'],
        //fecha_ultimo_corte_fiscal
        //fecha_proximo_corte_fiscal
        //amortizacion_bloqueada
        //date_bloqueo_desbloqueo_prorrateo
        "empresa"              => $emp_id

      ];
      $consecutivo++;
    }

    // 4. Inserción masiva de una sola vez
    if (!empty($unidadesParaInsertar)) {
      $insertUnidades = DB::table('eegr_activos_intangibles_unidades')->insert($unidadesParaInsertar);

      if (!$insertUnidades) {
        throw new \Exception("Error crítico al registrar las unidades individuales de los activos.");
      }
    }
  }

  public function registrarCompraByReembolso(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_reem' => 'required|string',
      'token_solicitud_reem' => 'required|string',
      'fecha_contabilizacion' => 'required|string',
      'fecha_vencimiento' => 'required|string',
      'total' => 'required|string', 
      'token_proveedor' => 'required|string',
      'dataCFDI_comprobante_fecha' => 'required|string',
      'cfdi_TipoCambio' => 'required|string',
      'cfdi_Moneda' => 'required|string',
      'cfdi_MoneDecimales' => 'required|string',
      'dataCFDI_comprobante_formaPago' => 'required|string',
      'dataCFDI_comprobante_MetodoPago' => 'required|string',
      'dataCFDI_receptor_UsoCFDI' => 'required|string',
      'cfdi_conceptos' => 'required|array',
      'cfdi_impuestos_retenidos' => 'array',
      'cfdi_impuestos_trasladados' => 'array',
      'compra_contado_credito' => 'required|string',
      'receptFactura' => 'required|boolean',
      'anticipoValor' => 'string',
      'classRecibeArtPago' => 'required|boolean',
      'tipoLugarEntrega' => 'required|string',
      'tknLugarRecepcion' => 'string',
      'compra_observaciones' => 'string'
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
      $periodo = $request->input('periodo');
      
      $patrón = '/[aA-zZ_]/';
      $patrónNum = '/^[0-9$,.-]*$/';
      $patrónNumCosto = '/^[0-9$,.-]*$/';
      $patrónRfc = '/[aA0-zZ9]/';
      $patrónFecha = '/^[0-9-]*$/';
      
      $moneda_decimales = 0;
      $token_reem = $parametrosArray['token_reem'];
      $token_solicitud_reem = $parametrosArray['token_solicitud_reem'];
      $fecha_contabilizacion = $parametrosArray['fecha_contabilizacion'];
      $fecha_vencimiento = $parametrosArray['fecha_vencimiento'];
      $total = $parametrosArray['total'];
      $token_proveedor = $parametrosArray['token_proveedor'];
      
      $cfdi_comprobante_fecha = $parametrosArray['dataCFDI_comprobante_fecha'];
      $cfdi_TipoCambio = $parametrosArray['cfdi_TipoCambio'];
      $cfdi_Moneda = $parametrosArray['cfdi_Moneda'];
      $cfdi_MoneDecimales = $parametrosArray['cfdi_MoneDecimales'];
      $dataCFDI_comprobante_formaPago = $parametrosArray['dataCFDI_comprobante_formaPago'];
      $dataCFDI_comprobante_MetodoPago = $parametrosArray['dataCFDI_comprobante_MetodoPago'];
      $dataCFDI_receptor_UsoCFDI = $parametrosArray['dataCFDI_receptor_UsoCFDI'];
      $cfdi_conceptos = $parametrosArray['cfdi_conceptos'];
      $cfdi_impuestos_retenidos = $parametrosArray['cfdi_impuestos_retenidos'];
      $cfdi_impuestos_trasladados = $parametrosArray['cfdi_impuestos_trasladados'];
      $compra_contado_credito = $parametrosArray['compra_contado_credito'];
      $receptFactura = $parametrosArray['receptFactura'] ? $parametrosArray['tipoDeCambio'] : '1.00';
      $anticipoValor = $parametrosArray['uuid_anticipo'];
      $classRecibeArtPago = $parametrosArray['classRecibeArtPago'];
      $tipoLugarEntrega = $parametrosArray['tipoLugarEntrega'];
      $tknLugarRecepcion = $parametrosArray['tknLugarRecepcion'];
      $compra_observaciones = $parametrosArray['compra_observaciones'];

      $permisosCreacion = $JwtAuth->permisosCreacion('bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA==',$empresa,$usuario);

      $validate_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $validate_fecha_vencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
      $validate_total = isset($total) && !empty($total);
      $idProveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$token_proveedor)->value("id");
      $validate_prov = isset($token_proveedor) && !empty($token_proveedor) && $idProveedor != "";
      
      $cfdi_comprobante_fecha = $parametrosArray['dataCFDI_comprobante_fecha'];
      $cfdi_TipoCambio = $parametrosArray['cfdi_TipoCambio'];
      $cfdi_Moneda = $parametrosArray['cfdi_Moneda'];
      $cfdi_MoneDecimales = $parametrosArray['cfdi_MoneDecimales'];
      $dataCFDI_comprobante_formaPago = $parametrosArray['dataCFDI_comprobante_formaPago'];
      $dataCFDI_comprobante_MetodoPago = $parametrosArray['dataCFDI_comprobante_MetodoPago'];
      $dataCFDI_receptor_UsoCFDI = $parametrosArray['dataCFDI_receptor_UsoCFDI'];

      $validate_cfdi_conceptos = isset($cfdi_conceptos) && !empty($cfdi_conceptos) && is_array($cfdi_conceptos);
      $validate_cfdi_impuestos_retenidos = isset($cfdi_impuestos_retenidos) && is_array($cfdi_impuestos_retenidos);
      $validate_cfdi_impuestos_trasladados = isset($cfdi_impuestos_trasladados) && is_array($cfdi_impuestos_trasladados);
      $validate_compra_contado_credito = isset($compra_contado_credito) && !empty($compra_contado_credito) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_contado_credito);
      $validate_anticipoValor = isset($anticipoValor) && !empty($anticipoValor);
      $validate_classRecibeArtPago = isset($classRecibeArtPago) && is_bool($classRecibeArtPago);
      $validate_tipoLugarEntrega = isset($tipoLugarEntrega) && !empty($tipoLugarEntrega) && isset($tknLugarRecepcion) && !empty($tknLugarRecepcion);
      $validate_compra_observaciones = isset($compra_observaciones) && !empty($compra_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_observaciones);
      
      if ($validate_fecha_contabilizacion && $validate_fecha_vencimiento && $permisosCreacion && $validate_total && $validate_prov && $validate_cfdi_conceptos && 
        $validate_cfdi_impuestos_retenidos && $validate_cfdi_impuestos_trasladados && $validate_compra_contado_credito && $validate_classRecibeArtPago) {
        //&& file_exists($request->file('imagenEvidenciaVerificacion'))

        $idDireccionProv = DB::table("teci_direcciones AS dir")
        ->join("eegr_catalogo_proveedores AS catprov","dir.proveedor","=","catprov.id")
        ->where(["dir.token_direccion" => $tknLugarRecepcion,"catprov.token_cat_proveedores" => $token_proveedor])
        ->value("dir.id");

        $idDireccionEst = DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$tknLugarRecepcion)->value("id");
        if (($tipoLugarEntrega == 'proveedor' && $idDireccionProv == "") || ($tipoLugarEntrega == 'establecimiento' && $idDireccionEst == "")) {
          $dataMensaje = array('status' => 'error','code' => 200,'message' => 'El lugar de recepción seleccionado no encontrado, verifique su información o comuniquese a soporte');
        }

        $detalleErrores = "";
        foreach ($cfdi_conceptos as $vDet) {
          $tokenArticulo = $vDet['articulo_homologado_token'];
          $identificador = $vDet['articulo_homologado_identificador'];
          $concepto = $vDet['Descripcion'];
          $precioUnitario = $vDet['ValorUnitario'];
          $cantidad = $vDet['Cantidad'];
          //return response()->json(['status' => 'error','code' => 200,'message' => $cantidad]);
          $descuentoXUni = $vDet['Descuento'];
          $iva = $vDet['articulo_homologado_iva'];
          $retenciones = $vDet['retenciones'];
          $traslados = $vDet['traslados'];
          $usoArticulo = $vDet['articulo_homologado_uso'];
          $efectoFiscalArticulo = $vDet['articulo_homologado_efecto_fiscal'];
          $activoFijo = $vDet['articulo_homologado_activoFijo'];
          $activoIntangible = $vDet['articulo_homologado_activoDiferido'];
          $prorratea = $vDet['articulo_homologado_prorratea'];

          //$periodicidadPc = $vDet['articulo_homologado_periodicidadPc'];
          //$iteracionPc = $vDet['articulo_homologado_iteracionPc'];
          //$periodoDetIndPc = $vDet['articulo_homologado_periodoDetIndPc'];
          //$fechaFinPc = $vDet['articulo_homologado_fechaFinPc'];
          //$tipoImporteVi = $vDet['articulo_homologado_tipoImporteVi'];
          //$importeMinVi = $vDet['articulo_homologado_importeMinVi']; //importeMinVi
          //$importeMaxVi = $vDet['articulo_homologado_importeMaxVi'];
          $importe = $JwtAuth->rellenaImportesCompras($vDet['Importe']);
          //return response()->json(['message' => 'pais11','codigo' => 200,'status' => 'error']);
          $validateActivos = false;
          $validatePeriodicidad = false;
          $validateDescuentos = false;
          $validateDecimalesMoneda = false;
          $validateForImpuRetenciones = false;
          $validateForImpuTraslados = false;

          $vItem_tokenArticulo = isset($tokenArticulo) && !empty($tokenArticulo);
          $vItem_identificador = isset($identificador) && !empty($identificador) && preg_match($JwtAuth->filtroAlfaNumerico(), $identificador);
          $vItem_precioUnitario = isset($precioUnitario) && !empty($precioUnitario) && preg_match($patrónNumCosto, $precioUnitario);
          $vItem_cantidad = isset($cantidad) && !empty($cantidad) && preg_match($patrónNum, $cantidad);
          //&& isset($iva) && !empty($iva) && preg_match($patrónNumCosto,$iva)
          $vItem_usoArticulo = isset($usoArticulo) && !empty($usoArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $usoArticulo);
          $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
          //$vItem_periodicidadPc = isset($periodicidadPc) && !empty($periodicidadPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $periodicidadPc);
          $vItem_importe = isset($importe) && !empty($importe) && preg_match($patrónNumCosto, $importe);

          if ($vItem_tokenArticulo && $vItem_identificador && $vItem_precioUnitario && $vItem_cantidad && $vItem_usoArticulo /*&& $vItem_periodicidadPc*/ && $vItem_importe) {
            if (isset($descuentoXUni) && !empty($descuentoXUni)) {
              if ($descuentoXUni != '---') {
                if (preg_match($patrónNumCosto, $descuentoXUni)) {
                  $strPosdescuentoXUni = strpos($descuentoXUni, '.');
                  if ($strPosdescuentoXUni !== FALSE) {
                    $expdescuentoXUni = explode('.', $descuentoXUni);
                    if ($moneda_decimales == strlen($expdescuentoXUni[1])) {
                      $validateDescuentos = true;
                    } else {
                      $validateDescuentos = false;
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La cantidad de decimales del descuento no coincide con los decimales que soporta la moneda seleccionada'
                      );
                    }
                  } else {
                    $validateDescuentos = false;
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importe no coincide con los decimales que soporta la moneda seleccionada'
                    );
                  }
                } else {
                  $validateDescuentos = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Descuento invalido'
                  );
                }
              } else {
                $validateDescuentos = true;
              }
            } else {
              $validateDescuentos = false;
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'La cantidad de descuento es invalida o inexistente'
              );
            }

            if ($moneda_decimales != 0) {
              $strPosPrecioUnit = strpos($precioUnitario, '.');
              $strPosimporte = strpos($importe, '.');

              if ($strPosPrecioUnit !== FALSE && $strPosimporte !== FALSE) {
                $expUnitPrecio = explode('.', $precioUnitario);
                $expimporte = explode('.', $importe);

                if ((strlen($expUnitPrecio[1]) == 6 || strlen($expUnitPrecio[1]) == $moneda_decimales) &&
                  (strlen($expimporte[1]) == 6 || strlen($expimporte[1]) == $moneda_decimales)
                ) {
                  $validateDecimalesMoneda = true;
                } else {
                  $validateDecimalesMoneda = false;
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                  );
                }
              } else {
                $validateDecimalesMoneda = false;
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                );
              }
            }

            if ($moneda_decimales == 0) {
              $strPosPrecioUnit = strpos($precioUnitario, '.');
              $strPosimporte = strpos($importe, '.');
              if ($strPosPrecioUnit !== FALSE && $strPosimporte !== FALSE) {
                $validateDecimalesMoneda = false;
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'El precio unitario del producto/servicio no tiene decimales'
                );
              } else {
                $validateDecimalesMoneda = true;
              }
            }

            if ($usoArticulo == 'activo_fijo') {
              if (isset($activoFijo) && !empty($activoFijo) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoFijo)) {
                $validateActivos = true;
              } else {
                $validateActivos = false;
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'El activo del producto/servicio '.$concepto.' es invalido o inexistente '
                );
                break;
              }
            } else if ($usoArticulo == 'activo_diferido') {
              if (isset($activoIntangible) && !empty($activoIntangible) && preg_match($JwtAuth->filtroAlfaNumerico(), $activoIntangible)) {
                $validateActivos = true;
              } else {
                $validateActivos = false;
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'El descuento del producto/servicio '.$concepto.' es invalido o inexistente '
                );
                break;
              }
            } else {
              $validateActivos = true;
            }

            /*if ($periodicidadPc == 'periodo') {
              //return response()->json(['message' => 'error desglose'.$importe,'codigo' => 200,'status' => 'error']);
              if (
                isset($iteracionPc) && !empty($iteracionPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $iteracionPc) &&
                isset($periodoDetIndPc) && !empty($periodoDetIndPc) && preg_match($JwtAuth->filtroAlfaNumerico(), $periodoDetIndPc) &&
                isset($tipoImporteVi) && !empty($tipoImporteVi) && preg_match($JwtAuth->filtroAlfaNumerico(), $tipoImporteVi)  &&
                isset($importeMinVi) && !empty($importeMinVi) && preg_match($patrónNumCosto, $importeMinVi) &&
                isset($importeMaxVi) && !empty($importeMaxVi) && preg_match($patrónNumCosto, $importeMaxVi)
              ) {
                if ($periodoDetIndPc == 'determinado') {
                  if (isset($fechaFinPc) && !empty($fechaFinPc) && preg_match($patrónFecha, $fechaFinPc)) {
                    $validatePeriodicidad = true;
                  } else {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'La fecha de fin de periodo de periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente '
                    );
                    break;
                  }
                }
                if ($periodoDetIndPc == 'indeterminado') {
                  $validatePeriodicidad = true;
                }

                if ($moneda_decimales != 0) {
                  $strPosimporteMinVi = strpos($importeMinVi, '.');
                  $strPosimporteMaxVi = strpos($importeMaxVi, '.');

                  if ($strPosimporteMinVi !== FALSE && $strPosimporteMaxVi !== FALSE) {
                    $expimporteMinVi = explode('.', $importeMinVi);
                    $expimporteMaxVi = explode('.', $importeMaxVi);

                    if (
                      $moneda_decimales == strlen($expimporteMinVi[1]) &&
                      $moneda_decimales == strlen($expimporteMaxVi[1])
                    ) {
                      $validateDecimalesMoneda = true;
                    } else {
                      $validateDecimalesMoneda = false;
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                      );
                    }
                  } else {
                    $validateDecimalesMoneda = false;
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'La cantidad de decimales se encuentra precio unitario, descuento, importeno coincide con los decimales que soporta la moneda seleccionada'
                    );
                  }
                }

                if ($moneda_decimales == 0) {
                  $strPosimporteMinVi = strpos($importeMinVi, '.');
                  $strPosimporteMaxVi = strpos($importeMaxVi, '.');

                  if ($strPosimporteMinVi !== FALSE && $strPosimporteMaxVi !== FALSE) {
                    $validateDecimalesMoneda = false;
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'El precio unitario del producto/servicio no tiene decimales'
                    );
                  } else {
                    $validateDecimalesMoneda = true;
                  }
                }
              } else {
                $validatePeriodicidad = false;
                if (!isset($iteracionPc) || empty($iteracionPc) || preg_match($JwtAuth->filtroAlfaNumerico(), $iteracionPc)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La iteración (repetición) de periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente'
                  );
                  break;
                }
                if (!isset($periodoDetIndPc) || empty($periodoDetIndPc) || preg_match($JwtAuth->filtroAlfaNumerico(), $periodoDetIndPc)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La selección de periodo de periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente'
                  );
                  break;
                }

                if (!isset($tipoImporteVi) || empty($tipoImporteVi) || !preg_match($JwtAuth->filtroAlfaNumerico(), $tipoImporteVi)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El tipo de variablidilad de importe del producto/servicio '.$concepto.' es invalido o inexistente '
                  );
                  break;
                }
                if (!isset($importeMinVi) || empty($importeMinVi) || !preg_match($patrónNumCosto, $importeMinVi)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El importe mínimo de variabilidad del producto/servicio '.$concepto.' es invalido o inexistente '
                  );
                  break;
                }
                if (!isset($importeMaxVi) || empty($importeMaxVi) || !preg_match($patrónNumCosto, $importeMaxVi)) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'El importe maximo de variabilidad del producto/servicio '.$concepto.' es invalido o inexistente '
                  );
                  break;
                }
              }
            }
            if ($periodicidadPc == 'eventual') {
              $validatePeriodicidad = true;
            }*/

            if (count($retenciones) != 0) {
              $countValidateRetencionesConcept = 0;
              for ($t = 0; $t < count($retenciones); $t++) {
                $base = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Base"]);
                $explodeBase = explode('.', $base);
                $impuesto = $retenciones[$t]["Impuesto"];
                $tipoFactor = $retenciones[$t]["TipoFactor"];
                $TasaOCuota = $retenciones[$t]["TasaOCuota"];
                $importe = $JwtAuth->rellenaImportesCompras($retenciones[$t]["Importe"]);
                $importe = $retenciones[$t]["TipoFactor"] != "Exento" || (isset($retenciones[$t]["Importe"]) && $retenciones[$t]["Importe"] != 0) ? $JwtAuth->rellenaImportesCompras($retenciones[$t]["Importe"]) : "0.00";
                //return response()->json(['message' => $retenciones[$t]["Importe"],'codigo' => 200,'status' => 'error']);
                $explodeImporte = explode('.', $importe);

                if (
                  isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                  && isset($tipoFactor) && !empty($tipoFactor)
                  && isset($TasaOCuota) && !empty($TasaOCuota)
                  && isset($importe) && !empty($importe)
                  && (strlen($explodeImporte[1]) == 6 || strlen($explodeImporte[1]) == $moneda_decimales)
                ) {
                  if (isset($base)) {
                    if (!empty($base) && (strlen($explodeBase[1]) == 6 || strlen($explodeBase[1]) == $moneda_decimales)) {
                      ++$countValidateRetencionesConcept;
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Base de retención del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida'
                      );
                      break;
                    }
                  } else {
                    ++$countValidateRetencionesConcept;
                  }
                  //return response()->json(['message' => $base,'codigo' => 200,'status' => 'error']);
                } else {
                  if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'Impuesto de retención del producto/servicio '.$concepto.' no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)'
                    );
                    break;
                  }
                  if (!isset($tipoFactor) || empty($tipoFactor)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'TipoFactor de retención del producto/servicio '.$concepto.' no existe o esta vacio'
                    );
                    break;
                  }
                  if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'TasaOCuota de retención del producto/servicio '.$concepto.' no existe o esta vacio'
                    );
                    break;
                  }
                  if (
                    !isset($importe) || empty($importe) ||
                    (strlen($explodeImporte[1]) != 6 && strlen($explodeImporte[1]) != $moneda_decimales)
                  ) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'Importe de retención del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida'
                    );
                    break;
                  }
                }
              }

              if ($countValidateRetencionesConcept == count($retenciones)) {
                $validateForImpuRetenciones = true;
              }
            } else {
              $validateForImpuRetenciones = true;
            }

            if (count($traslados) != 0) {
              $countValidateTrasladosConcept = 0;
              for ($t = 0; $t < count($traslados); $t++) {
                $base = $JwtAuth->rellenaImportesCompras($traslados[$t]["Base"]);
                $explodeBase = explode('.', $base);
                $impuesto = $traslados[$t]["Impuesto"];
                //return response()->json(['message' => $impuesto,'codigo' => 200,'status' => 'error']);
                $tipoFactor = $traslados[$t]["TipoFactor"];
                $TasaOCuota = $traslados[$t]["TasaOCuota"];
                $importe = $traslados[$t]["TipoFactor"] != "Exento" || (isset($traslados[$t]["Importe"]) && $traslados[$t]["Importe"] != 0) ? $JwtAuth->rellenaImportesCompras($traslados[$t]["Importe"]) : "0.00";
                $explodeImporte = explode('.', $importe);

                if (
                  isset($impuesto) && !empty($impuesto) && strlen($impuesto) == 3
                  && isset($tipoFactor) && !empty($tipoFactor)
                  && isset($TasaOCuota) && !empty($TasaOCuota)
                  && isset($importe) && !empty($importe) && (strlen($explodeImporte[1]) == 6 || strlen($explodeImporte[1]) == $moneda_decimales)
                ) {
                  if (isset($base)) {
                    //return response()->json(['message' => strlen($explodeBase[1]).' == '.$moneda_decimales,'codigo' => 200,'status' => 'error']);
                    if (!empty($base) && (strlen($explodeBase[1]) == 6 || strlen($explodeBase[1]) == $moneda_decimales)) {
                      ++$countValidateTrasladosConcept;
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Base de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida'
                      );
                      break;
                    }
                  } else {
                    ++$countValidateTrasladosConcept;
                  }
                } else {
                  if (!isset($impuesto) || empty($impuesto) || strlen($impuesto) != 3) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'Impuesto de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sobrepasa el limite de caracteres permitidos (max. 3)'
                    );
                    break;
                  }
                  if (!isset($tipoFactor) || empty($tipoFactor)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'TipoFactor de traslado del producto/servicio '.$concepto.' no existe o esta vacio'
                    );
                    break;
                  }
                  if (!isset($TasaOCuota) || empty($TasaOCuota)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'TasaOCuota de traslado del producto/servicio '.$concepto.' no existe o esta vacio'
                    );
                    break;
                  }
                  if (!isset($importe) || empty($importe) || (strlen($explodeImporte[1]) != 6 && strlen($explodeImporte[1]) != $moneda_decimales)) {
                    $dataMensaje = array(
                      'status' => 'error',
                      'code' => 200,
                      'message' => 'Importe de traslado del producto/servicio '.$concepto.' no existe, esta vacio o sus decimales no son iguales a loa que permite la moneda establecida (' . $moneda_decimales . ')'
                    );
                    break;
                  }
                }
              }

              if ($countValidateTrasladosConcept == count($traslados)) {
                $validateForImpuTraslados = true;
              }
            } else {
              $validateForImpuTraslados = true;
            }
          } else {
            if (!$vItem_tokenArticulo) {$detalleErrores = 'producto/servicio '.$concepto.' invalidado';}
            if (!$vItem_identificador) {$detalleErrores = 'identificador del producto/servicio '.$concepto.' es incorrecto o inexistente';}
            if (!$vItem_precioUnitario) {$detalleErrores = 'El precio unitario del producto/servicio '.$concepto.' es invalido o inexistente';}
            if (!$vItem_cantidad) {$detalleErrores = 'La cantidad del producto/servicio '.$concepto.' es invalida o inexistente';}
            if (!$vItem_usoArticulo) {$detalleErrores = 'El uso del producto/servicio '.$concepto.' es invalido o inexistente';}
            //if (!$vItem_periodicidadPc) {$detalleErrores = 'La periodicidad de compra del producto/servicio '.$concepto.' es invalido o inexistente';}
            if (!$vItem_importe) {$detalleErrores = 'El importe del producto/servicio '.$concepto.' es invalido o inexistente';}
            break;
          }
        }
        
        if ($detalleErrores == "") {
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

          foreach ($queryEmp as $vEmp) {
            $reembolso_id = $token_reem ? DB::table("terc_reembolso_main")->where("token_reem",$token_reem)->value("id") : NULL;
            $reembolso_soli_id = $token_solicitud_reem ? DB::table("terc_reembolso_solicitud")->where("token_solicitud_reem", $token_solicitud_reem)->value("id") : NULL;

            //$cfdi_comprobante_fecha = "";
            //$cfdi_comprobante_moneda = "";
            //$cfdi_comprobante_forma_de_pago = "";
            //$cfdi_comprobante_metodo_de_pago = "";
            //$cfdi_receptor_uso_del_cfdi = "";
            //$cfdi_comprobante_tipo_de_cambio = "";

            //$cfdi_comprobante_fecha
            //$cfdi_TipoCambio
            //$cfdi_Moneda
            //$cfdi_MoneDecimales
            //$dataCFDI_comprobante_formaPago
            //$dataCFDI_comprobante_MetodoPago
            //$dataCFDI_receptor_UsoCFDI

            $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
              WHERE fold.egr_compras = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

            $post_folio_db = DB::select("SELECT post_folio FROM eegr_compras WHERE id = (SELECT Max(catbuy.id) FROM eegr_compras AS catbuy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
              WHERE catbuy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$empresa, $usuario]);

            $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
            $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
            $folio_buy = 'COMP-'.$JwtAuth->generarFolio($folio_nuevo).($post_folio != NULL ? '-'.$post_folio:'');
            //return response()->json(['message' => $folio_buy,'codigo' => 200,'status' => 'error']);
            $nombreRecePago = '';
            
            foreach ($cfdi_impuestos_retenidos as $vComp) {}
            foreach ($cfdi_impuestos_trasladados as $vComp) {}
            
            $tokenCompra = $JwtAuth->encriptarToken(time(), $token_proveedor, $cfdi_Moneda, $tipoLugarEntrega, $tknLugarRecepcion, $cfdi_conceptos);
            $fechaSistema = time();
            $fecha_altaCompra = $cfdi_comprobante_fecha != '' ? $JwtAuth->convierteFechaEpoc($cfdi_comprobante_fecha) : time();
            $anticipo = $anticipoValor != '' ? $anticipoValor : NULL;
            $user_jerarquia = $vEmp->jerarquia_main == 'P' ? $vEmp->userr : NULL;
            $status_autorizacion = $vEmp->jerarquia_main == 'P' ? TRUE : FALSE;
            $nombreDocs = $fechaSistema."-".$folio_buy;
            $compras = new ComprasModelo();
            $compras->token_compras = $tokenCompra;
            $compras->folio_compra = $folio_nuevo;
            $compras->post_folio = $post_folio;
            $compras->fecha_sistemaCompras = $fechaSistema;
            $compras->fecha_altaCompra = $fecha_altaCompra;
            $compras->fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
            $compras->fecha_vencimiento = $JwtAuth->convierteFechaEpoc($fecha_vencimiento);
            $compras->proveedor = $idProveedor;
            $compras->reembolso_vinculado_main = $reembolso_id;
            $compras->reembolso_vinculado_soli = $reembolso_soli_id;
            $compras->compra_a_credito = $compra_contado_credito == 'contado' ? 'cont' : 'cred';
            $compras->recibeFactura = TRUE;
            $compras->aplica_recepcion_facturas = 'Sí';
            //$compras->facturaXml = file_exists($request->file('imagenEvidenciaXMl')) ? $JwtAuth->encriptar($request->file('imagenEvidenciaXMl')->getClientOriginalName()) : NULL;  //cifrado 
            //$compras->facturaPdf = file_exists($request->file('imagenEvidenciaPdf')) ? $JwtAuth->encriptar($request->file('imagenEvidenciaPdf')->getClientOriginalName()) : NULL;  //cifrado 
            $compras->recepcionPago = $nombreRecePago; //cifrado
            $compras->evidenciaSAT = file_exists($request->file('imagenEvidenciaVerificacion')) ? $JwtAuth->encriptar($fechaSistema."-".$folio_buy.pathinfo($request->file('imagenEvidenciaVerificacion')->getClientOriginalName(), PATHINFO_FILENAME). ".pdf") : NULL; //cifrado
            $compras->anexos = $JwtAuth->encriptar($nombreDocs.".pdf");  //cifrado
            $compras->reporte = $JwtAuth->encriptar($nombreDocs.".pdf"); //cifrado
            $compras->moneda = $cfdi_Moneda;
            $compras->anticipo = $anticipo;
            $compras->forma_pago = $dataCFDI_comprobante_formaPago;
            $compras->metodo_pago = $dataCFDI_comprobante_MetodoPago;
            $compras->uso_cfdi = $dataCFDI_receptor_UsoCFDI;
            $compras->recibeProducto = $classRecibeArtPago ? TRUE : FALSE;// si es TRUE genera orden de pago, si es FALSE no
            $compras->pago_caja_tesoreria = NULL;
            $compras->caja_paga = NULL;

            $compras->recepcion_prov = $tipoLugarEntrega == 'proveedor' ? $idDireccionProv : NULL;
            $compras->recepcion_estab = $tipoLugarEntrega == 'establecimiento' ? $idDireccionEst : NULL;
            $compras->comprador = $vEmp->id;
            $compras->usuario_comprador = $vEmp->userr;
            $compras->status_autorizacion = $status_autorizacion;
            $compras->autoriza = $user_jerarquia;
            $compras->status_cancelacion = FALSE;
            $compras->cancela = NULL;
            $compras->status_recepcion = FALSE;
            $compras->recibe = NULL;
            $compras->fecha_delete_compra = '';
            $compras->status_compra = TRUE;
            $compras->observaciones_compra = $validate_compra_observaciones ? $JwtAuth->encriptar($compra_observaciones) : NULL;
            $insertCompra = $compras->save();
            //return response()->json(['status' => 'error','code' => 200,'message' => 'compraorden']);
            //return response()->json(['status' => 'error','code' => 200,'message' => 'cantidad']);
            if ($insertCompra) {
              $obtenCompra = $compras->id;
              $insertCFDIEstructura = DB::table('cfdi_comprobantes_fiscales AS cfdi')//cfdi__estructura
              ->join("cfdi_vinculacion_reembolsos AS reem_vinc", "cfdi.id", "=", "reem_vinc.comprobante_fiscal")
              ->where("reem_vinc.reembolso_vinculado_main",$reembolso_id)
              ->where("reem_vinc.reembolso_vinculado_soli",$reembolso_soli_id)
              ->limit(1)->update(array('reem_vinc.compra_vinculada' => $obtenCompra));
              $filepath = $vEmp->root_tkn . "/0002-cpp/compras/compras/$nombreDocs/";

              if (!file_exists(storage_path("/root/" . $filepath))) {
                Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
              }

              file_exists($request->file('imagenEvidenciaVerificacion')) ? Storage::putFileAs("/public/root/" . $filepath,$request->file('imagenEvidenciaVerificacion'),$request->file('imagenEvidenciaVerificacion')->getClientOriginalName()) : NULL; 

              if (!empty($_FILES['compra_anexos'])) {
                $evidencias = $_FILES["compra_anexos"];
                $string_name_evid = json_encode($_FILES["compra_anexos"]["name"]);
                if (count(json_decode($string_name_evid)) != 0) {
                  $evidencia_nombre = json_decode($string_name_evid);
                  for ($doc = 0; $doc < count($evidencia_nombre); $doc++) {
                    $temporal = $evidencias["tmp_name"][$doc];
                    $doc_name = $evidencias["name"][$doc];
                    Storage::putFileAs("/public/root/" . $filepath, $temporal, $evidencia_nombre[$doc]);
                    $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%BUY-ANEX%'");
                    $token_documento = $JwtAuth->encriptarToken($obtenCompra,$doc_name,$select_folio_doc[0]->folio);
                    $insertDocSoli = DB::table("sos_documentos")->insert(
                      array(
                        "token_documento" => $token_documento,
                        "fecha_carga" => time(),
                        "modulo" => "pagos",
                        "folio_modulo" => "BUY-ANEX" . $select_folio_doc[0]->folio,
                        "tipo_documento" => "an",
                        "nombre_documento" => $JwtAuth->encriptar($doc_name),
                        "compra" => $obtenCompra,
                        "status_documento" => TRUE,
                      )
                    );
                  }
                }
              }
  
              $validate_insert_ord_pago = false;
              $orden_de_pago_vinculada = "";
              if ($vEmp->jerarquia_main == 'P') {
                $folioRecepcionOrden = DB::select("SELECT COALESCE(MAX(ord_rec.folio_recepcion) + 1, 1) AS folio FROM eegr_compras_orden_recepcion AS ord_rec JOIN main_empresas AS emp 
                  ON ord_rec.empresa = emp.id JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                  WHERE emp.empresa_token = ? AND users.usuario_token = ?",[$empresa, $usuario]);

                $orden_recept = new OrdenRecepcionModelo();
                $orden_recept->uuid_orden_recepcion = Str::uuid()->toString();
                $orden_recept->folio_recepcion = $folioRecepcionOrden[0]->folio;
                $orden_recept->fecha_recepcion = time();
                $orden_recept->fecha_contabilizacion_recep = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
                $orden_recept->proveedor = $idProveedor;
                $orden_recept->orden_compra = $obtenCompra;
                $orden_recept->almacen = $tipoLugarEntrega == 'establecimiento' ? $idDireccionEst : NULL;
                $orden_recept->estado = 'pendiente';//, -- 'pendiente', 'parcial', 'completa', 'cancelada'
                $orden_recept->orden_bloqueada = !$classRecibeArtPago ? FALSE : TRUE;
                $orden_recept->observaciones = NULL;
                $orden_recept->empresa = $vEmp->id; //cifrado
                $newOrderRecept = $orden_recept->save();
              }

              $contadorDetallecompra = 0;
              for ($i = 0; $i < count($cfdi_conceptos); $i++) {
                $validUpdtProd = false;
                $validUpdtServ = false;
                $NoIdentificacion = $cfdi_conceptos[$i]['NoIdentificacion'];
                $ObjetoImp = $cfdi_conceptos[$i]['ObjetoImp'];
                $ClaveProdServ = $cfdi_conceptos[$i]['ClaveProdServ'];
                $tokenArticulo = $cfdi_conceptos[$i]['articulo_homologado_token'];
                $identificador = $cfdi_conceptos[$i]['articulo_homologado_identificador'];
                $concepto = $cfdi_conceptos[$i]['Descripcion'];
                $precioUnitario = $cfdi_conceptos[$i]['ValorUnitario'];
                $cantidad = $cfdi_conceptos[$i]['Cantidad'];
                $ClaveUnidad = $cfdi_conceptos[$i]['ClaveUnidad'];
                $Unidad = $cfdi_conceptos[$i]['Unidad'];
                $descuentoXUni = $cfdi_conceptos[$i]['Descuento'];
                $total_descuento = $descuentoXUni != '' && $descuentoXUni != '---' && $descuentoXUni != '0.00' ? $descuentoXUni : '0.00';
                $iva = $cfdi_conceptos[$i]['articulo_homologado_iva'];
                $retenciones = $cfdi_conceptos[$i]['retenciones'];
                $TotalRetenciones = $cfdi_conceptos[$i]['TotalRetenciones'];
                //$retenciones_homologada = $cfdi_conceptos[$i]['retencion_token'];
                $traslados = $cfdi_conceptos[$i]['traslados'];
                $TotalTraslados = $cfdi_conceptos[$i]['TotalTraslados'];
                //$traslados_homologada = $cfdi_conceptos[$i]['traslado_token'];
                $Subtotal = $cfdi_conceptos[$i]['Subtotal'];
                $usoArticulo = $cfdi_conceptos[$i]['articulo_homologado_uso'];
                $efectoFiscalArticulo = $cfdi_conceptos[$i]['articulo_homologado_efecto_fiscal'];
                $alm_serie = $cfdi_conceptos[$i]['articulo_homologado_serie_token'];
                $alm_lote = $cfdi_conceptos[$i]['articulo_homologado_lote_token'];
                $alm_pedimento = $cfdi_conceptos[$i]['articulo_homologado_pedimento_token'];
                $activoFijo = $cfdi_conceptos[$i]['articulo_homologado_activoFijo'];
                $activoIntangible = $cfdi_conceptos[$i]['articulo_homologado_activoDiferido'];
                $prorratea = $cfdi_conceptos[$i]['articulo_homologado_prorratea'];

                $importe = $cfdi_conceptos[$i]['Importe'];
                $token_unidad_medida = $cfdi_conceptos[$i]['Unidad'];

                $token_producto = '';
                $token_servicio = '';
                $total_retenciones = '';
                $total_traslado = '';
                $activos_fijos = '';
                $activos_intangibles = '';
                $pedimento_aduanal = NULL;
                $boolprorratea = FALSE;
                
                $catProdServ = DB::table(DB::raw('(SELECT
                  CASE
                    WHEN ? IN (
                      SELECT token_cat_productos 
                      FROM in_egr_catalogo_productos AS catprod 
                      JOIN main_empresas AS emp ON catprod.admin_empresa = emp.id
                      JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa
                      JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                      WHERE catprod.modulo_mostrador = FALSE 
                        AND catprod.status = TRUE 
                        AND emp.empresa_token = ?
                        AND users.usuario_token = ?
                    ) THEN "Producto"
                    WHEN ? IN (
                      SELECT token_cat_servicios 
                      FROM in_egr_catalogo_servicios AS catserv 
                      JOIN main_empresas AS emp ON catserv.administrador = emp.id
                      JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa 
                      JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                      WHERE catserv.proceso = "c" 
                        AND catserv.status = TRUE 
                        AND emp.empresa_token = ? 
                        AND users.usuario_token = ?
                    ) THEN "Servicio"
                  END AS identificador) AS subconsulta'))
                ->select('identificador')
                ->setBindings([
                  $tokenArticulo,
                  $empresa,
                  $usuario,
                  $tokenArticulo,
                  $empresa,
                  $usuario
                ])
                ->value("identificador");

                $token_producto = $catProdServ == 'Producto' ? DB::table("in_egr_catalogo_productos")->where("token_cat_productos",$tokenArticulo)->value("id") : NULL;
                $token_servicio = $catProdServ == 'Producto' ? NULL : DB::table("in_egr_catalogo_servicios")->where("token_cat_servicios",$tokenArticulo)->value("id");
                $serie = $catProdServ == 'Producto' && $alm_serie != '' ? DB::table("inventarios_catalogo_series")->where("serie_token",$alm_serie)->value("id") : NULL;
                $lote = $catProdServ == 'Producto' && $alm_lote != '' ? DB::table("inventarios_catalogo_lotes")->where("token_lote",$alm_lote)->value("id") : NULL;
                $pedimento_aduanal = $catProdServ == 'Producto' && $alm_pedimento != '' ? DB::table("inventarios_catalogo_pedimento_aduanal")->where("token_pedimento",$alm_pedimento)->value("id") : NULL;
                $activos_fijos = $catProdServ == 'Producto' && $usoArticulo == 'activo_fijo' && isset($activoFijo) && !empty($activoFijo) ? DB::table("eegr_activos_fijos_catalogo")->where("token_act_fijos",$activoFijo)->value("id") : NULL;
                $activos_intangibles = $catProdServ == 'Servicio' && $usoArticulo == 'activo_diferido' && isset($activoIntangible) && !empty($activoIntangible) ? DB::table("eegr_activos_intangibles_catalogo")->where("token_act_intang",$activoIntangible)->value("id") : NULL;

                $tokenDetalleCompra = $JwtAuth->encriptarToken(time().$token_producto.$token_servicio.$tokenArticulo.$identificador.$concepto.$precioUnitario.$cantidad.$total_descuento.$iva.$usoArticulo.$alm_serie.
                  $alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$importe);

                if (count($retenciones) != 0) {
                  $sumaImporteimp = 0;
                  for ($t = 0; $t < count($retenciones); $t++) {
                    //$importe = $retenciones[$t]["TipoFactor"] != "Exento" ? $retenciones[$t]["Importe"] : 0;
                    $importe = $retenciones[$t]["TipoFactor"] != "Exento" || (isset($retenciones[$t]["Importe"]) && $retenciones[$t]["Importe"] != 0) ? $retenciones[$t]["Importe"] : 0;
                    $sumaImporteimp = $sumaImporteimp + $importe;
                  }
                  $total_retenciones = $sumaImporteimp;
                } else {
                  $total_retenciones = '0.00';
                }

                if (count($traslados) != 0) {
                  $sumaImporteimp = 0;
                  for ($t = 0; $t < count($traslados); $t++) {
                    //$importe = $traslados[$t]["TipoFactor"] != "Exento" ? $traslados[$t]["Importe"] : 0;
                    $importe = $traslados[$t]["TipoFactor"] != "Exento" || (isset($traslados[$t]["Importe"]) && $traslados[$t]["Importe"] != 0) ? $traslados[$t]["Importe"] : 0;
                    $sumaImporteimp = $sumaImporteimp + $importe;
                  }

                  $total_traslado = $sumaImporteimp;
                } else {
                  $total_traslado = '0.00';
                }

                $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
                
                $insertDetCompra = DB::table('eegr_compras_detalle')
                ->insert(array(
                  "token_detcompra" => $tokenDetalleCompra,
                  "numero_compra" => $obtenCompra,
                  "concepto_cfdi" => $JwtAuth->encriptar($concepto),
                  "producto" => $token_producto,
                  "servicio" => $token_servicio,
                  "moneda_detalle_compra" => $cfdi_Moneda,
                  "tipo_de_cambio_detalle_compra" => $cfdi_TipoCambio,
                  "precio_unitario" => $precioUnitario,
                  "cantidad" => $cantidad,
                  "unidad_medida" => $token_unidad_medida,
                  "descuento" => $total_descuento,
                  "retenciones_total" => $total_retenciones,
                  //"retencion_homologada" => $rete_homologada,
                  "traslados_total" => $total_traslado,
                  //"traslado_homologado" => $tras_homologado,
                  "destino" => $usoArticulo,
                  "efecto_fiscal" => $vItem_efectoFiscalArticulo ? $efectoFiscalArticulo : NULL,
                  "activo_fijo" => $activos_fijos,
                  "activo_intangible" => $activos_intangibles,
                  "prorrateo" => $prorratea ? TRUE : FALSE,
                  "empresa" => $vEmp->id,
                ));

                $uuid_cfdi_detalle = Str::uuid()->toString();
                $insertDetCFDICompra = DB::table('cfdi_comprobantes_conceptos')
                ->insert(array(
                  "uuid_cfdi_detalle" => $uuid_cfdi_detalle,
                  //"comprobante_fiscal" => DB::table("cfdi_comprobantes_fiscales")->where("cfdi_comprobantes_token",$cfdi_comprobantes_token)->value("id"),
                  "numero_compra" => $obtenCompra,
                  "NoIdentificacion" => $NoIdentificacion,
                  "ObjetoImp" => $ObjetoImp,
                  "ClaveProdServ" => $ClaveProdServ,
                  "Cantidad" => $cantidad,
                  "ClaveUnidad" => $ClaveUnidad,
                  "Unidad" => $Unidad,
                  "Descripcion" => $concepto,
                  "ValorUnitario" => $precioUnitario,
                  "Descuento" => $descuentoXUni,
                  "Importe" => $importe,
                  "TotalRetenciones" => $TotalRetenciones,
                  "TotalTraslados" => $TotalTraslados,
                  "Subtotal" => $Subtotal,
                  "empresa" => $vEmp->id
                ));

                //return response()->json(['status' => 'error','code' => 200,'message' => "det compra serve"]);
                $selectDetBuy = DB::select("SELECT detcomp.id FROM eegr_compras_detalle AS detcomp JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                  WHERE detcomp.token_detcompra = ? AND detcomp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                  [$tokenDetalleCompra, $empresa, $usuario]);


                if (count($retenciones) != 0) {
                  for ($r = 0; $r < count($retenciones); $r++) {
                    $retencion_traslado = "rete";
                    $Base = $retenciones[$r]["Base"] ? $retenciones[$r]["Base"] : 0.00;
                    $Impuesto = $retenciones[$r]["Impuesto"] ? $retenciones[$r]["Impuesto"] : 000;
                    $TipoFactor = $retenciones[$r]["TipoFactor"] ? $retenciones[$r]["TipoFactor"] : NULL;
                    $TasaOCuota = $retenciones[$r]["TasaOCuota"] ? $retenciones[$r]["TasaOCuota"] : NULL;
                    $importe = $retenciones[$r]["Importe"] ? $retenciones[$r]["Importe"] : 0.00;
                    $impuesto_relacionado = $retenciones[$r]["impuesto_relacionado"];
                    $rete_homologada = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$impuesto_relacionado)->value("id") : NULL;
                    $tokenDetBuyImp = $JwtAuth->encriptarToken(time().$obtenCompra.$selectDetBuy[0]->id.$rete_homologada.$retencion_traslado.$Base.$Impuesto.$TipoFactor.$TasaOCuota.$importe);
                    
                    $insertDetImpuestoCompra = DB::table('eegr_compras_detalle_impuestos')
                    ->insert(array(
                      "token_imp_det_buy" => $tokenDetBuyImp,
                      "detalle_compra" => $selectDetBuy[0]->id,	
                      "retencion_traslado" => $retencion_traslado,
                      "base" => $Base,
                      "impuesto" => $Impuesto,
                      "tipo_factor" => $TipoFactor,
                      "tasa_cuota" => $TasaOCuota,
                      "importe" => $importe,
                      "impuesto_relacionado" => $rete_homologada
                    ));
                  }
                }
  
                if (count($traslados) != 0) {
                  for ($t = 0; $t < count($traslados); $t++) {
                    $retencion_traslado = "tras";
                    $Base = $traslados[$t]["Base"] ? $traslados[$t]["Base"] : 0.00;
                    $Impuesto = $traslados[$t]["Impuesto"] ? $traslados[$t]["Impuesto"] : 000;
                    $TipoFactor = $traslados[$t]["TipoFactor"] ? $traslados[$t]["TipoFactor"] : NULL;
                    $TasaOCuota = $traslados[$t]["TasaOCuota"] ? $traslados[$t]["TasaOCuota"] : NULL;
                    $importe = $traslados[$t]["Importe"] ? $traslados[$t]["Importe"] : 0.00;
                    $impuesto_relacionado = $traslados[$t]["impuesto_relacionado"];
                    $tras_homologado = $impuesto_relacionado != "" ? DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto",$impuesto_relacionado)->value("id") : NULL;
                    $tokenDetBuyImp = $JwtAuth->encriptarToken(time().$obtenCompra.$selectDetBuy[0]->id.$tras_homologado.$retencion_traslado.$Base.$Impuesto.$TipoFactor.$TasaOCuota.$importe);
                    
                    $insertDetCompra = DB::table('eegr_compras_detalle_impuestos')
                    ->insert(array(
                      "token_imp_det_buy" => $tokenDetBuyImp,
                      "detalle_compra" => $selectDetBuy[0]->id,	
                      "retencion_traslado" => $retencion_traslado,
                      "base" => $Base,
                      "impuesto" => $Impuesto,
                      "tipo_factor" => $TipoFactor,
                      "tasa_cuota" => $TasaOCuota,
                      "importe" => $importe,
                      "impuesto_relacionado" => $tras_homologado
                    ));
                  }
                }

                if ($prorratea) {
                    
                  $folioProrrateo = DB::selectOne("SELECT COALESCE(MAX(fold.folder) + 1, 1) AS folio FROM sos_last_folders AS fold JOIN main_empresas AS emp ON fold.empresa = emp.id
                  JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                  WHERE fold.egr_prorrateos = TRUE AND emp.empresa_token = ? AND users.usuario_token = ?",[$empresa,$usuario]);
                    
                  //return response()->json(['message' => 'error GeneralesCompra'.$folioProrrateo->folio,'codigo' => 200,'status' => 'error']);
                  $tokenCompraProrrateo = $JwtAuth->encriptarToken(time().$token_producto.$token_servicio.$identificador.$concepto.$precioUnitario.$cantidad.$total_descuento.$iva.$usoArticulo.$alm_serie.$alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$prorratea);

                  //return response()->json(['message' => 'error GeneralesCompra'.$importeMinVi,'codigo' => 200,'status' => 'error']);

                  $insertDetCompra = DB::table('eegr_compras_prorrateos')
                  ->insert(array(
                    "token_prorrateo" => $tokenCompraProrrateo,
                    "folio_prorrateo" => $folioProrrateo->folio,	
                    "fecha_sistema_prorrateo" => time(),	
                    "fecha_prorrateo" => time(),	
                    "producto" => $token_producto,	
                    "servicio" => $token_servicio,	
                    "compra" => $obtenCompra,	
                    "detalle_compra" => $selectDetBuy[0]->id,	
                    "empresa"	 => $vEmp->id,	
                    "status_prorrateo" => TRUE,
                  ));

                  if ($folioProrrateo->folio == 1) {
                    $insertSistema = DB::table('sos_last_folders')
                      ->insert(
                        array(
                          "egr_prorrateos" => TRUE,
                          "folder" => 1,
                          "empresa" => $vEmp->id,
                        )
                      );
                  } else {
                    $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                    ->where([
                      'sos_last_folders.egr_prorrateos' => TRUE,
                      'emp.empresa_token' => $empresa,
                      'users.usuario_token' => $usuario,
                    ])
                    ->limit(1)->update(
                      array(
                        'sos_last_folders.folder' => $folioProrrateo->folio,
                      )
                    );
                  }

                  $obten_prorrateo_ident =DB::table("eegr_compras_prorrateos")->where("token_prorrateo",$tokenCompraProrrateo)->value("id");
                  $token_detalle_prorrt = $JwtAuth->encriptarToken(time().$obten_prorrateo_ident.$iva.$usoArticulo.$alm_serie.$alm_lote.$alm_pedimento.$activoFijo.$activoIntangible.$prorratea);
                  $insertDetCompra = DB::table('eegr_compras_prorrateos_detalle')
                  ->insert(array(
                    "token_detalle_prorrt" => $token_detalle_prorrt,
                    "prorrateo" => $obten_prorrateo_ident,	
                    "detalle_compra" => $selectDetBuy[0]->id,
                  ));
                }

                if ($token_producto != NULL && $token_producto != '') {
                  $selectDetBuy = DB::select("SELECT detcomp.id FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_productos AS catprod 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE detcomp.token_detcompra = ? 
                    AND detcomp.producto = catprod.id AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? 
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                    [$tokenDetalleCompra, $tokenArticulo, $empresa, $usuario]
                  );

                  $folioKardex = DB::select("SELECT IF (max(dexkar.folio_kardex) IS NOT NULL,(max(dexkar.folio_kardex)+1),1) AS folio 
                    FROM in_egr_productos_kardex AS dexkar JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE dexkar.producto_id = catprod.id 
                    AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                    AND empuser.usuario = users.id AND users.usuario_token = ?", [$tokenArticulo, $empresa, $usuario]);

                  $token_kardex =  $JwtAuth->encriptarToken(time(),$tokenCompra, $tokenDetalleCompra);

                  $insertKardex = DB::table("in_egr_productos_kardex")
                    ->insert(array(
                      "token_kardex" => $token_kardex,
                      "folio_kardex" => $folioKardex[0]->folio,
                      "fecha_kardex" => time(),
                      "status_kardex" => 2,
                      "producto" => $token_producto,
                      "concepto" => "por recibir",
                      "factura_compra" => $obtenCompra,
                      "detalle_compra" => $selectDetBuy[0]->id,
                      "recibir_cantidad" => $cantidad,
                      "valor_unitario" => $precioUnitario,
                    ));
                    //return response()->json(['status' => 'error','code' => 200,'message' => "total_descuento ".$total_descuento]);
                    $upDateProducto = DB::table('in_egr_catalogo_productos')
                    ->join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                    ->where([
                      'in_egr_catalogo_productos.status' => TRUE,
                      'in_egr_catalogo_productos.id' => $token_producto,
                      'emp.empresa_token' => $empresa,
                      'users.usuario_token' => $usuario,
                    ])
                    ->limit(1)->update(array("in_egr_catalogo_productos.ultima_compra" => time(),));
                    $validUpdtProd = $upDateProducto ? true : false;
                } else {
                  $validUpdtProd = true;
                }

                if ($token_servicio != NULL && $token_servicio != '') {
                  $upDateServicio = DB::table('in_egr_catalogo_servicios')
                  ->join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
                  ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                  ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                  ->where([
                    'in_egr_catalogo_servicios.status' => TRUE,
                    'in_egr_catalogo_servicios.id' => $token_servicio,
                    'emp.empresa_token' => $empresa,
                    'users.usuario_token' => $usuario,
                  ])
                  ->limit(1)->update(array("in_egr_catalogo_servicios.ultima_compra" => time(),));
                  $validUpdtServ = $upDateServicio ? true : false;
                } else {
                  $validUpdtServ = true;
                }

                if ($insertDetCompra && $validUpdtProd == true && $validUpdtServ == true) {
                  ++$contadorDetallecompra;
                }
              }

              if ($insertCompra && $contadorDetallecompra == count($cfdi_conceptos)) {
                $JwtAuth->insertBitacoraActividad(
                  'egresos',
                  'compras',
                  'compras',
                  $folio_buy,
                  'registro en el alta de compras',
                  $empresa,
                  $usuario
                );

                if (count($folioSistema) == 0) {
                  $insertSistema = DB::table('sos_last_folders')
                    ->insert(
                      array(
                        "egr_compras" => TRUE,
                        "folder" => 1,
                        "post_folder" => $post_folio,
                        "empresa" => $vEmp->id,
                      )
                    );
                } else {
                  $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                    ->where([
                      'sos_last_folders.egr_compras' => TRUE,
                      'emp.empresa_token' => $empresa,
                      'users.usuario_token' => $usuario,
                    ])
                    ->limit(1)->update(
                      array(
                        'sos_last_folders.folder' => $folio_nuevo,
                        'sos_last_folders.post_folder' => $post_folio,
                      )
                    );
                }

                $dataMensaje = array(
                  'message' => 'Compra registrada y autorizada con el folio '.$folio_buy.($validate_insert_ord_pago ? ', revise ordenes de pago' : ''),'code' => 200,'status' => 'success','token_compras' => $tokenCompra
                );
              }

            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Esta compra no fue terminada debido a errores internos'
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => $detalleErrores
          );
        }
        //$dataMensaje = array('status' => 'error','code' => 200,'message' => 'mensaje_error_main'.$receptFactura);
      } else {
        $mensaje_error_main = '';
        if (!$permisosCreacion) {$mensaje_error_main = 'No tiene permisos para registrar esta compra';}
        if (!$validate_fecha_contabilizacion) {$mensaje_error_main = 'Error en fecha de contabilización, verifique su información o comuniquese a soporte';}
        if (!$validate_fecha_vencimiento) {$mensaje_error_main = 'Error en fecha de vencimiento, verifique su información o comuniquese a soporte';}
        if (!$validate_total) {$mensaje_error_main = 'Error en total de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$validate_prov) {$mensaje_error_main = 'Error al seleccionar proveedor, verifique su información o comuniquese a soporte';}
        if (!$validate_cfdi_conceptos) {$mensaje_error_main = 'No se encontro listado de productos y/o servicios sobre esta compra, verifique su información o comuniquese a soporte';}
        if (!$validate_cfdi_impuestos_retenidos) {$mensaje_error_main = 'Error en impuestos retenidos de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$validate_cfdi_impuestos_trasladados) {$mensaje_error_main = 'Error en impuestos trasladados de su CFDI, verifique su información o comuniquese a soporte';}
        if (!$validate_compra_contado_credito) {$mensaje_error_main = 'Error en seleccion de compra a crédito o contado, verifique su información o comuniquese a soporte';}
        if (!$validate_classRecibeArtPago) {$mensaje_error_main = 'No se encontro respuesta a recepcion de articulos antes o despues de pago sobre esta compra, verifique su información o comuniquese a soporte';}
        if (!file_exists($request->file('imagenEvidenciaVerificacion'))) {$mensaje_error_main = 'Debe cargar el documento de verificación de comprobante fiscal degital correspondiente a esta compra';}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}