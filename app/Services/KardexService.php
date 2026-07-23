<?php

namespace App\Services;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\DB;
use App\Models\EmpresasModelo;
use Illuminate\Support\Facades\Storage;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

class KardexService{
  private function getUltimo($productoId){
    return DB::table("in_egr_productos_kardex")->where("producto_id", $productoId)->orderBy("id", "DESC")->first();
  }

  // Estructura las columnas comunes de tu tabla
  private function camposBase($productoId, $folioKardex) {
    return [
      "token_kardex" => Str::uuid()->toString(),
      "folio_kardex" => $folioKardex,
      "fecha_kardex" => time(),
      "producto_id"  => $productoId,
    ];
  }

  //entradas
  //registro de compra por recibir en nuestro kardex
  public function registrarRecibir($productoId, $cantidad, $precioUnitario, $statusKardex, $concepto, $tipo_documento, $factura_compra, $detalle_compra) {
    $maxFolioKardex = DB::table('in_egr_productos_kardex')->where('producto_id', $productoId)
    ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
    ->max('folio_kardex');
    $folioKardex = $maxFolioKardex ? $maxFolioKardex + 1 : 1;

    $ultimo = $this->getUltimo($productoId);

    return DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardex), [
      "status_kardex"     => $statusKardex,
      "concepto"          => $concepto,
      "tipo_documento"    => $tipo_documento,
      "factura_compra"    => $factura_compra,
      "detalle_compra"    => $detalle_compra,
      "valor_unitario"    => $precioUnitario,
      "recibir_cantidad"  => $cantidad,
      "recibir_valor"     => ($cantidad * $precioUnitario),
      "saldo_cantidad"    => $ultimo ? $ultimo->saldo_cantidad : 0.00,
      "saldo_valor"       => $ultimo ? $ultimo->saldo_valor : 0.00
    ]));
  }
  
  //registro de compra por recibir en nuestro kardex/*FLUJO B: Transicionar / Compensar Estados ('en_transito_compra' o 'cancelado')*/
  public function transicionarKardexTransitoCompra($productoId, $cantidad, $precioUnitario, $statusOrigen, $statusDestino, $concepto, $tipo_documento, $factura_compra, $detalle_compra ) {
    $ultimo = $this->getUltimo($productoId);
    $saldoCant = $ultimo ? $ultimo->saldo_cantidad : 0.00;
    $saldoVal  = $ultimo ? $ultimo->saldo_valor : 0.00;

    $maxFolioKardex = DB::table('in_egr_productos_kardex')->where('producto_id', $productoId)
    ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
    ->max('folio_kardex');
    $folioKardexMovUno = $maxFolioKardex ? $maxFolioKardex + 1 : 1;
    $folioKardexMovDos = $folioKardexMovUno + 1;

    //Fila 1: Mata el origen aplicando cantidad negativa
    DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovUno), [
      "status_kardex"    => $statusOrigen,
      "concepto"         => $concepto . " (Compensación)",
      "tipo_documento"   => $tipo_documento,
      "valor_unitario"   => $precioUnitario,
      "factura_compra"   => $factura_compra,
      "detalle_compra"   => $detalle_compra,
      "recibir_cantidad" => $cantidad * -1,
      "recibir_valor"    => ($cantidad * $precioUnitario) * -1,
      "saldo_cantidad"   => $saldoCant, 
      "saldo_valor"      => $saldoVal
    ]));

    //Fila 2: Crea el nuevo hito en positivo
    return DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovDos), [
      "status_kardex"             => $statusDestino,
      "concepto"                  => $concepto . " (Nuevo Asiento)",
      "tipo_documento"            => $tipo_documento,
      "valor_unitario"            => $precioUnitario,
      "factura_compra"            => $factura_compra,
      "detalle_compra"            => $detalle_compra,
      "transito_entrada_cantidad" => $cantidad,
      "transito_entrada_valor"    => ($cantidad * $precioUnitario),
      "saldo_cantidad"            => $saldoCant, 
      "saldo_valor" => $saldoVal
    ]));
  }

  //FLUJO C: Afectación Física Real ('disponible' o 'ajuste de entrada')
  public function registrarEntradaMovimientoFisico($productoId, $cantidad, $precioUnitario, $statusOrigen, $statusDestino, $concepto, $tipo_documento, $factura_compra, $detalle_compra) {
    // 1. Obtener de manera estricta el último estado antes del bloqueo
    $ultimo = $this->getUltimo($productoId);
    $saldoCantAnterior = $ultimo ? (float)$ultimo->saldo_cantidad : 0.00;
    $saldoValAnterior  = $ultimo ? (float)$ultimo->saldo_valor : 0.00;

    $maxFolioKardex = DB::table('in_egr_productos_kardex')->where('producto_id', $productoId)
    ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
    ->max('folio_kardex');
    // CORRECCIÓN CRÍTICA: Asignación lineal indestructible de folios consecutivos
    $folioKardexMovUno = $maxFolioKardex ? $maxFolioKardex + 1 : 1;
    $folioKardexMovDos = $folioKardexMovUno + 1;

    // 3. Calcular los nuevos saldos reales físicos para el segundo movimiento
    $nuevoSaldoCantidad = $saldoCantAnterior + (float)$cantidad;
    $nuevoSaldoValor    = $nuevoSaldoCantidad * (float)$precioUnitario;

    DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovUno), [
      "status_kardex"    => $statusOrigen,
      "concepto"         => $concepto . " (Compensación)",
      "tipo_documento"   => $tipo_documento,
      "valor_unitario"   => $precioUnitario,
      "factura_compra"   => $factura_compra,
      "detalle_compra"   => $detalle_compra,
      "transito_entrada_cantidad" => $cantidad * -1,
      "transito_entrada_valor"    => ($cantidad * $precioUnitario) * -1,
      "saldo_cantidad"   => $saldoCantAnterior, 
      "saldo_valor" => $saldoValAnterior
    ]));

    DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovDos), [
      "status_kardex"    => $statusDestino,
      "concepto"         => $concepto,
      "tipo_documento"   => $tipo_documento,
      "valor_unitario"   => $precioUnitario,
      "factura_compra"   => $factura_compra,
      "detalle_compra"   => $detalle_compra,
      "entrada_cantidad" => $cantidad,
      "entrada_valor"    => ($cantidad * $precioUnitario),
      "saldo_cantidad"   => $nuevoSaldoCantidad,
      "saldo_valor"      => $nuevoSaldoValor
    ]));
    return "kardex registrado";
  }
  
  // REGISTRO DE CANCELACIÓN (Variante de tu Flujo B)
  public function registrarCancelacionExpectativaCompra($productoId, $cantidad, $precioUnitario, $statusOrigen, $concepto, $tipo_documento, $factura_compra, $detalle_compra) {
    $ultimo = $this->getUltimo($productoId);
    $saldoCantAnterior = $ultimo ? (float)$ultimo->saldo_cantidad : 0.00;
    $saldoValAnterior  = $ultimo ? (float)$ultimo->saldo_valor : 0.00;

    $maxFolioKardex = DB::table('in_egr_productos_kardex')->where('producto_id', $productoId)
    ->lockForUpdate() 
    ->max('folio_kardex');
        
    $folioKardexMovUno = $maxFolioKardex ? $maxFolioKardex + 1 : 1;
    $folioKardexMovDos = $folioKardexMovUno + 1;

    $esTransito = ($statusOrigen === 'en_transito_compra');

    // Fila 1: Neutraliza el impacto del estado actual (ej: 'por_recibir' o 'en_transito_compra')
    DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovUno), [
      "status_kardex"             => $statusOrigen, // El estado que vas a anular
      "concepto"                  => $concepto . " (Anulación de " . $statusOrigen . ")",
      "tipo_documento"            => $tipo_documento,
      "valor_unitario"            => $precioUnitario,
      "factura_compra"            => $factura_compra,
      "detalle_compra"            => $detalle_compra,
      "recibir_cantidad"          => !$esTransito ? ($cantidad * -1) : null,
      "recibir_valor"             => !$esTransito ? (($cantidad * $precioUnitario) * -1) : null,
      "transito_entrada_cantidad" => $esTransito ? ($cantidad * -1) : null,
      "transito_entrada_valor"    => $esTransito ? (($cantidad * $precioUnitario) * -1) : null,
      "saldo_cantidad"            => $saldoCantAnterior, 
      "saldo_valor"               => $saldoValAnterior
    ]));

    // Fila 2: Asienta el registro oficial histórico de la cancelación
    return DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovDos), [
      "status_kardex"    => 'cancelado', // Tu nuevo estado explícito de texto
      "concepto"         => $concepto . " (Registro de Cancelación)",
      "tipo_documento"   => $tipo_documento,
      "valor_unitario"   => $precioUnitario,
      "factura_compra"   => $factura_compra,
      "detalle_compra"   => $detalle_compra,
      "recibir_cantidad" => $cantidad, // Suma al histórico de lo cancelado bajo este estatus
      "recibir_valor"    => ($cantidad * $precioUnitario),
      "saldo_cantidad"   => $saldoCantAnterior, 
      "saldo_valor"      => $saldoValAnterior
    ]));
  }

  //salidas
  public function registrarVentaExpectativa($productoId, $cantidad, $precioUnitario, $statusKardex, $concepto, $tipo_documento, $factura_venta, $detalle_venta) {
    $maxFolioKardex = DB::table('in_egr_productos_kardex')->where('producto_id', $productoId)
    ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
    ->max('folio_kardex');
    $folioKardex = $maxFolioKardex ? $maxFolioKardex + 1 : 1;

    $ultimo = $this->getUltimo($productoId);

    return DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardex), [
      "status_kardex"     => $statusKardex, // 'comprometido'
      "concepto"          => $concepto,
      "tipo_documento"    => $tipo_documento,
      "factura_venta"     => $factura_venta,
      "detalle_venta"     => $detalle_venta, 
      "valor_unitario"    => $precioUnitario,
      "entregar_cantidad" => $cantidad,
      "entregar_valor"    => ($cantidad * $precioUnitario),
      "saldo_cantidad"    => $ultimo ? $ultimo->saldo_cantidad : 0.00,
      "saldo_valor"       => $ultimo ? $ultimo->saldo_valor : 0.00
    ]));
  }

  public function registrarCancelacionExpectativaVenta($productoId, $cantidad, $precioUnitario, $statusOrigen, $concepto, $tipo_documento, $factura_venta, $detalle_venta) {
    $ultimo = $this->getUltimo($productoId);
    $saldoCantAnterior = $ultimo ? (float)$ultimo->saldo_cantidad : 0.00;
    $saldoValAnterior  = $ultimo ? (float)$ultimo->saldo_valor : 0.00;

    $maxFolioKardex = DB::table('in_egr_productos_kardex')->where('producto_id', $productoId)
    ->lockForUpdate() 
    ->max('folio_kardex');
        
    $folioKardexMovUno = $maxFolioKardex ? $maxFolioKardex + 1 : 1;
    $folioKardexMovDos = $folioKardexMovUno + 1;

    $esTransito = ($statusOrigen === 'en_transito');

    // Fila 1: Neutraliza el impacto del estado actual (ej: 'por_entregar' o 'en_transito_venta')
    DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovUno), [
      "status_kardex"            => $statusOrigen, 
      "concepto"                 => $concepto . " (Anulación de " . $statusOrigen . ")",
      "tipo_documento"           => $tipo_documento,
      "valor_unitario"           => $precioUnitario,
      "factura_venta"            => $factura_venta,
      "detalle_venta"            => $detalle_venta,
      "entregar_cantidad"        => !$esTransito ? ($cantidad * -1) : null,
      "entregar_valor"           => !$esTransito ? (($cantidad * $precioUnitario) * -1) : null,
      "transito_salida_cantidad" => $esTransito ? ($cantidad * -1) : null,
      "transito_salida_valor"    => $esTransito ? (($cantidad * $precioUnitario) * -1) : null,
      "saldo_cantidad"           => $saldoCantAnterior, 
      "saldo_valor"              => $saldoValAnterior
    ]));

    // Fila 2: Asienta el registro oficial histórico de la cancelación
    return DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovDos), [
      "status_kardex"     => 'cancelado', 
      "concepto"          => $concepto . " (Registro de Cancelación de Venta)",
      "tipo_documento"    => $tipo_documento,
      "valor_unitario"    => $precioUnitario,
      "factura_venta"     => $factura_venta,
      "detalle_venta"     => $detalle_venta,
      "entregar_cantidad" => $cantidad, 
      "entregar_valor"    => ($cantidad * $precioUnitario),
      "saldo_cantidad"    => $saldoCantAnterior, 
      "saldo_valor"       => $saldoValAnterior
    ]));
  }

  public function transicionarKardexTransitoVenta($productoId, $cantidad, $precioUnitario, $statusOrigen, $statusDestino, $concepto, $tipo_documento, $factura_venta, $detalle_venta) {
    $ultimo = $this->getUltimo($productoId);
    $saldoCantAnterior = $ultimo ? (float)$ultimo->saldo_cantidad : 0.00;
    $saldoValAnterior  = $ultimo ? (float)$ultimo->saldo_valor : 0.00;

    $maxFolioKardex = DB::table('in_egr_productos_kardex')->where('producto_id', $productoId)
    ->lockForUpdate() 
    ->max('folio_kardex');
        
    $folioKardexMovUno = $maxFolioKardex ? $maxFolioKardex + 1 : 1;
    $folioKardexMovDos = $folioKardexMovUno + 1;

    // MATEMÁTICA OUTBOUND: Al iniciar ruta logistica de entrega, el stock físico real disminuye
    $nuevoSaldoCantidad = $saldoCantAnterior - (float)$cantidad;
    $nuevoSaldoValor    = $nuevoSaldoCantidad * (float)$precioUnitario;

    DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovUno), [
      "status_kardex"     => $statusOrigen, // 'comprometido'
      "concepto"          => $concepto . " (Compensación)",
      "tipo_documento"    => $tipo_documento,
      "valor_unitario"    => $precioUnitario,
      "factura_venta"     => $factura_venta,
      "detalle_venta"     => $detalle_venta,
      "entregar_cantidad" => $cantidad * -1,
      "entregar_valor"    => ($cantidad * $precioUnitario) * -1,
      "saldo_cantidad"    => $saldoCantAnterior,
      "saldo_valor"       => $saldoValAnterior
    ]));

    DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovDos), [
      "status_kardex"             => $statusDestino, // 'en_transito'
      "concepto"                  => $concepto . " (Nuevo Asiento)",
      "tipo_documento"            => $tipo_documento,
      "valor_unitario"            => $precioUnitario,
      "factura_venta"             => $factura_venta,
      "detalle_venta"             => $detalle_venta,
      "transito_salida_cantidad"  => $cantidad,
      "transito_salida_valor"     => ($cantidad * $precioUnitario),
      "saldo_cantidad"            => $nuevoSaldoCantidad, 
      "saldo_valor"               => $nuevoSaldoValor
    ]));
    return "kardex registrado";
  }

  public function registrarEntregaMovimientoFisico($productoId, $cantidad, $precioUnitario, $statusOrigen, $statusDestino, $concepto, $tipo_documento, $factura_venta, $detalle_venta) {
    $ultimo = $this->getUltimo($productoId);
    $saldoCantAnterior = $ultimo ? (float)$ultimo->saldo_cantidad : 0.00;
    $saldoValAnterior  = $ultimo ? (float)$ultimo->saldo_valor : 0.00;

    $maxFolioKardex = DB::table('in_egr_productos_kardex')->where('producto_id', $productoId)
    ->lockForUpdate() 
    ->max('folio_kardex');
        
    $folioKardexMovUno = $maxFolioKardex ? $maxFolioKardex + 1 : 1;
    $folioKardexMovDos = $folioKardexMovUno + 1;

    //Fila 1: Mata el origen aplicando cantidad negativa
    DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovUno), [
      "status_kardex"             => $statusOrigen,
      "concepto"                  => $concepto . " (Compensación)",
      "tipo_documento"            => $tipo_documento,
      "valor_unitario"            => $precioUnitario,
      "factura_venta"             => $factura_venta,
      "detalle_venta"             => $detalle_venta,
      "transito_salida_cantidad"  => ($cantidad * -1),
      "transito_salida_valor"     => (($cantidad * $precioUnitario) * -1),
      "saldo_cantidad"            => $saldoCantAnterior, 
      "saldo_valor"               => $saldoValAnterior
    ]));

    //Fila 2: Crea el nuevo hito en positivo
    return DB::table("in_egr_productos_kardex")->insert(array_merge($this->camposBase($productoId, $folioKardexMovDos), [
      "status_kardex"    => $statusDestino,
      "concepto"         => $concepto . " (Nuevo Asiento)",
      "tipo_documento"   => $tipo_documento,
      "valor_unitario"   => $precioUnitario,
      "factura_venta"    => $factura_venta,
      "detalle_venta"    => $detalle_venta,
      "salida_cantidad"  => $cantidad,
      "salida_valor"     => ($cantidad * $precioUnitario),
      "saldo_cantidad"   => $saldoCantAnterior, 
      "saldo_valor"      => $saldoValAnterior
    ]));
  }
}