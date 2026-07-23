<?php
namespace App\Helpers;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Models\PermisosModelo;
//session_start();
use Session;

class AuthLogistica{
    public $key;
    public function __construct(){
        $this->key = 'dtclavessecreto-9876986986986986s';
    }

    public function moduleLoginLogistica($codigo_acceso,$password,$firebase_token_movil,$firebase_token_web){
        $authJwt = new \App\Helpers\JwtAuth();
        $signup = false; 
        
        $queryModulo = DB::select("SELECT mantenimiento,acceso FROM sos_modulos_sistemas WHERE modulo = 'logistica'"); 
        foreach ($queryModulo AS $rMod){
            if ($rMod->mantenimiento == FALSE && $rMod->acceso == TRUE) {
                $queryLogin = DB::select("SELECT users.user_token,emp.emp_token,emp.root_tkn FROM main_usuarios AS users JOIN main_empresas AS emp 
                    WHERE emp.id = users.empresa AND (users.codigo_acceso = ? OR users.username = ?) 
                    AND users.password = ?",[$codigo_acceso,$codigo_acceso,$password]); 
        
                if (is_object($queryLogin) || count($queryLogin) == 1) {
                    $signup = true;
                }
                //validación de usuario
                if ($signup) {
                    //preguntando sobre los permisos del usuario
                    foreach($queryLogin as $user){
                        //echo $user->emp_token;
                        /*$permissionLogin = DB::select("SELECT users.login_permission AS login,users.outside_logistica FROM main_usuarios AS users JOIN main_empresas AS emp 
                            WHERE emp.id = users.empresa AND (users.codigo_acceso = ? OR users.username = ?) 
                            AND users.password = ?",[$codigo_acceso,$codigo_acceso,$password]);*/
                            
                        $permissionLogin = DB::select("SELECT login_permission AS login,outside_logistica FROM main_usuarios WHERE (codigo_acceso = ? OR username = ?) AND password = ?",
                            [$codigo_acceso,$codigo_acceso,$password]);
                            
                        if ($permissionLogin[0]->login == TRUE && $permissionLogin[0]->outside_logistica == TRUE) {
                            
                            if ($firebase_token_movil != ""){
                                $infoUser = User::where(['users.user_token' => $user->user_token])
                                ->limit(1)->update(
                                    array(
                                        'users.token_dispositivo_movil' => $firebase_token_movil
                                    )
                                );
                            }
                            
                            if ($firebase_token_web != ""){
                                $infoUser = User::where(['users.user_token' => $user->user_token])
                                ->limit(1)->update(
                                    array(
                                        'users.token_dispositivo_web' => $firebase_token_web
                                    )
                                );
                            }
                            
                            $infoUser = User::join("vhum_personal AS pers","users.id","=","pers.usuario")
                            ->join("teci_user_settings AS sett","users.id","=","sett.usuario")
                            ->join("vhum_personal_area AS area","pers.area","=","area.id")
                            ->join("vhum_personal_cargo AS cargo","pers.cargo","=","cargo.id")
                            ->join("sos_personas AS people","pers.personal","=","people.id")
                            ->join("main_empresapersonal AS emppers","pers.id","=","emppers.personal")
                            ->join("main_empresas","emppers.empresa","=","main_empresas.id")
                            ->where([
                                'users.codigo_acceso' => $codigo_acceso,
                                'users.password' => $password,
                            ])
                            ->orwhere([
                                'users.username' => $codigo_acceso,
                                'users.password' => $password,
                            ])->get();
                            
                            if ($infoUser[0]->privilegio_crear == TRUE){
                                $privilegio_crear = true;    
                            } else {
                                $privilegio_crear = false;
                            }
                            
                            if ($infoUser[0]->privilegio_editar == TRUE){
                                $privilegio_editar = true;    
                            } else {
                                $privilegio_editar = false;
                            }                            
                            
                            if ($infoUser[0]->privilegio_consulta == TRUE){
                                $privilegio_consulta = true;    
                            } else {
                                $privilegio_consulta = false;
                            }
                            
                            if ($infoUser[0]->privilegio_elimina == TRUE){
                                $privilegio_elimina = true;    
                            } else {
                                $privilegio_elimina = false;
                            }
                            
                            if ($infoUser[0]->privilegio_ver_docs == TRUE){
                                $privilegio_ver_docs = true;    
                            } else {
                                $privilegio_ver_docs = false;
                            }
                            
                            $histBitacora = DB::table("teci_bitacora_actividad AS histBitacora")
                            ->join("main_empresas AS emp","histBitacora.empresa","=","emp.id")
                            ->join("main_usuarios AS users","histBitacora.usuario","=","users.id")
                            ->where([
                                'emp.emp_token' => $user->emp_token,
                                'users.user_token' => $user->user_token,
                            ])->get();
                            if (count($histBitacora) == 0) {
                                $enlaceLink = './gestion_de_proyectos/update_pass';
                                $validate_process = 'inicial';
                                $token = array(
                                    'user_token' => $user->user_token,
                                    'emp_token' => $user->emp_token,
                                );
                                
                                $data_user = array(
                                    'iat' => time(),
                                    'exp' => time() + (7 * 24 * 60 * 60),
                                );
                            } else {
                                //$enlaceLink = './outside_logistica/home';
                                //$enlaceLink = './outside_logistica/select_company';
                                //$validate_process = 'finished';
                                
                                if ($user->emp_token == "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==") {
                                    $enlaceLink = "./outside_logistica/dashboard_principal";
                                    $validate_process = "principal";
                                } else if ($user->emp_token == "EmP2KHiqtgbjHjguyh8yriufuy8f6iuyxjgdjhhfdjyfjbkdERG2KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qhBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIA==") {
                                    $enlaceLink = "./outside_logistica/dashboard_logistica";
                                    $validate_process = "logistica";
                                } else if ($user->emp_token == "EmP3KJkhjgkjgKJHJKLkjgbxgljhd76e97e97tsw756eiyte97itditydbkdERG3KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUt1qcXVsK05BQVBHNytPaG9nQ2RCazANTY3OA==") {
                                    $enlaceLink = "./outside_logistica/dashboard_produccion";
                                    $validate_process = "produccion";
                                }
                                            
                                
                                $dataEmpresa = DB::select("SELECT emp.zona_horaria,emp.zona_horaria_utc,ispa.codigo_pais,
                                    people.materno,people.paterno,people.nombre,people.denominacion_rs,people.rfc_generico,
                                    people.rfc,people.tax_id,people.img_perfil FROM main_empresas AS emp
                                    JOIN sos_personas AS people JOIN teci_pais AS ispa WHERE people.nacionalidad = ispa.id
                                    AND people.id = emp.persona AND emp.emp_token = ?",[$user->emp_token]);
                                if ($dataEmpresa[0]->denominacion_rs == '') {
                                    $nombreEmpresa = $authJwt->desencriptar($dataEmpresa[0]->paterno)." ".$authJwt->desencriptar($dataEmpresa[0]->materno)." ".$authJwt->desencriptar($dataEmpresa[0]->nombre);
                                } else {
                                    $nombreEmpresa = $authJwt->desencriptar($dataEmpresa[0]->denominacion_rs);
                                }
                                $rfc_generico = $dataEmpresa[0]->rfc_generico;
        
                                if ($dataEmpresa[0]->rfc != NULL) {
                                    $rfc_emp = $authJwt->desencriptar($dataEmpresa[0]->rfc);
                                } else {
                                    $rfc_emp = '---';
                                }
        
                                if ($dataEmpresa[0]->tax_id != NULL) {
                                    $tax_id_emp = $authJwt->desencriptar($dataEmpresa[0]->tax_id);
                                } else {
                                    $tax_id_emp = '---';
                                }
        
                                //echo $authJwt->desencriptar($dataEmpresa[0]->img_perfil);
                                $logoTipo = $authJwt->encriptaBase64(Storage::path('public/root/'.$user->root_tkn.'/0007-core/'.$authJwt->desencriptar($dataEmpresa[0]->img_perfil)));
        
                                $alertaList = DB::select("SELECT * FROM teci_notificaciones AS alert INNER JOIN main_empresas AS emp 
                                    ON alert.empresa = emp.id INNER JOIN vhum_personal AS receptor ON alert.receptor = receptor.id 
                                    INNER JOIN main_usuarios AS users ON receptor.usuario = users.id 
                                    WHERE alert.status_recibe = FALSE AND alert.status_delete = TRUE and emp.emp_token = ? 
                                    AND users.user_token = ?
                                    AND ((alert.proyecto IS NOT NULL AND alert.area IS NULL AND alert.subarea IS NULL 
                                        AND	alert.producto IS NULL AND alert.servicio IS NULL AND alert.clave_serv IS NULL 
                                        AND	alert.cliente IS NULL AND alert.proveedor IS NULL 
                                        AND alert.proyecto IN (SELECT id FROM module_proyectos)) 
                                        OR (alert.proyecto IS NULL AND alert.area IS NOT NULL AND alert.subarea IS NOT NULL 
                                        AND	alert.producto IS NOT NULL AND alert.servicio IS NOT NULL 
                                        AND alert.clave_serv IS NOT NULL AND alert.cliente IS NOT NULL 
                                        AND alert.proveedor IS NOT NULL)) ORDER BY alert.id DESC",
                                [$user->emp_token,$user->user_token]);
                                
                                $areadb = $authJwt->desencriptar($infoUser[0]->areaemp);
                                if ($infoUser[0]->areaemp == 'MkljUG5ya01tZUNqYjlrNkRaZ0ljQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                                    $areasettings = 'airneg';
                                } else if ($infoUser[0]->areaemp == 'OHNPcXphaG5ac3dFVFVtZW5UT3dRdz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                                    $areasettings = 'aerger';
                                } else if ($infoUser[0]->areaemp == 'akVjZ2ZyVzBJM3Q2QmYvbE96VmFoQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                                    $areasettings = 'atseer';
                                } else if ($infoUser[0]->areaemp == 'MjlOOWJJZDYvU2NOSXE4TDlNbCt1Zz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                                    $areasettings = 'avsleh';
                                } else if ($infoUser[0]->areaemp == 'NUxVVURJNXp2OGNlUFpCUm52dVJsdz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                                    $areasettings = 'acsleo';
                                } else if ($infoUser[0]->areaemp == 'QnZUL2pXcytLTnN3RlRDaWZWaUkwUHd6elVuU3dDSEl0UDFYak9ZSG1WWT06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
                                   if ($infoUser[0]->emp_token =    'bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kv  YjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==') {
                                        $areasettings = 'aprtsieif';
                                    } else {
                                        $areasettings = 'asctsieif';
                                    }
                                } else if ($infoUser[0]->areaemp == 'U0FyNDFBeWVpZ3V4d3ZTQklNZjBldmFwY3BHZUkvSHF3RmxkVjZqRTM3ST06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
                                    $areasettings = 'aasdemg';
                                }
        
                                if ($authJwt->desencriptar($infoUser[0]->img_perfil) == 'default-profile.png') {
                                    $avatar = $authJwt->encriptaBase64(Storage::path('public/settings/default-profile.png'));
                                } else {
                                    $avatar = $authJwt->encriptaBase64(Storage::path('public/root/'.$infoUser[0]->root_tkn.
                                    '/0004-vhm/catalogos/employees/'.$authJwt->generar($infoUser[0]->folio_pers).'-'.
                                    $infoUser[0]->fecha_alta_pers.'/'.$authJwt->desencriptar($infoUser[0]->img_perfil).'-profile.png'));
                                }
                                
                                $name_user_data = ucwords($authJwt->desencriptar($infoUser[0]->paterno)." ".
                                    $authJwt->desencriptar($infoUser[0]->materno)." ".$authJwt->desencriptar($infoUser[0]->nombre));
                                Session::put('name_user_data', $name_user_data);
                                
                                $token = array(
                                    'user_token' => $user->user_token,
                                    'emp_token' => $user->emp_token,
                                );
                                
                                $data_user = array(
                                    "user_token" => $user->user_token,
                                    "company_name" => $nombreEmpresa,
                                    "zona_horaria" => $dataEmpresa[0]->zona_horaria,
                                    "zona_horaria_utc" => $dataEmpresa[0]->zona_horaria_utc,
                                    "codigo_pais" => $dataEmpresa[0]->codigo_pais,
                                    "rfc_generico" => $rfc_generico,
                                    "rfc_emp" => $rfc_emp,
                                    "tax_id_emp" => $tax_id_emp,
                                    "logotypo" => $logoTipo,
                                    "name" => $name_user_data,
                                    "total_notificaciones" => count($alertaList),
                                    "jerarquia" => $infoUser[0]->jerarquia,
                                    
                                    "settings_lenguaje" => $infoUser[0]->lenguaje,
                                    "settings_privilegio_crear" => $privilegio_crear,
                                    "settings_privilegio_editar" => $privilegio_editar,
                                    "settings_privilegio_consulta" => $privilegio_consulta,
                                    "settings_privilegio_elimina" => $privilegio_elimina,
                                    "settings_privilegio_ver_docs" => $privilegio_ver_docs,
                                    
                                    "area" => ucfirst(strtolower($areadb)),
                                    "areasettings" => $areasettings,
                                    "cargo" => ucfirst(strtolower($authJwt->desencriptar($infoUser[0]->cargo))),
                                    "avatar" => $avatar,
                                    "iat" => time(),
                                    "exp" => time() + (7 * 24 * 60 * 60),
                                );
                            }
                            
                            //echo json_encode($sistem_access_list);
                            //exit;
            
                            $jwt = JWT::encode($token,$this->key,'HS256');
                            $jwt_data_user = JWT::encode($data_user,$this->key,'HS256');
                            $decodeTkn = JWT::decode($jwt_data_user,$this->key,['HS256']);
            
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "modulo_destino" => $enlaceLink,
                                "validate_process" => $validate_process,
                                "modulo_title" => "Logística",
                                "large_token_access" => $jwt,
                                "modulo_code" => "ZnpQZzIxbGxORytFWW5uQ3A5WWNhd0hZbFBteGppc1Z5VzNkZE9SM0I4TT06OjEyMzQ1Njc4MTIzNDU2Nzg",
                                "settings_privilegio_crear" => $privilegio_crear,
                                "settings_privilegio_editar" => $privilegio_editar,
                                "settings_privilegio_consulta" => $privilegio_consulta,
                                "settings_privilegio_elimina" => $privilegio_elimina,
                                "settings_privilegio_ver_docs" => $privilegio_ver_docs,
                                "dataUsers" => $decodeTkn,
                                "lenguaje" => $infoUser[0]->lenguaje,
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 404,
                                "message" => "Acceso no permitido, usuario bloqueado"
                            );
                        }
                    }
                } else {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 404,
                        'message' => 'Código de acceso o contraseña incorrectos',
                        'password' => $password,
                    );
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Acceso no permitido, módulo en construcción o en mantenimiento',
                );
            }
        }
        return $dataMensaje;
    }

    public function signupNewPass($user_token,$passPrimera,$passSegunda,$passOlder){
        $authJwt = new \App\Helpers\JwtAuth();
        $signup = false; //decetdp de autentificacion
        $queryVerif = DB::select("SELECT users.user_token,emp.emp_token,emp.root_tkn FROM main_usuarios AS users JOIN main_empresas AS emp 
            WHERE emp.id = users.empresa AND users.user_token = ? AND users.password = ?",[$user_token,$passOlder]); 

        if (is_object($queryVerif) || count($queryVerif) == 1) {
            $signup = true;
        }
        //gerenera el tokern identificadoir
        if ($signup) {
            foreach($queryVerif as $user){
                $permissionLogin = DB::select("SELECT users.login_permission AS login,users.outside_logistica FROM main_usuarios AS users JOIN main_empresas AS emp 
                    WHERE emp.emp_token = ? AND emp.id = users.empresa AND users.user_token = ? AND users.password = ?",
                [$user->emp_token,$user->user_token,$passOlder]);
                //echo $permissionLogin[0]->id_permisos_usuario." ".$permissionLogin[0]->login;
                if ($permissionLogin[0]->login == TRUE && $permissionLogin[0]->outside_logistica == TRUE) {
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
                            $selectEmp = DB::select("SELECT emp.id,users.id AS userId,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp
                                JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE emp.emp_token = ?
                                AND emp.id = emppers.empresa AND emppers.personal = pers.id
                                AND pers.usuario = users.id AND users.user_token= ?",[$user->emp_token,$user_token]);
                            //echo $user_token;
                            date_default_timezone_set($selectEmp[0]->zona_horaria);
    
                            $folioBitacora = DB::select("SELECT IF (max(bit_act.folio_bitacora) IS NOT NULL,
    		                    (max(bit_act.folio_bitacora)+1),1) AS folio FROM teci_bitacora_actividad AS bit_act
    		                    JOIN main_empresas AS emp WHERE bit_act.empresa = emp.id AND emp.emp_token = ?",[$user->emp_token]);
    		                $tokenBiracora = $authJwt->encriptarToken($folioBitacora[0]->folio.$time_entrada.'---'.rand(10,10).
    		                    '---'.'---'.'---'.'actualizar password'.$user->emp_token.$user_token);
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
                                ->join("main_usuarios AS users","histBitacora.usuario","=","users.id")
                                ->where([
                                    'emp.emp_token' => $user->emp_token,
                                    'users.user_token' => $user->user_token,
                                ])->get();
                                if (count($histBitacora) == 0) {
                                    $enlaceLink = './gestion_de_proyectos/update_pass';
                                    $validate_process = 'inicial';
                                    $token = array(
                                        'user_token' => $user->user_token,
                                        'emp_token' => $user->emp_token,
                                    );
                                    
                                    $data_user = array(
                                        'iat' => time(),
                                        'exp' => time() + (7 * 24 * 60 * 60),
                                    );
                                } else {
                                    $infoUser = User::join("vhum_personal AS pers","users.id","=","pers.usuario")
                                    ->join("teci_user_settings AS sett","users.id","=","sett.usuario")
                                    ->join("vhum_personal_area AS area","pers.area","=","area.id")
                                    ->join("vhum_personal_cargo AS cargo","pers.cargo","=","cargo.id")
                                    ->join("sos_personas AS people","pers.personal","=","people.id")
                                    ->join("main_empresapersonal AS emppers","pers.id","=","emppers.personal")
                                    ->join("main_empresas AS emp","emppers.empresa","=","emp.id")
                                    ->where([
                                        'emp.emp_token' => $user->emp_token,
                                        'users.user_token' => $user->user_token,
                                    ])->get();
                                    
                                    if ($infoUser[0]->privilegio_crear == TRUE){
                                        $privilegio_crear = true;    
                                    } else {
                                        $privilegio_crear = false;
                                    }
                                    
                                    if ($infoUser[0]->privilegio_editar == TRUE){
                                        $privilegio_editar = true;    
                                    } else {
                                        $privilegio_editar = false;
                                    }                            
                                    
                                    if ($infoUser[0]->privilegio_consulta == TRUE){
                                        $privilegio_consulta = true;    
                                    } else {
                                        $privilegio_consulta = false;
                                    }
                                    
                                    if ($infoUser[0]->privilegio_elimina == TRUE){
                                        $privilegio_elimina = true;    
                                    } else {
                                        $privilegio_elimina = false;
                                    }
                                    
                                    if ($infoUser[0]->privilegio_ver_docs == TRUE){
                                        $privilegio_ver_docs = true;    
                                    } else {
                                        $privilegio_ver_docs = false;
                                    }
                                                    
                                    //$enlaceLink = './outside_logistica/home';
                                    $enlaceLink = './outside_logistica/select_company';
                                    $validate_process = 'finished';
                                    $dataEmpresa = DB::select("SELECT emp.zona_horaria,emp.zona_horaria_utc,ispa.codigo_pais,
                                        people.materno,people.paterno,people.nombre,people.denominacion_rs,people.rfc_generico,
                                        people.rfc,people.tax_id,people.img_perfil FROM main_empresas AS emp
                                        JOIN sos_personas AS people JOIN teci_pais AS ispa WHERE people.nacionalidad = ispa.id
                                        AND people.id = emp.persona AND emp.emp_token = ?",[$user->emp_token]);
                                    if ($dataEmpresa[0]->denominacion_rs == '') {
                                        $nombreEmpresa = $authJwt->desencriptar($dataEmpresa[0]->paterno)." ".$authJwt->desencriptar($dataEmpresa[0]->materno)." ".$authJwt->desencriptar($dataEmpresa[0]->nombre);
                                    } else {
                                        $nombreEmpresa = $authJwt->desencriptar($dataEmpresa[0]->denominacion_rs);
                                    }
                                    $rfc_generico = $dataEmpresa[0]->rfc_generico;
            
                                    if ($dataEmpresa[0]->rfc != NULL) {
                                        $rfc_emp = $authJwt->desencriptar($dataEmpresa[0]->rfc);
                                    } else {
                                        $rfc_emp = '---';
                                    }
            
                                    if ($dataEmpresa[0]->tax_id != NULL) {
                                        $tax_id_emp = $authJwt->desencriptar($dataEmpresa[0]->tax_id);
                                    } else {
                                        $tax_id_emp = '---';
                                    }
            
                                    //echo $authJwt->desencriptar($dataEmpresa[0]->img_perfil);
                                    $logoTipo = $authJwt->encriptaBase64(Storage::path('public/root/'.$user->root_tkn.'/0007-core/'.$authJwt->desencriptar($dataEmpresa[0]->img_perfil)));
            
                                    $alertaList = DB::select("SELECT * FROM teci_notificaciones AS alert INNER JOIN main_empresas AS emp 
                                        ON alert.empresa = emp.id INNER JOIN vhum_personal AS receptor ON alert.receptor = receptor.id 
                                        INNER JOIN main_usuarios AS users ON receptor.usuario = users.id 
                                        WHERE alert.status_recibe = FALSE AND alert.status_delete = TRUE and emp.emp_token = ? 
                                        AND users.user_token = ?
                                        AND ((alert.proyecto IS NOT NULL AND alert.area IS NULL AND alert.subarea IS NULL 
                                            AND	alert.producto IS NULL AND alert.servicio IS NULL AND alert.clave_serv IS NULL 
                                            AND	alert.cliente IS NULL AND alert.proveedor IS NULL 
                                            AND alert.proyecto IN (SELECT id FROM module_proyectos)) 
                                            OR (alert.proyecto IS NULL AND alert.area IS NOT NULL AND alert.subarea IS NOT NULL 
                                            AND	alert.producto IS NOT NULL AND alert.servicio IS NOT NULL 
                                            AND alert.clave_serv IS NOT NULL AND alert.cliente IS NOT NULL 
                                            AND alert.proveedor IS NOT NULL)) ORDER BY alert.id DESC",
                                    [$user->emp_token,$user->user_token]);
                                    
                                    $areadb = $authJwt->desencriptar($infoUser[0]->areaemp);
                                    if ($infoUser[0]->areaemp == 'MkljUG5ya01tZUNqYjlrNkRaZ0ljQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                                        $areasettings = 'airneg';
                                    } else if ($infoUser[0]->areaemp == 'OHNPcXphaG5ac3dFVFVtZW5UT3dRdz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                                        $areasettings = 'aerger';
                                    } else if ($infoUser[0]->areaemp == 'akVjZ2ZyVzBJM3Q2QmYvbE96VmFoQT09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                                        $areasettings = 'atseer';
                                    } else if ($infoUser[0]->areaemp == 'MjlOOWJJZDYvU2NOSXE4TDlNbCt1Zz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                                        $areasettings = 'avsleh';
                                    } else if ($infoUser[0]->areaemp == 'NUxVVURJNXp2OGNlUFpCUm52dVJsdz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
                                        $areasettings = 'acsleo';
                                    } else if ($infoUser[0]->areaemp == 'QnZUL2pXcytLTnN3RlRDaWZWaUkwUHd6elVuU3dDSEl0UDFYak9ZSG1WWT06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
                                       if ($infoUser[0]->emp_token =    'bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kv  YjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==') {
                                            $areasettings = 'aprtsieif';
                                        } else {
                                            $areasettings = 'asctsieif';
                                        }
                                    } else if ($infoUser[0]->areaemp == 'U0FyNDFBeWVpZ3V4d3ZTQklNZjBldmFwY3BHZUkvSHF3RmxkVjZqRTM3ST06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
                                        $areasettings = 'aasdemg';
                                    }
            
                                    if ($authJwt->desencriptar($infoUser[0]->img_perfil) == 'default-profile.png') {
                                        $avatar = $authJwt->encriptaBase64(Storage::path('public/settings/default-profile.png'));
                                    } else {
                                        $avatar = $authJwt->encriptaBase64(Storage::path('public/root/'.$infoUser[0]->root_tkn.
                                        '/0004-vhm/catalogos/employees/'.$authJwt->generar($infoUser[0]->folio_pers).'-'.
                                        $infoUser[0]->fecha_alta_pers.'/'.$authJwt->desencriptar($infoUser[0]->img_perfil).'-profile.png'));
                                    }
                                    
                                    $name_user_data = ucwords($authJwt->desencriptar($infoUser[0]->paterno)." ".
                                        $authJwt->desencriptar($infoUser[0]->materno)." ".$authJwt->desencriptar($infoUser[0]->nombre));
                                    Session::put('name_user_data', $name_user_data);
                                    
                                    $token = array(
                                        'user_token' => $user->user_token,
                                        'emp_token' => $user->emp_token,
                                    );
                                    
                                    $data_user = array(
                                        "user_token" => $user->user_token,
                                        "company_name" => $nombreEmpresa,
                                        "zona_horaria" => $dataEmpresa[0]->zona_horaria,
                                        "zona_horaria_utc" => $dataEmpresa[0]->zona_horaria_utc,
                                        "codigo_pais" => $dataEmpresa[0]->codigo_pais,
                                        "rfc_generico" => $rfc_generico,
                                        "rfc_emp" => $rfc_emp,
                                        "tax_id_emp" => $tax_id_emp,
                                        "logotypo" => $logoTipo,
                                        "name" => $name_user_data,
                                        "total_notificaciones" => count($alertaList),
                                        "jerarquia" => $infoUser[0]->jerarquia,
                                        
                                        "settings_lenguaje" => $infoUser[0]->lenguaje,
                                        "settings_privilegio_crear" => $privilegio_crear,
                                        "settings_privilegio_editar" => $privilegio_editar,
                                        "settings_privilegio_consulta" => $privilegio_consulta,
                                        "settings_privilegio_elimina" => $privilegio_elimina,
                                        "settings_privilegio_ver_docs" => $privilegio_ver_docs,
                                        
                                        "area" => ucfirst(strtolower($areadb)),
                                        "areasettings" => $areasettings,
                                        "cargo" => ucfirst(strtolower($authJwt->desencriptar($infoUser[0]->cargo))),
                                        "avatar" => $avatar,
                                        "iat" => time(),
                                        "exp" => time() + (7 * 24 * 60 * 60),
                                    );
                                }
    
                                $jwt = JWT::encode($token,$this->key,'HS256');
                                $jwt_data_user = JWT::encode($data_user,$this->key,'HS256');
                                $decodeTkn = JWT::decode($jwt_data_user,$this->key,['HS256']);
                    
                                $dataMensaje = array(
                                    "status" => "success",
                                    "code" => 200,
                                    "message" => "su contraseña ha sido actualizada",
                                    "large_token_access" =>  $jwt,
                                    "modulo_destino" => $enlaceLink,
                                    "validate_process" => $validate_process,
                                    "modulo_title" => "Logística",
                                    "modulo_code" => "ZnpQZzIxbGxORytFWW5uQ3A5WWNhd0hZbFBteGppc1Z5VzNkZE9SM0I4TT06OjEyMzQ1Njc4MTIzNDU2Nzg",
                                    "settings_privilegio_crear" => $privilegio_crear,
                                    "settings_privilegio_editar" => $privilegio_editar,
                                    "settings_privilegio_consulta" => $privilegio_consulta,
                                    "settings_privilegio_elimina" => $privilegio_elimina,
                                    "settings_privilegio_ver_docs" => $privilegio_ver_docs,
                                    "dataUsers" => $decodeTkn,
                                    "lenguaje" => $infoUser[0]->lenguaje,
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
}