<?php 
    namespace App\Helpers; 
    use Firebase\JWT\JWT; 
    use Illuminate\Support\Facades\DB; 
    use App\Models\User; 
    use Illuminate\Support\Facades\Storage; 
    use App\Models\PermisosModelo; 
    session_destroy(); 
    session_start(); 
    use Session; 
    class AuthEmployees { 
        public $authJwt; 
        public $key; 
        public function __construct(){ 
            $this->authJwt = new \App\Helpers\JwtAuth(); 
            $this->key = 'dtclavessecreto-9876986986986986s'; 
        } 
        
        public function signupEmpleados($empresa,$codigo_acceso,$password,$firebase_token_movil,$firebase_token_web){ 
            $authJwt = new \App\Helpers\JwtAuth();
            $signup = false; 
            
            $queryModulo = DB::select("SELECT mantenimiento,acceso FROM sos_modulos_sistemas WHERE modulo = 'ter_emp'"); 
            foreach ($queryModulo AS $rMod){
                if ($rMod->mantenimiento == FALSE && $rMod->acceso == TRUE) {
                    $queryLogin = DB::select("SELECT users.user_token,emp.empresa_token,emp.root_tkn FROM teci_catalogo_usuarios AS users JOIN main_empresas AS emp 
                    WHERE emp.id = users.empresa AND (users.codigo_acceso = ? OR users.username = ?) 
                    AND users.password = ?",[$codigo_acceso,$codigo_acceso,$password]); 
                    $signup = is_object($queryLogin) || count($queryLogin) == 1 ? true : false;
                    if ($signup) {
                        foreach($queryLogin as $user){
                            $permissionLogin = DB::select("SELECT users.login_permission AS login,users.outside_empleados FROM teci_catalogo_usuarios AS users JOIN main_empresas AS emp 
                            WHERE emp.id = users.empresa AND (users.codigo_acceso = ? OR users.username = ?) 
                            AND users.password = ?",[$codigo_acceso,$codigo_acceso,$password]);
    
                            if (end($permissionLogin)->login == TRUE && end($permissionLogin)->outside_empleados == TRUE) {
                                $infoUser = User::join("vhum_empleados_catalogo AS pers","teci_catalogo_usuarios.id","=","pers.usuario") 
                                ->join("teci_user_settings AS sett","teci_catalogo_usuarios.id","=","sett.usuario") 
                                ->join("sos_personas AS people","pers.personal","=","people.id") 
                                ->where([ 'teci_catalogo_usuarios.codigo_acceso' => $codigo_acceso, 'teci_catalogo_usuarios.password' => $password]) 
                                ->orwhere([ 'teci_catalogo_usuarios.username' => $codigo_acceso, 'teci_catalogo_usuarios.password' => $password])->get();
                            
                                foreach ($infoUser as $rUser) {
                                    $privilegio_crear = $rUser->privilegio_crear == TRUE ? true : false;
                                    $privilegio_editar = $rUser->privilegio_editar == TRUE ? true : false;  
                                    $privilegio_consulta = $rUser->privilegio_consulta == TRUE ? true : false;  
                                    $privilegio_elimina = $rUser->privilegio_elimina == TRUE ? true : false;  
                                    $privilegio_ver_docs = $rUser->privilegio_ver_docs == TRUE ? true : false;  
                                    $name_user_data = $authJwt->desencriptarNombres($rUser->paterno,$rUser->materno,$rUser->nombre);
                                    Session::put('name_user_data', $name_user_data);   
                                
                                    if ($firebase_token_movil != ""){
                                        $infoUser = User::where(['teci_catalogo_usuarios.user_token' => $user->user_token])
                                        ->limit(1)->update(
                                            array(
                                                'teci_catalogo_usuarios.token_dispositivo_movil' => $firebase_token_movil
                                            )
                                        );
                                    }
                                    
                                    if ($firebase_token_web != ""){
                                        $infoUser = User::where(['teci_catalogo_usuarios.user_token' => $user->user_token])
                                        ->limit(1)->update(
                                            array(
                                                'teci_catalogo_usuarios.token_dispositivo_web' => $firebase_token_web
                                            )
                                        );
                                    }                          
                                    
                                    $user_logo_text = $authJwt->desencriptar($rUser->img_perfil);
                                    $user_logo_path = 'public/root/main_users/'.$authJwt->generar($rUser->folio_pers).'-'.$rUser->fecha_alta_pers.'/';
                                    $avatar = $authJwt->encriptaBase64(Storage::path($user_logo_text != 'default-profile.png' ? $user_logo_path.$user_logo_text.'-profile.png' : 'public/settings/default-profile.png'));
                                
                                    $histBitacora = DB::table("teci_bitacora_actividad AS histBitacora")
                                    ->join("main_empresas AS emp","histBitacora.empresa","=","emp.id")
                                    ->join("teci_catalogo_usuarios AS users","histBitacora.usuario","=","users.id")
                                    ->where(["emp.empresa_token" => $user->empresa_token,"users.user_token" => $user->user_token])->get();
                                
                                    if (count($histBitacora) == 0) {
                                        $enlaceLink = './sos_inside/actualiza_contrasena';
                                        $validate_process = 'inicial';
                                        $data_user = array("iat" => time(),"exp" => time() + (7 * 24 * 60 * 60));
                                        $token = array("user_token" => $user->user_token,"empresa_token" => $user->empresa_token);
                                    } else {
                                        $dataEmpresa = $authJwt->loginEmpresaSeleccionada($empresa,$user->user_token);
                                        if (count($dataEmpresa) > 0) {
                                            $enlaceLink = './portal_para_terceros_empleados/home';
                                            $validate_process = 'finished';
                                            $token = array("user_token" => $user->user_token,"empresa_token" => $dataEmpresa["empresa_token"]);
                                            $data_user = array(
                                                "user_token" => $user->user_token,
                                                "company_name" => $dataEmpresa["company_name"],
                                                "zona_horaria" => $dataEmpresa["zona_horaria"],
                                                "zona_horaria_utc" => $dataEmpresa["zona_horaria_utc"],
                                                "codigo_pais" => $dataEmpresa["codigo_pais"],
                                                "rfc_generico" => $dataEmpresa["rfc_generico"],
                                                "rfc_emp" => $dataEmpresa["rfc_emp"],
                                                "tax_id_emp" => $dataEmpresa["tax_id_emp"],
                                                "logotypo" => $dataEmpresa["logotypo"],
                                                "name" => $name_user_data,
                                                "companies_vinc" => $dataEmpresa["companies_vinc"],
                                                "jerarquia" => $rUser->jerarquia_main,
                                                "settings_lenguaje" => $rUser->lenguaje,
                                                "settings_privilegio_crear" => $privilegio_crear,
                                                "settings_privilegio_editar" => $privilegio_editar,
                                                "settings_privilegio_consulta" => $privilegio_consulta,
                                                "settings_privilegio_elimina" => $privilegio_elimina,
                                                "settings_privilegio_ver_docs" => $privilegio_ver_docs,
                                                "area" => $dataEmpresa["area"],
                                                "areasettings" => $dataEmpresa["areasettings"],
                                                "cargo" => $dataEmpresa["cargo"],
                                                "avatar" => $avatar,
                                                "iat" => time(),
                                                "exp" => time() + (7 * 24 * 60 * 60),
                                                "conf_egresos" => $dataEmpresa["conf_egresos"],
                                                "moneda_ktn" => $dataEmpresa["moneda_ktn"],
                                                "moneda_code" => $dataEmpresa["moneda_code"],
                                                "moneda_name" => $dataEmpresa["moneda_name"],
                                                "moneda_decimales" => $dataEmpresa["moneda_decimales"],
                                            );
                                        } else {
                                            $dataMensaje = array(
                                                "status" => "error",
                                                "code" => 404,
                                                "message" => "Acceso no permitido, empresa no vinculada al usuario"
                                            );
                                            break;
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
                                        "modulo_title" => "Portal de empleados",
                                        "large_token_access" => $jwt,
                                        "modulo_code" => "dEUrRnRDQ3NxVFR6RE14ZHNTRkRJZWk0cklObE10cldhUjJ2YXg1bE1LMD06OjEyMzQ1Njc4MTIzNDU2Nzg=",
                                        "settings_privilegio_crear" => $privilegio_crear,
                                        "settings_privilegio_editar" => $privilegio_editar,
                                        "settings_privilegio_consulta" => $privilegio_consulta,
                                        "settings_privilegio_elimina" => $privilegio_elimina,
                                        "settings_privilegio_ver_docs" => $privilegio_ver_docs,
                                        "dataUsers" => $decodeTkn,
                                        "lenguaje" => $rUser->lenguaje,
                                    );
                                }
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
    }