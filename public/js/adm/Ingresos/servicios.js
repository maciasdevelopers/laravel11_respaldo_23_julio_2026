$(document).ready(function(){
    //vista de servicios
        var slctBusquedaServVig = document.getElementById("slctBusquedaServVig");
        var buscaServvigente = document.getElementById("buscaServvigente");
        const tBodyListaCatServicios = document.getElementById("tBodyListaCatServicios");
        var headerh4ServModal = document.getElementById("headerh4ServModal");
        var loadingServicio = document.getElementById("loadingServicio");
        var progressbarServicio = document.getElementById("progressbarServicio");

        $(listaServiciosVigentes());
        function listaServiciosVigentes(){
            $.ajax({
                url: "ingresos-listaVigentesServ",
                type: "post",
                dataType: 'html',
                success: function (respuesta) {
                    $(tBodyListaCatServicios).html(respuesta);
                }
            });
        }

        $(slctBusquedaServVig).change(function(){
            if (this.value != '' && strFilter.test(this.value)) {
                $(buscaServvigente).removeAttr('disabled');
                quitalblDisabled(buscaServvigente);
                if (this.value == 'clasificacion') {
                    buscaServvigente.setAttribute("maxlength","14");
                } else if (this.value == 'catalogoSat') {
                    buscaServvigente.setAttribute("maxlength","8");
                } else {
                    $(buscaServvigente).removeAttr("maxlength");
                }
            } else {
                $(buscaServvigente).attr('disabled',true);
                addlblDisabled(buscaServvigente);
            }
        });
        
        $(buscaServvigente).keyup(function(){
            const trBodyListaCatServicios = $(tBodyListaCatServicios).find("#trBodyListaCatServicios"); 
            if (this.value != '') {
                if (slctBusquedaServVig.value == 'clasificacion') {
                    if (/^[0-9-]+$/.test(this.value) && this.value.length <= 14) {
                        correctoInput(this,'Buscar servicio');
                        buscaTablasHtml(this,tBodyListaCatServicios,trBodyListaCatServicios);
                    } else {
                        errorInput(this,'filtro invalido');
                    }
                } 
        
                if (slctBusquedaServVig.value == 'servicio') {
                    if (strFilter.test(this.value)) {
                        correctoInput(this,'Buscar servicio');
                        buscaTablasHtml(this,tBodyListaCatServicios,trBodyListaCatServicios);
                    } else {
                        errorInput(this,'filtro invalido');
                    }
                } 
                
                if (slctBusquedaServVig.value == 'catalogoSat') {
                    if (/^[0-9]+$/.test(this.value) && this.value.length <= 8) {
                        correctoInput(this,'Buscar servicio');
                        buscaTablasHtml(this,tBodyListaCatServicios,trBodyListaCatServicios);
                    } else {
                        errorInput(this,'filtro invalido');
                    }
                }
            } else {
                buscaTablasHtml(this,tBodyListaCatServicios,trBodyListaCatServicios);
            }
        });
        
        $(buscaServvigente).bind('keypress', function(event){
            var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
            if (slctBusquedaServVig.value == 'clasificacion') {
                if (!/^[0-9-]+$/.test(clave) || clave.length > 14) {
                    event.preventDefault();
                    return false;
                }
            }
        
            if (slctBusquedaServVig.value == 'servicio') {
                if (!strFilter.test(clave)) {
                    event.preventDefault();
                    return false;
                }
            } 
        
            if (slctBusquedaServVig.value == 'fechaDelete') {
                if (!/^[0-9-]+$/.test(clave) || clave.length > 10) {
                    event.preventDefault();
                    return false;
                }
            }
        
        });

        $("#tabListaCatServicios").on("click","tr td a#btnVerServicio",function(){
            var tokenVerServicios = $(this).parent("td").parent("tr").find("td").eq(0).html();
            var tdImgServ = $(this).parent("td").parent("tr").find("td").eq(1).html();
            var tdServicios = $(this).parent("td").parent("tr").find("td").eq(3).html();
            headerh4ServModal.innerHTML = 'Información de servicio '+tdImgServ+' '+tdServicios;
            //alert(tokenVerServicios);
            var porcentajeCarga = 0;
            $.ajax({
                url: "ingresos-verservicio",
                type: "post",
                data: {
                        tokenVerServicios:tokenVerServicios,
                      },
                dataType: "html",
                success: function (respuesta) {
                    var intervalo = setInterval(() => {
                        porcentajeCarga = porcentajeCarga+1;
                        var porcenDiv = porcentajeCarga+'%';
                        $(".h6CargaServ").html('cargando... '+porcenDiv);
                        $(progressbarServicio).css('width',porcenDiv);
                        if (porcentajeCarga == 100) {
                            clearInterval(intervalo);
                            setTimeout(function (){
                                $("#dataModalInfoServicio").removeClass("noneView");
                                $("#txtEnablePassCheckedServ").removeClass("noneView");
                                $("#lbltxtEnablePassCheckedServ").removeClass("noneView");
                                $("#btnVerificaPassServ").removeClass("noneView");
                                $("#dataModalInfoServicio").html(respuesta);
                                $('.collapsible').collapsible();
                                $(loadingServicio).fadeOut("slow");
                            }, 1000);
                        }
                    }, 20);

                }
            });
        });

        $("#tabListaCatServicios").on("click","tr td a#btnDeleteServicio",function(){
            var trDelServicios = $(this).parent("td").parent("tr");
            var tokenVerServicios = $(this).parent("td").parent("tr").find("td").eq(0).html();
            cuteAlert({
                type: "question",
                title: "Alerta",
                message: "¿Deseas eliminar este servicio?",
                confirmText: "Si",
                cancelText: "No"
            }).then((e)=>{
                if (e){
                    $.ajax({
                        url: "ingresos-eliminaservicio",
                        type: "post",
                        data: {
                                tokenVerServicios:tokenVerServicios,
                            },
                        dataType: "html",
                        success: function (respuesta) {
                            if (respuesta == 'errorTokenServicio') {
                                toastError('Usuario no autorizado');
                                trDelServicios.classList.add("btnError");
                            }

                            if (respuesta == 'servNotDeleted') {
                                toastError('Usuario no autorizado');
                            }

                            if (respuesta == 'servDeleted') {
                                var $toastContent = $('<div class="btnCorrecto">eliminado exitosamente</div>');
                                Materialize.toast($toastContent,5000);
                                listaServiciosVigentes();
                                listaServiciosDeleted();
                            }
                        }
                    });
                } 
            })
            
        });

        var slctBusquedaServDel = document.getElementById("slctBusquedaServDel");
        var buscaServDelete = document.getElementById("buscaServDelete");
        const tBodyListaServDelCat = document.getElementById("tBodyListaServDelCat");

        $(listaServiciosDeleted());
        function listaServiciosDeleted(){
            $.ajax({
                url: "ingresos-listaEliminadosServ",
                type: "post",
                dataType: 'html',
                success: function (respuesta) {
                    $(tBodyListaServDelCat).html(respuesta);   
                }
            });
        }

        $(slctBusquedaServDel).change(function(){
            if (this.value != '' && strFilter.test(this.value)) {
                $(buscaServDelete).removeAttr('disabled');
                quitalblDisabled(buscaServDelete);
                if (this.value == 'clasificacion') {
                    buscaServDelete.setAttribute("maxlength","14");
                } else if (this.value == 'fechaDelete') {
                    buscaServDelete.setAttribute("maxlength","10");
                } else {
                    $(buscaServDelete).removeAttr("maxlength");
                }
            } else {
                $(buscaServDelete).attr('disabled',true);
                addlblDisabled(buscaServDelete);
            }
        });

        $(buscaServDelete).keyup(function(){
            const trBodyListaServDelCat = $(tBodyListaServDelCat).find("#trBodyListaServDelCat"); 
            if (this.value != '') {

                if (slctBusquedaServDel.value == 'clasificacion') {
                    if (/^[0-9-]+$/.test(this.value) && this.value.length <= 14) {
                        correctoInput(this,'Buscar servicio');
                        buscaTablasHtml(this,tBodyListaServDelCat,trBodyListaServDelCat);
                    } else {
                        errorInput(this,'filtro invalido');
                    }
                } 

                if (slctBusquedaServDel.value == 'servicio') {
                    if (strFilter.test(this.value)) {
                        correctoInput(this,'Buscar servicio');
                        buscaTablasHtml(this,tBodyListaServDelCat,trBodyListaServDelCat);
                    } else {
                        errorInput(this,'filtro invalido');
                    }
                } 
                
                if (slctBusquedaServDel.value == 'fechaDelete') {
                    if (filtroFecha.test(this.value) && this.value.length <= 10) {
                        correctoInput(this,'Buscar servicio');
                        buscaTablasHtml(this,tBodyListaServDelCat,trBodyListaServDelCat);
                    } else {
                        errorInput(this,'filtro invalido');
                    }
                }
            } else {
                buscaTablasHtml(this,tBodyListaServDelCat,trBodyListaServDelCat);
            }
        });

        $(buscaServDelete).bind('keypress', function(event){
            var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
            if (slctBusquedaServDel.value == 'clasificacion') {
                if (!/^[0-9-]+$/.test(clave) || clave.length > 14) {
                    event.preventDefault();
                    return false;
                }
            }

            if (slctBusquedaServDel.value == 'servicio') {
                if (!strFilter.test(clave)) {
                    event.preventDefault();
                    return false;
                }
            } 

            if (slctBusquedaServDel.value == 'fechaDelete') {
                if (!/^[0-9-]+$/.test(clave) || clave.length > 10) {
                    event.preventDefault();
                    return false;
                }
            }

        });

        $("#tabListaServDelCat").on("click","tr td a#btnRestServListaPap",function(){
            var trDelServicios = $(this).parent("td").parent("tr");
            var tokenVerServicios = $(this).parent("td").parent("tr").find("td").eq(0).html();
            alert("funciona "+tokenVerServicios);
            cuteAlert({
                type: "question",
                title: "Alerta",
                message: "¿Deseas restaurar este servicio?",
                confirmText: "Si",
                cancelText: "No"
            }).then((e)=>{
                if (e){
                    $.ajax({
                        url: "ingresos-srestauraservicio",
                        type: "post",
                        data: {
                                tokenVerServicios:tokenVerServicios,
                            },
                        dataType: "html",
                        success: function (respuesta) {
                            alert(respuesta);
                            if (respuesta == 'errorTokenServicio') {
                                toastError('Usuario no autorizado');
                                trDelServicios.classList.add("btnError");
                            }

                            if (respuesta == 'servNotRestored') {
                                toastError('Usuario no autorizado');
                            }

                            if (respuesta == 'servRestored') {
                                var $toastContent = $('<div class="btnCorrecto">restaurado exitosamente</div>');
                                Materialize.toast($toastContent,5000);
                                listaServiciosVigentes();
                                listaServiciosDeleted();
                            }
                        }
                    });
                } 
            })
        });

        $("#tabListaServDelCat").on("click","tr td a#btnDelServListaPap",function(){
            var trDelServicios = $(this).parent("td").parent("tr");
            var tokenVerServicios = $(this).parent("td").parent("tr").find("td").eq(0).html();
            cuteAlert({
                type: "question",
                title: "Alerta",
                message: "¿Deseas eliminar este servicio?",
                confirmText: "Si",
                cancelText: "No"
            }).then((e)=>{
                if (e){
                    $.ajax({
                        url: "ingresos-delpapservicio",
                        type: "post",
                        data: {
                                tokenVerServicios:tokenVerServicios,
                            },
                        dataType: "html",
                        success: function (respuesta) {
                            alert(respuesta);
                            if (respuesta == 'errorTokenServicio') {
                                toastError('Usuario no autorizado');
                                trDelServicios.classList.add("btnError");
                            }

                            if (respuesta == 'servNotFound') {
                                toastError('Servicio no encontrado o no valido');
                            }

                            if (respuesta == 'servNotDeleted') {
                                toastError('Usuario no autorizado');
                            }

                            if (respuesta == 'servDeleted') {
                                var $toastContent = $('<div class="btnCorrecto">eliminado exitosamente</div>');
                                Materialize.toast($toastContent,5000);
                                listaServiciosVigentes();
                                listaServiciosDeleted();
                            }
                        }
                    });
                } 
            })
            
        });

    //alta de servicios
        //validaciones
            //fecha
                var fechaAltaServicio = document.getElementById("fechaAltaServicio");
                $(fechaAltaServicio).change(function(){
                    if (this.value == '' || !filtroFecha.test(this.value)) {
                        errorInput(this,"Fecha invalida");
                    } else {
                        correctoInput(this,"Fecha de Alta");
                    }
                });
                
                //clasificacion
                var newFolio = document.getElementById("newFolio");
                var tabClasificacion = document.getElementById("tabClasificacion");
                var theadClasificacion = document.getElementById("theadClasificacion");
                var inputAddClasificacion = document.getElementById("inputAddClasificacion");

                $(listaGenero());
                function listaGenero(){
                    $.ajax({
                        type: "post",
                        url: "ingresos-listaclasificaserv",
                        data: {
                            tokenGenero:'-'
                        },
                        dataType: "html",
                        success: function (response) {
                            if (response == 'errorTknGenero') {
                                toastError('genero invalido'); 
                            } else {
                                $("#tbodyClasServ").html(response); 
                            }
                        }
                    });
                }

                $(tabClasificacion).on("click","td input#txtClassServicios",function(){
                    $(theadClasificacion).removeClass("btnError");
                    var token = this.value;
                    $.ajax({
                        type: "post",
                        url: "ingresos-generafolio",
                        data: {token:token},
                        dataType: "html",
                        success: function (response) {
                            if (response != 'error') {
                                $("#newFolio").html(response);
                            }
                        }
                    });
                });

                $(tabClasificacion).on("keyup","td input#inputAddClasificacion",function(){
                    var btnAddClass = $(this).parents("tr").find("#btnAddClass");
                    if (this.value != '' && strFilter.test(this.value) && this.value.length >= 10) {
                        $(btnAddClass).removeAttr('disabled');
                        $(this).removeClass("error");
                        $(this).addClass("correcto");
                    } else {
                        $(this).attr('disabled',true);
                        $(this).removeClass("correcto");
                        $(this).addClass("error");
                    }
                });

                $(tabClasificacion).on("bind keypress","td input#inputAddClasificacion",function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!strFilter.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

                $(tabClasificacion).on("click","td a#btnAddClass",function(){
                    var inputClasificacion = $(this).parents("tr").find("#inputAddClasificacion");
                    cuteAlert({
                        type: "question",
                        title: "Alerta",
                        message: "¿Deseas agregar esta clasificación?",
                        confirmText: "Si",
                        cancelText: "No"
                    }).then((e)=>{
                        if (e){
                            if (inputClasificacion.val() != '' && strFilter.test(inputClasificacion.val()) && 
                                inputClasificacion.val().length >= 10) {
                                var clasificacion = inputClasificacion.val();
                                $.ajax({
                                    type: "post",
                                    url: "ingresos-newclasificacion",
                                    data: {clasificacion:clasificacion},
                                    dataType: "html",
                                    success: function (response) {
                                        alert(response);
                                        if (response == 'executed') {
                                            listaGenero().load();
                                        }
                                    }
                                });
                            } else {
                                $(btnAddClass).attr('disabled',true);
                                $(inputClasificacion).removeClass("correcto");
                                $(inputClasificacion).addClass("error");
                            }
                        } 
                    })
                });

                //catalogo / codigo sat
                var precargaSat = document.getElementById("precargaSat");
                var h6Sat = document.getElementById("h6Sat"); 
                var catSATServHidden = document.getElementById("catSATServHidden");
                var catSATServ = document.getElementById("catSATServ");
                var verTablaCatalogo = document.getElementById("verTablaCatalogo");
                var btnFrameSat = document.getElementById("btnFrameSat");
                var divTablaSat = document.getElementById("divTablaSat");
                //var filtroBusquedaSat = document.querySelector('input[name="filtroBusquedaSat"]:checked');
                var filtroBusquedaSatCodigo = document.getElementById("filtroBusquedaSatCodigo"); 
                var filtroBusquedaSatDesc = document.getElementById("filtroBusquedaSatDesc");
                var buscaClaveSat = document.getElementById("buscaClaveSat");
                var btnBuscarCodigo = document.getElementById("btnBuscarCodigo");
                var frameSat = document.getElementById("frameSat");

                $(catSATServ).keyup(function(){
                    if (this.value == '' || !filtroNum.test(this.value) || this.value.length !=8) {
                        errorInput(this,"Código SAT invalido");
                    } else {
                        $.ajax({
                            type: "post",
                            url: "ingresos-verificacodigosat",
                            data: {codigoSat:this.value},
                            dataType: "html",
                            success: function (response) {
                                //alert(response);
                                precargaSat.classList.remove("noneView"); 
                                h6Sat.classList.add("precargaSat");
                                setTimeout(() => {
                                    precargaSat.classList.add("noneView"); 
                                    h6Sat.classList.remove("precargaSat");
                                    if (response == 'vacio') {
                                        errorInput(catSATServ,"Código SAT invalido");
                                        h6Sat.innerHTML = 'Código inexistente, verifique su marcación';
                                        divTablaSat.classList.remove("noneView");
                                    } else {
                                        correctoInput(catSATServ,"Código SAT");
                                        var posicion = response.split("/token/");
                                        h6Sat.innerHTML = 'Descripción de código de SAT: '+posicion[1];
                                        $(catSATServHidden).val(posicion[0]);
                                    }
                                },5000);
                            }
                        });
                    }
                });

                $(catSATServ).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!filtroNum.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

                $(verTablaCatalogo).click(function(){
                    if (divTablaSat.classList.contains("noneView")) {
                        divTablaSat.classList.remove("noneView");

                        if (!frameSat.classList.contains("noneView")) {
                            frameSat.classList.add("noneView");
                        }
                        
                    } else {
                        divTablaSat.classList.add("noneView");
                    }
                });

                $(btnFrameSat).click(function(){
                    if (frameSat.classList.contains("noneView")) {
                        frameSat.setAttribute("src","http://pys.sat.gob.mx/PyS/catPyS.aspx");
                        frameSat.classList.remove("noneView");

                        if (!divTablaSat.classList.contains("noneView")) {
                            divTablaSat.classList.add("noneView");
                        }

                    } else {
                        $(frameSat).removeAttr("src");
                        frameSat.classList.add("noneView");
                    }
                });

                $(verCodigoSat('',''));
                function verCodigoSat(categoria, valor){
                    $.ajax({
                        type: "post",
                        url: "ingresos-consultacodigosat",
                        data: {
                            categoria:categoria,
                            valor:valor
                        },
                        dataType: "html",
                        success: function (response) {
                            //alert(response);
                            $("#bodySerPrdSat").html(response);
                        }
                    });
                }

                $('input[name="filtroBusquedaSat"]').click(function(){
                    $(filtroBusquedaSatCodigo).parent("label").removeClass("errorlabel");
                    $(filtroBusquedaSatDesc).parent("label").removeClass("errorlabel");
                    buscaClaveSat.value == '';
                    $(buscaClaveSat).val('');

                    if (filtroBusquedaSatCodigo.checked == true) {
                        if (filtroBusquedaSatCodigo.value == 'codigo') {
                            $(buscaClaveSat).removeAttr('disabled');
                            quitalblDisabled(buscaClaveSat);
                            buscaClaveSat.type == "number";
                        } else {
                            //alert("No se puede continuar con la busqueda");
                            $(buscaClaveSat).attr('disabled',true);
                            addlblDisabled(buscaClaveSat);
                            $(filtroBusquedaSatCodigo).parent("label").addClass("errorlabel");
                            $(filtroBusquedaSatDesc).parent("label").addClass("errorlabel");
                        }
                    }

                    if (filtroBusquedaSatDesc.checked==true) {
                        if (filtroBusquedaSatDesc.value == 'descripcion') {
                            $(buscaClaveSat).removeAttr('disabled');
                            quitalblDisabled(buscaClaveSat);
                            buscaClaveSat.type == "text";
                        } else {
                            //alert("No se puede continuar con la busqueda");
                            $(buscaClaveSat).attr('disabled',true);
                            addlblDisabled(buscaClaveSat);
                            $(filtroBusquedaSatCodigo).parent("label").addClass("errorlabel");
                            $(filtroBusquedaSatDesc).parent("label").addClass("errorlabel");
                        }
                    } 
                    
                });

                $(buscaClaveSat).keyup(function(){
                    var filtroBusquedaSat = document.querySelector('input[name="filtroBusquedaSat"]:checked');

                    if (filtroBusquedaSat && filtroBusquedaSat.value != '') {
                        if (filtroBusquedaSat.value == 'codigo') {
                            if (this.value == '' || !filtroNum.test(this.value) || this.value.length < 5) {
                                errorInput(this,"Código SAT invalido");
                                $(btnBuscarCodigo).attr('disabled',true);
                            } else {
                                correctoInput(this,"Código SAT");
                                $(btnBuscarCodigo).removeAttr('disabled');
                            }
                        } 

                        if (filtroBusquedaSat.value == 'descripcion') {
                            if (this.value == '' || !filtroLetras.test(this.value) || this.value.length < 3) {
                                errorInput(this,"Código SAT invalido");
                                $(btnBuscarCodigo).attr('disabled',true); 
                            } else {
                                correctoInput(this,"Código SAT");
                                $(btnBuscarCodigo).removeAttr('disabled');
                            }
                        } 
                        
                    } else {
                        //alert("vacio");
                        if (!filtroBusquedaSat) {
                            $(filtroBusquedaSatCodigo).parent("label").addClass("errorlabel");
                            $(filtroBusquedaSatDesc).parent("label").addClass("errorlabel");
                        } 
                    }

                });

                $(buscaClaveSat).bind('keypress',function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    var filtroBusquedaSat = document.querySelector('input[name="filtroBusquedaSat"]:checked');
                    if (filtroBusquedaSat && filtroBusquedaSat.value != '') {
                        if (filtroBusquedaSat.value == 'codigo') {
                            if (!filtroNum.test(clave) || this.value.length >= 8) {
                                event.preventDefault();
                                return false;
                            }
                        } 

                        if (filtroBusquedaSat.value == 'descripcion') {
                            if (!filtroLetras.test(clave)) {
                                event.preventDefault();
                                return false;
                            }
                        } 
                        
                    } else {
                        //alert("vacio");
                        if (!filtroBusquedaSat) {
                            $(filtroBusquedaSatCodigo).parent("label").addClass("errorlabel");
                            $(filtroBusquedaSatDesc).parent("label").addClass("errorlabel");
                        } 
                    }

                });

                $(btnBuscarCodigo).click(function(){
                    var filtroBusquedaSat = document.querySelector('input[name="filtroBusquedaSat"]:checked');
                    if (filtroBusquedaSat && buscaClaveSat.value != '') {
                        if (filtroBusquedaSat.value != '' && filtroBusquedaSat.value == 'codigo'){
                            if (buscaClaveSat.value.length >= 5 && filtroNum.test(buscaClaveSat.value)) {
                                verCodigoSat('codigo',buscaClaveSat.value);
                            } else {
                                if (buscaClaveSat.value.length < 5 || !filtroNum.test(buscaClaveSat.value)) {
                                    verCodigoSat('codigo',buscaClaveSat.value);
                                }  
                            }
                        } else{
                            (!filtroBusquedaSat.value == '' || !filtroBusquedaSat.value == 'codigo');
                        }

                        if (filtroBusquedaSat.value != '' && filtroBusquedaSat.value == 'descripcion'){
                            if (buscaClaveSat.value.length >= 3 && strFilter.test(buscaClaveSat.value)) {
                                verCodigoSat('descripcion',buscaClaveSat.value);
                            } else {
                                if (buscaClaveSat.value.length < 3 || !strFilter.test(buscaClaveSat.value)) {
                                    verCodigoSat('descripcion',buscaClaveSat.value);
                                }
                            }
                        } else{
                            (!filtroBusquedaSat.value == '' || !filtroBusquedaSat.value == 'descripcion');
                        }
                        
                    } else {
                        
                    }
                });

                $("#tabSerProdSat").on("click","td input#selectCodigoSat",function(){
                    var codigo = $(this).parents("tr").find("td").eq(0).html();
                    var descripTab = $(this).parents("tr").find("td").eq(1).html();
                    //alert(codigo);
                    h6Sat.innerHTML = 'Descripción de código de SAT: '+descripTab;
                    $(catSATServHidden).val(this.value);
                    $(catSATServ).val(codigo);
                    //alert(this.value);
                });

                //concepto
                var serv_regCat = document.getElementById("serv_regCat");

                $(serv_regCat).keyup(function(){
                    if (this.value == '' || !strFilter.test(this.value) || this.value.length < 10) {
                        errorInput(this,"Concepto / Descripción invalida");
                    } else {
                        correctoInput(this,"Concepto / Descripción");
                    }
                });

                $(serv_regCat).bind('keypress', function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                    if (!strFilter.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

                //unidad de medida
                var txtUnidadMedida = document.getElementById("txtUnidadMedida");

                $(txtUnidadMedida).keyup(function(){
                    if (this.value == '' || !strFilter.test(this.value) || this.value.length < 3) {
                        errorInput(this,"Unidad de medida invalida");
                    } else {
                        correctoInput(this,"Unidad de medida");
                    }
                });

                $(txtUnidadMedida).change(function(){
                    if (this.value == '' || !strFilter.test(this.value) || this.value.length < 3) {
                        errorInput(this,"Unidad de medida invalida");
                    } else {
                        correctoInput(this,"Unidad de medida");
                    }
                });

                //moneda
                var txtMonedaServ = document.getElementById("txtMonedaServ");

                $(txtMonedaServ).keyup(function(){
                    if (this.value == '' || !strFilter.test(this.value) || this.value.length < 3) {
                        errorInput(this,"Moneda invalida");
                    } else {
                        correctoInput(this,"Moneda");
                    }
                });

                $(txtMonedaServ).change(function(){
                    if (this.value == '' || !strFilter.test(this.value) || this.value.length < 3) {
                        errorInput(this,"Moneda invalida");
                    } else {
                        correctoInput(this,"Moneda");
                        var cadena = this.value.substring(0,3);
                        console.log(cadena);
                        if (cadena == 'MXN') {
                            tipoCambio.value = 1;
                            $(tipoCambio).attr('disabled',true);
                            addlblDisabled(tipoCambio);
                        } else {
                            tipoCambio.value = '';
                            $(tipoCambio).removeAttr('disabled');
                            quitalblDisabled(tipoCambio);
                        }
                    }
                });

                //tipo de cambio
                var tipoCambio = document.getElementById("tipoCambio");

                $(tipoCambio).keyup(function(){
                    if (this.value == '' || !filtroCosto.test(this.value)) {
                        errorInput(this,"Tipo de cambio invalido");
                    } else {
                        correctoInput(this,"Tipo de cambio");

                    }
                });

                $(tipoCambio).change(function(){
                    if (this.value == '' || !filtroCosto.test(this.value)) {
                        errorInput(this,"Tipo de cambio invalido");
                    } else {
                        correctoInput(this,"Tipo de cambio");
                        var nuCosto = numeral(this.value);
                        tipoCambio.value = nuCosto.format('$0,0.00');
                        console.log(nuCosto.format('$0,0.00'));
                    }
                });

            //cantidad
                var cantidadServ = document.getElementById("cantidadServ");

                $(cantidadServ).keyup(function(){
                    if (this.value == '' || !filtroNum.test(this.value)) {
                        errorInput(this,'Cantidad invalida');
                        $(precioBaseServ).attr('disabled',true);
                        addlblDisabled(precioBaseServ);
                    } else {
                        correctoInput(this,'Cantidad');
                        if (precioBaseServ.value != '') {
                            llenaSubtotal();
                        } else {
                            $(precioBaseServ).removeAttr('disabled');
                            quitalblDisabled(precioBaseServ);
                        }
                    }
                });

                $(cantidadServ).bind('keypress',function(event){
                    var clave = String.fromCharCode(!event.charCode ? event.which : event.charCode);
                    if (!filtroNum.test(clave)) {
                        event.preventDefault();
                        return false;
                    }
                });

            //precio base
                var precioBaseServ = document.getElementById("precioBaseServ");

                $(precioBaseServ).keyup(function(){
                    if (this.value == '' || !filtroCosto.test(this.value)) {
                        errorInput(this,"Precio base invalido");
                    } else {
                        correctoInput(this,"Precio base");
                        llenaSubtotal();
                    }
                });

                $(precioBaseServ).change(function(){
                    if (this.value == '' || !filtroCosto.test(this.value)) {
                        errorInput(this,"Precio base invalido");
                    } else {
                        correctoInput(this,"Precio base");
                        var nuCosto = numeral(this.value);
                        precioBaseServ.value = nuCosto.format('$0,0.00');
                        console.log(nuCosto.format('$0,0.00'));
                    }
                });

                $(precioBaseServ).keypress(function(e){
                    if (!soloNumeros(event)) {
                        e.preventDefault();
                    }
                });

                //subtotal
                var subtotalServ = document.getElementById("subtotalServ");

                function llenaSubtotal(){
                    var pBase = '';
                    if (precioBaseServ.value.includes("$")) {
                        pBase = precioBaseServ.value.substring(1);
                    } else {
                        pBase = precioBaseServ.value.substring(0);
                    }
                    var tipocambio = tipoCambio.value.substring(1);
                    var cant = cantidadServ.value;
                    var multiplicacion = pBase * cant * tipocambio;                    
                    var numeralSubTotal = numeral(multiplicacion);
                    subtotalServ.value = numeralSubTotal.format('$0,0.00');
                    $(listaImpuestos).removeAttr('disabled');
                    listaImpuestos.classList.remove("disabled");
                } 

                //lista de impuestos
                var listaImpuestos = document.getElementById("listaImpuestos");
                var divtabsImpuestos = document.getElementById("divtabsImpuestos");
                var tabInfoImpuestos = document.getElementById("tabInfoImpuestos");
                var tabTotalImpuestos = document.getElementById("tabTotalImpuestos");
                var theadTotalImpuestos = document.getElementById("theadTotalImpuestos");
                var tbodyTotalImp = document.getElementById("tbodyTotalImp");
                var vacioTotalImp = document.getElementById("vacioTotalImp");
                var arrayImpuestos = [];

                $(listaImpuestos).click(function(){
                    if (subtotalServ.value != '' && filtroCosto.test(subtotalServ.value)) {
                        if (tbodyTotalImp.childNodes.length == 3) {
                            $('#divtabsImpuestos').removeClass("noneView");
                            $.ajax({
                                type: "post",
                                url: "ingresos-consultaimpuestos",
                                data: {precioBase:subtotalServ.value},
                                dataType: "html",
                                success: function (response) {
                                    if (response != 'impVacio') {
                                        $("#tbodyTabAproxImp").html(response);
                                        $("#tabInfoImpuestos td input#selectImpServ").each(function(){
                                            var importe = $(this).parents("tr").find("td").eq(3);
                                            var nuCosto = numeral(importe.html());
                                            importe.html(nuCosto.format('$0,0.00'));
                                        });
                                    } else {
                                        var $toastContent = $('<div class="btnError">No tienes impuesto agregados, agrega un nuevo impuesto</div>');
                                        Materialize.toast($toastContent,5000);  
                                    }
                                }
                            });
                        } else {
                            var mensaje = confirm("¿Desea actualizar el precio base?");
                            if (mensaje == true) {
                                $.ajax({
                                    type: "post",
                                    url: "ingresos-consultaimpuestos",
                                    data: {precioBase:subtotalServ.value},
                                    dataType: "html",
                                    success: function (response) {
                                        //console.log(response);
                                        if (response != 'impVacio') {
                                            $("#tbodyTabAproxImp").html(response);
                                            for (let i = 0; i < arrayImpuestos.length; i++) {
                                                $("#tabInfoImpuestos td input#selectImpServ").each(function(){
                                                    var importe = $(this).parents("tr").find("td").eq(3);
                                                    var nuCosto = numeral(importe.html());
                                                    importe.html(nuCosto.format('$0,0.00'));
                                                    if (this.value == arrayImpuestos[i]) {
                                                        $(this).attr("disabled",true);
                                                        this.checked = true;
                                                        var position = i+1;
                                                        var tabla = $(tbodyTotalImp).find("tr").eq(position);
                                                        var columna = $(tabla).find("td").eq(2);
                                                        columna.html(importe.html());
                                                    }
                                                });
                                            }
                                        } else {
                                            var $toastContent = $('<div class="btnError">No tienes impuesto agregados, agrega un nuevo impuesto</div>');
                                            Materialize.toast($toastContent,5000);  
                                        }
                                    }
                                });
                            }
                        }
      
                    } else {
                        lblapliServ.classList.add("errorlabel");
                    }
                });
                
                var contTdTotalez = 0;
                $(tabInfoImpuestos).on("click","td input#selectImpServ",function(){
                    $(theadTotalImpuestos).removeClass("btnError");
                    var token = this.value;
                    var alias = $(this).parents("tr").find("td").eq(1).html();
                    var total = $(this).parents("tr").find("td").eq(3).html();
                    arrayImpuestos.push(token);
                    vacioTotalImp.classList.add("noneView");

                    var datostd  = '<td class="noneView">'+contTdTotalez+'</td><td>'+alias+'</td><td>'+total+'</td>'+
                    '<td class="ultimo"><a id="deleteImpTotalServ" class="btn waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                    var nuevoTrImp = document.createElement("tr");
                    nuevoTrImp.innerHTML = datostd;
                    tbodyTotalImp.appendChild(nuevoTrImp);
                    $(this).attr("disabled",true);
                    contTdTotalez++;
                });

                $(tabTotalImpuestos).on("click","td a#deleteImpTotalServ",function(){
                    var mensaje = confirm("¿Desea eliminar este registro?");
                    if (mensaje) {
                        var trElimina = $(this).parent("td").parent("tr");
                        var posicion = trElimina.index() - 1;
                        $("#tabInfoImpuestos td input#selectImpServ").each(function(){
                            if (this.value == arrayImpuestos[posicion]) {
                                $(this).removeAttr('disabled');
                                this.checked = false; 
                            }
                        }); 

                        if (tbodyTotalImp.childNodes.length == 4) {
                            $(theadTotalImpuestos).addClass("btnError");
                            vacioTotalImp.classList.remove("noneView");
                            trElimina.remove();
                        } else {
                            trElimina.remove();
                        }
                        arrayImpuestos.splice(posicion,1);
                    }
                });

                //descuentos
                    var contFolio = 0;
                    var contFolioPromo = 0;
                    var asinfofolioDescuento = document.getElementById("asinfofolioDescuento");
                    var asfolioDescuento = document.getElementById("asfolioDescuento");
                    var asaliasDescuento = document.getElementById("asaliasDescuento");
                    var arrayTokenDescuentoas = [];
                    var arraYaliasDescuento = [];
                    var asconceptoDescuento = document.getElementById("asconceptoDescuento");
                    var arraYconceptoDescuento = [];
                    var asselectCotaPorcDescuento = document.getElementById("asselectCotaPorcDescuento");
                    var arraYselectCotaPorcDescuento = [];
                    var ascantidadBaseDescuento = document.getElementById("ascantidadBaseDescuento");
                    var arraYcantidadBaseDescuento = [];
                    var asselectTipoDescuento = document.getElementById("asselectTipoDescuento");
                    var arraYselectTipoDescuento = [];
                    var asfechaInicioDesc = document.getElementById("asfechaInicioDesc");
                    var arraYfechaInicioDesc = [];
                    var asfechaFinDesc = document.getElementById("asfechaFinDesc");
                    var arraYfechaFinDesc = [];
                    var asbtnRegDescuento = document.getElementById("asbtnRegDescuento");
                    var asbtnDeleteDescuento = document.getElementById("asbtnDeleteDescuento");

                    var astabAltaDescuentos = document.getElementById("astabAltaDescuentos");
                    var astheadAltaDescuentos = document.getElementById("astheadAltaDescuentos");
                    var astbodyAltaDescuentos = document.getElementById("astbodyAltaDescuentos");
                    var astrvAltaDescuentos = document.getElementById("astrvAltaDescuentos");

                    function nuevoFolio(){
                        var folioInfo = '';
                        var sumaFolio = parseInt(asfolioDescuento.value) + parseInt(contFolio);
                        if (sumaFolio < 10) {
                            folioInfo = '000'+sumaFolio;
                        } else if (sumaFolio > 10 && sumaFolio < 100) {
                            folioInfo = '00'+sumaFolio;
                        } else if (sumaFolio > 100 && sumaFolio < 1000) {
                            folioInfo = '0'+sumaFolio;
                        } else {
                            folioInfo = sumaFolio;
                        }
                        asinfofolioDescuento.innerHTML = folioInfo;
                    }

                    $(asaliasDescuento).keyup(function(){
                        aliasServDesc(this,asconceptoDescuento,asbtnDeleteDescuento);
                    });

                    $(asaliasDescuento).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(asconceptoDescuento).keyup(function(){
                        conceptoServDesc(this,asselectCotaPorcDescuento);
                    });
                    
                    $(asconceptoDescuento).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(asselectCotaPorcDescuento).change(function(){
                        cuotaPorcServDesc(this,ascantidadBaseDescuento);
                    });

                    $(ascantidadBaseDescuento).keyup(function(){
                        cantidadBaseKeyUp(asselectCotaPorcDescuento,this,asselectTipoDescuento);
                    });

                    $(ascantidadBaseDescuento).change(function(){
                        changeCantidadBase(asselectCotaPorcDescuento,this);
                    });

                    $(ascantidadBaseDescuento).bind('keypress',function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which : event.charCode);
                        if (!/^[0-9.,]+$/.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });
                    
                    $(asselectTipoDescuento).change(function(){
                        tipoDescuentoServDesc(this,asfechaInicioDesc,asfechaFinDesc,asbtnRegDescuento);
                    });

                    $(asfechaInicioDesc).change(function(){
                        fechaInicioServDesc(asselectTipoDescuento,this,asfechaFinDesc,asbtnRegDescuento);
                    });

                    $(asfechaFinDesc).change(function(){
                        fechaFinServDesc(asselectTipoDescuento,this,asbtnRegDescuento);
                    });

                    //var asbtnRegDescuento = document.getElementById("asbtnRegDescuento");
                    $(asbtnDeleteDescuento).click(function(){
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas eliminar este descuento?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                borraInputRow(asaliasDescuento);
                                borraInputRow(asconceptoDescuento);
                                $(asconceptoDescuento).attr("disabled",true);
                                $(asselectCotaPorcDescuento).attr("disabled",true);
                                asselectCotaPorcDescuento.selectedIndex = 0;
                                asselectCotaPorcDescuento.value = '';
                                $(asselectCotaPorcDescuento).material_select();
                                borraInputRow(ascantidadBaseDescuento);
                                $(ascantidadBaseDescuento).attr("disabled",true);
                                $(asselectTipoDescuento).attr("disabled",true);
                                asselectTipoDescuento.selectedIndex = 0;
                                asselectTipoDescuento.value = '';
                                $(asselectTipoDescuento).material_select();
                                borraInputRow(asfechaInicioDesc);
                                $(asfechaInicioDesc).attr("disabled",true);
                                borraInputRow(asfechaFinDesc);
                                $(asfechaFinDesc).attr("disabled",true);
                            } 
                        })
                    });

                    $(asbtnRegDescuento).click(function(){
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas agregar este descuento?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if ((asaliasDescuento.value != '' && strFilter.test(asaliasDescuento.value) && asaliasDescuento.value.length >= 4) &&
                                    (asconceptoDescuento.value != '' && strFilter.test(asconceptoDescuento.value) && asconceptoDescuento.value.length >= 5) &&
                                    (asselectCotaPorcDescuento.value != '' && strFilter.test(asselectCotaPorcDescuento.value)) &&
                                    (ascantidadBaseDescuento.value != '' && ((asselectCotaPorcDescuento.value == 'cuota' && filtroCosto.test(ascantidadBaseDescuento.value)) 
                                    || (asselectCotaPorcDescuento.value == 'porcentaje' && filtroPorc.test(ascantidadBaseDescuento.value)))) &&
                                    (asselectTipoDescuento.value != '' && strFilter.test(asselectTipoDescuento.value))) {
                                    
                                    if (asselectTipoDescuento.value == 'eventual') {
                                        llenaTablaDescServ('eventual','-','-');
                                    }
                                
                                    if (asselectTipoDescuento.value == 'pIndeterminado') {
                                        if (asfechaInicioDesc.value != '' && filtroFecha.test(asfechaInicioDesc.value)) {
                                            llenaTablaDescServ('indeterminado',asfechaInicioDesc.value,'-');
                                        } else {
                                            asfechaInicioDesc.classList.add("error");
                                        }
                                    }
                                
                                    if (asselectTipoDescuento.value == 'pDeterminado') {
                                        if (asfechaInicioDesc.value != '' && filtroFecha.test(asfechaInicioDesc.value) && 
                                            asfechaFinDesc.value != '' && filtroFecha.test(asfechaFinDesc.value)) {
                                            llenaTablaDescServ('determinado',asfechaInicioDesc.value,asfechaFinDesc.value);
                                        } else {
                                            if (asfechaInicioDesc.value == '' || !filtroFecha.test(asfechaInicioDesc.value)) {
                                                asfechaInicioDesc.classList.add("error");
                                            }
                                            if (asfechaFinDesc.value == '' || !filtroFecha.test(asfechaFinDesc.value)) {
                                                asfechaFinDesc.classList.add("error");
                                            }
                                        }
                                    }

                                    function llenaTablaDescServ(txtTdesc,fechaInicioDesc,fechaFinDesc){
                                        var folioTd = '';
                                        var sumaFolio = parseInt(asfolioDescuento.value) + parseInt(contFolio);
                                        if (sumaFolio < 10) {
                                            folioTd = '000'+sumaFolio;
                                        } else if (sumaFolio > 10 && sumaFolio < 100) {
                                            folioTd = '00'+sumaFolio;
                                        } else if (sumaFolio > 100 && sumaFolio < 1000) {
                                            folioTd = '0'+sumaFolio;
                                        } else {
                                            folioTd = sumaFolio;
                                        }
                                        astheadAltaDescuentos.classList.remove("btnError");
                                        astrvAltaDescuentos.classList.add("noneView");
                                        var nuevotr = document.createElement("tr");
                                        nuevotr.setAttribute("id","nuevoTr");
                                        var datos = '<td>'+folioTd+'</td><td>'+asaliasDescuento.value+'</td>'+
                                            '<td>'+asconceptoDescuento.value+'</td><td>'+asselectCotaPorcDescuento.value+'</td>'+
                                            '<td>'+ascantidadBaseDescuento.value+'</td><td>'+txtTdesc+'</td>'+
                                            '<td>'+fechaInicioDesc+'</td><td>'+fechaFinDesc+'</td>'+
                                            '<td><a id="deleteRegDesc" class="btn waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                                        nuevotr.innerHTML = datos;
                                        astbodyAltaDescuentos.appendChild(nuevotr);
                                        arraYaliasDescuento.push(asaliasDescuento.value);
                                        arraYconceptoDescuento.push(asconceptoDescuento.value);
                                        arraYselectCotaPorcDescuento.push(asselectCotaPorcDescuento.value);
                                        arraYcantidadBaseDescuento.push(ascantidadBaseDescuento.value);
                                        arraYselectTipoDescuento.push(asselectTipoDescuento.value);
                                        arraYfechaInicioDesc.push(fechaInicioDesc);
                                        arraYfechaFinDesc.push(fechaFinDesc);
                                        contFolio++;
                                        nuevoFolio();
                                    }

                                    borraInputRow(asaliasDescuento);
                                    borraInputRow(asconceptoDescuento);
                                    $(asconceptoDescuento).attr("disabled",true);
                                    $(asselectCotaPorcDescuento).attr("disabled",true);
                                    asselectCotaPorcDescuento.selectedIndex = 0;
                                    asselectCotaPorcDescuento.value = '';
                                    $(asselectCotaPorcDescuento).material_select();
                                    borraInputRow(ascantidadBaseDescuento);
                                    $(ascantidadBaseDescuento).attr("disabled",true);
                                    $(asselectTipoDescuento).attr("disabled",true);
                                    asselectTipoDescuento.selectedIndex = 0;
                                    asselectTipoDescuento.value = '';
                                    $(asselectTipoDescuento).material_select();
                                    borraInputRow(asfechaInicioDesc);
                                    $(asfechaInicioDesc).attr("disabled",true);
                                    borraInputRow(asfechaFinDesc);
                                    $(asfechaFinDesc).attr("disabled",true);

                                } else {
                                    if (asaliasDescuento.value == '' || !strFilter.test(asaliasDescuento.value) || asaliasDescuento.value.length < 4) {
                                        asaliasDescuento.classList.add("error");
                                    }
                                
                                    if (asconceptoDescuento.value == '' || !strFilter.test(asconceptoDescuento.value) || asconceptoDescuento.value.length < 5) {
                                        asconceptoDescuento.classList.add("error");
                                    }
                                
                                    if (asselectCotaPorcDescuento.value == '' || !strFilter.test(asselectCotaPorcDescuento.value)) {
                                        asselectCotaPorcDescuento.classList.add("error");
                                    }
                                
                                    if (ascantidadBaseDescuento.value == '' || !filtroCosto.test(ascantidadBaseDescuento.value)) {
                                        ascantidadBaseDescuento.classList.add("error");
                                    }
                                
                                    if (asselectTipoDescuento.value == '' || !strFilter.test(asselectTipoDescuento.value)) {
                                        asselectTipoDescuento.classList.add("error");
                                    }
                                }
                            } 
                        })
                    });

                    //astabAltaDescuentos
                    //astheadAltaDescuentos
                    //astbodyAltaDescuentos
                    //astrvAltaDescuentos

                    $("#astabInfoDescuentos").on("click","td input#selectDescAltaServ",function(){
                        var tknDescuento = this;
                        var folioTd = $(this).parents("td").parent("tr").find("td").eq(0).html();
                        var aliasTd = $(this).parents("td").parent("tr").find("td").eq(1).html();
                        var conceptoTd = $(this).parents("td").parent("tr").find("td").eq(2).html();
                        var selectCotaPorcTd = $(this).parents("td").parent("tr").find("td").eq(3).html();
                        var cantidadBaseTd = $(this).parents("td").parent("tr").find("td").eq(4).html();
                        var selectTipoTd = $(this).parents("td").parent("tr").find("td").eq(5).html();
                        var fechaInicioTd = $(this).parents("td").parent("tr").find("td").eq(6).html();
                        var fechaFinTd = $(this).parents("td").parent("tr").find("td").eq(7).html();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas agregar este descuento?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                $(tknDescuento).attr("disabled",true);
                                astheadAltaDescuentos.classList.remove("btnError");
                                astrvAltaDescuentos.classList.add("noneView");
                                var nuevotr = document.createElement("tr");
                                nuevotr.setAttribute("id","nuevoTrLista");
                                var datos = '<td class="noneView">'+tknDescuento.value+'</td><td>'+folioTd+' (descuento registrado)</td><td>'+aliasTd+'</td>'+
                                    '<td>'+conceptoTd+'</td><td>'+selectCotaPorcTd+'</td>'+
                                    '<td>'+cantidadBaseTd+'</td><td>'+selectTipoTd+'</td>'+
                                    '<td>'+fechaInicioTd+'</td><td>'+fechaFinTd+'</td>'+
                                    '<td><a id="deleteRegLDesc" class="btn waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                                nuevotr.innerHTML = datos;
                                astbodyAltaDescuentos.appendChild(nuevotr);
                                arrayTokenDescuentoas.push(tknDescuento.value);
                            } else {
                                $(tknDescuento).removeAttr("checked");
                            }
                        })
                    });

                    $("#astabAltaDescuentos").on("click","td a#deleteRegDesc",function(){
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
                                if (astbodyAltaDescuentos.childNodes.length == 4) {
                                    astrvAltaDescuentos.classList.remove("noneView");
                                    trElimina.remove();
                                } else {
                                    trElimina.remove();
                                }
                                var contFolioDel = 1;
                                $("#astabAltaDescuentos tbody #nuevoTr").each(function(){
                                    var folioTd = '';
                                    if (contFolioDel < 10) {
                                        folioTd = '000'+contFolioDel;
                                    } else if (contFolioDel > 10 && contFolioDel < 100) {
                                        folioTd = '00'+contFolioDel;
                                    } else if (contFolioDel > 100 && contFolioDel < 1000) {
                                        folioTd = '0'+contFolioDel;
                                    } else {
                                        folioTd = contFolioDel;
                                    }
                                    alert("hpoñl.lol");
                                    var primertd = $(this).find("td").eq(0);
                                    alert(primertd.text());
                                    primertd.html(folioTd);
                                    contFolioDel++;
                                });
                                arraYaliasDescuento.splice(arregloPos-1,1);
                                arraYconceptoDescuento.splice(arregloPos-1,1);
                                arraYselectCotaPorcDescuento.splice(arregloPos-1,1);
                                arraYcantidadBaseDescuento.splice(arregloPos-1,1);
                                arraYselectTipoDescuento.splice(arregloPos-1,1);
                                arraYfechaInicioDesc.splice(arregloPos-1,1);
                                arraYfechaFinDesc.splice(arregloPos-1,1);
                                contFolio--;
                                nuevoFolio();
                            } 
                        })
                    });

                    $("#astabAltaDescuentos").on("click","td a#deleteRegLDesc",function(){
                        var trElimina = $(this).parent("td").parent("tr");
                        var arregloPos = $(this).parent("td").parent("tr").find("td").eq(0).html();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas eliminar este registro?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if (astbodyAltaDescuentos.childNodes.length == 4) {
                                    astrvAltaDescuentos.classList.remove("noneView");
                                    trElimina.remove();
                                } else {
                                    trElimina.remove();
                                }
                                
                                for (let i = 0; i < arrayTokenDescuentoas.length; i++) {
                                    if (arrayTokenDescuentoas[i] == arregloPos) {
                                        $("#astabInfoDescuentos td input#selectDescAltaServ").each(function(){
                                            var tknDescuentoDel = this;
                                            if (arrayTokenDescuentoas[i] = tknDescuentoDel.value) {
                                                $(tknDescuentoDel).removeAttr("disabled");
                                                $(tknDescuentoDel).removeAttr("checked");
                                            }
                                        });
                                        arrayTokenDescuentoas.splice(arrayTokenDescuentoas[i],1); 
                                    }  
                                }
                            } 
                        })
                    });

                //promociones 
                    var asinfofolioPromocion = document.getElementById("asinfofolioPromocion");
                    var asfolioPromocion = document.getElementById("asfolioPromocion");
                    var arrayTokenPromociones = [];
                    var asaliasPromocion = document.getElementById("asaliasPromocion");
                    var arraYaliasPromocion = [];
                    var conceptoasPromocion = document.getElementById("conceptoasPromocion");
                    var arraYconceptoPromocion = [];
                    var asselectCotaPorcPromocion = document.getElementById("asselectCotaPorcPromocion");
                    var arraYselectCotaPorcPromocion = [];
                    var ascantidadBasePromocion = document.getElementById("ascantidadBasePromocion");
                    var arraYcantidadBasePromocion = [];
                    var asselectTipoPromocion = document.getElementById("asselectTipoPromocion");
                    var arraYselectTipoPromocion = [];
                    var asfechaInicioProm = document.getElementById("asfechaInicioProm");
                    var arraYfechaInicioProm = [];
                    var asfechaFinProm = document.getElementById("asfechaFinProm");
                    var arraYfechaFinProm = [];
                    var asbtnRegPromocion = document.getElementById("asbtnRegPromocion");
                    var asbtnDeletePromocion = document.getElementById("asbtnDeletePromocion");

                    var astabInfoPromocion = document.getElementById("astabInfoPromocion");
                    var astheadInfoPromocion = document.getElementById("astheadInfoPromocion");

                    var astabAltaPromocion = document.getElementById("astabAltaPromocion");
                    var astheadAltaPromocion = document.getElementById("astheadAltaPromocion");
                    var astbodyAltaPromocion = document.getElementById("astbodyAltaPromocion");
                    var astrvAltaPromocion = document.getElementById("astrvAltaPromocion");

                    function nuevoPromoFolio(){
                        var folioInfoPromo = '';
                        var sumaFolioPromo = parseInt(asfolioPromocion.value) + parseInt(contFolioPromo);
                        if (sumaFolioPromo < 10) {
                            folioInfoPromo = '000'+sumaFolioPromo;
                        } else if (sumaFolioPromo > 10 && sumaFolioPromo < 100) {
                            folioInfoPromo = '00'+sumaFolioPromo;
                        } else if (sumaFolioPromo > 100 && sumaFolioPromo < 1000) {
                            folioInfoPromo = '0'+sumaFolioPromo;
                        } else {
                            folioInfoPromo = sumaFolioPromo;
                        }
                        asinfofolioPromocion.innerHTML = folioInfoPromo;
                    }

                    $(asaliasPromocion).keyup(function(){
                        aliasPromo(this,conceptoasPromocion,asbtnDeletePromocion);
                    });
                    
                    $(asaliasPromocion).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(conceptoasPromocion).keyup(function(){
                        conceptoFuncPromo(this,asselectCotaPorcPromocion);
                    });
                    
                    $(conceptoasPromocion).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(asselectCotaPorcPromocion).change(function(){
                        cuotaPorcPromo(this,ascantidadBasePromocion);
                    });

                    $(ascantidadBasePromocion).keyup(function(){
                        cantBasePromoKeyup(asselectCotaPorcPromocion,this,asselectTipoPromocion);
                    });
                    
                    $(ascantidadBasePromocion).change(function(){
                        cantBasePromoChange(asselectCotaPorcPromocion,this);
                    });
                    
                    $(ascantidadBasePromocion).bind('keypress',function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which : event.charCode);
                        if (!/^[0-9.,]+$/.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(asselectTipoPromocion).change(function(){
                        tipoPromoAlta(this,asfechaInicioProm,asfechaFinProm,asbtnRegPromocion);
                    });

                    $(asfechaInicioProm).change(function(){
                        fechaInicioPromoServ(asselectTipoPromocion,this,asfechaFinProm,asbtnRegPromocion);
                    });

                    $(asfechaFinProm).change(function(){
                        fechaFinPromoServ(asselectTipoPromocion,this,asbtnRegPromocion);
                    });

                    $(asbtnRegPromocion).click(function(){
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas agregar esta promoción?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if ((asaliasPromocion.value != '' && strFilter.test(asaliasPromocion.value) && asaliasPromocion.value.length >= 4) &&
                                    (conceptoasPromocion.value != '' && strFilter.test(conceptoasPromocion.value) && conceptoasPromocion.value.length >= 5) &&
                                    (asselectCotaPorcPromocion.value != '' && strFilter.test(asselectCotaPorcPromocion.value)) &&
                                    (ascantidadBasePromocion.value != '' && ((asselectCotaPorcPromocion.value == 'cuota' && filtroCosto.test(ascantidadBasePromocion.value)) 
                                    || (asselectCotaPorcPromocion.value == 'porcentaje' && filtroPorc.test(ascantidadBasePromocion.value)))) &&
                                    (asselectTipoPromocion.value != '' && strFilter.test(asselectTipoPromocion.value))) {
                                    
                                    if (asselectTipoPromocion.value == 'eventual') {
                                        llenaTablaPromoServ('eventual','-','-');
                                    }
                                
                                    if (asselectTipoPromocion.value == 'pIndeterminado') {
                                        if (asfechaInicioProm.value != '' && filtroFecha.test(asfechaInicioProm.value)) {
                                            llenaTablaPromoServ('indeterminado',asfechaInicioProm.value,'-');
                                        } else {
                                            asfechaInicioProm.classList.add("error");
                                        }
                                    }
                                
                                    if (asselectTipoPromocion.value == 'pDeterminado') {
                                        if (asfechaInicioProm.value != '' && filtroFecha.test(asfechaInicioProm.value) && 
                                            asfechaFinProm.value != '' && filtroFecha.test(asfechaFinProm.value)) {
                                            llenaTablaPromoServ('indeterminado',asfechaInicioProm.value,asfechaFinProm.value);
                                        } else {
                                            if (asfechaInicioProm.value == '' || !filtroFecha.test(asfechaInicioProm.value)) {
                                                asfechaInicioProm.classList.add("error");
                                            }
                                            if (asfechaFinProm.value == '' || !filtroFecha.test(asfechaFinProm.value)) {
                                                asfechaFinProm.classList.add("error");
                                            }
                                        }
                                    }
                                
                                    function llenaTablaPromoServ(txtTipoPromo,promoFechaInicio,promoFechaFin){
                                        var folioTd = '';
                                        var sumaFolio = parseInt(asfolioPromocion.value) + parseInt(contFolioPromo);
                                        if (sumaFolio < 10) {
                                            folioTd = '000'+sumaFolio;
                                        } else if (sumaFolio > 10 && sumaFolio < 100) {
                                            folioTd = '00'+sumaFolio;
                                        } else if (sumaFolio > 100 && sumaFolio < 1000) {
                                            folioTd = '0'+sumaFolio;
                                        } else {
                                            folioTd = sumaFolio;
                                        }
                                        astheadAltaPromocion.classList.remove("btnError");
                                        astrvAltaPromocion.classList.add("noneView");
                                        var nuevotr = document.createElement("tr");
                                        nuevotr.setAttribute("id","nuevoTr");
                                        var datos = '<td>'+folioTd+'</td><td>'+asaliasPromocion.value+'</td>'+
                                            '<td>'+conceptoasPromocion.value+'</td><td>'+asselectCotaPorcPromocion.value+'</td>'+
                                            '<td>'+ascantidadBasePromocion.value+'</td><td>'+txtTipoPromo+'</td>'+
                                            '<td>'+promoFechaInicio+'</td><td>'+promoFechaFin+'</td>'+
                                            '<td><a id="deleteRegPromo" class="btn waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                                        nuevotr.innerHTML = datos;
                                        astbodyAltaPromocion.appendChild(nuevotr);
                                        arraYaliasPromocion.push(asaliasPromocion.value);
                                        arraYconceptoPromocion.push(conceptoasPromocion.value);
                                        arraYselectCotaPorcPromocion.push(asselectCotaPorcPromocion.value);
                                        arraYcantidadBasePromocion.push(ascantidadBasePromocion.value);
                                        arraYselectTipoPromocion.push(asselectTipoPromocion.value);
                                        arraYfechaInicioProm.push(promoFechaInicio);
                                        arraYfechaFinProm.push(promoFechaFin);
                                        contFolioPromo++;
                                        nuevoPromoFolio();
                                    }

                                    borraInputRow(asaliasPromocion);
                                    borraInputRow(conceptoasPromocion);
                                    $(conceptoasPromocion).attr("disabled",true);
                                    $(asselectCotaPorcPromocion).attr("disabled",true);
                                    asselectCotaPorcPromocion.selectedIndex = 0;
                                    asselectCotaPorcPromocion.value = '';
                                    $(asselectCotaPorcPromocion).material_select();
                                    borraInputRow(ascantidadBasePromocion);
                                    $(ascantidadBasePromocion).attr("disabled",true);
                                    $(asselectTipoPromocion).attr("disabled",true);
                                    asselectTipoPromocion.selectedIndex = 0;
                                    asselectTipoPromocion.value = '';
                                    $(asselectTipoPromocion).material_select();
                                    borraInputRow(asfechaInicioProm);
                                    $(asfechaInicioProm).attr("disabled",true);
                                    borraInputRow(asfechaFinProm);
                                    $(asfechaFinProm).attr("disabled",true);

                                } else {
                                    if (asaliasPromocion.value == '' || !strFilter.test(asaliasPromocion.value) || asaliasPromocion.value.length < 4) {
                                        asaliasPromocion.classList.add("error");
                                    }
                                
                                    if (conceptoasPromocion.value == '' || !strFilter.test(conceptoasPromocion.value) || conceptoasPromocion.value.length < 5) {
                                        conceptoasPromocion.classList.add("error");
                                    }
                                
                                    if (asselectCotaPorcPromocion.value == '' || !strFilter.test(asselectCotaPorcPromocion.value)) {
                                        asselectCotaPorcPromocion.classList.add("error");
                                    }
                                
                                    if (ascantidadBasePromocion.value == '' || !filtroCosto.test(ascantidadBasePromocion.value)) {
                                        ascantidadBasePromocion.classList.add("error");
                                    }
                                
                                    if (asselectTipoPromocion.value == '' || !strFilter.test(asselectTipoPromocion.value)) {
                                        asselectTipoPromocion.classList.add("error");
                                    }
                                }
                            } 
                        })
                    });

                    //asbtnDeletePromocion
                    //astabInfoPromocion
                    //astheadInfoPromocion

                    $(astabInfoPromocion).on("click","td input#selectPromoAltaServ",function(){
                        var tknPromocion = this;
                        var folioTd = $(this).parents("td").parent("tr").find("td").eq(0).html();
                        var aliasTd = $(this).parents("td").parent("tr").find("td").eq(1).html();
                        var conceptoTd = $(this).parents("td").parent("tr").find("td").eq(2).html();
                        var selectCotaPorcTd = $(this).parents("td").parent("tr").find("td").eq(3).html();
                        var cantidadBaseTd = $(this).parents("td").parent("tr").find("td").eq(4).html();
                        var selectTipoTd = $(this).parents("td").parent("tr").find("td").eq(5).html();
                        var fechaInicioTd = $(this).parents("td").parent("tr").find("td").eq(6).html();
                        var fechaFinTd = $(this).parents("td").parent("tr").find("td").eq(7).html();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas agregar este descuento?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                $(tknPromocion).attr("disabled",true);
                                astheadAltaPromocion.classList.remove("btnError");
                                astrvAltaPromocion.classList.add("noneView");
                                var nuevotr = document.createElement("tr");
                                nuevotr.setAttribute("id","nuevoTrLista");
                                var datos = '<td class="noneView">'+tknPromocion.value+'</td><td>'+folioTd+' (descuento registrado)</td><td>'+aliasTd+'</td>'+
                                    '<td>'+conceptoTd+'</td><td>'+selectCotaPorcTd+'</td>'+
                                    '<td>'+cantidadBaseTd+'</td><td>'+selectTipoTd+'</td>'+
                                    '<td>'+fechaInicioTd+'</td><td>'+fechaFinTd+'</td>'+
                                    '<td><a id="deleteRegLPromo" class="btn waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                                nuevotr.innerHTML = datos;
                                astbodyAltaPromocion.appendChild(nuevotr);
                                arrayTokenPromociones.push(tknPromocion.value);
                            } else {
                                $(tknPromocion).removeAttr("checked");
                            }
                        })
                    });

                    $(astabAltaPromocion).on("click","td a#deleteRegPromo",function(){
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
                                if (astbodyAltaPromocion.childNodes.length == 4) {
                                    astrvAltaPromocion.classList.remove("noneView");
                                    trElimina.remove();
                                } else {
                                    trElimina.remove();
                                }
                                var contFolioPromoDel = 1;
                                $("#astabAltaPromocion tbody #nuevoTr").each(function(){
                                    var folioTd = '';
                                    if (contFolioPromoDel < 10) {
                                        folioTd = '000'+contFolioPromoDel;
                                    } else if (contFolioPromoDel > 10 && contFolioPromoDel < 100) {
                                        folioTd = '00'+contFolioPromoDel;
                                    } else if (contFolioPromoDel > 100 && contFolioPromoDel < 1000) {
                                        folioTd = '0'+contFolioPromoDel;
                                    } else {
                                        folioTd = contFolioPromoDel;
                                    }
                                    alert("hpoñl.lol");
                                    var primertd = $(this).find("td").eq(0);
                                    alert(primertd.text());
                                    primertd.html(folioTd);
                                    contFolioPromoDel++;
                                });
                                arraYaliasPromocion.splice(arregloPos-1,1);
                                arraYconceptoPromocion.splice(arregloPos-1,1);
                                arraYselectCotaPorcPromocion.splice(arregloPos-1,1);
                                arraYcantidadBasePromocion.splice(arregloPos-1,1);
                                arraYselectTipoPromocion.splice(arregloPos-1,1);
                                arraYfechaInicioProm.splice(arregloPos-1,1);
                                arraYfechaFinProm.splice(arregloPos-1,1);
                                contFolioPromo--;
                                nuevoPromoFolio();
                            } 
                        });
                    });
                    
                    $(astabAltaPromocion).on("click","td a#deleteRegLPromo",function(){
                        var trElimina = $(this).parent("td").parent("tr");
                        var arregloPos = $(this).parent("td").parent("tr").find("td").eq(0).html();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Deseas eliminar este registro?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                if (astbodyAltaPromocion.childNodes.length == 4) {
                                    astrvAltaPromocion.classList.remove("noneView");
                                    trElimina.remove();
                                } else {
                                    trElimina.remove();
                                }
                                
                                for (let i = 0; i < arrayTokenPromociones.length; i++) {
                                    if (arrayTokenPromociones[i] == arregloPos) {
                                        $("#astabInfoPromocion td input#selectPromoAltaServ").each(function(){
                                            var tknPromocionDel = this;
                                            if (arrayTokenPromociones[i] = tknPromocionDel.value) {
                                                $(tknPromocionDel).removeAttr("disabled");
                                                $(tknPromocionDel).removeAttr("checked");
                                            }
                                        });
                                        arrayTokenPromociones.splice(arrayTokenPromociones[i],1); 
                                    }  
                                }
                            } 
                        })
                    });

                //claves alternas y periodicidad de venta
                    var selectClientNacExt = document.getElementById("selectClientNacExt");
                    var selectClientFisMor = document.getElementById("selectClientFisMor");
                    var selectIdTaxRfc = document.getElementById("selectIdTaxRfc");
                    var buscaClavealterna = document.getElementById("buscaClavealterna");
                    var lblClaveAlterna = document.getElementById("lblClaveAlterna");
                    var tbodyClienteServicio = document.getElementById("tbodyClienteServicio");

                    $(listaClientes('','','',''));
                    function listaClientes(nacionalidad,tipoPersona,rfcTaxNombre,descripcion){
                        $.ajax({
                            type: "post",
                            url: "ingresos-consultaclientesserv",
                            data: {
                                nacionalidad:nacionalidad,
                                tipoPersona:tipoPersona,
                                rfcIdtax:rfcTaxNombre,
                                datoBusqueda:descripcion
                            },
                            dataType: "html",
                            success: function (response) {
                                $(tbodyClienteServicio).html(response);
                                $("#tableClienteServicio tr").each(function (){
                                    var lPrecios = $(this).find("td").eq(4);
                                    //lPrecios.addClass("noneView");
                                    lPrecios.remove();
                                });
                            }
                        });
                    }

                    var claveServ = document.getElementById("claveServ");
                    var arrayClaveAsignada = [];
                    var arrayClientClave = [];
                    var periodicidadVenta = document.getElementById("assPeriodicidadVenta");
                    var arrayperiodicidadVenta = [];
                    var periodNotifVenta = document.getElementById("assPeriodNotifVenta");
                    var arrayperiodNotifVenta = [];
                    var fechaInicioNotifVenta = document.getElementById("asfechaInicioNotifVenta");
                    var arrayfechaInicioNotifVenta = [];
                    var fechaFinNotifVenta = document.getElementById("asfechaFinNotifVenta");
                    var arrayfechaFinNotifVenta = [];
                    var addClaveClient = document.getElementById("addClaveClient");
                    var tabClienteClaveServ = document.getElementById("tabClienteClaveServ");
                    var theadClienteClaveServ = document.getElementById("theadClienteClaveServ");
                    var tbodyClaveClienteServ = document.getElementById("tbodyClaveClienteServ");
                    var trClaveVacia = document.getElementById("trClaveVacia");

                    function desabilita1(){
                        $(selectClientFisMor).attr('disabled',true);
                        $(selectClientFisMor).material_select();
                        addlblDisabledSelect(selectClientFisMor);
                        selectClientFisMor.selectedIndex = 0;
                    }
                    
                    function desabilita2(){
                        $(selectIdTaxRfc).attr("disabled",true);
                        $(selectIdTaxRfc).material_select();
                        addlblDisabledSelect(selectIdTaxRfc);
                        selectIdTaxRfc.selectedIndex = 0;
                    }
                    
                    function desabilita3(){
                        buscaClavealterna.value = '';
                        lblClaveAlterna.innerText = '';
                        $(buscaClavealterna).attr('disabled',true);
                        addlblDisabled(buscaClavealterna);
                        $(buscaClavealterna).removeAttr("data-length");
                        $(buscaClavealterna).removeAttr("placeholder");
                        $(buscaClavealterna).removeAttr("maxlength");
                    }

                    $(selectClientNacExt).change(function(){
                        desabilita1(),desabilita2(),desabilita3();
                        if (this.value === '' || !strFilter.test(this.value)) {
                            addlblDisabledSelect(selectClientFisMor);
                            $(selectClientFisMor).attr("disabled",true);
                            $(selectClientFisMor).material_select();
                        } else {
                            listaClientes(this.value,'','','');
                            $(selectClientFisMor).removeAttr('disabled');
                            $(selectClientFisMor).material_select();
                            quitalblDisabledSelect(selectClientFisMor);
                        }
                    });
                    
                    $(selectClientFisMor).change(function(){
                        desabilita2(),desabilita3();
                        if (this.value === '' || !strFilter.test(this.value)) {
                            addlblDisabledSelect(selectIdTaxRfc);
                            $(selectIdTaxRfc).attr("disabled",true);
                            $(selectIdTaxRfc).material_select();
                        } else {
                            listaClientes(selectClientNacExt.value,this.value,'','');
                            $(selectIdTaxRfc).removeAttr('disabled');
                            $(selectIdTaxRfc).material_select();
                            quitalblDisabledSelect(selectIdTaxRfc);
                        }
                    });
                    
                    $(selectIdTaxRfc).change(function(){
                        desabilita3();
                        if (this.value === '' || !strFilter.test(this.value)) {
                            addlblDisabledSelect(buscaClavealterna);
                            $(buscaClavealterna).attr("disabled",true);
                        } else {
                            if (this.value == 'idTaxRfc') {
                                $(buscaClavealterna).removeAttr('disabled');
                                quitalblDisabled(buscaClavealterna);
                                if (selectClientNacExt.value  == 'nacional') {
                                    if (selectClientFisMor.value == 'fisica') {
                                        buscaClavealterna.setAttribute("data-length","13");
                                        buscaClavealterna.setAttribute("placeholder","Ej. ABCD000000XXX");
                                        buscaClavealterna.setAttribute("maxlength","13");
                                    }
                                
                                    if (selectClientFisMor.value == 'moral') {
                                        buscaClavealterna.setAttribute("data-length","12");
                                        buscaClavealterna.setAttribute("placeholder","Ej. ABC000000XXX");
                                        buscaClavealterna.setAttribute("maxlength","12");
                                    }
                                
                                }
                            
                                if (selectClientNacExt.value  == 'extranjero') {
                                    buscaClavealterna.setAttribute("minlength","9");
                                    buscaClavealterna.setAttribute("maxlength","40");
                                }
                            }
                        
                            if (this.value == 'nombre') {
                                $(buscaClavealterna).removeAttr('disabled');
                                quitalblDisabled(buscaClavealterna);
                                if (selectClientNacExt.value  == 'nacional') {
                                    if (selectClientFisMor.value == 'fisica') {
                                        buscaClavealterna.setAttribute("placeholder","Nombre");
                                    }
                                
                                    if (selectClientFisMor.value == 'moral') {
                                        buscaClavealterna.setAttribute("placeholder","Nombre");
                                    }
                                
                                }
                            
                                if (selectClientNacExt.value  == 'extranjero') {
                                    buscaClavealterna.setAttribute("minlength","3");
                                }
                            }
                        }
                    });
                    
                    $(buscaClavealterna).keyup(function(){
                        if (this.value != '') { 
                            if (selectIdTaxRfc.value == 'idTaxRfc') {
                                if (selectClientNacExt.value == 'nacional') {
                                    if (selectClientFisMor.value == 'fisica') {
                                        var cdna1 = buscaClavealterna.value.substring(0,4);
                                        var cdna2 = buscaClavealterna.value.substring(4,10);
                                        var cdna3 = buscaClavealterna.value.substring(10,13);
                                        if (/^[a-zA-Z]+$/.test(cdna1)) {
                                            if (/^[0-9]+$/.test(cdna2)) {
                                                if (/^[a-zA-Z0-9]+$/.test(cdna3) && buscaClavealterna.value.length == 13) {
                                                    listaClientes(selectClientNacExt.value,selectClientFisMor.value,selectIdTaxRfc.value,this.value);
                                                    correctoInput(buscaClavealterna,'Escriba su rfc con Homoclave');
                                                } else {
                                                    errorInput(buscaClavealterna,'su RFC no es correcto');
                                                }
                                            } else {
                                                errorInput(buscaClavealterna,'su RFC no es correcto');
                                            }
                                        } else {
                                            errorInput(buscaClavealterna,'su RFC no es correcto');
                                        }
                                    }
                                    if (selectClientFisMor.value == 'moral') {
                                        var cdna1 = buscaClavealterna.value.substring(0,3);
                                        var cdna2 = buscaClavealterna.value.substring(3,9);
                                        var cdna3 = buscaClavealterna.value.substring(9,12);
                                        if (/^[a-zA-Z]+$/.test(cdna1)) {
                                            if (/^[0-9]+$/.test(cdna2)) {
                                                if (/^[a-zA-Z0-9]+$/.test(cdna3) && buscaClavealterna.value.length == 12) {
                                                    listaClientes(selectClientNacExt.value,selectClientFisMor.value,selectIdTaxRfc.value,this.value);
                                                    correctoInput(buscaClavealterna,'Escriba su rfc con Homoclave');
                                                }
                                                else{
                                                    errorInput(buscaClavealterna,'su RFC no es correcto');
                                                }
                                            }
                                            else{
                                                errorInput(buscaClavealterna,'su RFC no es correcto');
                                            }
                                        }
                                        else{
                                            errorInput(buscaClavealterna,'su RFC no es correcto');
                                        }
                                    }
                                }
                            
                                if (selectClientNacExt.value == 'extranjero') {
                                    if (selectClientFisMor.value == 'fisica') {
                                        if (this.value.length> 9 && this.value.length <40 && strFilter.test(this.value)) {
                                            listaClientes(selectClientNacExt.value,selectClientFisMor.value,selectIdTaxRfc.value,this.value);
                                            correctoInput(buscaClavealterna,'Escriba idTax ó nombre completo del cliente');
                                        } else {
                                            errorInput(buscaClavealterna,'IdTax ó nombre invalido');
                                        }
                                    }
                                    if (selectClientFisMor.value == 'moral') {
                                        if ((this.value.length> 9 && this.value.length <40) && strFilter.test(this.value)) {
                                            listaClientes(selectClientNacExt.value,selectClientFisMor.value,selectIdTaxRfc.value,this.value);
                                            correctoInput(buscaClavealterna,'Escriba idTax ó razon social del cliente');
                                        } else {
                                            errorInput(buscaClavealterna,'IdTax ó razon social invalida');
                                        }
                                    }
                                }
                            } 
                            if (selectIdTaxRfc.value == 'nombre') {
                                if (this.value == '' || !filtroLetras.test(this.value) || this.value.length < 3) {
                                    errorInput(this,"Nombre invalido"); 
                                } else {
                                    listaClientes(selectClientNacExt.value,selectClientFisMor.value,selectIdTaxRfc.value,this.value);
                                    correctoInput(this,'Nombre'); 
                                }
                            } 
                        
                        } else {
                            if (selectIdTaxRfc.value == 'idTaxRfc') {
                                if (selectClientNacExt.value == 'nacional') {
                                    if (selectClientFisMor.value == 'fisica') {
                                        errorInput(buscaClavealterna,'Rfc incorrecto (13 caracteres Ej. ABCD000000XXX)');
                                    }
                                    if (selectClientFisMor.value == 'moral') {
                                        errorInput(buscaClavealterna,'Rfc incorrecto (12 caracteres Ej. ABC000000XXX)');
                                    }
                                }
                            
                                if (selectClientNacExt.value == 'extranjero') {
                                    if (selectClientFisMor.value == 'fisica') {
                                        errorInput(buscaClavealterna,'IdTax ó nombre del cliente es invalido');
                                    }
                                    if (selectClientFisMor.value == 'moral') {
                                        errorInput(buscaClavealterna,'IdTax ó razon social del cliente es invalida');
                                    }
                                }
                            }
                            if (selectIdTaxRfc.value == 'nombre') {
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

                    function disabledPeriodv(){
                        addlblDisabledSelect(assPeriodicidadVenta);
                        addlblDisabledSelect(assPeriodNotifVenta);
                        addlblDisabledSelect(asfechaInicioNotifVenta);
                        addlblDisabledSelect(asfechaFinNotifVenta);
                    
                        $(assPeriodicidadVenta).attr('disabled',true);
                        $(assPeriodNotifVenta).attr('disabled',true);
                        $(asfechaInicioNotifVenta).attr('disabled',true);
                        $(asfechaFinNotifVenta).attr('disabled',true);
                    } 

                    $(claveServ).keyup(function(){
                        disabledPeriodv();
                        if (this.value == '' || !filtroClave.test(this.value) || this.value.length < 4) {
                            errorInput(this,"Clave asignada invalida");
                        } else {
                            correctoInput(this,"Clave asignada");
                            $(periodNotifVenta).removeAttr('disabled');
                            quitalblDisabled(periodNotifVenta);
                            $(periodNotifVenta).material_select();
                        }
                    });
                    
                    $(claveServ).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!filtroClave.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    $(periodNotifVenta).change(function(){
                        if (this.value != '' && strFilter.test(this.value)) {
                            $(periodicidadVenta).removeAttr('disabled');
                            quitalblDisabled(periodicidadVenta);
                            $(periodicidadVenta).material_select();
                        } else {
                            errorSelect(this,'periodo invalido');
                            $(periodicidadVenta).attr('disabled',true);
                            addlblDisabledSelect(periodicidadVenta);
                            $(periodicidadVenta).material_select();
                        }
                    });

                    $(periodicidadVenta).change(function(){
                        tipoPromoAlta(this,asfechaInicioNotifVenta,fechaFinNotifVenta,addClaveClient);
                    });

                    $(asfechaInicioNotifVenta).change(function(){
                        fechaInicioPromoServ(periodicidadVenta,this,fechaFinNotifVenta,addClaveClient);
                    });

                    $(fechaFinNotifVenta).change(function(){
                        fechaFinPromoServ(periodicidadVenta,this,addClaveClient);
                    });

                    $(addClaveClient).click(function(){
                        alert("fiunciona");
                        var selectCliente = document.querySelector('input[name="selectClientClave"]:checked');
                        if (selectCliente && selectCliente.value != '' && 
                            (claveServ.value != '' && filtroClave.test(claveServ.value) && claveServ.value.length >= 4) &&
                            (periodicidadVenta.value != '' && strFilter.test(periodicidadVenta.value)) &&
                            (periodNotifVenta.value != '' && strFilter.test(periodNotifVenta.value)) ) {
                            correctoInput(claveServ,"Clave asignada");
                            theadClienteClaveServ.classList.remove("btnError");
                            var cliente = $(selectCliente).parents("tr").find("td").eq(3).html();
                            arrayClientClave.push(selectCliente.value);
                            arrayClaveAsignada.push(claveServ.value);
                            arrayperiodNotifVenta.push(periodNotifVenta.value);
                            arrayperiodicidadVenta.push(periodicidadVenta.value);
                            var tdnotif = '';
                            var tdperiodicidad = '';
                            var tdfechainiperiod = '';
                            var tdfechafinperiod = '';
                            if(periodNotifVenta.value == 'daytNotifa1e') { 
                                tdnotif = 'día'; 
                            } else if(periodNotifVenta.value == 'weektNotifa1e') { 
                                tdnotif = 'semana'; 
                            } else if(periodNotifVenta.value == 'monthtNotifa1e') { 
                                tdnotif = 'mes'; 
                            } else { 
                                tdnotif = 'año'; 
                            }

                            if(periodicidadVenta.value == 'eventual') { 
                                tdperiodicidad = 'eventual'; 
                                tdfechainiperiod = '-';
                                tdfechafinperiod = '-';
                            } 
                            if(periodicidadVenta.value == 'pIndeterminado') { 
                                tdperiodicidad = 'indeterminado'; 
                                tdfechainiperiod = fechaInicioNotifVenta.value;
                                tdfechafinperiod = '-';
                            }
                            if(periodicidadVenta.value == 'pDeterminado') { 
                                tdperiodicidad = 'determinado'; 
                                tdfechainiperiod = '-';
                                tdfechafinperiod = '-';
                            }

                            arrayfechaInicioNotifVenta.push(tdfechainiperiod);
                            arrayfechaFinNotifVenta.push(tdfechafinperiod);

                            trClaveVacia.classList.add("noneView");

                            var datostd  = '<td>'+cliente+'</td><td>'+claveServ.value+'</td>'+
                            '<td>'+tdnotif+'</td><td>'+tdperiodicidad+'</td>'+
                            '<td>'+tdfechainiperiod+'</td><td>'+tdfechafinperiod+'</td>'+
                            '<td class="ultimo"><a id="deleteClientServ" class="btn waves-effect btn-floating waves-light red darken-2">&#xf1f8;</a></td>';
                            var nuevoTrClientClave = document.createElement("tr");
                            nuevoTrClientClave.innerHTML = datostd;
                            tbodyClaveClienteServ.appendChild(nuevoTrClientClave);
                            $(selectCliente).attr('disabled',true);

                            periodNotifVenta.selectedIndex = 0;
                            periodNotifVenta.value = '';
                            $(periodNotifVenta).material_select();

                            periodicidadVenta.selectedIndex = 0;
                            periodicidadVenta.value = '';
                            $(periodicidadVenta).material_select();

                            borraInputRow(fechaInicioNotifVenta);
                            $(fechaInicioNotifVenta).attr("disabled",true);

                            borraInputRow(fechaFinNotifVenta);
                            $(fechaFinNotifVenta).attr("disabled",true);

                        } else {
                            if (!selectCliente || selectCliente.value == '') {
                                
                            }
                            if (claveServ.value == '' || !filtroClave.test(claveServ.value) || claveServ.value.length < 4) {
                                errorInput(claveServ,"Clave asignada invalida");
                            }

                            if (periodicidadVenta.value == '' || strFilter.test(periodicidadVenta.value)) {
                                errorSelect(periodicidadVenta,"periodicidad invalida");
                            }

                            if (periodNotifVenta.value == '' || strFilter.test(periodNotifVenta.value)) {
                                errorSelect(periodNotifVenta,"notificación invalida");
                            } 
                        }
                    });

                    $(tabClienteClaveServ).on("click","td a#deleteClientServ",function(){
                        var trElimina = $(this).parent("td").parent("tr");
                        alert("index "+trElimina.index());
                        var posicion = $(this).parent("td").parent("tr").index()-1;
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Desea eliminar esta clave?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e){
                                arrayClaveAsignada.splice(posicion,1);
                                arrayClientClave.splice(posicion,1);
                                arrayperiodicidadVenta.splice(posicion,1);
                                arrayperiodNotifVenta.splice(posicion,1);
                                arrayfechaInicioNotifVenta.splice(posicion,1);
                                arrayfechaFinNotifVenta.splice(posicion,1);
                                trElimina.remove();
                                if (arrayClaveAsignada.length == 0 &&
                                    arrayClientClave.length == 0 &&
                                    arrayperiodicidadVenta.length == 0 &&
                                    arrayperiodNotifVenta.length == 0 &&
                                    arrayfechaInicioNotifVenta.length == 0 &&
                                    arrayfechaFinNotifVenta.length == 0) {
                                    $(theadClienteClaveServ).addClass("btnError");
                                    $(trClaveVacia).removeClass("noneView");
                                }
                            }
                        });
                    });
                //registro
                var registraServicio = document.getElementById("registraServicio");

                $(registraServicio).click(function(){
                    var txtClassServicios = document.querySelector('input[name="txtClassServicios"]:checked');
                    cuteAlert({
                        type: "question",
                        title: "Alerta",
                        message: "¿Deseas agregar este servicio?",
                        confirmText: "Si",
                        cancelText: "No"
                    }).then((e)=>{
                        if (e){
                           //txtClassServicios
                            if ((txtClassServicios && txtClassServicios.value != '') && 
                                (catSATServ.value != '' && filtroNum.test(catSATServ.value) && catSATServ.value.length == 8) && 
                                (serv_regCat.value != '' && strFilter.test(serv_regCat.value) && serv_regCat.value.length >= 10) && 
                                (txtUnidadMedida.value != '' && strFilter.test(txtUnidadMedida.value)) &&
                                (txtMonedaServ.value != '' && strFilter.test(txtMonedaServ.value)) &&
                                (tipoCambio.value != '' && filtroCosto.test(tipoCambio.value)) &&
                                (cantidadServ.value != '' && filtroNum.test(cantidadServ.value)) &&
                                (precioBaseServ.value != '' && filtroCosto.test(precioBaseServ.value)) && 
                                (tbodyTotalImp.childNodes.length >= 4) &&
                                (tbodyClaveClienteServ.childNodes.length >= 4) ) {
                                
                                if (fechaAltaServicio.value != '') {
                                    if (filtroFecha.test(fechaAltaServicio.value)) {
                                        registraServicio(fechaAltaServicio.value,txtClassServicios.value,catSATServHidden.value,catSATServ.value,
                                            serv_regCat.value,txtUnidadMedida.value,txtMonedaServ.value,tipoCambio.value,cantidadServ.value,
                                            precioBaseServ.value,arrayImpuestos,arrayClientClave,arrayClaveAsignada);
                                    } else {
                                        errorInput(fechaAltaServicio,"Fecha invalida");
                                    }
                                } else {
                                    registraServicio('-',txtClassServicios.value,catSATServHidden.value,catSATServ.value,
                                            serv_regCat.value,txtUnidadMedida.value,txtMonedaServ.value,tipoCambio.value,cantidadServ.value,
                                            precioBaseServ.value,arrayImpuestos,arrayClientClave,arrayClaveAsignada);
                                }
                            
                                function registraServicio(fecha,clasificacion,tokenSat,catalogoSAT,concepto,medida,moneda,tipoCambio,cantidad,
                                    precio,arrayimpuestos,arrayClientClave,arrayClaveAsignada){
                                    var partData = $(this).closest('formAddServ').serialize();
                                    var data = new FormData();
                                    data.append('data',partData);
                                    data.append('fechaAltaServicio',fecha);
                                    data.append('clasificacion',clasificacion);
                                    data.append('tokenSat',tokenSat); 
                                    data.append('catalogoSAT',catalogoSAT); 
                                    data.append('serv_regCat',concepto); 
                                    data.append('txtUnidadMedida',medida);
                                    data.append('txtMonedaServ',moneda);
                                    data.append('tipoCambio',tipoCambio);
                                    data.append('cantidadServ',cantidad);
                                    data.append('precioBase',precio);
                                    data.append('impuestos',JSON.stringify(arrayimpuestos));
                                    data.append('tokenDescuento',JSON.stringify(arrayTokenDescuentoas));
                                    data.append('aliasDescuento',JSON.stringify(arraYaliasDescuento));
                                    data.append('conceptoDescuento',JSON.stringify(arraYconceptoDescuento));
                                    data.append('selectCotaPorcDescuento',JSON.stringify(arraYselectCotaPorcDescuento));
                                    data.append('cantidadBaseDescuento',JSON.stringify(arraYcantidadBaseDescuento));
                                    data.append('selectTipoDescuento',JSON.stringify(arraYselectTipoDescuento));
                                    data.append('fechaInicioDesc',JSON.stringify(arraYfechaInicioDesc));
                                    data.append('fechaFinDesc',JSON.stringify(arraYfechaFinDesc));
                                    
                                    data.append('tokenPromo',JSON.stringify(arrayTokenPromociones));
                                    data.append('aliasPromo',JSON.stringify(arraYaliasPromocion));
                                    data.append('conceptoPromo',JSON.stringify(arraYconceptoPromocion));
                                    data.append('selectCotaPorcPromo',JSON.stringify(arraYselectCotaPorcPromocion));
                                    data.append('cantidadBasePromo',JSON.stringify(arraYcantidadBasePromocion));
                                    data.append('selectTipoPromo',JSON.stringify(arraYselectTipoPromocion));
                                    data.append('fechaInicioPromo',JSON.stringify(arraYfechaInicioProm));
                                    data.append('fechaFinPromo',JSON.stringify(arraYfechaFinProm));
                                    data.append('clientClave',JSON.stringify(arrayClientClave));
                                    data.append('claveAsignada',JSON.stringify(arrayClaveAsignada));
                                    data.append('periodNotifVenta',JSON.stringify(arrayperiodNotifVenta));
                                    data.append('periodicidadVenta',JSON.stringify(arrayperiodicidadVenta));
                                    data.append('fechaInicioNotifVenta',JSON.stringify(arrayfechaInicioNotifVenta));
                                    data.append('fechaFinNotifVenta',JSON.stringify(arrayfechaFinNotifVenta));
                                    $.ajax({
                                        url: "ingresos-registraservicio",
                                        type: "post",
                                        data: data,
                                        datatype: 'json',
                                        processData: false,
                                        contentType: false,
                                        success: function(respuesta) {
                                            alert(respuesta);
                                            console.log(respuesta);
                                            if (respuesta == 'errorUser') {
                                                var $toastContent = $('<div class="btnError">¡Usuario no autorizado!</ div>');
                                                Materialize.toast($toastContent,5000);  
                                            }

                                            if (respuesta == 'errorfechaAltaServicio') {
                                                var $toastContent = $('<div class="btnError">Fecha invalida</ div>');
                                                theadClasificacion.classList.add("btnError");
                                                errorInput(fechaAltaServicio,"Fecha invalida");
                                            }
                                        
                                            if (respuesta == 'errorClasificacion') {
                                                var $toastContent = $('<div class="btnError">Fecha invalida</ div>');
                                                theadClasificacion.classList.add("btnError");
                                            }
                                        
                                            if (respuesta == 'errorTokenSat') {
                                                var $toastContent = $('<div class="btnError">Código SAT invalido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                errorInput(catSATServ,"Código SAT invalido");
                                            }
                                        
                                            if (respuesta == 'errorCatalogoSAT') {
                                                var $toastContent = $('<div class="btnError">Código SAT invalido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                errorInput(catSATServ,"Código SAT invalido");
                                            }
                                        
                                            if (respuesta == 'errorConcepto') {
                                                var $toastContent = $('<div class="btnError">Concepto / Descripción invalido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                errorInput(serv_regCat,"Concepto / Descripción invalido");
                                            }
                                        
                                            if (respuesta == 'errorUnidadMedida') {
                                                var $toastContent = $('<div class="btnError">Unidad de medida invalido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                errorInput(txtUnidadMedida,"Unidad de medida invalido");
                                            }
                                        
                                            if (respuesta == 'errorMoneda') {
                                                var $toastContent = $('<div class="btnError">Moneda invalida</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                errorInput(txtMonedaServ,"Moneda invalida");
                                            }
                                        
                                            if (respuesta == 'errorTipoCambio') {
                                                var $toastContent = $('<div class="btnError">Tipo de cambio invalido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                errorInput(tipoCambio,"Tipo de cambio invalido");
                                            }
                                        
                                            if (respuesta == 'errorCantidad') {
                                                var $toastContent = $('<div class="btnError">Cantidad invalida</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                errorInput(cantidadServ,"Cantidad invalida");
                                            }
                                        
                                            if (respuesta == 'errorPrecioBase') {
                                                var $toastContent = $('<div class="btnError">Precio base invalido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                errorInput(precioBaseServ,"Precio base invalido");
                                            }
                                        
                                            if (respuesta == 'errorimpuestos') {
                                                var $toastContent = $('<div class="btnError">Lista de impuestos vacia</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                theadTotalImpuestos.classList.add("btnError");
                                            }
                                        
                                            if (respuesta == 'errorTokenDescuento') {
                                                var $toastContent = $('<div class="btnError">Descuento agragado invalidamente</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaDescuentos.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorAliasDescuento') {
                                                var $toastContent = $('<div class="btnError">Alias de descuento no valido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaDescuentos.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorConceptoDescuento') {
                                                var $toastContent = $('<div class="btnError">Concepto de descuento no valido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaDescuentos.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorSelectCotaPorc') {
                                                var $toastContent = $('<div class="btnError">Selección no valida</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaDescuentos.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorCantidadBaseDesc') {
                                                var $toastContent = $('<div class="btnError">Monto de descuento no valido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaDescuentos.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorSelectTipoDesc') {
                                                var $toastContent = $('<div class="btnError">Tipo de descuento no valido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaDescuentos.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorTokenPromocion') {
                                                var $toastContent = $('<div class="btnError">Promoción agragada invalidamente</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaPromocion.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorAliasPromocion') {
                                                var $toastContent = $('<div class="btnError">Alias de promoción no valido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaPromocion.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorConceptoPromocion') {
                                                var $toastContent = $('<div class="btnError">Concepto de promoción no valido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaPromocion.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorSelectCotaPorcPromocion') {
                                                var $toastContent = $('<div class="btnError">Selección no valida</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaPromocion.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorCantidadBasePromocion') {
                                                var $toastContent = $('<div class="btnError">Monto de promoción no valido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaPromocion.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorSelectTipoPromo') {
                                                var $toastContent = $('<div class="btnError">Tipo de promoción no valido</ div>');
                                                Materialize.toast($toastContent,5000);  
                                                astheadAltaPromocion.classList.remove("btnError");
                                            }
                                        
                                            if (respuesta == 'errorClientClave') {
                                                var $toastContent = $('<div class="btnError">Cliente no valido</ div>');
                                                Materialize.toast($toastContent,5000);
                                                theadClienteClaveServ.classList.add("btnError");  
                                            }
                                        
                                            if (respuesta == 'errorClaveAsignada') {
                                                var $toastContent = $('<div class="btnError">Clave no valida</ div>');
                                                Materialize.toast($toastContent,5000);
                                                theadClienteClaveServ.classList.add("btnError");  
                                            }
                                        
                                            for (let r = 0; r < arrayimpuestos.length; r++) {
                                                if(respuesta == 'errorImpuestos'+r){
                                                    toastError('Impuesto invalido');   
                                                    theadTotalImpuestos.classList.add("btnError");
                                                    var numTrCont = r+1;
                                                    var posTrCont = $(tbodyTotalImp).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            }
                                        
                                            if(respuesta == 'errorCountableArraysDescList'){
                                                toastError('Lista de descuentos invalido');   
                                            }
                                        
                                            if(respuesta == 'notFoundData'){
                                                toastError('descuento o promocion no encontrada');  
                                            }
                                        
                                            if(respuesta == 'errorFoundFolio'){
                                                toastError('folio de descuento o promocion no encontrado');  
                                            }
                                        
                                            for (let d1 = 0; d1 < arrayTokenDescuentoas.length; d1++) {
                                                if(respuesta == 'errorTokenDescuento'+d1){
                                                    toastError('descuento invalido');   
                                                    astheadAltaDescuentos.classList.add("btnError");
                                                    var numTrCont = d1+1;
                                                    var posTrCont = $(astbodyAltaDescuentos).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            }

                                            if(respuesta == 'errorCountableArraysDesc'){
                                                toastError('Apellido Paterno de contacto invalido');   
                                                astheadAltaDescuentos.classList.add("btnError");
                                            }
                                        
                                            for (let da = 0; da < arraYaliasDescuento.length; da++) {
                                                if(respuesta == 'errorAliasDescuento'+da){
                                                    toastError('Alias de descuento no valido');   
                                                    astheadAltaDescuentos.classList.add("btnError");
                                                    var numTrCont = da+1;
                                                    var posTrCont = $(astbodyAltaDescuentos).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }

                                                if(respuesta == 'errorConceptoDescuento'+da){
                                                    toastError('Concepto de descuento no valido');   
                                                    astheadAltaDescuentos.classList.add("btnError");
                                                    var numTrCont = da+1;
                                                    var posTrCont = $(astbodyAltaDescuentos).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }

                                                if(respuesta == 'errorSelectCotaPorc'+da){
                                                    toastError('Selección no valida');   
                                                    astheadAltaDescuentos.classList.add("btnError");
                                                    var numTrCont = da+1;
                                                    var posTrCont = $(astbodyAltaDescuentos).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }

                                                if(respuesta == 'errorCantidadBaseDescuento'+da){
                                                    toastError('Monto de descuento no valido');   
                                                    astheadAltaDescuentos.classList.add("btnError");
                                                    var numTrCont = da+1;
                                                    var posTrCont = $(astbodyAltaDescuentos).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }

                                                if(respuesta == 'errorSelectTipoDesc'+da){
                                                    toastError('Tipo de descuento no valido');   
                                                    astheadAltaDescuentos.classList.add("btnError");
                                                    var numTrCont = da+1;
                                                    var posTrCont = $(astbodyAltaDescuentos).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }

                                                if(respuesta == 'errorFechaInicio'+da){
                                                    toastError('Fecha de inicio de descuento invalida');   
                                                    astheadAltaDescuentos.classList.add("btnError");
                                                    var numTrCont = da+1;
                                                    var posTrCont = $(astbodyAltaDescuentos).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }

                                                if(respuesta == 'errorFechaFin'+da){
                                                    toastError('Fecha de fin de descuento invalida');   
                                                    astheadAltaDescuentos.classList.add("btnError");
                                                    var numTrCont = da+1;
                                                    var posTrCont = $(astbodyAltaDescuentos).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            }
                                        
                                            if(respuesta == 'errorCountAFiniDesc'){
                                                toastError('Fecha de inicio invalida');  
                                                astheadAltaDescuentos.classList.add("btnError"); 
                                            }
                                        
                                            if(respuesta == 'errorCountAFfinDesc'){
                                                toastError('Fecha de finalización invalida');
                                                astheadAltaDescuentos.classList.add("btnError"); 
                                            }

                                            if(respuesta == 'errorCountableArraysPromoList'){
                                                toastError('Lista de descuentos invalido');   
                                                astheadAltaPromocion.classList.add("btnError");
                                            }

                                            for (let p1 = 0; p1 < arrayTokenPromociones.length; p1++) {
                                                if(respuesta == 'errorTokenPromocion'+p1){
                                                    toastError('Apellido Paterno de contacto invalido');   
                                                    astheadAltaPromocion.classList.add("btnError");
                                                    var numTrCont = p1+1;
                                                    var posTrCont = $(astbodyAltaPromocion).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            }
                                        
                                            for (let pa = 0; pa < arraYaliasPromocion.length; pa++) {
                                                if(respuesta == 'errorAliasPromocion'+pa){
                                                    toastError('Alias de promocion no valido');   
                                                    astheadAltaPromocion.classList.add("btnError");
                                                    var numTrCont = pa+1;
                                                    var posTrCont = $(astbodyAltaPromocion).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            
                                                if(respuesta == 'errorConceptoPromocion'+pa){
                                                    toastError('Concepto de promoción no valido');    
                                                    astheadAltaPromocion.classList.add("btnError");
                                                    var numTrCont = pa+1;
                                                    var posTrCont = $(astbodyAltaPromocion).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            
                                                if(respuesta == 'errorSelectCotaPorcPromocion'+pa){
                                                    toastError('Selección no valida');    
                                                    astheadAltaPromocion.classList.add("btnError");
                                                    var numTrCont = pa+1;
                                                    var posTrCont = $(astbodyAltaPromocion).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            
                                                if(respuesta == 'errorCantidadBasePromocion'+pa){
                                                    toastError('Monto de promoción no valido');     
                                                    astheadAltaPromocion.classList.add("btnError");
                                                    var numTrCont = pa+1;
                                                    var posTrCont = $(astbodyAltaPromocion).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            
                                                if(respuesta == 'errorSelectTipoProm'+pa){
                                                    toastError('Tipo de promoción no valido');    
                                                    astheadAltaPromocion.classList.add("btnError");
                                                    var numTrCont = pa+1;
                                                    var posTrCont = $(astbodyAltaPromocion).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }                                        
                                            
                                                if(respuesta == 'errorFechaInicioPromo'+pa){
                                                    toastError('Fecha de inicio de promoción invalida'); 
                                                    astheadAltaPromocion.classList.add("btnError");
                                                    var numTrCont = pa+1;
                                                    var posTrCont = $(astbodyAltaPromocion).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            
                                                if(respuesta == 'errorFechaFinPromo'+pa){
                                                    toastError('Fecha de fin de promoción invalida');
                                                    astheadAltaPromocion.classList.add("btnError");
                                                    var numTrCont = pa+1;
                                                    var posTrCont = $(astbodyAltaPromocion).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            }
                                        
                                            if(respuesta == 'errorCountAFiniPromo'){
                                                toastError('Fecha de inicio invalida'); 
                                                astheadAltaPromocion.classList.add("btnError");
                                            }

                                            if(respuesta == 'errorCountAFfinPromo'){
                                                toastError('Fecha de finalización invalida');   
                                                astheadAltaPromocion.classList.add("btnError");
                                            }

                                            if(respuesta == 'errorCountableArraysPromo'){
                                                toastError('Lista de descuentos invalido');   
                                                astheadAltaPromocion.classList.add("btnError");
                                            }
                                        
                                            if (respuesta == 'errorClaveAsignada') {
                                                var $toastContent = $('<div class="btnError">Clave de cliente no valida</ div>');
                                                Materialize.toast($toastContent,5000);
                                                theadClienteClaveServ.classList.add("btnError");  
                                            }

                                            if (respuesta == 'errorPeriodNotifVenta' ||
                                                respuesta == 'errorPeriodicidadVenta' ||
                                                respuesta == 'errorFechaInicioNotifVenta' ||
                                                respuesta == 'errorFechaFinNotifVenta') {
                                                toastError('Notificación / Cliente no valido');   
                                                theadClienteClaveServ.classList.add("btnError");
                                            }


                                            for (let Clave = 0; Clave < arrayClientClave.length; Clave++) {
                                                if(respuesta == 'errorClientClave'+Clave){
                                                    toastError('Cliente no valido');   
                                                    theadClienteClaveServ.classList.add("btnError");
                                                    var numTrCont = Clave+1;
                                                    var posTrCont = $(tbodyClaveClienteServ).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            
                                                if(respuesta == 'errorClaveAsignada'+Clave){
                                                    toastError('Clave de cliente no valida');   
                                                    theadClienteClaveServ.classList.add("btnError");
                                                    var numTrCont = Clave+1;
                                                    var posTrCont = $(tbodyClaveClienteServ).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                                
                                                
                                                if(respuesta == 'errorPeriodNotifVenta'+Clave){
                                                    toastError('Notificacion de Venta no valida');   
                                                    theadClienteClaveServ.classList.add("btnError");
                                                    var numTrCont = Clave+1;
                                                    var posTrCont = $(tbodyClaveClienteServ).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                                
                                                if(respuesta == 'errorPeriodicidadVenta'+Clave){
                                                    toastError('Periodicidad de Venta no valida');   
                                                    theadClienteClaveServ.classList.add("btnError");
                                                    var numTrCont = Clave+1;
                                                    var posTrCont = $(tbodyClaveClienteServ).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                                
                                                if(respuesta == 'errorFechaInicioNotifVenta'+Clave){
                                                    toastError('Inicio de Notificacion de Venta no valida');   
                                                    theadClienteClaveServ.classList.add("btnError");
                                                    var numTrCont = Clave+1;
                                                    var posTrCont = $(tbodyClaveClienteServ).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                                
                                                if(respuesta == 'errorFechaFinNotifVenta'+Clave){
                                                    toastError('Fin de Notificacion de Venta no valida');   
                                                    theadClienteClaveServ.classList.add("btnError");
                                                    var numTrCont = Clave+1;
                                                    var posTrCont = $(tbodyClaveClienteServ).find("tr").eq(numTrCont);
                                                    $(posTrCont).addClass("btnError");
                                                }
                                            }
                                        
                                            if (respuesta == 'dataIguales') {
                                                var $toastContent = $('<div class="btnError">¡Este servicio ya ha sido registrado anteriormente, consulte su catalogo de servicios ó comuniquese con soporte!</             div>');
                                                Materialize.toast($toastContent,5000);  
                                            }
                                            
                                            if (respuesta == 'servExecuted') {
                                                var $toastContent = $('<div class="btnCorrecto">Servicio registrado exitosamente</div>');
                                                Materialize.toast($toastContent,5000); 
                                                location.reload();
                                            }
                                        }
                                    });
                                }  

                            } else {
                                Push.create("Completa todos los campos", {
                                    body: "SOS-México",
                                    icon: "vista/media/adm/errores/logoSOS.png",
                                    timeout: 3000,
                                });
                            
                                if (!txtClassServicios || txtClassServicios.value == '') {
                                    theadClasificacion.classList.add("btnError");
                                }
                            
                                if (catSATServ.value == '' || !filtroNum.test(catSATServ.value) || catSATServ.value.length != 8) {
                                    errorInput(catSATServ,"Código SAT invalido");
                                }
                            
                                if (serv_regCat.value == '' || !strFilter.test(serv_regCat.value) || serv_regCat.value.length < 10) {
                                    errorInput(serv_regCat,"Concepto / Descripción invalido");
                                }
                            
                                if(txtUnidadMedida.value == '' || !strFilter.test(txtUnidadMedida.value)){
                                    errorInput(txtUnidadMedida,"Unidad de medida invalido");
                                }
                            
                                if(txtMonedaServ.value == '' || !strFilter.test(txtMonedaServ.value)){
                                    errorInput(txtMonedaServ,"Moneda invalida");
                                }
                            
                                if (tipoCambio.value == '' || !filtroCosto.test(tipoCambio.value)) {
                                    errorInput(tipoCambio,"Tipo de cambio invalido");
                                }
                            
                                if (cantidadServ.value == '' || !filtroNum.test(cantidadServ.value)) {
                                    errorInput(cantidadServ,"Cantidad invalida");
                                }
                            
                                if (precioBaseServ.value == '' || !filtroCosto.test(precioBaseServ.value)) {
                                    errorInput(precioBaseServ,"Precio base invalido");
                                }
                            
                                if(tbodyTotalImp.childNodes.length < 4){
                                    theadTotalImpuestos.classList.add("btnError");
                                }
                            
                                if(tbodyClaveClienteServ.childNodes.length < 4){
                                    theadClienteClaveServ.classList.add("btnError");
                                }
                            } 
                        } 
                    })
                });

        //Alta de descuentos y promociones
            //descuentos
                //validaciones
                    var btnHeaderDescuento = document.getElementById("btnHeaderDescuento");
                    var tabAltaDescuentos = document.getElementById("tabAltaDescuentos");
                    var theadAltaDescuentos = document.getElementById("theadAltaDescuentos");

                    var aliasDescuento = document.getElementById("aliasDescuento");
                    $(aliasDescuento).keyup(function(){
                        aliasServDesc(this,conceptoDescuento,btnDeleteDescuento);
                    });
                        
                    $(aliasDescuento).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });
                    
                    var conceptoDescuento = document.getElementById("conceptoDescuento");
                    $(conceptoDescuento).keyup(function(){
                        conceptoServDesc(this,selectCotaPorcDescuento);
                    });
                    
                    $(conceptoDescuento).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    var selectCotaPorcDescuento = document.getElementById("selectCotaPorcDescuento");
                    $(selectCotaPorcDescuento).change(function(){
                        cuotaPorcServDesc(this,cantidadBaseDescuento);
                    });

                    var cantidadBaseDescuento = document.getElementById("cantidadBaseDescuento");
                    $(cantidadBaseDescuento).keyup(function(){
                        cantidadBaseKeyUp(selectCotaPorcDescuento,this,selectTipoDescuento);
                    });

                    $(cantidadBaseDescuento).change(function(){
                        changeCantidadBase(selectCotaPorcDescuento,this);
                    });

                    $(cantidadBaseDescuento).bind('keypress',function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which : event.charCode);
                        if (!/^[0-9.,]+$/.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    var selectTipoDescuento = document.getElementById("selectTipoDescuento");
                    $(selectTipoDescuento).change(function(){
                        tipoDescuentoServDesc(this,fechaInicioDesc,fechaFinDesc,btnRegDescuento);
                    });

                    var fechaInicioDesc = document.getElementById("fechaInicioDesc");
                    $(fechaInicioDesc).change(function(){
                        fechaInicioServDesc(selectTipoDescuento,this,fechaFinDesc,btnRegDescuento);
                    });

                    var fechaFinDesc = document.getElementById("fechaFinDesc");
                    $(fechaFinDesc).change(function(){
                        fechaFinServDesc(selectTipoDescuento,this,btnRegDescuento);
                    });

                    var tabListaServiciosDescuentos = document.getElementById("tabListaServiciosDescuentos");
                    var theadListaServiciosDescuentos = document.getElementById("theadListaServiciosDescuentos");
                    var arrayservciosDescuentos = []; 

                    $(tabListaServiciosDescuentos).on("click","tr th input#radioSelectTodoServDesc",function(){
                        arrayservciosDescuentos.splice(0,arrayservciosDescuentos.length);
                        $("#tabListaServiciosDescuentos td input#radioSelectServicioDesc").each(function(){
                            if ($(this).is(':checked')) {
                                console.log(this.value);
                                $(this).removeAttr('checked');
                            } 
                            $(this).attr("checked",true);
                        });  
                    });

                    $(tabListaServiciosDescuentos).on("click","tr td input#radioSelectServicioDesc",function(){
                        if($(this).is(':checked')){
                            if (this.value != '') {
                                arrayservciosDescuentos.push(this.value);
                                theadListaServiciosDescuentos.classList.remove("btnError");
                            } else {
                                theadListaServiciosDescuentos.classList.add("btnError");
                                var trError = $(this).parents("tr");
                                $(trError).addClass("btnError");
                            } 
                        } else {
                            for (let i = 0; i < arrayservciosDescuentos.length; i++) {
                                if (this.value == arrayservciosDescuentos[i]) {
                                    arrayservciosDescuentos.splice(i,1);
                                }
                            }
                        }
                    });

                    var btnRegDescuento = document.getElementById("btnRegDescuento");
                    $(btnRegDescuento).click(function(){
                        var radioSelectServicioDesc = document.querySelector('input[name="radioSelectServicioDesc"]:checked');
                        if ((radioSelectServicioDesc) &&
                            (aliasDescuento.value != '' && strFilter.test(aliasDescuento.value) && aliasDescuento.value.length >= 4) &&
                            (conceptoDescuento.value != '' && strFilter.test(conceptoDescuento.value) && conceptoDescuento.value.length >= 5) &&
                            (selectCotaPorcDescuento.value != '' && strFilter.test(selectCotaPorcDescuento.value)) &&
                            (cantidadBaseDescuento.value != '' && ((selectCotaPorcDescuento.value == 'cuota' && filtroCosto.test(cantidadBaseDescuento.value)) 
                            || (selectCotaPorcDescuento.value == 'porcentaje' && filtroPorc.test(cantidadBaseDescuento.value)))) &&
                            (selectTipoDescuento.value != '' && strFilter.test(selectTipoDescuento.value)) &&
                            (arrayservciosDescuentos.length >= 1)) {

                            if (selectTipoDescuento.value == 'eventual') {
                                regDescuento('-','-');
                            }

                            if (selectTipoDescuento.value == 'pIndeterminado') {
                                if (fechaInicioDesc.value != '' && filtroFecha.test(fechaInicioDesc.value)) {
                                    regDescuento(fechaInicioDesc.value,'-');
                                } else {
                                    fechaInicioDesc.classList.add("error");
                                }
                            }

                            if (selectTipoDescuento.value == 'pDeterminado') {
                                if (fechaInicioDesc.value != '' && filtroFecha.test(fechaInicioDesc.value) && 
                                    fechaFinDesc.value != '' && filtroFecha.test(fechaFinDesc.value)) {
                                    regDescuento(fechaInicioDesc.value,fechaFinDesc.value);
                                } else {
                                    if (fechaInicioDesc.value == '' || !filtroFecha.test(fechaInicioDesc.value)) {
                                        fechaInicioDesc.classList.add("error");
                                    }
                                    if (fechaFinDesc.value == '' || !filtroFecha.test(fechaFinDesc.value)) {
                                        fechaFinDesc.classList.add("error");
                                    }
                                }
                            }

                            function regDescuento(fechaInicioDesc,fechaFinDesc){
                                cuteAlert({
                                    type: "question",
                                    title: "Alerta",
                                    message: "¿Desea registrar este descuento?",
                                    confirmText: "Si",
                                    cancelText: "No"
                                }).then((e)=>{
                                    if (e) {
                                        var partData = $(this).closest('div').serialize();
                                        var data = new FormData();
                                        data.append('data',partData);
                                        data.append('aliasDescuento',aliasDescuento.value);
                                        data.append('conceptoDescuento',conceptoDescuento.value);
                                        data.append('selectCotaPorcDescuento',selectCotaPorcDescuento.value);
                                        data.append('cantidadBaseDescuento',cantidadBaseDescuento.value);
                                        data.append('selectTipoDescuento',selectTipoDescuento.value);
                                        data.append('fechaInicioDesc',fechaInicioDesc);
                                        data.append('fechaFinDesc',fechaFinDesc);
                                        data.append('arrayservciosDescuentos',JSON.stringify(arrayservciosDescuentos));
                                        $.ajax({
                                            url: "ingresos-registrarDescuento",
                                            type: "post",
                                            data: data,
                                            dataType: 'html',
                                            processData: false,
                                            contentType: false,
                                            success: function (respuesta) {
                                                var resarray = respuesta.substring(respuesta.length-1,respuesta.length);
                                                if (respuesta == 'errorUser') {
                                                    toastError("Usuario no autorizado");
                                                }
                                                if (respuesta == 'errorAliasDescuento') {
                                                    toastError('Alias invalido');
                                                    $(aliasDescuento).addClass("error");
                                                }
                                                if (respuesta == 'errorConceptoDescuento') {
                                                    toastError('Concepto invalido');
                                                    $(conceptoDescuento).addClass("error");
                                                }
                                                if (respuesta == 'errorSelectCotaPorc') {
                                                    toastError('Seleccion cuota/porcentaje invalido');
                                                    $(selectCotaPorcDescuento).addClass("error");
                                                }
                                                if (respuesta == 'errorCantidadBaseDesc') {
                                                    toastError('Cantidad base invalida');
                                                    $(cantidadBaseDescuento).addClass("error");
                                                }
                                                if (respuesta == 'errorSelectTipoDesc') {
                                                    toastError('Seleccion tipo descuento invalido');
                                                    $(selectTipoDescuento).addClass("error");
                                                }
                                                if (respuesta == 'errorServicioDescuento'+resarray) {
                                                    toastError('Servicio invalido'); 
                                                    $(theadListaServiciosDescuentos).addClass("btnError"); 
                                                    var masUno = resarray+1;
                                                    var posTr = $("#tbodyListaServiciosDescuentos").find("tr").eq(masUno);
                                                    $(posTr).addClass("btnError");
                                                }
                                                if (respuesta == 'errorArrayservciosDescuentosVacio') {
                                                    toastError('Servicio invalido');
                                                    $(theadListaServiciosDescuentos).addClass("btnError"); 
                                                }
                                                if (respuesta == 'registrado') {
                                                    var $toastContent = $('<div class="btnCorrecto">Descuento registrado</div>');
                                                    Materialize.toast($toastContent,5000);
                                                    location.reload();
                                                }
                                            }
                                        });
                                    } else {
                                        
                                    }
                                })
                            }
                        } else {
                            if (!radioSelectServicioDesc) {
                                theadListaServiciosDescuentos.classList.add("btnError");
                            }
                            if (aliasDescuento.value == '' || !strFilter.test(aliasDescuento.value) || aliasDescuento.value.length < 4) {
                                aliasDescuento.classList.add("error");
                            }

                            if (conceptoDescuento.value == '' || !strFilter.test(conceptoDescuento.value) || conceptoDescuento.value.length < 5) {
                                conceptoDescuento.classList.add("error");
                            }

                            if (selectCotaPorcDescuento.value == '' || !strFilter.test(selectCotaPorcDescuento.value)) {
                                selectCotaPorcDescuento.classList.add("error");
                            }

                            if (cantidadBaseDescuento.value == '' || !filtroCosto.test(cantidadBaseDescuento.value)) {
                                cantidadBaseDescuento.classList.add("error");
                            }

                            if (selectTipoDescuento.value == '' || !strFilter.test(selectTipoDescuento.value)) {
                                selectTipoDescuento.classList.add("error");
                            }

                            if (arrayservciosDescuentos.length == 0) {
                                theadListaServiciosDescuentos.classList.add("btnError");
                            }
                        }
                    });

                    $("#tabInfoDescuentos").on("click","td a#historialDescuento",function(){
                        var tokenDescuento = $(this).parents("tr").find("td").eq(0).html();
                        var porcentajeCarga = 0;
                        var porcentajeDiv = '';
                        var intervalo = setInterval(() => {
                            porcentajeCarga = porcentajeCarga+1;
                            var porcenDiv = porcentajeCarga+'%';
                            $(".h6Carga").html('cargando...'+porcenDiv);
                            $("#progressbarModalDescuento").css('width',porcenDiv);
                            if (porcentajeCarga == 100) {
                                clearInterval(intervalo);
                                setTimeout(function() {
                                    $("#dataModalDescuento").removeClass("noneView");
                                    $("#loadingmodalDescuento").fadeOut("slow");
                                }, 1000);
                            }
                        }, 20);

                        $.ajax({
                            url: "ingresos-modalviewdescuentos",
                            type: "POST",
                            dataType: 'html',
                            data: {tokenDescuento: tokenDescuento}
                        })
                        .done(function(respuesta){
                            $("#dataModalDescuento").html(respuesta);
                        })
                        .fail(function(){
                            console.log("error");
                        })
                    });

                    $("#tabInfoDescuentos").on("click","td a#btnDeleteDescuento",function(){
                        var tokenDescuento = $(this).parents("tr").find("td").eq(0).html();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Desea eliminar este descuento?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e) {
                                $.ajax({
                                    url: "ingresos-eliminadescuento",
                                    type: "post",
                                    data: {tokenDescuento:tokenDescuento,},
                                    dataType: "html",
                                    success: function (respuesta) {
                                        console.log(respuesta);
                                        if (respuesta == 'eliminado') {
                                            toastCorrecto("Descuento eliminado");
                                        } 
                                        if (respuesta == 'noEliminado') {
                                            toastError("Este descuento no se ha eliminado correctamente");
                                        } 
                                    }
                                });
                            }
                        })
                    }); 

                    $("#tabDeletDesc").on("click","td a#btnRestDesc",function(){
                        var tokenDescuento = $(this).parents("tr").find("td").eq(0).html();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Desea restaurar este descuento?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e) {
                                $.ajax({
                                    url: "ingresos-restauradescuento",
                                    type: "post",
                                    data: {tokenDescuento:tokenDescuento,},
                                    dataType: "html",
                                    success: function (respuesta) {
                                        //console.log(respuesta);
                                        if (respuesta == 'restaurado') {
                                            toastCorrecto("Descuento restaurado");
                                        } 
                                        if (respuesta == 'noRestaurado') {
                                            toastError("Este descuento no se ha restaurado correctamente");
                                        } 
                                    }
                                });
                            }
                        })
                    }); 

                    $("#tabDeletDesc").on("click","td a#btnDelPermDesc",function(){
                        var tokenDescuento = $(this).parents("tr").find("td").eq(0).html();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Desea eliminar permanentemente este descuento?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e) {
                                $.ajax({
                                    url: "ingresos-eliminapermdescuento",
                                    type: "post",
                                    data: {tokenDescuento:tokenDescuento,},
                                    dataType: "html",
                                    success: function (respuesta) {
                                        //console.log(respuesta);
                                        if (respuesta == 'eliminadooo') {
                                            toastCorrecto("Descuento eliminado permanentemente");
                                        } 
                                        if (respuesta == 'noRestaurado') {
                                            toastError("Este descuento no se ha eliminado correctamente");
                                        } 
                                    }
                                });
                            }
                        })
                    });

                    var chipDescuento = document.getElementById("chipDescuento");
                    var divEnablePassCheckedDescuento = document.getElementById("divEnablePassCheckedDescuento");
                    var btnVerificaPassDescuento = document.getElementById("btnVerificaPassDescuento");
                    var disabledDescuentoCont = document.getElementById("disabledDescuentoCont");
                    var radioHabilEdiDescuento = document.getElementById("radioHabilEdiDescuento");

                    $("#cierraModalListaServDesc").click(function(){
                        $("#dataModalDescuento").addClass("noneView");
                        $("#loadingmodalDescuento").fadeIn("slow");

                        chipDescuento.classList.remove("chipPass");
                        divEnablePassCheckedDescuento.classList.add("noneView");
                        btnVerificaPassDescuento.classList.add("noneView");
                        disabledDescuentoCont.innerHTML = "Habilitar edición";
                        chipDescuento.classList.remove("btnError");
                        radioHabilEdiDescuento.checked = false;
                    });

            //promociones
                //validaciones
                    var btnHeaderPromocion = document.getElementById("btnHeaderPromocion");
                    var tabAltaPromocion = document.getElementById("tabAltaPromocion");
                    var theadAltaPromocion = document.getElementById("theadAltaPromocion");
                    
                    var aliasPromocion = document.getElementById("aliasPromocion");
                    $(aliasPromocion).keyup(function(){
                        aliasPromo(this,conceptoPromocion,btnDeletePromocion);
                    });
                    function aliasPromo(alias,concepto,btnDelete){
                        if (alias.value === '' || !strFilter.test(alias.value) || alias.value.length < 4) {
                            alias.classList.add("error");
                            $(concepto).attr("disabled",true);
                        } else {
                            $(concepto).removeAttr('disabled');
                            $(btnDelete).removeAttr('disabled');
                            alias.classList.remove("error");
                        }
                    }
                    $(aliasPromocion).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    var conceptoPromocion = document.getElementById("conceptoPromocion");
                    $(conceptoPromocion).keyup(function(){
                        conceptoFuncPromo(this,selectCotaPorcPromocion);
                    });

                    $(conceptoPromocion).bind('keypress', function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which :event.charCode);
                        if (!strFilter.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    var selectCotaPorcPromocion = document.getElementById("selectCotaPorcPromocion");
                    $(selectCotaPorcPromocion).change(function(){
                        cuotaPorcPromo(this,cantidadBasePromocion);
                    });

                    var cantidadBasePromocion = document.getElementById("cantidadBasePromocion");
                    $(cantidadBasePromocion).keyup(function(){
                        cantBasePromoKeyup(selectCotaPorcPromocion,this,selectTipoPromocion);
                    });

                    $(cantidadBasePromocion).change(function(){
                        cantBasePromoChange(selectCotaPorcPromocion,this);
                    });

                    $(cantidadBasePromocion).bind('keypress',function(event){
                        var clave = String.fromCharCode(!event.charCode ? event.which : event.charCode);
                        if (!/^[0-9.,]+$/.test(clave)) {
                            event.preventDefault();
                            return false;
                        }
                    });

                    var selectTipoPromocion = document.getElementById("selectTipoPromocion");
                    $(selectTipoPromocion).change(function(){
                        tipoPromoAlta(this,fechaInicioProm,fechaFinProm,btnRegPromocion);
                    });
                    
                    var fechaInicioProm = document.getElementById("fechaInicioProm");
                    $(fechaInicioProm).change(function(){
                        fechaInicioPromoServ(selectTipoPromocion,this,fechaFinProm,btnRegPromocion);
                    });
                    
                    var fechaFinProm = document.getElementById("fechaFinProm");
                    $(fechaFinProm).change(function(){
                        fechaFinPromoServ(selectTipoPromocion,this,btnRegPromocion);
                    });

                    var tabListaServiciosPromociones = document.getElementById("tabListaServiciosPromociones");
                    var theadListaServiciosPromociones = document.getElementById("theadListaServiciosPromociones");
                    arrayserviciosPromociones = [];

                    $(tabListaServiciosPromociones).on("click","tr th input#radioSelectTotServicioPromo",function(){
                        arrayserviciosPromociones.splice(0,arrayserviciosPromociones.length);
                        $("#tabListaServiciosPromociones td input#radioSelectServicioPromo").each(function(){
                            if ($(this).is(':checked')) {
                                console.log(this.value);
                                $(this).removeAttr('checked');
                            } 
                            $(this).attr("checked",true);
                        });   
                    });

                    $(tabListaServiciosPromociones).on("click","tr td input#radioSelectServicioPromo",function(){
                        if($(this).is(':checked')){
                            if (this.value != '') {
                                arrayserviciosPromociones.push(this.value);
                                theadListaServiciosPromociones.classList.remove("btnError");
                            } else {
                                theadListaServiciosPromociones.classList.add("btnError");
                                var trError = $(this).parents("tr");
                                $(trError).addClass("btnError");
                            } 
                        } else {
                            for (let i = 0; i < arrayserviciosPromociones.length; i++) {
                                if (this.value == arrayserviciosPromociones[i]) {
                                    arrayserviciosPromociones.splice(i,1);
                                }
                            }
                        }
                    });

                    var btnRegPromocion = document.getElementById("btnRegPromocion");
                    $(btnRegPromocion).click(function(){
                        var radioSelectServicioPromo = document.querySelector('input[name="radioSelectServicioPromo"]:checked');
                        if ((radioSelectServicioPromo) &&
                            (aliasPromocion.value != '' && strFilter.test(aliasPromocion.value) && aliasPromocion.value.length >= 4) &&
                            (conceptoPromocion.value != '' && strFilter.test(conceptoPromocion.value) && conceptoPromocion.value.length >= 5) &&
                            (selectCotaPorcPromocion.value != '' && strFilter.test(selectCotaPorcPromocion.value)) &&
                            (cantidadBasePromocion.value != '' && ((selectCotaPorcPromocion.value == 'cuota' && filtroCosto.test(cantidadBasePromocion.value)) 
                            || (selectCotaPorcPromocion.value == 'porcentaje' && filtroPorc.test(cantidadBasePromocion.value)))) &&
                            (selectTipoPromocion.value != '' && strFilter.test(selectTipoPromocion.value)) &&
                            (arrayserviciosPromociones.length >=1)) {
                            
                            if (selectTipoPromocion.value == 'eventual') {
                                regPromocion('-','-');
                            }
    
                            if (selectTipoPromocion.value == 'pIndeterminado') {
                                if (fechaInicioProm.value != '' && filtroFecha.test(fechaInicioProm.value)) {
                                    regPromocion(fechaInicioProm.value,'-');
                                } else {
                                    fechaInicioProm.classList.add("error");
                                }
                            }
    
                            if (selectTipoPromocion.value == 'pDeterminado') {
                                if (fechaInicioProm.value != '' && filtroFecha.test(fechaInicioProm.value) && 
                                    fechaFinProm.value != '' && filtroFecha.test(fechaFinProm.value)) {
                                    regPromocion(fechaInicioProm.value,fechaFinProm.value);
                                } else {
                                    if (fechaInicioProm.value == '' || !filtroFecha.test(fechaInicioProm.value)) {
                                        fechaInicioProm.classList.add("error");
                                    }
                                    if (fechaFinProm.value == '' || !filtroFecha.test(fechaFinProm.value)) {
                                        fechaFinProm.classList.add("error");
                                    }
                                }
                            }

                            function regPromocion(fechaInicioProm,fechaFinProm){
                                cuteAlert({
                                    type: "question",
                                    title: "Alerta",
                                    message: "¿Desea registrar esta promoción?",
                                    confirmText: "Si",
                                    cancelText: "No"
                                }).then((e)=>{
                                    if (e) {
                                        var partData = $(this).closest('div').serialize();
                                        var data = new FormData();
                                        data.append('data',partData);
                                        data.append('aliasPromocion',aliasPromocion.value);
                                        data.append('conceptoPromocion',conceptoPromocion.value);
                                        data.append('selectCotaPorcPromocion',selectCotaPorcPromocion.value);
                                        data.append('cantidadBasePromocion',cantidadBasePromocion.value);
                                        data.append('selectTipoPromocion',selectTipoPromocion.value);
                                        data.append('fechaInicioProm',fechaInicioProm);
                                        data.append('fechaFinProm',fechaFinProm);
                                        data.append('arrayserviciosPromociones',JSON.stringify(arrayserviciosPromociones));
                                        $.ajax({
                                            url: "ingresos-registrarpromociones",
                                            type: "post",
                                            data: data,
                                            dataType: 'html',
                                            processData: false,
                                            contentType: false,
                                            success: function (respuesta) {
                                                alert(respuesta);
                                                console.log(respuesta);
                                                var ressarray = respuesta.substring(respuesta.length-1,respuesta.length);
                                                if (respuesta == 'errorUser') {
                                                    toastError('Usuario no autorizado');
                                                }
                                                if (respuesta == 'errorAliasPromocion') {
                                                    toastError('Alias invalido');
                                                    $(aliasPromocion).addClass("error");
                                                }
                                                if (respuesta == 'errorConceptoPromocion') {
                                                    toastError('Concepto invalido');
                                                    $(conceptoPromocion).addClass("error");
                                                }
                                                if (respuesta == 'errorSelectCotaPorcPromocion') {
                                                    toastError('Seleccion cuota/porcentaje invalido');
                                                    $(selectCotaPorcPromocion).addClass("error");
                                                }
                                                if (respuesta == 'errorCantidadBasePromocion') {
                                                    toastError('Cantidad base invalida');
                                                    $(cantidadBasePromocion).addClass("error");
                                                }
                                                if (respuesta == 'errorSelectTipoProm') {
                                                    toastError('Seleccion tipo de promocion invalido');
                                                    $(selectTipoPromocion).addClass("error");
                                                }
                                                if (respuesta == 'errorServicioPromocion') {
                                                    toastError('Servicio invalido');
                                                    $(theadListaServiciosPromociones).addClass("btnError");
                                                    var masUno = ressarray+1;
                                                    var posTr = $("#tbodyListaServiciosPromociones").find("tr").eq(masUno);
                                                    $(posTr).addClass("btnError");
                                                }
                                                if (respuesta == 'errorArrayservciosPromocionVacio') {
                                                    toastError('Servicio invalido');
                                                    $(theadListaServiciosPromociones).addClass("btnError");
                                                }
                                                if (respuesta == 'registrado') {
                                                    var $toastContent = $('<div class="btnCorrecto">Promoción registrada</div>');
                                                    Materialize.toast($toastContent,5000);
                                                    location.reload();
                                                }
                                            }
                                        });
                                    } else {
                                        
                                    }
                                })
                            }
                        } else {
                            if (!radioSelectServicioPromo) {
                                theadListaServiciosPromociones.classList.add("btnError");
                            }
                            if (aliasPromocion.value == '' || !strFilter.test(aliasPromocion.value) || aliasPromocion.value.length < 4) {
                                aliasPromocion.classList.add("error");
                            }

                            if (conceptoPromocion.value == '' || !strFilter.test(conceptoPromocion.value) || conceptoPromocion.value.length < 5) {
                                conceptoPromocion.classList.add("error");
                            }

                            if (selectCotaPorcPromocion.value == '' || !strFilter.test(selectCotaPorcPromocion.value)) {
                                selectCotaPorcPromocion.classList.add("error");
                            }

                            if (cantidadBasePromocion.value == '' || !filtroCosto.test(cantidadBasePromocion.value)) {
                                cantidadBasePromocion.classList.add("error");
                            }

                            if (selectTipoPromocion.value == '' || !strFilter.test(selectTipoPromocion.value)) {
                                selectTipoPromocion.classList.add("error");
                            } 

                            if (arrayserviciosPromociones.length == 0) {
                                theadListaServiciosPromociones.classList.add("btnError");
                            }
                        }
                    });

                    $("#tabInfoPromocion").on("click","td a#historialPromocion",function(){
                        var tokenPromociones = $(this).parents("tr").find("td").eq(0).html();
                        var porcentajeCarga = 0;
                        var porcentajeDiv = '';
                        var intervalo = setInterval(() => {
                            porcentajeCarga = porcentajeCarga+1;
                            var porcenDiv = porcentajeCarga+'%';
                            $(".h6Carga").html('cargando...'+porcenDiv);
                            $("#progressbarModalPromo").css('width',porcenDiv);
                            if (porcentajeCarga == 100) {
                                clearInterval(intervalo);
                                setTimeout(function() {
                                    $("#dataModalPromo").removeClass("noneView");
                                    $("#loadingmodalPromo").fadeOut("slow");
                                }, 1000);
                            }
                        }, 20);

                        $.ajax({
                            url: "ingresos-modalviewpromociones",
                            type: "POST",
                            dataType: 'html',
                            data: {tokenPromociones: tokenPromociones}
                        })
                        .done(function(respuesta){
                            $("#dataModalPromo").html(respuesta);
                        })
                        .fail(function(){
                            console.log("error");
                        })
                    });

                    $("#tabInfoPromocion").on("click","td a#btnDeletePromocion",function(){
                        var tokenPromocion = $(this).parents("tr").find("td").eq(0).html();
                        alert(tokenPromocion);
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Desea eliminar esta promoción?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e) {
                                $.ajax({
                                    url: "ingresos-eliminacionpromo",
                                    type: "post",
                                    data: {tokenPromo:tokenPromocion},
                                    dataType: "html",
                                    success: function (respuesta) {
                                        //console.log(respuesta);
                                        if (respuesta == 'eliminado') {
                                            toastCorrecto("Promoción eliminada");
                                        } 
                                        if (respuesta == 'noEliminado') {
                                            toastError("Esta promoción no se ha eliminado correctamente");
                                        } 
                                    }
                                });
                            }
                        })
                    }); 

                    $("#tabDeletPromo").on("click","td a#btnRestPromo",function(){
                        var tokenPromocion = $(this).parents("tr").find("td").eq(0).html();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Desea restaurar esta promoción?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e) {
                                $.ajax({
                                    url: "ingresos-restaurarpromo",
                                    type: "post",
                                    data: {tokenPromo:tokenPromocion},
                                    dataType: "html",
                                    success: function (respuesta) {
                                        //console.log(respuesta);
                                        if (respuesta == 'restaurado') {
                                            toastCorrecto("Peomoción restaurada");
                                        } 
                                        if (respuesta == 'noRestaurado') {
                                            toastError("esta promoción no se ha restaurado correctamente");
                                        } 
                                    }
                                });
                            }
                        })
                    }); 

                    $("#tabDeletPromo").on("click","td a#btnDelPermPromo",function(){
                        var tokenPromocion = $(this).parents("tr").find("td").eq(0).html();
                        cuteAlert({
                            type: "question",
                            title: "Alerta",
                            message: "¿Desea eliminar permanentemente esta promoción?",
                            confirmText: "Si",
                            cancelText: "No"
                        }).then((e)=>{
                            if (e) {
                                $.ajax({
                                    url: "ingresos-eliminarpermanentpromo",
                                    type: "post",
                                    data: {tokenPromo:tokenPromocion},
                                    dataType: "html",
                                    success: function (respuesta) {
                                        //console.log(respuesta);
                                        if (respuesta == 'eliminadooo') {
                                            toastCorrecto("Promoción eliminada permanentemente");
                                        } 
                                        if (respuesta == 'noRestaurado') {
                                            toastError("esta promoción no se ha eliminado correctamente");
                                        } 
                                    }
                                });
                            }
                        })
                    });

                    var chipPromo = document.getElementById("chipPromo");
                    var divEnablePassCheckedPromo = document.getElementById("divEnablePassCheckedPromo");
                    var btnVerificaPassPromo = document.getElementById("btnVerificaPassPromo");
                    var disabledPromoCont = document.getElementById("disabledPromoCont");
                    var radioHabilEdiPromo = document.getElementById("radioHabilEdiPromo");

                    $("#cierraModalListaServDesc").click(function(){
                        $("#dataModalPromo").addClass("noneView");
                        $("#loadingmodalPromo").fadeIn("slow");

                        chipPromo.classList.remove("chipPass");
                        divEnablePassCheckedPromo.classList.add("noneView");
                        btnVerificaPassPromo.classList.add("noneView");
                        disabledPromoCont.innerHTML = "Habilitar edición";
                        chipPromo.classList.remove("btnError");
                        radioHabilEdiPromo.checked = false;
                    });

});