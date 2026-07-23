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
use App\Models\NotificacionesModelo;
use PDF;
use QRCode;

class MAIN_NotificacionesController extends Controller
{

  public function totalNotificaciones(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayAlertas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
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
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $alertaList = DB::select(
          "SELECT * FROM teci_notificaciones AS alert INNER JOIN main_empresas AS emp
                    ON alert.empresa = emp.id INNER JOIN vhum_personal AS receptor ON alert.receptor = receptor.id 
                    INNER JOIN main_usuarios AS users ON receptor.usuario = users.id 
                    WHERE alert.status_recibe = FALSE AND alert.status_delete = TRUE and emp.emp_token = ? 
                    AND users.user_token = ?
                    AND ((alert.proyecto IS NOT NULL AND alert.area IS NULL AND alert.subarea IS NULL 
                        AND	alert.producto IS NULL AND alert.servicio IS NULL AND alert.clave_serv IS NULL 
                        AND	alert.cliente IS NULL AND alert.proveedor IS NULL 
                        AND alert.proyecto IN (SELECT id FROM module_proyectos)) 
                        OR (alert.proyecto IS NULL AND alert.area IS NOT NULL AND alert.subarea IS NOT NULL 
                        AND	alert.producto IS NOT NULL AND alert.servicio IS NOT NULL 
                        AND alert.clave_serv IS NOT NULL AND alert.cliente IS NOT NULL 
                        AND alert.proveedor IS NOT NULL)) ORDER BY alert.id DESC",
          [$usuario->emp_token, $usuario->user_token]
        );

        $dataMensaje = array(
          'status' => 'success',
          'code' => 200,
          'tareas' => count($alertaList),
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

  public function listaNotificacionesFirst(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayAlertas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'string',
        //'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        //$usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
        if ($parametrosArray['user_token'] != "") {
          $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
          $not_empr = $usuario->emp_token;
          $not_user = $usuario->user_token;
        } else {
          $not_empr = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
          $not_user = "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4";
        }

        $lista = DB::select(
          "SELECT * FROM teci_notificaciones AS alert INNER JOIN main_empresas AS emp
                    ON alert.empresa = emp.id INNER JOIN vhum_personal AS receptor ON alert.receptor = receptor.id 
                    INNER JOIN main_usuarios AS users ON receptor.usuario = users.id 
                    WHERE alert.status_recibe = FALSE AND alert.status_delete = TRUE and emp.emp_token = ? 
                    AND users.user_token = ?
                    AND ((alert.proyecto IS NOT NULL AND alert.area IS NULL AND alert.subarea IS NULL 
                        AND	alert.producto IS NULL AND alert.servicio IS NULL AND alert.clave_serv IS NULL 
                        AND	alert.cliente IS NULL AND alert.proveedor IS NULL 
                        AND alert.proyecto IN (SELECT id FROM module_proyectos)) 
                        OR (alert.proyecto IS NULL AND alert.area IS NOT NULL AND alert.subarea IS NOT NULL 
                        AND	alert.producto IS NOT NULL AND alert.servicio IS NOT NULL 
                        AND alert.clave_serv IS NOT NULL AND alert.cliente IS NOT NULL 
                        AND alert.proveedor IS NOT NULL)) ORDER BY alert.id DESC LIMIT 5",
          [$usuario->emp_token, $usuario->user_token]
        );

        if (count($lista) != 0) {
          foreach ($lista as $valAlert) {
            $token_notificacion = $valAlert->token_notificacion;
            $fecha_notificacion = date('d-m-Y H:i:s', $valAlert->fecha_notificacion);
            $titulo_alerta = $JwtAuth->desencriptar($valAlert->titulo);
            $link_detalle = "";
            //control

            //area
            //subarea
            //producto
            //servicio
            //clave_serv
            //cliente
            //proveedor
            //empresa
            //emisor
            //receptor
            //visto
            //status_recibe
            //fecha_lectura
            //status_delete
            //fecha_delete

            $sql_emisor = NotificacionesModelo::join("vhum_personal AS emisor", "alert.emisor", "=", "emisor.id")
              ->join("sos_personas AS pers_people", "emisor.personal", "=", "pers_people.id")
              ->where(['alert.token_notificacion' => $token_notificacion,])->get();

            $token_emisor = $sql_emisor[0]->pers_token;
            $emisor = $JwtAuth->desencriptar($sql_emisor[0]->paterno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->materno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->nombre);

            $mensaje = "";
            $contenido = "";

            if ($valAlert->visto == TRUE) {
              $visto = true;
            } else {
              $visto = false;
            }

            $select_proy = DB::select("SELECT proyecto_name,token_proyecto FROM module_proyectos WHERE id = ?", [$valAlert->proyecto]);

            if (count($select_proy) != 0) {
              $css_mensaje = "proyectos";
              $tipo_mensaje = "Gestión de proyectos";
              //echo $valAlert->proyecto." ";
              $select_proy = DB::select("SELECT proyecto_name,token_proyecto FROM module_proyectos WHERE id = ?", [$valAlert->proyecto]);
              $txt_proyecto = $JwtAuth->desencriptar($select_proy[0]->proyecto_name);

              $txt_tarea = "";
              if ($valAlert->tarea != "") {
                $select_tar = DB::select("SELECT tarea_nombre FROM module_proyectos_tareas WHERE id = ?", [$valAlert->tarea]);
                $txt_tarea = $JwtAuth->desencriptar($select_tar[0]->tarea_nombre);
              }

              $txt_informe = "";
              if ($valAlert->informe != "") {
                $select_inf = DB::select("SELECT folio_informe,post_folio_informe FROM module_proyectos_informes WHERE id = ?", [$valAlert->informe]);
                if ($select_inf[0]->post_folio_informe == NULL) {
                  $txt_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe);
                } else {
                  $txt_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe) . "-" . $select_inf[0]->post_folio_informe;
                }
              }

              if ($valAlert->tarea == "" && $valAlert->informe == "") {
                $mensaje = $txt_proyecto . ": " . $titulo_alerta;
                $link_detalle = "/sos_inside/bitacora_proyectos";
              } else if ($valAlert->tarea != "" && $valAlert->informe == "") {
                $mensaje = "Actualización de proyecto " . $txt_proyecto . ", Tarea: " . $txt_tarea . ", " . $titulo_alerta;
                $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $select_proy[0]->token_proyecto;
              } else if ($valAlert->tarea != "" && $valAlert->informe != "") {
                $mensaje = "Actualización de proyecto " . $txt_proyecto . " y tarea " . $txt_tarea . ", " . $titulo_alerta . " con folio " . $txt_informe;
                $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $select_proy[0]->token_proyecto;
              }
            } else {

              /*{path:'ingresos_catalogodemercancias',component: ListaProdIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_descuentos/:tknProducto',component: DescuentosMercComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_promociones/:tknProducto',component: PromocionesMercComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_kardex/:tknProducto',component: KardexMercComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodeservicios',component: ListaServIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_servicios_perfil/:tknServicio',component: DetalleServComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodedescuentos',component: ListaDescuentosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodepromociones',component: ListaPromocionesIngresosComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodeimpuestos',component: ListaImpuestosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeimpuestos',component: AltaImpuestosIngresosComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodeclientes',component: ListaClientesIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeclientes',component: AltaClientesIngresosComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_altadeopedidos',component: AltaPedidosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeventas',component: AltaVentasIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_seguimientodeventas',component: SeguimientoVentasComponent,canActivate:[AuthGuardService]},

              	//egresos
              		//catalogos
              			{path:'egresos_catalogodeproductos',component: ListaProdEgresosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_perfil/:tknProducto',component: PerfilGeneralesComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_almacen/:tknProducto',component: PerfilAlmacenComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_kardex/:tknProducto',component: PerfilKardexComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeservicios_perfil/:tknServicio',component: DetalleServEgresosComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeactivosfijos',component: ListaActivoFijoEgresosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeactivosintangibles',component: ListaActivoIntangibleEgresosComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeproveedores_perfil/:tknProveedor',component: DetalleProvComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeestablecimientos_perfil/:tknEstablecimiento',component: DetalleEstablecimientoComponent,canActivate:[AuthGuardService]},

              		//compras
              			{path:'egresos_catalogoderequisiciones',component: ListaRequisicionComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodecotizaciones',component: ListaCotizacionComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_altade_erogacionesygastos',component: SeguimientoComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_altadecompras',component: AltaComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras',component: SeguimientoComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras_prorrateos/:tknPrort',component: ProrrateosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras_recepcion/:tknCompra',component: RecibeCompraComponent,canActivate:[AuthGuardService]},

              	//tesoreria
              		//catalogos
              			//cuentas
              				{path:'tesoreria_catalogodecuentasbancarias',component: ListaCuentasTesoreriaComponent,canActivate:[AuthGuardService]},
              				{path:'tesoreria_cuentasbancarias_perfil/:tknCuenta',component: DetalleCuentasTesoreriaComponent,canActivate:[AuthGuardService]},
              			//cajas
              				{path:'tesoreria_catalogodecajas',component: ListaCajasTesoreriaComponent,canActivate:[AuthGuardService]},
              			//monederos
              				{path:'tesoreria_catalogodemonederos_electronicos',component: ListaMonederoTesoreriaComponent,canActivate:[AuthGuardService]},
              			//dispositivos
              				{path:'tesoreria_catalogodedispositivos',component: ListaDevicesTesoreriaComponent,canActivate:[AuthGuardService]},
              		//movimientos bancarios
              		//movimiwentos en efectivo
              		//ordenes de pagos
              			{path:'tesoreria_catalogodeordenesdepagocompras_detalle/:token_ordenPago',component: DetalleOrdenPagoTesoreriaComponent,canActivate:[AuthGuardService]},

              			{path:'tesoreria_catalogodeordenesdepagoventas',component: ListaOrdenesPagoVentasComponent,canActivate:[AuthGuardService]},
              		//ajustes
              		//info bancaria

              	//valor humano
              	//contabilidad
              		//catalogo de cuentas
              			{path:'contabilidad_catalogodecuentas',component: CatalogoCuentasComponent,canActivate:[AuthGuardService]},
              	//tecnologiass de la informacion
              		{path:'soporte_sos',component: SoporteComponent,canActivate:[AuthGuardService]},
                                          */

              if ($valAlert->area == 1) {
                $css_mensaje = "ingresos";
                $tipo_mensaje = "Ingresos y cuentas por cobrar";
              }

              if ($valAlert->area == 2) {
                $css_mensaje = "egresos";
                $tipo_mensaje = "Egresos y cuentas por pagar";
              }

              if ($valAlert->area == 3) {
                $css_mensaje = "finanzas";
                $tipo_mensaje = "Finanzas";
              }

              if ($valAlert->area == 4) {
                $css_mensaje = "vHumano";
                $tipo_mensaje = "Valor humano";
              }

              if ($valAlert->area == 5) {
                $css_mensaje = "contabilidad";
                $tipo_mensaje = "Contabilidad";
              }

              if ($valAlert->area == 6) {
                $css_mensaje = "tecInformacion";
                $tipo_mensaje = "Tecnologías de la información";
              }

              $selectSubArea = DB::select(
                "SELECT accion FROM rutas_acceso WHERE id_ruta_acceso = ?",
                [$valAlert->subarea]
              );

              if ($valAlert->producto != NULL) {
                $prodList = DB::table("catalogo_productos AS catprod")
                  ->where(['catprod.id' => $valAlert->producto,])->get();

                if ($prodList[0]->post_folio == NULL) {
                  $folio = 'PROD-' . $JwtAuth->generarFolio($prodList[0]->folio_sistema);
                } else {
                  $folio = 'PROD-' . $JwtAuth->generarFolio($prodList[0]->folio_sistema) . "-" . $prodList[0]->post_folio;
                }
              }

              if ($valAlert->servicio != NULL) {
                //echo $valAlert->servicio.":"; 
                $servList = DB::select(
                  "SELECT folio_sistema,post_folio FROM catalogo_servicios WHERE id = ?",
                  [$valAlert->servicio,]
                );

                if ($servList[0]->post_folio == NULL) {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($servList[0]->folio_sistema);
                } else {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($servList[0]->folio_sistema) . "-" . $servList[0]->post_folio;
                }
              }

              if ($valAlert->cliente != NULL) {
                $klientList = DB::table("catalogo_clientes AS catclient")
                  ->where(['catclient.id' => $valAlert->cliente,])->get();

                if ($klientList[0]->post_folio == NULL) {
                  $folio = 'CLT-' . $JwtAuth->generarFolio($klientList[0]->folio);
                } else {
                  $folio = 'CLT-' . $JwtAuth->generarFolio($klientList[0]->folio) . "-" . $klientList[0]->post_folio;
                }
              }

              if ($valAlert->proveedor != NULL) {
                $provList = DB::table("catalogo_proveedores AS catprov")
                  ->where(['catprov.id' => $valAlert->proveedor,])->get();

                if ($provList[0]->post_folio == NULL) {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($provList[0]->folio);
                } else {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($provList[0]->folio) . "-" . $provList[0]->post_folio;
                }
              }

              $mensaje = $selectSubArea[0]->accion . " - " . $titulo_alerta . " " . $folio;
            }

            $contenido = '<html><head><style>div{width:100%;display:flex;flex-wrap:wrap;flex-direction:column;justify-content:flex-start;align-items:flex-start;} 
                                div h4{width:100%;background-color:#353553;text-align:center;color:#fff;border-radius:8px} 
                                div p{width:100%;color:#353553;text-align:center;display:flex;justify-content:center;align-items:center;}</style>
                                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
                            </head><body><div><h4>' . $emisor . '</h4><p>' . $mensaje . '</p></div></body></html>';

            $each = array(
              "token_notificacion" => $token_notificacion,
              "token_emisor" => $token_emisor,
              "css_mensaje" => $css_mensaje,
              "tipo_mensaje" => $tipo_mensaje,
              "emisor" => $emisor,
              "mensaje" => $mensaje,
              "contenido" => $contenido,
              "view" => $visto,
              "link_detalle" => $link_detalle,
            );
            $arrayAlertas[] = $each;
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'total' => count($arrayAlertas),
            'alertas' => $arrayAlertas,
          );
        } else {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'alertas' => $arrayAlertas,
            'respuesta' => 'no tienes notificaciones pendientes',
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

  public function listaNotificacionesAll(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayAlertas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'string',
        //'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        //$usuario = $JwtAuth->checkToken($parametrosArray['user_token'],true); 
        if ($parametrosArray['user_token'] != "") {
          $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);
          $not_empr = $usuario->emp_token;
          $not_user = $usuario->user_token;
        } else {
          $not_empr = "bkdERG1KRUF2Ui9IdnNTUkcxSXJQNytmbHlFclQwc2RXMWw0SGlvWndSTnp3N3NYNUJGUlVQVFNscTUrYnVROG4zQW96UDlEWnJMWUR0MU1RNklxa0I5M0pqOW8xanhaZFM3b3E3Q29ROWFiR0tSZm4vb2psbkx0REZwNzNlQk9jVUNxWjM1Wnp6c3FOa0t6STNUcUFnRTI4dkdMNVNGclNSQmpqeVRRTUc5VlgyWVFhMzZxQWF0QlpLMWgxem5BZ1ZkV0ovYVMrL0kvYjRIWlV6WElZdlNGbUdxdEFhdUtqdjlmbkFxcG1qcXVsK05BQVBHNytPaG9nQ2RCazA2US9zV0hBa2JLTnVXK0poWUhsU1JpYlE9PTo6MTIzNDU2NzgxMjM0NTY3OA==";
          $not_user = "ZnRNZzFSSUQ1OE1VM0hYNkxZTjEyQT09OjoxMjM0NTY3ODEyMzQ1Njc4";
        }


        //$tareaList = DB::table("module_proyectos_informes")->count();NotificacionesModelo
        /*$alertaList = DB::select("SELECT alert.visto,alert.token_notificacion,alert.titulo,alert.proyecto,
                    alert.tarea,alert.informe,
                    emisor.pers_token,pers_people.paterno,pers_people.materno,pers_people.nombre 
                    
                    FROM teci_notificaciones AS alert
                    JOIN main_empresas AS emp
                    JOIN vhum_personal AS emisor 
                    JOIN sos_personas AS pers_people 
                    JOIN vhum_personal AS receptor 
                    JOIN main_usuarios As users
                    WHERE alert.empresa	= emp.id AND emp.emp_token = ? AND alert.emisor = emisor.id AND 
                    emisor.personal = pers_people.id
                    AND alert.receptor = receptor.id AND receptor.usuario = users.id AND users.user_token = ?
                    AND alert.status_recibe = FALSE	AND alert.status_delete = TRUE",[$usuario->emp_token,$usuario->user_token]);*/

        /*$lista = NotificacionesModelo::join("empresas AS emp","alert.empresa","=","emp.id")
                ->join("personal AS receptor","alert.receptor","=","receptor.id")
                ->join("usuarios As users","receptor.usuario","=","users.id")
                ->where([
                    'alert.status_recibe' => FALSE,
                    'alert.status_delete' => TRUE,
                    'emp.emp_token' => $usuario->emp_token,
                    'users.user_token' => $usuario->user_token,
                ])
                ->where(["alert.proyecto","!=","NULL"])
                ->orwhere(["alert.proyecto","=","NULL"])
                ->orderBy('alert.id','DESC')->get();*/

        $lista = DB::select(
          "SELECT * FROM teci_notificaciones AS alert INNER JOIN main_empresas AS emp
                    ON alert.empresa = emp.id INNER JOIN vhum_personal AS receptor ON alert.receptor = receptor.id 
                    INNER JOIN main_usuarios AS users ON receptor.usuario = users.id 
                    WHERE alert.status_recibe = FALSE AND alert.status_delete = TRUE and emp.emp_token = ? 
                    AND users.user_token = ?
                    AND ((alert.proyecto IS NOT NULL AND alert.area IS NULL AND alert.subarea IS NULL 
                        AND	alert.producto IS NULL AND alert.servicio IS NULL AND alert.clave_serv IS NULL 
                        AND	alert.cliente IS NULL AND alert.proveedor IS NULL 
                        AND alert.proyecto IN (SELECT id FROM module_proyectos)) 
                        OR (alert.proyecto IS NULL AND alert.area IS NOT NULL AND alert.subarea IS NOT NULL 
                        AND	alert.producto IS NOT NULL AND alert.servicio IS NOT NULL 
                        AND alert.clave_serv IS NOT NULL AND alert.cliente IS NOT NULL 
                        AND alert.proveedor IS NOT NULL)) ORDER BY alert.id DESC",
          [$usuario->emp_token, $usuario->user_token]
        );

        if (count($lista) != 0) {
          foreach ($lista as $valAlert) {
            $token_notificacion = $valAlert->token_notificacion;
            $fecha_notificacion = date('d-m-Y H:i:s', $valAlert->fecha_notificacion);
            $titulo_alerta = $JwtAuth->desencriptar($valAlert->titulo);
            $link_detalle = "";
            //control

            //area
            //subarea
            //producto
            //servicio
            //clave_serv
            //cliente
            //proveedor
            //empresa
            //emisor
            //receptor
            //visto
            //status_recibe
            //fecha_lectura
            //status_delete
            //fecha_delete

            $sql_emisor = NotificacionesModelo::join("vhum_personal AS emisor", "alert.emisor", "=", "emisor.id")
              ->join("sos_personas AS pers_people", "emisor.personal", "=", "pers_people.id")
              ->where(['alert.token_notificacion' => $token_notificacion,])->get();

            $token_emisor = $sql_emisor[0]->pers_token;
            $emisor = $JwtAuth->desencriptar($sql_emisor[0]->paterno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->materno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->nombre);

            $mensaje = "";
            $contenido = "";

            if ($valAlert->visto == TRUE) {
              $visto = true;
            } else {
              $visto = false;
            }

            $select_proy = DB::select("SELECT proyecto_name,token_proyecto FROM module_proyectos WHERE id = ?", [$valAlert->proyecto]);

            if (count($select_proy) != 0) {
              $css_mensaje = "proyectos";
              $tipo_mensaje = "Gestión de proyectos";
              //echo $valAlert->proyecto." ";
              $select_proy = DB::select("SELECT proyecto_name,token_proyecto FROM module_proyectos WHERE id = ?", [$valAlert->proyecto]);
              $txt_proyecto = $JwtAuth->desencriptar($select_proy[0]->proyecto_name);

              $txt_tarea = "";
              if ($valAlert->tarea != "") {
                $select_tar = DB::select("SELECT tarea_nombre FROM module_proyectos_tareas WHERE id = ?", [$valAlert->tarea]);
                $txt_tarea = $JwtAuth->desencriptar($select_tar[0]->tarea_nombre);
              }

              $txt_informe = "";
              if ($valAlert->informe != "") {
                //echo $valAlert->informe." ";
                $select_inf = DB::select("SELECT folio_informe,post_folio_informe FROM module_proyectos_informes WHERE id = ?", [$valAlert->informe]);
                if ($select_inf[0]->post_folio_informe == NULL) {
                  $txt_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe);
                } else {
                  $txt_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe) . "-" . $select_inf[0]->post_folio_informe;
                }
              }

              if ($valAlert->tarea == "" && $valAlert->informe == "") {
                $mensaje = $txt_proyecto . ": " . $titulo_alerta;
                $link_detalle = "/sos_inside/bitacora_proyectos";
              } else if ($valAlert->tarea != "" && $valAlert->informe == "") {
                $mensaje = "Actualización de proyecto " . $txt_proyecto . ", Tarea: " . $txt_tarea . ", " . $titulo_alerta;
                $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $select_proy[0]->token_proyecto;
              } else if ($valAlert->tarea != "" && $valAlert->informe != "") {
                $mensaje = "Actualización de proyecto " . $txt_proyecto . " y tarea " . $txt_tarea . ", " . $titulo_alerta . " con folio " . $txt_informe;
                $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $select_proy[0]->token_proyecto;
              }
            } else {

              /*{path:'ingresos_catalogodemercancias',component: ListaProdIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_descuentos/:tknProducto',component: DescuentosMercComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_promociones/:tknProducto',component: PromocionesMercComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_kardex/:tknProducto',component: KardexMercComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodeservicios',component: ListaServIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_servicios_perfil/:tknServicio',component: DetalleServComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodedescuentos',component: ListaDescuentosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodepromociones',component: ListaPromocionesIngresosComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodeimpuestos',component: ListaImpuestosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeimpuestos',component: AltaImpuestosIngresosComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodeclientes',component: ListaClientesIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeclientes',component: AltaClientesIngresosComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_altadeopedidos',component: AltaPedidosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeventas',component: AltaVentasIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_seguimientodeventas',component: SeguimientoVentasComponent,canActivate:[AuthGuardService]},

              	//egresos
              		//catalogos
              			{path:'egresos_catalogodeproductos',component: ListaProdEgresosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_perfil/:tknProducto',component: PerfilGeneralesComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_almacen/:tknProducto',component: PerfilAlmacenComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_kardex/:tknProducto',component: PerfilKardexComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeservicios_perfil/:tknServicio',component: DetalleServEgresosComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeactivosfijos',component: ListaActivoFijoEgresosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeactivosintangibles',component: ListaActivoIntangibleEgresosComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeproveedores_perfil/:tknProveedor',component: DetalleProvComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeestablecimientos_perfil/:tknEstablecimiento',component: DetalleEstablecimientoComponent,canActivate:[AuthGuardService]},

              		//compras
              			{path:'egresos_catalogoderequisiciones',component: ListaRequisicionComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodecotizaciones',component: ListaCotizacionComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_altade_erogacionesygastos',component: SeguimientoComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_altadecompras',component: AltaComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras',component: SeguimientoComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras_prorrateos/:tknPrort',component: ProrrateosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras_recepcion/:tknCompra',component: RecibeCompraComponent,canActivate:[AuthGuardService]},

              	//tesoreria
              		//catalogos
              			//cuentas
              				{path:'tesoreria_catalogodecuentasbancarias',component: ListaCuentasTesoreriaComponent,canActivate:[AuthGuardService]},
              				{path:'tesoreria_cuentasbancarias_perfil/:tknCuenta',component: DetalleCuentasTesoreriaComponent,canActivate:[AuthGuardService]},
              			//cajas
              				{path:'tesoreria_catalogodecajas',component: ListaCajasTesoreriaComponent,canActivate:[AuthGuardService]},
              			//monederos
              				{path:'tesoreria_catalogodemonederos_electronicos',component: ListaMonederoTesoreriaComponent,canActivate:[AuthGuardService]},
              			//dispositivos
              				{path:'tesoreria_catalogodedispositivos',component: ListaDevicesTesoreriaComponent,canActivate:[AuthGuardService]},
              		//movimientos bancarios
              		//movimiwentos en efectivo
              		//ordenes de pagos
              			{path:'tesoreria_catalogodeordenesdepagocompras_detalle/:token_ordenPago',component: DetalleOrdenPagoTesoreriaComponent,canActivate:[AuthGuardService]},

              			{path:'tesoreria_catalogodeordenesdepagoventas',component: ListaOrdenesPagoVentasComponent,canActivate:[AuthGuardService]},
              		//ajustes
              		//info bancaria

              	//valor humano
              	//contabilidad
              		//catalogo de cuentas
              			{path:'contabilidad_catalogodecuentas',component: CatalogoCuentasComponent,canActivate:[AuthGuardService]},
              	//tecnologiass de la informacion
              		{path:'soporte_sos',component: SoporteComponent,canActivate:[AuthGuardService]},
                            */

              if ($valAlert->area == 1) {
                $css_mensaje = "ingresos";
                $tipo_mensaje = "Ingresos y cuentas por cobrar";
              }

              if ($valAlert->area == 2) {
                $css_mensaje = "egresos";
                $tipo_mensaje = "Egresos y cuentas por pagar";
              }

              if ($valAlert->area == 3) {
                $css_mensaje = "finanzas";
                $tipo_mensaje = "Finanzas";
              }

              if ($valAlert->area == 4) {
                $css_mensaje = "vHumano";
                $tipo_mensaje = "Valor humano";
              }

              if ($valAlert->area == 5) {
                $css_mensaje = "contabilidad";
                $tipo_mensaje = "Contabilidad";
              }

              if ($valAlert->area == 6) {
                $css_mensaje = "tecInformacion";
                $tipo_mensaje = "Tecnologías de la información";
              }

              $selectSubArea = DB::select(
                "SELECT accion FROM rutas_acceso WHERE id_ruta_acceso = ?",
                [$valAlert->subarea]
              );

              if ($valAlert->producto != NULL) {
                $prodList = DB::table("catalogo_productos AS catprod")
                  ->where(['catprod.id' => $valAlert->producto,])->get();

                if ($prodList[0]->post_folio == NULL) {
                  $folio = 'PROD-' . $JwtAuth->generarFolio($prodList[0]->folio_sistema);
                } else {
                  $folio = 'PROD-' . $JwtAuth->generarFolio($prodList[0]->folio_sistema) . "-" . $prodList[0]->post_folio;
                }
              }

              if ($valAlert->servicio != NULL) {
                //echo $valAlert->servicio.":"; 
                $servList = DB::select(
                  "SELECT folio_sistema,post_folio FROM catalogo_servicios WHERE id = ?",
                  [$valAlert->servicio,]
                );

                if ($servList[0]->post_folio == NULL) {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($servList[0]->folio_sistema);
                } else {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($servList[0]->folio_sistema) . "-" . $servList[0]->post_folio;
                }
              }

              if ($valAlert->cliente != NULL) {
                $klientList = DB::table("catalogo_clientes AS catclient")
                  ->where(['catclient.id' => $valAlert->cliente,])->get();

                if ($klientList[0]->post_folio == NULL) {
                  $folio = 'CLT-' . $JwtAuth->generarFolio($klientList[0]->folio);
                } else {
                  $folio = 'CLT-' . $JwtAuth->generarFolio($klientList[0]->folio) . "-" . $klientList[0]->post_folio;
                }
              }

              if ($valAlert->proveedor != NULL) {
                $provList = DB::table("catalogo_proveedores AS catprov")
                  ->where(['catprov.id' => $valAlert->proveedor,])->get();

                if ($provList[0]->post_folio == NULL) {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($provList[0]->folio);
                } else {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($provList[0]->folio) . "-" . $provList[0]->post_folio;
                }
              }

              $mensaje = $selectSubArea[0]->accion . " - " . $titulo_alerta . " " . $folio;
            }

            $contenido = '<html><head><style>div{width:100%;display:flex;flex-wrap:wrap;flex-direction:column;justify-content:flex-start;align-items:flex-start;} 
                                div h4{width:100%;background-color:#353553;text-align:center;color:#fff;border-radius:8px} 
                                div p{width:100%;color:#353553;text-align:center;display:flex;justify-content:center;align-items:center;}</style>
                                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
                            </head><body><div><h4>' . $emisor . '</h4><p>' . $mensaje . '</p></div></body></html>';

            $each = array(
              "token_notificacion" => $token_notificacion,
              "token_emisor" => $token_emisor,
              "css_mensaje" => $css_mensaje,
              "tipo_mensaje" => $tipo_mensaje,
              "emisor" => $emisor,
              "mensaje" => $mensaje,
              "contenido" => $contenido,
              "view" => $visto,
              "link_detalle" => $link_detalle,
            );
            $arrayAlertas[] = $each;
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'total' => count($arrayAlertas),
            'alertas' => $arrayAlertas,
          );
        } else {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'alertas' => $arrayAlertas,
            'respuesta' => 'no tienes notificaciones pendientes',
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

  public function listaMinNotificaciones(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayAlertas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
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
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $lista = NotificacionesModelo::join("empresas AS emp", "alert.empresa", "=", "emp.id")
          ->join("personal AS receptor", "alert.receptor", "=", "receptor.id")
          ->join("usuarios As users", "receptor.usuario", "=", "users.id")
          ->where([
            'alert.status_recibe' => FALSE,
            'alert.status_delete' => TRUE,
            'emp.emp_token' => $usuario->emp_token,
            'users.user_token' => $usuario->user_token,
          ])->orderBy('alert.id', 'DESC')->limit(5)->get();

        if (count($lista) != 0) {
          foreach ($lista as $valAlert) {
            $token_notificacion = $valAlert->token_notificacion;
            $fecha_notificacion = date('d-m-Y H:i:s', $valAlert->fecha_notificacion);
            $titulo_alerta = $JwtAuth->desencriptar($valAlert->titulo);
            $link_detalle = "";
            //control

            //area
            //subarea
            //producto
            //servicio
            //clave_serv
            //cliente
            //proveedor
            //empresa
            //emisor
            //receptor
            //visto
            //status_recibe
            //fecha_lectura
            //status_delete
            //fecha_delete

            $sql_emisor = NotificacionesModelo::join("vhum_personal AS emisor", "alert.emisor", "=", "emisor.id")
              ->join("sos_personas AS pers_people", "emisor.personal", "=", "pers_people.id")
              ->where(['alert.token_notificacion' => $token_notificacion,])->get();

            $token_emisor = $sql_emisor[0]->pers_token;
            $emisor = $JwtAuth->desencriptar($sql_emisor[0]->paterno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->materno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->nombre);

            $mensaje = "";
            $contenido = "";

            if ($valAlert->visto == TRUE) {
              $visto = true;
            } else {
              $visto = false;
            }

            if ($valAlert->proyecto != NULL) {
              $css_mensaje = "proyectos";
              $tipo_mensaje = "Gestión de proyectos";

              $select_proy = DB::select("SELECT proyecto_name,token_proyecto FROM module_proyectos WHERE id = ?", [$valAlert->proyecto]);
              $txt_proyecto = $JwtAuth->desencriptar($select_proy[0]->proyecto_name);

              $txt_tarea = "";
              if ($valAlert->tarea != "") {
                $select_tar = DB::select("SELECT tarea_nombre FROM module_proyectos_tareas WHERE id = ?", [$valAlert->tarea]);
                $txt_tarea = $JwtAuth->desencriptar($select_tar[0]->tarea_nombre);
              }

              $txt_informe = "";
              if ($valAlert->informe != "") {
                $select_inf = DB::select("SELECT folio_informe,post_folio_informe FROM module_proyectos_informes WHERE id = ?", [$valAlert->informe]);
                if ($select_inf[0]->post_folio_informe == NULL) {
                  $txt_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe);
                } else {
                  $txt_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe) . "-" . $select_inf[0]->post_folio_informe;
                }
              }

              if ($valAlert->tarea == "" && $valAlert->informe == "") {
                $mensaje = $txt_proyecto . ": " . $titulo_alerta;
                $link_detalle = "/sos_inside/bitacora_proyectos";
              } else if ($valAlert->tarea != "" && $valAlert->informe == "") {
                $mensaje = "Actualización de proyecto " . $txt_proyecto . ", Tarea: " . $txt_tarea . ", " . $titulo_alerta;
                $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $select_proy[0]->token_proyecto;
              } else if ($valAlert->tarea != "" && $valAlert->informe != "") {
                $mensaje = "Actualización de proyecto " . $txt_proyecto . " y tarea " . $txt_tarea . ", " . $titulo_alerta . " con folio " . $txt_informe;
                $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $select_proy[0]->token_proyecto;
              }
            } else {

              /*{path:'ingresos_catalogodemercancias',component: ListaProdIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_descuentos/:tknProducto',component: DescuentosMercComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_promociones/:tknProducto',component: PromocionesMercComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_kardex/:tknProducto',component: KardexMercComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodeservicios',component: ListaServIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_servicios_perfil/:tknServicio',component: DetalleServComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodedescuentos',component: ListaDescuentosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodepromociones',component: ListaPromocionesIngresosComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodeimpuestos',component: ListaImpuestosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeimpuestos',component: AltaImpuestosIngresosComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_catalogodeclientes',component: ListaClientesIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeclientes',component: AltaClientesIngresosComponent,canActivate:[AuthGuardService]},

              			{path:'ingresos_altadeopedidos',component: AltaPedidosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeventas',component: AltaVentasIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_seguimientodeventas',component: SeguimientoVentasComponent,canActivate:[AuthGuardService]},

              	//egresos
              		//catalogos
              			{path:'egresos_catalogodeproductos',component: ListaProdEgresosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_perfil/:tknProducto',component: PerfilGeneralesComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_almacen/:tknProducto',component: PerfilAlmacenComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_kardex/:tknProducto',component: PerfilKardexComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeservicios_perfil/:tknServicio',component: DetalleServEgresosComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeactivosfijos',component: ListaActivoFijoEgresosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeactivosintangibles',component: ListaActivoIntangibleEgresosComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeproveedores_perfil/:tknProveedor',component: DetalleProvComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeestablecimientos_perfil/:tknEstablecimiento',component: DetalleEstablecimientoComponent,canActivate:[AuthGuardService]},

              		//compras
              			{path:'egresos_catalogoderequisiciones',component: ListaRequisicionComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodecotizaciones',component: ListaCotizacionComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_altade_erogacionesygastos',component: SeguimientoComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_altadecompras',component: AltaComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras',component: SeguimientoComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras_prorrateos/:tknPrort',component: ProrrateosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras_recepcion/:tknCompra',component: RecibeCompraComponent,canActivate:[AuthGuardService]},

              	//tesoreria
              		//catalogos
              			//cuentas
              				{path:'tesoreria_catalogodecuentasbancarias',component: ListaCuentasTesoreriaComponent,canActivate:[AuthGuardService]},
              				{path:'tesoreria_cuentasbancarias_perfil/:tknCuenta',component: DetalleCuentasTesoreriaComponent,canActivate:[AuthGuardService]},
              			//cajas
              				{path:'tesoreria_catalogodecajas',component: ListaCajasTesoreriaComponent,canActivate:[AuthGuardService]},
              			//monederos
              				{path:'tesoreria_catalogodemonederos_electronicos',component: ListaMonederoTesoreriaComponent,canActivate:[AuthGuardService]},
              			//dispositivos
              				{path:'tesoreria_catalogodedispositivos',component: ListaDevicesTesoreriaComponent,canActivate:[AuthGuardService]},
              		//movimientos bancarios
              		//movimiwentos en efectivo
              		//ordenes de pagos
              			{path:'tesoreria_catalogodeordenesdepagocompras_detalle/:token_ordenPago',component: DetalleOrdenPagoTesoreriaComponent,canActivate:[AuthGuardService]},

              			{path:'tesoreria_catalogodeordenesdepagoventas',component: ListaOrdenesPagoVentasComponent,canActivate:[AuthGuardService]},
              		//ajustes
              		//info bancaria

              	//valor humano
              	//contabilidad
              		//catalogo de cuentas
              			{path:'contabilidad_catalogodecuentas',component: CatalogoCuentasComponent,canActivate:[AuthGuardService]},
              	//tecnologiass de la informacion
              		{path:'soporte_sos',component: SoporteComponent,canActivate:[AuthGuardService]},
                            */

              if ($valAlert->area == 1) {
                $css_mensaje = "ingresos";
                $tipo_mensaje = "Ingresos y cuentas por cobrar";
              }

              if ($valAlert->area == 2) {
                $css_mensaje = "egresos";
                $tipo_mensaje = "Egresos y cuentas por pagar";
              }

              if ($valAlert->area == 3) {
                $css_mensaje = "finanzas";
                $tipo_mensaje = "Finanzas";
              }

              if ($valAlert->area == 4) {
                $css_mensaje = "vHumano";
                $tipo_mensaje = "Valor humano";
              }

              if ($valAlert->area == 5) {
                $css_mensaje = "contabilidad";
                $tipo_mensaje = "Contabilidad";
              }

              if ($valAlert->area == 6) {
                $css_mensaje = "tecInformacion";
                $tipo_mensaje = "Tecnologías de la información";
              }

              $selectSubArea = DB::select(
                "SELECT accion FROM rutas_acceso WHERE id_ruta_acceso = ?",
                [$valAlert->subarea]
              );

              if ($valAlert->producto != NULL) {
                $prodList = DB::table("catalogo_productos AS catprod")
                  ->where(['catprod.id' => $valAlert->producto,])->get();

                if ($prodList[0]->post_folio == NULL) {
                  $folio = 'PROD-' . $JwtAuth->generarFolio($prodList[0]->folio_sistema);
                } else {
                  $folio = 'PROD-' . $JwtAuth->generarFolio($prodList[0]->folio_sistema) . "-" . $prodList[0]->post_folio;
                }
              }

              if ($valAlert->servicio != NULL) {
                //echo $valAlert->servicio.":"; 
                $servList = DB::select(
                  "SELECT folio_sistema,post_folio FROM catalogo_servicios WHERE id = ?",
                  [$valAlert->servicio,]
                );

                if ($servList[0]->post_folio == NULL) {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($servList[0]->folio_sistema);
                } else {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($servList[0]->folio_sistema) . "-" . $servList[0]->post_folio;
                }
              }

              if ($valAlert->cliente != NULL) {
                $klientList = DB::table("catalogo_clientes AS catclient")
                  ->where(['catclient.id' => $valAlert->cliente,])->get();

                if ($klientList[0]->post_folio == NULL) {
                  $folio = 'CLT-' . $JwtAuth->generarFolio($klientList[0]->folio);
                } else {
                  $folio = 'CLT-' . $JwtAuth->generarFolio($klientList[0]->folio) . "-" . $klientList[0]->post_folio;
                }
              }

              if ($valAlert->proveedor != NULL) {
                $provList = DB::table("catalogo_proveedores AS catprov")
                  ->where(['catprov.id' => $valAlert->proveedor,])->get();

                if ($provList[0]->post_folio == NULL) {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($provList[0]->folio);
                } else {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($provList[0]->folio) . "-" . $provList[0]->post_folio;
                }
              }


              $mensaje = $selectSubArea[0]->accion . " - " . $titulo_alerta . " " . $folio;
            }

            $contenido = '<html><head><style>div{width:100%;display:flex;flex-wrap:wrap;flex-direction:column;justify-content:flex-start;align-items:flex-start;} 
                                div h4{width:100%;background-color:#353553;text-align:center;color:#fff;border-radius:8px} 
                                div p{width:100%;color:#353553;text-align:center;display:flex;justify-content:center;align-items:center;}</style>
                                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
                            </head><body><div><h4>' . $emisor . '</h4><p>' . $mensaje . '</p></div></body></html>';

            $each = array(
              "token_notificacion" => $token_notificacion,
              "token_emisor" => $token_emisor,
              "css_mensaje" => $css_mensaje,
              "tipo_mensaje" => $tipo_mensaje,
              "emisor" => $emisor,
              "mensaje" => $mensaje,
              "contenido" => $contenido,
              "view" => $visto,
              "link_detalle" => $link_detalle,
            );
            $arrayAlertas[] = $each;
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'total' => count($arrayAlertas),
            'alertas' => $arrayAlertas,
          );
        } else {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'alertas' => $arrayAlertas,
            'respuesta' => 'no tienes notificaciones pendientes',
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

  public function listaNotificacionesGestionProyectos(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayAlertas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'string',
        //'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listaNotif = NotificacionesModelo::join("module_proyectos AS proy", "alert.proyecto", "=", "proy.id")
          ->join("main_empresas AS emp", "alert.empresa", "=", "emp.id")
          ->join("vhum_personal AS receptor", "alert.receptor", "=", "receptor.id")
          ->join("main_usuarios AS users", "receptor.usuario", "=", "users.id")
          ->where([
            "emp.emp_token" => $usuario->emp_token,
            "users.user_token" => $usuario->user_token,
            "alert.status_recibe" => FALSE,
            "alert.status_delete" => TRUE,
          ])->orderBy("alert.id", "DESC")->get();

        if (count($listaNotif) != 0) {
          foreach ($listaNotif as $valAlert) {
            $token_notificacion = $valAlert->token_notificacion;
            $token_notificacion_outside = str_replace("=", "_", $valAlert->token_notificacion);
            $fecha_notificacion = date('d-m-Y H:i:s', $valAlert->fecha_notificacion);
            $titulo_alerta = $JwtAuth->desencriptar($valAlert->titulo);
            $asunto = $valAlert->asunto;
            $name_tarea = "";
            $name_informe = "";
            $link_detalle = "";
            $link_detalle_out = "";

            if ($valAlert->visto == TRUE) {
              $visto = true;
            } else {
              $visto = false;
            }

            $sql_emisor = NotificacionesModelo::join("vhum_personal AS emisor", "alert.emisor", "=", "emisor.id")
              ->join("sos_personas AS pers_people", "emisor.personal", "=", "pers_people.id")
              ->where(['alert.token_notificacion' => $token_notificacion,])->get();

            $token_emisor = $sql_emisor[0]->pers_token;
            $emisor = $JwtAuth->desencriptar($sql_emisor[0]->paterno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->materno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->nombre);

            if ($valAlert->post_folio == NULL) {
              $folio_proy = "PROY-" . $JwtAuth->generarFolio($valAlert->folio);
            } else {
              $folio_proy = "PROY-" . $JwtAuth->generarFolio($valAlert->folio) . "-" . $valAlert->post_folio;
            }
            $txt_proyecto = $folio_proy . " - " . $JwtAuth->desencriptar($valAlert->proyecto_name);

            if ($valAlert->tarea != "") {
              $select_tar = DB::select("SELECT folio_tarea,post_folio_tar,tarea_nombre FROM module_proyectos_tareas WHERE id = ?", [$valAlert->tarea]);
              if ($select_tar[0]->post_folio_tar == NULL) {
                $folio_tarea = "TAR-" . $JwtAuth->generarFolio($select_tar[0]->folio_tarea);
              } else {
                $folio_tarea = "TAR-" . $JwtAuth->generarFolio($select_tar[0]->folio_tarea) . "-" . $select_tar[0]->post_folio_tar;
              }
              $name_tarea = $folio_tarea . " - " . $JwtAuth->desencriptar($select_tar[0]->tarea_nombre);
            }

            if ($valAlert->informe != "") {
              $select_inf = DB::select("SELECT folio_informe,post_folio_informe,informe FROM module_proyectos_informes WHERE id = ?", [$valAlert->informe]);
              if ($select_inf[0]->post_folio_informe == NULL) {
                $folio_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe);
              } else {
                $folio_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe) . "-" . $select_inf[0]->post_folio_informe;
              }
              $name_informe = $folio_informe . " - " . $JwtAuth->desencriptar($select_inf[0]->informe);
            }

            if ($valAlert->tarea == "" && $valAlert->informe == "") {
              //$mensaje = $txt_proyecto.": ".$titulo_alerta;
              $link_detalle = "/sos_inside/bitacora_proyectos";
            } else if ($valAlert->tarea != "" && $valAlert->informe == "") {
              //$mensaje = "Tarea: ".$txt_tarea.", ".$titulo_alerta;
              $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $valAlert->token_proyecto;
            } else if ($valAlert->tarea != "" && $valAlert->informe != "") {
              //$mensaje = "Tarea: ".$txt_tarea.", ".$titulo_alerta;
              $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $valAlert->token_proyecto;
            }

            $link_detalle_out = "https://sos-mexico.com.mx/notificaciones_gestion_de_proyectos/" . $token_notificacion_outside;

            $each = array(
              "token_notificacion" => $token_notificacion_outside,
              "token_emisor" => $token_emisor,
              //encabezado
              "tipo_mensaje" => $txt_proyecto,
              //asunto
              "asunto" => $asunto,
              //cuerpo
              "tarea" => $name_tarea,
              "informe" => $name_informe,
              "mensaje" => $titulo_alerta,
              "emisor" => $emisor,
              //status
              "view" => $visto,
              "link_detalle" => $link_detalle,
              "link_detalle_out" => $link_detalle_out,
            );
            $arrayAlertas[] = $each;
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'total' => count($arrayAlertas),
            'alertas' => $arrayAlertas,
          );
        } else {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'alertas' => $arrayAlertas,
            'respuesta' => 'no tienes notificaciones pendientes',
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

  public function listaNotificacionesGestionProyectoZ(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $arrayAlertas = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        'user_token' => 'string',
        //'user_token' => 'required|string',
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $listaNotif = NotificacionesModelo::join("module_proyectos AS proy", "alert.proyecto", "=", "proy.id")
          ->join("main_empresas AS emp", "alert.empresa", "=", "emp.id")
          ->join("vhum_personal AS receptor", "alert.receptor", "=", "receptor.id")
          ->join("main_usuarios AS users", "receptor.usuario", "=", "users.id")
          ->where([
            "emp.emp_token" => $usuario->emp_token,
            "users.user_token" => $usuario->user_token,
            "alert.status_recibe" => FALSE,
            "alert.status_delete" => TRUE,
          ])->orderBy("alert.id", "DESC")->get();

        if (count($listaNotif) != 0) {
          foreach ($listaNotif as $valAlert) {
            $token_notificacion = $valAlert->token_notificacion;
            $fecha_notificacion = date('d-m-Y H:i:s', $valAlert->fecha_notificacion);
            $titulo_alerta = $JwtAuth->desencriptar($valAlert->titulo);
            $asunto = $valAlert->asunto;
            $name_tarea = "";
            $name_informe = "";
            $link_detalle = "";
            $link_detalle_out = "";

            if ($valAlert->visto == TRUE) {
              $visto = true;
            } else {
              $visto = false;
            }

            $sql_emisor = NotificacionesModelo::join("vhum_personal AS emisor", "alert.emisor", "=", "emisor.id")
              ->join("sos_personas AS pers_people", "emisor.personal", "=", "pers_people.id")
              ->where(['alert.token_notificacion' => $token_notificacion,])->get();

            $token_emisor = $sql_emisor[0]->pers_token;
            $emisor = $JwtAuth->desencriptar($sql_emisor[0]->paterno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->materno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->nombre);

            if ($valAlert->post_folio == NULL) {
              $folio_proy = "PROY-" . $JwtAuth->generarFolio($valAlert->folio);
            } else {
              $folio_proy = "PROY-" . $JwtAuth->generarFolio($valAlert->folio) . "-" . $valAlert->post_folio;
            }
            $txt_proyecto = $folio_proy . " - " . $JwtAuth->desencriptar($valAlert->proyecto_name);

            if ($valAlert->tarea != "") {
              $select_tar = DB::select("SELECT folio_tarea,post_folio_tar,tarea_nombre FROM module_proyectos_tareas WHERE id = ?", [$valAlert->tarea]);
              if ($select_tar[0]->post_folio_tar == NULL) {
                $folio_tarea = "TAR-" . $JwtAuth->generarFolio($select_tar[0]->folio_tarea);
              } else {
                $folio_tarea = "TAR-" . $JwtAuth->generarFolio($select_tar[0]->folio_tarea) . "-" . $select_tar[0]->post_folio_tar;
              }
              $name_tarea = $folio_tarea . " - " . $JwtAuth->desencriptar($select_tar[0]->tarea_nombre);
            }

            if ($valAlert->informe != "") {
              $select_inf = DB::select("SELECT folio_informe,post_folio_informe,informe FROM module_proyectos_informes WHERE id = ?", [$valAlert->informe]);
              if ($select_inf[0]->post_folio_informe == NULL) {
                $folio_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe);
              } else {
                $folio_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe) . "-" . $select_inf[0]->post_folio_informe;
              }
              $name_informe = $folio_informe . " - " . $JwtAuth->desencriptar($select_inf[0]->informe);
            }

            if ($valAlert->tarea == "" && $valAlert->informe == "") {
              //$mensaje = $txt_proyecto.": ".$titulo_alerta;
              $link_detalle = "/sos_inside/bitacora_proyectos";
            } else if ($valAlert->tarea != "" && $valAlert->informe == "") {
              //$mensaje = "Tarea: ".$txt_tarea.", ".$titulo_alerta;
              $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $valAlert->token_proyecto;
            } else if ($valAlert->tarea != "" && $valAlert->informe != "") {
              //$mensaje = "Tarea: ".$txt_tarea.", ".$titulo_alerta;
              $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $valAlert->token_proyecto;
            }

            $link_detalle_out = "https://sos-mexico.com.mx/notificaciones_gestion_de_proyectos/" . $token_notificacion;

            $each = array(
              "token_notificacion" => $token_notificacion,
              "token_emisor" => $token_emisor,
              //encabezado
              "tipo_mensaje" => $txt_proyecto,
              //asunto
              "asunto" => $asunto,
              //cuerpo
              "tarea" => $name_tarea,
              "informe" => $name_informe,
              "mensaje" => $titulo_alerta,
              "emisor" => $emisor,
              //status
              "view" => $visto,
              "link_detalle" => $link_detalle,
              "link_detalle_out" => $link_detalle_out,
            );
            $arrayAlertas[] = $each;
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'total' => count($arrayAlertas),
            'alertas' => $arrayAlertas,
          );
        } else {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'alertas' => $arrayAlertas,
            'respuesta' => 'no tienes notificaciones pendientes',
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

  public function ultimaNotificacion(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
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
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $lista = NotificacionesModelo::join("empresas AS emp", "alert.empresa", "=", "emp.id")
          ->join("personal AS receptor", "alert.receptor", "=", "receptor.id")
          ->join("usuarios As users", "receptor.usuario", "=", "users.id")
          ->where([
            'alert.status_recibe' => FALSE,
            'alert.status_delete' => TRUE,
            'emp.emp_token' => $usuario->emp_token,
            'users.user_token' => $usuario->user_token,
          ])->orderBy('alert.id', 'DESC')->limit(1)->get();

        if (count($lista) != 0) {
          foreach ($lista as $valAlert) {
            $token_notificacion = $valAlert->token_notificacion;
            $fecha_notificacion = date('d-m-Y H:i:s', $valAlert->fecha_notificacion);
            $titulo_alerta = $JwtAuth->desencriptar($valAlert->titulo);
            $link_detalle = "";
            //control

            //area
            //subarea
            //producto
            //servicio
            //clave_serv
            //cliente
            //proveedor
            //empresa
            //emisor
            //receptor
            //visto
            //status_recibe
            //fecha_lectura
            //status_delete
            //fecha_delete

            $sql_emisor = NotificacionesModelo::join("vhum_personal AS emisor", "alert.emisor", "=", "emisor.id")
              ->join("sos_personas AS pers_people", "emisor.personal", "=", "pers_people.id")
              ->where(['alert.token_notificacion' => $token_notificacion,])->get();

            $token_emisor = $sql_emisor[0]->pers_token;
            $emisor = $JwtAuth->desencriptar($sql_emisor[0]->paterno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->materno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->nombre);

            $mensaje = "";
            $contenido = "";

            if ($valAlert->visto == TRUE) {
              $visto = true;
            } else {
              $visto = false;
            }

            $select_proy = DB::select("SELECT proyecto_name,token_proyecto FROM module_proyectos WHERE id = ?", [$valAlert->proyecto]);

            if (count($select_proy) != 0) {
              $css_mensaje = "proyectos";
              $tipo_mensaje = "Gestión de proyectos";
              //echo $valAlert->proyecto." ";
              $select_proy = DB::select("SELECT proyecto_name,token_proyecto FROM module_proyectos WHERE id = ?", [$valAlert->proyecto]);
              $txt_proyecto = $JwtAuth->desencriptar($select_proy[0]->proyecto_name);

              $txt_tarea = "";
              if ($valAlert->tarea != "") {
                $select_tar = DB::select("SELECT tarea_nombre FROM module_proyectos_tareas WHERE id = ?", [$valAlert->tarea]);
                $txt_tarea = $JwtAuth->desencriptar($select_tar[0]->tarea_nombre);
              }

              $txt_informe = "";
              if ($valAlert->informe != "") {
                $select_inf = DB::select("SELECT folio_informe,post_folio_informe FROM module_proyectos_informes WHERE id = ?", [$valAlert->informe]);
                if ($select_inf[0]->post_folio_informe == NULL) {
                  $txt_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe);
                } else {
                  $txt_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe) . "-" . $select_inf[0]->post_folio_informe;
                }
              }

              if ($valAlert->tarea == "" && $valAlert->informe == "") {
                $mensaje = $txt_proyecto . ": " . $titulo_alerta;
                $link_detalle = "/sos_inside/bitacora_proyectos";
              } else if ($valAlert->tarea != "" && $valAlert->informe == "") {
                $mensaje = "Actualización de proyecto " . $txt_proyecto . ", Tarea: " . $txt_tarea . ", " . $titulo_alerta;
                $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $select_proy[0]->token_proyecto;
              } else if ($valAlert->tarea != "" && $valAlert->informe != "") {
                $mensaje = "Actualización de proyecto " . $txt_proyecto . " y tarea " . $txt_tarea . ", " . $titulo_alerta . " con folio " . $txt_informe;
                $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $select_proy[0]->token_proyecto;
              }
            } else {

              /*{path:'ingresos_catalogodemercancias',component: ListaProdIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_descuentos/:tknProducto',component: DescuentosMercComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_promociones/:tknProducto',component: PromocionesMercComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodemercancias_kardex/:tknProducto',component: KardexMercComponent,canActivate:[AuthGuardService]},
                          
              			{path:'ingresos_catalogodeservicios',component: ListaServIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_servicios_perfil/:tknServicio',component: DetalleServComponent,canActivate:[AuthGuardService]},
                          
              			{path:'ingresos_catalogodedescuentos',component: ListaDescuentosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_catalogodepromociones',component: ListaPromocionesIngresosComponent,canActivate:[AuthGuardService]},
                          
              			{path:'ingresos_catalogodeimpuestos',component: ListaImpuestosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeimpuestos',component: AltaImpuestosIngresosComponent,canActivate:[AuthGuardService]},
                          
              			{path:'ingresos_catalogodeclientes',component: ListaClientesIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeclientes',component: AltaClientesIngresosComponent,canActivate:[AuthGuardService]},
                          
              			{path:'ingresos_altadeopedidos',component: AltaPedidosIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_altadeventas',component: AltaVentasIngresosComponent,canActivate:[AuthGuardService]},
              			{path:'ingresos_seguimientodeventas',component: SeguimientoVentasComponent,canActivate:[AuthGuardService]},
                          
              	//egresos
              		//catalogos
              			{path:'egresos_catalogodeproductos',component: ListaProdEgresosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_perfil/:tknProducto',component: PerfilGeneralesComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_almacen/:tknProducto',component: PerfilAlmacenComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_kardex/:tknProducto',component: PerfilKardexComponent,canActivate:[AuthGuardService]},
                          
              			{path:'egresos_catalogodeservicios_perfil/:tknServicio',component: DetalleServEgresosComponent,canActivate:[AuthGuardService]},
                          
              			{path:'egresos_catalogodeactivosfijos',component: ListaActivoFijoEgresosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeactivosintangibles',component: ListaActivoIntangibleEgresosComponent,canActivate:[AuthGuardService]},
                          
              			{path:'egresos_catalogodeproveedores_perfil/:tknProveedor',component: DetalleProvComponent,canActivate:[AuthGuardService]},
                          
              			{path:'egresos_catalogodeestablecimientos_perfil/:tknEstablecimiento',component: DetalleEstablecimientoComponent,canActivate:[AuthGuardService]},
                          
              		//compras
              			{path:'egresos_catalogoderequisiciones',component: ListaRequisicionComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodecotizaciones',component: ListaCotizacionComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_altade_erogacionesygastos',component: SeguimientoComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_altadecompras',component: AltaComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras',component: SeguimientoComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras_prorrateos/:tknPrort',component: ProrrateosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras_recepcion/:tknCompra',component: RecibeCompraComponent,canActivate:[AuthGuardService]},
                          
              	//tesoreria
              		//catalogos
              			//cuentas
              				{path:'tesoreria_catalogodecuentasbancarias',component: ListaCuentasTesoreriaComponent,canActivate:[AuthGuardService]},
              				{path:'tesoreria_cuentasbancarias_perfil/:tknCuenta',component: DetalleCuentasTesoreriaComponent,canActivate:[AuthGuardService]},
              			//cajas
              				{path:'tesoreria_catalogodecajas',component: ListaCajasTesoreriaComponent,canActivate:[AuthGuardService]},
              			//monederos
              				{path:'tesoreria_catalogodemonederos_electronicos',component: ListaMonederoTesoreriaComponent,canActivate:[AuthGuardService]},
              			//dispositivos
              				{path:'tesoreria_catalogodedispositivos',component: ListaDevicesTesoreriaComponent,canActivate:[AuthGuardService]},
              		//movimientos bancarios
              		//movimiwentos en efectivo
              		//ordenes de pagos
              			{path:'tesoreria_catalogodeordenesdepagocompras_detalle/:token_ordenPago',component: DetalleOrdenPagoTesoreriaComponent,canActivate:[AuthGuardService]},
                          
              			{path:'tesoreria_catalogodeordenesdepagoventas',component: ListaOrdenesPagoVentasComponent,canActivate:[AuthGuardService]},
              		//ajustes
              		//info bancaria
                          
              	//valor humano
              	//contabilidad
              		//catalogo de cuentas
              			{path:'contabilidad_catalogodecuentas',component: CatalogoCuentasComponent,canActivate:[AuthGuardService]},
              	//tecnologiass de la informacion
              		{path:'soporte_sos',component: SoporteComponent,canActivate:[AuthGuardService]},
                            */

              if ($valAlert->area == 1) {
                $css_mensaje = "ingresos";
                $tipo_mensaje = "Ingresos y cuentas por cobrar";
              }

              if ($valAlert->area == 2) {
                $css_mensaje = "egresos";
                $tipo_mensaje = "Egresos y cuentas por pagar";
              }

              if ($valAlert->area == 3) {
                $css_mensaje = "finanzas";
                $tipo_mensaje = "Finanzas";
              }

              if ($valAlert->area == 4) {
                $css_mensaje = "vHumano";
                $tipo_mensaje = "Valor humano";
              }

              if ($valAlert->area == 5) {
                $css_mensaje = "contabilidad";
                $tipo_mensaje = "Contabilidad";
              }

              if ($valAlert->area == 6) {
                $css_mensaje = "tecInformacion";
                $tipo_mensaje = "Tecnologías de la información";
              }

              $selectSubArea = DB::select(
                "SELECT accion FROM rutas_acceso WHERE id_ruta_acceso = ?",
                [$valAlert->subarea]
              );

              if ($valAlert->producto != NULL) {
                $prodList = DB::table("catalogo_productos AS catprod")
                  ->where(['catprod.id' => $valAlert->producto,])->get();

                if ($prodList[0]->post_folio == NULL) {
                  $folio = 'PROD-' . $JwtAuth->generarFolio($prodList[0]->folio_sistema);
                } else {
                  $folio = 'PROD-' . $JwtAuth->generarFolio($prodList[0]->folio_sistema) . "-" . $prodList[0]->post_folio;
                }
              }

              if ($valAlert->servicio != NULL) {
                //echo $valAlert->servicio.":"; 
                $servList = DB::select(
                  "SELECT folio_sistema,post_folio FROM catalogo_servicios WHERE id = ?",
                  [$valAlert->servicio,]
                );

                if ($servList[0]->post_folio == NULL) {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($servList[0]->folio_sistema);
                } else {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($servList[0]->folio_sistema) . "-" . $servList[0]->post_folio;
                }
              }

              if ($valAlert->cliente != NULL) {
                $klientList = DB::table("catalogo_clientes AS catclient")
                  ->where(['catclient.id' => $valAlert->cliente,])->get();

                if ($klientList[0]->post_folio == NULL) {
                  $folio = 'CLT-' . $JwtAuth->generarFolio($klientList[0]->folio);
                } else {
                  $folio = 'CLT-' . $JwtAuth->generarFolio($klientList[0]->folio) . "-" . $klientList[0]->post_folio;
                }
              }

              if ($valAlert->proveedor != NULL) {
                $provList = DB::table("catalogo_proveedores AS catprov")
                  ->where(['catprov.id' => $valAlert->proveedor,])->get();

                if ($provList[0]->post_folio == NULL) {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($provList[0]->folio);
                } else {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($provList[0]->folio) . "-" . $provList[0]->post_folio;
                }
              }

              $mensaje = $selectSubArea[0]->accion . " - " . $titulo_alerta . " " . $folio;
            }

            $contenido = '<html><head><style>div{width:100%;display:flex;flex-wrap:wrap;flex-direction:column;justify-content:flex-start;align-items:flex-start;} 
                                div h4{width:100%;background-color:#353553;text-align:center;color:#fff;border-radius:8px} 
                                div p{width:100%;color:#353553;text-align:center;display:flex;justify-content:center;align-items:center;}</style>
                                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
                            </head><body><div><h4>' . $emisor . '</h4><p>' . $mensaje . '</p></div></body></html>';

            $dataMensaje = array(
              "status" => 'success',
              "code" => 200,
              "token_notificacion" => $token_notificacion,
              "token_emisor" => $token_emisor,
              "css_mensaje" => $css_mensaje,
              "tipo_mensaje" => $tipo_mensaje,
              "emisor" => $emisor,
              "mensaje" => $mensaje,
              "contenido" => $contenido,
              "link_detalle" => $link_detalle,
              "view" => $visto,
            );
          }
        } else {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'alertas' => $arrayAlertas,
            'respuesta' => 'no tienes notificaciones pendientes',
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

  public function detalleNotificacionInside(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $detalleNotif = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_notificacion" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray['user_token'], true);

        $lista = NotificacionesModelo::join("empresas AS emp", "alert.empresa", "=", "emp.id")
          ->join("personal AS receptor", "alert.receptor", "=", "receptor.id")
          ->join("usuarios As users", "receptor.usuario", "=", "users.id")
          ->where([
            "alert.token_notificacion" => $parametrosArray['token_notificacion'],
            "alert.status_recibe" => FALSE,
            "alert.status_delete" => TRUE,
            "emp.emp_token" => $usuario->emp_token,
            "users.user_token" => $usuario->user_token,
          ])->get();

        if (count($lista) != 0) {
          foreach ($lista as $valAlert) {
            $token_notificacion = $valAlert->token_notificacion;
            $fecha_notificacion = date('d-m-Y H:i:s', $valAlert->fecha_notificacion);
            $titulo_alerta = $JwtAuth->desencriptar($valAlert->titulo);
            $link_detalle = "";
            //control

            //area
            //subarea
            //producto
            //servicio
            //clave_serv
            //cliente
            //proveedor
            //empresa
            //emisor
            //receptor
            //visto
            //status_recibe
            //fecha_lectura
            //status_delete
            //fecha_delete

            $sql_emisor = NotificacionesModelo::join("vhum_personal AS emisor", "alert.emisor", "=", "emisor.id")
              ->join("sos_personas AS pers_people", "emisor.personal", "=", "pers_people.id")
              ->where(['alert.token_notificacion' => $token_notificacion,])->get();

            $token_emisor = $sql_emisor[0]->pers_token;
            $emisor = $JwtAuth->desencriptar($sql_emisor[0]->paterno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->materno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->nombre);

            $mensaje = "";
            $contenido = "";

            if ($valAlert->visto == TRUE) {
              $visto = true;
            } else {
              $visto = false;
            }

            if ($valAlert->proyecto != NULL) {
              $css_mensaje = "proyectos";
              $tipo_mensaje = "Gestión de proyectos";

              $select_proy = DB::select("SELECT proyecto_name,token_proyecto FROM module_proyectos WHERE id = ?", [$valAlert->proyecto]);
              $txt_proyecto = $JwtAuth->desencriptar($select_proy[0]->proyecto_name);

              $txt_tarea = "";
              if ($valAlert->tarea != "") {
                $select_tar = DB::select("SELECT tarea_nombre FROM module_proyectos_tareas WHERE id = ?", [$valAlert->tarea]);
                $txt_tarea = $JwtAuth->desencriptar($select_tar[0]->tarea_nombre);
              }

              $txt_informe = "";
              if ($valAlert->informe != "") {
                $select_inf = DB::select("SELECT folio_informe,post_folio_informe FROM module_proyectos_informes WHERE id = ?", [$valAlert->informe]);
                if ($select_inf[0]->post_folio_informe == NULL) {
                  $txt_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe);
                } else {
                  $txt_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe) . "-" . $select_inf[0]->post_folio_informe;
                }
              }

              if ($valAlert->tarea == "" && $valAlert->informe == "") {
                $mensaje = $txt_proyecto . ": " . $titulo_alerta;
                $link_detalle = "/sos_inside/sos_inside/bitacora_proyectos";
              } else if ($valAlert->tarea != "" && $valAlert->informe == "") {
                $mensaje = "Actualización de proyecto " . $txt_proyecto . ", Tarea: " . $txt_tarea . ", " . $titulo_alerta;
                $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $select_proy[0]->token_proyecto;
              } else if ($valAlert->tarea != "" && $valAlert->informe != "") {
                $mensaje = "Actualización de proyecto " . $txt_proyecto . " y tarea " . $txt_tarea . ", " . $titulo_alerta . " con folio " . $txt_informe;
                $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $select_proy[0]->token_proyecto;
              }
            } else {    

              /*{path:'ingresos_catalogodemercancias',component: ListaProdIngresosComponent,canActivate:[AuthGuardService]},
          			{path:'ingresos_catalogodemercancias_descuentos/:tknProducto',component: DescuentosMercComponent,canActivate:[AuthGuardService]},
          			{path:'ingresos_catalogodemercancias_promociones/:tknProducto',component: PromocionesMercComponent,canActivate:[AuthGuardService]},
          			{path:'ingresos_catalogodemercancias_kardex/:tknProducto',component: KardexMercComponent,canActivate:[AuthGuardService]},
                      
          			{path:'ingresos_catalogodeservicios',component: ListaServIngresosComponent,canActivate:[AuthGuardService]},
          			{path:'ingresos_servicios_perfil/:tknServicio',component: DetalleServComponent,canActivate:[AuthGuardService]},
                      
          			{path:'ingresos_catalogodedescuentos',component: ListaDescuentosIngresosComponent,canActivate:[AuthGuardService]},
          			{path:'ingresos_catalogodepromociones',component: ListaPromocionesIngresosComponent,canActivate:[AuthGuardService]},
                      
          			{path:'ingresos_catalogodeimpuestos',component: ListaImpuestosIngresosComponent,canActivate:[AuthGuardService]},
          			{path:'ingresos_altadeimpuestos',component: AltaImpuestosIngresosComponent,canActivate:[AuthGuardService]},
                      
          			{path:'ingresos_catalogodeclientes',component: ListaClientesIngresosComponent,canActivate:[AuthGuardService]},
          			{path:'ingresos_altadeclientes',component: AltaClientesIngresosComponent,canActivate:[AuthGuardService]},
                      
          			{path:'ingresos_altadeopedidos',component: AltaPedidosIngresosComponent,canActivate:[AuthGuardService]},
          			{path:'ingresos_altadeventas',component: AltaVentasIngresosComponent,canActivate:[AuthGuardService]},
          			{path:'ingresos_seguimientodeventas',component: SeguimientoVentasComponent,canActivate:[AuthGuardService]},
                      
              	//egresos
              		//catalogos
              			{path:'egresos_catalogodeproductos',component: ListaProdEgresosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_perfil/:tknProducto',component: PerfilGeneralesComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_almacen/:tknProducto',component: PerfilAlmacenComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeproductos_kardex/:tknProducto',component: PerfilKardexComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeservicios_perfil/:tknServicio',component: DetalleServEgresosComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeactivosfijos',component: ListaActivoFijoEgresosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodeactivosintangibles',component: ListaActivoIntangibleEgresosComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeproveedores_perfil/:tknProveedor',component: DetalleProvComponent,canActivate:[AuthGuardService]},

              			{path:'egresos_catalogodeestablecimientos_perfil/:tknEstablecimiento',component: DetalleEstablecimientoComponent,canActivate:[AuthGuardService]},

              		//compras
              			{path:'egresos_catalogoderequisiciones',component: ListaRequisicionComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_catalogodecotizaciones',component: ListaCotizacionComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_altade_erogacionesygastos',component: SeguimientoComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_altadecompras',component: AltaComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras',component: SeguimientoComprasComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras_prorrateos/:tknPrort',component: ProrrateosComponent,canActivate:[AuthGuardService]},
              			{path:'egresos_seguimientodecompras_recepcion/:tknCompra',component: RecibeCompraComponent,canActivate:[AuthGuardService]},

              	//tesoreria
              		//catalogos
              			//cuentas
              				{path:'tesoreria_catalogodecuentasbancarias',component: ListaCuentasTesoreriaComponent,canActivate:[AuthGuardService]},
              				{path:'tesoreria_cuentasbancarias_perfil/:tknCuenta',component: DetalleCuentasTesoreriaComponent,canActivate:[AuthGuardService]},
              			//cajas
              				{path:'tesoreria_catalogodecajas',component: ListaCajasTesoreriaComponent,canActivate:[AuthGuardService]},
              			//monederos
              				{path:'tesoreria_catalogodemonederos_electronicos',component: ListaMonederoTesoreriaComponent,canActivate:[AuthGuardService]},
              			//dispositivos
              				{path:'tesoreria_catalogodedispositivos',component: ListaDevicesTesoreriaComponent,canActivate:[AuthGuardService]},
              		//movimientos bancarios
              		//movimiwentos en efectivo
              		//ordenes de pagos
              			{path:'tesoreria_catalogodeordenesdepagocompras_detalle/:token_ordenPago',component: DetalleOrdenPagoTesoreriaComponent,canActivate:[AuthGuardService]},

              			{path:'tesoreria_catalogodeordenesdepagoventas',component: ListaOrdenesPagoVentasComponent,canActivate:[AuthGuardService]},
              		//ajustes
              		//info bancaria

              	//valor humano
              	//contabilidad
              		//catalogo de cuentas
              			{path:'contabilidad_catalogodecuentas',component: CatalogoCuentasComponent,canActivate:[AuthGuardService]},
              	//tecnologiass de la informacion
              		{path:'soporte_sos',component: SoporteComponent,canActivate:[AuthGuardService]},
                            */

              if ($valAlert->area == 1) {
                $css_mensaje = "ingresos";
                $tipo_mensaje = "Ingresos y cuentas por cobrar";
              }

              if ($valAlert->area == 2) {
                $css_mensaje = "egresos";
                $tipo_mensaje = "Egresos y cuentas por pagar";
              }

              if ($valAlert->area == 3) {
                $css_mensaje = "finanzas";
                $tipo_mensaje = "Finanzas";
              }

              if ($valAlert->area == 4) {
                $css_mensaje = "vHumano";
                $tipo_mensaje = "Valor humano";
              }

              if ($valAlert->area == 5) {
                $css_mensaje = "contabilidad";
                $tipo_mensaje = "Contabilidad";
              }

              if ($valAlert->area == 6) {
                $css_mensaje = "tecInformacion";
                $tipo_mensaje = "Tecnologías de la información";
              }

              $selectSubArea = DB::select(
                "SELECT accion FROM rutas_acceso WHERE id_ruta_acceso = ?",
                [$valAlert->subarea]
              );

              if ($valAlert->producto != NULL) {
                $prodList = DB::table("catalogo_productos AS catprod")
                  ->where(['catprod.id' => $valAlert->producto,])->get();

                if ($prodList[0]->post_folio == NULL) {
                  $folio = 'PROD-' . $JwtAuth->generarFolio($prodList[0]->folio_sistema);
                } else {
                  $folio = 'PROD-' . $JwtAuth->generarFolio($prodList[0]->folio_sistema) . "-" . $prodList[0]->post_folio;
                }
              }

              if ($valAlert->servicio != NULL) {
                //echo $valAlert->servicio.":"; 
                $servList = DB::select(
                  "SELECT folio_sistema,post_folio FROM catalogo_servicios WHERE id = ?",
                  [$valAlert->servicio,]
                );

                if ($servList[0]->post_folio == NULL) {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($servList[0]->folio_sistema);
                } else {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($servList[0]->folio_sistema) . "-" . $servList[0]->post_folio;
                }
              }

              if ($valAlert->cliente != NULL) {
                $klientList = DB::table("catalogo_clientes AS catclient")
                  ->where(['catclient.id' => $valAlert->cliente,])->get();

                if ($klientList[0]->post_folio == NULL) {
                  $folio = 'CLT-' . $JwtAuth->generarFolio($klientList[0]->folio);
                } else {
                  $folio = 'CLT-' . $JwtAuth->generarFolio($klientList[0]->folio) . "-" . $klientList[0]->post_folio;
                }
              }

              if ($valAlert->proveedor != NULL) {
                $provList = DB::table("catalogo_proveedores AS catprov")
                  ->where(['catprov.id' => $valAlert->proveedor,])->get();

                if ($provList[0]->post_folio == NULL) {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($provList[0]->folio);
                } else {
                  $folio = 'PROV-' . $JwtAuth->generarFolio($provList[0]->folio) . "-" . $provList[0]->post_folio;
                }
              }


              $mensaje = $selectSubArea[0]->accion . " - " . $titulo_alerta . " " . $folio;
            }

            $contenido = '<html><head><style>div{width:100%;display:flex;flex-wrap:wrap;flex-direction:column;justify-content:flex-start;align-items:flex-start;} 
                                div h4{width:100%;background-color:#353553;text-align:center;color:#fff;border-radius:8px} 
                                div p{width:100%;color:#353553;text-align:center;display:flex;justify-content:center;align-items:center;}</style>
                                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
                            </head><body><div><h4>' . $emisor . '</h4><p>' . $mensaje . '</p></div></body></html>';

            $each = array(
              "token_notificacion" => $token_notificacion,
              "token_emisor" => $token_emisor,
              "css_mensaje" => $css_mensaje,
              "tipo_mensaje" => $tipo_mensaje,
              "emisor" => $emisor,
              "mensaje" => $mensaje,
              "contenido" => $contenido,
              "view" => $visto,
              "link_detalle" => $link_detalle,
            );
            $detalleNotif[] = $each;
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'detalle' => $detalleNotif,
          );
        } else {
          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
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

  public function detalleNotificacionOutsideGP(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $detalleNotif = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "token_notificacion" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          'status' => 'error',
          'code' => 200,
          'message' => 'Usuario incorrecto ',
          'errors' => $validate->errors()
        );
      } else {
        $token_notificacion = str_replace("_", "=", $parametrosArray['token_notificacion']);
        $lista = NotificacionesModelo::join("module_proyectos AS proy", "alert.proyecto", "=", "proy.id")
          ->where([
            "alert.token_notificacion" => $token_notificacion,
            "alert.status_recibe" => FALSE,
            "alert.status_delete" => TRUE,
          ])->get();

        if (count($lista) != 0) {
          foreach ($lista as $valAlert) {
            $token_notificacion = $valAlert->token_notificacion;
            $token_notificacion_outside = str_replace("==", "__", $valAlert->token_notificacion);
            $fecha_notificacion = date('d-m-Y H:i:s', $valAlert->fecha_notificacion);
            $titulo_alerta = $JwtAuth->desencriptar($valAlert->titulo);
            $asunto = $valAlert->asunto;
            $name_tarea = "";
            $name_informe = "";
            $link_detalle = "";
            $link_detalle_out = "";

            if ($valAlert->visto == TRUE) {
              $visto = true;
            } else {
              $visto = false;
            }

            $sql_emisor = NotificacionesModelo::join("vhum_personal AS emisor", "alert.emisor", "=", "emisor.id")
              ->join("sos_personas AS pers_people", "emisor.personal", "=", "pers_people.id")
              ->where(['alert.token_notificacion' => $token_notificacion,])->get();

            $token_emisor = $sql_emisor[0]->pers_token;
            $emisor = $JwtAuth->desencriptar($sql_emisor[0]->paterno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->materno) . " " .
              $JwtAuth->desencriptar($sql_emisor[0]->nombre);

            if ($valAlert->post_folio == NULL) {
              $folio_proy = "PROY-" . $JwtAuth->generarFolio($valAlert->folio);
            } else {
              $folio_proy = "PROY-" . $JwtAuth->generarFolio($valAlert->folio) . "-" . $valAlert->post_folio;
            }
            $txt_proyecto = $folio_proy . " - " . $JwtAuth->desencriptar($valAlert->proyecto_name);

            if ($valAlert->upload_evidencias == TRUE) {
              $evidenciasUpload = true;
            } else {
              $evidenciasUpload = false;
            }

            if ($valAlert->delete_evd_perm == TRUE) {
              $evd_delete_perm = true;
            } else {
              $evd_delete_perm = false;
            }

            if ($valAlert->prioridad_proyecto == "baj") {
              $text_prioridad_proyecto = "baja";
            } else if ($valAlert->prioridad_proyecto == "med") {
              $text_prioridad_proyecto = "media";
            } else if ($valAlert->prioridad_proyecto == "alt") {
              $text_prioridad_proyecto = "alta";
            }

            $selectRecalendar = DB::select("SELECT rectar.id FROM module_proyectos_calendarizacion AS rectar 
                            JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?", [$valAlert->token_proyecto]);
            if (count($selectRecalendar) > 0) {
              $nuevaFechaFin = DB::select("SELECT fecha_compromiso_nueva FROM module_proyectos_calendarizacion 
                                WHERE id = (SELECT MAX(rectar.id) FROM module_proyectos_calendarizacion AS rectar 
                                JOIN module_proyectos AS tar WHERE rectar.proyecto = tar.id AND tar.token_proyecto = ?)", [$valAlert->token_proyecto]);
              $fecha_fin_proyecto = date('d-m-Y H:i:s', $nuevaFechaFin[0]->fecha_compromiso_nueva) . " (recalendarizada)";
            } else {
              $fecha_fin_proyecto = date('d-m-Y H:i:s', $valAlert->fecha_fin);
            }

            $selectLider = DB::select("SELECT pers.pers_token,people.paterno,
                            people.materno,people.nombre FROM vhum_personal AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'li' AND resp.personal = pers.id
                            AND pers.personal = people.id", [$valAlert->token_proyecto]);

            if (count($selectLider) == 1) {
              $token_lider = $selectLider[0]->pers_token;
              $nombre_lider = $JwtAuth->desencriptar($selectLider[0]->paterno) . " " .
                $JwtAuth->desencriptar($selectLider[0]->materno) . " " .
                $JwtAuth->desencriptar($selectLider[0]->nombre);
            } else {
              $selectCr = DB::select("SELECT pers.pers_token,people.paterno,
                            people.materno,people.nombre FROM vhum_personal AS pers JOIN sos_personas AS people JOIN 
                            module_proyectos_responsable AS resp JOIN module_proyectos AS proy WHERE proy.token_proyecto = ? 
                            AND proy.id = resp.proyecto AND resp.tipo_pp = 'cr' AND resp.personal = pers.id
                            AND pers.personal = people.id", [$valAlert->token_proyecto]);
              $token_lider = $selectCr[0]->pers_token;
              $nombre_lider = $JwtAuth->desencriptar($selectCr[0]->paterno) . " " .
                $JwtAuth->desencriptar($selectCr[0]->materno) . " " .
                $JwtAuth->desencriptar($selectCr[0]->nombre);
            }

            $tar_terminadas = 0;
            $tareaList = DB::table("module_proyectos_tareas AS subtar")
              ->join("module_proyectos AS tar", "subtar.proyecto", "=", "tar.id")
              ->where(["subtar.status" => TRUE, "tar.token_proyecto" => $valAlert->token_proyecto])
              ->orderBy("subtar.id", "DESC")->get();

            $tar_total = count($tareaList);

            foreach ($tareaList as $vTar) {
              if ($vTar->realizacion == TRUE) {
                $tar_terminadas++;
              }
            }

            if ($valAlert->tarea != "") {
              $select_tar = DB::select("SELECT folio_tarea,post_folio_tar,tarea_nombre FROM module_proyectos_tareas WHERE id = ?", [$valAlert->tarea]);
              if ($select_tar[0]->post_folio_tar == NULL) {
                $folio_tarea = "TAR-" . $JwtAuth->generarFolio($select_tar[0]->folio_tarea);
              } else {
                $folio_tarea = "TAR-" . $JwtAuth->generarFolio($select_tar[0]->folio_tarea) . "-" . $select_tar[0]->post_folio_tar;
              }
              $name_tarea = $folio_tarea . " - " . $JwtAuth->desencriptar($select_tar[0]->tarea_nombre);
            }

            if ($valAlert->informe != "") {
              $select_inf = DB::select("SELECT folio_informe,post_folio_informe,informe FROM module_proyectos_informes WHERE id = ?", [$valAlert->informe]);
              if ($select_inf[0]->post_folio_informe == NULL) {
                $folio_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe);
              } else {
                $folio_informe = "INF-" . $JwtAuth->generarFolio($select_inf[0]->folio_informe) . "-" . $select_inf[0]->post_folio_informe;
              }
              $name_informe = $folio_informe . " - " . $JwtAuth->desencriptar($select_inf[0]->informe);
            }

            if ($valAlert->tarea == "" && $valAlert->informe == "") {
              //$mensaje = $txt_proyecto.": ".$titulo_alerta;
              $link_detalle = "/sos_inside/bitacora_proyectos";
            } else if ($valAlert->tarea != "" && $valAlert->informe == "") {
              //$mensaje = "Tarea: ".$txt_tarea.", ".$titulo_alerta;
              $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $valAlert->token_proyecto;
            } else if ($valAlert->tarea != "" && $valAlert->informe != "") {
              //$mensaje = "Tarea: ".$txt_tarea.", ".$titulo_alerta;
              $link_detalle = "/sos_inside/bitacora_proyectos_detalle/" . $valAlert->token_proyecto;
            }

            $token_notificacion = str_replace("==", "__", $token_notificacion);

            $each = array(
              "token_notificacion" => $token_notificacion_outside,
              "token_emisor" => $token_emisor,
              //encabezado
              "tipo_mensaje" => $txt_proyecto,
              //proyecto
              "prioridad_proyecto" => $text_prioridad_proyecto,
              "fecha_inicio_proyecto" => date('d-m-Y H:i:s', $valAlert->fecha_inicio),
              "fecha_fin_proyecto" => $fecha_fin_proyecto,

              "carga_evd" => $evidenciasUpload,
              "eliminar_evd" => $evd_delete_perm,
              "nombre_lider" => $nombre_lider,
              "tar_total" => $tar_total,
              "tar_terminadas" => $tar_terminadas,

              //asunto
              "asunto" => $asunto,
              //cuerpo
              "tarea" => $name_tarea,
              "informe" => $name_informe,
              "mensaje" => $titulo_alerta,
              "emisor" => $emisor,
              //status
              "view" => $visto,
              "link_detalle" => $link_detalle,
              //"mensaje_simple" => $mensaje_simple,
            );
            $detalleNotif[] = $each;
          }

          $dataMensaje = array(
            'status' => 'success',
            'code' => 200,
            'detalle' => $detalleNotif,
          );
        } else {
          $dataMensaje = array(
            'status' => 'error',
            'code' => 200,
            'message' => 'error en token de notificación'
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

  public function deleteNotificacion(Request $request)
  {
    $JwtAuth = new \JwtAuth();
    $jsonUser = $request->input('json');
    $parametros = json_decode($jsonUser);
    $parametrosArray = json_decode($jsonUser, true);
    $detalleNotif = array();

    if (!empty($parametros) && !empty($parametrosArray)) {
      $validate = \Validator::make($parametrosArray, [
        "user_token" => "required|string",
        "token_notificacion" => "required|string",
      ]);

      if ($validate->fails()) {
        $dataMensaje = array(
          "status" => "error",
          "code" => 200,
          "message" => "Usuario incorrecto",
          "errors" => $validate->errors()
        );
      } else {
        $usuario = $JwtAuth->checkToken($parametrosArray["user_token"], true);
        $token_notificacion = $parametrosArray["token_notificacion"];

        if (isset($token_notificacion) && !empty($token_notificacion)) {
          $query_delete = NotificacionesModelo::join("main_empresas AS emp", "alert.empresa", "=", "emp.id")
            ->join("vhum_personal AS receptor", "alert.receptor", "=", "receptor.id")
            ->join("main_usuarios As users", "receptor.usuario", "=", "users.id")
            ->where([
              "alert.token_notificacion" => $token_notificacion,
              "alert.status_delete" => TRUE,
              "emp.emp_token" => $usuario->emp_token,
              "users.user_token" => $usuario->user_token,
            ])->limit(1)->update(
              array(
                "alert.status_recibe" => TRUE,
              )
            );

          if ($query_delete) {
            $dataMensaje = array(
              "status" => "success",
              "code" => 200,
              "message" => "Notificación eliminada",
            );
          } else {
            $dataMensaje = array(
              "status" => "error",
              "code" => 200,
              "message" => "Notificación no eliminada",
            );
          }
        } else {
          $dataMensaje = array(
            "status" => "error",
            "code" => 200,
            "message" => "Notificación inexistente",
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

  public function marcarLeida($id, Request $request){
    $notificacion = NotificacionesModelo::findOrFail($id);

    $notificacion->visto = true;
    $notificacion->read_at = time();
    $notificacion->fecha_lectura = time();
    $notificacion->save();

    return response()->json([
      'status' => 'ok',
      'message' => 'Notificación marcada como leída',
      'data' => $notificacion
    ]);
  }
}
