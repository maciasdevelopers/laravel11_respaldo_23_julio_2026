<?php

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Barryvdh\DomPDF\Facade\Pdf;

//SSIC
    //contabilidad
        use App\Http\Controllers\CONT_CuentasContablesController;
        use App\Http\Controllers\CONT_ImpuestosController;
        use App\Http\Controllers\CONT_PoliticasController;

    //egresos 
        use App\Http\Controllers\EGRE_ProductosController;
        use App\Http\Controllers\EGRE_LotesController;  
        use App\Http\Controllers\EGRE_ProdSeriesController;
        use App\Http\Controllers\EGRE_PedimentosController;
        use App\Http\Controllers\EGRE_GastosController;
        use App\Http\Controllers\EGRE_ServiciosController;
        use App\Http\Controllers\EGRE_ActivosFijosController;
        use App\Http\Controllers\EGRE_ActivosIntangiblesController;
        use App\Http\Controllers\EGRE_ProveedoresController;
        use App\Http\Controllers\EGRE_AlmacenController;
        use App\Http\Controllers\EGRE_RequisicionesController;
        use App\Http\Controllers\EGRE_CotizacionesController;
        use App\Http\Controllers\EGRE_ComprasController;
        use App\Http\Controllers\EGRE_ProrrateosController;
        use App\Http\Controllers\EGRE_ReembolsosController;
    
    //finanzas
        use App\Http\Controllers\FNZS_CajaController;
        use App\Http\Controllers\FNZS_CuentBancController;
        use App\Http\Controllers\FNZS_MonedElectController;
        use App\Http\Controllers\FNZS_PagoOrdenController; 
        use App\Http\Controllers\FNZS_IndicadoresController;
        use App\Http\Controllers\FNZS_MovimientosBancariosController;
        use App\Http\Controllers\MAIN_ComisionesController;
        
    //gerencia
        use App\Http\Controllers\ModuleProyectosController;
        use App\Http\Controllers\JURI_EventosController;
    
    //ingresos
        use App\Http\Controllers\INGR_ProductosController;
        use App\Http\Controllers\INGR_ListaPreciosController;
        use App\Http\Controllers\INGR_ServiciosController;
        use App\Http\Controllers\INGR_DescuentosController;
        use App\Http\Controllers\INGR_PromocionesController;
        use App\Http\Controllers\INGR_ClientesController;
        use App\Http\Controllers\INGR_VentasController;
        use App\Http\Controllers\INGR_FacturacionController;
    
    //juridico
    
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
        use App\Http\Controllers\MAIN_UMedidaController;
        use App\Http\Controllers\MAIN_PaisController;
        use App\Http\Controllers\MAIN_CatSatController;
        use App\Http\Controllers\MAIN_DireccionesController;
        use App\Http\Controllers\MAIN_RegimenFiscalController;
        use App\Http\Controllers\MAIN_MenuController;
        use App\Http\Controllers\MAIN_UsuarioController;
        use App\Http\Controllers\MAIN_ModulosController;
        use App\Http\Controllers\MAIN_LandingController;
    
    //valor humano
        use App\Http\Controllers\VHUM_ReembolsosController;
        use App\Http\Controllers\VHUM_PersonalController;
        
//sos
    use App\Http\Controllers\MAIN_XmlValidateController;
    use App\Http\Controllers\MAIN_ImagesController;
    use App\Http\Controllers\MAIN_SettingsController;
    use App\Http\Controllers\MAIN_CfdiController;
    use App\Http\Controllers\MAIN_ClasificacionController;
    
//TERCEROS
    use App\Http\Controllers\TERC_AssociatesController;
    use App\Http\Controllers\TERC_AssociatesCatalogosController;
    use App\Http\Controllers\TERC_EmployeesController;
    
//chatGPT
    use App\Http\Controllers\MAIN_GPTController;
    
//rutas
    Route::get("catalogo_modulos",[MAIN_ModulosController::class,"catalogoModulosSOS"]);
    Route::post("modulo_configuracion",[MAIN_ModulosController::class,"modulosConfigSOS"]);
    
    //logueo_sistemas
        Route::post("usuario_login_main",[MAIN_UsuarioController::class,"loginUsuarioMain"]);
        Route::post("login_module_ssic",[MAIN_UsuarioController::class,"loginModuleSSIC"]);
        Route::post("get_access_token",[MAIN_UsuarioController::class,"get_access_token"]);
        Route::post("module_ssic_updatepass",[MAIN_UsuarioController::class,"userUpdatePass"]);
        Route::post("login_module_xml_download",[MAIN_UsuarioController::class,"loginModuleXmlDownload"]);
        Route::post("login_module_logistica",[MAIN_UsuarioController::class,"loginModuleLogistica"]);
        Route::post("login_module_compras",[MAIN_UsuarioController::class,"loginModuleCompras"]);
        Route::post("login_module_gestion_proyectos",[MAIN_UsuarioController::class,"loginModuleGestionProyectos"]);
        Route::post("updatepass_module_gestion_proyectos",[MAIN_UsuarioController::class,"updatePassModuleGestionProyectos"]);
        Route::post("login_module_terceros_associates",[MAIN_UsuarioController::class,"loginModuleTercerosAssociates"]);  
        Route::post("login_module_terceros_clientes",[MAIN_UsuarioController::class,"loginModuleTercerosCustomers"]);
        Route::post("login_module_terceros_proveedores",[MAIN_UsuarioController::class,"loginModuleTercerosSuppliers"]);
        Route::post("login_module_terceros_empleados",[MAIN_UsuarioController::class,"loginModuleTercerosEmployees"]);
        Route::post("user_update_firebase_code",[MAIN_UsuarioController::class,"firebaseCodeUpdate"]);
        Route::post("user_update_avatar",[MAIN_UsuarioController::class,"user_update_avatar"]);

    //ssic
        Route::post("login_ssic",[MAIN_UsuarioController::class,"sesionSsic"]);
        Route::post("login_ssic_mobile",[MAIN_UsuarioController::class,"sesionMobileSsic"]);
        Route::post("secondloginaccess",[MAIN_UsuarioController::class,"sesionSecondLoginAccess"]);

        //contabilidad
            //polizas modelo
            //series y folios de control interno
            //cuentas contables
                Route::get("catalogocuentas",[CONT_CuentasContablesController::class,"catalogoCuentas"]);
            //contribuciones
                Route::post("listaImpuestos",[CONT_ImpuestosController::class,"listaImpuestos"]);
                Route::post("catalogoimpuestos",[CONT_ImpuestosController::class,"catalogoImpuestosVig"]);
                Route::post("viewimpuestoselected",[CONT_ImpuestosController::class,"verImpuesto"]);
                Route::post("catalogoimpuestosdel",[CONT_ImpuestosController::class,"catalogoImpuestosDel"]);
            //catalogo de CFDI descargables periodicamente
            //politicas
                Route::post("politica_comisiones_lista",[CONT_PoliticasController::class,"politicaComisionesLista"]);
                Route::post("politica_comisiones_last",[CONT_PoliticasController::class,"politicaComisionesLast"]);

                Route::post("politica_reembolsos_lista",[CONT_PoliticasController::class,"politicaReembolsosLista"]);
                Route::post("politica_reembolsos_last",[CONT_PoliticasController::class,"politicaReembolsosLast"]);

                Route::post("politica_proveedores_lista",[CONT_PoliticasController::class,"politicaProveedoresLista"]);
                Route::post("politica_proveedores_last",[CONT_PoliticasController::class,"politicaProveedoresLast"]);

                Route::post("politicas_detalle",[CONT_PoliticasController::class,"politicasDetalle"]);
                Route::post("politica_update",[CONT_PoliticasController::class,"politica_update"]);
                Route::post("politica_nuevo_registro",[CONT_PoliticasController::class,"politicaNewRegistro"]);

        //egresos
            Route::post("empresa_config_eegr",[MAIN_EmpresasController::class,"empresaConfigEegr"]);
            //catalogos 
                //productos
                    Route::post("listaegresosProductosVigentes",[EGRE_ProductosController::class,"listaegresosProductosVigentes"]);
                    Route::post("listaegresosProductosProcessBuy",[EGRE_ProductosController::class,"listaegresosProductosProcessBuy"]);
                    Route::post("detalleproducto",[EGRE_ProductosController::class,"detalleProductoVigente"]);
                    Route::post("recargaprovproductos",[EGRE_ProductosController::class,"recargaProvProductos"]);
                    Route::post("detalleproductoproveedor",[EGRE_ProductosController::class,"detalleProductoProveedor"]);
                    Route::post("updatearticulologo",[EGRE_ProductosController::class,"updateArticuloLogo"]);
                    Route::post("updategeneralesproducto",[EGRE_ProductosController::class,"updateGeneralesProducto"]);
                    Route::post("deleteclaveprodproveedor",[EGRE_ProductosController::class,"deleteClaveProdProveedor"]);
                    Route::post("updateclaveprodproveedor",[EGRE_ProductosController::class,"updateClaveProdProveedor"]);
                    Route::post("appendclaveprodproveedor",[EGRE_ProductosController::class,"appendClaveProdProveedor"]);
                    Route::post("changalmproducto",[EGRE_ProductosController::class,"changAlmProducto"]);
                    Route::post("movepaparticulo",[EGRE_ProductosController::class,"deleteProducto"]);
                    Route::post("listaegresosProductosEliminados",[EGRE_ProductosController::class,"listaegresosProductosEliminados"]);
                    Route::post("paprestauraarticulo",[EGRE_ProductosController::class,"restauraProducto"]);
                    Route::post("eliminapaparticulo",[EGRE_ProductosController::class,"deletePapProducto"]);
                    Route::post("prodPorProveedor",[EGRE_ProductosController::class,"prodPorProveedor"]);
                    Route::post("createarticulo",[EGRE_ProductosController::class,"registroProducto"]); 
                    //lotes
                        Route::post("listalotesvigentes",[EGRE_LotesController::class,"listaLotesVigentes"]); 
                        Route::post("listalotesdelete",[EGRE_LotesController::class,"listaLotesdelete"]);
                        Route::post("detalleegresoslote",[EGRE_LotesController::class,"detalleEgresosLote"]);
                        Route::post("actualizaegresoslote",[EGRE_LotesController::class,"updateEgresosLote"]); 
                        Route::post("listadeletedlotes",[EGRE_LotesController::class,"listaLotesDeleted"]);  
                        Route::post("restartlote",[EGRE_LotesController::class,"loteRestart"]);  
                        Route::post("deleteloteperm",[EGRE_LotesController::class,"LoteDeletePerm"]);  
                        Route::post("registraLote",[EGRE_LotesController::class,"registraLote"]);  
                    //series
                        Route::post("listaseriesvigentes",[EGRE_ProdSeriesController::class,"listaSeriesVigentes"]); 
                    //pedimentos
                        Route::post("listaegresospedimentosvigentes",[EGRE_PedimentosController::class,"listaegresosPedimentosVigentes"]);
                        Route::post("detalleregresospedimento",[EGRE_PedimentosController::class,"detalleEgresosPedimento"]);
                        Route::post("actualizaegresospedimento",[EGRE_PedimentosController::class,"updateEgresosPedimento"]); 
                        Route::post("listaegresospedimentosdelete",[EGRE_PedimentosController::class,"listaegresosPedimentosDelete"]);
                        Route::post("listadeletedegresospedimentos",[EGRE_PedimentosController::class,"listaegresosPedimentosDeleted"]);
                        Route::post("restartpedimento",[EGRE_PedimentosController::class,"pedimentoRestart"]);
                        Route::post("pedimentodeleteperm",[EGRE_PedimentosController::class,"pedimentoDeletePerm"]);
                        Route::post("registrapedimento",[EGRE_PedimentosController::class,"registraPedimento"]);
                    //gastos
                        Route::post("listaegresosgastosvigentes",[EGRE_GastosController::class,"listaGastosVigentes"]);
                
                //servicios
                    Route::post("listaegresosServiciosVigentes",[EGRE_ServiciosController::class,"listaegresosServiciosVigentes"]);
                    Route::post("detalleservicioegresos",[EGRE_ServiciosController::class,"viewServicioEgresos"]);
                    Route::post("detalleservicioproveedor",[EGRE_ServiciosController::class,"detalleServicioProveedor"]);
                    Route::post("recargaprovservicios",[EGRE_ServiciosController::class,"recargaProvServicios"]);
                    Route::post("downpdfservegresos",[EGRE_ServiciosController::class,"downloadServicioEgresosPdf"]);
                    Route::post("updateservicioegresos",[EGRE_ServiciosController::class,"actualizaGeneralesServicio"]);
                    Route::post("updateservicioprov",[EGRE_ServiciosController::class,"actualizaProvClavesServicio"]);
                    Route::post("newservicioprov",[EGRE_ServiciosController::class,"newProvClavesServicio"]);
                    Route::post("deleteservicioprov",[EGRE_ServiciosController::class,"deleteProvClavesServicio"]);
                    Route::post("servicioegresostopap",[EGRE_ServiciosController::class,"deleteServicioEgresos"]);
                    Route::post("listaegresosServiciosEliminados",[EGRE_ServiciosController::class,"listaegresosServiciosEliminados"]);
                    Route::post("restartservicioegresos",[EGRE_ServiciosController::class,"restartServicioEgresos"]);
                    Route::post("servicioegresosdead",[EGRE_ServiciosController::class,"deleteDeadServicioEgresos"]);
                    Route::post("appendservicio",[EGRE_ServiciosController::class,"registroServicio"]);   
                    
                //activos
                    //activos fijos
                        Route::post("listaActivosFijos",[EGRE_ActivosFijosController::class,"getActivosFijos"]);
                        Route::post("listacompraActivosFijos",[EGRE_ActivosFijosController::class,"getActivosFijosCompra"]);
                        Route::post("viewActivoFijo",[EGRE_ActivosFijosController::class,"verActivoFijo"]);
                        Route::post("actualizageneralesactfijo",[EGRE_ActivosFijosController::class,"actualizaGeneralesActivoFijo"]);
                        Route::post("updateactivofijoprov",[EGRE_ActivosFijosController::class,"actualizaProvClavesActivo"]);
                        Route::post("newactivofijoprov",[EGRE_ActivosFijosController::class,"newProvClavesActivo"]);
                        Route::post("deleteactivofijoprov",[EGRE_ActivosFijosController::class,"deleteProvClavesActivo"]);
                        Route::post("deletepapeleraactivofijo",[EGRE_ActivosFijosController::class,"deleteActivoFijo"]);
                        Route::post("listaActivosFijosDeleted",[EGRE_ActivosFijosController::class,"getActivosFijosDeleted"]);
                        Route::post("restartActivosFijos",[EGRE_ActivosFijosController::class,"restartActivosFijos"]);
                        Route::post("deleteDeadActivosFijos",[EGRE_ActivosFijosController::class,"deleteDeadActivosFijos"]);
                        Route::post("clasificacionfijosactv",[EGRE_ActivosFijosController::class,"listaClassAct"]);
                        Route::post("agregaclassactivo",[EGRE_ActivosFijosController::class,"agregaClassActivo"]);
                        Route::post("appendactivofijo",[EGRE_ActivosFijosController::class,"registroActivoFijo"]);
                    //activos fijos
                        Route::post("listaActivosIntan",[EGRE_ActivosIntangiblesController::class,"getListActIntangibles"]);
                        Route::post("listacompraActivosIntan",[EGRE_ActivosIntangiblesController::class,"getListActIntangiblesCompras"]);
                        Route::post("viewActivoIntan",[EGRE_ActivosIntangiblesController::class,"verActivoIntang"]);
                        Route::post("actualizageneralesactintang",[EGRE_ActivosIntangiblesController::class,"actualizageneralesActivoIntang"]);
                        Route::post("updateactivointangprov",[EGRE_ActivosIntangiblesController::class,"actualizaProvClavesActivoIntang"]);
                        Route::post("nuevactivointangprov",[EGRE_ActivosIntangiblesController::class,"newProvClavesActivoIntang"]);
                        Route::post("deleteactivointangprov",[EGRE_ActivosIntangiblesController::class,"deleteProvClavesActivoIntang"]);
                        Route::post("deletepapeleraactivointang",[EGRE_ActivosIntangiblesController::class,"deleteActivoIntang"]);
                        Route::post("listaactivosintandeleted",[EGRE_ActivosIntangiblesController::class,"getActivosIntangDeleted"]);
                        Route::post("restartActivosintang",[EGRE_ActivosIntangiblesController::class,"restartActivosIntang"]);
                        Route::post("deleteDeadActivosIntang",[EGRE_ActivosIntangiblesController::class,"deleteDeadActivosIntang"]);
                        Route::post("activosclasificacionintang",[EGRE_ActivosIntangiblesController::class,"listaClassActIntangibles"]);
                        Route::post("agregaclassactivointang",[EGRE_ActivosFijosController::class,"agregaClassActivoIntang"]);
                        Route::post("appendactivointangible",[EGRE_ActivosIntangiblesController::class,"registroActivoIntang"]);
                        
                //proveedores
                    Route::post("listaproveedoresgen",[EGRE_ProveedoresController::class,"proveedoresGen"]);
                    Route::post("catalogoprovdel",[EGRE_ProveedoresController::class,"getCatalogoProvDel"]);
                    Route::post("catalogo_prov_autorizados",[EGRE_ProveedoresController::class,"catalogoProvAutorizados"]);
                    Route::post("catalogo_prov_no_autorizados",[EGRE_ProveedoresController::class,"catalogoProvNotAutorizados"]);
                    Route::post("solicitar_validacion_proveedores",[EGRE_ProveedoresController::class,"requestValidacionProv"]);
                    Route::post("validacion_proceso_proveedores",[EGRE_ProveedoresController::class,"validacionProcesoProveedores"]);
                    Route::post("detalle_proveedores",[EGRE_ProveedoresController::class,"verDetalleProveedor"]);
                    Route::post("registracuentacontableproveedor",[EGRE_ProveedoresController::class,"createCuentaContableProv"]);
                    Route::post("actualizarfcproveedor",[EGRE_ProveedoresController::class,"actualizaRfcProv"]);
                    Route::post("actualizaidtaxproveedor",[EGRE_ProveedoresController::class,"actualizaIdTaxProv"]);
                    Route::post("actualizageneralespfproveedor",[EGRE_ProveedoresController::class,"actualizaGeneralesPF"]);
                    Route::post("actualizageneralespmproveedor",[EGRE_ProveedoresController::class,"actualizaGeneralesPM"]);
                    Route::post("actualizaredesproveedor",[EGRE_ProveedoresController::class,"actualizaRedes"]);
                    Route::post("ingresapersonalproveedor",[EGRE_ProveedoresController::class,"ingresaPersonalProveedor"]);
                    Route::post("eliminapersonalproveedor",[EGRE_ProveedoresController::class,"deletePersonalProv"]);
                    Route::post("actualizapersonalgeneralesproveedor",[EGRE_ProveedoresController::class,"actualizaGeneralesPersonal"]);
                    Route::post("agregapersonaltelefonoproveedor",[EGRE_ProveedoresController::class,"nuevoTelefonoPersonal"]);
                    Route::post("actualizapersonaltelefonoproveedor",[EGRE_ProveedoresController::class,"actualizaTelefonoPersonal"]);
                    Route::post("eliminapersonaltelefonoproveedor",[EGRE_ProveedoresController::class,"eliminaTelefonoPersonal"]);
                    Route::post("restartpersonaltelefonoproveedor",[EGRE_ProveedoresController::class,"restartTelefonoPersonal"]);
                    Route::post("eliminapermpersonaltelefonoproveedor",[EGRE_ProveedoresController::class,"eliminaTelefonoPersonalPermanente"]);
                    Route::post("agregapersonalemailproveedor",[EGRE_ProveedoresController::class,"nuevoCorreoPersonal"]);
                    Route::post("actualizapersonalemailproveedor",[EGRE_ProveedoresController::class,"actualizaCorreoPersonal"]);
                    Route::post("eliminapersonalemailproveedor",[EGRE_ProveedoresController::class,"eliminaCorreoPersonal"]);
                    Route::post("restartpersonalemailproveedor",[EGRE_ProveedoresController::class,"restartCorreoPersonal"]);
                    Route::post("eliminapermpersonalemailproveedor",[EGRE_ProveedoresController::class,"eliminaCorreoPersonalPermanente"]);
                    Route::post("restartpersonalproveedor",[EGRE_ProveedoresController::class,"restartPersonalProv"]);
                    Route::post("deletepermanentepersonalproveedor",[EGRE_ProveedoresController::class,"deletePersonalProvPermanente"]);
                    Route::post("updatecontanciafiscalsitload",[EGRE_ProveedoresController::class,"updatecontanciafiscalsitload"]);
                    Route::post("updatecontanciafiscalsitbase64",[EGRE_ProveedoresController::class,"updatecontanciafiscalsitbase64"]);
                    Route::post("updatecumplimientoload",[EGRE_ProveedoresController::class,"updatecumplimientoload"]);
                    Route::post("updatecumplimientobase64",[EGRE_ProveedoresController::class,"updatecumplimientobase64"]);
                    Route::post("updatecreditosproveedor",[EGRE_ProveedoresController::class,"updateCreditosProveedor"]);
                    Route::post("updateformapagoproveedor",[EGRE_ProveedoresController::class,"updateFormaPagoProveedor"]);
                    Route::post("updatefpagoproveedorestcuenta",[EGRE_ProveedoresController::class,"updatefPagoProveedorEstCuenta"]);
                    Route::post("updateclabeinterbpagoproveedor",[EGRE_ProveedoresController::class,"updateClabeInterbPagoProveedor"]);
                    Route::post("registranuevaubicacionnacionalproveedor",[EGRE_ProveedoresController::class,"registraNuevaUbicacionNacionalProveedor"]);
                    Route::post("registranuevaubicacionextranjeroproveedor",[EGRE_ProveedoresController::class,"registraNuevaUbicacionExtranjeroProveedor"]);
                    Route::post("updateubicacionnacionalproveedor",[EGRE_ProveedoresController::class,"updateUbicacionNacionalProveedor"]);
                    Route::post("updateubicacionextranjeroproveedor",[EGRE_ProveedoresController::class,"updateUbicacionExtranjeroProveedor"]);
                    Route::post("deleteubicacionproveedor",[EGRE_ProveedoresController::class,"deleteUbicacionProveedor"]);
                    Route::post("restaurarubicacionproveedor",[EGRE_ProveedoresController::class,"restaurarUbicacionProveedor"]);
                    Route::post("deletepermubicacionproveedor",[EGRE_ProveedoresController::class,"deletePermUbicacionProveedor"]);
                    Route::post("deleteproveedor",[EGRE_ProveedoresController::class,"deleteProveedor"]);
                    Route::post("restaurarproveedor",[EGRE_ProveedoresController::class,"restaurarProveedor"]);
                    Route::post("deletepermproveedor",[EGRE_ProveedoresController::class,"deletePermProveedor"]);
                    Route::post("verify_exist_proveedor_one",[EGRE_ProveedoresController::class,"buscaRfcAllProveedorOut"]);
                    Route::post("egresos_search_all_proveedores",[EGRE_ProveedoresController::class,"buscaRfcAllProveedor"]);
                    Route::post("egresos-busquedaproveedor",[EGRE_ProveedoresController::class,"buscaRFProveedor"]);
                    Route::post("egresos-busquedaextproveedor",[EGRE_ProveedoresController::class,"buscaRFProveedorExtPM"]);
                    Route::post("egresos-busquedapfextproveedor",[EGRE_ProveedoresController::class,"buscaRFProveedorExtPF"]);
                    Route::post("proveedor_solicitud_registro",[EGRE_ProveedoresController::class,"registraProveedorMin"]);
                    Route::post("proveedor_solicitud_registro_compras",[EGRE_ProveedoresController::class,"registraProveedorModuloCompras"]);
                    //Route::post("proveedor_solicitud_registro",[EGRE_ProveedoresController::class,"proveedorSolicitudRegistro"]);
                    Route::post("egresos-registraproveedor",[EGRE_ProveedoresController::class,"registraProveedorMax"]);
                    
                //establecimientos
                    Route::post("totalalmacenes",[EGRE_AlmacenController::class,"totalAlmacenes"]);
                    Route::post("listdireccionalm",[EGRE_AlmacenController::class,"direccionAlmacen"]);
                    Route::post("listdireccionalmcomplete",[EGRE_AlmacenController::class,"direccionAlmacenComplete"]);
                    Route::post("listdireccionalmdeleted",[EGRE_AlmacenController::class,"direccionAlmacenDeleted"]);
                    Route::post("detalleestablecimiento",[EGRE_AlmacenController::class,"detalleAlmacen"]);
                    Route::post("updategeneralestablecimiento",[EGRE_AlmacenController::class,"updateGenerales"]);
                    Route::post("updateubicanacestab",[EGRE_AlmacenController::class,"updateUbicacionNacional"]);
                    Route::post("updateubicaextestab",[EGRE_AlmacenController::class,"updateUbicacionExtranjero"]);
                    Route::post("quitapersonalestab",[EGRE_AlmacenController::class,"eliminaPersonalEstablecimiento"]);
                    Route::post("agregapersonalestab",[EGRE_AlmacenController::class,"agregaPersonalEstablecimiento"]);
                    Route::post("registraestablecimientonacional",[EGRE_AlmacenController::class,"registraEstablecimientoNacional"]);
                    Route::post("registraestablecimientoextranjero",[EGRE_AlmacenController::class,"registraEstablecimientoExtranjero"]);
                    
            //compras
                //requisiciones
                    Route::post("catalogo_requisiciones",[EGRE_RequisicionesController::class,"catalogoRequisiciones"]);
                    Route::get("verRequisicionPdf/{tokenRequi}",[EGRE_RequisicionesController::class,"verRequisicionPdfHtml"]);
                    Route::post("detalle_requisicion",[EGRE_RequisicionesController::class,"detalleRequisicion"]);
                    Route::post("detalle_requisicion_cot_list",[EGRE_RequisicionesController::class,"detalleRequisicionWithCotizaciones"]);
                    Route::post("eliminar_requisicion_detalle",[EGRE_RequisicionesController::class,"eliminarRequisicionDetalle"]);
                    Route::post("autoriza_requisicion",[EGRE_RequisicionesController::class,"autorizaRequisicion"]);
                    Route::post("autoriza_requisicion_all",[EGRE_RequisicionesController::class,"autorizaRequisicionAll"]);
                    Route::post("desautoriza_requisicion",[EGRE_RequisicionesController::class,"desautorizaRequisicion"]);
                    Route::post("update_requisicion_proyecto",[EGRE_RequisicionesController::class,"updateRequisicionProyecto"]);
                    Route::post("update_requisicion_prioridad",[EGRE_RequisicionesController::class,"updateRequisicionPrioridad"]);
                    Route::post("update_requisicion_list_tipo",[EGRE_RequisicionesController::class,"updateRequisicionListTipo"]);
                    Route::post("update_requisicion_list_concepto",[EGRE_RequisicionesController::class,"updateRequisicionListConcepto"]);
                    Route::post("update_requisicion_add_caract_list",[EGRE_RequisicionesController::class,"updateRequisicionAddCaractList"]);
                    Route::post("update_requisicion_delete_caract_list",[EGRE_RequisicionesController::class,"updateRequisicionDeleteCaractList"]);
                    Route::post("update_requisicion_list_cantidad",[EGRE_RequisicionesController::class,"updateRequisicionListCantidad"]);
                    Route::post("update_requisicion_list_unidad_medida",[EGRE_RequisicionesController::class,"updateRequisicionListUnidadMedida"]);
                    Route::post("update_requisicion_list_marca",[EGRE_RequisicionesController::class,"updateRequisicionListMarca"]);
                    Route::post("registraRequisicionLista",[EGRE_RequisicionesController::class,"registraRequisicionLista"]);
                    Route::post("requisicion_load_docs",[EGRE_RequisicionesController::class,"requisicionLoadDocs"]);
                    Route::post("registraRequisicionDocumento",[EGRE_RequisicionesController::class,"catalogoRequisiciones"]);
                    Route::post("totalRequisicionesPend",[EGRE_RequisicionesController::class,"totalRequisicionesPendientes"]);
                    Route::post("folioReqMax",[EGRE_RequisicionesController::class,"folioReqMax"]);
                    Route::get("listacaracteristicas",[EGRE_RequisicionesController::class,"listaCaracteristicas"]);
                //cotizaciones
                    Route::post("solicitudes_cotizacion",[EGRE_CotizacionesController::class,"solicitudesCotizacion"]);
                    Route::post("solicitudes_cotizacion_cotizadas",[EGRE_CotizacionesController::class,"solicitudesCotizacionCheck"]);
                    Route::post("solicitud_cotizacion_detalle",[EGRE_CotizacionesController::class,"solicitudCotizacionDetalle"]);
                    Route::post("catalogo_cotizaciones",[EGRE_CotizacionesController::class,"catalogoCotizaciones"]);
                    Route::post("cotizacion_detalle",[EGRE_CotizacionesController::class,"cotizacionDetalle"]);
                    Route::post("totalCotizacionesPend",[EGRE_CotizacionesController::class,"totalCotizacionesPendientes"]);
                    Route::post("registrar_cotizacion_preq",[EGRE_CotizacionesController::class,"registrarCotizacionPReq"]);
                    Route::post("last_cotizacion_preq",[EGRE_CotizacionesController::class,"detalleReqLastCotizacion"]);
                    Route::post("autoriza_cotizacion_all",[EGRE_CotizacionesController::class,"autorizarAllCotizacion"]);
                    Route::post("autoriza_cotizacion",[EGRE_CotizacionesController::class,"autorizaCotizacion"]);
                    Route::post("desautoriza_cotizacion",[EGRE_CotizacionesController::class,"desautorizaCotizacion"]);
                    Route::post("registrar_cotizacion_directa",[EGRE_CotizacionesController::class,"registrarCotizacionDirecta"]);
                    Route::post("catalogo_cotizacion_directa",[EGRE_CotizacionesController::class,"catalogoCotizacionDirecta"]);
                    Route::post("autoriza_cotizacion_directa",[EGRE_CotizacionesController::class,"autorizaCotizacionDirecta"]);
                    Route::post("desautoriza_cotizacion_directa",[EGRE_CotizacionesController::class,"desautorizaCotizacionDirecta"]);
                    Route::post("cotizaciones_autorizadas",[EGRE_CotizacionesController::class,"cotizacionesAutorizadas"]);
                    Route::post("cotizacion_confirmar_contactoprov",[EGRE_CotizacionesController::class,"cotizacionConfirmarContactoProv"]);
                    Route::post("cotizaciones_preorden_compra",[EGRE_CotizacionesController::class,"cotizacionesPreordenCompra"]);
                    
                //compras listaComprasProd
                    Route::post("selectFolioCompra",[EGRE_ComprasController::class,"selectFolioCompra"]);
                    Route::post("listaComprasProd",[EGRE_ComprasController::class,"listaComprasProd"]);
                    Route::post("validaestructxmlingresos",[MAIN_XmlValidateController::class,"validaEstructXmlIngresos"]);
                    Route::post("validaestructxmlegresos",[MAIN_XmlValidateController::class,"validaEstructXmlEgresos"]);
                    Route::post("listaprdservcomp",[EGRE_ComprasController::class,"cargaArticulosCompras"]);
                    Route::post("listaprdservcompprov",[EGRE_ComprasController::class,"cargaArticulosCompras"]);
                    Route::post("consultarticulocompra",[EGRE_ComprasController::class,"consultArticuloCompras"]);
                    Route::get("vaduanas",[MAIN_XmlValidateController::class,"aduanas"]);
                    Route::post("registracompra",[EGRE_ComprasController::class,"registraCompra"]);
                    Route::post("pruebaregistracompra",[EGRE_ComprasController::class,"pruebaregistraCompra"]);
                    //seguimiento de compras
                        //compras no autorizadas
                            Route::post("listanoautorizadacompra",[EGRE_ComprasController::class,"listanoautorizadaCompra"]);
                            Route::post("autorizarcompra",[EGRE_ComprasController::class,"autorizarCompra"]);
                            Route::post("cancelarcompra",[EGRE_ComprasController::class,"cancelarCompra"]);
                        //prorrateos
                            Route::post("listaegresosnoprorratea",[EGRE_ProrrateosController::class,"listaNoProrrateos"]);
                            Route::post("detailegresosnoprorratefalse",[EGRE_ProrrateosController::class,"detalleNoProrrateos"]);
                            Route::post("listaegresosprorrateos",[EGRE_ProrrateosController::class,"listaProrrateos"]);
                            Route::post("detailegresosprorrateos",[EGRE_ProrrateosController::class,"detalleProrrateo"]);
                            Route::post("historialegresosprorrateos",[EGRE_ProrrateosController::class,"historialDetalleProrrateo"]);
                            Route::post("deletehistoricdetalleprorrat",[EGRE_ProrrateosController::class,"eliminarHistoricoDetalleProrrateo"]);
                            Route::post("guardaregresosprorrateos",[EGRE_ProrrateosController::class,"guardarProrrateo"]);
                        //compras autorizadas
                            Route::post("detallecomprasautorizadas",[EGRE_ComprasController::class,"detalleComprasAutorizadas"]);
                            Route::post("listacomprasautorizadas",[EGRE_ComprasController::class,"listaComprasAutorizadas"]);
                            Route::post("trueperiodoespera24hrs",[EGRE_ComprasController::class,"habilitaPeridoEspera"]);
                            Route::post("rechazoscomprasautorizadas",[EGRE_ComprasController::class,"rechazosComprasAutorizadas"]);
                            Route::post("recibecepcionprodcompras",[EGRE_ComprasController::class,"recibeProdComprasAlmacen"]);
                            Route::post("buyrecibeactfijos",[EGRE_ComprasController::class,"recibeActivoFijoComprasAlmacen"]);
                            Route::post("recibeactintangbuy",[EGRE_ComprasController::class,"recibeActivoIntangComprasAlmacen"]);
                            Route::post("recibeserviciosbuy",[EGRE_ComprasController::class,"recibeServComprasAlmacen"]);
                            Route::post("listacomprasdevoluciones",[EGRE_ComprasController::class,"listaComprasDevoluciones"]);
                //reembolsos
                    Route::post("egr_reembolso_lista",[EGRE_ReembolsosController::class,"reembolso_lista"]); 
                    Route::post("egr_reembolso_detalle",[EGRE_ReembolsosController::class,"egr_reembolso_detalle"]);
                    Route::post("egr_reembolso_auth",[EGRE_ReembolsosController::class,"egr_reembolso_auth"]);
                //comisiones
                    Route::post("comision_registro_aviso_eegr",[MAIN_ComisionesController::class,"comisionRegistroAvisoEegr"]);
                
        //finanzas (tesoreria)
            //indicadores
                Route::get("ver_indicadores",[FNZS_IndicadoresController::class,"listaIndicadores"]);
                Route::get("indicadores_inpc",[FNZS_IndicadoresController::class,"indicadores_inpc"]);
                Route::get("indicadores_tasa_recargos",[FNZS_IndicadoresController::class,"indicadores_tasa_recargos"]);
                Route::get("indicadores_tipo_cambio",[FNZS_IndicadoresController::class,"indicadores_tipo_cambio"]);
                Route::get("indicadores_salario_minimo",[FNZS_IndicadoresController::class,"indicadores_salario_minimo"]);
                Route::get("indicadores_salario_min_front",[FNZS_IndicadoresController::class,"indicadores_salario_min_front"]);
                Route::get("indicadores_uma",[FNZS_IndicadoresController::class,"indicadores_uma"]);
                Route::get("indicadores_udi",[FNZS_IndicadoresController::class,"indicadores_udi"]);
                Route::get("indicadores_tiie",[FNZS_IndicadoresController::class,"indicadores_tiie"]);
            //catalogos
                //catalogo de acreedores
                //cuentas bancarias
                    Route::post("foliocuentabanc",[FNZS_CuentBancController::class,"folioCuentaBancaria"]);
                    Route::post("responsablecuenta",[FNZS_CuentBancController::class,"responsableCuenta"]);
                    Route::post("cuentasvig",[FNZS_CuentBancController::class,"cuentasVig"]);
                    Route::post("cuentasdel",[FNZS_CuentBancController::class,"cuentasDel"]);
                    Route::post("detallecuentavig",[FNZS_CuentBancController::class,"detalleCuentasVig"]);
                    Route::post("detalleCuentaMonBancovig",[FNZS_CuentBancController::class,"detalleCuentaMonederoCBancoVig"]);
                    Route::post("registracuentabancaria",[FNZS_CuentBancController::class,"registraCuentaBanc"]); 
                    Route::post("updatecuentbncaria",[FNZS_CuentBancController::class,"updateCuentaBanc"]);
                    Route::post("eliminacuentaban",[FNZS_CuentBancController::class,"deleteCuentaBancaria"]);
                    Route::post("restauracuentaban",[FNZS_CuentBancController::class,"restaurarCuentaBancaria"]);
                    Route::post("deltepermcuentaban",[FNZS_CuentBancController::class,"deltPermanenteCuentaBancaria"]);
                    Route::post("actualizacuentabankdispositivo",[TICS_DispositivosController::class,"actualizaCuentaBankDispositivo"]);
                    Route::post("unvinccuentabankdispositivo",[TICS_DispositivosController::class,"unvincCuentaBankDispositivo"]);
                    Route::post("actualizacuentamoneddispositivo",[TICS_DispositivosController::class,"actualizaCuentaMonedDispositivo"]);
                    Route::post("unvinccuentamoneddispositivo",[TICS_DispositivosController::class,"actualizaCuentaMonedDispositivo"]);    
                //petty cash
                //caja
                    Route::post("foliocaja",[FNZS_CajaController::class,"folioCaja"]);
                    Route::post("catalogo_cajas_true",[FNZS_CajaController::class,"catalogoCajasActual"]);
                    Route::post("catalogo_cajas_deleted",[FNZS_CajaController::class,"catalogoCajasDeleted"]);
                    Route::post("detallecaja",[FNZS_CajaController::class,"detalleCajaVig"]);
                    Route::post("responsablecaja",[FNZS_CajaController::class,"respCaja"]);
                    Route::post("registracaja",[FNZS_CajaController::class,"registraCaja"]);
                    Route::put("updatealmacencaja",[FNZS_CajaController::class,"updateAlmacenCaja"]);
                    Route::post("chngperscja",[FNZS_CajaController::class,"desvincRespCaja"]);
                    Route::post("vnculspnbcaja",[FNZS_CajaController::class,"vinculaRespCaja"]);
                    Route::post("updtpersnew",[FNZS_CajaController::class,"updateAlmacenNewCaja"]);
                    Route::post("updtecja",[FNZS_CajaController::class,"updateCaja"]);
                    Route::post("editacortecja",[FNZS_CajaController::class,"editaCorteCaja"]);
                    Route::post("newcortecja",[FNZS_CajaController::class,"agregaNewCorteCaja"]);
                    Route::post("eliminacortecja",[FNZS_CajaController::class,"deleteCorteCaja"]);
                    Route::post("eliminacja",[FNZS_CajaController::class,"deleteCaja"]);
                    Route::post("restauracaja",[FNZS_CajaController::class,"restaurarCaja"]); 
                    Route::post("eliminapermcj",[FNZS_CajaController::class,"eliminaPrmannteCaja"]);
                    Route::post("unvinccajadispositivo",[TICS_DispositivosController::class,"unvincCajaDispositivo"]);
                //monedero electronico
                    Route::post("foliomonelectronico",[FNZS_MonedElectController::class,"folioMonederoElectronico"]);
                    Route::post("responsablemonedero",[FNZS_MonedElectController::class,"responsableMonedero"]);
                    Route::post("verlistamonedero",[FNZS_MonedElectController::class,"ListaMonederoVig"]);
                    Route::post("verlistamonederodel",[FNZS_MonedElectController::class,"ListaMonederoDel"]);
                    Route::post("detallemonedero",[FNZS_MonedElectController::class,"detalleMonederoVig"]);
                    Route::post("registramonederoelctrnico",[FNZS_MonedElectController::class,"registrarMonederoElctronico"]);
                    Route::post("updatemonederoelectronico",[FNZS_MonedElectController::class,"updateMonederoElectronico"]);
                    Route::post("eliminamonelectronico",[FNZS_MonedElectController::class,"eliminarMonederoElctronico"]);
                    Route::post("restauramonelectronico",[FNZS_MonedElectController::class,"restaurarMonederoElctronico"]);
                    Route::post("deletPermmonederoelctrnico",[FNZS_MonedElectController::class,"deletPermMonederoElctronico"]); 
                    
                //movimientos bancarios
                    Route::post("catalogo_movimientos_bancarios_cuent",[FNZS_MovimientosBancariosController::class,"movimientosBancariosCuentasAll"]); 
                    Route::post("movimientos_bancarios_cuenta_selected",[FNZS_MovimientosBancariosController::class,"movimientosBancariosCuentaToken"]); 
                    
                    Route::post("registra_ajuste_cuenta_sin_auth",[FNZS_MovimientosBancariosController::class,"movimientosBancariosCuentasAll"]); 
                    Route::post("registra_ajuste_cuenta_autorizado",[FNZS_MovimientosBancariosController::class,"registra_ajuste_cuenta_autorizado"]); 
                //movimientos en efectivo

            //ordenes de pago
                Route::post("listageneralordenespago",[FNZS_PagoOrdenController::class,"listaGeneralOrdenesPago"]);
                Route::post("autorizar_orden_pago",[FNZS_PagoOrdenController::class,"autorizarOrdenPago"]);
                Route::post("desautorizar_orden_pago",[FNZS_PagoOrdenController::class,"desautorizarOrdenPago"]);
                Route::post("catalogopagosdone",[FNZS_PagoOrdenController::class,"catalogoPagosDone"]);
                Route::post("ordenpago_registrapagosimple",[FNZS_PagoOrdenController::class,"generaPagoSimple"]);
                Route::post("ordenpago_pagos_realizados",[FNZS_PagoOrdenController::class,"ordenpPagosRealizados"]);
                
                //compras
                    Route::post("countordenespago",[FNZS_PagoOrdenController::class,"countOrdenPagoCompras"]);
                    Route::post("listaordenespagocompras",[FNZS_PagoOrdenController::class,"listaOrdenPagoCompras"]);
                    Route::post("detalleordenpagocompras",[FNZS_PagoOrdenController::class,"detalleOrdenPagoCompras"]); 
                    Route::post("registrapagodirecto",[FNZS_PagoOrdenController::class,"pagarOrdenPagoDirecto"]);
                    
                //ventas
                
                //reembolsos
                    Route::post("op_reembolso_lista",[FNZS_PagoOrdenController::class,"reembolso_op_lista"]);
                    Route::post("op_reembolso_detalle",[FNZS_PagoOrdenController::class,"reembolso_op_detalle"]);
                    Route::post("registrapagoreembolso_nivel_uno",[FNZS_PagoOrdenController::class,"pagarOrdenPagoReembolso"]);
                    Route::post("registrapagoreembolso_directo",[FNZS_PagoOrdenController::class,"pagarReembolso"]);
                    Route::post("detenerpagoreembolso",[FNZS_PagoOrdenController::class,"desautorizarPagoReembolso"]);
                    Route::post("autorizarpagoreembolso",[FNZS_PagoOrdenController::class,"autorizarPagoReembolso"]);
            //comisiones
                Route::post("comision_lista_general",[MAIN_ComisionesController::class,"comisionListaGeneral"]);
                Route::post("comisiones_monitoreo",[MAIN_ComisionesController::class,"comisionesMonitoreo"]);
                Route::post("comision_listas_recibe_dinero",[MAIN_ComisionesController::class,"comisionListasRecibeDinero"]);
                Route::post("comision_registro_aviso_fnzs",[MAIN_ComisionesController::class,"comisionRegistroAvisoFnzs"]);
                Route::post("comisiones_solicitud_apertura",[MAIN_ComisionesController::class,"comisionesSolicitudApertura"]);
                Route::post("comision_solicitar_apertura",[MAIN_ComisionesController::class,"comisionSolicitarApertura"]);
                Route::post("comision_apertura_reabrir",[MAIN_ComisionesController::class,"comisionAperturaReabrir"]);
        //ingresos
            //catalogos
                //lista_precios
                    Route::post("getlistaprecios",[INGR_ListaPreciosController::class,"getListaPrecios"]);
                //mercancias
                    Route::post("registralistapreciosmercancias",[INGR_ListaPreciosController::class,"registralistaPreciosMerc"]);
                    Route::post("updatelistapreciosmercancias",[INGR_ListaPreciosController::class,"updatelistaPreciosMerc"]);
                //servicios
                    Route::post("registralistapreciosserv",[INGR_ListaPreciosController::class,"registralistaPreciosServ"]);
                    Route::post("updatelistapreciosserv",[INGR_ListaPreciosController::class,"updatelistaPreciosServ"]);    
                //productos
                    Route::post("listavntsProductosVigentes",[INGR_ProductosController::class,"listaingresosProductosVigentes"]);
                    Route::post("detallemercancia",[INGR_ProductosController::class,"detalleProductoIngresos"]);
                    Route::post("registradescuentomercancia",[INGR_DescuentosController::class,"registrarMercDescuento"]);
                    Route::post("vincdescuentomercancia",[INGR_DescuentosController::class,"vincularMercDescuento"]);
                    Route::post("desvincdescuentomercancia",[INGR_DescuentosController::class,"desvincularMercDescuento"]);
                    Route::post("registrapromocionmercancia",[INGR_PromocionesController::class,"registrarMercPromocion"]);
                    Route::post("vincpromocionmercancia",[INGR_PromocionesController::class,"vincularMercPromocion"]);
                    Route::post("desvincpromocionmercancia",[INGR_PromocionesController::class,"desvincularMercPromocion"]);
                    Route::post("listavntsProductosEliminados",[INGR_ProductosController::class,"listavntsProductosEliminados"]);
                //servicios
                    Route::post("listaserviciosvigentesingresos",[INGR_ServiciosController::class,"listaServiciosVigentesIngresos"]);
                    Route::post("simulaprecioservicio",[INGR_ServiciosController::class,"simulaPrecioServicio"]);
                    Route::post("detalleingresosservicio",[INGR_ServiciosController::class,"viewServicioIngresos"]);
                    Route::post("downloadservicioingresospdf",[INGR_ServiciosController::class,"downloadServicioIngresosPdf"]);
                    Route::post("actualizageneralservicioingresos",[INGR_ServiciosController::class,"actualizaGeneralesServicioIngresos"]);
                    Route::post("vincimpuestoservicio",[INGR_ServiciosController::class,"vincularServicioImpuesto"]);
                    Route::post("desvincimpuestoservicio",[INGR_ServiciosController::class,"desvincularServicioImpuesto"]);
                    Route::post("registradescuentoservicio",[INGR_DescuentosController::class,"registrarServicioDescuento"]);
                    Route::post("vincdescuentoservicio",[INGR_DescuentosController::class,"vincularServicioDescuento"]);
                    Route::post("desvincdescuentoservicio",[INGR_DescuentosController::class,"desvincularServicioDescuento"]);
                    Route::post("registrapromocionservicio",[INGR_PromocionesController::class,"registrarServicioPromocion"]);
                    Route::post("vincpromocionservicio",[INGR_PromocionesController::class,"vincularServicioPromocion"]);
                    Route::post("desvincpromocionservicio",[INGR_PromocionesController::class,"desvincularServicioPromocion"]);
                    Route::post("newclienteclaveservicio",[INGR_ServiciosController::class,"newClienteClavesServicio"]);  
                    Route::post("clavesactualizaclienteservicio",[INGR_ServiciosController::class,"actualizaClienteClavesServicio"]);
                    Route::post("deleteclavesclienteservicio",[INGR_ServiciosController::class,"deleteClienteClavesServicio"]);
                    Route::post("deleteservicioingresos",[INGR_ServiciosController::class,"deleteServicioIngresos"]);
                    Route::post("listaservicioseliminadosingresos",[INGR_ServiciosController::class,"listaServiciosEliminadosIngresos"]);
                    Route::post("servicioingresosrestart",[INGR_ServiciosController::class,"restartServicioIngresos"]);
                    Route::post("eliminazionservingresos",[INGR_ServiciosController::class,"deleteDeadServicioIngresos"]);
                    Route::post("registroservicioingresos",[INGR_ServiciosController::class,"registroServicioIngresos"]);
                //descuentos
                    Route::post("foliomaxdescuentos",[INGR_DescuentosController::class,"folioMaxDescuento"]);
                    Route::post("folionewdescuentos",[INGR_DescuentosController::class,"folioNewRegDescuento"]);
                    Route::post("listadescuentos",[INGR_DescuentosController::class,"listaDescuentos"]);
                    Route::post("descuentosselected",[INGR_DescuentosController::class,"verDescuento"]);
                    Route::post("desactivadescuento",[INGR_DescuentosController::class,"stopDescuento"]);
                    Route::post("habilitadescuento",[INGR_DescuentosController::class,"habilitarDescuento"]);
                    Route::post("updategeneralesdescuento",[INGR_DescuentosController::class,"updateGeneralesDescuento"]);
                    Route::post("descuentosdesac",[INGR_DescuentosController::class,"listaDescuentosDeact"]);
                    Route::post("eliminadescuento",[INGR_DescuentosController::class,"eliminadescuento"]);
                    Route::post("restauradescuento",[INGR_DescuentosController::class,"restauradescuento"]);
                    Route::post("deadeliminadescuento",[INGR_DescuentosController::class,"eliminaPermDescuento"]);
                    Route::post("descuentosdelete",[INGR_DescuentosController::class,"listaDescuentosDel"]);
                    Route::post("registranuevodescuento",[INGR_DescuentosController::class,"registraDescuento"]);
                //promociones
                    Route::post("foliomaxpromocion",[INGR_PromocionesController::class,"folioMaxPromocion"]);
                    Route::post("folionewpromocion",[INGR_PromocionesController::class,"folioNewRegPromocion"]);
                    Route::post("listapromociones",[INGR_PromocionesController::class,"listaPromociones"]);
                    Route::post("promocionesselected",[INGR_PromocionesController::class,"verPromocion"]);
                    Route::post("desactivapromocion",[INGR_PromocionesController::class,"stopPromocion"]);
                    Route::post("habilitapromocion",[INGR_PromocionesController::class,"habilitarPromocion"]);
                    Route::post("updategeneralespromocion",[INGR_PromocionesController::class,"updateGeneralesPromocion"]);
                    Route::post("promocionesdesac",[INGR_PromocionesController::class,"listaPromocionesDesac"]);
                    Route::post("eliminapromocion",[INGR_PromocionesController::class,"eliminapromocion"]);
                    Route::post("restaurapromocion",[INGR_PromocionesController::class,"restaurapromocion"]);
                    Route::post("deadeliminapromocion",[INGR_PromocionesController::class,"eliminaPermPromocion"]);
                    Route::post("promocionesdelete",[INGR_PromocionesController::class,"listaPromocionesDel"]);
                    Route::post("registranuevopromocion",[INGR_PromocionesController::class,"registraPromocion"]);
                //clientes
                    Route::post("listaclientes",[INGR_ClientesController::class,"ClientesGen"]);
                    Route::post("clientesdelete",[INGR_ClientesController::class,"ClientesDelete"]);
                    Route::post("verify_exist_cliente_one",[INGR_ClientesController::class,"verifyClienteExist"]);
                    Route::post("verify_exist_cliente_two",[INGR_ClientesController::class,"verifyClienteExist"]);
                    Route::post("cliente_solicitud_registro",[INGR_ClientesController::class,"clienteSolicitudRegistro"]);
                    Route::post("registrar_cliente",[INGR_ClientesController::class,"registrarCliente"]);
                    Route::post("verclientes",[INGR_ClientesController::class,"verCliente"]);
        
            //ventas
                //pedidos
                //ventas
                    Route::post("catalogoventas",[INGR_VentasController::class,"catalogoVentas"]);
                    Route::post("catalogoventas",[INGR_VentasController::class,"catalogoVentas"]);
                    Route::post("newFolioVenta",[INGR_VentasController::class,"newFolioVenta"]);
                    Route::post("cargaArticulosVenta",[INGR_VentasController::class,"cargaArticulosVenta"]);
                    Route::post("descargarttosell",[INGR_VentasController::class,"detalleVentaArticulo"]);
                    Route::post("descargarttosellpr",[INGR_VentasController::class,"detalleVentaArticuloPr"]);
                    Route::post("registraventa",[INGR_VentasController::class,"registraVentaArticulo"]);
            
            //facturacion 
                Route::post("solicitudes_facturacion",[INGR_FacturacionController::class,"solicitudesCFDI"]);
                Route::post("detalle_solicitud_facturacion",[INGR_FacturacionController::class,"detalleSolicitudCFDI"]);
                Route::post("emision_factura",[INGR_FacturacionController::class,"emisionCFDI"]);
            //reportes
            //xml
            
        //gerencia
            //catalogo de reportes

        //juridico
        
        //tecnologías de la información
            //pagina principal 
                Route::get("landingSoluciones",[MAIN_LandingController::class,"listaServicios"]);
                Route::get("verPublicacionesMin",[TICS_PublicacionesController::class,"listaPublicacionesMin"]);
                Route::get("verPublicacionesMax",[TICS_PublicacionesController::class,"listaPublicacionesMax"]);
                Route::post("verPublicacionDetalle",[TICS_PublicacionesController::class,"verPublicacionDetalle"]);
                Route::get("ver_visitas",[TICS_VisitasController::class,"totalVisitas"]);
                Route::get("listadescargables",[MAIN_DescargablesController::class,"listaDescargables"]);
                Route::post("decoumg",[MAIN_ImagesController::class,"convertidor"]);
                Route::post("save_codigopass_ssic",[MAIN_UsuarioController::class,"guardarCodigoPass"]);
                Route::post("verif_codigopass_ssic",[MAIN_UsuarioController::class,"verificarCodigoPass"]);
                Route::post("reset_passwpord_ssic",[MAIN_UsuarioController::class,"resetPassFunction"]);
                
            //plataformas digitales
                //Route::get("catalogomonelect",[FNZS_MonedElectController::class,"monederosElectronicos"]);
                Route::get("catalogomonelect",[TICS_PlataformasDigitalesController::class,"listPlataformas"]);
                Route::get("catalogo_plataformas_digitales",[TICS_PlataformasDigitalesController::class,"listPlataformas"]);
                Route::post("registrar_plataforma_digital",[FNZS_MonedElectController::class,"registrarMonederoElctronico"]);
                Route::post("update_plataforma_digital",[FNZS_MonedElectController::class,"updateMonederoElectronico"]);
                Route::post("elimina_plataforma_digital",[FNZS_MonedElectController::class,"eliminarMonederoElctronico"]);
                Route::post("restaura_plataforma_digital",[FNZS_MonedElectController::class,"restaurarMonederoElctronico"]);
                Route::post("deletPer_plataforma_digital",[FNZS_MonedElectController::class,"deletPermMonederoElctronico"]);
        
            //dispositivos
                Route::get("listipodispositivo",[TICS_DispositivosController::class,"listaTipoDispositivo"]);
                Route::post("registradevice",[MAIN_UsuarioController::class,"registraDevice"]);
                Route::post("foliodispositivo",[TICS_DispositivosController::class,"folioDispositivo"]);
                Route::post("verlistadisovig",[TICS_DispositivosController::class,"listaDispositivosVig"]);
                Route::post("verlistadispdel",[TICS_DispositivosController::class,"listaDispositivosDel"]);
                Route::post("detalledispositivo",[TICS_DispositivosController::class,"detalleDispositivo"]);
                Route::post("actualizadispositivo",[TICS_DispositivosController::class,"actualizaDispositivo"]);
                Route::post("actualizacajadispositivo",[TICS_DispositivosController::class,"actualizaCajaDispositivo"]);
                Route::post("deletedispositivo",[TICS_DispositivosController::class,"deleteDispositivo"]);
                Route::post("restauradispositivo",[TICS_DispositivosController::class,"restaurarDispositivo"]);
                Route::post("deletepermdispositivo",[TICS_DispositivosController::class,"deletePermanenteDispositivo"]);
                Route::post("registradispositivo",[TICS_DispositivosController::class,"registrarDispositivo"]);
                //dispositivo checador
                    Route::post("checador_personal",[VHUM_PersonalController::class,"asistenciaPersonalEntrada"]);
            //empresas
                Route::post("solicitudes_reg_vig",[TICS_SoliRegistroController::class,"solicitudRegistroVigentes"]);
            //usuarios
                Route::post("catalogo_usuarios",[MAIN_UsuarioController::class,"catalogo_usuarios_SOS"]);
                Route::post("lista_areas_sos",[MAIN_UsuarioController::class,"listaAreasSOS"]);
                Route::post("registrar_usuario_nuevo",[MAIN_UsuarioController::class,"registraUsuarioNuevo"]);
                Route::post("catalogo_empleados_empresas",[VHUM_PersonalController::class,"listaPersonalSOS"]);
                Route::post("catalogo_empleados_clientes",[VHUM_PersonalController::class,"catalogo_empleados_clientes"]);
                Route::post("actualizaareapersonal",[VHUM_PersonalController::class,"actualizaAreaPersonalSOS"]);
                Route::post("generapasscodeuserpersonal",[MAIN_UsuarioController::class,"generaPassCodeUserPersonalSOS"]);
                //accciones y areas de acceso 
                    Route::post("user_solicitar_permiso_jerarquia",[MAIN_UsuarioController::class,"userSolicitarPermisoJerarquia"]);
                    Route::post("user_solicitar_permiso_crear",[MAIN_UsuarioController::class,"userSolicitarPermisoCrear"]);
                    Route::post("user_solicitar_permiso_editar",[MAIN_UsuarioController::class,"userSolicitarPermisoEditar"]);
                    Route::post("user_solicitar_permiso_consulta",[MAIN_UsuarioController::class,"userSolicitarPermisoConsultar"]);
                    Route::post("user_solicitar_permiso_eliminar",[MAIN_UsuarioController::class,"userSolicitarPermisoEliminar"]);
                    Route::post("user_solicitar_permiso_ver_docs",[MAIN_UsuarioController::class,"userSolicitarPermisoVerDocs"]);
                    //modulos
                        Route::post("user_acceso_modulo_ssic",[MAIN_UsuarioController::class,"userAccesoModuloSsic"]);
                        Route::post("user_acceso_modulo_descarga_xml",[MAIN_UsuarioController::class,"userAccesoModuloDescargaXml"]);
                        Route::post("user_acceso_modulo_logistica",[MAIN_UsuarioController::class,"userAccesoModuloLogistica"]);
                        Route::post("user_acceso_modulo_compras",[MAIN_UsuarioController::class,"userAccesoModuloCompras"]);
                        Route::post("user_acceso_modulo_proyectos",[MAIN_UsuarioController::class,"userAccesoModuloProyectos"]);
                        Route::post("user_acceso_modulo_terceros",[MAIN_UsuarioController::class,"userAccesoModuloTerceros"]);
                        Route::post("user_acceso_modulo_terceros_associates",[MAIN_UsuarioController::class,"userAccesoModuloTercerosAssociates"]);
                        Route::post("user_acceso_modulo_terceros_clientes",[MAIN_UsuarioController::class,"userAccesoModuloTercerosClientes"]);
                        Route::post("user_acceso_modulo_terceros_proveedores",[MAIN_UsuarioController::class,"userAccesoModuloTercerosProveedores"]);
                        Route::post("user_acceso_modulo_terceros_empleados",[MAIN_UsuarioController::class,"userAccesoModuloTercerosEmpleados"]);
                    //ingresos
                        Route::post("user_permisos_ingresos_acceso",[MAIN_UsuarioController::class,"userPermisosIngresosAcceso"]);
                        Route::post("user_permisos_ingresos_jerarquia",[MAIN_UsuarioController::class,"userPermisosIngresosJerarquia"]);
                        Route::post("user_permisos_ingresos_crear",[MAIN_UsuarioController::class,"userPermisosIngresosCrear"]);
                        Route::post("user_permisos_ingresos_editar",[MAIN_UsuarioController::class,"userPermisosIngresosEditar"]);
                        Route::post("user_permisos_ingresos_consultar",[MAIN_UsuarioController::class,"userPermisosIngresosConsultar"]);
                        Route::post("user_permisos_ingresos_eliminar",[MAIN_UsuarioController::class,"userPermisosIngresosEliminar"]);
                        Route::post("user_permisos_ingresos_ver_docs",[MAIN_UsuarioController::class,"userPermisosIngresosVerDocs"]);
                        //Catalogos
                        Route::post("user_permisos_ingresos_catalogos_modulo",[MAIN_UsuarioController::class,"userPermisosIngresosCatalogosModulo"]);
                            Route::post("user_permisos_ingresos_mercancias",[MAIN_UsuarioController::class,"userPermisosIngresosMercancias"]);
                            Route::post("user_permisos_ingresos_servicios",[MAIN_UsuarioController::class,"userPermisosIngresosServicios"]);
                            Route::post("user_permisos_ingresos_lista_precios",[MAIN_UsuarioController::class,"userPermisosIngresosListaPrecios"]);
                            Route::post("user_permisos_ingresos_descuentos",[MAIN_UsuarioController::class,"userPermisosIngresosDescuentos"]);
                            Route::post("user_permisos_ingresos_promociones",[MAIN_UsuarioController::class,"userPermisosIngresosPromociones"]);
                            Route::post("user_permisos_ingresos_impuestos",[MAIN_UsuarioController::class,"userPermisosIngresosImpuestos"]);
                            Route::post("user_permisos_ingresos_clientes",[MAIN_UsuarioController::class,"userPermisosIngresosClientes"]);
                        Route::post("user_permisos_ingresos_ventas_modulo",[MAIN_UsuarioController::class,"userPermisosIngresosVentasModulo"]);
                            Route::post("user_permisos_ingresos_pedidos",[MAIN_UsuarioController::class,"userPermisosIngresosPedidos"]);
                            Route::post("user_permisos_ingresos_ventas",[MAIN_UsuarioController::class,"userPermisosIngresosVentas"]);
                            Route::post("user_permisos_ingresos_seguimiento_ventas",[MAIN_UsuarioController::class,"userPermisosIngresosSeguimientoVentas"]);
                            Route::post("user_permisos_ingresos_devoluciones",[MAIN_UsuarioController::class,"userPermisosIngresosDevoluciones"]);
                            Route::post("user_permisos_ingresos_facturacion",[MAIN_UsuarioController::class,"userPermisosIngresosFacturacion"]);
                        Route::post("user_permisos_ingresos_reportes",[MAIN_UsuarioController::class,"userPermisosIngresosReportes"]);
                    //egresos
                        Route::post("user_permisos_egresos_acceso",[MAIN_UsuarioController::class,"userPermisosEgresosAcceso"]);
                        Route::post("user_permisos_egresos_jerarquia",[MAIN_UsuarioController::class,"userPermisosEgresosJerarquia"]);
                        Route::post("user_permisos_egresos_crear",[MAIN_UsuarioController::class,"userPermisosEgresosCrear"]);
                        Route::post("user_permisos_egresos_editar",[MAIN_UsuarioController::class,"userPermisosEgresosEditar"]);
                        Route::post("user_permisos_egresos_consultar",[MAIN_UsuarioController::class,"userPermisosEgresosConsultar"]);
                        Route::post("user_permisos_egresos_eliminar",[MAIN_UsuarioController::class,"userPermisosEgresosEliminar"]);
                        Route::post("user_permisos_egresos_ver_docs",[MAIN_UsuarioController::class,"userPermisosEgresosVerDocs"]);
                        Route::post("user_permisos_egresos_catalogos_modulo",[MAIN_UsuarioController::class,"userPermisosEgresosCatalogosModulo"]);
                            Route::post("user_permisos_egresos_productos",[MAIN_UsuarioController::class,"userPermisosEgresosProductos"]);
                            Route::post("user_permisos_egresos_servicios",[MAIN_UsuarioController::class,"userPermisosEgresosServicios"]);
                            Route::post("user_permisos_egresos_activos_fijos",[MAIN_UsuarioController::class,"userPermisosEgresosActivosFijos"]);
                            Route::post("user_permisos_egresos_activos_intang",[MAIN_UsuarioController::class,"userPermisosEgresosActivosIntang"]);
                            Route::post("user_permisos_egresos_proveedores",[MAIN_UsuarioController::class,"userPermisosEgresosProveedores"]);
                            Route::post("user_permisos_egresos_establecimientos",[MAIN_UsuarioController::class,"userPermisosEgresosEstablecimientos"]);
                        //Compras
                        Route::post("user_permisos_egresos_compras_modulo",[MAIN_UsuarioController::class,"userPermisosEgresosComprasModulo"]);
                            Route::post("user_permisos_egresos_requisiciones",[MAIN_UsuarioController::class,"userPermisosEgresosRequisiciones"]);
                            Route::post("user_permisos_egresos_cotizaciones",[MAIN_UsuarioController::class,"userPermisosEgresosCotizaciones"]);
                            Route::post("user_permisos_egresos_compra_directa",[MAIN_UsuarioController::class,"userPermisosEgresosCompraDirecta"]);
                            Route::post("user_permisos_egresos_compra_seguimiento",[MAIN_UsuarioController::class,"userPermisosEgresosCompraSeguimiento"]);
                    //finanzas
                        Route::post("user_permisos_finanzas_acceso",[MAIN_UsuarioController::class,"userPermisosFinanzasAcceso"]);
                        Route::post("user_permisos_finanzas_jerarquia",[MAIN_UsuarioController::class,"userPermisosFinanzasJerarquia"]);
                        Route::post("user_permisos_finanzas_crear",[MAIN_UsuarioController::class,"userPermisosFinanzasCrear"]);
                        Route::post("user_permisos_finanzas_editar",[MAIN_UsuarioController::class,"userPermisosFinanzasEditar"]);
                        Route::post("user_permisos_finanzas_consultar",[MAIN_UsuarioController::class,"userPermisosFinanzasConsultar"]);
                        Route::post("user_permisos_finanzas_eliminar",[MAIN_UsuarioController::class,"userPermisosFinanzasEliminar"]);
                        Route::post("user_permisos_finanzas_ver_docs",[MAIN_UsuarioController::class,"userPermisosFinanzasVerDocs"]);
                        Route::post("user_permisos_finanzas_catalogos_modulo",[MAIN_UsuarioController::class,"userPermisosFinanzasCatalogosModulo"]);
                            Route::post("user_permisos_finanzas_cuentas_bancarias",[MAIN_UsuarioController::class,"userPermisosFinanzasCuentasBancarias"]);
                            Route::post("user_permisos_finanzas_caja",[MAIN_UsuarioController::class,"userPermisosFinanzasCaja"]);
                            Route::post("user_permisos_finanzas_monederos_electronicos",[MAIN_UsuarioController::class,"userPermisosFinanzasMonederosElectronicos"]);
                            Route::post("user_permisos_finanzas_dispositivos_electronicos",[MAIN_UsuarioController::class,"userPermisosFinanzasDispositivosElectronicos"]);
                        Route::post("user_permisos_finanzas_control_mov_bancarios",[MAIN_UsuarioController::class,"userPermisosFinanzasControlMovBancarios"]);
                        Route::post("user_permisos_finanzas_control_mov_efectivo",[MAIN_UsuarioController::class,"userPermisosFinanzasControlMovEfectivo"]);
                        Route::post("user_permisos_finanzas_ordenes_pago",[MAIN_UsuarioController::class,"userPermisosFinanzasOrdenesPago"]);
                        Route::post("user_permisos_finanzas_ajustes_ycpr",[MAIN_UsuarioController::class,"userPermisosFinanzasAjustesyCPR"]);
                        Route::post("user_permisos_finanzas_info_bancaria",[MAIN_UsuarioController::class,"userPermisosFinanzasInfoBancaria"]);
                    //valor_humano
                        Route::post("user_permisos_valor_humano_acceso",[MAIN_UsuarioController::class,"userPermisosValorHumanoAcceso"]);
                        Route::post("user_permisos_valor_humano_jerarquia",[MAIN_UsuarioController::class,"userPermisosValorHumanoJerarquia"]);
                        Route::post("user_permisos_valor_humano_crear",[MAIN_UsuarioController::class,"userPermisosValorHumanoCrear"]);
                        Route::post("user_permisos_valor_humano_editar",[MAIN_UsuarioController::class,"userPermisosValorHumanoEditar"]);
                        Route::post("user_permisos_valor_humano_consultar",[MAIN_UsuarioController::class,"userPermisosValorHumanoConsultar"]);
                        Route::post("user_permisos_valor_humano_eliminar",[MAIN_UsuarioController::class,"userPermisosValorHumanoEliminar"]);
                        Route::post("user_permisos_valor_humano_ver_docs",[MAIN_UsuarioController::class,"userPermisosValorHumanoVerDocs"]);
                        Route::post("user_permisos_valor_humano_catalogos",[MAIN_UsuarioController::class,"userPermisosValorHumanoCatalogos"]);
                        Route::post("user_permisos_valor_humano_reembolsos",[MAIN_UsuarioController::class,"userPermisosValorHumanoReembolsos"]);
                        Route::post("user_permisos_valor_humano_reportes",[MAIN_UsuarioController::class,"userPermisosValorHumanoReportes"]);
                    //contabilidad
                        Route::post("user_permisos_contabilidad_acceso",[MAIN_UsuarioController::class,"userPermisosContabilidadAcceso"]);
                        Route::post("user_permisos_contabilidad_jerarquia",[MAIN_UsuarioController::class,"userPermisosContabilidadJerarquia"]);
                        Route::post("user_permisos_contabilidad_crear",[MAIN_UsuarioController::class,"userPermisosContabilidadCrear"]);
                        Route::post("user_permisos_contabilidad_editar",[MAIN_UsuarioController::class,"userPermisosContabilidadEditar"]);
                        Route::post("user_permisos_contabilidad_consultar",[MAIN_UsuarioController::class,"userPermisosContabilidadConsultar"]);
                        Route::post("user_permisos_contabilidad_eliminar",[MAIN_UsuarioController::class,"userPermisosContabilidadEliminar"]);
                        Route::post("user_permisos_contabilidad_ver_docs",[MAIN_UsuarioController::class,"userPermisosContabilidadVerDocs"]);
                        Route::post("user_permisos_contabilidad_catalogos",[MAIN_UsuarioController::class,"userPermisosContabilidadCatalogos"]);
                        Route::post("user_permisos_contabilidad_politicas",[MAIN_UsuarioController::class,"userPermisosContabilidadPoliticas"]);
                        Route::post("user_permisos_contabilidad_catalogo_cuentas",[MAIN_UsuarioController::class,"userPermisosContabilidadCatalogoCuentas"]);
                        Route::post("user_permisos_contabilidad_estados_financieros",[MAIN_UsuarioController::class,"userPermisosContabilidadEstadosFinancieros"]);
                        Route::post("user_permisos_contabilidad_reportes",[MAIN_UsuarioController::class,"userPermisosContabilidadReportes"]);
                    //tec_info
                        Route::post("user_permisos_teci_info_acceso",[MAIN_UsuarioController::class,"userPermisosTeciInfoAcceso"]);
                        Route::post("user_permisos_teci_info_jerarquia",[MAIN_UsuarioController::class,"userPermisosTeciInfoJerarquia"]);
                        Route::post("user_permisos_teci_info_crear",[MAIN_UsuarioController::class,"userPermisosTeciInfoCrear"]);
                        Route::post("user_permisos_teci_info_editar",[MAIN_UsuarioController::class,"userPermisosTeciInfoEditar"]);
                        Route::post("user_permisos_teci_info_consultar",[MAIN_UsuarioController::class,"userPermisosTeciInfoConsultar"]);
                        Route::post("user_permisos_teci_info_eliminar",[MAIN_UsuarioController::class,"userPermisosTeciInfoEliminar"]);
                        Route::post("user_permisos_teci_info_ver_docs",[MAIN_UsuarioController::class,"userPermisosTeciInfoVerDocs"]);
                        Route::post("user_permisos_teci_info_apps_complementarias",[MAIN_UsuarioController::class,"userPermisosTeciInfoAppsComplementarias"]);
                        Route::post("user_permisos_teci_info_soporte",[MAIN_UsuarioController::class,"userPermisosTeciInfoSoporte"]);
                        Route::post("user_permisos_teci_info_comunicacion",[MAIN_UsuarioController::class,"userPermisosTeciInfoComunicacion"]);
                        Route::post("user_permisos_teci_info_publicaciones",[MAIN_UsuarioController::class,"userPermisosTeciInfoPublicaciones"]);
                        
            //acceso
                Route::post("all_user_config_ssic",[MAIN_RolesController::class,"allUserConfigSSIC"]);
                //accesos por menu
                    Route::post("dtalgnpacc",[MAIN_RolesController::class,"permisoAcceso"]);
                    Route::post("permisos_acceso_menu",[MAIN_RolesController::class,"newPermisoAcceso"]);
                //accesos por link
                    //ingresos
                        Route::post("permisos_acceso_ingresos",[MAIN_RolesController::class,"permisosIngresos"]);
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
                        Route::post("permisos_egresos_acceso_reem",[MAIN_RolesController::class,"permisosEGRESOSReembolsos"]);
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
                        Route::post("permisos_finanzas_acceso_ordenesdepago",[MAIN_RolesController::class,"permisosFINANZASOrdenPago"]);
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
                        Route::post("permisos_vhum_acceso_reem",[MAIN_RolesController::class,"permisosVHUMReembolsos"]);
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
                
            //notificaciones
                Route::post("total_notificaciones",[MAIN_NotificacionesController::class,"totalNotificaciones"]);
                Route::post("lista_min_notificaciones",[MAIN_NotificacionesController::class,"listaNotificacionesFirst"]);
                Route::post("lista_notificaciones_all",[MAIN_NotificacionesController::class,"listaNotificacionesAll"]);
                Route::post("lista_notificaciones_gestion_proyectos",[MAIN_NotificacionesController::class,"listaNotificacionesGestionProyectos"]);
                Route::post("lista_notificaciones_gestion_p",[MAIN_NotificacionesController::class,"listaNotificacionesGestionProyectoZ"]);
                //Route::post("lista_min_notificaciones",[MAIN_NotificacionesController::class,"listaMinNotificaciones"]);
                Route::post("ultima_notificacion",[MAIN_NotificacionesController::class,"ultimaNotificacion"]);
                Route::post("detalle_notificacion",[MAIN_NotificacionesController::class,"detalleNotificacionInside"]);
                Route::post("detalle_notificacion_outside_gp",[MAIN_NotificacionesController::class,"detalleNotificacionOutsideGP"]);
                Route::post("delete_notificacion",[MAIN_NotificacionesController::class,"deleteNotificacion"]);
                Route::post("listanotificaciones",[MAIN_EmpresasController::class,"listaempresasSSIC"]);
            //chats
                Route::post("listachats",[MAIN_ChatController::class,"listaHistoryChat"]); 
            //empresas
                Route::get("allcompanies",[MAIN_EmpresasController::class,"listaEmpresasAll"]);
                Route::post("empresacompleteregistro",[MAIN_EmpresasController::class,"empresaCompleteRegistro"]);
                Route::post("catalogo_empresas_vinculadas",[MAIN_EmpresasController::class,"catalogoEmpresasVinculadas"]);
                Route::post("select_empresa_vinculada",[MAIN_EmpresasController::class,"empresaVinculada"]);
                Route::post("verify_exist_empresa_one",[MAIN_EmpresasController::class,"buscaRfcAllEmpresaOut"]);
                Route::post("empresa_registrar",[MAIN_EmpresasController::class,"registraEmpresaMin"]);
            //fecha y hora
                Route::post("getFechaInput",[MAIN_MenuController::class,"getFechaInput"]);
                Route::post("horarioUso",[MAIN_MenuController::class,"getRelojes"]);
            //lenguaje
                Route::post("update_language",[MAIN_SettingsController::class,"updateLanguage"]);
            //monedas
                Route::get("listaMonedas",[MAIN_MonedaController::class,"catalogoMonedas"]);
                Route::post("monedaempresa",[MAIN_MonedaController::class,"monedaEmpresa"]);
            //unidades de medida
                Route::get("clasificacionMedidaSat",[MAIN_UMedidaController::class,"clasificacionMedidaSat"]);
                Route::get("listamedidas",[MAIN_UMedidaController::class,"listaUnidadesMedida"]);
                Route::get("verpdf",[MAIN_UMedidaController::class,"pdfHtml"]);
                
                Route::post("medidasat",[MAIN_UMedidaController::class,"medidasSat"]);
                Route::get("medidasatservicios",[MAIN_UMedidaController::class,"medidasSatServicios"]);
                Route::post("postmedidasatservicios",[MAIN_UMedidaController::class,"postMedidasSatServicios"]);
            //configuracion de cfdi
                Route::get("getListaUso",[MAIN_CfdiController::class,"getListaUso"]);
                Route::get("getMotivosCancelacionCfdi",[MAIN_CfdiController::class,"getMotivosCancelacion"]);
                Route::get("getformapago",[MAIN_FormaPagoController::class,"listaFormaPago"]);
                Route::get("getmetodopago",[MAIN_MetodoPagoController::class,"listaMetodoPago"]);
            //paises
                Route::get("listaPaises",[MAIN_PaisController::class,"getListaPais"]);
            //sat
                Route::get("catalogo_prodservsat",[MAIN_CatSatController::class,"listaCatalogo"]);
                Route::post("catalogo_prodservsatClave",[MAIN_CatSatController::class,"listaCatalogoPClave"]);
                Route::post("catalogo_prodservsatDesc",[MAIN_CatSatController::class,"listaCatalogoPdesc"]);
                Route::post("catalogo_prodservsatInput",[MAIN_CatSatController::class,"listaCatalogoPInput"]);
            //clasificacion de productos y servicios
                Route::get("getClasificacionProductos",[MAIN_ClasificacionController::class,"getClasificacionProductos"]);
                Route::post("getGeneroProductos",[MAIN_ClasificacionController::class,"getGeneroProductos"]);
                Route::post("getClasificacionFull",[MAIN_ClasificacionController::class,"setClasificacionFull"]);
                Route::get("getClasificacionServicios",[MAIN_ClasificacionController::class,"getClasificacionServicios"]);
                Route::post("clasificacompletserv",[MAIN_ClasificacionController::class,"fullClasifServicios"]);
            //direcciones
                Route::post("dipomexcpostales",[MAIN_DireccionesController::class,"listacodDipomex"]);
                Route::post("location_iq_dir",[MAIN_DireccionesController::class,"listaLocationIQ"]);
                Route::get("dipomexcpostales2",[MAIN_DireccionesController::class,"listacodDipome2"]);
                Route::get("getcpostales",[MAIN_DireccionesController::class,"listacodPostal"]);
                Route::post("postcpostales",[MAIN_DireccionesController::class,"listacodPostalLike"]);
                Route::post("getlistacolonias",[MAIN_DireccionesController::class,"listacolonias"]);
                Route::post("getselectentfed",[MAIN_DireccionesController::class,"selectentfed"]);
            //regimen fiscal
                Route::get("getallregimenfiscal",[MAIN_RegimenFiscalController::class,"listAllRegimenFiscal"]);
                Route::get("getpfregimenfiscal",[MAIN_RegimenFiscalController::class,"listPFRegimenFiscal"]);
                Route::get("getpmregimenfiscal",[MAIN_RegimenFiscalController::class,"listPMRegimenFiscal"]);
            //bancos
                Route::get("listabancos",[TICS_BancosController::class,"bancos"]);
                    
        //valor humano
             //catalogo_empleados
                Route::post("catalogo_empleados_sos",[VHUM_PersonalController::class,"catalogo_empleados_SOS"]);
                Route::post("catalogo_empleados_empresa",[VHUM_PersonalController::class,"catalogo_empleados_SOS"]);
                Route::post("actualizapaternopersonal",[VHUM_PersonalController::class,"actualizaPaternoPersonalSOS"]);
                Route::post("actualizamaternopersonal",[VHUM_PersonalController::class,"actualizaMaternoPersonalSOS"]);
                Route::post("actualizanombrespersonal",[VHUM_PersonalController::class,"actualizaNombresPersonalSOS"]);
                Route::post("actualizaareapersonal",[VHUM_PersonalController::class,"actualizaAreaPersonalSOS"]);
                Route::post("actualizaemailpersonal",[VHUM_PersonalController::class,"actualizaMailPersonalSOS"]);
                Route::post("registratelefonopersonal",[VHUM_PersonalController::class,"registraTelefonoPersonalSOS"]);
                Route::post("actualizatelefonopersonal",[VHUM_PersonalController::class,"actualizaTelefonoPersonalSOS"]);
                Route::post("listapersgeneral",[VHUM_PersonalController::class,"listaPersonalGneral"]);
                Route::post("listapersgeneralarea",[VHUM_PersonalController::class,"listaPersonalArea"]);
                Route::post("listapersonal",[VHUM_PersonalController::class,"listaResponsablesAlmacen"]);
            //centros de trabajo
            //viaticos y otros conceptos
            //percepciones y deducciones
            //reembolsos
                Route::post("vh_reembolso_lista",[VHUM_ReembolsosController::class,"reembolso_lista"]); 
                Route::post("vh_reembolso_detalle",[VHUM_ReembolsosController::class,"reembolso_detalle"]);
                Route::post("vh_reembolso_auth",[VHUM_ReembolsosController::class,"vh_reembolso_auth"]);
            //comisiones
                Route::post("comision_registro_aviso_vhum",[MAIN_ComisionesController::class,"comisionRegistroAvisoVhum"]);
            //apps externas
                Route::post("checador_entrada_personal",[VHUM_PersonalController::class,"asistenciaPersonalEntrada"]);
                Route::post("checador_salida_personal",[VHUM_PersonalController::class,"asistenciaPersonalSalida"]);
    
        //descarga de xmls
            Route::post("guardarfacturasxml",[MAIN_XmlValidateController::class,"guardarFacturasXml"]);
            Route::post("consultafacturasxml",[MAIN_XmlValidateController::class,"consultaFacturasXml"]);       
            
    //terceros
        //associates 
            Route::post("modulo_mostrador_impuestos_catalogo",[TERC_AssociatesCatalogosController::class,"catalogoImpuestosVig"]);
            Route::post("modulo_mostrador_impuestos_actualizar",[TERC_AssociatesCatalogosController::class,"impuestoActualizar"]);
            Route::post("modulo_mostrador_impuestos_papelera_save",[TERC_AssociatesCatalogosController::class,"impuestoPapeleraSave"]);
            Route::post("modulo_mostrador_impuestos_papelera_catalogo",[TERC_AssociatesCatalogosController::class,"catalogoImpuestosEliminados"]);
            Route::post("modulo_mostrador_impuestos_restaurar",[TERC_AssociatesCatalogosController::class,"impuestoPapeleraRestaurar"]);
            Route::post("modulo_mostrador_impuestos_eliminar",[TERC_AssociatesCatalogosController::class,"impuestoDeletePerm"]);
            Route::post("modulo_mostrador_impuestos_registrar",[TERC_AssociatesCatalogosController::class,"impuestoRegistro"]);

            Route::post("modulo_mostrador_productos_catalogo",[TERC_AssociatesCatalogosController::class,"productoAssocCatalogo"]); 
            Route::post("modulo_mostrador_prod_solicita_valid",[TERC_AssociatesCatalogosController::class,"requestValidacionProd"]);
            Route::post("modulo_mostrador_productos_actualizar",[TERC_AssociatesCatalogosController::class,"productoActualizar"]);
            Route::post("modulo_mostrador_productos_papelera_save",[TERC_AssociatesCatalogosController::class,"productoPapeleraSave"]);
            Route::post("modulo_mostrador_productos_papelera_catalogo",[TERC_AssociatesCatalogosController::class,"productoAssocCatalogoEliminados"]);
            Route::post("modulo_mostrador_productos_restaurar",[TERC_AssociatesCatalogosController::class,"productoPapeleraRestaurar"]);
            Route::post("modulo_mostrador_productos_eliminar",[TERC_AssociatesCatalogosController::class,"productoDeletePerm"]);
            Route::post("modulo_mostrador_createarticulo",[TERC_AssociatesCatalogosController::class,"registroProductoAssoc"]);

            Route::post("modulo_mostrador_servicios_catalogo",[TERC_AssociatesCatalogosController::class,"servicioAssocCatalogo"]); 
            Route::post("modulo_mostrador_serv_solicita_valid",[TERC_AssociatesCatalogosController::class,"requestValidacionServ"]);
            Route::post("modulo_mostrador_servicios_actualizar",[TERC_AssociatesCatalogosController::class,"servicioActualizar"]);
            Route::post("modulo_mostrador_servicios_papelera_save",[TERC_AssociatesCatalogosController::class,"servicioPapeleraSave"]);
            Route::post("modulo_mostrador_servicios_papelera_catalogo",[TERC_AssociatesCatalogosController::class,"servicioAssocCatalogoEliminados"]);
            Route::post("modulo_mostrador_servicios_restaurar",[TERC_AssociatesCatalogosController::class,"servicioPapeleraRestaurar"]);
            Route::post("modulo_mostrador_servicios_eliminar",[TERC_AssociatesCatalogosController::class,"servicioDeletePerm"]);
            Route::post("modulo_mostrador_createservicio",[TERC_AssociatesCatalogosController::class,"registroServicioAssoc"]);

            Route::post("modulo_mostrador_pv_lista",[TERC_AssociatesCatalogosController::class,"pventaAssocCatalogo"]); 
            Route::post("modulo_mostrador_pv_solicita_valid",[TERC_AssociatesCatalogosController::class,"requestValidacionPventa"]);
            Route::post("modulo_mostrador_pv_actualizar",[TERC_AssociatesCatalogosController::class,"pventaActualizar"]);
            Route::post("modulo_mostrador_pv_papelera_save",[TERC_AssociatesCatalogosController::class,"pventaPapeleraSave"]);
            Route::post("modulo_mostrador_pv_papelera_catalogo",[TERC_AssociatesCatalogosController::class,"pventaAssocCatalogoEliminados"]);
            Route::post("modulo_mostrador_pv_restaurar",[TERC_AssociatesCatalogosController::class,"pventaPapeleraRestaurar"]);
            Route::post("modulo_mostrador_pv_eliminar",[TERC_AssociatesCatalogosController::class,"pventaDeletePerm"]);
            Route::post("modulo_mostrador_pv_registrar",[TERC_AssociatesCatalogosController::class,"registroPventaAssoc"]);

            Route::post("modulo_mostrador_articulosVenta",[TERC_AssociatesCatalogosController::class,"cargaArticulosVenta"]);

            Route::post("list_companies_associates",[MAIN_EmpresasController::class,"listaempresasAssociates"]);
            Route::post("select_company_associates",[MAIN_EmpresasController::class,"selectEmpresasAssociates"]);
            Route::post("list_solicitud_cfdi",[TERC_AssociatesController::class,"listaSolicitudCFDI"]);
            Route::post("detalle_solicitud_cfdi",[TERC_AssociatesController::class,"detalleSolicitudCFDI"]);
            Route::post("cancelar_solicitud_cfdi",[TERC_AssociatesController::class,"cancelarCFDI"]);
            Route::post("registro_solicitud_cfdi",[TERC_AssociatesController::class,"registroSolicitudCFDI"]);
            Route::post("r_solicitud_cfdi",[TERC_AssociatesController::class,"registroSoliCFDI"]);    
        
        //customers    
        
        //suppliers
        
        //employees
            //comisiones
                Route::post("comision_listas_no_concluidas",[TERC_EmployeesController::class,"comisionListasNoConcluidas"]);
                Route::post("comision_deshabilitar",[TERC_EmployeesController::class,"comisionDeshabilitar"]);
                Route::post("comision_listas_concluidas",[TERC_EmployeesController::class,"comisionListasConcluidas"]);
                Route::post("comision_deshabilitadas",[TERC_EmployeesController::class,"comisionDeshabilitadas"]);
                Route::post("comision_rehabilitar",[TERC_EmployeesController::class,"comisionRehabilitar"]);
                Route::post("comision_detalle_update",[TERC_EmployeesController::class,"comisionDetalleUpdate"]);
                Route::post("comision_detalle_get_data",[TERC_EmployeesController::class,"comisionDetalleGetData"]);
                Route::post("actualizar_comision",[TERC_EmployeesController::class,"comisionUpdate"]); 
                Route::post("comision_reem_listas",[TERC_EmployeesController::class,"comisionReemListas"]);
                Route::post("registrar_comision",[TERC_EmployeesController::class,"comision_registro"]);
            //reembolsos
                Route::post("reembolso_lista",[TERC_EmployeesController::class,"reembolso_lista_true"]); 
                Route::post("reembolso_deshabilitar",[TERC_EmployeesController::class,"reembolso_deshabilitar"]); 
                Route::post("reembolso_lista_deleted",[TERC_EmployeesController::class,"reembolso_lista_false"]);
                Route::post("reembolso_rehabilitar",[TERC_EmployeesController::class,"reembolso_rehabilitar"]); 
                Route::post("reembolso_detalle",[TERC_EmployeesController::class,"reembolso_detalle"]);
                Route::post("reembolso_add_new",[TERC_EmployeesController::class,"reembolso_agregar"]);
                Route::post("reembolso_delete_docs",[TERC_EmployeesController::class,"reembolso_delete_docs"]);
                Route::post("reembolso_update",[TERC_EmployeesController::class,"reembolso_soli_update"]);
                Route::post("reembolso_load_docs",[TERC_EmployeesController::class,"reembolso_load_docs"]);
                Route::post("reembolso_registro",[TERC_EmployeesController::class,"reembolso_registro"]);
                
    //modulo de proyectos
        //catalogo de reportes
            //eventos
                Route::post("calendar_proyectos",[JURI_EventosController::class,"calendarCompleteProyectos"]);
                Route::post("calendar_por_proyecto",[JURI_EventosController::class,"calendarProyectos"]);
                Route::post("calendar_por_tarea",[JURI_EventosController::class,"calendarTareas"]);
                Route::post("calendar_all_por_proy_pers",[JURI_EventosController::class,"calendarProyectosPersonalAll"]);
                Route::post("calendar_por_proy_pers",[JURI_EventosController::class,"calendarProyectosPersonal"]);
                Route::post("calendar_por_tare_pers",[JURI_EventosController::class,"calendarTareasPersonal"]);
            //eventos
                Route::post("gantt_proyectos",[JURI_EventosController::class,"ganttCompleteProyectos"]);
                //Route::post("calendar_por_proyecto",[JURI_EventosController::class,"calendarProyectos"]);
                //Route::post("calendar_por_tarea",[JURI_EventosController::class,"calendarTareas"]);
                //Route::post("calendar_all_por_proy_pers",[JURI_EventosController::class,"calendarProyectosPersonalAll"]);
                //Route::post("calendar_por_proy_pers",[JURI_EventosController::class,"calendarProyectosPersonal"]);
                //Route::post("calendar_por_tare_pers",[JURI_EventosController::class,"calendarTareasPersonal"]);
        //tareas programadas
            Route::post("catalogo_plantillas",[ModuleProyectosController::class,"catalogoPlantillas"]);
            Route::post("registrar_plantilla",[ModuleProyectosController::class,"registrarPlantilla"]);
            Route::post("permisos_proyectos",[ModuleProyectosController::class,"permisosProyectos"]);
            Route::post("registrar_proyecto",[ModuleProyectosController::class,"registrarProyecto"]);
            Route::post("last_proyect_created",[ModuleProyectosController::class,"lastProyectCreated"]);
            Route::post("lista_proyectos_eliminados",[ModuleProyectosController::class,"listaProyectosDeleted"]);
            Route::post("restaurar_proyecto",[ModuleProyectosController::class,"restaurarProyecto"]);
            Route::post("recover_proyecto",[ModuleProyectosController::class,"recoverProyecto"]);
            Route::post("remover_proyecto",[ModuleProyectosController::class,"removerProyecto"]);
            Route::post("lista_proyectos",[ModuleProyectosController::class,"listaProyectos"]);
            Route::post("lista_proyectos_fecha_asc",[ModuleProyectosController::class,"listaProyectosAscFecha"]);
            Route::post("lista_proyectos_fecha_desc",[ModuleProyectosController::class,"listaProyectosDescFecha"]);
            Route::post("lista_proyectos_black_asc",[ModuleProyectosController::class,"listaProyectosAscBlack"]);
            Route::post("lista_proyectos_black_desc",[ModuleProyectosController::class,"listaProyectosDescBlack"]);
            Route::post("lista_proyectos_green_asc",[ModuleProyectosController::class,"listaProyectosAscGreen"]);
            Route::post("lista_proyectos_green_desc",[ModuleProyectosController::class,"listaProyectosDescGreen"]);
            Route::post("lista_proyectos_yellow_asc",[ModuleProyectosController::class,"listaProyectosAscYellow"]);
            Route::post("lista_proyectos_yellow_desc",[ModuleProyectosController::class,"listaProyectosDescYellow"]);
            Route::post("lista_proyectos_red_asc",[ModuleProyectosController::class,"listaProyectosAscRed"]);
            Route::post("lista_proyectos_red_desc",[ModuleProyectosController::class,"listaProyectosDescRed"]);
            Route::post("lista_proyectos_finish_asc",[ModuleProyectosController::class,"listaProyectosAscFinish"]);
            Route::post("lista_proyectos_finish_desc",[ModuleProyectosController::class,"listaProyectosDescFinish"]);
            Route::post("actualizar_proyecto",[ModuleProyectosController::class,"actualizarProyecto"]);
            Route::post("quita_lider_proyecto",[ModuleProyectosController::class,"quitaLiderProyecto"]);
            Route::post("agregar_proyecto_eqtrabajo",[ModuleProyectosController::class,"agregarEqTeamProyecto"]);
            Route::post("eliminar_proyecto_eqtrabajo",[ModuleProyectosController::class,"eliminarEqTeamProyecto"]);
            Route::post("proyecto_recalendarizar",[ModuleProyectosController::class,"recalendarizarProyecto"]);
            Route::post("eliminar_proyecto",[ModuleProyectosController::class,"eliminarProyecto"]);
            Route::post("nuevo_nombre_proyecto",[ModuleProyectosController::class,"nuevoNombreProyecto"]);  
            Route::post("detalle_proyecto",[ModuleProyectosController::class,"detalleProyecto"]);//detalle de proyecto
            //tareas
            Route::post("registrar_tarea",[ModuleProyectosController::class,"registrarTarea"]);
            Route::post("last_tarea_created",[ModuleProyectosController::class,"ultimaTareaCreada"]);
            Route::post("recover_tarea",[ModuleProyectosController::class,"recoverTarea"]);
            Route::post("revision_tarea_acceso",[ModuleProyectosController::class,"revisionTareaAcceso"]);
            Route::post("proyecto_dependiente_tar_agregar",[ModuleProyectosController::class,"tareaDependienteAgregar"]);
            Route::post("proyecto_dependiente_tar_remover",[ModuleProyectosController::class,"tareaDependienteRemover"]);
            Route::post("duplica_tarea",[ModuleProyectosController::class,"duplicaTarea"]);
            Route::post("actualiza_name_tarea",[ModuleProyectosController::class,"actualizaNameTarea"]);
            Route::post("actualiza_descrip_tarea",[ModuleProyectosController::class,"actualizaDescTarea"]);
            Route::post("actualiza_tarea",[ModuleProyectosController::class,"actualizaTarea"]);
            Route::post("recalendarizar_tarea",[ModuleProyectosController::class,"recalendarizarTarea"]);
            Route::post("agrega_responsable_tarea",[ModuleProyectosController::class,"agregarRespTarea"]);
            Route::post("elimina_responsable_tarea",[ModuleProyectosController::class,"eliminarRespTarea"]);
            Route::post("terminar_tarea",[ModuleProyectosController::class,"terminarTarea"]);
            Route::post("eliminar_tarea",[ModuleProyectosController::class,"eliminarTarea"]);
            Route::post("last_tarea_deleted",[ModuleProyectosController::class,"lastTareaDeleted"]);
            Route::post("restaurar_tarea",[ModuleProyectosController::class,"restaurarTarea"]);
            Route::post("remove_perm_tarea",[ModuleProyectosController::class,"removeTareaPerm"]);
            Route::post("terminar_perticipacion_tarea",[ModuleProyectosController::class,"terminarParticipacionTarea"]);
            //informes
            Route::post("registra_informe",[ModuleProyectosController::class,"registrarInformeTarea"]);
            Route::post("last_informe_created",[ModuleProyectosController::class,"lastInformeTareaCreated"]);
            Route::post("recover_informe",[ModuleProyectosController::class,"recoverInformeTarea"]);
            Route::post("detalle_informe",[ModuleProyectosController::class,"detalleInforme"]);
            Route::get("ver_en_browser/{json}/{archivo}",[ModuleProyectosController::class,"visorEvidencias"]);
            Route::get("descarga_browser/{json}",[ModuleProyectosController::class,"descargarEvidencias"]);
            Route::post("revisar_informe",[ModuleProyectosController::class,"revisarInformeTarea"]);
            Route::post("aprobar_informe",[ModuleProyectosController::class,"aprobarInformeTarea"]);
            Route::post("actualiza_informe",[ModuleProyectosController::class,"updateInformeTarea"]);
            Route::post("actualiza_observaciones_informe",[ModuleProyectosController::class,"updateObservacionesInforme"]);
            Route::post("carga_evidencias_informe",[ModuleProyectosController::class,"cargaEvidenciasInformeTarea"]);
            Route::post("proy_eliminar_evidencia",[ModuleProyectosController::class,"deleteEvidenciaInfProyecto"]);
            Route::post("proy_restaura_evidencia",[ModuleProyectosController::class,"restartEvidenciaInfProyecto"]);
            Route::post("proy_delete_evid_perman",[ModuleProyectosController::class,"deleteEvidInfProyectoPermanente"]);
            Route::post("elimina_informe",[ModuleProyectosController::class,"deleteInformeTarea"]);
            Route::post("restaurar_informe",[ModuleProyectosController::class,"restaurarInformeTarea"]);
            Route::post("elimina_perm_informe",[ModuleProyectosController::class,"deleteInformePerm"]);
    //chatGPT
    Route::post("chat_con_gpt",[MAIN_GPTController::class,"respuestaChatGPT"]);