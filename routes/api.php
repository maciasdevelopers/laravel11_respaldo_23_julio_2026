<?php

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use App\Http\Middleware\JwtMiddleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

//SSIC
//gerencia
//ingresos
use App\Http\Controllers\INGR_ProductosController;
use App\Http\Controllers\INGR_ListaPreciosController;
use App\Http\Controllers\INGR_ServiciosController;
use App\Http\Controllers\INGR_DescuentosController;
use App\Http\Controllers\INGR_PromocionesController;
use App\Http\Controllers\INGR_ClientesController;
use App\Http\Controllers\INGR_VentasController;
use App\Http\Controllers\INGR_FacturacionController;

//egresos 
use App\Http\Controllers\EGRE_LogisticaComprasController;
use App\Http\Controllers\EGRE_AnticiposController;
use App\Http\Controllers\EGRE_ProveedoresController;
use App\Http\Controllers\EGRE_ComprasRegistroController;
use App\Http\Controllers\EGRE_ComprasRegistroManualController;
use App\Http\Controllers\EGRE_ComprasRegistroInstruccionController;
use App\Http\Controllers\EGRE_ComprasRegistroReembolsoController;
use App\Http\Controllers\EGRE_ComprasListasController;
use App\Http\Controllers\EGRE_CancelacionSoliController;
use App\Http\Controllers\EGRE_ProrrateosController;

//inventarios
use App\Http\Controllers\INVENT_RecepcionesController;
use App\Http\Controllers\INVENT_ProductosController;
use App\Http\Controllers\INVENT_LotesController;
use App\Http\Controllers\INVENT_PedimentosController;
use App\Http\Controllers\EGRE_GastosController;
use App\Http\Controllers\EGRE_ServiciosController;
use App\Http\Controllers\EGRE_ActivosFijosController;
use App\Http\Controllers\EGRE_ActivosIntangiblesController;
use App\Http\Controllers\EGRE_AlmacenController;
use App\Http\Controllers\EGRE_RequisicionesController;
use App\Http\Controllers\EGRE_CotizacionesController;
use App\Http\Controllers\EGRE_ComisionesController;
use App\Http\Controllers\EGRE_ReembolsosController;
use App\Http\Controllers\INVENT_ServiciosVentasController;
use App\Http\Controllers\INVENT_ServiciosComprasController;
use App\Http\Controllers\INVENT_SeriesController;

//finanzas
use App\Http\Controllers\FNZS_AcreedoresController;
use App\Http\Controllers\FNZS_DeudoresController;
use App\Http\Controllers\FNZS_PuntoVentaController;
use App\Http\Controllers\FNZS_CajaController;
use App\Http\Controllers\FNZS_CuentBancController;
use App\Http\Controllers\FNZS_MonedElectController;
use App\Http\Controllers\FNZS_FedEstadosMunicipiosController;
use App\Http\Controllers\FNZS_PagoOrdenController;
use App\Http\Controllers\FNZS_PagoDispersionNominaOrdenController;
use App\Http\Controllers\FNZS_IndicadoresController;
use App\Http\Controllers\FNZS_MovimientosDineroController;
use App\Http\Controllers\FNZS_EstadoMovFinanCajaController;
use App\Http\Controllers\FNZS_EstadoMovFinanCuentController;
use App\Http\Controllers\FNZS_EstadoMovFinanMonedController;
use App\Http\Controllers\FNZS_EstadoMovFinanClienteController;
use App\Http\Controllers\FNZS_EstadoMovFinanDeudorController;
use App\Http\Controllers\FNZS_EstadoMovFinanProveedorController;
use App\Http\Controllers\FNZS_EstadoMovFinanAcreedorController;
use App\Http\Controllers\FNZS_SolicitudesCancelacionController;
use App\Http\Controllers\MAIN_ComisionesController;

//valor humano
use App\Http\Controllers\VHUM_ReembolsosController;
use App\Http\Controllers\VHUM_TrabajadoresController;
use App\Http\Controllers\VHUM_CentrosDeTrabajoController;
use App\Http\Controllers\VHUM_NominasController;
use App\Http\Controllers\VHUM_AsimiladosController;
use App\Http\Controllers\VHUM_ImpuestosSobreNominaController;
use App\Http\Controllers\VHUM_IMSSController;

//contabilidad
use App\Http\Controllers\CONT_CuentasContablesController;
use App\Http\Controllers\CONT_DeclaracionesController;
use App\Http\Controllers\Cont_EsquemasImpuestosController;
use App\Http\Controllers\CONT_ImpuestosController;
use App\Http\Controllers\CONT_ActivosFijosDeprecController;
use App\Http\Controllers\CONT_PoliticasController;
use App\Http\Controllers\CONT_DevengacionesController;

//tecnologias de la informacion
use App\Http\Controllers\TICS_PlataformasDigitalesController;
use App\Http\Controllers\TICS_BancosController;
use App\Http\Controllers\TICS_PublicacionesController;
use App\Http\Controllers\TICS_DispositivosController;
use App\Http\Controllers\TICS_SoliRegistroController;
use App\Http\Controllers\TICS_VisitasController;
use App\Http\Controllers\MAIN_DescargablesController;
use App\Http\Controllers\MAIN_EmpresasController;
use App\Http\Controllers\MAIN_NotificacionesController;
use App\Http\Controllers\MAIN_ChatController;
use App\Http\Controllers\MAIN_RolesController;
use App\Http\Controllers\MAIN_MonedaController;
use App\Http\Controllers\MAIN_FormaPagoController;
use App\Http\Controllers\MAIN_MetodoPagoController;
use App\Http\Controllers\INVENT_UMedidaController;
use App\Http\Controllers\MAIN_PaisController;
use App\Http\Controllers\MAIN_CatSatController;
use App\Http\Controllers\MAIN_DireccionesController;
use App\Http\Controllers\MAIN_RegimenFiscalController;
use App\Http\Controllers\MAIN_MenuController;
use App\Http\Controllers\MAIN_UsuarioController;
use App\Http\Controllers\MAIN_SessionController;
use App\Http\Controllers\MAIN_ModulosController;
use App\Http\Controllers\MAIN_LandingController;
//sos
use App\Http\Controllers\MAIN_XmlValidateController;
use App\Http\Controllers\MAIN_ImagesController;
use App\Http\Controllers\MAIN_SettingsController;
use App\Http\Controllers\MAIN_CfdiController;
use App\Http\Controllers\MAIN_ClasificacionController;
use App\Http\Controllers\ModuleProyectosController;
use App\Http\Controllers\JURI_EventosController;

//TERCEROS
use App\Http\Controllers\TERC_AssociatesController;
use App\Http\Controllers\TERC_AssociatesCatalogosController;
use App\Http\Controllers\TERC_EmployeesController;

//chatGPT
use App\Http\Controllers\MAIN_GPTController;
use App\Models\User;
use App\Notifications\NuevaNotificacion;

//pagina principal
use Carbon\Carbon;

Route::withoutMiddleware(['jwt.auth','refresh.user','refresh.moriah','moriah.context','activity.log'])->get('/diagnostico-hora', function () {
    // 1. Configurar zona a CDMX para la prueba
    date_default_timezone_set('America/Mexico_City');

    // 2. Fecha de prueba: 10 de Junio de 2024 (En la ley vieja, esto sería Horario de Verano)
    $fechaPrueba = Carbon::create(2024, 6, 10, 12, 0, 0, 'America/Mexico_City');

    // 3. Obtener información
    $version_php = phpversion();
    $version_db_timezone = timezone_version_get();
    
    // 4. Lógica de validación
    // En la ley vieja (Verano), el offset era -05:00 (UTC-5)
    // En la ley nueva (Sin cambio), el offset debe ser siempre -06:00 (UTC-6)
    $offset = $fechaPrueba->format('P'); 
    $esCorrecto = ($offset === '-06:00');

    return response()->json([
        'ambiente' => 'Hosting sin SSH',
        'versiones' => [
            'php' => $version_php,
            'timezone_db' => $version_db_timezone, // Si dice "0.0.0" usa la interna de PHP
        ],
        'prueba_mexico' => [
            'descripcion' => 'Verificando si el servidor aplica Horario de Verano (ya eliminado)',
            'fecha_simulada' => $fechaPrueba->toDateTimeString(),
            'zona_horaria' => 'America/Mexico_City',
            'offset_obtenido' => $offset,
            'offset_esperado' => '-06:00',
            'resultado_diagnostico' => $esCorrecto ? '✅ CORRECTO (Tu servidor está actualizado)' : '❌ PELIGRO (Tu servidor sigue usando Horario de Verano)',
            'explicacion' => $esCorrecto 
                ? 'El sistema detecta que México ya no cambia de horario.' 
                : 'El sistema cree que es verano y adelantó una hora. Tus reportes saldrán mal en verano.'
        ]
    ]);
});

Route::withoutMiddleware(['jwt.auth','refresh.user','refresh.moriah','moriah.context','activity.log'])->group(function () {
	Route::get("listaPaises", [MAIN_PaisController::class, "getListaPais"]);
	Route::post("usuario_login_main", [MAIN_SessionController::class, "loginUsuarioMain"])->name('login');
	Route::get("landingSoluciones", [MAIN_LandingController::class, "listaServicios"]);
	Route::get("verPublicacionesMin", [TICS_PublicacionesController::class, "listaPublicacionesHome"]);
	Route::post("ver_publicacion_completa", [TICS_PublicacionesController::class, "verPublicacionCompleta"]);
	Route::get("ver_visitas", [TICS_VisitasController::class, "totalVisitas"]);
	Route::get("listadescargables", [MAIN_DescargablesController::class, "listaDescargables"]);
	Route::post("decoumg", [MAIN_ImagesController::class, "convertidor"]);
	Route::post("save_codigopass_ssic", [MAIN_SessionController::class, "guardarCodigoPass"]);
	Route::post("verif_codigopass_ssic", [MAIN_SessionController::class, "verificarCodigoPass"]);
	Route::post("reset_passwpord_ssic", [MAIN_SessionController::class, "resetPassFunction"]);
	Route::get("finanzas_indicadores_economicos", [FNZS_IndicadoresController::class, "listaIndicadores"]);
	Route::get("finanzas_indicadores_inpc_banxico", [FNZS_IndicadoresController::class, "indicadores_inpc_banxico"]);
	Route::get("finanzas_indicadores_salario_minimo_general_banxico", [FNZS_IndicadoresController::class, "indicadores_salario_minimo_general_banxico"]);
	Route::get("finanzas_indicadores_salario_minimo_fronterizo_banxico", [FNZS_IndicadoresController::class, "indicadores_salario_minimo_fronterizo_banxico"]);
	Route::get("finanzas_indicadores_uma_banxico", [FNZS_IndicadoresController::class, "indicadores_uma_banxico"]);
	Route::get("finanzas_indicadores_udi_banxico", [FNZS_IndicadoresController::class, "indicadores_udi_banxico"]);
	Route::get("finanzas_indicadores_tipo_de_cambio_banxico", [FNZS_IndicadoresController::class, "indicadores_tipo_de_cambio_banxico"]);
	Route::get("finanzas_indicadores_tiie_banxico", [FNZS_IndicadoresController::class, "indicadores_tiie_banxico"]);
	//visor CFDI
	Route::post("visor_cfdi_estado_xml_ingresos", [MAIN_XmlValidateController::class, "visorEstadoXmlCFDICompra"]);
	Route::get("catalogomonelect", [TICS_PlataformasDigitalesController::class, "listPlataformas"]);
	Route::get("catalogo_plataformas_digitales", [TICS_PlataformasDigitalesController::class, "listPlataformas"]);
	Route::get("listipodispositivo", [TICS_DispositivosController::class, "listaTipoDispositivo"]);
	Route::get("getallregimenfiscal", [MAIN_RegimenFiscalController::class, "listAllRegimenFiscal"]);
	Route::get("getpfregimenfiscal", [MAIN_RegimenFiscalController::class, "listPFRegimenFiscal"]);
	Route::get("getpmregimenfiscal", [MAIN_RegimenFiscalController::class, "listPMRegimenFiscal"]);
	Route::get("dipomexcpostales2", [MAIN_DireccionesController::class, "listacodDipome2"]);
	Route::post("dipomexcpostales", [MAIN_DireccionesController::class, "listacodDipomex"]);
	Route::get("getcpostales", [MAIN_DireccionesController::class, "listacodPostal"]);
	Route::get("listar_entidades_federativas", [MAIN_DireccionesController::class, "getEntidadesFederativas"]);
	Route::get("listabancos", [TICS_BancosController::class, "bancos"]);
	Route::get("catalogo_modulos", [MAIN_ModulosController::class, "catalogoModulosSOS"]);
	Route::post("modulo_configuracion", [MAIN_ModulosController::class, "modulosConfigSOS"]);
	Route::get("listaMonedas", [MAIN_MonedaController::class, "catalogoMonedas"]);
	Route::get("getformapago", [MAIN_FormaPagoController::class, "listaFormaPago"]);
	Route::get("getmetodopago", [MAIN_MetodoPagoController::class, "listaMetodoPago"]);
});

//logueo_sistemas
//Route::post("usuario_login_main", [MAIN_UsuarioController::class, "loginUsuarioMain"]);
//Route::post("login_module_ssic",[MAIN_UsuarioController::class,"loginModuleSSIC"]);
//Route::post("get_access_token",[MAIN_UsuarioController::class,"get_access_token"]);
//Route::post("module_ssic_updatepass",[MAIN_UsuarioController::class,"userUpdatePass"]);
//Route::post("login_module_xml_download",[MAIN_UsuarioController::class,"loginModuleXmlDownload"]);
//Route::post("login_module_logistica",[MAIN_UsuarioController::class,"loginModuleLogistica"]);
//Route::post("login_module_compras",[MAIN_UsuarioController::class,"loginModuleCompras"]);
//Route::post("login_module_gestion_proyectos",[MAIN_UsuarioController::class,"loginModuleGestionProyectos"]);
//Route::post("updatepass_module_gestion_proyectos",[MAIN_UsuarioController::class,"updatePassModuleGestionProyectos"]);
//Route::post("login_module_terceros_associates",[MAIN_UsuarioController::class,"loginModuleTercerosAssociates"]);  
//Route::post("login_module_terceros_clientes",[MAIN_UsuarioController::class,"loginModuleTercerosCustomers"]);
//Route::post("login_module_terceros_proveedores",[MAIN_UsuarioController::class,"loginModuleTercerosSuppliers"]);
//Route::post("login_module_terceros_empleados",[MAIN_UsuarioController::class,"loginModuleTercerosEmployees"]);
//Route::post("user_update_firebase_code",[MAIN_UsuarioController::class,"firebaseCodeUpdate"]);
//Route::post("user_update_avatar",[MAIN_UsuarioController::class,"user_update_avatar"]);

Route::middleware(['jwt.auth','refresh.user'])->group(function () {
  Route::get("modulos_de_acceso", [MAIN_SessionController::class, "getContextModulos"])->withoutMiddleware(['refresh.moriah','moriah.context','activity.log']);
  Route::get("settings_de_usuario", [MAIN_SessionController::class, "getContextUserSettings"])->withoutMiddleware(['refresh.moriah','moriah.context','activity.log']);
  Route::post("catalogo_empresas_vinculadas", [MAIN_SessionController::class, "catalogoEmpresasVinculadas"])->withoutMiddleware(['refresh.moriah','moriah.context','activity.log']);
	Route::post("select_empresa_vinculada", [MAIN_SessionController::class, "empresaVinculada"])->withoutMiddleware(['refresh.moriah','moriah.context','activity.log']);
	Route::get("/session/context", [MAIN_UsuarioController::class, "empresaVinculada"]);
	Route::post("listachats", [MAIN_ChatController::class, "listaHistoryChat"])->withoutMiddleware(['refresh.moriah','moriah.context','activity.log']);
	//notificaciones
		Route::get('/notificaciones', function (Request $request) {
      $herald_royal = $request->attributes->get('user_auth')->herald_royal;
			return $herald_royal->notifications;
		})->withoutMiddleware(['refresh.moriah','moriah.context','activity.log']);
		Route::get('/notificaciones_sin_leer', function (Request $request) {
      $herald_royal = $request->attributes->get('user_auth')->herald_royal;
			return $herald_royal->unreadNotifications;
		})->withoutMiddleware(['refresh.moriah','moriah.context','activity.log']);
		Route::post('notificaciones/{id}/marcar-leida', [MAIN_NotificacionesController::class, 'marcarLeida'])->withoutMiddleware(['refresh.moriah','moriah.context','activity.log']);
});

Route::middleware(['jwt.auth','refresh.user','activity.log'])->group(function () {
	Route::post('usuario_logout_main', [MAIN_SessionController::class, 'logoutUsuarioMain']);
	Route::post('usuario_recupera_user_empresa', [MAIN_SessionController::class, 'recuperaDataUserEmpresa']);
});

Route::middleware(['refresh.user','jwt.auth','refresh.moriah','moriah.context','activity.log'])->group(function () {
  //ssic
	//Route::post("login_ssic",[MAIN_UsuarioController::class,"sesionSsic"]);
	//Route::post("login_ssic_mobile",[MAIN_UsuarioController::class,"sesionMobileSsic"]);
	//Route::post("secondloginaccess",[MAIN_UsuarioController::class,"sesionSecondLoginAccess"]);

	//gerencia
		//monitoreo
		Route::post("comision_lista_general", [MAIN_ComisionesController::class, "comisionListaGeneral"]);
		Route::post("gerencia_monitoreo_comisiones", [MAIN_ComisionesController::class, "comisionesMonitoreo"]);
		Route::post("comision_listas_recibe_dinero", [MAIN_ComisionesController::class, "comisionListasRecibeDinero"]);
		Route::post("comision_registro_aviso_fnzs", [MAIN_ComisionesController::class, "comisionRegistroAvisoFnzs"]);
		Route::post("comisiones_solicitud_apertura", [MAIN_ComisionesController::class, "comisionesSolicitudApertura"]);

	//ingresos
		//ventas
		//Notas de mostrador
		//Ordenes de venta
		//Anticipo de clientes
		//Entrega de productos al cliente
		//Devoluciones sobre ventas
		//Notas de crédito
		//Solicitud para emisión de CFDI (Fiscal MX)
		Route::post("ingresos_facturacion_solicitudes_facturacion", [INGR_FacturacionController::class, "solicitudesCFDI"]);
		Route::post("ingresos_facturacion_detalle_solicitud_facturacion", [INGR_FacturacionController::class, "detalleSolicitudCFDI"]);
		Route::post("ingresos_facturacion_emision_factura", [INGR_FacturacionController::class, "emisionCFDI"]);
		//Facturación (Fiscal MX)
		//Notas de crédito (Fiscal MX)
		//Notas de debito (Fiscal MX)
		Route::post("ingresos_mostrador_buscaArticulosVenta", [INGR_VentasController::class, "buscaArticulosVentaMostrador"]);
		Route::post("ingresos_mostrador_articulosVenta", [INGR_VentasController::class, "cargaArticulosVentaMostrador"]);
		Route::post("ingresos_mostrador_articulosVentaByCode", [INGR_VentasController::class, "cargaArticulosVentaMostradorByCode"]);
		Route::post("ingresos_mostrador_registraventa", [INGR_VentasController::class, "registroVentaMostrador"]);
		Route::post("ingresos_mostrador_ventascatalogogeneral", [INGR_VentasController::class, "catalogoVentasMostrador"]);
		Route::post("ingresos_mostrador_registracobroventa", [INGR_VentasController::class, "registroCobroVentaMostrador"]);
		Route::post("ingresos_mostrador_venta_acceso", [INGR_VentasController::class, "imperialAccessVentas"]);
		Route::post("ingresos_mostrador_venta_inside_detalle", [INGR_VentasController::class, "detalleVentaInsideMostrador"]);
		Route::post("ingresos_mostrador_venta_detalle", [INGR_VentasController::class, "detalleVentaMostrador"]);
		Route::post("ingresos_mostrador_venta_cancelar", [INGR_VentasController::class, "cancelarVentaMostrador"]);
		Route::post("ingresos_mostrador_ventascatalogocanceladas", [INGR_VentasController::class, "catalogoVentasCanceladasMostrador"]);
		Route::post("ingresos_ventas_catalogogeneral", [INGR_VentasController::class, "catalogoVentas"]);
		Route::post("ingresos_ventas_cargaArticulosVenta", [INGR_VentasController::class, "cargaArticulosVenta"]);
		Route::post("ingresos_ventas_descargarttosell", [INGR_VentasController::class, "detalleVentaArticulo"]);
		Route::post("ingresos_ventas_descargarttosellpr", [INGR_VentasController::class, "detalleVentaArticuloPr"]);
		Route::post("ingresos_ventas_registraventa", [INGR_VentasController::class, "registraVentaArticulo"]);

		//clientes
		Route::post("ingresos_catalogos_clientes_general", [INGR_ClientesController::class, "clientesCatGeneral"]);
		Route::post("ingresos_catalogos_clientes_mx", [INGR_ClientesController::class, "clientesCatMx"]);
		Route::post("ingresos_catalogos_clientes_extranjeros", [INGR_ClientesController::class, "clientesCatExtranjeros"]);
		Route::post("ingresos_catalogos_listaclientes_publicogeneral", [INGR_ClientesController::class, "catalogoClientesPublicoGeneral"]);
		Route::post("ingresos_catalogos_listaclientes_publicogeneralVentasMostrador", [INGR_ClientesController::class, "catalogoClientesPublicoGeneralVentasMostrador"]);
		Route::post("ingresos_catalogos_listaclientes_validacion_request", [INGR_ClientesController::class, "requestValidacionCliente"]);
		Route::post("ingresos_catalogos_listaclientes_validar", [INGR_ClientesController::class, "validacionProcesoClientes"]);
		Route::post("ingresos_catalogos_cliente_papelera_save", [INGR_ClientesController::class, "clientePapeleraSave"]);
		Route::post("ingresos_catalogos_listaclienteseliminados", [INGR_ClientesController::class, "catalogoClientesEliminados"]);
		Route::post("ingresos_catalogos_cliente_restaurar", [INGR_ClientesController::class, "clienteRestaurar"]);
		Route::post("ingresos_catalogos_cliente_eliminar", [INGR_ClientesController::class, "clienteEliminar"]);
		Route::post("ingresos_catalogos_verify_exist_cliente_one", [INGR_ClientesController::class, "verifyClienteExist"]);
		Route::post("ingresos_catalogos_verify_exist_cliente_two", [INGR_ClientesController::class, "verifyClienteExistPerfil"]);
		Route::post("ingresos_catalogos_cliente_solicitud_registro", [INGR_ClientesController::class, "clienteSolicitudRegistro"]);
		Route::post("ingresos_catalogos_registrar_cliente", [INGR_ClientesController::class, "registrarCliente"]);
		Route::post("ingresos_catalogos_registrar_cliente_publicogeneral", [INGR_ClientesController::class, "clientePublicoGeneralRegistro"]);
		Route::post("ingresos_catalogos_verclientes", [INGR_ClientesController::class, "verCliente"]);
		Route::post("ingresos_catalogos_cliente_registra_contacto", [INGR_ClientesController::class, "clienteRegistraNuevoContacto"]);
		Route::post("ingresos_catalogos_update_contacto_generales", [INGR_ClientesController::class, "clienteUpdateContactoGenerales"]);
		Route::post("ingresos_catalogos_clientes_contacto_telefono_agregar", [INGR_ClientesController::class, "clienteUpdateContactoAddPhone"]);
		Route::post("ingresos_catalogos_clientes_contacto_telefono_update", [INGR_ClientesController::class, "clienteUpdateContactoUpdatePhone"]);
		Route::post("ingresos_catalogos_clientes_contacto_telefono_delete", [INGR_ClientesController::class, "clienteUpdateContactoDeletePhone"]);
		Route::post("ingresos_catalogos_clientes_contacto_email_agregar", [INGR_ClientesController::class, "clienteUpdateContactoAddEmail"]);
		Route::post("ingresos_catalogos_clientes_contacto_email_update", [INGR_ClientesController::class, "clienteUpdateContactoUpdateEmail"]);
		Route::post("ingresos_catalogos_clientes_contacto_correo_delete", [INGR_ClientesController::class, "clienteUpdateContactoDeleteEmail"]);
		Route::post("ingresos_catalogos_clientes_creditos_update", [INGR_ClientesController::class, "clienteUpdateCreditosUpdate"]);
		Route::post("ingresos_catalogos_clientes_creditos_delete", [INGR_ClientesController::class, "clienteUpdateCreditosDelete"]);
		Route::post("ingresos_catalogos_clientes_fcobro_update", [INGR_ClientesController::class, "clienteUpdateFormaCobroUpdate"]);
		Route::post("ingresos_catalogos_clientes_habilita_emitir_fact_antes_cobro", [INGR_ClientesController::class, "clienteUpdateHabilitaEmitirFactAntesCobro"]);
		Route::post("ingresos_catalogos_clientes_cancela_emitir_fact_antes_cobro", [INGR_ClientesController::class, "clienteUpdateCancelaEmitirFactAntesCobro"]);
		Route::post("ingresos_catalogos_clientes_entrega_de_prod_antes_cobro", [INGR_ClientesController::class, "clienteUpdateEntregaProdAntesCobro"]);
		Route::post("ingresos_catalogos_clientes_cancela_entrega_de_prod_antes_cobro", [INGR_ClientesController::class, "clienteUpdateCancelaEntregaProdAntesCobro"]);
		Route::post("ingresos_catalogos_clientes_update_ubicacion_dipomex", [INGR_ClientesController::class, "clienteUpdateUpdateUbicacionDipoMex"]);
		Route::post("ingresos_catalogos_clientes_update_ubicacion_no_api", [INGR_ClientesController::class, "clienteUpdateUpdateUbicacionNoApi"]);

		//lista de precios
		Route::post("ingresos_catalogos_getlistaprecios", [INGR_ListaPreciosController::class, "getListaPrecios"]);
		Route::post("ingresos_catalogos_registralistapreciosmercancias", [INGR_ListaPreciosController::class, "registralistaPreciosMerc"]);

		//productos
		Route::post("ingresos_catalogos_updatelistapreciosmercancias", [INGR_ListaPreciosController::class, "updatelistaPreciosMerc"]);
		Route::post("ingresos_catalogos_detallemercancia", [INGR_ProductosController::class, "detalleProductoIngresos"]);
		Route::post("ingresos_catalogos_registradescuentomercancia", [INGR_DescuentosController::class, "registrarMercDescuento"]);
		Route::post("ingresos_catalogos_vincdescuentomercancia", [INGR_DescuentosController::class, "vincularMercDescuento"]);
		Route::post("ingresos_catalogos_desvincdescuentomercancia", [INGR_DescuentosController::class, "desvincularMercDescuento"]);
		Route::post("ingresos_catalogos_registrapromocionmercancia", [INGR_PromocionesController::class, "registrarMercPromocion"]);
		Route::post("ingresos_catalogos_vincpromocionmercancia", [INGR_PromocionesController::class, "vincularMercPromocion"]);
		Route::post("ingresos_catalogos_desvincpromocionmercancia", [INGR_PromocionesController::class, "desvincularMercPromocion"]);
		Route::post("ingresos_catalogos_listavntsProductosEliminados", [INGR_ProductosController::class, "listavntsProductosEliminados"]);
		Route::post("ingresos_catalogos_detallemercancia", [INGR_ProductosController::class, "detalleProductoIngresos"]);
		Route::post("ingresos_catalogos_registradescuentomercancia", [INGR_DescuentosController::class, "registrarMercDescuento"]);
		Route::post("ingresos_catalogos_vincdescuentomercancia", [INGR_DescuentosController::class, "vincularMercDescuento"]);
		Route::post("ingresos_catalogos_desvincdescuentomercancia", [INGR_DescuentosController::class, "desvincularMercDescuento"]);
		Route::post("ingresos_catalogos_registrapromocionmercancia", [INGR_PromocionesController::class, "registrarMercPromocion"]);
		Route::post("ingresos_catalogos_vincpromocionmercancia", [INGR_PromocionesController::class, "vincularMercPromocion"]);
		Route::post("ingresos_catalogos_desvincpromocionmercancia", [INGR_PromocionesController::class, "desvincularMercPromocion"]);
		Route::post("ingresos_catalogos_listavntsProductosEliminados", [INGR_ProductosController::class, "listavntsProductosEliminados"]);

		//servicios
		Route::post("ingresos_catalogos_registralistapreciosserv", [INGR_ListaPreciosController::class, "registralistaPreciosServ"]);
		Route::post("ingresos_catalogos_updatelistapreciosserv", [INGR_ListaPreciosController::class, "updatelistaPreciosServ"]);
		Route::post("ingresos_catalogos_listaserviciosvigentesingresos", [INGR_ServiciosController::class, "listaServiciosVigentesIngresos"]);
		Route::post("ingresos_catalogos_simulaprecioservicio", [INGR_ServiciosController::class, "simulaPrecioServicio"]);
		Route::post("ingresos_catalogos_detalleingresosservicio", [INGR_ServiciosController::class, "viewServicioIngresos"]);
		Route::post("ingresos_catalogos_downloadservicioingresospdf", [INGR_ServiciosController::class, "downloadServicioIngresosPdf"]);
		Route::post("ingresos_catalogos_actualizageneralservicioingresos", [INGR_ServiciosController::class, "actualizaGeneralesServicioIngresos"]);
		Route::post("ingresos_catalogos_vincimpuestoservicio", [INGR_ServiciosController::class, "vincularServicioImpuesto"]);
		Route::post("ingresos_catalogos_desvincimpuestoservicio", [INGR_ServiciosController::class, "desvincularServicioImpuesto"]);
		Route::post("ingresos_catalogos_registradescuentoservicio", [INGR_DescuentosController::class, "registrarServicioDescuento"]);
		Route::post("ingresos_catalogos_vincdescuentoservicio", [INGR_DescuentosController::class, "vincularServicioDescuento"]);
		Route::post("ingresos_catalogos_desvincdescuentoservicio", [INGR_DescuentosController::class, "desvincularServicioDescuento"]);
		Route::post("ingresos_catalogos_registrapromocionservicio", [INGR_PromocionesController::class, "registrarServicioPromocion"]);
		Route::post("ingresos_catalogos_vincpromocionservicio", [INGR_PromocionesController::class, "vincularServicioPromocion"]);
		Route::post("ingresos_catalogos_desvincpromocionservicio", [INGR_PromocionesController::class, "desvincularServicioPromocion"]);
		Route::post("ingresos_catalogos_newclienteclaveservicio", [INGR_ServiciosController::class, "newClienteClavesServicio"]);
		Route::post("ingresos_catalogos_clavesactualizaclienteservicio", [INGR_ServiciosController::class, "actualizaClienteClavesServicio"]);
		Route::post("ingresos_catalogos_deleteclavesclienteservicio", [INGR_ServiciosController::class, "deleteClienteClavesServicio"]);
		Route::post("ingresos_catalogos_deleteservicioingresos", [INGR_ServiciosController::class, "deleteServicioIngresos"]);
		Route::post("ingresos_catalogos_listaservicioseliminadosingresos", [INGR_ServiciosController::class, "listaServiciosEliminadosIngresos"]);
		Route::post("ingresos_catalogos_servicioingresosrestart", [INGR_ServiciosController::class, "restartServicioIngresos"]);
		Route::post("ingresos_catalogos_eliminazionservingresos", [INGR_ServiciosController::class, "deleteDeadServicioIngresos"]);
		Route::post("ingresos_catalogos_registroservicioingresos", [INGR_ServiciosController::class, "registroServicioIngresos"]);

		//impuestos aplicables para ventas

		//descuentos
		Route::post("ingresos_catalogos_foliomaxdescuentos", [INGR_DescuentosController::class, "folioMaxDescuento"]);
		Route::post("ingresos_catalogos_folionewdescuentos", [INGR_DescuentosController::class, "folioNewRegDescuento"]);
		Route::post("ingresos_catalogos_listadescuentos", [INGR_DescuentosController::class, "listaDescuentos"]);
		Route::post("ingresos_catalogos_descuentosselected", [INGR_DescuentosController::class, "verDescuento"]);
		Route::post("ingresos_catalogos_desactivadescuento", [INGR_DescuentosController::class, "stopDescuento"]);
		Route::post("ingresos_catalogos_habilitadescuento", [INGR_DescuentosController::class, "habilitarDescuento"]);
		Route::post("ingresos_catalogos_updategeneralesdescuento", [INGR_DescuentosController::class, "updateGeneralesDescuento"]);
		Route::post("ingresos_catalogos_descuentosdesac", [INGR_DescuentosController::class, "listaDescuentosDeact"]);
		Route::post("ingresos_catalogos_eliminadescuento", [INGR_DescuentosController::class, "eliminadescuento"]);
		Route::post("ingresos_catalogos_restauradescuento", [INGR_DescuentosController::class, "restauradescuento"]);
		Route::post("ingresos_catalogos_deadeliminadescuento", [INGR_DescuentosController::class, "eliminaPermDescuento"]);
		Route::post("ingresos_catalogos_descuentosdelete", [INGR_DescuentosController::class, "listaDescuentosDel"]);
		Route::post("ingresos_catalogos_registranuevodescuento", [INGR_DescuentosController::class, "registraDescuento"]);

		//promociones
		Route::post("ingresos_catalogos_foliomaxpromocion", [INGR_PromocionesController::class, "folioMaxPromocion"]);
		Route::post("ingresos_catalogos_folionewpromocion", [INGR_PromocionesController::class, "folioNewRegPromocion"]);
		Route::post("ingresos_catalogos_listapromociones", [INGR_PromocionesController::class, "listaPromociones"]);
		Route::post("ingresos_catalogos_promocionesselected", [INGR_PromocionesController::class, "verPromocion"]);
		Route::post("ingresos_catalogos_desactivapromocion", [INGR_PromocionesController::class, "stopPromocion"]);
		Route::post("ingresos_catalogos_habilitapromocion", [INGR_PromocionesController::class, "habilitarPromocion"]);
		Route::post("ingresos_catalogos_updategeneralespromocion", [INGR_PromocionesController::class, "updateGeneralesPromocion"]);
		Route::post("ingresos_catalogos_promocionesdesac", [INGR_PromocionesController::class, "listaPromocionesDesac"]);
		Route::post("ingresos_catalogos_eliminapromocion", [INGR_PromocionesController::class, "eliminapromocion"]);
		Route::post("ingresos_catalogos_restaurapromocion", [INGR_PromocionesController::class, "restaurapromocion"]);
		Route::post("ingresos_catalogos_deadeliminapromocion", [INGR_PromocionesController::class, "eliminaPermPromocion"]);
		Route::post("ingresos_catalogos_promocionesdelete", [INGR_PromocionesController::class, "listaPromocionesDel"]);
		Route::post("ingresos_catalogos_registranuevopromocion", [INGR_PromocionesController::class, "registraPromocion"]);
		//reportes
		//Lista general de partidas
		//Lista de partidas abiertas
		//Lista de partidas cerradas
		//Reporte de ventas brutas
		//Reporte de ventas después de descuentos
		//Reporte de devoluciones
		//Costo de ventas
		//Reporte de ventas netas
		//Antigüedad de saldos por cobrar
		//Consulta de algún reporte espeifico sobre ventas
		//Conciliación fiscal-contable relacionada con ventas

	//egresos
		//compras
		//requisicion
		Route::post("egresos_compras_catalogo_requisiciones", [EGRE_RequisicionesController::class, "catalogoRequisiciones"]);
		Route::get("egresos_compras_verRequisicionPdf/{tokenRequi}", [EGRE_RequisicionesController::class, "verRequisicionPdfHtml"]);
		Route::post("egresos_compras_detalle_requisicion", [EGRE_RequisicionesController::class, "detalleRequisicion"]);
		Route::post("egresos_compras_detalle_requisicion_cot_list", [EGRE_RequisicionesController::class, "detalleRequisicionWithCotizaciones"]);
		Route::post("egresos_compras_eliminar_requisicion_detalle", [EGRE_RequisicionesController::class, "eliminarRequisicionDetalle"]);
		Route::post("egresos_compras_autoriza_requisicion", [EGRE_RequisicionesController::class, "autorizaRequisicion"]);
		Route::post("egresos_compras_autoriza_requisicion_all", [EGRE_RequisicionesController::class, "autorizaRequisicionAll"]);
		Route::post("egresos_compras_desautoriza_requisicion", [EGRE_RequisicionesController::class, "desautorizaRequisicion"]);
		Route::post("egresos_compras_update_requisicion_proyecto", [EGRE_RequisicionesController::class, "updateRequisicionProyecto"]);
		Route::post("egresos_compras_update_requisicion_prioridad", [EGRE_RequisicionesController::class, "updateRequisicionPrioridad"]);
		Route::post("egresos_compras_update_requisicion_list_tipo", [EGRE_RequisicionesController::class, "updateRequisicionListTipo"]);
		Route::post("egresos_compras_update_requisicion_list_concepto", [EGRE_RequisicionesController::class, "updateRequisicionListConcepto"]);
		Route::post("egresos_compras_update_requisicion_add_caract_list", [EGRE_RequisicionesController::class, "updateRequisicionAddCaractList"]);
		Route::post("egresos_compras_update_requisicion_delete_caract_list", [EGRE_RequisicionesController::class, "updateRequisicionDeleteCaractList"]);
		Route::post("egresos_compras_update_requisicion_list_cantidad", [EGRE_RequisicionesController::class, "updateRequisicionListCantidad"]);
		Route::post("egresos_compras_update_requisicion_list_unidad_medida", [EGRE_RequisicionesController::class, "updateRequisicionListUnidadMedida"]);
		Route::post("egresos_compras_update_requisicion_list_marca", [EGRE_RequisicionesController::class, "updateRequisicionListMarca"]);
		Route::post("egresos_compras_registraRequisicionLista", [EGRE_RequisicionesController::class, "registraRequisicionLista"]);
		Route::post("egresos_compras_requisicion_load_docs", [EGRE_RequisicionesController::class, "requisicionLoadDocs"]);
		Route::post("egresos_compras_registraRequisicionDocumento", [EGRE_RequisicionesController::class, "catalogoRequisiciones"]);
		Route::post("egresos_compras_totalRequisicionesPend", [EGRE_RequisicionesController::class, "totalRequisicionesPendientes"]);
		Route::post("egresos_compras_folioReqMax", [EGRE_RequisicionesController::class, "folioReqMax"]);
		Route::get("egresos_compras_listacaracteristicas", [EGRE_RequisicionesController::class, "listaCaracteristicas"]);
		//cotizacion
		Route::post("egresos_compras_solicitudes_cotizacion", [EGRE_CotizacionesController::class, "solicitudesCotizacion"]);
		Route::post("egresos_compras_solicitudes_cotizacion_cotizadas", [EGRE_CotizacionesController::class, "solicitudesCotizacionCheck"]);
		Route::post("egresos_compras_solicitud_cotizacion_detalle", [EGRE_CotizacionesController::class, "solicitudCotizacionDetalle"]);
		Route::post("egresos_compras_catalogo_cotizaciones", [EGRE_CotizacionesController::class, "catalogoCotizaciones"]);
		Route::post("egresos_compras_cotizacion_detalle", [EGRE_CotizacionesController::class, "cotizacionDetalle"]);
		Route::post("egresos_compras_totalCotizacionesPend", [EGRE_CotizacionesController::class, "totalCotizacionesPendientes"]);
		Route::post("egresos_compras_registrar_cotizacion_preq", [EGRE_CotizacionesController::class, "registrarCotizacionPReq"]);
		Route::post("egresos_compras_last_cotizacion_preq", [EGRE_CotizacionesController::class, "detalleReqLastCotizacion"]);
		Route::post("egresos_compras_autoriza_cotizacion_all", [EGRE_CotizacionesController::class, "autorizarAllCotizacion"]);
		Route::post("egresos_compras_autoriza_cotizacion", [EGRE_CotizacionesController::class, "autorizaCotizacion"]);
		Route::post("egresos_compras_desautoriza_cotizacion", [EGRE_CotizacionesController::class, "desautorizaCotizacion"]);
		Route::post("egresos_compras_registrar_cotizacion_directa", [EGRE_CotizacionesController::class, "registrarCotizacionDirecta"]);
		Route::post("egresos_compras_catalogo_cotizacion_directa", [EGRE_CotizacionesController::class, "catalogoCotizacionDirecta"]);
		Route::post("egresos_compras_autoriza_cotizacion_directa", [EGRE_CotizacionesController::class, "autorizaCotizacionDirecta"]);
		Route::post("egresos_compras_desautoriza_cotizacion_directa", [EGRE_CotizacionesController::class, "desautorizaCotizacionDirecta"]);
		Route::post("egresos_compras_cotizaciones_autorizadas", [EGRE_CotizacionesController::class, "cotizacionesAutorizadas"]);
		Route::post("egresos_compras_cotizacion_confirmar_contactoprov", [EGRE_CotizacionesController::class, "cotizacionConfirmarContactoProv"]);
		Route::post("egresos_compras_cotizaciones_preorden_compra", [EGRE_CotizacionesController::class, "cotizacionesPreordenCompra"]);
		Route::post("egresos_compras_cotizaciones_compra_proceso", [EGRE_CotizacionesController::class, "cotizacionesContactoProvBuyPrc"]);
		//Instrucción para la orden de compra
		//Registro de compras
		Route::post("egresos_compras_selectFolioCompra", [EGRE_ComprasRegistroController::class, "selectFolioCompra"]);
		Route::post("egresos_compras_listaprdservcomp", [EGRE_ComprasRegistroController::class, "listaGeneralArticulosCompras"]);
		Route::post("egresos_compras_listaprdservcompprov", [EGRE_ComprasRegistroController::class, "listaArticulosComprasByProv"]);
		Route::post("egresos_compras_listaservicioscomp", [EGRE_ComprasRegistroController::class, "listaGeneralServiciosCompras"]);
		Route::post("egresos_compras_listaservicioscompprov", [EGRE_ComprasRegistroController::class, "listaServiciosComprasByProv"]);
		Route::post("egresos_compras_registra_clave_articulo_prv", [EGRE_ComprasRegistroController::class, "registraArticulosClavesProv"]);
		Route::post("egresos_compras_consultarticulocompra", [EGRE_ComprasRegistroController::class, "consultArticuloCompras"]);
		Route::post("egresos_compras_registracompraByCFDI", [EGRE_ComprasRegistroController::class, "registrarCompraByCFDI"]);
		Route::post("egresos_compras_carga_cfdi_traslado", [EGRE_ComprasRegistroController::class, "cargarCfdiTraslado"]);
		Route::post("egresos_compras_registracompraByARTICULOS", [EGRE_ComprasRegistroManualController::class, "registrarCompraByARTICULOS"]);
		Route::post("egresos_compras_registracompraByINSTRUCCION", [EGRE_ComprasRegistroInstruccionController::class, "registrarCompraByINSTRUCCION"]);
		Route::post("egresos_compras_registracompraByReembolso", [EGRE_ComprasRegistroReembolsoController::class, "registrarCompraByReembolso"]);
		//Orden de compra
		//compras listaComprasProd
		Route::post("egresos_compras_lista_GeneralCompras", [EGRE_ComprasListasController::class, "listaComprasGeneral"]);
		Route::post("egresos_compras_solicitar_cancelacion_compra", [EGRE_ComprasListasController::class, "compraSolicitarCancelacion"]);
		Route::post("egresos_compras_listanoautorizadacompra", [EGRE_ComprasListasController::class, "listanoautorizadaCompra"]);
		Route::post("egresos_compras_listacomprasautorizadas", [EGRE_ComprasListasController::class, "listaComprasAutorizadas"]);
		Route::post("egresos_compras_listacompraspagadas", [EGRE_ComprasListasController::class, "listaComprasPagadas"]);
		Route::post("egresos_compras_listacompras_sinfactura", [EGRE_ComprasListasController::class, "listaComprasRecibeFacturaDespues"]);
		Route::post("egresos_compras_autorizarcompra", [EGRE_ComprasListasController::class, "autorizarCompra"]);
		Route::post("egresos_compras_registra_orden_recepcion", [EGRE_ComprasListasController::class, "registraRecepcionOrdenByCompra"]);
		Route::post("egresos_compras_activa_orden_recepcion", [EGRE_ComprasListasController::class, "desbloqueaRecepcionOrdenByCompra"]);
		Route::post("egresos_compras_registra_orden_pago", [EGRE_ComprasListasController::class, "registraPagoOrdenByCompra"]);
		Route::post("egresos_compras_activa_orden_pago", [EGRE_ComprasListasController::class, "desbloqueaPagoOrdenByCompra"]);
		Route::post("egresos_compras_cancelarcompra", [EGRE_ComprasListasController::class, "cancelarCompra"]);
		Route::post("egresos_compras_desglosecompletocompra", [EGRE_ComprasListasController::class, "desgloseCompletoCompra"]);
		Route::post("egresos_compras_complementa_informacion_CFDI", [EGRE_ComprasListasController::class, "compraComplementaInformacionCFDI"]);
		Route::post("egresos_compras_desglose_activar_aplicafacturasrecep", [EGRE_ComprasListasController::class, "desgloseCompraActivarAplicaFacturasRecep"]);
		Route::post("egresos_compras_desglose_deshabilitar_aplicafacturasrecep", [EGRE_ComprasListasController::class, "desgloseCompraDeshabilitarAplicaFacturasRecep"]);
		Route::post("egresos_compras_detallecomprasautorizadas", [EGRE_ComprasListasController::class, "detalleComprasAutorizadas"]);
		Route::post("egresos_compras_lista_comprasPeriodicas", [EGRE_ComprasListasController::class, "listaComprasPeriodicasProd"]);
		Route::post("egresos_compras_lista_general_comprasPeriodicas", [EGRE_ComprasListasController::class, "listaGeneralComprasPeriodicas"]);
		Route::post("egresos_compras_detallecomprasdevengserv", [EGRE_ComprasListasController::class, "detalleComprasDevengServ"]);
		Route::post("egresos_compras_devengaserviciocompras", [EGRE_ComprasListasController::class, "devengaServicioCompras"]);
		Route::post("egresos_compras_registrardevolucion", [EGRE_ComprasListasController::class, "registrarComprasDevolucion"]);
		Route::post("egresos_compras_listacomprasdevoluciones", [EGRE_ComprasListasController::class, "listaComprasDevoluciones"]);
		Route::post("egresos_compras_autorizarcomprasdevoluciones", [EGRE_ComprasListasController::class, "autorizarComprasDevolucion"]);
		Route::post("egresos_compras_cancelarcomprasdevoluciones", [EGRE_ComprasListasController::class, "cancelarComprasDevoluciones"]);

		Route::post("egresos_cancelaciones_lista_general", [EGRE_CancelacionSoliController::class, "listaSolicitudesCancelacion"]);
		Route::post("egresos_cancelaciones_solicitud_cancelacion_compra", [EGRE_CancelacionSoliController::class, "solicitudCancelacionCompra"]);
		Route::post("egresos_cancelaciones_confirmar_cancelacion_compra", [EGRE_CancelacionSoliController::class, "confirmarCancelacionCompra"]);
    
		Route::post("egresos_compras_validaestructxmlingresos", [MAIN_XmlValidateController::class, "validaEstructXmlIngresos"]);
		Route::post("egresos_compras_validaestructxmlegresos", [MAIN_XmlValidateController::class, "validaEstructXmlEgresos"]);
		Route::get("egresos_compras_vaduanas", [MAIN_XmlValidateController::class, "aduanas"]);

		//seguimiento de compras
		//compras no autorizadas
		//prorrateos
		Route::post("egresos_compras_listaegresosnoprorratea", [EGRE_ProrrateosController::class, "listaNoProrrateos"]);
		Route::post("egresos_compras_detailegresosnoprorratefalse", [EGRE_ProrrateosController::class, "detalleNoProrrateos"]);
		
		Route::post("egresos_compras_listaegresosprorrateos", [EGRE_ProrrateosController::class, "listaProrrateos"]);
		Route::post("egresos_compras_detailegresosprorrateos", [EGRE_ProrrateosController::class, "detalleProrrateo"]);
		Route::post("egresos_compras_historialegresosprorrateos", [EGRE_ProrrateosController::class, "historialDetalleProrrateo"]);
		Route::post("egresos_compras_deletehistoricdetalleprorrat", [EGRE_ProrrateosController::class, "eliminarHistoricoDetalleProrrateo"]);
		Route::post("egresos_compras_prorrateos_prorratear_productos", [EGRE_ProrrateosController::class, "getProductosParaProrratear"]);
		Route::post("egresos_compras_prorrateos_prorratear_activos_fijos", [EGRE_ProrrateosController::class, "getActivosFijosParaProrratear"]);
		Route::post("egresos_compras_guardaregresosprorrateos", [EGRE_ProrrateosController::class, "guardarProrrateo"]);

		Route::post("egresos_settings_empresa_config_eegr", [MAIN_EmpresasController::class, "empresaConfigEegr"]);

    //Logistica de compras
		Route::post("egresos_logistica_transitos_iniciados", [EGRE_LogisticaComprasController::class, "listaLogisticaTransitosIniciados"]);
		Route::post("egresos_logistica_transito_actualizar", [EGRE_LogisticaComprasController::class, "actualizarLogisticaTransito"]);
		Route::post("egresos_logistica_compras_lista_carta_porte", [EGRE_LogisticaComprasController::class, "listaCFDICartaPorteUUID"]);
		Route::post("egresos_logistica_compras_obtener_carta_porte", [EGRE_LogisticaComprasController::class, "obtenerCFDICartaPorteUUID"]);
		Route::post("egresos_logistica_compras_lista", [EGRE_LogisticaComprasController::class, "listaLogisticaCompras"]);
		Route::post("egresos_logistica_compras_desglose_partidas", [EGRE_LogisticaComprasController::class, "logisticaCompraSeleccionada"]);
		Route::post("egresos_logistica_compras_guardar_transito", [EGRE_LogisticaComprasController::class, "guardarTransitoCompra"]);

		Route::post("egresos_logistica_compras_arribos_sin_fecha_registrada", [EGRE_LogisticaComprasController::class, "obtenerArribosSinFecha"]);
		Route::post("egresos_logistica_compras_llegada_registrar", [EGRE_LogisticaComprasController::class, "registrarArribo"]);
		Route::post("egresos_logistica_compras_arribos_no_autorizados", [EGRE_LogisticaComprasController::class, "obtenerArribosNoAutorizados"]);
		Route::post("egresos_logistica_compras_llegada_autorizar", [EGRE_LogisticaComprasController::class, "autorizarArribo"]);
		Route::post("egresos_logistica_compras_ubicaciones_sin_entrega", [EGRE_LogisticaComprasController::class, "obtenerUbicacionesSinEntrega"]);
		Route::post("egresos_logistica_compras_continuar_ruta", [EGRE_LogisticaComprasController::class, "continuarRuta"]);
		Route::post("egresos_logistica_compras_monitor", [EGRE_LogisticaComprasController::class, "monitorRutasLogistica"]);
		Route::post("egresos_logistica_compras_ultimo_paradero", [EGRE_LogisticaComprasController::class, "obtenerUltimaUbicacion"]);
		//Anticipo para proveedores
		//Recepción de productos
		//Facturación del proveedor
		//Catálogo de erogaciones y otros gastos
		//Devolución de productos al proveedor
		//Notas de crédito del proveedor
		//Notas de debito del proveedor',

		//comisiones
		Route::post("egresos_comisiones_lista_general", [EGRE_ComisionesController::class, "comisionListaGeneral"]);
		Route::post("egresos_comisiones_listas_no_concluidas", [EGRE_ComisionesController::class, "comisionListasNoConcluidas"]);
		Route::post("egresos_comisiones_listas_concluidas", [EGRE_ComisionesController::class, "comisionListasConcluidas"]);
		Route::post("egresos_comisiones_deshabilitadas", [EGRE_ComisionesController::class, "comisionDeshabilitadas"]);
		Route::post("egresos_comisiones_terminar", [EGRE_ComisionesController::class, "comisionTerminar"]);
		Route::post("egresos_comisiones_reabrir", [EGRE_ComisionesController::class, "comisionAperturaReabrir"]);
		Route::post("egresos_comisiones_deshabilitar", [EGRE_ComisionesController::class, "comisionDeshabilitar"]);
		Route::post("egresos_comisiones_rehabilitar", [EGRE_ComisionesController::class, "comisionRehabilitar"]);
		Route::post("egresos_comisiones_detalle_update", [EGRE_ComisionesController::class, "comisionDetalleUpdate"]);
		Route::post("egresos_comisiones_detalle_get_data", [EGRE_ComisionesController::class, "comisionDetalleGetData"]);
		Route::post("egresos_comisiones_actualizar", [EGRE_ComisionesController::class, "comisionUpdate"]);
		Route::post("egresos_comisiones_reem_listas", [EGRE_ComisionesController::class, "comisionReemListas"]);
		Route::post("egresos_comisiones_registrar", [EGRE_ComisionesController::class, "comision_registro"]);
		Route::post("egresos_comisiones_comisionados", [EGRE_ComisionesController::class, "comisionadosListas"]);

		//reembolsos
		Route::post("egresos_reembolsos_lista_general", [EGRE_ReembolsosController::class, "reembolso_lista_general_partidas"]);
		Route::post("egresos_reembolsos_lista_revision", [EGRE_ReembolsosController::class, "reembolso_lista_general_revision"]);
		Route::post("egresos_reembolsos_lista_pendientes", [EGRE_ReembolsosController::class, "reembolso_lista_pendientes"]);
		Route::post("egresos_reembolsos_compras_para_vincular", [EGRE_ReembolsosController::class, "reembolso_compras_para_vincular"]);
		Route::post("egresos_reembolsos_lista_concluidos", [EGRE_ReembolsosController::class, "reembolso_lista_concluidos"]);

		Route::post("egresos_reembolsos_detalle", [EGRE_ReembolsosController::class, "egr_reembolso_detalle"]);
		Route::post("egresos_reembolsos_auth", [EGRE_ReembolsosController::class, "egr_reembolso_auth"]);
		Route::post("egresos_reembolsos_observaciones_auth", [EGRE_ReembolsosController::class, "egr_reembolso_observaciones_auth"]);
		Route::post("egresos_reembolsos_auth_pagar_a_acreedor", [EGRE_ReembolsosController::class, "egr_reembolso_auth_pagar_a_acreedor"]);
		Route::post("egresos_reembolsos_solicita_cancelacion_vinc_compras", [EGRE_ReembolsosController::class, "egr_reembolso_solicita_cancelacion_vinc"]);
		Route::post("egresos_reembolsos_compras_auth", [EGRE_ReembolsosController::class, "egr_reembolso_compras_auth"]);
		Route::post("egresos_reembolsos_genera_op_compras", [EGRE_ReembolsosController::class, "egr_reembolso_compras_genera_orden_pago"]);

		//gastos
		//Route::post("listaegresosgastosvigentes",[EGRE_GastosController::class,"listaGastosVigentes"]);

		//Anticipos
		Route::post("egresos_catalogos_proveedores_anticipos_catalogo", [EGRE_AnticiposController::class, "anticipoCatalogoGeneral"]);
		Route::post("egresos_catalogos_proveedores_anticipos_autorizados", [EGRE_AnticiposController::class, "anticipoAutorizados"]);
		Route::post("egresos_catalogos_proveedores_anticipos_solicitudes", [EGRE_AnticiposController::class, "anticipoSolicitudes"]);
		Route::post("egresos_catalogos_proveedores_anticipos_autorizar", [EGRE_AnticiposController::class, "anticipoAutorizar"]);
		Route::post("egresos_catalogos_proveedores_anticipos_by_prov", [EGRE_AnticiposController::class, "anticipoProveedorList"]);
		Route::post("egresos_catalogos_proveedores_anticipos_disponibles", [EGRE_AnticiposController::class, "anticipoProveedorDisponibleList"]);
		Route::post("egresos_catalogos_proveedores_anticipos_registro", [EGRE_AnticiposController::class, "anticipoProveedorRegist"]);

		//proveedores
		Route::post("egresos_catalogos_proveedores_general", [EGRE_ProveedoresController::class, "proveedoresCatGeneral"]);
		Route::post("egresos_catalogos_proveedores_for_procesos", [EGRE_ProveedoresController::class, "proveedoresCatGeneral"]);
		Route::post("egresos_catalogos_proveedores_mx", [EGRE_ProveedoresController::class, "proveedoresCatMx"]);
		Route::post("egresos_catalogos_proveedores_extranjeros", [EGRE_ProveedoresController::class, "proveedoresCatExtranjeros"]);
		Route::post("egresos_catalogos_proveedores_personas_fisicas", [EGRE_ProveedoresController::class, "proveedoresCatPersonasFisicas"]);
		Route::post("egresos_catalogos_proveedores_bitacora", [EGRE_ProveedoresController::class, "proveedoresBitacora"]);
		Route::post("egresos_catalogos_proveedoresforclaves", [EGRE_ProveedoresController::class, "proveedoresParaClaves"]);
		Route::post("egresos_catalogos_catalogoprovdel", [EGRE_ProveedoresController::class, "getCatalogoProvDel"]);
		Route::post("egresos_catalogos_proveedores_autorizados", [EGRE_ProveedoresController::class, "catalogoProvAutorizados"]);
		Route::post("egresos_catalogos_catalogo_prov_no_autorizados", [EGRE_ProveedoresController::class, "catalogoProvNotAutorizados"]);
		Route::post("egresos_catalogos_solicitar_validacion_proveedores", [EGRE_ProveedoresController::class, "requestValidacionProv"]);
		Route::post("egresos_catalogos_validacion_proceso_proveedores", [EGRE_ProveedoresController::class, "validacionProcesoProveedores"]);
		Route::post("egresos_catalogos_proveedores_prov_not_vinc_user", [EGRE_ProveedoresController::class, "catalogoProvNotVincUser"]);
		Route::post("egresos_catalogos_proveedores_prov_vincular_existente_usuario", [EGRE_ProveedoresController::class, "catalogoProvVincularExistentUsuario"]);
		Route::post("egresos_catalogos_proveedores_prov_vincular_nuevo_usuario", [EGRE_ProveedoresController::class, "catalogoProvVincularNewUsuario"]);
		Route::post("egresos_catalogos_detalle_proveedores", [EGRE_ProveedoresController::class, "verDetalleProveedor"]);
		Route::post("egresos_catalogos_proveedores_habilita_para_reembolsos", [EGRE_ProveedoresController::class, "provHabilitaParaReembolsos"]);
		Route::post("egresos_catalogos_proveedores_cancela_para_reembolsos", [EGRE_ProveedoresController::class, "provCancelaParaReembolsos"]);
		Route::post("egresos_catalogos_proveedores_saldos_catalogo", [EGRE_ProveedoresController::class, "saldosProveedorList"]);
		Route::post("egresos_catalogos_proveedores_saldos_disponible", [EGRE_ProveedoresController::class, "saldosProveedorDisponibleList"]);
		Route::post("egresos_catalogos_registracuentacontableproveedor", [EGRE_ProveedoresController::class, "createCuentaContableProv"]);
		Route::post("egresos_catalogos_actualizarfcproveedor", [EGRE_ProveedoresController::class, "actualizaRfcProv"]);
		Route::post("egresos_catalogos_actualizaidtaxproveedor", [EGRE_ProveedoresController::class, "actualizaIdTaxProv"]);
		Route::post("egresos_catalogos_actualizageneralespfproveedor", [EGRE_ProveedoresController::class, "actualizaGeneralesPF"]);
		Route::post("egresos_catalogos_actualizageneralespmproveedor", [EGRE_ProveedoresController::class, "actualizaGeneralesPM"]);
		Route::post("egresos_catalogos_actualizaredesproveedor", [EGRE_ProveedoresController::class, "actualizaRedes"]);
		Route::post("egresos_catalogos_ingresapersonalproveedor", [EGRE_ProveedoresController::class, "ingresaPersonalProveedor"]);
		Route::post("egresos_catalogos_eliminapersonalproveedor", [EGRE_ProveedoresController::class, "deletePersonalProv"]);
		Route::post("egresos_catalogos_actualizapersonalgeneralesproveedor", [EGRE_ProveedoresController::class, "actualizaGeneralesPersonal"]);
		Route::post("egresos_catalogos_agregapersonaltelefonoproveedor", [EGRE_ProveedoresController::class, "nuevoTelefonoPersonal"]);
		Route::post("egresos_catalogos_actualizapersonaltelefonoproveedor", [EGRE_ProveedoresController::class, "actualizaTelefonoPersonal"]);
		Route::post("egresos_catalogos_eliminapersonaltelefonoproveedor", [EGRE_ProveedoresController::class, "eliminaTelefonoPersonal"]);
		Route::post("egresos_catalogos_restartpersonaltelefonoproveedor", [EGRE_ProveedoresController::class, "restartTelefonoPersonal"]);
		Route::post("egresos_catalogos_eliminapermpersonaltelefonoproveedor", [EGRE_ProveedoresController::class, "eliminaTelefonoPersonalPermanente"]);
		Route::post("egresos_catalogos_agregapersonalemailproveedor", [EGRE_ProveedoresController::class, "nuevoCorreoPersonal"]);
		Route::post("egresos_catalogos_actualizapersonalemailproveedor", [EGRE_ProveedoresController::class, "actualizaCorreoPersonal"]);
		Route::post("egresos_catalogos_eliminapersonalemailproveedor", [EGRE_ProveedoresController::class, "eliminaCorreoPersonal"]);
		Route::post("egresos_catalogos_restartpersonalemailproveedor", [EGRE_ProveedoresController::class, "restartCorreoPersonal"]);
		Route::post("egresos_catalogos_eliminapermpersonalemailproveedor", [EGRE_ProveedoresController::class, "eliminaCorreoPersonalPermanente"]);
		Route::post("egresos_catalogos_restartpersonalproveedor", [EGRE_ProveedoresController::class, "restartPersonalProv"]);
		Route::post("egresos_catalogos_deletepermanentepersonalproveedor", [EGRE_ProveedoresController::class, "deletePersonalProvPermanente"]);
		Route::post("egresos_catalogos_updatecontanciafiscalsitload", [EGRE_ProveedoresController::class, "updatecontanciafiscalsitload"]);
		Route::post("egresos_catalogos_updatecontanciafiscalsitbase64", [EGRE_ProveedoresController::class, "updatecontanciafiscalsitbase64"]);
		Route::post("egresos_catalogos_updatecumplimientoload", [EGRE_ProveedoresController::class, "updatecumplimientoload"]);
		Route::post("egresos_catalogos_updatecumplimientobase64", [EGRE_ProveedoresController::class, "updatecumplimientobase64"]);
		Route::post("egresos_catalogos_proveedores_creditos_update", [EGRE_ProveedoresController::class, "updateCreditosProveedor"]);
		Route::post("egresos_catalogos_updateformapagoproveedor", [EGRE_ProveedoresController::class, "updateFormaPagoProveedor"]);
		Route::post("egresos_catalogos_updatefpagoproveedorestcuenta", [EGRE_ProveedoresController::class, "updatefPagoProveedorEstCuenta"]);
		Route::post("egresos_catalogos_updateclabeinterbpagoproveedor", [EGRE_ProveedoresController::class, "updateClabeInterbPagoProveedor"]);
		Route::post("egresos_catalogos_registranuevaubicacionextranjeroproveedor", [EGRE_ProveedoresController::class, "registraNuevaUbicacionExtranjeroProveedor"]);
		Route::post("egresos_catalogos_proveedores_update_ubicacion_dipomex", [EGRE_ProveedoresController::class, "updateUbicacionNacionalProveedor"]);
		Route::post("egresos_catalogos_updateubicacionextranjeroproveedor", [EGRE_ProveedoresController::class, "updateUbicacionExtranjeroProveedor"]);
		Route::post("egresos_catalogos_deleteubicacionproveedor", [EGRE_ProveedoresController::class, "deleteUbicacionProveedor"]);
		Route::post("egresos_catalogos_restaurarubicacionproveedor", [EGRE_ProveedoresController::class, "restaurarUbicacionProveedor"]);
		Route::post("egresos_catalogos_deletepermubicacionproveedor", [EGRE_ProveedoresController::class, "deletePermUbicacionProveedor"]);
		Route::post("egresos_catalogos_deleteproveedor", [EGRE_ProveedoresController::class, "deleteProveedor"]);
		Route::post("egresos_catalogos_restaurarproveedor", [EGRE_ProveedoresController::class, "restaurarProveedor"]);
		Route::post("egresos_catalogos_deletepermproveedor", [EGRE_ProveedoresController::class, "deletePermProveedor"]);
		Route::post("egresos_catalogos_verify_exist_proveedor_rfc", [EGRE_ProveedoresController::class, "buscaProveedorByRFC"]);
		Route::post("egresos_catalogos_verify_exist_proveedor_one", [EGRE_ProveedoresController::class, "buscaRfcAllProveedorOut"]);
		Route::post("egresos_catalogos_verify_exist_proveedor_two", [EGRE_ProveedoresController::class, "verifyProveedorExistPerfil"]);
		Route::post("egresos_catalogos_egresos_search_all_proveedores", [EGRE_ProveedoresController::class, "buscaRfcAllProveedor"]);
		Route::post("egresos_catalogos_egresos-busquedaproveedor", [EGRE_ProveedoresController::class, "buscaRFProveedor"]);
		Route::post("egresos_catalogos_egresos-busquedaextproveedor", [EGRE_ProveedoresController::class, "buscaRFProveedorExtPM"]);
		Route::post("egresos_catalogos_egresos-busquedapfextproveedor", [EGRE_ProveedoresController::class, "buscaRFProveedorExtPF"]);
		Route::post("egresos_catalogos_proveedor_solicitud_registro_compras", [EGRE_ProveedoresController::class, "registraProveedorModuloCompras"]);
		Route::post("egresos_catalogos_proveedor_registro_modulos_externos", [EGRE_ProveedoresController::class, "registraProveedorMModulosExternos"]);
		Route::post("egresos_catalogos_egresos_registraproveedor", [EGRE_ProveedoresController::class, "registraProveedorMax"]);

		//Impuestos aplicables a las compras
		//comisiones
		Route::post("egresos_comisiones_comision_registro_aviso_eegr", [MAIN_ComisionesController::class, "comisionRegistroAvisoEegr"]);
		//reportes
		//Lista general de partidas
		//Lista de partidas abiertas
		//Lista de partidas cerradas
		//Reporte de compra de productos
		//Reporte de contratación de servicios
		//Antigüedad de saldos por pagar
		//Conciliación fiscal-contable relacionada con compras

	//inventarios
		//Registros relacionados con movimientos al inventario
		Route::post("egresos_compras_lista_ordenes_recepcion", [INVENT_RecepcionesController::class, "listaOrdenesRecepcionCompra"]);
		Route::post("egresos_compras_detallecompras_recep", [INVENT_RecepcionesController::class, "detalleOrdenRecepcion"]);
		Route::post("egresos_compras_lista_ProdSinRecibir", [INVENT_RecepcionesController::class, "listaComprasProdSinRecibir"]);
    Route::post("egresos_compras_lista_ServSinDevengar", [INVENT_RecepcionesController::class, "listaComprasServSinDevengar"]);
    Route::post("inventarios_movimientos_recibe_activo", [INVENT_RecepcionesController::class, "recibeActivoFijoAlmacen"]);
		Route::post("egresos_compras_trueperiodoespera24hrs", [INVENT_RecepcionesController::class, "habilitaPeridoEspera"]);
		Route::post("egresos_compras_recibeprodutocompras", [INVENT_RecepcionesController::class, "recibeProdComprasAlmacen"]);
		//Route::post("egresos_compras_recibeactintangbuy", [INVENT_RecepcionesController::class, "recibeActivoIntangComprasAlmacen"]);
		//Route::post("egresos_compras_recibeserviciosbuy", [INVENT_RecepcionesController::class, "recibeServComprasAlmacen"]);
		Route::post("egresos_compras_recepcionescomprasautorizadas", [INVENT_RecepcionesController::class, "listaComprasRecepciones"]);
		Route::post("egresos_compras_rechazoscomprasautorizadas", [INVENT_RecepcionesController::class, "listaComprasRechazos"]);
		//Reporte de incidencias en inventarios
		//Articulos alternos
		//Ajustes a los costos por arribo de mercancias compradas
		//Bloqueo/desbloqueo de existencias
		//Ajustes manuales a los inventarios',
		//productos
		Route::post("inventarios_catalogos_productos_general", [INVENT_ProductosController::class, "catalogoProductosGeneral"]);
		Route::post("inventarios_catalogos_productos_inventarios", [INVENT_ProductosController::class, "catalogoProductosInventarios"]);
		Route::post("inventarios_catalogos_productos_mostrador", [INVENT_ProductosController::class, "catalogoProductosMostrador"]);
		Route::post("inventarios_catalogos_productosForVentas", [INVENT_ProductosController::class, "listaProductosForVentas"]);
		Route::post("inventarios_catalogos_productos_no_autorizados", [INVENT_ProductosController::class, "catalogoProductosNotAutorizados"]);
		Route::post("inventarios_catalogos_productos_solicita_valid", [INVENT_ProductosController::class, "requestValidacionProd"]);
		Route::post("inventarios_catalogos_validacion_proceso_productos", [INVENT_ProductosController::class, "validacionProcesoProducto"]);
		Route::post("inventarios_catalogos_detalleproducto", [INVENT_ProductosController::class, "detalleProductoDatosGenerales"]);
		Route::post("inventarios_catalogos_detalleproducto_almacen", [INVENT_ProductosController::class, "detalleProductoAlmacen"]);
		Route::post("inventarios_catalogos_detalleproducto_kardex", [INVENT_ProductosController::class, "detalleProductoKardex"]);
		Route::post("inventarios_catalogos_detalleproductoByCode", [INVENT_ProductosController::class, "detalleProductoVigenteByCode"]);
		Route::post("inventarios_catalogos_recargaprovproductos", [INVENT_ProductosController::class, "recargaProvProductos"]);
		Route::post("inventarios_catalogos_detalleproductoproveedor", [INVENT_ProductosController::class, "detalleProductoProveedor"]);
		Route::post("inventarios_catalogos_updatearticulologo", [INVENT_ProductosController::class, "updateArticuloLogo"]);
		Route::post("inventarios_catalogos_updategeneralesproducto", [INVENT_ProductosController::class, "updateGeneralesProducto"]);
		Route::post("inventarios_catalogos_updategeneralesmostradorproducto", [INVENT_ProductosController::class, "updateGeneralesMostraVentProducto"]);
		Route::post("inventarios_catalogos_agregacaracteristicaproducto", [INVENT_ProductosController::class, "agregaCaracteristicasProducto"]);
		Route::post("inventarios_catalogos_deletecaracteristicaproducto", [INVENT_ProductosController::class, "deleteCaracteristicasProducto"]);
		Route::post("inventarios_catalogos_agregaclavesinternasproducto", [INVENT_ProductosController::class, "agregaClavesProducto"]);
		Route::post("inventarios_catalogos_deleteclavesinternasproducto", [INVENT_ProductosController::class, "deleteClavesProducto"]);
		Route::post("inventarios_catalogos_deleteclaveprodproveedor", [INVENT_ProductosController::class, "deleteClaveProdProveedor"]);
		Route::post("inventarios_catalogos_updateclaveprodproveedor", [INVENT_ProductosController::class, "updateClaveProdProveedor"]);
		Route::post("inventarios_catalogos_appendclaveprodproveedor", [INVENT_ProductosController::class, "appendClaveProdProveedor"]);
		Route::post("inventarios_catalogos_deleteanexosproducto", [INVENT_ProductosController::class, "deleteAnexosProducto"]);
		Route::post("inventarios_catalogos_registraanexosproducto", [INVENT_ProductosController::class, "registraNuevoAnexosProducto"]);
		Route::post("inventarios_catalogos_changalmproducto", [INVENT_ProductosController::class, "changAlmProducto"]);
		Route::post("inventarios_catalogos_producto_papelera_save", [INVENT_ProductosController::class, "deleteProducto"]);
		Route::post("inventarios_catalogos_productosEliminados", [INVENT_ProductosController::class, "listaegresosProductosEliminados"]);
		Route::post("inventarios_catalogos_producto_restaurar", [INVENT_ProductosController::class, "restauraProducto"]);
		Route::post("inventarios_catalogos_producto_delete_perm", [INVENT_ProductosController::class, "deletePapProducto"]);
		Route::post("inventarios_catalogos_prodPorProveedor", [INVENT_ProductosController::class, "prodPorProveedor"]);
		Route::post("inventarios_catalogos_createarticulo", [INVENT_ProductosController::class, "registroProducto"]);
		Route::post("inventarios_catalogos_mostrador_createarticulo", [INVENT_ProductosController::class, "registroProductoMostrador"]);
		Route::post("modulo_mostrador_productos_catalogo", [TERC_AssociatesCatalogosController::class, "productoAssocCatalogo"]);
		Route::post("modulo_mostrador_productos_actualizar", [TERC_AssociatesCatalogosController::class, "productoActualizar"]);
		Route::post("modulo_mostrador_productos_papelera_save", [TERC_AssociatesCatalogosController::class, "productoPapeleraSave"]);
		Route::post("modulo_mostrador_productos_papelera_catalogo", [TERC_AssociatesCatalogosController::class, "productoAssocCatalogoEliminados"]);
		Route::post("modulo_mostrador_productos_restaurar", [TERC_AssociatesCatalogosController::class, "productoPapeleraRestaurar"]);
		Route::post("modulo_mostrador_productos_eliminar", [TERC_AssociatesCatalogosController::class, "productoDeletePerm"]);

		//servicios
		Route::post("inventarios_catalogos_serviciosVigentes", [INVENT_ServiciosComprasController::class, "serviciosCatalogoGeneral"]);
		Route::post("inventarios_catalogos_servicios_no_autorizados", [INVENT_ServiciosVentasController::class, "catalogoServiciosNotAutorizados"]);
		Route::post("inventarios_catalogos_servicios_solicita_valid", [INVENT_ServiciosVentasController::class, "requestValidacionServ"]);
		Route::post("inventarios_catalogos_validacion_proceso_servicios", [INVENT_ServiciosVentasController::class, "validacionProcesoServicio"]);
		Route::post("inventarios_catalogos_general_createservicio", [INVENT_ServiciosVentasController::class, "servToVentasGeneralRegistro"]);
		Route::post("inventarios_catalogos_general_catalogoserv", [INVENT_ServiciosVentasController::class, "servToVentasGeneralCatalogo"]);
		Route::post("inventarios_catalogos_general_deletedserv", [INVENT_ServiciosVentasController::class, "servToVentasGeneralDeletedCatalogo"]);
		//ventas de mostrador
		Route::post("inventarios_catalogos_mostrador_createservicio", [INVENT_ServiciosVentasController::class, "servToVentasMostradorRegistro"]);
		Route::post("inventarios_catalogos_mostrador_catalogoserv", [INVENT_ServiciosVentasController::class, "servToVentasMostradorCatalogo"]);
		Route::post("inventarios_catalogos_mostrador_servicioperfil", [INVENT_ServiciosVentasController::class, "servToVentasMostradorPerfil"]);
		Route::post("inventarios_catalogos_mostrador_servicioupdate", [INVENT_ServiciosVentasController::class, "servToVentasMostradorUpdate"]);
		Route::post("inventarios_catalogos_mostrador_serviciodelete", [INVENT_ServiciosVentasController::class, "servToVentasMostradorDelete"]);
		//Servicios para compra
		Route::post("inventarios_catalogos_appendservicio", [INVENT_ServiciosComprasController::class, "servToComprasGeneralRegistro"]);
		Route::post("inventarios_catalogos_servicios_compras", [INVENT_ServiciosComprasController::class, "servToComprasGeneralCatalogo"]);
		Route::post("inventarios_catalogos_detalleservicioegresos", [INVENT_ServiciosComprasController::class, "servToComprasGeneralPerfil"]);
		Route::post("inventarios_catalogos_detalleservicioproveedor", [INVENT_ServiciosComprasController::class, "detalleServicioProveedor"]);
		Route::post("inventarios_catalogos_recargaprovservicios", [INVENT_ServiciosComprasController::class, "recargaProvServicios"]);
		Route::post("inventarios_catalogos_downpdfservegresos", [INVENT_ServiciosComprasController::class, "downloadServicioEgresosPdf"]);
		Route::post("inventarios_catalogos_servicios_compras_update", [INVENT_ServiciosComprasController::class, "actualizaGeneralesServicio"]);
		Route::post("inventarios_catalogos_updateservicioprov", [INVENT_ServiciosComprasController::class, "actualizaProvClavesServicio"]);
		Route::post("inventarios_catalogos_newservicioprov", [INVENT_ServiciosComprasController::class, "newProvClavesServicio"]);
		Route::post("inventarios_catalogos_deleteservicioprov", [INVENT_ServiciosComprasController::class, "deleteProvClavesServicio"]);
		Route::post("inventarios_catalogos_servicio_papelera_save", [INVENT_ServiciosComprasController::class, "deleteServicioEgresos"]);
		Route::post("inventarios_catalogos_serviciosEliminados", [INVENT_ServiciosComprasController::class, "listaegresosServiciosEliminados"]);
		Route::post("inventarios_catalogos_servicio_restaurar", [INVENT_ServiciosComprasController::class, "restartServicio"]);
		Route::post("inventarios_catalogos_servicio_delete_perm", [INVENT_ServiciosComprasController::class, "deleteDeadServicioEgresos"]);

		//Códigos de barras
		//lotes
		Route::post("inventarios_catalogos_listalotesvigentes", [INVENT_LotesController::class, "listaLotesVigentes"]);
		Route::post("inventarios_catalogos_listalotesdelete", [INVENT_LotesController::class, "listaLotesdelete"]);
		Route::post("inventarios_catalogos_detalleegresoslote", [INVENT_LotesController::class, "detalleEgresosLote"]);
		Route::post("inventarios_catalogos_actualizaegresoslote", [INVENT_LotesController::class, "updateEgresosLote"]);
		Route::post("inventarios_catalogos_listadeletedlotes", [INVENT_LotesController::class, "listaLotesDeleted"]);
		Route::post("inventarios_catalogos_restartlote", [INVENT_LotesController::class, "loteRestart"]);
		Route::post("inventarios_catalogos_deleteloteperm", [INVENT_LotesController::class, "LoteDeletePerm"]);
		Route::post("inventarios_catalogos_registraLote", [INVENT_LotesController::class, "registraLote"]);

		//pedimentos
		Route::post("inventarios_catalogos_listaegresospedimentosvigentes", [INVENT_PedimentosController::class, "listaegresosPedimentosVigentes"]);
		Route::post("inventarios_catalogos_detalleregresospedimento", [INVENT_PedimentosController::class, "detalleEgresosPedimento"]);
		Route::post("inventarios_catalogos_actualizaegresospedimento", [INVENT_PedimentosController::class, "updateEgresosPedimento"]);
		Route::post("inventarios_catalogos_listaegresospedimentosdelete", [INVENT_PedimentosController::class, "listaegresosPedimentosDelete"]);
		Route::post("inventarios_catalogos_listadeletedegresospedimentos", [INVENT_PedimentosController::class, "listaegresosPedimentosDeleted"]);
		Route::post("inventarios_catalogos_restartpedimento", [INVENT_PedimentosController::class, "pedimentoRestart"]);
		Route::post("inventarios_catalogos_pedimentodeleteperm", [INVENT_PedimentosController::class, "pedimentoDeletePerm"]);
		Route::post("inventarios_catalogos_registrapedimento", [INVENT_PedimentosController::class, "registraPedimento"]);

		//series
		Route::post("inventarios_catalogos_series_registro", [INVENT_SeriesController::class, "listaSeriesRegistro"]);
		Route::post("inventarios_catalogos_series_catalogo", [INVENT_SeriesController::class, "listaSeriesRegistradas"]);
		Route::post("inventarios_catalogos_series_detalle", [INVENT_SeriesController::class, "listaSeriesSeguimiento"]);
		Route::post("inventarios_catalogos_series_eliminapap", [INVENT_SeriesController::class, "listaSeriesMoveToPapelera"]);
		Route::post("inventarios_catalogos_series_eliminadas", [INVENT_SeriesController::class, "listaSeriesEliminadas"]);
		Route::post("inventarios_catalogos_series_restaurar", [INVENT_SeriesController::class, "listaSeriesRestaurar"]);
		Route::post("inventarios_catalogos_series_borrar", [INVENT_SeriesController::class, "listaSeriesEliminar"]);

		//Lineas de productos
		//Departamentos
		//activos fijos
		Route::post("inventarios_catalogos_appendactivofijo", [EGRE_ActivosFijosController::class, "registroActivoFijo"]);
		Route::post("inventarios_catalogos_listaActivosFijos", [EGRE_ActivosFijosController::class, "getActivosFijosCatalogo"]);

		Route::post("inventarios_catalogos_viewActivoFijo", [EGRE_ActivosFijosController::class, "verActivoFijo"]);
		Route::post("inventarios_catalogos_actualizageneralesactfijo", [EGRE_ActivosFijosController::class, "actualizaGeneralesActivoFijo"]);
		Route::post("inventarios_catalogos_deletepapeleraactivofijo", [EGRE_ActivosFijosController::class, "deleteActivoFijo"]);
		Route::post("inventarios_catalogos_listaActivosFijosDeleted", [EGRE_ActivosFijosController::class, "getActivosFijosDeleted"]);
		Route::post("inventarios_catalogos_restartActivosFijos", [EGRE_ActivosFijosController::class, "restartActivosFijos"]);
		Route::post("inventarios_catalogos_deleteDeadActivosFijos", [EGRE_ActivosFijosController::class, "deleteDeadActivosFijos"]);

		//activos diferidos
		Route::post("inventarios_catalogos_listaActivosIntan", [EGRE_ActivosIntangiblesController::class, "getListActIntangibles"]);
		Route::post("inventarios_catalogos_listacompraActivosIntan", [EGRE_ActivosIntangiblesController::class, "getListActIntangiblesCompras"]);
		Route::post("inventarios_catalogos_viewActivoIntan", [EGRE_ActivosIntangiblesController::class, "verActivoIntang"]);
		Route::post("inventarios_catalogos_actualizageneralesactintang", [EGRE_ActivosIntangiblesController::class, "actualizageneralesActivoIntang"]);
		Route::post("inventarios_catalogos_updateactivointangprov", [EGRE_ActivosIntangiblesController::class, "actualizaProvClavesActivoIntang"]);
		Route::post("inventarios_catalogos_nuevactivointangprov", [EGRE_ActivosIntangiblesController::class, "newProvClavesActivoIntang"]);
		Route::post("inventarios_catalogos_deleteactivointangprov", [EGRE_ActivosIntangiblesController::class, "deleteProvClavesActivoIntang"]);
		Route::post("inventarios_catalogos_deletepapeleraactivointang", [EGRE_ActivosIntangiblesController::class, "deleteActivoIntang"]);
		Route::post("inventarios_catalogos_listaactivosintandeleted", [EGRE_ActivosIntangiblesController::class, "getActivosIntangDeleted"]);
		Route::post("inventarios_catalogos_restartActivosintang", [EGRE_ActivosIntangiblesController::class, "restartActivosIntang"]);
		Route::post("inventarios_catalogos_deleteDeadActivosIntang", [EGRE_ActivosIntangiblesController::class, "deleteDeadActivosIntang"]);
		Route::post("inventarios_catalogos_activosclasificacionintang", [EGRE_ActivosIntangiblesController::class, "listaClassActIntangibles"]);
		Route::post("inventarios_catalogos_agregaclassactivointang", [EGRE_ActivosFijosController::class, "agregaClassActivoIntang"]);
		Route::post("inventarios_catalogos_appendactivointangible", [EGRE_ActivosIntangiblesController::class, "registroActivoIntang"]);
		Route::post("egresos_compras_prorrateos_prorratear_activos_diferidos", [EGRE_ProrrateosController::class, "getActivosDiferidosParaProrratear"]);

		//establecimientos
		Route::post("inventarios_catalogos_establecimientos_total", [EGRE_AlmacenController::class, "totalAlmacenes"]);
		Route::post("inventarios_catalogos_establecimientos", [EGRE_AlmacenController::class, "establecimientosCatalogo"]);
		Route::post("inventarios_catalogos_establecimientos_no_centro_trabajo", [EGRE_AlmacenController::class, "establecimientosCatalogoNoCentrosTrabajo"]);
		Route::post("inventarios_catalogos_listdireccionalmcomplete", [EGRE_AlmacenController::class, "direccionAlmacenComplete"]);
		Route::post("inventarios_catalogos_detalleestablecimiento", [EGRE_AlmacenController::class, "detalleEstablecimiento"]);
		Route::post("inventarios_catalogos_actualizaestablecimiento", [EGRE_AlmacenController::class, "updateEstablecimiento"]);
		Route::post("inventarios_catalogos_deleteestablecimiento", [EGRE_AlmacenController::class, "eliminaEstablecimiento"]);
		Route::post("inventarios_catalogos_deletedestablecimientos", [EGRE_AlmacenController::class, "establecimientosDeletedCatalogo"]);
		Route::post("inventarios_catalogos_restoreestablecimiento", [EGRE_AlmacenController::class, "restaurarEstablecimiento"]);
		Route::post("inventarios_catalogos_permdeleteestablecimiento", [EGRE_AlmacenController::class, "eliminaPermEstablecimiento"]);
		Route::post("inventarios_catalogos_registraestablecimiento", [EGRE_AlmacenController::class, "registraEstablecimiento"]);
		Route::post("inventarios_catalogos_registraestablecimientoextranjero", [EGRE_AlmacenController::class, "registraEstablecimientoExtranjero"]);
		Route::post("inventarios_catalogos_establecimientoresponsables", [EGRE_AlmacenController::class, "listaResponsablesAlmacen"]);
		//unidades de medida
		//Route::post("inventarios_catalogos_establecimientos_total",[EGRE_AlmacenController::class,"totalAlmacenes"]);
		//Route::post("inventarios_catalogos_listdireccionalm",[EGRE_AlmacenController::class,"direccionAlmacen"]);
		//Route::post("inventarios_catalogos_listdireccionalmcomplete",[EGRE_AlmacenController::class,"direccionAlmacenComplete"]);
		//Route::post("inventarios_catalogos_listdireccionalmdeleted",[EGRE_AlmacenController::class,"direccionAlmacenDeleted"]);
		//Route::post("inventarios_catalogos_detalleestablecimiento",[EGRE_AlmacenController::class,"detalleEstablecimiento"]);
		//Route::post("inventarios_catalogos_updategeneralestablecimiento",[EGRE_AlmacenController::class,"updateGenerales"]);
		//Route::post("inventarios_catalogos_updateubicanacestab",[EGRE_AlmacenController::class,"updateUbicacionNacional"]);
		//Route::post("inventarios_catalogos_updateubicaextestab",[EGRE_AlmacenController::class,"updateUbicacionExtranjero"]);
		//Route::post("inventarios_catalogos_quitapersonalestab",[EGRE_AlmacenController::class,"eliminaPersonalEstablecimiento"]);
		//Route::post("inventarios_catalogos_agregapersonalestab",[EGRE_AlmacenController::class,"agregaPersonalEstablecimiento"]);
		//Route::post("inventarios_catalogos_registraestablecimiento",[EGRE_AlmacenController::class,"registraEstablecimiento"]);
		//Route::post("inventarios_catalogos_registraestablecimientoextranjero",[EGRE_AlmacenController::class,"registraEstablecimientoExtranjero"]);
		//Route::post("inventarios_catalogos_establecimientoresponsables",[EGRE_AlmacenController::class,"listaResponsablesAlmacen"]);

		//unidades de medida
		Route::post("inventarios_catalogos_unidades_medida_categoria", [INVENT_UMedidaController::class, "clasificacionMedidaSat"]);
		Route::post("inventarios_catalogos_unidades_medida_lista_general", [INVENT_UMedidaController::class, "listaUnidadesMedida"]);
		Route::post("inventarios_catalogos_unidades_medida_medidasat", [INVENT_UMedidaController::class, "medidasSat"]);
		Route::post("inventarios_catalogos_unidades_medida_medidasatservicios", [INVENT_UMedidaController::class, "medidasSatServicios"]);
		Route::post("inventarios_catalogos_unidades_medida_postmedidasatservicios", [INVENT_UMedidaController::class, "postMedidasSatServicios"]);
		Route::post("inventarios_catalogos_unidades_medida_verpdf", [INVENT_UMedidaController::class, "pdfHtml"]);
		Route::post("inventarios_catalogos_unidades_medida_registrar", [INVENT_UMedidaController::class, "unidadesMedidaRegistrar"]);
		Route::post("inventarios_catalogos_unidades_medida_catalogo", [INVENT_UMedidaController::class, "unidadesMedidaCatalogo"]);
		Route::post("inventarios_catalogos_unidades_medida_enabled_catalogo", [INVENT_UMedidaController::class, "unidadesMedidaEnabledCatalogo"]);
		Route::post("inventarios_catalogos_unidades_medida_generales_update", [INVENT_UMedidaController::class, "unidadesMedidaUpdate"]);
		Route::post("inventarios_catalogos_unidades_medida_generales_habilitar", [INVENT_UMedidaController::class, "unidadesMedidaHabilitar"]);
		Route::post("inventarios_catalogos_unidades_medida_generales_deshabilitar", [INVENT_UMedidaController::class, "unidadesMedidaDeshabilitar"]);
		Route::post("inventarios_catalogos_unidades_medida_generales_eliminar_papelera", [INVENT_UMedidaController::class, "unidadesMedidaEliminarPapelera"]);
		Route::post("inventarios_catalogos_unidades_medida_eliminadas_catalogo", [INVENT_UMedidaController::class, "unidadesMedidaEliminadasCatalogo"]);
		Route::post("inventarios_catalogos_unidades_medida_generales_restaurar", [INVENT_UMedidaController::class, "unidadesMedidaRestaurar"]);
		Route::post("inventarios_catalogos_unidades_medida_generales_eliminacion_permanente", [INVENT_UMedidaController::class, "unidadesMedidaEliminacionPermanente"]);
		Route::get("inventarios_catalogos_unidades_medida_sat_catalogo", [INVENT_UMedidaController::class, "catalogoPrdServ"]);

		//Reportes
		//Lista general de productos y servicios
		//Kardex
		//Reporte de stock
		//Reporte de productos comprometidos
		//Reporte de productos en tránsito por compra
		//Reporte de productos en tránsito por venta
		//Reporte de rotación de productos

	//produccion en proceso  
		//Planeación
		//Lista de recursos para producción
		//Materia prima planeada
		//Recurso humano planeado
		//Otros conceptos planeados
		//Cronología de trabajo
		//Producción
		//Orden de producción
		//Recibo de materiales para producción
		//Recursos consumidos durante la producción
		//Reportes
		//Lista general de partidas
		//Lista de partidas abiertas
		//Lista de partidas cerradas
		//Reporte comparativo entre planeado y ejecutado

	//finanzas
		//Registro de movimientos financieros
		Route::post("finanzas_catalogos_catalogo_movimientos_bancarios_cuent", [FNZS_MovimientosDineroController::class, "movimientosBancariosCuentasAll"]);
		Route::post("finanzas_catalogos_movimientos_bancarios_cuenta_selected", [FNZS_MovimientosDineroController::class, "movimientosBancariosCuentaToken"]);
		Route::post("finanzas_catalogos_registra_ajuste_cuenta_sin_auth", [FNZS_MovimientosDineroController::class, "movimientosBancariosCuentasAll"]);
		Route::post("finanzas_catalogos_registra_ajuste_cuenta_autorizado", [FNZS_MovimientosDineroController::class, "registra_ajuste_cuenta_autorizado"]);
		//Registro de entrada de dinero
		//Registro de salida de dinero
		//Movimiento entre cuentas propias
		Route::post("finanzas_mov_financieros_catalogo_movimiento_cuentas_propias", [FNZS_MovimientosDineroController::class, "movimiento_cuentas_propias_catalogo"]);
		Route::post("finanzas_mov_financieros_catalogo_movimiento_cpropias_cancela", [FNZS_MovimientosDineroController::class, "movimiento_cuentas_propias_cancelar"]);
		Route::post("finanzas_mov_financieros_catalogo_movimiento_cpropias_cancelados", [FNZS_MovimientosDineroController::class, "movimiento_cuentas_propias_cancelados"]);
		Route::post("finanzas_mov_financieros_registra_movimiento_cuentas_propias", [FNZS_MovimientosDineroController::class, "movimiento_cuentas_propias_registro"]);
		//Reconciliación interna financiera
		//Registro manual de ajustes
		//Corte financiero de punto de notas de venta mostrador
		//Conciliación de fondo de caja
		//Conciliación bancaria
		//Conciliación de monederos electrónicos
		//Conciliación de plataformas electrónicas
		//Catalogos
		//Acreedores
		Route::post("finanzas_catalogos_acreedores_nombres_relacionados", [FNZS_AcreedoresController::class, "catalogoNombresRelacionados"]);
		Route::post("finanzas_catalogos_acreedores_lista_general", [FNZS_AcreedoresController::class, "acreedoresCatGeneral"]);
		Route::post("finanzas_catalogos_acreedores_mx", [FNZS_AcreedoresController::class, "acreedoresCatMx"]);
		Route::post("finanzas_catalogos_acreedores_extranjeros", [FNZS_AcreedoresController::class, "acreedoresCatExt"]);
		Route::post("finanzas_catalogos_acreedores_detalle_generales", [FNZS_AcreedoresController::class, "acreedorDetalleInfoGeneral"]);
		Route::post("finanzas_catalogos_acreedores_detalle_pagos", [FNZS_AcreedoresController::class, "acreedorDetalleInfoPagos"]);
		Route::post("finanzas_catalogos_acreedores_actualiza", [FNZS_AcreedoresController::class, "actualizaAcreedor"]);
		Route::post("finanzas_catalogos_acreedores_elimina_papelera", [FNZS_AcreedoresController::class, "eliminaAcreedorPapelera"]);
		Route::post("finanzas_catalogos_acreedores_eliminados", [FNZS_AcreedoresController::class, "acreedoresCatEliminados"]);
		Route::post("finanzas_catalogos_acreedores_restaurar", [FNZS_AcreedoresController::class, "restaurarAcreedor"]);
		Route::post("finanzas_catalogos_acreedores_elimina_permanente", [FNZS_AcreedoresController::class, "eliminaAcreedorPermanente"]);
		Route::post("finanzas_catalogos_acreedores_registra", [FNZS_AcreedoresController::class, "registrarAcreedor"]);

		//Deudores
		Route::post("finanzas_catalogos_deudores_nombres_relacionados", [FNZS_DeudoresController::class, "catalogoNombresRelacionados"]);
		Route::post("finanzas_catalogos_deudores_lista_general", [FNZS_DeudoresController::class, "deudoresCatGeneral"]);
		Route::post("finanzas_catalogos_deudores_mx", [FNZS_DeudoresController::class, "deudoresCatMx"]);
		Route::post("finanzas_catalogos_deudores_extranjeros", [FNZS_DeudoresController::class, "deudoresCatExt"]);
		Route::post("finanzas_catalogos_deudores_detalle_generales", [FNZS_DeudoresController::class, "deudorDetalleInfoGeneral"]);
		Route::post("finanzas_catalogos_deudores_detalle_pagos", [FNZS_DeudoresController::class, "deudorDetalleInfoPagos"]);
		Route::post("finanzas_catalogos_deudores_actualiza", [FNZS_DeudoresController::class, "actualizaDeudor"]);
		Route::post("finanzas_catalogos_deudores_elimina_papelera", [FNZS_DeudoresController::class, "eliminaDeudorPapelera"]);
		Route::post("finanzas_catalogos_deudores_eliminados", [FNZS_DeudoresController::class, "deudoresCatEliminados"]);
		Route::post("finanzas_catalogos_deudores_restaurar", [FNZS_DeudoresController::class, "restaurarDeudor"]);
		Route::post("finanzas_catalogos_deudores_elimina_permanente", [FNZS_DeudoresController::class, "eliminaDeudorPermanente"]);
		Route::post("finanzas_catalogos_deudores_registra", [FNZS_DeudoresController::class, "registrarDeudor"]);

		//Punto de venta mostrador
		Route::post("finanzas_catalogos_puntodeventa_lista", [FNZS_PuntoVentaController::class, "pventaAssocCatalogo"]);
		Route::post("finanzas_catalogos_puntodeventa_solicita_valid", [FNZS_PuntoVentaController::class, "requestValidacionPventa"]);
		Route::post("finanzas_catalogos_puntodeventa_validate_proceso", [FNZS_PuntoVentaController::class, "validacionProcesoPventa"]);
		Route::post("finanzas_catalogos_puntodeventa_actualizar", [FNZS_PuntoVentaController::class, "pventaActualizar"]);
		Route::post("finanzas_catalogos_puntodeventa_papelera_save", [FNZS_PuntoVentaController::class, "pventaPapeleraSave"]);
		Route::post("finanzas_catalogos_puntodeventa_papelera_catalogo", [FNZS_PuntoVentaController::class, "pventaAssocCatalogoEliminados"]);
		Route::post("finanzas_catalogos_puntodeventa_restaurar", [FNZS_PuntoVentaController::class, "pventaPapeleraRestaurar"]);
		Route::post("finanzas_catalogos_puntodeventa_eliminar", [FNZS_PuntoVentaController::class, "pventaDeletePerm"]);
		Route::post("finanzas_catalogos_puntodeventa_registrar", [FNZS_PuntoVentaController::class, "registroPventaAssoc"]);

		//Fondo de caja
		Route::post("finanzas_catalogos_foliocaja", [FNZS_CajaController::class, "folioCaja"]);
		Route::post("finanzas_catalogos_catalogo_cajas_true", [FNZS_CajaController::class, "catalogoCajasActual"]);
		Route::post("finanzas_catalogos_catalogo_cajas_deleted", [FNZS_CajaController::class, "catalogoCajasDeleted"]);
		Route::post("finanzas_catalogos_detallecaja", [FNZS_CajaController::class, "detalleCajaVig"]);
		Route::post("finanzas_catalogos_responsablecaja", [FNZS_CajaController::class, "respCaja"]);
		Route::post("finanzas_catalogos_registracaja", [FNZS_CajaController::class, "registraCaja"]);
		Route::put("finanzas_catalogos_updatealmacencaja", [FNZS_CajaController::class, "updateAlmacenCaja"]);
		Route::post("finanzas_catalogos_chngperscja", [FNZS_CajaController::class, "desvincRespCaja"]);
		Route::post("finanzas_catalogos_vnculspnbcaja", [FNZS_CajaController::class, "vinculaRespCaja"]);
		Route::post("finanzas_catalogos_updtpersnew", [FNZS_CajaController::class, "updateAlmacenNewCaja"]);
		Route::post("finanzas_catalogos_updatecaja", [FNZS_CajaController::class, "updateCaja"]);
		Route::post("finanzas_catalogos_editacortecja", [FNZS_CajaController::class, "editaCorteCaja"]);
		Route::post("finanzas_catalogos_newcortecja", [FNZS_CajaController::class, "agregaNewCorteCaja"]);
		Route::post("finanzas_catalogos_eliminacortecja", [FNZS_CajaController::class, "deleteCorteCaja"]);
		Route::post("finanzas_catalogos_eliminacaja", [FNZS_CajaController::class, "deleteCaja"]);
		Route::post("finanzas_catalogos_restauracaja", [FNZS_CajaController::class, "restaurarCaja"]);
		Route::post("finanzas_catalogos_eliminapermcj", [FNZS_CajaController::class, "eliminaPrmannteCaja"]);
		Route::post("finanzas_catalogos_unvinccajadispositivo", [TICS_DispositivosController::class, "unvincCajaDispositivo"]);

		//Cuentas bancarias
		Route::post("finanzas_catalogos_foliocuentabanc", [FNZS_CuentBancController::class, "folioCuentaBancaria"]);
		Route::post("finanzas_catalogos_responsablecuenta", [FNZS_CuentBancController::class, "responsableCuenta"]);
		Route::post("finanzas_catalogos_cuentasvig", [FNZS_CuentBancController::class, "cuentasVig"]);
		Route::post("finanzas_catalogos_ver_cuenta_bancaria_completa", [FNZS_CuentBancController::class, "cuentaBancariaCompleta"]);
		Route::post("finanzas_catalogos_ver_cuenta_bancaria_4_digitos", [FNZS_CuentBancController::class, "cuentaBancaria4Digitos"]);
		Route::post("finanzas_catalogos_cuentasdel", [FNZS_CuentBancController::class, "cuentasDel"]);
		Route::post("finanzas_catalogos_detallecuentavig", [FNZS_CuentBancController::class, "detalleCuentasVig"]);
		Route::post("finanzas_catalogos_detalleCuentaMonBancovig", [FNZS_CuentBancController::class, "detalleCuentaMonederoCBancoVig"]);
		Route::post("finanzas_catalogos_registracuentabancaria", [FNZS_CuentBancController::class, "registraCuentaBanc"]);
		Route::post("finanzas_catalogos_updatecuentbncaria", [FNZS_CuentBancController::class, "updateCuentaBanc"]);
		Route::post("finanzas_catalogos_eliminacuentaban", [FNZS_CuentBancController::class, "deleteCuentaBancaria"]);
		Route::post("finanzas_catalogos_restauracuentaban", [FNZS_CuentBancController::class, "restaurarCuentaBancaria"]);
		Route::post("finanzas_catalogos_deltepermcuentaban", [FNZS_CuentBancController::class, "deltPermanenteCuentaBancaria"]);
		Route::post("finanzas_catalogos_actualizacuentabankdispositivo", [TICS_DispositivosController::class, "actualizaCuentaBankDispositivo"]);
		Route::post("finanzas_catalogos_unvinccuentabankdispositivo", [TICS_DispositivosController::class, "unvincCuentaBankDispositivo"]);
		Route::post("finanzas_catalogos_actualizacuentamoneddispositivo", [TICS_DispositivosController::class, "actualizaCuentaMonedDispositivo"]);
		Route::post("finanzas_catalogos_unvinccuentamoneddispositivo", [TICS_DispositivosController::class, "actualizaCuentaMonedDispositivo"]);

		//Dispositivos
		//Monederos electrónicos
		Route::post("finanzas_catalogos_foliomonelectronico", [FNZS_MonedElectController::class, "folioMonederoElectronico"]);
		Route::post("finanzas_catalogos_responsablemonedero", [FNZS_MonedElectController::class, "responsableMonedero"]);
		Route::post("finanzas_catalogos_verlistamonedero", [FNZS_MonedElectController::class, "ListaMonederoVig"]);
		Route::post("finanzas_catalogos_verlistamonederodel", [FNZS_MonedElectController::class, "ListaMonederoDel"]);
		Route::post("finanzas_catalogos_detallemonedero", [FNZS_MonedElectController::class, "detalleMonederoVig"]);
		Route::post("finanzas_catalogos_actualizamonederoelectronico", [FNZS_MonedElectController::class, "updateMonederoElectronico"]);
		Route::post("finanzas_catalogos_registramonederoelctrnico", [FNZS_MonedElectController::class, "registrarMonederoElectronico"]);
		Route::post("finanzas_catalogos_eliminamonelectronico", [FNZS_MonedElectController::class, "eliminarMonederoElctronico"]);
		Route::post("finanzas_catalogos_restauramonelectronico", [FNZS_MonedElectController::class, "restaurarMonederoElctronico"]);
		Route::post("finanzas_catalogos_deletPermmonederoelctrnico", [FNZS_MonedElectController::class, "deletPermMonederoElctronico"]);
		//Plataformas electrónicas
		//Cuentas bancarias
		Route::post("finanzas_catalogos_fed_est_mun_registro", [FNZS_FedEstadosMunicipiosController::class, "fedEstMunRegistro"]);
		Route::post("finanzas_catalogos_fed_est_mun_catalogo_activo", [FNZS_FedEstadosMunicipiosController::class, "fedEstMunList"]);
		Route::post("finanzas_catalogos_fed_est_mun_detalle", [FNZS_FedEstadosMunicipiosController::class, "fedEstMunDetalle"]);
		Route::post("finanzas_catalogos_fed_est_mun_update", [FNZS_FedEstadosMunicipiosController::class, "fedEstMunActualiza"]);
		Route::post("finanzas_catalogos_fed_est_mun_eliminar", [FNZS_FedEstadosMunicipiosController::class, "fedEstMunEliminar"]);
		Route::post("finanzas_catalogos_fed_est_mun_catalogo_eliminados", [FNZS_FedEstadosMunicipiosController::class, "fedEstMunDeletedList"]);
		Route::post("finanzas_catalogos_fed_est_mun_restaurar", [FNZS_FedEstadosMunicipiosController::class, "fedEstMunRestaurar"]);
		Route::post("finanzas_catalogos_fed_est_mun_perm_delete", [FNZS_FedEstadosMunicipiosController::class, "fedEstMunEliminacionPerm"]);
		//Indicadores económicos
		Route::get("finanzas_indicadores_inpc", [FNZS_IndicadoresController::class, "indicadores_inpc"]);
		Route::get("finanzas_indicadores_tasa_recargos", [FNZS_IndicadoresController::class, "indicadores_tasa_recargos"]);
		Route::get("finanzas_indicadores_tipo_cambio", [FNZS_IndicadoresController::class, "indicadores_tipo_cambio"]);
		Route::get("finanzas_indicadores_salario_minimo", [FNZS_IndicadoresController::class, "indicadores_salario_minimo"]);
		Route::get("finanzas_indicadores_salario_min_front", [FNZS_IndicadoresController::class, "indicadores_salario_min_front"]);
		Route::get("finanzas_indicadores_uma", [FNZS_IndicadoresController::class, "indicadores_uma"]);
		Route::get("finanzas_indicadores_udi", [FNZS_IndicadoresController::class, "indicadores_udi"]);
		Route::get("finanzas_indicadores_tiie", [FNZS_IndicadoresController::class, "indicadores_tiie"]);

		//Monedas y divisas  

		//Reportes
		//Lista de ordenes de cobro
		//Lista de ordenes de pago
		Route::post("finanzas_orden_pago_listageneralordenespago", [FNZS_PagoOrdenController::class, "listaGeneralOrdenesPago"]);
		Route::post("finanzas_orden_pago_listaordenespagopendientes", [FNZS_PagoOrdenController::class, "listaOrdenesPendientes"]);
		Route::post("finanzas_orden_pago_listaordenespagoliberadas", [FNZS_PagoOrdenController::class, "listaOrdenesLiberadas"]);
		Route::post("finanzas_orden_pago_listaordenespagoparacompras", [FNZS_PagoOrdenController::class, "listaOrdenForCompra"]);
		Route::post("finanzas_orden_pago_listaordenespagoconcluidas", [FNZS_PagoOrdenController::class, "listaOrdenesConcluidas"]);
		Route::post("finanzas_orden_pago_autorizar_orden_pago", [FNZS_PagoOrdenController::class, "autorizarOrdenPago"]);
		Route::post("finanzas_orden_pago_autorizar_ordenes_pago", [FNZS_PagoOrdenController::class, "autorizarOrdenesPago"]);
		Route::post("finanzas_orden_pago_desautorizar_orden_pago", [FNZS_PagoOrdenController::class, "desautorizarOrdenPago"]);
		Route::post("finanzas_orden_pago_actualizar_orden_pago", [FNZS_PagoOrdenController::class, "actualizarOrdenPago"]);
		Route::post("finanzas_orden_pago_ordenpago_registrapagosimple", [FNZS_PagoOrdenController::class, "generaPagoSimple"]);
		Route::post("finanzas_orden_pago_ordenpago_registra_movimiento_acreedor", [FNZS_PagoOrdenController::class, "registraMovimientoAcreedor"]);
		Route::post("finanzas_orden_pago_ordenpago_registra_movimiento_deudor", [FNZS_PagoOrdenController::class, "registraMovimientoDeudor"]);
		Route::post("finanzas_orden_pago_catalogo_pagos_done", [FNZS_PagoOrdenController::class, "catalogoPagosDone"]);
		Route::post("finanzas_orden_pago_catalogo_pagos_desglose", [FNZS_PagoOrdenController::class, "catalogoPagosDesglose"]);
		//compras
		Route::post("finanzas_orden_pago_countordenespago", [FNZS_PagoOrdenController::class, "countOrdenPagoCompras"]);
		Route::post("finanzas_orden_pago_listaordenespagocompras", [FNZS_PagoOrdenController::class, "listaOrdenPagoCompras"]);
		Route::post("finanzas_orden_pago_detalleordenpagocompras", [FNZS_PagoOrdenController::class, "detalleOrdenPagoCompras"]);
		Route::post("finanzas_orden_pago_registrapagodirecto", [FNZS_PagoOrdenController::class, "pagarOrdenPagoDirecto"]);
		//reembolsos
		Route::post("finanzas_orden_pago_op_reembolso_lista", [FNZS_PagoOrdenController::class, "reembolso_op_lista"]);
		Route::post("finanzas_orden_pago_op_reembolso_detalle", [FNZS_PagoOrdenController::class, "reembolso_op_detalle"]);
		Route::post("finanzas_orden_pago_registrapagoreembolso_nivel_uno", [FNZS_PagoOrdenController::class, "pagarOrdenPagoReembolso"]);
		Route::post("finanzas_orden_pago_registrapagoreembolso_directo", [FNZS_PagoOrdenController::class, "pagarReembolso"]);
		Route::post("finanzas_orden_pago_detenerpagoreembolso", [FNZS_PagoOrdenController::class, "desautorizarPagoReembolso"]);
		Route::post("finanzas_orden_pago_autorizarpagoreembolso", [FNZS_PagoOrdenController::class, "autorizarPagoReembolso"]);
		//Ordenes de dispersión de nómina
		Route::post("finanzas_orden_dispersion_lista_general", [FNZS_PagoDispersionNominaOrdenController::class, "listaGeneralDispersion"]);
		Route::post("finanzas_orden_dispersion_lista_pendientes", [FNZS_PagoDispersionNominaOrdenController::class, "listaPendientesDispersion"]);
		Route::post("finanzas_orden_dispersion_lista_liberadas", [FNZS_PagoDispersionNominaOrdenController::class, "listaLiberadasDispersion"]);
		Route::post("finanzas_orden_pago_nomina_desglose", [FNZS_PagoDispersionNominaOrdenController::class, "nominaDesgloseOrdenPago"]);
		Route::post("finanzas_orden_pago_nomina_especie_desglose", [FNZS_PagoDispersionNominaOrdenController::class, "nominaEspecieDesgloseOrdenPago"]);
		Route::post("finanzas_orden_pago_ordenpago_registra_dispersion_nomina", [FNZS_PagoDispersionNominaOrdenController::class, "generaPagoNominaDispersion"]);
		Route::post("finanzas_orden_pago_ordenpago_registra_pago_nomina_especie", [FNZS_PagoDispersionNominaOrdenController::class, "generaPagoNominaEspecie"]);
		Route::post("finanzas_orden_dispersion_lista_concluidas", [FNZS_PagoDispersionNominaOrdenController::class, "listaConcluidasDispersion"]);
		Route::post("finanzas_orden_dispersion_lista_pagos_done", [FNZS_PagoDispersionNominaOrdenController::class, "catalogoPagosDone"]);
		Route::post("finanzas_orden_dispersion_desglose_pago_nomina", [FNZS_PagoDispersionNominaOrdenController::class, "desglosePagosNominaDispersion"]);
		Route::post("finanzas_orden_dispersion_lista_pagos_trabajador", [FNZS_PagoDispersionNominaOrdenController::class, "trabajador_desglose_pagos"]);
		Route::post("finanzas_orden_dispersion_catalogo_trabajadores", [FNZS_PagoDispersionNominaOrdenController::class, "catalogo_nomina_trabajadores"]);
		//Estado de movimientos financieros
		Route::post("finanzas_reportes_estado_movimientos_financieros_caja", [FNZS_EstadoMovFinanCajaController::class, "movimientosFinancierosCaja"]);
		Route::post("finanzas_reportes_estado_movimientos_financieros_cuenta", [FNZS_EstadoMovFinanCuentController::class, "movimientosFinancierosCuentaBancaria"]);
		Route::post("finanzas_reportes_estado_movimientos_financieros_monedero_electronico", [FNZS_EstadoMovFinanMonedController::class, "movimientosFinancierosMonederoElectronico"]);
		Route::post("finanzas_reportes_estado_movimientos_financieros_cliente", [FNZS_EstadoMovFinanClienteController::class, "movimientosFinancierosCliente"]);
		Route::post("finanzas_reportes_estado_movimientos_financieros_deudor", [FNZS_EstadoMovFinanDeudorController::class, "movimientosFinancierosDeudor"]);
		Route::post("finanzas_reportes_estado_movimientos_financieros_proveedor", [FNZS_EstadoMovFinanProveedorController::class, "movimientosFinancierosProveedor"]);
		Route::post("finanzas_reportes_estado_movimientos_financieros_acreedor", [FNZS_EstadoMovFinanAcreedorController::class, "movimientosFinancierosAcreedor"]);
    //solicitudes de cancelacion
		Route::post("finanzas_orden_pago_solicitar_cancelacion_anticipo", [FNZS_SolicitudesCancelacionController::class, "anticipoSolicitarCancelacion"]);
		Route::post("finanzas_orden_pago_solicitar_cancelacion_orden_pago", [FNZS_SolicitudesCancelacionController::class, "ordenPagoSolicitarCancelacion"]);
		Route::post("finanzas_orden_pago_solicitar_cancelacion_pago", [FNZS_SolicitudesCancelacionController::class, "pagoRealizadoSolicitarCancelacion"]);
		Route::post("finanzas_orden_pago_solicitudes_de_cancelacion", [FNZS_SolicitudesCancelacionController::class, "solicitudesCancelacion"]);
		Route::post("finanzas_orden_pago_actualiza_solicitud_de_cancelacion", [FNZS_SolicitudesCancelacionController::class, "recargaSolicitudCancelacion"]);
		Route::post("finanzas_orden_pago_solicitud_cancelacion_anticipo", [FNZS_SolicitudesCancelacionController::class, "solicitudCancelacionAnticipo"]);
		Route::post("finanzas_orden_pago_confirmar_cancelacion_anticipo", [FNZS_SolicitudesCancelacionController::class, "confirmarCancelacionAnticipo"]);
		Route::post("finanzas_orden_pago_solicitud_cancelacion_pago", [FNZS_SolicitudesCancelacionController::class, "solicitudCancelacionPago"]);
		Route::post("finanzas_orden_pago_confirmar_cancelacion_pago", [FNZS_SolicitudesCancelacionController::class, "confirmarCancelacionPago"]);
		Route::post("finanzas_orden_pago_solicitud_cancelacion_orden_pago", [FNZS_SolicitudesCancelacionController::class, "solicitudCancelacionOrdenPago"]);
		Route::post("finanzas_orden_pago_confirmar_cancelacion_orden_pago", [FNZS_SolicitudesCancelacionController::class, "confirmarCancelacionOrdenPago"]);
		Route::post("finanzas_orden_pago_solicitud_cancelacion_reembolso_orden_pago", [FNZS_SolicitudesCancelacionController::class, "solicitudCancelacionReembolsoOrdenPago"]);
		Route::post("finanzas_orden_pago_confirmar_cancelacion_reembolso_orden_pago", [FNZS_SolicitudesCancelacionController::class, "confirmarCancelacionReembolsoOrdenPago"]);
		Route::post("finanzas_orden_pago_solicitud_cancelacion_mcp", [FNZS_SolicitudesCancelacionController::class, "solicitudCancelacionMCP"]);
		Route::post("finanzas_orden_pago_confirmar_cancelacion_mcp", [FNZS_SolicitudesCancelacionController::class, "confirmarCancelacionMCP"]);
		Route::post("finanzas_orden_dispersion_nomina_pago_solicitar_cancelacion", [FNZS_SolicitudesCancelacionController::class, "pagoRealizadoDispersionSolicitarCancelacion"]);
		Route::post("finanzas_orden_dispersion_nomina_pago_solicitud_cancelacion", [FNZS_SolicitudesCancelacionController::class, "solicitudCancelacionDispersionPago"]);
		Route::post("finanzas_orden_dispersion_nomina_pago_confirmar_cancelacion", [FNZS_SolicitudesCancelacionController::class, "confirmarCancelacionDispersionPago"]);

		Route::post("finanzas_orden_dispersion_nomina_efectivo_solicitar_cancelacion", [FNZS_SolicitudesCancelacionController::class, "disperNominaEfectSolicitaCancelacion"]);
		Route::post("finanzas_orden_dispersion_nomina_efectivo_solicitud_cancelacion", [FNZS_SolicitudesCancelacionController::class, "solicitudCancelacionOrdenDispersionNominaEfectivo"]);
		Route::post("finanzas_orden_dispersion_nomina_efectivo_confirmar_cancelacion", [FNZS_SolicitudesCancelacionController::class, "confirmarCancelacionOrdenDispersionNominaEfectivo"]);
		Route::post("finanzas_orden_dispersion_nomina_especie_solicitar_cancelacion", [FNZS_SolicitudesCancelacionController::class, "dispeNnominaEspecieSolicitaCancelacion"]);
		Route::post("finanzas_orden_dispersion_nomina_especie_solicitud_cancelacion", [FNZS_SolicitudesCancelacionController::class, "solicitudCancelacionOrdenDispersionNominaEspecie"]);
		Route::post("finanzas_orden_dispersion_nomina_especie_confirmar_cancelacion", [FNZS_SolicitudesCancelacionController::class, "confirmarCancelacionOrdenDispersionNominaEspecie"]);

	//valor_humano
		//Registros vinculados con valor humano
		//Resposivas de equipo, herremientas y otros
		//Asistencias e incidencias
		Route::post("valor_humano_checador_entrada_personal", [VHUM_TrabajadoresController::class, "asistenciaPersonalEntrada"]);
		Route::post("valor_humano_checador_salida_personal", [VHUM_TrabajadoresController::class, "asistenciaPersonalSalida"]);
		Route::post("valor_humano_comision_registro_aviso_vhum", [MAIN_ComisionesController::class, "comisionRegistroAvisoVhum"]);
		//Solicitud de vacaciones
		//Permisos laborales
		//Incapacidades
		//Incidencia laboral
		//Acta administrativa laboral
		//Descuentos a la nomina

		//Catálogos 
		//Trabajadores
		Route::post("valor_humano_catalogo_general_trabajadores", [VHUM_TrabajadoresController::class, "catalogo_general_trabajadores"]);
		Route::post("valor_humano_catalogo_trabajadores_por_registro_patronal", [VHUM_TrabajadoresController::class, "catalogo_trabajadores_por_registro_patronal"]);
		Route::post("valor_humano_trabajadores_detalle", [VHUM_TrabajadoresController::class, "trabajador_detalle"]);
		Route::post("valor_humano_trabajadores_info_para_nominas", [VHUM_TrabajadoresController::class, "trabajador_info_para_nominas"]);
		Route::post("valor_humano_trabajadores_info_para_nominas_by_nss", [VHUM_TrabajadoresController::class, "trabajador_info_para_nominas_by_nss"]);
		Route::post("valor_humano_catalogos_actualizatrabajador", [VHUM_TrabajadoresController::class, "actualizaTrabajador"]);
		Route::post("valor_humano_catalogos_alta_trabajador", [VHUM_TrabajadoresController::class, "altaTrabajador"]);
		Route::post("valor_humano_catalogos_baja_trabajador", [VHUM_TrabajadoresController::class, "bajaTrabajador"]);
		Route::post("valor_humano_catalogo_trabajadores_activos", [VHUM_TrabajadoresController::class, "catalogo_trabajadores_activos"]);
		Route::post("valor_humano_catalogo_trabajadores_inactivos", [VHUM_TrabajadoresController::class, "catalogo_trabajadores_inactivos"]);
		Route::post("valor_humano_trabajadores_eliminar", [VHUM_TrabajadoresController::class, "trabajador_eliminar"]);
		Route::post("valor_humano_catalogo_trabajadores_eliminados", [VHUM_TrabajadoresController::class, "catalogo_trabajadores_eliminados"]);
		Route::post("valor_humano_trabajadores_restaurar", [VHUM_TrabajadoresController::class, "trabajador_restaurar"]);
		Route::post("valor_humano_trabajadores_eliminacion_permanente", [VHUM_TrabajadoresController::class, "trabajador_eliminacion_permanente"]);
		Route::post("valor_humano_catalogo_empleados_empresa", [VHUM_TrabajadoresController::class, "catalogo_empleados_SOS"]);
		Route::post("valor_humano_actualizapaternopersonal", [VHUM_TrabajadoresController::class, "actualizaPaternoPersonalSOS"]);
		Route::post("valor_humano_actualizamaternopersonal", [VHUM_TrabajadoresController::class, "actualizaMaternoPersonalSOS"]);
		Route::post("valor_humano_actualizanombrespersonal", [VHUM_TrabajadoresController::class, "actualizaNombresPersonalSOS"]);
		Route::post("valor_humano_actualizaareapersonal", [VHUM_TrabajadoresController::class, "actualizaAreaPersonalSOS"]);
		Route::post("valor_humano_actualizaemailpersonal", [VHUM_TrabajadoresController::class, "actualizaMailPersonalSOS"]);
		Route::post("valor_humano_registratelefonopersonal", [VHUM_TrabajadoresController::class, "registraTelefonoPersonalSOS"]);
		Route::post("valor_humano_actualizatelefonopersonal", [VHUM_TrabajadoresController::class, "actualizaTelefonoPersonalSOS"]);
		Route::post("valor_humano_listapersgeneral", [VHUM_TrabajadoresController::class, "listaPersonalGneral"]);
		Route::post("valor_humano_listapersgeneralarea", [VHUM_TrabajadoresController::class, "listaPersonalArea"]);
		Route::post("valor_humano_catalogos_registratrabajador", [VHUM_TrabajadoresController::class, "registraTrabajador"]);
		//viaticos y otros conceptos
		//percepciones y deducciones
		//Clave patronal
		//Centro de trabajo
		Route::post("valor_humano_catalogos_registra_centro_trabajo", [VHUM_CentrosDeTrabajoController::class, "registraCentroDeTrabajo"]);
		Route::post("valor_humano_catalogos_centros_de_trabajo", [VHUM_CentrosDeTrabajoController::class, "catalogoCentrosDeTrabajo"]);
		Route::post("valor_humano_centros_de_trabajo_detalle", [VHUM_CentrosDeTrabajoController::class, "detalleCentroDeTrabajo"]);
		Route::post("valor_humano_centros_de_trabajo_actualiza", [VHUM_CentrosDeTrabajoController::class, "actualizaCentroDeTrabajo"]);
		Route::post("valor_humano_catalogos_centros_de_trabajo_activos", [VHUM_CentrosDeTrabajoController::class, "catalogoCentrosActivosDeTrabajo"]);
		Route::post("valor_humano_catalogos_centros_de_trabajo_inactivos", [VHUM_CentrosDeTrabajoController::class, "catalogoCentrosInactivosDeTrabajo"]);
		Route::post("valor_humano_catalogos_centros_de_trabajo_eliminados", [VHUM_CentrosDeTrabajoController::class, "catalogoEliminadosCentrosDeTrabajo"]);
		Route::post("valor_humano_catalogos_alta_centro_trabajo", [VHUM_CentrosDeTrabajoController::class, "altaCentroDeTrabajo"]);
		Route::post("valor_humano_catalogos_baja_centro_trabajo", [VHUM_CentrosDeTrabajoController::class, "bajaCentroDeTrabajo"]);
		Route::post("valor_humano_catalogos_elimina_centro_trabajo", [VHUM_CentrosDeTrabajoController::class, "eliminaCentroDeTrabajo"]);
		Route::post("valor_humano_catalogos_restaura_centro_trabajo", [VHUM_CentrosDeTrabajoController::class, "restauraCentrosDeTrabajo"]);
		Route::post("valor_humano_catalogos_eliminacion_permanente_centro_trabajo", [VHUM_CentrosDeTrabajoController::class, "eliminacionPermanenteCentrosDeTrabajo"]);
		//Jornadas de trabajo
		//Reportes
		//Analisis de nomina
		Route::post("valor_humano_nomina_reportes", [VHUM_NominasController::class, "reportesNominaTrabajadores"]);
		Route::post("valor_humano_nomina_efectivo_seguimiento_pagos", [VHUM_NominasController::class, "nominaEfectivoSeguimientoOrdenPago"]);
		Route::post("valor_humano_nomina_especie_seguimiento_pagos", [VHUM_NominasController::class, "nominaEspecieSeguimientoOrdenPago"]);
		Route::post("valor_humano_nomina_desglose_dispersion", [VHUM_NominasController::class, "nominaDesgloseDispersion"]);
		Route::post("valor_humano_nomina_carga_cfdi", [VHUM_NominasController::class, "nominaCargaCFDIS"]);
		Route::post("valor_humano_nomina_genera_registro", [VHUM_NominasController::class, "registraNominaTrabajadores"]);
		Route::post("valor_humano_nomina_eliminar", [VHUM_NominasController::class, "eliminaNominaTrabajadores"]);
		Route::post("valor_humano_nomina_reportes_eliminados", [VHUM_NominasController::class, "reportesDeletedNominaTrabajadores"]);
		Route::post("valor_humano_nomina_restaurar", [VHUM_NominasController::class, "restauraNominaTrabajadores"]);
		Route::post("valor_humano_nomina_eliminacion_permanente", [VHUM_NominasController::class, "eliminaPermanenteNominaTrabajadores"]);
		//Analisis de asimilados
		Route::post("valor_humano_asimilados_reportes", [VHUM_AsimiladosController::class, "reportesAsimilados"]);
		Route::post("valor_humano_asimilados_seguimiento_pagos", [VHUM_AsimiladosController::class, "asimiladoSeguimientoOrdenPago"]);
		Route::post("valor_humano_asimilados_genera_registro", [VHUM_AsimiladosController::class, "registraAsimiladoReporte"]);
		Route::post("valor_humano_asimilados_desglose", [VHUM_AsimiladosController::class, "asimiladoDesglose"]);
		Route::post("valor_humano_asimilados_actualizar", [VHUM_AsimiladosController::class, "actualizaAsimiladoReporte"]);
		Route::post("valor_humano_asimilados_eliminar", [VHUM_AsimiladosController::class, "eliminaAsimiladoReporte"]);
		Route::post("valor_humano_asimilados_reportes_eliminados", [VHUM_AsimiladosController::class, "reportesDeletedAsimilados"]);
		Route::post("valor_humano_asimilados_restaurar", [VHUM_AsimiladosController::class, "restauraAsimiladoReporte"]);
		Route::post("valor_humano_asimilados_eliminacion_permanente", [VHUM_AsimiladosController::class, "eliminaPermanenteAsimiladoReporte"]);

		Route::post("valor_humano_impuesto_sobre_nomina_registro", [VHUM_ImpuestosSobreNominaController::class, "registraNominaImpuestos"]);
		Route::post("valor_humano_impuesto_sobre_nomina_reportes", [VHUM_ImpuestosSobreNominaController::class, "listaRegNominaImpuestos"]);
		Route::post("valor_humano_impuesto_sobre_nomina_seguimiento_pagos", [VHUM_ImpuestosSobreNominaController::class, "nominaImpuestosSeguimientoOrdenPago"]);
		Route::post("valor_humano_impuesto_sobre_nomina_desglose", [VHUM_ImpuestosSobreNominaController::class, "desgloseNominaImpuestos"]);
		Route::post("valor_humano_impuesto_sobre_nomina_carga_cfdi", [VHUM_ImpuestosSobreNominaController::class, "nominaImpuestosCargaCFDIS"]);
		Route::post("valor_humano_impuesto_sobre_nomina_actualizar", [VHUM_ImpuestosSobreNominaController::class, "actualizaNominaImpuestos"]);
		Route::post("valor_humano_impuesto_sobre_nomina_eliminar", [VHUM_ImpuestosSobreNominaController::class, "eliminaNominaImpuestos"]);
		Route::post("valor_humano_impuesto_sobre_nomina_reportes_eliminados", [VHUM_ImpuestosSobreNominaController::class, "listaDeletedNominaImpuestos"]);
		Route::post("valor_humano_impuesto_sobre_nomina_restaurar", [VHUM_ImpuestosSobreNominaController::class, "restauraNominaImpuestos"]);
		Route::post("valor_humano_impuesto_sobre_nomina_eliminacion_permanente", [VHUM_ImpuestosSobreNominaController::class, "eliminaPermanenteNominaImpuestos"]);
		
		Route::post("valor_humano_aportaciones_seguridad_social_registro", [VHUM_IMSSController::class, "registraAportacionSeguridadSocial"]);
		Route::post("valor_humano_aportaciones_seguridad_social_reportes", [VHUM_IMSSController::class, "listaRegAportacionSeguridadSocial"]);
		Route::post("valor_humano_aportaciones_seguridad_social_seguimiento_pagos", [VHUM_IMSSController::class, "aportacionSeguridadSocialImpuestosSeguimientoOrdenPago"]);
		Route::post("valor_humano_aportaciones_seguridad_social_desglose", [VHUM_IMSSController::class, "desgloseAportacionSeguridadSocial"]);
		Route::post("valor_humano_aportaciones_seguridad_social_actualizar", [VHUM_IMSSController::class, "actualizaAportacionSeguridadSocial"]);
		Route::post("valor_humano_aportaciones_seguridad_social_carga_cfdi", [VHUM_IMSSController::class, "aportacionSeguridadSocialCargaCFDIS"]);
		Route::post("valor_humano_aportaciones_infonavit_carga_cfdi", [VHUM_IMSSController::class, "aportacionInfonavitCargaCFDIS"]);
		Route::post("valor_humano_aportaciones_seguridad_social_eliminar", [VHUM_IMSSController::class, "eliminaAportacionSeguridadSocial"]);
		Route::post("valor_humano_aportaciones_seguridad_social_reportes_eliminados", [VHUM_IMSSController::class, "listaDeletedAportacionSeguridadSocial"]);
		Route::post("valor_humano_aportaciones_seguridad_social_restaurar", [VHUM_IMSSController::class, "restauraAportacionSeguridadSocial"]);
		Route::post("valor_humano_aportaciones_seguridad_social_eliminacion_permanente", [VHUM_IMSSController::class, "eliminaPermAportacionSeguridadSocial"]);
		//reembolsos
		Route::post("valor_humano_vh_reembolso_lista", [VHUM_ReembolsosController::class, "reembolso_lista"]);
		Route::post("valor_humano_vh_reembolso_detalle", [VHUM_ReembolsosController::class, "reembolso_detalle"]);
		Route::post("valor_humano_vh_reembolso_auth", [VHUM_ReembolsosController::class, "vh_reembolso_auth"]);

	//contabilidad
		//Registros contables
		Route::post("contabilidad_lista_ordenes_devengacion", [CONT_DevengacionesController::class, "listaOrdenesDevengacion"]);
		Route::post("contabilidad_orden_devengacion_detalle", [CONT_DevengacionesController::class, "detalleOrdenDevengacion"]);
		Route::post("contabilidad_lista_servicios_sin_devengar", [CONT_DevengacionesController::class, "listaComprasServSinDevengar"]);
    //Route::post("inventarios_movimientos_recibe_activo", [CONT_DevengacionesController::class, "recibeActivoFijoAlmacen"]);
		Route::post("contabilidad_trueperiodoespera24hrs", [CONT_DevengacionesController::class, "habilitaPeridoEspera"]);
		Route::post("contabilidad_recibeprodutocompras", [CONT_DevengacionesController::class, "recibeProdComprasAlmacen"]);
		Route::post("contabilidad_recibeactintangbuy", [CONT_DevengacionesController::class, "recibeActivoIntangComprasAlmacen"]);
		Route::post("contabilidad_recibeserviciosbuy", [CONT_DevengacionesController::class, "recibeServComprasAlmacen"]);
		Route::post("contabilidad_recepcionescomprasautorizadas", [CONT_DevengacionesController::class, "listaComprasRecepciones"]);
		Route::post("contabilidad_rechazoscomprasautorizadas", [CONT_DevengacionesController::class, "listaComprasRechazos"]);
		//Registro diario
		//Ejecución de depreciaciones contables
		//Ejecución de amortizaciones contables
		//Reconciliación interna contables
		//Registros fiscales
		//Cruce de XML internos vs XML SAT
		//Ingresos acumulables
		//Deducciones autorizadas
		//Declaraciones anuales
		//Declaraciones
		Route::post("contabilidad_declaraciones_imp_federales_registro", [CONT_DeclaracionesController::class, "declaracionRegistro"]);
		Route::post("contabilidad_declaraciones_imp_federales_catalogo", [CONT_DeclaracionesController::class, "catalogoGeneralDeclaraciones"]);
		Route::post("contabilidad_declaraciones_imp_federales_seguimiento_orden_pago", [CONT_DeclaracionesController::class, "declaracionImpFederalesSeguimientoOrdenPago"]);
		Route::post("contabilidad_declaraciones_imp_federales_desglose", [CONT_DeclaracionesController::class, "desgloseDeclaracionImpFederales"]);
		Route::post("contabilidad_declaraciones_imp_federales_actualizacion", [CONT_DeclaracionesController::class, "actualizaDeclaracion"]);
		Route::post("contabilidad_declaraciones_imp_federales_carga_cfdis", [CONT_DeclaracionesController::class, "declaracionCargaCFDIS"]);
		Route::post("contabilidad_declaraciones_imp_federales_delete", [CONT_DeclaracionesController::class, "deleteDeclaracion"]);
		Route::post("contabilidad_declaraciones_imp_federales_deleted_catalogo", [CONT_DeclaracionesController::class, "catalogoDeclaracionesDeleted"]);
		Route::post("contabilidad_declaraciones_imp_federales_restaurar", [CONT_DeclaracionesController::class, "restaurarDeclaracion"]);
		Route::post("contabilidad_declaraciones_imp_federales_delete_perm", [CONT_DeclaracionesController::class, "deletePermDeclaracion"]);
		//Cálculo de ISR Federal
		//Cálculo de IVA
		//Cálculo de IEPS
		//Cálculo de retenciones a terceros
		//Cálculo de retenciones a la nomina
		//Cálculo de ISN
		//Cálculo de aportaciones de seguridad social
		//Cálculo de derechos
		//Cálculo de otras contribuciones
		//Cálculo de actualizaciones y recargos
		//Movimientos ante autoridades fiscales
		//Opinión de cumplimiento
		//Casos de aclaración
		//Perdidas fiscales
		//Seguimiento de saldos a favor de IVA
		//Seguimiento de saldos a favor de ISR
		//Seguimiento a casos de pago de lo indebido
		//Seguimiento a cartas de invitación
		//Seguimiento a requerimientos
		//Seguimiento a créditos fiscales
		//Catálogos
		//Catálogo de cuentas contables
		Route::post("contabilidad_catalogos_cuentas_contables_nivel_uno", [CONT_CuentasContablesController::class, "cuentasContablesNivelUno"]);
		Route::post("contabilidad_catalogos_cuentas_contables_nivel_dos", [CONT_CuentasContablesController::class, "cuentasContablesNivelDos"]);
		Route::post("contabilidad_catalogos_cuenta_contable_lista", [CONT_CuentasContablesController::class, "cuentasContablesCatalogo"]);
		Route::post("contabilidad_catalogos_cuenta_contable_registra", [CONT_CuentasContablesController::class, "cuentaContableRegistro"]);
		//Catálogo de cuentas fiscales
		//Catálogo de productos y servicios SAT
		//Catálogo para DIOT
		//Catálogo general de impuestos
		Route::post("contabilidad_catalogoimpuestos_esquema_registro", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaRegistro"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_catalogo", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaCatalogo"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_catalogo_enabled", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaCatalogoEnabled"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_catalogo_forventas", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaCatalogoForVentas"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_detalle", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaDetalle"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_actualizar", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaActualizar"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_actualizar_agregar", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaActualizarVincular"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_actualizar_remove", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaActualizarDesvincular"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_enable", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaHabilitar"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_disable", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaDeshabilitar"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_papelera_save", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaPapeleraSave"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_eliminados", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaEliminados"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_restaurar", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaPapeleraRestaurar"]);
		Route::post("contabilidad_catalogoimpuestos_esquema_eliminar", [Cont_EsquemasImpuestosController::class, "impuestoEsquemaDeletePerm"]);
		//catalogo_impuestos
		Route::post("contabilidad_catalogoimpuestos_registrar", [CONT_ImpuestosController::class, "impuestoCatalogoRegistro"]);
		Route::post("contabilidad_catalogo_general_impuestos", [CONT_ImpuestosController::class, "catalogoGeneralImpuestos"]);
		Route::post("contabilidad_catalogo_impuestos_declaracion", [CONT_ImpuestosController::class, "catalogoImpuestosDeclaracion"]);
		Route::post("contabilidad_catalogo_general_impuestos_retenciones", [CONT_ImpuestosController::class, "catalogoGeneralImpuestosRetenciones"]);
		Route::post("contabilidad_catalogo_general_impuestos_traslados", [CONT_ImpuestosController::class, "catalogoGeneralImpuestosTraslados"]);
		Route::post("contabilidad_catalogo_general_impuestos_enabled", [CONT_ImpuestosController::class, "catalogoGeneralImpuestosEnabled"]);
		Route::post("contabilidad_catalogoimpuestos_detalle", [CONT_ImpuestosController::class, "catalogoImpuestosDetalle"]);
		Route::post("contabilidad_catalogoimpuestos_actualizar", [CONT_ImpuestosController::class, "impuestoActualizar"]);
		Route::post("contabilidad_catalogoimpuestos_enable", [CONT_ImpuestosController::class, "impuestoHabilitar"]);
		Route::post("contabilidad_catalogoimpuestos_disable", [CONT_ImpuestosController::class, "impuestoDeshabilitar"]);
		Route::post("contabilidad_catalogoimpuestos_papelera_save", [CONT_ImpuestosController::class, "impuestoPapeleraSave"]);
		Route::post("contabilidad_catalogoimpuestos_eliminados", [CONT_ImpuestosController::class, "catalogoImpuestosDel"]);
		Route::post("contabilidad_catalogoimpuestos_restaurar", [CONT_ImpuestosController::class, "impuestoPapeleraRestaurar"]);
		Route::post("contabilidad_catalogoimpuestos_eliminar", [CONT_ImpuestosController::class, "impuestoDeletePerm"]);
		Route::post("contabilidad_viewimpuestoselected", [CONT_ImpuestosController::class, "verImpuesto"]);
		//Catálogo de deducciones para inversiones
		//Catálogo de obligaciones fiscales
		//Catálogo de registros fiscales
		//Indicadores fiscales
    //Activos fijos
		Route::post("contabilidad_activos_fijos_catalogo", [CONT_ActivosFijosDeprecController::class, "contGetActivosFijos"]);
		Route::post("contabilidad_activos_fijos_depreciaciones_pendientes", [CONT_ActivosFijosDeprecController::class, "checkNotificacionesDepreciacion"]);
		Route::post("contabilidad_activos_fijos_inicia_depreciacion", [CONT_ActivosFijosDeprecController::class, "activoFijoRegistraFechaDepreciacion"]);
		Route::post("contabilidad_activos_fijos_bloquea_depreciacion", [CONT_ActivosFijosDeprecController::class, "activoFijoBloqueaDepreciacion"]);
		Route::post("contabilidad_activos_fijos_desbloquea_depreciacion", [CONT_ActivosFijosDeprecController::class, "activoFijoDesbloqueaDepreciacion"]);
		Route::post("contabilidad_activos_fijos_detalle_to_deprec", [CONT_ActivosFijosDeprecController::class, "contActivoFijoDetalleToDeprec"]);
		Route::post("contabilidad_activos_fijos_depreciar_activo", [CONT_ActivosFijosDeprecController::class, "storeDepreciation"]);
		Route::post("contabilidad_activos_fijos_depreciaciones_registradas", [CONT_ActivosFijosDeprecController::class, "getDepreciacionReporte"]);
		Route::post("contabilidad_activos_fijos_mejoras_registradas", [CONT_ActivosFijosDeprecController::class, "getMejorasReporte"]);

		//politicas
		Route::post("contabilidad_politica_comisiones_lista", [CONT_PoliticasController::class, "politicaComisionesLista"]);
		Route::post("contabilidad_politica_comisiones_last", [CONT_PoliticasController::class, "politicaComisionesLast"]);
		Route::post("contabilidad_politica_reembolsos_lista", [CONT_PoliticasController::class, "politicaReembolsosLista"]);
		Route::post("contabilidad_politica_reembolsos_last", [CONT_PoliticasController::class, "politicaReembolsosLast"]);
		Route::post("contabilidad_politica_proveedores_lista", [CONT_PoliticasController::class, "politicaProveedoresLista"]);
		Route::post("contabilidad_politica_proveedores_last", [CONT_PoliticasController::class, "politicaProveedoresLast"]);
		Route::post("contabilidad_politicas_detalle", [CONT_PoliticasController::class, "politicasDetalle"]);
		Route::post("contabilidad_politica_update", [CONT_PoliticasController::class, "politica_update"]);
		Route::post("contabilidad_politica_nuevo_registro", [CONT_PoliticasController::class, "politicaNewRegistro"]);

	//tecnologías_de_la_informacion
		//plataformas digitales
		//Route::get("catalogomonelect",[FNZS_MonedElectController::class,"monederosElectronicos"]);
		Route::post("registrar_plataforma_digital", [FNZS_MonedElectController::class, "registrarMonederoElctronico"]);
		Route::post("update_plataforma_digital", [FNZS_MonedElectController::class, "updateMonederoElectronico"]);
		Route::post("elimina_plataforma_digital", [FNZS_MonedElectController::class, "eliminarMonederoElctronico"]);
		Route::post("restaura_plataforma_digital", [FNZS_MonedElectController::class, "restaurarMonederoElctronico"]);
		Route::post("deletPer_plataforma_digital", [FNZS_MonedElectController::class, "deletPermMonederoElctronico"]);
		//dispositivos
		Route::post("registradevice", [MAIN_UsuarioController::class, "registraDevice"]);
		Route::post("foliodispositivo", [TICS_DispositivosController::class, "folioDispositivo"]);
		Route::post("verlistadisovig", [TICS_DispositivosController::class, "listaDispositivosVig"]);
		Route::post("verlistadispdel", [TICS_DispositivosController::class, "listaDispositivosDel"]);
		Route::post("detalledispositivo", [TICS_DispositivosController::class, "detalleDispositivo"]);
		Route::post("actualizadispositivo", [TICS_DispositivosController::class, "actualizaDispositivo"]);
		Route::post("actualizacajadispositivo", [TICS_DispositivosController::class, "actualizaCajaDispositivo"]);
		Route::post("deletedispositivo", [TICS_DispositivosController::class, "deleteDispositivo"]);
		Route::post("restauradispositivo", [TICS_DispositivosController::class, "restaurarDispositivo"]);
		Route::post("deletepermdispositivo", [TICS_DispositivosController::class, "deletePermanenteDispositivo"]);
		Route::post("registradispositivo", [TICS_DispositivosController::class, "registrarDispositivo"]);
		//dispositivo checador
		Route::post("checador_personal", [VHUM_TrabajadoresController::class, "asistenciaPersonalEntrada"]);
		//empresas
		Route::post("solicitudes_reg_vig", [TICS_SoliRegistroController::class, "solicitudRegistroVigentes"]);
		//usuarios
		Route::post("catalogo_usuarios", [MAIN_UsuarioController::class, "catalogo_general_usuarios"]);
		Route::post("usuarios_desglose_completo", [MAIN_UsuarioController::class, "usuarios_desglose_completo"]);
		Route::post("genera_credenciales_acceso_usuario", [MAIN_UsuarioController::class, "generaPassCodeUserPersonalSOS"]);
		Route::post("revoca_credenciales_acceso_usuario", [MAIN_UsuarioController::class, "revocaPassCodeUserPersonalSOS"]);
		Route::post("actualizaareapersonal", [VHUM_TrabajadoresController::class, "actualizaAreaPersonalSOS"]);
		Route::post("lista_areas_sos", [MAIN_UsuarioController::class, "listaAreasSOS"]);
		Route::post("registrar_usuario_nuevo", [MAIN_UsuarioController::class, "registraUsuarioNuevo"]);
		Route::post("catalogo_empleados_empresas", [VHUM_TrabajadoresController::class, "listaPersonalSOS"]);
		Route::post("catalogo_empleados_clientes", [VHUM_TrabajadoresController::class, "catalogo_empleados_clientes"]);
		//accciones y areas de acceso 
		Route::post("user_solicitar_permiso_jerarquia", [MAIN_UsuarioController::class, "userSolicitarPermisoJerarquia"]);
		Route::post("user_solicitar_permiso_crear", [MAIN_UsuarioController::class, "userSolicitarPermisoCrear"]);
		Route::post("user_solicitar_permiso_editar", [MAIN_UsuarioController::class, "userSolicitarPermisoEditar"]);
		Route::post("user_solicitar_permiso_consulta", [MAIN_UsuarioController::class, "userSolicitarPermisoConsultar"]);
		Route::post("user_solicitar_permiso_eliminar", [MAIN_UsuarioController::class, "userSolicitarPermisoEliminar"]);
		Route::post("user_solicitar_permiso_ver_docs", [MAIN_UsuarioController::class, "userSolicitarPermisoVerDocs"]);
		//modulos
		Route::post("user_acceso_modulo_ssic", [MAIN_UsuarioController::class, "userAccesoModuloSsic"]);
		Route::post("user_acceso_modulo_descarga_xml", [MAIN_UsuarioController::class, "userAccesoModuloDescargaXml"]);
		Route::post("user_acceso_modulo_logistica", [MAIN_UsuarioController::class, "userAccesoModuloLogistica"]);
		Route::post("user_acceso_modulo_compras", [MAIN_UsuarioController::class, "userAccesoModuloCompras"]);
		Route::post("user_acceso_modulo_proyectos", [MAIN_UsuarioController::class, "userAccesoModuloProyectos"]);
		Route::post("user_acceso_modulo_terceros", [MAIN_UsuarioController::class, "userAccesoModuloTerceros"]);
		Route::post("user_acceso_modulo_terceros_associates", [MAIN_UsuarioController::class, "userAccesoModuloTercerosAssociates"]);
		Route::post("user_acceso_modulo_terceros_clientes", [MAIN_UsuarioController::class, "userAccesoModuloTercerosClientes"]);
		Route::post("user_acceso_modulo_terceros_proveedores", [MAIN_UsuarioController::class, "userAccesoModuloTercerosProveedores"]);
		Route::post("user_acceso_modulo_terceros_empleados", [MAIN_UsuarioController::class, "userAccesoModuloTercerosEmpleados"]);
		//ingresos
		Route::post("user_permisos_ingresos_acceso", [MAIN_UsuarioController::class, "userPermisosIngresosAcceso"]);
		Route::post("user_permisos_ingresos_jerarquia", [MAIN_UsuarioController::class, "userPermisosIngresosJerarquia"]);
		Route::post("user_permisos_ingresos_crear", [MAIN_UsuarioController::class, "userPermisosIngresosCrear"]);
		Route::post("user_permisos_ingresos_editar", [MAIN_UsuarioController::class, "userPermisosIngresosEditar"]);
		Route::post("user_permisos_ingresos_consultar", [MAIN_UsuarioController::class, "userPermisosIngresosConsultar"]);
		Route::post("user_permisos_ingresos_eliminar", [MAIN_UsuarioController::class, "userPermisosIngresosEliminar"]);
		Route::post("user_permisos_ingresos_ver_docs", [MAIN_UsuarioController::class, "userPermisosIngresosVerDocs"]);
		//Catalogos
		Route::post("user_permisos_ingresos_catalogos_modulo", [MAIN_UsuarioController::class, "userPermisosIngresosCatalogosModulo"]);
		Route::post("user_permisos_ingresos_mercancias", [MAIN_UsuarioController::class, "userPermisosIngresosMercancias"]);
		Route::post("user_permisos_ingresos_servicios", [MAIN_UsuarioController::class, "userPermisosIngresosServicios"]);
		Route::post("user_permisos_ingresos_lista_precios", [MAIN_UsuarioController::class, "userPermisosIngresosListaPrecios"]);
		Route::post("user_permisos_ingresos_descuentos", [MAIN_UsuarioController::class, "userPermisosIngresosDescuentos"]);
		Route::post("user_permisos_ingresos_promociones", [MAIN_UsuarioController::class, "userPermisosIngresosPromociones"]);
		Route::post("user_permisos_ingresos_impuestos", [MAIN_UsuarioController::class, "userPermisosIngresosImpuestos"]);
		Route::post("user_permisos_ingresos_clientes", [MAIN_UsuarioController::class, "userPermisosIngresosClientes"]);
		Route::post("user_permisos_ingresos_ventas_modulo", [MAIN_UsuarioController::class, "userPermisosIngresosVentasModulo"]);
		Route::post("user_permisos_ingresos_pedidos", [MAIN_UsuarioController::class, "userPermisosIngresosPedidos"]);
		Route::post("user_permisos_ingresos_ventas", [MAIN_UsuarioController::class, "userPermisosIngresosVentas"]);
		Route::post("user_permisos_ingresos_seguimiento_ventas", [MAIN_UsuarioController::class, "userPermisosIngresosSeguimientoVentas"]);
		Route::post("user_permisos_ingresos_devoluciones", [MAIN_UsuarioController::class, "userPermisosIngresosDevoluciones"]);
		Route::post("user_permisos_ingresos_facturacion", [MAIN_UsuarioController::class, "userPermisosIngresosFacturacion"]);
		Route::post("user_permisos_ingresos_reportes", [MAIN_UsuarioController::class, "userPermisosIngresosReportes"]);
		//egresos
		Route::post("user_permisos_egresos_acceso", [MAIN_UsuarioController::class, "userPermisosEgresosAcceso"]);
		Route::post("user_permisos_egresos_jerarquia", [MAIN_UsuarioController::class, "userPermisosEgresosJerarquia"]);
		Route::post("user_permisos_egresos_crear", [MAIN_UsuarioController::class, "userPermisosEgresosCrear"]);
		Route::post("user_permisos_egresos_editar", [MAIN_UsuarioController::class, "userPermisosEgresosEditar"]);
		Route::post("user_permisos_egresos_consultar", [MAIN_UsuarioController::class, "userPermisosEgresosConsultar"]);
		Route::post("user_permisos_egresos_eliminar", [MAIN_UsuarioController::class, "userPermisosEgresosEliminar"]);
		Route::post("user_permisos_egresos_ver_docs", [MAIN_UsuarioController::class, "userPermisosEgresosVerDocs"]);
		Route::post("user_permisos_egresos_catalogos_modulo", [MAIN_UsuarioController::class, "userPermisosEgresosCatalogosModulo"]);
		Route::post("user_permisos_egresos_productos", [MAIN_UsuarioController::class, "userPermisosEgresosProductos"]);
		Route::post("user_permisos_egresos_servicios", [MAIN_UsuarioController::class, "userPermisosEgresosServicios"]);
		Route::post("user_permisos_egresos_activos_fijos", [MAIN_UsuarioController::class, "userPermisosEgresosActivosFijos"]);
		Route::post("user_permisos_egresos_activos_intang", [MAIN_UsuarioController::class, "userPermisosEgresosActivosIntang"]);
		Route::post("user_permisos_egresos_proveedores", [MAIN_UsuarioController::class, "userPermisosEgresosProveedores"]);
		Route::post("user_permisos_egresos_establecimientos", [MAIN_UsuarioController::class, "userPermisosEgresosEstablecimientos"]);
		//Compras
		Route::post("user_permisos_egresos_compras_modulo", [MAIN_UsuarioController::class, "userPermisosEgresosComprasModulo"]);
		Route::post("user_permisos_egresos_requisiciones", [MAIN_UsuarioController::class, "userPermisosEgresosRequisiciones"]);
		Route::post("user_permisos_egresos_cotizaciones", [MAIN_UsuarioController::class, "userPermisosEgresosCotizaciones"]);
		Route::post("user_permisos_egresos_compra_directa", [MAIN_UsuarioController::class, "userPermisosEgresosCompraDirecta"]);
		Route::post("user_permisos_egresos_compra_seguimiento", [MAIN_UsuarioController::class, "userPermisosEgresosCompraSeguimiento"]);
		//finanzas
		Route::post("user_permisos_finanzas_acceso", [MAIN_UsuarioController::class, "userPermisosFinanzasAcceso"]);
		Route::post("user_permisos_finanzas_jerarquia", [MAIN_UsuarioController::class, "userPermisosFinanzasJerarquia"]);
		Route::post("user_permisos_finanzas_crear", [MAIN_UsuarioController::class, "userPermisosFinanzasCrear"]);
		Route::post("user_permisos_finanzas_editar", [MAIN_UsuarioController::class, "userPermisosFinanzasEditar"]);
		Route::post("user_permisos_finanzas_consultar", [MAIN_UsuarioController::class, "userPermisosFinanzasConsultar"]);
		Route::post("user_permisos_finanzas_eliminar", [MAIN_UsuarioController::class, "userPermisosFinanzasEliminar"]);
		Route::post("user_permisos_finanzas_ver_docs", [MAIN_UsuarioController::class, "userPermisosFinanzasVerDocs"]);
		Route::post("user_permisos_finanzas_catalogos_modulo", [MAIN_UsuarioController::class, "userPermisosFinanzasCatalogosModulo"]);
		Route::post("user_permisos_finanzas_cuentas_bancarias", [MAIN_UsuarioController::class, "userPermisosFinanzasCuentasBancarias"]);
		Route::post("user_permisos_finanzas_caja", [MAIN_UsuarioController::class, "userPermisosFinanzasCaja"]);
		Route::post("user_permisos_finanzas_monederos_electronicos", [MAIN_UsuarioController::class, "userPermisosFinanzasMonederosElectronicos"]);
		Route::post("user_permisos_finanzas_dispositivos_electronicos", [MAIN_UsuarioController::class, "userPermisosFinanzasDispositivosElectronicos"]);
		Route::post("user_permisos_finanzas_control_mov_bancarios", [MAIN_UsuarioController::class, "userPermisosFinanzasControlMovBancarios"]);
		Route::post("user_permisos_finanzas_control_mov_efectivo", [MAIN_UsuarioController::class, "userPermisosFinanzasControlMovEfectivo"]);
		Route::post("user_permisos_finanzas_ordenes_pago", [MAIN_UsuarioController::class, "userPermisosFinanzasOrdenesPago"]);
		Route::post("user_permisos_finanzas_ajustes_ycpr", [MAIN_UsuarioController::class, "userPermisosFinanzasAjustesyCPR"]);
		Route::post("user_permisos_finanzas_info_bancaria", [MAIN_UsuarioController::class, "userPermisosFinanzasInfoBancaria"]);
		//valor_humano
		Route::post("user_permisos_valor_humano_acceso", [MAIN_UsuarioController::class, "userPermisosValorHumanoAcceso"]);
		Route::post("user_permisos_valor_humano_jerarquia", [MAIN_UsuarioController::class, "userPermisosValorHumanoJerarquia"]);
		Route::post("user_permisos_valor_humano_crear", [MAIN_UsuarioController::class, "userPermisosValorHumanoCrear"]);
		Route::post("user_permisos_valor_humano_editar", [MAIN_UsuarioController::class, "userPermisosValorHumanoEditar"]);
		Route::post("user_permisos_valor_humano_consultar", [MAIN_UsuarioController::class, "userPermisosValorHumanoConsultar"]);
		Route::post("user_permisos_valor_humano_eliminar", [MAIN_UsuarioController::class, "userPermisosValorHumanoEliminar"]);
		Route::post("user_permisos_valor_humano_ver_docs", [MAIN_UsuarioController::class, "userPermisosValorHumanoVerDocs"]);
		Route::post("user_permisos_valor_humano_catalogos", [MAIN_UsuarioController::class, "userPermisosValorHumanoCatalogos"]);
		Route::post("user_permisos_valor_humano_reembolsos", [MAIN_UsuarioController::class, "userPermisosValorHumanoReembolsos"]);
		Route::post("user_permisos_valor_humano_reportes", [MAIN_UsuarioController::class, "userPermisosValorHumanoReportes"]);
		//contabilidad
		Route::post("user_permisos_contabilidad_acceso", [MAIN_UsuarioController::class, "userPermisosContabilidadAcceso"]);
		Route::post("user_permisos_contabilidad_jerarquia", [MAIN_UsuarioController::class, "userPermisosContabilidadJerarquia"]);
		Route::post("user_permisos_contabilidad_crear", [MAIN_UsuarioController::class, "userPermisosContabilidadCrear"]);
		Route::post("user_permisos_contabilidad_editar", [MAIN_UsuarioController::class, "userPermisosContabilidadEditar"]);
		Route::post("user_permisos_contabilidad_consultar", [MAIN_UsuarioController::class, "userPermisosContabilidadConsultar"]);
		Route::post("user_permisos_contabilidad_eliminar", [MAIN_UsuarioController::class, "userPermisosContabilidadEliminar"]);
		Route::post("user_permisos_contabilidad_ver_docs", [MAIN_UsuarioController::class, "userPermisosContabilidadVerDocs"]);
		Route::post("user_permisos_contabilidad_catalogos", [MAIN_UsuarioController::class, "userPermisosContabilidadCatalogos"]);
		Route::post("user_permisos_contabilidad_politicas", [MAIN_UsuarioController::class, "userPermisosContabilidadPoliticas"]);
		Route::post("user_permisos_contabilidad_catalogo_cuentas", [MAIN_UsuarioController::class, "userPermisosContabilidadCatalogoCuentas"]);
		Route::post("user_permisos_contabilidad_estados_financieros", [MAIN_UsuarioController::class, "userPermisosContabilidadEstadosFinancieros"]);
		Route::post("user_permisos_contabilidad_reportes", [MAIN_UsuarioController::class, "userPermisosContabilidadReportes"]);
		//tec_info
		Route::post("user_permisos_teci_info_acceso", [MAIN_UsuarioController::class, "userPermisosTeciInfoAcceso"]);
		Route::post("user_permisos_teci_info_jerarquia", [MAIN_UsuarioController::class, "userPermisosTeciInfoJerarquia"]);
		Route::post("user_permisos_teci_info_crear", [MAIN_UsuarioController::class, "userPermisosTeciInfoCrear"]);
		Route::post("user_permisos_teci_info_editar", [MAIN_UsuarioController::class, "userPermisosTeciInfoEditar"]);
		Route::post("user_permisos_teci_info_consultar", [MAIN_UsuarioController::class, "userPermisosTeciInfoConsultar"]);
		Route::post("user_permisos_teci_info_eliminar", [MAIN_UsuarioController::class, "userPermisosTeciInfoEliminar"]);
		Route::post("user_permisos_teci_info_ver_docs", [MAIN_UsuarioController::class, "userPermisosTeciInfoVerDocs"]);
		Route::post("user_permisos_teci_info_apps_complementarias", [MAIN_UsuarioController::class, "userPermisosTeciInfoAppsComplementarias"]);
		Route::post("user_permisos_teci_info_soporte", [MAIN_UsuarioController::class, "userPermisosTeciInfoSoporte"]);
		Route::post("user_permisos_teci_info_comunicacion", [MAIN_UsuarioController::class, "userPermisosTeciInfoComunicacion"]);
		Route::post("user_permisos_teci_info_publicaciones", [MAIN_UsuarioController::class, "userPermisosTeciInfoPublicaciones"]);

		Route::post("cfdi_validacion_validaestadoxmlcfdi_impuestos_sobre_nomina", [MAIN_XmlValidateController::class, "validaEstadoXmlCFDIISN"]);
		Route::post("cfdi_validacion_validaestadoxmlcfdi_nominas", [MAIN_XmlValidateController::class, "validaEstadoXmlCFDINomina"]);
		Route::post("cfdi_validacion_validaestadoxmlcfdi_reembolsos", [MAIN_XmlValidateController::class, "validaEstadoXmlCFDIReembolsos"]);
		Route::post("cfdi_validacion_validaestadoxmlcfdi_compras", [MAIN_XmlValidateController::class, "validaEstadoXmlCFDICompra"]);
		Route::post("cfdi_validacion_validaestadoxmlcfdi_aportaciones_imss", [MAIN_XmlValidateController::class, "validaEstadoXmlCFDIAportacionesIMSS"]);
		Route::post("cfdi_validacion_validaestadoxmlcfdi_decimp_federales", [MAIN_XmlValidateController::class, "validaEstadoXmlCFDIDeclaracionesImpFederales"]);
		//acceso
		Route::post("all_user_config_ssic", [MAIN_RolesController::class, "allUserConfigSSIC"]);
		//accesos por menu
		Route::post("dtalgnpacc", [MAIN_RolesController::class, "permisoAcceso"]);
		Route::post("permisos_acceso_menu", [MAIN_RolesController::class, "newPermisoAcceso"]);
		//accesos por link
		//ingresos
		Route::post("permisos_acceso_ingresos", [MAIN_RolesController::class, "permisosIngresos"]);
		//sos_inside/ingresos/catalogodemercancias
		//sos_inside/ingresos/catalogodeservicios
		//sos_inside/ingresos/altadeservicios
		//sos_inside/ingresos/lista_de_precios
		//sos_inside/ingresos/catalogodedescuentos
		//sos_inside/ingresos/altadedescuentos
		//sos_inside/ingresos/catalogodepromociones
		//sos_inside/ingresos/altadepromociones
		//sos_inside/ingresos/catalogodeimpuestos
		//sos_inside/ingresos/altadeimpuestos
		//sos_inside/ingresos/catalogodeclientes
		//sos_inside/ingresos/altadeclientes
		//sos_inside/ingresos/listadepedidos
		//sos_inside/ingresos/altadeopedidos
		//sos_inside/ingresos/altadeventas
		//sos_inside/ingresos/seguimientodeventas
		//btnAbreCatSeg
		//btnAbreAltaSeg
		//btnAbreCatDevol
		//btnAbreAltaDevol
		//sos_inside/ingresos/solicitudes_facturacion
		//sos_inside/ingresos/nueva_factura
		//menuEgresos
		//sos_inside/egresos/catalogodeproductos
		//sos_inside/egresos/altadeproductos
		//sos_inside/egresos/catalogodelotes
		//sos_inside/egresos/altadelotes
		//sos_inside/egresos/catalogodepedimentos
		//sos_inside/egresos/altadepedimentos
		//sos_inside/egresos/catalogodeservicios
		//sos_inside/egresos/altadeservicios
		//sos_inside/egresos/catalogodeactivosfijos
		//sos_inside/egresos/altadeactivosfijos
		//sos_inside/egresos/catalogodeactivosintangibles
		//sos_inside/egresos/altadeactivosintangibles
		//sos_inside/egresos/catalogodeproveedores
		//sos_inside/egresos/altadeproveedores
		//sos_inside/egresos/catalogodeestablecimientos
		//sos_inside/egresos/altadeestablecimientos
		//sos_inside/egresos/catalogoderequisiciones
		//sos_inside/egresos/altaderequisiciones
		//sos_inside/egresos/catalogodecotizaciones
		//sos_inside/egresos/altadecotizaciones
		//sos_inside/egresos/catalogode_erogacionesygastos
		//sos_inside/egresos/altade_erogacionesygastos
		//sos_inside/egresos/altadecompras
		//sos_inside/egresos/seguimientodecompras
		Route::post("permisos_egresos_acceso_reem", [MAIN_RolesController::class, "permisosEGRESOSReembolsos"]);
		//menuFinanzas
		//sos_inside/finanzas/catalogodecuentasbancarias
		//sos_inside/finanzas/altadecuentasbancarias
		//sos_inside/finanzas/catalogodecajas
		//sos_inside/finanzas/altadecajas
		//sos_inside/finanzas/catalogodemonederos_electronicos
		//sos_inside/finanzas/altademonederos_electronicos
		//sos_inside/finanzas/catalogodedispositivos
		//sos_inside/finanzas/altadedispositivos
		//sos_inside/finanzas/control_movimientos_bancarios
		//sos_inside/finanzas/control_movimientos_en_efectivo
		//sos_inside/finanzas/catalogodeordenesdepago
		Route::post("permisos_finanzas_acceso_ordenesdepago", [MAIN_RolesController::class, "permisosFINANZASOrdenPago"]);
		//menuValorHumano: any = [
		//{id: 1,name: 'Catalogos'},
		//{id: 2,name: 'Asistencias'}
		//{id: 3,name: 'Cálculo de nominas'},
		//{id: 4,name: 'Cálculo de aportaciones'},
		//{path:'centros_de_trabajo_alta',component: VHCentrosTrabajoAltaComponent,canActivate:[AuthGuardService]},
		//{path:'centros_de_trabajo_lista',component: VHCentrosTrabajoListaComponent,canActivate:[AuthGuardService]},
		//{path:'empleados_alta',component: VHEmpleadosAltaComponent,canActivate:[AuthGuardService]},
		//{path:'empleados_lista',component: VHEmpleadosListaComponent,canActivate:[AuthGuardService]},
		//{path:'empleados_asistencias',component: AsistenciasComponent,canActivate:[AuthGuardService]},
		//{path:'empleados_nomina',component: CalcNominasComponent,canActivate:[AuthGuardService]},
		//{path:'empleados_aportaciones',component: CalcAportacionesComponent,canActivate:[AuthGuardService]},
		Route::post("permisos_vhum_acceso_reem", [MAIN_RolesController::class, "permisosVHUMReembolsos"]);
		//menuContabilidad: any = [
		//sos_inside/contabilidad/catalogodecuentas'},
		//politicas
		//Estados Financieros'},
		//Reportes'},
		//menuTecInfo: any = [
		//Apps complementarias'},
		//sos_inside/tecnologias_info/soporte_sos'},
		//comunicación'},
		//Publicaciones'},
    Route::post("tecnologias_info_publicaciones_registrar", [TICS_PublicacionesController::class, "registra_publicacion"]);
    Route::post("tecnologias_info_publicaciones_catalogo", [TICS_PublicacionesController::class, "catalogoPublicaciones"]);
    Route::post("tecnologias_info_publicaciones_detalle", [TICS_PublicacionesController::class, "publicacionDetalle"]);
    Route::post("tecnologias_info_publicaciones_actualizar", [TICS_PublicacionesController::class, "actualiza_publicacion"]);
    Route::post("tecnologias_info_publicaciones_eliminar", [TICS_PublicacionesController::class, "publicacionEliminar"]);
    Route::post("tecnologias_info_publicaciones_restaurar", [TICS_PublicacionesController::class, "publicacionRestaurar"]);
    Route::post("tecnologias_info_publicaciones_eliminacion_permanente", [TICS_PublicacionesController::class, "publicacionEliminacionPermanente"]);
		//notificaciones
		Route::post("total_notificaciones", [MAIN_NotificacionesController::class, "totalNotificaciones"]);
		Route::post("lista_min_notificaciones", [MAIN_NotificacionesController::class, "listaNotificacionesFirst"]);
		Route::post("lista_notificaciones_all", [MAIN_NotificacionesController::class, "listaNotificacionesAll"]);
		Route::post("lista_notificaciones_gestion_proyectos", [MAIN_NotificacionesController::class, "listaNotificacionesGestionProyectos"]);
		Route::post("lista_notificaciones_gestion_p", [MAIN_NotificacionesController::class, "listaNotificacionesGestionProyectoZ"]);
		//Route::post("lista_min_notificaciones",[MAIN_NotificacionesController::class,"listaMinNotificaciones"]);
		Route::post("ultima_notificacion", [MAIN_NotificacionesController::class, "ultimaNotificacion"]);
		Route::post("detalle_notificacion", [MAIN_NotificacionesController::class, "detalleNotificacionInside"]);
		Route::post("detalle_notificacion_outside_gp", [MAIN_NotificacionesController::class, "detalleNotificacionOutsideGP"]);
		Route::post("delete_notificacion", [MAIN_NotificacionesController::class, "deleteNotificacion"]);
		Route::post("listanotificaciones", [MAIN_EmpresasController::class, "listaempresasSSIC"]);
		//empresas
		Route::get("allcompanies", [MAIN_EmpresasController::class, "listaEmpresasAll"]);
		Route::post("empresacompleteregistro", [MAIN_EmpresasController::class, "empresaCompleteRegistro"]);
		Route::post("verify_exist_empresa_one", [MAIN_EmpresasController::class, "buscaRfcAllEmpresaOut"]);
		Route::post("catalogo_empresas_perfil", [MAIN_EmpresasController::class, "empresaPerfil"]);
		Route::post("catalogo_empresas_detalle", [MAIN_EmpresasController::class, "empresaDetalle"]);
		Route::post("empresa_vincular_usuario", [MAIN_EmpresasController::class, "vincularEmpresaUsuario"]);
		Route::post("empresa_registrar", [MAIN_EmpresasController::class, "registraEmpresaMin"]);
		//fecha y hora
		Route::post("getFechaInput", [MAIN_MenuController::class, "getFechaInput"]);
		Route::post("horarioUso", [MAIN_MenuController::class, "getRelojes"]);
		//lenguaje
		Route::post("update_language", [MAIN_SettingsController::class, "updateLanguage"]);
		//monedas
		Route::post("monedaempresa", [MAIN_MonedaController::class, "monedaEmpresa"]);
		//configuracion de cfdi
		Route::get("getListaUso", [MAIN_CfdiController::class, "getListaUso"]);
		Route::get("getMotivosCancelacionCfdi", [MAIN_CfdiController::class, "getMotivosCancelacion"]);
		//paises
		//sat
		Route::get("catalogo_prodservsat", [MAIN_CatSatController::class, "listaCatalogo"]);
		Route::post("catalogo_prodservsatClave", [MAIN_CatSatController::class, "listaCatalogoPClave"]);
		Route::post("catalogo_prodservsatDesc", [MAIN_CatSatController::class, "listaCatalogoPdesc"]);
		Route::post("catalogo_prodservsatInput", [MAIN_CatSatController::class, "listaCatalogoPInput"]);
		//clasificacion de productos y servicios
		Route::get("getClasificacionProductos", [MAIN_ClasificacionController::class, "getClasificacionProductos"]);
		Route::post("getClasificacionProductosComplete", [MAIN_ClasificacionController::class, "getClasificacionProductosComplete"]);
		Route::post("getGeneroProductos", [MAIN_ClasificacionController::class, "getGeneroProductos"]);
		Route::post("getClasificacionFull", [MAIN_ClasificacionController::class, "setClasificacionFull"]);
		Route::get("getClasificacionServicios", [MAIN_ClasificacionController::class, "getClasificacionServicios"]);
		Route::post("clasificacompletserv", [MAIN_ClasificacionController::class, "fullClasifServicios"]);
		//direcciones
		Route::post("location_iq_dir", [MAIN_DireccionesController::class, "listaLocationIQ"]);
		Route::post("postcpostales", [MAIN_DireccionesController::class, "listacodPostalLike"]);
		Route::post("getlistacolonias", [MAIN_DireccionesController::class, "listacolonias"]);
		Route::post("getselectentfed", [MAIN_DireccionesController::class, "selectentfed"]);
	//terceros
		//associates 
		Route::post("modulo_mostrador_servicios_catalogo", [TERC_AssociatesCatalogosController::class, "servicioAssocCatalogo"]);
		Route::post("modulo_mostrador_serv_solicita_valid", [TERC_AssociatesCatalogosController::class, "requestValidacionServ"]);
		Route::post("modulo_mostrador_servicios_actualizar", [TERC_AssociatesCatalogosController::class, "servicioActualizar"]);
		Route::post("modulo_mostrador_servicios_papelera_save", [TERC_AssociatesCatalogosController::class, "servicioPapeleraSave"]);
		Route::post("modulo_mostrador_servicios_papelera_catalogo", [TERC_AssociatesCatalogosController::class, "servicioAssocCatalogoEliminados"]);
		Route::post("modulo_mostrador_servicios_restaurar", [TERC_AssociatesCatalogosController::class, "servicioPapeleraRestaurar"]);
		Route::post("modulo_mostrador_servicios_eliminar", [TERC_AssociatesCatalogosController::class, "servicioDeletePerm"]);
		Route::post("modulo_mostrador_createservicio", [TERC_AssociatesCatalogosController::class, "registroServicioAssoc"]);
		Route::post("list_companies_associates", [MAIN_EmpresasController::class, "listaempresasAssociates"]);
		Route::post("select_company_associates", [MAIN_EmpresasController::class, "selectEmpresasAssociates"]);
		Route::post("list_solicitud_cfdi", [TERC_AssociatesController::class, "listaSolicitudCFDI"]);
		Route::post("detalle_solicitud_cfdi", [TERC_AssociatesController::class, "detalleSolicitudCFDI"]);
		Route::post("cancelar_solicitud_cfdi", [TERC_AssociatesController::class, "cancelarCFDI"]);
		Route::post("registro_solicitud_cfdi", [TERC_AssociatesController::class, "registroSolicitudCFDI"]);
		Route::post("registra_solicitud_factura_venta_mostrador", [TERC_AssociatesController::class, "registroSolicitudCFDIMostrador"]);
		Route::post("r_solicitud_cfdi", [TERC_AssociatesController::class, "registroSoliCFDI"]);
		//customers    
		//suppliers
		//employees
		//comisiones
		Route::post("comision_reem_listas", [TERC_EmployeesController::class, "comisionReemListas"]);
		//reembolsos
		Route::post("reembolso_lista", [TERC_EmployeesController::class, "reembolso_lista_true"]);
		Route::post("reembolso_deshabilitar", [TERC_EmployeesController::class, "reembolso_deshabilitar"]);
		Route::post("reembolso_lista_deleted", [TERC_EmployeesController::class, "reembolso_lista_false"]);
		Route::post("reembolso_rehabilitar", [TERC_EmployeesController::class, "reembolso_rehabilitar"]);
		Route::post("reembolso_detalle", [TERC_EmployeesController::class, "reembolso_detalle"]);
		Route::post("reembolso_load_xml_fact", [TERC_EmployeesController::class, "reembolso_load_xml_fact"]);
		Route::post("reembolso_load_pdf_fact", [TERC_EmployeesController::class, "reembolso_load_pdf_fact"]);
		Route::post("reembolso_load_docs", [TERC_EmployeesController::class, "reembolso_load_docs"]);
		Route::post("reembolso_add_new", [TERC_EmployeesController::class, "reembolso_agregar"]);
		Route::post("reembolso_delete_docs", [TERC_EmployeesController::class, "reembolso_delete_docs"]);
		Route::post("reembolso_update", [TERC_EmployeesController::class, "reembolso_soli_update"]);
		Route::post("reembolso_registro", [TERC_EmployeesController::class, "reembolso_registro"]);
		Route::post("reembolso_registro_fase_uno", [TERC_EmployeesController::class, "reembolso_registro_fase_uno"]);
		Route::post("reembolso_registro_fase_dos", [TERC_EmployeesController::class, "reembolso_registro_fase_dos"]);
		Route::post("reembolso_registro_fase_dos_delete", [TERC_EmployeesController::class, "reembolso_registro_fase_dos_delete"]);
		Route::post("reembolso_registro_fase_tres", [TERC_EmployeesController::class, "reembolso_registro_fase_tres"]);

	//modulo de proyectos
		//catalogo de reportes
		//eventos
		Route::post("calendar_proyectos", [JURI_EventosController::class, "calendarCompleteProyectos"]);
		Route::post("calendar_por_proyecto", [JURI_EventosController::class, "calendarProyectos"]);
		Route::post("calendar_por_tarea", [JURI_EventosController::class, "calendarTareas"]);
		Route::post("calendar_all_por_proy_pers", [JURI_EventosController::class, "calendarProyectosPersonalAll"]);
		Route::post("calendar_por_proy_pers", [JURI_EventosController::class, "calendarProyectosPersonal"]);
		Route::post("calendar_por_tare_pers", [JURI_EventosController::class, "calendarTareasPersonal"]);
		//eventos
		Route::post("gantt_proyectos", [JURI_EventosController::class, "ganttCompleteProyectos"]);
		//Route::post("calendar_por_proyecto",[JURI_EventosController::class,"calendarProyectos"]);
		//Route::post("calendar_por_tarea",[JURI_EventosController::class,"calendarTareas"]);
		//Route::post("calendar_all_por_proy_pers",[JURI_EventosController::class,"calendarProyectosPersonalAll"]);
		//Route::post("calendar_por_proy_pers",[JURI_EventosController::class,"calendarProyectosPersonal"]);
		//Route::post("calendar_por_tare_pers",[JURI_EventosController::class,"calendarTareasPersonal"]);
		//tareas programadas
		Route::post("catalogo_plantillas", [ModuleProyectosController::class, "catalogoPlantillas"]);
		Route::post("registrar_plantilla", [ModuleProyectosController::class, "registrarPlantilla"]);
		Route::post("permisos_proyectos", [ModuleProyectosController::class, "permisosProyectos"]);
		Route::post("registrar_proyecto", [ModuleProyectosController::class, "registrarProyecto"]);
		Route::post("last_proyect_created", [ModuleProyectosController::class, "lastProyectCreated"]);
		Route::post("lista_proyectos_eliminados", [ModuleProyectosController::class, "listaProyectosDeleted"]);
		Route::post("restaurar_proyecto", [ModuleProyectosController::class, "restaurarProyecto"]);
		Route::post("recover_proyecto", [ModuleProyectosController::class, "recoverProyecto"]);
		Route::post("remover_proyecto", [ModuleProyectosController::class, "removerProyecto"]);
		Route::post("lista_proyectos", [ModuleProyectosController::class, "listaProyectos"]);
		Route::post("lista_proyectos_fecha_asc", [ModuleProyectosController::class, "listaProyectosAscFecha"]);
		Route::post("lista_proyectos_fecha_desc", [ModuleProyectosController::class, "listaProyectosDescFecha"]);
		Route::post("lista_proyectos_black_asc", [ModuleProyectosController::class, "listaProyectosAscBlack"]);
		Route::post("lista_proyectos_black_desc", [ModuleProyectosController::class, "listaProyectosDescBlack"]);
		Route::post("lista_proyectos_green_asc", [ModuleProyectosController::class, "listaProyectosAscGreen"]);
		Route::post("lista_proyectos_green_desc", [ModuleProyectosController::class, "listaProyectosDescGreen"]);
		Route::post("lista_proyectos_yellow_asc", [ModuleProyectosController::class, "listaProyectosAscYellow"]);
		Route::post("lista_proyectos_yellow_desc", [ModuleProyectosController::class, "listaProyectosDescYellow"]);
		Route::post("lista_proyectos_red_asc", [ModuleProyectosController::class, "listaProyectosAscRed"]);
		Route::post("lista_proyectos_red_desc", [ModuleProyectosController::class, "listaProyectosDescRed"]);
		Route::post("lista_proyectos_finish_asc", [ModuleProyectosController::class, "listaProyectosAscFinish"]);
		Route::post("lista_proyectos_finish_desc", [ModuleProyectosController::class, "listaProyectosDescFinish"]);
		Route::post("actualizar_proyecto", [ModuleProyectosController::class, "actualizarProyecto"]);
		Route::post("quita_lider_proyecto", [ModuleProyectosController::class, "quitaLiderProyecto"]);
		Route::post("agregar_proyecto_eqtrabajo", [ModuleProyectosController::class, "agregarEqTeamProyecto"]);
		Route::post("eliminar_proyecto_eqtrabajo", [ModuleProyectosController::class, "eliminarEqTeamProyecto"]);
		Route::post("proyecto_recalendarizar", [ModuleProyectosController::class, "recalendarizarProyecto"]);
		Route::post("eliminar_proyecto", [ModuleProyectosController::class, "eliminarProyecto"]);
		Route::post("nuevo_nombre_proyecto", [ModuleProyectosController::class, "nuevoNombreProyecto"]);
		Route::post("detalle_proyecto", [ModuleProyectosController::class, "detalleProyecto"]); //detalle de proyecto
		//tareas
		Route::post("registrar_tarea", [ModuleProyectosController::class, "registrarTarea"]);
		Route::post("last_tarea_created", [ModuleProyectosController::class, "ultimaTareaCreada"]);
		Route::post("recover_tarea", [ModuleProyectosController::class, "recoverTarea"]);
		Route::post("revision_tarea_acceso", [ModuleProyectosController::class, "revisionTareaAcceso"]);
		Route::post("proyecto_dependiente_tar_agregar", [ModuleProyectosController::class, "tareaDependienteAgregar"]);
		Route::post("proyecto_dependiente_tar_remover", [ModuleProyectosController::class, "tareaDependienteRemover"]);
		Route::post("duplica_tarea", [ModuleProyectosController::class, "duplicaTarea"]);
		Route::post("actualiza_name_tarea", [ModuleProyectosController::class, "actualizaNameTarea"]);
		Route::post("actualiza_descrip_tarea", [ModuleProyectosController::class, "actualizaDescTarea"]);
		Route::post("actualiza_tarea", [ModuleProyectosController::class, "actualizaTarea"]);
		Route::post("recalendarizar_tarea", [ModuleProyectosController::class, "recalendarizarTarea"]);
		Route::post("agrega_responsable_tarea", [ModuleProyectosController::class, "agregarRespTarea"]);
		Route::post("elimina_responsable_tarea", [ModuleProyectosController::class, "eliminarRespTarea"]);
		Route::post("terminar_tarea", [ModuleProyectosController::class, "terminarTarea"]);
		Route::post("eliminar_tarea", [ModuleProyectosController::class, "eliminarTarea"]);
		Route::post("last_tarea_deleted", [ModuleProyectosController::class, "lastTareaDeleted"]);
		Route::post("restaurar_tarea", [ModuleProyectosController::class, "restaurarTarea"]);
		Route::post("remove_perm_tarea", [ModuleProyectosController::class, "removeTareaPerm"]);
		Route::post("terminar_perticipacion_tarea", [ModuleProyectosController::class, "terminarParticipacionTarea"]);
		//informes
		Route::post("registra_informe", [ModuleProyectosController::class, "registrarInformeTarea"]);
		Route::post("last_informe_created", [ModuleProyectosController::class, "lastInformeTareaCreated"]);
		Route::post("recover_informe", [ModuleProyectosController::class, "recoverInformeTarea"]);
		Route::post("detalle_informe", [ModuleProyectosController::class, "detalleInforme"]);
		Route::post("informe_evidencias_lista", [ModuleProyectosController::class, "informeListaEvidencias"]);
		Route::get("ver_en_browser/{json}/{archivo}", [ModuleProyectosController::class, "visorEvidencias"]);
		Route::get("descarga_browser/{json}", [ModuleProyectosController::class, "descargarEvidencias"]);
		Route::post("revisar_informe", [ModuleProyectosController::class, "revisarInformeTarea"]);
		Route::post("aprobar_informe", [ModuleProyectosController::class, "aprobarInformeTarea"]);
		Route::post("actualiza_informe", [ModuleProyectosController::class, "updateInformeTarea"]);
		Route::post("actualiza_observaciones_informe", [ModuleProyectosController::class, "updateObservacionesInforme"]);
		Route::post("carga_evidencias_informe", [ModuleProyectosController::class, "cargaEvidenciasInformeTarea"]);
		Route::post("proy_eliminar_evidencia", [ModuleProyectosController::class, "deleteEvidenciaInfProyecto"]);
		Route::post("proy_restaura_evidencia", [ModuleProyectosController::class, "restartEvidenciaInfProyecto"]);
		Route::post("proy_delete_evid_perman", [ModuleProyectosController::class, "deleteEvidInfProyectoPermanente"]);
		Route::post("elimina_informe", [ModuleProyectosController::class, "deleteInformeTarea"]);
		Route::post("restaurar_informe", [ModuleProyectosController::class, "restaurarInformeTarea"]);
		Route::post("elimina_perm_informe", [ModuleProyectosController::class, "deleteInformePerm"]);
});


//chatGPT
Route::post("chat_con_gpt", [MAIN_GPTController::class, "respuestaChatGPT"]);
//Route::middleware('auth:sanctum')->get('/notificaciones', function (Request $request) {
//    return $request->user()->notifications;
//});
Route::get('/probar-notificacion', function () {
	$user = User::find(3); // Cambia al ID real
	$user->notify(new NuevaNotificacion("esta es una notificacion", "1234"));
	return response()->json(['mensaje' => 'Notificación enviada']);
});