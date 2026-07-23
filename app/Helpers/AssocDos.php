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
    
    public function loginSession($codigo_acceso,$password,$getToken = null){
        $signup = false; 
        //buscar al usuario con sus credenciales
        $queryLogin = DB::select("SELECT users.user_token FROM teci_usuarios AS users JOIN tipo_usuario AS tp_user 
            WHERE users.tipo = tp_user.id_tipo AND tp_user.tipo = 'associate' AND (users.codigo_acceso = ? OR users.username = ?) 
            AND users.password = ?",[$codigo_acceso,$codigo_acceso,$password]); 

        if (is_object($queryLogin) || count($queryLogin) == 1) {
            $signup = true;
        }
        //validación de usuario
        if ($signup) {
            //preguntando sobre los permisos del usuario
            foreach($queryLogin as $user){
                //echo $user->emp_token;
                $permissionLogin = DB::select("SELECT users.login_permission FROM teci_usuarios AS users JOIN tipo_usuario AS tp_user 
                    WHERE users.tipo = tp_user.id_tipo AND tp_user.tipo = 'associate' AND (users.codigo_acceso = ? OR users.username = ?) 
                    AND users.password = ?",[$codigo_acceso,$codigo_acceso,$password]);
                if ($permissionLogin[0]->login_permission == TRUE) {
                    
                    $infoUser = User::join("personal","teci_usuarios.id","=","personal.usuario")
                    ->join("settings","personal.id","=","settings.personal")
                    ->join("personas AS people","personal.personal","=","people.id")
                    ->where([
                        'teci_usuarios.codigo_acceso' => $codigo_acceso,
                        'teci_usuarios.password' => $password,
                    ])
                    ->orwhere([
                        'teci_usuarios.username' => $codigo_acceso,
                        'teci_usuarios.password' => $password,
                    ])->get();
    
                    $companies_working = 1;
                    
                    $selectCompanies = DB::select("SELECT COUNT(emp.id) AS workingCompanies FROM empresas AS emp 
                        JOIN empresapersonal AS emppers JOIN personal AS pers JOIN teci_usuarios AS users 
                        JOIN tipo_usuario AS tpuser WHERE emp.id = emppers.empresa AND emppers.personal = pers.id
                        AND pers.usuario = users.id AND users.tipo = tpuser.id_tipo AND tpuser.tipo = 'associate' 
                        AND (users.codigo_acceso = ? OR users.username = ?) AND users.password = ?",
                        [$codigo_acceso,$codigo_acceso,$password]);
                
                    foreach($selectCompanies as $vComSel) {
                        $companies_working = $vComSel->workingCompanies;
                        
                        $histBitacora = DB::table("bitacora_actividad AS histBitacora")
                        ->join("empresas AS emp","histBitacora.empresa","=","emp.id")
                        ->join("teci_usuarios AS users","histBitacora.usuario","=","users.id")
                        ->where([
                            'users.user_token' => $user->user_token,
                        ])->get();
                        if (count($histBitacora) == 0) {
                            $token = array(
                                'user_token' => $user->user_token,
                                'emp_token' => $user->user_token,
                            );
                            
                            $data_user = array(
                                'iat' => time(),
                                'exp' => time() + (7 * 24 * 60 * 60),
                            );
                        } else {
                            $name_user_data = ucwords($this->authJwt->desencriptar($infoUser[0]->paterno)." ".
                                $this->authJwt->desencriptar($infoUser[0]->materno)." ".$this->authJwt->desencriptar($infoUser[0]->nombre));
                            Session::put('name_user_data', $name_user_data);
                            
                            if ($companies_working == 1) {
                                $dataEmpresa = DB::select("SELECT emp.emp_token,emp.root_tkn,emp.zona_horaria,emp.zona_horaria_utc,ispa.codigo_pais,
                                    people.materno,people.paterno,people.nombre,people.denominacion_rs,people.rfc_generico,
                                    people.rfc,people.tax_id,people.img_perfil,ar.areaemp,car.cargo FROM empresas AS emp
                                    JOIN personas AS people JOIN pais AS ispa JOIN empresapersonal AS emppers
                                    JOIN personal AS pers JOIN area AS ar JOIN cargo AS car JOIN settings AS conf 
                                    JOIN teci_usuarios AS users WHERE people.nacionalidad = ispa.id
                                    AND people.id = emp.persona AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                                    AND pers.area = ar.id AND pers.cargo = car.id AND pers.id = conf.personal
                                    AND pers.usuario = users.id AND users.user_token = ?",[$user->user_token]);
                                    
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
                                
                                $logoTipo = $this->authJwt->encriptaBase64(Storage::path('public/root/'.$dataEmpresa[0]->root_tkn.'/0007-core/'.$this->authJwt->desencriptar($dataEmpresa[0]->img_perfil)));
                                    
                                if ($this->authJwt->desencriptar($infoUser[0]->img_perfil) == 'default-profile.png') {
                                    $avatar = $this->authJwt->encriptaBase64(Storage::path('public/settings/default-profile.png'));
                                } else {
                                    $avatar = $this->authJwt->encriptaBase64(Storage::path('public/root/'.$dataEmpresa[0]->root_tkn.
                                    '/0004-vhm/catalogos/employees/'.$this->authJwt->generar($infoUser[0]->folio_pers).'-'.
                                    $infoUser[0]->fecha_alta_pers.'/'.$this->authJwt->desencriptar($infoUser[0]->img_perfil).'-profile.png'));
                                }
                                
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
                                   if ($infoUser[0]->emp_token =    'bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kv  YjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==') {
                                        $areasettings = 'aprtsieif';
                                    } else {
                                        $areasettings = 'asctsieif';
                                    }
                                } else if ($dataEmpresa[0]->areaemp == 'U0FyNDFBeWVpZ3V4d3ZTQklNZjBldmFwY3BHZUkvSHF3RmxkVjZqRTM3ST06OjEyMzQ1Njc4MTIzNDU2Nzg=') {
                                    $areasettings = 'aasdemg';
                                }
                                
                                $token = array(
                                    'user_token' => $user->user_token,
                                    'emp_token' => $dataEmpresa[0]->emp_token,
                                );
                                
                                $data_user = array(
                                    'user_token' => $user->user_token,
                                    'companies_working' => $companies_working,
                                    'emp_token' => $dataEmpresa[0]->emp_token,
                                    'company_name' => $nombreEmpresa,
                                    'zona_horaria' => $dataEmpresa[0]->zona_horaria,
                                    'zona_horaria_utc' => $dataEmpresa[0]->zona_horaria_utc,
                                    'codigo_pais' => $dataEmpresa[0]->codigo_pais,
                                    'rfc_generico' => $rfc_generico,
                                    'rfc_emp' => $rfc_emp,
                                    'tax_id_emp' => $tax_id_emp,
                                    'name' => $name_user_data,
                                    'lenguaje' => $infoUser[0]->lenguaje,
                                    'jerarquia' => $infoUser[0]->jerarquia,
                                    'area' => ucfirst(strtolower($areadb)),
                                    'areasettings' => $areasettings,
                                    'cargo' => ucfirst(strtolower($this->authJwt->desencriptar($dataEmpresa[0]->cargo))),
                                    'iat' => time(),
                                    'exp' => time() + (7 * 24 * 60 * 60),
                                    'logotypo' => $logoTipo,
                                    'avatar' => $avatar,
                                );
                            } else {
                                if ($this->authJwt->desencriptar($infoUser[0]->img_perfil) == 'default-profile.png') {
                                    $avatar = $this->authJwt->encriptaBase64(Storage::path('public/settings/default-profile.png'));
                                } else {
                                    $avatar = "";
                                }
                                
                                $selectTknCompanies = DB::select("SELECT emp.emp_token FROM empresas AS emp 
                                    JOIN empresapersonal AS emppers JOIN personal AS pers JOIN teci_usuarios AS users 
                                    JOIN tipo_usuario AS tpuser WHERE emp.id = emppers.empresa AND emppers.personal = pers.id
                                    AND pers.usuario = users.id AND users.tipo = tpuser.id_tipo AND tpuser.tipo = 'associate' 
                                    AND (users.codigo_acceso = ? OR users.username = ?) AND users.password = ?",
                                    [$codigo_acceso,$codigo_acceso,$password]);
                                
                                $textTokens = "";
                                
                                foreach ($selectTknCompanies as $vtokens) {
                                    $textTokens = $textTokens.$vtokens->emp_token;
                                }
                                           
                                $token = array(
                                    'user_token' => $user->user_token,
                                    'emp_token' => $textTokens,
                                );
                                
                                $data_user = array(
                                    'user_token' => $user->user_token,
                                    'emp_token' => $textTokens,
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
                                    'lenguaje' => $infoUser[0]->lenguaje,
                                    'jerarquia' => $infoUser[0]->jerarquia,
                                    'area' => "",
                                    'areasettings' => "",
                                    'cargo' => "",
                                    'avatar' => $avatar,
                                    'iat' => time(),
                                    'exp' => time() + (7 * 24 * 60 * 60),
                                );
                            }
                        }   
                    }

                    $jwt = JWT::encode($token,$this->key,'HS256');
                    $jwt_data_user = JWT::encode($data_user,$this->key,'HS256');
                    $decodeTkn = JWT::decode($jwt_data_user,$this->key,['HS256']);
                    
                    if (is_null($getToken)) {
                        //session_name($jwt);
                        $_SESSION["user_token"] = $jwt;
                        Session::put('user_token', $jwt);
                        $dataMensaje = $jwt;
                    } else {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'companies_working' => $companies_working,
                            'inside_page' => "./portal_para_terceros_asociados",
                            'dataUsers' => $decodeTkn,
                            'lenguaje' => $infoUser[0]->lenguaje,
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
                'code' => 404,
                'message' => 'Código de acceso o contraseña incorrectos',
                'password' => $password,
            );

        }
        return $dataMensaje;
    }
}