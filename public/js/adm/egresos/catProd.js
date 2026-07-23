$(document).ready(function(){
    /*abrir catalogo de productos*/
        var txtbuscaProducto = document.getElementById("buscaProducto");
        $(buscarProducto());
        function buscarProducto(filtroBusquedaProd) {
            
        };
    
        $(txtbuscaProducto).keyup(function(){
            if (txtbuscaProducto.value != '') {
                var filtroBusquedaProd = this.value;
                /*$.ajax({
                    url: 'egresos-buscaproducto',
                    type: 'POST',
                    datatype: 'html',
                    data: {filtroBusquedaProd: filtroBusquedaProd,
                    },
                }).done(function(respuesta){
                    $("#listaProdc").html(respuesta);
                }).fail(function(){
                    console.log("error");
                });*/
                // Enviamos la variable de javascript a archivo.php
		        $.post("egresos-buscaproducto",{"filtroBusquedaProd": filtroBusquedaProd},function(respuesta){
                    $("#listaProdc").html(respuesta);
		        });
            }
        });

        //paginador
            $("#listaProd").pageMe({
                pageSelector:'#paginador',
                activeColor:'blue',
                prevText:'Prev',
                nextText:'Next',
                showPrevNext:true,
                hidePageNumbers:false,
                perPage:10
            });

            var _gaq = _gaq || [];_gaq.push(['_setAccount', 'UA-36251023-1']);_gaq.push(['_setDomainName', 'jqueryscript.net']);
            _gaq.push(['_trackPageview']);

            (function() {
              var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
              ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
              var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
            })();

    //formulario de alta de productos
        //identificadores
            //div 
                var btnAddLogotipo = document.getElementById("btnAddLogotipo");
                var divClaveProd = document.getElementById("relacionProvProd");
                var dataProvProducto = document.getElementById("dataProveedorProducto");
                var divbotoncheck = document.getElementById("btnAltaProdCheck");

            //input
                //text
                    var logotipo = document.getElementById("logotipoProd");
                    var catalogoSAT = document.getElementById("catalogoSAT");
                    var fechaAltaProd = document.getElementById("fechaAltaProd");
                    var productodesc = document.getElementById("producto_regCat");
                    var marca_regCat = document.getElementById("marca_regCat");
                    var stockmin = document.getElementById("stockmin_regCat");
                    var stockmax = document.getElementById("stockmax_regCat");
                    var numSerie_regCat = document.getElementById("numSerie_regCat");
                    var importado_regCat = document.getElementById("importado_regCat");
                    var claveProdProv = document.getElementById("claveProdProv");
                    var costoProdProv = document.getElementById("costoProdProv");
                //select
                    var selectClass = document.getElementById("txtClass");
                    var selectGenero = document.getElementById("txtGenero");
                    var costeo = document.getElementById("costeo_regCat");
                    var uEntrada = document.getElementById("uEntrada_regCat");
                    var uSalida = document.getElementById("uSalida_regCat");
                    var selectProv = document.getElementById("dataProv");

            //label
                var lblClassProd = document.getElementById("lblClassProd");
                var lblGeneroProd = document.getElementById("lblGeneroProd");
                var labelSAT = document.getElementById("lblcatalogoSAT");
                var lblfechaAltaProd = document.getElementById("lblfechaAltaProd");
                var lblProducto = document.getElementById("lblProducto");
                var lblMarca = document.getElementById("lblMarca");
                var lblStockmin = document.getElementById("lblStockmin");
                var lblStockmax = document.getElementById("lblStockmax");
                var lblSelectCosteo = document.getElementById("lblSelectCosteo");
                var lblSelectEnt = document.getElementById("lblSelectEnt");
                var lblSelectSal = document.getElementById("lblSelectSal");
                var lblnumSerie_regCat = document.getElementById("lblnumSerie_regCat");
                var lblimportado = document.getElementById("lblimportado");
                var lblSelectProveedor = document.getElementById("lblSelectProveedor");
                var lblClaveProdProv = document.getElementById("lblClaveProdProv");
                var lblCostoProdProv = document.getElementById("lblCostoProdProv");

            //table
                var tbody = document.getElementById("regClaveProd");

            //botones
                var btnAltaProducto1 = document.getElementById("btnAltaProducto1");
                var btnAltaProducto2 = document.getElementById("btnAltaProducto2");
        
        //buscar fegenero del producto
            $(buscarGenero());
            function buscarGenero(clasificacion,media) {
                $.ajax({
                    url: 'egresos-dataprodgenero',
                    type: 'POST',
                    datatype: 'html',
                    data: {
                        clasificacion: clasificacion,
                        media: media,
                    },
                })
                .done(function(respuesta){
                    $("#txtGenero").html(respuesta);
                })
                .fail(function(){
                console.log("error");
                })
            };

        //validaciones
            $(logotipo).change(function(e){
                //objeto de la clase reader
                let reader = new FileReader();
                //lectura de archivo subido y pasar al reader
                reader.readAsDataURL(e.target.files[0]);
            
                reader.onload =  function(){
                    btnAddLogotipo.classList.remove("btnError");
                    let imgPerfil = '<img class="circle responsive-img " src="'+reader.result+'">';
                    btnAddLogotipo.innerHTML = imgPerfil;
                };
            });    

            $(".switch").find("input[data-id=checkDiv]").on("change",function(){
                let checked = $(this).prop('checked');
                if (checked == true) {
                    btnAltaProducto1.classList.add("noneView");
                    dataProvProducto.classList.remove("noneView");
                } else {
                    btnAltaProducto2.classList.add("noneView");
                    dataProvProducto.classList.add("noneView");
                    btnAltaProducto1.classList.remove("noneView");
                }
            });

        //clasificacion del producto
            $(selectClass).change(function(){
                if (lblClassProd.classList.contains("errorlabel")) {
                    lblClassProd.innerHTML = "clasificaci&oacute;n";
                    lblClassProd.classList.remove("errorlabel");
                }
                var clasificacion = this.value;
                var dato = 1;
                if (clasificacion != '') {
                    buscarGenero(clasificacion,dato);
                } else {
                    buscarGenero(); 
                }
            });

            $(selectGenero).change(function(){
                if (lblGeneroProd.classList.contains("errorlabel")) {
                    lblGeneroProd.innerHTML = "clasificaci&oacute;n";
                    lblGeneroProd.classList.remove("errorlabel");
                }
            });

        //validacion de alta de producto sin proveedor
            $(btnAltaProducto1).click(function(){
                if (logotipo.value != '' && selectClass.value != '' && selectGenero.value != '' && catalogoSAT.value != '' 
                    && fechaAltaProd.value != ''&& productodesc.value != '' && marca_regCat.value != '' && stockmin.value != '' 
                    && stockmax.value != '' && costeo.value != '' && uEntrada.value != '' && uSalida.value != '' 
                    && numSerie_regCat.value != '' && importado_regCat.value != '') {

                    if (!isNaN(parseInt(stockmin.value)) && stockmin.value != 0 
                        && !isNaN(parseInt(stockmax.value)) && stockmax.value != 0) {
                            var smin = parseInt(stockmin.value);
                            var smax = parseInt(stockmax.value);
                            if (smax <= smin) {
                                errorStock();
                            } else {
                                envio_form();
                                Push.create("Producto registrado", {
                                    body: "SOS-México",
                                    icon: "vista/media/adm/errores/logoSOS.png",
                                    timeout: 3000,
                                });
                            }
                    } else {
                         
                        if (stockmin.value == 0) {
                            lblStockmin.innerHTML = "Stock invalido";
                            lblStockmin.classList.add("errorlabel");
                        } 
                        if (isNaN(parseInt(stockmin.value))) {
                            lblStockmin.classList.add("errorlabel");
                            lblStockmin.innerHTML = "ingresa stock en número";
                        } 
                        if (stockmax.value == 0) {
                            lblStockmax.innerHTML = "Stock invalido";
                            lblStockmax.classList.add("errorlabel");
                        } 
                        if (isNaN(parseInt(stockmax.value))) {
                            lblStockmax.classList.add("errorlabel");
                            lblStockmax.innerHTML = "ingresa stock en número";
                        } 
                    }
                }
                else{
                    erroresFunct();
                }
            });

        //validacion de informacion del provedor asignado           
            $("#addRelProvClave").click(function(){
                if (selectProv.value != '' && claveProdProv.value != '' &&
                    costoProdProv.value != '') {
                    if(btnAltaProducto2.classList.contains("noneView")){
                        btnAltaProducto2.classList.remove("noneView");
                    }
                    arrayProv = selectProv.value;
                    resArrayProv = arrayProv.split("-");
                    divClaveProd.classList.remove("noneView");
                    let renglon = document.createElement('tr');
                    tbody.appendChild(renglon);
                    //inputs

                    var fila =  '<input class="listaCompra" type="hidden" id="rfc_viewVentaPM2" name="dataProveedor[]" value="'+resArrayProv[0]+'">'+
                                '<input class="listaCompra" type="hidden" id="rfc_viewVentaPM2" name="dataClaveProdProv[]" value="'+claveProdProv.value+'">'+
                                '<input class="listaCompra" type="hidden" id="rfc_viewVentaPM2" name="dataClaveProdProv[]" value="'+costoProdProv.value+'">'+
                                //lista td
                                '<td>'+resArrayProv[1]+'</td>'+'<td>'+claveProdProv.value+'</td>'+'<td>'+costoProdProv.value+'</td>';
                    renglon.innerHTML = fila;
                }
                else {
                    Push.create("Completa los campos marcados en rojo", {
                        body: "SOS-México",
                        icon: "vista/media/adm/errores/logoSOS.png",
                        timeout: 3000,
                    });
                    if (selectProv.value == '') {
                        lblSelectProveedor.classList.add("errorlabel");
                    }
                    if (claveProdProv.value == '') {
                        lblClaveProdProv.classList.add("errorlabel");
                    }
                    if (costoProdProv.value == '') {
                        lblCostoProdProv.classList.add("errorlabel");
                    }
                }
            });
    
        //validacion de alta de producto con proveedor
            $("#btnAltaProducto2").click(function(){
                var hijotbody = $('#regClaveProd > *').length;
                if (logotipo.value != '' && selectClass.value != '' && selectGenero.value != '' && catalogoSAT.value != '' 
                    && fechaAltaProd.value != ''&& productodesc.value != '' && marca_regCat.value != '' && stockmin.value != '' 
                    && stockmax.value != '' && costeo.value != '' && uEntrada.value != '' && uSalida.value != '' 
                    && numSerie_regCat.value != '' && importado_regCat.value != '' && hijotbody != 0) {

                    if (!isNaN(parseInt(stockmin.value)) && stockmin.value != 0 
                        && !isNaN(parseInt(stockmax.value)) && stockmax.value != 0) {
                        var smin = parseInt(stockmin.value);
                        var smax = parseInt(stockmax.value);
                            if (smax <= smin) {
                                errorStock();
                            } else {
                                envio_form();
                                Push.create("Producto registrado", {
                                    body: "SOS-México",
                                    icon: "vista/media/adm/errores/logoSOS.png",
                                    timeout: 3000,
                                });
                            }
                    } else {
                         
                        if (stockmin.value == 0) {
                            lblStockmin.innerHTML = "Stock invalido";
                            lblStockmin.classList.add("errorlabel");
                        } 
                        if (isNaN(parseInt(stockmin.value))) {
                            lblStockmin.classList.add("errorlabel");
                            lblStockmin.innerHTML = "ingresa stock en número";
                        } 
                        if (stockmax.value == 0) {
                            lblStockmax.innerHTML = "Stock invalido";
                            lblStockmax.classList.add("errorlabel");
                        } 
                        if (isNaN(parseInt(stockmax.value))) {
                            lblStockmax.classList.add("errorlabel");
                            lblStockmax.innerHTML = "ingresa stock en número";
                        } 
                    }

                }
                else{
                    erroresFunct();
                    if (hijotbody == 0) {
                        Push.create("La imagen no debe superar los 2MB", {
                            body: "SOS-México",
                            icon: "public/imagenes/logos/logo_redux.jpg",
                            timeout: 3000,
                        });
                    } 
                }
            });

            function erroresFunct(){
                Push.create("Completa todos los campos", {
                    body: "SOS-México",
                    icon: "vista/media/adm/errores/logoSOS.png",
                    timeout: 3000,
                });
                if (logotipo.value == '') {
                    btnAddLogotipo.classList.add("btnError");
                }
                if (selectClass.value == '') {
                    lblClassProd.classList.add("errorlabel");
                } 
                if(selectGenero.value == ''){
                    lblGeneroProd.classList.add("errorlabel");
                }
                if (catalogoSAT.value == '') {
                    labelSAT.classList.add("errorlabel");
                } 
                if (isNaN(parseInt(catalogoSAT.value))) {
                    labelSAT.classList.add("errorlabel");
                    labelSAT.innerHTML = "C&Oacute;DIGO INVALIDO";
                } 
                if (fechaAltaProd.value == '') {
                    lblfechaAltaProd.classList.add("errorlabel");
                }
                if (productodesc.value == '') {
                    lblProducto.classList.add("errorlabel");
                } 
                if (marca_regCat.value == '') {
                    lblMarca.classList.add("errorlabel");
                } 
                if (stockmin.value == '') {
                    lblStockmin.classList.add("errorlabel");
                } 
                if (isNaN(parseInt(stockmin.value))) {
                    lblStockmin.classList.add("errorlabel");
                    lblStockmin.innerHTML = "ingresa stock en número";
                } 
                if (stockmax.value == '') {
                    lblStockmax.classList.add("errorlabel");
                } 
                if (isNaN(parseInt(stockmax.value))) {
                    lblStockmax.classList.add("errorlabel");
                    lblStockmax.innerHTML = "ingresa stock en número";
                } 
                if (costeo.value == '') {
                    lblSelectCosteo.classList.add("errorlabel");
                } 
                if (uEntrada.value == '') {
                    lblSelectEnt.classList.add("errorlabel");
                } 
                if (uSalida.value == '') {
                    lblSelectSal.classList.add("errorlabel");
                } 
                if (numSerie_regCat.value == '') {
                    lblnumSerie_regCat.classList.add("errorlabel");
                } 
                if (importado_regCat.value == '') {
                    lblimportado.classList.add("errorlabel");
                } 
            }

            function envio_form(){
                document.formAddProdCat.target = "_self";
                document.formAddProdCat.action = "egresos-agregaproducto";
                document.formAddProdCat.submit();
            }

        //funciones especiales de validacion
            $(catalogoSAT).keyup(function(){
                if (isNaN(parseInt(this.value))) {
                    labelSAT.classList.add("errorlabel");
                    labelSAT.innerHTML = "C&Oacute;DIGO INVALIDO"
                } else if (this.value.length < 8) {
                    labelSAT.classList.add("errorlabel");
                    labelSAT.innerHTML = "C&Oacute;DIGO INVALIDO"
                } else if (this.value.length >= 8) {
                    labelSAT.classList.add("errorlabel");
                    labelSAT.innerHTML = "C&Oacute;DIGO INVALIDO"
                    this.value = this.value.slice(0,8);
                } else if (labelSAT.classList.contains("errorlabel")) {
                    labelSAT.innerHTML = "C&oacute;digo SAT";
                    labelSAT.classList.remove("errorlabel");
                }

            });

            $(catalogoSAT).keypress(function(e){
                if (!validaNumeros(e)) {
                    e.preventDefault();
                }
            });

            $(fechaAltaProd).change(function() {
                if (lblfechaAltaProd.classList.contains("errorlabel")) {
                    lblfechaAltaProd.innerHTML = "Fecha de Alta";
                    lblfechaAltaProd.classList.remove("errorlabel");
                }
            });
        
            $(productodesc).keyup(function() {
                if (lblProducto.classList.contains("errorlabel")) {
                    lblProducto.innerHTML = "Concepto / Descripci&oacute;n";
                    lblProducto.classList.remove("errorlabel");
                }
            });

            $(marca_regCat).keyup(function() {
                if (lblMarca.classList.contains("errorlabel")) {
                    lblMarca.innerHTML = "Marca";
                    lblMarca.classList.remove("errorlabel");
                }
            });
        
            $(stockmin).keyup(function() {
                var smin = parseInt(this.value);
                var smax = parseInt(stockmax.value);
                if (isNaN(parseInt(this.value))) {
                    lblStockmin.classList.add("errorlabel");
                    lblStockmin.innerHTML = "ingresa stock en número";
                } else if (this.value == 0) {
                    lblStockmin.innerHTML = "Stock invalido";
                    lblStockmin.classList.add("errorlabel");
                } else if (smin >= smax && stockmax.value != '' && stockmax.value > 0) {
                    errorStock();
                }
                else if (lblStockmin.classList.contains("errorlabel")) {
                    stockCorrecto();
                }
            });
        
            $(stockmax).keyup(function() {
                var smin = parseInt(stockmin.value);
                var smax = parseInt(this.value);
                if (isNaN(parseInt(this.value))) {
                    lblStockmax.classList.add("errorlabel");
                    lblStockmax.innerHTML = "ingresa stock en número";
                } else if (this.value == 0) {
                    lblStockmax.innerHTML = "Stock invalido";
                    lblStockmax.classList.add("errorlabel");
                } else if ( smax <= smin && stockmin.value != '' && stockmin.value > 0) {
                    errorStock();
                } else if (lblStockmax.classList.contains("errorlabel")) {
                    stockCorrecto();
                }
            });
        
            $(costeo).change(function() {
                if (lblSelectCosteo.classList.contains("errorlabel")) {
                    lblSelectCosteo.innerHTML = "Costeo";
                    lblSelectCosteo.classList.remove("errorlabel");
                }
            });
        
            $(uEntrada).change(function() {
                if (lblSelectEnt.classList.contains("errorlabel")) {
                    lblSelectEnt.innerHTML = "Unidad de Entrada";
                    lblSelectEnt.classList.remove("errorlabel");
                }
            });
        
            $(uSalida).change(function() {
                if (lblSelectSal.classList.contains("errorlabel")) {
                    lblSelectSal.innerHTML = "Unidad de Salida";
                    lblSelectSal.classList.remove("errorlabel");
                }
            });
 
            $(numSerie_regCat).keyup(function() {
                if (lblnumSerie_regCat.classList.contains("errorlabel")) {
                    lblnumSerie_regCat.innerHTML = "N&uacute;mero de serie";
                    lblnumSerie_regCat.classList.remove("errorlabel");
                }
            });
        
            $(importado_regCat).change(function() {
                if (lblimportado.classList.contains("errorlabel")) {
                    lblimportado.innerHTML = "Importado";
                    lblimportado.classList.remove("errorlabel");
                }
            });

            $(selectProv).change(function() {
                if (lblSelectProveedor.classList.contains("errorlabel")) {
                    lblSelectProveedor.innerHTML = "Proveedor";
                    lblSelectProveedor.classList.remove("errorlabel");
                }
            });

            $(claveProdProv).keyup(function() {
                if (lblClaveProdProv.classList.contains("errorlabel")) {
                    lblClaveProdProv.innerHTML = "Clave asignada";
                    lblClaveProdProv.classList.remove("errorlabel");
                }
            });   

            $(costoProdProv.value == '').keyup(function(){
                if (lblCostoProdProv.classList.contains("errorlabel")) {
                    lblCostoProdProv.classList.remove("errorlabel");
                }
            });
            
            function validaNumeros(e){
                var clave = e.charCode;
                return clave >=48 && clave <= 57; 
            };

            function errorStock(){
                lblStockmin.innerHTML = "Stock invalido";
                lblStockmin.classList.add("errorlabel");
                lblStockmax.innerHTML = "Stock invalido";
                lblStockmax.classList.add("errorlabel");
            }

            function stockCorrecto(){
                lblStockmin.innerHTML = "Stock m&iacute;nimo";
                lblStockmin.classList.remove("errorlabel");
                lblStockmax.innerHTML = "Stock m&aacute;ximo";
                lblStockmax.classList.remove("errorlabel");
            }
});

