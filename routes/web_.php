<?php

/*header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");*/

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ImagesController;

//descarga de xmls
  Route::post('guardarfacturasxml','App\Http\Controllers\xmlValidateController@guardarFacturasXml');
  Route::post('consultafacturasxml','App\Http\Controllers\xmlValidateController@consultaFacturasXml');

//pagina principal 
  Route::get('landingSoluciones','App\Http\Controllers\LandingController@listaServicios');
  Route::get('ver_indicadores','App\Http\Controllers\PublicacionesController@listaIndicadores'); 
  Route::get('verPublicaciones','App\Http\Controllers\PublicacionesController@listaPublicaciones');  
  Route::get('listadescargables','App\Http\Controllers\DescargablesController@listaDescargables');
  Route::post('verPublicacionDetalle','App\Http\Controllers\PublicacionesController@verPublicacionDetalle');
  Route::post('decoumg','App\Http\Controllers\ImagesController@convertidor');
  
  Route::post('login_ssic','App\Http\Controllers\UsuarioController@sesionSsic');
  Route::post('login_ssic_mobile','App\Http\Controllers\UsuarioController@sesionMobileSsic');
  Route::post('login_clientes','App\Http\Controllers\UsuarioController@sesionClientes');
  Route::post('login_proveedores','App\Http\Controllers\UsuarioController@sesionProveedores');
  Route::post('login_empleados','App\Http\Controllers\UsuarioController@sesionEmpleados');
  Route::post('loginClientes','App\Http\Controllers\UsuarioController@sesionSsic');
  
  Route::post('login_reload','App\Http\Controllers\UsuarioController@sesionReload');
  
  Route::post('secondloginaccess','App\Http\Controllers\UsuarioController@sesionSecondLoginAccess');
  Route::post('registradevice','App\Http\Controllers\UsuarioController@registraDevice');
  
  
  Route::post('total_notificaciones','App\Http\Controllers\NotificacionesController@totalNotificaciones');
  Route::post('lista_min_notificaciones','App\Http\Controllers\NotificacionesController@listaNotificacionesFirst');
  Route::post('lista_notificaciones_all','App\Http\Controllers\NotificacionesController@listaNotificacionesAll');
  //Route::post('lista_min_notificaciones','App\Http\Controllers\NotificacionesController@listaMinNotificaciones');
  Route::post('ultima_notificacion','App\Http\Controllers\NotificacionesController@ultimaNotificacion');
  Route::post('detalle_notificacion','App\Http\Controllers\NotificacionesController@detalleNotificacion');
  Route::post('delete_notificacion','App\Http\Controllers\NotificacionesController@deleteNotificacion');

//funciones generales
  Route::post('updatepasswpordserv','App\Http\Controllers\UsuarioController@userUpdatePass');
  Route::post('empresacompleteregistro','App\Http\Controllers\EmpresasController@empresaCompleteRegistro');
  Route::post('listaempresascargadas','App\Http\Controllers\EmpresasController@listaempresasVigentes');
  Route::post('listanotificaciones','App\Http\Controllers\EmpresasController@listaempresasVigentes');
  Route::post('listachats','App\Http\Controllers\ChatController@listaHistoryChat');
  
  //relojes y permisos de acceso
    Route::post('horarioUso','App\Http\Controllers\menuController@getRelojes');
    Route::post('getFechaInput','App\Http\Controllers\menuController@getFechaInput');
    Route::post('dtalgnpacc','App\Http\Controllers\rolesController@permisoAcceso');
    Route::post('update_language','App\Http\Controllers\SettingsController@updateLanguage');
  //monedas
    Route::get('listaMonedas','App\Http\Controllers\MonedaController@catalogoMonedas');
    Route::post('monedaempresa','App\Http\Controllers\MonedaController@monedaEmpresa');
  //unidadeds de medida
    Route::get('clasificacionMedidaSat','App\Http\Controllers\UMedidaController@clasificacionMedidaSat');
    Route::get('listamedidas','App\Http\Controllers\UMedidaController@listaUnidadesMedida');
    Route::post('medidasat','App\Http\Controllers\UMedidaController@medidasSat');
    Route::get('medidasatservicios','App\Http\Controllers\UMedidaController@medidasSatServicios');
    Route::post('postmedidasatservicios','App\Http\Controllers\UMedidaController@postMedidasSatServicios');
  //configuracion de cfdi
    Route::get('getListaUso','App\Http\Controllers\UsoCFDIController@getListaUso');
    Route::get('getformapago','App\Http\Controllers\FormaPagoController@listaFormaPago');
    Route::get('getmetodopago','App\Http\Controllers\MetodoPagoController@listaMetodoPago');
  //paises
    Route::get('listaPaises','App\Http\Controllers\PaisController@getListaPais');
  //sat
    Route::get('catalogo_prodservsat','App\Http\Controllers\CatSatController@listaCatalogo');
    Route::post('catalogo_prodservsatClave','App\Http\Controllers\CatSatController@listaCatalogoPClave');
    Route::post('catalogo_prodservsatDesc','App\Http\Controllers\CatSatController@listaCatalogoPdesc');
    Route::post('catalogo_prodservsatInput','App\Http\Controllers\CatSatController@listaCatalogoPInput');
  //clasificacion de productos y servicios
    Route::get('getClasificacionProductos','App\Http\Controllers\ClasificacionController@getClasificacionProductos');
    Route::post('getGeneroProductos','App\Http\Controllers\ClasificacionController@getGeneroProductos');
    Route::post('getClasificacionFull','App\Http\Controllers\ClasificacionController@setClasificacionFull');
    Route::get('getClasificacionServicios','App\Http\Controllers\ClasificacionController@getClasificacionServicios');
    Route::post('clasificacompletserv','App\Http\Controllers\ClasificacionController@fullClasifServicios');
  //personal
    Route::post('listapersonalsos','App\Http\Controllers\PersonalController@listaPersonalSOS');
    Route::post('actualizapaternopersonal','App\Http\Controllers\PersonalController@actualizaPaternoPersonalSOS');
    Route::post('actualizamaternopersonal','App\Http\Controllers\PersonalController@actualizaMaternoPersonalSOS');
    Route::post('actualizanombrespersonal','App\Http\Controllers\PersonalController@actualizaNombresPersonalSOS');
    Route::post('actualizaareapersonal','App\Http\Controllers\PersonalController@actualizaAreaPersonalSOS');
    Route::post('generapasscodeuserpersonal','App\Http\Controllers\PersonalController@generaPassCodeUserPersonalSOS');
    Route::post('actualizaemailpersonal','App\Http\Controllers\PersonalController@actualizaMailPersonalSOS');
    Route::post('registratelefonopersonal','App\Http\Controllers\PersonalController@registraTelefonoPersonalSOS');
    Route::post('actualizatelefonopersonal','App\Http\Controllers\PersonalController@actualizaTelefonoPersonalSOS');
    Route::post('listapersgeneral','App\Http\Controllers\PersonalController@listaPersonalGneral');
    Route::post('listapersgeneralarea','App\Http\Controllers\PersonalController@listaPersonalArea');
    Route::post('listapersonal','App\Http\Controllers\PersonalController@listaResponsablesAlmacen');
  //direcciones
    Route::get('getcpostales','App\Http\Controllers\DireccionesController@listacodPostal');
    Route::post('postcpostales','App\Http\Controllers\DireccionesController@listacodPostalLike');
    Route::post('getlistacolonias','App\Http\Controllers\DireccionesController@listacolonias');
    Route::post('getselectentfed','App\Http\Controllers\DireccionesController@selectentfed');

//ingresos
  //catalogos
    //lista_precios
      Route::post('getlistaprecios','App\Http\Controllers\ListaPreciosController@getListaPrecios');
      //mercancias
        Route::post('registralistapreciosmercancias','App\Http\Controllers\ListaPreciosController@registralistaPreciosMerc');
        Route::post('updatelistapreciosmercancias','App\Http\Controllers\ListaPreciosController@updatelistaPreciosMerc');
      //servicios
        Route::post('registralistapreciosserv','App\Http\Controllers\ListaPreciosController@registralistaPreciosServ');
        Route::post('updatelistapreciosserv','App\Http\Controllers\ListaPreciosController@updatelistaPreciosServ');    
    //productos
      Route::post('listavntsProductosVigentes','App\Http\Controllers\ProductosController@listaingresosProductosVigentes');
      Route::post('detallemercancia','App\Http\Controllers\ProductosController@detalleProductoIngresos');
      Route::post('registradescuentomercancia','App\Http\Controllers\DescuentosController@registrarMercDescuento');
      Route::post('vincdescuentomercancia','App\Http\Controllers\DescuentosController@vincularMercDescuento');
      Route::post('desvincdescuentomercancia','App\Http\Controllers\DescuentosController@desvincularMercDescuento');
      Route::post('registrapromocionmercancia','App\Http\Controllers\PromocionesController@registrarMercPromocion');
      Route::post('vincpromocionmercancia','App\Http\Controllers\PromocionesController@vincularMercPromocion');
      Route::post('desvincpromocionmercancia','App\Http\Controllers\PromocionesController@desvincularMercPromocion');
      Route::post('listavntsProductosEliminados','App\Http\Controllers\ServiciosController@listavntsProductosEliminados');

    //servicios
      Route::post('listaserviciosvigentesingresos','App\Http\Controllers\ServiciosController@listaServiciosVigentesIngresos');
      Route::post('simulaprecioservicio','App\Http\Controllers\ServiciosController@simulaPrecioServicio');
      Route::post('detalleingresosservicio','App\Http\Controllers\ServiciosController@viewServicioIngresos');
      Route::post('downloadservicioingresospdf','App\Http\Controllers\ServiciosController@downloadServicioIngresosPdf');
      Route::post('actualizageneralservicioingresos','App\Http\Controllers\ServiciosController@actualizaGeneralesServicioIngresos');
      Route::post('vincimpuestoservicio','App\Http\Controllers\ServiciosController@vincularServicioImpuesto');
      Route::post('desvincimpuestoservicio','App\Http\Controllers\ServiciosController@desvincularServicioImpuesto');
      Route::post('registradescuentoservicio','App\Http\Controllers\DescuentosController@registrarServicioDescuento');
      Route::post('vincdescuentoservicio','App\Http\Controllers\DescuentosController@vincularServicioDescuento');
      Route::post('desvincdescuentoservicio','App\Http\Controllers\DescuentosController@desvincularServicioDescuento');
      Route::post('registrapromocionservicio','App\Http\Controllers\PromocionesController@registrarServicioPromocion');
      Route::post('vincpromocionservicio','App\Http\Controllers\PromocionesController@vincularServicioPromocion');
      Route::post('desvincpromocionservicio','App\Http\Controllers\PromocionesController@desvincularServicioPromocion');
      Route::post('newclienteclaveservicio','App\Http\Controllers\ServiciosController@newClienteClavesServicio');  
      Route::post('clavesactualizaclienteservicio','App\Http\Controllers\ServiciosController@actualizaClienteClavesServicio');
      Route::post('deleteclavesclienteservicio','App\Http\Controllers\ServiciosController@deleteClienteClavesServicio');
      Route::post('deleteservicioingresos','App\Http\Controllers\ServiciosController@deleteServicioIngresos');
      Route::post('listaservicioseliminadosingresos','App\Http\Controllers\ServiciosController@listaServiciosEliminadosIngresos');
      Route::post('servicioingresosrestart','App\Http\Controllers\ServiciosController@restartServicioIngresos');
      Route::post('eliminazionservingresos','App\Http\Controllers\ServiciosController@deleteDeadServicioIngresos');
      Route::post('registroservicioingresos','App\Http\Controllers\ServiciosController@registroServicioIngresos');

    //descuentos
      Route::post('foliomaxdescuentos','App\Http\Controllers\DescuentosController@folioMaxDescuento');
      Route::post('folionewdescuentos','App\Http\Controllers\DescuentosController@folioNewRegDescuento');
      Route::post('listadescuentos','App\Http\Controllers\DescuentosController@listaDescuentos');
      Route::post('descuentosselected','App\Http\Controllers\DescuentosController@verDescuento');
      Route::post('desactivadescuento','App\Http\Controllers\DescuentosController@stopDescuento');
      Route::post('habilitadescuento','App\Http\Controllers\DescuentosController@habilitarDescuento');
      Route::post('updategeneralesdescuento','App\Http\Controllers\DescuentosController@updateGeneralesDescuento');
      Route::post('descuentosdesac','App\Http\Controllers\DescuentosController@listaDescuentosDeact');
      Route::post('eliminadescuento','App\Http\Controllers\DescuentosController@eliminadescuento');
      Route::post('restauradescuento','App\Http\Controllers\DescuentosController@restauradescuento');
      Route::post('deadeliminadescuento','App\Http\Controllers\DescuentosController@eliminaPermDescuento');
      Route::post('descuentosdelete','App\Http\Controllers\DescuentosController@listaDescuentosDel');
      Route::post('registranuevodescuento','App\Http\Controllers\DescuentosController@registraDescuento');

    //promociones
      Route::post('foliomaxpromocion','App\Http\Controllers\PromocionesController@folioMaxPromocion');
      Route::post('folionewpromocion','App\Http\Controllers\PromocionesController@folioNewRegPromocion');
      Route::post('listapromociones','App\Http\Controllers\PromocionesController@listaPromociones');
      Route::post('promocionesselected','App\Http\Controllers\PromocionesController@verPromocion');
      Route::post('desactivapromocion','App\Http\Controllers\PromocionesController@stopPromocion');
      Route::post('habilitapromocion','App\Http\Controllers\PromocionesController@habilitarPromocion');
      Route::post('updategeneralespromocion','App\Http\Controllers\PromocionesController@updateGeneralesPromocion');
      Route::post('promocionesdesac','App\Http\Controllers\PromocionesController@listaPromocionesDesac');
      Route::post('eliminapromocion','App\Http\Controllers\PromocionesController@eliminapromocion');
      Route::post('restaurapromocion','App\Http\Controllers\PromocionesController@restaurapromocion');
      Route::post('deadeliminapromocion','App\Http\Controllers\PromocionesController@eliminaPermPromocion');
      Route::post('promocionesdelete','App\Http\Controllers\PromocionesController@listaPromocionesDel');
      Route::post('registranuevopromocion','App\Http\Controllers\PromocionesController@registraPromocion');

    //impuestos
      Route::post('listaImpuestos','App\Http\Controllers\ImpuestosController@listaImpuestos');
      Route::post('catalogoimpuestos','App\Http\Controllers\ImpuestosController@catalogoImpuestosVig');
      Route::post('viewimpuestoselected','App\Http\Controllers\ImpuestosController@verImpuesto');
      Route::post('catalogoimpuestosdel','App\Http\Controllers\ImpuestosController@catalogoImpuestosDel');

    //clientes
      Route::post('listaclientes','App\Http\Controllers\ClientesController@ClientesGen');
      Route::post('clientesdelete','App\Http\Controllers\ClientesController@ClientesDelete');
      Route::post('verclientes','App\Http\Controllers\ClientesController@verCliente');

//ventas
  //pedidos
  //ventas
    Route::post('catalogoventas','App\Http\Controllers\VentasController@catalogoVentas');
    Route::post('catalogoventas','App\Http\Controllers\VentasController@catalogoVentas');
    Route::post('newFolioVenta','App\Http\Controllers\VentasController@newFolioVenta');
    Route::post('cargaArticulosVenta','App\Http\Controllers\VentasController@cargaArticulosVenta');
    Route::post('descargarttosell','App\Http\Controllers\VentasController@detalleVentaArticulo');
    Route::post('descargarttosellpr','App\Http\Controllers\VentasController@detalleVentaArticuloPr');
    Route::post('registraventa','App\Http\Controllers\VentasController@registraVentaArticulo');

//egresos 
  //catalogos
    //Productos 
      Route::post('listaegresosProductosVigentes','App\Http\Controllers\ProductosController@listaegresosProductosVigentes');
      Route::post('listaegresosProductosProcessBuy','App\Http\Controllers\ProductosController@listaegresosProductosProcessBuy');
      Route::post('detalleproducto','App\Http\Controllers\ProductosController@detalleProductoVigente');
      Route::post('recargaprovproductos','App\Http\Controllers\ProductosController@recargaProvProductos');
      Route::post('detalleproductoproveedor','App\Http\Controllers\ProductosController@detalleProductoProveedor');
      Route::post('updatearticulologo','App\Http\Controllers\ProductosController@updateArticuloLogo');
      Route::post('updategeneralesproducto','App\Http\Controllers\ProductosController@updateGeneralesProducto');
      Route::post('deleteclaveprodproveedor','App\Http\Controllers\ProductosController@deleteClaveProdProveedor');
      Route::post('updateclaveprodproveedor','App\Http\Controllers\ProductosController@updateClaveProdProveedor');
      Route::post('appendclaveprodproveedor','App\Http\Controllers\ProductosController@appendClaveProdProveedor');
      Route::post('changalmproducto','App\Http\Controllers\ProductosController@changAlmProducto');
      Route::post('movepaparticulo','App\Http\Controllers\ProductosController@deleteProducto');
      Route::post('listaegresosProductosEliminados','App\Http\Controllers\ProductosController@listaegresosProductosEliminados');
      Route::post('paprestauraarticulo','App\Http\Controllers\ProductosController@restauraProducto');
      Route::post('eliminapaparticulo','App\Http\Controllers\ProductosController@deletePapProducto');
      Route::post('prodPorProveedor','App\Http\Controllers\ProductosController@prodPorProveedor');
      Route::post('createarticulo','App\Http\Controllers\ProductosController@registroProducto');

    //lotes
      Route::post('listalotesvigentes','App\Http\Controllers\LotesController@listaLotesVigentes'); 
      Route::post('listalotesdelete','App\Http\Controllers\LotesController@listaLotesdelete');
      Route::post('detalleegresoslote','App\Http\Controllers\LotesController@detalleEgresosLote');
      Route::post('actualizaegresoslote','App\Http\Controllers\LotesController@updateEgresosLote'); 
      Route::post('listadeletedlotes','App\Http\Controllers\LotesController@listaLotesDeleted');  
      Route::post('restartlote','App\Http\Controllers\LotesController@loteRestart');  
      Route::post('deleteloteperm','App\Http\Controllers\LotesController@LoteDeletePerm');  
      Route::post('registraLote','App\Http\Controllers\LotesController@registraLote');  

    //pedimentos
      Route::post('listaegresospedimentosvigentes','App\Http\Controllers\PedimentosController@listaegresosPedimentosVigentes');
      Route::post('detalleregresospedimento','App\Http\Controllers\PedimentosController@detalleEgresosPedimento');
      Route::post('actualizaegresospedimento','App\Http\Controllers\PedimentosController@updateEgresosPedimento'); 
      Route::post('listaegresospedimentosdelete','App\Http\Controllers\PedimentosController@listaegresosPedimentosDelete');
      Route::post('listadeletedegresospedimentos','App\Http\Controllers\PedimentosController@listaegresosPedimentosDeleted');
      Route::post('restartpedimento','App\Http\Controllers\PedimentosController@pedimentoRestart');
      Route::post('pedimentodeleteperm','App\Http\Controllers\PedimentosController@pedimentoDeletePerm');
      Route::post('registrapedimento','App\Http\Controllers\PedimentosController@registraPedimento');

    //pedimentos
      Route::post('listaegresosgastosvigentes','App\Http\Controllers\GastosController@listaGastosVigentes');
      //Route::post('detalleregresospedimento','App\Http\Controllers\PedimentosController@detalleEgresosPedimento');
      //Route::post('actualizaegresospedimento','App\Http\Controllers\PedimentosController@updateEgresosPedimento'); 
      //Route::post('listaegresospedimentosdelete','App\Http\Controllers\PedimentosController@listaegresosPedimentosDelete');
      //Route::post('listadeletedegresospedimentos','App\Http\Controllers\PedimentosController@listaegresosPedimentosDeleted');
      //Route::post('restartpedimento','App\Http\Controllers\PedimentosController@pedimentoRestart');
      //Route::post('pedimentodeleteperm','App\Http\Controllers\PedimentosController@pedimentoDeletePerm');
      //Route::post('registrapedimento','App\Http\Controllers\PedimentosController@registraPedimento');

    //servicios
      Route::post('listaegresosServiciosVigentes','App\Http\Controllers\ServiciosController@listaegresosServiciosVigentes');
      Route::post('detalleservicioegresos','App\Http\Controllers\ServiciosController@viewServicioEgresos');
      Route::post('detalleservicioproveedor','App\Http\Controllers\ServiciosController@detalleServicioProveedor');
      Route::post('recargaprovservicios','App\Http\Controllers\ServiciosController@recargaProvServicios');
      Route::post('downpdfservegresos','App\Http\Controllers\ServiciosController@downloadServicioEgresosPdf');
      Route::post('updateservicioegresos','App\Http\Controllers\ServiciosController@actualizaGeneralesServicio');
      Route::post('updateservicioprov','App\Http\Controllers\ServiciosController@actualizaProvClavesServicio');
      Route::post('newservicioprov','App\Http\Controllers\ServiciosController@newProvClavesServicio');
      Route::post('deleteservicioprov','App\Http\Controllers\ServiciosController@deleteProvClavesServicio');
      Route::post('servicioegresostopap','App\Http\Controllers\ServiciosController@deleteServicioEgresos');
      Route::post('listaegresosServiciosEliminados','App\Http\Controllers\ServiciosController@listaegresosServiciosEliminados');
      Route::post('restartservicioegresos','App\Http\Controllers\ServiciosController@restartServicioEgresos');
      Route::post('servicioegresosdead','App\Http\Controllers\ServiciosController@deleteDeadServicioEgresos');
      Route::post('appendservicio','App\Http\Controllers\ServiciosController@registroServicio');

    //activos
      //activos fijos
        Route::post('listaActivosFijos','App\Http\Controllers\ActivosFijosController@getActivosFijos');
        Route::post('listacompraActivosFijos','App\Http\Controllers\ActivosFijosController@getActivosFijosCompra');
        Route::post('viewActivoFijo','App\Http\Controllers\ActivosFijosController@verActivoFijo');
        Route::post('actualizageneralesactfijo','App\Http\Controllers\ActivosFijosController@actualizaGeneralesActivoFijo');
        Route::post('updateactivofijoprov','App\Http\Controllers\ActivosFijosController@actualizaProvClavesActivo');
        Route::post('newactivofijoprov','App\Http\Controllers\ActivosFijosController@newProvClavesActivo');
        Route::post('deleteactivofijoprov','App\Http\Controllers\ActivosFijosController@deleteProvClavesActivo');
        Route::post('deletepapeleraactivofijo','App\Http\Controllers\ActivosFijosController@deleteActivoFijo');
        Route::post('listaActivosFijosDeleted','App\Http\Controllers\ActivosFijosController@getActivosFijosDeleted');
        Route::post('restartActivosFijos','App\Http\Controllers\ActivosFijosController@restartActivosFijos');
        Route::post('deleteDeadActivosFijos','App\Http\Controllers\ActivosFijosController@deleteDeadActivosFijos');
        Route::post('clasificacionfijosactv','App\Http\Controllers\ActivosFijosController@listaClassAct');
        Route::post('agregaclassactivo','App\Http\Controllers\ActivosFijosController@agregaClassActivo');
        Route::post('appendactivofijo','App\Http\Controllers\ActivosFijosController@registroActivoFijo');

      //activos fijos
        Route::post('listaActivosIntan','App\Http\Controllers\ActivosIntangiblesController@getListActIntangibles');
        Route::post('listacompraActivosIntan','App\Http\Controllers\ActivosIntangiblesController@getListActIntangiblesCompras');
        Route::post('viewActivoIntan','App\Http\Controllers\ActivosIntangiblesController@verActivoIntang');
        Route::post('actualizageneralesactintang','App\Http\Controllers\ActivosIntangiblesController@actualizageneralesActivoIntang');
        Route::post('updateactivointangprov','App\Http\Controllers\ActivosIntangiblesController@actualizaProvClavesActivoIntang');
        Route::post('nuevactivointangprov','App\Http\Controllers\ActivosIntangiblesController@newProvClavesActivoIntang');
        Route::post('deleteactivointangprov','App\Http\Controllers\ActivosIntangiblesController@deleteProvClavesActivoIntang');
        Route::post('deletepapeleraactivointang','App\Http\Controllers\ActivosIntangiblesController@deleteActivoIntang');
        Route::post('listaactivosintandeleted','App\Http\Controllers\ActivosIntangiblesController@getActivosIntangDeleted');
        Route::post('restartActivosintang','App\Http\Controllers\ActivosIntangiblesController@restartActivosIntang');
        Route::post('deleteDeadActivosIntang','App\Http\Controllers\ActivosIntangiblesController@deleteDeadActivosIntang');
        Route::post('activosclasificacionintang','App\Http\Controllers\ActivosIntangiblesController@listaClassActIntangibles');
        Route::post('agregaclassactivointang','App\Http\Controllers\ActivosFijosController@agregaClassActivoIntang');
        Route::post('appendactivointangible','App\Http\Controllers\ActivosIntangiblesController@registroActivoIntang');

    //proveedores
      Route::post('listaproveedoresgen','App\Http\Controllers\ProveedoresController@proveedoresGen');
      Route::post('catalogoprovig','App\Http\Controllers\ProveedoresController@getCatalogoProvVig');
      Route::post('catalogoprovdel','App\Http\Controllers\ProveedoresController@getCatalogoProvDel');
      Route::post('verproveedores','App\Http\Controllers\ProveedoresController@verProveedores');
      Route::post('registracuentacontableproveedor','App\Http\Controllers\ProveedoresController@createCuentaContableProv');
      Route::post('actualizarfcproveedor','App\Http\Controllers\ProveedoresController@actualizaRfcProv');
      Route::post('actualizaidtaxproveedor','App\Http\Controllers\ProveedoresController@actualizaIdTaxProv');
      Route::post('actualizageneralespfproveedor','App\Http\Controllers\ProveedoresController@actualizaGeneralesPF');
      Route::post('actualizageneralespmproveedor','App\Http\Controllers\ProveedoresController@actualizaGeneralesPM');
      Route::post('actualizaredesproveedor','App\Http\Controllers\ProveedoresController@actualizaRedes');
      Route::post('ingresapersonalproveedor','App\Http\Controllers\ProveedoresController@ingresaPersonalProveedor');
      Route::post('eliminapersonalproveedor','App\Http\Controllers\ProveedoresController@deletePersonalProv');
      Route::post('actualizapersonalgeneralesproveedor','App\Http\Controllers\ProveedoresController@actualizaGeneralesPersonal');
      Route::post('agregapersonaltelefonoproveedor','App\Http\Controllers\ProveedoresController@nuevoTelefonoPersonal');
      Route::post('actualizapersonaltelefonoproveedor','App\Http\Controllers\ProveedoresController@actualizaTelefonoPersonal');
      Route::post('eliminapersonaltelefonoproveedor','App\Http\Controllers\ProveedoresController@eliminaTelefonoPersonal');
      Route::post('restartpersonaltelefonoproveedor','App\Http\Controllers\ProveedoresController@restartTelefonoPersonal');
      Route::post('eliminapermpersonaltelefonoproveedor','App\Http\Controllers\ProveedoresController@eliminaTelefonoPersonalPermanente');
      Route::post('agregapersonalemailproveedor','App\Http\Controllers\ProveedoresController@nuevoCorreoPersonal');
      Route::post('actualizapersonalemailproveedor','App\Http\Controllers\ProveedoresController@actualizaCorreoPersonal');
      Route::post('eliminapersonalemailproveedor','App\Http\Controllers\ProveedoresController@eliminaCorreoPersonal');
      Route::post('restartpersonalemailproveedor','App\Http\Controllers\ProveedoresController@restartCorreoPersonal');
      Route::post('eliminapermpersonalemailproveedor','App\Http\Controllers\ProveedoresController@eliminaCorreoPersonalPermanente');
      Route::post('restartpersonalproveedor','App\Http\Controllers\ProveedoresController@restartPersonalProv');
      Route::post('deletepermanentepersonalproveedor','App\Http\Controllers\ProveedoresController@deletePersonalProvPermanente');
      Route::post('updatecontanciafiscalsitload','App\Http\Controllers\ProveedoresController@updatecontanciafiscalsitload');
      Route::post('updatecontanciafiscalsitbase64','App\Http\Controllers\ProveedoresController@updatecontanciafiscalsitbase64');
      Route::post('updatecumplimientoload','App\Http\Controllers\ProveedoresController@updatecumplimientoload');
      Route::post('updatecumplimientobase64','App\Http\Controllers\ProveedoresController@updatecumplimientobase64');
      Route::post('updatecreditosproveedor','App\Http\Controllers\ProveedoresController@updateCreditosProveedor');
      Route::post('updateformapagoproveedor','App\Http\Controllers\ProveedoresController@updateFormaPagoProveedor');
      Route::post('updatefpagoproveedorestcuenta','App\Http\Controllers\ProveedoresController@updatefPagoProveedorEstCuenta');
      Route::post('updateclabeinterbpagoproveedor','App\Http\Controllers\ProveedoresController@updateClabeInterbPagoProveedor');
      Route::post('registranuevaubicacionnacionalproveedor','App\Http\Controllers\ProveedoresController@registraNuevaUbicacionNacionalProveedor');
      Route::post('registranuevaubicacionextranjeroproveedor','App\Http\Controllers\ProveedoresController@registraNuevaUbicacionExtranjeroProveedor');
      Route::post('updateubicacionnacionalproveedor','App\Http\Controllers\ProveedoresController@updateUbicacionNacionalProveedor');
      Route::post('updateubicacionextranjeroproveedor','App\Http\Controllers\ProveedoresController@updateUbicacionExtranjeroProveedor');
      Route::post('deleteubicacionproveedor','App\Http\Controllers\ProveedoresController@deleteUbicacionProveedor');
      Route::post('restaurarubicacionproveedor','App\Http\Controllers\ProveedoresController@restaurarUbicacionProveedor');
      Route::post('deletepermubicacionproveedor','App\Http\Controllers\ProveedoresController@deletePermUbicacionProveedor');
      Route::post('deleteproveedor','App\Http\Controllers\ProveedoresController@deleteProveedor');
      Route::post('restaurarproveedor','App\Http\Controllers\ProveedoresController@restaurarProveedor');
      Route::post('deletepermproveedor','App\Http\Controllers\ProveedoresController@deletePermProveedor');
      Route::post('egresos-busquedaproveedor','App\Http\Controllers\ProveedoresController@buscaRFProveedor');
      Route::post('egresos-busquedaextproveedor','App\Http\Controllers\ProveedoresController@buscaRFProveedorExtPM');
      Route::post('egresos-busquedapfextproveedor','App\Http\Controllers\ProveedoresController@buscaRFProveedorExtPF');
      Route::post('egresos-registraproveedor','App\Http\Controllers\ProveedoresController@registraProveedor');

    //establecimientos
      Route::post('totalalmacenes','App\Http\Controllers\AlmacenController@totalAlmacenes');
      Route::post('listdireccionalm','App\Http\Controllers\AlmacenController@direccionAlmacen');
      Route::post('listdireccionalmcomplete','App\Http\Controllers\AlmacenController@direccionAlmacenComplete');
      Route::post('listdireccionalmdeleted','App\Http\Controllers\AlmacenController@direccionAlmacenDeleted');
      Route::post('detalleestablecimiento','App\Http\Controllers\AlmacenController@detalleAlmacen');
      Route::post('updategeneralestablecimiento','App\Http\Controllers\AlmacenController@updateGenerales');
      Route::post('updateubicanacestab','App\Http\Controllers\AlmacenController@updateUbicacionNacional');
      Route::post('updateubicaextestab','App\Http\Controllers\AlmacenController@updateUbicacionExtranjero');
      Route::post('quitapersonalestab','App\Http\Controllers\AlmacenController@eliminaPersonalEstablecimiento');
      Route::post('agregapersonalestab','App\Http\Controllers\AlmacenController@agregaPersonalEstablecimiento');
      Route::post('registraestablecimientonacional','App\Http\Controllers\AlmacenController@registraEstablecimientoNacional');
      Route::post('registraestablecimientoextranjero','App\Http\Controllers\AlmacenController@registraEstablecimientoExtranjero');
    
  //compras
    //requisiciones
      Route::post('catalogoRequisiciones','App\Http\Controllers\RequisicionesController@catalogoRequisiciones');
      Route::post('totalRequisicionesPend','App\Http\Controllers\RequisicionesController@totalRequisicionesPendientes');
      Route::post('folioReqMax','App\Http\Controllers\RequisicionesController@folioReqMax');
      Route::get('listacaracteristicas','App\Http\Controllers\RequisicionesController@listaCaracteristicas');

    //cotizaciones
      Route::post('catalogoCotizaciones','App\Http\Controllers\CotizacionesController@catalogoCotizaciones');
      Route::post('totalCotizacionesPend','App\Http\Controllers\CotizacionesController@totalCotizacionesPendientes');
      Route::post('folioCotMax','App\Http\Controllers\CotizacionesController@folioCotMax');

    //compras listaComprasProd
      Route::post('selectFolioCompra','App\Http\Controllers\ComprasController@selectFolioCompra');
      Route::post('listaComprasProd','App\Http\Controllers\ComprasController@listaComprasProd');
      Route::post('validaestructxmlingresos','App\Http\Controllers\xmlValidateController@validaEstructXmlIngresos');
      Route::post('listaprdservcomp','App\Http\Controllers\ComprasController@cargaArticulosCompras');
      Route::post('listaprdservcompprov','App\Http\Controllers\ComprasController@cargaArticulosCompras');
      Route::post('consultarticulocompra','App\Http\Controllers\ComprasController@consultArticuloCompras');
      Route::get('vaduanas','App\Http\Controllers\xmlValidateController@aduanas');
      Route::post('registracompra','App\Http\Controllers\ComprasController@registraCompra');
      Route::post('pruebaregistracompra','App\Http\Controllers\ComprasController@pruebaregistraCompra');

      //seguimiento de compras
        //compras no autorizadas
          Route::post('listanoautorizadacompra','App\Http\Controllers\ComprasController@listanoautorizadaCompra');
          Route::post('autorizarcompra','App\Http\Controllers\ComprasController@autorizarCompra');
          Route::post('cancelarcompra','App\Http\Controllers\ComprasController@cancelarCompra');

        //prorrateos
          Route::post('listaegresosnoprorratea','App\Http\Controllers\ProrrateosController@listaNoProrrateos');
          Route::post('detailegresosnoprorratefalse','App\Http\Controllers\ProrrateosController@detalleNoProrrateos');
          Route::post('listaegresosprorrateos','App\Http\Controllers\ProrrateosController@listaProrrateos');
          Route::post('detailegresosprorrateos','App\Http\Controllers\ProrrateosController@detalleProrrateo');
          Route::post('historialegresosprorrateos','App\Http\Controllers\ProrrateosController@historialDetalleProrrateo');
          Route::post('deletehistoricdetalleprorrat','App\Http\Controllers\ProrrateosController@eliminarHistoricoDetalleProrrateo');
          Route::post('guardaregresosprorrateos','App\Http\Controllers\ProrrateosController@guardarProrrateo');

        //compras autorizadas
          Route::post('detallecomprasautorizadas','App\Http\Controllers\ComprasController@detalleComprasAutorizadas');
          Route::post('listacomprasautorizadas','App\Http\Controllers\ComprasController@listaComprasAutorizadas');
          Route::post('trueperiodoespera24hrs','App\Http\Controllers\ComprasController@habilitaPeridoEspera');
          Route::post('rechazoscomprasautorizadas','App\Http\Controllers\ComprasController@rechazosComprasAutorizadas');
          Route::post('recibecepcionprodcompras','App\Http\Controllers\ComprasController@recibeProdComprasAlmacen');
          Route::post('buyrecibeactfijos','App\Http\Controllers\ComprasController@recibeActivoFijoComprasAlmacen');
          Route::post('recibeactintangbuy','App\Http\Controllers\ComprasController@recibeActivoIntangComprasAlmacen');
          Route::post('recibeserviciosbuy','App\Http\Controllers\ComprasController@recibeServComprasAlmacen');

          Route::post('listacomprasdevoluciones','App\Http\Controllers\ComprasController@listaComprasDevoluciones');

//Tesoreria
  //catalogos
    //cuentas bancarias
      Route::get('listabancos','App\Http\Controllers\CuentBancController@bancos');
      Route::post('foliocuentabanc','App\Http\Controllers\CuentBancController@folioCuentaBancaria');
      Route::post('responsablecuenta','App\Http\Controllers\CuentBancController@responsableCuenta');
      Route::post('cuentasvig','App\Http\Controllers\CuentBancController@cuentasVig');
      Route::post('cuentasdel','App\Http\Controllers\CuentBancController@cuentasDel');
      Route::post('detallecuentavig','App\Http\Controllers\CuentBancController@detalleCuentasVig');
      Route::post('detalleCuentaMonBancovig','App\Http\Controllers\CuentBancController@detalleCuentaMonederoCBancoVig');
      Route::post('registracuentabancaria','App\Http\Controllers\CuentBancController@registraCuentaBanc'); 
      Route::post('updatecuentbncaria','App\Http\Controllers\CuentBancController@updateCuentaBanc');
      Route::post('eliminacuentaban','App\Http\Controllers\CuentBancController@deleteCuentaBancaria');
      Route::post('restauracuentaban','App\Http\Controllers\CuentBancController@restaurarCuentaBancaria');
      Route::post('deltepermcuentaban','App\Http\Controllers\CuentBancController@deltPermanenteCuentaBancaria');

    //caja
      Route::post('foliocaja','App\Http\Controllers\CajaController@folioCaja');
      Route::post('listacaja','App\Http\Controllers\CajaController@listaCajaVig');
      Route::post('listacajadel','App\Http\Controllers\CajaController@listaCajaDel');
      Route::post('detallecaja','App\Http\Controllers\CajaController@detalleCajaVig');
      Route::post('responsablecaja','App\Http\Controllers\CajaController@respCaja');
      Route::post('registracaja','App\Http\Controllers\CajaController@registraCaja');
      Route::put('updatealmacencaja','App\Http\Controllers\CajaController@updateAlmacenCaja');
      Route::post('chngperscja','App\Http\Controllers\CajaController@desvincRespCaja');
      Route::post('vnculspnbcaja','App\Http\Controllers\CajaController@vinculaRespCaja');
      Route::post('updtpersnew','App\Http\Controllers\CajaController@updateAlmacenNewCaja');
      Route::post('updtecja','App\Http\Controllers\CajaController@updateCaja');
      Route::post('editacortecja','App\Http\Controllers\CajaController@editaCorteCaja');
      Route::post('newcortecja','App\Http\Controllers\CajaController@agregaNewCorteCaja');
      Route::post('eliminacortecja','App\Http\Controllers\CajaController@deleteCorteCaja');
      Route::post('eliminacja','App\Http\Controllers\CajaController@deleteCaja');
      Route::post('restauracaja','App\Http\Controllers\CajaController@restaurarCaja'); 
      Route::post('eliminapermcj','App\Http\Controllers\CajaController@eliminaPrmannteCaja');

    //monedero electronico
      Route::get('catalogomonelect','App\Http\Controllers\MonedElectController@monederosElectronicos');
      Route::post('foliomonelectronico','App\Http\Controllers\MonedElectController@folioMonederoElectronico');
      Route::post('responsablemonedero','App\Http\Controllers\MonedElectController@responsableMonedero');
      Route::post('verlistamonedero','App\Http\Controllers\MonedElectController@ListaMonederoVig');
      Route::post('verlistamonederodel','App\Http\Controllers\MonedElectController@ListaMonederoDel');
      Route::post('detallemonedero','App\Http\Controllers\MonedElectController@detalleMonederoVig');
      Route::post('registramonederoelctrnico','App\Http\Controllers\MonedElectController@registrarMonederoElctronico');
      Route::post('updatemonederoelectronico','App\Http\Controllers\MonedElectController@updateMonederoElectronico');
      Route::post('eliminamonelectronico','App\Http\Controllers\MonedElectController@eliminarMonederoElctronico');
      Route::post('restauramonelectronico','App\Http\Controllers\MonedElectController@restaurarMonederoElctronico');
      Route::post('deletPermmonederoelctrnico','App\Http\Controllers\MonedElectController@deletPermMonederoElctronico');

    //dispositivos
      Route::get('listipodispositivo','App\Http\Controllers\DispositivosController@listaTipoDispositivo');
      Route::post('foliodispositivo','App\Http\Controllers\DispositivosController@folioDispositivo');
      Route::post('verlistadisovig','App\Http\Controllers\DispositivosController@listaDispositivosVig');
      Route::post('verlistadispdel','App\Http\Controllers\DispositivosController@listaDispositivosDel');
      Route::post('detalledispositivo','App\Http\Controllers\DispositivosController@detalleDispositivo');
      Route::post('actualizadispositivo','App\Http\Controllers\DispositivosController@actualizaDispositivo');
      Route::post('actualizacajadispositivo','App\Http\Controllers\DispositivosController@actualizaCajaDispositivo');
      Route::post('unvinccajadispositivo','App\Http\Controllers\DispositivosController@unvincCajaDispositivo');
      Route::post('actualizacuentabankdispositivo','App\Http\Controllers\DispositivosController@actualizaCuentaBankDispositivo');
      Route::post('unvinccuentabankdispositivo','App\Http\Controllers\DispositivosController@unvincCuentaBankDispositivo');
      Route::post('actualizacuentamoneddispositivo','App\Http\Controllers\DispositivosController@actualizaCuentaMonedDispositivo');
      Route::post('unvinccuentamoneddispositivo','App\Http\Controllers\DispositivosController@actualizaCuentaMonedDispositivo');
      Route::post('deletedispositivo','App\Http\Controllers\DispositivosController@deleteDispositivo');
      Route::post('restauradispositivo','App\Http\Controllers\DispositivosController@restaurarDispositivo');
      Route::post('deletepermdispositivo','App\Http\Controllers\DispositivosController@deletePermanenteDispositivo');
      Route::post('registradispositivo','App\Http\Controllers\DispositivosController@registrarDispositivo');

  //ordenes de pago
    Route::post('countordenespago','App\Http\Controllers\OrdenPagoController@countOrdenPagoCompras');
    Route::post('listaordenespagocompras','App\Http\Controllers\OrdenPagoController@listaOrdenPagoCompras');
    Route::post('detalleordenpagocompras','App\Http\Controllers\OrdenPagoController@detalleOrdenPagoCompras'); 
    Route::post('registrapagodirecto','App\Http\Controllers\OrdenPagoController@pagarOrdenPagoDirecto'); 
        
//tecnologias de la informacion
  Route::post('catalogocuentas','App\Http\Controllers\SoliRegistroController@catalogoCuentas');

//tecnologias de la informacion
  Route::post('solicitudes_reg_vig','App\Http\Controllers\SoliRegistroController@solicitudRegistroVigentes');

//apps externas
  Route::post('checador_entrada_personal','App\Http\Controllers\PersonalController@asistenciaPersonalEntrada');
  Route::post('checador_salida_personal','App\Http\Controllers\PersonalController@asistenciaPersonalSalida');
  
  Route::post('lista_proyectos','App\Http\Controllers\TareasProgramadasController@listaProyectos');
  Route::post('lista_proyectos_eliminados','App\Http\Controllers\TareasProgramadasController@listaProyectosDeleted');
  Route::post('lista_proyectos_fecha_asc','App\Http\Controllers\TareasProgramadasController@listaProyectosAscFecha');
  Route::post('lista_proyectos_fecha_desc','App\Http\Controllers\TareasProgramadasController@listaProyectosDescFecha');
  Route::post('lista_proyectos_black_asc','App\Http\Controllers\TareasProgramadasController@listaProyectosAscBlack');
  Route::post('lista_proyectos_black_desc','App\Http\Controllers\TareasProgramadasController@listaProyectosDescBlack');
  Route::post('lista_proyectos_green_asc','App\Http\Controllers\TareasProgramadasController@listaProyectosAscGreen');
  Route::post('lista_proyectos_green_desc','App\Http\Controllers\TareasProgramadasController@listaProyectosDescGreen');
  Route::post('lista_proyectos_yellow_asc','App\Http\Controllers\TareasProgramadasController@listaProyectosAscYellow');
  Route::post('lista_proyectos_yellow_desc','App\Http\Controllers\TareasProgramadasController@listaProyectosDescYellow');
  Route::post('lista_proyectos_red_asc','App\Http\Controllers\TareasProgramadasController@listaProyectosAscRed');
  Route::post('lista_proyectos_red_desc','App\Http\Controllers\TareasProgramadasController@listaProyectosDescRed');
  Route::post('lista_proyectos_finish_asc','App\Http\Controllers\TareasProgramadasController@listaProyectosAscFinish');
  Route::post('lista_proyectos_finish_desc','App\Http\Controllers\TareasProgramadasController@listaProyectosDescFinish');
  Route::post('registrar_proyecto','App\Http\Controllers\TareasProgramadasController@registrarProyecto');
  Route::post('nuevo_nombre_proyecto','App\Http\Controllers\TareasProgramadasController@nuevoNombreProyecto');  
  Route::post('detalle_proyecto','App\Http\Controllers\TareasProgramadasController@detalleProyecto');
  Route::post('actualizar_proyecto','App\Http\Controllers\TareasProgramadasController@actualizarProyecto');
  Route::post('agregar_proyecto_eqtrabajo','App\Http\Controllers\TareasProgramadasController@agregarEqTeamProyecto');
  Route::post('eliminar_proyecto_eqtrabajo','App\Http\Controllers\TareasProgramadasController@eliminarEqTeamProyecto');
  Route::post('eliminar_proyecto','App\Http\Controllers\TareasProgramadasController@eliminarProyecto');
  Route::post('restaurar_proyecto','App\Http\Controllers\TareasProgramadasController@restaurarProyecto');
  Route::post('remover_proyecto','App\Http\Controllers\TareasProgramadasController@removerProyecto');
  Route::post('proyecto_recalendarizar','App\Http\Controllers\TareasProgramadasController@recalendarizarProyecto');
    
  Route::post('registrar_tarea','App\Http\Controllers\TareasProgramadasController@registrarTarea');
  Route::post('detalle_tarea','App\Http\Controllers\TareasProgramadasController@detalleTarea');
  Route::post('duplica_tarea','App\Http\Controllers\TareasProgramadasController@duplicaTarea');
  Route::post('actualiza_tarea','App\Http\Controllers\TareasProgramadasController@actualizaTarea');
  Route::post('recalendarizar_tarea','App\Http\Controllers\TareasProgramadasController@recalendarizarTarea');
  Route::post('agrega_responsable_tarea','App\Http\Controllers\TareasProgramadasController@agregarRespTarea');
  Route::post('elimina_responsable_tarea','App\Http\Controllers\TareasProgramadasController@eliminarRespTarea');
  Route::post('terminar_tarea','App\Http\Controllers\TareasProgramadasController@terminarTarea');
  Route::post('eliminar_tarea','App\Http\Controllers\TareasProgramadasController@eliminarTarea');
  Route::post('restaurar_tarea','App\Http\Controllers\TareasProgramadasController@restaurarTarea');
  Route::post('remove_perm_tarea','App\Http\Controllers\TareasProgramadasController@removeTareaPerm');
  Route::post('tareas_detalle_subtarea_tarea','App\Http\Controllers\TareasProgramadasController@detalleSubTareaWithTarea');
  
  Route::post('registra_informe','App\Http\Controllers\TareasProgramadasController@registrarInformeTarea');
  Route::post('detalle_informe','App\Http\Controllers\TareasProgramadasController@detalleInforme');
  Route::post('revisar_informe','App\Http\Controllers\TareasProgramadasController@revisarInformeTarea');
  Route::post('aprobar_informe','App\Http\Controllers\TareasProgramadasController@aprobarInformeTarea');
  Route::post('actualiza_informe','App\Http\Controllers\TareasProgramadasController@updateInformeTarea');
  Route::post('actualiza_observaciones_informe','App\Http\Controllers\TareasProgramadasController@updateObservacionesTarea');
  Route::post('carga_evidencias_informe','App\Http\Controllers\TareasProgramadasController@cargaEvidenciasInformeTarea');
  Route::post('proy_eliminar_evidencia','App\Http\Controllers\TareasProgramadasController@deleteEvidenciaInfProyecto');
  Route::post('elimina_informe','App\Http\Controllers\TareasProgramadasController@deleteInformeTarea');
  Route::post('restaurar_informe','App\Http\Controllers\TareasProgramadasController@restaurarInformeTarea');
  Route::post('elimina_perm_informe','App\Http\Controllers\TareasProgramadasController@deleteInformePerm');
  
  Route::post('control_proyectos','App\Http\Controllers\TareasProgramadasController@controlProyectos');

  Route::post('total_informes','App\Http\Controllers\TareasProgramadasController@total_informes');
  Route::post('notif_informes','App\Http\Controllers\TareasProgramadasController@notif_informes');

  Route::post('total_proyectos','App\Http\Controllers\TareasProgramadasController@total_proyectos'); 
  Route::post('notif_newproyecto','App\Http\Controllers\TareasProgramadasController@notif_newproyecto');
 
  Route::post('total_proyectosfinish','App\Http\Controllers\TareasProgramadasController@total_proyectos_finish');  
  Route::post('notif_proyectofinish','App\Http\Controllers\TareasProgramadasController@notif_proyectofinish');
  
  Route::post('total_proyectosvencer','App\Http\Controllers\TareasProgramadasController@total_proyectos_vencer'); 
  Route::post('notif_proyectovencer','App\Http\Controllers\TareasProgramadasController@notif_proyectovencer');

  Route::post('total_proyectosvencido','App\Http\Controllers\TareasProgramadasController@total_proyectos_vencido');   
  Route::post('notif_proyectovencido','App\Http\Controllers\TareasProgramadasController@notif_proyectovencido');
  
  Route::post('total_tareas','App\Http\Controllers\TareasProgramadasController@total_tareas');
  Route::post('notif_newtarea','App\Http\Controllers\TareasProgramadasController@notif_newtarea');
  
  Route::post('total_tareasfinish','App\Http\Controllers\TareasProgramadasController@total_tareasfinish');
  Route::post('notif_tareafinish','App\Http\Controllers\TareasProgramadasController@notif_tareafinish');
  
  Route::post('total_tareasvencer','App\Http\Controllers\TareasProgramadasController@total_tareas_vencer');  
  Route::post('notif_tareavencer','App\Http\Controllers\TareasProgramadasController@notif_tareavencer');
  
  Route::post('total_tareasvencida','App\Http\Controllers\TareasProgramadasController@total_tareas_vencida');  
  Route::post('notif_tareavencida','App\Http\Controllers\TareasProgramadasController@notif_tareavencida');
 
