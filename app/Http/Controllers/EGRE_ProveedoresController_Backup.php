<?php

namespace App\Http\Controllers;

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use App\Models\DireccionesModelo;
use App\Models\ProveedoresModelo;
use App\Models\FormaPagoModelo;
use App\Models\MonedasModelo;
use App\Models\CuentasContablesModelo;
use PDF;
use QRCode;

class EGRE_ProveedoresController extends Controller{
	public function proveedoresGen(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayProveedores = array();
		$arrayProvNacional = array();
		$arrayProvExtranjero = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
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

				$listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
					->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
					->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
					->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
					->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
					->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
					->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token, "catprov.status" => TRUE])->get();
				//echo count($listaProveedores);
				//echo $JwtAuth->desencriptar("RDRyRWpWczV2eU1mNjBxTXVJQ3pmZz09OjoxMjM0NTY3ODEyMzQ1Njc4");
				foreach ($listaProveedores as $resListProv) {
					$autorizado = $resListProv->authorized == TRUE ? true : false;
					$auth_fecha = $resListProv->authorized == TRUE ? date('d-m-Y H:i:s', $resListProv->authorized_fecha) : null;
					$utilizado = $resListProv->utilizado == TRUE ? true : false;

					//if ($resListProv->denominacion_rs != '') {
					//	$nombreProv = $JwtAuth->desencriptar($resListProv->denominacion_rs);
					//} else {
					//	$nombreProv = $JwtAuth->desencriptar($resListProv->paterno) . " " .
					//		$JwtAuth->desencriptar($resListProv->materno) . " " .
					//		$JwtAuth->desencriptar($resListProv->nombre);
					//}
					
					$nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

					$rfc_generico = $resListProv->rfc_generico;
                    $rfc_prov = $resListProv->rfc != NULL ? $JwtAuth->desencriptar($resListProv->rfc) : '---';
                    $tax_id_prov = $resListProv->tax_id != NULL ? $JwtAuth->desencriptar($resListProv->tax_id) : '---';

					if ($resListProv->folio != NULL && $resListProv->folio != "") {
						$folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio);
						if ($resListProv->post_folio != NULL) $folio_prov = $folio_prov . '-' . $resListProv->post_folio;
					} else {
						$folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($resListProv->temp_folio);
					}

					$lista_precios = $resListProv->lista_precios != NULL ? "A" : "";

					//if ($resListProv->denominacion_rs == '') {
					//	$nombre_prov_ = strtolower($JwtAuth->desencriptarNombres($resListProv->paterno,$resListProv->materno,$resListProv->nombre));
					//} else {
					//	$nombre_prov_ = strtolower($JwtAuth->desencriptar($resListProv->denominacion_rs));
					//}
                    //$updateProvName = DB::table("eegr_catalogo_proveedores AS catprov")
					//->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
					//->where(["catprov.token_cat_proveedores" => $resListProv->token_cat_proveedores])
                    //->limit(1)->update(
                    //    array(
                    //        'prov.nombre_extendido' => $JwtAuth->encriptar($nombre_prov_),
                    //        'prov.paterno' => NULL,
                    //        'prov.materno' => NULL,
                    //        'prov.nombre' => NULL,
                    //        'prov.denominacion_rs' => NULL,
                    //    )
                    //);

					$arrayForeach = array(
						"token_cat_proveedores" => $resListProv->token_cat_proveedores,
						"folio" => $folio_prov,
						"pais" => $resListProv->pais,
						"rfc_generico" => $rfc_generico,
						"rfc_prov" => $rfc_prov,
						"tax_id_prov" => $tax_id_prov,
						"nombre" => $nombreProv,
						"listaPrecios" => $lista_precios,
						"tiene_clave" => "",
						"encendido" => false,
						"utilizado" => $utilizado,
						"autorizado" => $autorizado,
						"auth_fecha" => $auth_fecha,
            "receptFactura" => $resListProv->receptFactura ? true : false, 
						"infoCollapsed" => true,
						"comprasCollapsed" => true,
						"data_detalle_vista" => false,
						"data_detalle" => [],
						"data_para_compras" => [],
						"data_anticipos" => [],
					);

					$arrayProveedores[] = $arrayForeach;

					$resListProv->nacionalidad == 118 ? $arrayProvNacional[] = $arrayForeach : $arrayProvExtranjero[] = $arrayForeach;
				}

				$dataMensaje = array(
					'status' => 'success',
					'code' => 200,
					'message' => 'La informacion que intenta registrar no es valida',
					'bitacora' => $JwtAuth->selectBitacoraActividad('egresos', 'catalogos', 'proveedores', $usuario->empresa_token, $usuario->user_token),
					'proveedores' => $arrayProveedores,
					'proveedornac' => $arrayProvNacional,
					'proveedorext' => $arrayProvExtranjero
				);

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

	public function proveedoresParaClaves(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayProveedores = array();
		$arrayProvNacional = array();
		$arrayProvExtranjero = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
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

				$listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
					->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
					->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
					->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
					->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
					->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
					->where(["emp.empresa_token" => $usuario->empresa_token, "users.usuario_token" => $usuario->user_token, "catprov.status" => TRUE])->get();
				foreach ($listaProveedores as $resListProv) {
					$autorizado = $resListProv->authorized == TRUE ? true : false;
					$auth_fecha = $resListProv->authorized == TRUE ? date('d-m-Y H:i:s', $resListProv->authorized_fecha) : null;
					$utilizado = $resListProv->utilizado == TRUE ? true : false;

					$nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

					$rfc_generico = $resListProv->rfc_generico;
                    $rfc_prov = $resListProv->rfc != NULL ? $JwtAuth->desencriptar($resListProv->rfc) : '---';
                    $tax_id_prov = $resListProv->tax_id != NULL ? $JwtAuth->desencriptar($resListProv->tax_id) : '---';

					if ($resListProv->folio != NULL && $resListProv->folio != "") {
						$folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio);
						if ($resListProv->post_folio != NULL) $folio_prov = $folio_prov . '-' . $resListProv->post_folio;
					} else {
						$folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($resListProv->temp_folio);
					}

					$lista_precios = $resListProv->lista_precios != NULL ? "A" : "";

					$arrayForeach = array(
						"token_cat_proveedores" => $resListProv->token_cat_proveedores,
						"folio" => $folio_prov,
						"pais" => $resListProv->pais,
						"rfc_generico" => $rfc_generico,
						"rfc_prov" => $rfc_prov,
						"tax_id_prov" => $tax_id_prov,
						"nombre" => $nombreProv,
						"listaPrecios" => $lista_precios,
						"encendido" => false,
						"tiene_clave" => "",
						"asigned_clave" => "",
					);

					$arrayProveedores[] = $arrayForeach;
				}

				return response()->json([
					'status' => 'success',
					'codigo' => 200,
					'proveedores' => $arrayProveedores
				]);
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

	public function catalogoProvAutorizados(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayProveedores = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
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

				$listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
					->join("sos_personas AS prov", "catprov.proveedor", "prov.id")
					->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
					->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
					->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
					->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
					->where([
						"catprov.status" => TRUE,
						"catprov.authorized" => TRUE,
						"emp.empresa_token" => $usuario->empresa_token,
						"users.usuario_token" => $usuario->user_token
					])->get();
				//echo count($listaProveedores);
				//echo $JwtAuth->desencriptar("RDRyRWpWczV2eU1mNjBxTXVJQ3pmZz09OjoxMjM0NTY3ODEyMzQ1Njc4");
				foreach ($listaProveedores as $resListProv) {
					$utilizado = false;
					if ($resListProv->utilizado == TRUE) $utilizado = true;

					$nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);

					$rfc_generico = $resListProv->rfc_generico;

					if ($resListProv->rfc != NULL) {
						$rfc_prov = $JwtAuth->desencriptar($resListProv->rfc);
					} else {
						$rfc_prov = '---';
					}

					if ($resListProv->tax_id != NULL) {
						$tax_id_prov = $JwtAuth->desencriptar($resListProv->tax_id);
					} else {
						$tax_id_prov = '---';
					}

					$folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio);
					if ($resListProv->post_folio != NULL) $folio_prov = $folio_prov . '-' . $resListProv->post_folio;

					$lista_precios = "";
					if ($resListProv->lista_precios != NULL) {
						$lista_precios = "A";
					}

					$arrayForeach = array(
						"token_cat_proveedores" => $resListProv->token_cat_proveedores,
						"folio" => $folio_prov,
						"pais" => $resListProv->pais,
						"rfc_generico" => $rfc_generico,
						"rfc_prov" => $rfc_prov,
						"tax_id_prov" => $tax_id_prov,
						"nombre" => $nombreProv,
						"listaPrecios" => $lista_precios,
						"tiene_clave" => "",
						"encendido" => false,
						"utilizado" => $utilizado,
						"auth_fecha" => date('d-m-Y H:i:s', $resListProv->authorized_fecha),
					);

					$arrayProveedores[] = $arrayForeach;
				}

				return response()->json(["status" => "success", "codigo" => 200, "listado" => $arrayProveedores]);
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

	public function catalogoProvNotAutorizados(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayProveedores = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
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

				$listaProveedores = DB::table("eegr_catalogo_proveedores AS catprov")
					->join("sos_personas AS prov", "catprov.proveedor","prov.id")
					->join("teci_pais AS ps","prov.nacionalidad","ps.id")
					->join("main_empresas AS emp", "catprov.administrador","=","emp.id")
					->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
					->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
					->where([
						"catprov.status" => TRUE,
						"catprov.authorized" => FALSE,
						"emp.empresa_token" => $usuario->empresa_token,
						"users.usuario_token" => $usuario->user_token
					])->get();
				//echo count($listaProveedores);
				//echo $JwtAuth->desencriptar("RDRyRWpWczV2eU1mNjBxTXVJQ3pmZz09OjoxMjM0NTY3ODEyMzQ1Njc4");
				foreach ($listaProveedores as $resListProv) {
					$utilizado = $resListProv->utilizado == TRUE ? true : false;
					$nombreProv = $JwtAuth->desencriptar($resListProv->nombre_extendido);
					$rfc_generico = $resListProv->rfc_generico;
					$rfc_prov = $resListProv->rfc != NULL ? $JwtAuth->desencriptar($resListProv->rfc) : '---';
					$tax_id_prov = $resListProv->tax_id != NULL ? $JwtAuth->desencriptar($resListProv->tax_id) : '---';
					$lista_precios = $resListProv->lista_precios != NULL ? "A" : "";

					if ($resListProv->temp_folio != NULL) {
						$folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($resListProv->temp_folio);
					} else {
						$folio_prov = 'PRV-' . $JwtAuth->generarFolio($resListProv->folio);
						if ($resListProv->post_folio != NULL) $folio_prov = $folio_prov . '-' . $resListProv->post_folio;
					}

					$soliValidate = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("eegr_catalogo_proveedores_soli_auth AS soli_auth", "catprov.id", "=", "soli_auth.proveedor")
						->where(["soli_auth.soli_aprobada" => FALSE, "catprov.token_cat_proveedores" => $resListProv->token_cat_proveedores])->get();

					$arrayForeach = array(
						"token_cat_proveedores" => $resListProv->token_cat_proveedores,
						"folio" => $folio_prov,
						"pais" => $resListProv->pais,
						"rfc_generico" => $rfc_generico,
						"rfc_prov" => $rfc_prov,
						"tax_id_prov" => $tax_id_prov,
						"nombre" => $nombreProv,
						"listaPrecios" => $lista_precios,
						"tiene_clave" => "",
						"encendido" => false,
						"utilizado" => $utilizado,
						"solicitudes" => count($soliValidate),
						//"auth_fecha" => date('d-m-Y H:i:s',$resListProv->authorized_fecha),
					);

					$arrayProveedores[] = $arrayForeach;
				}

				return response()->json(["status" => "success", "codigo" => 200, "listado" => $arrayProveedores]);
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

	public function requestValidacionProv(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayProveedores = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"token_proveedor" => "required|string",
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
				$token_proveedor = $parametrosArray["token_proveedor"];
				$observaciones = "permiso de prueba";

				$queryProveedor = DB::table("eegr_catalogo_proveedores AS catprov")
					->join("sos_personas AS prov","catprov.proveedor","prov.id")
					->join("teci_pais AS ps","prov.nacionalidad","ps.id")
					->join("main_empresas AS emp", "catprov.administrador","=","emp.id")
					->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
					->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
					->where([
						"catprov.token_cat_proveedores" => $token_proveedor,
						"emp.empresa_token" => $usuario->empresa_token,
						"users.usuario_token" => $usuario->user_token
					])->get();

				if (count($queryProveedor) == 1) {
					foreach ($queryProveedor as $vProv) {
						//da_te_default_timezone_set($vProv->zona_horaria);

						$folio_prov = 'PRV-TEMP-' . $JwtAuth->generarFolio($vProv->temp_folio);
						$nombre_prov = strtolower($JwtAuth->desencriptar($vProv->nombre_extendido));

						$select_id_prov = DB::select(
							"SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?",
							[$vProv->token_cat_proveedores]
						);

						$select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario->empresa_token]);

						$select_usuario = DB::select("SELECT users.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                            AND users.usuario_token = ?", [$usuario->user_token]);

						$nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);
						$folioSistema = DB::select("SELECT max(soli_auth.folio_proveedores_soli_auth) AS folio_permiso FROM eegr_catalogo_proveedores_soli_auth AS soli_auth 
                            JOIN main_empresas AS emp WHERE soli_auth.user_emp = emp.id AND emp.empresa_token = ?", [$usuario->empresa_token]);

						if (count($folioSistema) == 0) {
							$sql_folio = 1;
						} else {
							$sql_folio = end($folioSistema)->folio_permiso + 1;
						}

						$token_auth = $JwtAuth->encriptarToken(time(), end($select_empresa)->id . end($select_usuario)->id . $observaciones . time() - 500);

						$insertSoliPerm = DB::table("eegr_catalogo_proveedores_soli_auth")
							->insert(
								array(
									"token_proveedores_soli_auth" => $token_auth,
									"folio_proveedores_soli_auth" => $sql_folio,
									"fecha_proveedores_soli_auth" => time(),
									"user_emp" => end($select_empresa)->id,
									"user_user" => end($select_usuario)->id,
									"proveedor" => end($select_id_prov)->id,
									"observaciones" => $JwtAuth->encriptar($observaciones),
									"receptor" => 3,
									"solicitud_prov_status" => TRUE,
								)
							);

						if ($insertSoliPerm) {
							$tkn_user = "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4TjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMWXJpMDJObHVlL1pYSS81RCttUk5SUGx6UWl1NjEvVG1YSlR1Y1puYWk5RFk1T3d3VjFMRExZN3hOTlBxcGE0U3p1ZUM2UTRHVWp4UkFuR241aUxKbHdLU1JLZmFMeXpvK1p3WmZRemkyendCZGY1M0UwM0h2OGhyclRDMytMMnJRRENUUXB4RlRpOWpZeEVpYVR6Nis4b01VaXV0WHpVZ3JzWGg1Q3pGa3lzY0E1VGE2MzM2TjdGU1U0azMvMXFwTVM3YmJMM3p3QTdvYlAxQ3FjUDJVWlRyd09xYWJhUFBLRm1BdXpaVVpXc1Z0UUcxVWtJNDVVTjBjcE1Lb2hIRGpMT2NjY";
							$titulo_ = "Validación de proveedor";
							$mensaje_user = "El usuario ".$nombre_user." de la empresa ".end($select_empresa)->abrev_nombre." ha solicitado validación para el proveedor con el folio ".$folio_prov." y nombre ".$nombre_prov;
							$JwtAuth->notificacionPushDevices($tkn_user,$titulo_,$mensaje_user);
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

	public function validacionProcesoProveedores(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayProveedores = array();

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "required|string",
				"token_proveedor" => "required|string",
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
				$token_proveedor = $parametrosArray["token_proveedor"];
				$observaciones = "permiso de prueba";

				$queryProveedor = DB::table("eegr_catalogo_proveedores AS catprov")
				->join("sos_personas AS prov","catprov.proveedor","prov.id")
				->join("teci_pais AS ps","prov.nacionalidad","ps.id")
				->join("main_empresas AS emp","catprov.administrador","=","emp.id")
				->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
				->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
				->where([
					"catprov.token_cat_proveedores" => $token_proveedor,
					"emp.empresa_token" => $usuario->empresa_token,
					"users.usuario_token" => $usuario->user_token
				])->get();

				if (count($queryProveedor) == 1) {
					foreach ($queryProveedor as $vProv) {
						//da_te_default_timezone_set($vProv->zona_horaria);

						$select_empresa = DB::select("SELECT emp.id,people.abrev_nombre FROM sos_personas AS people JOIN main_empresas AS emp 
                            WHERE people.id = emp.persona AND emp.empresa_token = ?", [$usuario->empresa_token]);

						$select_usuario = DB::select("SELECT pers.id,people.paterno,people.materno,people.nombre FROM sos_personas AS people 
                            JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE people.id = pers.empleado_name AND pers.id = users.empleado 
                            AND users.usuario_token = ?", [$usuario->user_token]);

						$nombre_user = $JwtAuth->desencriptarNombres(end($select_usuario)->paterno, end($select_usuario)->materno, end($select_usuario)->nombre);

						$nombre_prov = strtolower($JwtAuth->desencriptar($vProv->nombre_extendido));

						$folio_prov_temp = 'PRV-TEMP-'.$JwtAuth->generarFolio($vProv->temp_folio);

						$folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder
                            FROM sos_last_folders AS fold JOIN main_empresas AS emp
                            WHERE fold.egr_proveedores = TRUE AND fold.empresa = emp.id 
                            AND emp.empresa_token = ?", [$usuario->empresa_token]);

						if (count($folioSistema) == 1) {
							if ($folioSistema[0]->folio == 1000000000) {
								$post_folio_db = DB::select("SELECT post_folio FROM catalogo_proveedores 
                                    WHERE id = (SELECT Max(catprov.id) FROM catalogo_proveedores AS catprov 
                                    JOIN main_empresas AS emp WHERE catprov.administrador = emp.id 
                                    AND emp.empresa_token = ?)", [$usuario->empresa_token]);

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

						$folio_prov = 'PRV-'.$JwtAuth->generarFolio($folio_nuevo).($post_folio != NULL ? '-'.$post_folio : '');
						
						$updateProvValid = DB::table("eegr_catalogo_proveedores")
							->where(["token_cat_proveedores" => $vProv->token_cat_proveedores])
							->limit(1)->update(
								array(
									"folio" => $folio_nuevo,
									"post_folio" => $post_folio,
									"authorized" => TRUE,
									"authorized_fecha" => time(),
									"authorized_by" => end($select_usuario)->id,
								)
							);

						if ($updateProvValid) {
							$soliValidate = DB::table("eegr_catalogo_proveedores AS catprov")
							->join("eegr_catalogo_proveedores_soli_auth AS soli_auth","catprov.id","=","soli_auth.proveedor")
							->join("teci_usuarios_catalogo AS users","soli_auth.user_user","=","users.id")
							->where(["soli_auth.soli_aprobada" => FALSE, "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();

							if (count($soliValidate) > 0) {
								$titulo_ = "Validación de proveedor";
								$mensaje_user = "El proveedor con folio temporal " . $folio_prov_temp . " y nombre " . $nombre_prov . " ha sido validado con el folio " . $folio_prov;
								foreach ($soliValidate as $mSoli) {
									$soliValidAprob = DB::table("eegr_catalogo_proveedores_soli_auth")
										->where(["token_proveedores_soli_auth" => $mSoli->token_proveedores_soli_auth])
										->limit(1)->update(array("soli_aprobada" => TRUE));

									$JwtAuth->notificacionPushDevices($mSoli->usuario_token, $titulo_, $mensaje_user);
								}
							}

							if (count($folioSistema) == 0) {
								$insertSistema = DB::table("sos_last_folders")
									->insert(array("egr_proveedores" => TRUE, "folder" => 1, "post_folder" => $post_folio, "empresa" => $select_empresa[0]->id));
							} else {
								$regFolder = DB::table("sos_last_folders AS lastf")->join("main_empresas AS emp", "lastf.empresa", "=", "emp.id")
									->where(["lastf.egr_proveedores" => TRUE, "emp.empresa_token" => $usuario->empresa_token])
									->limit(1)->update(array("lastf.folder" => $folio_nuevo, "lastf.post_folder" => $post_folio));
							}

							$dataMensaje = array(
								"status" => "success",
								"code" => 200,
								"message" => "Proveedor validado con el folio " . $folio_prov,
							);
						} else {
							$dataMensaje = array(
								"status" => "error",
								"code" => 200,
								"message" => "Validación de proveedor no registrada, intentelo nuevamente o comuniquese a soporte",
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

	public function getCatalogoProvDel(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$usuario = $JwtAuth->checkToken($parametros->user_token, true);
		$arrayProv = array();
		$listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
			->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
			->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador","=","emp.id")
			->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
			->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
			->where([
				'eegr_catalogo_proveedores.status' => FALSE,
				'emp.empresa_token' => $usuario->empresa_token,
				'users.usuario_token' => $usuario->user_token
			])->get();

		foreach ($listaProveedores as $resListprov) {
			$nombreProv = $JwtAuth->desencriptar($resListprov->nombre_extendido);

			$rfc_generico = $resListprov->rfc_generico;

			if ($resListprov->rfc != NULL) {
				$rfc_prov = $JwtAuth->desencriptar($resListprov->rfc);
			} else {
				$rfc_prov = '---';
			}

			if ($resListprov->tax_id != NULL) {
				$tax_id_prov = $JwtAuth->desencriptar($resListprov->tax_id);
			} else {
				$tax_id_prov = '---';
			}

			$fechaDelete = $resListprov->fecha_delete_prov;
			//da_te_default_timezone_set('America/Mexico_City');
			$fecha = date('d-m-Y H:i:s', $fechaDelete);

			if ($resListprov->post_folio == NULL) {
				$folio_prov = 'prv-' . $JwtAuth->generarFolio($resListprov->folio);
			} else {
				$folio_prov = 'prv-' . $JwtAuth->generarFolio($resListprov->folio) . '-' . $resListprov->post_folio;
			}

			$arrayForeach = array(
				"fecha_delete" => $fecha,
				"token_cat_proveedores" => $resListprov->token_cat_proveedores,
				//"img_perfil" => $JwtAuth->desencriptar($resListprov->img_perfil),
				"folio" => $folio_prov,
				"pais" => $resListprov->pais,
				"rfc_generico" => $rfc_generico,
				"rfc_prov" => $rfc_prov,
				"tax_id_prov" => $tax_id_prov,
				"nombre" => $nombreProv,
				//"listaPrecios" => $resListprov->listaPrecios,
			);

			$arrayProv[] = $arrayForeach;
		}

		return response()->json([
			'proveedor' => $arrayProv,
			'codigo' => 200,
			'status' => 'success'
		]);
	}

	public function verDetalleProveedor(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayProveedor = array();

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required',
				'token_proveedor' => 'required'
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Los parametro de busqueda recibidos son incorrectos',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				//QRCode::text($parametrosArray['user_token'])->setOutfile(Storage::path('public/root/QRPersonal.png'))->png();
				$token_proveedor = $parametrosArray['token_proveedor'];

				$selectProveedor = ProveedoresModelo::join("sos_personas AS perns","eegr_catalogo_proveedores.proveedor","=","perns.id")
				->join("main_empresas AS emp","eegr_catalogo_proveedores.administrador","=","emp.id")
				->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
				->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
				->where(["eegr_catalogo_proveedores.token_cat_proveedores" => $token_proveedor,"emp.empresa_token" => $usuario->empresa_token,"users.usuario_token" => $usuario->user_token])->get();
				//echo count($buscaProveedor);

				if (count($selectProveedor) == 1) {
					foreach ($selectProveedor as $vProv) {
						//da_te_default_timezone_set($vProv->zona_horaria);
            $listaUbicacion = array();
						$arrayActividad = array();

            $folio_prov = $vProv->authorized == TRUE ? "PRV-".$JwtAuth->generarFolio($vProv->folio).($vProv->post_folio != NULL ? '-'.$vProv->post_folio : "") : "PRV-TEMP-".$JwtAuth->generarFolio($vProv->temp_folio);
            $proveedor_name = $vProv->nombre_extendido == "" || $vProv->nombre_extendido == NULL ? 
              ($vProv->denominacion_rs == "" ? $JwtAuth->desencriptarNombres($vProv->paterno,$vProv->materno,$vProv->nombre) : $JwtAuth->desencriptar($vProv->denominacion_rs)) :
              $JwtAuth->desencriptar($vProv->nombre_extendido);
						$proveedor_name_header = $vProv->nombre_com != '' && $vProv->nombre_com != '-' ? $JwtAuth->desencriptar($vProv->nombre_com) : $proveedor_name;

						$proveedor_nacionalidad = $vProv->nacionalidad;
						$pais_token = $vProv->token_pais;
						$pais_name = $vProv->pais;

						$rfc_generico = $vProv->rfc_generico;
						$rfc_prov = $vProv->rfc != NULL ? $JwtAuth->desencriptar($vProv->rfc) : "---";
						$tax_id_prov = $vProv->tax_id != NULL ? $JwtAuth->desencriptar($vProv->tax_id) : "---";

						$nombre_com = $vProv->nombre_com != '' && $vProv->nombre_com != '-' ? $JwtAuth->desencriptar($vProv->nombre_com) : "";
						$sitio_web = $vProv->sitio_web != '' && $vProv->sitio_web != '-' ? $JwtAuth->desencriptar($vProv->sitio_web) : "";
						$listaPrecios = $vProv->lista_precios != '' ? $vProv->lista_precios : "";

            $regimen_fiscal_token = "";
            $regimen_fiscal_descripcion = "";
            $queryRegimenFiscal = ProveedoresModelo::join("sos_regimen_fiscal AS regf","eegr_catalogo_proveedores.regimen_fiscal","=","regf.id")
            ->where(["eegr_catalogo_proveedores.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();

            foreach ($queryRegimenFiscal as $vRegf) {
              $regimen_fiscal_token = $vRegf->token_regimen_fiscal;
              $regimen_fiscal_descripcion = $vRegf->clave."-".$vRegf->descripcion;
            }

						//contacto actual
						$personalContacto = array();
						$queryContProv = DB::table("in_egr_contacto_cliente_proveedor AS empleado")
						->join("sos_personas AS people","empleado.nombre","=","people.id")
						->join("eegr_catalogo_proveedores AS catprov","empleado.cat_proveedores","=","catprov.id")
						->where(["empleado.status" => TRUE, "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();

						if (count($queryContProv) > 0) {
							foreach ($queryContProv as $valContProv) {
								$arrayTelefono = array();
								$arrayTelefonoDeleted = array();
								$telefonoProv = DB::table("sos_personas_telefonos AS tel")
									->join("in_egr_contacto_cliente_proveedor AS empleado","tel.contacto_cliente_prov","=","empleado.id")
									->where(["tel.status_telefono" => TRUE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

								if (count($telefonoProv) > 0) {
									foreach ($telefonoProv as $valueTelPers) {
										$arrateleach = array(
											"token_telefono" => $valueTelPers->token_telefono,
											"etiqueta" => $valueTelPers->etiqueta,
                      "etiqueta_edit" => $valueTelPers->etiqueta,
                      "telefono" => $JwtAuth->desencriptar($valueTelPers->telefono),
                      "telefono_edit" => $JwtAuth->desencriptar($valueTelPers->telefono),
											"extension" => $valueTelPers->extension != NULL && $valueTelPers->extension != "" ? $JwtAuth->desencriptar($valueTelPers->extension) : "---",
                      "extension_edit" => $valueTelPers->extension != NULL && $valueTelPers->extension != "" ? $JwtAuth->desencriptar($valueTelPers->extension) : "---",
                      "preferredCountries" => [],
                      "initialValue" => "",
                      "phoneForm" => ""
										);
										$arrayTelefono[] = $arrateleach;
									}
								}

								$telefonoProvDeleted = DB::table("sos_personas_telefonos AS tel")
									->join("in_egr_contacto_cliente_proveedor AS empleado","tel.contacto_cliente_prov","=","empleado.id")
									->where(["tel.status_telefono" => FALSE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

								if (count($telefonoProvDeleted) > 0) {
									foreach ($telefonoProvDeleted as $vTelPers) {
										$arrateleach = array(
											"token_telefono" => $vTelPers->token_telefono,
											"etiqueta" => $vTelPers->etiqueta,
                      "telefono" => $JwtAuth->desencriptar($vTelPers->telefono),
                      "extension" => $vTelPers->extension != NULL && $vTelPers->extension != "" ? $JwtAuth->desencriptar($vTelPers->extension) : "---",
										);
										$arrayTelefonoDeleted[] = $arrateleach;
									}
								}

								$arrayCorreo = array();
								$arrayCorreoDel = array();

								$queryMailProv = DB::table("sos_personas_correos AS mailpers")
									->join("in_egr_contacto_cliente_proveedor AS empleado","mailpers.contacto_cliente_prov","=","empleado.id")
									->where(["mailpers.status_correo" => TRUE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

								if (count($queryMailProv) > 0) {
									foreach ($queryMailProv as $vMailPers) {
										$arrateleach = array(
											"token_correo" => $vMailPers->token_correo,
                      "correo" => $JwtAuth->desencriptar($vMailPers->correo),
                      "correo_edit" => $JwtAuth->desencriptar($vMailPers->correo)
										);
										$arrayCorreo[] = $arrateleach;
									}
								}

								$queryMailPrvD = DB::table("sos_personas_correos AS mailpers")
									->join("in_egr_contacto_cliente_proveedor AS empleado", "mailpers.contacto_cliente_prov","=","empleado.id")
									->where(["mailpers.status_correo" => FALSE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

								if (count($queryMailPrvD) > 0) {
									foreach ($queryMailPrvD as $vMailPers) {
										$arrateleach = array(
											'token_correo' => $vMailPers->token_correo,
											'correo' => $JwtAuth->desencriptar($vMailPers->correo),
											'fechaDelete' => date('d-m-Y H:i:s', $vMailPers->fecha_delete_correo),
										);
										$arrayCorreoDel[] = $arrateleach;
									}
								}

								$proveVig = array(
									"token_contacto" => $valContProv->token_contacto,
									"paterno" => $JwtAuth->desencriptar($valContProv->paterno),
									"paterno_edit" => $JwtAuth->desencriptar($valContProv->paterno),
									"materno" => $JwtAuth->desencriptar($valContProv->materno),
									"materno_edit" => $JwtAuth->desencriptar($valContProv->materno),
									"nombre" => $JwtAuth->desencriptar($valContProv->nombre),
									"nombre_edit" => $JwtAuth->desencriptar($valContProv->nombre),
									"area_contacto" => $JwtAuth->desencriptar($valContProv->area_contacto),
									"area_contacto_edit" => $JwtAuth->desencriptar($valContProv->area_contacto),
									"cargo_contacto" => $JwtAuth->desencriptar($valContProv->cargo_contacto),
									"cargo_contacto_edit" => $JwtAuth->desencriptar($valContProv->cargo_contacto),
									"telefonos" => $arrayTelefono,
									"telefonosDeleted" => $arrayTelefonoDeleted,
                  "filtro_telefonos" => null,
									"correos" => $arrayCorreo,
									"correosDeleted" => $arrayCorreoDel,
                  "filtro_correos" => null,
								);
								$personalContacto[] = $proveVig;
							}
						}

						//eliminado
						$personalContactoDel = array();
						$queryContProvDel = DB::table("in_egr_contacto_cliente_proveedor AS empleado")
							->join("sos_personas AS people", "empleado.nombre", "=", "people.id")
							->join("eegr_catalogo_proveedores AS catprov", "empleado.cat_proveedores", "=", "catprov.id")
							->where(["empleado.status" => FALSE, "catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();

						if (count($queryContProvDel) > 0) {
							foreach ($queryContProvDel as $valContProv) {
								$arrayTelefono = array();
								$arrayTelefonoDeleted = array();
								$telefonoProv = DB::table("sos_personas_telefonos AS tel")
									->join("in_egr_contacto_cliente_proveedor AS empleado", "tel.personal", "=", "empleado.id")
									->where(["tel.status_telefono" => TRUE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

								if (count($telefonoProv) > 0) {
									foreach ($telefonoProv as $valueTelPers) {
										$telExtension = '';
										if ($valueTelPers->extension != '') {
											$telExtension = $JwtAuth->desencriptar($valueTelPers->extension);
										}
										$arrateleach = array(
											'token_telefono' => $valueTelPers->token_telefono,
											'telefono' => $JwtAuth->desencriptar($valueTelPers->telefono),
											'extension' => $telExtension,
											'icono' => $valueTelPers->icono,
											'etiqueta' => $valueTelPers->etiqueta,
											'validate' => false,
										);
										$arrayTelefono[] = $arrateleach;
									}
								}

								$telefonoProvDeleted = DB::table("sos_personas_telefonos AS tel")
									->join("in_egr_contacto_cliente_proveedor AS empleado", "tel.personal", "=", "empleado.id")
									->where(["tel.status_telefono" => FALSE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

								if (count($telefonoProvDeleted) > 0) {
									foreach ($telefonoProvDeleted as $vTelPers) {
										$telExtension = '';
										if ($vTelPers->extension != '') {
											$telExtension = $JwtAuth->desencriptar($vTelPers->extension);
										}
										$arrateleach = array(
											'token_telefono' => $vTelPers->token_telefono,
											'telefono' => $JwtAuth->desencriptar($vTelPers->telefono),
											'extension' => $telExtension,
											'icono' => $vTelPers->icono,
											'etiqueta' => $vTelPers->etiqueta,
											'validate' => false,
										);
										$arrayTelefonoDeleted[] = $arrateleach;
									}
								}

								$arrayCorreo = array();
								$arrayCorreoDel = array();

								$queryMailProv = DB::table("sos_personas_correos AS mailpers")
									->join("in_egr_contacto_cliente_proveedor AS empleado", "mailpers.empleado_name", "=", "empleado.id")
									->where(["mailpers.status_correo" => TRUE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

								if (count($queryMailProv) > 0) {
									foreach ($queryMailProv as $valueMailPers) {
										$arrateleach = array(
											'token_correo' => $valueMailPers->token_correo,
											'correo' => $JwtAuth->desencriptar($valueMailPers->correo)
										);
										$arrayCorreo[] = $arrateleach;
									}
								}

								$queryMailPrvD = DB::table("sos_personas_correos AS mailpers")
									->join("in_egr_contacto_cliente_proveedor AS empleado", "mailpers.empleado_name", "=", "empleado.id")
									->where(["mailpers.status_correo" => FALSE, "empleado.token_contacto" => $valContProv->token_contacto])->get();

								if (count($queryMailPrvD) > 0) {
									foreach ($queryMailPrvD as $vMailPers) {
										$arrateleach = array(
											'token_correo' => $vMailPers->token_correo,
											'correo' => $JwtAuth->desencriptar($vMailPers->correo),
											'fechaDelete' => date('d-m-Y H:i:s', $vMailPers->fecha_delete_correo),
										);
										$arrayCorreoDel[] = $arrateleach;
									}
								}

								$proveVig = array(
									"token_contacto" => $valContProv->token_contacto,
									"paterno" => strtolower($JwtAuth->desencriptar($valContProv->paterno)),
									"materno" => strtolower($JwtAuth->desencriptar($valContProv->materno)),
									"nombre" => strtolower($JwtAuth->desencriptar($valContProv->nombre)),
									"areaemp" => strtolower($JwtAuth->desencriptar($valContProv->areaemp)),
									"cargo" => strtolower($JwtAuth->desencriptar($valContProv->cargo)),
									"telefono" => $arrayTelefono,
									"telefonoDeleted" => $arrayTelefonoDeleted,
									"correo" => $arrayCorreo,
									"arrayCorreoDel" => $arrayCorreoDel
								);
								$personalContactoDel[] = $proveVig;
							}
						}

						$docs_adjuntos = array();
						if ($vProv->tiene_docs_fiscales == TRUE) {
							//echo $JwtAuth->desencriptar("VEpIMk44MlAwK2NXNy9mSVF4bzI0RjNwQnFPdGRaRVAyUWtnQ05TWGUxdXFMakFYOU4wWnltNGxLempiQWsrejo6MTIzNDU2NzgxMjM0NTY3OA==");
							$selectSitFisDoc = DB::table("sos_documentos AS docs")
								->join("eegr_catalogo_proveedores AS catprov", "docs.proveedor", "=", "catprov.id")
								->where([
									"docs.tipo_documento" => "fcsf",
									"docs.status_documento" => TRUE,
									"catprov.token_cat_proveedores" => $vProv->token_cat_proveedores
								])->get();
							if (count($selectSitFisDoc) > 0) {
								foreach ($selectSitFisDoc as $vDoc) {
                  $fcsf = "Constancia de situación fiscal";
                  $cuof = "Constancia de cumplimiento de obligaciones fiscales";
                  $fcnt = "Contratos";
                  $anex = "Anexos";
                  $ecue = "Estado de cuenta";

                  $url_fcsf = "https://downloads.sos-mexico.com.mx/proveedores_sit_fiscal_by_token/";
                  $url_cuof = "https://downloads.sos-mexico.com.mx/proveedores_opinion_cumplimiento_by_token/";
                  $url_fcnt = "https://downloads.sos-mexico.com.mx/proveedores_contratos_by_token/";
                  $url_anex = "https://downloads.sos-mexico.com.mx/proveedores_anexos_by_token/";
                  $url_ecue = "https://downloads.sos-mexico.com.mx/proveedores_estado_de_cuenta_by_token/";

                  $rowDocs = array(
                      "token_documento" => $vDoc->token_documento,
                      "tipo_documento" => $vDoc->tipo_documento == "fcsf" ? $fcsf : ($vDoc->tipo_documento == "cuof" ? $cuof : ($vDoc->tipo_documento == "fcnt" ? $fcnt : ($vDoc->tipo_documento == "anex" ? $anex : $ecue))), 
                      "ext_doc" => $vDoc->extension_documento,
                      "name_documento" => $JwtAuth->desencriptar($vDoc->nombre_documento),	
                      "url" => $vDoc->tipo_documento == "fcsf" ? $url_fcsf : ($vDoc->tipo_documento == "cuof" ? $url_cuof : ($vDoc->tipo_documento == "fcnt" ? $url_fcnt : ($vDoc->tipo_documento == "anex" ? $url_anex : $url_ecue))).$vDoc->token_documento,
                  );
                  $docs_adjuntos[] = $rowDocs;
								}
							}
						}

						$no_cuenta_fiscales = $vProv->no_cuenta_fiscales != NULL && $vProv->no_cuenta_fiscales != '' ? $JwtAuth->desencriptar($vProv->no_cuenta_fiscales) : "-";

						//creditos
						$token_moneda = "";
						$arregloCreditos = array();
						$credProveedor = DB::table("eegr_catalogo_proveedores AS catprv")
							->join("in_egr_creditos AS cred", "catprv.id", "=", "cred.proveedor")
							->where(["catprv.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();


            $creditos_token = "";
            $creditos_acepta = false;
            $creditos_moneda_code = "";
            $creditos_moneda_decimales = "";
            $creditos_limite = "$".number_format(0,2, '.', ',');
            $creditos_dias = "";
            $creditos_fechalimite = "";
            $creditos_comienza = "";
						if (count($credProveedor) > 0) {
							foreach ($credProveedor as $vCred) {
                $creditos_token = $vCred->token_creditos;
                $creditos_acepta = $vCred->aceptacredito == TRUE ? true : false;
                $creditos_moneda_code = $vCred->aceptacredito == TRUE ? $vCred->moneda_code : '';
                $creditos_moneda_decimales = $vCred->aceptacredito == TRUE ? $vCred->moneda_decimales : '';
                $creditos_limite = $vCred->aceptacredito == TRUE ? $vCred->limite : '';
                $creditos_dias = $vCred->aceptacredito == TRUE ? $vCred->dias : '';
                $creditos_fechalimite = $vCred->aceptacredito == TRUE ? date("d-m-Y", time() + (86400 * $vCred->dias)) : '';
                $creditos_comienza = $vCred->aceptacredito == TRUE ? $vCred->comienza : '';

								$comienza_credito_text = "---";
								if ($vCred->aceptacredito == TRUE && $vCred->comienza == "cada.inicio.mes") $comienza_credito_text = "Cada inicio de mes";
								if ($vCred->aceptacredito == TRUE && $vCred->comienza == "sistem.emite.orden.pago") $comienza_credito_text = "Se emite/envía orden de pago";
								if ($vCred->aceptacredito == TRUE && $vCred->comienza == "serecibe.facturadel.proveedor") $comienza_credito_text = "Se recibe factura del proveedor";
								if ($vCred->aceptacredito == TRUE && $vCred->comienza == "producto.sale.bodegas.proveedor") $comienza_credito_text = "El producto salga de las bodegas del proveedor";
								if ($vCred->aceptacredito == TRUE && $vCred->comienza == "producto.recibido.nuestras.bodegas") $comienza_credito_text = "El producto es recibido en nuestras bodegas";
								$class_disabled = $vCred->aceptacredito == FALSE ? "disabledContentWhite" : ""; 

								$creditosEach = array(
									"token_creditos" => $vCred->token_creditos,
									"acepta" => $vCred->aceptacredito == TRUE ? true : false,
									"moneda_code" => $vCred->aceptacredito == TRUE ? $vCred->moneda_code : '',
									"moneda_decimales" => $vCred->aceptacredito == TRUE ? $vCred->moneda_decimales : '',
									"limite" => $vCred->aceptacredito == TRUE ? $vCred->limite : "---",
									"dias" => $vCred->aceptacredito == TRUE ? $vCred->dias : "---",
									"fechalimite" => $vCred->aceptacredito == TRUE ? date("d-m-Y", time() + (86400 * $vCred->dias)) : '',
									"comienza" => $vCred->aceptacredito == TRUE ? $vCred->comienza : '',
									"comienza_credito_text" => $comienza_credito_text,
									"class_disabled" => $class_disabled,
								);
								$arregloCreditos[] = $creditosEach;
							}
						}

						$arrayMonedas = array();
						$catMonedas = MonedasModelo::get();
						foreach ($catMonedas as $valMonedas) {
							$arrayMon = array(
								"token_monedas" => $valMonedas->token_monedas,
								"codigo" => $valMonedas->codigo,
								"moneda" => $valMonedas->moneda,
								"decimales" => $valMonedas->decimales,
								"selected" => $valMonedas->token_monedas == $token_moneda ? true : false,
							);
							$arrayMonedas[] = $arrayMon;
						}

						//forma de pago
            $formaPagoTiene = false;
            $formaPagoToken = "";
            $formaPagoClave = "";
            $formaPagoConcepto = "";
            $formaPagoTipoReferencia = "";
            $formaPagoInsideClass = "";
            if (isset($vProv->forma_pago)) {
              $tieneformaPago = true;
              $queryFPagoProv = ProveedoresModelo::join("teci_forma_pago AS pfor","eegr_catalogo_proveedores.forma_pago","=","pfor.id")
              ->where(["eegr_catalogo_proveedores.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();  
              foreach ($queryFPagoProv as $vFPbP) {
                  $formaPagoToken = $vFPbP->token_formapago;
                  $formaPagoClave = $vFPbP->clave;
                  $formaPagoConcepto = $vFPbP->forma;
                  $formaPagoTipoReferencia = $vProv->tipo_referencia_pago != NULL ? ($vProv->tipo_referencia_pago == "ci" ? "clabeInterbancaria" : ($vProv->tipo_referencia_pago == "co" ? "convenio" : "lineaCaptura")) : "";
                  $formaPagoInsideClass = $vProv->token_formapago == "RkxGMTRidG44ZWJJYVh0dUlDK1o4Zz09OjoxMjM0NTY3ODEyMzQ1Njc4" ? "transferencia" : "noneView";
              }
            }

						//ubicacion
						$arrayUbicacion = array();
						$arrayUbicacionDel = array();

						$listaUbicacion = DB::table("eegr_catalogo_proveedores AS catprov")
							->join("teci_direcciones AS ubica", "catprov.id", "ubica.proveedor")
							->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
							->where(["catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();
						if (count($listaUbicacion) > 0) {
							foreach ($listaUbicacion as $vUbica) {
								//echo $vUbica->pais;
								$status_ubica = DB::table("teci_direcciones")->where("token_direccion",$vUbica->token_direccion)->value("status");

								$new_direccion_estado = $vUbica->estado_edit != NULL ? $JwtAuth->desencriptar($vUbica->estado_edit) : "";
								$new_direccion_municipio = $vUbica->municipio_edit != NULL ? $JwtAuth->desencriptar($vUbica->municipio_edit) : "";
								$new_direccion_c_postal = $vUbica->c_postal_edit != NULL ? $vUbica->c_postal_edit : "";
								$new_direccion_colonia = $vUbica->colonia_edit != NULL ? $JwtAuth->desencriptar($vUbica->colonia_edit) : "";
								$new_direccion_adicional = $vUbica->adicional != NULL ? $vUbica->adicional : "";
								if ($vProv->nacionalidad == 118) {
									$eachUbicacion = array(
										"token_direccion" => $vUbica->token_direccion,
										"tipo_direccion" => $vUbica->tipo_direccion,
										"clase" => $JwtAuth->desencriptar($vUbica->clase),
										"pais" => 118,
										"estado_main" => $JwtAuth->desencriptar($vUbica->estado_edit),
										"estado_edit" => $JwtAuth->desencriptar($vUbica->estado_edit),
										"municipio_main" => $JwtAuth->desencriptar($vUbica->municipio_edit),
										"municipio_edit" => $JwtAuth->desencriptar($vUbica->municipio_edit),
										"c_postal_main" => $vUbica->c_postal_edit,
										"c_postal_edit" => $vUbica->c_postal_edit,
										"colonia_main" => $JwtAuth->desencriptar($vUbica->colonia_edit),
										"colonia_edit" => $JwtAuth->desencriptar($vUbica->colonia_edit),
									);
								} else {
									$eachUbicacion = array(
										"token_direccion" => $vUbica->token_direccion,
										"tipo_direccion" => $vUbica->tipo_direccion,
										"clase" => $JwtAuth->desencriptar($vUbica->clase),
										"pais" => $vUbica->pais,
										"cod_postalext" => $JwtAuth->desencriptar($vUbica->cod_postalext),
									);
								}
								//if ($status_ubica == TRUE) {
								//	$arrayUbicacion[] = $eachUbicacion;
								//} else {
								//	$arrayUbicacionDel[] = $eachUbicacion;
								//}
								$status_ubica == TRUE ? $arrayUbicacion[] = $eachUbicacion : $arrayUbicacionDel[] = $eachUbicacion;
							}
						}

						//cuentas contables 
						$arrayCuentasCont = array();
						$getCuentasContables = DB::table("cont_catalogo_cuentas_contables AS cuent")
							->join("eegr_catalogo_proveedores AS catprov", "cuent.proveedor", "=", "catprov.id")
							->where(["catprov.token_cat_proveedores" => $vProv->token_cat_proveedores])->get();
						if (count($getCuentasContables) > 0) {
							foreach ($getCuentasContables as $valContables) {
								$alist = array(
									"token_cuenta_contable" => $valContables->token_cuenta_contable,
									"contable" => $valContables->contable,
								);
								$arrayCuentasCont[] = $alist;
							}
						}

						$select_bitacora = DB::select("SELECT bit_prv.*,people.paterno,people.materno,people.nombre FROM eegr_catalogo_proveedores_bitacora AS bit_prv JOIN eegr_catalogo_proveedores AS catprov 
              JOIN main_empresas AS emp JOIN teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people WHERE bit_prv.proveedor = catprov.id 
							AND catprov.token_cat_proveedores = ? AND bit_prv.empresa = emp.id AND emp.empresa_token = ? AND bit_prv.usuario = users.id 
              AND users.usuario_token = ? AND users.empleado = pers.id AND pers.empleado_name = people.id", [$vProv->token_cat_proveedores, $usuario->empresa_token, $usuario->user_token]);

						foreach ($select_bitacora as $valBit) {
							$aeachFor = array(
								"token_bitacora" => $valBit->token_bitacora_prov,
								"fecha_bitacora" => date('d-m-Y H:i:s', $valBit->fecha_bitacora_prov),
								"folio_bitacora" => $JwtAuth->generarFolio($valBit->folio_bitacora_prov),
								"actividad" => $valBit->actividad,
								"usuario_relacionado" => $JwtAuth->desencriptarNombres($valBit->paterno, $valBit->materno, $valBit->nombre),
							);
							$arrayActividad[] = $aeachFor;
						}

						$pfmx = "Persona física (México)";
						$pfext = "Persona física Extranjero";
						$pmmx = "Persona moral (México)";
						$pmext = "Persona moral Extranjero";
            $clasificacion = $vProv->nacionalidad == 118 ? "nacional" : "extranjero"; 
						$subClasificacionAll = $vProv->nacionalidad == 118 ? ($vProv->subClase == "PM" ? $pmmx : $pfmx) : ($vProv->subClase == "PM" ? $pmext : $pfext);
						$proveedor_identif = $vProv->rfc != NULL ? $JwtAuth->desencriptar($vProv->rfc) : ($vProv->tax_id != NULL ? $JwtAuth->desencriptar($vProv->tax_id) : $vProv->rfc_generico);

						$arrayForeachProv = array(
							"token_proveedor" => $vProv->token_cat_proveedores,
							"folio_proveedor" => $folio_prov,
							"rfc_generico" => $rfc_generico,
							"nombre_proveedor_header" => $proveedor_name_header,
							"nombre_proveedor" => $proveedor_name,
              "nombre_proveedor_edit" => $proveedor_name,
							"rfc_prov" => $rfc_prov,
              "rfc_prov_edit" => $rfc_prov,
							"tax_id_prov" => $tax_id_prov,
              "tax_id_prov_edit" => $tax_id_prov,
              "clasificacion" => $clasificacion,
              "subClasificacionSimple" => $vProv->subClase,
              "subClasificacionAll" => $subClasificacionAll,
							"identificador" => $proveedor_identif,
							"nationality" => $proveedor_nacionalidad,
							"pais_ident" => $pais_token,
							"pais_name" => $pais_name,
							"nombre_comercial" => $nombre_com,
              "nombre_comercial_edit" => $nombre_com,
							"sitio_web" => $sitio_web,
							"sitio_web_edit" => $sitio_web,
              "regimen_fiscal_token" => $regimen_fiscal_token,
              "regimen_fiscal_token_edit" => $regimen_fiscal_token,
              "regimen_fiscal_descripcion" => $regimen_fiscal_descripcion,
							"lista_precios" => $listaPrecios,
              "tiene_contacto_registrado" => count($personalContacto) > 0 ? true : false,
              "tiene_contacto_registrado_edit" => count($personalContacto) > 0 ? true : false,
							"contacto_registrado" => $personalContacto,
							"contacto_deleted" => $personalContactoDel,
							"tiene_docs_fiscales" => $vProv->tiene_docs_fiscales == TRUE ? true : false,
              "docs_adjuntos" => $docs_adjuntos,
							//"docs_sit_fiscal" => $docs_sit_fiscal,
							//"docs_obl_fiscal" => $docs_obl_fiscal,
							//"docs_contratos" => $docs_contratos,
							//"docs_anexos" => $docs_anexos,
							"noCargaDocsFiscalesRazon" => $no_cuenta_fiscales,

              "tieneCreditoAsignado" => $creditos_token != "" ? true : false,
              "tieneCreditoAsignado_edit" => $creditos_token != "" ? true : false,
							"creditos" => $arregloCreditos,
              "creditos_token_creditos" => $creditos_token,
              "creditos_acepta" => $creditos_acepta,
              "creditos_acepta_edit" => $creditos_acepta,
              "creditos_moneda_code" => $creditos_moneda_code,
              "creditos_moneda_code_edit" => $creditos_moneda_code,
              "creditos_moneda_decimales" => $creditos_moneda_decimales,
              "creditos_moneda_decimales_edit" => $creditos_moneda_decimales,
              "creditos_limite" => $creditos_limite,
              "creditos_limite_edit" => $creditos_limite,
              "creditos_dias" => $creditos_dias,
              "creditos_dias_edit" => $creditos_dias,
              "creditos_fechalimite" => $creditos_fechalimite,
              "creditos_comienza" => $creditos_comienza,
              "creditos_comienza_edit" => $creditos_comienza,
							//"arrayMonedas" => $arrayMonedas,

              "forma_pago_tiene" => $formaPagoTiene,
							"forma_pago_tiene_edit" => $formaPagoTiene,
              "forma_pago_token" => $formaPagoToken,
              "forma_pago_token_edit" => $formaPagoToken,
              "forma_pago_clave" => $formaPagoClave,
              "forma_pago_concepto" => $formaPagoConcepto,
              "forma_pago_tipo_referencia" => $formaPagoTipoReferencia,
              "forma_pago_inside_class" => $formaPagoInsideClass,

							"receptFacturaConcept" => $vProv->receptFactura == TRUE ? "Antes" : "Despues",
							"receptFacturaBool" => $vProv->receptFactura == TRUE ? true : false,
							"classRecibeArtPagoConcept" => $vProv->classRecibeArtPago == TRUE ? "Antes" : "Despues",
							"classRecibeArtPagoBool" => $vProv->classRecibeArtPago == TRUE ? true : false,
							"arrayUbicacion" => $arrayUbicacion,
							"arrayUbicacionDel" => $arrayUbicacionDel,
							"arrayCuentasCont" => $arrayCuentasCont,
							"bitacora" => $arrayActividad,
						);
						$arrayProveedor[] = $arrayForeachProv;
					}
					$dataMensaje = array(
						'proveedor' => $arrayProveedor,
						'code' => 200,
						'status' => 'success'
					);
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
				'message' => 'Los parametro de busqueda recibidos son inexistentes'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function verDetalleProveedor_(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
		$arrayProveedor = array();
		$proveedor_name_edit = array();

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required',
				'provdatta' => 'required'
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Los parametro de busqueda recibidos son incorrectos',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				QRCode::text($parametrosArray['user_token'])->setOutfile(Storage::path('public/root/QRPersonal.png'))->png();

				$proveedor = $parametrosArray['provdatta'];

				$buscaProveedor = ProveedoresModelo::join("sos_personas AS perns", "eegr_catalogo_proveedores.proveedor", "=", "perns.id")
					->join("teci_pais AS country", "perns.nacionalidad", "=", "country.id")
					->join("teci_forma_pago AS fpago", "eegr_catalogo_proveedores.forma_pago", "=", "fpago.id")
					->join("in_egr_creditos AS cred", "eegr_catalogo_proveedores.id", "=", "cred.proveedor")
					->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
					->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
					->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
					->where([
						'eegr_catalogo_proveedores.token_cat_proveedores' => $proveedor,
						'emp.empresa_token' => $usuario->empresa_token,
						'users.usuario_token' => $usuario->user_token,
					])->get();
				//echo count($buscaProveedor);

				if (count($buscaProveedor) == 1) {
					foreach ($buscaProveedor as $valViewProv) {
						//da_te_default_timezone_set($valViewProv->zona_horaria);
						$arrayActividad = array();
						$select_bitacora = DB::select(
							"SELECT bit_prv.*,
              people.paterno,people.materno,people.nombre 
              FROM eegr_catalogo_proveedores_bitacora AS bit_prv
              JOIN eegr_catalogo_proveedores AS catprov
              JOIN main_empresas AS emp JOIN teci_usuarios_catalogo AS users JOIN vhum_empleados_catalogo AS pers JOIN sos_personas AS people
              WHERE bit_prv.proveedor = catprov.id AND catprov.token_cat_proveedores = ?
              AND bit_prv.empresa = emp.id AND emp.empresa_token = ? AND bit_prv.usuario = users.id 
              AND users.usuario_token = ? AND users.empleado = pers.id AND pers.empleado_name = people.id",
							[$proveedor, $usuario->empresa_token, $usuario->user_token,]
						);

						foreach ($select_bitacora as $valBit) {
							/*bit_prv.token_bitacora_prov,
                            bit_prv.folio_bitacora_prov,
                            bit_prv.fecha_bitacora_prov,
                            bit_prv.actividad,*/
							$aeachFor = array(
								"token_bitacora" => $valBit->token_bitacora_prov,
								"fecha_bitacora" => date('d-m-Y H:i:s', $valBit->fecha_bitacora_prov),
								"folio_bitacora" => $JwtAuth->generarFolio($valBit->folio_bitacora_prov),
								"actividad" => $valBit->actividad,
								"usuario_relacionado" => $JwtAuth->desencriptar($valBit->paterno) . ' ' .
									$JwtAuth->desencriptar($valBit->materno) . ' ' . $JwtAuth->desencriptar($valBit->nombre),
							);
							$arrayActividad[] = $aeachFor;
						}

						//cuentas contables 
						$getCuentasContables = CuentasContablesModelo::join("eegr_catalogo_proveedores AS catprov", "cont_catalogo_cuentas_contables.proveedor", "=", "catprov.id")
						->join("main_empresas AS emp","catprov.administrador", "=", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
						->where([
							'catprov.token_cat_proveedores' => $valViewProv->token_cat_proveedores,
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])->get();
						$arrayCuentasCont = array();
						if (count($getCuentasContables) >= 1) {
							foreach ($getCuentasContables as $valContables) {
								$alist = array(
									"token_cuenta_contable" => $valContables->token_cuenta_contable,
									"contable" => $valContables->contable,
								);
								$arrayCuentasCont[] = $alist;
							}
						}

						//informacion general del proveedor
						//$arrayRedes = array();

						$textNombreProv = strtolower($JwtAuth->desencriptar($valViewProv->nombre_extendido));
						$proveedor_name_edit[0] = strtolower($JwtAuth->desencriptar($valViewProv->nombre_extendido));

						$rfc_generico = $valViewProv->rfc_generico;

						if ($valViewProv->rfc != NULL) {
							$rfc_prov = $JwtAuth->desencriptar($valViewProv->rfc);
						} else {
							$rfc_prov = '---';
						}

						if ($valViewProv->tax_id != NULL) {
							$tax_id_prov = $JwtAuth->desencriptar($valViewProv->tax_id);
						} else {
							$tax_id_prov = '---';
						}

						if ($valViewProv->nombre_com == '' || $valViewProv->nombre_com == '-') {
							$nombre_com = '';
						} else {
							$nombre_com = $JwtAuth->desencriptar($valViewProv->nombre_com);
						}

						if ($valViewProv->sitio_web == '' || $valViewProv->sitio_web == '-') {
							$sitio_web = '';
						} else {
							$sitio_web = $JwtAuth->desencriptar($valViewProv->sitio_web);
						}

						if ($valViewProv->redes_soc != '' && $valViewProv->redes_soc != NULL) {
							$arrayRedes = array();
							$listaRedes = json_decode($JwtAuth->desencriptar($valViewProv->redes_soc));
							for ($r = 0; $r < count($listaRedes); $r++) {
								$arrayRedes[$r] = $listaRedes[$r];
							}
						} else {
							$arrayRedes = array('', '', '', '');
						}

						//documentos
						$rutaDocsProv = $valViewProv->root_tkn . '/0002-cpp/catalogos/proveedores/' . $JwtAuth->generar($valViewProv->folio) . '-' . $valViewProv->fechaAlta . '/';

						if ($valViewProv->const_sit_fiscal == '' || $valViewProv->const_sit_fiscal == NULL) {
							$const_sit_fiscal = '-';
							$nameConst_sit_fiscal = '-';
						} else {
							if (file_exists(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->const_sit_fiscal)))) {
								$extensionFiscal = pathinfo(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->const_sit_fiscal)), PATHINFO_EXTENSION);
								$nameConst_sit_fiscal = $JwtAuth->desencriptar($valViewProv->const_sit_fiscal);
								if ($extensionFiscal == 'pdf') {
									$fiscalB64 = $JwtAuth->encriptaBase64Pdf(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->const_sit_fiscal)));
									$const_sit_fiscal = '<iframe src="' . $fiscalB64 . '" width="100%" height="400px"></iframe>';
								}

								if ($extensionFiscal == 'jpg') {
									$fiscalB64 = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->const_sit_fiscal)));
									$const_sit_fiscal = '<img class="responsive-img circle materialboxed imag2" src="' . $fiscalB64 . '">';
								}

								if ($extensionFiscal == 'png') {
									$fiscalB64 = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->const_sit_fiscal)));
									$const_sit_fiscal = '<img class="responsive-img circle materialboxed imag2" src="' . $fiscalB64 . '">';
								}
							} else {
								$const_sit_fiscal = '-';
								$nameConst_sit_fiscal = '-';
							}
						}

						if ($valViewProv->opinion_cumplimiento == '' || $valViewProv->opinion_cumplimiento == NULL) {
							$doc_opinion_cumplimiento = '-';
							$nameDoc_opinion_cumplimiento = '-';
						} else {
							if (file_exists(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->opinion_cumplimiento)))) {
								$extensionFiscal = pathinfo(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->opinion_cumplimiento)), PATHINFO_EXTENSION);
								$nameDoc_opinion_cumplimiento = $JwtAuth->desencriptar($valViewProv->opinion_cumplimiento);
								if ($extensionFiscal == 'pdf') {
									$fiscalB64 = $JwtAuth->encriptaBase64Pdf(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->opinion_cumplimiento)));
									$doc_opinion_cumplimiento = '<iframe src="' . $fiscalB64 . '" width="100%" height="400px"></iframe>';
								}

								if ($extensionFiscal == 'jpg') {
									$fiscalB64 = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->opinion_cumplimiento)));
									$doc_opinion_cumplimiento = '<img class="responsive-img circle materialboxed imag2" src="' . $fiscalB64 . '">';
								}

								if ($extensionFiscal == 'png') {
									$fiscalB64 = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->opinion_cumplimiento)));
									$doc_opinion_cumplimiento = '<img class="responsive-img circle materialboxed imag2" src="' . $fiscalB64 . '">';
								}
							} else {
								$doc_opinion_cumplimiento = '-';
								$nameDoc_opinion_cumplimiento = '-';
							}
						}

						if ($valViewProv->no_cuenta_fiscales != NULL && $valViewProv->no_cuenta_fiscales != '') {
							$no_cuenta_fiscales = $JwtAuth->desencriptar($valViewProv->no_cuenta_fiscales);
						} else {
							$no_cuenta_fiscales = '-';
						}

						//contacto
						//vigentes 
						$personalContacto = array();

						$queryContProv = DB::select("SELECT empleado.token_contacto,areapers.areaemp,cargopers.cargo,people.paterno,people.materno,people.nombre
						    FROM in_egr_contacto_cliente_proveedor AS empleado JOIN vhum_empleados_catalogo_area AS areapers JOIN vhum_empleados_catalogo_cargo AS cargopers 
						    JOIN sos_personas AS people JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
						    JOIN teci_usuarios_catalogo AS users WHERE empleado.area = areapers.id AND empleado.cargo = cargopers.id AND empleado.nombre = people.id 
						    AND empleado.status = TRUE AND empleado.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id 
						    AND emp.id = empuser.empresa AND emp.empresa_token = ? AND empuser.usuario = users.id AND users.usuario_token = ?", 
						    [$proveedor, $usuario->empresa_token, $usuario->user_token]);

						if (count($queryContProv) > 0) {
							foreach ($queryContProv as $valContProv) {
								$arrayTelefono = array();
								$arrayTelefonoDeleted = array();
								$telefonoProv = DB::select("SELECT tel.token_telefono,tel.icono,tel.etiqueta,tel.telefono,tel.extension
										    FROM sos_personas_telefonos AS tel JOIN in_egr_contacto_cliente_proveedor AS empleado WHERE tel.status_telefono = TRUE 
										    AND tel.personal = empleado.id AND empleado.token_contacto = ?", [$valContProv->token_contacto]);
								if (count($telefonoProv) > 0) {
									foreach ($telefonoProv as $valueTelPers) {
										$telExtension = '';
										if ($valueTelPers->extension != '') {
											$telExtension = $JwtAuth->desencriptar($valueTelPers->extension);
										}
										$arrateleach = array(
											'token_telefono' => $valueTelPers->token_telefono,
											'telefono' => $JwtAuth->desencriptar($valueTelPers->telefono),
											'extension' => $telExtension,
											'icono' => $valueTelPers->icono,
											'etiqueta' => $valueTelPers->etiqueta,
											'validate' => false,
										);
										$arrayTelefono[] = $arrateleach;
									}
								}

								$telefonoProvDel = DB::select("SELECT tel.token_telefono,tel.icono,tel.etiqueta,tel.telefono,tel.extension,tel.fecha_delete_tel
										    FROM sos_personas_telefonos AS tel JOIN in_egr_contacto_cliente_proveedor AS empleado WHERE tel.status_telefono = FALSE 
										    AND tel.personal = empleado.id AND empleado.token_contacto = ?", [$valContProv->token_contacto]);
								if (count($telefonoProvDel) > 0) {
									foreach ($telefonoProvDel as $valueTelPers) {
										$telExtension = '';
										if ($valueTelPers->extension != '') {
											$telExtension = $JwtAuth->desencriptar($valueTelPers->extension);
										}
										$arrateleach = array(
											'token_telefono' => $valueTelPers->token_telefono,
											'telefono' => $JwtAuth->desencriptar($valueTelPers->telefono),
											'extension' => $telExtension,
											'icono' => $valueTelPers->icono,
											'etiqueta' => $valueTelPers->etiqueta,
											'fechaDelete' => date('d-m-Y H:i:s', $valueTelPers->fecha_delete_tel),
										);
										$arrayTelefonoDeleted[] = $arrateleach;
									}
								}

								$arrayCorreo = array();
								$arrayCorreoDel = array();

								$queryContProv = DB::select("SELECT mailpers.token_correo,mailpers.correo
										FROM sos_personas_correos AS mailpers JOIN in_egr_contacto_cliente_proveedor AS empleado WHERE mailpers.status_correo = TRUE AND mailpers.empleado_name = empleado.id 
										AND empleado.token_contacto = ?", [$valContProv->token_contacto]);

								if (count($queryContProv) > 0) {
									foreach ($queryContProv as $valueMailPers) {
										$arrateleach = array(
											'token_correo' => $valueMailPers->token_correo,
											'correo' => $JwtAuth->desencriptar($valueMailPers->correo)
										);
										$arrayCorreo[] = $arrateleach;
									}
								}

								$queryContProv = DB::select("SELECT mailpers.token_correo,mailpers.correo,mailpers.fecha_delete_correo
										FROM sos_personas_correos AS mailpers JOIN in_egr_contacto_cliente_proveedor AS empleado WHERE mailpers.status_correo = FALSE AND mailpers.empleado_name = empleado.id 
										AND empleado.token_contacto = ?", [$valContProv->token_contacto]);

								if (count($queryContProv) > 0) {
									foreach ($queryContProv as $valueMailPers) {
										$arrateleach = array(
											'token_correo' => $valueMailPers->token_correo,
											'correo' => $JwtAuth->desencriptar($valueMailPers->correo),
											'fechaDelete' => date('d-m-Y H:i:s', $valueMailPers->fecha_delete_correo),
										);
										$arrayCorreoDel[] = $arrateleach;
									}
								}

								$proveVig = array(
									"token_contacto" => $valContProv->token_contacto,
									"paterno" => strtoupper($JwtAuth->desencriptar($valContProv->paterno)),
									"materno" => strtoupper($JwtAuth->desencriptar($valContProv->materno)),
									"nombre" => strtoupper($JwtAuth->desencriptar($valContProv->nombre)),
									"areaemp" => strtolower($JwtAuth->desencriptar($valContProv->areaemp)),
									"cargo" => strtolower($JwtAuth->desencriptar($valContProv->cargo)),
									"telefono" => $arrayTelefono,
									"telefonoDeleted" => $arrayTelefonoDeleted,
									"correo" => $arrayCorreo,
									"arrayCorreoDel" => $arrayCorreoDel
								);
								$personalContacto[] = $proveVig;
							}
						}

						//eliminado
						$personalContactoDel = array();

						$queryContProvDel = DB::select("SELECT empleado.token_contacto,areapers.areaemp,cargopers.cargo,people.paterno,people.materno,people.nombre,empleado.fecha_delete
							FROM in_egr_contacto_cliente_proveedor AS empleado JOIN vhum_empleados_catalogo_area AS areapers JOIN vhum_empleados_catalogo_cargo AS cargopers 
							JOIN sos_personas AS people JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
							JOIN teci_usuarios_catalogo AS users WHERE empleado.area = areapers.id AND empleado.cargo = cargopers.id AND empleado.nombre = people.id AND empleado.status = FALSE 
							AND empleado.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.id = empuser.empresa
							AND emp.empresa_token = ? AND empuser.usuario = users.id AND users.usuario_token = ?", [$proveedor, $usuario->empresa_token, $usuario->user_token]);

						if (count($queryContProvDel) > 0) {
							foreach ($queryContProvDel as $valContProvDel) {
								$fecha_delete_pers = date('d-m-Y H:i:s', $valContProvDel->fecha_delete);

								$arrayCorreo = array();

								$queryContProv = DB::select("SELECT mailpers.token_correo,mailpers.correo
										FROM correos_personal AS mailpers JOIN in_egr_contacto_cliente_proveedor AS empleado WHERE mailpers.empleado_name = empleado.id 
										AND empleado.token_contacto = ?", [$valContProvDel->token_contacto]);

								if (count($queryContProv) > 0) {
									foreach ($queryContProv as $valueMailPers) {
										$arrateleach = array(
											'token_correo' => $valueMailPers->token_correo,
											'correo' => $JwtAuth->desencriptar($valueMailPers->correo)
										);
										$arrayCorreo[] = $arrateleach;
									}
								}

								$arrayTelefono = array();
								$telefonoProv = DB::select("SELECT tel.token_telefono,tel.icono,tel.etiqueta,
										tel.telefono,tel.extension FROM telefonos_personal AS tel 
										JOIN in_egr_contacto_cliente_proveedor AS empleado WHERE tel.personal = empleado.id
										AND empleado.token_contacto = ?", [$valContProvDel->token_contacto]);

								if (count($telefonoProv) > 0) {
									foreach ($telefonoProv as $valueTelPers) {
										$telExtension = '';
										if ($valueTelPers->extension != '') {
											$telExtension = $JwtAuth->desencriptar($valueTelPers->extension);
										}
										$arrateleach = array(
											'token_telefono' => $valueTelPers->token_telefono,
											'icono' => $valueTelPers->icono,
											'etiqueta' => $valueTelPers->etiqueta,
											'telefono' => $JwtAuth->desencriptar($valueTelPers->telefono),
											'extension' => $telExtension,
										);
										$arrayTelefono[] = $arrateleach;
									}
								}

								$proveDelete = array(
									"token" => $valContProvDel->pers_token,
									"nombre_completo" => strtoupper($JwtAuth->desencriptar($valContProvDel->paterno) . " " . $JwtAuth->desencriptar($valContProvDel->materno) . " " . $JwtAuth->desencriptar($valContProvDel->nombre)),
									"areaemp" => strtoupper($JwtAuth->desencriptar($valContProvDel->areaemp)),
									"cargo" => strtoupper($JwtAuth->desencriptar($valContProvDel->cargo)),
									"fecha_delete" => $fecha_delete_pers,
									"correo" => $arrayCorreo,
									"telefono" => $arrayTelefono
								);
								$personalContactoDel[] = $proveDelete;
							}
						}

						//direcciones 
						$arrayUbicacion = array();
						$arrayUbicacionDel = array();

						if ($valViewProv->nacionalidad == 118) {
							$listaUbicacion = ProveedoresModelo::join("teci_direcciones AS ubica", "ubica.proveedor", "eegr_catalogo_proveedores.id")
								->join("teci_direcciones_codigos_postales AS cpostal", "ubica.codigo_postal", "cpostal.id")
								->join("teci_pais AS detpais", "ubica.pais", "detpais.id")
								->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "emp.id")
								->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
								->join("teci_usuarios_catalogo AS users","empuser.usuario", "users.id")
								->where([
									"ubica.status" => TRUE,
									"eegr_catalogo_proveedores.token_cat_proveedores" => $proveedor,
									"emp.empresa_token" => $usuario->empresa_token,
									"users.usuario_token" => $usuario->user_token
								])->get();

							if (count($listaUbicacion) > 0) {
								foreach ($listaUbicacion as $valUbicacion) {
									if ($valUbicacion->calle != '' && $valUbicacion->calle != NULL) {
										$calle = $JwtAuth->desencriptar($valUbicacion->calle);
									} else {
										$calle = 's/c';
									}

									if ($valUbicacion->num_ext != '' && $valUbicacion->num_ext != NULL) {
										$num_ext = $JwtAuth->desencriptar($valUbicacion->num_ext);
									} else {
										$num_ext = 's/n';
									}

									if ($valUbicacion->num_int != '' && $valUbicacion->num_int != NULL) {
										$num_int = $JwtAuth->desencriptar($valUbicacion->num_int);
									} else {
										$num_int = 's/n';
									}

									if ($valUbicacion->calle1 != '' && $valUbicacion->calle1 != NULL) {
										$calle1 = $JwtAuth->desencriptar($valUbicacion->calle1);
									} else {
										$calle1 = 's/c';
									}

									if ($valUbicacion->calle2 != '' && $valUbicacion->calle2 != NULL) {
										$calle2 = $JwtAuth->desencriptar($valUbicacion->calle2);
									} else {
										$calle2 = 's/c';
									}

									if ($valUbicacion->referencia != '' && $valUbicacion->referencia != NULL) {
										$referencia = $JwtAuth->desencriptar($valUbicacion->referencia);
									} else {
										$referencia = 's/reg';
									}

									$eachUbicacion = array(
										"token_direccion" => $valUbicacion->token_direccion,
										"tipo_direccion" => $valUbicacion->tipo_direccion,
										"clasificacion" => $JwtAuth->desencriptar($valUbicacion->clase),
										"alias" => $JwtAuth->desencriptar($valUbicacion->alias),
										"calle" => $calle,
										"num_ext" => $num_ext,
										"num_int" => $num_int,
										"token_codigos_postales" => $valUbicacion->token_codigos_postales,
										"codigo_postal" => $valUbicacion->codigo_postal,
										"asentamiento" => $valUbicacion->asentamiento,
										"tipo_asentamiento" => $valUbicacion->tipo_asentamiento,
										"deleg_mun" => $valUbicacion->deleg_mun,
										"estado" => $valUbicacion->estado,
										"ciudad" => $valUbicacion->ciudad,
										"pais" => $valUbicacion->pais,
										"calle1" => $calle1,
										"calle2" => $calle2,
										"referencia" => $referencia,
										"validate" => false,
									);
									$arrayUbicacion[] = $eachUbicacion;
								}
							}

							$listaUbicacionDel = ProveedoresModelo::join("teci_direcciones AS ubica","ubica.proveedor","eegr_catalogo_proveedores.id")
								->join("teci_direcciones_codigos_postales AS cpostal","ubica.codigo_postal","cpostal.id")
								->join("teci_pais AS detpais", "ubica.pais","detpais.id")
								->join("main_empresas AS emp","eegr_catalogo_proveedores.administrador","emp.id")
								->join("main_empresa_usuario AS empuser","emp.id","empuser.empresa")
								->join("teci_usuarios_catalogo AS users","empuser.usuario", "users.id")
								->where([
									"ubica.status" => FALSE,
									"eegr_catalogo_proveedores.token_cat_proveedores" => $proveedor,
									"emp.empresa_token" => $usuario->empresa_token,
									"users.usuario_token" => $usuario->user_token
								])->get();

							if (count($listaUbicacionDel) > 0) {
								foreach ($listaUbicacionDel as $valUbicacion) {
									if ($valUbicacion->calle != '' && $valUbicacion->calle != NULL) {
										$calle = $JwtAuth->desencriptar($valUbicacion->calle);
									} else {
										$calle = 's/c';
									}

									if ($valUbicacion->num_ext != '' && $valUbicacion->num_ext != NULL) {
										$num_ext = $JwtAuth->desencriptar($valUbicacion->num_ext);
									} else {
										$num_ext = 's/n';
									}

									if ($valUbicacion->num_int != '' && $valUbicacion->num_int != NULL) {
										$num_int = $JwtAuth->desencriptar($valUbicacion->num_int);
									} else {
										$num_int = 's/n';
									}

									if ($valUbicacion->calle1 != '' && $valUbicacion->calle1 != NULL) {
										$calle1 = $JwtAuth->desencriptar($valUbicacion->calle1);
									} else {
										$calle1 = 's/c';
									}

									if ($valUbicacion->calle2 != '' && $valUbicacion->calle2 != NULL) {
										$calle2 = $JwtAuth->desencriptar($valUbicacion->calle2);
									} else {
										$calle2 = 's/c';
									}

									if ($valUbicacion->referencia != '' && $valUbicacion->referencia != NULL) {
										$referencia = $JwtAuth->desencriptar($valUbicacion->referencia);
									} else {
										$referencia = 's/reg';
									}

									$eachUbicacion = array(
										"token_direccion" => $valUbicacion->token_direccion,
										"fecha_delete" => date('d-m-Y H:i:s', $valUbicacion->fecha_delete_dir),
										"tipo_direccion" => $valUbicacion->tipo_direccion,
										"clasificacion" => $JwtAuth->desencriptar($valUbicacion->clase),
										"alias" => $JwtAuth->desencriptar($valUbicacion->alias),
										"calle" => $calle,
										"num_ext" => $num_ext,
										"num_int" => $num_int,
										"token_codigos_postales" => $valUbicacion->token_codigos_postales,
										"codigo_postal" => $valUbicacion->codigo_postal,
										"asentamiento" => $valUbicacion->asentamiento,
										"tipo_asentamiento" => $valUbicacion->tipo_asentamiento,
										"deleg_mun" => $valUbicacion->deleg_mun,
										"estado" => $valUbicacion->estado,
										"ciudad" => $valUbicacion->ciudad,
										"pais" => $valUbicacion->pais,
										"calle1" => $calle1,
										"calle2" => $calle2,
										"referencia" => $referencia,
										"validate" => false,
									);
									$arrayUbicacionDel[] = $eachUbicacion;
								}
							}
						} else {
							$listUbicacion = DireccionesModelo::join('teci_pais AS detpais', 'direcciones.pais', 'detpais.id')
								->join('eegr_catalogo_proveedores AS catprov', 'direcciones.proveedor', 'catprov.id')
								->join('main_empresas AS emp', 'catprov.administrador', 'emp.id')
								->join('main_empresa_usuario AS empuser', 'emp.id', 'empuser.empresa')
								->join('teci_usuarios_catalogo AS users', 'empuser.usuario', 'users.id')
								->where([
									'direcciones.status' => TRUE,
									'catprov.token_cat_proveedores' => $proveedor,
									'emp.empresa_token' => $usuario->empresa_token,
									'users.usuario_token' => $usuario->user_token
								])->get();

							if (count($listUbicacion) > 0) {
								foreach ($listUbicacion as $valUbicacion) {
									if ($valUbicacion->calle == NULL) {
										$valUbiCalle = 'sin Dirección';
									} else {
										$valUbiCalle = $JwtAuth->desencriptar($valUbicacion->calle);
									}
									$eachUbicacion = array(
										"token_direccion" => $valUbicacion->token_direccion,
										"tipo_direccion" => $valUbicacion->tipo_direccion,
										"clasificacion" => $JwtAuth->desencriptar($valUbicacion->clase),
										"alias" => $JwtAuth->desencriptar($valUbicacion->alias),
										"cod_postalext" => $JwtAuth->desencriptar($valUbicacion->cod_postalext),
										"dir_completa" => $valUbiCalle,
										"pais" => $valUbicacion->pais,
										"validate" => false,
									);
									$arrayUbicacion[] = $eachUbicacion;
								}
							}

							$listUbicacionDel = DireccionesModelo::join('teci_pais AS detpais','direcciones.pais','detpais.id')
								->join('eegr_catalogo_proveedores AS catprov','direcciones.proveedor','catprov.id')
								->join('main_empresas AS emp','catprov.administrador', 'emp.id')
								->join('main_empresa_usuario AS empuser','emp.id', 'empuser.empresa')
								->join('teci_usuarios_catalogo AS users','empuser.usuario', 'users.id')
								->where([
									'direcciones.status' => FALSE,
									'catprov.token_cat_proveedores' => $proveedor,
									'emp.empresa_token' => $usuario->empresa_token,
									'users.usuario_token' => $usuario->user_token
								])->get();

							if (count($listUbicacionDel) > 0) {
								foreach ($listUbicacionDel as $valUbicacionDel) {
									if ($valUbicacionDel->calle == NULL) {
										$valUbiCalle = 'sin Dirección';
									} else {
										$valUbiCalle = $JwtAuth->desencriptar($valUbicacionDel->calle);
									}
									$eachUbicacion = array(
										"token_direccion" => $valUbicacionDel->token_direccion,
										"tipo_direccion" => $valUbicacionDel->tipo_direccion,
										"fecha_delete" => date('d-m-Y H:i:s', $valUbicacionDel->fecha_delete_dir),
										"clasificacion" => $JwtAuth->desencriptar($valUbicacionDel->clase),
										"alias" => $JwtAuth->desencriptar($valUbicacionDel->alias),
										"cod_postalext" => $JwtAuth->desencriptar($valUbicacionDel->cod_postalext),
										"dir_completa" => $valUbiCalle,
										"pais" => $valUbicacionDel->pais,
										"validate" => false,
									);
									$arrayUbicacionDel[] = $eachUbicacion;
								}
							}
						}

						//forma de pago
						$arregloformaPago = array();
						$listaformaspago = array();
						$listafp = FormaPagoModelo::all();
						$forma_pago_concept = '';
						foreach ($listafp as $valFormaPagoo) {
							if ($valFormaPagoo->token_formapago == $valViewProv->token_formapago) {
								$selected = true;
								$forma_pago_concept = $valFormaPagoo->clave . ' - ' . $valFormaPagoo->forma;
							} else {
								$selected = false;
							}
							$arrayEachFormaPago = array(
								"selected" => $selected,
								"token_formapago" => $valFormaPagoo->token_formapago,
								"clave" => $valFormaPagoo->clave,
								"forma" => $valFormaPagoo->forma,
							);
							$listaformaspago[] = $arrayEachFormaPago;
						}

						if ($valViewProv->tipo_referencia_pago != NULL) {
							if ($valViewProv->tipo_referencia_pago == 'ci') {
								$tipo_referencia_pago = 'clabeInterbancaria';
							} else if ($valViewProv->tipo_referencia_pago == 'co') {
								$tipo_referencia_pago = 'convenio';
							} else if ($valViewProv->tipo_referencia_pago == 'lc') {
								$tipo_referencia_pago = 'lineaCaptura';
							}
						} else {
							$tipo_referencia_pago = '';
						}

						if ($valViewProv->token_formapago == 'RkxGMTRidG44ZWJJYVh0dUlDK1o4Zz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
							if ($valViewProv->estado_cuenta == '' || $valViewProv->estado_cuenta == NULL) {
								$doc_estado_cuenta = '';
								$nameEstado_cuenta = '';
							} else {
								//echo $valViewProv->estado_cuenta;
								if (file_exists(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->estado_cuenta)))) {
									$extensionFiscal = pathinfo(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->estado_cuenta)), PATHINFO_EXTENSION);
									$nameEstado_cuenta = $JwtAuth->desencriptar($valViewProv->estado_cuenta);
									if ($extensionFiscal == 'pdf') {
										$est_cuentaB64 = $JwtAuth->encriptaBase64Pdf(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->estado_cuenta)));
										$doc_estado_cuenta = '<iframe src="' . $est_cuentaB64 . '" width="100%" height="400px"></iframe>';
									}

									if ($extensionFiscal == 'jpg') {
										$est_cuentaB64 = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->opinion_cumplimiento)));
										$doc_estado_cuenta = '<img class="responsive-img circle materialboxed imag2" src="' . $est_cuentaB64 . '">';
									}

									if ($extensionFiscal == 'png') {
										$est_cuentaB64 = $JwtAuth->encriptaBase64(Storage::path('public/root/' . $rutaDocsProv . $JwtAuth->desencriptar($valViewProv->opinion_cumplimiento)));
										$doc_estado_cuenta = '<img class="responsive-img circle materialboxed imag2" src="' . $est_cuentaB64 . '">';
									}
								} else {
									$nameEstado_cuenta = '';
									$doc_estado_cuenta = '';
								}
							}

							$forma_pago = array(
								"token_formapago" => $valViewProv->token_formapago,
								"validateclint" => 'transferencia',
								"clabe_interbancaria" => $valViewProv->clabe_interbancaria,
								"doc_estado_cuenta" => $doc_estado_cuenta,
								"estado_cuenta" => $nameEstado_cuenta,
								"forma_pago_concept" => $forma_pago_concept,
								"tipo_referencia_pago" => $tipo_referencia_pago,
							);
							$arregloformaPago[] = $forma_pago;
						} else {
							$forma_pago = array(
								"token_formapago" => $valViewProv->token_formapago,
								"validateclint" => 'noneView',
								"clabe_interbancaria" => '',
								"doc_estado_cuenta" => '',
								"estado_cuenta" => '',
								"forma_pago_concept" => $forma_pago_concept,
								"tipo_referencia_pago" => $tipo_referencia_pago,
							);
							$arregloformaPago[] = $forma_pago;
						}

						//creditos
						$token_moneda = '';
						$arregloCreditos = array();
						$queryAsignCreditos = DB::select("SELECT cred.token_creditos,cred.aceptacredito,
							IF (cred.moneda in (SELECT id FROM teci_catalogo_monedas),
								(SELECT token_monedas FROM teci_catalogo_monedas WHERE id = cred.moneda),'') AS token_monedas, 
							IF (cred.moneda in (SELECT id FROM teci_catalogo_monedas),
								(SELECT codigo FROM teci_catalogo_monedas WHERE id = cred.moneda),'') AS codigo,
							IF (cred.moneda in (SELECT id FROM teci_catalogo_monedas),
								(SELECT moneda FROM teci_catalogo_monedas WHERE id = cred.moneda),'') AS moneda, 
							IF (cred.moneda in (
								SELECT id FROM teci_catalogo_monedas),cred.limite,'') AS limite,
							IF (cred.moneda in (SELECT id FROM teci_catalogo_monedas),cred.dias,'') AS diasPago,
							IF (cred.moneda in (SELECT id FROM teci_catalogo_monedas),cred.comienza,'') AS comienza
							FROM in_egr_creditos AS cred
							JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp
							JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users
							WHERE cred.token_creditos = ? AND cred.proveedor = catprov.id AND catprov.token_cat_proveedores = ? 
							AND catprov.administrador = emp.id AND emp.id = empuser.empresa AND emp.empresa_token = ? 
							AND empuser.usuario = users.id AND users.usuario_token = ?", 
							[$valViewProv->token_creditos, $proveedor, $usuario->empresa_token, $usuario->user_token]);

						foreach ($queryAsignCreditos as $valCreditos) {
							if ($valCreditos->aceptacredito == TRUE) {
								$token_moneda = $valCreditos->token_monedas;
								$aceptaCredito = true;
								$diasPago = 86400 * $valCreditos->diasPago;
								$fLimitePago = time() + $diasPago;
								$fechaLimitePago = gmdate('Y-m-d H:i:s', $fLimitePago);
							} else {
								$token_moneda = '';
								$fechaLimitePago = '';
								$aceptaCredito = false;
							}
							$creditosEach = array(
								"token_creditos" => $valViewProv->token_creditos,
								"acepta" => $aceptaCredito,
								"codigo_moneda" => $valCreditos->codigo,
								"token_moneda" => $token_moneda,
								"moneda" => $valCreditos->moneda,
								"limite" => $valCreditos->limite,
								"dias" => $valCreditos->diasPago,
								"fechalimite" => $fechaLimitePago,
								"comienza" => $valCreditos->comienza
							);
							$arregloCreditos[] = $creditosEach;
						}

						$arrayMonedas = array();
						$catMonedas = MonedasModelo::get();

						foreach ($catMonedas as $valMonedas) {
							//echo $valMonedas->token_monedas;
							if ($valMonedas->token_monedas == $token_moneda) {
								$selected = true;
							} else {
								$selected = false;
							}

							$arrayMon = array(
								"token_monedas" => $valMonedas->token_monedas,
								"codigo" => $valMonedas->codigo,
								"moneda" => $valMonedas->moneda,
								"decimales" => $valMonedas->decimales,
								"selected" => $selected,
							);
							$arrayMonedas[] = $arrayMon;
						}


						if ($valViewProv->tiene_docs_fiscales == TRUE) {
							$tiene_docs_fiscales = true;
						} else {
							$tiene_docs_fiscales = false;
						}

						if ($valViewProv->post_folio == NULL) {
							$folio_prov = 'prv-' . $JwtAuth->generarFolio($valViewProv->folio);
						} else {
							$folio_prov = 'prv-' . $JwtAuth->generarFolio($valViewProv->folio) . '-' . $valViewProv->post_folio;
						}

						$arrayForeachProv = array(
							"token_proveedor" => $valViewProv->token_cat_proveedores,
							"folio" => $folio_prov,
							"textNombreProv" => $textNombreProv,
							"proveedor_name_edit" => $proveedor_name_edit,
							"nationality" => $valViewProv->nacionalidad,
							"pais_ident" => $valViewProv->token_pais,
							"pais_name" => $valViewProv->pais,
							"rfc_generico" => $rfc_generico,
							"rfc_prov" => $rfc_prov,
							"tax_id_prov" => $tax_id_prov,
							"nombre_comercial" => $nombre_com,
							"sitio_web" => $sitio_web,
							"redes_sociales" => $arrayRedes,
							"lista_precios" => $valViewProv->listaPrecios,
							"personal" => $personalContacto,
							"personalDelete" => $personalContactoDel,
							"const_sit_fiscal" => $const_sit_fiscal,
							"nameConst_sit_fiscal" => $nameConst_sit_fiscal,
							"tiene_docs_fiscales" => $tiene_docs_fiscales,
							"doc_opinion_cumplimiento" => $doc_opinion_cumplimiento,
							"nameDoc_opinion_cumplimiento" => $nameDoc_opinion_cumplimiento,
							"noCargaDocsFiscalesRazon" => $no_cuenta_fiscales,
							"creditos" => $arregloCreditos,
							"arrayMonedas" => $arrayMonedas,
							"forma_pago" => $arregloformaPago,
							"listaformaspago" => $listaformaspago,
							"arrayUbicacion" => $arrayUbicacion,
							"arrayUbicacionDel" => $arrayUbicacionDel,
							"arrayCuentasCont" => $arrayCuentasCont,
							"bitacora" => $arrayActividad,
						);

						$arrayProveedor[] = $arrayForeachProv;

						$dataMensaje = array(
							'proveedor' => $arrayProveedor,
							'code' => 200,
							'status' => 'success'
						);
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
				'message' => 'Los parametro de busqueda recibidos son inexistentes'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function acticipoProveedorList(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);
    $listaAnticipos = array();

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Los parametro de busqueda recibidos son incorrectos',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
        $token_cat_proveedores = $parametrosArray['token_cat_proveedores'];

        $catalogAnticipos = DB::table("eegr_catalogo_proveedores_anticipo AS ant")
        ->join("eegr_catalogo_proveedores AS catprv","ant.proveedor","catprv.id") 
        ->join("main_empresas AS emp","ant.empresa","emp.id") 
        ->where(["ant.estatus_anticipo" => TRUE,"catprv.token_cat_proveedores" => $token_cat_proveedores,"emp.empresa_token" => $usuario->empresa_token])->get();

        foreach ($catalogAnticipos as $vAnt) {
          //da_te_default_timezone_set($vAnt->zona_horaria);
          $row = array(
            "fecha_registro" => date('d-m-Y H:i:s', $vAnt->fecha_registro), 
            "folio" => $JwtAuth->generarFolio($vAnt->folio_anticipo), 
            "token_anticipo" => $vAnt->token_anticipo,   
            "fecha_aplicacion" => date('d-m-Y H:i:s', $vAnt->fecha_aplicacion),
            "forma_pago" => $vAnt->forma_pago_anticipo,
            "moneda_code" => $vAnt->moneda_code,
            "moneda_decimales" => $vAnt->moneda_decimales, 
            "tipo_cambio" => $vAnt->tipo_cambio, 
            "cantidad_anticipo" => $vAnt->monto_total,  
            "observaciones" => $JwtAuth->desencriptar($vAnt->observaciones)
          );
          $listaAnticipos[] = $row;
        }

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'anticipos' => $listaAnticipos
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

	public function acticipoProveedorRegist(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
				'fecha_aplicacion' => 'required|string',
				'forma_pago' => 'required|string',
				'moneda_codigo' => 'required|string',
				'moneda_decimales' => 'required|string',
				'tipo_cambio' => 'required|string',
				'cantidad_anticipo' => 'required|string',
				'observaciones' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Los parametro de busqueda recibidos son incorrectos',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$fecha_registro = time(); 
				//QRCode::text($parametrosArray['user_token'])->setOutfile(Storage::path('public/root/QRPersonal.png'))->png();
				$token_cat_proveedores = $parametrosArray['token_cat_proveedores'];
				$fecha_aplicacion = $parametrosArray["fecha_aplicacion"]; 
				$forma_pago = $parametrosArray["forma_pago"]; 
				$moneda_codigo = $parametrosArray['moneda_codigo'];
				$moneda_decimales = $parametrosArray['moneda_decimales'];
				$tipo_cambio = $parametrosArray['tipo_cambio'];
				$cantidad_anticipo = $parametrosArray['cantidad_anticipo'];
				$observaciones = $parametrosArray['observaciones'];

				$valida_prov = isset($token_cat_proveedores) && !empty($token_cat_proveedores);
				$valida_aplicacion = isset($fecha_aplicacion) && !empty($fecha_aplicacion) && preg_match($JwtAuth->filtroAlfaNumerico(),$fecha_aplicacion);
				$valida_fpago = isset($forma_pago) && !empty($forma_pago) && preg_match($JwtAuth->filtroAlfaNumerico(),$forma_pago);
				$valida_moncod = isset($moneda_codigo) && !empty($moneda_codigo) && preg_match($JwtAuth->filtroAlfaNumerico(),$moneda_codigo);
				$valida_mondec = isset($moneda_decimales) && !empty($moneda_decimales) && preg_match($JwtAuth->filtroNumericoSimple(),$moneda_decimales);
				$valida_tipoc = isset($tipo_cambio) && !empty($tipo_cambio) && preg_match($JwtAuth->filtroNumericoSimple(),$tipo_cambio);
				$valida_cant = isset($cantidad_anticipo) && !empty($cantidad_anticipo) && preg_match($JwtAuth->filtroNumericoSimple(),$cantidad_anticipo);
				$valida_observ = isset($observaciones) && !empty($observaciones) && preg_match($JwtAuth->filtroAlfaNumerico(),$observaciones);

				if ($valida_aplicacion && $valida_fpago && $valida_prov && $valida_moncod && $valida_mondec && $valida_tipoc && $valida_cant && $valida_observ) {
					//$ident_proveedor = DB::select("SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?",[$token_cat_proveedores]);
					//$ident_proveedor = DB::table("eegr_catalogo_proveedores")->where(["token_cat_proveedores" => $token_cat_proveedores])->get();
					$ident_empresa = DB::table("main_empresas")->where("empresa_token", $usuario->empresa_token)->value("id");
					$ident_usuario = DB::table("teci_usuarios_catalogo")->where("usuario_token", $usuario->user_token)->value("id");
					$ident_proveedor = DB::table("eegr_catalogo_proveedores")->where("token_cat_proveedores", $token_cat_proveedores)->value("id");

					$max_folio = DB::select("SELECT IF (max(ant.folio_anticipo) IS NOT NULL,max(ant.folio_anticipo)+1,1) AS folio FROM eegr_catalogo_proveedores_anticipo AS ant 
						JOIN eegr_catalogo_proveedores AS catprv WHERE ant.proveedor = catprv.id AND catprv.token_cat_proveedores = ?",[$token_cat_proveedores]);
					
					$folio_registrado = "ANT-".$JwtAuth->generarFolio($max_folio[0]->folio);
					$token_anticipo = $JwtAuth->encriptarToken($ident_empresa,$ident_usuario,$ident_proveedor,$moneda_codigo,$moneda_decimales,$tipo_cambio,$cantidad_anticipo);
					$insertAnticipo = DB::table('eegr_catalogo_proveedores_anticipo')
					->insert(array(
							"fecha_registro" => $fecha_registro,
							"folio_anticipo" => $max_folio[0]->folio, 
							"token_anticipo" => $token_anticipo,   
							"proveedor" => $ident_proveedor, 
							"fecha_aplicacion" => $JwtAuth->convierteFechaEpoc($fecha_aplicacion),
              "forma_pago_anticipo" => $forma_pago,
              "moneda_code" => $moneda_codigo,
							"moneda_decimales" => $moneda_decimales, 
							"tipo_cambio" => $tipo_cambio, 
							"monto_total" => $cantidad_anticipo,  
							"observaciones" => $JwtAuth->encriptar($observaciones),   
							"estatus_anticipo" => TRUE, 
							"empresa" => $ident_empresa, 
							"usuario" => $ident_usuario
					));

					if ($insertAnticipo) {
						$dataMensaje = array('status' => 'success','code' => 200,'message' => "Anticipo registrado exitosamente con folio $folio_registrado");
					} else {
						$dataMensaje = array('status' => 'error','code' => 200,'message' => "Error al registrar anticipo, intentelo nuevamente o comuniquese a soporte");
					}
				} else {
					$mensaje_error = "";
					if (!$valida_aplicacion) $mensaje_error = "Error al registrar fecha de aplicación, intentelo nuevamente o comuniquese a soporte";
					if (!$valida_fpago) $mensaje_error = "Error al seleccionar forma de pago, intentelo nuevamente o comuniquese a soporte";
					if (!$valida_prov) $mensaje_error = "Error al seleccionar proveedor, intentelo nuevamente o comuniquese a soporte";
					if (!$valida_moncod) $mensaje_error = "Error al seleccionar moneda (Código de moneda), intentelo nuevamente o comuniquese a soporte"; 
					if (!$valida_mondec) $mensaje_error = "Error al seleccionar moneda (decimales), intentelo nuevamente o comuniquese a soporte"; 
					if (!$valida_tipoc) $mensaje_error = "Error al registrar tipo de cambio, intentelo nuevamente o comuniquese a soporte"; 
					if (!$valida_cant) $mensaje_error = "Error al registrar importe de anticipo, intentelo nuevamente o comuniquese a soporte"; 
					if (!$valida_observ) $mensaje_error = "Error al registrar observaciones, intentelo nuevamente o comuniquese a soporte";  
					$dataMensaje = array('status' => 'error','code' => 200,'message' => $mensaje_error);
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

	public function createCuentaContableProv(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp JOIN main_empresa_usuario AS empuser 
					JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				$selectProv = DB::select("SELECT catprov.id FROM catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
				    WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
					[$parametrosArray['token_proveedor'], $usuario->empresa_token, $usuario->user_token]
				);

				$folioCuentaCont = DB::select("SELECT IF (max(cuent.folio) IS NOT NULL,(max(cuent.folio)+1),1) AS folio FROM cuentas_contables AS cuent JOIN main_empresas AS emp 
				    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE cuent.empresa = emp.id AND emp.empresa_token = ?
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", [$usuario->empresa_token, $usuario->user_token]);

				$token_cuenta_contable = $JwtAuth->encriptarToken(
					$usuario->empresa_token,
					$usuario->user_token,
					$parametrosArray['token_proveedor'],
					$folioCuentaCont[0]->folio
				);

				$creaCuentaCont = new CuentasContablesModelo();
				$creaCuentaCont->token_cuenta_contable = $token_cuenta_contable;
				$creaCuentaCont->fecha_alta	= time();
				$creaCuentaCont->folio = $folioCuentaCont[0]->folio;
				$creaCuentaCont->proveedor = $selectProv[0]->id;
				$creaCuentaCont->cliente = NULL;
				$creaCuentaCont->contable = NULL;
				$creaCuentaCont->act_pas_cap = NULL;
				$creaCuentaCont->al_pl = NULL;
				$creaCuentaCont->acum = NULL;
				$creaCuentaCont->empresa = $selectEmp[0]->id;
				$savednewCuentaCont = $creaCuentaCont->save();

				if ($savednewCuentaCont) {
					$dataMensaje = array(
						'status' => 'success',
						'code' => 200,
						'message' => 'Cuenta contable registrada'
					);
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Cuenta contable no registrada, intente nuevamente o comuniquese a soporte para resolver este problema'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function actualizaRfcProv(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'rfc_generico' => 'required|string',
				'rfc_prov' => 'required|string',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$patronRfc = '/[aA0-zZ9]/';

				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
				    WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$rfc_generico = $parametrosArray['rfc_generico'];
				$rfc_prov = $parametrosArray['rfc_prov'];

				$validateRfc = 'false';

				if (
					isset($rfc_generico) && !empty($rfc_generico) && preg_match($patronRfc, $rfc_generico) &&
					isset($rfc_prov) && !empty($rfc_prov) && preg_match($patronRfc, $rfc_prov)
				) {

					if (strlen($rfc_generico) == 13) {
						if (strlen($rfc_prov) == 13) {
							$validateRfc = 'true';
						} else {
							$validateRfc = 'false';
						}
					} else if (strlen($rfc_generico) == 12) {
						if (strlen($rfc_prov) == 12) {
							$validateRfc = 'true';
						} else {
							$validateRfc = 'false';
						}
					}

					if ($validateRfc == 'true') {
						$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
							->join("personas AS people", "catprov.proveedor", "=", "people.id")
							->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
							->join("main_empresa_usuario AS empuser","emp.id", "=", "empuser.empresa")
							->join("teci_usuarios_catalogo AS users","empuser.usuario", "=", "users.id")
							->where([
								'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
								'emp.empresa_token' => $usuario->empresa_token,
								'users.usuario_token' => $usuario->user_token,
							])
							->limit(1)->update(
								array(
									'people.rfc' => $JwtAuth->encriptar($rfc_prov),
								)
							);

						if ($updatePaterno) {
							$folio_db_prov = DB::select("SELECT folio,post_folio FROM catalogo_proveedores
						    	WHERE token_cat_proveedores = ?", [$parametrosArray['token_proveedor']]);
							if ($folio_db_prov[0]->post_folio == NULL) {
								$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio);
							} else {
								$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio) . '-' . $folio_db_prov[0]->post_folio;
							}

							$JwtAuth->insertBitacoraActividad(
								'egresos',
								'catalogos',
								'proveedores',
								$folio_prov,
								'registro de rfc de proveedor',
								$usuario->empresa_token,
								$usuario->user_token
							);
							$JwtAuth->insertBitacoraProv(
								$parametrosArray['token_proveedor'],
								'registro de rfc de proveedor',
								$usuario->empresa_token,
								$usuario->user_token
							);

							$dataMensaje = array(
								'status' => 'success',
								'code' => 200,
								'message' => 'Rfc del proveedor actualizado'
							);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Rfc del proveedor no fue actualizado, intente nuevamente o comuniquese a soporte para resolver este problema'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en rfc de su proveedor, revise su información o comuniquese a soporte'
						);
					}
				} else {
					if (!isset($rfc_generico) || empty($rfc_generico) || !preg_match($patronRfc, $rfc_generico)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en rfc generico de su proveedor, revise su información o comuniquese a soporte'
						);
					}
					if (!isset($rfc_prov) || empty($rfc_prov) || !preg_match($patronRfc, $rfc_prov)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en rfc de su proveedor, revise su información o comuniquese a soporte'
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
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function actualizaIdTaxProv(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'tax_id_prov' => 'required|string',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$patronRfc = '/[aA0-zZ9]/';

				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
				    WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);
				$tax_id_prov = $parametrosArray['tax_id_prov'];

				if (isset($tax_id_prov) && !empty($tax_id_prov) && preg_match($patronRfc, $tax_id_prov)) {
					$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("personas AS people", "catprov.proveedor", "=", "people.id")
						->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])
						->limit(1)->update(
							array(
								'people.tax_id' => $JwtAuth->encriptar($tax_id_prov),
							)
						);

					if ($updatePaterno) {
						$folio_db_prov = DB::select("SELECT folio,post_folio FROM catalogo_proveedores
							WHERE token_cat_proveedores = ?", [$parametrosArray['token_proveedor']]);
						if ($folio_db_prov[0]->post_folio == NULL) {
							$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio);
						} else {
							$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio) . '-' . $folio_db_prov[0]->post_folio;
						}

						$JwtAuth->insertBitacoraActividad(
							'egresos',
							'catalogos',
							'proveedores',
							$folio_prov,
							'registro de IDTax de proveedor',
							$usuario->empresa_token,
							$usuario->user_token
						);
						$JwtAuth->insertBitacoraProv(
							$parametrosArray['token_proveedor'],
							'registro de IDTax de proveedor',
							$usuario->empresa_token,
							$usuario->user_token
						);

						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'IDTax del proveedor actualizado'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'IDTax del proveedor no fue actualizado, intente nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Error en rfc de su proveedor, revise su información o comuniquese a soporte'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function actualizaGeneralesPF(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'paternoPersonales' => 'string',
				'maternoPersonales' => 'string',
				'nombrePersonales' => 'string',
				'nombreComPersonales' => 'string',
				'curpTaxPersonales' => 'string',
				'selectPaisPersonales' => 'string',
				'sitWebPersonales' => 'string',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
				$patronNumCred = '/^[0-9$,.-]*$/';
				$patronRfc = '/[aA0-zZ9]/';
				$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users 
				    WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$paternoPersonales = $parametrosArray['paternoPersonales'];
				$maternoPersonales = $parametrosArray['maternoPersonales'];
				$nombrePersonales = $parametrosArray['nombrePersonales'];
				$nombreComPersonales = $parametrosArray['nombreComPersonales'];
				$curpTaxPersonales = $parametrosArray['curpTaxPersonales'];
				$selectPaisPersonales = $parametrosArray['selectPaisPersonales'];
				$sitWebPersonales = $parametrosArray['sitWebPersonales'];

				if (
					isset($paternoPersonales) && !empty($paternoPersonales) && preg_match($patron, $paternoPersonales) &&
					isset($maternoPersonales) && !empty($maternoPersonales) && preg_match($patron, $maternoPersonales) &&
					isset($nombrePersonales) && !empty($nombrePersonales) && preg_match($patron, $nombrePersonales)
				) {

					if (isset($nombreComPersonales) && !empty($nombreComPersonales) && preg_match($patron, $nombreComPersonales)) {
						$nameComPersonales = $JwtAuth->encriptar($nombreComPersonales);
					} else {
						$nameComPersonales = NULL;
					}

					if (isset($curpTaxPersonales) && !empty($curpTaxPersonales) && preg_match($patron, $curpTaxPersonales)) {
						$curptaxPersonales = $JwtAuth->encriptar($curpTaxPersonales);
					} else {
						$curptaxPersonales = NULL;
					}

					if (isset($selectPaisPersonales) && !empty($selectPaisPersonales)) {
						$selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$selectPaisPersonales]);
						$selectpaisPersonales = $selectPais[0]->id;
					} else {
						$selectpaisPersonales = NULL;
					}

					if (isset($sitWebPersonales) && !empty($sitWebPersonales) && preg_match($patronUrl, $sitWebPersonales)) {
						$sitwebPersonales = $JwtAuth->encriptar($sitWebPersonales);
					} else {
						$sitwebPersonales = NULL;
					}

					$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("personas AS people", "catprov.proveedor", "=", "people.id")
						->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])
						->limit(1)->update(
							array(
								'people.paterno' => $JwtAuth->encriptar($paternoPersonales),
								'people.materno' => $JwtAuth->encriptar($maternoPersonales),
								'people.nombre' => $JwtAuth->encriptar($nombrePersonales),
								'people.nombre_com' => $nameComPersonales,
								'people.curp' => $curptaxPersonales,
								'people.nacionalidad' => $selectpaisPersonales,
								'people.sitio_web' => $sitwebPersonales,
							)
						);

					if ($updatePaterno) {
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Datos generales del proveedor actualizados'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Datos generales del proveedor no fueron actualizados, intente nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					if (!isset($paternoPersonales) || empty($paternoPersonales) || !preg_match($patron, $paternoPersonales)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en apellido paterno de su proveedor, revise su información o comuniquese a soporte'
						);
					}
					if (!isset($maternoPersonales) || empty($maternoPersonales) || !preg_match($patron, $maternoPersonales)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en apellido materno de su proveedor, revise su información o comuniquese a soporte'
						);
					}
					if (!isset($nombrePersonales) || empty($nombrePersonales) || preg_match($patron, $nombrePersonales)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en nombre de su proveedor, revise su información o comuniquese a soporte'
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
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function actualizaGeneralesPM(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'empresaPersonales' => 'string',
				'nombreComPersonales' => 'string',
				'curpTaxPersonales' => 'string',
				'selectPaisPersonales' => 'string',
				'sitWebPersonales' => 'string',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
				$patronNumCred = '/^[0-9$,.-]*$/';
				$patronRfc = '/[aA0-zZ9]/';
				$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp  
					JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$empresaPersonales = $parametrosArray['empresaPersonales'];
				$nombreComPersonales = $parametrosArray['nombreComPersonales'];
				$curpTaxPersonales = $parametrosArray['curpTaxPersonales'];
				$selectPaisPersonales = $parametrosArray['selectPaisPersonales'];
				$sitWebPersonales = $parametrosArray['sitWebPersonales'];

				if (isset($empresaPersonales) && !empty($empresaPersonales) && preg_match($patron, $empresaPersonales)) {

					if (isset($nombreComPersonales) && !empty($nombreComPersonales) && preg_match($patron, $nombreComPersonales)) {
						$nameComPersonales = $JwtAuth->encriptar($nombreComPersonales);
					} else {
						$nameComPersonales = NULL;
					}

					if (isset($curpTaxPersonales) && !empty($curpTaxPersonales) && preg_match($patron, $curpTaxPersonales)) {
						$curptaxPersonales = $JwtAuth->encriptar($curpTaxPersonales);
					} else {
						$curptaxPersonales = NULL;
					}

					if (isset($selectPaisPersonales) && !empty($selectPaisPersonales)) {
						$selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$selectPaisPersonales]);
						$selectpaisPersonales = $selectPais[0]->id;
					} else {
						$selectpaisPersonales = NULL;
					}

					if (isset($sitWebPersonales) && !empty($sitWebPersonales) && preg_match($patronUrl, $sitWebPersonales)) {
						$sitwebPersonales = $JwtAuth->encriptar($sitWebPersonales);
					} else {
						$sitwebPersonales = NULL;
					}

					$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("personas AS people", "catprov.proveedor", "=", "people.id")
						->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])
						->limit(1)->update(
							array(
								'people.nombre_extendido' => $JwtAuth->encriptar($empresaPersonales),
								'people.nombre_com' => $nameComPersonales,
								'people.curp' => $curptaxPersonales,
								'people.nacionalidad' => $selectpaisPersonales,
								'people.sitio_web' => $sitwebPersonales,
							)
						);

					if ($updatePaterno) {
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Datos generales del proveedor actualizados'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Datos generales del proveedor no fueron actualizados, intente nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					if (!isset($empresaPersonales) || empty($empresaPersonales) || preg_match($patron, $empresaPersonales)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en razön social/empresa de su proveedor, revise su información o comuniquese a soporte'
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
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function actualizaRedes(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'redes_sociales' => 'required|array',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp  
					JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$arrayredesSoc = $parametrosArray['redes_sociales'];
				$countarrayredesSoc = 0;
				$countRedesVacias = 0;

				if (isset($arrayredesSoc) && !empty($arrayredesSoc)) {
					for ($i = 0; $i < count($arrayredesSoc); $i++) {
						if ($arrayredesSoc[$i] == '') {
							$countarrayredesSoc++;
							$countRedesVacias++;
						} else {
							if (preg_match($patronUrl, $arrayredesSoc[$i])) {
								$countarrayredesSoc++;
							} else {
								break;
								if ($i == 0) {
									$red_soc_error = 'facebook';
								} else if ($i == 1) {
									$red_soc_error = 'twitter';
								} else if ($i == 2) {
									$red_soc_error = 'instagram';
								} else {
									$red_soc_error = 'youtube';
								}

								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'codeProvGenError' => 'redSoc',
									'message' => 'Error en red social ' . $red_soc_error . ' de su proveedor'
								);
							}
						}
					}
				}

				if ($countarrayredesSoc == count($arrayredesSoc)) {
					if ($countRedesVacias == $countarrayredesSoc) {
						$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
							->join("personas AS people", "catprov.proveedor", "=", "people.id")
							->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
							->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
							->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
							->where([
								'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
								'emp.empresa_token' => $usuario->empresa_token,
								'users.usuario_token' => $usuario->user_token,
							])
							->limit(1)->update(
								array(
									'people.redes_soc' => NULL,
								)
							);

						if ($updatePaterno) {
							$dataMensaje = array(
								'status' => 'success',
								'code' => 200,
								'message' => 'las redes sociales de su proveedor han sido actualizadas'
							);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'nomcom',
								'message' => 'las redes sociales de su proveedor no fueron actualizadas, intentelo nuevamente o comuniquese a soporte para resolver este problema'
							);
						}
					}

					if ($countRedesVacias != $countarrayredesSoc) {
						$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
							->join("personas AS people", "catprov.proveedor", "=", "people.id")
							->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
							->join("main_empresa_usuario AS empuser", "emp.id", "=", "empuser.empresa")
							->join("teci_usuarios_catalogo AS users", "empuser.usuario", "=", "users.id")
							->where([
								'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
								'emp.empresa_token' => $usuario->empresa_token,
								'users.usuario_token' => $usuario->user_token,
							])
							->limit(1)->update(
								array(
									'people.redes_soc' => $JwtAuth->encriptar(json_encode($arrayredesSoc)),
								)
							);

						if ($updatePaterno) {
							$dataMensaje = array(
								'status' => 'success',
								'code' => 200,
								'message' => 'las redes sociales de su proveedor han sido actualizadas'
							);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'nomcom',
								'message' => 'las redes sociales de su proveedor no fueron actualizadas, intentelo nuevamente o comuniquese a soporte para resolver este problema'
							);
						}
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
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function deletePersonalProv(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp  
					JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$token_personal = $parametrosArray['token_personal'];
				$countarrayredesSoc = 0;
				$countRedesVacias = 0;

				if (isset($token_personal) && !empty($token_personal)) {
					$countPers = DB::select(
						"SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.cat_proveedores = catprov.id 
						AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					$idenfiticaPers = DB::select(
						"SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
						AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					if (count($idenfiticaPers) == 1) {
						if (count($countPers) > 1) {
							$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
								->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
								->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
								->where([
									'pers.pers_token' => $token_personal,
									'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
									'emp.empresa_token' => $usuario->empresa_token,
								])
								->limit(1)->update(
									array(
										'pers.status' => FALSE,
										'pers.fecha_delete' => time(),
									)
								);

							if ($updatePaterno) {
								$dataMensaje = array(
									'status' => 'success',
									'code' => 200,
									'message' => 'personal eliminado'
								);
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
								);
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'no es posible eliminar este registro debido a que no existe mas personal de contacto para su proveedor'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'personal identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'personal invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function ingresaPersonalProveedor(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'list_contacto' => 'required|array',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {

				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
				$patronNumCred = '/^[0-9$,.-]*$/';
				$patronRfc = '/[aA0-zZ9]/';
				$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp  
					JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$arrayContactoPersonal = $parametrosArray['list_contacto'];
				$contadorContacto = 0;

				for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
					if (
						preg_match($patron, $arrayContactoPersonal[$i]['paterno']) && preg_match($patron, $arrayContactoPersonal[$i]['materno']) &&
						preg_match($patron, $arrayContactoPersonal[$i]['nombre']) && preg_match($patron, $arrayContactoPersonal[$i]['area']) &&
						preg_match($patron, $arrayContactoPersonal[$i]['cargo'])
					) {

						if (count($arrayContactoPersonal[$i]['emails']) == 0 && count($arrayContactoPersonal[$i]['telefonos']) == 0) {
							$contadorContacto++;
						} else {
							$contadorMails = 0;
							$personalEmails = $arrayContactoPersonal[$i]['emails'];
							for ($m = 0; $m < count($personalEmails); $m++) {
								if (preg_match($patronMail, $personalEmails[$m])) {
									$contadorMails++;
								} else {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCode' => $m,
										'message' => 'Error en correo electrónico de personal de contacto'
									);
									break;
								}
							}

							$contadorTelefonos = 0;
							$personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
							for ($t = 0; $t < count($personalTelefonos); $t++) {
								if (
									preg_match($patron, $personalTelefonos[$t]['icon']) &&
									preg_match($patron, $personalTelefonos[$t]['etiqueta']) &&
									preg_match($patronNum, $personalTelefonos[$t]['telefono']) &&
									preg_match($patronCpostal, $personalTelefonos[$t]['extension'])
								) {
									$contadorTelefonos++;
								} else {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCode' => $m,
										'message' => 'Error en teléfono de personal de contacto'
									);
									break;
								}
							}

							if ($contadorMails == count($personalEmails) || $contadorTelefonos == count($personalTelefonos)) {
								$contadorContacto++;
							}
						}
					} else {
						if (!preg_match($patron, $arrayContactoPersonal[$i]['paterno'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'positionErrorCode' => $i,
								'message' => 'Error en apellido paterno de personal de contacto'
							);
						}
						if (!preg_match($patron, $arrayContactoPersonal[$i]['materno'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'positionErrorCode' => $i,
								'message' => 'Error en apellido materno de personal de contacto'
							);
						}
						if (!preg_match($patron, $arrayContactoPersonal[$i]['nombre'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'positionErrorCode' => $i,
								'message' => 'Error en nombre de personal de contacto'
							);
						}
						if (!preg_match($patron, $arrayContactoPersonal[$i]['area'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'positionErrorCode' => $i,
								'message' => 'Error en area de trabajo de personal de contacto'
							);
						}
						if (!preg_match($patron, $arrayContactoPersonal[$i]['cargo'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'positionErrorCode' => $i,
								'message' => 'Error en cargo de trabajo de personal de contacto'
							);
						}
					}
				}

				if ($contadorContacto == count($arrayContactoPersonal)) {
					$selectProvCat = DB::select("SELECT id,folio,post_folio FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$parametrosArray['token_proveedor']]);
					$contadorInsertContacto = 0;
					$fechaAlta = time();
					for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
						$contArea = $JwtAuth->encriptar($arrayContactoPersonal[$i]['area']);

						$insertArea = DB::table('area')->insert(array("areaemp" => $contArea));
						$selectNewArea = DB::select("SELECT id FROM area WHERE areaemp = ?", [$contArea]);

						$contCargo = $JwtAuth->encriptar($arrayContactoPersonal[$i]['cargo']);
						$insertCargo = DB::table('cargo')->insert(array(
							"cargo" => $contCargo,
							"area" => $selectNewArea[0]->id,
						));
						$selectNewCargo = DB::select("SELECT id FROM cargo WHERE cargo = ? AND area = ?", [$contCargo, $selectNewArea[0]->id]);

						$contApePaterno = $JwtAuth->encriptar($arrayContactoPersonal[$i]['paterno']);
						$contApeMaterno = $JwtAuth->encriptar($arrayContactoPersonal[$i]['materno']);
						$contNombre = $JwtAuth->encriptar($arrayContactoPersonal[$i]['nombre']);

						$tokenPersonasPersonal = $JwtAuth->encriptarToken($contArea . '/' . $contCargo . '/' .
							$contApePaterno . '/' . $contApeMaterno . '/' . $selectEmp[0]->id . '/' . $contArea);

						$insertapersonalpersonas = DB::table('personas')
							->insert(array(
								"token_personas" => $tokenPersonasPersonal,
								"paterno" => $contApePaterno,
								"materno" => $contApeMaterno,
								"nombre" => $contNombre,
							));

						$selectpersonalpersonas = DB::select(
							"SELECT id FROM personas WHERE 
							token_personas = ? AND paterno = ? AND materno = ? AND nombre = ?",
							[$tokenPersonasPersonal, $contApePaterno, $contApeMaterno, $contNombre]
						);

						$tokenPersonal = $JwtAuth->encriptarToken($arrayContactoPersonal[$i]['paterno'] . '/' . $arrayContactoPersonal[$i]['nombre'] .
							'/' . $arrayContactoPersonal[$i]['materno'] . '/' . $selectEmp[0]->id . '/' . $contArea);

						$insertapersonal = DB::table('personal')
							->insert(array(
								"pers_token" => $tokenPersonal,
								"fecha_alta_pers" => $fechaAlta,
								"folio_pers" => $i,
								"area" => $selectNewArea[0]->id,
								"cargo" => $selectNewCargo[0]->id,
								"personal" => $selectpersonalpersonas[0]->id,
								"usuario" => NULL,
								"cat_clientes" => NULL,
								"cat_proveedores" => $selectProvCat[0]->id,
								"status" => TRUE,
								"fecha_delete" => NULL
							));

						$selectpersonal = DB::select("SELECT id FROM personal WHERE pers_token = ?", [$tokenPersonal]);

						$countInsertTel = 0;
						$personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
						if (count($personalTelefonos) != 0) {
							for ($t = 0; $t < count($personalTelefonos); $t++) {
								$contTelefono = $JwtAuth->encriptar($personalTelefonos[$t]['telefono']);

								if ($personalTelefonos[$t]['extension'] == '') {
									$contExtension = NULL;
								} else {
									$contExtension = $JwtAuth->encriptar($personalTelefonos[$t]['extension']);
								}

								$tokentel = $JwtAuth->encriptarToken($tokenPersonal . $contTelefono);

								$insertatelefonos_personal = DB::table('telefonos_personal')
									->insert(array(
										"token_telefono" => $tokentel,
										"personal" => $selectpersonal[0]->id,
										"icono" => $personalTelefonos[$t]['icon'],
										"etiqueta" => $personalTelefonos[$t]['etiqueta'],
										"telefono" => $contTelefono,
										"extension" => $contExtension,
										"status_telefono" => TRUE,
										"fecha_delete_tel" => NULL,
									));

								if ($insertatelefonos_personal) {
									$countInsertTel++;
								}
							}
						}

						$countInsertMails = 0;
						$personalEmails = $arrayContactoPersonal[$i]['emails'];
						if (count($personalEmails) != 0) {
							for ($m = 0; $m < count($personalEmails); $m++) {
								$contEmail = $JwtAuth->encriptar($personalEmails[$m]);
								$tokenEmail = $JwtAuth->encriptarToken($personalEmails[$m], $contEmail, $selectpersonal[0]->id);

								$insertacorreos_personal = DB::table('correos_personal')
									->insert(array(
										"token_correo" => $tokenEmail,
										"personal" => $selectpersonal[0]->id,
										"correo" => $contEmail,
										"status_correo" => TRUE,
										"fecha_delete_correo" => NULL,
									));
								if ($insertacorreos_personal) {
									$countInsertMails++;
								}
							}
						}

						if (
							$insertArea && $insertCargo && $insertapersonalpersonas && $insertapersonal &&
							$countInsertTel == count($personalTelefonos) && $countInsertMails == count($personalEmails)
						) {
							$contadorInsertContacto++;
						}
					}

					if ($contadorInsertContacto == count($arrayContactoPersonal)) {
						if ($selectProvCat[0]->post_folio == NULL) {
							$folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio);
						} else {
							$folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio) . '-' . $selectProvCat[0]->post_folio;
						}
						$JwtAuth->insertBitacoraActividad(
							'egresos',
							'catalogos',
							'proveedores',
							$folio_prov,
							'registro de personal para proveedor',
							$usuario->empresa_token,
							$usuario->user_token
						);
						$JwtAuth->insertBitacoraProv(
							$parametrosArray['token_proveedor'],
							'registro de personal para proveedor',
							$usuario->empresa_token,
							$usuario->user_token
						);
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'personal registrado'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'no concuerda la cantidad de información recibida'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function actualizaGeneralesPersonal(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
				'personal_cont_paterno' => 'string',
				'personal_cont_materno' => 'string',
				'personal_cont_nombre' => 'string',
				'personal_cont_area' => 'string',
				'personal_cont_cargo' => 'string'

			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
				$patronNumCred = '/^[0-9$,.-]*$/';
				$patronRfc = '/[aA0-zZ9]/';
				$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp  
					JOIN main_empresa_usuario AS empuser JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$validateInsertpersonal_cont_paterno = 'false';
				$validateInsertpersonal_cont_materno = 'false';
				$validateInsertpersonal_cont_nombre = 'false';
				$validateInsertpersonal_cont_area = 'false';
				$validateInsertpersonal_cont_cargo = 'false';

				if (isset($parametrosArray['personal_cont_paterno']) && !empty($parametrosArray['personal_cont_paterno'])) {
					if (preg_match($patron, $parametrosArray['personal_cont_paterno'])) {
						$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
							->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
							->join("personas AS people", "pers.empleado_name", "=", "people.id")
							->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
							->where([
								'pers.pers_token' => $parametrosArray['token_personal'],
								'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
								'emp.empresa_token' => $usuario->empresa_token,
							])
							->limit(1)->update(
								array(
									'people.paterno' => $JwtAuth->encriptar($parametrosArray['personal_cont_paterno']),
								)
							);

						if ($updatePaterno) {
							$validateInsertpersonal_cont_paterno = 'true';
						} else {
							return response()->json([
								'status' => 'error',
								'code' => 200,
								'message' => 'No fue actualizado el apellido paterno del personal de su proveedor, comuniquese a soporte para resolver este problema'
							]);
						}
					} else {
						return response()->json([
							'status' => 'error',
							'code' => 200,
							'codeProvGenError' => 'nomcom',
							'message' => 'Error en apellido paterno del personal de su proveedor, revise su información o comuniquese a soporte'
						]);
					}
				} else {
					$validateInsertpersonal_cont_paterno = 'true';
				}

				if (isset($parametrosArray['personal_cont_materno']) && !empty($parametrosArray['personal_cont_materno'])) {
					if (preg_match($patron, $parametrosArray['personal_cont_materno'])) {
						$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
							->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
							->join("personas AS people", "pers.empleado_name", "=", "people.id")
							->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
							->where([
								'pers.pers_token' => $parametrosArray['token_personal'],
								'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
								'emp.empresa_token' => $usuario->empresa_token,
							])
							->limit(1)->update(
								array(
									'people.materno' => $JwtAuth->encriptar($parametrosArray['personal_cont_materno']),
								)
							);

						if ($updatePaterno) {
							$validateInsertpersonal_cont_materno = 'true';
						} else {
							return response()->json([
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'nomcom',
								'message' => 'No fue actualizado el apellido materno del personal de su proveedor, comuniquese a soporte para resolver este problema'
							]);
						}
					} else {
						return response()->json([
							'status' => 'error',
							'code' => 200,
							'codeProvGenError' => 'nomcom',
							'message' => 'Error en apellido materno del personal de su proveedor, revise su información o comuniquese a soporte'
						]);
					}
				} else {
					$validateInsertpersonal_cont_materno = 'true';
				}

				if (isset($parametrosArray['personal_cont_nombre']) && !empty($parametrosArray['personal_cont_nombre'])) {
					if (preg_match($patron, $parametrosArray['personal_cont_nombre'])) {
						$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
							->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
							->join("personas AS people", "pers.empleado_name", "=", "people.id")
							->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
							->where([
								'pers.pers_token' => $parametrosArray['token_personal'],
								'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
								'emp.empresa_token' => $usuario->empresa_token,
							])
							->limit(1)->update(
								array(
									'people.nombre' => $JwtAuth->encriptar($parametrosArray['personal_cont_nombre']),
								)
							);

						if ($updatePaterno) {
							$validateInsertpersonal_cont_nombre = 'true';
						} else {
							return response()->json([
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'nomcom',
								'message' => 'No fue actualizado el nombre del personal de su proveedor, comuniquese a soporte para resolver este problema'
							]);
						}
					} else {
						return response()->json([
							'status' => 'error',
							'code' => 200,
							'codeProvGenError' => 'nomcom',
							'message' => 'Error en nombre del personal de su proveedor, revise su información o comuniquese a soporte'
						]);
					}
				} else {
					$validateInsertpersonal_cont_nombre = 'true';
				}

				if (isset($parametrosArray['personal_cont_area']) && !empty($parametrosArray['personal_cont_area'])) {
					if (preg_match($patron, $parametrosArray['personal_cont_area'])) {
						$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
							->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
							->join("area AS areaemp", "pers.area", "=", "areaemp.id")
							->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
							->where([
								'pers.pers_token' => $parametrosArray['token_personal'],
								'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
								'emp.empresa_token' => $usuario->empresa_token,
							])
							->limit(1)->update(
								array(
									'areaemp.areaemp' => $JwtAuth->encriptar($parametrosArray['personal_cont_area']),
								)
							);

						if ($updatePaterno) {
							$validateInsertpersonal_cont_area = 'true';
						} else {
							return response()->json([
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'nomcom',
								'message' => 'No fue actualizada el área de trabajo del personal de su proveedor, comuniquese a soporte para resolver este problema'
							]);
						}
					} else {
						return response()->json([
							'status' => 'error',
							'code' => 200,
							'codeProvGenError' => 'nomcom',
							'message' => 'Error en área de trabajo del personal de su proveedor, revise su información o comuniquese a soporte'
						]);
					}
				} else {
					$validateInsertpersonal_cont_area = 'true';
				}

				if (isset($parametrosArray['personal_cont_cargo']) && !empty($parametrosArray['personal_cont_cargo'])) {
					if (preg_match($patron, $parametrosArray['personal_cont_cargo'])) {
						$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
							->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
							->join("cargo AS ocup", "pers.cargo", "=", "ocup.id")
							->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
							->where([
								'pers.pers_token' => $parametrosArray['token_personal'],
								'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
								'emp.empresa_token' => $usuario->empresa_token,
							])
							->limit(1)->update(
								array(
									'ocup.cargo' => $JwtAuth->encriptar($parametrosArray['personal_cont_cargo']),
								)
							);

						if ($updatePaterno) {
							$validateInsertpersonal_cont_cargo = 'true';
						} else {
							return response()->json([
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'nomcom',
								'message' => 'No fue actualizado el cargo de trabajo del personal de su proveedor, comuniquese a soporte para resolver este problema'
							]);
						}
					} else {
						return response()->json([
							'status' => 'error',
							'code' => 200,
							'codeProvGenError' => 'nomcom',
							'message' => 'Error en cargo de trabajo del personal de su proveedor, revise su información o comuniquese a soporte'
						]);
					}
				} else {
					$validateInsertpersonal_cont_cargo = 'true';
				}

				if (
					$validateInsertpersonal_cont_paterno == 'true' && $validateInsertpersonal_cont_materno == 'true' &&
					$validateInsertpersonal_cont_nombre == 'true' && $validateInsertpersonal_cont_area == 'true' &&
					$validateInsertpersonal_cont_cargo == 'true'
				) {
					$dataMensaje = array(
						'status' => 'success',
						'code' => 200,
						'message' => 'datos generales de su proveedor han sido actualizados'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function nuevoTelefonoPersonal(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
				'personal_etiqueta' => 'required|string',
				'personal_icon' => 'required|string',
				'personal_telefono' => 'required|numeric',
				'personal_extension' => 'numeric'
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';

				$personal_etiqueta = $parametrosArray['personal_etiqueta'];
				$personal_icon = $parametrosArray['personal_icon'];
				$personal_telefono = $parametrosArray['personal_telefono'];
				$personal_extension = $parametrosArray['personal_extension'];

				if (
					isset($personal_etiqueta) && !empty($personal_etiqueta) && preg_match($patron, $personal_etiqueta) &&
					isset($personal_icon) && !empty($personal_icon) && preg_match($patron, $personal_icon) &&
					isset($personal_telefono) && !empty($personal_telefono) && preg_match($patronNum, $personal_telefono)
				) {
					$txtpersonal_telefono = $JwtAuth->encriptar($parametrosArray['personal_telefono']);
					$idenfiticaPers = DB::select("SELECT id FROM telefonos_personal WHERE telefono = ?", [$txtpersonal_telefono]);
					//echo count($idenfiticaPers);
					if (count($idenfiticaPers) == 0) {
						$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
							AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

						//da_te_default_timezone_set($selectEmp[0]->zona_horaria);
						$validateInsertpersonal_extension = 'true';
						$txtpersonal_extension = '';

						if (isset($parametrosArray['personal_extension'])) {
							if (!empty($parametrosArray['personal_extension'])) {
								if (preg_match($patronNum, $parametrosArray['personal_extension'])) {
									$validateInsertpersonal_extension = 'true';
									$txtpersonal_extension = $JwtAuth->encriptar($parametrosArray['personal_extension']);
								} else {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'message' => 'Error en extension del telefono del personal de su proveedor, revise su información o comuniquese a soporte'
									);
								}
							} else {
								$validateInsertpersonal_extension = 'true';
							}
						} else {
							$validateInsertpersonal_extension = 'true';
						}

						if ($validateInsertpersonal_extension == 'true') {
							$selectPers = DB::select(
								"SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
								JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
								AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
								[$parametrosArray['token_personal'], $parametrosArray['token_proveedor'], $usuario->empresa_token]
							);

							$tokenPerTel = $JwtAuth->encriptarToken($parametrosArray['token_personal'] . $parametrosArray['token_proveedor'] .
								$txtpersonal_telefono, $txtpersonal_extension);
							$insertatelefonos_personal = DB::table('telefonos_personal')
								->insert(array(
									"personal" => $selectPers[0]->id,
									"token_telefono" => $tokenPerTel,
									"icono" => $personal_icon,
									"etiqueta" => $personal_etiqueta,
									"telefono" => $txtpersonal_telefono,
									"extension" => $txtpersonal_extension,
									"status_telefono" => TRUE,
									"fecha_delete_tel" => ''
								));
							if ($insertatelefonos_personal) {

								$folio_db_prov = DB::select("SELECT folio,post_folio FROM catalogo_proveedores
							        WHERE token_cat_proveedores = ?", [$parametrosArray['token_proveedor']]);

								if ($folio_db_prov[0]->post_folio == NULL) {
									$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio);
								} else {
									$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio) . '-' . $folio_db_prov[0]->post_folio;
								}

								$JwtAuth->insertBitacoraActividad(
									'egresos',
									'catalogos',
									'proveedores',
									$folio_prov,
									'registro de telefono para personal de proveedor',
									$usuario->empresa_token,
									$usuario->user_token
								);
								$JwtAuth->insertBitacoraProv(
									$parametrosArray['token_proveedor'],
									'registro de telefono para personal de proveedor',
									$usuario->empresa_token,
									$usuario->user_token
								);

								$dataMensaje = array(
									'status' => 'success',
									'code' => 200,
									'message' => 'el nuevo telefono del personal de su proveedor ha sido registrado'
								);
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'No fue registrado el nuevo telefono del personal de su proveedor, comuniquese a soporte para resolver este problema'
								);
							}
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'El telefono ' . $parametrosArray['personal_telefono'] . ' ya ha sido registrado enteriormente, revise su información o comuniquese a soporte'
						);
					}
				} else {
					if (!isset($personal_etiqueta) || empty($personal_etiqueta) || !preg_match($patron, $personal_etiqueta)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en etiqueta del telefono que intenta registrar, revise su información o comuniquese a soporte'
						);
					}
					if (!isset($personal_icon) || empty($personal_icon) || !preg_match($patron, $personal_icon)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en icono del telefono que intenta registrar, revise su información o comuniquese a soporte'
						);
					}
					if (!isset($personal_telefono) || empty($personal_telefono) || !preg_match($patronNum, $personal_telefono)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en telefono del personal de su proveedor, revise su información o comuniquese a soporte'
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
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function actualizaTelefonoPersonal(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
				'token_telefono' => 'required|string',
				'personal_etiqueta' => 'required|string',
				'personal_icon' => 'required|string',
				'personal_telefono' => 'required|numeric',
				'personal_extension' => 'numeric'
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';

				$personal_etiqueta = $parametrosArray['personal_etiqueta'];
				$personal_icon = $parametrosArray['personal_icon'];
				$personal_telefono = $parametrosArray['personal_telefono'];
				$personal_extension = $parametrosArray['personal_extension'];

				if (
					isset($personal_etiqueta) && !empty($personal_etiqueta) && preg_match($patron, $personal_etiqueta) &&
					isset($personal_icon) && !empty($personal_icon) && preg_match($patron, $personal_icon) &&
					isset($personal_telefono) && !empty($personal_telefono) && preg_match($patronNum, $personal_telefono)
				) {

					$txtpersonal_telefono = $JwtAuth->encriptar($parametrosArray['personal_telefono']);
					if (isset($personal_extension) && !empty($personal_extension)) {
						if (preg_match($patron, $personal_extension)) {
							$personal_extension = $JwtAuth->encriptar($personal_extension);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en extension del telefono del personal de su proveedor, revise su información o comuniquese a soporte'
							);
						}
					} else {
						$personal_extension = '';
					}

					$updateTelefono = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
						->join("telefonos_personal AS telpers", "pers.id", "=", "telpers.empleado_name")
						->join("personas AS people", "pers.empleado_name", "=", "people.id")
						->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
							'pers.pers_token' => $parametrosArray['token_personal'],
							'telpers.token_telefono' => $parametrosArray['token_telefono'],
							'emp.empresa_token' => $usuario->empresa_token,
						])
						->limit(1)->update(
							array(
								'telpers.icono' => $personal_icon,
								'telpers.etiqueta' => $personal_etiqueta,
								'telpers.telefono' => $txtpersonal_telefono,
								'telpers.extension' => $personal_extension,
							)
						);

					if ($updateTelefono) {
						$folio_db_prov = DB::select("SELECT folio,post_folio FROM catalogo_proveedores
							WHERE token_cat_proveedores = ?", [$parametrosArray['token_proveedor']]);

						if ($folio_db_prov[0]->post_folio == NULL) {
							$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio);
						} else {
							$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_db_prov[0]->folio) . '-' . $folio_db_prov[0]->post_folio;
						}

						$JwtAuth->insertBitacoraActividad(
							'egresos',
							'catalogos',
							'proveedores',
							$folio_prov,
							'actualización de telefono para personal de proveedor',
							$usuario->empresa_token,
							$usuario->user_token
						);
						$JwtAuth->insertBitacoraProv(
							$parametrosArray['token_proveedor'],
							'actualización de telefono para personal de proveedor',
							$usuario->empresa_token,
							$usuario->user_token
						);

						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'telefono del personal de su proveedor han sido actualizados'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'No fue actualizado el telefono del personal de su proveedor, comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					if (!isset($personal_etiqueta) || empty($personal_etiqueta) || !preg_match($patron, $personal_etiqueta)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en etiqueta del telefono que intenta actualizar, revise su información o comuniquese a soporte'
						);
					}
					if (!isset($personal_icon) || empty($personal_icon) || !preg_match($patron, $personal_icon)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en icono del telefono que intenta actualizar, revise su información o comuniquese a soporte'
						);
					}
					if (!isset($personal_telefono) || empty($personal_telefono) || !preg_match($patronNum, $personal_telefono)) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en telefono del personal de su proveedor, revise su información o comuniquese a soporte'
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
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function eliminaTelefonoPersonal(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
				'token_telefono' => 'required|string'

			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$token_personal = $parametrosArray['token_personal'];
				$token_telefono = $parametrosArray['token_telefono'];
				$countarrayredesSoc = 0;
				$countRedesVacias = 0;

				if (isset($token_personal) && !empty($token_personal) && isset($token_telefono) && !empty($token_telefono)) {
					$countPers = DB::select(
						"SELECT telpers.id FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE telpers.status_telefono = TRUE AND telpers.empleado_name = pers.id AND pers.cat_proveedores = catprov.id 
						AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					$idenfiticaPers = DB::select(
						"SELECT telpers.id FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE telpers.token_telefono = ? AND telpers.status_telefono = TRUE AND telpers.empleado_name = pers.id AND pers.pers_token = ? 
						AND pers.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$token_telefono, $token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					if (count($idenfiticaPers) == 1) {
						if (count($countPers) > 1) {
							$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
								->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
								->join("telefonos_personal AS telpers", "pers.id", "=", "telpers.empleado_name")
								->join("personas AS people", "pers.empleado_name", "=", "people.id")
								->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
								->where([
									'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
									'pers.pers_token' => $parametrosArray['token_personal'],
									'telpers.token_telefono' => $parametrosArray['token_telefono'],
									'emp.empresa_token' => $usuario->empresa_token,
								])
								->limit(1)->update(
									array(
										'telpers.status_telefono' => FALSE,
										'telpers.fecha_delete_tel' => time(),
									)
								);

							if ($updatePaterno) {
								$dataMensaje = array(
									'status' => 'success',
									'code' => 200,
									'message' => 'telefono eliminado'
								);
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'telefono del personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
								);
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'no es posible eliminar este registro debido a que no existe mas personal de contacto para su proveedor'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'telefono identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'telefono invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function restartTelefonoPersonal(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
				'token_telefono' => 'required|string'

			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
				    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$token_personal = $parametrosArray['token_personal'];
				$token_telefono = $parametrosArray['token_telefono'];
				$countarrayredesSoc = 0;
				$countRedesVacias = 0;

				if (isset($token_personal) && !empty($token_personal) && isset($token_telefono) && !empty($token_telefono)) {
					$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
						->join("telefonos_personal AS telpers", "pers.id", "=", "telpers.empleado_name")
						->join("personas AS people", "pers.empleado_name", "=", "people.id")
						->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
							'pers.pers_token' => $parametrosArray['token_personal'],
							'telpers.token_telefono' => $parametrosArray['token_telefono'],
							'emp.empresa_token' => $usuario->empresa_token,
						])
						->limit(1)->update(
							array(
								'telpers.status_telefono' => TRUE,
								'telpers.fecha_delete_tel' => '',
							)
						);

					if ($updatePaterno) {
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'telefono restaurado'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'telefono del personal de su proveedor no fue restaurado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'telefono invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function eliminaTelefonoPersonalPermanente(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
				'token_telefono' => 'required|string'

			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
				    WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$token_personal = $parametrosArray['token_personal'];
				$token_telefono = $parametrosArray['token_telefono'];
				$countarrayredesSoc = 0;
				$countRedesVacias = 0;

				if (isset($token_personal) && !empty($token_personal) && isset($token_telefono) && !empty($token_telefono)) {

					$idenfiticaPers = DB::select(
						"SELECT telpers.id FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE telpers.token_telefono = ? AND telpers.status_telefono = FALSE AND telpers.empleado_name = pers.id AND pers.pers_token = ? 
						AND pers.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$token_telefono, $token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					if (count($idenfiticaPers) == 1) {
						$deleteTelefono = DB::select(
							"DELETE telpers.* FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers 
							JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE pers.id = telpers.empleado_name 
							AND telpers.token_telefono = ? AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
							AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
							[$token_telefono, $token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
						);

						$selectProhne = DB::select(
							"SELECT telpers.id FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers 
							JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE pers.id = telpers.empleado_name 
							AND telpers.token_telefono = ? AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
							AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
							[$token_telefono, $token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
						);

						if (count($selectProhne) == 0) {
							$dataMensaje = array(
								'status' => 'success',
								'code' => 200,
								'message' => 'telefono eliminado permanentemente'
							);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'telefono del personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'telefono identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'telefono invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function nuevoCorreoPersonal(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
				'personal_correo' => 'required|string'

			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
				    WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$txtpersonal_correo = $JwtAuth->encriptar($parametrosArray['personal_correo']);
				$idenfiticaPers = DB::select("SELECT id FROM correos_personal WHERE correo = ?", [$txtpersonal_correo]);

				if (
					isset($parametrosArray['personal_correo']) && !empty($parametrosArray['personal_correo']) && preg_match($patronMail, $parametrosArray['personal_correo'])
					&& count($idenfiticaPers) == 0
				) {
					$selectPers = DB::select(
						"SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
						AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$parametrosArray['token_personal'], $parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					$tokenPerMail = $JwtAuth->encriptarToken($parametrosArray['token_personal'] . $parametrosArray['token_proveedor'] . $txtpersonal_correo);
					$insertacorreos_personal = DB::table('correos_personal')
						->insert(array(
							"personal" => $selectPers[0]->id,
							"token_correo" => $tokenPerMail,
							"correo" => $txtpersonal_correo,
							'status_correo' => TRUE,
							'fecha_delete_correo' => '',
						));

					if ($insertacorreos_personal) {
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'el nuevo correo electrónico del personal de su proveedor ha sido registrado'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'No fue registrado el nuevo correo electrónico del personal de su proveedor, comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					if (!isset($parametrosArray['personal_correo']) || empty($parametrosArray['personal_correo']) || !preg_match($patronMail, $parametrosArray['personal_correo'])) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Error en correo electrónico del personal de su proveedor, revise su información o comuniquese a soporte'
						);
					}

					if (count($idenfiticaPers) > 0) {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'El correo electrónico ' . $parametrosArray['personal_correo'] . ' ya ha sido registrado enteriormente, revise su información o comuniquese a soporte'
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
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function actualizaCorreoPersonal(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
				'token_correo' => 'required|string',
				'personal_correo' => 'required|string',

			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				if (isset($parametrosArray['personal_correo']) && !empty($parametrosArray['personal_correo']) && preg_match($patronMail, $parametrosArray['personal_correo'])) {
					$updateCorreo = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
						->join("correos_personal AS mailpers", "pers.id", "=", "mailpers.empleado_name")
						->join("personas AS people", "pers.empleado_name", "=", "people.id")
						->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
							'pers.pers_token' => $parametrosArray['token_personal'],
							'mailpers.token_correo' => $parametrosArray['token_correo'],
							'emp.empresa_token' => $usuario->empresa_token,
						])
						->limit(1)->update(
							array(
								'mailpers.correo' => $JwtAuth->encriptar(strtolower($parametrosArray['personal_correo'])),
							)
						);

					if ($updateCorreo) {
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'correo electrónico del personal de su proveedor ha sido actualizado'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'No fue actualizado el correo electrónico del personal de su proveedor, comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Error en correo electrónico del personal de su proveedor, revise su información o comuniquese a soporte'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function eliminaCorreoPersonal(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
				'token_correo' => 'required|string'

			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$token_personal = $parametrosArray['token_personal'];
				$token_correo = $parametrosArray['token_correo'];

				if (isset($token_personal) && !empty($token_personal) && isset($token_correo) && !empty($token_correo)) {
					$countPers = DB::select(
						"SELECT mailpers.id FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE mailpers.status_correo = TRUE AND mailpers.empleado_name = pers.id AND pers.cat_proveedores = catprov.id 
						AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					$idenfiticaPers = DB::select(
						"SELECT mailpers.id FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE mailpers.token_correo = ? AND mailpers.status_correo = TRUE AND mailpers.empleado_name = pers.id AND pers.pers_token = ? 
						AND pers.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$token_correo, $token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					if (count($idenfiticaPers) == 1) {
						if (count($countPers) > 1) {
							$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
								->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
								->join("correos_personal AS mailpers", "pers.id", "=", "mailpers.empleado_name")
								->join("personas AS people", "pers.empleado_name", "=", "people.id")
								->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
								->where([
									'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
									'pers.pers_token' => $parametrosArray['token_personal'],
									'mailpers.token_correo' => $parametrosArray['token_correo'],
									'emp.empresa_token' => $usuario->empresa_token,
								])
								->limit(1)->update(
									array(
										'mailpers.status_correo' => FALSE,
										'mailpers.fecha_delete_correo' => time(),
									)
								);

							if ($updatePaterno) {
								$dataMensaje = array(
									'status' => 'success',
									'code' => 200,
									'message' => 'correo electrónico eliminado'
								);
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'correo electrónico del personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
								);
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'no es posible eliminar este registro debido a que no existe mas personal de contacto para su proveedor'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'correo electrónico identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'correo electrónico invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function restartCorreoPersonal(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
				'token_correo' => 'required|string'

			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$token_personal = $parametrosArray['token_personal'];
				$token_correo = $parametrosArray['token_correo'];
				$countarrayredesSoc = 0;
				$countRedesVacias = 0;

				if (isset($token_personal) && !empty($token_personal) && isset($token_correo) && !empty($token_correo)) {
					$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
						->join("correos_personal AS mailpers", "pers.id", "=", "mailpers.empleado_name")
						->join("personas AS people", "pers.empleado_name", "=", "people.id")
						->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
							'pers.pers_token' => $parametrosArray['token_personal'],
							'mailpers.token_correo' => $parametrosArray['token_correo'],
							'emp.empresa_token' => $usuario->empresa_token,
						])
						->limit(1)->update(
							array(
								'mailpers.status_correo' => TRUE,
								'mailpers.fecha_delete_correo' => '',
							)
						);

					if ($updatePaterno) {
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'correo electrónico restaurado'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'correo electrónico del personal de su proveedor no fue restaurado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'correo electrónico invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function eliminaCorreoPersonalPermanente(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
				'token_correo' => 'required|string'

			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$token_personal = $parametrosArray['token_personal'];
				$token_correo = $parametrosArray['token_correo'];
				$countarrayredesSoc = 0;
				$countRedesVacias = 0;

				if (isset($token_personal) && !empty($token_personal) && isset($token_correo) && !empty($token_correo)) {
					$countPers = DB::select(
						"SELECT mailpers.id FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE mailpers.status_telefono = TRUE AND mailpers.empleado_name = pers.id AND pers.cat_proveedores = catprov.id 
						AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					$idenfiticaPers = DB::select(
						"SELECT mailpers.id FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE mailpers.token_correo = ? AND mailpers.status_telefono = TRUE AND mailpers.empleado_name = pers.id AND pers.pers_token = ? 
						AND pers.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$token_correo, $token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					if (count($idenfiticaPers) == 1) {
						$deleteCorreo = DB::select(
							"DELETE mailpers.* FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers 
							JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE mailpers.token_correo = ? AND 
							pers.id = mailpers.empleado_name AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
							AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
							[$token_correo, $token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
						);

						$selectCorreo = DB::select(
							"SELECT mailpers.* FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers 
							JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE mailpers.token_correo = ? AND 
							pers.id = mailpers.empleado_name AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
							AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
							[$token_correo, $token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
						);

						if (count($selectCorreo) == 0) {
							$dataMensaje = array(
								'status' => 'success',
								'code' => 200,
								'message' => 'correo electrónico eliminado permanentemente'
							);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'correo electrónico del personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'correo electrónico identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'correo electrónico invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function restartPersonalProv(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$token_personal = $parametrosArray['token_personal'];
				$countarrayredesSoc = 0;
				$countRedesVacias = 0;

				if (isset($token_personal) && !empty($token_personal)) {


					$updatePaterno = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("vhum_empleados_catalogo AS pers", "catprov.id", "=", "pers.cat_proveedores")
						->join("main_empresas AS emp", "catprov.administrador", "=", "emp.id")
						->where([
							'pers.pers_token' => $token_personal,
							'catprov.token_cat_proveedores' => $parametrosArray['token_proveedor'],
							'emp.empresa_token' => $usuario->empresa_token,
						])
						->limit(1)->update(
							array(
								'pers.status' => TRUE,
								'pers.fecha_delete' => '',
							)
						);

					if ($updatePaterno) {
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'personal restaurado'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'personal de su proveedor no fue restaurado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'personal invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function deletePersonalProvPermanente(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_proveedor' => 'required|string',
				'token_personal' => 'required|string',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido',
					'errors' => $validate->errors()
				);
			} else {
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$token_personal = $parametrosArray['token_personal'];
				$countarrayredesSoc = 0;
				$countRedesVacias = 0;

				if (isset($token_personal) && !empty($token_personal)) {
					$countPers = DB::select(
						"SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.cat_proveedores = catprov.id 
						AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					$idenfiticaPers = DB::select(
						"SELECT pers.id FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE pers.status = TRUE AND pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
						AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
						[$token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
					);

					if (count($idenfiticaPers) == 1) {
						if (count($countPers) > 1) {

							$deletePersonas = DB::select(
								"DELETE people.* FROM personas AS people JOIN vhum_empleados_catalogo AS pers 
								JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE people.id = pers.empleado_name AND 
								pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
								AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
								[$token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
							);

							$deleteArea = DB::select(
								"DELETE are.* FROM area AS aree JOIN vhum_empleados_catalogo AS pers 
								JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE are.id = pers.area AND 
								pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
								AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
								[$token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
							);

							$deleteCargo = DB::select(
								"DELETE ocup.* FROM cargo AS ocup JOIN vhum_empleados_catalogo AS pers 
								JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE ocup.id = pers.cargo AND 
								pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
								AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
								[$token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
							);

							$deleteTelefono = DB::select(
								"DELETE telpers.* FROM telefonos_personal AS telpers JOIN vhum_empleados_catalogo AS pers 
								JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE pers.id = telpers.empleado_name AND 
								pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
								AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
								[$token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
							);

							$deleteCorreo = DB::select(
								"DELETE mailpers.* FROM correos_personal AS mailpers JOIN vhum_empleados_catalogo AS pers 
								JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp WHERE pers.id = mailpers.empleado_name AND 
								pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
								AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
								[$token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
							);


							$deletePersonal = DB::select(
								"DELETE pers.* FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
								JOIN main_empresas AS emp WHERE pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
								AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
								[$token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
							);


							$selectPersonal = DB::select(
								"SELECT pers.* FROM vhum_empleados_catalogo AS pers JOIN eegr_catalogo_proveedores AS catprov 
								JOIN main_empresas AS emp WHERE pers.pers_token = ? AND pers.cat_proveedores = catprov.id 
								AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ?",
								[$token_personal, $parametrosArray['token_proveedor'], $usuario->empresa_token]
							);


							if (count($selectPersonal)) {
								$dataMensaje = array(
									'status' => 'success',
									'code' => 200,
									'message' => 'personal eliminado permanentemente'
								);
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'personal de su proveedor no fue eliminado, intentelo nuevamente o comuniquese a soporte para resolver este problema'
								);
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'no es posible eliminar este registro debido a que no existe mas personal de contacto para su proveedor'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'personal identificado ' . count($idenfiticaPers) . ' veces, intentelo nuevamente o comuniquese a soporte para resolver este problema'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'personal invalido, intentelo nuevamente o comuniquese a soporte para resolver este problema'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function updatecontanciafiscalsitload(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$imagenAltaPdfFiscal = $request->file('imagenAltaPdfFiscal');

		$jsonData = $request->input('proveedor');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string'
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$selectFolio = DB::select("SELECT catprov.const_sit_fiscal,catprov.fechaAlta,catprov.folio,emp.root_tkn FROM catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
					JOIN teci_usuarios_catalogo AS users WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
					AND empuser.usuario = users.id AND users.usuario_token= ?",[$parametrosArray['token_cat_proveedores'], $usuario->empresa_token, $usuario->user_token]);

				$filepath = $selectFolio[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . "/";

				if (!file_exists(storage_path("/root/" . $filepath))) {
					Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
				}

				if (file_exists($request->file('imagenAltaPdfFiscal'))) {
					//eliminar imagen anterior
					$nombre_imagen = $request->file('imagenAltaPdfFiscal')->getClientOriginalName();
					Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfFiscal'), $nombre_imagen);

					$updateImg = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("main_empresas AS emp", "catprov.administrador", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])
						->limit(1)->update(
							array(
								'catprov.const_sit_fiscal' => $JwtAuth->encriptar($nombre_imagen),
							)
						);

					if ($updateImg) {
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Constancia de situación fiscal actualizada'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Constancia de situación fiscal no ha sido actualizada, intente nuevamente o comuniquese a soporte'
						);
					}
				}
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

	public function updatecontanciafiscalsitbase64(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$imagenAltaPdfFiscal = $request->file('imagenAltaPdfFiscal');

		$jsonData = $request->input('data_producto');

		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);
		$jsonUser = $request->input('user_token');
		$parametrosUser = json_decode($jsonUser);
		$parametrosArrayUser = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string'
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$selectFolio = DB::select("SELECT catprov.const_sit_fiscal,catprov.fechaAlta,catprov.folio,emp.root_tkn FROM catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
					JOIN teci_usuarios_catalogo AS users WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
					AND empuser.usuario = users.id AND users.usuario_token= ?",[$parametrosArray['token_cat_proveedores'], $usuario->empresa_token, $usuario->user_token]);

				$filepath = $selectFolio[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . "/";

				if (!file_exists(storage_path("/root/" . $filepath))) {
					Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
				}

				if (file_exists($request->file('imagenAltaPdfFiscal'))) {
					//eliminar imagen anterior
					$nombre_imagen = $request->file('imagenAltaPdfFiscal')->getClientOriginalName();
					Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfFiscal'), $nombre_imagen);

					$updateImg = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("main_empresas AS emp", "catprov.administrador", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])
						->limit(1)->update(
							array(
								'catprov.const_sit_fiscal' => $JwtAuth->encriptar($nombre_imagen),
							)
						);

					if ($updateImg) {
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Constancia de situación fiscal actualizada'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Constancia de situación fiscal no ha sido actualizada, intente nuevamente o comuniquese a soporte'
						);
					}
				}
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

	public function updatecumplimientoload(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('proveedor');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make(
				$parametrosArray,
				[
					'user_token' => 'required|string',
					'token_cat_proveedores' => 'required|string'
				]
			);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$selectFolio = DB::select("SELECT catprov.opinion_cumplimiento,catprov.fechaAlta,catprov.folio,emp.root_tkn FROM catalogo_proveedores AS catprov JOIN main_empresas AS emp 
				    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? 
				    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",[$parametrosArray['token_cat_proveedores'], $usuario->empresa_token, $usuario->user_token]);

				$filepath = $selectFolio[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . "/";

				if (!file_exists(storage_path("/root/" . $filepath))) {
					Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
				}

				if (file_exists($request->file('imagenAltaPdfCumplimientoObFiscales'))) {
					//eliminar imagen anterior
					$nombre_imagen = $request->file('imagenAltaPdfCumplimientoObFiscales')->getClientOriginalName();
					Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfCumplimientoObFiscales'), $nombre_imagen);

					$updateImg = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("main_empresas AS emp", "catprov.administrador", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])
						->limit(1)->update(
							array(
								'catprov.opinion_cumplimiento' => $JwtAuth->encriptar($nombre_imagen),
							)
						);

					if ($updateImg) {
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Constancia de cumplimiento de obligaciones fiscales actualizada'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Constancia de cumplimiento de obligaciones fiscales no ha sido actualizada, intente nuevamente o comuniquese a soporte'
						);
					}
				}
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

	public function updatecumplimientobase64(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('data_proveedor');

		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);
		$jsonUser = $request->input('user_token');
		$parametrosUser = json_decode($jsonUser);
		$parametrosArrayUser = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string'
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$selectFolio = DB::select("SELECT catprov.opinion_cumplimiento,catprov.fechaAlta,catprov.folio,emp.root_tkn FROM catalogo_proveedores AS catprov JOIN main_empresas AS emp 
				    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? 
				    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",[$parametrosArray['token_cat_proveedores'], $usuario->empresa_token, $usuario->user_token]);

				$filepath = $selectFolio[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . "/";

				if (!file_exists(storage_path("/root/" . $filepath))) {
					Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
				}

				if (file_exists($request->file('imagenAltaPdfCumplimientoObFiscales'))) {
					//eliminar imagen anterior
					$nombre_imagen = $request->file('imagenAltaPdfCumplimientoObFiscales')->getClientOriginalName();
					Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfCumplimientoObFiscales'), $nombre_imagen);

					$updateImg = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("main_empresas AS emp", "catprov.administrador", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])
						->limit(1)->update(
							array(
								'catprov.opinion_cumplimiento' => $JwtAuth->encriptar($nombre_imagen),
							)
						);

					if ($updateImg) {
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Constancia de cumplimiento de obligaciones fiscales actualizada'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Constancia de cumplimiento de obligaciones fiscales no ha sido actualizada, intente nuevamente o comuniquese a soporte'
						);
					}
				}
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

	public function updateCreditosProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('json');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
				'creditos' => 'required|array',
				'decideaceptcredito' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
				$patronNumCred = '/^[0-9$,.-]*$/';
				$patronRfc = '/[aA0-zZ9]/';
				$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$arrayCred = $parametrosArray['creditos'];

				for ($i = 0; $i < count($arrayCred); $i++) {

					if (preg_match($patron, $parametrosArray['decideaceptcredito'])) {
						if ($parametrosArray['decideaceptcredito'] == 'true') {
							if (
								isset($arrayCred[$i]['token_moneda']) && !empty($arrayCred[$i]['token_moneda']) &&
								preg_match($patronNumCred, $arrayCred[$i]['limite']) &&
								preg_match($patronNum, $arrayCred[$i]['dias']) &&
								preg_match($patron, $arrayCred[$i]['comienza'])
							) {
								$dbMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?", [$arrayCred[$i]['token_moneda']]);
								$selectlimiteCredito = $arrayCred[$i]['limite'];
								$selectdiaspagoCredit = $arrayCred[$i]['dias'];
								$selectComienzaPagoProv = $arrayCred[$i]['comienza'];

								$updateCreditoProv = DB::table('creditos AS cred')
									->join("eegr_catalogo_proveedores AS catprov", "cred.proveedor", "catprov.id")
									->join("main_empresas AS emp", "catprov.administrador", "emp.id")
									->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
									->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
									->where([
										'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
										'cred.token_creditos' => $arrayCred[$i]['token_creditos'],
										'emp.empresa_token' => $usuario->empresa_token,
										'users.usuario_token' => $usuario->user_token,
									])
									->update(array(
										"cred.aceptacredito" => TRUE,
										"cred.moneda" => $dbMoneda[0]->id,
										"cred.limite" => $selectlimiteCredito,
										"cred.dias" => $selectdiaspagoCredit,
										"cred.comienza" => $selectComienzaPagoProv,
									));

								if ($updateCreditoProv) {
									$dataMensaje = array(
										'status' => 'success',
										'code' => 200,
										'message' => 'Crédito del proveedor actualizado'
									);
								} else {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'message' => 'Actualización de crédito del proveedor no realizada, intente nuevamente o comuniquese a soporte'
									);
								}
							} else {
								if (!preg_match($patron, $arrayCred[$i]['txtMoneda'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'message' => 'Error en seleccion de moneda'
									);
								}
								if (!preg_match($patronNumCred, $arrayCred[$i]['txtlimiteCredito'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'message' => 'Error en limite de credito'
									);
								}

								if (!preg_match($patronNum, $arrayCred[$i]['txtdiaspagoCredit'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'message' => 'Error en seleccion de dias de pago'
									);
								}
								if (!preg_match($patron, $arrayCred[$i]['selectComienzaPagoProv'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'message' => 'Error en seleccion de comienzo de pago'
									);
								}
							}
						} else {
							$updateCreditoProv = DB::table('creditos AS cred')
								->join("eegr_catalogo_proveedores AS catprov", "cred.proveedor", "catprov.id")
								->join("main_empresas AS emp", "catprov.administrador", "emp.id")
								->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
								->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
								->where([
									'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
									'cred.token_creditos' => $arrayCred[$i]['token_creditos'],
									'emp.empresa_token' => $usuario->empresa_token,
									'users.usuario_token' => $usuario->user_token,
								])
								->update(array(
									"cred.aceptacredito" => FALSE,
									"cred.moneda" => NULL,
									"cred.limite" => NULL,
									"cred.dias" => NULL,
									"cred.comienza" => NULL,
								));

							if ($updateCreditoProv) {
								$dataMensaje = array(
									'status' => 'success',
									'code' => 200,
									'message' => 'Crédito del proveedor actualizado'
								);
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'Actualización de crédito del proveedor no realizada, intente nuevamente o comuniquese a soporte'
								);
							}
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Selecciona opcion de aceptacion de creditos'
						);
					}
				}
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

	public function updateFormaPagoProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('json');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
				'formaPago' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectFpago = DB::select("SELECT id FROM forma_pago WHERE token_formapago	= ?", [$parametrosArray['formaPago']]);
				$updateFormaPago = DB::table("eegr_catalogo_proveedores AS catprov")
					->join("main_empresas AS emp", "catprov.administrador", "emp.id")
					->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
					->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
					->where([
						'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
						'emp.empresa_token' => $usuario->empresa_token,
						'users.usuario_token' => $usuario->user_token,
					])
					->limit(1)->update(array('catprov.forma_pago' => $selectFpago[0]->id));

				if ($updateFormaPago) {
					$dataMensaje = array(
						'status' => 'success',
						'code' => 200,
						'message' => 'Forma de pago actualizada'
					);
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Forma de pago no ha sido actualizada, intente nuevamente o comuniquese a soporte'
					);
				}
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

	public function updatefPagoProveedorEstCuenta(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('proveedor');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$selectFolio = DB::select("SELECT catprov.const_sit_fiscal,catprov.fechaAlta,catprov.folio,emp.root_tkn FROM catalogo_proveedores AS catprov JOIN main_empresas AS emp 
				    JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.empresa_token = ? 
				    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?",[$parametrosArray['token_cat_proveedores'], $usuario->empresa_token, $usuario->user_token]);

				$filepath = $selectFolio[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . "/";
				if (file_exists($request->file('imagenPerfilPdfEstCuenta'))) {
					$doc_estado_cuenta = $JwtAuth->encriptar($JwtAuth->generar($selectFolio[0]->folio) . "-" . $selectFolio[0]->fechaAlta . '-' . $request->file('imagenPerfilPdfEstCuenta')->getClientOriginalName());

					$updateFormaPago = DB::table("eegr_catalogo_proveedores AS catprov")
						->join("main_empresas AS emp", "catprov.administrador", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
						->where([
							'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])
						->limit(1)->update(array('catprov.estado_cuenta' => $doc_estado_cuenta,));

					if ($updateFormaPago) {
						Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenPerfilPdfEstCuenta'), $JwtAuth->desencriptar($doc_estado_cuenta));
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Forma de pago actualizada'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Forma de pago no ha sido actualizada, intente nuevamente o comuniquese a soporte'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'El archivo que intenta cargar es invalido o vacio'
					);
				}
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

	public function updateClabeInterbPagoProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('json');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
				'formaPago' => 'required|array',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$patronNumCred = '/^[0-9$,.-]*$/';

				$forma_pago = $parametrosArray['formaPago'];

				for ($i = 0; $i < count($forma_pago); $i++) {
					if (isset($forma_pago[$i]['tipo_referencia_pago']) && !empty($forma_pago[$i]['tipo_referencia_pago'])) {
						if ($forma_pago[$i]['tipo_referencia_pago'] == 'clabeInterbancaria') {
							$tipoReferenciaPago = 'ci';
						} else if ($forma_pago[$i]['tipo_referencia_pago'] == 'convenio') {
							$tipoReferenciaPago = 'co';
						} else if ($forma_pago[$i]['tipo_referencia_pago'] == 'lineaCaptura') {
							$tipoReferenciaPago = 'lc';
						}

						if ($forma_pago[$i]['tipo_referencia_pago'] == 'clabeInterbancaria') {
							if (preg_match($patronNumCred, $forma_pago[$i]['clabe_Interbancaria'])) {
								$formaPagoClabeInterbank = $parametrosArray['clabe_Interbancaria'];
							} else {
								$formaPagoClabeInterbank = NULL;
							}
						} else {
							$formaPagoClabeInterbank = NULL;
						}

						$updateFormaPago = DB::table("eegr_catalogo_proveedores AS catprov")
							->join("main_empresas AS emp", "catprov.administrador", "emp.id")
							->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
							->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
							->where([
								'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
								'emp.empresa_token' => $usuario->empresa_token,
								'users.usuario_token' => $usuario->user_token,
							])
							->limit(1)->update(
								array(
									'catprov.tipo_referencia_pago' => $tipoReferenciaPago,
									'catprov.clabe_interbancaria' => $formaPagoClabeInterbank,
								)
							);
						if ($updateFormaPago) {
							$selectProvCat = DB::select("SELECT folio,post_folio FROM catalogo_proveedores 
								WHERE token_cat_proveedores = ?", [$parametrosArray['token_cat_proveedores']]);
							if ($selectProvCat[0]->post_folio == NULL) {
								$folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio);
							} else {
								$folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio) . '-' . $selectProvCat[0]->post_folio;
							}

							$JwtAuth->insertBitacoraActividad(
								'egresos',
								'catalogos',
								'proveedores',
								$folio_prov,
								'actualizacion de forma de pago de proveedor',
								$usuario->empresa_token,
								$usuario->user_token
							);
							$JwtAuth->insertBitacoraProv(
								$parametrosArray['token_cat_proveedores'],
								'actualizacion de forma de pago de proveedor',
								$usuario->empresa_token,
								$usuario->user_token
							);
							$dataMensaje = array(
								'status' => 'success',
								'code' => 200,
								'message' => 'Forma de pago actualizada'
							);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Forma de pago no ha sido actualizada, intente nuevamente o comuniquese a soporte'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Selecciona opcion de tipo de referencia de pago'
						);
					}
				}
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

	public function registraNuevaUbicacionNacionalProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
				'arrayubicacionNacionalProvv_reg' => 'required|array',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users 
				    WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);
				$ubicacionNacionalProvv_reg = $parametrosArray['arrayubicacionNacionalProvv_reg'];
				$contadorUbicaciones = 0;
				if (count($ubicacionNacionalProvv_reg) != 0) {
					//Fiscal
					for ($i = 0; $i < count($ubicacionNacionalProvv_reg); $i++) {
						if (
							preg_match($patron, $ubicacionNacionalProvv_reg[$i]['clase']) && preg_match($patron, $ubicacionNacionalProvv_reg[$i]['alias']) &&
							preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle']) && preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['num_ext']) &&
							!empty($ubicacionNacionalProvv_reg[$i]['cod_postal_nacprov'])
						) {

							if (
								$ubicacionNacionalProvv_reg[$i]['calle1'] == '-' && $ubicacionNacionalProvv_reg[$i]['calle2'] == '-' &&
								$ubicacionNacionalProvv_reg[$i]['referencia'] == '-'
							) {
								$contadorUbicaciones++;
							} else {
								if (
									preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle1']) &&
									preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle2']) &&
									preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['referencia'])
								) {

									if ($ubicacionNacionalProvv_reg[$i]['num_int'] != '-') {
										if (preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['num_int'])) {
											$contadorUbicaciones++;
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'positionErrorCodeFiscalNac' => $i,
												'message' => 'Error en número interiór de dirección nacional'
											);
										}
									} else {
										$contadorUbicaciones++;
									}
								} else {
									if (!preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle1'])) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'positionErrorCodeFiscalNac' => $i,
											'message' => 'Error en primera calle de referencia de dirección nacional'
										);
									}
									if (!preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle2'])) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'positionErrorCodeFiscalNac' => $i,
											'message' => 'Error en segunda calle de referencia de dirección nacional'
										);
									}
									if (!preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['referencia'])) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'positionErrorCodeFiscalNac' => $i,
											'message' => 'Error en lugar de referencia de dirección nacional'
										);
									}
								}
							}
						} else {
							if (!preg_match($patron, $ubicacionNacionalProvv_reg[$i]['clase'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCodeFiscalNac' => $i,
									'message' => 'Error en clasificacion de dirección nacional'
								);
							}
							if (!preg_match($patron, $ubicacionNacionalProvv_reg[$i]['alias'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCodeFiscalNac' => $i,
									'message' => 'Error en alias de dirección nacional'
								);
							}
							if (!preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCodeFiscalNac' => $i,
									'message' => 'Error en calle de dirección nacional'
								);
							}
							if (!preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['num_ext'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCodeFiscalNac' => $i,
									'message' => 'Error en número exteriór de dirección nacional'
								);
							}
							if (!preg_match($patronNum, $ubicacionNacionalProvv_reg[$i]['cod_postal_nacprov'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCodeFiscalNac' => $i,
									'message' => 'Error en código postal de dirección nacional'
								);
							}
						}
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Ingrese la dirección nacional de su proveedor'
					);
				}

				if ($contadorUbicaciones == count($ubicacionNacionalProvv_reg)) {
					$selectProvCat = DB::select("SELECT id,folio,post_folio FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$parametrosArray['token_cat_proveedores']]);
					$selectUbiCat = DB::select("SELECT ubica.id FROM teci_direcciones AS ubica JOIN eegr_catalogo_proveedores AS catprov 
					   WHERE ubica.proveedor = catprov.id AND catprov.token_cat_proveedores = ?", [$parametrosArray['token_cat_proveedores']]);
					$contadorInsertUbicaciones = 0;
					for ($i = 0; $i < count($ubicacionNacionalProvv_reg); $i++) {


						if (count($selectUbiCat) == 0) {
							if ($i == 0) {
								$tipo_direccion = 'dirección fiscal';
							} else {
								$tipo_direccion = 'dirección sucursal';
							}
						} else {
							$tipo_direccion = 'dirección sucursal';
						}

						$arrayClasificacion = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['clase']);
						$arrayalias = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['alias']);
						$arraycalle = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['calle']);
						$arraynum_ext = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['num_ext']);

						$queryIDDir = DB::select("SELECT id FROM codigos_postales WHERE	token_codigos_postales = ?", [$ubicacionNacionalProvv_reg[$i]['cod_postal_nacprov']]);

						if ($ubicacionNacionalProvv_reg[$i]['num_int'] != '-') {
							$arraynum_int = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['num_int']);
						} else {
							$arraynum_int = NULL;
						}

						if ($ubicacionNacionalProvv_reg[$i]['calle1'] != '-') {
							$arraycalle1 = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['calle1']);
						} else {
							$arraycalle1 = NULL;
						}

						if ($ubicacionNacionalProvv_reg[$i]['calle2'] != '-') {
							$arraycalle2 = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['calle2']);
						} else {
							$arraycalle2 = NULL;
						}

						if ($ubicacionNacionalProvv_reg[$i]['referencia'] != '-') {
							$arrayreferencia = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['referencia']);
						} else {
							$arrayreferencia = NULL;
						}

						$tokenCDir = $JwtAuth->encriptarToken($tipo_direccion . $arrayClasificacion . $arrayalias .
							$arraycalle . $arraynum_ext . $arraynum_int . $arraycalle1 . $arraycalle2 . $arrayreferencia);

						$fisinsertDir = DB::table('direcciones')
							->insert(array(
								"token_direccion" => $tokenCDir,
								"tipo_direccion" => $tipo_direccion,
								"clase" => $arrayClasificacion,
								"alias" => $arrayalias,
								"calle" => $arraycalle,
								"num_ext" => $arraynum_ext,
								"num_int" => $arraynum_int,
								"codigo_postal" => $queryIDDir[0]->id,
								"pais" => 118,
								"calle1" => $arraycalle1,
								"calle2" => $arraycalle2,
								"referencia" => $arrayreferencia,
								"proveedor" => $selectProvCat[0]->id,
								"status" => TRUE,
								"administrador" => $selectEmp[0]->id,
							));
						if ($fisinsertDir) {
							$contadorInsertUbicaciones++;
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Dirección con alias' . $ubicacionNacionalProvv_reg[$i]['alias'] . ' no registrada, intente nuevamente o comuniquese a soporte'
							);
						}
					}

					if ($contadorInsertUbicaciones == count($ubicacionNacionalProvv_reg)) {
						if ($selectProvCat[0]->post_folio == NULL) {
							$folio_prov = 'PRV-' . $JwtAuth->generarFolio($selectProvCat[0]->folio);
						} else {
							$folio_prov = 'PRV-' . $JwtAuth->generarFolio($selectProvCat[0]->folio) . '-' . $selectProvCat[0]->post_folio;
						}

						$JwtAuth->insertBitacoraActividad(
							'egresos',
							'catalogos',
							'proveedores',
							$folio_prov,
							'registro de direcciones de proveedor',
							$usuario->empresa_token,
							$usuario->user_token
						);
						$JwtAuth->insertBitacoraProv(
							$parametrosArray['token_cat_proveedores'],
							'registro de direcciones de proveedor',
							$usuario->empresa_token,
							$usuario->user_token
						);
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Direcciones registradas'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Direcciones no registradas, intente nuevamente o comuniquese a soporte'
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
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function registraNuevaUbicacionExtranjeroProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
				'pais' => 'required|string',
				'arrayubicacionExtranjeroProvv_reg' => 'required|array',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';

				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? 
					AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				$fechaAlta = time();

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$ubicacionExtranjeraProvv_reg = $parametrosArray['arrayubicacionExtranjeroProvv_reg'];
				$contadorUbicaciones = 0;
				if (count($ubicacionExtranjeraProvv_reg) != 0) {
					for ($i = 0; $i < count($ubicacionExtranjeraProvv_reg); $i++) {
						if (
							preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['clasificacion']) &&
							preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['alias']) &&
							preg_match($patronCpostal, $ubicacionExtranjeraProvv_reg[$i]['codigo_postal'])
						) {
							if ($ubicacionExtranjeraProvv_reg[$i]['direccion'] != '' && $ubicacionExtranjeraProvv_reg[$i]['direccion'] != '-') {
								if (preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['direccion'])) {
									$contadorUbicaciones++;
								} else {
									$contadorUbicaciones = 0;
									break;
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeSucursalExt' => $i,
										'message' => 'Error en dirección completa de dirección extranjera'
									);
								}
							} else {
								$contadorUbicaciones++;
							}
						} else {
							if (!preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['clasificacion'])) {
								break;
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCodeSucursalExt' => $i,
									'message' => 'Error en clasificacion de dirección extranjera'
								);
							}
							if (!preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['alias'])) {
								break;
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCodeSucursalExt' => $i,
									'message' => 'Error en alias de dirección extranjera'
								);
							}
							if (!preg_match($patronCpostal, $ubicacionExtranjeraProvv_reg[$i]['codigo_postal'])) {
								break;
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCodeSucursalExt' => $i,
									'message' => 'Error en código postal de dirección extranjera'
								);
							}
						}
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Ingrese la dirección extranjera de su proveedor'
					);
				}

				if ($contadorUbicaciones == count($ubicacionExtranjeraProvv_reg)) {
					$selectProvCat = DB::select("SELECT id,folio,post_folio FROM catalogo_proveedores 
						WHERE token_cat_proveedores = ?", [$parametrosArray['token_cat_proveedores']]);
					$contadorInsertUbicaciones = 0;
					for ($i = 0; $i < count($ubicacionExtranjeraProvv_reg); $i++) {
						$selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$parametrosArray['pais']]);
						if ($i == 0) {
							$tipo_direccion = 'dirección fiscal';
						} else {
							$tipo_direccion = 'dirección sucursal';
						}

						$clasificacionDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i]['clasificacion']);
						$aliasDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i]['alias']);
						$cpostalDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i]['codigo_postal']);
						$calleDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i]['direccion']);
						if (
							$ubicacionExtranjeraProvv_reg[$i]['direccion'] != '' &&
							$ubicacionExtranjeraProvv_reg[$i]['direccion'] != '-' &&
							preg_match($patron, $ubicacionExtranjeraProvv_reg[$i]['direccion'])
						) {
							$calleDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i]['direccion']);
						} else {
							$calleDir = NULL;
						}
						$tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $clasificacionDir, $aliasDir, $cpostalDir, $calleDir);

						$fisinsertDir = DB::table('direcciones')
							->insert(array(
								"token_direccion" => $tokenCDir,
								"tipo_direccion" => $tipo_direccion,
								"clase" => $clasificacionDir,
								"alias" => $aliasDir,
								"calle" => $calleDir,
								"cod_postalext" => $cpostalDir,
								"pais" => $selectPais[0]->id,
								"proveedor" => $selectProvCat[0]->id,
								"status" => TRUE,
								"administrador" => $selectEmp[0]->id,
							));
						if ($fisinsertDir) {
							$contadorInsertUbicaciones++;
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Dirección con alias' . $ubicacionExtranjeraProvv_reg[$i]['alias'] . ' no registrada, intente nuevamente o comuniquese a soporte'
							);
						}
					}

					if ($contadorInsertUbicaciones == count($ubicacionExtranjeraProvv_reg)) {

						if ($selectProvCat[0]->post_folio == NULL) {
							$folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio);
						} else {
							$folio_prov = 'prv-' . $JwtAuth->generarFolio($selectProvCat[0]->folio) . '-' . $selectProvCat[0]->post_folio;
						}
						$JwtAuth->insertBitacoraActividad(
							'egresos',
							'catalogos',
							'proveedores',
							$folio_prov,
							'registro de direcciones de proveedor',
							$usuario->empresa_token,
							$usuario->user_token
						);
						$JwtAuth->insertBitacoraProv(
							$parametrosArray['token_cat_proveedores'],
							'registro de direcciones de proveedor',
							$usuario->empresa_token,
							$usuario->user_token
						);

						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Direcciones registradas'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Direcciones no registradas, intente nuevamente o comuniquese a soporte'
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
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function updateUbicacionNacionalProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('json');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);
		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
				'dirUbicacion' => 'required|array'
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$ubicaFor = $parametrosArray['dirUbicacion'];
				$contadorUbicaciones = 0;
				for ($i = 0; $i < count($ubicaFor); $i++) {
					if (
						preg_match($patron, $ubicaFor[$i]['clasificacion']) && preg_match($patron, $ubicaFor[$i]['alias']) &&
						preg_match($patronCpostal, $ubicaFor[$i]['calle']) && preg_match($patronCpostal, $ubicaFor[$i]['num_ext']) &&
						!empty($ubicaFor[$i]['token_codigos_postales'])
					) {

						if (
							$ubicaFor[$i]['calle1'] == '-' &&
							$ubicaFor[$i]['calle2'] == '-' &&
							$ubicaFor[$i]['referencia'] == '-'
						) {
							$contadorUbicaciones++;
						} else {
							if (
								preg_match($patronCpostal, $ubicaFor[$i]['calle1']) &&
								preg_match($patronCpostal, $ubicaFor[$i]['calle2']) &&
								preg_match($patronCpostal, $ubicaFor[$i]['referencia'])
							) {

								if ($ubicaFor[$i]['num_int'] != '-') {
									if (preg_match($patronCpostal, $ubicaFor[$i]['num_int'])) {
										$contadorUbicaciones++;
									} else {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'positionErrorCodeFiscalNac' => $i,
											'message' => 'Error en número interiór de dirección nacional'
										);
									}
								} else {
									$contadorUbicaciones++;
								}
							} else {
								if (!preg_match($patronCpostal, $ubicaFor[$i]['calle1'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeFiscalNac' => $i,
										'message' => 'Error en primera calle de referencia de dirección nacional'
									);
								}
								if (!preg_match($patronCpostal, $ubicaFor[$i]['calle2'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeFiscalNac' => $i,
										'message' => 'Error en segunda calle de referencia de dirección nacional'
									);
								}
								if (!preg_match($patronCpostal, $ubicaFor[$i]['referencia'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeFiscalNac' => $i,
										'message' => 'Error en lugar de referencia de dirección nacional'
									);
								}
							}
						}
					} else {
						if (!preg_match($patron, $ubicaFor[$i]['clasificacion'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'positionErrorCodeFiscalNac' => $i,
								'message' => 'Error en clasificacion de dirección nacional'
							);
						}
						if (!preg_match($patron, $ubicaFor[$i]['alias'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'positionErrorCodeFiscalNac' => $i,
								'message' => 'Error en alias de dirección nacional'
							);
						}
						if (!preg_match($patronCpostal, $ubicaFor[$i]['calle'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'positionErrorCodeFiscalNac' => $i,
								'message' => 'Error en calle de dirección nacional'
							);
						}
						if (!preg_match($patronCpostal, $ubicaFor[$i]['num_ext'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'positionErrorCodeFiscalNac' => $i,
								'message' => 'Error en número exteriór de dirección nacional'
							);
						}
						if (!preg_match($patronNum, $ubicaFor[$i]['token_codigos_postales'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'positionErrorCodeFiscalNac' => $i,
								'message' => 'Error en código postal de dirección nacional'
							);
						}
					}
				}

				if ($contadorUbicaciones == 0) {
					for ($i = 0; $i < count($ubicaFor); $i++) {

						$arrayClasificacion = $JwtAuth->encriptar($ubicaFor[$i]['clasificacion']);
						$arrayalias = $JwtAuth->encriptar($ubicaFor[$i]['alias']);
						$arraycalle = $JwtAuth->encriptar($ubicaFor[$i]['calle']);
						$arraynum_ext = $JwtAuth->encriptar($ubicaFor[$i]['num_ext']);

						$queryIDDir = DB::select("SELECT id FROM codigos_postales WHERE	token_codigos_postales = ?", [$ubicaFor[$i]['token_codigos_postales']]);

						if ($ubicaFor[$i]['num_int'] != '-') {
							$arraynum_int = $JwtAuth->encriptar($ubicaFor[$i]['num_int']);
						} else {
							$arraynum_int = NULL;
						}

						if ($ubicaFor[$i]['calle1'] != '-') {
							$arraycalle1 = $JwtAuth->encriptar($ubicaFor[$i]['calle1']);
						} else {
							$arraycalle1 = NULL;
						}

						if ($ubicaFor[$i]['calle2'] != '-') {
							$arraycalle2 = $JwtAuth->encriptar($ubicaFor[$i]['calle2']);
						} else {
							$arraycalle2 = NULL;
						}

						if ($ubicaFor[$i]['referencia'] != '-') {
							$arrayreferencia = $JwtAuth->encriptar($ubicaFor[$i]['referencia']);
						} else {
							$arrayreferencia = NULL;
						}

						$updateUbicacion = DB::table('teci_direcciones AS ubica')
							->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
							->join("main_empresas AS emp", "catprov.administrador", "emp.id")
							->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
							->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
							->where([
								'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
								'ubica.token_direccion' => $ubicaFor[$i]['token_direccion'],
								'emp.empresa_token' => $usuario->empresa_token,
								'users.usuario_token' => $usuario->user_token,
							])
							->update(array(
								"ubica.clase" => $arrayClasificacion,
								"ubica.alias" => $arrayalias,
								"ubica.calle" => $arraycalle,
								"ubica.num_ext" => $arraynum_ext,
								"ubica.num_int" => $arraynum_int,
								"ubica.codigo_postal" => $queryIDDir[0]->id,
								"ubica.pais" => 118,
								"ubica.calle1" => $arraycalle1,
								"ubica.calle2" => $arraycalle2,
								"ubica.referencia" => $arrayreferencia,
							));
						if ($updateUbicacion) {
							$dataMensaje = array(
								'status' => 'success',
								'code' => 200,
								'message' => 'Dirección actualizada'
							);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Dirección no actualizada, intente nuevamente o comuniquese a soporte'
							);
						}
					}
				}
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

	public function updateUbicacionExtranjeroProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('json');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);
		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
				'dirUbicacion' => 'required|array'
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$forUbica = $parametrosArray['dirUbicacion'];

				for ($i = 0; $i < count($forUbica); $i++) {

					if (
						preg_match($patron, $forUbica[$i]['clasificacion']) && preg_match($patron, $forUbica[$i]['alias']) &&
						preg_match($patronCpostal, $forUbica[$i]['cod_postalext']) && preg_match($patron, $forUbica[$i]['dir_completa'])
					) {
						$fisinsertDir = DB::table('direcciones AS dirfis')
							->join("eegr_catalogo_proveedores AS catprov", "dirfis.proveedor", "catprov.id")
							->join("main_empresas AS emp", "catprov.administrador", "emp.id")
							->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
							->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
							->where([
								'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
								'dirfis.token_direccion' => $forUbica[$i]['token_direccion'],
								'emp.empresa_token' => $usuario->empresa_token,
								'users.usuario_token' => $usuario->user_token,
							])
							->update(array(
								"clase" => $JwtAuth->encriptar($forUbica[$i]['clasificacion']),
								"alias" => $JwtAuth->encriptar($forUbica[$i]['alias']),
								"calle" => $JwtAuth->encriptar($forUbica[$i]['dir_completa']),
								"cod_postalext" => $JwtAuth->encriptar($forUbica[$i]['cod_postalext']),
							));
						if ($fisinsertDir) {
							$dataMensaje = array(
								'status' => 'success',
								'code' => 200,
								'message' => 'Dirección actualizada'
							);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Actualización de dirección no realizada, intente nuevamente o comuniquese a soporte'
							);
						}
					} else {
						if (!preg_match($patron, $forUbica[$i]['clasificacion'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en clasificacion de dirección de sucursal'
							);
						}
						if (!preg_match($patron, $forUbica[$i]['alias'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en alias de dirección de sucursal'
							);
						}
						if (!preg_match($patronCpostal, $forUbica[$i]['cod_postalext'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en código postal de dirección de sucursal'
							);
						}
						if (!preg_match($patron, $forUbica[$i]['dir_completa'])) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en dirección de dirección de sucursal'
							);
						}
					}
				}
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

	public function deleteUbicacionProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('json');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
				'token_direccion' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$deleteUbica = DB::table('teci_direcciones AS ubica')
					->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
					->join("main_empresas AS emp", "catprov.administrador", "emp.id")
					->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
					->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
					->where([
						'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
						'ubica.token_direccion' => $parametrosArray['token_direccion'],
						'emp.empresa_token' => $usuario->empresa_token,
						'users.usuario_token' => $usuario->user_token,
					])
					->update(array(
						"ubica.status" => FALSE,
						"ubica.fecha_delete_dir" => time(),
					));
				if ($deleteUbica) {
					$dataMensaje = array(
						'status' => 'success',
						'code' => 200,
						'message' => 'Dirección eliminada'
					);
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Eliminación de dirección no realizada, intente nuevamente o comuniquese a soporte'
					);
				}
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

	public function restaurarUbicacionProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('json');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
				'token_direccion' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$restDir = DB::table('teci_direcciones AS ubica')
					->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
					->join("main_empresas AS emp", "catprov.administrador", "emp.id")
					->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
					->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
					->where([
						'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
						'ubica.token_direccion' => $parametrosArray['token_direccion'],
						'emp.empresa_token' => $usuario->empresa_token,
						'users.usuario_token' => $usuario->user_token,
					])
					->update(array(
						"ubica.status" => TRUE,
						"ubica.fecha_delete_dir" => '',
					));
				if ($restDir) {
					$dataMensaje = array(
						'status' => 'success',
						'code' => 200,
						'message' => 'Dirección restaurada'
					);
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Restauración de dirección no realizada, intente nuevamente o comuniquese a soporte'
					);
				}
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

	public function deletePermUbicacionProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('json');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
				'token_direccion' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$deleteDir = DB::table('teci_direcciones AS ubica')
					->join("eegr_catalogo_proveedores AS catprov", "ubica.proveedor", "catprov.id")
					->join("main_empresas AS emp", "catprov.administrador", "emp.id")
					->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
					->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
					->where([
						'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
						'ubica.token_direccion' => $parametrosArray['token_direccion'],
						'emp.empresa_token' => $usuario->empresa_token,
						'users.usuario_token' => $usuario->user_token,
					])
					->delete();

				if ($deleteDir) {
					$dataMensaje = array(
						'status' => 'success',
						'code' => 200,
						'message' => 'Dirección eliminada permanentemente'
					);
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Eliminación permanente de dirección no realizada, intente nuevamente o comuniquese a soporte'
					);
				}
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

	public function deleteProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('json');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$utilizado = DB::select("SELECT folio,post_folio,utilizado FROM catalogo_proveedores 
				    WHERE token_cat_proveedores = ?", [$parametrosArray['token_cat_proveedores']]);

				if ($utilizado[0]->utilizado == FALSE) {
					if ($utilizado[0]->post_folio == NULL) {
						$folio_prov = 'PRV-' . $JwtAuth->generarFolio($utilizado[0]->folio);
					} else {
						$folio_prov = 'PRV-' . $JwtAuth->generarFolio($utilizado[0]->folio) . '-' . $utilizado[0]->post_folio;
					}

					$deleteProv = ProveedoresModelo::join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
						->where([
							'eegr_catalogo_proveedores.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])
						->limit(1)->update(
							array(
								'eegr_catalogo_proveedores.status' => FALSE,
								'eegr_catalogo_proveedores.fecha_delete_prov' => time(),
							)
						);
					if ($deleteProv) {
						$JwtAuth->insertBitacoraActividad(
							'egresos',
							'catalogos',
							'proveedores',
							$folio_prov,
							'eliminación de proveedor',
							$usuario->empresa_token,
							$usuario->user_token
						);

						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Proveedor eliminado'
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Eliminación de proveedor no realizada, intente nuevamente o comuniquese a soporte'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Eliminación de proveedor no realizada, proveedor vinculado a operaciones realizadas'
					);
				}
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

	public function restaurarProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('json');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$contadorFolio = DB::select(
					"SELECT COUNT(folio) AS contador FROM catalogo_proveedores 
                    WHERE folio = (SELECT folio FROM catalogo_proveedores WHERE token_cat_proveedores = ?)",
					[$parametrosArray['token_cat_proveedores']]
				);

				if ($contadorFolio[0]->contador == 1) {
					$getFolio = DB::select(
						"SELECT folio FROM catalogo_proveedores WHERE token_cat_proveedores = ?",
						[$parametrosArray['token_cat_proveedores']]
					);
					$post_folio_db = DB::select("SELECT post_folio FROM catalogo_proveedores 
				        WHERE token_cat_proveedores = ?", [$parametrosArray['token_cat_proveedores']]);
					$post_folio = $post_folio_db[0]->post_folio;
					$folio_nuevo = $getFolio[0]->folio;

					if ($post_folio == NULL) {
						$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_nuevo);
					} else {
						$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
					}

					$restaurarProv = ProveedoresModelo::join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
						->where([
							'eegr_catalogo_proveedores.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])
						->limit(1)->update(
							array(
								'eegr_catalogo_proveedores.folio' => $folio_nuevo,
								'eegr_catalogo_proveedores.post_folio' => $post_folio,
								'eegr_catalogo_proveedores.status' => TRUE,
								'eegr_catalogo_proveedores.fecha_delete_prov' => '',
							)
						);
					if ($restaurarProv) {
						$JwtAuth->insertBitacoraActividad(
							'egresos',
							'catalogos',
							'proveedores',
							$folio_prov,
							'recuperación de proveedor eliminado',
							$usuario->empresa_token,
							$usuario->user_token
						);
						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Proveedor restaurado con el folio ' . $folio_prov
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Restauración de proveedor no realizada, intente nuevamente o comuniquese a soporte'
						);
					}
				} else {
					$newFolio = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
					    JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE fold.egr_proveedores = TRUE AND fold.empresa = emp.id AND emp.empresa_token = ? 
					    AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

					if (count($newFolio) == 1) {
						if ($newFolio[0]->folio == 1000000000) {
							$post_folio_db = DB::select("SELECT MAX(catprov.post_folio)+1 AS folio FROM catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
				                JOIN vhum_empleados_catalogo AS pers JOIN teci_usuarios_catalogo AS users WHERE catprov.administrador = emp.id AND emp.empresa_token = ? AND emp.id = empuser.empresa 
				                AND empuser.usuario = users.id AND users.usuario_token = ?",[$usuario->empresa_token, $usuario->user_token]);

							$post_folio = $JwtAuth->generarPostFolio($post_folio_db[0]->post_folder);
							$folio_nuevo = 1;
						} else {
							$post_folio_db = DB::select("SELECT post_folio FROM catalogo_proveedores 
				                WHERE token_cat_proveedores = ?", [$parametrosArray['token_cat_proveedores']]);
							$post_folio = $post_folio_db[0]->post_folio;
							$folio_nuevo = $newFolio[0]->folio;
						}
					} else {
						$post_folio = NULL;
						$folio_nuevo = 1;
					}

					if ($post_folio == NULL) {
						$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_nuevo);
					} else {
						$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
					}

					$restaurarProv = ProveedoresModelo::join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
						->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
						->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
						->where([
							'eegr_catalogo_proveedores.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.usuario_token' => $usuario->user_token,
						])
						->limit(1)->update(
							array(
								'eegr_catalogo_proveedores.folio' => $folio_nuevo,
								'eegr_catalogo_proveedores.post_folio' => $post_folio,
								'eegr_catalogo_proveedores.status' => TRUE,
								'eegr_catalogo_proveedores.fecha_delete_prov' => '',
							)
						);
					if ($restaurarProv) {
						$JwtAuth->insertBitacoraActividad(
							'egresos',
							'catalogos',
							'proveedores',
							$folio_prov,
							'recuperación de proveedor eliminado',
							$usuario->empresa_token,
							$usuario->user_token
						);
						$regFolder = DB::table("sos_last_folders")->join("main_empresas AS emp", "last_folders.empresa", "=", "emp.id")
							->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
							->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
							->where([
								"sos_last_folders.egr_proveedores" => TRUE,
								"emp.empresa_token" => $usuario->empresa_token,
								"users.usuario_token" => $usuario->user_token,
							])
							->limit(1)->update(
								array(
									"sos_last_folders.folder" => $folio_nuevo,
									"sos_last_folders.post_folder" => $post_folio,
								)
							);

						$dataMensaje = array(
							'status' => 'success',
							'code' => 200,
							'message' => 'Proveedor restaurado con el folio ' . $folio_prov
						);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Restauración de proveedor no realizada, intente nuevamente o comuniquese a soporte'
						);
					}
				}
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

	public function deletePermProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonData = $request->input('json');
		$parametros = json_decode($jsonData);
		$parametrosArray = json_decode($jsonData, true);

		if (!empty($parametros) && !empty($parametrosArray)) {

			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'token_cat_proveedores' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$utilizado = DB::select("SELECT utilizado FROM catalogo_proveedores 
				    WHERE token_cat_proveedores = ?", [$parametrosArray['token_cat_proveedores']]);

				if ($utilizado[0]->utilizado == FALSE) {
					$buscaProveedor = ProveedoresModelo::join("personas AS perns","eegr_catalogo_proveedores.proveedor","=","perns.id")
						->join("pais AS country","perns.nacionalidad","=","country.id")
						->join("forma_pago AS fpago","eegr_catalogo_proveedores.forma_pago","=","fpago.id")
						->join("creditos AS cred","eegr_catalogo_proveedores.id","=","cred.proveedor")
						->join("main_empresas AS emp","eegr_catalogo_proveedores.administrador","=","emp.id")
						->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
						->join("teci_usuarios_catalogo","empuser.usuario","=","users.id")
						->where([
							'eegr_catalogo_proveedores.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
							'emp.empresa_token' => $usuario->empresa_token,
							'users.user_token' => $usuario->user_token,
						])->get();
					//echo count($buscaProveedor);
					if (count($buscaProveedor) == 1) {
						foreach ($buscaProveedor as $valViewProv) {
							//creditos
							$deleteCreditos = DB::table("creditos AS cred")
								->join("eegr_catalogo_proveedores AS catprov","cred.proveedor","=","catprov.id")
								->join("main_empresas AS emp","catprov.administrador","=","emp.id")
								->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
								->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
								->where([
									'cred.token_creditos' => $valViewProv->token_creditos,
									'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
									'emp.empresa_token' => $usuario->empresa_token,
									'users.user_token' => $usuario->user_token,
								])->limit(1)->delete();
							//echo 'token_creditos '.$deleteCreditos[0]->token_creditos;

							//contacto
							$queryContProv = DB::select("SELECT empleado.token_contacto,areapers.areaemp,cargopers.cargo,people.token_personas,people.paterno,people.materno,people.nombre
									FROM vhum_empleados_catalogo AS empleado JOIN vhum_empleados_catalogo_area AS areapers JOIN vhum_empleados_catalogo_cargo AS cargopers 
									JOIN sos_personas AS people JOIN eegr_catalogo_proveedores AS catprov JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser 
									JOIN teci_usuarios_catalogo AS users WHERE empleado.area = areapers.id AND empleado.cargo = cargopers.id AND empleado.empleado_name = people.id 
									AND empleado.cat_proveedores = catprov.id AND catprov.token_cat_proveedores = ? AND catprov.administrador = emp.id AND emp.id = empuser.empresa
									AND emp.empresa_token = ? AND empuser.usuario = users.id AND users.usuario_token = ?", 
									[$parametrosArray['token_cat_proveedores'], $usuario->empresa_token, $usuario->user_token]);

							if (count($queryContProv) > 0) {
								foreach ($queryContProv as $valContProv) {
									$telefonoProv = DB::select("SELECT tel.token_telefono,tel.telefono,tel.extension FROM telefonos_personal AS tel 
									    JOIN in_egr_contacto_cliente_proveedor AS empleado WHERE tel.personal = empleado.id AND empleado.token_contacto = ?",[$valContProv->token_contacto]);

									if (count($telefonoProv) > 0) {
										foreach ($telefonoProv as $valueTelPers) {
											$deleteTelefono = DB::table("telefonos_personal AS tel")
											->join("in_egr_contacto_cliente_proveedor AS empleado", "tel.personal", "=", "empleado.id")
											->where(['tel.token_telefono' => $valueTelPers->token_telefono,'empleado.token_contacto' => $valContProv->token_contacto])->limit(1)->delete();
										}
									}

									$queryContProv = DB::select("SELECT mailpers.token_correo,mailpers.correo FROM correos_personal AS mailpers JOIN in_egr_contacto_cliente_proveedor AS empleado 
										WHERE mailpers.empleado_name = empleado.id AND empleado.pers_token = ?",[$valContProv->pers_token]);

									if (count($queryContProv) > 0) {
										foreach ($queryContProv as $valueMailPers) {
											$deleteCorreo = DB::table("correos_personal AS mailpers")
											->join("in_egr_contacto_cliente_proveedor AS empleado","mailpers.personal","=","empleado.id")
											->where(['mailpers.token_correo' => $valueMailPers->token_correo,'empleado.token_contacto' => $valContProv->token_contacto,])->limit(1)->delete();
										}
									}

									$deletePersonas = DB::table("personas AS people")
										->join("in_egr_contacto_cliente_proveedor AS empleado","people.id","=","empleado.nombre")
										->where(['people.token_personas' => $valContProv->token_personas,'empleado.token_contacto' => $valContProv->token_contacto])->limit(1)->delete();
									//echo $valContProv->token_personas;
									$deletePersonal = DB::table("in_egr_contacto_cliente_proveedor AS empleado")
									->join("eegr_catalogo_proveedores AS catprov", "empleado.cat_proveedores", "=", "catprov.id")
									->where(['empleado.token_contacto' => $valContProv->token_contacto,'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],])->limit(1)->delete();
								}
							}

							//direcciones 
							$listaUbicacion = DB::table('teci_direcciones AS ubica')
								->join('eegr_catalogo_proveedores AS catprov', 'ubica.proveedor', 'catprov.id')
								->join("main_empresas AS emp", "catprov.administrador", "emp.id")
								->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
								->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
								->where([
									'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
									'emp.empresa_token' => $usuario->empresa_token,
									'users.usuario_token' => $usuario->user_token
								])->limit(1)->delete();

							//nombre del proveedor 
							$listaUbicacion = DB::table('personas AS people')
								->join('eegr_catalogo_proveedores AS catprov', 'people.id', 'catprov.proveedor')
								->join("main_empresas AS emp", "catprov.administrador", "emp.id")
								->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
								->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
								->where([
									'catprov.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
									'emp.empresa_token' => $usuario->empresa_token,
									'users.usuario_token' => $usuario->user_token
								])->limit(1)->delete();

							if ($valViewProv->post_folio == NULL) {
								$folio_prov = 'prv-' . $JwtAuth->generarFolio($valViewProv->folio);
							} else {
								$folio_prov = 'prv-' . $JwtAuth->generarFolio($valViewProv->folio) . '-' . $valViewProv->post_folio;
							}

							$deleteProv = ProveedoresModelo::join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
								->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
								->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
								->where([
									'eegr_catalogo_proveedores.token_cat_proveedores' => $parametrosArray['token_cat_proveedores'],
									'emp.empresa_token' => $usuario->empresa_token,
									'users.usuario_token' => $usuario->user_token,
								])->limit(1)->delete();

							if ($deleteProv) {
								$JwtAuth->insertBitacoraActividad(
									'egresos',
									'catalogos',
									'proveedores',
									$folio_prov,
									'eliminación permanente de proveedor',
									$usuario->empresa_token,
									$usuario->user_token
								);

								$dataMensaje = array(
									'status' => 'success',
									'code' => 200,
									'message' => 'Proveedor eliminado'
								);
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'Eliminación de proveedor no realizada, intente nuevamente o comuniquese a soporte'
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
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Eliminación de proveedor no realizada, proveedor vinculado a operaciones realizadas'
					);
				}
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

	public function buscaRFProveedor(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				//'radioProv' => 'required|string',
				//'subtipoProv' => 'required|string',
				'rfc_generico' => 'required|string',
				'prov_rfc' => 'string',
				'id_tax' => 'string',
				'nombre' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

				$paramProvRfcGenerico = strtolower($parametrosArray['rfc_generico']);
				$paramProvRfc = strtolower($parametrosArray['prov_rfc']);
				$paramIdTax = strtolower($parametrosArray['id_tax']);
				$paramNombreProv = strtolower($parametrosArray['nombre']);

				$listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
					->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
					->join("teci_forma_pago AS pago","eegr_catalogo_proveedores.forma_pago", "pago.id")
					->join("main_empresas AS emp","eegr_catalogo_proveedores.administrador", "=", "emp.id")
					->join("main_empresa_usuario AS empuser","emp.id","=","empuser.empresa")
					->join("teci_usuarios_catalogo AS users","empuser.usuario","=","users.id")
					->where([
						'emp.empresa_token' => $usuario->empresa_token,
						'users.usuario_token' => $usuario->user_token,
						'eegr_catalogo_proveedores.status' => true
					])->get();

				$countVerifica = 0;
				$invalidName = '';
				foreach ($listaProveedores as $resListProv) {
					$nombreProv = strtolower($JwtAuth->desencriptar($resListProv->nombre_extendido));

					$rfc_generico = strtolower($resListProv->rfc_generico);

					if ($resListProv->rfc != NULL) {
						$rfc_prov = strtolower($JwtAuth->desencriptar($resListProv->rfc));
					} else {
						$rfc_prov = '';
					}

					if ($resListProv->tax_id != NULL) {
						$tax_id_prov = strtolower($JwtAuth->desencriptar($resListProv->tax_id));
					} else {
						$tax_id_prov = '';
					}

					if ($paramProvRfc != '') {
						if ($rfc_prov == $paramProvRfc) {
							++$countVerifica;
							$invalidName = $nombreProv;
						}
					} else if ($paramIdTax != '') {
						if ($tax_id_prov == $paramIdTax) {
							++$countVerifica;
							$invalidName = $nombreProv;
						}
					} else if ($rfc_generico == $paramProvRfcGenerico && $nombreProv == $paramNombreProv) {
						++$countVerifica;
						$invalidName = $nombreProv;
					} else {
						if ($nombreProv == $paramNombreProv) {
							++$countVerifica;
							$invalidName = $nombreProv;
						}
					}
				}

				if ($countVerifica >= 1) {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'El proveedor verificado ya ha sido registrado con nombre ' . strtoupper($invalidName)
					);
				} else {
					$dataMensaje = array(
						'status' => 'success',
						'code' => 200,
						'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
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

	public function buscaRfcAllProveedorOut(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'string',
				'radioProv' => 'required|string',
				'nombre' => 'required|string',
				'rfc_generico' => 'required|string',
				'prov_rfc' => 'string',
				'id_tax' => 'string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				if ($usuario->empresa_token == "") {
					$empresa = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
				} else {
					$empresa = $usuario->empresa_token;
				}

				$paramradioProv = $parametrosArray['radioProv'];
				$paramNombreProv = strtolower($parametrosArray['nombre']);
				$paramProvRfcGenerico = strtolower($parametrosArray['rfc_generico']);
				$paramProvRfc = strtolower($parametrosArray['prov_rfc']);
				$paramIdTax = strtolower($parametrosArray['id_tax']);

				if (
					isset($paramradioProv) && !empty($paramradioProv) && preg_match($JwtAuth->filtroRfc(), $paramradioProv) &&
					isset($paramProvRfcGenerico) && !empty($paramProvRfcGenerico) && preg_match($JwtAuth->filtroRfc(), $paramProvRfcGenerico) &&
					isset($paramNombreProv) && !empty($paramNombreProv) && preg_match($JwtAuth->filtroAlfabetico(), $paramNombreProv)
				) {

					$arrayProveedores = array();
					$listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
						->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
						->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
						->where(['emp.empresa_token' => $empresa, 'eegr_catalogo_proveedores.status' => true])
						->get();

					$countVerifica = 0;
					$invalidName = '';

					foreach ($listaProveedores as $resListProv) {
						$rfc_prov = '';
						$tax_id_prov = '';
						$nombreProv = strtolower($JwtAuth->desencriptar($resListProv->nombre_extendido));

						$rfc_generico = strtolower($resListProv->rfc_generico);

						if ($resListProv->rfc != NULL) $rfc_prov = strtolower($JwtAuth->desencriptar($resListProv->rfc));

						if ($resListProv->tax_id != NULL) $tax_id_prov = strtolower($JwtAuth->desencriptar($resListProv->tax_id));

						$row_prov = array(
							"nombre_prov" => $nombreProv,
							"rfc_generico" => $rfc_generico,
							"rfc_prov" => $rfc_prov,
							"tax_id_prov" => $tax_id_prov,
						);
						$arrayProveedores[] = $row_prov;
					}
					$search_by_nombre = array_column($arrayProveedores, "nombre_prov");
					$search_by_rfc_generico = array_column($arrayProveedores, "rfc_generico");
					$search_by_rfc_prov = array_column($arrayProveedores, "rfc_prov");
					$search_by_tax_id_prov = array_column($arrayProveedores, "tax_id_prov");

					if ($paramradioProv == "nacional") {
						//array_search($paramNombreProv, $search_by_nombre) == ""
						if (array_search($paramProvRfc, $search_by_rfc_prov) == "") {
							$dataMensaje = array(
								'status' => 'success',
								'code' => 200,
								'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
							);
						} else {
							$invalidName = $arrayProveedores[array_search($paramProvRfc, array_column($arrayProveedores, "rfc_prov"))]["nombre_prov"];
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'El proveedor verificado ya ha sido registrado con nombre ' . strtoupper($invalidName)
							);
							return response()->json($dataMensaje, $dataMensaje['code']);
						}


						if (array_search($paramNombreProv, $search_by_nombre) == "") {
							$dataMensaje = array(
								'status' => 'success',
								'code' => 200,
								'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
							);
						} else {
							$invalidRfc = $arrayProveedores[array_search($paramNombreProv, array_column($arrayProveedores, "nombre_prov"))]["rfc_prov"];
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'El proveedor verificado ya ha sido registrado con rfc ' . strtoupper($invalidRfc)
							);
							return response()->json($dataMensaje, $dataMensaje['code']);
						}
					} else if ($paramradioProv == "extranjero") {
						if (array_search($paramNombreProv, $search_by_nombre) == "") {
							$dataMensaje = array(
								'status' => 'success',
								'code' => 200,
								'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
							);
						} else {
							$invalidName = $arrayProveedores[array_search($paramNombreProv, array_column($arrayProveedores, "nombre_prov"))]["nombre_prov"];
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'El proveedor verificado ya ha sido registrado con nombre ' . strtoupper($invalidName)
							);
						}
					}
				} else {
					$dataMensaje = array('status' => 'error', 'code' => 200, 'message' => 'Nombre ó rfc generico del proveedor son incorrectos');
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

	public function buscaRfcAllProveedorOutBack(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'string',
				'rfc_generico' => 'required|string',
				'prov_rfc' => 'string',
				'id_tax' => 'string',
				'nombre' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida',
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				if ($usuario->empresa_token == "") {
					$empresa = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
				} else {
					$empresa = $usuario->empresa_token;
				}

				$paramProvRfcGenerico = strtolower($parametrosArray['rfc_generico']);
				$paramProvRfc = strtolower($parametrosArray['prov_rfc']);
				$paramIdTax = strtolower($parametrosArray['id_tax']);
				$paramNombreProv = strtolower($parametrosArray['nombre']);

				$listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
					->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
					->join("teci_forma_pago AS pago", "eegr_catalogo_proveedores.forma_pago", "pago.id")
					->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
					->where([
						'emp.empresa_token' => $empresa,
						'eegr_catalogo_proveedores.status' => true
					])->get();

				$countVerifica = 0;
				$invalidName = '';
				foreach ($listaProveedores as $resListProv) {
					$nombreProv = strtolower($JwtAuth->desencriptar($resListProv->nombre_extendido));

					$rfc_generico = strtolower($resListProv->rfc_generico);

					if ($resListProv->rfc != NULL) {
						$rfc_prov = strtolower($JwtAuth->desencriptar($resListProv->rfc));
					} else {
						$rfc_prov = '';
					}

					if ($resListProv->tax_id != NULL) {
						$tax_id_prov = strtolower($JwtAuth->desencriptar($resListProv->tax_id));
					} else {
						$tax_id_prov = '';
					}

					if ($paramProvRfc != '') {
						if ($rfc_prov == $paramProvRfc) {
							++$countVerifica;
							$invalidName = $nombreProv;
						}
					} else if ($paramIdTax != '') {
						if ($tax_id_prov == $paramIdTax) {
							++$countVerifica;
							$invalidName = $nombreProv;
						}
					} else if ($rfc_generico == $paramProvRfcGenerico && $nombreProv == $paramNombreProv) {
						++$countVerifica;
						$invalidName = $nombreProv;
					} else {
						if ($nombreProv == $paramNombreProv) {
							++$countVerifica;
							$invalidName = $nombreProv;
						}
					}
				}

				if ($countVerifica >= 1) {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'El proveedor verificado ya ha sido registrado con nombre ' . strtoupper($invalidName)
					);
				} else {
					$dataMensaje = array(
						'status' => 'success',
						'code' => 200,
						'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
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

	public function buscaRfcAllProveedor(Request $request)
	{
		$JwtAuth = new \JwtAuth();
		$jsonUser = $request->input('json');
		$parametros = json_decode($jsonUser);
		$parametrosArray = json_decode($jsonUser, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'empresa_token' => 'string',
				'rfc_generico' => 'required|string',
				'prov_rfc' => 'string',
				'id_tax' => 'string',
				'nombre' => 'required|string',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'La infomación que ha intantado registrar es invalida',
					'errors' => $validate->errors()
				);
			} else {
				if ($parametrosArray['empresa_token'] == "") {
					$empresa = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
				} else {
					$empresa = $parametrosArray['empresa_token'];
				}

				$paramProvRfcGenerico = strtolower($parametrosArray['rfc_generico']);
				$paramProvRfc = strtolower($parametrosArray['prov_rfc']);
				$paramIdTax = strtolower($parametrosArray['id_tax']);
				$paramNombreProv = strtolower($parametrosArray['nombre']);

				$listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
					->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
					->join("teci_forma_pago AS pago", "eegr_catalogo_proveedores.forma_pago", "pago.id")
					->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
					->where([
						'emp.empresa_token' => $empresa,
						'eegr_catalogo_proveedores.status' => true
					])->get();

				$countVerifica = 0;
				$invalidName = '';
				foreach ($listaProveedores as $resListProv) {
					$nombreProv = strtolower($JwtAuth->desencriptar($resListProv->nombre_extendido));

					$rfc_generico = strtolower($resListProv->rfc_generico);

					if ($resListProv->rfc != NULL) {
						$rfc_prov = strtolower($JwtAuth->desencriptar($resListProv->rfc));
					} else {
						$rfc_prov = '';
					}

					if ($resListProv->tax_id != NULL) {
						$tax_id_prov = strtolower($JwtAuth->desencriptar($resListProv->tax_id));
					} else {
						$tax_id_prov = '';
					}

					if ($paramProvRfc != '') {
						if ($rfc_prov == $paramProvRfc) {
							++$countVerifica;
							$invalidName = $nombreProv;
						}
					} else if ($paramIdTax != '') {
						if ($tax_id_prov == $paramIdTax) {
							++$countVerifica;
							$invalidName = $nombreProv;
						}
					} else if ($rfc_generico == $paramProvRfcGenerico && $nombreProv == $paramNombreProv) {
						++$countVerifica;
						$invalidName = $nombreProv;
					} else {
						if ($nombreProv == $paramNombreProv) {
							++$countVerifica;
							$invalidName = $nombreProv;
						}
					}
				}

				if ($countVerifica >= 1) {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'El proveedor verificado ya ha sido registrado con nombre ' . strtoupper($invalidName)
					);
				} else {
					$dataMensaje = array(
						'status' => 'success',
						'code' => 200,
						'message' => 'El proveedor con el nombre ' . strtoupper($paramNombreProv) . ' no ha sido registrado'
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

	public function registraProveedorMin(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('json');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "string",
				"rfc_generico" => "required|string",
				"prov_rfc" => "string",
				"id_tax" => "string",
				"radioProv" => "required|string",
				"subtipoProv" => "required|string",
				"name_prov" => "string",
				"comercial_nombre" => "string",
				"curp" => "string",
				"paistoken" => "string",
				"sitio_web" => "string",
				"tknRegimenFiscal" => "string",
				"cod_postal" => "string",
				"dipomex_cod_postal_estado" => "string",
				"dipomex_cod_postal_municipio" => "string",
				"dipomex_cod_postal_cp" => "string",
				"dipomex_cod_postal_colonia_vinculada" => "string",
				"listnewdireccionNac" => "array",
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
				$fechaAlta = time();
				//$user_token = $parametrosArray["user_token"];
				$rfc_generico = $parametrosArray["rfc_generico"];
				$prov_rfc = $parametrosArray["prov_rfc"];
				$rfc_prov = NULL;
				$id_tax = $parametrosArray["id_tax"];
				$idtax = NULL;
				$radioProv = $parametrosArray["radioProv"];
				$subtipoProv = $parametrosArray["subtipoProv"];
				$name_prov = $parametrosArray["name_prov"];
				$comercial_nombre = $parametrosArray["comercial_nombre"];
				$curp = $parametrosArray["curp"];
				$paistoken = $parametrosArray["paistoken"];
				$sitio_web = $parametrosArray["sitio_web"];
				$tknRegimenFiscal = $parametrosArray["tknRegimenFiscal"];
				$cod_postal = $parametrosArray["cod_postal"];
				//echo "razon_social ".$JwtAuth->desencriptar("cnlZSktiM1FlMHlqbk13ZFhkS0ozUT09OjoxMjM0NTY3ODEyMzQ1Njc4");exit;
				$dipomex_cod_postal_estado = $parametrosArray["dipomex_cod_postal_estado"];
				$dipomex_cod_postal_municipio = $parametrosArray["dipomex_cod_postal_municipio"];
				$dipomex_cod_postal_cp = $parametrosArray["dipomex_cod_postal_cp"];
				$dipomex_cod_postal_colonia_vinculada = $parametrosArray["dipomex_cod_postal_colonia_vinculada"];
				$listnewdireccionNac = $parametrosArray["listnewdireccionNac"];

				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
				$patronNumCred = '/^[0-9$,.-]*$/';
				$patronRfc = '/[aA0-zZ9]/';
				$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp 
                    WHERE emp.empresa_token = ?", [$usuario->empresa_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$folio_nuevo = NULL;
				$post_folio = NULL;

				$folioSistemaTemp = DB::select("SELECT temp_folio FROM eegr_catalogo_proveedores WHERE temp_folio IS NOT NULL AND administrador = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$usuario->empresa_token]);
				if (count($folioSistemaTemp) > 0) {
					$queryFolioTmpPrv = DB::select("SELECT temp_folio+1 AS temp_folio FROM eegr_catalogo_proveedores 
						WHERE id = (SELECT Max(catprov.id) FROM eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE temp_folio IS NOT NULL AND catprov.administrador = emp.id 
						AND emp.empresa_token = ?)", [$usuario->empresa_token]);
					
					foreach ($queryFolioTmpPrv as $vTemp) {
						$folio_temporal = $vTemp->temp_folio;
					}
				} else {
					$folio_temporal = 1;
				}

				$folio_prov_temp = 'PRV-TEMP-'.$JwtAuth->generarFolio($folio_temporal);

				if ($prov_rfc != "") {
					if (isset($prov_rfc) && isset($prov_rfc) && preg_match($patronRfc, $prov_rfc)) {
						$rfc_prov = $JwtAuth->encriptar($prov_rfc);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'error al registrar rfc del proveedor'
						);
					}
				} else {
					$rfc_prov = NULL;
				}

				if ($id_tax != "") {
					if (isset($id_tax) && preg_match($patronRfc, $id_tax)) {
						$idtax = $JwtAuth->encriptar($id_tax);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'error al registrar idtax del proveedor'
						);
					}
				} else {
					$idtax = NULL;
				}

				$razon_social_txt = NULL;
				$empresa_txt = NULL;
				$comercial_nombre_txt = NULL;
				$curp_txt = NULL;
				$pais_txt = NULL;
				$sitio_web_txt = NULL;
				$regimen_fiscal_txt = NULL;

				if (isset($radioProv) && isset($radioProv) && preg_match($patron, $radioProv)) {
					if ($radioProv == "extranjero") {
						if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
							if ($subtipoProv == "provMoral") {
								//return response()->json(['message' => $parametrosArray['pais'],'codigo' => 200,'status' => 'error']);
								if (isset($name_prov) && !empty($name_prov) && preg_match($patron,$name_prov) &&
									isset($paistoken) && !empty($paistoken)) {
									//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
									$name_prov_txt = $JwtAuth->encriptar($name_prov);
									//echo "razon_social ".$razon_social_txt;exit;$JwtAuth->encriptar("cnlZSktiM1FlMHlqbk13ZFhkS0ozUT09OjoxMjM0NTY3ODEyMzQ1Njc4")
									//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
									$selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
									$pais_txt = $selectPais[0]->id;
									//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
									if (!empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
										if (preg_match($patron, $comercial_nombre)) {
											$comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}
										if (preg_match($patronUrl, $sitio_web)) {
											$sitio_web_txt = $JwtAuth->encriptar($sitio_web);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$nombre_comercial_txt = NULL;
										$sitioweb = NULL;
									}
								} else {
									if (!isset($name_prov) || empty($name_prov) || !preg_match($patron,$name_prov)) {
										$dataMensaje = array(
											"status" => "error",
											"code" => 200,
											"codeProvGenError" => "nomemp",
											"message" => "Error en nombre de empresa de su proveedor"
										);
									}
									if (!isset($paistoken) || empty($paistoken)) {
										$dataMensaje = array(
											"status" => "error",
											"code" => 200,
											"codeProvGenError" => "pais",
											"message" => "Error en pais de su proveedor"
										);
									}
								}
							}
							//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);		
							if ($subtipoProv == 'provFisica') {
								if (isset($name_prov) && !empty($name_prov) && preg_match($patron,$name_prov) &&
								isset($paistoken) && !empty($paistoken)) {
									$name_prov_txt = $JwtAuth->encriptar($name_prov);

									if (isset($comercial_nombre) && !empty($comercial_nombre) &&
										isset($sitio_web) && !empty($sitio_web)) {
										if (preg_match($patron, $comercial_nombre)) {
											$comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}

										if (preg_match($patronUrl, $sitio_web)) {
											$sitio_web_txt = $JwtAuth->encriptar($sitio_web);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$comercial_nombre_txt = NULL;
										$sitio_web_txt = NULL;
									}

									$selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
									$pais_txt = $selectPais[0]->id;
								} else {
									if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(),$name_prov)) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'nombrePF',
											'message' => 'Error en nombre de su proveedor'
										);
									}
									if (!isset($paistoken) || empty($paistoken)) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'paisPF',
											'message' => 'Error en pais de su proveedor'
										);
									}
								}
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'clbint',
								'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
							);
						}
					}

					if ($radioProv == 'nacional') {
						if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
							if ($subtipoProv == 'provMoral') {
								if (isset($name_prov) && !empty($name_prov) && preg_match($patron,$name_prov)) {
									$name_prov_txt = $JwtAuth->encriptar($name_prov);
									if (isset($comercial_nombre) && !empty($comercial_nombre) &&
										isset($sitio_web) && !empty($sitio_web)) {
										if (preg_match($patron, $comercial_nombre)) {
											$nombre_comercial = $JwtAuth->encriptar($comercial_nombre);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}
										if (preg_match($patronUrl, $sitio_web)) {
											$sitioweb = $JwtAuth->encriptar($sitio_web);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$comercial_nombre_txt = NULL;
										$sitio_web_txt = NULL;
									}

									$pais_txt = '118';
								} else {
									if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(),$name_prov)) {
										$dataMensaje = array(
											"status" => "error",
											"code" => 200,
											"codeProvGenError" => "nomemp",
											"message" => "Error en nombre de su proveedor"
										);
									}
								}
							}

							if ($subtipoProv == 'provFisica') {
								if (isset($name_prov) && !empty($name_prov) && preg_match($patron,$name_prov)) {
									$name_prov_txt = $JwtAuth->encriptar($name_prov);

									if (isset($comercial_nombre) && !empty($comercial_nombre) &&
										isset($curp) && !empty($curp) &&
										isset($sitio_web) && !empty($sitio_web)) {

										if (preg_match($patron, $comercial_nombre)) {
											$comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}

										if (preg_match($patronRfc, $curp)) {
											$curp_txt = $JwtAuth->encriptar($curp);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'clbint',
												'message' => 'Error en curp de su proveedor'
											);
										}

										if (preg_match($patronUrl, $sitio_web)) {
											$sitio_web_txt = $JwtAuth->encriptar($sitio_web);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$comercial_nombre_txt = NULL;
										$curp_txt = NULL;
										$sitio_web_txt = NULL;
									}

									$pais_txt = '118';
								} else {
									if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(),$name_prov)) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'nombrePF',
											'message' => 'Error en nombre de su proveedor'
										);
									}
								}
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'clbint',
								'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
							);
						}
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'codeProvGenError' => 'clbint',
						'message' => 'Seleccione tipo de proveedor (nacional o extranjero)'
					);
				}

				$listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
					->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
					->join("teci_forma_pago AS pago", "eegr_catalogo_proveedores.forma_pago", "pago.id")
					->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
					->where([
						'emp.empresa_token' => $usuario->empresa_token,
						'eegr_catalogo_proveedores.status' => true
					])->get();

				$countVerifica = 0;
				$invalidName = '';

				foreach ($listaProveedores as $resListProv) {
					$nombreProv_f = strtolower($JwtAuth->desencriptar($resListProv->nombre_extendido));

					$rfc_generico_f = strtolower($resListProv->rfc_generico);
					/*if ($resListProv->rfc != NULL) {
                        $rfc_prov_f = strtolower($JwtAuth->desencriptar($resListProv->rfc));
                    } else {
                        $rfc_prov_f = '';
                    }
            
                    if ($resListProv->tax_id != NULL) {
                        $tax_id_prov_f = strtolower($JwtAuth->desencriptar($resListProv->tax_id));
                    } else {
                        $tax_id_prov_f = '';
                    }*/
					//return response()->json(['message' => $empresa_txt,'codigo' => 200,'status' => 'error']);
					if ($rfc_prov != NULL) {
						if ($resListProv->rfc == $rfc_prov) {
							++$countVerifica;
							$invalidName = $nombreProv_f;
						}
					} else if ($idtax != '') {
						if ($resListProv->tax_id == $idtax) {
							++$countVerifica;
							$invalidName = $nombreProv_f;
						}
					} else if ($resListProv->rfc_generico == $rfc_generico && $nombreProv_f == $name_prov) {
						++$countVerifica;
						$invalidName = $nombreProv_f;
					} else {
						if ($nombreProv_f == $empresa_txt) {
							++$countVerifica;
							$invalidName = $nombreProv_f;
						}
					}
				}
				//echo $razon_social_txt; exit;
				if ($countVerifica == 0) {
					$tkn_people_prov = $JwtAuth->encriptarToken(
						$fechaAlta,
						$name_prov_txt,
						$comercial_nombre_txt,
						$sitio_web_txt,
						$pais_txt,
						$rfc_prov
					);

					$insertProv = DB::table("sos_personas")
						->insert(array(
							"token_personas" => $tkn_people_prov,
							"nombre_extendido" => $name_prov_txt,
							"nombre_com" => $comercial_nombre_txt,
							"sitio_web" => $sitio_web_txt,
							"nacionalidad" => $pais_txt,
							"rfc_generico" => $rfc_generico,
							"rfc" => $rfc_prov,
							"tax_id" => $idtax,
							"curp" => $curp_txt,
						));

					if ($insertProv) {
						$selecProv = DB::select("SELECT id FROM sos_personas WHERE token_personas = ?", [$tkn_people_prov]);
						$tokenProv = $JwtAuth->encriptarToken($folio_prov_temp . $selecProv[0]->id . $tkn_people_prov . $selectEmp[0]->id);

						$creaCatProv = new ProveedoresModelo();
						$creaCatProv->token_cat_proveedores	= $tokenProv;
						$creaCatProv->folio	= $folio_nuevo;
						$creaCatProv->post_folio = $post_folio;
						$creaCatProv->fechaAlta = $fechaAlta;
						$creaCatProv->proveedor = $selecProv[0]->id;
						//$creaCatProv->lista_precios = NULL;
						$creaCatProv->regimen_fiscal = $regimen_fiscal_txt;
						$creaCatProv->temp_folio = $folio_temporal;
						$creaCatProv->authorized = FALSE;
						$creaCatProv->status = TRUE;
						$creaCatProv->fecha_delete_prov = "";
						$creaCatProv->administrador = $selectEmp[0]->id;
						$savednewProv = $creaCatProv->save();

						if ($savednewProv) {
							$selectProvCat = DB::select("SELECT id FROM eegr_catalogo_proveedores 
                                WHERE token_cat_proveedores = ?", [$tokenProv]);

							$contadorInsertUbicaciones = 0;

							if ($radioProv == 'extranjero') {
								$tipo_direccion = 'dirección fiscal';
								$cpostalDir = $JwtAuth->encriptar($cod_postal);
								$clasificacionDir = $JwtAuth->encriptar("matriz");
								$tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $cpostalDir, $clasificacionDir);

								$fisinsertDir = DB::table("teci_direcciones")
									->insert(array(
										"token_direccion" => $tokenCDir,
										"tipo_direccion" => $tipo_direccion,
										"clase" => $clasificacionDir,
										"cod_postalext" => $cpostalDir,
										"pais" => $pais_txt,
										"proveedor" => $selectProvCat[0]->id,
										"status" => TRUE,
										"administrador" => $selectEmp[0]->id,
									));
								if ($fisinsertDir) {
									$contadorInsertUbicaciones++;
								}
							} else {
								if (count($listnewdireccionNac) != 0) {
									for ($nd = 0; $nd < count($listnewdireccionNac); $nd++) {
										$listnew_estado = $listnewdireccionNac[$nd]["estado"];
										$listnew_municipio = $listnewdireccionNac[$nd]["municipio"];
										$listnew_codigo_postal = $listnewdireccionNac[$nd]["codigo_postal"];
										$listnew_colonia = $listnewdireccionNac[$nd]["colonia"];

										$tipo_direccion = "dirección fiscal";
										$clasificacionDir = $JwtAuth->encriptar("matriz");
										$tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $listnew_estado, $listnew_municipio, $listnew_codigo_postal, $listnew_colonia, $clasificacionDir);
										$fisinsertDir = DB::table("teci_direcciones")
											->insert(array(
												"token_direccion" => $tokenCDir,
												"tipo_direccion" => $tipo_direccion,
												"clase" => $clasificacionDir,
												"pais" => 118,
												"estado_edit" => $JwtAuth->encriptar($listnew_estado),
												"municipio_edit" => $JwtAuth->encriptar($listnew_municipio),
												"c_postal_edit" => $listnew_codigo_postal,
												"colonia_edit" => $JwtAuth->encriptar($listnew_colonia),
												"adicional" => "no_api_found",
												"proveedor" => $selectProvCat[0]->id,
												"status" => TRUE,
												"administrador" => $selectEmp[0]->id,
											));
									}
								} else {
									$tipo_direccion = "dirección fiscal";
									$clasificacionDir = $JwtAuth->encriptar("matriz");
									$tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $dipomex_cod_postal_estado, $dipomex_cod_postal_municipio, $dipomex_cod_postal_cp, $dipomex_cod_postal_colonia_vinculada, $clasificacionDir);
									$listnewdireccionNac = $parametrosArray["listnewdireccionNac"];
									$fisinsertDir = DB::table("teci_direcciones")
										->insert(array(
											"token_direccion" => $tokenCDir,
											"tipo_direccion" => $tipo_direccion,
											"clase" => $clasificacionDir,
											"pais" => 118,
											"estado_edit" => $JwtAuth->encriptar($dipomex_cod_postal_estado),
											"municipio_edit" => $JwtAuth->encriptar($dipomex_cod_postal_municipio),
											"c_postal_edit" => $dipomex_cod_postal_cp,
											"colonia_edit" => $JwtAuth->encriptar($dipomex_cod_postal_colonia_vinculada),
											"adicional" => "api",
											"proveedor" => $selectProvCat[0]->id,
											"status" => TRUE,
											"administrador" => $selectEmp[0]->id,
										));
								}

								if ($fisinsertDir) {
									$contadorInsertUbicaciones++;
								}
							}

							//return response()->json(["message" => "prueba25","code" => 200,"status" => "error"]);
							if ($contadorInsertUbicaciones == 1) {
								//return response()->json(["message" => "prueba26","code" => 200,"status" => "error"]);
								$filepath = $selectEmp[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $folio_prov_temp . "-" . $fechaAlta . "/";
								//return response()->json(["message" => "prueba27","code" => 200,"status" => "error"]);
								if (!file_exists(storage_path("/root/" . $filepath))) {
									Storage::disk("root")->makeDirectory($filepath, 0777, true, true);
								}
								//return response()->json(["message" => "prueba28","code" => 200,"status" => "error"]);
								//QRCode::text($tokenProv)->setOutfile(Storage::path("public/root/" . $filepath . $folio_prov_temp . " - " . $fechaAlta . "-QRCode.png"))->png();
								//$cumplimiento;
								//$formaPagoClabeInterbank;
								//return response()->json(["message" => "prueba32","code" => 200,"status" => "error"]);
								$JwtAuth->insertBitacoraActividad(
									"egresos",
									"catalogos",
									"proveedores",
									$folio_prov_temp,
									"registro en el catalogo de proveedores",
									$usuario->empresa_token,
									$usuario->user_token
								);
								//return response()->json(["message" => "prueba33","code" => 200,"status" => "error"]);
								$dataMensaje = array(
									"status" => 'success',
									"code" => 200,
									"message" => "Proveedor registrado satisfactoriamente con el folio " . $folio_prov_temp
								);
							} else {
								$dataMensaje = array(
									"status" => "error",
									"code" => 200,
									"message" => "Datos de personal de contacto/direcciones/creditos de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información"
								);
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'ya existe un proveedor con esta información'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function registraProveedorModuloCompras(Request $request){
		$JwtAuth = new \JwtAuth();
		$jsonServ = $request->input('proveedor');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);
		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				"user_token" => "string",
				"rfc_generico" => "required|string",
				"prov_rfc" => "string",
				"id_tax" => "string",
				"radioProv" => "required|string",
				"subtipoProv" => "required|string",
				"paterno" => "string",
				"materno" => "string",
				"nombres" => "string",
				"razon_social" => "string",
				"comercial_nombre" => "string",
				"curp" => "string",
				"paistoken" => "string",
				"sitio_web" => "string",
				"tknRegimenFiscal" => "string",
				"decideinfocontacto" => "boolean",
				"listaContactoPersonal" => "array",
				"tiene_docs_fiscales" => "boolean",
				//'valnoCargaDocsFiscalesRazon' => 'string',
				"decideaceptcredito" => "boolean",
				"token_moneda" => "string",
				"limite_credito" => "string",
				"dias_pago_credito" => "numeric",
				"comienzacomputo_credito" => "string",
				"decideformapago" => "boolean",
				"token_forma_pago" => "string",
				"tipoReferenciaPago" => "string",
				"clabe_interbancaria" => "string",
				"receptFactura" => "boolean",
				"classRecibeArtPago" => "boolean",

				"cod_postal" => "string",
				"dipomex_cod_postal_estado" => "string",
				"dipomex_cod_postal_municipio" => "string",
				"dipomex_cod_postal_cp" => "string",
				"dipomex_cod_postal_colonia_vinculada" => "string",
				"listnewdireccionNac" => "array",
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
				$fechaAlta = time();
				$rfc_generico = $parametrosArray["rfc_generico"];
				$prov_rfc = $parametrosArray["prov_rfc"];
				$rfc_prov = NULL;
				$id_tax = $parametrosArray["id_tax"];
				$idtax = NULL;
				$radioProv = $parametrosArray["radioProv"];
				$subtipoProv = $parametrosArray["subtipoProv"];
				$paterno = $parametrosArray["paterno"];
				$materno = $parametrosArray["materno"];
				$nombres = $parametrosArray["nombres"];
				$razon_social = $parametrosArray["razon_social"];
				$comercial_nombre = $parametrosArray["comercial_nombre"];
				$curp = $parametrosArray["curp"];
				$paistoken = $parametrosArray["paistoken"];
				$sitio_web = $parametrosArray["sitio_web"];
				$tknRegimenFiscal = $parametrosArray["tknRegimenFiscal"];

				$decideinfocontacto = $parametrosArray["decideinfocontacto"];
				$listaContactoPersonal = $parametrosArray["listaContactoPersonal"];
				$tiene_docs_fiscales = $parametrosArray["tiene_docs_fiscales"] == true ? TRUE : FALSE;
				//$valnoCargaDocsFiscalesRazon = $parametrosArray["valnoCargaDocsFiscalesRazon"];
				$decideaceptcredito = $parametrosArray["decideaceptcredito"] == true ? TRUE : FALSE;
				$token_moneda = $parametrosArray["token_moneda"];
				$limite_credito = $parametrosArray["decideaceptcredito"] == true ? $parametrosArray["limite_credito"] : NULL;
				$dias_pago_credito = $parametrosArray["decideaceptcredito"] == true ? $parametrosArray["dias_pago_credito"] : NULL;
				$comienzacomputo_credito = $parametrosArray["decideaceptcredito"] == true ? $parametrosArray["comienzacomputo_credito"] : NULL;
				$decideformapago = $parametrosArray["decideformapago"] == true ? TRUE : FALSE;
				$token_forma_pago = $parametrosArray["token_forma_pago"];
				$tipoReferenciaPago = $parametrosArray["decideformapago"] == true ? $parametrosArray["tipoReferenciaPago"] : NULL;
				$clabe_interbancaria = $parametrosArray["decideformapago"] == true ? $parametrosArray["clabe_interbancaria"] : NULL;
				$receptFactura = $parametrosArray["receptFactura"] == true ? TRUE : FALSE;
				$classRecibeArtPago = $parametrosArray["classRecibeArtPago"] == true ? TRUE : FALSE;

				$cod_postal = $parametrosArray["cod_postal"];
				$dipomex_cod_postal_estado = $parametrosArray["dipomex_cod_postal_estado"];
				$dipomex_cod_postal_municipio = $parametrosArray["dipomex_cod_postal_municipio"];
				$dipomex_cod_postal_cp = $parametrosArray["dipomex_cod_postal_cp"];
				$dipomex_cod_postal_colonia_vinculada = $parametrosArray["dipomex_cod_postal_colonia_vinculada"];
				$listnewdireccionNac = $parametrosArray["listnewdireccionNac"];

				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
				$patronNumCred = '/^[0-9$,.-]*$/';
				$patronRfc = '/[aA0-zZ9]/';
				$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';
				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM main_empresas AS emp 
                    WHERE emp.empresa_token = ?", [$usuario->empresa_token]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$folio_nuevo = NULL;
				$post_folio = NULL;

				$folioSistemaTemp = DB::select("SELECT temp_folio FROM eegr_catalogo_proveedores WHERE temp_folio IS NOT NULL AND administrador = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$usuario->empresa_token]);
				if (count($folioSistemaTemp) > 0) {
					$queryFolioTmpPrv = DB::select("SELECT temp_folio+1 AS temp_folio FROM eegr_catalogo_proveedores 
						WHERE id = (SELECT Max(catprov.id) FROM eegr_catalogo_proveedores AS catprov 
						JOIN main_empresas AS emp WHERE temp_folio IS NOT NULL AND catprov.administrador = emp.id 
						AND emp.empresa_token = ?)", [$usuario->empresa_token]);
					
					foreach ($queryFolioTmpPrv as $vTemp) {
						$folio_temporal = $vTemp->temp_folio;
					}
				} else {
					$folio_temporal = 1;
				}

				$folio_prov_temp = 'PRV-TEMP-'.$JwtAuth->generarFolio($folio_temporal);

				if ($prov_rfc != "") {
					if (isset($prov_rfc) && isset($prov_rfc) && preg_match($patronRfc, $prov_rfc)) {
						$rfc_prov = $JwtAuth->encriptar($prov_rfc);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'error al registrar rfc del proveedor'
						);
					}
				} else {
					$rfc_prov = NULL;
				}

				if ($id_tax != "") {
					if (isset($id_tax) && preg_match($patronRfc, $id_tax)) {
						$idtax = $JwtAuth->encriptar($id_tax);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'error al registrar idtax del proveedor'
						);
					}
				} else {
					$idtax = NULL;
				}

				$paterno_txt = NULL;
				$materno_txt = NULL;
				$nombres_txt = NULL;
				$razon_social_txt = NULL;
				$empresa_txt = NULL;
				$comercial_nombre_txt = NULL;
				$curp_txt = NULL;
				$pais_txt = NULL;
				$sitio_web_txt = NULL;
				$regimen_fiscal_txt = NULL;

				if (isset($radioProv) && isset($radioProv) && preg_match($patron, $radioProv)) {
					if ($radioProv == "extranjero") {
						if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
							if ($subtipoProv == "provMoral") {
								//return response()->json(['message' => $parametrosArray['pais'],'codigo' => 200,'status' => 'error']);
								if (
									isset($razon_social) && !empty($razon_social) && preg_match($patron, $razon_social) &&
									isset($paistoken) && !empty($paistoken)
								) {
									//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
									$razon_social_txt = $JwtAuth->encriptar($razon_social);
									$empresa_txt = $razon_social;
									//echo "razon_social ".$razon_social_txt;exit;$JwtAuth->encriptar("cnlZSktiM1FlMHlqbk13ZFhkS0ozUT09OjoxMjM0NTY3ODEyMzQ1Njc4")
									//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
									$selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
									$pais_txt = $selectPais[0]->id;
									//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
									if (!empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
										if (preg_match($patron, $comercial_nombre)) {
											$comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}
										if (preg_match($patronUrl, $sitio_web)) {
											$sitioweb = $JwtAuth->encriptar($sitio_web);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$nombre_comercial = NULL;
										$sitioweb = NULL;
									}
								} else {
									if (!isset($razon_social) || empty($razon_social) || !preg_match($patron, $razon_social)) {
										$dataMensaje = array(
											"status" => "error",
											"code" => 200,
											"codeProvGenError" => "nomemp",
											"message" => "Error en nombre de empresa de su proveedor"
										);
									}
									if (!isset($paistoken) || empty($paistoken)) {
										$dataMensaje = array(
											"status" => "error",
											"code" => 200,
											"codeProvGenError" => "pais",
											"message" => "Error en pais de su proveedor"
										);
									}
								}
							}
							//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);		
							if ($subtipoProv == 'provFisica') {
								if (
									isset($paterno) && !empty($paterno) && preg_match($patron, $paterno) &&
									isset($materno) && !empty($materno) && preg_match($patron, $materno) &&
									isset($nombres) && !empty($nombres) && preg_match($patron, $nombres) &&
									isset($paistoken) && !empty($paistoken)
								) {

									$paterno_txt = $JwtAuth->encriptar($paterno);
									$materno_txt = $JwtAuth->encriptar($materno);
									$nombres_txt = $JwtAuth->encriptar($nombres);
									$empresa_txt = $paterno . ' ' . $materno . ' ' . $nombres;

									if (
										isset($comercial_nombre) && !empty($comercial_nombre) &&
										isset($sitio_web) && !empty($sitio_web)
									) {

										if (preg_match($patron, $comercial_nombre)) {
											$comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}

										if (preg_match($patronUrl, $sitio_web)) {
											$sitio_web_txt = $JwtAuth->encriptar($sitio_web);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$comercial_nombre_txt = NULL;
										$sitio_web_txt = NULL;
									}

									$selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
									$pais_txt = $selectPais[0]->id;
								} else {
									if (!isset($paterno) || empty($paterno) || !preg_match($patron, $paterno)) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'paternoPF',
											'message' => 'Error en apellido paterno de su proveedor'
										);
									}
									if (!isset($materno) || empty($materno) || !preg_match($patron, $materno)) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'maternoPF',
											'message' => 'Error en apellido materno de su proveedor'
										);
									}
									if (!isset($nombres) || empty($nombres) || !preg_match($patron, $nombres)) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'nombrePF',
											'message' => 'Error en nombre de su proveedor'
										);
									}
									if (!isset($paistoken) || empty($paistoken)) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'paisPF',
											'message' => 'Error en pais de su proveedor'
										);
									}
								}
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'clbint',
								'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
							);
						}
					}

					if ($radioProv == 'nacional') {
						if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
							if ($subtipoProv == 'provMoral') {
								if (isset($razon_social) && !empty($razon_social) && preg_match($patron, $razon_social)) {
									$razon_social_txt = $JwtAuth->encriptar($razon_social);
									$empresa_txt = $razon_social;
									if (
										isset($comercial_nombre) && !empty($comercial_nombre) &&
										isset($sitio_web) && !empty($sitio_web)
									) {
										if (preg_match($patron, $comercial_nombre)) {
											$nombre_comercial = $JwtAuth->encriptar($comercial_nombre);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}
										if (preg_match($patronUrl, $sitio_web)) {
											$sitioweb = $JwtAuth->encriptar($sitio_web);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$comercial_nombre_txt = NULL;
										$sitio_web_txt = NULL;
									}

									$pais_txt = '118';
								} else {
									if (!isset($razon_social) || empty($razon_social) || !preg_match($patron, $razon_social)) {
										$dataMensaje = array(
											"status" => "error",
											"code" => 200,
											"codeProvGenError" => "nomemp",
											"message" => "Error en nombre de empresa de su proveedor"
										);
									}
								}
							}

							if ($subtipoProv == 'provFisica') {
								if (
									isset($paterno) && !empty($paterno) && preg_match($patron, $paterno) &&
									isset($materno) && !empty($materno) && preg_match($patron, $materno) &&
									isset($nombres) && !empty($nombres) && preg_match($patron, $nombres)
								) {

									$paterno_txt = $JwtAuth->encriptar($paterno);
									$materno_txt = $JwtAuth->encriptar($materno);
									$nombres_txt = $JwtAuth->encriptar($nombres);
									$empresa_txt = $paterno . ' ' . $materno . ' ' . $nombres;

									if (
										isset($comercial_nombre) && !empty($comercial_nombre) &&
										isset($curp) && !empty($curp) &&
										isset($sitio_web) && !empty($sitio_web)
									) {

										if (preg_match($patron, $comercial_nombre)) {
											$comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}

										if (preg_match($patronRfc, $curp)) {
											$curp_txt = $JwtAuth->encriptar($curp);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'clbint',
												'message' => 'Error en curp de su proveedor'
											);
										}

										if (preg_match($patronUrl, $sitio_web)) {
											$sitio_web_txt = $JwtAuth->encriptar($sitio_web);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$comercial_nombre_txt = NULL;
										$curp_txt = NULL;
										$sitio_web_txt = NULL;
									}

									$pais_txt = '118';
								} else {
									if (!isset($paterno) || empty($paterno) || !preg_match($patron, $paterno)) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'paternoPF',
											'message' => 'Error en apellido paterno de su proveedor'
										);
									}

									if (!isset($materno) || empty($materno) || !preg_match($patron, $materno)) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'maternoPF',
											'message' => 'Error en apellido materno de su proveedor'
										);
									}

									if (!isset($nombres) || empty($nombres) || !preg_match($patron, $nombres)) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'nombrePF',
											'message' => 'Error en nombre de su proveedor'
										);
									}
								}
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'clbint',
								'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
							);
						}
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'codeProvGenError' => 'clbint',
						'message' => 'Seleccione tipo de proveedor (nacional o extranjero)'
					);
				}

				if ($decideformapago == TRUE) {
					$token_forma_pago = $parametrosArray["token_forma_pago"];
					$selectFpago = DB::select("SELECT id FROM teci_forma_pago WHERE token_formapago = ?", [$token_forma_pago]);
					$forma_pago_ident = $selectFpago[0]->id;
				} else {
					$forma_pago_ident = NULL;
				}

				$listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
					->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
					->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
					->where([
						'emp.empresa_token' => $usuario->empresa_token,
						'eegr_catalogo_proveedores.status' => true
					])->get();

				$countVerifica = 0;
				$invalidName = '';

				foreach ($listaProveedores as $resListProv) {
					$nombreProv_f = strtolower($JwtAuth->desencriptar($resListProv->nombre_extendido));

					$rfc_generico_f = strtolower($resListProv->rfc_generico);
					if ($rfc_prov != NULL) {
						if ($resListProv->rfc == $rfc_prov) {
							++$countVerifica;
							$invalidName = $nombreProv_f;
						}
					} else if ($idtax != '') {
						if ($resListProv->tax_id == $idtax) {
							++$countVerifica;
							$invalidName = $nombreProv_f;
						}
					} else if ($resListProv->rfc_generico == $rfc_generico && $nombreProv_f == $empresa_txt) {
						++$countVerifica;
						$invalidName = $nombreProv_f;
					} else {
						if ($nombreProv_f == $empresa_txt) {
							++$countVerifica;
							$invalidName = $nombreProv_f;
						}
					}
				}
				//echo $razon_social_txt; exit;

				if ($countVerifica == 0) {
					$tkn_people_prov = $JwtAuth->encriptarToken(
						$fechaAlta,
						$paterno_txt,
						$materno_txt,
						$nombres_txt,
						$razon_social_txt,
						$comercial_nombre_txt,
						$sitio_web_txt,
						$pais_txt,
						$rfc_prov
					);

					$insertProv = DB::table("sos_personas")
						->insert(array(
							"token_personas" => $tkn_people_prov,
							//"paterno" => $paterno_txt,
							//"materno" => $materno_txt,
							//"nombre" => $nombres_txt,
							"denominacion_rs" => $razon_social_txt,
							"nombre_com" => $comercial_nombre_txt,
							"sitio_web" => $sitio_web_txt,
							"nacionalidad" => $pais_txt,
							"rfc_generico" => $rfc_generico,
							"rfc" => $rfc_prov,
							"tax_id" => $idtax,
							"curp" => $curp_txt,
						));

					if ($insertProv) {
						$selecProv = DB::select("SELECT id FROM sos_personas WHERE token_personas = ?", [$tkn_people_prov]);
						$tokenProv = $JwtAuth->encriptarToken($folio_prov_temp . $selecProv[0]->id . $tkn_people_prov . $selectEmp[0]->id);

						$creaCatProv = new ProveedoresModelo();
						$creaCatProv->token_cat_proveedores	= $tokenProv;
						$creaCatProv->folio	= $folio_nuevo;
						$creaCatProv->post_folio = $post_folio;
						$creaCatProv->temp_folio = $folio_temporal;
						$creaCatProv->authorized = FALSE;
						$creaCatProv->fechaAlta = $fechaAlta;
						$creaCatProv->proveedor = $selecProv[0]->id;
						$creaCatProv->regimen_fiscal = $regimen_fiscal_txt;
						$creaCatProv->tiene_docs_fiscales = $tiene_docs_fiscales;
						$creaCatProv->forma_pago = $forma_pago_ident;
						$creaCatProv->tipo_referencia_pago = $tipoReferenciaPago;
						$creaCatProv->clabe_interbancaria = $clabe_interbancaria;
						$creaCatProv->receptFactura = $receptFactura;
						$creaCatProv->classRecibeArtPago = $classRecibeArtPago;
						$creaCatProv->status = TRUE;
						$creaCatProv->fecha_delete_prov = "";
						$creaCatProv->administrador = $selectEmp[0]->id;
						$savednewProv = $creaCatProv->save();

						if ($savednewProv) {
							$selectProvCat = DB::select("SELECT id FROM eegr_catalogo_proveedores WHERE token_cat_proveedores = ?", [$tokenProv]);

							if ($parametrosArray["decideaceptcredito"] == true) {
								$dbMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?", [$token_moneda]);
								$selectMoneda = $dbMoneda[0]->id;
							} else {
								$selectMoneda = NULL;
							}

							$tokenCreditos = $JwtAuth->encriptarToken($selectProvCat[0]->id . $fechaAlta . $decideaceptcredito);
							$insertaCreditoProv = DB::table('in_egr_creditos')
								->insert(array(
									"token_creditos" => $tokenCreditos,
									"proveedor" => $selectProvCat[0]->id,
									"aceptacredito" => $decideaceptcredito,
									"moneda" => $selectMoneda,
									"limite" => $limite_credito,
									"dias" => $dias_pago_credito,
									"comienza" => $comienzacomputo_credito,
								));

							$contadorInsertUbicaciones = 0;

							if ($radioProv == 'extranjero') {
								$tipo_direccion = 'dirección fiscal';
								$cpostalDir = $JwtAuth->encriptar($cod_postal);
								$clasificacionDir = $JwtAuth->encriptar("matriz");
								$tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $cpostalDir, $clasificacionDir);

								$fisinsertDir = DB::table("teci_direcciones")
									->insert(array(
										"token_direccion" => $tokenCDir,
										"tipo_direccion" => $tipo_direccion,
										"clase" => $clasificacionDir,
										"cod_postalext" => $cpostalDir,
										"pais" => $pais_txt,
										"proveedor" => $selectProvCat[0]->id,
										"status" => TRUE,
										"administrador" => $selectEmp[0]->id,
									));
								if ($fisinsertDir) {
									$contadorInsertUbicaciones++;
								}
							} else {
								if (count($listnewdireccionNac) != 0) {
									for ($nd = 0; $nd < count($listnewdireccionNac); $nd++) {
										$listnew_estado = $listnewdireccionNac[$nd]["estado"];
										$listnew_municipio = $listnewdireccionNac[$nd]["municipio"];
										$listnew_codigo_postal = $listnewdireccionNac[$nd]["codigo_postal"];
										$listnew_colonia = $listnewdireccionNac[$nd]["colonia"];

										$tipo_direccion = $nd == 0 ? "dirección fiscal" : "dirección sucursal";
										$clasificacionDir = $JwtAuth->encriptar("matriz");
										$tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $listnew_estado, $listnew_municipio, $listnew_codigo_postal, $listnew_colonia, $clasificacionDir);
										$fisinsertDir = DB::table("teci_direcciones")
											->insert(array(
												"token_direccion" => $tokenCDir,
												"tipo_direccion" => $tipo_direccion,
												"clase" => $clasificacionDir,
												"pais" => 118,
												"estado_edit" => $JwtAuth->encriptar($listnew_estado),
												"municipio_edit" => $JwtAuth->encriptar($listnew_municipio),
												"c_postal_edit" => $listnew_codigo_postal,
												"colonia_edit" => $JwtAuth->encriptar($listnew_colonia),
												"adicional" => "no_api_found",
												"proveedor" => $selectProvCat[0]->id,
												"status" => TRUE,
												"administrador" => $selectEmp[0]->id,
											));
									}
								} else {
									$tipo_direccion = "dirección fiscal";
									$clasificacionDir = $JwtAuth->encriptar("matriz");
									$tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $dipomex_cod_postal_estado, $dipomex_cod_postal_municipio, $dipomex_cod_postal_cp, $dipomex_cod_postal_colonia_vinculada, $clasificacionDir);
									$listnewdireccionNac = $parametrosArray["listnewdireccionNac"];
									$fisinsertDir = DB::table("teci_direcciones")
										->insert(array(
											"token_direccion" => $tokenCDir,
											"tipo_direccion" => $tipo_direccion,
											"clase" => $clasificacionDir,
											"pais" => 118,
											"estado_edit" => $JwtAuth->encriptar($dipomex_cod_postal_estado),
											"municipio_edit" => $JwtAuth->encriptar($dipomex_cod_postal_municipio),
											"c_postal_edit" => $dipomex_cod_postal_cp,
											"colonia_edit" => $JwtAuth->encriptar($dipomex_cod_postal_colonia_vinculada),
											"adicional" => "api",
											"proveedor" => $selectProvCat[0]->id,
											"status" => TRUE,
											"administrador" => $selectEmp[0]->id,
										));
								}

								if ($fisinsertDir) {
									$contadorInsertUbicaciones++;
								}
							}

							if ($decideinfocontacto == true) {
								for ($i = 0; $i < count($listaContactoPersonal); $i++) {
									$contArea = $JwtAuth->encriptar($listaContactoPersonal[$i]['area']); //area
									$contCargo = $JwtAuth->encriptar($listaContactoPersonal[$i]['cargo']);
									$contApePaterno = $JwtAuth->encriptar($listaContactoPersonal[$i]['paterno']);
									$contApeMaterno = $JwtAuth->encriptar($listaContactoPersonal[$i]['materno']);
									$contNombre = $JwtAuth->encriptar($listaContactoPersonal[$i]['nombre']);

									$tokenPersonasPersonal = $JwtAuth->encriptarToken($contArea . '/' . $contCargo . '/' . $contApePaterno . '/' . $contApeMaterno . '/' . $selectEmp[0]->id . '/' . $contArea);
									$insertapersonalpersonas = DB::table('sos_personas')
										->insert(array("token_personas" => $tokenPersonasPersonal, "paterno" => $contApePaterno, "materno" => $contApeMaterno, "nombre" => $contNombre));

									$selectpersonalpersonas = DB::select("SELECT id FROM sos_personas WHERE token_personas = ?", [$tokenPersonasPersonal]);
									$tokenPersonal = $JwtAuth->encriptarToken($contApePaterno . '/' . $contNombre . '/' . $contApeMaterno . '/' . $selectEmp[0]->id . '/' . $contArea);
									$insertapersonal = DB::table('in_egr_contacto_cliente_proveedor')->insert(
										array(
											"token_contacto" => $tokenPersonal,
											"fecha_alta_contacto" => time(),
											"folio_contacto" => $i,
											"area_contacto" => $contArea,
											"cargo_contacto" => $contCargo,
											"nombre" => $selectpersonalpersonas[0]->id,
											"cat_proveedores" => $selectProvCat[0]->id,
											"status" => TRUE,
											"fecha_delete" => NULL
										)
									);
									$selectContacto = DB::select("SELECT id FROM in_egr_contacto_cliente_proveedor WHERE token_contacto = ?", [$tokenPersonal]);
									$personalTelefonos = $listaContactoPersonal[$i]['telefonos'];
									if (count($personalTelefonos) != 0) {
										for ($t = 0; $t < count($personalTelefonos); $t++) {
											$contTelefono = $JwtAuth->encriptar($personalTelefonos[$t]['telefono']);
											$contExtension = $personalTelefonos[$t]['extension'] != '' ? $JwtAuth->encriptar($personalTelefonos[$t]['extension']) : NULL;
											$tokentel = $JwtAuth->encriptarToken($tokenPersonal . $contTelefono);
											$principal = $t == 0 ? TRUE : FALSE;
											//return response()->json(['message' => $personalTelefonos[$t]["etiqueta"],'code' => 200,'status' => 'error']);
											$insertatelefonos_personal = DB::table('sos_personas_telefonos')
												->insert(array(
													"token_telefono" => $tokentel,
													"contacto_cliente_prov" => $selectContacto[0]->id,
													//"icono" => $personalTelefonos[$t]["icon"],	
													"etiqueta" => $personalTelefonos[$t]["etiqueta"],
													"cod_pais" => 52,
													"telefono" => $contTelefono,
													"principal" => $principal,
													"extension" => $contExtension,
													"status_telefono" => TRUE,
													"fecha_delete_tel" => NULL,

												));
										}
									}

									$personalEmails = $listaContactoPersonal[$i]['emails'];
									if (count($personalEmails) != 0) {
										for ($m = 0; $m < count($personalEmails); $m++) {
											$contEmail = $JwtAuth->encriptar($personalEmails[$m]);
											$tokenEmail = $JwtAuth->encriptarToken($personalEmails[$m], $contEmail, $selectContacto[0]->id);
											$insertacorreos_personal = DB::table('sos_personas_correos')
												->insert(array(
													"token_correo" => $tokenEmail,
													"contacto_cliente_prov" => $selectContacto[0]->id,
													"correo" => $contEmail,
													"status_correo" => TRUE,
													"fecha_delete_correo" => NULL,
												));
										}
									}
								}
							}

							if ($contadorInsertUbicaciones == 1) {
								$filepath = $selectEmp[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $folio_prov_temp . "-" . $fechaAlta . "/";
								if (!file_exists(storage_path("/root/" . $filepath))) {
									Storage::disk("root")->makeDirectory($filepath, 0777, true, true);
								}
								if (file_exists($request->file('docSituacionFiscal'))) {
									$namesitfiscal = $fechaAlta . '-' . $request->file('docSituacionFiscal')->getClientOriginalName();
									$typesitfiscal = $JwtAuth->getExtensionDoc($request->file('docSituacionFiscal')->getClientMimeType());
									$tkn_doc_sitfiscal = $JwtAuth->encriptarToken($tokenProv, $usuario->user_token, $usuario->empresa_token, $namesitfiscal);
									$JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_sitfiscal, "fcsf", $namesitfiscal, $typesitfiscal);
									Storage::putFileAs("/public/root/" . $filepath, $request->file('docSituacionFiscal'), $namesitfiscal);
								}

								if (file_exists($request->file('docCumplimientoObFiscales'))) {
									$namecumplimiento = $fechaAlta . '-' . $request->file('docCumplimientoObFiscales')->getClientOriginalName();
									$typecumplimiento = $JwtAuth->getExtensionDoc($request->file('docCumplimientoObFiscales')->getClientMimeType());
									$tkn_doc_cumplimiento = $JwtAuth->encriptarToken($tokenProv, $usuario->user_token, $usuario->empresa_token, $namecumplimiento);
									$JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_cumplimiento, "cuof", $namecumplimiento, $typecumplimiento);
									Storage::putFileAs("/public/root/" . $filepath, $request->file('docCumplimientoObFiscales'), $namecumplimiento);
								}

								if (file_exists($request->file('docContrato'))) {
									$namecontrato = $fechaAlta . '-' . $request->file('docContrato')->getClientOriginalName();
									$typecontrato = $JwtAuth->getExtensionDoc($request->file('docContrato')->getClientMimeType());
									$tkn_doc_contrato = $JwtAuth->encriptarToken($tokenProv, $usuario->user_token, $usuario->empresa_token, $namecontrato);
									$JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_contrato, "fcnt", $namecontrato, $typecontrato);
									Storage::putFileAs("/public/root/" . $filepath, $request->file('docContrato'), $namecontrato);
								}

								if (isset($_FILES['files_anexos']) && !empty($_FILES['files_anexos'])) {
									$anexo_archivos = $_FILES["files_anexos"];
									$anexo_archivos_strings = json_decode(json_encode($_FILES["files_anexos"]["name"]));
									if (count($anexo_archivos_strings) > 0) {
										for ($i = 0; $i < count($anexo_archivos_strings); $i++) {
											$docAnexo_temporal = $anexo_archivos["tmp_name"][$i];
											$docAnexo_name = "anexos/" . $anexo_archivos["name"][$i];
											$docAnexo_type = $JwtAuth->getExtensionDoc($anexo_archivos["type"][$i]);
											$docAnexo_tknn = $JwtAuth->encriptarToken($tokenProv, $usuario->user_token, $usuario->empresa_token, $docAnexo_name);
											$JwtAuth->registraDocsProveedor($tokenProv, $docAnexo_tknn, "anex", $docAnexo_name, $docAnexo_type);
											Storage::putFileAs("/public/root/" . $filepath, $docAnexo_temporal, $docAnexo_name);
										}
									}
								}

								if (file_exists($request->file('docEstadoCuenta'))) {
									$nameestadocuenta = $fechaAlta . '-' . $request->file('docEstadoCuenta')->getClientOriginalName();
									$typeestadocuenta = $JwtAuth->getExtensionDoc($request->file('docEstadoCuenta')->getClientMimeType());
									$tkn_doc_estadocuenta = $JwtAuth->encriptarToken($tokenProv, $usuario->user_token, $usuario->empresa_token, $nameestadocuenta);
									$JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_estadocuenta, "ecue", $nameestadocuenta, $typeestadocuenta);
									Storage::putFileAs("/public/root/" . $filepath, $request->file('docEstadoCuenta'), $nameestadocuenta);
								}

								$JwtAuth->insertBitacoraActividad(
									"egresos",
									"catalogos",
									"proveedores",
									$folio_prov_temp,
									"registro en el catalogo de proveedores",
									$usuario->empresa_token,
									$usuario->user_token
								);
								//return response()->json(['message' => 'gantt_diagram','code' => 200,'status' => 'error']);
								//return response()->json(["message" => "prueba33","code" => 200,"status" => "error"]);
								$dataMensaje = array(
									"status" => 'success',
									"code" => 200,
									"message" => "Proveedor registrado satisfactoriamente con el folio " . $folio_prov_temp
								);
							} else {
								$dataMensaje = array(
									"status" => "error",
									"code" => 200,
									"message" => "Datos de personal de contacto/direcciones/creditos de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información"
								);
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'ya existe un proveedor con esta información'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function registraProveedorMax(Request $request){
		$JwtAuth = new \JwtAuth();
		$imagenAltaPdfFiscal = $request->file('imagenAltaPdfFiscal');
		//formData.append('base64AltaPdfFiscal',base64AltaPdfFiscal);
		$imagenAltaPdfCumplimientoObFiscales = $request->file('imagenAltaPdfCumplimientoObFiscales');
		//formData.append('base64AltaPdfCumplimientoObFiscales',base64AltaPdfCumplimientoObFiscales);
		$imagenAltaPdfEstCuenta = $request->file('imagenAltaPdfEstCuenta');
		//formData.append('base64AltaPdfEstCuenta',base64AltaPdfEstCuenta);
		$jsonServ = $request->input('proveedor');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'user_token' => 'required|string',
				'rfc_generico' => 'required|string',
				'prov_rfc' => 'string',
				'id_tax' => 'string',
				'radioProv' => 'required|string',
				'subtipoProv' => 'required|string',
				'name_prov' => 'string',
				'comercial_nombre' => 'string',
				'curp' => 'string',
				'paistoken' => 'string',
				'sitio_web' => 'string',
				'tknRegimenFiscal' => 'string',
				'decideinfocontacto' => 'required|boolean',
				'arrayContactoPersonalProvv_reg' => 'array',
				'tiene_docs_fiscales' => 'required|boolean',
				'valnoCargaDocsFiscalesRazon' => 'string',
				'aceptaCredito' => 'required|boolean',
				'txtMoneda' => 'string',
				'txtlimiteCredito' => 'string',
				'txtdiaspagoCredit' => 'numeric',
				'selectComienzaPagoProv' => 'string',
				'decideformapago' => 'boolean',
				'formaPagoAltaProv' => 'string',//"token_forma_pago" => "string",
				'tipoReferenciaPago' => 'string',
				'clabeIntBanc' => 'string',
        'receptFactura' => 'boolean',
				'classRecibeArtPago' => 'boolean',
				'cod_postal' => 'string',
				'dipomex_cod_postal_estado' => 'string',
				'dipomex_cod_postal_municipio' => 'string',
				'dipomex_cod_postal_cp' => 'string',
				'dipomex_cod_postal_colonia_vinculada' => 'string',
				'listnewdireccionNac' => 'array',
			]);
			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
				$rfc_generico = $parametrosArray['rfc_generico'];
				$prov_rfc = $parametrosArray['prov_rfc'];
				$id_tax = $parametrosArray['id_tax'];
				$radioProv = $parametrosArray['radioProv'];
				$subtipoProv = $parametrosArray['subtipoProv'];
				$name_prov = $parametrosArray['name_prov'];
				$comercial_nombre = $parametrosArray['comercial_nombre'];
				$curp = $parametrosArray['curp'];
				$paistoken = $parametrosArray['paistoken'];
				$sitio_web = $parametrosArray['sitio_web'];
				$tknRegimenFiscal = $parametrosArray['tknRegimenFiscal'];
				$decideinfocontacto = $parametrosArray['decideinfocontacto'];
				$arrayContactoPersonal = $parametrosArray['arrayContactoPersonalProvv_reg'];
				$tiene_docs_fiscales = $parametrosArray['tiene_docs_fiscales'];
				$valnoCargaDocsFiscalesRazon = $parametrosArray['valnoCargaDocsFiscalesRazon'];
				$aceptaCredito = $parametrosArray['aceptaCredito'];
				$txtMoneda = $parametrosArray['txtMoneda'];
				$txtlimiteCredito = $parametrosArray['txtlimiteCredito'];
				$txtdiaspagoCredit = $parametrosArray['txtdiaspagoCredit'];
				$comienzaPagoProv = $parametrosArray['selectComienzaPagoProv'];
				$decideformapago = $parametrosArray['decideformapago'];
				$token_forma_pago = $parametrosArray['formaPagoAltaProv'];//"token_forma_pago" => "string",
				$tipoReferenciaPago = $parametrosArray['tipoReferenciaPago'];
				$clabeIntBanc = $parametrosArray['clabeIntBanc'];
        $receptFactura = $parametrosArray['receptFactura'];
				$classRecibeArtPago = $parametrosArray['classRecibeArtPago'];
				$cod_postal = $parametrosArray['cod_postal'];
				$dipomex_cod_postal_estado = $parametrosArray['dipomex_cod_postal_estado'];
				$dipomex_cod_postal_municipio = $parametrosArray['dipomex_cod_postal_municipio'];
				$dipomex_cod_postal_cp = $parametrosArray['dipomex_cod_postal_cp'];
				$dipomex_cod_postal_colonia_vinculada = $parametrosArray['dipomex_cod_postal_colonia_vinculada'];
				$listnewdireccionNac = $parametrosArray['listnewdireccionNac'];

				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
				$patronNumCred = '/^[0-9$,.-]*$/';
				$patronRfc = '/[aA0-zZ9]/';
				$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				$queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.jerarquia_main,users.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
          JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
          AND users.usuario_token= ?", [$usuario->empresa_token, $usuario->user_token]);

				foreach ($queryEmp as $vEmp) {
					//da_te_default_timezone_set($vEmp->zona_horaria);
					$autorizado = FALSE;
          $autorizacion_fecha = NULL;
          $autorizacion_user = NULL;
          $folio_nuevo = NULL;
          $post_folio =  NULL;
          $folio_temporal = NULL;
          $folio_prov = NULL;

					$folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp 
						JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE fold.egr_proveedores = TRUE AND fold.empresa = emp.id 
						AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?", 
						[$usuario->empresa_token, $usuario->user_token]);

          if ($vEmp->jerarquia_main == 'P') {
            $post_folio_db = DB::select("SELECT post_folio FROM eegr_catalogo_proveedores WHERE id = (SELECT Max(catprov.id) FROM eegr_catalogo_proveedores AS catprov 
							JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprov.administrador = emp.id 
							AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",
							[$usuario->empresa_token, $usuario->user_token]);

            $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
            $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
            $folio_prov = $post_folio == NULL ? 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) : 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
            $autorizado = TRUE;
            $autorizacion_fecha = time();
            $autorizacion_user = $vEmp->userr;
          } else {
            $folioSistemaTemp = DB::select("SELECT temp_folio FROM eegr_catalogo_proveedores WHERE temp_folio IS NOT NULL AND administrador = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$usuario->empresa_token]);
            if (count($folioSistemaTemp) > 0) {
							$queryFolioTmpPrv = DB::select("SELECT temp_folio+1 AS temp_folio FROM eegr_catalogo_proveedores 
								WHERE id = (SELECT Max(catprov.id) FROM eegr_catalogo_proveedores AS catprov 
								JOIN main_empresas AS emp WHERE temp_folio IS NOT NULL AND catprov.administrador = emp.id 
								AND emp.empresa_token = ?)", [$usuario->empresa_token]);

							foreach ($queryFolioTmpPrv as $vTemp) {
								$folio_temporal = $vTemp->temp_folio;
							}
            } else {
              $folio_temporal = 1;
            }
            $folio_prov = 'PROV-TEMP-' . $JwtAuth->generarFolio($folio_temporal);
            $autorizado = FALSE;
          }

					$fechaAlta = time();

					if ($prov_rfc != "") {
						if (isset($prov_rfc) && isset($prov_rfc) && preg_match($patronRfc, $prov_rfc)) {
							$rfc_prov = $JwtAuth->encriptar($prov_rfc);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'error al registrar rfc del proveedor'
							);
						}
					} else {
						$rfc_prov = NULL;
					}
	
					if ($id_tax != "") {
						if (isset($id_tax) && preg_match($patronRfc, $id_tax)) {
							$idtax = $JwtAuth->encriptar($id_tax);
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'error al registrar idtax del proveedor'
							);
						}
					} else {
						$idtax = NULL;
					}	

					$sql_proveedor = NULL;
					$comercial_nombre_txt = NULL;
					$curp_txt = NULL;
					$pais_txt = NULL;
					$sitio_web_txt = NULL;
	
					if (isset($radioProv) && isset($radioProv) && preg_match($patron, $radioProv)) {
						if ($radioProv == "extranjero") {
							if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
								if ($subtipoProv == "provMoral") {
									//return response()->json(['message' => $parametrosArray['pais'],'codigo' => 200,'status' => 'error']);
									if (isset($name_prov) && !empty($name_prov) && preg_match($patron,$name_prov) && isset($paistoken) && !empty($paistoken)) {
										$sql_proveedor = $JwtAuth->encriptar($name_prov);
										$selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
										$pais_txt = $selectPais[0]->id;
										if (!empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
											if (preg_match($patron, $comercial_nombre)) {
												$comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
											} else {
												$dataMensaje = array(
													'status' => 'error',
													'code' => 200,
													'codeProvGenError' => 'nomcom',
													'message' => 'Error en nombre comercial de su proveedor'
												);
											}
											if (preg_match($patronUrl, $sitio_web)) {
												$sitio_web_txt = $JwtAuth->encriptar($sitio_web);
											} else {
												$dataMensaje = array(
													'status' => 'error',
													'code' => 200,
													'codeProvGenError' => 'websitio',
													'message' => 'Error en sitio web de su proveedor'
												);
											}
										} else {
											$nombre_comercial_txt = NULL;
											$sitioweb = NULL;
										}
									} else {
										if (!isset($name_prov) || empty($name_prov) || !preg_match($patron,$name_prov)) {
											$dataMensaje = array(
												"status" => "error",
												"code" => 200,
												"codeProvGenError" => "nomemp",
												"message" => "Error en nombre de empresa de su proveedor"
											);
										}
										if (!isset($paistoken) || empty($paistoken)) {
											$dataMensaje = array(
												"status" => "error",
												"code" => 200,
												"codeProvGenError" => "pais",
												"message" => "Error en pais de su proveedor"
											);
										}
									}
								}
								//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);		
								if ($subtipoProv == 'provFisica') {
									if (isset($name_prov) && !empty($name_prov) && preg_match($patron,$name_prov) && isset($paistoken) && !empty($paistoken)) {
										$sql_proveedor = $JwtAuth->encriptar($name_prov);
	
										if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
											if (preg_match($patron, $comercial_nombre)) {
												$comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
											} else {
												$dataMensaje = array(
													'status' => 'error',
													'code' => 200,
													'codeProvGenError' => 'nomcom',
													'message' => 'Error en nombre comercial de su proveedor'
												);
											}
	
											if (preg_match($patronUrl, $sitio_web)) {
												$sitio_web_txt = $JwtAuth->encriptar($sitio_web);
											} else {
												$dataMensaje = array(
													'status' => 'error',
													'code' => 200,
													'codeProvGenError' => 'websitio',
													'message' => 'Error en sitio web de su proveedor'
												);
											}
										} else {
											$comercial_nombre_txt = NULL;
											$sitio_web_txt = NULL;
										}
	
										$selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
										$pais_txt = $selectPais[0]->id;
									} else {
										if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(),$name_prov)) {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nombrePF',
												'message' => 'Error en nombre de su proveedor'
											);
										}
										if (!isset($paistoken) || empty($paistoken)) {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'paisPF',
												'message' => 'Error en pais de su proveedor'
											);
										}
									}
								}
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'codeProvGenError' => 'clbint',
									'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
								);
							}
						}
	
						if ($radioProv == 'nacional') {
							if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
								if ($subtipoProv == 'provMoral') {
									if (isset($name_prov) && !empty($name_prov) && preg_match($patron,$name_prov)) {
										$sql_proveedor = $JwtAuth->encriptar($name_prov);
										if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
											if (preg_match($patron, $comercial_nombre)) {
												$nombre_comercial = $JwtAuth->encriptar($comercial_nombre);
											} else {
												$dataMensaje = array(
													'status' => 'error',
													'code' => 200,
													'codeProvGenError' => 'nomcom',
													'message' => 'Error en nombre comercial de su proveedor'
												);
											}
											if (preg_match($patronUrl, $sitio_web)) {
												$sitioweb = $JwtAuth->encriptar($sitio_web);
											} else {
												$dataMensaje = array(
													'status' => 'error',
													'code' => 200,
													'codeProvGenError' => 'websitio',
													'message' => 'Error en sitio web de su proveedor'
												);
											}
										} else {
											$comercial_nombre_txt = NULL;
											$sitio_web_txt = NULL;
										}
	
										$pais_txt = '118';
									} else {
										if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(),$name_prov)) {
											$dataMensaje = array(
												"status" => "error",
												"code" => 200,
												"codeProvGenError" => "nomemp",
												"message" => "Error en nombre de su proveedor"
											);
										}
									}
								}
	
								if ($subtipoProv == 'provFisica') {
									if (isset($name_prov) && !empty($name_prov) && preg_match($patron,$name_prov)) {
										$sql_proveedor = $JwtAuth->encriptar($name_prov);
	
										if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($curp) && !empty($curp) && isset($sitio_web) && !empty($sitio_web)) {
											if (preg_match($patron, $comercial_nombre)) {
												$comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
											} else {
												$dataMensaje = array(
													'status' => 'error',
													'code' => 200,
													'codeProvGenError' => 'nomcom',
													'message' => 'Error en nombre comercial de su proveedor'
												);
											}
	
											if (preg_match($patronRfc, $curp)) {
												$curp_txt = $JwtAuth->encriptar($curp);
											} else {
												$dataMensaje = array(
													'status' => 'error',
													'code' => 200,
													'codeProvGenError' => 'clbint',
													'message' => 'Error en curp de su proveedor'
												);
											}
	
											if (preg_match($patronUrl, $sitio_web)) {
												$sitio_web_txt = $JwtAuth->encriptar($sitio_web);
											} else {
												$dataMensaje = array(
													'status' => 'error',
													'code' => 200,
													'codeProvGenError' => 'websitio',
													'message' => 'Error en sitio web de su proveedor'
												);
											}
										} else {
											$comercial_nombre_txt = NULL;
											$curp_txt = NULL;
											$sitio_web_txt = NULL;
										}
	
										$pais_txt = '118';
									} else {
										if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(),$name_prov)) {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nombrePF',
												'message' => 'Error en nombre de su proveedor'
											);
										}
									}
								}
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'codeProvGenError' => 'clbint',
									'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
								);
							}
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'codeProvGenError' => 'clbint',
							'message' => 'Seleccione tipo de proveedor (nacional o extranjero)'
						);
					}

					$sql_regimen_fiscal = DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal",$tknRegimenFiscal)->value("id");

					$contadorContacto = 0;
					if ($decideinfocontacto) {
						for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
							if (
								preg_match($patron, $arrayContactoPersonal[$i]['paterno']) && preg_match($patron, $arrayContactoPersonal[$i]['materno']) &&
								preg_match($patron, $arrayContactoPersonal[$i]['nombre']) && preg_match($patron, $arrayContactoPersonal[$i]['area']) &&
								preg_match($patron, $arrayContactoPersonal[$i]['cargo'])
							) {
	
								if (count($arrayContactoPersonal[$i]['emails']) == 0 && count($arrayContactoPersonal[$i]['telefonos']) == 0) {
									$contadorContacto++;
								} else {
									$contadorMails = 0;
									$personalEmails = $arrayContactoPersonal[$i]['emails'];
									for ($m = 0; $m < count($personalEmails); $m++) {
										if (preg_match($patronMail, $personalEmails[$m])) {
											$contadorMails++;
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'positionErrorCode' => $m,
												'message' => 'Error en correo electrónico de personal de contacto'
											);
											break;
										}
									}
	
									$contadorTelefonos = 0;
									$personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
									for ($t = 0; $t < count($personalTelefonos); $t++) {
										if (preg_match($patron, $personalTelefonos[$t]['etiqueta']) &&
											preg_match($patronNum, $personalTelefonos[$t]['telefono_complete']) &&
											preg_match($patronCpostal, $personalTelefonos[$t]['extension'])
										) {
											$contadorTelefonos++;
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'positionErrorCode' => $m,
												'message' => 'Error en teléfono de personal de contacto'
											);
											break;
										}
									}
	
									if ($contadorMails == count($personalEmails) || $contadorTelefonos == count($personalTelefonos)) {
										$contadorContacto++;
									}
								}
							} else {
								if (!preg_match($patron, $arrayContactoPersonal[$i]['paterno'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCode' => $i,
										'message' => 'Error en apellido paterno de personal de contacto'
									);
								}
								if (!preg_match($patron, $arrayContactoPersonal[$i]['materno'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCode' => $i,
										'message' => 'Error en apellido materno de personal de contacto'
									);
								}
								if (!preg_match($patron, $arrayContactoPersonal[$i]['nombre'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCode' => $i,
										'message' => 'Error en nombre de personal de contacto'
									);
								}
								if (!preg_match($patron, $arrayContactoPersonal[$i]['area'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCode' => $i,
										'message' => 'Error en area de trabajo de personal de contacto'
									);
								}
								if (!preg_match($patron, $arrayContactoPersonal[$i]['cargo'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCode' => $i,
										'message' => 'Error en cargo de trabajo de personal de contacto'
									);
								}
							}
						}
					}

					$aceptCredProv = false;
					$selectMoneda = NULL;
					$selectlimiteCredito = NULL;
					$selectdiaspagoCredit = NULL;
					$selectComienzaPagoProv = NULL;

					if ($aceptaCredito) {
						if (!isset($txtMoneda)) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en seleccion de moneda'
							);
						}
						if (!preg_match($patronNumCred,$txtlimiteCredito)) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en limite de credito'
							);
						}

						if (!preg_match($patronNum,$txtdiaspagoCredit)) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en seleccion de dias de pago'
							);
						}
						if (!preg_match($patron,$comienzaPagoProv)) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Error en seleccion de comienzo de pago'
							);
						}
					} 
					
					$forma_pago_ident = $decideformapago ? DB::table("teci_forma_pago")->where("token_formapago",$token_forma_pago)->value("id") : NULL;

					$proveedorExiste = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
    			->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
    			->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
    			->where([
        		'emp.empresa_token' => $usuario->empresa_token,
        		'eegr_catalogo_proveedores.status' => true
    			])
    			->where(function ($query) use ($rfc_prov, $idtax, $sql_proveedor) {
        		if ($rfc_prov) {
          	  $query->orWhere('prov.rfc', $rfc_prov);
        		}
        		if (!empty($idtax)) {
          	  $query->orWhere('prov.tax_id', $idtax);
        		}
        		if (!empty($sql_proveedor)) {
          	  $query->orWhereRaw('LOWER(prov.nombre_extendido) = ?', [strtolower($sql_proveedor)]);
        		}
					})->exists();

					if (!$proveedorExiste) {
						$tkn_people_prov = $JwtAuth->encriptarToken($fechaAlta,$name_prov,$comercial_nombre_txt,$sitio_web_txt,$pais_txt,$rfc_prov);

						$insertProv = DB::table("sos_personas")
						->insert(array(
							"token_personas" => $tkn_people_prov,
							"nombre_extendido" => $sql_proveedor,
							"nombre_com" => $comercial_nombre_txt,
							"sitio_web" => $sitio_web_txt,
							"nacionalidad" => $pais_txt,
							"rfc_generico" => $rfc_generico,
							"rfc" => $rfc_prov,
							"tax_id" => $idtax,
							"curp" => $curp_txt,
						));
						if ($insertProv) {
							$selecProvNames = DB::table("sos_personas")->where("token_personas",$tkn_people_prov)->value("id");
							$tokenProv = $JwtAuth->encriptarToken($selecProvNames.substr($tkn_people_prov,0,20).$autorizado.$autorizacion_fecha.$autorizacion_user.$folio_nuevo.$post_folio.$folio_temporal.$folio_prov.$vEmp->id);
							$creaCatProv = new ProveedoresModelo();
							$creaCatProv->token_cat_proveedores	= $tokenProv;
							$creaCatProv->folio	= $folio_nuevo;
							$creaCatProv->post_folio	= $post_folio;
							$creaCatProv->temp_folio	= $folio_temporal;
							$creaCatProv->authorized	= $autorizado;
							$creaCatProv->authorized_fecha	= $autorizacion_fecha;
							$creaCatProv->authorized_by	= $autorizacion_user;
							$creaCatProv->fechaAlta = $fechaAlta;
							$creaCatProv->proveedor = $selecProvNames;
							$creaCatProv->subClase = $subtipoProv == "provMoral" ? "PM" : "PF";
							$creaCatProv->regimen_fiscal = $sql_regimen_fiscal;
							$creaCatProv->lista_precios = NULL;
							$creaCatProv->tiene_docs_fiscales = $tiene_docs_fiscales ? TRUE : FALSE;
							$creaCatProv->no_cuenta_fiscales = $tiene_docs_fiscales ? $valnoCargaDocsFiscalesRazon : NULL;
							$creaCatProv->forma_pago = $forma_pago_ident;
							$creaCatProv->tipo_referencia_pago = $decideformapago ? $tipoReferenciaPago : NULL;
							$creaCatProv->clabe_interbancaria = $decideformapago ? $clabeIntBanc : NULL;
							$creaCatProv->receptFactura = $receptFactura ? TRUE : FALSE;
							$creaCatProv->classRecibeArtPago = $classRecibeArtPago ? TRUE : FALSE;
							$creaCatProv->utilizado = FALSE;
							$creaCatProv->status = TRUE;
							$creaCatProv->administrador = $vEmp->id;
							$savednewProv = $creaCatProv->save();
							if ($savednewProv) {
								$selectProvCat = $creaCatProv->id;

								if ($radioProv == 'extranjero') {
									$tipo_direccion = 'dirección fiscal';
									$cpostalDir = $JwtAuth->encriptar($cod_postal);
									$clasificacionDir = $JwtAuth->encriptar("matriz");
									$tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $cpostalDir, $clasificacionDir);
	
									$fisinsertDir = DB::table("teci_direcciones")
										->insert(array(
											"token_direccion" => $tokenCDir,
											"tipo_direccion" => $tipo_direccion,
											"clase" => $clasificacionDir,
											"cod_postalext" => $cpostalDir,
											"pais" => $pais_txt,
											"proveedor" => $selectProvCat,
											"status" => TRUE,
											"administrador" => $vEmp[0]->id,
										));
								} else {
									$tipo_direccion = "dirección fiscal";
									$clasificacionDir = $JwtAuth->encriptar("matriz");
									$tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $dipomex_cod_postal_estado, $dipomex_cod_postal_municipio, $dipomex_cod_postal_cp, $dipomex_cod_postal_colonia_vinculada, $clasificacionDir);
									$listnewdireccionNac = $parametrosArray["listnewdireccionNac"];
									$fisinsertDir = DB::table("teci_direcciones")
										->insert(array(
											"token_direccion" => $tokenCDir,
											"tipo_direccion" => $tipo_direccion,
											"clase" => $clasificacionDir,
											"pais" => 118,
											"pais_code" => "MEX",
											"estado_edit" => $JwtAuth->encriptar($dipomex_cod_postal_estado),
											"municipio_edit" => $JwtAuth->encriptar($dipomex_cod_postal_municipio),
											"c_postal_edit" => $dipomex_cod_postal_cp,
											"colonia_edit" => $JwtAuth->encriptar($dipomex_cod_postal_colonia_vinculada),
											"adicional" => "api",
											"proveedor" => $selectProvCat,
											"status" => TRUE,
											"administrador" => $vEmp->id,
										));
								}

								if ($decideinfocontacto == true) {
									for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
										$contArea = $arrayContactoPersonal[$i]['area']; //area
										$contCargo = $arrayContactoPersonal[$i]['cargo'];
										$contApePaterno = $arrayContactoPersonal[$i]['paterno'];
										$contApeMaterno = $arrayContactoPersonal[$i]['materno'];
										$contNombre = $arrayContactoPersonal[$i]['nombre'];
	
										$tokenPersonasPersonal = $JwtAuth->encriptarToken($contArea.'/'.$contCargo.'/'.$contApePaterno.'/'.$contApeMaterno.'/'.$vEmp->id.'/'. $contArea);
										$insertapersonalpersonas = DB::table('sos_personas')
											->insert(array(
												"token_personas" => $tokenPersonasPersonal, 
												"paterno" => $JwtAuth->encriptar($contApePaterno), 
												"materno" => $JwtAuth->encriptar($contApeMaterno), 
												"nombre" => $JwtAuth->encriptar($contNombre) 
											));
	
										$tokenPersonal = $JwtAuth->encriptarToken($contApePaterno . '/' . $contNombre . '/' . $contApeMaterno . '/' . $vEmp->id . '/' . $contArea);
										$insertapersonal = DB::table('in_egr_contacto_cliente_proveedor')->insert(
											array(
												"token_contacto" => $tokenPersonal,
												"fecha_alta_contacto" => time(),
												"folio_contacto" => $i,
												"area_contacto" => $JwtAuth->encriptar($contArea),
												"cargo_contacto" => $JwtAuth->encriptar($contCargo),
												"nombre" => DB::table("sos_personas")->where("token_personas",$tokenPersonasPersonal)->value("id"),
												"cat_proveedores" => $selectProvCat,
												"status" => TRUE,
												"fecha_delete" => NULL
											)
										);
										$selectContacto = DB::table("in_egr_contacto_cliente_proveedor")->where("token_contacto",$tokenPersonal)->value("id");
										$personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
										if (count($personalTelefonos) != 0) {
											for ($t = 0; $t < count($personalTelefonos); $t++) {
												$contTelefono = $JwtAuth->encriptar($personalTelefonos[$t]['telefono_complete']);
												$contExtension = $personalTelefonos[$t]['extension'] != '' ? $JwtAuth->encriptar($personalTelefonos[$t]['extension']) : NULL;
												$tokentel = $JwtAuth->encriptarToken($tokenPersonal . $contTelefono);
												$principal = $t == 0 ? TRUE : FALSE;
												//return response()->json(['message' => $personalTelefonos[$t]["etiqueta"],'code' => 200,'status' => 'error']);
												$insertatelefonos_personal = DB::table('sos_personas_telefonos')
													->insert(array(
														"token_telefono" => $tokentel,
														"contacto_cliente_prov" => $selectContacto,
														//"icono" => $personalTelefonos[$t]["icon"],	
														"etiqueta" => $personalTelefonos[$t]["etiqueta"],
														"cod_pais" => 52,
														"telefono" => $contTelefono,
														"principal" => $principal,
														"extension" => $contExtension,
														"status_telefono" => TRUE,
														"fecha_delete_tel" => NULL,
	
													));
											}
										}
	
										$personalEmails = $arrayContactoPersonal[$i]['emails'];
										if (count($personalEmails) != 0) {
											for ($m = 0; $m < count($personalEmails); $m++) {
												$contEmail = $JwtAuth->encriptar($personalEmails[$m]);
												$tokenEmail = $JwtAuth->encriptarToken($personalEmails[$m], $contEmail, $selectContacto);
												$insertacorreos_personal = DB::table('sos_personas_correos')
													->insert(array(
														"token_correo" => $tokenEmail,
														"contacto_cliente_prov" => $selectContacto,
														"correo" => $contEmail,
														"status_correo" => TRUE,
														"fecha_delete_correo" => NULL,
													));
											}
										}
									}
								}

								$tokenCreditos = $JwtAuth->encriptarToken($selectProvCat . $fechaAlta . $aceptaCredito);
								$insertaCreditoProv = DB::table('in_egr_creditos')
									->insert(array(
										"token_creditos" => $tokenCreditos,
										"proveedor" => $selectProvCat,
										"aceptacredito" => $aceptaCredito ? TRUE : FALSE,
										"moneda_code" => $aceptaCredito ? $txtMoneda : NULL,
										"limite" => $aceptaCredito ? $txtlimiteCredito : NULL,
										"dias" => $aceptaCredito ? $txtdiaspagoCredit : NULL,
										"comienza" => $aceptaCredito ? $comienzaPagoProv : NULL,
									));

									$filepath = $vEmp->root_tkn . "/0002-cpp/catalogos/proveedores/$fechaAlta/";
									if (!file_exists(storage_path("/root/" . $filepath))) {
										Storage::disk("root")->makeDirectory($filepath, 0777, true, true);
									}
									if (file_exists($request->file('docSituacionFiscal'))) {
										$namesitfiscal = $fechaAlta . '-' . $request->file('docSituacionFiscal')->getClientOriginalName();
										$typesitfiscal = $JwtAuth->getExtensionDoc($request->file('docSituacionFiscal')->getClientMimeType());
										$tkn_doc_sitfiscal = $JwtAuth->encriptarToken($tokenProv, $usuario->user_token, $usuario->empresa_token, $namesitfiscal);
										$JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_sitfiscal, "fcsf", $namesitfiscal, $typesitfiscal);
										Storage::putFileAs("/public/root/" . $filepath, $request->file('docSituacionFiscal'), $namesitfiscal);
									}
	
									if (file_exists($request->file('docCumplimientoObFiscales'))) {
										$namecumplimiento = $fechaAlta . '-' . $request->file('docCumplimientoObFiscales')->getClientOriginalName();
										$typecumplimiento = $JwtAuth->getExtensionDoc($request->file('docCumplimientoObFiscales')->getClientMimeType());
										$tkn_doc_cumplimiento = $JwtAuth->encriptarToken($tokenProv, $usuario->user_token, $usuario->empresa_token, $namecumplimiento);
										$JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_cumplimiento, "cuof", $namecumplimiento, $typecumplimiento);
										Storage::putFileAs("/public/root/" . $filepath, $request->file('docCumplimientoObFiscales'), $namecumplimiento);
									}
	
									if (file_exists($request->file('docContrato'))) {
										$namecontrato = $fechaAlta . '-' . $request->file('docContrato')->getClientOriginalName();
										$typecontrato = $JwtAuth->getExtensionDoc($request->file('docContrato')->getClientMimeType());
										$tkn_doc_contrato = $JwtAuth->encriptarToken($tokenProv, $usuario->user_token, $usuario->empresa_token, $namecontrato);
										$JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_contrato, "fcnt", $namecontrato, $typecontrato);
										Storage::putFileAs("/public/root/" . $filepath, $request->file('docContrato'), $namecontrato);
									}
	
									if (isset($_FILES['files_anexos']) && !empty($_FILES['files_anexos'])) {
										$anexo_archivos = $_FILES["files_anexos"];
										$anexo_archivos_strings = json_decode(json_encode($_FILES["files_anexos"]["name"]));
										if (count($anexo_archivos_strings) > 0) {
											for ($i = 0; $i < count($anexo_archivos_strings); $i++) {
												$docAnexo_temporal = $anexo_archivos["tmp_name"][$i];
												$docAnexo_name = "anexos/" . $anexo_archivos["name"][$i];
												$docAnexo_type = $JwtAuth->getExtensionDoc($anexo_archivos["type"][$i]);
												$docAnexo_tknn = $JwtAuth->encriptarToken($tokenProv, $usuario->user_token, $usuario->empresa_token, $docAnexo_name);
												$JwtAuth->registraDocsProveedor($tokenProv, $docAnexo_tknn, "anex", $docAnexo_name, $docAnexo_type);
												Storage::putFileAs("/public/root/" . $filepath, $docAnexo_temporal, $docAnexo_name);
											}
										}
									}
	
									if (file_exists($request->file('docEstadoCuenta'))) {
										$nameestadocuenta = $fechaAlta . '-' . $request->file('docEstadoCuenta')->getClientOriginalName();
										$typeestadocuenta = $JwtAuth->getExtensionDoc($request->file('docEstadoCuenta')->getClientMimeType());
										$tkn_doc_estadocuenta = $JwtAuth->encriptarToken($tokenProv, $usuario->user_token, $usuario->empresa_token, $nameestadocuenta);
										$JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_estadocuenta, "ecue", $nameestadocuenta, $typeestadocuenta);
										Storage::putFileAs("/public/root/" . $filepath, $request->file('docEstadoCuenta'), $nameestadocuenta);
									}
	
									$JwtAuth->insertBitacoraActividad(
										"egresos",
										"catalogos",
										"proveedores",
										$folio_prov,
										"registro en el catalogo de proveedores",
										$usuario->empresa_token,
										$usuario->user_token
									);
									//return response()->json(['message' => 'gantt_diagram','code' => 200,'status' => 'error']);
									//return response()->json(["message" => "prueba33","code" => 200,"status" => "error"]);
									
									if ($vEmp->jerarquia_main == 'P') {
										if (count($folioSistema) == 0) {
											$insertSistema = DB::table("sos_last_folders")
												->insert(
													array(
														"egr_proveedores" => TRUE,
														"folder" => 1,
														"post_folder" => $post_folio,
														"empresa" => $selectEmp[0]->id,
													)
												);
										} else {
											$regFolder = DB::table("sos_last_folders AS fold")
											->join("main_empresas AS emp", "fold.empresa", "=", "emp.id")
												->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
												->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
												->where([
													"fold.egr_proveedores" => TRUE,
													"emp.empresa_token" => $usuario->empresa_token,
													"users.usuario_token" => $usuario->user_token,
												])
												->limit(1)->update(
													array(
														"fold.folder" => $folio_nuevo,
														"fold.post_folder" => $post_folio,
													)
												);
										}
									}

									$dataMensaje = array(
										'status' => 'success',
										'code' => 200,
										'message' => 'Proveedor registrado satisfactoriamente con el folio ' . $folio_prov
									);
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
								);
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'ya existe un proveedor con esta información'
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
		return response()->json($dataMensaje, $dataMensaje['code']);
	}

	public function proveedorSolicitudRegistro(Request $request){
		$JwtAuth = new \JwtAuth();
		$imagenAltaPdfFiscal = $request->file('imagenAltaPdfFiscal');
		$imagenAltaPdfCumplimientoObFiscales = $request->file('imagenAltaPdfCumplimientoObFiscales');
		$imagenAltaPdfEstCuenta = $request->file('imagenAltaPdfEstCuenta');
		$jsonServ = $request->input('proveedor');
		$parametros = json_decode($jsonServ);
		$parametrosArray = json_decode($jsonServ, true);

		if (!empty($parametros) && !empty($parametrosArray)) {
			$validate = \Validator::make($parametrosArray, [
				'empresa_token' => 'string',
				'rfc_generico' => 'required|string',
				'prov_rfc' => 'string',
				'id_tax' => 'string',
				'radioProv' => 'required|string',
				'subtipoProv' => 'required|string',
				'txtPaternoPF' => 'string',
				'txtMaternoPF' => 'string',
				'txtnombrePF' => 'string',
				'txtcurpPF' => 'string',
				'paisPF' => 'string',
				'txtNomComercialPF' => 'string',
				'txtSitioWebPF' => 'string',
				'redesSocialesPF' => 'array',
				'txtempresa' => 'string',
				'pais' => 'string',
				'txtNomComercialPM' => 'string',
				'txtSitioWebPM' => 'string',
				'redesSocialesPM' => 'array',
				'decideinfocontacto' => 'required|string',
				'arrayContactoPersonalProvv_reg' => 'array',
				'tiene_docs_fiscales' => 'required|string',
				'valnoCargaDocsFiscalesRazon' => 'string',
				'aceptaCredito' => 'required|string',
				'txtMoneda' => 'string',
				'txtlimiteCredito' => 'string',
				'txtdiaspagoCredit' => 'string',
				'selectComienzaPagoProv' => 'string',
				'formaPagoAltaProv' => 'required|string',
				'tipoReferenciaPago' => 'string',
				'clabeIntBanc' => 'string',
				'arrayubicacionExtranjeraProvv_reg' => 'array',
				'arrayubicacionNacionalProvv_reg' => 'array',
			]);

			if ($validate->fails()) {
				$dataMensaje = array(
					'status' => 'error',
					'code' => 200,
					'message' => 'Proveedor invalido' . $validate->errors(),
					'errors' => $validate->errors()
				);
			} else {
				$patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
				$patronNum = '/^[1-9][0-9]*$/';
				$patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
				$patronNumCred = '/^[0-9$,.-]*$/';
				$patronRfc = '/[aA0-zZ9]/';
				$patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
				$patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

				if ($parametrosArray['empresa_token'] == "") {
					$empresa = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
				} else {
					$empresa = $parametrosArray['empresa_token'];
				}

				$selectEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn FROM empresas AS emp WHERE emp.empresa_token = ?", [$empresa]);

				//da_te_default_timezone_set($selectEmp[0]->zona_horaria);

				$folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder
                    FROM sos_last_folders AS fold JOIN main_empresas AS emp
                    WHERE fold.egr_proveedores = TRUE AND fold.empresa = emp.id 
                    AND emp.empresa_token = ?", [$empresa]);

				if (count($folioSistema) == 1) {
					if ($folioSistema[0]->folio == 1000000000) {
						$post_folio_db = DB::select("SELECT post_folio FROM catalogo_proveedores 
                            WHERE id = (SELECT Max(catprov.id) FROM catalogo_proveedores AS catprov 
                            JOIN main_empresas AS emp WHERE catprov.administrador = emp.id 
                            AND emp.empresa_token = ?)", [$empresa]);

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

				if ($post_folio == NULL) {
					$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_nuevo);
				} else {
					$folio_prov = 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
				}

				$fechaAlta = time();

				$arrayContactoPersonal = $parametrosArray['arrayContactoPersonalProvv_reg'];
				$contadorContacto = 0;

				if ($parametrosArray['decideinfocontacto'] == 'true') {
					for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
						if (
							preg_match($patron, $arrayContactoPersonal[$i]['paterno']) && preg_match($patron, $arrayContactoPersonal[$i]['materno']) &&
							preg_match($patron, $arrayContactoPersonal[$i]['nombre']) && preg_match($patron, $arrayContactoPersonal[$i]['area']) &&
							preg_match($patron, $arrayContactoPersonal[$i]['cargo'])
						) {

							if (count($arrayContactoPersonal[$i]['emails']) == 0 && count($arrayContactoPersonal[$i]['telefonos']) == 0) {
								$contadorContacto++;
							} else {
								$contadorMails = 0;
								$personalEmails = $arrayContactoPersonal[$i]['emails'];
								for ($m = 0; $m < count($personalEmails); $m++) {
									if (preg_match($patronMail, $personalEmails[$m])) {
										$contadorMails++;
									} else {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'positionErrorCode' => $m,
											'message' => 'Error en correo electrónico de personal de contacto'
										);
										break;
									}
								}

								$contadorTelefonos = 0;
								$personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
								for ($t = 0; $t < count($personalTelefonos); $t++) {
									if (
										preg_match($patron, $personalTelefonos[$t]['icon']) &&
										preg_match($patron, $personalTelefonos[$t]['etiqueta']) &&
										preg_match($patronNum, $personalTelefonos[$t]['telefono']) &&
										preg_match($patronCpostal, $personalTelefonos[$t]['extension'])
									) {
										$contadorTelefonos++;
									} else {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'positionErrorCode' => $m,
											'message' => 'Error en teléfono de personal de contacto'
										);
										break;
									}
								}

								if ($contadorMails == count($personalEmails) || $contadorTelefonos == count($personalTelefonos)) {
									$contadorContacto++;
								}
							}
						} else {
							if (!preg_match($patron, $arrayContactoPersonal[$i]['paterno'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCode' => $i,
									'message' => 'Error en apellido paterno de personal de contacto'
								);
							}
							if (!preg_match($patron, $arrayContactoPersonal[$i]['materno'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCode' => $i,
									'message' => 'Error en apellido materno de personal de contacto'
								);
							}
							if (!preg_match($patron, $arrayContactoPersonal[$i]['nombre'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCode' => $i,
									'message' => 'Error en nombre de personal de contacto'
								);
							}
							if (!preg_match($patron, $arrayContactoPersonal[$i]['area'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCode' => $i,
									'message' => 'Error en area de trabajo de personal de contacto'
								);
							}
							if (!preg_match($patron, $arrayContactoPersonal[$i]['cargo'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'positionErrorCode' => $i,
									'message' => 'Error en cargo de trabajo de personal de contacto'
								);
							}
						}
					}
				}

				$aceptCredProv = false;
				$selectMoneda = NULL;
				$selectlimiteCredito = NULL;
				$selectdiaspagoCredit = NULL;
				$selectComienzaPagoProv = NULL;

				if (preg_match($patron, $parametrosArray['aceptaCredito'])) {
					if ($parametrosArray['aceptaCredito'] == 'si') {
						if (
							isset($parametrosArray['txtMoneda']) &&
							preg_match($patronNumCred, $parametrosArray['txtlimiteCredito']) &&
							preg_match($patronNum, $parametrosArray['txtdiaspagoCredit']) &&
							preg_match($patron, $parametrosArray['selectComienzaPagoProv'])
						) {
							$aceptCredProv = true;
							$dbMoneda = DB::select("SELECT id FROM teci_catalogo_monedas WHERE token_monedas = ?", [$parametrosArray['txtMoneda']]);
							$selectMoneda = $dbMoneda[0]->id;
							$selectlimiteCredito = $parametrosArray['txtlimiteCredito'];
							$selectdiaspagoCredit = $parametrosArray['txtdiaspagoCredit'];
							$selectComienzaPagoProv = $parametrosArray['selectComienzaPagoProv'];
						} else {
							if (!isset($parametrosArray['txtMoneda'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'Error en seleccion de moneda'
								);
							}
							if (!preg_match($patronNumCred, $parametrosArray['txtlimiteCredito'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'Error en limite de credito'
								);
							}
							if (!preg_match($patronNum, $parametrosArray['txtdiaspagoCredit'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'Error en seleccion de dias de pago'
								);
							}
							if (!preg_match($patron, $parametrosArray['selectComienzaPagoProv'])) {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'Error en seleccion de comienzo de pago'
								);
							}
						}
					} else {
						$aceptCredProv = false;
						$selectMoneda = NULL;
						$selectlimiteCredito = NULL;
						$selectdiaspagoCredit = NULL;
						$selectComienzaPagoProv = NULL;
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'Selecciona opcion de aceptacion de creditos'
					);
				}

				$contadorUbicaciones = 0;
				if (preg_match($patron, $parametrosArray['radioProv']) && $parametrosArray['radioProv'] == 'extranjero') {
					$ubicacionExtranjeraProvv_reg = $parametrosArray['arrayubicacionExtranjeraProvv_reg'];
					if (count($ubicacionExtranjeraProvv_reg) != 0) {
						for ($i = 0; $i < count($ubicacionExtranjeraProvv_reg); $i++) {
							if (
								preg_match($patron, $ubicacionExtranjeraProvv_reg[$i][0]) &&
								preg_match($patron, $ubicacionExtranjeraProvv_reg[$i][1]) &&
								preg_match($patronCpostal, $ubicacionExtranjeraProvv_reg[$i][2]) &&
								preg_match($patron, $ubicacionExtranjeraProvv_reg[$i][3])
							) {
								$contadorUbicaciones++;
							} else {
								if (!preg_match($patron, $ubicacionExtranjeraProvv_reg[$i][0])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeSucursalExt' => $i,
										'message' => 'Error en clasificacion de dirección extranjera'
									);
								}
								if (!preg_match($patron, $ubicacionExtranjeraProvv_reg[$i][1])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeSucursalExt' => $i,
										'message' => 'Error en alias de dirección extranjera'
									);
								}
								if (!preg_match($patronCpostal, $ubicacionExtranjeraProvv_reg[$i][2])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeSucursalExt' => $i,
										'message' => 'Error en código postal de dirección extranjera'
									);
								}
								if (!preg_match($patron, $ubicacionExtranjeraProvv_reg[$i][3])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeSucursalExt' => $i,
										'message' => 'Error en dirección completa de dirección extranjera'
									);
								}
							}
						}
					} else {
						if (count($ubicacionExtranjeraProvv_reg) == 0) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Ingrese direcciones de ubicación de su proveedor'
							);
						}
					}
				}

				if (preg_match($patron, $parametrosArray['radioProv']) && $parametrosArray['radioProv'] == 'nacional') {
					$ubicacionNacionalProvv_reg = $parametrosArray['arrayubicacionNacionalProvv_reg'];
					if (count($ubicacionNacionalProvv_reg) != 0) {
						for ($i = 0; $i < count($ubicacionNacionalProvv_reg); $i++) {
							if (
								preg_match($patron, $ubicacionNacionalProvv_reg[$i]['clase']) &&
								preg_match($patron, $ubicacionNacionalProvv_reg[$i]['alias']) &&
								preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle']) &&
								preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['num_ext']) &&
								!empty($ubicacionNacionalProvv_reg[$i]['cod_postal_nacprov'])
							) {

								if (
									$ubicacionNacionalProvv_reg[$i]['calle1'] == '-' &&
									$ubicacionNacionalProvv_reg[$i]['calle2'] == '-' &&
									$ubicacionNacionalProvv_reg[$i]['referencia'] == '-'
								) {
									$contadorUbicaciones++;
								} else {
									if (
										preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle1']) &&
										preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle2']) &&
										preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['referencia'])
									) {
										if ($ubicacionNacionalProvv_reg[$i]['num_int'] != '-') {
											if (preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['num_int'])) {
												$contadorUbicaciones++;
											} else {
												$dataMensaje = array(
													'status' => 'error',
													'code' => 200,
													'positionErrorCodeFiscalNac' => $i,
													'message' => 'Error en número interiór de dirección nacional'
												);
											}
										} else {
											$contadorUbicaciones++;
										}
									} else {
										if (!preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle1'])) {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'positionErrorCodeFiscalNac' => $i,
												'message' => 'Error en primera calle de referencia de dirección nacional'
											);
										}
										if (!preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle2'])) {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'positionErrorCodeFiscalNac' => $i,
												'message' => 'Error en segunda calle de referencia de dirección nacional'
											);
										}
										if (!preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['referencia'])) {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'positionErrorCodeFiscalNac' => $i,
												'message' => 'Error en lugar de referencia de dirección nacional'
											);
										}
									}
								}
							} else {
								if (!preg_match($patron, $ubicacionNacionalProvv_reg[$i]['clase'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeFiscalNac' => $i,
										'message' => 'Error en clasificacion de dirección nacional'
									);
								}
								if (!preg_match($patron, $ubicacionNacionalProvv_reg[$i]['alias'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeFiscalNac' => $i,
										'message' => 'Error en alias de dirección nacional'
									);
								}
								if (!preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['calle'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeFiscalNac' => $i,
										'message' => 'Error en calle de dirección nacional'
									);
								}
								if (!preg_match($patronCpostal, $ubicacionNacionalProvv_reg[$i]['num_ext'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeFiscalNac' => $i,
										'message' => 'Error en número exteriór de dirección nacional'
									);
								}
								if (!preg_match($patronNum, $ubicacionNacionalProvv_reg[$i]['cod_postal_nacprov'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'positionErrorCodeFiscalNac' => $i,
										'message' => 'Error en código postal de dirección nacional'
									);
								}
							}
						}
					} else {
						if (count($ubicacionNacionalProvv_reg) == 0) {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Ingrese direcciones de ubicación de su proveedor'
							);
						}
					}
				}

				$formaPagoClabeInterbank = '';
				$tipoReferenciaPago = '';
				if ($parametrosArray['formaPagoAltaProv'] == 'RkxGMTRidG44ZWJJYVh0dUlDK1o4Zz09OjoxMjM0NTY3ODEyMzQ1Njc4') {
					if (isset($parametrosArray['tipoReferenciaPago']) && !empty($parametrosArray['tipoReferenciaPago'])) {

						if ($parametrosArray['tipoReferenciaPago'] == 'clabeInterbancaria') {
							$tipoReferenciaPago = 'ci';
						} else if ($parametrosArray['tipoReferenciaPago'] == 'convenio') {
							$tipoReferenciaPago = 'co';
						} else if ($parametrosArray['tipoReferenciaPago'] == 'lineaCaptura') {
							$tipoReferenciaPago = 'lc';
						}

						if ($parametrosArray['tipoReferenciaPago'] == 'clabeInterbancaria') {
							if (preg_match($patronNumCred, $parametrosArray['clabeIntBanc'])) {
								$formaPagoClabeInterbank = $parametrosArray['clabeIntBanc'];
							} else {
								if (!preg_match($patronNumCred, $parametrosArray['clabeIntBanc'])) {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'codeProvGenError' => 'clbint',
										'message' => 'Ingrese la clabe interbancaria de su proveedor'
									);
								}
							}
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'Selecciona opcion de aceptacion de creditos'
						);
					}
				} else {
					$formaPagoClabeInterbank = '';
					$tipoReferenciaPago = '';
				}

				$selectFpago = DB::select("SELECT id FROM forma_pago WHERE token_formapago	= ?", [$parametrosArray['formaPagoAltaProv']]);

				$rfc_generico = $parametrosArray['rfc_generico'];
				if ($parametrosArray['prov_rfc'] != '') {
					if (
						isset($parametrosArray['prov_rfc']) && isset($parametrosArray['prov_rfc']) &&
						preg_match($patronRfc, $parametrosArray['prov_rfc'])
					) {
						$rfc_prov = $JwtAuth->encriptar($parametrosArray['prov_rfc']);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'error al registrar rfc del proveedor'
						);
					}
				} else {
					$rfc_prov = NULL;
				}

				if ($parametrosArray['id_tax'] != '') {
					if (isset($parametrosArray['id_tax']) && preg_match($patronRfc, $parametrosArray['id_tax'])) {
						$idtax = $JwtAuth->encriptar($parametrosArray['id_tax']);
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'error al registrar idtax del proveedor'
						);
					}
				} else {
					$idtax = NULL;
				}

				$paternoPFtxt = NULL;
				$maternoPFtxt = NULL;
				$nombrePFtxt = NULL;
				$denominacion_rs = NULL;
				$nombre_txt = '';
				$nombre_comercial = NULL;
				$sitioweb = NULL;
				$arraySocialMedia = array();
				$pais = NULL;

				$curp = NULL;
				$countarrayredesSoc = 0;

				if (preg_match($patron, $parametrosArray['radioProv'])) {
					if ($parametrosArray['radioProv'] == 'extranjero') {
						if (preg_match($patron, $parametrosArray['subtipoProv'])) {
							if ($parametrosArray['subtipoProv'] == 'provMoral') {
								//return response()->json(['message' => $parametrosArray['pais'],'codigo' => 200,'status' => 'error']);
								if (
									isset($parametrosArray['txtempresa']) && !empty($parametrosArray['txtempresa']) &&
									preg_match($patron, $parametrosArray['txtempresa']) && isset($parametrosArray['pais']) &&
									!empty($parametrosArray['pais'])
								) {
									//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
									$denominacion_rs = $JwtAuth->encriptar($parametrosArray['txtempresa']);
									$nombre_txt = $parametrosArray['txtempresa'];
									//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
									$selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$parametrosArray['pais']]);
									$pais = $selectPais[0]->id;
									//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);
									if (
										!empty($parametrosArray['txtNomComercialPM']) &&
										isset($parametrosArray['txtSitioWebPM']) &&
										!empty($parametrosArray['txtSitioWebPM'])
									) {
										if (preg_match($patron, $parametrosArray['txtNomComercialPM'])) {
											$nombre_comercial = $JwtAuth->encriptar($parametrosArray['txtNomComercialPM']);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}
										if (preg_match($patronUrl, $parametrosArray['txtSitioWebPM'])) {
											$sitioweb = $JwtAuth->encriptar($parametrosArray['txtSitioWebPM']);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$nombre_comercial = NULL;
										$sitioweb = NULL;
									}

									if (isset($parametrosArray['redesSocialesPM']) && !empty($parametrosArray['redesSocialesPM'])) {
										$arrayredesSoc = $parametrosArray['redesSocialesPM'];
										if (count($arrayredesSoc) != 0) {
											for ($i = 0; $i < count($arrayredesSoc); $i++) {
												if ($arrayredesSoc[$i] == '') {
													//$cliente->__SET('redes_sociales',$parametrosArray['redesSocialesPM']);
													$countarrayredesSoc++;
													$arraySocialMedia[] = $arrayredesSoc[$i];
												} else {
													$dataMensaje = array(
														'status' => 'error',
														'code' => 200,
														'codeProvGenError' => 'redSoc',
														'message' => 'Error en redes sociales de su proveedor'
													);
												}
											}
										} else {
											$countarrayredesSoc = 0;
										}
									}
								} else {
									if (
										!isset($parametrosArray['txtempresa']) || empty($parametrosArray['txtempresa']) ||
										!preg_match($patron, $parametrosArray['txtempresa'])
									) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'nomemp',
											'message' => 'Error en nombre de empresa de su proveedor'
										);
									}

									if (!isset($parametrosArray['txtempresa']) || empty($parametrosArray['pais'])) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'pais',
											'message' => 'Error en pais de su proveedor'
										);
									}
								}
							}
							//return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);		
							if ($parametrosArray['subtipoProv'] == 'provFisica') {
								if (
									isset($parametrosArray['txtPaternoPF']) && !empty($parametrosArray['txtPaternoPF']) &&
									preg_match($patron, $parametrosArray['txtPaternoPF']) && isset($parametrosArray['txtMaternoPF']) &&
									!empty($parametrosArray['txtMaternoPF']) && preg_match($patron, $parametrosArray['txtMaternoPF']) &&
									isset($parametrosArray['txtnombrePF']) && !empty($parametrosArray['txtnombrePF']) &&
									preg_match($patron, $parametrosArray['txtnombrePF']) && isset($parametrosArray['paisPF']) &&
									!empty($parametrosArray['paisPF'])
								) {

									$paternoPFtxt = $JwtAuth->encriptar($parametrosArray['txtPaternoPF']);
									$maternoPFtxt = $JwtAuth->encriptar($parametrosArray['txtMaternoPF']);
									$nombrePFtxt = $JwtAuth->encriptar($parametrosArray['txtnombrePF']);
									$nombre_txt = $parametrosArray['txtPaternoPF'] . ' ' . $parametrosArray['txtMaternoPF']
										. ' ' . $parametrosArray['txtnombrePF'];

									if (
										isset($parametrosArray['txtNomComercialPF']) && !empty($parametrosArray['txtNomComercialPF']) &&
										isset($parametrosArray['txtSitioWebPF']) && !empty($parametrosArray['txtSitioWebPF'])
									) {

										if (preg_match($patron, $parametrosArray['txtNomComercialPF'])) {
											$nombre_comercial = $JwtAuth->encriptar($parametrosArray['txtNomComercialPF']);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}

										if (preg_match($patronUrl, $parametrosArray['txtSitioWebPF'])) {
											$sitioweb = $JwtAuth->encriptar($parametrosArray['txtSitioWebPF']);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$nombre_comercial = NULL;
										$sitioweb = NULL;
									}

									if (isset($parametrosArray['redesSocialesPF']) && !empty($parametrosArray['redesSocialesPF'])) {
										$arrayredesSoc = $parametrosArray['redesSocialesPF'];
										if (count($arrayredesSoc) != 0) {
											for ($i = 0; $i < count($arrayredesSoc); $i++) {
												if ($arrayredesSoc[$i] == '') {
													//$cliente->__SET('redes_sociales',$parametrosArray['redesSocialesPF']);
													$countarrayredesSoc++;
													$arraySocialMedia[] = $arrayredesSoc[$i];
												} else {
													$dataMensaje = array(
														'status' => 'error',
														'code' => 200,
														'codeProvGenError' => 'redSoc',
														'message' => 'Error en redes sociales de su proveedor'
													);
												}
											}
										} else {
											$countarrayredesSoc = 0;
										}
									}

									$selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$parametrosArray['paisPF']]);
									$pais = $selectPais[0]->id;
								} else {
									if (
										!isset($parametrosArray['txtPaternoPF']) ||
										empty($parametrosArray['txtPaternoPF']) ||
										!preg_match($patron, $parametrosArray['txtPaternoPF'])
									) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'paternoPF',
											'message' => 'Error en apellido paterno de su proveedor'
										);
									}
									if (
										!isset($parametrosArray['txtMaternoPF']) ||
										empty($parametrosArray['txtMaternoPF']) ||
										!preg_match($patron, $parametrosArray['txtMaternoPF'])
									) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'maternoPF',
											'message' => 'Error en apellido materno de su proveedor'
										);
									}
									if (
										!isset($parametrosArray['txtnombrePF']) ||
										empty($parametrosArray['txtnombrePF']) ||
										!preg_match($patron, $parametrosArray['txtnombrePF'])
									) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'nombrePF',
											'message' => 'Error en nombre de su proveedor'
										);
									}
									if (
										!isset($parametrosArray['paisPF']) ||
										empty($parametrosArray['paisPF'])
									) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'paisPF',
											'message' => 'Error en pais de su proveedor'
										);
									}
								}
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'clbint',
								'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
							);
						}
					}

					if ($parametrosArray['radioProv'] == 'nacional') {
						if (preg_match($patron, $parametrosArray['subtipoProv'])) {
							if ($parametrosArray['subtipoProv'] == 'provMoral') {
								if (
									isset($parametrosArray['txtempresa']) && !empty($parametrosArray['txtempresa']) &&
									preg_match($patron, $parametrosArray['txtempresa'])
								) {

									$denominacion_rs = $JwtAuth->encriptar($parametrosArray['txtempresa']);
									$nombre_txt = $parametrosArray['txtempresa'];
									if (
										isset($parametrosArray['txtNomComercialPM']) && !empty($parametrosArray['txtNomComercialPM']) &&
										isset($parametrosArray['txtSitioWebPM']) && !empty($parametrosArray['txtSitioWebPM'])
									) {
										if (preg_match($patron, $parametrosArray['txtNomComercialPM'])) {
											$nombre_comercial = $JwtAuth->encriptar($parametrosArray['txtNomComercialPM']);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}
										if (preg_match($patronUrl, $parametrosArray['txtSitioWebPM'])) {
											$sitioweb = $JwtAuth->encriptar($parametrosArray['txtSitioWebPM']);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$nombre_comercial = NULL;
										$sitioweb = NULL;
									}

									if (isset($parametrosArray['redesSocialesPM']) && !empty($parametrosArray['redesSocialesPM'])) {
										$arrayredesSoc = $parametrosArray['redesSocialesPM'];
										if (count($arrayredesSoc) != 0) {
											for ($i = 0; $i < count($arrayredesSoc); $i++) {
												if ($arrayredesSoc[$i] == '') {
													//$cliente->__SET('redes_sociales',$parametrosArray['redesSocialesPM']);
													$countarrayredesSoc++;
													$arraySocialMedia[] = $arrayredesSoc[$i];
												} else {
													$dataMensaje = array(
														'status' => 'error',
														'code' => 200,
														'codeProvGenError' => 'redSoc',
														'message' => 'Error en redes sociales de su proveedor'
													);
												}
											}
										} else {
											$countarrayredesSoc = 0;
										}
									}
									$pais = '118';
								} else {
									if (empty($parametrosArray['txtempresa']) || !preg_match($patron, $parametrosArray['txtempresa'])) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'nomemp',
											'message' => 'Error en nombre de empresa de su proveedor'
										);
									}
								}
							}

							if ($parametrosArray['subtipoProv'] == 'provFisica') {
								if (
									isset($parametrosArray['txtPaternoPF']) && !empty($parametrosArray['txtPaternoPF']) &&
									preg_match($patron, $parametrosArray['txtPaternoPF']) &&
									isset($parametrosArray['txtMaternoPF']) && !empty($parametrosArray['txtMaternoPF']) &&
									preg_match($patron, $parametrosArray['txtMaternoPF']) &&
									isset($parametrosArray['txtnombrePF']) && !empty($parametrosArray['txtnombrePF']) &&
									preg_match($patron, $parametrosArray['txtnombrePF'])
								) {

									$paternoPFtxt = $JwtAuth->encriptar($parametrosArray['txtPaternoPF']);
									$maternoPFtxt = $JwtAuth->encriptar($parametrosArray['txtMaternoPF']);
									$nombrePFtxt = $JwtAuth->encriptar($parametrosArray['txtnombrePF']);

									$nombre_txt = $parametrosArray['txtPaternoPF'] . ' ' . $parametrosArray['txtMaternoPF']
										. ' ' . $parametrosArray['txtnombrePF'];

									if (
										isset($parametrosArray['txtNomComercialPF']) && !empty($parametrosArray['txtNomComercialPF']) &&
										isset($parametrosArray['txtcurpPF']) && !empty($parametrosArray['txtcurpPF']) &&
										isset($parametrosArray['txtSitioWebPF']) && !empty($parametrosArray['txtSitioWebPF'])
									) {

										if (preg_match($patron, $parametrosArray['txtNomComercialPF'])) {
											$nombre_comercial = $JwtAuth->encriptar($parametrosArray['txtNomComercialPF']);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'nomcom',
												'message' => 'Error en nombre comercial de su proveedor'
											);
										}
										if (preg_match($patronRfc, $parametrosArray['txtcurpPF'])) {
											$curp = $JwtAuth->encriptar($parametrosArray['txtcurpPF']);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'clbint',
												'message' => 'Error en curp de su proveedor'
											);
										}
										if (preg_match($patronUrl, $parametrosArray['txtSitioWebPF'])) {
											$sitioweb = $JwtAuth->encriptar($parametrosArray['txtSitioWebPF']);
										} else {
											$dataMensaje = array(
												'status' => 'error',
												'code' => 200,
												'codeProvGenError' => 'websitio',
												'message' => 'Error en sitio web de su proveedor'
											);
										}
									} else {
										$nombre_comercial = NULL;
										$curp = NULL;
										$sitioweb = NULL;
									}

									if (
										isset($parametrosArray['redesSocialesPF']) &&
										!empty($parametrosArray['redesSocialesPF'])
									) {
										$arrayredesSoc = $parametrosArray['redesSocialesPF'];
										if (count($arrayredesSoc) != 0) {
											for ($i = 0; $i < count($arrayredesSoc); $i++) {
												if ($arrayredesSoc[$i] == '') {
													//$cliente->__SET('redes_sociales',$parametrosArray['redesSocialesPF']);
													$countarrayredesSoc++;
													$arraySocialMedia[] = $arrayredesSoc[$i];
												} else {
													$dataMensaje = array(
														'status' => 'error',
														'code' => 200,
														'codeProvGenError' => 'redSoc',
														'message' => 'Error en redes sociales de su proveedor'
													);
												}
											}
										} else {
											$countarrayredesSoc = 0;
										}
									}
									$pais = '118';
								} else {
									if (
										!isset($parametrosArray['txtPaternoPF']) ||
										empty($parametrosArray['txtPaternoPF']) ||
										!preg_match($patron, $parametrosArray['txtPaternoPF'])
									) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'paternoPF',
											'message' => 'Error en apellido paterno de su proveedor'
										);
									}

									if (
										!isset($parametrosArray['txtMaternoPF']) ||
										empty($parametrosArray['txtMaternoPF']) ||
										!preg_match($patron, $parametrosArray['txtMaternoPF'])
									) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'maternoPF',
											'message' => 'Error en apellido materno de su proveedor'
										);
									}

									if (
										!isset($parametrosArray['txtnombrePF']) ||
										empty($parametrosArray['txtnombrePF']) ||
										!preg_match($patron, $parametrosArray['txtnombrePF'])
									) {
										$dataMensaje = array(
											'status' => 'error',
											'code' => 200,
											'codeProvGenError' => 'nombrePF',
											'message' => 'Error en nombre de su proveedor'
										);
									}
								}
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'codeProvGenError' => 'clbint',
								'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
							);
						}
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'codeProvGenError' => 'clbint',
						'message' => 'Seleccione tipo de proveedor (nacional o extranjero)'
					);
				}

				if (($parametrosArray['decideinfocontacto'] == 'true' && $contadorContacto == 0) ||
					($contadorContacto == count($arrayContactoPersonal))
				) {

					if ($arraySocialMedia[0] != '' || $arraySocialMedia[1] != '' || $arraySocialMedia[2] != '' || $arraySocialMedia[3] != '') {
						$json_redes = $JwtAuth->encriptar(json_encode($arraySocialMedia));
					} else {
						$json_redes = NULL;
					}

					$listaProveedores = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
						->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
						->join("teci_forma_pago AS pago", "eegr_catalogo_proveedores.forma_pago", "pago.id")
						->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
						->where([
							'emp.empresa_token' => $empresa,
							'eegr_catalogo_proveedores.status' => true
						])->get();

					$countVerifica = 0;
					$invalidName = '';

					foreach ($listaProveedores as $resListProv) {
						if ($resListProv->denominacion_rs != '') {
							$nombreProv_f = strtolower($JwtAuth->desencriptar($resListProv->denominacion_rs));
						} else {
							$nombreProv_f = strtolower($JwtAuth->desencriptar($resListProv->paterno) . " " .
								$JwtAuth->desencriptar($resListProv->materno) . " " .
								$JwtAuth->desencriptar($resListProv->nombre));
						}

						$rfc_generico_f = strtolower($resListProv->rfc_generico);

						/*if ($resListProv->rfc != NULL) {
                  $rfc_prov_f = strtolower($JwtAuth->desencriptar($resListProv->rfc));
                } else {
                  $rfc_prov_f = '';
                }
        
                if ($resListProv->tax_id != NULL) {
                  $tax_id_prov_f = strtolower($JwtAuth->desencriptar($resListProv->tax_id));
                } else {
                  $tax_id_prov_f = '';
                }*/
						//return response()->json(['message' => $nombre_txt,'codigo' => 200,'status' => 'error']);
						if ($rfc_prov != NULL) {
							if ($resListProv->rfc == $rfc_prov) {
								++$countVerifica;
								$invalidName = $nombreProv_f;
							}
						} else if ($idtax != '') {
							if ($resListProv->tax_id == $idtax) {
								++$countVerifica;
								$invalidName = $nombreProv_f;
							}
						} else if ($resListProv->rfc_generico == $rfc_generico && $nombreProv_f == $nombre_txt) {
							++$countVerifica;
							$invalidName = $nombreProv_f;
						} else {
							if ($nombreProv_f == $nombre_txt) {
								++$countVerifica;
								$invalidName = $nombreProv_f;
							}
						}
					}
					//return response()->json(['message' => 'prueba 3','codigo' => 200,'status' => 'error']);	
					if ($countVerifica == 0) {
						$tknClemp = $JwtAuth->encriptarToken(
							$fechaAlta,
							$paternoPFtxt,
							$maternoPFtxt,
							$nombrePFtxt,
							$denominacion_rs,
							$nombre_comercial,
							$sitioweb,
							$json_redes,
							$pais,
							$rfc_prov
						);

						$insertProv = DB::table('personas')
							->insert(array(
								"token_personas" => $tknClemp,
								"paterno" => $paternoPFtxt,
								"materno" => $maternoPFtxt,
								"nombre" => $nombrePFtxt,
								"denominacion_rs" => $denominacion_rs,
								"nombre_com" => $nombre_comercial,
								"sitio_web" => $sitioweb,
								"redes_soc" => $json_redes,
								"nacionalidad" => $pais,
								"rfc_generico" => $rfc_generico,
								"rfc" => $rfc_prov,
								"tax_id" => $idtax,
								"curp" => $curp,
							));

						if ($insertProv) {
							$selecProv = DB::select("SELECT id FROM personas WHERE token_personas = ?", [$tknClemp]);
							$selectFpago = DB::select("SELECT id FROM forma_pago WHERE token_formapago	= ?", [$parametrosArray['formaPagoAltaProv']]);

							$tokenProv = $JwtAuth->encriptarToken($parametrosArray['formaPagoAltaProv'], $folio_prov . $selecProv[0]->id . $selectFpago[0]->id . $selectEmp[0]->id);

							if (file_exists($request->file('imagenAltaPdfFiscal'))) {
								$constsitfiscal = $JwtAuth->encriptar($folio_prov . "-" . $fechaAlta . '-' . $request->file('imagenAltaPdfFiscal')->getClientOriginalName());
							} else {
								$constsitfiscal = '';
							}
							if (file_exists($request->file('imagenAltaPdfCumplimientoObFiscales'))) {
								$cumplimiento = $JwtAuth->encriptar($folio_prov . "-" . $fechaAlta . '-' . $request->file('imagenAltaPdfCumplimientoObFiscales')->getClientOriginalName());
							} else {
								$cumplimiento = '';
							}

							if (file_exists($request->file('imagenAltaPdfEstCuenta'))) {
								$doc_estado_cuenta = $JwtAuth->encriptar($folio_prov . "-" . $fechaAlta . '-' . $request->file('imagenAltaPdfEstCuenta')->getClientOriginalName());
							} else {
								$doc_estado_cuenta = '';
							}

							if ($parametrosArray['valnoCargaDocsFiscalesRazon'] != '') {
								$valnoCargaDocsFiscalesRazon = $JwtAuth->encriptar($parametrosArray['valnoCargaDocsFiscalesRazon']);
							} else {
								$valnoCargaDocsFiscalesRazon = NULL;
							}

							if ($parametrosArray['tiene_docs_fiscales'] == 'true') {
								$tiene_docs_fiscales = TRUE;
							} else {
								$tiene_docs_fiscales = FALSE;
							}

							$creaCatProv = new ProveedoresModelo();

							$creaCatProv->token_cat_proveedores	= $tokenProv;
							$creaCatProv->folio	= $folio_nuevo;
							$creaCatProv->post_folio = $post_folio;

							$creaCatProv->fechaAlta = $fechaAlta;
							$creaCatProv->proveedor = $selecProv[0]->id;
							$creaCatProv->tiene_docs_fiscales = $tiene_docs_fiscales;
							$creaCatProv->const_sit_fiscal = $constsitfiscal;

							$creaCatProv->opinion_cumplimiento = $cumplimiento;
							$creaCatProv->no_cuenta_fiscales = $valnoCargaDocsFiscalesRazon;
							$creaCatProv->forma_pago = $selectFpago[0]->id;

							$creaCatProv->tipo_referencia_pago = $tipoReferenciaPago;
							$creaCatProv->clabe_interbancaria = $formaPagoClabeInterbank;
							$creaCatProv->estado_cuenta = $doc_estado_cuenta;

							$creaCatProv->utilizado = FALSE;
							$creaCatProv->fecha_delete_prov = '';
							$creaCatProv->status = TRUE;
							$creaCatProv->administrador = $selectEmp[0]->id;
							$savednewProv = $creaCatProv->save();

							if ($savednewProv) {
								$selectProvCat = DB::select("SELECT id FROM catalogo_proveedores WHERE token_cat_proveedores = ?", [$tokenProv]);

								$contadorInsertUbicaciones = 0;

								if ($parametrosArray['radioProv'] == 'extranjero') {
									$ubicacionExtranjeraProvv_reg = $parametrosArray['arrayubicacionExtranjeraProvv_reg'];
									for ($i = 0; $i < count($ubicacionExtranjeraProvv_reg); $i++) {
										if ($i == 0) {
											$tipo_direccion = 'dirección fiscal';
										} else {
											$tipo_direccion = 'dirección sucursal';
										}

										$clasificacionDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i][0]);
										$aliasDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i][1]);
										$cpostalDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i][2]);
										$calleDir = $JwtAuth->encriptar($ubicacionExtranjeraProvv_reg[$i][3]);
										$tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $clasificacionDir, $aliasDir, $cpostalDir, $calleDir);

										$fisinsertDir = DB::table('direcciones')
											->insert(array(
												"token_direccion" => $tokenCDir,
												"tipo_direccion" => $tipo_direccion,
												"clase" => $clasificacionDir,
												"alias" => $aliasDir,
												"calle" => $calleDir,
												"cod_postalext" => $cpostalDir,
												"pais" => $pais,
												"proveedor" => $selectProvCat[0]->id,
												"status" => TRUE,
												"administrador" => $selectEmp[0]->id,
											));
										if ($fisinsertDir) {
											$contadorInsertUbicaciones++;
										}
									}
								} else {
									$ubicacionNacionalProvv_reg = $parametrosArray['arrayubicacionNacionalProvv_reg'];

									for ($i = 0; $i < count($ubicacionNacionalProvv_reg); $i++) {

										if ($i == 0) {
											$tipo_direccion = 'dirección fiscal';
										} else {
											$tipo_direccion = 'dirección sucursal';
										}

										$arrayClasificacion = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['clase']);
										$arrayalias = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['alias']);
										$arraycalle = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['calle']);
										$arraynum_ext = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['num_ext']);

										$queryIDDir = DB::select("SELECT id FROM codigos_postales WHERE	token_codigos_postales = ?", [$ubicacionNacionalProvv_reg[$i]['cod_postal_nacprov']]);

										if ($ubicacionNacionalProvv_reg[$i]['num_int'] != '-') {
											$arraynum_int = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['num_int']);
										} else {
											$arraynum_int = NULL;
										}

										if ($ubicacionNacionalProvv_reg[$i]['calle1'] != '-') {
											$arraycalle1 = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['calle1']);
										} else {
											$arraycalle1 = NULL;
										}

										if ($ubicacionNacionalProvv_reg[$i]['calle2'] != '-') {
											$arraycalle2 = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['calle2']);
										} else {
											$arraycalle2 = NULL;
										}

										if ($ubicacionNacionalProvv_reg[$i]['referencia'] != '-') {
											$arrayreferencia = $JwtAuth->encriptar($ubicacionNacionalProvv_reg[$i]['referencia']);
										} else {
											$arrayreferencia = NULL;
										}

										$tokenCDir = $JwtAuth->encriptarToken($fechaAlta . $tipo_direccion .
											$queryIDDir[0]->id . $arrayClasificacion . $arrayalias . $arraycalle .
											$arraynum_ext . $arraynum_int . $arraycalle1 . $arraycalle2 . $arrayreferencia);

										$fisinsertDir = DB::table('direcciones')
											->insert(array(
												"token_direccion" => $tokenCDir,
												"tipo_direccion" => $tipo_direccion,
												"clase" => $arrayClasificacion,
												"alias" => $arrayalias,
												"calle" => $arraycalle,
												"num_ext" => $arraynum_ext,
												"num_int" => $arraynum_int,
												"codigo_postal" => $queryIDDir[0]->id,
												"pais" => 118,
												"calle1" => $arraycalle1,
												"calle2" => $arraycalle2,
												"referencia" => $arrayreferencia,
												"proveedor" => $selectProvCat[0]->id,
												"status" => TRUE,
												"administrador" => $selectEmp[0]->id,
											));

										if ($fisinsertDir) {
											$contadorInsertUbicaciones++;
										}
									}
								}

								$contadorInsertContacto = 0;

								if ($parametrosArray['decideinfocontacto'] == 'true') {
									for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
										$contArea = $JwtAuth->encriptar($arrayContactoPersonal[$i]['area']);
										$contCargo = $JwtAuth->encriptar($arrayContactoPersonal[$i]['cargo']);

										$contApePaterno = $JwtAuth->encriptar($arrayContactoPersonal[$i]['paterno']);
										$contApeMaterno = $JwtAuth->encriptar($arrayContactoPersonal[$i]['materno']);
										$contNombre = $JwtAuth->encriptar($arrayContactoPersonal[$i]['nombre']);

										$tokenPersonasPersonal = $JwtAuth->encriptarToken($contArea . '/' . $contCargo . '/' .
											$contApePaterno . '/' . $contApeMaterno . '/' . $selectEmp[0]->id . '/' . $contArea);

										$insertapersonalpersonas = DB::table('personas')
											->insert(array(
												"token_personas" => $tokenPersonasPersonal,
												"paterno" => $contApePaterno,
												"materno" => $contApeMaterno,
												"nombre" => $contNombre,
											));

										$selectpersonalpersonas = DB::select(
											"SELECT id FROM personas WHERE 
                      token_personas = ? AND paterno = ? AND materno = ? AND nombre = ?",
											[$tokenPersonasPersonal, $contApePaterno, $contApeMaterno, $contNombre]
										);

										$tokenPersonal = $JwtAuth->encriptarToken($arrayContactoPersonal[$i]['paterno'] . '/' . $arrayContactoPersonal[$i]['nombre'] .
											'/' . $arrayContactoPersonal[$i]['materno'] . '/' . $selectEmp[0]->id . '/' . $contArea);

										$insertapersonal = DB::table('personal')
											->insert(array(
												"pers_token" => $tokenPersonal,
												"fecha_alta_pers" => $fechaAlta,
												"folio_pers" => $i,
												"area" => $contArea,
												"cargo" => $contCargo,
												"personal" => $selectpersonalpersonas[0]->id,
												"usuario" => NULL,
												"cat_clientes" => NULL,
												"cat_proveedores" => $selectProvCat[0]->id,
												"status" => TRUE,
												"fecha_delete" => NULL
											));

										$selectpersonal = DB::select("SELECT id FROM personal WHERE pers_token = ?", [$tokenPersonal]);

										//$arrayEmailCont = $arrayContactoPersonal[$i]['emails'];
										$countInsertTel = 0;
										$personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
										if (count($personalTelefonos) != 0) {

											for ($t = 0; $t < count($personalTelefonos); $t++) {
												$contTelefono = $JwtAuth->encriptar($personalTelefonos[$t]['telefono']);

												if ($personalTelefonos[$t]['extension'] == '') {
													$contExtension = NULL;
												} else {
													$contExtension = $JwtAuth->encriptar($personalTelefonos[$t]['extension']);
												}

												$tokentel = $JwtAuth->encriptarToken($tokenPersonal . $contTelefono);

												$insertatelefonos_personal = DB::table('telefonos_personal')
													->insert(array(
														"token_telefono" => $tokentel,
														"personal" => $selectpersonal[0]->id,
														"icono" => $personalTelefonos[$t]['icon'],
														"etiqueta" => $personalTelefonos[$t]['etiqueta'],
														"telefono" => $contTelefono,
														"extension" => $contExtension,
														"status_telefono" => TRUE,
														"fecha_delete_tel" => NULL,
													));

												if ($insertatelefonos_personal) {
													$countInsertTel++;
												}
											}
										}

										$countInsertMails = 0;
										$personalEmails = $arrayContactoPersonal[$i]['emails'];
										if (count($personalEmails) != 0) {
											for ($m = 0; $m < count($personalEmails); $m++) {
												$contEmail = $JwtAuth->encriptar($personalEmails[$m]);
												$tokenEmail = $JwtAuth->encriptarToken($personalEmails[$m], $contEmail, $selectpersonal[0]->id);

												$insertacorreos_personal = DB::table('correos_personal')
													->insert(array(
														"token_correo" => $tokenEmail,
														"personal" => $selectpersonal[0]->id,
														"correo" => $contEmail,
														"status_correo" => TRUE,
														"fecha_delete_correo" => NULL,
													));
												if ($insertacorreos_personal) {
													$countInsertMails++;
												}
											}
										}


										if ($insertapersonalpersonas && $insertapersonal &&
											$countInsertTel == count($personalTelefonos) && $countInsertMails == count($personalEmails)
										) {
											$contadorInsertContacto++;
										}
									}
								}

								$tokenCreditos = $JwtAuth->encriptarToken($selectProvCat[0]->id . $fechaAlta . $aceptCredProv);
								$insertaCreditoProv = DB::table('creditos')
									->insert(array(
										"token_creditos" => $tokenCreditos,
										"proveedor" => $selectProvCat[0]->id,
										"aceptacredito" => $aceptCredProv,
										"moneda" => $selectMoneda,
										"limite" => $selectlimiteCredito,
										"dias" => $selectdiaspagoCredit,
										"comienza" => $selectComienzaPagoProv,
									));

								if (($contadorInsertUbicaciones == count($parametrosArray['arrayubicacionExtranjeraProvv_reg'])
										|| $contadorInsertUbicaciones == count($parametrosArray['arrayubicacionNacionalProvv_reg'])) &&
									$contadorInsertContacto == count($arrayContactoPersonal) && $insertaCreditoProv
								) {

									$filepath = $selectEmp[0]->root_tkn . "/0002-cpp/catalogos/proveedores/" . $folio_prov . "-" . $fechaAlta . "/";

									if (!file_exists(storage_path("/root/" . $filepath))) {
										Storage::disk('root')->makeDirectory($filepath, 0777, true, true);
									}

									if (file_exists($request->file('imagenAltaPdfFiscal'))) {
										Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfFiscal'), $JwtAuth->desencriptar($constsitfiscal));
									}

									//formData.append('base64AltaPdfFiscal',base64AltaPdfFiscal);
									if (file_exists($request->file('imagenAltaPdfCumplimientoObFiscales'))) {
										Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfCumplimientoObFiscales'), $JwtAuth->desencriptar($cumplimiento));
									}

									if (file_exists($request->file('imagenAltaPdfEstCuenta'))) {
										Storage::putFileAs("/public/root/" . $filepath, $request->file('imagenAltaPdfEstCuenta'), $JwtAuth->desencriptar($doc_estado_cuenta));
									}
									QRCode::text($tokenProv)->setOutfile(Storage::path('public/root/' . $filepath . $folio_prov . "-" . $fechaAlta . '-QRCode.png'))
										->png();
									//$cumplimiento;
									//$formaPagoClabeInterbank;

									$JwtAuth->insertBitacoraActividad(
										'egresos',
										'catalogos',
										'proveedores',
										$folio_prov,
										'registro en el catalogo de proveedores',
										$empresa
									);

									if (count($folioSistema) == 0) {
										$insertSistema = DB::table("sos_last_folders")
											->insert(
												array(
													"egr_proveedores" => TRUE,
													"folder" => 1,
													"post_folder" => $post_folio,
													"empresa" => $selectEmp[0]->id,
												)
											);
									} else {
										$regFolder = DB::table("sos_last_folders")->join("main_empresas AS emp", "last_folders.empresa", "=", "emp.id")
											->where([
												"sos_last_folders.egr_proveedores" => TRUE,
												"emp.empresa_token" => $empresa,
											])
											->limit(1)->update(
												array(
													"sos_last_folders.folder" => $folio_nuevo,
													"sos_last_folders.post_folder" => $post_folio,
												)
											);
									}

									$dataMensaje = array(
										'status' => 'success',
										'code' => 200,
										'message' => 'Proveedor registrado satisfactoriamente con el folio ' . $folio_prov
									);
								} else {
									$dataMensaje = array(
										'status' => 'error',
										'code' => 200,
										'message' => 'Datos de personal de contacto/direcciones/creditos de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
									);
								}
							} else {
								$dataMensaje = array(
									'status' => 'error',
									'code' => 200,
									'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
								);
							}
						} else {
							$dataMensaje = array(
								'status' => 'error',
								'code' => 200,
								'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
							);
						}
					} else {
						$dataMensaje = array(
							'status' => 'error',
							'code' => 200,
							'message' => 'ya existe un proveedor con esta información'
						);
					}
				} else {
					$dataMensaje = array(
						'status' => 'error',
						'code' => 200,
						'message' => 'problema'
					);
				}
			}
		} else {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'Los datos no son correctos'
			);
		}
		return response()->json($dataMensaje, $dataMensaje['code']);
	}
}

  public function registraProveedorMax(Request $request){

          if (isset($radioProv) && isset($radioProv) && preg_match($patron, $radioProv)) {
            
            if ($radioProv == "extranjero") {
              if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
                if ($subtipoProv == "provMoral") {
                  //return response()->json(['message' => 'pais','codigo' => 200,'status' => 'error']);
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov) && isset($paistoken) && !empty($paistoken)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);
                    $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
                    $pais_txt = $selectPais[0]->id;
                    if (!empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }
                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $sitio_web_txt = NULL;
                    }
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($patron, $name_prov)) {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeProvGenError" => "nomemp",
                        "message" => "Error en nombre de empresa de su proveedor"
                      );
                    }
                    if (!isset($paistoken) || empty($paistoken)) {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeProvGenError" => "pais",
                        "message" => "Error en pais de su proveedor"
                      );
                    }
                  }
                }
                //return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);		
                if ($subtipoProv == 'provFisica') {
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov) && isset($paistoken) && !empty($paistoken)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);

                    if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }

                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $sitio_web_txt = NULL;
                    }

                    $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
                    $pais_txt = $selectPais[0]->id;
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(), $name_prov)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'nombrePF',
                        'message' => 'Error en nombre de su proveedor'
                      );
                    }
                    if (!isset($paistoken) || empty($paistoken)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'paisPF',
                        'message' => 'Error en pais de su proveedor'
                      );
                    }
                  }
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'codeProvGenError' => 'clbint',
                  'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
                );
              }
            }

            if ($radioProv == 'nacional') {
              if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
                if ($subtipoProv == 'provMoral') {
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);
                    if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $nombre_comercial = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }
                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitioweb = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $sitio_web_txt = NULL;
                    }

                    $pais_txt = '118';
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(), $name_prov)) {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeProvGenError" => "nomemp",
                        "message" => "Error en nombre de su proveedor"
                      );
                    }
                  }
                }

                if ($subtipoProv == 'provFisica') {
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);

                    if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($curp) && !empty($curp) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }

                      if (preg_match($patronRfc, $curp)) {
                        $curp_txt = $JwtAuth->encriptar($curp);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'clbint',
                          'message' => 'Error en curp de su proveedor'
                        );
                      }

                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $curp_txt = NULL;
                      $sitio_web_txt = NULL;
                    }

                    $pais_txt = '118';
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(), $name_prov)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'nombrePF',
                        'message' => 'Error en nombre de su proveedor'
                      );
                    }
                  }
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'codeProvGenError' => 'clbint',
                  'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
                );
              }
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'codeProvGenError' => 'clbint',
              'message' => 'Seleccione tipo de proveedor (nacional o extranjero)'
            );
          }

    $imagenAltaPdfFiscal = $request->file('imagenAltaPdfFiscal');
    //formData.append('base64AltaPdfFiscal',base64AltaPdfFiscal);
    $imagenAltaPdfCumplimientoObFiscales = $request->file('imagenAltaPdfCumplimientoObFiscales');
    //formData.append('base64AltaPdfCumplimientoObFiscales',base64AltaPdfCumplimientoObFiscales);
    $imagenAltaPdfEstCuenta = $request->file('imagenAltaPdfEstCuenta');
    //formData.append('base64AltaPdfEstCuenta',base64AltaPdfEstCuenta);

    $empresa = $request->get('malchut_ctx')->malchut_hotam;
    $usuario = $request->get('user_auth')->keter_davidic;

    if (!$empresa) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado, debe seleccionar una empresa'], 428);
    }

    if (!$usuario) {
      return response()->json(['status' => 'error','message' => 'Usuario no autenticado'], 401);
    }

    $validate = \Validator::make($request->all(),[
      'rfc_generico' => 'required|string',
      'prov_rfc' => 'nullable|string',
      'id_tax' => 'nullable|string',
      'radioProv' => 'required|string',
      'subtipoProv' => 'required|string',
      'name_prov' => 'required|string',
      'habilitado_para_reembolsos' => 'required|string',
      'email_para_reembolsos' => 'nullable|string',
      'info_comparativa' => 'nullable|string',
      'comercial_nombre' => 'nullable|string',
      'curp' => 'nullable|string',
      'paistoken' => 'nullable|string',
      'sitio_web' => 'nullable|string',
      'tknRegimenFiscal' => 'nullable|string',
      'cuenta_contable' => 'nullable|string',
      'decideinfocontacto' => 'required|string',
      'arrayContactoPersonalProvv_reg' => 'nullable|array',
      'tiene_docs_fiscales' => 'required|string',
      'valnoCargaDocsFiscalesRazon' => 'nullable|string',
      'aceptaCredito' => 'required|string',
      'txtMoneda' => 'nullable|string',
      'txtlimiteCredito' => 'nullable|string',
      'txtdiaspagoCredit' => 'nullable|numeric',
      'selectComienzaPagoProv' => 'nullable|string',
      'decideformapago' => 'nullable|string',
      'formaPagoAltaProv' => 'nullable|string', //"token_forma_pago" => "string",
      'tipoReferenciaPago' => 'nullable|string',
      'clabeIntBanc' => 'nullable|string',
      'receptFactura' => 'nullable|string',
      'classRecibeArtPago' => 'nullable|string',
      'cod_postal' => 'nullable|string',
      'dipomex_cod_postal_estado' => 'nullable|string',
      'dipomex_cod_postal_municipio' => 'nullable|string',
      'dipomex_cod_postal_cp' => 'nullable|string',
      'dipomex_cod_postal_colonia_vinculada' => 'nullable|string',
      'listnewdireccionNac' => 'nullable|array',
    ]);

    if ($validate->fails()) {
			$dataMensaje = array(
				'status' => 'error',
				'code' => 200,
				'message' => 'El rango de fechas es inválido'.$validate->errors(),
				'errors' => $validate->errors()
			);
    } else {
      $JwtAuth = new \App\Helpers\JwtAuth();
      $rfc_generico = $request->input('rfc_generico');
      $prov_rfc = $request->input('prov_rfc');
      $id_tax = $request->input('id_tax');
      $radioProv = $request->input('radioProv');
      $subtipoProv = $request->input('subtipoProv');
      $name_prov = $request->input('name_prov');
      $habilitado_para_reembolsos = $request->input('habilitado_para_reembolsos');
      $email_para_reembolsos = $request->input('email_para_reembolsos');
      $info_comparativa = $request->input('info_comparativa');
      $comercial_nombre = $request->input('comercial_nombre');
      $curp = $request->input('curp');
      $paistoken = $request->input('paistoken');
      $sitio_web = $request->input('sitio_web');
      $tknRegimenFiscal = $request->input('tknRegimenFiscal');
      $cuenta_contable = $request->input('cuenta_contable');
      $decideinfocontacto = $request->input('decideinfocontacto');
      $arrayContactoPersonal = $request->input('arrayContactoPersonalProvv_reg');
      $tiene_docs_fiscales = $request->input('tiene_docs_fiscales');
      $valnoCargaDocsFiscalesRazon = $request->input('valnoCargaDocsFiscalesRazon');
      $aceptaCredito = $request->input('aceptaCredito');
      $txtMoneda = $request->input('txtMoneda');
      $txtlimiteCredito = $request->input('txtlimiteCredito');
      $txtdiaspagoCredit = $request->input('txtdiaspagoCredit');
      $comienzaPagoProv = $request->input('selectComienzaPagoProv');
      $decideformapago = $request->input('decideformapago');
      $token_forma_pago = $request->input('formaPagoAltaProv'); //"token_forma_pago" => "string",
      $tipoReferenciaPago = $request->input('tipoReferenciaPago');
      $clabeIntBanc = $request->input('clabeIntBanc');
      $receptFactura = $request->input('receptFactura');
      $classRecibeArtPago = $request->input('classRecibeArtPago');
      $cod_postal = $request->input('cod_postal');
      $dipomex_cod_postal_estado = $request->input('dipomex_cod_postal_estado');
      $dipomex_cod_postal_municipio = $request->input('dipomex_cod_postal_municipio');
      $dipomex_cod_postal_cp = $request->input('dipomex_cod_postal_cp');
      $dipomex_cod_postal_colonia_vinculada = $request->input('dipomex_cod_postal_colonia_vinculada');
      $listnewdireccionNac = $request->input('listnewdireccionNac');

      $validacion_cuenta_contable = isset($cuenta_contable) && !empty($cuenta_contable) && preg_match($JwtAuth->filtroAlfaNumerico(), $cuenta_contable);

      $patron = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]/';
      $patronNum = '/^[1-9][0-9]*$/';
      $patronCpostal = '/[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,-]/';
      $patronNumCred = '/^[0-9$,.-]*$/';
      $patronRfc = '/[aA0-zZ9]/';
      $patronMail = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}\b/';
      $patronUrl = '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|](\.)[a-z]{2}/i';

      $queryEmp = DB::select("SELECT emp.id,emp.zona_horaria,emp.root_tkn,users.jerarquia_main,users.id AS userr FROM main_empresas AS emp JOIN main_empresa_usuario AS empuser 
        JOIN teci_usuarios_catalogo AS users WHERE emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id 
        AND users.usuario_token = ?", [$empresa, $usuario]);

      if (count($queryEmp) > 0) {
        foreach ($queryEmp as $vEmp) {
          //da_te_default_timezone_set($vEmp->zona_horaria);
          $autorizado = FALSE;
          $autorizacion_fecha = NULL;
          $autorizacion_user = NULL;
          $folio_nuevo = NULL;
          $post_folio =  NULL;
          $folio_temporal = NULL;
          $folio_prov = NULL;

          $folioSistema = DB::select("SELECT fold.folder+1 AS folio,post_folder FROM sos_last_folders AS fold JOIN main_empresas AS emp 
            JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE fold.egr_proveedores = TRUE AND fold.empresa = emp.id 
            AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?",
            [$empresa, $usuario]
          );

          if ($vEmp->jerarquia_main == 'P') {
            $post_folio_db = DB::select(
              "SELECT post_folio FROM eegr_catalogo_proveedores WHERE id = (SELECT Max(catprov.id) FROM eegr_catalogo_proveedores AS catprov 
              JOIN main_empresas AS emp JOIN main_empresa_usuario AS empuser JOIN teci_usuarios_catalogo AS users WHERE catprov.administrador = emp.id 
              AND emp.empresa_token = ? AND emp.id = empuser.empresa AND empuser.usuario = users.id AND users.usuario_token = ?)",
              [$empresa, $usuario]
            );

            $folio_nuevo = count($folioSistema) != 1 || $folioSistema[0]->folio == 1000000000 ? 1 : $folioSistema[0]->folio;
            $post_folio = count($folioSistema) != 1 || (count($folioSistema) == 1 && $folioSistema[0]->folio != 1000000000) ? NULL : $JwtAuth->generarPostFolio($post_folio_db[0]->post_folio);
            $folio_prov = $post_folio == NULL ? 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) : 'PRV-' . $JwtAuth->generarFolio($folio_nuevo) . '-' . $post_folio;
            $autorizado = TRUE;
            $autorizacion_fecha = time();
            $autorizacion_user = $vEmp->userr;
          } else {
            $folioSistemaTemp = DB::select("SELECT temp_folio FROM eegr_catalogo_proveedores WHERE temp_folio IS NOT NULL AND administrador = (SELECT id FROM main_empresas WHERE empresa_token = ?)", [$empresa]);
            if (count($folioSistemaTemp) > 0) {
              $queryFolioTmpPrv = DB::select("SELECT temp_folio+1 AS temp_folio FROM eegr_catalogo_proveedores 
                WHERE id = (SELECT Max(catprov.id) FROM eegr_catalogo_proveedores AS catprov 
                JOIN main_empresas AS emp WHERE temp_folio IS NOT NULL AND catprov.administrador = emp.id 
                AND emp.empresa_token = ?)", [$empresa]);

              foreach ($queryFolioTmpPrv as $vTemp) {
                $folio_temporal = $vTemp->temp_folio;
              }
            } else {
              $folio_temporal = 1;
            }
            $folio_prov = 'PROV-TEMP-' . $JwtAuth->generarFolio($folio_temporal);
            $autorizado = FALSE;
          }

          $fechaAlta = time();

          if ($prov_rfc != "") {
            if (isset($prov_rfc) && isset($prov_rfc) && preg_match($patronRfc, $prov_rfc)) {
              $rfc_prov = $JwtAuth->encriptar(strtoupper($prov_rfc));
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'error al registrar rfc del proveedor'
              );
            }
          } else {
            $rfc_prov = NULL;
          }

          if ($id_tax != "") {
            if (isset($id_tax) && preg_match($patronRfc, $id_tax)) {
              $idtax = $JwtAuth->encriptar(strtoupper($id_tax));
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'error al registrar idtax del proveedor'
              );
            }
          } else {
            $idtax = NULL;
          }

          $sql_proveedor = NULL;
          $comercial_nombre_txt = NULL;
          $curp_txt = NULL;
          $pais_txt = NULL;
          $sitio_web_txt = NULL;

          if (isset($radioProv) && isset($radioProv) && preg_match($patron, $radioProv)) {
            if ($radioProv == "extranjero") {
              if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
                if ($subtipoProv == "provMoral") {
                  //return response()->json(['message' => 'pais','codigo' => 200,'status' => 'error']);
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov) && isset($paistoken) && !empty($paistoken)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);
                    $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
                    $pais_txt = $selectPais[0]->id;
                    if (!empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }
                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $sitio_web_txt = NULL;
                    }
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($patron, $name_prov)) {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeProvGenError" => "nomemp",
                        "message" => "Error en nombre de empresa de su proveedor"
                      );
                    }
                    if (!isset($paistoken) || empty($paistoken)) {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeProvGenError" => "pais",
                        "message" => "Error en pais de su proveedor"
                      );
                    }
                  }
                }
                //return response()->json(['message' => 'error','codigo' => 200,'status' => 'error']);		
                if ($subtipoProv == 'provFisica') {
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov) && isset($paistoken) && !empty($paistoken)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);

                    if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }

                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $sitio_web_txt = NULL;
                    }

                    $selectPais = DB::select("SELECT id FROM teci_pais WHERE token_pais = ?", [$paistoken]);
                    $pais_txt = $selectPais[0]->id;
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(), $name_prov)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'nombrePF',
                        'message' => 'Error en nombre de su proveedor'
                      );
                    }
                    if (!isset($paistoken) || empty($paistoken)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'paisPF',
                        'message' => 'Error en pais de su proveedor'
                      );
                    }
                  }
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'codeProvGenError' => 'clbint',
                  'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
                );
              }
            }

            if ($radioProv == 'nacional') {
              if (isset($subtipoProv) && isset($subtipoProv) && preg_match($patron, $subtipoProv)) {
                if ($subtipoProv == 'provMoral') {
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);
                    if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $nombre_comercial = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }
                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitioweb = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $sitio_web_txt = NULL;
                    }

                    $pais_txt = '118';
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(), $name_prov)) {
                      $dataMensaje = array(
                        "status" => "error",
                        "code" => 200,
                        "codeProvGenError" => "nomemp",
                        "message" => "Error en nombre de su proveedor"
                      );
                    }
                  }
                }

                if ($subtipoProv == 'provFisica') {
                  if (isset($name_prov) && !empty($name_prov) && preg_match($patron, $name_prov)) {
                    $sql_proveedor = $JwtAuth->encriptar($name_prov);

                    if (isset($comercial_nombre) && !empty($comercial_nombre) && isset($curp) && !empty($curp) && isset($sitio_web) && !empty($sitio_web)) {
                      if (preg_match($patron, $comercial_nombre)) {
                        $comercial_nombre_txt = $JwtAuth->encriptar($comercial_nombre);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'nomcom',
                          'message' => 'Error en nombre comercial de su proveedor'
                        );
                      }

                      if (preg_match($patronRfc, $curp)) {
                        $curp_txt = $JwtAuth->encriptar($curp);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'clbint',
                          'message' => 'Error en curp de su proveedor'
                        );
                      }

                      if (preg_match($patronUrl, $sitio_web)) {
                        $sitio_web_txt = $JwtAuth->encriptar($sitio_web);
                      } else {
                        $dataMensaje = array(
                          'status' => 'error',
                          'code' => 200,
                          'codeProvGenError' => 'websitio',
                          'message' => 'Error en sitio web de su proveedor'
                        );
                      }
                    } else {
                      $comercial_nombre_txt = NULL;
                      $curp_txt = NULL;
                      $sitio_web_txt = NULL;
                    }

                    $pais_txt = '118';
                  } else {
                    if (!isset($name_prov) || empty($name_prov) || !preg_match($JwtAuth->filtroAlfaNumerico(), $name_prov)) {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'codeProvGenError' => 'nombrePF',
                        'message' => 'Error en nombre de su proveedor'
                      );
                    }
                  }
                }
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'codeProvGenError' => 'clbint',
                  'message' => 'Seleccione subtipo de proveedor (persona física o moral)'
                );
              }
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'codeProvGenError' => 'clbint',
              'message' => 'Seleccione tipo de proveedor (nacional o extranjero)'
            );
          }

          $sql_regimen_fiscal = DB::table("sos_regimen_fiscal")->where("token_regimen_fiscal", $tknRegimenFiscal)->value("id");

          $contadorContacto = 0;
          if ($decideinfocontacto == 'true') {
            for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
              if (
                preg_match($patron, $arrayContactoPersonal[$i]['paterno']) && preg_match($patron, $arrayContactoPersonal[$i]['materno']) &&
                preg_match($patron, $arrayContactoPersonal[$i]['nombre']) && preg_match($patron, $arrayContactoPersonal[$i]['area']) &&
                preg_match($patron, $arrayContactoPersonal[$i]['cargo'])
              ) {

                if (count($arrayContactoPersonal[$i]['emails']) == 0 && count($arrayContactoPersonal[$i]['telefonos']) == 0) {
                  $contadorContacto++;
                } else {
                  $contadorMails = 0;
                  $personalEmails = $arrayContactoPersonal[$i]['emails'];
                  for ($m = 0; $m < count($personalEmails); $m++) {
                    if (preg_match($patronMail, $personalEmails[$m])) {
                      $contadorMails++;
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'positionErrorCode' => $m,
                        'message' => 'Error en correo electrónico de personal de contacto'
                      );
                      break;
                    }
                  }

                  $contadorTelefonos = 0;
                  $personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
                  for ($t = 0; $t < count($personalTelefonos); $t++) {
                    if (
                      preg_match($patron, $personalTelefonos[$t]['etiqueta']) &&
                      preg_match($patronNum, $personalTelefonos[$t]['telefono_complete']) &&
                      preg_match($patronCpostal, $personalTelefonos[$t]['extension'])
                    ) {
                      $contadorTelefonos++;
                    } else {
                      $dataMensaje = array(
                        'status' => 'error',
                        'code' => 200,
                        'positionErrorCode' => $m,
                        'message' => 'Error en teléfono de personal de contacto'
                      );
                      break;
                    }
                  }

                  if ($contadorMails == count($personalEmails) || $contadorTelefonos == count($personalTelefonos)) {
                    $contadorContacto++;
                  }
                }
              } else {
                if (!preg_match($patron, $arrayContactoPersonal[$i]['paterno'])) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'positionErrorCode' => $i,
                    'message' => 'Error en apellido paterno de personal de contacto'
                  );
                }
                if (!preg_match($patron, $arrayContactoPersonal[$i]['materno'])) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'positionErrorCode' => $i,
                    'message' => 'Error en apellido materno de personal de contacto'
                  );
                }
                if (!preg_match($patron, $arrayContactoPersonal[$i]['nombre'])) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'positionErrorCode' => $i,
                    'message' => 'Error en nombre de personal de contacto'
                  );
                }
                if (!preg_match($patron, $arrayContactoPersonal[$i]['area'])) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'positionErrorCode' => $i,
                    'message' => 'Error en area de trabajo de personal de contacto'
                  );
                }
                if (!preg_match($patron, $arrayContactoPersonal[$i]['cargo'])) {
                  $dataMensaje = array(
                    'status' => 'error',
                    'code' => 200,
                    'positionErrorCode' => $i,
                    'message' => 'Error en cargo de trabajo de personal de contacto'
                  );
                }
              }
            }
          }

          $aceptCredProv = false;
          $selectMoneda = NULL;
          $selectlimiteCredito = NULL;
          $selectdiaspagoCredit = NULL;
          $selectComienzaPagoProv = NULL;

          if ($aceptaCredito == 'true') {
            if (!isset($txtMoneda)) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Error en seleccion de moneda'
              );
            }
            if (!preg_match($patronNumCred, $txtlimiteCredito)) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Error en limite de credito'
              );
            }

            if (!preg_match($patronNum, $txtdiaspagoCredit)) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Error en seleccion de dias de pago'
              );
            }
            if (!preg_match($patron, $comienzaPagoProv)) {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Error en seleccion de comienzo de pago'
              );
            }
          }

          $forma_pago_ident = $decideformapago == 'true' ? DB::table("teci_forma_pago")->where("token_formapago", $token_forma_pago)->value("id") : NULL;

          $proveedorExiste = ProveedoresModelo::join("sos_personas AS prov", "eegr_catalogo_proveedores.proveedor", "prov.id")
            ->join("teci_pais AS ps", "prov.nacionalidad", "ps.id")
            ->join("main_empresas AS emp", "eegr_catalogo_proveedores.administrador", "=", "emp.id")
            ->where('emp.empresa_token', $empresa)
            ->where('eegr_catalogo_proveedores.status', TRUE)
            ->where(function ($query) use ($rfc_prov, $idtax, $sql_proveedor) {
              if ($rfc_prov) {
                $query->orWhere('prov.rfc', $rfc_prov);
              }
              if (!empty($idtax)) {
                $query->orWhere('prov.tax_id', $idtax);
              }
              if (!empty($sql_proveedor)) {
                $query->orWhereRaw('LOWER(prov.nombre_extendido) = ?', [strtolower($sql_proveedor)]);
              }
            })->exists();

          if (!$proveedorExiste) {
            $tkn_people_prov = $JwtAuth->encriptarToken($fechaAlta, $name_prov, $comercial_nombre_txt, $sitio_web_txt, $pais_txt, $rfc_prov);

            $insertProv = DB::table("sos_personas")
              ->insert(array(
                "token_personas" => $tkn_people_prov,
                "nombre_extendido" => $sql_proveedor,
                "nombre_com" => $comercial_nombre_txt,
                "sitio_web" => $sitio_web_txt,
                "nacionalidad" => $pais_txt,
                "rfc_generico" => $rfc_generico,
                "rfc" => $rfc_prov,
                "tax_id" => $idtax,
                "curp" => $curp_txt,
              ));
            if ($insertProv) {
              $selecProvNames = DB::table("sos_personas")->where("token_personas", $tkn_people_prov)->value("id");
              $tokenProv = $JwtAuth->encriptarToken($selecProvNames . substr($tkn_people_prov, 0, 20) . $autorizado . $autorizacion_fecha . $autorizacion_user . $folio_nuevo . $post_folio . $folio_temporal . $folio_prov . $vEmp->id);
              $creaCatProv = new ProveedoresModelo();
              $creaCatProv->token_cat_proveedores  = $tokenProv;
              $creaCatProv->folio = $folio_nuevo;
              $creaCatProv->post_folio  = $post_folio;
              $creaCatProv->temp_folio  = $folio_temporal;
              $creaCatProv->authorized  = $autorizado;
              $creaCatProv->authorized_fecha  = $autorizacion_fecha;
              $creaCatProv->authorized_by  = $autorizacion_user;
              $creaCatProv->fechaAlta = $fechaAlta;
              $creaCatProv->proveedor = $selecProvNames;
              $creaCatProv->subClase = $subtipoProv == "provMoral" ? "PM" : "PF";
              $creaCatProv->regimen_fiscal = $sql_regimen_fiscal;
              $creaCatProv->habilitado_para_reembolsos = $subtipoProv == "provFisica" && $habilitado_para_reembolsos == 'true' ? TRUE : FALSE;
              $creaCatProv->lista_precios = NULL;
              $creaCatProv->tiene_docs_fiscales = $tiene_docs_fiscales == 'true' ? TRUE : FALSE;
              $creaCatProv->no_cuenta_fiscales = $tiene_docs_fiscales == 'true' ? $valnoCargaDocsFiscalesRazon : NULL;
              $creaCatProv->forma_pago = $forma_pago_ident;
              $creaCatProv->tipo_referencia_pago = $decideformapago == 'true' ? $tipoReferenciaPago : NULL;
              $creaCatProv->clabe_interbancaria = $decideformapago == 'true' ? $clabeIntBanc : NULL;
              $creaCatProv->receptFactura = $receptFactura == 'true' ? TRUE : FALSE;
              $creaCatProv->classRecibeArtPago = $classRecibeArtPago == 'true' ? TRUE : FALSE;
              $creaCatProv->cuenta_contable = $validacion_cuenta_contable ? $JwtAuth->encriptar($cuenta_contable) : NULL;
              $creaCatProv->utilizado = FALSE;
              $creaCatProv->status = TRUE;
              $creaCatProv->administrador = $vEmp->id;
              $savednewProv = $creaCatProv->save();
              if ($savednewProv) {
                $selectProvCat = $creaCatProv->id;

                if ($subtipoProv == "provFisica" && $habilitado_para_reembolsos == 'true') {
                  $select_fol_emp = DB::selectOne("SELECT COALESCE(MAX(folio_soli_vinculo) + 1, 1) AS folio FROM eegr_catalogo_proveedores_soli_vinc_usuario where empresa = ?", [1]);
                  DB::table("eegr_catalogo_proveedores_soli_vinc_usuario")
                    ->insert(array(
                      "token_soli_vinculo" => $JwtAuth->encriptarToken($selectProvCat, $select_fol_emp->folio, $email_para_reembolsos),
                      "folio_soli_vinculo" => $select_fol_emp->folio,
                      "fecha_soli_vinculo" => $fechaAlta,
                      "proveedor" => $selectProvCat,
                      "email_vinculo" => $JwtAuth->encriptar($email_para_reembolsos),
                      "info_comparativa" => $JwtAuth->encriptar($info_comparativa),
                      "empresa" => $vEmp->id,
                      "aprobada" => FALSE
                    ));
                  //create table eegr_catalogo_proveedores_soli_vinc_usuario(
                  //  id int(10) primary key not null auto_increment,
                  //  token_soli_vinculo text,
                  //  folio_soli_vinculo text,
                  //  fecha_soli_vinculo varchar(10),
                  //  proveedor int(10),
                  //  email_vinculo text,
                  //  empresa int(10),
                  //  aprobada int(10),
                  //  folio_aprobacion text,
                  //  usuario int(10) DEFAULT NULL,
                  //  foreign key (proveedor) references eegr_catalogo_proveedores (id),
                  //  foreign key (empresa) references main_empresas (id),
                  //  foreign key (usuario) references teci_usuarios_catalogo (id)
                  //);
                }

                if ($radioProv == 'extranjero') {
                  $tipo_direccion = 'dirección fiscal';
                  $cpostalDir = $JwtAuth->encriptar($cod_postal);
                  $clasificacionDir = $JwtAuth->encriptar("matriz");
                  $tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $cpostalDir, $clasificacionDir);

                  $fisinsertDir = DB::table("teci_direcciones")
                    ->insert(array(
                      "token_direccion" => $tokenCDir,
                      "tipo_direccion" => $tipo_direccion,
                      "clase" => $clasificacionDir,
                      "cod_postalext" => $cpostalDir,
                      "pais_code" => $pais_txt,
                      "proveedor" => $selectProvCat,
                      "status" => TRUE,
                      "administrador" => $vEmp->id,
                    ));
                } else {
                  $tipo_direccion = "dirección fiscal";
                  $clasificacionDir = $JwtAuth->encriptar("matriz");
                  $tokenCDir = $JwtAuth->encriptarToken($fechaAlta, $dipomex_cod_postal_estado, $dipomex_cod_postal_municipio, $dipomex_cod_postal_cp, $dipomex_cod_postal_colonia_vinculada, $clasificacionDir);
                  $listnewdireccionNac = $request->input('listnewdireccionNac');
                  $fisinsertDir = DB::table("teci_direcciones")
                    ->insert(array(
                      "token_direccion" => $tokenCDir,
                      "tipo_direccion" => $tipo_direccion,
                      "clase" => $clasificacionDir,
                      "pais" => 118,
                      "pais_code" => "MEX",
                      "estado_edit" => $JwtAuth->encriptar($dipomex_cod_postal_estado),
                      "municipio_edit" => $JwtAuth->encriptar($dipomex_cod_postal_municipio),
                      "c_postal_edit" => $dipomex_cod_postal_cp,
                      "colonia_edit" => $JwtAuth->encriptar($dipomex_cod_postal_colonia_vinculada),
                      "adicional" => "api",
                      "proveedor" => $selectProvCat,
                      "status" => TRUE,
                      "administrador" => $vEmp->id,
                    ));
                }

                if ($decideinfocontacto == 'true') {
                  for ($i = 0; $i < count($arrayContactoPersonal); $i++) {
                    $contArea = $arrayContactoPersonal[$i]['area']; //area
                    $contCargo = $arrayContactoPersonal[$i]['cargo'];
                    $contApePaterno = $arrayContactoPersonal[$i]['paterno'];
                    $contApeMaterno = $arrayContactoPersonal[$i]['materno'];
                    $contNombre = $arrayContactoPersonal[$i]['nombre'];

                    $tokenPersonasPersonal = $JwtAuth->encriptarToken($contArea . '/' . $contCargo . '/' . $contApePaterno . '/' . $contApeMaterno . '/' . $vEmp->id . '/' . $contArea);
                    $insertapersonalpersonas = DB::table('sos_personas')
                      ->insert(array(
                        "token_personas" => $tokenPersonasPersonal,
                        "paterno" => $JwtAuth->encriptar($contApePaterno),
                        "materno" => $JwtAuth->encriptar($contApeMaterno),
                        "nombre" => $JwtAuth->encriptar($contNombre)
                      ));

                    $tokenPersonal = $JwtAuth->encriptarToken($contApePaterno . '/' . $contNombre . '/' . $contApeMaterno . '/' . $vEmp->id . '/' . $contArea);
                    $insertapersonal = DB::table('in_egr_contacto_cliente_proveedor')->insert(
                      array(
                        "token_contacto" => $tokenPersonal,
                        "fecha_alta_contacto" => time(),
                        "folio_contacto" => $i,
                        "area_contacto" => $JwtAuth->encriptar($contArea),
                        "cargo_contacto" => $JwtAuth->encriptar($contCargo),
                        "nombre" => DB::table("sos_personas")->where("token_personas", $tokenPersonasPersonal)->value("id"),
                        "cat_proveedores" => $selectProvCat,
                        "status" => TRUE,
                        "fecha_delete" => NULL
                      )
                    );
                    $selectContacto = DB::table("in_egr_contacto_cliente_proveedor")->where("token_contacto", $tokenPersonal)->value("id");
                    $personalTelefonos = $arrayContactoPersonal[$i]['telefonos'];
                    if (count($personalTelefonos) != 0) {
                      for ($t = 0; $t < count($personalTelefonos); $t++) {
                        $contTelefono = $JwtAuth->encriptar($personalTelefonos[$t]['telefono_complete']);
                        $contExtension = $personalTelefonos[$t]['extension'] != '' ? $JwtAuth->encriptar($personalTelefonos[$t]['extension']) : NULL;
                        $tokentel = $JwtAuth->encriptarToken($tokenPersonal . $contTelefono);
                        $principal = $t == 0 ? TRUE : FALSE;
                        //return response()->json(['message' => $personalTelefonos[$t]["etiqueta"],'code' => 200,'status' => 'error']);
                        $insertatelefonos_personal = DB::table('sos_personas_telefonos')
                          ->insert(array(
                            "token_telefono" => $tokentel,
                            "contacto_cliente_prov" => $selectContacto,
                            //"icono" => $personalTelefonos[$t]["icon"],	
                            "etiqueta" => $personalTelefonos[$t]["etiqueta"],
                            "cod_pais" => 52,
                            "telefono" => $contTelefono,
                            "principal" => $principal,
                            "extension" => $contExtension,
                            "status_telefono" => TRUE,
                            "fecha_delete_tel" => NULL,

                          ));
                      }
                    }

                    $personalEmails = $arrayContactoPersonal[$i]['emails'];
                    if (count($personalEmails) != 0) {
                      for ($m = 0; $m < count($personalEmails); $m++) {
                        $contEmail = $JwtAuth->encriptar($personalEmails[$m]);
                        $tokenEmail = $JwtAuth->encriptarToken($personalEmails[$m], $contEmail, $selectContacto);
                        $insertacorreos_personal = DB::table('sos_personas_correos')
                          ->insert(array(
                            "token_correo" => $tokenEmail,
                            "contacto_cliente_prov" => $selectContacto,
                            "correo" => $contEmail,
                            "status_correo" => TRUE,
                            "fecha_delete_correo" => NULL,
                          ));
                      }
                    }
                  }
                }

                $tokenCreditos = $JwtAuth->encriptarToken($selectProvCat . $fechaAlta . $aceptaCredito);
                $insertaCreditoProv = DB::table('in_egr_creditos')
                  ->insert(array(
                    "token_creditos" => $tokenCreditos,
                    "proveedor" => $selectProvCat,
                    "aceptacredito" => $aceptaCredito == 'true' ? TRUE : FALSE,
                    "moneda_code" => $aceptaCredito == 'true' ? $txtMoneda : NULL,
                    "limite" => $aceptaCredito == 'true' ? $txtlimiteCredito : NULL,
                    "dias" => $aceptaCredito == 'true' ? $txtdiaspagoCredit : NULL,
                    "comienza" => $aceptaCredito == 'true' ? $comienzaPagoProv : NULL,
                  ));

                $filepath = $vEmp->root_tkn . "/0002-cpp/catalogos/proveedores/$fechaAlta/";
                if (!file_exists(storage_path("/root/" . $filepath))) {
                  Storage::disk("root")->makeDirectory($filepath, 0777, true, true);
                }
                if (file_exists($request->file('docSituacionFiscal'))) {
                  $namesitfiscal = $fechaAlta . '-' . $request->file('docSituacionFiscal')->getClientOriginalName();
                  $typesitfiscal = $JwtAuth->getExtensionDoc($request->file('docSituacionFiscal')->getClientMimeType());
                  $tkn_doc_sitfiscal = $JwtAuth->encriptarToken($tokenProv, $usuario, $empresa, $namesitfiscal);
                  $JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_sitfiscal, "fcsf", $namesitfiscal, $typesitfiscal);
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('docSituacionFiscal'), $namesitfiscal);
                }

                if (file_exists($request->file('docCumplimientoObFiscales'))) {
                  $namecumplimiento = $fechaAlta . '-' . $request->file('docCumplimientoObFiscales')->getClientOriginalName();
                  $typecumplimiento = $JwtAuth->getExtensionDoc($request->file('docCumplimientoObFiscales')->getClientMimeType());
                  $tkn_doc_cumplimiento = $JwtAuth->encriptarToken($tokenProv, $usuario, $empresa, $namecumplimiento);
                  $JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_cumplimiento, "cuof", $namecumplimiento, $typecumplimiento);
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('docCumplimientoObFiscales'), $namecumplimiento);
                }

                if (file_exists($request->file('docContrato'))) {
                  $namecontrato = $fechaAlta . '-' . $request->file('docContrato')->getClientOriginalName();
                  $typecontrato = $JwtAuth->getExtensionDoc($request->file('docContrato')->getClientMimeType());
                  $tkn_doc_contrato = $JwtAuth->encriptarToken($tokenProv, $usuario, $empresa, $namecontrato);
                  $JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_contrato, "fcnt", $namecontrato, $typecontrato);
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('docContrato'), $namecontrato);
                }

                if (isset($_FILES['files_anexos']) && !empty($_FILES['files_anexos'])) {
                  $anexo_archivos = $_FILES["files_anexos"];
                  $anexo_archivos_strings = json_decode(json_encode($_FILES["files_anexos"]["name"]));
                  if (count($anexo_archivos_strings) > 0) {
                    for ($i = 0; $i < count($anexo_archivos_strings); $i++) {
                      $docAnexo_temporal = $anexo_archivos["tmp_name"][$i];
                      $docAnexo_name = "anexos/" . $anexo_archivos["name"][$i];
                      $docAnexo_type = $JwtAuth->getExtensionDoc($anexo_archivos["type"][$i]);
                      $docAnexo_tknn = $JwtAuth->encriptarToken($tokenProv, $usuario, $empresa, $docAnexo_name);
                      $JwtAuth->registraDocsProveedor($tokenProv, $docAnexo_tknn, "anex", $docAnexo_name, $docAnexo_type);
                      Storage::putFileAs("/public/root/" . $filepath, $docAnexo_temporal, $docAnexo_name);
                    }
                  }
                }

                if (file_exists($request->file('docEstadoCuenta'))) {
                  $nameestadocuenta = $fechaAlta . '-' . $request->file('docEstadoCuenta')->getClientOriginalName();
                  $typeestadocuenta = $JwtAuth->getExtensionDoc($request->file('docEstadoCuenta')->getClientMimeType());
                  $tkn_doc_estadocuenta = $JwtAuth->encriptarToken($tokenProv, $usuario, $empresa, $nameestadocuenta);
                  $JwtAuth->registraDocsProveedor($tokenProv, $tkn_doc_estadocuenta, "ecue", $nameestadocuenta, $typeestadocuenta);
                  Storage::putFileAs("/public/root/" . $filepath, $request->file('docEstadoCuenta'), $nameestadocuenta);
                }

                $JwtAuth->insertBitacoraActividad(
                  "egresos",
                  "catalogos",
                  "proveedores",
                  $folio_prov,
                  "registro en el catalogo de proveedores",
                  $empresa,
                  $usuario
                );
                //return response()->json(['message' => 'gantt_diagram','code' => 200,'status' => 'error']);
                //return response()->json(["message" => "prueba33","code" => 200,"status" => "error"]);

                if ($vEmp->jerarquia_main == 'P') {
                  if (count($folioSistema) == 0) {
                    $insertSistema = DB::table("sos_last_folders")
                      ->insert(
                        array(
                          "egr_proveedores" => TRUE,
                          "folder" => 1,
                          "post_folder" => $post_folio,
                          "empresa" => $vEmp->id,
                        )
                      );
                  } else {
                    $regFolder = DB::table("sos_last_folders AS fold")
                      ->join("main_empresas AS emp", "fold.empresa", "=", "emp.id")
                      ->join("main_empresa_usuario AS empuser", "emp.id", "empuser.empresa")
                      ->join("teci_usuarios_catalogo AS users", "empuser.usuario", "users.id")
                      ->where([
                        "fold.egr_proveedores" => TRUE,
                        "emp.empresa_token" => $empresa,
                        "users.usuario_token" => $usuario,
                      ])
                      ->limit(1)->update(
                        array(
                          "fold.folder" => $folio_nuevo,
                          "fold.post_folder" => $post_folio,
                        )
                      );
                  }
                }

                $dataMensaje = array(
                  'status' => 'success',
                  'code' => 200,
                  'message' => 'Proveedor registrado satisfactoriamente con el folio ' . $folio_prov
                );
              } else {
                $dataMensaje = array(
                  'status' => 'error',
                  'code' => 200,
                  'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
                );
              }
            } else {
              $dataMensaje = array(
                'status' => 'error',
                'code' => 200,
                'message' => 'Datos generales de este proveedor no fueron guardados debido a problemas internos, comuniquese a soporte para más información'
              );
            }
          } else {
            $dataMensaje = array(
              'status' => 'error',
              'code' => 200,
              'message' => 'ya existe un proveedor con esta información'
            );
          }
        }
      } else {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'La empresa seleccionada es invalida'
        );
      }
    }
    return response()->json($dataMensaje, $dataMensaje['code']);
  }