<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\DescuentosModelo;
use App\Models\ServiciosModelo;
use App\Models\ProductosModelo;
use Illuminate\Support\Facades\DB;

class INGR_DescuentosController extends Controller{
    
    public function folioMaxDescuento(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true);
        $folioMax = DescuentosModelo::max('folio');
       
        return response()->json([
            'folioCompleto' => $JwtAuth->generar($folioMax),
            'folio' => $folioMax,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }

    public function folioNewRegDescuento(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true);
        $folioMax = DescuentosModelo::max('folio');
       
        return response()->json([
            'folioCompleto' => $JwtAuth->generar($folioMax+1),
            'folio' => $folioMax+1,
            'codigo' => 200,
            'status' => 'success'
        ]);

    }

    public function listaDescuentos(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input("json");
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser, true);
        $arrayDescuentos = array();

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
                
                $listaDesc = DescuentosModelo::join("main_empresas AS emp","ingr_catalogo_descuentos.empresa","=","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                ->where([
                    "ingr_catalogo_descuentos.status_activacion" => TRUE,
                    "ingr_catalogo_descuentos.status" => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();
        
                foreach ($listaDesc as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    $arrayForeach = array(
                        "c_token" => $value->token_descuentos,
                        "folio" => "DESC-".$JwtAuth->generarFolio($value->folio),
                        "alias" => $JwtAuth->desencriptar($value->alias),
                        "concepto" => $JwtAuth->desencriptar($value->concepto),
                        "cuo_porc" => $value->cuo_porc == FALSE ? 'cuota' : 'porcentaje',
                        "cantidad_base" => $JwtAuth->desencriptar($value->cantidad_base),
                        "aplicacion" => $value->aplicacion == 'usa' ? 'eventual' : ($value->aplicacion == 'ind' ? 'indeterminado' : 'determinado'),
                        "fecha_inicio" => $value->fecha_inicio != '-' ? date('d-m-Y H:i:s',$value->fecha_inicio) : '-',
                        "fecha_fin" => $value->fecha_fin != '-' ? date('d-m-Y H:i:s',$value->fecha_fin) : '-',
                        "fecha_activacion" => date('d-m-Y H:i:s',$value->fecha_activacion),
                        "vinculacion" => false,
                    );
                    $arrayDescuentos[] = $arrayForeach; 
                }
                $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "descuentos" => $arrayDescuentos
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

    public function verDescuento(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $detalleDescuento = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_descuento' => 'required|string'
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

                $infoDescuento = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                ->where([
                    'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                    'catdescu.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();

                foreach ($infoDescuento as $detailDescuento) {
                    //da_te_default_timezone_set($detailDescuento->zona_horaria);
                    
                    $ventasDescuento = DB::select("SELECT venta_desc.id FROM ingr_ventas_descu_promo AS venta_desc
                        JOIN ingr_catalogo_ingr_catalogo_descuentos AS descu WHERE venta_desc.descuento =  descu.id
                        AND descu.token_descuentos = ?",[$detailDescuento->token_descuentos]);

                    if (count($ventasDescuento) > 0) {
                        $validateVinculo = true;
                    } else {
                        $validateVinculo = false;
                    }
                    
                    if ($detailDescuento->cuo_porc == TRUE) {
                        $cuota_percent = 'porcentaje';
                        $txtbase = explode("%",$JwtAuth->desencriptar($detailDescuento->cantidad_base));
                        $res_cantidad_base = $txtbase[0];
                    } else {
                        $cuota_percent = 'cuota';
                        $txtbase = explode("$",$JwtAuth->desencriptar($detailDescuento->cantidad_base));
                        $res_cantidad_base = $txtbase[1];
                    }

                    if ($detailDescuento->aplicacion == 'usa') {
                        $periodo_inicio = '';
                        $periodo_fin = '';
                        $aplicacion = 'eventual';
                    } else if ($detailDescuento->aplicacion == 'ind') {
                        $periodo_inicio = date('d-m-Y H:i:s',$detailDescuento->fecha_inicio);
                        $periodo_fin = '';
                        $aplicacion = 'pIndeterminado';
                    } else if ($detailDescuento->aplicacion == 'det') {
                        $periodo_inicio = date('d-m-Y H:i:s',$detailDescuento->fecha_inicio);
                        $periodo_fin = date('d-m-Y H:i:s',$detailDescuento->fecha_fin);
                        $aplicacion = 'pDeterminado';
                    } 

                    if ($detailDescuento->status_activacion == TRUE) {
                        $activacion_status = true;
                    } else {
                        $activacion_status = false;
                    }

                    $arrayServVigentes = array();
                    $arrayServVinculados = array();
                    $arrayServDeleted = array();
                    $servListServ = ServiciosModelo::join("servicios AS ltserv","catserv.servicio","=","ltserv.id")
                    ->join("sos_ps_genero AS gen","ltserv.genero","=","gen.id")
                    ->join("teci_catalogo_prodservsat AS prsrvsat","ltserv.catalogo_sat","=","prsrvsat.id")
                    //->join("unidad_medida AS umed","ltserv.medida_sat","=","umed.id")
                    ->join("main_empresas AS emp","catserv.administrador","=","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","=","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                    ->where([
                        'catserv.status' => TRUE,
                        'catserv.proceso' => FALSE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($servListServ as $value) {
                        if ($JwtAuth->desencriptar($value->imagen) =='default-servicios.jpg') {
                            $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/settings/'.$JwtAuth->desencriptar($value->imagen)));
                        } else {
                            $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/'.
                                $value->root_tkn.'/0001-cpc/catalogos/servicios/'.$value->fecha_sistema.'-'.
                                $JwtAuth->generar($value->folio_sistema).'/'.$JwtAuth->desencriptar($value->imagen)));
                        }
                        
                        $checkListaServ = DB::select("SELECT detdes.servicio,detdes.status,
                            detdes.token_detalle_descuento FROM ingr_detalle_descuento AS detdes 
                            JOIN in_egr_catalogo_servicios AS catserv JOIN ingr_catalogo_ingr_catalogo_descuentos AS descu
                            WHERE detdes.servicio = catserv.id AND catserv.token_cat_servicios = ?
                            AND detdes.descuento = descu.id AND descu.token_descuentos = ?",
                            [$value->token_cat_servicios,$detailDescuento->token_descuentos]);
                            
                        if (count($checkListaServ) == 1) {
                            if ($checkListaServ[0]->status == TRUE) {
                                $vincDescServ = true;
                                $tokenDescDetalle = $checkListaServ[0]->token_detalle_descuento;
                            } else if ($checkListaServ[0]->status == FALSE) {
                                $vincDescServ = false;
                                $tokenDescDetalle = '';
                            } 
                        } else if (count($checkListaServ) == 0) {
                            $vincDescServ = false;
                            $tokenDescDetalle = '';
                        }
                        
                        $arrayForeachVig = array(
                            "c_token" => $value->token_cat_servicios,
                            "imagen" => $logo_serv,
                            "clasificacion" => $JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                                $JwtAuth->generar($value->folio),
                            "servicio" => $JwtAuth->desencriptar($value->servicio),
                            "clave" => $value->clave,
                            "vincDescServ" => $vincDescServ,
                            "tokenDescDetalle" => $tokenDescDetalle,
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
                    $servListProd = ProductosModelo::join("sos_ps_genero AS gen","catprod.genero","=","gen.id")
                    ->join("teci_catalogo_prodservsat AS prsrvsat","catprod.catalogo_sat","=","prsrvsat.id")
                    //->join("unidad_medida","catprod.medida_sat","=","unidad_medida.id")
                    ->join("main_empresas AS emp","catprod.administrador","=","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","=","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                    ->where([
                        'catprod.status' => TRUE,
                        'catprod.uso_producto' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
        
                    foreach ($servListProd as $value) {
                        
                        $buyList = ProductosModelo::join("eegr_compras_detalle AS detcomp","catprod.id","=","detcomp.producto")
                        ->join("eegr_compras_recepcion AS recept","detcomp.id","=","recept.detalle_compra")
                        ->join("in_egr_establecimientos_almacen AS det_alm","recept.id","=","det_alm.recepcion_compra")
                        ->join("eegr_compras AS buy","detcomp.numero_compra","=","buy.id")
                        ->join("main_empresas AS emp","catprod.administrador","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("vhum_empleados_catalogo AS pers","empuser.personal","=","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                        ->where([
                            'buy.status_recepcion' => TRUE,
                            'recept.recept_status' => TRUE,
                            //'det_alm.existencia' > 0,
                            'detcomp.activo_fijo' => NULL,
                            'detcomp.activo_intangible' => NULL,
                            'catprod.token_cat_productos' => $value->token_cat_productos,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])
                        ->whereRaw('det_alm.existencia != 0')
                        ->orderBy('detcomp.id','DESC')->get();
                        
                        if (count($buyList) > 0) {
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
                            
                            $checkListaProd = DB::select("SELECT detdes.producto,detdes.status, 
                            detdes.token_detalle_descuento FROM ingr_detalle_descuento AS detdes 
                            JOIN catalogo_productos AS catprod JOIN ingr_catalogo_ingr_catalogo_descuentos AS descu
                            WHERE detdes.producto = catprod.id AND catprod.token_cat_productos = ?
                            AND detdes.descuento = descu.id AND descu.token_descuentos = ?",
                            [$value->token_cat_productos,$detailDescuento->token_descuentos]);
                            
                            if (count($checkListaProd) == 1) {
                                if ($checkListaProd[0]->status == TRUE) {
                                    $vincDescProd = true;
                                    $tokenDescDetalle = $checkListaProd[0]->token_detalle_descuento;
                                } else if ($checkListaProd[0]->status == FALSE) {
                                    $vincDescProd = false;
                                    $tokenDescDetalle = '';
                                } 
                            } else if (count($checkListaProd) == 0) {
                                $vincDescProd = false;
                                $tokenDescDetalle = '';
                            }
                            
                            $arrayForeachVig = array(
                                "c_token" => $value->token_cat_productos,
                                "imagen" => $logo_prod,
                                "clasificacion" => $JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                                    $JwtAuth->generar($value->folio),
                                "producto" => $JwtAuth->desencriptar($value->producto),
                                "clave" => $value->clave,
                                "vincDescProd" => $vincDescProd,
                                "tokenDescDetalle" => $tokenDescDetalle,
                            );
                            $arrayProdVigentes[] = $arrayForeachVig; 

                            if (count($checkListaProd) == 1) {
                                if ($checkListaProd[0]->status == TRUE) {
                                    $arrayProdVinculados[] = $arrayForeachVig; 
                                } else if ($checkListaProd[0]->status == FALSE) {
                                    $arrayProdDeleted[] = $arrayForeachVig; 
                                } 
                            }
                        }
                    }
                    
                    $arrayForeachDesc = array(
                        "token_descuentos" => $detailDescuento->token_descuentos,
                        "folio_descuento" => $JwtAuth->generar($detailDescuento->folio),	
                        "alias_descuento" => $JwtAuth->desencriptar($detailDescuento->alias),	
                        "concepto_descuento" => $JwtAuth->desencriptar($detailDescuento->concepto),	
                        "cuo_porc" => $cuota_percent,	
                        "cantidad_base" => $res_cantidad_base,	
                        "aplicacion" => $aplicacion,	
                        "periodo_inicio" => $periodo_inicio,	
                        "periodo_fin" => $periodo_fin,	
                        "status_activacion" => $activacion_status,	
                        "fecha_activacion" => date('d-m-Y H:i:s',$detailDescuento->fecha_activacion),	
                        "validateVinculo" => $validateVinculo,
                        "servicios" => $arrayServVigentes,
                        "serviciosVinculados" => $arrayServVinculados,
                        "serviciosDeleted" => $arrayServDeleted,
                        "productos" => $arrayProdVigentes,
                        "productosVinculados" => $arrayProdVinculados,
                        "productosDeleted" => $arrayProdDeleted,
                    );
                    $detalleDescuento[] = $arrayForeachDesc; 
                }

                $dataMensaje = array(
                    'datosDescuento' => $detalleDescuento,
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
    
    public function stopDescuento(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $detalleDescuento = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_descuento' => 'required|string'
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

                $infoDescuento = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                ->where([
                    'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                    'catdescu.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();

                foreach ($infoDescuento as $detailDescuento) {
                    $tiempo = time();
                    $updatedDesc = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                        'catdescu.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->update(array( 
                        "catdescu.fecha_activacion" => $tiempo,
                        "catdescu.status_activacion" => FALSE, 	
                    ));
    
                    if ($updatedDesc) {
                        $selectDescuentoDetalle = DB::select("SELECT detdes.token_detalle_descuento
                            FROM ingr_detalle_descuento AS detdes JOIN ingr_catalogo_ingr_catalogo_descuentos AS descu 
                            WHERE detdes.descuento = descu.id AND descu.token_descuentos = ?",[$parametrosArray['token_descuento']]);

                        $contadorDescuentosDetalle = 0;
                        //echo count($selectDescuentoDetalle);
                        for ($i=0; $i < count($selectDescuentoDetalle); $i++) {
                            $updateDetalleDesc = DB::table('detalle_descuento')
                            ->join("ingr_catalogo_descuentos AS descu","detalle_descuento.descuento","descu.id") 
                            ->join("main_empresas AS emp","descu.empresa","emp.id")
                            ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                            ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                            ->where(array(
                                'detalle_descuento.token_detalle_descuento' => $selectDescuentoDetalle[$i]->token_detalle_descuento, 
                                'descu.token_descuentos' => $parametrosArray['token_descuento'],
                                'descu.status' => TRUE,
                                'emp.empresa_token' => $usuario->empresa_token,
                                'users.usuario_token' => $usuario->user_token,
                            ))
                            ->update(array( 
                                "detalle_descuento.fecha_activacion" => $tiempo, 	
                                "detalle_descuento.status_activacion" => FALSE, 
                            ));

                            if ($updateDetalleDesc) {
                                ++$contadorDescuentosDetalle;
                            }

                        }

                        if ($updatedDesc && $contadorDescuentosDetalle == count($selectDescuentoDetalle)) {
                            $dataMensaje = array(
                                'message' => 'Descuento deshabilitado',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        }
                    } else {
                        $dataMensaje = array(
                            'message' => 'Descuento no deshabilitado, intente mas tarde o comuniquese a soporte',
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

    public function habilitarDescuento(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $detalleDescuento = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_descuento' => 'required|string'
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

                $infoDescuento = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                ->where([
                    'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                    'catdescu.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();

                foreach ($infoDescuento as $detailDescuento) {
                    $tiempo = time();
                    $updatedDesc = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                        'catdescu.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->update(array( 
                        "catdescu.fecha_activacion" => $tiempo,
                        "catdescu.status_activacion" => TRUE, 	
                    ));
    
                    if ($updatedDesc) {
                        $selectDescuentoDetalle = DB::select("SELECT detdes.token_detalle_descuento
                            FROM ingr_detalle_descuento AS detdes JOIN ingr_catalogo_ingr_catalogo_descuentos AS descu 
                            WHERE detdes.descuento = descu.id AND descu.token_descuentos = ?",[$parametrosArray['token_descuento']]);

                        $contadorDescuentosDetalle = 0;

                        for ($i=0; $i < count($selectDescuentoDetalle); $i++) { 

                            $updateDetalleDesc = DB::table('detalle_descuento')
                            ->join("ingr_catalogo_descuentos AS descu","detalle_descuento.descuento","descu.id") 
                            ->join("main_empresas AS emp","descu.empresa","emp.id")
                            ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                            ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                            ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                            ->where(array(
                                'detalle_descuento.token_detalle_descuento' => $selectDescuentoDetalle[$i]->token_detalle_descuento, 
                                'descu.token_descuentos' => $parametrosArray['token_descuento'],
                                'descu.status' => TRUE,
                                'emp.empresa_token' => $usuario->empresa_token,
                                'users.usuario_token' => $usuario->user_token,
                            ))
                            ->update(array( 
                                "detalle_descuento.fecha_activacion" => $tiempo, 	
                                "detalle_descuento.status_activacion" => TRUE, 
                            ));

                            if ($updateDetalleDesc) {
                                ++$contadorDescuentosDetalle;
                            }

                        }

                        if ($updatedDesc && $contadorDescuentosDetalle == count($selectDescuentoDetalle)) {
                            $dataMensaje = array(
                                'message' => 'Descuento habilitado',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        }
                    } else {
                        $dataMensaje = array(
                            'message' => 'Descuento no habilitado, intente mas tarde o comuniquese a soporte',
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

    public function listaDescuentosDeact(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input("json");
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser, true);
        $arrayDescuentos = array();

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
                
                $listaDesc = DescuentosModelo::join("main_empresas AS emp","ingr_catalogo_descuentos.empresa","=","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                ->where([
                    "ingr_catalogo_descuentos.status_activacion" => FALSE,
                    "ingr_catalogo_descuentos.status" => TRUE,
                    "emp.empresa_token" => $usuario->empresa_token,
                    "users.usuario_token" => $usuario->user_token,
                ])->get();
        
                foreach ($listaDesc as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    $arrayForeach = array(
                        "c_token" => $value->token_descuentos,
                        "folio" => $JwtAuth->generar($value->folio),
                        "alias" => $JwtAuth->desencriptar($value->alias),
                        "concepto" => $JwtAuth->desencriptar($value->concepto),
                        "cuo_porc" => $value->cuo_porc == FALSE ? 'cuota' : 'porcentaje',
                        "cantidad_base" => $JwtAuth->desencriptar($value->cantidad_base),
                        "aplicacion" => $value->aplicacion == 'usa' ? 'eventual' : ($value->aplicacion == 'ind' ? 'indeterminado' : 'determinado'),
                        "fecha_inicio" => $value->fecha_inicio != '-' ? date('d-m-Y H:i:s',$value->fecha_inicio) : '-',
                        "fecha_fin" => $value->fecha_fin != '-' ? date('d-m-Y H:i:s',$value->fecha_fin) : '-',
                        "fecha_activacion" => date('d-m-Y H:i:s',$value->fecha_activacion),
                    );
                    $arrayDescuentos[] = $arrayForeach; 
                }
                $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "descuentos" => $arrayDescuentos
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

    public function listaDescuentosDel(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input("json");
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser, true);
        $arrayDescuentos = array();

        if (!empty($parametros) && !empty($parametrosArray)) {
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
                
                $listaDesc = DescuentosModelo::join("main_empresas AS emp","ingr_catalogo_descuentos.empresa","=","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                ->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
                ->where([
                    "ingr_catalogo_descuentos.status" => FALSE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();
        
                foreach ($listaDesc as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    $arrayForeach = array(
                        "c_token" => $value->token_descuentos,
                        "folio" => $JwtAuth->generar($value->folio),
                        "alias" => $JwtAuth->desencriptar($value->alias),
                        "concepto" => $JwtAuth->desencriptar($value->concepto),
                        "fecha_delete" => date('d-m-Y H:i:s',$value->fecha_delete_desc) 
                    );
                    $arrayDescuentos[] = $arrayForeach;
                }
                $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "descuentos" => $arrayDescuentos
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
    
    public function eliminadescuento(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $detalleDescuento = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_descuento' => 'required|string'
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

                $infoDescuento = DescuentosModelo::join("ingr_ventas_descu_promo AS detvet","catdescu.id","detvet.descuento")
                ->join("main_empresas AS emp","catdescu.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                ->where([
                    'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                    'catdescu.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();

                if (count($infoDescuento) == 0) {
                    $updatedDesc = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                        'catdescu.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->update(array( 
                        "catdescu.fecha_delete" => time(),
                        "catdescu.status" => FALSE, 	
                    ));
    
                    if ($updatedDesc) {
                        $dataMensaje = array(
                            'message' => 'Descuento eliminado',
                            'code' => 200,
                            'status' => 'success'
                        ); 
                    } else {
                        $dataMensaje = array(
                            'message' => 'Descuento no eliminado, intente mas tarde o comuniquese a soporte',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                } else {
                    $dataMensaje = array(
                        'message' => 'Descuento no eliminado por vinculación a ventas, intente mas tarde o comuniquese a soporte',
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

    public function restauradescuento(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $detalleDescuento = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_descuento' => 'required|string'
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

                $updatedDesc = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                ->where([
                    'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                    'catdescu.status' => FALSE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->update(array( 
                    "catdescu.fecha_delete" => '',
                    "catdescu.status" => TRUE, 	
                ));

                if ($updatedDesc) {
                    $dataMensaje = array(
                        'message' => 'Descuento restaurado',
                        'code' => 200,
                        'status' => 'success'
                    ); 
                } else {
                    $dataMensaje = array(
                        'message' => 'Descuento no restaurado, intente mas tarde o comuniquese a soporte',
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
    
    public function eliminaPermDescuento(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $detalleDescuento = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            //validar 
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'token_descuento' => 'required|string'
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

                $infoDescuento = DescuentosModelo::join("ingr_ventas_descu_promo AS detvet","catdescu.id","detvet.descuento")
                ->join("main_empresas AS emp","catdescu.empresa","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                ->where([
                    'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                    'catdescu.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();

                if (count($infoDescuento) == 0) {

                    $selectDescuentoDetalle = DB::select("SELECT detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin,
                        detdes.fecha_activacion,detdes.status_activacion,detdes.fecha_delete,detdes.token_detalle_descuento
                        FROM ingr_detalle_descuento AS detdes JOIN ingr_catalogo_ingr_catalogo_descuentos AS descu 
                        WHERE detdes.descuento = descu.id AND descu.token_descuentos = ?",[$parametrosArray['token_descuento']]);

                    $contadorDescuentosDetalle = 0;

                    for ($i=0; $i < count($selectDescuentoDetalle); $i++) { 

                        $updateDetalleDesc = DB::table('detalle_descuento')::join("ingr_catalogo_descuentos AS descu","detalle_descuento.descuento","descu.id") 
                        ->join("main_empresas AS emp","descu.empresa","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                        ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                        ->where(array(
                            'detalle_descuento.token_detalle_descuento' => $selectDescuentoDetalle[$i]['token_detalle_descuento'], 
                            'descu.token_descuentos' => $parametrosArray['token_descuentos'],
                            'descu.status' => TRUE,
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ))->limit(1)->delete();

                        if ($updateDetalleDesc) {
                            ++$contadorDescuentosDetalle;
                        }

                    }

                    if ($contadorDescuentosDetalle == count($selectDescuentoDetalle)) {
                        $updatedDesc = DescuentosModelo::where([
                            'token_descuentos' => $parametrosArray['token_descuento'],
                        ])->limit(1)->delete();
        
                        if ($updatedDesc) {
                            $dataMensaje = array(
                                'message' => 'Descuento eliminado',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'Descuento no eliminado, intente mas tarde o comuniquese a soporte',
                                'code' => 200,
                                'status' => 'error'
                            );
                        }
                    } 
                } else {
                    $dataMensaje = array(
                        'message' => 'Descuento no eliminado por vinculación a ventas, intente mas tarde o comuniquese a soporte',
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
        try {
            $tokenDescuento = $descuento->__GET('token'); 
            //echo $tokenDescuento; exit;
            $busquedaVinc = $this->pdo->prepare("SELECT id_detalle_venta FROM detalle_venta AS detvet
                JOIN ingr_catalogo_ingr_catalogo_descuentos AS descu WHERE detvet.descuento = descu.id_descuento
                AND descu.c_token = :TokenDescuento"); 
            $busquedaVinc->bindParam("TokenDescuento",$tokenDescuento,PDO::PARAM_STR); 
            $busquedaVinc->execute(); 
            echo $busquedaVinc->rowCount();
            if ($busquedaVinc->rowCount() >= 1) {
                echo 'vinculado|';
                foreach ($busquedaVinc->fetchAll(PDO::FETCH_OBJ) as $value) {
                    $detalleV = $value->id_detalle_venta;
                    $folioDetVenta = $this->pdo->prepare("SELECT folio FROM detalle_venta WHERE id_detalle_venta = :DetalleVenta");
                    $folioDetVenta->bindParam("DetalleVenta",$detalleV,PDO::PARAM_STR);
                    $folioDetVenta->execute();
                    if ($folioDetVenta->rowCount() !=0) {
                        $resFolioVenta = $folioDetVenta->fetch(PDO::FETCH_OBJ);
                        echo GeneraCodigo::generar($resFolioVenta->folio);
                    } else {
                        echo 'errorFoundCodigo';
                        exit;
                    }
                }
            }

            if ($busquedaVinc->rowCount() == 0){
                //unix_timestamp(now())
                $fecha_delete = time();
                $eliminaDescuento = $this->pdo->prepare("DELETE FROM descuentos WHERE c_token = :TokenDescuento");
                $eliminaDescuento->bindParam("FechaDelete",$fecha_delete,PDO::PARAM_STR); 
                $eliminaDescuento->bindParam("TokenDescuento",$tokenDescuento,PDO::PARAM_STR); 
                $eliminaDescuento->execute(); 
                if ($eliminaDescuento->rowCount() == 1) {
                    echo 'eliminado'; exit;
                } else {
                    echo 'noEliminado'; exit;
                }
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    
    public function updateGeneralesDescuento(Request $request){
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
                'token_descuentos' => 'required|string',
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
                                'message' => 'fecha de inicio del descuento '.$concepto.' es invalida',
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
                                    'message' => 'fecha de inicio del descuento '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }

                            if (!isset($fecha_termina) || empty($fecha_termina) || !preg_match($patrónFecha,$fecha_termina)) {
                                $dataMensaje = array(
                                    'message' => 'fecha de finalización del descuento '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }
                        }
                    } 

                    if ($validateFecha == true) {
                        
                        $folioDescu = DB::select("SELECT IF (max(descu.folio) IS NOT NULL,(max(descu.folio)+1),1) AS folio
                            FROM ingr_catalogo_descuentos AS descu JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                            WHERE descu.empresa = emp.id AND emp.empresa_token = ?
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
                        
                        $updateDescuentos = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                        ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                        ->where([
                            'catdescu.token_descuentos' => $parametrosArray['token_descuentos'],
                            'catdescu.status' => TRUE,
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

                        if ($updateDescuentos) {
                            
                            $selectDescuentoDetalle = DB::select("SELECT detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin,
                                detdes.fecha_activacion,detdes.status_activacion,detdes.fecha_delete,detdes.token_detalle_descuento
                                FROM ingr_detalle_descuento AS detdes JOIN ingr_catalogo_ingr_catalogo_descuentos AS descu 
                                WHERE detdes.descuento = descu.id AND descu.token_descuentos = ?",[$parametrosArray['token_descuentos']]);
                            
                            $contadorDescuentosDetalle = 0;

                            for ($i=0; $i < count($selectDescuentoDetalle); $i++) { 
                                
                                $updateDetalleDesc = DB::table('detalle_descuento')::join("ingr_catalogo_descuentos AS descu","detalle_descuento.descuento","descu.id") 
                                ->join("main_empresas AS emp","descu.empresa","emp.id")
                                ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                                ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                                ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                                ->where(array(
                                    'detalle_descuento.token_detalle_descuento' => $selectDescuentoDetalle[$i]['token_detalle_descuento'], 
                                    'descu.token_descuentos' => $parametrosArray['token_descuentos'],
                                    'descu.status' => TRUE,
                                    'emp.empresa_token' => $usuario->empresa_token,
                                    'users.usuario_token' => $usuario->user_token,
                                ))
                                ->update(array( 
                                    "aplicacion" => $tipo, 	
                                    "fecha_inicio" => $fecha_inicia, 	
                                    "fecha_fin" => $fecha_termina, 	
                                ));

                                if ($updateDetalleDesc) {
                                    ++$contadorDescuentosDetalle;
                                }

                            }
    
                            if ($updateDescuentos && $contadorDescuentosDetalle == count($selectDescuentoDetalle)) {
                                $dataMensaje = array(
                                    'message' => 'La actualización de este descuento se ha realizado correctamente',
                                    'code' => 200,
                                    'status' => 'success'
                                ); 
                            } else {
                                $dataMensaje = array(
                                    'message' => 'La actualización de este descuento se ha realizado no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }
                        }
                        
                    } 
                } else {
                    if (!isset($alias) || empty($alias) || !preg_match($patrónConcepto,$alias)) {
                        $dataMensaje = array(
                            'message' => 'alias del descuento '.$concepto.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($concepto) || empty($concepto) || !preg_match($patrónConcepto,$concepto)) {
                        $dataMensaje = array(
                            'message' => 'concepto del descuento '.$alias.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($aplicacion) || empty($aplicacion) || !preg_match($patrónConcepto,$aplicacion)) {
                        $dataMensaje = array(
                            'message' => 'aplicación del descuento '.$concepto.' es invalida',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($monto) || empty($monto)) {
                        $dataMensaje = array(
                            'message' => 'monto de aplicación del descuento '.$concepto.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($tipo) || empty($tipo) || !preg_match($patrónConcepto,$tipo)) {
                        $dataMensaje = array(
                            'message' => 'tipo de aplicación del descuento '.$concepto.' es invalido',
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

    public function registraDescuento(Request $request){
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
                    AND users.empleado = pers.id",[$usuario->empresa_token,$usuario->user_token]);
                //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

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
                                'message' => 'fecha de inicio del descuento '.$concepto.' es invalida',
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
                                    'message' => 'fecha de inicio del descuento '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }

                            if (!isset($fecha_termina) || empty($fecha_termina) || !preg_match($patrónFecha,$fecha_termina)) {
                                $dataMensaje = array(
                                    'message' => 'fecha de finalización del descuento '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                            }
                        }
                    } 

                    if ($validateFecha == true) {
                        $timeActual = time();
                        $folioDescu = DB::select("SELECT IF (max(descu.folio) IS NOT NULL,(max(descu.folio)+1),1) AS folio FROM ingr_catalogo_descuentos AS descu 
                            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE descu.empresa = emp.id 
                            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
                            [$usuario->empresa_token,$usuario->user_token]);

                        $alias = $JwtAuth->encriptar($parametrosArray['alias']);
                        $concepto = $parametrosArray['concepto'];

                        $aplicacion = $parametrosArray['aplicacion'] == 'cuota' ? FALSE : TRUE;
                        $monto = $parametrosArray['aplicacion'] == 'cuota' ? $JwtAuth->encriptar('$'.$parametrosArray['monto']) : $JwtAuth->encriptar($parametrosArray['monto'].'%');
                        
                        $tipo = $parametrosArray['tipo'] == 'eventual' ? 'usa' : ($parametrosArray['tipo'] == 'pIndeterminado' ? 'ind' : 'det');
                        $fecha_inicia = $parametrosArray['fecha_inicia'] == '' ? '-' : $JwtAuth->convierteFechaEpoc($parametrosArray['fecha_inicia']);
                        $fecha_termina = $parametrosArray['fecha_termina'] == '' ? '-' : $JwtAuth->convierteFechaEpoc($parametrosArray['fecha_termina']);
                        
                        $tokenDesc = $JwtAuth->encriptarToken($timeActual,$alias,$concepto,$aplicacion,$monto,$tipo,$fecha_inicia,$fecha_termina);
                        $newdesc = new DescuentosModelo();
                        $newdesc->token_descuentos = $tokenDesc;
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
                                'message' => 'El registro de este descuento se ha realizado correctamente con el folio DESC-'.$JwtAuth->generarFolio($folioDescu[0]->folio),
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'El registro de este descuento se ha realizado no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                'code' => 200,
                                'status' => 'error'
                            );
                        }
                    } 
                } else {
                    if (!isset($alias) || empty($alias) || !preg_match($patrónConcepto,$alias)) {
                        $dataMensaje = array(
                            'message' => 'alias del descuento '.$concepto.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($concepto) || empty($concepto) || !preg_match($patrónConcepto,$concepto)) {
                        $dataMensaje = array(
                            'message' => 'concepto del descuento '.$alias.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($aplicacion) || empty($aplicacion) || !preg_match($patrónConcepto,$aplicacion)) {
                        $dataMensaje = array(
                            'message' => 'aplicación del descuento '.$concepto.' es invalida',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($monto) || empty($monto)) {
                        $dataMensaje = array(
                            'message' => 'monto de aplicación del descuento '.$concepto.' es invalido',
                            'code' => 200,
                            'status' => 'error'
                        );
                    }
                    if (!isset($tipo) || empty($tipo) || !preg_match($patrónConcepto,$tipo)) {
                        $dataMensaje = array(
                            'message' => 'tipo de aplicación del descuento '.$concepto.' es invalido',
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
        public function registrarMercDescuento(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'arrayAltaDescuentos' => 'required',
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

                    $validateForDesc = false;
                    $contadorForDesc = 0;
                    for ($i=0; $i < count($parametrosArray['arrayAltaDescuentos']); $i++) { 
                        $patrónConcepto = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,:]/';
                        $patrón = '/[aA-zZ_]/';
                        $patrónNumCosto = '/^[0-9,.]*$/';
                        $patrónFecha = '/^[0-9-]*$/';

                        $patrónFecha = '/^\d{1,2}\/\d{1,2}\/\d{2,4}$/';

                        $alias = $parametrosArray['arrayAltaDescuentos'][$i]['alias'];
                        $concepto = $parametrosArray['arrayAltaDescuentos'][$i]['concepto'];
                        $aplicacion = $parametrosArray['arrayAltaDescuentos'][$i]['aplicacion'];
                        $monto = $parametrosArray['arrayAltaDescuentos'][$i]['monto'];
                        $tipo = $parametrosArray['arrayAltaDescuentos'][$i]['tipo'];
                        $fecha_inicia = $parametrosArray['arrayAltaDescuentos'][$i]['fecha_inicia'];
                        $fecha_termina = $parametrosArray['arrayAltaDescuentos'][$i]['fecha_termina'];

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
                                        'message' => 'fecha de inicio del descuento '.$concepto.' es invalida',
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
                                            'message' => 'fecha de inicio del descuento '.$concepto.' es invalida',
                                            'code' => 200,
                                            'status' => 'error'
                                        );
                                        break;
                                    }

                                    if (!isset($fecha_termina) || empty($fecha_termina) || !preg_match($patrónFecha,$fecha_termina)) {
                                        $dataMensaje = array(
                                            'message' => 'fecha de finalización del descuento '.$concepto.' es invalida',
                                            'code' => 200,
                                            'status' => 'error'
                                        );
                                        break;
                                    }
                                }
                            } 

                            if ($validateFecha[$i] == true) {
                                ++$contadorForDesc;
                            } 
                        } else {
                            if (!isset($alias) || empty($alias) || !preg_match($patrónConcepto,$alias)) {
                                $dataMensaje = array(
                                    'message' => 'alias del descuento '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($concepto) || empty($concepto) || !preg_match($patrónConcepto,$concepto)) {
                                $dataMensaje = array(
                                    'message' => 'concepto del descuento '.$alias.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($aplicacion) || empty($aplicacion) || !preg_match($patrónConcepto,$aplicacion)) {
                                $dataMensaje = array(
                                    'message' => 'aplicación del descuento '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($monto) || empty($monto)) {
                                $dataMensaje = array(
                                    'message' => 'monto de aplicación del descuento '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($tipo) || empty($tipo) || !preg_match($patrónConcepto,$tipo)) {
                                $dataMensaje = array(
                                    'message' => 'tipo de aplicación del descuento '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                        }


                    }

                    if ($contadorForDesc == count($parametrosArray['arrayAltaDescuentos'])) {
                        $validateForDesc = true;
                    }

                    if ($validateForDesc == true) {
                        $validateInsertForDesc = false;
                        $countInsertForDesc = 0;

                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                            AND emp.id = empuser.empresa AND empuser.personal = pers.id
                            AND pers.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

                        $timeActual = time();

                        for ($i=0; $i < count($parametrosArray['arrayAltaDescuentos']); $i++) { 

                            $folioDescu = DB::select("SELECT IF (max(descu.folio) IS NOT NULL,(max(descu.folio)+1),1) AS folio
                                FROM ingr_catalogo_descuentos AS descu JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                                WHERE descu.empresa = emp.id AND emp.empresa_token = ?
                                AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                AND pers.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);

                            $alias = $JwtAuth->encriptar($parametrosArray['arrayAltaDescuentos'][$i]['alias']);
                            $concepto = $parametrosArray['arrayAltaDescuentos'][$i]['concepto'];

                            if ($parametrosArray['arrayAltaDescuentos'][$i]['aplicacion'] == 'cuota') {
                                $aplicacion = FALSE;
                                $monto = $JwtAuth->encriptar('$'.$parametrosArray['arrayAltaDescuentos'][$i]['monto']);
                            } else {
                                $aplicacion = TRUE;
                                $monto = $JwtAuth->encriptar($parametrosArray['arrayAltaDescuentos'][$i]['monto'].'%');
                            }

                            if ($parametrosArray['arrayAltaDescuentos'][$i]['tipo'] == 'eventual') {
                                $tipo = 'usa';
                            } else if($parametrosArray['arrayAltaDescuentos'][$i]['tipo'] == 'pIndeterminado'){
                                $tipo = 'ind';
                            } else if($parametrosArray['arrayAltaDescuentos'][$i]['tipo'] == 'pDeterminado'){
                                $tipo = 'det';
                            }

                            if ($parametrosArray['arrayAltaDescuentos'][$i]['fecha_inicia'] == '') {
                                $fecha_inicia = '-';
                            } else {
                                $fecha_inicia = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaDescuentos'][$i]['fecha_inicia']);
                            }
                            if ($parametrosArray['arrayAltaDescuentos'][$i]['fecha_termina'] == '') {
                                $fecha_termina = '-';
                            } else {
                                $fecha_termina = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaDescuentos'][$i]['fecha_termina']);
                            }

                            $tokenDesc = $JwtAuth->encriptarToken($timeActual,$alias,$concepto,$aplicacion,$monto,$tipo,$fecha_inicia,$fecha_termina);

                            $newdesc = new DescuentosModelo();
                            $newdesc->token_descuentos = $tokenDesc;
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
                            $newdesc->empresa = $selectEmp[0]->id;
                            $salvaDesc = $newdesc->save();

                            if ($salvaDesc) {
                                $selectDescXId = DB::select("SELECT id FROM descuentos WHERE token_descuentos = ?",[$tokenDesc]);
                                $obtenProducto = DB::select("SELECT id FROM catalogo_productos WHERE token_cat_productos = ?",[$parametrosArray['token_cat_productos']]);
        
                                $datoTokenDescuento = $JwtAuth->encriptar($tokenDesc.$parametrosArray['token_cat_productos'].$selectDescXId[0]->id.$obtenProducto[0]->id);
        
                                $insertDetalleDesc = DB::table('detalle_descuento') 
                                ->insert(array(
                                    "token_detalle_descuento" => $datoTokenDescuento,
                                    "descuento" => $selectDescXId[0]->id,  
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
        
                                if ($insertDetalleDesc) {
                                    ++$countInsertForDesc;
                                } else {
                                    $dataMensaje = array(
                                        'message' => 'La vinculación de este articulo con el descuento seleccionado no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                        'code' => 200,
                                        'status' => 'error'
                                    );
                                    break; 
                                }
                            } else {
                                $dataMensaje = array(
                                    'message' => 'registro del descuento '.$concepto.' no fue realizado debido a errores internos, intente nuevamente ó comuniquese a soporte',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            
    
                        }

                        if ($countInsertForDesc == count($parametrosArray['arrayAltaDescuentos'])) {
                            $validateInsertForDesc = true;
                        }

                        if ($validateInsertForDesc == true) {
                            $dataMensaje = array(
                                'message' => 'La vinculación de este articulo con el descuento seleccionado se ha realizado correctamente',
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

        public function vincularMercDescuento(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_descuento' => 'required|string',
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

                    $listaDescuento = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                        'catdescu.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($listaDescuento as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $selectTipoDesc = DB::select("SELECT id FROM descuentos WHERE token_descuentos = ?",[$parametrosArray['token_descuento']]);
                        $obtenProducto = DB::select("SELECT id FROM catalogo_productos WHERE token_cat_productos = ?",[$parametrosArray['token_cat_productos']]);

                        $selectAplicacionDesc = $value->aplicacion;
                        $fechaInicioDesc = $value->fecha_inicio;
                        $fechaFinDesc = $value->fecha_fin;
                        $fecha_activacion = $value->fecha_activacion;
                        $status_activacion = $value->status_activacion;
                        $fecha_delete = $value->fecha_delete;
                        $status_desc = $value->status;
 
                        $datoTokenDescuento = $JwtAuth->encriptar($parametrosArray['token_descuento'].$parametrosArray['token_cat_productos'].
                            $selectAplicacionDesc.$fechaInicioDesc.$fechaFinDesc.$fecha_activacion.$status_activacion.$fecha_delete.$status_desc);

                        $insertDetalleDesc = DB::table('detalle_descuento') 
                        ->insert(array(
                            "token_detalle_descuento" => $datoTokenDescuento,
                            "descuento" => $selectTipoDesc[0]->id,  
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
                                'message' => 'La vinculación de este articulo con el descuento seleccionado se ha realizado correctamente',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'La vinculación de este articulo con el descuento seleccionado no se realizó correctamente, intente mas tarde o comuniquese a soporte',
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

        public function desvincularMercDescuento(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_descuento' => 'required|string',
                    'tokenDescDetalle' => 'required|string',
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

                    $listaDescuento = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                        'catdescu.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($listaDescuento as $value) {
                        $deleteDetalleDesc = DB::table('detalle_descuento AS detdesc') 
                        ->join("descuentos AS discount","detdesc.descuento","=","discount.id")
                        ->join("catalogo_productos AS catprod","detdesc.producto","=","catprod.id")
                        ->join("main_empresas AS emp","catprod.administrador","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("vhum_empleados_catalogo AS pers","empuser.personal","=","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                        ->where([
                            'detdesc.token_detalle_descuento' => $parametrosArray['tokenDescDetalle'],
                            'discount.token_descuentos' => $parametrosArray['token_descuento'],
                            'catprod.token_cat_productos' => $parametrosArray['token_cat_productos'],
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])
                        ->limit(1)->delete();

                        if ($deleteDetalleDesc) {
                            $dataMensaje = array(
                                'message' => 'La desvinculación de este articulo con el descuento seleccionado se ha realizado correctamente',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'La desvinculación de este articulo con el descuento seleccionado no se realizó correctamente, intente mas tarde o comuniquese a soporte',
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
        public function registrarServicioDescuento(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'arrayAltaDescuentos' => 'required',
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

                    $validateForDesc = false;
                    $contadorForDesc = 0;
                    for ($i=0; $i < count($parametrosArray['arrayAltaDescuentos']); $i++) { 
                        $patrónConcepto = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,:]/';
                        $patrón = '/[aA-zZ_]/';
                        $patrónNumCosto = '/^[0-9,.]*$/';
                        $patrónFecha = '/^[0-9-]*$/';

                        $patrónFecha = '/^\d{1,2}\/\d{1,2}\/\d{2,4}$/';

                        $alias = $parametrosArray['arrayAltaDescuentos'][$i]['alias'];
                        $concepto = $parametrosArray['arrayAltaDescuentos'][$i]['concepto'];
                        $aplicacion = $parametrosArray['arrayAltaDescuentos'][$i]['aplicacion'];
                        $monto = $parametrosArray['arrayAltaDescuentos'][$i]['monto'];
                        $tipo = $parametrosArray['arrayAltaDescuentos'][$i]['tipo'];
                        $fecha_inicia = $parametrosArray['arrayAltaDescuentos'][$i]['fecha_inicia'];
                        $fecha_termina = $parametrosArray['arrayAltaDescuentos'][$i]['fecha_termina'];

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
                                        'message' => 'fecha de inicio del descuento '.$concepto.' es invalida',
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
                                            'message' => 'fecha de inicio del descuento '.$concepto.' es invalida',
                                            'code' => 200,
                                            'status' => 'error'
                                        );
                                        break;
                                    }

                                    if (!isset($fecha_termina) || empty($fecha_termina) || !preg_match($patrónFecha,$fecha_termina)) {
                                        $dataMensaje = array(
                                            'message' => 'fecha de finalización del descuento '.$concepto.' es invalida',
                                            'code' => 200,
                                            'status' => 'error'
                                        );
                                        break;
                                    }
                                }
                            } 

                            if ($validateFecha[$i] == true) {
                                ++$contadorForDesc;
                            } 
                        } else {
                            if (!isset($alias) || empty($alias) || !preg_match($patrónConcepto,$alias)) {
                                $dataMensaje = array(
                                    'message' => 'alias del descuento '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($concepto) || empty($concepto) || !preg_match($patrónConcepto,$concepto)) {
                                $dataMensaje = array(
                                    'message' => 'concepto del descuento '.$alias.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($aplicacion) || empty($aplicacion) || !preg_match($patrónConcepto,$aplicacion)) {
                                $dataMensaje = array(
                                    'message' => 'aplicación del descuento '.$concepto.' es invalida',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($monto) || empty($monto)) {
                                $dataMensaje = array(
                                    'message' => 'monto de aplicación del descuento '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                            if (!isset($tipo) || empty($tipo) || !preg_match($patrónConcepto,$tipo)) {
                                $dataMensaje = array(
                                    'message' => 'tipo de aplicación del descuento '.$concepto.' es invalido',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }
                        }


                    }

                    if ($contadorForDesc == count($parametrosArray['arrayAltaDescuentos'])) {
                        $validateForDesc = true;
                    }

                    if ($validateForDesc == true) {
                        $validateInsertForDesc = false;
                        $countInsertForDesc = 0;

                        $selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,pers.id AS userr,users.jerarquia_main FROM main_empresas AS emp
                            JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ?
                            AND emp.id = empuser.empresa AND empuser.personal = pers.id
                            AND pers.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);
                        //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

                        $timeActual = time();

                        for ($i=0; $i < count($parametrosArray['arrayAltaDescuentos']); $i++) { 

                            $folioDescu = DB::select("SELECT IF (max(descu.folio) IS NOT NULL,(max(descu.folio)+1),1) AS folio
                                FROM ingr_catalogo_descuentos AS descu JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users
                                WHERE descu.empresa = emp.id AND emp.empresa_token = ?
                                AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                AND pers.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token,$usuario->user_token]);

                            $alias = $JwtAuth->encriptar($parametrosArray['arrayAltaDescuentos'][$i]['alias']);
                            $concepto = $parametrosArray['arrayAltaDescuentos'][$i]['concepto'];

                            if ($parametrosArray['arrayAltaDescuentos'][$i]['aplicacion'] == 'cuota') {
                                $aplicacion = FALSE;
                                $monto = $JwtAuth->encriptar('$'.$parametrosArray['arrayAltaDescuentos'][$i]['monto']);
                            } else {
                                $aplicacion = TRUE;
                                $monto = $JwtAuth->encriptar($parametrosArray['arrayAltaDescuentos'][$i]['monto'].'%');
                            }

                            if ($parametrosArray['arrayAltaDescuentos'][$i]['tipo'] == 'eventual') {
                                $tipo = 'usa';
                            } else if($parametrosArray['arrayAltaDescuentos'][$i]['tipo'] == 'pIndeterminado'){
                                $tipo = 'ind';
                            } else if($parametrosArray['arrayAltaDescuentos'][$i]['tipo'] == 'pDeterminado'){
                                $tipo = 'det';
                            }

                            if ($parametrosArray['arrayAltaDescuentos'][$i]['fecha_inicia'] == '') {
                                $fecha_inicia = '-';
                            } else {
                                $fecha_inicia = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaDescuentos'][$i]['fecha_inicia']);
                            }
                            if ($parametrosArray['arrayAltaDescuentos'][$i]['fecha_termina'] == '') {
                                $fecha_termina = '-';
                            } else {
                                $fecha_termina = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaDescuentos'][$i]['fecha_termina']);
                            }

                            $tokenDesc = $JwtAuth->encriptarToken($timeActual,$alias,$concepto,$aplicacion,$monto,$tipo,$fecha_inicia,$fecha_termina);

                            $newdesc = new DescuentosModelo();
                            $newdesc->token_descuentos = $tokenDesc;
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
                                $selectDescXId = DB::select("SELECT id FROM descuentos WHERE token_descuentos = ?",[$tokenDesc]);
                                $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?",[$parametrosArray['token_cat_servicios']]);
                            
                                $datoTokenDescuento = $JwtAuth->encriptar($tokenDesc.$parametrosArray['token_cat_servicios'].$selectDescXId[0]->id.$obtenServicio[0]->id);
                            
                                $insertDetalleDesc = DB::table('detalle_descuento') 
                                ->insert(array(
                                    "token_detalle_descuento" => $datoTokenDescuento,
                                    "descuento" => $selectDescXId[0]->id,  
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
                                    ++$countInsertForDesc;
                                } else {
                                    $dataMensaje = array(
                                        'message' => 'La vinculación de este articulo con el descuento seleccionado no se realizó correctamente, intente mas tarde o comuniquese a soporte',
                                        'code' => 200,
                                        'status' => 'error'
                                    );
                                    break; 
                                }
                            } else {
                                $dataMensaje = array(
                                    'message' => 'registro del descuento '.$concepto.' no fue realizado debido a errores internos, intente nuevamente ó comuniquese a soporte',
                                    'code' => 200,
                                    'status' => 'error'
                                );
                                break;
                            }


                        }

                        if ($countInsertForDesc == count($parametrosArray['arrayAltaDescuentos'])) {
                            $validateInsertForDesc = true;
                        }

                        if ($validateInsertForDesc == true) {
                            $dataMensaje = array(
                                'message' => 'La vinculación de este articulo con el descuento seleccionado se ha realizado correctamente',
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

        public function vincularServicioDescuento(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_descuento' => 'required|string',
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

                    $listaDescuento = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                        'catdescu.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($listaDescuento as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);
                        $selectTipoDesc = DB::select("SELECT id FROM descuentos WHERE token_descuentos = ?",[$parametrosArray['token_descuento']]);
                        $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?",[$parametrosArray['token_cat_servicios']]);

                        $selectAplicacionDesc = $value->aplicacion;
                        $fechaInicioDesc = $value->fecha_inicio;
                        $fechaFinDesc = $value->fecha_fin;
                        $fecha_activacion = $value->fecha_activacion;
                        $status_activacion = $value->status_activacion;
                        $fecha_delete = $value->fecha_delete;
                        $status_desc = $value->status;
 
                        $datoTokenDescuento = $JwtAuth->encriptar($parametrosArray['token_descuento'].$parametrosArray['token_cat_servicios'].
                            $selectAplicacionDesc.$fechaInicioDesc.$fechaFinDesc.$fecha_activacion.$status_activacion.$fecha_delete.$status_desc);

                        $insertDetalleDesc = DB::table('detalle_descuento') 
                        ->insert(array(
                            "token_detalle_descuento" => $datoTokenDescuento,
                            "descuento" => $selectTipoDesc[0]->id,  
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
                                'message' => 'La vinculación de este servicio con el descuento seleccionado se ha realizado correctamente',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'La vinculación de este servicio con el descuento seleccionado no se realizó correctamente, intente mas tarde o comuniquese a soporte',
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

        public function desvincularServicioDescuento(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_descuento' => 'required|string',
                    'tokenDescDetalle' => 'required|string',
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

                    $listaDescuento = DescuentosModelo::join("main_empresas AS emp","catdescu.empresa","emp.id")
                    ->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
                    ->join("vhum_empleados_catalogo AS pers","empuser.personal","pers.id")
                    ->join("teci_usuarios_catalogo AS users","pers.usuario","users.id")
                    ->where([
                        'catdescu.token_descuentos' => $parametrosArray['token_descuento'],
                        'catdescu.status' => TRUE,
                        'emp.empresa_token' => $usuario->empresa_token,
                        'users.usuario_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($listaDescuento as $value) {
                        $deleteDetalleDesc = DB::table('detalle_descuento AS detdesc') 
                        ->join("descuentos AS discount","detdesc.descuento","=","discount.id")
                        ->join("in_egr_catalogo_servicios AS catserv","detdesc.servicio","=","catserv.id")
                        ->join("main_empresas AS emp","catserv.administrador","=","emp.id")
                        ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                        ->join("vhum_empleados_catalogo AS pers","empuser.personal","=","pers.id")
                        ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                        ->where([
                            'detdesc.token_detalle_descuento' => $parametrosArray['tokenDescDetalle'],
                            'discount.token_descuentos' => $parametrosArray['token_descuento'],
                            'catserv.token_cat_servicios' => $parametrosArray['token_cat_servicios'],
                            'emp.empresa_token' => $usuario->empresa_token,
                            'users.usuario_token' => $usuario->user_token,
                        ])
                        ->limit(1)->delete();

                        if ($deleteDetalleDesc) {
                            $dataMensaje = array(
                                'message' => 'La desvinculación de este servicio con el descuento seleccionado se ha realizado correctamente',
                                'code' => 200,
                                'status' => 'success'
                            ); 
                        } else {
                            $dataMensaje = array(
                                'message' => 'La desvinculación de este servicio con el descuento seleccionado no se realizó correctamente, intente mas tarde o comuniquese a soporte',
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