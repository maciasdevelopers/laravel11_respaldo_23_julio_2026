<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\ComprasModelo;
use App\Models\OrdenPagoModelo;
use App\Models\OrdenRecepcionModelo;
use App\Models\OrdenDevengacionModelo;

class EGRE_ComprasRegistroManualController extends Controller{
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
            $retencion_token = $retenciones[$t]["impuesto_relacionado_token"]; 
            $retencion_nombre = $retenciones[$t]["impuesto_relacionado_nombre"]; 
            $impRetenido = DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $retencion_token)->exists();  
            
            if ($impRetenido) {
              ++$countValidateRetencionesConcept;
            } else {
              $detalleErrores = "El impuesto homologado con token {$retencion_nombre} no existe.";
              break;
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
            $traslado_token = $traslados[$t]["impuesto_relacionado_token"]; 
            $traslado_nombre = $traslados[$t]["impuesto_relacionado_nombre"]; 
            $impTrasladado = DB::table("cont_impuestos_catalogo")->where("token_catalogo_impuesto", $traslado_token)->exists();  
            
            if ($impTrasladado) {
              ++$countValidateTrasladosConcept;
            } else {
              $detalleErrores = "El impuesto homologado con token {$traslado_nombre} no existe.";
              break;
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

  private function registraAnticipoCompra($JwtAuth,$token_proveedor,$emp_id,$usuario,$user_id,$anticipo_aplicado,$compra_observaciones,$fecha_contabilizacion,$tipoDeCambio,$compra_moneda,$orden_de_pago_vinculada){
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
        "deu_tipo_cambio" => $tipoDeCambio,
        "deu_mov_moneda" => $compra_moneda,
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

  private function registraArticuloCompra($JwtAuth,$detBuy,$emp_id,$obtenCompra,$compra_moneda,$tipoDeCambio){
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
    $totalRetenciones = $detBuy['TotalRetenciones'];
    $traslados = $detBuy['traslados'];
    $totalTraslados = $detBuy['TotalTraslados'];
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
  
    $vItem_efectoFiscalArticulo = isset($efectoFiscalArticulo) && !empty($efectoFiscalArticulo) && preg_match($JwtAuth->filtroAlfaNumerico(), $efectoFiscalArticulo);
    
    $id_compra_detalle = DB::table('eegr_compras_detalle')
    ->insertGetId([
      'token_detcompra'               => $tokenDetalleCompra,
      'numero_compra'                 => $obtenCompra,
      'concepto_cfdi'                 => $JwtAuth->encriptar($concepto),
      'producto'                      => $id_producto,
      'servicio'                      => $id_servicio,
      'moneda_detalle_compra'         => $compra_moneda,
      'tipo_de_cambio_detalle_compra' => $tipoDeCambio,
      'precio_unitario'               => $precioUnitario,
      'cantidad'                      => $cantidad,
      'unidad_medida'                 => $token_unidad_medida,
      'descuento'                     => $total_descuento,
      'retenciones_total'             => $totalRetenciones,
      //'retencion_homologada'          => $rete_homologada,
      'traslados_total'               => $totalTraslados,
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

    $this->procesarImpuestos($id_compra_detalle, $retenciones ?? [], 'rete', $JwtAuth,$totalRetenciones);
    $this->procesarImpuestos($id_compra_detalle, $traslados ?? [], 'tras', $JwtAuth,$totalTraslados);
    
    return $id_compra_detalle;
  }

  private function procesarImpuestos($idDetalle, $impuestos, $tipo, $JwtAuth,$totalRegistrado) {
    if (empty($impuestos)) return;
    $dataToInsert = [];
    foreach ($impuestos as $imp) {
      $impRelacionado = $imp["impuesto_relacionado_token"] ?? "";
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
        "base"                 => 0.00,
        "impuesto"             => '000',
        "tipo_factor"          => null,
        "tasa_cuota"           => null,
        "importe"              => $totalRegistrado,
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

  private function procesarActivoFijo($JwtAuth, $selectDetBuy, $detBuy, $compra_moneda, $tipoDeCambio, $emp_id) {
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
    $total_retenciones = $detBuy['TotalRetenciones'] ? $detBuy['TotalRetenciones'] : 0;
    $total_traslados = $detBuy['TotalTraslados'] ? $detBuy['TotalTraslados'] : 0;
    
    $uuid_activo_fijo_det = Str::uuid()->toString();//(string) Str::uuid();
    
    // 2. Inserción del registro Maestro (Detalle de Activo)
    // Usamos insertGetId para obtener el ID real y evitar consultas extras dentro del bucle
    //echo $emp_id;
    $id_det_insertado = DB::table('eegr_activos_fijos_detalle')->insertGetId([
      "token_det_activo_fijo" => $uuid_activo_fijo_det,
      "activo_fijo"           => $id_activo_fijo,
      "compra_detalle"        => $selectDetBuy,
      "concepto"              => $JwtAuth->encriptar($detBuy['Descripcion']),
      "moneda"                => $compra_moneda,
      "tipo_de_cambio"        => $tipoDeCambio,
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
    $unidadesFijosParaInsertar = [];

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
      $unidadesFijosParaInsertar[] = [
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
    if (!empty($unidadesFijosParaInsertar)) {
      $insertUnidades = DB::table('eegr_activos_fijos_unidades')->insert($unidadesFijosParaInsertar);

      if (!$insertUnidades) {
        throw new \Exception("Error crítico al registrar las unidades individuales de los activos.");
      }
    }
  }

  private function procesarActivoDiferido($JwtAuth, $selectDetBuy, $detBuy, $compra_moneda, $tipoDeCambio, $emp_id) {
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
    $total_retenciones = $detBuy['TotalRetenciones'] ? $detBuy['TotalRetenciones'] : 0;
    $total_traslados = $detBuy['TotalTraslados'] ? $detBuy['TotalTraslados'] : 0;
    
    $uuid_activo_fijo_det = Str::uuid()->toString();//(string) Str::uuid();
    
    // 2. Inserción del registro Maestro (Detalle de Activo)
    // Usamos insertGetId para obtener el ID real y evitar consultas extras dentro del bucle
    //echo $emp_id;
    $id_det_insertado = DB::table('eegr_activos_intangibles_detalle')->insertGetId([
      "token_det_act_intang"  => $uuid_activo_fijo_det,
      "activo_intang"         => $id_activo_diferido,
      "compra_detalle"        => $selectDetBuy,
      "concepto"              => $JwtAuth->encriptar($detBuy['Descripcion']),
      "moneda"                => $compra_moneda,
      "tipo_de_cambio"        => $tipoDeCambio,
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
    $unidadesDiferidosParaInsertar = [];

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
      $unidadesDiferidosParaInsertar[] = [
        "token_activod_unidad" => Str::uuid()->toString(),
        "activod_detalle"      => $id_det_insertado, // Relación directa por ID
        "folio_activod_unidad" => $folioAutomatico,
        //costo_adquisicion
        //fecha_inicio_amortizacion
        "amort_contable_periodo" => $item['amort_contable_periodo'],
        "amort_contable_tiempo" => $item['amort_contable_tiempo'],
        "amort_contable_fecha_apartir" => $item['amort_contable_fecha_apartir'] ? $JwtAuth->convierteFechaEpoc($item['amort_contable_fecha_apartir']) : NULL,
        "amort_contable_observaciones" => $JwtAuth->encriptar($item['amort_contable_observaciones']),
        //fecha_ultimo_corte_contable
        //fecha_proximo_corte_contable
        "amort_fiscal_periodo" => $item['amort_fiscal_periodo'],
        "amort_fiscal_tiempo" => $item['amort_fiscal_tiempo'],
        "amort_fiscal_fecha_apartir" => $item['amort_fiscal_fecha_apartir'] ? $JwtAuth->convierteFechaEpoc($item['amort_fiscal_fecha_apartir']) : NULL,
        "amort_fiscal_observaciones" => $JwtAuth->encriptar($item['amort_fiscal_observaciones']),
        //fecha_ultimo_corte_fiscal
        //fecha_proximo_corte_fiscal
        //amortizacion_bloqueada
        //date_bloqueo_desbloqueo_prorrateo
        "empresa"              => $emp_id
      ];
      $consecutivo++;
    }

    // 4. Inserción masiva de una sola vez
    if (!empty($unidadesDiferidosParaInsertar)) {
      $insertUnidades = DB::table('eegr_activos_intangibles_unidades')->insert($unidadesDiferidosParaInsertar);

      if (!$insertUnidades) {
        throw new \Exception("Error crítico al registrar las unidades individuales de los activos.");
      }
    }
  }

  public function registrarCompraByARTICULOS(Request $request){
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
      'token_proveedor' => 'required|string',
      'compra_moneda' => 'required|string',
      'tipoDeCambio' => 'required|string',
      'compra_conceptos' => 'required|json',
      'total' => 'required|string', 
      'compra_contado_credito' => 'required|string',
      'classRecibeArtPago' => 'required|string',

      'tipoLugarEntrega' => 'required|string',
      'compra_fecha_tentativa_salida' => 'nullable|string',
      'tknLugarSalida' => 'nullable|string',
      'compra_fecha_tentativa_recepcion' => 'nullable|string',
      'tknLugarRecepcion' => 'nullable|string',

      'anticipo_aplicado' => 'nullable|numeric',
      'aplica_recepcion_facturas' => 'nullable|string',
      'compra_observaciones' => 'nullable|string',
      'pagar' => 'nullable|string'
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
      $moneda_decimales = 0;
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $fecha_vencimiento = $request->input('fecha_vencimiento');
      $token_proveedor = $request->input('token_proveedor');
      $compra_moneda = $request->input('compra_moneda');
      $tipoDeCambio = $request->input('tipoDeCambio') ? $request->input('tipoDeCambio') : "1.00";
      $compra_conceptos = json_decode($request->input('compra_conceptos'), true);
      $total = $request->input('total');
      $compra_contado_credito = $request->input('compra_contado_credito');
      $classRecibeArtPago = $request->input('classRecibeArtPago') == 'true' ? true : false;
      $tipoLugarEntrega = $request->input('tipoLugarEntrega');
      $compra_fecha_tentativa_salida = $request->input('compra_fecha_tentativa_salida');
      $tknLugarSalida = $request->input('tknLugarSalida');
      $compra_fecha_tentativa_recepcion = $request->input('compra_fecha_tentativa_recepcion');
      $tknLugarRecepcion = $request->input('tknLugarRecepcion');
      $anticipo_aplicado = $request->input('anticipo_aplicado');
      $aplica_recepcion_facturas = $request->input('aplica_recepcion_facturas');
      $compra_observaciones = $request->input('compra_observaciones');
      $compra_pagar = $request->input('pagar');

      $mi_llave_secreta = env('JWT_BUY_ID_SECRET');
      $permisosCreacion = $JwtAuth->permisosCreacion($mi_llave_secreta,$empresa,$usuario);
      $validate_fecha_contabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $validate_fecha_vencimiento = isset($fecha_vencimiento) && !empty($fecha_vencimiento) && preg_match($JwtAuth->filtroFecha(),$fecha_vencimiento);
      $idProveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores",$token_proveedor)->value("id");
      $validate_prov = isset($token_proveedor) && !empty($token_proveedor) && $idProveedor != "";

      $validate_token_moneda = isset($compra_moneda) && !empty($compra_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_moneda);
      $validate_tipoDeCambio = isset($tipoDeCambio) && !empty($tipoDeCambio) && preg_match($JwtAuth->filtroNumericoSimple(),$tipoDeCambio);
      $validate_compra_conceptos = isset($compra_conceptos) && !empty($compra_conceptos) && is_array($compra_conceptos);
      $validate_total = isset($total) && !empty($total);
      $validate_compra_contado_credito = isset($compra_contado_credito) && !empty($compra_contado_credito) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_contado_credito);
      $validate_classRecibeArtPago = isset($classRecibeArtPago) && is_bool($classRecibeArtPago);

      $validate_tipoLugarEntrega = isset($tipoLugarEntrega) && !empty($tipoLugarEntrega);
      $validate_fecha_tentativa_salida_compra = isset($compra_fecha_tentativa_salida) && !empty($compra_fecha_tentativa_salida) && preg_match($JwtAuth->filtroFecha(),$compra_fecha_tentativa_salida);
      $validate_LugarSalida_tkn = isset($tknLugarSalida) && !empty($tknLugarSalida);
      $validate_fecha_tentativa_recepcion_compra = isset($compra_fecha_tentativa_recepcion) && !empty($compra_fecha_tentativa_recepcion) && preg_match($JwtAuth->filtroFecha(),$compra_fecha_tentativa_recepcion);
      $validate_LugarRecepcion_tkn = isset($tknLugarRecepcion) && !empty($tknLugarRecepcion);

      $validate_anticipo_aplicado = isset($anticipo_aplicado) && !empty($anticipo_aplicado);
      $validate_compra_observaciones = isset($compra_observaciones) && !empty($compra_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$compra_observaciones);

      if ($permisosCreacion && $validate_fecha_contabilizacion && $validate_fecha_vencimiento && $validate_prov && $validate_token_moneda && $validate_tipoDeCambio && $validate_compra_conceptos && 
        $validate_total && $validate_compra_contado_credito && $validate_classRecibeArtPago && $validate_tipoLugarEntrega) {// && file_exists($request->file('imagenEvidenciaXMl')) && file_exists($request->file('imagenEvidenciaVerificacion'))

        $moneda_decimales = $JwtAuth->getMonedaAPI($compra_moneda ?? 'MXN');

        $tentativa_salida_compra = $tipoLugarEntrega != 'noAplica' && $validate_fecha_tentativa_salida_compra ? $JwtAuth->convierteFechaEpoc($compra_fecha_tentativa_salida) : NULL;
        $idSalidaLugar = $tipoLugarEntrega != 'proveedor' && $validate_LugarSalida_tkn ? DB::table("teci_direcciones AS dir")
        ->join("eegr_catalogo_proveedores AS catprov","dir.proveedor","=","catprov.id")
        ->where(["dir.token_direccion" => $tknLugarRecepcion,"catprov.token_cat_proveedores" => $token_proveedor])
        ->value("dir.id") : NULL;

        $tentativa_recepcion_compra = $tipoLugarEntrega != 'noAplica' && $validate_fecha_tentativa_recepcion_compra ? $JwtAuth->convierteFechaEpoc($compra_fecha_tentativa_recepcion) : NULL;
        $idRecepcionLugar = $tipoLugarEntrega != 'noAplica' && $validate_LugarRecepcion_tkn ? DB::table("in_egr_establecimientos_catalogo")->where("token_establecimiento",$tknLugarRecepcion)->value("id") : NULL;

        $detalleErrores = $this->registrarCompraValidaConceptos($compra_conceptos,$moneda_decimales,$JwtAuth);
        
        if ($detalleErrores == "") {
          $vEmp = DB::table("main_empresas AS emp")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where([
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario
          ])
          ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr','users.jerarquia_main')
          ->first();

          if ($vEmp) {
            DB::beginTransaction();
            try {
              $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                WHERE fold.egr_compras = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);
  
              $post_folio_db = DB::select("SELECT post_folio FROM eegr_compras WHERE id = (SELECT Max(catbuy.id) FROM eegr_compras AS catbuy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
                WHERE catbuy.comprador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$empresa, $usuario]);
  
              $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
              $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
              $folio_buy = 'COMP-'.$JwtAuth->generarFolio($folio_nuevo).($post_folio != NULL ? '-'.$post_folio:'');
              //return response()->json(['message' => $folio_buy,'codigo' => 200,'status' => 'error']);
              $nombreRecePago = '';
              
              $tokenCompra = $JwtAuth->encriptarToken(time(),$idProveedor,$compra_moneda,$tipoLugarEntrega,$tknLugarRecepcion);
              $fechaSistema = time();
              $fecha_altaCompra = time();
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
              $compras->compra_a_credito = $compra_contado_credito == 'contado' ? 'cont' : 'cred';
              $compras->recibeFactura = FALSE;
              $compras->aplica_recepcion_facturas = $aplica_recepcion_facturas;
              $compras->recepcionPago = $nombreRecePago; //cifrado
              $compras->moneda = $compra_moneda;
              $compras->tipo_de_cambio = $tipoDeCambio;
              $compras->anticipo = $anticipo_aplicado;
              //$compras->forma_pago = $cfdi_comprobante_forma_de_pago;
              //$compras->metodo_pago = $cfdi_comprobante_metodo_de_pago;
              //$compras->uso_cfdi = $cfdi_receptor_uso_del_cfdi;
              $compras->recibeProducto = $classRecibeArtPago ? TRUE : FALSE;// si es TRUE genera orden de pago, si es FALSE no
              $compras->pago_caja_tesoreria = NULL;
              $compras->caja_paga = NULL;
  
              $compras->fecha_tentativa_salida = $tentativa_salida_compra;
              $compras->direccion_salida_prov = $idSalidaLugar;
              $compras->recepcion_estab = $idRecepcionLugar;
              $compras->fecha_tentativa_recepcion = $tentativa_recepcion_compra;
  
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
              if ($insertCompra) {
                $obtenCompra = $compras->id;
  
                $filepath = $vEmp->root_tkn . "/0002-cpp/compras/compras/$nombreDocs/";
  
                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
                }
  
                if ($request->hasFile('compra_anexos')) {
                  $anexos = $request->file('compra_anexos');
                  // 1. Rendimiento: Consultamos el folio una sola vez fuera del ciclo
                  $conteoActual = DB::table("sos_documentos")->where('folio_modulo', 'LIKE', 'BUY-ANEX%')->count();
                  $folioSiguiente = $conteoActual + 1;
                  
                  foreach ($anexos as $archivo) {
                    if ($archivo && $archivo->isValid()) {
                      // 2. Definición de nombre original
                      $nombreOriginal = $archivo->getClientOriginalName();
                      // Usamos el nombre original directamente ya que $filepath es único por compra
                      $nombreFisico = $nombreOriginal;
                      // 3. Guardado físico en el storage
                      $storagePath = "/public/root/$filepath";
                      $saveFile = Storage::putFileAs($storagePath, $archivo, $nombreFisico);
                      if (!$saveFile) {
                        throw new \Exception("Error al guardar el archivo físico: $nombreOriginal");
                      }
                      // 4. Preparar datos y generar Token
                      $folioModulo = "BUY-ANEX" . $folioSiguiente;
                      $tokenDoc = $JwtAuth->encriptarToken($obtenCompra, $nombreOriginal, $folioSiguiente);
                      // 5. Inserción en base de datos
                      $insertDoc = DB::table("sos_documentos")->insert([
                        "token_documento"  => $tokenDoc,
                        "fecha_carga"      => time(),
                        "modulo"           => "pagos",
                        "folio_modulo"     => $folioModulo,
                        "tipo_documento"   => "an",
                        "nombre_documento" => $JwtAuth->encriptar($nombreOriginal),
                        "compra"           => $obtenCompra,
                        "status_documento" => true,
                      ]);
                      if (!$insertDoc) {
                        throw new \Exception("Error al registrar el anexo $nombreOriginal en la base de datos.");
                      }
                      // Incrementamos para el siguiente archivo
                      $folioSiguiente++;
                    }
                  }
                }
                
                // Productos y Servicios de la Empresa
                $productos = DB::table("in_egr_catalogo_productos")
                ->where(["admin_empresa" => $vEmp->id, "status" => true])
                ->pluck("id", "token_cat_productos")->toArray();
  
                $servicios = DB::table("in_egr_catalogo_servicios")
                ->where(["administrador" => $vEmp->id, "status" => true])
                ->pluck("id", "token_cat_servicios")->toArray();
  
                // Servicios GLOBALES (Activos Fijos/Diferidos)
                // Buscamos los tokens de esos folios especiales
                $globales = DB::table('in_egr_catalogo_servicios')
                ->whereIn('folio_sistema', ['999999998', '999999999'])
                ->whereNull('administrador')
                ->select('id', 'token_cat_servicios', 'folio_sistema')
                ->get()
                ->keyBy('token_cat_servicios');
                // --- 2. DETECTAR CONTENIDO ANTES O DURANTE EL CICLO ---
                $tieneProductos = false;
                $tieneServicios = false;
  
                foreach ($compra_conceptos as $oDetHave) {
                  $tokenArticulo = $oDetHave['articulo_homologado_token'];
                  
                  if (isset($productos[$tokenArticulo])) {
                    $tieneProductos = true;
                  } elseif (isset($servicios[$tokenArticulo])) {
                    $tieneServicios = true;
                  } elseif (isset($globales[$tokenArticulo])) {
                    $global = $globales[$tokenArticulo];
                    // Folio 999999998 = Activo Fijo (Entra a almacén/inventario de activos)
                    // Folio 999999999 = Activo Diferido (Gasto que se devenga)
                    if ($global->folio_sistema == '999999998') {
                      $tieneProductos = true; 
                    } else {
                      $tieneServicios = true;
                    }
                  }
                }
  
                $validate_insert_ord_pago = false;
                $orden_de_pago_vinculada = "";
                if ($vEmp->jerarquia_main == 'P') {
                  $folioOrden = DB::select("SELECT IF (max(ordenP.folio_ordenPago) IS NOT NULL,(max(ordenP.folio_ordenPago)+1),1) AS folio FROM fnzs_pagos_orden AS ordenP 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE ordenP.empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);
  
                  $tknOrder = $JwtAuth->encriptarToken(time(), $folioOrden[0]->folio, $tokenCompra);
                  $orden_de_pago_vinculada = $tknOrder;
                  $orderpay = new OrdenPagoModelo();
                  $orderpay->token_ordenPago = $tknOrder;
                  $orderpay->folio_ordenPago = $folioOrden[0]->folio; //falta generar
                  $orderpay->fecha_sistema_ordenp = $fechaSistema;
                  //$orderpay->fecha_contabilizacion_ordenPago = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
                  $orderpay->factura_compra = $obtenCompra;
                  $orderpay->ord_proveedor = $idProveedor;
                  $orderpay->doc_anterior_fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
                  $orderpay->orden_bloqueada = $compra_pagar == "pagar" || $classRecibeArtPago ? FALSE : TRUE;
                  $orderpay->autorizacion_pay = $compra_pagar == "pagar" ? TRUE : FALSE;
                  $orderpay->fecha_autorizacion_pay = $compra_pagar == "pagar" ? time() : NULL;
                  $orderpay->tentativa_pago = $compra_pagar == "pagar" ? time() : NULL;
                  $orderpay->orden_terminada_bool = FALSE;
                  $orderpay->orden_terminada_fecha = NULL;
                  $orderpay->status_ordenPago = TRUE;  //cifrado
                  $orderpay->empresa = $vEmp->id; //cifrado
                  $orderpay->comprador = $vEmp->userr; //cifrado
                  $insertOrder = $orderpay->save();
                  if (!$insertOrder) {
                    throw new \Exception("Error al guardar orden de pago de compra.");
                  }
                  //return response()->json(['status' => 'error','code' => 200,'message' => 'orden']);
                  if ($insertOrder) {
                    $validate_insert_ord_pago = true;
                  }
                }
  
                if ($vEmp->jerarquia_main == 'P' && $tieneProductos) {
                  //$folioRecepcionOrden = DB::select("SELECT COALESCE(MAX(ord_rec.folio_recepcion) + 1, 1) AS folio FROM eegr_compras_orden_recepcion AS ord_rec JOIN main_empresas AS emp 
                  //  ON ord_rec.empresa = emp.id JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                  //  WHERE emp.empresa_token = ? AND users.usuario_token = ?",[$empresa, $usuario]);
  
                  $maxFolioOrdenRecep = DB::table('eegr_compras_orden_recepcion')
                  ->where('empresa', $vEmp->id)
                  ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
                  ->max('folio_recepcion');
  
                  $folioRecepcionOrden = $maxFolioOrdenRecep ? $maxFolioOrdenRecep + 1 : 1;
  
                  $orden_recept = new OrdenRecepcionModelo();
                  $orden_recept->uuid_orden_recepcion = Str::uuid()->toString();
                  $orden_recept->folio_recepcion = $folioRecepcionOrden;//$folioRecepcionOrden[0]->folio;
                  $orden_recept->fecha_recepcion = $tentativa_recepcion_compra;
                  $orden_recept->fecha_contabilizacion_recep = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
                  $orden_recept->proveedor = $idProveedor;
                  $orden_recept->orden_compra = $obtenCompra;
                  $orden_recept->almacen = $idRecepcionLugar;
                  $orden_recept->estado = 'pendiente';//, -- 'pendiente', 'parcial', 'completa', 'cancelada'
                  $orden_recept->orden_bloqueada = !$classRecibeArtPago ? FALSE : TRUE;
                  $orden_recept->observaciones = NULL;
                  $orden_recept->empresa = $vEmp->id; //cifrado
                  $newOrderRecept = $orden_recept->save();
                  if (!$newOrderRecept) {
                    throw new \Exception("Error al guardar orden de recepción de compra.");
                  }
                }
  
                if ($vEmp->jerarquia_main == 'P' && $tieneServicios) {
                  //$folioDevengacionOrden = DB::select("SELECT COALESCE(MAX(ord_rec.folio_devengacion) + 1, 1) AS folio FROM eegr_compras_orden_devengacion AS ord_rec JOIN main_empresas AS emp 
                  //  ON ord_rec.empresa = emp.id JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
                  //  WHERE emp.empresa_token = ? AND users.usuario_token = ?",[$empresa, $usuario]);
  
                  $maxFolioOrdenDeven = DB::table('eegr_compras_orden_devengacion')
                  ->where('empresa', $vEmp->id)
                  ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
                  ->max('folio_devengacion');
  
                  $folioDevengacionOrden = $maxFolioOrdenDeven ? $maxFolioOrdenDeven + 1 : 1;
  
                  $orden_deven = new OrdenDevengacionModelo();
                  $orden_deven->uuid_orden_devengacion = Str::uuid()->toString();
                  $orden_deven->folio_devengacion = $folioDevengacionOrden;//$folioDevengacionOrden[0]->folio;
                  $orden_deven->fecha_devengacion = $tentativa_recepcion_compra;
                  $orden_deven->proveedor = $idProveedor;
                  $orden_deven->orden_compra = $obtenCompra;
                  $orden_deven->estado = 'pendiente';//, -- 'pendiente', 'parcial', 'completa', 'cancelada'
                  $orden_deven->orden_bloqueada = !$classRecibeArtPago ? FALSE : TRUE;
                  $orden_deven->observaciones = NULL;
                  $orden_deven->empresa = $vEmp->id; //cifrado
                  $newOrderDeven = $orden_deven->save();
                  if (!$newOrderDeven) {
                    throw new \Exception("Error al guardar orden de recepción de compra.");
                  }
                }
  
                if ($anticipo_aplicado > 0) {
                  $this->registraAnticipoCompra($JwtAuth,$token_proveedor,$vEmp->id,$usuario,$vEmp->userr,$anticipo_aplicado,$compra_observaciones,$fecha_contabilizacion,$tipoDeCambio,$compra_moneda,$orden_de_pago_vinculada);
                }
  
                $contadorDetallecompra = 0;
                foreach ($compra_conceptos as $vDet) {
                  $tokenArticulo = $vDet['articulo_homologado_token'];
                  $identificador = $vDet['articulo_homologado_identificador'];
                  $precioUnitario = $vDet['ValorUnitario'];
                  $cantidad = $vDet['Cantidad'];
                  $usoArticulo = $vDet['articulo_homologado_uso'];
                  $prorratea = $vDet['articulo_homologado_prorratea'];
  
                  //return response()->json(['status' => 'error','code' => 200,'message' => "det compra serve"]);
                  $selectDetBuy = $this->registraArticuloCompra($JwtAuth,$vDet,$vEmp->id,$obtenCompra,$compra_moneda,$tipoDeCambio);
                  
                  $id_producto = $productos[$tokenArticulo] ?? null;
                  $id_servicio = $servicios[$tokenArticulo] ?? null;
                  $global      = $globales->get($tokenArticulo);
                  
                  if ($id_producto) {
                    $catProdServ = 'Producto';
                    $articulo_id = $id_producto;
                  } elseif ($id_servicio) {
                    $catProdServ = 'Servicio';
                    $articulo_id = $id_servicio;
                  } elseif ($global) {
                    // Si el folio es 999999998 es Fijo, si es 999999999 es Diferido
                    $catProdServ = ($global->folio_sistema == '999999998') ? 'ActivoFijo' : 'ActivoDiferido';
                    $articulo_id = $global->id;
                  } else {
                    $catProdServ = null; // Token no encontrado en ningún catálogo
                    throw new \Exception("El artículo con token $tokenArticulo no se encuentra en ningún catálogo.");
                  }
  
                  if ($prorratea) {
                    $this->procesarProrrateo($JwtAuth, $vEmp, $id_producto, $id_servicio, $obtenCompra, $selectDetBuy, $fecha_contabilizacion);
                  }
  
                  if ($catProdServ == 'Producto') {
                    $this->procesarKardexProducto($JwtAuth, $vEmp, $id_producto, $tokenArticulo, $obtenCompra, $selectDetBuy, $cantidad, $precioUnitario, $tokenCompra);
                  }
  
                  if ($catProdServ == 'Servicio') {
                    $upDateServicio = DB::table('in_egr_catalogo_servicios')
                    ->join("main_empresas AS emp", "in_egr_catalogo_servicios.administrador", "=", "emp.id")
                    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                    ->where([
                      'in_egr_catalogo_servicios.status' => TRUE,
                      'in_egr_catalogo_servicios.id' => $id_servicio,
                      'emp.empresa_token' => $empresa,
                      'users.usuario_token' => $usuario,
                    ])
                    ->limit(1)->update(array("in_egr_catalogo_servicios.ultima_compra" => time(),));
                  }
  
                  if ($usoArticulo == 'activo_fijo') {//$identificador == 'ActivoFijo' && 
                    $this->procesarActivoFijo($JwtAuth,$selectDetBuy,$vDet,$compra_moneda,$tipoDeCambio,$vEmp->id);
                  }
  
                  if ($usoArticulo == 'activo_diferido') {//$identificador == 'ActivoDiferido' && 
                    $this->procesarActivoDiferido($JwtAuth,$selectDetBuy,$vDet,$compra_moneda,$tipoDeCambio,$vEmp->id);
                  }
  
                  ++$contadorDetallecompra;
                }
                
                if ($insertCompra && $contadorDetallecompra == count($compra_conceptos)) {
                  DB::commit();
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
                    'message' => 'Compra registrada y autorizada con el folio '.$folio_buy.($validate_insert_ord_pago ? ', revise ordenes de pago' : ''),
                    'code' => 200,
                    'status' => 'success',
                    'token_compras' => $compra_pagar == "pagar" ? $tokenCompra : null,
                    'token_proveedor' => $compra_pagar == "pagar" ? $token_proveedor : null,
                    'token_ordenPago' => $compra_pagar == "pagar" ? $orden_de_pago_vinculada : null,
                  );
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Esta compra no fue terminada debido a errores internos'
                );
              }
            } catch (\Exception $e) {
              // 7. Si algo falla, revertimos TODO en la BD
              DB::rollBack();
              // Opcional: Borrar carpetas físicas creadas en este intento
              // Storage::disk('root')->deleteDirectory($filepath);
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Error en el registro: ' . $e->getMessage(),
                'line' => $e->getLine()
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
        if (!$validate_compra_conceptos) {$mensaje_error_main = 'No se encontro listado de productos y/o servicios sobre esta compra, verifique su información o comuniquese a soporte';}
        if (!$validate_compra_contado_credito) {$mensaje_error_main = 'Error en seleccion de compra a crédito o contado, verifique su información o comuniquese a soporte';}
        if (!$validate_classRecibeArtPago) {$mensaje_error_main = 'No se encontro respuesta a recepcion de articulos antes o despues de pago sobre esta compra, verifique su información o comuniquese a soporte';}
        //if (!$validate_tipoLugarEntrega) {$mensaje_error_main = 'No se encontro respuesta a seleccion de lugar de entrega sobre esta compra, verifique su información o comuniquese a soporte';}
        if (!file_exists($request->file('imagenEvidenciaXMl'))) {$mensaje_error_main = 'Debe cargar la factura en formato xml correspondiente a esta compra';}
        if (!file_exists($request->file('imagenEvidenciaVerificacion'))) {$mensaje_error_main = 'Debe cargar el documento de verificación de comprobante fiscal degital correspondiente a esta compra';}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error_main);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}