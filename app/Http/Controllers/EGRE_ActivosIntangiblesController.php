<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\ActivosIntangiblesModelo;
use App\Models\ProveedoresModelo;
use App\Models\UsoCFDIModelo;
use Illuminate\Support\Str;

class EGRE_ActivosIntangiblesController extends Controller{
  public function registroActivoIntang(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'categoria' => 'required|string',
      'categoriaCuentaContable' => 'required|string',
      'amortizacionContablePeriodo' => 'nullable|string',
      'amortizacionContableTiempoEjecucion' => 'nullable|string',
      'amortizacionContableCuentaUno' => 'required|string',
      'amortizacionContableCuentaDos' => 'required|string',
      'amortizacionFiscalPeriodo' => 'nullable|string',
      'amortizacionFiscalTiempoEjecucion' => 'nullable|string',
      'amortizacionFiscalCuentaUno' => 'required|string',
      'amortizacionFiscalCuentaDos' => 'required|string',
      'observaciones' => 'required|string'
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
      $categoria = $request->input('categoria');
      $categoriaCuentaContable = $request->input('categoriaCuentaContable');
      $amortizacionContablePeriodo = $request->input('amortizacionContablePeriodo');
      $amortizacionContableTiempoEjecucion = $request->input('amortizacionContableTiempoEjecucion');
      $amortizacionContableCuentaUno = $request->input('amortizacionContableCuentaUno');
      $amortizacionContableCuentaDos = $request->input('amortizacionContableCuentaDos');
      $amortizacionFiscalPeriodo = $request->input('amortizacionFiscalPeriodo');
      $amortizacionFiscalTiempoEjecucion = $request->input('amortizacionFiscalTiempoEjecucion');
      $amortizacionFiscalCuentaUno = $request->input('amortizacionFiscalCuentaUno');
      $amortizacionFiscalCuentaDos = $request->input('amortizacionFiscalCuentaDos');
      $observaciones = $request->input('observaciones');
      
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
        DB::beginTransaction();
        try {
          //da_te_default_timezone_set($vEmp->zona_horaria);
  
          $ultimoActivo = DB::table('eegr_activos_intangibles_catalogo as actd')
          ->join('main_empresas as emp', 'actd.administrador', '=', 'emp.id')
          ->join('main_empresa_usuario as empuser', 'emp.id', '=', 'empuser.empresa')
          ->join('teci_usuarios_catalogo as users', 'empuser.usuario', '=', 'users.id')
          ->where('emp.empresa_token', $empresa)
          ->where('users.usuario_token', $usuario)
          ->select('actd.id', 'actd.folio_activo', 'actd.subfolio_activo')
          ->orderBy('actd.folio_activo', 'desc')
          ->lockForUpdate() // <--- Evita duplicados en concurrencia
          ->first();
          
          // 2. Inicializar valores por defecto (por si no existen registros previos)
          $folio_nuevo = 1;
          $post_folio  = null;
          
          if ($ultimoActivo) {
            $siguienteFolio = $ultimoActivo->folio_activo + 1;
            // Si alcanzó el límite, se genera el post_folio y se reinicia el folio principal
            if ($siguienteFolio === 1000000000) {
              // Nota: Cambié 'nomina_subfolio' por 'subfolio_activo' ya que en tu consulta original 
              // seleccionabas 'subfolio_activo' pero abajo intentabas leer una propiedad inexistente.
              $post_folio  = $JwtAuth->generarPostFolio($ultimoActivo->subfolio_activo);
              $folio_nuevo = 1;
            } else {
              $folio_nuevo = $siguienteFolio;
            }
          }
          $folio_activo = 'ACTD-' . $JwtAuth->generarFolio($folio_nuevo) . (!is_null($post_folio) ? '-' . $post_folio : '');
          $fechaAlta = time();
          $tokenAct = $JwtAuth->encriptarToken($categoria, $categoriaCuentaContable,rand(0, 500)).Str::uuid()->toString();
          $newActivo = new ActivosIntangiblesModelo();
          $newActivo->token_act_intang = $tokenAct;
          $newActivo->folio_activo = $folio_nuevo;
          $newActivo->subfolio_activo = $post_folio;
          $newActivo->fechaAlta = $fechaAlta;
          $newActivo->categoria = $JwtAuth->encriptar($categoria);
          $newActivo->categoria_cuenta_contable = $categoriaCuentaContable;
  
          $newActivo->amortizacion_contable_periodo = $amortizacionContablePeriodo;
          $newActivo->amortizacion_contable_tiempo_ejecucion = $amortizacionContableTiempoEjecucion;
          $newActivo->amortizacion_contable_cuenta = $amortizacionContableCuentaUno;
          $newActivo->amortizacion_contable_cuenta_dos = $amortizacionContableCuentaDos;
          $newActivo->amortizacion_fiscal_periodo = $amortizacionFiscalPeriodo;
          $newActivo->amortizacion_fiscal_tiempo_ejecucion = $amortizacionFiscalTiempoEjecucion;
          $newActivo->amortizacion_fiscal_cuenta = $amortizacionFiscalCuentaUno;
          $newActivo->amortizacion_fiscal_cuenta_dos = $amortizacionFiscalCuentaDos;
          $newActivo->activo_observaciones = $JwtAuth->encriptar($observaciones);
          $newActivo->status = TRUE;
          $newActivo->administrador = $vEmp->id;
          $savednewActivo = $newActivo->save();
          
          DB::commit();
          
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => "Activo registrado satisfactoriamente con el folio $folio_activo"
          );
        } catch (\Exception $e) {
          DB::rollBack();
          return response()->json([
            'status'  => 'error',
            'code'    => 500,
            'message' => 'Este activo no fue registrado debido a problemas internos, comuniquese a soporte para más información'
          ], 500);
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function procesaActivoDiferidoLista($dataActivos,$JwtAuth){
    $activos_procesados = array();
    $periodos = [86400 => 'Por día',604800 => 'Por semana',2629743 => 'Por mes',31556926 => 'Por año'];

    $contador = 1;
    foreach ($dataActivos as $vActivos) {
      //da_te_default_timezone_set($vActivos->zona_horaria);
      $detalles_relacionados = DB::table('eegr_activos_fijos_detalle AS actdet')
      ->join("eegr_activos_fijos_catalogo AS actf", "actdet.activo_fijo", "=", "actf.id")
      ->where("actf.token_act_fijos", $vActivos->token_act_fijos)
      ->count();

      $arrayEach = array(
        "num_act" => $contador,
        "token_act_intang" => $vActivos->token_act_intang,
        "folio_activo" => "ACTD-".$JwtAuth->generarFolio($vActivos->folio_activo).(!is_null($vActivos->subfolio_activo) ? '-'.$vActivos->subfolio_activo : ''),
        "fechaAlta" => $JwtAuth->mostrarUnixAFechaMexico($vActivos->fechaAlta),
        "categoria" => $JwtAuth->desencriptar($vActivos->categoria),
        "categoria_cuenta_contable" => $vActivos->categoria_cuenta_contable,

        "amortizacion_contable_periodo" => $periodos[$vActivos->amortizacion_contable_periodo] ?? '',
        "amortizacion_contable_tiempo_ejecucion" => $vActivos->amortizacion_contable_tiempo_ejecucion ?? '',
        "amortizacion_contable_cuenta" => $vActivos->amortizacion_contable_cuenta ?? '',
        "amortizacion_contable_cuenta_dos" => $vActivos->amortizacion_contable_cuenta_dos ?? '',
        
        "amortizacion_fiscal_periodo" => $periodos[$vActivos->amortizacion_fiscal_periodo] ?? '',
        "amortizacion_fiscal_tiempo_ejecucion" => $vActivos->amortizacion_fiscal_tiempo_ejecucion ?? '',
        "amortizacion_fiscal_cuenta" => $vActivos->amortizacion_fiscal_cuenta ?? '',
        "amortizacion_fiscal_cuenta_dos" => $vActivos->amortizacion_fiscal_cuenta_dos ?? '',

        "activo_observaciones" => $JwtAuth->desencriptar($vActivos->activo_observaciones),
        "puede_eliminar" => $detalles_relacionados == 0 ? true : false,
      );
      ++$contador;
      $activos_procesados[] = $arrayEach;
    }
    return $activos_procesados;
  }

  public function getListActIntangibles(Request $request){
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
      
      $queryActivos = ActivosIntangiblesModelo::join("main_empresas AS emp", "eegr_activos_intangibles_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_activos_intangibles_catalogo.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("eegr_activos_intangibles_catalogo.fechaAlta", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('eegr_activos_intangibles_catalogo.id', 'DESC')
      ->get();

      if ($queryActivos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $datosActivo = $this->procesaActivoDiferidoLista($queryActivos,$JwtAuth);

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'datosActivo' => $datosActivo,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function getListActIntangiblesCompras(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'cant_art_prorrateo' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $cant_art_prorrateo = $request->input('cant_art_prorrateo');
      
      $listActivos = ActivosIntangiblesModelo::join("main_empresas AS emp", "eegr_activos_intangibles_catalogo.administrador", "=", "emp.id")
      //join("eegr_activos_intangibles_clasificacion", "eegr_activos_intangibles_catalogo.categoria", "eegr_activos_intangibles_clasificacion.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_activos_intangibles_catalogo.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->get();

      if ($listActivos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayActivosInt = array();
        
        foreach ($listActivos as $valActivos) {
          //emp.e_moneda_code,emp.e_moneda_decimales
          switch ($valActivos->categoria) {
            case 'act_cat_1':
              $categoria = 'Marcas comerciales y nombres de dominio';
              break;
            case 'act_cat_2':
              $categoria = 'Patentes, derechos de autor y secretos comerciales';
              break;
            case 'act_cat_3':
              $categoria = 'Software y tecnología';
              break;
            case 'act_cat_3':
              $categoria = 'Contratos y acuerdos comerciales';
              break;
            case 'act_cat_5':
              $categoria = 'Relaciones con clientes y proveedores';
              break;
            case 'act_cat_6':
              $categoria = 'Conocimiento y habilidades especializadas de los empleados';
              break;
            case 'act_cat_7':
              $categoria = 'Reputación de la marca y prestigio de la empresa';
              break;
            case 'act_cat_8':
              $categoria = 'Derechos de explotación de franquicias y licencias';
              break;
            default:
              $categoria = null;
              break;
          }
  
          $selectdetalleCompra = DB::select("SELECT comp.folio_compra,detcomp.token_detcompra,detcomp.precio_unitario,detcomp.cantidad,detcomp.descuento,
                        detcomp.retenciones_total,detcomp.traslados_total,
                        IF (detcomp.producto in (SELECT id FROM in_egr_catalogo_productos),
                            (SELECT producto FROM in_egr_catalogo_productos WHERE id = detcomp.producto),'') AS concepto_producto,
                        IF (detcomp.producto in (SELECT id FROM in_egr_catalogo_productos),
                            (SELECT marca FROM in_egr_catalogo_productos WHERE id = detcomp.producto),'') AS marca_producto,
                        IF (detcomp.servicio in (SELECT id FROM in_egr_catalogo_servicios),
                            (SELECT servicio FROM in_egr_catalogo_servicios WHERE id = detcomp.servicio),'') AS concepto_servicio 
                        FROM eegr_compras AS comp
                        JOIN eegr_compras_detalle AS detcomp
                        JOIN eegr_activos_intangibles_catalogo AS act_intang
                        JOIN main_empresas AS emp 
                        JOIN main_empresa_usuario AS empuser 
                        JOIN teci_usuarios_catalogo AS users 
                        WHERE detcomp.prorrateo = FALSE
                        AND detcomp.activo_intangible = act_intang.id
                        AND act_intang.token_act_intang = ?
                        AND comp.id = detcomp.numero_compra
                        AND detcomp.empresa = emp.id 
                        AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa 
                        AND empuser.usuario = users.id 
                        AND users.usuario_token = ?",
            [$valActivos->token_act_intang, $empresa, $usuario]
          );
  
          if (count($selectdetalleCompra) > 0) {
            $totalCompra = 0;
            $resultCompratotal = 0;
            foreach ($selectdetalleCompra as $resDetCompra) {
              $token_detcompra = $resDetCompra->token_detcompra;
  
              $totalDetComp = DB::select("SELECT 
                                TRUNCATE(((SUM(precio_unitario*cantidad) - SUM(descuento*cantidad)) -
                                SUM(retenciones_total)) + SUM(traslados_total),?) AS total
                                FROM eegr_compras_detalle WHERE token_detcompra = ?", [$valActivos->e_moneda_decimales, $token_detcompra]);
  
              $totalDetCompFormat = DB::select("SELECT 
                                FORMAT(((SUM(precio_unitario*cantidad) - SUM(descuento*cantidad)) -
                                SUM(retenciones_total)) + SUM(traslados_total),?) AS total
                                FROM eegr_compras_detalle WHERE token_detcompra = ?", [$valActivos->e_moneda_decimales, $token_detcompra]);
  
              if ($resDetCompra->concepto_producto != '') {
                $articulo = $JwtAuth->desencriptar($resDetCompra->concepto_producto) . " - " . $JwtAuth->desencriptar($resDetCompra->marca_producto);
              }
  
              if ($resDetCompra->concepto_servicio != '') {
                $articulo = $JwtAuth->desencriptar($resDetCompra->concepto_servicio);
              }
  
              $formatPuRetTras = DB::select(
                "SELECT FORMAT(?,?) AS formatPunit,FORMAT(?,?) AS formatDescuento,FORMAT(?,?) AS formatRetenc,FORMAT(?,?) AS formatTraslad",
                [
                  $resDetCompra->precio_unitario,
                  $valActivos->e_moneda_decimales,
                  $resDetCompra->descuento,
                  $valActivos->e_moneda_decimales,
                  $resDetCompra->retenciones_total,
                  $valActivos->e_moneda_decimales,
                  $resDetCompra->traslados_total,
                  $valActivos->e_moneda_decimales
                ]
              );
  
              $arrayEachDetalleCompra = array(
                "token_act_intang" => $valActivos->token_act_intang,
                "categoria" => $categoria,
                "amortizacion" => $JwtAuth->desencriptar($valActivos->amortizacion_contable) . " / " . $JwtAuth->desencriptar($valActivos->amortizacion_fiscal),
  
                "articulo" => $articulo,
                "cantidad" => $resDetCompra->cantidad,
                "descuento" => "$" . $formatPuRetTras[0]->formatDescuento,
                "precio_unitario" => $formatPuRetTras[0]->formatPunit,
                "token_detcompra" => $token_detcompra,
                "total" => $totalDetComp[0]->total,
                "totalDetCompFormat" => "$" . $totalDetCompFormat[0]->total,
                "retenciones_total" => "$" . $formatPuRetTras[0]->formatRetenc,
                "traslados_total" => "$" . $formatPuRetTras[0]->formatTraslad,
                "totalCompra" => "",
                "totalProrrateo" => "",
                "desvioProrrateo" => "",
              );
              $arrayActivosInt[] = $arrayEachDetalleCompra;
              $totalCompra = $totalCompra + $totalDetComp[0]->total;
            }
            for ($i = 0; $i < count($arrayActivosInt); $i++) {
              $arrayActivosInt[$i]["totalCompra"] = $totalCompra;
              $prorrateoUno = $cant_art_prorrateo * ($arrayActivosInt[$i]["total"] / $totalCompra);
              $prorrateoDos = $prorrateoUno / $arrayActivosInt[$i]["cantidad"];
  
              $arrayActivosInt[$i]["totalProrrateo"] = $prorrateoUno;
              $arrayActivosInt[$i]["desvioProrrateo"] = $prorrateoDos;
            }
          }
        }
  
        $dataMensaje = array(
          'datosActivo' => $arrayActivosInt,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function verActivoIntang(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_intang' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_act_intang = $request->input('token_act_intang');
      
      $queryActivos = ActivosIntangiblesModelo::join("main_empresas AS emp", "eegr_activos_intangibles_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_activos_intangibles_catalogo.token_act_intang' => $token_act_intang,
        'eegr_activos_intangibles_catalogo.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->get();

      if ($queryActivos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $periodos = [86400 => 'Por día',604800 => 'Por semana',2629743 => 'Por mes',31556926 => 'Por año'];
        $arrayActivosFVig = array();

        foreach ($queryActivos as $vActivos) {
          //da_te_default_timezone_set($vActivos->zona_horaria);
          $detalles_relacionados = DB::table('eegr_activos_fijos_detalle AS actdet')
          ->join("eegr_activos_fijos_catalogo AS actf", "actdet.activo_fijo", "=", "actf.id")
          ->where("actf.token_act_fijos", $vActivos->token_act_intang)
          ->count();
          
          $arrayEach = array(
            "token_act_intang" => $vActivos->token_act_intang,
            "folio_activo" => "ACTD-".$JwtAuth->generarFolio($vActivos->folio_activo).(!is_null($vActivos->subfolio_activo) ? '-'.$vActivos->subfolio_activo : ''),
            "fechaAlta" => $JwtAuth->mostrarUnixAFechaMexico($vActivos->fechaAlta),
            "categoria" => $JwtAuth->desencriptar($vActivos->categoria),
            "categoria_cuenta_contable" => $vActivos->categoria_cuenta_contable,

            "amortizacion_contable_periodo" => $vActivos->amortizacion_contable_periodo ?? '',
            "amortizacion_contable_periodo_no_edit" => $periodos[$vActivos->amortizacion_contable_periodo] ?? '',
            "amortizacion_contable_tiempo_ejecucion" => $vActivos->amortizacion_contable_tiempo_ejecucion ?? '',
            "amortizacion_contable_cuenta" => $vActivos->amortizacion_contable_cuenta ?? '',
            "amortizacion_contable_cuenta_dos" => $vActivos->amortizacion_contable_cuenta_dos ?? '',

            "amortizacion_fiscal_periodo" => $vActivos->amortizacion_fiscal_periodo ?? '',
            "amortizacion_fiscal_periodo_no_edit" => $periodos[$vActivos->amortizacion_fiscal_periodo] ?? '',
            "amortizacion_fiscal_tiempo_ejecucion" => $vActivos->amortizacion_fiscal_tiempo_ejecucion ?? '',
            "amortizacion_fiscal_cuenta" => $vActivos->amortizacion_fiscal_cuenta ?? '',
            "amortizacion_fiscal_cuenta_dos" => $vActivos->amortizacion_fiscal_cuenta_dos ?? '',

            "activo_observaciones" => $JwtAuth->desencriptar($vActivos->activo_observaciones),
            "puede_editar" => $detalles_relacionados == 0 ? true : false,
            "puede_eliminar" => $detalles_relacionados == 0 ? true : false,
          );
          $arrayActivosFVig[] = $arrayEach;
        }

        $dataMensaje = array(
          'datosActivo' => $arrayActivosFVig,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizageneralesActivoIntang(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_intang' => 'required|string',
      'categoria' => 'required|string',
      'categoriaCuentaContable' => 'required|string',
      'amortizacionContablePeriodo' => 'nullable|string',
      'amortizacionContableTiempoEjecucion' => 'nullable|string',
      'amortizacionContableCuentaUno' => 'required|string',
      'amortizacionContableCuentaDos' => 'required|string',
      'amortizacionFiscalPeriodo' => 'nullable|string',
      'amortizacionFiscalTiempoEjecucion' => 'nullable|string',
      'amortizacionFiscalCuentaUno' => 'required|string',
      'amortizacionFiscalCuentaDos' => 'required|string',
      'observaciones' => 'required|string'
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
      $token_act_intang = $request->input('token_act_intang');
      $categoria = $request->input('categoria');
      $categoriaCuentaContable = $request->input('categoriaCuentaContable');
      $amortizacionContablePeriodo = $request->input('amortizacionContablePeriodo');
      $amortizacionContableTiempoEjecucion = $request->input('amortizacionContableTiempoEjecucion');
      $amortizacionContableCuentaUno = $request->input('amortizacionContableCuentaUno');
      $amortizacionContableCuentaDos = $request->input('amortizacionContableCuentaDos');
      $amortizacionFiscalPeriodo = $request->input('amortizacionFiscalPeriodo');
      $amortizacionFiscalTiempoEjecucion = $request->input('amortizacionFiscalTiempoEjecucion');
      $amortizacionFiscalCuentaUno = $request->input('amortizacionFiscalCuentaUno');
      $amortizacionFiscalCuentaDos = $request->input('amortizacionFiscalCuentaDos');
      $observaciones = $request->input('observaciones');
      
      $OKActfToken = isset($token_act_intang) && !empty($token_act_intang);
      $OKCategoria = isset($categoria) && !empty($categoria) && preg_match($JwtAuth->filtroAlfaNumerico(), $categoria);
      $OKCategoriaCuenta = isset($categoriaCuentaContable) && !empty($categoriaCuentaContable) && preg_match($JwtAuth->filtroAlfaNumerico(), $categoriaCuentaContable);
      
      $OKAmortContPeriodo = isset($amortizacionContablePeriodo) && !empty($amortizacionContablePeriodo) && preg_match($JwtAuth->filtroAlfaNumerico(), $amortizacionContablePeriodo);
      $OKAmortContTiempoEjecucion = isset($amortizacionContableTiempoEjecucion) && !empty($amortizacionContableTiempoEjecucion) && preg_match($JwtAuth->filtroNumerico(), $amortizacionContableTiempoEjecucion);
      $OKAmortContCuenta = isset($amortizacionContableCuentaUno) && !empty($amortizacionContableCuentaUno) && preg_match($JwtAuth->filtroAlfaNumerico(), $amortizacionContableCuentaUno);
      $OKAmortContCuentaDos = isset($amortizacionContableCuentaDos) && !empty($amortizacionContableCuentaDos) && preg_match($JwtAuth->filtroAlfaNumerico(), $amortizacionContableCuentaDos);
      
      $OKAmortFiscalPeriodo = isset($amortizacionFiscalPeriodo) && !empty($amortizacionFiscalPeriodo) && preg_match($JwtAuth->filtroAlfaNumerico(), $amortizacionFiscalPeriodo);
      $OKAmortFiscalTiempoEjecucion = isset($amortizacionFiscalTiempoEjecucion) && !empty($amortizacionFiscalTiempoEjecucion) && preg_match($JwtAuth->filtroNumerico(), $amortizacionFiscalTiempoEjecucion);
      $OKAmortFiscalCuenta = isset($amortizacionFiscalCuentaUno) && !empty($amortizacionFiscalCuentaUno) && preg_match($JwtAuth->filtroAlfaNumerico(), $amortizacionFiscalCuentaUno);
      $OKAmortFiscalCuentaDos = isset($amortizacionFiscalCuentaDos) && !empty($amortizacionFiscalCuentaDos) && preg_match($JwtAuth->filtroAlfaNumerico(), $amortizacionFiscalCuentaDos);
      $OKObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones);

      if ($OKActfToken && $OKCategoria && $OKCategoriaCuenta && $OKAmortContCuenta && $OKAmortContCuentaDos && $OKAmortFiscalCuenta && $OKAmortFiscalCuentaDos && $OKObservacion) {
        $listActivos = ActivosIntangiblesModelo::join("main_empresas AS emp", "eegr_activos_intangibles_catalogo.administrador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'eegr_activos_intangibles_catalogo.token_act_intang' => $token_act_intang,
          'eegr_activos_intangibles_catalogo.status' => TRUE,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])->get();
        foreach ($listActivos as $vActivos) {
          $folio_activo = "ACTD-".$JwtAuth->generarFolio($vActivos->folio_activo).(!is_null($vActivos->subfolio_activo) ? '-'.$vActivos->subfolio_activo : '');
          $upDateActivo = ActivosIntangiblesModelo::where('token_act_intang',$vActivos->token_act_intang)
          ->limit(1)->update(array(
            "categoria" => $JwtAuth->encriptar($categoria),
            "categoria_cuenta_contable" => $categoriaCuentaContable,
            "amortizacion_contable_periodo" => $OKAmortContPeriodo ? $amortizacionContablePeriodo : NULL,
            "amortizacion_contable_tiempo_ejecucion" => $OKAmortContTiempoEjecucion ? $amortizacionContableTiempoEjecucion : NULL,
            "amortizacion_contable_cuenta" => $amortizacionContableCuentaUno,
            "amortizacion_contable_cuenta_dos" => $amortizacionContableCuentaDos,
            
            "amortizacion_fiscal_periodo" => $OKAmortFiscalPeriodo ? $amortizacionFiscalPeriodo : NULL,
            "amortizacion_fiscal_tiempo_ejecucion" => $OKAmortFiscalTiempoEjecucion ? $amortizacionFiscalTiempoEjecucion : NULL,
            "amortizacion_fiscal_cuenta" => $amortizacionFiscalCuentaUno,
            "amortizacion_fiscal_cuenta_dos" => $amortizacionFiscalCuentaDos,
            "activo_observaciones" => $JwtAuth->encriptar($observaciones),
          ));
  
          if ($upDateActivo) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Datos generales del activo con folio $folio_activo han sido actualizados satisfactoriamente"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 404,
              'message' => 'Datos generales de este activo no fueron actualizados debido a problemas internos, comuniquese a soporte para más información'
            );
          }
        }
      } else {
        $mensaje_error = "";
        if (!$OKActfToken) $mensaje_error = "Error al seleccionar activo, intentelo nuevamente o comuniquese a soporte";
        if (!$OKCategoria) $mensaje_error = "Error al registrar categoría, intentelo nuevamente o comuniquese a soporte";
        if (!$OKCategoriaCuenta) $mensaje_error = "Error al registrar cuenta contable de categoría, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAmortContPeriodo) $mensaje_error = "Error al registrar periodo de amortización contable, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAmortContTiempoEjecucion) $mensaje_error = "Error al registrar tiempo de amortización contable, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAmortContCuenta) $mensaje_error = "Error al registrar cuenta contable de amortización contable, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAmortContCuentaDos) $mensaje_error = "Error al registrar la segunda cuenta contable de amortización contable, intentelo nuevamente o comuniquese a soporte";

        if (!$OKAmortFiscalPeriodo) $mensaje_error = "Error al registrar periodo de amortización fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAmortFiscalTiempoEjecucion) $mensaje_error = "Error al registrar tiempo de amortización fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAmortFiscalCuenta) $mensaje_error = "Error al registrar cuenta contable de amortización fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKAmortFiscalCuentaDos) $mensaje_error = "Error al registrar la segunda cuenta contable de amortización fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKObservacion) $mensaje_error = "Error al registrar número de nómina, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteActivoIntang(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_intang' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_act_intang = $request->input('token_act_intang');
      
      $obtenCompraServ = DB::select(
        "SELECT * FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_productos AS catprod 
                  JOIN eegr_activos_intangibles_catalogo AS act_intang JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                  JOIN teci_usuarios_catalogo AS users WHERE detcomp.producto = catprod.id AND catprod.activo = act_intang.id 
                  AND act_intang.token_act_intang = ? AND act_intang.administrador = emp.id AND emp.empresa_token = ? 
                  AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
        [$token_act_intang, $empresa, $usuario]
      );

      if (count($obtenCompraServ) == 0) {
        $prodDeleteList = ActivosIntangiblesModelo::join("main_empresas AS emp", "eegr_activos_intangibles_catalogo.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'eegr_activos_intangibles_catalogo.token_act_intang' => $token_act_intang,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              'eegr_activos_intangibles_catalogo.fecha_delete_act' => time(),
              'eegr_activos_intangibles_catalogo.status' => FALSE
            )
          );

        if ($prodDeleteList) {
          return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'activo eliminado satisfactoriamente'
          ]);
        } else {
          return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => 'activo no eliminado'
          ]);
        }
      } else {
        return response()->json([
          'status' => 'error',
          'code' => 404,
          'message' => 'activo no eliminado, esta vinculado a compras'
        ]);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function getActivosIntangDeleted(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryActivos = ActivosIntangiblesModelo::join("main_empresas AS emp", "eegr_activos_intangibles_catalogo.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      'eegr_activos_intangibles_catalogo.status' => FALSE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])->get();

    if ($queryActivos->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron activos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayActivosFdel = $this->procesaActivoDiferidoLista($queryActivos,$JwtAuth);

      $dataMensaje = array(
        'datosActivo' => $arrayActivosFdel,
        'code' => 200,
        'status' => 'success'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restartActivosIntang(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_intang' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_act_intang = $request->input('token_act_intang');
      
      $actRestartList = ActivosIntangiblesModelo::join("main_empresas AS emp", "eegr_activos_intangibles_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_activos_intangibles_catalogo.token_act_intang' => $token_act_intang,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->limit(1)->update(
        array(
          'eegr_activos_intangibles_catalogo.fecha_delete_act' => '',
          'eegr_activos_intangibles_catalogo.status' => TRUE
        )
      );

      if ($actRestartList) {
        return response()->json([
          'status' => 'success',
          'code' => 200,
          'message' => 'activo restaurado satisfactoriamente'
        ]);
      } else {
        return response()->json([
          'status' => 'error',
          'code' => 404,
          'message' => 'activo no restaurado'
        ]);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteDeadActivosIntang(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_intang' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_act_intang = $request->input('token_act_intang');
      
      $obtenCompraServ = DB::select(
        "SELECT * FROM eegr_compras_detalle AS detcomp JOIN in_egr_catalogo_productos AS catprod JOIN eegr_activos_intangibles_catalogo AS act_intang 
                  JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE detcomp.producto = catprod.id 
                  AND catprod.activo = act_intang.id AND act_intang.token_act_intang = ? AND act_intang.administrador = emp.id AND emp.empresa_token = ? 
                  AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
        [$token_act_intang, $empresa, $usuario]
      );

      if (count($obtenCompraServ) == 0) {
        $provactLista = ActivosIntangiblesModelo::join("eegr_activos_intangibles_proveedor AS clavintan", "eegr_activos_intangibles_catalogo.id", "=", "clavintan.activo")
          ->where([
            'eegr_activos_intangibles_catalogo.token_act_intang' => $token_act_intang
          ])->count();

        if ($provactLista != 0) {
          $deleteactprov = ActivosIntangiblesModelo::join("eegr_activos_intangibles_proveedor AS clavintan", "eegr_activos_intangibles_catalogo.id", "=", "clavintan.activo")
            ->where([
              'eegr_activos_intangibles_catalogo.token_act_intang' => $token_act_intang
            ])->limit(1)->delete();
          if ($deleteactprov) {
            $prodDeleteList = ActivosIntangiblesModelo::join("main_empresas AS emp", "eegr_activos_intangibles_catalogo.administrador", "=", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
              ->where([
                'eegr_activos_intangibles_catalogo.token_act_intang' => $token_act_intang,
                'emp.empresa_token' => $empresa,
                'users.usuario_token' => $usuario,
              ])
              ->limit(1)->delete();

            if ($prodDeleteList) {
              return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'activo eliminado satisfactoriamente'
              ]);
            } else {
              return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'activo no eliminado'
              ]);
            }
          } else {
            return response()->json([
              'status' => 'error',
              'code' => 404,
              'message' => 'relación de activo con proveedor no eliminada'
            ]);
          }
        } else {
          $prodDeleteList = ActivosIntangiblesModelo::join("main_empresas AS emp", "eegr_activos_intangibles_catalogo.administrador", "=", "emp.id")
            ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
            ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
            ->where([
              'eegr_activos_intangibles_catalogo.token_act_intang' => $token_act_intang,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario,
            ])
            ->limit(1)->delete();

          if ($prodDeleteList) {
            return response()->json([
              'status' => 'success',
              'code' => 200,
              'message' => 'activo eliminado satisfactoriamente'
            ]);
          } else {
            return response()->json([
              'status' => 'error',
              'code' => 404,
              'message' => 'activo no eliminado'
            ]);
          }
        }
      } else {
        return response()->json([
          'status' => 'error',
          'code' => 404,
          'message' => 'activo no eliminado, esta vinculado a compras'
        ]);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //respaldos
  public function actualizaProvClavesActivoIntang(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_intang' => 'required|string',
      'tknProveedor' => 'required|string',
      'activo_claveTkn' => 'required|string',
      'clave' => 'required|string',
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
      $token_act_intang = $request->input('token_act_intang');
      $tknProveedor = $request->input('tknProveedor');
      $activo_claveTkn = $request->input('activo_claveTkn');
      $clave = $request->input('clave');
      
      $obtenProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$tknProveedor]);

      if (count($obtenProv) == 1) {
        $upDateServicio = DB::table('activo_prove')
          ->join("activos_intangibles AS actIntang", "activo_prove.activo", "=", "actIntang.id")
          ->join("main_empresas AS emp", "actIntang.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'activo_prove.token_actintang_claves' => $activo_claveTkn,
            'activo_prove.proveedor' => $obtenProv[0]->id,
            'actIntang.status' => TRUE,
            'actIntang.token_act_intang' => $token_act_intang,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->update(
            array(
              "activo_prove.identificador" => $JwtAuth->encriptar($clave),
            )
          );

        if ($upDateServicio) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Relación de proveedor con este activo actualizada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 404,
            'message' => 'Relación de proveedor con este activo no fue actualizada debido a problemas internos, comuniquese a soporte para más información'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'proveedor inexistente'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function newProvClavesActivoIntang(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_intang' => 'required|string',
      'tknProveedor' => 'required|string',
      'clave' => 'required|string',
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
      $token_act_intang = $request->input('token_act_intang');
      $tknProveedor = $request->input('tknProveedor');
      $clave = $request->input('clave');
      
      $obtenActivo = DB::select("SELECT id FROM activos_intangibles WHERE token_act_intang = ?", [$token_act_intang]);
      $obtenProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$tknProveedor]);
      $tkn_clavesServ = $JwtAuth->encriptarToken(time(), $token_act_intang, $tknProveedor);

      if (count($obtenProv) == 1) {
        $insertActivo = DB::table('activo_prove')
          ->insert(array(
            "token_actintang_claves" =>  $tkn_clavesServ,
            "activo" => $obtenActivo[0]->id,
            "proveedor" => $obtenProv[0]->id,
            "identificador" => $JwtAuth->encriptar($clave),
          ));

        if ($insertActivo) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Relación de proveedor con este activo guradada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 404,
            'message' => 'Relación de proveedor con este activo no fue guardada debido a problemas internos, comuniquese a soporte para más información'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'proveedor inexistente'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteProvClavesActivoIntang(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_intang' => 'required|string',
      'tknProveedor' => 'required|string',
      'tkn_act_clave' => 'required|string'
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
      $token_act_intang = $request->input('token_act_intang');
      $tknProveedor = $request->input('tknProveedor');
      $tkn_act_clave = $request->input('tkn_act_clave');
      
      $obtenActivo = DB::select("SELECT id FROM activos_intangibles WHERE token_act_intang = ?", [$token_act_intang]);
      $obtenProv = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$tknProveedor]);

      if (count($obtenProv) == 1 && count($obtenActivo) == 1) {
        $deleteactivo_prove = DB::table('activo_prove')
          ->where([
            "token_actintang_claves" => $tkn_act_clave,
            "activo" => $obtenActivo[0]->id,
            "proveedor" => $obtenProv[0]->id
          ])
          ->limit(1)->delete();

        if ($deleteactivo_prove) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Relación de proveedor con este activo eliminada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 404,
            'message' => 'Relación de proveedor con este activo no fue eliminada debido a problemas internos, comuniquese a soporte para más información'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'proveedor inexistente'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
  
  public function listaclassActIntangibles(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryLysta = DB::table("eegr_activos_intangibles_clasificacion AS actClass")
    ->join("main_empresas AS emp", "actClass.empresa", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])->get();

    if ($queryLysta->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron activos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaclassIntang = array();
      
      foreach ($queryLysta as $resClAct) {
        if ($resClAct->imagen == 'default_prod.jpg') {
          $logo_activo = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $resClAct->imagen));
        } else {
          $logo_activo = $JwtAuth->encriptaBase64(Storage::path('public/root/' .
            $resClAct->root_tkn . '/0002-cpp/catalogos/activos/activos_intangibles/clasificacion/' .
            $JwtAuth->generar($resClAct->folio) . '-' . $resClAct->fechaRegAlta . '/' . $JwtAuth->desencriptar($resClAct->imagen) . '.png'));
        }
        $lista = array(
          "token_clasificacion_intang" => $resClAct->token_clasificacion_intang,
          "folio" => $JwtAuth->generar($resClAct->folio),
          "concepto" => $JwtAuth->desencriptar($resClAct->concepto),
          "amort_contable" => $JwtAuth->desencriptar($resClAct->amort_contable),
          "amort_fiscal" => $JwtAuth->desencriptar($resClAct->amort_fiscal),
          "codigo" => $resClAct->codigo,
          "logo_activo" => $logo_activo,
        );
        $listaclassIntang[] = $lista;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'datosActivo' => $listaclassIntang
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function agregaClassActivoIntang(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'cActConcepto' => 'required|string',
      'cActContable' => 'required|string',
      'cActFiscal' => 'required|string',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $cActConcepto = $JwtAuth->encriptar($request->input('cActConcepto'));
      $cActContable = $JwtAuth->encriptar($request->input('cActContable'));
      $cActFiscal = $JwtAuth->encriptar($request->input('cActFiscal'));
      
      $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,emp.zona_horaria FROM main_empresas AS emp  
        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
        [$empresa, $usuario]
      );
      //echo $selectEmp[0]->id;
      //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

      $folioActAclass = DB::select("SELECT COUNT(actClass.id) AS folio FROM eegr_activos_intangibles_clasificacion AS actClass 
        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE actClass.empresa = emp.id 
        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",
        [$empresa, $usuario]
      );

      $token_clasificacion_intang = $JwtAuth->encriptarToken($folioActAclass[0]->folio + 1, $cActConcepto, $cActContable, $cActFiscal);

      $insertActClass = DB::table('eegr_activos_intangibles_clasificacion')
      ->insert(array(
        "token_clasificacion_intang" => $token_clasificacion_intang,
        "folio" => $folioActAclass[0]->folio + 1,
        "concepto" => $cActConcepto,
        "amort_contable" => $cActContable,
        "amort_fiscal" => $cActFiscal,
        "codigo" => $folioActAclass[0]->folio + 1,
        "imagen" => $JwtAuth->encriptar($JwtAuth->generar($folioActAclass[0]->folio + 1) . "-" . time()),
        "empresa" => $selectEmp[0]->id
      ));

      if ($insertActClass) {
        $filepath = $selectEmp[0]->root_tkn . "/egresos/catalogos/activos/activos_intangibles/clasificacion/" .
          $JwtAuth->generar($folioActAclass[0]->folio + 1) . "-" . time() . "/"; // or image.jpg
        //mkdir($filepath,0777);
        $folder = public_path($filepath);
        /*Storage::putFileAs(storage_path("/root/".$filepath), $imageServ,'pruebsa');*/
        if (!file_exists(storage_path("/root/" . $filepath))) {
          //Storage::disk('public')->makeDirectory('/storage/root/'.$filepath,0777, true, true);
          Storage::disk('root')->makeDirectory($filepath, 0777, true, true);

          // Finalmente guarda la imágen en el directorio especificado y con la informacion dada                
          Storage::putFileAs(
            "/public/root/" . $filepath,
            $request->file('imgActClassCaarga'),
            $JwtAuth->generar($folioActAclass[0]->folio + 1) . "-" . time() . ".png"
          );
        } else {
          // Finalmente guarda la imágen en el directorio especificado y con la informacion dada
          Storage::putFileAs(
            "/public/root/" . $filepath,
            $request->file('imgActClassCaarga'),
            $JwtAuth->generar($folioActAclass[0]->folio + 1) . "-" . time() . ".png"
          );
        }
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'Esta clasificación ha sido registrada satisfactoriamente'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'Esta clasificación no fue registrada debido a problemas internos'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}