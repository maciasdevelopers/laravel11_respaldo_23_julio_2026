<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\PermisosModelo;
use App\Models\RegimenFiscalModelo;
//session_start();
use Session;

class MAIN_RegimenFiscalController extends Controller{
    public function listAllRegimenFiscal(Request $request){
        $JwtAuth = new \JwtAuth();
        $arrayRegFisc = array();
        $query_regimen = RegimenFiscalModelo::all();
        foreach ($query_regimen as $vReg) {
            $row = array(
               "token_regimen_fiscal" => $vReg->token_regimen_fiscal,
               "regimen" => $vReg->clave."-".$vReg->descripcion,
               "clave" => $vReg->clave,
               "descripcion" => $vReg->descripcion,
               "fisica" => $vReg->fisica,
               "moral" => $vReg->moral, 
            );
            $arrayRegFisc[] = $row;
        }

        $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'listRegFisc' => $arrayRegFisc,
        );
        return response()->json($dataMensaje, $dataMensaje['code']);
    }
    
    public function listPFRegimenFiscal(Request $request){
        $JwtAuth = new \JwtAuth();
        $arrayRegFisc = array();
        $query_regimen = RegimenFiscalModelo::where(["fisica" => "si"])->get();
        foreach ($query_regimen as $vReg) {
            $row = array(
               "token_regimen_fiscal" => $vReg->token_regimen_fiscal,
               "regimen" => $vReg->clave."-".$vReg->descripcion,
               "clave" => $vReg->clave,
               "descripcion" => $vReg->descripcion,
               "fisica" => $vReg->fisica,
            );
            $arrayRegFisc[] = $row;
        }

        $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'listRegFisc' => $arrayRegFisc,
        );
        return response()->json($dataMensaje, $dataMensaje['code']);
    }
    
    public function listPMRegimenFiscal(Request $request){
        $JwtAuth = new \JwtAuth();
        $arrayRegFisc = array();
        $query_regimen = RegimenFiscalModelo::where(["moral" => "si"])->get();
        foreach ($query_regimen as $vReg) {
            $row = array(
               "token_regimen_fiscal" => $vReg->token_regimen_fiscal,
               "regimen" => $vReg->clave."-".$vReg->descripcion,
               "clave" => $vReg->clave,
               "descripcion" => $vReg->descripcion,
               "moral" => $vReg->moral,
            );
            $arrayRegFisc[] = $row;
        }

        $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'listRegFisc' => $arrayRegFisc,
        );
        return response()->json($dataMensaje, $dataMensaje['code']);
    }
    
    public function listAllRegimen1Fiscal(Request $request){
        
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayMenuPerm = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Usuario incorrecto ',
                    'errors' => $validate->errors()
                );
            } else {
                $token_session = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                $empresa = $token_session->emp_token;
                $usuario = $token_session->user_token;
                
                $queryPermLog = DB::select("SELECT permiso.login FROM permiso_login AS permiso JOIN empresas AS emp 
                    WHERE permiso.empresa = emp.id AND emp.emp_token = ?
                    AND (permiso.clasificacion = 1 AND permiso.uprincipal IS NOT NULL AND permiso.empleado IS NULL AND permiso.uprincipal = (
                        SELECT id FROM usuarios WHERE user_token = ?)
                    ) OR emp.emp_token = ? AND 
                    (permiso.clasificacion = 2 AND permiso.empleado IS NOT NULL AND permiso.uprincipal IS NULL AND permiso.empleado = (
                        SELECT pers.id FROM personal AS pers JOIN usuarios AS users WHERE pers.usuario = users.id AND users.user_token = ?))",
                [$empresa,$usuario,$empresa,$usuario]);
        
                if ($queryPermLog[0]->login == TRUE) {
                    $loginPerM = true;
                    
                    //$queryPermMenu = PermisosModelo::join("empresas AS emp","permisos_usuario.empresa","emp.id")
                    //->join("permisos_menu AS permenu","permisos_usuario.menu","permenu.id")
                    //->leftjoin("usuarios AS users","permisos_usuario.uprincipal","users.id")
                    //->where([
                    //    "permisos_usuario.empleado" => NULL,
                    //    "permisos_usuario.uprincipal" => !NULL,
                    //    'emp.emp_token' => $usuario->emp_token,
                    //    'users.user_token' => $usuario->user_token
                    //])
                    //->orwhere([
                    //    "permisos_usuario.empleado" => !NULL,
                    //    "permisos_usuario.uprincipal" => NULL
                    //])
                    //->leftjoin("personal AS pers","permisos_usuario.empleado","pers.id")
                    //->leftjoin("usuarios AS users2","pers.usuario","users2.id")
                    //->where([
                    //    'emp.emp_token' => $usuario->emp_token,
                    //    'users2.user_token' => $usuario->user_token
                    //])
                    //->get();
                    
                    $queryPermMenu = DB::select("SELECT permenu.token_permisos_menu,permiso.acceso 
                        FROM permisos_usuario AS permiso JOIN permisos_menu AS permenu JOIN empresas AS emp 
                        WHERE permiso.menu = permenu.id AND permiso.empresa = emp.id AND emp.emp_token = ?
                        AND (
                            permiso.clasificacion = 1 AND permiso.uprincipal IS NOT NULL AND permiso.empleado IS NULL 
                            AND permiso.uprincipal = (SELECT id FROM usuarios WHERE user_token = ?)
                        ) 
                        OR permiso.menu = permenu.id AND permiso.empresa = emp.id AND emp.emp_token = ?
                        AND (
                            permiso.clasificacion = 2 AND permiso.empleado IS NOT NULL AND permiso.uprincipal IS NULL 
                            AND permiso.empleado = (
                                SELECT pers.id FROM personal AS pers JOIN usuarios AS users 
                                WHERE pers.usuario = users.id AND users.user_token = ?
                            )
                        )",[$empresa,$usuario,$empresa,$usuario]);
                    
                    foreach ($queryPermMenu AS $valPerMenu) {
                        //echo $valPerMenu->token_permisos_menu." ".$valPerMenu->acceso." ";
                        if ($valPerMenu->token_permisos_menu == 'VUVEREt3aUd0WXVDZitjNmt6SGhIWnVmMC94dllvRHM3bVorMkNmZ3VIcWFKYmk5cGp6Yzg0MlA0UTZLRTFsTzo6MTIzNDU2NzgxMjM0NTY3OA=='){ 
                            $menuData = 'ingtkndat';
                        } else if ($valPerMenu->token_permisos_menu == 'bTRlTnVoRVdNbjI5US9sckp6RXk5RFRYZkFCWHdvUzd2L0xzRW9yeThkSTJJcEtoWEVVbFdkalh3WklhTDk2cDo6MTIzNDU2NzgxMjM0NTY3OA=='){ 
                            $menuData = 'egrtkndat';
                        } else if ($valPerMenu->token_permisos_menu == 'a25CTllTTHIzQUxqMytVREI2SzQ2cmNwKy9CQ0NuUEVoUFNXVnpOMld4bz06OjEyMzQ1Njc4MTIzNDU2Nzg='){ 
                            $menuData = 'testkndat';
                        } else if ($valPerMenu->token_permisos_menu == 'MmlEQzEvYnJ6cFNXTEZ2N3FXbVlLUXVQMEp4OGJEa2h6REo5ak93TlhNdz06OjEyMzQ1Njc4MTIzNDU2Nzg='){ 
                            $menuData = 'valtkndat';
                        } else if ($valPerMenu->token_permisos_menu == 'ek5vaHgrODJIVEtJcnlGbWxMVWVQSVpxQjdoaEttS2ZrWFlOOU44dXhSND06OjEyMzQ1Njc4MTIzNDU2Nzg='){ 
                            $menuData = 'contkndat';
                        } else if ($valPerMenu->token_permisos_menu == 'MHZzVlhpRHQyaldUdXkvNmY5S0FQU3A5OHRIZlF6ellXZGRsWHNpMUtNZVFMMkJZVDNVU0FPV2xRVUU3ZFBoQjo6MTIzNDU2NzgxMjM0NTY3OA=='){ 
                            $menuData = 'tinftkndat';
                        }
                        
                        if ($valPerMenu->acceso == TRUE) {
                            $accesoStatus = true;
                        } else {
                            $accesoStatus = false;
                        }
                        
                        $arraYnterno = array(
                            "menuData" => $menuData,
                            "accesoData" => $accesoStatus,
                        );
                        $arrayMenuPerm[] = $arraYnterno; 
                    }
                    
                } else {
                    $loginPerM = false;
                }
                
                $dataMensaje = array(
                    'status' => 'success',
                    'code' => 200,
                    'statusPerm' => $loginPerM,
                    'arrayMenuPerm' => $arrayMenuPerm,
                );
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
