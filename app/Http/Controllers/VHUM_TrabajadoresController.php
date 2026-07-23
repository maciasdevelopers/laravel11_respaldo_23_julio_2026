<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\PersonalModelo;
use App\Services\FirebaseService;

class VHUM_TrabajadoresController extends Controller{
  public function registraTrabajador_(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpleados = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'apePaterno' => 'required|string',
        'apeMaterno' => 'required|string',
        'nombres' => 'required|string',
        'edad' => 'required|numeric',
        'domicilio_CalleNumero' => 'required|string',
        'domicilio_cod_postal' => 'required|numeric',
        'domicilio_colonia_vinculada' => 'required|string',
        'domicilio_municipio' => 'required|string',
        'domicilio_estado' => 'required|string',
        'origen_nacimiento_fecha' => 'required|string',
        'origen_nacimiento_lugar' => 'required|string',
        'origen_nacionalidad' => 'required|string',
        'sexo' => 'required|string',
        'estado_civil' => 'required|string',
        'contacto_telefono_tipo' => 'required|string',
        'contacto_telefono_numero' => 'required|string',
        'contacto_email' => 'required|string',
        'documentacion_curp' => 'required|string',
        'documentacion_rfc' => 'required|string',
        'documentacion_pasaporte' => 'required|string',
        'documentacion_numero_de_seguridad_social' => 'required|string',
        'documentacion_licencia_tiene' => 'required|boolean',
        'documentacion_licencia_clase' => 'string',
        'documentacion_licencia_numero' => 'string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $emisor_emp = 1;
        $emisor_tkn_user = $usuario->user_token;
        $emisor_pers = 3;
        $select_reembolso_main = 18;
        $titulo_alerta = "Solicitud de reembolso registrada con el folio: 18";
        $egresos_valua = TRUE;
        $valor_humano_valua = false;
        $egresos_user = 3;
        $valor_humano_user = 3;
        //$select_reembolso_main = DB::select("SELECT id
        $JwtAuth->insertNotifReembolsos("tercR", "Registro de reembolso", $titulo_alerta, $select_reembolso_main, NULL, $emisor_emp, $emisor_pers, $emisor_pers);
        $JwtAuth->notificacionPushDevices($emisor_tkn_user, "SOS-México - Portal para empleados", $titulo_alerta);
        return response()->json(['status' => 'error','code' => 200,'message' => 'true5.1r'.$titulo_alerta]);
        if ($egresos_valua == TRUE) {
          //$egresos_user = 6;
          $JwtAuth->insertNotifReembolsos("tercR", "Registro de reembolso", $titulo_alerta, $select_reembolso_main, NULL, $emisor_emp, $emisor_pers, $egresos_user);
          $selectDTUserEgr = DB::select("SELECT users.usuario_token FROM teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers WHERE users.empleado = pers.id 
              AND pers.id = ?", [$egresos_user]);
          foreach ($selectDTUserEgr as $vuedt) {
            $JwtAuth->notificacionPushDevices($vuedt->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
          }
        }
        if ($valor_humano_valua == TRUE) {
          //$valor_humano_user = 7;
          $JwtAuth->insertNotifReembolsos("tercR", "Registro de reembolso", $titulo_alerta, $select_reembolso_main, NULL, $emisor_emp, $emisor_pers, $valor_humano_user);
          $selectDTUserVHUM = DB::select("SELECT users.usuario_token FROM teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers WHERE users.empleado = pers.id 
              AND pers.id = ?", [$valor_humano_user]);
          foreach ($selectDTUserVHUM as $vuvhdt) {
            $JwtAuth->notificacionPushDevices($vuvhdt->usuario_token, "SOS-México - Portal para empleados", $titulo_alerta);
          }
        }

        /*$deviceToken = "fJHfbzPf5MfLFrNkRge3up:APA91bEyjEm63J7tmjX-gWyGsABq2WKzWn3XOUSGDRvqsSAZMHhF3dod1wfaZXc9_aC3Om2-klcToCYHEkMue42q4C3yWJOzYEDse7amwwT_qB5mzpyG5no"; // el que obtienes en Angular
        //$firebase = new FirebaseService();
        //$response = $firebase->sendNotification($deviceToken, "Hola!", "Esto es una notificación desde Laravel 🚀");
        //return response()->json($response);
        $creds = json_decode(file_get_contents(storage_path('app/firebase/sosmexico-b2eb5-68d0c7d82768.json')), true);
        $jwt = \App\Services\FirebaseJWT::createJwt($creds['client_email'], $creds['private_key']);
        // Obtener access_token
        $ch = curl_init("https://oauth2.googleapis.com/token");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
            "assertion" => $jwt,
        ]));
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        $accessToken = $data['access_token'];

        $payload = [
            "message" => [
                "token" => $deviceToken,
                "notification" => [
                    "title" => "SOS-México informa: ",
                    "body"  => "Esto es una notificación desde Laravel 🚀",
                ],
            ],
        ];

        $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$creds['project_id']}/messages:send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;*/
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraTrabajador(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'apePaterno' => 'required|string',
        'apeMaterno' => 'required|string',
        'nombres' => 'required|string',
        'edad' => 'required|numeric',
        'domicilio_CalleNumero' => 'required|string',
        'domicilio_cod_postal' => 'required|numeric',
        'domicilio_colonia_vinculada' => 'required|string',
        'domicilio_municipio' => 'required|string',
        'domicilio_estado' => 'required|string',
        'origen_nacimiento_fecha' => 'required|string',
        'origen_nacimiento_lugar' => 'required|string',
        'origen_nacionalidad' => 'required|string',
        'sexo' => 'required|string',
        'estado_civil' => 'required|string',
        //'regimen_trabajador' => 'string',
        'contacto_telefono_tipo' => 'required|string',
        'contacto_telefono_numero' => 'required|string',
        'contacto_email' => 'required|string',
        'documentacion_curp' => 'required|string',
        'documentacion_rfc' => 'required|string',
        'documentacion_pasaporte' => 'array',
        'documentacion_visa' => 'array',
        'documentacion_numero_de_seguridad_social' => 'required|string',
        'documentacion_licencia' => 'array',
        'cbancaria_banco_token' => 'string',
        'cbancaria_cuenta' => 'string',
        'cbancaria_clabe_inter' => 'string',
        'cbancaria_sucursal' => 'string',
        'centro_de_trabajo' => 'string',
        'departamento' => 'string',
        'puesto' => 'string',
        'salario_tipo' => 'required|string',
        'contratacion_tipo' => 'required|string',
        'contratacion_fecha' => 'required|string',
        'alta_en_empresa' => 'required|string',
        'nomina_periodicidad' => 'string',
        'nomina_moneda' => 'string',
        'tipo_jornada' => 'string',
        'turno' => 'string',
        'nomina_salario_diario' => 'string',
        'nomina_salario_integrado' => 'string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $apePaterno = $parametrosArray['apePaterno'];
        $apeMaterno = $parametrosArray['apeMaterno'];
        $nombres = $parametrosArray['nombres'];
        $edad = $parametrosArray['edad'];
        $domicilio_CalleNumero = $parametrosArray['domicilio_CalleNumero'];
        $domicilio_cod_postal = $parametrosArray['domicilio_cod_postal'];
        $domicilio_colonia_vinculada = $parametrosArray['domicilio_colonia_vinculada'];
        $domicilio_municipio = $parametrosArray['domicilio_municipio'];
        $domicilio_estado = $parametrosArray['domicilio_estado'];
        $origen_nacimiento_fecha = $parametrosArray['origen_nacimiento_fecha'];
        $origen_nacimiento_lugar = $parametrosArray['origen_nacimiento_lugar'];
        $origen_nacionalidad = $parametrosArray['origen_nacionalidad'];
        $sexo = $parametrosArray['sexo'];
        $estado_civil = $parametrosArray['estado_civil'];
        //$regimen_trabajador = $parametrosArray['regimen_trabajador'];
        $contacto_telefono_tipo = $parametrosArray['contacto_telefono_tipo'];
        $contacto_telefono_numero = $parametrosArray['contacto_telefono_numero'];
        $contacto_email = $parametrosArray['contacto_email'];
        $documentacion_curp = $parametrosArray['documentacion_curp'];
        $documentacion_rfc = $parametrosArray['documentacion_rfc'];
        $documentacion_pasaporte = $parametrosArray['documentacion_pasaporte'];
        $documentacion_visa = $parametrosArray['documentacion_visa'];
        $documentacion_numero_de_seguridad_social = $parametrosArray['documentacion_numero_de_seguridad_social'];
        $documentacion_licencia = $parametrosArray['documentacion_licencia'];

        $cbancaria_banco_token = $parametrosArray['cbancaria_banco_token'];
        $cbancaria_cuenta = $parametrosArray['cbancaria_cuenta'];
        $cbancaria_clabe_inter = $parametrosArray['cbancaria_clabe_inter'];
        $cbancaria_sucursal = $parametrosArray['cbancaria_sucursal'];
        $centro_de_trabajo = $parametrosArray['centro_de_trabajo'];
        $departamento = $parametrosArray['departamento'];
        $puesto = $parametrosArray['puesto'];
        $salario_tipo = $parametrosArray['salario_tipo'];
        $contratacion_tipo = $parametrosArray['contratacion_tipo'];
        $contratacion_fecha = $parametrosArray['contratacion_fecha'];
        $alta_en_empresa = $parametrosArray['alta_en_empresa'];
        $nomina_periodicidad = $parametrosArray['nomina_periodicidad'];
        $nomina_moneda = $parametrosArray['nomina_moneda'];
        $tipo_jornada = $parametrosArray['tipo_jornada'];
        $turno = $parametrosArray['turno'];
        $nomina_salario_diario = $parametrosArray['nomina_salario_diario'];
        $nomina_salario_integrado = $parametrosArray['nomina_salario_integrado'];

        $OKPaterno = isset($apePaterno) && !empty($apePaterno) && preg_match($JwtAuth->filtroAlfaNumerico(),$apePaterno);
        $OKMaterno = isset($apeMaterno) && !empty($apeMaterno) && preg_match($JwtAuth->filtroAlfaNumerico(),$apeMaterno);
        $OKNombres = isset($nombres) && !empty($nombres) && preg_match($JwtAuth->filtroAlfaNumerico(),$nombres);
        $OKEdad = isset($edad) && !empty($edad) && preg_match($JwtAuth->filtroNumericoSimple(),$edad);
        $OKSexo = isset($sexo) && !empty($sexo) && preg_match($JwtAuth->filtroAlfaNumerico(),$sexo);
        $OKEstCivil = isset($estado_civil) && !empty($estado_civil) && preg_match($JwtAuth->filtroAlfaNumerico(),$estado_civil);
        //$OKRegTrab = isset($regimen_trabajador) && !empty($regimen_trabajador);
        $OKDomiCalle = isset($domicilio_CalleNumero) && !empty($domicilio_CalleNumero) && preg_match($JwtAuth->filtroAlfaNumerico(),$domicilio_CalleNumero);
        $OKDomiCP = isset($domicilio_cod_postal) && !empty($domicilio_cod_postal) && preg_match($JwtAuth->filtroNumericoSimple(),$domicilio_cod_postal);
        $OKDomiCol = isset($domicilio_colonia_vinculada) && !empty($domicilio_colonia_vinculada) && preg_match($JwtAuth->filtroAlfaNumerico(),$domicilio_colonia_vinculada);
        $OKDomiMuni = isset($domicilio_municipio) && !empty($domicilio_municipio) && preg_match($JwtAuth->filtroAlfaNumerico(),$domicilio_municipio);
        $OKDomiestado = isset($domicilio_estado) && !empty($domicilio_estado) && preg_match($JwtAuth->filtroAlfaNumerico(),$domicilio_estado);
				$OKNacimDecha = isset($origen_nacimiento_fecha) && !empty($origen_nacimiento_fecha) && preg_match($JwtAuth->filtroFecha(),$origen_nacimiento_fecha);
        $OKNacimLugar = isset($origen_nacimiento_lugar) && !empty($origen_nacimiento_lugar) && preg_match($JwtAuth->filtroAlfaNumerico(),$origen_nacimiento_lugar);
        $OKNacionalidad = isset($origen_nacionalidad) && !empty($origen_nacionalidad);
        $OKContTelTipo = isset($contacto_telefono_tipo) && !empty($contacto_telefono_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$contacto_telefono_tipo);
        $OKContTelNumero = isset($contacto_telefono_numero) && !empty($contacto_telefono_numero) && preg_match($JwtAuth->filtroAlfaNumerico(),$contacto_telefono_numero);
        $OKContEmail = isset($contacto_email) && !empty($contacto_email) && preg_match($JwtAuth->filtroAlfaNumerico(),$contacto_email);
        $OKDocsCurp = isset($documentacion_curp) && !empty($documentacion_curp) && preg_match($JwtAuth->filtroAlfaNumerico(),$documentacion_curp);
        $OKDocsRfc = isset($documentacion_rfc) && !empty($documentacion_rfc) && preg_match($JwtAuth->filtroAlfaNumerico(),$documentacion_rfc);

        $OKDocsPasaporte = isset($documentacion_pasaporte) && is_array($documentacion_pasaporte) && count($documentacion_pasaporte) > 0;
        $OKDocsVisa = isset($documentacion_visa) && is_array($documentacion_visa) && count($documentacion_visa) > 0;
        $OKDocsNSS = isset($documentacion_numero_de_seguridad_social) && !empty($documentacion_numero_de_seguridad_social) && preg_match($JwtAuth->filtroAlfaNumerico(),$documentacion_numero_de_seguridad_social);
        $OKDocsLicen = isset($documentacion_licencia) && is_array($documentacion_licencia) && count($documentacion_licencia) > 0;

        $OKCBancariaBancoTkn = isset($cbancaria_banco_token) && !empty($cbancaria_banco_token);
        $OKCBancariaCuenta = isset($cbancaria_cuenta) && !empty($cbancaria_cuenta) && preg_match($JwtAuth->filtroNumericoSimple(),$cbancaria_cuenta);
        $OKCBancariaClabeInter = isset($cbancaria_clabe_inter) && !empty($cbancaria_clabe_inter) && preg_match($JwtAuth->filtroNumericoSimple(),$cbancaria_clabe_inter);

        $OKEmpCenTrabaj = isset($centro_de_trabajo) && !empty($centro_de_trabajo);
        $OKEmpDepartamento = isset($departamento) && !empty($departamento) && preg_match($JwtAuth->filtroAlfaNumerico(),$departamento);
        $OKEmpPuesto = isset($puesto) && !empty($puesto) && preg_match($JwtAuth->filtroAlfaNumerico(),$puesto);
        $OKEmpSalarioTipo = isset($salario_tipo) && !empty($salario_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$salario_tipo);
        $OKEmpContratacionTipo = isset($contratacion_tipo) && !empty($contratacion_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$contratacion_tipo);
				$OKEmpContratacionFecha = isset($contratacion_fecha) && !empty($contratacion_fecha) && preg_match($JwtAuth->filtroFecha(),$contratacion_fecha);
				$OKEmpAltaEnEmpresa = isset($alta_en_empresa) && !empty($alta_en_empresa) && preg_match($JwtAuth->filtroFecha(),$alta_en_empresa);

        $OKPeriodicidad = isset($nomina_periodicidad) && !empty($nomina_periodicidad) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_periodicidad);
        $OKMoneda = isset($nomina_moneda) && !empty($nomina_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_moneda);
        $OKTipoJornada = isset($tipo_jornada) && !empty($tipo_jornada) && preg_match($JwtAuth->filtroAlfaNumerico(),$tipo_jornada);
        $OKTurno = isset($turno) && !empty($turno) && preg_match($JwtAuth->filtroAlfaNumerico(),$turno);
        $OKEmpSalarioDiario = isset($nomina_salario_diario) && !empty($nomina_salario_diario) && preg_match($JwtAuth->filtroCostoPrecio(),$nomina_salario_diario);
        $OKEmpSalarioIntegrado = isset($nomina_salario_integrado) && !empty($nomina_salario_integrado) && preg_match($JwtAuth->filtroCostoPrecio(),$nomina_salario_integrado);

        if ($OKPaterno && $OKMaterno && $OKNombres && $OKEdad && $OKSexo && $OKEstCivil && $OKDomiCalle && $OKDomiCP && $OKDomiCol && $OKDomiMuni && $OKDomiestado && $OKNacimDecha && $OKNacimLugar && $OKNacionalidad && 
          $OKContTelTipo && $OKContTelNumero && $OKContEmail && $OKDocsCurp && $OKDocsRfc && $OKDocsNSS && $OKEmpCenTrabaj && $OKEmpDepartamento && $OKEmpPuesto && $OKEmpSalarioTipo && $OKEmpContratacionTipo && 
          $OKEmpContratacionFecha && $OKEmpAltaEnEmpresa && $OKPeriodicidad && $OKMoneda && $OKTipoJornada && $OKTurno && $OKEmpSalarioDiario && $OKEmpSalarioIntegrado) {
          $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,emp.habilita_centros_de_trabajo,users.id AS userr,users.jerarquia_main FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
            WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

          foreach ($queryEmp as $vEmp) {
            $folioSistema = DB::select("SELECT trab.folio_pers+1 AS folio,post_folio_pers FROM vhum_empleados_catalogo AS trab JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
              WHERE trab.empleado_empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ? ORDER BY trab.folio_pers DESC LIMIT 1",
              [$usuario->empresa_token,$usuario->user_token]);
            //return response()->json(['message' => $folioSistema[0]->folio,'code' => 200,'status' => 'error']);
            if (count($folioSistema) == 1) {
              if ($folioSistema[0]->folio == 1000000000) {
                  $post_folio_db = DB::select("SELECT post_folio_pers FROM vhum_empleados_catalogo WHERE id = (SELECT Max(trab.id) FROM vhum_empleados_catalogo AS trab JOIN main_empresas AS emp 
                    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE trab.empleado_empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",[$usuario->empresa_token,$usuario->user_token]);
                  
                  $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio_pers);
                  $folio_nuevo = 1;
              } else {
                  $post_folio = NULL;
                  $folio_nuevo = $folioSistema[0]->folio;
              }
            } else {
              $post_folio = NULL;
              $folio_nuevo = 1;
            }
            $folio_trab = 'TRAB-'.$JwtAuth->generarFolio($folio_nuevo).(!is_null($post_folio) ? '-'.$post_folio : '');
  
            $nacionalidad_pais = DB::table("teci_pais")->where("token_pais",$origen_nacionalidad)->value("id");
            $id_trabajo_centro = $vEmp->habilita_centros_de_trabajo ? DB::table("vhum_centros_de_trabajo_catalogo")->where('centrotrab_uuid',$centro_de_trabajo)->value("id") : NULL;
  
            $tokenPersona = $JwtAuth->encriptarToken($apePaterno,$apeMaterno,$nombres,$edad,$origen_nacimiento_fecha,$origen_nacimiento_lugar,$nacionalidad_pais,$sexo,$estado_civil);
            $trabPersonasInsert = DB::table("sos_personas")
            ->insert(array(
              "token_personas" => $tokenPersona,
              "paterno" => $JwtAuth->encriptar($apePaterno),
              "materno" => $JwtAuth->encriptar($apeMaterno),
              "nombre" => $JwtAuth->encriptar($nombres),
              "fecha_nacimiento" => $JwtAuth->convierteFechaEpoc($origen_nacimiento_fecha),
              "lugar_nacimiento" => $JwtAuth->encriptar($origen_nacimiento_lugar),
              "sexo" => $sexo,
              "estado_civil" => $estado_civil,
              "nacionalidad" => $nacionalidad_pais,
              "edad" => $edad,
              "rfc" => $OKDocsRfc ? $JwtAuth->encriptar($documentacion_rfc) : NULL,
              "curp" => $OKDocsCurp ? $JwtAuth->encriptar($documentacion_curp) : NULL,
              "numero_de_seguridad_social" => $OKDocsNSS ? $documentacion_numero_de_seguridad_social : NULL
            ));
            $persona_empleado = DB::table("sos_personas")->where("token_personas",$tokenPersona)->value("id");
  
            $tokenEmpleado = $JwtAuth->encriptarToken($persona_empleado,$folio_nuevo,$post_folio);
            
            $idBanco = $OKCBancariaBancoTkn ? DB::table("teci_bancos")->where("token_bancos",$cbancaria_banco_token)->value("id") : NULL;
            $cuentaEncode = $OKCBancariaCuenta ? $JwtAuth->encryptBankAccount($cbancaria_cuenta) : NULL;
            $clabeInterEncode = $OKCBancariaClabeInter ? $JwtAuth->encryptBankAccount($cbancaria_clabe_inter) : NULL;
  
            $creaTrab = new PersonalModelo();
            $creaTrab->empleado_token = $tokenEmpleado;
            $creaTrab->fecha_alta_pers = time();
            $creaTrab->folio_pers = $folio_nuevo;
            $creaTrab->post_folio_pers = $post_folio;
            $creaTrab->fecha_alta_en_empresa = $JwtAuth->convierteFechaEpoc($alta_en_empresa);
            $creaTrab->centro_de_trabajo = $id_trabajo_centro;
            $creaTrab->departamento = $JwtAuth->encriptar($departamento);
            $creaTrab->puesto = $JwtAuth->encriptar($puesto);

            $creaTrab->telefono_tipo = $contacto_telefono_tipo;
            $creaTrab->telefono_numero = $JwtAuth->encriptar($contacto_telefono_numero);
            $creaTrab->correo = $JwtAuth->encriptar($contacto_email);

            $creaTrab->trabcuentabanc_banco = $idBanco;
            $creaTrab->trabcuentabanc_cuenta = $cuentaEncode;
            $creaTrab->trabcuentabanc_clabe = $clabeInterEncode;
            $creaTrab->regimen_fiscal_trabajador = 3;
            $creaTrab->salario_tipo = $salario_tipo;
            $creaTrab->nomina_periodicidad_pago = $nomina_periodicidad;
            $creaTrab->nomina_moneda = $nomina_moneda;
            $creaTrab->nomina_jornada = $tipo_jornada;
            $creaTrab->nomina_turno = $turno;
            $creaTrab->contratacion_fecha = $JwtAuth->convierteFechaEpoc($contratacion_fecha);
            $creaTrab->contratacion_tipo = $contratacion_tipo;
            $creaTrab->empleado_name = $persona_empleado;
            $creaTrab->empleado_empresa = $vEmp->id;
            $creaTrab->nivel_empleado = "N1";
            $creaTrab->status = TRUE;
            $creaTrab->fecha_delete = NULL;
            $savednewTrab = $creaTrab->save();
            //$trabajador_cat_id = DB::table("vhum_empleados_catalogo")->where("empleado_token",$tokenEmpleado)->value("id");
            $trabajador_cat_id = $creaTrab->id;
  
            DB::table("vhum_empleados_registro_salarial")
            ->insert(array(
              "trabajador" => $trabajador_cat_id,
              "salario_diario" => $nomina_salario_diario,
              "salario_diario_integrado" => $nomina_salario_integrado,
              "entra_en_vigor" => time(),
            ));
  
            if ($OKDocsPasaporte) {
              foreach ($documentacion_pasaporte as $e_pst_v => $e_pst_n) {
                $new_pst_numero = $e_pst_n["pasaporte_numero"];
                $new_pst_expide = $e_pst_n["pasaporte_expide"];
                $new_pst_vigencia = $e_pst_n["pasaporte_vigencia"];
                $tokenPassporte = $JwtAuth->encriptarToken($new_pst_numero,$new_pst_expide,$new_pst_vigencia);
                DB::table("vhum_empleados_pasaporte")
                ->insert(array(
                  "pasaporte_token" => $tokenPassporte,
                  "pasaporte_empleado" => $trabajador_cat_id,
                  "pasaporte_numero" => $new_pst_numero,
                  "pasaporte_expide" => $JwtAuth->encriptar($new_pst_expide),
                  "pasaporte_vigencia" => $JwtAuth->convierteFechaEpoc($new_pst_vigencia)
                ));
              }
            }
  
            if ($OKDocsVisa) {
              foreach ($documentacion_visa as $e_visa_v => $e_visa_n) {
                $new_vis_numero = $e_visa_n["visa_numero"];
                $new_vis_expide = $e_visa_n["visa_expide"];
                $new_vis_vigencia = $e_visa_n["visa_vigencia"];
                $tokenVisa = $JwtAuth->encriptarToken($new_vis_numero,$new_vis_expide,$new_vis_vigencia);
                DB::table("vhum_empleados_visa")
                ->insert(array(
                  "visa_token" => $tokenVisa,
                  "visa_empleado" => $trabajador_cat_id,
                  "visa_numero" => $new_vis_numero,
                  "visa_expide" => $JwtAuth->encriptar($new_vis_expide),
                  "visa_vigencia" => $JwtAuth->convierteFechaEpoc($new_vis_vigencia)
                ));
              }
            }
  
            if ($OKDocsLicen) {
              foreach ($documentacion_licencia as $e_licen_v => $e_licen_n) {
                $new_licen_nivel = $e_licen_n["licencia_nivel"];
                $new_licen_clase = $e_licen_n["licencia_clase"];
                $new_licen_numero = $e_licen_n["licencia_numero"];
                $new_licen_expide = $e_licen_n["licencia_expide"];
                $new_licen_fecha_expedicion = $e_licen_n["licencia_fecha_expedicion"];
                $new_licen_vigencia = $e_licen_n["licencia_vigencia"];
                $new_licen_permanente = $e_licen_n["licencia_permanente"];
  
                $tokenLicencia= $JwtAuth->encriptarToken($new_licen_nivel.$new_licen_clase.$new_licen_numero.$new_licen_expide.$new_licen_fecha_expedicion.$new_licen_vigencia.$new_licen_permanente);
                DB::table("vhum_empleados_licencia")
                ->insert(array(
                  "licencia_token" => $tokenLicencia,
                  "licencia_empleado" => $trabajador_cat_id,
                  "licencia_nivel" => $new_licen_nivel,
                  "licencia_clase" => $new_licen_clase,
                  "licencia_numero" => $JwtAuth->encriptar($new_licen_numero),
                  "licencia_expide" => $new_licen_expide,
                  "licencia_fecha_expedicion" => $JwtAuth->convierteFechaEpoc($new_licen_fecha_expedicion),
                  "licencia_vigencia" => $new_licen_vigencia,
                  "licencia_permanente" => $new_licen_permanente ? TRUE : FALSE
                ));
              }
            }
  
            $tokenCDir = $JwtAuth->encriptarToken($origen_nacimiento_fecha,$domicilio_estado,$domicilio_municipio,$domicilio_cod_postal,$domicilio_colonia_vinculada);
            $trabInsertDomi = DB::table("teci_direcciones")
            ->insert(array(
              "token_direccion" => $tokenCDir,
              "clase" => $JwtAuth->encriptar("matriz"),
              "pais" => $nacionalidad_pais,
              "pais_code" => "MEX",
              "calle" => $JwtAuth->encriptar($domicilio_CalleNumero),
              "estado_edit" => $JwtAuth->encriptar($domicilio_estado),
              "municipio_edit" => $JwtAuth->encriptar($domicilio_municipio),
              "c_postal_edit" => $domicilio_cod_postal,
              "colonia_edit" => $JwtAuth->encriptar($domicilio_colonia_vinculada),
              "adicional" => "api",
              "trabajador" => $trabajador_cat_id,
              "status" => TRUE,
              "administrador" => $vEmp->id,
            ));
            
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Trabajador registrado satisfactoriamente con el folio $folio_trab"
            );
          }
        } else {
          $mensaje_error = "";
          if (!$OKPaterno) $mensaje_error = "Error al registrar apellido paterno, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKMaterno) $mensaje_error = "Error al registrar apellido materno, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKNombres) $mensaje_error = "Error al registrar nombres, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKEdad) $mensaje_error = "Error al registrar edad, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKSexo) $mensaje_error = "Error al registrar sexo, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKEstCivil) $mensaje_error = "Error al registrar estado civil, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDomiCalle) $mensaje_error = "Error al registrar calle y número, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDomiCP) $mensaje_error = "Error al registrar código postal, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDomiCol) $mensaje_error = "Error al registrar colonia, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDomiMuni) $mensaje_error = "Error al registrar municipio, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDomiestado) $mensaje_error = "Error al registrar estado, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKNacimDecha) $mensaje_error = "Error al registrar fecha de nacimiento, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKNacimLugar) $mensaje_error = "Error al registrar lugar de nacimiento, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKNacionalidad) $mensaje_error = "Error al registrar nacionalidad, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKContTelTipo) $mensaje_error = "Error al registrar tipo de teléfono, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKContTelNumero) $mensaje_error = "Error al registrar teléfono, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKContEmail) $mensaje_error = "Error al registrar email, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDocsCurp) $mensaje_error = "Error al registrar curp, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDocsRfc) $mensaje_error = "Error al registrar rfc, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDocsNSS) $mensaje_error = "Error al registrar número de seguridad social, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKEmpCenTrabaj) $mensaje_error = "Error al seleccionar centro de trabajo, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpDepartamento) $mensaje_error = "Error al seleccionar departamento de trabajo, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpPuesto) $mensaje_error = "Error al seleccionar puesto de trabajo, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpSalarioTipo) $mensaje_error = "Error al seleccionar tipo de salario, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpContratacionTipo) $mensaje_error = "Error al seleccionar tipo de contratación, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpContratacionFecha) $mensaje_error = "Error al seleccionar fecha de contratación, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpAltaEnEmpresa) $mensaje_error = "Error al seleccionar fecha en la que el trabajador ingreso a laborar, intentelo nuevamente o comuniquese a soporte";
          if (!$OKPeriodicidad) $mensaje_error = "Error al seleccionar periodicidad de pagos, intentelo nuevamente o comuniquese a soporte";
          if (!$OKMoneda) $mensaje_error = "Error al seleccionar la moneda relacionada a la nómina del trabajador, intentelo nuevamente o comuniquese a soporte";
          if (!$OKTipoJornada) $mensaje_error = "Error al seleccionar tipo de jornada del trabajador, intentelo nuevamente o comuniquese a soporte";
          if (!$OKTurno) $mensaje_error = "Error al seleccionar turno del trabajador, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpSalarioDiario) $mensaje_error = "Error al seleccionar salario diario del trabajador, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpSalarioIntegrado) $mensaje_error = "Error al seleccionar salario diario integrado del trabajador, intentelo nuevamente o comuniquese a soporte";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  //empleados SOS
  public function catalogo_general_trabajadores(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpleados = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.folio_pers", "!=", 0)
        ->where("vhum_empleados_catalogo.status",TRUE)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();
        //echo count($listEmpleados);
        foreach ($listEmpleados as $vEmploy) {
          $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');
          $token_empleado_dispositivo_firebase = $vEmploy->token_dispositivo_firebase;
          $nombre_completo = ucwords($JwtAuth->desencriptar($vEmploy->paterno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->materno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->nombre));

          $trabNominas = DB::table("vhum_nominas_recibos AS nom")
          ->join("vhum_empleados_catalogo AS trab", "nom.trabajador", "trab.id")
          ->where('trab.empleado_token',$vEmploy->empleado_token)
          ->count();

          $rowEmpleado = array(
            "token_empleado_inside" => $vEmploy->empleado_token,
            "token_empleado_vhum" => $vEmploy->empleado_token,
            "token_empleado_dispositivo_firebase" => $token_empleado_dispositivo_firebase,
            "folio_empleado" => $folio_empleado,
            "alta_en_empresa" => !is_null($vEmploy->fecha_alta_en_empresa) && $vEmploy->fecha_alta_en_empresa != '' ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_alta_en_empresa) : '',
            "token_personas" => $vEmploy->token_personas,
            "paterno" => ucwords($JwtAuth->desencriptar($vEmploy->paterno)),
            "materno" => ucwords($JwtAuth->desencriptar($vEmploy->materno)),
            "nombres" => ucwords($JwtAuth->desencriptar($vEmploy->nombre)),
            "nombre_completo" => ucwords($nombre_completo),
            "nacionalidad" => $vEmploy->nacionalidad,
            "rfc" => !is_null($vEmploy->rfc) && $vEmploy->rfc != '' ? $JwtAuth->desencriptar($vEmploy->rfc) : '',
            "tax_id" => !is_null($vEmploy->tax_id) && $vEmploy->tax_id != '' ? $vEmploy->tax_id : '',
            "curp" => !is_null($vEmploy->curp) && $vEmploy->curp != '' ? $JwtAuth->desencriptar($vEmploy->curp) : '',
            "baja_dado" => $vEmploy->causa_baja ? true : false,
            "baja_motivo" => $vEmploy->causa_baja ? $JwtAuth->desencriptar($vEmploy->motivo_causa_baja) : '',
            "baja_fecha" => $vEmploy->causa_baja ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_causa_baja) : '',
            "puede_eliminar" => $trabNominas == 0 ? true : false,
            "selected" => false,
            "ver_trabajador_info" => false,
            "trabajador_detail" => [],
          );
          $arrayEmpleados[] = $rowEmpleado;
        }

        $dataMensaje = array(
          "empleados" => $arrayEmpleados,
          "code" => 200,
          "status" => "success"
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogo_trabajadores_por_registro_patronal(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpleados = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'registro_patronal' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $registro_patronal_imss = $parametrosArray['registro_patronal'];

        $OKRPImss = isset($registro_patronal_imss) && !empty($registro_patronal_imss) && preg_match($JwtAuth->filtroAlfaNumerico(),$registro_patronal_imss);
        $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("vhum_centros_de_trabajo_catalogo AS ctrab", "vhum_empleados_catalogo.centro_de_trabajo", "ctrab.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.folio_pers", "!=", 0)
        ->where("vhum_empleados_catalogo.causa_baja",FALSE)
        ->where("vhum_empleados_catalogo.status",TRUE)
        ->where('ctrab.centrotrab_clave_registro_patronal_imss',$registro_patronal_imss)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();
        if ($OKRPImss && count($listEmpleados) > 0) {
          foreach ($listEmpleados as $vEmploy) {
            $cTrabSueldoActual = $JwtAuth->trabSueldosUltimo($vEmploy->empleado_token);
            $nomina_moneda_decimales = $JwtAuth->getMonedaAPI($vEmploy->nomina_moneda);
            $rowEmpleado = array(
              "token_empleado_vhum" => $vEmploy->empleado_token,
              "folio_empleado" => 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : ''),
              "nombre_completo" => ucwords($JwtAuth->desencriptar($vEmploy->paterno))." ".ucwords($JwtAuth->desencriptar($vEmploy->materno))." ".ucwords($JwtAuth->desencriptar($vEmploy->nombre)),
              "alta_en_empresa" => !is_null($vEmploy->fecha_alta_en_empresa) && $vEmploy->fecha_alta_en_empresa != '' ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_alta_en_empresa) : '',
              "rfc" => !is_null($vEmploy->rfc) && $vEmploy->rfc != '' ? $JwtAuth->desencriptar($vEmploy->rfc) : '',
              "curp" => !is_null($vEmploy->curp) && $vEmploy->curp != '' ? $JwtAuth->desencriptar($vEmploy->curp) : '',
              
              "nomina_periodicidad" => !is_null($vEmploy->nomina_periodicidad_pago) && $vEmploy->nomina_periodicidad_pago != '' ? $vEmploy->nomina_periodicidad_pago : '',
              "nomina_moneda" => !is_null($vEmploy->nomina_moneda) && $vEmploy->nomina_moneda != '' ? $vEmploy->nomina_moneda : '',
              "nomina_moneda_decimales" => $nomina_moneda_decimales,
              "nomina_salario_diario" => $cTrabSueldoActual ? number_format($cTrabSueldoActual->salario_diario,$nomina_moneda_decimales,'.','') : '0.00',
              "nomina_salario_integrado" => $cTrabSueldoActual ? number_format($cTrabSueldoActual->salario_diario_integrado,$nomina_moneda_decimales,'.','') : '0.00',
            );

            $valid_emp_nomina_periodicidad = !is_null($vEmploy->nomina_periodicidad_pago) && $vEmploy->nomina_periodicidad_pago != '';
            $valid_emp_nomina_jornada = !is_null($vEmploy->nomina_jornada) && $vEmploy->nomina_jornada != '';
            $valid_emp_nomina_moneda = !is_null($vEmploy->nomina_moneda) && $vEmploy->nomina_moneda != '';
            if ($valid_emp_nomina_periodicidad && $valid_emp_nomina_jornada && $valid_emp_nomina_moneda && $cTrabSueldoActual) {
              $arrayEmpleados[] = $rowEmpleado;
            }
          }
  
          $dataMensaje = array(
            "list_empleados" => count($listEmpleados),
            "empleados" => $arrayEmpleados,
            "code" => 200,
            "status" => "success"
          );
        } else {
          $mensaje_error = "";
          if (!$OKRPImss) $mensaje_error = "Error al seleccionar clave de registro patronal del IMSS, intentelo nuevamente o comuniquese a soporte"; 
          if (count($listEmpleados) == 0) $mensaje_error = "Este centro de trabajo no cuenta con trabajadores registrados, intentelo nuevamente o comuniquese a soporte"; 
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function trabajador_detalle(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpleados = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_empleado_vhum' => 'required'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_empleado_vhum = $parametrosArray['token_empleado_vhum'];

        $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.empleado_token",$token_empleado_vhum)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        //echo count($listEmpleados);
        foreach ($listEmpleados as $vEmploy) {
          //da_te_default_timezone_set('UTC');
          $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');
          $nombre_completo = ucwords($JwtAuth->desencriptar($vEmploy->paterno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->materno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->nombre));

          //direcciones
          $arrayDirecciones = array();
          $listLocations = DB::table("teci_direcciones AS dom")
          ->join("vhum_empleados_catalogo AS pers", "dom.trabajador", "pers.id")
          ->where("dom.status",TRUE)
          ->where("pers.empleado_token",$vEmploy->empleado_token)
          ->get();

          foreach ($listLocations as $vDom) {
            $domRow = array(
              "token_direccion" => $vDom->token_direccion,
              "clase" => $JwtAuth->desencriptar($vDom->clase),
              "pais" => !is_null($vDom->pais) && $vDom->pais != '' ? DB::table("teci_pais")->where("id",$vDom->pais)->value("pais") : '',
              "pais_code" => $vDom->pais_code,
              "calle" => $vDom->calle ? $JwtAuth->desencriptar($vDom->calle) : '',
              "estado_edit" => $JwtAuth->desencriptar($vDom->estado_edit),
              "municipio_edit" => $JwtAuth->desencriptar($vDom->municipio_edit),
              "c_postal_edit" => $vDom->c_postal_edit,
              "colonia_edit" => $JwtAuth->desencriptar($vDom->colonia_edit),
              "adicional" => $vDom->adicional,
              "validate" => false,
            );
            $arrayDirecciones[] = $domRow;
          }

					$queryRegimenFiscal = DB::table("sos_regimen_fiscal")
					->where("id",$vEmploy->regimen_fiscal_trabajador)
					->select('clave','descripcion')
					->first();

          $regFiscalTrab = !is_null($vEmploy->regimen_fiscal_trabajador) && $queryRegimenFiscal ? $queryRegimenFiscal->clave."-".$queryRegimenFiscal->descripcion : '';

          //pasaporte
          $arrayPasaporte = array();
          $trabPasaporteQuery = DB::table("vhum_empleados_pasaporte AS pass")
          ->join("vhum_empleados_catalogo AS trab", "pass.pasaporte_empleado", "trab.id")
          ->where("trab.empleado_token",$vEmploy->empleado_token)
          ->get();
          foreach ($trabPasaporteQuery as $vPass) {
            $row_passa = array(
              "pasaporte_token" => $vPass->pasaporte_token,
              "pasaporte_numero" => $vPass->pasaporte_numero,
              "pasaporte_expide" => $JwtAuth->desencriptar($vPass->pasaporte_expide),
              "pasaporte_vigencia" => $JwtAuth->convierteEpocFechaHtml('UTC',$vPass->pasaporte_vigencia),
            );
            $arrayPasaporte[] = $row_passa;
          }

          //visa
          $arrayVisa = array();
          $trabVisaQuery = DB::table("vhum_empleados_visa AS visa")
          ->join("vhum_empleados_catalogo AS trab", "visa.visa_empleado", "trab.id")
          ->where("trab.empleado_token",$vEmploy->empleado_token)
          ->get();
          foreach ($trabVisaQuery as $vIsa) {
            $row_vis_a = array(
              "visa_token" => $vIsa->visa_token,
              "visa_numero" => $vIsa->visa_numero,
              "visa_expide" => $JwtAuth->desencriptar($vIsa->visa_expide),
              "visa_vigencia" => $JwtAuth->convierteEpocFechaHtml('UTC',$vIsa->visa_vigencia),
            );
            $arrayVisa[] = $row_vis_a;
          }

          //licencia
          $arrayLicenciaConducir = array();
          $trabLicenciaQuery = DB::table("vhum_empleados_licencia AS licen")
          ->join("vhum_empleados_catalogo AS trab", "licen.licencia_empleado", "trab.id")
          ->where("trab.empleado_token",$vEmploy->empleado_token)
          ->get();
          foreach ($trabLicenciaQuery as $vLicen) {
            $licencia_expide_show = "";
            switch ($vLicen->licencia_expide) {
              case 'aguascalientes':
                $licencia_expide_show = "Aguascalientes";
                break;
              case 'bajacalifornia':
                $licencia_expide_show = "Baja California";
                break;
              case 'bajacaliforniasur':
                $licencia_expide_show = "Baja California Sur";
                break;
              case 'campeche':
                $licencia_expide_show = "Campeche";
                break;
              case 'coahuila':
                $licencia_expide_show = "Coahuila";
                break;
              case 'colima':
                $licencia_expide_show = "Colima";
                break;
              case 'chiapas':
                $licencia_expide_show = "Chiapas";
                break;
              case 'chihuahua':
                $licencia_expide_show = "Chihuahua";
                break;
              case 'ciudaddemexico':
                $licencia_expide_show = "Ciudad de México";
                break;
              case 'durango':
                $licencia_expide_show = "Durango";
                break;
              case 'guanajuato':
                $licencia_expide_show = "Guanajuato";
                break;
              case 'guerrero':
                $licencia_expide_show = "Guerrero";
                break;
              case 'hidalgo':
                $licencia_expide_show = "Hidalgo";
                break;
              case 'jalisco':
                $licencia_expide_show = "Jalisco";
                break;
              case 'mexico':
                $licencia_expide_show = "México";
                break;
              case 'michoacan':
                $licencia_expide_show = "Michoacán";
                break;
              case 'morelos':
                $licencia_expide_show = "Morelos";
                break;
              case 'nayarit':
                $licencia_expide_show = "Nayarit";
                break;
              case 'nuevoleon':
                $licencia_expide_show = "Nuevo León";
                break;
              case 'oaxaca':
                $licencia_expide_show = "Oaxaca";
                break;
              case 'puebla':
                $licencia_expide_show = "Puebla";
                break;
              case 'queretaro':
                $licencia_expide_show = "Querétaro";
                break;
              case 'quintanaroo':
                $licencia_expide_show = "Quintana Roo";
                break;
              case 'sanluispotosi':
                $licencia_expide_show = "San Luis Potosí";
                break;
              case 'sinaloa':
                $licencia_expide_show = "Sinaloa";
                break;
              case 'sonora':
                $licencia_expide_show = "Sonora";
                break;
              case 'tabasco':
                $licencia_expide_show = "Tabasco";
                break;
              case 'tamaulipas':
                $licencia_expide_show = "Tamaulipas";
                break;
              case 'tlaxcala':
                $licencia_expide_show = "Tlaxcala";
                break;
              case 'veracruz':
                $licencia_expide_show = "Veracruz";
                break;
              case 'yucatan':
                $licencia_expide_show = "Yucatán";
                break;
              case 'zacatecas':
                $licencia_expide_show = "Zacatecas";
                break;
              default:
                # code...
                break;
            }

            $row_licen = array(
              "licencia_token" => $vLicen->licencia_token,
              "licencia_nivel" => $vLicen->licencia_nivel,
              "licencia_clase" => $vLicen->licencia_clase,
              "licencia_numero" => $JwtAuth->desencriptar($vLicen->licencia_numero),
              "licencia_expide" => $vLicen->licencia_expide,
              "licencia_expide_show" => $licencia_expide_show,
              "licencia_fecha_expedicion" => $JwtAuth->convierteEpocFechaHtml('UTC',$vLicen->licencia_fecha_expedicion),
              "licencia_vigencia" => $vLicen->licencia_vigencia,
              "licencia_permanente" => $vLicen->licencia_permanente ? true : false
            );
            $arrayLicenciaConducir[] = $row_licen;
          }

          $cTrabQuery = DB::table("vhum_centros_de_trabajo_catalogo AS c_trab")
          ->join("vhum_empleados_catalogo AS trab", "c_trab.id", "trab.centro_de_trabajo")
          ->where("trab.empleado_token",$vEmploy->empleado_token)
          ->select('c_trab.centrotrab_uuid','c_trab.centrotrab_folio','c_trab.centrotrab_sub_folio','c_trab.centrotrab_clave_registro_patronal_imss')
					->first();
          $centro_trab_uuid = $cTrabQuery ? $cTrabQuery->centrotrab_uuid : '';
          $centro_trab_folio = $cTrabQuery ? 'CTRA-'.$JwtAuth->generarFolio($cTrabQuery->centrotrab_folio).(!is_null($cTrabQuery->centrotrab_sub_folio) ? '-'.$cTrabQuery->centrotrab_sub_folio : '') : '';
          $centro_trab_registro_patronal_imss = $cTrabQuery ? $cTrabQuery->centrotrab_clave_registro_patronal_imss : '';

          $cTrabSueldoActual = $JwtAuth->trabSueldosUltimo($vEmploy->empleado_token);
          //echo $cTrabSueldoActual;
          
          $contrato_tipo = '';
          if (!is_null($vEmploy->contratacion_tipo) && $vEmploy->contratacion_tipo != '') {
            switch ($vEmploy->contratacion_tipo) {
              case 'contrattimeindet':
                $contrato_tipo = 'Por tiempo indeterminado';
                break;
              case 'contrattimedet':
                $contrato_tipo = 'Por tiempo determinado';
                break;
              case 'contratobratimedet':
                $contrato_tipo = 'Por obra o tiempo determinado';
                break;
              case 'contratpertest':
                $contrato_tipo = 'Periodo de prueba';
                break;
              case 'contratcapacinicial':
                $contrato_tipo = 'Capacitación inicial';
                break;
              case 'contratoutsourcing':
                $contrato_tipo = 'Outsourcing (Servicios especializados)';
                break;
              case 'contratjornreductimeparc':
                $contrato_tipo = 'Jornada reducida o tiempo parcial';
                break;
              case 'contrattemporada':
                $contrato_tipo = 'Temporada';
                break;
              case 'contratteletrabajo':
                $contrato_tipo = 'Teletrabajo (home office)';
                break;
              default:
                $contrato_tipo = '';
                break;
            }
          }
          
          $getBanco = DB::table("teci_bancos AS bank")
          ->join("vhum_empleados_catalogo AS trab", "bank.id", "trab.trabcuentabanc_banco")
          ->where("trab.empleado_token",$vEmploy->empleado_token)
          ->select('bank.token_bancos','bank.clave','bank.nombre_comercial')
          ->first();

          $cuenta_descifrada = '';
          $cuenta_descifrada_last_digitos = '';
          if (!is_null($vEmploy->trabcuentabanc_cuenta) && $vEmploy->trabcuentabanc_cuenta != '') {
            $cuenta_descifrada = $JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_cuenta);
            $cuenta_descifrada_substr = substr($JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_cuenta), -4);
            $cuenta_descifrada_last_digitos = "**** **** **** $cuenta_descifrada_substr";
          }
          
          $clabe_descifrada = '';
          $clabe_descifrada_last_digitos = '';
          if (!is_null($vEmploy->trabcuentabanc_clabe) && $vEmploy->trabcuentabanc_clabe != '') {
            $clabe_descifrada = $JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_clabe);
            $clabe_descifrada_substr = substr($JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_clabe), -4);
            $clabe_descifrada_last_digitos = "**** **** **** $clabe_descifrada_substr";
          }

          $nomina_moneda_decimales = !is_null($vEmploy->nomina_moneda) && $vEmploy->nomina_moneda != '' ? $JwtAuth->getMonedaAPI($vEmploy->nomina_moneda) : 0;

          $rowPersonales = array(
            "token_empleado_inside" => $vEmploy->empleado_token,
            "token_empleado_vhum" => $vEmploy->empleado_token,
            "folio_empleado" => $folio_empleado,
            //Datos personales
            "token_personas" => $vEmploy->token_personas,
            "nombre_completo" => ucwords($nombre_completo),
            "paterno" => ucwords($JwtAuth->desencriptar($vEmploy->paterno)),
            "materno" => ucwords($JwtAuth->desencriptar($vEmploy->materno)),
            "nombres" => ucwords($JwtAuth->desencriptar($vEmploy->nombre)),
            "edad" => !is_null($vEmploy->edad) && $vEmploy->edad != '' ? $vEmploy->edad : '',
            "sexo" => !is_null($vEmploy->sexo) && $vEmploy->sexo != '' ? $vEmploy->sexo : '',
            "estado_civil" => !is_null($vEmploy->estado_civil) && $vEmploy->estado_civil != '' ? $vEmploy->estado_civil : '',
            "regimen_fiscal_trabajador" => $regFiscalTrab,
            "fecha_nacimiento" => !is_null($vEmploy->fecha_nacimiento) && $vEmploy->fecha_nacimiento != '' ? $JwtAuth->convierteEpocFechaHtml('UTC',$vEmploy->fecha_nacimiento) : '',
            "lugar_nacimiento" => !is_null($vEmploy->lugar_nacimiento) && $vEmploy->lugar_nacimiento != '' ? $JwtAuth->desencriptar($vEmploy->lugar_nacimiento) : '',
            "nacionalidad_token" => !is_null($vEmploy->nacionalidad) && $vEmploy->nacionalidad != '' ? DB::table("teci_pais")->where("id",$vEmploy->nacionalidad)->value("token_pais") : '',
            "nacionalidad_pais" => !is_null($vEmploy->nacionalidad) && $vEmploy->nacionalidad != '' ? DB::table("teci_pais")->where("id",$vEmploy->nacionalidad)->value("pais") : '',
            "curp" => !is_null($vEmploy->curp) && $vEmploy->curp != '' ? $JwtAuth->desencriptar($vEmploy->curp) : '',
            "rfc" => !is_null($vEmploy->rfc) && $vEmploy->rfc != '' ? $JwtAuth->desencriptar($vEmploy->rfc) : '',
            "numero_de_seguridad_social" => !is_null($vEmploy->numero_de_seguridad_social) && $vEmploy->numero_de_seguridad_social != '' ? $vEmploy->numero_de_seguridad_social : '',

            //Domicilio
            "direcciones" => $arrayDirecciones,

            //contacto
            "telefono_tipo" => !is_null($vEmploy->telefono_tipo) && $vEmploy->telefono_tipo != '' ? $vEmploy->telefono_tipo : '',
            "telefono_numero" => !is_null($vEmploy->telefono_numero) && $vEmploy->telefono_numero != '' ? $JwtAuth->desencriptar($vEmploy->telefono_numero) : '',
            "correo" => !is_null($vEmploy->correo) && $vEmploy->correo != '' ? $JwtAuth->desencriptar($vEmploy->correo) : '',

            //Documentación Internacional
            "pasaporte" => $arrayPasaporte,
            "visa" => $arrayVisa,

            //licencia
            "licenciaConducir" => $arrayLicenciaConducir,
            
            //Información bancaria
            "bancCuentaBancoToken" => $getBanco ? $getBanco->token_bancos : '',
            "bancCuentaBancoClave" => $getBanco ? $getBanco->clave : '',
            "bancCuentaBancoNombreComercial" => $getBanco ? $getBanco->nombre_comercial : '',
            "bancCuentaCuenta" => $cuenta_descifrada,
            "bancCuentaCuentaMin" => $cuenta_descifrada_last_digitos,
            "cuenta_view" => false,
            "bancCuentaClabeInter" => $clabe_descifrada,
            "bancCuentaClabeInterMin" => $clabe_descifrada_last_digitos,
            "clabe_inter_view" => false,
            
            //Dentro de la empresa
            "centro_de_trabajo_uuid" => $centro_trab_uuid,
            "centro_de_trabajo_folio" => $cTrabQuery ? "$centro_trab_folio Registro patronal del IMSS: $centro_trab_registro_patronal_imss" : '',
            "departamento" => !is_null($vEmploy->departamento) ? $JwtAuth->desencriptar($vEmploy->departamento) : '',
            "puesto" => !is_null($vEmploy->puesto) ? $JwtAuth->desencriptar($vEmploy->puesto) : '',
            //salario
            "salario_tipo" => !is_null($vEmploy->salario_tipo) ? $vEmploy->salario_tipo : '',
            //contratacion
            "contratacion_tipo" => !is_null($vEmploy->contratacion_tipo) && $vEmploy->contratacion_tipo != '' ? $vEmploy->contratacion_tipo : '',
            "contratacion_tipo_nombre" => $contrato_tipo,
            "contratacion_fecha" => $contrato_tipo != '' && !is_null($vEmploy->contratacion_fecha) ? $JwtAuth->convierteEpocFechaHtml('UTC',$vEmploy->contratacion_fecha) : '',
            "alta_en_empresa" => !is_null($vEmploy->fecha_alta_en_empresa) && $vEmploy->fecha_alta_en_empresa != '' ? $JwtAuth->convierteEpocFechaHtml('UTC',$vEmploy->fecha_alta_en_empresa) : '',
            
            //nominas
            "nomina_periodicidad" => !is_null($vEmploy->nomina_periodicidad_pago) && $vEmploy->nomina_periodicidad_pago != '' ? $vEmploy->nomina_periodicidad_pago : '',
            "nomina_moneda" => !is_null($vEmploy->nomina_moneda) && $vEmploy->nomina_moneda != '' ? $vEmploy->nomina_moneda : '',
            "nomina_moneda_decimales" => $nomina_moneda_decimales,
            "nomina_jornada" => !is_null($vEmploy->nomina_jornada) && $vEmploy->nomina_jornada != '' ? $vEmploy->nomina_jornada : '',
            "nomina_turno" => !is_null($vEmploy->nomina_turno) && $vEmploy->nomina_turno != '' ? $vEmploy->nomina_turno : '',
            "nomina_salario_diario" => $cTrabSueldoActual ? number_format($cTrabSueldoActual->salario_diario,$nomina_moneda_decimales,'.','') : '0.00',
            "nomina_salario_integrado" => $cTrabSueldoActual ? number_format($cTrabSueldoActual->salario_diario_integrado,$nomina_moneda_decimales,'.','') : '0.00',
            //vhum_empleados_catalogo
            "selected" => false,
          );

          $rowEmpleado = array(
            "token_empleado_inside" => $vEmploy->empleado_token,
            "token_empleado_vhum" => $vEmploy->empleado_token,
            "folio_empleado" => $folio_empleado,
						"informacion_personal" => [$rowPersonales],
						"historial_de_sueldos" => $JwtAuth->trabSueldosHistorial($vEmploy->empleado_token),
						"contratos" => [],
						"movimientos_imss" => [],
						"recibos_de_nomina" => [],
						"adicionales_a_la_lft" => [],
						"equipo_proporcionado" => [],
						"otros" => [],
          );
          $arrayEmpleados[] = $rowEmpleado;
        }

        $dataMensaje = array(
          "empleado_info" => $arrayEmpleados,
          "code" => 200,
          "status" => "success"
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function trabajador_info_para_nominas(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_empleado_vhum' => 'required'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_empleado_vhum = $parametrosArray['token_empleado_vhum'];
        //da_te_default_timezone_set('UTC');

        $queryEmpleado = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.empleado_token",$token_empleado_vhum)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        if (count($queryEmpleado) > 0) {
          foreach ($queryEmpleado as $vEmploy) {
            $numero_de_seguridad_social = !is_null($vEmploy->numero_de_seguridad_social) && $vEmploy->numero_de_seguridad_social != '' ? $vEmploy->numero_de_seguridad_social : '';
            $rfc = !is_null($vEmploy->rfc) && $vEmploy->rfc != '' ? $JwtAuth->desencriptar($vEmploy->rfc) : '';
            $curp = !is_null($vEmploy->curp) && $vEmploy->curp != '' ? $JwtAuth->desencriptar($vEmploy->curp) : '';
            $fecha_alta_en_empresa = !is_null($vEmploy->fecha_alta_en_empresa) && $vEmploy->fecha_alta_en_empresa != '' ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_alta_en_empresa) : '';
            $salario_tipo = !is_null($vEmploy->salario_tipo) && $vEmploy->salario_tipo != '' ? $vEmploy->salario_tipo : '';
  
            $getBanco = DB::table("teci_bancos AS bank")
            ->join("vhum_empleados_catalogo AS trab", "bank.id", "trab.trabcuentabanc_banco")
            ->where("trab.empleado_token",$vEmploy->empleado_token)
            ->select('bank.token_bancos','bank.clave','bank.nombre_comercial')
            ->first();
  
            $cuenta_descifrada = '';
            $cuenta_descifrada_last_digitos = '';
            if (!is_null($vEmploy->trabcuentabanc_cuenta) && $vEmploy->trabcuentabanc_cuenta != '') {
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_cuenta);
              $cuenta_descifrada_substr = substr($JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_cuenta), -4);
              $cuenta_descifrada_last_digitos = "**** **** **** $cuenta_descifrada_substr";
            }
            
            $clabe_descifrada = '';
            $clabe_descifrada_last_digitos = '';
            if (!is_null($vEmploy->trabcuentabanc_clabe) && $vEmploy->trabcuentabanc_clabe != '') {
              $clabe_descifrada = $JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_clabe);
              $clabe_descifrada_substr = substr($JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_clabe), -4);
              $clabe_descifrada_last_digitos = "**** **** **** $clabe_descifrada_substr";
            }
  
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "numero_de_seguridad_social" => strtoupper($numero_de_seguridad_social),
              "rfc" => strtoupper($rfc),
              "curp" => strtoupper($curp),
              "fecha_alta" => strtoupper($fecha_alta_en_empresa),
              "departamento" => !is_null($vEmploy->departamento) && $vEmploy->departamento != '' ? $JwtAuth->desencriptar($vEmploy->departamento) : '',
              "puesto" => !is_null($vEmploy->puesto) && $vEmploy->puesto != '' ? $JwtAuth->desencriptar($vEmploy->puesto) : '',
              "salario_tipo" => $salario_tipo,
              "bancCuentaBancoToken" => $getBanco ? $getBanco->token_bancos : '',
              "bancCuentaBancoClave" => $getBanco ? $getBanco->clave : '',
              "bancCuentaBancoNombreComercial" => $getBanco ? $getBanco->nombre_comercial : '',
              "bancCuentaCuenta" => $cuenta_descifrada,
              "bancCuentaCuentaMin" => $cuenta_descifrada_last_digitos,
              "cuenta_view" => false,
              "bancCuentaClabeInter" => $clabe_descifrada,
              "bancCuentaClabeInterMin" => $clabe_descifrada_last_digitos,
              "clabe_inter_view" => false,
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Empleado no registrado'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function trabajador_info_para_nominas_by_nss(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'trabajador_nss' => 'required'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $trabajador_nss = $parametrosArray['trabajador_nss'];

        $queryEmpleado = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->where("people.numero_de_seguridad_social",$trabajador_nss)
        ->get();

        if (count($queryEmpleado) > 0) {
          foreach ($queryEmpleado as $vEmploy) {
            //da_te_default_timezone_set('UTC');
            $numero_de_seguridad_social = !is_null($vEmploy->numero_de_seguridad_social) && $vEmploy->numero_de_seguridad_social != '' ? $vEmploy->numero_de_seguridad_social : '';
            $rfc = !is_null($vEmploy->rfc) && $vEmploy->rfc != '' ? $JwtAuth->desencriptar($vEmploy->rfc) : '';
            $curp = !is_null($vEmploy->curp) && $vEmploy->curp != '' ? $JwtAuth->desencriptar($vEmploy->curp) : '';
            $fecha_alta_en_empresa = !is_null($vEmploy->fecha_alta_en_empresa) && $vEmploy->fecha_alta_en_empresa != '' ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_alta_en_empresa) : '';
            $salario_tipo = !is_null($vEmploy->salario_tipo) && $vEmploy->salario_tipo != '' ? $vEmploy->salario_tipo : '';
  
            $getBanco = DB::table("teci_bancos AS bank")
            ->join("vhum_empleados_catalogo AS trab", "bank.id", "trab.trabcuentabanc_banco")
            ->where("trab.empleado_token",$vEmploy->empleado_token)
            ->select('bank.token_bancos','bank.clave','bank.nombre_comercial')
            ->first();
  
            $cuenta_descifrada = '';
            $cuenta_descifrada_last_digitos = '';
            if (!is_null($vEmploy->trabcuentabanc_cuenta) && $vEmploy->trabcuentabanc_cuenta != '') {
              $cuenta_descifrada = $JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_cuenta);
              $cuenta_descifrada_substr = substr($JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_cuenta), -4);
              $cuenta_descifrada_last_digitos = "**** **** **** $cuenta_descifrada_substr";
            }
            
            $clabe_descifrada = '';
            $clabe_descifrada_last_digitos = '';
            if (!is_null($vEmploy->trabcuentabanc_clabe) && $vEmploy->trabcuentabanc_clabe != '') {
              $clabe_descifrada = $JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_clabe);
              $clabe_descifrada_substr = substr($JwtAuth->decryptBankAccount($vEmploy->trabcuentabanc_clabe), -4);
              $clabe_descifrada_last_digitos = "**** **** **** $clabe_descifrada_substr";
            }
  
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "empleado_token" => $vEmploy->empleado_token,
              "numero_de_seguridad_social" => strtoupper($numero_de_seguridad_social),
              "rfc" => strtoupper($rfc),
              "curp" => strtoupper($curp),
              "fecha_alta" => strtoupper($fecha_alta_en_empresa),
              "salario_tipo" => $salario_tipo,
              "bancCuentaBancoToken" => $getBanco ? $getBanco->token_bancos : '',
              "bancCuentaBancoClave" => $getBanco ? $getBanco->clave : '',
              "bancCuentaBancoNombreComercial" => $getBanco ? $getBanco->nombre_comercial : '',
              "bancCuentaCuenta" => $cuenta_descifrada,
              "bancCuentaCuentaMin" => $cuenta_descifrada_last_digitos,
              "cuenta_view" => false,
              "bancCuentaClabeInter" => $clabe_descifrada,
              "bancCuentaClabeInterMin" => $clabe_descifrada_last_digitos,
              "clabe_inter_view" => false,
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Empleado no registrado'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaTrabajador(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'token_empleado_vhum' => 'required|string',
        'apePaterno' => 'string',
        'apeMaterno' => 'string',
        'nombres' => 'string',
        'edad' => 'numeric',
        'domicilio_CalleNumero' => 'string',
        'domicilio_cod_postal' => 'numeric',
        'domicilio_colonia_vinculada' => 'string',
        'domicilio_municipio' => 'string',
        'domicilio_estado' => 'string',
        'origen_nacimiento_fecha' => 'string',
        'origen_nacimiento_lugar' => 'string',
        'origen_nacionalidad' => 'string',
        'sexo' => 'string',
        'estado_civil' => 'string',
        //'regimen_trabajador' => 'string',
        'contacto_telefono_tipo' => 'string',
        'contacto_telefono_numero' => 'string',
        'contacto_email' => 'string',
        'documentacion_curp' => 'string',
        'documentacion_rfc' => 'string',
        'documentacion_pasaporte_new' => 'array',
        'documentacion_pasaporte_delete' => 'array',
        'documentacion_visa_new' => 'array',
        'documentacion_visa_delete' => 'array',
        'documentacion_numero_de_seguridad_social' => 'string',
        'documentacion_licencia_new' => 'array',
        'documentacion_licencia_delete' => 'array',
        'cbancaria_banco_token' => 'string',
        'cbancaria_cuenta' => 'string',
        'cbancaria_clabe_inter' => 'string',
        //'cbancaria_sucursal' => 'string',
        'centro_de_trabajo' => 'string',
        'departamento' => 'string',
        'puesto' => 'string',
        'salario_tipo' => 'string',
        'contratacion_tipo' => 'string',
        'contratacion_fecha' => 'string',
        'alta_en_empresa' => 'string',
        'nomina_periodicidad' => 'string',
        'nomina_moneda' => 'string',
        'tipo_jornada' => 'string',
        'turno' => 'string',
        'salario_diario' => 'string',
        'salario_integrado' => 'string',
        'entra_en_vigor' => 'string',
        'observacion' => 'string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_empleado_vhum = $parametrosArray['token_empleado_vhum'];
        $apePaterno = $parametrosArray['apePaterno'];
        //return response()->json(['codigo' => 200,'status' => 'error','message' => $apePaterno]);
        //exit;
        $apeMaterno = $parametrosArray['apeMaterno'];
        $nombres = $parametrosArray['nombres'];
        $edad = $parametrosArray['edad'];
        $domicilio_CalleNumero = $parametrosArray['domicilio_CalleNumero'];
        $domicilio_cod_postal = $parametrosArray['domicilio_cod_postal'];
        $domicilio_colonia_vinculada = $parametrosArray['domicilio_colonia_vinculada'];
        $domicilio_municipio = $parametrosArray['domicilio_municipio'];
        $domicilio_estado = $parametrosArray['domicilio_estado'];
        $origen_nacimiento_fecha = $parametrosArray['origen_nacimiento_fecha'];
        $origen_nacimiento_lugar = $parametrosArray['origen_nacimiento_lugar'];
        $origen_nacionalidad = $parametrosArray['origen_nacionalidad'];
        $sexo = $parametrosArray['sexo'];
        $estado_civil = $parametrosArray['estado_civil'];
        //$regimen_trabajador = $parametrosArray['regimen_trabajador'];
        $contacto_telefono_tipo = $parametrosArray['contacto_telefono_tipo'];
        $contacto_telefono_numero = $parametrosArray['contacto_telefono_numero'];
        $contacto_email = $parametrosArray['contacto_email'];
        $documentacion_curp = $parametrosArray['documentacion_curp'];
        $documentacion_rfc = $parametrosArray['documentacion_rfc'];

        $documentacion_pasaporte_new = $parametrosArray['documentacion_pasaporte_new'];
        $documentacion_pasaporte_delete = $parametrosArray['documentacion_pasaporte_delete'];
        $documentacion_visa_new = $parametrosArray['documentacion_visa_new'];
        $documentacion_visa_delete = $parametrosArray['documentacion_visa_delete'];
        $documentacion_numero_de_seguridad_social = $parametrosArray['documentacion_numero_de_seguridad_social'];
        $documentacion_licencia_new = $parametrosArray['documentacion_licencia_new'];
        $documentacion_licencia_delete = $parametrosArray['documentacion_licencia_delete'];

        $cbancaria_banco_token = $parametrosArray['cbancaria_banco_token'];
        $cbancaria_cuenta = $parametrosArray['cbancaria_cuenta'];
        $cbancaria_clabe_inter = $parametrosArray['cbancaria_clabe_inter'];
        //$cbancaria_sucursal = $parametrosArray['cbancaria_sucursal'];
        $centro_de_trabajo = $parametrosArray['centro_de_trabajo'];
        $departamento = $parametrosArray['departamento'];
        $puesto = $parametrosArray['puesto'];
        $salario_tipo = $parametrosArray['salario_tipo'];
        $contratacion_tipo = $parametrosArray['contratacion_tipo'];
        $contratacion_fecha = $parametrosArray['contratacion_fecha'];
        $alta_en_empresa = $parametrosArray['alta_en_empresa'];
        $nomina_periodicidad = $parametrosArray['nomina_periodicidad'];
        $nomina_moneda = $parametrosArray['nomina_moneda'];
        $tipo_jornada = $parametrosArray['tipo_jornada'];
        $turno = $parametrosArray['turno'];

        $salario_diario = $parametrosArray['salario_diario'];
        $salario_integrado = $parametrosArray['salario_integrado'];
        $entra_en_vigor = $parametrosArray['entra_en_vigor'];
        $observacion = $parametrosArray['observacion'];

        $OKEmpTkn = isset($token_empleado_vhum) && !empty($token_empleado_vhum);
        $OKPaterno = isset($apePaterno) && !empty($apePaterno) && preg_match($JwtAuth->filtroAlfaNumerico(),$apePaterno);
        $OKMaterno = isset($apeMaterno) && !empty($apeMaterno) && preg_match($JwtAuth->filtroAlfaNumerico(),$apeMaterno);
        $OKNombres = isset($nombres) && !empty($nombres) && preg_match($JwtAuth->filtroAlfaNumerico(),$nombres);
        $OKEdad = isset($edad) && !empty($edad) && preg_match($JwtAuth->filtroNumericoSimple(),$edad);
        $OKSexo = isset($sexo) && !empty($sexo) && preg_match($JwtAuth->filtroAlfaNumerico(),$sexo);
        $OKEstCivil = isset($estado_civil) && !empty($estado_civil) && preg_match($JwtAuth->filtroAlfaNumerico(),$estado_civil);
        //$OKRegTrab = isset($regimen_trabajador) && !empty($regimen_trabajador);
        $OKDomiCalle = isset($domicilio_CalleNumero) && !empty($domicilio_CalleNumero) && preg_match($JwtAuth->filtroAlfaNumerico(),$domicilio_CalleNumero);
        $OKDomiCP = isset($domicilio_cod_postal) && !empty($domicilio_cod_postal) && preg_match($JwtAuth->filtroNumericoSimple(),$domicilio_cod_postal);
        $OKDomiCol = isset($domicilio_colonia_vinculada) && !empty($domicilio_colonia_vinculada) && preg_match($JwtAuth->filtroAlfaNumerico(),$domicilio_colonia_vinculada);
        $OKDomiMuni = isset($domicilio_municipio) && !empty($domicilio_municipio) && preg_match($JwtAuth->filtroAlfaNumerico(),$domicilio_municipio);
        $OKDomiestado = isset($domicilio_estado) && !empty($domicilio_estado) && preg_match($JwtAuth->filtroAlfaNumerico(),$domicilio_estado);
				$OKNacimDecha = isset($origen_nacimiento_fecha) && !empty($origen_nacimiento_fecha) && preg_match($JwtAuth->filtroFecha(),$origen_nacimiento_fecha);
        $OKNacimLugar = isset($origen_nacimiento_lugar) && !empty($origen_nacimiento_lugar) && preg_match($JwtAuth->filtroAlfaNumerico(),$origen_nacimiento_lugar);
        $OKNacionalidad = isset($origen_nacionalidad) && !empty($origen_nacionalidad);
        $OKContTelTipo = isset($contacto_telefono_tipo) && !empty($contacto_telefono_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$contacto_telefono_tipo);
        $OKContTelNumero = isset($contacto_telefono_numero) && !empty($contacto_telefono_numero) && preg_match($JwtAuth->filtroAlfaNumerico(),$contacto_telefono_numero);
        $OKContEmail = isset($contacto_email) && !empty($contacto_email) && preg_match($JwtAuth->filtroAlfaNumerico(),$contacto_email);
        $OKDocsCurp = isset($documentacion_curp) && !empty($documentacion_curp) && preg_match($JwtAuth->filtroAlfaNumerico(),$documentacion_curp);
        $OKDocsRfc = isset($documentacion_rfc) && !empty($documentacion_rfc) && preg_match($JwtAuth->filtroAlfaNumerico(),$documentacion_rfc);
        
        $OKDocsPasaporteNew = isset($documentacion_pasaporte_new) && is_array($documentacion_pasaporte_new) && count($documentacion_pasaporte_new) > 0;
        $OKDocsPasaporteDelete = isset($documentacion_pasaporte_delete) && is_array($documentacion_pasaporte_delete) && count($documentacion_pasaporte_delete) > 0;
        $OKDocsVisaNew = isset($documentacion_visa_new) && is_array($documentacion_visa_new) && count($documentacion_visa_new) > 0;
        $OKDocsVisaDelete = isset($documentacion_visa_delete) && is_array($documentacion_visa_delete) && count($documentacion_visa_delete) > 0;
        $OKDocsNSS = isset($documentacion_numero_de_seguridad_social) && !empty($documentacion_numero_de_seguridad_social) && preg_match($JwtAuth->filtroAlfaNumerico(),$documentacion_numero_de_seguridad_social);
        $OKDocsLicenciaNew = isset($documentacion_licencia_new) && is_array($documentacion_licencia_new) && count($documentacion_licencia_new) > 0;
        $OKDocsLicenciaDelete = isset($documentacion_licencia_delete) && is_array($documentacion_licencia_delete) && count($documentacion_licencia_delete) > 0;

        $OKCBancariaBancoTkn = isset($cbancaria_banco_token) && !empty($cbancaria_banco_token);
        $OKCBancariaCuenta = isset($cbancaria_cuenta) && !empty($cbancaria_cuenta) && preg_match($JwtAuth->filtroNumericoSimple(),$cbancaria_cuenta);
        $OKCBancariaClabeInter = isset($cbancaria_clabe_inter) && !empty($cbancaria_clabe_inter) && preg_match($JwtAuth->filtroNumericoSimple(),$cbancaria_clabe_inter);

        $OKEmpCenTrabaj = isset($centro_de_trabajo) && !empty($centro_de_trabajo);
        $OKEmpDepartamento = isset($departamento) && !empty($departamento) && preg_match($JwtAuth->filtroAlfaNumerico(),$departamento);
        $OKEmpPuesto = isset($puesto) && !empty($puesto) && preg_match($JwtAuth->filtroAlfaNumerico(),$puesto);
        $OKEmpSalarioTipo = isset($salario_tipo) && !empty($salario_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$salario_tipo);
        $OKEmpContratacionTipo = isset($contratacion_tipo) && !empty($contratacion_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$contratacion_tipo);
				$OKEmpContratacionFecha = isset($contratacion_fecha) && !empty($contratacion_fecha) && preg_match($JwtAuth->filtroFecha(),$contratacion_fecha);
				$OKEmpAltaEnEmpresa = isset($alta_en_empresa) && !empty($alta_en_empresa) && preg_match($JwtAuth->filtroFecha(),$alta_en_empresa);

        $OKPeriodicidad = isset($nomina_periodicidad) && !empty($nomina_periodicidad) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_periodicidad);
        $OKMoneda = isset($nomina_moneda) && !empty($nomina_moneda) && preg_match($JwtAuth->filtroAlfaNumerico(),$nomina_moneda);
        $OKTipoJornada = isset($tipo_jornada) && !empty($tipo_jornada) && preg_match($JwtAuth->filtroAlfaNumerico(),$tipo_jornada);
        $OKTurno = isset($turno) && !empty($turno) && preg_match($JwtAuth->filtroAlfaNumerico(),$turno);

        $OKEmpSalarioDiario = isset($salario_diario) && !empty($salario_diario) && preg_match($JwtAuth->filtroCostoPrecio(),$salario_diario);
        $OKEmpSalarioIntegrado = isset($salario_integrado) && !empty($salario_integrado) && preg_match($JwtAuth->filtroCostoPrecio(),$salario_integrado);

				$OKEmpSalarioEntraEnVigor = isset($entra_en_vigor) && !empty($entra_en_vigor) && preg_match($JwtAuth->filtroFecha(),$entra_en_vigor);
        $OKEmpSalarioObservacion = isset($observacion) && !empty($observacion) && preg_match($JwtAuth->filtroAlfaNumerico(),$observacion);
        $OKEmpSalarios = $OKEmpSalarioDiario && $OKEmpSalarioIntegrado && $OKEmpSalarioEntraEnVigor && $OKEmpSalarioObservacion;
        
        if ($OKEmpTkn || $OKPaterno || $OKMaterno || $OKNombres || $OKEdad || $OKSexo || $OKEstCivil || $OKDomiCalle || $OKDomiCP || $OKDomiCol || $OKDomiMuni || $OKDomiestado || $OKNacimDecha || $OKNacimLugar || $OKNacionalidad || 
          $OKContTelTipo || $OKContTelNumero || $OKContEmail || $OKDocsCurp || $OKDocsRfc || $OKDocsNSS || $OKEmpCenTrabaj || $OKEmpDepartamento || $OKEmpPuesto || $OKEmpSalarioTipo || $OKEmpContratacionTipo || 
          $OKEmpContratacionFecha || $OKEmpAltaEnEmpresa || $OKPeriodicidad || $OKMoneda || $OKTipoJornada || $OKTurno || $OKEmpSalarios) {

          $queryEmpleado = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
          ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where("vhum_empleados_catalogo.empleado_token",$token_empleado_vhum)
          ->where('emp.empresa_token',$usuario->empresa_token)
          ->where('users.usuario_token',$usuario->user_token)
          ->get();

          foreach ($queryEmpleado as $qTrab) {
            $folio_trab = 'TRAB-'.$JwtAuth->generarFolio($qTrab->folio_pers).(!is_null($qTrab->post_folio_pers) ? '-'.$qTrab->post_folio_pers : '');

            $nacionalidad_pais = DB::table("teci_pais")->where("token_pais",$origen_nacionalidad)->value("id");
            $id_trabajo_centro = DB::table("vhum_centros_de_trabajo_catalogo")->where('centrotrab_uuid',$centro_de_trabajo)->value("id");

            //$tokenPersona = $JwtAuth->encriptarToken($apePaterno,$apeMaterno,$nombres,$edad,$origen_nacimiento_fecha,$origen_nacimiento_lugar,$nacionalidad_pais,$sexo,$estado_civil);
            $queryNombresEmpleado = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
            ->where('people.token_personas',$qTrab->token_personas)
            ->where("vhum_empleados_catalogo.empleado_token",$qTrab->empleado_token)
            ->limit(1)->update(array(
              "people.paterno" => $JwtAuth->encriptar($apePaterno),
              "people.materno" => $JwtAuth->encriptar($apeMaterno),
              "people.nombre" => $JwtAuth->encriptar($nombres),
              "people.fecha_nacimiento" => $JwtAuth->convierteFechaEpoc($origen_nacimiento_fecha),
              "people.lugar_nacimiento" => $JwtAuth->encriptar($origen_nacimiento_lugar),
              "people.sexo" => $sexo,
              "people.estado_civil" => $estado_civil,
              "people.nacionalidad" => $nacionalidad_pais,
              "people.edad" => $edad,
              "people.rfc" => $OKDocsRfc ? $JwtAuth->encriptar($documentacion_rfc) : NULL,
              "people.curp" => $OKDocsCurp ? $JwtAuth->encriptar($documentacion_curp) : NULL,
              "people.numero_de_seguridad_social" => $OKDocsNSS ? $documentacion_numero_de_seguridad_social : NULL,
              //"people.pasaporte" => $OKDocsPasaporte ? $documentacion_pasaporte : NULL,
            ));

            $idBanco = $OKCBancariaBancoTkn ? DB::table("teci_bancos")->where("token_bancos",$cbancaria_banco_token)->value("id") : NULL;
            $cuentaEncode = $OKCBancariaCuenta ? $JwtAuth->encryptBankAccount($cbancaria_cuenta) : NULL;
            $clabeInterEncode = $OKCBancariaClabeInter ? $JwtAuth->encryptBankAccount($cbancaria_clabe_inter) : NULL;

            //$tokenEmpleado = $JwtAuth->encriptarToken($persona_empleado,$folio_nuevo,$post_folio);
            $updateEmpleado = PersonalModelo::where("empleado_token",$qTrab->empleado_token)
            ->limit(1)->update(array(
              "fecha_alta_en_empresa" => $JwtAuth->convierteFechaEpoc($alta_en_empresa),
              "centro_de_trabajo" => $id_trabajo_centro,
              "departamento" => $JwtAuth->encriptar($departamento),
              "puesto" => $JwtAuth->encriptar($puesto),
              "telefono_tipo" => $contacto_telefono_tipo,
              "telefono_numero" => $JwtAuth->encriptar($contacto_telefono_numero),
              "correo" => $JwtAuth->encriptar($contacto_email),
              "trabcuentabanc_banco" => $idBanco,
              "trabcuentabanc_cuenta" => $cuentaEncode,
              "trabcuentabanc_clabe" => $clabeInterEncode,
              "salario_tipo" => $salario_tipo,
              "nomina_periodicidad_pago" => $nomina_periodicidad,
              "nomina_moneda" => $nomina_moneda,
              "nomina_jornada" => $tipo_jornada,
              "nomina_turno" => $turno,
              "contratacion_fecha" => $JwtAuth->convierteFechaEpoc($contratacion_fecha),
              "contratacion_tipo" => $contratacion_tipo,
            ));
            $trabajador_cat_id = DB::table("vhum_empleados_catalogo")->where("empleado_token",$qTrab->empleado_token)->value("id");

            
            if ($OKEmpSalarios) {
              $cTrabSueldoActual = DB::table("vhum_empleados_registro_salarial AS sal_actual")
              ->join("vhum_empleados_catalogo AS trab", "sal_actual.trabajador", "trab.id")
              ->where("trab.empleado_token",$qTrab->empleado_token)
              ->orderByDesc("sal_actual.id")
              ->select('sal_actual.id','sal_actual.salario_diario','sal_actual.salario_diario_integrado','sal_actual.entra_en_vigor')
              ->first();

              $validacion_desigualdad_sueldos = $cTrabSueldoActual && $cTrabSueldoActual->salario_diario != $salario_diario && $cTrabSueldoActual->salario_diario_integrado != $salario_integrado;
              if ($cTrabSueldoActual && $validacion_desigualdad_sueldos) {
                DB::table("vhum_empleados_registro_salarial")
                ->where("id",$cTrabSueldoActual->id)
                ->limit(1)->update(array(
                  "expira" => $JwtAuth->convierteFechaEpoc($entra_en_vigor),
                  "motivo" => $JwtAuth->encriptar($observacion),
                ));
              }
                          
              if (!$cTrabSueldoActual || ($cTrabSueldoActual && $validacion_desigualdad_sueldos)) {
                DB::table("vhum_empleados_registro_salarial")
                ->insert(array(
                  "trabajador" => $trabajador_cat_id,
                  "salario_diario" => $salario_diario,
                  "salario_diario_integrado" => $salario_integrado,
                  "entra_en_vigor" => $JwtAuth->convierteFechaEpoc($entra_en_vigor),
                ));
              }
            }

            $trabPasaporteQuery = DB::table("vhum_empleados_pasaporte AS pass")
            ->join("vhum_empleados_catalogo AS trab", "pass.pasaporte_empleado", "trab.id")
            ->where("trab.empleado_token",$qTrab->empleado_token)
            ->count();
            if ($trabPasaporteQuery > 0 && $OKDocsPasaporteDelete) {
              foreach ($documentacion_pasaporte_delete as $d_pst_v => $d_pst_d) {
                $pasaporte_token = $d_pst_d["pasaporte_token"];
                DB::table("vhum_empleados_pasaporte")
                ->where("pasaporte_token",$pasaporte_token)->limit(1)->delete();
              }
            } elseif ($trabPasaporteQuery == 0 && $OKDocsPasaporteNew) {
              foreach ($documentacion_pasaporte_new as $e_pst_v => $e_pst_n) {
                $new_pst_numero = $e_pst_n["pasaporte_numero"];
                $new_pst_expide = $e_pst_n["pasaporte_expide"];
                $new_pst_vigencia = $e_pst_n["pasaporte_vigencia"];
                $tokenPassporte = $JwtAuth->encriptarToken($new_pst_numero,$new_pst_expide,$new_pst_vigencia);
                DB::table("vhum_empleados_pasaporte")
                ->insert(array(
                  "pasaporte_token" => $tokenPassporte,
                  "pasaporte_empleado" => $trabajador_cat_id,
                  "pasaporte_numero" => $new_pst_numero,
                  "pasaporte_expide" => $JwtAuth->encriptar($new_pst_expide),
                  "pasaporte_vigencia" => $JwtAuth->convierteFechaEpoc($new_pst_vigencia)
                ));
              }
            }

            $trabVISAQuery = DB::table("vhum_empleados_visa AS visa")
            ->join("vhum_empleados_catalogo AS trab", "visa.visa_empleado", "trab.id")
            ->where("trab.empleado_token",$qTrab->empleado_token)
            ->count();
            if ($trabVISAQuery > 0 && $OKDocsVisaDelete) {
              foreach ($documentacion_visa_delete as $d_visa_v => $d_visa_d) {
                $visa_token = $d_visa_d["visa_token"];
                DB::table("vhum_empleados_visa")
                ->where("visa_token",$visa_token)->limit(1)->delete();
              }
            } elseif ($trabVISAQuery == 0 && $OKDocsVisaNew) {
              foreach ($documentacion_visa_new as $e_visa_v => $e_visa_n) {
                $new_vis_numero = $e_visa_n["visa_numero"];
                $new_vis_expide = $e_visa_n["visa_expide"];
                $new_vis_vigencia = $e_visa_n["visa_vigencia"];
                $tokenVisa = $JwtAuth->encriptarToken($new_vis_numero,$new_vis_expide,$new_vis_vigencia);
                DB::table("vhum_empleados_visa")
                ->insert(array(
                  "visa_token" => $tokenVisa,
                  "visa_empleado" => $trabajador_cat_id,
                  "visa_numero" => $new_vis_numero,
                  "visa_expide" => $JwtAuth->encriptar($new_vis_expide),
                  "visa_vigencia" => $JwtAuth->convierteFechaEpoc($new_vis_vigencia)
                ));
              }
            }

            $trabLicenciaQuery = DB::table("vhum_empleados_licencia AS licen")
            ->join("vhum_empleados_catalogo AS trab", "licen.licencia_empleado", "trab.id")
            ->where("trab.empleado_token",$qTrab->empleado_token)
            ->count();
            if ($trabLicenciaQuery > 0 && $OKDocsLicenciaDelete) {
              foreach ($documentacion_licencia_delete as $d_licen_v => $d_licen_d) {
                $licencia_token = $d_licen_d["licencia_token"];
                DB::table("vhum_empleados_licencia")
                ->where("licencia_token",$licencia_token)->limit(1)->delete();
              }
            } elseif ($trabLicenciaQuery == 0 && $OKDocsLicenciaNew) {
              foreach ($documentacion_licencia_new as $e_licen_v => $e_licen_n) {
                $new_licen_nivel = $e_licen_n["licencia_nivel"];
                $new_licen_clase = $e_licen_n["licencia_clase"];
                $new_licen_numero = $e_licen_n["licencia_numero"];
                $new_licen_expide = $e_licen_n["licencia_expide"];
                $new_licen_fecha_expedicion = $e_licen_n["licencia_fecha_expedicion"];
                $new_licen_vigencia = $e_licen_n["licencia_vigencia"];
                $new_licen_permanente = $e_licen_n["licencia_permanente"];

                $tokenLicencia= $JwtAuth->encriptarToken($new_licen_nivel.$new_licen_clase.$new_licen_numero.$new_licen_expide.$new_licen_fecha_expedicion.$new_licen_vigencia.$new_licen_permanente);
                DB::table("vhum_empleados_licencia")
                ->insert(array(
                  "licencia_token" => $tokenLicencia,
                  "licencia_empleado" => $trabajador_cat_id,
                  "licencia_nivel" => $new_licen_nivel,
                  "licencia_clase" => $new_licen_clase,
                  "licencia_numero" => $JwtAuth->encriptar($new_licen_numero),
                  "licencia_expide" => $new_licen_expide,
                  "licencia_fecha_expedicion" => $JwtAuth->convierteFechaEpoc($new_licen_fecha_expedicion),
                  "licencia_vigencia" => $new_licen_vigencia,
                  "licencia_permanente" => $new_licen_permanente ? TRUE : FALSE
                ));
              }
            }

            $trabDomiQuery = DB::table("teci_direcciones AS dom")
            ->join("vhum_empleados_catalogo AS trab", "dom.trabajador", "trab.id")
            ->where("trab.empleado_token",$qTrab->empleado_token)
            ->count();
            if ($trabDomiQuery > 0) {
              $trabUpdateDomi = DB::table("teci_direcciones AS dom")
              ->join("vhum_empleados_catalogo AS trab", "dom.trabajador", "trab.id")
              ->where("trab.empleado_token",$qTrab->empleado_token)
              ->limit(1)->update(array(
                "dom.pais" => $nacionalidad_pais,
                "dom.pais_code" => "MEX",
                "dom.calle" => $JwtAuth->encriptar($domicilio_CalleNumero),
                "dom.estado_edit" => $JwtAuth->encriptar($domicilio_estado),
                "dom.municipio_edit" => $JwtAuth->encriptar($domicilio_municipio),
                "dom.c_postal_edit" => $domicilio_cod_postal,
                "dom.colonia_edit" => $JwtAuth->encriptar($domicilio_colonia_vinculada),
                "dom.adicional" => "api"
              ));
            } else {
              $tokenCDir = $JwtAuth->encriptarToken($origen_nacimiento_fecha,$domicilio_estado,$domicilio_municipio,$domicilio_cod_postal,$domicilio_colonia_vinculada);
              $trabInsertDomi = DB::table("teci_direcciones")
              ->insert(array(
                "token_direccion" => $tokenCDir,
                "clase" => $JwtAuth->encriptar("matriz"),
                "pais" => $nacionalidad_pais,
                "pais_code" => "MEX",
                "calle" => $JwtAuth->encriptar($domicilio_CalleNumero),
                "estado_edit" => $JwtAuth->encriptar($domicilio_estado),
                "municipio_edit" => $JwtAuth->encriptar($domicilio_municipio),
                "c_postal_edit" => $domicilio_cod_postal,
                "colonia_edit" => $JwtAuth->encriptar($domicilio_colonia_vinculada),
                "adicional" => "api",
                "trabajador" => $trabajador_cat_id,
                "status" => TRUE,
                "administrador" => DB::table("main_empresas")->where("empresa_token",$usuario->empresa_token)->value("id"),
              ));
            }
            
            //main_empresa_usuario
            //empresa Índice	int(10)			Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
            //empleado Índice	int(10)			Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
            //vinculacion_estado	tinyint(1)			Sí	NULL			Cambiar Cambiar	Eliminar Eliminar	
            //vinculacion_apagado	varchar(10)
            
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => "Información del trabajador con el folio $folio_trab ha sido actualizada satisfactoriamente"
            );
          }
        } else {
          $mensaje_error = "";
          if (!$OKPaterno) $mensaje_error = "Error al registrar apellido paterno, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKMaterno) $mensaje_error = "Error al registrar apellido materno, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKNombres) $mensaje_error = "Error al registrar nombres, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKEdad) $mensaje_error = "Error al registrar edad, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKSexo) $mensaje_error = "Error al registrar sexo, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKEstCivil) $mensaje_error = "Error al registrar estado civil, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDomiCalle) $mensaje_error = "Error al registrar calle y número, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDomiCP) $mensaje_error = "Error al registrar código postal, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDomiCol) $mensaje_error = "Error al registrar colonia, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDomiMuni) $mensaje_error = "Error al registrar municipio, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDomiestado) $mensaje_error = "Error al registrar estado, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKNacimDecha) $mensaje_error = "Error al registrar fecha de nacimiento, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKNacimLugar) $mensaje_error = "Error al registrar lugar de nacimiento, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKNacionalidad) $mensaje_error = "Error al registrar nacionalidad, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKContTelTipo) $mensaje_error = "Error al registrar tipo de teléfono, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKContTelNumero) $mensaje_error = "Error al registrar teléfono, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKContEmail) $mensaje_error = "Error al registrar email, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDocsCurp) $mensaje_error = "Error al registrar curp, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDocsRfc) $mensaje_error = "Error al registrar rfc, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKDocsNSS) $mensaje_error = "Error al registrar número de seguridad social, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpCenTrabaj) $mensaje_error = "Error al seleccionar centro de trabajo, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpDepartamento) $mensaje_error = "Error al seleccionar departamento de trabajo, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpPuesto) $mensaje_error = "Error al seleccionar puesto de trabajo, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpSalarioTipo) $mensaje_error = "Error al seleccionar tipo de salario, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpContratacionTipo) $mensaje_error = "Error al seleccionar tipo de contratación, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpContratacionFecha) $mensaje_error = "Error al seleccionar fecha de contratación, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpAltaEnEmpresa) $mensaje_error = "Error al seleccionar fecha en la que el trabajador ingreso a laborar, intentelo nuevamente o comuniquese a soporte";
          if (!$OKPeriodicidad) $mensaje_error = "Error al seleccionar periodicidad de pagos, intentelo nuevamente o comuniquese a soporte";
          if (!$OKMoneda) $mensaje_error = "Error al seleccionar la moneda relacionada a la nómina del trabajador, intentelo nuevamente o comuniquese a soporte";
          if (!$OKTipoJornada) $mensaje_error = "Error al seleccionar tipo de jornada del trabajador, intentelo nuevamente o comuniquese a soporte";
          if (!$OKTurno) $mensaje_error = "Error al seleccionar turno del trabajador, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpTkn) $mensaje_error = "Error al seleccionar trabajador, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpSalarioDiario) $mensaje_error = "Error al registrar salario diario del trabajador, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpSalarioIntegrado) $mensaje_error = "Error al registrar salario diario integrado del trabajador, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpSalarioEntraEnVigor) $mensaje_error = "Error al registrar fecha de entreada en vigor del salario diario del trabajador, intentelo nuevamente o comuniquese a soporte";
          if (!$OKEmpSalarioObservacion) $mensaje_error = "Error al registrar observaciones acerca del salario diario del trabajador, intentelo nuevamente o comuniquese a soporte";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
        
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function altaTrabajador(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonData = $request->input('json');
    $parametros = json_decode($jsonData);
    $argumentos = json_decode($jsonData,true);
    if (!empty($parametros) && !empty($argumentos)) {
      $validate = \Validator::make($argumentos,[
        'user_token' => 'required|string',
        'token_empleado_vhum' => 'required|string',
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametros de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($argumentos['user_token'], true);
        $token_empleado_vhum = $argumentos['token_empleado_vhum'];

        $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.empleado_token",$token_empleado_vhum)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        if (count($listEmpleados) > 0) {
          foreach ($listEmpleados as $vEmploy) {
            //da_te_default_timezone_set('UTC');
            $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');
            $updateWorker = DB::table("vhum_empleados_catalogo")
            ->where("status",TRUE)
            ->where("empleado_token",$vEmploy->empleado_token)
            ->limit(1)->update(array(
              "causa_baja" => FALSE,
              "motivo_causa_baja" => NULL,
              "fecha_causa_baja" => NULL,
            ));

            if ($updateWorker) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Trabajador con folio $folio_empleado ha sido dado de alta",
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Trabajador con folio $folio_empleado no dado de alta, intente más tarde o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Trabajador no se encuentra registrado, verifique su información o comuniquese a soporte',
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function bajaTrabajador(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_empleado_vhum' => 'required|string',
        'baja_motivo' => 'required|string',
        'fecha_contabilizacion' => 'required|string'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_empleado_vhum = $parametrosArray['token_empleado_vhum'];
        $baja_motivo = $parametrosArray['baja_motivo'];
        $fecha_contabilizacion = $parametrosArray['fecha_contabilizacion'];

        $OKBajaMotivo = isset($baja_motivo) && !empty($baja_motivo) && preg_match($JwtAuth->filtroAlfaNumerico(),$baja_motivo);
        $OKFechaContabilizacion = isset($fecha_contabilizacion) && !empty($fecha_contabilizacion) && preg_match($JwtAuth->filtroFecha(),$fecha_contabilizacion);

        if ($OKBajaMotivo && $OKFechaContabilizacion) {
          $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
          ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
          ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where("vhum_empleados_catalogo.empleado_token",$token_empleado_vhum)
          ->where('emp.empresa_token',$usuario->empresa_token)
          ->where('users.usuario_token',$usuario->user_token)
          ->get();

          foreach ($listEmpleados as $vEmploy) {
            //da_te_default_timezone_set('UTC');
            $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');
            $updateWorker = DB::table("vhum_empleados_catalogo")
            ->where("status",TRUE)
            ->where("empleado_token",$vEmploy->empleado_token)
            ->limit(1)->update(array(
              "causa_baja" => TRUE,
              "motivo_causa_baja" => $JwtAuth->encriptar($baja_motivo),
              "fecha_causa_baja" => $JwtAuth->convierteFechaEpoc($fecha_contabilizacion),
            ));

            if ($updateWorker) {
              $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                'message' => "Trabajador con folio $folio_empleado ha sido dado de baja",
              );
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Trabajador con folio $folio_empleado no dado de baja, intente más tarde o comuniquese a soporte",
              );
            }
          }
        } else {
          $mensaje_error = "";
          if (!$OKBajaMotivo) $mensaje_error = "Error al registrar motivo de baja, intentelo nuevamente o comuniquese a soporte"; 
          if (!$OKFechaContabilizacion) $mensaje_error = "Error al registrar fecha de baja, intentelo nuevamente o comuniquese a soporte";
          $dataMensaje = array('status' => 'error', 'code' => 200, 'message' => $mensaje_error);
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogo_trabajadores_activos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpleados = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.folio_pers", "!=", 0)
        ->where('vhum_empleados_catalogo.causa_baja',FALSE)
        ->where('vhum_empleados_catalogo.status',TRUE)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();
        //echo count($listEmpleados);
        foreach ($listEmpleados as $vEmploy) {
          $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');

          $token_empleado_dispositivo_firebase = $vEmploy->token_dispositivo_firebase;

          $nombre_completo = ucwords($JwtAuth->desencriptar($vEmploy->paterno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->materno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->nombre));

          //if (!is_null($vEmploy->img_perfil) && $JwtAuth->desencriptar($vEmploy->img_perfil) == 'default-profile.png') {
          //  $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($vEmploy->img_perfil)));
          //} else {
          //  $filepath = "main_users/" . $JwtAuth->generar($vEmploy->folio_pers) . "-" . $vEmploy->fecha_alta_pers;
          //  $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vEmploy->img_perfil) . '-profile.png'));
          //}

          $trabNominas = DB::table("vhum_nominas_recibos AS nom")
          ->join("vhum_empleados_catalogo AS trab", "nom.trabajador", "trab.id")
          ->where('trab.empleado_token',$vEmploy->empleado_token)
          ->count();

          $rowEmpleado = array(
            "token_empleado_inside" => $vEmploy->empleado_token,
            "token_empleado_vhum" => $vEmploy->empleado_token,
            "token_empleado_dispositivo_firebase" => $token_empleado_dispositivo_firebase,
            "folio_empleado" => $folio_empleado,
            "alta_en_empresa" => !is_null($vEmploy->fecha_alta_en_empresa) && $vEmploy->fecha_alta_en_empresa != '' ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_alta_en_empresa) : '',
            "token_personas" => $vEmploy->token_personas,
            "paterno" => ucwords($JwtAuth->desencriptar($vEmploy->paterno)),
            "materno" => ucwords($JwtAuth->desencriptar($vEmploy->materno)),
            "nombres" => ucwords($JwtAuth->desencriptar($vEmploy->nombre)),
            "nombre_completo" => ucwords($nombre_completo),
            "nacionalidad" => $vEmploy->nacionalidad,
            //"rfc_generico" => $vEmploy->rfc_generico,
            "rfc" => !is_null($vEmploy->rfc) && $vEmploy->rfc != '' ? $JwtAuth->desencriptar($vEmploy->rfc) : '',
            "tax_id" => !is_null($vEmploy->tax_id) && $vEmploy->tax_id != '' ? $vEmploy->tax_id : '',
            "curp" => !is_null($vEmploy->curp) && $vEmploy->curp != '' ? $JwtAuth->desencriptar($vEmploy->curp) : '',
            //"imagen" => $img_perfil,
            "baja_dado" => $vEmploy->causa_baja ? true : false,
            "baja_motivo" => $vEmploy->causa_baja ? $JwtAuth->desencriptar($vEmploy->motivo_causa_baja) : '',
            "baja_fecha" => $vEmploy->causa_baja ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_causa_baja) : '',
            "puede_eliminar" => $trabNominas == 0 ? true : false,
            "selected" => false,
            "ver_trabajador_info" => false,
            "trabajador_detail" => [],
          );
          $arrayEmpleados[] = $rowEmpleado;
        }

        $dataMensaje = array(
          "empleados" => $arrayEmpleados,
          "code" => 200,
          "status" => "success"
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogo_trabajadores_inactivos(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpleados = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.folio_pers", "!=", 0)
        ->where('vhum_empleados_catalogo.causa_baja',TRUE)
        ->where('vhum_empleados_catalogo.status',TRUE)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();
        //echo count($listEmpleados);
        foreach ($listEmpleados as $vEmploy) {
          $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');

          $token_empleado_dispositivo_firebase = $vEmploy->token_dispositivo_firebase;

          $nombre_completo = ucwords($JwtAuth->desencriptar($vEmploy->paterno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->materno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->nombre));

          //if (!is_null($vEmploy->img_perfil) && $JwtAuth->desencriptar($vEmploy->img_perfil) == 'default-profile.png') {
          //  $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($vEmploy->img_perfil)));
          //} else {
          //  $filepath = "main_users/" . $JwtAuth->generar($vEmploy->folio_pers) . "-" . $vEmploy->fecha_alta_pers;
          //  $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vEmploy->img_perfil) . '-profile.png'));
          //}

          $trabNominas = DB::table("vhum_nominas_recibos AS nom")
          ->join("vhum_empleados_catalogo AS trab", "nom.trabajador", "trab.id")
          ->where('trab.empleado_token',$vEmploy->empleado_token)
          ->count();

          $rowEmpleado = array(
            "token_empleado_inside" => $vEmploy->empleado_token,
            "token_empleado_vhum" => $vEmploy->empleado_token,
            "token_empleado_dispositivo_firebase" => $token_empleado_dispositivo_firebase,
            "folio_empleado" => $folio_empleado,
            "alta_en_empresa" => !is_null($vEmploy->fecha_alta_en_empresa) && $vEmploy->fecha_alta_en_empresa != '' ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_alta_en_empresa) : '',
            "token_personas" => $vEmploy->token_personas,
            "paterno" => ucwords($JwtAuth->desencriptar($vEmploy->paterno)),
            "materno" => ucwords($JwtAuth->desencriptar($vEmploy->materno)),
            "nombres" => ucwords($JwtAuth->desencriptar($vEmploy->nombre)),
            "nombre_completo" => ucwords($nombre_completo),
            "nacionalidad" => $vEmploy->nacionalidad,
            //"rfc_generico" => $vEmploy->rfc_generico,
            "rfc" => !is_null($vEmploy->rfc) && $vEmploy->rfc != '' ? $JwtAuth->desencriptar($vEmploy->rfc) : '',
            "tax_id" => !is_null($vEmploy->tax_id) && $vEmploy->tax_id != '' ? $vEmploy->tax_id : '',
            "curp" => !is_null($vEmploy->curp) && $vEmploy->curp != '' ? $JwtAuth->desencriptar($vEmploy->curp) : '',
            //"imagen" => $img_perfil,
            "baja_dado" => $vEmploy->causa_baja ? true : false,
            "baja_motivo" => $vEmploy->causa_baja ? $JwtAuth->desencriptar($vEmploy->motivo_causa_baja) : '',
            "baja_fecha" => $vEmploy->causa_baja ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_causa_baja) : '',
            "puede_eliminar" => $trabNominas == 0 ? true : false,
            "selected" => false,
            "ver_trabajador_info" => false,
            "trabajador_detail" => [],
          );
          $arrayEmpleados[] = $rowEmpleado;
        }

        $dataMensaje = array(
          "empleados" => $arrayEmpleados,
          "code" => 200,
          "status" => "success"
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function trabajador_eliminar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpleados = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_empleado_vhum' => 'required'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_empleado_vhum = $parametrosArray['token_empleado_vhum'];

        $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.status",TRUE)
        ->where("vhum_empleados_catalogo.empleado_token",$token_empleado_vhum)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        if (count($listEmpleados) > 0) {
          foreach ($listEmpleados as $vEmploy) {
            //da_te_default_timezone_set('UTC');
            $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');
  
            $trabNominas = DB::table("vhum_nominas_recibos AS nom")
            ->join("vhum_empleados_catalogo AS trab", "nom.trabajador", "trab.id")
            ->where('trab.empleado_token',$vEmploy->empleado_token)
            ->count();

            if ($trabNominas == 0) {
              $deleteCTrab = DB::table("vhum_empleados_catalogo")
              ->where("status",TRUE)
              ->where("empleado_token",$vEmploy->empleado_token)
              ->limit(1)->update(array("status" => FALSE,"fecha_delete" => time()));
  
              if ($deleteCTrab) {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => "Trabajador con folio $folio_empleado ha sido eliminado",
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => "Trabajador con folio $folio_empleado no eliminado, intente más tarde o comuniquese a soporte",
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Trabajador con folio $folio_empleado no eliminado, esta registrado en otros procedimientos, revise su información o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Trabajador no se encuentra registrado, verifique su información o comuniquese a soporte',
          );
        } 
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function catalogo_trabajadores_eliminados(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpleados = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.folio_pers", "!=", 0)
        ->where("vhum_empleados_catalogo.status",FALSE)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();
        //echo count($listEmpleados);
        foreach ($listEmpleados as $vEmploy) {
          $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');

          $token_empleado_dispositivo_firebase = $vEmploy->token_dispositivo_firebase;

          $nombre_completo = ucwords($JwtAuth->desencriptar($vEmploy->paterno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->materno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->nombre));

          //if (!is_null($vEmploy->img_perfil) && $JwtAuth->desencriptar($vEmploy->img_perfil) == 'default-profile.png') {
          //  $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($vEmploy->img_perfil)));
          //} else {
          //  $filepath = "main_users/" . $JwtAuth->generar($vEmploy->folio_pers) . "-" . $vEmploy->fecha_alta_pers;
          //  $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vEmploy->img_perfil) . '-profile.png'));
          //}

          $trabNominas = DB::table("vhum_nominas_recibos AS nom")
          ->join("vhum_empleados_catalogo AS trab", "nom.trabajador", "trab.id")
          ->where('trab.empleado_token',$vEmploy->empleado_token)
          ->count();

          $rowEmpleado = array(
            "token_empleado_inside" => $vEmploy->empleado_token,
            "token_empleado_vhum" => $vEmploy->empleado_token,
            "token_empleado_dispositivo_firebase" => $token_empleado_dispositivo_firebase,
            "folio_empleado" => $folio_empleado,
            "alta_en_empresa" => !is_null($vEmploy->fecha_alta_en_empresa) && $vEmploy->fecha_alta_en_empresa != '' ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_alta_en_empresa) : '',
            "token_personas" => $vEmploy->token_personas,
            "paterno" => ucwords($JwtAuth->desencriptar($vEmploy->paterno)),
            "materno" => ucwords($JwtAuth->desencriptar($vEmploy->materno)),
            "nombres" => ucwords($JwtAuth->desencriptar($vEmploy->nombre)),
            "nombre_completo" => ucwords($nombre_completo),
            "nacionalidad" => $vEmploy->nacionalidad,
            //"rfc_generico" => $vEmploy->rfc_generico,
            "rfc" => !is_null($vEmploy->rfc) && $vEmploy->rfc != '' ? $JwtAuth->desencriptar($vEmploy->rfc) : '',
            "tax_id" => !is_null($vEmploy->tax_id) && $vEmploy->tax_id != '' ? $vEmploy->tax_id : '',
            "curp" => !is_null($vEmploy->curp) && $vEmploy->curp != '' ? $JwtAuth->desencriptar($vEmploy->curp) : '',
            //"imagen" => $img_perfil,
            "baja_dado" => $vEmploy->causa_baja ? true : false,
            "baja_motivo" => $vEmploy->causa_baja ? $JwtAuth->desencriptar($vEmploy->motivo_causa_baja) : '',
            "baja_fecha" => $vEmploy->causa_baja ? gmdate('Y-m-d H:i:s', $vEmploy->fecha_causa_baja) : '',
            "puede_eliminar" => $trabNominas == 0 ? true : false,
            "selected" => false,
            "ver_trabajador_info" => false,
            "trabajador_detail" => [],
          );
          $arrayEmpleados[] = $rowEmpleado;
        }

        $dataMensaje = array(
          "empleados" => $arrayEmpleados,
          "code" => 200,
          "status" => "success"
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function trabajador_restaurar(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_empleado_vhum' => 'required'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_empleado_vhum = $parametrosArray['token_empleado_vhum'];

        $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.status",FALSE)
        ->where("vhum_empleados_catalogo.empleado_token",$token_empleado_vhum)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        if (count($listEmpleados) > 0) {
          foreach ($listEmpleados as $vEmploy) {
            //da_te_default_timezone_set('UTC');
            $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');
  
            $trabNominas = DB::table("vhum_nominas_recibos AS nom")
            ->join("vhum_empleados_catalogo AS trab", "nom.trabajador", "trab.id")
            ->where('trab.empleado_token',$vEmploy->empleado_token)
            ->count();

            if ($trabNominas == 0) {
              $restoreCTrab = DB::table("vhum_empleados_catalogo")
              ->where("status",FALSE)
              ->where("empleado_token",$vEmploy->empleado_token)
              ->limit(1)->update(array("status" => TRUE,"fecha_delete" => NULL));
  
              if ($restoreCTrab) {
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => "Trabajador con folio $folio_empleado ha sido restaurado",
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => "Trabajador con folio $folio_empleado no restaurado, intente más tarde o comuniquese a soporte",
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Trabajador con folio $folio_empleado no restaurado, esta registrado en otros procedimientos, revise su información o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Trabajador no se encuentra registrado, verifique su información o comuniquese a soporte',
          );
        } 
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function trabajador_eliminacion_permanente(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required',
        'token_empleado_vhum' => 'required'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_empleado_vhum = $parametrosArray['token_empleado_vhum'];

        $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
        ->join("main_empresas AS emp", "vhum_empleados_catalogo.empleado_empresa", "emp.id")
        ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
        ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
        ->where("vhum_empleados_catalogo.status",FALSE)
        ->where("vhum_empleados_catalogo.empleado_token",$token_empleado_vhum)
        ->where('emp.empresa_token',$usuario->empresa_token)
        ->where('users.usuario_token',$usuario->user_token)
        ->get();

        if (count($listEmpleados) > 0) {
          foreach ($listEmpleados as $vEmploy) {
            //da_te_default_timezone_set('UTC');
            $folio_empleado = 'TRAB-'.$JwtAuth->generarFolio($vEmploy->folio_pers).(!is_null($vEmploy->post_folio_pers) ? '-'.$vEmploy->post_folio_pers : '');
  
            $trabNominas = DB::table("vhum_nominas_recibos AS nom")
            ->join("vhum_empleados_catalogo AS trab", "nom.trabajador", "trab.id")
            ->where('trab.empleado_token',$vEmploy->empleado_token)
            ->count();

            if ($trabNominas == 0) {
              //DB::table("sos_personas")
              //->where("trabajador", function($query) use ($vEmploy){
              //  $query->select('id')
              //  ->from('vhum_empleados_catalogo')
              //  ->where('empleado_token',$vEmploy->empleado_token);
              //})->delete();

              //DB::table("vhum_empleados_registro_salarial AS sueld")
              //->join("vhum_empleados_catalogo AS trab", "sueld.trabajador", "trab.id")
              //->where("trab.empleado_token",$vEmploy->empleado_token)
              //->delete();

              DB::table("vhum_empleados_registro_salarial")
              ->where("trabajador", function($query) use ($vEmploy){
                $query->select('id')
                ->from('vhum_empleados_catalogo')
                ->where('empleado_token',$vEmploy->empleado_token);
              })->delete();

              DB::table("vhum_empleados_pasaporte")
              ->where("pasaporte_empleado", function($query) use ($vEmploy){
                $query->select('id')
                ->from('vhum_empleados_catalogo')
                ->where('empleado_token',$vEmploy->empleado_token);
              })->delete();

              DB::table("vhum_empleados_visa")
              ->where("visa_empleado", function($query) use ($vEmploy){
                $query->select('id')
                ->from('vhum_empleados_catalogo')
                ->where('empleado_token',$vEmploy->empleado_token);
              })->delete();

              DB::table("vhum_empleados_licencia")
              ->where("licencia_empleado", function($query) use ($vEmploy){
                $query->select('id')
                ->from('vhum_empleados_catalogo')
                ->where('empleado_token',$vEmploy->empleado_token);
              })->delete();

              DB::table("teci_direcciones")
              ->where("trabajador", function($query) use ($vEmploy){
                $query->select('id')
                ->from('vhum_empleados_catalogo')
                ->where('empleado_token',$vEmploy->empleado_token);
              })->delete();

              DB::table("sos_personas_telefonos")
              ->where("personal", function($query) use ($vEmploy){
                $query->select('id')
                ->from('vhum_empleados_catalogo')
                ->where('empleado_token',$vEmploy->empleado_token);
              })->delete();

              DB::table("sos_personas_correos")
              ->where("personal", function($query) use ($vEmploy){
                $query->select('id')
                ->from('vhum_empleados_catalogo')
                ->where('empleado_token',$vEmploy->empleado_token);
              })->delete();

              $userTrabQuery = DB::table("vhum_empleados_catalogo AS trab")
              ->join("teci_usuarios_catalogo AS users", "trab.id", "users.empleado")
              ->where("status",FALSE)
              ->where("trab.empleado_token",$vEmploy->empleado_token)
              ->get();
              
              if (count($userTrabQuery) == 1) {
                foreach ($userTrabQuery as $uTrab) {
                  $deleteCTrab = DB::table("teci_usuarios_catalogo")
                  ->where('usuario_token',$uTrab->usuario_token)
                  ->limit(1)->update(array("empleado" => NULL));
                }
              }

              $deleteCTrab = DB::table("vhum_empleados_catalogo")
              ->where("status",FALSE)
              ->where("empleado_token",$vEmploy->empleado_token)
              ->limit(1)->delete();
              
              if ($deleteCTrab) {
                DB::table("sos_personas")
                ->where("token_personas",$vEmploy->token_personas)
                ->limit(1)->delete();
                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => "Trabajador con folio $folio_empleado ha sido eliminado",
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => "Trabajador con folio $folio_empleado no eliminado, intente más tarde o comuniquese a soporte",
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => "Trabajador con folio $folio_empleado no eliminado, esta registrado en otros procedimientos, revise su información o comuniquese a soporte",
              );
            }
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Trabajador no se encuentra registrado, verifique su información o comuniquese a soporte',
          );
        } 
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function empleado_detalle_(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayEmpleados = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required'
      ]);
      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Los parametro de busqueda recibidos son incorrectos',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY") {
          $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
          ->join("teci_usuarios_catalogo AS users", "vhum_empleados_catalogo.id", "users.empleado")
          ->join("main_empresa_usuario AS empuser", "users.id", "empuser.usuario")
          ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
          ->where('emp.empresa_token',$usuario->empresa_token)->get();
        } else {
          $listEmpleados = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.empleado_name", "people.id")
          ->join("main_empresa_usuario AS empuser", "vhum_empleados_catalogo.id", "empuser.empleado")
          ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
          ->where("vhum_empleados_catalogo.folio_pers", "!=", 0)
          ->where('emp.empresa_token',$usuario->empresa_token)->get();
        }

        //echo count($listPersonal);
        foreach ($listEmpleados as $vEmploy) {
          $token_empleado_inside = $vEmploy->empleado_token;
          $token_empleado_vhum = $vEmploy->empleado_token;
          $token_empleado_dispositivo_firebase = $vEmploy->token_dispositivo_firebase;

          $nombre_completo = ucwords($JwtAuth->desencriptar($vEmploy->paterno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->materno)). " " .ucwords($JwtAuth->desencriptar($vEmploy->nombre));

          if ($JwtAuth->desencriptar($vEmploy->img_perfil) == 'default-profile.png') {
            $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($vEmploy->img_perfil)));
          } else {
            $filepath = "main_users/" . $JwtAuth->generar($vEmploy->folio_pers) . "-" . $vEmploy->fecha_alta_pers;
            $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $filepath . '/' . $JwtAuth->desencriptar($vEmploy->img_perfil) . '-profile.png'));
          }

          //telefonos
          $arrayTelefonos = array();
          $listPhone = DB::table("sos_personas_telefonos AS tel")
          ->join("vhum_empleados_catalogo AS pers", "tel.personal", "pers.id")
          ->where('pers.empleado_token',$token_empleado_inside)
          ->where('tel.status_telefono',TRUE)
          ->get();

          foreach ($listPhone as $vPhone) {
            if ($vPhone->extension != "" && $vPhone->extension != NULL) {
              $extension_tel = $JwtAuth->desencriptar($vPhone->extension);
            } else {
              $extension_tel = "";
            }

            $each = array(
              "token_telefono" => $vPhone->token_telefono,
              "icono" => $vPhone->icono,
              "etiqueta" => $vPhone->etiqueta,
              "telefono" => $JwtAuth->desencriptar($vPhone->telefono),
              //"telefono" => $JwtAuth->encriptar("5631863335"),
              "extension" => $extension_tel,
            );
            $arrayTelefonos[] = $each;
          }

          //correos
          $arrayCorreos = array();
          $listMails = DB::table("sos_personas_correos AS mail")
          ->join("vhum_empleados_catalogo AS pers", "mail.personal", "pers.id")
          ->where('pers.empleado_token',$token_empleado_inside)            
          ->where('mail.status_correo',TRUE)
          ->get();

          foreach ($listMails as $vMail) {
            $row = array(
              "token_correo" => $vMail->token_correo,
              "correo" => $JwtAuth->desencriptar($vMail->correo),
            );
            $arrayCorreos[] = $row;
          }

          //direcciones
          $arrayDirecciones = array();
          $listLocations = DB::table("teci_direcciones AS dom")
          ->join("vhum_empleados_catalogo AS pers", "dom.empleado", "pers.id")
          ->join('teci_direcciones_codigos_postales AS cpostal', 'dom.codigo_postal', 'cpostal.id')
          ->join("teci_pais AS detpais", "dom.pais", "detpais.id")
          ->where("dom.status",TRUE)
          ->where("pers.empleado_token",$token_empleado_inside)
          ->get();

          foreach ($listLocations as $vDom) {
            if ($vDom->calle != '' && $vDom->calle != NULL) {
              $calle = $JwtAuth->desencriptar($vDom->calle);
            } else {
              $calle = 's/c';
            }

            if ($vDom->num_ext != '' && $vDom->num_ext != NULL) {
              $num_ext = $JwtAuth->desencriptar($vDom->num_ext);
            } else {
              $num_ext = 's/n';
            }

            if ($vDom->num_int != '' && $vDom->num_int != NULL) {
              $num_int = $JwtAuth->desencriptar($vDom->num_int);
            } else {
              $num_int = 's/n';
            }

            if ($vDom->calle1 != '' && $vDom->calle1 != NULL) {
              $calle1 = $JwtAuth->desencriptar($vDom->calle1);
            } else {
              $calle1 = 's/c';
            }

            if ($vDom->calle2 != '' && $vDom->calle2 != NULL) {
              $calle2 = $JwtAuth->desencriptar($vDom->calle2);
            } else {
              $calle2 = 's/c';
            }

            if ($vDom->referencia != '' && $vDom->referencia != NULL) {
              $referencia = $JwtAuth->desencriptar($vDom->referencia);
            } else {
              $referencia = 's/reg';
            }

            $domRow = array(
              "token_direccion" => $vDom->token_direccion,
              "token_direccion" => $vDom->token_direccion,
              "tipo_direccion" => $vDom->tipo_direccion,
              "clasificacion" => $JwtAuth->desencriptar($vDom->clase),
              "alias" => $JwtAuth->desencriptar($vDom->alias),
              "calle" => $calle,
              "num_ext" => $num_ext,
              "num_int" => $num_int,
              "token_codigos_postales" => $vDom->token_codigos_postales,
              "codigo_postal" => $vDom->codigo_postal,
              "asentamiento" => $vDom->asentamiento,
              "tipo_asentamiento" => $vDom->tipo_asentamiento,
              "deleg_mun" => $vDom->deleg_mun,
              "estado" => $vDom->estado,
              "ciudad" => $vDom->ciudad,
              "pais" => $vDom->pais,
              "calle1" => $calle1,
              "calle2" => $calle2,
              "referencia" => $referencia,
              "validate" => false,
            );
            $arrayDirecciones[] = $domRow;
          }

          $rowEmpleado = array(
            "token_empleado_inside" => $token_empleado_inside,
            "token_empleado_vhum" => $token_empleado_vhum,
            "token_empleado_dispositivo_firebase" => $token_empleado_dispositivo_firebase,
            "folio_empleado" => "TRB-" . $JwtAuth->generarFolio($vEmploy->folio_pers),
            "nombre_completo" => ucwords($nombre_completo),
            "paterno" => ucwords($JwtAuth->desencriptar($vEmploy->paterno)),
            "materno" => ucwords($JwtAuth->desencriptar($vEmploy->materno)),
            "nombres" => ucwords($JwtAuth->desencriptar($vEmploy->nombre)),
            "imagen" => $img_perfil,
            "telefonos" => $arrayTelefonos,
            "correos" => $arrayCorreos,
            "direcciones" => $arrayDirecciones,
            "selected" => false,
          );
          $arrayEmpleados[] = $rowEmpleado;
        }

        $dataMensaje = array(
          "empleados" => $arrayEmpleados,
          "code" => 200,
          "status" => "success"
        );
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'Los parametro de busqueda recibidos son inexistentes'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaPaternoPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'paterno' => 'required|string',
        'token_personal' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $paterno = $parametrosArray['paterno'];
        $token_personal = $parametrosArray['token_personal'];

        $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
        if (
          isset($paterno) && !empty($paterno) && preg_match($patron, $paterno) &&
          isset($token_personal) && !empty($token_personal)
        ) {

          $updatePaterno = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "vhum_empleados_catalogo.usuario")
            ->join("sos_personas AS people", "vhum_empleados_catalogo.personal", "=", "people.id")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'people.paterno' => $JwtAuth->encriptar(ucwords($paterno)),
              )
            );

          if ($updatePaterno) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Apellido paterno de personal actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Apellido paterno de personal no actualizado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en apellido paterno de personal'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaMaternoPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'materno' => 'required|string',
        'token_personal' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $materno = $parametrosArray['materno'];
        $token_personal = $parametrosArray['token_personal'];

        $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
        if (
          isset($materno) && !empty($materno) && preg_match($patron, $materno) &&
          isset($token_personal) && !empty($token_personal)
        ) {

          $updateMaterno = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "vhum_empleados_catalogo.usuario")
            ->join("sos_personas AS people", "vhum_empleados_catalogo.personal", "=", "people.id")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'people.materno' => $JwtAuth->encriptar(ucwords($materno)),
              )
            );

          if ($updateMaterno) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Apellido materno de personal actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Apellido materno de personal no actualizado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en apellido materno de personal'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaNombresPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'nombres' => 'required|string',
        'token_personal' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $nombres = $parametrosArray['nombres'];
        $token_personal = $parametrosArray['token_personal'];

        $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
        if (
          isset($nombres) && !empty($nombres) && preg_match($patron, $nombres) &&
          isset($token_personal) && !empty($token_personal)
        ) {

          $updateNombres = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "pers.usuario")
            ->join("sos_personas AS people", "pers.personal", "=", "people.id")
            ->join("main_empresa_usuario AS empuser", "pers.id", "=", "empuser.empleado")
            ->join("main_empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'people.nombre' => $JwtAuth->encriptar(ucwords($nombres)),
              )
            );

          if ($updateNombres) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Nombre(s) de personal actualizado(s)'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Nombre(s) de personal no actualizado(s)'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en nombre(s) de personal'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaAreaPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'email' => 'required|string',
        'token_personal' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $email = $parametrosArray['email'];
        $token_personal = $parametrosArray['token_personal'];

        $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
        if (
          isset($email) && !empty($email) && preg_match($patronMail, $email) &&
          isset($token_personal) && !empty($token_personal)
        ) {
          $updatePaterno = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "vhum_empleados_catalogo.usuario")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'users.email' => $JwtAuth->encriptar($email),
              )
            );

          if ($updatePaterno) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Correo electrónico de personal actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Correo electrónico de personal no actualizado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en correo electrónico de personal'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaMailPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'email' => 'required|string',
        'username' => 'required|string',
        'token_personal' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $email = $parametrosArray['email'];
        $username = $parametrosArray['username'];
        $token_personal = $parametrosArray['token_personal'];

        $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
        if (
          isset($email) && !empty($email) && preg_match($patronMail, $email) &&
          isset($username) && !empty($username) &&
          isset($token_personal) && !empty($token_personal)
        ) {
          $updateEmail = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "vhum_empleados_catalogo.usuario")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'users.email' => $JwtAuth->encriptar($email),
                'users.username' => $JwtAuth->encriptar($username),
              )
            );

          if ($updateEmail) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Correo electrónico de personal actualizado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Correo electrónico de personal no actualizado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en correo electrónico de personal'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function registraTelefonoPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'telefono' => 'required|string',
        'token_personal' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $telefono = $parametrosArray['telefono'];
        $token_personal = $parametrosArray['token_personal'];

        $patronNum = '/^[1-9][0-9]*$/';
        if (
          isset($telefono) && !empty($telefono) && preg_match($patronNum, $telefono) &&
          isset($token_personal) && !empty($token_personal)
        ) {

          $selectPers = DB::select("SELECT id FROM personal WHERE empleado_token = ?", [$token_personal]);
          $tokentel = $JwtAuth->encriptarToken($token_personal . $telefono);
          $encriptPhone = $JwtAuth->encriptar($telefono);
          $insertTelefono = DB::table('sos_personas_telefonos')
            ->insert(array(
              "token_telefono" => $tokentel,
              "personal" => $selectPers[0]->id,
              "icono" => "",
              "etiqueta" => "",
              "cod_pais" => "52",
              "telefono" => $encriptPhone,
              "extension" => "",
              "status_telefono" => TRUE,
              "fecha_delete_tel" => NULL,
            ));

          if ($insertTelefono) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Teléfono de personal registrado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Teléfono de personal no registrado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en correo electrónico de personal'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function actualizaTelefonoPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'telefono' => 'required|string',
        'token_personal' => 'required|string',
        'token_telefono' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $telefono = $parametrosArray['telefono'];
        $token_personal = $parametrosArray['token_personal'];
        $token_telefono = $parametrosArray['token_telefono'];

        $patronNum = '/^[1-9][0-9]*$/';
        if (
          isset($telefono) && !empty($telefono) && preg_match($patronNum, $telefono) &&
          isset($token_personal) && !empty($token_personal) &&
          isset($token_telefono) && !empty($token_telefono)
        ) {

          $encriptPhone = $JwtAuth->encriptar($telefono);

          $updateTelefono = DB::table("sos_personas_telefonos AS phone")
            ->join("vhum_empleados_catalogo AS pers", "phone.personal", "=", "vhum_empleados_catalogo.id")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'phone.token_telefono' => $token_telefono,
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'phone.telefono' => $encriptPhone,
              )
            );

          if ($updateTelefono) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Teléfono de personal registrado'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Teléfono de personal no registrado'
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'Error en correo electrónico de personal'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function generaPassCodeUserPersonalSOS(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayTareas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'required|string',
        'access_code' => 'required|string',
        'password_code' => 'required|string',
        'token_personal' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'informcación incorrecta',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $access_code = $parametrosArray['access_code'];
        $password_code = $parametrosArray['password_code'];
        $token_personal = $parametrosArray['token_personal'];

        $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
        if (
          isset($access_code) && !empty($access_code) &&
          isset($password_code) && !empty($password_code) &&
          isset($token_personal) && !empty($token_personal)
        ) {
          $updatePaterno = DB::table("teci_usuarios_catalogo AS users")
            ->join("vhum_empleados_catalogo AS pers", "users.id", "=", "vhum_empleados_catalogo.usuario")
            ->join("empresapersonal AS empuser", "vhum_empleados_catalogo.id", "=", "empuser.empleado")
            ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
            ->where([
              'pers.empleado_token' => $token_personal,
              'emp.empresa_token' => $usuario->empresa_token,
            ])
            ->limit(1)->update(
              array(
                'users.codigo_acceso' => $JwtAuth->encriptar($access_code),
                'users.password' => $JwtAuth->encriptar($password_code),
              )
            );

          if ($updatePaterno) {
            $dataMensaje = array(
              'status' => 'success',
              'code' => 200,
              'message' => 'Códigos de acceso generados'
            );
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Códigos de acceso no generados'
            );
          }
        } else {
          if (isset($access_code) && !empty($access_code)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en código de acceso de personal'
            );
          }

          if (isset($password_code) && !empty($password_code)) {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'Error en password de personal'
            );
          }
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function listaPersonalGneral(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $personal = array();

    $listPersonal = PersonalModelo::join("sos_personas AS people", "vhum_empleados_catalogo.personal", "people.id")
      ->join("main_empresa_usuario AS empuser", "vhum_empleados_catalogo.id", "empuser.empleado")
      ->join("main_empresas AS emp", "empuser.empresa", "emp.id")
      ->where([
        'emp.empresa_token' => $usuario->empresa_token,
      ])->get();

    //echo count($listPersonal);
    foreach ($listPersonal as $resPersonal) {
      if ($JwtAuth->desencriptar($resPersonal->img_perfil) == 'default-profile.png') {
        $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($resPersonal->img_perfil)));
      } else {
        $img_perfil =  $JwtAuth->encriptaBase64(Storage::path('public/root/' .
          $resPersonal->root_tkn . '/0004-vhm/catalogos/employees/' . $JwtAuth->desencriptar($resPersonal->img_perfil)
          . '/' . $JwtAuth->desencriptar($resPersonal->img_perfil) . '-profile.png'));
      }

      $nombre_full = $JwtAuth->desencriptar($resPersonal->paterno)
        . " " . $JwtAuth->desencriptar($resPersonal->materno)
        . " " . $JwtAuth->desencriptar($resPersonal->nombre);

      $arrayRespons = array(
        "token_personal" => $resPersonal->empleado_token,
        "folio" => $JwtAuth->generar($resPersonal->folio_pers),
        "nombre_completo" => ucwords($nombre_full),
        //"email" => $JwtAuth->desencriptar($resPersonal->email),
        "imagen" => $img_perfil,
        "selected" => false,
      );
      if ($usuario->user_token == "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4") {
        $personal[] = $arrayRespons;
      } else {
        if ($resPersonal->empleado_token != "8bTRXM1JJS0FpYy9CdEtFWGRHSExXZz09OjoxMjM0NTY3ODEyMzQ1Njc4") {
          $personal[] = $arrayRespons;
        }
      }
    }

    return response()->json([
      'personal' => $personal,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function listaPersonalArea(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $personal = array();

    $tipo_userquery = PersonalModelo::join("sos_personas AS people", "personal.personal", "people.id")
      ->join("empresapersonal AS empuser", "personal.id", "empuser.empleado")
      ->join("teci_usuarios_catalogo AS users", "personal.usuario", "users.id")
      ->join("tipo_usuario AS typeuser", "users.tipo", "typeuser.id_tipo")
      ->join("empresas AS emp", "empuser.empresa", "emp.id")
      ->where([
        'emp.empresa_token' => $usuario->empresa_token,
        'users.user_token' => $usuario->user_token,
      ])->get();

    //echo $tipo_userquery[0]->tipo;
    if ($tipo_userquery[0]->tipo == 'SuperAdministrador') {
      $area_tipo = 'sos-ti';
    } else {
      $area_tipo = $tipo_userquery[0]->tipo;
    }

    $listPersonal = PersonalModelo::join("sos_personas AS people", "personal.personal", "people.id")
      ->join("empresapersonal AS empuser", "personal.id", "empuser.empleado")
      ->join("teci_usuarios_catalogo AS users", "personal.usuario", "users.id")
      ->join("tipo_usuario AS typeuser", "users.tipo", "typeuser.id_tipo")
      ->join("empresas AS emp", "empuser.empresa", "emp.id")
      ->where([
        'typeuser.tipo' => $area_tipo,
        'personal.jerarquia' => 'terc',
        'emp.empresa_token' => $usuario->empresa_token,
      ])->get();

    //echo count($listPersonal);
    foreach ($listPersonal as $resPersonal) {
      if ($JwtAuth->desencriptar($resPersonal->img_perfil) == 'default-profile.png') {
        $img_perfil = $JwtAuth->encriptaBase64(Storage::path('public/settings/' . $JwtAuth->desencriptar($resPersonal->img_perfil)));
      } else {
        $img_perfil =  $JwtAuth->encriptaBase64(Storage::path('public/root/' .
          $resPersonal->root_tkn . '/0004-vhm/catalogos/employees/' . $JwtAuth->desencriptar($resPersonal->img_perfil)
          . '/' . $JwtAuth->desencriptar($resPersonal->img_perfil) . '-profile.png'));
      }

      $name_completo = $JwtAuth->desencriptar($resPersonal->paterno) . " " .
        $JwtAuth->desencriptar($resPersonal->materno) . " " .
        $JwtAuth->desencriptar($resPersonal->nombre);

      $arrayRespons = array(
        "token_personal" => $resPersonal->empleado_token,
        "folio" => $JwtAuth->generar($resPersonal->folio_pers),
        "nombre_completo" => ucwords($name_completo),
        "imagen" => $img_perfil,
      );

      $personal[] = $arrayRespons;
    }

    return response()->json([
      'personal' => $personal,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function listaResponsablesAlmacen(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $usuario = $JwtAuth->checkToken($parametros->user_token, true);
    $personal = array();
    $listPersonal = PersonalModelo::join("sos_personas AS people", "personal.personal", "people.id")
      ->join("responsables_almacen AS resp", "personal.id", "resp.responsable")
      ->join("almacen as alm", "resp.almacen", "alm.id")
      ->join("empresas AS emp", "alm.empresa", "emp.id")
      ->where([
        'personal.status' => TRUE,
        'emp.empresa_token' => $usuario->empresa_token,
        'alm.token_almacen' => $parametros->datalmpers,
        //'users.user_token' => $tokenUser '1457869IHDIFUJJ39485'
      ])->get();
    $name_completo = $JwtAuth->desencriptar($resPersonal->paterno) . " " .
      $JwtAuth->desencriptar($resPersonal->materno) . " " .
      $JwtAuth->desencriptar($resPersonal->nombre);
    //echo count($listPersonal);
    foreach ($listPersonal as $resPersonal) {
      $arrayRespons = array(
        "token_personal" => $resPersonal->token_responsables,
        "nombre_completo" => ucwords($name_completo),
        "imagen" => $JwtAuth->desencriptar($resPersonal->img_perfil)
      );

      $personal[] = $arrayRespons;
    }

    return response()->json([
      'personal' => $personal,
      'codigo' => 200,
      'status' => 'success'
    ]);
  }

  public function asistenciaPersonalEntrada(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

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
        $nombrePersonal = '';

        //$usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
        //user_token original dGEzQVAzZnArRmY3SXpoV0lsTzRkem8xNkdtM1JFRFJOSnlEV1FKNXRreVRGdE9Tb05RVVB1R0QrelZtWkFPSStlVlNVNmpjZWEyQTQzelVpS1AzSFYwanc4cVBrZ1Q3aXZPV1M0ZTJTQW5CUmJhYUlXVHFvS2xzY1pqd1hCZ3RNRjB2c2hSTWsyalpzVDlwNDZqayswZU9mSHRDME5xWGRkbVJRVE9GM2ppQld3S242cnBLVVpJaTF0aGlkSVE5cUhnUlJOaUZYK2FhTmFRUXNjdjZWQzhEbDFhZUsxVks5MDRKMi9FOUlWNGxWVHl1ZDdSeER6N1k3MlBFbktlMCtoS25lMmNWeVlFbmQ5VG9PdVFSejJEOW9JYjVEc01lN3ZPRWRRYXFUT0E9OjoxMjM0NTY3ODEyMzQ1Njc4 

        $listPersonal = PersonalModelo::join("sos_personas AS people", "personal.personal", "people.id")
          ->join("empresapersonal AS empuser", "personal.id", "=", "empuser.empleado")
          ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "personal.usuario", "=", "users.id")
          ->where([
            'personal.status' => TRUE,
            //'emp.empresa_token' => $usuario->empresa_token,
            //'users.user_token' => $usuario->user_token,
            'users.user_token' => $parametrosArray['user_token'],
          ])->get();

        if (count($listPersonal) == 1) {
          foreach ($listPersonal as $resPersonal) {
            $fecha = time();
            //da_te_default_timezone_set($resPersonal->zona_horaria);

            $nombrePersonal = $JwtAuth->desencriptar($resPersonal->paterno)
              . " " . $JwtAuth->desencriptar($resPersonal->materno)
              . " " . $JwtAuth->desencriptar($resPersonal->nombre);

            $selectEmp = DB::select("SELECT emp.id FROM empresas AS emp  
                            JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN usuarios AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.empleado = pers.id 
                            AND pers.usuario = users.id AND users.user_token= ?", [$resPersonal->empresa_token, $resPersonal->user_token]);

            $empleado_token = DB::select("SELECT id FROM personal WHERE empleado_token = ?", [$resPersonal->empleado_token]);

            $insertAsistenciaEntrada = DB::table('asistencias')
              ->insert(
                array(
                  "empresa" => $selectEmp[0]->id,
                  "personal" => $empleado_token[0]->id,
                  "entrada" => $fecha,
                  "salida" => NULL,
                )
              );

            if ($insertAsistenciaEntrada) {
              $dataMensaje = array(
                'personal' => $nombrePersonal,
                'fecha' => gmdate('Y-m-d H:i:s', $fecha),
                'hora' => date('H:i:s', $fecha),
                'code' => 200,
                'status' => 'success'
              );
            } else {
              $dataMensaje = array(
                'message' => 'error en registro de entrada, intente nuevamente o comuniquese a soporte',
                'code' => 200,
                'status' => 'error'
              );
            }
          }
        } else {
          $dataMensaje = array(
            'message' => 'personal no encontrado',
            'code' => 200,
            'status' => 'error'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }

  public function asistenciaPersonalSalida(Request $request){
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

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
        $nombrePersonal = '';

        //$usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
        //user_token original dGEzQVAzZnArRmY3SXpoV0lsTzRkem8xNkdtM1JFRFJOSnlEV1FKNXRreVRGdE9Tb05RVVB1R0QrelZtWkFPSStlVlNVNmpjZWEyQTQzelVpS1AzSFYwanc4cVBrZ1Q3aXZPV1M0ZTJTQW5CUmJhYUlXVHFvS2xzY1pqd1hCZ3RNRjB2c2hSTWsyalpzVDlwNDZqayswZU9mSHRDME5xWGRkbVJRVE9GM2ppQld3S242cnBLVVpJaTF0aGlkSVE5cUhnUlJOaUZYK2FhTmFRUXNjdjZWQzhEbDFhZUsxVks5MDRKMi9FOUlWNGxWVHl1ZDdSeER6N1k3MlBFbktlMCtoS25lMmNWeVlFbmQ5VG9PdVFSejJEOW9JYjVEc01lN3ZPRWRRYXFUT0E9OjoxMjM0NTY3ODEyMzQ1Njc4 

        $listPersonal = PersonalModelo::join("sos_personas AS people", "personal.personal", "people.id")
          ->join("empresapersonal AS empuser", "personal.id", "=", "empuser.empleado")
          ->join("empresas AS emp", "empuser.empresa", "=", "emp.id")
          ->join("teci_usuarios_catalogo AS users", "personal.usuario", "=", "users.id")
          ->where([
            'personal.status' => TRUE,
            //'emp.empresa_token' => $usuario->empresa_token,
            //'users.user_token' => $usuario->user_token,
            'users.user_token' => $parametrosArray['user_token'],
          ])->get();

        if (count($listPersonal) == 1) {
          foreach ($listPersonal as $resPersonal) {
            $fecha = time();
            //da_te_default_timezone_set($resPersonal->zona_horaria);

            $nombrePersonal = $JwtAuth->desencriptar($resPersonal->paterno)
              . " " . $JwtAuth->desencriptar($resPersonal->materno)
              . " " . $JwtAuth->desencriptar($resPersonal->nombre);

            $selectEmp = DB::select("SELECT emp.id FROM empresas AS emp  
                            JOIN empresapersonal AS empuser JOIN vhum_personal AS pers JOIN usuarios AS users WHERE emp.empresa_token = ? 
                            AND emp.id = empuser.empresa AND empuser.empleado = pers.id 
                            AND pers.usuario = users.id AND users.user_token= ?", [$resPersonal->empresa_token, $resPersonal->user_token]);

            $empleado_token = DB::select("SELECT id FROM personal WHERE empleado_token = ?", [$resPersonal->empleado_token]);

            $insertAsistenciaEntrada = DB::table('asistencias')
              ->insert(
                array(
                  "empresa" => $selectEmp[0]->id,
                  "personal" => $empleado_token[0]->id,
                  "entrada" => NULL,
                  "salida" => $fecha,
                )
              );

            if ($insertAsistenciaEntrada) {
              $dataMensaje = array(
                'personal' => $nombrePersonal,
                'fecha' => gmdate('Y-m-d H:i:s', $fecha),
                'hora' => date('H:i:s', $fecha),
                'code' => 200,
                'status' => 'success'
              );
            } else {
              $dataMensaje = array(
                'message' => 'error en registro de salida, intente nuevamente o comuniquese a soporte',
                'code' => 200,
                'status' => 'error'
              );
            }
          }
        } else {
          $dataMensaje = array(
            'message' => 'personal no encontrado',
            'code' => 200,
            'status' => 'error'
          );
        }
      }
    } else {
      $dataMensaje = array(
        'status' => 'error',
        'code' => 200,
        'message' => 'La información que intenta registrar no es valida'
      );
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }
}