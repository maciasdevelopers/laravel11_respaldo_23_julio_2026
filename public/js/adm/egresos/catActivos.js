$(document).ready(function(){
    //catalogo
        //formulario
            //lista de clasificacion de activos
                var tableClassAct = document.getElementById("tableClassAct");
                var cActConcepto = document.getElementById("cActConcepto");
                var cActContable = document.getElementById("cActContable");
                var cActFiscal = document.getElementById("cActFiscal");
                var agregaClassActivo = document.getElementById("agregaClassActivo");

                $(listaAct());
                function listaAct() {
                    $.ajax({
                        url: 'egresos-listclasact',
                    }).done(function(respuesta){
                        $("#tbodyClassAct").html(respuesta);
                    }).fail(function(){
                        Push.create("Fallo al descargar esta informacion", {
                            body: "SOS-México",
                            icon: "vista/media/adm/errores/logoSOS.png",
                            timeout: 3000,
                        });
                    });
                };

                $(agregaClassActivo).click(function(){
                    if (cActConcepto.value != '' && cActContable.value != '' && cActFiscal.value != '') {
                        $.ajax({
                            url: 'egresos-regclassact',
                            type: 'POST',
                            datatype: 'html',
                            data:{
                                cActConcepto: cActConcepto.value,
                                cActContable: cActContable.value,
                                cActFiscal: cActFiscal.value
                            }
                        }).done(function(respuesta){
                            if (respuesta == 'registrado') {
                                cActConcepto.value = '';
                                cActContable.value = ''; 
                                cActFiscal.value = '';
                                listaAct().load();
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
    
                        if (cActConcepto.value == '') {
                            cActConcepto.classList.add("error");
                        }
    
                        if (cActContable.value == '') {
                            cActContable.classList.add("error");
                        }
                        if (cActFiscal.value == '') {
                            cActFiscal.classList.add("error");
                        }
                    }
                });

            //contenido
                //div
                    var btnAddLogotipoActivo = document.getElementById("btnAddLogotipoActivo");
                    var relacionActProd = document.getElementById("relacionActProd");
                
                //inputs
                    var logotipoActivo = document.getElementById("logotipoActivo");
                    var fechaAltaActivo = document.getElementById("fechaAltaActivo");
                    var uso_cfdi = document.getElementById("uso_cfdi");
                    var catalogoSATActivo = document.getElementById("catalogoSATActivo");
                    var activo_regCat = document.getElementById("activo_regCat");
                    var marca_regCatAct = document.getElementById("marca_regCatAct");
                    var classActivo = document.getElementById("classActivo"); 
                    var deprecContable = document.getElementById("deprecContable");
                    var deprecFiscal = document.getElementById("deprecFiscal");
                    var dataProvAct = document.getElementById("dataProvAct");
                    var claveActProv = document.getElementById("claveActProv");
                    var costoActProv = document.getElementById("costoActProv");

                //labels
                    var lblfechaAltaActivo = document.getElementById("lblfechaAltaActivo");
                    var lblDistribucionActivo = document.getElementById("lblDistribucionActivo");
                    var lblcatalogoSATActivo = document.getElementById("lblcatalogoSATActivo");
                    var lblActivo = document.getElementById("lblActivo");
                    var lblMarcaActivo = document.getElementById("lblMarcaActivo");
                    var lblResponsable = document.getElementById("lblResponsable");
                    var lblClassActivo = document.getElementById("lblClassActivo");
                    var lblDeprecContable = document.getElementById("lblDeprecContable");
                    var lblDeprecFiscal = document.getElementById("lblDeprecFiscal");
                    var lblProveedorActivo = document.getElementById("lblProveedorActivo");
                    var lblClaveActProv = document.getElementById("lblClaveActProv");
                    var lblCostoACProv = document.getElementById("lblCostoACProv");
                
                //boton
                    var addRelProvFijo = document.getElementById("addRelProvFijo");
                    var btnAltafijo = document.getElementById("btnAltafijo");

                //table 
                    var regClaveAct = document.getElementById("regClaveAct"); 

                //validaciones
                    $(addRelProvFijo).click(function(){
                        if (dataProvAct.value != '' &&
                            claveActProv.value != '' &&
                            costoActProv.value != '') {
                            if (btnAltafijo.classList.contains("noneView")) {
                                btnAltafijo.classList.remove("noneView");
                            }
                            arrayProv = dataProvAct.value;
                            resArray = arrayProv.split("-");
                            relacionActProd.classList.remove("noneView");
                            let renglon = document.createElement("tr");
                            regClaveAct.appendChild(renglon);
                            var fila =  '<input class="listaCompra" type="hidden" name="dataProvAct[]" value="'+resArray[0]+'">'+
                                        '<input class="listaCompra" type="hidden" name="dataClaveActProv[]" value="'+claveActProv.value+'">'+
                                        '<input class="listaCompra" type="hidden" name="dataCostoActProv[]" value="'+costoActProv.value+'">'+
                                        //lista td
                                            '<td>'+resArray[1]+'</td>'+'<td>'+claveActProv.value+'</td>'+'<td>'+costoActProv.value+'</td>';
                             renglon.innerHTML = fila;
                        } else {
                            Push.create("Completa los campos marcados en rojo", {
                                body: "SOS-México",
                                icon: "vista/media/adm/errores/logoSOS.png",
                                timeout: 3000,
                            });
                            if(dataProvAct.value == ''){
                                lblProveedorActivo.classList.add("errorlabel");
                            }
                            
                            if(claveActProv.value == ''){
                                lblClaveActProv.classList.add("errorlabel");
                            }
                            
                            if(costoActProv.value == ''){
                                lblCostoACProv.classList.add("errorlabel");
                            }
                            
                        }
                    });

                    $(btnAltafijo).click(function(){
                        var hijotbody = $('#regClaveAct > *').length;
                        if (uso_cfdi.value != '' &&
                            catalogoSATActivo.value != '' &&
                            classActivo.value != '' &&
                            deprecContable.value != '' &&
                            deprecFiscal.value != '' &&
                            hijotbody != 0) {
                                document.formAddActCat.target= "_self"; 
                                document.formAddActCat.action = "egresos-regactivofijo";
                                document.formAddActCat.submit();
                        } else {
        
                            Push.create("Completa todos los campos", {
                                body: "SOS-México",
                                icon: "vista/media/adm/errores/logoSOS.png",
                                timeout: 3000,
                            });
        
                            if (uso_cfdi.value == ''){
                                lblDistribucionActivo.classList.add("errorlabel");
                                lblDistribucionActivo.innerHTML = "Seleccione distribucion";
                            }
        
                            if (catalogoSATActivo.value == ''){
                                lblcatalogoSATActivo.innerHTML = "C&oacute;digo SAT invalido";
                                lblcatalogoSATActivo.classList.add("errorlabel");
                            }
        
                            if (isNaN(parseInt(catalogoSATActivo.value))) {
                                lblcatalogoSATActivo.classList.add("errorlabel");
                                lblcatalogoSATActivo.innerHTML = "C&oacute;digo SAT invalido";
                            }
        
                            if (classActivo.value == '') {
                                lblClassActivo.classList.add("errorlabel");
                                lblClassActivo.innerHTML = "Clasificaci&oacute;n incorrecta";
                            } 
                            
                            if (deprecContable.value == ''){
                                lblDeprecContable.classList.add("errorlabel");
                                lblDeprecContable.innerHTML = "Depreciaci&oacute;n contable incorrecta";
                            }
        
                            if (deprecFiscal.value == ''){
                                lblDeprecFiscal.classList.add("errorlabel");
                                lblDeprecFiscal.innerHTML = "Depreciaci&oacute;n fiscal incorrecta";
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
        
                    $(logotipoActivo).change(function(e){
                        //objeto de la clase reader
                        let reader = new FileReader();
                        //lectura de archivo subido y pasar al reader
                        reader.readAsDataURL(e.target.files[0]);
                    
                        reader.onload =  function(){
                            btnAddLogotipoActivo.classList.remove("btnError");
                            let imgPerfil = '<img class="circle responsive-img " src="'+reader.result+'">';
                            btnAddLogotipoActivo.innerHTML = imgPerfil;
                        };
                    });
        
                    $(fechaAltaActivo).change(function(){
                        if (lblfechaAltaActivo.classList.contains("errorlabel")){
                            lblfechaAltaActivo.classList.remove("errorlabel");
                            lblfechaAltaActivo.innerHTML = "Fecha de Alta"
                        };
                    });
        
                    $(uso_cfdi).change(function(){
                        if (lblDistribucionActivo.classList.contains("errorlabel")){
                            lblDistribucionActivo.classList.remove("errorlabel");
                            lblDistribucionActivo.innerHTML = "Distribuci&oacute;n"
                        };
                    });
        
                    $(catalogoSATActivo).keyup(function(){
                        if (isNaN(parseInt(this.value))) {
                            lblcatalogoSATActivo.classList.add("errorlabel");
                            lblcatalogoSATActivo.innerHTML = "C&oacute;digo SAT invalido";
                        } else if (this.value == 0) {
                            lblcatalogoSATActivo.innerHTML = "C&oacute;digo SAT invalido";
                            lblcatalogoSATActivo.classList.add("errorlabel");
                        } else if (lblcatalogoSATActivo.classList.contains("errorlabel")) {
                            lblcatalogoSATActivo.classList.remove("errorlabel");
                            lblcatalogoSATActivo.innerHTML = "C&oacute;digo SAT";
                        }
                    });
        
                    $(catalogoSATActivo).keypress(function(e){
                        if (!validaNumeros(e)) {
                            e.preventDefault();
                        }
                    });
        
                    $(activo_regCat).keyup(function(){
                        if (lblActivo.classList.contains("errorlabel")){
                            lblActivo.classList.remove("errorlabel");
                            lblActivo.innerHTML = "Concepto / Descripción"
                        };
                    });
                    
                    $(marca_regCatAct).keyup(function(){
                        if (lblMarcaActivo.classList.contains("errorlabel")){
                            lblMarcaActivo.classList.remove("errorlabel");
                            lblMarcaActivo.innerHTML = "Marca"
                        };
                    });
        
                    $(classActivo).keyup(function(){
                        if (lblClassActivo.classList.contains("errorlabel")) {
                            lblClassActivo.classList.remove("errorlabel");
                            lblClassActivo.innerHTML = "Clasificaci&oacute;n incorrecta";
                        }
                    }); 
         
                    $(deprecContable).keyup(function(){
                        if (lblDeprecContable.classList.contains("errorlabel")) {
                            lblDeprecContable.classList.remove("errorlabel");
                            lblDeprecContable.innerHTML = "Depreciaci&oacute;n contable";
                        }
                    });
        
                    $(deprecFiscal).keyup(function(){
                        if (lblDeprecFiscal.classList.contains("errorlabel")) {
                            lblDeprecFiscal.classList.remove("errorlabel");
                            lblDeprecFiscal.innerHTML = "Depreciaci&oacute;n fiscal incorrecta";
                        }
                    });

                    $(dataProvAct).change(function(){
                        if (lblProveedorActivo.classList.contains("errorlabel")) {
                            lblProveedorActivo.classList.remove("errorlabel");
                        }
                    });
                    
                    $(claveActProv).keyup(function(){
                        if (lblClaveActProv.classList.contains("errorlabel")) {
                            lblClaveActProv.classList.remove("errorlabel");
                        }
                    });
                    
                    $(costoActProv).keyup(function(){
                        if (isNaN(parseInt(this.value))) {
                            lblCostoACProv.classList.add("errorlabel");
                            lblCostoACProv.innerHTML = "Costo invalido";
                        } else if(this.value == 0){
                            lblCostoACProv.classList.add("errorlabel");
                            lblCostoACProv.innerHTML = "Costo invalido";
                        } else if(lblCostoACProv.classList.contains("errorlabel")){
                            lblCostoACProv.classList.remove("errorlabel");
                            lblCostoACProv.innerHTML = "Costo promedio";
                        }
                    });
        
                    $(costoActProv).keypress(function(e){
                        if (!validaNumeros(e)) {
                            e.preventDefault();
                        }
                    });
                    function validaNumeros(e){
                        var clave = e.charCode;
                        return clave >=48 && clave <= 57;
                    }
});