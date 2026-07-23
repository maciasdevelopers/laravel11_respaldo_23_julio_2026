$(document).ready(function() {
    //catalogo
        $("#tabListaClient").on("click","td a#btnInfoCli",function(){
            verDataCliente(this);
        });

        $("#tabListaNacClient").on("click","td a#btnInfoCli",function(){
            verDataCliente(this);
        });

        $("#tabListaExtClient").on("click","td a#btnInfoCli",function(){
            verDataCliente(this);
        });

        function verDataCliente(boton){
            var tkncliente = $(boton).parents("tr").find("td").eq(0).html();
            var porcentajeCarga = 0;
            var porcenDiv = '';
            var intervalo = setInterval(() => {
                porcentajeCarga = porcentajeCarga+1;
                var porcenDiv = porcentajeCarga+'%';
                $(".h6Carga").html('cargando... '+porcenDiv); 
                $("#progressbarModalClient").css('width',porcenDiv);
                if (porcentajeCarga == 100) {
                    clearInterval(intervalo);
                    setTimeout(function() {
                        $("#dataModalClient").removeClass("noneView");
                        $("#loadingmodalClient").fadeOut("slow");
                    }, 1000); // WAIT 5 milliseconds
                }
            }, 20);

            $.ajax({
                url: 'ingresos-modalviewcliente',
                type: 'POST',
                datatype: 'html',
                data: {tkncliente: tkncliente},
            })
            .done(function(respuesta){
                $("#dataModalClient").html(respuesta);
            })
            .fail(function(){
            console.log("error");
            })
        }

        var chipClient = document.getElementById("chipClient");
        var disabledClientCont = document.getElementById("disabledClientCont");
        var radioHabilEdiClient = document.getElementById("radioHabilEdiClient");
        var divEnablePassCheckedClient = document.getElementById("divEnablePassCheckedClient");
        var btnVerificaPassClient = document.getElementById("btnVerificaPassClient");

        $("#cierraModalClientes").click(function(){
            $("#dataModalClient").addClass("noneView");
            $("#loadingmodalClient").fadeIn("slow");

            chipClient.classList.remove("chipPass");
            divEnablePassCheckedClient.classList.add("noneView");
            btnVerificaPassClient.classList.add("noneView");
            disabledClientCont.innerHTML = "Habilitar edición";
            chipClient.classList.remove("btnError");
            radioHabilEdiClient.checked = false;
        });

    //formulario
        var media = window.matchMedia("(max-width: 400px)");
        //funciones especiales
            function errorInput(valor,mensaje){
                var divParent = valor.parentElement;
                var errorlbl = divParent.querySelector('label');
                    errorlbl.className = "activeInput errorlabel";
                    errorlbl.innerText = mensaje;
            };

            function errorInputRow(valor){
                valor.classList.remove("correcto");
                valor.classList.add("error");
            };

            function errorSelect(valor,mensaje){
                var divParent = valor.parentElement.parentElement;
                var errorlbl = divParent.querySelector('label');
                    errorlbl.className = "activeSelect errorlabel";
                    errorlbl.innerText = mensaje;
            };

            function errorSelectRow(valor){
                var divParent = valor.parentElement;
                var correctlbl = divParent.querySelector('input');
                    correctlbl.className = "select-dropdown error";
            };

            function correctoInput(valor,mensaje){
                var divParent = valor.parentElement;
                var correctlbl = divParent.querySelector('label');
                    correctlbl.className = "activeInput";
                    correctlbl.innerText = mensaje;
            };

            function correctoInputRow(valor){
                valor.classList.remove("error");
                valor.classList.add("correcto");
            };
            
            function borraInputRow(valor){
                valor.classList.remove("error");
                valor.classList.remove("correcto");
                valor.value = '';
            };

            function correctoSelect(valor,mensaje){
                var divParent = valor.parentElement.parentElement;
                var correctlbl = divParent.querySelector('label');
                    correctlbl.className = "activeSelect";
                    correctlbl.innerText = mensaje;
            };

            function errorSelectRow(valor){
                var divParent = valor.parentElement;
                var correctlbl = divParent.querySelector('input');
                    correctlbl.className = "select-dropdown error";
            };

            function correctoSelectRow(valor){
                var divParent = valor.parentElement;
                var correctlbl = divParent.querySelector('input');
                    correctlbl.className = "select-dropdown correcto";
            };

            function soloNumeros(e){
                var key = e.charCode;
                console.log(key);
                return key >= 48 && key <= 57;
            };

            function toastError(texto){
                var $toastContent = $('<div class="btnError">'+texto+'</div>');
                Materialize.toast($toastContent,5000);    
            }

            function abreHeaderCont(){
                dataContacto.classList.remove("noneView");
                collapsible_headerContacto.classList.add("active");
                collapsible_bodyContacto.classList.add("activeViewError");
            }

            function abreHeaderCredito(){
                serrorCred.classList.remove("noneView");
                lidatacredito.classList.remove("noneView");
                collapsible_headerCredito.classList.add("active");
                collapsible_bodyCredito.classList.add("activeViewError");
            }

            function abreHeaderCreditoEnt(){
                serrorDirEnt.classList.remove("noneView");
                lidirEntregas.classList.remove("noneView");
                collapsible_headerDirEnt.classList.add("active");
                collapsible_bodyDirEnt.classList.add("activeViewError");
            }
            
            function abreHeaderFpago(){
                lidataformapago.classList.remove("noneView");
                collapsible_headerfPago.classList.add("active");
                collapsible_bodyfPago.classList.add("activeViewError");                    
            }

            function checkCurpTax(valor){
                var tipoCliente = document.querySelector('input[name="tipoCliente"]:checked');
                if (tipoCliente.value == 'nacional') {
                    if (valor.value === '' || !(/^[a-zA-Z0-9]+$/.test(valor.value))) {
                        errorInput(valor,'Inserta CURP');
                    } else if (valor.value.length < 18 || valor.value.length > 18) {
                        errorInput(valor,'Inserta CURP valido');
                    } else {
                        correctoInput(valor,'CURP');
                    }
                } else {
                    if (valor.value === '' || !(/^[a-zA-Z0-9]+$/.test(valor.value))) {
                        errorInput(valor,'Inserta IDTax');
                    } else if (valor.value.length > 40) {
                        errorInput(valor,'Inserta IDTax valido');
                    } else {
                        correctoInput(valor,'ID Tax');
                    }
                }
            }

            function checkSelectPais(valor){
                if (valor.value == '' || strFilter.test(valor.value)) {
                    errorSelect(valor,'Selecciona un pais');
                } else {
                    correctoSelect(valor,'Pais');
                }
            }

            function checkSelectListaPrecios(valor){
                if (valor.value == '' || !strFilter.test(valor.value)) {
                    errorSelect(valor,'Selecciona lista de precios');
                } else {
                    correctoSelect(valor,'Lista de precios');
                }
            }

            function checkPaterno(valor){
                if (valor.value === '') {
                    errorInput(valor,'Inserta Apellido Paterno');
                } else {
                    if (!strFilter.test(valor.value)) {
                        errorInput(valor,'Apellido Paterno invalido');
                    } else {
                        if (valor.value.length <2) {
                            errorInput(valor,'Número de caracteres invalido');
                        } else {
                            correctoInput(valor,'Apellido Paterno');
                        }
                    }
                }                        
            }

            function checkMaterno(valor){
                if (valor.value === '') {
                    errorInput(valor,'Inserta Apellido Materno');
                } else {
                    if (!strFilter.test(valor.value)) {
                        errorInput(valor,'Apellido Materno invalido');
                    } else {
                        if (valor.value.length <2) {
                            errorInput(valor,'Número de caracteres invalido');
                        } else {
                            correctoInput(valor,'Apellido Materno');
                        } 
                    }
                }  
            }

            function checkNombre(valor){
                if (valor.value === '') {
                    errorInput(valor,'Inserta Nombre(s)');
                } else {
                    if (!strFilter.test(valor.value)) {
                        errorInput(valor,'Nombre(s) invalido');
                    } else {
                        if (valor.value.length <2) {
                            errorInput(valor,'Número de caracteres invalido');
                        } else {
                            correctoInput(valor,'Nombre(s)'); 
                        }
                    }
                }
            }

            function checkNombreCom(valor){
                if (valor.value === '') {
                    errorInput(valor,'Inserta Nombre comercial');
                } else {
                    if (!strFilEmp.test(valor.value)) {
                        errorInput(valor,'Nombre comercial invalido');
                    } else {
                        if (valor.value.length <10) {
                            errorInput(valor,'Número de caracteres invalido');
                        } else {
                            correctoInput(valor,'Nombre comercial');
                        }
                    }
                }
            }

            function checkMail(valor){
                if (valor.value === '') {
                    errorInput(valor,'Inserta Email');
                } else {
                    if (!correoRegex.test(valor.value)) {
                        errorInput(valor,'Email invalido');
                    } else {
                        if (valor.value.length >= 320) {
                            errorInput(valor,'Número de caracteres invalido');
                        } else {
                            var splitCorreo = valor.value.split("@");
                            if (splitCorreo[0].length <= 64 && splitCorreo[1].length <= 255) {
                                if (valor.value.includes('gmail.com') || valor.value.includes('hotmail.com') || 
                                    valor.value.includes('outlook.com') || valor.value.includes('yahoo.com') || 
                                    valor.value.includes(txtsitWebPF_reg.value) || 
                                    valor.value.includes(txtsitWeb_regPM.value)) {
                                    correctoInput(valor,'Email');
                                } else {
                                    errorInput(valor,'Número de caracteres invalido');
                                }
                            } else {
                                errorInput(valor,'Número de caracteres invalido');
                            }
                        }
                    }
                }
            }

            function checkTelefono(valor){
                if (valor.value === '') {
                    errorInput(valor,'Inserta Teléfono');
                } else {
                    if (!(/^[0-9]+$/.test(valor.value))) {
                        errorInput(valor,'Número de caracteres invalido');
                    } else {
                        if (vtipoCliente == 'nacional') {
                            if (valor.value.length !=10) {
                                errorInput(valor,'Número de caracteres invalido');
                            } else {
                                correctoInput(valor,'Teléfono'); 
                            }
                        }

                        if (vtipoCliente == 'extranjero') {
                            if (valor.value.length <7 || valor.value.length >15) {
                                errorInput(valor,'Número de caracteres invalido');
                            } else {
                                correctoInput(valor,'Teléfono'); 
                            }
                        }

                    }
                }
            }

            function checkExtension(valor){
                if (valor.value === '') {
                    errorInput(valor,'Inserta Extensión');
                } else {
                    if (!(/^[0-9]+$/.test(valor.value))) {
                        errorInput(valor,'Número de caracteres invalido');
                    } else {
                        if (valor.value.length <1) {
                            errorInput(valor,'Número de caracteres invalido');
                        } else {
                            correctoInput(valor,'Extensión'); 
                        }
                    }
                }
            }

            function checkSitWeb(valor){
                if (valor.value == '') {
                    errorInput(valor,'Inserta Sitio Web');
                } else {
                    if (!(filtroUrl.test(valor.value))) {
                        errorInput(valor,'Número de caracteres invalido');
                    } else {
                        if (valor.value.length <10) {
                            errorInput(valor,'Número de caracteres invalido');
                        } else {
                            correctoInput(valor,'Sitio Web'); 
                        }
                    }
                }
            }

            function checkPostalExt(valor){
                if (valor.value === '' || !filtroDom.test(valor.value)) {
                    errorInputRow(valor);
                } else {
                    correctoInputRow(valor);
                }
            }

            function checkDireccionExt(valor){
                if (valor.value === '' || !filtroDom.test(valor.value)) {
                    errorInputRow(valor);
                } else {
                    correctoInputRow(valor);
                }
            }

            var filtroRfc = /^[A-Za-z0-9]*$/; 
            var correoRegex = /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
            var strFilter = /^[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]*$/;
            var strFilterMail = /^[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ@0-9.,;:_-]*$/;
            var strFilEmp = /^[A-Za-z0-9ƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.-]*$/; 
            var filtroDom = /^[A-Za-z0-9ƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:-]*$/; 
            var filtroDomNum = /^[A-Za-z0-9 .,-/]*$/; 
            var filtroUrl = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/; 

        
        //validacion rfc
            //verificar tipo y subtipo de cliente
                var vtipoCliente = '';
                var rdioTipoClient1 = document.getElementById("clientNacional");
                var rdioTipoClient2 = document.getElementById("clientExtranjero");
                var backTipoClient = document.getElementById("backTipoClient");
            
                var rdioSubTipClient1 = document.getElementById("clientFisica");
                var rdioSubTipClient2 = document.getElementById("clientMoral");
                var backSubTipoClient = document.getElementById("backSubTipoClient");

                var rfcVerifClient = document.getElementById("rfcVerifClient");
                var btnVerifRfcCliente = document.getElementById("btnVerifRfcCliente");
                var dataregClient = document.getElementById("data-regClient");
                var varriablleRfc = '';

            //tipo
                $('input[name="tipoCliente"]').click(function() {
                    rdioTipoClient1.disabled = true;
                    rdioTipoClient2.disabled = true;
                    $(rdioTipoClient1).parents("p").addClass("checked");
                    $(rdioTipoClient2).parents("p").addClass("checked");
                    rdioSubTipClient1.removeAttribute("disabled");
                    rdioSubTipClient2.removeAttribute("disabled");
                    backTipoClient.classList.remove("noneView");
                    txtrfcClient.value = '';
                });
            
                $(backTipoClient).click(function(){
                    rdioSubTipClient1.disabled = true;
                    rdioSubTipClient2.disabled = true;

                    $(rdioSubTipClient1).removeAttr("checked");
                    $(rdioSubTipClient2).removeAttr("checked");

                    $(rdioSubTipClient1).parents("p").removeClass("checked");
                    $(rdioSubTipClient2).parents("p").removeClass("checked");
                    $(rdioTipoClient1).parents("p").removeClass("checked");
                    $(rdioTipoClient2).parents("p").removeClass("checked");
                    $(rdioTipoClient1).removeAttr("disabled");
                    $(rdioTipoClient2).removeAttr("disabled");
                    $(rdioTipoClient1).removeAttr("checked");
                    $(rdioTipoClient2).removeAttr("checked");

                    this.classList.add("noneView");
                    backSubTipoClient.classList.add("noneView");
                    $(btnVerifRfcCliente).attr('disabled',true);
                    txtrfcClient.disabled = true;
                    txtrfcClient.value = '';
                    lbl_rfcCliente.classList.add("disabled");
                });

            //subtipo
                var datClienteExtranjero = document.getElementById("datClienteExtranjero");
                var verifRfcPaterno = document.getElementById("verifRfcPaterno");
                var verifRfcMaterno = document.getElementById("verifRfcMaterno");
                var verifRfcnombre = document.getElementById("verifRfcnombre");
                var divRfcCliente = document.getElementById("divRfcCliente");
                var txtrfcClient = document.getElementById("verif_rfcClient");
                var lbl_rfcCliente = document.getElementById("lbl_rfcCliente");
                var reRfc = document.getElementById("rfc_View");

                var nombrePF = document.getElementById("nombrePF");
                var viewPFExt = document.getElementById("viewPFExt");
            
                $('input[name="subtipoCliente"]').click(function() {
                    backSubTipoClient.classList.remove("noneView");
                    var radioClient = document.querySelector('input[name="tipoCliente"]:checked');
                    if (radioClient) {
                        txtrfcClient.value = '';
                        txtrfcClient.removeAttribute("disabled");
                        lbl_rfcCliente.classList.remove("disabled");
                        if (radioClient.value == 'nacional') {
                            divRfcCliente.classList.remove("noneView");
                            datClienteExtranjero.classList.add("noneView");
                            if (this.value == 'clientFisica') {
                                lbl_rfcCliente.innerHTML='Escriba su rfc con Homoclave (13 caracteres Ej. ABCD000000XXX)';
                                txtrfcClient.setAttribute("data-length","13");
                                txtrfcClient.setAttribute("placeholder","Ej. ABCD000000XXX");
                                txtrfcClient.setAttribute("maxlength","13");
                            }
                            if (this.value == 'clientMoral') {
                                lbl_rfcCliente.innerHTML='Escriba su rfc con Homoclave (12 caracteres Ej. ABC000000XXX)';
                                txtrfcClient.setAttribute("data-length","12");
                                txtrfcClient.setAttribute("placeholder","Ej. ABC000000XXX");
                                txtrfcClient.setAttribute("maxlength","12");
                            }
                        }
                        if (radioClient.value == 'extranjero') {
                            if (this.value == 'clientFisica') {
                                //lbl_rfcCliente.innerHTML='Escriba idTax ó nombre completo del cliente';
                                divRfcCliente.classList.add("noneView");
                                txtrfcClient.disabled = true;
                                lbl_rfcCliente.classList.add("disabled");
                                datClienteExtranjero.classList.remove("noneView");
                            }
                            if (this.value == 'clientMoral') {
                                datClienteExtranjero.classList.add("noneView");
                                divRfcCliente.classList.remove("noneView");

                                txtrfcClient.setAttribute("minlength","9");
                                txtrfcClient.setAttribute("maxlength","40");
                                txtrfcClient.setAttribute("placeholder","EJEMPLO S.A DE C.V.");

                                lbl_rfcCliente.innerHTML='Escriba idTax ó razon social del cliente';
                            }

                        }
                        btnVerifRfcCliente.classList.remove("noneView");
                        $(rdioSubTipClient1).parents("p").addClass("checked");
                        $(rdioSubTipClient2).parents("p").addClass("checked");
                    } else {

                    } 
                });

            //cliente nacional
                $(txtrfcClient).keyup(function(){
                    var radioClient = document.querySelector('input[name="tipoCliente"]:checked');
                    var subtipoCliente = document.querySelector('input[name="subtipoCliente"]:checked');
                    if (this.value != '') {

                        if (radioClient.value == 'nacional') {
                            if (subtipoCliente.value == 'clientFisica') {
                                var cdna1 = txtrfcClient.value.substring(0,4);
                                var cdna2 = txtrfcClient.value.substring(4,10);
                                var cdna22 = cdna2.substring(2,4);
                                var cdna23 = cdna2.substring(4,6);
                                var cdna3 = txtrfcClient.value.substring(10,13);
                                if (/^[a-zA-Z]+$/.test(cdna1)) {
                                    if (/^[0-9]+$/.test(cdna2) && cdna22 <= 12 && cdna23 <= 31) {
                                        if (/^[a-zA-Z0-9]+$/.test(cdna3) && txtrfcClient.value.length == 13) {
                                            correctoInput(txtrfcClient,'Escriba su rfc con Homoclave');
                                            $(btnVerifRfcCliente).removeAttr("disabled");
                                        } else {
                                            errorInput(txtrfcClient,'su RFC no es correcto');
                                            $(btnVerifRfcCliente).attr('disabled',true);
                                        }
                                    } else {
                                        errorInput(txtrfcClient,'su RFC no es correcto');
                                        $(btnVerifRfcCliente).attr('disabled',true);
                                    }
                                } else {
                                    errorInput(txtrfcClient,'su RFC no es correcto');
                                    $(btnVerifRfcCliente).attr('disabled',true);
                                }
                            }
                            if (subtipoCliente.value == 'clientMoral') {
                                var cdna1 = txtrfcClient.value.substring(0,3);
                                var cdna2 = txtrfcClient.value.substring(3,9);
                                var cdna22 = cdna2.substring(2,4);
                                var cdna23 = cdna2.substring(4,6);
                                var cdna3 = txtrfcClient.value.substring(9,12);
                                if (/^[a-zA-Z]+$/.test(cdna1)) {
                                    if (/^[0-9]+$/.test(cdna2) && cdna22 <= 12 && cdna23 <= 31) {
                                        if (/^[a-zA-Z0-9]+$/.test(cdna3) && txtrfcClient.value.length == 12) {
                                            correctoInput(txtrfcClient,'Escriba su rfc con Homoclave');
                                            $(btnVerifRfcCliente).removeAttr("disabled");
                                        }
                                        else{
                                            errorInput(txtrfcClient,'su RFC no es correcto');
                                            $(btnVerifRfcCliente).attr('disabled',true);
                                        }
                                    }
                                    else{
                                        errorInput(txtrfcClient,'su RFC no es correcto');
                                        $(btnVerifRfcCliente).attr('disabled',true);
                                    }
                                }
                                else{
                                    errorInput(txtrfcClient,'su RFC no es correcto');
                                    $(btnVerifRfcCliente).attr('disabled',true);
                                }
                            }
                        }

                        if (radioClient.value == 'extranjero') {
                            if (subtipoCliente.value == 'clientFisica') {
                                if (this.value.length> 9 && this.value.length <40 && strFilEmp.test(this.value)) {
                                    correctoInput(txtrfcClient,'Escriba idTax ó nombre completo del cliente');
                                    $(btnVerifRfcCliente).removeAttr("disabled");
                                } else {
                                    errorInput(txtrfcClient,'IdTax ó nombre del cliente es invalido');
                                    $(btnVerifRfcCliente).attr('disabled',true);
                                }
                            }
                            if (subtipoCliente.value == 'clientMoral') {
                                if ((this.value.length> 9 && this.value.length <40) && strFilEmp.test(this.value)) {
                                    correctoInput(txtrfcClient,'Escriba idTax ó razon social del cliente');
                                    $(btnVerifRfcCliente).removeAttr("disabled");
                                } else {
                                    errorInput(txtrfcClient,'IdTax ó razon social del cliente es invalida');
                                    $(btnVerifRfcCliente).attr('disabled',true);
                                }
                            }
                        }
                    } else {
                        if (radioClient.value == 'nacional') {
                            if (subtipoCliente.value == 'clientFisica') {
                                errorInput(txtrfcClient,'Rfc incorrecto (13 caracteres Ej. ABCD000000XXX)');
                                $(btnVerifRfcCliente).attr('disabled',true);
                            }
                            if (subtipoCliente.value == 'clientMoral') {
                                errorInput(txtrfcClient,'Rfc incorrecto (12 caracteres Ej. ABC000000XXX)');
                                $(btnVerifRfcCliente).attr('disabled',true);
                            }
                        }

                        if (radioClient.value == 'extranjero') {
                            if (subtipoCliente.value == 'clientFisica') {
                                errorInput(txtrfcClient,'IdTax ó nombre del cliente es invalido');
                                $(btnVerifRfcCliente).attr('disabled',true);
                            }
                            if (subtipoCliente.value == 'clientMoral') {
                                errorInput(txtrfcClient,'IdTax ó razon social del cliente es invalida');
                                $(btnVerifRfcCliente).attr('disabled',true);
                            }
                        }
                    }
                });
                
                $(txtrfcClient).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!filtroRfc.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

            //cliente extranjero
                $(verifRfcPaterno).keyup(function(){
                    checkPaterno(this);
                    verifPatMatnom();
                });

                $(verifRfcPaterno).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!strFilter.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

                $(verifRfcMaterno).keyup(function(){
                    checkMaterno(this);
                    verifPatMatnom();
                });

                $(verifRfcMaterno).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!strFilter.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

                $(verifRfcnombre).keyup(function(){
                    checkNombre(this);
                    verifPatMatnom();
                });

                $(verifRfcnombre).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!strFilter.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });
            
                function verifPatMatnom(){
                    if ((verifRfcPaterno.value != '' && strFilter.test(verifRfcPaterno.value) && verifRfcPaterno.value.length >= 3) &&
                        (verifRfcMaterno.value != '' && strFilter.test(verifRfcMaterno.value) && verifRfcMaterno.value.length >= 3) &&
                        (verifRfcnombre.value != '' && strFilter.test(verifRfcnombre.value) && verifRfcnombre.value.length >= 3) ) {
                        $(btnVerifRfcCliente).removeAttr("disabled");
                    } else {
                        $(btnVerifRfcCliente).attr('disabled',true);
                    }
                }

            //verifica rfc del Cliente subtipoCliente
                $(btnVerifRfcCliente).click(function() {
                    var radioClient = document.querySelector('input[name="tipoCliente"]:checked');
                    var subtipoCliente = document.querySelector('input[name="subtipoCliente"]:checked');
                    if (radioClient.value == 'nacional') {
                        if (subtipoCliente) {
                            if (subtipoCliente.value == 'clientFisica') {
                                if (txtrfcClient.value != '' && txtrfcClient.value.length == 13) {
                                    var cdna1 = txtrfcClient.value.substring(0,4);
                                    var cdna2 = txtrfcClient.value.substring(4,10);
                                    var cdna3 = txtrfcClient.value.substring(10,13);
                                    if ((/^[a-zA-Z]+$/.test(cdna1)) && (/^[0-9]+$/.test(cdna2)) && (/^[a-zA-Z0-9]+$/.test(cdna3)) ) {
                                        correctoInput(txtrfcClient,'Escriba su rfc con Homoclave');
                                        cuteAlert({
                                            type: "question",
                                            title: "Alerta",
                                            message: "¿Su cliente es Persona Física?",
                                            confirmText: "Si",
                                            cancelText: "No"
                                        }).then((e)=>{
                                            if (e){
                                                validaClientMySQL(txtrfcClient.value);
                                            } else {
                                                //alert(":-(");
                                            }
                                        })
                                    } else {
                                        errorInput(txtrfcClient,'su RFC no es correcto');
                                        rfcInvalido();
                                    }
                                } else {
                                    if (txtrfcClient.value == '') {
                                        rfcVacio();
                                        errorInput(txtrfcClient,'Inserta Rfc de su Cliente');
                                    }

                                    if (txtrfcClient.value.length != 13) {
                                        errorInput(txtrfcClient,'Su rfc debe contener 13 caracteres');
                                        rfcInvalido();
                                    }   
                                }
                            }

                            if (subtipoCliente.value == 'clientMoral') {
                                if(txtrfcClient.value != '' && txtrfcClient.value.length == 12) {
                                    var cdna1 = txtrfcClient.value.substring(0,3);
                                    var cdna2 = txtrfcClient.value.substring(3,9);
                                    var cdna3 = txtrfcClient.value.substring(9,12);
                                    if ((/^[a-zA-Z]+$/.test(cdna1)) && (/^[0-9]+$/.test(cdna2)) && (/^[a-zA-Z0-9]+$/.test(cdna3))) {
                                        correctoInput(txtrfcClient,'Escriba su rfc con Homoclave');
                                        cuteAlert({
                                            type: "question",
                                            title: "Alerta",
                                            message: "¿Su cliente es Persona Moral?",
                                            confirmText: "Si",
                                            cancelText: "No"
                                        }).then((e)=>{
                                            if (e){
                                                validaClientMySQL(txtrfcClient.value);
                                            } else {
                                                //alert(":-(");
                                            }
                                        })
                                    }
                                    else{
                                         errorInput(txtrfcClient,'su RFC no es correcto');
                                         rfcInvalido();
                                    }
                                } else {
                                    if (txtrfcClient.value == '') {
                                        rfcVacio();
                                        errorInput(txtrfcClient,'Inserta Rfc de su Cliente');
                                    }

                                    if (txtrfcClient.value.length != 12) {
                                        errorInput(txtrfcClient,'Su rfc debe contener 12 caracteres');
                                        rfcInvalido();
                                    }  
                                }
                            }

                        } else {
                            toastError('seleccione subtipo de cliente');
                        }
                    }

                    if (radioClient.value == 'extranjero') {
                        if (subtipoCliente) {
                            if (subtipoCliente.value == 'clientFisica') {
                                if ((verifRfcPaterno.value != '' && strFilter.test(verifRfcPaterno.value) && verifRfcPaterno.value.length >= 4) && 
                                    (verifRfcMaterno.value != '' && strFilter.test(verifRfcMaterno.value) && verifRfcMaterno.value.length >= 4) && 
                                    (verifRfcnombre.value != '' && strFilter.test(verifRfcnombre.value) && verifRfcnombre.value.length >= 3)) {
                                    cuteAlert({
                                        type: "question",
                                        title: "Alerta",
                                        message: "¿Su cliente es Persona Física?",
                                        confirmText: "Si",
                                        cancelText: "No"
                                    }).then((e)=>{
                                        if (e){
                                            validaClientMySQLPF(verifRfcPaterno.value,verifRfcMaterno.value,verifRfcnombre.value);
                                        } else {
                                            //alert(":-(");
                                        }
                                    })
                                } else {
                                    camposVacios();
                                    if(verifRfcPaterno.value == '' || !strFilter.test(verifRfcPaterno.value) || verifRfcPaterno.value.length < 4){
                                        errorInput(verifRfcPaterno,'Inserta Apellido Paterno');
                                    }
                                
                                    if(verifRfcMaterno.value == '' || !strFilter.test(verifRfcMaterno.value) || verifRfcMaterno.value.length < 4){
                                        errorInput(verifRfcMaterno,'Inserta Apellido Materno');
                                    }
                                
                                    if(verifRfcnombre.value == '' || !strFilter.test(verifRfcnombre.value) || verifRfcnombre.value.length < 3){
                                        errorInput(verifRfcnombre,'Inserta Nombre(s)');
                                    }

                                }
                            }

                            if (subtipoCliente.value == 'clientMoral') {
                                if (txtrfcClient.value != '' && txtrfcClient.value.length >= 9 && txtrfcClient.value.length <= 40) {
                                    correctoInput(txtrfcClient,'Escriba su rfc con Homoclave');
                                    cuteAlert({
                                        type: "question",
                                        title: "Alerta",
                                        message: "¿Su cliente es Persona Moral?",
                                        confirmText: "Si",
                                        cancelText: "No"
                                    }).then((e)=>{
                                        if (e){
                                            validaClientMySQL(txtrfcClient.value);
                                        } else {
                                        }
                                    })
                                } else {
                                    if (txtrfcClient.value == '') {
                                        rfcVacio();
                                        errorInput(txtrfcClient,'Inserta nombre de su Cliente');
                                    }

                                    if (txtrfcClient.value.length < 9 || txtrfcClient.value.length > 40) {
                                        errorInput(txtrfcClient,'Su rfc debe contener 12 caracteres');
                                        rfcInvalido();
                                    }  
                                }
                            }
                        } else {
                            toastError('seleccione subtipo de cliente');
                        }
                    }
                });
        
                function validaClientMySQL(rfc){
                    var radioClient = document.querySelector('input[name="tipoCliente"]:checked');
                    var subtipoCliente = document.querySelector('input[name="subtipoCliente"]:checked'); 
                    var direccionNacional = document.getElementById("direccionNacionalAltaClient");
                    if (radioClient.value == 'nacional') {
                        $.ajax({
                            url: 'ingresos-busquedacliente',
                            type: 'POST',
                            datatype: 'html',
                            data: {clientrfc:rfc}
                        })
                        .done(function(respuesta){
                            //alert(respuesta)
                            if (respuesta == 'errorVerifRfc') {
                                toastError('error al verificar el rfc/idTax '+rfc);
                            } 
    
                            if (respuesta == 'registred') {
                                toastError('el cliente con el rfc '+rfc+' ya ha sido registrado');
                            } 
    
                            if (respuesta == 'notRegistred') {
                                var $toastContent = $('<div class="btnCorrecto">el cliente con el rfc '+rfc+' no ha sido registrado</div>');
                                Materialize.toast($toastContent,5000);
    
                                rfcVerifClient.classList.add("noneView");
                                dataregClient.classList.remove("noneView");

                                reRfc.innerHTML = txtrfcClient.value;
                                varriablleRfc = txtrfcClient.value;
                                vtipoCliente = 'nacional';
                                alert("fiscal");
                                //direccionNacional.classList.remove("noneView");
                                $("#direccionNacionalAltaClient").removeClass("noneView");
                                txtTelefonoCont_reg.setAttribute("data-length","10"); 
                                txtTelefonoCont_reg.setAttribute("maxlength","10");
                                if (subtipoCliente.value == 'clientMoral'){
                                    datGenPM.classList.remove("noneView");
                                    datGenPM.classList.add("active");
                                    datGenPMBody.classList.add("activeView");
                                    txtempresa_reg.required = true;
                                }
                                if(subtipoCliente.value == 'clientFisica'){
                                    datGenPF.classList.remove("noneView");
                                    datGenPF.classList.add("active");
                                    datGenPFBody.classList.add("activeView");
                                    txtPaternoPF_reg.required = true;
                                    txtMaternoPF_reg.required = true;
                                    txtnombrePF_reg.required = true;
                                    txtcurpPF_reg.required = true;
                                }
                            }
                        })
                        .fail(function(){
                            console.log("error")
                        });
                    }
        
                    if (radioClient.value == 'extranjero') {
                        $.ajax({
                            url: 'ingresos-busquedaextcliente',
                            type: 'POST',
                            datatype: 'html',
                            data: {denominacion:rfc}
                        })
                        .done(function(respuesta){
                            //alert(respuesta)
                            if (respuesta == 'errorVerifRfc') {
                                toastError('error al verificar el rfc/idTax'+rfc);
                            } 
    
                            if (respuesta == 'registred') {
                                toastError('el cliente con el rfc/idTax '+rfc+' ya ha sido registrado');
                            } 
    
                            if (respuesta == 'notRegistred') {
                                var $toastContent = $('<div class="btnCorrecto">el cliente con el rfc/idTax '+rfc+' no ha sido registrado</div>');
                                Materialize.toast($toastContent,5000);
    
                                rfcVerifClient.classList.add("noneView");
                                dataregClient.classList.remove("noneView");

                                reRfc.innerHTML = "xexx010101000";
                                varriablleRfc = "xexx010101000";
                                nombrePF.classList.remove("noneView");
                                viewPFExt.innerHTML = rfc;
                                direccionFiscalAltaExtran.classList.remove("noneView");
                                vtipoCliente = 'extranjero';
                                txtTelefonoCont_reg.setAttribute("data-length","15"); 
                                txtTelefonoCont_reg.setAttribute("maxlength","15");
    
                                if (subtipoCliente.value == 'clientMoral'){
                                    datGenPM.classList.remove("noneView");
                                    datGenPM.classList.add("active");
                                    datGenPMBody.classList.add("activeView");
                                    idTaxPM.classList.remove("noneView");
                                    paisExtPM.classList.remove("noneView");
                                    txtempresa_reg.required = true;
                                }
    
                            }
                        })
                        .fail(function(){
                            console.log("error")
                        });
                    }
                }
        
                function validaClientMySQLPF(rfcPaterno,rfcMaterno,rfcNombre){
                    $.ajax({
                        url: 'ingresos-busquedapfextcliente',
                        type: 'POST',
                        datatype: 'html',
                        data: {
                            rfcPaterno:rfcPaterno,
                            rfcMaterno:rfcMaterno,
                            rfcNombre:rfcNombre
                        }
                    })
                    .done(function(respuesta){
                        //alert(respuesta)
                        if (respuesta == 'errorVerifRfc') {
                            toastError('error al verificar el nombre/idTax '+rfcPaterno+" "+rfcMaterno+" "+rfcNombre);
                        } 

                        if (respuesta == 'registred') {
                            toastError('el cliente con el nombre/idTax '+rfcPaterno+" "+rfcMaterno+" "+rfcNombre+' ya ha sido registrado');
                        } 

                        if (respuesta == 'notRegistred') {
                            var $toastContent = $('<div class="btnCorrecto">el cliente con el rfc/idTax '+rfcPaterno+" "+rfcMaterno+" "+rfcNombre+' no ha sido registrado</div>');
                            Materialize.toast($toastContent,5000);

                            rfcVerifClient.classList.add("noneView");
                            dataregClient.classList.remove("noneView");

                            var radioClient = document.querySelector('input[name="tipoCliente"]:checked');
                            var subtipoCliente = document.querySelector('input[name="subtipoCliente"]:checked'); 

                            reRfc.innerHTML = "xexx010101000";
                            varriablleRfc = "xexx010101000";
                            nombrePF.classList.remove("noneView");
                            viewPFExt.innerHTML = rfcPaterno+" "+rfcMaterno+" "+rfcNombre;
                            direccionFiscalAltaExtran.classList.remove("noneView");
                            vtipoCliente = 'extranjero';
                            txtTelefonoCont_reg.setAttribute("data-length","15"); 
                            txtTelefonoCont_reg.setAttribute("maxlength","15");
                            if(subtipoCliente.value == 'clientFisica'){
                                datGenPF.classList.remove("noneView");
                                datGenPF.classList.add("active");
                                datGenPFBody.classList.add("activeView");
                                txtPaternoPF_reg.required = true;
                                txtMaternoPF_reg.required = true;
                                txtnombrePF_reg.required = true;
                                txtcurpPF_reg.required = true;

                                lblCurp.innerHTML = 'ID Tax';
                                paisExtPF.classList.remove("noneView");
                            }
                        }
                    })
                    .fail(function(){
                        console.log("error")
                    });
                }
            
            //validaciones
                //persona fisica
                    var datGenPF = document.getElementById("datGenPF");
                    var datGenPFHead = document.getElementById("datGenPFHead");
                    var datGenPFBody = document.getElementById("datGenPFBody");
                    var txtPaternoPF_reg = document.getElementById("txtPaternoPF_reg");
                    var txtMaternoPF_reg = document.getElementById("txtMaternoPF_reg");
                    var txtnombrePF_reg = document.getElementById("txtnombrePF_reg");
                    var selListaPreciosPF_reg = document.getElementById("selListaPreciosPF_reg");
                    var txtnomCom_regPF = document.getElementById("txtnomCom_regPF");
                    var txtcurpPF_reg = document.getElementById("txtcurpPF_reg");
                    var paisExtPF = document.getElementById("paisExtPF");
                    var selPaisExtPF_reg = document.getElementById("selPaisExtPF_reg");
                    var txtsitWebPF_reg = document.getElementById("txtsitWebPF_reg");
                    var tabRedSocialPF = document.getElementById("tabRedSocialPF");
                    var arrayRedesPF = ["","","",""];

                    $(txtPaternoPF_reg).keyup(function(){
                        checkPaterno(this);
                        if (vtipoCliente == 'extranjero') {
                            verificaEmpIguales();
                        }
                    });

                    $(txtPaternoPF_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtMaternoPF_reg).keyup(function(){
                        checkMaterno(this);
                        if (vtipoCliente == 'extranjero') {
                            verificaEmpIguales();
                        }
                    });

                    $(txtMaternoPF_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtnombrePF_reg).keyup(function(){
                        checkNombre(this);
                        if (vtipoCliente == 'extranjero') {
                            verificaEmpIguales();
                        }
                    });

                    $(txtnombrePF_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    function verificaEmpIguales(){
                        var nombre = txtPaternoPF_reg.value+" "+txtMaternoPF_reg.value+" "+txtnombrePF_reg.value;
                        if (nombre.toLowerCase() == viewPFExt.innerHTML.toLowerCase()) {
                            correctoInput(txtPaternoPF_reg,'Apellido Paterno');
                            correctoInput(txtMaternoPF_reg,'Apellido Materno');
                            correctoInput(txtnombrePF_reg,'Nombre(s)'); 
                        } else {
                            errorInput(txtPaternoPF_reg,'Inserta Apellido Paterno');
                            errorInput(txtMaternoPF_reg,'Inserta Apellido Materno');
                            errorInput(txtnombrePF_reg,'Inserta Nombre(s)');
                        }
                    }

                    $(selListaPreciosPF_reg).change(function(){
                        checkSelectListaPrecios(this);
                    });

                    $(txtnomCom_regPF).keyup(function(){
                        checkNombreCom(this);
                    });

                    $(txtnomCom_regPF).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtcurpPF_reg).keyup(function(){
                        checkCurpTax(this);
                    });

                    $(txtcurpPF_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!/^[a-zA-Z0-9]+$/.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(selPaisExtPF_reg).change(function(){
                        checkSelectPais(this);
                    });

                    $(txtsitWebPF_reg).keyup(function(){
                        checkSitWeb(this);
                    });

                    $(txtsitWebPF_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroUrl.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtsitWebPF_reg).change(function(){
                        checkSitWeb(this);
                    });

                    $(tabRedSocialPF).on("click", "td a#btnIcon",function() {
                        var inputEdi = $(this).parent("td").parent("tr").find("#txtredSocial");
                        var eliminaRedSoc = $(this).parent("td").parent("tr").find("#eliminaRedSoc");
                        if (inputEdi.is(":disabled")){
                            this.setAttribute("disabled","disabled");
                            inputEdi.removeAttr("disabled");
                            eliminaRedSoc.removeAttr("disabled");
                            if (this.classList.contains("icon-facebook")) {
                                inputEdi.val("https://www.facebook.com/");
                            } 
                            if (this.classList.contains("icon-twitter")) {
                                inputEdi.val("https://www.twitter.com/");
                            }
                            if (this.classList.contains("icon-instagram")) {
                                inputEdi.val("https://www.instagram.com/");
                            } 
                            if (this.classList.contains("icon-youtube")) {
                                inputEdi.val("https://www.youtube.com/");
                            }
                        }
                    });

                    $(tabRedSocialPF).on("keyup", "td input#txtredSocial",function() {
                        var btnIcon = $(this).parent("td").parent("tr").find("#btnIcon");
                        if (btnIcon.hasClass("icon-facebook")) {
                            arrayRedesPF[0] = this.value;
                        } 
                        if (btnIcon.hasClass("icon-twitter")) {
                            arrayRedesPF[1] = this.value;
                        }
                        if (btnIcon.hasClass("icon-instagram")) {
                            arrayRedesPF[2] = this.value;
                        } 
                        if (btnIcon.hasClass("icon-youtube")) {
                            arrayRedesPF[3] = this.value;
                        }
                    });

                    $(tabRedSocialPF).on("click", "td a#eliminaRedSoc",function() {
                        var inputEdi = $(this).parent("td").parent("tr").find("#txtredSocial");
                        var btnIcon = $(this).parent("td").parent("tr").find("#btnIcon");
                        if (!inputEdi.is(":disabled")) {
                            inputEdi.prop('disabled', true);
                            this.setAttribute("disabled","disabled");
                            btnIcon.removeAttr("disabled");

                            if (btnIcon.hasClass("icon-facebook")) {
                                arrayRedesPF[0] = '';
                            } 
                            if (btnIcon.hasClass("icon-twitter")) {
                                arrayRedesPF[1] = '';
                            }
                            if (btnIcon.hasClass("icon-instagram")) {
                                arrayRedesPF[2] = '';
                            } 
                            if (btnIcon.hasClass("icon-youtube")) {
                                arrayRedesPF[3] = '';
                            }

                            inputEdi.val('');
                        }
                    });
                            
                //persona moral
                    var datGenPM = document.getElementById("datGenPM");

                    var datGenPMHead = document.getElementById("datGenPMHead");
                    var datGenPMBody = document.getElementById("datGenPMBody");
                    var idTaxPM = document.getElementById("idTaxPM");
                    var paisExtPM = document.getElementById("paisExtPM");

                    var txtempresa_reg = document.getElementById("txtempresa_reg");
                    var txtidtax_reg = document.getElementById("txtidtax_reg");
                    var selListaPreciosPM_reg = document.getElementById("selListaPreciosPM_reg");
                    var selPaisExtPM_reg = document.getElementById("selPaisExtPM_reg");
                    var txtnomCom_regPM = document.getElementById("txtnomCom_regPM");
                    var txtsitWeb_regPM = document.getElementById("txtsitWeb_regPM");
                    var tabRedSocialPM = document.getElementById("tabRedSocialPM")
                    var arrayRedesPM = ["","","",""];
                    $(txtempresa_reg).keyup(function(){
                        if (this.value === '' || !strFilEmp.test(this.value)) {
                            errorInput(this,'Inserta empresa');
                        } else {
                            correctoInput(this,'Empresa');
                        }
                        if (vtipoCliente == 'extranjero') {
                            verificaEmpIgualesPM(this);
                        }
                    });

                    function verificaEmpIgualesPM(empresa){
                        console.log(viewPFExt.innerHTML+" "+empresa.value);
                        if (empresa.value.toLowerCase() == viewPFExt.innerHTML.toLowerCase()) {
                            correctoInput(empresa,'Empresa');
                        } else {
                            errorInput(empresa,'Inserta empresa');
                        }
                    }

                    $(txtidtax_reg).keyup(function(){
                        checkCurpTax(this);
                    });

                    $(txtidtax_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!/^[a-zA-Z0-9]+$/.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(selListaPreciosPM_reg).change(function(){
                        checkSelectListaPrecios(this);
                    });

                    $(selPaisExtPM_reg).change(function(){
                        checkSelectPais(this);
                    });

                    $(txtnomCom_regPM).keyup(function(){
                        checkNombreCom(this);
                    });

                    $(txtnomCom_regPM).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtsitWeb_regPM).keyup(function(){
                        checkSitWeb(this);
                    });

                    $(txtsitWeb_regPM).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroUrl.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(tabRedSocialPM).on("click", "td a#btnIconPM",function() {
                        var inputEdiPM = $(this).parent("td").parent("tr").find("#txtredSocialPM");
                        var eliminaRedSocPM = $(this).parent("td").parent("tr").find("#eliminaRedSocPM");
                        if (inputEdiPM.is(":disabled")){
                            this.setAttribute("disabled","disabled");
                            inputEdiPM.removeAttr("disabled");
                            eliminaRedSocPM.removeAttr("disabled");
                            if (this.classList.contains("icon-facebook")) {
                                inputEdiPM.val("https://www.facebook.com/");
                            } 
                            if (this.classList.contains("icon-twitter")) {
                                inputEdiPM.val("https://www.twitter.com/");
                            }
                            if (this.classList.contains("icon-instagram")) {
                                inputEdiPM.val("https://www.instagram.com/");
                            } 
                            if (this.classList.contains("icon-youtube")) {
                                inputEdiPM.val("https://www.youtube.com/");
                            }
                        }
                    });

                    $(tabRedSocialPM).on("keyup", "td input#txtredSocialPM",function() {
                        var btnIconPM = $(this).parent("td").parent("tr").find("#btnIconPM");
                        if (btnIconPM.hasClass("icon-facebook")) {
                            arrayRedesPM[0] = this.value;
                        } 
                        if (btnIconPM.hasClass("icon-twitter")) {
                            arrayRedesPM[1] = this.value;
                        }
                        if (btnIconPM.hasClass("icon-instagram")) {
                            arrayRedesPM[2] = this.value;
                        } 
                        if (btnIconPM.hasClass("icon-youtube")) {
                            arrayRedesPM[3] = this.value;
                        }
                    });

                    $(tabRedSocialPM).on("click", "td a#eliminaRedSocPM",function() {
                        var txtredSocialPM = $(this).parent("td").parent("tr").find("#txtredSocialPM");
                        var btnIconPM = $(this).parent("td").parent("tr").find("#btnIconPM");
                        if (!txtredSocialPM.is(":disabled")) {
                            txtredSocialPM.prop('disabled', true);
                            this.setAttribute("disabled","disabled");
                            btnIconPM.removeAttr("disabled");

                            if (btnIconPM.hasClass("icon-facebook")) {
                                arrayRedesPM[0] = '';
                            } 
                            if (btnIconPM.hasClass("icon-twitter")) {
                                arrayRedesPM[1] = '';
                            }
                            if (btnIconPM.hasClass("icon-instagram")) {
                                arrayRedesPM[2] = '';
                            } 
                            if (btnIconPM.hasClass("icon-youtube")) {
                                arrayRedesPM[3] = '';
                            }
                            txtredSocialPM.val('');
                        }
                    });

                //informacion de contacto
                    var dataContacto = document.getElementById("dataContacto");
                    var collapsible_headerContacto = document.getElementById("collapsible-headerContacto");
                    var collapsible_bodyContacto = document.getElementById("collapsible-bodyContacto");
                    var txtPaternoCont_reg = document.getElementById("txtPaternoCont_reg");
                    var arrayPaternoCont_reg = [];
                    var txtMaternoCont_reg = document.getElementById("txtMaternoCont_reg");
                    var arrayMaternoCont_reg = [];
                    var txtNombreCont_reg = document.getElementById("txtNombreCont_reg");
                    var arrayNombreCont_reg = [];
                    var txtAreaCont_reg = document.getElementById("txtAreaCont_reg");
                    var arrayAreaCont_reg = [];
                    var txtCargoCont_reg = document.getElementById("txtCargoCont_reg");
                    var arrayCargoCont_reg = [];
                    var txtEmailCont_reg = document.getElementById("txtEmailCont_reg");
                    var arrayEmailCont_reg = [];
                    var txtTelefonoCont_reg = document.getElementById("txtTelefonoCont_reg");
                    var arrayTelefonoCont_reg = [];
                    var txtExtension_reg = document.getElementById("txtExtension_reg");
                    var arrayExtension_reg = [];
                    var addInfoContacto = document.getElementById("addInfoContacto");
                    var tbodyDatosConta = document.getElementById("tbodyDatosConta");
                    var tHeadContacto = document.getElementById("tHeadContacto");
                    var trVacioDatCont = document.getElementById("trVacioDatCont");

                    $(txtPaternoCont_reg).keyup(function(){
                        checkPaterno(this);
                    });

                    $(txtPaternoCont_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtMaternoCont_reg).keyup(function(){
                        checkMaterno(this);
                    });

                    $(txtMaternoCont_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtNombreCont_reg).keyup(function(){
                        checkNombre(this);  
                    });

                    $(txtNombreCont_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtAreaCont_reg).keyup(function(){
                        if (this.value === '' || !strFilEmp.test(this.value)) {
                            errorInput(this,'Inserta Area');
                        } else {
                            correctoInput(this,'Area');
                        }
                    });

                    $(txtAreaCont_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtCargoCont_reg).keyup(function(){
                        if (this.value === '' || !strFilter.test(this.value)) {
                            errorInput(this,'Inserta Cargo');
                        } else {
                            correctoInput(this,'Cargo');
                        }
                    });

                    $(txtCargoCont_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtEmailCont_reg).keyup(function(){
                        checkMail(this);
                    });

                    $(txtEmailCont_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilterMail.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $('input[name="telefonopersonal"]').click(function(){
                        //perpm fieldTelefono
                        if (this.value == 'casa/celular') {
                            $(txtTelefonoCont_reg).parent("div").addClass("perpm");
                            $(txtTelefonoCont_reg).parent("div").removeClass("fieldTelefono");
                            var divExt = $(txtExtension_reg).parent("div");
                            if (!divExt.hasClass("noneView")) {
                                divExt.addClass("noneView");
                            }
                        }

                        if (this.value == 'corporativo') {
                            $(txtExtension_reg).parent("div").removeClass("noneView");
                            var divTel = $(txtTelefonoCont_reg).parent("div");
                            if (!divTel.hasClass("fieldTelefono")) {
                                divTel.addClass("fieldTelefono");
                                divTel.removeClass("perpm");
                            }
                        }
                    });

                    $(txtTelefonoCont_reg).keyup(function(){
                        checkTelefono(this);
                    });

                    $(txtTelefonoCont_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!/^[0-9]+$/.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtExtension_reg).keyup(function(){
                        checkExtension(this);
                    });

                    $(txtExtension_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!/^[0-9]+$/.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(addInfoContacto).click(function() {
                        //alert(vtipoCliente);
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas agregar esta información de personal?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                var telefonopersonal = document.querySelector('input[name="telefonopersonal"]:checked'); 
                                if ((txtPaternoCont_reg.value != '' && strFilter.test(txtPaternoCont_reg.value) && txtPaternoCont_reg.value.length >= 4) && 
                                    (txtMaternoCont_reg.value != '' && strFilter.test(txtMaternoCont_reg.value) && txtMaternoCont_reg.value.length >= 4) && 
                                    (txtNombreCont_reg.value != '' && strFilter.test(txtNombreCont_reg.value) && txtNombreCont_reg.value.length >= 3) && 
                                    (txtAreaCont_reg.value != '' && strFilter.test(txtAreaCont_reg.value) && txtAreaCont_reg.value.length >= 2) &&
                                    (txtCargoCont_reg.value != '' && strFilter.test(txtCargoCont_reg.value) && txtCargoCont_reg.value.length >= 5) && 
                                    (txtEmailCont_reg.value != '' && correoRegex.test(txtEmailCont_reg.value) && txtEmailCont_reg.value.length < 320) && 
                                    (txtEmailCont_reg.value.includes('gmail.com') || txtEmailCont_reg.value.includes('hotmail.com') || 
                                    txtEmailCont_reg.value.includes('outlook.com') || txtEmailCont_reg.value.includes('yahoo.com') || 
                                    txtEmailCont_reg.value.includes(txtsitWebPF_reg.value) || txtEmailCont_reg.value.includes(txtsitWeb_regPM.value)) &&
                                    (txtTelefonoCont_reg.value != '' && /^[0-9]+$/.test(txtTelefonoCont_reg.value)) &&
                                    telefonopersonal && telefonopersonal.value != '') {

                                    if (vtipoCliente == 'nacional') {
                                        if (txtTelefonoCont_reg.value.length == 10) {
                                            if (telefonopersonal.value == 'corporativo') {
                                                if ((/^[0-9]+$/.test(txtExtension_reg.value) && txtExtension_reg.value.length >= 1) ) {
                                                    llenaTabCont(txtExtension_reg.value);
                                                    txtExtension_reg.value = '';
                                                } else {
                                                    if (!(/^[0-9]+$/.test(txtExtension_reg.value)) || txtExtension_reg.value.length == 0){
                                                        abreHeaderCont();
                                                        errorInput(txtExtension_reg,'Inserta Extensión');
                                                    }
                                                } 
                                            } else {
                                                llenaTabCont("-");
                                            }
                                        } else {
                                            errorInput(txtTelefonoCont_reg,'Teléfono invalido');
                                        }
                                    }

                                    if (vtipoCliente == 'extranjero') {
                                        if (txtTelefonoCont_reg.value.length >= 5) {
                                            if (telefonopersonal.value == 'corporativo') {
                                                if ((/^[0-9]+$/.test(txtExtension_reg.value) && txtExtension_reg.value.length >= 1) ) {
                                                    llenaTabCont(txtExtension_reg.value);
                                                    txtExtension_reg.value = '';
                                                } else {
                                                    if (!(/^[0-9]+$/.test(txtExtension_reg.value)) || txtExtension_reg.value.length == 0){
                                                        abreHeaderCont();
                                                        errorInput(txtExtension_reg,'Inserta Extensión');
                                                    }
                                                } 
                                            } else {
                                                llenaTabCont("-");
                                            }
                                        } else {
                                            errorInput(txtTelefonoCont_reg,'Teléfono invalido');
                                            return;
                                        }
                                    }

                                } else {
                                    camposVacios();
                                    if(txtPaternoCont_reg.value == '' || !strFilter.test(txtPaternoCont_reg.value) || txtPaternoCont_reg.value.length < 4){
                                        abreHeaderCont();
                                        errorInput(txtPaternoCont_reg,'Inserta Apellido Paterno');
                                    }

                                    if(txtMaternoCont_reg.value == '' || !strFilter.test(txtMaternoCont_reg.value) || txtMaternoCont_reg.value.length < 4){
                                        abreHeaderCont();
                                        errorInput(txtMaternoCont_reg,'Inserta Apellido Materno');
                                    }

                                    if(txtNombreCont_reg.value == '' || !strFilter.test(txtNombreCont_reg.value) || txtNombreCont_reg.value.length < 3){
                                        abreHeaderCont();
                                        errorInput(txtNombreCont_reg,'Inserta Nombre(s)');
                                    }

                                    if(txtAreaCont_reg.value == '' || !strFilter.test(txtAreaCont_reg.value) || txtAreaCont_reg.value.length < 2){
                                        abreHeaderCont();
                                        errorInput(txtAreaCont_reg,'Inserta Area');
                                    }

                                    if(txtCargoCont_reg.value == '' || !strFilter.test(txtCargoCont_reg.value) || txtCargoCont_reg.value.length < 5){
                                        abreHeaderCont();
                                        errorInput(txtCargoCont_reg,'Inserta Cargo');
                                    }

                                    if(txtEmailCont_reg.value == '' || !correoRegex.test(txtEmailCont_reg.value)){
                                        abreHeaderCont();
                                        errorInput(txtEmailCont_reg,'Inserta Email');
                                    }

                                    if(!(txtEmailCont_reg.value.includes('gmail.com') || txtEmailCont_reg.value.includes('hotmail.com') || 
                                        txtEmailCont_reg.value.includes('outlook.com') || txtEmailCont_reg.value.includes('yahoo.com'))){
                                        abreHeaderCont();
                                        errorInput(txtEmailCont_reg,'Inserta Email');
                                    }

                                    if(txtTelefonoCont_reg.value == '' || !(/^[0-9]+$/.test(txtTelefonoCont_reg.value))){
                                        abreHeaderCont();
                                        errorInput(txtTelefonoCont_reg,'Inserta Teléfono');
                                    }

                                    if (telefonopersonal.value == 'corporativo') {
                                        if ((/^[0-9]+$/.test(txtExtension_reg.value) && txtExtension_reg.value.length >= 1) ) {
                                            //llenaTabCont(txtExtension_reg.value);
                                            txtExtension_reg.value = '';
                                        } else {
                                            if (!(/^[0-9]+$/.test(txtExtension_reg.value)) || txtExtension_reg.value.length == 0){
                                                abreHeaderCont();
                                                errorInput(txtExtension_reg,'Inserta Extensión');
                                            }
                                        } 
                                    } else {
                                        //llenaTabCont("-");
                                    }

                                }
                            } 
                        })

                    });

                    function llenaTabCont(extensión){
                        arrayPaternoCont_reg.push(txtPaternoCont_reg.value);
                        arrayMaternoCont_reg.push(txtMaternoCont_reg.value);
                        arrayNombreCont_reg.push(txtNombreCont_reg.value);
                        arrayAreaCont_reg.push(txtAreaCont_reg.value);
                        arrayCargoCont_reg.push(txtCargoCont_reg.value);
                        arrayEmailCont_reg.push(txtEmailCont_reg.value);
                        arrayTelefonoCont_reg.push(txtTelefonoCont_reg.value);
                        arrayExtension_reg.push(extensión);

                        tHeadContacto.classList.remove("btnError");
                        trVacioDatCont.classList.add("noneView");
                        var newTab = document.createElement("tr");
                        var datosContact = '<td>'+txtPaternoCont_reg.value+' '+txtMaternoCont_reg.value+' '+txtNombreCont_reg.value+'</td>'+
                        '<td>'+txtAreaCont_reg.value+'</td><td>'+txtCargoCont_reg.value+'</td>'+
                        '<td>'+txtEmailCont_reg.value+'</td><td>'+txtTelefonoCont_reg.value+' ext. '+extensión+'</td>'+
                        '<td><a id="deleteRegCont" class="btn deleteRegCont waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                        newTab.innerHTML = datosContact;
                        tbodyDatosConta.appendChild(newTab);
                        txtPaternoCont_reg.value = '';
                        txtMaternoCont_reg.value = '';
                        txtNombreCont_reg.value = '';
                        txtAreaCont_reg.value = '';
                        txtCargoCont_reg.value = '';
                        txtEmailCont_reg.value = '';
                        txtTelefonoCont_reg.value = '';
                    }

                    $('#tableDatosContacto').on("click","td a#deleteRegCont",function(){
                        var arregloPos = $(this).parent("td").parent("tr").index();
                        var trElimina = $(this).parent("td").parent("tr");
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas eliminar este registro?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if (tbodyDatosConta.childNodes.length == 4) {
                                    trVacioDatCont.classList.remove("noneView");
                                    trElimina.remove();
                                } else {
                                    trElimina.remove();
                                }
                                arrayPaternoCont_reg.splice(arregloPos-1,1);
                                arrayMaternoCont_reg.splice(arregloPos-1,1);
                                arrayNombreCont_reg.splice(arregloPos-1,1);
                                arrayAreaCont_reg.splice(arregloPos-1,1);
                                arrayCargoCont_reg.splice(arregloPos-1,1);
                                arrayEmailCont_reg.splice(arregloPos-1,1);
                                arrayTelefonoCont_reg.splice(arregloPos-1,1);
                                arrayExtension_reg.splice(arregloPos-1,1);
                                if (arrayPaternoCont_reg.length == 0) {
                                    tHeadContacto.classList.remove("btnError");
                                }
                            } 
                        })
                    });

                //informacion fiscal
                    var datFiscal = document.getElementById("datFiscal");

                    var collapsible_headerfiscal = document.getElementById("collapsible-headerfiscal");
                    var collapsible_bodyfiscal = document.getElementById("collapsible-bodyfiscal");
                    var btnSituFiscal = document.getElementById("btnSituFiscal");
                    var situacion_fiscal = document.getElementById("id_situacionFiscalClient");
                    var imgpdfSitfisClient = document.getElementById("imgpdfSitfisClient");

                    $(situacion_fiscal).change(function(e){
                        var valor = this.value;
                        var boton = btnSituFiscal;
                        if (imgpdfSitfisClient.hasChildNodes()) {
                            imgpdfSitfisClient.removeChild(imgpdfSitfisClient.firstElementChild);
                            imgpdfSitfisClient.classList.add("noneView");
                        }
                        var destino = imgpdfSitfisClient;
                        var carga = document.getElementById("cargaSF");
                        llenarPdfImg(e,valor,boton,destino,carga);
                    });
                    
                    var direccionFiscalAltaExtran = document.getElementById("direccionFiscalAltaExtran");
                    var direccionNacional = document.getElementById("direccionNacionalAltaClient");
                    var arrayClasificacionExt = [];
                    var txtAliasExtClient = document.getElementById("txtAliasExtClient");
                    var arraytxtAliasExtClient = [];
                    var txtCodPostalExtClient = document.getElementById("txtCodPostalExtClient");
                    var arraytxtCodPostalExtClient = [];
                    var txtDireccionExt = document.getElementById("txtDireccExtClient");
                    var arraytxtDireccionExt = [];
                    var addDirFiscalListaExt = document.getElementById("addDirFiscalListaExt");
                    var listaDirFiscalExt = document.getElementById("listaDirFiscalExt");
                    var tHeadDirFiscalExt = document.getElementById("tHeadDirFiscalExt");
                    var tBodyDirFiscalExt = document.getElementById("tBodyDirFiscalExt");
                    var trVaciosFiscalExt = document.getElementById("trVaciosFiscalExt");

                    $(buscaAliasExtFiscalAlta());
                    function buscaAliasExtFiscalAlta(){
                        if(arrayClasificacionExt.length == 0){
                            txtAliasExtClient.value = 'matriz';
                            $(txtAliasExtClient).attr('disabled',true);
                        } else {
                            txtAliasExtClient.value = '';
                            $(txtAliasExtClient).removeAttr('disabled');
                        }
                    }
                    
                    $(txtAliasExtClient).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });
    
                    $(txtAliasExtClient).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDom.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtCodPostalExtClient).keyup(function() {
                        checkPostalExt(this);
                    });

                    $(txtDireccionExt).keyup(function(){
                        checkDireccionExt(this);
                    });

                    $(addDirFiscalListaExt).click(function(){
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas agregar esta dirección?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if ((txtAliasExtClient.value != '' && filtroDom.test(txtAliasExtClient.value)) &&
                                    (txtCodPostalExtClient.value != '' && filtroDom.test(txtCodPostalExtClient.value)) &&
                                    (txtDireccionExt.value != '' && filtroDom.test(txtDireccionExt.value))) {
                                    var nuevotr = document.createElement("tr"); 
                                    tHeadDirFiscalExt.classList.remove("btnError");
                                    trVaciosFiscalExt.classList.add("noneView");
                                    var clasficacion = '';
                                    if (arrayClasificacionExt.length == 0) {
                                        arrayClasificacionExt.push('matriz');
                                        clasficacion = 'matriz';
                                    } else {
                                        nuevotr.setAttribute("id","nuevotr"); 
                                        arrayClasificacionExt.push('sucursal');
                                        clasificacion = 'sucursal ('+txtAliasExtClient.value+')';
                                    }
                                
                                    var datos = '<td>'+clasficacion+'</td><td>'+txtCodPostalExtClient.value+'</td><td>'+txtDireccionExt.value+'</td>'+
                                    '<td><a id="deleteRegDirFiscExt" class="btn waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                                    nuevotr.innerHTML = datos;
                                    tBodyDirFiscalExt.appendChild(nuevotr);
                                    arraytxtAliasExtClient.push(txtAliasExtClient.value);
                                    arraytxtCodPostalExtClient.push(txtCodPostalExtClient.value);
                                    arraytxtDireccionExt.push(txtDireccionExt.value);
                                    borraInputRow(txtAliasExtClient);
                                    borraInputRow(txtCodPostalExtClient);
                                    borraInputRow(txtDireccionExt);
                                    buscaAliasExtFiscalAlta();
                                } else {
                                    if (txtAliasExtClient.value === '' || !filtroDom.test(txtAliasExtClient.value)) {
                                        errorInputRow(txtAliasExtClient);
                                    } 
                                
                                    if (txtCodPostalExtClient.value === '' || !filtroDom.test(txtCodPostalExtClient.value)) {
                                        errorInputRow(txtCodPostalExtClient);
                                    }
                                
                                    if (txtDireccionExt.value === '' || !filtroDom.test(txtDireccionExt.value)) {
                                        errorInputRow(txtDireccionExt);
                                    }
                                }
                            } 
                        })
                    });

                    $(listaDirFiscalExt).on("click","td a#deleteRegDirFiscExt",function(){
                        var trElimina = $(this).parent("td").parent("tr");
                        var arregloPos = $(this).parent("td").parent("tr").index();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas eliminar esta dirección?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if (tBodyDirFiscalExt.childNodes.length == 4) {
                                    trVaciosFiscalExt.classList.remove("noneView");
                                    trElimina.remove();
                                } else {
                                    trElimina.remove();

                                    if (tBodyDirFiscalExt.childNodes.length >= 4) {
                                        var trMatriz = $("#listaDirFiscalExt tbody").find("tr").eq(1);
                                        var primerTd = $(trMatriz).find("td").eq(0);
                                        primerTd.html('matriz');
                                        arrayClasificacionExt[0] = $(trMatriz).find("td").eq(0).html(); 
                                        arraytxtAliasExtClient[0] = $(trMatriz).find("td").eq(0).html(); 
                                        arraytxtCodPostalExtClient[0] = $(trMatriz).find("td").eq(1).html(); 
                                        arraytxtDireccionExt[0] = $(trMatriz).find("td").eq(2).html(); 

                                        if (arrayClasificacionEntExt.length !=0 && arraytxtAliasExtClientEnt != 0 &&
                                            arraytxtCodPostalExtClientEnt.length != 0 && arraytxtDireccionExtEnt.length != 0) {
                                            
                                            if ((arrayClasificacionEntExt[0] == arrayClasificacionExt[0]) &&
                                                (arraytxtAliasExtClientEnt[0] == arraytxtAliasExtClient[0]) &&
                                                (arraytxtCodPostalExtClientEnt[0] == arraytxtCodPostalExtClient[0]) &&
                                                (arraytxtDireccionExtEnt[0] == arraytxtDireccionExt[0])) {
                                                $("#tableDirEntregas tbody tr td a#actRegDirEntrega").attr("disabled",true);
                                            } else {
                                                $("#tableDirEntregas tbody tr td a#actRegDirEntrega").removeAttr('disabled');
                                            }

                                        }

                                    }
                                }
                                arrayClasificacionExt.splice(arregloPos-1,1);
                                arraytxtAliasExtClient.splice(arregloPos-1,1);
                                arraytxtCodPostalExtClient.splice(arregloPos-1,1);
                                arraytxtDireccionExt.splice(arregloPos-1,1);
                                buscaAliasExtFiscalAlta();
                            }
                        })
                    });

                    var arrayClasificacion = [];
                    var txtalias_reg = document.getElementById("txtalias_reg");
                    var arraytxtalias_reg = [];
                    var txtcalle_reg = document.getElementById("txtcalle_reg");
                    var arraytxtcalle_reg = [];
                    var txtnumext_reg = document.getElementById("txtnumext_reg");
                    var arraytxtnumext_reg = [];
                    var txtnumint_reg = document.getElementById("txtnumint_reg");
                    var arraytxtnumint_reg = [];
                    var txtcPostalFiscal = document.getElementById("txtcPostalFiscal");
                    var arraytxtcPostalFiscal = [];
                    var txtcolFiscal = document.getElementById("txtcolFiscal");
                    var arraytxtcolFiscal = [];
                    var txtdelFiscal = document.getElementById("txtdelFiscal"); 
                    var arraytxtdelFiscal = [];
                    var txtentFiscal = document.getElementById("txtentFiscal");
                    var arraytxtentFiscal = [];
                    var txtlocalidad_reg = document.getElementById("txtlocalidad_reg");
                    var arraytxtlocalidad_reg = [];
                    var txtcalle1ref_reg = document.getElementById("txtcalle1ref_reg");
                    var arraytxtcalle1ref_reg = [];
                    var txtcalle2ref_reg = document.getElementById("txtcalle2ref_reg");
                    var arraytxtcalle2ref_reg = [];
                    var txtreferencia_reg = document.getElementById("txtreferencia_reg");
                    var arraytxtreferencia_reg = [];
                    var addDirFiscalLista = document.getElementById("addDirFiscalLista");
                    var listaDirFiscal = document.getElementById("listaDirFiscal");
                    var tHeadDirFiscal = document.getElementById("tHeadDirFiscal");
                    var tBodyDirFiscal = document.getElementById("tBodyDirFiscal");
                    var trVaciosFiscal = document.getElementById("trVaciosFiscal");

                    $(buscaAliasFiscalAlta());
                    function buscaAliasFiscalAlta(){
                        if(arrayClasificacion.length == 0){
                            txtalias_reg.value = 'matriz';
                            $(txtalias_reg).attr('disabled',true);
                        } else {
                            txtalias_reg.value = '';
                            $(txtalias_reg).removeAttr('disabled');
                        }
                    }

                    $(txtalias_reg).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });
    
                    $(txtalias_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDom.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtcalle_reg).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtcalle_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDom.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtnumext_reg).keyup(function(){
                        if (this.value === '' || !filtroDomNum.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtnumext_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDomNum.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtnumint_reg).keyup(function(){
                        if (this.value == '' || !(filtroDomNum.test(this.value)) ) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtnumint_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDomNum.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(buscarColonia());
                    function buscarColonia(cpostal) {
                        $.ajax({
                            url: 'ingresos-buscacoldir',
                            type: 'POST',
                            datatype: 'html',
                            data: {
                                cpostal: cpostal,
                            },
                        })
                        .done(function(respuesta){
                            $("#txtcolFiscal").html(respuesta);
                            $('select').material_select();
                        })
                        .fail(function(){
                        console.log("error");
                        })
                    };
    
                    $(txtcPostalFiscal).keyup(function(){
                        var cpostal = this.value;
                        if (cpostal != '' && /^[0-9]+$/.test(this.value) && this.value.length == 5) {
                            correctoInputRow(this);
                            txtcolFiscal.value = '';
                            txtdelFiscal.value = '';
                            txtentFiscal.value = '';
                            buscarColonia(cpostal);
                            $(txtcolFiscal).removeAttr('disabled');
                        } else {
                            errorInputRow(this);
                            buscarColonia(); 
                        }
                    });

                    $(txtcPostalFiscal).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!/^[0-9]+$/.test(clave) || clave.length > 5) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtcolFiscal).change(function(){
                        if ((this.value != '' && filtroDom.test(this.value)) &&
                            (txtcPostalFiscal.value != '' && /^[0-9]+$/.test(txtcPostalFiscal.value) && txtcPostalFiscal.value.length == 5)) {
                            correctoSelectRow(this);
                            txtcolFiscal.focus()
                            $.ajax({
                                url: 'ingresos-buscadelegdirmun',
                                type: 'POST',
                                datatype: 'html',
                                data: {
                                    cpostal: txtcPostalFiscal.value,
                                    colonia: txtcolFiscal.value
                                },
                            })
                            .done(function(respuesta){
                                if (respuesta == 'notFoundData') {
                                    var $toastContent = $('<div class="btnError">¡operación no realizada, intente nuevamente ó comuniquese con soporte!</div>');
                                    Materialize.toast($toastContent,5000);
                                } else {
                                    correctoSelectRow(txtcolFiscal);
                                    var resultado = respuesta.split('||');
                                    txtdelFiscal.classList.add("noneView");
                                    setTimeout(txtdelFiscal.classList.remove("noneView"), 5000);
                                    txtdelFiscal.value = resultado[0];
                                    setTimeout(txtentFiscal.classList.add("noneView"), 3000);
                                    txtentFiscal.classList.remove("noneView")
                                    txtentFiscal.value = resultado[1];
                                }
                            })
                            .fail(function(){
                                errorSelectRow(txtcolFiscal);
                                var $toastContent = $('<div class="btnError">¡operación no realizada, intente nuevamente ó comuniquese con soporte!</div>');
                                Materialize.toast($toastContent,5000);
                            })
                        } else {
                            errorSelectRow(this);
                        }
                    });

                    $(txtlocalidad_reg).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtlocalidad_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDom.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtcalle1ref_reg).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtcalle1ref_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDom.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtcalle2ref_reg).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtcalle2ref_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDom.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtreferencia_reg).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtreferencia_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDom.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(addDirFiscalLista).click(function(){
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas agregar esta dirección?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if ((txtalias_reg.value != '' && filtroDom.test(txtalias_reg.value)) && 
                                    (txtcalle_reg.value != '' && filtroDom.test(txtcalle_reg.value)) && 
                                    (txtnumext_reg.value != '' && filtroDomNum.test(txtnumext_reg.value)) &&  
                                    (txtcPostalFiscal.value != '' && /^[0-9]+$/.test(txtcPostalFiscal.value) && txtcPostalFiscal.value.length == 5) && 
                                    (txtcolFiscal.value != '' && filtroDom.test(txtcolFiscal.value)) && 
                                    (txtdelFiscal.value != '' && filtroDom.test(txtdelFiscal.value)) && 
                                    (txtentFiscal.value != '' && filtroDom.test(txtentFiscal.value)) && 
                                    (txtlocalidad_reg.value != '' && filtroDom.test(txtlocalidad_reg.value)) && 
                                    (txtcalle1ref_reg.value != '' && filtroDom.test(txtcalle1ref_reg.value)) && 
                                    (txtcalle2ref_reg.value != '' && filtroDom.test(txtcalle2ref_reg.value)) && 
                                    (txtreferencia_reg.value != '' && filtroDom.test(txtreferencia_reg.value))) {
                                    
                                    if (txtnumint_reg.value != '' && filtroDomNum.test(txtnumint_reg.value)) {
                                        llenarDisfiscTab(txtalias_reg.value,txtcalle_reg.value,txtnumext_reg.value,txtnumint_reg.value,txtcPostalFiscal.value,
                                            txtcolFiscal.value,txtdelFiscal.value,txtentFiscal.value,txtlocalidad_reg.value,
                                            txtcalle1ref_reg.value,txtcalle2ref_reg.value,txtreferencia_reg.value);
                                    } else {
                                        llenarDisfiscTab(txtalias_reg.value,txtcalle_reg.value,txtnumext_reg.value,'-',txtcPostalFiscal.value,
                                            txtcolFiscal.value,txtdelFiscal.value,txtentFiscal.value,txtlocalidad_reg.value,
                                            txtcalle1ref_reg.value,txtcalle2ref_reg.value,txtreferencia_reg.value);
                                    }
                                
                                    borraInputRow(txtalias_reg);
                                    borraInputRow(txtcalle_reg);
                                    borraInputRow(txtnumext_reg);
                                    borraInputRow(txtnumint_reg);
                                    borraInputRow(txtcPostalFiscal);
                                    borraInputRow(txtcolFiscal);
                                    buscarColonia();
                                    $(txtcolFiscal).attr("disabled",true);
                                    txtcolFiscal.selectedIndex = 0;
                                    txtcolFiscal.value = '';
                                    $('select').material_select();
                                    borraInputRow(txtdelFiscal);
                                    borraInputRow(txtentFiscal);
                                    borraInputRow(txtlocalidad_reg);
                                    borraInputRow(txtcalle1ref_reg);
                                    borraInputRow(txtcalle2ref_reg);
                                    borraInputRow(txtreferencia_reg);
                                
                                } else {
                                    if (txtalias_reg.value == '' || !filtroDom.test(txtalias_reg.value)) {
                                        errorInputRow(txtalias_reg);
                                    }
                                    if (txtcalle_reg.value == '' || !filtroDom.test(txtcalle_reg.value)) {
                                        errorInputRow(txtcalle_reg);
                                    } 
                                    if (txtnumext_reg.value == '' || !filtroDomNum.test(txtnumext_reg.value)) {
                                        errorInputRow(txtnumext_reg);
                                    } 
                                    if (txtcPostalFiscal.value == '' || !/^[0-9]+$/.test(txtcPostalFiscal.value) || !txtcPostalFiscal.value.length == 5){
                                        errorInputRow(txtcPostalFiscal);
                                    } 
                                    if (txtcolFiscal.value == '' || !filtroDom.test(txtcolFiscal.value)){
                                        errorSelectRow(txtcolFiscal);
                                    } 
                                    if (txtdelFiscal.value == '' || !filtroDom.test(txtdelFiscal.value)){
                                        errorInputRow(txtdelFiscal);
                                    } 
                                    if (txtentFiscal.value == '' || !filtroDom.test(txtentFiscal.value)){
                                        errorInputRow(txtentFiscal);
                                    } 
                                    if (txtlocalidad_reg.value == '' || !filtroDom.test(txtlocalidad_reg.value)) {
                                        errorInputRow(txtlocalidad_reg);
                                    } 
                                    if (txtcalle1ref_reg.value == '' || !filtroDom.test(txtcalle1ref_reg.value)) {
                                        errorInputRow(txtcalle1ref_reg);
                                    } 
                                    if (txtcalle2ref_reg.value == '' || !filtroDom.test(txtcalle2ref_reg.value)) {
                                        errorInputRow(txtcalle2ref_reg);
                                    } 
                                    if (txtreferencia_reg.value == '' || !filtroDom.test(txtreferencia_reg.value)) {
                                        errorInputRow(txtreferencia_reg);
                                    }
                                }
                            } 
                        })
                    });

                    function llenarDisfiscTab(alias,calle,ext,int,cPstal,cOlonia,deleg,enti,localidad,c1,c2,refer){
                        var nuevotr = document.createElement("tr"); 
                        var clasficacion = '';
                        tHeadDirFiscal.classList.remove("btnError");
                        trVaciosFiscal.classList.add("noneView");
                        if (arraytxtcalle_reg.length == 0) {
                            arrayClasificacion.push('matriz');
                            clasficacion = 'matriz';
                        } else {
                            nuevotr.setAttribute("id","nuevotr"); 
                            arrayClasificacion.push('sucursal');
                            clasificacion = 'sucursal ('+alias+')';
                        }
                        var datos = '<td>'+clasficacion+'</td><td>'+calle+'</td> <td>'+ext+'</td><td>'+int+'</td><td>'+cPstal+'</td>'+
                                    '<td>'+cOlonia+'</td><td>'+deleg+'</td><td>'+enti+'</td><td>'+localidad+'</td>'+
                                    '<td>'+c1+'</td><td>'+c2+'</td><td>'+refer+'</td>'+
                                    '<td><a id="deleteRegDirFisc" class="btn waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                        nuevotr.innerHTML = datos;
                        tBodyDirFiscal.appendChild(nuevotr);
                        arraytxtalias_reg.push(alias);
                        arraytxtcalle_reg.push(calle);
                        arraytxtnumext_reg.push(ext);
                        arraytxtnumint_reg.push(int);
                        arraytxtcPostalFiscal.push(cPstal);
                        arraytxtcolFiscal.push(cOlonia);
                        arraytxtdelFiscal.push(deleg);
                        arraytxtentFiscal.push(enti);
                        arraytxtlocalidad_reg.push(localidad);
                        arraytxtcalle1ref_reg.push(c1);
                        arraytxtcalle2ref_reg.push(c2);
                        arraytxtreferencia_reg.push(refer);
                        buscaAliasFiscalAlta();
                    }

                    $(listaDirFiscal).on("click","td a#deleteRegDirFisc",function(){
                        var trElimina = $(this).parent("td").parent("tr");
                        var arregloPos = $(this).parent("td").parent("tr").index();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas eliminar esta dirección?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if (tBodyDirFiscal.childNodes.length == 4) {
                                    trVaciosFiscal.classList.remove("noneView");
                                    trElimina.remove();
                                } else {
                                    trElimina.remove();
                                    if (tBodyDirFiscal.childNodes.length >= 4) {
                                        var trMatriz = $("#listaDirFiscal tbody").find("tr").eq(1);
                                        var primerTd = $(trMatriz).find("td").eq(0);
                                        primerTd.html('matriz');
                                        arrayClasificacion[0] = $(trMatriz).find("td").eq(0).html(); 
                                        arraytxtalias_reg[0] = $(trMatriz).find("td").eq(0).html(); 
                                        arraytxtcalle_reg[0] = $(trMatriz).find("td").eq(1).html(); 
                                        arraytxtnumext_reg[0] = $(trMatriz).find("td").eq(2).html(); 
                                        arraytxtnumint_reg[0] = $(trMatriz).find("td").eq(3).html(); 
                                        arraytxtcPostalFiscal[0] = $(trMatriz).find("td").eq(4).html(); 
                                        arraytxtcolFiscal[0] = $(trMatriz).find("td").eq(5).html(); 
                                        arraytxtdelFiscal[0] = $(trMatriz).find("td").eq(6).html(); 
                                        arraytxtentFiscal[0] = $(trMatriz).find("td").eq(7).html(); 
                                        arraytxtlocalidad_reg[0] = $(trMatriz).find("td").eq(8).html(); 
                                        arraytxtcalle1ref_reg[0] = $(trMatriz).find("td").eq(9).html(); 
                                        arraytxtcalle2ref_reg[0] = $(trMatriz).find("td").eq(10).html(); 
                                        arraytxtreferencia_reg[0] = $(trMatriz).find("td").eq(11).html(); 
                                        if (arrayClasificacionEnt.length !=0 && arrayalias_regEnt.length != 0 && 
                                            arraycalle_regEnt.length != 0 && arraynumext_regEnt.length != 0 && 
                                            arraynumint_regEnt.length != 0 && arrayCodPostalEnt.length != 0 && 
                                            arraycolEnt.length != 0 && arraydelegEnt.length != 0 && 
                                            arrayentidadEnt.length != 0 && arraylocalidad_regEnt.length != 0 && 
                                            arraycalle1ref_regEnt.length != 0 && arraycalle2ref_regEnt.length != 0 && 
                                            arrayreferencia_regEnt.length != 0) {
                                            
                                            if ((arrayClasificacionEnt[0] == arrayClasificacion[0]) &&
                                                (arrayalias_regEnt[0] == arraytxtalias_reg[0]) &&
                                                (arraycalle_regEnt[0] == arraytxtcalle_reg[0]) &&
                                                (arraynumext_regEnt[0] == arraytxtnumext_reg[0]) &&
                                                (arraynumint_regEnt[0] == arraytxtnumint_reg[0]) &&
                                                (arrayCodPostalEnt[0] == arraytxtcPostalFiscal[0]) &&
                                                (arraycolEnt[0] == arraytxtcolFiscal[0]) &&
                                                (arraydelegEnt[0] == arraytxtdelFiscal[0]) &&
                                                (arrayentidadEnt[0] == arraytxtentFiscal[0]) &&
                                                (arraylocalidad_regEnt[0] == arraytxtlocalidad_reg[0]) &&
                                                (arraycalle1ref_regEnt[0] == arraytxtcalle1ref_reg[0]) &&
                                                (arraycalle2ref_regEnt[0] == arraytxtcalle2ref_reg[0]) &&
                                                (arrayreferencia_regEnt[0] == arraytxtreferencia_reg[0])) {
                                                $("#tableDirEntregas tbody tr td a#actRegDirEntrega").attr("disabled",true);
                                            } else {
                                                $("#tableDirEntregas tbody tr td a#actRegDirEntrega").removeAttr('disabled');
                                            }

                                        }

                                    }
                                }
                                arrayClasificacion.splice(arregloPos-1,1);
                                arraytxtalias_reg.splice(arregloPos-1,1);
                                arraytxtcalle_reg.splice(arregloPos-1,1);
                                arraytxtnumext_reg.splice(arregloPos-1,1);
                                arraytxtnumint_reg.splice(arregloPos-1,1);
                                arraytxtcPostalFiscal.splice(arregloPos-1,1);
                                arraytxtcolFiscal.splice(arregloPos-1,1);
                                arraytxtdelFiscal.splice(arregloPos-1,1);
                                arraytxtentFiscal.splice(arregloPos-1,1);
                                arraytxtlocalidad_reg.splice(arregloPos-1,1);
                                arraytxtcalle1ref_reg.splice(arregloPos-1,1);
                                arraytxtcalle2ref_reg.splice(arregloPos-1,1);
                                arraytxtreferencia_reg.splice(arregloPos-1,1);
                                buscaAliasFiscalAlta();
                            }
                        })
                    });

                //credito
                    var lidatacredito = document.getElementById("lidatacredito");

                    var collapsible_headerCredito = document.getElementById("collapsible-headerCredito");
                    var collapsible_bodyCredito = document.getElementById("collapsible-bodyCredito");
                    var divcreditoClient = document.getElementById("divcreditoClient");

                    var serrorCred = document.getElementById("serrorCred");

                    var txtMoneda_reg = document.getElementById("txtMoneda_reg");
                    var limiteCredito = document.getElementById("txtlimiteCredito_reg");
                    var txtdiaspagoCredit_reg = document.getElementById("txtdiaspagoCredit_reg");
                    var selectComienzaPago = document.getElementById("selectComienzaPago");

                    $('input[name="aceptaCreditoClient"]').click(function(){
                        if (!serrorCred.classList.contains("noneView")){
                            serrorCred.classList.add("noneView");
                        }
                        if (this.value == "si") {
                            divcreditoClient.classList.remove("noneView");
                            divcreditoClient.classList.add("credito");
                            txtMoneda_reg.required = true;
                            limiteCredito.required = true;
                            txtdiaspagoCredit_reg.required = true;
                            selectComienzaPago.required = true;
                        }
                        if (this.value == "no") {
                            txtMoneda_reg.required = false;
                            limiteCredito.required = false;
                            txtdiaspagoCredit_reg.required = false;
                            selectComienzaPago.required = false;
                            txtMoneda_reg.value = '';
                            limiteCredito.value = '';

                            selectComienzaPago.selectedIndex = 0;
                            selectComienzaPago.value = '';
                            $('#selectComienzaPago').prop('readonly', false);
                            $('select').material_select();

                            txtdiaspagoCredit_reg.value = '';
                            divcreditoClient.classList.remove("credito");
                            divcreditoClient.classList.add("noneView");
                        }
                    });

                    $(txtMoneda_reg).keyup(function(){
                        if (this.value === '' || !strFilEmp.test(this.value) || !this.value.length >= 5) {
                            errorInput(this,'Inserta Moneda');
                        } else {
                            correctoInput(this,'Moneda');
                        }
                    });

                    $(txtMoneda_reg).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilEmp.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(limiteCredito).keyup(function(){
                        if (this.value === '' || !(/^[0-9]+$/.test(this.value)) || !this.value.length >= 1) {
                            errorInput(this,'Inserta Límite de credito');
                        } else {
                            correctoInput(this,'Límite de credito');
                        }
                    });

                    $(limiteCredito).change(function(){
                        var nuMoneda = numeral(this.value);
                        limiteCredito.value = nuMoneda.format('$0,0.00');
                        console.log(nuMoneda.format('$0,0.00'));
                    });
                
                    $(limiteCredito).keypress(function(e){
                        if (!soloNumeros(event)) {
                            e.preventDefault();
                        }
                    });

                    $(txtdiaspagoCredit_reg).keyup(function(){
                        if (this.value === '' || !(/^[0-9]+$/.test(this.value)) || !this.value.length >= 1) {
                            errorInput(this,'Inserta Días de pago');
                        } else {
                            correctoInput(this,'Días de pago');
                        }
                    });
                
                    $(txtdiaspagoCredit_reg).keypress(function(e){
                        if (!soloNumeros(event)) {
                            e.preventDefault();
                        }
                    });

                    $(selectComienzaPago).change(function(){
                        if (this.value == '' || !(/^[a-z]+$/.test(this.value))) {
                            errorSelect(this,'Selecciona una opción');
                        } else {
                            correctoSelect(this,'Comienza a partir de:');
                        }
                    });

                //forma de pago
                    var lidataformapago = document.getElementById("lidataformapago");

                    var collapsible_headerfPago = document.getElementById("collapsible-headerfPago");
                    var collapsible_bodyfPago = document.getElementById("collapsible-bodyfPago");
                    var divTransferClient = document.getElementById("divTransferClient");
                    var btnEstadoCuenta = document.getElementById("btnEstadoCuenta");
                    var imgpdfEstadCuenta = document.getElementById("imgpdfEstadCuenta");

                    var formaPago = document.getElementById("formaPago");
                    var estado_cuenta = document.getElementById("estado_cuenta");
                    var clabeIntBanc = document.getElementById("txtClabeIntBanc_reg");

                    $(formaPago).change(function(){
                        if (this.value == '') {
                            errorSelect(this,'Selecciona forma de pago');
                        } else {
                            correctoSelect(this,'Forma de pago');
                            if (this.value == 3) {
                                divTransferClient.classList.remove("noneView");
                                divTransferClient.classList.add("transferencia");
                                clabeIntBanc.required = true;
                                estado_cuenta.required = true;
                            } else {
                                if (divTransferClient.classList.contains("transferencia")) {
                                    divTransferClient.classList.remove("transferencia");
                                    divTransferClient.classList.add("noneView");
                                }
                                if (clabeIntBanc.required = true) {
                                    clabeIntBanc.required = false;
                                }
                                if(estado_cuenta.required = true){
                                    estado_cuenta.required = false;
                                }
                            }
                        }
                    });

                    $(clabeIntBanc).keyup(function(){
                        if (this.value === '' || !(/^[0-9-]+$/.test(this.value))) {
                            errorInput(this,'Inserta Clabe interbancaria');
                        } else {
                            correctoInput(this,'Clabe interbancaria');
                        }
                    });
                
                    $(clabeIntBanc).change(function(){
                        var cdna1 = clabeIntBanc.value.substring(0,3);
                        var cdna2 = clabeIntBanc.value.substring(3,6);
                        var cdna3 = clabeIntBanc.value.substring(6,17);
                        var cdna4 = clabeIntBanc.value.substring(17,18);
                        //clabeIntBanc.value = cdna1+'-'+cdna2+'-'+cdna3+'-'+cdna4;
                    });

                    $(clabeIntBanc).keypress(function(e){
                        if (!soloNumeros(e)) {
                            e.preventDefault();
                        }
                    }); 
                
                    $(estado_cuenta).change(function(e){
                        var valor = this.value;
                        var boton = btnEstadoCuenta;
                        if (imgpdfEstadCuenta.hasChildNodes()) {
                            imgpdfEstadCuenta.removeChild(imgpdfEstadCuenta.firstElementChild);
                            imgpdfEstadCuenta.classList.add("noneView");
                        }
                        var destino = imgpdfEstadCuenta;
                        var carga = document.getElementById("cargaEstCuenta");
                        llenarPdfImg(e,valor,boton,destino,carga);
                    });

                //direccion de Entursal
                    var lidirEntregas = document.getElementById("lidirEntregas");
                    var collapsible_headerDirEnt = document.getElementById("collapsible-headerDirEnt");
                    var collapsible_bodyDirEnt = document.getElementById("collapsible-bodyDirEnt");
                    var serrorDirEnt = document.getElementById("serrorDirEnt");
                    var btnReturnaEntregar = document.getElementById("btnReturnaEntregar");
                    var radioEntregasSelect = document.getElementById("radioEntregasSelect");

                    $('input[name="direccfisClient"]').click(function(){
                        if (!serrorDirEnt.classList.contains("noneView")){
                            serrorDirEnt.classList.add("noneView");
                        }
                        
                        if (this.value == "mismaDireccion") {
                            if (vtipoCliente == 'nacional') {
                                direccionEntregasExtra.classList.add("noneView");
                                direccionesEntNacion.classList.add("noneView");
                                
                                if (arrayClasificacion.length != 0 && arraytxtalias_reg.length != 0 && 
                                    arraytxtcalle_reg.length != 0 && arraytxtnumext_reg.length != 0 && 
                                    arraytxtnumint_reg.length != 0 && arraytxtcPostalFiscal.length != 0 && 
                                    arraytxtcolFiscal.length != 0 && arraytxtdelFiscal.length != 0 && 
                                    arraytxtentFiscal.length != 0 && arraytxtlocalidad_reg.length != 0 && 
                                    arraytxtcalle1ref_reg.length != 0 && arraytxtcalle2ref_reg.length != 0 && 
                                    arraytxtreferencia_reg.length != 0) {

                                    if ((arrayClasificacionEnt[0] != arrayClasificacion[0]) &&
                                        (arrayalias_regEnt[0] != arraytxtalias_reg[0]) &&
                                        (arraycalle_regEnt[0] != arraytxtcalle_reg[0]) &&
                                        (arraynumext_regEnt[0] != arraytxtnumext_reg[0]) &&
                                        (arraynumint_regEnt[0] != arraytxtnumint_reg[0]) &&
                                        (arrayCodPostalEnt[0] != arraytxtcPostalFiscal[0]) &&
                                        (arraycolEnt[0] != arraytxtcolFiscal[0]) &&
                                        (arraydelegEnt[0] != arraytxtdelFiscal[0]) &&
                                        (arrayentidadEnt[0] != arraytxtentFiscal[0]) &&
                                        (arraylocalidad_regEnt[0] != arraytxtlocalidad_reg[0]) && 
                                        (arraycalle1ref_regEnt[0] != arraytxtcalle1ref_reg[0]) &&
                                        (arraycalle2ref_regEnt[0] != arraytxtcalle2ref_reg[0]) &&
                                        (arrayreferencia_regEnt[0] != arraytxtreferencia_reg[0])){
                                        radioEntregasSelect.classList.add("noneView");
                                        direccionEntregasExtra.classList.add("noneView");
                                        direccionesEntNacion.classList.remove("noneView");
                                        btnReturnaEntregar.classList.add("noneView");

                                        arrayClasificacionEnt[0] = arrayClasificacion[0];
                                        arrayalias_regEnt[0] = arraytxtalias_reg[0];
                                        arraycalle_regEnt[0] = arraytxtcalle_reg[0];
                                        arraynumext_regEnt[0] = arraytxtnumext_reg[0];
                                        arraynumint_regEnt[0] = arraytxtnumint_reg[0];
                                        arrayCodPostalEnt[0] = arraytxtcPostalFiscal[0];
                                        arraycolEnt[0] = arraytxtcolFiscal[0];
                                        arraydelegEnt[0] = arraytxtdelFiscal[0];
                                        arrayentidadEnt[0] = arraytxtentFiscal[0];
                                        arraylocalidad_regEnt[0] = arraytxtlocalidad_reg[0];
                                        arraycalle1ref_regEnt[0] = arraytxtcalle1ref_reg[0];
                                        arraycalle2ref_regEnt[0] = arraytxtcalle2ref_reg[0];
                                        arrayreferencia_regEnt[0] = arraytxtreferencia_reg[0];

                                        tHeadEntregas.classList.remove("btnError");
                                        trVaciosEntregas.classList.add("noneView");
                                        var nuevotr = document.createElement("tr"); 
                                        nuevotr.setAttribute("id","trMatrizFiscalEnt"); 
                                        var datos = '<td>matriz fiscal</td><td>'+arraycalle_regEnt[0]+'</td><td>'+arraynumext_regEnt[0]+'</td><td>'+arraynumint_regEnt[0]+'</td>'+
                                            '<td>'+arrayCodPostalEnt[0]+'</td><td>'+arraycolEnt[0]+'</td><td>'+arraydelegEnt[0]+'</td>'+
                                            '<td>'+arrayentidadEnt[0]+'</td><td>'+arraylocalidad_regEnt[0]+'</td><td>'+arraycalle1ref_regEnt[0]+'</td>'+
                                            '<td>'+arraycalle2ref_regEnt[0]+'</td><td>'+ arrayreferencia_regEnt[0]+'</td>'+
                                            '<td><a id="actRegDirEntrega" class="btn waves-effect btn-floating waves-light blue-grey lighten-3" disabled>&#xf1f8;</a></td>'+
                                            '<td><a id="deleteRegDirEntrega" class="btn waves-effect btn-floating waves-light red darken-2" disabled>&#xf1f8;</a></td>';
                                        nuevotr.innerHTML = datos;
                                        tBodyEntregas.appendChild(nuevotr);
                                        buscaAliasEntAlta();
                                    } else {
                                        btnReturnaEntregar.classList.remove("noneView");
                                    }
                                    
                                } else {
                                    var $toastContent = $('<div class="btnError">Agrega una dirección fiscal</div>');
                                    Materialize.toast($toastContent,5000);  
                                    this.checked = false; 
                                }
                            }

                            if (vtipoCliente == 'extranjero') {
                                direccionEntregasExtra.classList.add("noneView");
                                direccionesEntNacion.classList.add("noneView");
                                if (arrayClasificacionExt.length != 0 && arraytxtCodPostalExtClient.length != 0 && arraytxtDireccionExt.length != 0) {
                                    
                                    if ((arrayClasificacionEntExt[0] != arrayClasificacionExt[0]) &&
                                        (arraytxtAliasExtClientEnt[0] != arraytxtAliasExtClient[0]) &&
                                        (arraytxtCodPostalExtClientEnt[0] != arraytxtCodPostalExtClient[0]) &&
                                        (arraytxtDireccionExtEnt[0] != arraytxtDireccionExt[0])){
                                        radioEntregasSelect.classList.add("noneView");
                                        direccionesEntNacion.classList.add("noneView");
                                        direccionEntregasExtra.classList.remove("noneView");
                                        //btnReturnaEntregar.classList.remove("noneView");

                                        arrayClasificacionEntExt[0] = arrayClasificacionExt[0];
                                        arraytxtAliasExtClientEnt[0] = arraytxtAliasExtClient[0];
                                        arraytxtCodPostalExtClientEnt[0] = arraytxtCodPostalExtClient[0];
                                        arraytxtDireccionExtEnt[0] = arraytxtDireccionExt[0];

                                        tHeadExtEntregas.classList.remove("btnError");
                                        trVaciosExtEntregas.classList.add("noneView");
                                        var nuevotr = document.createElement("tr"); 
                                        nuevotr.setAttribute("id","trMatrizFiscalEnt"); 
                                        var datos = '<td>matriz fiscal</td><td>'+arraytxtCodPostalExtClientEnt[0]+'</td><td>'+arraytxtDireccionExtEnt[0]+'</td>'+
                                            '<td><a id="actRegDirExtEntrega" class="btn waves-effect btn-floating waves-light blue-grey lighten-3" disabled>&#xf1f8;</a></td>'+
                                            '<td><a id="deleteRegDirExtEnt" class="btn waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                                        nuevotr.innerHTML = datos;
                                        tBodyExtEntregas.appendChild(nuevotr);
                                        buscaAliasExtEntregasAlta();
                                    } else {
                                        btnReturnaEntregar.classList.remove("noneView");
                                    }
                                    
                                } else {
                                    var $toastContent = $('<div class="btnError">Agrega una dirección fiscal</div>');
                                    Materialize.toast($toastContent,5000);  
                                    this.checked = false; 
                                }
                            }
                        }

                        if (this.value == "otraDireccion") {
                            btnReturnaEntregar.classList.remove("noneView");
                            if (vtipoCliente == 'nacional') {
                                radioEntregasSelect.classList.add("noneView");
                                direccionEntregasExtra.classList.add("noneView");
                                direccionesEntNacion.classList.remove("noneView");
                                buscaAliasEntAlta();
                            }

                            if (vtipoCliente == 'extranjero') {
                                radioEntregasSelect.classList.add("noneView");
                                direccionesEntNacion.classList.add("noneView");
                                direccionEntregasExtra.classList.remove("noneView");
                                buscaAliasExtEntregasAlta();
                            }
                        }
                    });

                    $(btnReturnaEntregar).click(function(){
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas regresar al menu anterior?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                btnReturnaEntregar.classList.add("noneView");
                                direccionesEntNacion.classList.add("noneView");
                                direccionEntregasExtra.classList.add("noneView");
                                radioEntregasSelect.classList.remove("noneView");
                            } 
                        })
                    });

                    var direccionEntregasExtra = document.getElementById("direccionEntregasExtra");
                    var arrayClasificacionEntExt = [];
                    var txtAliasEntExt = document.getElementById("txtAliasEntExt");
                    var arraytxtAliasExtClientEnt = [];
                    var txtCodPostalEntExt = document.getElementById("txtCodPostalEntExt");
                    var arraytxtCodPostalExtClientEnt = [];
                    var txtDireccionEntExt = document.getElementById("txtDireccionEntExt");
                    var arraytxtDireccionExtEnt = [];
                    var addDirFiscalListaExtEnt = document.getElementById("addDirFiscalListaExtEnt");
                    var tableDirExtEntregas = document.getElementById("tableDirExtEntregas"); 
                    var tHeadExtEntregas = document.getElementById("tHeadExtEntregas"); 
                    var tBodyExtEntregas = document.getElementById("tBodyExtEntregas"); 
                    var trVaciosExtEntregas = document.getElementById("trVaciosExtEntregas");

                    //$(buscaAliasExtEntregasAlta());
                    function buscaAliasExtEntregasAlta(){
                        if(arrayClasificacionEntExt.length == 0){
                            txtAliasEntExt.value = 'matriz';
                            $(txtAliasEntExt).attr('disabled',true);
                        } else {
                            txtAliasEntExt.value = '';
                            $(txtAliasEntExt).removeAttr('disabled');
                        }
                    }
                    
                    $(txtAliasEntExt).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });
    
                    $(txtAliasEntExt).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDom.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtCodPostalEntExt).keyup(function() {
                        checkPostalExt(this);
                    });

                    $(txtDireccionEntExt).keyup(function(){
                        checkDireccionExt(this);
                    });

                    $(addDirFiscalListaExtEnt).click(function(){
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas agregar esta dirección?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if ((txtAliasEntExt.value != '' && filtroDom.test(txtAliasEntExt.value)) &&
                                    (txtCodPostalEntExt.value != '' && filtroDom.test(txtCodPostalEntExt.value)) &&
                                    (txtDireccionEntExt.value != '' && filtroDom.test(txtDireccionEntExt.value))) {
                                    var nuevotr = document.createElement("tr"); 
                                    var clasficacion = '';
                                    if (arrayClasificacionEntExt.length == 0) {
                                        arrayClasificacionEntExt.push('matriz');
                                        clasficacion = 'matriz';
                                    } else {
                                        arrayClasificacionEntExt.push('sucursal');
                                        nuevotr.setAttribute("id","nuevotr"); 
                                        clasficacion = 'sucursal ('+txtAliasEntExt.value+')';
                                    }
                                    var datos = '<td>'+clasficacion+'</td><td>'+txtCodPostalEntExt.value+'</td><td colspan="2">'+txtDireccionEntExt.value+'</td>'+
                                    '<td><a id="deleteRegDirExtEnt" class="btn waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                                    nuevotr.innerHTML = datos;
                                    tBodyExtEntregas.appendChild(nuevotr);
                                    arraytxtAliasExtClientEnt.push(txtAliasEntExt.value);
                                    arraytxtCodPostalExtClientEnt.push(txtCodPostalEntExt.value);
                                    arraytxtDireccionExtEnt.push(txtDireccionEntExt.value);
                                    tHeadExtEntregas.classList.remove("btnError");
                                    trVaciosExtEntregas.classList.add("noneView");
                                    borraInputRow(txtCodPostalEntExt);
                                    borraInputRow(txtDireccionEntExt);
                                    buscaAliasExtEntregasAlta();
                                    btnReturnaEntregar.classList.add("noneView");
                                } else {
                                    if (txtAliasEntExt.value === '' || !filtroDom.test(txtAliasEntExt.value)) {
                                        errorInputRow(txtAliasEntExt);
                                    } 
                                
                                    if (txtCodPostalEntExt.value === '' || !filtroDom.test(txtCodPostalEntExt.value)) {
                                        errorInputRow(txtCodPostalExtClient);
                                    }
                                
                                    if (txtDireccionEntExt.value === '' || !filtroDom.test(txtDireccionEntExt.value)) {
                                        errorInputRow(txtDireccionExt);
                                    }
                                }
                            } 
                        })
                    });

                    $(tableDirExtEntregas).on("click","td a#actRegDirExtEntrega",function(){
                        //trMatrizFiscalEnt
                        var trMatrizPrincipal = $(this).parent("td").parent("tr");
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Desea actualizar esta dirección?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                arrayClasificacionEntExt[0] = arrayClasificacionExt[0];
                                arraytxtCodPostalExtClientEnt[0] = arraytxtCodPostalExtClient[0];
                                arraytxtDireccionExtEnt[0] = arraytxtDireccionExt[0];   
                                trMatrizPrincipal.find("td").eq(0).html(arrayClasificacionExt[0]);
                                trMatrizPrincipal.find("td").eq(1).html(arraytxtCodPostalExtClient[0]);
                                trMatrizPrincipal.find("td").eq(2).html(arraytxtDireccionExt[0]);
                            }
                        })
                    });

                    $(tableDirExtEntregas).on("click","td a#deleteRegDirExtEnt",function(){
                        var trElimina = $(this).parent("td").parent("tr");
                        var arregloPos = $(this).parent("td").parent("tr").index();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas eliminar esta dirección?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if (tBodyExtEntregas.childNodes.length == 4) {
                                    trVaciosExtEntregas.classList.remove("noneView");
                                    trElimina.remove();
                                    btnReturnaEntregar.classList.remove("noneView");
                                } else {
                                    trElimina.remove();
                                    if (tBodyExtEntregas.childNodes.length >= 4) {
                                        var trMatriz = $("#tableDirExtEntregas tbody").find("tr").eq(1);
                                        var primerTd = $(trMatriz).find("td").eq(0);
                                        primerTd.html('matriz');
                                        arrayClasificacionExt[0] = $(trMatriz).find("td").eq(0).html(); 
                                        arraytxtCodPostalExtClient[0] = $(trMatriz).find("td").eq(1).html(); 
                                        arraytxtDireccionExt[0] = $(trMatriz).find("td").eq(2).html(); 

                                        if (arrayClasificacionEntExt.length !=0 && arraytxtCodPostalExtClientEnt.length != 0 && 
                                            arraytxtDireccionExtEnt.length != 0) {
                                            
                                            if ((arrayClasificacionEntExt[0] == arrayClasificacionExt[0]) &&
                                                (arraytxtCodPostalExtClientEnt[0] == arraytxtCodPostalExtClient[0]) &&
                                                (arraytxtDireccionExtEnt[0] == arraytxtDireccionExt[0])) {
                                                $("#tableDirEntregas tbody tr td a#actRegDirExtEntrega").attr("disabled",true);
                                            } else {
                                                $("#tableDirEntregas tbody tr td a#actRegDirExtEntrega").removeAttr('disabled');
                                            }

                                        }

                                    }
                                }
                                arrayClasificacionEntExt.splice(arregloPos-1,1);
                                arraytxtCodPostalExtClientEnt.splice(arregloPos-1,1);
                                arraytxtDireccionExtEnt.splice(arregloPos-1,1);
                                buscaAliasExtEntregasAlta();
                            } else {
                            }
                        })
                    });

                    var direccionesEntNacion = document.getElementById("direccionesEntNacion");
                    var tableDirEntregas = document.getElementById("tableDirEntregas");
                    var tHeadEntregas = document.getElementById("tHeadEntregas");
                    var tBodyEntregas = document.getElementById("tBodyEntregas");
                    var trVaciosEntregas = document.getElementById("trVaciosEntregas");
                    var arrayClasificacionEnt = [];
                    var txtalias_regEnt = document.getElementById("txtalias_regEnt");
                    var arrayalias_regEnt = [];
                    var txtcalle_regEnt = document.getElementById("txtcalle_regEnt");
                    var arraycalle_regEnt = [];
                    var txtnumext_regEnt = document.getElementById("txtnumext_regEnt");
                    var arraynumext_regEnt = [];
                    var txtnumint_regEnt = document.getElementById("txtnumint_regEnt");
                    var arraynumint_regEnt = [];
                    var txtCodPostalEnt = document.getElementById("txtCodPostalEnt");
                    var arrayCodPostalEnt = [];
                    var txtcolEnt = document.getElementById("txtcolEnt");
                    var arraycolEnt = [];
                    var txtdelegEnt = document.getElementById("txtdelegEnt");
                    var arraydelegEnt = [];
                    var txtentidadEnt = document.getElementById("txtentidadEnt");
                    var arrayentidadEnt = [];
                    var txtlocalidad_regEnt = document.getElementById("txtlocalidad_regEnt");
                    var arraylocalidad_regEnt = [];
                    var txtcalle1ref_regEnt = document.getElementById("txtcalle1ref_regEnt");
                    var arraycalle1ref_regEnt = [];
                    var txtcalle2ref_regEnt = document.getElementById("txtcalle2ref_regEnt");
                    var arraycalle2ref_regEnt = [];
                    var txtreferencia_regEnt = document.getElementById("txtreferencia_regEnt");
                    var arrayreferencia_regEnt = [];
                    var addDirEntregasLista = document.getElementById("addDirEntregasLista");
                    var actRegDirEntrega = document.getElementById("actRegDirEntrega");

                    //$(buscaAliasEntAlta());
                    function buscaAliasEntAlta(){
                        if(arrayClasificacionEnt.length == 0){
                            txtalias_regEnt.value = 'matriz';
                            $(txtalias_regEnt).attr('disabled',true);
                        } else {
                            txtalias_regEnt.value = '';
                            $(txtalias_regEnt).removeAttr('disabled');
                        }
                    } 

                    $(txtalias_regEnt).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtalias_regEnt).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDom.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtcalle_regEnt).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtcalle_regEnt).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDom.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });
                    
                    $(txtnumext_regEnt).keyup(function(){
                        if (this.value === '' || !filtroDomNum.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtnumext_regEnt).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDomNum.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(txtnumint_regEnt).keyup(function(){
                        if (this.value === '' || !filtroDomNum.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtnumint_regEnt).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroDomNum.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(buscarColoniaEnt());
                    function buscarColoniaEnt(cpostal) {
                        $.ajax({
                            url: 'ingresos-buscacoldir',
                            type: 'POST',
                            datatype: 'html',
                            data: {
                                cpostal: cpostal,
                            },
                        })
                        .done(function(respuesta){
                            $("#txtcolEnt").html(respuesta);
                            $('select').material_select();
                        })
                        .fail(function(){
                        console.log("error");
                        })
                    };
                
                    $(txtCodPostalEnt).keyup(function(){
                        var cpostal = this.value;
                        if (cpostal != '' && /^[0-9]+$/.test(this.value) && cpostal.length == 5) {
                            correctoInputRow(this);
                            txtcolEnt.value = '';
                            txtdelegEnt.value = '';
                            txtentidadEnt.value = '';
                            buscarColoniaEnt(cpostal);
                            $(txtcolEnt).removeAttr("disabled");
                        } else {
                            buscarColoniaEnt(); 
                            errorInputRow(this);
                        }
                    });

                    $(txtCodPostalEnt).keypress(function(e){
                        if (!soloNumeros(event)) {
                            e.preventDefault();
                        }
                    }); 

                    $(txtcolEnt).change(function(){
                        if ((this.value != '' && filtroDom.test(this.value)) &&
                            (txtCodPostalEnt.value != '' && /^[0-9]+$/.test(txtCodPostalEnt.value) && txtCodPostalEnt.value.length == 5)) {
                            correctoSelectRow(this);
                            $.ajax({
                                url: 'ingresos-buscadelegdirmun',
                                type: 'POST',
                                datatype: 'html',
                                data: {
                                    cpostal: txtCodPostalEnt.value,
                                    colonia: txtcolEnt.value
                                },
                            })
                            .done(function(respuesta){
                                if (respuesta == 'notFoundData') {
                                    var $toastContent = $('<div class="btnError">operacion no realizada, intente nuevamente o comuniquese con soporte</div>');
                                    Materialize.toast($toastContent,5000);
                                } else {
                                    correctoSelectRow(txtcolEnt);
                                    var resultado = respuesta.split('||');
                                    txtdelegEnt.classList.add("noneView");
                                    setTimeout(txtdelegEnt.classList.remove("noneView"), 5000);
                                    txtdelegEnt.value = resultado[0];
                                    setTimeout(txtentidadEnt.classList.add("noneView"), 3000);
                                    txtentidadEnt.classList.remove("noneView")
                                    txtentidadEnt.value = resultado[1];
                                }
                            })
                            .fail(function(){
                                errorSelectRow(txtcolEnt);
                                var $toastContent = $('<div class="btnError">¡operación no realizada, intente nuevamente ó comuniquese con soporte!</div>');
                                Materialize.toast($toastContent,5000);
                            })
                        } else {
                            errorSelectRow(this);
                        }
                    });

                    $(txtlocalidad_regEnt).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtcalle1ref_regEnt).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtcalle2ref_regEnt).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });

                    $(txtreferencia_regEnt).keyup(function(){
                        if (this.value === '' || !filtroDom.test(this.value)) {
                            errorInputRow(this);
                        } else {
                            correctoInputRow(this);
                        }
                    });
            
                    $(addDirEntregasLista).click(function(){
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas agregar esta dirección?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if ((txtalias_regEnt.value != '' && filtroDom.test(txtalias_regEnt.value)) && 
                                    (txtcalle_regEnt.value != '' && filtroDom.test(txtcalle_regEnt.value)) && 
                                    (txtnumext_regEnt.value != '' && filtroDomNum.test(txtnumext_regEnt.value)) &&  
                                    (txtCodPostalEnt.value != '' && /^[0-9]+$/.test(txtCodPostalEnt.value) && txtCodPostalEnt.value.length == 5) && 
                                    (txtcolEnt.value != '' && filtroDom.test(txtcolEnt.value)) && 
                                    (txtdelegEnt.value != '' && filtroDom.test(txtdelegEnt.value)) && 
                                    (txtentidadEnt.value != '' && filtroDom.test(txtentidadEnt.value)) && 
                                    (txtlocalidad_regEnt.value != '' && filtroDom.test(txtlocalidad_regEnt.value)) && 
                                    (txtcalle1ref_regEnt.value != '' && filtroDom.test(txtcalle1ref_regEnt.value)) && 
                                    (txtcalle2ref_regEnt.value != '' && filtroDom.test(txtcalle2ref_regEnt.value)) && 
                                    (txtreferencia_regEnt.value != '' && filtroDom.test(txtreferencia_regEnt.value))) {
                                    
                                    if (txtnumint_regEnt.value != '' && filtroDomNum.test(txtnumint_regEnt.value)) {
                                        llenarDisentregasTab(txtalias_regEnt.value,txtcalle_regEnt.value,txtnumext_regEnt.value,txtnumint_regEnt.value,
                                            txtCodPostalEnt.value,txtcolEnt.value,txtdelegEnt.value,txtentidadEnt.value,
                                            txtlocalidad_regEnt.value,txtcalle1ref_regEnt.value,txtcalle2ref_regEnt.value,
                                            txtreferencia_regEnt.value);
                                    } else {
                                        llenarDisentregasTab(txtalias_regEnt.value,txtcalle_regEnt.value,txtnumext_regEnt.value,'-',
                                            txtCodPostalEnt.value,txtcolEnt.value,txtdelegEnt.value,txtentidadEnt.value,
                                            txtlocalidad_regEnt.value,txtcalle1ref_regEnt.value,txtcalle2ref_regEnt.value,
                                            txtreferencia_regEnt.value);
                                    }
                                
                                    borraInputRow(txtalias_regEnt);
                                    borraInputRow(txtcalle_regEnt);
                                    borraInputRow(txtnumext_regEnt);
                                    borraInputRow(txtnumint_regEnt);
                                    borraInputRow(txtCodPostalEnt);
                                    buscarColoniaEnt();
                                    $(txtcolEnt).attr("disabled",true);
                                    txtcolEnt.selectedIndex = 0;
                                    txtcolEnt.value = '';
                                    $('select').material_select();
                                    borraInputRow(txtdelegEnt);
                                    borraInputRow(txtentidadEnt);
                                    borraInputRow(txtlocalidad_regEnt);
                                    borraInputRow(txtcalle1ref_regEnt);
                                    borraInputRow(txtcalle2ref_regEnt);
                                    borraInputRow(txtreferencia_regEnt);
                                    btnReturnaEntregar.classList.add("noneView");
                                    buscaAliasEntAlta();
                                } else {
                                    if (txtalias_regEnt.value == '' || !filtroDom.test(txtalias_regEnt.value)) {
                                        errorInputRow(txtalias_regEnt);
                                    } 
                                    if (txtcalle_regEnt.value == '' || !filtroDom.test(txtcalle_regEnt.value)) {
                                        errorInputRow(txtcalle_regEnt);
                                    } 
                                    if (txtnumext_regEnt.value == '' || !filtroDomNum.test(txtnumext_regEnt.value)) {
                                        errorInputRow(txtnumext_regEnt);
                                    } 
                                    if (txtCodPostalEnt.value == '' || !/^[0-9]+$/.test(txtCodPostalEnt.value) || !txtCodPostalEnt.value.length == 5){
                                        errorInputRow(txtCodPostalEnt);
                                    } 
                                    if (txtcolEnt.value == '' || !filtroDom.test(txtcolEnt.value)){
                                        errorInputRow(txtcolEnt);
                                    } 
                                    if (txtdelegEnt.value == '' || !filtroDom.test(txtdelegEnt.value)){
                                        errorInputRow(txtdelegEnt);
                                    } 
                                    if (txtentidadEnt.value == '' || !filtroDom.test(txtentidadEnt.value)){
                                        errorInputRow(txtentidadEnt);
                                    } 
                                    if (txtlocalidad_regEnt.value == '' || !filtroDom.test(txtlocalidad_regEnt.value)) {
                                        errorInputRow(txtlocalidad_regEnt);
                                    } 
                                    if (txtcalle1ref_regEnt.value == '' || !filtroDom.test(txtcalle1ref_regEnt.value)) {
                                        errorInputRow(txtcalle1ref_regEnt);
                                    } 
                                    if (txtcalle2ref_regEnt.value == '' || !filtroDom.test(txtcalle2ref_regEnt.value)) {
                                        errorInputRow(txtcalle2ref_regEnt);
                                    } 
                                    if (txtreferencia_regEnt.value == '' || !filtroDom.test(txtreferencia_regEnt.value)) {
                                        errorInputRow(txtreferencia_regEnt);
                                    }
                                } 
                            } 
                        })
                    });

                    function llenarDisentregasTab(alias,calle,ext,int,cPstal,cOlonia,deleg,enti,localidad,c1,c2,refer){
                        var nuevotr = document.createElement("tr"); 
                        var clasficacion = '';
                        if (arraytxtcalle_reg.length == 0) {
                            arrayClasificacionEnt.push('matriz');
                            clasficacion = 'matriz';
                        } else {
                            nuevotr.setAttribute("id","nuevotr"); 
                            arrayClasificacionEnt.push('sucursal');
                            clasficacion = 'sucursal ('+alias+')';
                        }
                        var datos = '<td>'+clasficacion+'</td><td>'+calle+'</td> <td>'+ext+'</td><td>'+int+'</td><td>'+cPstal+'</td>'+
                                    '<td>'+cOlonia+'</td><td>'+deleg+'</td><td>'+enti+'</td><td>'+localidad+'</td>'+
                                    '<td>'+c1+'</td><td>'+c2+'</td><td>'+refer+'</td><td></td>'+
                                    '<td><a id="deleteRegDirExt" class="btn waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                        nuevotr.innerHTML = datos;
                        tBodyEntregas.appendChild(nuevotr);
                        arrayalias_regEnt.push(alias);
                        arraycalle_regEnt.push(calle);
                        arraynumext_regEnt.push(ext);
                        arraynumint_regEnt.push(int);
                        arrayCodPostalEnt.push(cPstal);
                        arraycolEnt.push(cOlonia);
                        arraydelegEnt.push(deleg);
                        arrayentidadEnt.push(enti);
                        arraylocalidad_regEnt.push(localidad);
                        arraycalle1ref_regEnt.push(c1);
                        arraycalle2ref_regEnt.push(c2);
                        arrayreferencia_regEnt.push(refer);
                        tHeadEntregas.classList.remove("btnError");
                        trVaciosEntregas.classList.add("noneView");
                    }

                    $(tableDirEntregas).on("click","td a#actRegDirEntrega",function(){
                        //trMatrizFiscalEnt
                        var trMatrizPrincipal = $(this).parent("td").parent("tr");
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Desea actualizar esta dirección?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                arrayClasificacionEnt[0] = arrayClasificacion[0];
                                arraycalle_regEnt[0] = arraytxtcalle_reg[0];
                                arraynumext_regEnt[0] = arraytxtnumext_reg[0];
                                arraynumint_regEnt[0] = arraytxtnumint_reg[0];
                                arrayCodPostalEnt[0] = arraytxtcPostalFiscal[0];
                                arraycolEnt[0] = arraytxtcolFiscal[0];
                                arraydelegEnt[0] = arraytxtdelFiscal[0];
                                arrayentidadEnt[0] = arraytxtentFiscal[0];
                                arraylocalidad_regEnt[0] = arraytxtlocalidad_reg[0];
                                arraycalle1ref_regEnt[0] = arraytxtcalle1ref_reg[0];
                                arraycalle2ref_regEnt[0] = arraytxtcalle2ref_reg[0];
                                arrayreferencia_regEnt[0] = arraytxtreferencia_reg[0];
                                
                                trMatrizPrincipal.find("td").eq(0).html(arrayClasificacionEnt[0]);
                                trMatrizPrincipal.find("td").eq(1).html(arraycalle_regEnt[0]);
                                trMatrizPrincipal.find("td").eq(2).html(arraynumext_regEnt[0]);
                                trMatrizPrincipal.find("td").eq(3).html(arraynumint_regEnt[0]);
                                trMatrizPrincipal.find("td").eq(4).html(arrayCodPostalEnt[0]);
                                trMatrizPrincipal.find("td").eq(5).html(arraycolEnt[0]);
                                trMatrizPrincipal.find("td").eq(6).html(arraydelegEnt[0]);
                                trMatrizPrincipal.find("td").eq(7).html(arrayentidadEnt[0]);
                                trMatrizPrincipal.find("td").eq(8).html(arraylocalidad_regEnt[0]);
                                trMatrizPrincipal.find("td").eq(9).html(arraycalle1ref_regEnt[0]);
                                trMatrizPrincipal.find("td").eq(10).html(arraycalle2ref_regEnt[0]);
                                trMatrizPrincipal.find("td").eq(11).html(arrayreferencia_regEnt[0]);
                            } else {
                            }
                        })
                    });

                    $(tableDirEntregas).on("click","td a#deleteRegDirExt",function(){
                        var trElimina = $(this).parent("td").parent("tr");
                        var arregloPos = $(this).parent("td").parent("tr").index();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas eliminar este registro?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if (tBodyEntregas.childNodes.length == 4) {
                                    trVaciosEntregas.classList.remove("noneView");
                                    trElimina.remove();
                                    btnReturnaEntregar.classList.remove("noneView");
                                } else {
                                    trElimina.remove();
                                }
                                arrayClasificacionEnt.splice(arregloPos-1,1);
                                arrayalias_regEnt.splice(arregloPos-1,1);
                                arraycalle_regEnt.splice(arregloPos-1,1);
                                arraynumext_regEnt.splice(arregloPos-1,1);
                                arraynumint_regEnt.splice(arregloPos-1,1);
                                arrayCodPostalEnt.splice(arregloPos-1,1);
                                arraycolEnt.splice(arregloPos-1,1);
                                arraydelegEnt.splice(arregloPos-1,1);
                                arrayentidadEnt.splice(arregloPos-1,1);
                                arraylocalidad_regEnt.splice(arregloPos-1,1);
                                arraycalle1ref_regEnt.splice(arregloPos-1,1);
                                arraycalle2ref_regEnt.splice(arregloPos-1,1);
                                arrayreferencia_regEnt.splice(arregloPos-1,1);
                                buscaAliasEntAlta();
                            } 
                        })
                    });

                //boton registro
                    var botonRegClient = document.getElementById("botonRegClient");
                    var btnRegistraClient = document.getElementById("btnRegistraClient");
                    $(btnRegistraClient).click(function(){
                        var subtipoCliente = document.querySelector('input[name="subtipoCliente"]:checked');  
                        if (vtipoCliente == 'nacional') {
                            if (subtipoCliente.value == 'clientFisica' && reRfc.innerHTML.length == 13) {
                                if ((txtPaternoPF_reg.value != '' && strFilter.test(txtPaternoPF_reg.value) && txtPaternoPF_reg.value.length >= 4) &&  
                                    (txtMaternoPF_reg.value != '' && strFilter.test(txtMaternoPF_reg.value) && txtMaternoPF_reg.value.length >= 4) &&  
                                    (txtnombrePF_reg.value != '' && strFilter.test(txtnombrePF_reg.value) && txtnombrePF_reg.value.length >= 3) &&
                                    (selListaPreciosPF_reg.value != '' && strFilter.test(selListaPreciosPF_reg.value)) ) {

                                    if (txtcurpPF_reg.value == '' && txtnomCom_regPF.value == '' && txtsitWebPF_reg.value == '') {
                                        erroresResto();
                                        validaDirecciones();
                                    } else {
                                        if ((/^[a-zA-Z0-9]+$/.test(txtcurpPF_reg.value) && txtcurpPF_reg.value.length == 18) || 
                                            (strFilEmp.test(txtnomCom_regPF.value) && txtnomCom_regPF.value.length >= 10) ||
                                            (filtroUrl.test(txtsitWebPF_reg.value) && txtsitWebPF_reg.value.length >= 10)) {
                                            erroresResto();
                                            validaDirecciones();
                                        } else {
                                            if (!/^[a-zA-Z0-9]+$/.test(txtcurpPF_reg.value) || !txtcurpPF_reg.value.length == 18) {
                                                errorInput(txtcurpPF_reg,'Inserta CURP/CURP invalido');
                                                return;    
                                            }

                                            if (!strFilEmp.test(txtnomCom_regPF.value) || !txtnomCom_regPF.value.length >= 10) {
                                                checkNombreCom(txtnomCom_regPF);
                                                return;
                                            }

                                            if (!filtroUrl.test(txtsitWebPF_reg.value) || !txtsitWebPF_reg.value.length >= 10) {
                                                checkSitWeb(txtsitWebPF_reg);
                                                return;
                                            }
                                        }
                                    }
                                    
                                    if (erroresResto() == 1 && validaDirecciones() == 1) {
                                        envio_form();
                                    }

                                } else {
                                    validaPrincipalesPF();
                                    validaDirecciones();
                                    erroresResto();
                                }   
                            }

                            if (subtipoCliente.value == 'clientMoral' && reRfc.innerHTML.length == 12) {
                                if ((txtempresa_reg.value != '' && strFilEmp.test(txtempresa_reg.value)) && 
                                    (selListaPreciosPM_reg.value != '' && strFilter.test(selListaPreciosPM_reg.value)) ) {

                                    if (txtnomCom_regPM.value == '' && txtsitWeb_regPM.value == '') {
                                        erroresResto();
                                        validaDirecciones();
                                    } else {
                                        if ((strFilEmp.test(txtnomCom_regPM.value) && txtnomCom_regPM.value.length >= 10) ||
                                            (filtroUrl.test(txtsitWeb_regPM.value) && txtsitWeb_regPM.value.length >= 10)) {
                                            erroresResto();
                                            validaDirecciones();
                                        } else {
                                            if (!strFilEmp.test(txtnomCom_regPM.value) || txtnomCom_regPM.value.length < 10) {  
                                                checkNombreCom(txtnomCom_regPM);
                                                return;
                                            } 

                                            if (!filtroUrl.test(txtsitWeb_regPM.value) || txtsitWeb_regPM.value.length < 10) {  
                                                checkSitWeb(txtsitWeb_regPM);
                                                return;
                                            } 
                                        }
                                    }

                                    if (erroresResto() == 1 && validaDirecciones() == 1) {
                                        envio_form();
                                    }
                                } else {
                                    validaPrincipalesPM();
                                    validaDirecciones();
                                    erroresResto();
                                }
                            }
                        }
                        
                        if (vtipoCliente == 'extranjero') {
                            if (subtipoCliente.value == 'clientFisica') {
                                if ((txtPaternoPF_reg.value != '' && strFilter.test(txtPaternoPF_reg.value) && txtPaternoPF_reg.value.length >= 4) &&  
                                    (txtMaternoPF_reg.value != '' && strFilter.test(txtMaternoPF_reg.value) && txtMaternoPF_reg.value.length >= 4) &&  
                                    (txtnombrePF_reg.value != '' && strFilter.test(txtnombrePF_reg.value) && txtnombrePF_reg.value.length >= 3)  &&
                                    (selListaPreciosPF_reg.value != '' && strFilter.test(selListaPreciosPF_reg.value)) &&   
                                    selPaisExtPF_reg.value != '') {

                                    if (txtcurpPF_reg.value == '' && txtnomCom_regPF.value == '' && txtsitWebPF_reg.value == '') {
                                        erroresResto();
                                    } else {
                                        if (/^[a-zA-Z0-9]+$/.test(txtcurpPF_reg.value) && txtcurpPF_reg.value.length <= 40) {
                                            erroresResto();
                                        } else {
                                            errorInput(txtcurpPF_reg,'Inserta IDTax/IDTax invalido');
                                            return;
                                        } 

                                        if (strFilEmp.test(txtnomCom_regPF.value) && txtnomCom_regPF.value.length >= 10) {
                                            erroresResto();
                                        } else {
                                            checkNombreCom(txtnomCom_regPF);
                                            return;
                                        } 

                                        if (filtroUrl.test(txtsitWebPF_reg.value) && txtsitWebPF_reg.value.length >= 10) {
                                            erroresResto();
                                        } else {
                                            checkSitWeb(txtsitWebPF_reg);
                                            return;
                                        }

                                    }
                                    
                                    if (erroresResto() == 1) {
                                        envio_form();
                                    } 
                                } else {
                                    validaPrincipalesPF();
                                    if (selPaisExtPF_reg.value == ''){
                                        checkSelectPais(selPaisExtPF_reg);
                                    }
                                    erroresResto();
                                }
                            }

                            if (subtipoCliente.value == 'clientMoral') {
                                if ((txtempresa_reg.value != '' && strFilEmp.test(txtempresa_reg.value))  && 
                                    (selListaPreciosPM_reg.value != '' && strFilter.test(selListaPreciosPM_reg.value)) && 
                                    selPaisExtPM_reg.value != '') {

                                    if (txtidtax_reg.value == '' && txtnomCom_regPM.value == '' && txtsitWeb_regPM.value == '') {
                                        erroresResto();
                                    } else {
                                        if (/^[a-zA-Z0-9]+$/.test(txtidtax_reg.value) && txtidtax_reg.value.length <= 40) {
                                            erroresResto();
                                        } else {
                                            errorInput(txtidtax_reg,'Inserta IDTax/IDTax invalido');
                                            return;
                                        }

                                        if (strFilEmp.test(txtnomCom_regPM.value) && txtnomCom_regPM.value.length >= 10) {
                                            erroresResto();
                                        } else {
                                            checkNombreCom(txtnomCom_regPF);
                                            return;
                                        } 

                                        if (filtroUrl.test(txtsitWeb_regPM.value) && txtsitWeb_regPM.value.length >= 10) {
                                            erroresResto();
                                        } else {
                                            checkSitWeb(txtsitWebPF_reg);
                                            return;
                                        }

                                    }

                                    if (erroresResto() == 1) {
                                        envio_form();
                                    }
                                } else {
                                    if(selPaisExtPM_reg.value == ''){
                                        checkSelectPais(selPaisExtPM_reg);
                                    }
                                    validaPrincipalesPM();
                                    erroresResto();
                                }
                            }
                        }
                    });    
                    
                    function validaPrincipalesPF(){
                        if (txtPaternoPF_reg.value == '' || !strFilter.test(txtPaternoPF_reg.value) || !txtPaternoPF_reg.value.length >= 2){
                            checkPaterno(txtPaternoPF_reg);
                        }
                    
                        if (txtMaternoPF_reg.value == '' || !strFilter.test(txtMaternoPF_reg.value) || !txtMaternoPF_reg.value.length >= 2){
                            checkMaterno(txtMaternoPF_reg);
                        }
                    
                        if (txtnombrePF_reg.value == '' || !strFilter.test(txtnombrePF_reg.value) || !txtnombrePF_reg.value.length >= 2){
                            checkNombre(txtnombrePF_reg);
                        }

                        if (selListaPreciosPF_reg.value == '' || !strFilter.test(selListaPreciosPF_reg.value)){
                            checkSelectListaPrecios(selListaPreciosPF_reg);
                        }

                    }
                    
                    function validaPrincipalesPM(){
                        if(txtempresa_reg.value == '' || !strFilEmp.test(txtempresa_reg.value) || !txtempresa_reg.value) {
                            errorInput(txtempresa_reg,'Inserta empresa');
                        }

                        if (selListaPreciosPM_reg.value == '' || strFilter.test(selListaPreciosPM_reg.value)) {
                            checkSelectListaPrecios(selListaPreciosPM_reg);
                        }
                    }
                
                    function validaDirecciones(){
               
                        if (vtipoCliente == 'nacional') {
                            if (tBodyDirFiscal.childNodes.length >= 4) {
                                var dir = 1; //habilitar el boton
                                return dir;
                            } else {
                                if (tBodyDirFiscal.childNodes.length == 3) {
                                    //dataContacto
                                    collapsible_headerContacto.classList.add("active");
                                    collapsible_bodyContacto.classList.add("activeViewError");
                                    tHeadDirFiscal.classList.add("btnError");
                                }
                                return 0;
                            }
                        }

                        if (vtipoCliente == 'extranjero') {
                            if(selPaisExtPM_reg.value == ''){
                                checkSelectPais(selPaisExtPM_reg);
                            }
                        }
                    }
                    
                    function erroresResto(){
                        var aceptCreditoClient = document.querySelector('input[name="aceptaCreditoClient"]:checked');
                        var direccfisClient = document.querySelector('input[name="direccfisClient"]:checked');
                        if (tbodyDatosConta.childNodes.length >= 4 && situacion_fiscal.value != '' && 
                            (situacion_fiscal.value.includes('.pdf') || situacion_fiscal.value.includes('.jpg') || 
                            situacion_fiscal.value.includes('.jpeg') || situacion_fiscal.value.includes('.png')) &&
                            (aceptCreditoClient &&  aceptCreditoClient.value != '' && strFilter.test(aceptCreditoClient.value)) && 
                            (formaPago.value != '' && /^[0-9]+$/.test(formaPago.value))) {

                            if(aceptCreditoClient.value == 'si'){
                                if ((txtMoneda_reg.value != '' && strFilEmp.test(txtMoneda_reg.value) && txtMoneda_reg.value.length >= 5) && 
                                    (limiteCredito.value != '' && (/^[0-9$.,]+$/.test(limiteCredito.value)) && limiteCredito.value.length >= 1) && 
                                    (txtdiaspagoCredit_reg.value != '' && (/^[0-9]+$/.test(txtdiaspagoCredit_reg.value)) && txtdiaspagoCredit_reg.value.length >= 1) &&
                                    (selectComienzaPago.value != '' && /^[a-z]+$/.test(this.value))){ 
                                    var vv = 1;
                                } else {
                                    if (txtMoneda_reg.value == '' || !strFilEmp.test(txtMoneda_reg.value) || !txtMoneda_reg.value.length >= 5) {
                                        abreHeaderCredito();
                                        errorInput(txtMoneda_reg,'Inserta Moneda');
                                    }
                            
                                    if (limiteCredito.value == '' || !/^[0-9$.,]+$/.test(limiteCredito.value) || !limiteCredito.value.length >= 1) {
                                        abreHeaderCredito();
                                        errorInput(limiteCredito,'Inserta Límite de credito');
                                    }
                            
                                    if (txtdiaspagoCredit_reg.value == '' || !/^[0-9]+$/.test(txtdiaspagoCredit_reg.value) || !txtdiaspagoCredit_reg.value.length >= 1) {
                                        abreHeaderCredito();
                                        errorInput(txtdiaspagoCredit_reg,'Inserta Días de pago');
                                    }

                                    if (selectComienzaPago.value == '' || !/^[a-z]+$/.test(this.value)) {
                                        errorSelect(this,'Selecciona una opción');
                                    }
                                }
                            }
                            if (aceptCreditoClient.value == 'no') {
                                var vv = 1;   
                            }

                            if (formaPago.value == 3) {
                                if (clabeIntBanc.value != '' && /^[0-9-]+$/.test(clabeIntBanc.value) && 
                                    estado_cuenta.value != '' && (estado_cuenta.value.includes('.pdf') || estado_cuenta.value.includes('.jpg') || 
                                    estado_cuenta.value.includes('.jpeg') || estado_cuenta.value.includes('.png'))) {
                                    var vv = 1;
                                } else {
                                    if(clabeIntBanc.value == '' || !(/^[0-9-]+$/.test(clabeIntBanc.value))){
                                        abreHeaderFpago();
                                        errorInput(clabeIntBanc,'Inserta Clabe interbancaria');
                                    }
                                    if (estado_cuenta.value == '' || !(estado_cuenta.value.includes('.pdf') || estado_cuenta.value.includes('.jpg') || 
                                    estado_cuenta.value.includes('.jpeg') || estado_cuenta.value.includes('.png'))) {
                                        abreHeaderFpago();
                                        btnEstadoCuenta.classList.add("btnError");
                                    }
                                }
                            } 
                            if (formaPago.value != 3) {
                                var vv = 1;   
                            } 
                    
                            if (vtipoCliente == 'nacional') {
                                if (tBodyEntregas.childNodes.length >= 4) {
                                    var vv = 1;
                                } else { 
                                    var vv = 0;
                                    //dataContacto
                                    collapsible_headerContacto.classList.add("active");
                                    collapsible_bodyContacto.classList.add("activeViewError");
                                    tHeadEntregas.classList.add("btnError");
                                }  
                            } else {
                                if (tBodyExtEntregas.childNodes.length >= 4) {
                                    var vv = 1;
                                } else {
                                    var vv = 0;
                                    tHeadExtEntregas.classList.add("btnError");
                                    errorInputRow(txtDireccionEntExt);
                                    errorInputRow(txtCodPostalEntExt);
                                }
                            }
                            
                            return vv;
                        } else {
                            camposVacios();
                            if (tbodyDatosConta.childNodes.length == 3) {
                                //dataContacto
                                collapsible_headerContacto.classList.add("active");
                                collapsible_bodyContacto.classList.add("activeViewError");
                                tHeadContacto.classList.add("btnError");
                            }
                            if (situacion_fiscal.value == '' || ! (situacion_fiscal.value.includes('.pdf') || situacion_fiscal.value.includes('.jpg') || 
                                situacion_fiscal.value.includes('.jpeg') || situacion_fiscal.value.includes('.png'))) {
                                datFiscal.classList.remove("noneView");
                                collapsible_headerfiscal.classList.add("active");
                                collapsible_bodyfiscal.classList.add("activeViewError");
                                btnSituFiscal.classList.add("btnError");
                            }
                    
                            if (!aceptCreditoClient || aceptCreditoClient.value == '' || !strFilter.test(aceptCreditoClient.value)) {
                                abreHeaderCredito();
                            }
                    
                            if (aceptCreditoClient && aceptCreditoClient.value == 'si') {
                                if (txtMoneda_reg.value == '' || !strFilEmp.test(txtMoneda_reg.value) || !txtMoneda_reg.value.length >= 5) {
                                    abreHeaderCredito();
                                    errorInput(txtMoneda_reg,'Inserta Moneda');
                                }
                    
                                if (limiteCredito.value == '' || !/^[0-9$.,]+$/.test(limiteCredito.value) || !limiteCredito.value.length >= 1) {
                                    abreHeaderCredito();
                                    errorInput(limiteCredito,'Inserta Límite de credito');
                                }
                    
                                if (txtdiaspagoCredit_reg.value == '' || !/^[0-9]+$/.test(txtdiaspagoCredit_reg.value) || !txtdiaspagoCredit_reg.value.length >= 1) {
                                    abreHeaderCredito();
                                    errorInput(txtdiaspagoCredit_reg,'Inserta Días de pago');
                                }

                                if (selectComienzaPago.value == '' || !/^[a-z]+$/.test(this.value)) {
                                    errorSelect(selectComienzaPago,'Selecciona una opción');
                                }
                            }
                    
                            if (formaPago.value == '' || !/^[0-9]+$/.test(formaPago.value)) {
                                abreHeaderFpago();
                                errorSelect(formaPago,'Selecciona forma de pago');
                            }
                    
                            if (formaPago.value != '' && formaPago.value == 3) {
                                if(clabeIntBanc.value == '' || !(/^[0-9-]+$/.test(clabeIntBanc.value))){
                                    abreHeaderFpago();
                                    errorInput(clabeIntBanc,'Inserta Clabe interbancaria');
                                }
                                if (estado_cuenta.value == '' || !(estado_cuenta.value.includes('.pdf') || estado_cuenta.value.includes('.jpg') || 
                                estado_cuenta.value.includes('.jpeg') || estado_cuenta.value.includes('.png'))) {
                                    abreHeaderFpago();
                                    btnEstadoCuenta.classList.add("btnError");
                                }
                            }
                    
                            if (!direccfisClient || direccfisClient.value == '' || !strFilter.test(direccfisClient.value)) {
                                abreHeaderCreditoEnt();
                            }
                            
                            if (vtipoCliente == 'nacional') {
                                if (tBodyEntregas.childNodes.length == 3) {
                                    //dataContacto
                                    collapsible_headerContacto.classList.add("active");
                                    collapsible_bodyContacto.classList.add("activeViewError");
                                    tHeadEntregas.classList.add("btnError");
                                    if (txtcalle_regEnt.value == '' || !filtroDom.test(txtcalle_regEnt.value)) {
                                        errorInputRow(txtcalle_regEnt);
                                    }
                                    if (txtnumext_regEnt.value == '' || !/^[0-9]+$/.test(txtnumext_regEnt.value)) {
                                        errorInputRow(txtnumext_regEnt);
                                    } 
                                    if (txtCodPostalEnt.value == '' || !/^[0-9]+$/.test(txtCodPostalEnt.value)){
                                        errorInputRow(txtCodPostalEnt);
                                    } 
                                    if (txtcolEnt.value == '' || !filtroDom.test(txtcolEnt.value)){
                                        errorInputRow(txtcolEnt);
                                    } 
                                    if (txtdelegEnt.value == '' || !filtroDom.test(txtdelegEnt.value)){
                                        errorInputRow(txtdelegEnt);
                                    } 
                                    if (txtentidadEnt.value == '' || !filtroDom.test(txtentidadEnt.value)){
                                        errorInputRow(txtentidadEnt);
                                    } 
                                    if (txtlocalidad_regEnt.value == '' || !filtroDom.test(txtlocalidad_regEnt.value)) {
                                        errorInputRow(txtlocalidad_regEnt);
                                    } 
                                    if (txtcalle1ref_regEnt.value == '' || !filtroDom.test(txtcalle1ref_regEnt.value)) {
                                        errorInputRow(txtcalle1ref_regEnt);
                                    }
                                    if (txtcalle2ref_regEnt.value == '' || !filtroDom.test(txtcalle2ref_regEnt.value)) {
                                        errorInputRow(txtcalle2ref_regEnt);
                                    } 
                                    if (txtreferencia_regEnt.value == '' || !filtroDom.test(txtreferencia_regEnt.value)) {
                                        errorInputRow(txtreferencia_regEnt);
                                    }
                                }
                            } else {
                                if (tBodyExtEntregas.childNodes.length == 3) {
                                    tHeadExtEntregas.classList.add("btnError");
                                    errorInputRow(txtDireccionEntExt);
                                    errorInputRow(txtCodPostalEntExt);
                                }
                            }
                            return 0;
                        }
                    }
                    
                    function envio_form() {
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Desea registrar este cliente?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                var partData = $(this).closest('frmAddClient').serialize();
                                var radioClient = document.querySelector('input[name="tipoCliente"]:checked');
                                var subtipoCliente = document.querySelector('input[name="subtipoCliente"]:checked'); 
                                var filesituacion_fiscal = $(situacion_fiscal)[0].files[0];
                                var checkCP = document.querySelector('input[name="checkCP"]:checked');
                                var fileestCuenta = $(estado_cuenta)[0].files[0];
                                var aceptCreditoClient = document.querySelector('input[name="aceptaCreditoClient"]:checked');
                                var direccfisClient= document.querySelector('input[name="direccfisClient"]:checked');
                                var data = new FormData();
                                data.append('data',partData);
                                data.append('rfc-registro-pf',varriablleRfc);
                                data.append('radioClient',radioClient.value);
                                data.append('subtipoCliente',subtipoCliente.value);
                                data.append('txtPaternoPF',txtPaternoPF_reg.value);
                                data.append('txtMaternoPF',txtMaternoPF_reg.value);
                                data.append('txtnombrePF',txtnombrePF_reg.value);
                                data.append('listaPreciosPF',selListaPreciosPF_reg.value);  
                                data.append('txtcurpPF',txtcurpPF_reg.value);
                                data.append('paisPF',selPaisExtPF_reg.value);
                                data.append('txtNomComercialPF',txtnomCom_regPF.value);
                                data.append('txtSitioWebPF',txtsitWebPF_reg.value);
                                data.append('redesSocialesPF',JSON.stringify(arrayRedesPF));
                                data.append('txtempresa',txtempresa_reg.value);
                                data.append('idtax',txtidtax_reg.value);
                                data.append('listaPreciosPM',selListaPreciosPM_reg.value);  
                                data.append('pais',selPaisExtPM_reg.value);
                                data.append('txtNomComercialPM',txtnomCom_regPM.value);
                                data.append('txtSitioWebPM',txtsitWeb_regPM.value);
                                data.append('redesSocialesPM',JSON.stringify(arrayRedesPM));
                                data.append('txtPaternoCont_reg',JSON.stringify(arrayPaternoCont_reg));
                                data.append('txtMaternoCont_reg',JSON.stringify(arrayMaternoCont_reg));
                                data.append('txtNombreCont_reg',JSON.stringify(arrayNombreCont_reg));
                                data.append('txtArea',JSON.stringify(arrayAreaCont_reg));
                                data.append('txtCargo',JSON.stringify(arrayCargoCont_reg));
                                data.append('txtEmailCont_reg',JSON.stringify(arrayEmailCont_reg));
                                data.append('txtTelefonoCont_reg',JSON.stringify(arrayTelefonoCont_reg));
                                data.append('txtExtension_reg',JSON.stringify(arrayExtension_reg));
                                data.append('situacion_fiscal',filesituacion_fiscal); 

                                data.append('clasificacionExt',JSON.stringify(arrayClasificacionExt));
                                data.append('aliasExtFiscal',JSON.stringify(arraytxtAliasExtClient));
                                data.append('txtCodPostalExtClient',JSON.stringify(arraytxtCodPostalExtClient));
                                data.append('direccionExt',JSON.stringify(arraytxtDireccionExt));

                                data.append('clasificacionFiscal',JSON.stringify(arrayClasificacion));
                                data.append('aliasFiscal',JSON.stringify(arraytxtalias_reg));
                                data.append('txtcalle',JSON.stringify(arraytxtcalle_reg));
                                data.append('txtnumext',JSON.stringify(arraytxtnumext_reg));
                                data.append('txtnumint',JSON.stringify(arraytxtnumint_reg));
                                data.append('txtcPostal',JSON.stringify(arraytxtcPostalFiscal));
                                data.append('txtcolFiscal',JSON.stringify(arraytxtcolFiscal));
                                data.append('txtdelFiscal',JSON.stringify(arraytxtdelFiscal));
                                data.append('txtentFiscal',JSON.stringify(arraytxtentFiscal));
                                data.append('txtlocalidad',JSON.stringify(arraytxtlocalidad_reg));
                                data.append('txtcalle1ref',JSON.stringify(arraytxtcalle1ref_reg));
                                data.append('txtcalle2ref',JSON.stringify(arraytxtcalle2ref_reg));
                                data.append('txtreferencia',JSON.stringify(arraytxtreferencia_reg));
    
                                data.append('aceptaCreditoClient',aceptCreditoClient.value);
                                data.append('txtMoneda',txtMoneda_reg.value);
                                data.append('txtlimiteCredito',limiteCredito.value);
                                data.append('txtdiaspagoCredit',txtdiaspagoCredit_reg.value);
                                data.append('selectComienzaPago',selectComienzaPago.value);
                                data.append('formaPago',formaPago.value);
                                data.append('txtClabeIntBanc',clabeIntBanc.value);
                                data.append('est_cuenta',fileestCuenta);
                                data.append('direccfisClient',direccfisClient.value);

                                data.append('clasificacionEntExt',JSON.stringify(arrayClasificacionEntExt));
                                data.append('aliasExtEntregas',JSON.stringify(arraytxtAliasExtClientEnt));
                                data.append('txtCodPostalEntExt',JSON.stringify(arraytxtCodPostalExtClientEnt));
                                data.append('direccionEntExt',JSON.stringify(arraytxtDireccionExtEnt));

                                data.append('clasificacionEnt',JSON.stringify(arrayClasificacionEnt));
                                data.append('aliasEntregas',JSON.stringify(arrayalias_regEnt));
                                data.append('txtcalleEnt',JSON.stringify(arraycalle_regEnt));
                                data.append('txtnumextEnt',JSON.stringify(arraynumext_regEnt));
                                data.append('txtnumintEnt',JSON.stringify(arraynumint_regEnt));
                                data.append('txtcPostalEnt',JSON.stringify(arrayCodPostalEnt));
                                data.append('txtcolEnt',JSON.stringify(arraycolEnt));
                                data.append('txtdelEnt',JSON.stringify(arraydelegEnt));
                                data.append('txtentEnt',JSON.stringify(arrayentidadEnt));
                                data.append('txtlocalidadEnt',JSON.stringify(arraylocalidad_regEnt));
                                data.append('txtcalle1refEnt',JSON.stringify(arraycalle1ref_regEnt));
                                data.append('txtcalle2refEnt',JSON.stringify(arraycalle2ref_regEnt));
                                data.append('txtreferenciaEnt',JSON.stringify(arrayreferencia_regEnt));
                        
                                $.ajax({
                                    url: 'ingresos-registracliente',
                                    type: "post",
                                    data: data,
                                    datatype: 'json',
                                    processData: false,
                                    contentType: false,
                                    success: function(respuesta) {
                                        alert(respuesta)
                                        var resarray = respuesta.substring(respuesta.length-1,respuesta.length);
                                        if(respuesta == 'errorRfc'){
                                            toastError('RFC INVALIDO');
                                        }
                                        if(respuesta == 'errorExistRfc'){
                                            toastError('RFC INEXISTENTE');
                                        }
                                        if(respuesta == 'errorEmptyRfc'){
                                            toastError('RFC VACIO');
                                        }
                                        if(respuesta == 'errorInvalidRfc'){
                                            toastError('RFC INVALIDO');
                                        }
                                        if(respuesta == 'errorTipoUser'){
                                            toastError('Tipo de usuario no autorizado');
                                        }
                                        if(respuesta == 'errorPaternoPF'){
                                            toastError('Apellido Paterno invalido');
                                            checkPaterno(txtPaternoPF_reg);
                                        }
                                        if(respuesta == 'errorMaternoPF'){
                                            toastError('Apellido Materno invalido');
                                            checkMaterno(txtMaternoPF_reg);
                                        }
                                        if(respuesta == 'errorNombrePF'){
                                            toastError('Nombre invalido');
                                            checkNombre(txtnombrePF_reg);
                                        }
                                        if(respuesta == 'errorListaPreciosPF'){
                                            toastError('Lista de precios invalida');
                                            checkSelectListaPrecios(selListaPreciosPF_reg);
                                        }
                                        if(respuesta == 'errorNomComercialPF'){
                                            toastError('Nombre comercial invalido');
                                            checkNombreCom(txtnomCom_regPF);
                                        }
                                        if(respuesta == 'errorSitioWebPF'){
                                            toastError('Sitio Web Invalido');
                                            checkSitWeb(txtsitWebPF_reg);
                                        }
                                        if(respuesta == 'errorRedSocialesPF'){
                                            toastError('redes sociales incompletas');
                                            tabRedSocialPF.classList.add("btnError");
                                        }
                                        if(respuesta == 'errorListaPreciosPM'){
                                            toastError('Lista de precios invalida');
                                            checkSelectListaPrecios(selListaPreciosPM_reg);
                                        }
                                        if(respuesta == 'errorNomComercialPM'){
                                            toastError('Nombre comercial invalido');
                                            checkNombreCom(txtnomCom_regPM);
                                        }
                                        if(respuesta == 'errorSitioWebPM'){
                                            toastError('Sitio Web Invalido');
                                            checkSitWeb(txtsitWeb_regPM);
                                        }
                                        if(respuesta == 'errorRedSocialesPM'){
                                            toastError('redes sociales incompletas');
                                            tabRedSocialPM.classList.add("btnError");
                                        }
                                        if(respuesta == 'errorCodPostalExt'){
                                            toastError('Inserta código postal');
                                            checkPostalExt(txtCodPostalExtClient);
                                        }
                                        if(respuesta == 'errorDireccionExt'){
                                            toastError('Inserta dirección');
                                            checkDireccionExt(txtDireccionExt);
                                        }
                                        if(respuesta == 'errorCodPostalEntExt'){
                                            toastError('Inserta código postal');
                                            checkPostalExt(txtCodPostalEntExt);
                                        }
                                        if(respuesta == 'errorDirEntExt'){
                                            toastError('Inserta dirección');
                                            checkDireccionExt(txtDireccionEntExt);
                                        }
                                        if(respuesta == 'errorIdTaxPF'){
                                            toastError('IDTax invalido');
                                            errorInput(txtcurpPF_reg,'Inserta IDTax/IDTax invalido');
                                        }
                                        if(respuesta == 'errorCurpPF'){
                                            toastError('CURP invalido');
                                            errorInput(txtcurpPF_reg,'Inserta CURP/CURP invalido');
                                        }
                                        if(respuesta == 'errorEmpresa'){
                                            toastError('Empresa invadida');
                                            errorInput(txtempresa_reg,'Inserta empresa');
                                        }
                                        if(respuesta == 'errorIdTax'){
                                            toastError('IDTax invalido');
                                            errorInput(txtidtax_reg,'Inserta IDTax/IDTax invalido');
                                        }
                                        if(respuesta == 'errorPais'){
                                            toastError('Selecciona un pais');
                                            checkSelectPais(selPaisExtPM_reg);
                                        }
                                        if(respuesta == 'errorPaternoCont'+resarray){
                                            toastError('Apellido Paterno de contacto invalido');   
                                            abreHeaderCont();
                                            $(tHeadContacto).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tbodyDatosConta).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorMaternoCont'+resarray){
                                            toastError('Apellido Materno de contacto invalido');    
                                            abreHeaderCont();
                                            $(tHeadContacto).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tbodyDatosConta).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorNombreCont'+resarray){
                                            toastError('Nombre de contacto invalido');    
                                            abreHeaderCont();
                                            $(tHeadContacto).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tbodyDatosConta).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorArea'+resarray){
                                            toastError('Area invalida');    
                                            abreHeaderCont();
                                            $(tHeadContacto).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tbodyDatosConta).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorCargo'+resarray){
                                            toastError('Cargo invalido');    
                                            abreHeaderCont();
                                            $(tHeadContacto).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tbodyDatosConta).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorEmailCont'+resarray){
                                            toastError('Email de contacto invalido');    
                                            abreHeaderCont();
                                            $(tHeadContacto).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tbodyDatosConta).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorTelefonoCont'+resarray){
                                            toastError('Teléfono de contacto invalido');
                                            abreHeaderCont();
                                            $(tHeadContacto).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tbodyDatosConta).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorExtensionCont'+resarray){
                                            toastError('extensión invalida');
                                            abreHeaderCont();
                                            $(tHeadContacto).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tbodyDatosConta).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'ingresaSitFis'){
                                            toastError('ingresa Constancia de situación fiscal');
                                            datFiscal.classList.remove("noneView");
                                            collapsible_headerfiscal.classList.add("active");
                                            collapsible_bodyfiscal.classList.add("activeViewError");
                                            btnSituFiscal.classList.add("btnError");
                                        }
                                        if(respuesta == 'errorTypeSF'){
                                            toastError('Formato de constancia invalido (PDF, JPG, JPEG  y PNG)');
                                            datFiscal.classList.remove("noneView");
                                            collapsible_headerfiscal.classList.add("active");
                                            collapsible_bodyfiscal.classList.add("activeViewError");
                                            btnSituFiscal.classList.add("btnError");
                                        }
                                        if(respuesta == 'errorNameExistSF'){
                                            toastError('Nombre de constancia indefinido');
                                            datFiscal.classList.remove("noneView");
                                            collapsible_headerfiscal.classList.add("active");
                                            collapsible_bodyfiscal.classList.add("activeViewError");
                                            btnSituFiscal.classList.add("btnError");
                                        } 
                                        if(respuesta == 'errorSizeMaxSF'){
                                            toastError('El formato que ha intentado subir excede el tamaño permitido (2MB)');
                                            datFiscal.classList.remove("noneView");
                                            collapsible_headerfiscal.classList.add("active");
                                            collapsible_bodyfiscal.classList.add("activeViewError");
                                            btnSituFiscal.classList.add("btnError");
                                        }
                                        if(respuesta == 'errorDireccionExt'){
                                            toastError('Diracción invalida');
                                            errorInput(txtDireccionExt,'Inserta dirección');
                                        }                  
                                        if(respuesta == 'errorClasificacion'+resarray){
                                            toastError('error en clasificacion');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorAlias'+resarray){
                                            toastError('error en alias');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorCalle'+resarray){
                                            toastError('Calle invalida');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorNumExt'+resarray){
                                            toastError('Número exteriór invalido');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorNumInt'+resarray){
                                            toastError('Número interiór invalido');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorcPostal'+resarray){
                                            toastError('Selecciona Código postal');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorCol'+resarray){
                                            toastError('Selecciona Colonia');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorDel'+resarray){
                                            toastError('Selecciona Delegación ó municipio');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorEntidad'+resarray){
                                            toastError('Selecciona Entidad federativa');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorLocalidad'+resarray){
                                            toastError('Localidad invalida');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorEntre1'+resarray){
                                            toastError('Calle de referencia invalida');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorEntre2'+resarray){
                                            toastError('Calle de referencia invalida');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }     
                                        if(respuesta == 'errorReferencia'+resarray){
                                            toastError('Punto de referencia invalido');
                                            $(tHeadDirFiscal).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyDirFiscal).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'ingresaAcepCred'){
                                            toastError('Selecciona opciones de crédito');
                                            abreHeaderCredito();
                                        }
                                        if(respuesta == 'errorMoneda'){
                                            toastError('Moneda invalida');
                                            abreHeaderCredito();
                                            errorInput(txtMoneda_reg,'Inserta Moneda');
                                        }    
                                        if(respuesta == 'errorLimiteCredito'){
                                            toastError('Límite de credito invalido');
                                            abreHeaderCredito();
                                            errorInput(limiteCredito,'Inserta Límite de credito');
                                        }
                                        if(respuesta == 'errorDiaspagoCredit'){
                                            toastError('formato de días de pago invalido');
                                            abreHeaderCredito();
                                            errorInput(txtdiaspagoCredit_reg,'Inserta Días de pago');
                                        }
                                        if(respuesta == 'errorFormaPago'){
                                            toastError('Selecciona Forma de pago');
                                            abreHeaderFpago();
                                            errorSelect(formaPago,'Selecciona Forma de pago');
                                        }
                                        if(respuesta == 'errorClabeIntBanc'){
                                            toastError('Clabe interbancaria invalida');
                                            abreHeaderFpago();
                                            errorInput(clabeIntBanc,'Inserta Clabe interbancaria');
                                        }
                                        if(respuesta == 'errorEstCVacio'){
                                            toastError('ingresa Formato de estado de cuenta');
                                            abreHeaderFpago();
                                            btnEstCuenta.classList.add("btnError");
                                        }               
                                        if(respuesta == 'errorTypeEC'){
                                            toastError('Formato de estado de cuenta invalido (PDF, JPG, JPEG  y PNG)');
                                            abreHeaderFpago();
                                            btnEstCuenta.classList.add("btnError");
                                        }
                                        if(respuesta == 'errorNameExistEC'){
                                            toastError('Nombre de estado de cuenta indefinido');
                                            abreHeaderFpago();
                                            btnEstCuenta.classList.add("btnError");
                                        }
                                        if(respuesta == 'errorSizeMaxEC'){
                                            toastError('El formato que ha intentado subir excede el tamaño permitido (2MB)');
                                            abreHeaderFpago();
                                            btnEstCuenta.classList.add("btnError");
                                        }
                                        if(respuesta == 'errorDirEntExt'){
                                            toastError('Diracción invalida');
                                            errorInput(txtDireccionEntExt,'Inserta dirección');
                                        }
                                        if(respuesta == 'errorClasificacionEnt'+resarray){
                                            toastError('error en clasificacion');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorAliasEntregas'+resarray){
                                            toastError('error en alias');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorCalleEnt'+resarray){
                                            toastError('Calle invalida');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorNumExtEnt'+resarray){
                                            toastError('Número exteriór invalido');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorNumIntEnt'+resarray){
                                            toastError('Número interiór invalido');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorcPostalEnt'+resarray){
                                            toastError('Selecciona Código postal');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorColEnt'+resarray){
                                            toastError('Selecciona Código postal');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorDelEnt'+resarray){
                                            toastError('Selecciona Código postal');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorEntidadEnt'+resarray){
                                            toastError('Selecciona Código postal');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorlocalidadEnt'+resarray){
                                            toastError('Localidad invalida');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorcalle1refEnt'+resarray){
                                            toastError('Calle de referencia invalida');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorcalle2refEnt'+resarray){
                                            toastError('Calle de referencia invalida');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if(respuesta == 'errorreferenciaEnt'+resarray){
                                            toastError('Punto de referencia invalido');
                                            $(tHeadEntregas).addClass("btnError");
                                            var numTrCont = resarray+1;
                                            var posTrCont = $(tBodyEntregas).find("tr").eq(numTrCont);
                                            $(posTrCont).addClass("btnError");
                                        }
                                        if (respuesta == 'ClientSaved'){
                                            var $toastContent = $('<div class="btnCorrecto">registrado exitosamente</div>');
                                            provereg();
                                            Materialize.toast($toastContent,5000);         
                                            location.reload();
                                        }
                                    }
                                });
                            } else {
                            }
                        })
                    };

                    function llenarPdfImg(e,valor,boton,destino,carga){
                        //objeto de la clase FileReader
                        let reader = new FileReader();
                        reader.readAsDataURL(e.target.files[0]);
                        var typeElemento = e.target.files[0].type;
                        var tamano = e.target.files[0].size;
                        if (typeElemento == "image/jpeg" || typeElemento == "image/png" || typeElemento == "application/pdf") {
                            if (tamano < 2000000) {
                                carga.classList.remove("noneView");
                                reader.onload = function(){
                                    switch(typeElemento){
                                        case "image/jpeg":
                                            let imgJpg = '<img class="circle responsive-img materialboxed initialized" src="'+reader.result+'">';
                                            destino.innerHTML = imgJpg;
                                        break;
                                        case "image/png":
                                            let imgPng = '<img class="circle responsive-img materialboxed initialized" src="'+reader.result+'">';
                                            destino.innerHTML = imgPng;
                                        break;
                                        case "application/pdf":
                                            var docPdf = document.createElement("embed");
                                            docPdf.setAttribute("src",reader.result); 
                                            docPdf.setAttribute("type","application/pdf");
                                            destino.appendChild(docPdf);
                                        break;
                                        default:
                                    }
                                    boton.classList.remove("btnError");
                                    destino.classList.remove("noneView");
                                    carga.classList.add("noneView");
                                }
                            } else {
                                boton.classList.add("btnError");
                                destino.classList.add("noneView");
                                carga.classList.add("noneView");
                                valor = '';
                                cuteAlert({
                                    type: "error",
                                    title: "Error",
                                    message: "El tamaño del archivo no debe superar los 2MB",
                                    buttonText: "Cerrar"
                                })
                            }
                        } else {
                            boton.classList.add("btnError");
                            destino.classList.add("noneView");
                            carga.classList.add("noneView");
                            valor = '';
                            cuteAlert({
                                type: "error",
                                title: "Error",
                                message: "El archivo debe estar en formato .jpg, .png ó .pdf",
                                buttonText: "Cerrar"
                            })
                        }
                    }

                    function error_ext() {
                        Push.create("El archivo debe estar en formato .jpg, .png ó .pdf", {
                            body: "SOS-México",
                            icon: "vista/media/adm/usuarios/perfiles/logoSOS.png",
                            timeout: 3000,
                        });
                    };
                
                    function Pesado() {
                        Push.create("El archivo no debe superar los 2MB", {
                            body: "SOS-México",
                            icon: "vista/media/adm/usuarios/perfiles/logoSOS.png",
                            timeout: 3000,
                        });
                    };
                
                    function camposVacios() {
                        Push.create("COMPLETE LOS CAMPOS", {
                            body: "SOS-México",
                            icon: "vista/media/landing/logotipo/314g.jpg",
                            timeout: 3000,
                        });
                    };
                
                    function provereg() {
                        Push.create("CLIENTE REGISTRADO", {
                            body: "SOS-México",
                            icon: "vista/media/landing/logotipo/314g.jpg",
                            timeout: 3000,
                        });
                    };
                
                    function rfcVacio() {
                        Push.create("DEBE REGISTRAR RFC", {
                            body: "SOS-México",
                            icon: "vista/media/landing/logotipo/314g.jpg",
                            timeout: 3000,
                        });
                    };
                
                    function rfcInvalido(){
                        Push.create("RFC INVALIDO", {
                            body: "SOS-México",
                            icon: "vista/media/landing/logotipo/314g.jpg",
                            timeout: 3000,
                        });
                    };

});