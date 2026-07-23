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
use PDF;
use QRCode;

class INGR_ProductosController extends Controller{    
        public function listaingresosProductosVigentes2(Request $request){
            $JwtAuth = new \JwtAuth();
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            $arrayProductosVig = array();

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
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
                    
                    $decimalesMoneda = DB::select("SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
                    JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers 
                    JOIN main_usuarios AS users WHERE emp.moneda = catmon.id AND emp.emp_token = ?
                    AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                    AND pers.usuario = users.id AND users.user_token = ?",
                    [$usuario->emp_token,$usuario->user_token]);
    
                    $servList = ProductosModelo::join("productos","catprod.producto","=","catprod.id")
                    ->join("sos_ps_genero AS gen","catprod.genero","=","gen.id")
                    ->join("teci_catalogo_prodservsat AS pscsat","catprod.catalogo_sat","=","pscsat.id")
                    ->join("teci_unidad_medida AS umed","catprod.medida_entrada","=","umed.id")
                    ->join("main_empresas AS emp","catprod.administrador","=","emp.id")
                    ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                    ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        'catprod.status' => TRUE,
                        'catprod.uso_producto' => TRUE,
                        'emp.emp_token' => $usuario->emp_token,
                        'users.user_token' => $usuario->user_token,
                    ])->get();
        
                    foreach ($servList as $value) {
                        //echo $value->root_tkn;
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
        
                        /*$filepath = $value->root_tkn."/0002-cpp/catalogos/productos/".$JwtAuth->generar($value->clasificacion)."-".
                            $JwtAuth->generar($value->folio_genero)."-".$JwtAuth->generar($value->folio)."-".$value->fecha_alta."/";
                            return QRCode::text('QR Code Generator for Laravel!')->png();*/
        
                        $buyList = ProductosModelo::join("eegr_compras_detalle AS detcomp","catprod.id","=","detcomp.producto")
                        ->join("eegr_compras AS buy","detcomp.numero_compra","=","buy.id")
                        ->join("main_empresas AS emp","catprod.administrador","=","emp.id")
                        ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                        ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                        ->join("main_usuarios AS users","pers.usuario","=","users.id")
                        ->where([
                            'buy.status_recepcion' => TRUE,
                            'detcomp.activo_fijo' => NULL,
                            'detcomp.activo_intangible' => NULL,
                            'catprod.token_cat_productos' => $value->token_cat_productos,
                            'emp.emp_token' => $usuario->emp_token,
                            'users.user_token' => $usuario->user_token,
                        ])->orderBy('detcomp.id','DESC')->get();
        
                        if (count($buyList) > 0) {
                            $detcompra = array();
                            foreach ($buyList as $resDetCompra) {
                                $token_detcompra = $resDetCompra->token_detcompra;
                                
                                $totalDetComp = DB::select("SELECT 
                                    TRUNCATE(((SUM(precio_unitario*cantidad) - SUM(descuento*cantidad)) -
                                    SUM(total_retenciones)) + SUM(total_traslados),?) AS total
                                    FROM eegr_compras_detalle WHERE token_detcompra = ?",[$decimalesMoneda[0]->decimales,$token_detcompra]);
                                
                                $totalDetCompFormat = DB::select("SELECT 
                                    FORMAT(((SUM(precio_unitario*cantidad) - SUM(descuento*cantidad)) -
                                    SUM(total_retenciones)) + SUM(total_traslados),?) AS total
                                    FROM eegr_compras_detalle WHERE token_detcompra = ?",[$decimalesMoneda[0]->decimales,$token_detcompra]);
                                
                                if ($resDetCompra->concepto_producto != '') {
                                    $articulo = $JwtAuth->desencriptar($resDetCompra->concepto_producto)." - ".$JwtAuth->desencriptar($resDetCompra->marca_producto); 
                                }
                                
                                if ($resDetCompra->concepto_servicio != '') {
                                    $articulo = $JwtAuth->desencriptar($resDetCompra->concepto_servicio); 
                                }
                                
                                $formatPuRetTras = DB::select("SELECT FORMAT(?,?) AS formatPunit,FORMAT(?,?) AS formatDescuento,FORMAT(?,?) AS formatRetenc,FORMAT(?,?) AS formatTraslad",
                                    [$resDetCompra->precio_unitario,$decimalesMoneda[0]->decimales,
                                    $resDetCompra->descuento,$decimalesMoneda[0]->decimales,
                                    $resDetCompra->total_retenciones,$decimalesMoneda[0]->decimales,
                                    $resDetCompra->total_traslados,$decimalesMoneda[0]->decimales]);      
            
                                $arrayEachDetalleCompra = array(
                                    "articulo" => $JwtAuth->desencriptar($value->producto),
                                    "cantidad" => $resDetCompra->cantidad,
                                    "descuento" => "$".$formatPuRetTras[0]->formatDescuento,
                                    "precio_unitario" => "$".$formatPuRetTras[0]->formatPunit,
                                    "token_detcompra" => $token_detcompra,
                                    "total" => $totalDetComp[0]->total,
                                    "totalDetCompFormat" => "$".$totalDetCompFormat[0]->total, 
                                    "total_retenciones" => "$".$formatPuRetTras[0]->formatRetenc,
                                    "total_traslados" => "$".$formatPuRetTras[0]->formatTraslad,
                                );
                                $detcompra[] = $arrayEachDetalleCompra;
                            }
        
                            $arrayForeachVig = array(
                                "c_token" => $value->token_cat_productos,
                                "imagen" => $logo_prod,
                                "clasificacion" => $JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                                    $JwtAuth->generar($value->folio),
                                "producto" => $JwtAuth->desencriptar($value->producto),
                                "clave" => $value->clave,
                                "detcompra" => $detcompra,
                            );
                            $arrayProductosVig[] = $arrayForeachVig;
                        } 
                    }
                    return response()->json([
                        'datosProducto' => $arrayProductosVig,
                        'codigo' => 200,
                        'status' => 'success'
                    ]); 

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
        
        public function detalleProductoIngresos(Request $request){
            $JwtAuth = new \JwtAuth();
            //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
            $jsonUser = $request->input('json');
            $parametros = json_decode($jsonUser);
            $parametrosArray = json_decode($jsonUser,true);
            //echo $JwtAuth->encriptar('prueba1serv');
            $arrayProductosVig = array();
            $arrayProdProv = array();
            $arrayNivelAlmacen1 = array();
            $arrayNivelAlmacen2 = array();
            $arrayNivelAlmacen3 = array();
            $arrayKardex = array();

            if (!empty($parametros) && !empty($parametrosArray)) {
                $validate = \Validator::make($parametrosArray,[
                    'user_token' => 'required|string',
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

                    $prodList = ProductosModelo::join("productos","catprod.producto","=","catprod.id")
                    ->join("sos_ps_genero AS gen","catprod.genero","=","gen.id")
                    ->join("teci_catalogo_prodservsat AS pscsat","catprod.catalogo_sat","=","pscsat.id")
                    ->join("teci_unidad_medida AS umed","catprod.medida_entrada","=","umed.id")
                    ->join("main_empresas AS emp","catprod.administrador","=","emp.id")
                    ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                    ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                    ->join("main_usuarios AS users","pers.usuario","=","users.id")
                    ->where([
                        'catprod.token_cat_productos' => $parametrosArray['token_cat_productos'],
                        'catprod.status' => true,
                        'emp.emp_token' => $usuario->emp_token,
                        'users.user_token' => $usuario->user_token,
                    ])->get();
                    
                    foreach ($prodList as $value) {
                        //da_te_default_timezone_set($value->zona_horaria);

                        //$provList = ProveedoresModelo::join("catalogo_proveedores AS catprov","clavprod.proveedor","=","catprov.id")
                        //->join("personas AS people","catprov.proveedor","=","people.id")
                        //->where(['catprov.status' => TRUE])->get();
                        
                        $provList = ProveedoresModelo::join("personas AS prov","catalogo_proveedores.proveedor","prov.id")
                            ->join("pais AS ps","prov.nacionalidad","ps.id")
                            ->join("forma_pago AS pago","catalogo_proveedores.forma_pago","pago.id")
                            ->join("main_empresas AS emp","catalogo_proveedores.administrador","=","emp.id")
                            ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                            ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                            ->join("main_usuarios AS users","pers.usuario","=","users.id")
                            ->where([
                                'emp.emp_token' => $usuario->emp_token,
                                'users.user_token' => $usuario->user_token,
                                'catalogo_proveedores.status' => true
                            ])->get();
                
                        foreach ($provList as $valprovList) {
                            $provList_claves = ProductosModelo::join("producto_claves AS clavprod","catprod.id","=","clavprod.productoid")
                            ->join("catalogo_proveedores AS catprov","clavprod.proveedor","=","catprov.id")
                            ->join("personas AS people","catprov.proveedor","=","people.id")
                            ->where([
                                'catprod.token_cat_productos' => $value->token_cat_productos,
                                'catprov.token_cat_proveedores' => $valprovList->token_cat_proveedores,
                            ])->get();
                
                            if (count($provList_claves) == 1 && $valprovList->token_cat_proveedores == $provList_claves[0]->token_cat_proveedores) {
                                $clave_identificador = $provList_claves[0]->identificador;
                                $gs1_identificador = $provList_claves[0]->gs1;
                                //echo $valprovList->token_personas." - ";
                            } else {
                                $clave_identificador = '';
                                $gs1_identificador = '';
                            }
                
                            if ($valprovList->denominacion_rs != '') {
                                $nombreProv = $JwtAuth->desencriptar($valprovList->denominacion_rs);
                            } else {
                                //echo $valprovList->token_personas." ";
                                $nombreProv = $JwtAuth->desencriptar($valprovList->paterno)." ".$JwtAuth->desencriptar($valprovList->materno)." ".$JwtAuth->desencriptar($valprovList->nombre);
                            }
                            $arrayProvFor = array(
                                "token_prov" => $valprovList->token_cat_proveedores,
                                "nombre_prov" => $nombreProv,
                                "clave" => $clave_identificador,
                                "gs1" => $gs1_identificador,
                            );
                            $arrayProdProv[] = $arrayProvFor;
                        }

                        $countExistMp = DB::select("SELECT COUNT(alm.id) AS cont FROM detalle_almacen AS alm 
                        JOIN catalogo_productos AS catprod JOIN nivel_almacen AS nivel
                        WHERE alm.nivel_almacen = nivel.id_nivel AND alm.producto = catprod.id 
                        AND catprod.token_cat_productos = ? AND nivel.id_nivel = 1
                        AND alm.status_disponibilidad = TRUE",[$parametrosArray['token_cat_productos']]);     
                
                        if ($countExistMp[0]->cont != 0) {
                            $arrayAlm1 = array();
                            $totalExistMp = DB::select("SELECT SUM(alm.existencia) as existencia FROM detalle_almacen AS alm 
                                JOIN catalogo_productos AS catprod JOIN nivel_almacen AS nivel
                                WHERE alm.nivel_almacen = nivel.id_nivel AND alm.producto = catprod.id 
                                AND catprod.token_cat_productos = ? AND nivel.id_nivel = 1
                                AND alm.status_disponibilidad = TRUE",[$parametrosArray['token_cat_productos']]);            
                
                            if ($totalExistMp[0]->existencia == '' || $totalExistMp[0]->existencia == NULL) {
                                $resTotalExistMatPrim = 0;
                            } else {
                                $resTotalExistMatPrim = $totalExistMp[0]->existencia;
                            }
                        
                            $dirAlm = DB::select("SELECT alm.token_almacen,alm.alias_almacen,dir.alias,dir.calle,dir.num_ext,
                                dir.num_int,Cpostal.cod_postal,col.colonia,del.clave,del.deleg_mun,entFed.entidad,
                                dir.localidad,dir.calle1,dir.calle2,dir.referencia,dir.pais FROM direcciones AS dir JOIN almacen AS alm   
                                JOIN codpostal AS Cpostal JOIN colonias AS col JOIN deleg_mun AS del 
                                JOIN entidad_federativa AS entFed WHERE dir.id = alm.ubicacion 
                                AND alm.id IN (SELECT detalm.almacen FROM detalle_almacen AS detalm JOIN catalogo_productos AS catprod 
                                    JOIN nivel_almacen AS nivel WHERE detalm.producto = catprod.id AND catprod.token_cat_productos = ?
                                    AND detalm.nivel_almacen = nivel.id_nivel AND nivel.id_nivel = 1 AND detalm.status_disponibilidad = TRUE)
                                AND dir.cod_postal = Cpostal.id AND dir.colonia = col.id AND dir.delegacion_municipio = del.id
                                AND dir.ent_federativa = entFed.id",[$parametrosArray['token_cat_productos']]);  
                                //AND detalm.status_disponibilidad = TRUE          
                
                            foreach ($dirAlm as $resdirAlm) {
                                //$list->__SET('id_producto',$res_catProd->id_producto);
                                $dattoken_almacen = $resdirAlm->token_almacen;
                                $datalias = $JwtAuth->desencriptar($resdirAlm->alias_almacen);
                                $desgloseAlm1 = array();
                            
                                if ($resdirAlm->pais == '118') {
                                    $dir_completaAlm = "Calle ".$JwtAuth->desencriptar($resdirAlm->calle)
                                        ." No. ".$JwtAuth->desencriptar($resdirAlm->num_ext)." Int.".$JwtAuth->desencriptar($resdirAlm->num_int)
                                        .", C.P. ".$resdirAlm->cod_postal." Col. ".$resdirAlm->colonia.", ".$resdirAlm->deleg_mun.", ".$resdirAlm->entidad
                                        .", ".$resdirAlm->pais.", loc ".$JwtAuth->desencriptar($resdirAlm->localidad)
                                        .", entre ".$JwtAuth->desencriptar($resdirAlm->calle1)." y ".$JwtAuth->desencriptar($resdirAlm->calle2)
                                        ." referencia ".$JwtAuth->desencriptar($resdirAlm->referencia);
                                } else {
                                    $dir_completaAlm = "Alias: ".$JwtAuth->desencriptar($resdirAlm->alias).", Calle ".$JwtAuth->desencriptar($resdirAlm->calle)
                                        .", C.P. ".$JwtAuth->desencriptar($resdirAlm->cod_postalext).", ".$resdirAlm->pais;
                                } 
                
                                $dataExistAlmMatPrim = DB::select("SELECT SUM(detalm.existencia)AS existencia
                                    FROM detalle_almacen AS detalm JOIN catalogo_productos AS catprod 
                                    JOIN nivel_almacen AS nivel JOIN almacen AS alm
                                    WHERE detalm.nivel_almacen = nivel.id_nivel AND nivel.id_nivel = 1 
                                    AND detalm.almacen = alm.id AND alm.token_almacen = ?
                                    AND detalm.producto = catprod.id AND catprod.token_cat_productos = ?
                                    AND detalm.status_disponibilidad = TRUE",[$resdirAlm->token_almacen,$parametrosArray['token_cat_productos']]);            
                
                                $existAlm = $dataExistAlmMatPrim[0]->existencia;
                            
                                $desgloseExistAlmMatPrim = DB::select("SELECT detalm.token_detalle_almacen,detalm.almacen,
                                    detalm.num_serie,detalm.num_lote,detalm.importado,detalm.existencia,
                                    medEnt.token_unidad_medida AS unidad_entrada,
                                    medSal.token_unidad_medida AS unidad_salida
                                    FROM detalle_almacen AS detalm 
                                    JOIN catalogo_productos AS catprod 
                                    JOIN nivel_almacen AS nivel 
                                    JOIN unidad_medida AS medEnt 
                                    JOIN unidad_medida AS medSal 
                                    JOIN almacen AS alm 
                                    WHERE detalm.nivel_almacen = nivel.id_nivel
                                    AND nivel.id_nivel = 1
                                    AND detalm.unidad_entrada = medEnt.id
                                    AND detalm.unidad_salida = medSal.id
                                    AND detalm.almacen = alm.id
                                    AND alm.token_almacen = ?
                                    AND detalm.producto = catprod.id 
                                    AND catprod.token_cat_productos = ?
                                    AND detalm.status_disponibilidad = TRUE",[$resdirAlm->token_almacen,$parametrosArray['token_cat_productos']]);            
                
                                foreach ($desgloseExistAlmMatPrim as $desgdesgloseExistAlm) {
                                    //echo $desgdesgloseExistAlm->id_nivel." ";
                                    $arrayAlmNotIn1 = array();
                                    $dirAlmNot = DB::select("SELECT alm.token_almacen,alm.alias_almacen,dir.alias,dir.calle,dir.num_ext,
                                        dir.num_int,Cpostal.cod_postal,col.colonia,del.clave,del.deleg_mun,entFed.entidad,
                                        dir.localidad,dir.calle1,dir.calle2,dir.referencia,dir.pais FROM direcciones AS dir JOIN almacen AS alm   
                                        JOIN codpostal AS Cpostal JOIN colonias AS col JOIN deleg_mun AS del 
                                        JOIN entidad_federativa AS entFed WHERE dir.id = alm.ubicacion 
                                        AND alm.id != ?
                                        AND dir.cod_postal = Cpostal.id AND dir.colonia = col.id AND dir.delegacion_municipio = del.id
                                        AND dir.ent_federativa = entFed.id",[$desgdesgloseExistAlm->almacen]);  
                                        //AND detalm.status_disponibilidad = TRUE          
                
                                    foreach ($dirAlmNot as $resdirAlmNot) {
                                        $dattoken_almacenNot = $resdirAlmNot->token_almacen;
                                        $dataliasNot = $JwtAuth->desencriptar($resdirAlmNot->alias_almacen);
                                    
                                        if ($resdirAlmNot->pais == '118') {
                                            $dir_completaAlmNot = "Calle ".$JwtAuth->desencriptar($resdirAlmNot->calle)
                                                ." No. ".$JwtAuth->desencriptar($resdirAlmNot->num_ext)." Int.".$JwtAuth->desencriptar($resdirAlmNot->num_int)
                                                .", C.P. ".$resdirAlmNot->cod_postal." Col. ".$resdirAlmNot->colonia.", ".$resdirAlmNot->deleg_mun.", ".$resdirAlmNot->entidad
                                                .", ".$resdirAlmNot->pais.", loc ".$JwtAuth->desencriptar($resdirAlmNot->localidad)
                                                .", entre ".$JwtAuth->desencriptar($resdirAlmNot->calle1)." y ".$JwtAuth->desencriptar($resdirAlmNot->calle2)
                                                ." referencia ".$JwtAuth->desencriptar($resdirAlmNot->referencia);
                                        } else {
                                            $dir_completaAlmNot = "Alias: ".$JwtAuth->desencriptar($resdirAlmNot->alias).", Calle ".$JwtAuth->desencriptar($resdirAlmNot->calle)
                                                .", C.P. ".$JwtAuth->desencriptar($resdirAlmNot->cod_postalext).", ".$resdirAlmNot->pais;
                                        } 
                                    
                                        $internoArrayDirNot = array(
                                            "dattoken_almacen" => $dattoken_almacenNot,
                                            "alias_alm" => $dataliasNot,
                                            "dir_completaAlm" => $dir_completaAlmNot
                                        );
                                    
                                        $arrayAlmNotIn1[] = $internoArrayDirNot;
                                    }
                                
                                    if ($desgdesgloseExistAlm->num_serie == '' || $desgdesgloseExistAlm->num_serie == NULL) {
                                        $desgloSerie = '---';
                                    } else {
                                        $desgloSerie = $desgdesgloseExistAlm->num_serie;
                                    }
                                
                                    if ($desgdesgloseExistAlm->num_lote == '' || $desgdesgloseExistAlm->num_lote == NULL) {
                                        $desgloLoteTkn = '---';
                                        $desgloLote = '---';
                                    } else {
                                        $desglosQLote = DB::select("SELECT token_lote,numero_lote FROM lote_prod 
                                            WHERE id = ?",[$desgdesgloseExistAlm->num_lote]);  
                                        $desgloLoteTkn = $desglosQLote[0]->token_lote;
                                        $desgloLote = $desglosQLote[0]->numero_lote;
                                    }
                
                                    if ($desgdesgloseExistAlm->importado == '' || $desgdesgloseExistAlm->importado == NULL) {
                                        $desgloImportTkn = '---';
                                        $desgloImport = '---';
                                    } else {
                                        $desglosQPed = DB::select("SELECT token_pedimento,numero_pedimento FROM pedimento_aduanal 
                                            WHERE id = ?",[$desgdesgloseExistAlm->importado]);  
                                        $desgloImportTkn = $desglosQPed[0]->token_pedimento;
                                        $desgloImport = $desglosQPed[0]->numero_pedimento;
                                    }
                
                                    $listMedidasEntrada = UMedidaModelo::all();
                                    $arrayMedEntrada = array();
                                    $listMedidasSalida = UMedidaModelo::all();
                                    $arrayMedSalida = array();
                                    foreach ($listMedidasEntrada as $key => $valMedEntrada) {
                                        if ($valMedEntrada->token_unidad_medida == $desgdesgloseExistAlm->unidad_entrada) {
                                            $disabled = true;
                                        } else {
                                            $disabled = false;
                                        }
                
                                        $arrayMedEach = array(
                                            "token_unidad_medida" => $valMedEntrada->token_unidad_medida,
                                            "disabled" => $disabled,
                                            "teci_unidad_medida AS umed" => $valMedEntrada->unidad_medida,
                                            "sat_clave" => $valMedEntrada->sat_clave
                                        );
                                        $arrayMedEntrada[] = $arrayMedEach;
                                    }
                
                                    foreach ($listMedidasSalida as $key => $valMedSalida) {
                                        if ($valMedSalida->token_unidad_medida == $desgdesgloseExistAlm->unidad_salida) {
                                            $disabled = true;
                                        } else {
                                            $disabled = false;
                                        }
                
                                        $arrayMedEach = array(
                                            "token_unidad_medida" => $valMedSalida->token_unidad_medida,
                                            "disabled" => $disabled,
                                            "teci_unidad_medida AS umed" => $valMedSalida->unidad_medida,
                                            "sat_clave" => $valMedSalida->sat_clave
                                        );
                                        $arrayMedSalida[] = $arrayMedEach;
                                    }
                
                                    $arraInternoDesg1 = array(
                                        "token_detalle_almacen" => $desgdesgloseExistAlm->token_detalle_almacen,
                                        "existencia" => $desgdesgloseExistAlm->existencia,
                                        "num_serie" => $desgloSerie,
                                        "desgloLoteTkn" => $desgloLoteTkn,
                                        "num_lote" => $desgloLote,
                                        "desgloImportTkn" => $desgloImportTkn,
                                        "desgloImport" => $desgloImport,
                                        "unidad_entrada" => $arrayMedEntrada,
                                        "unidad_salida" => $arrayMedSalida,
                                        "datosDirNot" => $arrayAlmNotIn1,
                                    );
                                    $desgloseAlm1[] = $arraInternoDesg1;
                                }
                
                                $internoArrayDir = array(
                                    "dattoken_almacen" => $dattoken_almacen,
                                    "dir_completaAlm" => $dir_completaAlm,
                                    "existAlm" => $existAlm,
                                    "desgloseAlm1" => $desgloseAlm1
                                );
                
                                $arrayAlm1[] = $internoArrayDir;
                
                            }
                            $arrayNivalm = array(
                                "resTotalExistMatPrim" => $resTotalExistMatPrim,
                                "datosDir" => $arrayAlm1,
                            );
                
                            $arrayNivelAlmacen1[] = $arrayNivalm;
                        } else {
                            $resTotalExistMatPrim = 0;
                        }
                
                        $countExistProdProcess = DB::select("SELECT COUNT(alm.id) AS cont FROM detalle_almacen AS alm 
                        JOIN catalogo_productos AS catprod JOIN nivel_almacen AS nivel
                        WHERE alm.nivel_almacen = nivel.id_nivel AND alm.producto = catprod.id 
                        AND catprod.token_cat_productos = ? AND nivel.id_nivel = 2
                        AND alm.status_disponibilidad = TRUE",[$parametrosArray['token_cat_productos']]);    
                        
                        if ($countExistProdProcess[0]->cont != 0) {
                            $arrayAlm2 = array();
                            $totalExistProdProcess = DB::select("SELECT SUM(alm.existencia) as existencia FROM detalle_almacen AS alm 
                                JOIN catalogo_productos AS catprod JOIN nivel_almacen AS nivel
                                WHERE alm.nivel_almacen = nivel.id_nivel AND alm.producto = catprod.id 
                                AND catprod.token_cat_productos = ? AND nivel.id_nivel = 2
                                AND alm.status_disponibilidad = TRUE",[$parametrosArray['token_cat_productos']]);            
                        
                            if ($totalExistProdProcess[0]->existencia == '' || $totalExistProdProcess[0]->existencia == NULL) {
                                $resTotalExistProdProcess  = 0;
                            } else {
                                $resTotalExistProdProcess = $totalExistProdProcess[0]->existencia;
                            }
                        
                            $dirAlm = DB::select("SELECT alm.token_almacen,alm.alias_almacen,dir.alias,dir.calle,dir.num_ext,
                                dir.num_int,Cpostal.cod_postal,col.colonia,del.clave,del.deleg_mun,entFed.entidad,
                                dir.localidad,dir.calle1,dir.calle2,dir.referencia,dir.pais FROM direcciones AS dir JOIN almacen AS alm   
                                JOIN codpostal AS Cpostal JOIN colonias AS col JOIN deleg_mun AS del 
                                JOIN entidad_federativa AS entFed WHERE dir.id = alm.ubicacion 
                                AND alm.id IN (SELECT detalm.almacen FROM detalle_almacen AS detalm JOIN catalogo_productos AS catprod 
                                    JOIN nivel_almacen AS nivel WHERE detalm.producto = catprod.id AND catprod.token_cat_productos = ?
                                    AND detalm.nivel_almacen = nivel.id_nivel AND nivel.id_nivel = 2 AND detalm.status_disponibilidad = TRUE)
                                AND dir.cod_postal = Cpostal.id AND dir.colonia = col.id AND dir.delegacion_municipio = del.id
                                AND dir.ent_federativa = entFed.id",[$parametrosArray['token_cat_productos']]);  
                                //AND detalm.status_disponibilidad = TRUE          
                        
                            foreach ($dirAlm as $resdirAlm) {
                                //$list->__SET('id_producto',$res_catProd->id_producto);
                                $dattoken_almacen = $resdirAlm->token_almacen;
                                $datalias = $JwtAuth->desencriptar($resdirAlm->alias_almacen);
                                $desgloseAlm2 = array();
                            
                                if ($resdirAlm->pais == '118') {
                                    $dir_completaAlm = "Calle ".$JwtAuth->desencriptar($resdirAlm->calle)
                                        ." No. ".$JwtAuth->desencriptar($resdirAlm->num_ext)." Int.".$JwtAuth->desencriptar($resdirAlm->num_int)
                                        .", C.P. ".$resdirAlm->cod_postal." Col. ".$resdirAlm->colonia.", ".$resdirAlm->deleg_mun.", ".$resdirAlm->entidad
                                        .", ".$resdirAlm->pais.", loc ".$JwtAuth->desencriptar($resdirAlm->localidad)
                                        .", entre ".$JwtAuth->desencriptar($resdirAlm->calle1)." y ".$JwtAuth->desencriptar($resdirAlm->calle2)
                                        ." referencia ".$JwtAuth->desencriptar($resdirAlm->referencia);
                                } else {
                                    $dir_completaAlm = "Alias: ".$JwtAuth->desencriptar($resdirAlm->alias).", Calle ".$JwtAuth->desencriptar($resdirAlm->calle)
                                        .", C.P. ".$JwtAuth->desencriptar($resdirAlm->cod_postalext).", ".$resdirAlm->pais;
                                } 
                            
                                $dataExistAlmProdProcess  = DB::select("SELECT SUM(detalm.existencia)AS existencia
                                    FROM detalle_almacen AS detalm JOIN catalogo_productos AS catprod 
                                    JOIN nivel_almacen AS nivel JOIN almacen AS alm
                                    WHERE detalm.nivel_almacen = nivel.id_nivel AND nivel.id_nivel = 2 
                                    AND detalm.almacen = alm.id AND alm.token_almacen = ?
                                    AND detalm.producto = catprod.id AND catprod.token_cat_productos = ?
                                    AND detalm.status_disponibilidad = TRUE",[$resdirAlm->token_almacen,$parametrosArray['token_cat_productos']]);            
                        
                                $existAlm = $dataExistAlmProdProcess[0]->existencia;
                            
                                $desgloseExistAlmProdProcess  = DB::select("SELECT detalm.token_detalle_almacen,detalm.almacen,
                                    detalm.num_serie,
                                    detalm.num_lote,detalm.importado,detalm.existencia,
                                    medEnt.token_unidad_medida AS unidad_entrada,
                                    medSal.token_unidad_medida AS unidad_salida
                                    FROM detalle_almacen AS detalm 
                                    JOIN catalogo_productos AS catprod 
                                    JOIN nivel_almacen AS nivel 
                                    JOIN unidad_medida AS medEnt 
                                    JOIN unidad_medida AS medSal 
                                    JOIN almacen AS alm 
                                    WHERE detalm.nivel_almacen = nivel.id_nivel
                                    AND nivel.id_nivel = 2
                                    AND detalm.unidad_entrada = medEnt.id
                                    AND detalm.unidad_salida = medSal.id
                                    AND detalm.almacen = alm.id
                                    AND alm.token_almacen = ?
                                    AND detalm.producto = catprod.id 
                                    AND catprod.token_cat_productos = ?
                                    AND detalm.status_disponibilidad = TRUE",[$resdirAlm->token_almacen,$parametrosArray['token_cat_productos']]);            
                        
                                foreach ($desgloseExistAlmProdProcess as $desgdesgloseExistAlm) {
                                    $arrayAlmNotIn2 = array();
                                    $dirAlmNot = DB::select("SELECT alm.token_almacen,alm.alias_almacen,dir.alias,dir.calle,dir.num_ext,
                                        dir.num_int,Cpostal.cod_postal,col.colonia,del.clave,del.deleg_mun,entFed.entidad,
                                        dir.localidad,dir.calle1,dir.calle2,dir.referencia,dir.pais FROM direcciones AS dir JOIN almacen AS alm   
                                        JOIN codpostal AS Cpostal JOIN colonias AS col JOIN deleg_mun AS del 
                                        JOIN entidad_federativa AS entFed WHERE dir.id = alm.ubicacion 
                                        AND alm.id != ?
                                        AND dir.cod_postal = Cpostal.id AND dir.colonia = col.id AND dir.delegacion_municipio = del.id
                                        AND dir.ent_federativa = entFed.id",[$desgdesgloseExistAlm->almacen]);  
                                        //AND detalm.status_disponibilidad = TRUE          
                                    
                                    foreach ($dirAlmNot as $resdirAlmNot) {
                                        $dattoken_almacenNot = $resdirAlmNot->token_almacen;
                                        $dataliasNot = $JwtAuth->desencriptar($resdirAlmNot->alias_almacen);
                                    
                                        if ($resdirAlmNot->pais == '118') {
                                            $dir_completaAlmNot = "Calle ".$JwtAuth->desencriptar($resdirAlmNot->calle)
                                                ." No. ".$JwtAuth->desencriptar($resdirAlmNot->num_ext)." Int.".$JwtAuth->desencriptar($resdirAlmNot->num_int)
                                                .", C.P. ".$resdirAlmNot->cod_postal." Col. ".$resdirAlmNot->colonia.", ".$resdirAlmNot->deleg_mun.", ".$resdirAlmNot->entidad
                                                .", ".$resdirAlmNot->pais.", loc ".$JwtAuth->desencriptar($resdirAlmNot->localidad)
                                                .", entre ".$JwtAuth->desencriptar($resdirAlmNot->calle1)." y ".$JwtAuth->desencriptar($resdirAlmNot->calle2)
                                                ." referencia ".$JwtAuth->desencriptar($resdirAlmNot->referencia);
                                        } else {
                                            $dir_completaAlmNot = "Alias: ".$JwtAuth->desencriptar($resdirAlmNot->alias).", Calle ".$JwtAuth->desencriptar($resdirAlmNot->calle)
                                                .", C.P. ".$JwtAuth->desencriptar($resdirAlmNot->cod_postalext).", ".$resdirAlmNot->pais;
                                        } 
                                    
                                        $internoArrayDirNot = array(
                                            "dattoken_almacen" => $dattoken_almacenNot,
                                            "alias_alm" => $dataliasNot,
                                            "dir_completaAlm" => $dir_completaAlmNot
                                        );
                                    
                                        $arrayAlmNotIn2[] = $internoArrayDirNot;
                                    }
                        
                                    if ($desgdesgloseExistAlm->num_serie == '' || $desgdesgloseExistAlm->num_serie == NULL) {
                                        $desgloSerie = '---';
                                    } else {
                                        $desgloSerie = $desgdesgloseExistAlm->num_serie;
                                    }
                        
                                    if ($desgdesgloseExistAlm->num_lote == '' || $desgdesgloseExistAlm->num_lote == NULL) {
                                        $desgloLoteTkn = '---';
                                        $desgloLote = '---';
                                    } else {
                                        $desglosQLote = DB::select("SELECT token_lote,numero_lote FROM lote_prod 
                                            WHERE id = ?",[$desgdesgloseExistAlm->num_lote]);  
                                        $desgloLoteTkn = $desglosQLote[0]->token_lote;
                                        $desgloLote = $desglosQLote[0]->numero_lote;
                                    }
                        
                                    if ($desgdesgloseExistAlm->importado == '' || $desgdesgloseExistAlm->importado == NULL) {
                                        $desgloImportTkn = '---';
                                        $desgloImport = '---';
                                    } else {
                                        $desglosQPed = DB::select("SELECT token_pedimento,numero_pedimento FROM pedimento_aduanal 
                                            WHERE id = ?",[$desgdesgloseExistAlm->importado]);  
                                        $desgloImportTkn = $desglosQPed[0]->token_pedimento;
                                        $desgloImport = $desglosQPed[0]->numero_pedimento;
                                    }
                        
                                    $listMedidasEntrada = UMedidaModelo::all();
                                    $arrayMedEntrada = array();
                                    $listMedidasSalida = UMedidaModelo::all();
                                    $arrayMedSalida = array();
                                    foreach ($listMedidasEntrada as $key => $valMedEntrada) {
                                        if ($valMedEntrada->token_unidad_medida == $desgdesgloseExistAlm->unidad_entrada) {
                                            $disabled = true;
                                        } else {
                                            $disabled = false;
                                        }
                                        
                                        $arrayMedEach = array(
                                            "token_unidad_medida" => $valMedEntrada->token_unidad_medida,
                                            "disabled" => $disabled,
                                            "teci_unidad_medida AS umed" => $valMedEntrada->unidad_medida,
                                            "sat_clave" => $valMedEntrada->sat_clave
                                        );
                                        $arrayMedEntrada[] = $arrayMedEach;
                                    }
                                    
                                    foreach ($listMedidasSalida as $key => $valMedSalida) {
                                        if ($valMedSalida->token_unidad_medida == $desgdesgloseExistAlm->unidad_salida) {
                                            $disabled = true;
                                        } else {
                                            $disabled = false;
                                        }
                                        
                                        $arrayMedEach = array(
                                            "token_unidad_medida" => $valMedSalida->token_unidad_medida,
                                            "disabled" => $disabled,
                                            "teci_unidad_medida AS umed" => $valMedSalida->unidad_medida,
                                            "sat_clave" => $valMedSalida->sat_clave
                                        );
                                        $arrayMedSalida[] = $arrayMedEach;
                                    }
                        
                                    $arraInternoDesg2 = array(
                                        "token_detalle_almacen" => $desgdesgloseExistAlm->token_detalle_almacen,
                                        "existencia" => $desgdesgloseExistAlm->existencia,
                                        "num_serie" => $desgloSerie,
                                        "desgloLoteTkn" => $desgloLoteTkn,
                                        "num_lote" => $desgloLote,
                                        "desgloImportTkn" => $desgloImportTkn,
                                        "desgloImport" => $desgloImport,
                                        "unidad_entrada" => $arrayMedEntrada,
                                        "unidad_salida" => $arrayMedSalida,
                                        "datosDirNot" => $arrayAlmNotIn2,
                                    );
                                    $desgloseAlm2[] = $arraInternoDesg2;
                                }
                        
                                $internoArrayDir = array(
                                    "dattoken_almacen" => $dattoken_almacen,
                                    "alias_alm" => $datalias,
                                    "dir_completaAlm" => $dir_completaAlm,
                                    "existAlm" => $existAlm,
                                    "desgloseAlm2" => $desgloseAlm2
                                );
                                
                                $arrayAlm2[] = $internoArrayDir;
                        
                            }
                            $arrayNivalm = array(
                                "resTotalExistProdProcess" => $resTotalExistProdProcess,
                                "datosDir" => $arrayAlm2,
                            );
                        
                            $arrayNivelAlmacen2[] = $arrayNivalm;
                        } else {
                            $resTotalExistProdProcess = 0;
                        }
                
                        $countExistProdTerminado = DB::select("SELECT COUNT(alm.id) AS cont FROM detalle_almacen AS alm 
                        JOIN catalogo_productos AS catprod JOIN nivel_almacen AS nivel
                        WHERE alm.nivel_almacen = nivel.id_nivel AND alm.producto = catprod.id 
                        AND catprod.token_cat_productos = ? AND nivel.id_nivel = 3
                        AND alm.status_disponibilidad = TRUE",[$parametrosArray['token_cat_productos']]); 
                
                        if ($countExistProdTerminado[0]->cont != 0) {
                            $arrayAlm3 = array();
                        
                            $totalExistProdTerminado = DB::select("SELECT SUM(alm.existencia) as existencia FROM detalle_almacen AS alm 
                                JOIN catalogo_productos AS catprod JOIN nivel_almacen AS nivel
                                WHERE alm.nivel_almacen = nivel.id_nivel AND alm.producto = catprod.id 
                                AND catprod.token_cat_productos = ? AND nivel.id_nivel = 3
                                AND alm.status_disponibilidad = TRUE",[$parametrosArray['token_cat_productos']]);            
                
                            if ($totalExistProdTerminado[0]->existencia == '' || $totalExistProdTerminado[0]->existencia == NULL) {
                                $resTotalExistProdTerminado  = 0;
                            } else {
                                $resTotalExistProdTerminado = $totalExistProdTerminado[0]->existencia;
                            }
                        
                            $dirAlm = DB::select("SELECT alm.token_almacen,alm.alias_almacen,dir.alias,dir.calle,dir.num_ext,
                                dir.num_int,Cpostal.cod_postal,col.colonia,del.clave,del.deleg_mun,entFed.entidad,
                                dir.localidad,dir.calle1,dir.calle2,dir.referencia,dir.pais FROM direcciones AS dir JOIN almacen AS alm   
                                JOIN codpostal AS Cpostal JOIN colonias AS col JOIN deleg_mun AS del 
                                JOIN entidad_federativa AS entFed WHERE dir.id = alm.ubicacion 
                                AND alm.id IN (SELECT detalm.almacen FROM detalle_almacen AS detalm JOIN catalogo_productos AS catprod 
                                    JOIN nivel_almacen AS nivel WHERE detalm.producto = catprod.id AND catprod.token_cat_productos = ?
                                    AND detalm.nivel_almacen = nivel.id_nivel AND nivel.id_nivel = 3 AND detalm.status_disponibilidad = TRUE)
                                AND dir.cod_postal = Cpostal.id AND dir.colonia = col.id AND dir.delegacion_municipio = del.id
                                AND dir.ent_federativa = entFed.id",[$parametrosArray['token_cat_productos']]);  
                                //AND detalm.status_disponibilidad = TRUE          
                        
                            foreach ($dirAlm as $resdirAlm) {
                                //$list->__SET('id_producto',$res_catProd->id_producto);
                                $dattoken_almacen = $resdirAlm->token_almacen;
                                $datalias = $JwtAuth->desencriptar($resdirAlm->alias_almacen);
                                $desgloseAlm3 = array();
                            
                                if ($resdirAlm->pais == '118') {
                                    $dir_completaAlm = "Calle ".$JwtAuth->desencriptar($resdirAlm->calle)
                                        ." No. ".$JwtAuth->desencriptar($resdirAlm->num_ext)." Int.".$JwtAuth->desencriptar($resdirAlm->num_int)
                                        .", C.P. ".$resdirAlm->cod_postal." Col. ".$resdirAlm->colonia.", ".$resdirAlm->deleg_mun.", ".$resdirAlm->entidad
                                        .", ".$resdirAlm->pais.", loc ".$JwtAuth->desencriptar($resdirAlm->localidad)
                                        .", entre ".$JwtAuth->desencriptar($resdirAlm->calle1)." y ".$JwtAuth->desencriptar($resdirAlm->calle2)
                                        ." referencia ".$JwtAuth->desencriptar($resdirAlm->referencia);
                                } else {
                                    $dir_completaAlm = "Alias: ".$JwtAuth->desencriptar($resdirAlm->alias).", Calle ".$JwtAuth->desencriptar($resdirAlm->calle)
                                        .", C.P. ".$JwtAuth->desencriptar($resdirAlm->cod_postalext).", ".$resdirAlm->pais;
                                } 
                            
                                $dataExistAlmProdTerminado  = DB::select("SELECT SUM(detalm.existencia)AS existencia
                                    FROM detalle_almacen AS detalm JOIN catalogo_productos AS catprod 
                                    JOIN nivel_almacen AS nivel JOIN almacen AS alm
                                    WHERE detalm.nivel_almacen = nivel.id_nivel AND nivel.id_nivel = 3 
                                    AND detalm.almacen = alm.id AND alm.token_almacen = ?
                                    AND detalm.producto = catprod.id AND catprod.token_cat_productos = ?
                                    AND detalm.status_disponibilidad = TRUE",[$resdirAlm->token_almacen,$parametrosArray['token_cat_productos']]);            
                
                                $existAlm = $dataExistAlmProdTerminado[0]->existencia;
                
                                $desgloseExistAlmProdTerminado  = DB::select("SELECT detalm.token_detalle_almacen,detalm.almacen,
                                    detalm.num_serie,detalm.num_lote,detalm.importado,detalm.existencia,
                                    medEnt.token_unidad_medida AS unidad_entrada,
                                    medSal.token_unidad_medida AS unidad_salida
                                    FROM detalle_almacen AS detalm 
                                    JOIN catalogo_productos AS catprod 
                                    JOIN nivel_almacen AS nivel 
                                    JOIN unidad_medida AS medEnt 
                                    JOIN unidad_medida AS medSal 
                                    JOIN almacen AS alm 
                                    WHERE detalm.nivel_almacen = nivel.id_nivel
                                    AND nivel.id_nivel = 3
                                    AND detalm.unidad_entrada = medEnt.id
                                    AND detalm.unidad_salida = medSal.id
                                    AND detalm.almacen = alm.id
                                    AND alm.token_almacen = ?
                                    AND detalm.producto = catprod.id 
                                    AND catprod.token_cat_productos = ?
                                    AND detalm.status_disponibilidad = TRUE",[$resdirAlm->token_almacen,$parametrosArray['token_cat_productos']]);            
                
                                foreach ($desgloseExistAlmProdTerminado as $desgdesgloseExistAlm) {
                                    //echo $desgdesgloseExistAlm->id_nivel." ";
                                    //num_lote,importado
                                    $arrayAlmNotIn3 = array();
                                    $dirAlmNot = DB::select("SELECT alm.token_almacen,alm.alias_almacen,dir.alias,dir.calle,dir.num_ext,
                                        dir.num_int,Cpostal.cod_postal,col.colonia,del.clave,del.deleg_mun,entFed.entidad,
                                        dir.localidad,dir.calle1,dir.calle2,dir.referencia,dir.pais FROM direcciones AS dir JOIN almacen AS alm   
                                        JOIN codpostal AS Cpostal JOIN colonias AS col JOIN deleg_mun AS del 
                                        JOIN entidad_federativa AS entFed WHERE dir.id = alm.ubicacion 
                                        AND alm.id != ?
                                        AND dir.cod_postal = Cpostal.id AND dir.colonia = col.id AND dir.delegacion_municipio = del.id
                                        AND dir.ent_federativa = entFed.id",[$desgdesgloseExistAlm->almacen]);  
                                        //AND detalm.status_disponibilidad = TRUE          
                
                                    foreach ($dirAlmNot as $resdirAlmNot) {
                                        $dattoken_almacenNot = $resdirAlmNot->token_almacen;
                                        $dataliasNot = $JwtAuth->desencriptar($resdirAlmNot->alias_almacen);
                                    
                                        if ($resdirAlmNot->pais == '118') {
                                            $dir_completaAlmNot = "Calle ".$JwtAuth->desencriptar($resdirAlmNot->calle)
                                                ." No. ".$JwtAuth->desencriptar($resdirAlmNot->num_ext)." Int.".$JwtAuth->desencriptar($resdirAlmNot->num_int)
                                                .", C.P. ".$resdirAlmNot->cod_postal." Col. ".$resdirAlmNot->colonia.", ".$resdirAlmNot->deleg_mun.", ".$resdirAlmNot->entidad
                                                .", ".$resdirAlmNot->pais.", loc ".$JwtAuth->desencriptar($resdirAlmNot->localidad)
                                                .", entre ".$JwtAuth->desencriptar($resdirAlmNot->calle1)." y ".$JwtAuth->desencriptar($resdirAlmNot->calle2)
                                                ." referencia ".$JwtAuth->desencriptar($resdirAlmNot->referencia);
                                        } else {
                                            $dir_completaAlmNot = "Alias: ".$JwtAuth->desencriptar($resdirAlmNot->alias).", Calle ".$JwtAuth->desencriptar($resdirAlmNot->calle)
                                                .", C.P. ".$JwtAuth->desencriptar($resdirAlmNot->cod_postalext).", ".$resdirAlmNot->pais;
                                        } 
                
                                        $internoArrayDirNot = array(
                                            "dattoken_almacen" => $dattoken_almacenNot,
                                            "alias_alm" => $dataliasNot,
                                            "dir_completaAlm" => $dir_completaAlmNot
                                        );
                
                                        $arrayAlmNotIn3[] = $internoArrayDirNot;
                                    }
                
                                    if ($desgdesgloseExistAlm->num_serie == '' || $desgdesgloseExistAlm->num_serie == NULL) {
                                        $desgloSerie = '---';
                                    } else {
                                        $desgloSerie = $desgdesgloseExistAlm->num_serie;
                                    }
                
                                    if ($desgdesgloseExistAlm->num_lote == '' || $desgdesgloseExistAlm->num_lote == NULL) {
                                        $desgloLoteTkn = '---';
                                        $desgloLote = '---';
                                    } else {
                                        $desglosQLote = DB::select("SELECT token_lote,numero_lote FROM lote_prod 
                                            WHERE id = ?",[$desgdesgloseExistAlm->num_lote]);  
                                        $desgloLoteTkn = $desglosQLote[0]->token_lote;
                                        $desgloLote = $desglosQLote[0]->numero_lote;
                                    }
                
                                    if ($desgdesgloseExistAlm->importado == '' || $desgdesgloseExistAlm->importado == NULL) {
                                        $desgloImportTkn = '---';
                                        $desgloImport = '---';
                                    } else {
                                        $desglosQPed = DB::select("SELECT token_pedimento,numero_pedimento FROM pedimento_aduanal 
                                            WHERE id = ?",[$desgdesgloseExistAlm->importado]);  
                                        $desgloImportTkn = $desglosQPed[0]->token_pedimento;
                                        $desgloImport = $desglosQPed[0]->numero_pedimento;
                                    }
                
                                    $listMedidasEntrada = UMedidaModelo::all();
                                    $arrayMedEntrada = array();
                                    $listMedidasSalida = UMedidaModelo::all();
                                    $arrayMedSalida = array();
                                    foreach ($listMedidasEntrada as $key => $valMedEntrada) {
                                        if ($valMedEntrada->token_unidad_medida == $desgdesgloseExistAlm->unidad_entrada) {
                                            $disabled = true;
                                        } else {
                                            $disabled = false;
                                        }
                
                                        $arrayMedEach = array(
                                            "token_unidad_medida" => $valMedEntrada->token_unidad_medida,
                                            "disabled" => $disabled,
                                            "teci_unidad_medida AS umed" => $valMedEntrada->unidad_medida,
                                            "sat_clave" => $valMedEntrada->sat_clave
                                        );
                                        $arrayMedEntrada[] = $arrayMedEach;
                                    }
                
                                    foreach ($listMedidasSalida as $key => $valMedSalida) {
                                        if ($valMedSalida->token_unidad_medida == $desgdesgloseExistAlm->unidad_salida) {
                                            $disabled = true;
                                        } else {
                                            $disabled = false;
                                        }
                
                                        $arrayMedEach = array(
                                            "token_unidad_medida" => $valMedSalida->token_unidad_medida,
                                            "disabled" => $disabled,
                                            "teci_unidad_medida AS umed" => $valMedSalida->unidad_medida,
                                            "sat_clave" => $valMedSalida->sat_clave
                                        );
                                        $arrayMedSalida[] = $arrayMedEach;
                                    }
                
                                    $arraInternoDesg3 = array(
                                        "token_detalle_almacen" => $desgdesgloseExistAlm->token_detalle_almacen,
                                        "existencia" => $desgdesgloseExistAlm->existencia,
                                        "num_serie" => $desgloSerie,
                                        "desgloLoteTkn" => $desgloLoteTkn,
                                        "num_lote" => $desgloLote,
                                        "desgloImportTkn" => $desgloImportTkn,
                                        "desgloImport" => $desgloImport,
                                        "unidad_entrada" => $arrayMedEntrada,
                                        "unidad_salida" => $arrayMedSalida,
                                        "datosDirNot" => $arrayAlmNotIn3,
                                    );
                                    $desgloseAlm3[] = $arraInternoDesg3;
                                }
                
                                $internoArrayDir = array(
                                    "dattoken_almacen" => $dattoken_almacen,
                                    "alias_alm" => $datalias,
                                    "dir_completaAlm" => $dir_completaAlm,
                                    "existAlm" => $existAlm,
                                    "desgloseAlm3" => $desgloseAlm3
                                );
                
                                $arrayAlm3[] = $internoArrayDir;
                
                            }
                
                            $arrayNivalm = array(
                                "resTotalExistProdTerminado" => $resTotalExistProdTerminado,
                                "datosDir" => $arrayAlm3,
                                //
                            );
                            $arrayNivelAlmacen3[] = $arrayNivalm;
                        } else {
                            $resTotalExistProdTerminado = 0;
                        }
                
                        if($value->num_serie == true){
                            $num_serie = true;
                        } else {
                            $num_serie = false;
                        }
                
                        if($value->num_lote == true){
                            $num_lote = true;
                        } else {
                            $num_lote = false;
                        }
                
                        if($value->importado == true){
                            $importado = true;
                        } else {
                            $importado = false;
                        }	
                
                        $kardexList = ProductosModelo::join("kardex AS kdx","catprod.id","=","kdx.producto")
                        ->where(['catprod.token_cat_productos' => $value->token_cat_productos])->get();
                
                        foreach ($kardexList as $valKardex) {
                
                            $decimalesMoneda = DB::select("SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
                            JOIN main_empresas AS emp JOIN main_empresapersonal AS emppers JOIN vhum_personal AS pers 
                            JOIN main_usuarios AS users WHERE emp.moneda = catmon.id AND emp.emp_token = ?
                            AND emp.id = emppers.empresa AND emppers.personal = pers.id 
                            AND pers.usuario = users.id AND users.user_token = ?",
                            [$usuario->emp_token,$usuario->user_token]);
                
                            //cantidades 
                            $recibir_cantidad = $valKardex->recibir_cantidad;
                            $entrada_cantidad = $valKardex->entrada_cantidad;
                            $entregar_cantidad = $valKardex->entregar_cantidad;
                            $salida_cantidad = $valKardex->salida_cantidad;
                            $saldo_cantidad = $valKardex->saldo_cantidad;
                
                            //valores
                            $valor_unitario = DB::select("SELECT FORMAT(?,?) AS valor",[$valKardex->valor_unitario,$decimalesMoneda[0]->decimales]); 	
                            $recibir_valor = DB::select("SELECT FORMAT(?,?) AS valor",[$valKardex->recibir_valor,$decimalesMoneda[0]->decimales]); 
                            $entrada_valor = DB::select("SELECT FORMAT(?,?) AS valor",[$valKardex->entrada_valor,$decimalesMoneda[0]->decimales]); 
                            $entregar_valor = DB::select("SELECT FORMAT(?,?) AS valor",[$valKardex->entregar_valor,$decimalesMoneda[0]->decimales]); 
                            $salida_valor = DB::select("SELECT FORMAT(?,?) AS valor",[$valKardex->salida_valor,$decimalesMoneda[0]->decimales]); 
                            $saldo_valor = DB::select("SELECT FORMAT(?,?) AS valor",[$valKardex->saldo_valor,$decimalesMoneda[0]->decimales]);
                
                            if ($valKardex->status_kardex == 6) {
                                $status_kardex = 'count';
                                $valfactura_compra = '---';
                                $valfactura_venta = '---';
                            } else {
                                $folioCompra = DB::select("SELECT folio_compra FROM compras WHERE id = ?",[$valKardex->factura_compra]);
                                $folioVenta = DB::select("SELECT numero_venta FROM ventas WHERE id = ?",[$valKardex->factura_venta]);
                
                                //echo " ".$JwtAuth->encriptar('0.00')." ";
                                if ($valKardex->status_kardex == 1) {
                                    $status_kardex = 'initcount';
                                    $valfactura_compra = '---';
                                    $valfactura_venta = '---';
                                }
                                if ($valKardex->status_kardex == 2) {
                                    $status_kardex = 'buyy';
                                    $valfactura_compra = $JwtAuth->generar($folioCompra[0]->folio_compra);
                                    $valfactura_venta = '---';
                                }
                                if ($valKardex->status_kardex == 3) {
                                    $status_kardex = 'devbuyy';
                                    $valfactura_compra = $JwtAuth->generar($folioCompra[0]->folio_compra);
                                    $valfactura_venta = '---';
                                }
                                if ($valKardex->status_kardex == 4) {
                                    $status_kardex = 'sell';
                                    $valfactura_compra = '---';
                                    $valfactura_venta = $JwtAuth->generar($folioVenta[0]->numero_venta);
                                }
                                if ($valKardex->status_kardex == 5) {
                                    $status_kardex = 'devsell';
                                    $valfactura_compra = '---';
                                    $valfactura_venta = $JwtAuth->generar($folioVenta[0]->numero_venta);
                                }
                            }
                            $forKardex = array(
                                "token_kardex" => $valKardex->token_kardex,	
                                "producto" => $valKardex->producto,	
                                "fecha" => date('d-m-Y H:i:s',$valKardex->fecha),	
                                "status_kardex" => $status_kardex,	
                                "concepto" => $valKardex->concepto,	
                                "factura_compra" => $valfactura_compra,	
                                "factura_venta" => $valfactura_venta,
                
                                "recibir_cantidad" => $recibir_cantidad,
                                "entrada_cantidad" => $entrada_cantidad,
                                "entregar_cantidad" => $entregar_cantidad,
                                "salida_cantidad" => $salida_cantidad,
                                "saldo_cantidad" => $saldo_cantidad,
                
                                "valor_unitario" => $valor_unitario[0]->valor, 	
                                "recibir_valor" => $recibir_valor[0]->valor, 
                                "entrada_valor" => $entrada_valor[0]->valor, 
                                "entregar_valor" => $entregar_valor[0]->valor, 
                                "salida_valor" => $salida_valor[0]->valor, 
                                "saldo_valor" => $saldo_valor[0]->valor,
                            );
                            $arrayKardex[] = $forKardex; 
                        }
                
                        //echo $JwtAuth->desencriptar($value->imagen).' ';
                        if ($value->imagen == '' || !file_exists(Storage::path('public/root/'.$value->root_tkn.'/0002-cpp/catalogos/productos/'
                            .$JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.$JwtAuth->generar($value->folio).
                            '-'.$value->fecha_alta.'/'.$JwtAuth->desencriptar($value->imagen))) || $JwtAuth->desencriptar($value->imagen) == 'default_prod.jpg') {
                            $logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/settings/default_prod.jpg'));
                        } else {
                            $logo_prod = $JwtAuth->encriptaBase64(Storage::path('public/root/'.$value->root_tkn.'/0002-cpp/catalogos/productos/'
                            .$JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                            $JwtAuth->generar($value->folio).'-'.$value->fecha_alta.'/'.$JwtAuth->desencriptar($value->imagen)));
                        }                

                        //descuentos
                            $arrayDescuentos = array();
                            $listaDesc = DescuentosModelo::join("main_empresas AS emp","descuentos.empresa","=","emp.id")
                            ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                            ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                            ->join("main_usuarios AS users","pers.usuario","=","users.id")
                            ->where([
                                "descuentos.status_activacion" => TRUE,
                                "descuentos.status" => TRUE,
                                'emp.emp_token' => $usuario->emp_token,
                                'users.user_token' => $usuario->user_token,
                            ])->get();
                            
                            foreach ($listaDesc as $valDesc) {
                                //da_te_default_timezone_set($value->zona_horaria);
                                if ($valDesc->cuo_porc == FALSE) {
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
                            
                               $queRDescSer = DB::select("SELECT detdescu.token_detalle_descuento,descu.token_descuentos
                                    FROM descuentos AS descu JOIN detalle_descuento AS detdescu
                                    JOIN catalogo_productos AS catprod JOIN main_empresas AS emp 
                                    WHERE descu.token_descuentos = ? AND descu.status = TRUE 
                                    AND descu.status_activacion = TRUE AND descu.id = detdescu.descuento
                                    AND detdescu.producto = catprod.id AND catprod.token_cat_productos = ?
                                    AND descu.empresa = emp.id AND emp.emp_token = ?",
                                    [$valDesc->token_descuentos,$value->token_cat_productos,$usuario->emp_token]);
                                $validateSerDesc = false;

                                if (count($queRDescSer) == 1) {
                                    $validateSerDesc = true;
                                    $tokenDescDetalle = $queRDescSer[0]->token_detalle_descuento;
                                } else {
                                    $validateSerDesc = false;
                                    $tokenDescDetalle = '';
                                }
                            
                                $arrayForeach = array(
                                "c_token" => $valDesc->token_descuentos,
                                "folio" => $JwtAuth->generar($valDesc->folio),
                                "alias" => $JwtAuth->desencriptar($valDesc->alias),
                                "concepto" => $JwtAuth->desencriptar($valDesc->concepto),
                                "cuo_porc" => $datCuotPorc,
                                "cantidad_base" => $JwtAuth->desencriptar($valDesc->cantidad_base),
                                "aplicacion" => $aplicatcion,
                                "fecha_inicio" => $fecha_inicio,
                                "fecha_fin" => $fecha_fin,
                                "fecha_activacion" => date('d-m-Y H:i:s',$valDesc->fecha_activacion),
                                "validateSerDesc" => $validateSerDesc,
                                "tokenDescDetalle" => $tokenDescDetalle,
                                );
                                $arrayDescuentos[] = $arrayForeach; 
                            }

                        //promociones
                            $arrayPromociones = array();
                            $listaPromo = PromocionesModelo::join("main_empresas AS emp","promociones.empresa","=","emp.id")
                            ->join("main_empresapersonal AS emppers","emp.id","=","emppers.empresa")
                            ->join("vhum_personal AS pers","emppers.personal","=","pers.id")
                            ->join("main_usuarios AS users","pers.usuario","=","users.id")
                            ->where([
                                "promociones.status_activacion" => TRUE,
                                "promociones.status" => TRUE,
                                'emp.emp_token' => $usuario->emp_token,
                                'users.user_token' => $usuario->user_token,
                            ])->get();
                            
                            foreach ($listaPromo as $valPromo) {
                                //da_te_default_timezone_set($valPromo->zona_horaria);
                                if ($valPromo->cuo_porc == 0) {
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
                            
                                $queRPromSer = DB::select("SELECT detpromo.token_detalle_promocion,promo.token_promocion FROM promociones AS promo 
                                    JOIN detalle_promocion AS detpromo JOIN catalogo_productos AS catprod JOIN main_empresas AS emp 
                                    WHERE promo.token_promocion = ? AND promo.status = TRUE AND promo.status_activacion = TRUE 
                                    AND promo.id = detpromo.promocion AND detpromo.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND promo.empresa = emp.id AND emp.emp_token = ?",
                                    [$valPromo->token_promocion,$value->token_cat_productos,$usuario->emp_token]);
                                $validateSerPromo = false;
                                if (count($queRPromSer) == 1) {
                                    $validateSerPromo = true;    
                                    $tokenPromoDetalle = $queRPromSer[0]->token_detalle_promocion;
                                } else {
                                    $validateSerPromo = false;
                                    $tokenPromoDetalle = '';
                                }
                                $arrayForeach = array(
                                "c_token" => $valPromo->token_promocion,
                                "folio" => $JwtAuth->generar($valPromo->folio),
                                "alias" => $JwtAuth->desencriptar($valPromo->alias),
                                "concepto" => $JwtAuth->desencriptar($valPromo->concepto),
                                "cuo_porc" => $datCuotPorc,
                                "cantidad_base" => $JwtAuth->desencriptar($valPromo->cantidad_base),
                                "aplicacion" => $aplicatcion,
                                "fecha_inicio" => $fecha_inicio,
                                "fecha_fin" => $fecha_fin,
                                "fecha_activacion" => date('d-m-Y H:i:s',$valPromo->fecha_activacion),
                                "validateSerPromo" => $validateSerPromo,
                                "tokenPromoDetalle" => $tokenPromoDetalle,
                                );
                                $arrayPromociones[] = $arrayForeach; 
                            }

                        $arrayForeach = array(
                            "c_token" => $value->token_cat_productos,
                            "fechaAlta" => date('d-m-Y H:i:s',$value->fechaAlta),
                            "producto" => $JwtAuth->desencriptar($value->producto),
                            "marca" => $JwtAuth->desencriptar($value->marca),
                            "clasificacion" => $JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->folio_genero).'-'.
                                $JwtAuth->generar($value->folio),
                            "proceso" => $value->proceso,
                            "stock_min" => $value->stock_min,
                            "stock_max" => $value->stock_max,
                            "costeo" => $value->costeo,
                            "imagen" => $logo_prod,
                            "num_serie" =>  $num_serie,
                            "num_lote" => $num_lote,
                            "importado" => $importado,
                            "codigo_gs1" => $value->codigo_gs1,	
                            //SAT
                            "concepto" => $value->concepto,
                            "clave" => $value->clave,
                            "descripcion" => $value->descripcion,
                            //NUIDAD DE MEDIDA
                            "teci_unidad_medida AS umed" => $value->unidad_medida,
                            "sat_claveMed" => $value->sat_clave,
                            "representa" => $value->representa,
                            "arrayProdProv" => $arrayProdProv,
                            "arrayNivelAlmacen1" => $arrayNivelAlmacen1,
                            "arrayNivelAlmacen2" => $arrayNivelAlmacen2,
                            "arrayNivelAlmacen3" => $arrayNivelAlmacen3,
                            "arrayKardex" => $arrayKardex,
                            "arrayDescuentos" => $arrayDescuentos,
                            "arrayPromociones" => $arrayPromociones,
                        );
                        $arrayProductosVig[] = $arrayForeach; 
                    }
                
                    $dataMensaje = array(
                        'arrayProductosVig' => $arrayProductosVig,
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
}
