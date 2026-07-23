<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\ProrrateosModelo;
use App\Models\ProductosModelo;
use Illuminate\Support\Str;
use QRCode;

class EGRE_ProrrateosController extends Controller{
  public function listaNoProrrateos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;
    
    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }
    
    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $JwtAuth = new \App\Helpers\JwtAuth();
    $loteList = DB::select("SELECT buy.token_compras,buy.folio_compra,people.paterno,people.materno,people.nombre,people.denominacion_rs,people.rfc_generico,people.rfc_taxId,emp.zona_horaria FROM compras AS buy 
      JOIN catalogo_proveedores AS catprov JOIN personas AS people JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
      WHERE buy.id IN (SELECT numero_compra FROM eegr_compras_detalle WHERE numero_compra = buy.id AND prorrateo = FALSE) AND buy.proveedor = catprov.id AND catprov.proveedor = people.id AND buy.comprador = emp.id 
      AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$empresa, $usuario]);

    //$loteList = DB::table("eegr_compras AS buy")

    foreach ($loteList as $value) {
      //da_te_default_timezone_set($value->zona_horaria);

      if ($value->denominacion_rs != '') {
        $nombreProv = $JwtAuth->desencriptar($value->denominacion_rs);
      } else {
        $nombreProv = $JwtAuth->desencriptar($value->paterno) . " " .
          $JwtAuth->desencriptar($value->materno) . " " .
          $JwtAuth->desencriptar($value->nombre);
      }

      if ($value->rfc_taxId != NULL) {
        $dataResRfc = $JwtAuth->desencriptar($value->rfc_taxId);
      } else {
        $dataResRfc = $value->rfc_generico;
      }

      $arrayForeach = array(
        "token_compras" => $value->token_compras,
        "folio_compra" => $JwtAuth->generar($value->folio_compra),
        "dataResRfc" => $dataResRfc,
        "nombreProv" => $nombreProv,
      );
      $arrayProrrateo[] = $arrayForeach;
    }
    $dataMensaje = array(
      'datosProrrateo' => $arrayProrrateo,
      'code' => 200,
      'status' => 'success'
    );
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleNoProrrateos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_compra' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_compra = $request->input('token_compra');
      
      $selectdetalleCompra = DB::table("eegr_compras AS comp")
      ->join("eegr_compras_detalle AS detcomp","comp.id","=","detcomp.numero_compra")
      ->join("main_empresas AS emp","detcomp.empresa","=","emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->select(
        "detcomp.token_detcompra",
        "detcomp.precio_unitario",
        "detcomp.cantidad",
        "detcomp.descuento",
        "detcomp.retenciones_total",
        "detcomp.traslados_total"
      )
      ->selectRaw("
        IF (
          detcomp.producto in (SELECT id FROM in_egr_catalogo_productos),
          (SELECT producto FROM in_egr_catalogo_productos WHERE id = detcomp.producto),
          ''
        ) AS concepto_producto
      ")
      ->selectRaw("
        IF (
          detcomp.producto in (SELECT id FROM in_egr_catalogo_productos),
          (SELECT marca FROM in_egr_catalogo_productos WHERE id = detcomp.producto),
          ''
        ) AS marca_producto
      ")
      ->selectRaw("
        IF (
          detcomp.servicio in (SELECT id FROM in_egr_catalogo_servicios),
          (SELECT servicio FROM in_egr_catalogo_servicios WHERE id = detcomp.servicio),
          ''
        ) AS concepto_servicio 
      ")
      ->where([
        "detcomp.prorrateo" => FALSE,
        "comp.token_compras" => $token_compra,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      if ($selectdetalleCompra->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron detalles de compra registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $detcompra = array();
        foreach ($selectdetalleCompra as $resDetCompra) {
          $token_detcompra = $resDetCompra->token_detcompra;

          $totalDetComp = DB::table("eegr_compras_detalle")
          ->selectRaw("
            (
              SUM(precio_unitario * cantidad) - 
              SUM(descuento * cantidad) - 
              SUM(retenciones_total)
            ) + 
            SUM(traslados_total) 
            AS total
          ")
          ->where('token_detcompra', $token_detcompra)
          ->first();

          if ($resDetCompra->concepto_producto != '') {
            $articulo = $JwtAuth->desencriptar($resDetCompra->concepto_producto) . " - " . $JwtAuth->desencriptar($resDetCompra->marca_producto);
          }

          if ($resDetCompra->concepto_servicio != '') {
            $articulo = $JwtAuth->desencriptar($resDetCompra->concepto_servicio);
          }

          $arrayEachDetalleCompra = array(
            "articulo" => $articulo,
            "cantidad" => $resDetCompra->cantidad,
            "descuento" => "$".number_format($resDetCompra->descuento, $resDetCompra->e_moneda_decimales, '.', ','),
            "precio_unitario" => "$".number_format($resDetCompra->precio_unitario, $resDetCompra->e_moneda_decimales, '.', ','),
            "token_detcompra" => $token_detcompra,
            "total" => $totalDetComp->total,
            "totalDetCompFormat" => "$".number_format($totalDetComp->total, $resDetCompra->e_moneda_decimales, '.', ','),
            "retenciones_total" => "$".number_format($resDetCompra->retenciones_total, $resDetCompra->e_moneda_decimales, '.', ','),
            "traslados_total" => "$".number_format($resDetCompra->traslados_total, $resDetCompra->e_moneda_decimales, '.', ','),
          );
          $detcompra[] = $arrayEachDetalleCompra;
        }

        $dataMensaje = array(
          'detcompra' => $detcompra,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaProrrateos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;
    
    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }
    
    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    
    $queryProrrat = DB::table("eegr_compras_prorrateos AS prort")
    ->join("eegr_compras AS buy","prort.compra","=","buy.id")
    ->join("main_empresas AS emp","prort.empresa","=","emp.id")
    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    ->whereIn('buy.id', function ($query) {
      $query->select('numero_compra')->from('eegr_compras_detalle')->where('prorrateo',TRUE);
    })
    ->where([
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->select(
      'prort.id AS prort_id',
      'prort.token_prorrateo',
      'prort.folio_prorrateo',
      'buy.folio_compra',
      'prort.fecha_sistema_prorrateo',
      'prort.fecha_prorrateo',
      'emp.zona_horaria'
    )
    ->get();
    
    if ($queryProrrat->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron ordenes de prorrateo registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayProrrateo = array();

      foreach ($queryProrrat as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $arrayForeach = array(
          "token_prorrateo" => $value->token_prorrateo,
          "folio_prorrateo" => "PRT-".$JwtAuth->generarFolio($value->folio_prorrateo),
          "folio_compra" => "COMP-".$JwtAuth->generarFolio($value->folio_compra),
          "fecha_prorrateo" => gmdate('Y-m-d H:i:s', $value->fecha_prorrateo),
          "fecha_sistema_prorrateo" => gmdate('Y-m-d H:i:s', $value->fecha_sistema_prorrateo),
        );
        $arrayProrrateo[] = $arrayForeach;
      }
  
      $dataMensaje = array(
        'datosProrrateo' => $arrayProrrateo,
        'code' => 200,
        'status' => 'success'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleProrrateo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;
    
    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }
    
    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_prorrateo' => 'required|string',
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
      $token_prorrateo = $request->input('token_prorrateo');
      
      $detPrort = ProrrateosModelo::join("eegr_compras AS buy", "eegr_compras_prorrateos.compra", "=", "buy.id")
      ->join("main_empresas AS emp", "eegr_compras_prorrateos.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_compras_prorrateos.token_prorrateo' => $token_prorrateo,
        'eegr_compras_prorrateos.status_prorrateo' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();

      foreach ($detPrort as $vPrt) {
        //emp.e_moneda_code,emp.e_moneda_decimales
        //da_te_default_timezone_set($vPrt->zona_horaria);

        $detcompra = array();
        $selectdetalleCompra = DB::table('eegr_compras as comp')
        ->join('eegr_compras_detalle as detcomp', 'comp.id', '=', 'detcomp.numero_compra')
        ->join('eegr_compras_prorrateos_detalle as det_prort', 'detcomp.id', '=', 'det_prort.detalle_compra')
        ->join('eegr_compras_prorrateos as ltprort', 'det_prort.prorrateo', '=', 'ltprort.id')
        ->join('main_empresas as emp', 'detcomp.empresa', '=', 'emp.id')
        ->join('main_empresa_usuario as empuser', 'emp.id', '=', 'empuser.empresa')
        ->join('teci_usuarios_catalogo as users', 'empuser.usuario', '=', 'users.id')

        ->where('ltprort.token_prorrateo', $vPrt->token_prorrateo)
        ->where('detcomp.prorrateo', true)
        ->where('comp.token_compras', $vPrt->token_compras)
        ->where('emp.empresa_token', $empresa)
        ->where('users.usuario_token', $usuario)
        ->select([
          'comp.folio_compra AS buy_folio',
          'comp.post_folio AS buy_subfolio',
          'det_prort.token_detalle_prorrt',
          'detcomp.token_detcompra',
          'detcomp.precio_unitario',
          'detcomp.cantidad',
          'detcomp.descuento',
          'detcomp.retenciones_total',
          'detcomp.traslados_total',
          // Subconsulta para concepto_producto
          DB::raw("(SELECT producto FROM in_egr_catalogo_productos WHERE id = detcomp.producto) as concepto_producto"),
          // Subconsulta para marca_producto
          DB::raw("(SELECT marca FROM in_egr_catalogo_productos WHERE id = detcomp.producto) as marca_producto"),
          // Subconsulta para concepto_servicio
          DB::raw("(SELECT servicio FROM in_egr_catalogo_servicios WHERE id = detcomp.servicio) as concepto_servicio")
        ])
        ->get();

        foreach ($selectdetalleCompra as $vdBuy) {
          $token_detcompra = $vdBuy->token_detcompra;

          $totalPrortHist = DB::table("eegr_compras_prorrateos_incrementos AS histPrort")
          ->join("eegr_compras_prorrateos AS ltprort", "histPrort.prorrateo", "=", "ltprort.id")
          ->join("eegr_compras_prorrateos_detalle AS detprort", "histPrort.detalle_prorrateo", "=", "detprort.id")
          ->join("main_empresas AS emp", "histPrort.empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'detprort.token_detalle_prorrt' => $vdBuy->token_detalle_prorrt,
            'ltprort.token_prorrateo' => $token_prorrateo,
            'ltprort.status_prorrateo' => TRUE,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->sum('histPrort.incremento_monto');//cantidad_prort

          $totalDetComp = DB::table('eegr_compras_detalle')
          ->where('token_detcompra', $token_detcompra)
          ->selectRaw("SUM((precio_unitario - descuento) * cantidad) AS total")
          ->first();
          //echo $totalPrortHist;

          $prorrateoResta = $totalDetComp->total - $totalPrortHist;
          $prorrateoRestaFormat = number_format($prorrateoResta, $vPrt->e_moneda_decimales, '.', ',');

          if ($vdBuy->concepto_producto != '') {
            $articulo = $JwtAuth->desencriptar($vdBuy->concepto_producto) . " - " . $JwtAuth->desencriptar($vdBuy->marca_producto);
          }

          if ($vdBuy->concepto_servicio != '') {
            $articulo = $JwtAuth->desencriptar($vdBuy->concepto_servicio);
          }

          $arrayEachDetalleCompra = array(
            "token_detalle_prorrt" => $vdBuy->token_detalle_prorrt,
            "token_detcompra" => $token_detcompra,
            "articulo" => $articulo,
            "documento_anterior" => "COMP-".$JwtAuth->generarFolio($vdBuy->buy_folio).($vdBuy->buy_subfolio != NULL ? '-'.$vdBuy->buy_subfolio : ''),
            "cantidad" => $vdBuy->cantidad,
            //"precio_unitario" => "$".$formatPuRetTras[0]->formatPunit,
            //"roundPunit" => $formatPuRetTras[0]->roundPunit,
            "totalDetCompFormat" => number_format($totalDetComp->total, $vPrt->e_moneda_decimales, '.', ''),
            "total" => $totalDetComp->total,
            "resta" => $prorrateoResta,
            "restaFormat" => $prorrateoRestaFormat,
            "mercancias" => false,
            "viewdetmercancias" => false,
            "act_fijos" => false,
            "viewdetfijos" => false,
            "act_intang" => false,
            "viewdetintang" => false,
            //informacion para prorrateo seleccionado
            "selected" => false,
            "viewPrort" => "",
            "token_art_prorrateo" => "",
            "name_art_prorrateo" => "",
            "tipo_art_prorrateo" => "",
            "cant_art_prorrateo" => 0,
            "desv_art_prorrateo" => "---",
          );
          $detcompra[] = $arrayEachDetalleCompra;
        }

        $arrayForeach = array(
          "token_prorrateo" => $vPrt->token_prorrateo,
          "folio_prorrateo" => $JwtAuth->generar($vPrt->folio_prorrateo),
          "folio_compra" => $JwtAuth->generar($vPrt->folio_compra),
          "fecha_sistema_prorrateo" => gmdate('Y-m-d H:i:s', $vPrt->fecha_sistema_prorrateo),
          "detcompra" => $detcompra,
        );
        $arrayProrrateo[] = $arrayForeach;
      }
      $dataMensaje = array(
        'datosProrrateo' => $arrayProrrateo,
        'code' => 200,
        'status' => 'success'
      );

    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function historialDetalleProrrateo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_prorrateo' => 'required|string',
      'token_detalle_prorrt' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_prorrateo = $request->input('token_prorrateo');
      $token_detalle_prorrt = $request->input('token_detalle_prorrt');

      $histPrort = DB::table("eegr_compras_prorrateos_incrementos AS histPrort")
      ->join("eegr_compras_prorrateos AS ltprort", "histPrort.prorrateo", "=", "ltprort.id")
      ->join("eegr_compras_prorrateos_detalle AS detprort", "histPrort.detalle_prorrateo", "=", "detprort.id")
      ->join("eegr_compras AS buy", "histPrort.compra", "=", "buy.id")
      ->join("eegr_compras_detalle AS detcomp", "histPrort.detalle_compra", "=", "detcomp.id")
      ->join("main_empresas AS emp", "histPrort.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'detprort.token_detalle_prorrt' => $token_detalle_prorrt,
        'ltprort.token_prorrateo' => $token_prorrateo,
        'ltprort.status_prorrateo' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();
      
      if ($histPrort->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron registros de prorrateos realizados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayProrrateo = array();
        foreach ($histPrort as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          $selectdetalleCompra = DB::table("eegr_compras AS comp")
          ->join("eegr_compras_detalle AS detcomp","comp.id","=","detcomp.numero_compra")
          ->join("main_empresas AS emp","detcomp.empresa","=","emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->select(
            "detcomp.token_detcompra",
            "detcomp.precio_unitario",
            "detcomp.cantidad",
            "detcomp.descuento",
            "detcomp.retenciones_total",
            "detcomp.traslados_total"
          )
          ->selectRaw("
            IF (
              detcomp.producto in (SELECT id FROM in_egr_catalogo_productos),
              (SELECT producto FROM in_egr_catalogo_productos WHERE id = detcomp.producto),
              ''
            ) AS concepto_producto
          ")
          ->selectRaw("
            IF (
              detcomp.producto in (SELECT id FROM in_egr_catalogo_productos),
              (SELECT marca FROM in_egr_catalogo_productos WHERE id = detcomp.producto),
              ''
            ) AS marca_producto
          ")
          ->selectRaw("
            IF (
              detcomp.servicio in (SELECT id FROM in_egr_catalogo_servicios),
              (SELECT servicio FROM in_egr_catalogo_servicios WHERE id = detcomp.servicio),
              ''
            ) AS concepto_servicio 
          ")
          ->where([
            "detcomp.token_detcompra" => $value->token_detcompra,
            "comp.token_compras" => $value->token_compras,
            "emp.empresa_token" => $empresa,
            "users.usuario_token" => $usuario
          ])
          ->first();

          if ($selectdetalleCompra->concepto_producto != '') {
            $articulo = $JwtAuth->desencriptar($selectdetalleCompra->concepto_producto) . " - " . $JwtAuth->desencriptar($selectdetalleCompra->marca_producto);
          }

          if ($selectdetalleCompra->concepto_servicio != '') {
            $articulo = $JwtAuth->desencriptar($selectdetalleCompra->concepto_servicio);
          }

          $totalDetComp = DB::table("eegr_compras_detalle")
          ->selectRaw("
            (
              SUM(precio_unitario * cantidad) - 
              SUM(descuento * cantidad) - 
              SUM(retenciones_total)
            ) + 
            SUM(traslados_total) 
            AS total
          ")
          ->where('token_detcompra', $value->token_detcompra)
          ->first();

          $arrayEachDetalleCompra = array(
            "token_rel_prort" => $value->token_rel_prort,
            "token_detalle_prorrt" => $value->token_detalle_prorrt,
            "token_detcompra" => $value->token_detcompra,
            "articulo" => $articulo,
            "detalleCompra_cantidad" => $selectdetalleCompra->cantidad,
            "total" => $totalDetComp->total,
            "totalDetCompFormat" => "$".number_format($totalDetComp->total, $value->e_moneda_decimales, '.', ','),
            //"histPrort_cantidad" => $totalDetCompFormat[0]->cantPrtr,
            "histPrort_incremento" => "$".number_format($totalDetComp->incremento_monto, $value->e_moneda_decimales, '.', ','),
          );
          $arrayProrrateo[] = $arrayEachDetalleCompra;
        }
        $dataMensaje = array(
          'datosProrrateo' => $arrayProrrateo,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminarHistoricoDetalleProrrateo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_prorrateo' => 'required|string',
      'token_detalle_prorrt' => 'required|string',
      'token_rel_prort' => 'required|string',
      'token_detcompra' => 'required|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_prorrateo = $request->input('token_prorrateo');
      $token_detalle_prorrt = $request->input('token_detalle_prorrt');
      $token_rel_prort = $request->input('token_rel_prort');
      $token_detcompra = $request->input('token_detcompra');
      
      $validateChanges = false;
      $validateChangesMerc = true;
      $validateChangesActf = true;
      $validateChangesActIntang = true;

      $OKTknProrrat = isset($token_prorrateo) && !empty($token_prorrateo);
      $OKTknDetProrrat = isset($token_detalle_prorrt) && !empty($token_detalle_prorrt);
      $OKTknRelProrrat = isset($token_rel_prort) && !empty($token_rel_prort);

      if ($OKTknProrrat && $OKTknDetProrrat && $OKTknRelProrrat) {
        $detallePrortCompra = ProrrateosModelo::join("eegr_compras_prorrateos_detalle AS det_prort", "eegr_compras_prorrateos.id", "=", "det_prort.prorrateo")
        ->join("eegr_compras_prorrateos_incrementos AS buydet_prort", "det_prort.id", "=", "buydet_prort.detalle_prorrateo")
        ->join("eegr_compras AS buy", "buydet_prort.compra", "=", "buy.id")
        ->join("eegr_compras_detalle AS detcomp", "buydet_prort.detalle_compra", "=", "detcomp.id")
        ->join("empresas", "eegr_compras_prorrateos.empresa", "=", "empresas.id")
        ->join("main_empresa_usuario AS empuser", "empresas.id", "=", "empuser.empresa")
        ->join("personal", "empresapersonal.personal", "=", "personal.id")
        ->join("usuarios", "personal.usuario", "=", "usuarios.id")
        ->where([
          'eegr_compras_prorrateos.token_prorrateo' => $token_prorrateo,
          'eegr_compras_prorrateos.status_prorrateo' => TRUE,
          'det_prort.token_detalle_prorrt' => $token_detalle_prorrt,
          'buydet_prort.token_rel_prort' => $token_rel_prort,
          'detcomp.token_detcompra' => $token_detcompra,
          'empresas.empresa_token' => $usuario->empresa_token,
          'usuarios.user_token' => $usuario->user_token,
        ])->get();
        
        if ($detallePrortCompra->isEmpty()) {
          $dataMensaje = array(
            'code' => 200,
            'status' => 'error',
            'message' => 'Información de prorrateo no encontrada'
          );
        } else {
          $JwtAuth = new \App\Helpers\JwtAuth();
          foreach ($detallePrortCompra as $valueDetPrortBuy) {
            $prorrateo_incremento = $valueDetPrortBuy->incremento_monto;
            $productoDetalleCompra = $valueDetPrortBuy->producto;
            $actFijoDetalleCompra = $valueDetPrortBuy->activo_fijo;
            $actIntangDetalleCompra = $valueDetPrortBuy->activo_intangible;
            $token_compra = $valueDetPrortBuy->token_compras;
            $nameProducto = '';
  
            $detalleProducto = DB::table("in_egr_catalogo_productos AS catprod")
            ->join("eegr_compras_detalle AS detcomp", "catprod.id", "=", "detcomp.producto")
            ->join("productos AS prodlist", "catprod.producto", "=", "prodlist.id")
            ->join("eegr_compras AS buy", "detcomp.numero_compra", "=", "buy.id")
            ->join("empresas", "catprod.administrador", "=", "empresas.id")
            ->join("main_empresa_usuario AS empuser", "empresas.id", "=", "empuser.empresa")
            ->join("personal", "empresapersonal.personal", "=", "personal.id")
            ->join("usuarios", "personal.usuario", "=", "usuarios.id")
            ->where([
              'detcomp.token_detcompra' => $token_detcompra,
              'buy.token_compras' => $token_compra,
              'empresas.empresa_token' => $usuario->empresa_token,
              'usuarios.user_token' => $usuario->user_token,
            ])
            ->get();
  
            $tknproducto = $detalleProducto[0]->token_cat_productos;
            $nameProducto = $JwtAuth->desencriptar($detalleProducto[0]->producto);
  
            if ($actFijoDetalleCompra == '' && $actIntangDetalleCompra == '') {
              $kardexHistorico = DB::table("in_egr_productos_kardex")
              ->join("in_egr_catalogo_productos AS catprod", "kardex.producto", "=", "catprod.id")
              ->join("eegr_compras AS buy", "kardex.factura_compra", "=", "buy.id")
              ->join("eegr_compras_detalle AS detcomp", "kardex.detalle_compra", "=", "detcomp.id")
              ->where([
                'catprod.token_cat_productos' => $tknproducto,
                'buy.token_compras' => $token_compra,
                'detcomp.token_detcompra' => $token_detcompra,
              ])->get();
  
              foreach ($kardexHistorico as $valDexkar) {
                $totalKardexProrrateo = DB::select("SELECT ROUND(?,6) AS total", [($valDexkar->valor_unitario - $prorrateo_incremento) * $valDexkar->recibir_cantidad]);
                $kardexDisminucion = DB::table("kardex AS dexkar")
                ->join("in_egr_catalogo_productos AS catprod", "dexkar.producto", "=", "catprod.id")
                ->join("eegr_compras AS buy", "dexkar.factura_compra", "=", "buy.id")
                ->join("eegr_compras_detalle AS detcomp", "dexkar.detalle_compra", "=", "detcomp.id")
                ->where([
                  'dexkar.token_kardex' => $valDexkar->token_kardex,
                  'catprod.token_cat_productos' => $tknproducto,
                  'buy.token_compras' => $token_compra,
                  'detcomp.token_detcompra' => $token_detcompra,
                ])->limit(1)->update(
                  array(
                    "dexkar.valor_unitario" => $valDexkar->valor_unitario - $prorrateo_incremento,
                    "dexkar.recibir_valor" => $totalKardexProrrateo[0]->total,
                    "dexkar.saldo_valor" => $totalKardexProrrateo[0]->total - $valDexkar->salida_valor,
                  )
                );
  
                $validateChanges = $kardexDisminucion ? true : false;
                $validateChangesMerc = $kardexDisminucion ? true : false;
              }
            } else {
              if ($actFijoDetalleCompra != '' && $actIntangDetalleCompra == '') {
                $selectFijo = DB::select("SELECT MAX(deprec.id) AS id_deprec FROM eegr_activos_fijos_detalle AS det_fijo
                  JOIN eegr_activos_fijos_depreciacion AS deprec JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detcomp
                  WHERE det_fijo.id = deprec.detalle_activo AND det_fijo.factura_compra = buy.id AND buy.token_compras = ?
                  AND det_fijo.detalle_compra = detcomp.id AND detcomp.token_detcompra ?",
                  [$token_compra, $token_detcompra]
                );
  
                $updateDeprec = DB::table("eegr_activos_fijos_depreciacion AS dep_act")
                ->join("eegr_activos_fijos_detalle AS detact", "dep_act.detalle_activo", "=", "detact.id")
                ->join("eegr_compras_detalle AS detcomp", "kardex.detalle_compra", "=", "detcomp.id")
                ->join("eegr_compras AS buy", "kardex.factura_compra", "=", "buy.id")
                ->where([
                  'dep_act.id' => $selectFijo[0]->id_deprec,
                ])->limit(1)->update(
                  array(
                    "dep_act.ultimo_prorrateo" => 0,
                  )
                );
  
                $validateChanges = $updateDeprec ? true : false;
                $validateChangesActf = $updateDeprec ? true : false;
              }
  
              if ($actIntangDetalleCompra != '' && $actFijoDetalleCompra == '') {
                $selectIntan = DB::select("SELECT MAX(amort.id) AS id_amort FROM eegr_activos_intangibles_detalle AS det_intang
                  JOIN eegr_activos_intangibles_amortizacion AS amort JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detcomp
                  WHERE det_intang.id = amort.detalle_activo AND det_intang.factura_compra = buy.id AND buy.token_compras = ?
                  AND det_intang.detalle_compra = detcomp.id AND detcomp.token_detcompra ?",
                  [$token_compra, $token_detcompra]
                );
  
                $updateAmort = DB::table("eegr_activos_intangibles_amortizacion AS amort_act")
                ->join("eegr_activos_intangibles_detalle AS detact", "amort_act.detalle_activo", "=", "detact.id")
                ->join("eegr_compras_detalle AS detcomp", "kardex.detalle_compra", "=", "detcomp.id")
                ->join("eegr_compras AS buy", "kardex.factura_compra", "=", "buy.id")
                ->where([
                  'amort_act.id' => $selectIntan[0]->id_amort,
                ])->limit(1)->update(
                  array(
                    "amort_act.ultimo_prorrateo" => 0,
                  )
                );
  
                $validateChanges = $updateAmort ? true : false;
                $validateChangesActIntang = $updateAmort ? true : false;
              }
            }
  
            if ($validateChanges && $validateChangesMerc && $validateChangesActf && $validateChangesActIntang) {
              $deleteDetProrrateos = DB::table('eegr_compras_prorrateos_incrementos')
              ->where([
                'token_rel_prort' => $token_rel_prort,
              ])->limit(1)->delete();
  
              if ($deleteDetProrrateos && $validateChanges == true) {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'Prorrateo eliminado satisfactoriamente'
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Prorrateo no eliminado, verifique su información'
                );
              }
            } else {
              if (!$validateChangesMerc) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Prorrateo de mercancia ' . $nameProducto . ' no eliminado debido a fallas en modificaciones de kardex, verifique su información'
                );
              }
  
              if (!$validateChangesActf) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Prorrateo de activo fijo ' . $nameProducto . ' no eliminado debido a fallas en actualización de la depreciación del mismo, verifique su información'
                );
              }
  
              if (!$validateChangesActIntang) {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Prorrateo de activo intangible ' . $nameProducto . ' no eliminado debido al fallas en actualización de amortización del mismo, verifique su información'
                );
              }
            }
          }
        }
      } else {
        $mensaje_error = '';
        if (!$OKTknProrrat) {
          $mensaje_error = 'No seleccionó prorrateo';
        }
        if (!$OKTknDetProrrat) {
          $mensaje_error = 'No seleccionó articulo para el que desea eliminar su prorrateo';
        }
        if (!$OKTknRelProrrat) {
          $mensaje_error = 'No seleccionó articulo de compra para desvincular a prorrateos';
        }
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function getProductosParaProrratear(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'cant_art_prorrateo' => 'required|numeric',
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
      //da_te_default_timezone_set('America/Mexico_City');
      $cant_art_prorrateo = $request->input('cant_art_prorrateo');
      $listaProductosTrue = array();
      
      $artList = ProductosModelo::join("eegr_compras_detalle AS detcomp", "in_egr_catalogo_productos.id", "=", "detcomp.producto")
      ->join("eegr_compras AS buy","detcomp.numero_compra","=","buy.id")
      ->join("sos_ps_genero AS gen", "in_egr_catalogo_productos.genero", "=", "gen.id")
      ->join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'detcomp.prorrateo' => FALSE,
        'in_egr_catalogo_productos.status' => TRUE,
        'detcomp.activo_fijo' => NULL,
        'detcomp.activo_intangible' => NULL,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->whereIn('in_egr_catalogo_productos.familia', ['i_i', 'i_v'])
      ->select(
        'buy.folio_compra AS buy_folio',
        'buy.post_folio AS buy_subfolio',
        'in_egr_catalogo_productos.*',
        'in_egr_catalogo_productos.producto AS concepto_prod',
        'detcomp.*',
        'emp.e_moneda_code',
        'emp.e_moneda_decimales'
      )
      ->get();
      //echo count($artList);
      $totalCompra = 0;
      $resultCompratotal = 0;
      $numLista = 1;
      foreach ($artList as $vArt) {
        $subtotal = ($vArt->precio_unitario - $vArt->descuento) * $vArt->cantidad;

        $arrayEachDetalleCompra = array(
          "numLista" => $numLista,
          "token_detcompra" => $vArt->token_detcompra,
          "token_cat_productos" => $vArt->token_cat_productos,
          "documento_anterior_compra" => "COMP-".$JwtAuth->generarFolio($vArt->buy_folio).($vArt->buy_subfolio != NULL ? '-'.$vArt->buy_subfolio : ''),
          "imagen" => "./assets/images/catalogos/default_producto.jpg",
          "clasificacion" => $JwtAuth->generar($vArt->clasificacion) . '-' . $JwtAuth->generar($vArt->folio_genero) . '-' . $JwtAuth->generar($vArt->folio),
          "producto" => $JwtAuth->desencriptar($vArt->concepto_prod),
          "clave" => $vArt->clave,
          "folio_compra" => $JwtAuth->generar($vArt->folio_compra),
          "cantidad" => $vArt->cantidad,
          "descuento" => "$".number_format($vArt->descuento, $vArt->e_moneda_decimales, '.', ',')." ".$vArt->e_moneda_code,
          "costo_ajustado" => $subtotal,
          "costo_ajustado_format" => number_format($subtotal, $vArt->e_moneda_decimales,'.',''),
          "totalDetCompFormat" => "$".number_format($subtotal, $vArt->e_moneda_decimales, '.', ',')." ".$vArt->e_moneda_code,
          "total_retenciones" => "$".number_format($vArt->total_retenciones ? $vArt->total_retenciones : 0, $vArt->e_moneda_decimales, '.', ',')." ".$vArt->e_moneda_code,
          "total_traslados" => "$".number_format($vArt->total_traslados ? $vArt->total_traslados : 0, $vArt->e_moneda_decimales, '.', ',')." ".$vArt->e_moneda_code,
          "totalCompra" => "",
          "merc_selected" => false,
          "totalProrrateo" => "",
          "desvioProrrateo" => "",
        );
        $listaProductosTrue[] = $arrayEachDetalleCompra;
        $totalCompra += $subtotal;
        ++$numLista;
      }
      for ($i = 0; $i < count($listaProductosTrue); $i++) {
        $listaProductosTrue[$i]["totalCompra"] = $totalCompra;
        //echo $totalCompra;exit;
        $prorrateoUno = $totalCompra != 0 ? $cant_art_prorrateo * ($listaProductosTrue[$i]["costo_ajustado"] / $totalCompra) : 0;
        $prorrateoDos = $prorrateoUno != 0 ? $prorrateoUno / $listaProductosTrue[$i]["cantidad"] : 0;

        $listaProductosTrue[$i]["totalProrrateo"] = $prorrateoUno;
        $listaProductosTrue[$i]["desvioProrrateo"] = $prorrateoDos;
      }
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'listado' => $listaProductosTrue
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function getActivosFijosParaProrratear(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'cant_art_prorrateo' => 'required|numeric',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $jwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $cant_art_prorrateo = $request->input('cant_art_prorrateo');
      $arrayActivosFVig = [];
      
      $listActivos = DB::table('eegr_activos_fijos_catalogo as act')
      ->join("eegr_activos_fijos_detalle AS actfDet","act.id","=","actfDet.activo_fijo")
      ->join('eegr_compras_detalle as buyDet', 'act.id', '=', 'buyDet.activo_fijo')
      ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
      ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
      ->join('eegr_compras as comp', 'comp.id', '=', 'buyDet.numero_compra')
      ->join("main_empresas AS emp", "act.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->leftJoin('in_egr_catalogo_servicios as serv', 'buyDet.servicio', '=', 'serv.id')
      ->select(
        'act.*',
        'actfDet.token_det_activo_fijo',
        'actfUnid.*',
        'buyDet.token_detcompra', 'buyDet.precio_unitario', 'buyDet.cantidad', 
        'buyDet.descuento', 'buyDet.retenciones_total', 'buyDet.traslados_total',
        'comp.folio_compra AS buy_folio',
        'comp.post_folio AS buy_subfolio',
        'serv.servicio as concepto_serv',
        'comp.moneda'
      )
      ->where([
        'act.activo_status' => TRUE,
        'buyDet.prorrateo' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();

      $totalGeneralCompra = 0;
      foreach ($listActivos as $item) {
        $folio_activo = "ACTF-".$jwtAuth->generarFolio($item->folio_activo).(!is_null($item->subfolio_activo) ? '-'.$item->subfolio_activo : '');
        $subtotal = $item->precio_unitario - $item->descuento;
        $totalFila = ($subtotal - $item->retenciones_total) + $item->traslados_total;
        
        $item->total_calculado = $totalFila; // Guardamos el valor numérico
        
        $decimals = $jwtAuth->getMonedaAPI($item->moneda);
        
        $arrayActivosFVig[] = [
          "token_act_fijos" => $item->token_act_fijos,
          "documento_anterior_compra" => "COMP-".$jwtAuth->generarFolio($item->buy_folio).($item->buy_subfolio != NULL ? '-'.$item->buy_subfolio : ''),
          "token_det_activo_fijo" => $item->token_det_activo_fijo,
          "token_activof_unidad" => $item->token_activof_unidad,
          "activo" => $folio_activo." - ".$jwtAuth->desencriptar($item->categoria),
          "folio_activof_unidad" => $item->folio_activof_unidad,
          "token_detcompra" => $item->token_detcompra,
          "servicio" => $jwtAuth->desencriptar($item->concepto_serv),
          "cantidad" => 1,
          "moneda" => $item->moneda,
          "descuento" => "$".number_format($item->descuento, $decimals)." ".$item->moneda,
          "retenciones_total" => "$".number_format($item->retenciones_total, $decimals)." ".$item->moneda,
          "traslados_total" => "$".number_format($item->traslados_total, $decimals)." ".$item->moneda,
          "costo_ajustado" => $subtotal,
          "costo_ajustado_format" => number_format($subtotal, $decimals,'.',''),
          "total_raw" => number_format($totalFila, $decimals,'.',''), // Para el cálculo de prorrateo
          "act_fijo_selected" => false,
          "totalCompra" => 0, 
          "totalProrrateo" => 0,
          "desvioProrrateo" => 0
        ];
        $totalGeneralCompra += $subtotal;
      }
  
      // 3. Segunda pasada: Prorrateo (ahora que tenemos el totalGeneralCompra)
      for ($i = 0; $i < count($arrayActivosFVig); $i++) {
        ///echo $fijo["totalCompra"]. $totalGeneralCompra; 
        $arrayActivosFVig[$i]["totalCompra"] = $totalGeneralCompra;
          
        if ($totalGeneralCompra > 0) {
          $prorrateoUno = $cant_art_prorrateo * ($arrayActivosFVig[$i]["costo_ajustado"] / $totalGeneralCompra);
          $arrayActivosFVig[$i]["totalProrrateo"] = $prorrateoUno;
          $arrayActivosFVig[$i]["desvioProrrateo"] = ($arrayActivosFVig[$i]["cantidad"] > 0) ? $prorrateoUno / $arrayActivosFVig[$i]["cantidad"] : 0;
        }
          
        //unset($fijo["total_raw"]); // Limpiamos el dato temporal
      }
      
      $dataMensaje = array(
        'datosActivo' => $arrayActivosFVig,
        'code' => 200,
        'status' => 'success'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function getActivosDiferidosParaProrratear(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'cant_art_prorrateo' => 'required|numeric',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $jwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $cant_art_prorrateo = $request->input('cant_art_prorrateo');
      $arrayActivosFVig = [];
      
      $listActivos = DB::table('eegr_activos_intangibles_catalogo as act')
      ->join("eegr_activos_intangibles_detalle AS actDifDet","act.id","=","actDifDet.activo_intang")
      ->join('eegr_compras_detalle as buyDet', 'act.id', '=', 'buyDet.activo_intangible')
      ->join("eegr_activos_intangibles_unidades AS actDifUnid","actDifDet.id","=","actDifUnid.activod_detalle")
      ->join("eegr_compras_recepcion AS recept","actDifUnid.id","=","recept.unidad_activo_fijo")
      ->join('eegr_compras as comp', 'comp.id', '=', 'buyDet.numero_compra')
      ->join("main_empresas AS emp", "act.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->leftJoin('in_egr_catalogo_servicios as serv', 'buyDet.servicio', '=', 'serv.id')
      ->select(
        'act.*',
        'actDifDet.token_det_act_intang',
        'actDifUnid.*',
        'buyDet.token_detcompra', 'buyDet.precio_unitario', 'buyDet.cantidad', 
        'buyDet.descuento', 'buyDet.retenciones_total', 'buyDet.traslados_total',
        'comp.folio_compra AS buy_folio',
        'comp.post_folio AS buy_subfolio',
        'serv.servicio as concepto_serv',
        'comp.moneda'
      )
      ->where([
        'act.activo_status' => TRUE,
        'buyDet.prorrateo' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])->get();

      $totalGeneralCompra = 0;
      foreach ($listActivos as $item) {
        $folio_activo = "ACTD-".$jwtAuth->generarFolio($item->folio_activo).(!is_null($item->subfolio_activo) ? '-'.$item->subfolio_activo : '');
        $subtotal = $item->precio_unitario - $item->descuento;
        $totalFila = ($subtotal - $item->retenciones_total) + $item->traslados_total;
        
        $item->total_calculado = $totalFila; // Guardamos el valor numérico
        
        $decimals = $jwtAuth->getMonedaAPI($item->moneda);
        
        $arrayActivosFVig[] = [
          "token_act_intang" => $item->token_act_intang,
          "documento_anterior_compra" => "COMP-".$jwtAuth->generarFolio($item->buy_folio).($item->buy_subfolio != NULL ? '-'.$item->buy_subfolio : ''),
          "token_det_act_intang" => $item->token_det_act_intang,
          "token_activod_unidad" => $item->token_activod_unidad,
          "activo" => $folio_activo." - ".$jwtAuth->desencriptar($item->categoria),
          "folio_activod_unidad" => $item->folio_activod_unidad,
          "token_detcompra" => $item->token_detcompra,
          "servicio" => $jwtAuth->desencriptar($item->concepto_serv),
          "cantidad" => 1,
          "moneda" => $item->moneda,
          "descuento" => "$".number_format($item->descuento, $decimals)." ".$item->moneda,
          "retenciones_total" => "$".number_format($item->retenciones_total, $decimals)." ".$item->moneda,
          "traslados_total" => "$".number_format($item->traslados_total, $decimals)." ".$item->moneda,
          "costo_ajustado" => $subtotal,
          "costo_ajustado_format" => number_format($subtotal, $decimals,'.',''),
          "total_raw" => number_format($totalFila, $decimals,'.',''), // Para el cálculo de prorrateo
          "act_diferido_selected" => false,
          "totalCompra" => 0, 
          "totalProrrateo" => 0,
          "desvioProrrateo" => 0
        ];
        $totalGeneralCompra += $subtotal;
      }
  
      // 3. Segunda pasada: Prorrateo (ahora que tenemos el totalGeneralCompra)
      for ($i = 0; $i < count($arrayActivosFVig); $i++) {
        ///echo $fijo["totalCompra"]. $totalGeneralCompra; 
        $arrayActivosFVig[$i]["totalCompra"] = $totalGeneralCompra;
          
        if ($totalGeneralCompra > 0) {
          $prorrateoUno = $cant_art_prorrateo * ($arrayActivosFVig[$i]["costo_ajustado"] / $totalGeneralCompra);
          $arrayActivosFVig[$i]["totalProrrateo"] = $prorrateoUno;
          $arrayActivosFVig[$i]["desvioProrrateo"] = ($arrayActivosFVig[$i]["cantidad"] > 0) ? $prorrateoUno / $arrayActivosFVig[$i]["cantidad"] : 0;
        }
          
        //unset($fijo["total_raw"]); // Limpiamos el dato temporal
      }
      
      $dataMensaje = array(
        'datosActivo' => $arrayActivosFVig,
        'code' => 200,
        'status' => 'success'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function validaProrrateo($prorrateos,$JwtAuth){
    $patronNumCosto = $JwtAuth->filtroCostoPrecio();
    $mensaje_error = "";
    foreach ($prorrateos as $prt) {
      # code...
      $token_art_detbuy_prorrateo = $prt['token_art_detbuy_prorrateo'];
      $token_art_prorrateo = $prt['token_art_prorrateo'];
      $detalle = $prt['detalle'];
      $name_art_prorrateo = $prt['name_art_prorrateo'];
      $tipo_art_prorrateo = $prt['tipo_art_prorrateo'];
  
      $validacion_token_art_prorrateo = isset($token_art_prorrateo) && !empty($token_art_prorrateo);
      $validacion_token_art_detbuy_prorrateo = isset($token_art_detbuy_prorrateo) && !empty($token_art_detbuy_prorrateo);
      $validacion_detalle = isset($detalle) && !empty($detalle) && count($detalle) > 0;
      $valiadte_tipo_art_prorrateo = isset($tipo_art_prorrateo) && !empty($tipo_art_prorrateo);
  
      if ($validacion_token_art_prorrateo && $validacion_token_art_detbuy_prorrateo && $validacion_detalle && $valiadte_tipo_art_prorrateo) {
        foreach ($detalle as $detPrt) {
          $precio_unitario = $detPrt['costo_ajustado'];
          $totalDetCompFormat = $detPrt['totalDetCompFormat'];
          $numero_articulos_prorratea = $detPrt['numero_articulos_prorratea'];
          $cant_art_prorrateo_contable = $detPrt['cant_art_prorrateo_contable'];
          $cant_art_prorrateo_fiscal = $detPrt['cant_art_prorrateo_fiscal'];
          $porcentaje_juega = $detPrt['porcentaje_juega'];
          $total_prorrateo = $detPrt['total_prorrateo'];
          $desv_art_prorrateo = $detPrt['desv_art_prorrateo'];
  
          $token_prorrateo = $detPrt['token_prorrateo'];
          $token_detalle_prorrt = $detPrt['token_detalle_prorrt'];
          $token_art_detbuy_prorrateo = $detPrt['token_art_detbuy_prorrateo'];
          $total_detalle = $detPrt['total_detalle'];
          $totalCompra = $detPrt['totalCompra'];
  
          $valiadte_precio_unitario = isset($precio_unitario) && !empty($precio_unitario) && preg_match($patronNumCosto, $precio_unitario);
          $valiadte_totalDetCompFormat = isset($totalDetCompFormat) && !empty($totalDetCompFormat) && preg_match($JwtAuth->filtroAlfaNumerico(), $totalDetCompFormat);
          $valiadte_numero_articulos_prorratea = isset($numero_articulos_prorratea) && !empty($numero_articulos_prorratea) && preg_match($patronNumCosto, $numero_articulos_prorratea);
          $valiadte_cant_art_prorrateo_contable = isset($cant_art_prorrateo_contable) && !empty($cant_art_prorrateo_contable) && preg_match($patronNumCosto, $cant_art_prorrateo_contable);
          $valiadte_cant_art_prorrateo_fiscal = isset($cant_art_prorrateo_fiscal) && !empty($cant_art_prorrateo_fiscal) && preg_match($patronNumCosto, $cant_art_prorrateo_fiscal);
          $valiadte_porcentaje_juega = isset($porcentaje_juega) && !empty($porcentaje_juega) && preg_match($patronNumCosto, $porcentaje_juega);
          $valiadte_total_prorrateo = isset($total_prorrateo) && !empty($total_prorrateo) && preg_match($patronNumCosto, $total_prorrateo);
          $valiadte_desv_art_prorrateo = ($tipo_art_prorrateo == 'mercancia' && isset($desv_art_prorrateo) && !empty($desv_art_prorrateo) && preg_match($patronNumCosto, $desv_art_prorrateo)) || $desv_art_prorrateo == "NA";
          $valiadte_token_prorrateo = isset($token_prorrateo) && !empty($token_prorrateo);
          $valiadte_token_detalle_prorrt = isset($token_detalle_prorrt) && !empty($token_detalle_prorrt);
          $valiadte_token_art_detbuy_prorrateo = isset($token_art_detbuy_prorrateo) && !empty($token_art_detbuy_prorrateo);
          $valiadte_total_detalle = isset($total_detalle) && !empty($total_detalle) && preg_match($patronNumCosto, $total_detalle);
          $valiadte_totalCompra = ($tipo_art_prorrateo == 'mercancia' && isset($totalCompra) && !empty($totalCompra) && preg_match($patronNumCosto, $totalCompra)) || $tipo_art_prorrateo != 'mercancia';
  
          if ($valiadte_precio_unitario && $valiadte_totalDetCompFormat && $valiadte_numero_articulos_prorratea && $valiadte_cant_art_prorrateo_contable && $valiadte_cant_art_prorrateo_fiscal && $valiadte_porcentaje_juega &&
            $valiadte_total_prorrateo && $valiadte_desv_art_prorrateo && $valiadte_token_prorrateo && $valiadte_token_detalle_prorrt && $valiadte_token_art_detbuy_prorrateo && $valiadte_total_detalle && $valiadte_totalCompra) {
              
            if ($tipo_art_prorrateo == 'mercancia') {
              $porc_juega = $porcentaje_juega / 100;
              $resultCangtProrrateo = $cant_art_prorrateo_contable *  $porc_juega;
              $prorrateoUno = $resultCangtProrrateo * ($total_detalle / $totalCompra);
              $prorrateoDos = $prorrateoUno / $numero_articulos_prorratea;
  
              if ($prorrateoUno == $total_prorrateo && $prorrateoDos == $desv_art_prorrateo) {
                $mensaje_error = '';
              }
            } else {
              $mensaje_error = '';
            }
          } else {
            $mensaje_error = '';
            if (!$valiadte_precio_unitario) {$mensaje_error = 'precio unitario de articulo es invalido';}
            if (!$valiadte_totalDetCompFormat) {$mensaje_error = 'Total de compra de articulo es invalido1';}
            if (!$valiadte_numero_articulos_prorratea) { $mensaje_error = 'No seleccionó cantidad de articulos para prorratear';}
            if (!$valiadte_cant_art_prorrateo_contable || !$valiadte_cant_art_prorrateo_fiscal) {$mensaje_error = 'No seleccionó cantidad de prorrateo';}
            if (!$valiadte_porcentaje_juega) {$mensaje_error = 'Porcentaje de prorrateo del articulo ' . $name_art_prorrateo . ' es invalido';}
            if (!$valiadte_total_prorrateo) {$mensaje_error = 'prorrateo total del articulo ' . $name_art_prorrateo . ' invalido';}
            if (!$valiadte_desv_art_prorrateo) {$mensaje_error = 'Aumento de costo de articulo ' . $name_art_prorrateo . ' no existe ó es incorrecto';}
            if (!$valiadte_token_prorrateo) {$mensaje_error = 'Error al seleccionar registro de prorrateo';}
            if (!$valiadte_token_detalle_prorrt) {$mensaje_error = 'Error al seleccionar detalle de prorrateo';}
            if (!$valiadte_token_art_detbuy_prorrateo) {$mensaje_error = 'Error al seleccionar detalle de compra';}
            if (!$valiadte_total_detalle) {$mensaje_error = 'Total de detalle de compra de articulo es invalido';}
            if (!$valiadte_totalCompra) {$mensaje_error = 'Total de compra de articulo es invalido' ;}
            //if ($tipo_art_prorrateo != 'mercancia' && !$valiadte_desv_art_prorrateo) {$mensaje_error = 'Aumento de costo de activo ' . $name_art_prorrateo . ' no existe ó es incorrecto';}
            break;
          }
        }
      } else {
        if (!$validacion_token_art_prorrateo) {$mensaje_error = 'No seleccionó articulo para aplicar prorrateo para el articulo '.$name_art_prorrateo;}
        if (!$validacion_token_art_detbuy_prorrateo) {$mensaje_error = 'Error en seleccion de compra para el articulo '.$name_art_prorrateo;}
        if (!$validacion_detalle) {$mensaje_error = 'Desglose de prorrateo del articulo '.$name_art_prorrateo.' es invalido';}
        if (!$valiadte_tipo_art_prorrateo) {$mensaje_error = 'tipo de articulo invalido';}
        break;
      }
    }
    return $mensaje_error;
  }

  private function saveProrrateoMercancias($JwtAuth,$prt,$main_empresa_id,$fecha_contabilizacion,$moneda_code){
    $token_art_detbuy_prorrateo = $prt['token_art_detbuy_prorrateo'];

    $idProducto = DB::table("in_egr_catalogo_productos")->where("token_cat_productos",$prt['token_art_prorrateo'])->value("id");
    
    $queryDetalleCompra = DB::table("eegr_compras_detalle AS detcomp")
    ->join("eegr_compras AS buy", "detcomp.numero_compra", "=", "buy.id")
    //->join("in_egr_catalogo_productos AS catprod", "detcomp.producto", "=", "catprod.id")
    ->where([
      "detcomp.token_detcompra" => $token_art_detbuy_prorrateo,
      "detcomp.producto" => $idProducto
    ])
    ->select('buy.id AS id_compra','buy.token_compras','detcomp.id AS id_detalle')
    ->first();

    $selectKardex = DB::table("in_egr_productos_kardex AS kardx")
    ->join("eegr_compras AS buy", "kardx.factura_compra", "=", "buy.id")
    ->join("eegr_compras_detalle AS detcomp", "kardx.detalle_compra", "=", "detcomp.id")
    //->join("in_egr_catalogo_productos AS catprod", "detcomp.producto", "=", "catprod.id")
    ->where([
      "buy.token_compras" => $queryDetalleCompra->token_compras,
      "detcomp.token_detcompra" => $token_art_detbuy_prorrateo,
      "detcomp.producto" => $idProducto
    ])
    ->select('kardx.valor_unitario','kardx.recibir_cantidad','kardx.salida_valor')
    ->first();

    $valor_unitario_kardex = $selectKardex ? ($selectKardex->valor_unitario ?? 0) : 0;
    $recibir_cantidad = $selectKardex ? ($selectKardex->recibir_cantidad ?? 0) : 0;
    $salida_valor = $selectKardex ? ($selectKardex->salida_valor ?? 0) : 0;

    $id_compra = $queryDetalleCompra->id_compra;
    $id_detalle = $queryDetalleCompra->id_detalle;

    $name_art_prorrateo = $prt['name_art_prorrateo'];
    
    $maxFolio = DB::table('eegr_compras_prorrateos_incrementos')
    ->where('empresa', $main_empresa_id)
    ->lockForUpdate() // <--- Esto evita que dos personas obtengan el mismo folio
    ->max('folio_incremento');
      
    $folioProrrateoNuevo = $maxFolio ? $maxFolio + 1 : 1;
    
    $f_cont_unix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);

    $detalle = $prt['detalle'];
    $total_incremento_contable_acumulado = 0;
    
    foreach ($detalle as $detPrt) {
      $detalleProrrateo = DB::table("eegr_compras_prorrateos_detalle")
      ->where("token_detalle_prorrt", $detPrt['token_detalle_prorrt'])
      ->select('id','prorrateo')
      ->first();
      $total_prorrateo = $detPrt['total_prorrateo'];
      $result_porcentaje_contable_juega = $detPrt['result_porcentaje_contable_juega'];
      $result_porcentaje_fiscal_juega = $detPrt['result_porcentaje_fiscal_juega'];
      
      $tkndetallerel_prort = $JwtAuth->encriptarToken($f_cont_unix).Str::uuid();
      $insertDetProrrateos = DB::table('eegr_compras_prorrateos_incrementos')
      ->insert(array(
        "token_rel_prort" => $tkndetallerel_prort,
        "folio_incremento" => $folioProrrateoNuevo,
        "fecha_contabilizacion_incremento" => $f_cont_unix,
        "prorrateo" => $detalleProrrateo->prorrateo,
        "detalle_prorrateo" => $detalleProrrateo->id,
        "compra" => $id_compra,
        "detalle_compra" => $id_detalle,
        //"cantidad_prort" => $total_prorrateo,
        "incremento_monto" => $result_porcentaje_contable_juega,
        "incremento_monto_fiscal" => $result_porcentaje_fiscal_juega,
        "incremento_moneda" => $moneda_code,
        "empresa" => $main_empresa_id
      ));

      if (!$insertDetProrrateos) {
        throw new \Exception("Error al registrar el desglose del prorrateo para: $name_art_prorrateo");
      }

      $folioProrrateoNuevo++;
      $total_incremento_contable_acumulado += $result_porcentaje_contable_juega;
    }
    
    if ($total_incremento_contable_acumulado > 0 && $recibir_cantidad > 0) {
      $nuevo_valor_unitario = $valor_unitario_kardex + $total_incremento_contable_acumulado;
      $totalKardexProrrateo = $nuevo_valor_unitario * $recibir_cantidad;
      $updateKardex = DB::table("in_egr_productos_kardex")// AS kardex
      //->join("in_egr_catalogo_productos AS catprod", "kardex.producto", "=", "catprod.id")
      //->join("eegr_compras AS buy", "kardex.factura_compra", "=", "buy.id")
      //->join("eegr_compras_detalle AS detcomp", "kardex.detalle_compra", "=", "detcomp.id")
      ->where([
        'producto' => $idProducto,
        'factura_compra' => $id_compra,
        'detalle_compra' => $id_detalle,
      ])->limit(1)->update(
        array(
          "valor_unitario" => $nuevo_valor_unitario,
          "recibir_valor" => $totalKardexProrrateo,
          "saldo_valor" => $totalKardexProrrateo - $salida_valor,
        )
      );

      if (!$updateKardex) {
        throw new \Exception("actualización de valor unitario de compra es incompleta, revise su información o comuniquese a soporte: " . $name_art_prorrateo);
      }
    }
  }

  private function saveProrrateoACTFijos($JwtAuth,$prt,$main_empresa_id,$fecha_contabilizacion,$moneda_code){
    $token_act_fijos = $prt['token_art_prorrateo'];
    $token_art_detbuy_prorrateo = $prt['token_art_detbuy_prorrateo'];
    
    $queryDetalleCompra = DB::table("eegr_compras_detalle AS detcomp")
    ->join("eegr_compras AS buy", "detcomp.numero_compra", "=", "buy.id")
    ->join("eegr_activos_fijos_catalogo AS actf", "detcomp.activo_fijo", "=", "actf.id")
    ->where([
      "detcomp.token_detcompra" => $token_art_detbuy_prorrateo,
      "actf.token_act_fijos" => $token_act_fijos
    ])
    ->select('buy.id AS id_compra','buy.token_compras','detcomp.id AS id_detalle')
    ->first();

    $id_compra = $queryDetalleCompra->id_compra;
    $id_detalle = $queryDetalleCompra->id_detalle;

    $activof_unidad = DB::table("eegr_activos_fijos_unidades")->where("token_activof_unidad",$prt['token_activof_unidad'])->value("id");
    $name_art_prorrateo = $prt['name_art_prorrateo'];
    $detalle = $prt['detalle'];

    $incremento_contable_total = 0;
    $incremento_fiscal_total = 0;
  
    foreach ($detalle as $detPrt) {
      $detalleProrrateo = DB::table("eegr_compras_prorrateos_detalle")
      ->where("token_detalle_prorrt", $detPrt['token_detalle_prorrt'])
      ->select('id','prorrateo')
      ->first();
      if (!$detalleProrrateo) continue;
      //$cant_art_prorrateo_contable = $detPrt['cant_art_prorrateo_contable'];
      //$cant_art_prorrateo_fiscal = $detPrt['cant_art_prorrateo_fiscal'];
      //$total_prorrateo = $detPrt['total_prorrateo'];
      $result_porcentaje_contable_juega = $detPrt['result_porcentaje_contable_juega'];
      $result_porcentaje_fiscal_juega = $detPrt['result_porcentaje_fiscal_juega'];
      //$token_art_detbuy_prorrateo = $detPrt['token_art_detbuy_prorrateo'];
  
      $maxFolio = DB::table('eegr_compras_prorrateos_incrementos')
      ->where('empresa', $main_empresa_id)->lockForUpdate()
      ->max('folio_incremento');
        
      $folioProrrateoNuevo = $maxFolio ? $maxFolio + 1 : 1;
      $f_cont_unix = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);

      $tkndetallerel_prort = $JwtAuth->encriptarToken($f_cont_unix).Str::uuid();
      $insertDetProrrateos = DB::table('eegr_compras_prorrateos_incrementos')
      ->insert(array(
        "token_rel_prort" => $tkndetallerel_prort,
        "folio_incremento" => $folioProrrateoNuevo,
        "fecha_contabilizacion_incremento" => $f_cont_unix,
        "prorrateo" => $detalleProrrateo->prorrateo,
        "detalle_prorrateo" => $detalleProrrateo->id,
        "compra" => $id_compra,
        "detalle_compra" => $id_detalle,
        "activof_unidad" => $activof_unidad,
        "incremento_monto" => $result_porcentaje_contable_juega,
        "incremento_monto_fiscal" => $result_porcentaje_fiscal_juega,
        "incremento_moneda" => $moneda_code,
        "empresa" => $main_empresa_id,
      ));
      $incremento_contable_total += $result_porcentaje_contable_juega;
      $incremento_fiscal_total += $result_porcentaje_fiscal_juega;
  
      if (!$insertDetProrrateos) {
        throw new \Exception("actualización de valor unitario de compra es incompleta, revise su información o comuniquese a soporte: " . $name_art_prorrateo);
      }
    }
    
    $exist_deprec = DB::table('eegr_activos_fijos_depreciaciones')
    ->where([
      'activof_unidad' => $activof_unidad,
      'empresa' => $main_empresa_id
    ])
    ->exists();

    if ($incremento_contable_total > 0 && $exist_deprec) {
      DB::table('eegr_activos_fijos_depreciaciones')
      ->insert(array(
        'token_activof_deprec' => Str::uuid(),
        'activof_unidad' => $activof_unidad,
        'deprec_concepto' => 'incremento',
        'fecha_cont_deprec_periodo' => $f_cont_unix,
        'deprec_tipo' => 'incremento',
        'deprec_subtipo' => 'contable',
        'periodo' => $f_cont_unix, // El Unix Timestamp confirmado
        'importe' => $incremento_contable_total,
        'valor_libros_final' => $incremento_contable_total,
        'empresa' => $main_empresa_id,
        'depreciado' => 1 // Marcamos como aplicado
      ));

      DB::table('eegr_activos_fijos_depreciaciones')
      ->insert(array(
        'token_activof_deprec' => Str::uuid(),
        'activof_unidad' => $activof_unidad,
        'deprec_concepto' => 'incremento',
        'fecha_cont_deprec_periodo' => $f_cont_unix,
        'deprec_tipo' => 'incremento',
        'deprec_subtipo' => 'fiscal',
        'periodo' => $f_cont_unix, // El Unix Timestamp confirmado
        'importe' => $incremento_fiscal_total,
        'valor_libros_final' => $incremento_fiscal_total,
        'empresa' => $main_empresa_id,
        'depreciado' => 1 // Marcamos como aplicado
      ));

      $ultimo_registro_contable = DB::table("eegr_activos_fijos_depreciaciones")
      ->where(['activof_unidad' => $activof_unidad, 'deprec_tipo' => 'natural', 'deprec_subtipo' => 'contable'])
      ->latest('periodo')
      ->first();
      $ultimo_valor_libros_contable = $ultimo_registro_contable ? $ultimo_registro_contable->valor_libros_final : 0;
      $nuevo_valor_inicial_contable = $ultimo_valor_libros_contable + $incremento_contable_total;

      DB::table('eegr_activos_fijos_depreciaciones')
      ->insert(array(
        'token_activof_deprec' => Str::uuid(),
        'activof_unidad' => $activof_unidad,
        'deprec_concepto' => 'depreciación',
        'deprec_tipo' => 'inicial',
        'deprec_subtipo' => 'contable',
        'fecha_cont_deprec_periodo' => $f_cont_unix,
        'periodo' => $f_cont_unix, // El Unix Timestamp confirmado
        'importe' => $incremento_contable_total,
        'valor_libros_final' => $nuevo_valor_inicial_contable,
        'empresa' => $main_empresa_id,
        'depreciado' => 1 // Marcamos como aplicado
      ));

      $ultimo_registro_fiscal = DB::table("eegr_activos_fijos_depreciaciones")
      ->where(['activof_unidad' => $activof_unidad, 'deprec_tipo' => 'natural', 'deprec_subtipo' => 'fiscal'])
      ->latest('periodo')
      ->first();
      $ultimo_valor_libros_fiscal = $ultimo_registro_fiscal ? $ultimo_registro_fiscal->valor_libros_final : 0;
      $nuevo_valor_inicial_fiscal = $ultimo_valor_libros_fiscal + $incremento_fiscal_total;

      DB::table('eegr_activos_fijos_depreciaciones')
      ->insert(array(
        'token_activof_deprec' => Str::uuid(),
        'activof_unidad' => $activof_unidad,
        'deprec_concepto' => 'depreciación',
        'deprec_tipo' => 'inicial',
        'deprec_subtipo' => 'fiscal',
        'fecha_cont_deprec_periodo' => $f_cont_unix,
        'periodo' => $f_cont_unix, // El Unix Timestamp confirmado
        'importe' => $incremento_fiscal_total,
        'valor_libros_final' => $nuevo_valor_inicial_fiscal,
        'empresa' => $main_empresa_id,
        'depreciado' => 1 // Marcamos como aplicado
      ));
    }
  }

  public function guardarProrrateo(Request $request){
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
      'prorrateo_moneda' => 'required|string',
      'arraySelectedProrrateos' => 'required|array',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado registrar es invalida'.$validate->errors(),
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      //exit;
      $fecha_contabilizacion = $request->input('fecha_contabilizacion');
      $prorrateo_moneda_code = $request->input('prorrateo_moneda');
      $arraySelectedProrrateos = $request->input('arraySelectedProrrateos');
      
      $OKFechaContabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);
      $OKProrratMonedaCode = isset($prorrateo_moneda_code) && !empty($prorrateo_moneda_code) && preg_match($JwtAuth->filtroAlfaNumerico(),$prorrateo_moneda_code);
      $detalle_errores = $this->validaProrrateo($arraySelectedProrrateos,$JwtAuth);

      if ($OKFechaContabilizacion && $OKProrratMonedaCode && $detalle_errores == "") {
        DB::beginTransaction();
        try {
          $main_empresa_id = DB::table("main_empresas")->where("empresa_token",$empresa)->value("id");
          foreach ($arraySelectedProrrateos as $prt) {
            if ($prt['tipo_art_prorrateo'] == 'mercancia') {$this->saveProrrateoMercancias($JwtAuth,$prt,$main_empresa_id,$fecha_contabilizacion,$prorrateo_moneda_code);}
            if ($prt['tipo_art_prorrateo'] == 'activo fijo') {$this->saveProrrateoACTFijos($JwtAuth,$prt,$main_empresa_id,$fecha_contabilizacion,$prorrateo_moneda_code);}
          }
  
          DB::commit(); // Si llegamos aquí, todo se guarda permanentemente
          return response()->json(['status' => 'success','message' => 'Registro de prorrateos han sido guardados, revise procedimientos y reportes relacionados'], 200);
        } catch (\Exception $e) {
          DB::rollBack();
          // 1. Guardar el error real en storage/logs/laravel.log
          \Log::error("Error al recibir activo: " . $e->getMessage());
          // 2. Responder al usuario con algo genérico
          return response()->json(['status' => 'error','message' => 'Registro de prorrateos incompleto, revise su información o comuniquese a soporte.' . $e->getMessage()], 500);
        }        
      } else {
        $mensaje_error = '';
        if (!$OKFechaContabilizacion) $mensaje_error = "Error en fecha de contabilización de prorrateo, verifique su información o comuníquese a soporte";
        if (!$OKProrratMonedaCode) {$mensaje_error = 'Error en moneda seleccionada, verifique su información o comuníquese a soporte';}
        if ($detalle_errores != '') {$mensaje_error = $detalle_errores;}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //mercancias
  public function detalleProductoKardex(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_cat_productos' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_cat_productos = $request->input('token_cat_productos');
      
      $queryProductos = ProductosModelo::join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'in_egr_catalogo_productos.token_cat_productos' => $token_cat_productos,
        'in_egr_catalogo_productos.status' => true,
        'emp.empresa_token' => $usuario->empresa_token,
        'users.usuario_token' => $usuario->user_token,
      ])->get();
      
      if ($queryProductos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron productos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $productoRegistrado = array();
        foreach ($queryProductos as $vProd) {
          //da_te_default_timezone_set($vProd->zona_horaria);
          $moneda_decimales = "";
          $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaMonedas');
          if ($response->successful()) {
            $datos = $response->json();
            $cantidadRegistros = is_array($datos) ? count($datos) : 0;
            $indice = array_search($vProd->e_moneda_code, array_column($datos["monedas"], "code"));
            $moneda_decimales = $datos["monedas"][$indice]["decimales"];
            //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
          }
          //echo $moneda_decimales;

          QRCode::text($vProd->token_cat_productos)->setOutfile(Storage::path('public/root/' . $vProd->fecha_registro_prod . 'QRCode.png'))->png();
          $folio_prod = $vProd->folio_sistema != NULL && $vProd->folio_sistema != "" ? ('PROD-' . ($vProd->post_folio == NULL ? $JwtAuth->generarFolio($vProd->folio_sistema) : $JwtAuth->generarFolio($vProd->folio_sistema) . '-' . $vProd->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($vProd->temps_folio);

          $desglose_kardex = array();
          $kardexQuery = DB::table("in_egr_productos_kardex AS kdx")
          ->join("in_egr_catalogo_productos AS catprod","kdx.producto","=","catprod.id")
          ->where('catprod.token_cat_productos',$vProd->token_cat_productos)->get();

          foreach ($kardexQuery as $vKar) {
            $folioCompra = DB::table("eegr_compras")->where("id",$vKar->factura_compra)->value("folio_compra");
            $folioVenta = DB::table("ingr_ventas")->where("id",$vKar->factura_venta)->value("folio_venta");
            $folio_produccion = $vKar->proceso_produccion;
            $status_kardex = '';
            $valfactura_compra = null;
            $valfactura_venta = null;
            $valproceso_produccion = null;

            switch ($vKar->status_kardex) {
              case '1':
                $status_kardex = 'initcount';
                $valfactura_compra = null;
                $valproceso_produccion = null;
                break;

              case '2':
                $status_kardex = 'buyy';
                $valfactura_compra = $JwtAuth->generar($folioCompra);
                $valfactura_venta = null;
                $valproceso_produccion = null;
                break;
              
              case '3':
                $status_kardex = 'devbuyy';
                $valfactura_compra = $JwtAuth->generar($folioCompra);
                $valfactura_venta = null;
                $valproceso_produccion = null;
                break;

              case '4':
                $status_kardex = 'sell';
                $valfactura_compra = null;
                $valfactura_venta = $JwtAuth->generar($folioVenta);
                $valproceso_produccion = null;
                break;

              case '5':
                $status_kardex = 'devsell';
                $valfactura_compra = null;
                $valfactura_venta = $JwtAuth->generar($folioVenta);
                $valproceso_produccion = null;
                break;

              case '6':
                $status_kardex = 'produccion';
                $valfactura_compra = null;
                $valfactura_venta = null;
                $valproceso_produccion = $JwtAuth->generar($folio_produccion);
                break;

              case '7':
                $status_kardex = 'produccionDev';
                $valfactura_compra = null;
                $valfactura_venta = null;
                $valproceso_produccion = $JwtAuth->generar($folio_produccion);
                break;

              case '8':
                $status_kardex = 'count';
                $valfactura_compra = null;
                $valfactura_venta = null;
                $valproceso_produccion = null;
                break;

              default:
                $status_kardex = '';
                $valfactura_compra = null;
                $valfactura_venta = null;
                $valproceso_produccion = null;
                break;
            }

            $forKardex = array(
              "token_kardex" => $vKar->token_kardex,	
              "producto" => $vKar->producto,	
              "fecha" => date('d-m-Y H:i:s',$vKar->fecha_kardex),	
              "status_kardex" => $status_kardex,	
              "concepto" => $vKar->concepto,	
              "factura_compra" => $valfactura_compra,	
              "factura_venta" => $valfactura_venta,
              "proceso_produccion" => $valproceso_produccion,

              "recibir_cantidad" => $vKar->recibir_cantidad,
              "entrada_cantidad" => $vKar->entrada_cantidad,
              "entregar_cantidad" => $vKar->entregar_cantidad,
              "salida_cantidad" => $vKar->salida_cantidad,
              "saldo_cantidad" => $vKar->saldo_cantidad,
              "valor_unitario" => "$".number_format($vKar->valor_unitario,$moneda_decimales, '.', ','), 	
              "recibir_valor" => "$".number_format($vKar->recibir_valor,$moneda_decimales, '.', ','), 
              "entrada_valor" => "$".number_format($vKar->entrada_valor,$moneda_decimales, '.', ','), 
              "entregar_valor" => "$".number_format($vKar->entregar_valor,$moneda_decimales, '.', ','), 
              "salida_valor" => "$".number_format($vKar->salida_valor,$moneda_decimales, '.', ','), 
              "saldo_valor" => "$".number_format($vKar->saldo_valor,$moneda_decimales, '.', ','),
            );
            $desglose_kardex[] = $forKardex; 

          }

          $rowPrd = array(
            "token_cat_productos" => $vProd->token_cat_productos,
            "modulo_destino" => $vProd->modulo_mostrador == TRUE ? "mostra_vent" : "ssic_menu_inven",
            "fecha_registro_prod" => gmdate('Y-m-d H:i:s', $vProd->fecha_registro_prod),
            "folio_prod" => $folio_prod,
            "producto" => $JwtAuth->desencriptar($vProd->producto),
            "desglose" => $desglose_kardex,
          );

          $productoRegistrado[] = $rowPrd; 
        }

        $dataMensaje = array('status' => 'success','code' => 200,'producto' => $productoRegistrado);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}