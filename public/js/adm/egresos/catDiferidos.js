$(document).ready(function(){
    //catalogo
    
    //formulario
        //lista de clasificacion de activos diferidos
            var cActConceptoDif = document.getElementById("cActConceptoDif");
            var cActContableDif = document.getElementById("cActContableDif");
            var cActFiscalDif = document.getElementById("cActFiscalDif");
            var agregaClassActivoDif = document.getElementById("agregaClassActivoDif");

            $(listaActDif());
            function listaActDif(){
                $.ajax({
                    url: 'egresos-listclasdif',
                }).done(function(respuesta){
                    $("#tbodyClassActDif").html(respuesta);
                }).fail(function(){
                    Push.create("Fallo al descargar esta información",{
                        body: "SOS-México",
                        icon: "vista/media/adm/errores/logoSOS.png",
                        timeout: 3000,
                    });
                });
            };
            
            $(agregaClassActivoDif).click(function(){
                if (cActConceptoDif.value != '' && cActContableDif.value != '' && cActFiscalDif.value != ''){
                    $.ajax({
                        url: 'egresos-regclassdif',
                        type: 'POST',
                        datatype: 'html',
                        data:{
                            cDifConcepto: cActConceptoDif.value,
                            cDifContable: cActContableDif.value,
                            cDifFiscal:  cActFiscalDif.value,
                        }
                    }).done(function(respuesta){
                        if (respuesta == 'registrado') {
                            cActConceptoDif.value = '';
                            cActContableDif.value = '';
                            cActFiscalDif.value = '';
                            listaActDif().load();
                        }
                    }).fail(function(){
                        Push.create("Fallo al registrar esta informacion", {
                            body: "SOS-México",
                            icon: "vista/media/adm/errores/logoSOS.png",
                            timeout: 3000,
                        });
                    });
                } else {
                    Push.create("Completa todos los campos", {
                        body: "SOS-México",
                        icon: "vista/media/adm/errores/logoSOS.png",
                        timeout: 3000,
                    });
                }
            
                if(cActConceptoDif.value == ''){
                    cActConceptoDif.classList.add("error");
                }
            
                if(cActContableDif.value == ''){
                    cActContableDif.classList.add("error");
                }
            
                if(cActFiscalDif.value == ''){
                    cActFiscalDif.classList.add("error");
                }
            });

        //contenido
            //div
                var relacionDifProd = document.getElementById("relacionDifProd");

            //inputs
                var uso_cfdiDif = document.getElementById("uso_cfdiDif");
                var catalogoSATDif = document.getElementById("catalogoSATDif");
                var classActDif = document.getElementById("classActDif"); 
                var amortContable = document.getElementById("amortContable"); 
                var amortFiscal = document.getElementById("amortFiscal"); 
                var dataProvDif = document.getElementById("dataProvDif");
                var claveDifProv = document.getElementById("claveDifProv"); 
                var costoDifProv = document.getElementById("costoDifProv");

            //labels
                var lblDistribucionDif = document.getElementById("lblDistribucionDif");
                var lblcatalogoSATDif = document.getElementById("lblcatalogoSATDif");
                var lblClassActDif = document.getElementById("lblClassActDif");
                var lblAmortContable = document.getElementById("lblAmortContable");
                var lblAmortFiscal = document.getElementById("lblAmortFiscal");
                var lblProveedorDif = document.getElementById("lblProveedorDif");
                var lblClaveDifProv = document.getElementById("lblClaveDifProv");
                var lblCostoDifProv = document.getElementById("lblCostoDifProv");

            //boton
                var addRelProvDif = document.getElementById("addRelProvDif");
                var btnAltaDif = document.getElementById("btnAltaDif");

            //table
                var regClaveDif = document.getElementById("regClaveDif");

            //validaciones
                $(addRelProvDif).click(function(){
                    if (dataProvDif.value != '' &&
                        claveDifProv.value != '' &&
                        costoDifProv.value != '') {
                        if (btnAltaDif.classList.contains("noneView")) {
                            btnAltaDif.classList.remove("noneView");
                        }
                        arrayProv = dataProvDif.value;
                        resArray = arrayProv.split("-");
                        relacionDifProd.classList.remove("noneView");
                        let renglon = document.createElement("tr");
                        regClaveDif.appendChild(renglon);
                        var fila =  '<input class="listaCompra" type="hidden" id="rfc_viewVentaPM2" name="dataDifProv[]" value="'+resArray[0]+'">'+
                                    '<input class="listaCompra" type="hidden" id="rfc_viewVentaPM2" name="dataClaveDifProv[]" value="'+claveDifProv.value+'">'+
                                    '<input class="listaCompra" type="hidden" id="rfc_viewVentaPM2" name="dataCostoDifProv[]" value="'+costoDifProv.value+'">'+
                                    //lista td
                                    '<td>'+resArray[1]+'</td>'+'<td>'+claveDifProv.value+'</td>'+'<td>'+costoDifProv.value+'</td>';
                        renglon.innerHTML = fila;
                    } else {
                        Push.create("Completa los campos marcados en rojo", {
                            body: "SOS-México",
                            icon: "vista/media/adm/errores/logoSOS.png",
                            timeout: 3000,
                        });

                        if(dataProvDif.value == ''){
                            lblProveedorDif.classList.add("errorlabel");
                        }

                        if(claveDifProv.value == ''){
                            lblClaveDifProv.classList.add("errorlabel");
                        }

                        if(costoDifProv.value == ''){
                            lblCostoDifProv.classList.add("errorlabel");
                        }
                    }
                });

                $(btnAltaDif).click(function(){
                    var hijotbody = $('#regClaveDif > *').length;
                    if (uso_cfdiDif.value != '' &&
                        catalogoSATDif.value != '' &&
                        classActDif.value != '' &&
                        amortContable.value != '' &&
                        amortFiscal.value != '' &&
                        hijotbody != 0) {
                        document.formAddDifCat.target= "_self"; 
                        document.formAddDifCat.action = "egresos-regactivodife";
                        document.formAddDifCat.submit();
                    } else {
                        Push.create("Completa todos los campos", {
                            body: "SOS-México",
                            icon: "vista/media/adm/errores/logoSOS.png",
                            timeout: 3000,
                        });
                    
                        if (uso_cfdiDif.value == ''){
                            lblDistribucionDif.classList.add("errorlabel");
                            lblDistribucionDif.innerHTML = "Seleccione distribucion";
                        }
                    
                        if (catalogoSATDif.value == ''){
                            lblcatalogoSATDif.innerHTML = "C&oacute;digo SAT invalido";
                            lblcatalogoSATDif.classList.add("errorlabel");
                        }
                    
                        if (isNaN(parseInt(catalogoSATDif.value))) {
                            lblcatalogoSATDif.classList.add("errorlabel");
                            lblcatalogoSATDif.innerHTML = "C&oacute;digo SAT invalido";
                        }
                    
                        if(classActDif.value == ''){
                            lblClassActDif.classList.add("errorlabel");
                            lblClassActDif.innerHTML = "Clasificaci&oacute;n incorrecta";
                        }
                    
                        if(amortContable.value == ''){
                            lblAmortContable.classList.add("errorlabel");
                            lblAmortContable.innerHTML = "Amortizaci&oacute;n contable incorrecta";
                        }

                        if(amortFiscal.value == ''){
                            lblAmortFiscal.classList.add("errorlabel");
                            lblAmortFiscal.innerHTML = "Amortizaci&oacute;n fiscal incorrecta";
                        }

                        if (hijotbody == 0) {
                            Push.create("Asigna proveedor a tu activo", {
                                body: "SOS-México",
                                icon: "vista/media/adm/errores/logoSOS.png",
                                timeout: 3000,
                            });
                        }
                    }
                });

                $(uso_cfdiDif).change(function(){
                    if (lblDistribucionDif.classList.contains("errorlabel")){
                        lblDistribucionDif.classList.remove("errorlabel");
                        lblDistribucionDif.innerHTML = "Distribuci&oacute;n"
                    };
                });

                $(catalogoSATDif).keyup(function(){
                    if (isNaN(parseInt(this.value))) {
                        lblcatalogoSATDif.classList.add("errorlabel");
                        lblcatalogoSATDif.innerHTML = "Ingresa C&oacute;digo SAT en número";
                    } else if (this.value == 0) {
                        lblcatalogoSATDif.innerHTML = "C&oacute;digo SAT invalido";
                        lblcatalogoSATDif.classList.add("errorlabel");
                    } else if (lblcatalogoSATDif.classList.contains("errorlabel")) {
                        lblcatalogoSATDif.classList.remove("errorlabel");
                        lblcatalogoSATDif.innerHTML = "C&oacute;digo SAT";
                    }
                });

                $(catalogoSATDif).keypress(function(e){
                    if (!validaNumeros(e)) {
                        e.preventDefault();
                    }
                });

                $(classActDif).keyup(function(){
                    if (lblClassActDif.classList.contains("errorlabel")) {
                        lblClassActDif.classList.remove("errorlabel");
                        lblClassActDif.innerHTML = "Clasificaci&oacute;n incorrecta";
                    }
                });

                $(amortContable).keyup(function(){
                    if (lblAmortContable.classList.contains("errorlabel")) {
                        lblAmortContable.classList.remove("errorlabel");
                        lblAmortContable.innerHTML = "Amortizaci&oacute;n contable";
                    }
                });

                $(amortFiscal).keyup(function(){
                    if (lblAmortFiscal.classList.contains("errorlabel")) {
                        lblAmortFiscal.classList.remove("errorlabel");
                        lblAmortFiscal.innerHTML = "Amortizaci&oacute;n fiscal";
                    }
                });

                $(dataProvDif).change(function(){
                    if (lblProveedorDif.classList.contains("errorlabel")) {
                        lblProveedorDif.classList.remove("errorlabel");
                    }
                });

                $(claveDifProv).keyup(function(){
                    if (lblClaveDifProv.classList.contains("errorlabel")) {
                        lblClaveDifProv.classList.remove("errorlabel");
                    }
                });

                $(costoDifProv).keyup(function(){
                    if (isNaN(parseInt(this.value))) {
                        lblCostoDifProv.classList.add("errorlabel");
                        lblCostoDifProv.innerHTML = "Costo invalido";
                    } else if (this.value == 0) {
                        lblCostoDifProv.classList.add("errorlabel");
                        lblCostoDifProv.innerHTML = "Costo invalido";
                    } else if (lblCostoDifProv.classList.contains("errorlabel")) {
                        lblCostoDifProv.classList.remove("errorlabel");
                        lblCostoDifProv.innerHTML = "Costo promedio";
                    }
                });

                $(costoDifProv).keypress(function(e){
                    if(!validaNumeros(e)){
                        e.preventDefault();
                    }
                });

                function validaNumeros(e){
                    var clave = e.charCode;
                    return clave >= 48 && clave <=57;
                };

});