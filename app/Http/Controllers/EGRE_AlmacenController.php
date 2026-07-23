<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\AlmacenModelo;
use App\Models\PersonalModelo;

class EGRE_AlmacenController extends Controller{
  public function totalAlmacenes(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $listaDirAlmacen = AlmacenModelo::join("main_empresas AS emp", "in_egr_establecimientos_catalogo.empresa", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->where([
      'in_egr_establecimientos_catalogo.habilitado' => TRUE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])->get();

    $dataMensaje = array(
      'status' => 'success',
      'code' => 200,
      'total_establecimientos' => count($listaDirAlmacen),
    );

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function establecimientosCatalogo(Request $request){
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
          $fechaInicio = date('Y-m-d 00:00:00');
          $fechaFin = date('Y-m-d 23:59:59');
          break;
        case 'esta_semana':
          $fechaInicio = date('Y-m-d 00:00:00', strtotime('monday this week'));
          $fechaFin = date('Y-m-d 23:59:59');
          break;
        case 'este_mes':
          $fechaInicio = date('Y-m-01 00:00:00');
          $fechaFin = date('Y-m-t 23:59:59');
          break;
        case 'mes_anterior':
          $fechaInicio = date('Y-m-d 00:00:00', strtotime("first day of last month"));
          $fechaFin = date('Y-m-d 23:59:59', strtotime("last day of last month"));
          break;
        case 'otras_fechas':
          $periodo_inicio = $request->input('periodo_inicio');
          $periodo_fin = $request->input('periodo_fin');
          $fechaInicio = $periodo_inicio . " 00:00:00";
          $fechaFin = $periodo_fin . " 23:59:59";
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
      
      $queryEstabs = AlmacenModelo::join("main_empresas AS emp", "in_egr_establecimientos_catalogo.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        //'dirAlm.tipo_direccion' => 'almacen',
        'in_egr_establecimientos_catalogo.status_establecimiento' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("in_egr_establecimientos_catalogo.created_at", [$fechaInicio, $fechaFin]);
      })
      ->get();
      
      if ($queryEstabs->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron establecimientos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $direccionAlmacen = array();
        foreach ($queryEstabs as $vEstab) {
          $arrayDirAlmExt = array(
            "token_establecimiento" => $vEstab->token_establecimiento,
            "estab_folio" => 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : ''),
            "estab_alias" => $JwtAuth->desencriptar($vEstab->alias_establecimiento),
            "estab_tipo" => !empty($vEstab->tipo_establecimiento) ? $JwtAuth->desencriptar($vEstab->tipo_establecimiento) : '',
            "estab_desc" => !empty($vEstab->descripcion_establecimiento) ? $JwtAuth->desencriptar($vEstab->descripcion_establecimiento) : '',
            "aplica_almacen" => $vEstab->aplica_almacen ? 'yes' : 'no',
            "aplica_ingresos" => $vEstab->ingresos ? 'yes' : 'no',
            "aplica_egresos" => $vEstab->egresos ? 'yes' : 'no',
            "aplica_interno" => $vEstab->interno ? 'yes' : 'no',
            "select_for_centrotrab" => false,
            "estab_detalle" => [],
          );
          $direccionAlmacen[] = $arrayDirAlmExt;
        }
  
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'establecimientos' => $direccionAlmacen,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function establecimientosCatalogoNoCentrosTrabajo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryEstabs = AlmacenModelo::join("main_empresas AS emp", "in_egr_establecimientos_catalogo.empresa", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->whereNotIn('in_egr_establecimientos_catalogo.id', function($queryCFDI) {
      $queryCFDI->select('centrotrab_ubicacion')->from('vhum_centros_de_trabajo_catalogo');
    })
    ->where([
      //'dirAlm.tipo_direccion' => 'almacen',
      'in_egr_establecimientos_catalogo.status_establecimiento' => TRUE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->get();

    if ($queryEstabs->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron establecimientos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $direccionAlmacen = array();
      foreach ($queryEstabs as $vEstab) {
        $arrayDirAlmExt = array(
          "token_establecimiento" => $vEstab->token_establecimiento,
          "estab_folio" => 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : ''),
          "estab_alias" => $JwtAuth->desencriptar($vEstab->alias_establecimiento),
          "estab_tipo" => !empty($vEstab->tipo_establecimiento) ? $JwtAuth->desencriptar($vEstab->tipo_establecimiento) : '',
          "estab_desc" => !empty($vEstab->descripcion_establecimiento) ? $JwtAuth->desencriptar($vEstab->descripcion_establecimiento) : '',
          "aplica_almacen" => $vEstab->aplica_almacen ? 'yes' : 'no',
          "aplica_ingresos" => $vEstab->ingresos ? 'yes' : 'no',
          "aplica_egresos" => $vEstab->egresos ? 'yes' : 'no',
          "aplica_interno" => $vEstab->interno ? 'yes' : 'no',
          "select_for_centrotrab" => false,
          "estab_detalle" => [],
        );
        $direccionAlmacen[] = $arrayDirAlmExt;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'establecimientos' => $direccionAlmacen,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function direccionAlmacenComplete(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $listaDirAlmacen = AlmacenModelo::join('teci_direcciones AS dirAlm', 'in_egr_establecimientos_catalogo.id', 'dirAlm.establecimiento')
    ->join('teci_pais AS detpais', 'dirAlm.pais', 'detpais.id')
    ->join("main_empresas AS emp", "in_egr_establecimientos_catalogo.empresa", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->where([
      "in_egr_establecimientos_catalogo.status_establecimiento" => TRUE,
      "emp.empresa_token" => $empresa,
      "users.usuario_token" => $usuario
    ])->get();

    if ($listaDirAlmacen->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron establecimientos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaEstablecimientos = array();      
      foreach ($listaDirAlmacen as $vEstab) {
        $arrayDirAlm = array(
          "token_establecimiento" => $vEstab->token_establecimiento,
          "estab_folio" => 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : ''),
          "estab_alias" => $JwtAuth->desencriptar($vEstab->alias_establecimiento),
          "estab_tipo" => !empty($vEstab->tipo_establecimiento) ? $JwtAuth->desencriptar($vEstab->tipo_establecimiento) : '',
          "estab_desc" => !empty($vEstab->descripcion_establecimiento) ? $JwtAuth->desencriptar($vEstab->descripcion_establecimiento) : '',
          "aplica_ingresos" => $vEstab->ingresos ? true : false,
          "aplica_egresos" => $vEstab->egresos ? true : false,
          "aplica_interno" => $vEstab->interno ? true : false,
          "aplica_almacen" => $vEstab->aplica_almacen ? true : false,
          "telefono" => !empty($vEstab->telefono) ? "+".$JwtAuth->desencriptar($vEstab->telefono) : '',
          "estab_ubicacion_pais" => $vEstab->pais_code == "MEX" ? "Mx" : "Ext",
          "token_direccion" => $vEstab->token_direccion,
          "tipo_direccion" => $vEstab->tipo_direccion,
          "clase" => $vEstab->clase,
        );

        if ($vEstab->pais_code == "MEX") {
          $arrayDirAlm['pais_code'] = "MEX";
          $arrayDirAlm['pais_nombre'] = "Mexico/Mèxico";
          $arrayDirAlm['estado_main'] = $JwtAuth->desencriptar($vEstab->estado_edit);
          $arrayDirAlm['estado_edit'] = $JwtAuth->desencriptar($vEstab->estado_edit);
          $arrayDirAlm['municipio_main'] = $JwtAuth->desencriptar($vEstab->municipio_edit);
          $arrayDirAlm['municipio_edit'] = $JwtAuth->desencriptar($vEstab->municipio_edit);
          $arrayDirAlm['c_postal_main'] = $vEstab->c_postal_edit;
          $arrayDirAlm['c_postal_edit'] = $vEstab->c_postal_edit;
          $arrayDirAlm['colonia_main'] = $JwtAuth->desencriptar($vEstab->colonia_edit);
          $arrayDirAlm['colonia_edit'] = $JwtAuth->desencriptar($vEstab->colonia_edit);
          $arrayDirAlm['adicional'] = $vEstab->adicional;
        } else {
          $pais_en = "";
          $pais_es = "";
          $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaPaises');
          if ($response->successful()) {
            $datos = $response->json();
            $cantidadRegistros = is_array($datos) ? count($datos) : 0;
            $indice = array_search($vProd->e_moneda_code, array_column($datos["paises"], "code"));
            $pais_en = $datos["paises"][$indice]["langEN"];
            $pais_es = $datos["paises"][$indice]["langES"];
            //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
          }

          $arrayDirAlm['pais_code'] = $vEstab->pais_code;
          $arrayDirAlm['pais_nombre'] = "$pais_en/$pais_es";
          $arrayDirAlm['cod_postalext'] = $JwtAuth->desencriptar($vEstab->cod_postalext);
          $arrayDirAlm['dir_completa'] = $JwtAuth->desencriptar($vEstab->calle);
        }
        $listaEstablecimientos[] = $arrayDirAlm;
      }
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'listaEstablecimientos' => $listaEstablecimientos,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleEstablecimiento(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'tokenEstablecimiento' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $tokenEstablecimiento = $request->input('tokenEstablecimiento');
      
      $listaDirAlmacen = AlmacenModelo::join('teci_direcciones AS dirAlm', 'in_egr_establecimientos_catalogo.id', 'dirAlm.establecimiento')
      ->join("main_empresas AS emp", "in_egr_establecimientos_catalogo.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'in_egr_establecimientos_catalogo.token_establecimiento' => $tokenEstablecimiento,
        "in_egr_establecimientos_catalogo.status_establecimiento" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])->get();
      //echo count($listaDirAlmacen);

      if ($listaDirAlmacen->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron establecimientos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayAlmacen = array();
        foreach ($listaDirAlmacen as $vEstab) {
          $arrayDirAlm = array(
            "token_establecimiento" => $vEstab->token_establecimiento,
						"estab_folio" => 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : ''),
						"estab_alias" => $JwtAuth->desencriptar($vEstab->alias_establecimiento),
						"estab_tipo" => !is_null($vEstab->tipo_establecimiento) && $vEstab->tipo_establecimiento != '' ? $JwtAuth->desencriptar($vEstab->tipo_establecimiento) : '',
						"estab_desc" => !empty($vEstab->descripcion_establecimiento) ? $JwtAuth->desencriptar($vEstab->descripcion_establecimiento) : '',
						"aplica_almacen" => $vEstab->aplica_almacen ? true : false,
						"aplica_ingresos" => $vEstab->ingresos ? true : false,
						"aplica_egresos" => $vEstab->egresos ? true : false,
						"aplica_interno" => $vEstab->interno ? true : false,
						"telefono" => $JwtAuth->desencriptar($vEstab->telefono),
						"cuenta_contable" => $vEstab->cuenta_contable,
						"estab_ubicacion_pais" => $vEstab->pais_code,
						"token_direccion" => $vEstab->token_direccion,
						"tipo_direccion" => $vEstab->tipo_direccion,
						"clase" => $vEstab->clase,
          );

					if ($vEstab->pais_code == "MEX") {
						$arrayDirAlm['pais_code'] = "MEX";
						$arrayDirAlm['pais_nombre'] = "Mexico/Mèxico";
						$arrayDirAlm['estado_main'] = $JwtAuth->desencriptar($vEstab->estado_edit);
						$arrayDirAlm['estado_edit'] = $JwtAuth->desencriptar($vEstab->estado_edit);
						$arrayDirAlm['municipio_main'] = $JwtAuth->desencriptar($vEstab->municipio_edit);
						$arrayDirAlm['municipio_edit'] = $JwtAuth->desencriptar($vEstab->municipio_edit);
						$arrayDirAlm['c_postal_main'] = $vEstab->c_postal_edit;
						$arrayDirAlm['c_postal_edit'] = $vEstab->c_postal_edit;
						$arrayDirAlm['colonia_main'] = $JwtAuth->desencriptar($vEstab->colonia_edit);
						$arrayDirAlm['colonia_edit'] = $JwtAuth->desencriptar($vEstab->colonia_edit);
						$arrayDirAlm['adicional'] = $vEstab->adicional;
					} else {
						$arrayDirAlm['pais_code'] = $vEstab->pais_code;
						$arrayDirAlm['cod_postalext'] = $JwtAuth->desencriptar($vEstab->cod_postalext);
					}
          $arrayAlmacen[] = $arrayDirAlm;
        }
        
        $dataMensaje = array(
          'arrayAlmacen' => $arrayAlmacen,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateEstablecimiento(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'establecimiento_token' => 'required|string',
      'establecimiento_alias' => 'required|string',
      'establecimiento_tipo' => 'required|string',
      'establecimiento_descripcion' => 'required|string',
      'establecimiento_aplica_ingresos' => 'required|boolean',
      'establecimiento_aplica_egresos' => 'required|boolean',
      'establecimiento_aplica_procesos_internos' => 'required|boolean',
      'establecimiento_aplica_almacen' => 'required|boolean',
      'establecimiento_ubicacion_pais' => 'required|string',
      'establecimiento_dipomex_cod_postal_estado' => 'nullable|string',
      'establecimiento_dipomex_cod_postal_municipio' => 'nullable|string',
      'establecimiento_dipomex_cod_postal_cp' => 'nullable|string',
      'establecimiento_dipomex_cod_postal_colonia_vinculada' => 'nullable|string',
      'establecimiento_ext_direccion_completa' => 'nullable|string',
      'establecimiento_phoneAll' => 'required|string',
      'establecimiento_cuenta_contable' => 'nullable|string',
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
      $establecimiento_token = $request->input('establecimiento_token');
      $establecimiento_alias = $request->input('establecimiento_alias');
      $establecimiento_tipo = $request->input('establecimiento_tipo');
      $establecimiento_descripcion = $request->input('establecimiento_descripcion');
      $establecimiento_aplica_ingresos = $request->input('establecimiento_aplica_ingresos');
      $establecimiento_aplica_egresos = $request->input('establecimiento_aplica_egresos');
      $establecimiento_aplica_procesos_internos = $request->input('establecimiento_aplica_procesos_internos');
      $establecimiento_aplica_almacen = $request->input('establecimiento_aplica_almacen');
      $establecimiento_ubicacion_pais = $request->input('establecimiento_ubicacion_pais');
      $establecimiento_estado = $request->input('establecimiento_dipomex_cod_postal_estado');
      $establecimiento_municipio = $request->input('establecimiento_dipomex_cod_postal_municipio');
      $establecimiento_cp = $request->input('establecimiento_dipomex_cod_postal_cp');
      $establecimiento_colonia = $request->input('establecimiento_dipomex_cod_postal_colonia_vinculada');
      $establecimiento_ext_direccion_completa = $request->input('establecimiento_ext_direccion_completa');
      $establecimiento_phoneAll = $request->input('establecimiento_phoneAll');
      $establecimiento_cuenta_contable = $request->input('establecimiento_cuenta_contable');

      $valida_estab_alias = isset($establecimiento_alias) && !empty($establecimiento_alias) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_alias);
      $valida_estab_tipo = isset($establecimiento_tipo) && !empty($establecimiento_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_tipo); 
      $valida_estab_descripcion = isset($establecimiento_descripcion) && !empty($establecimiento_descripcion) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_descripcion); 
      $valida_estab_aplica_ingresos = isset($establecimiento_aplica_ingresos) && is_bool($establecimiento_aplica_ingresos);
      $valida_estab_aplica_egresos = isset($establecimiento_aplica_egresos) && is_bool($establecimiento_aplica_egresos);
      $valida_estab_aplica_procesos_internos = isset($establecimiento_aplica_procesos_internos) && is_bool($establecimiento_aplica_procesos_internos);
      $valida_estab_aplica_almacen = isset($establecimiento_aplica_almacen) && is_bool($establecimiento_aplica_almacen);
      
      $valida_estab_ubicacion_pais = isset($establecimiento_ubicacion_pais) && !empty($establecimiento_ubicacion_pais) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_ubicacion_pais); 
      $valida_estab_estado = isset($establecimiento_estado) && !empty($establecimiento_estado) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_estado);
      $valida_estab_municipio = isset($establecimiento_municipio) && !empty($establecimiento_municipio) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_municipio);
      $valida_estab_cp = isset($establecimiento_cp) && !empty($establecimiento_cp) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_cp);
      $valida_estab_colonia = isset($establecimiento_colonia) && !empty($establecimiento_colonia) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_colonia);
      $valida_ubi_mx = $establecimiento_ubicacion_pais == "MEX" && $valida_estab_estado && $valida_estab_municipio && $valida_estab_cp && $valida_estab_colonia;
      
      $valida_estab_ext_direccion_completa = isset($establecimiento_ext_direccion_completa) && !empty($establecimiento_ext_direccion_completa) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_ext_direccion_completa);
      $valida_ubi_ext = $establecimiento_ubicacion_pais != "MEX" && $valida_estab_ext_direccion_completa;

      $valida_estab_phoneAll = isset($establecimiento_phoneAll) && !empty($establecimiento_phoneAll) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_phoneAll);
      //echo $establecimiento_cuenta_contable;
      $valida_estab_ccontable = isset($establecimiento_cuenta_contable) && !empty($establecimiento_cuenta_contable) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_cuenta_contable);

      if ($valida_estab_alias && $valida_estab_tipo && $valida_estab_descripcion && $valida_estab_aplica_ingresos && $valida_estab_aplica_egresos && $valida_estab_aplica_procesos_internos && $valida_estab_aplica_almacen && 
        $valida_estab_ubicacion_pais && ($valida_ubi_mx || $valida_ubi_ext) && $valida_estab_phoneAll && $valida_estab_ccontable) {
          
        $queryEstabs = AlmacenModelo::join("main_empresas AS emp", "in_egr_establecimientos_catalogo.empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'in_egr_establecimientos_catalogo.token_establecimiento' => $establecimiento_token,
          'in_egr_establecimientos_catalogo.status_establecimiento' => TRUE,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])->get();

        foreach ($queryEstabs as $vEstab) {
          $updateAlmacen = DB::table('in_egr_establecimientos_catalogo')
          ->where('token_establecimiento',$vEstab->token_establecimiento)
          ->limit(1)->update(
            array(
              'alias_establecimiento' => $JwtAuth->encriptar($establecimiento_alias),
              'tipo_establecimiento' => $JwtAuth->encriptar($establecimiento_tipo),
              'descripcion_establecimiento' => $JwtAuth->encriptar($establecimiento_descripcion),
              'aplica_almacen' => $establecimiento_aplica_almacen ? TRUE : FALSE,
              'ingresos' => $establecimiento_aplica_ingresos ? TRUE : FALSE,
              'egresos' => $establecimiento_aplica_egresos ? TRUE : FALSE,
              'interno' => $establecimiento_aplica_procesos_internos ? TRUE : FALSE,
              'telefono' => $JwtAuth->encriptar($establecimiento_phoneAll),
              'cuenta_contable' => $establecimiento_cuenta_contable,
            )
          );
          
          if ($establecimiento_ubicacion_pais == "MEX") {
            $updateAlmacen = DB::table("teci_direcciones AS ubi")
            ->join("in_egr_establecimientos_catalogo AS estab", "ubi.establecimiento", "estab.id")
            ->where('estab.token_establecimiento',$vEstab->token_establecimiento)
            ->update(array(
              "clase" => $establecimiento_tipo,
              "pais" => 118,
              "pais_code" => "MEX",
              "estado_edit" => $JwtAuth->encriptar($establecimiento_estado),
              "municipio_edit" => $JwtAuth->encriptar($establecimiento_municipio),
              "c_postal_edit" => $establecimiento_cp,
              "colonia_edit" => $JwtAuth->encriptar($establecimiento_colonia),
              "adicional" => "api"
            ));
          } else {
            $updateAlmacen = DB::table("teci_direcciones AS ubi")
            ->join("in_egr_establecimientos_catalogo AS estab", "ubi.establecimiento", "estab.id")
            ->where('estab.token_establecimiento',$vEstab->token_establecimiento)
            ->update(array(
              "clase" => $establecimiento_tipo,
              "pais_code" => $establecimiento_ubicacion_pais,
              "cod_postalext" => $JwtAuth->encriptar($establecimiento_ext_direccion_completa),
              "adicional" => "api"
            ));
          }

          if ($updateAlmacen) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Actualización de establecimiento realizada'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Actualización de establecimiento no realizada, intente nuevamente o comuniquese a soporte'
            );
          }
        }

      } else {
        $error_alerta = "";
        if (!$valida_estab_alias) $error_alerta = "Error en alias de establecimiento, intente nuevamente o comuniquese a soporte";
        if (!$valida_estab_tipo) $error_alerta = "Error en tipo de establecimiento, intente nuevamente o comuniquese a soporte";
        if (!$valida_estab_descripcion) $error_alerta = "Error en descripción de establecimiento, intente nuevamente o comuniquese a soporte";
        if (!$valida_estab_aplica_almacen) $error_alerta = "Error al seleccionar si establecimiento aplica para almacen, intente nuevamente o comuniquese a soporte";
        if (!$valida_estab_ubicacion_pais) $error_alerta = "Error en país de establecimiento, intente nuevamente o comuniquese a soporte";
        if ($valida_estab_ubicacion_pais == "MEX" && !$valida_estab_estado) $error_alerta = "Error en estado de establecimiento, intente nuevamente o comuniquese a soporte";
        if ($valida_estab_ubicacion_pais == "MEX" && !$valida_estab_municipio) $error_alerta = "Error en municipio establecimiento, intente nuevamente o comuniquese a soporte";
        if ($valida_estab_ubicacion_pais == "MEX" && !$valida_estab_cp) $error_alerta = "Error en código postal de establecimiento, intente nuevamente o comuniquese a soporte";
        if ($valida_estab_ubicacion_pais == "MEX" && !$valida_estab_colonia) $error_alerta = "Error en colonia de establecimiento, intente nuevamente o comuniquese a soporte";
        if ($valida_estab_ubicacion_pais != "MEX" && !$valida_estab_ext_direccion_completa) $error_alerta = "Error en diracción de establecimiento, intente nuevamente o comuniquese a soporte";
        if (!$valida_estab_phoneAll) $error_alerta = "Error en telefono de establecimiento, intente nuevamente o comuniquese a soporte"; 
        if (!$valida_estab_ccontable) $error_alerta = "Error en cuenta contable de establecimiento, intente nuevamente o comuniquese a soporte";

        $dataMensaje = array('status' => 'error','code' => 200,'message' => $error_alerta);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaEstablecimiento(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'tokenEstablecimiento' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $tokenEstablecimiento = $request->input('tokenEstablecimiento');
      
      $listaDirAlmacen = AlmacenModelo::join("main_empresas AS emp", "in_egr_establecimientos_catalogo.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'in_egr_establecimientos_catalogo.token_establecimiento' => $tokenEstablecimiento,
        "in_egr_establecimientos_catalogo.status_establecimiento" => TRUE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      if ($listaDirAlmacen->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron establecimientos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        foreach ($listaDirAlmacen as $vEstab) {
          $folio_establecimiento = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '');
          $deleteEstab = DB::table('in_egr_establecimientos_catalogo')
          ->where('token_establecimiento',$vEstab->token_establecimiento)
          ->limit(1)->update(
            array(
              'status_establecimiento' => FALSE,
              'fecha_delete_estab' => time(),
            )
          );
          
          if ($deleteEstab) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Establecimiento con folio $folio_establecimiento ha sido eliminado"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Establecimiento con folio $folio_establecimiento no eliminado, intente nuevamente o comuniquese a soporte"
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function establecimientosDeletedCatalogo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryEstabs = AlmacenModelo::join("main_empresas AS emp", "in_egr_establecimientos_catalogo.empresa", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
    ->where([
      'in_egr_establecimientos_catalogo.status_establecimiento' => FALSE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])
    ->get();

    if ($queryEstabs->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron establecimientos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $direccionAlmacen = array();
      foreach ($queryEstabs as $vEstab) {
        $arrayDirAlmExt = array(
          "token_establecimiento" => $vEstab->token_establecimiento,
          "estab_folio" => 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : ''),
          "estab_alias" => $JwtAuth->desencriptar($vEstab->alias_establecimiento),
          "estab_tipo" => !empty($vEstab->tipo_establecimiento) ? $JwtAuth->desencriptar($vEstab->tipo_establecimiento) : '',
          "estab_desc" => !empty($vEstab->descripcion_establecimiento) ? $JwtAuth->desencriptar($vEstab->descripcion_establecimiento) : '',
          "aplica_almacen" => $vEstab->aplica_almacen ? 'yes' : 'no',
          "aplica_ingresos" => $vEstab->ingresos ? 'yes' : 'no',
          "aplica_egresos" => $vEstab->egresos ? 'yes' : 'no',
          "aplica_interno" => $vEstab->interno ? 'yes' : 'no',
          "select_for_centrotrab" => false,
          "estab_detalle" => [],
        );
        $direccionAlmacen[] = $arrayDirAlmExt;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'establecimientos' => $direccionAlmacen,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function restaurarEstablecimiento(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'tokenEstablecimiento' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $tokenEstablecimiento = $request->input('tokenEstablecimiento');
      
      $listaDirAlmacen = AlmacenModelo::join("main_empresas AS emp", "in_egr_establecimientos_catalogo.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'in_egr_establecimientos_catalogo.token_establecimiento' => $tokenEstablecimiento,
        "in_egr_establecimientos_catalogo.status_establecimiento" => FALSE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();
      
      if ($listaDirAlmacen->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron establecimientos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        foreach ($listaDirAlmacen as $vEstab) {
          $folio_establecimiento = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '');
          $deleteEstab = DB::table('in_egr_establecimientos_catalogo')
          ->where('token_establecimiento',$vEstab->token_establecimiento)
          ->limit(1)->update(
            array(
              'status_establecimiento' => TRUE,
              'fecha_delete_estab' => NULL,
            )
          );
          
          if ($deleteEstab) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Establecimiento con folio $folio_establecimiento ha sido restaurado"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Establecimiento con folio $folio_establecimiento no restaurado, intente nuevamente o comuniquese a soporte"
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaPermEstablecimiento(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'tokenEstablecimiento' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $tokenEstablecimiento = $request->input('tokenEstablecimiento');
      
      $listaDirAlmacen = AlmacenModelo::join("main_empresas AS emp", "in_egr_establecimientos_catalogo.empresa", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
      ->where([
        'in_egr_establecimientos_catalogo.token_establecimiento' => $tokenEstablecimiento,
        "in_egr_establecimientos_catalogo.status_establecimiento" => FALSE,
        "emp.empresa_token" => $empresa,
        "users.usuario_token" => $usuario
      ])
      ->get();

      if ($listaDirAlmacen->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron establecimientos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        foreach ($listaDirAlmacen as $vEstab) {
          $folio_establecimiento = 'ESTAB-'.$JwtAuth->generarFolio($vEstab->folio_establecimiento).($vEstab->post_folio != NULL ? '-'.$vEstab->post_folio : '');
          
          $queryDirEstab = DB::table("teci_direcciones AS dir")
          ->join("in_egr_establecimientos_catalogo AS estab", "dir.establecimiento", "estab.id")
          ->where('estab.token_establecimiento',$vEstab->token_establecimiento)->get();
          
          if (count($queryDirEstab) > 0) {
            foreach ($queryDirEstab as $vDir) {
              $deleteDir = DB::table("teci_direcciones")->where('token_direccion',$vDir->token_direccion)->limit(1)->delete();
              //->insert(array("token_direccion" => $tokenCDir));
            }
          }

          $deleteEstab = DB::table('in_egr_establecimientos_catalogo')
          ->where('token_establecimiento',$vEstab->token_establecimiento)
          ->limit(1)->delete();
          
          if ($deleteEstab) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Establecimiento con folio $folio_establecimiento ha sido eliminado"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => "Establecimiento con folio $folio_establecimiento no eliminado, intente nuevamente o comuniquese a soporte"
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraEstablecimiento(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'establecimiento_alias' => 'required|string',
      'establecimiento_tipo' => 'required|string',
      'establecimiento_descripcion' => 'required|string',
      'establecimiento_aplica_ingresos' => 'required|boolean',
      'establecimiento_aplica_egresos' => 'required|boolean',
      'establecimiento_aplica_procesos_internos' => 'required|boolean',
      'establecimiento_aplica_almacen' => 'required|boolean',
      'establecimiento_ubicacion_pais' => 'required|string',
      'establecimiento_dipomex_cod_postal_estado' => 'nullable|string',
      'establecimiento_dipomex_cod_postal_municipio' => 'nullable|string',
      'establecimiento_dipomex_cod_postal_cp' => 'nullable|string',
      'establecimiento_dipomex_cod_postal_colonia_vinculada' => 'nullable|string',
      'establecimiento_ext_direccion_completa' => 'nullable|string',
      'establecimiento_phoneAll' => 'required|string',
      'establecimiento_cuenta_contable' => 'nullable|string',
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
      $establecimiento_alias = $request->input('establecimiento_alias');
      $establecimiento_tipo = $request->input('establecimiento_tipo');
      $establecimiento_descripcion = $request->input('establecimiento_descripcion');
      //$establecimiento_encargado = $request->input('establecimiento_encargado');
      $establecimiento_aplica_ingresos = $request->input('establecimiento_aplica_ingresos');
      $establecimiento_aplica_egresos = $request->input('establecimiento_aplica_egresos');
      $establecimiento_aplica_procesos_internos = $request->input('establecimiento_aplica_procesos_internos');
      $establecimiento_aplica_almacen = $request->input('establecimiento_aplica_almacen');
      $establecimiento_ubicacion_pais = $request->input('establecimiento_ubicacion_pais');
      $establecimiento_estado = $request->input('establecimiento_dipomex_cod_postal_estado');
      $establecimiento_municipio = $request->input('establecimiento_dipomex_cod_postal_municipio');
      $establecimiento_cp = $request->input('establecimiento_dipomex_cod_postal_cp');
      $establecimiento_colonia = $request->input('establecimiento_dipomex_cod_postal_colonia_vinculada');
      $establecimiento_ext_direccion_completa = $request->input('establecimiento_ext_direccion_completa');
      $establecimiento_phoneAll = $request->input('establecimiento_phoneAll');
      $establecimiento_cuenta_contable = $request->input('establecimiento_cuenta_contable');

      $valida_estab_alias = isset($establecimiento_alias) && !empty($establecimiento_alias) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_alias);
      $valida_estab_tipo = isset($establecimiento_tipo) && !empty($establecimiento_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_tipo); 
      $valida_estab_descripcion = isset($establecimiento_descripcion) && !empty($establecimiento_descripcion) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_descripcion); 
      //$valida_estab_encargado = isset($establecimiento_encargado) && !empty($establecimiento_encargado); 
      $valida_estab_aplica_ingresos = isset($establecimiento_aplica_ingresos) && is_bool($establecimiento_aplica_ingresos);
      $valida_estab_aplica_egresos = isset($establecimiento_aplica_egresos) && is_bool($establecimiento_aplica_egresos);
      $valida_estab_aplica_procesos_internos = isset($establecimiento_aplica_procesos_internos) && is_bool($establecimiento_aplica_procesos_internos);
      $valida_estab_aplica_almacen = isset($establecimiento_aplica_almacen) && is_bool($establecimiento_aplica_almacen);
      
      $valida_estab_ubicacion_pais = isset($establecimiento_ubicacion_pais) && !empty($establecimiento_ubicacion_pais) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_ubicacion_pais); 
      $valida_estab_estado = isset($establecimiento_estado) && !empty($establecimiento_estado) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_estado);
      $valida_estab_municipio = isset($establecimiento_municipio) && !empty($establecimiento_municipio) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_municipio);
      $valida_estab_cp = isset($establecimiento_cp) && !empty($establecimiento_cp) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_cp);
      $valida_estab_colonia = isset($establecimiento_colonia) && !empty($establecimiento_colonia) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_colonia);
      
      $valida_estab_ext_direccion_completa = isset($establecimiento_ext_direccion_completa) && !empty($establecimiento_ext_direccion_completa) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_ext_direccion_completa);
      $valida_estab_phoneAll = isset($establecimiento_phoneAll) && !empty($establecimiento_phoneAll) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_phoneAll);
      $valida_estab_ccontable = isset($establecimiento_cuenta_contable) && !empty($establecimiento_cuenta_contable) && preg_match($JwtAuth->filtroAlfaNumerico(), $establecimiento_cuenta_contable);

      if ($valida_estab_alias && $valida_estab_tipo && $valida_estab_descripcion && $valida_estab_aplica_ingresos && $valida_estab_aplica_egresos &&
        $valida_estab_aplica_procesos_internos && $valida_estab_aplica_almacen && $valida_estab_ubicacion_pais && 
        (($establecimiento_ubicacion_pais == "MEX" && $valida_estab_estado && $valida_estab_municipio && $valida_estab_cp && $valida_estab_colonia) || 
        ($establecimiento_ubicacion_pais != "MEX" && $valida_estab_ext_direccion_completa)) &&
        $valida_estab_phoneAll && $valida_estab_ccontable) {
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);
        foreach ($queryEmp as $vEmp) {
          $fecha_registro = time();
          //da_te_default_timezone_set($vEmp->zona_horaria);

          $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp WHERE fold.egr_establecimientos = TRUE AND fold.empresa = emp.id
          AND emp.empresa_token = ?", [$empresa]);

          $post_folio_db = DB::select("SELECT post_folio FROM in_egr_establecimientos_catalogo WHERE id = (SELECT MAX(estab.id) FROM in_egr_establecimientos_catalogo AS estab
            JOIN main_empresas AS emp WHERE estab.empresa = emp.id AND emp.empresa_token = ?)", [$empresa]);
          //return response()->json(["message" => "prueba6","code" => 200,"status" => "error"]);
          $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
          $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
          $folio_estab = 'ESTAB-'.$JwtAuth->generarFolio($folio_nuevo).($post_folio != NULL ? '-'.$post_folio : '');
          //$obten_encargado = $valida_estab_encargado ? DB::table("vhum_empleados_catalogo")->where("empleado_token",$establecimiento_encargado)->value("id") : NULL;
          $tokenEstab = $JwtAuth->encriptarToken(time(),$establecimiento_tipo,$establecimiento_alias,$establecimiento_estado);
          $newAlmacen = new AlmacenModelo();
          $newAlmacen->token_establecimiento = $tokenEstab;
          $newAlmacen->folio_establecimiento = $folio_nuevo;
          $newAlmacen->post_folio = $post_folio;
          $newAlmacen->alias_establecimiento = $JwtAuth->encriptar($establecimiento_alias);
          $newAlmacen->tipo_establecimiento = $JwtAuth->encriptar($establecimiento_tipo);
          $newAlmacen->descripcion_establecimiento = $JwtAuth->encriptar($establecimiento_descripcion);
          //$newAlmacen->encargado_establecimiento = $obten_encargado;
          $newAlmacen->aplica_almacen = $establecimiento_aplica_almacen ? TRUE : FALSE;
          $newAlmacen->ingresos = $establecimiento_aplica_ingresos ? TRUE : FALSE;
          $newAlmacen->egresos = $establecimiento_aplica_egresos ? TRUE : FALSE;
          $newAlmacen->interno = $establecimiento_aplica_procesos_internos ? TRUE : FALSE;
          $newAlmacen->telefono = $JwtAuth->encriptar($establecimiento_phoneAll);
          $newAlmacen->cuenta_contable = $establecimiento_cuenta_contable;
          $newAlmacen->status_establecimiento = TRUE;
          $newAlmacen->fecha_delete_estab = NULL;
          $newAlmacen->habilitado =TRUE;
          $newAlmacen->empresa = $vEmp->id;
          $savedAlamcen = $newAlmacen->save();
          if ($savedAlamcen) {
            $newAlmacen->id;
            $tipo_direccion = "establecimiento";
            if ($establecimiento_ubicacion_pais == "MEX") {
              $tokenCDir = $JwtAuth->encriptarToken($fecha_registro,$establecimiento_estado,$establecimiento_municipio,$establecimiento_cp,$establecimiento_colonia,$establecimiento_tipo);
              $fisinsertDir = DB::table("teci_direcciones")
              ->insert(array(
                "token_direccion" => $tokenCDir,
                "tipo_direccion" => $tipo_direccion,
                "clase" => $establecimiento_tipo,
                "pais" => 118,
                "pais_code" => "MEX",
                "estado_edit" => $JwtAuth->encriptar($establecimiento_estado),
                "municipio_edit" => $JwtAuth->encriptar($establecimiento_municipio),
                "c_postal_edit" => $establecimiento_cp,
                "colonia_edit" => $JwtAuth->encriptar($establecimiento_colonia),
                "adicional" => "api",
                "establecimiento" => $newAlmacen->id,
                "status" => TRUE,
                "administrador" => $vEmp->id,
              ));
            } else {
              $tokenCDir = $JwtAuth->encriptarToken($fecha_registro,$establecimiento_ubicacion_pais,$establecimiento_ext_direccion_completa);
              $fisinsertDir = DB::table("teci_direcciones")
              ->insert(array(
                "token_direccion" => $tokenCDir,
                "tipo_direccion" => $tipo_direccion,
                "clase" => $establecimiento_tipo,
                "pais_code" => $establecimiento_ubicacion_pais,
                "cod_postalext" => $JwtAuth->encriptar($establecimiento_ext_direccion_completa),
                "adicional" => "api",
                "establecimiento" => $newAlmacen->id,
                "status" => TRUE,
                "administrador" => $vEmp->id,
              ));
            }
            
            if (count($folioSistema) == 0) {
              $insertSistema = DB::table("sos_last_folders")
              ->insert(array(
                "egr_establecimientos" => TRUE,
                "folder" => 1,
                "post_folder" => $post_folio,
                "empresa" => $vEmp->id,
              ));
            } else {
              $regFolder = DB::table("sos_last_folders AS last")
              ->join("main_empresas AS emp","last.empresa","=","emp.id")
              ->where([
                'last.egr_establecimientos' => TRUE,
                'emp.empresa_token' => $empresa,
              ])
              ->limit(1)->update(
                array(
                  'last.folder' => $folio_nuevo,
                  'last.post_folder' => $post_folio,
                )
              );
            }

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Almacén registrado satisfactoriamente con el folio '.$folio_estab
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 400,
              'message' => 'Registro de establecimiento no realizado, intente nuevamente o comuniquese a soporte'
            );
          }
        }
      } else {
        $error_alerta = "";
        if (!$valida_estab_alias) $error_alerta = "Error en alias de establecimiento, intente nuevamente o comuniquese a soporte";
        if (!$valida_estab_tipo) $error_alerta = "Error en tipo de establecimiento, intente nuevamente o comuniquese a soporte";
        if (!$valida_estab_descripcion) $error_alerta = "Error en descripción de establecimiento, intente nuevamente o comuniquese a soporte";
        if (!$valida_estab_aplica_almacen) $error_alerta = "Error al seleccionar si establecimiento aplica para almacen, intente nuevamente o comuniquese a soporte";
        if (!$valida_estab_ubicacion_pais) $error_alerta = "Error en país de establecimiento, intente nuevamente o comuniquese a soporte";
        if ($valida_estab_ubicacion_pais == "MEX" && !$valida_estab_estado) $error_alerta = "Error en estado de establecimiento, intente nuevamente o comuniquese a soporte";
        if ($valida_estab_ubicacion_pais == "MEX" && !$valida_estab_municipio) $error_alerta = "Error en municipio establecimiento, intente nuevamente o comuniquese a soporte";
        if ($valida_estab_ubicacion_pais == "MEX" && !$valida_estab_cp) $error_alerta = "Error en código postal de establecimiento, intente nuevamente o comuniquese a soporte";
        if ($valida_estab_ubicacion_pais == "MEX" && !$valida_estab_colonia) $error_alerta = "Error en colonia de establecimiento, intente nuevamente o comuniquese a soporte";
        if ($valida_estab_ubicacion_pais != "MEX" && !$valida_estab_ext_direccion_completa) $error_alerta = "Error en diracción de establecimiento, intente nuevamente o comuniquese a soporte";
        if ($valida_estab_phoneAll) $error_alerta = "Error en telefono de establecimiento, intente nuevamente o comuniquese a soporte"; 
        if ($valida_estab_ccontable) $error_alerta = "Error en cuenta contable de establecimiento, intente nuevamente o comuniquese a soporte";

        $dataMensaje = array('status' => 'error','code' => 200,'message' => $error_alerta);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraEstablecimientoExtranjero(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'estabnac_clase' => 'required|string',
      'estabnac_alias' => 'required|string',
      'estabnac_cod_postal' => 'required|string',
      'estabnac_calle' => 'required|string',
      'estab_pais' => 'required|string',
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
      $estabnac_clase = $request->input('estabnac_clase');
      $estabnac_alias = $request->input('estabnac_alias');
      $estabnac_cod_postal = $request->input('estabnac_cod_postal');
      $estabnac_calle = $request->input('estabnac_calle');
      $estab_pais = $request->input('estab_pais');
      
      $OKEstClase = isset($estabnac_clase) && !empty($estabnac_clase) && preg_match($JwtAuth->filtroAlfaNumerico(), $estabnac_clase);
      $OKEstAlias = isset($estabnac_alias) && !empty($estabnac_alias) && preg_match($JwtAuth->filtroAlfaNumerico(), $estabnac_alias);
      $OKEstCodPostal = isset($estabnac_cod_postal) && !empty($estabnac_cod_postal) && preg_match($JwtAuth->filtroCpostal(), $estabnac_cod_postal);

      if ($OKEstClase && $OKEstAlias && $OKEstCodPostal) {
        $vEmp = DB::table("main_empresas AS emp")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where([
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])
        ->select('emp.id','emp.zona_horaria','emp.root_tkn','users.id AS userr')
        ->first();

        if ($vEmp) {
          //da_te_default_timezone_set($vEmp->zona_horaria);
    
          $folioAlamcen = DB::select("SELECT IF (max(folio_almacen) IS NOT NULL,(max(folio_almacen)+1),1) AS folio FROM almacen AS alm JOIN main_empresas AS emp 
            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE alm.empresa = emp.id 
            AND emp.empresa_token = ? AND emp.id = empper.empresa AND empper.personal = pers.id AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$empresa, $usuario]);
    
          $tokenAlmacen = $JwtAuth->encriptarToken(time(), $estabnac_clase, $estabnac_alias);
          $tokenDireccion = $JwtAuth->encriptarToken(time(), $estabnac_clase, $estabnac_alias, $estabnac_cod_postal, $estabnac_calle);
  
          $arrayClasificacion = $JwtAuth->encriptar($estabnac_clase);
          $arrayalias = $JwtAuth->encriptar($estabnac_alias);
          $arraycalle = $JwtAuth->encriptar($estabnac_calle);
          $estabnac_cod_postal = $JwtAuth->encriptar($estabnac_cod_postal);
          $id_pais = DB::table("teci_pais")->where("token_pais",$estab_pais)->value("id");
  
          $insertDirEstab = DB::table('direcciones')
            ->insert(array(
              "token_direccion" => $tokenDireccion,
              "clase" => $arrayClasificacion,
              "alias" => $arrayalias,
              "calle" => $arraycalle,
              "num_ext" => NULL,
              "num_int" => NULL,
              "codigo_postal" => NULL,
              "cod_postalext" => $estabnac_cod_postal,
              "pais" => $id_pais,
              "calle1" => NULL,
              "calle2" => NULL,
              "referencia" => NULL,
              "status" => TRUE,
              "administrador" => $vEmp->id,
            ));
  
          if ($insertDirEstab) {
            $idDireccion = DB::select("SELECT id FROM direcciones WHERE token_direccion = ?", [$tokenDireccion]);
            $newAlmacen = new AlmacenModelo();
            $newAlmacen->token_almacen = $tokenAlmacen;
            $newAlmacen->folio_almacen = $folioAlamcen[0]->folio;
            $newAlmacen->alias_almacen = $arrayalias;
            $newAlmacen->fecha_delete_alm = NULL;
            $newAlmacen->status_alm = TRUE;
            $newAlmacen->ubicacion = $idDireccion[0]->id;
            $newAlmacen->empresa = $vEmp->id;
            $savedAlamcen = $newAlmacen->save();
  
            if ($savedAlamcen) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Almacén registrado satisfactoriamente con el folio ' . $JwtAuth->generar($folioAlamcen[0]->folio)
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Registro de establecimiento no realizado, intente nuevamente o comuniquese a soporte'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Registro de dirección de establecimiento no realizado, intente nuevamente o comuniquese a soporte'
            );
          }
        }
      } else {
        $mensaje_error = '';
        if (!$OKEstClase) { $mensaje_error = 'Error en clasificacion de establecimiento'; }
        if (!$OKEstAlias) { $mensaje_error = 'Error en alias de establecimiento'; }
        if (!$OKEstCodPostal) { $mensaje_error = 'Error en codigo postal de establecimiento'; }
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaResponsablesAlmacen(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_establecimiento' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_establecimiento = $request->input('token_establecimiento');
      $listaEstablecimientos = array();
      
      $listPersonal = PersonalModelo::join("sos_personas AS people","vhum_empleados_catalogo.empleado_name","people.id")
      ->join("in_egr_establecimientos_responsables AS resp","vhum_empleados_catalogo.id","resp.responsable")
      ->join("in_egr_establecimientos_catalogo AS estab","resp.almacen","estab.id")
      ->join("main_empresas AS emp","estab.empresa","emp.id")
      ->where([
        'vhum_empleados_catalogo.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'estab.token_establecimiento' => $token_establecimiento,
        //'users.user_token' => $tokenUser '1457869IHDIFUJJ39485'
      ])->get();

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'listaEstablecimientos' => $listaEstablecimientos,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
