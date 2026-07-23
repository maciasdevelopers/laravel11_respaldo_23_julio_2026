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

class AuthAssociates {
    public $authJwt;
    public $key;
    public function __construct(){
        $this->authJwt = new \App\Helpers\JwtAuth();
        $this->key = 'dtclavessecreto-9876986986986986s';
    }
    
    public function loginSession($codigo_acceso,$password,$firebase_token_movil,$firebase_token_web){ 
        $signup = false; //buscar al usuario con sus credenciales 
        $queryLogin = DB::select("SELECT users.user_token,emp.empresa_token,emp.root_tkn FROM teci_catalogo_usuarios AS users JOIN main_empresas AS emp WHERE emp.id = users.empresa 
            AND (users.codigo_acceso = ? OR users.username = ?) AND users.password = ?",[$codigo_acceso,$codigo_acceso,$password]); 
            
        if (is_object($queryLogin) || count($queryLogin) == 1) { 
            $signup = true; 
        } 
        
        //validaci&oacute;n de usuario 
        if ($signup) { 
            //preguntando sobre los permisos del usuario 
            foreach($queryLogin as $user){ 
                //echo $user->empresa_token; 
                $permissionLogin = DB::select("SELECT users.login_permission AS login,users.outside_associates FROM teci_catalogo_usuarios AS users 
                    JOIN main_empresas AS emp WHERE emp.id = users.empresa AND (users.codigo_acceso = ? OR users.username = ?) 
                    AND users.password = ?",[$codigo_acceso,$codigo_acceso,$password]); 
                
                if ($permissionLogin[0]->login == TRUE && $permissionLogin[0]->outside_associates == TRUE) { 
                    $infoUser = User::join("vhum_empleados_catalogo AS pers","users.id","=","pers.usuario") 
                    ->join("teci_user_settings AS sett","users.id","=","sett.usuario") 
                    ->join("sos_personas AS people","pers.personal","=","people.id") 
                    ->where([ 'users.codigo_acceso' => $codigo_acceso, 'users.password' => $password]) 
                    ->orwhere([ 'users.username' => $codigo_acceso, 'users.password' => $password])->get();
                    
                    //var_dump($infoUser);
                    foreach ($infoUser as $rUser) {
                        $companies_working = 1; 
                        $privilegio_crear = false; 
                        $privilegio_editar = false; 
                        $privilegio_consulta = false; 
                        $privilegio_elimina = false; 
                        $privilegio_ver_docs = false; 
                        $name_user_data = ucwords($this->authJwt->desencriptar($rUser->paterno)." ".$this->authJwt->desencriptar($rUser->materno)." ".$this->authJwt->desencriptar($rUser->nombre));        
                        Session::put('name_user_data', $name_user_data); 
                        if ($rUser->privilegio_crear == TRUE)$privilegio_crear = true;
                        if ($rUser->privilegio_editar == TRUE)$privilegio_editar = true;
                        if ($rUser->privilegio_consulta == TRUE)$privilegio_consulta = true; 
                        if ($rUser->privilegio_elimina == TRUE)$privilegio_elimina = true;
                        if ($rUser->privilegio_ver_docs == TRUE)$privilegio_ver_docs = true;  
                        
                        if ($firebase_token_movil != ""){ 
                            User::where(['users.user_token' => $user->user_token])->limit(1)->update(array('users.token_dispositivo_movil' => $firebase_token_movil)); 
                        } 
                        
                        if ($firebase_token_web != ""){ 
                            User::where(['users.user_token' => $user->user_token])->limit(1)->update(array('users.token_dispositivo_web' => $firebase_token_web));
                        } 
                            
                        if ($this->authJwt->desencriptar($rUser->img_perfil) == 'default-profile.png') { 
                            $avatar = $this->authJwt->encriptaBase64(Storage::path('public/settings/default-profile.png'));
                        } else { 
                            $avatar = $this->authJwt->encriptaBase64(Storage::path('public/root/main_users/'.$this->authJwt->generar($rUser->folio_pers).'-'. 
                                $rUser->fecha_alta_pers.'/'.$this->authJwt->desencriptar($rUser->img_perfil).'-profile.png'));
                        }         
                            
                        $selectCompanies = DB::select("SELECT COUNT(emp.id) AS workingCompanies FROM main_empresas AS emp 
                            JOIN main_empresapersonal AS emppers JOIN vhum_empleados_catalogo AS pers JOIN teci_catalogo_usuarios AS users 
                            WHERE emp.id = emppers.empresa AND emppers.personal = pers.id AND pers.usuario = users.id 
                            AND (users.codigo_acceso = ? OR users.username = ?) AND users.password = ?", 
                            [$codigo_acceso,$codigo_acceso,$password]);     
                        
                        foreach($selectCompanies as $vComSel) { 
                            $companies_working = $vComSel->workingCompanies;
                        } 
                        
                        $histBitacora = DB::table("teci_bitacora_actividad AS histBitacora") 
                        ->join("main_empresas AS emp","histBitacora.empresa","=","emp.id") 
                        ->join("teci_catalogo_usuarios AS users","histBitacora.usuario","=","users.id") 
                        ->where([ 'users.user_token' => $user->user_token, ])->get(); 
                        
                        if (count($histBitacora) == 0) { 
                            $token = array('user_token' => $user->user_token,'empresa_token' => $user->user_token);
                            $data_user = array('iat' => time(),'exp' => time() + (7 * 24 * 60 * 60)); 
                        } else {                                
                            if ($companies_working == 1) { 
                                $dataEmpresa = DB::select("SELECT emp.empresa_token,emp.root_tkn,emp.zona_horaria,emp.zona_horaria_utc,ispa.codigo_pais, 
                                    people.materno,people.paterno,people.nombre,people.denominacion_rs,people.rfc_generico, 
                                    people.rfc,people.tax_id,people.img_perfil,ar.areaemp,car.cargo FROM main_empresas AS emp JOIN sos_personas AS people JOIN teci_pais AS ispa 
                                    JOIN main_empresapersonal AS emppers JOIN vhum_empleados_catalogo AS pers JOIN vhum_empleados_catalogo_area AS ar JOIN vhum_empleados_catalogo_cargo AS car 
                                    JOIN teci_user_settings AS conf JOIN teci_catalogo_usuarios AS users WHERE people.nacionalidad = ispa.id AND people.id = emp.persona 
                                    AND emp.id = emppers.empresa AND emppers.personal = pers.id AND pers.area = ar.id AND pers.cargo = car.id AND pers.usuario = users.id 
                                    AND users.user_token = ? AND users.id = conf.usuario",[$user->user_token]); 
                                    
                                if ($dataEmpresa[0]->denominacion_rs == '') { 
                                    $nombreEmpresa = $this->authJwt->desencriptar($dataEmpresa[0]->paterno)." ". 
                                        $this->authJwt->desencriptar($dataEmpresa[0]->materno)." ". 
                                        $this->authJwt->desencriptar($dataEmpresa[0]->nombre);
                                } else { 
                                    $nombreEmpresa = $this->authJwt->desencriptar($dataEmpresa[0]->denominacion_rs);
                                } 
                                
                                $rfc_generico = $dataEmpresa[0]->rfc_generico; 
                                
                                if ($dataEmpresa[0]->rfc != NULL) { 
                                    $rfc_emp = $this->authJwt->desencriptar($dataEmpresa[0]->rfc); 
                                } else { 
                                    $rfc_emp = '---'; 
                                } 
                                
                                if ($dataEmpresa[0]->tax_id != NULL) { 
                                    $tax_id_emp = $this->authJwt->desencriptar($dataEmpresa[0]->tax_id);
                                } else { 
                                    $tax_id_emp = '---';
                                } 
                                
                                $logoTipo = $this->authJwt->encriptaBase64(Storage::path('public/root/'.$dataEmpresa[0]->root_tkn.'/0007-core/'.
                                    $this->authJwt->desencriptar($dataEmpresa[0]->img_perfil))); 
                                
                                $areadb = $this->authJwt->desencriptar($dataEmpresa[0]->areaemp); 
                                
                                if ($dataEmpresa[0]->areaemp == 'MkljUG5ya01tZUNqYjlrNkRaZ0ljQT09OjoxMjM0NTY3ODEyMzQ1Njc4') { 
                                    $areasettings = 'airneg';
                                } else if ($dataEmpresa[0]->areaemp == 'OHNPcXphaG5ac3dFVFVtZW5UT3dRdz09OjoxMjM0NTY3ODEyMzQ1Njc4') { 
                                    $areasettings = 'aerger';
                                } else if ($dataEmpresa[0]->areaemp == 'akVjZ2ZyVzBJM3Q2QmYvbE96VmFoQT09OjoxMjM0NTY3ODEyMzQ1Njc4') { 
                                    $areasettings = 'atseer';
                                } else if ($dataEmpresa[0]->areaemp == 'MjlOOWJJZDYvU2NOSXE4TDlNbCt1Zz09OjoxMjM0NTY3ODEyMzQ1Njc4') { 
                                    $areasettings = 'avsleh';
                                } else if ($dataEmpresa[0]->areaemp == 'NUxVVURJNXp2OGNlUFpCUm52dVJsdz09OjoxMjM0NTY3ODEyMzQ1Njc4') { 
                                    $areasettings = 'acsleo';
                                } else if ($dataEmpresa[0]->areaemp == 'QnZUL2pXcytLTnN3RlRDaWZWaUkwUHd6elVuU3dDSEl0UDFYak9ZSG1WWT06OjEyMzQ1Njc4MTIzNDU2Nzg=') { 
                                    if ($rUser->empresa_token = 'bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==') { 
                                        $areasettings = 'aprtsieif';
                                    } else { 
                                        $areasettings = 'asctsieif';
                                    }
                                } else if ($dataEmpresa[0]->areaemp == 'U0FyNDFBeWVpZ3V4d3ZTQklNZjBldmFwY3BHZUkvSHF3RmxkVjZqRTM3ST06OjEyMzQ1Njc4MTIzNDU2Nzg=') { 
                                    $areasettings = 'aasdemg';
                                } 
                                
                                $token = array('user_token' => $user->user_token,'empresa_token' => $dataEmpresa[0]->empresa_token); 
                                $data_user = array( 
                                    'user_token' => $user->user_token, 
                                    'companies_working' => $companies_working, 
                                    'empresa_token' => $dataEmpresa[0]->empresa_token, 
                                    'company_name' => $nombreEmpresa, 
                                    'zona_horaria' => $dataEmpresa[0]->zona_horaria, 
                                    'zona_horaria_utc' => $dataEmpresa[0]->zona_horaria_utc, 
                                    'codigo_pais' => $dataEmpresa[0]->codigo_pais, 
                                    'rfc_generico' => $rfc_generico, 
                                    'rfc_emp' => $rfc_emp, 
                                    'tax_id_emp' => $tax_id_emp, 
                                    'name' => $name_user_data, 
                                    'lenguaje' => $rUser->lenguaje, 
                                    'jerarquia' => $rUser->jerarquia, 
                                    'area' => ucfirst(strtolower($areadb)), 
                                    'areasettings' => $areasettings, 
                                    'cargo' => ucfirst(strtolower($this->authJwt->desencriptar($dataEmpresa[0]->cargo))), 
                                    'iat' => time(), 
                                    'exp' => time() + (7 * 24 * 60 * 60), 
                                    'logotypo' => $logoTipo, 
                                    'avatar' => $avatar, 
                                );
                            } else { 
                                $token = array('user_token' => $user->user_token,'empresa_token' => ""); 
                                
                                $data_user = array(
                                    'user_token' => $user->user_token, 
                                    'empresa_token' => "", 
                                    'companies_working' => $companies_working, 
                                    'company_name' => "", 
                                    'zona_horaria' => "", 
                                    'zona_horaria_utc' => "", 
                                    'codigo_pais' => "", 
                                    'rfc_generico' => "", 
                                    'rfc_emp' => "", 
                                    'tax_id_emp' => "", 
                                    'logotypo' => "", 
                                    'name' => $name_user_data, 
                                    'lenguaje' => $rUser->lenguaje, 
                                    'jerarquia' => $rUser->jerarquia, 
                                    'area' => "", 
                                    'areasettings' => "", 
                                    'cargo' => "", 
                                    'avatar' => $avatar, 
                                    'iat' => time(), 
                                    'exp' => time() + (7 * 24 * 60 * 60)
                                );
                            } 
                        }
                        
                        $jwt = JWT::encode($token,$this->key,'HS256'); 
                        $jwt_data_user = JWT::encode($data_user,$this->key,'HS256'); 
                        $decodeTkn = JWT::decode($jwt_data_user,$this->key,['HS256']); 
                        $dataMensaje = array( 
                            "status" => "success", 
                            "code" => 200, 
                            "message" => "bienvenido", 
                            "modulo_destino" => "./portal_para_terceros_asociados/select_company", 
                            "validate_process" => "associates", 
                            "modulo_title" => "Terceros asociados", 
                            "large_token_access" => $jwt, 
                            "modulo_code" => "dEUrRnRDQ3NxVFR6RE14ZHNTRkRJZWk0cklObE10cldhUjJ2YXg1bE1LMD06OjEyMzQ1Njc4MTIzNDU2Nzg=", 
                            "settings_privilegio_crear" => $privilegio_crear, 
                            "settings_privilegio_editar" => $privilegio_editar, 
                            "settings_privilegio_consulta" => $privilegio_consulta, 
                            "settings_privilegio_elimina" => $privilegio_elimina, 
                            "settings_privilegio_ver_docs" => $privilegio_ver_docs, 
                            "dataUsers" => $decodeTkn, 
                            "lenguaje" => $rUser->lenguaje, 
                            "companies_working" => $companies_working, 
                        );
                        
                    } 
                } else { 
                    $dataMensaje = array('status' => 'error','code' => 404,'message' => 'Acceso no permitido');
                }
            }
        } else { 
            $dataMensaje = array( 'status' => 'error', 'code' => 404, 'message' => 'Código de acceso o contraseña incorrectos', 'password' => $password); 
        } 
        return $dataMensaje; 
    }
}