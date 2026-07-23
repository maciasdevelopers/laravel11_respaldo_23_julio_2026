<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ActivosFijosModelo;

class EGRE_ActivosFijosController extends Controller{
  public function registroActivoFijo(Request $request){
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
      'depreciacionContableTipo' => 'required|string',
      'depreciacionContablePeriodo' => 'required|string',
      'depreciacionContableImporte' => 'required|numeric',
      'depreciacionContableCuenta' => 'required|string',
      'depreciacionContableCuentaDos' => 'required|string',
      'depreciacionFiscalTipo' => 'required|string',
      'depreciacionFiscalPeriodo' => 'required|string',
      'depreciacionFiscalImporte' => 'required|numeric',
      'depreciacionFiscalCuenta' => 'required|string',
      'depreciacionFiscalCuentaDos' => 'required|string',
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
      $depreciacionContableTipo = $request->input('depreciacionContableTipo');
      $depreciacionContablePeriodo = $request->input('depreciacionContablePeriodo');
      $depreciacionContableImporte = $request->input('depreciacionContableImporte');
      $depreciacionContableCuenta = $request->input('depreciacionContableCuenta');
      $depreciacionContableCuentaDos = $request->input('depreciacionContableCuentaDos');
      $depreciacionFiscalTipo = $request->input('depreciacionFiscalTipo');
      $depreciacionFiscalPeriodo = $request->input('depreciacionFiscalPeriodo');
      $depreciacionFiscalImporte = $request->input('depreciacionFiscalImporte');
      $depreciacionFiscalCuenta = $request->input('depreciacionFiscalCuenta');
      $depreciacionFiscalCuentaDos = $request->input('depreciacionFiscalCuentaDos');
      $observaciones = $request->input('observaciones');
      //return response()->json(['message' => $categoriaCuentaContable,'code' => 200,'status' => 'error']);

      $OKCategoria = isset($categoria) && !empty($categoria) && preg_match($JwtAuth->filtroAlfaNumerico(), $categoria);
      $OKCategoriaCuenta = isset($categoriaCuentaContable) && !empty($categoriaCuentaContable) && preg_match($JwtAuth->filtroAlfaNumerico(), $categoriaCuentaContable);
      $OKDeprecContTipo = isset($depreciacionContableTipo) && !empty($depreciacionContableTipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionContableTipo);
      $OKDeprecContPeriodo = isset($depreciacionContablePeriodo) && !empty($depreciacionContablePeriodo) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionContablePeriodo);
      $OKDeprecContImporte = isset($depreciacionContableImporte) && !empty($depreciacionContableImporte) && preg_match($JwtAuth->filtroNumerico(), $depreciacionContableImporte);
      $OKDeprecContCuenta = isset($depreciacionContableCuenta) && !empty($depreciacionContableCuenta) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionContableCuenta);
      $OKDeprecContCuentaDos = isset($depreciacionContableCuentaDos) && !empty($depreciacionContableCuentaDos) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionContableCuentaDos);

      $OKDeprecFiscalTipo = isset($depreciacionFiscalTipo) && !empty($depreciacionFiscalTipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionFiscalTipo);
      $OKDeprecFiscalPeriodo = isset($depreciacionFiscalPeriodo) && !empty($depreciacionFiscalPeriodo) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionFiscalPeriodo);
      $OKDeprecFiscalImporte = isset($depreciacionFiscalImporte) && !empty($depreciacionFiscalImporte) && preg_match($JwtAuth->filtroNumerico(), $depreciacionFiscalImporte);
      $OKDeprecFiscalCuenta = isset($depreciacionFiscalCuenta) && !empty($depreciacionFiscalCuenta) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionFiscalCuenta);
      $OKDeprecFiscalCuentaDos = isset($depreciacionFiscalCuentaDos) && !empty($depreciacionFiscalCuentaDos) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionFiscalCuentaDos);
      $OKObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones);

      if ($OKCategoria && $OKCategoriaCuenta && $OKDeprecContTipo && $OKDeprecContPeriodo && $OKDeprecContImporte && $OKDeprecContCuenta && $OKDeprecContCuentaDos &&
        $OKDeprecFiscalTipo && $OKDeprecFiscalPeriodo && $OKDeprecFiscalImporte && $OKDeprecFiscalCuenta && $OKDeprecFiscalCuentaDos && $OKObservacion) {
        $fechaSistema = time();
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
          WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

        foreach ($queryEmp as $vEmp) {
          $ultimoActivo = ActivosFijosModelo::where('administrador', $vEmp->id)
          ->lockForUpdate() 
          ->orderBy('id', 'desc') // Asumimos que ID desc es el último cronológico
          ->first();
          // 2. Calculamos el nuevo folio
          $folio_nuevo = 1;
          $post_folio = null; // El sufijo (A, B, C...)
      
          if ($ultimoActivo) {
            $folio_nuevo = $ultimoActivo->folio_activo + 1;
            $post_folio = $ultimoActivo->subfolio_activo;
      
            // 3. Validamos el límite de 1,000,000,000
            if ($folio_nuevo >= 1000000000) {
              $folio_nuevo = 1;
              $post_folio = $JwtAuth->generarPostFolio($post_folio); 
            }
          }

          $folio_activo = 'ACTF-' . $JwtAuth->generarFolio($folio_nuevo) . (!is_null($post_folio) ? '-' . $post_folio : '');
          $depreciacionTipo = $depreciacionContableTipo . $depreciacionFiscalTipo;
          $depreciacionPeriodo = $depreciacionContablePeriodo . $depreciacionFiscalPeriodo;
          $depreciacionImporte = $depreciacionContableImporte . $depreciacionFiscalImporte;
          $tokenAct = $JwtAuth->encriptarToken($categoria, $depreciacionTipo, $depreciacionPeriodo, $depreciacionImporte, $observaciones, rand(0, 500));

          $newActivo = new ActivosFijosModelo();
          $newActivo->token_act_fijos = $tokenAct;
          $newActivo->folio_activo = $folio_nuevo;
          $newActivo->subfolio_activo = $post_folio;
          $newActivo->fechaAlta = time();
          $newActivo->categoria = $categoria;
          $newActivo->categoria_cuenta_contable = $categoriaCuentaContable;
          $newActivo->deprec_contable_tipo = $depreciacionContableTipo;
          $newActivo->deprec_contable_periodo = $depreciacionContablePeriodo;
          $newActivo->deprec_contable_importe = $depreciacionContableImporte;
          $newActivo->deprec_contable_cuenta = $depreciacionContableCuenta;
          $newActivo->deprec_contable_cuenta_dos = $depreciacionContableCuentaDos;
          
          $newActivo->deprec_fiscal_tipo = $depreciacionFiscalTipo;
          $newActivo->deprec_fiscal_periodo = $depreciacionFiscalPeriodo;
          $newActivo->deprec_fiscal_importe = $depreciacionFiscalImporte;
          $newActivo->deprec_fiscal_cuenta = $depreciacionFiscalCuenta;
          $newActivo->deprec_fiscal_cuenta_dos = $depreciacionFiscalCuentaDos;
          $newActivo->activo_observaciones = $JwtAuth->encriptar($observaciones);
          $newActivo->activo_status = TRUE;
          $newActivo->administrador = $vEmp->id;
          $savednewActivo = $newActivo->save();
          if ($savednewActivo) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Activo registrado satisfactoriamente con el folio $folio_activo"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 404,
              'message' => 'El activo no fue registrado debido a problemas internos, comuniquese a soporte para más información'
            );
          }
        }
      } else {
        $mensaje_error = "";
        if (!$OKCategoria) $mensaje_error = "Error al registrar categoría, intentelo nuevamente o comuniquese a soporte";
        if (!$OKCategoriaCuenta) $mensaje_error = "Error al registrar cuenta contable de categoría, intentelo nuevamente o comuniquese a soporte";

        if (!$OKDeprecContTipo) $mensaje_error = "Error al registrar tipo de depreciación contable, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecContPeriodo) $mensaje_error = "Error al registrar periodo de depreciación contable, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecContImporte) $mensaje_error = "Error al registrar importe de depreciación contable, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecContCuenta) $mensaje_error = "Error al registrar cuenta contable de depreciación contable, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecContCuentaDos) $mensaje_error = "Error al registrar la segunda cuenta contable de depreciación contable, intentelo nuevamente o comuniquese a soporte";

        if (!$OKDeprecFiscalTipo) $mensaje_error = "Error al registrar tipo de depreciación fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecFiscalPeriodo) $mensaje_error = "Error al registrar periodo de depreciación fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecFiscalImporte) $mensaje_error = "Error al registrar importe de depreciación fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecFiscalCuenta) $mensaje_error = "Error al registrar cuenta contable de depreciación fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecFiscalCuentaDos) $mensaje_error = "Error al registrar la segunda cuenta contable de depreciación fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKObservacion) $mensaje_error = "Error al registrar número de nómina, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  private function procesaActivoFijoLista($dataActivos,$JwtAuth){
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

      $arrayEach = array(
        "num_act" => $contador,
        "token_act_fijos" => $vActivos->token_act_fijos,
        "folio_activo" => "ACTF-".$JwtAuth->generarFolio($vActivos->folio_activo),
        "fechaAlta" => $JwtAuth->mostrarUnixAFechaMexico($vActivos->fechaAlta),
        "categoria" => $JwtAuth->desencriptar($vActivos->categoria),
        "categoria_cuenta_contable" => $vActivos->categoria_cuenta_contable,
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
      );
      ++$contador;
      $activos_procesados[] = $arrayEach;
    }
    return $activos_procesados;
  }

  public function getActivosFijosCatalogo(Request $request){
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
      
      $queryActivos = ActivosFijosModelo::join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_activos_fijos_catalogo.activo_status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->addSelect('eegr_activos_fijos_catalogo.*','emp.zona_horaria') // Aseguramos traer los datos del activo
      ->addSelect(DB::raw('(SELECT COUNT(*) FROM eegr_activos_fijos_detalle WHERE eegr_activos_fijos_detalle.activo_fijo = eegr_activos_fijos_catalogo.id) as conteo_detalles'))
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("eegr_activos_fijos_catalogo.fechaAlta", [$fechaInicio, $fechaFin]);
      })
      ->orderBy('eegr_activos_fijos_catalogo.id', 'DESC')
      ->get();

      if ($queryActivos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'datosActivo' => $this->procesaActivoFijoLista($queryActivos,$JwtAuth)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function verActivoFijo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_fijos' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_act_fijos = $request->input('token_act_fijos');
      $periodos = [86400 => 'Por día',604800 => 'Por semana',2629743 => 'Por mes',31556926 => 'Por año'];

      $listActivos = ActivosFijosModelo::join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_activos_fijos_catalogo.token_act_fijos' => $token_act_fijos,
        'eegr_activos_fijos_catalogo.activo_status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->get();
      
      if ($listActivos->isEmpty()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'No se encontraron activos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $arrayActivosFVig = array();
        foreach ($listActivos as $vActivos) {
          //da_te_default_timezone_set($vActivos->zona_horaria);
          $deprec_contable_importe = $vActivos->deprec_contable_tipo == 'cuota' ? "$".number_format($vActivos->deprec_contable_importe,$JwtAuth->getMonedaAPI('MXN'),'.', ',')." MXN" : number_format($vActivos->deprec_contable_importe,$JwtAuth->getMonedaAPI('MXN'),'.','').'%';
          $deprec_fiscal_importe = $vActivos->deprec_fiscal_tipo == 'cuota' ? "$".number_format($vActivos->deprec_fiscal_importe,$JwtAuth->getMonedaAPI('MXN'),'.', ',')." MXN" : number_format($vActivos->deprec_fiscal_importe,$JwtAuth->getMonedaAPI('MXN'),'.','').'%';
          $detalles_relacionados = DB::table('eegr_activos_fijos_detalle AS actdet')
          ->join("eegr_activos_fijos_catalogo AS actf", "actdet.activo_fijo", "=", "actf.id")
          ->where("actf.token_act_fijos", $vActivos->token_act_fijos)
          ->count();
          
          $arrayEach = array(
            "token_act_fijos" => $vActivos->token_act_fijos,
            "folio_activo" => "ACTF-".$JwtAuth->generarFolio($vActivos->folio_activo),
            "fechaAlta" => $JwtAuth->mostrarUnixAFechaMexico($vActivos->fechaAlta),
            "categoria" => $JwtAuth->desencriptar($vActivos->categoria),
            "categoria_cuenta_contable" => $vActivos->categoria_cuenta_contable,

            "deprec_contable_tipo" => $vActivos->deprec_contable_tipo,
            "deprec_contable_periodo_no_edit" => $periodos[$vActivos->deprec_contable_periodo] ?? '',
            "deprec_contable_periodo" => $vActivos->deprec_contable_periodo,
            "deprec_contable_importe_no_edit" => $deprec_contable_importe,
            "deprec_contable_importe" => rtrim(rtrim($vActivos->deprec_contable_importe, '0'), '.'),
            "deprec_contable_cuenta" => $vActivos->deprec_contable_cuenta,
            "deprec_contable_cuenta_dos" => $vActivos->deprec_contable_cuenta_dos,

            "deprec_fiscal_tipo" => $vActivos->deprec_fiscal_tipo,
            "deprec_fiscal_periodo_no_edit" => $periodos[$vActivos->deprec_fiscal_periodo] ?? '',
            "deprec_fiscal_periodo" => $vActivos->deprec_fiscal_periodo,
            "deprec_fiscal_importe_no_edit" => $deprec_fiscal_importe,
            "deprec_fiscal_importe" => rtrim(rtrim($vActivos->deprec_fiscal_importe, '0'), '.'),
            "deprec_fiscal_cuenta" => $vActivos->deprec_fiscal_cuenta,
            "deprec_fiscal_cuenta_dos" => $vActivos->deprec_fiscal_cuenta_dos,

            "activo_observaciones" => $JwtAuth->desencriptar($vActivos->activo_observaciones),
            "puede_editar" => $detalles_relacionados == 0 ? true : false,
          );
          $arrayActivosFVig[] = $arrayEach;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'datosActivo' => $arrayActivosFVig,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizageneralesActivoFijo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_fijos' => 'required|string',
      'categoria' => 'required|string',
      'categoriaCuentaContable' => 'required|string',
      'depreciacionContableTipo' => 'required|string',
      'depreciacionContablePeriodo' => 'required|string',
      'depreciacionContableImporte' => 'required|numeric',
      'depreciacionContableCuenta' => 'required|string',
      'depreciacionContableCuentaDos' => 'required|string',
      'depreciacionFiscalTipo' => 'required|string',
      'depreciacionFiscalPeriodo' => 'required|string',
      'depreciacionFiscalImporte' => 'required|numeric',
      'depreciacionFiscalCuenta' => 'required|string',
      'depreciacionFiscalCuentaDos' => 'required|string',
      'observaciones' => 'required|string',
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
      $token_act_fijos = $request->input('token_act_fijos');
      $categoria = $request->input('categoria');
      $categoriaCuentaContable = $request->input('categoriaCuentaContable');
      $depreciacionContableTipo = $request->input('depreciacionContableTipo');
      $depreciacionContablePeriodo = $request->input('depreciacionContablePeriodo');
      $depreciacionContableImporte = $request->input('depreciacionContableImporte');
      $depreciacionContableCuenta = $request->input('depreciacionContableCuenta');
      $depreciacionContableCuentaDos = $request->input('depreciacionContableCuentaDos');
      $depreciacionFiscalTipo = $request->input('depreciacionFiscalTipo');
      $depreciacionFiscalPeriodo = $request->input('depreciacionFiscalPeriodo');
      $depreciacionFiscalImporte = $request->input('depreciacionFiscalImporte');
      $depreciacionFiscalCuenta = $request->input('depreciacionFiscalCuenta');
      $depreciacionFiscalCuentaDos = $request->input('depreciacionFiscalCuentaDos');
      $observaciones = $request->input('observaciones');

      $OKActfToken = isset($token_act_fijos) && !empty($token_act_fijos);
      $OKCategoria = isset($categoria) && !empty($categoria) && preg_match($JwtAuth->filtroAlfaNumerico(), $categoria);
      $OKCategoriaCuenta = isset($categoriaCuentaContable) && !empty($categoriaCuentaContable) && preg_match($JwtAuth->filtroAlfaNumerico(), $categoriaCuentaContable);
      
      $OKDeprecContTipo = isset($depreciacionContableTipo) && !empty($depreciacionContableTipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionContableTipo);
      $OKDeprecContPeriodo = isset($depreciacionContablePeriodo) && !empty($depreciacionContablePeriodo) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionContablePeriodo);
      $OKDeprecContImporte = isset($depreciacionContableImporte) && !empty($depreciacionContableImporte) && preg_match($JwtAuth->filtroNumerico(), $depreciacionContableImporte);
      $OKDeprecContCuenta = isset($depreciacionContableCuenta) && !empty($depreciacionContableCuenta) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionContableCuenta);
      $OKDeprecContCuentaDos = isset($depreciacionContableCuentaDos) && !empty($depreciacionContableCuentaDos) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionContableCuentaDos);
      
      $OKDeprecFiscalTipo = isset($depreciacionFiscalTipo) && !empty($depreciacionFiscalTipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionFiscalTipo);
      $OKDeprecFiscalPeriodo = isset($depreciacionFiscalPeriodo) && !empty($depreciacionFiscalPeriodo) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionFiscalPeriodo);
      $OKDeprecFiscalImporte = isset($depreciacionFiscalImporte) && !empty($depreciacionFiscalImporte) && preg_match($JwtAuth->filtroNumerico(), $depreciacionFiscalImporte);
      $OKDeprecFiscalCuenta = isset($depreciacionFiscalCuenta) && !empty($depreciacionFiscalCuenta) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionFiscalCuenta);
      $OKDeprecFiscalCuentaDos = isset($depreciacionFiscalCuentaDos) && !empty($depreciacionFiscalCuentaDos) && preg_match($JwtAuth->filtroAlfaNumerico(), $depreciacionFiscalCuentaDos);
      $OKObservacion = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $observaciones);

      if ($OKActfToken && $OKCategoria && $OKCategoriaCuenta && $OKDeprecContTipo && $OKDeprecContPeriodo && $OKDeprecContImporte && $OKDeprecContCuenta && $OKDeprecContCuentaDos &&
        $OKDeprecFiscalTipo && $OKDeprecFiscalPeriodo && $OKDeprecFiscalImporte && $OKDeprecFiscalCuenta && $OKDeprecFiscalCuentaDos && $OKObservacion) {
        $listActivos = ActivosFijosModelo::join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'eegr_activos_fijos_catalogo.token_act_fijos' => $token_act_fijos,
          'eegr_activos_fijos_catalogo.activo_status' => TRUE,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])->get();
        foreach ($listActivos as $vActivos) {
          $folio_activo = "ACTF-".$JwtAuth->generarFolio($vActivos->folio_activo).(!is_null($vActivos->subfolio_activo) ? '-'.$vActivos->subfolio_activo : '');
          $upDateActivo = ActivosFijosModelo::where('token_act_fijos',$vActivos->token_act_fijos)
          ->limit(1)->update(array(
            "categoria" => $JwtAuth->encriptar($categoria),
            "categoria_cuenta_contable" => $categoriaCuentaContable,
            "deprec_contable_tipo" => $depreciacionContableTipo,
            "deprec_contable_periodo" => $depreciacionContablePeriodo,
            "deprec_contable_importe" => $depreciacionContableImporte,
            "deprec_contable_cuenta" => $depreciacionContableCuenta,
            "deprec_contable_cuenta_dos" => $depreciacionContableCuentaDos,
            "deprec_fiscal_tipo" => $depreciacionFiscalTipo,
            "deprec_fiscal_periodo" => $depreciacionFiscalPeriodo,
            "deprec_fiscal_importe" => $depreciacionFiscalImporte,
            "deprec_fiscal_cuenta" => $depreciacionFiscalCuenta,
            "deprec_fiscal_cuenta_dos" => $depreciacionFiscalCuentaDos,
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
        if (!$OKDeprecContTipo) $mensaje_error = "Error al registrar tipo de depreciación contable, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecContPeriodo) $mensaje_error = "Error al registrar periodo de depreciación contable, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecContImporte) $mensaje_error = "Error al registrar importe de depreciación contable, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecContCuenta) $mensaje_error = "Error al registrar cuenta contable de depreciación contable, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecContCuentaDos) $mensaje_error = "Error al registrar la segunda cuenta contable de depreciación contable, intentelo nuevamente o comuniquese a soporte";

        if (!$OKDeprecFiscalTipo) $mensaje_error = "Error al registrar tipo de depreciación fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecFiscalPeriodo) $mensaje_error = "Error al registrar periodo de depreciación fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecFiscalImporte) $mensaje_error = "Error al registrar importe de depreciación fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecFiscalCuenta) $mensaje_error = "Error al registrar cuenta contable de depreciación fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKDeprecFiscalCuentaDos) $mensaje_error = "Error al registrar la segunda cuenta contable de depreciación fiscal, intentelo nuevamente o comuniquese a soporte";
        if (!$OKObservacion) $mensaje_error = "Error al registrar número de nómina, intentelo nuevamente o comuniquese a soporte";
        $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteActivoFijo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_fijos' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_act_fijos = $request->input('token_act_fijos');
      
      $obtenCompraServ = DB::table("eegr_compras_detalle AS detcomp")
      ->join("in_egr_catalogo_productos AS catprod", "detcomp.producto", "=", "catprod.id")
      ->join("eegr_activos_fijos_catalogo AS act_fijo", "catprod.activo", "=", "act_fijo.id")
      ->join("main_empresas AS emp", "act_fijo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'act_fijo.token_act_fijos' => $token_act_fijos,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->get();
      
      if ($obtenCompraServ->isEmpty()) {
        $prodDeleteList = ActivosFijosModelo::join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
        ->where([
          'eegr_activos_fijos_catalogo.token_act_fijos' => $token_act_fijos,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario,
        ])
        ->limit(1)->update(
          array(
            'eegr_activos_fijos_catalogo.activo_status' => FALSE,
            'eegr_activos_fijos_catalogo.fecha_delete_act' => time()
          )
        );

        if ($prodDeleteList) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'activo eliminado satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 404,
            'message' => 'activo no eliminado'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'activo no eliminado, esta vinculado a compras'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function getActivosFijosDeleted(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $periodos = [86400 => 'Por día',604800 => 'Por semana',2629743 => 'Por mes',31556926 => 'Por año'];

    $listActivos = ActivosFijosModelo::join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where([
      'eegr_activos_fijos_catalogo.activo_status' => FALSE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])
    ->orderBy('eegr_activos_fijos_catalogo.id', 'DESC')->get();

    if ($listActivos->isEmpty()) {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'No se encontraron activos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $arrayActivosFdel = array();
      
      foreach ($listActivos as $vActivos) {
        //da_te_default_timezone_set($vActivos->zona_horaria);
        $deprec_contable_importe = $vActivos->deprec_contable_tipo == 'cuota' ? "$".number_format($vActivos->deprec_contable_importe,$JwtAuth->getMonedaAPI('MXN'),'.', ',')." MXN" : number_format($vActivos->deprec_contable_importe,$JwtAuth->getMonedaAPI('MXN'),'.','').'%';
        $deprec_fiscal_importe = $vActivos->deprec_fiscal_tipo == 'cuota' ? "$".number_format($vActivos->deprec_fiscal_importe,$JwtAuth->getMonedaAPI('MXN'),'.', ',')." MXN" : number_format($vActivos->deprec_fiscal_importe,$JwtAuth->getMonedaAPI('MXN'),'.','').'%';

        $arrayEach = array(
          "token_act_fijos" => $vActivos->token_act_fijos,
          "folio_activo" => "ACTF-".$JwtAuth->generarFolio($vActivos->folio_activo),
          "fechaAlta" => $JwtAuth->mostrarUnixAFechaMexico($vActivos->fechaAlta),
          "categoria" => $JwtAuth->desencriptar($vActivos->categoria),
          "deprec_contable_tipo" => $vActivos->deprec_contable_tipo,
          "deprec_contable_periodo" => $periodos[$vActivos->deprec_contable_periodo] ?? '',
          "deprec_contable_importe" => $deprec_contable_importe,
          "deprec_contable_cuenta" => $vActivos->deprec_contable_cuenta,
          "deprec_fiscal_tipo" => $vActivos->deprec_fiscal_tipo,
          "deprec_fiscal_periodo" => $periodos[$vActivos->deprec_fiscal_periodo] ?? '',
          "deprec_fiscal_importe" => $deprec_fiscal_importe,
          "deprec_fiscal_cuenta" => $vActivos->deprec_fiscal_cuenta,
          "activo_observaciones" => $JwtAuth->desencriptar($vActivos->activo_observaciones),
          "fecha_delete" => $JwtAuth->mostrarUnixAFechaMexico($vActivos->fecha_delete_act),
        );
        $arrayActivosFdel[] = $arrayEach;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'datosActivo' => $arrayActivosFdel,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restartActivosFijos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_fijos' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_act_fijos = $request->input('token_act_fijos');
      
      $actRestartList = ActivosFijosModelo::join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'eegr_activos_fijos_catalogo.token_act_fijos' => $token_act_fijos,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->limit(1)->update(
        array(
          'eegr_activos_fijos_catalogo.activo_status' => TRUE,
          'eegr_activos_fijos_catalogo.fecha_delete_act' => NULL
        )
      );

      if ($actRestartList) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'activo restaurado satisfactoriamente'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'activo no restaurado'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteDeadActivosFijos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_act_fijos' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_act_fijos = $request->input('token_act_fijos');

      $obtenCompraServ = DB::table("eegr_compras_detalle AS detcomp")
      ->join("in_egr_catalogo_productos AS catprod", "detcomp.producto", "=", "catprod.id")
      ->join("eegr_activos_fijos_catalogo AS act_fijo", "catprod.activo", "=", "act_fijo.id")
      ->join("main_empresas AS emp", "act_fijo.administrador", "=", "emp.id")
      ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
      ->where([
        'act_fijo.token_act_fijos' => $token_act_fijos,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario,
      ])
      ->get();
      
      if ($obtenCompraServ->isEmpty()) {
        $provactLista = ActivosFijosModelo::join("eegr_activos_fijos_claves AS clavact", "eegr_activos_fijos_catalogo.id", "=", "clavact.activo_fijo")
        ->where('eegr_activos_fijos_catalogo.token_act_fijos', $token_act_fijos)->count();

        if ($provactLista != 0) {
          $deleteactprov = ActivosFijosModelo::join("eegr_activos_fijos_claves AS clavact", "eegr_activos_fijos_catalogo.id", "=", "clavact.activo_fijo")
          ->where('eegr_activos_fijos_catalogo.token_act_fijos', $token_act_fijos)
          ->limit(1)->delete();
          
          if ($deleteactprov) {
            $prodDeleteList = ActivosFijosModelo::join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
              ->where([
                'eegr_activos_fijos_catalogo.token_act_fijos' => $token_act_fijos,
                'emp.empresa_token' => $empresa,
                'users.usuario_token' => $usuario,
              ])
              ->limit(1)->delete();

            if ($prodDeleteList) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'activo eliminado satisfactoriamente'
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'activo no eliminado'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 404,
              'message' => 'relación de activo con proveedor no eliminada'
            );
          }
        } else {
          $prodDeleteList = ActivosFijosModelo::join("main_empresas AS emp", "eegr_activos_fijos_catalogo.administrador", "=", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
          ->where([
            'eegr_activos_fijos_catalogo.token_act_fijos' => $token_act_fijos,
            'emp.empresa_token' => $empresa,
            'users.usuario_token' => $usuario,
          ])
          ->limit(1)->delete();

          if ($prodDeleteList) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'activo eliminado satisfactoriamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 404,
              'message' => 'activo no eliminado'
            );
          }
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'activo no eliminado, esta vinculado a compras'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}