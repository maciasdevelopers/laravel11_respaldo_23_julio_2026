<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Models\ComprasModelo;
use App\Models\ActivosFijosModelo;
use Illuminate\Support\Str;
use PDF;
use QRCode;

class INVENT_KardexController extends Controller{
  public function procesaActivoFijoLista($dataActivos,$JwtAuth){
    $activos_procesados = array();
    $periodos = [86400 => 'Por día',604800 => 'Por semana',2629743 => 'Por mes',31556926 => 'Por año'];

    $contador = 1;
    foreach ($dataActivos as $vActivos) {
      //da_te_default_timezone_set($vActivos->zona_horaria);
      $deprec_contable_importe = $vActivos->deprec_contable_tipo == 'cuota' ? "$".number_format($vActivos->deprec_contable_importe,$JwtAuth->getMonedaAPI('MXN'),'.', ',')." MXN" : number_format($vActivos->deprec_contable_importe,$JwtAuth->getMonedaAPI('MXN'),'.','').'%';
      $deprec_fiscal_importe = $vActivos->deprec_fiscal_tipo == 'cuota' ? "$".number_format($vActivos->deprec_fiscal_importe,$JwtAuth->getMonedaAPI('MXN'),'.', ',')." MXN" : number_format($vActivos->deprec_fiscal_importe,$JwtAuth->getMonedaAPI('MXN'),'.','').'%';

      $detalles_relacionados = DB::table('eegr_activos_fijos_detalle AS actdet')
      ->join("eegr_activos_fijos_catalogo AS actf", "actdet.activo_fijo", "=", "actf.id")
      ->where("actf.token_act_fijos", $vActivos->token_act_fijos)
      ->count();

      $hay_deprec_pendiente = !is_null($vActivos->fecha_inicio_depreciacion) && ($vActivos->fecha_proximo_corte_contable <= time() || $vActivos->fecha_proximo_corte_fiscal <= time());

      $arrayEach = array(
        "num_act" => $contador,
        "token_act_fijos" => $vActivos->token_act_fijos,
        "token_det_activo_fijo" => $vActivos->token_det_activo_fijo,
        "token_activof_unidad" => $vActivos->token_activof_unidad,
        "folio_activo" => "ACTF-".$JwtAuth->generarFolio($vActivos->folio_activo),
        "fechaAlta" => gmdate('Y-m-d H:i:s', $vActivos->fechaAlta),
        "categoria" => $JwtAuth->desencriptar($vActivos->categoria),
        "categoria_cuenta_contable" => $vActivos->categoria_cuenta_contable,

        "articulo" => $JwtAuth->desencriptar($vActivos->concepto),
        "folio_activof_unidad" => $vActivos->folio_activof_unidad,
        "cantidad" => $vActivos->cantidad_recibida,
        "unidad_medida" => $vActivos->unidad_medida_recibida,
        "fecha_recep" => gmdate('Y-m-d H:i:s', $vActivos->fecha_recep),
        "folio_recep" => $JwtAuth->generarFolio($vActivos->folio_recep),
        "unidad_observaciones" => !is_null($vActivos->unidad_observaciones) ? $JwtAuth->desencriptar($vActivos->unidad_observaciones) : '',
        "fecha_inicio_depreciacion" => !is_null($vActivos->fecha_inicio_depreciacion) ? gmdate('Y-m-d H:i:s', $vActivos->fecha_inicio_depreciacion) : '',
        "fecha_iniciar_depreciacion" => '',
        
        "deprec_contable_tipo" => $vActivos->deprec_contable_tipo,
        "deprec_contable_periodo" => $periodos[$vActivos->deprec_contable_periodo] ?? '',
        "deprec_contable_importe" => $deprec_contable_importe,
        "deprec_contable_cuenta" => $vActivos->deprec_contable_cuenta,
        "deprec_contable_cuenta_dos" => $vActivos->deprec_contable_cuenta_dos,
        "deprec_fiscal_tipo" => $vActivos->deprec_fiscal_tipo,
        "deprec_fiscal_periodo" => $periodos[$vActivos->deprec_fiscal_periodo] ?? '',
        "deprec_fiscal_importe" => $deprec_fiscal_importe,
        "deprec_fiscal_cuenta" => $vActivos->deprec_fiscal_cuenta,
        "deprec_fiscal_cuenta_dos" => $vActivos->deprec_fiscal_cuenta_dos,
        "activo_observaciones" => $JwtAuth->desencriptar($vActivos->activo_observaciones),
        "puede_eliminar" => $detalles_relacionados == 0 ? true : false,
        "hay_deprec_pendiente" => $hay_deprec_pendiente,
        "depreciacion_bloqueada" => (bool)$vActivos->depreciacion_bloqueada,
        "date_bloqueo_desbloqueo_prorrateo" => gmdate('Y-m-d H:i:s', $vActivos->date_bloqueo_desbloqueo_prorrateo),
      );
      ++$contador;
      $activos_procesados[] = $arrayEach;
    }
    return $activos_procesados;
  }

  public function contGetActivosFijosEsteMes(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    //da_te_default_timezone_set('America/Mexico_City');
    $inicioMes = strtotime(date('Y-m-01 00:00:00'));
    $finMes = strtotime(date('Y-m-t 23:59:59'));
    $queryActivos = ActivosFijosModelo::join("eegr_activos_fijos_detalle AS actfDet","eegr_activos_fijos_catalogo.id","=","actfDet.activo_fijo")
    ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
    ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
    ->join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      'eegr_activos_fijos_catalogo.activo_status' => TRUE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])
    ->whereBetween("eegr_activos_fijos_catalogo.fechaAlta", [$inicioMes, $finMes])
    ->orderBy('eegr_activos_fijos_catalogo.id', 'DESC')
    ->get();

    if ($queryActivos->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron activos registrados'
      );
    } else {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'success',
        'datosActivo' => $this->procesaActivoFijoLista($queryActivos,$JwtAuth)
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function contGetActivosFijosMesAnterior(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    //da_te_default_timezone_set('America/Mexico_City');
    $inicioMes = strtotime("first day of last month 00:00:00");
    $finMes = strtotime("last day of last month 23:59:59");
    $queryActivos = ActivosFijosModelo::join("eegr_activos_fijos_detalle AS actfDet","eegr_activos_fijos_catalogo.id","=","actfDet.activo_fijo")
    ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
    ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
    ->join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      'eegr_activos_fijos_catalogo.activo_status' => TRUE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])
    ->whereBetween("eegr_activos_fijos_catalogo.fechaAlta", [$inicioMes, $finMes])
    ->orderBy('eegr_activos_fijos_catalogo.id', 'DESC')
    ->get();

    if ($queryActivos->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron activos registrados'
      );
    } else {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'success',
        'datosActivo' => $this->procesaActivoFijoLista($queryActivos,$JwtAuth)
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function contGetActivosFijosPeriodoFechas(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'periodo_inicio' => 'required|date_format:Y-m-d',
      'periodo_fin' => 'required|date_format:Y-m-d',
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
      $periodo_inicio = $request->input('periodo_inicio');
      $periodo_fin = $request->input('periodo_fin');
      $fechaInicio = strtotime($periodo_inicio . " 00:00:00");
      $fechaFin = strtotime($periodo_fin . " 23:59:59");
      $queryActivos = ActivosFijosModelo::join("eegr_activos_fijos_detalle AS actfDet","eegr_activos_fijos_catalogo.id","=","actfDet.activo_fijo")
      ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
      ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
      ->join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_activos_fijos_catalogo.activo_status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->whereBetween("eegr_activos_fijos_catalogo.fechaAlta", [$fechaInicio, $fechaFin])
      ->orderBy('eegr_activos_fijos_catalogo.id', 'DESC')
      ->get();
  
      if ($queryActivos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'datosActivo' => $this->procesaActivoFijoLista($queryActivos,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function contGetActivosFijosAllRegistros(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    //da_te_default_timezone_set('America/Mexico_City');
    $queryActivos = ActivosFijosModelo::join("eegr_activos_fijos_detalle AS actfDet","eegr_activos_fijos_catalogo.id","=","actfDet.activo_fijo")
    ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
    ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
    ->join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      'eegr_activos_fijos_catalogo.activo_status' => TRUE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])
    ->orderBy('eegr_activos_fijos_catalogo.id', 'DESC')
    ->get();

    if ($queryActivos->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron activos registrados'
      );
    } else {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'success',
        'datosActivo' => $this->procesaActivoFijoLista($queryActivos,$JwtAuth)
      );
    }

    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function contActivoFijoDetalleToDeprec(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_activof_unidad' => 'required|string',
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
      $token_activof_unidad = $request->input('token_activof_unidad');
      $lista_pendientes = [];

      $queryActivo = ActivosFijosModelo::join("eegr_activos_fijos_detalle AS actfDet","eegr_activos_fijos_catalogo.id","=","actfDet.activo_fijo")
      ->join("eegr_activos_fijos_unidades AS actfUnid","actfDet.id","=","actfUnid.activof_detalle")
      ->join("eegr_compras_recepcion AS recept","actfUnid.id","=","recept.unidad_activo_fijo")
      ->join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_activos_fijos_catalogo.activo_status' => TRUE,
        'actfUnid.token_activof_unidad' => $token_activof_unidad,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->get();
  
      if ($queryActivo->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
        } else {
        foreach ($queryActivo as $vPend) {
          $lista_pendientes[] = [
            "token_activof_unidad" => $vPend->token_activof_unidad,
            "folio_activof_unidad" => $vPend->folio_activof_unidad,
            "unidad_serie" => $vPend->unidad_serie,
            "unidad_otros" => $JwtAuth->desencriptar($vPend->unidad_otros),
            "unidad_observaciones" => $JwtAuth->desencriptar($vPend->unidad_observaciones),
            "status_unidad_activo" => $vPend->status_unidad_activo,
            "costo_adquisicion" => $vPend->costo_adquisicion,
            "fecha_inicio_depreciacion" => gmdate('Y-m-d H:i:s', $vPend->fecha_inicio_depreciacion),
            "fecha_ultimo_corte_contable" => gmdate('Y-m-d H:i:s', $vPend->fecha_ultimo_corte_contable),
            "fecha_proximo_corte_contable" => gmdate('Y-m-d H:i:s', $vPend->fecha_proximo_corte_contable),
            "fecha_ultimo_corte_fiscal" => gmdate('Y-m-d H:i:s', $vPend->fecha_ultimo_corte_fiscal),
            "fecha_proximo_corte_fiscal" => gmdate('Y-m-d H:i:s', $vPend->fecha_proximo_corte_fiscal),
            "depreciacion_bloqueada" => $vPend->depreciacion_bloqueada,
            "date_bloqueo_desbloqueo_prorrateo" => gmdate('Y-m-d H:i:s', $vPend->date_bloqueo_desbloqueo_prorrateo),
          ];
        }
        $dataMensaje = array(
          'code' => 200,
          'status' => 'success',
          'lista_pendientes' => $lista_pendientes
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function getDepreciacionReporte(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_activof_unidad' => 'required|string',
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
      $token_activof_unidad = $request->input('token_activof_unidad');

      $reporte = DB::table('eegr_activos_fijos_depreciaciones AS d')
      ->join('eegr_activos_fijos_unidades AS actfUnid', 'd.activof_unidad', '=', 'actfUnid.id')
      ->join("main_empresas AS emp", "actfUnid.empresa", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'actfUnid.token_activof_unidad' => $token_activof_unidad,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select(
        'd.periodo',
        DB::raw("SUM(CASE WHEN d.tipo = 'contable' THEN d.importe ELSE 0 END) as contable_monto"),
        DB::raw("SUM(CASE WHEN d.tipo = 'fiscal' THEN d.importe ELSE 0 END) as fiscal_monto"),
        DB::raw("MAX(CASE WHEN d.tipo = 'contable' THEN d.valor_libros_final ELSE 0 END) as contable_libros")
      )
      ->groupBy('d.periodo')
      ->get();

      $reporte->transform(function($item) {
        $item->fecha_legible = date('M Y', $item->periodo);
        $item->periodo = gmdate('Y-m-d H:i:s', $item->periodo);
        return $item;
      });
      //return response()->json($reporte);

      $dataMensaje = array(
        'code' => 200,
        'status' => 'success',
        'depreciaciones' => $reporte
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}