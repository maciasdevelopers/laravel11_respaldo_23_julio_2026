<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\ProductosModelo;
use App\Models\ProveedoresModelo;
use App\Models\UMedidaModelo;
use App\Models\ListaPreciosModelo;
use App\Models\ClasificacionModelo;
use PDF;
use QRCode;

class INVENT_ProductosController extends Controller{
  private function productosEach($prodList,$JwtAuth){
    $listaProductosTrue = array();
    $num_lista = 1;
    foreach ($prodList as $value) {
      //da_te_default_timezone_set($value->zona_horaria);
      QRCode::text($value->token_cat_productos)->setOutfile(Storage::path('public/root/' . $value->fecha_registro_prod . 'QRCode.png'))->png();

      $folio_prod = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('PROD-'.$JwtAuth->generarFolio($value->folio_sistema). (!is_null($value->post_folio) ? '-'.$value->post_folio : '')) : 'PROD-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

      $prodGenero = DB::table("in_egr_catalogo_productos AS catprod")
        ->join("sos_ps_genero AS gen", "catprod.genero", "=", "gen.id")
        ->where(['catprod.token_cat_productos' => $value->token_cat_productos])->get();
      $genero_prod = $value->modulo_mostrador == FALSE && count($prodGenero) == 1 ? $JwtAuth->generar($prodGenero[0]->folio_genero) : "---";

      $familia = "N/A";
      if (!$value->modulo_mostrador && $value->familia != NULL && $value->familia != "") {
        switch ($value->familia) {
          case 'u_i':
            $familia = "Uso interno";
            break;

          case 'i_i':
            $familia = "inventarios (uso interno)";
            break;
          
          case 'i_v':
            $familia = "inventarios para ventas";
            break;

          case 'a_f':
            $familia = "activos fijos";
            break;
          case 'a_i':
            $familia = "activos intangibles";
            break;
          default:
            $familia = "N/A";
            break;
        }
      }
      

      $arrayForeachVig = array(
        "token_cat_productos" => $value->token_cat_productos,
        "folio_prod" => $folio_prod,
        "ventanas" => "modal".$folio_prod,
        "producto" => $JwtAuth->desencriptar($value->producto),
        "familia" => $familia,
        "clasificacion" => $value->modulo_mostrador == TRUE ? "N/A" : $JwtAuth->generar($value->clasificacion) . "-" . $genero_prod . "-" . $JwtAuth->generar($value->folio_sistema),
        "genero" => $value->modulo_mostrador == TRUE ? "N/A" : '',
        "marca" => $value->modulo_mostrador == TRUE ? "N/A" : ($value->marca != NULL && $value->marca != "" ? $JwtAuth->desencriptar($value->marca) : ''),
        "cuenta_contable" => !empty($value->cuenta_contable) ? $JwtAuth->desencriptar($value->cuenta_contable) : '',
        //stock
        "stock_actual" => $value->modulo_mostrador == TRUE ? "N/A" : 10,
        "stock_minimo_registrado" => $value->modulo_mostrador == TRUE ? "N/A" : $value->stock_min,
        "stock_maximo_registrado" => $value->modulo_mostrador == TRUE ? "N/A" : $value->stock_max,
        //costeo
        "metodo_costeo" => $value->modulo_mostrador == TRUE ? "N/A" : $value->costeo,
        //unidad de medida
        "unidad_medida_entrada_clave" => $value->unidad_medida_entrada_clave != "" ? $value->unidad_medida_entrada_clave : "---",
        "unidad_medida_salida_clave" => $value->unidad_medida_salida_clave != "" ? $value->unidad_medida_salida_clave : "---",
        //moneda aplicada
        "moneda_aplicable_clave" => $value->moneda_aplicable_clave != "" ? $value->moneda_aplicable_clave : "---",
        "moneda_aplicable_clave_decimales" => $value->moneda_aplicable_clave != "" ? $value->moneda_aplicable_clave : "---",
        //uso
        //"uso_producto" => $value->modulo_mostrador == TRUE ? "N/A" : ($value->uso_producto == 'i' ? 'Uso interno' : 'Producto para ventas'),
        //serie
        "num_serie" => $value->modulo_mostrador == TRUE ? "N/A" : ($value->num_serie == TRUE ? 'enabled' : 'disabled'),
        //lote
        "num_lote" => $value->modulo_mostrador == TRUE ? "N/A" : ($value->num_lote == TRUE ? 'enabled' : 'disabled'),
        //pedimento
        "importado" => $value->modulo_mostrador == TRUE ? "N/A" : ($value->importado == TRUE ? 'enabled' : 'disabled'),
        //sat
        "sat_clave_code" => $value->modulo_mostrador == TRUE ? "N/A" : ($value->sat_clave_code != "" ? $value->sat_clave_code : "---"),
        "costo_aplicable" => "$" . ($value->costo_aplicable != "" ? number_format($value->costo_aplicable, $value->moneda_aplicable_clave_decimales, '.', ',') : "0.00"),
        //"sat_homologado" => $value->catalogo_sat != "" ? $value->catalogo_sat : "---",
        "utilizado" => $value->utilizado == TRUE ? true : false,
        "modulo_destino" => $value->modulo_mostrador == TRUE ? "mostra_vent" : "ssic_menu_inven",
        "authorized" => $value->authorized == TRUE ? true : false, //authorized_by
        "authorized_fecha" => $value->authorized == TRUE ? date("d-m-Y H:i:s", $value->authorized_fecha) : "---",
        //precio
        //
        //periodicidad de compra
        //disponibilidad
        //almacen
        //costo de compra
        //precio de venta
        "detalle_info" => [],
        "detalle_almacen" => [],
        "detalle_kardex" => []
      );
      $listaProductosTrue[] = $arrayForeachVig;
      ++$num_lista;
    }
    return $listaProductosTrue;
  }

  public function catalogoProductosGeneral(Request $request){
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
      
      $prodList = DB::table("in_egr_catalogo_productos AS catprod")
      ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'catprod.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catprod.fecha_registro_prod", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($prodList->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron productos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();

        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'listado' => $this->productosEach($prodList,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function productosInventariosEach($prodList,$JwtAuth){
    $listaProductosTrue = array();

    foreach ($prodList as $value) {
      //da_te_default_timezone_set($value->zona_horaria);
      QRCode::text($value->token_cat_productos)->setOutfile(Storage::path('public/root/' . $value->fecha_registro_prod . 'QRCode.png'))->png();

      $folio_prod = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('PROD-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

      $prodGenero = DB::table("in_egr_catalogo_productos AS catprod")
      ->join("sos_ps_genero AS gen", "catprod.genero", "=", "gen.id")
      ->where(['catprod.token_cat_productos' => $value->token_cat_productos])->get();
      $genero_prod = $value->modulo_mostrador == FALSE && count($prodGenero) == 1 ? $JwtAuth->generar($prodGenero[0]->folio_genero) : "---";

      $familia = "N/A";
      if ($value->familia != NULL && $value->familia != "") {
        switch ($value->familia) {
          case 'u_i':
            $familia = "Uso interno";
            break;
          case 'i_i':
            $familia = "inventarios (uso interno)";
            break;
          case 'i_v':
            $familia = "inventarios para ventas";
            break;
          case 'a_f':
            $familia = "activos fijos";
            break;
          case 'a_i':
            $familia = "activos intangibles";
            break;
          default:
            $familia = "N/A";
            break;
        }
      }

      $arrayForeachVig = array(
        "token_cat_productos" => $value->token_cat_productos,
        "folio_prod" => $folio_prod,
        "ventanas" => "modal".$folio_prod,
        "producto" => $JwtAuth->desencriptar($value->producto),
        "familia" => $familia,
        "clasificacion" => $JwtAuth->generar($value->clasificacion) . "-" . $genero_prod . "-" . $JwtAuth->generar($value->folio_sistema),
        "genero" => '',
        "marca" => $value->marca != NULL && $value->marca != "" ? $JwtAuth->desencriptar($value->marca) : '',
        //stock
        "stock_actual" => 10,
        "stock_minimo_registrado" => $value->stock_min,
        "stock_maximo_registrado" => $value->stock_max,
        //costeo
        "metodo_costeo" => $value->costeo,
        //unidad de medida
        "unidad_medida_entrada_clave" => $value->unidad_medida_entrada_clave != "" ? $value->unidad_medida_entrada_clave : "---",
        "unidad_medida_salida_clave" => $value->unidad_medida_salida_clave != "" ? $value->unidad_medida_salida_clave : "---",
        //moneda aplicada
        "moneda_aplicable_clave" => $value->moneda_aplicable_clave != "" ? $value->moneda_aplicable_clave : "---",
        "moneda_aplicable_clave_decimales" => $value->moneda_aplicable_clave != "" ? $value->moneda_aplicable_clave : "---",
        //uso
        "uso_producto" => $value->uso_producto == 'i' ? 'Uso interno' : 'Producto para ventas',
        //serie
        "num_serie" => $value->num_serie == TRUE ? 'enabled' : 'disabled',
        //lote
        "num_lote" => $value->num_lote == TRUE ? 'enabled' : 'disabled',
        //pedimento
        "importado" => $value->importado == TRUE ? 'enabled' : 'disabled',
        //sat
        "sat_clave_code" => $value->sat_clave_code != "" ? $value->sat_clave_code : "---",
        "costo_aplicable" => "$" . ($value->costo_aplicable != "" ? number_format($value->costo_aplicable, $value->moneda_aplicable_clave_decimales, '.', ',') : "0.00"),
        //"sat_homologado" => $value->catalogo_sat != "" ? $value->catalogo_sat : "---",
        "utilizado" => $value->utilizado == TRUE ? true : false,
        "authorized" => $value->authorized == TRUE ? true : false, //authorized_by
        "authorized_fecha" => $value->authorized == TRUE ? date("d-m-Y H:i:s", $value->authorized_fecha) : "---",
        //precio
        //
        //periodicidad de compra
        //disponibilidad
        //almacen
        //costo de compra
        //precio de venta
      );
      $listaProductosTrue[] = $arrayForeachVig;
    }
    return $listaProductosTrue;
  }

  public function catalogoProductosInventarios(Request $request){
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
      
      $prodList = DB::table("in_egr_catalogo_productos AS catprod")
      ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'catprod.status' => TRUE,
        'catprod.modulo_mostrador' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catprod.fecha_registro_prod", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($prodList->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron productos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();

        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'listado' => $this->productosInventariosEach($prodList,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function productosMostradorEach($prodList,$JwtAuth){
    $listaProductos = array();
    foreach ($prodList as $value) {
      //da_te_default_timezone_set($value->zona_horaria);
      QRCode::text($value->token_cat_productos)->setOutfile(Storage::path('public/root/' . $value->fecha_registro_prod . 'QRCode.png'))->png();

      $folio_prod = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('PROD-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

      $prodGenero = DB::table("in_egr_catalogo_productos AS catprod")
      ->join("sos_ps_genero AS gen", "catprod.genero", "=", "gen.id")
      ->where('catprod.token_cat_productos',$value->token_cat_productos)
      ->get();
      $genero_prod = $value->modulo_mostrador == FALSE && count($prodGenero) == 1 ? $JwtAuth->generar($prodGenero[0]->folio_genero) : "---";

      $row = array(
        "token_cat_productos" => $value->token_cat_productos,
        "folio_prod" => $folio_prod,
        "ventanas" => "modal".$folio_prod,
        "producto" => $JwtAuth->desencriptar($value->producto),
        "sat_clave_code" => $value->sat_clave_code != "" ? $value->sat_clave_code : "---",
        "unidad_medida_entrada_clave" => $value->unidad_medida_entrada_clave != "" ? $value->unidad_medida_entrada_clave : "---",
        "unidad_medida_salida_clave" => $value->unidad_medida_salida_clave != "" ? $value->unidad_medida_salida_clave : "---",
        "costo_aplicable" => "$" . ($value->costo_aplicable != "" ? number_format($value->costo_aplicable, $value->moneda_aplicable_clave_decimales, '.', ',') : "0.00"),
        "moneda_aplicable_clave" => $value->moneda_aplicable_clave != "" ? $value->moneda_aplicable_clave : "---",
        //"sat_homologado" => $value->catalogo_sat != "" ? $value->catalogo_sat : "---",
        "utilizado" => $value->utilizado == TRUE ? true : false,
        "authorized" => $value->authorized == TRUE ? true : false, //authorized_by
        "authorized_fecha" => $value->authorized == TRUE ? date("d-m-Y H:i:s", $value->authorized_fecha) : "---",
      );
      $listaProductos[] = $row;
    }
    return $listaProductos;
  }

  public function catalogoProductosMostrador(Request $request){
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
      
      $prodList = DB::table("in_egr_catalogo_productos AS catprod")
      ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'catprod.status' => TRUE,
        'catprod.modulo_mostrador' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("catprod.fecha_registro_prod", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($prodList->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron productos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();

        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'listado' => $this->productosMostradorEach($prodList,$JwtAuth),
          'bitacora' => $JwtAuth->selectBitacoraActividad('egresos', 'catalogos', 'productos', $empresa, $usuario),
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaProductosForVentas(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser,true);
    $arrayProductosVig = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
        $validate = \Validator::make($parametrosArray,[
            'user_token' => 'required|string',
        ]);
    
        if ($validate->fails()) {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Usuario incorrecto '.$validate->errors(),
                'errors' => $validate->errors()
            );
        } else {
            $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
            
            /*$decimalesMoneda = DB::select("SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers 
            JOIN teci_usuarios_catalogo AS users WHERE emp.moneda = catmon.id AND emp.empresa_token = ?
            AND emp.id = empuser.empresa AND empuser.personal = pers.id 
            AND pers.usuario = users.id AND users.usuario_token = ?",
            [$usuario->empresa_token,$usuario->user_token]);*/
        
            $prodList = ProductosModelo::join("sos_ps_genero AS gen","in_egr_catalogo_productos.genero","=","gen.id")
            //->join("teci_catalogo_prodservsat AS pscsat","in_egr_catalogo_productos.catalogo_sat","=","pscsat.id")
            //->join("teci_unidad_medida AS umed","in_egr_catalogo_productos.medida_entrada","=","umed.id")
            ->join("main_empresas AS emp","in_egr_catalogo_productos.admin_empresa","=","emp.id")
            ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
            ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
            ->where([
                'in_egr_catalogo_productos.status' => TRUE,
                'in_egr_catalogo_productos.uso_producto' => 'v',
                'emp.empresa_token' => $usuario->empresa_token,
                'users.usuario_token' => $usuario->user_token,
            ])->orderBy('in_egr_catalogo_productos.id','DESC')->get();
            
            foreach ($prodList as $vProd) {
              //echo "token_cat_productos ".$vProd->token_cat_productos." ";
              //$vProd->e_moneda_code,$vProd->e_moneda_decimales
              //da_te_default_timezone_set($vProd->zona_horaria);
            
              $buyList = ProductosModelo::join("eegr_compras_detalle AS detcomp","in_egr_catalogo_productos.id","=","detcomp.producto")
              ->join("eegr_compras_recepcion AS recept","detcomp.id","=","recept.detalle_compra")
              ->join("in_egr_establecimientos_almacen AS det_alm","recept.id","=","det_alm.recepcion_compra")
              ->join("eegr_compras AS buy","detcomp.numero_compra","=","buy.id")
              ->join("main_empresas AS emp","in_egr_catalogo_productos.admin_empresa","=","emp.id")
              ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
              ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
              ->where([
                //'buy.status_recepcion' => TRUE,
                //'recept.recept_status' => TRUE,
                //
                //'det_alm.existencia' > 0,
                //'detcomp.activo_fijo' => NULL,
                //'detcomp.activo_intangible' => NULL,
                'in_egr_catalogo_productos.token_cat_productos' => $vProd->token_cat_productos,
                'emp.empresa_token' => $usuario->empresa_token,
                'users.usuario_token' => $usuario->user_token,
              ])
              ->whereRaw('det_alm.existencia != 0')
              ->orderBy('detcomp.id','DESC')->get();
                
                if (count($buyList) > 0) {
                    $exist_alm = 0;
                    $cost_alm = 0;
                    foreach ($buyList as $resDetCompra) {
                        $token_detcompra = $resDetCompra->token_detcompra;
                    
                        $selectRecibido = DB::select("SELECT almReg.token_establecimiento_almacen	,almReg.existencia,almReg.costo_aplicable FROM eegr_compras_recepcion AS recept JOIN in_egr_catalogo_productos AS catprod  
                        JOIN in_egr_establecimientos_almacen AS almReg JOIN eegr_compras AS buy JOIN eegr_compras_detalle AS detbuy JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser  
                        JOIN teci_usuarios_catalogo AS users WHERE recept.producto = catprod.id AND catprod.token_cat_productos = ? AND recept.id = almReg.recepcion_compra AND almReg.nivel_almacen = 3 
                        AND almReg.existencia > 0 AND recept.compra = buy.id AND buy.token_compras = ? AND recept.detalle_compra = detbuy.id AND detbuy.token_detcompra = ? AND recept.empresa = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                        [$vProd->token_cat_productos,$resDetCompra->token_compras,$token_detcompra,$usuario->empresa_token,$usuario->user_token]);
                        //echo "hola";
                        foreach ($selectRecibido as $valueRecib) {
                            //echo $valueRecib->token_detalle_almacen."<br>";
                            $exist_alm = $valueRecib->existencia;
                            $formatCosto_aplicable = DB::select("SELECT FORMAT(?,?) AS formatcost",[$valueRecib->costo_aplicable,$vProd->e_moneda_decimales]);  
                            $cost_alm = $formatCosto_aplicable[0]->formatcost;
                        }
                    
                    }
                
                    $arrayListaPrecios = array();
                    $baseListaPrecios = ListaPreciosModelo::get();
                    //"content_color" => "background-color:#".$value->content_color,
                    foreach ($baseListaPrecios as $valPrecios) {
                        $selectDetalePrec = DB::select("SELECT detlp.token_det_list_precios,ROUND(detlp.precio,?) AS precio
                        FROM detalle_lista_precios AS detlp JOIN lista_precios AS pricelist
                        JOIN catalogo_productos AS catprod WHERE detlp.lista = pricelist.id
                        AND detlp.producto = catprod.id AND pricelist.token_lista_precios = ?
                        AND catprod.token_cat_productos = ?",
                        [$vProd->e_moneda_decimales,$valPrecios->token_lista_precios,$vProd->token_cat_productos]);
                            
                        $impuestoArray = array();
                        $countretenciones = 0;
                        $counttraslados = 0;
                        $retenciones = 0;
                        $traslados = 0;
                        if (count($selectDetalePrec) > 0) {
                            $simulacion = $selectDetalePrec[0]->precio;
                            $querySelectImpuestos = DB::select("SELECT tip.token_tipoimpuestos,tip.concepto,tip.tipo,
                            cat.token_cat_impuestos,cat.ret_tras,cat.alias,cat.por_cuo,cat.importe
                            FROM tipoimpuestos AS tip JOIN catalogo_impuestos AS cat
                            JOIN impuestos_articulos AS impserv JOIN catalogo_productos AS catprod
                            JOIN main_empresas AS emp WHERE tip.id = cat.impuesto AND cat.id = impserv.impuestos
                            AND impserv.producto_rel = catprod.id AND catprod.token_cat_productos = ?
                            AND cat.empresa = emp.id AND emp.empresa_token = ?",[$vProd->token_cat_productos,$usuario->empresa_token]);

                            if (count($querySelectImpuestos) != 0) {
                                foreach ($querySelectImpuestos as $valueImpuest) {
                                    $token_impuesto = $valueImpuest->token_cat_impuestos;
                                
                                    if ($valueImpuest->tipo == 001) {
                                        $tipo = 'impuestos Federales';
                                    }
                                    if ($valueImpuest->tipo == 002) {
                                        $tipo = 'impuestos Estatales';
                                    }
                                    if ($valueImpuest->tipo == 003) {
                                        $tipo = 'impuestos Locales';
                                    }
                                
                                    if ($valueImpuest->por_cuo == FALSE) {
                                        $por_cuo = 'cuota';
                                        $importeExplode = explode("$",$valueImpuest->importe);
                                        $importe_imp = $importeExplode[1];
                                    } else {
                                        $por_cuo = 'porcentaje';
                                        $importeExplode = explode("%",$valueImpuest->importe);
                                        $importe_imp = $simulacion * ($importeExplode[0] / 100);
                                    }
                                
                                    if ($valueImpuest->ret_tras == FALSE) {
                                        $simulacion = $simulacion - $importe_imp;
                                        $retenciones = $retenciones + $importe_imp;
                                        ++$countretenciones;
                                    } 
                                
                                    if ($valueImpuest->ret_tras == TRUE) {
                                        $simulacion = $simulacion + $importe_imp;
                                        $traslados = $traslados + $importe_imp;
                                        ++$counttraslados;
                                    }
                                
                                    $formatTotalImp = DB::select("SELECT FORMAT(?,?) AS totalSimulado",[$importe_imp,$decimalesMoneda[0]->decimales]);
                                
                                    $arrayForeachImp = array(
                                        "token_tipoimpuestos" => $valueImpuest->token_tipoimpuestos,
                                        "token_cat_impuestos" => $valueImpuest->token_cat_impuestos,
                                        "tipo" => $tipo,
                                        "concepto" => $valueImpuest->concepto.' ('.$valueImpuest->alias.')',
                                        "importe" => $valueImpuest->importe.' ('.$por_cuo.')',
                                        "formatTotalImp" => "$".$formatTotalImp[0]->totalSimulado,
                                    );
                                    $impuestoArray[] = $arrayForeachImp;
                                }
                            } else {
                                $simulacion = '0.00';
                            }
                        
                            $tkn_detalle_lista = $selectDetalePrec[0]->token_det_list_precios;
                            $precio_detalle = $selectDetalePrec[0]->precio;
                            $validate_button = true;
                            //$token_impuesto = $token_impuesto;
                        } else {
                            $tkn_detalle_lista = ''; 
                            $precio_detalle = ''; 
                            $simulacion = 0;
                            $validate_button = false;
                        }
                            
                        $selectSimulation = DB::select("SELECT FORMAT(?,?) AS simulacion",[$simulacion,$vProd->e_moneda_decimales]);
                        $selectRetenciones = DB::select("SELECT FORMAT(?,?) AS retenciones",[$retenciones,$vProd->e_moneda_decimales]);
                        $selectTraslados = DB::select("SELECT FORMAT(?,?) AS traslados",[$traslados,$vProd->e_moneda_decimales]);
                
                        $arrayForeach = array(
                            "token_lista_precios" => $valPrecios->token_lista_precios,
                            "tkn_detalle_lista" => $tkn_detalle_lista, 
                            "precio_detalle" => $precio_detalle, 
                            "content_color" => "background-color:#".$valPrecios->content_color,
                            //"token_impuesto" => $token_impuesto,
                            "simulacion" => $selectSimulation[0]->simulacion,
                            "validate_button" => $validate_button,
                            "retenciones" => $selectRetenciones[0]->retenciones,
                            "traslados" => $selectTraslados[0]->traslados,
                            "countretenciones" => $countretenciones,
                            "counttraslados" => $counttraslados,
                            "impuestoArray" => $impuestoArray,
                        );
                        $arrayListaPrecios[] = $arrayForeach;
                    
                    }
                    
                    $selectKardesx = DB::select("SELECT FORMAT(deskar.valor_unitario,?) AS valor_unitario 
                        FROM in_egr_productos_kardex AS deskar JOIN in_egr_catalogo_productos AS catprod WHERE deskar.producto = catprod.id
                        AND catprod.token_cat_productos = ?",[$vProd->e_moneda_decimales,$vProd->token_cat_productos]);
                
                    $arrayForeachVig = array(
                        "c_token" => $vProd->token_cat_productos,
                        //"imagen" => $logo_prod,//$logo_prod
                        "clasificacion" => $JwtAuth->generar($vProd->clasificacion).'-'.$JwtAuth->generar($vProd->folio_genero).'-'.
                            $JwtAuth->generar($vProd->folio),
                        "producto" => $JwtAuth->desencriptar($vProd->producto),
                        "clave" => $vProd->clave,
                        "exist_alm" => $exist_alm,
                        "cost_alm" => $cost_alm,
                        "simulated" => 10.00,
                        "selectKardesx" => $selectKardesx[0]->valor_unitario,
                        "arrayListaPrecios" => $arrayListaPrecios,
                    );
                    $arrayProductosVig[] = $arrayForeachVig;
                } 
            }
            return response()->json([
                'datosProducto' => $arrayProductosVig,
                'codigo' => 200,
                'status' => 'success'
            ]); 
        
        }
    } else {
        $dataMensaje = array(
            'status' => 'error',
            'code' => 404,
            'message' => 'Los informacion que intenta registrar no es valida'
        );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoProductosNotAutorizados(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $listaProductosTrue = array();

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

        $prodList = DB::table("in_egr_catalogo_productos AS catprod")
          ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'catprod.status' => TRUE,
            'catprod.authorized' => FALSE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
        foreach ($prodList as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          QRCode::text($value->token_cat_productos)->setOutfile(Storage::path('public/root/' . $value->fecha_registro_prod . 'QRCode.png'))->png();

          $folio_prod = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('PROD-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) :
            'PROD-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

          $prodGenero = DB::table("in_egr_catalogo_productos AS catprod")
            ->join("sos_ps_genero AS gen", "catprod.genero", "=", "gen.id")
            ->where(['catprod.token_cat_productos' => $value->token_cat_productos])->get();
          $genero_prod = $value->modulo_mostrador == FALSE && count($prodGenero) == 1 ? $JwtAuth->generar($prodGenero[0]->folio_genero) : "---";

          $soliValidate = DB::table("in_egr_catalogo_productos AS catprod")
            ->join("in_egr_catalogo_productos_soli_auth AS soli_auth", "catprod.id", "=", "soli_auth.producto")
            ->where(["soli_auth.soli_aprobada" => FALSE, "catprod.token_cat_productos" => $value->token_cat_productos])->get();

          $arrayForeachVig = array(
            "token_cat_productos" => $value->token_cat_productos,
            "folio_prod" => $folio_prod,
            "clasificacion" => $JwtAuth->generar($value->clasificacion) . "-" . $genero_prod . "-" . $JwtAuth->generar($value->folio_sistema),
            "producto" => $JwtAuth->desencriptar($value->producto),
            "sat_clave_code" => $value->sat_clave_code != "" ? $value->sat_clave_code : "---",
            "unidad_medida_entrada_clave" => $value->unidad_medida_entrada_clave != "" ? $value->unidad_medida_entrada_clave : "---",
            "unidad_medida_salida_clave" => $value->unidad_medida_salida_clave != "" ? $value->unidad_medida_salida_clave : "---",
            "costo_aplicable" => "$" . ($value->costo_aplicable != "" ? number_format($value->costo_aplicable, $value->moneda_aplicable_clave_decimales, '.', ',') : "0.00"),
            "moneda_aplicable_clave" => $value->moneda_aplicable_clave != "" ? $value->moneda_aplicable_clave : "---",
            //"sat_homologado" => $value->catalogo_sat != "" ? $value->catalogo_sat : "---",
            "utilizado" => $value->utilizado == TRUE ? true : false,
            "modulo_destino" => $value->modulo_mostrador == TRUE ? "mostra_vent" : "ssic_menu_inven",
            "solicitudes" => count($soliValidate),
          );
          $listaProductosTrue[] = $arrayForeachVig;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'listado' => $listaProductosTrue,
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

  public function requestValidacionProd(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayProveedores = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_productos" => "required|string",
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
        $token_cat_productos = $parametrosArray["token_cat_productos"];
        $observaciones = "permiso de prueba";

        $queryProducto = DB::table("in_egr_catalogo_productos AS catprod")
          ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            "catprod.modulo_mostrador" => TRUE,
            "catprod.token_cat_productos" => $token_cat_productos,
            "catprod.status" => TRUE,
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
          ])->get();

        if (count($queryProducto) == 1) {
          foreach ($queryProducto as $vProd) {
            //da_te_default_timezone_set($vProd->zona_horaria);
            $folio_prod = 'PROD-TEMP-' . $JwtAuth->generarFolio($vProd->temps_folio);
            $nombre_prod = strtolower($JwtAuth->desencriptar($vProd->producto));

            $select_id_prod = DB::table("in_egr_catalogo_productos")->where("token_cat_productos", $vProd->token_cat_productos)->value('id');

            $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario->empresa_token]);

            $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                            AND users.usuario_token = ?", [$usuario->user_token]);

            $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);
            $folioSistema = DB::select("SELECT max(soli_auth.folio_productos_soli_auth) AS folio_permiso FROM in_egr_catalogo_productos_soli_auth AS soli_auth 
                            JOIN main_empresas AS emp WHERE soli_auth.user_emp = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

            $sql_folio = count($folioSistema) == 0 ? 1 : end($folioSistema)->folio_permiso + 1;

            $token_auth = $JwtAuth->encriptarToken(time(), end($select_empresa)->id . end($select_usuario)->id . $observaciones . time() - 500);
            $insertSoliPerm = DB::table("in_egr_catalogo_productos_soli_auth")
              ->insert(
                array(
                  "token_productos_soli_auth" => $token_auth,
                  "folio_productos_soli_auth" => $sql_folio,
                  "fecha_productos_soli_auth" => time(),
                  "user_emp" => end($select_empresa)->id,
                  "user_user" => end($select_usuario)->id,
                  "producto" => $select_id_prod,
                  "observaciones" => $JwtAuth->encriptar($observaciones),
                  "receptor" => 3,
                  "solicitud_prod_status" => TRUE,
                )
              );

            if ($insertSoliPerm) {
              $userAdmin = "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY";
              $titulo_ = "Validación de proveedor";
              $mensaje_user = "El usuario " . $nombre_user . " de la empresa " . end($select_empresa)->abrev_nombre . " ha solicitado validación para el producto con el folio " . $folio_prod . " " . $nombre_prod;
              $JwtAuth->notificacionPushDevices($userAdmin, $titulo_, $mensaje_user);

              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Solicitud de permiso generada con el folio PERM-" . $JwtAuth->generarFolio($sql_folio),
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'el proveedor buscado no existe'
          );
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

  public function validacionProcesoProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayProveedores = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_cat_productos" => "required|string",
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
        $token_cat_productos = $parametrosArray["token_cat_productos"];
        $observaciones = "permiso de prueba";

        $queryProducto = DB::table("in_egr_catalogo_productos AS catprod")
          ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            "catprod.modulo_mostrador" => TRUE,
            "catprod.token_cat_productos" => $token_cat_productos,
            "catprod.status" => TRUE,
            "emp.empresa_token" => $usuario->empresa_token,
            "users.usuario_token" => $usuario->user_token,
          ])->get();

        if (count($queryProducto) == 1) {
          foreach ($queryProducto as $vProd) {
            //da_te_default_timezone_set($vProd->zona_horaria);

            $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario->empresa_token]);

            $select_usuario = DB::select("SELECT pers.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                            AND users.usuario_token = ?", [$usuario->user_token]);

            $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);

            $nombre_prod = strtolower($JwtAuth->desencriptar($vProd->producto));

            $folio_prod_temp = 'PROD-TEMP-' . $JwtAuth->generarFolio($vProd->temps_folio);

            $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder
                            FROM sos_last_folders AS fold JOIN main_empresas AS emp
                            WHERE fold.egr_productos = TRUE AND fold.empresa = emp.id 
                            AND emp.empresa_token = ?", [$usuario->empresa_token]);

            if (count($folioSistema) == 1) {
              if ($folioSistema[0]->folio == 1000000000) {
                $post_folio_db = DB::select("SELECT post_folio FROM in_egr_catalogo_productos 
                                    WHERE id = (SELECT Max(catprod.id) FROM in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp WHERE catprod.admin_empresa = emp.id 
                                    AND emp.empresa_token = ?)", [$usuario->empresa_token]);

                $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
                $folio_nuevo = 1;
              } else {
                $post_folio = NULL;
                $folio_nuevo = $folioSistema[0]->folio;
              }
            } else {
              $post_folio = NULL;
              $folio_nuevo = 1;
            }

            $folio_prod = 'PROD-' . $JwtAuth->generarFolio($folio_nuevo) . ($post_folio != NULL ? '-' . $post_folio : '');
            //echo $folio_prod;exit;

            $updateProdValid = DB::table("in_egr_catalogo_productos")
              ->where(["token_cat_productos" => $vProd->token_cat_productos])
              ->limit(1)->update(
                array(
                  "folio_sistema" => $folio_nuevo,
                  "post_folio" => $post_folio,
                  "authorized" => TRUE,
                  "authorized_fecha" => time(),
                  "authorized_by" => end($select_usuario)->id,
                )
              );

            if ($updateProdValid) {
              $soliValidate = DB::table("in_egr_catalogo_productos AS catprod")
                ->join("in_egr_catalogo_productos_soli_auth AS soli_auth", "catprod.id", "=", "soli_auth.producto")
                ->join("teci_usuarios_catalogo AS users", "soli_auth.user_user", "=", "users.id")
                ->where(["soli_auth.soli_aprobada" => FALSE, "catprod.token_cat_productos" => $vProd->token_cat_productos])->get();

              if (count($soliValidate) > 0) {
                $titulo_ = "Validación de productos";
                $mensaje_user = "El producto $nombre_prod con folio temporal $folio_prod_temp ha sido validado con el folio " . $folio_prod;
                foreach ($soliValidate as $mSoli) {
                  $soliValidAprob = DB::table("in_egr_catalogo_productos_soli_auth")
                    ->where(["token_productos_soli_auth" => $mSoli->token_productos_soli_auth])
                    ->limit(1)->update(array("soli_aprobada" => TRUE));

                  $JwtAuth->notificacionPushDevices($mSoli->usuario_token, $titulo_, $mensaje_user);
                }
              }

              if (count($folioSistema) == 0) {
                $insertSistema = DB::table("sos_last_folders")
                  ->insert(array("egr_productos" => TRUE, "folder" => 1, "post_folder" => $post_folio, "empresa" => $select_empresa[0]->id));
              } else {
                $regFolder = DB::table("sos_last_folders AS lastf")->join("main_empresas AS emp", "lastf.empresa", "=", "emp.id")
                  ->where(["lastf.egr_productos" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
                  ->limit(1)->update(array("lastf.folder" => $folio_nuevo, "lastf.post_folder" => $post_folio));
              }

              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Producto validado con el folio " . $folio_prod,
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Validación de proveedor no registrada, intentelo nuevamente o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'El producto buscado no existe');
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

  public function detalleProductoInventariosDatosGenerales(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $productoRegistrado = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "proddata" => "required|string",
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
        $proddata = $parametrosArray["proddata"];

        $queryProductos = ProductosModelo::join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'in_egr_catalogo_productos.token_cat_productos' => $proddata,
          'in_egr_catalogo_productos.status' => true,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();

        foreach ($queryProductos as $vProd) {
          //da_te_default_timezone_set($vProd->zona_horaria);
          QRCode::text($vProd->token_cat_productos)->setOutfile(Storage::path('public/root/' . $vProd->fecha_registro_prod . 'QRCode.png'))->png();
          $folio_prod = $vProd->folio_sistema != NULL && $vProd->folio_sistema != "" ? ('PROD-' . ($vProd->post_folio == NULL ? $JwtAuth->generarFolio($vProd->folio_sistema) : $JwtAuth->generarFolio($vProd->folio_sistema) . '-' . $vProd->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($vProd->temps_folio);
          
          $prodGenero = DB::table("in_egr_catalogo_productos AS catprod")
          ->join("sos_ps_genero AS gen", "catprod.genero", "=", "gen.id")
          ->where(['catprod.token_cat_productos' => $vProd->token_cat_productos])->get();
          $genero_prod = $vProd->modulo_mostrador == FALSE && count($prodGenero) == 1 ? $JwtAuth->generar($prodGenero[0]->folio_genero) : "---";

          $queryClasificacionToken = ProductosModelo::join("sos_ps_clasificacion AS class", "in_egr_catalogo_productos.clasificacion", "=", "class.id")
          ->where('in_egr_catalogo_productos.token_cat_productos',$vProd->token_cat_productos)->value("class.token_clasificacion");
          $queryClasificacionConcepto = ProductosModelo::join("sos_ps_clasificacion AS class", "in_egr_catalogo_productos.clasificacion", "=", "class.id")
          ->where('in_egr_catalogo_productos.token_cat_productos',$vProd->token_cat_productos)->value("class.concepto");

          $queryGeneroToken = ProductosModelo::join("sos_ps_genero AS gen", "in_egr_catalogo_productos.genero", "=", "gen.id")
          ->where('in_egr_catalogo_productos.token_cat_productos',$vProd->token_cat_productos)->value("gen.token_genero");
          $queryGeneroConcepto = ProductosModelo::join("sos_ps_genero AS gen", "in_egr_catalogo_productos.genero", "=", "gen.id")
          ->where('in_egr_catalogo_productos.token_cat_productos',$vProd->token_cat_productos)->value("gen.concepto");

          $arrayCaracteristicas = array();
          $selectCaracteristicas = DB::table("eegr_catalogo_productos_caracteristicas AS caractList")
          ->join("in_egr_catalogo_productos AS catprod", "caractList.producto", "catprod.id")
          ->where(['catprod.token_cat_productos' => $vProd->token_cat_productos])->get();

          foreach ($selectCaracteristicas as $valCaract) {
            $listeach = array(
              "token_caracteristicas" => $valCaract->token_caracteristicas,
              "clave_caract" => $valCaract->clave_caract,
              "valor_caract" => $valCaract->valor_caract,
              "eliminacion_proceso" => false,
            );
            $arrayCaracteristicas[] = $listeach;
          }

          $arrayClavesInternas = array();
          $selectClavesInternas = DB::table("in_egr_catalogo_productos_claves_internas AS intKlav")
          ->join("in_egr_catalogo_productos AS catprod","intKlav.producto_alta", "catprod.id")
          ->where(['catprod.token_cat_productos' => $vProd->token_cat_productos])->get();

          foreach ($selectClavesInternas as $vClav) {
            $listeach = array(
              "token_alta_clave" => $vClav->token_alta_clave,
              "clave_nombre" => $vClav->clave_nombre,
              "clave_valor" => $vClav->clave_valor,
              "eliminacion_proceso" => false,
            );
            $arrayClavesInternas[] = $listeach;
          }

          $arrayClavesProv = array();
          $selectClavesProv = DB::table("in_egr_catalogo_productos AS catprod")
          ->join("in_egr_catalogo_productos_claves AS prvRel","catprod.id", "prvRel.productoid")
          ->join("eegr_catalogo_proveedores AS catprov","prvRel.proveedor", "catprov.id")
          ->select('prvRel.proveedor','catprov.token_cat_proveedores','prvRel.token_producto_claves','prvRel.tiene_clave','prvRel.identificador', DB::raw('COUNT(catprod.id) as total_productos'))
          ->where(['catprod.token_cat_productos' => $vProd->token_cat_productos])
          ->groupBy('prvRel.proveedor','catprov.token_cat_proveedores','prvRel.token_producto_claves','prvRel.tiene_clave','prvRel.identificador')
          ->get();

          foreach ($selectClavesProv as $vClav) {
            $listeach = array(
              "token_cat_proveedores" => $vClav->token_cat_proveedores,
              "token_producto_claves" => $vClav->token_producto_claves,
              "folio" => "",
              "rfc_prov" => "",
              "nombre" => "",
              "encendido" => true,
              "tiene_clave" => $vClav->tiene_clave == TRUE ? true : false,
              "tiene_clave_background" => $vClav->tiene_clave == TRUE ? true : false,
              "asigned_clave" => $vClav->tiene_clave == TRUE ? $vClav->identificador : '',
              "asigned_clave_background" => $vClav->tiene_clave == TRUE ? $vClav->identificador : '',
              "eliminacion_proceso" => false,
            );
            $arrayClavesProv[] = $listeach;
          }

          $lista_anexos_prod = array();
          $selectIdEvid = DB::table("sos_documentos AS docs")
          ->join("in_egr_catalogo_productos AS catprod","docs.productos","=","catprod.id")
          ->where([
            "status_documento" => TRUE,
            "catprod.token_cat_productos" => $vProd->token_cat_productos,
          ])
          ->get();
          if (count($selectIdEvid) > 0) {
            foreach ($selectIdEvid as $vDoc){
              $rowDocs = array(
                "token_documento" => $vDoc->token_documento,
                "ext_doc" => $vDoc->extension_documento,
                "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
                "url" => "https://downloads.sos-mexico.com.mx/productos/".$vProd->fecha_registro_prod."/".$vDoc->token_documento,
                "eliminacion_proceso" => false,
              );
              $lista_anexos_prod[] = $rowDocs;
            }
          }

          $rowPrd = array(
            "token_cat_productos" => $vProd->token_cat_productos,
            "modulo_destino" => "ssic_menu_inven",
            "fecha_registro_prod" => gmdate('Y-m-d H:i:s', $vProd->fecha_registro_prod),
            "folio_prod" => $folio_prod,
            "producto" => $JwtAuth->desencriptar($vProd->producto),
            "familia" => !empty($vProd->familia) ? $vProd->familia : '',
            "clasificacion_folio" => $JwtAuth->generar($vProd->clasificacion)."-".$genero_prod."-".$JwtAuth->generar($vProd->folio_sistema),
            "concept_class" => 'Clasificación: '.$queryClasificacionConcepto. ', genero: '.$queryGeneroConcepto,
            "clasificacion" => $queryClasificacionToken,
            "genero" => $queryGeneroToken,
            "marca" => !empty($vProd->marca) ? $JwtAuth->desencriptar($vProd->marca) : '',
            //stock
            "stock_actual" => 10,
            "stock_minimo_registrado" => $vProd->stock_min,
            "stock_maximo_registrado" => $vProd->stock_max,
            //costeo
            "metodo_costeo" => $vProd->costeo,
            //unidad de medida
            "unidad_medida_entrada_clave" => $vProd->unidad_medida_entrada_clave,
            "unidad_medida_salida_clave" => $vProd->unidad_medida_salida_clave,
            //moneda aplicada
            "moneda_aplicable_clave" => $vProd->moneda_aplicable_clave,
            "moneda_aplicable_clave_decimales" => $vProd->moneda_aplicable_clave_decimales,
            //uso
            "uso_producto" => $vProd->uso_producto,
            "cuenta_contable" => !empty($vProd->cuenta_contable) ? $JwtAuth->desencriptar($vProd->cuenta_contable) : '',
            //serie
            "num_serie" => $vProd->num_serie == TRUE ? true : false,
            //lote
            "num_lote" => $vProd->num_lote == TRUE ? true : false,
            //pedimento
            "importado" => $vProd->importado == TRUE ? true : false,
            //sat
            "sat_clave_code" => $vProd->sat_clave_code,
            //características
            "caracteristicas" => $arrayCaracteristicas,
            //clavesInternas
            "clavesInternas" => $arrayClavesInternas,
            //clavesInternas
            "rel_proveedores" => $arrayClavesProv,
            //anexos
            "anexos" => $lista_anexos_prod,
            //"moneda_aplicable_clave_decimales" = "costo_aplicable" => "$" . ($value->costo_aplicable != "" ? number_format($value->costo_aplicable, $value->moneda_aplicable_clave_decimales, '.', ',') : "0.00"),
            //"sat_homologado" => $value->catalogo_sat != "" ? $value->catalogo_sat : "---",
            //"moneda_aplicable_clave_decimales" = "utilizado" => $value->utilizado == TRUE ? true : false,
            //"moneda_aplicable_clave_decimales" = "authorized" => $value->authorized == TRUE ? true : false, //authorized_by
            //"moneda_aplicable_clave_decimales" = "authorized_fecha" => $value->authorized == TRUE ? date("d-m-Y H:i:s", $value->authorized_fecha) : "---",
            //precio
            //
            //periodicidad de compra
            //disponibilidad
            //almacen
            //costo de compra
            //precio de venta
          );
          $productoRegistrado[] = $rowPrd; 
        }

        $dataMensaje = array('status' => 'success','code' => 200,'producto' => $productoRegistrado);
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

  public function detalleProductoMostradorDatosGenerales(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $productoRegistrado = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "proddata" => "required|string",
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
        $proddata = $parametrosArray["proddata"];

        $queryProductos = ProductosModelo::join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'in_egr_catalogo_productos.token_cat_productos' => $proddata,
          'in_egr_catalogo_productos.status' => true,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();

        foreach ($queryProductos as $vProd) {
          //da_te_default_timezone_set($vProd->zona_horaria);
          QRCode::text($vProd->token_cat_productos)->setOutfile(Storage::path('public/root/' . $vProd->fecha_registro_prod . 'QRCode.png'))->png();
          $folio_prod = $vProd->folio_sistema != NULL && $vProd->folio_sistema != "" ? ('PROD-' . ($vProd->post_folio == NULL ? $JwtAuth->generarFolio($vProd->folio_sistema) : $JwtAuth->generarFolio($vProd->folio_sistema) . '-' . $vProd->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($vProd->temps_folio);
          
          $arrayClavesInternas = array();
          $selectClavesInternas = DB::table("in_egr_catalogo_productos_claves_internas AS intKlav")
          ->join("in_egr_catalogo_productos AS catprod","intKlav.producto_alta", "catprod.id")
          ->where(['catprod.token_cat_productos' => $vProd->token_cat_productos])->get();

          foreach ($selectClavesInternas as $vClav) {
            $listeach = array(
              "token_alta_clave" => $vClav->token_alta_clave,
              "clave_nombre" => $vClav->clave_nombre,
              "clave_valor" => $vClav->clave_valor,
              "eliminacion_proceso" => false,
            );
            $arrayClavesInternas[] = $listeach;
          }

          $rowPrd = array(
            "token_cat_productos" => $vProd->token_cat_productos,
            "modulo_destino" => $vProd->modulo_mostrador == TRUE ? "mostra_vent" : "ssic_menu_inven",
            "fecha_registro_prod" => gmdate('Y-m-d H:i:s', $vProd->fecha_registro_prod),
            "folio_prod" => $folio_prod,
            "producto" => $JwtAuth->desencriptar($vProd->producto),
            "precio_aplicable" => $vProd->costo_aplicable,
            "precio_aplicable_format" => "$".($vProd->costo_aplicable != "" ? number_format($vProd->costo_aplicable, $vProd->moneda_aplicable_clave_decimales, '.', ',') . " " . $vProd->moneda_aplicable_clave : "0.00"),
            //unidad de medida
            "unidad_medida_salida_clave" => $vProd->unidad_medida_salida_clave,
            //moneda aplicada
            "moneda_aplicable_clave" => $vProd->moneda_aplicable_clave,
            "moneda_aplicable_clave_decimales" => $vProd->moneda_aplicable_clave_decimales,
            //clavesInternas
            "clavesInternas" => $arrayClavesInternas,
          );
          $productoRegistrado[] = $rowPrd; 
        }

        $dataMensaje = array('status' => 'success','code' => 200,'producto' => $productoRegistrado);
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

  public function detalleProductoAlmacen(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $productoRegistrado = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "proddata" => "required|string",
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
        $proddata = $parametrosArray["proddata"];
        
        $queryProductos = ProductosModelo::join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'in_egr_catalogo_productos.token_cat_productos' => $proddata,
          'in_egr_catalogo_productos.status' => true,
          'emp.empresa_token' => $usuario->empresa_token,
          'users.usuario_token' => $usuario->user_token,
        ])->get();

        foreach ($queryProductos as $vProd) {
          //da_te_default_timezone_set($vProd->zona_horaria);
          $row_prd_num_serie = $vProd->num_serie;
          $row_prd_num_lote = $vProd->num_lote;
          $row_prd_importado = $vProd->importado;
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

          $arrayNivelAlmacen1 = array();
          $resTotalExistMatPrim = 0;
          $countExistMp = DB::select("SELECT COUNT(alm.id) AS cont FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod JOIN in_egr_establecimientos_almacen_nivel AS nivel
          WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? AND alm.producto = catprod.id AND catprod.token_cat_productos = ? AND alm.status_disponibilidad = TRUE",
          ['VDVicUgzNzJscnp3WU5YdnRQdFk4Zz09OjoxMjM0NTY3ODEyMzQ1Njc4',$vProd->token_cat_productos]);     
          
          if ($countExistMp[0]->cont != 0) {
            $arrayAlm1 = array();
            $totalExistMp = DB::select("SELECT SUM(alm.existencia) as existencia FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod 
              JOIN in_egr_establecimientos_almacen_nivel AS nivel WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? AND alm.producto = catprod.id AND catprod.token_cat_productos = ? AND alm.status_disponibilidad = TRUE",
              ['VDVicUgzNzJscnp3WU5YdnRQdFk4Zz09OjoxMjM0NTY3ODEyMzQ1Njc4',$vProd->token_cat_productos]);            

            $resTotalExistMatPrim = $totalExistMp[0]->existencia == '' || $totalExistMp[0]->existencia == NULL ? 0 : $totalExistMp[0]->existencia;
        
            $dirAlm = DB::table("teci_direcciones AS dir")
            ->join("teci_pais AS detpais","dir.pais","detpais.id")
            ->join("in_egr_establecimientos_catalogo AS estab","dir.establecimiento","estab.id")
            ->join("in_egr_establecimientos_almacen AS alm","estab.id","alm.almacen")
            ->join("in_egr_establecimientos_almacen_nivel AS nivel","alm.nivel_almacen","nivel.id")
            ->join("in_egr_catalogo_productos AS catprod","alm.producto","catprod.id")
            ->where(["dir.status" => TRUE,"nivel.token_almacen_nivel" => "VDVicUgzNzJscnp3WU5YdnRQdFk4Zz09OjoxMjM0NTY3ODEyMzQ1Njc4","catprod.token_cat_productos" => $vProd->token_cat_productos])->get();

            //echo "dirAlm ".count($dirAlm);

            foreach ($dirAlm as $vDirAlm) {
                //$list->__SET('id_producto',$res_catProd->id_producto);
                $token_establecimiento = $vDirAlm->token_establecimiento;
                $datalias = $JwtAuth->desencriptar($vDirAlm->alias_establecimiento);
                $desgloseAlm1 = array();
                if ($vDirAlm->pais == 'México') {
                  $estado =$JwtAuth->desencriptar($vDirAlm->estado_edit);
                  $municipio =$JwtAuth->desencriptar($vDirAlm->municipio_edit);
                  $c_postal = $vDirAlm->c_postal_edit;
                  $colonia = $JwtAuth->desencriptar($vDirAlm->colonia_edit);
                  $dir_completaAlm = "Estado: $estado, Municipio: $municipio, C.P. $c_postal, Colonia: $colonia";
                } else {
                  $cod_postalext = $JwtAuth->desencriptar($vDirAlm->cod_postalext);
                  $dir_completaAlm = "Alias: ".$JwtAuth->desencriptar($vDirAlm->alias).", Direccion completa ".$cod_postalext.", ".$vDirAlm->pais;
                } 

                $dataExistAlmMatPrim = DB::select("SELECT SUM(alm.existencia)AS existencia
                    FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod 
                    JOIN in_egr_establecimientos_almacen_nivel AS nivel JOIN in_egr_establecimientos_catalogo AS estab
                    WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? 
                    AND alm.almacen = estab.id AND estab.token_establecimiento = ?
                    AND alm.producto = catprod.id AND catprod.token_cat_productos = ?
                    AND alm.status_disponibilidad = TRUE",['VDVicUgzNzJscnp3WU5YdnRQdFk4Zz09OjoxMjM0NTY3ODEyMzQ1Njc4',$vDirAlm->token_establecimiento,$vProd->token_cat_productos]);            

                $existAlm = $dataExistAlmMatPrim[0]->existencia;
            
                $desgloseExistAlmMatPrim = DB::select("SELECT alm.token_establecimiento_almacen,alm.almacen,alm.num_serie,alm.num_lote,alm.importado,alm.existencia,alm.unidad_entrada,alm.unidad_salida,alm.costo_aplicable,
                  alm.status_disponibilidad,alm.recepcion_compra FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod JOIN in_egr_establecimientos_almacen_nivel AS nivel 
                  JOIN in_egr_establecimientos_catalogo AS estab WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? AND alm.almacen = estab.id AND estab.token_establecimiento = ? AND alm.producto = catprod.id 
                  AND catprod.token_cat_productos = ? AND alm.status_disponibilidad = TRUE",['VDVicUgzNzJscnp3WU5YdnRQdFk4Zz09OjoxMjM0NTY3ODEyMzQ1Njc4',$vDirAlm->token_establecimiento,$vProd->token_cat_productos]);

                foreach ($desgloseExistAlmMatPrim as $desgExistAlm) {
                    //echo $desgExistAlm->id_nivel." ";
                    $desgloSerie = !$row_prd_num_serie || empty($desgExistAlm->num_serie) ? '---' : $JwtAuth->desencriptar(DB::table("inventarios_catalogo_series")->where("id",$desgExistAlm->num_serie)->value("serie_codigo"));
                    $desgloLote = !$row_prd_num_lote || empty($desgExistAlm->num_lote) ? '---' : $JwtAuth->desencriptar(DB::table("inventarios_catalogo_lotes")->where("id",$desgExistAlm->num_lote)->value("numero_lote"));
                    $desgloImport = !$row_prd_importado || empty($desgExistAlm->importado) ? '---' : $JwtAuth->desencriptar(DB::table("inventarios_catalogo_pedimento_aduanal")->where("id",$desgExistAlm->importado)->value("numero_pedimento"));

                    $unidad_entrada_complete = $desgExistAlm->unidad_entrada;
                    $unidad_entrada_abrev = "";

                    $unidad_salida_complete = $desgExistAlm->unidad_salida;
                    $unidad_salida_abrev = "";

                    $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaUnidadMedidaProducto');
                    if ($response->successful()) {
                      $datos = $response->json();
                      $cantidadRegistros = is_array($datos) ? count($datos) : 0;
                      $indice_u_entrada = array_search($unidad_entrada_complete, array_column($datos["unidades_medida"], "nombre"));
                      $unidad_entrada_abrev = $datos["unidades_medida"][$indice_u_entrada]["simbolo"];

                      $indice_u_entrada = array_search($unidad_salida_complete, array_column($datos["unidades_medida"], "nombre"));
                      $unidad_salida_abrev = $datos["unidades_medida"][$indice_u_entrada]["simbolo"];
                      //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
                    }

                    $arraInternoDesg1 = array(
                      "token_detalle_almacen" => $desgExistAlm->token_establecimiento_almacen,
                      "num_serie" => $desgloSerie,
                      "num_lote" => $desgloLote,
                      "desgloImport" => $desgloImport,
                      "unidad_entrada_complete" => $unidad_entrada_complete,
                      "unidad_entrada_abrev" => $unidad_entrada_abrev,
                      "existencia" => $desgExistAlm->existencia,
                      "unidad_salida_complete" => $unidad_salida_complete,
                      "unidad_salida_abrev" => $unidad_salida_abrev,
                      "existencia_convert" => "",
                      "costo_aplicable" => "$".number_format($desgExistAlm->costo_aplicable,$moneda_decimales, '.', ','),
                      "status_disponibilidad" => $desgExistAlm->status_disponibilidad ? true : false,
                      "recepcion_compra" => empty($desgExistAlm->recepcion_compra) ? '---' : $JwtAuth->generarFolio(DB::table("eegr_compras_recepcion")->where("id",$desgExistAlm->recepcion_compra)->value("folio_recep")),
                    );
                    $desgloseAlm1[] = $arraInternoDesg1;
                }

                $internoArrayDir = array(
                    "establecimiento_token" => $token_establecimiento,
                    "establecimiento_alias" => $datalias,
                    "dir_completaAlm" => $dir_completaAlm,
                    "existAlm" => $existAlm,
                    "desgloseAlm1" => $desgloseAlm1
                );

                $arrayAlm1[] = $internoArrayDir;

            }
            $arrayNivalm = array(
                "resTotalExistMatPrim" => $resTotalExistMatPrim,
                "datosDir" => $arrayAlm1,
            );

            $arrayNivelAlmacen1[] = $arrayNivalm;
          }

          $arrayNivelAlmacen2 = array();
          $resTotalExistProdProcess = 0;
          $countExistProdProcess = DB::select("SELECT COUNT(alm.id) AS cont FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod JOIN in_egr_establecimientos_almacen_nivel AS nivel
          WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? AND alm.producto = catprod.id AND catprod.token_cat_productos = ? AND alm.status_disponibilidad = TRUE",
          ['SzJRMit6Yittck56cmEzNlAzQW5hUT09OjoxMjM0NTY3ODEyMzQ1Njc4',$vProd->token_cat_productos]);     
          
          if ($countExistProdProcess[0]->cont != 0) {
            $arrayAlm2 = array();
            $totalExistProdProcess = DB::select("SELECT SUM(alm.existencia) as existencia FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod 
              JOIN in_egr_establecimientos_almacen_nivel AS nivel WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? AND alm.producto = catprod.id AND catprod.token_cat_productos = ? AND alm.status_disponibilidad = TRUE",
              ['SzJRMit6Yittck56cmEzNlAzQW5hUT09OjoxMjM0NTY3ODEyMzQ1Njc4',$vProd->token_cat_productos]);            

            $resTotalExistProdProcess = $totalExistProdProcess[0]->existencia == '' || $totalExistProdProcess[0]->existencia == NULL ? 0 : $totalExistProdProcess[0]->existencia;
        
            $dirAlm = DB::table("teci_direcciones AS dir")
            ->join("teci_pais AS detpais","dir.pais","detpais.id")
            ->join("in_egr_establecimientos_catalogo AS estab","dir.establecimiento","estab.id")
            ->join("in_egr_establecimientos_almacen AS alm","estab.id","alm.almacen")
            ->join("in_egr_establecimientos_almacen_nivel AS nivel","alm.nivel_almacen","nivel.id")
            ->join("in_egr_catalogo_productos AS catprod","alm.producto","catprod.id")
            ->where(["dir.status" => TRUE,"nivel.token_almacen_nivel" => "SzJRMit6Yittck56cmEzNlAzQW5hUT09OjoxMjM0NTY3ODEyMzQ1Njc4","catprod.token_cat_productos" => $vProd->token_cat_productos])->get();

            //echo "dirAlm ".count($dirAlm);

            foreach ($dirAlm as $vDirAlm) {
                //$list->__SET('id_producto',$res_catProd->id_producto);
                $token_establecimiento = $vDirAlm->token_establecimiento;
                $datalias = $JwtAuth->desencriptar($vDirAlm->alias_establecimiento);
                $desgloseAlm2 = array();
                if ($vDirAlm->pais == 'México') {
                  $estado =$JwtAuth->desencriptar($vDirAlm->estado_edit);
                  $municipio =$JwtAuth->desencriptar($vDirAlm->municipio_edit);
                  $c_postal = $vDirAlm->c_postal_edit;
                  $colonia = $JwtAuth->desencriptar($vDirAlm->colonia_edit);
                  $dir_completaAlm = "Estado: $estado, Municipio: $municipio, C.P. $c_postal, Colonia: $colonia";
                } else {
                  $cod_postalext = $JwtAuth->desencriptar($vDirAlm->cod_postalext);
                  $dir_completaAlm = "Alias: ".$JwtAuth->desencriptar($vDirAlm->alias).", Direccion completa ".$cod_postalext.", ".$vDirAlm->pais;
                } 

                $dataExistAlmProdProcess = DB::select("SELECT SUM(alm.existencia)AS existencia
                    FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod 
                    JOIN in_egr_establecimientos_almacen_nivel AS nivel JOIN in_egr_establecimientos_catalogo AS estab
                    WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? 
                    AND alm.almacen = estab.id AND estab.token_establecimiento = ?
                    AND alm.producto = catprod.id AND catprod.token_cat_productos = ?
                    AND alm.status_disponibilidad = TRUE",['SzJRMit6Yittck56cmEzNlAzQW5hUT09OjoxMjM0NTY3ODEyMzQ1Njc4',$vDirAlm->token_establecimiento,$vProd->token_cat_productos]);            

                $existAlm = $dataExistAlmProdProcess[0]->existencia;
            
                $desgloseExistAlmProdProcess = DB::select("SELECT alm.token_establecimiento_almacen,alm.almacen,alm.num_serie,alm.num_lote,alm.importado,alm.existencia,alm.unidad_entrada,alm.unidad_salida,alm.costo_aplicable,
                  alm.status_disponibilidad,alm.recepcion_compra FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod JOIN in_egr_establecimientos_almacen_nivel AS nivel 
                  JOIN in_egr_establecimientos_catalogo AS estab WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? AND alm.almacen = estab.id AND estab.token_establecimiento = ? AND alm.producto = catprod.id 
                  AND catprod.token_cat_productos = ? AND alm.status_disponibilidad = TRUE",['SzJRMit6Yittck56cmEzNlAzQW5hUT09OjoxMjM0NTY3ODEyMzQ1Njc4',$vDirAlm->token_establecimiento,$vProd->token_cat_productos]);

                foreach ($desgloseExistAlmProdProcess as $desgExistAlm) {
                    //echo $desgExistAlm->id_nivel." ";
                    $desgloSerie = !$row_prd_num_serie || empty($desgExistAlm->num_serie) ? '---' : $JwtAuth->desencriptar(DB::table("inventarios_catalogo_series")->where("id",$desgExistAlm->num_serie)->value("serie_codigo"));
                    $desgloLote = !$row_prd_num_lote || empty($desgExistAlm->num_lote) ? '---' : $JwtAuth->desencriptar(DB::table("inventarios_catalogo_lotes")->where("id",$desgExistAlm->num_lote)->value("numero_lote"));
                    $desgloImport = !$row_prd_importado || empty($desgExistAlm->importado) ? '---' : $JwtAuth->desencriptar(DB::table("inventarios_catalogo_pedimento_aduanal")->where("id",$desgExistAlm->importado)->value("numero_pedimento"));

                    $unidad_entrada_complete = $desgExistAlm->unidad_entrada;
                    $unidad_entrada_abrev = "";

                    $unidad_salida_complete = $desgExistAlm->unidad_salida;
                    $unidad_salida_abrev = "";

                    $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaUnidadMedidaProducto');
                    if ($response->successful()) {
                      $datos = $response->json();
                      $cantidadRegistros = is_array($datos) ? count($datos) : 0;
                      $indice_u_entrada = array_search($unidad_entrada_complete, array_column($datos["unidades_medida"], "nombre"));
                      $unidad_entrada_abrev = $datos["unidades_medida"][$indice_u_entrada]["simbolo"];

                      $indice_u_entrada = array_search($unidad_salida_complete, array_column($datos["unidades_medida"], "nombre"));
                      $unidad_salida_abrev = $datos["unidades_medida"][$indice_u_entrada]["simbolo"];
                      //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
                    }

                    $arraInternoDesg1 = array(
                      "token_detalle_almacen" => $desgExistAlm->token_establecimiento_almacen,
                      "num_serie" => $desgloSerie,
                      "num_lote" => $desgloLote,
                      "desgloImport" => $desgloImport,
                      "unidad_entrada_complete" => $unidad_entrada_complete,
                      "unidad_entrada_abrev" => $unidad_entrada_abrev,
                      "existencia" => $desgExistAlm->existencia,
                      "unidad_salida_complete" => $unidad_salida_complete,
                      "unidad_salida_abrev" => $unidad_salida_abrev,
                      "existencia_convert" => "",
                      "costo_aplicable" => "$".number_format($desgExistAlm->costo_aplicable,$moneda_decimales, '.', ','),
                      "status_disponibilidad" => $desgExistAlm->status_disponibilidad ? true : false,
                      "recepcion_compra" => empty($desgExistAlm->recepcion_compra) ? '---' : $JwtAuth->generarFolio(DB::table("eegr_compras_recepcion")->where("id",$desgExistAlm->recepcion_compra)->value("folio_recep")),
                    );
                    $desgloseAlm2[] = $arraInternoDesg1;
                }

                $internoArrayDir = array(
                    "establecimiento_token" => $token_establecimiento,
                    "establecimiento_alias" => $datalias,
                    "dir_completaAlm" => $dir_completaAlm,
                    "existAlm" => $existAlm,
                    "desgloseAlm2" => $desgloseAlm2
                );

                $arrayAlm2[] = $internoArrayDir;

            }
            $arrayNivalm = array(
                "resTotalExistProdProcess" => $resTotalExistProdProcess,
                "datosDir" => $arrayAlm2,
            );

            $arrayNivelAlmacen2[] = $arrayNivalm;
          }

          $arrayNivelAlmacen3 = array();
          $resTotalExistProdTerminado = 0;
          $countExistProdTerminado = DB::select("SELECT COUNT(alm.id) AS cont FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod JOIN in_egr_establecimientos_almacen_nivel AS nivel
          WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? AND alm.producto = catprod.id AND catprod.token_cat_productos = ? AND alm.status_disponibilidad = TRUE",
          ['RUxTbHNPeEtmNERCY0hUT3kvTVJndz09OjoxMjM0NTY3ODEyMzQ1Njc4',$vProd->token_cat_productos]);     
          
          if ($countExistProdTerminado[0]->cont != 0) {
            $arrayAlm3 = array();
            $totalExistProdTerminado = DB::select("SELECT SUM(alm.existencia) as existencia FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod 
              JOIN in_egr_establecimientos_almacen_nivel AS nivel WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? AND alm.producto = catprod.id AND catprod.token_cat_productos = ? AND alm.status_disponibilidad = TRUE",
              ['RUxTbHNPeEtmNERCY0hUT3kvTVJndz09OjoxMjM0NTY3ODEyMzQ1Njc4',$vProd->token_cat_productos]);            

            $resTotalExistProdTerminado = $totalExistProdTerminado[0]->existencia == '' || $totalExistProdTerminado[0]->existencia == NULL ? 0 : $totalExistProdTerminado[0]->existencia;
        
            $dirAlm = DB::table("teci_direcciones AS dir")
            ->join("teci_pais AS detpais","dir.pais","detpais.id")
            ->join("in_egr_establecimientos_catalogo AS estab","dir.establecimiento","estab.id")
            ->join("in_egr_establecimientos_almacen AS alm","estab.id","alm.almacen")
            ->join("in_egr_establecimientos_almacen_nivel AS nivel","alm.nivel_almacen","nivel.id")
            ->join("in_egr_catalogo_productos AS catprod","alm.producto","catprod.id")
            ->where(["dir.status" => TRUE,"nivel.token_almacen_nivel" => "RUxTbHNPeEtmNERCY0hUT3kvTVJndz09OjoxMjM0NTY3ODEyMzQ1Njc4","catprod.token_cat_productos" => $vProd->token_cat_productos])->get();

            //echo "dirAlm ".count($dirAlm);

            foreach ($dirAlm as $vDirAlm) {
                //$list->__SET('id_producto',$res_catProd->id_producto);
                $token_establecimiento = $vDirAlm->token_establecimiento;
                $datalias = $JwtAuth->desencriptar($vDirAlm->alias_establecimiento);
                $desgloseAlm3 = array();
                if ($vDirAlm->pais == 'México') {
                  $estado =$JwtAuth->desencriptar($vDirAlm->estado_edit);
                  $municipio =$JwtAuth->desencriptar($vDirAlm->municipio_edit);
                  $c_postal = $vDirAlm->c_postal_edit;
                  $colonia = $JwtAuth->desencriptar($vDirAlm->colonia_edit);
                  $dir_completaAlm = "Estado: $estado, Municipio: $municipio, C.P. $c_postal, Colonia: $colonia";
                } else {
                  $cod_postalext = $JwtAuth->desencriptar($vDirAlm->cod_postalext);
                  $dir_completaAlm = "Alias: ".$JwtAuth->desencriptar($vDirAlm->alias).", Direccion completa ".$cod_postalext.", ".$vDirAlm->pais;
                } 

                $dataExistAlmProdTerminado = DB::select("SELECT SUM(alm.existencia)AS existencia
                    FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod 
                    JOIN in_egr_establecimientos_almacen_nivel AS nivel JOIN in_egr_establecimientos_catalogo AS estab
                    WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? 
                    AND alm.almacen = estab.id AND estab.token_establecimiento = ?
                    AND alm.producto = catprod.id AND catprod.token_cat_productos = ?
                    AND alm.status_disponibilidad = TRUE",['RUxTbHNPeEtmNERCY0hUT3kvTVJndz09OjoxMjM0NTY3ODEyMzQ1Njc4',$vDirAlm->token_establecimiento,$vProd->token_cat_productos]);            

                $existAlm = $dataExistAlmProdTerminado[0]->existencia;
            
                $desgloseExistAlmProdTerminado = DB::select("SELECT alm.token_establecimiento_almacen,alm.almacen,alm.num_serie,alm.num_lote,alm.importado,alm.existencia,alm.unidad_entrada,alm.unidad_salida,alm.costo_aplicable,
                  alm.status_disponibilidad,alm.recepcion_compra FROM in_egr_establecimientos_almacen AS alm JOIN in_egr_catalogo_productos AS catprod JOIN in_egr_establecimientos_almacen_nivel AS nivel 
                  JOIN in_egr_establecimientos_catalogo AS estab WHERE alm.nivel_almacen = nivel.id AND nivel.token_almacen_nivel = ? AND alm.almacen = estab.id AND estab.token_establecimiento = ? AND alm.producto = catprod.id 
                  AND catprod.token_cat_productos = ? AND alm.status_disponibilidad = TRUE",['RUxTbHNPeEtmNERCY0hUT3kvTVJndz09OjoxMjM0NTY3ODEyMzQ1Njc4',$vDirAlm->token_establecimiento,$vProd->token_cat_productos]);

                foreach ($desgloseExistAlmProdTerminado as $desgExistAlm) {
                    //echo $desgExistAlm->id_nivel." ";
                    $desgloSerie = !$row_prd_num_serie || empty($desgExistAlm->num_serie) ? '---' : $JwtAuth->desencriptar(DB::table("inventarios_catalogo_series")->where("id",$desgExistAlm->num_serie)->value("serie_codigo"));
                    $desgloLote = !$row_prd_num_lote || empty($desgExistAlm->num_lote) ? '---' : $JwtAuth->desencriptar(DB::table("inventarios_catalogo_lotes")->where("id",$desgExistAlm->num_lote)->value("numero_lote"));
                    $desgloImport = !$row_prd_importado || empty($desgExistAlm->importado) ? '---' : $JwtAuth->desencriptar(DB::table("inventarios_catalogo_pedimento_aduanal")->where("id",$desgExistAlm->importado)->value("numero_pedimento"));

                    $unidad_entrada_complete = $desgExistAlm->unidad_entrada;
                    $unidad_entrada_abrev = "";

                    $unidad_salida_complete = $desgExistAlm->unidad_salida;
                    $unidad_salida_abrev = "";

                    $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaUnidadMedidaProducto');
                    if ($response->successful()) {
                      $datos = $response->json();
                      $cantidadRegistros = is_array($datos) ? count($datos) : 0;
                      $indice_u_entrada = array_search($unidad_entrada_complete, array_column($datos["unidades_medida"], "nombre"));
                      $unidad_entrada_abrev = $datos["unidades_medida"][$indice_u_entrada]["simbolo"];

                      $indice_u_entrada = array_search($unidad_salida_complete, array_column($datos["unidades_medida"], "nombre"));
                      $unidad_salida_abrev = $datos["unidades_medida"][$indice_u_entrada]["simbolo"];
                      //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
                    }

                    $arraInternoDesg1 = array(
                      "token_detalle_almacen" => $desgExistAlm->token_establecimiento_almacen,
                      "num_serie" => $desgloSerie,
                      "num_lote" => $desgloLote,
                      "desgloImport" => $desgloImport,
                      "unidad_entrada_complete" => $unidad_entrada_complete,
                      "unidad_entrada_abrev" => $unidad_entrada_abrev,
                      "existencia" => $desgExistAlm->existencia,
                      "unidad_salida_complete" => $unidad_salida_complete,
                      "unidad_salida_abrev" => $unidad_salida_abrev,
                      "existencia_convert" => "",
                      "costo_aplicable" => "$".number_format($desgExistAlm->costo_aplicable,$moneda_decimales, '.', ','),
                      "status_disponibilidad" => $desgExistAlm->status_disponibilidad ? true : false,
                      "recepcion_compra" => empty($desgExistAlm->recepcion_compra) ? '---' : $JwtAuth->generarFolio(DB::table("eegr_compras_recepcion")->where("id",$desgExistAlm->recepcion_compra)->value("folio_recep")),
                    );
                    $desgloseAlm3[] = $arraInternoDesg1;
                }

                $internoArrayDir = array(
                    "establecimiento_token" => $token_establecimiento,
                    "establecimiento_alias" => $datalias,
                    "dir_completaAlm" => $dir_completaAlm,
                    "existAlm" => $existAlm,
                    "desgloseAlm3" => $desgloseAlm3
                );

                $arrayAlm3[] = $internoArrayDir;

            }
            $arrayNivalm = array(
                "resTotalExistProdTerminado" => $resTotalExistProdTerminado,
                "datosDir" => $arrayAlm3,
            );

            $arrayNivelAlmacen3[] = $arrayNivalm;
          }

          $rowPrd = array(
            "token_cat_productos" => $vProd->token_cat_productos,
            "modulo_destino" => $vProd->modulo_mostrador == TRUE ? "mostra_vent" : "ssic_menu_inven",
            "fecha_registro_prod" => gmdate('Y-m-d H:i:s', $vProd->fecha_registro_prod),
            "folio_prod" => $folio_prod,
            "producto" => $JwtAuth->desencriptar($vProd->producto),
            "existencia_total" => $resTotalExistMatPrim + $resTotalExistProdProcess + $resTotalExistProdTerminado,
            "almacen_materia_prima" => $arrayNivelAlmacen1,
            "almacen_produccion" => $arrayNivelAlmacen2,
            "almacen_producto_final" => $arrayNivelAlmacen3,
          );

          $productoRegistrado[] = $rowPrd; 
        }

        $dataMensaje = array('status' => 'success','code' => 200,'producto' => $productoRegistrado);
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

  private function obtenCompraFolioKardex($JwtAuth,$factura_compra){
    $queryCompra = DB::table("eegr_compras")
    ->where("id",$factura_compra)
    ->select("folio_compra","post_folio")
    ->first();
    if ($queryCompra) {
      return 'COMP-'.$JwtAuth->generarFolio($queryCompra->folio_compra).(!is_null($queryCompra->post_folio) ? '-'.$queryCompra->post_folio: '');
    }
  }

  private function obtenVentaFolioKardex($JwtAuth,$factura_venta){
    $queryVenta = DB::table("ingr_ventas")
    ->where("id",$factura_venta)
    ->select("folio_venta","post_folio")
    ->first();
    if ($queryVenta) {
      return 'VENT-'.$JwtAuth->generarFolio($queryVenta->folio_venta).(!is_null($queryVenta->post_folio) ? '-'.$queryVenta->post_folio: '');
    }
  }

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

      $queryKardexProductos = DB::table("in_egr_productos_kardex AS kdx")
      ->join("in_egr_catalogo_productos AS catprod","kdx.producto_id","=","catprod.id")
      ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'catprod.token_cat_productos' => $token_cat_productos,
        'catprod.status' => true,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->select('kdx.*','emp.e_moneda_code')
      ->get();

      if ($queryKardexProductos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron productos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $prod_kardex = array();

        foreach ($queryKardexProductos as $vKar) {
          $moneda_decimales = $JwtAuth->getMonedaAPI($vKar->e_moneda_code || 2);
          $factura_compra = !is_null($vKar->factura_compra) ? $this->obtenCompraFolioKardex($JwtAuth,$vKar->factura_compra) : '';
          $factura_venta = !is_null($vKar->factura_venta) ? $this->obtenVentaFolioKardex($JwtAuth,$vKar->factura_venta) : '';
          $folio_produccion = $vKar->proceso_produccion;
          $status_kardex = $vKar->status_kardex;
          $valproceso_produccion = null;

          /*switch ($vKar->status_kardex) {
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
          }*/

          $rowKardex = array(
            "token_kardex" => $vKar->token_kardex,	
            "producto" => $vKar->producto_id,	
            "fecha" => $JwtAuth->mostrarUnixAFechaMexico($vKar->fecha_kardex),	
            "status_kardex" => $status_kardex,	
            "concepto" => $vKar->concepto,	
            "factura_compra" => $factura_compra,	
            "factura_venta" => $factura_venta,
            "proceso_produccion" => $valproceso_produccion,
            "moneda_decimales" => $moneda_decimales,
            "valor_unitario" => "$".number_format($vKar->valor_unitario,$moneda_decimales, '.', ','), 	
            //recibir
            "recibir_cantidad" => $vKar->recibir_cantidad ?? 0,
            "recibir_valor" => "$".number_format($vKar->recibir_valor,$moneda_decimales, '.', ','), 	
            //transito_entrada
            "transito_entrada_cantidad" => $vKar->transito_entrada_cantidad ?? 0,
            "transito_entrada_valor" => "$".number_format($vKar->transito_entrada_valor,$moneda_decimales, '.', ','),
            //entrada
            "entrada_cantidad" => $vKar->entrada_cantidad ?? 0,
            "entrada_valor" => "$".number_format($vKar->entrada_valor,$moneda_decimales, '.', ','), 
            //entregar
            "entregar_cantidad" => $vKar->entregar_cantidad ?? 0,
            "entregar_valor" => "$".number_format($vKar->entregar_valor,$moneda_decimales, '.', ','), 	
            //transito_salida
            "transito_salida_cantidad" => $vKar->transito_salida_cantidad ?? 0,
            "transito_salida_valor" => "$".number_format($vKar->transito_salida_valor,$moneda_decimales, '.', ','), 
            //salida
            "salida_cantidad" => $vKar->salida_cantidad ?? 0,
            "salida_valor" => "$".number_format($vKar->salida_valor,$moneda_decimales, '.', ','), 
            //saldo
            "saldo_cantidad" => $vKar->saldo_cantidad ?? 0,
            "saldo_valor" => "$".number_format($vKar->saldo_valor,$moneda_decimales, '.', ','),
          );
          $prod_kardex[] = $rowKardex;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'producto_kardex' => $prod_kardex
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleProductoKardexMostrador(Request $request){
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
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
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
          $moneda_decimales = $JwtAuth->getMonedaAPI($vProd->e_moneda_code) || 2;
          //echo $moneda_decimales;

          QRCode::text($vProd->token_cat_productos)->setOutfile(Storage::path('public/root/' . $vProd->fecha_registro_prod . 'QRCode.png'))->png();
          $folio_prod = $vProd->folio_sistema != NULL && $vProd->folio_sistema != "" ? ('PROD-' . ($vProd->post_folio == NULL ? $JwtAuth->generarFolio($vProd->folio_sistema) : $JwtAuth->generarFolio($vProd->folio_sistema) . '-' . $vProd->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($vProd->temps_folio);

          $desglose_kardex = array();
          $kardexQuery = DB::table("in_egr_productos_kardex AS kdx")
          ->join("in_egr_catalogo_productos AS catprod","kdx.producto_id","=","catprod.id")
          ->where('catprod.token_cat_productos',$vProd->token_cat_productos)->get();

          foreach ($kardexQuery as $vKar) {
            $factura_compra = $vKar->factura_compra;//!is_null($vKar->factura_compra) ? DB::table("eegr_compras")->where("id",$vKar->factura_compra)->value("folio_compra") : '';
            $factura_venta = !is_null($vKar->factura_venta) ? DB::table("ingr_ventas")->where("id",$vKar->factura_venta)->value("folio_venta") : '';
            $folio_produccion = $vKar->proceso_produccion;
            $status_kardex = $vKar->status_kardex;
            $valproceso_produccion = null;

            /*switch ($vKar->status_kardex) {
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
            }*/

            $forKardex = array(
              "token_kardex" => $vKar->token_kardex,	
              "producto" => $vKar->producto,	
              "fecha" => $JwtAuth->mostrarUnixAFechaMexico($vKar->fecha_kardex),	
              "status_kardex" => $status_kardex,	
              "concepto" => $vKar->concepto,	
              "factura_compra" => $factura_compra,	
              "factura_venta" => $factura_venta,
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

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'producto' => $productoRegistrado
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleProductoVigenteByCode(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $listaProductosTrue = array();
    $arrayProdProv = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "scanner_codigo" => "required|string",
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
        $scanner_codigo = $parametrosArray["scanner_codigo"];
        //echo $scanner_codigo; 

        $prodList = ProductosModelo::join("in_egr_catalogo_productos_claves_internas AS klav", "in_egr_catalogo_productos.id", "=", "klav.producto_alta")
          ->join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'klav.clave_valor' => $scanner_codigo,
            'in_egr_catalogo_productos.status' => true,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($prodList as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          //relacion con proveedores 
          $listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
            ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
              'eegr_catalogo_proveedores.status' => true
            ])->get();

          foreach ($listaProveedores as $resListProv) {

            $provservLista = DB::table("in_egr_catalogo_productos_claves AS clavprod")
              ->join("in_egr_catalogo_productos AS catprod", "clavprod.productoid", "=", "catprod.id")
              ->join("eegr_catalogo_proveedores AS catprov", "clavprod.proveedor", "=", "catprov.id")
              ->where([
                'catprov.token_cat_proveedores' => $resListProv->token_cat_proveedores,
                'catprod.token_cat_productos' => $value->token_cat_productos,
                'catprov.status' => TRUE
              ])->get();
            //$claveAsignada = '';
            $tiene_clave = '';
            $claveAsignada = '';
            $token_producto_claves = '';
            $encendido = false;
            $trProv = '';
            //$periodicidad_c_v = ''; 
            //$notificacion_c_v = ''; 
            //$inicio_periodo = '';
            //$fin_periodo = '';
            foreach ($provservLista as $relservprov) {
              //echo $relservprov->productoid;
              $token_producto_claves = $relservprov->token_producto_claves;
              $claveAsignada = $relservprov->identificador;

              if ($relservprov->tiene_clave == TRUE) {
                $tiene_clave = 'true';
              } else {
                $tiene_clave = 'false';
              }

              $encendido = true;
              $trProv = 'trCliente';
              /*$notificacion_c_v = $relservprov->notificacion_c_v;
                                if ($relservprov->periodicidad_c_v == 'usa') {
                                    $periodicidad_c_v = 'eventual'; 
                                } else if ($relservprov->periodicidad_c_v == 'ind') {
                                    $periodicidad_c_v = 'pIndeterminado'; 
                                } else {
                                    $periodicidad_c_v = 'pDeterminado'; 
                                }
                                if ($relservprov->inicio_periodo != '') {
                                    $inicio_periodo = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$relservprov->inicio_periodo);
                                } else {
                                    $inicio_periodo = '';
                                }
                                
                                if ($relservprov->fin_periodo != '') {
                                    $fin_periodo = $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$relservprov->fin_periodo);
                                } else {
                                    $fin_periodo = '';
                                }*/
            }
            if ($resListProv->rfc_taxId != NULL) {
              $dataResRfc = $JwtAuth->desencriptar($resListProv->rfc_taxId);
            } else {
              $dataResRfc = $resListProv->rfc_generico;
            }
            $nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

            $arrayForeach = array(
              "token_cat_proveedores" => $resListProv->token_cat_proveedores,
              "folio" => $JwtAuth->generar($resListProv->folio),
              "rfc" => $dataResRfc,
              "nombre" => $nombreProv,
              /*"periodicidad_c_v" => $periodicidad_c_v,
                            "notificacion_c_v" => $notificacion_c_v,    
                            "inicio_periodo" => $inicio_periodo,
                            "fin_periodo" => $fin_periodo,*/
              "tiene_clave" => $tiene_clave,
              "tiene_clave_respaldo" => $tiene_clave,
              "asigned_clave" => $claveAsignada,
              "asigned_clave_respaldo" => $claveAsignada,
              "token_producto_claves" => $token_producto_claves,
              "encendido" => $encendido,
              "class" => $trProv,
              "tdClass" => "",
              "btnClass" => false,
            );
            $arrayProdProv[] = $arrayForeach;
          }

          $num_serie = $value->num_serie == TRUE ? true : false;
          $num_lote = $value->num_lote == true ? true : false;
          $importado = $value->importado == TRUE ? true : false;

          if (
            $value->imagen == '' || !file_exists(Storage::path('public/root/' .
              $value->root_tkn . '/0002-cpp/catalogos/productos/' . $JwtAuth->generar($value->folio_sistema) .
              '-' . $value->fecha_sistema . '/' . $JwtAuth->desencriptar($value->imagen))) ||
            $JwtAuth->desencriptar($value->imagen) == 'default_prod.jpg'
          ) {
            $logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/settings/default_prod.jpg'));
          } else {
            $logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
              $value->root_tkn . '/0002-cpp/catalogos/productos/' . $JwtAuth->generar($value->folio_sistema) .
              '-' . $value->fecha_sistema . '/' . $JwtAuth->desencriptar($value->imagen)));
          }

          $uso_producto = $value->uso_producto == TRUE ? true : false;

          //Use App\Models\UMedidaModelo;
          $listMedidas = UMedidaModelo::all();
          $arrayMedidas = array();
          foreach ($listMedidas as $valmed) {
            $eachmed = array(
              "token_unidad_medida" => $valmed->token_unidad_medida,
              "unidad_medida" => $valmed->unidad_medida,
              "sat_clave" => $valmed->sat_clave,
              "representa" => $valmed->representa,
              "selected" => $valmed->token_unidad_medida == $value->token_unidad_medida ? true : false,
            );
            $arrayMedidas[] = $eachmed;
          }

          $arrayCaracteristicas = array();
          $selectCaracteristicas = DB::table("eegr_catalogo_productos_caracteristicas AS caractList")
            ->join("in_egr_catalogo_productos AS catprod", "caractList.producto", "catprod.id")
            ->join("main_empresas AS emp", "catprod.admin_empresa", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
            ->where([
              'catprod.token_cat_productos' => $value->token_cat_productos,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->get();

          foreach ($selectCaracteristicas as $valCaract) {
            $listeach = array(
              "token_caracteristicas" => $valCaract->token_caracteristicas,
              "clave_caract" => $valCaract->clave_caract,
              "valor_caract" => $valCaract->valor_caract,
            );
            $arrayCaracteristicas[] = $listeach;
          }

          //$conceptClass = DB::select("SELECT concepto FROM sos_ps_clasificacion WHERE token_clasificacion = ?",[$value->token_clasificacion]);   
          //$conceptGenero = DB::select("SELECT concepto FROM sos_ps_genero WHERE token_genero = ?",[$value->token_genero]);
          $arrayGenero = array();
          $listClass = ClasificacionModelo::join("sos_ps_genero AS gen", "sos_ps_clasificacion.id", "gen.clasificacion")
            ->where('sos_ps_clasificacion.token_clasificacion', '=', $value->token_clasificacion)->get();

          foreach ($listClass as $classVal) {
            $eachList = array(
              "token_clasificacion" => $classVal->token_clasificacion,
              "concepto" => $classVal->concepto,
              "codigo" => $classVal->codigo,
              "token_genero" => $classVal->token_genero,
              "folio_genero" => $classVal->folio_genero,
              "clasificacion" => $classVal->clasificacion,
              "selected" => $classVal->token_genero == $value->token_genero ? true : false,
            );
            $arrayGenero[] = $eachList;
          }

          if ($value->post_folio == NULL) {
            $folio_prod = 'PROD-' . $JwtAuth->generarFolio($value->folio_sistema);
          } else {
            $folio_prod = 'PROD-' . $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio;
          }

          $arrayForeachModal = array(
            "token_cat_productos" => $value->token_cat_productos,
            "folio_prod" => $folio_prod,
            "fechaAlta" => $JwtAuth->convierteEpocFechaHtml($value->zona_horaria, $value->fechaAlta),
            "horaAlta" => date('H:i:s', $value->fechaAlta),
            "producto" => $JwtAuth->desencriptar($value->producto),
            "marca" => $value->marca != NULL && $value->marca != "" ? $JwtAuth->desencriptar($value->marca) : null,
            "clasificacion" => $value->token_clasificacion,
            "genero" => $value->token_genero,
            "arrayGenero" => $arrayGenero,
            "folio_clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' . $JwtAuth->generar($value->clasif_folio),
            //"concept_class" => 'clasificación: '.$conceptClass[0]->concepto.', genero: '.$conceptGenero[0]->concepto,
            "costo_aplicable" => "$" . ($value->costo_aplicable != "" ? number_format($value->costo_aplicable, $value->moneda_aplicable_clave_decimales, '.', ',') . " " . $value->moneda_aplicable_clave : "0.00"),
            //$value->costo_aplicable	moneda_aplicable_clave moneda_aplicable_clave_decimales
            "proceso" => $value->proceso,
            "uso_producto" => $uso_producto,
            "stock_min" => $value->stock_min,
            "stock_max" => $value->stock_max,
            "costeo" => $value->costeo,
            "imagen" => $logo_prod,
            "num_serie" =>  $num_serie,
            "num_lote" => $num_lote,
            "importado" => $importado,
            "codigo_gs1" => $value->codigo_gs1,
            "caracteristicas" => $arrayCaracteristicas,
            //SAT
            "concepto" => $value->concepto,
            "clave" => $value->clave,
            "descripcion" => $value->descripcion,
            "token_prodservsat" => $value->token_prodservsat,
            //NUIDAD DE MEDIDA
            "unidad_medida" => $value->token_unidad_medida,
            "concepto_unidad_medida" => $value->sat_clave . '-' . $value->unidad_medida,
            "arrayMedidas" => $arrayMedidas,
            "arrayProdProv" => $arrayProdProv,
          );
          $listaProductosTrue[] = $arrayForeachModal;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'producto' => $listaProductosTrue
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

  public function detalleProductoProveedor(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $ordenes = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      //validar 
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'tokenProveedor' => 'required|string',
        'token_articulo' => 'required|string',
        'identificador' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación del usuario invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_articulo = $parametrosArray['token_articulo'];
        $tokenProveedor = $parametrosArray['tokenProveedor'];
        $prodList = ProductosModelo::join("in_egr_catalogo_productos_claves AS clavprod", "in_egr_catalogo_productos.id", "=", "clavprod.productoid")
          ->join("eegr_catalogo_proveedores AS catprov", "clavprod.proveedor", "=", "catprov.id")
          ->join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'in_egr_catalogo_productos.token_cat_productos' => $token_articulo,
            'catprov.token_cat_proveedores' => $tokenProveedor,
            //'clavprod.identificador' => $parametrosArray['noIdentificacionXML'],
            'in_egr_catalogo_productos.status' => true,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
        //echo count($prodList).$prodList[0]->productoid;
        if (count($prodList) > 0) {
          foreach ($prodList as $vPrd) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'articulo homologado',
              'token_articulo' => $token_articulo,
              'clave' => $vPrd->tiene_clave == TRUE ? $vPrd->identificador : '',
              'identificador' => 'Producto',
              'bool_serie' => $vPrd->num_serie == TRUE ? true : false,
              'bool_lote' => $vPrd->num_lote == TRUE ? true : false,
              'bool_pedimento' => $vPrd->importado == TRUE ? true : false,
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'info',
            'code' => 200,
            'message' => 'Los codigos de identificación de acuerdo al proveedor seleccionado no coinciden'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'No fue posible procesar los datos recibidos'
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function recargaProvProductos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayProdProv = array();

    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $listaProveedores = ProveedoresModelo::join("personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
          ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "=", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
          ->where([
            'eegr_catalogo_proveedores.status' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
            'eegr_catalogo_proveedores.status' => true
          ])->get();
        foreach ($listaProveedores as $resListProv) {

          $provservLista = DB::table("in_egr_catalogo_productos_claves AS clavprod")
            ->join("in_egr_catalogo_productos AS catprod", "clavprod.productoid", "=", "catprod.id")
            ->join("eegr_catalogo_proveedores AS catprov", "clavprod.proveedor", "=", "catprov.id")
            ->where([
              'catprov.token_cat_proveedores' => $resListProv->token_cat_proveedores,
              'catprod.token_cat_productos' => $parametrosArray['token_cat_productos'],
              'catprov.status' => TRUE
            ])->get();

          $tiene_clave = '';
          $claveAsignada = '';
          $token_producto_claves = '';
          $encendido = false;
          $trProv = '';
          foreach ($provservLista as $relservprov) {

            $token_producto_claves = $relservprov->token_producto_claves;
            $claveAsignada = $relservprov->identificador;

            if ($relservprov->tiene_clave == TRUE) {
              $tiene_clave = 'true';
            } else {
              $tiene_clave = 'false';
            }

            $encendido = true;
            $trProv = 'trCliente';
          }
          if ($resListProv->rfc_taxId != NULL) {
            $dataResRfc = $JwtAuth->desencriptar($resListProv->rfc_taxId);
          } else {
            $dataResRfc = $resListProv->rfc_generico;
          }
          if ($resListProv->denominacion_rs != '') {
            $nombreProv = $JwtAuth->desencriptar($resListProv->denominacion_rs);
          } else {
            $nombreProv = $JwtAuth->desencriptar($resListProv->paterno) . " " .
              $JwtAuth->desencriptar($resListProv->materno) . " " .
              $JwtAuth->desencriptar($resListProv->nombre);
          }

          $arrayForeach = array(
            "token_cat_proveedores" => $resListProv->token_cat_proveedores,
            "folio" => $JwtAuth->generar($resListProv->folio),
            "rfc" => $dataResRfc,
            "nombre" => $nombreProv,
            "tiene_clave" => $tiene_clave,
            "tiene_clave_respaldo" => $tiene_clave,
            "asigned_clave" => $claveAsignada,
            "asigned_clave_respaldo" => $claveAsignada,
            "token_producto_claves" => $token_producto_claves,
            "encendido" => $encendido,
            "class" => $trProv,
            "tdClass" => "",
            "btnClass" => false,
          );

          $arrayProdProv[] = $arrayForeach;
        }

        $dataMensaje = array(
          'arrayProdProv' => $arrayProdProv,
          'code' => 200,
          'status' => 'success'
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateArticuloLogo(Request $request){
    $JwtAuth = new \JwtAuth();
    $imgProdCaarga = $request->file('imgProdCaarga');
    $jsonData = $request->input('data_producto');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);
    $jsonUser = $request->input('user_token');
    $parametrosUser = json_decode($jsonUser);
    $parametrosArrayUser = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string'
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $selectFolio = DB::select("SELECT prodlist.imagen,catprod.fecha_sistema,catprod.folio_sistema,emp.root_tkn
                    FROM in_egr_catalogo_productos AS catprod JOIN productos AS prodlist JOIN main_empresas AS emp  
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                    WHERE catprod.token_cat_productos = ? AND catprod.producto = prodlist.id 
                    AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                    AND empuser.personal = pers.id AND pers.usuario = users.id 
                    AND users.usuario_token= ?", [$parametrosArray['token_cat_productos'], $usuario->empresa_token, $usuario->user_token]);
        $filepath = $selectFolio[0]->root_tkn . "/0002-cpp/catalogos/productos/" . $JwtAuth->generar($selectFolio[0]->folio_sistema) . "-" . $selectFolio[0]->fecha_sistema . "/";

        if (!file_exists(storage_path("/root/" . $filepath))) {
          Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
        }

        if (file_exists($request->file('imgProdCaarga'))) {
          //eliminar imagen anterior
          $nombre_imagen = $request->file('imgProdCaarga')->getClientOriginalName();
          Storage::putFileAs("/public/root/" . $filepath, $request->file('imgProdCaarga'), $nombre_imagen);
          $updateImg = DB::table("productos AS prodlist")
            ->join("in_egr_catalogo_productos AS catprod", "prodlist.id", "catprod.producto")
            ->join("main_empresas AS emp", "catprod.admin_empresa", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
            ->join("personal AS pers", "empuser.personal", "pers.id")
            ->join("teci_usuarios_catalogo AS users", "pers.usuario", "users.id")
            ->where([
              'catprod.token_cat_productos' => $parametrosArray['token_cat_productos'],
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])
            ->limit(1)->update(
              array(
                'prodlist.imagen' => $JwtAuth->encriptar($nombre_imagen),
              )
            );
          if ($updateImg) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Logotipo de producto actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'El logotipo del producto no ha sido actualizado, intente nuevamente o comuniquese a soporte'
            );
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateGeneralesProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);

    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string',
        'concepto' => 'required|string',
        'familia' => 'required|string',
        'clasificacion' => 'required|string',
        'genero' => 'required|string',
        'marca' => 'string',
        'stock_min' => 'required|numeric',
        'stock_max' => 'required|numeric',
        'costeo' => 'required|string',
        'unidad_entrada_clave' => 'string',
        'unidad_salida_clave' => 'string',
        'moneda_codigo' => 'string',
        'cuenta_contable' => 'string',
        'uso_prod' => 'nullable|string',
        'num_serie' => 'boolean',
        'num_lote' => 'boolean',
        'pedimentoAduanal' => 'boolean',
        'sat_clave_code' => 'nullable|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray["token_cat_productos"];
        $concepto = $parametrosArray["concepto"];
        $familia = $parametrosArray["familia"];
        $clasificacion = $parametrosArray["clasificacion"];
        $genero = $parametrosArray["genero"];
        $marca = $parametrosArray["marca"];
        $stock_min = $parametrosArray["stock_min"];
        $stock_max = $parametrosArray["stock_max"];
        $costeo = $parametrosArray["costeo"];
        $unidad_entrada_clave = $parametrosArray["unidad_entrada_clave"];
        $unidad_salida_clave = $parametrosArray["unidad_salida_clave"];
        $moneda_codigo = $parametrosArray["moneda_codigo"];
        $cuenta_contable = $parametrosArray["cuenta_contable"];
        $uso_prod = $parametrosArray["uso_prod"];
        $num_serie = $parametrosArray["num_serie"];
        $num_lote = $parametrosArray["num_lote"];
        $pedimentoAduanal = $parametrosArray["pedimentoAduanal"];
        $sat_clave_code = $parametrosArray["sat_clave_code"];

        $validacion_concepto = isset($concepto) && !empty($concepto) && preg_match($JwtAuth->filtroAlfaNumerico(),$concepto);
        $validacion_familia = isset($familia) && !empty($familia);
        $validacion_clasificacion = isset($clasificacion) && !empty($clasificacion);
        $validacion_genero = isset($genero) && !empty($genero);
        $validacion_stock = isset($stock_min) && isset($stock_max) && ((empty($stock_min) && empty($stock_max)) || (!empty($stock_min) && preg_match($JwtAuth->filtroNumerico(),$stock_min) && $stock_min > 0 && !empty($stock_max) && preg_match($JwtAuth->filtroNumerico(),$stock_max) && $stock_max > 0 && $stock_max > $stock_min));
        $validacion_costeo = isset($costeo) && !empty($costeo) && preg_match($JwtAuth->filtroAlfaNumerico(),$costeo);
        $validacion_unidad_entrada_clave = isset($unidad_entrada_clave) && !empty($unidad_entrada_clave) && preg_match($JwtAuth->filtroAlfaNumerico(),$unidad_entrada_clave);
        $validacion_unidad_salida_clave = isset($unidad_salida_clave) && !empty($unidad_salida_clave) && preg_match($JwtAuth->filtroAlfaNumerico(),$unidad_salida_clave);
        $validacion_moneda = isset($moneda_codigo) && !empty($moneda_codigo) && preg_match($JwtAuth->filtroAlfaNumerico(),$moneda_codigo);
        $validacion_cuenta_contable = isset($cuenta_contable) && !empty($cuenta_contable) && preg_match($JwtAuth->filtroAlfaNumerico(),$cuenta_contable);
        $validacion_uso_prod = isset($uso_prod) && !empty($uso_prod) && preg_match($JwtAuth->filtroAlfaNumerico(),$uso_prod);
        $validacion_num_serie = isset($num_serie) && is_bool($num_serie);
        $validacion_num_lote = isset($num_lote) && is_bool($num_lote);
        $validacion_pedimentoAduanal = isset($pedimentoAduanal) && is_bool($pedimentoAduanal);

        if ($validacion_concepto && $validacion_familia && $validacion_clasificacion && $validacion_genero && $validacion_stock && $validacion_costeo && 
          $validacion_unidad_entrada_clave && $validacion_unidad_salida_clave && $validacion_moneda && $validacion_num_serie && 
          $validacion_num_lote && $validacion_pedimentoAduanal) {

          $queryEmp = DB::select("SELECT emp.id FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
            AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);
  
          foreach ($queryEmp as $vEmp) {
            $data_concepto = $JwtAuth->encriptar(strtolower($concepto));
            $serie_num = $validacion_uso_prod && $num_serie == true ? TRUE : FALSE;
            $lote_num = $validacion_num_serie && $num_lote == true ? TRUE : FALSE;
            $aduan_pedim = $validacion_num_lote && $pedimentoAduanal == true ? TRUE : FALSE;
            $clasifProd = DB::table("sos_ps_clasificacion")->where("token_clasificacion", $clasificacion)->value("id");
            $genroProd = DB::table("sos_ps_genero")->where("token_genero", $genero)->value("id");
            $data_sat_code = isset($sat_clave_code) && !empty($sat_clave_code) ? $sat_clave_code : NULL;
            $data_marca = isset($marca) && !empty($marca) ? $JwtAuth->encriptar(strtolower($marca)) : NULL;

            $moneda_decimales = "";

            $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaMonedas');
            if ($response->successful()) {
              $datos = $response->json();
              $cantidadRegistros = is_array($datos) ? count($datos) : 0;
              $indice = array_search($moneda_codigo, array_column($datos["monedas"], "code"));
              $moneda_decimales = $datos["monedas"][$indice]["decimales"];
              //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
            }

            $updateProdGen = DB::table("in_egr_catalogo_productos AS catprod")
            ->join("main_empresas AS emp", "catprod.admin_empresa", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
            ->where([
              'catprod.token_cat_productos' => $token_cat_productos,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])
            ->limit(1)->update(
              array(
                'catprod.producto' => $data_concepto,
                'catprod.familia' => $familia,
                'catprod.clasificacion' => $clasifProd,
                'catprod.genero' => $genroProd,
                'catprod.marca' => $data_marca,
                'catprod.stock_min' => $stock_min,
                'catprod.stock_max' => $stock_max,
                'catprod.costeo' => $costeo,
                'catprod.unidad_medida_entrada_clave' => $unidad_entrada_clave,
                'catprod.unidad_medida_salida_clave' => $unidad_salida_clave,
                'catprod.moneda_aplicable_clave' => $moneda_codigo,
                'catprod.moneda_aplicable_clave_decimales' => $moneda_decimales,
                'catprod.cuenta_contable' => $validacion_cuenta_contable ? $JwtAuth->encriptar($cuenta_contable) : NULL,
                'catprod.uso_producto' => $uso_prod,
                'catprod.num_serie' => $serie_num,
                'catprod.num_lote' => $lote_num,
                'catprod.importado' => $aduan_pedim,
                'catprod.sat_clave_code' => $data_sat_code
              )
            );

            if ($updateProdGen) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Producto actualizado satisfactoriamente'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'La información de este producto no ha sido actualizada, intente nuevamente o comuniquese a soporte'
              );
            }

          }
          
        } else {
          $error_alerta = "";
          if (!$validacion_concepto) {
            $error_alerta = "Error al ingresar concepto del producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_familia) {
            $error_alerta = "Error al seleccionar familia del producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_clasificacion) {
            $error_alerta = "Error al seleccionar clasificación de producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_genero) {
            $error_alerta = "Error al seleccionar genero de producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_stock) {
            $error_alerta = "Error al ingresar stock mínimo / máximo, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_costeo) {
            $error_alerta = "Error al seleccionar método de costeo, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_unidad_entrada_clave) {
            $error_alerta = "Error al seleccionar unidad de medida de entrada, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_unidad_salida_clave) {
            $error_alerta = "Error al seleccionar unidad de medida de salida, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_moneda) {
            $error_alerta = "Error al seleccionar moneda, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_uso_prod) {
            $error_alerta = "Error al seleccionar uso del producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_num_serie) {
            $error_alerta = "Error al seleccionar si el producto debe contener número de serie, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_num_lote) {
            $error_alerta = "Error al seleccionar si el producto debe contener número de lote, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_pedimentoAduanal) {
            $error_alerta = "Error al seleccionar si el producto debe contener número de pedimento aduanal, verifique su información o comuniquese a soporte";
          }
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $error_alerta);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateGeneralesMostraVentProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);

    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string',
        'concepto' => 'required|string',
        'precio_aplicable' => 'required|string',
        'unidad_salida_clave' => 'required|string',
        'moneda_codigo' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray["token_cat_productos"];
        $concepto = $parametrosArray["concepto"];
        $precio_aplicable = $parametrosArray["precio_aplicable"];
        $unidad_salida_clave = $parametrosArray["unidad_salida_clave"];
        $moneda_codigo = $parametrosArray["moneda_codigo"];

        $validacion_concepto = isset($concepto) && !empty($concepto) && preg_match($JwtAuth->filtroAlfaNumerico(),$concepto);
        $validacion_precio_aplicable = isset($precio_aplicable) && !empty($precio_aplicable) && preg_match($JwtAuth->filtroNumerico(),$precio_aplicable);
        $validacion_unidad_salida_clave = isset($unidad_salida_clave) && !empty($unidad_salida_clave);
        $validacion_moneda = isset($moneda_codigo) && !empty($moneda_codigo);

        if ($validacion_concepto && $validacion_precio_aplicable && $validacion_unidad_salida_clave && $validacion_moneda) {
          $queryEmp = DB::select("SELECT emp.id FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa 
            AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);
  
          foreach ($queryEmp as $vEmp) {
            $data_concepto = $JwtAuth->encriptar(strtolower($concepto));
            $moneda_decimales = "";
            $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaMonedas');
            if ($response->successful()) {
              $datos = $response->json();
              $cantidadRegistros = is_array($datos) ? count($datos) : 0;
              $indice = array_search($moneda_codigo, array_column($datos["monedas"], "code"));
              $moneda_decimales = $datos["monedas"][$indice]["decimales"];
              //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
            }

            $updateProdGen = DB::table("in_egr_catalogo_productos AS catprod")
            ->join("main_empresas AS emp", "catprod.admin_empresa", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
            ->where([
              'catprod.token_cat_productos' => $token_cat_productos,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])
            ->limit(1)->update(
              array(
                'catprod.producto' => $data_concepto,
                'catprod.costo_aplicable' => $precio_aplicable,
                'catprod.unidad_medida_salida_clave' => $unidad_salida_clave,
                'catprod.moneda_aplicable_clave' => $moneda_codigo,
                'catprod.moneda_aplicable_clave_decimales' => $moneda_decimales
              )
            );

            if ($updateProdGen) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Producto actualizado satisfactoriamente'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'La información de este producto no ha sido actualizada, intente nuevamente o comuniquese a soporte'
              );
            }

          }
          
        } else {
          $error_alerta = "";
          if (!$validacion_concepto) {
            $error_alerta = "Error al ingresar concepto del producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_precio_aplicable) {
            $error_alerta = "Error al seleccionar precio aplicable del producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_unidad_salida_clave) {
            $error_alerta = "Error al seleccionar unidad de medida de salida, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_moneda) {
            $error_alerta = "Error al seleccionar moneda, verifique su información o comuniquese a soporte";
          }
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $error_alerta);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function agregaCaracteristicasProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);

    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string',
        'nuevas_caracteristicas' => 'required|array',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray["token_cat_productos"];
        $caracteristicas = $parametrosArray["nuevas_caracteristicas"];

        if (count($caracteristicas) > 0) {
          $contador = 0;
          $obtenProducto = DB::table("in_egr_catalogo_productos")->where("token_cat_productos",$token_cat_productos)->value("id");
          for ($i = 0; $i < count($caracteristicas); $i++) {
            $clave_caract = $caracteristicas[$i]['clave_caract'];
            $valor_caract = $caracteristicas[$i]['valor_caract'];
            $tokenClabeProdProv = $JwtAuth->encriptarToken(time(), $clave_caract, $valor_caract);
            $queryCaract = DB::table('eegr_catalogo_productos_caracteristicas')
            ->insert(array(
              "token_caracteristicas" => $tokenClabeProdProv,
              "clave_caract" => $clave_caract,
              "valor_caract" => $valor_caract,
              "producto" => $obtenProducto,
            ));
            if ($queryCaract) {
              ++$contador;
            }
          }
          if ($contador == count($caracteristicas)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Producto actualizado satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'La información de este producto no ha sido actualizada, intente nuevamente o comuniquese a soporte'
            );
          }
          
        } else {
          $dataMensaje = array(
            'status' => 'error', 
            'code' => 200, 
            'message' => "Error al seleccionar si el producto debe contener número de pedimento aduanal, verifique su información o comuniquese a soporte"
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteCaracteristicasProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);

    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string',
        'caracteristicas' => 'required|array',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray["token_cat_productos"];
        $caracteristicas = $parametrosArray["caracteristicas"];

        if (count($caracteristicas) > 0) {
          $contador = 0;
          $obtenProducto = DB::table("in_egr_catalogo_productos")->where("token_cat_productos",$token_cat_productos)->value("id");
          for ($i = 0; $i < count($caracteristicas); $i++) {
            $token_caracteristicas = $caracteristicas[$i]['token_caracteristicas'];
            $queryCaract = DB::table("eegr_catalogo_productos_caracteristicas AS caract")
            ->join("in_egr_catalogo_productos AS catprod","caract.producto", "=", "catprod.id")
            ->join("main_empresas AS emp","catprod.admin_empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'caract.token_caracteristicas' => $token_caracteristicas,
              'catprod.token_cat_productos' => $token_cat_productos,
              'catprod.status' => TRUE,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->get();

            foreach ($queryCaract as $vCar) {
              $deleteCaract = DB::table("eegr_catalogo_productos_caracteristicas")->where(['token_caracteristicas' => $vCar->token_caracteristicas])->limit(1)->delete();
              if ($deleteCaract) {
                ++$contador;
              }
            }
          }
          if ($contador == count($caracteristicas)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Producto actualizado satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'La información de este producto no ha sido actualizada, intente nuevamente o comuniquese a soporte'
            );
          }
          
        } else {
          $dataMensaje = array(
            'status' => 'error', 
            'code' => 200, 
            'message' => "Error al seleccionar si el producto debe contener número de pedimento aduanal, verifique su información o comuniquese a soporte"
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function agregaClavesProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);

    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string',
        'nuevas_claves' => 'required|array',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray["token_cat_productos"];
        $nuevas_claves = $parametrosArray["nuevas_claves"];

        if (count($nuevas_claves) > 0) {
          $contador = 0;
          $obtenProducto = DB::table("in_egr_catalogo_productos")->where("token_cat_productos",$token_cat_productos)->value("id");
          for ($i = 0; $i < count($nuevas_claves); $i++) {
            $clave_name = $nuevas_claves[$i]['clave_name'];
            $valor_name = $nuevas_claves[$i]['valor_name'];
            $tokenClabeInside = $JwtAuth->encriptarToken(time(), $clave_name, $valor_name);
            $queryKlaves = DB::table('in_egr_catalogo_productos_claves_internas')
            ->insert(array(
              "token_alta_clave" => $tokenClabeInside,
              "producto_alta" => $obtenProducto,
              "clave_nombre" => $clave_name,
              "clave_valor" => $valor_name,
            ));
            if ($queryKlaves) {
              ++$contador;
            }
          }
          if ($contador == count($nuevas_claves)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Producto actualizado satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'La información de este producto no ha sido actualizada, intente nuevamente o comuniquese a soporte'
            );
          }
          
        } else {
          $dataMensaje = array(
            'status' => 'error', 
            'code' => 200, 
            'message' => "Error al seleccionar si el producto debe contener número de pedimento aduanal, verifique su información o comuniquese a soporte"
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteClavesProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);

    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string',
        'claves' => 'required|array',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray["token_cat_productos"];
        $claves = $parametrosArray["claves"];

        if (count($claves) > 0) {
          $contador = 0;
          for ($i = 0; $i < count($claves); $i++) {
            $token_claves = $claves[$i]['token_alta_clave'];
            $queryCaract = DB::table("in_egr_catalogo_productos_claves_internas AS klav")
            ->join("in_egr_catalogo_productos AS catprod","klav.producto_alta", "=", "catprod.id")
            ->join("main_empresas AS emp","catprod.admin_empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'klav.token_alta_clave' => $token_claves,
              'catprod.token_cat_productos' => $token_cat_productos,
              'catprod.status' => TRUE,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->get();

            foreach ($queryCaract as $vClav) {
              $deleteClav = DB::table("in_egr_catalogo_productos_claves_internas")->where(['token_alta_clave' => $vClav->token_alta_clave])->limit(1)->delete();
              if ($deleteClav) {
                ++$contador;
              }
            }
          }
          if ($contador == count($claves)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Producto actualizado satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'La información de este producto no ha sido actualizada, intente nuevamente o comuniquese a soporte'
            );
          }
          
        } else {
          $dataMensaje = array(
            'status' => 'error', 
            'code' => 200, 
            'message' => "Error al seleccionar si el producto debe contener número de pedimento aduanal, verifique su información o comuniquese a soporte"
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteClaveProdProveedor(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);
    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string',
        'prv_claves' => 'required|array',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray['token_cat_productos'];
        $productos_claves_prv = $parametrosArray['prv_claves'];

        $valida_cat_productos = isset($token_cat_productos) && !empty($token_cat_productos);
        $valida_claves = isset($productos_claves_prv) && count($productos_claves_prv) > 0;
        if ($valida_cat_productos && $valida_claves) {
          $contador = 0;
          for ($i=0; $i < count($productos_claves_prv); $i++) {
            $token_producto_claves = $productos_claves_prv[$i]['token_producto_claves'];
            $token_cat_proveedores = $productos_claves_prv[$i]['token_cat_proveedores'];
            $queryProdClabes = DB::table('in_egr_catalogo_productos_claves AS prodkey')
            ->join("in_egr_catalogo_productos AS catprod", "prodkey.productoid", "=", "catprod.id")
            ->join("eegr_catalogo_proveedores AS catprov", "prodkey.proveedor", "=", "catprov.id")
            ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'prodkey.token_producto_claves' => $token_producto_claves,
              'catprov.token_cat_proveedores' => $token_cat_proveedores,
              'catprod.token_cat_productos' => $token_cat_productos,
              'catprod.status' => TRUE,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->get();

            foreach ($queryProdClabes as $vPClav) {
              $deleteProdClabes = DB::table('in_egr_catalogo_productos_claves')
              ->where('token_producto_claves',$vPClav->token_producto_claves)->limit(1)->delete();
              if ($deleteProdClabes) {
                ++$contador;
              }
            }
          }

          if ($contador == count($productos_claves_prv)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Relación del producto con proveedor eliminada satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Relación del producto con proveedor no eliminada, intente nuevamente o comuniquese a soporte'
            );
          }
        } else {
          $mensaje_error = '';
          if (!$valida_cat_productos) {$mensaje_error = 'Error en producto seleccionado, verifique su información o comuniquese a soporte';}
          if (!$valida_claves) {$mensaje_error = 'Error en lista de claves seleccionada, verifique su información o comuniquese a soporte';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateClaveProdProveedor(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);
    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string',
        'prv_claves' => 'required|array',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray['token_cat_productos'];
        $productos_claves_prv = $parametrosArray['prv_claves'];

        $valida_cat_productos = isset($token_cat_productos) && !empty($token_cat_productos);
        $valida_claves = isset($productos_claves_prv) && count($productos_claves_prv) > 0;
        if ($valida_cat_productos && $valida_claves) {
          $contador = 0;
          for ($i=0; $i < count($productos_claves_prv); $i++) {
            $token_producto_claves = $productos_claves_prv[$i]['token_producto_claves'];
            $token_cat_proveedores = $productos_claves_prv[$i]['token_cat_proveedores'];
            $tiene_clave = $productos_claves_prv[$i]['tiene_clave'];
            $asigned_clave = $tiene_clave == true ? $productos_claves_prv[$i]['asigned_clave'] : NULL;
            $queryProdClabes = DB::table('in_egr_catalogo_productos_claves AS prodkey')
            ->join("in_egr_catalogo_productos AS catprod", "prodkey.productoid", "=", "catprod.id")
            ->join("eegr_catalogo_proveedores AS catprov", "prodkey.proveedor", "=", "catprov.id")
            ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'prodkey.token_producto_claves' => $token_producto_claves,
              'catprov.token_cat_proveedores' => $token_cat_proveedores,
              'catprod.token_cat_productos' => $token_cat_productos,
              'catprod.status' => TRUE,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->get();

            foreach ($queryProdClabes as $vPClav) {
              $bd_tiene_clave = $vPClav->tiene_clave == TRUE ? true : false;
              if ($tiene_clave && $bd_tiene_clave) {
                $updateProdClabes = DB::table('in_egr_catalogo_productos_claves')
                ->where('token_producto_claves',$vPClav->token_producto_claves)
                ->limit(1)->update(array("identificador" => $asigned_clave));
    
                if ($updateProdClabes) {
                  ++$contador;
                }
              } else {
                $updateProdClabes = DB::table('in_egr_catalogo_productos_claves')
                ->where('token_producto_claves',$vPClav->token_producto_claves)
                ->limit(1)->update(array("tiene_clave" => $tiene_clave,"identificador" => $asigned_clave));
    
                if ($updateProdClabes) {
                  ++$contador;
                }
              }
            }
          }

          if ($contador == count($productos_claves_prv)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Relación del producto con proveedor actualizada satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Relación del producto con proveedor no actualizada, intente nuevamente o comuniquese a soporte'
            );
          }
        } else {
          $mensaje_error = '';
          if (!$valida_cat_productos) {$mensaje_error = 'Error en producto seleccionado, verifique su información o comuniquese a soporte';}
          if (!$valida_claves) {$mensaje_error = 'Error en lista de claves seleccionada, verifique su información o comuniquese a soporte';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function appendClaveProdProveedor(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);
    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string',
        'prv_claves' => 'required|array',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray['token_cat_productos'];
        $productos_claves_prv = $parametrosArray['prv_claves'];

        $valida_cat_productos = isset($token_cat_productos) && !empty($token_cat_productos);
        $valida_claves = isset($productos_claves_prv) && count($productos_claves_prv) > 0;
        if ($valida_cat_productos && $valida_claves) {
          $obtenProducto = DB::table("in_egr_catalogo_productos")->where("token_cat_productos", $token_cat_productos)->value("id");
          $contador = 0;
          for ($i=0; $i < count($productos_claves_prv); $i++) {
            $prv_token = $productos_claves_prv[$i]['token_cat_proveedores'];
            $prv_tiene_clave = $productos_claves_prv[$i]['tiene_clave'] == "true" ? TRUE : FALSE;
            $prv_clave = $productos_claves_prv[$i]['tiene_clave'] == "true" ? $productos_claves_prv[$i]['asigned_clave'] : NULL;
            $obtenProv = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $prv_token)->value("id");
            $tokenClabeProdProv = $JwtAuth->encriptarToken($obtenProducto.$prv_clave.$obtenProv);
            $insertProdClabes = DB::table('in_egr_catalogo_productos_claves')
            ->insert(array(
              "token_producto_claves" => $tokenClabeProdProv,
              "productoid" => $obtenProducto,
              "proveedor" => $obtenProv,
              "cliente" => NULL,
              "tiene_clave" => $prv_tiene_clave,
              "identificador" => $prv_clave,
            ));

            if ($insertProdClabes) {
              ++$contador;
            }
          }

          if ($contador == count($productos_claves_prv)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Relación del producto con proveedor registrada satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Relación del producto con proveedor no registrada, intente nuevamente o comuniquese a soporte'
            );
          }
        } else {
          $mensaje_error = '';
          if (!$valida_cat_productos) {$mensaje_error = 'Error en producto seleccionado, verifique su información o comuniquese a soporte';}
          if (!$valida_claves) {$mensaje_error = 'Error en lista de claves seleccionada, verifique su información o comuniquese a soporte';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteAnexosProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);
    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string',
        'docs_delete' => 'required|array',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray['token_cat_productos'];
        $productos_docs_delete = $parametrosArray['docs_delete'];

        $valida_cat_productos = isset($token_cat_productos) && !empty($token_cat_productos);
        $valida_docs = isset($productos_docs_delete) && count($productos_docs_delete) > 0;
        if ($valida_cat_productos && $valida_docs) {
          $obtenProducto = DB::table("in_egr_catalogo_productos")->where("token_cat_productos", $token_cat_productos)->value("id");
          $contador = 0;
          for ($i=0; $i < count($productos_docs_delete); $i++) {
            $token_documento = $productos_docs_delete[$i]['token_documento'];
            $queryDocs = DB::table('sos_documentos AS docs')
            ->join("in_egr_catalogo_productos AS catprod", "docs.productos", "=", "catprod.id")
            ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'docs.token_documento' => $token_documento,
              'catprod.token_cat_productos' => $token_cat_productos,
              'catprod.status' => TRUE,
              'emp.empresa_token' => $usuario->empresa_token,
              'users.usuario_token' => $usuario->user_token,
            ])->get();

            foreach ($queryDocs as $vDocs) {
              $nombre_documento = $JwtAuth->desencriptar($vDocs->nombre_documento);
              $deleteDocs = DB::table('sos_documentos')->where('token_documento',$token_documento)->limit(1)->delete();
              if ($deleteDocs) {
                $filepath = "root/$vDocs->root_tkn/0002-cpp/catalogos/productos/anexos-$vDocs->fecha_registro_prod/$nombre_documento";
                ++$contador;
                if (Storage::disk('public')->exists($filepath)) {
                  Storage::disk('public')->delete($filepath);
                }
              }
            }
          }

          if ($contador == count($productos_docs_delete)) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Documento anexo al producto ha sido eliminado satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Documento anexo al producto no eliminado, intente nuevamente o comuniquese a soporte'
            );
          }
        } else {
          $mensaje_error = '';
          if (!$valida_cat_productos) {$mensaje_error = 'Error en producto seleccionado, verifique su información o comuniquese a soporte';}
          if (!$valida_docs) {$mensaje_error = 'Error en lista de anexos seleccionada, verifique su información o comuniquese a soporte';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraNuevoAnexosProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $parametrosArray = json_decode($jsonData, true);
    if (!empty($parametros) && !empty($parametrosArray)) {

      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_cat_productos' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray['token_cat_productos'];

        $valida_cat_productos = isset($token_cat_productos) && !empty($token_cat_productos);
        $docsProdAnexos = $_FILES['docsProdAnexos']['name'];
        //return response()->json(["message" => " registred ".count($docsProdAnexos),"code" => 200,"status" => "error"]);
        $valida_docs = !empty($_FILES['docsProdAnexos']['name'][0]);
        if ($valida_cat_productos && $valida_docs) {
          $contador = 0;
          $queryDocs = DB::table("in_egr_catalogo_productos AS catprod")
          ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'catprod.token_cat_productos' => $token_cat_productos,
            'catprod.status' => TRUE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
            ])->get();
            
          foreach ($queryDocs as $vDocs) {
            $obtenProducto = DB::table("in_egr_catalogo_productos")->where("token_cat_productos", $token_cat_productos)->value("id");
            $filepath = $vDocs->root_tkn."/0002-cpp/catalogos/productos/anexos-$vDocs->fecha_registro_prod/";
            if (!file_exists(storage_path("/root/".$filepath))){
              Storage::disk('root')->makeDirectory($filepath,0777, true, true);
            }
            foreach ($_FILES['docsProdAnexos']['name'] as $index => $name) {
              // Obtener información del archivo
              $type = $JwtAuth->getExtensionDoc($_FILES['docsProdAnexos']['type'][$index]);
              $tmpName = $_FILES['docsProdAnexos']['tmp_name'][$index];
              $error = $_FILES['docsProdAnexos']['error'][$index];
              $size = $_FILES['docsProdAnexos']['size'][$index];
              
              // Verificar si hubo errores al subir el archivo
              if ($error === UPLOAD_ERR_OK) {
                // Leer el contenido del archivo
                $content = file_get_contents($tmpName);
                
                // Preparar la sentencia SQL para insertar los datos
                $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%PROD-EVID%'");
                $tkn_evidencia = $JwtAuth->encriptarToken($obtenProducto,$usuario->user_token,$usuario->empresa_token,$name);
                $insertEvidenceProd = DB::table('sos_documentos')->insert(
                array(
                  "token_documento" => $tkn_evidencia,
                  "fecha_carga" => time(),
                  "modulo" => "proyectos",
                  "folio_modulo" => "PROD-EVID".$select_folio_doc[0]->folio,
                  "tipo_documento" => "file",
                  "nombre_documento" => $JwtAuth->encriptar($name),
                  "extension_documento" => $type,
                  "productos" => $obtenProducto,
                  "status_documento" => TRUE,	
                  "fecha_delete_documento" => NULL,
                  ) 
                );
                if ($insertEvidenceProd) {
                  ++$contador;
                  Storage::putFileAs("/public/root/".$filepath,$tmpName,$name);
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 404,
                  'message' => "Error al subir el archivo '$name'. Código de error: $error"
                );
              }
            }
  
            if ($contador == count($docsProdAnexos)) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Documentos anexos al producto han sido registrados satisfactoriamente'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Documentos anexos al producto no registrados, intente nuevamente o comuniquese a soporte'
              );
            }
          }
        } else {
          $mensaje_error = '';
          if (!$valida_cat_productos) {$mensaje_error = 'Error en producto seleccionado, verifique su información o comuniquese a soporte';}
          if (!$valida_docs) {$mensaje_error = 'Error en lista de anexos seleccionada, verifique su información o comuniquese a soporte';}
          $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function changAlmProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'proddata' => 'required|string',
        'tknTabAlm' => 'required|string',
        'tknDetAlm' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'almacen no encontrado',
          'errors' => $validate->errors()
        );
      } else {
        $selectAlmacen = DB::select(
          "SELECT alm.id,alm.alias_almacen FROM almacen AS alm JOIN main_empresas AS emp  
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
                    WHERE alm.token_almacen = ? AND alm.empresa = emp.id AND emp.empresa_token = ? 
                    AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                    AND pers.usuario = users.id AND users.usuario_token= ?",
          [$parametrosArray['tknTabAlm'], $usuario->empresa_token, $usuario->user_token]
        );
        $datalias = $JwtAuth->desencriptar($selectAlmacen[0]->alias_almacen);
        $prodDeleteList = ProductosModelo::join("detalle_almacen AS detalm", "catprod.id", "=", "detalm.producto")
          ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("personal AS pers", "empuser.personal", "=", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
          ->where([
            'catprod.token_cat_productos' => $parametrosArray['proddata'],
            'detalm.token_detalle_almacen' => $parametrosArray['tknDetAlm'],
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])
          ->limit(1)->update(
            array(
              'detalm.almacen' => $selectAlmacen[0]->id,
            )
          );

        if ($prodDeleteList) {
          return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'producto removido al almacen ' . $datalias . ' satisfactoriamente'
          ]);
        } else {
          return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => 'producto no removido al almacen ' . $datalias
          ]);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 400,
        'message' => 'datos incorrectos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $json_data = $request->input('json');
    $parametros = json_decode($json_data);
    $parametrosArray = json_decode($json_data, true);
    $arrayImpuestos = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "proddata" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Usuario incorrecto" . $validate->errors(),
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_productos = $parametrosArray["proddata"];

        $prodSelected = ProductosModelo::join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where(['in_egr_catalogo_productos.token_cat_productos' => $token_cat_productos, 'emp.empresa_token' => $usuario->empresa_token, 'users.usuario_token' => $usuario->user_token])
          ->get();

        if (count($prodSelected) == 1) {
          foreach ($prodSelected as $vPrd) {
            $obtenCompraProd = DB::select("SELECT * FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN teci_usuarios_catalogo AS users WHERE detcomp.producto = catprod.id AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$vPrd->token_cat_productos, $usuario->empresa_token, $usuario->user_token]);

            $obtenVentaProd = DB::select("SELECT * FROM ingr_ventas_detalle AS detvent JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN teci_usuarios_catalogo AS users WHERE detvent.producto = catprod.id AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$vPrd->token_cat_productos, $usuario->empresa_token, $usuario->user_token]);

            if (count($obtenCompraProd) == 0 && count($obtenVentaProd) == 0) {
              $prodDeleteList = ProductosModelo::join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
                ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
                ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
                ->where([
                  'in_egr_catalogo_productos.token_cat_productos' => $parametros->proddata,
                  'emp.empresa_token' => $usuario->empresa_token,
                  'users.usuario_token' => $usuario->user_token,
                ])
                ->limit(1)->update(array('in_egr_catalogo_productos.fecha_delete_prod' => time(), 'in_egr_catalogo_productos.status' => FALSE));
              if ($prodDeleteList) {
                return response()->json([
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'producto eliminado satisfactoriamente'
                ]);
              } else {
                return response()->json([
                  'status' => 'error',
                  'code' => 404,
                  'message' => 'producto no eliminado'
                ]);
              }
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Producto no eliminado, esta vinculado a compras o ventas realizadas, por favor verifique su información o comuniquese a soporte"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Producto no encontrado, por favor verifique su información o comuniquese a soporte"
          );
        }
      }
    } else {
      $dataMensaje = array(
        "status" => "error",
        "code" => 200,
        "message" => "La información que intenta registrar no es valida"
      );
    }
    return response()->json($dataMensaje, $dataMensaje["code"]);
  }

  public function listaegresosProductosEliminados(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $listaProductosDeleted = array();

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

        $prodList = DB::table("in_egr_catalogo_productos AS catprod")
          ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'catprod.status' => FALSE,
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();
        foreach ($prodList as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          QRCode::text($value->token_cat_productos)->setOutfile(Storage::path('public/root/' . $value->fecha_registro_prod . 'QRCode.png'))->png();

          $folio_prod = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('PROD-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_sistema) : $JwtAuth->generarFolio($value->folio_sistema) . '-' . $value->post_folio)) : 'PROD-TEMP-' . $JwtAuth->generarFolio($value->temps_folio);

          $prodGenero = DB::table("in_egr_catalogo_productos AS catprod")
            ->join("sos_ps_genero AS gen", "catprod.genero", "=", "gen.id")
            ->where(['catprod.token_cat_productos' => $value->token_cat_productos])->get();
          $genero_prod = $value->modulo_mostrador == FALSE && count($prodGenero) == 1 ? $JwtAuth->generar($prodGenero[0]->folio_genero) : "---";

          $arrayForeachVig = array(
            "token_cat_productos" => $value->token_cat_productos,
            "folio_prod" => $folio_prod,
            "clasificacion" => $JwtAuth->generar($value->clasificacion) . "-" . $genero_prod . "-" . $JwtAuth->generar($value->folio_sistema),
            "producto" => $JwtAuth->desencriptar($value->producto),
            "sat_clave_code" => $value->sat_clave_code != "" ? $value->sat_clave_code : "---",
            "unidad_medida_entrada_clave" => $value->unidad_medida_entrada_clave != "" ? $value->unidad_medida_entrada_clave : "---",
            "unidad_medida_salida_clave" => $value->unidad_medida_salida_clave != "" ? $value->unidad_medida_salida_clave : "---",
            "costo_aplicable" => "$" . ($value->costo_aplicable != "" ? number_format($value->costo_aplicable, $value->moneda_aplicable_clave_decimales, '.', ',') : "0.00"),
            "moneda_aplicable_clave" => $value->moneda_aplicable_clave != "" ? $value->moneda_aplicable_clave : "---",
            //"sat_homologado" => $value->catalogo_sat != "" ? $value->catalogo_sat : "---",
            "utilizado" => $value->utilizado == TRUE ? true : false,
            "modulo_destino" => $value->modulo_mostrador == TRUE ? "mostra_vent" : "ssic_menu_inven",
            "authorized" => $value->authorized == TRUE ? true : false, //authorized_by
            "authorized_fecha" => $value->authorized == TRUE ? date("d-m-Y H:i:s", $value->authorized_fecha) : "---",
            "fecha_delete" => date("d-m-Y H:i:s", $value->fecha_delete_prod),
          );
          $listaProductosDeleted[] = $arrayForeachVig;
        }

        $dataMensaje = array('status' => 'success', 'code' => 200, 'listado' => $listaProductosDeleted);
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

  public function restauraProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    //echo $parametros->proddata;
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $listaProductosTrue = array();
    $prodRestauraList = ProductosModelo::join("main_empresas AS emp", "in_egr_catalogo_productos.admin_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'in_egr_catalogo_productos.token_cat_productos' => $parametros->proddata,
        'emp.empresa_token' => $usuario->empresa_token,
        'users.usuario_token' => $usuario->user_token,
      ])
      ->limit(1)->update(
        array(
          'in_egr_catalogo_productos.fecha_delete_prod' => '',
          'in_egr_catalogo_productos.status' => TRUE
        )
      );
    if ($prodRestauraList) {
      return response()->json([
        'status' => 'success',
        'code' => 200,
        'message' => 'producto restaurado satisfactoriamente'
      ]);
    } else {
      return response()->json([
        'status' => 'error',
        'code' => 404,
        'message' => 'producto no restaurado'
      ]);
    }
  }

  public function deletePapProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    //echo $parametros->proddata;
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $obtenEmpresa = DB::select(
      "SELECT emp.id FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
            JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
      [$usuario->empresa_token, $usuario->user_token]
    );

    $obtenProductoCat = DB::select(
      "SELECT catprod.id FROM in_egr_catalogo_productos AS catprod 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
            WHERE catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? 
            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
      [$parametros->proddata, $usuario->empresa_token, $usuario->user_token]
    );

    $obtenCompraProd = DB::select(
      "SELECT * FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_productos AS catprod 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE detcomp.producto = catprod.id AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id 
            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
            AND users.usuario_token = ?",
      [$parametros->proddata, $usuario->empresa_token, $usuario->user_token]
    );

    $obtenVentaProd = DB::select("SELECT * FROM ingr_ventas_detalle AS detvent JOIN in_egr_catalogo_productos AS catprod 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE detvent.producto = catprod.id AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id 
            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
            AND users.usuario_token = ?", [$parametros->proddata, $usuario->empresa_token, $usuario->user_token]);

    if (count($obtenCompraProd) == 0 && count($obtenVentaProd) == 0) {
      $obtenProdClaves = DB::select("SELECT * FROM in_egr_catalogo_productos_claves WHERE productoid = ?", [$obtenProductoCat[0]->id]);
      if (count($obtenProdClaves) >= 1) {
        $deleteProdClaveProv = DB::table('in_egr_catalogo_productos_claves')
          ->where(['productoid' => $obtenProductoCat[0]->id])
          ->limit(1)->delete();
      }

      $obtenProdInsideKey = DB::select("SELECT * FROM in_egr_catalogo_productos_claves_internas WHERE producto_alta = ?", [$obtenProductoCat[0]->id]);
      if (count($obtenProdInsideKey) >= 1) {
        $deleteProdClaveProv = DB::table('in_egr_catalogo_productos_claves_internas')
          ->where(['producto_alta' => $obtenProductoCat[0]->id])
          ->limit(1)->delete();
      }

      $prodEliminaCat = ProductosModelo::where(['token_cat_productos' => $parametros->proddata])->limit(1)->delete();
      if ($prodEliminaCat) {
        return response()->json([
          'status' => 'success',
          'code' => 200,
          'message' => 'producto eliminado definitivamente'
        ]);
      } else {
        return response()->json([
          'status' => 'error',
          'code' => 404,
          'message' => 'producto no eliminado'
        ]);
      }
    } else {
      return response()->json([
        'status' => 'error',
        'code' => 404,
        'message' => 'producto no eliminado, esta vinculado a compras o ventas realizadas'
      ]);
    }
  }

  public function prodPorProveedor(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    //echo $parametros->provv;
    $arrayProductos = array();
    $prodList = ProductosModelo::join("productos", "catprod.producto", "=", "catprod.id")
      ->join("sos_ps_genero AS gen", "catprod.genero", "=", "gen.id")
      ->join("teci_catalogo_prodservsat AS pscsat", "catprod.catalogoSAT", "=", "teci_catalogo_prodservsat AS pscsat.id")
      ->join("teci_unidad_medida AS medida_entrada", "catprod.medida_entrada", "=", "medida_entrada.id")
      ->join("teci_unidad_medida AS medida_salida", "catprod.medida_salida", "=", "medida_salida.id")
      ->join("in_egr_catalogo_productos_claves AS prodclav", "catprod.id", "=", "prodclav.productoid")
      ->join("eegr_catalogo_proveedores AS catprov", "prodclav.proveedor", "=", "catprov.id")
      ->join("main_empresas AS emp", "catprod.admin_empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "=", "pers.id")
      ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
      ->where([
        'catprod.status' => TRUE,
        'catprov.status' => TRUE,
        'catprov.token_cat_proveedores' => $parametros->provv,
        'emp.empresa_token' => $usuario->empresa_token,
        'users.usuario_token' => $usuario->user_token,
      ])->get();
    //return $prodList;
    foreach ($prodList as $value) {
      if ($value->tipo_prod == 'pr') {
        $tipo_prod = 'producto';
      } else if ($value->tipo_prod == 'af') {
        $tipo_prod = 'act. fijo';
      } else {
        $tipo_prod = 'act. intangible';
      }

      $arrayForeach = array(
        "token_cat_productos" => $value->token_cat_productos,
        "imagen" => $value->imagen,
        "tipo_prod" => $tipo_prod,
        "unidad_medida" => $value->unidad_medida,
        "medida_sat" => $value->sat_clave,
        "representa" => $value->representa,
        "clave" => $value->clave,
        "descripcion" => $value->descripcion,
        "clasificacion" => $JwtAuth->generar($value->clasificacion) . '-' . $JwtAuth->generar($value->folio_genero) . '-' .
          $JwtAuth->generar($value->folio),
        "concepto" => $value->concepto,
        "producto" => $JwtAuth->desencriptar($value->producto),
        "valor_uni" => $value->costo_aplicable
      );
      $arrayProductos[] = $arrayForeach;
    }
    return response()->json([
      'arrayProductos' => $arrayProductos,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function registroProducto(Request $request){
    $JwtAuth = new \JwtAuth();
    $imgProdCaarga = $request->file('imgProdCaarga');
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        //'logoProducto' => 'string', 
        'concepto' => 'required|string',
        'familia' => 'required|string',
        'clasificacion' => 'required|string',
        'genero' => 'required|string',
        'marca' => 'string',
        'stock_min' => 'required|string',
        'stock_max' => 'required|string',
        'control_inventarios' => 'required|string',
        'costeo' => 'required|string',
        'unidad_entrada_clave' => 'string',
        'unidad_salida_clave' => 'string',
        'moneda_codigo' => 'string',
        'cuenta_contable' => 'string',
        //'uso_prod' => 'string',
        'num_serie' => 'boolean',
        'num_lote' => 'boolean',
        'pedimentoAduanal' => 'boolean',
        'nivel_alm' => 'string',
        'sat_clave_code' => 'string',
        'caracteristicas' => 'array',
        'claves_internas' => 'array',
        'proveedor' => 'array',
        'prodAnexoName' => 'array',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $fecha_sistema = time();
        $concepto = $parametrosArray["concepto"];
        $familia = $parametrosArray["familia"];
        $clasificacion = $parametrosArray["clasificacion"];
        $genero = $parametrosArray["genero"];
        $marca = $parametrosArray["marca"];
        $stock_min = $parametrosArray["stock_min"];
        $stock_max = $parametrosArray["stock_max"];
        $control_inventarios = $parametrosArray["control_inventarios"];
        $costeo = $parametrosArray["costeo"];
        $unidad_entrada_clave = $parametrosArray["unidad_entrada_clave"];
        $unidad_salida_clave = $parametrosArray["unidad_salida_clave"];
        $moneda_codigo = $parametrosArray["moneda_codigo"];
        $cuenta_contable = $parametrosArray["cuenta_contable"];
        //$uso_prod = $parametrosArray["uso_prod"];
        $num_serie = $parametrosArray["num_serie"];
        $num_lote = $parametrosArray["num_lote"];
        $pedimentoAduanal = $parametrosArray["pedimentoAduanal"];
        $nivel_alm = $parametrosArray["nivel_alm"];
        $sat_clave_code = $parametrosArray["sat_clave_code"];
        $caracteristicas = $parametrosArray["caracteristicas"];
        $claves_internas = $parametrosArray["claves_internas"];
        $proveedor = $parametrosArray["proveedor"];
        $prodAnexoName = $parametrosArray["prodAnexoName"];
        //return response()->json(["message" => $concepto,"code" => 200,"status" => "error"]);

        $validacion_concepto = isset($concepto) && !empty($concepto) && preg_match($JwtAuth->filtroAlfaNumerico(),$concepto);
        $validacion_familia = isset($familia) && !empty($familia);
        $validacion_clasificacion = isset($clasificacion) && !empty($clasificacion);
        $validacion_genero = isset($genero) && !empty($genero);
        $validacion_stock = isset($stock_min) && isset($stock_max) && ((empty($stock_min) && empty($stock_max)) || (!empty($stock_min) && preg_match($JwtAuth->filtroNumerico(),$stock_min) && $stock_min > 0 && !empty($stock_max) && preg_match($JwtAuth->filtroNumerico(),$stock_max) && $stock_max > 0 && $stock_max > $stock_min));
        $validacion_control_inventarios = isset($control_inventarios) && !empty($control_inventarios) && preg_match($JwtAuth->filtroAlfaNumerico(),$control_inventarios);
        $validacion_costeo = isset($costeo) && !empty($costeo) && preg_match($JwtAuth->filtroAlfaNumerico(),$costeo);
        $validacion_unidad_entrada_clave = isset($unidad_entrada_clave) && !empty($unidad_entrada_clave) && preg_match($JwtAuth->filtroAlfaNumerico(),$unidad_entrada_clave);
        $validacion_unidad_salida_clave = isset($unidad_salida_clave) && !empty($unidad_salida_clave) && preg_match($JwtAuth->filtroAlfaNumerico(),$unidad_salida_clave);
        $validacion_moneda = isset($moneda_codigo) && !empty($moneda_codigo) && preg_match($JwtAuth->filtroAlfaNumerico(),$moneda_codigo);
        $validacion_cuenta_contable = isset($cuenta_contable) && !empty($cuenta_contable) && preg_match($JwtAuth->filtroAlfaNumerico(),$cuenta_contable);
        //$validacion_uso_prod = isset($uso_prod) && !empty($uso_prod) && preg_match($JwtAuth->filtroAlfaNumerico(),$uso_prod);
        $validacion_num_serie = isset($num_serie) && is_bool($num_serie);
        $validacion_num_lote = isset($num_lote) && is_bool($num_lote);
        $validacion_pedimentoAduanal = isset($pedimentoAduanal) && is_bool($pedimentoAduanal);
        $validacion_nivel_alm = isset($nivel_alm) && !empty($nivel_alm) && preg_match($JwtAuth->filtroAlfaNumerico(),$nivel_alm);

        if (
          $validacion_concepto && $validacion_familia && $validacion_clasificacion && $validacion_genero && $validacion_stock && $validacion_control_inventarios && $validacion_costeo && $validacion_unidad_entrada_clave && $validacion_unidad_salida_clave &&
          $validacion_moneda/*&& $validacion_uso_prod*/ && $validacion_num_serie && $validacion_num_lote && $validacion_pedimentoAduanal
        ) {
          $queryEmp = DB::select("SELECT emp.id,emp.root_tkn,users.id AS userr,emp.zona_horaria,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                    WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);
          foreach ($queryEmp as $vEmp) {
            //da_te_default_timezone_set($vEmp->zona_horaria);

            $autorizado = FALSE;
            $autorizacion_fecha = NULL;
            $autorizacion_user = NULL;
            $folio_nuevo = NULL;
            $post_folio =  NULL;
            $folio_temporal = NULL;
            $folio_prod = NULL;

            if ($vEmp->jerarquia_main == 'P') {
              $folioSistema = DB::select(
                "SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                            WHERE fold.egr_productos = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                [$usuario->empresa_token, $usuario->user_token]
              );

              $post_folio_db = DB::select(
                "SELECT post_folio FROM in_egr_catalogo_productos WHERE id = (SELECT Max(catprod.id) FROM in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                            JOIN teci_usuarios_catalogo AS users WHERE catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",
                [$usuario->empresa_token, $usuario->user_token]
              );

              $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
              $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
              $folio_prod = $post_folio == NULL ? 'PROD-' . $JwtAuth->generarFolio($folio_nuevo) : 'PROD-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
              $autorizado = TRUE;
              $autorizacion_fecha = time();
              $autorizacion_user = $vEmp->userr;
            } else {
              $folioSistemaTemp = DB::select("SELECT temps_folio FROM in_egr_catalogo_productos WHERE temps_folio IS NOT NULL AND admin_empresa = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$usuario->empresa_token]);
              if (count($folioSistemaTemp) > 0) {
                $queryFolioTmpPrd = DB::select("SELECT temps_folio+1 AS temps_folio FROM in_egr_catalogo_productos 
                                    WHERE id = (SELECT Max(catprod.id) FROM in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp WHERE catprod.temps_folio IS NOT NULL AND catprod.admin_empresa = emp.id 
                                    AND emp.empresa_token = ?)", [$usuario->empresa_token]);

                foreach ($queryFolioTmpPrd as $vTemp) {
                  $folio_temporal = $vTemp->temps_folio;
                }
              } else {
                $folio_temporal = 1;
              }

              $folio_prod = 'PROD-TEMP-' . $JwtAuth->generarFolio($folio_temporal);
              $autorizado = FALSE;
            }

            //$prod_uso = isset($uso_prod) && is_bool($uso_prod) && $uso_prod == true ? TRUE : FALSE;
            $data_concepto = $JwtAuth->encriptar(strtolower($concepto));
            $serie_num = $validacion_num_serie && $num_serie == true ? TRUE : FALSE;
            $lote_num = $validacion_num_lote && $num_lote == true ? TRUE : FALSE;
            $aduan_pedim = $validacion_pedimentoAduanal && $pedimentoAduanal == true ? TRUE : FALSE;
            $almacen_nivel = $validacion_nivel_alm ? $nivel_alm : NULL; 
            $clasifProd = DB::table("sos_ps_clasificacion")->where("token_clasificacion", $clasificacion)->value("id");
            $genroProd = DB::table("sos_ps_genero")->where("token_genero", $genero)->value("id");
            $data_sat_code = isset($sat_clave_code) && !empty($sat_clave_code) ? $sat_clave_code : NULL;
            $data_marca = isset($marca) && !empty($marca) ? $JwtAuth->encriptar(strtolower($marca)) : NULL;

            $moneda_decimales = "";

            $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaMonedas');
            if ($response->successful()) {
              $datos = $response->json();
              $cantidadRegistros = is_array($datos) ? count($datos) : 0;
              $indice = array_search($moneda_codigo, array_column($datos["monedas"], "code"));
              $moneda_decimales = $datos["monedas"][$indice]["decimales"];
              //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
            }

            $ubicaProducto = DB::select(
              "SELECT catprod.id FROM in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                        WHERE catprod.producto = ? AND catprod.admin_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
              [$data_concepto, $usuario->empresa_token, $usuario->user_token]
            );
            //return response()->json(["message" => " clase ".$vEmp->jerarquia_main,"code" => 200,"status" => "error"]);
            if (count($ubicaProducto) == 0) {
              $tokenCatProd = $JwtAuth->encriptarToken($fecha_sistema, $clasificacion, $data_sat_code, $costeo, $concepto, $data_marca);
              //return response()->json(["message" => " class ".$uso_prod,"code" => 200,"status" => "error"]);
              $newProd = new ProductosModelo();
              $newProd->fecha_registro_prod = $fecha_sistema;
              $newProd->token_cat_productos = $tokenCatProd;
              $newProd->folio_sistema = $folio_nuevo;
              $newProd->post_folio = $post_folio;
              $newProd->temps_folio = $folio_temporal;
              $newProd->authorized = $autorizado;
              $newProd->authorized_fecha = $autorizacion_fecha;
              $newProd->authorized_by = $autorizacion_user;
              $newProd->producto = $data_concepto;
              $newProd->familia = $familia;
              $newProd->clasificacion = $clasifProd;
              $newProd->genero = $genroProd;
              $newProd->marca = $data_marca;
              $newProd->stock_min = $stock_min;
              $newProd->stock_max = $stock_max;
              $newProd->control_inventarios = $control_inventarios;
              $newProd->costeo = $costeo;
              $newProd->unidad_medida_entrada_clave = $unidad_entrada_clave;
              $newProd->unidad_medida_salida_clave = $unidad_salida_clave;
              //moneda
              $newProd->moneda_aplicable_clave = $moneda_codigo;
              $newProd->moneda_aplicable_clave_decimales = $moneda_decimales;

              $newProd->num_serie = $serie_num;
              $newProd->num_lote = $lote_num;
              $newProd->importado = $aduan_pedim;
              $newProd->almacen_nivel = $almacen_nivel;
              $newProd->sat_clave_code = $data_sat_code;
              $newProd->cuenta_contable = $validacion_cuenta_contable ? $JwtAuth->encriptar($cuenta_contable) : NULL;
              //$newProd->uso_producto = $uso_prod;
              //$newProd->costo_aplicable = ''; 
              //$newProd->proceso = FALSE;
              //$newProd->tipo_prod = 'pr';
              //$newProd->activo = NULL;
              //$newProd->impuestos = '';
              //$newProd->fecha_delete_prod = '';
              //$newProd->utilizado = FALSE;
              $newProd->status = TRUE;
              $newProd->admin_empresa = $vEmp->id;
              $newProd->admin_user_registra = $vEmp->userr;
              $savednewProd = $newProd->save();
              if ($savednewProd) {
                $obtenProducto = $newProd->id;

                if (count($caracteristicas) > 0) {
                  for ($i = 0; $i < count($caracteristicas); $i++) {
                    $clave_caract = $caracteristicas[$i]['clave_caract'];
                    $valor_caract = $caracteristicas[$i]['valor_caract'];
                    $tokenClabeProdProv = $JwtAuth->encriptarToken(time(), $clave_caract, $valor_caract);
                    $insertProd = DB::table('eegr_catalogo_productos_caracteristicas')
                      ->insert(array(
                        "token_caracteristicas" => $tokenClabeProdProv,
                        "clave_caract" => $clave_caract,
                        "valor_caract" => $valor_caract,
                        "producto" => $obtenProducto,
                      ));
                  }
                }

                if (count($claves_internas) > 0) {
                  for ($i = 0; $i < count($claves_internas); $i++) {
                    $clave_name = $claves_internas[$i]['clave_name'];
                    $valor_name = $claves_internas[$i]['valor_name'];
                    $tokenClabeInside = $JwtAuth->encriptarToken(time(), $clave_name, $valor_name);
                    $insertProd = DB::table('in_egr_catalogo_productos_claves_internas')
                      ->insert(array(
                        "token_alta_clave" => $tokenClabeInside,
                        "producto_alta" => $obtenProducto,
                        "clave_nombre" => $clave_name,
                        "clave_valor" => $valor_name,
                      ));
                  }
                }

                if (count($proveedor) > 0) {
                  for ($i = 0; $i < count($proveedor); $i++) {
                    $prv_token = $proveedor[$i]['token_cat_proveedores'];
                    $prv_tiene_clave = $proveedor[$i]['tiene_clave'] == "true" ? TRUE : FALSE;
                    $prv_clave = $proveedor[$i]['tiene_clave'] == "true" ? $proveedor[$i]['clave'] : NULL;
                    $obtenProv = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $prv_token)->value("id");
                    $tokenClabeProdProv = $JwtAuth->encriptarToken($fecha_sistema . $prv_clave . $obtenProv . $usuario->empresa_token . $usuario->user_token);
                    $insertProd = DB::table('in_egr_catalogo_productos_claves')
                      ->insert(array(
                        "token_producto_claves" => $tokenClabeProdProv,
                        "productoid" => $obtenProducto,
                        "proveedor" => $obtenProv,
                        "cliente" => NULL,
                        "tiene_clave" => $prv_tiene_clave,
                        "identificador" => $prv_clave,
                      ));
                  }
                }

                if(!empty($_FILES['docsProdAnexos']['name'][0])) {
                  $filepath = $vEmp->root_tkn."/0002-cpp/catalogos/productos/anexos-".$fecha_sistema."/";
                  if (!file_exists(storage_path("/root/".$filepath))){
                    Storage::disk('root')->makeDirectory($filepath,0777, true, true);
                  }
                  foreach ($_FILES['docsProdAnexos']['name'] as $index => $name) {
                    // Obtener información del archivo
                    $type = $JwtAuth->getExtensionDoc($_FILES['docsProdAnexos']['type'][$index]);
                    $tmpName = $_FILES['docsProdAnexos']['tmp_name'][$index];
                    $error = $_FILES['docsProdAnexos']['error'][$index];
                    $size = $_FILES['docsProdAnexos']['size'][$index];
                    
                    // Verificar si hubo errores al subir el archivo
                    if ($error === UPLOAD_ERR_OK) {
                      // Leer el contenido del archivo
                      $content = file_get_contents($tmpName);
                      
                      // Preparar la sentencia SQL para insertar los datos
                      $select_folio_doc = DB::select("SELECT COUNT(id)+1 AS folio FROM sos_documentos WHERE folio_modulo LIKE '%PROD-EVID%'");
                      $tkn_evidencia = $JwtAuth->encriptarToken($obtenProducto,$usuario->user_token,$usuario->empresa_token,$name);
                      $insertEvidenceInf = DB::table('sos_documentos')->insert(
                      array(
                        "token_documento" => $tkn_evidencia,
                        "fecha_carga" => time(),
                        "modulo" => "proyectos",
                        "folio_modulo" => "PROD-EVID".$select_folio_doc[0]->folio,
                        "tipo_documento" => "file",
                        "nombre_documento" => $JwtAuth->encriptar($name),
                        "extension_documento" => $type,
                        "productos" => $obtenProducto,
                        "status_documento" => TRUE,	
                        "fecha_delete_documento" => NULL,
                        ) 
                      );
                      Storage::putFileAs("/public/root/".$filepath,$tmpName,$name);
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 404,
                        'message' => "Error al subir el archivo '$name'. Código de error: $error"
                      );
                    }
                  }
                }

                //return response()->json(["message" => " registred ".$uso_prod,"code" => 200,"status" => "error"]);
                if ($autorizado == TRUE) {
                  if (count($folioSistema) == 0) {
                    $insertSistema = DB::table('sos_last_folders')
                      ->insert(
                        array(
                          "egr_productos" => TRUE,
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
                        'sos_last_folders.egr_productos' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                      ])
                      ->limit(1)->update(
                        array(
                          'sos_last_folders.folder' => $folio_nuevo,
                          'sos_last_folders.post_folder' => $post_folio,
                        )
                      );
                  }
                }

                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'Este producto ha sido registrado satisfactoriamente con el folio ' . $folio_prod
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 404,
                  'message' => 'Producto no registrado, intente nuevamente o comuniquese a soporte'
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Este producto ya ha sido registrado anteriormente, intente nuevamente o comuniquese a soporte'
              );
            }
          }
        } else {
          $error_alerta = "";
          if (!$validacion_concepto) {
            $error_alerta = "Error al ingresar concepto del producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_familia) {
            $error_alerta = "Error al seleccionar familia del producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_clasificacion) {
            $error_alerta = "Error al seleccionar clasificación de producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_genero) {
            $error_alerta = "Error al seleccionar genero de producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_stock) {
            $error_alerta = "Error al ingresar stock mínimo / máximo, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_control_inventarios) {
            $error_alerta = "Error al seleccionar control de inventarios del producto, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_costeo) {
            $error_alerta = "Error al seleccionar método de costeo, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_unidad_entrada_clave) {
            $error_alerta = "Error al seleccionar unidad de medida de entrada, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_unidad_salida_clave) {
            $error_alerta = "Error al seleccionar unidad de medida de salida, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_moneda) {
            $error_alerta = "Error al seleccionar moneda, verifique su información o comuniquese a soporte";
          }
          /*if (! && $validacion_control_inventarios $validacion_uso_prod) {
            $error_alerta = "Error al seleccionar uso del producto, verifique su información o comuniquese a soporte";
          }*/
          if (!$validacion_num_serie) {
            $error_alerta = "Error al seleccionar si el producto debe contener número de serie, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_num_lote) {
            $error_alerta = "Error al seleccionar si el producto debe contener número de lote, verifique su información o comuniquese a soporte";
          }
          if (!$validacion_pedimentoAduanal) {
            $error_alerta = "Error al seleccionar si el producto debe contener número de pedimento aduanal, verifique su información o comuniquese a soporte";
          }
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $error_alerta);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registroProductoMostrador(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'concepto' => 'required|string',
        'precio' => 'required|numeric',
        'unidad_salida_clave' => 'required|string',
        'unidad_salida_homologada' => 'string',
        'moneda_codigo' => 'required|string',
        'moneda_homologada' => 'string',
        'claves_internas' => 'array',
        'impuestos' => 'array',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $fecha_sistema = time();
        $concepto = $parametrosArray["concepto"];
        $precio = $parametrosArray["precio"];
        $unidad_salida_clave = $parametrosArray["unidad_salida_clave"];
        $unidad_salida_homologada = $parametrosArray["unidad_salida_homologada"];
        $moneda_codigo = $parametrosArray["moneda_codigo"];
        $moneda_homologada = $parametrosArray["moneda_homologada"];
        $claves_internas = $parametrosArray["claves_internas"];
        $impuestos = $parametrosArray["impuestos"];

        if (
          isset($concepto) && !empty($concepto) && isset($precio) && !empty($precio) && isset($unidad_salida_clave) && !empty($unidad_salida_clave) &&
          isset($moneda_codigo) && !empty($moneda_codigo)
        ) {
          //return response()->json(["message" => "prueba25","code" => 200,"status" => "error"]);
          $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,users.id AS userr,emp.zona_horaria,people.paterno,people.materno,people.nombre,
                        people.denominacion_rs,people.sitio_web FROM main_empresas AS emp JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.persona = people.id AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);
          //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
          //echo $selectEmp[0]->id;

          $folioSistemaTemp = DB::select("SELECT temps_folio FROM in_egr_catalogo_productos WHERE temps_folio IS NOT NULL AND admin_empresa = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$usuario->empresa_token]);
          if (count($folioSistemaTemp) > 0) {
            $queryFolioTmpPrv = DB::select("SELECT temps_folio+1 AS temps_folio FROM in_egr_catalogo_productos 
                            WHERE id = (SELECT Max(catprod.id) FROM in_egr_catalogo_productos AS catprod 
                            JOIN main_empresas AS emp WHERE temps_folio IS NOT NULL AND catprod.admin_empresa = emp.id 
                            AND emp.empresa_token = ?)", [$usuario->empresa_token]);

            foreach ($queryFolioTmpPrv as $vTemp) {
              $folio_temporal = $vTemp->temps_folio;
            }
          } else {
            $folio_temporal = 1;
          }

          $folio_prod_temp = 'PROD-TEMP-' . $JwtAuth->generarFolio($folio_temporal);

          $conceptoProd = $JwtAuth->encriptar(strtolower($concepto));
          //$unidadMSalidaDB = DB::select("SELECT id FROM teci_unidad_medida WHERE token_unidad_medida = ?",[$unidad_medida]);
          //$monedaSalidaDB = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_token]);
          $ubicaProducto = DB::select(
            "SELECT catprod.id FROM in_egr_catalogo_productos AS catprod
                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                        WHERE catprod.producto = ? AND catprod.admin_empresa = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$conceptoProd, $usuario->empresa_token, $usuario->user_token]
          );
          if (count($ubicaProducto) == 0) {
            $tokenCatProd = $JwtAuth->encriptarToken($conceptoProd . $precio . $unidad_salida_clave . $moneda_codigo);
            $newProd = new ProductosModelo();
            $newProd->fecha_registro_prod = $fecha_sistema;
            $newProd->token_cat_productos = $tokenCatProd;
            $newProd->temps_folio = $folio_temporal;
            $newProd->authorized = FALSE;
            $newProd->modulo_mostrador = TRUE;
            $newProd->producto = $conceptoProd;
            $newProd->unidad_medida_salida_clave = $unidad_salida_clave;
            $newProd->costo_aplicable = $precio;
            $newProd->moneda_aplicable_clave = $moneda_codigo;
            $newProd->tipo_prod = 'pr';
            $newProd->activo = NULL;
            $newProd->proceso = FALSE;
            $newProd->utilizado = FALSE;
            $newProd->fecha_delete_prod = '';
            $newProd->status = TRUE;
            $newProd->admin_empresa = $selectEmp[0]->id;
            $newProd->admin_user_registra = $selectEmp[0]->userr;
            $savednewProd = $newProd->save();

            if ($savednewProd) {
              $obtenProducto = DB::select("SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?", [$tokenCatProd]);
              if (count($claves_internas) > 0) {
                for ($i = 0; $i < count($claves_internas); $i++) {
                  $clave_name = $claves_internas[$i]['clave_name'];
                  $valor_name = $claves_internas[$i]['valor_name'];
                  $tokenClabeProdProv = $JwtAuth->encriptarToken(time(), $clave_name, $valor_name);
                  $insertProd = DB::table('in_egr_catalogo_productos_claves_internas')
                    ->insert(array(
                      "token_alta_clave" => $tokenClabeProdProv,
                      "producto_alta" => $obtenProducto[0]->id,
                      "clave_nombre" => $clave_name,
                      "clave_valor" => $valor_name,
                    ));
                }
              }
              if (count($impuestos) > 0) {
                for ($i = 0; $i < count($impuestos); $i++) {
                  $impuesto_vinculado = DB::select("SELECT id FROM cont_impuestos_catalogo WHERE token_cat_impuestos = ?", [$impuestos[$i]['impuesto_vinculado']]);
                  $tokenImpArt = $JwtAuth->encriptarToken(time(), $obtenProducto[0]->id, $impuesto_vinculado[0]->id);
                  $insertImpArt = DB::table('in_egr_impuestos_articulos')
                    ->insert(array(
                      "token_impuestos_articulos" => $tokenImpArt,
                      "producto_rel" => $obtenProducto[0]->id,
                      "impuestos" => $impuesto_vinculado[0]->id,
                    ));
                }
              }
              $JwtAuth->insertBitacoraActividad('egresos', 'catalogos', 'productos', $folio_prod_temp, 'registro en el catalogo de productos', $usuario->empresa_token, $usuario->user_token);

              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Este producto ha sido registrado satisfactoriamente con el folio ' . $folio_prod_temp
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'La información de este producto no es valida'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Este producto ya ha sido registrado anteriormente, intente nuevamente o comuniquese a soporte'
            );
          }
        } else {
          $error_alerta = "";
          if (!isset($concepto) || empty($concepto)) {
            $error_alerta = "error al ingresar concepto del producto, verifique su información o comuniquese a soporte para más información";
          }
          if (!isset($precio) || empty($precio)) {
            $error_alerta = "error al ingresar precio de producto, verifique su información o comuniquese a soporte para más información";
          }
          if (!isset($unidad_medida) || empty($unidad_medida)) {
            $error_alerta = "error al ingresar unidad de medida, verifique su información o comuniquese a soporte para más información";
          }
          $dataMensaje = array(
            'status' => 'error',
            'code' => 404,
            'message' => $error_alerta
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los informacion que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
