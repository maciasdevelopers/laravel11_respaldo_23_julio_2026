<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\CajaModelo;
use App\Models\MovimientosBancariosModelo;

class FNZS_CajaController extends Controller{
  public function folioCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $JwtAuth = new \App\Helpers\JwtAuth();
    $folioCaja = DB::select("SELECT 
      IF (max(no_caja) IS NOT NULL,(max(no_caja)+1),1) AS folio
      FROM fnzs_catalogos_caja AS caj JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
      JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
      WHERE caj.empresa = emp.id AND emp.empresa_token = ?
      AND emp.id = empuser.empresa AND empuser.personal = pers.id
      AND pers.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

    return response()->json(['caja' => $JwtAuth->generar($folioCaja[0]->folio), 'codigo' => 200, 'status' => 'success']);
  }

  public function catalogoCajasActual(Request $request){
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
      //da_te_default_timezone_set('America/Mexico_City');
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
      
      $queryCaja = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "fnzs_catalogos_caja.almacen", "alm.id")
      ->join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("fnzs_catalogos_caja.fecha_alta_caja", [$fechaInicio, $fechaFin]);
      })
      ->orderby('fnzs_catalogos_caja.id', 'desc')
      ->get();

      if ($queryCaja->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron cajas registradas'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $listaCajas = array();

        foreach ($queryCaja as $resCaja) {
          $caja_folio = "CAJ-".$JwtAuth->generarFolio($resCaja->no_caja);
          $caja_alias = $JwtAuth->desencriptar($resCaja->alias_caja);
          $moneda_decimales = $JwtAuth->getMonedaAPI($resCaja->e_moneda_code);

          $caja_result_saldo = $this->saldoCajaByToken($resCaja->token_caja, $empresa);
          $row = array(
            "token_caja" => $resCaja->token_caja,
            "caja_folio" => $caja_folio,
            "caja_alias" => $caja_alias,
            "establecimiento" => $JwtAuth->desencriptar($resCaja->alias_establecimiento),
            //"usuario" => $JwtAuth->desencriptar('N2FXYXMwR0syOEVZNTZSV2svMHhvZz09OjoxMjM0NTY3ODEyMzQ1Njc4')
            "saldofloat" => $caja_result_saldo,
            "salDoCaja" => "$".number_format($caja_result_saldo,$moneda_decimales, '.', ',')." $resCaja->moneda_caja",
            "aplicable_disabled" => true,
            "select_for_pagos" => false,
            //"disponible" => $vSal->disponible ? true : false,
            "monto_aplicar" => 0,
            "_filtro_busqueda" => "$caja_folio $caja_alias",
          );
          $listaCajas[] = $row;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'caja' => $listaCajas,
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoCajasDeleted(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryCaja = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "fnzs_catalogos_caja.almacen", "alm.id")
    ->join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
    ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
    ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
    ->where([
      'fnzs_catalogos_caja.status' => FALSE,
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario
    ])->orderby('fnzs_catalogos_caja.fecha_delete_caja', 'DESC')
    ->get();

    if ($queryCaja->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron cajas registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaCajas = array();

      foreach ($queryCaja as $resCaja) {
        //da_te_default_timezone_set('America/Mexico_City');
        $row = array(
          "token_caja" => $resCaja->token_caja,
          "caja_folio" => "CAJ-" . $JwtAuth->generarFolio($resCaja->no_caja),
          "caja_alias" => $JwtAuth->desencriptar($resCaja->alias_caja),
          "establecimiento" => $JwtAuth->desencriptar($resCaja->alias_establecimiento),
          "fecha_delete" => gmdate('Y-m-d H:i:s', $resCaja->fecha_delete_caja)
        );
        $listaCajas[] = $row;
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'caja' => $listaCajas,
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function detalleCajaVig(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      //da_te_default_timezone_set('America/Mexico_City');
      $token_caja = $request->input('token_caja');

      $queryCaja = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "fnzs_catalogos_caja.almacen", "alm.id")
      ->join("teci_direcciones AS dir", "alm.id", "dir.establecimiento")
      ->join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.token_caja' => $token_caja,
        'fnzs_catalogos_caja.status' => TRUE,
        'dir.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->get();

      if ($queryCaja->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'La caja no existe'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $detalleCaja = array();
        $arrayResponsable = array();
        $arrayCorteCaja = array();
        
        foreach ($queryCaja as $resCaja) {
          $tknEstablecimiento = $resCaja->token_establecimiento;
          $cajEstab = DB::table('in_egr_establecimientos_catalogo')->where('token_establecimiento',$tknEstablecimiento)->get();
          $establecimiento_folio = 'ESTAB-'.$JwtAuth->generarFolio($cajEstab[0]->folio_establecimiento).($cajEstab[0]->post_folio != NULL ? '-'.$cajEstab[0]->post_folio : '');
          $establecimiento_alias = $JwtAuth->desencriptar($cajEstab[0]->alias_establecimiento);
          //Direccion de almacen seleccionada en el alta
          if ($resCaja->pais_code == 'MEX') {
            $dir_completa = "Colonia " . $JwtAuth->desencriptar($resCaja->colonia_edit).", C.P. ".$resCaja->c_postal_edit.", ".$JwtAuth->desencriptar($resCaja->municipio_edit).", ".$JwtAuth->desencriptar($resCaja->estado_edit).", Mexico/México";
          } else {
            $pais_en = "";
            $pais_es = "";
            $response = Http::get('https://insideapis.sos-mexico.com.mx/api/listaPaises');
            if ($response->successful()) {
              $datos = $response->json();
              $cantidadRegistros = is_array($datos) ? count($datos) : 0;
              $indice = array_search($resCaja->pais_code, array_column($datos["paises"], "code"));
              $pais_en = $datos["paises"][$indice]["langEN"];
              $pais_es = $datos["paises"][$indice]["langES"];
              //return response()->json(['message' => 'pais5'.$moneda_decimales,'codigo' => 200,'status' => 'error']);
            }
            $dir_completa = "Address " . $JwtAuth->desencriptar($resCaja->calle)
              . ", C.P. " . $JwtAuth->desencriptar($resCaja->cod_postalext) . ", $pais_en/$pais_es";
          }

          //Personal seleccionado en el alta
          $responsable = DB::select(
            "SELECT caj.token_caja,respAlm.token_responsables,respAlm.ocupacion,respAlm.turno_inicio,respAlm.turno_fin,people.paterno,people.materno,people.nombre,
            people.img_perfil,pers.folio_pers,pers.fecha_alta_pers,pers.empleado_token FROM in_egr_establecimientos_responsables AS respAlm JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_catalogo AS alm 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers
            JOIN sos_personas AS people JOIN teci_usuarios_catalogo AS users
            WHERE respAlm.almacen = alm.id AND alm.token_establecimiento = ? AND respAlm.caja = caj.id AND caj.token_caja = ? 
            AND respAlm.responsable = pers.id AND pers.empleado_name = people.id
            AND respAlm.administrador = emp.id AND emp.empresa_token = ?
            AND emp.id = empusers.empresa AND empusers.usuario = users.id AND users.usuario_token = ?",
            [$tknEstablecimiento, $token_caja, $empresa, $usuario]
          );

          foreach ($responsable as $vResp) {
            $statusAsigned = $token_caja == $vResp->token_caja ? true : false;
            $user_logo_text = $JwtAuth->desencriptar($vResp->img_perfil);
            $user_logo_path = 'public/root/main_users/' . $JwtAuth->generar($vResp->folio_pers) . '-' . $vResp->fecha_alta_pers . '/';
            $avatar = $JwtAuth->encriptaBase64(Storage::path($user_logo_text != 'default-profile.png' ? $user_logo_path . $user_logo_text . '-profile.png' : 'public/settings/default-profile.png'));

            $arrayRes = array(
              "token_responsables" => $vResp->token_responsables,
              "empleado_token" => $vResp->empleado_token,
              "ocupacion" => $vResp->ocupacion,
              "turno_inicio" => $vResp->turno_inicio,
              "turno_fin" => $vResp->turno_fin,
              "nombre_completo" => $JwtAuth->desencriptar($vResp->paterno) . " " . $JwtAuth->desencriptar($vResp->materno) . " " . $JwtAuth->desencriptar($vResp->nombre),
              "img_perfil" => $avatar,
              "statusAsigned" => $statusAsigned
            );
            $arrayResponsable[] = $arrayRes;
          }

          //Corte de caja seleccionada en el alta
          $corteCaja = DB::table('fnzs_catalogos_caja_corte_catalogo AS cort')
            ->join("fnzs_catalogos_caja AS caj", "cort.caja", "caj.id")
            ->join("main_empresas AS emp", "caj.empresa", "emp.id")
            ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
            ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
            ->where([
              'caj.token_caja' => $resCaja->token_caja,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario
            ])->get();

          foreach ($corteCaja as $resCorteCaja) {
            $arrayCorte = array(
              "token_cortecaja" => $resCorteCaja->token_cortecaja,
              "horario_corte" => $JwtAuth->desencriptar($resCorteCaja->horario_corte)
            );
            $arrayCorteCaja[] = $arrayCorte;
          }

          //Detalle de caja
          $arrayCaja = array(
            "token_caja" => $resCaja->token_caja,
            "caja_folio" => "CAJ-" . $JwtAuth->generarFolio($resCaja->no_caja),
            "alias" => $JwtAuth->desencriptar($resCaja->alias_caja),
            "moneda" => $resCaja->moneda_caja,
            "cuenta_contable" => !is_null($resCaja->cuenta_contable_caja) && $resCaja->cuenta_contable_caja != '' ? $resCaja->cuenta_contable_caja : '',
            "serv_egresos" => $resCaja->serv_egresos ? true : false,
            "serv_ingresos" => $resCaja->serv_ingresos ? true : false,
            "serv_interno" => $resCaja->serv_interno ? true : false,
            "capt_cliente" => $resCaja->capt_cliente ? true : false,
            "capt_precio_x_articulo" => $resCaja->capt_precio_x_articulo ? true : false,
            "capt_primero_cantidad" => $resCaja->capt_primero_cantidad ? true : false,
            "responsable" => !is_null($resCaja->encargado_principal) && $resCaja->encargado_principal != '' ? $JwtAuth->desencriptar($resCaja->encargado_principal) : '',
            "establecimiento_token" => $tknEstablecimiento,
            "establecimiento_folio" => $establecimiento_folio,
            "establecimiento_alias" => $establecimiento_alias,
            "establecimiento_direccion" => $dir_completa,
            "corte_caja" => $arrayCorteCaja
          );
          $detalleCaja[] = $arrayCaja;
        }

        $dataMensaje = array(
          'caja' => $detalleCaja,
          'code' => 200,
          'status' => 'success'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function respCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,people.nacionalidad FROM main_empresas AS emp JOIN sos_personas AS people 
      JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
      WHERE emp.empresa_token = ? AND emp.persona = people.id AND emp.id = empusers.empresa 
      AND empusers.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);

    $queryCaja = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "fnzs_catalogos_caja.almacen", "alm.id")
    ->join("teci_direcciones AS dirubica","alm.id","dirubica.establecimiento")
    ->join("in_egr_establecimientos_responsables AS respons", "fnzs_catalogos_caja.id", "respons.caja")
    ->join("vhum_empleados_catalogo AS persnl", "respons.responsable", "persnl.id")
    ->join("sos_personas AS people", "persnl.empleado_name", "people.id")
    ->join("teci_usuarios_catalogo AS users", "persnl.id", "users.empleado")
    ->where([
      "fnzs_catalogos_caja.serv_ingresos" => TRUE,
      "fnzs_catalogos_caja.empresa" => $selectEmp[0]->id,
      'users.usuario_token' => $usuario
    ])->get();

    if ($queryCaja->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron cajas registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $direccion = array();
      $caja = array();
      
      foreach ($queryCaja as $resCaja) {
        //echo $selectEmp[0]->nacionalidad." ".$resCaja->token_direccion;
        if ($selectEmp[0]->nacionalidad = 118) {
          $direccionAlmacen = DB::table('teci_direcciones AS diralm')
            ->join('teci_direcciones_codigos_postales AS cpostal', 'diralm.codigo_postal', 'cpostal.id')
            //->join('colonias AS col','diralm.cod_postal','col.id')
            //->join('deleg_mun AS delmun','diralm.delegacion_municipio','delmun.id')
            //->join('entidad_federativa AS entfed','diralm.ent_federativa','entfed.id')
            ->join('teci_pais AS detpais', 'diralm.pais', 'detpais.id')
            ->join("main_empresas AS emp", "diralm.administrador", "emp.id")
            ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
            ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
            ->where([
              'diralm.status' => TRUE,
              'diralm.tipo_direccion' => 'almacen',
              'diralm.token_direccion' => $resCaja->token_direccion,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario
            ])->get();

          $tknDireccion = $direccionAlmacen[0]->token_direccion;
          $tipoDireccion = $direccionAlmacen[0]->tipo_direccion;
          $clasifDireccion = $JwtAuth->desencriptar($direccionAlmacen[0]->clase);
          $aliasDireccion =  $JwtAuth->desencriptar($direccionAlmacen[0]->alias);

          if ($direccionAlmacen[0]->calle != '' && $direccionAlmacen[0]->calle != NULL) {
            $calle = $JwtAuth->desencriptar($direccionAlmacen[0]->calle);
          } else {
            $calle = 's/c';
          }

          if ($direccionAlmacen[0]->num_ext != '' && $direccionAlmacen[0]->num_ext != NULL) {
            $num_ext = $JwtAuth->desencriptar($direccionAlmacen[0]->num_ext);
          } else {
            $num_ext = 's/n';
          }

          if ($direccionAlmacen[0]->num_int != '' && $direccionAlmacen[0]->num_int != NULL) {
            $num_int = $JwtAuth->desencriptar($direccionAlmacen[0]->num_int);
          } else {
            $num_int = 's/n';
          }

          if ($direccionAlmacen[0]->calle1 != '' && $direccionAlmacen[0]->calle1 != NULL) {
            $calle1 = $JwtAuth->desencriptar($direccionAlmacen[0]->calle1);
          } else {
            $calle1 = 's/c';
          }

          if ($direccionAlmacen[0]->calle2 != '' && $direccionAlmacen[0]->calle2 != NULL) {
            $calle2 = $JwtAuth->desencriptar($direccionAlmacen[0]->calle2);
          } else {
            $calle2 = 's/c';
          }

          if ($direccionAlmacen[0]->referencia != '' && $direccionAlmacen[0]->referencia != NULL) {
            $referencia = $JwtAuth->desencriptar($direccionAlmacen[0]->referencia);
          } else {
            $referencia = 's/reg';
          }

          $dir_completa = "Calle " . $calle . " No. " . $num_ext . " Int." . $num_int .
            ", C.P. " . $direccionAlmacen[0]->codigo_postal .
            $direccionAlmacen[0]->tipo_asentamiento . " " .
            $direccionAlmacen[0]->asentamiento . ", " .
            $direccionAlmacen[0]->deleg_mun . ", " . $direccionAlmacen[0]->estado .
            ", ciudad " . $direccionAlmacen[0]->ciudad .
            ", " . $direccionAlmacen[0]->pais .
            ", entre " . $JwtAuth->desencriptar($direccionAlmacen[0]->calle1) .
            " y " . $JwtAuth->desencriptar($direccionAlmacen[0]->calle2) .
            " referencia " . $JwtAuth->desencriptar($direccionAlmacen[0]->referencia);
        } else {
          $queryDirAlmacenExt = DB::table('teci_direcciones AS diralm')
            ->join('teci_pais AS detpais', 'diralm.pais', 'detpais.id')
            ->join('main_empresas AS emp', 'diralm.administrador', 'emp.id')
            ->join('main_empresa_usuario AS empusers', 'emp.id', 'empusers.empresa')
            ->join('vhum_empleados_catalogo AS pers', 'empusers.personal', 'pers.id')
            ->join('teci_usuarios_catalogo AS users', 'pers.usuario', 'users.id')
            ->where([
              'diralm.status' => TRUE,
              'diralm.tipo_direccion' => 'almacen',
              'diralm.token_direccion' => $resCaja->token_direccion,
              'emp.empresa_token' => $empresa,
              'users.usuario_token' => $usuario
            ])
            ->get();

          $tknDireccion = $queryDirAlmacenExt[0]->token_direccion;
          $tipoDireccion = $direccionAlmacen[0]->tipo_direccion;
          $clasifDireccion = $JwtAuth->desencriptar($direccionAlmacen[0]->clase);
          $aliasDireccion =  $JwtAuth->desencriptar($direccionAlmacen[0]->alias);

          $dir_completa = "Alias: " . $JwtAuth->desencriptar($queryDirAlmacenExt[0]->alias)
            . ", Calle " . $JwtAuth->desencriptar($queryDirAlmacenExt[0]->calle)
            . ", C.P. " . $JwtAuth->desencriptar($queryDirAlmacenExt[0]->cod_postalext) . ", " . $queryDirAlmacenExt[0]->pais;
        }

        if ($JwtAuth->desencriptar($resCaja->img_perfil) == 'default-profile.png') {
          $avatar = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($resCaja->img_perfil)));
        } else {
          $avatar = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $selectEmp[0]->root_tkn .
            '/0004-vhm/catalogos/employees/' . $JwtAuth->generar($resCaja->folio_pers) . '-' .
            $resCaja->fecha_alta_pers . '/' . $JwtAuth->desencriptar($resCaja->img_perfil) . '-profile.png'));
        }

        $decimalesMoneda = DB::select(
          "SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.e_moneda = catmon.id AND emp.empresa_token = ?
                        AND emp.id = empusers.empresa AND empusers.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token = ?",
          [$empresa, $usuario]
        );

        //suman
        $cobroVenta = DB::select("SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_cobros_cobro AS cobrar JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE movim.tipo_movimiento = TRUE 
                            AND movim.subtipo_movimiento = 'V' 
                            AND movim.cobro = cobrar.id 
                            AND movim.caja = caj.id 
                            AND cobrar.caja = caj.id 
                            AND caj.token_caja = ?
                            AND movim.empresa = emp.id 
                            AND cobrar.empresa = emp.id
                            AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
                            AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
                            AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?", [$decimalesMoneda[0]->decimales, $resCaja->token_caja, $empresa, $usuario]);

        $devolucionCompra = DB::select("SELECT TRUNCATE(SUM(cobrar.monto_cobro),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_cobros_cobro AS cobrar JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp  
                            JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE movim.tipo_movimiento = FALSE AND movim.subtipo_movimiento = 'D' AND movim.cobro = cobrar.id 
                            AND movim.caja = caj.id AND cobrar.caja = caj.id AND caj.token_caja = ? AND movim.empresa = emp.id AND cobrar.empresa = emp.id 
                            AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
                            AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
                            AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?", [$decimalesMoneda[0]->decimales, $resCaja->token_caja, $empresa, $usuario]);

        //restan
        $pagoCompra = DB::select("SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE movim.tipo_movimiento = FALSE AND movim.subtipo_movimiento = 'C' AND movim.pago = payment.id 
                            AND movim.caja = caj.id AND payment.caja = caj.id AND caj.token_caja = ? AND movim.empresa = emp.id AND payment.empresa = emp.id
                            AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
                            AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
                            AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?", [$decimalesMoneda[0]->decimales, $resCaja->token_caja, $empresa, $usuario]);

        $devolucionVenta = DB::select("SELECT TRUNCATE(SUM(payment.monto_pago),?) AS total FROM fnzs_actividad_movimientos AS movim 
                            JOIN fnzs_pagos_pago AS payment JOIN fnzs_catalogos_caja AS caj JOIN in_egr_establecimientos_responsables AS alm_resp JOIN main_empresas AS emp 
                            JOIN main_empresa_usuario AS empusers JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE movim.tipo_movimiento = TRUE AND movim.subtipo_movimiento = 'D' AND movim.pago = payment.id 
                            AND movim.caja = caj.id AND payment.caja = caj.id AND caj.token_caja = ? AND movim.empresa = emp.id AND payment.empresa = emp.id
                            AND caj.empresa = emp.id AND alm_resp.administrador = emp.id AND emp.empresa_token = ?
                            AND emp.id = empusers.empresa AND empusers.personal = pers.id AND caj.id = alm_resp.caja
                            AND alm_resp.responsable = pers.id AND pers.usuario = users.id AND users.usuario_token = ?", [$decimalesMoneda[0]->decimales, $resCaja->token_caja, $empresa, $usuario]);

        $resultsalDoCaja = $cobroVenta[0]->total + $devolucionCompra[0]->total - $pagoCompra[0]->total - $devolucionVenta[0]->total;
        $salDoCaja = DB::select("SELECT FORMAT(?,?) AS saldo", [$resultsalDoCaja, $decimalesMoneda[0]->decimales]);

        $arrayCaja = array(
          "token_establecimiento" => $resCaja->token_establecimiento,
          "token_almacen" => $resCaja->token_establecimiento_almacen,
          "alias_almacen" => $JwtAuth->desencriptar($resCaja->alias),
          "token_direccion" => $tknDireccion,
          "tipoDireccion" => $tipoDireccion,
          "clasifDireccion" => $clasifDireccion,
          "aliasDireccion" => $aliasDireccion,
          "dir_completa" => $dir_completa,
          "latitud" => $resCaja->latitud,
          "longitud" => $resCaja->longitud,
          "pers_token" => $resCaja->token_responsables,
          "img_resp" =>  $avatar,
          "nombre" => $JwtAuth->desencriptar($resCaja->paterno) . " " . $JwtAuth->desencriptar($resCaja->materno) . " " . $JwtAuth->desencriptar($resCaja->nombre),

          "token_caja" => $resCaja->token_caja,
          "alias_caja" => $JwtAuth->desencriptar($resCaja->alias_caja),
          "caja" => $JwtAuth->generar($resCaja->no_caja),
          "saldofloat" => $salDoCaja[0]->saldo,
          "salDoCaja" => "$" . $salDoCaja[0]->saldo,
        );

        $caja[] = $arrayCaja;
      }

      $dataMensaje = array(
        'caja' => $caja,
        'code' => 200,
        'status' => 'success'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'moneda' => 'required|string',
      'establecimiento_token' => 'required|string',
      'descripcion' => 'required|string',
      'cuenta_contable' => 'required|string',
      'servegresos' => 'required|boolean',
      'servingresos' => 'required|boolean',
      'servpropias' => 'required|boolean',
      'capt_cliente' => 'required|boolean',
      'capt_precio_x_articulo' => 'required|boolean',
      'capt_primero_cantidad' => 'required|boolean',
      'vendedor' => 'string',
      //'turnos' => 'string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos que desea registrar',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $moneda = $request->input('moneda');
      $establecimiento_token = $request->input('establecimiento_token');
      $descripcion = $request->input('descripcion');
      $cuenta_contable = $request->input('cuenta_contable');
      $servegresos = $request->input('servegresos');
      $servingresos = $request->input('servingresos');
      $servpropias = $request->input('servpropias');
      $capt_cliente = $request->input('capt_cliente');
      $capt_precio_x_articulo = $request->input('capt_precio_x_articulo');
      $capt_primero_cantidad = $request->input('capt_primero_cantidad');
      $vendedor = $request->input('vendedor');

      $queryEmp = DB::table('main_empresas AS emp')
      ->join('main_empresa_usuario AS empuser', 'emp.id', '=', 'empuser.empresa')
      ->join('teci_usuarios_catalogo AS users', 'empuser.usuario', '=', 'users.id')
      ->where([
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->select('emp.id','emp.zona_horaria')
      ->get();
        
      if ($queryEmp->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontro la empresa seleccionada'
        );
      } else {
        foreach ($queryEmp as $vEmp) {
          $fecha_registro = time();
          //da_te_default_timezone_set($vEmp->zona_horaria);

          $listaDirAlmacen = DB::select("SELECT alm.id FROM in_egr_establecimientos_catalogo AS alm JOIN main_empresas AS emp JOIN main_empresa_usuario AS empusers 
            JOIN teci_usuarios_catalogo AS users WHERE alm.token_establecimiento = ? AND alm.empresa = emp.id AND emp.empresa_token = ?
            AND emp.id = empusers.empresa AND empusers.usuario = users.id AND users.usuario_token = ?",
            [$establecimiento_token, $empresa, $usuario]);
          //echo $listaDirAlmacen[0]->id;

          $folioCaja = DB::selectOne("SELECT COALESCE(MAX(fold.folder) + 1, 1) AS folio FROM sos_last_folders AS fold JOIN main_empresas AS emp ON fold.empresa = emp.id
            JOIN main_empresa_usuario AS empuser ON emp.id = empuser.empresa JOIN teci_usuarios_catalogo AS users ON empuser.usuario = users.id
            WHERE fold.fnzs_caja = TRUE AND emp.empresa_token = ? AND users.usuario_token = ?",
          [$empresa,$usuario]);
          $new_caja_folio = "CAJ-".$JwtAuth->generarFolio($folioCaja->folio);
          $tokenCaja = $JwtAuth->encriptarToken(time(), $listaDirAlmacen[0]->id, $descripcion);

          $caja = new CajaModelo();
          $caja->fecha_alta_caja = $fecha_registro;
          $caja->token_caja = $tokenCaja;
          $caja->no_caja = $folioCaja->folio;
          $caja->alias_caja = $JwtAuth->encriptar($descripcion);
          $caja->moneda_caja = $moneda;
          $caja->cuenta_contable_caja = $cuenta_contable;
          $caja->serv_egresos = $servegresos ? TRUE : FALSE;
          $caja->serv_ingresos = $servingresos ? TRUE : FALSE;
          $caja->serv_interno = $servpropias ? TRUE : FALSE;
          $caja->capt_cliente = $capt_cliente ? TRUE : FALSE;
          $caja->capt_precio_x_articulo = $capt_precio_x_articulo ? TRUE : FALSE;
          $caja->capt_primero_cantidad = $capt_primero_cantidad ? TRUE : FALSE;
          $caja->saldo_actual = '0.00';
          $caja->almacen = $listaDirAlmacen[0]->id;
          $caja->encargado_principal = !empty($vendedor) ? $JwtAuth->encriptar($vendedor) : NULL;
          $caja->fecha_delete_caja = '';
          $caja->status = TRUE;
          $caja->empresa = $vEmp->id;
          $savedCaja = $caja->save();
          if ($savedCaja) {
            $obtenCaja = $caja->id;

            if ($folioCaja->folio == 1) {
              $insertSistema = DB::table('sos_last_folders')
              ->insert(
                  array(
                    "fnzs_caja" => TRUE, 
                    "folder" => 1, 
                    "empresa" => $vEmp->id,
                  )
              );
            } else {
              $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp","sos_last_folders.empresa","=","emp.id")
              ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
              ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
              ->where([
                'sos_last_folders.fnzs_caja' => TRUE,
                'emp.empresa_token' => $empresa,
                'users.usuario_token' => $usuario,
              ])
              ->limit(1)->update(
                array(
                  'sos_last_folders.folder' => $folioCaja->folio,
                )
              );
            }

            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Esta caja ha sido registrada satisfactoriamente con el folio $new_caja_folio"
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 400,
              'message' => 'Caja no registrada, intente nuevamente o comuniquese a soporte'
            );
          }
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateAlmacenCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_diralmacen' => 'required|string',
      'token_responsable' => 'required|string',
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      //da_te_default_timezone_set('America/Mexico_City');
      $token_diralmacen = $request->input('token_diralmacen');
      $token_responsable = $request->input('token_responsable');
      $token_caja = $request->input('token_caja');
      
      $selectValidCajaRespons = DB::select("SELECT caja FROM responsables_almacen WHERE token_responsables = ?",[$token_responsable]);

      if ($selectValidCajaRespons[0]->caja != '' && $selectValidCajaRespons[0]->caja != NULL) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'Este empleado ya esta vinculado con la caja ' . $selectValidCajaRespons[0]->caja
        );
      } else {
        $selectTknCaja = DB::select("SELECT id FROM fnzs_catalogos_caja WHERE token_caja = ?", [$token_caja]);

        $selectTknDirAlm = DB::select("SELECT id FROM almacen WHERE token_almacen = ?", [$token_diralmacen]);

        $selectTknRespons = DB::select(
          "SELECT id FROM responsables_almacen WHERE token_responsables = ?",
          [$token_responsable]
        );

        $updateRepons = DB::table('responsables_almacen')
          ->where(
            [
              'token_responsables' => $token_responsable,
              'almacen' => $selectTknDirAlm[0]->id
            ]
          )
          ->limit(1)
          ->update(array('caja' => $selectTknCaja[0]->id));

        if ($updateRepons) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Actualización completada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Actualización incorrecta'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateAlmacenNewCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string',
      'token_almacenOld' => 'required|string',
      'token_almacenNew' => 'required|string',
      'token_responsables' => 'required'
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
      $token_caja = $request->input('token_caja');
      $token_almacenOld = $request->input('token_almacenOld');
      $token_almacenNew = $request->input('token_almacenNew');
      $token_responsables = $request->input('token_responsables');
      
      $selectAlmacenOld = DB::select("SELECT id FROM almacen WHERE token_almacen = ?", [$token_almacenOld]);
      $selectAlmacenNew = DB::select("SELECT id,alias_almacen FROM almacen WHERE token_almacen = ?", [$token_almacenNew]);
      $selectTknCaja = DB::select("SELECT id,alias_caja FROM caja WHERE token_caja = ?", [$token_caja]);

      $contadorValidacion = 0;
      for ($i = 0; $i < count($token_responsables); $i++) {
        $countResponCaja = DB::table('responsables_almacen AS respAlm')
        ->join("fnzs_catalogos_caja AS caj", "respAlm.caja", "caj.id")
        ->where([
          'respAlm.responsable' => $token_responsables[$i],
          'caj.token_caja' => $token_caja
        ])
        ->count();

        if ($countResponCaja == 0) {
          $contadorValidacion++;
        }
      }

      if ($contadorValidacion == count($token_responsables) && count($selectAlmacenOld) == 1 && count($selectAlmacenNew) == 1 && count($selectTknCaja) == 1) {
        $updatCajalmOld = DB::table('caja')
        ->where([
          'almacen' => $selectAlmacenOld[0]->id,
          'token_caja' => $token_caja
        ])
        ->limit(1)
        ->update(array('almacen' => NULL));

        if ($updatCajalmOld) {
          $updateRespalmOld = DB::table('responsables_almacen')
          ->where([
            'almacen' => $selectAlmacenOld[0]->id,
            'caja' => $selectTknCaja[0]->id
          ])
          //->limit(1)
          ->update(array('caja' => NULL));

          if ($updateRespalmOld) {
            $updateCajalmNew = DB::table('caja')
              ->where(
                [
                  'token_caja' => $parametrosArray['token_caja']
                ]
              )
              ->limit(1)
              ->update(array('almacen' => $selectAlmacenNew[0]->id));

            if ($updateCajalmNew) {
              $contadorAlmacen = 0;
              for ($i = 0; $i < count($token_responsables); $i++) {
                $updateRespalmNew = DB::table('responsables_almacen')
                ->where([
                  'token_responsables' => $token_responsables[$i],
                  'caja' => NULL
                ])
                //->limit(1)
                ->update(array('caja' => $selectTknCaja[0]->id));
                if ($updateRespalmNew) {
                  $contadorAlmacen++;
                }
              }

              if ($contadorAlmacen == count($token_responsables)) {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'La caja ' . $selectTknCaja[0]->alias_caja . ' ha sido vinculada al almacen ' . $JwtAuth->desencriptar($selectAlmacenNew[0]->alias_almacen) . '    satisfactoriamente'
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 400,
                  'message' => 'Personal de almacen no valido'
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Actualización incorrecta1'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 400,
              'message' => 'Actualización incorrecta2'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Actualización incorrecta3'
          );
        }
      } else {
        if ($contadorValidacion < count($token_responsables)) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'errorConfig' => 'INF-001',
            'message' => 'La información que intenta modificar presenta errores de configuración ó no se encuentra registrada'
          );
        }
        if (count($selectAlmacenOld) != 1 || count($selectAlmacenNew) != 1 || count($selectTknCaja) != 1) {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'errorConfig' => 'INF-002',
            'message' => 'La información que intenta modificar presenta errores de configuración ó no se encuentra registrada'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function desvincRespCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_diralmacen' => 'required|string',
      'token_responsable' => 'required|string',
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_diralmacen = $request->input('token_diralmacen');
      $token_caja = $request->input('token_caja');
      $token_responsable = $request->input('token_responsable');
      
      $selectTknDirAlm = DB::select("SELECT id FROM almacen WHERE token_almacen = ?", [$token_diralmacen]);
      //echo $selectTknDirAlm[0]->id; exit;

      $countRespon = DB::table('responsables_almacen AS respAlm')
      ->join("fnzs_catalogos_caja AS caj", "respAlm.caja", "caj.id")
      ->where('caj.token_caja',$token_caja)
      ->count();
      if ($countRespon == 1) {
        $dataMensaje = array(
          'code' => 400,
          'status' => 'error',
          'message' => 'No se puede desvincular, porqué no existe otro personal asignado'
        );
      } else if ($countRespon > 1) {
        $updateCajaRepons = DB::table('responsables_almacen')
        ->where([
          'token_responsables' => $token_responsable,
          'almacen' => $selectTknDirAlm[0]->id
        ])
        ->limit(1)
        ->update(array('caja' => NULL));

        if ($updateCajaRepons) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Actualización completada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Actualización incorrecta'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function vinculaRespCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_diralmacen' => 'required|string',
      'token_responsable' => 'required|string',
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_diralmacen = $request->input('token_diralmacen');
      $token_responsable = $request->input('token_responsable');
      $token_caja = $request->input('token_caja');
      
      $selectTknCaja = DB::select("SELECT id FROM fnzs_catalogos_caja WHERE token_caja = ?", [$token_caja]);

      $countRespon = DB::table('responsables_almacen AS respAlm')
      ->join("in_egr_establecimientos_catalogo AS alm", "respAlm.almacen", "alm.id")
      ->where([
        'alm.token_almacen' => $token_diralmacen,
        'respAlm.token_responsables' => $token_responsable,
        'respAlm.caja' => NULL
      ])->count();
      //echo $countRespon; exit;
      if ($countRespon == 0) {
        $updateCajaRepons = DB::table('responsables_almacen AS respAlm')
        ->join("in_egr_establecimientos_catalogo AS alm", "respAlm.almacen", "alm.id")
        ->where([
          'respAlm.token_responsables' => $token_responsable,
          'alm.token_almacen' => $token_diralmacen
        ])
        ->limit(1)
        ->update(array('respAlm.caja' => $selectTknCaja[0]->id));
        //echo $updateCajaRepons; exit;

        if ($updateCajaRepons) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Actualización completada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'Actualización no realizada'
          );
        }
      } else {
        $personal = DB::select("SELECT caj.no_caja FROM responsables_almacen AS resp JOIN fnzs_catalogos_caja AS caj WHERE resp.caja = caj.id");

        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'El personal que intenta vincular a esta caja ya se encuentra vinculado a la caja' .
            $JwtAuth->generar($personal[0]->no_caja)
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function updateCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string',
      'moneda' => 'required|string',
      'establecimiento_token' => 'required|string',
      'descripcion' => 'required|string',
      'cuenta_contable' => 'required|string',
      'servegresos' => 'required|boolean',
      'servingresos' => 'required|boolean',
      'servpropias' => 'required|boolean',
      'capt_cliente' => 'required|boolean',
      'capt_precio_x_articulo' => 'required|boolean',
      'capt_primero_cantidad' => 'required|boolean',
      'vendedor' => 'required|string',
      //'turnos' => 'string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_caja = $request->input('token_caja');
      $monedaCaja = $request->input('moneda');
      $establecimiento_token = $request->input('establecimiento_token');
      $descripcion = $request->input('descripcion');
      $cuenta_contable = $request->input('cuenta_contable');
      $servegresos = $request->input('servegresos');
      $servingresos = $request->input('servingresos');
      $servpropias = $request->input('servpropias');
      $capt_cliente = $request->input('capt_cliente');
      $capt_precio_x_articulo = $request->input('capt_precio_x_articulo');
      $capt_primero_cantidad = $request->input('capt_primero_cantidad');
      $vendedor = $request->input('vendedor');

      $queryCaja = CajaModelo::join("in_egr_establecimientos_catalogo AS alm", "fnzs_catalogos_caja.almacen", "alm.id")
      ->join("teci_direcciones AS dir", "alm.id", "dir.establecimiento")
      ->join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.token_caja' => $token_caja,
        'fnzs_catalogos_caja.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->get();

      foreach ($queryCaja as $vCaja) {
        $obten_vendedor = DB::table("vhum_empleados_catalogo")->where("empleado_token", $vendedor)->value("id");

        //$listaDirAlmacen = DB::select("SELECT alm.id FROM in_egr_establecimientos_catalogo AS alm JOIN main_empresas AS emp JOIN main_empresa_usuario AS empusers 
        //              JOIN teci_usuarios_catalogo AS users WHERE alm.token_establecimiento = ? AND alm.empresa = emp.id AND emp.empresa_token = ?
        //              AND emp.id = empusers.empresa AND empusers.usuario = users.id AND users.usuario_token = ?",
        //  [$establecimiento_token, $empresa, $usuario]
        //);

        $listaDirAlmacen = DB::table("in_egr_establecimientos_catalogo AS alm")
        ->join("main_empresas AS emp", "alm.empresa", "emp.id")
        ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
        ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
        ->where([
          'alm.token_establecimiento' => $establecimiento_token,
          'emp.empresa_token' => $empresa,
          'users.usuario_token' => $usuario
        ])->value("alm.id");

        $updatCaja = DB::table('fnzs_catalogos_caja')
        ->where('token_caja',$vCaja->token_caja)->limit(1)
        ->update(
          array(
            'alias_caja' => $JwtAuth->encriptar($descripcion),
            'moneda_caja' => $monedaCaja,
            'cuenta_contable_caja' => $cuenta_contable,
            'serv_egresos' => $servegresos ? TRUE : FALSE,
            'serv_ingresos' => $servingresos ? TRUE : FALSE,
            'serv_interno' => $servpropias ? TRUE : FALSE,
            'capt_cliente' => $capt_cliente ? TRUE : FALSE,
            'capt_precio_x_articulo' => $capt_precio_x_articulo ? TRUE : FALSE,
            'capt_primero_cantidad' => $capt_primero_cantidad ? TRUE : FALSE,
            'almacen' => $listaDirAlmacen,
            'encargado_principal' => !empty($vendedor) ? $JwtAuth->encriptar($vendedor) : NULL,
          )
        );
        if ($updatCaja) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'Actualización de caja completada satisfactoriamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Actualización no realizada'
          );
        }
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function editaCorteCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string',
      'token_cortecaja' => 'required|string',
      'horario_cortecaja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $horario_cortecaja = $request->input('horario_cortecaja');
      $token_cortecaja = $request->input('token_cortecaja');
      $token_caja = $request->input('token_caja');
      
      $horario_corte = $JwtAuth->encriptar($horario_cortecaja);

      $updateHorCrtCaja = DB::table('corte_caja')
      ->join("fnzs_catalogos_caja AS caj", "corte_caja.caja", "caj.id")
      ->where([
        "corte_caja.token_cortecaja" => $token_cortecaja,
        "caj.token_caja" => $token_caja
      ])
      ->limit(1)
      ->update(array('corte_caja.horario_corte' => $horario_corte));

      if ($updateHorCrtCaja) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'El corte caja se ha actualizado correctamente'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'Error al actualizado el corte caja, comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteCorteCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string',
      'token_cortecaja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_caja = $request->input('token_caja');
      $token_cortecaja = $request->input('token_cortecaja');
      
      $cajaID = DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id");

      $insertNewCorte = DB::table('corte_caja')
      ->where([
        "token_cortecaja" => $token_cortecaja,
        "caja" => $cajaID
      ])
      ->delete();

      if ($insertNewCorte) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'El corte caja se ha eliminado correctamente'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'Error al eliminado el corte caja, comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function agregaNewCorteCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string',
      'horario_cortecaja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_caja = $request->input('token_caja');
      $horario_cortecaja = $request->input('horario_cortecaja');
      
      $cajaID = DB::table("fnzs_catalogos_caja")->where("token_caja",$token_caja)->value("id");
      $empresaID = DB::table("main_empresas")->where("empresa_token",$empresa)->value("id");
      $token_cortecaja = $JwtAuth->encriptarToken(time(), $cajaID, $horario_cortecaja, $empresaID);

      $insertNewCorte = DB::table('corte_caja')
      ->insert(array(
        "token_cortecaja" => $token_cortecaja,
        "caja" => $cajaID,
        "horario_corte" => $horario_cortecaja,
        "empresa" => $empresaID
      ));

      if ($insertNewCorte) {
        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'message' => 'El corte caja se ha guardado correctamente'
        );
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'Error al guardar el corte caja, comuniquese a soporte'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function deleteCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_caja = $request->input('token_caja');
      
      $consultCaja = CajaModelo::join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.token_caja' => $token_caja,
        'fnzs_catalogos_caja.status' => TRUE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->count();

      if ($consultCaja == 1) {
        $consultCajaCompr = CajaModelo::join("eegr_compras AS comp", "fnzs_catalogos_caja.id", "comp.caja_paga")
        ->where('fnzs_catalogos_caja.token_caja', $token_caja)
        ->count();
        
        $consultCajaVentas = CajaModelo::join("ingr_ventas AS vent", "fnzs_catalogos_caja.id", "vent.caja")
        ->where('fnzs_catalogos_caja.token_caja', $token_caja)
        ->count();

        $consultCajaDisp = CajaModelo::join("teci_dispositivos AS disp", "fnzs_catalogos_caja.id", "disp.caja")
        ->where('fnzs_catalogos_caja.token_caja', $token_caja)
        ->count();

        //echo $consultCajaVentas;
        if ($consultCajaCompr == 0 && $consultCajaVentas == 0 && $consultCajaDisp == 0) {
          $updateStatusCaja = DB::table('fnzs_catalogos_caja')
          ->where(['token_caja' => $token_caja])
          ->limit(1)->update(array(
            'fecha_delete_caja' => time(), 
            'status' => FALSE
          ));

          if ($updateStatusCaja) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'La caja se ha eliminado correctamente'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 500,
              'message' => 'Error al eliminar caja, comuniquese a soporte'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 409,
            'message' => 'La caja que intenta eliminar esta vinvulada a compras o ventas realizadas'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 404,
          'message' => 'La caja que intenta eliminar no existe'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function restaurarCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La infomación que ha intantado actualizar es inválida',
				'errors' => $validate->errors()
			);
    } else {
      $token_caja = $request->input('token_caja');
      
      $consultCaja = CajaModelo::join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.token_caja' => $token_caja,
        'fnzs_catalogos_caja.status' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])
      ->count();

      if ($consultCaja == 1) {
        $updateStatusCaja = DB::table('fnzs_catalogos_caja')
        ->where('token_caja',$token_caja)
        ->limit(1)->update(array(
          'fecha_delete_caja' => NULL,
          'status' => TRUE
        ));

        if ($updateStatusCaja) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La caja se ha restaurado correctamente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'La caja que intenta restaurar es incorrecta'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La caja que intenta restaurar no existe'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function eliminaPrmannteCaja(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_caja' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido',
				'errors' => $validate->errors()
			);
    } else {
      $token_caja = $request->input('token_caja');
      
      $consultCaja = CajaModelo::join("main_empresas AS emp", "fnzs_catalogos_caja.empresa", "emp.id")
      ->join("main_empresa_usuario AS empusers", "emp.id", "empusers.empresa")
      ->join("teci_usuarios_catalogo AS users", "empusers.usuario", "users.id")
      ->where([
        'fnzs_catalogos_caja.token_caja' => $token_caja,
        'fnzs_catalogos_caja.status' => FALSE,
        'emp.empresa_token' => $empresa,
        'users.usuario_token' => $usuario
      ])->count();

      if ($consultCaja == 1) {
        $consultCajaRepALm = DB::table('in_egr_establecimientos_responsables AS resp')
        ->join("fnzs_catalogos_caja AS caj", "resp.caja", "caj.id")
        ->where("caj.token_caja",$token_caja)->count();

        if ($consultCajaRepALm >= 1) {
          $updateCajaRepons = DB::table('in_egr_establecimientos_responsables AS resp')
            ->join("fnzs_catalogos_caja AS caj", "resp.caja", "caj.id")
            ->where("caj.token_caja",$token_caja)
            ->update(array('resp.caja' => NULL));

          if (!$updateCajaRepons) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 400,
              'message' => 'La caja que intenta eliminar esta vinculada con algun personal'
            );
          }
        }

        $deleteCaja = DB::table('fnzs_catalogos_caja')->where('token_caja',$token_caja)->limit(1)->delete();

        if ($deleteCaja) {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'message' => 'La caja se ha eliminado permanentemente'
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 400,
            'message' => 'La caja que intenta eliminar es incorrecta'
          );
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 400,
          'message' => 'La caja que intenta eliminar no existe'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function saldoCajaByToken($token_caja, $empresa){
    $queryMovimientos = MovimientosBancariosModelo::join("main_empresas AS emp", "fnzs_actividad_movimientos.empresa", "=", "emp.id")
    ->join("fnzs_catalogos_caja AS caj_cat", "fnzs_actividad_movimientos.caja", "=", "caj_cat.id")
    ->where([
      "caj_cat.token_caja" => $token_caja,
      "emp.empresa_token" => $empresa
    ])
    ->orderBy('fnzs_actividad_movimientos.fecha_contabilizacion_movimiento', 'ASC')
    ->get();
    $empresaData = DB::table("main_empresas")->where("empresa_token",$empresa)->first();
    $codeMoneda = $empresaData->e_moneda_code;
    $decimalesMoneda = $empresaData->e_moneda_decimales;
    $saldo_total_acumulado = 0;

    foreach ($queryMovimientos as $cMov) {
      $monto_applc = (float)$cMov->monto_aplicado * ($cMov->tipo_cambio_movimiento ? $cMov->tipo_cambio_movimiento : 1);
      $movimiento_debe = $cMov->tipo_movimiento == 'S' ? $monto_applc : 0;
      $movimiento_haber = $cMov->tipo_movimiento == 'R' ? $monto_applc : 0;
      
      if (!is_null($cMov->pago)) {
        $idPagoByMovAcreedor = DB::table("fnzs_catalogo_acreedores_movimientos AS acrMov")
        ->join("fnzs_catalogo_acreedores_movimientos_pagos_vinculados AS ampv", "acrMov.id", "=", "ampv.mov_realizado")
        ->where("ampv.pago_vinculado",$cMov->pago)
        ->exists();

        $idPagoByMovDeudor = DB::table("fnzs_catalogo_deudores_movimientos AS deuMov")
        ->join("fnzs_catalogo_deudores_movimientos_pagos_vinculados AS dmpv", "deuMov.id", "=", "dmpv.mov_realizado")
        ->where("dmpv.pago_vinculado",$cMov->pago)
        ->exists();

        if ($idPagoByMovAcreedor || $idPagoByMovDeudor) {
          continue;
        }
      }
      
      if (!is_null($cMov->acreedor_movimiento)) {
        $movimiento_debe = $cMov->tipo_movimiento == 'R' ? $cMov->monto_aplicado : 0;
        $movimiento_haber = $cMov->tipo_movimiento == 'S' ? $cMov->monto_aplicado : 0;
      }

      $saldo_total_acumulado += ($movimiento_debe - $movimiento_haber);
    }
    return $saldo_total_acumulado;
  }
}
