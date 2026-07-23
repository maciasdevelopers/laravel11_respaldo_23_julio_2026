<?php

namespace App\Services;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\DB;
use App\Models\EmpresasModelo;
use Illuminate\Support\Facades\Storage;
use Firebase\JWT\JWT;

class UserEmpresaService{
  public function getEmpresa($empresa_token,$usuario,$JwtAuth){
    $empData = EmpresasModelo::join("sos_personas AS people", "emp.persona", "=", "people.id")
    ->join("teci_pais AS ispa", "people.nacionalidad", "=", "ispa.id")
    ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
    ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
    ->where("emp.status_empresa",TRUE)
    ->where("emp.empresa_token",$empresa_token)
    ->where("users.usuario_token",$usuario)
    ->first();

    if (!$empData) {
      return response()->json(['status' => 'error','message' => 'La empresa no está vinculada al usuario'], 403);
    }

    $nombreEmpresa = $empData->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($empData->paterno, $empData->materno, $empData->nombre) : $JwtAuth->desencriptar($empData->denominacion_rs);
    $name_abrev = $empData->abrev_nombre;
    //$tipo_sociedad = $JwtAuth->desencriptar($empData->tipo_sociedad_escrito);
    $tipo_sociedad = $empData->tipo_sociedad_escrito ? $JwtAuth->desencriptar($empData->tipo_sociedad_escrito) : "";
    $rfc_generico = $empData->rfc_generico;
    $rfc_emp = $empData->rfc != NULL ? $JwtAuth->desencriptar($empData->rfc) : "---";
    $tax_id_emp = $empData->tax_id != NULL ? $JwtAuth->desencriptar($empData->tax_id) : "---";
    //echo $JwtAuth->desencriptar($empData->img_perfil);
    if ($JwtAuth->desencriptar($empData->img_perfil) == "empresa_desconocida.png") {
      $logoTipo = $JwtAuth->encriptaBase64(Storage::path('public/settings/empresa_desconocida.png'));
    } else {
      $logoTipo = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $empData->root_tkn . '/0007-core/' . $JwtAuth->desencriptar($empData->img_perfil)));
    }

    //configuración de accesos y permisos
    $permisos_ingresos = [];
    $queryConfigIngr = DB::table("configuracion_systema_ingr AS conf_ingr")
    ->join("main_empresas AS emp","conf_ingr.empresa","=","emp.id")
    ->join("teci_usuarios_catalogo AS users","conf_ingr.usuario","=","users.id")
    ->where(["emp.empresa_token" => $empData->empresa_token, "users.usuario_token" => $usuario])->get();
    foreach ($queryConfigIngr as $cINGR) {
      $permisos_ingresos[] = [
        "jerarquia" => $cINGR->jerarquia,
        "bool_ingr_perm_crear" => (bool)$cINGR->privilegio_crear,	
        "bool_ingr_perm_editar" => (bool)$cINGR->privilegio_editar,	
        "bool_ingr_perm_consulta" => (bool)$cINGR->privilegio_consulta,	
        "bool_ingr_perm_elimina" => (bool)$cINGR->privilegio_elimina,	
        "bool_ingr_perm_ver_docs" => (bool)$cINGR->privilegio_ver_docs,
      ];
    }

    $permisos_egresos = [];
    $queryConfigEegr = DB::table("configuracion_systema_eegr AS eegr_conf")
    ->join("main_empresas AS emp", "eegr_conf.empresa", "emp.id")
    ->join("teci_usuarios_catalogo AS users", "eegr_conf.usuario", "users.id")
    ->where(["emp.empresa_token" => $empData->empresa_token, "users.usuario_token" => $usuario])->get();

    foreach ($queryConfigEegr as $vCegr) {
      $permisos_egresos[] = [
        "jerarquia" => $vCegr->jerarquia,
        "bool_eegr_catalogos" => (bool)$vCegr->catalogos,
        "bool_eegr_cat_prod" => (bool)$vCegr->cat_prod,
        "bool_eegr_cat_serv" => (bool)$vCegr->cat_serv,
        "bool_eegr_cat_actf" => (bool)$vCegr->cat_actf,
        "bool_eegr_cat_acti" => (bool)$vCegr->cat_acti,
        "bool_eegr_cat_prov" => (bool)$vCegr->cat_prov,
        "bool_eegr_cat_esta" => (bool)$vCegr->cat_esta,
        "bool_eegr_compras" => (bool)$vCegr->compras,
        "bool_eegr_comp_req" => (bool)$vCegr->comp_req,
        "bool_eegr_comp_cot" => (bool)$vCegr->comp_cot,
        "bool_eegr_comp_dir" => (bool)$vCegr->comp_dir,
        "bool_eegr_comp_seg" => (bool)$vCegr->comp_seg,
        "bool_eegr_perm_crear" => (bool)$vCegr->privilegio_crear,
        "bool_eegr_perm_editar" => (bool)$vCegr->privilegio_editar,
        "bool_eegr_perm_consulta" => (bool)$vCegr->privilegio_consulta,
        "bool_eegr_perm_elimina" => (bool)$vCegr->privilegio_elimina,
        "bool_eegr_perm_ver_docs" => (bool)$vCegr->privilegio_ver_docs,
      ];
    }
    
    $permisos_finanzas = [];
    $queryConfigFnzs = DB::table("configuracion_systema_fnzs AS conf_fnzs")
    ->join("main_empresas AS emp","conf_fnzs.empresa","=","emp.id")
    ->join("teci_usuarios_catalogo AS users","conf_fnzs.usuario","=","users.id")
    ->where(["emp.empresa_token" => $empData->empresa_token, "users.usuario_token" => $usuario])->get();
    foreach ($queryConfigFnzs as $cFNZS) {
      $permisos_finanzas[] = [
        "jerarquia" => $cFNZS->jerarquia,
        "bool_fnzs_perm_crear" => (bool)$cFNZS->privilegio_crear,	
        "bool_fnzs_perm_editar" => (bool)$cFNZS->privilegio_editar,	
        "bool_fnzs_perm_consulta" => (bool)$cFNZS->privilegio_consulta,	
        "bool_fnzs_perm_elimina" => (bool)$cFNZS->privilegio_elimina,	
        "bool_fnzs_perm_ver_docs" => (bool)$cFNZS->privilegio_ver_docs,
      ];
    }

    $permisos_valor_humano = [];
    $queryConfigVhum = DB::table("configuracion_systema_vhum AS conf_vhum")
    ->join("main_empresas AS emp","conf_vhum.empresa","=","emp.id")
    ->join("teci_usuarios_catalogo AS users","conf_vhum.usuario","=","users.id")
    ->where(["emp.empresa_token" => $empData->empresa_token, "users.usuario_token" => $usuario])->get();
    foreach ($queryConfigVhum as $cVHUM) {
      $permisos_valor_humano[] = [
        "jerarquia" => $cVHUM->jerarquia,
        "bool_vhum_perm_crear" => (bool)$cVHUM->privilegio_crear,	
        "bool_vhum_perm_editar" => (bool)$cVHUM->privilegio_editar,	
        "bool_vhum_perm_consulta" => (bool)$cVHUM->privilegio_consulta,	
        "bool_vhum_perm_elimina" => (bool)$cVHUM->privilegio_elimina,	
        "bool_vhum_perm_ver_docs" => (bool)$cVHUM->privilegio_ver_docs,
      ];
    }

    $permisos_contabilidad = [];
    $queryConfigCont = DB::table("configuracion_systema_cont AS conf_cont")
    ->join("main_empresas AS emp","conf_cont.empresa","=","emp.id")
    ->join("teci_usuarios_catalogo AS users","conf_cont.usuario","=","users.id")
    ->where(["emp.empresa_token" => $empData->empresa_token, "users.usuario_token" => $usuario])->get();
    foreach ($queryConfigCont as $cCONT) {
      $permisos_contabilidad[] = [
        "jerarquia" => $cCONT->jerarquia,
        "bool_cont_perm_crear" => (bool)$cCONT->privilegio_crear,	
        "bool_cont_perm_editar" => (bool)$cCONT->privilegio_editar,	
        "bool_cont_perm_consulta" => (bool)$cCONT->privilegio_consulta,	
        "bool_cont_perm_elimina" => (bool)$cCONT->privilegio_elimina,	
        "bool_cont_perm_ver_docs" => (bool)$cCONT->privilegio_ver_docs,
      ];
    }

    $permisos_tec_info = [];
    $queryConfigTeci = DB::table("configuracion_systema_teci AS conf_teci")
    ->join("main_empresas AS emp","conf_teci.empresa","=","emp.id")
    ->join("teci_usuarios_catalogo AS users","conf_teci.usuario","=","users.id")
    ->where(["emp.empresa_token" => $empData->empresa_token, "users.usuario_token" => $usuario])->get();
    foreach ($queryConfigTeci as $cTECI) {
      $permisos_tec_info[] = [
        "jerarquia" => $cTECI->jerarquia,
        "bool_teci_perm_crear" => (bool)$cTECI->privilegio_crear,	
        "bool_teci_perm_editar" => (bool)$cTECI->privilegio_editar,	
        "bool_teci_perm_consulta" => (bool)$cTECI->privilegio_consulta,	
        "bool_teci_perm_elimina" => (bool)$cTECI->privilegio_elimina,	
        "bool_teci_perm_ver_docs" => (bool)$cTECI->privilegio_ver_docs,
      ];
    }

    $queryRegimenFiscal = DB::table("sos_regimen_fiscal")
    ->where("id",$empData->emp_regimen_fiscal)
    ->select('token_regimen_fiscal','clave','descripcion')
    ->first();

    $regFiscalEmpToken = !is_null($empData->emp_regimen_fiscal) && $queryRegimenFiscal ? $queryRegimenFiscal->token_regimen_fiscal : '';
    $regFiscalEmpDescripcion = !is_null($empData->emp_regimen_fiscal) && $queryRegimenFiscal ? $queryRegimenFiscal->clave."-".$queryRegimenFiscal->descripcion : '';
    
    $acreedorQuery = DB::table("fnzs_catalogo_acreedores AS acree")
    ->whereIn('acree.acr_empleado_vinculado', function ($sub) use($empData,$usuario) {
      $sub->select('trab.id')->from('vhum_empleados_catalogo AS trab')
      ->join("main_empresas AS emp", "trab.empleado_empresa", "emp.id")
      ->join("teci_usuarios_catalogo AS users", "trab.id", "users.empleado")
      ->where('acree.acr_habilita_reembolsos',TRUE)
      ->where('acree.acr_status',TRUE)
      ->where(["emp.empresa_token" => $empData->empresa_token, "users.usuario_token" => $usuario]);
    })
    ->orWhereIn('acree.acr_proveedor_vinculado', function ($sub) use($empData,$usuario) {
      $sub->select('catprov.id')->from('eegr_catalogo_proveedores AS catprov')
      ->join("main_empresas AS emp", "catprov.administrador", "emp.id")
      ->join("main_proveedor_usuario AS relpu", "catprov.id", "=", "relpu.proveedor")
      ->join("teci_usuarios_catalogo AS users", "relpu.usuario", "=", "users.id")
      ->where('acree.acr_habilita_reembolsos',TRUE)
      ->where('acree.acr_status',TRUE)
      ->where('catprov.habilitado_para_reembolsos',TRUE)
      ->where(["emp.empresa_token" => $empData->empresa_token, "users.usuario_token" => $usuario]);
    })
    ->select(
      'acree.token_cat_acreedores',
      'acree.acr_habilita_reembolsos',
      'acree.acr_titular'
    )
    ->get();

    $acreedores = $acreedorQuery->map(function ($item) use ($JwtAuth) {
      $item->acr_habilita_reembolsos = $item->acr_habilita_reembolsos ? true : false;
      $item->acr_titular = $item->acr_titular ? $JwtAuth->desencriptar($item->acr_titular) : '';
      return $item;
    });

    $token_payload = [
      "ctx"           => 'moriah',
      "user_token"    => $usuario,
      "empresa_id"    => DB::table("main_empresas")->where("empresa_token", $empData->empresa_token)->value("id"),
      "empresa_token" => $empData->empresa_token,
      "iat"           => time(),
      "exp" => time() + (30 * 60) // 30 minutos
    ];

    $key = config('services.jwt.secret');
    $jwt = JWT::encode($token_payload, $key, 'HS256');

    return [
      "empresa_token" => $empData->empresa_token,
      "company_name" => $nombreEmpresa,
      "name_abrev" => $name_abrev,
      "company_name_short" => $name_abrev,
      "company_name_large" => "$name_abrev - $nombreEmpresa",
      "tipo_sociedad" => $tipo_sociedad,
      "emp_regimen_fiscal_token" => $regFiscalEmpToken,
      "emp_regimen_fiscal_descripcion" => $regFiscalEmpDescripcion,
      "zona_horaria" => $empData->zona_horaria,
      "zona_horaria_utc" => $empData->zona_horaria_utc,
      "e_moneda_code" => $empData->e_moneda_code,
      "e_moneda_decimales" => $empData->e_moneda_decimales,
      "codigo_pais" => $empData->codigo_pais,
      "rfc_generico" => $rfc_generico,
      "rfc_emp" => $rfc_emp,
      "tax_id_emp" => $tax_id_emp,
      "logotypo" => $logoTipo,
      "conf_ingresos" => $permisos_ingresos,
      "conf_egresos" => $permisos_egresos,
      "conf_finanzas" => $permisos_finanzas,
      "conf_valor_humano" => $permisos_valor_humano,
      "conf_contabilidad" => $permisos_contabilidad,
      "conf_tec_info" => $permisos_tec_info,
      "habilita_reembolsos" => count($acreedores) > 0 ? true : false,
      "acreedor" => $acreedores,
      "large_token_access" => $jwt,
      "active_class" => "",
      "areasettings" => "",
      "es_administradora" => (bool)$empData->es_administradora,
      //"empleado_token" => $trabajador_token,
      "nivel_empleado" => $empData->trabajador_nivel,
      //"token_cat_proveedores" => $token_cat_proveedores,
    ];
  }
}