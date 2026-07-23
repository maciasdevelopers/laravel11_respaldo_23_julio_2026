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

class TERC_AssociatesCatalogosController extends Controller{
//impuestos
    public function catalogoImpuestosVig(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
        $arrayImpuestos = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
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

                $queryImp = ImpuestosModelo::join('main_empresas AS emp','cont_impuestos_catalogo.empresa','emp.id')
                ->where(['cont_impuestos_catalogo.imp_status' => TRUE,'cont_impuestos_catalogo.assoc' => TRUE,
                'emp.empresa_token' => $usuario->empresa_token])->get();

                foreach ($queryImp as $value) {
                    if ($value->calculo == "tarifa") {
                        $importeExplode = explode("$",$value->importe);
                        $importe_imp = $importeExplode[1];
                    } else {
                        $importeExplode = explode("%",$value->importe);
                        $importe_imp = $importeExplode[0];
                    }
                    $arrayforeach = array(
                        "token_cat_impuestos" => $value->token_cat_impuestos,
                        "fecha_registro" => date('d-m-Y H:i:s',$value->fecha_registro),
                        "folio_impuesto" => 'IMP-'.$JwtAuth->generarFolio($value->folio_impuesto),
                        "alias" => $JwtAuth->desencriptar($value->alias),
                        "ret_tras" => $value->ret_tras == "rete" ? 'retenido' : 'trasladado',
                        "calculo" => $value->calculo == "tarifa" ? 'tarifa' : 'tasa',
                        "importe" => $importe_imp,
                        "txtimporte" => $value->importe,
                        "vinculacion" => false,
                    );
                    $arrayImpuestos[] = $arrayforeach;
                }

                $dataMensaje = array("status" => "success","code" => 200,"impuestos" => $arrayImpuestos);
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

    public function catalogoImpuestosEliminados(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
        $arrayImpuestos = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
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

                $queryImp = ImpuestosModelo::join('main_empresas AS emp','cont_impuestos_catalogo.empresa','emp.id')
                ->where(['cont_impuestos_catalogo.imp_status' => FALSE,'cont_impuestos_catalogo.assoc' => TRUE,
                'emp.empresa_token' => $usuario->empresa_token])->get();

                foreach ($queryImp as $value) {
                    $retTras = $value->ret_tras == "rete" ? 'retenido' : 'trasladado';
                    $imp_calculo = $value->calculo == "tarifa" ? 'tarifa' : 'tasa';
                    if ($value->calculo == "tarifa") {
                        $importeExplode = explode("$",$value->importe);
                        $importe_imp = $importeExplode[1];
                    } else {
                        $importeExplode = explode("%",$value->importe);
                        $importe_imp = $importeExplode[0];
                    }
                    $arrayforeach = array(
                        "token_cat_impuestos" => $value->token_cat_impuestos,
                        "fecha_registro" => date('d-m-Y H:i:s',$value->fecha_registro),
                        "folio_impuesto" => 'IMP-'.$JwtAuth->generarFolio($value->folio_impuesto),
                        "alias" => $JwtAuth->desencriptar($value->alias),
                        "ret_tras" => $retTras,
                        "calculo" => $imp_calculo,
                        "importe" => $importe_imp,
                        "txtimporte" => $value->importe,
                        "fecha_delete" => date('d-m-Y H:i:s',$value->imp_fecha_delete),
                    );
                    $arrayImpuestos[] = $arrayforeach;
                }

                $dataMensaje = array("status" => "success","code" => 200,"impuestos" => $arrayImpuestos);
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

//productos
    public function productoAssocCatalogo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayProductosVig = array();

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
                $prodList = DB::table("in_egr_catalogo_productos AS catprod") 
                ->join("teci_unidad_medida AS umed","catprod.medida_salida","=","umed.id")
                ->join("teci_catalogo_monedas AS money","catprod.moneda_aplicable","=","money.id")
                ->join("main_empresas AS emp","catprod.admin_empresa","=","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                ->join("vhum_personal AS pers","empuser.personal","=","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                ->where([
                    'catprod.modulo_mostrador' => TRUE,
                    'catprod.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();
                foreach ($prodList as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    //QRCode::text($value->token_cat_productos)->setOutfile(Storage::path('public/root/'.$value->fecha_registro_prod.'QRCode.png'))->png();    
                    $folio_prod = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('PROD-'.($value->post_folio != NULL ? $JwtAuth->generarFolio($value->folio_sistema):$JwtAuth->generarFolio($value->folio_sistema).'-'.$value->post_folio)):
                        'PROD-TEMP-'.$JwtAuth->generarFolio($value->temps_folio);

                    $autorizado_por = "";
                    if ($value->authorized == TRUE) {
                        $prodAuthQuery = DB::table("in_egr_catalogo_productos AS catprod") 
                        ->join("vhum_personal AS authPers","catprod.authorized_by","=","authPers.id")
                        ->join("sos_personas AS authPeople","authPers.personal","=","authPeople.id")
                        ->where(["catprod.token_cat_productos" => $value->token_cat_productos])->get();
                        $autorizado_por = $JwtAuth->desencriptarNombres($prodAuthQuery[0]->paterno,$prodAuthQuery[0]->materno,$prodAuthQuery[0]->nombre);
                    }
                    $arrayImpuestos = array();
                    $impuestoQuery = DB::table("in_egr_catalogo_productos AS catprod") 
                    ->join("in_egr_impuestos_articulos AS impart","catprod.id","=","impart.producto_rel")
                    ->join("cont_impuestos_catalogo AS catimp","impart.impuestos","=","catimp.id")
                    ->where(['catprod.token_cat_productos' => $value->token_cat_productos])->get();
                    
                    foreach ($impuestoQuery as $vqueim) {
                      $retTras = $vqueim->ret_tras == "rete" ? 'retenido' : 'trasladado';
                      $imp_calculo = $vqueim->calculo == "tarifa" ? 'tarifa' : 'tasa';
                      if ($vqueim->calculo == "tarifa") {
                          $importeExplode = explode("$",$vqueim->importe);
                          $importe_imp = $importeExplode[1];
                      } else {
                          $importeExplode = explode("%",$vqueim->importe);
                          $importe_imp = $importeExplode[0];
                      }
                      $arrayforeach = array(
                          "token_cat_impuestos" => $vqueim->token_cat_impuestos,
                          "fecha_registro" => date('d-m-Y H:i:s',$vqueim->fecha_registro),
                          "folio_impuesto" => 'IMP-'.$JwtAuth->generarFolio($vqueim->folio_impuesto),
                          "alias" => $JwtAuth->desencriptar($vqueim->alias),
                          "ret_tras" => $retTras,
                          "calculo" => $imp_calculo,
                          "importe" => $importe_imp,
                          "txtimporte" => $vqueim->importe,
                          //"vinculacion" => false,
                      );
                      $arrayImpuestos[] = $arrayforeach;
                    }

                
                    $arrayForeachVig = array(
                        "token_cat_productos" => $value->token_cat_productos,
                        "folio_prod" => $folio_prod,
                        "producto" => $JwtAuth->desencriptar($value->producto),
                        "precio_simple" => $value->costo_aplicable,
                        "precio_completo" => "$".number_format($value->costo_aplicable,$value->decimales,'.', ','),
                        //"medida_salida"
                        "unidad_medida_token" => $value->token_unidad_medida,
                        "unidad_medida_nombre" => $value->unidad_medida." ".$value->sat_clave,
                        "unidad_medida_representa" => $value->representa,
                        //"moneda_aplicable"
                        "moneda_aplicable_token" => $value->token_monedas,
                        "moneda_aplicable_codigo" => $value->codigo,
                        "moneda_aplicable_moneda" => $value->moneda,
                        "utilizado" => $value->utilizado == TRUE ? true : false,
                        "autorizado" => $value->authorized == TRUE ? true : false,
                        "autorizado_fecha" => $value->authorized == TRUE ? date('d-m-Y H:i:s', $value->authorized_fecha) : null,
                        "autorizado_by" => $value->authorized == TRUE ? $autorizado_por : null,//authorized_by
                        //"imagen" => $JwtAuth->encriptaBase64(Storage::path('public/settings/default_prod.jpg')),
                        "vinculacion_imp_prod" => $arrayImpuestos,
                    );
                    $arrayProductosVig[] = $arrayForeachVig; 
                }
                $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "datosProducto" => $arrayProductosVig,
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

    public function productoActualizar(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
      
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
                "token_cat_impuestos" => "required|string", 
                'concepto' => 'required|string', 
                'precio' => 'required|numeric',
                'unidad_medida' => 'required|string',
                'moneda_token' => 'required|string', 
                'claves_internas' => 'array',
                'impuestos' => 'array', 
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
                $token_cat_impuestos = $parametrosArray["token_cat_impuestos"];
                $concepto = $parametrosArray["concepto"]; 
                $precio = $parametrosArray["precio"]; 
                $unidad_medida = $parametrosArray["unidad_medida"]; 
                $moneda_token = $parametrosArray["moneda_token"]; 
                $claves_internas = $parametrosArray["claves_internas"]; 
                $impuestos = $parametrosArray["impuestos"];

                if (isset($concepto) && !empty($concepto) && isset($precio) && !empty($precio) && isset($unidad_medida) && !empty($unidad_medida) && isset($moneda_token) && !empty($moneda_token)) {
                    //return response()->json(["message" => "prueba25","code" => 200,"status" => "error"]);
                    $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,pers.id AS userr,emp.zona_horaria,people.paterno,
                        people.materno,people.nombre,people.denominacion_rs,people.sitio_web FROM main_empresas AS emp  
                        JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.persona = people.id 
                        AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id 
                        AND users.usuario_token= ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                    //echo $selectEmp[0]->id;

                    $conceptoProd = $JwtAuth->encriptar(strtolower($concepto));
                    $unidadMSalidaDB = DB::select("SELECT id FROM teci_unidad_medida WHERE token_unidad_medida = ?",[$unidad_medida]);
                    $monedaSalidaDB = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_token]);
                    
                    $ubicaProducto = DB::select("SELECT catprod.id FROM in_egr_catalogo_productos AS catprod
                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users 
                        WHERE catprod.producto = ? AND catprod.admin_empresa = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token = ?",
                    [$conceptoProd,$usuario->empresa_token,$usuario->user_token]);
                    if (count($ubicaProducto) == 0) {
                        $tokenCatProd = $JwtAuth->encriptarToken($conceptoProd.$precio.$unidadMSalidaDB[0]->id);    
                        $newProd = new ProductosModelo();
                        $newProd->fecha_registro_prod = $fecha_sistema;
                        $newProd->token_cat_productos = $tokenCatProd;
                        $newProd->temps_folio = $folio_temporal;
                        $newProd->authorized = FALSE;
                        $newProd->modulo_mostrador = TRUE;
                        $newProd->producto = $conceptoProd;
                        $newProd->medida_salida = $unidadMSalidaDB[0]->id;
                        $newProd->costo_aplicable = $precio;
                        $newProd->moneda_aplicable = $monedaSalidaDB[0]->id;
                        $newProd->tipo_prod = 'pr';
                        $newProd->activo = NULL;
                        $newProd->proceso = FALSE;
                        $newProd->utilizado = FALSE;
                        $newProd->fecha_delete_prod = '';
                        $newProd->status = TRUE;
                        $newProd->admin_empresa = $selectEmp[0]->id;
                        $newProd->admin_user_registra = $selectEmp[0]->userr;
                        $savednewProd = $newProd->save();
                    
                        if ($savednewProd) {
                            $obtenProducto = DB::select("SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?",[$tokenCatProd]);
                            if (count($claves_internas) > 0) {
                                for ($i=0; $i < count($claves_internas); $i++) { 
                                    $clave_name = $claves_internas[$i]['clave_name'];
                                    $valor_name = $claves_internas[$i]['valor_name'];
                                    $tokenClabeProdProv = $JwtAuth->encriptarToken(time(),$clave_name,$valor_name); 
                                    $insertProd = DB::table('in_egr_catalogo_productos_claves_internas') 
                                    ->insert(array(
                                        "token_alta_clave" => $tokenClabeProdProv,  
                                        "producto_alta" => $obtenProducto[0]->id,
                                        "clave_nombre" => $clave_name,
                                        "clave_valor" => $valor_name,    
                                    ));
                                }
                            }
                            if (count($impuestos) > 0) {
                                for ($i=0; $i < count($impuestos); $i++) { 
                                    $impuesto_vinculado = DB::select("SELECT id FROM cont_impuestos_catalogo WHERE token_cat_impuestos = ?",[$impuestos[$i]['impuesto_vinculado']]);
                                    $tokenImpArt = $JwtAuth->encriptarToken(time(),$obtenProducto[0]->id,$impuesto_vinculado[0]->id); 
                                    $insertImpArt = DB::table('in_egr_impuestos_articulos') 
                                    ->insert(array(
                                        "token_impuestos_articulos" => $tokenImpArt,
                                        "producto_rel" => $obtenProducto[0]->id,
                                        "impuestos" => $impuesto_vinculado[0]->id,    
                                    ));
                                }
                            }
                            $JwtAuth->insertBitacoraActividad('egresos','catalogos','productos',$folio_prod_temp,'registro en el catalogo de productos',$usuario->empresa_token,$usuario->user_token);
                
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Este producto ha sido registrado satisfactoriamente con el folio '.$folio_prod_temp
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'La información de este producto no es valida'
                            );
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Este producto ya ha sido registrado anteriormente, intente nuevamente o comuniquese a soporte'
                        );
                    }
                    
                } else {
                    $error_alerta = "";
                    if (!isset($concepto) || empty($concepto)){$error_alerta = "error al ingresar concepto del producto, verifique su información o comuniquese a soporte para más información";}
                    if (!isset($precio) || empty($precio)){$error_alerta = "error al ingresar precio de producto, verifique su información o comuniquese a soporte para más información";}
                    if (!isset($unidad_medida) || empty($unidad_medida)){$error_alerta = "error al ingresar unidad de medida, verifique su información o comuniquese a soporte para más información";}
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 404,
                        'message' => $error_alerta
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

    public function productoPapeleraSave(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
      
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
                "token_cat_productos" => "required|string"
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
                $token_cat_productos = $parametrosArray["token_cat_productos"];
              
                if (isset($token_cat_productos) && !empty($token_cat_productos)) {
                    $queryProducto = ProductosModelo::join('main_empresas AS emp','in_egr_catalogo_productos.admin_empresa','emp.id')
                    ->where(['in_egr_catalogo_productos.token_cat_productos' => $token_cat_productos,'in_egr_catalogo_productos.status' => TRUE,'in_egr_catalogo_productos.modulo_mostrador' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token])->get();
                
                    foreach ($queryProducto as $quepd) {
                      $folio_prod = $quepd->folio_sistema != NULL && $quepd->folio_sistema != "" ? ('PROD-'.($quepd->post_folio != NULL ? $JwtAuth->generarFolio($quepd->folio_sistema):$JwtAuth->generarFolio($quepd->folio_sistema).'-'.$quepd->post_folio)):
                      'PROD-TEMP-'.$JwtAuth->generarFolio($quepd->temps_folio);
                        $ProdDelete = ProductosModelo::find(1);
                        $ProdDelete->where("token_cat_productos", $quepd->token_cat_productos)->update(["status" => FALSE,"fecha_delete_prod" => time()]);
    
                        if ($ProdDelete) {
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "message" => "El producto con folio ".$folio_prod." ha sido eliminado satisfactoriamente"
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Error en eliminacion de producto, por favor verifique su información o comuniquese a soporte"
                            );
                        }
                    }
                    
                } else {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Error en producto registrado, por favor verifique su información o comuniquese a soporte"
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

    public function productoAssocCatalogoEliminados(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
        $arrayProductos = array();
        
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
                $prodList = DB::table("in_egr_catalogo_productos AS catprod") 
                ->join("teci_unidad_medida AS umed","catprod.medida_salida","=","umed.id")
                ->join("teci_catalogo_monedas AS money","catprod.moneda_aplicable","=","money.id")
                ->join("main_empresas AS emp","catprod.admin_empresa","=","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                ->join("vhum_personal AS pers","empuser.personal","=","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                ->where([
                    'catprod.modulo_mostrador' => TRUE,
                    'catprod.status' => FALSE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();
                foreach ($prodList as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    //QRCode::text($value->token_cat_productos)->setOutfile(Storage::path('public/root/'.$value->fecha_registro_prod.'QRCode.png'))->png();    
                    $folio_prod = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('PROD-'.($value->post_folio != NULL ? $JwtAuth->generarFolio($value->folio_sistema):$JwtAuth->generarFolio($value->folio_sistema).'-'.$value->post_folio)):
                        'PROD-TEMP-'.$JwtAuth->generarFolio($value->temps_folio);

                    $autorizado_por = "";
                    if ($value->authorized == TRUE) {
                        $prodAuthQuery = DB::table("in_egr_catalogo_productos AS catprod") 
                        ->join("vhum_personal AS authPers","catprod.authorized_by","=","authPers.id")
                        ->join("sos_personas AS authPeople","authPers.personal","=","authPeople.id")
                        ->where(["catprod.token_cat_productos" => $value->token_cat_productos])->get();
                        $autorizado_por = $JwtAuth->desencriptarNombres($prodAuthQuery[0]->paterno,$prodAuthQuery[0]->materno,$prodAuthQuery[0]->nombre);
                    }
                    $arrayImpuestos = array();
                    $impuestoQuery = DB::table("in_egr_catalogo_productos AS catprod") 
                    ->join("in_egr_impuestos_articulos AS impart","catprod.id","=","impart.producto_rel")
                    ->join("cont_impuestos_catalogo AS catimp","impart.impuestos","=","catimp.id")
                    ->where(['catprod.token_cat_productos' => $value->token_cat_productos])->get();

                    foreach ($impuestoQuery as $vqueim) {
                      $retTras = $vqueim->ret_tras == "rete" ? 'retenido' : 'trasladado';
                      $imp_calculo = $vqueim->calculo == "tarifa" ? 'tarifa' : 'tasa';
                      if ($vqueim->calculo == "tarifa") {
                          $importeExplode = explode("$",$vqueim->importe);
                          $importe_imp = $importeExplode[1];
                      } else {
                          $importeExplode = explode("%",$vqueim->importe);
                          $importe_imp = $importeExplode[0];
                      }
                      $arrayforeach = array(
                          "token_cat_impuestos" => $vqueim->token_cat_impuestos,
                          "fecha_registro" => date('d-m-Y H:i:s',$vqueim->fecha_registro),
                          "folio_impuesto" => 'IMP-'.$JwtAuth->generarFolio($vqueim->folio_impuesto),
                          "alias" => $JwtAuth->desencriptar($vqueim->alias),
                          "ret_tras" => $retTras,
                          "calculo" => $imp_calculo,
                          "importe" => $importe_imp,
                          "txtimporte" => $vqueim->importe,
                          //"vinculacion" => false,
                      );
                      $arrayImpuestos[] = $arrayforeach;
                    }

                  
                    $arrayForeachVig = array(
                        "token_cat_productos" => $value->token_cat_productos,
                        "folio_prod" => $folio_prod,
                        "producto" => $JwtAuth->desencriptar($value->producto),
                        "precio_simple" => $value->costo_aplicable,
                        "precio_completo" => "$".number_format($value->costo_aplicable,$value->decimales,'.', ','),
                        //"medida_salida"
                        "unidad_medida_token" => $value->token_unidad_medida,
                        "unidad_medida_nombre" => $value->unidad_medida." ".$value->sat_clave,
                        "unidad_medida_representa" => $value->representa,
                        //"moneda_aplicable"
                        "moneda_aplicable_token" => $value->token_monedas,
                        "moneda_aplicable_codigo" => $value->codigo,
                        "moneda_aplicable_moneda" => $value->moneda,
                        "utilizado" => $value->utilizado == TRUE ? true : false,
                        "autorizado" => $value->authorized == TRUE ? true : false,
                        "autorizado_fecha" => $value->authorized == TRUE ? date('d-m-Y H:i:s', $value->authorized_fecha) : null,
                        "autorizado_by" => $value->authorized == TRUE ? $autorizado_por : null,//authorized_by
                        //"imagen" => $JwtAuth->encriptaBase64(Storage::path('public/settings/default_prod.jpg')),
                        "vinculacion_imp_prod" => $arrayImpuestos,
                        "fecha_delete" => date('d-m-Y H:i:s',$value->fecha_delete_prod)
                    );
                    $arrayProductos[] = $arrayForeachVig; 
                }
                $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "datosProducto" => $arrayProductos,
                );
            }
        } else {
            $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Los informacion que intenta registrar no es valida'
            );
        }
        return response()->json($dataMensaje, $dataMensaje["code"]);
    }

    public function productoPapeleraRestaurar(Request $request){
      $JwtAuth = new \JwtAuth();
      $json_data = $request->input('json');
      $parametros = json_decode($json_data);
      $parametrosArray = json_decode($json_data,true);
    
      if (!empty($parametros) && !empty($parametrosArray)) {
          $validate = \Validator::make($parametrosArray,[
              "user_token" => "required|string",  
              "token_cat_productos" => "required|string"
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
              $token_cat_productos = $parametrosArray["token_cat_productos"];
            
              if (isset($token_cat_productos) && !empty($token_cat_productos)) {
                  $queryProducto = ProductosModelo::join('main_empresas AS emp','in_egr_catalogo_productos.admin_empresa','emp.id')
                  ->where(['in_egr_catalogo_productos.token_cat_productos' => $token_cat_productos,'in_egr_catalogo_productos.status' => FALSE,'in_egr_catalogo_productos.modulo_mostrador' => TRUE,
                  'emp.empresa_token' => $usuario->empresa_token])->get();
              
                  foreach ($queryProducto as $quepd) {
                    $folio_prod = $quepd->folio_sistema != NULL && $quepd->folio_sistema != "" ? ('PROD-'.($quepd->post_folio != NULL ? $JwtAuth->generarFolio($quepd->folio_sistema):$JwtAuth->generarFolio($quepd->folio_sistema).'-'.$quepd->post_folio)):
                    'PROD-TEMP-'.$JwtAuth->generarFolio($quepd->temps_folio);
                      $prodRestaurar = ProductosModelo::find(1);
                      $prodRestaurar->where("token_cat_productos", $quepd->token_cat_productos)->update(["status" => TRUE,"fecha_delete_prod" => NULL]);
  
                      if ($prodRestaurar) {
                          $dataMensaje = array(
                              "status" => "success",
                              "code" => 200,
                              "message" => "El producto con folio ".$folio_prod." ha sido restaurado satisfactoriamente"
                          );
                      } else {
                          $dataMensaje = array(
                              "status" => "error",
                              "code" => 200,
                              "message" => "Error en restauracion de producto, por favor verifique su información o comuniquese a soporte"
                          );
                      }
                  }
                  
              } else {
                  $dataMensaje = array(
                      "status" => "error",
                      "code" => 200,
                      "message" => "Error en producto registrado, por favor verifique su información o comuniquese a soporte"
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

    public function productoDeletePerm(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
      
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
                "token_cat_productos" => "required|string"
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
                $token_cat_productos = $parametrosArray["token_cat_productos"];

                if (isset($token_cat_productos) && !empty($token_cat_productos)) {
                    $queryProducto = ProductosModelo::join('main_empresas AS emp','in_egr_catalogo_productos.admin_empresa','emp.id')
                    ->where(['in_egr_catalogo_productos.token_cat_productos' => $token_cat_productos,'in_egr_catalogo_productos.status' => FALSE,'in_egr_catalogo_productos.modulo_mostrador' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token])->get();
                
                    foreach ($queryProducto as $quepd) {
                        $folio_prod = $quepd->folio_sistema != NULL && $quepd->folio_sistema != "" ? ('PROD-'.($quepd->post_folio != NULL ? $JwtAuth->generarFolio($quepd->folio_sistema):$JwtAuth->generarFolio($quepd->folio_sistema).'-'.$quepd->post_folio)):
                        'PROD-TEMP-'.$JwtAuth->generarFolio($quepd->temps_folio);

                        $deleteClaves = DB::table("in_egr_catalogo_productos_claves_internas AS klave")
                        ->join("in_egr_catalogo_productos AS catprod","klave.producto_alta","=","catprod.id")
                        ->where(["catprod.token_cat_productos" => $quepd->token_cat_productos])->limit(1)->delete();

                        $deleteImpRel = DB::table("in_egr_impuestos_articulos AS impArt")
                        ->join("in_egr_catalogo_productos AS catprod","impArt.producto_rel","=","catprod.id")
                        ->where(["catprod.token_cat_productos" => $quepd->token_cat_productos])->limit(1)->delete();
                        $prodRestaurar = ProductosModelo::find(1)->where("token_cat_productos", $quepd->token_cat_productos)->delete();
                    
                        if ($prodRestaurar) {
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "message" => "El producto con folio ".$folio_prod." ha sido eliminado satisfactoriamente"
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Error en eliminacion de producto, por favor verifique su información o comuniquese a soporte"
                            );
                        }
                    }

                } else {
                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => "Error en producto registrado, por favor verifique su información o comuniquese a soporte"
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

    public function registroProductoAssoc(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'concepto' => 'required|string', 
                'precio' => 'required|numeric',
                'unidad_medida' => 'required|string',
                'moneda_token' => 'required|string', 
                'claves_internas' => 'array',
                'impuestos' => 'array', 
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
                $concepto = $parametrosArray["concepto"]; 
                $precio = $parametrosArray["precio"]; 
                $unidad_medida = $parametrosArray["unidad_medida"]; 
                $moneda_token = $parametrosArray["moneda_token"]; 
                $claves_internas = $parametrosArray["claves_internas"]; 
                $impuestos = $parametrosArray["impuestos"];

                if (isset($concepto) && !empty($concepto) && isset($precio) && !empty($precio) && isset($unidad_medida) && !empty($unidad_medida) && isset($moneda_token) && !empty($moneda_token)) {
                    //return response()->json(["message" => "prueba25","code" => 200,"status" => "error"]);
                    $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,pers.id AS userr,emp.zona_horaria,people.paterno,
                        people.materno,people.nombre,people.denominacion_rs,people.sitio_web FROM main_empresas AS emp  
                        JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.persona = people.id 
                        AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id 
                        AND users.usuario_token= ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                    //echo $selectEmp[0]->id;

                    $folioSistemaTemp = DB::select("SELECT temps_folio FROM in_egr_catalogo_productos WHERE temps_folio IS NOT NULL AND admin_empresa = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$usuario->empresa_token]);
                    if (count($folioSistemaTemp) > 0) {
                        $queryFolioTmpPrv = DB::select("SELECT temps_folio+1 AS temps_folio FROM in_egr_catalogo_productos 
                            WHERE id = (SELECT Max(catproD.id) FROM in_egr_catalogo_productos AS catproD 
                            JOIN main_empresas AS emp WHERE temps_folio IS NOT NULL AND catproD.admin_empresa = emp.id 
                            AND emp.empresa_token = ?)", [$usuario->empresa_token]);
                        
                        foreach ($queryFolioTmpPrv as $vTemp) {
                            $folio_temporal = $vTemp->temps_folio;
                        }
                    } else {
                        $folio_temporal = 1;
                    }
    
                    $folio_prod_temp = 'PROD-TEMP-'.$JwtAuth->generarFolio($folio_temporal);
                    
                    $conceptoProd = $JwtAuth->encriptar(strtolower($concepto));
                    $unidadMSalidaDB = DB::select("SELECT id FROM teci_unidad_medida WHERE token_unidad_medida = ?",[$unidad_medida]);
                    $monedaSalidaDB = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_token]);
                    $ubicaProducto = DB::select("SELECT catprod.id FROM in_egr_catalogo_productos AS catprod
                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users 
                        WHERE catprod.producto = ? AND catprod.admin_empresa = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token = ?",
                    [$conceptoProd,$usuario->empresa_token,$usuario->user_token]);
                    if (count($ubicaProducto) == 0) {
                        $tokenCatProd = $JwtAuth->encriptarToken($conceptoProd.$precio.$unidadMSalidaDB[0]->id);    
                        $newProd = new ProductosModelo();
                        $newProd->fecha_registro_prod = $fecha_sistema;
                        $newProd->token_cat_productos = $tokenCatProd;
                        $newProd->temps_folio = $folio_temporal;
                        $newProd->authorized = FALSE;
                        $newProd->modulo_mostrador = TRUE;
                        $newProd->producto = $conceptoProd;
                        $newProd->medida_salida = $unidadMSalidaDB[0]->id;
                        $newProd->costo_aplicable = $precio;
                        $newProd->moneda_aplicable = $monedaSalidaDB[0]->id;
                        $newProd->tipo_prod = 'pr';
                        $newProd->activo = NULL;
                        $newProd->proceso = FALSE;
                        $newProd->utilizado = FALSE;
                        $newProd->fecha_delete_prod = '';
                        $newProd->status = TRUE;
                        $newProd->admin_empresa = $selectEmp[0]->id;
                        $newProd->admin_user_registra = $selectEmp[0]->userr;
                        $savednewProd = $newProd->save();
                    
                        if ($savednewProd) {
                            $obtenProducto = DB::select("SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?",[$tokenCatProd]);
                            if (count($claves_internas) > 0) {
                                for ($i=0; $i < count($claves_internas); $i++) { 
                                    $clave_name = $claves_internas[$i]['clave_name'];
                                    $valor_name = $claves_internas[$i]['valor_name'];
                                    $tokenClabeProdProv = $JwtAuth->encriptarToken(time(),$clave_name,$valor_name); 
                                    $insertProd = DB::table('in_egr_catalogo_productos_claves_internas') 
                                    ->insert(array(
                                        "token_alta_clave" => $tokenClabeProdProv,  
                                        "producto_alta" => $obtenProducto[0]->id,
                                        "clave_nombre" => $clave_name,
                                        "clave_valor" => $valor_name,    
                                    ));
                                }
                            }
                            if (count($impuestos) > 0) {
                                for ($i=0; $i < count($impuestos); $i++) { 
                                    $impuesto_vinculado = DB::select("SELECT id FROM cont_impuestos_catalogo WHERE token_cat_impuestos = ?",[$impuestos[$i]['impuesto_vinculado']]);
                                    $tokenImpArt = $JwtAuth->encriptarToken(time(),$obtenProducto[0]->id,$impuesto_vinculado[0]->id); 
                                    $insertImpArt = DB::table('in_egr_impuestos_articulos') 
                                    ->insert(array(
                                        "token_impuestos_articulos" => $tokenImpArt,
                                        "producto_rel" => $obtenProducto[0]->id,
                                        "impuestos" => $impuesto_vinculado[0]->id,    
                                    ));
                                }
                            }
                            $JwtAuth->insertBitacoraActividad('egresos','catalogos','productos',$folio_prod_temp,'registro en el catalogo de productos',$usuario->empresa_token,$usuario->user_token);
                
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Este producto ha sido registrado satisfactoriamente con el folio '.$folio_prod_temp
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'La información de este producto no es valida'
                            );
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Este producto ya ha sido registrado anteriormente, intente nuevamente o comuniquese a soporte'
                        );
                    }
                    
                } else {
                    $error_alerta = "";
                    if (!isset($concepto) || empty($concepto)){$error_alerta = "error al ingresar concepto del producto, verifique su información o comuniquese a soporte para más información";}
                    if (!isset($precio) || empty($precio)){$error_alerta = "error al ingresar precio de producto, verifique su información o comuniquese a soporte para más información";}
                    if (!isset($unidad_medida) || empty($unidad_medida)){$error_alerta = "error al ingresar unidad de medida, verifique su información o comuniquese a soporte para más información";}
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 404,
                        'message' => $error_alerta
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
    
//servicios
    public function servicioAssocCatalogo(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        $arrayProductosVig = array();

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
                $prodList = DB::table("in_egr_catalogo_productos AS catprod") 
                ->join("teci_unidad_medida AS umed","catprod.medida_salida","=","umed.id")
                ->join("teci_catalogo_monedas AS money","catprod.moneda_aplicable","=","money.id")
                ->join("main_empresas AS emp","catprod.admin_empresa","=","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                ->join("vhum_personal AS pers","empuser.personal","=","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                ->where([
                    'catprod.modulo_mostrador' => TRUE,
                    'catprod.status' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token,
                    'users.usuario_token' => $usuario->user_token,
                ])->get();
                foreach ($prodList as $value) {
                    //da_te_default_timezone_set($value->zona_horaria);
                    //QRCode::text($value->token_cat_productos)->setOutfile(Storage::path('public/root/'.$value->fecha_registro_prod.'QRCode.png'))->png();    
                    $folio_prod = $value->folio_sistema != NULL && $value->folio_sistema != "" ? ('PROD-'.($value->post_folio != NULL ? $JwtAuth->generarFolio($value->folio_sistema):$JwtAuth->generarFolio($value->folio_sistema).'-'.$value->post_folio)):
                        'PROD-TEMP-'.$JwtAuth->generarFolio($value->temps_folio);

                    $autorizado_por = "";
                    if ($value->authorized == TRUE) {
                        $prodAuthQuery = DB::table("in_egr_catalogo_productos AS catprod") 
                        ->join("vhum_personal AS authPers","catprod.authorized_by","=","authPers.id")
                        ->join("sos_personas AS authPeople","authPers.personal","=","authPeople.id")
                        ->where(["catprod.token_cat_productos" => $value->token_cat_productos])->get();
                        $autorizado_por = $JwtAuth->desencriptarNombres($prodAuthQuery[0]->paterno,$prodAuthQuery[0]->materno,$prodAuthQuery[0]->nombre);
                    }
                    
                    $arrayForeachVig = array(
                        "token_cat_productos" => $value->token_cat_productos,
                        "folio_prod" => $folio_prod,
                        "producto" => $JwtAuth->desencriptar($value->producto),
                        "precio_simple" => $value->costo_aplicable,
                        "precio_completo" => "$".number_format($value->costo_aplicable,$value->decimales,'.', ','),
                        //"medida_salida"
                        "unidad_medida_token" => $value->token_unidad_medida,
                        "unidad_medida_nombre" => $value->unidad_medida." ".$value->sat_clave,
                        "unidad_medida_representa" => $value->representa,
                        //"moneda_aplicable"
                        "moneda_aplicable_token" => $value->token_monedas,
                        "moneda_aplicable_codigo" => $value->codigo,
                        "moneda_aplicable_moneda" => $value->moneda,
                        "utilizado" => $value->utilizado == TRUE ? true : false,
                        "autorizado" => $value->authorized == TRUE ? true : false,
                        "autorizado_fecha" => $value->authorized == TRUE ? date('d-m-Y H:i:s', $value->authorized_fecha) : null,
                        "autorizado_by" => $value->authorized == TRUE ? $autorizado_por : null,//authorized_by
                        //"imagen" => $JwtAuth->encriptaBase64(Storage::path('public/settings/default_prod.jpg')),
                    );
                    $arrayProductosVig[] = $arrayForeachVig; 
                }
                $dataMensaje = array(
                    "status" => "success",
                    "code" => 200,
                    "datosProducto" => $arrayProductosVig,
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

	public function requestValidacionServ(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayProveedores = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"token_producto" => "required|string",
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
				$token_producto = $parametrosArray["token_producto"];
				$observaciones = "permiso de prueba";

                $queryProducto = DB::table("in_egr_catalogo_productos AS catprod") 
                ->join("teci_unidad_medida AS umed","catprod.medida_salida","=","umed.id")
                ->join("teci_catalogo_monedas AS money","catprod.moneda_aplicable","=","money.id")
                ->join("main_empresas AS emp","catprod.admin_empresa","=","emp.id")
                ->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
                ->join("vhum_personal AS pers","empuser.personal","=","pers.id")
                ->join("teci_usuarios_catalogo AS users","pers.usuario","=","users.id")
                ->where([
                    "catprod.modulo_mostrador" => TRUE,
                    "catprod.token_cat_productos" => $token_producto,
                    "catprod.status" => TRUE,
                    "emp.empresa_token" => $usuario->empresa_token,
                    "users.usuario_token" => $usuario->user_token,
                ])->get();

				if (count($queryProducto) == 1) {
					foreach ($queryProducto as $vProd) {
						//da_te_default_timezone_set($vProd->zona_horaria);
						$folio_prod = 'PROD-TEMP-'.$JwtAuth->generarFolio($vProd->temps_folio);
                        $nombre_prod = strtolower($JwtAuth->desencriptar($vProd->producto));

						$select_id_prod = DB::select("SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?",[$vProd->token_cat_productos]);

						$select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario->empresa_token]);

						$select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                            JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.personal AND pers.id = users.empleado 
                            AND users.usuario_token = ?", [$usuario->user_token]);

						$nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);
						$folioSistema = DB::select("SELECT max(soli_auth.folio_productos_soli_auth) AS folio_permiso FROM in_egr_catalogo_productos_soli_auth AS soli_auth 
                            JOIN main_empresas AS emp WHERE soli_auth.user_emp = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

						if (count($folioSistema) == 0) {
							$sql_folio = 1;
						} else {
							$sql_folio = end($folioSistema)->folio_permiso + 1;
						}

						$token_auth = $JwtAuth->encriptarToken(time(), end($select_empresa)->id . end($select_usuario)->id . $observaciones . time() - 500);

						$insertSoliPerm = DB::table("in_egr_catalogo_productos_soli_auth")
							->insert(
								array(
									"token_productos_soli_auth" => $token_auth,
									"folio_productos_soli_auth" => $sql_folio,
									"fecha_productos_soli_auth" => time(),
									"user_emp" => end($select_empresa)->id,
									"user_user" => end($select_usuario)->id,
									"producto" => end($select_id_prod)->id,
									"observaciones" => $JwtAuth->encriptar($observaciones),
									"receptor" => 3,
									"solicitud_prov_status" => TRUE,
								)
							);

						if ($insertSoliPerm) {
							$fireReceptor = DB::select("SELECT token_dispositivo_movil,token_dispositivo_web FROM teci_usuarios_catalogo 
                                WHERE user_token = ?", ["ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY"]);
							$titulo_ = "Validación de proveedor";
							$mensaje_user = "El usuario " . $nombre_user . " de la empresa " . end($select_empresa)->abrev_nombre . " ha solicitado validación para el producto con el folio ".$folio_prod." " . $nombre_prod;

							if (end($fireReceptor)->token_dispositivo_movil != null && end($fireReceptor)->token_dispositivo_movil != "") {
								$JwtAuth->notificacionPushDevices(end($fireReceptor)->token_dispositivo_movil, $titulo_, $mensaje_user);
							}

							if (end($fireReceptor)->token_dispositivo_web != null && end($fireReceptor)->token_dispositivo_web != "") {
								$JwtAuth->notificacionPushDevices(end($fireReceptor)->token_dispositivo_web, $titulo_, $mensaje_user);
							}

							$dataMensaje = array(
								"status" => "success",
								"code" => 200,
								"message" => "Solicitud de permiso generada con el folio PERM-" . $JwtAuth->generarFolio($sql_folio),
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
						'status' => 'error',
						'code' => 200,
						'message' => 'el proveedor buscado no existe'
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

    public function servicioActualizar(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
      
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
                "token_cat_impuestos" => "required|string",
                "impuesto_alias" => "required|string",
                "impuesto_tipo" => "required|string",
                "impuesto_tasa_tarifa" => "required|string",
                "impuesto_importe" => "required|string"
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
                $token_cat_impuestos = $parametrosArray["token_cat_impuestos"];
                $impuesto_alias = $parametrosArray["impuesto_alias"];
                $impuesto_tipo = $parametrosArray["impuesto_tipo"];
                $impuesto_tasa_tarifa = $parametrosArray["impuesto_tasa_tarifa"];
                $impuesto_importe = $parametrosArray["impuesto_importe"];
              
                if (isset($token_cat_impuestos) && !empty($token_cat_impuestos) && isset($impuesto_alias) && !empty($impuesto_alias) && preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_alias) &&
                    isset($impuesto_tipo) && !empty($impuesto_tipo) && preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_tipo) &&
                    isset($impuesto_tasa_tarifa) && !empty($impuesto_tasa_tarifa) && preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_tasa_tarifa) &&
                    isset($impuesto_importe) && !empty($impuesto_importe)) {

                    if ($impuesto_tasa_tarifa == "tasa") {
                        if (!preg_match($JwtAuth->filtroPorcentaje(),$impuesto_importe)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte"
                            );
                        }
                    } else {
                        if (!preg_match($JwtAuth->filtroCostoPrecio(),$impuesto_importe)) {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte"
                            );
                        }
                    }

                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                    $queryImp = ImpuestosModelo::join('main_empresas AS emp','cont_impuestos_catalogo.empresa','emp.id')
                    ->where(['cont_impuestos_catalogo.token_cat_impuestos' => $token_cat_impuestos,'cont_impuestos_catalogo.imp_status' => TRUE,'cont_impuestos_catalogo.assoc' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token])->get();
                
                    foreach ($queryImp as $vImp) {
                        $sql_ret_tras = $impuesto_tipo == "retenido" ? "rete" : "tras";
                        $imp_folio = "IMP-".$JwtAuth->generarFolio($vImp->folio_impuesto);
                        $impUpdate = ImpuestosModelo::find(1);
                        $impUpdate->where("token_cat_impuestos", $vImp->token_cat_impuestos)->update([
                            "alias" => $JwtAuth->encriptar($impuesto_alias), 
                            "ret_tras" => $sql_ret_tras, 
                            "calculo" => $impuesto_tasa_tarifa, 
                            "importe" => $impuesto_importe,
                        ]);
    
                        if ($impUpdate) {
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "message" => "El impuesto con folio ".$imp_folio." ha sido actualizado satisfactoriamente"
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Error en registro de impuesto, por favor verifique su información o comuniquese a soporte"
                            );
                        }
                    }
                    
                } else {
                    $mensaje_error = "";
                    if (!isset($token_cat_impuestos) || empty($token_cat_impuestos)) $mensaje_error = "Error en impuesto registrado, por favor verifique su información o comuniquese a soporte"; 
                    if (!isset($alias) || empty($alias) || !preg_match($JwtAuth->filtroAlfaNumerico(),$alias)) $mensaje_error = "Error en alias de impuesto, por favor verifique su información o comuniquese a soporte"; 
                    if (!isset($impuesto_tipo) || empty($impuesto_tipo) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_tipo)) $mensaje_error = "Error en tipo de impuesto, por favor verifique su información o comuniquese a soporte";  
                    if (!isset($impuesto_tasa_tarifa) || empty($impuesto_tasa_tarifa) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_tasa_tarifa)) $mensaje_error = "Error en tasa o tarifa de impuesto, por favor verifique su información o comuniquese a soporte";  
                    if (!isset($impuesto_importe) || empty($impuesto_importe) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_importe)) $mensaje_error = "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte";  

                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => $mensaje_error
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

    public function servicioPapeleraSave(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
      
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
                "token_cat_impuestos" => "required|string"
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
                $token_cat_impuestos = $parametrosArray["token_cat_impuestos"];
              
                if (isset($token_cat_impuestos) && !empty($token_cat_impuestos)) {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                    $queryImp = ImpuestosModelo::join('main_empresas AS emp','cont_impuestos_catalogo.empresa','emp.id')
                    ->where(['cont_impuestos_catalogo.token_cat_impuestos' => $token_cat_impuestos,'cont_impuestos_catalogo.imp_status' => TRUE,'cont_impuestos_catalogo.assoc' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token])->get();
                
                    foreach ($queryImp as $vImp) {
                        $imp_folio = "IMP-".$JwtAuth->generarFolio($vImp->folio_impuesto);
                        $impDelete = ImpuestosModelo::find(1);
                        $impDelete->where("token_cat_impuestos", $vImp->token_cat_impuestos)->update(["imp_status" => FALSE,"imp_fecha_delete" => time()]);
    
                        if ($impDelete) {
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "message" => "El impuesto con folio ".$imp_folio." ha sido eliminado satisfactoriamente"
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Error en registro de impuesto, por favor verifique su información o comuniquese a soporte"
                            );
                        }
                    }
                    
                } else {
                    $mensaje_error = "";
                    if (!isset($token_cat_impuestos) || empty($token_cat_impuestos)) $mensaje_error = "Error en impuesto registrado, por favor verifique su información o comuniquese a soporte"; 
                    if (!isset($alias) || empty($alias) || !preg_match($JwtAuth->filtroAlfaNumerico(),$alias)) $mensaje_error = "Error en alias de impuesto, por favor verifique su información o comuniquese a soporte"; 
                    if (!isset($impuesto_tipo) || empty($impuesto_tipo) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_tipo)) $mensaje_error = "Error en tipo de impuesto, por favor verifique su información o comuniquese a soporte";  
                    if (!isset($impuesto_tasa_tarifa) || empty($impuesto_tasa_tarifa) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_tasa_tarifa)) $mensaje_error = "Error en tasa o tarifa de impuesto, por favor verifique su información o comuniquese a soporte";  
                    if (!isset($impuesto_importe) || empty($impuesto_importe) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_importe)) $mensaje_error = "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte";  

                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => $mensaje_error
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

    public function servicioAssocCatalogoEliminados(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
        $arrayImpuestos = array();
        
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
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

                $queryImp = ImpuestosModelo::join('main_empresas AS emp','cont_impuestos_catalogo.empresa','emp.id')
                ->where(['cont_impuestos_catalogo.imp_status' => FALSE,'cont_impuestos_catalogo.assoc' => TRUE,
                'emp.empresa_token' => $usuario->empresa_token])->get();

                foreach ($queryImp as $value) {
                    $retTras = $value->ret_tras == "rete" ? 'retenido' : 'trasladado';
                    $imp_calculo = $value->calculo == "tarifa" ? 'tarifa' : 'tasa';
                    if ($value->calculo == "tarifa") {
                        $importeExplode = explode("$",$value->importe);
                        $importe_imp = $importeExplode[1];
                    } else {
                        $importeExplode = explode("%",$value->importe);
                        $importe_imp = $importeExplode[0];
                    }
                    $arrayforeach = array(
                        "token_cat_impuestos" => $value->token_cat_impuestos,
                        "fecha_registro" => date('d-m-Y H:i:s',$value->fecha_registro),
                        "folio_impuesto" => 'IMP-'.$JwtAuth->generarFolio($value->folio_impuesto),
                        "alias" => $JwtAuth->desencriptar($value->alias),
                        "ret_tras" => $retTras,
                        "calculo" => $imp_calculo,
                        "importe" => $importe_imp,
                        "txtimporte" => $value->importe,
                        "fecha_delete" => date('d-m-Y H:i:s',$value->imp_fecha_delete),
                    );
                    $arrayImpuestos[] = $arrayforeach;
                }

                $dataMensaje = array("status" => "success","code" => 200,"impuestos" => $arrayImpuestos);
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

    public function servicioPapeleraRestaurar(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
      
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
                "token_cat_impuestos" => "required|string"
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
                $token_cat_impuestos = $parametrosArray["token_cat_impuestos"];
              
                if (isset($token_cat_impuestos) && !empty($token_cat_impuestos)) {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                    $queryImp = ImpuestosModelo::join('main_empresas AS emp','cont_impuestos_catalogo.empresa','emp.id')
                    ->where(['cont_impuestos_catalogo.token_cat_impuestos' => $token_cat_impuestos,'cont_impuestos_catalogo.imp_status' => FALSE,'cont_impuestos_catalogo.assoc' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token])->get();
                
                    foreach ($queryImp as $vImp) {
                        $imp_folio = "IMP-".$JwtAuth->generarFolio($vImp->folio_impuesto);
                        $impDelete = ImpuestosModelo::find(1);
                        $impDelete->where("token_cat_impuestos", $vImp->token_cat_impuestos)->update(["imp_status" => TRUE,"imp_fecha_delete" => NULL]);
    
                        if ($impDelete) {
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "message" => "El impuesto con folio ".$imp_folio." ha sido restaurado satisfactoriamente"
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Error en registro de impuesto, por favor verifique su información o comuniquese a soporte"
                            );
                        }
                    }
                    
                } else {
                    $mensaje_error = "";
                    if (!isset($token_cat_impuestos) || empty($token_cat_impuestos)) $mensaje_error = "Error en impuesto registrado, por favor verifique su información o comuniquese a soporte"; 
                    if (!isset($alias) || empty($alias) || !preg_match($JwtAuth->filtroAlfaNumerico(),$alias)) $mensaje_error = "Error en alias de impuesto, por favor verifique su información o comuniquese a soporte"; 
                    if (!isset($impuesto_tipo) || empty($impuesto_tipo) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_tipo)) $mensaje_error = "Error en tipo de impuesto, por favor verifique su información o comuniquese a soporte";  
                    if (!isset($impuesto_tasa_tarifa) || empty($impuesto_tasa_tarifa) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_tasa_tarifa)) $mensaje_error = "Error en tasa o tarifa de impuesto, por favor verifique su información o comuniquese a soporte";  
                    if (!isset($impuesto_importe) || empty($impuesto_importe) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_importe)) $mensaje_error = "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte";  

                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => $mensaje_error
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

    public function servicioDeletePerm(Request $request){
        $JwtAuth = new \JwtAuth();
        $json_data = $request->input('json');
        $parametros = json_decode($json_data);
        $parametrosArray = json_decode($json_data,true);
      
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                "user_token" => "required|string",  
                "token_cat_impuestos" => "required|string"
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
                $token_cat_impuestos = $parametrosArray["token_cat_impuestos"];
              
                if (isset($token_cat_impuestos) && !empty($token_cat_impuestos)) {
                    $usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true);

                    $queryImp = ImpuestosModelo::join('main_empresas AS emp','cont_impuestos_catalogo.empresa','emp.id')
                    ->where(['cont_impuestos_catalogo.token_cat_impuestos' => $token_cat_impuestos,'cont_impuestos_catalogo.imp_status' => FALSE,'cont_impuestos_catalogo.assoc' => TRUE,
                    'emp.empresa_token' => $usuario->empresa_token])->get();
                
                    foreach ($queryImp as $vImp) {
                        $imp_folio = "IMP-".$JwtAuth->generarFolio($vImp->folio_impuesto);
                        $impDelete = ImpuestosModelo::find(1);
                        $impDelete->where("token_cat_impuestos", $vImp->token_cat_impuestos)->delete();
    
                        if ($impDelete) {
                            $dataMensaje = array(
                                "status" => "success",
                                "code" => 200,
                                "message" => "El impuesto con folio ".$imp_folio." ha sido eliminado satisfactoriamente"
                            );
                        } else {
                            $dataMensaje = array(
                                "status" => "error",
                                "code" => 200,
                                "message" => "Error en registro de impuesto, por favor verifique su información o comuniquese a soporte"
                            );
                        }
                    }
                    
                } else {
                    $mensaje_error = "";
                    if (!isset($token_cat_impuestos) || empty($token_cat_impuestos)) $mensaje_error = "Error en impuesto registrado, por favor verifique su información o comuniquese a soporte"; 
                    if (!isset($alias) || empty($alias) || !preg_match($JwtAuth->filtroAlfaNumerico(),$alias)) $mensaje_error = "Error en alias de impuesto, por favor verifique su información o comuniquese a soporte"; 
                    if (!isset($impuesto_tipo) || empty($impuesto_tipo) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_tipo)) $mensaje_error = "Error en tipo de impuesto, por favor verifique su información o comuniquese a soporte";  
                    if (!isset($impuesto_tasa_tarifa) || empty($impuesto_tasa_tarifa) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_tasa_tarifa)) $mensaje_error = "Error en tasa o tarifa de impuesto, por favor verifique su información o comuniquese a soporte";  
                    if (!isset($impuesto_importe) || empty($impuesto_importe) || !preg_match($JwtAuth->filtroAlfaNumerico(),$impuesto_importe)) $mensaje_error = "Error en importe de impuesto, por favor verifique su información o comuniquese a soporte";  

                    $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "message" => $mensaje_error
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

    public function registroServicioAssoc(Request $request){
        $JwtAuth = new \JwtAuth();
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $parametrosArray = json_decode($jsonUser,true);
        if (!empty($parametros) && !empty($parametrosArray)) {
            $validate = \Validator::make($parametrosArray,[
                'user_token' => 'required|string',
                'concepto' => 'required|string', 
                'precio' => 'required|numeric',
                'unidad_medida' => 'required|string',
                'moneda_token' => 'required|string', 
                'claves_internas' => 'array',
                'impuestos' => 'array', 
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
                $concepto = $parametrosArray["concepto"]; 
                $precio = $parametrosArray["precio"]; 
                $unidad_medida = $parametrosArray["unidad_medida"]; 
                $moneda_token = $parametrosArray["moneda_token"]; 
                $claves_internas = $parametrosArray["claves_internas"]; 
                $impuestos = $parametrosArray["impuestos"];

                if (isset($concepto) && !empty($concepto) && isset($precio) && !empty($precio) && isset($unidad_medida) && !empty($unidad_medida) && isset($moneda_token) && !empty($moneda_token)) {
                    //return response()->json(["message" => "prueba25","code" => 200,"status" => "error"]);
                    $selectEmp = DB::select("SELECT emp.id,emp.root_tkn,pers.id AS userr,emp.zona_horaria,people.paterno,
                        people.materno,people.nombre,people.denominacion_rs,people.sitio_web FROM main_empresas AS emp  
                        JOIN sos_personas AS people JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.persona = people.id 
                        AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id 
                        AND users.usuario_token= ?",[$usuario->empresa_token,$usuario->user_token]);
                    //da_te_default_timezone_set($selectEmp[0]->zona_horaria);
                    //echo $selectEmp[0]->id;

                    $folioSistemaTemp = DB::select("SELECT temps_folio FROM in_egr_catalogo_productos WHERE temps_folio IS NOT NULL AND admin_empresa = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$usuario->empresa_token]);
                    if (count($folioSistemaTemp) > 0) {
                        $queryFolioTmpPrv = DB::select("SELECT temps_folio+1 AS temps_folio FROM in_egr_catalogo_productos 
                            WHERE id = (SELECT Max(catproD.id) FROM in_egr_catalogo_productos AS catproD 
                            JOIN main_empresas AS emp WHERE temps_folio IS NOT NULL AND catproD.admin_empresa = emp.id 
                            AND emp.empresa_token = ?)", [$usuario->empresa_token]);
                        
                        foreach ($queryFolioTmpPrv as $vTemp) {
                            $folio_temporal = $vTemp->temps_folio;
                        }
                    } else {
                        $folio_temporal = 1;
                    }
    
                    $folio_prod_temp = 'PROD-TEMP-'.$JwtAuth->generarFolio($folio_temporal);
                    
                    $conceptoProd = $JwtAuth->encriptar(strtolower($concepto));
                    $unidadMSalidaDB = DB::select("SELECT id FROM teci_unidad_medida WHERE token_unidad_medida = ?",[$unidad_medida]);
                    $monedaSalidaDB = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?",[$moneda_token]);
                    $ubicaProducto = DB::select("SELECT catprod.id FROM in_egr_catalogo_productos AS catprod
                        JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users 
                        WHERE catprod.producto = ? AND catprod.admin_empresa = emp.id 
                        AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                        AND pers.usuario = users.id AND users.usuario_token = ?",
                    [$conceptoProd,$usuario->empresa_token,$usuario->user_token]);
                    if (count($ubicaProducto) == 0) {
                        $tokenCatProd = $JwtAuth->encriptarToken($conceptoProd.$precio.$unidadMSalidaDB[0]->id);    
                        $newProd = new ProductosModelo();
                        $newProd->fecha_registro_prod = $fecha_sistema;
                        $newProd->token_cat_productos = $tokenCatProd;
                        $newProd->temps_folio = $folio_temporal;
                        $newProd->authorized = FALSE;
                        $newProd->modulo_mostrador = TRUE;
                        $newProd->producto = $conceptoProd;
                        $newProd->medida_salida = $unidadMSalidaDB[0]->id;
                        $newProd->costo_aplicable = $precio;
                        $newProd->moneda_aplicable = $monedaSalidaDB[0]->id;
                        $newProd->tipo_prod = 'pr';
                        $newProd->activo = NULL;
                        $newProd->proceso = FALSE;
                        $newProd->utilizado = FALSE;
                        $newProd->fecha_delete_prod = '';
                        $newProd->status = TRUE;
                        $newProd->admin_empresa = $selectEmp[0]->id;
                        $newProd->admin_user_registra = $selectEmp[0]->userr;
                        $savednewProd = $newProd->save();
                    
                        if ($savednewProd) {
                            $obtenProducto = DB::select("SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?",[$tokenCatProd]);
                            if (count($claves_internas) > 0) {
                                for ($i=0; $i < count($claves_internas); $i++) { 
                                    $clave_name = $claves_internas[$i]['clave_name'];
                                    $valor_name = $claves_internas[$i]['valor_name'];
                                    $tokenClabeProdProv = $JwtAuth->encriptarToken(time(),$clave_name,$valor_name); 
                                    $insertProd = DB::table('in_egr_catalogo_productos_claves_internas') 
                                    ->insert(array(
                                        "token_alta_clave" => $tokenClabeProdProv,  
                                        "producto_alta" => $obtenProducto[0]->id,
                                        "clave_nombre" => $clave_name,
                                        "clave_valor" => $valor_name,    
                                    ));
                                }
                            }
                            if (count($impuestos) > 0) {
                                for ($i=0; $i < count($impuestos); $i++) { 
                                    $impuesto_vinculado = DB::select("SELECT id FROM cont_impuestos_catalogo WHERE token_cat_impuestos = ?",[$impuestos[$i]['impuesto_vinculado']]);
                                    $tokenImpArt = $JwtAuth->encriptarToken(time(),$obtenProducto[0]->id,$impuesto_vinculado[0]->id); 
                                    $insertImpArt = DB::table('in_egr_impuestos_articulos') 
                                    ->insert(array(
                                        "token_impuestos_articulos" => $tokenImpArt,
                                        "producto_rel" => $obtenProducto[0]->id,
                                        "impuestos" => $impuesto_vinculado[0]->id,    
                                    ));
                                }
                            }
                            $JwtAuth->insertBitacoraActividad('egresos','catalogos','productos',$folio_prod_temp,'registro en el catalogo de productos',$usuario->empresa_token,$usuario->user_token);
                
                            $dataMensaje = array(
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Este producto ha sido registrado satisfactoriamente con el folio '.$folio_prod_temp
                            );
                        } else {
                            $dataMensaje = array(
                                'status' => 'error',
                                'code' => 404,
                                'message' => 'La información de este producto no es valida'
                            );
                        }
                    } else {
                        $dataMensaje = array(
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Este producto ya ha sido registrado anteriormente, intente nuevamente o comuniquese a soporte'
                        );
                    }
                    
                } else {
                    $error_alerta = "";
                    if (!isset($concepto) || empty($concepto)){$error_alerta = "error al ingresar concepto del producto, verifique su información o comuniquese a soporte para más información";}
                    if (!isset($precio) || empty($precio)){$error_alerta = "error al ingresar precio de producto, verifique su información o comuniquese a soporte para más información";}
                    if (!isset($unidad_medida) || empty($unidad_medida)){$error_alerta = "error al ingresar unidad de medida, verifique su información o comuniquese a soporte para más información";}
                    $dataMensaje = array(
                        'status' => 'error',
                        'code' => 404,
                        'message' => $error_alerta
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

    public function cargaArticulosVenta_(Request $request){
        $JwtAuth = new \JwtAuth();
        //echo $JwtAuth->desencriptar("b05lQ21XWWdacWlENS9HTk1FOFdlQT09OjoxMjM0NTY3ODEyMzQ1Njc4");
        $jsonUser = $request->input('json');
        $parametros = json_decode($jsonUser);
        $usuario = $JwtAuth->checkToken($parametros->user_token,true); 
        //echo $JwtAuth->encriptar('prueba1serv');
        $arrayArticulos = array();
        
        //echo $JwtAuth->encriptar('acer')." acer";
        
        $decimalesMoneda = DB::select("SELECT catmon.decimales FROM teci_catalogo_monedas AS catmon 
            JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers 
            JOIN teci_usuarios_catalogo AS users WHERE emp.e_moneda = catmon.id AND emp.empresa_token = ?
            AND emp.id = empuser.empresa AND empuser.personal = pers.id 
            AND pers.usuario = users.id AND users.usuario_token = ?",
            [$usuario->empresa_token,$usuario->user_token]);
        
        $catProdServ = DB::select("SELECT total.token_articulo,total.identificador,total.concepto,total.clasificacion,
            total.genero,total.folio,total.clave,total.descripcion,total.precioBase,total.SAT,total.root_tkn,total.fecha_alta
            FROM ((SELECT catprod.token_cat_productos AS token_articulo,catprod.fecha_registro_prod AS fecha_alta,'Producto' AS identificador,
                concat(catprod.producto,'-',catprod.marca) AS concepto,catprod.clasificacion,gen.folio_genero AS genero,
                catprod.folio_sistema AS folio,prodsat.clave,prodsat.descripcion,ROUND(detprice.precio,?) AS precioBase,
                concat(unimed.unidad_medida,' - ',unimed.sat_clave) as SAT,emp.root_tkn
                FROM in_egr_catalogo_productos AS catprod JOIN sos_ps_genero AS gen 
                JOIN ingr_catalogo_lista_precios AS price JOIN ingr_catalogo_lista_precios_detalle AS detprice
                JOIN teci_catalogo_prodservsat AS prodsat JOIN teci_unidad_medida AS unimed
                JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers
                JOIN teci_usuarios_catalogo AS users WHERE catprod.id = detprice.producto AND detprice.lista = price.id
                AND price.token_lista_precios = 'S0dNRUZGbGdGTFlzTzJyNnBhRkJaZz09OjoxMjM0NTY3ODEyMzQ1Njc4'
                AND catprod.genero = gen.id AND catprod.token_cat_productos IN (
                    SELECT catprod.token_cat_productos FROM in_egr_catalogo_productos AS catprod
                    JOIN eegr_compras_detalle AS detcomp JOIN eegr_compras AS buy JOIN eegr_compras_recepcion AS recept
                    JOIN in_egr_establecimientos_almacen AS det_alm WHERE catprod.id = detcomp.producto
                    AND detcomp.activo_fijo IS NULL AND detcomp.activo_intangible IS NULL
                    AND detcomp.numero_compra = buy.id AND detcomp.id = recept.detalle_compra
                    AND recept.recept_status = TRUE AND recept.id = det_alm.recepcion_compra
                    AND buy.status_recepcion = TRUE
                )
            AND catprod.catalogo_sat = prodsat.id AND catprod.medida_entrada = unimed.id AND catprod.tipo_prod = 'pr'
            AND catprod.activo IS NULL AND catprod.status = TRUE AND catprod.admin_empresa = emp.id
            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
            AND pers.usuario = users.id AND users.usuario_token = ?
            UNION ALL
            SELECT catserv.token_cat_servicios AS token_articulo,catserv.fechaAlta AS fecha_alta,'Servicio' AS identificador,
            catserv.servicio AS concepto,catserv.clasificacion,gen.folio_genero AS genero,catserv.folio,catserv.catalogo_sat,
            prodsat.descripcion,ROUND(detprice.precio,?) AS precioBase,concat(unimed.unidad_medida,' - ',unimed.sat_clave) as SAT,
            emp.root_tkn FROM in_egr_catalogo_servicios AS catserv JOIN sos_ps_genero AS gen 
            JOIN ingr_catalogo_lista_precios AS price JOIN ingr_catalogo_lista_precios_detalle AS detprice 
            JOIN teci_catalogo_prodservsat AS prodsat
            JOIN teci_unidad_medida AS unimed JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers
            JOIN teci_usuarios_catalogo AS users WHERE catserv.id = detprice.servicio AND detprice.lista = price.id
            AND price.token_lista_precios = 'S0dNRUZGbGdGTFlzTzJyNnBhRkJaZz09OjoxMjM0NTY3ODEyMzQ1Njc4' AND catserv.genero = gen.id 
            AND catserv.medida_sat = unimed.id AND catserv.proceso = FALSE AND catserv.status = TRUE 
            AND catserv.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
            AND pers.usuario = users.id AND users.usuario_token = ?) as total) ORDER BY total.concepto DESC"
            ,[$decimalesMoneda[0]->decimales,$usuario->empresa_token,$usuario->user_token,$decimalesMoneda[0]->decimales,$usuario->empresa_token,$usuario->user_token]
        );

        foreach ($catProdServ as $value) {
            $arrayDescuentos = array();
            $arrayPromociones = array();
            $arrayDesgloseImpuestos = array();
            $arraySerieLoteImport = array();
            $arrayImportado = array();
            $token_Articulo = $value->token_articulo;
            $dataPrecioBase = $value->precioBase;
            
            //if ($value->identificador == 'Servicio') {
            //    $dataPrecioBase = $JwtAuth->desencriptar($dataPrecioBase);
            //}
            //echo ." ".;
            $dataCantidad = 1.00;
            $resTotalDataDesc = '';
            $importeTdescuento = 0.00;
            $totalImpuesto = floatVal(0);
            $importePartida = 0.00;
            $contadorDescuentos = 0;

            if ($value->identificador == 'Producto') {
                $consultaImpArticulo = DB::select("SELECT catimp.* FROM in_egr_impuestos_articulos AS imp_art JOIN cont_impuestos_catalogo AS catimp
                    JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers 
                    JOIN teci_usuarios_catalogo AS users WHERE catimp.id = imp_art.impuestos AND imp_art.producto_rel = catprod.id 
                    AND catprod.token_cat_productos = ? AND catprod.status = TRUE AND catprod.admin_empresa = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id 
                    AND pers.usuario = users.id AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);

                $listaDescModal = DB::select("SELECT descu.id,descu.token_descuentos,descu.folio,descu.alias,descu.concepto,descu.cuo_porc,
                    descu.cantidad_base,detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin FROM ingr_catalogo_descuentos AS descu 
                    JOIN ingr_detalle_descuento AS detdes JOIN in_egr_catalogo_productos AS catprod JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users WHERE descu.id = detdes.descuento AND detdes.producto = catprod.id
                    AND catprod.token_cat_productos = ? AND descu.status_activacion = TRUE AND descu.status = TRUE 
                    AND descu.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                    AND pers.usuario = users.id AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);

                $listaPromoModal = DB::select("SELECT promo.token_promocion,promo.folio,promo.alias,promo.concepto,promo.cuo_porc,
                    promo.cantidad_base,detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin
                    FROM ingr_catalogo_promociones AS promo JOIN ingr_detalle_promocion AS detpromo JOIN in_egr_catalogo_productos AS catprod 
                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users
                    WHERE promo.id = detpromo.promocion AND detpromo.producto = catprod.id AND catprod.token_cat_productos = ?
                    AND promo.status_activacion = TRUE AND promo.status = TRUE AND promo.empresa = emp.id AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id AND users.usuario_token = ?",
                    [$token_Articulo,$usuario->empresa_token,$usuario->user_token]);

            } else {
                $consultaImpArticulo = DB::select("SELECT catimp.* FROM in_egr_impuestos_articulos AS imp_art JOIN cont_impuestos_catalogo AS catimp
                    JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers 
                    JOIN teci_usuarios_catalogo AS users WHERE catimp.id = imp_art.impuestos AND imp_art.servicio_rel = catserv.id 
                    AND catserv.token_cat_servicios = ? AND catserv.status = TRUE AND catserv.administrador = emp.id 
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);

                $listaDescModal = DB::select("SELECT descu.id,descu.token_descuentos,descu.folio,descu.alias,descu.concepto,descu.cuo_porc,
                    descu.cantidad_base,detdes.aplicacion,detdes.fecha_inicio,detdes.fecha_fin FROM ingr_catalogo_descuentos AS descu 
                    JOIN ingr_detalle_descuento AS detdes JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users WHERE descu.id = detdes.descuento AND detdes.servicio = catserv.id
                    AND catserv.token_cat_servicios = ? AND descu.status_activacion = TRUE AND descu.status = TRUE AND descu.empresa = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);

                $listaPromoModal = DB::select("SELECT promo.token_promocion,promo.folio,promo.alias,promo.concepto,promo.cuo_porc,
                    promo.cantidad_base,detpromo.aplicacion,detpromo.fecha_inicio,detpromo.fecha_fin FROM ingr_catalogo_promociones AS promo 
                    JOIN ingr_detalle_promocion AS detpromo JOIN in_egr_catalogo_servicios AS catserv JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser
                    JOIN vhum_personal AS pers JOIN teci_usuarios_catalogo AS users WHERE promo.id = detpromo.promocion AND detpromo.servicio = catserv.id
                    AND catserv.token_cat_servicios = ? AND promo.status_activacion = TRUE AND promo.status = TRUE AND promo.empresa = emp.id
                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id AND pers.usuario = users.id
                    AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
            }

            if (count($consultaImpArticulo) != 0) {
                //$arrayImpuestos = json_decode($JwtAuth->desencriptar($consultaImpArticulo[0]->impuestos));
                for ($i=0; $i < count($consultaImpArticulo); $i++) {
                    $tknImpuesto = $consultaImpArticulo[$i]->token_cat_impuestos;                
                    $catImpuestos = DB::select("SELECT catimp.id,catimp.token_cat_impuestos,catimp.alias,catimp.clasificacion_impuestos,
                        catimp.ret_tras,catimp.por_cuo,catimp.importe,tip.concepto,tip.tipo FROM cont_impuestos_catalogo AS catimp  
                        JOIN cont_impuestos_catalogo_tipo AS tip JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers 
                        JOIN teci_usuarios_catalogo AS users WHERE catimp.token_cat_impuestos = ? AND catimp.impuesto = tip.id AND catimp.status = TRUE
                        AND catimp.empresa = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                        AND pers.usuario = users.id AND users.usuario_token = ?",[$tknImpuesto,$usuario->empresa_token,$usuario->user_token]);

                    if (count($catImpuestos) == 1) {
                        $resImpDat = $catImpuestos[0];
                        //$dataPrecioBase,$totalImpuesto 
                        $cantBaseImpuesto = $catImpuestos[0]->importe;
                        
                        if ($resImpDat->por_cuo == TRUE) {
                            $importeBase = explode("%",$cantBaseImpuesto);
                            //echo $importeBase[0];
                            $multi = '';
                            if ($importeBase[0] > 0 && $importeBase[0] < 1) {
                                $importeBase2 = explode(".",$importeBase[0]);
                                $multi = '0.00'.$importeBase2[1];
                            } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
                                $multi = '0.0'.$importeBase[0];
                            } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
                                $multi = '0.'.$importeBase[0];
                                //echo $multi;
                            } else if ($importeBase[0] == 100) {
                                $multi = 1;
                            }
                            //echo $importePartida ;
                            $totalImp =  floatval($dataPrecioBase) * floatval($multi);
                        } else {
                            $importeBase = str_replace("$","",$cantBaseImpuesto);
                            $importeBase = str_replace(",","",$importeBase);
                            $totalImp = floatval($importeBase);
                        }

                        if ($resImpDat->ret_tras == TRUE) {
                            //echo $totalImpuesto;
                            $totalImpuesto = $totalImpuesto + $totalImp;
                        }

                        if ($resImpDat->ret_tras == FALSE) {
                            $totalImpuesto = $totalImpuesto - $totalImp;
                        }
                    } else {
                        $totalImpuesto = $totalImpuesto + 0;
                    }
                }
            } else {
                $totalImpuesto = $totalImpuesto; 
            }
            
            $precioBaseConImp = number_format($dataPrecioBase + $totalImpuesto,2,'.',',');

            //echo count($listaDescModal).", "; 
            
            if (count($listaDescModal) == 0) {
                $importeTdescuento = 0.00;
            } else {
                $cantidadBaseDesc = $JwtAuth->desencriptar($listaDescModal[0]->cantidad_base);
                
                if ($listaDescModal[0]->cuo_porc == TRUE) {
                    $importeBase = explode("%",$cantidadBaseDesc);
                    $multi = '';
                    if ($importeBase[0] > 0 && $importeBase[0] < 1) {
                        $importeBase2 = explode(".",$importeBase[0]);
                        $multi = '0.00'.$importeBase2[1];
                    } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
                        $multi = '0.0'.$importeBase[0];
                    } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
                        $multi = '0.'.$importeBase[0];
                    } else if ($importeBase[0] == 100) {
                        $multi = 1;
                    }
                    $importeTdescuento = $dataPrecioBase * floatval($multi);
                } else {
                    $importeBase = explode("$",$cantidadBaseDesc);
                    $importeTdescuento = floatval($importeBase[1]);
                } 
                //$importeTdescuento = number_format($importeTdescuento,2,'.',',');

                foreach($listaDescModal AS $resListaDesc){
                    //echo $resListaDesc->id;
                    if ($resListaDesc->cuo_porc == 0) {
                        $cuoPorc = 'cuota';
                    } else {
                        $cuoPorc = 'porcentaje';
                    }

                    if ($resListaDesc->aplicacion == 'usa') {
                        $periodo = 'Eventual';
                        $resPeriodoInicio = '-'; $resPeriodoFin = '-'; 
                    } else if($resListaDesc->aplicacion == 'ind'){
                        $periodo = 'Periodo Indeterminado';
                        $resPeriodoInicio = ''; $resPeriodoFin = '-'; 
                        $valorFechaInicio = $resListaDesc->fecha_inicio;
                        //da_te_default_timezone_set('America/Mexico_City');
                        $resPeriodoInicio = date('d-m-Y H:i:s',$valorFechaInicio);
                    } else if($resListaDesc->aplicacion == 'det'){
                        $periodo = 'Periodo Determinado';
                        //da_te_default_timezone_set('America/Mexico_City');
                        $resPeriodoInicio = ''; $resPeriodoFin = ''; 
                        $valorFechaInicio = $resListaDesc->fecha_inicio;
                        $resPeriodoInicio = date('d-m-Y H:i:s',$valorFechaInicio);
                        $valorFechaFin = $resListaDesc->fecha_fin;
                        $resPeriodoFin = date('d-m-Y H:i:s',$valorFechaFin);
                    }

                    if (count($arrayDescuentos) == 0) {
                        $valorDescuento = $importeTdescuento;
                    } else {
                        $valorDescuento = floatVal('0.00');
                    }

                    if ($contadorDescuentos == 0) {
                        $checkDesc = '0TRUE';
                    } else {
                        $checkDesc = '1FALSE';
                    }

                    $arraForeachDesc = array(
                        "token_descuentos" => $resListaDesc->token_descuentos,
                        "folioDesc" => $JwtAuth->generar($resListaDesc->folio),
                        "aliasDesc" => $JwtAuth->desencriptar($resListaDesc->alias),
                        "conceptoDesc" => $JwtAuth->desencriptar($resListaDesc->concepto),
                        "cuoPorc" => $cuoPorc,
                        "cantidad_base" => $JwtAuth->desencriptar($resListaDesc->cantidad_base),
                        "periodo" => $periodo,
                        "resPeriodoInicio" => $resPeriodoInicio,
                        "resPeriodoFin" => $resPeriodoFin,
                        "tdImporteDesc" => number_format($valorDescuento,2,'.',','),
                        "rescheck" => $checkDesc,
                    );
                    $arrayDescuentos[] = $arraForeachDesc;
                    $contadorDescuentos++;
                }

            }

            $importePartida = ($dataPrecioBase * $dataCantidad) - $importeTdescuento;
            $importePartida = number_format($importePartida,2,'.',',');

            if (count($listaPromoModal) > 0) {
                foreach($listaPromoModal AS $resListaPromo){
                    if ($resListaPromo->cuo_porc == 0) {
                        $cuoPorc = 'cuota';
                    } else {
                        $cuoPorc = 'porcentaje';
                    }

                    if ($resListaPromo->aplicacion == 'usa') {
                        $periodo = 'Eventual';
                        $resPeriodoInicio = '-'; $resPeriodoFin = '-'; 
                    } else if($resListaPromo->aplicacion == 'ind'){
                        $periodo = 'Periodo Indeterminado';
                        $resPeriodoInicio = ''; $resPeriodoFin = '-'; 
                        $valorFechaInicio = $resListaPromo->fecha_inicio;
                        //da_te_default_timezone_set('America/Mexico_City');
                        $resPeriodoInicio = date('d-m-Y H:i:s',$valorFechaInicio);
                    } else if($resListaPromo->aplicacion == 'det'){
                        $periodo = 'Periodo Determinado';
                        //da_te_default_timezone_set('America/Mexico_City');
                        $resPeriodoInicio = ''; $resPeriodoFin = ''; 
                        $valorFechaInicio = $resListaPromo->fecha_inicio;
                        $resPeriodoInicio = date('d-m-Y H:i:s',$valorFechaInicio);
                        $valorFechaFin = $resListaPromo->fecha_fin;
                        $resPeriodoFin = date('d-m-Y H:i:s',$valorFechaFin);
                    }
                    //$importePartida

                    $cantidadBasePromo = $JwtAuth->desencriptar($resListaPromo->cantidad_base);
                    //echo $cantidadBasePromo;
                    if ($resListaPromo->cuo_porc == TRUE) {
                        $importeBase = explode("%",$cantidadBasePromo);
                        $multi = '';
                        if ($importeBase[0] > 0 && $importeBase[0] < 1) {
                            $importeBase2 = explode(".",$importeBase[0]);
                            $multi = '0.00'.$importeBase2[1];
                        } else if ($importeBase[0] >= 1 && $importeBase[0] < 10) {
                            $multi = '0.0'.$importeBase[0];
                        } else if ($importeBase[0] >= 10 && $importeBase[0] < 100) {
                            $multi = '0.'.$importeBase[0];
                        } else if ($importeBase[0] == 100) {
                            $multi = 1;
                        }
                        $tdImportePromo = $dataPrecioBase * floatval($multi);
                    } else {
                        $importeBase = explode("$",$cantidadBasePromo);
                        $tdImportePromo = floatval($importeBase[1]);
                    } 
                    $tdImportePromo = number_format($tdImportePromo,2,'.',',');
                    //echo $tdImportePromo; 
                    $arraForeachPromo = array(
                        "token_promocion" => $resListaPromo->token_promocion,
                        "folioPromo" => $JwtAuth->generar($resListaPromo->folio),
                        "aliasPromo" => $JwtAuth->desencriptar($resListaPromo->alias),
                        "conceptoPromo" => $JwtAuth->desencriptar($resListaPromo->concepto),
                        "cuoPorc" => $cuoPorc,
                        "cantidad_base" => $JwtAuth->desencriptar($resListaPromo->cantidad_base),
                        "periodo" => $periodo,
                        "resPeriodoInicio" => $resPeriodoInicio,
                        "resPeriodoFin" => $resPeriodoFin,
                        "tdImportePromo" => $tdImportePromo,
                    );
                    $arrayPromociones[] = $arraForeachPromo;
                }
            } 

            if ($value->identificador == 'Producto') {
                $conceptoExplode = explode("-",$value->concepto);
                //SELECT * FROM in_egr_productos_kardex where status_kardex = 5 AND fecha = (SELECT max(fecha) FROM in_egr_productos_kardex where fecha < now());
                //;
                $sumaExistencias = DB::select("SELECT SUM(saldo_cantidad) AS existencia FROM in_egr_productos_kardex 
                    WHERE status_kardex = 6 AND fecha_kardex = (SELECT MAX(fecha_kardex) FROM in_egr_productos_kardex WHERE fecha_kardex < now() AND status_kardex = 6)
                    AND producto = (SELECT id FROM in_egr_catalogo_productos WHERE token_cat_productos = ?)",[$token_Articulo]);
                
                $producto = DB::select("SELECT catprod.costeo,catprod.num_serie,catprod.num_lote,catprod.importado 
                    FROM in_egr_catalogo_productos AS catprod 
                    JOIN main_empresas AS emp
                    JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers
                    JOIN teci_usuarios_catalogo AS users
                    WHERE catprod.token_cat_productos = ?
                    AND catprod.admin_empresa = emp.id
                    AND emp.empresa_token = ?
                    AND emp.id = empuser.empresa
                    AND empuser.personal = pers.id
                    AND pers.usuario = users.id
                    AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);

                if ($producto[0]->num_serie == TRUE) {
                    $serieProd = DB::select("SELECT detalm.token_detalle_almacen,detalm.num_serie,detalm.existencia
                        FROM ingr_ventas_detalle_almacen AS detalm
                        JOIN in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers
                        JOIN teci_usuarios_catalogo AS users
                        WHERE detalm.status_disponibilidad = TRUE
                        AND detalm.producto = catprod.id
                        AND catprod.token_cat_productos = ?
                        AND catprod.admin_empresa = emp.id
                        AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa
                        AND empuser.personal = pers.id
                        AND pers.usuario = users.id
                        AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
                    //echo count($serieProd);
                    if (count($serieProd) != 0) {
                        $serie = array();
                        if ($producto[0]->costeo == 'UEPS') {
                            for ($i= count($serieProd)-1; $i >= 0; $i--) { 
                                $arrayEach = array(
                                    "token_detalle_almacen" => $serieProd[$i]->token_detalle_almacen,
                                    "num_serie" => $serieProd[$i]->num_serie,
                                    "existencia" => $serieProd[$i]->existencia,
                                );
                                $serie[] = $arrayEach;
                            }
                        } 
                        if ($producto[0]->costeo == 'PEPS') {
                            for ($i= 0; $i < count($serieProd); $i++) { 
                                $arrayEach = array(
                                    "token_detalle_almacen" => $serieProd[$i]->token_detalle_almacen,
                                    "num_serie" => $serieProd[$i]->num_serie,
                                    "existencia" => $serieProd[$i]->existencia,
                                );
                                $serie[] = $arrayEach;
                            }
                        } 
                    } else {
                        $serie = array();
                    }
                } else {
                    $serie = array();
                }

                if ($producto[0]->num_lote == TRUE) {
                    $loteProd = DB::select("SELECT token_lote,numero_lote
                        FROM lote_prod 
                        WHERE id in (SELECT detalm.num_lote
                        FROM ingr_ventas_detalle_almacen AS detalm
                        JOIN in_egr_catalogo_productos AS catprod 
                        JOIN main_empresas AS emp
                        JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers
                        JOIN teci_usuarios_catalogo AS users
                        WHERE detalm.status_disponibilidad = TRUE
                        AND detalm.producto = catprod.id
                        AND catprod.token_cat_productos = ?
                        AND catprod.admin_empresa = emp.id
                        AND emp.empresa_token = ?
                        AND emp.id = empuser.empresa
                        AND empuser.personal = pers.id
                        AND pers.usuario = users.id
                        AND users.usuario_token = ?)
                        ",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
                    
                    //echo count($loteProd);
                    if (count($loteProd) != 0) {
                        $lote = array();
                        
                        if ($producto[0]->costeo == 'UEPS') {
                            for ($i= count($loteProd)-1; $i >= 0; $i--) { 
                                $sumLote = DB::select("SELECT SUM(existencia) AS existencia
                                    FROM ingr_ventas_detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.num_lote = (SELECT id FROM lote_prod WHERE token_lote = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                                    [$loteProd[$i]->token_lote,$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
                                $arrayEach = array(
                                    "token_lote" => $loteProd[$i]->token_lote,
                                    "num_lote" => $loteProd[$i]->numero_lote,
                                    "existencia" => $sumLote[0]->existencia,
                                );
                                $lote[] = $arrayEach;
                            }
                        } 
                        if ($producto[0]->costeo == 'PEPS') {
                            for ($i= 0; $i < count($loteProd); $i++) { 
                                $sumLote = DB::select("SELECT SUM(existencia) AS existencia
                                    FROM ingr_ventas_detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.num_lote = (SELECT id FROM lote_prod WHERE token_lote = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                                    [$loteProd[$i]->token_lote,$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
                                $arrayEach = array(
                                    "token_lote" => $loteProd[$i]->token_lote,
                                    "num_lote" => $loteProd[$i]->numero_lote,
                                    "existencia" => $sumLote[0]->existencia,
                                );
                                $lote[] = $arrayEach;
                            }
                        } 
                    } else {
                        $lote = array();
                    }
                } else {
                    $lote = array();
                }

                if ($producto[0]->importado == TRUE) {
                    //$prodImportado = DB::select("SELECT catprod.importado,
                        //ped.token_pedimento,ped.numero_pedimiento,ped.tipo_operacion,
                        //ped.regimen,ped.destino,ped.tipo_cambio,ped.aduana FROM  pedimento_aduanal AS ped 
                        //JOIN productos_importados AS importt
                        //JOIN in_egr_catalogo_productos AS catprod 
                        //WHERE ped.id = importt.pedimento
                        //AND importt.existencia != 0
                        //AND importt.producto = catprod.id
                        //AND catprod.importado = true
                        //AND catprod.status = true
                        //AND catprod.token_cat_productos = ?
                        //AND catprod.admin_empresa = emp.id
                        //AND emp.empresa_token = ?
                        //AND emp.id = empuser.empresa
                        //AND empuser.personal = pers.id
                        //AND pers.usuario = users.id
                        //AND users.usuario_token = ?",[$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
                    $pedimentoProd = DB::select("SELECT token_pedimento,numero_pedimento 
                        FROM pedimento_aduanal
                        WHERE id IN (SELECT detalm.importado
                            FROM ingr_ventas_detalle_almacen AS detalm
                            JOIN in_egr_catalogo_productos AS catprod 
                            JOIN main_empresas AS emp
                            JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers
                            JOIN teci_usuarios_catalogo AS users
                            WHERE detalm.status_disponibilidad = TRUE
                            AND detalm.producto = catprod.id
                            AND catprod.token_cat_productos = ?
                            AND catprod.admin_empresa = emp.id
                            AND emp.empresa_token = ?
                            AND emp.id = empuser.empresa
                            AND empuser.personal = pers.id
                            AND pers.usuario = users.id
                            AND users.usuario_token = ?)",
                            [$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
                    if (count($pedimentoProd) != 0) {
                        $pedimento = array();

                        if ($producto[0]->costeo == 'UEPS') {
                            for ($i= count($pedimentoProd)-1; $i >= 0; $i--) { 
                                echo $pedimentoProd[$i]->token_pedimento;
                                $sumImported = DB::select("SELECT SUM(existencia) AS existencia
                                    FROM ingr_ventas_detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.importado = (SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                                    [$pedimentoProd[$i]->token_pedimento,$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
                                $arrayEach = array(
                                    "token_pedimento" => $pedimentoProd[$i]->token_pedimento,
                                    "numero_pedimento" => $pedimentoProd[$i]->numero_pedimento,
                                    "existencia" => $sumImported[0]->existencia,
                                );
                                $pedimento[] = $arrayEach;
                            }
                        } 
                        if ($producto[0]->costeo == 'PEPS') {
                            for ($i= 0; $i < count($pedimentoProd); $i++) { 
                                //echo $token_Articulo;
                                $sumImported = DB::select("SELECT SUM(existencia) AS existencia
                                    FROM ingr_ventas_detalle_almacen AS detalm JOIN in_egr_catalogo_productos AS catprod 
                                    JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_personal AS pers
                                    JOIN teci_usuarios_catalogo AS users WHERE detalm.importado = (SELECT id FROM pedimento_aduanal WHERE token_pedimento = ?)
                                    AND detalm.status_disponibilidad = TRUE AND detalm.producto = catprod.id
                                    AND catprod.token_cat_productos = ? AND catprod.admin_empresa = emp.id
                                    AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.personal = pers.id
                                    AND pers.usuario = users.id AND users.usuario_token = ?",
                                    [$pedimentoProd[$i]->token_pedimento,$token_Articulo,$usuario->empresa_token,$usuario->user_token]);
                                $arrayEach = array(
                                    "token_pedimento" => $pedimentoProd[$i]->token_pedimento,
                                    "numero_pedimento" => $pedimentoProd[$i]->numero_pedimento,
                                    "existencia" => $sumImported[0]->existencia,
                                );
                                $pedimento[] = $arrayEach;
                            }
                        } 


                    } else {
                        $pedimento = array();
                    }
                } else {
                    $pedimento = array();
                }

                $arraySerieLoteImport = array(
                    "existKardex" => $sumaExistencias[0]->existencia,
                    "serie" => $serie,
                    "lote" => $lote,
                    "pedimento" => $pedimento
                ); 

                $conceptoArticulo = $JwtAuth->desencriptar($conceptoExplode[0]).
                " Marca:(".$JwtAuth->desencriptar($conceptoExplode[1]).")";

            } else {
                $conceptoArticulo = $JwtAuth->desencriptar($value->concepto);
                $arraySerieLoteImport = [];
            }
            ///echo 'imaagen '.$JwtAuth->encriptar('default_prod.jpg').' ';
            //echo $totalImpuesto;
            $importePartida = ($dataPrecioBase * $dataCantidad) - $importeTdescuento;
            $importePartida = number_format($importePartida,2,'.',',');

            $arraForeach = array(
                "token_articulo" => $value->token_articulo,
                "identificador" => $value->identificador,
                "clasificacion" => $JwtAuth->generar($value->clasificacion).'-'.$JwtAuth->generar($value->genero).'-'.$JwtAuth->generar($value->folio),
                "sat" => $value->SAT,
                "clave" => $value->clave,
                "descripcion" => $value->descripcion,
                "concepto" => $conceptoArticulo,
                "arraySerieLoteImport" => $arraySerieLoteImport,
                "precioBaseConImp" => $precioBaseConImp,
                "precioBase" => $dataPrecioBase,
                "dataCantidad" => $dataCantidad,
                "importeTdescuento" => $importeTdescuento,
                "arrayDescuentos" => $arrayDescuentos,
                "arrayPromociones" => $arrayPromociones,
                "totalImpuesto" => number_format($totalImpuesto,2,'.',','),
                //"arrayDesgloseImpuestos" => $arrayDesgloseImpuestos,
                "importePartida" => $importePartida,
            );
            $arrayArticulos[] = $arraForeach;
            
        }
        
        return response()->json([
            'listaArticulos' => $arrayArticulos,
            'codigo' => 200,
            'status' => 'success'
        ]);
    }

}
