<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Models\ProductosModelo;
use App\Models\ProveedoresModelo;
use App\Models\UMedidaModelo;
use App\Models\DescuentosModelo;
use App\Models\PromocionesModelo;
use App\Models\MonedasModelo;
use App\Models\ListaPreciosModelo;
use App\Models\ClasificacionModelo;
use App\Models\ImpuestosModelo;
use App\Models\PuntoVentaModelo;
use PDF;
use QRCode;

class FNZS_PuntoVentaController extends Controller{
    public function pventaAssocCatalogo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $puntoVentaList = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string'
            ]);
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true); 
                $queryPunrtoVenta = PuntoVentaModelo::join('main_empresas AS emp','sos_puntodeventa_catalogos.empresa','emp.id')
                ->where(['sos_puntodeventa_catalogos.status_pv' => TRUE,'emp.empresa_token' => $usuario->empresa_token])->get();

                foreach ($queryPunrtoVenta as $vPV) {
                    $row = array(
                        "token_puntodeventa" => $vPV->token_puntodeventa,
                        "folio_puntodeventa" => "PVEN-".$JwtAuth->generarFolio($vPV->folio_puntodeventa),
                        "fecha_registro" => date('d-m-Y H:i:s',$vPV->fecha_registro_pv),
                        "pv_alias" => $JwtAuth->desencriptar($vPV->pv_alias),
                        "pv_direccion" => $JwtAuth->desencriptar($vPV->pv_direccion),
                        "pv_responsable" => $JwtAuth->desencriptar($vPV->pv_responsable),
                        "pv_observaciones" => $JwtAuth->desencriptar($vPV->pv_observaciones),
                        "pv_autorizado" => false
                    );
                    $puntoVentaList[] = $row; 
                }
                $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "catalogo" => $puntoVentaList,
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

    public function pventaAssocDetalle(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $puntoVentaList = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_puntodeventa' => 'required|string'
            ]);
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
                $token_puntodeventa = $parametrosArray["token_puntodeventa"];
                
                if (isset($token_puntodeventa) && !empty($token_puntodeventa)){
                    $queryPunrtoVenta = PuntoVentaModelo::join('main_empresas AS emp','sos_puntodeventa_catalogos.empresa','emp.id')
                    ->where([
                        'sos_puntodeventa_catalogos.token_puntoventa' => $token_puntodeventa,
                        'sos_puntodeventa_catalogos.status_pv' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                    ])->get();
    
                    foreach ($queryPunrtoVenta as $vPV) {
                        $row = array(
                            "token_puntodeventa" => $vPV->token_puntodeventa,
                            "folio_puntodeventa" => "PVEN-".$JwtAuth->generarFolio($vPV->folio_puntodeventa),
                            "fecha_registro" => date('d-m-Y H:i:s',$vPV->fecha_registro_pv),
                            "pv_alias" => $JwtAuth->desencriptar($vPV->pv_alias),
                            "pv_direccion" => $JwtAuth->desencriptar($vPV->pv_direccion),
                            "pv_responsable" => $JwtAuth->desencriptar($vPV->pv_responsable),
                            "pv_observaciones" => $JwtAuth->desencriptar($vPV->pv_observaciones),
                            "pv_autorizado" => false
                        );
                        $puntoVentaList[] = $row; 
                    }
                    $dataMensaje = array(
                        "status" => "success",
                        "code" => 200,
                        "catalogo" => $puntoVentaList,
                    );
                    
                } else {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Error al obtener punto de venta, por favor verifique su información o comuniquese a soporte"
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

	public function requestValidacionPventa(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"token_puntodeventa" => "required|string",
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
				$token_puntodeventa = $parametrosArray["token_puntodeventa"];
				$observaciones = "permiso de prueba";
              
                if (isset($token_puntodeventa) && !empty($token_puntodeventa)) {
                    $queryPV = PuntoVentaModelo::join('main_empresas AS emp','sos_puntodeventa_catalogos.empresa','emp.id')
                    ->where(['sos_puntodeventa_catalogos.token_puntodeventa' => $token_puntodeventa,'sos_puntodeventa_catalogos.status_pv' => TRUE,'emp.empresa_token' => $usuario->empresa_token])->get();
                
                    foreach ($queryPV as $vPv) {
                        $pv_folio = "PVEN-".$JwtAuth->generarFolio($vPv->folio_puntodeventa);
                        
			    		$select_id_pv = DB::select("SELECT id FROM sos_puntodeventa_catalogos WHERE token_puntodeventa = ?",[$vPv->token_puntodeventa]);
			    			    
                        $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?",[$usuario->empresa_token]); 
                            
                        $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                            AND users.usuario_token = ?",[$usuario->user_token]);    
			    		    
                        $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno,end($select_usuario)->materno,end($select_usuario)->nombre);
                        $folioSistema = DB::select("SELECT max(soli_auth.folio_puntodeventa_soli_auth) AS folio_permiso FROM sos_puntodeventa_soli_auth AS soli_auth 
                            JOIN main_empresas AS emp WHERE soli_auth.user_emp = emp.id AND emp.empresa_token = ?",[$usuario->empresa_token]);
                            
                        if (count($folioSistema) == 0){
                            $sql_folio = 1;
                        } else {
                            $sql_folio = end($folioSistema)->folio_permiso+1;
                        }    
			    			    
			    		$token_auth = $JwtAuth->encriptarToken(time(),end($select_empresa)->id.end($select_usuario)->id.$observaciones.time()-500);
			    			
                        $insertSoliPerm = DB::table("sos_puntodeventa_soli_auth")
                        ->insert(
                            array(
                                "token_puntodeventa_soli_auth" => $token_auth,
                                "folio_puntodeventa_soli_auth" => $sql_folio,
                                "fecha_puntodeventa_soli_auth" => time(),
                                "user_emp" => end($select_empresa)->id, 
                                "user_user" => end($select_usuario)->id, 
                                "puntodeventa" => end($select_id_pv)->id, 
                                "observaciones" => $JwtAuth->encriptar($observaciones),
                                "receptor" => 3,
                                "solicitud_pv_status" => TRUE,
                            )
                        ); 
			    			
                        if ($insertSoliPerm) {
                            $titulo_ = "Validación de punto de venta";
                            $mensaje_user = "El usuario ".$nombre_user." de la empresa ".end($select_empresa)->abrev_nombre." ha solicitado validación para el punto de venta con el folio ".$pv_folio;
                            
                            $receptorMovil = DB::select("SELECT device.dispositivo_token FROM teci_usuarios_dispositivos AS device JOIN teci_usuarios_catalogo AS users
                                WHERE device.dispositivo_tipo = 'movil' AND device.usuario = users.id AND users.usuario_token = ?",["ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY"]);
                                
                            if (count($receptorMovil) > 0) {
                                foreach ($receptorMovil as $devMov) {
                                    //echo $devMov->dispositivo_token;
                                    $JwtAuth->notificacionPushDevices($devMov->dispositivo_token,$titulo_,$mensaje_user);
                                }
                            }
                            
                            $receptorWeb = DB::table("teci_usuarios_dispositivos AS device")
                            ->join("teci_usuarios_catalogo AS users","device.usuario","=","users.id")
                            ->where(["device.dispositivo_tipo" => "web","users.usuario_token" => "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY"])->get();
                            
                            if (count($receptorWeb) > 0) {
                                foreach ($receptorWeb as $devWeb) {
                                    //echo $devWeb->dispositivo_token;
                                    $JwtAuth->notificacionPushDevices($devWeb->dispositivo_token,$titulo_,$mensaje_user);
                                }
                            }
                            
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "message" => "Solicitud de permiso generada con el folio PERM-".$JwtAuth->generarFolio($sql_folio),
                            ); 
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
                            ); 
                        }
                    }
                    
                } else {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Error en punto de venta registrado, por favor verifique su información o comuniquese a soporte"
                    );
                }
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La informacion que intenta registrar no es valida'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}
	
	public function validacionProcesoPventa(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"token_puntodeventa" => "required|string",
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true);
				$token_puntodeventa = $parametrosArray["token_puntodeventa"];
				$observaciones = "permiso de prueba";
              
                if (isset($token_puntodeventa) && !empty($token_puntodeventa)) {
                    $queryPV = PuntoVentaModelo::join('main_empresas AS emp','sos_puntodeventa_catalogos.empresa','emp.id')
                    ->where(['sos_puntodeventa_catalogos.token_puntodeventa' => $token_puntodeventa,'sos_puntodeventa_catalogos.status_pv' => TRUE,'emp.empresa_token' => $usuario->empresa_token])->get();
                
                    foreach ($queryPV as $vPv) {
                        $pv_folio = "PVEN-".$JwtAuth->generarFolio($vPv->folio_puntodeventa);
                        
			    		$select_id_pv = DB::select("SELECT id FROM sos_puntodeventa_catalogos WHERE token_puntodeventa = ?",[$vPv->token_puntodeventa]);
			    			    
                        $select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?",[$usuario->empresa_token]); 
                            
                        $select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                            AND users.usuario_token = ?",[$usuario->user_token]);    
			    		    
                        $nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno,end($select_usuario)->materno,end($select_usuario)->nombre);
                        
                        $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder
                            FROM sos_last_folders AS fold JOIN main_empresas AS emp
                            WHERE fold.fnzs_puntodeventa = TRUE AND fold.empresa = emp.id 
                            AND emp.empresa_token = ?",[$usuario->empresa_token]);						
    						
                        if (count($folioSistema) == 1) {
                            if ($folioSistema[0]->folio == 1000000000) {
                                $post_folio_db = DB::select("SELECT post_folio FROM sos_puntodeventa_catalogos WHERE id = (SELECT Max(pv.id) FROM sos_puntodeventa_catalogos AS pv 
                                    JOIN main_empresas AS emp WHERE pv.empresa = emp.id AND emp.empresa_token = ?)",[$usuario->empresa_token]);
                              
                                $post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
                                $folio_nuevo = 1;
                            } else {
                                $post_folio = NULL;
                                $folio_nuevo = $folioSistema[0]->folio;
                            }
                        } else {
                            $post_folio = NULL;
                            $folio_nuevo = 1;
                        }
                 
                        $new_pv_folio = 'PVEN-'.($post_folio == NULL ? $JwtAuth->generarFolio($folio_nuevo) : $JwtAuth->generarFolio($folio_nuevo).'-'.$post_folio);
                        
                        $pvAuth = PuntoVentaModelo::find(1);
                        $pvAuth->where("token_puntodeventa", $vPv->token_puntodeventa)->update([
                            "folio_puntodeventa" => $folio_nuevo,
                            "post_folio" => $post_folio,
    					    "authorized" => TRUE,
    					    "authorized_fecha" => time(),
    					    "authorized_by" => end($select_usuario)->id, 
                        ]);  
			    			    
			    		if ($pvAuth) {
                            $soliValidate = DB::table("sos_puntodeventa_catalogos AS pv")
                            ->join("sos_puntodeventa_soli_auth AS soli_auth","pv.id","=","soli_auth.puntodeventa")
                            ->join("teci_usuarios_catalogo AS users","soli_auth.user_user","=","users.id")
                            ->join("teci_usuarios_dispositivos AS device","users.id","=","device.usuario")
                            ->where(["soli_auth.soli_aprobada" => FALSE,"pv.token_puntodeventa" => $vPv->token_puntodeventa])->get();
                            
                            if (count($soliValidate) > 0){                         
                                $titulo_ = "Validación de punto de venta";
                                $mensaje_user = "El punto de venta con folio temporal ".$pv_folio." ha sido validado con el folio ".$new_pv_folio;
                                foreach ($soliValidate as $mSoli) {
                                    
                                    $soliValidAprob = DB::table("sos_puntodeventa_soli_auth")
                                    ->where(["token_puntodeventa_soli_auth" => $mSoli->token_puntodeventa_soli_auth])
					                ->limit(1)->update(array("soli_aprobada" => TRUE));
                                    
                                    if ($mSoli->dispositivo_tipo == "movil") {
                                        $JwtAuth->notificacionPushDevices($mSoli->dispositivo_token,$titulo_,$mensaje_user);
                                    }
                                    
                                    if ($mSoli->dispositivo_tipo == "web") {
                                        $JwtAuth->notificacionPushDevices($mSoli->dispositivo_token,$titulo_,$mensaje_user);
                                    }
                                }   
                            }    
                            if (count($folioSistema) == 0) {
                                $insertSistema = DB::table("sos_last_folders")
                                ->insert(array("fnzs_puntodeventa" => TRUE, "folder" => 1, "post_folder" => $post_folio,"empresa" => $select_empresa[0]->id));
                            } else {
                                $regFolder = DB::table("sos_last_folders AS lastf")->join("main_empresas AS emp","lastf.empresa","=","emp.id")
                                ->where(["lastf.fnzs_puntodeventa" => TRUE,"emp.empresa_token" => $usuario->empresa_token])
                                ->limit(1)->update(array("lastf.folder" => $folio_nuevo,"lastf.post_folder" => $post_folio));
                            }
                            
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "message" => "Punto de venta validado con el folio ".$new_pv_folio,
                            );  
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Solicitud de permiso no registrada, intentelo nuevamente o comuniquese a soporte",
                            ); 
                        }
                    }
                    
                } else {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Error en punto de venta registrado, por favor verifique su información o comuniquese a soporte"
                    );
                }
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'La informacion que intenta registrar no es valida'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

    public function pventaActualizar(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",
                "token_puntodeventa" => "required|string",
                "punto_venta_alias" => "required|string",
                "punto_venta_direccion" => "required|string",
                "punto_venta_responsable" => "required|string",
                "punto_venta_observaciones" => "required|string"
            ]);
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true); 
                $fecha_sistema = time();
                $token_puntodeventa = $parametrosArray["token_puntodeventa"];
                $punto_venta_alias = $parametrosArray["punto_venta_alias"];
                $punto_venta_direccion = $parametrosArray["punto_venta_direccion"];
                $punto_venta_responsable = $parametrosArray["punto_venta_responsable"];
                $punto_venta_observaciones = $parametrosArray["punto_venta_observaciones"];

                if (isset($token_puntodeventa) && !empty($token_puntodeventa) &&
                    isset($punto_venta_alias) && !empty($punto_venta_alias) && preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_alias) &&
                    isset($punto_venta_direccion) && !empty($punto_venta_direccion) && preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_direccion) &&
                    isset($punto_venta_responsable) && !empty($punto_venta_responsable) && preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_responsable) &&
                    isset($punto_venta_observaciones) && !empty($punto_venta_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_observaciones)) {

                    $queryPVenta = PuntoVentaModelo::join("main_empresas AS emp","sos_puntodeventa_catalogos.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
                    ->where([
                        "sos_puntodeventa_catalogos.status_pv" => TRUE,
                        "sos_puntodeventa_catalogos.token_puntodeventa" => $token_puntodeventa,
                        "emp.empresa_token" => $usuario->empresa_token,
                        "users.usuario_token" => $usuario->user_token,
                    ])->get();
                    
                    if (count($queryPVenta) > 0 && count($queryPVenta) == 1) {
                        foreach ($queryPVenta as $vPv) {
                            $pv_folio = 'PVEN-'.($post_folio == NULL ? $JwtAuth->generarFolio($folio_nuevo) : $JwtAuth->generarFolio($folio_nuevo).'-'.$post_folio);
                            $pvUpdate = PuntoVentaModelo::find(1);
                            $pvUpdate->where("token_puntodeventa", $vPv->token_puntodeventa)->update([
                                "pv_alias" => $JwtAuth->encriptar($punto_venta_alias),
                                "pv_direccion" => $JwtAuth->encriptar($punto_venta_nombre),
                                "pv_responsable" => $JwtAuth->encriptar($punto_venta_responsable),
                                "pv_observaciones" => $JwtAuth->encriptar($punto_venta_observaciones),
                            ]);
                            
                            if ($pvUpdate) {
                                $dataMensaje = array(
                                    "status" => "success",
                                    "code" => 200,
                                    "message" => "Punto de venta con folio ".$pv_folio." ha sido actualizado satisfactoriamente"
                                );
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    "message" => "Error en actualización de punto de venta, por favor verifique su información o comuniquese a soporte"
                                );
                            }
                        }
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            "message" => "Punto de venta no se encuentra registrado, por favor verifique su información o comuniquese a soporte"
                        );
                    }
                } else {
                    $mensaje_error = "";
                    if(!isset($token_puntodeventa) || empty($token_puntodeventa)) $mensaje_error = "Error al obtener punto de venta, por favor verifique su información o comuniquese a soporte";
                    if(!isset($punto_venta_alias) || empty($punto_venta_alias) || !preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_alias)) $mensaje_error = "Error en alias de punto de venta, por favor verifique su información o comuniquese a soporte";
                    if(!isset($punto_venta_nombre) || empty($punto_venta_nombre) || !preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_nombre)) $mensaje_error = "Error en nombre de punto de venta, por favor verifique su información o comuniquese a soporte";
                    if(!isset($punto_venta_responsable) || empty($punto_venta_responsable) || !preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_responsable)) $mensaje_error = "Error en responsable de punto de venta, por favor verifique su información o comuniquese a soporte";
                    if(!isset($punto_venta_observaciones) || empty($punto_venta_observaciones) || !preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_observaciones)) $mensaje_error = "Error en observaciones de punto de venta, por favor verifique su información o comuniquese a soporte"; 
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "message" => $mensaje_error
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

    public function pventaPapeleraSave(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
      
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
                "token_puntodeventa" => "required|string"
            ]);
      
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "Usuario incorrecto".$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                $token_puntodeventa = $parametrosArray["token_puntodeventa"];
              
                if (isset($token_puntodeventa) && !empty($token_puntodeventa)) {
                    $queryPV = PuntoVentaModelo::join('main_empresas AS emp','sos_puntodeventa_catalogos.empresa','emp.id')
                    ->where(['sos_puntodeventa_catalogos.token_puntodeventa' => $token_puntodeventa,'sos_puntodeventa_catalogos.status_pv' => TRUE,'emp.empresa_token' => $usuario->empresa_token])->get();
                
                    foreach ($queryPV as $vPv) {
                        $pv_folio = "PVEN-".$JwtAuth->generarFolio($vPv->folio_puntodeventa);
                        
                        $querySoli = PuntoVentaModelo::join("sos_puntodeventa_soli_auth AS soli","sos_puntodeventa_catalogos.id","soli.puntodeventa")
                        ->where(["sos_puntodeventa_catalogos.token_puntodeventa" => $vPv->token_puntodeventa,"soli.soli_aprobada" => TRUE])->get();
                        
                        if (count($querySoli) == 0) {
                            $impDelete = PuntoVentaModelo::find(1);
                            $impDelete->where("token_puntodeventa", $vPv->token_puntodeventa)->update(["status_pv" => FALSE,"fecha_pv_delete" => time()]);
        
                            if ($impDelete) {
                                $dataMensaje = array(
                                    "status" => "success",
                                    "code" => 200,
                                    "message" => "El punto de venta con folio ".$pv_folio." ha sido eliminado satisfactoriamente"
                                );
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    "message" => "Error en eliminación de punto de venta, por favor verifique su información o comuniquese a soporte"
                                );
                            }
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Eliminación de punto de venta no completada, está relacionado con otros catalogos y registros, por favor verifique su información o comuniquese a soporte"
                            );
                        }
                    }
                } else {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Error en punto de venta registrado, por favor verifique su información o comuniquese a soporte"
                    );
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }

    public function pventaAssocCatalogoEliminados(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $puntoVentaList = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string'
            ]);
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true); 
                $queryPunrtoVenta = PuntoVentaModelo::join('main_empresas AS emp','sos_puntodeventa_catalogos.empresa','emp.id')
                ->where(['sos_puntodeventa_catalogos.status_pv' => FALSE,'emp.empresa_token' => $usuario->empresa_token])->get();

                foreach ($queryPunrtoVenta as $vPV) {
                    $row = array(
                        "token_puntodeventa" => $vPV->token_puntodeventa,
                        "folio_puntodeventa" => "PVEN-".$JwtAuth->generarFolio($vPV->folio_puntodeventa),
                        "fecha_registro" => date('d-m-Y H:i:s',$vPV->fecha_registro_pv),
                        "pv_alias" => $JwtAuth->desencriptar($vPV->pv_alias),
                        "pv_direccion" => $JwtAuth->desencriptar($vPV->pv_direccion),
                        "pv_responsable" => $JwtAuth->desencriptar($vPV->pv_responsable),
                        "pv_observaciones" => $JwtAuth->desencriptar($vPV->pv_observaciones),
                        "pv_autorizado" => false,
                        "fecha_pv_delete" => date('d-m-Y H:i:s',$vPV->fecha_pv_delete),
                    );
                    $puntoVentaList[] = $row; 
                }
                $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "catalogo" => $puntoVentaList,
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

    public function pventaPapeleraRestaurar(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
      
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
                "token_puntodeventa" => "required|string"
            ]);
      
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "Usuario incorrecto".$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                $token_puntodeventa = $parametrosArray["token_puntodeventa"];
              
                if (isset($token_puntodeventa) && !empty($token_puntodeventa)) {
                    $queryPV = PuntoVentaModelo::join('main_empresas AS emp','sos_puntodeventa_catalogos.empresa','emp.id')
                    ->where(['sos_puntodeventa_catalogos.token_puntodeventa' => $token_puntodeventa,'sos_puntodeventa_catalogos.status_pv' => FALSE,'emp.empresa_token' => $usuario->empresa_token])->get();
                
                    foreach ($queryPV as $vPv) {
                        $pv_folio = "PVEN-".$JwtAuth->generarFolio($vPv->folio_puntodeventa);
                        $impDelete = PuntoVentaModelo::find(1);
                        $impDelete->where("token_puntodeventa", $vPv->token_puntodeventa)->update(["status_pv" => TRUE,"fecha_pv_delete" => NULL]);
    
                        if ($impDelete) {
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "message" => "El punto de venta con folio ".$pv_folio." ha sido restaurado satisfactoriamente"
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Error en restauración de punto de venta, por favor verifique su información o comuniquese a soporte"
                            );
                        }
                    }
                    
                } else {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Error en punto de venta registrado, por favor verifique su información o comuniquese a soporte"
                    );
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }

    public function pventaDeletePerm(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
      
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
                "token_puntodeventa" => "required|string"
            ]);
      
            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 200,
                    "message" => "Usuario incorrecto".$validate->errors(),
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                $token_puntodeventa = $parametrosArray["token_puntodeventa"];
              
                if (isset($token_puntodeventa) && !empty($token_puntodeventa)) {
                    $queryPV = PuntoVentaModelo::join('main_empresas AS emp','sos_puntodeventa_catalogos.empresa','emp.id')
                    ->where(['sos_puntodeventa_catalogos.token_puntodeventa' => $token_puntodeventa,'sos_puntodeventa_catalogos.status_pv' => FALSE,'emp.empresa_token' => $usuario->empresa_token])->get();
                
                    foreach ($queryPV as $vPv) {
                        $pv_folio = "PVEN-".$JwtAuth->generarFolio($vPv->folio_puntodeventa);
                        
                        $querySoli = PuntoVentaModelo::join("sos_puntodeventa_soli_auth AS soli","sos_puntodeventa_catalogos.id","soli.puntodeventa")
                        ->where(["sos_puntodeventa_catalogos.token_puntodeventa" => $vPv->token_puntodeventa,"soli.soli_aprobada" => TRUE])->get();
                        
                        if (count($querySoli) == 0) {
                            $impDelete = PuntoVentaModelo::find(1);
                            $impDelete->where("token_puntodeventa", $vPv->token_puntodeventa)->delete();
        
                            if ($impDelete) {
                                $dataMensaje = array(
                                    "status" => "success",
                                    "code" => 200,
                                    "message" => "El punto de venta con folio ".$pv_folio." ha sido eliminado satisfactoriamente"
                                );
                            } else {
                                $dataMensaje = array(
                                    "status" => "error",
                                    "code" => 200,
                                    "message" => "Error en eliminación de punto de venta, por favor verifique su información o comuniquese a soporte"
                                );
                            }
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Eliminación de punto de venta no completada, está relacionado con otros catalogos y registros, por favor verifique su información o comuniquese a soporte"
                            );
                        }
                    }
                } else {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Error en punto de venta registrado, por favor verifique su información o comuniquese a soporte"
                    );
                }
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 200,
                "message" => "La información que intenta registrar no es valida"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }

    public function registroPventaAssoc(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",
                "punto_venta_alias" => "required|string",
                "punto_venta_nombre" => "required|string",
                "punto_venta_responsable" => "required|string",
                "punto_venta_observaciones" => "required|string"
            ]);
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'La infomación que ha intantado registrar es invalida'.$validate->errors(),
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"],true); 
                $fecha_sistema = time();
                $punto_venta_alias = $parametrosArray["punto_venta_alias"];
                $punto_venta_nombre = $parametrosArray["punto_venta_nombre"];
                $punto_venta_responsable = $parametrosArray["punto_venta_responsable"];
                $punto_venta_observaciones = $parametrosArray["punto_venta_observaciones"];

                if (isset($punto_venta_alias) && !empty($punto_venta_alias) && preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_alias) &&
                    isset($punto_venta_nombre) && !empty($punto_venta_nombre) && preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_nombre) &&
                    isset($punto_venta_responsable) && !empty($punto_venta_responsable) && preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_responsable) &&
                    isset($punto_venta_observaciones) && !empty($punto_venta_observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_observaciones)) {
                    //return response()->json(["message" => "prueba25","code" => 200,"status" => "error"]);
                    $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,users.id AS userr,emp.zona_horaria,people.paterno,
                    people.materno,people.nombre,people.denominacion_rs,people.sitio_web FROM main_empresas AS emp  
                    JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
                    WHERE emp.empresa_token = ? AND emp.persona = people.id AND emp.id = empuser.empresa AND empuser.usuario = users.id 
                    AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

                    $folioSistema = DB::select("SELECT MAX(folio_puntodeventa) AS folio FROM sos_puntodeventa_catalogos AS imp
                        JOIN main_empresas AS emp WHERE imp.empresa = emp.id AND emp.empresa_token = ?",[$usuario->empresa_token]);
                    $sql_folio = count($folioSistema) == 0 ? 1 : $folioSistema[0]->folio+1;
                    $folio_pv = 'PVEN-'.$JwtAuth->generarFolio($sql_folio);
                    $token_cat_impuestos = $JwtAuth->encriptarToken($punto_venta_alias,$punto_venta_nombre,$punto_venta_responsable,$punto_venta_observaciones);
                    $creaPV = new PuntoVentaModelo();
                    $creaPV->token_puntodeventa = $token_cat_impuestos;
                    $creaPV->fecha_registro_pv = $fecha_sistema;
                    $creaPV->folio_puntodeventa = $sql_folio;
                    $creaPV->pv_alias = $JwtAuth->encriptar($punto_venta_alias);
                    $creaPV->pv_direccion = $JwtAuth->encriptar($punto_venta_nombre);
                    $creaPV->pv_responsable = $JwtAuth->encriptar($punto_venta_responsable);
                    $creaPV->pv_observaciones = $JwtAuth->encriptar($punto_venta_observaciones);
                    $creaPV->status_pv = TRUE;
                    $creaPV->empresa = $selectEmp[0]->id;
                    $saveNewPV = $creaPV->save();

                    if ($saveNewPV) {
                        $dataMensaje = array(
                            "status" => "success",
                            "code" => 200,
                            "message" => "Este punto de venta ha sido registrado satisfactoriamente con el folio ".$folio_pv
                        );
                    } else {
                        $dataMensaje = array(
                            "status" => "error",
                            "code" => 200,
                            "message" => "Error en registro de punto de venta, por favor verifique su información o comuniquese a soporte"
                        );
                    }
                    
                } else {
                    $mensaje_error = "";
                    if(!isset($punto_venta_alias) || empty($punto_venta_alias) || !preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_alias)) $mensaje_error = "Error en alias de punto de venta, por favor verifique su información o comuniquese a soporte";
                    if(!isset($punto_venta_nombre) || empty($punto_venta_nombre) || !preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_nombre)) $mensaje_error = "Error en nombre de punto de venta, por favor verifique su información o comuniquese a soporte";
                    if(!isset($punto_venta_responsable) || empty($punto_venta_responsable) || !preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_responsable)) $mensaje_error = "Error en responsable de punto de venta, por favor verifique su información o comuniquese a soporte";
                    if(!isset($punto_venta_observaciones) || empty($punto_venta_observaciones) || !preg_match($JwtAuth->filtroAlfaNumerico(),$punto_venta_observaciones)) $mensaje_error = "Error en observaciones de punto de venta, por favor verifique su información o comuniquese a soporte"; 
                    $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "message" => $mensaje_error
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
}
