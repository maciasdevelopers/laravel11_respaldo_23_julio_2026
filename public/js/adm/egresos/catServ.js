$(document).ready(function(){
    //formulario de alta de servicios
        //identificadores
            //div
                var btnAddLogotipoServ = document.getElementById("btnAddLogotipoServ");
            
            //inputs
                var logotipoServ = document.getElementById("logotipoServ");
                var fechaAltaServ = document.getElementById("fechaAltaServ");
                var txtClassServ = document.getElementById("txtClassServ");
                var catalogoSATServ = document.getElementById("catalogoSATServ");
                var servicio_regCat = document.getElementById("servicio_regCat");
                var dataProvServ = document.getElementById("dataProvServ");
                var claveServProv = document.getElementById("claveServProv");
                var costoServProv = document.getElementById("costoServProv");
            
            //labels
                var lblfechaAltaServ = document.getElementById("lblfechaAltaServ");
                var lblClassServ = document.getElementById("lblClassServ");
                var lblcatalogoSATServ = document.getElementById("lblcatalogoSATServ");
                var lblServicio = document.getElementById("lblServicio");
                var lblProveedorServ = document.getElementById("lblProveedorServ");
                var lblClaveServProv = document.getElementById("lblClaveServProv");
                var lblCostoServProv = document.getElementById("lblCostoServProv");

            //boton
                var registraServ = document.getElementById("registraServ");

            //table
                var regClaveServ = document.getElementById("regClaveServ");

        //validaciones
            //validacion de informacion del provedor asignado           
                $("#addRelProvClaveServ").click(function(){
                    if (dataProvServ.value != '' && claveServProv.value != '' &&
                        costoServProv.value != '') {
                        if(registraServ.classList.contains("noneView")){
                            registraServ.classList.remove("noneView");
                        }
                        arrayProvserv = dataProvServ.value;
                        resArrayProvServ = arrayProvserv.split("-");
                        relacionServProd.classList.remove("noneView");
                        let renglon = document.createElement('tr');
                        regClaveServ.appendChild(renglon);
                        //input
                        var fila =  '<input class="listaCompra" type="hidden" id="rfc_viewVentaPM2" name="dataProveedor[]" value="'+resArrayProvServ[0]+'">'+
                                    '<input class="listaCompra" type="hidden" id="rfc_viewVentaPM2" name="dataClaveProdProv[]" value="'+claveServProv.value+'">'+
                                    '<input class="listaCompra" type="hidden" id="rfc_viewVentaPM2" name="dataCostoProdProv[]" value="'+costoServProv.value+'">'+
                                    //lista td
                                    '<td>'+resArrayProvServ[1]+'</td>'+'<td>'+claveServProv.value+'</td>'+'<td>'+costoServProv.value+'</td>';
                        renglon.innerHTML = fila;
                    }
                    else {
                        Push.create("Completa los campos marcados en rojo", {
                            body: "SOS-México",
                            icon: "vista/media/adm/errores/logoSOS.png",
                            timeout: 3000,
                        });
                        if (dataProvServ.value == '') {
                            lblProveedorServ.classList.add("errorlabel");
                        }
                        if (claveServProv.value == '') {
                            lblClaveServProv.classList.add("errorlabel");
                        }
                        if (costoServProv.value == '') {
                            lblCostoServProv.classList.add("errorlabel");
                        }
                    }
                });         

            $(registraServ).click(function(){
                if (logotipoServ.value != '' &&
                    fechaAltaServ.value != '' &&
                    txtClassServ.value != '' &&
                    catalogoSATServ.value != '' &&
                    servicio_regCat.value != '') {
                    envio_formServ();
                } else {

                    Push.create("Completa todos los campos", {
                        body: "SOS-México",
                        icon: "vista/media/adm/errores/logoSOS.png",
                        timeout: 3000,
                    });

                    if (logotipoServ.value == '') {
                        btnAddLogotipoServ.classList.add("btnError");
                    }
                   
                    if (fechaAltaServ.value == ''){
                        lblfechaAltaServ.classList.add("errorlabel");
                        lblfechaAltaServ.innerHTML = "Fecha invalida";
                    }

                    if (txtClassServ.value == ''){
                        lblClassServ.classList.add("errorlabel");
                        lblClassServ.innerHTML = "clasificacion invalida";
                    }
                    
                    if (catalogoSATServ.value == ''){
                        lblcatalogoSATServ.innerHTML = "C&oacute;digo SAT invalido";
                        lblcatalogoSATServ.classList.add("errorlabel");
                    }

                    if (isNaN(parseInt(this.value))) {
                        lblcatalogoSATServ.classList.add("errorlabel");
                        lblcatalogoSATServ.innerHTML = "C&oacute;digo SAT invalido";
                    }

                    if (servicio_regCat.value == ''){
                        lblServicio.classList.add("errorlabel");
                        lblServicio.innerHTML = "Concepto / Descripción";
                    }

                }
            });
    
            function envio_formServ(){
                document.formAddServCat.target = "_self";
                document.formAddServCat.action = "egresos-agregaservicio";
                document.formAddServCat.submit();
            }

            $(logotipoServ).change(function(e){
                //objeto de la clase reader
                let reader = new FileReader();
                //lectura de archivo subido y pasar al reader
                reader.readAsDataURL(e.target.files[0]);
            
                reader.onload =  function(){
                    btnAddLogotipoServ.classList.remove("btnError");
                    let imgPerfil = '<img class="circle responsive-img " src="'+reader.result+'">';
                    btnAddLogotipoServ.innerHTML = imgPerfil;
                };
            });

            $(fechaAltaServ).change(function(){
                if (lblfechaAltaServ.classList.contains("errorlabel")){
                    lblfechaAltaServ.classList.remove("errorlabel");
                    lblfechaAltaServ.innerHTML = "Fecha de Alta"
                };
            });

            $(txtClassServ).change(function(){
                if (lblClassServ.classList.contains("errorlabel")) {
                    lblClassServ.classList.remove("errorlabel");
                    lblClassServ.innerHTML = "Clasificaci&oacute;n";
                }
            });

            $(catalogoSATServ).keyup(function(){
                if (isNaN(parseInt(this.value))) {
                    lblcatalogoSATServ.classList.add("errorlabel");
                    lblcatalogoSATServ.innerHTML = "Ingresa C&oacute;digo SAT en número";
                } else if (this.value == 0) {
                    lblcatalogoSATServ.innerHTML = "C&oacute;digo SAT invalido";
                    lblcatalogoSATServ.classList.add("errorlabel");
                } else if (lblcatalogoSATServ.classList.contains("errorlabel")) {
                    lblcatalogoSATServ.classList.remove("errorlabel");
                    lblcatalogoSATServ.innerHTML = "C&oacute;digo SAT";
                }
            });

            $(servicio_regCat).change(function(){
                if (lblServicio.classList.contains("errorlabel")) {
                    lblServicio.classList.remove("errorlabel");
                    lblServicio.innerHTML = "Concepto / Descripción";
                }
            });           

});
