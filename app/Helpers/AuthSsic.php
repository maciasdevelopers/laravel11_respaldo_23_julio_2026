<?php
namespace App\Helpers;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Models\PermisosModelo;
//session_start();
use Session;

class AuthSsic{
    public $key;
    public function __construct(){
        $this->key = 'dtclavessecreto-9876986986986986s';
    }

    public function signupNewPass($user_token,$passPrimera,$passSegunda,$passOlder){
        $authJwt = new \App\Helpers\JwtAuth();
        $signup = false; //decetdp de autentificacion
        $queryVerif = DB::select("SELECT users.usuario_token,emp.empresa_token,emp.root_tkn FROM teci_usuarios_catalogo AS users JOIN main_empresas AS emp 
            WHERE emp.id = users.empresa AND users.usuario_token = ? AND users.acceso_password = ?",[$user_token,$passOlder]); 

        if (is_object($queryVerif) || count($queryVerif) == 1) {
            $signup = true;
        }
        //gerenera el tokern identificadoir
        if ($signup) {
            foreach($queryVerif as $user){
                $permissionLogin = DB::select("SELECT users.login_permission AS login,users.inside_ssic FROM teci_usuarios_catalogo AS users JOIN main_empresas AS emp 
                    WHERE emp.empresa_token = ? AND emp.id = users.empresa AND users.usuario_token = ? AND users.acceso_password = ?",
                [$user->empresa_token,$user->user_token,$passOlder]);
                //echo $permissionLogin[0]->id_permisos_usuario." ".$permissionLogin[0]->login;
                if ($permissionLogin[0]->login == TRUE && $permissionLogin[0]->inside_ssic == TRUE) {
                    //$user_token,$passPrimera,$passSegunda,$passOlder
                    if ($passPrimera == $passSegunda) {
                        $userUpdate = User::where([
                            'user_token' => $user_token,
                            'password' => $passOlder,
                        ])->limit(1)->update(
                            array(
                                'password' => $passSegunda,
                            )
                        );
                        
                        $validate_updt = 1;
                        if ($userUpdate) {
                            $time_entrada = time();
                            $selectEmp = DB::select("SELECT emp.id,users.id AS userId,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
                                JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                                AND users.usuario_token= ?",[$user->empresa_token,$user_token]);
                            //echo $user_token;
                            date_default_timezone_set($selectEmp[0]->zona_horaria);
    
                            $folioBitacora = DB::select("SELECT IF (max(bit_act.folio_bitacora) IS NOT NULL,
    		                    (max(bit_act.folio_bitacora)+1),1) AS folio FROM teci_bitacora_actividad AS bit_act
    		                    JOIN main_empresas AS emp WHERE bit_act.empresa = emp.id AND emp.empresa_token = ?",[$user->empresa_token]);
    		                $tokenBiracora = $authJwt->encriptarToken($folioBitacora[0]->folio.$time_entrada.'---'.rand(10,10).
    		                    '---'.'---'.'---'.'actualizar password'.$user->empresa_token.$user_token);
                            $insertBitacora = DB::table('teci_bitacora_actividad')
                            ->insert(array(
                                "token_bitacora" => $tokenBiracora,
                                "folio_bitacora" => $folioBitacora[0]->folio,
                                "fecha_bitacora" => $time_entrada,
                                "area" => '---',
                                "subarea1" => '---',
                                "subarea2" => '---',
                                "folio_relacionado" => '---',
                                "actividad" => 'actualizar password',
                                "entrada" => '---',
                                "salida" => NULL,
                                "usuario" => $selectEmp[0]->userId,
                                "empresa" => $selectEmp[0]->id,
                            ));
                            if ($insertBitacora) {
                                $histBitacora = DB::table("teci_bitacora_actividad AS histBitacora")
                                ->join("main_empresas AS emp","histBitacora.empresa","=","emp.id")
                                ->join("teci_usuarios_catalogo AS users","histBitacora.usuario","=","users.id")
                                ->where([
                                    'emp.empresa_token' => $user->empresa_token,
                                    'users.usuario_token' => $user->user_token,
                                ])->get();
                                if (count($histBitacora) == 0) {
                                    $enlaceLink = './sos_inside/actualiza_contrasena';
                                    $validate_process = 'inicial';
                                    $token = array(
                                        'user_token' => $user->user_token,
                                        'empresa_token' => $user->empresa_token,
                                    );
                                    
                                    $data_user = array(
                                        'iat' => time(),
                                        'exp' => time() + (7 * 24 * 60 * 60),
                                    );
                                } else {
                                    $infoUser = User::join("vhum_empleados_catalogo AS pers","teci_usuarios_catalogo.empleado","=","pers.id") 
                                    ->join("teci_user_settings AS sett","teci_usuarios_catalogo.id","=","sett.usuario") 
                                    ->join("sos_personas AS people","pers.empleado_name","=","people.id") 
                                    ->where([
                                        'teci_usuarios_catalogo.user_token' => $user->user_token,
                                    ])->get();

                                    foreach ($infoUser as $rUser) {
                                        $main_jerarquia = $rUser->jerarquia_main;
                                        $privilegio_crear = $rUser->privilegio_crear == TRUE ? true : false;
                                        $privilegio_editar = $rUser->privilegio_editar == TRUE ? true : false;  
                                        $privilegio_consulta = $rUser->privilegio_consulta == TRUE ? true : false;  
                                        $privilegio_elimina = $rUser->privilegio_elimina == TRUE ? true : false;  
                                        $privilegio_ver_docs = $rUser->privilegio_ver_docs == TRUE ? true : false;  
                                        $name_user_data = $authJwt->desencriptarNombres($rUser->paterno,$rUser->materno,$rUser->nombre);
                                        Session::put('name_user_data', $name_user_data);   
        
                                        if ($authJwt->desencriptar($rUser->img_perfil) == 'default-profile.png') { 
                                            $avatar = $authJwt->encriptaBase64(Storage::path('public/settings/default-profile.png'));
                                        } else { 
                                            $avatar = $authJwt->encriptaBase64(Storage::path('public/root/main_users/'.$authJwt->generar($rUser->folio_pers).'-'. 
                                                $rUser->fecha_alta_pers.'/'.$authJwt->desencriptar($rUser->img_perfil).'-profile.png'));
                                        }
        
                                        $dataEmpresa = $authJwt->primeraEmpresaVinc($user->user_token);
                                        $token = array("user_token" => $user->user_token,"empresa_token" => $dataEmpresa["empresa_token"]);
                                        $histBitacoraEmpresa = DB::table("teci_bitacora_actividad AS bita_emp")
                                        ->join("main_empresas AS emp","bita_emp.empresa","=","emp.id")
                                        ->where(["emp.empresa_token" => $user->empresa_token])->get();
        
                                        if (count($histBitacoraEmpresa) == 0) {
                                            $enlaceLink = "./sos_inside/actualiza_contrasena";
                                            $validate_process = "inicial";
                                            $data_user = array("iat" => time(),"exp" => time() + (7 * 24 * 60 * 60));
                                        } else if (count($histBitacoraEmpresa) == 1) {
                                            $enlaceLink = "./sos_inside/completa_registro";
                                            $validate_process = "profile-validate";
                                            $data_user = array("iat" => time(),"exp" => time() + (7 * 24 * 60 * 60));
                                        } else if (count($histBitacoraEmpresa) > 1) {
                                           $histBitacora = DB::table("teci_bitacora_actividad AS histBitacora")
                                            ->join("main_empresas AS emp","histBitacora.empresa","=","emp.id")
                                            ->join("teci_usuarios_catalogo AS users","histBitacora.usuario","=","users.id")
                                            ->where(["emp.empresa_token" => $user->empresa_token,"users.usuario_token" => $user->user_token])->get();
        
                                            if (count($histBitacora) == 0) {
                                                $enlaceLink = "./sos_inside/actualiza_contrasena";
                                                $validate_process = 'inicial';
                                                $data_user = array("iat" => time(),"exp" => time() + (7 * 24 * 60 * 60));
                                            } else {
                                                $enlaceLink = "./sos_inside/home";
                                                $validate_process = 'finished';
                                                $data_user = array(
                                                    "user_token" => $user->user_token,
                                                    "company_name" => $dataEmpresa["company_name"],
                                                    "zona_horaria" => $dataEmpresa["zona_horaria"],
                                                    "zona_horaria_utc" => $dataEmpresa["zona_horaria_utc"],
                                                    "codigo_pais" => $dataEmpresa["codigo_pais"],
                                                    "rfc_generico" => $dataEmpresa["rfc_generico"],
                                                    "rfc_emp" => $dataEmpresa["rfc_emp"],
                                                    "tax_id_emp" => $dataEmpresa["tax_id_emp"],
                                                    "moneda_token" => $dataEmpresa["moneda_ktn"],
                                                    "moneda_codigo" => $dataEmpresa["moneda_code"],
                                                    "moneda_name" => $dataEmpresa["moneda_name"],
                                                    "moneda_decimales" => $dataEmpresa["moneda_decimales"],
                                                    "logotypo" => $dataEmpresa["logotypo"],
                                                    "name" => $name_user_data,
                                                    "total_notificaciones" => $dataEmpresa["total_notificaciones"],
                                                    "nivel_empleado" => $rUser->nivel_empleado,
                                                    "settings_lenguaje" => $rUser->lenguaje,
                                                    "companies_vinc" => $dataEmpresa["companies_vinc"],
                                                    "main_jerarquia" => $rUser->jerarquia_main,
                                                    "main_privilegio_crear" => $privilegio_crear,
                                                    "main_privilegio_editar" => $privilegio_editar,
                                                    "main_privilegio_consulta" => $privilegio_consulta,
                                                    "main_privilegio_elimina" => $privilegio_elimina,
                                                    "main_privilegio_ver_docs" => $privilegio_ver_docs,
                                                    "conf_ingresos" => $dataEmpresa["conf_ingresos"],
                                                    "conf_egresos" => $dataEmpresa["conf_egresos"],
                                                    "conf_finanzas" => $dataEmpresa["conf_finanzas"],
                                                    "conf_vhumano" => $dataEmpresa["conf_vhumano"],
                                                    "conf_contabilidad" => $dataEmpresa["conf_contabilidad"],
                                                    "conf_teci" => $dataEmpresa["conf_teci"],
        
                                                    "area" => $dataEmpresa["area"],
                                                    "areasettings" => $dataEmpresa["areasettings"],
                                                    "cargo" => $dataEmpresa["cargo"],
                                                    "avatar" => $avatar,
                                                    "iat" => time(),
                                                    "exp" => time() + (7 * 24 * 60 * 60),
                                                );
                                            }
                                        }
        
                                        $jwt = JWT::encode($token,$this->key,'HS256');
                                        $jwt_data_user = JWT::encode($data_user,$this->key,'HS256');
                                        $decodeTkn = JWT::decode($jwt_data_user,$this->key,['HS256']);
                        
                                        $dataMensaje = array(
                                            "status" => "success",
                                            "code" => 200,
                                            "modulo_destino" => $enlaceLink,
                                            "validate_process" => $validate_process,
                                            "modulo_title" => "Sistema de Sinergia Integral Corporativa (SSIC)",
                                            "large_token_access" => $jwt,
                                            "modulo_code" => "bEIxeFFKY2k4RnFEbWtnWDE5c1dKMGN5TFUwSW5EY0pTditvM3drV3FzTnFCZVhZN3A5aDREM3ZLRHF1YjFGUmNhY1pacDJDS3JsTm9RSXF6SkVTS2c9PTo6MTIzNDU2NzgxMjM0NTY3OA==",
                                            "settings_privilegio_crear" => $privilegio_crear,
                                            "settings_privilegio_editar" => $privilegio_editar,
                                            "settings_privilegio_consulta" => $privilegio_consulta,
                                            "settings_privilegio_elimina" => $privilegio_elimina,
                                            "settings_privilegio_ver_docs" => $privilegio_ver_docs,
                                            "dataUsers" => $decodeTkn,
                                            "lenguaje" => $rUser->lenguaje,
                                        );
                                    }
                                }
                            } else {
                                $dataMensaje = array(
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'contraseña no actualizada',
                                );
                            }

                            //$this->moduleSSICSignup($codigo_acceso,$password,"","");

                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'contraseña no actualizada',
                            );
                        }
    
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'las contraseñas no coinciden',
                        );
                    }
    
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 404,
                        'message' => 'Acceso no permitido'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'los datos no son correctos',
                'passOlder' => $passOlder
            );

        }
        return $dataMensaje;
    }
    
    public function signupMobileSsic($codigo_acceso,$password,$tipo_app,$actividad_app){
        $authJwt = new \App\Helpers\JwtAuth();
        //buscar al uaurios con sus credencizales {"email":"mac24her@gmail.com","pass":"satellite2424a,"}
        $user = User::join("main_empresas","users.empresa","=","main_empresas.id")
        ->where([
            'users.codigo_acceso' => $codigo_acceso,
            'users.acceso_password' => $password,
        ])
        ->orwhere([
            'users.username' => $codigo_acceso,
            'users.acceso_password' => $password,
        ])->first();
        //return $user;
        // comprobar si son correctasas + Opciones Textos completos id_personal c_token
        $signup = false; //decetdp de autentificacion
        if (is_object($user)) {
            $signup = true;
        }
        //gerenera el tokern identificadoir
        if ($signup) {
            $permissionLogin = DB::select("SELECT permiso.login FROM permiso_login AS permiso
                JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE permiso.empresa = emp.id
                AND emp.empresa_token = ? AND permiso.clasificacion = 2 AND
                (permiso.empleado is not null AND permiso.uprincipal is null AND permiso.empleado = pers.id
                    AND pers.id = users.empleado AND users.codigo_acceso = ? AND users.acceso_password = ?)
                OR permiso.clasificacion = 1 AND (permiso.uprincipal is not null AND permiso.empleado is null
                    AND permiso.uprincipal = users.id AND users.codigo_acceso = ? AND users.acceso_password = ?)",
                [$user->empresa_token,$codigo_acceso,$password,$codigo_acceso,$password]);
            if ($permissionLogin[0]->login == TRUE) {
                $time_entrada = time();
                $selectEmp = DB::select("SELECT emp.id,users.id AS userId,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.empleado = pers.id
                    AND pers.id = users.empleado AND users.usuario_token= ?",[$user->empresa_token,$user->user_token]);
                //echo $user_token;
                date_default_timezone_set($selectEmp[0]->zona_horaria);

                $folioBitacora = DB::select("SELECT IF (max(bit_act.folio_bitacora) IS NOT NULL,
		        (max(bit_act.folio_bitacora)+1),1) AS folio FROM teci_bitacora_actividad AS bit_act
		        JOIN main_empresas AS emp WHERE bit_act.empresa = emp.id AND emp.empresa_token = ?",[$user->empresa_token]);

		        $tokenBiracora = $authJwt->encriptarToken($folioBitacora[0]->folio.$time_entrada.$user->empresa_token.$codigo_acceso.$password.$user->user_token,$tipo_app,$actividad_app);
                $insertBitacora = DB::table('teci_bitacora_actividad')
                ->insert(array(
                    "token_bitacora" => $tokenBiracora,
                    "folio_bitacora" => $folioBitacora[0]->folio,
                    "fecha_bitacora" => $time_entrada,
                    "vhum_personal_area AS area" => NULL,
                    "subarea1" => NULL,
                    "subarea2" => NULL,
                    "folio_relacionado" => NULL,
                    "actividad" => $actividad_app,
                    "entrada" => $time_entrada,
                    "salida" => NULL,
                    "usuario" => $selectEmp[0]->userId,
                    "empresa" => $selectEmp[0]->id,
                ));

                $dataEmpresa = DB::select("SELECT emp.zona_horaria,emp.zona_horaria_utc,ispa.codigo_pais,
                    people.materno,people.paterno,people.nombre,people.denominacion_rs,people.rfc_generico,
                    people.rfc,people.tax_id,people.img_perfil FROM main_empresas AS emp
                    JOIN sos_personas AS people JOIN teci_pais AS ispa WHERE people.nacionalidad = ispa.id
                    AND people.id = emp.persona AND emp.empresa_token = ?",[$user->empresa_token]);

                if ($dataEmpresa[0]->denominacion_rs == '') {
                    $nombreEmpresa = $authJwt->desencriptar($dataEmpresa[0]->paterno)." ".$authJwt->desencriptar($dataEmpresa[0]->materno)." ".$authJwt->desencriptar($dataEmpresa[0]->nombre);
                } else {
                    $nombreEmpresa = $authJwt->desencriptar($dataEmpresa[0]->denominacion_rs);
                }
                $rfc_generico = $dataEmpresa[0]->rfc_generico;

                if ($dataEmpresa[0]->rfc != NULL) {
                    $rfc_prov = $authJwt->desencriptar($dataEmpresa[0]->rfc);
                } else {
                    $rfc_prov = '---';
                }

                if ($dataEmpresa[0]->tax_id != NULL) {
                    $tax_id_prov = $authJwt->desencriptar($dataEmpresa[0]->tax_id);
                } else {
                    $tax_id_prov = '---';
                }
                //echo $authJwt->desencriptar($dataEmpresa[0]->img_perfil);
                $logoTipo = $authJwt->encriptaBase64(Storage::path('public/root/'.$user->root_tkn.'/0007-core/'.$authJwt->desencriptar($dataEmpresa[0]->img_perfil)));

                $alertaList = DB::select("SELECT * FROM teci_notificaciones AS alert INNER JOIN main_empresas AS emp 
                    ON alert.empresa = emp.id INNER JOIN vhum_personal AS receptor ON alert.receptor = receptor.id 
                    INNER JOIN teci_usuarios_catalogo AS users ON receptor.usuario = users.id 
                    WHERE alert.status_recibe = FALSE AND alert.status_delete = TRUE and emp.empresa_token = ? 
                    AND users.usuario_token = ?
                    AND ((alert.proyecto IS NOT NULL AND alert.area IS NULL AND alert.subarea IS NULL 
                        AND	alert.producto IS NULL AND alert.servicio IS NULL AND alert.clave_serv IS NULL 
                        AND	alert.cliente IS NULL AND alert.proveedor IS NULL 
                        AND alert.proyecto IN (SELECT id FROM module_proyectos)) 
                        OR (alert.proyecto IS NULL AND alert.area IS NOT NULL AND alert.subarea IS NOT NULL 
                        AND	alert.producto IS NOT NULL AND alert.servicio IS NOT NULL 
                        AND alert.clave_serv IS NOT NULL AND alert.cliente IS NOT NULL 
                        AND alert.proveedor IS NOT NULL)) ORDER BY alert.id DESC",
                [$user->empresa_token,$user->user_token]);

                $infoUser = User::join("tipo_usuario","users.tipo","=","tipo_usuario.id_tipo")
                ->join("vhum_empleados_catalogo AS pers","users.empleado","=","pers.id")
                ->join("teci_user_settings AS sett","pers.id","=","sett.personal")
                ->join("vhum_personal_area AS area","pers.area","=","area.id")
                ->join("vhum_personal_cargo AS cargo","pers.cargo","=","cargo.id")
                ->join("sos_personas AS people","pers.empleado_name","=","people.id")
                ->join("main_empresa_usuario AS empuser","pers.id","=","empuser.empleado")
                ->join("main_empresas","empuser.empresa","=","main_empresas.id")
                ->where([
                    'codigo_acceso' => $codigo_acceso,
                    'password' => $password,
                ])->get();
                $areadb = $authJwt->desencriptar($infoUser[0]->areaemp);
                if ($infoUser[0]->areaemp == 'MkljUG5ya01tZUNqYjlrNkRaZ0ljQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                    $areasettings = 'airneg';
                } else if ($infoUser[0]->areaemp == 'OHNPcXphaG5ac3dFVFVtZW5UT3dRdz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                    $areasettings = 'aerger';
                } else if ($infoUser[0]->areaemp == 'akVjZ2ZyVzBJM3Q2QmYvbE96VmFoQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                    $areasettings = 'atseer';
                } else if ($infoUser[0]->areaemp == 'MjlOOWJJZDYvU2NOSXE4TDlNbCt1Zz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                    $areasettings = 'avsleh';
                } else if ($infoUser[0]->areaemp == 'QnZUL2pXcytLTnN3RlRDaWZWaUkwUHd6elVuU3dDSEl0UDFYak9ZSG1WWT06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
                   if ($infoUser[0]->empresa_token =    'bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kv  YjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==') {
                        $areasettings = 'aprtsieif';
                    } else {
                        $areasettings = 'asctsieif';
                    }
                } else if ($infoUser[0]->areaemp == 'U0FyNDFBeWVpZ3V4d3ZTQklNZjBldmFwY3BHZUkvSHF3RmxkVjZqRTM3ST06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
                    $areasettings = 'aAsdemg';
                }
                //echo $authJwt->encriptar('default-profile.png');exit;
                //echo $authJwt->generar($infoUser[0]->folio_pers)." ".$authJwt->encriptarRegistro($infoUser[0]->empresa_token);
                if ($authJwt->desencriptar($infoUser[0]->img_perfil) == 'default-profile.png') {
                    $avatar = 'https://backend.sos-mexico.com.mx/settings/'.$authJwt->desencriptar($infoUser[0]->img_perfil);
                } else {
                    $avatar = $authJwt->encriptaBase64(Storage::path('public/root/'.$infoUser[0]->root_tkn.
                    '/0004-vhm/catalogos/employees/'.$authJwt->generar($infoUser[0]->folio_pers).'-'.
                    $infoUser[0]->fecha_alta_pers.'/'.$authJwt->desencriptar($infoUser[0]->img_perfil).'-profile.png'));
                }
                $array_user = array(
                    'user_token' => $user->user_token,
                    'empresa_token' => $user->empresa_token,
                );
                $user_token = JWT::encode($array_user,$this->key,'HS256');
                
                $data_user = array(
                    'company_name' => $nombreEmpresa,
                    'zona_horaria' => $dataEmpresa[0]->zona_horaria,
                    'zona_horaria_utc' => $dataEmpresa[0]->zona_horaria_utc,
                    'codigo_pais' => $dataEmpresa[0]->codigo_pais,
                    'rfc_generico' => $rfc_generico,
                    'rfc_prov' => $rfc_prov,
                    'tax_id_prov' => $tax_id_prov,
                    'logotypo' => $logoTipo,
                    'name' => ucwords($authJwt->desencriptar($infoUser[0]->paterno)." ".$authJwt->desencriptar($infoUser[0]->materno)." ".$authJwt->desencriptar($infoUser[0]->nombre)),
                    'jerarquia' => $infoUser[0]->jerarquia,
                    'area' => ucfirst(strtolower($areadb)),
                    'areasettings' => $areasettings,
                    'cargo' => ucfirst(strtolower($authJwt->desencriptar($infoUser[0]->cargo))),
                    'total_notificaciones' => count($alertaList),
                    'avatar' => $avatar,
                    'iat' => time(),
                    'exp' => time() + (7 * 24 * 60 * 60),
                );
                
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'user_token' => $user_token,
                    'data_user' => $data_user,
                    'total_notificaciones' => count($alertaList),
                    'validate_process' => $tipo_app,
                    'lenguaje' => $infoUser[0]->lenguaje,
                );

            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Acceso no permitido'
                );
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Código de acceso o contraseña incorrectos',
                'password' => $codigo_acceso,
            );

        }

        return $dataMensaje;
    }
    
    public function signupReload($user_token){
        $usuario = $authJwt->checkToken($user_token,true);
        //echo $usuario->user_token;//buscar al uaurios con sus credencizales {"email":"mac24her@gmail.com","pass":"satellite2424a,"}
        $user = User::join("main_empresas AS emp","users.empresa","=","emp.id")
        ->where([
            'emp.empresa_token' => $usuario->empresa_token,
            'users.usuario_token' => $usuario->user_token,
        ])->first();
        //return $user;
        // comprobar si son correctasas + Opciones Textos completos id_personal c_token
        $signup = false; //decetdp de autentificacion
        if (is_object($user)) {
            $signup = true;
        }
        //gerenera el tokern identificadoir
        if ($signup) {
            $permissionLogin = DB::select("SELECT permiso.login FROM permiso_login AS permiso
                JOIN main_empresas AS emp JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE permiso.empresa = emp.id
                AND emp.empresa_token = ? AND permiso.clasificacion = 2 AND
                (permiso.empleado is not null AND permiso.uprincipal is null AND permiso.empleado = pers.id
                    AND pers.id = users.empleado AND users.usuario_token = ?)
                OR permiso.clasificacion = 1 AND (permiso.uprincipal is not null AND permiso.empleado is null
                    AND permiso.uprincipal = users.id AND users.usuario_token = ?)",
                [$user->empresa_token,$user->user_token,$user->user_token]);
            if ($permissionLogin[0]->login == TRUE) {
                $histBitacora = DB::table("teci_bitacora_actividad AS histBitacora")
                ->join("main_empresas AS emp","histBitacora.empresa","=","emp.id")
                ->join("teci_usuarios_catalogo AS users","histBitacora.usuario","=","users.id")
                ->where([
                    'emp.empresa_token' => $user->empresa_token,
                    'users.usuario_token' => $user->user_token,
                ])->get();
                if (count($histBitacora) == 0) {
                    $enlaceLink = 'administracion_actualiza_contrasena';
                } else if (count($histBitacora) == 1) {
                    $histBitacoraSinUser = DB::table("teci_bitacora_actividad AS histBitacora")
                    ->join("main_empresas AS emp","histBitacora.empresa","=","emp.id")
                    ->where([
                        'emp.empresa_token' => $user->empresa_token,
                    ])->get();
                    if (count($histBitacoraSinUser) == 1) {
                        $enlaceLink = 'administracion_completa_registro';
                        $token = array(
                            'user_token' => $user->user_token,
                            'empresa_token' => $user->empresa_token,
                            'iat' => time(),
                            'exp' => time() + (7 * 24 * 60 * 60),
                        );
                    } else if (count($histBitacoraSinUser) > 1) {
                        $enlaceLink = 'sos_inside/home';
                    }
                } else {
                    $enlaceLink = 'sos_inside/home';
                }
                    $dataMensaje = array(
                        'status' => 'success',
                        'code' => 200,
                        'enlaceLink' => $enlaceLink,
                    );

            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Acceso no permitido'
                );
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Código de acceso o contraseña incorrectos',
            );

        }

        return $dataMensaje;
    }

    public function resetPassFunction($user_token,$passPrimera,$passSegunda){
        $authJwt = new \App\Helpers\JwtAuth();
        //buscar al uaurios con sus credencizales {"email":"mac24her@gmail.com","pass":"satellite2424a,"}
        $user = User::join("main_empresas AS emp","teci_usuarios_catalogo.empresa","=","emp.id")
        ->where(['teci_usuarios_catalogo.usuario_token' => $user_token])->first();
        $signup = false; //decetdp de autentificacion
        if (is_object($user)) {
            $signup = true;
        }
        //gerenera el tokern identificadoir
        if ($signup) {
            if ($passPrimera == $passSegunda) {
                $userUpdate = User::where(['usuario_token' => $user_token,])->limit(1)->update(array('acceso_password' => $passSegunda));
                if ($userUpdate) {
                    $time_entrada = time();
                    $selectEmp = DB::select("SELECT emp.id,users.id AS userId,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                        AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",[$user->empresa_token,$user_token]);
                    //echo $user_token;
                    date_default_timezone_set($selectEmp[0]->zona_horaria);

                    $folioBitacora = DB::select("SELECT IF (max(bit_act.folio_bitacora) IS NOT NULL,
	                    (max(bit_act.folio_bitacora)+1),1) AS folio FROM teci_bitacora_actividad AS bit_act
	                    JOIN main_empresas AS emp WHERE bit_act.empresa = emp.id AND emp.empresa_token = ?",[$user->empresa_token]);
	                $tokenBiracora = $authJwt->encriptarToken($folioBitacora[0]->folio.$time_entrada.'---'.rand(10,10).
		                '---'.'---'.'---'.'actualizar password'.$user->empresa_token.$user_token);
                    $insertBitacora = DB::table('teci_bitacora_actividad')
                    ->insert(array(
                        "token_bitacora" => $tokenBiracora,
                        "folio_bitacora" => $folioBitacora[0]->folio,
                        "fecha_bitacora" => $time_entrada,
                        "area" => '---',
                        "subarea1" => '---',
                        "subarea2" => '---',
                        "folio_relacionado" => '---',
                        "actividad" => 'actualizar password',
                        "entrada" => '---',
                        "salida" => NULL,
                        "usuario" => $selectEmp[0]->userId,
                        "empresa" => $selectEmp[0]->id,
                    ));
                        
                    if ($insertBitacora) {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'su contraseña ha sido actualizada',
                        );
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'contraseña no actualizada',
                        );
                    }
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'contraseña no actualizada',
                    );
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'las contraseñas no coinciden',
                );
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'los datos no son correctos',
            );
        }
        return $dataMensaje;
    }

    public function signupAcessEnableForce($area,$subarea1,$subarea2,$folioRelacionado,$company,$user_token,$codeAccessForce,$actividad){
        $authJwt = new \App\Helpers\JwtAuth();
        $user = User::join("main_empresas AS emp","users.empresa","=","emp.id")
        ->where([
            'emp.empresa_token' => $company,
            'users.usuario_token' => $user_token,
            'users.special_token_access' => $codeAccessForce,
        ])->first();
        //return $user;
        // comprobar si son correctasas + Opciones Textos completos id_personal c_token
        $signup = false; //decetdp de autentificacion
        if (is_object($user)) {
            $signup = true;
        }
        //gerenera el tokern identificadoir
        if ($signup) {
            $time_entrada = time();
            $selectEmp = DB::select("SELECT emp.id,users.id AS userId,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp
                JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                AND emp.id = empuser.empresa AND empuser.empleado = pers.id
                AND pers.id = users.empleado AND users.usuario_token= ?",[$company,$user_token]);
            //echo $user_token;
            date_default_timezone_set($selectEmp[0]->zona_horaria);

            $folioBitacora = DB::select("SELECT IF (max(bit_act.folio_bitacora) IS NOT NULL,
		        (max(bit_act.folio_bitacora)+1),1) AS folio FROM teci_bitacora_actividad AS bit_act
		        JOIN main_empresas AS emp WHERE bit_act.empresa = emp.id AND emp.empresa_token = ?",[$company]);

		    $tokenBiracora = $authJwt->encriptarToken($folioBitacora[0]->folio.$time_entrada.$area.rand(10,10).
		        $subarea1.$subarea2.$folioRelacionado.$actividad.$company.$user_token);
            $insertBitacora = DB::table('teci_bitacora_actividad')
            ->insert(array(
                "token_bitacora" => $tokenBiracora,
                "folio_bitacora" => $folioBitacora[0]->folio,
                "fecha_bitacora" => $time_entrada,
                "vhum_personal_area AS area" => $area,
                "subarea1" => $subarea1,
                "subarea2" => $subarea2,
                "folio_relacionado" => $folioRelacionado,
                "actividad" => $actividad,
                "entrada" => $time_entrada,
                "salida" => NULL,
                "usuario" => $selectEmp[0]->userId,
                "empresa" => $selectEmp[0]->id,
            ));
            $dataMensaje = array(
                'status' => 'success',
                'code' => 200,
                "time_entrada" => date('d-m-Y H:i:s',$time_entrada),
                'message' => 'acceso correcto en la fecha: '.date('d-m-Y H:i:s',$time_entrada),
                'verifAccessCode' => $tokenBiracora,
            );
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 404,
                'message' => 'Código de acceso incorrecto',
            );

        }

        return $dataMensaje;
    }

}