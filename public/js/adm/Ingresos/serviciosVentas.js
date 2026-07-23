$(document).ready(function(){
    //inicioAltaVenta
        var divTipoUsuario = document.getElementById("divTipoUsuario");
        var vpgeneral = document.getElementById("vpgeneral");
        
        $(vpgeneral).click(function(){
            cuteAlert({
                type: "question",
                title: "Alerta",
                message: "¿Desea acceder como público en general?",
                confirmText: "Si",
                cancelText: "No"
            }).then((e)=>{
                if (e) {
                    $(divAltaVenta).removeClass("noneView");
                    $(divTipoUsuario).addClass("noneView");
                    daTokenCliente = '-';

                    if (tbodyDegloceVentas.rows == 0) {
                        buscaConceptoServProd(daTokenCliente);
                    } 
                }
            })
        });

        //lista de clientes
            //buscador de clientes
                var selectBuscaTipo = document.getElementById("selectBuscaTipo");
                var selectBuscaPersona = document.getElementById("selectBuscaPersona");
                var selectIdtaxRfc = document.getElementById("selectIdtaxRfc");
                var txtBusquedaCliente = document.getElementById("txtBusquedaCliente");
                var lblBusquedaCliente = document.getElementById("lblBusquedaCliente");

                $(buscaClienteVenta('','','',''));
                function buscaClienteVenta(nacionalidad,tipoPersona,rfcTaxNombre,descripcion){
                    $.ajax({
                        url: "ingresos-consultaclientesserv",
                        type: "post",
                        data: {
                            nacionalidad:nacionalidad,
                            tipoPersona:tipoPersona,
                            rfcIdtax:rfcTaxNombre,
                            datoBusqueda:descripcion
                            },
                        dataType: "html",
                        success: function (respuesta) {
                            //console.log(respuesta);
                            if (respuesta == 'errorBusquedaCliente') {
                                txtBusquedaCliente.classList.add("error");
                                errorInput(txtBusquedaCliente,'Busqueda incorrecta'); 
                            } else {
                                correctoInput(txtBusquedaCliente,'Busqueda...');
                                $("#tbodyClienteVenta").html(respuesta);
                                $('select').material_select();
                            }
                        }
                    });
                }

                function desabilita1(){
                    $(selectBuscaPersona).attr('disabled',true);
                    $('select').material_select();
                    addlblDisabledSelect(selectBuscaPersona);
                    selectBuscaPersona.selectedIndex = 0;
                }

                function desabilita2(){
                    $(selectIdtaxRfc).attr("disabled",true);
                    $('select').material_select();
                    addlblDisabledSelect(selectIdtaxRfc);
                    selectIdtaxRfc.selectedIndex = 0;
                }

                function desabilita3(){
                    txtBusquedaCliente.value = '';
                    lblBusquedaCliente.innerText = '';
                    $(txtBusquedaCliente).attr('disabled',true);
                    addlblDisabled(txtBusquedaCliente);
                    $(txtBusquedaCliente).removeAttr("data-length");
                    $(txtBusquedaCliente).removeAttr("placeholder");
                    $(txtBusquedaCliente).removeAttr("maxlength");
                }

                $(selectBuscaTipo).change(function(){
                    desabilita1(),desabilita2(),desabilita3();
                    if (this.value === '' || !strFilter.test(this.value)) {
                        addlblDisabledSelect(selectBuscaPersona);
                        $(selectBuscaPersona).attr("disabled",true);
                        $('select').material_select();
                    } else {
                        buscaClienteVenta(this.value,'','','');
                        $(selectBuscaPersona).removeAttr('disabled');
                        $('select').material_select();
                        quitalblDisabledSelect(selectBuscaPersona);
                    }
                });

                $(selectBuscaPersona).change(function(){
                    desabilita2(),desabilita3();
                    if (this.value === '' || !strFilter.test(this.value)) {
                        addlblDisabledSelect(selectIdtaxRfc);
                        $(selectIdtaxRfc).attr("disabled",true);
                        $('select').material_select();
                    } else {
                        buscaClienteVenta(selectBuscaTipo.value,this.value,'','');
                        $(selectIdtaxRfc).removeAttr('disabled');
                        $('select').material_select();
                        quitalblDisabledSelect(selectIdtaxRfc);
                    }
                });

                $(selectIdtaxRfc).change(function(){
                    desabilita3();
                    if (this.value === '' || !strFilter.test(this.value)) {
                        addlblDisabledSelect(txtBusquedaCliente);
                        $(txtBusquedaCliente).attr("disabled",true);
                    } else {
                        if (this.value == 'idTaxRfc') {
                            $(txtBusquedaCliente).removeAttr('disabled');
                            quitalblDisabled(txtBusquedaCliente);
                            if (selectBuscaTipo.value  == 'nacional') {
                                if (selectBuscaPersona.value == 'fisica') {
                                    txtBusquedaCliente.setAttribute("data-length","13");
                                    txtBusquedaCliente.setAttribute("placeholder","Ej. ABCD000000XXX");
                                    txtBusquedaCliente.setAttribute("maxlength","13");
                                }
                            
                                if (selectBuscaPersona.value == 'moral') {
                                    txtBusquedaCliente.setAttribute("data-length","12");
                                    txtBusquedaCliente.setAttribute("placeholder","Ej. ABC000000XXX");
                                    txtBusquedaCliente.setAttribute("maxlength","12");
                                }
                            
                            }
                        
                            if (selectBuscaTipo.value  == 'extranjero') {
                                txtBusquedaCliente.setAttribute("minlength","9");
                                txtBusquedaCliente.setAttribute("maxlength","40");
                            }
                        }
                    
                        if (this.value == 'nombre') {
                            $(txtBusquedaCliente).removeAttr('disabled');
                            quitalblDisabled(txtBusquedaCliente);
                            if (selectBuscaTipo.value  == 'nacional') {
                                if (selectBuscaPersona.value == 'fisica') {
                                    txtBusquedaCliente.setAttribute("placeholder","Nombre");
                                }
                            
                                if (selectBuscaPersona.value == 'moral') {
                                    txtBusquedaCliente.setAttribute("placeholder","Nombre");
                                }
                            
                            }
                        
                            if (selectBuscaTipo.value  == 'extranjero') {
                                txtBusquedaCliente.setAttribute("minlength","3");
                            }
                        }
                    }
                });

                $(txtBusquedaCliente).keyup(function(){
                    if (this.value != '') { 
                        if (selectIdtaxRfc.value == 'idTaxRfc') {
                            if (selectBuscaTipo.value == 'nacional') {
                                if (selectBuscaPersona.value == 'fisica') {
                                    var cdna1 = txtBusquedaCliente.value.substring(0,4);
                                    var cdna2 = txtBusquedaCliente.value.substring(4,10);
                                    var cdna3 = txtBusquedaCliente.value.substring(10,13);
                                    if (/^[a-zA-Z]+$/.test(cdna1)) {
                                        if (/^[0-9]+$/.test(cdna2)) {
                                            if (/^[a-zA-Z0-9]+$/.test(cdna3) && txtBusquedaCliente.value.length == 13) {
                                                buscaClienteVenta(selectBuscaTipo.value,selectBuscaPersona.value,selectIdtaxRfc.value,this.value);
                                                correctoInput(txtBusquedaCliente,'Escriba su rfc con Homoclave');
                                            
                                            } else {
                                                errorInput(txtBusquedaCliente,'su RFC no es correcto');
                                            }
                                        } else {
                                            errorInput(txtBusquedaCliente,'su RFC no es correcto');
                                        }
                                    } else {
                                        errorInput(txtBusquedaCliente,'su RFC no es correcto');
                                    }
                                }
                                if (selectBuscaPersona.value == 'moral') {
                                    var cdna1 = txtBusquedaCliente.value.substring(0,3);
                                    var cdna2 = txtBusquedaCliente.value.substring(3,9);
                                    var cdna3 = txtBusquedaCliente.value.substring(9,12);
                                    if (/^[a-zA-Z]+$/.test(cdna1)) {
                                        if (/^[0-9]+$/.test(cdna2)) {
                                            if (/^[a-zA-Z0-9]+$/.test(cdna3) && txtBusquedaCliente.value.length == 12) {
                                                buscaClienteVenta(selectBuscaTipo.value,selectBuscaPersona.value,selectIdtaxRfc.value,this.value);
                                                correctoInput(txtBusquedaCliente,'Escriba su rfc con Homoclave');
                                            }
                                            else{
                                                errorInput(txtBusquedaCliente,'su RFC no es correcto');
                                            }
                                        }
                                        else{
                                            errorInput(txtBusquedaCliente,'su RFC no es correcto');
                                        }
                                    }
                                    else{
                                        errorInput(txtBusquedaCliente,'su RFC no es correcto');
                                    }
                                }
                            }
                        
                            if (selectBuscaTipo.value == 'extranjero') {
                                if (selectBuscaPersona.value == 'fisica') {
                                    if (this.value.length> 9 && this.value.length <40 && strFilter.test(this.value)) {
                                        buscaClienteVenta(selectBuscaTipo.value,selectBuscaPersona.value,selectIdtaxRfc.value,this.value);
                                        correctoInput(txtBusquedaCliente,'Escriba idTax ó nombre completo del cliente');
                                    } else {
                                        errorInput(txtBusquedaCliente,'IdTax ó nombre invalido');
                                    }
                                }
                                if (selectBuscaPersona.value == 'moral') {
                                    if ((this.value.length> 9 && this.value.length <40) && strFilter.test(this.value)) {
                                        buscaClienteVenta(selectBuscaTipo.value,selectBuscaPersona.value,selectIdtaxRfc.value,this.value);
                                        correctoInput(txtBusquedaCliente,'Escriba idTax ó razon social del cliente');
                                    } else {
                                        errorInput(txtBusquedaCliente,'IdTax ó razon social invalida');
                                    }
                                }
                            }
                        } 
                        if (selectIdtaxRfc.value == 'nombre') {
                            if (this.value == '' || !filtroLetras.test(this.value) || this.value.length < 3) {
                                errorInput(this,"Nombre invalido"); 
                            } else {
                                buscaClienteVenta(selectBuscaTipo.value,selectBuscaPersona.value,selectIdtaxRfc.value,this.value);
                                correctoInput(this,'Nombre'); 
                            }
                        } 
                    
                    } else {
                        if (selectIdtaxRfc.value == 'idTaxRfc') {
                            if (selectBuscaTipo.value == 'nacional') {
                                if (selectBuscaPersona.value == 'fisica') {
                                    errorInput(txtBusquedaCliente,'Rfc incorrecto (13 caracteres Ej. ABCD000000XXX)');
                                }
                                if (selectBuscaPersona.value == 'moral') {
                                    errorInput(txtBusquedaCliente,'Rfc incorrecto (12 caracteres Ej. ABC000000XXX)');
                                }
                            }
                        
                            if (selectBuscaTipo.value == 'extranjero') {
                                if (selectBuscaPersona.value == 'fisica') {
                                    errorInput(txtBusquedaCliente,'IdTax ó nombre del cliente es invalido');
                                }
                                if (selectBuscaPersona.value == 'moral') {
                                    errorInput(txtBusquedaCliente,'IdTax ó razon social del cliente es invalida');
                                }
                            }
                        }
                        if (selectIdtaxRfc.value == 'nombre') {
                            if (this.value == '' || !filtroLetras.test(this.value) || this.value.length < 3) {
                                errorInput(this,"Nombre invalido"); 
                            
                            } else {
                                if (this.value != '' && filtroLetras.test(this.value) && this.value.length >= 3) {
                                    correctoInput(this,'Nombre'); 
                                }
                            } 
                        }

                    }                    

                });

            //seleccion del cliente
                var hiddenclienteToken = document.getElementById("hiddenclienteToken");
                var h6Folio = document.getElementById("h6Folio");
                var h6Nombre = document.getElementById("h6Nombre");
                var h6Rfc = document.getElementById("h6Rfc");
                var h6ListaPrecio = document.getElementById("h6ListaPrecio");
                var daTokenCliente = '';
                var modalInitServVenta = document.getElementById("modalInitServVenta");

                $("#tabClienteVenta").on("click","td input.selectClientClave",function(){
                    cuteAlert({
                        type: "question",
                        title: "Alerta",
                        message: "¿Desea agregar los datos del cliente?",
                        confirmText: "Si",
                        cancelText: "No"
                    }).then((e)=>{
                        if (e) {
                            $("#efectoCargando").removeClass("noneView");
                            daTokenCliente = this.value;
                            //alert(daTokenCliente);
                            hiddenclienteToken.value = daTokenCliente;
                            h6Folio.innerHTML = $(this).parents("tr").find("td").eq(0).html();
                            h6Rfc.innerHTML = $(this).parents("tr").find("td").eq(2).html();
                            h6Nombre.innerHTML = $(this).parents("tr").find("td").eq(3).html();
                            h6ListaPrecio.innerHTML = $(this).parents("tr").find("td").eq(4).html();
                            buscaConceptoServProd(daTokenCliente);
                        } 
                    });                    
                });

    //Alta de venta
        var divAltaVenta = document.getElementById("divAltaVenta");
        var returnMenu = document.getElementById("returnMenu");

        $(returnMenu).click(function(){
            cuteAlert({
                type: "question",
                title: "Alerta",
                message: "¿Desea regresar el menu principal de ventas?",
                confirmText: "Si",
                cancelText: "No"
            }).then((e)=>{
                if (e) {
                    hiddenclienteToken.value = '';
                    h6Folio.innerHTML = ("----");
                    h6Rfc.innerHTML = ("XAXX010101000");
                    h6Nombre.innerHTML = ("----");
                    h6ListaPrecio.innerHTML = ("Púlico general");
                    $(divAltaVenta).addClass("noneView");
                    $(divTipoUsuario).removeClass("noneView");
                }
            })
        });

        //Tabla de productos y servicios
            //Buscador de productos y servicios
                var selectProdServVenta = document.getElementById("selectProdServVenta");
                var txtBuscaProdServ = document.getElementById("txtBuscaProdServ");
                var tbodyDegloceVentas = document.getElementById("tbodyDegloceVentas");

                function buscaConceptoServProd(token){
                    $.ajax({
                        url: "ingresos-buscaservproduc",
                        type: "post",
                        data: {
                            tokenCliente:token
                            },
                        dataType: "html",
                        success: function (respuesta) {
                            //console.log(respuesta);
                            if (respuesta == 'errorTokneCliente') {
                                $("#theadClienteVenta").addClass("btnError");
                            } else {
                                $(tbodyDegloceVentas).html(respuesta);
                                var modalDescuentos = $(tbodyDegloceVentas).find(".modalDescuentos");
                                $(modalDescuentos).modal();
                                var modalImpuestos = $(tbodyDegloceVentas).find(".modalImpuestos");
                                $(modalImpuestos).modal();
                                $("#efectoCargando").addClass("noneView");
                                $("#modalInitServVenta").modal('close');
                                $(divAltaVenta).removeClass("noneView");
                                $(divTipoUsuario).addClass("noneView");
                                 
                            }
                        }
                    });

                    $.ajax({
                        url: "ingresos-formapagoventa",
                        type: "post",
                        data: {
                            tokenCliente:token
                            },
                        dataType: "html",
                        success: function (respuesta) {
                            console.log(respuesta);
                            if (respuesta == 'errorTokenClient') {
                                
                            } else {
                                
                                 
                            }
                        }
                    });
                }

                $(selectProdServVenta).change(function(){
                    txtBuscaProdServ.value = '';
                    if (this.value != '' && strFilter.test(this.value)) {
                        $(txtBuscaProdServ).removeAttr('disabled');
                        if (this.value == 'tipo') {
                            $(txtBuscaProdServ).removeAttr("minlength");
                            txtBuscaProdServ.setAttribute("maxlength","8");  
                        } if (this.value == 'clasificacion') {
                            $(txtBuscaProdServ).removeAttr("minlength");
                            txtBuscaProdServ.setAttribute("maxlength","14");
                        } else if (this.value == 'concepto') {
                            txtBuscaProdServ.setAttribute("minlength","5"); 
                            $(txtBuscaProdServ).removeAttr("maxlength");     
                        } else if (this.value == 'catalogoSat') {
                            $(txtBuscaProdServ).removeAttr("minlength");
                            txtBuscaProdServ.setAttribute("maxlength","8");
                        } else if (this.value == 'precio') {
                            $(txtBuscaProdServ).removeAttr("minlength");
                            txtBuscaProdServ.setAttribute("maxlength","15");
                        }
                        else {
                            $(txtBuscaProdServ).removeAttr("maxlength");
                            $(txtBuscaProdServ).removeAttr("minlength"); 
                        }                    
                    } else {
                        errorSelect(this);
                    }
                });
            
                $(txtBuscaProdServ).keyup(function(){
                    var trVacio = $(tbodyDegloceVentas).find("#trVacioDesgloceVentas");
                    if (this.value != '') {
                        if (selectProdServVenta.value == 'clasificacion') {
                            if (filtroClasificacion.test(this.value)) {
                                correctoInput(this,'Buscar clasificacion');
                                buscaTablasHtml(this,tbodyDegloceVentas,trVacio);
                            } else {
                                errorInput(this,'filtro invalido');
                            }
                        }
                    
                        if (selectProdServVenta.value == 'concepto') {
                            if (strFilter.test(this.value)) {
                                correctoInput(this,'Buscar concepto');
                                buscaTablasHtml(this,tbodyDegloceVentas,trVacio);
                            } else {
                                errorInput(this,'filtro invalido');
                            }
                        } 
                    
                        if (selectProdServVenta.value == 'catalogoSat') {
                            if (filtroNum.test(this.value)) {
                                correctoInput(this,'Buscar catalogo SAT');
                                buscaTablasHtml(this,tbodyDegloceVentas,trVacio);
                            } else {
                                errorInput(this,'filtro invalido');
                            }
                        }
                    
                        if (selectProdServVenta.value == 'precio') {
                            if (filtroCosto.test(this.value)) {
                                correctoInput(this,'Buscar precio');
                                buscaTablasHtml(this,tbodyDegloceVentas,trVacio);
                            } else {
                                errorInput(this,'filtro invalido');
                            }
                        }
                    
                        if (selectProdServVenta.value == 'unidadSat') {
                            if (strFilter.test(this.value)) {
                                correctoInput(this,'Buscar unidad Sat');
                                buscaTablasHtml(this,tbodyDegloceVentas,trVacio);
                            } else {
                                errorInput(this,'filtro invalido');
                            }
                        }
                    } else {
                        buscaTablasHtml(this,tbodyDegloceVentas,trVacio);
                    }
                
                });
    
                $(txtBuscaProdServ).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!strFilter.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                
                    if (selectProdServVenta.value == 'clasificacion') {
                        if (!filtroClasificacion.test(clave) || this.value.length > 14) {
                            event.preventDefault();
                            return false;
                        }
                    }
                
                    if (selectProdServVenta.value == 'concepto') {
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    } 

                    if (selectProdServVenta.value == 'catalogoSat') {
                        if (!filtroNum.test(clave) || this.value.length > 8) {
                            event.preventDefault();
                            return false;
                        }
                    }
                
                    if (selectProdServVenta.value == 'precio') {
                        if (!/^[0-9.,$]*$/.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    }
                
                    if (selectProdServVenta.value == 'unidadSat') {
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    }
                });

                //$("#theadDesgloceVentas").click("input#txtSelectAllProdServ",function(){});

            //Operaciones descuentos,promociones e impuestos
                var arrayTokenDescuento = [];

                $("#tabDesgloceVentas").on("keyup","tr td input.txtCantidadVenta",function(event){
                    if (this.value != '' && filtroNum.test(this.value)) {
                        this.classList.remove("error");
                        operacionVentaPartida(this);
                    } else {
                        this.classList.add("error");
                    } 
                });
    
                $("#tabDesgloceVentas").on("bind keypress","tr input.txtCantidadVenta",function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!filtroNum.test(clave)) {
                        event.preventDefault();
                        return false;
                    } 
                });

                $("#tabDesgloceVentas").on("click","#trListaDescModal td input#txtSelectDescuento",function(){
                    var descSelected = this.value;
                    var trDescuento = $(this).parent("label").parent("p").parents("td").parents("tr").parents("tbody").parents("#tabListaDescuentosVenta").find("tr").eq(2);
                    var tdTokenDescuentoP1 = $(trDescuento).find("input#txtSelectDescuento");
                    arrayTokenDescuento = tdTokenDescuentoP1.val();
                    console.log(arrayTokenDescuento[0]);
                    cuteAlert({
                        type: "question",
                        title: "Alerta",
                        message: "¿Desea agregar este descuento?",
                        confirmText: "Si",
                        cancelText: "No"
                    }).then((e)=>{
                        if (e) {
                            arrayTokenDescuento.push(descSelected);
                            operacionVentaPartida(this);
                        } else {
                            $(this).removeAttr("checked");
                        }
                    });
                });

                function operacionVentaPartida(valor){
                    //tdTknVenta trDataVentas
                    var trPrincipal = $(valor).parents("#trDataVentas");
                    var precioBase = $(valor).parents("#trDataVentas").find("#tdPrecioBase").html();
                    var cantidad = $(valor).parents("#trDataVentas").find(".txtCantidadVenta");
                    var btnInfoDesc = $(valor).parents("#trDataVentas").find("#infoDesc");
                    var btnInfoImp = $(valor).parents("#trDataVentas").find("#infoImp");
                    var txtTotalDescuento = $(valor).parents("#trDataVentas").find(".txtTotalDescuento");
                    var txtTotalImpuesto = $(valor).parents("#trDataVentas").find(".txtTotalImpuesto");
                    var importePartida = $(valor).parents("#trDataVentas").find(".tdImportePartida");
                    var valPrecio = precioBase.replace("$","");
                    var subTotal = parseFloat(valPrecio) * parseFloat(cantidad.val());
                    var totalDescuentos = 0;
                    var totalPromociones = 0;
                    var totalImpuestos = 0;
                    var totalPartida = 0;

                    //Descuentos
                        if ($(btnInfoDesc).hasClass("disabled")) {
                            totalDescuentos = 0;

                        } else {                                                                                     
                            var rutaDescuento = $(trPrincipal).find("table#tabListaDescuentosVenta tbody");
                            var tdRows = $(rutaDescuento).find("tr");
                            console.log(tdRows.length);
                            for (let i = 1; i < $(rutaDescuento).find("tr").length; i++) {
                                var tdSelectDescuentos = $(rutaDescuento).find("tr").eq(i).find("td").eq(9);
                                var tdInput = tdSelectDescuentos.find("input#txtSelectDescuento");
                                var cuoPorc = $(rutaDescuento).find("tr").eq(i).find("#tdCoutaPorc").html();
                                var monto = $(rutaDescuento).find("tr").eq(i).find("#tdMonto").html();
                                var importeDescuento = $(rutaDescuento).find("tr").eq(i).find("#tdImporteDescuento");
                                if (i == 1) {
                                    if ($(tdInput).is(":checked") || $(tdInput).is(":disabled")) {
                                        if (cuoPorc == 'cuota') {
                                            var descuento = monto.replace("$","");
                                        } else {
                                            var valorDesc = monto.replace("%","");
                                            var descuento = parseFloat(valorDesc) / 100;
                                        }

                                        totalDescuentos = parseFloat(subTotal) * parseFloat(descuento);
                                        var nuDesc = numeral(totalDescuentos);
                                        txtTotalDescuento.val(nuDesc.format('$0,0.00'));
                                    }
                                }

                                if (i >= 2) {
                                    //if ($(tdInput).is(":checked") || $(tdInput).is(":disabled")) {}
                                    for (let i = 0; i < arrayTokenDescuento.length; i++) {
                                        if (tdInput.val() == arrayTokenDescuento[i]) {
                                            $("#tabListaDescuentosVenta td input#txtSelectDescuento").each(function(){
                                                var monto = $(rutaDescuento).find("tr").eq(i).find("#tdMonto").html();
                                                alert(monto);

                                            });
                                        }
                                    }
                                }
                            }
                        }

                    //Promociones

                    
                    //Resultados de operaciones (descuentos,promociones e impuestos)
                        subTotal = subTotal - parseFloat(totalDescuentos);
                        subTotal = subTotal - parseFloat(totalPromociones);
                        subTotal = subTotal.toFixed(2);
                        var nutot = numeral(subTotal);
                        importePartida.html(nutot.format('$0,0.00'));

                    //Impuestos
                        var importeSnImp = importePartida.html();
                        importeSnImp = importeSnImp.replace("$","");
                        importeSnImp = importeSnImp.replace(",","");
                        if ($(btnInfoImp).hasClass("disabled")) {
                            totalImpuestos = 0;
                        
                        } else {
                            var rutaImpuesto = $(trPrincipal).find("table#tabListaImpuestosVenta tbody");
                            var tdRowsImp = $(rutaImpuesto).find("tr");
                            console.log(tdRowsImp.length);
                            for (let i = 1; i < $(rutaImpuesto).find("tr").length; i++) {
                                var tokenImpuesto = $(rutaImpuesto).find("tr").eq(i).find("td").eq(0).html();
                                var reteTras = $(rutaImpuesto).find("tr").eq(i).find("#tdReTras").html();
                                var cuotaPorc = $(rutaImpuesto).find("tr").eq(i).find("#tdCuoPorcImporte").html();
                                var montoImpuesto = $(rutaImpuesto).find("tr").eq(i).find("#tdImporteImpuesto").html();
                            
                                if (cuotaPorc == 'cuota') {
                                    var impuesto = montoImpuesto.replace("$","");
                                } else {
                                    var valImpuesto = montoImpuesto.replace("%","");
                                    valImpuesto = parseFloat(valImpuesto) / parseFloat(100);
                                    var impuesto =  parseFloat(importeSnImp) * parseFloat(valImpuesto);
                                }

                                if (reteTras == 'trasladado') {
                                    totalImpuestos = parseFloat(totalImpuestos) + parseFloat(impuesto);
                                }
                                
                                if (reteTras == 'retenido') {
                                    totalImpuestos = parseFloat(totalImpuestos) - parseFloat(impuesto);
                                } 
                                
                                //var nuimp = numeral(totalImpuestos);
                                //txtTotalImpuesto.val(nuimp.format('$0,0.00'));
                            }
                        }

                        totalPartida = parseFloat(importeSnImp) + parseFloat(totalImpuestos);
                        var nutot = numeral(totalPartida);
                        importePartida.html(nutot.format('$0,0.00'));

                }

            //detalle de operaciones descuentos,promociones y impuestos
                var theadOperProdServ = document.getElementById("theadOperProdServ");
                var tbodyOperProdServ = document.getElementById("tbodyOperProdServ");
                var trVacioOperProdServ = document.getElementById("trVacioOperProdServ");
                var arrayToken = [];
                $("#tabDesgloceVentas").on("click","tr td a.btnDetalleVenta",function(){
                    //alert("funciona");
                    var tokenServVenta = $(this).parent("td").parent("tr").find("td").eq(0).html();
                    arrayToken.push(tokenServVenta);
                    var cantidad = $(this).parent("td").parent("tr").find(".txtCantidadVenta").val();
                    var btnInfoDesc = $(this).parent("td").parent("tr").find("#infoDesc");
                    var valdescuento = $(this).parent("td").parent("tr").find(".txtTotalDescuento").val();
                    if ($(btnInfoDesc).hasClass("disabled")) {
                        var valTotDescuento = '$0.00';
                    } else {
                        var valTotDescuento = valdescuento;
                    }

                    var promocion = '$0.00';
                    var valTotImporte = $(this).parent("td").parent("tr").find(".tdImportePartida").html();

                    cuteAlert({
                        type: "question",
                        title: "Alerta",
                        message: "¿Desea agregar está venta?",
                        confirmText: "Si",
                        cancelText: "No"
                    }).then((e)=>{
                        if (e) {
                            $.ajax({
                                url: "ingresos-detalleventaproserv",
                                type: "post",
                                data: {
                                    token:tokenServVenta,
                                    cantidad:cantidad,
                                    descuento:valTotDescuento,
                                    promocion:promocion,
                                    importe:valTotImporte,
                                    },
                                dataType: "html",
                                success: function (respuesta) {
                                    //console.log(respuesta);
                                    $("#trVacioOperProdServ").addClass("noneView");
                                    $("#tbodyOperProdServ").append(respuesta);
                                    //var $toastContent = $('<div class="btnError">'+texto+'</div>');
                                    //Materialize.toast($toastContent,5000);   
                                    //var topDetalle = $(respuesta).find('.datalistRetenidos').html();
                                    //¿var toolContent = $();
                                    //$('.tdlistRetenidos').tooltip('<div class="btnError">'+topDetalle+'</div>');
                                    sumaImporteFunct();
                                }
                            });
                        } else {
                            $(this).removeAttr("disabled");
                        }
                    });
                    $(this).attr("disabled",true);
                });

                //eliminacion de detalle de descuentos,promociones y impuestos
                    $("#tabOperProdServ").on("click","tr td a.btnDeletDetalleProdServ",function(){
                        //alert("funciona");
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Desea eliminar el detalle de esta venta?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e) {
                                var trElimina = $(this).parent("td").parent("tr.trOperVenta");
                                var posicion = trElimina.index() - 1;
                                $("#tabDesgloceVentas td a.btnDetalleVenta").each(function(){
                                    var tokenServVenta = $(this).parent("td").parent("tr").find("td").eq(0).html();
                                    //alert(tokenServVenta);
                                    if (tokenServVenta == arrayToken[posicion]) {
                                        $(this).removeAttr('disabled');   
                                    }
                                });
                                
                                //alert(tbodyOperProdServ.rows.length);
                                if (tbodyOperProdServ.rows.length == 2) {
                                    trElimina.remove();
                                    trVacioOperProdServ.classList.remove("noneView");
                                } else {
                                    trElimina.remove();
                                }
                                arrayToken.splice(posicion,1);
                                sumaImporteFunct();
                            } 
                        });
                    });

        //Forma de pago
        
        //totales
            var subtotalVenta = document.getElementById("subtotalVenta");
            var totalDescuentoVenta = document.getElementById("totalDescuentoVenta");
            var iva = document.getElementById("iva");
            var isRetenido = document.getElementById("isRetenido");
            var ivaRetenido = document.getElementById("ivaRetenido");
            var ieps = document.getElementById("ieps");
            var otrosImpuFed = document.getElementById("otrosImpuFed");
            var otrosImpuLocal = document.getElementById("otrosImpuLocal");
            var total = document.getElementById("total");

            function sumaImporteFunct(){
                var sumaSubtotal = 0; 
                var sumaDescuento = 0;
                var sumaIva = 0;
                var sumaIsRetenido = 0;
                var sumaIvaRetenido = 0;
                var sumaIeps = 0;
                var sumaOtrosImpuFed = 0;
                var sumatrosImpuLocal = 0;
                var subImporte = 0;
                $("#tbodyOperProdServ tr.trOperVenta").each(function(){
                    var precioVenta = $(this).find("td").eq(6).html();
                    alert(precioVenta);
                    var cantidadVenta = $(this).find("td").eq(7).html();
                    var descuentoVenta = $(this).find("td").eq(8).html();
                    var promocionVenta = $(this).find("td").eq(9).html();
                    var impuestoRetVenta = $(this).find("td").eq(10).html();
                    var impuestoTrasVenta = $(this).find("td").eq(11).html();  
                    var valorIva = $(this).find("td.impIva").html();
                    var valorIsRet = $(this).find("td.impIsRet").html();
                    var valorIvaRet = $(this).find("td.impIvaRet").html();
                    var valorIeps = $(this).find("td.impIeps").html();
                    var valorOtroImpFed = $(this).find("td.impOtrImpFed").html();
                    var valorOtroImpLoc = $(this).find("td.impOtrImpLoc").html();
                    var importeTotal = $(this).find("td").eq(12).html();
                    
                    var valorUnitario = precioVenta.replace("$","");
                    valorUnitario = valorUnitario.replace(",","");
                    sumaSubtotal = parseFloat(sumaSubtotal) + (parseFloat(valorUnitario) * parseFloat(cantidadVenta));
                    alert(sumaSubtotal);

                    var valorDesc = descuentoVenta.replace("$","");
                    valorDesc = valorDesc.replace(",","");
                    sumaDescuento = parseFloat(sumaDescuento) + parseFloat(valorDesc);
                    
                    var valIva = valorIva.replace("$","");
                    valIva = valIva.replace(",","");
                    sumaIva = parseFloat(sumaIva) + parseFloat(valIva);
            
                    var valIsRetenido = valorIsRet.replace("$","");
                    valIsRetenido = valIsRetenido.replace(",","");
                    sumaIsRetenido = parseFloat(sumaIsRetenido) + parseFloat(valIsRetenido);

                    var valIvaRetenido = valorIvaRet.replace("$","");
                    valIvaRetenido = valIvaRetenido.replace(",","");
                    sumaIvaRetenido = parseFloat(sumaIvaRetenido) + parseFloat(valIvaRetenido);

                    var valIeps = valorIeps.replace("$","");
                    valIeps = valIeps.replace(",","");
                    sumaIeps = parseFloat(sumaIeps) + parseFloat(valIeps);

                    var valOtroImpFed = valorOtroImpFed.replace("$","");
                    valOtroImpFed = valOtroImpFed.replace(",","");
                    sumaOtrosImpuFed = parseFloat(sumaOtrosImpuFed) + parseFloat(valOtroImpFed);

                    var valOtroImpLoc = valorOtroImpLoc.replace("$","");
                    valOtroImpLoc = valOtroImpLoc.replace(",","");
                    sumatrosImpuLocal = parseFloat(sumatrosImpuLocal) + parseFloat(valOtroImpLoc);
                    
                    subImporte = parseFloat(sumaSubtotal) - parseFloat(sumaDescuento) + parseFloat(sumaIva) - parseFloat(sumaIsRetenido) - parseFloat(sumaIvaRetenido);                 
                }); 
                var nutot = numeral(sumaSubtotal);
                sumaSubtotal = nutot.format('$0,0.00');
                subtotalVenta.innerHTML = sumaSubtotal;

                var nutot = numeral(sumaDescuento);
                sumaDescuento = nutot.format('$0,0.00');
                totalDescuentoVenta.innerHTML = sumaDescuento;

                var nutot = numeral(sumaIva);
                sumaIva = nutot.format('$0,0.00');
                iva.innerHTML = sumaIva;

                var nutot = numeral(sumaIsRetenido);
                sumaIsRetenido = nutot.format('$0,0.00');
                isRetenido.innerHTML = sumaIsRetenido;

                var nutot = numeral(sumaIvaRetenido);
                sumaIvaRetenido = nutot.format('$0,0.00');
                ivaRetenido.innerHTML = sumaIvaRetenido;

                var nutot = numeral(sumaIeps);
                sumaIeps = nutot.format('$0,0.00');
                ieps.innerHTML = sumaIeps;

                var nutot = numeral(sumaOtrosImpuFed);
                sumaOtrosImpuFed = nutot.format('$0,0.00');
                otrosImpuFed.innerHTML = sumaOtrosImpuFed;

                var nutot = numeral(sumatrosImpuLocal);
                sumatrosImpuLocal = nutot.format('$0,0.00');
                otrosImpuLocal.innerHTML = sumatrosImpuLocal;

                var nutot = numeral(subImporte);
                subImporte = nutot.format('$0,0.00');
                total.innerHTML = subImporte;
            }
    });