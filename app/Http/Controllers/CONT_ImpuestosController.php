<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ImpuestosModelo;

class CONT_ImpuestosController extends Controller{
  public function impuestoCatalogoRegistro(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'impuesto_abreviacion' => 'required|string',
      'impuesto_concepto' => 'required|string',
      'impuesto_modulo' => 'nullable|string',
      'impuesto_nivel' => 'required|string',
      'impuesto_clave_sat' => 'nullable|string',
      'impuesto_tipo' => 'required|string',
      'impuesto_exento' => 'required|boolean',
      'impuesto_tasa_cuota' => 'nullable|string',
      'impuesto_importe' => 'nullable|string',
      'tipo_cambio' => 'nullable|string',
      'moneda_impuesto' => 'nullable|string',
      'impuesto_aplica_sobre' => 'nullable|string',
      'impuesto_desglose' => 'required|boolean',
      'impuesto_gl_por_pagar_o_cobrar' => 'nullable|string',
      'impuesto_gl_efectivamente_pagada_o_cobrada' => 'nullable|string',
      'impuesto_observaciones' => 'required|string'
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
      //da_te_default_timezone_set('America/Mexico_City');      
      $impuesto_abreviacion = $request->input('impuesto_abreviacion');
      $impuesto_concepto = $request->input('impuesto_concepto');
      $impuesto_modulo = $request->input('impuesto_modulo');
      $impuesto_nivel = $request->input('impuesto_nivel');
      $impuesto_clave_sat = $request->input('impuesto_clave_sat');
      $impuesto_tipo = $request->input('impuesto_tipo');
      $impuesto_exento = $request->input('impuesto_exento');
      $impuesto_tasa_cuota = $request->input('impuesto_tasa_cuota');
      $impuesto_importe = $request->input('impuesto_importe');
      $tipo_cambio = $request->input('tipo_cambio');
      $moneda_impuesto = $request->input('moneda_impuesto');
      $impuesto_aplica_sobre = $request->input('impuesto_aplica_sobre');
      $impuesto_desglose = $request->input('impuesto_desglose');
      $impuesto_gl_por_pagar_o_cobrar = $request->input('impuesto_gl_por_pagar_o_cobrar');
      $impuesto_gl_efectivamente_pagada_o_cobrada = $request->input('impuesto_gl_efectivamente_pagada_o_cobrada');
      $impuesto_observaciones = $request->input('impuesto_observaciones');

      $valida_importe_impuesto = isset($impuesto_importe) && !is_null($impuesto_importe) && $impuesto_importe != "";

      if (
        isset($impuesto_abreviacion) && !empty($impuesto_abreviacion) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_abreviacion) &&
        isset($impuesto_concepto) && !empty($impuesto_concepto) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_concepto) &&
        isset($impuesto_nivel) && !empty($impuesto_nivel) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_nivel) &&
        isset($impuesto_tipo) && !empty($impuesto_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_tipo) && 
        ($impuesto_exento || (isset($impuesto_tasa_cuota) && !empty($impuesto_tasa_cuota) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_tasa_cuota) &&
        $valida_importe_impuesto && isset($impuesto_aplica_sobre) && !empty($impuesto_aplica_sobre) &&
        preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_aplica_sobre) && isset($impuesto_desglose) && is_bool($impuesto_desglose))) &&
        isset($impuesto_observaciones) && !empty($impuesto_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_observaciones)
      ) {

        $sql_impuesto_modulo = NULL;
        if (isset($impuesto_modulo) && !empty($impuesto_modulo)) {
          if (preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_modulo)) {
            $sql_impuesto_modulo = $JwtAuth->encriptar($impuesto_modulo);
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en modulo de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        }
        $sql_impuesto_clave_sat = NULL;
        //echo $impuesto_clave_sat;
        if (isset($impuesto_clave_sat) && !empty($impuesto_clave_sat)) {
          if (preg_match($JwtAuth->filtroNumericoSimple(), $impuesto_clave_sat)) {
            //echo $impuesto_clave_sat;exit;
            $sql_impuesto_clave_sat = $impuesto_clave_sat;
            //echo "nada";exit;
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en clave de sat de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        }
        $sql_tipo_cambio = NULL;
        $sql_moneda = NULL;
        if ($impuesto_tasa_cuota == "tasa") {
          if (!preg_match($JwtAuth->filtroPorcentaje(), $impuesto_importe)) {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        } else {
          if (preg_match($JwtAuth->filtroCostoPrecio(), $impuesto_importe)) {
            if (isset($tipo_cambio) && !empty($tipo_cambio)) {
              if (preg_match($JwtAuth->filtroCostoPrecio(), $tipo_cambio)) {
                $sql_tipo_cambio = $tipo_cambio;
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Error en tipo de cambio de impuesto, por favor verifique su información o comuniquese a soporte"
                );
              }
            }
            if (isset($moneda_impuesto) && !empty($moneda_impuesto)) {
              $queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?", [$moneda_impuesto]);
              if (count($queryMoneda) > 0 && count($queryMoneda) == 1) {
                $sql_moneda = end($queryMoneda)->id;
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Error en moneda de impuesto, por favor verifique su información o comuniquese a soporte"
                );
              }
            }
            //$moneda_impuesto = $parametrosArray["moneda_impuesto"];
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        }

        $sql_impuesto_gl_por_pagar_o_cobrar = NULL;
        if (isset($impuesto_gl_por_pagar_o_cobrar) && !empty($impuesto_gl_por_pagar_o_cobrar)) {
          if (preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_gl_por_pagar_o_cobrar)) {
            $sql_impuesto_gl_por_pagar_o_cobrar = $impuesto_gl_por_pagar_o_cobrar;
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en GL por pagar o cobrar, por favor verifique su información o comuniquese a soporte"
            );
          }
        }

        $sql_impuesto_gl_efectivamente_pagada_o_cobrada = NULL;
        if (isset($impuesto_gl_efectivamente_pagada_o_cobrada) && !empty($impuesto_gl_efectivamente_pagada_o_cobrada)) {
          if (preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_gl_efectivamente_pagada_o_cobrada)) {
            $sql_impuesto_gl_efectivamente_pagada_o_cobrada = $impuesto_gl_efectivamente_pagada_o_cobrada;
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en GL efectivamente pagada o cobrada, por favor verifique su información o comuniquese a soporte"
            );
          }
        }

        $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,users.id AS userr,emp.zona_horaria,people.paterno,
                      people.materno,people.nombre,people.denominacion_rs,people.sitio_web FROM main_empresas AS emp  
                      JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                      WHERE emp.empresa_token = ? AND emp.persona = people.id AND emp.id = empuser.empresa 
                      AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);
        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

        $folioSistema = DB::select("SELECT MAX(folio_impuesto) AS folio_impuesto FROM cont_impuestos_catalogo AS imp
                  JOIN main_empresas AS emp WHERE imp.assoc = TRUE AND imp.empresa = emp.id AND emp.empresa_token = ?", [$empresa]);

        $sql_folio = count($folioSistema) == 0 ? 1 : $folioSistema[0]->folio_impuesto + 1;

        $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder
                      FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                      WHERE fold.cont_impuestos = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
                      AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);
        //echo count($folioSistema);    
        if (count($folioSistema) == 1) {
          if ($folioSistema[0]->folio == 1000000000) {
            $post_folio_db = DB::select(
              "SELECT post_folio FROM cont_impuestos_catalogo WHERE id = (SELECT Max(catimp.id) FROM cont_impuestos_catalogo AS catimp 
                              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catimp.empresa = emp.id 
                              AND emp.empresa_token = ? AND emp.id = empper.empresa AND empper.usuario = users.id AND users.usuario_token = ?)",
              [$empresa, $usuario]
            );

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
        $fecha_sistema = time();
        $folio_imp = $post_folio == NULL ? 'IMP-' . $JwtAuth->generarFolio($folio_nuevo) : 'IMP-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
        //echo  $sql_impuesto_clave_sat; exit; 
        $token_cat_impuestos = $JwtAuth->encriptarToken($impuesto_concepto, $impuesto_tipo, $impuesto_tasa_cuota, $impuesto_importe);
        $sql_ret_tras = $impuesto_tipo == "retenido" ? "rete" : "tras";
        $creaImpuesto = new ImpuestosModelo();
        $creaImpuesto->token_catalogo_impuesto = $token_cat_impuestos;
        $creaImpuesto->fecha_registro = $fecha_sistema;
        $creaImpuesto->folio_impuesto = $folio_nuevo;
        $creaImpuesto->post_folio = $post_folio;
        $creaImpuesto->abreviacion_impuesto = $JwtAuth->encriptar($impuesto_abreviacion);
        $creaImpuesto->concepto_impuesto = $JwtAuth->encriptar($impuesto_concepto);
        $creaImpuesto->modulo = $sql_impuesto_modulo;
        $creaImpuesto->nivel_aplicacion = $impuesto_nivel;
        $creaImpuesto->catalogo_sat = $sql_impuesto_clave_sat;
        $creaImpuesto->tipo_impuesto = $sql_ret_tras;
        $creaImpuesto->exento = $impuesto_exento ? TRUE : FALSE;
        $creaImpuesto->calculo = $impuesto_tasa_cuota;
        $creaImpuesto->importe = $impuesto_importe;
        $creaImpuesto->tipo_cambio_imp = $sql_tipo_cambio;
        $creaImpuesto->moneda_registrada_imp = $sql_moneda;
        $creaImpuesto->base = $impuesto_aplica_sobre;
        $creaImpuesto->desglose = $impuesto_desglose;
        $creaImpuesto->gl_por_pagarcobrar = $sql_impuesto_gl_por_pagar_o_cobrar;
        $creaImpuesto->gl_pagada_o_cobrada = $sql_impuesto_gl_efectivamente_pagada_o_cobrada;
        $creaImpuesto->observaciones = $JwtAuth->encriptar($impuesto_observaciones);
        $creaImpuesto->imp_status = TRUE;
        $creaImpuesto->empresa = $selectEmp[0]->id;
        $creaImpuesto->assoc = TRUE;
        $savednewImp = $creaImpuesto->save();

        if ($savednewImp) {
          DB::table('cont_impuestos_vinc_empresas')
          ->insert(
            array(
              "impuesto_generado" => $creaImpuesto->id,
              "empresa_vinculada" => $selectEmp[0]->id,
            )
          );

          if (count($folioSistema) == 0) {
            $insertSistema = DB::table('sos_last_folders')
              ->insert(
                array(
                  "cont_impuestos" => TRUE,
                  "folder" => 1,
                  "post_folder" => $post_folio,
                  "empresa" => $selectEmp[0]->id,
                )
              );
          } else {
            $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
              ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
              ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
              ->where([
                'sos_last_folders.cont_impuestos' => TRUE,
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
            "status" => "success",
            "code" => 200,
            "message" => "Este impuesto ha sido registrado satisfactoriamente con el folio " . $folio_imp
          );
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Error en registro de impuesto, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $mensaje_error = "";
        if (!isset($impuesto_abreviacion) || empty($impuesto_abreviacion) || !preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_abreviacion)) $mensaje_error = "Error en abreviación de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!isset($impuesto_concepto) || empty($impuesto_concepto) || !preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_concepto)) $mensaje_error = "Error en concepto de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!isset($impuesto_nivel) || empty($impuesto_nivel) || !preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_nivel)) $mensaje_error = "Error en nivel de aplicación de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!isset($impuesto_tipo) || empty($impuesto_tipo) || !preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_tipo)) $mensaje_error = "Error en tipo de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!$impuesto_exento && (!isset($impuesto_tasa_cuota) || empty($impuesto_tasa_cuota) || !preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_tasa_cuota))) $mensaje_error = "Error en tasa o cuota de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!$impuesto_exento && (!$valida_importe_impuesto)) $mensaje_error = "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!$impuesto_exento && (!isset($impuesto_aplica_sobre) || empty($impuesto_aplica_sobre) || !preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_aplica_sobre))) $mensaje_error = "Error en aplicacion de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!$impuesto_exento && (!isset($impuesto_desglose) || !is_bool($impuesto_desglose))) $mensaje_error = "Error en selección sobre desglose de impuesto, por favor verifique su información o comuniquese a soporte";
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoGeneralImpuestos(Request $request){
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
      $JwtAuth = new \App\Helpers\JwtAuth();
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
      
      $catImp = ImpuestosModelo::join('cont_impuestos_vinc_empresas AS vinc', 'cont_impuestos_catalogo.id', 'vinc.impuesto_generado')
      ->join('main_empresas AS emp', 'vinc.empresa_vinculada', 'emp.id')
      ->where([
        'cont_impuestos_catalogo.imp_status' => TRUE, 
        'emp.empresa_token' => $empresa
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("cont_impuestos_catalogo.fecha_registro", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($catImp->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron impuestos registrados'
        );
      } else {
        $listaImpuestos = array();
        foreach ($catImp as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          $folio_impuesto = 'IMP-'.($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_impuesto) : $JwtAuth->generarFolio($value->folio_impuesto) . '-' . $value->post_folio);
          $importe_imp = $value->calculo == "cuota" ? "$" . $value->importe : $value->importe . "%";

          $data_tipo_cambio = "";
          $data_monedas_tkn = ""; //token_monedas
          $data_monedas_codigo = ""; //codigo
          $data_monedas_moneda = ""; //moneda
          $data_monedas_decimales = ""; //decimales

          if ($value->calculo == "cuota") {
            //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
            $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
              ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $value->token_catalogo_impuesto])->get();

            foreach ($queryCurrencyImp as $vMon) {
              $data_monedas_tkn = $vMon->token_monedas;
              $data_monedas_codigo = $vMon->codigo;
              $data_monedas_moneda = $vMon->moneda;
              $data_monedas_decimales = $vMon->decimales;
              $data_tipo_cambio = "$" . number_format($value->tipo_cambio_imp, $vMon->decimales, '.', ',');
            }
          }

          $arrayforeach = array(
            "token_catalogo_impuesto" => $value->token_catalogo_impuesto,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($value->fecha_registro),
            "folio_impuesto" => $folio_impuesto,
            "abreviacion_impuesto" => $JwtAuth->desencriptar($value->abreviacion_impuesto),
            "concepto_impuesto" => $JwtAuth->desencriptar($value->concepto_impuesto),
            "modulo" => $value->modulo != NULL ? $JwtAuth->desencriptar($value->modulo) : null,
            "nivel_aplicacion" => $value->nivel_aplicacion,
            "catalogo_sat" => $value->catalogo_sat != NULL ? $value->catalogo_sat : null,
            "tipo_impuesto" => $value->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
            "exento" => $value->exento ? "yes" : "No",
            "calculo" => $value->calculo,
            "importe" => $value->importe,
            "txtimporte" => $importe_imp,
            "tipo_cambio" => $data_tipo_cambio,
            //moneda_registrada_imp
            "monedas_tkn" => $data_monedas_tkn,
            "monedas_codigo" => $data_monedas_codigo,
            "monedas_moneda" => $data_monedas_moneda,
            "monedas_decimales" => $data_monedas_decimales,
            "base_aplicable" => $value->base,
            "desglose" => $value->desglose == TRUE ? "yes" : "No",
            "gl_por_pagarcobrar" => $value->gl_por_pagarcobrar != NULL ? $value->gl_por_pagarcobrar : null,
            "gl_pagada_o_cobrada" => $value->gl_pagada_o_cobrada != NULL ? $value->gl_pagada_o_cobrada : null,
            "observaciones" => $JwtAuth->desencriptar($value->observaciones),
            "habilitado" => $value->habilitado_imp == TRUE ? true : false,
            "vinculacion" => false,
          );
          $listaImpuestos[] = $arrayforeach;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "impuestos" => $listaImpuestos
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoImpuestosDeclaracion(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $JwtAuth = new \App\Helpers\JwtAuth();
    $declaracion = "eyJkYXRhIjoiVCtGaHB2N1pUVGJuZ3RcL0I0VDNUU2c9PSIsIml2IjoiTXpNMllqUTJNbU0wTlRjelpqaGhOdz09In0=";
    $catImp = ImpuestosModelo::join('cont_impuestos_vinc_empresas AS vinc', 'cont_impuestos_catalogo.id', 'vinc.impuesto_generado')
    ->join('main_empresas AS emp', 'vinc.empresa_vinculada', 'emp.id')
    ->where([
      'cont_impuestos_catalogo.modulo' => $declaracion,
      'cont_impuestos_catalogo.imp_status' => TRUE, 
      'emp.empresa_token' => $empresa
    ])
    ->get();

    if ($catImp->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron impuestos registrados'
      );
    } else {
      $listaImpuestos = array();
      
      foreach ($catImp as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $folio_impuesto = 'IMP-'.$JwtAuth->generarFolio($value->folio_impuesto).(!is_null($value->post_folio) ? '-'.$value->post_folio : '');
        $importe_imp = $value->calculo == "cuota" ? "$" . $value->importe : $value->importe . "%";

        $data_tipo_cambio = "";
        $data_monedas_tkn = ""; //token_monedas
        $data_monedas_codigo = ""; //codigo
        $data_monedas_moneda = ""; //moneda
        $data_monedas_decimales = ""; //decimales

        if ($value->calculo == "cuota") {
          //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
          $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
            ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $value->token_catalogo_impuesto])->get();

          foreach ($queryCurrencyImp as $vMon) {
            $data_monedas_tkn = $vMon->token_monedas;
            $data_monedas_codigo = $vMon->codigo;
            $data_monedas_moneda = $vMon->moneda;
            $data_monedas_decimales = $vMon->decimales;
            $data_tipo_cambio = "$" . number_format($value->tipo_cambio_imp, $vMon->decimales, '.', ',');
          }
        }

        $arrayforeach = array(
          "token_catalogo_impuesto" => $value->token_catalogo_impuesto,
          "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($value->fecha_registro),
          "folio_impuesto" => $folio_impuesto,
          "abreviacion_impuesto" => $JwtAuth->desencriptar($value->abreviacion_impuesto),
          "concepto_impuesto" => $JwtAuth->desencriptar($value->concepto_impuesto),
          "modulo" => $value->modulo != NULL ? $JwtAuth->desencriptar($value->modulo) : null,
          "nivel_aplicacion" => $value->nivel_aplicacion,
          "catalogo_sat" => $value->catalogo_sat != NULL ? $value->catalogo_sat : null,
          "tipo_impuesto" => $value->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
          "exento" => $value->exento ? "yes" : "No",
          "calculo" => $value->calculo,
          "importe" => $value->importe,
          "txtimporte" => $importe_imp,
          "tipo_cambio" => $data_tipo_cambio,
          //moneda_registrada_imp
          "monedas_tkn" => $data_monedas_tkn,
          "monedas_codigo" => $data_monedas_codigo,
          "monedas_moneda" => $data_monedas_moneda,
          "monedas_decimales" => $data_monedas_decimales,
          "base_aplicable" => $value->base,
          "desglose" => $value->desglose == TRUE ? "yes" : "No",
          "gl_por_pagarcobrar" => $value->gl_por_pagarcobrar != NULL ? $value->gl_por_pagarcobrar : null,
          "gl_pagada_o_cobrada" => $value->gl_pagada_o_cobrada != NULL ? $value->gl_pagada_o_cobrada : null,
          "observaciones" => $JwtAuth->desencriptar($value->observaciones),
          "habilitado" => $value->habilitado_imp == TRUE ? true : false,
          "vinculacion" => false,
        );
        $listaImpuestos[] = $arrayforeach;
      }

      $dataMensaje = array(
        "status" => "success",
        "code" => 200,
        "impuestos" => $listaImpuestos
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoGeneralImpuestosRetenciones(Request $request){
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
      $JwtAuth = new \App\Helpers\JwtAuth();
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
      
      $catImp = ImpuestosModelo::join('cont_impuestos_vinc_empresas AS vinc', 'cont_impuestos_catalogo.id', 'vinc.impuesto_generado')
      ->join('main_empresas AS emp', 'vinc.empresa_vinculada', 'emp.id')
      ->where([
        'cont_impuestos_catalogo.tipo_impuesto' => "rete",
        'cont_impuestos_catalogo.modulo' => "OHNPcXphaG5ac3dFVFVtZW5UT3dRdz09OjoxMjM0NTY3ODEyMzQ1Njc4",
        'emp.empresa_token' => $empresa
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("cont_impuestos_catalogo.fecha_registro", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($catImp->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron impuestos registrados'
        );
      } else {
        $listaImpuestos = array();
        foreach ($catImp as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          $folio_impuesto = 'IMP-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_impuesto) : $JwtAuth->generarFolio($value->folio_impuesto) . '-' . $value->post_folio);
          $importe_imp = $value->calculo == "cuota" ? "$" . $value->importe : $value->importe . "%";

          $data_tipo_cambio = "";
          $data_monedas_tkn = ""; //token_monedas
          $data_monedas_codigo = ""; //codigo
          $data_monedas_moneda = ""; //moneda
          $data_monedas_decimales = ""; //decimales

          if ($value->calculo == "cuota") {
            //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
            $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
              ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $value->token_catalogo_impuesto])->get();

            foreach ($queryCurrencyImp as $vMon) {
              $data_monedas_tkn = $vMon->token_monedas;
              $data_monedas_codigo = $vMon->codigo;
              $data_monedas_moneda = $vMon->moneda;
              $data_monedas_decimales = $vMon->decimales;
              $data_tipo_cambio = "$" . number_format($value->tipo_cambio_imp, $vMon->decimales, '.', ',');
            }
          }

          $arrayforeach = array(
            "token_catalogo_impuesto" => $value->token_catalogo_impuesto,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($value->fecha_registro),
            "folio_impuesto" => $folio_impuesto,
            "abreviacion_impuesto" => $JwtAuth->desencriptar($value->abreviacion_impuesto),
            "concepto_impuesto" => $JwtAuth->desencriptar($value->concepto_impuesto),
            "modulo" => $value->modulo != NULL ? $JwtAuth->desencriptar($value->modulo) : null,
            "nivel_aplicacion" => $value->nivel_aplicacion,
            "catalogo_sat" => $value->catalogo_sat != NULL ? $value->catalogo_sat : null,
            "tipo_impuesto" => $value->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
            "exento" => $value->exento ? "yes" : "No",
            "calculo" => $value->calculo,
            "importe" => $value->importe,
            "txtimporte" => $importe_imp,
            "tipo_cambio" => $data_tipo_cambio,
            //moneda_registrada_imp
            "monedas_tkn" => $data_monedas_tkn,
            "monedas_codigo" => $data_monedas_codigo,
            "monedas_moneda" => $data_monedas_moneda,
            "monedas_decimales" => $data_monedas_decimales,
            "base_aplicable" => $value->base,
            "desglose" => $value->desglose == TRUE ? "yes" : "No",
            "gl_por_pagarcobrar" => $value->gl_por_pagarcobrar != NULL ? $value->gl_por_pagarcobrar : null,
            "gl_pagada_o_cobrada" => $value->gl_pagada_o_cobrada != NULL ? $value->gl_pagada_o_cobrada : null,
            "observaciones" => $JwtAuth->desencriptar($value->observaciones),
            "habilitado" => $value->habilitado_imp == TRUE ? true : false,
            "vinculacion" => false,
          );
          $listaImpuestos[] = $arrayforeach;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "impuestos" => $listaImpuestos
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoGeneralImpuestosTraslados(Request $request){
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
      $JwtAuth = new \App\Helpers\JwtAuth();
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
      
      $catImp = ImpuestosModelo::join('cont_impuestos_vinc_empresas AS vinc', 'cont_impuestos_catalogo.id', 'vinc.impuesto_generado')
      ->join('main_empresas AS emp', 'vinc.empresa_vinculada', 'emp.id')
      ->where([
        'cont_impuestos_catalogo.tipo_impuesto' => "tras",
        'cont_impuestos_catalogo.modulo' => "OHNPcXphaG5ac3dFVFVtZW5UT3dRdz09OjoxMjM0NTY3ODEyMzQ1Njc4",
        'emp.empresa_token' => $empresa
      ])
      ->when($periodo != 'all_partidas', function ($query) use ($fechaInicio, $fechaFin) {
        return $query->whereBetween("cont_impuestos_catalogo.fecha_registro", [$fechaInicio, $fechaFin]);
      })
      ->get();

      if ($catImp->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron impuestos registrados'
        );
      } else {
        $listaImpuestos = array();
        foreach ($catImp as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          $folio_impuesto = 'IMP-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_impuesto) : $JwtAuth->generarFolio($value->folio_impuesto) . '-' . $value->post_folio);
          $importe_imp = $value->calculo == "cuota" ? "$" . $value->importe : $value->importe . "%";

          $data_tipo_cambio = "";
          $data_monedas_tkn = ""; //token_monedas
          $data_monedas_codigo = ""; //codigo
          $data_monedas_moneda = ""; //moneda
          $data_monedas_decimales = ""; //decimales

          if ($value->calculo == "cuota") {
            //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
            $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
              ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $value->token_catalogo_impuesto])->get();

            foreach ($queryCurrencyImp as $vMon) {
              $data_monedas_tkn = $vMon->token_monedas;
              $data_monedas_codigo = $vMon->codigo;
              $data_monedas_moneda = $vMon->moneda;
              $data_monedas_decimales = $vMon->decimales;
              $data_tipo_cambio = "$" . number_format($value->tipo_cambio_imp, $vMon->decimales, '.', ',');
            }
          }

          $arrayforeach = array(
            "token_catalogo_impuesto" => $value->token_catalogo_impuesto,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($value->fecha_registro),
            "folio_impuesto" => $folio_impuesto,
            "abreviacion_impuesto" => $JwtAuth->desencriptar($value->abreviacion_impuesto),
            "concepto_impuesto" => $JwtAuth->desencriptar($value->concepto_impuesto),
            "modulo" => $value->modulo != NULL ? $JwtAuth->desencriptar($value->modulo) : null,
            "nivel_aplicacion" => $value->nivel_aplicacion,
            "catalogo_sat" => $value->catalogo_sat != NULL ? $value->catalogo_sat : null,
            "tipo_impuesto" => $value->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
            "exento" => $value->exento ? "yes" : "No",
            "calculo" => $value->calculo,
            "importe" => $value->importe,
            "txtimporte" => $importe_imp,
            "tipo_cambio" => $data_tipo_cambio,
            //moneda_registrada_imp
            "monedas_tkn" => $data_monedas_tkn,
            "monedas_codigo" => $data_monedas_codigo,
            "monedas_moneda" => $data_monedas_moneda,
            "monedas_decimales" => $data_monedas_decimales,
            "base_aplicable" => $value->base,
            "desglose" => $value->desglose == TRUE ? "yes" : "No",
            "gl_por_pagarcobrar" => $value->gl_por_pagarcobrar != NULL ? $value->gl_por_pagarcobrar : null,
            "gl_pagada_o_cobrada" => $value->gl_pagada_o_cobrada != NULL ? $value->gl_pagada_o_cobrada : null,
            "observaciones" => $JwtAuth->desencriptar($value->observaciones),
            "habilitado" => $value->habilitado_imp == TRUE ? true : false,
            "vinculacion" => false,
          );
          $listaImpuestos[] = $arrayforeach;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "impuestos" => $listaImpuestos
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoGeneralImpuestosRetencionesIngresos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $catImp = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
    ->where([
      'cont_impuestos_catalogo.tipo_impuesto' => "rete",
      'cont_impuestos_catalogo.imp_status' => TRUE, 
      'emp.empresa_token' => $empresa
    ])
    ->get();
    
    if ($catImp->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron impuestos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaImpuestos = array();
      
      foreach ($catImp as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $folio_impuesto = 'IMP-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_impuesto) : $JwtAuth->generarFolio($value->folio_impuesto) . '-' . $value->post_folio);
        $importe_imp = $value->calculo == "cuota" ? "$" . $value->importe : $value->importe . "%";

        $data_tipo_cambio = "";
        $data_monedas_tkn = ""; //token_monedas
        $data_monedas_codigo = ""; //codigo
        $data_monedas_moneda = ""; //moneda
        $data_monedas_decimales = ""; //decimales

        if ($value->calculo == "cuota") {
          //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
          $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
            ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $value->token_catalogo_impuesto])->get();

          foreach ($queryCurrencyImp as $vMon) {
            $data_monedas_tkn = $vMon->token_monedas;
            $data_monedas_codigo = $vMon->codigo;
            $data_monedas_moneda = $vMon->moneda;
            $data_monedas_decimales = $vMon->decimales;
            $data_tipo_cambio = "$" . number_format($value->tipo_cambio_imp, $vMon->decimales, '.', ',');
          }
        }

        $arrayforeach = array(
          "token_catalogo_impuesto" => $value->token_catalogo_impuesto,
          "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($value->fecha_registro),
          "folio_impuesto" => $folio_impuesto,
          "abreviacion_impuesto" => $JwtAuth->desencriptar($value->abreviacion_impuesto),
          "concepto_impuesto" => $JwtAuth->desencriptar($value->concepto_impuesto),
          "modulo" => $value->modulo != NULL ? $JwtAuth->desencriptar($value->modulo) : null,
          "nivel_aplicacion" => $value->nivel_aplicacion,
          "catalogo_sat" => $value->catalogo_sat != NULL ? $value->catalogo_sat : null,
          "tipo_impuesto" => $value->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
          "calculo" => $value->calculo,
          "importe" => $value->importe,
          "txtimporte" => $importe_imp,
          "tipo_cambio" => $data_tipo_cambio,
          //moneda_registrada_imp
          "monedas_tkn" => $data_monedas_tkn,
          "monedas_codigo" => $data_monedas_codigo,
          "monedas_moneda" => $data_monedas_moneda,
          "monedas_decimales" => $data_monedas_decimales,
          "base_aplicable" => $value->base,
          "desglose" => $value->desglose == TRUE ? "yes" : "No",
          "gl_por_pagarcobrar" => $value->gl_por_pagarcobrar != NULL ? $value->gl_por_pagarcobrar : null,
          "gl_pagada_o_cobrada" => $value->gl_pagada_o_cobrada != NULL ? $value->gl_pagada_o_cobrada : null,
          "observaciones" => $JwtAuth->desencriptar($value->observaciones),
          "habilitado" => $value->habilitado_imp == TRUE ? true : false,
          "vinculacion" => false,
        );
        $listaImpuestos[] = $arrayforeach;
      }

      $dataMensaje = array(
        "status" => "success",
        "code" => 200,
        "impuestos" => $listaImpuestos
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoGeneralImpuestosTrasladosIngresos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $catImp = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
    ->where([
      'cont_impuestos_catalogo.tipo_impuesto' => "tras",
      'cont_impuestos_catalogo.imp_status' => TRUE, 
      'emp.empresa_token' => $empresa
    ])
    ->get();

    if ($catImp->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron impuestos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaImpuestos = array();
      
      foreach ($catImp as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $folio_impuesto = 'IMP-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_impuesto) : $JwtAuth->generarFolio($value->folio_impuesto) . '-' . $value->post_folio);
        $importe_imp = $value->calculo == "cuota" ? "$" . $value->importe : $value->importe . "%";

        $data_tipo_cambio = "";
        $data_monedas_tkn = ""; //token_monedas
        $data_monedas_codigo = ""; //codigo
        $data_monedas_moneda = ""; //moneda
        $data_monedas_decimales = ""; //decimales

        if ($value->calculo == "cuota") {
          //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
          $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
            ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $value->token_catalogo_impuesto])->get();

          foreach ($queryCurrencyImp as $vMon) {
            $data_monedas_tkn = $vMon->token_monedas;
            $data_monedas_codigo = $vMon->codigo;
            $data_monedas_moneda = $vMon->moneda;
            $data_monedas_decimales = $vMon->decimales;
            $data_tipo_cambio = "$" . number_format($value->tipo_cambio_imp, $vMon->decimales, '.', ',');
          }
        }

        $arrayforeach = array(
          "token_catalogo_impuesto" => $value->token_catalogo_impuesto,
          "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($value->fecha_registro),
          "folio_impuesto" => $folio_impuesto,
          "abreviacion_impuesto" => $JwtAuth->desencriptar($value->abreviacion_impuesto),
          "concepto_impuesto" => $JwtAuth->desencriptar($value->concepto_impuesto),
          "modulo" => $value->modulo != NULL ? $JwtAuth->desencriptar($value->modulo) : null,
          "nivel_aplicacion" => $value->nivel_aplicacion,
          "catalogo_sat" => $value->catalogo_sat != NULL ? $value->catalogo_sat : null,
          "tipo_impuesto" => $value->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
          "calculo" => $value->calculo,
          "importe" => $value->importe,
          "txtimporte" => $importe_imp,
          "tipo_cambio" => $data_tipo_cambio,
          //moneda_registrada_imp
          "monedas_tkn" => $data_monedas_tkn,
          "monedas_codigo" => $data_monedas_codigo,
          "monedas_moneda" => $data_monedas_moneda,
          "monedas_decimales" => $data_monedas_decimales,
          "base_aplicable" => $value->base,
          "desglose" => $value->desglose == TRUE ? "yes" : "No",
          "gl_por_pagarcobrar" => $value->gl_por_pagarcobrar != NULL ? $value->gl_por_pagarcobrar : null,
          "gl_pagada_o_cobrada" => $value->gl_pagada_o_cobrada != NULL ? $value->gl_pagada_o_cobrada : null,
          "observaciones" => $JwtAuth->desencriptar($value->observaciones),
          "habilitado" => $value->habilitado_imp == TRUE ? true : false,
          "vinculacion" => false,
        );
        $listaImpuestos[] = $arrayforeach;
      }

      $dataMensaje = array(
        "status" => "success",
        "code" => 200,
        "impuestos" => $listaImpuestos
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoGeneralImpuestosEnabled(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $catImp = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
    ->where([
      'cont_impuestos_catalogo.habilitado_imp' => TRUE, 
      'cont_impuestos_catalogo.imp_status' => TRUE, 
      'emp.empresa_token' => $empresa
    ])
    ->get();
    
    if ($catImp->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron impuestos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaImpuestos = array();
      
      foreach ($catImp as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $folio_impuesto = 'IMP-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_impuesto) : $JwtAuth->generarFolio($value->folio_impuesto) . '-' . $value->post_folio);
        $importe_imp = $value->calculo == "cuota" ? "$" . $value->importe : $value->importe . "%";

        $data_tipo_cambio = "";
        $data_monedas_tkn = ""; //token_monedas
        $data_monedas_codigo = ""; //codigo
        $data_monedas_moneda = ""; //moneda
        $data_monedas_decimales = ""; //decimales

        if ($value->calculo == "cuota") {
          //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
          $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
            ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $value->token_catalogo_impuesto])->get();

          foreach ($queryCurrencyImp as $vMon) {
            $data_monedas_tkn = $vMon->token_monedas;
            $data_monedas_codigo = $vMon->codigo;
            $data_monedas_moneda = $vMon->moneda;
            $data_monedas_decimales = $vMon->decimales;
            $data_tipo_cambio = "$" . number_format($value->tipo_cambio_imp, $vMon->decimales, '.', ',');
          }
        }

        $arrayforeach = array(
          "token_catalogo_impuesto" => $value->token_catalogo_impuesto,
          "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($value->fecha_registro),
          "folio_impuesto" => $folio_impuesto,
          "abreviacion_impuesto" => $JwtAuth->desencriptar($value->abreviacion_impuesto),
          "concepto_impuesto" => $JwtAuth->desencriptar($value->concepto_impuesto),
          "modulo" => $value->modulo != NULL ? $JwtAuth->desencriptar($value->modulo) : null,
          "nivel_aplicacion" => $value->nivel_aplicacion,
          "catalogo_sat" => $value->catalogo_sat != NULL ? $value->catalogo_sat : null,
          "tipo_impuesto" => $value->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
          "calculo" => $value->calculo,
          "importe" => $value->importe,
          "txtimporte" => $importe_imp,
          "tipo_cambio" => $data_tipo_cambio,
          //moneda_registrada_imp
          "monedas_tkn" => $data_monedas_tkn,
          "monedas_codigo" => $data_monedas_codigo,
          "monedas_moneda" => $data_monedas_moneda,
          "monedas_decimales" => $data_monedas_decimales,
          "base_aplicable" => $value->base,
          "desglose" => $value->desglose == TRUE ? true : false,
          "gl_por_pagarcobrar" => $value->gl_por_pagarcobrar != NULL ? $value->gl_por_pagarcobrar : null,
          "gl_pagada_o_cobrada" => $value->gl_pagada_o_cobrada != NULL ? $value->gl_pagada_o_cobrada : null,
          "observaciones" => $JwtAuth->desencriptar($value->observaciones),
          "habilitado" => true,
          "vinculacion" => false,
        );
        $listaImpuestos[] = $arrayforeach;
      }

      $dataMensaje = array(
        "status" => "success",
        "code" => 200,
        "impuestos" => $listaImpuestos
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoImpuestosDetalle(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_catalogo_impuesto' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos seleccionados',
				'errors' => $validate->errors()
			);
    } else {
      $token_catalogo_impuesto = $request->input('token_catalogo_impuesto');
  
      $catImp = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
      ->where([
        'cont_impuestos_catalogo.imp_status' => TRUE, 
        'cont_impuestos_catalogo.token_catalogo_impuesto' => $token_catalogo_impuesto, 
        'emp.empresa_token' => $empresa
      ])
      ->get();

      if ($catImp->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron impuestos registrados'
        );
      } else {
        $JwtAuth = new \App\Helpers\JwtAuth();
        $impuestoInfo = array();

        foreach ($catImp as $value) {
          //da_te_default_timezone_set($value->zona_horaria);
          $folio_impuesto = 'IMP-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_impuesto) : $JwtAuth->generarFolio($value->folio_impuesto) . '-' . $value->post_folio);
          $importe_imp = $value->calculo == "cuota" ? "$" . $value->importe : $value->importe . "%";

          $data_tipo_cambio_simple = "";
          $data_tipo_cambio_format = "";
          $data_monedas_tkn = ""; //token_monedas
          $data_monedas_codigo = ""; //codigo
          $data_monedas_moneda = ""; //moneda
          $data_monedas_decimales = ""; //decimales

          if ($value->calculo == "cuota") {
            //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
            $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
              ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $value->token_catalogo_impuesto])->get();

            foreach ($queryCurrencyImp as $vMon) {
              $data_monedas_tkn = $vMon->token_monedas;
              $data_monedas_codigo = $vMon->codigo;
              $data_monedas_moneda = $vMon->moneda;
              $data_monedas_decimales = $vMon->decimales;
              $data_tipo_cambio_simple = $value->tipo_cambio_imp;
              $data_tipo_cambio_format = "$" . number_format($value->tipo_cambio_imp, $vMon->decimales, '.', ',');
            }
          }

          $arrayforeach = array(
            "token_catalogo_impuesto" => $value->token_catalogo_impuesto,
            "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($value->fecha_registro),
            "folio_impuesto" => $folio_impuesto,
            "abreviacion_impuesto" => $JwtAuth->desencriptar($value->abreviacion_impuesto),
            "concepto_impuesto" => $JwtAuth->desencriptar($value->concepto_impuesto),
            "modulo" => $value->modulo != NULL ? $JwtAuth->desencriptar($value->modulo) : "",
            "modulo_respaldo" => $value->modulo != NULL ? $JwtAuth->desencriptar($value->modulo) : "",
            "nivel_aplicacion" => $value->nivel_aplicacion,
            "nivel_aplicacion_respaldo" => $value->nivel_aplicacion,
            "catalogo_sat" => $value->catalogo_sat != NULL ? $value->catalogo_sat : "",
            "catalogo_sat_respaldo" => $value->catalogo_sat != NULL ? $value->catalogo_sat : "",
            "tipo_impuesto" => $value->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
            "tipo_impuesto_respaldo" => $value->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
            "calculo" => $value->calculo,
            "calculo_respaldo" => $value->calculo,
            "impuesto_importe_simbolo" => $value->calculo == "tasa" ? "%" : "$",
            "importe" => $value->importe,
            "importe_respaldo" => $value->importe,
            "txtimporte" => $importe_imp,
            "tipo_cambio" => $data_tipo_cambio_simple,
            "tipo_cambio_respaldo" => $data_tipo_cambio_simple,
            "txttipo_cambio" => $data_tipo_cambio_format,
            //moneda_registrada_imp
            "monedas_tkn" => $data_monedas_tkn,
            "monedas_tkn_respaldo" => $data_monedas_tkn,
            "monedas_codigo" => $data_monedas_codigo,
            "monedas_moneda" => $data_monedas_moneda,
            "monedas_moneda_respaldo" => $data_monedas_moneda,
            "monedas_decimales" => $data_monedas_decimales,
            "base_aplicable" => $value->base,
            "base_aplicable_respaldo" => $value->base,
            "desglose" => $value->desglose == TRUE ? true : false,
            "desglose_respaldo" => $value->desglose == TRUE ? true : false,
            "gl_por_pagarcobrar" => $value->gl_por_pagarcobrar != NULL ? $value->gl_por_pagarcobrar : "",
            "gl_por_pagarcobrar_respaldo" => $value->gl_por_pagarcobrar != NULL ? $value->gl_por_pagarcobrar : "",
            "gl_pagada_o_cobrada" => $value->gl_pagada_o_cobrada != NULL ? $value->gl_pagada_o_cobrada : "",
            "gl_pagada_o_cobrada_respaldo" => $value->gl_pagada_o_cobrada != NULL ? $value->gl_pagada_o_cobrada : "",
            "observaciones" => $JwtAuth->desencriptar($value->observaciones),
            "observaciones_respaldo" => $JwtAuth->desencriptar($value->observaciones),
            "bool_impuestos_update" => false,
            "vinculacion" => false,
          );
          $impuestoInfo[] = $arrayforeach;
        }

        $dataMensaje = array(
          "status" => "success",
          "code" => 200,
          "datosImpuesto" => $impuestoInfo
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoActualizar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_catalogo_impuesto' => 'required|string',
      'impuesto_modulo' => 'string',
      'impuesto_nivel' => 'required|string',
      'impuesto_clave_sat' => 'string',
      'impuesto_tipo' => 'required|string',
      'impuesto_tasa_cuota' => 'required|string',
      'impuesto_importe' => 'required|string',
      'tipo_cambio' => 'string',
      'moneda_impuesto' => 'string',
      'impuesto_aplica_sobre' => 'required|string',
      'impuesto_desglose' => 'required|boolean',
      'impuesto_gl_por_pagar_o_cobrar' => 'string',
      'impuesto_gl_efectivamente_pagada_o_cobrada' => 'string',
      'impuesto_observaciones' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Existen errores en los datos que desea actualizar',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $token_catalogo_impuesto = $request->input('token_catalogo_impuesto');
      $impuesto_modulo = $request->input('impuesto_modulo');
      $impuesto_nivel = $request->input('impuesto_nivel');
      $impuesto_clave_sat = $request->input('impuesto_clave_sat');
      $impuesto_tipo = $request->input('impuesto_tipo');
      $impuesto_tasa_cuota = $request->input('impuesto_tasa_cuota');
      $impuesto_importe = $request->input('impuesto_importe');
      $tipo_cambio = $request->input('tipo_cambio');
      $moneda_impuesto = $request->input('moneda_impuesto');
      $impuesto_aplica_sobre = $request->input('impuesto_aplica_sobre');
      $impuesto_desglose = $request->input('impuesto_desglose');
      $impuesto_gl_por_pagar_o_cobrar = $request->input('impuesto_gl_por_pagar_o_cobrar');
      $impuesto_gl_efectivamente_pagada_o_cobrada = $request->input('impuesto_gl_efectivamente_pagada_o_cobrada');
      $impuesto_observaciones = $request->input('impuesto_observaciones');

      if (
        isset($token_catalogo_impuesto) && !empty($token_catalogo_impuesto) &&
        isset($impuesto_nivel) && !empty($impuesto_nivel) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_nivel) &&
        isset($impuesto_tipo) && !empty($impuesto_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_tipo) &&
        isset($impuesto_tasa_cuota) && !empty($impuesto_tasa_cuota) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_tasa_cuota) &&
        isset($impuesto_importe) && !empty($impuesto_importe) && isset($impuesto_aplica_sobre) && !empty($impuesto_aplica_sobre) &&
        preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_aplica_sobre) && isset($impuesto_desglose) && is_bool($impuesto_desglose) &&
        isset($impuesto_observaciones) && !empty($impuesto_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_observaciones)
      ) {
        $sql_impuesto_modulo = NULL;
        if (isset($impuesto_modulo) && !empty($impuesto_modulo)) {
          if (preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_modulo)) {
            $sql_impuesto_modulo = $JwtAuth->encriptar($impuesto_modulo);
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en modulo de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        }
        //echo $sql_impuesto_modulo;exit;
        $sql_impuesto_clave_sat = NULL;
        //echo $impuesto_clave_sat;
        if (isset($impuesto_clave_sat) && !empty($impuesto_clave_sat)) {
          if (preg_match($JwtAuth->filtroNumericoSimple(), $impuesto_clave_sat)) {
            //echo $impuesto_clave_sat;exit;
            $sql_impuesto_clave_sat = $impuesto_clave_sat;
            //echo "nada";exit;
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en clave de sat de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        }
        $sql_tipo_cambio = NULL;
        $sql_moneda = NULL;
        if ($impuesto_tasa_cuota == "tasa") {
          if (!preg_match($JwtAuth->filtroPorcentaje(), $impuesto_importe)) {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        } else {
          if (preg_match($JwtAuth->filtroCostoPrecio(), $impuesto_importe)) {
            if (isset($tipo_cambio) && !empty($tipo_cambio)) {
              if (preg_match($JwtAuth->filtroCostoPrecio(), $tipo_cambio)) {
                $sql_tipo_cambio = $tipo_cambio;
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Error en tipo de cambio de impuesto, por favor verifique su información o comuniquese a soporte"
                );
              }
            }
            if (isset($moneda_impuesto) && !empty($moneda_impuesto)) {
              $queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?", [$moneda_impuesto]);
              if (count($queryMoneda) > 0 && count($queryMoneda) == 1) {
                $sql_moneda = end($queryMoneda)->id;
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Error en moneda de impuesto, por favor verifique su información o comuniquese a soporte"
                );
              }
            }
            //$moneda_impuesto = $parametrosArray["moneda_impuesto"];
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        }

        $sql_impuesto_gl_por_pagar_o_cobrar = NULL;
        if (isset($impuesto_gl_por_pagar_o_cobrar) && !empty($impuesto_gl_por_pagar_o_cobrar)) {
          if (preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_gl_por_pagar_o_cobrar)) {
            $sql_impuesto_gl_por_pagar_o_cobrar = $impuesto_gl_por_pagar_o_cobrar;
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en GL por pagar o cobrar, por favor verifique su información o comuniquese a soporte"
            );
          }
        }

        $sql_impuesto_gl_efectivamente_pagada_o_cobrada = NULL;
        if (isset($impuesto_gl_efectivamente_pagada_o_cobrada) && !empty($impuesto_gl_efectivamente_pagada_o_cobrada)) {
          if (preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_gl_efectivamente_pagada_o_cobrada)) {
            $sql_impuesto_gl_efectivamente_pagada_o_cobrada = $impuesto_gl_efectivamente_pagada_o_cobrada;
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en GL efectivamente pagada o cobrada, por favor verifique su información o comuniquese a soporte"
            );
          }
        }

        $queryImp = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
          ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $token_catalogo_impuesto, 'cont_impuestos_catalogo.imp_status' => TRUE, 'emp.empresa_token' => $empresa])->get();

        foreach ($queryImp as $vImp) {
          $folio_impuesto = 'IMP-' . ($vImp->post_folio == NULL ? $JwtAuth->generarFolio($vImp->folio_impuesto) : $JwtAuth->generarFolio($vImp->folio_impuesto) . '-' . $vImp->post_folio);
          $sql_ret_tras = $impuesto_tipo == "retenido" ? "rete" : "tras";
          $impUpdate = ImpuestosModelo::find(1);
          $impUpdate->where("token_catalogo_impuesto", $vImp->token_catalogo_impuesto)->update([
            "modulo" => $sql_impuesto_modulo,
            "nivel_aplicacion" => $impuesto_nivel,
            "catalogo_sat" => $sql_impuesto_clave_sat,
            "tipo_impuesto" => $sql_ret_tras,
            "calculo" => $impuesto_tasa_cuota,
            "importe" => $impuesto_importe,
            "tipo_cambio_imp" => $sql_tipo_cambio,
            "moneda_registrada_imp" => $sql_moneda,
            "base" => $impuesto_aplica_sobre,
            "desglose" => $impuesto_desglose,
            "gl_por_pagarcobrar" => $sql_impuesto_gl_por_pagar_o_cobrar,
            "gl_pagada_o_cobrada" => $sql_impuesto_gl_efectivamente_pagada_o_cobrada,
            "observaciones" => $JwtAuth->encriptar($impuesto_observaciones),
          ]);

          if ($impUpdate) {
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "El impuesto con folio " . $folio_impuesto . " ha sido actualizado satisfactoriamente"
            );
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en actualizacion de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        }
      } else {
        $mensaje_error = "";
        if (!isset($token_catalogo_impuesto) || empty($token_catalogo_impuesto)) $mensaje_error = "Error en impuesto seleccionado, por favor verifique su información o comuniquese a soporte";
        if (!isset($impuesto_nivel) || empty($impuesto_nivel) || !preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_nivel)) $mensaje_error = "Error en nivel de aplicación de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!isset($impuesto_tipo) || empty($impuesto_tipo) || !preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_tipo)) $mensaje_error = "Error en tipo de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!isset($impuesto_tasa_cuota) || empty($impuesto_tasa_cuota) || !preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_tasa_cuota)) $mensaje_error = "Error en tasa o cuota de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!isset($impuesto_importe) || empty($impuesto_importe)) $mensaje_error = "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!isset($impuesto_aplica_sobre) || empty($impuesto_aplica_sobre) || !preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_aplica_sobre)) $mensaje_error = "Error en aplicacion de impuesto, por favor verifique su información o comuniquese a soporte";
        if (!isset($impuesto_desglose) || !is_bool($impuesto_desglose)) $mensaje_error = "Error en selección sobre desglose de impuesto, por favor verifique su información o comuniquese a soporte";
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoHabilitar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_catalogo_impuesto' => 'required|string'
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
      $token_catalogo_impuesto = $request->input('token_catalogo_impuesto');

      if (isset($token_catalogo_impuesto) && !empty($token_catalogo_impuesto)) {
        $queryImp = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
          ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $token_catalogo_impuesto, 'cont_impuestos_catalogo.imp_status' => TRUE, 'emp.empresa_token' => $empresa])->get();

        foreach ($queryImp as $vImp) {
          $imp_folio = 'IMP-' . ($vImp->post_folio == NULL ? $JwtAuth->generarFolio($vImp->folio_impuesto) : $JwtAuth->generarFolio($vImp->folio_impuesto) . '-' . $vImp->post_folio);
          $impDelete = ImpuestosModelo::find(1);
          $impDelete->where("token_catalogo_impuesto", $vImp->token_catalogo_impuesto)->update(["habilitado_imp" => TRUE]);

          if ($impDelete) {
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "El impuesto con folio " . $imp_folio . " ha sido habilitado satisfactoriamente"
            );
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en registro de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en impuesto registrado, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoDeshabilitar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_catalogo_impuesto' => 'required|string'
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
      $token_catalogo_impuesto = $request->input('token_catalogo_impuesto');
      
      if (isset($token_catalogo_impuesto) && !empty($token_catalogo_impuesto)) {
        $queryImp = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
          ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $token_catalogo_impuesto, 'cont_impuestos_catalogo.imp_status' => TRUE, 'emp.empresa_token' => $empresa])->get();

        foreach ($queryImp as $vImp) {
          $imp_folio = 'IMP-' . ($vImp->post_folio == NULL ? $JwtAuth->generarFolio($vImp->folio_impuesto) : $JwtAuth->generarFolio($vImp->folio_impuesto) . '-' . $vImp->post_folio);
          $impDelete = ImpuestosModelo::find(1);
          $impDelete->where("token_catalogo_impuesto", $vImp->token_catalogo_impuesto)->update(["habilitado_imp" => FALSE]);

          if ($impDelete) {
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "El impuesto con folio " . $imp_folio . " ha sido deshabilitado satisfactoriamente"
            );
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en registro de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en impuesto registrado, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoPapeleraSave(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_catalogo_impuesto' => 'required|string'
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
      //da_te_default_timezone_set('America/Mexico_City');
      $token_catalogo_impuesto = $request->input('token_catalogo_impuesto');
      
      if (isset($token_catalogo_impuesto) && !empty($token_catalogo_impuesto)) {
        $queryImp = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
          ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $token_catalogo_impuesto, 'cont_impuestos_catalogo.imp_status' => TRUE, 'emp.empresa_token' => $empresa])->get();

        foreach ($queryImp as $vImp) {
          $imp_folio = 'IMP-' . ($vImp->post_folio == NULL ? $JwtAuth->generarFolio($vImp->folio_impuesto) : $JwtAuth->generarFolio($vImp->folio_impuesto) . '-' . $vImp->post_folio);
          $impDelete = ImpuestosModelo::find(1);
          $impDelete->where("token_catalogo_impuesto", $vImp->token_catalogo_impuesto)->update(["imp_status" => FALSE, "imp_fecha_delete" => time()]);

          if ($impDelete) {
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "El impuesto con folio " . $imp_folio . " ha sido eliminado satisfactoriamente"
            );
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en registro de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en impuesto registrado, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoImpuestosDel(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $catImp = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
    ->where([
      'cont_impuestos_catalogo.imp_status' => FALSE, 
      'emp.empresa_token' => $empresa
    ])
    ->get();

    if ($catImp->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron impuestos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaImpuestos = array();
      
      foreach ($catImp as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $folio_impuesto = 'IMP-' . ($value->post_folio == NULL ? $JwtAuth->generarFolio($value->folio_impuesto) : $JwtAuth->generarFolio($value->folio_impuesto) . '-' . $value->post_folio);
        $importe_imp = $value->calculo == "cuota" ? "$" . $value->importe : $value->importe . "%";

        $data_tipo_cambio = "";
        $data_monedas_tkn = ""; //token_monedas
        $data_monedas_codigo = ""; //codigo
        $data_monedas_moneda = ""; //moneda
        $data_monedas_decimales = ""; //decimales

        if ($value->calculo == "cuota") {
          //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
          $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
            ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $value->token_catalogo_impuesto])->get();

          foreach ($queryCurrencyImp as $vMon) {
            $data_monedas_tkn = $vMon->token_monedas;
            $data_monedas_codigo = $vMon->codigo;
            $data_monedas_moneda = $vMon->moneda;
            $data_monedas_decimales = $vMon->decimales;
            $data_tipo_cambio = "$" . number_format($value->tipo_cambio_imp, $vMon->decimales, '.', ',');
          }
        }

        $arrayforeach = array(
          "token_catalogo_impuesto" => $value->token_catalogo_impuesto,
          "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($value->fecha_registro),
          "folio_impuesto" => $folio_impuesto,
          "abreviacion_impuesto" => $JwtAuth->desencriptar($value->abreviacion_impuesto),
          "concepto_impuesto" => $JwtAuth->desencriptar($value->concepto_impuesto),
          "modulo" => $value->modulo != NULL ? $JwtAuth->desencriptar($value->modulo) : null,
          "nivel_aplicacion" => $value->nivel_aplicacion,
          "catalogo_sat" => $value->catalogo_sat != NULL ? $value->catalogo_sat : null,
          "tipo_impuesto" => $value->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
          "calculo" => $value->calculo,
          "importe" => $value->importe,
          "txtimporte" => $importe_imp,
          "tipo_cambio" => $data_tipo_cambio,
          //moneda_registrada_imp
          "monedas_tkn" => $data_monedas_tkn,
          "monedas_codigo" => $data_monedas_codigo,
          "monedas_moneda" => $data_monedas_moneda,
          "monedas_decimales" => $data_monedas_decimales,
          "base_aplicable" => $value->base,
          "desglose" => $value->desglose == TRUE ? true : false,
          "gl_por_pagarcobrar" => $value->gl_por_pagarcobrar != NULL ? $value->gl_por_pagarcobrar : null,
          "gl_pagada_o_cobrada" => $value->gl_pagada_o_cobrada != NULL ? $value->gl_pagada_o_cobrada : null,
          "observaciones" => $JwtAuth->desencriptar($value->observaciones),
          "habilitado" => $value->habilitado_imp == TRUE ? true : false,
          "vinculacion" => false,
        );
        $listaImpuestos[] = $arrayforeach;
      }

      $dataMensaje = array(
        "status" => "success",
        "code" => 200,
        "impuestos" => $listaImpuestos
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoPapeleraRestaurar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_catalogo_impuesto' => 'required|string'
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
      $token_catalogo_impuesto = $request->input('token_catalogo_impuesto');
      
      if (isset($token_catalogo_impuesto) && !empty($token_catalogo_impuesto)) {
        $queryImp = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
          ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $token_catalogo_impuesto, 'cont_impuestos_catalogo.imp_status' => FALSE, 'emp.empresa_token' => $empresa])->get();

        foreach ($queryImp as $vImp) {
          $imp_folio = 'IMP-' . ($vImp->post_folio == NULL ? $JwtAuth->generarFolio($vImp->folio_impuesto) : $JwtAuth->generarFolio($vImp->folio_impuesto) . '-' . $vImp->post_folio);
          $impDelete = ImpuestosModelo::find(1);
          $impDelete->where("token_catalogo_impuesto", $vImp->token_catalogo_impuesto)->update(["imp_status" => TRUE, "imp_fecha_delete" => NULL]);

          if ($impDelete) {
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "El impuesto con folio " . $imp_folio . " ha sido restaurado satisfactoriamente"
            );
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Error en registro de impuesto, por favor verifique su información o comuniquese a soporte"
            );
          }
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en impuesto registrado, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoDeletePerm(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'token_catalogo_impuesto' => 'required|string'
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
      $token_catalogo_impuesto = $request->input('token_catalogo_impuesto');
      
      if (isset($token_catalogo_impuesto) && !empty($token_catalogo_impuesto)) {
        $queryImp = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
          ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $token_catalogo_impuesto, 'cont_impuestos_catalogo.imp_status' => FALSE, 'emp.empresa_token' => $empresa])->get();

        if (count($queryImp) > 0) {
          foreach ($queryImp as $vImp) {
            $imp_folio = 'IMP-' . ($vImp->post_folio == NULL ? $JwtAuth->generarFolio($vImp->folio_impuesto) : $JwtAuth->generarFolio($vImp->folio_impuesto) . '-' . $vImp->post_folio);
            $impDelete = ImpuestosModelo::find(1);
            $impDelete->where("token_catalogo_impuesto", $vImp->token_catalogo_impuesto)->delete();

            if ($impDelete) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "El impuesto con folio " . $imp_folio . " ha sido eliminado satisfactoriamente"
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Error en registro de impuesto, por favor verifique su información o comuniquese a soporte"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Impuesto no registrado, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en impuesto registrado, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
