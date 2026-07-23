<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\PromocionesModelo;
use App\Models\ServiciosModelo;
use App\Models\ProductosModelo;
use Illuminate\Support\Facades\DB;

class INGR_PromocionesController extends Controller{
 
    public function folioMaxPromocion(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true); 
        $promoMax = PromocionesModelo::max('ingr_catalogo_promociones.folio');

        return response()->json([
            'folioCompleto' => $JwtAuth->generar($promoMax),
            'folio' => $promoMax,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }

    public function folioNewRegPromocion(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true); 
        $promoMax = PromocionesModelo::max('folio');

        return response()->json([
            'folioCompleto' => $JwtAuth->generar($promoMax+1),
            'folio' => $promoMax+1,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }

    public function listaPromociones(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input("json");
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser, true);
        $listaPromociones = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar
            $validate = \Validator::make($parametrosArray, [
                "user_token" => "required|string"
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 400,
                    "message" => "La infomación que hemos recibido es invalida",
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
                
                $listaPromo = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","=","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                ->where([
                    "ingr_catalogo_promociones.status_activacion" => TRUE,
                    "ingr_catalogo_promociones.status" => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();
                
                foreach ($listaPromo as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    $arrayForeach = array(
                        "token_promocion" => $value->token_promocion,
                        "folio" => "PROMO-".$JwtAuth->generarFolio($value->folio),
                        "alias" => $JwtAuth->desencriptar($value->alias),
                        "concepto" => $JwtAuth->desencriptar($value->concepto),
                        "cuo_porc" => $value->cuo_porc == FALSE ? 'cuota' : 'porcentaje',
                        "cantidad_base" => $JwtAuth->desencriptar($value->cantidad_base),
                        "aplicacion" => $value->aplicacion == 'usa' ? 'eventual' : ($value->aplicacion == 'ind' ? 'indeterminado' : 'determinado'),
                        "fecha_inicio" => $value->fecha_inicio == '-' ? '-' : date('d-m-Y H:i:s',$value->fecha_inicio),
                        "fecha_fin" => $value->fecha_fin == '-' ? '-' : date('d-m-Y H:i:s',$value->fecha_fin),
                        "fecha_activacion" => date('d-m-Y H:i:s',$value->fecha_activacion),
                        "vinculacion" => false,
                    );
                    $listaPromociones[] = $arrayForeach; 
                }
                $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "promociones" => $listaPromociones
                );
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 400,
                "message" => "No fue posible procesar los datos recibidos"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);   
    }

    public function verPromocion(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $detallePromocion = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_promocion' => 'required|string'
            ]);
            
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'La información que hemos recibido es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                $infoPromocion = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
                ->where([
                    'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                    'ingr_catalogo_promociones.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();

                foreach ($infoPromocion as $detailPromocion) {

                    $consultaPromocion = DB::select("SELECT venta_promo.id FROM ingr_ventas_descu_promo AS venta_promo
                        JOIN ingr_catalogo_promociones AS promo WHERE venta_promo.promocion =  promo.id
                        AND promo.token_promocion = ?",[$detailPromocion->token_promocion]);
                    
                    //da_te_default_timezone_set($detailPromocion->zona_horaria);
                    $txtbase = $detailPromocion->cuo_porc == TRUE ? explode("%",$JwtAuth->desencriptar($detailPromocion->cantidad_base)) : explode("$",$JwtAuth->desencriptar($detailPromocion->cantidad_base));

                    $arrayServVigentes = array();
                    $arrayServVinculados = array();
                    $arrayServDeleted = array();
                    
					$servListPrm = ServiciosModelo::join("main_empresas AS emp","in_egr_catalogo_servicios.administrador","=","emp.id")
					->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
					->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
					->where([
						'in_egr_catalogo_servicios.status' => TRUE,
						'in_egr_catalogo_servicios.proceso' => "v",
						'in_egr_catalogo_servicios.modulo_mostrador' => TRUE,
						'emp.empresa_token' => $usuario->empresa_token,
						'users.usuario_token' => $usuario->user_token,
					])->get();

                    foreach ($servListPrm as $value) {
                        $checkListaServ = DB::select("SELECT detpromo.token_detalle_promocion,detpromo.servicio,
                            detpromo.status FROM ingr_detalle_promocion AS detpromo 
                            JOIN in_egr_catalogo_servicios AS catserv JOIN ingr_catalogo_promociones AS promo
                            WHERE detpromo.servicio = catserv.id AND catserv.token_cat_servicios = ?
                            AND detpromo.promocion = promo.id AND promo.token_promocion = ?",
                            [$value->token_cat_servicios,$detailPromocion->token_promocion]);
                            
                        if (count($checkListaServ) == 1) {
                            if ($checkListaServ[0]->status == TRUE) {
                                $vincPromoServ = true;
                                $tokenPromoDetalle = $checkListaServ[0]->token_detalle_promocion;
                            } else if ($checkListaServ[0]->status == FALSE) {
                                $vincPromoServ = false;
                                $tokenPromoDetalle = '';
                            } 
                        } else if (count($checkListaServ) == 0) {
                            $vincPromoServ = false;
                            $tokenPromoDetalle = '';
                        }
                        
                        $arrayForeachVig = array(
                            "token_cat_servicios" => $value->token_cat_servicios,
                            "imagen" => "./assets/images/catalogos/default_servicio.jpg",
                            "clasificacion" => $JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                                $JwtAuth->generar($value->folio),
                            "servicio" => $JwtAuth->desencriptar($value->servicio),
                            "clave" => $value->clave,
                            "vincPromoServ" => $vincPromoServ,
                            "tokenPromoDetalle" => $tokenPromoDetalle,
                        );
                        
                        if (count($checkListaServ) == 1) {
                            if ($checkListaServ[0]->status == TRUE) {
                                $arrayServVinculados[] = $arrayForeachVig; 
                            } else if ($checkListaServ[0]->status == FALSE) {
                                $arrayServDeleted[] = $arrayForeachVig; 
                            } 
                        } else {
                            $arrayServVigentes[] = $arrayForeachVig; 
                        }
                        
                    }

                    $arrayProdVigentes = array();
                    $arrayProdVinculados = array();
                    $arrayProdDeleted = array();
                    $prodListPrm = ProductosModelo::join("main_empresas AS emp","in_egr_catalogo_productos.admin_empresa","=","emp.id")
					->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
					->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
					->where([
						'in_egr_catalogo_productos.status' => TRUE,
						'emp.empresa_token' => $usuario->empresa_token,
						'users.usuario_token' => $usuario->user_token,
					])->get();
        
                    foreach ($prodListPrm as $value) {
                        $buyList = ProductosModelo::join("eegr_compras_detalle AS detcomp","in_egr_catalogo_productos.id","=","detcomp.producto")
                        ->join("eegr_compras_recepcion AS recept","detcomp.id","=","recept.detalle_compra")
                        ->join("in_egr_establecimientos_almacen AS det_alm","recept.id","=","det_alm.recepcion_compra")
                        ->join("eegr_compras AS buy","detcomp.numero_compra","=","buy.id")
                        ->join("main_empresas AS emp","in_egr_catalogo_productos.admin_empresa","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                        ->where([
                            'buy.status_recepcion' => TRUE,
                            'recept.recept_status' => TRUE,
                            //'det_alm.existencia' > 0,
                            'detcomp.activo_fijo' => NULL,
                            'detcomp.activo_intangible' => NULL,
                            'in_egr_catalogo_productos.token_cat_productos' => $value->token_cat_productos,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])
                        ->whereRaw('det_alm.existencia != 0')
                        ->orderBy('detcomp.id','DESC')->get();
                        
                        if (count($buyList) > 0) {
                        
                            $checkListaProd = DB::select("SELECT detpromo.token_detalle_promocion 
                                FROM ingr_detalle_promocion AS detpromo 
                                JOIN catalogo_productos AS catprod JOIN ingr_catalogo_promociones AS promo
                                WHERE detpromo.servicio = catprod.id AND catprod.token_cat_productos = ?
                                AND detpromo.promocion = promo.id AND promo.token_promocion = ?",
                                [$value->token_cat_productos,$detailPromocion->token_promocion]);
                            
                            if (count($checkListaProd) == 1) {
                                if ($checkListaProd[0]->status == TRUE) {
                                    $vincPromoProd = true;
                                    $tokenPromoDetalle = $checkListaProd[0]->token_detalle_promocion;
                                } else if ($checkListaProd[0]->status == FALSE) {
                                    $vincPromoProd = false;
                                    $tokenPromoDetalle = '';
                                } 
                            } else if (count($checkListaProd) == 0) {
                                $vincPromoProd = false;
                                $tokenPromoDetalle = '';
                            }

                            //da_te_default_timezone_set($value->zona_horaria);
                            if ($value->imagen == '' || !file_exists(Storage::path('public/root/'.
                                    $value->root_tkn.'/0002-cpp/catalogos/productos/'
                                    .$JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                                    $JwtAuth->generar($value->folio).'-'.$value->fecha_alta.'/'.$JwtAuth->desencriptar($value->imagen))) || $JwtAuth->desencriptar($value->imagen) == 'default_prod.jpg') {
                                    $logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/settings/default_prod.jpg'));
                                } else {
                                    $logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/root/'.
                                        $value->root_tkn.'/0002-cpp/catalogos/productos/'
                                        .$JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                                        $JwtAuth->generar($value->folio).'-'.$value->fecha_alta.'/'.$JwtAuth->desencriptar($value->imagen)));
                                }
                            
                            $arrayForeachVig = array(
                                "token_cat_productos" => $value->token_cat_productos,
                                "imagen" => $logo_prod,
                                "clasificacion" => $JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                                    $JwtAuth->generar($value->folio),
                                "producto" => $JwtAuth->desencriptar($value->producto),
                                "clave" => $value->clave,
                                "vincPromoProd" => $vincPromoProd,
                                "tokenPromoDetalle" => $tokenPromoDetalle,
                            );
                            if (count($checkListaProd) == 1) {
                                if ($checkListaProd[0]->status == TRUE) {
                                    $arrayProdVinculados[] = $arrayForeachVig; 
                                } else if ($checkListaProd[0]->status == FALSE) {
                                    $arrayProdDeleted[] = $arrayForeachVig; 
                                } 
                            } else {
                                $arrayProdVigentes[] = $arrayForeachVig; 
                            }
                        }
                    }
                    
                    $arrayForeachDesc = array(
                        "token_promocion" => $detailPromocion->token_promocion,
                        "folio_promocion" => "PROMO-".$JwtAuth->generarFolio($detailPromocion->folio),	
                        "alias_promocion" => $JwtAuth->desencriptar($detailPromocion->alias),	
                        "concepto_promocion" => $JwtAuth->desencriptar($detailPromocion->concepto),	
                        "cuo_porc" => $detailPromocion->cuo_porc == TRUE ? true : false,	
                        "cantidad_base" => $detailPromocion->cuo_porc == TRUE ? $txtbase[0] : $txtbase[1],	
                        "aplicacion" => $detailPromocion->aplicacion == 'usa' ? 'eventual' : ($detailPromocion->aplicacion == 'ind' ? 'indeterminado' : 'determinado'),	
                        "periodo_inicio" => $detailPromocion->aplicacion == 'usa' ? '' : gmdate('Y-m-d H:i:s',$detailPromocion->fecha_inicio),	
                        "periodo_fin" => $detailPromocion->aplicacion == 'det' ? date('d-m-Y H:i:s',$detailPromocion->fecha_fin) : '',		
                        "status_activacion" => $detailPromocion->status_activacion == TRUE ? true : false,	
                        "fecha_activacion" => date('d-m-Y H:i:s',$detailPromocion->fecha_activacion),	
                        "validateVinculo" => count($consultaPromocion) > 0 ? 'vinculado' : '',
                        "servicios" => $arrayServVigentes,
                        "serviciosVinculados" => $arrayServVinculados,
                        "serviciosDeleted" => $arrayServDeleted,
                        "productos" => $arrayProdVigentes,
                        "productosVinculados" => $arrayProdVinculados,
                        "productosDeleted" => $arrayProdDeleted,
                    );
                    $detallePromocion[] = $arrayForeachDesc; 
                }

                $dataMensaje = array(
                    'datosPromocion' => $detallePromocion,
                    'code' => 200,
                    'status' => 'success'
                );
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'No fue posible procesar los datos recibidos'
            );
        }
        
        return response()->json($dataMensaje, $dataMensaje['code']);
    }

    public function stopPromocion(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_promocion' => 'required|string'
            ]);
            
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'La infomación que hemos recibido es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                $infoPromocion = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                ->where([
                    'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                    'ingr_catalogo_promociones.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();

                foreach ($infoPromocion as $detailPromocion) {
                    $tiempo = time();
                    $updatedPromo = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                        'ingr_catalogo_promociones.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->update(array( 
                        "ingr_catalogo_promociones.fecha_activacion" => $tiempo,
                        "ingr_catalogo_promociones.status_activacion" => FALSE, 	
                    ));
    
                    if ($updatedPromo) {
                        $selectPromocionDetalle = DB::select("SELECT detpromo.token_detalle_promocion
                            FROM ingr_detalle_promocion AS detpromo JOIN ingr_catalogo_promociones AS promo 
                            WHERE detpromo.promocion = promo.id AND promo.token_promocion = ?",[$parametrosArray['token_promocion']]);

                        $contadorPromocionesDetalle = 0;
                        if (count($selectPromocionDetalle) > 0) { 

                            $updateDetallePromo = DB::table('detalle_promocion')
                            ->join("ingr_catalogo_promociones AS promo","detalle_promocion.promocion","promo.id") 
                            ->join("main_empresas AS emp","promo.empresa","emp.id")
                            ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                            ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                            ->where(array(
                                'promo.token_promocion' => $parametrosArray['token_promocion'],
                            ))
                            ->update(array( 
                                "detalle_promocion.fecha_activacion" => $tiempo, 	
                                "detalle_promocion.status_activacion" => FALSE, 
                            ));

                            if ($updateDetallePromo) {
                                $dataMensaje = array(
                                    'message' => 'Promoción deshabilitada',
                                    'code' => 200,
                                    'status' => 'success'
                                ); 
                            } else {
                                $dataMensaje = array(
                                    'message' => '¨Promoción no deshabilitada, intente mas tarde o comuniquese a soporte',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }

                        } else {
                            $dataMensaje = array(
                                'message' => 'Promoción deshabilitada',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        }
                        
                    } else {
                        $dataMensaje = array(
                            'message' => 'Promoción no deshabilitada, intente mas tarde o comuniquese a soporte',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'No fue posible procesar los datos recibidos'
            );
        }
        
        return response()->json($dataMensaje, $dataMensaje['code']);
    } 

    public function habilitarPromocion(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_promocion' => 'required|string'
            ]);
            
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'La infomación que hemos recibido es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                $infoPromocion = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                ->where([
                    'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                    'ingr_catalogo_promociones.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();

                foreach ($infoPromocion as $detailPromocion) {
                    $tiempo = time();
                    $updatedPromo = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                        'ingr_catalogo_promociones.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->update(array( 
                        "ingr_catalogo_promociones.fecha_activacion" => $tiempo,
                        "ingr_catalogo_promociones.status_activacion" => TRUE, 	
                    ));
    
                    if ($updatedPromo) {
                        $selectPromocionDetalle = DB::select("SELECT detpromo.token_detalle_promocion
                            FROM ingr_detalle_promocion AS detpromo JOIN ingr_catalogo_promociones AS promo 
                            WHERE detpromo.promocion = promo.id AND promo.token_promocion = ?",[$parametrosArray['token_promocion']]);

                        $contadorPromocionesDetalle = 0;
                        if (count($selectPromocionDetalle) > 0) { 

                            $updateDetallePromo = DB::table('detalle_promocion')
                            ->join("ingr_catalogo_promociones AS promo","detalle_promocion.promocion","promo.id") 
                            ->join("main_empresas AS emp","promo.empresa","emp.id")
                            ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                            ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                            ->where(array(
                                'promo.token_promocion' => $parametrosArray['token_promocion'],
                            ))
                            ->update(array( 
                                "detalle_promocion.fecha_activacion" => $tiempo, 	
                                "detalle_promocion.status_activacion" => TRUE, 
                            ));

                            if ($updateDetallePromo) {
                                $dataMensaje = array(
                                    'message' => 'Promoción habilitada',
                                    'code' => 200,
                                    'status' => 'success'
                                ); 
                            } else {
                                $dataMensaje = array(
                                    'message' => '¨Promoción no habilitada, intente mas tarde o comuniquese a soporte',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }

                        } else {
                             $dataMensaje = array(
                                'message' => 'Promoción habilitada',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        }
                    } else {
                        $dataMensaje = array(
                            'message' => '¨Promoción no habilitada, intente mas tarde o comuniquese a soporte',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'No fue posible procesar los datos recibidos'
            );
        }
        
        return response()->json($dataMensaje, $dataMensaje['code']);
    }

    public function listaPromocionesDesac(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input("json");
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser, true);
        $listaPromociones = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar
            $validate = \Validator::make($parametrosArray, [
                "user_token" => "required|string"
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 400,
                    "message" => "La infomación que hemos recibido es invalida",
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
                
                $listaPromo = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","=","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                ->where([
                    "ingr_catalogo_promociones.status_activacion" => FALSE,
                    "ingr_catalogo_promociones.status" => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();
                
                foreach ($listaPromo as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    $arrayForeach = array(
                    	"token_promocion" => $value->token_promocion,
                    	"folio" => "PROMO-".$JwtAuth->generarFolio($value->folio),
                    	"alias" => $JwtAuth->desencriptar($value->alias),
                    	"concepto" => $JwtAuth->desencriptar($value->concepto),
                    	"cuo_porc" => $value->cuo_porc == FALSE ? 'cuota' : 'porcentaje',
                    	"cantidad_base" => $JwtAuth->desencriptar($value->cantidad_base),
                    	"aplicacion" => $value->aplicacion == 'usa' ? 'eventual' : ($value->aplicacion == 'ind' ? 'indeterminado' : 'determinado'),
                    	"fecha_inicio" => $value->fecha_inicio == '-' ? '-' : date('d-m-Y H:i:s',$value->fecha_inicio),
                    	"fecha_fin" => $value->fecha_fin == '-' ? '-' : date('d-m-Y H:i:s',$value->fecha_fin),
                    	"fecha_activacion" => date('d-m-Y H:i:s',$value->fecha_activacion),
                    	"vinculacion" => false,
                    );
                    $listaPromociones[] = $arrayForeach; 
                }
                $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "promociones" => $listaPromociones
                );
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 400,
                "message" => "No fue posible procesar los datos recibidos"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);   
    } 

    public function listaPromocionesDel(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input("json");
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser, true);
        $listaPromociones = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar
            $validate = \Validator::make($parametrosArray, [
                "user_token" => "required|string"
            ]);

            if ($validate->fails()) {
                $dataMensaje = array(
                    "status" => "error",
                    "code" => 400,
                    "message" => "La infomación que hemos recibido es invalida",
                    "errors" => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
                
                $listaPromo = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","=","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                ->where([
                    "ingr_catalogo_promociones.status" => FALSE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();
                
                foreach ($listaPromo as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    $arrayForeach = array(
                    	"token_promocion" => $value->token_promocion,
						"folio" => "PROMO-".$JwtAuth->generarFolio($value->folio),
                    	"alias" => $JwtAuth->desencriptar($value->alias),
                    	"concepto" => $JwtAuth->desencriptar($value->concepto),
                    	"fecha_delete" => date('d-m-Y H:i:s',$value->fecha_delete_promo)
                    );
                    $listaPromociones[] = $arrayForeach; 
                }
                $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "promociones" => $listaPromociones
                );
            }
        } else {
            $dataMensaje = array(
                "status" => "error",
                "code" => 400,
                "message" => "No fue posible procesar los datos recibidos"
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);   
    }

    public function eliminapromocion(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_promocion' => 'required|string'
            ]);
            
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'La infomación que hemos recibido es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
				$token_promocion = $parametrosArray['token_promocion'];

                $infoPromocion = PromocionesModelo::join("ingr_ventas_descu_promo AS detvet","ingr_catalogo_promociones.id","detvet.promocion")
                ->join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
                ->where([
                    'ingr_catalogo_promociones.token_promocion' => $token_promocion,
                    'ingr_catalogo_promociones.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();

                if (count($infoPromocion) == 0) {
                    $updatedPromo = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
                    ->where([
                        'ingr_catalogo_promociones.token_promocion' => $token_promocion,
                        'ingr_catalogo_promociones.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->update(array( 
                        "ingr_catalogo_promociones.fecha_delete" => time(),
                        "ingr_catalogo_promociones.status" => FALSE, 	
                    ));
    
                    if ($updatedPromo) {
                        $dataMensaje = array(
                            'message' => 'Promoción eliminada',
                            'code' => 200,
                            'status' => 'success'
                        ); 
                    } else {
                        $dataMensaje = array(
                            'message' => 'Promoción no eliminada, intente mas tarde o comuniquese a soporte',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                } else {
                    $dataMensaje = array(
                        'message' => 'Promoción no eliminada por vinculación a ventas, intente mas tarde o comuniquese a soporte',
                        'code' => 200,
                        'status' => 'error'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'No fue posible procesar los datos recibidos'
            );
        }
        
        return response()->json($dataMensaje, $dataMensaje['code']);
    } 

    public function restaurapromocion(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_promocion' => 'required|string'
            ]);
            
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'La infomación que hemos recibido es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
				$token_promocion = $parametrosArray['token_promocion'];

                $updatedPromo = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","users.id")
                ->where([
                    'ingr_catalogo_promociones.token_promocion' => $token_promocion,
                    'ingr_catalogo_promociones.status' => FALSE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->update(array( 
                    "ingr_catalogo_promociones.fecha_delete" => '',
                    "ingr_catalogo_promociones.status" => TRUE, 	
                ));

                if ($updatedPromo) {
                    $dataMensaje = array(
                        'message' => 'Promoción restaurada',
                        'code' => 200,
                        'status' => 'success'
                    ); 
                } else {
                    $dataMensaje = array(
                        'message' => 'Promoción no restaurada, intente mas tarde o comuniquese a soporte',
                        'code' => 200,
                        'status' => 'error'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'No fue posible procesar los datos recibidos'
            );
        }
        
        return response()->json($dataMensaje, $dataMensaje['code']);
    } 

    public function eliminaPermPromocion(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_promocion' => 'required|string'
            ]);
            
            if ($validate->fails()) {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'La infomación que hemos recibido es invalida',
                    'errors' => $validate->errors()
                );
            } else {
                $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                $infoPromocion = PromocionesModelo::join("ingr_ventas_descu_promo AS detvet","ingr_catalogo_promociones.id","detvet.promocion")
                ->join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                ->where([
                    'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                    'ingr_catalogo_promociones.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();

                if (count($infoPromocion) == 0) {

                    $selectPromocionDetalle = DB::select("SELECT detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin,
                        detpromo.fecha_activacion,detpromo.status_activacion,detpromo.fecha_delete,detpromo.token_detalle_promocion
                        FROM ingr_detalle_promocion AS detpromo JOIN ingr_catalogo_promociones AS promo 
                        WHERE detpromo.promocion = promo.id AND promo.token_promocion = ?",[$parametrosArray['token_promocion']]);

                    $contadorPromocionesDetalle = 0;

                    for ($i=0; $i < count($selectPromocionDetalle); $i++) { 

                        $updateDetallePromo = DB::table('detalle_promocion')
                        ->join("ingr_catalogo_promociones AS promo","detalle_promocion.promocion","promo.id") 
                        ->join("main_empresas AS emp","promo.empresa","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                        ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                        ->where(array(
                            'detalle_promocion.token_detalle_promocion' => $selectPromocionDetalle[$i]->token_detalle_promocion, 
                            'promo.token_promocion' => $parametrosArray['token_promocion'],
                            'promo.status' => TRUE,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ))->limit(1)->delete();

                        if ($updateDetallePromo) {
                            ++$contadorPromocionesDetalle;
                        }

                    }

                    if ($contadorPromocionesDetalle == count($selectPromocionDetalle)) {
                        $updatedPromo = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                        ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                        ->where([
                            'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                            'ingr_catalogo_promociones.status' => TRUE,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])->limit(1)->delete();
        
                        if ($updatedPromo) {
                            $dataMensaje = array(
                                'message' => 'Promoción eliminada',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'Promoción no eliminada, intente mas tarde o comuniquese a soporte',
                                'code' => 200,
                                'status' => 'error'
                            );
                        }
                    } 
                } else {
                    $dataMensaje = array(
                        'message' => 'Promoción no eliminada por vinculación a ventas, intente mas tarde o comuniquese a soporte',
                        'code' => 200,
                        'status' => 'error'
                    );
                }
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'No fue posible procesar los datos recibidos'
            );
        }
        
        return response()->json($dataMensaje, $dataMensaje['code']);
    }

    public function updateGeneralesPromocion(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'alias' => 'required|string',
                'concepto' => 'required|string',
                'aplicacion' => 'required|string',
                'monto' => 'required|string',
                'tipo' => 'required|string',
                'fecha_inicia' => 'string',
                'fecha_termina' => 'string',
                'token_promocion' => 'required|string',
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

                $patrónConcepto = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,:]/';
                $patrón = '/[aA-zZ_]/';
                $patrónNumCosto = '/^[0-9,.]*$/';
                $patrónFecha = '/^[0-9-]*$/';

                $patrónFecha = '/^\d{1,2}\/\d{1,2}\/\d{2,4}$/';

                $alias = $parametrosArray['alias'];
                $concepto = $parametrosArray['concepto'];
                $aplicacion = $parametrosArray['aplicacion'];
                $monto = $parametrosArray['monto'];
                $tipo = $parametrosArray['tipo'];
                $fecha_inicia = $parametrosArray['fecha_inicia'];
                $fecha_termina = $parametrosArray['fecha_termina'];

                $validateFecha = false;

                if (isset($alias) && !empty($alias) && preg_match($patrónConcepto,$alias) &&
                    isset($concepto) && !empty($concepto) && preg_match($patrónConcepto,$concepto) &&
                    isset($aplicacion) && !empty($aplicacion) && preg_match($patrónConcepto,$aplicacion) &&
                    isset($monto) && !empty($monto) && preg_match($patrónNumCosto,$monto) &&
                    isset($tipo) && !empty($tipo) && preg_match($patrónConcepto,$tipo)) {
                    
                    if ($tipo == 'eventual') {
                        $validateFecha = true;
                    } else if ($tipo == 'pIndeterminado'){
                        if (isset($fecha_inicia) && !empty($fecha_inicia) && preg_match($patrónFecha,$fecha_inicia) && empty($fecha_termina)) {
                            $validateFecha = true;
                        } else {
                            $validateFecha = false;
                            $dataMensaje = array(
                                'message' => 'fecha de inicio de la promoción '.$concepto.' es invalida',
                                'code' => 200,
                                'status' => 'error'
                            );
                        }
                    
                    } else if ($tipo == 'pDeterminado'){
                        if (isset($fecha_inicia) && !empty($fecha_inicia) && preg_match($patrónFecha,$fecha_inicia) &&
                            isset($fecha_termina) && !empty($fecha_termina) && preg_match($patrónFecha,$fecha_termina)) {
                            $validateFecha = true;
                        } else {
                            $validateFecha = false;
                            if (!isset($fecha_inicia) || empty($fecha_inicia) || !preg_match($patrónFecha,$fecha_inicia)) {
                                $dataMensaje = array(
                                    'message' => 'fecha de inicio de la promoción '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }

                            if (!isset($fecha_termina) || empty($fecha_termina) || !preg_match($patrónFecha,$fecha_termina)) {
                                $dataMensaje = array(
                                    'message' => 'fecha de finalización de la promoción '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }
                        }
                    } 

                    if ($validateFecha == true) {
                        
                        $folioDescu = DB::select("SELECT IF (max(promo.folio) IS NOT NULL,(max(promo.folio)+1),1) AS folio
                            FROM ingr_catalogo_promociones AS promo JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE promo.empresa = emp.id AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa AND empuser.personal = pers.id
                            AND pers.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);

                        $alias = $JwtAuth->encriptar($parametrosArray['alias']);
                        $concepto = $parametrosArray['concepto'];

                        if ($parametrosArray['aplicacion'] == 'cuota') {
                            $aplicacion = FALSE;
                            $monto = $JwtAuth->encriptar('$'.$parametrosArray['monto']);
                        } else {
                            $aplicacion = TRUE;
                            $monto = $JwtAuth->encriptar($parametrosArray['monto'].'%');
                        }
                        
                        if ($parametrosArray['tipo'] == 'eventual') {
                            $tipo = 'usa';
                        } else if($parametrosArray['tipo'] == 'pIndeterminado'){
                            $tipo = 'ind';
                        } else if($parametrosArray['tipo'] == 'pDeterminado'){
                            $tipo = 'det';
                        }
                        
                        if ($parametrosArray['fecha_inicia'] == '') {
                            $fecha_inicia = '-';
                        } else {
                            $fecha_inicia = $JwtAuth->convierteFechaEpoc($parametrosArray['fecha_inicia']);
                        }
                        
                        if ($parametrosArray['fecha_termina'] == '') {
                            $fecha_termina = '-';
                        } else {
                            $fecha_termina = $JwtAuth->convierteFechaEpoc($parametrosArray['fecha_termina']);
                        }
                        
                        $updatePromociones = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                        ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                        ->where([
                            'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                            'ingr_catalogo_promociones.status' => TRUE,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])
                        ->update(array( 
                            "alias" => $alias,
                            "concepto" => $JwtAuth->encriptar($concepto),	
                            "cuo_porc" => $aplicacion,
                            "cantidad_base" => $monto,	
                            "aplicacion" => $tipo, 	
                            "fecha_inicio" => $fecha_inicia, 	
                            "fecha_fin" => $fecha_termina, 	
                        ));

                        if ($updatePromociones) {
                            
                            $selectPromocionDetalle = DB::select("SELECT detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin,
                                detpromo.fecha_activacion,detpromo.status_activacion,detpromo.fecha_delete,detpromo.token_detalle_promocion
                                FROM ingr_detalle_promocion AS detpromo JOIN ingr_catalogo_promociones AS promo 
                                WHERE detpromo.promocion = promo.id AND promo.token_promocion = ?",[$parametrosArray['token_promocion']]);
                            
                            $contadorPromocionesDetalle = 0;

                            for ($i=0; $i < count($selectPromocionDetalle); $i++) { 
                                
                                $updateDetallePromo = DB::table('detalle_promocion')::join("ingr_catalogo_promociones AS promo","detalle_promocion.promocion","promo.id") 
                                ->join("main_empresas AS emp","promo.empresa","emp.id")
                                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                                ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                                ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                                ->where(array(
                                    'detalle_promocion.token_detalle_promocion' => $selectPromocionDetalle->token_detalle_promocion, 
                                    'promo.token_promocion' => $parametrosArray['token_promocion'],
                                    'promo.status' => TRUE,
                                    'emp.empresa_token' => $usuario->empresa_token,
                                    'users.usuario_token' => $usuario->user_token,
                                ))
                                ->update(array( 
                                    "aplicacion" => $tipo, 	
                                    "fecha_inicio" => $fecha_inicia, 	
                                    "fecha_fin" => $fecha_termina, 	
                                ));

                                if ($updateDetallePromo) {
                                    ++$contadorPromocionesDetalle;
                                }

                            }
    
                            if ($updatePromociones && $contadorPromocionesDetalle == count($selectPromocionDetalle)) {
                                $dataMensaje = array(
                                    'message' => 'La actualización de esta promoción se ha realizado correctamente',
                                    'code' => 200,
                                    'status' => 'success'
                                ); 
                            } else {
                                $dataMensaje = array(
                                    'message' => 'La actualización de esta promoción se ha realizado no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }
                        }
                        
                    } 
                } else {
                    if (!isset($alias) || empty($alias) || !preg_match($patrónConcepto,$alias)) {
                        $dataMensaje = array(
                            'message' => 'alias de la promoción '.$concepto.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($concepto) || empty($concepto) || !preg_match($patrónConcepto,$concepto)) {
                        $dataMensaje = array(
                            'message' => 'concepto de la promoción '.$alias.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($aplicacion) || empty($aplicacion) || !preg_match($patrónConcepto,$aplicacion)) {
                        $dataMensaje = array(
                            'message' => 'aplicación de la promoción '.$concepto.' es invalida',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($monto) || empty($monto)) {
                        $dataMensaje = array(
                            'message' => 'monto de aplicación de la promoción '.$concepto.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($tipo) || empty($tipo) || !preg_match($patrónConcepto,$tipo)) {
                        $dataMensaje = array(
                            'message' => 'tipo de aplicación de la promoción '.$concepto.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                }

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

    public function registraPromocion(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);

        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'alias' => 'required|string',
                'concepto' => 'required|string',
                'aplicacion' => 'required|string',
                'monto' => 'required|string',
                'tipo' => 'required|string',
                'fecha_inicia' => 'string',
                'fecha_termina' => 'string',
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

                $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                    JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?
                    AND users.empleado = pers.id ",[$usuario->empresa_token,$usuario->user_token]);
                //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

                $patrónConcepto = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,:]/';
                $patrón = '/[aA-zZ_]/';
                $patrónNumCosto = '/^[0-9,.]*$/';
                $patrónFecha = '/^[0-9-]*$/';

                //$patrónFecha = '/^\d{1,2}\/\d{1,2}\/\d{2,4}$/';

                $alias = $parametrosArray['alias'];
                $concepto = $parametrosArray['concepto'];
                $aplicacion = $parametrosArray['aplicacion'];
                $monto = $parametrosArray['monto'];
                $tipo = $parametrosArray['tipo'];
                $fecha_inicia = $parametrosArray['fecha_inicia'];
                $fecha_termina = $parametrosArray['fecha_termina'];

                $validateFecha = false;

                if (isset($alias) && !empty($alias) && preg_match($patrónConcepto,$alias) &&
                    isset($concepto) && !empty($concepto) && preg_match($patrónConcepto,$concepto) &&
                    isset($aplicacion) && !empty($aplicacion) && preg_match($patrónConcepto,$aplicacion) &&
                    isset($monto) && !empty($monto) && preg_match($patrónNumCosto,$monto) &&
                    isset($tipo) && !empty($tipo) && preg_match($patrónConcepto,$tipo)) {
                    
                    if ($tipo == 'eventual') {
                        $validateFecha = true;
                    } else if ($tipo == 'pIndeterminado'){
                        if (isset($fecha_inicia) && !empty($fecha_inicia) && preg_match($patrónFecha,$fecha_inicia) && empty($fecha_termina)) {
                            $validateFecha = true;
                        } else {
                            $validateFecha = false;
                            $dataMensaje = array(
                                'message' => 'fecha de inicio de la promoción '.$concepto.' es invalida',
                                'code' => 200,
                                'status' => 'error'
                            );
                        }
                    
                    } else if ($tipo == 'pDeterminado'){
                        if (isset($fecha_inicia) && !empty($fecha_inicia) && preg_match($patrónFecha,$fecha_inicia) &&
                            isset($fecha_termina) && !empty($fecha_termina) && preg_match($patrónFecha,$fecha_termina)) {
                            $validateFecha = true;
                        } else {
                            $validateFecha = false;
                            if (!isset($fecha_inicia) || empty($fecha_inicia) || !preg_match($patrónFecha,$fecha_inicia)) {
                                $dataMensaje = array(
                                    'message' => 'fecha de inicio de la promoción '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }

                            if (!isset($fecha_termina) || empty($fecha_termina) || !preg_match($patrónFecha,$fecha_termina)) {
                                $dataMensaje = array(
                                    'message' => 'fecha de finalización de la promoción '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }
                        }
                    } 

                    if ($validateFecha == true) {
                        $timeActual = time();
                        $folioDescu = DB::select("SELECT IF (max(promo.folio) IS NOT NULL,(max(promo.folio)+1),1) AS folio
                            FROM ingr_catalogo_promociones AS promo JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            JOIN teci_usuarios_catalogo AS users WHERE promo.empresa = emp.id AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$usuario->empresa_token,$usuario->user_token]);

                        $alias = $JwtAuth->encriptar($parametrosArray['alias']);
                        $concepto = $parametrosArray['concepto'];

                        $aplicacion = $parametrosArray['aplicacion'] == 'cuota' ? FALSE : TRUE;
                        $monto = $parametrosArray['aplicacion'] == 'cuota' ? $JwtAuth->encriptar('$'.$parametrosArray['monto']) : $JwtAuth->encriptar($parametrosArray['monto'].'%');
                        $tipo = $parametrosArray['tipo'] == 'eventual' ? 'usa' : ($parametrosArray['tipo'] == 'pIndeterminado' ? 'ind' : 'det');
                        $fecha_inicia = $parametrosArray['fecha_inicia'] == '' ? '-' : $JwtAuth->convierteFechaEpoc($parametrosArray['fecha_inicia']);
                        $fecha_termina = $parametrosArray['fecha_termina'] == '' ? '-' : $JwtAuth->convierteFechaEpoc($parametrosArray['fecha_termina']);
                        
                        $tokenDesc = $JwtAuth->encriptarToken($timeActual,$alias,$concepto,$aplicacion,$monto,$tipo,$fecha_inicia,$fecha_termina);
                        $newdesc = new PromocionesModelo();
                        $newdesc->token_promocion = $tokenDesc;
                        $newdesc->folio = $folioDescu[0]->folio;	
                        $newdesc->alias = $alias;	
                        $newdesc->concepto = $JwtAuth->encriptar($concepto);	
                        $newdesc->cuo_porc = $aplicacion;	
                        $newdesc->cantidad_base = $monto;	
                        $newdesc->aplicacion = $tipo;	
                        $newdesc->fecha_inicio = $fecha_inicia;
                        $newdesc->fecha_fin = $fecha_termina;	
                        $newdesc->fecha_activacion = $timeActual;
                        $newdesc->status_activacion = TRUE;
                        $newdesc->fecha_delete = '';
                        $newdesc->status = TRUE;
                        $newdesc->empresa =  $selectEmp[0]->id;
                        $salvaDesc = $newdesc->save();
                        if ($salvaDesc) {
                            $dataMensaje = array(
                                'message' => 'El registro de esta promoción se ha realizado correctamente con el folio PROMO-'.$JwtAuth->generarFolio($folioDescu[0]->folio),
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'El registro de esta promoción se ha realizado no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                'code' => 200,
                                'status' => 'error'
                            );
                        }
                    } 
                } else {
                    if (!isset($alias) || empty($alias) || !preg_match($patrónConcepto,$alias)) {
                        $dataMensaje = array(
                            'message' => 'alias de la promoción '.$concepto.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($concepto) || empty($concepto) || !preg_match($patrónConcepto,$concepto)) {
                        $dataMensaje = array(
                            'message' => 'concepto de la promoción '.$alias.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($aplicacion) || empty($aplicacion) || !preg_match($patrónConcepto,$aplicacion)) {
                        $dataMensaje = array(
                            'message' => 'aplicación de la promoción '.$concepto.' es invalida',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($monto) || empty($monto)) {
                        $dataMensaje = array(
                            'message' => 'monto de aplicación de la promoción '.$concepto.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($tipo) || empty($tipo) || !preg_match($patrónConcepto,$tipo)) {
                        $dataMensaje = array(
                            'message' => 'tipo de aplicación de la promoción '.$concepto.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                }

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
        public function registrarMercPromocion(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'arrayAltaPromociones' => 'required',
                    'token_cat_productos' => 'required|string',
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

                    $validateForPromo = false;
                    $contadorForPromo = 0;
                    for ($i=0; $i < count($parametrosArray['arrayAltaPromociones']); $i++) { 
                        $patrónConcepto = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,:]/';
                        $patrón = '/[aA-zZ_]/';
                        $patrónNumCosto = '/^[0-9,.]*$/';
                        $patrónFecha = '/^[0-9-]*$/';

                        $patrónFecha = '/^\d{1,2}\/\d{1,2}\/\d{2,4}$/';

                        $alias = $parametrosArray['arrayAltaPromociones'][$i]['alias'];
                        $concepto = $parametrosArray['arrayAltaPromociones'][$i]['concepto'];
                        $aplicacion = $parametrosArray['arrayAltaPromociones'][$i]['aplicacion'];
                        $monto = $parametrosArray['arrayAltaPromociones'][$i]['monto'];
                        $tipo = $parametrosArray['arrayAltaPromociones'][$i]['tipo'];
                        $fecha_inicia = $parametrosArray['arrayAltaPromociones'][$i]['fecha_inicia'];
                        $fecha_termina = $parametrosArray['arrayAltaPromociones'][$i]['fecha_termina'];

                        $validateFecha[$i] = false;

                        if (isset($alias) && !empty($alias) && preg_match($patrónConcepto,$alias) &&
                            isset($concepto) && !empty($concepto) && preg_match($patrónConcepto,$concepto) &&
                            isset($aplicacion) && !empty($aplicacion) && preg_match($patrónConcepto,$aplicacion) &&
                            isset($monto) && !empty($monto) && preg_match($patrónNumCosto,$monto) &&
                            isset($tipo) && !empty($tipo) && preg_match($patrónConcepto,$tipo)) {
                            
                            if ($tipo == 'eventual') {
                                $validateFecha[$i] = true;
                            } else if ($tipo == 'pIndeterminado'){
                                if (isset($fecha_inicia) && !empty($fecha_inicia) && preg_match($patrónFecha,$fecha_inicia) && empty($fecha_termina)) {
                                    $validateFecha[$i] = true;
                                } else {
                                    $validateFecha[$i] = false;
                                    $dataMensaje = array(
                                        'message' => 'fecha de inicio de la promoción '.$concepto.' es invalida',
                                        'code' => 200,
                                        'status' => 'error'
                                    );
                                    break;
                                }
                            
                            } else if ($tipo == 'pDeterminado'){
                                if (isset($fecha_inicia) && !empty($fecha_inicia) && preg_match($patrónFecha,$fecha_inicia) &&
                                    isset($fecha_termina) && !empty($fecha_termina) && preg_match($patrónFecha,$fecha_termina)) {
                                    $validateFecha[$i] = true;
                                } else {
                                    $validateFecha[$i] = false;
                                    if (!isset($fecha_inicia) || empty($fecha_inicia) || !preg_match($patrónFecha,$fecha_inicia)) {
                                        $dataMensaje = array(
                                            'message' => 'fecha de inicio de la promoción '.$concepto.' es invalida',
                                            'code' => 200,
                                            'status' => 'error'
                                        );
                                        break;
                                    }

                                    if (!isset($fecha_termina) || empty($fecha_termina) || !preg_match($patrónFecha,$fecha_termina)) {
                                        $dataMensaje = array(
                                            'message' => 'fecha de finalización de la promoción '.$concepto.' es invalida',
                                            'code' => 200,
                                            'status' => 'error'
                                        );
                                        break;
                                    }
                                }
                            } 

                            if ($validateFecha[$i] == true) {
                                ++$contadorForPromo;
                            } 
                        } else {
                            if (!isset($alias) || empty($alias) || !preg_match($patrónConcepto,$alias)) {
                                $dataMensaje = array(
                                    'message' => 'alias de la promoción '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($concepto) || empty($concepto) || !preg_match($patrónConcepto,$concepto)) {
                                $dataMensaje = array(
                                    'message' => 'concepto de la promoción '.$alias.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($aplicacion) || empty($aplicacion) || !preg_match($patrónConcepto,$aplicacion)) {
                                $dataMensaje = array(
                                    'message' => 'aplicación de la promoción '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($monto) || empty($monto)) {
                                $dataMensaje = array(
                                    'message' => 'monto de aplicación de la promoción '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($tipo) || empty($tipo) || !preg_match($patrónConcepto,$tipo)) {
                                $dataMensaje = array(
                                    'message' => 'tipo de aplicación de la promoción '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                        }
                    }

                    if ($contadorForPromo == count($parametrosArray['arrayAltaPromociones'])) {
                        $validateForPromo = true;
                    }

                    if ($validateForPromo == true) {
                        $validateInsertForPromo = false;
                        $countInsertForPromo = 0;

                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                            AND emp.id = empuser.empresa AND empuser.personal = pers.id
                            AND pers.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

                        $timeActual = time();

                        for ($i=0; $i < count($parametrosArray['arrayAltaPromociones']); $i++) { 

                            $folioPromo = DB::select("SELECT IF (max(promo.folio) IS NOT NULL,(max(promo.folio)+1),1) AS folio
                                FROM ingr_catalogo_promociones AS promo JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                                WHERE promo.empresa = emp.id AND emp.empresa_token = ?
                                AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                AND pers.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);

                            $alias = $JwtAuth->encriptar($parametrosArray['arrayAltaPromociones'][$i]['alias']);
                            $concepto = $parametrosArray['arrayAltaPromociones'][$i]['concepto'];

                            if ($parametrosArray['arrayAltaPromociones'][$i]['aplicacion'] == 'cuota') {
                                $aplicacion = FALSE;
                                $monto = $JwtAuth->encriptar('$'.$parametrosArray['arrayAltaPromociones'][$i]['monto']);
                            } else {
                                $aplicacion = TRUE;
                                $monto = $JwtAuth->encriptar($parametrosArray['arrayAltaPromociones'][$i]['monto'].'%');
                            }

                            if ($parametrosArray['arrayAltaPromociones'][$i]['tipo'] == 'eventual') {
                                $tipo = 'usa';
                            } else if($parametrosArray['arrayAltaPromociones'][$i]['tipo'] == 'pIndeterminado'){
                                $tipo = 'ind';
                            } else if($parametrosArray['arrayAltaPromociones'][$i]['tipo'] == 'pDeterminado'){
                                $tipo = 'det';
                            }

                            if ($parametrosArray['arrayAltaPromociones'][$i]['fecha_inicia'] == '') {
                                $fecha_inicia = '-';
                            } else {
                                $fecha_inicia = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaPromociones'][$i]['fecha_inicia']);
                            }
                            if ($parametrosArray['arrayAltaPromociones'][$i]['fecha_termina'] == '') {
                                $fecha_termina = '-';
                            } else {
                                $fecha_termina = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaPromociones'][$i]['fecha_termina']);
                            }

                            $tokenPromo = $JwtAuth->encriptarToken($timeActual,$alias,$concepto,$aplicacion,$monto,$tipo,$fecha_inicia,$fecha_termina);

                            $newpromo = new PromocionesModelo();
                            $newpromo->token_promocion = $tokenPromo;
                            $newpromo->folio = $folioPromo[0]->folio;	
                            $newpromo->alias = $alias;	
                            $newpromo->concepto = $JwtAuth->encriptar($concepto);	
                            $newpromo->cuo_porc = $aplicacion;	
                            $newpromo->cantidad_base = $monto;	
                            $newpromo->aplicacion = $tipo;	
                            $newpromo->fecha_inicio = $fecha_inicia;
                            $newpromo->fecha_fin = $fecha_termina;	
                            $newpromo->fecha_activacion = $timeActual;
                            $newpromo->status_activacion = TRUE;
                            $newpromo->fecha_delete = '';
                            $newpromo->status = TRUE;
                            $newpromo->empresa =  $selectEmp[0]->id;
                            $salvaPromo = $newpromo->save();

                            if ($salvaPromo) {
                                $selectPromoXId = DB::select("SELECT id FROM promociones WHERE token_promocion = ?",[$tokenPromo]);
                                $obtenProducto = DB::select("SELECT id FROM catalogo_productos WHERE token_cat_productos = ?",[$parametrosArray['token_cat_productos']]);
                            
                                $datotokenPromocion = $JwtAuth->encriptar($tokenPromo.$parametrosArray['token_cat_productos'].$selectPromoXId[0]->id.$obtenProducto[0]->id);
                            
                                $insertDetallePromo = DB::table('detalle_promocion') 
                                ->insert(array(
                                    "token_detalle_promocion" => $datotokenPromocion,
                                    "promocion" => $selectPromoXId[0]->id,  
                                    "producto" => $obtenProducto[0]->id,  
                                    "servicio" => NULL,  
                                    "aplicacion" => $tipo, 	
                                    "fecha_inicio" => $fecha_inicia, 	
                                    "fecha_fin" => $fecha_termina, 	
                                    "fecha_activacion" => $timeActual, 	
                                    "status_activacion" => TRUE, 	
                                    "fecha_delete" => '', 	
                                    "status" => TRUE, 
                                ));
                            
                                if ($insertDetallePromo) {
                                    ++$countInsertForPromo;
                                } else {
                                    $dataMensaje = array(
                                        'message' => 'La vinculación de este articulo con la promoción seleccionada no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                        'code' => 200,
                                        'status' => 'error'
                                    );
                                    break; 
                                }
                            } else {
                                $dataMensaje = array(
                                    'message' => 'registro de la promoción '.$concepto.' no fue realizado debido a errores internos, intente nuevamente ó comuniquese a soporte',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }


                        }

                        if ($countInsertForPromo == count($parametrosArray['arrayAltaPromociones'])) {
                            $validateInsertForPromo = true;
                        }

                        if ($validateInsertForPromo == true) {
                            $dataMensaje = array(
                                'message' => 'La vinculación de este articulo con la promoción seleccionada se ha realizado correctamente',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        }

                    }
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

        public function vincularMercPromocion(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_promocion' => 'required|string',
                    'token_cat_productos' => 'required|string',
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

                    $listaPromocion = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                        'ingr_catalogo_promociones.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($listaPromocion as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $selectTipoPromo = DB::select("SELECT id FROM promociones WHERE token_promocion = ?",[$parametrosArray['token_promocion']]);
                        $obtenProducto = DB::select("SELECT id FROM catalogo_productos WHERE token_cat_productos = ?",[$parametrosArray['token_cat_productos']]);

                        $selectAplicacionDesc = $value->aplicacion;
                        $fechaInicioDesc = $value->fecha_inicio;
                        $fechaFinDesc = $value->fecha_fin;
                        $fecha_activacion = $value->fecha_activacion;
                        $status_activacion = $value->status_activacion;
                        $fecha_delete = $value->fecha_delete;
                        $status_desc = $value->status;

                        $datoTokenPromocion = $JwtAuth->encriptar($parametrosArray['token_promocion'].$parametrosArray['token_cat_productos'].
                            $selectAplicacionDesc.$fechaInicioDesc.$fechaFinDesc.$fecha_activacion.$status_activacion.$fecha_delete.$status_desc);

                        $insertDetalleDesc = DB::table('detalle_promocion') 
                        ->insert(array(
                            "token_detalle_promocion" => $datoTokenPromocion,
                            "promocion" => $selectTipoPromo[0]->id,  
                            "producto" => $obtenProducto[0]->id,  
                            "servicio" => NULL,  
                            "aplicacion" => $selectAplicacionDesc, 	
                            "fecha_inicio" => $fechaInicioDesc, 	
                            "fecha_fin" => $fechaFinDesc, 	
                            "fecha_activacion" => $fecha_activacion, 	
                            "status_activacion" => $status_activacion, 	
                            "fecha_delete" => $fecha_delete, 	
                            "status" => $status_desc, 
                        ));

                        if ($insertDetalleDesc) {
                            $dataMensaje = array(
                                'message' => 'La vinculación de este articulo con la promoción seleccionada se ha realizado correctamente',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'La vinculación de este articulo con la promoción seleccionada no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                'code' => 200,
                                'status' => 'error'
                            ); 
                        }

                    }
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

        public function desvincularMercPromocion(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_promocion' => 'required|string',
                    'tokenPromoDetalle' => 'required|string',
                    'token_cat_productos' => 'required|string',
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

                    $listaPromocion = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                        'ingr_catalogo_promociones.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($listaPromocion as $value) {
                        $deleteDetalleDesc = DB::table('detalle_promocion AS detpromo') 
                        ->join("promociones AS tionpromo","detpromo.promocion","=","tionpromo.id")
                        ->join("catalogo_productos AS catprod","detpromo.producto","=","catprod.id")
                        ->join("main_empresas AS emp","catprod.administrador","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("vhum_empleados_catalogo AS pers","empuser.personal","=","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                        ->where([
                            'detpromo.token_detalle_promocion' => $parametrosArray['tokenPromoDetalle'],
                            'tionpromo.token_promocion' => $parametrosArray['token_promocion'],
                            'catprod.token_cat_productos' => $parametrosArray['token_cat_productos'],
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])
                        ->limit(1)->delete();

                        if ($deleteDetalleDesc) {
                            $dataMensaje = array(
                                'message' => 'La desvinculación de este articulo con la promoción seleccionada se ha realizado correctamente',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'La desvinculación de este articulo con la promoción seleccionada no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                'code' => 200,
                                'status' => 'error'
                            ); 
                        }

                    }
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

    //servicios
        public function registrarServicioPromocion(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'arrayAltaPromociones' => 'required',
                    'token_cat_servicios' => 'required|string',
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

                    $validateForPromo = false;
                    $contadorForPromo = 0;
                    for ($i=0; $i < count($parametrosArray['arrayAltaPromociones']); $i++) { 
                        $patrónConcepto = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,:]/';
                        $patrón = '/[aA-zZ_]/';
                        $patrónNumCosto = '/^[0-9,.]*$/';
                        $patrónFecha = '/^[0-9-]*$/';

                        $patrónFecha = '/^\d{1,2}\/\d{1,2}\/\d{2,4}$/';

                        $alias = $parametrosArray['arrayAltaPromociones'][$i]['alias'];
                        $concepto = $parametrosArray['arrayAltaPromociones'][$i]['concepto'];
                        $aplicacion = $parametrosArray['arrayAltaPromociones'][$i]['aplicacion'];
                        $monto = $parametrosArray['arrayAltaPromociones'][$i]['monto'];
                        $tipo = $parametrosArray['arrayAltaPromociones'][$i]['tipo'];
                        $fecha_inicia = $parametrosArray['arrayAltaPromociones'][$i]['fecha_inicia'];
                        $fecha_termina = $parametrosArray['arrayAltaPromociones'][$i]['fecha_termina'];

                        $validateFecha[$i] = false;

                        if (isset($alias) && !empty($alias) && preg_match($patrónConcepto,$alias) &&
                            isset($concepto) && !empty($concepto) && preg_match($patrónConcepto,$concepto) &&
                            isset($aplicacion) && !empty($aplicacion) && preg_match($patrónConcepto,$aplicacion) &&
                            isset($monto) && !empty($monto) && preg_match($patrónNumCosto,$monto) &&
                            isset($tipo) && !empty($tipo) && preg_match($patrónConcepto,$tipo)) {
                            
                            if ($tipo == 'eventual') {
                                $validateFecha[$i] = true;
                            } else if ($tipo == 'pIndeterminado'){
                                if (isset($fecha_inicia) && !empty($fecha_inicia) && preg_match($patrónFecha,$fecha_inicia) && empty($fecha_termina)) {
                                    $validateFecha[$i] = true;
                                } else {
                                    $validateFecha[$i] = false;
                                    $dataMensaje = array(
                                        'message' => 'fecha de inicio de la promoción '.$concepto.' es invalida',
                                        'code' => 200,
                                        'status' => 'error'
                                    );
                                    break;
                                }
                            
                            } else if ($tipo == 'pDeterminado'){
                                if (isset($fecha_inicia) && !empty($fecha_inicia) && preg_match($patrónFecha,$fecha_inicia) &&
                                    isset($fecha_termina) && !empty($fecha_termina) && preg_match($patrónFecha,$fecha_termina)) {
                                    $validateFecha[$i] = true;
                                } else {
                                    $validateFecha[$i] = false;
                                    if (!isset($fecha_inicia) || empty($fecha_inicia) || !preg_match($patrónFecha,$fecha_inicia)) {
                                        $dataMensaje = array(
                                            'message' => 'fecha de inicio de la promoción '.$concepto.' es invalida',
                                            'code' => 200,
                                            'status' => 'error'
                                        );
                                        break;
                                    }

                                    if (!isset($fecha_termina) || empty($fecha_termina) || !preg_match($patrónFecha,$fecha_termina)) {
                                        $dataMensaje = array(
                                            'message' => 'fecha de finalización de la promoción '.$concepto.' es invalida',
                                            'code' => 200,
                                            'status' => 'error'
                                        );
                                        break;
                                    }
                                }
                            } 

                            if ($validateFecha[$i] == true) {
                                ++$contadorForPromo;
                            } 
                        } else {
                            if (!isset($alias) || empty($alias) || !preg_match($patrónConcepto,$alias)) {
                                $dataMensaje = array(
                                    'message' => 'alias de la promoción '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($concepto) || empty($concepto) || !preg_match($patrónConcepto,$concepto)) {
                                $dataMensaje = array(
                                    'message' => 'concepto de la promoción '.$alias.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($aplicacion) || empty($aplicacion) || !preg_match($patrónConcepto,$aplicacion)) {
                                $dataMensaje = array(
                                    'message' => 'aplicación de la promoción '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($monto) || empty($monto)) {
                                $dataMensaje = array(
                                    'message' => 'monto de aplicación de la promoción '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($tipo) || empty($tipo) || !preg_match($patrónConcepto,$tipo)) {
                                $dataMensaje = array(
                                    'message' => 'tipo de aplicación de la promoción '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                        }

                    }

                    if ($contadorForPromo == count($parametrosArray['arrayAltaPromociones'])) {
                        $validateForPromo = true;
                    }

                    if ($validateForPromo == true) {
                        $validateInsertForPromo = false;
                        $countInsertForPromo = 0;

                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                            AND emp.id = empuser.empresa AND empuser.personal = pers.id
                            AND pers.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

                        $timeActual = time();

                        for ($i=0; $i < count($parametrosArray['arrayAltaPromociones']); $i++) { 

                            $folioPromo = DB::select("SELECT IF (max(promo.folio) IS NOT NULL,(max(promo.folio)+1),1) AS folio
                                FROM ingr_catalogo_promociones AS promo JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                                WHERE promo.empresa = emp.id AND emp.empresa_token = ?
                                AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                AND pers.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);

                            $alias = $JwtAuth->encriptar($parametrosArray['arrayAltaPromociones'][$i]['alias']);
                            $concepto = $parametrosArray['arrayAltaPromociones'][$i]['concepto'];

                            if ($parametrosArray['arrayAltaPromociones'][$i]['aplicacion'] == 'cuota') {
                                $aplicacion = FALSE;
                                $monto = $JwtAuth->encriptar('$'.$parametrosArray['arrayAltaPromociones'][$i]['monto']);
                            } else {
                                $aplicacion = TRUE;
                                $monto = $JwtAuth->encriptar($parametrosArray['arrayAltaPromociones'][$i]['monto'].'%');
                            }

                            if ($parametrosArray['arrayAltaPromociones'][$i]['tipo'] == 'eventual') {
                                $tipo = 'usa';
                            } else if($parametrosArray['arrayAltaPromociones'][$i]['tipo'] == 'pIndeterminado'){
                                $tipo = 'ind';
                            } else if($parametrosArray['arrayAltaPromociones'][$i]['tipo'] == 'pDeterminado'){
                                $tipo = 'det';
                            }

                            if ($parametrosArray['arrayAltaPromociones'][$i]['fecha_inicia'] == '') {
                                $fecha_inicia = '-';
                            } else {
                                $fecha_inicia = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaPromociones'][$i]['fecha_inicia']);
                            }
                            if ($parametrosArray['arrayAltaPromociones'][$i]['fecha_termina'] == '') {
                                $fecha_termina = '-';
                            } else {
                                $fecha_termina = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaPromociones'][$i]['fecha_termina']);
                            }

                            $tokenPromo = $JwtAuth->encriptarToken($timeActual,$alias,$concepto,$aplicacion,$monto,$tipo,$fecha_inicia,$fecha_termina);

                            $newdesc = new PromocionesModelo();
                            $newdesc->token_promocion = $tokenPromo;
                            $newdesc->folio = $folioPromo[0]->folio;	
                            $newdesc->alias = $alias;	
                            $newdesc->concepto = $JwtAuth->encriptar($concepto);	
                            $newdesc->cuo_porc = $aplicacion;	
                            $newdesc->cantidad_base = $monto;	
                            $newdesc->aplicacion = $tipo;	
                            $newdesc->fecha_inicio = $fecha_inicia;
                            $newdesc->fecha_fin = $fecha_termina;	
                            $newdesc->fecha_activacion = $timeActual;
                            $newdesc->status_activacion = TRUE;
                            $newdesc->fecha_delete = '';
                            $newdesc->status = TRUE;
                            $newdesc->empresa =  $selectEmp[0]->id;
                            $salvaPromo = $newdesc->save();

                            if ($salvaPromo) {
                                $selectPromoXId = DB::select("SELECT id FROM promociones WHERE token_promocion = ?",[$tokenPromo]);
                                $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?",[$parametrosArray['token_cat_servicios']]);
                            
                                $datoTokenPromocion = $JwtAuth->encriptar($tokenPromo.$parametrosArray['token_cat_servicios'].$selectPromoXId[0]->id.$obtenServicio[0]->id);
                            
                                $insertDetalleDesc = DB::table('detalle_promocion') 
                                ->insert(array(
                                    "token_detalle_promocion" => $datoTokenPromocion,
                                    "promocion" => $selectPromoXId[0]->id,  
                                    "producto" => NULL,  
                                    "servicio" => $obtenServicio[0]->id,  
                                    "aplicacion" => $tipo, 	
                                    "fecha_inicio" => $fecha_inicia, 	
                                    "fecha_fin" => $fecha_termina, 	
                                    "fecha_activacion" => $timeActual, 	
                                    "status_activacion" => TRUE, 	
                                    "fecha_delete" => '', 	
                                    "status" => TRUE, 
                                ));
                            
                                if ($insertDetalleDesc) {
                                    ++$countInsertForPromo;
                                } else {
                                    $dataMensaje = array(
                                        'message' => 'La vinculación de este articulo con la promoción seleccionada no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                        'code' => 200,
                                        'status' => 'error'
                                    );
                                    break; 
                                }
                            } else {
                                $dataMensaje = array(
                                    'message' => 'registro de la promoción '.$concepto.' no fue realizado debido a errores internos, intente nuevamente ó comuniquese a soporte',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }


                        }

                        if ($countInsertForPromo == count($parametrosArray['arrayAltaPromociones'])) {
                            $validateInsertForPromo = true;
                        }

                        if ($validateInsertForPromo == true) {
                            $dataMensaje = array(
                                'message' => 'La vinculación de este articulo con la promoción seleccionada se ha realizado correctamente',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        }

                    }
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

        public function vincularServicioPromocion(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_promocion' => 'required|string',
                    'tokenPromoDetalle' => 'required|string',
                    'token_cat_servicios' => 'required|string',
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

                    $listaPromocion = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                        'ingr_catalogo_promociones.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($listaPromocion as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $selectTipoPromo = DB::select("SELECT id FROM promociones WHERE token_promocion = ?",[$parametrosArray['token_promocion']]);
                        $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?",[$parametrosArray['token_cat_servicios']]);

                        $selectAplicacionDesc = $value->aplicacion;
                        $fechaInicioDesc = $value->fecha_inicio;
                        $fechaFinDesc = $value->fecha_fin;
                        $fecha_activacion = $value->fecha_activacion;
                        $status_activacion = $value->status_activacion;
                        $fecha_delete = $value->fecha_delete;
                        $status_desc = $value->status;

                        $datoTokenPromocion = $JwtAuth->encriptar($parametrosArray['token_promocion'].$parametrosArray['token_cat_productos'].
                            $selectAplicacionDesc.$fechaInicioDesc.$fechaFinDesc.$fecha_activacion.$status_activacion.$fecha_delete.$status_desc);

                        $insertDetalleDesc = DB::table('detalle_promocion') 
                        ->insert(array(
                            "token_detalle_promocion" => $datoTokenPromocion,
                            "promocion" => $selectTipoPromo[0]->id,  
                            "producto" => NULL,  
                            "servicio" => $obtenServicio[0]->id,  
                            "aplicacion" => $selectAplicacionDesc, 	
                            "fecha_inicio" => $fechaInicioDesc, 	
                            "fecha_fin" => $fechaFinDesc, 	
                            "fecha_activacion" => $fecha_activacion, 	
                            "status_activacion" => $status_activacion, 	
                            "fecha_delete" => $fecha_delete, 	
                            "status" => $status_desc, 
                        ));

                        if ($insertDetalleDesc) {
                            $dataMensaje = array(
                                'message' => 'La vinculación de este articulo con la promoción seleccionada se ha realizado correctamente',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'La vinculación de este articulo con la promoción seleccionada no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                'code' => 200,
                                'status' => 'error'
                            ); 
                        }

                    }
                    
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

        public function desvincularServicioPromocion(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
        
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_promocion' => 'required|string',
                    'tokenPromoDetalle' => 'required|string',
                    'token_cat_servicios' => 'required|string',
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

                    $listaPromocion = PromocionesModelo::join("main_empresas AS emp","ingr_catalogo_promociones.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'ingr_catalogo_promociones.token_promocion' => $parametrosArray['token_promocion'],
                        'ingr_catalogo_promociones.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($listaPromocion as $value) {
                        $deleteDetalleDesc = DB::table('detalle_promocion AS detpromo') 
                        ->join("promociones AS tionpromo","detpromo.promocion","=","tionpromo.id")
                        ->join("catalogo_servicios AS catserv","detpromo.servicio","=","catserv.id")
                        ->join("main_empresas AS emp","catserv.administrador","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("vhum_empleados_catalogo AS pers","empuser.personal","=","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                        ->where([
                            'detpromo.token_detalle_promocion' => $parametrosArray['tokenPromoDetalle'],
                            'tionpromo.token_promocion' => $parametrosArray['token_promocion'],
                            'catserv.token_cat_servicios' => $parametrosArray['token_cat_servicios'],
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])
                        ->limit(1)->delete();

                        if ($deleteDetalleDesc) {
                            $dataMensaje = array(
                                'message' => 'La desvinculación de este articulo con la promoción seleccionada se ha realizado correctamente',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'La desvinculación de este articulo con la promoción seleccionada no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                'code' => 200,
                                'status' => 'error'
                            ); 
                        }

                    }
                    
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
    
}
