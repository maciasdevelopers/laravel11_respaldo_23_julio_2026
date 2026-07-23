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
use App\Models\ListaPreciosModelo;

class INGR_ListaPreciosController extends Controller{

	public function getListaPrecios(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser,true);
		$arrayListaPrecios = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray,[
				'user_token' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Los datos del usuario son incorrectos, favor de verificarlos'.$validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
				$listaCompras = ListaPreciosModelo::get();

				foreach ($listaCompras as $value) {
					$arrayForeach = array(
						"token_lista_precios" => $value->token_lista_precios,
						"nombre_lista" => $JwtAuth->desencriptar($value->nombre_lista),
						"header_color" => "background-color:#".$value->header_color,
						"content_color" => "background-color:#".$value->content_color,
					);
					$arrayListaPrecios[] = $arrayForeach;
				}

				$dataMensaje = array(
					'price_list' => $arrayListaPrecios,
					'code' => 200,
					'status' => 'success'
				);
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje,$dataMensaje['code']);
	}

    //mercancias
        public function registralistaPreciosMerc(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProductosVig = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_cat_productos' => 'required|string',
                    'token_lista_precios' => 'required|string',
                    'precio_detalle' => 'required|string',
                    'arrayImpuestos' => 'required|array'
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Usuario incorrecto '.$validate->errors(),
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                
                    $token_cat_productos = $parametrosArray['token_cat_productos'];
                    $token_lista_precios = $parametrosArray['token_lista_precios'];
                    $precio_detalle = $parametrosArray['precio_detalle'];
                    $arrayImpuestos = $parametrosArray['arrayImpuestos'];
                    $patrónNumCosto = '/^[0-9$,.-]*$/';
                
                    $validateData = false;
                
                    if (isset($token_cat_productos) && !empty($token_cat_productos) &&
                        isset($token_lista_precios) && !empty($token_lista_precios) &&
                        isset($precio_detalle) && !empty($precio_detalle) && preg_match($patrónNumCosto,$precio_detalle) &&
                        isset($arrayImpuestos) && !empty($arrayImpuestos) && count($arrayImpuestos) > 0) {
                        $validateData = true;
                    } else {
                        if(!isset($token_cat_productos) || empty($token_cat_productos)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en mercancia seleccionada, intente nuevamente o comuniquese a soporte'
                            );
                        }
                        if(!isset($token_lista_precios) || empty($token_lista_precios)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en lista de precios seleccionada, intente nuevamente o comuniquese a soporte'
                            );
                        }
                        if(!isset($precio_detalle) || empty($precio_detalle) || !preg_match($patrónNumCosto,$precio_detalle)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en precio de la mercancia seleccionada, intente nuevamente o comuniquese a soporte'
                            );
                        }
                        if(!isset($arrayImpuestos) || empty($arrayImpuestos) || count($arrayImpuestos) == 0){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en lista de impuestos asignados para la mercancia seleccionada, intente nuevamente o comuniquese a soporte'
                            );
                        }
                    }
                
                    if ($validateData == true) {
                        $obtenProducto = DB::select("SELECT catprod.id,list.producto FROM catalogo_productos AS catprod JOIN productos AS list WHERE 
                            catprod.producto = list.id AND catprod.token_cat_productos = ?",
                            [$parametrosArray['token_cat_productos']]);

                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp  
                        JOIN empresapersonal AS emppers JOIN personal AS pers JOIN usuarios AS users WHERE emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token= ?",[$usuario->emp_token,$usuario->user_token]);

                        if (count($arrayImpuestos) > 0) {
                            for ($i=0; $i < count($arrayImpuestos); $i++) { 
                                //$token_impuesto = $arrayImpuestos[$i]['c_token'];
                                $obtenImpuestoArt = DB::select("SELECT catimp.id FROM impuestos_articulos AS impart 
                                    JOIN catalogo_impuestos AS catimp JOIN catalogo_productos AS catprod
                                    WHERE impart.producto_rel = catprod.id AND catprod.token_cat_productos = ?
                                    AND impart.impuestos = catimp.id AND catimp.token_cat_impuestos = ?",
                                    [$token_cat_productos,$arrayImpuestos[$i]['token_cat_impuestos']]);

                                if (count($obtenImpuestoArt) == 0) {
                                    $obtenImpuesto = DB::select("SELECT id FROM catalogo_impuestos WHERE 
                                    token_cat_impuestos = ?",[$arrayImpuestos[$i]['token_cat_impuestos']]);
                                    
                                    $tokenImpArt = $JwtAuth->encriptarToken(time().$token_cat_productos.$token_lista_precios.$precio_detalle);
                                    $insertaDetListPrec = DB::table('impuestos_articulos') 
                                    ->insert(array(
                                        "token_impuestos_articulos" => $tokenImpArt,
                                        "producto_rel" => $obtenProducto[0]->id, 
                                        "servicio_rel" => NULL, 
                                        "impuestos" => $obtenImpuesto[0]->id,
                                    ));
                                } 
                            }
                        
                        } 

                        $obtenListaPrecios = DB::select("SELECT id,nombre_lista FROM lista_precios WHERE token_lista_precios = ?",
                            [$parametrosArray['token_lista_precios']]);
                    
                        $tokenDetalleLista = $JwtAuth->encriptarToken($token_cat_productos.$token_lista_precios.$precio_detalle);
                    
                        $insertaDetListPrec = DB::table('detalle_lista_precios') 
                        ->insert(array(
                            "token_det_list_precios" => $tokenDetalleLista,
                            "lista" => $obtenListaPrecios[0]->id, 
                            "producto" => $obtenProducto[0]->id, 
                            "servicio" => NULL, 
                            "precio" => $precio_detalle,
                        ));
                    
                        if ($insertaDetListPrec) {
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Precio para mercancia'.$JwtAuth->desencriptar($obtenProducto[0]->producto).' ha sido registrado para la lista'.$JwtAuth->desencriptar($obtenListaPrecios[0]->nombre_lista)
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Registro de precio para mercancia'.$JwtAuth->desencriptar($obtenProducto[0]->producto).'para la lista'.$JwtAuth->desencriptar($obtenListaPrecios[0]->nombre_lista).' no fue realizado, intente nuevamente o comuniquese a soporte para más información'
                            );
                        }
                    
                    }
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Los informacion que intenta registrar no es valida'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }

        public function updatelistaPreciosMerc(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProductosVig = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_cat_productos' => 'required|string',
                    'token_lista_precios' => 'required|string',
                    'precio_detalle' => 'required|string',
                    'arrayImpuestos' => 'required|array'
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Usuario incorrecto '.$validate->errors(),
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                
                    $token_cat_productos = $parametrosArray['token_cat_productos'];
                    $token_lista_precios = $parametrosArray['token_lista_precios'];
                    $precio_detalle = $parametrosArray['precio_detalle'];
                    $arrayImpuestos = $parametrosArray['arrayImpuestos'];
                    $patrónNumCosto = '/^[0-9$,.-]*$/';
                
                    $validateData = false;
                
                    if (isset($token_cat_productos) && !empty($token_cat_productos) &&
                        isset($token_lista_precios) && !empty($token_lista_precios) &&
                        isset($precio_detalle) && !empty($precio_detalle) && preg_match($patrónNumCosto,$precio_detalle) &&
                        isset($arrayImpuestos) && !empty($arrayImpuestos) && count($arrayImpuestos) > 0) {
                        $validateData = true;
                    } else {
                        if(!isset($token_cat_productos) || empty($token_cat_productos)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en mercancia seleccionada, intente nuevamente o comuniquese a soporte'
                            );
                        }
                        if(!isset($token_lista_precios) || empty($token_lista_precios)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en lista de precios seleccionada, intente nuevamente o comuniquese a soporte'
                            );
                        }
                        if(!isset($precio_detalle) || empty($precio_detalle) || !preg_match($patrónNumCosto,$precio_detalle)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en precio de la mercancia seleccionada, intente nuevamente o comuniquese a soporte'
                            );
                        }
                        if(!isset($arrayImpuestos) || empty($arrayImpuestos) || count($arrayImpuestos) == 0){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en lista de impuestos asignados para la mercancia seleccionada, intente nuevamente o comuniquese a soporte'
                            );
                        }
                    }
                
                    if ($validateData == true) {
                        $obtenProducto = DB::select("SELECT catprod.id,list.producto FROM catalogo_productos AS catprod JOIN productos AS list WHERE 
                            catprod.producto = list.id AND catprod.token_cat_productos = ?",
                            [$parametrosArray['token_cat_productos']]);

                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp  
                        JOIN empresapersonal AS emppers JOIN personal AS pers JOIN usuarios AS users WHERE emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token= ?",[$usuario->emp_token,$usuario->user_token]);

                        $obtenListaPrecios = DB::select("SELECT id,nombre_lista FROM  WHERE token_lista_precios = ?",
                            [$parametrosArray['token_lista_precios']]);
                    
                        $insertaDetListPrec = DB::table('detalle_lista_precios AS detlist') 
                        ->join("lista_precios AS list","detlist.lista","=","list.id")
                        ->join("catalogo_productos AS catprod","detlist.producto","=","catprod.id")
                        ->where([
                            'list.token_lista_precios' => $parametrosArray['token_lista_precios'],
                            'catprod.token_cat_productos' => $parametrosArray['token_cat_productos'],
                        ])
                        ->limit(1)->update(
                            array(
                                'detlist.precio' => $precio_detalle,
                            )
                        );

                        if ($insertaDetListPrec) {
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Precio para mercancia'.$JwtAuth->desencriptar($obtenProducto[0]->producto).' ha sido actualizado para la lista'.$JwtAuth->desencriptar($obtenListaPrecios[0]->nombre_lista)
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Registro de precio para mercancia'.$JwtAuth->desencriptar($obtenProducto[0]->producto).'para la lista'.$JwtAuth->desencriptar($obtenListaPrecios[0]->nombre_lista).' no fue actualizado, intente nuevamente o comuniquese a soporte para más información'
                            );
                        }
                    
                    }
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Los informacion que intenta registrar no es valida'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }
	    
	//servicios
	    public function registralistaPreciosServ(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProductosVig = array();

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_cat_servicios' => 'required|string',
                    'token_lista_precios' => 'required|string',
                    'precio_detalle' => 'required|string',
                ]);

                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Usuario incorrecto '.$validate->errors(),
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 

                    $token_cat_servicios = $parametrosArray['token_cat_servicios'];
                    $token_lista_precios = $parametrosArray['token_lista_precios'];
                    $precio_detalle = $parametrosArray['precio_detalle'];
                    $patrónNumCosto = '/^[0-9$,.-]*$/';

                    $validateData = false;

                    if (isset($token_cat_servicios) && !empty($token_cat_servicios) &&
                        isset($token_lista_precios) && !empty($token_lista_precios) &&
                        isset($precio_detalle) && !empty($precio_detalle) && preg_match($patrónNumCosto,$precio_detalle)) {
                        $validateData = true;
                    } else {
                        if(!isset($token_cat_servicios) || empty($token_cat_servicios)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en servicio seleccionado, intente nuevamente o comuniquese a soporte'
                            );
                        }
                        if(!isset($token_lista_precios) || empty($token_lista_precios)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en lista de precios seleccionada, intente nuevamente o comuniquese a soporte'
                            );
                        }
                        if(!isset($precio_detalle) || empty($precio_detalle) || !preg_match($patrónNumCosto,$precio_detalle)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en precio del servicio seleccionado, intente nuevamente o comuniquese a soporte'
                            );
                        }                  
                    }
                    
                    if ($validateData == true) {
                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp  
					    JOIN empresapersonal AS emppers JOIN personal AS pers JOIN usuarios AS users WHERE emp.emp_token = ? 
			    		AND emp.id = emppers.empresa AND emppers.personal = pers.id 
			    		AND pers.usuario = users.id AND users.user_token= ?",[$usuario->emp_token,$usuario->user_token]);
                        
                        $obtenServicio = DB::select("SELECT catserv.id,list.servicio FROM catalogo_servicios AS catserv JOIN servicios AS list WHERE 
                            catserv.servicio = list.id AND catserv.token_cat_servicios = ?",
                            [$parametrosArray['token_cat_servicios']]);

                        $obtenListaPrecios = DB::select("SELECT id,nombre_lista FROM lista_precios WHERE token_lista_precios = ?",
                            [$parametrosArray['token_lista_precios']]);

                        $tokenDetalleLista = $JwtAuth->encriptarToken($token_cat_servicios.$token_lista_precios.$precio_detalle.time());

                        $insertaDetListPrec = DB::table('detalle_lista_precios') 
                        ->insert(array(
                            "token_det_list_precios" => $tokenDetalleLista,
                            "lista" => $obtenListaPrecios[0]->id, 
                            "producto" => NULL, 
                            "servicio" => $obtenServicio[0]->id,
                            //"impuesto" => $impuestoSelect, 
                            "precio" => $precio_detalle,
                        ));
                    
                        if ($insertaDetListPrec) {
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Precio para servicio'.$JwtAuth->desencriptar($obtenServicio[0]->servicio).' ha sido registrado para la lista'.$JwtAuth->desencriptar($obtenListaPrecios[0]->nombre_lista)
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Registro de precio para servicio'.$JwtAuth->desencriptar($obtenServicio[0]->servicio).'para la lista'.$JwtAuth->desencriptar($obtenListaPrecios[0]->nombre_lista).' no fue realizado, intente nuevamente o comuniquese a soporte para más información'
                            );
                        }

                    }
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Los informacion que intenta registrar no es valida'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }
        
        public function updatelistaPreciosServ(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProductosVig = array();
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_cat_servicios' => 'required|string',
                    'token_lista_precios' => 'required|string',
                    'precio_detalle' => 'required|string',
                    'arrayImpuestos' => 'required|array'
                ]);
            
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Usuario incorrecto '.$validate->errors(),
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                
                    $token_cat_servicios = $parametrosArray['token_cat_servicios'];
                    $token_lista_precios = $parametrosArray['token_lista_precios'];
                    $precio_detalle = $parametrosArray['precio_detalle'];
                    $arrayImpuestos = $parametrosArray['arrayImpuestos'];
                    $patrónNumCosto = '/^[0-9$,.-]*$/';
                
                    $validateData = false;
                
                    if (isset($token_cat_servicios) && !empty($token_cat_servicios) &&
                        isset($token_lista_precios) && !empty($token_lista_precios) &&
                        isset($precio_detalle) && !empty($precio_detalle) && preg_match($patrónNumCosto,$precio_detalle)) {
                        $validateData = true;
                    } else {
                        if(!isset($token_cat_servicios) || empty($token_cat_servicios)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en servicio seleccionado, intente nuevamente o comuniquese a soporte'
                            );
                        }
                        if(!isset($token_lista_precios) || empty($token_lista_precios)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en lista de precios seleccionado, intente nuevamente o comuniquese a soporte'
                            );
                        }
                        if(!isset($precio_detalle) || empty($precio_detalle) || !preg_match($patrónNumCosto,$precio_detalle)){
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'Error en precio del servicio seleccionado, intente nuevamente o comuniquese a soporte'
                            );
                        }
                    }
                
                    if ($validateData == true) {
                        $obtenProducto = DB::select("SELECT catserv.id,list.servicio FROM catalogo_servicios AS catserv JOIN servicios AS list WHERE 
                            catserv.servicio = list.id AND catserv.token_cat_servicios = ?",
                            [$parametrosArray['token_cat_servicios']]);

                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp  
                        JOIN empresapersonal AS emppers JOIN personal AS pers JOIN usuarios AS users WHERE emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token= ?",[$usuario->emp_token,$usuario->user_token]);

                        $obtenListaPrecios = DB::select("SELECT id,nombre_lista FROM  WHERE token_lista_precios = ?",
                            [$parametrosArray['token_lista_precios']]);
                    
                        $insertaDetListPrec = DB::table('detalle_lista_precios AS detlist') 
                        ->join("lista_precios AS list","detlist.lista","=","list.id")
                        ->join("catalogo_servicios AS catserv","detlist.servicio","=","catserv.id")
                        ->where([
                            'list.token_lista_precios' => $parametrosArray['token_lista_precios'],
                            'catserv.token_cat_servicios' => $parametrosArray['token_cat_servicios'],
                        ])
                        ->limit(1)->update(
                            array(
                                'detlist.precio' => $precio_detalle,
                            )
                        );

                        if ($insertaDetListPrec) {
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Precio para servicio'.$JwtAuth->desencriptar($obtenProducto[0]->servicio).' ha sido actualizado para la lista'.$JwtAuth->desencriptar($obtenListaPrecios[0]->nombre_lista)
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Registro de precio para servicio'.$JwtAuth->desencriptar($obtenProducto[0]->servicio).'para la lista'.$JwtAuth->desencriptar($obtenListaPrecios[0]->nombre_lista).' no fue actualizado, intente nuevamente o comuniquese a soporte para más información'
                            );
                        }
                    
                    }
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Los informacion que intenta registrar no es valida'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }

}