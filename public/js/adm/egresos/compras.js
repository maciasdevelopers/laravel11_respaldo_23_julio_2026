$(document).ready(function () {
    $('.modal').modal();
    $('.tooltipped').tooltip();

    //crear menu
        const app = new Vue({
            mounted() {
                $('.tooltipped').tooltip();
              },
            el:"#menuCatComp",
            data:{
                compraMenu:[
                    {imagen:'vista/media/adm/usuarios/egresos/compras/requisiciones/requisiciones.jpg',
                        btn1:{id:'btnAbreCatReq',letrero:'Catalogo de requisiciones',icon:'fas fa-clipboard-list'},
                        btn2:{id:'btnAbreAltaReq',letrero:'Alta de requisiciones',icon:'fas fa-tasks'}},

                    {imagen:'vista/media/adm/usuarios/egresos/compras/cotizaciones/cotizaciones.jpg',
                        btn1:{id:'btnAbreCatCot',letrero:'Catalogo de cotizaciones',icon:'fas fa-money-check-alt'},
                        btn2:{id:'btnAbreAltaCot',letrero:'Alta de cotizaciones',icon:'fas fa-tasks'}},

                    {imagen:'vista/media/adm/usuarios/egresos/compras/compras/compras.jpg',
                        btn1:{id:'btnAbreCatCom',letrero:'Catalogo de compras',icon:'fas fa-shopping-cart'},
                        btn2:{id:'btnAbreAltaCom',letrero:'Alta de compras',icon:'fas fa-tasks'}},

                    {imagen:'vista/media/adm/usuarios/egresos/compras/segcomp/segcomp.jpg',
                        btn1:{id:'btnAbreCatSegCom',letrero:'Catalogo de seguimiento de compras',icon:'fas fa-cart-arrow-down'},
                        btn2:{id:'btnAbreAltaSegCom',letrero:'Alta de seguimiento de compras',icon:'fas fa-tasks'}},

                ]
            }
        }); 

    //funcionalidad del menu    
        var menuCatComp = document.getElementById("menuCatComp");
        var listaRequisi = document.getElementById("listaRequisi");
        var formRequisi = document.getElementById("formRequisi");
        var listaCot = document.getElementById("listaCot");
        var formCot = document.getElementById("formCot");
        var listaCompras = document.getElementById("listaCompras");
        var formCompras = document.getElementById("formCompras");

        //compras
            //requisiciones
                //Catalogo
                    var btnAbreCatReq = document.getElementById("btnAbreCatReq");
                    $(btnAbreCatReq).click(function(){
                        abreCatalogoCompras();
                        listaRequisi.classList.remove("noneView");
                    });
                //Alta
                    var btnAbreAltaReq = document.getElementById("btnAbreAltaReq");
                    $(btnAbreAltaReq).click(function(){
                        abreCatalogoCompras();
                        formRequisi.classList.remove("noneView");
                    });
            //cotizaciones
                //Catalogo
                    var btnAbreCatCot = document.getElementById("btnAbreCatCot");
                    $(btnAbreCatCot).click(function(){
                        abreCatalogoCompras();
                        listaCot.classList.remove("noneView");
                    });
                //Alta
                    var btnAbreAltaCot = document.getElementById("btnAbreAltaCot");
                    $(btnAbreAltaCot).click(function(){
                        abreCatalogoCompras();
                        formCot.classList.remove("noneView");
                    });
            //compras
                //Catalogo
                    var btnAbreCatCom = document.getElementById("btnAbreCatCom");
                    $(btnAbreCatCom).click(function(){
                        abreCatalogoCompras();
                        listaCompras.classList.remove("noneView");
                    });
                //Alta
                    var btnAbreAltaCom = document.getElementById("btnAbreAltaCom");
                    $(btnAbreAltaCom).click(function(){
                        abreCatalogoCompras();
                        formCompras.classList.remove("noneView");
                    });
                
            function abreCatalogoCompras(){
            menuCatComp.classList.add("menuCatReducido");
            listas_ps.classList.remove("noneView");
            listaRequisi.classList.add("noneView");
            formRequisi.classList.add("noneView");
            listaCot.classList.add("noneView");
            formCot.classList.add("noneView");
            listaCompras.classList.add("noneView");
            formCompras.classList.add("noneView");
            }
    
    //funcionalidad de compras 
        var media = window.matchMedia("(max-width: 400px)");
        var divProductos = document.getElementById("div-listaprod");
        var formProd = document.getElementById("formProd"); 
        var buscar_prod = document.getElementById("buscar-prod");
        var detalleReqCompra = document.getElementById("detalleReqCompra");
        var detalleCotizacionCompra = document.getElementById("detalleCotizacionCompra");
        var detalleUl = document.getElementById("detalleUl");
        var txtBuscarLCompra = document.getElementById("txtBuscarLCompra");

        //fecha y folio
            var fechaRegCompra = document.getElementById("fechaRegCompra");
            var foliocompra = document.getElementById("foliocompra");

        //proveedor
            var rfcReceptorIG = document.getElementById("rfcReceptorIG");
            var liProveedor = document.getElementById("liProveedor");
            var headProveedor = document.getElementById("headProveedor");
            var bodyProveedor = document.getElementById("data_formCompra"); 

            $("#tableProveedorCompra").on("click","td input#selectBuscaProv",function (){
                var resRfc = this.value;
                //alert(resRfc);
                if (resRfc != "") {
                    headProveedor.classList.remove("headerError");
                    liProveedor.classList.add("active");
                    headProveedor.classList.add("active");
                    buscar_prod.classList.remove("noneView");
                    buscarProveedor(resRfc);
                    compraDirecta(resRfc);
                } else {
                    buscarProveedor();
                   compraDirecta();
                }
            });

            $(buscarProveedor());
            function buscarProveedor(resRfc) {
                $.ajax({
                    url: 'egresos-compraproveedor',
                    type: 'POST',
                    datatype: 'html',
                    data: {rfcConsulta: resRfc},
                    //data: {rfcConsulta: 'mahg900802ht0'},
                })
                .done(function(respuesta){
                $("#data_formCompra").html(respuesta);
                })
                .fail(function(){
                console.log("error");
                })
            };
            
        //¿Recibe factura antes o despues del pago?
            var facturaXml = document.getElementById("facturaxml");
            var viewFacturaxml = document.getElementById("viewFacturaxml"); 
            var validaXmlRfcEmisor = document.getElementById("validaXmlRfcEmisor"); 
            var validaXmlRfcReceptor = document.getElementById("validaXmlRfcReceptor"); 
            var validaXmlEstructure = document.getElementById("validaXmlEstructure"); 
            var btnResultadosXML = document.getElementById("btnResultadosXML");

            var facturapdf = document.getElementById("facturapdf");
            var viewFacturapdf = document.getElementById("viewFacturapdf");
            var formaPagoCompra = document.getElementById("formaPagoCompra");
            var metodoPagoCompra = document.getElementById("metodoPagoCompra");
            var formatoRecepcionPago = document.getElementById("formatoRecepcionPago");

            $('input[name="dataRecibeFactura"]').click(function(){
                headDecisionFactura.classList.remove("headerError");
                if(this.value == 'antesFact'){
                    btnXml.classList.remove("btnDisabled");
                    btnPdf.classList.remove("btnDisabled");
                    btnXml.classList.remove("btnError");   
                    btnPdf.classList.remove("btnError"); 
            
                    facturaXml.removeAttribute("disabled");
                    viewFacturaxml.classList.remove("disabled"); 
            
                    facturapdf.removeAttribute("disabled");
                    viewFacturapdf.classList.remove("disabled");
                } else{ 
                    btnXml.classList.remove("btnError");   
                    btnPdf.classList.remove("btnError"); 
                    btnXml.classList.add("btnDisabled");
                    btnPdf.classList.add("btnDisabled");
            
                    facturaXml.disabled = true;
                    viewFacturaxml.classList.add("disabled"); 
            
                    validaXmlRfcEmisor.classList.add("btnDisabled");
                    validaXmlRfcReceptor.classList.add("btnDisabled");
                    validaXmlEstructure.classList.add("btnDisabled");
            
                    facturapdf.disabled = true;
                    viewFacturapdf.classList.add("disabled");
                }
            });

            $(facturaXml).change(function(e){
                var ext = this.value.split('.').pop();
                if (ext != 'xml') {
                    errorLiHeadBody(this);
                    Error_extXml();
                    btnXml.classList.add("btnError");
                    this.value="";
                } else {
                    var checkProv = document.querySelector('input[name="selectBuscaProv"]:checked'); 
                    if (!checkProv) {
                        errorLiHeadBody(this);
                        liProveedor.classList.add("active");
                        headProveedor.classList.add("active");
                        headProveedor.classList.add("headerError");
                        bodyProveedor.classList.add("activeViewError");

                        btnXml.classList.add("btnError");
                        this.value="";
                    } else {
                        btnXml.classList.remove("btnError");
                        correctoLiHeadBody(this);
                        var partData = $(this).closest('div').serialize();
                        var file = $("#facturaxml")[0].files[0];
                        var data = new FormData();
                        data.append('data',partData);
                        data.append('file',file);
                        data.append('checkProvEmisor',checkProv.value);
                        data.append('rfcReceptorIG',rfcReceptorIG.value);

                        //estructura
                            $.ajax({
                                url: "egresos-verificaxmlEstructura",
                                type: "post",
                                data: data,
                                datatype: 'json',
                                processData: false,
                                contentType: false,
                                success: function(respuesta){
                                    if(respuesta == 'correcto'){
                                        var verifEmisor = false;
                                        var verifReceptor = false;
                                        //emisor
                                            $.ajax({
                                                url: "egresos-verificaxmlEmisor",
                                                type: "post",
                                                data: data,
                                                datatype: 'json',
                                                processData: false,
                                                contentType: false,
                                                success: function(respuesta){
                                                    if (checkProv.value == respuesta) {
                                                        verifEmisor = true;
                                                    } else {
                                                        verifEmisor = false;
                                                    }
                                                },
                                                async: false
                                            }); 

                                        //receptor
                                            $.ajax({
                                                url: "egresos-verificaxmlReceptor",
                                                type: "post",
                                                data: data,
                                                datatype: 'json',
                                                processData: false,
                                                contentType: false,
                                                success: function(respuesta){
                                                    if (rfcReceptorIG.value == respuesta) {
                                                        verifReceptor = true;
                                                    } else {
                                                        verifReceptor = false;
                                                    }
                                                },
                                                async: false
                                            });
                                        
                                        if (verifEmisor == true &&  verifReceptor == true) {
                                            correctoXml();
                                        } else {
                                            if (checkProv.value != verifEmisor) {
                                                incorrectoXml(); 
                                            }
                                            if (rfcReceptorIG.value != verifReceptor) {
                                                incorrectoXml(); 
                                            }
                                        }
                                    } else {
                                        incorrectoXml();
                                    }

                                    $(".verXMlGoogle").click(function(){
                                        $.ajax({
                                            url: "egresos-verxmlData",
                                            type: "post",
                                            data: data,
                                            datatype: 'json',
                                            processData: false,
                                            contentType: false,
                                            success: function(respuesta){
                                                $("#dataViewXML").html(respuesta);
                                            }
                                        });
                                    });
                                    $(".verErroresXMl").click(function(){
                                        $.ajax({
                                            url: "egresos-verxmlErrores",
                                            type: "post",
                                            data: data,
                                            datatype: 'json',
                                            processData: false,
                                            contentType: false,
                                            success: function(respuesta){
                                                $("#dataViewErroresXML").html(respuesta);
                                            }
                                        });
                                    });
                                }
                            });
                    }
                }
            });

            $(facturapdf).change(function(e){
                var valor = this.value;
                var input = this;
                if (facturaXml.value != '') {
                    validaPdfImg(e,valor,input,btnPdf);
                } else {
                    btnXml.classList.add("btnError");
                    btnPdf.classList.add("btnError");  
                    errorLiHeadBody(input); 
                }
            });

            $(formaPagoCompra).change(function(){
                if (formaPagoCompra.value != '') {
                    correctoLiHeadBody(this);
                    lblformaPagoCompra.classList.remove("errorSelect");
                    if (formaPagoCompra.value == '099') {
                        divFileRecepcion.classList.remove("noneView");
                        metodoPagoCompra.selectedIndex = 2;
                        metodoPagoCompra.value = 'PPD';
                        $('#metodoPagoCompra').prop('readonly', false);
                        $('select').material_select();
                        lblmetodoPagoCompra.classList.remove("errorSelect");
                    }
                    else{
                        divFileRecepcion.classList.add("noneView");
                        metodoPagoCompra.selectedIndex = 0;
                        metodoPagoCompra.value = '';
                        $('#metodoPagoCompra').prop('readonly', false);
                        $('select').material_select();
                    }
                } else {
                    errorLiHeadBody(this); 
                    lblformaPagoCompra.classList.add("errorSelect");
                }
            });
        
            $(metodoPagoCompra).change(function(){
                headDecisionFactura.classList.remove("headerError");
                lblmetodoPagoCompra.classList.remove("errorSelect");
            });
        
            $(formatoRecepcionPago).change(function(e){
                var valor = this.value;
                var input = this;
                if(this.value != ''){
                    validaPdfImg(e,valor,input,btnRecepcionPago);
                } else {
                    btnRecepcionPago.classList.add("btnError");
                    errorLiHeadBody(input);
                }
            });

            function validaPdfImg(e,valor,input,boton){       
                alert(valor);        
                let reader = new FileReader();
                reader.readAsDataURL(e.target.files[0]);
                var typeElemento = e.target.files[0].type;
                var tamano = e.target.files[0].size;
                if (typeElemento == "application/pdf" || typeElemento == "image/jpeg" || typeElemento == "image/jpg" || typeElemento == "image/png") {
                    if (tamano < 2000000) {
                        correctoLiHeadBody(input);
                        boton.classList.remove("btnError");    
                    } else {
                        input.value = '';
                        boton.classList.add("btnError");
                        errorLiHeadBody(input); 
                        if (media.matches) {
                            alert('La imagen no debe superar los 2MB');
                            //this.files[0].name = '';
                        } else {
                            Pesado();
                            //this.files[0].name = '';      
                        }
                    }                         
                } else {
                    input.value = '';
                    boton.classList.add("btnError");  
                    errorLiHeadBody(input);  
                    if (media.matches) {
                        alert('EL ARCHIVO DEBE ESTAR EN FORMATO PDF');
                        valor.files[0].name = '';
                    } else {
                        Error_extPdf();
                        //this.files[0].name = '';                
                    }
                }
            }

            function correctoXml(){
                validaXmlEstructure.classList.remove("btnError");
                validaXmlEstructure.classList.add("btnCorrecto");
                validaXmlEstructure.innerHTML = "&#xf058;";
                btnResultadosXML.classList.remove("btnDisabled");
                btnResultadosXML.classList.remove("btnError");
                btnResultadosXML.classList.add("btnCorrecto");
                btnResultadosXML.classList.remove("verErroresXMl");
                btnResultadosXML.classList.add("verXMlGoogle");
                btnResultadosXML.classList.add("modal-trigger");
                btnResultadosXML.setAttribute("href","#modalViewXML");
                btnResultadosXML.setAttribute("data-tooltip","Visualizar xml");
            }

            function incorrectoXml(){
                validaXmlEstructure.classList.remove("btnCorrecto");
                validaXmlEstructure.classList.add("btnError");
                validaXmlEstructure.innerHTML = "&#xf00d;";
                btnResultadosXML.classList.remove("btnDisabled");
                btnResultadosXML.classList.remove("btnCorrecto");
                btnResultadosXML.classList.add("btnError");
                btnResultadosXML.classList.remove("verXMlGoogle");
                btnResultadosXML.classList.add("verErroresXMl");
                btnResultadosXML.classList.add("modal-trigger");
                btnResultadosXML.setAttribute("href","#modalErroresXML");
                btnResultadosXML.setAttribute("data-tooltip","ver informe de errores");
            }

            

        //captura de pantalla 
            var capturaValidacion = document.getElementById("capturaValidacion");
            var inlineFrameExample = document.getElementById("inlineFrameExample");
            $(capturaValidacion).click(function(){
                html2canvas(inlineFrameExample,{
                    onrendered(canvas){
                        var imgCapt = canvas.toDataURL();
                        capturaValidacion.href = imgCapt;
                        capturaValidacion.download = 'screen.jpg';
                    }
                });
            });

        //Compras
            //Por previa requisicion
                (buscaReqPCompra());
                function buscaReqPCompra(requisicion) {   
                    $.ajax({
                        url:'egresos-buscarequisicioncom',
                        type: 'POST',
                        datatype: 'html',
                        data: {requisicion:requisicion}
                    }).done(function(respuesta){
                        $(detalleReqCompra).html(respuesta);
                    }).fail(function(respuesta){
                        console.log("error")
                    });
                }
            
                $("#requisicionesCompraTable tr").click(function(){
                    var requisicion = $(this).find("td").eq(0).html();
                    if (this.classList.contains("pendiente")) {
                        this.classList.remove("pendiente");
                        this.classList.add("revisado");
                    }
                    buscaReqPCompra(requisicion);
                });
            
            //Compra por previa cotizacion
                (buscaCotizacionPCompra());
                function buscaCotizacionPCompra(cotizacion) {   
                    $.ajax({
                        url:'egresos-buscacotizacioncomp',
                        type: 'POST',
                        datatype: 'html',
                        data: {requisicion:cotizacion}
                    }).done(function(respuesta){
                        $(detalleCotizacionCompra).html(respuesta);
                    }).fail(function(respuesta){
                        console.log("error")
                    });
                }
            
                $("#cotRequisicionCompReqTable tr").click(function(){
                    var cotizacion = $(this).find("td").eq(0).html();
                    if (this.classList.contains("pendiente")) {
                        this.classList.remove("pendiente");
                        this.classList.add("revisado");
                    }
                    buscaCotizacionPCompra(cotizacion);
                });
            
            //Compra directa
                $(compraDirecta());
                function compraDirecta(resRfc){
                    $.ajax({
                        url: 'egresos-buscaproducto',
                        type: 'POST',
                        datatype: 'html',
                        data: {consulta:resRfc},
                        //data: {consulta: 'hhg900802ht0'},
                    })
                    .done(function(respuesta){
                        $(divProductos).html(respuesta);
                    })
                    .fail(function(){
                        console.log("error");
                    });
                }

        //¿Recibes el producto o servicio antes o despues del pago?
            var recibePagoAntes = document.getElementById("recibePagoAntes");
            var recibePagoDespues = document.getElementById("recibePagoDespues");

        //PERIODICIDAD DE COMPRA
            var compraEventual = document.getElementById("compraEventual");
            var compraConstante = document.getElementById("compraConstante");
            var repeticionPeriodo = document.getElementById("repeticionPeriodo");
            var periodoDeterminado = document.getElementById("periodoDeterminado");
            var periodoIndeterminado = document.getElementById("periodoIndeterminado");
            var fechaFinPerido = document.getElementById("fechaFinPerido");
            var lblfechaFinPerido = document.getElementById("lblfechaFinPerido");

            $('input[name="periodicidadCompra"]').click(function(){
                headPeriodicidadCompra.classList.remove("headerError");
                if (this.value == 'eventual') {
                    //divrepeticionPeriodo.classList.add("noneView");
                    repeticionPeriodo.selectedIndex = 0;
                    repeticionPeriodo.value = '';
                    $('#repeticionPeriodo').prop('disabled', true);
                    $('select').material_select();
                    lblrepeticionPeriodo.classList.add("disabled");
                    liperiodoDeterminado.classList.add("disabled");
                    liperiodoIndeterminado.classList.add("disabled");
                    fechaFinPerido.disabled = true;
                } else {
                    //divrepeticionPeriodo.classList.remove("noneView");
                    $('#repeticionPeriodo').prop('disabled', false);
                    $('select').material_select();
                    lblrepeticionPeriodo.classList.remove("disabled");
                    liperiodoDeterminado.classList.remove("disabled");
                    liperiodoIndeterminado.classList.remove("disabled");
                }
            });
        
            $(repeticionPeriodo).change(function(){
                lblrepeticionPeriodo.classList.remove("errorSelect");
                headPeriodicidadCompra.classList.remove("headerError");
            });
        
            $('input[name="tipoPeriodo"]').click(function(){
                var periodicidadCompra = document.querySelector('input[name="periodicidadCompra"]:checked');
                if (periodicidadCompra) {
                    headPeriodicidadCompra.classList.remove("headerError");
                    divdatepicker.classList.remove("disabled");
                    if (this.value == 'determinado') {
                        lblfechaFinPerido.classList.remove("disabled");
                        fechaFinPerido.removeAttribute("disabled");
                    } else {
                        lblfechaFinPerido.classList.add("disabled");
                        fechaFinPerido.disabled = true;
                    }
                } else {
                    headPeriodicidadCompra.classList.add("headerError");
                }
            });
        
            $(fechaFinPerido).change(function(){
                if (this.value != '') {
                    lblfechaFinPerido.classList.remove("errorlabel");
                    headPeriodicidadCompra.classList.remove("headerError");
                } else {
                    lblfechaFinPerido.classList.add("errorlabel");
                    headPeriodicidadCompra.classList.add("headerError");
                }
            });

        //VARIABILIDAD DE IMPORTE
            var txtMonedaVImporte = document.getElementById("txtMonedaVImporte");
            var lblMonedaVImporte = document.getElementById("lblMonedaVImporte");
            var tableRangos = document.getElementById("tableRangos");
            var vImporteMin = document.getElementById("vImporteMin");
            var vImporteMax = document.getElementById("vImporteMax");

            $('input[name="variabilidadImporte"]').click(function(){
                if (this.value == 'importeFijo') {
                    txtMonedaVImporte.disabled = true;
                    txtMonedaVImporte.value = "";
                    lblMonedaVImporte.classList.add("disabled");
                    tableRangos.classList.add("disabled");
                    vImporteMin.disabled = true;
                    vImporteMin.value = "";
                    vImporteMax.disabled = true;
                    vImporteMax.value = "";
                } else {
                    txtMonedaVImporte.removeAttribute("disabled");
                    txtMonedaVImporte.value = "MXN-PESO MEXICANO";
                    lblMonedaVImporte.classList.remove("disabled");
                    tableRangos.classList.remove("disabled");
                    vImporteMin.removeAttribute("disabled");
                    vImporteMin.value = "0.00";
                    vImporteMax.removeAttribute("disabled");
                    vImporteMax.value = "0.00";
                }
            });

        //ubicacion
            var ubicaRegistrada = document.getElementById("ubicaRegistrada");
            $(buscaPuntoRecepcion());
            function buscaPuntoRecepcion(lugar) {
                $.ajax({
                    url: 'egresos-lugarecepcioncompra',
                    type: 'POST',
                    datatype: 'html',
                    data: {lugar:lugar}
                })
                .done(function(respuesta){
                $("#dirPuntoRecepcion").html(respuesta);
                })
                .fail(function(){
                console.log("error");
                })
            };

            $(txtBuscarLCompra).keyup(function name(params) {
                if (txtBuscarLCompra.value != "") {
                    buscaPuntoRecepcion(txtBuscarLCompra.value);
                } else {
                    buscaPuntoRecepcion();
                }
            });       


        function errorLiHeadBody(valor){
            $(valor).parents("li").find(".licollapsible").addClass("active");
            $(valor).parents("li").find(".collapsible-header").addClass("active");
            $(valor).parents("li").find(".collapsible-header").addClass("headerError");
            $(valor).parents("li").find(".collapsible-body").addClass("activeViewError");
        }

        function correctoLiHeadBody(valor){
            $(valor).parents("li").find(".licollapsible").removeClass("active");
            $(valor).parents("li").find(".collapsible-header").removeClass("active");
            $(valor).parents("li").find(".collapsible-header").removeClass("headerError");
            $(valor).parents("li").find(".collapsible-body").removeClass("activeViewError");
        }

});
function Error_extXml(){
    Push.create("EL ARCHIVO DEBE ESTAR EN FORMATO XML", {
        body: "SOS-México",
        icon: "vista/media/landing/logotipo/314g.jpg",
        timeout: 3000,
    });
};

function Error_extPdf(){
    Push.create("EL ARCHIVO DEBE ESTAR EN FORMATO PDF", {
        body: "SOS-México",
        icon: "vista/media/landing/logotipo/314g.jpg",
        timeout: 3000,
    });
};