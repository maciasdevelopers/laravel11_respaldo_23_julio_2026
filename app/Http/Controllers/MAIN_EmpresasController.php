<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\EmpresasModelo;
use QRCode;

class MAIN_EmpresasController extends Controller{
  public function listaEmpresasAll(){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $arrayCompanies = array();
    $empList = EmpresasModelo::join("sos_personas AS people", "emp.persona", "=", "people.id")
      ->join("teci_pais AS ispa", "people.nacionalidad", "=", "ispa.id")
      ->where("emp.status_empresa", "=", TRUE)->get();

    foreach ($empList as $value) {
      $nombreEmpresa = $value->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($value->paterno, $value->materno, $value->nombre) : $JwtAuth->desencriptar($value->denominacion_rs);
      $rfc_generico = $value->rfc_generico;
      $rfc_emp = $value->rfc != NULL ? $JwtAuth->desencriptar($value->rfc) : '---';
      $tax_id_emp = $value->tax_id != NULL ? $JwtAuth->desencriptar($value->tax_id) : '---';
      $logoTipo = "https://downloads.sos-mexico.com.mx/empresa_img/" . $value->empresa_token;

      $row = array(
        "empresa_token" => $value->empresa_token,
        "name_abrev" => $value->abrev_nombre,
        "company_name" => $nombreEmpresa,
        "zona_horaria" => $value->zona_horaria,
        "zona_horaria_utc" => $value->zona_horaria_utc,
        "codigo_pais" => $value->codigo_pais,
        "rfc_generico" => $rfc_generico,
        "rfc_company" => $rfc_emp,
        "tax_id_company" => $tax_id_emp,
        "logotypo" => $logoTipo,
      );
      $arrayCompanies[] = $row;
    }
    $dataMensaje = array(
      'companies' => $arrayCompanies,
      'code' => 200,
      'status' => 'success',
    );
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogoEmpresasVinculadas__(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $empCatalogoArray = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $empList = EmpresasModelo::join("sos_personas AS people", "emp.persona", "=", "people.id")
          ->join("teci_pais AS ispa", "people.nacionalidad", "=", "ispa.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("vhum_empleados_catalogo AS pers", "empuser.usuario", "=", "users.id")
          ->where(["emp.status_empresa" => TRUE, "users.usuario_token" => $usuario->user_token])->get();

        foreach ($empList as $value) {
          //echo $JwtAuth->encriptar("Value Point");
          $rfc_emp = '---';
          $tax_id_emp = '---';

          if ($value->denominacion_rs == '') {
            $nombreEmpresa = $JwtAuth->desencriptar($value->paterno) . " " . $JwtAuth->desencriptar($value->materno) . " " . $JwtAuth->desencriptar($value->nombre);
          } else {
            $nombreEmpresa = $JwtAuth->desencriptar($value->denominacion_rs);
          }

          $name_abrev = $value->abrev_nombre;

          //$tipo_sociedad = $JwtAuth->desencriptar($value->tipo_sociedad_escrito);
          $tipo_sociedad = $value->tipo_sociedad_escrito != NULL ? $JwtAuth->desencriptar($value->tipo_sociedad_escrito) : "";

          $rfc_generico = $value->rfc_generico;

          if ($value->rfc != NULL) $rfc_emp = $JwtAuth->desencriptar($value->rfc);

          if ($value->tax_id != NULL) $tax_id_emp = $JwtAuth->desencriptar($value->tax_id);

          //echo $JwtAuth->desencriptar($value->img_perfil);
          if ($JwtAuth->desencriptar($value->img_perfil) == "empresa_desconocida.png") {
            $logoTipo = $JwtAuth->encriptaBase64(Storage::path('public/settings/empresa_desconocida.png'));
          } else {
            $logoTipo = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $value->root_tkn . '/0007-core/' . $JwtAuth->desencriptar($value->img_perfil)));
          }

          //configuración de accesos y permisos
          $permisos_ingresos = array();
          $permisos_egresos = array();
          $permisos_finanzas = array();
          $permisos_valor_humano = array();
          $permisos_contabilidad = array();
          $permisos_tec_info = array();
          $queryConfigEegr = DB::table("configuracion_systema_eegr AS eegr_conf")
            ->join("main_empresas AS emp", "eegr_conf.empresa", "emp.id")
            ->join("teci_usuarios_catalogo AS users", "eegr_conf.usuario", "users.id")
            ->where(["emp.empresa_token" => $value->empresa_token, "users.usuario_token" => $usuario->user_token])->get();

          foreach ($queryConfigEegr as $vCegr) {
            $bool_eegr_catalogos = false;
            $bool_eegr_cat_prod = false;
            $bool_eegr_cat_serv = false;
            $bool_eegr_cat_actf = false;
            $bool_eegr_cat_acti = false;
            $bool_eegr_cat_prov = false;
            $bool_eegr_cat_esta = false;
            $bool_eegr_compras = false;
            $bool_eegr_comp_req = false;
            $bool_eegr_comp_cot = false;
            $bool_eegr_comp_dir = false;
            $bool_eegr_comp_seg = false;
            $bool_eegr_perm_crear = false;
            $bool_eegr_perm_editar = false;
            $bool_eegr_perm_consulta = false;
            $bool_eegr_perm_elimina = false;
            $bool_eegr_perm_ver_docs = false;

            if ($vCegr->catalogos == TRUE) $bool_eegr_catalogos = true;
            if ($vCegr->cat_prod == TRUE) $bool_eegr_cat_prod = true;
            if ($vCegr->cat_serv == TRUE) $bool_eegr_cat_serv = true;
            if ($vCegr->cat_actf == TRUE) $bool_eegr_cat_actf = true;
            if ($vCegr->cat_acti == TRUE) $bool_eegr_cat_acti = true;
            if ($vCegr->cat_prov == TRUE) $bool_eegr_cat_prov = true;
            if ($vCegr->cat_esta == TRUE) $bool_eegr_cat_esta = true;
            if ($vCegr->compras == TRUE) $bool_eegr_compras = true;
            if ($vCegr->comp_req == TRUE) $bool_eegr_comp_req = true;
            if ($vCegr->comp_cot == TRUE) $bool_eegr_comp_cot = true;
            if ($vCegr->comp_dir == TRUE) $bool_eegr_comp_dir = true;
            if ($vCegr->comp_seg == TRUE) $bool_eegr_comp_seg = true;
            if ($vCegr->privilegio_crear == TRUE) $bool_eegr_perm_crear = true;
            if ($vCegr->privilegio_editar == TRUE) $bool_eegr_perm_editar = true;
            if ($vCegr->privilegio_consulta == TRUE) $bool_eegr_perm_consulta = true;
            if ($vCegr->privilegio_elimina == TRUE) $bool_eegr_perm_elimina = true;
            if ($vCegr->privilegio_ver_docs == TRUE) $bool_eegr_perm_ver_docs = true;

            $row_ee_conf = array(
              "jerarquia" => $vCegr->jerarquia,
              "bool_eegr_catalogos" => $bool_eegr_catalogos,
              "bool_eegr_cat_prod" => $bool_eegr_cat_prod,
              "bool_eegr_cat_serv" => $bool_eegr_cat_serv,
              "bool_eegr_cat_actf" => $bool_eegr_cat_actf,
              "bool_eegr_cat_acti" => $bool_eegr_cat_acti,
              "bool_eegr_cat_prov" => $bool_eegr_cat_prov,
              "bool_eegr_cat_esta" => $bool_eegr_cat_esta,
              "bool_eegr_compras" => $bool_eegr_compras,
              "bool_eegr_comp_req" => $bool_eegr_comp_req,
              "bool_eegr_comp_cot" => $bool_eegr_comp_cot,
              "bool_eegr_comp_dir" => $bool_eegr_comp_dir,
              "bool_eegr_comp_seg" => $bool_eegr_comp_seg,
              "bool_eegr_perm_crear" => $bool_eegr_perm_crear,
              "bool_eegr_perm_editar" => $bool_eegr_perm_editar,
              "bool_eegr_perm_consulta" => $bool_eegr_perm_consulta,
              "bool_eegr_perm_elimina" => $bool_eegr_perm_elimina,
              "bool_eegr_perm_ver_docs" => $bool_eegr_perm_ver_docs,
            );
            $permisos_egresos[] = $row_ee_conf;
          }

          $token = array('user_token' => $usuario->user_token, 'empresa_token' => $value->empresa_token);
          $jwt = JWT::encode($token, "dtclavessecreto-9876986986986986s", 'HS256');

          $arrayforeach = array(
            "empresa_token" => $value->empresa_token,
            "company_name" => $nombreEmpresa,
            "tipo_sociedad" => $tipo_sociedad,
            "name_abrev" => $name_abrev,
            "zona_horaria" => $value->zona_horaria,
            "zona_horaria_utc" => $value->zona_horaria_utc,
            "codigo_pais" => $value->codigo_pais,
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
            "large_token_access" => $jwt,
            "active_class" => ""
          );
          $empCatalogoArray[] = $arrayforeach;
          //$empCatalogoArray[] = $arrayforeach;
          //$empCatalogoArray[] = $arrayforeach;
          //$empCatalogoArray[] = $arrayforeach;
          //$empCatalogoArray[] = $arrayforeach;
          //$empCatalogoArray[] = $arrayforeach;
          //$empCatalogoArray[] = $arrayforeach;
          //$empCatalogoArray[] = $arrayforeach;
          //$empCatalogoArray[] = $arrayforeach;
          //$empCatalogoArray[] = $arrayforeach;
        }
        $dataMensaje = array(
          'emp_result' => $empCatalogoArray,
          'code' => 200,
          'status' => 'success',
        );
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

  public function empresaConfigEegr(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $permisos_egresos = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "empresa_token" => "required|string",
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $empresa_token = $parametrosArray["empresa_token"];

        $queryConfigEegr = DB::table("configuracion_systema_eegr AS eegr_conf")
          ->join("main_empresas AS emp", "eegr_conf.empresa", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "eegr_conf.usuario", "users.id")
          ->where(["emp.empresa_token" => $empresa_token, "users.usuario_token" => $usuario->user_token])->get();

        foreach ($queryConfigEegr as $vCegr) {
          $bool_eegr_catalogos = false;
          $bool_eegr_cat_prod = false;
          $bool_eegr_cat_serv = false;
          $bool_eegr_cat_actf = false;
          $bool_eegr_cat_acti = false;
          $bool_eegr_cat_prov = false;
          $bool_eegr_cat_esta = false;
          $bool_eegr_compras = false;
          $bool_eegr_comp_req = false;
          $bool_eegr_comp_cot = false;
          $bool_eegr_comp_dir = false;
          $bool_eegr_comp_seg = false;
          $bool_eegr_perm_crear = false;
          $bool_eegr_perm_editar = false;
          $bool_eegr_perm_consulta = false;
          $bool_eegr_perm_elimina = false;
          $bool_eegr_perm_ver_docs = false;

          if ($vCegr->catalogos == TRUE) $bool_eegr_catalogos = true;
          if ($vCegr->cat_prod == TRUE) $bool_eegr_cat_prod = true;
          if ($vCegr->cat_serv == TRUE) $bool_eegr_cat_serv = true;
          if ($vCegr->cat_actf == TRUE) $bool_eegr_cat_actf = true;
          if ($vCegr->cat_acti == TRUE) $bool_eegr_cat_acti = true;
          if ($vCegr->cat_prov == TRUE) $bool_eegr_cat_prov = true;
          if ($vCegr->cat_esta == TRUE) $bool_eegr_cat_esta = true;
          if ($vCegr->compras == TRUE) $bool_eegr_compras = true;
          if ($vCegr->comp_req == TRUE) $bool_eegr_comp_req = true;
          if ($vCegr->comp_cot == TRUE) $bool_eegr_comp_cot = true;
          if ($vCegr->comp_dir == TRUE) $bool_eegr_comp_dir = true;
          if ($vCegr->comp_seg == TRUE) $bool_eegr_comp_seg = true;
          if ($vCegr->privilegio_crear == TRUE) $bool_eegr_perm_crear = true;
          if ($vCegr->privilegio_editar == TRUE) $bool_eegr_perm_editar = true;
          if ($vCegr->privilegio_consulta == TRUE) $bool_eegr_perm_consulta = true;
          if ($vCegr->privilegio_elimina == TRUE) $bool_eegr_perm_elimina = true;
          if ($vCegr->privilegio_ver_docs == TRUE) $bool_eegr_perm_ver_docs = true;

          $row_ee_conf = array(
            "jerarquia" => $vCegr->jerarquia,
            "bool_eegr_catalogos" => $bool_eegr_catalogos,
            "bool_eegr_cat_prod" => $bool_eegr_cat_prod,
            "bool_eegr_cat_serv" => $bool_eegr_cat_serv,
            "bool_eegr_cat_actf" => $bool_eegr_cat_actf,
            "bool_eegr_cat_acti" => $bool_eegr_cat_acti,
            "bool_eegr_cat_prov" => $bool_eegr_cat_prov,
            "bool_eegr_cat_esta" => $bool_eegr_cat_esta,
            "bool_eegr_compras" => $bool_eegr_compras,
            "bool_eegr_comp_req" => $bool_eegr_comp_req,
            "bool_eegr_comp_cot" => $bool_eegr_comp_cot,
            "bool_eegr_comp_dir" => $bool_eegr_comp_dir,
            "bool_eegr_comp_seg" => $bool_eegr_comp_seg,
            "bool_eegr_perm_crear" => $bool_eegr_perm_crear,
            "bool_eegr_perm_editar" => $bool_eegr_perm_editar,
            "bool_eegr_perm_consulta" => $bool_eegr_perm_consulta,
            "bool_eegr_perm_elimina" => $bool_eegr_perm_elimina,
            "bool_eegr_perm_ver_docs" => $bool_eegr_perm_ver_docs,
          );
          $permisos_egresos[] = $row_ee_conf;
        }

        $dataMensaje = array("status" => "success", "code" => 200, "conf_egresos" => $permisos_egresos);
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

  public function listaempresasAssociates(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmp = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'token_back_ter' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['token_back_ter'], true);

        $empList = EmpresasModelo::join("sos_personas AS people", "emp.persona", "=", "people.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "=", "pers.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
          ->join("teci_usuario_tipo AS tpuser", "users.tipo", "=", "tpuser.id")
          ->where([
            "tpuser.tipo" => "associate",
            "users.usuario_token" => $usuario->user_token
          ])->get();

        foreach ($empList as $value) {

          if ($value->denominacion_rs == '') {
            $nombreEmpresa = $JwtAuth->desencriptar($value->paterno) . " " . $JwtAuth->desencriptar($value->materno) . " " . $JwtAuth->desencriptar($value->nombre);
          } else {
            $nombreEmpresa = $JwtAuth->desencriptar($value->denominacion_rs);
          }

          $rfc_generico = $value->rfc_generico;

          if ($value->rfc != NULL) {
            $rfc_emp = $JwtAuth->desencriptar($value->rfc);
          } else {
            $rfc_emp = '---';
          }

          if ($value->tax_id != NULL) {
            $tax_id_emp = $JwtAuth->desencriptar($value->tax_id);
          } else {
            $tax_id_emp = '---';
          }

          //echo $JwtAuth->desencriptar($value->img_perfil);
          $logoTipo = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $value->root_tkn . '/0007-core/' . $JwtAuth->desencriptar($value->img_perfil)));

          $arrayforeach = array(
            "empresa_token" => $value->empresa_token,
            "company_name" => $nombreEmpresa,
            "rfc_generico" => $rfc_generico,
            "rfc_company" => $rfc_emp,
            "tax_id_company" => $tax_id_emp,
            "logotypo" => $logoTipo,
          );
          $arrayEmp[] = $arrayforeach;
        }
        $dataMensaje = array(
          'listCompanies' => $arrayEmp,
          'code' => 200,
          'status' => 'success',
        );
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

  public function selectEmpresasAssociates(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'token_back_ter' => 'required|string',
        'emp_selected' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['token_back_ter'], true);

        $empList = EmpresasModelo::join("sos_personas AS people", "emp.persona", "=", "people.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
          ->join("vhum_empleados_catalogo AS pers", "empuser.personal", "=", "pers.id")
          ->join("settings AS conf", "pers.id", "=", "conf.personal")
          ->join("area AS ar", "pers.area", "=", "ar.id")
          ->join("cargo AS car", "pers.cargo", "=", "car.id")
          ->join("teci_usuarios_catalogo AS users", "pers.usuario", "=", "users.id")
          ->join("teci_usuario_tipo AS tpuser", "users.tipo", "=", "tpuser.id")
          ->where([
            "tpuser.tipo" => "associate",
            "emp.empresa_token" => $parametrosArray['emp_selected'],
            "users.usuario_token" => $usuario->user_token
          ])->get();

        foreach ($empList as $value) {
          $infoUser = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "pers.usuario")
            ->join("settings", "pers.id", "=", "settings.personal")
            ->join("sos_personas AS people", "pers.personal", "=", "people.id")
            ->where([
              "users.usuario_token" => $usuario->user_token
            ])->get();

          $name_user_data = ucwords($JwtAuth->desencriptar($infoUser[0]->paterno) . " " .
            $JwtAuth->desencriptar($infoUser[0]->materno) . " " . $JwtAuth->desencriptar($infoUser[0]->nombre));

          if ($value->denominacion_rs == '') {
            $nombreEmpresa = $JwtAuth->desencriptar($value->paterno) . " " . $JwtAuth->desencriptar($value->materno) . " " . $JwtAuth->desencriptar($value->nombre);
          } else {
            $nombreEmpresa = $JwtAuth->desencriptar($value->denominacion_rs);
          }

          $rfc_generico = $value->rfc_generico;

          if ($value->rfc != NULL) {
            $rfc_emp = $JwtAuth->desencriptar($value->rfc);
          } else {
            $rfc_emp = '---';
          }

          if ($value->tax_id != NULL) {
            $tax_id_emp = $JwtAuth->desencriptar($value->tax_id);
          } else {
            $tax_id_emp = '---';
          }

          $logoTipo = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $value->root_tkn . '/0007-core/' . $JwtAuth->desencriptar($value->img_perfil)));

          if ($JwtAuth->desencriptar($value->img_perfil) == 'default-profile.png') {
            $avatar = $JwtAuth->encriptaBase64(Storage::path('public/settings/default-profile.png'));
          } else {
            $avatar = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $value->root_tkn .
              '/0004-vhm/catalogos/employees/' . $JwtAuth->generar($value->folio_pers) . '-' .
              $value->fecha_alta_pers . '/' . $JwtAuth->desencriptar($value->img_perfil) . '-profile.png'));
          }

          $areadb = $JwtAuth->desencriptar($value->areaemp);
          if ($value->areaemp == 'MkljUG5ya01tZUNqYjlrNkRaZ0ljQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
            $areasettings = 'airneg';
          } else if ($value->areaemp == 'OHNPcXphaG5ac3dFVFVtZW5UT3dRdz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
            $areasettings = 'aerger';
          } else if ($value->areaemp == 'akVjZ2ZyVzBJM3Q2QmYvbE96VmFoQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
            $areasettings = 'atseer';
          } else if ($value->areaemp == 'MjlOOWJJZDYvU2NOSXE4TDlNbCt1Zz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
            $areasettings = 'avsleh';
          } else if ($value->areaemp == 'NUxVVURJNXp2OGNlUFpCUm52dVJsdz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
            $areasettings = 'acsleo';
          } else if ($value->areaemp == 'QnZUL2pXcytLTnN3RlRDaWZWaUkwUHd6elVuU3dDSEl0UDFYak9ZSG1WWT06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
            if ($value->empresa_token =    'bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kv  YjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==') {
              $areasettings = 'aprtsieif';
            } else {
              $areasettings = 'asctsieif';
            }
          } else if ($value->areaemp == 'U0FyNDFBeWVpZ3V4d3ZTQklNZjBldmFwY3BHZUkvSHF3RmxkVjZqRTM3ST06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
            $areasettings = 'aasdemg';
          }

          //echo $JwtAuth->desencriptar($value->img_perfil);
          $logoTipo = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $value->root_tkn . '/0007-core/' . $JwtAuth->desencriptar($value->img_perfil)));

          $companies_working = 1;

          $selectCompanies = DB::select("SELECT COUNT(emp.id) AS workingCompanies FROM empresas AS emp 
                        JOIN empresapersonal AS empuser JOIN personal AS pers JOIN usuarios AS users 
                        JOIN tipo_usuario AS tpuser WHERE emp.id = empuser.empresa AND empuser.personal = pers.id
                        AND pers.usuario = users.id AND users.tipo = tpuser.id AND tpuser.tipo = 'associate' 
                        AND users.usuario_token = ?", [$usuario->user_token]);

          foreach ($selectCompanies as $vComSel) {
            $companies_working = $vComSel->workingCompanies;
          }

          $token = array(
            "user_token" => $value->user_token,
            "empresa_token" => $parametrosArray['emp_selected'],
          );

          $data_user = array(
            "user_token" => $value->user_token,
            "empresa_token" => $parametrosArray['emp_selected'],
            "companies_working" => $companies_working,
            "company_name" => $nombreEmpresa,
            "zona_horaria" => $value->zona_horaria,
            "zona_horaria_utc" => $value->zona_horaria_utc,
            "codigo_pais" => $value->codigo_pais,
            "rfc_generico" => $rfc_generico,
            "rfc_emp" => $rfc_emp,
            "tax_id_emp" => $tax_id_emp,
            "name" => $name_user_data,
            "lenguaje" => $value->lenguaje,
            "jerarquia" => $value->jerarquia,
            "area" => ucfirst(strtolower($areadb)),
            "areasettings" => $areasettings,
            "cargo" => ucfirst(strtolower($JwtAuth->desencriptar($value->cargo))),
            "iat" => time(),
            "exp" => time() + (7 * 24 * 60 * 60),
            "logotypo" => $logoTipo,
            "avatar" => $avatar,
          );

          $jwt = JWT::encode($token, "dtclavessecreto-9876986986986986s", "HS256");
          $jwt_data_user = JWT::encode($data_user, "dtclavessecreto-9876986986986986s", "HS256");
          $decodeTkn = JWT::decode($jwt_data_user, "dtclavessecreto-9876986986986986s", ["HS256"]);

          $dataMensaje = array(
            "status" => 'success',
            "code" => 200,
            "back_token" => $jwt,
            "data_user" => $decodeTkn,
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

  public function empresaCompleteRegistro(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpVig = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $empList = EmpresasModelo::join("sos_personas AS people", "emp.persona", "=", "people.id")
          ->join("teci_usuarios_catalogo AS users", "emp.usuario_administrador", "=", "users.id")
          ->join("solicitud_registro AS soli", "users.registro", "=", "soli.id_solicitud_registro")
          ->where([
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
          ])->get();

        foreach ($empList as $value) {

          if ($value->denominacion_rs == '') {
            $nombreEmpresa = $JwtAuth->desencriptar($value->paterno) . " " . $JwtAuth->desencriptar($value->materno) . " " . $JwtAuth->desencriptar($value->nombre);
            $tipoPersona = 'persona física';
          } else {
            $nombreEmpresa = $JwtAuth->desencriptar($value->denominacion_rs);
            $tipoPersona = 'persona moral';
          }

          if ($value->rfc_taxId != NULL) {
            $dataResRfc = $JwtAuth->desencriptar($value->rfc_taxId);
          } else {
            $dataResRfc = $value->rfc_generico;
          }

          $arrayforeach = array(
            'empresa_token' => $value->empresa_token,
            'company_name' => $nombreEmpresa,
            'tipoPersona' => $tipoPersona,
            'company_rfc' => $dataResRfc,
            'fecha_nac_const' => gmdate('Y-m-d H:i:s', $value->fecha_nac_const),
            'telefono' => $JwtAuth->desencriptar($value->telefono),
            'extension' => $JwtAuth->desencriptar($value->extension),
            'correo' => $JwtAuth->desencriptar($value->correo),
          );
          $arrayEmpVig[] = $arrayforeach;
        }
        $dataMensaje = array(
          'arrayEmpVig' => $arrayEmpVig,
          'code' => 200,
          'status' => 'success',
        );
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

  public function buscaRfcAllEmpresaOut(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'string',
        'tipoEmp' => 'required|string',
        'nombre' => 'required|string',
        'rfc_generico' => 'required|string',
        'emp_rfc' => 'string',
        'id_tax' => 'string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La infomación que ha intantado registrar es invalida',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        if ($usuario->empresa_token == "") {
          $empresa = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
        } else {
          $empresa = $usuario->empresa_token;
        }

        $paramtipoEmp = $parametrosArray['tipoEmp'];
        $paramNombreEmp = strtolower($parametrosArray['nombre']);
        $paramRfc = strtolower($parametrosArray['emp_rfc']);
        $paramIdTax = strtolower($parametrosArray['id_tax']);
        //$paramProvRfcGenerico = strtolower($parametrosArray['rfc_generico']);
        //echo "paramProvRfc ".$paramProvRfc." paramIdTax ".$paramIdTax;exit;

        if (
          isset($paramtipoEmp) && !empty($paramtipoEmp) && preg_match($JwtAuth->filtroRfc(), $paramtipoEmp) &&
          isset($paramNombreEmp) && !empty($paramNombreEmp) && preg_match($JwtAuth->filtroAlfabetico(), $paramNombreEmp)
        ) {

          $arrayEmpresas = array();
          $queryEmpresas = DB::table("main_empresas AS emp")
            ->join("sos_personas AS people", "emp.persona", "people.id")
            ->join("teci_pais AS ps", "people.nacionalidad", "ps.id")
            ->where(['emp.status_empresa' => true])->get();

          $countVerifica = 0;
          $invalidName = '';

          foreach ($queryEmpresas as $fEmp) {
            $nombre_emp = $fEmp->denominacion_rs != '' ? strtolower($JwtAuth->desencriptar($fEmp->denominacion_rs)) : strtolower($JwtAuth->desencriptarNombres($fEmp->paterno, $fEmp->materno, $fEmp->nombre));
            //$rfc_generico = strtolower($fEmp->rfc_generico);
            $rfc_emp = $fEmp->rfc != NULL ? strtolower($JwtAuth->desencriptar($fEmp->rfc)) : "";
            $tax_id_emp = $fEmp->tax_id != NULL ? strtolower($JwtAuth->desencriptar($fEmp->tax_id)) : "";

            $row_prov = array(
              "empresa" => $nombre_emp,
              "rfc_emp" => $rfc_emp,
              "tax_id_emp" => $tax_id_emp,
            );
            $arrayEmpresas[] = $row_prov;
          }
          $search_by_nombre = array_column($arrayEmpresas, "empresa");
          //$search_by_rfc_generico = array_column($arrayEmpresas,"rfc_generico");
          $search_by_rfc_emp = array_column($arrayEmpresas, "rfc_emp");
          $search_by_tax_id_emp = array_column($arrayEmpresas, "tax_id_emp");

          if ($JwtAuth->bool_rfc($paramRfc) == false && $JwtAuth->bool_rfc($paramIdTax) == false) {
            if (array_search($paramNombreEmp, $search_by_nombre) == "") {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'La empresa con nombre/razón social ' . strtoupper($paramNombreEmp) . ' no ha sido registrada'
              );
            } else {
              $invalidName = $arrayEmpresas[array_search($paramNombreEmp, array_column($arrayEmpresas, "empresa"))]["empresa"];
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'La empresa verificada ya ha sido registrada con nombre ' . strtoupper($invalidName)
              );
            }
          } else {
            if ($JwtAuth->bool_rfc($paramRfc) == true) {
              if (array_search($paramRfc, $search_by_rfc_emp) == "") {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'La empresa con nombre/razón social ' . strtoupper($paramNombreEmp) . ' no ha sido registrada'
                );
              } else {
                $invalidName = $arrayEmpresas[array_search($paramRfc, array_column($arrayEmpresas, "rfc_emp"))]["empresa"];
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'La empresa verificada ya ha sido registrada con nombre ' . strtoupper($invalidName)
                );
                return response()->json($dataMensaje, $dataMensaje['code']);
              }
            }

            if ($JwtAuth->bool_rfc($paramIdTax) == true) {
              if (array_search($paramIdTax, $search_by_tax_id_emp) == "") {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'La empresa con nombre/razón social ' . strtoupper($paramNombreEmp) . ' no ha sido registrada'
                );
              } else {
                $invalidName = $arrayEmpresas[array_search($paramIdTax, array_column($arrayEmpresas, "tax_id_emp"))]["empresa"];
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'La empresa verificada ya ha sido registrada con nombre ' . strtoupper($invalidName)
                );
              }
            }
          }
        } else {
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Nombre ó rfc generico de la empresa son incorrectos');
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

  public function empresaPerfil(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonServ = $request->input('json');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $dataEmpresa = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "string",
        "empresa_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Empresa invalido' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $empresa_token = $parametrosArray["empresa_token"];

        $empList = EmpresasModelo::join("sos_personas AS people", "emp.persona", "=", "people.id")
        ->join("teci_pais AS ispa", "people.nacionalidad", "=", "ispa.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("emp.status_empresa", "=", TRUE)
        ->where("emp.empresa_token",$empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        foreach ($empList as $vEmp) {
          //da_te_default_timezone_set('UTC');
          $logoTipoName = $JwtAuth->desencriptar($vEmp->img_perfil);
          $tkn_root = $vEmp->root_tkn;
          $logoTipo = $JwtAuth->encriptaBase64(Storage::path($logoTipoName == "empresa_desconocida.png" ? 'public/settings/empresa_desconocida.png' : "public/root/$tkn_root/0007-core/$logoTipoName"));

					$queryRegimenFiscal = DB::table("sos_regimen_fiscal")
					->where("id",$vEmp->emp_regimen_fiscal)
					->select('token_regimen_fiscal','clave','descripcion')
					->first();
          $regFiscalEmpToken = !is_null($vEmp->emp_regimen_fiscal) && $queryRegimenFiscal ? $queryRegimenFiscal->token_regimen_fiscal : '';
          $regFiscalEmpDescripcion = !is_null($vEmp->emp_regimen_fiscal) && $queryRegimenFiscal ? $queryRegimenFiscal->clave."-".$queryRegimenFiscal->descripcion : '';

          $row = array(
            "empresa_token" => $vEmp->empresa_token,
            "empresa_folio" => 'EMP-'.$JwtAuth->generarFolio($vEmp->empresa_folio),
            "name_abrev" => $vEmp->abrev_nombre,
            "company_name" => $vEmp->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($vEmp->paterno, $vEmp->materno, $vEmp->nombre) : $JwtAuth->desencriptar($vEmp->denominacion_rs),
            "codigo_pais" => $vEmp->codigo_pais,
            "rfc_generico" => $vEmp->rfc_generico,
            "rfc_company" => $vEmp->rfc != NULL ? $JwtAuth->desencriptar($vEmp->rfc) : '---',
            "tax_id_company" => $vEmp->tax_id != NULL ? $JwtAuth->desencriptar($vEmp->tax_id) : '---',
            "logotypo" => $logoTipo,
            "emp_regimen_fiscal_token" => $regFiscalEmpToken,
            "emp_regimen_fiscal_descripcion" => $regFiscalEmpDescripcion,
            "emp_habilita_centros_de_trabajo" => $vEmp->habilita_centros_de_trabajo ? true : false,
            "fecha_nac_const" => !is_null($vEmp->fecha_nac_const) && $vEmp->fecha_nac_const != '' ? gmdate('Y-m-d H:i:s', $vEmp->fecha_nac_const) : '',
            "zona_horaria" => $vEmp->zona_horaria,
            "zona_horaria_utc" => $vEmp->zona_horaria_utc,
            "moneda_code" => $vEmp->e_moneda_code,
            "moneda_decimales" => $vEmp->e_moneda_decimales
          );
          $dataEmpresa[] = $row;
        }
        $dataMensaje = array(
          'empresaInfo' => $dataEmpresa,
          'code' => 200,
          'status' => 'success',
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function empresaDetalle(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonServ = $request->input('json');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);
    $dataEmpresa = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "string",
        "empresa_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Empresa invalido' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $empresa_token = $parametrosArray["empresa_token"];

        $empList = EmpresasModelo::join("sos_personas AS people", "emp.persona", "=", "people.id")
        ->join("teci_pais AS ispa", "people.nacionalidad", "=", "ispa.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("emp.status_empresa", "=", TRUE)
        ->where("emp.empresa_token",$empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        foreach ($empList as $value) {
          $nombreEmpresa = $value->denominacion_rs == '' ? $JwtAuth->desencriptarNombres($value->paterno, $value->materno, $value->nombre) : $JwtAuth->desencriptar($value->denominacion_rs);
          $rfc_generico = $value->rfc_generico;
          $rfc_emp = $value->rfc != NULL ? $JwtAuth->desencriptar($value->rfc) : '---';
          $tax_id_emp = $value->tax_id != NULL ? $JwtAuth->desencriptar($value->tax_id) : '---';
          $logoTipo = "https://downloads.sos-mexico.com.mx/empresa_img/" . $value->empresa_token;

          $list_users_vinculados = array();
          $list_users_no_vinculados = array();
          $queryUsers = DB::table("teci_usuarios_catalogo AS users")
          //->join("main_empresa_usuario AS empuser", "users.id", "empuser.usuario")
          //->join("main_empresas AS emp", "empuser.empresa", "emp.id")
          //->where("emp.empresa_token",$empresa_token)
          ->get();

          foreach ($queryUsers as $vUser) {
            $queryUsersVinc = DB::table("teci_usuarios_catalogo AS users")
            ->join("main_empresa_usuario AS empuser", "users.id", "empuser.usuario")
            ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
            ->where("users.usuario_token",$vUser->usuario_token)
            ->where("emp.empresa_token",$value->empresa_token)
            ->get();

            $userRow = array(
              "usuario_token" => $vUser->usuario_token,
              "usuario_folio" => 'USER-'.$JwtAuth->generarFolio($vUser->usuario_folio),
              "usuario_alias" => $JwtAuth->desencriptar($vUser->usuario_alias),
            );
            if (count($queryUsersVinc) == 1) {
              $list_users_vinculados[] = $userRow;
            } else {
              $list_users_no_vinculados[] = $userRow;
            }
          }

          //$queryUsersNoVinc = DB::table('teci_usuarios_catalogo AS users')
          //->whereNotIn('users.id', function($query) use ($empresa_token) {
          //  $query->select('empuser.usuario')
          //  ->from('main_empresa_usuario AS empuser')
          //  ->join('main_empresas AS emp', 'empuser.empresa', '=', 'emp.id')
          //  ->where('emp.empresa_token', $empresa_token);
          //})
          //->get();
          //foreach ($queryUsersNoVinc as $vUserNoRel) {
          //  $userNoRow = array(
          //    "usuario_token" => $vUserNoRel->usuario_token,
          //    "usuario_folio" => 'USER-'.$JwtAuth->generarFolio($vUserNoRel->usuario_folio),
          //    "usuario_alias" => $JwtAuth->desencriptar($vUserNoRel->usuario_alias),
          //  );
          //  $list_users_no_vinculados[] = $userNoRow;
          //}
          
          $row = array(
            "empresa_token" => $value->empresa_token,
            "name_abrev" => $value->abrev_nombre,
            "company_name" => $nombreEmpresa,
            "zona_horaria" => $value->zona_horaria,
            "zona_horaria_utc" => $value->zona_horaria_utc,
            "codigo_pais" => $value->codigo_pais,
            "rfc_generico" => $rfc_generico,
            "rfc_company" => $rfc_emp,
            "tax_id_company" => $tax_id_emp,
            "logotypo" => $logoTipo,
            "usuarios_vinculados" => $list_users_vinculados,
            "usuarios_no_vinculados" => $list_users_no_vinculados,
          );
          $dataEmpresa[] = $row;
        }
        $dataMensaje = array(
          'empresaInfo' => $dataEmpresa,
          'code' => 200,
          'status' => 'success',
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function vincularEmpresaUsuario(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonServ = $request->input('json');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "empresa_token" => "required|string",
        "usuario_token" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Empresa invalido' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $empresa_token = $parametrosArray["empresa_token"];
        $usuario_token = $parametrosArray["usuario_token"];

        $OKempresa_token = isset($empresa_token) && !empty($empresa_token);
        $OKusuario_token = isset($usuario_token) && !empty($usuario_token);

        if ($OKempresa_token && $OKusuario_token) {
          $empresa_id = DB::table("main_empresas")->where("empresa_token",$empresa_token)->value("id");
          $usuario_id = DB::table("teci_usuarios_catalogo")->where("usuario_token",$usuario_token)->value("id");

          $queryVincula = DB::table("main_empresa_usuario")
          ->insert(array(
            "empresa" => $empresa_id,
            "usuario" => $usuario_id,
            "vinculacion_estado" => TRUE
          ));

          if ($queryVincula) {
            $dataMensaje = array(
              'status' => 'success', 
              'code' => 200, 
              'message' => 'Usuario y empresa han sido vinculados'
            );
          } else {
            $dataMensaje = array(
              'status' => 'success', 
              'code' => 200, 
              'message' => 'Usuario y empresa no vinculados'
            );
          }
        } else {
          $mensaje_error = "";
          if (!$OKempresa_token) $mensaje_error = "Error al seleccionar empresa, intentelo nuevamente o comuniquese a soporte";
          if (!$OKusuario_token) $mensaje_error = "Error al seleccionar usuario, intentelo nuevamente o comuniquese a soporte";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
        
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraEmpresaMin(Request $request){
    $JwtAuth = new \App\Helpers\JwtAuth();
    $jsonServ = $request->input('json');
    $parametros = json_decode($jsonServ);
    $parametrosArray = json_decode($jsonServ, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "string",
        "rfc_generico" => "required|string",
        "emp_rfc" => "string",
        "id_tax" => "string",
        "tipoEmp" => "required|string",
        "subtipoEmp" => "required|string",
        "razon_social" => "string",
        "abrev" => "string",
        "comercial_nombre" => "string",
        "curp" => "string",
        "paistoken" => "string",
        "sitio_web" => "string",
        "tknRegimenFiscal" => "string"
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Empresa invalido' . $validate->errors(),
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $fechaAlta = time();
        //$user_token = $parametrosArray["user_token"];
        $rfc_generico = $parametrosArray["rfc_generico"];
        $emp_rfc = $parametrosArray["emp_rfc"];
        $rfc_emp = NULL;
        $id_tax = $parametrosArray["id_tax"];
        $idtax = NULL;
        $tipoEmp = $parametrosArray["tipoEmp"];
        $subtipoEmp = $parametrosArray["subtipoEmp"];
        $razon_social = $parametrosArray["razon_social"];
        $abrev = $parametrosArray["abrev"];
        $comercial_nombre = $parametrosArray["comercial_nombre"];
        $curp = $parametrosArray["curp"];
        $paistoken = $parametrosArray["paistoken"];
        $sitio_web = $parametrosArray["sitio_web"];
        $tknRegimenFiscal = $parametrosArray["tknRegimenFiscal"];
        
        $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
        $patronNum = '/^[1-9][0-9]*$/';
        $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
        $patronNumCred = '/^[0-9$,.-]*$/';
        $patronRfc = '/[aA0-zZ9]/';
        $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
        $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp WHERE emp.empresa_token = ?", [$usuario->empresa_token]);
        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

        $select_fol_emp = DB::select("SELECT MAX(empresa_folio)+1 AS fol_max FROM main_empresas");
        foreach ($select_fol_emp as $vTemp) {
          $folio_nuevo = $vTemp->fol_max;
          $folio_nuevo_extend = 'EMP-' . $JwtAuth->generarFolio($vTemp->fol_max);
        }
        //echo strlen("rootSTZTMzhQUG9ZSmlXVWVQd2dLN3JJRnQyMGYvSmhn")." ".$folio_nuevo." ".$folio_nuevo_extend; exit;
        if ($emp_rfc != "") {
          if (isset($emp_rfc) && isset($emp_rfc) && preg_match($patronRfc, $emp_rfc)) {
            $rfc_emp = $JwtAuth->encriptar($emp_rfc);
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'error al registrar rfc de la empresa'
            );
          }
        } else {
          $rfc_emp = NULL;
        }

        if ($id_tax != "") {
          if (isset($id_tax) && preg_match($patronRfc, $id_tax)) {
            $idtax = $JwtAuth->encriptar($id_tax);
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'error al registrar idtax de la empresa'
            );
          }
        } else {
          $idtax = NULL;
        }

        $empresa_txt = NULL;
        $comercial_nombre_txt = NULL;
        $curp_txt = NULL;
        $pais_txt = NULL;
        $sitio_web_txt = NULL;
        $regimen_fiscal_txt = NULL;

        if (isset($tipoEmp) && isset($tipoEmp) && preg_match($patron, $tipoEmp)) {
          if ($tipoEmp == "extranjero") {
            if (isset($subtipoEmp) && isset($subtipoEmp) && preg_match($patron, $subtipoEmp)) {
              if ($subtipoEmp == "empresaMoral") {
                //return response()->json(['message' => $parametrosArray['pais'],'codigo' => 200,'status' => 'error']);
                $valida_razon_social = isset($razon_social) && !empty($razon_social) && preg_match($patron, $razon_social); 
                $valida_paistoken = isset($paistoken) && !empty($paistoken);
                if ($valida_razon_social && $valida_paistoken) {
                  //return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
                  $empresa_txt = $JwtAuth->encriptar($razon_social);
                  //echo "razon_social ".$razon_social_txt;exit;$JwtAuth->encriptar("cnlZSktiM1FlMHlqbk13ZFhkS0ozUT09OjoxMjM0NTY3ODEyMzQ1Njc4")
                  //return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
                  $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
                  $pais_txt = $selectPais[0]->id;
                  //return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
                  if (!empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                    if (preg_match($patron, $comercial_nombre)) {
                      $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'nomcom',
                        'message' => 'Error en nombre comercial de su empresa'
                      );
                    }
                    if (preg_match($patronUrl, $sitio_web)) {
                      $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'websitio',
                        'message' => 'Error en sitio web de su empresa'
                      );
                    }
                  } else {
                    $comercial_nombre_txt = NULL;
                    $sitio_web_txt = NULL;
                  }
                } else {
                  if (!$valida_razon_social) {
                    $dataMensaje = array("status" => "error","code" => 200,"codeProvGenError" => "nomemp","message" => "Error en nombre de empresa de su empresa");
                  }
                  if (!$valida_paistoken) {
                    $dataMensaje = array("status" => "error","code" => 200,"codeProvGenError" => "pais","message" => "Error en pais de su empresa");
                  }
                }
              }
              //return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);		
              if ($subtipoEmp == 'empresaFisica') {
                $valida_razon_social = isset($razon_social) && !empty($razon_social) && preg_match($patron, $razon_social); 
                $valida_paistoken = isset($paistoken) && !empty($paistoken);
                if ($valida_razon_social && $valida_paistoken) {
                  $empresa_txt = $JwtAuth->encriptar($razon_social);
                  if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                    if (preg_match($patron, $comercial_nombre)) {
                      $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'nomcom',
                        'message' => 'Error en nombre comercial de su empresa'
                      );
                    }

                    if (preg_match($patronUrl, $sitio_web)) {
                      $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'websitio',
                        'message' => 'Error en sitio web de su empresa'
                      );
                    }
                  } else {
                    $comercial_nombre_txt = NULL;
                    $sitio_web_txt = NULL;
                  }

                  $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
                  $pais_txt = $selectPais[0]->id;
                } else {
                  if (!$valida_razon_social) {
                    $dataMensaje = array("status" => "error","code" => 200,"codeProvGenError" => "nomemp","message" => "Error en nombre de empresa de su empresa");
                  }
                  if (!$valida_paistoken) {
                    $dataMensaje = array("status" => "error","code" => 200,"codeProvGenError" => "pais","message" => "Error en pais de su empresa");
                  }
                }
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'codeProvGenError' => 'clbint',
                'message' => 'Seleccione subtipo de empresa (persona física o moral)'
              );
            }
          }

          if ($tipoEmp == 'nacional') {
            if (isset($subtipoEmp) && isset($subtipoEmp) && preg_match($patron, $subtipoEmp)) {
              if ($subtipoEmp == 'empresaMoral') {
                $valida_razon_social = isset($razon_social) && !empty($razon_social) && preg_match($patron, $razon_social); 
                if ($valida_razon_social) {
                  $empresa_txt = $JwtAuth->encriptar($razon_social);
                  if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                    if (preg_match($patron, $comercial_nombre)) {
                      $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'nomcom',
                        'message' => 'Error en nombre comercial de su empresa'
                      );
                    }
                    if (preg_match($patronUrl, $sitio_web)) {
                      $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'websitio',
                        'message' => 'Error en sitio web de su empresa'
                      );
                    }
                  } else {
                    $comercial_nombre_txt = NULL;
                    $sitio_web_txt = NULL;
                  }
                  $pais_txt = '118';
                } else {
                  $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "codeProvGenError" => "nomemp",
                    "message" => "Error en nombre de empresa de su empresa"
                  );
                }
              }

              if ($subtipoEmp == 'empresaFisica') {
                if ($valida_razon_social) {
                  $empresa_txt = $JwtAuth->encriptar($razon_social);

                  if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($curp) && !empty($curp) && isset($sitio_web) && !empty($sitio_web)) {
                    if (preg_match($patron, $comercial_nombre)) {
                      $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                    } else {
                      $dataMensaje = array('status' => 'error','code' => 200,'codeProvGenError' => 'nomcom','message' => 'Error en nombre comercial de su empresa');
                    }

                    if (preg_match($patronRfc, $curp)) {
                      $curp_txt = $JwtAuth->encriptar($curp);
                    } else {
                      $dataMensaje = array('status' => 'error','code' => 200,'codeProvGenError' => 'clbint','message' => 'Error en curp de su empresa');
                    }

                    if (preg_match($patronUrl, $sitio_web)) {
                      $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                    } else {
                      $dataMensaje = array('status' => 'error','code' => 200,'codeProvGenError' => 'websitio','message' => 'Error en sitio web de su empresa');
                    }
                  } else {
                    $comercial_nombre_txt = NULL;
                    $curp_txt = NULL;
                    $sitio_web_txt = NULL;
                  }

                  $pais_txt = '118';
                } else {
                  $dataMensaje = array("status" => "error","code" => 200,"codeProvGenError" => "nomemp","message" => "Error en nombre de empresa de su empresa");
                }
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'codeProvGenError' => 'clbint',
                'message' => 'Seleccione subtipo de empresa (persona física o moral)'
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'codeProvGenError' => 'clbint',
            'message' => 'Seleccione tipo de empresa (nacional o extranjero)'
          );
        }
        
        $EmpresaExiste = DB::table("main_empresas AS emp")
        ->join("sos_personas AS people", "emp.persona", "people.id")
        ->join("teci_pais AS ps", "people.nacionalidad", "ps.id")
        ->where('emp.status_empresa',TRUE)
        ->where(function ($query) use ($rfc_emp, $idtax, $empresa_txt) {
          if ($rfc_emp) {
            $query->orWhere('people.rfc', $rfc_emp);
          }
          if (!empty($idtax)) {
            $query->orWhere('people.tax_id', $idtax);
          }
          if (!empty($empresa_txt)) {
            $query->orWhereRaw('LOWER(people.denominacion_rs) = ?', [strtolower($empresa_txt)]);
          }
        })->exists();

        if (!$EmpresaExiste) {

          if (file_exists($request->file('logotypo_emp'))) {
            //Storage::putFileAs("/public/root/".$filepath,$request->file('imagenAltaPdfFiscal'),$JwtAuth->desencriptar($constsitfiscal));
            $img_logotypo = "";
          } else {
            $img_logotypo = "empresa_desconocida.png";
          }

          $tkn_people_emp = $JwtAuth->encriptarToken(
            $fechaAlta,
            $empresa_txt,
            $comercial_nombre_txt,
            $sitio_web_txt,
            $pais_txt,
            $rfc_emp
          );
          //echo $img_logotypo; exit;
          $insertPeopleEmp = DB::table("sos_personas")
            ->insert(array(
              "token_personas" => $tkn_people_emp,
              "abrev_nombre" => $abrev,
              "denominacion_rs" => $empresa_txt,
              "nombre_com" => $comercial_nombre_txt,
              "sitio_web" => $sitio_web_txt,
              "nacionalidad" => $pais_txt,
              "rfc_generico" => $rfc_generico,
              "rfc" => $rfc_emp,
              "tax_id" => $idtax,
              "curp" => $curp_txt,
              "img_perfil" => $JwtAuth->encriptar($img_logotypo),
            ));

          if ($insertPeopleEmp) {
            $selecPeopleEmp = DB::select("SELECT id FROM sos_personas WHERE token_personas = ?", [$tkn_people_emp]);
            $tkn_emp_main = $JwtAuth->encriptarToken($tkn_people_emp, $img_logotypo, "America/Mexico_City", "UTC-6");

            $txt_id_reg_fiscal = $tknRegimenFiscal != "" ? DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal", $tknRegimenFiscal)->value("id") : NULL;

            $filepathRoot = "root_" . $folio_nuevo . "_" . substr($tkn_emp_main, 0, 46);
            $insertEmpMain = DB::table("main_empresas")
              ->insert(array(
                "empresa_token" => $tkn_emp_main,
                "empresa_folio" => $folio_nuevo,
                "root_tkn" => $filepathRoot,
                "persona" => $selecPeopleEmp[0]->id,
                "emp_regimen_fiscal" => $txt_id_reg_fiscal,
                "emp_habilita_centros_de_trabajo" => false,
                "fecha_nac_const" => time(),
                "zona_horaria" => "America/Mexico_City",
                "zona_horaria_utc" => "UTC-6",
                "e_moneda_code" => "MXN",
                "e_moneda_decimales" => 2,
                "logotipo" => $JwtAuth->encriptar($img_logotypo),
                "usuario_administrador" => 2,
                "status_empresa" => TRUE,
                "fecha_delete_empresa" => NULL,
              ));
            //echo strlen("rootSTZTMzhQUG9ZSmlXVWVQd2dLN3JJRnQyMGYvSmhn")." ".$folio_nuevo." ".$folio_nuevo_extend; exit;
            if ($insertEmpMain) {
              $mainNewEmp = DB::TABLE("main_empresas")->where("empresa_token",$tkn_emp_main)->value("id");
              if (!file_exists(storage_path("/root/" . $filepathRoot))) {
                Storage::disk('root')->makeDirectory($filepathRoot, 0777, true, true);
                Storage::disk('root')->makeDirectory($filepathRoot . "/0001-cpc", 0777, true, true);
                Storage::disk('root')->makeDirectory($filepathRoot . "/0002-cpp", 0777, true, true);
                Storage::disk('root')->makeDirectory($filepathRoot . "/0003-tes", 0777, true, true);
                Storage::disk('root')->makeDirectory($filepathRoot . "/0004-vhm", 0777, true, true);
                Storage::disk('root')->makeDirectory($filepathRoot . "/0005-cnt", 0777, true, true);
                Storage::disk('root')->makeDirectory($filepathRoot . "/0006-tnf", 0777, true, true);
                Storage::disk('root')->makeDirectory($filepathRoot . "/0007-core", 0777, true, true);
                Storage::disk('root')->makeDirectory($filepathRoot . "/0008-proyectos", 0777, true, true);
                Storage::disk('root')->makeDirectory($filepathRoot . "/0009-cfdi", 0777, true, true);
                Storage::disk('root')->makeDirectory($filepathRoot . "/0010-reem", 0777, true, true);
                Storage::disk('root')->makeDirectory($filepathRoot . "/0011-just", 0777, true, true);
              }

              $insertempuserUnionUno = DB::table("main_empresa_usuario")->insert(array("empresa" => $mainNewEmp, "usuario" => 1));
              $JwtAuth->registra_permisos_nueva_empresa($mainNewEmp, 1);

              $insertempuserUnionUno = DB::table("main_empresa_usuario")->insert(array("empresa" => $mainNewEmp, "usuario" => 2));
              $JwtAuth->registra_permisos_nueva_empresa($mainNewEmp, 2);

              $insertempuserUnionUno = DB::table("main_empresa_usuario")->insert(array("empresa" => $mainNewEmp, "usuario" => 3));
              $JwtAuth->registra_permisos_nueva_empresa($mainNewEmp, 3);
              $JwtAuth->registra_permisos_usuario_old($mainNewEmp);

              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => 'Empresa registrada satisfactoriamente con el folio ' . $folio_nuevo_extend
              );
            } else {
              $deleteDir = DB::table('sos_personas')->where(["token_personas" => $tkn_people_emp])->delete();
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Datos generales de esta empresa no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Datos generales de esta empresa no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'ya existe un proveedor con esta información'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function empresaLogotypo($tokenEmpresa){
    $JwtAuth = new \App\Helpers\JwtAuth();
    if (!empty($tokenEmpresa) && !empty($tokenEmpresa)) {
      $selectedEmp = DB::table("sos_personas AS people")
        ->join("main_empresas AS emp", "people.id", "=", "emp.persona")
        ->where(["emp.empresa_token" => $tokenEmpresa])->get();
      foreach ($selectedEmp as $value) {
        $logo_name = $JwtAuth->desencriptar($value->img_perfil);
        if ($logo_name == "empresa_desconocida.png") {
          return response(Storage::disk('settings')->get("empresa_desconocida.png"), 200)
            ->header('Content-Type', Storage::disk('settings')->mimeType("empresa_desconocida.png"));
        } else {
          $logoTipo = $value->root_tkn . '/0007-core/' . $logo_name;
          return response(Storage::disk('root')->get($logoTipo), 200)
            ->header('Content-Type', Storage::disk('root')->mimeType($logoTipo));
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los datos no son correctos'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}
