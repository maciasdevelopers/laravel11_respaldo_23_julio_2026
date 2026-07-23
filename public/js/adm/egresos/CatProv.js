$(document).ready(function(){
    $("#tabListaGenProv").on("click","td a#btnInfoProv",function(){
        verDataProv(this);
    });

    $("#tabListaNacProv").on("click","td a#btnInfoProv",function(){
        verDataProv(this);
    });

    $("#tabListaExtProv").on("click","td a#btnInfoProv",function(){
        verDataProv(this);
    });

    function verDataProv(boton){
        var proveedor = $(boton).parents("tr").find("td").eq(0).html();
        $.ajax({
            url: 'egresos-modalviewproveedor',
            type: 'POST',
            datatype: 'html',
            data: {proveedor: proveedor},
        })
        .done(function(respuesta){
            $("#dataModalProv").html(respuesta);
        })
        .fail(function(){
        console.log("error");
        })
    }

    //formulario
        var media = window.matchMedia("(max-width: 400px)");
        //funciones generales
        function errorInput(valor,mensaje){
            var divParent = valor.parentElement;
            var errorlbl = divParent.querySelector('label');
                errorlbl.className = "activeInput errorlabel";
                errorlbl.innerText = mensaje;
        };

        function errorSelect(valor,mensaje){
            var divParent = valor.parentElement.parentElement;
            var errorlbl = divParent.querySelector('label');
                errorlbl.className = "activeSelect errorlabel";
                errorlbl.innerText = mensaje;
        };

        function correctoInput(valor,mensaje){
            var divParent = valor.parentElement;
            var correctlbl = divParent.querySelector('label');
                correctlbl.className = "activeInput";
                correctlbl.innerText = mensaje;
        };

        function correctoSelect(valor,mensaje){
            var divParent = valor.parentElement.parentElement;
            var correctlbl = divParent.querySelector('label');
                correctlbl.className = "activeSelect";
                correctlbl.innerText = mensaje;
        };

        function soloNumeros(e){
            var key = e.charCode;
            console.log(key);
            return key >= 48 && key <= 57;
        };
    
    //validacion rfc
        //verificar tipo y subtipo de proveedor
            var radioTiprov1 = document.getElementById("provNacional");
            var radioTiprov2 = document.getElementById("provExtranjero");
            var backTipoProv = document.getElementById("backTipoProv");

            var radioSubTipoProbee1 = document.getElementById("provFisica");
            var radioSubTipoProbee2 = document.getElementById("provMoral");
            var backSubTipoProv = document.getElementById("backSubTipoProv");

            var dvVerificaProv = document.getElementById("dvVerificaProv");
            var btnBuscaProvDB = document.getElementById("btnBuscaProvDB");
            var dataregProv = document.getElementById("data-regProv");

            //tipo 
                $('input[name="tipoProv"]').click(function(){
                    radioTiprov1.disabled = true;
                    radioTiprov2.disabled = true;
                    radioSubTipoProbee1.removeAttribute("disabled");
                    radioSubTipoProbee2.removeAttribute("disabled");
                    backTipoProv.classList.remove("noneView");
                });
        
            //subtipo
                var datProvExtranjero = document.getElementById("datProvExtranjero");
                var verifRfcPaterno = document.getElementById("verifRfcPaterno");
                var verifRfcMaterno = document.getElementById("verifRfcMaterno");
                var verifRfcnombre = document.getElementById("verifRfcnombre");
                var divRfcProv = document.getElementById("divRfcProv");
                var txtrfcProv = document.getElementById("verif_rfcProv");
                var lbl_proveedor = document.getElementById("lbl_proveedor");
                var resRfc = document.getElementById("rfc_View");
                
                var nombrePF = document.getElementById("nombrePF");
                var viewPFExt = document.getElementById("viewPFExt");

                $('input[name="subtipoProv"]').click(function(){
                    backSubTipoProv.classList.remove("noneView");
                    var radioProv = document.querySelector('input[name="tipoProv"]:checked');
                    if (radioProv) {
                        txtrfcProv.removeAttribute("disabled");
                        lbl_proveedor.classList.remove("disabled");
                        if (radioProv.value == 'nacional') {
                            if (this.value == 'provFisica') {
                                lbl_proveedor.innerHTML='Escriba su rfc con Homoclave (13 caracteres Ej. ABCD000000XXX)';
                                txtrfcProv.setAttribute("data-length","13");
                                txtrfcProv.setAttribute("placeholder","Ej. ABCD000000XXX");
                                txtrfcProv.setAttribute("maxlength","13");
                            }
                            if (this.value == 'provMoral') {
                                lbl_proveedor.innerHTML='Escriba su rfc con Homoclave (12 caracteres Ej. ABC000000XXX)';
                                txtrfcProv.setAttribute("data-length","12");
                                txtrfcProv.setAttribute("placeholder","Ej. ABC000000XXX");
                                txtrfcProv.setAttribute("maxlength","12");
                            }
                        }
                        if (radioProv.value == 'extranjero') {
                            txtrfcProv.setAttribute("minlength","9");
                            txtrfcProv.setAttribute("maxlength","40");

                            if (this.value == 'provFisica') {
                                //lbl_proveedor.innerHTML='Escriba idTax ó nombre completo del cliente';
                                divRfcProv.classList.add("noneView");
                                txtrfcProv.disabled = true;
                                lbl_proveedor.classList.add("disabled");
                                datProvExtranjero.classList.remove("noneView");
                            }
                            if (this.value == 'provMoral') {
                                lbl_proveedor.innerHTML='Escriba idTax ó razon social del cliente';
                            }

                        }
                        btnBuscaProvDB.classList.remove("noneView");
                    } else {
                        
                    }
                });
            
            $(verifRfcPaterno).keyup(function(){
                checkPaterno(this);
            });
            $(verifRfcMaterno).keyup(function(){
                checkMaterno(this);
            });
            $(verifRfcnombre).keyup(function(){
                checkNombre(this);
            });                
            
            $(txtrfcProv).keyup(function(){
                var radioProv = document.querySelector('input[name="tipoProv"]:checked');
                var subtipoProv = document.querySelector('input[name="subtipoProv"]:checked');
                if (this.value != '') {

                    if (radioProv.value == 'nacional') {
                        if (subtipoProv.value == 'provFisica') {
                            var cdna1 = txtrfcProv.value.substring(0,4);
                            var cdna2 = txtrfcProv.value.substring(4,10);
                            var cdna3 = txtrfcProv.value.substring(10,13);
                            if (/^[a-zA-Z]+$/.test(cdna1)) {
                                if (/^[0-9]+$/.test(cdna2)) {
                                    if (/^[a-zA-Z0-9]+$/.test(cdna3) && txtrfcProv.value.length == 13) {
                                        correctoInput(txtrfcProv,'Escriba su rfc con Homoclave');
                                    } else {
                                        errorInput(txtrfcProv,'su RFC no es correcto');
                                    }
                                } else {
                                    errorInput(txtrfcProv,'su RFC no es correcto');
                                }
                            } else {
                                errorInput(txtrfcProv,'su RFC no es correcto');
                            }
                        }
                        if (subtipoProv.value == 'provMoral') {
                            var cdna1 = txtrfcProv.value.substring(0,3);
                            var cdna2 = txtrfcProv.value.substring(3,9);
                            var cdna3 = txtrfcProv.value.substring(9,12);
                            if (/^[a-zA-Z]+$/.test(cdna1)) {
                                if (/^[0-9]+$/.test(cdna2)) {
                                    if (/^[a-zA-Z0-9]+$/.test(cdna3) && txtrfcProv.value.length == 12) {
                                        correctoInput(txtrfcProv,'Escriba su rfc con Homoclave');
                                    }
                                    else{
                                        errorInput(txtrfcProv,'su RFC no es correcto');
                                    }
                                }
                                else{
                                    errorInput(txtrfcProv,'su RFC no es correcto');
                                }
                            }
                            else{
                                errorInput(txtrfcProv,'su RFC no es correcto');
                            }
                        }
                    }

                    if (radioProv.value == 'extranjero') {
                        if (subtipoProv.value == 'provFisica') {
                            if (this.value.length> 9 && this.value.length <40 && strFilEmp.test(this.value)) {
                                correctoInput(txtrfcProv,'Escriba idTax ó nombre completo del cliente');
                            } else {
                                errorInput(txtrfcProv,'IdTax ó nombre del cliente es invalido');
                            }
                        }
                        if (subtipoProv.value == 'provMoral') {
                            if ((this.value.length> 9 && this.value.length <40) && strFilEmp.test(this.value)) {
                                correctoInput(txtrfcProv,'Escriba idTax ó razon social del cliente');
                            } else {
                                errorInput(txtrfcProv,'IdTax ó razon social del cliente es invalida');
                            }
                        }
                    }
                } else {
                    if (radioProv.value == 'nacional') {
                        if (subtipoProv.value == 'provFisica') {
                            errorInput(txtrfcProv,'Rfc incorrecto (13 caracteres Ej. ABCD000000XXX)');
                        }
                        if (subtipoProv.value == 'provMoral') {
                            errorInput(txtrfcProv,'Rfc incorrecto (12 caracteres Ej. ABC000000XXX)');
                        }
                    }

                    if (radioProv.value == 'extranjero') {
                        if (subtipoProv.value == 'provFisica') {
                            errorInput(txtrfcProv,'IdTax ó nombre del cliente es invalido');
                        }
                        if (subtipoProv.value == 'provMoral') {
                            errorInput(txtrfcProv,'IdTax ó razon social del cliente es invalida');
                        }
                    }
                }
            });            
            
            //verifica rfc del Cliente subtipoProv
            $(btnBuscaProvDB).click(function() {
                var radioProv = document.querySelector('input[name="tipoProv"]:checked');
                var subtipoProv = document.querySelector('input[name="subtipoProv"]:checked');
                if (radioProv.value == 'nacional') {
                    if (subtipoProv) {
                        if (subtipoProv.value == 'provFisica') {
                            if (txtrfcProv.value != '' && txtrfcProv.value.length == 13) {
                                var cdna1 = txtrfcProv.value.substring(0,4);
                                var cdna2 = txtrfcProv.value.substring(4,10);
                                var cdna3 = txtrfcProv.value.substring(10,13);
                                if ((/^[a-zA-Z]+$/.test(cdna1)) && (/^[0-9]+$/.test(cdna2)) && (/^[a-zA-Z0-9]+$/.test(cdna3)) ) {
                                    correctoInput(txtrfcProv,'Escriba su rfc con Homoclave');
                                    var mensaje = confirm("¿Su proveedor es Persona Física?");
                                    if (mensaje) {
                                        validaClientMySQL(txtrfcProv.value);
                                    }
                                } else {
                                    errorInput(txtrfcProv,'su RFC no es correcto');
                                    rfcInvalido();
                                }
                            } else {
                                if (txtrfcProv.value == '') {
                                    rfcVacio();
                                    errorInput(txtrfcProv,'Inserta Rfc de su proveedor');
                                }

                                if (txtrfcProv.value.length != 13) {
                                    errorInput(txtrfcProv,'Su rfc debe contener 13 caracteres');
                                    rfcInvalido();
                                }   
                            }
                        }

                        if (subtipoProv.value == 'provMoral') {
                            if(txtrfcProv.value != '' && txtrfcProv.value.length == 12) {
                                var cdna1 = txtrfcProv.value.substring(0,3);
                                var cdna2 = txtrfcProv.value.substring(3,9);
                                var cdna3 = txtrfcProv.value.substring(9,12);
                                if ((/^[a-zA-Z]+$/.test(cdna1)) && (/^[0-9]+$/.test(cdna2)) && (/^[a-zA-Z0-9]+$/.test(cdna3))) {
                                    correctoInput(txtrfcProv,'Escriba su rfc con Homoclave');
                                    var mensaje = confirm("¿Su proveedor es Persona Moral?");
                                    if (mensaje) {
                                        validaClientMySQL(txtrfcProv.value);
                                    }
                                }
                                else{
                                     errorInput(txtrfcProv,'su RFC no es correcto');
                                     rfcInvalido();
                                }
                            } else {
                                if (txtrfcProv.value == '') {
                                    rfcVacio();
                                    errorInput(txtrfcProv,'Inserta Rfc de su proveedor');
                                }

                                if (txtrfcProv.value.length != 12) {
                                    errorInput(txtrfcProv,'Su rfc debe contener 12 caracteres');
                                    rfcInvalido();
                                }  
                            }
                        }

                    } else {
                        toastError('seleccione subtipo de proveedor');
                    }
                }

                if (radioProv.value == 'extranjero') {
                    if (subtipoProv) {
                        if (subtipoProv.value == 'provFisica') {
                            if ((verifRfcPaterno.value != '' && strFilter.test(verifRfcPaterno.value) && verifRfcPaterno.value.length >= 4) && 
                                (verifRfcMaterno.value != '' && strFilter.test(verifRfcMaterno.value) && verifRfcMaterno.value.length >= 4) && 
                                (verifRfcnombre.value != '' && strFilter.test(verifRfcnombre.value) && verifRfcnombre.value.length >= 3)) {
                                var mensaje = confirm("¿Su proveedor es Persona Física?");
                                if (mensaje) {
                                    validaClientMySQLPF(verifRfcPaterno.value,verifRfcMaterno.value,verifRfcnombre.value);
                                }
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

                        if (subtipoProv.value == 'provMoral') {
                            if (txtrfcProv.value != '' && txtrfcProv.value.length >= 9 && txtrfcProv.value.length <= 40) {
                                correctoInput(txtrfcProv,'Escriba su rfc con Homoclave');
                                var mensaje = confirm("¿Su proveedor es Persona Moral?");
                                if (mensaje) {
                                    validaClientMySQL(txtrfcProv.value);
                                }
                            } else {
                                if (txtrfcProv.value == '') {
                                    rfcVacio();
                                    errorInput(txtrfcProv,'Inserta nombre de su Cliente');
                                }

                                if (txtrfcProv.value.length < 9 || txtrfcProv.value.length > 40) {
                                    errorInput(txtrfcProv,'Su rfc debe contener 12 caracteres');
                                    rfcInvalido();
                                }  
                            }
                        }
                    } else {
                        toastError('seleccione subtipo de proveedor');
                    }
                }
            });
        
            function validaClientMySQL(rfc){
                var radioProv = document.querySelector('input[name="tipoProv"]:checked');
                var subtipoProv = document.querySelector('input[name="subtipoProv"]:checked'); 

                if (radioProv.value == 'nacional') {
                    $.ajax({
                        url: 'egresos-busquedaproveedor',
                        type: 'POST',
                        datatype: 'html',
                        data: {proveedorrfc:rfc}
                    })
                    .done(function(respuesta){
                        alert(respuesta)
                        console.log(respuesta)
                        if (respuesta == 'errorVerifRfc') {
                            toastError('error al verificar el rfc/idTax'+rfc);
                        } 

                        if (respuesta == 'registred') {
                            toastError('el proveedor con el rfc '+rfc+' ya ha sido registrado');
                        } 

                        if (respuesta == 'notRegistred') {
                            var $toastContent = $('<div class="btnCorrecto">el proveedor con el rfc '+rfc+' no ha sido registrado</div>');
                            Materialize.toast($toastContent,5000);

                            rfcVerifProv.classList.add("noneView");
                            dataregProv.classList.remove("noneView");

                            resRfc.innerHTML = txtrfcProv.value;
                            varriablleRfc = txtrfcProv.value;
                            vtipoProv = 'nacional';
                            direccionNacional.classList.remove("noneView");

                            if (subtipoProv.value == 'provMoral'){
                                datGenPM.classList.remove("noneView");
                                datGenPM.classList.add("active");
                                datGenPMBody.classList.add("activeView");
                                txtempresa_reg.required = true;
                            }
                            if(subtipoProv.value == 'provFisica'){
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

                if (radioProv.value == 'extranjero') {
                    $.ajax({
                        url: 'egresos-busquedaextproveedor',
                        type: 'POST',
                        datatype: 'html',
                        data: {denominacion:rfc}
                    })
                    .done(function(respuesta){
                        alert(respuesta)
                        if (respuesta == 'errorVerifRfc') {
                            toastError('error al verificar el rfc/idTax'+rfc);
                        } 

                        if (respuesta == 'registred') {
                            toastError('el proveedor con el rfc/idTax '+rfc+' ya ha sido registrado');
                        } 

                        if (respuesta == 'notRegistred') {
                            var $toastContent = $('<div class="btnCorrecto">el proveedor con el rfc/idTax '+rfc+' no ha sido registrado</div>');
                            Materialize.toast($toastContent,5000);

                            rfcVerifProv.classList.add("noneView");
                            dataregProv.classList.remove("noneView");

                            resRfc.innerHTML = "xexx010101000";
                            varriablleRfc = "xexx010101000";
                            nombrePF.classList.remove("noneView");
                            viewPFExt.innerHTML = rfc;
                            direccionFiscalExtran.classList.remove("noneView");
                            vtipoProv = 'extranjero';

                            if (subtipoProv.value == 'provMoral'){
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
                    url: 'egresos-busquedapfextproveedor',
                    type: 'POST',
                    datatype: 'html',
                    data: {
                        rfcPaterno:rfcPaterno,
                        rfcMaterno:rfcMaterno,
                        rfcNombre:rfcNombre
                    }
                })
                .done(function(respuesta){
                    alert(respuesta)
                    if (respuesta == 'errorVerifRfc') {
                        toastError('error al verificar el nombre/idTax'+rfcPaterno+" "+rfcMaterno+" "+rfcNombre);
                    } 

                    if (respuesta == 'registred') {
                        toastError('el proveedor con el nombre/idTax '+rfcPaterno+" "+rfcMaterno+" "+rfcNombre+' ya ha sido registrado');
                    } 

                    if (respuesta == 'notRegistred') {
                        var $toastContent = $('<div class="btnCorrecto">el proveedor con el rfc/idTax '+rfcPaterno+" "+rfcMaterno+" "+rfcNombre+' no ha sido registrado</div>');
                        Materialize.toast($toastContent,5000);

                        rfcVerifProv.classList.add("noneView");
                        dataregProv.classList.remove("noneView");

                        var radioProv = document.querySelector('input[name="tipoProv"]:checked');
                        var subtipoProv = document.querySelector('input[name="subtipoProv"]:checked'); 


                        resRfc.innerHTML = "xexx010101000";
                        varriablleRfc = "xexx010101000";
                        nombrePF.classList.remove("noneView");
                        viewPFExt.innerHTML = rfcPaterno+" "+rfcMaterno+" "+rfcNombre;
                        direccionFiscalExtran.classList.remove("noneView");
                        vtipoProv = 'extranjero';

                        if(subtipoProv.value == 'provFisica'){
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
                

        //contenido del formulario
        var vtipoProv;
        var correoRegex = /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
        var strFilter = /^[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]*$/;
        var strFilEmp = /^[A-Za-z0-9ƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.-]*$/; 
        var filtroDom = /^[A-Za-z0-9ƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.-]*$/; 
        var filtroUrl = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/; 
        
        //validaciones
            //persona fisica
                var datGenPF = document.getElementById("datGenPF");

                var datGenPFHead = document.getElementById("datGenPFHead");
                var datGenPFBody = document.getElementById("datGenPFBody");

                var txtPaternoPF_reg = document.getElementById("txtPaternoPF_reg");
                var txtMaternoPF_reg = document.getElementById("txtMaternoPF_reg");
                var txtnombrePF_reg = document.getElementById("txtnombrePF_reg");
                var txtnomCom_regPF = document.getElementById("txtnomCom_regPF");
                var txtcurpPF_reg = document.getElementById("txtcurpPF_reg");
                var selPaisExtPF_reg = document.getElementById("selPaisExtPF_reg");
                var txtsitWebPF_reg = document.getElementById("txtsitWebPF_reg");
                var tabRedSocialPF = document.getElementById("tabRedSocialPF");
                var arrayRedesPF = [];
                $(txtPaternoPF_reg).keyup(function(){
                    checkPaterno(this);
                    if (vtipoProv == 'extranjero') {
                        verificaEmpIguales();
                    }
                });

                $(txtMaternoPF_reg).keyup(function(){
                    checkMaterno(this);
                    if (vtipoProv == 'extranjero') {
                        verificaEmpIguales();
                    }
                });

                $(txtnombrePF_reg).keyup(function(){
                    checkNombre(this);
                    if (vtipoProv == 'extranjero') {
                        verificaEmpIguales();
                    }
                });

                function verificaEmpIguales(){
                    var nombre = txtPaternoPF_reg.value+" "+txtMaternoPF_reg.value+" "+txtnombrePF_reg.value;
                    if (nombre == viewPFExt.innerHTML) {
                        correctoInput(txtPaternoPF_reg,'Apellido Paterno');
                        correctoInput(txtMaternoPF_reg,'Apellido Materno');
                        correctoInput(txtnombrePF_reg,'Nombre(s)'); 
                    } else {
                        errorInput(txtPaternoPF_reg,'Inserta Apellido Paterno');
                        errorInput(txtMaternoPF_reg,'Inserta Apellido Materno');
                        errorInput(txtnombrePF_reg,'Inserta Nombre(s)');
                    }
                }

                $(txtnomCom_regPF).keyup(function(){
                    checkNombreCom(this);
                });

                $(txtcurpPF_reg).keyup(function(){
                    checkCurpTax(this);
                });

                $(selPaisExtPF_reg).change(function(){
                    checkSelectPais(this);
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
                    if (vtipoProv == 'extranjero') {
                        verificaEmpIgualesPM(this);
                    }
                });

                function verificaEmpIgualesPM(empresa){
                    console.log(viewPFExt.innerHTML+" "+empresa.value);
                    if (empresa.value == viewPFExt.innerHTML) {
                        correctoInput(empresa,'Empresa');
                    } else {
                        errorInput(empresa,'Inserta empresa');
                    }
                }

                $(txtidtax_reg).keyup(function(){
                    checkCurpTax(this);
                });

                $(selPaisExtPM_reg).change(function(){
                    checkSelectPais(this);
                });

                $(txtnomCom_regPM).keyup(function(){
                    checkNombreCom(this);
                });
                
                $(txtsitWeb_regPM).keyup(function(){
                    checkSitWeb(this);
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
                            inputEdiPM.val("https://www.facebook.com/");
                        }
                        if (this.classList.contains("icon-instagram")) {
                            inputEdiPM.val("https://www.facebook.com/");
                        } 
                        if (this.classList.contains("icon-youtube")) {
                            inputEdiPM.val("https://www.facebook.com/");
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

                $(txtMaternoCont_reg).keyup(function(){
                    checkMaterno(this);
                });

                $(txtNombreCont_reg).keyup(function(){
                    checkNombre(this);  
                });

                $(txtAreaCont_reg).keyup(function(){
                    if (this.value === '' || !strFilEmp.test(this.value)) {
                        errorInput(this,'Inserta Area');
                    } else {
                        correctoInput(this,'Area');
                    }
                });

                $(txtCargoCont_reg).keyup(function(){
                    if (this.value === '' || !strFilter.test(this.value)) {
                        errorInput(this,'Inserta Cargo');
                    } else {
                        correctoInput(this,'Cargo');
                    }
                });

                $(txtEmailCont_reg).keyup(function(){
                    checkMail(this);
                });
                
                $(txtTelefonoCont_reg).keyup(function(){
                    checkTelefono(this);
                });

                $(txtExtension_reg).keyup(function(){
                    checkExtension(this);
                });

                $(addInfoContacto).click(function() {
                    if ((txtPaternoCont_reg.value != '' && strFilter.test(txtPaternoCont_reg.value) && txtPaternoCont_reg.value.length >= 4) && 
                        (txtMaternoCont_reg.value != '' && strFilter.test(txtMaternoCont_reg.value) && txtMaternoCont_reg.value.length >= 4) && 
                        (txtNombreCont_reg.value != '' && strFilter.test(txtNombreCont_reg.value) && txtNombreCont_reg.value.length >= 3) && 
                        (txtAreaCont_reg.value != '' && strFilter.test(txtAreaCont_reg.value) && txtAreaCont_reg.value.length >= 5) &&
                        (txtCargoCont_reg.value != '' && strFilter.test(txtCargoCont_reg.value) && txtCargoCont_reg.value.length >= 5) && 
                        (txtEmailCont_reg.value != '' && correoRegex.test(txtEmailCont_reg.value) && txtEmailCont_reg.value.length >= 20) && 
                        (txtEmailCont_reg.value.includes('gmail.com') || txtEmailCont_reg.value.includes('hotmail.com') || 
                        txtEmailCont_reg.value.includes('outlook.com') || txtEmailCont_reg.value.includes('yahoo.com') || 
                        txtEmailCont_reg.value.includes(txtsitWebPF_reg.value) || txtEmailCont_reg.value.includes(txtsitWeb_regPM.value)) &&
                        (txtTelefonoCont_reg.value != '' && /^[0-9]+$/.test(txtTelefonoCont_reg.value) && txtTelefonoCont_reg.value.length >= 5)) {

                        if (txtExtension_reg.value != '') {
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

                        if(txtAreaCont_reg.value == '' || !strFilter.test(txtAreaCont_reg.value) || txtAreaCont_reg.value.length < 5){
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
                    }
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
                    var mensaje = confirm("¿Deseas eliminar este registro?");
                    var trElimina = $(this).parent("td").parent("tr");
                    if (mensaje) {
                        if (tbodyDatosConta.childNodes.length == 4) {
                            trVacioDatCont.classList.remove("noneView");
                            trElimina.remove();
                        } else {
                            trElimina.remove();
                        }
                    }
                });

            //informacion fiscal
                var datFiscal = document.getElementById("datFiscal");

                var collapsible_headerfiscal = document.getElementById("collapsible-headerfiscal");
                var collapsible_bodyfiscal = document.getElementById("collapsible-bodyfiscal");
                var btnSituFiscal = document.getElementById("btnSituFiscal");
                var imgpdfSfiscal = document.getElementById("imgpdfSfiscal");
                
                var direccionFiscalExtran = document.getElementById("direccionFiscalExtran");
                var direccionNacional = document.getElementById("direccionNacional");
                var situacion_fiscal = document.getElementById("id_situacion_fiscal");
                var txtDireccionExt = document.getElementById("txtDireccExtProv");
                var txtCodPostalExtProv = document.getElementById("txtCodPostalExtProv");
                var tHeadDireccionfiscal = document.getElementById("tHeadDireccionfiscal");
                var txtcalle_reg = document.getElementById("txtcalle_reg");
                var txtnumext_reg = document.getElementById("txtnumext_reg");
                var txtnumint_reg = document.getElementById("txtnumint_reg");
                var txtCodPostal = document.getElementById("txtCodPostal");
                var txtlocalidad_reg = document.getElementById("txtlocalidad_reg");
                var txtcalle1ref_reg = document.getElementById("txtcalle1ref_reg");
                var txtcalle2ref_reg = document.getElementById("txtcalle2ref_reg");
                var txtreferencia_reg = document.getElementById("txtreferencia_reg");

                $(situacion_fiscal).change(function(e){
                    var valor = this.value;
                    var boton = btnSituFiscal;
                    if (imgpdfSfiscal.hasChildNodes()) {
                        imgpdfSfiscal.removeChild(imgpdfSfiscal.firstElementChild);
                        imgpdfSfiscal.classList.add("noneView");
                    }
                    var destino = imgpdfSfiscal;
                    var carga = document.getElementById("cargaSF");
                    llenarPdfImg(e,valor,boton,destino,carga);
                });

                $(txtCodPostalExtProv).keyup(function() {
                    checkPostalExt(this);
                });

                $(txtDireccionExt).keyup(function(){
                    checkDireccionExt(this);
                });

                $(txtcalle_reg).keyup(function(){
                    if (this.value === '' || !filtroDom.test(this.value)) {
                        errorInput(this,'Inserta calle');
                    } else {
                        correctoInput(this,'Calle');
                    }
                });

                $(txtnumext_reg).keyup(function(){
                    if (this.value === '' || !(/^[0-9]+$/.test(this.value))) {
                        errorInput(this,'Inserta Número exteriór');
                    } else {
                        correctoInput(this,'Número exteriór');
                    }
                });

                $(txtnumext_reg).keypress(function(e){
                    if (!soloNumeros(event)) {
                        e.preventDefault();
                    }
                });

                $(txtnumint_reg).keyup(function(){
                    if (this.value == '' || !(/^[0-9]+$/.test(this.value)) ) {
                        errorInput(this,'Inserta Número interiór');
                    } else {
                        correctoInput(this,'Número interiór');
                    }
                });

                $(txtnumint_reg).keypress(function(e){
                    if (!soloNumeros(event)) {
                        e.preventDefault();
                    }
                });

                $(buscarMuni());
                function buscarMuni(cpostal) {
                    $.ajax({
                        url: 'egresos-buscadireccion',
                        type: 'POST',
                        datatype: 'html',
                        data: {
                            cpostal: cpostal,
                        },
                    })
                    .done(function(respuesta){
                        $("#dataSelectMuni").html(respuesta);
                    })
                    .fail(function(){
                    console.log("error");
                    })
                };

                $(txtCodPostal).keyup(function(){
                    checkTelefono(this);
                    var cpostal = this.value;
                    if (cpostal != '' && /^[0-9]+$/.test(this.value) && this.value.length == 5) {
                        buscarMuni(cpostal);
                    } else {
                        buscarMuni(); 
                    }
                });

                $(txtCodPostal).keypress(function(e){
                    if (!soloNumeros(event)) {
                        e.preventDefault();
                    }
                }); 

                $("#tableDireccion").on("click","td input#checkCP",function(){
                    if (tHeadDireccionfiscal.classList.contains("btnError")) {
                        tHeadDireccionfiscal.classList.remove("btnError");
                    }
                });

                $(txtlocalidad_reg).keyup(function(){
                    if (this.value === '' || !filtroDom.test(this.value)) {
                        errorInput(this,'Inserta localidad');
                    } else {
                        correctoInput(this,'Localidad');
                    }
                });

                $(txtcalle1ref_reg).keyup(function(){
                    if (this.value === '' || !filtroDom.test(this.value)) {
                        errorInput(this,'Inserta calle de referencia');
                    } else {
                        correctoInput(this,'Entre calle');
                    }
                });

                $(txtcalle2ref_reg).keyup(function(){
                    if (this.value === '' || !filtroDom.test(this.value)) {
                        errorInput(this,'Inserta calle de referencia');
                    } else {
                        correctoInput(this,'Y calle');
                    }
                });

                $(txtreferencia_reg).keyup(function(){
                    if (this.value === '' || !filtroDom.test(this.value)) {
                        errorInput(this,'Inserta Referencia');
                    } else {
                        correctoInput(this,'Referencia');
                    }
                });

            //credito
                var lidatacredito = document.getElementById("lidatacredito");

                var collapsible_headerCredito = document.getElementById("collapsible-headerCredito");
                var collapsible_bodyCredito = document.getElementById("collapsible-bodyCredito");
                var divcredito = document.getElementById("divcredito");

                var serrorCred = document.getElementById("serrorCred");

                var txtMoneda_reg = document.getElementById("txtMoneda_reg");
                var limiteCredito = document.getElementById("txtlimiteCredito_reg");
                var txtdiaspagoCredit_reg = document.getElementById("txtdiaspagoCredit_reg");

                $('input[name="aceptaCredito"]').click(function(){
                    if (!serrorCred.classList.contains("noneView")){
                        serrorCred.classList.add("noneView");
                    }
                    if (this.value == "si") {
                        divcredito.classList.remove("noneView");
                        divcredito.classList.add("credito");
                        limiteCredito.required = true;
                        txtdiaspagoCredit_reg.required = true;
                    }
                    if (this.value == "no") {
                        limiteCredito.required = false;
                        txtdiaspagoCredit_reg.required = false;
                        limiteCredito.value = '';
                        txtdiaspagoCredit_reg.value = '';
                        divcredito.classList.remove("credito");
                        divcredito.classList.add("noneView");
                    }
                });

                $(txtMoneda_reg).keyup(function(){
                    if (this.value === '' || !strFilEmp.test(this.value) || !this.value.length >= 5) {
                        errorInput(this,'Inserta Moneda');
                    } else {
                        correctoInput(this,'Moneda');
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

            //forma de pago
                var lidataformapago = document.getElementById("lidataformapago");

                var collapsible_headerfPago = document.getElementById("collapsible-headerfPago");
                var collapsible_bodyfPago = document.getElementById("collapsible-bodyfPago");
                var divtransfer = document.getElementById("divtransfer");
                var btnEstadoCuenta = document.getElementById("btnEstadoCuenta");
                var imgpdfEstCuenta = document.getElementById("imgpdfEstCuenta");

                var formaPago = document.getElementById("formaPago");
                var estado_cuenta = document.getElementById("estado_cuenta");
                var clabeIntBanc = document.getElementById("txtClabeIntBanc_reg");

                $(formaPago).change(function(){
                    if (this.value == '') {
                        errorSelect(this,'Inserta Apellido Materno');
                    } else {
                        correctoSelect(this,'Apellido Materno');
                        if (this.value == 3) {
                            divtransfer.classList.remove("noneView");
                            divtransfer.classList.add("transferencia");
                            clabeIntBanc.required = true;
                            estado_cuenta.required = true;
                        } else {
                            if (divtransfer.classList.contains("transferencia")) {
                                divtransfer.classList.remove("transferencia");
                                divtransfer.classList.add("noneView");
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
                        var valor = clabeIntBanc.value.length;
                        if (valor == 3 || valor == 7 || valor == 19) {
                            clabeIntBanc.value = clabeIntBanc.value + "-";
                        }
                    }
                });

                $(clabeIntBanc).keypress(function(e){
                    if (!soloNumeros(e)) {
                        e.preventDefault();
                    }
                }); 
            
                $(estado_cuenta).change(function(e){
                    var valor = this.value;
                    var boton = btnEstadoCuenta;
                    if (imgpdfEstCuenta.hasChildNodes()) {
                        imgpdfEstCuenta.removeChild(imgpdfEstCuenta.firstElementChild);
                        imgpdfEstCuenta.classList.add("noneView");
                    }
                    var destino = imgpdfEstCuenta;
                    var carga = document.getElementById("cargaEstCuenta");
                    llenarPdfImg(e,valor,boton,destino,carga);
                });

            //direccion de sucursal
                var lidirsucursal = document.getElementById("lidirsucursal");

                var collapsible_headerDirSuc = document.getElementById("collapsible-headerDirSuc");
                var collapsible_bodyDirSuc = document.getElementById("collapsible-bodyDirSuc");

                var serrorDirSuc = document.getElementById("serrorDirSuc");

                var direccionSucurExtra = document.getElementById("direccionSucurExtra");
                var txtDireccionSucExt = document.getElementById("txtDireccionSucExt");
                var txtCodPostalSucExt = document.getElementById("txtCodPostalSucExt");
                var direccionesSucNacion = document.getElementById("direccionesSucNacion");
                var tHeadSucursal = document.getElementById("tHeadSucursal");
                var txtcalle_regSuc = document.getElementById("txtcalle_regSuc");
                var txtnumext_regSuc = document.getElementById("txtnumext_regSuc");
                var txtnumint_regSuc = document.getElementById("txtnumint_regSuc");
                var txtCodPostalSuc = document.getElementById("txtCodPostalSuc");
                var txtlocalidad_regSuc = document.getElementById("txtlocalidad_regSuc");
                var txtcalle1ref_regSuc = document.getElementById("txtcalle1ref_regSuc");
                var txtcalle2ref_regSuc = document.getElementById("txtcalle2ref_regSuc");
                var txtreferencia_regSuc = document.getElementById("txtreferencia_regSuc");

                $('input[name="direccionfisica"]').click(function(){
                    if (!serrorDirSuc.classList.contains("noneView")){
                        serrorDirSuc.classList.add("noneView");
                    }
                    if (this.value == "mismaDireccion") {
                        if (!direccionesSucNacion.classList.contains("noneView")) {
                            direccionesSucNacion.classList.add("noneView");
                        } 
                        if (!direccionSucurExtra.classList.contains("noneView")) {
                            direccionSucurExtra.classList.add("noneView");
                        }
                    }
                    if (this.value == "otraDireccion") {
                        if (vtipoProv == 'nacional') {
                            direccionSucurExtra.classList.add("noneView");
                            direccionesSucNacion.classList.remove("noneView");
                        }

                        if (vtipoProv == 'extranjero') {
                            direccionesSucNacion.classList.add("noneView");
                            direccionSucurExtra.classList.remove("noneView");
                        }
                    }
                });

                $(txtCodPostalSucExt).keyup(function() {
                    checkPostalExt(this);
                });

                $(txtDireccionSucExt).keyup(function(){
                    checkDireccionExt(this);
                });


                $(txtcalle_regSuc).keyup(function(){
                    if (this.value === '' || !filtroDom.test(this.value)) {
                        errorInput(this,'Inserta calle');
                    } else {
                        correctoInput(this,'Calle');
                    }
                });

                $(txtnumext_regSuc).keyup(function(){
                    if (this.value === '' || !(/^[0-9]+$/.test(this.value))) {
                        errorInput(this,'Inserta Número exteriór');
                    } else {
                        correctoInput(this,'Número exteriór');
                    }
                });

                $(txtnumext_regSuc).keypress(function(e){
                    if (!soloNumeros(event)) {
                        e.preventDefault();
                    }
                });

                $(txtnumint_regSuc).keyup(function(){
                    if (this.value === '' || !(/^[0-9]+$/.test(this.value))) {
                        errorInput(this,'Inserta Número interiór');
                    } else {
                        correctoInput(this,'Número interiór');
                    }
                });

                $(txtnumint_regSuc).keypress(function(e){
                    if (!soloNumeros(event)) {
                        e.preventDefault();
                    }
                });

                $(buscarDirSucursal());
                function buscarDirSucursal(cpostalSucursal) {
                    $.ajax({
                        url: 'egresos-buscadirecsucu',
                        type: 'post',
                        datatype: 'html',
                        data: {cpostalSucursal: cpostalSucursal},
                    })
                    .done(function(respuesta){
                        $("#dataSelectMuniSuc").html(respuesta);
                    })
                    .fail(function(){
                    console.log("error");
                    })
                };

                $(txtCodPostalSuc).keyup(function(){
                    //txtnumint_regSuc
                    checkTelefono(this);
                    var cpostalSucursal = this.value;
                    if (cpostalSucursal != '' && /^[0-9]+$/.test(this.value) && this.value.length == 5) {
                        buscarDirSucursal(cpostalSucursal);
                    } else {
                        buscarDirSucursal(); 
                    }
                });

                $(txtCodPostalSuc).keypress(function(e){
                    if (!soloNumeros(event)) {
                        e.preventDefault();
                    }
                }); 

                $("#tableDireccionSuc").on("click","td input#checkCPSuc",function(){
                    if (tHeadSucursal.classList.contains("btnError")) {
                        tHeadSucursal.classList.remove("btnError");
                    }
                });

                $(txtlocalidad_regSuc).keyup(function(){
                    if (this.value === '' || !filtroDom.test(this.value)) {
                        errorInput(this,'Inserta localidad');
                    } else {
                        correctoInput(this,'Localidad');
                    }
                });

                $(txtcalle1ref_regSuc).keyup(function(){
                    if (this.value === '' || !filtroDom.test(this.value)) {
                        errorInput(this,'Inserta calle de referencia');
                    } else {
                        correctoInput(this,'Entre calle');
                    }
                });

                $(txtcalle2ref_regSuc).keyup(function(){
                    if (this.value === '' || !filtroDom.test(this.value)) {
                        errorInput(this,'Inserta calle de referencia');
                    } else {
                        correctoInput(this,'Y calle');
                    }
                });

                $(txtreferencia_regSuc).keyup(function(){
                    if (this.value === '' || !filtroDom.test(this.value)) {
                        errorInput(this,'Inserta Referencia');
                    } else {
                        correctoInput(this,'Referencia');
                    }
                });

            //boton registro
                var botonRegProvt = document.getElementById("botonRegProvt");
                var btnRegistraProv = document.getElementById("btnRegistraProv");
                $(btnRegistraProv).click(function(){
                    alert(resRfc.innerHTML.length)
                    var subtipoProv = document.querySelector('input[name="subtipoProv"]:checked');  
                    if (vtipoProv == 'nacional') {
                        if (subtipoProv.value == 'provFisica' && resRfc.innerHTML.length == 13) {
                            if ((txtPaternoPF_reg.value != '' && strFilter.test(txtPaternoPF_reg.value) && txtPaternoPF_reg.value.length >= 4) &&  
                                (txtMaternoPF_reg.value != '' && strFilter.test(txtMaternoPF_reg.value) && txtMaternoPF_reg.value.length >= 4) &&  
                                (txtnombrePF_reg.value != '' && strFilter.test(txtnombrePF_reg.value) && txtnombrePF_reg.value.length >= 3) &&  
                                (txtcurpPF_reg.value != '' && (/^[a-zA-Z0-9]+$/.test(txtcurpPF_reg.value)) || txtcurpPF_reg.value.length == 18) && 
                                (txtnomCom_regPF.value != '' && strFilEmp.test(txtnomCom_regPF.value) && txtnomCom_regPF.value.length >= 10) && 
                                (txtsitWebPF_reg.value != '' && filtroUrl.test(txtsitWebPF_reg.value) && txtsitWebPF_reg.value.length >= 10)) {
                                erroresResto();
                                validaDirecciones();
                                if (erroresResto() == 1 && validaDirecciones() == 1) {
                                    provereg();
                                    envio_form();
                                }
                            } else {
                                validaPrincipalesPF();
                                validaDirecciones();
                                erroresResto();
                            }   
                        }

                        if (subtipoProv.value == 'provMoral' && resRfc.innerHTML.length == 12) {
                            if ((txtempresa_reg.value != '' && strFilEmp.test(txtempresa_reg.value)) &&
                                (txtnomCom_regPM.value != '' && strFilEmp.test(txtnomCom_regPM.value) && txtnomCom_regPM.value.length >= 10) && 
                                (txtsitWeb_regPM.value != '' && filtroUrl.test(txtsitWeb_regPM.value) && txtsitWeb_regPM.value.length >= 10) ) {
                                erroresResto();
                                validaDirecciones();
                                if (erroresResto() == 1 && validaDirecciones() == 1) {
                                    provereg();
                                    envio_form();
                                }
                            } else {
                                validaPrincipalesPM();
                                validaDirecciones();
                                erroresResto();
                            }
                        }
                    }
                    
                    if (vtipoProv == 'extranjero') {
                        if (subtipoProv.value == 'provFisica') {
                            if ((txtPaternoPF_reg.value != '' && strFilter.test(txtPaternoPF_reg.value) && txtPaternoPF_reg.value.length >= 4) &&  
                                (txtMaternoPF_reg.value != '' && strFilter.test(txtMaternoPF_reg.value) && txtMaternoPF_reg.value.length >= 4) &&  
                                (txtnombrePF_reg.value != '' && strFilter.test(txtnombrePF_reg.value) && txtnombrePF_reg.value.length >= 3) &&   
                                (txtcurpPF_reg.value != '' && (/^[a-zA-Z0-9]+$/.test(txtcurpPF_reg.value)) && txtcurpPF_reg.value.length <= 40) &&  
                                selPaisExtPF_reg.value != '' && (txtDireccionExt.value != '' && strFilter.test(txtDireccionExt.value)) &&
                                (txtCodPostalExtProv.value != '' && filtroDom.test(txtCodPostalExtProv.value)) &&
                                (txtnomCom_regPF.value != '' && strFilEmp.test(txtnomCom_regPF.value) && txtnomCom_regPF.value.length >= 10) && 
                                (txtsitWebPF_reg.value != '' && filtroUrl.test(txtsitWebPF_reg.value) && txtsitWebPF_reg.value.length >= 10)) {
                                erroresResto();
                                validaDirecciones();
                                if (erroresResto() == 1) {
                                    provereg();
                                    envio_form();
                                } 
                            } else {
                                validaPrincipalesPF();
                                if (selPaisExtPF_reg.value == ''){
                                    checkSelectPais(selPaisExtPF_reg);
                                }
                            
                                if (txtDireccionExt.value == '' || !strFilter.test(txtDireccionExt.value)) {
                                    errorInput(txtDireccionExt,'Inserta dirección');
                                }

                                if (txtCodPostalExtProv.value == '' || !filtroDom.test(txtCodPostalExtProv.value)) {
                                    errorInput(txtCodPostalExtProv,'Inserta código postal');
                                }
                                erroresResto();
                            }
                        }

                        if (subtipoProv.value == 'provMoral') {
                            if ((txtempresa_reg.value != '' && strFilEmp.test(txtempresa_reg.value)) && 
                                (txtidtax_reg.value != '' && (/^[a-zA-Z0-9]+$/.test(txtidtax_reg.value)) && txtidtax_reg.value.length <= 40) && 
                                selPaisExtPM_reg.value != '' && (txtDireccionExt.value != '' && strFilter.test(txtDireccionExt.value)) && 
                                (txtCodPostalExtProv.value != '' && filtroDom.test(txtCodPostalExtProv.value)) &&
                                (txtnomCom_regPM.value != '' && strFilEmp.test(txtnomCom_regPM.value) && txtnomCom_regPM.value.length >= 10) && 
                                (txtsitWeb_regPM.value != '' && filtroUrl.test(txtsitWeb_regPM.value) && txtsitWeb_regPM.value.length >= 10) ) {
                                erroresResto();
                                validaDirecciones();
                                if (erroresResto() == 1) {
                                    provereg();
                                    envio_form();
                                }
                            } else {
                                validaPrincipalesPM();
                                erroresResto();
                            }
                        }
                    }
                });    
                
                function validaPrincipalesPF(){
                    if (txtPaternoPF_reg.value == '' || !strFilter.test(txtPaternoPF_reg.value) || !txtPaternoPF_reg.value.length >= 4){
                        checkPaterno(txtPaternoPF_reg);
                    }
                
                    if (txtMaternoPF_reg.value == '' || !strFilter.test(txtMaternoPF_reg.value) || !txtMaternoPF_reg.value.length >= 4){
                        checkMaterno(txtMaternoPF_reg);
                    }
                
                    if (txtnombrePF_reg.value == '' || !strFilter.test(txtnombrePF_reg.value) || !txtnombrePF_reg.value.length >= 3){
                        checkNombre(txtnombrePF_reg);
                    }
                
                    if (txtnomCom_regPF.value == '' || !strFilEmp.test(txtnomCom_regPF.value) || txtnomCom_regPF.value.length <10){
                        checkNombreCom(txtnomCom_regPF);
                    }
                
                    if (txtsitWebPF_reg.value == '' || !(filtroUrl.test(txtsitWebPF_reg.value)) || txtsitWebPF_reg.value.length <10) {
                        checkSitWeb(txtsitWebPF_reg)
                    }
                
                    if (vtipoProv == 'nacional') {
                        if(txtcurpPF_reg.value == '' || !(/^[a-zA-Z0-9]+$/.test(txtcurpPF_reg.value)) || !txtcurpPF_reg.value.length == 18){
                            errorInput(txtcurpPF_reg,'Inserta CURP/CURP invalido');
                        }
                    } else {
                        if(txtcurpPF_reg.value == '' || !(/^[a-zA-Z0-9]+$/.test(txtcurpPF_reg.value)) || txtcurpPF_reg.value.length > 40){
                            errorInput(txtcurpPF_reg,'Inserta IDTax/IDTax invalido');
                        }
                    }
                }
                
                function validaPrincipalesPM(){
                    if(txtempresa_reg.value == '' || !strFilEmp.test(txtempresa_reg.value) || !txtempresa_reg.value) {
                        errorInput(txtempresa_reg,'Inserta Apellido Materno');
                    }
                
                    if (txtnomCom_regPM.value == '' || !strFilEmp.test(txtnomCom_regPM.value) || txtnomCom_regPM.value.length <10){
                        checkNombreCom(txtnomCom_regPM);
                    }
                
                    if (txtsitWeb_regPM.value == '' || !(filtroUrl.test(txtsitWeb_regPM.value)) || !txtsitWeb_regPM.value.length <10) {
                        checkSitWeb(txtsitWeb_regPM)
                    }
                
                    if (vtipoProv == 'extranjero') {
                        if(txtidtax_reg.value == '' || !(/^[a-zA-Z0-9]+$/.test(txtidtax_reg.value)) || txtidtax_reg.value.length > 40){
                            errorInput(txtidtax_reg,'Inserta IDTax/IDTax invalido');
                        }
                
                        if(selPaisExtPM_reg.value == ''){
                            checkSelectPais(selPaisExtPM_reg);
                        }
                        
                        if (txtDireccionExt.value == '' || !strFilter.test(txtDireccionExt.value)) {
                            errorInput(txtDireccionExt,'Inserta dirección');
                        }
                        
                        if (txtCodPostalExtProv.value == '' || !filtroDom.test(txtCodPostalExtProv.value)) {
                            errorInput(txtCodPostalExtProv,'Inserta código postal');
                        }
                    }
                
                }
                
                function validaDirecciones(){
                    var checkCP = document.querySelector('input[name="checkCP"]:checked');
                    if ((txtcalle_reg.value != '' && filtroDom.test(txtcalle_reg.value)) && 
                        (txtnumext_reg.value != '' && /^[0-9]+$/.test(txtnumext_reg.value)) &&  
                        (checkCP &&  checkCP.value != '' && /^[0-9]+$/.test(checkCP.value)) && 
                        (txtlocalidad_reg.value != '' && filtroDom.test(txtlocalidad_reg.value)) && 
                        (txtcalle1ref_reg.value != '' && filtroDom.test(txtcalle1ref_reg.value)) && 
                        (txtcalle2ref_reg.value != '' && filtroDom.test(txtcalle2ref_reg.value)) && 
                        (txtreferencia_reg.value != '' && filtroDom.test(txtreferencia_reg.value))) {
                        var dir = 1;
                        return dir;
                    } else {
                        if (txtcalle_reg.value == '' || !filtroDom.test(txtcalle_reg.value)) {
                            errorInput(txtcalle_reg,'Inserta calle');
                        } 
                        if (txtnumext_reg.value == '' || !/^[0-9]+$/.test(txtnumext_reg.value)) {
                            errorInput(txtnumext_reg,'Inserta Número exteriór');
                        } 
                        if (!checkCP || checkCP.value == '' || !/^[0-9]+$/.test(checkCP.value)) {
                            tHeadDireccionfiscal.classList.add("btnError");
                        } 
                        if (txtlocalidad_reg.value == '' || !filtroDom.test(txtlocalidad_reg.value)) {
                            errorInput(txtlocalidad_reg,'Inserta localidad')
                        } 
                        if (txtcalle1ref_reg.value == '' || !filtroDom.test(txtcalle1ref_reg.value)) {
                            errorInput(txtcalle1ref_reg,'Inserta calle de referencia');
                        } 
                        if (txtcalle2ref_reg.value == '' || !filtroDom.test(txtcalle2ref_reg.value)) {
                            errorInput(txtcalle2ref_reg,'Inserta calle de referencia');
                        } 
                        if (txtreferencia_reg.value == '' || !filtroDom.test(txtreferencia_reg.value)) {
                            errorInput(txtreferencia_reg,'Inserta Referencia');
                        }
                        return 0;
                    }
                }
                
                function erroresResto(){
                    var aceptaCredito = document.querySelector('input[name="aceptaCredito"]:checked');
                    var direccionfisica = document.querySelector('input[name="direccionfisica"]:checked');
                    if (tbodyDatosConta.childNodes.length > 3 && situacion_fiscal.value != '' && 
                        (situacion_fiscal.value.includes('.pdf') || situacion_fiscal.value.includes('.jpg') || 
                        situacion_fiscal.value.includes('.jpeg') || situacion_fiscal.value.includes('.png')) &&
                        (aceptaCredito &&  aceptaCredito.value != '' && strFilter.test(aceptaCredito.value)) && 
                        (formaPago.value != '' && /^[0-9]+$/.test(formaPago.value)) && 
                        (direccionfisica &&  direccionfisica.value != '' && strFilter.test(direccionfisica.value))) {

                        if(aceptaCredito.value == 'si'){
                            if ((txtMoneda_reg.value != '' && strFilEmp.test(txtMoneda_reg.value) && txtMoneda_reg.value.length >= 5) && 
                                (limiteCredito.value != '' && (/^[0-9$.,]+$/.test(limiteCredito.value)) && limiteCredito.value.length >= 1) && 
                                (txtdiaspagoCredit_reg.value != '' && (/^[0-9]+$/.test(txtdiaspagoCredit_reg.value)) && txtdiaspagoCredit_reg.value.length >= 1) ){ 
                                var vv = 1;
                            } else {
                                if (txtMoneda_reg.value == '' || !strFilEmp.test(txtMoneda_reg.value) || !txtMoneda_reg.value.length >= 5) {
                                    abreHeaderCredito();
                                    errorInput(txtMoneda_reg,'Inserta Moneda');
                                }
                        
                                if (limiteCredito.value == '' || !/^[0-9]+$/.test(limiteCredito.value) || !limiteCredito.value.length >= 1) {
                                    abreHeaderCredito();
                                    errorInput(limiteCredito,'Inserta Límite de credito');
                                }
                        
                                if (txtdiaspagoCredit_reg.value == '' || !/^[0-9]+$/.test(txtdiaspagoCredit_reg.value) || !txtdiaspagoCredit_reg.value.length >= 1) {
                                    abreHeaderCredito();
                                    errorInput(txtdiaspagoCredit_reg,'Inserta Días de pago');
                                }
                            }
                        }
                        if (aceptaCredito.value == 'no') {
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
                
                        if (direccionfisica.value == 'otraDireccion') {
                            if (vtipoProv == 'nacional') {
                                var checkCPSuc = document.querySelector('input[name="checkCPSuc"]:checked');
                                if ((txtcalle_regSuc.value != '' && filtroDom.test(txtcalle_regSuc.value)) && 
                                    (txtnumext_regSuc.value != '' && /^[0-9]+$/.test(txtnumext_regSuc.value)) && 
                                    (checkCPSuc &&  checkCPSuc.value != '' && /^[0-9]+$/.test(checkCPSuc.value)) && 
                                    (txtlocalidad_regSuc.value != '' && filtroDom.test(txtlocalidad_regSuc.value)) && 
                                    (txtcalle1ref_regSuc.value != '' && filtroDom.test(txtcalle1ref_regSuc.value)) && 
                                    (txtcalle2ref_regSuc.value != '' && filtroDom.test(txtcalle2ref_regSuc.value)) && 
                                    (txtreferencia_regSuc.value != '' && filtroDom.test(txtreferencia_regSuc.value))) {
                                    var vv = 1;
                                } else { 
                                    var vv = 0;
                                    if (txtcalle_regSuc.value == '' || !filtroDom.test(txtcalle_regSuc.value)) {
                                        errorInput(txtcalle_regSuc,'Inserta calle');
                                    }
                                    if (txtnumext_regSuc.value == '' || !/^[0-9]+$/.test(txtnumext_regSuc.value)) {
                                        errorInput(txtnumext_regSuc,'Inserta Número exteriór');
                                    } 
                                    if (!checkCPSuc || checkCPSuc.value == '' || !/^[0-9]+$/.test(checkCPSuc.value)) {
                                        tHeadSucursal.classList.add("btnError");
                                    } 
                                    if (txtlocalidad_regSuc.value == '' || !filtroDom.test(txtlocalidad_regSuc.value)) {
                                        errorInput(txtlocalidad_regSuc,'Inserta localidad')
                                    } 
                                    if (txtcalle1ref_regSuc.value == '' || !filtroDom.test(txtcalle1ref_regSuc.value)) {
                                        errorInput(txtcalle1ref_regSuc,'Inserta calle de referencia');
                                    }
                                    if (txtcalle2ref_regSuc.value == '' || !filtroDom.test(txtcalle2ref_regSuc.value)) {
                                        errorInput(txtcalle2ref_regSuc,'Inserta calle de referencia');
                                    } 
                                    if (txtreferencia_regSuc.value == '' || !filtroDom.test(txtreferencia_regSuc.value)) {
                                        errorInput(txtreferencia_regSuc,'Inserta Referencia');
                                    }
                                }  
                            } else {
                                if ((txtDireccionSucExt.value != '' && filtroDom.test(txtDireccionSucExt.value)) &&
                                    (txtCodPostalSucExt.value != '' && filtroDom.test(txtCodPostalSucExt.value))) {
                                    var vv = 1;
                                } else {
                                    var vv = 0;
                                    if (txtDireccionSucExt.value == '' || !filtroDom.test(txtDireccionSucExt.value)) {
                                        errorInput(txtDireccionSucExt,'Inserta dirección');
                                    }
                                    
                                    if (txtCodPostalSucExt.value == '' || !filtroDom.test(txtCodPostalSucExt.value)) {
                                        errorInput(txtCodPostalSucExt,'Inserta código postal');
                                    }
                                }
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
                
                        if (!aceptaCredito || aceptaCredito.value == '' || !strFilter.test(aceptaCredito.value)) {
                            abreHeaderCredito();
                        }
                
                        if (aceptaCredito && aceptaCredito.value == 'si') {
                            if (txtMoneda_reg.value == '' || !strFilEmp.test(txtMoneda_reg.value) || !txtMoneda_reg.value.length >= 5) {
                                abreHeaderCredito();
                                errorInput(txtMoneda_reg,'Inserta Moneda');
                            }
                
                            if (limiteCredito.value == '' || !/^[0-9]+$/.test(limiteCredito.value) || !limiteCredito.value.length >= 1) {
                                abreHeaderCredito();
                                errorInput(limiteCredito,'Inserta Límite de credito');
                            }
                
                            if (txtdiaspagoCredit_reg.value == '' || !/^[0-9]+$/.test(txtdiaspagoCredit_reg.value) || !txtdiaspagoCredit_reg.value.length >= 1) {
                                abreHeaderCredito();
                                errorInput(txtdiaspagoCredit_reg,'Inserta Días de pago');
                            }
                        }
                
                        if (formaPago.value == '' || !/^[0-9]+$/.test(formaPago.value)) {
                            abreHeaderFpago();
                            errorSelect(formaPago,'Inserta Apellido Materno');
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
                
                        if (!direccionfisica || direccionfisica.value == '' || !strFilter.test(direccionfisica.value)) {
                            abreHeaderCreditoSuc();
                        }
                
                        if (direccionfisica && direccionfisica.value == 'otraDireccion') {
                            if (vtipoProv == 'nacional') {
                                if (txtcalle_regSuc.value == '' || !filtroDom.test(txtcalle_regSuc.value)) {
                                    errorInput(txtcalle_regSuc,'Inserta calle');
                                } 
                
                                if (txtnumext_regSuc.value == '' || !/^[0-9]+$/.test(txtnumext_regSuc.value)) {
                                    errorInput(txtnumext_regSuc,'Inserta Número exteriór');
                                } 
                
                                if (!checkCPSuc || checkCPSuc.value == '' || !/^[0-9]+$/.test(checkCPSuc.value)) {
                                    tHeadSucursal.classList.add("btnError");
                                } 
                
                                if (txtlocalidad_regSuc.value == '' || !filtroDom.test(txtlocalidad_regSuc.value)) {
                                    errorInput(txtlocalidad_regSuc,'Inserta localidad')
                                } 
                
                                if (txtcalle1ref_regSuc.value == '' || !filtroDom.test(txtcalle1ref_regSuc.value)) {
                                    errorInput(txtcalle1ref_regSuc,'Inserta calle de referencia');
                                } 
                
                                if (txtcalle2ref_regSuc.value == '' || !filtroDom.test(txtcalle2ref_regSuc.value)) {
                                    errorInput(txtcalle2ref_regSuc,'Inserta calle de referencia');
                                } 
                
                                if (txtreferencia_regSuc.value == '' || !filtroDom.test(txtreferencia_regSuc.value)) {
                                    errorInput(txtreferencia_regSuc,'Inserta Referencia');
                                }
                                
                            } else {
                                if (txtDireccionSucExt.value == '' || !filtroDom.test(txtDireccionSucExt.value)) {
                                    errorInput(txtDireccionSucExt,'Inserta dirección');
                                }
                                
                                if (txtCodPostalSucExt.value == '' || !filtroDom.test(txtCodPostalSucExt.value)) {
                                    errorInput(txtCodPostalSucExt,'Inserta código postal');
                                }
                            }
                        }
                        return 0;
                    }
                }
                
                function envio_form() {
                    var mensajeSaveClient = confirm("¿Desea registrar este cliente?");
                    if (mensajeSaveClient) {
                        var partData = $(this).closest('frmAddClient').serialize();
                        var radioProv = document.querySelector('input[name="tipoProv"]:checked');
                        var subtipoProv = document.querySelector('input[name="subtipoProv"]:checked'); 
                        var filesituacion_fiscal = $(situacion_fiscal)[0].files[0];
                        var checkCP = document.querySelector('input[name="checkCP"]:checked');
                        var fileestCuenta = $(estado_cuenta)[0].files[0];
                        var aceptaCredito = document.querySelector('input[name="aceptaCredito"]:checked');
                        var direccionfisica= document.querySelector('input[name="direccionfisica"]:checked');
                        var checkCPSuc = document.querySelector('input[name="checkCPSuc"]:checked');
                        var data = new FormData();
                        data.append('data',partData);
                        data.append('rfc-registro-pf',varriablleRfc);
                        data.append('radioProv',radioProv.value);
                        data.append('subtipoProv',subtipoProv.value);
                        data.append('txtPaternoPF',txtPaternoPF_reg.value);
                        data.append('txtMaternoPF',txtMaternoPF_reg.value);
                        data.append('txtnombrePF',txtnombrePF_reg.value);
                        data.append('txtcurpPF',txtcurpPF_reg.value);
                        data.append('paisPF',selPaisExtPF_reg.value);
                        data.append('txtNomComercialPF',txtnomCom_regPF.value);
                        data.append('txtSitioWebPF',txtsitWebPF_reg.value);
                        data.append('redesSocialesPF',JSON.stringify(arrayRedesPF));
                        data.append('txtempresa',txtempresa_reg.value);
                        data.append('idtax',txtidtax_reg.value);
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
                        data.append('txtCodPostalExtProv',txtCodPostalExtProv.value);
                        data.append('direccionExt',txtDireccionExt.value);
                        data.append('txtcalle',txtcalle_reg.value);
                        data.append('txtnumext',txtnumext_reg.value);
                        data.append('txtnumint',txtnumint_reg.value);
                        if (checkCP) {
                            data.append('checkCP',checkCP.value);
                        }
                        data.append('txtlocalidad',txtlocalidad_reg.value);
                        data.append('txtcalle1ref',txtcalle1ref_reg.value);
                        data.append('txtcalle2ref',txtcalle2ref_reg.value);
                        data.append('txtreferencia',txtreferencia_reg.value);
                        data.append('aceptaCredito',aceptaCredito.value);
                        data.append('txtMoneda',txtMoneda_reg.value);
                        data.append('txtlimiteCredito',limiteCredito.value);
                        data.append('txtdiaspagoCredit',txtdiaspagoCredit_reg.value);
                        data.append('formaPago',formaPago.value);
                        data.append('txtClabeIntBanc',clabeIntBanc.value);
                        data.append('est_cuenta',fileestCuenta);
                        data.append('direccionfisica',direccionfisica.value);
                        data.append('txtCodPostalSucExt',txtCodPostalSucExt.value);
                        data.append('direccionSucExt',txtDireccionSucExt.value);
                        data.append('txtcalleSuc',txtcalle_regSuc.value);
                        data.append('txtnumextSuc',txtnumext_regSuc.value);
                        data.append('txtnumintSuc',txtnumint_regSuc.value);
                        if (checkCPSuc) {
                            data.append('checkCPSuc',checkCPSuc.value);
                        }
                        data.append('txtlocalidadSuc',txtlocalidad_regSuc.value);
                        data.append('txtcalle1refSuc',txtcalle1ref_regSuc.value);
                        data.append('txtcalle2refSuc',txtcalle2ref_regSuc.value);
                        data.append('txtreferenciaSuc',txtreferencia_regSuc.value);
                
                        $.ajax({
                            url: 'egresos-registraproveedor',
                            type: "post",
                            data: data,
                            datatype: 'json',
                            processData: false,
                            contentType: false,
                            success: function(respuesta) {
                                alert(respuesta);
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
                                    checkPostalExt(txtCodPostalExtProv);
                                }

                                if(respuesta == 'errorDireccionExt'){
                                    toastError('Inserta dirección');
                                    checkDireccionExt(txtDireccionExt);
                                }

                                if(respuesta == 'errorCodPostalSucExt'){
                                    toastError('Inserta código postal');
                                    checkPostalExt(txtCodPostalSucExt);
                                }
                                if(respuesta == 'errorDirSucExt'){
                                    toastError('Inserta dirección');
                                    checkDireccionExt(txtDireccionSucExt);
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
                
                                if(respuesta == 'errorPaternoCont'){
                                    toastError('Apellido Paterno de contacto invalido');   
                                    abreHeaderCont();
                                    errorInput(txtPaternoCont_reg,'Apellido Paterno Invalido');
                                }
                            
                                if(respuesta == 'errorMaternoCont'){
                                    toastError('Apellido Materno de contacto invalido');    
                                    abreHeaderCont();
                                    errorInput(txtMaternoCont_reg,'Apellido Materno invalido');
                                }
                                if(respuesta == 'errorNombreCont'){
                                    toastError('Nombre de contacto invalido');    
                                    abreHeaderCont();
                                    errorInput(txtNombreCont_reg,'Nombre invalido');
                                }
                
                                if(respuesta == 'errorArea'){
                                    toastError('Area invalida');    
                                    abreHeaderCont();
                                    errorInput(txtAreaCont_reg,'Area invalida');
                                }
                
                                if(respuesta == 'errorCargo'){
                                    toastError('Cargo invalido');    
                                    abreHeaderCont();
                                    errorInput(txtCargoCont_reg,'Cargo invalido');
                                }
                                if(respuesta == 'errorEmailCont'){
                                    toastError('Email de contacto invalido');    
                                    abreHeaderCont();
                                    errorInput(txtEmailCont_reg,'Email invalido');
                                }
                                if(respuesta == 'errorTelefonoCont'){
                                    toastError('Teléfono de contacto invalido');
                                    abreHeaderCont();
                                    errorInput(txtTelefonoCont_reg,'Teléfono invalido');
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
                
                                if(respuesta == 'errorCalle'){
                                    toastError('Calle invalida');
                                    errorInput(txtcalle_reg,'Inserta calle');
                                }
                                if(respuesta == 'errorNumExt'){
                                    toastError('Número exteriór invalido');
                                    errorInput(txtnumext_reg,'Inserta Número exteriór');
                                }
                
                                if(respuesta == 'errorNumInt'){
                                    toastError('Número interiór invalido');
                                    errorInput(txtnumext_reg,'Inserta Número exteriór');
                                }
                
                                if(respuesta == 'errorCheckCP'){
                                    toastError('Selecciona Código postal');
                                    tHeadDireccionfiscal.classList.add("btnError");
                                }
                                if(respuesta == 'errorLocalidad'){
                                    toastError('Localidad invalida');
                                    errorInput(txtlocalidad_reg,'Inserta localidad')
                                }
                
                                if(respuesta == 'errorEntre1'){
                                    toastError('Calle de referencia invalida');
                                    errorInput(txtcalle1ref_reg,'Inserta calle de referencia');
                                }
                
                                if(respuesta == 'errorEntre2'){
                                    toastError('Calle de referencia invalida');
                                    errorInput(txtcalle2ref_reg,'Inserta calle de referencia');
                                }     
                
                                if(respuesta == 'errorReferencia'){
                                    toastError('Punto de referencia invalido');
                                    errorInput(txtreferencia_reg,'Inserta Referencia');
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
                                if(respuesta == 'errorDirSucExt'){
                                    toastError('Diracción invalida');
                                    errorInput(txtDireccionSucExt,'Inserta dirección');
                                }
                
                                if(respuesta == 'errorCalleSuc'){
                                    toastError('Calle invalida');
                                    errorInput(txtcalle_regSuc,'Inserta dirección');
                                }
                
                                if(respuesta == 'errorNumExtSuc'){
                                    toastError('Número exteriór invalido');
                                    errorInput(txtnumext_regSuc,'Inserta dirección');
                                }
                
                                if(respuesta == 'errorNumIntSuc'){
                                    toastError('Número interiór invalido');
                                    errorInput(txtnumint_regSuc,'Inserta dirección');
                                }
                
                                if(respuesta == 'errorCheckCPSuc'){
                                    toastError('Selecciona Código postal');
                                    tHeadDireccionfiscal.classList.add("btnError");
                                }
                                if(respuesta == 'errorLocalidadSuc'){
                                    toastError('Localidad invalida');
                                    errorInput(txtlocalidad_regSuc,'Inserta dirección');
                                }
                
                                if(respuesta == 'errorEntre1Suc'){
                                    toastError('Calle de referencia invalida');
                                    errorInput(txtcalle1ref_regSuc,'Inserta dirección');
                                }
                
                                if(respuesta == 'errorEntre2Suc'){
                                    toastError('Calle de referencia invalida');
                                    errorInput(txtcalle2ref_regSuc,'Inserta dirección');
                                }
                
                                if(respuesta == 'errorReferenciaSuc'){
                                    toastError('Punto de referencia invalido');
                                    errorInput(txtreferencia_regSuc,'Inserta dirección');
                                }

                                if (respuesta == 'ProvSaved') {
                                    var $toastContent = $('<div class="btnCorrecto">¡PROVEEDOR REGISTRADO EXITOSAMENTE!</div>');
                                    Materialize.toast($toastContent,5000);
                                    location.reload();
                                }

                                if(respuesta == 'ProvNoSaved'){
                                    toastError('¡PROVEEDOR NO REGISTRADO, VERIFIQUE SU INFORMACIÓN!');
                                }
                            }
                        });
                    }
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

                function abreHeaderCreditoSuc(){
                    serrorDirSuc.classList.remove("noneView");
                    lidirsucursal.classList.remove("noneView");
                    collapsible_headerDirSuc.classList.add("active");
                    collapsible_bodyDirSuc.classList.add("activeViewError");
                }
                
                function abreHeaderFpago(){
                    lidataformapago.classList.remove("noneView");
                    collapsible_headerfPago.classList.add("active");
                    collapsible_bodyfPago.classList.add("activeViewError");                    
                }

                function checkCurpTax(valor){
                    var tipoProv = document.querySelector('input[name="tipoProv"]:checked');
                    if (tipoProv.value == 'nacional') {
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
                    if (valor.value == '') {
                        errorSelect(valor,'Selecciona un pais');
                    } else {
                        correctoSelect(valor,'Pais');
                    }
                }

                function checkPaterno(valor){
                    if (valor.value === '') {
                        errorInput(valor,'Inserta Apellido Paterno');
                    } else {
                        if (!strFilter.test(valor.value)) {
                            errorInput(valor,'Apellido Paterno invalido');
                        } else {
                            if (valor.value.length <4) {
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
                            if (valor.value.length <4) {
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
                            if (valor.value.length <3) {
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
                            if (valor.value.length <20) {
                                errorInput(valor,'Número de caracteres invalido');
                            } else {
                                correctoInput(valor,'Email');
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
                            if (valor.value.length <5) {
                                errorInput(valor,'Número de caracteres invalido');
                            } else {
                                correctoInput(valor,'Teléfono'); 
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
                    if (valor.value === '') {
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
                        errorInput(valor,'Inserta código postal');
                    } else {
                        correctoInput(valor,'Código postal');
                    }
                }

                function checkDireccionExt(valor){
                    if (valor.value === '' || !filtroDom.test(valor.value)) {
                        errorInput(valor,'Inserta dirección');
                    } else {
                        correctoInput(valor,'Calle');
                    }
                }

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
                                        let imgJpg = '<img class="circle responsive-img " src="'+reader.result+'">';
                                        destino.innerHTML = imgJpg;
                                    break;
                                    case "image/png":
                                        let imgPng = '<img class="circle responsive-img " src="'+reader.result+'">';
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
                                destino.classList.remove("noneView");
                                carga.classList.add("noneView");
                            }
                        } else {
                            boton.classList.add("btnError");
                            destino.classList.add("noneView");
                            carga.classList.add("noneView");
                            if (media.matches) {
                                alert('La imagen no debe superar los 2MB');
                                valor = '';
                                //this.files[0].name = '';
                            } else {
                                Pesado();
                                valor = '';
                                //this.files[0].name = '';      
                            }
                        }
                    } else {
                        boton.classList.add("btnError");
                        destino.classList.add("noneView");
                        carga.classList.add("noneView");
                        if (media.matches) {
                            alert('El archivo debe estar en formato .jpg, .png ó .pdf');
                            valor = '';
                            //this.files[0].name = '';
                        } else {
                            error_ext();
                            valor = '';
                            //this.files[0].name = '';                
                        }
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
    Push.create("PROVEEDOR REGISTRADO", {
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