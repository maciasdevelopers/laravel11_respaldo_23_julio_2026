<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Models\ServiciosModelo;
use App\Models\ClientesModelo;
use App\Models\ProveedoresModelo;
use App\Models\DescuentosModelo;
use App\Models\PromocionesModelo;
use App\Models\MonedasModelo;
use App\Models\ListaPreciosModelo;
use App\Models\ClasificacionModelo;
use PDF;
use QRCode;

class INGR_ServiciosController extends Controller{
    //ingresos .Vam04Y3JkN
        public function listaServiciosVigentesIngresos(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayServVigentes = array();

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                ]);

                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 404,
                        'message' => 'Los parametros de busqueda recibidos son incorrectos'.$validate->errors(),
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                
                    $decimalesMoneda = DB::select("SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
                    JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers 
                    JOIN main_usuarios AS users WHERE emp.moneda = catmon.id AND emp.emp_token = ?
                    AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                    AND pers.usuario = users.id AND users.user_token = ?",
                    [$usuario->emp_token,$usuario->user_token]);
                
                    $servList = ServiciosModelo::join("sos_ps_genero AS gen","catserv.genero","=","gen.id")
                    ->join("teci_catalogo_prodservsat AS prsrvsat","catserv.catalogo_sat","=","prsrvsat.id")
                    //->join("unidad_medida AS umed","ltserv.medida_sat","=","umed.id")
                    ->join("main_empresas AS emp","catserv.administrador","=","emp.id")
                    ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                    ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        'catserv.status' => TRUE,
                        'catserv.proceso' => FALSE,
                        'emp.emp_token' => $usuario->emp_token,
                        'users.user_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($servList as $value) {
                    
                        if ($JwtAuth->desencriptar($value->imagen) == 'default-servicios.jpg') {
                            $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/settings/'.$JwtAuth->desencriptar($value->imagen)));
                        } else {
                            $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/'.
                                $value->root_tkn.'/0001-cpc/catalogos/servicios/'.$value->fecha_sistema.'-'.
                                $JwtAuth->generar($value->folio_sistema).'/'.$JwtAuth->desencriptar($value->imagen)));
                        }
                    
                        $arrayListaPrecios = array();
                        $impuestoArray = array();
                        $baseListaPrecios = ListaPreciosModelo::get();
                        //"content_color" => "background-color:#".$value->content_color,
                        foreach ($baseListaPrecios as $valPrecios) {
                            $selectDetalePrec = DB::select("SELECT detlp.token_det_list_precios,ROUND(detlp.precio,?) AS precio
                            FROM ingr_catalogo_lista_precios_detalle AS detlp JOIN ingr_catalogo_lista_precios AS pricelist
                            JOIN in_egr_catalogo_servicios AS catserv WHERE detlp.lista = pricelist.id
                            AND detlp.servicio = catserv.id AND pricelist.token_lista_precios = ?
                            AND catserv.token_cat_servicios = ?",
                            [$decimalesMoneda[0]->decimales,$valPrecios->token_lista_precios,$value->token_cat_servicios]);
                        
                            if (count($selectDetalePrec) > 0) {
                                $simulacion = $selectDetalePrec[0]->precio;
                                $querySelectImpuestos = DB::select("SELECT tip.token_tipoimpuestos,tip.concepto,tip.tipo,
                                cat.token_cat_impuestos,cat.ret_tras,cat.alias,cat.por_cuo,cat.importe
                                FROM cont_catalogo_impuestos_tipo AS tip JOIN cont_catalogo_impuestos AS cat
                                JOIN in_egr_impuestos_articulos AS impserv JOIN in_egr_catalogo_servicios AS catserv
                                JOIN main_empresas AS emp WHERE tip.id = cat.impuesto AND cat.id = impserv.impuestos
                                AND impserv.servicio_rel = catserv.id AND catserv.token_cat_servicios = ?
                                AND cat.empresa = emp.id AND emp.emp_token = ?",[$value->token_cat_servicios,$usuario->emp_token]);

                                if (count($querySelectImpuestos) != 0) {
                                    foreach ($querySelectImpuestos as $valueImpuest) {
                                        $token_impuesto = $valueImpuest->token_cat_impuestos;
                                    
                                        if ($valueImpuest->tipo == 001) {
                                            $tipo = 'impuestos Federales';
                                        }
                                        if ($valueImpuest->tipo == 002) {
                                            $tipo = 'impuestos Estatales';
                                        }
                                        if ($valueImpuest->tipo == 003) {
                                            $tipo = 'impuestos Locales';
                                        }
                                    
                                        if ($valueImpuest->por_cuo == FALSE) {
                                            $por_cuo = 'cuota';
                                            $importeExplode = explode("$",$valueImpuest->importe);
                                            $importe_imp = $importeExplode[1];
                                        } else {
                                            $por_cuo = 'porcentaje';
                                            $importeExplode = explode("%",$valueImpuest->importe);
                                            $importe_imp = $simulacion * ($importeExplode[0] / 100);
                                        }
                                    
                                        if ($valueImpuest->ret_tras == FALSE) {
                                            $simulacion = $simulacion - $importe_imp;
                                        } 

                                        if ($valueImpuest->ret_tras == TRUE) {
                                            $simulacion = $simulacion + $importe_imp;
                                        }

                                        $formatTotalImp = DB::select("SELECT FORMAT(?,?) AS totalSimulado",[$importe_imp,$decimalesMoneda[0]->decimales]);
                                    
                                        $arrayForeachImp = array(
                                            "token_tipoimpuestos" => $valueImpuest->token_tipoimpuestos,
                                            "token_cat_impuestos" => $valueImpuest->token_cat_impuestos,
                                            "tipo" => $tipo,
                                            "concepto" => $valueImpuest->concepto.' ('.$valueImpuest->alias.')',
                                            "importe" => $valueImpuest->importe.' ('.$por_cuo.')',
                                            "formatTotalImp" => "$".$formatTotalImp[0]->totalSimulado,
                                        );
                                        $impuestoArray[] = $arrayForeachImp;
                                    }
                                } else {
                                    $simulacion = '0.00';
                                }
                            
                                $tkn_detalle_lista = $selectDetalePrec[0]->token_det_list_precios;
                                $precio_detalle = $selectDetalePrec[0]->precio;
                                $validate_button = true;
                                //$token_impuesto = $token_impuesto;
                            } else {
                                $tkn_detalle_lista = ''; 
                                $precio_detalle = ''; 
                                $simulacion = 0;
                                $validate_button = false;
                            }
                        
                            $selectSimulation = DB::select("SELECT FORMAT(?,?) AS simulacion",[$simulacion,$decimalesMoneda[0]->decimales]);
                        
                            $arrayForeach = array(
                                "token_lista_precios" => $valPrecios->token_lista_precios,
                                "tkn_detalle_lista" => $tkn_detalle_lista, 
                                "precio_detalle" => $precio_detalle, 
                                "content_color" => "background-color:#".$valPrecios->content_color,
                                "token_impuesto" => $token_impuesto,
                                "simulacion" => $selectSimulation[0]->simulacion,
                                "validate_button" => $validate_button,
                                "impuestoArray" => $impuestoArray,
                            );
                            $arrayListaPrecios[] = $arrayForeach;
                        
                        }
                    
                        $arrayForeachVig = array(
                            "c_token" => $value->token_cat_servicios,
                            "imagen" => $logo_serv,
                            "clasificacion" => $JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                                $JwtAuth->generar($value->folio),
                            "servicio" => $JwtAuth->desencriptar($value->servicio),
                            "clave" => $value->clave,
                            "arrayListaPrecios" => $arrayListaPrecios,
                        );
                        $arrayServVigentes[] = $arrayForeachVig; 
                    }
                
                    return response()->json([
                        'datosServicio' => $arrayServVigentes,
                        'codigo' => 200,
                        'status' => 'success'
                    ]); 
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Los parametros de busqueda recibidos son inexistentes'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }

        public function viewServicioIngresos(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $proveedor = $parametros->servdata;
            $arrayClientServ = array();
            $arrayServVigentes = array();
    
            if (!empty($parametros) && !empty($parametrosArray)) {
                
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required',
                    'servdata' => 'required'
                ]);
    
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Los parametro de busqueda recibidos son incorrectos',
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $servList = ServiciosModelo::join("sos_ps_genero AS gen","catserv.genero","=","gen.id")
                    ->join("teci_catalogo_prodservsat AS prsrvsat","catserv.catalogoSAT","=","prsrvsat.id")
                    ->join("unidad_medida AS umed","catserv.medida_sat","=","umed.id")
                    ->join("teci_catalogo_monedas AS catmon","catserv.moneda","=","catmon.id")
                    ->join("main_empresas AS emp","catserv.administrador","=","emp.id")
                    ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                    ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        'catserv.token_cat_servicios' => $parametrosArray['servdata'],
                        'catserv.status' => TRUE,
                        'catserv.proceso' => FALSE,
                        'emp.emp_token' => $usuario->emp_token,
                        'users.user_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($servList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);

                        if ($JwtAuth->desencriptar($value->imagen) == 'default-servicios.jpg') {
                            $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/settings/'.$JwtAuth->desencriptar($value->imagen)));
                        } else {
                            $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/'.
                                $value->root_tkn.'/0001-cpc/catalogos/servicios/'.$value->fecha_sistema.'-'.
                                $JwtAuth->generar($value->folio_sistema).'/'.$JwtAuth->desencriptar($value->imagen)));
                        }
                        
                        $file_pdf = Storage::path('public/root/'.
                                $value->root_tkn.'/0001-cpc/catalogos/servicios/'.$value->fecha_sistema.'-'.
                                $JwtAuth->generar($value->folio_sistema).'/'.$value->fecha_sistema.'-'.
                                $JwtAuth->generar($value->folio_sistema).'.pdf');
                        
                        if (file_exists($file_pdf)) {
                            $pdf_serv = $JwtAuth->encriptaBase64($file_pdf);
                            $pdf_name = $value->fecha_sistema.'-'.$JwtAuth->generar($value->folio_sistema);
                        } else {
                            $pdf_serv = null;
                            $pdf_name = null;
                        }

                        $datPrecioBase = $JwtAuth->desencriptar($value->precioBase);
                        $datCantidad = $JwtAuth->desencriptar($value->cantidad_sim);
                        $datTipoCambio = $JwtAuth->desencriptar($value->tipo_cambio);
                        $subTotalServicios = $datPrecioBase * $datCantidad * $datTipoCambio;
                        
                        $formatSimulado = DB::select("SELECT FORMAT(?,?) AS totalSimulado",
                            [$subTotalServicios,$value->decimales]);

                        $impuestoArray = array();
                        
                        $querySelectImpuestos = DB::select("SELECT tip.token_tipoimpuestos,tip.concepto,tip.tipo,
                            cat.token_cat_impuestos,cat.alias,cat.por_cuo,cat.importe
                            FROM cont_catalogo_impuestos_tipo AS tip JOIN cont_catalogo_impuestos AS cat
                            JOIN main_empresas AS emp WHERE tip.id = cat.impuesto
                            AND cat.empresa = emp.id AND emp.emp_token = ?",[$usuario->emp_token]);
                            
                        if (count($querySelectImpuestos) != 0) {
                            foreach ($querySelectImpuestos as $valueImpuest) {
                                if ($valueImpuest->tipo == 001) {
                                    $tipo = 'impuestos Federales';
                                }
                                if ($valueImpuest->tipo == 002) {
                                    $tipo = 'impuestos Estatales';
                                }
                                if ($valueImpuest->tipo == 003) {
                                    $tipo = 'impuestos Locales';
                                }
                                $por_cuo = '';
                                $totalImp =  '';
                                if ($valueImpuest->por_cuo == TRUE) {
                                    $por_cuo = 'porcentaje';
                                    $importeBase = explode("%",$valueImpuest->importe);
                                    $totalImp = ($subTotalServicios*$importeBase[0])/100;
                                } else {
                                    $por_cuo = 'cuota';
                                    $importeBase = explode("$",$value->importe);
                                    $totalImp = floatval($importeBase[1]);
                                }
                                            
                                $formatTotalImp = DB::select("SELECT FORMAT(?,?) AS totalSimulado",[$totalImp,$value->decimales]);
                                $decodImpuestos = DB::select("SELECT cat_imp.token_cat_impuestos FROM in_egr_impuestos_articulos AS imp_art
                                    JOIN in_egr_catalogo_servicios AS catserv JOIN cont_catalogo_impuestos AS cat_imp JOIN main_empresas AS emp
                                    JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users 
                                    WHERE catserv.token_cat_servicios = ? AND catserv.id = imp_art.servicio_rel 
                                    AND imp_art.impuestos = cat_imp.id AND cat_imp.token_cat_impuestos = ?
                                    AND catserv.administrador = emp.id AND emp.emp_token = ? AND emp.id = emppers.empresa
                                    AND emppers.personal = pers.id AND pers.usuario = users.id AND users.user_token = ?",
                                [$parametrosArray['servdata'],$valueImpuest->token_cat_impuestos,$usuario->emp_token,$usuario->user_token]);
                                    
                                if (count($decodImpuestos) > 0) {
                                    $vincImp = true;
                                    $imp_art_token = $decodImpuestos[0]->token_cat_impuestos;
                                } else {
                                    $vincImp = false;
                                    $imp_art_token = '';
                                }
                                            
                                $arrayForeachImp = array(
                                    "token_tipoimpuestos" => $valueImpuest->token_tipoimpuestos,
                                    "token_cat_impuestos" => $valueImpuest->token_cat_impuestos,
                                    "tipo" => $tipo,
                                    "concepto" => $valueImpuest->concepto.' ('.$valueImpuest->alias.')',
                                    "importe" => $valueImpuest->importe.' ('.$por_cuo.')',
                                    "formatTotalImp" => "$".$formatTotalImp[0]->totalSimulado,
                                    "vincImp" => $vincImp,
                                    "imp_art_token" => $imp_art_token,
                                );
                                $impuestoArray[] = $arrayForeachImp;
                            }
                        }
                        
                        //descuentos
                            $arrayDescuentos = array();
                            $listaDesc = DescuentosModelo::join("empresas","descuentos.empresa","=","empresas.id")
                            ->join("main_empresapersonal AS emppers","empresas.id","=","emppers.empresa")
                            ->join("personal","emppers.personal","=","personal.id")
                            ->join("usuarios","personal.usuario","=","usuarios.id")
                            ->where([
                                "descuentos.status_activacion" => TRUE,
                                "descuentos.status" => TRUE,
                                'empresas.emp_token' => $usuario->emp_token,
                                'usuarios.user_token' => $usuario->user_token,
                            ])->get();
                            
                            foreach ($listaDesc as $valDesc) {
                                //da_te_default_timezone_set($value->zona_horaria);
                                if ($valDesc->cou_porc == 0) {
                                    $datCuotPorc = 'cuota';
                                } else {
                                    $datCuotPorc = 'porcentaje';
                                }

                                if ($valDesc->aplicacion == 'usa') {
                                    $aplicatcion = 'eventual'; 
                                } else if ($valDesc->aplicacion == 'ind') {
                                    $aplicatcion = 'indeterminado'; 
                                } else {
                                    $aplicatcion = 'determinado'; 
                                }
                            
                                if ($valDesc->fecha_inicio == '-') {
                                    $fecha_inicio = '-';
                                } else {
                                    $fecha_inicio = date('d-m-Y H:i:s',$valDesc->fecha_inicio);
                                }

                                if ($valDesc->fecha_fin == '-') {
                                    $fecha_fin = '-';
                                } else {
                                    $fecha_fin = date('d-m-Y H:i:s',$valDesc->fecha_fin);
                                }
                            
                               $queRDescSer = DB::select("SELECT detdescu.token_detalle_descuento	
                                    FROM descuentos AS descu JOIN detalle_descuento AS detdescu
                                    JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp 
                                    WHERE descu.token_descuentos = ? AND descu.status = TRUE 
                                    AND descu.status_activacion = TRUE AND descu.id = detdescu.descuento
                                    AND detdescu.servicio = catserv.id AND catserv.token_cat_servicios = ?
                                    AND descu.empresa = emp.id AND emp.emp_token = ?",
                                    [$valDesc->token_descuentos,$value->token_cat_servicios,$usuario->emp_token]);
                                $validateSerDesc = false;

                                if (count($queRDescSer) == 1) {
                                    $validateSerDesc = true;   
                                    $detalle_token_desc = $queRDescSer[0]->token_detalle_descuento;
                                } else {
                                    $validateSerDesc = false;
                                    $detalle_token_desc = '';
                                }
                            
                                $arrayForeach = array(
                                "c_token" => $valDesc->token_descuentos,
                                "folio" => $JwtAuth->generar($valDesc->folio),
                                "alias" => $JwtAuth->desencriptar($valDesc->alias),
                                "concepto" => $JwtAuth->desencriptar($valDesc->concepto),
                                "cou_porc" => $datCuotPorc,
                                "cantidad_base" => $JwtAuth->desencriptar($valDesc->cantidad_base),
                                "aplicacion" => $aplicatcion,
                                "fecha_inicio" => $fecha_inicio,
                                "fecha_fin" => $fecha_fin,
                                "fecha_activacion" => date('d-m-Y H:i:s',$valDesc->fecha_activacion),
                                "validateSerDesc" => $validateSerDesc,
                                "detalle_token_desc" => $detalle_token_desc,
                                );
                                $arrayDescuentos[] = $arrayForeach; 
                            }
        
                        //promociones
                            $arrayPromociones = array();
                            $listaPromo = PromocionesModelo::join("empresas","promociones.empresa","=","empresas.id")
                            ->join("main_empresapersonal AS emppers","empresas.id","=","emppers.empresa")
                            ->join("personal","emppers.personal","=","personal.id")
                            ->join("usuarios","personal.usuario","=","usuarios.id")
                            ->where([
                                "promociones.status_activacion" => TRUE,
                                "promociones.status" => TRUE,
                                'empresas.emp_token' => $usuario->emp_token,
                                'usuarios.user_token' => $usuario->user_token,
                            ])->get();
                            
                            foreach ($listaPromo as $valPromo) {
                                //da_te_default_timezone_set($valPromo->zona_horaria);
                                if ($valPromo->cou_porc == 0) {
                                    $datCuotPorc = 'cuota';
                                } else {
                                    $datCuotPorc = 'porcentaje';
                                }

                                if ($valPromo->aplicacion == 'usa') {
                                    $aplicatcion = 'eventual'; 
                                } else if ($valPromo->aplicacion == 'ind') {
                                    $aplicatcion = 'indeterminado'; 
                                } else {
                                    $aplicatcion = 'determinado'; 
                                }
                            
                                if ($valPromo->fecha_inicio == '-') {
                                    $fecha_inicio = '-';
                                } else {
                                    $fecha_inicio = date('d-m-Y H:i:s',$valPromo->fecha_inicio);
                                }

                                if ($valPromo->fecha_fin == '-') {
                                    $fecha_fin = '-';
                                } else {
                                    $fecha_fin = date('d-m-Y H:i:s',$valPromo->fecha_fin);
                                }
                            
                                $queRPromSer = DB::select("SELECT detpromo.token_detalle_promocion FROM promociones AS promo 
                                    JOIN detalle_promocion AS detpromo JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp 
                                    WHERE promo.token_promocion = ? AND promo.status = TRUE AND promo.status_activacion = TRUE 
                                    AND promo.id = detpromo.promocion AND detpromo.servicio = catserv.id
                                    AND catserv.token_cat_servicios = ? AND promo.empresa = emp.id AND emp.emp_token = ?",
                                    [$valPromo->token_promocion,$value->token_cat_servicios,$usuario->emp_token]);
                                $validateSerPromo = false;
                                if (count($queRPromSer) == 1) {
                                    $validateSerPromo = true;    
                                    $token_detalle_promo = $queRPromSer[0]->token_detalle_promocion;
                                } else {
                                    $validateSerPromo = false;
                                    $token_detalle_promo = '';
                                }
                                $arrayForeach = array(
                                "c_token" => $valPromo->token_promocion,
                                "folio" => $JwtAuth->generar($valPromo->folio),
                                "alias" => $JwtAuth->desencriptar($valPromo->alias),
                                "concepto" => $JwtAuth->desencriptar($valPromo->concepto),
                                "cou_porc" => $datCuotPorc,
                                "cantidad_base" => $JwtAuth->desencriptar($valPromo->cantidad_base),
                                "aplicacion" => $aplicatcion,
                                "fecha_inicio" => $fecha_inicio,
                                "fecha_fin" => $fecha_fin,
                                "fecha_activacion" => date('d-m-Y H:i:s',$valPromo->fecha_activacion),
                                "validateSerPromo" => $validateSerPromo,
                                "token_detalle_promo" => $token_detalle_promo,
                                );
                                $arrayPromociones[] = $arrayForeach; 
                            }

                            $listaClientes = ClientesModelo::join("personas AS client","catalogo_clientes.cliente","client.id")
                            ->join("main_empresas AS emp","catalogo_clientes.administrador","=","emp.id")
                            ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                            ->join("personal","emppers.personal","=","personal.id")
                            ->join("usuarios","personal.usuario","=","usuarios.id")
                            ->where([
                                'emp.emp_token' => $usuario->emp_token,
                                'usuarios.user_token' => $usuario->user_token,
                                'catalogo_clientes.status' => true
                            ])->get();

                            foreach ($listaClientes as $resListClient) {
                                $clientservLista = ServiciosModelo::join("serv_claves AS clavserv","catserv.id","=",
                                "clavserv.servicio_id")
                                ->join("catalogo_clientes AS catclient","clavserv.cliente","=","catclient.id")
                                ->join("personas AS people","catclient.cliente","=","people.id")
                                ->where([
                                    'catclient.token_cat_clientes' => $resListClient->token_cat_clientes,
                                    'catserv.token_cat_servicios' => $parametrosArray['servdata'],
                                    'catclient.status' => true
                                ])->get();

                                $claveAsignada = '';
                                $txt_token_serv_claves = '';
    
                                foreach ($clientservLista as $relservclient) {
                                    if ($relservclient->asigned_clave != '' && $JwtAuth->desencriptar($relservclient->asigned_clave) != '') {
                                        $claveAsignada = $JwtAuth->desencriptar($relservclient->asigned_clave);
                                        $txt_token_serv_claves = $relservclient->token_serv_claves;
                                    } else {
                                        $claveAsignada = '';
                                        $txt_token_serv_claves = ''; 
                                    }
                                }
    
                                if ($resListClient->rfc_taxId != NULL) {
                                    $dataResRfc = $JwtAuth->desencriptar($resListClient->rfc_taxId);
                                } else {
                                    $dataResRfc = $resListClient->rfc_generico;
                                }

                                if ($resListClient->denominacion_rs != '') {
                                    $nombreProv = $JwtAuth->desencriptar($resListClient->denominacion_rs);
                                } else {
                                    $nombreProv = $JwtAuth->desencriptar($resListClient->paterno)." ".
                                    $JwtAuth->desencriptar($resListClient->materno)." ".
                                    $JwtAuth->desencriptar($resListClient->nombre);
                                }
                            
                                $arrayForeach = array(
                                    "token_cat_clientes" => $resListClient->token_cat_clientes,
                                    "rfc" => $dataResRfc,
                                    "nombre" => $nombreProv,
                                    "asigned_clave" => $claveAsignada,
                                    "token_serv_claves" => $txt_token_serv_claves,
                                );
                        
                                $arrayClientServ[] = $arrayForeach;
                            }

                        //monedas
                            $arrayMonedas = array();
                            $catMonedas = MonedasModelo::all();
                            
                            foreach ($catMonedas as $valMonedas) {
                                $validateMonedavinc = '';
                                if ($valMonedas->token_monedas == $value->token_monedas) {
                                    $validateMonedavinc = true;
                                } else {
                                    $validateMonedavinc = false;
                                }

                                $arraEachMon = array(
                                    "token_monedas" => $valMonedas->token_monedas,
                                    "codigo" => $valMonedas->codigo,
                                    "moneda" => $valMonedas->moneda,
                                    "decimales" => $valMonedas->decimales,
                                    "validateMonedavinc" => $validateMonedavinc,
                                );
                                $arrayMonedas[] = $arraEachMon;
                            }

                        $arrayForeachVig = array(
                            "token_cat_servicios" => $value->token_cat_servicios,
                            "imagen" => $logo_serv,
                            "pdf" => $pdf_serv,
                            "name_docs" => $pdf_name,
                            "clasificacion" => $JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                                $JwtAuth->generar($value->folio),
                            "genero" => $value->token_genero,
                            "servicio" => $JwtAuth->desencriptar($value->servicio),
                            "clave" => $value->clave, 
                            "descripcion" => $value->descripcion, 
                            "tokenSat" => $value->token_prodservsat,
                            "token_unidad_medida" => $value->token_unidad_medida,
                            "unidad_medida" => $value->unidad_medida." (".$value->sat_clave.")", 
                            "representa" => $value->representa,
                            "token_monedas" => $value->token_monedas,
                            "moneda" => $value->codigo." - ".$value->moneda,
                            "arrayMonedas" => $arrayMonedas,
                            "datPrecioBase" => $datPrecioBase,
                            "datCantidad" => $datCantidad,
                            "datTipoCambio" => $datTipoCambio,
                            "totalSimulado" => "$".$formatSimulado[0]->totalSimulado,
                            "impuestoArray" => $impuestoArray,
                            "arrayDescuentos" => $arrayDescuentos,
                            "arrayPromociones" => $arrayPromociones,
                            "clientes" => $arrayClientServ,
                            "fechaAlta" => $JwtAuth->convierteEpocFechaHtml($value->zona_horaria,$value->fechaAlta),
                            //gmdate('Y-m-d H:i:s',$value->fechaAlta)
                        );
                        $arrayServVigentes[] = $arrayForeachVig; 
                    }
        
                    $dataMensaje = array(
                        'datosServicio' => $arrayServVigentes,
                        'code' => 200,
                        'status' => 'success'
                    );
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Los parametro de busqueda recibidos son inexistentes'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }
        
        public function simulaPrecioServicio(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_cat_servicios' => 'required|string',
                    'precio_base' => 'required|numeric',
                ]);
                
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Los parametro de busqueda recibidos son incorrectos',
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $simulacion = $parametrosArray['precio_base'];
                    $decimalesMoneda = DB::select("SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
                        JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers 
                        JOIN main_usuarios AS users WHERE emp.moneda = catmon.id AND emp.emp_token = ?
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token = ?",
                        [$usuario->emp_token,$usuario->user_token]);

                    $querySelectImpuestos = DB::select("SELECT tip.token_tipoimpuestos,tip.concepto,tip.tipo,
                        cat.token_cat_impuestos,cat.ret_tras,cat.alias,cat.por_cuo,cat.importe
                        FROM cont_catalogo_impuestos_tipo AS tip JOIN cont_catalogo_impuestos AS cat
                        JOIN in_egr_impuestos_articulos AS impserv JOIN in_egr_catalogo_servicios AS catserv
                        JOIN main_empresas AS emp WHERE tip.id = cat.impuesto AND cat.id = impserv.impuestos
                        AND impserv.servicio_rel = catserv.id AND catserv.token_cat_servicios = ?
                        AND cat.empresa = emp.id AND emp.emp_token = ?",
                        [$parametrosArray['token_cat_servicios'],$usuario->emp_token]);
                        
                    if (count($querySelectImpuestos) != 0) {
                        foreach ($querySelectImpuestos as $valueImpuest) {
                            if ($valueImpuest->por_cuo == FALSE) {
                                $importeExplode = explode("$",$valueImpuest->importe);
                                $importe_imp = $importeExplode[1];
                            } else {
                                $importeExplode = explode("%",$valueImpuest->importe);
                                $importe_imp = $simulacion * ($importeExplode[0] / 100);
                            }

                            if ($valueImpuest->ret_tras == FALSE) {
                                $simulacion = $simulacion - $importe_imp;
                            } 
                            
                            if ($valueImpuest->ret_tras == TRUE) {
                                $simulacion = $simulacion + $importe_imp;
                            }
                        }
                    } else {
                        $simulacion = '0.00';
                    }
                    $selectSimulation = DB::select("SELECT FORMAT(?,?) AS simulacion",[$simulacion,$decimalesMoneda[0]->decimales]);
                    $dataMensaje = array(
                        'status' => 'success',
                        'code' => 200,
                        'simulacion' => $selectSimulation[0]->simulacion, 
                    ); 
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Los parametro de busqueda recibidos son inexistentes'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }

        public function downloadServicioIngresosPdf(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $proveedor = $parametros->servdata;
            $arrayClientServ = array();
            $arrayServVigentes = array();
    
            if (!empty($parametros) && !empty($parametrosArray)) {
                
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required',
                    'servdata' => 'required'
                ]);
    
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Los parametro de busqueda recibidos son incorrectos',
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $servList = ServiciosModelo::join("servicios AS ltserv","catserv.servicio","=","ltserv.id")
                    ->join("sos_ps_genero AS gen","ltserv.genero","=","gen.id")
                    ->join("teci_catalogo_prodservsat AS prsrvsat","ltserv.catalogoSAT","=","prsrvsat.id")
                    //->join("unidad_medida AS umed","ltserv.medida_sat","=","umed.id")
                    ->join("main_empresas AS emp","catserv.administrador","=","emp.id")
                    ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                    ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        'catserv.token_cat_servicios' => $parametrosArray['servdata'],
                        'catserv.status' => TRUE,
                        'catserv.proceso' => FALSE,
                        'emp.emp_token' => $usuario->emp_token,
                        'users.user_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($servList as $value) {
                        $pdf_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/'.
                            $value->root_tkn.'/0001-cpc/catalogos/servicios/'.$JwtAuth->generar($value->clasificacion).'-'.
                            $JwtAuth->generar($value->folio_genero).'-'.$JwtAuth->generar($value->folio).'-'.
                            $value->fechaAlta.'/'.$JwtAuth->desencriptar($value->imagen).'.pdf'));
                       
                        $dompdf = \PDF::loadView($pdf_serv);
                        return response()->download($dompdf);

                        //$dompdf->setPaper("A2", "portrait");
                        //$dompdf->render();
                        //$contenidoPDF = $dompdf->output();
                    }
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Los parametro de busqueda recibidos son inexistentes'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }

        public function actualizaGeneralesServicioIngresos(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_cat_servicio' => 'required|string',
                    'fechaAlta' => 'required|string',
                    'clasificacion' => 'required|string',
                    'genero' => 'required|string',
                    'clave_sat' => 'required|numeric',
                    'concepto' => 'required|string',
                ]);
                
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La infomación que ha intantado registrar es invalida 2'.$validate->errors(),
                        'errors' => $validate->errors()
                    );
                } else {
                    //echo $parametrosArray['concepto'];exit;
                    
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,emp.zona_horaria FROM main_empresas AS emp  
                    JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE emp.emp_token = ? 
                    AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                    AND pers.usuario = users.id AND users.user_token= ?",[$usuario->emp_token,$usuario->user_token]);
                    //echo $selectEmp[0]->id;
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

                    $folioServ = DB::select("SELECT COUNT(catserv.id) AS folio FROM catalogo_servicios AS catserv 
                        JOIN servicios AS listServ JOIN sos_ps_genero AS gen JOIN main_empresas AS emp 
                        JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users 
                        WHERE catserv.servicio = listServ.id AND listServ.genero = gen.id AND gen.token_genero = ?
                        AND catserv.administrador = emp.id AND emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token= ?",[$parametrosArray['genero'],$usuario->emp_token,$usuario->user_token]);

                    $folioAsignadoServ = DB::select("SELECT catserv.folio FROM catalogo_servicios AS catserv 
                        JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users 
                        WHERE catserv.token_cat_servicios = ? AND catserv.administrador = emp.id AND emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token= ?",[$parametrosArray['token_cat_servicio'],$usuario->emp_token,$usuario->user_token]);

                    $selectGenero = DB::select("SELECT gen.id FROM sos_ps_genero AS gen JOIN servicios AS listServ
                        JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users 
                        WHERE catserv.servicio = listServ.id AND listServ.genero = gen.id
                        AND catserv.administrador = emp.id AND emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token= ?",[$usuario->emp_token,$usuario->user_token]);

                    $clasifServ = DB::select("SELECT id FROM clasificacion WHERE token_clascificacion = ?",[$parametrosArray['clasificacion']]);
                    //echo $clasifServ[0]->id;

                    if ($selectGenero[0]->id == $clasifServ[0]->id) {
                        $nuevofolio = $folioAsignadoServ[0]->folio;
                    } else {
                        $nuevofolio = $folioServ[0]->folio+1;
                    }

                    $genroServ = DB::select("SELECT id,folio_genero,concepto FROM genero WHERE token_genero = ?",[$parametrosArray['genero']]);
                    //$genroServ[0]->id;

                    $claveSat = DB::select("SELECT id,descripcion FROM teci_catalogo_prodservsat WHERE clave = ?",[$parametrosArray['clave_sat']]);
                    //echo " claveSat ".$claveSat[0]->id;

                    $fechaAlta = $JwtAuth->convierteFechaEpoc($parametrosArray['fechaAlta']);
                    //echo $fechaAlta;
   
                    $conceptoServ = $JwtAuth->encriptar($parametrosArray['concepto']);

                    $upDateServicio = ServiciosModelo::join("servicios AS serv","catserv.servicio","=","serv.id")
                    ->join("main_empresas AS emp","catserv.administrador","=","emp.id")
                    ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                    ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        'catserv.status' => TRUE,
                        'catserv.token_cat_servicios' => $parametrosArray['token_cat_servicio'],
                        'emp.emp_token' => $usuario->emp_token,
                        'users.user_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                        array(                           
                            "serv.servicio" => $conceptoServ, 
                            "serv.clasificacion" => $clasifServ[0]->id,
                            "serv.genero" => $genroServ[0]->id,
                            "serv.catalogoSAT" => $claveSat[0]->id,
                            "catserv.fechaAlta" => $fechaAlta,
                            "catserv.folio" => $nuevofolio
                        )
                    );

                    if ($upDateServicio) {
                        $dataMensaje = array(
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Datos generales de este servicio actualizados satisfactoriamente'
                        );
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Datos generales de este servicio no fueron actualizados debido a problemas internos, comuniquese a soporte para más información'
                        );
                    }
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Los informacion que intenta modificar es invalida o inexistente'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }

        public function vincularServicioImpuesto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_cat_servicio' => 'required|string',
                    'token_cat_impuestos' => 'required|string',
                ]);
   
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La infomación que ha intantado registrar es invalida',
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $obtenImpuesto = DB::select("SELECT id FROM catalogo_impuestos WHERE token_cat_impuestos = ?",[$parametrosArray['token_cat_impuestos']]);
                    $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?",[$parametrosArray['token_cat_servicio']]);

                    if (count($obtenImpuesto) == 1 && count($obtenServicio) == 1) {
                        $tkn_imp_serv = $JwtAuth->encriptarToken(time(),$parametrosArray['token_cat_impuestos'],$parametrosArray['token_cat_servicio']);
                        $insertaImp_art = DB::table('in_egr_impuestos_articulos') 
                        ->insert(array(
                            "token_impuestos_articulos" => $tkn_imp_serv,
                            "producto_rel" => NULL, 
                            "servicio_rel" => $obtenServicio[0]->id, 
                            "impuestos" => $obtenImpuesto[0]->id,
                        ));
                    
                        if ($insertaImp_art) {
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Relación del impuesto seleccionado con este servicio registrada satisfactoriamente'
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Relación del impuesto seleccionado con este servicio no fue registrada debido a problemas internos, comuniquese a soporte para más información'
                            );
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'impuesto inexistente'
                        );
                    }
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Los informacion que intenta modificar es invalida o inexistente'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }

        public function desvincularServicioImpuesto(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_cat_servicio' => 'required|string',
                    'token_cat_impuestos' => 'required|string',
                    'imp_art_token' => 'required|string',
                ]);
   
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La infomación que ha intantado registrar es invalida',
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $obtenImpuesto = DB::select("SELECT id FROM catalogo_impuestos WHERE token_cat_impuestos = ?",[$parametrosArray['token_cat_impuestos']]);
                    $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?",[$parametrosArray['token_cat_servicio']]);
                    $obtenRelServImp = DB::select("SELECT id FROM in_egr_impuestos_articulos WHERE token_impuestos_articulos = ?",[$parametrosArray['imp_art_token']]);

                    if (count($obtenImpuesto) == 1 && count($obtenServicio) == 1 && count($obtenRelServImp) == 1) {
                        $deleteImp_art = DB::table('in_egr_impuestos_articulos AS imp_serv') 
                        ->join("catalogo_servicios AS catserv","imp_serv.servicio_rel","catserv.id")
                        ->join("cont_catalogo_impuestos AS catimp","imp_serv.impuestos","catimp.id")
                        ->where([
                            "serv_key.token_impuestos_articulos" => $parametrosArray['imp_art_token'],
                            "catserv.token_cat_servicios" => $parametrosArray['token_cat_servicio'],
                            "catimp.token_impuestos_articulos" => $parametrosArray['token_cat_impuestos'],
                        ])
                        ->limit(1)->delete();
                    
                        if ($deleteImp_art) {
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Relación del impuesto seleccionado con este servicio eliminada satisfactoriamente'
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Relación del impuesto seleccionado con este servicio no fue eliminada debido a problemas internos, comuniquese a soporte para más información'
                            );
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'impuesto inexistente'
                        );
                    }
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Los informacion que intenta modificar es invalida o inexistente'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }

        public function actualizaClienteClavesServicio(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
         
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_cat_servicio' => 'required|string',
                    'tknCliente' => 'required|string',
                    'serv_claveTkn' => 'required|string',
                    'clave' => 'required|string',
                ]);
   
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La infomación que ha intantado registrar es invalida',
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    $obtenCliente = DB::select("SELECT token_cat_clientes FROM catalogo_clientes WHERE token_cat_clientes = ?",[$parametrosArray['tknCliente']]);
                    $obtenServicio = DB::select("SELECT token_cat_servicios FROM catalogo_servicios WHERE token_cat_servicios = ?",[$parametrosArray['token_cat_servicio']]);

                    if (count($obtenCliente) == 1 && count($obtenServicio) == 1) {
                        $upDateServicio = DB::table('serv_claves AS serv_key')
                        ->join("catalogo_servicios AS catserv","serv_key.servicio_id","=","catserv.id")
                        ->join("catalogo_clientes AS catclient","serv_key.cliente","=","catclient.id")
                        ->join("main_empresas AS emp","catserv.administrador","=","emp.id")
                        ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                        ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                        ->join("main_usuarios AS users","pers.usuario","=","users.id")
                        ->where([
                            'serv_key.token_serv_claves' => $parametrosArray['serv_claveTkn'],
                            'catclient.token_cat_clientes' => $parametrosArray['tknCliente'],
                            'catserv.status' => TRUE,
                            'catserv.token_cat_servicios' => $parametrosArray['token_cat_servicio'],
                            'emp.emp_token' => $usuario->emp_token,
                            'users.user_token' => $usuario->user_token,
                        ])
                        ->limit(1)->update(
                            array(                           
                                "serv_key.asigned_clave" => $JwtAuth->encriptar($parametrosArray['clave']),
                            )
                        );
    
                        if ($upDateServicio) {
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Relación de cliente con este servicio actualizados satisfactoriamente'
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Relación de cliente con este servicio no fue actualizada debido a problemas internos, comuniquese a soporte para más información'
                            );
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'cliente inexistente'
                        );
                    }
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Los informacion que intenta modificar es invalida o inexistente'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }

        public function newClienteClavesServicio(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
         
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_cat_servicio' => 'required|string',
                    'tknCliente' => 'required|string',
                    'clave' => 'required|string',
                ]);
   
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La infomación que ha intantado registrar es invalida',
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);
                    $obtenCliente = DB::select("SELECT id FROM catalogo_clientes WHERE token_cat_clientes = ?",[$parametrosArray['tknCliente']]);
                    $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?",[$parametrosArray['token_cat_servicio']]);
                    $tkn_clavesServ = $JwtAuth->encriptarToken(time(),$parametrosArray['token_cat_servicio'],$parametrosArray['tknCliente']);

                    if (count($obtenCliente) == 1) {
                        $insertaClaves = DB::table('serv_claves') 
                        ->insert(array(
                            "token_serv_claves" =>  $tkn_clavesServ,
                            "servicio_id" => $obtenServicio[0]->id,
                            "cliente" => $obtenCliente[0]->id,
                            "asigned_clave" => $JwtAuth->encriptar($parametrosArray['clave']),
                            "asigned_clave" => $JwtAuth->encriptar($parametrosArray['clave']),
                            "periodicidad_c_v" => NULL, 
                            "notificacion_c_v" => NULL,	
                            "inicio_periodo" => NULL,	
                            "fin_periodo" => NULL,
                            "status_c_v" => NULL
                        ));
                    
                        if ($insertaClaves) {
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Relación de cliente con este servicio guradada satisfactoriamente'
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Relación de cliente con este servicio no fue guardada debido a problemas internos, comuniquese a soporte para más información'
                            );
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'cliente inexistente'
                        );
                    }
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Los informacion que intenta modificar es invalida o inexistente'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }

        public function deleteClienteClavesServicio(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
         
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'token_cat_servicio' => 'required|string',
                    'tknCliente' => 'required|string',
                    'serv_claveTkn' => 'required|string',
                ]);
   
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La infomación que ha intantado registrar es invalida'.$validate->errors(),
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 

                    $obtenCliente = DB::select("SELECT token_cat_clientes FROM catalogo_clientes WHERE token_cat_clientes = ?",[$parametrosArray['tknCliente']]);
                    $obtenServicio = DB::select("SELECT token_cat_servicios FROM catalogo_servicios WHERE token_cat_servicios = ?",[$parametrosArray['token_cat_servicio']]);

                    if (count($obtenCliente) == 1 && count($obtenServicio) == 1) {
                        $deleteServicio = DB::table('serv_claves AS serv_key') 
                        ->join("catalogo_servicios AS catserv","serv_key.servicio_id","catserv.id")
                        ->join("catalogo_clientes AS catclient","serv_key.cliente","catclient.id")
                        ->where([
                            "serv_key.token_serv_claves" => $parametrosArray['serv_claveTkn'],
                            "catserv.token_cat_servicios" => $parametrosArray['token_cat_servicio'],
                            "catclient.token_cat_clientes" => $parametrosArray['tknCliente'],
                        ])
                        ->limit(1)->delete();
                    
                        if ($deleteServicio) {
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Relación de cliente con este servicio eliminada satisfactoriamente'
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'Relación de cliente con este servicio no fue eliminada debido a problemas internos, comuniquese a soporte para más información'
                            );
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'cliente inexistente'
                        );
                    }
                }
            } else {
                $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Los informacion que intenta modificar es invalida o inexistente'
                );
            }
            return response()->json($dataMensaje, $dataMensaje['code']);
        }

        public function deleteServicioIngresos(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $usuario = $JwtAuth->checkToken($parametros->user_token,true); 

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'servdata' => 'required|string'
                ]);
    
                if ($validate->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'elementos de busqueda invalidos',
                        'errors' => $validate->errors()
                    ]);
                } else {
                    $obtenVentaServ = DB::select("SELECT * FROM detalle_venta AS detvent JOIN in_egr_catalogo_servicios AS catserv 
                        JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users 
                        WHERE detvent.servicio = catserv.id AND catserv.token_cat_servicios = ? AND catserv.administrador = emp.id 
                        AND emp.emp_token = ? AND emp.id = emppers.empresa AND emppers.personal = pers.id AND pers.usuario = users.id 
                        AND users.user_token = ?",[$parametrosArray['servdata'],$usuario->emp_token,$usuario->user_token]);

                    if (count($obtenVentaServ) == 0) {
                        $prodDeleteList = ServiciosModelo::join("main_empresas AS emp","catserv.administrador","=","emp.id")
                        ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                        ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                        ->join("main_usuarios AS users","pers.usuario","=","users.id")
                        ->where([
                            'catserv.token_cat_servicios' => $parametrosArray['servdata'],
                            'emp.emp_token' => $usuario->emp_token,
                            'users.user_token' => $usuario->user_token,
                        ])
                        ->limit(1)->update(
                            array(
                                'catserv.fecha_delete_serv' => time(),
                                'catserv.status' => FALSE
                            )
                        );
                    
                        if ($prodDeleteList) {
                            return response()->json([
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'servicio eliminado satisfactoriamente'
                            ]);
                        } else {
                            return response()->json([
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'servicio no eliminado'
                            ]);
                        }
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'servicio no eliminado, esta vinculado a compras'
                        ]);
                    }
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'datos incorrectos'
                ]);
            }
        }

        public function listaServiciosEliminadosIngresos(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $usuario = $JwtAuth->checkToken($parametros->user_token,true); 
            $arrayServDeleted = array();
            $servList = ServiciosModelo::join("sos_ps_genero AS gen","catserv.genero","=","gen.id")
            ->join("teci_catalogo_prodservsat AS prsrvsat","catserv.catalogo_sat","=","prsrvsat.id")
            //->join("unidad_medida AS umed","catserv.medida_sat","=","umed.id")
            ->join("main_empresas AS emp","catserv.administrador","=","emp.id")
            ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
            ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
            ->join("main_usuarios AS users","pers.usuario","=","users.id")
            ->where([
                'catserv.status' => FALSE,
                'catserv.proceso' => FALSE,
                'emp.emp_token' => $usuario->emp_token,
                'users.user_token' => $usuario->user_token,
            ])->get();
            
            foreach ($servList as $value) {
                //da_te_default_timezone_set($value->zona_horaria);

                if ($JwtAuth->desencriptar($value->imagen) =='default-servicios.jpg') {
                    $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/settings/'.$JwtAuth->desencriptar($value->imagen)));
                } else {
                    $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/root/'.
                        $value->root_tkn.'/0001-cpc/catalogos/servicios/'.$value->fecha_sistema.'-'.
                        $JwtAuth->generar($value->folio_sistema).'/'.$JwtAuth->desencriptar($value->imagen)));
                }

                $arrayForeachVig = array(
                    "c_token" => $value->token_cat_servicios,
                    "imagen" => $logo_serv,
                    "clasificacion" => $JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                        $JwtAuth->generar($value->folio),
                    "servicio" => $JwtAuth->desencriptar($value->servicio),
                    "clave" => $value->clave,
                    "fechaDelete" => date('d-m-Y H:i:s',$value->fecha_delete_serv)
                );
                $arrayServDeleted[] = $arrayForeachVig; 
            }

            return response()->json([
                'datosServicio' => $arrayServDeleted,
                'codigo' => 200,
                'status' => 'success'
            ]); 
        }

        public function restartServicioIngresos(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $usuario = $JwtAuth->checkToken($parametros->user_token,true); 

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'servdata' => 'required|string'
                ]);
    
                if ($validate->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'elementos de busqueda invalidos',
                        'errors' => $validate->errors()
                    ]);
                } else {
                    $prodDeleteList = ServiciosModelo::join("main_empresas AS emp","catserv.administrador","=","emp.id")
                    ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                    ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        'catserv.token_cat_servicios' => $parametrosArray['servdata'],
                        'emp.emp_token' => $usuario->emp_token,
                        'users.user_token' => $usuario->user_token,
                    ])
                    ->limit(1)->update(
                        array(
                            'catserv.fecha_delete_serv' => '',
                            'catserv.status' => TRUE
                        )
                    );
                
                    if ($prodDeleteList) {
                        return response()->json([
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'servicio eliminado satisfactoriamente'
                        ]);
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'servicio no eliminado'
                        ]);
                    }
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'datos incorrectos'
                ]);
            }
        }

        public function deleteDeadServicioIngresos(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $usuario = $JwtAuth->checkToken($parametros->user_token,true); 

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'servdata' => 'required|string'
                ]);
    
                if ($validate->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'elementos de busqueda invalidos',
                        'errors' => $validate->errors()
                    ]);
                } else {
                    $obtenVentaServ = DB::select("SELECT * FROM detalle_venta AS detvent JOIN in_egr_catalogo_servicios AS catserv 
                        JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users 
                        WHERE detvent.servicio = catserv.id AND catserv.token_cat_servicios = ? AND catserv.administrador = emp.id 
                        AND emp.emp_token = ? AND emp.id = emppers.empresa AND emppers.personal = pers.id AND pers.usuario = users.id 
                        AND users.user_token = ?",[$parametrosArray['servdata'],$usuario->emp_token,$usuario->user_token]);

                    if (count($obtenVentaServ) == 0) {

                        
                        $provservLista = ServiciosModelo::join("serv_claves AS clavserv","catserv.id","=","clavserv.servicio_id")
                        ->where([
                            'catserv.token_cat_servicios' => $parametrosArray['servdata']
                        ])->count();
    
                        if ($provservLista >= 1) {
                            $deleteProdClaveServ = ServiciosModelo::join("serv_claves AS clavserv","catserv.id","=","clavserv.servicio_id")
                            ->where([
                                'catserv.token_cat_servicios' => $parametrosArray['servdata']
                            ])->limit(1)->delete();
                            
                            if ($deleteProdClaveServ) {
                                $prodDeleteList = ServiciosModelo::join("main_empresas AS emp","catserv.administrador","=","emp.id")
                                ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                                ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                                ->join("main_usuarios AS users","pers.usuario","=","users.id")
                                ->where([
                                    'catserv.token_cat_servicios' => $parametrosArray['servdata'],
                                    'emp.emp_token' => $usuario->emp_token,
                                    'users.user_token' => $usuario->user_token,
                                ])
                                ->limit(1)->update(
                                    array(
                                        'catserv.fecha_delete_serv' => time(),
                                        'catserv.status' => FALSE
                                    )
                                );
                            
                                if ($prodDeleteList) {
                                    return response()->json([
                                        'status' => 'success',
                                        'code' => 200,
                                        'message' => 'servicio eliminado satisfactoriamente'
                                    ]);
                                } else {
                                    return response()->json([
                                        'status' => 'error',
                                        'code' => 200,
                                        'message' => 'servicio no eliminado'
                                    ]);
                                }
                            } else {
                                return response()->json([
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'relación de servicio con proveedor no eliminada'
                                ]);
                            }
                        } else {
                            $prodDeleteList = ServiciosModelo::join("main_empresas AS emp","catserv.administrador","=","emp.id")
                            ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                            ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                            ->join("main_usuarios AS users","pers.usuario","=","users.id")
                            ->where([
                                'catserv.token_cat_servicios' => $parametrosArray['servdata'],
                                'emp.emp_token' => $usuario->emp_token,
                                'users.user_token' => $usuario->user_token,
                            ])
                            ->limit(1)->update(
                                array(
                                    'catserv.fecha_delete_serv' => time(),
                                    'catserv.status' => FALSE
                                )
                            );
                        
                            if ($prodDeleteList) {
                                return response()->json([
                                    'status' => 'success',
                                    'code' => 200,
                                    'message' => 'servicio eliminado satisfactoriamente'
                                ]);
                            } else {
                                return response()->json([
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'servicio no eliminado'
                                ]);
                            }
                        }
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'servicio no eliminado, esta vinculado a compras'
                        ]);
                    }
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'datos incorrectos'
                ]);
            }
        }

        public function registroServicioIngresos(Request $request){
            $JwtAuth = new \JwtAuth();
            $imageServ = $request->file('image');
            $jsonServ = $request->input('servdata');
            $parametros = json_decode($jsonServ);
            $parametrosArray = json_decode($jsonServ,true);
            
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
                    'fechaAlta' => 'required|string',
                    'clasificacion' => 'required|string',
                    'genero' => 'required|string',
                    'clave_sat' => 'required|string',
                    'tknSat' => 'required|string',
                    'concepto' => 'required|string',
                    'token_unidad_medida' => 'required|string', 
                    'token_monedaServAlta' => 'required|string', 
                    'txttipoCam' => 'required|string',
                    'txtCantSim' => 'required|string',
                    'txtPrecioB' => 'required|string',

                    'txtSubtotal' => 'required|numeric',//

                    'catImpVigArray' => 'array',
                    'arrayDescuentos' => 'array',
                    'arrayPromociones' => 'array',
                    'arrayAltaDescuentos' => 'array',
                    'arrayAltaPromociones' => 'array',
                    'arrayClaveClientServ' => 'array',

                ]);
   
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La infomación que ha intantado registrar es invalida'.$validate->errors(),
                        'errors' => $validate->errors()
                    );
                } else {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
                    
                    $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,emp.zona_horaria,people.paterno,
                        people.materno,people.nombre,people.denominacion_rs,people.sitio_web FROM main_empresas AS emp 
	                    JOIN personas AS people JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers 
                        JOIN main_usuarios AS users WHERE emp.persona = people.id AND emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token= ?",[$usuario->emp_token,$usuario->user_token]);
                    //echo $selectEmp[0]->id;
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                    //folio de sistema
                    
                    $folioSistema = DB::select("SELECT IF (max(catserv.folio_sistema) IS NOT NULL,(max(catserv.folio_sistema)+1),1) AS folio
					    FROM catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN empresapersonal AS empper 
					    JOIN vhum_personal AS pers JOIN main_usuarios AS users
					    WHERE catserv.administrador = emp.id AND emp.emp_token = ?
					    AND emp.id = empper.empresa AND empper.personal = pers.id
					    AND pers.usuario = users.id AND users.user_token = ?",[$usuario->emp_token,$usuario->user_token]);
                    //folio de servicio
                    $folioServ = DB::select("SELECT COUNT(catserv.id) AS folio FROM catalogo_servicios AS catserv 
                        JOIN servicios AS listServ JOIN sos_ps_genero AS gen JOIN main_empresas AS emp 
                        JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users 
                        WHERE catserv.servicio = listServ.id AND listServ.genero = gen.id AND gen.token_genero = ?
                        AND catserv.administrador = emp.id AND emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token= ?",[$parametrosArray['genero'],$usuario->emp_token,$usuario->user_token]); 
   
                    $clasifServ = DB::select("SELECT id FROM clasificacion WHERE token_clascificacion = ?",[$parametrosArray['clasificacion']]);
                    //echo $clasifServ[0]->id;

                    $genroServ = DB::select("SELECT id,folio_genero,concepto FROM genero WHERE token_genero = ?",[$parametrosArray['genero']]);
                    //$genroServ[0]->id;

                    $claveSat = DB::select("SELECT id,descripcion FROM teci_catalogo_prodservsat WHERE clave = ? AND token_prodservsat = ?",[$parametrosArray['clave_sat'],$parametrosArray['tknSat']]);
                    //echo " claveSat ".$claveSat[0]->id;

                    $medidaUnidad = DB::select("SELECT id FROM unidad_medida WHERE token_unidad_medida = ?",[$parametrosArray['token_unidad_medida']]);
                    //echo " claveSat ".$claveSat[0]->id;

                    //moneda
                    $monedaselect = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$parametrosArray['token_monedaServAlta']]);

                    $fechaAlta = $JwtAuth->convierteFechaEpoc($parametrosArray['fechaAlta']);
                    //echo $fechaAlta;
   
                    $conceptoServ = $JwtAuth->encriptar($parametrosArray['concepto']);
                    
                    $tokenServ = $JwtAuth->encriptarToken($parametrosArray['clasificacion'],$parametrosArray['clave_sat'],
                    $JwtAuth->encriptar($conceptoServ).$conceptoServ);

                    if (file_exists($request->file('image'))){
                        $nombre_imagen = $JwtAuth->encriptar($request->file('image')->getClientOriginalName());
                    } else {
                        $nombre_imagen = $JwtAuth->encriptar('default-servicios.jpg');
                    }

                    $insertServ = DB::table('servicios') 
                    ->insert(array(
                        "token_servicios" => $tokenServ, 
                        "servicio" => $conceptoServ, 
                        "clasificacion" => $clasifServ[0]->id,
                        "genero" => $genroServ[0]->id,
                        "catalogoSAT" => $claveSat[0]->id,
                        "medida_sat" => $medidaUnidad[0]->id,
                        "imagen" => $nombre_imagen,
                        "empresa" => $selectEmp[0]->id,
                    ));

                    if ($insertServ) {
                        //echo "insertCorteCaja"; 
                        $obtenServ = DB::select("SELECT id FROM servicios WHERE token_servicios = ?",[$tokenServ]);
                        //echo $obtenServ[0]->id;
                        $fechaSistema = time();
                        $tokenCatServ = $JwtAuth->encriptarToken(time(),$parametrosArray['clasificacion'],$parametrosArray['clave_sat'],$conceptoServ);
 
                        $newServ = new ServiciosModelo();
                        $newServ->token_cat_servicios = $tokenCatServ;	
                        $newServ->fecha_sistema	= $fechaSistema;
                        $newServ->folio_sistema	= $folioSistema[0]->folio;
                        $newServ->fechaAlta	= $fechaAlta;
                        $newServ->servicio = $obtenServ[0]->id;
                        $newServ->folio = $folioServ[0]->folio+1;
                        $newServ->proceso = FALSE;
                        $newServ->moneda = $monedaselect[0]->id;
                        $newServ->tipo_cambio = $JwtAuth->encriptar($parametrosArray['txttipoCam']); 	
                        $newServ->cantidad_sim = $JwtAuth->encriptar($parametrosArray['txtCantSim']); 	
                        $newServ->precioBase = $JwtAuth->encriptar($parametrosArray['txtPrecioB']); 	
                        $newServ->cantidad = NULL;
                        $newServ->periodicidad = NULL;
                        $newServ->repeticion_periodo = NULL;
                        $newServ->tipo_periodo = NULL;
                        $newServ->fecha_finPeriodo = NULL;
                        $newServ->tipo_variabilidad = NULL;
                        $newServ->importe_minimo = NULL;
                        $newServ->importe_maximo = NULL;
                        $newServ->fecha_delete_serv = '';
                        $newServ->status = TRUE;
                        $newServ->administrador = $selectEmp[0]->id;
                        $savednewServ = $newServ->save();
            
                        if ($savednewServ) {
                            $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?",[$tokenCatServ]);

                            //impuestos
                            for ($im = 0; $im < count($parametrosArray['catImpVigArray']); $im++) { 
                                $token_imp = $parametrosArray['catImpVigArray'][$im]['c_token'];
                                if ($parametrosArray['catImpVigArray'][$im]['vinculacion'] == true) {
                                    $obtenImpuesto = DB::select("SELECT id FROM catalogo_impuestos WHERE token_cat_impuestos = ?",[$token_imp]);
                                    $tkn_imp_serv = $JwtAuth->encriptarToken(time(),$obtenServicio[0]->id,$obtenImpuesto[0]->id,$conceptoServ);
                                    $insertServ = DB::table('in_egr_impuestos_articulos') 
                                    ->insert(array(
                                        "token_impuestos_articulos" => $tkn_imp_serv, 
                                        "producto_rel" => NULL, 
                                        "servicio_rel" => $obtenServicio[0]->id,
                                        "impuestos" => $obtenImpuesto[0]->id,
                                    ));
                                }
                            }

                            //descuentos
                                if (count($parametrosArray['arrayDescuentos']) > 0) {
                                    for ($desc = 0; $desc < count($parametrosArray['arrayDescuentos']); $desc++) { 
                                        if ($parametrosArray['arrayDescuentos'][$desc]['vinculacion'] == true) {
                                            $token_descp = $parametrosArray['arrayDescuentos'][$desc]['c_token'];
                                            $listaDescuento = DescuentosModelo::join("main_empresas AS emp","descuentos.empresa","emp.id")
                                            ->join("main_empresapersonal AS emppers","emp.id","emppers.empresa")
                                            ->join("vhum_personal AS pers","emppers.personal","pers.id")
                                            ->join("main_usuarios AS users","pers.usuario","users.id")
                                            ->where([
                                                'descuentos.token_descuentos' => $token_descp,
                                                'descuentos.status' => TRUE,
                                                'emp.emp_token' => $usuario->emp_token,
                                                'users.user_token' => $usuario->user_token,
                                            ])->get();
                                            
                                            foreach ($listaDescuento as $value) {
                                                //da_te_default_timezone_set($value->zona_horaria);
                                                $selectTipoDesc = DB::select("SELECT id FROM descuentos WHERE token_descuentos = ?",[$token_descp]);
                                                
                                                $selectAplicacionDesc = $value->aplicacion;
                                                $fechaInicioDesc = $value->fecha_inicio;
                                                $fechaFinDesc = $value->fecha_fin;
                                                $fecha_activacion = $value->fecha_activacion;
                                                $status_activacion = $value->status_activacion;
                                                $fecha_delete = $value->fecha_delete;
                                                $status_desc = $value->status;
                         
                                                $datoTokenDescuento = $JwtAuth->encriptar($token_descp.$tokenCatServ.
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
                                                
                                            }
                                        }
                                    }
                                }
                                
                                if (count($parametrosArray['arrayAltaDescuentos']) > 0) {
                                    for ($idesc = 0; $idesc < count($parametrosArray['arrayAltaDescuentos']); $idesc++) { 
                                        $folioDescu = DB::select("SELECT IF (max(descu.folio) IS NOT NULL,(max(descu.folio)+1),1) AS folio
                                            FROM descuentos AS descu JOIN main_empresas AS emp JOIN empresapersonal AS empper
                                            JOIN vhum_personal AS pers JOIN main_usuarios AS users
                                            WHERE descu.empresa = emp.id AND emp.emp_token = ?
                                            AND emp.id = empper.empresa AND empper.personal = pers.id
                                            AND pers.usuario = users.id AND users.user_token = ?",[$usuario->emp_token,$usuario->user_token]);
            
                                        $alias = $JwtAuth->encriptar($parametrosArray['arrayAltaDescuentos'][$idesc]['alias']);
                                        $concepto = $parametrosArray['arrayAltaDescuentos'][$idesc]['concepto'];
            
                                        if ($parametrosArray['arrayAltaDescuentos'][$idesc]['aplicacion'] == 'cuota') {
                                            $aplicacion = FALSE;
                                            $monto = $JwtAuth->encriptar('$'.$parametrosArray['arrayAltaDescuentos'][$idesc]['monto']);
                                        } else {
                                            $aplicacion = TRUE;
                                            $monto = $JwtAuth->encriptar($parametrosArray['arrayAltaDescuentos'][$idesc]['monto'].'%');
                                        }
            
                                        if ($parametrosArray['arrayAltaDescuentos'][$idesc]['tipo'] == 'eventual') {
                                            $tipo = 'usa';
                                        } else if($parametrosArray['arrayAltaDescuentos'][$idesc]['tipo'] == 'pIndeterminado'){
                                            $tipo = 'ind';
                                        } else if($parametrosArray['arrayAltaDescuentos'][$idesc]['tipo'] == 'pDeterminado'){
                                            $tipo = 'det';
                                        }
            
                                        if ($parametrosArray['arrayAltaDescuentos'][$idesc]['fecha_inicia'] == '') {
                                            $fecha_inicia = '-';
                                        } else {
                                            $fecha_inicia = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaDescuentos'][$idesc]['fecha_inicia']);
                                        }
                                        if ($parametrosArray['arrayAltaDescuentos'][$idesc]['fecha_termina'] == '') {
                                            $fecha_termina = '-';
                                        } else {
                                            $fecha_termina = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaDescuentos'][$idesc]['fecha_termina']);
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
                                        
                                            $datoTokenDescuento = $JwtAuth->encriptarToken($tokenDesc.$parametrosArray['token_cat_servicios'].$selectDescXId[0]->id.$obtenServicio[0]->id);
                                        
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
                                        } 
            
            
                                    }
                                }

                            //promociones
                                if (count($parametrosArray['arrayPromociones']) > 0) {
                                    for ($promo = 0; $promo < count($parametrosArray['arrayPromociones']); $promo++) { 
                                        $token_promo = $parametrosArray['arrayPromociones'][$promo]['c_token'];
                                        if ($parametrosArray['arrayPromociones'][$promo]['vinculacion'] == true) {
    
                                            $listaPromocion = PromocionesModelo::join("main_empresas AS emp","promociones.empresa","emp.id")
                                            ->join("main_empresapersonal AS emppers","emp.id","emppers.empresa")
                                            ->join("vhum_personal AS pers","emppers.personal","pers.id")
                                            ->join("main_usuarios AS users","pers.usuario","users.id")
                                            ->where([
                                                'promociones.token_promocion' => $token_promo,
                                                'promociones.status' => TRUE,
                                                'emp.emp_token' => $usuario->emp_token,
                                                'users.user_token' => $usuario->user_token,
                                            ])->get();
                                            
                                            foreach ($listaPromocion as $value) {
                                                //da_te_default_timezone_set($value->zona_horaria);
                                                $selectTipoPromo = DB::select("SELECT id FROM promociones WHERE token_promocion = ?",[$token_promo]);
                                                
                                                $selectAplicacionDesc = $value->aplicacion;
                                                $fechaInicioDesc = $value->fecha_inicio;
                                                $fechaFinDesc = $value->fecha_fin;
                                                $fecha_activacion = $value->fecha_activacion;
                                                $status_activacion = $value->status_activacion;
                                                $fecha_delete = $value->fecha_delete;
                                                $status_desc = $value->status;
                        
                                                $datoTokenPromocion = $JwtAuth->encriptar($token_promo.$tokenCatServ.
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
                                            }
                                        }
                                    }
                                }
                                return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
                                if (count($parametrosArray['arrayAltaPromociones']) > 0) {
                                    for ($ipromo=0; $ipromo < count($parametrosArray['arrayAltaPromociones']); $ipromo++) { 

                                        $folioPromo = DB::select("SELECT IF (max(promo.folio) IS NOT NULL,(max(promo.folio)+1),1) AS folio
                                            FROM promociones AS promo JOIN main_empresas AS emp JOIN empresapersonal AS empper
                                            JOIN vhum_personal AS pers JOIN main_usuarios AS users
                                            WHERE promo.empresa = emp.id AND emp.emp_token = ?
                                            AND emp.id = empper.empresa AND empper.personal = pers.id
                                            AND pers.usuario = users.id AND users.user_token = ?",[$usuario->emp_token,$usuario->user_token]);
                                    
                                        $alias = $JwtAuth->encriptar($parametrosArray['arrayAltaPromociones'][$ipromo]['alias']);
                                        $concepto = $parametrosArray['arrayAltaPromociones'][$ipromo]['concepto'];
                                    
                                        if ($parametrosArray['arrayAltaPromociones'][$ipromo]['aplicacion'] == 'cuota') {
                                            $aplicacion = FALSE;
                                            $monto = $JwtAuth->encriptar('$'.$parametrosArray['arrayAltaPromociones'][$ipromo]['monto']);
                                        } else {
                                            $aplicacion = TRUE;
                                            $monto = $JwtAuth->encriptar($parametrosArray['arrayAltaPromociones'][$ipromo]['monto'].'%');
                                        }
                                    
                                        if ($parametrosArray['arrayAltaPromociones'][$ipromo]['tipo'] == 'eventual') {
                                            $tipo = 'usa';
                                        } else if($parametrosArray['arrayAltaPromociones'][$ipromo]['tipo'] == 'pIndeterminado'){
                                            $tipo = 'ind';
                                        } else if($parametrosArray['arrayAltaPromociones'][$ipromo]['tipo'] == 'pDeterminado'){
                                            $tipo = 'det';
                                        }
                                    
                                        if ($parametrosArray['arrayAltaPromociones'][$ipromo]['fecha_inicia'] == '') {
                                            $fecha_inicia = '-';
                                        } else {
                                            $fecha_inicia = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaPromociones'][$ipromo]['fecha_inicia']);
                                        }
                                        if ($parametrosArray['arrayAltaPromociones'][$ipromo]['fecha_termina'] == '') {
                                            $fecha_termina = '-';
                                        } else {
                                            $fecha_termina = $JwtAuth->convierteFechaEpoc($parametrosArray['arrayAltaPromociones'][$ipromo]['fecha_termina']);
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
                                        }
                                    }
                                }
                            //clientes
                                if (count($parametrosArray['arrayClaveClientServ']) > 0) { 
                                    for ($klient = 0; $klient < count($parametrosArray['arrayClaveClientServ']); $klient++) {
                                        if($parametrosArray['arrayClaveClientServ'][$klient]['clave'] != "" && 
                                            $parametrosArray['arrayClaveClientServ'][$klient]['periodo'] != "" &&
                                            $parametrosArray['arrayClaveClientServ'][$klient]['periodicidad'] != "" &&
                                            $parametrosArray['arrayClaveClientServ'][$klient]['inicio'] != "" &&
                                            $parametrosArray['arrayClaveClientServ'][$klient]['fin']){
                                            $token_klient = $parametrosArray['arrayClaveClientServ'][$klient]['cliente'];
                                            $clave_klient = $JwtAuth->encriptar($parametrosArray['arrayClaveClientServ'][$klient]['clave']);
                                            $periodo_klient = $parametrosArray['arrayClaveClientServ'][$klient]['periodo'];

                                            $obtenCliente = DB::select("SELECT id FROM catalogo_clientes WHERE token_cat_clientes = ?",[$token_klient]);
                                            
                                            if ($parametrosArray['arrayClaveClientServ'][$klient]['periodicidad'] == 'eventual') {
                                                $periodicidad_klient = 'usa';
                                            } else if($parametrosArray['arrayClaveClientServ'][$klient]['periodicidad'] == 'pIndeterminado'){
                                                $periodicidad_klient = 'ind';
                                            } else if($parametrosArray['arrayClaveClientServ'][$klient]['periodicidad'] == 'pDeterminado'){
                                                $periodicidad_klient = 'det';
                                            }
    
                                            $inicio_klient = $parametrosArray['arrayClaveClientServ'][$klient]['inicio'];
                                            $fin_klient = $parametrosArray['arrayClaveClientServ'][$klient]['fin'];

                                            $token_klav_ser = $JwtAuth->encriptarToken(time(),$token_klient.$clave_klient.$periodo_klient.
                                                $periodicidad_klient.$inicio_klient.$fin_klient.$obtenServicio[0]->id);
    
                                            $insert_serv_claves = DB::table('serv_claves') 
                                            ->insert(array(
                                                "token_serv_claves" => $token_klav_ser,
                                                "servicio_id" => $obtenServicio[0]->id,
                                                "interno" => NULL,
                                                "proveedor" => NULL,
                                                "cliente" => $obtenCliente[0]->id,
                                                "asigned_clave" => $clave_klient,
                                                "gs1" => NULL,
                                                "periodicidad_c_v" => $periodicidad_klient,
                                                "notificacion_c_v" => $periodo_klient,
                                                "inicio_periodo" => $inicio_klient,
                                                "fin_periodo" => $fin_klient,
                                                "status_c_v" => TRUE,
                                            ));	 	
                                        }
                                    }
                                }
                            $filepath = $selectEmp[0]->root_tkn."/0001-cpc/catalogos/servicios/".$fechaSistema."-".
                                $JwtAuth->generar($folioSistema[0]->folio)."/"; 
                    
                            if (!file_exists(storage_path("/root/".$filepath))){
                                Storage::disk('root')->makeDirectory($filepath,0777, true, true);
                            }

                            if (file_exists($request->file('image'))){
                                $nombre_imagen = $JwtAuth->encriptar($request->file('image')->getClientOriginalName());
                                Storage::putFileAs("/public/root/".$filepath,$request->file('image'),$nombre_imagen);
                            }
                            
                            QRCode::text($tokenCatServ)
                            ->setOutfile(Storage::path('public/root/'.$filepath.$fechaSistema."-".$JwtAuth->generar($folioSistema[0]->folio).'-QRCode.png'))
                            ->png();
                                            
                            $qrGenerado = $JwtAuth->encriptaBase64(
                                Storage::path('public/root/'.$filepath.$fechaSistema."-".$JwtAuth->generar($folioSistema[0]->folio).'-QRCode.png'));

                            if (file_exists($request->file('image'))){
                                $nombre_imagen = $JwtAuth->encriptar($request->file('image')->getClientOriginalName());
                                $logo_serv= $JwtAuth->encriptaBase64(Storage::path('public/root/'.$filepath.'/'.$nombre_imagen));
                            } else {
                                $logo_serv = $JwtAuth->encriptaBase64(Storage::path('public/settings/default-servicios.jpg'));
                            }

                            //pdf reporte
                                $areaCss = 'information-cpc';
                                $areaPdf = 'Ingresos y cuentas por cobrar';
                                $Subarea = 'Catalogos de ingresos';
                                $nameDoc = 'evidencia de registro de servicios';
                                $logoEmp = $JwtAuth->encriptaBase64(Storage::path('public/homePagePrincipal/sos-mexico.png'));
                                if ($selectEmp[0]->denominacion_rs == ''){
                                    $nameEmp = $JwtAuth->desencriptar($selectEmp[0]->paterno)." ".
                                        $JwtAuth->desencriptar($selectEmp[0]->materno)." ".
                                        $JwtAuth->desencriptar($selectEmp[0]->nombre);
                                } else {
                                    $nameEmp = $JwtAuth->desencriptar($selectEmp[0]->denominacion_rs);
                                }
                                
                                if ($selectEmp[0]->sitio_web == '' || $selectEmp[0]->sitio_web == '-'){
                                    $sitio_web = '---';
                                } else {
                                    $sitio_web = $JwtAuth->desencriptar($selectEmp[0]->sitio_web);
                                }
                                $direccion = '';

                                $fecha_pdf = $JwtAuth->convierteEpocFecha($selectEmp[0]->zona_horaria,$fechaSistema);
                                $datePdf = gmdate('Y-m-d H:i:s',$fechaAlta);

                                $contenidoPdf = '<div class="divLogo"><img src="'.$qrGenerado.'" alt=""></div>
                                    <div class="divLogo"><img class="logotipo" src="'.$logo_serv.'" alt=""></div>
                                    <h3>'.$parametrosArray['concepto'].'</h3>
                                    <table class="contenido" width="100%">
                                        <thead>
                                            <tr>
                                                <th>fecha de alta registrada</th>
                                                <th>clasificación</th>
                                                <th>catalogo de sat</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <td>'.$datePdf.'</td>
                                            <td>'.$JwtAuth->generar('6')."-".
                                                $JwtAuth->generar($genroServ[0]->folio_genero)."-".
                                                $JwtAuth->generar($folioServ[0]->folio+1).' ('.$genroServ[0]->concepto.')</td>
                                            <td>'.$parametrosArray['clave_sat'].' ('.$claveSat[0]->descripcion.')</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <br>
                                    <h3>clientes asignados</h3>
                                    <table class="contenido" width="100%">
                                        <thead>
                                            <tr>
                                                <th>cliente</th>
                                                <th>clave de servicio</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        //return response()->json(['message' => 'pdf','codigo' => 200,'status' => 'error']);
                                            if (count($parametrosArray['arrayClaveClientServ']) > 0) {
                                                for ($klient = 0; $klient < count($parametrosArray['arrayClaveClientServ']); $klient++) { 
                                                    
                                                    if($parametrosArray['arrayClaveClientServ'][$klient]['clave'] != "" && 
                                                        $parametrosArray['arrayClaveClientServ'][$klient]['periodo'] != "" &&
                                                        $parametrosArray['arrayClaveClientServ'][$klient]['periodicidad'] != "" &&
                                                        $parametrosArray['arrayClaveClientServ'][$klient]['inicio'] != "" &&
                                                        $parametrosArray['arrayClaveClientServ'][$klient]['fin']){
	                                                    $token_klient = $parametrosArray['arrayClaveClientServ'][$klient]['cliente'];
                                                        $clave_klient = $JwtAuth->encriptar($parametrosArray['arrayClaveClientServ'][$klient]['clave']);
                    
                                                        $clientPdf = DB::select("SELECT people.paterno,people.materno,people.nombre,
                                                        people.denominacion_rs FROM catalogo_clientes AS catclient 
                                                        JOIN personas AS people WHERE people.id = catclient.cliente 
                                                        AND catclient.token_cat_clientes = ?",[$token_klient]);

                                                        if ($clientPdf[0]->denominacion_rs == '') {
                                                            $nombreClient = $JwtAuth->desencriptar($clientPdf[0]->paterno)." ".
                                                            $JwtAuth->desencriptar($clientPdf[0]->materno)." ".
                                                            $JwtAuth->desencriptar($clientPdf[0]->nombre);
                                                        } else {
                                                            $nombreClient = $JwtAuth->desencriptar($clientPdf[0]->denominacion_rs);
                                                        }
                                                        $contenidoPdf.='<tr>
                                                            <td>'.$nombreClient.'</td>
                                                            <td>'.$clave_klient.'</td>
                                                        </tr>';
                                                    }
                                                }
                                            } else {
                                                $contenidoPdf.='<tr><td colspan="2">¡NO HAY REGISTROS!</td></tr>';
                                            }
                                            
                                        $contenidoPdf.= '</tbody>
                                    </table>
                                    <h3>registrado por</h3>
                                    <table class="contenido" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Area</th>
                                                <th>Ubicacion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>'.$usuario->name.'</td>
                                                <td>'.$usuario->area.'</td>
                                                <td>Germany</td>
                                            </tr>
                                        </tbody>
                                    </table>';
                    
                                $pdfGenerado = $JwtAuth->generaPdf($areaCss,$areaPdf,$Subarea,$nameDoc,
                                    $logoEmp,$nameEmp,$sitio_web,$direccion,$fecha_pdf,$contenidoPdf);

                                $dompdf = \PDF::loadHtml($pdfGenerado);
                                $dompdf->setPaper("A2", "portrait");
                                $contenidoPDF = $dompdf->output();
                
                            file_put_contents(storage_path("app/public/root/".$filepath).$fechaSistema."-".$JwtAuth->generar($folioSistema[0]->folio).".pdf", $contenidoPDF);
                                            
                            $dompdf = \PDF::loadHtml($pdfGenerado);
                            $dompdf->setPaper("A2", "portrait");
                            $contenidoPDF = $dompdf->output();

                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Este servicio ha sido registrado satisfactoriamente'
                            );

                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'La información de este servicio no es valida'
                            );
                        }

                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'La información de este servicio no es valida'
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

        public function registroServicioIngresos2(Request $request){
            $JwtAuth = new \JwtAuth();
            $imageServ = $request->file('image');

            $usuario = $JwtAuth->checkToken($request->input('user_token'),true); 
            $jsonServ = $request->input('servdata');
    
            $parametros = json_decode($jsonServ);
            $parametrosArray = json_decode($jsonServ,true);
         
            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'fechaAlta' => 'required|string',
                    'clasificacion' => 'required|string',
                    'genero' => 'required|string',
                    'clave_sat' => 'required|numeric',
                    'concepto' => 'required|string',
                    'cliente' => 'required'
                ]);
   
                if ($validate->fails()) {
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'La infomación que ha intantado registrar es invalida',
                        'errors' => $validate->errors()
                    );
                } else {
 
                    $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,emp.zona_horaria FROM main_empresas AS emp  
                    JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users WHERE emp.emp_token = ? 
                    AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                    AND pers.usuario = users.id AND users.user_token= ?",[$usuario->emp_token,$usuario->user_token]);
                    //echo $selectEmp[0]->id;
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);

                    $folioServ = DB::select("SELECT COUNT(catserv.id) AS folio FROM catalogo_servicios AS catserv 
                        JOIN servicios AS listServ JOIN sos_ps_genero AS gen JOIN main_empresas AS emp 
                        JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers JOIN main_usuarios AS users 
                        WHERE catserv.servicio = listServ.id AND listServ.genero = gen.id AND gen.token_genero = ?
                        AND catserv.administrador = emp.id AND emp.emp_token = ? 
                        AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                        AND pers.usuario = users.id AND users.user_token= ?",[$parametrosArray['genero'],$usuario->emp_token,$usuario->user_token]); 
   
                    $clasifServ = DB::select("SELECT id FROM clasificacion WHERE token_clascificacion = ?",[$parametrosArray['clasificacion']]);
                    //echo $clasifServ[0]->id;

                    $genroServ = DB::select("SELECT id,folio_genero,concepto FROM genero WHERE token_genero = ?",[$parametrosArray['genero']]);
                    //$genroServ[0]->id;

                    $claveSat = DB::select("SELECT id,descripcion FROM teci_catalogo_prodservsat WHERE clave = ?",[$parametrosArray['clave_sat']]);
                    //echo " claveSat ".$claveSat[0]->id;

                    $fechaAlta = $JwtAuth->convierteFechaEpoc($parametrosArray['fechaAlta']);
                    //echo $fechaAlta;
   
                    $conceptoServ = $JwtAuth->encriptar($parametrosArray['concepto']);
                    
                    $tokenServ = $JwtAuth->encriptarToken($parametrosArray['clasificacion'],$parametrosArray['clave_sat'],
                    $JwtAuth->encriptar($conceptoServ).$conceptoServ);

                    $insertServ = DB::table('servicios') 
                    ->insert(array(
                        "token_servicios" => $tokenServ, 
                        "servicio" => $conceptoServ, 
                        "clasificacion" => $clasifServ[0]->id,
                        "genero" => $genroServ[0]->id,
                        "catalogoSAT" => $claveSat[0]->id,
                        "imagen" => $JwtAuth->encriptar($JwtAuth->generar('6')."-".
                                $JwtAuth->generar($genroServ[0]->folio_genero)."-".
                                $JwtAuth->generar($folioServ[0]->folio+1)."-".$fechaAlta),
                        "empresa" => $selectEmp[0]->id,
                    ));

                    if ($insertServ) {
                        //echo "insertCorteCaja"; 
                        $obtenServ = DB::select("SELECT id FROM servicios WHERE token_servicios = ?",[$tokenServ]);
                        //echo $obtenServ[0]->id;

                        $tokenCatServ = $JwtAuth->encriptarToken(time(),$parametrosArray['clasificacion'],$parametrosArray['clave_sat'],$conceptoServ);

                        $newServ = new ServiciosModelo();
                        $newServ->token_cat_servicios = $tokenCatServ;
                        $newServ->fechaAlta = $fechaAlta;
                        $newServ->servicio = $obtenServ[0]->id;
                        $newServ->folio = $folioServ[0]->folio+1;
                        $newServ->proceso = FALSE;
                        $newServ->fecha_delete_serv = '';
                        $newServ->status = TRUE;
                        $newServ->administrador = $selectEmp[0]->id;
        
                        $savednewServ = $newServ->save();
                        if ($savednewServ) {

                            $arrayInternoClientServ = array();
                            $obtenServicio = DB::select("SELECT id FROM catalogo_servicios WHERE token_cat_servicios = ?",[$tokenCatServ]);
                            $servclientclaves = $parametrosArray['cliente']; 
                            for ($p1=0; $p1 < count($servclientclaves); $p1++) { 
                                if ($servclientclaves[$p1] != '') {
                                    array_push($arrayInternoClientServ,$servclientclaves[$p1]);
                                }
                            }

                            $contadorProvServ = 0; 
                            for ($p1=0; $p1 < count($arrayInternoClientServ); $p1++) { 
                                
                                if ($parametrosArray['cliente'][$p1] != '') {
                                    $clienteToken = $arrayInternoClientServ[$p1][0];
                                    $obtenCliente = DB::select("SELECT id FROM catalogo_clientes WHERE token_cat_clientes = ?",[$clienteToken]);

                                    $insertProd = DB::table('serv_claves') 
                                    ->insert(array(
                                        "servicio_id" => $obtenServicio[0]->id,
                                        "proveedor" => $obtenCliente[0]->id,
                                        "asigned_clave" => $JwtAuth->encriptar($arrayInternoClientServ[$p1][1]),
                                        "periodicidad_c_v" => 'ind', 
                                        "notificacion_c_v" => '1 day',	
                                        "inicio_periodo" => '1641854609',	
                                        "fin_periodo" => '',
                                        "status_c_v" => FALSE
                                    ));
                            
                                    if ($insertProd) {
                                        $contadorProvServ++;
                                    }
                                }
                            }
                            //echo $contadorProvServ." ".count($parametrosArray['proveedor']);
                            if ($contadorProvServ == count($arrayInternoProvServ)) {
                                $filepath = $selectEmp[0]->root_tkn."/0001-cpc/catalogos/servicios/".$JwtAuth->generar('6')."-".
                                    $JwtAuth->generar($genroServ[0]->folio_genero)."-".$JwtAuth->generar($folioServ[0]->folio+1)."-".
                                    $fechaAlta."/"; 

                                if (!file_exists(storage_path("/root/".$filepath))){
                                    Storage::disk('root')->makeDirectory($filepath,0777, true, true);
                                }

                                Storage::putFileAs("/public/root/".$filepath,$request->file('image'), $JwtAuth->generar('6')."-".
                                    $JwtAuth->generar($genroServ[0]->folio_genero)."-".
                                    $JwtAuth->generar($folioServ[0]->folio+1)."-".$fechaAlta.".png");

                                $logo_serv= $JwtAuth->encriptaBase64(Storage::path('public/root/'.$selectEmp[0]->root_tkn.
                                    '/0002-cpp/catalogos/servicios/'.$JwtAuth->generar('6').'-'.$JwtAuth->generar($genroServ[0]->folio_genero).'-'.
                                    $JwtAuth->generar($folioServ[0]->folio+1).'-'.$fechaAlta.'/'.$JwtAuth->generar('6')."-".
                                    $JwtAuth->generar($genroServ[0]->folio_genero)."-".
                                    $JwtAuth->generar($folioServ[0]->folio+1)."-".$fechaAlta.'.png'));

                                QRCode::text($tokenCatServ)
                                ->setOutfile(Storage::path('public/root/'.$filepath.$JwtAuth->generar('6')."-".
                                    $JwtAuth->generar($genroServ[0]->folio_genero)."-".
                                    $JwtAuth->generar($folioServ[0]->folio+1)."-".$fechaAlta.'-QRCode.png'))
                                ->png();

                                $qrGenerado = $JwtAuth->encriptaBase64(
                                    Storage::path('public/root/'.$filepath.$JwtAuth->generar('6')."-".
                                    $JwtAuth->generar($genroServ[0]->folio_genero)."-".
                                    $JwtAuth->generar($folioServ[0]->folio+1)."-".$fechaAlta.'-QRCode.png'));
                                
                                $areaCss = 'information-cpc';
                                $areaPdf = 'Ingresos y cuentas por cobrar';
                                $Subarea = 'Catalogos de ingresos';
                                $nameDoc = 'evidencia de registro de servicios';
                                $logoEmp = $JwtAuth->encriptaBase64(Storage::path('public/homePagePrincipal/sos-mexico.png'));
                                if ($selectEmp[0]->denominacion_rs == ''){
                                    $nameEmp = $JwtAuth->desencriptar($selectEmp[0]->paterno)." ".
                                        $JwtAuth->desencriptar($selectEmp[0]->materno)." ".
                                        $JwtAuth->desencriptar($selectEmp[0]->nombre);
                                } else {
                                    $nameEmp = $JwtAuth->desencriptar($selectEmp[0]->denominacion_rs);
                                }
                                if ($selectEmp[0]->sitio_web == '' || $selectEmp[0]->sitio_web == '-'){
                                    $sitio_web = '---';
                                } else {
                                    $sitio_web = $JwtAuth->desencriptar($selectEmp[0]->sitio_web);
                                }
                                $direccion = '';
                                $fecha_pdf = $JwtAuth->convierteEpocFecha("America/Mexico_City",time());

                                $contenidoPdf = '<div class="divLogo">
                                        <img src="'.$qrGenerado.'" alt="">
                                    </div>
                                    <div class="divLogo">
                                        <img class="logotipo" src="'.$logo_serv.'" alt="">
                                    </div>
                                    <h3>'.$parametrosArray['concepto'].'</h3>
                                    <table class="contenido" width="100%">
                                        <thead>
                                            <tr>
                                                <th>fecha de alta registrada</th>
                                                <th>clasificación</th>
                                                <th>catalogo de sat</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <td>'.$parametrosArray['fechaAlta'].'</td>
                                            <td>'.$JwtAuth->generar('6')."-".
                                                $JwtAuth->generar($genroServ[0]->folio_genero)."-".
                                                $JwtAuth->generar($folioServ[0]->folio+1).' ('.$genroServ[0]->concepto.')</td>
                                            <td>'.$parametrosArray['clave_sat'].' ('.$claveSat[0]->descripcion.')</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <br>
                                    <h3>Cuentas bancarias vinculadas</h3>
                                    <table class="contenido" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Proveedor asignado</th>
                                                <th>clave de servicio</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                            for ($p1=0; $p1 < count($arrayInternoClientServ); $p1++) { 
                                                $clienteToken = $arrayInternoClientServ[$p1][0];
                                                $obtenProv = DB::select("SELECT people.paterno,people.materno,people.nombre,
                                                    people.denominacion_rs FROM catalogo_clientes AS catclient 
                                                    JOIN personas AS people WHERE people.id = catclient.proveedor 
                                                    AND catclient.token_cat_clientes = ?",[$clienteToken]);
                                                if ($obtenProv[0]->denominacion_rs == '') {
                                                    $nombreClient = $JwtAuth->desencriptar($obtenProv[0]->paterno)." ".
                                                        $JwtAuth->desencriptar($obtenProv[0]->materno)." ".
                                                        $JwtAuth->desencriptar($obtenProv[0]->nombre);
                                                } else {
                                                    $nombreClient = $JwtAuth->desencriptar($obtenProv[0]->denominacion_rs);
                                                }
                                                $contenidoPdf.='<tr>
                                                    <td>'.$nombreClient.'</td>
                                                    <td>'.$arrayInternoClientServ[$p1][1].'</td>
                                                </tr>';
                                            }
                                        $contenidoPdf.= '</tbody>
                                    </table>
                                    <h3>registrado por</h3>
                                    <table class="contenido" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Area</th>
                                                <th>Ubicacion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>'.$usuario->name.'</td>
                                                <td>'.$usuario->area.'</td>
                                                <td>Germany</td>
                                            </tr>
                                        </tbody>
                                    </table>';
                            
                            
                                $pdfGenerado = $JwtAuth->generaPdf($areaCss,$areaPdf,$Subarea,$nameDoc,
                                    $logoEmp,$nameEmp,$sitio_web,$direccion,$fecha_pdf,$contenidoPdf);

                                $dompdf = \PDF::loadHtml($pdfGenerado);
                                $dompdf->setPaper("A2", "portrait");
                                $contenidoPDF = $dompdf->output();

                                /*<form id="ubicacionForm" name="ubicacionForm" method="post"
			action="/app/qr/faces/pages/mobile/validadorqr.jsf;jsessionid=IiQc9BxhnU5CQOJ5Ed_lSrZUHP0xXaIegb0zWMxhxWS4RJjGqEWG!73028413"
			class="ui-content" enctype="application/x-www-form-urlencoded">*/

                                file_put_contents(storage_path("app/public/root/".$filepath).$JwtAuth->generar('6')."-".
                                    $JwtAuth->generar($genroServ[0]->folio_genero)."-".
                                    $JwtAuth->generar($folioServ[0]->folio+1)."-".$fechaAlta.".pdf", $contenidoPDF);
                                        
                                $dompdf = \PDF::loadHtml($pdfGenerado);
                                $dompdf->setPaper("A2", "portrait");
                                $contenidoPDF = $dompdf->output();
            
                                $dataMensaje = array(
                                    'status' => 'success',
                                    'code' => 200,
                                    'message' => 'Este servicio ha sido registrado satisfactoriamente'
                                );
                            } else {
                                $dataMensaje = array(
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'La informacion de su proveedor no es valida'
                                );
                            }
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'La información de este servicio no es valida'
                            );
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'La información de este servicio no es valida'
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