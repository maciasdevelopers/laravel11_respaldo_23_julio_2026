<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ImpuestosModelo;

class Cont_EsquemasImpuestosController extends Controller{
  public function impuestoEsquemaRegistro(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'impuesto_esquema' => 'required|string',
      'impuestos_lista' => 'required|array'
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
      $fecha_sistema = time();
      $impuesto_esquema = $request->input('impuesto_esquema');
      $impuestos_lista = $request->input('impuestos_lista');

      $OKImpEsquema = isset($impuesto_esquema) && !empty($impuesto_esquema) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_esquema);
      $OKImpLista = isset($impuestos_lista) && !empty($impuestos_lista);
      
      if ($OKImpEsquema && $OKImpLista) {
        $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,users.id AS userr,emp.zona_horaria,people.paterno,
                      people.materno,people.nombre,people.denominacion_rs,people.sitio_web FROM main_empresas AS emp  
                      JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                      WHERE emp.empresa_token = ? AND emp.persona = people.id AND emp.id = empuser.empresa 
                      AND empuser.usuario = users.id AND users.usuario_token= ?", [$empresa, $usuario]);
        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

        $folioSistema = DB::select("SELECT MAX(impes.esquema_folio) AS esquema_folio FROM cont_impuestos_esquema AS impes
                  JOIN main_empresas AS emp WHERE impes.empresa = emp.id AND emp.empresa_token = ?", [$empresa]);

        $sql_folio = count($folioSistema) == 0 ? 1 : $folioSistema[0]->esquema_folio + 1;
        $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($sql_folio);

        //echo  $sql_impuesto_clave_sat; exit; 
        $token_esquema = $JwtAuth->encriptarToken($impuesto_esquema, $fecha_sistema);
        $queryInsert = DB::table("cont_impuestos_esquema")
          ->insert(
            array(
              "esquema_token" => $token_esquema,
              "esquema_folio" => $sql_folio,
              "esquema_date_insert" => $fecha_sistema,
              "esquema_concepto" => $JwtAuth->encriptar($impuesto_esquema),
              "status_esquema" => TRUE,
              "empresa" => $selectEmp[0]->id,
              "usuario_registra" => $selectEmp[0]->userr,
              "habilitado" => TRUE
            )
          );
        if ($queryInsert) {
          $queryEsquema = DB::select("SELECT id FROM cont_impuestos_esquema WHERE esquema_token = ?", [$token_esquema]);
          $contador_registro = 0;
          for ($i = 0; $i < count($impuestos_lista); $i++) {
            $tkn_impuesto = $impuestos_lista[$i]["token_catalogo_impuesto"];
            $queryImpuesto = DB::select("SELECT id FROM cont_impuestos_catalogo WHERE token_catalogo_impuesto = ?", [$tkn_impuesto]);

            $queryVincular = DB::table("cont_impuestos_esquema_vinculo")
              ->insert(
                array(
                  "esquema_vinculado" => $queryEsquema[0]->id,
                  "impuesto_vinculado" => $queryImpuesto[0]->id,
                )
              );

            if ($queryVincular) {
              ++$contador_registro;
            }
          }

          if ($contador_registro == count($impuestos_lista)) {
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "Este esquema de impuestos ha sido registrado satisfactoriamente con el folio " . $folio_esquema
            );
          } else {
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "Error en registro de esquema de impuestos, por favor verifique su información o comuniquese a soporte"
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Error en registro de impuesto, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $mensaje_error = "";
        if (!$OKImpEsquema) $mensaje_error = "Error en descripcion de esquema de impuestos seleccionado, por favor verifique su información o comuniquese a soporte";
        if (!$OKImpLista) $mensaje_error = "Error en esquema de impuestos seleccionado, por favor verifique su información o comuniquese a soporte";

        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaCatalogo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
    ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
    ->where(['esqImp.status_esquema' => TRUE, 'emp.empresa_token' => $empresa])
    ->get();

    if ($queryEsquema->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron esquemas de impuestos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaEsquemas = array();
      
      foreach ($queryEsquema as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($value->esquema_folio);
        $arrayforeach = array(
          "esquema_token" => $value->esquema_token,
          "esquema_folio" => $folio_esquema,
          "esquema_date_insert" => $JwtAuth->mostrarUnixAFechaMexico($value->esquema_date_insert),
          "esquema_concepto" => $JwtAuth->desencriptar($value->esquema_concepto),
          "habilitado" => $value->habilitado == TRUE ? true : false,
        );
        $listaEsquemas[] = $arrayforeach;
      }

      $dataMensaje = array(
        "status" => "success",
        "code" => 200,
        "esquemas" => $listaEsquemas
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaCatalogoEnabled(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
    
    $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
    ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
    ->where([
      'esqImp.status_esquema' => TRUE, 
      'esqImp.habilitado' => TRUE, '
      emp.empresa_token' => $empresa
    ])
    ->get();

    if ($queryEsquema->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron esquemas de impuestos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaEsquemas = array();
      
      foreach ($queryEsquema as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($value->esquema_folio);
        $arrayforeach = array(
          "esquema_token" => $value->esquema_token,
          "esquema_folio" => $folio_esquema,
          "esquema_date_insert" => $JwtAuth->mostrarUnixAFechaMexico($value->esquema_date_insert),
          "esquema_concepto" => $JwtAuth->desencriptar($value->esquema_concepto),
          "habilitado" => true
        );
        $listaEsquemas[] = $arrayforeach;
      }
      $dataMensaje = array(
        "status" => "success",
        "code" => 200,
        "esquemas" => $listaEsquemas
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaCatalogoForVentas(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
    ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
    ->where([
      'esqImp.status_esquema' => TRUE, 
      'emp.empresa_token' => $empresa
    ])
    ->get();
    
    if ($queryEsquema->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontro esquema de impuestos, por favor verifique su información o comuniquese a soporte'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaEsquemas = array();
      
      foreach ($queryEsquema as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($value->esquema_folio);

        $listaImpuestos = array();
        $queryImpVinc = DB::table("cont_impuestos_esquema AS esqImp")
          ->join('cont_impuestos_esquema_vinculo AS vinc', 'esqImp.id', 'vinc.esquema_vinculado')
          ->join('cont_impuestos_catalogo AS catImp', 'vinc.impuesto_vinculado', 'catImp.id')
          ->where(['esqImp.esquema_token' => $value->esquema_token])
          ->get();

        if (count($queryImpVinc) > 0) {
          foreach ($queryImpVinc as $impCat) {
            $folio_impuesto = 'IMP-' . ($impCat->post_folio == NULL ? $JwtAuth->generarFolio($impCat->folio_impuesto) : $JwtAuth->generarFolio($impCat->folio_impuesto) . '-' . $impCat->post_folio);
            $importe_imp = $impCat->calculo == "cuota" ? "$" . $impCat->importe : $impCat->importe . "%";

            $data_tipo_cambio = "";
            $data_monedas_tkn = ""; //token_monedas
            $data_monedas_codigo = ""; //codigo
            $data_monedas_moneda = ""; //moneda
            $data_monedas_decimales = ""; //decimales

            if ($impCat->calculo == "cuota") {
              //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
              $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
                ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $impCat->token_catalogo_impuesto])->get();

              foreach ($queryCurrencyImp as $vMon) {
                $data_monedas_tkn = $vMon->token_monedas;
                $data_monedas_codigo = $vMon->codigo;
                $data_monedas_moneda = $vMon->moneda;
                $data_monedas_decimales = $vMon->decimales;
                $data_tipo_cambio = "$" . number_format($impCat->tipo_cambio_imp, $vMon->decimales, '.', ',');
              }
            }

            $arrayforeach = array(
              "token_catalogo_impuesto" => $impCat->token_catalogo_impuesto,
              "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($impCat->fecha_registro),
              "folio_impuesto" => $folio_impuesto,
              "abreviacion_impuesto" => $JwtAuth->desencriptar($impCat->abreviacion_impuesto),
              "concepto_impuesto" => $JwtAuth->desencriptar($impCat->concepto_impuesto),
              "modulo" => $impCat->modulo != NULL ? $JwtAuth->desencriptar($impCat->modulo) : null,
              "nivel_aplicacion" => $impCat->nivel_aplicacion,
              "catalogo_sat" => $impCat->catalogo_sat != NULL ? $impCat->catalogo_sat : null,
              "tipo_impuesto" => $impCat->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
              "calculo" => $impCat->calculo,
              "importe" => $impCat->importe,
              "txtimporte" => $importe_imp,
              "valor_para_venta" => 0.00,
              "valor_para_ventaFormat" => "$" . number_format(0.00, 2, '.', ','),
              "tipo_cambio" => $data_tipo_cambio,
              //moneda_registrada_imp
              "monedas_tkn" => $data_monedas_tkn,
              "monedas_codigo" => $data_monedas_codigo,
              "monedas_moneda" => $data_monedas_moneda,
              "monedas_decimales" => $data_monedas_decimales,
              "base_aplicable" => $impCat->base,
              "desglose" => $impCat->desglose == TRUE ? true : false,
              "gl_por_pagarcobrar" => $impCat->gl_por_pagarcobrar != NULL ? $impCat->gl_por_pagarcobrar : null,
              "gl_pagada_o_cobrada" => $impCat->gl_pagada_o_cobrada != NULL ? $impCat->gl_pagada_o_cobrada : null,
              "observaciones" => $JwtAuth->desencriptar($impCat->observaciones),
              "habilitado" => $impCat->habilitado_imp == TRUE ? true : false,
              "vinculacion" => false,
            );
            $listaImpuestos[] = $arrayforeach;
          }

          $arrayforeach = array(
            "esquema_token" => $value->esquema_token,
            "esquema_folio" => $folio_esquema,
            "esquema_date_insert" => $JwtAuth->mostrarUnixAFechaMexico($value->esquema_date_insert),
            "esquema_concepto" => $JwtAuth->desencriptar($value->esquema_concepto),
            "impuestos" => $listaImpuestos,
            "habilitado" => true
          );
          $listaEsquemas[] = $arrayforeach;
        }
      }

      $dataMensaje = array(
        "status" => "success",
        "code" => 200,
        "esquemas" => $listaEsquemas
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaDetalle(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'esquema_token' => 'required|string'
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
      $esquema_token = $request->input('esquema_token');

      if (isset($esquema_token) && !empty($esquema_token)) {
        $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
        ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
        ->where([
          'esqImp.esquema_token' => $esquema_token, 
          'esqImp.status_esquema' => TRUE, 
          'emp.empresa_token' => $empresa
        ])
        ->get();

        if (count($queryEsquema) > 0 && count($queryEsquema) == 1) {
          foreach ($queryEsquema as $value) {
            //da_te_default_timezone_set($value->zona_horaria);
            $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($value->esquema_folio);

            $listaImpuestos = array();
            $impuestosEnabled = ImpuestosModelo::join('main_empresas AS emp', 'cont_impuestos_catalogo.empresa', 'emp.id')
              ->where(['cont_impuestos_catalogo.habilitado_imp' => TRUE, 'cont_impuestos_catalogo.imp_status' => TRUE, 'emp.empresa_token' => $empresa])
              ->get();
            foreach ($impuestosEnabled as $impCat) {
              $folio_impuesto = 'IMP-' . ($impCat->post_folio == NULL ? $JwtAuth->generarFolio($impCat->folio_impuesto) : $JwtAuth->generarFolio($impCat->folio_impuesto) . '-' . $impCat->post_folio);
              $importe_imp = $impCat->calculo == "cuota" ? "$" . $impCat->importe : $impCat->importe . "%";

              $data_tipo_cambio = "";
              $data_monedas_tkn = ""; //token_monedas
              $data_monedas_codigo = ""; //codigo
              $data_monedas_moneda = ""; //moneda
              $data_monedas_decimales = ""; //decimales

              if ($impCat->calculo == "cuota") {
                //$queryMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_impuesto]);
                $queryCurrencyImp = ImpuestosModelo::join('teci_catalogo_monedas AS money', 'cont_impuestos_catalogo.moneda_registrada_imp', 'money.id')
                  ->where(['cont_impuestos_catalogo.token_catalogo_impuesto' => $impCat->token_catalogo_impuesto])->get();

                foreach ($queryCurrencyImp as $vMon) {
                  $data_monedas_tkn = $vMon->token_monedas;
                  $data_monedas_codigo = $vMon->codigo;
                  $data_monedas_moneda = $vMon->moneda;
                  $data_monedas_decimales = $vMon->decimales;
                  $data_tipo_cambio = "$" . number_format($impCat->tipo_cambio_imp, $vMon->decimales, '.', ',');
                }
              }

              $queryImpVinc = DB::table("cont_impuestos_esquema AS esqImp")
                ->join('cont_impuestos_esquema_vinculo AS vinc', 'esqImp.id', 'vinc.esquema_vinculado')
                ->join('cont_impuestos_catalogo AS catImp', 'vinc.impuesto_vinculado', 'catImp.id')
                ->where(['esqImp.esquema_token' => $value->esquema_token, 'catImp.token_catalogo_impuesto' => $impCat->token_catalogo_impuesto])
                ->get();

              $arrayforeach = array(
                "token_catalogo_impuesto" => $impCat->token_catalogo_impuesto,
                "fecha_registro" => $JwtAuth->mostrarUnixAFechaMexico($impCat->fecha_registro),
                "folio_impuesto" => $folio_impuesto,
                "abreviacion_impuesto" => $JwtAuth->desencriptar($impCat->abreviacion_impuesto),
                "concepto_impuesto" => $JwtAuth->desencriptar($impCat->concepto_impuesto),
                "modulo" => $impCat->modulo != NULL ? $JwtAuth->desencriptar($impCat->modulo) : null,
                "nivel_aplicacion" => $impCat->nivel_aplicacion,
                "catalogo_sat" => $impCat->catalogo_sat != NULL ? $impCat->catalogo_sat : null,
                "tipo_impuesto" => $impCat->tipo_impuesto == "rete" ? 'retenido' : 'trasladado',
                "calculo" => $impCat->calculo,
                "importe" => $impCat->importe,
                "txtimporte" => $importe_imp,
                "tipo_cambio" => $data_tipo_cambio,
                //moneda_registrada_imp
                "monedas_tkn" => $data_monedas_tkn,
                "monedas_codigo" => $data_monedas_codigo,
                "monedas_moneda" => $data_monedas_moneda,
                "monedas_decimales" => $data_monedas_decimales,
                "base_aplicable" => $impCat->base,
                "desglose" => $impCat->desglose == TRUE ? true : false,
                "gl_por_pagarcobrar" => $impCat->gl_por_pagarcobrar != NULL ? $impCat->gl_por_pagarcobrar : null,
                "gl_pagada_o_cobrada" => $impCat->gl_pagada_o_cobrada != NULL ? $impCat->gl_pagada_o_cobrada : null,
                "observaciones" => $JwtAuth->desencriptar($impCat->observaciones),
                "vinculado" => count($queryImpVinc) > 0 ? true : false,
                "habilitado" => true,
              );
              $listaImpuestos[] = $arrayforeach;
            }

            $arrayforeach = array(
              "esquema_token" => $value->esquema_token,
              "esquema_folio" => $folio_esquema,
              "esquema_date_insert" => $JwtAuth->mostrarUnixAFechaMexico($value->esquema_date_insert),
              "esquema_concepto" => $JwtAuth->desencriptar($value->esquema_concepto),
              "esquema_concepto_respaldo" => $JwtAuth->desencriptar($value->esquema_concepto),
              "impuestos" => $listaImpuestos,
              "habilitado" => $value->habilitado == TRUE ? true : false,
            );
            $listaEsquemas[] = $arrayforeach;
          }
          $dataMensaje = array(
            "status" => "success",
            "code" => 200,
            "esquemas" => $listaEsquemas
          );
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "No se encontro esquema de impuestos, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error al seleccionar esquema de impuestos, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaActualizar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'esquema_token' => 'required|string',
      'impuesto_esquema' => 'required|string'
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
      $esquema_token = $request->input('esquema_token');
      $impuesto_esquema = $request->input('impuesto_esquema');
      
      $OKImpEsquema = isset($impuesto_esquema) && !empty($impuesto_esquema) && preg_match($JwtAuth->filtroAlfaNumerico(), $impuesto_esquema);
      //$OKImpLista = isset($impuestos_lista) && !empty($impuestos_lista);
      $OKImpEsquToken = isset($esquema_token) && !empty($esquema_token);

      if ($OKImpEsquema && $OKImpEsquToken) {
        $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
          ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
          ->where(['esqImp.esquema_token' => $esquema_token, 'esqImp.status_esquema' => TRUE, 'emp.empresa_token' => $empresa])
          ->get();

        if (count($queryEsquema) > 0 && count($queryEsquema) == 1) {
          foreach ($queryEsquema as $value) {
            $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($value->esquema_folio);
            $esqUpdate = DB::table("cont_impuestos_esquema")
              ->where(["esquema_token" => $value->esquema_token])
              ->limit(1)->update(
                array(
                  "esquema_concepto" => $JwtAuth->encriptar($impuesto_esquema)
                )
              );

            if ($esqUpdate) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Esquema de impuestos con folio " . $folio_esquema . " ha sido actualizado satisfactoriamente"
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "No se actualizo descripcion de esquema de impuestos, por favor verifique su información o comuniquese a soporte"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "No se encontro esquema de impuestos, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $mensaje_error = "";
        if (!$OKImpEsquema) $mensaje_error = "Error en descripcion de esquema de impuestos seleccionado, por favor verifique su información o comuniquese a soporte";
        if (!$OKImpEsquToken) $mensaje_error = "Error en esquema de impuestos seleccionado, por favor verifique su información o comuniquese a soporte";
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaActualizarVincular(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'esquema_token' => 'required|string',
      'token_catalogo_impuesto' => 'required|string'
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
      $esquema_token = $request->input('esquema_token');
      $token_catalogo_impuesto = $request->input('token_catalogo_impuesto');
      
      $OKEsqToken = isset($esquema_token) && !empty($esquema_token);
      $OKCatImp = isset($token_catalogo_impuesto) && !empty($token_catalogo_impuesto);

      if ($OKEsqToken && $OKCatImp) {
        $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
          ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
          ->where(['esqImp.esquema_token' => $esquema_token, 'esqImp.status_esquema' => TRUE, 'emp.empresa_token' => $empresa])
          ->get();

        if (count($queryEsquema) > 0 && count($queryEsquema) == 1) {
          foreach ($queryEsquema as $value) {
            $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($value->esquema_folio);
            $esqIdent = DB::select("SELECT id FROM cont_impuestos_esquema WHERE esquema_token = ?", [$value->esquema_token]);
            $catIdent = DB::select("SELECT id FROM cont_impuestos_catalogo WHERE token_catalogo_impuesto = ?", [$token_catalogo_impuesto]);
            $queryVincular = DB::table("cont_impuestos_esquema_vinculo")
              ->insert(
                array(
                  "esquema_vinculado" => $esqIdent[0]->id,
                  "impuesto_vinculado" => $catIdent[0]->id,
                )
              );

            if ($queryVincular) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Esquema de impuestos con folio " . $folio_esquema . " ha sido actualizado satisfactoriamente"
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "No se actualizo descripcion de esquema de impuestos, por favor verifique su información o comuniquese a soporte"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "No se encontro esquema de impuestos, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $mensaje_error = "";
        if (!$OKEsqToken) $mensaje_error = "Error en descripcion de esquema de impuestos seleccionado, por favor verifique su información o comuniquese a soporte";
        if (!$OKCatImp) $mensaje_error = "Error en impuesto seleccionado, por favor verifique su información o comuniquese a soporte";
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaActualizarDesvincular(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'esquema_token' => 'required|string',
      'token_catalogo_impuesto' => 'required|string'
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
      $esquema_token = $request->input('esquema_token');
      $token_catalogo_impuesto = $request->input('token_catalogo_impuesto');
      
      if (isset($esquema_token) && !empty($esquema_token) && isset($token_catalogo_impuesto) && !empty($token_catalogo_impuesto)) {
        $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
          ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
          ->where(['esqImp.esquema_token' => $esquema_token, 'esqImp.status_esquema' => TRUE, 'emp.empresa_token' => $empresa])
          ->get();

        if (count($queryEsquema) > 0 && count($queryEsquema) == 1) {
          foreach ($queryEsquema as $value) {
            $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($value->esquema_folio);
            $esqIdent = DB::select("SELECT id FROM cont_impuestos_esquema WHERE esquema_token = ?", [$value->esquema_token]);
            $catIdent = DB::select("SELECT id FROM cont_impuestos_catalogo WHERE token_catalogo_impuesto = ?", [$token_catalogo_impuesto]);

            $queryDesvincular = DB::table('cont_impuestos_esquema_vinculo')
              ->where(["esquema_vinculado" => $esqIdent[0]->id, "impuesto_vinculado" => $catIdent[0]->id])
              ->limit(1)->delete();

            if ($queryDesvincular) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Esquema de impuestos con folio " . $folio_esquema . " ha sido actualizado satisfactoriamente"
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "No se actualizo descripcion de esquema de impuestos, por favor verifique su información o comuniquese a soporte"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "No se encontro esquema de impuestos, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $mensaje_error = "";
        if (!isset($esquema_token) || empty($esquema_token)) $mensaje_error = "Error en descripcion de esquema de impuestos seleccionado, por favor verifique su información o comuniquese a soporte";
        if (!isset($token_catalogo_impuesto) || empty($token_catalogo_impuesto)) $mensaje_error = "Error en impuesto seleccionado, por favor verifique su información o comuniquese a soporte";
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => $mensaje_error
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaHabilitar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'esquema_token' => 'required|string'
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
      $esquema_token = $request->input('esquema_token');
      
      if (isset($esquema_token) && !empty($esquema_token)) {
        $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
        ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
        ->where(['esqImp.esquema_token' => $esquema_token, 'esqImp.status_esquema' => TRUE, 'emp.empresa_token' => $empresa])
        ->get();

        if (count($queryEsquema) > 0 && count($queryEsquema) == 1) {
          foreach ($queryEsquema as $vEsq) {
            $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($vEsq->esquema_folio);
            $esqHabi = DB::table("cont_impuestos_esquema")->where(["esquema_token" => $vEsq->esquema_token])
              ->limit(1)->update(array("habilitado" => TRUE));
            if ($esqHabi) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Esquema de impuestos con folio " . $folio_esquema . " ha sido habilitado satisfactoriamente"
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Esquema de impuestos no habilitado, por favor verifique su información o comuniquese a soporte"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Esquema de impuestos no encontrado, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en esquema de impuestos seleccionado, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaDeshabilitar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'esquema_token' => 'required|string'
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
      $esquema_token = $request->input('esquema_token');
      
      if (isset($esquema_token) && !empty($esquema_token)) {
        $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
          ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
          ->where(['esqImp.esquema_token' => $esquema_token, 'esqImp.status_esquema' => TRUE, 'emp.empresa_token' => $empresa])
          ->get();

        if (count($queryEsquema) > 0 && count($queryEsquema) == 1) {
          foreach ($queryEsquema as $vEsq) {
            $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($vEsq->esquema_folio);
            $esqHabi = DB::table("cont_impuestos_esquema")->where(["esquema_token" => $vEsq->esquema_token])
              ->limit(1)->update(array("habilitado" => FALSE));
            if ($esqHabi) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Esquema de impuestos con folio " . $folio_esquema . " ha sido deshabilitado satisfactoriamente"
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Esquema de impuestos no deshabilitado, por favor verifique su información o comuniquese a soporte"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Esquema de impuestos no encontrado, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en esquema de impuestos seleccionado, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaPapeleraSave(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'esquema_token' => 'required|string'
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
      $esquema_token = $request->input('esquema_token');
      
      if (isset($esquema_token) && !empty($esquema_token)) {
        $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
          ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
          ->where(['esqImp.esquema_token' => $esquema_token, 'esqImp.status_esquema' => TRUE, 'emp.empresa_token' => $empresa])
          ->get();

        if (count($queryEsquema) > 0 && count($queryEsquema) == 1) {
          foreach ($queryEsquema as $vEsq) {
            $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($vEsq->esquema_folio);
            $esqHabi = DB::table("cont_impuestos_esquema")->where(["esquema_token" => $vEsq->esquema_token])
              ->limit(1)->update(array("status_esquema" => FALSE, "fecha_delete_esquema" => time()));
            if ($esqHabi) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Esquema de impuestos con folio " . $folio_esquema . " ha sido deshabilitado satisfactoriamente"
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Esquema de impuestos no deshabilitado, por favor verifique su información o comuniquese a soporte"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Esquema de impuestos no encontrado, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en esquema de impuestos seleccionado, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaEliminados(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
    ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
    ->where([
      'esqImp.status_esquema' => FALSE, 
      'emp.empresa_token' => $empresa
    ])
    ->get();
    
    if ($queryEsquema->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron esquemas de impuestos registrados'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $listaEsquemas = array();
      
      foreach ($queryEsquema as $value) {
        //da_te_default_timezone_set($value->zona_horaria);
        $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($value->esquema_folio);
        $arrayforeach = array(
          "esquema_token" => $value->esquema_token,
          "esquema_folio" => $folio_esquema,
          "esquema_date_insert" => $JwtAuth->mostrarUnixAFechaMexico($value->esquema_date_insert),
          "esquema_concepto" => $JwtAuth->desencriptar($value->esquema_concepto),
          "habilitado" => $value->habilitado == TRUE ? true : false,
          "esquema_date_delete" => $JwtAuth->mostrarUnixAFechaMexico($value->fecha_delete_esquema),
        );
        $listaEsquemas[] = $arrayforeach;
      }

      $dataMensaje = array(
        "status" => "success",
        "code" => 200,
        "esquemas" => $listaEsquemas
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaPapeleraRestaurar(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'esquema_token' => 'required|string'
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
      $esquema_token = $request->input('esquema_token');
      
      if (isset($esquema_token) && !empty($esquema_token)) {
        $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
          ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
          ->where(['esqImp.esquema_token' => $esquema_token, 'esqImp.status_esquema' => FALSE, 'emp.empresa_token' => $empresa])
          ->get();

        if (count($queryEsquema) > 0 && count($queryEsquema) == 1) {
          foreach ($queryEsquema as $vEsq) {
            $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($vEsq->esquema_folio);
            $esqHabi = DB::table("cont_impuestos_esquema")->where(["esquema_token" => $vEsq->esquema_token])
              ->limit(1)->update(array("status_esquema" => TRUE, "fecha_delete_esquema" => NULL));
            if ($esqHabi) {
              $dataMensaje = array(
                "status" => "success",
                "code" => 200,
                "message" => "Esquema de impuestos con folio " . $folio_esquema . " ha sido restaurado satisfactoriamente"
              );
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Esquema de impuestos no restaurado, por favor verifique su información o comuniquese a soporte"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Esquema de impuestos no encontrado, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en esquema de impuestos seleccionado, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function impuestoEsquemaDeletePerm(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'esquema_token' => 'required|string'
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
      $esquema_token = $request->input('esquema_token');
      
      if (isset($esquema_token) && !empty($esquema_token)) {
        $queryEsquema = DB::table("cont_impuestos_esquema AS esqImp")
          ->join('main_empresas AS emp', 'esqImp.empresa', 'emp.id')
          ->where(['esqImp.esquema_token' => $esquema_token, 'esqImp.status_esquema' => FALSE, 'emp.empresa_token' => $empresa])
          ->get();

        if (count($queryEsquema) > 0 && count($queryEsquema) == 1) {
          foreach ($queryEsquema as $vEsq) {
            $folio_esquema = 'IMPESQ-' . $JwtAuth->generarFolio($vEsq->esquema_folio);

            $queryImpVinc = DB::table("cont_impuestos_esquema AS esqImp")
              ->join('cont_impuestos_esquema_vinculo AS vinc', 'esqImp.id', 'vinc.esquema_vinculado')
              ->join('cont_impuestos_catalogo AS catImp', 'vinc.impuesto_vinculado', 'catImp.id')
              ->where(['esqImp.esquema_token' => $vEsq->esquema_token])
              ->get();

            if (count($queryImpVinc) == 0) {
              $esqHabi = DB::table("cont_impuestos_esquema")->where(["esquema_token" => $vEsq->esquema_token])
                ->limit(1)->delete();
              if ($esqHabi) {
                $dataMensaje = array(
                  "status" => "success",
                  "code" => 200,
                  "message" => "Esquema de impuestos con folio " . $folio_esquema . " ha sido eliminado satisfactoriamente"
                );
              } else {
                $dataMensaje = array(
                  "status" => "error",
                  "code" => 200,
                  "message" => "Esquema de impuestos no eliminado, por favor verifique su información o comuniquese a soporte"
                );
              }
            } else {
              $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "Esquema de impuestos no eliminado, esta relacionado con " . count($queryImpVinc) . " impuestos, por favor verifique su información o comuniquese a soporte"
              );
            }
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Esquema de impuestos no encontrado, por favor verifique su información o comuniquese a soporte"
          );
        }
      } else {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Error en esquema de impuestos seleccionado, por favor verifique su información o comuniquese a soporte"
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}