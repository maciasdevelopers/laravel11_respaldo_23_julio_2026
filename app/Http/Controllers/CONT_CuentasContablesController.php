<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CuentasContablesModelo;

class CONT_CuentasContablesController extends Controller{
  public function cuentasContablesNivelUno(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $queryNivelUno = DB::table("cont_catalogo_cuentas_contables_nivel_uno")
    ->where("nivel_activo",TRUE)
    ->get();

    if ($queryNivelUno->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron cuentas contables registradas'
      );
    } else {
      foreach ($queryNivelUno as $vcCont) { 
        $row = array(
          "uuid_nivel_uno" => $vcCont->uuid_nivel_uno,
          "codigo" => $vcCont->codigo,
          "abreb" => $vcCont->abreb,
          "nombre" => $vcCont->nombre,
        );
        $nivel_uno_list[] = $row; 
      }

      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'nivel_uno' => $nivel_uno_list
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cuentasContablesNivelDos(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }
    
    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'uuid_nivel_uno' => 'required|string'
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'No se encontraron cuentas contables',
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $uuid_nivel_uno = $request->input('uuid_nivel_uno');
  
      $queryNivelDos = DB::table("cont_catalogo_cuentas_contables_nivel_dos AS dos")
      ->join("cont_catalogo_cuentas_contables_nivel_uno AS uno","dos.nivel_uno","=","uno.id")
      ->where("uno.uuid_nivel_uno",$uuid_nivel_uno)
      ->get();

      if ($queryNivelDos->isEmpty()) {
        $dataMensaje = array(
          'code' => 200,
          'status' => 'error',
          'message' => 'No se encontraron cuentas contables registradas'
        );
      } else {
        $nivel_dos_list = array();

        foreach ($queryNivelDos as $vcCont) {
          $row = array(
            "uuid_nivel_dos" => $vcCont->uuid_nivel_dos,
            "nivel_dos_codigo" => $vcCont->nivel_dos_codigo,
            "nivel_dos_abreb" => $vcCont->nivel_dos_abreb,
            "nivel_dos_nombre" => $vcCont->nivel_dos_nombre,
          );
          $nivel_dos_list[] = $row; 
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'nivel_dos' => $nivel_dos_list
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cuentasContablesCatalogo(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }
        
    $queryCuentasC = CuentasContablesModelo::join("main_empresas AS emp","cont_catalogo_cuentas_contables.empresa","=","emp.id")
    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
    ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
    ->where([
      'emp.empresa_token' => $empresa,
      'users.usuario_token' => $usuario,
    ])->get();

    if ($queryCuentasC->isEmpty()) {
      $dataMensaje = array(
        'code' => 200,
        'status' => 'error',
        'message' => 'No se encontraron cuentas contables registradas'
      );
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $list_cuentas_contables = array();      
      foreach ($queryCuentasC as $vcCont) {
        $folio_ccontable = 'CCONT-'.$JwtAuth->generarFolio($vcCont->folio_c_contable).(!is_null($vcCont->inside_folio) ? '-'.$vcCont->inside_folio:'');
        $row = array(
          "token_catalogo_cuentas" => $vcCont->token_catalogo_cuentas,
          "folio_c_contable" => $folio_ccontable,
          "fecha_registro" => date('d-m-Y H:i:s',$vcCont->fecha_registro),
          "fecha_contabilizacion" => date('d-m-Y H:i:s',$vcCont->fecha_contabilizacion),
          "numero" => $vcCont->numero,
          "tipo" => $vcCont->tipo_c_contable,
          "naturaleza" => $vcCont->naturaleza,
          "clasificacion" => $vcCont->clasificacion,
          "observaciones" => $JwtAuth->desencriptar($vcCont->observaciones)
        );
        $list_cuentas_contables[] = $row; 
      }
      $dataMensaje = array(
        'status' => 'success',
        'code' => 200,
        'cuentas_contables' => $list_cuentas_contables
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function cuentaContableRegistro(Request $request){
    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'nombre' => 'required|string',
      'uuid_nivel_uno' => 'required|string',
      'uuid_nivel_dos' => 'required|string',
      'numero' => 'required|numeric',
      'tipo' => 'required|string',
      'naturaleza' => 'required|string',
      'catalogo_aplicado_tipo' => 'string',
      'catalogo_aplicado_token' => 'string',
      'observaciones' => 'required|string'
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
      $nombre = $request->input('nombre');
      $uuid_nivel_uno = $request->input('uuid_nivel_uno');
      $uuid_nivel_dos = $request->input('uuid_nivel_dos');
      $numero = $request->input('numero');
      $tipo = $request->input('tipo');
      $naturaleza = $request->input('naturaleza');
      $catalogo_aplicado_tipo = $request->input('catalogo_aplicado_tipo');
      $catalogo_aplicado_token = $request->input('catalogo_aplicado_token');
      $observaciones = $request->input('observaciones');
      
      $validate_nombre = isset($nombre) && !empty($nombre) && preg_match($JwtAuth->filtroAlfaNumerico(),$nombre);
      $validate_uuid_nivel_uno = isset($uuid_nivel_uno) && !empty($uuid_nivel_uno);
      $validate_uuid_nivel_dos = isset($uuid_nivel_dos) && !empty($uuid_nivel_dos);
      $validate_numero = isset($numero) && !empty($numero) && preg_match($JwtAuth->filtroAlfaNumerico(),$numero);
      $validate_tipo = isset($tipo) && !empty($tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$tipo);
      $validate_naturaleza = isset($naturaleza) && !empty($naturaleza) && preg_match($JwtAuth->filtroAlfaNumerico(),$naturaleza);
      $validate_catalogo_aplicado_tipo = isset($catalogo_aplicado_tipo) && !empty($catalogo_aplicado_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$catalogo_aplicado_tipo);
      $validate_catalogo_aplicado_token = isset($catalogo_aplicado_token) && !empty($catalogo_aplicado_token);
      $validate_observaciones = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

      if ($validate_nombre && $validate_uuid_nivel_uno && $validate_uuid_nivel_dos && $validate_numero && $validate_tipo && $validate_naturaleza && $validate_observaciones) {
        $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
        WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$empresa, $usuario]);
        foreach ($queryEmp as $vEmp) {
          //ALTER TABLE `sos_last_folders` ADD `cont_cuentas_contables` BOOLEAN NULL AFTER `fnzs_ordenes_pago`;
          $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
          JOIN teci_usuarios_catalogo AS users WHERE fold.cont_cuentas_contables = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
          AND users.usuario_token = ?",[$empresa, $usuario]);

          $inside_folio_db = DB::select("SELECT inside_folio FROM cont_catalogo_cuentas_contables WHERE id = (SELECT Max(ccont.id) FROM cont_catalogo_cuentas_contables AS ccont JOIN main_empresas AS emp 
            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
            WHERE ccont.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$empresa, $usuario]);

          $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
          $inside_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($inside_folio_db[0]->inside_folio);
          $folio_ccontable = 'CCONT-'.$JwtAuth->generarFolio($folio_nuevo).($inside_folio != NULL ? '-'.$inside_folio:'');

          /*CREATE TABLE cont_catalogo_cuentas_contables (
            id int(10) primary key NOT NULL auto_increment,
            cuenta_contable_token text,
            cuenta_contable_folio int(5),
            cuenta_contable_inside_folio text,
            cuenta_contable_fecha_registro varchar(10),
            cuenta_contable_fecha_contabilizacion varchar(10),
            cuenta_contable_nivel_uno int(10),
            cuenta_contable_nivel_dos int(10),
            cuenta_contable_numero char(4),
            cuenta_contable_tipo char(11),
            cuenta_contable_naturaleza char(9),
            cuenta_contable_observaciones text,
          
            ingresos_catalogodeclientes int(10) DEFAULT NULL,
            foreign key (ingresos_catalogodeclientes) references ingr_catalogo_clientes (id),
            ingresos_catalogodedescuentos int(10) DEFAULT NULL,
            foreign key (ingresos_catalogodedescuentos) references ingr_catalogo_descuentos (id),
            ingresos_catalogodepromociones int(10) DEFAULT NULL,
            foreign key (ingresos_catalogodepromociones) references ingr_catalogo_promociones (id),
          
            egresos_proveedores int(10) DEFAULT NULL,
            foreign key (egresos_proveedores) references eegr_catalogo_proveedores (id),
          
            inventarios_productos int(10) DEFAULT NULL,
            foreign key (inventarios_productos) references in_egr_catalogo_productos (id),
            inventarios_servicios int(10) DEFAULT NULL,
            foreign key (inventarios_servicios) references in_egr_catalogo_servicios (id),
            inventarios_activos_fijos int(10) DEFAULT NULL,
            foreign key (inventarios_activos_fijos) references eegr_activos_fijos_catalogo (id),
            inventarios_activos_intangibles int(10) DEFAULT NULL,
            foreign key (inventarios_activos_intangibles) references eegr_activos_intangibles_catalogo (id),
            inventarios_establecimientos int(10) DEFAULT NULL,
            foreign key (inventarios_establecimientos) references in_egr_establecimientos_catalogo (id),
          
            finanzas_acreedores int(10) DEFAULT NULL,
            foreign key (finanzas_acreedores) references fnzs_catalogo_acreedores (id),
            finanzas_deudores int(10) DEFAULT NULL,
            foreign key (finanzas_deudores) references fnzs_catalogo_deudores (id),
            finanzas_punto_de_venta int(10) DEFAULT NULL,
            foreign key (finanzas_punto_de_venta) references sos_puntodeventa_catalogos (id),
            finanzas_cajas int(10) DEFAULT NULL,
            foreign key (finanzas_cajas) references fnzs_catalogos_caja (id),
            finanzas_cuentas_bancarias int(10) DEFAULT NULL,
            foreign key (finanzas_cuentas_bancarias) references fnzs_catalogos_cuentas (id),
            finanzas_dispositivos int(10) DEFAULT NULL,
            finanzas_monederos_electronicos int(10) DEFAULT NULL,
            foreign key (finanzas_monederos_electronicos) references fnzs_catalogos_cuentas_monedero (id),
            finanzas_plataformas_electronicas int(10) DEFAULT NULL,
            finanzas_indicadores_economicos int(10) DEFAULT NULL,
            foreign key (finanzas_indicadores_economicos) references 	fnzs_indicadores (id),
            finanzas_monedas_y_divisas int(10) DEFAULT NULL,
          
            cuenta_contable_empresa int(10) DEFAULT NULL,
            foreign key (cuenta_contable_empresa) references main_empresas (id),
            created_at text DEFAULT NULL,
            updated_at text DEFAULT NULL
          );*/

          $c_contable_cliente = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (ingresos_catalogodeclientes) references ingr_catalogo_clientes (id),
          $c_contable_descuentos = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (ingresos_catalogodedescuentos) references ingr_catalogo_descuentos (id),
          $c_contable_promociones = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (ingresos_catalogodepromociones) references ingr_catalogo_promociones (id),
          $c_contable_proveedores = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (egresos_proveedores) references eegr_catalogo_proveedores (id),
          $c_contable_productos = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (inventarios_productos) references in_egr_catalogo_productos (id),
          $c_contable_servicios = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (inventarios_servicios) references in_egr_catalogo_servicios (id),
          $c_contable_activos_fijos = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (inventarios_activos_fijos) references eegr_activos_fijos_catalogo (id),
          $c_contable_activos_intangibles = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (inventarios_activos_intangibles) references eegr_activos_intangibles_catalogo (id),
          $c_contable_establecimientos = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (inventarios_establecimientos) references in_egr_establecimientos_catalogo (id),
          $c_contable_acreedores = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (finanzas_acreedores) references fnzs_catalogo_acreedores (id),
          $c_contable_deudores = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (finanzas_deudores) references fnzs_catalogo_deudores (id),
          $c_contable_punto_de_venta = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (finanzas_punto_de_venta) references sos_puntodeventa_catalogos (id),
          $c_contable_cajas = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (finanzas_cajas) references fnzs_catalogos_caja (id),
          $c_contable_cuentas_bancarias = $catalogo_aplicado_tipo == "" ? DB::table()->where("",)->value() : NULL; //foreign key (finanzas_cuentas_bancarias) references fnzs_catalogos_cuentas (id),
          $c_contable_dispositivos = NULL;
          $c_contable_monederos_electronicos = NULL; //foreign key (finanzas_monederos_electronicos) references fnzs_catalogos_cuentas_monedero (id),
          $c_contable_plataformas_electronicas = NULL;
          $c_contable_indicadores_economicos = NULL; //foreign key (finanzas_indicadores_economicos) references 	fnzs_indicadores (id),
          $c_contable_monedas_y_divisas = NULL;

          $fechaSistema = time();
          $token_cuenta_contable = $JwtAuth->encriptarToken(time(),$numero,$tipo,$naturaleza,$validate_uuid_nivel_uno,$validate_uuid_nivel_dos,$observaciones);
          $ccontn = new CuentasContablesModelo();
          $ccontn->token_catalogo_cuentas = $token_cuenta_contable;
          $ccontn->folio_c_contable = $folio_nuevo;
          $ccontn->inside_folio = $inside_folio;
          $ccontn->fecha_registro = $fechaSistema;
          $ccontn->fecha_contabilizacion = $fechaSistema;
          //$ccontn->fecha_contabilizacion = $JwtAuth->convierteFechaEpoc($fecha_contabilizacion);
          $ccontn->numero = $numero;
          $ccontn->tipo_c_contable = $tipo;
          $ccontn->naturaleza = $naturaleza;
          //$ccontn->clasificacion = $clasificacion;
          $ccontn->observaciones = $JwtAuth->encriptar($observaciones);
          $ccontn->empresa = $vEmp->id;
          $insertCContable = $ccontn->save();

          if ($insertCContable) {
            if (count($folioSistema) == 0) {
              $insertSistema = DB::table('sos_last_folders')
                ->insert(
                  array(
                    "cont_cuentas_contables" => TRUE,
                    "folder" => 1,
                    "post_folder" => $inside_folio,
                    "empresa" => $vEmp->id,
                  )
                );
            } else {
              $regFolder = DB::table('sos_last_folders')->join("main_empresas AS emp", "sos_last_folders.empresa", "=", "emp.id")
                ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                ->where([
                  'sos_last_folders.cont_cuentas_contables' => TRUE,
                  'emp.empresa_token' => $empresa,
                  'users.usuario_token' => $usuario,
                ])
                ->limit(1)->update(
                  array(
                    'sos_last_folders.folder' => $folio_nuevo,
                    'sos_last_folders.post_folder' => $inside_folio,
                  )
                );
            }

            $dataMensaje = array('status' => 'success','code' => 200,'message' => "Cuenta contable registrada con el folio $folio_ccontable");
          } else {
            $dataMensaje = array('status' => 'error','code' => 200,'message' => "Cuenta contable no registrada, intentelo nuevamente o comuniquese a soporte");
          }
          

        }
      } else {
        $mensaje_error = '';
        if (!$validate_numero) {$mensaje_error = 'Error al registrar nùmero de cuenta contable, intentelo nuevamente o comuniquese a soporte';}
        if (!$validate_tipo) {$mensaje_error = 'Error al registrar tipo de cuenta contable, intentelo nuevamente o comuniquese a soporte';}
        if (!$validate_naturaleza) {$mensaje_error = 'Error al registrar naturaleza de cuenta contable, intentelo nuevamente o comuniquese a soporte';}
        if (!$validate_catalogo_aplicado_tipo) {$mensaje_error = 'Error al registrar clasificación de cuenta contable, intentelo nuevamente o comuniquese a soporte';}
        if (!$validate_catalogo_aplicado_token) {$mensaje_error = 'Error al registrar clasificación de cuenta contable, intentelo nuevamente o comuniquese a soporte';}
        if (!$validate_observaciones) {$mensaje_error = 'Error al registrar observaciones de cuenta contable, intentelo nuevamente o comuniquese a soporte';}
        $dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}