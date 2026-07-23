$(document).ready(function(){
    //funciones
        function addlblDisabled(input){
            var label = $(input).parent(".input-field").find("label");
            label.addClass("disabled");
        }

        function quitalblDisabled(input){
            var label = $(input).parent(".input-field").find("label");
            label.removeClass("disabled");
        }

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

        function addlblDisabledSelect(input){
            var label = $(input).parents(".input-field").find("label");
            label.addClass("disabled");
        }

        function quitalblDisabledSelect(input){
            var label = $(input).parents(".input-field").find("label");
            label.removeClass("disabled");
        }

        function soloNumeros(e){
            var key = e.charCode;
            console.log(key);
            return key >= 48 && key <= 57;
        };

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
                        if (splitCorreo[0].length <= 64 && splitCorreo[1].length <= 255 && 
                            (correo.value.includes('gmail.com') || correo.value.includes('hotmail.com') || 
                            correo.value.includes('outlook.com') || correo.value.includes('yahoo.com')) ) {
                            correctoInput(valor,'Email');
                        } else {
                            errorInput(valor,'Número de caracteres invalido');
                        }
                    }
                }
            }
        }

        function logoImg(e,valor,boton){
            //objeto de la clase FileReader
            let reader = new FileReader();
            reader.readAsDataURL(e.target.files[0]);
            var typeElemento = e.target.files[0].type;
            var tamano = e.target.files[0].size;
            if (typeElemento == "image/jpeg" || typeElemento == "image/png") {
                if (tamano < 2000000) {
                    reader.onload = function(){
                        switch(typeElemento){
                            case "image/jpeg":
                                let imgJpg = '<img class="circle responsive-img " src="'+reader.result+'">';
                                boton.innerHTML = imgJpg;
                            break;
                            case "image/png":
                                let imgPng = '<img class="circle responsive-img " src="'+reader.result+'">';
                                boton.innerHTML = imgPng;
                            break;
                        }
                    }
                } else {
                    boton.classList.add("btnError");
                    if (media.matches) {
                        alert('La imagen no debe superar los 2MB');
                        valor = '';
                    } else {
                        Pesado();
                        valor = '';
                    }
                }
            } else {
                boton.classList.add("btnError");
                if (media.matches) {
                    alert('El archivo debe estar en formato .jpg ó .png');
                    valor = '';
                } else {
                    Error_ext();
                    valor = '';
                }
            }
        }

        function toastError(texto){
            var $toastContent = $('<div class="btnError">'+texto+'</div>');
            Materialize.toast($toastContent,5000);    
        }

    //filtros
        var media = window.matchMedia("(max-width: 400px)");
        var strFilter = /^[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,:-]*$/;
        var filtroClave = /^[A-Za-z0-9ƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ-]*$/;
        var filtroLetras = /^[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ]*$/;
        var correoRegex = /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
        var filtroNum = /^[0-9]*$/;
        var filtroPorc= /^[0-9.%]*$/;

    //alta de registro
        //validaciones
            //logoEmpresa
                var btnAddLogoEmpresa = document.getElementById("btnAddLogoEmpresa");
                var spanLogoEmpresa = document.getElementById("spanLogoEmpresa");
                var logo = document.getElementById("logo_empresa");

                $(logo).change(function(e){
                    var valor = this.value;
                    var boton = btnAddLogoEmpresa;
                    logoImg(e,valor,boton);
                });

            //regimen Fiscal
                var tipoPersona = document.getElementById("tipoPersona");
                var valTipoPersona = $(tipoPersona).html();
                var selectRegimen = document.getElementById("selectRegimen");
                var txtporcentajeRegfiscal = document.getElementById("txtporcentajeRegfiscal");
                var addRegimenList = document.getElementById("addRegimenList");

                var tablaRegFiscal = document.getElementById("tablaRegFiscal"); 
                var tHeadTablaRegFiscal = document.getElementById("tHeadTablaRegFiscal"); 
                var bodyTablaRegFiscal = document.getElementById("bodyTablaRegFiscal"); 
                var trCamposVaciosFiscal = document.getElementById("trCamposVaciosFiscal");
                var arrayRegimen = []; 
                var arrayPorcRegimen = [];

                $(selectRegimen).change(function(){
                    if (this.value != '') {
                        correctoSelect(this,"Regimen fiscal");
                        if (valTipoPersona == 'persona física') {
                            $(txtporcentajeRegfiscal).removeAttr("disabled");
                            quitalblDisabled(txtporcentajeRegfiscal);
                        }
                    } else {
                        errorSelect(this,"Selecciona regimen fiscal");
                    }
                });

                $(txtporcentajeRegfiscal).keyup(function(){
                    if (this.value == '' || !filtroPorc.test(this.value)) {
                        errorInput(txtporcentajeRegfiscal,"Ingresa porcentaje");
                        $(addRegimenList).attr("disabled",true);
                    } else {
                        this.classList.remove("error");
                        $(addRegimenList).removeAttr('disabled');
                        correctoInput(txtporcentajeRegfiscal,"Porcentaje");
                    }
                });

                $(txtporcentajeRegfiscal).change(function(){
                    if (this.value == '' || !filtroPorc.test(this.value)) {
                        this.classList.add("error");
                    } else {
                        this.classList.remove("error");
                        this.value = this.value+"%";
                    }
                });
        
                $(txtporcentajeRegfiscal).keypress(function(e){
                    if (!soloNumeros(event)) {
                        e.preventDefault();
                    }
                });

                $(addRegimenList).click(function(){
                    if (selectRegimen.value != '' && txtporcentajeRegfiscal.value != '' && filtroPorc.test(txtporcentajeRegfiscal.value)) {
                        $(tHeadTablaRegFiscal).removeClass("btnError");
                        var splitRegimen = selectRegimen.value.split("/");
                        trCamposVaciosFiscal.classList.add("noneView");
                        arrayRegimen.push(splitRegimen[0]);
                        arrayPorcRegimen.push(txtporcentajeRegfiscal.value);
                        var trNuevo = document.createElement("tr");
                        var datos = '<td class="noneView">'+splitRegimen[0]+'</td><td>'+splitRegimen[1]+'</td>'+
                        '<td>'+txtporcentajeRegfiscal.value+'</td>'+
                        '<td class="ultimo"><a class="btn waves-effect waves-light red darken-2" id="deleteRegFiscal">&#xf1f8;</a></td>';
                        trNuevo.innerHTML = datos;
                        bodyTablaRegFiscal.appendChild(trNuevo);

                        selectRegimen.selectedIndex = 0;
                        selectRegimen.value = '';
                        $('#selectRegimen').prop('readonly', false);
                        $('select').material_select();

                        txtporcentajeRegfiscal.value = '';
                    } else {
                        if (selectRegimen.value == '') {
                            errorSelect(selectRegimen,"Selecciona regimen fiscal");
                        }

                        if (txtporcentajeRegfiscal.value == '' || filtroPorc.test(txtporcentajeRegfiscal.value)) {
                            errorInput(txtporcentajeRegfiscal,"Ingresa porcentaje");
                        }
                    }
                });

                $(tablaRegFiscal).on("click", "td a#deleteRegFiscal",function(){
                    var mensaje = confirm("¿Deseas eliminar este registro?");
                    var arregloPos = bodyTablaRegFiscal.childNodes.length -4;
                    var trElimina = $(this).parent("td").parent("tr");
                    if (mensaje) {
                        if (bodyTablaRegFiscal.childNodes.length == 4) {
                            trCamposVaciosFiscal.classList.remove("noneView");
                            trElimina.remove();
                            //tHeadTablaTelMail.classList.add("btnError");
                        } else {
                            trElimina.remove();
                        }
                    }
                    arrayRegimen.splice(arregloPos,1);
                    arrayPorcRegimen.splice(arregloPos,1);
                });

            //logoPerfil
                var btnAddFotoPerfil = document.getElementById("btnAddFotoPerfil");
                var spanImgPerfil = document.getElementById("spanImgPerfil");
                var perfil = document.getElementById("foto_perfil");

                $(perfil).change(function(e){
                    var valor = this.value;
                    var boton = btnAddFotoPerfil;
                    logoImg(e,valor,boton);
                });

            //apellido paterno
                var paterno = document.getElementById("txtpaterno");

                $(paterno).keyup(function(){
                    if (this.value == '' || !filtroLetras.test(this.value) || this.value.length < 3) {
                        errorInput(this,"Apellido paterno invalido");
                    } else {
                        correctoInput(this,"Apellido paterno");
                    }
                });

                $(paterno).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!filtroLetras.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });
            
            //apellido materno
                var materno = document.getElementById("txtmaterno");

                $(materno).keyup(function(){
                    if (this.value == '' || !filtroLetras.test(this.value) || this.value.length < 3) {
                        errorInput(this,"Apellido materno invalido");
                    } else {
                        correctoInput(this,"Apellido materno");
                    }
                });

                $(materno).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!filtroLetras.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

            //nombre(s)
                var nombres = document.getElementById("txtnombres");

                $(nombres).keyup(function(){
                    if (this.value == '' || !filtroLetras.test(this.value) || this.value.length < 3) {
                        errorInput(this,"Nombre(s) invalido");
                    } else {
                        correctoInput(this,"Nombre(s)");
                    }
                });

                $(nombres).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!filtroLetras.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

            //area
                var area = document.getElementById("txtarea");

                $(area).keyup(function(){
                    if (this.value == '' || !filtroLetras.test(this.value)) {
                        errorInput(this,"Área invalida");
                    } else {
                        correctoInput(this,"Área");
                    }
                });

                $(area).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!filtroLetras.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

            //cargo
                var cargo = document.getElementById("txtcargo");

                $(cargo).keyup(function(){
                    if (this.value == '' || !filtroLetras.test(this.value)) {
                        errorInput(this,"Cargo invalido");
                    } else {
                        correctoInput(this,"Cargo");
                    }
                });

                $(cargo).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!filtroLetras.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

            //telefono/extension
                var telefono = document.getElementById("txttelefono");
                
                var extension = document.getElementById("txtExtension");
                var padreExt = extension.parentElement;
                var padreTel = telefono.parentElement;

                $('input[name="telefonocelular"]').click(function(){
                    $("#celular").parent("label").removeClass("errorlabel");
                    $("#corporativo").parent("label").removeClass("errorlabel");
                    if (this.value == 'celular') {
                        $(padreExt).addClass("noneView");
                        $(padreTel).removeClass("telefono");
                    }
                    if (this.value == 'corporativo') {
                        $(padreTel).addClass("telefono");
                        $(padreExt).removeClass("noneView");
                    }
                });

                $(telefono).keyup(function(){
                    if (this.value == '' || this.value == '0000000000' || !filtroNum.test(this.value) || this.value.length != 10) {
                        errorInput(this,"Teléfono invalido");
                    } else {
                        correctoInput(this,"Teléfono");
                    }
                });

                $(telefono).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!filtroNum.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

                $(extension).keyup(function(){
                    if (this.value == '' || this.value == '0' || !filtroNum.test(this.value)) {
                        errorInput(this,"Extensión invalida");
                    } else {
                        correctoInput(this,"Extensión");
                    }
                });

                $(extension).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!filtroNum.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });
            
            //correo
                var correo = document.getElementById("txtemail");

                $(correo).keyup(function(){
                    checkMail(this);
                });
        
            //agregar telefono y correo
                var addTelMail = document.getElementById("addTelMail");

                //tabla 
                    var tablaTelMail = document.getElementById("tablaTelMail");
                    var tHeadTablaTelMail = document.getElementById("tHeadTablaTelMail");
                    var bodyTablaTelMail = document.getElementById("bodyTablaTelMail");
                    var trCamposVacios = document.getElementById("trCamposVacios");
                    var deleteTelMail = document.getElementById("deleteTelMail");
                    var arrayTelefono = []; 
                    var arrayExtension = [];
                    var arrayCorreo = [];

                    window.addEventListener('load', cargando,false);
                    function cargando(){
                        var telefono1 = $(bodyTablaTelMail).find("tr").eq(1).find("td").eq(0).html();
                        var extension1 = $(bodyTablaTelMail).find("tr").eq(1).find("td").eq(1).html();
                        var correo1 = $(bodyTablaTelMail).find("tr").eq(1).find("td").eq(2).html();
                        arrayTelefono.push(telefono1);
                        arrayExtension.push(extension1);
                        arrayCorreo.push(correo1);
                    }

                    $(addTelMail).click(function(){
                        var tipoTelefono = document.querySelector('input[name="telefonocelular"]:checked');
                        if (tipoTelefono && tipoTelefono.value != '' && 
                            (telefono.value != '' && filtroNum.test(telefono.value) && telefono.value.length == 10) &&
                            (correo.value !='' && correoRegex.test(correo.value) &&
                            (correo.value.includes('gmail.com') || correo.value.includes('hotmail.com') || 
                            correo.value.includes('outlook.com') || correo.value.includes('yahoo.com')))) {
                            if (tipoTelefono.value == 'celular') {
                                llenaTelefono(telefono.value,"-",correo.value);
                                telefono.value = '';
                                correo.value = '';
                            }

                            if (tipoTelefono.value == 'corporativo') {
                                if (extension.value != '' && extension.value != '0' && filtroNum.test(extension.value) && extension.value.length >= 1) {
                                    llenaTelefono(telefono.value,extension.value,correo.value);
                                    telefono.value = '';
                                    extension.value = '';
                                    correo.value = '';
                                } else {
                                    if (extension.value == '' || extension.value == '0' || !filtroNum.test(extension.value) || extension.value.length == 0){
                                        errorInput(extension,'Inserta Extensión');
                                    }
                                }
                            }
                        } else {
                            if (!tipoTelefono || tipoTelefono.value == '') {
                                $("#celular").parent("label").addClass("errorlabel");
                                $("#corporativo").parent("label").addClass("errorlabel");
                            }

                            if ((telefono.value == '' || !filtroNum.test(telefono.value) || telefono.value.length != 10)) {
                                errorInput(telefono,"Teléfono invalido");
                            }

                            if (correo.value =='' || !correoRegex.test(correo.value) ||
                                !(correo.value.includes('gmail.com') || correo.value.includes('hotmail.com') || 
                                correo.value.includes('outlook.com') || correo.value.includes('yahoo.com'))) {
                                errorInput(correo,"Correo invalido");
                            }
                        }
                    });

                    function llenaTelefono(telefono,extension,correo){
                        arrayTelefono.push(telefono);//agrega valores al array
                        arrayExtension.push(extension);
                        arrayCorreo.push(correo);

                        tHeadTablaTelMail.classList.remove("btnError");
                        trCamposVacios.classList.add("noneView");
                        var newReg = document.createElement("tr");
                        var datosNuevos = '<td>'+telefono+'</td><td>'+extension+'</td><td>'+correo+'</td>'+
                        '<td><a class="btn waves-effect waves-light red darken-2" id="deleteTelMail">&#xf1f8;</a></td>';
                        newReg.innerHTML = datosNuevos;
                        bodyTablaTelMail.appendChild(newReg);
                    } 

                    $(tablaTelMail).on("click", "td a#deleteTelMail",function(){
                        var mensaje = confirm("¿Deseas eliminar este registro?");
                        var arregloPos = bodyTablaTelMail.childNodes.length -5;
                        var trElimina = $(this).parent("td").parent("tr");
                        if (mensaje) {
                            if (bodyTablaTelMail.childNodes.length == 5) {
                                trCamposVacios.classList.remove("noneView");
                                trElimina.remove();
                                //tHeadTablaTelMail.classList.add("btnError");
                            } else {
                                trElimina.remove();
                            }
                        }
                        arrayTelefono.splice(arregloPos,1);
                        arrayExtension.splice(arregloPos,1);
                        arrayCorreo.splice(arregloPos,1);
                    });

            //registrar
                var guardaRegistro = document.getElementById("guardaRegistro");
            
                $(guardaRegistro).click(function(){
                    alert(bodyTablaTelMail.childNodes.length)
                    if (logo.value != '' && perfil.value != '' &&
                        (paterno.value != '' && filtroLetras.test(paterno.value) && paterno.value.length >= 3) &&
                        (materno.value != '' && filtroLetras.test(materno.value) && materno.value.length >= 3) &&
                        (nombres.value != '' && filtroLetras.test(nombres.value) && nombres.value.length >= 3) &&
                        (area.value != '' && filtroLetras.test(area.value)) &&
                        (cargo.value != '' && filtroLetras.test(cargo.value)) &&
                        (bodyTablaTelMail.childNodes.length >= 4)) {
                        if (valTipoPersona == 'persona física') {
                            if (bodyTablaRegFiscal.childNodes.length >= 4) {
                                var partData = $(this).closest('formRegistro').serialize();
                                var filelogo_empresa = $("#logo_empresa")[0].files[0];
                                var filefoto_perfil = $("#foto_perfil")[0].files[0];
                                var data = new FormData();
                                data.append('data',partData);
                                data.append('logo',filelogo_empresa);
                                data.append('valTipoPersona',valTipoPersona);
                                data.append('regimen',JSON.stringify(arrayRegimen));
                                data.append('porcentaje',JSON.stringify(arrayPorcRegimen));
                                data.append('perfil',filefoto_perfil);
                                data.append('paterno',paterno.value);
                                data.append('materno',materno.value);
                                data.append('nombres',nombres.value);
                                data.append('area',area.value);
                                data.append('cargo',cargo.value);
                                data.append('telefono',JSON.stringify(arrayTelefono));
                                data.append('extension',JSON.stringify(arrayExtension));
                                data.append('correo',JSON.stringify(arrayCorreo));
                                validaRegistro(data);
                            } else {
                                $(tHeadTablaRegFiscal).addClass("btnError");
                            }
                        } else {
                            if (selectRegimen.value != '') {
                                var partData = $(this).closest('formRegistro').serialize();
                                var filelogo_empresa = $("#logo_empresa")[0].files[0];
                                var filefoto_perfil = $("#foto_perfil")[0].files[0];
                                var data = new FormData();
                                data.append('data',partData);
                                data.append('logo',filelogo_empresa);
                                data.append('valTipoPersona',valTipoPersona);
                                data.append('regimen',selectRegimen.value);
                                data.append('perfil',filefoto_perfil);
                                data.append('paterno',paterno.value);
                                data.append('materno',materno.value);
                                data.append('nombres',nombres.value);
                                data.append('area',area.value);
                                data.append('cargo',cargo.value);
                                data.append('telefono',JSON.stringify(arrayTelefono));
                                data.append('extension',JSON.stringify(arrayExtension));
                                data.append('correo',JSON.stringify(arrayCorreo));
                                validaRegistro(data);
                            } else {
                                    errorSelect(selectRegimen,"Selecciona regimen fiscal");
                            }
                        }
                    } else {
                        Push.create("Completa todos los campos", {
                            body: "SOS-México",
                            icon: "vista/media/adm/errores/logoSOS.png",
                            timeout: 3000,
                        });

                        if (logo.value == '') {
                            btnAddLogoEmpresa.classList.add("btnError");
                            spanLogoEmpresa.classList.add("btnspan");
                        }

                        if (perfil.value == '') {
                            btnAddFotoPerfil.classList.add("btnError");
                            spanImgPerfil.classList.add("btnspan");
                        }

                        if (paterno.value == '' || !filtroLetras.test(paterno.value) || paterno.value.length < 3) {
                            errorInput(paterno,"Apellido paterno invalido");
                        }

                        if (materno.value == '' || !filtroLetras.test(materno.value) || materno.value.length < 3) {
                            errorInput(materno,"Apellido materno invalido");
                        }

                        if (nombres.value == '' || !filtroLetras.test(nombres.value) || nombres.value.length < 3) {
                            errorInput(nombres,"Nombre invalido");
                        }

                        if (area.value == '' || !filtroLetras.test(area.value)) {
                            errorInput(area,"Área invalida");
                        }

                        if (cargo.value == '' || !filtroLetras.test(cargo.value)) {
                            errorInput(cargo,"Cargo invalido");
                        }

                        if(bodyTablaTelMail.childNodes.length == 4){
                            tHeadTablaTelMail.classList.add("btnError");
                        }
                    }
                });
            
                function validaRegistro(data){
                    $.ajax({
                        url: "clientessos-terminaregistro",
                        type: "post",
                        data: data,
                        dataType: 'html',
                        processData: false,
                        contentType: false,
                        success: function(response){
                            var resarray = response.substring(response.length-1,response.length);
                            if (response == 'registroTerminado') {
                                Materialize.toast('Registro completado exitosamente',3000);
                                window.location = "admin"; 
                            }

                            if (response == 'errorExistLogo') {
                                toastError('Logotipo invalido');
                                btnAddLogoEmpresa.classList.add("btnError");
                                spanLogoEmpresa.classList.add("btnspan");
                            }

                            if (response == 'errorExistRegimenTab') {
                                toastError('Regimen invalido');  
                                $(tHeadTablaRegFiscal).addClass("btnError");
                            }
                            if (response == 'errorExistPorcentaje') {
                                toastError('Porcentaje invalido');  
                                $(tHeadTablaRegFiscal).addClass("btnError");
                            }

                            if (response == 'errorContentArrayRegimen'+resarray) { 
                                toastError('Regimen invalido');  
                                $(tHeadTablaRegFiscal).addClass("btnError");
                                var numTrFiscal = resarray+1;
                                var posTrfiscal = $(bodyTablaRegFiscal).find("tr").eq(numTrFiscal);
                                $(posTrfiscal).addClass("btnError");
                            }

                            if (response == 'errorArrayPorcentaje'+resarray) { 
                                toastError('Porcentaje invalido');  
                                $(tHeadTablaRegFiscal).addClass("btnError");
                                var numTrFiscal = resarray+1;
                                var posTrfiscal = $(bodyTablaRegFiscal).find("tr").eq(numTrFiscal);
                                $(posTrfiscal).addClass("btnError");
                            }

                            if (response == 'errorExistFPerfil') {
                                toastError('Foto de perfil invalida');
                                btnAddFotoPerfil.classList.add("btnError");
                                spanImgPerfil.classList.add("btnspan");
                            }
                            if (response == 'errorExistPaterno') {
                                toastError('Apellido paterno invalido');
                                errorInput(paterno,"Apellido paterno invalido");
                            }
                            if (response == 'errorExistMaterno') {
                                toastError('Apellido materno invalido');
                                errorInput(materno,"Apellido materno invalido");
                            }
                            if (response == 'errorExistNombres') {
                                toastError('Nombre invalido');
                                errorInput(nombres,"Nombre invalido");
                            }
                            if (response == 'errorExistArea') {
                                toastError('Área invalida');
                                errorInput(area,"Área invalida");
                            }
                            if (response == 'errorExistCargo') {
                                toastError('Cargo invalido');
                                errorInput(cargo,"Telefono invalido");
                            }
                            if (response == 'errorExistTelefono') {
                                toastError('Telefono invalido');
                                tHeadTablaTelMail.classList.add("btnError");
                            }
                            if (response == 'errorExistExtension') {
                                toastError('Extension invalida');
                                tHeadTablaTelMail.classList.add("btnError");
                            }
                            if (response == 'errorExistCorreo') {
                                toastError('Correo invalido');
                                tHeadTablaTelMail.classList.add("btnError");
                            }
                            if (response == 'errorTelefono'+resarray) { 
                                toastError('Telefono invalido'); 
                                $(tHeadTablaTelMail).addClass("btnError");
                                var numTrTelMail = resarray+1;
                                var posTrTelMail = $(bodyTablaTelMail).find("tr").eq(numTrTelMail);
                                $(posTrTelMail).addClass("btnError");
                            }
                            if (response == 'errorExtension'+resarray) { 
                                toastError('Telefono invalido'); 
                                $(tHeadTablaTelMail).addClass("btnError");
                                var numTrTelMail = resarray+1;
                                var posTrTelMail = $(bodyTablaTelMail).find("tr").eq(numTrTelMail);
                                $(posTrTelMail).addClass("btnError");
                            }
                            if (response == 'errorCorreo'+resarray) { 
                                toastError('Telefono invalido'); 
                                $(tHeadTablaTelMail).addClass("btnError");
                                var numTrTelMail = resarray+1;
                                var posTrTelMail = $(bodyTablaTelMail).find("tr").eq(numTrTelMail);
                                $(posTrTelMail).addClass("btnError");
                            }
                        }
                    });
                }
});

function Pesado() {
    Push.create("La imagen no debe superar los 2MB", {
        body: "SOS-México",
        icon: "vista/media/adm/usuarios/perfiles/logoSOS.png",
        timeout: 3000,
    });
};

function Error_ext() {
    Push.create("La imagen debe estar en formato .jpg ó .png", {
        body: "SOS-México",
        icon: "vista/media/adm/usuarios/perfiles/logoSOS.png",
        timeout: 3000,
    });
};

