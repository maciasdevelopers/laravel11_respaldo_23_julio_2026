$('select').material_select();
$('.tooltipped').tooltip();
$('.slider').slider();
$('.datepicker').pickadate({
    format: 'dd-mm-yyyy',
    monthsFull: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
    monthsShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
    weekdaysFull: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
    weekdaysShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
    selectMonths: true,
    selectYears: 100, // Puedes cambiarlo para mostrar más o menos años
    today: 'Hoy',
    clear: 'Limpiar',
    close: 'Ok',
    labelMonthNext: 'Siguiente mes',
    labelMonthPrev: 'Mes anterior',
    labelMonthSelect: 'Selecciona un mes',
    labelYearSelect: 'Selecciona un año',
});
$('.collapsible').collapsible();
$('.tabs').tabs();

//funciones
    function buscaTablasHtml(datoBusqueda,tbody,trVacio){
        trVacio.addClass("noneView");
        var valor = datoBusqueda.value.toLowerCase();
        let total = 0;
        console.log("hijos tbody "+tbody.rows.length+" "+valor);

        for (let i = 1; i < tbody.rows.length; i++) {
            console.log(tbody.rows[i]);
            let found = false;
            var celdasXtr = tbody.rows[i].getElementsByClassName("tdView");
            for (let j = 0; j < celdasXtr.length; j++) {
                const comparacion = celdasXtr[j].innerHTML.toLowerCase();
                console.log(comparacion.indexOf(valor));
                if (valor.length == 0 || comparacion.indexOf(valor) > -1) {
                    found = true;
                    total++;
                } 
            }
            console.log("found "+found);
            if (found) {
                tbody.rows[i].classList.remove("noneView");
            } else {
                tbody.rows[i].classList.add("noneView");
            }
        }

        if (total != 0) {
            trVacio.addClass("noneView");
        } else {
            //tbody.rows.classList.add("noneView");
            for (let i = 1; i < tbody.rows.length; i++) {
                console.log(tbody.rows[i]);
                tbody.rows[i].classList.add("noneView");
            }
            trVacio.removeClass("noneView");
        }
    } 

    //toast
        function toastError(texto){
            var $toastContent = $('<div class="btnError">'+texto+'</div>');
            Materialize.toast($toastContent,5000);    
        }

    //input
        function borraInputRow(valor){
            valor.classList.remove("error");
            valor.classList.remove("correcto");
            valor.value = '';
        };

        function addlblDisabled(input){
            var label = $(input).parent(".input-field").find("label");
            label.addClass("disabled");
        }

        function quitalblDisabled(input){
            var label = $(input).parent(".input-field").find("label");
            label.removeClass("disabled");
        }

        function correctoInput(valor,mensaje){
            var divParent = valor.parentElement;
            var correctlbl = divParent.querySelector('label');
                correctlbl.className = "activeInput";
                correctlbl.innerText = mensaje;
        };

        function correctoInputMail(valor){
            var divParent = valor.parentElement;
            var errorlbl = divParent.querySelector('label');
                errorlbl.className = "activeInput correcto";
        };

        function errorInput(valor,mensaje){
            var divParent = valor.parentElement;
            var errorlbl = divParent.querySelector('label');
                errorlbl.className = "activeInput errorlabel";
                errorlbl.innerText = mensaje;
        };

        function correctoInputRow(valor){
            valor.classList.remove("error");
            valor.classList.add("correcto");
        };

        function errorInputRow(valor){
            valor.classList.remove("correcto");
            valor.classList.add("error");
        };

    //select
        function errorSelect(valor,mensaje){
            var divParent = valor.parentElement.parentElement;
            var errorlbl = divParent.querySelector('label');
                errorlbl.className = "activeSelect errorlabel";
                errorlbl.innerText = mensaje;
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

    //validaciones numericas
        function soloNumeros(e){
            var key = e.charCode;
            console.log(key);
            return key >= 48 && key <= 57;
        };
    

    //funciones especiales para servicios
        function aliasServDesc(alias,concepto,btnDelete){
            if (alias.value === '' || !strFilter.test(alias.value) || alias.value.length < 4) {
                alias.classList.add("error");
                $(concepto).attr("disabled",true);
            } else {
                $(concepto).removeAttr('disabled');
                $(btnDelete).removeAttr('disabled');
                alias.classList.remove("error");
            }
        }

        function conceptoServDesc(concepto,cuotaPorc){
            if (concepto.value == '' || !strFilter.test(concepto.value) || concepto.value.length < 5) {
                concepto.classList.add("error");
                $(cuotaPorc).attr("disabled",true);
                $(cuotaPorc).material_select();
            } else {
                $(cuotaPorc).removeAttr('disabled');
                $(cuotaPorc).material_select();
                concepto.classList.remove("error");
            }
        }

        function cuotaPorcServDesc(selectCuota,cantidadBase){
            cantidadBase.value = '';
            var inpSlect = $(selectCuota).parent("div").find("input.select-dropdown");
            if (selectCuota.value == '' || !strFilter.test(selectCuota.value)) {
                inpSlect.addClass("error");
                $(cantidadBase).attr("disabled",true);
            } else {
                inpSlect.removeClass("error");
                $(cantidadBase).removeAttr('disabled');
            }
        }

        function cantidadBaseKeyUp(selectCuota,cantidadBase,tipoDescuento){
            if (selectCuota.value == 'cuota') {
                if (cantidadBase.value == '' || !filtroCosto.test(cantidadBase.value)) {
                    cantidadBase.classList.add("error");
                    $(tipoDescuento).attr("disabled",true);
                    $(tipoDescuento).material_select();
                } else {
                    $(tipoDescuento).removeAttr('disabled');
                    cantidadBase.classList.remove("error");
                    $(tipoDescuento).material_select();
                }
            }

            if (selectCuota.value == 'porcentaje') {
                if (cantidadBase.value == '' || !filtroPorc.test(cantidadBase.value)) {
                    cantidadBase.classList.add("error");
                    $(tipoDescuento).attr("disabled",true);
                    $(tipoDescuento).material_select();
                } else {
                    cantidadBase.classList.remove("error");
                    $(tipoDescuento).removeAttr('disabled');
                    $(tipoDescuento).material_select();
                }
            }
        }

        function changeCantidadBase(selectCuota,cantidadBase){
            if (selectCuota.value == 'porcentaje') {
                if (cantidadBase.value === '' || !filtroPorc.test(cantidadBase.value)) {
                    cantidadBase.classList.add("error");
                } else {
                    cantidadBase.classList.remove("error");
                    cantidadBase.value = cantidadBase.value+"%";
                }
            }

            if (selectCuota.value == 'cuota') {
                if (cantidadBase.value === '' || !filtroCosto.test(cantidadBase.value)) {
                    cantidadBase.classList.add("error");
                } else {
                    cantidadBase.classList.remove("error");
                    var nuMoneda = numeral(cantidadBase.value);
                    cantidadBase.value = nuMoneda.format('$0,0.00');
                    console.log(nuMoneda.format('$0,0.00'));
                }
            }
        }

        function tipoDescuentoServDesc(tipoDescuento,fechaInicio,fechaFin,btnRegistro){
            fechaInicio.value = '';
            fechaFin.value = '';
            if (tipoDescuento.value === 'eventual' || !strFilter.test(tipoDescuento.value)) {
                $(fechaInicio).attr("disabled",true);
                $(fechaFin).attr("disabled",true);
                $(btnRegistro).removeAttr('disabled'); 
            } else if(tipoDescuento.value === 'pIndeterminado' || !strFilter.test(tipoDescuento.value)){
                $(fechaInicio).removeAttr("disabled");
                $(fechaFin).attr("disabled",true);
                $(btnRegistro).attr("disabled",true);
            } else if(tipoDescuento.value === 'pDeterminado' || !strFilter.test(tipoDescuento.value)){
                $(fechaInicio).removeAttr("disabled");
                $(fechaFin).attr("disabled",true);
                $(btnRegistro).attr("disabled",true);
            }
        }

        function fechaInicioServDesc(tipoDescuento,fechaInicio,fechaFin,btnRegistro){
            if (fechaInicio.value == '' || !filtroFecha.test(fechaInicio.value)) {
                fechaInicio.classList.add("error");
            } else {
                fechaInicio.classList.remove("error");
                if (tipoDescuento.value === 'eventual' || !strFilter.test(tipoDescuento.value)) {
                    $(fechaInicio).attr("disabled",true);
                    fechaInicio.value = '';
                    $(btnRegistro).removeAttr('disabled'); 
                } else if(tipoDescuento.value === 'pIndeterminado' || !strFilter.test(tipoDescuento.value)){
                    $(fechaFin).attr("disabled",true);
                    $(btnRegistro).removeAttr('disabled');
                } else if(tipoDescuento.value === 'pDeterminado' || !strFilter.test(tipoDescuento.value)){
                    $(fechaFin).removeAttr("disabled");
                    $(btnRegistro).attr("disabled",true);
                }
            }
        }

        function fechaFinServDesc(tipoDescuento,fechaFin,btnRegistro){
            if (fechaFin.value == '' || !filtroFecha.test(fechaFin.value)) {
                fechaFin.classList.add("error");
            } else {
                fechaFin.classList.remove("error");
                if (tipoDescuento.value === 'eventual' || !strFilter.test(tipoDescuento.value)) {
                    $(fechaFin).attr("disabled",true);
                    fechaFin.value = '';
                    $(btnRegistro).removeAttr('disabled'); 
                } else if(tipoDescuento.value === 'pIndeterminado' || !strFilter.test(tipoDescuento.value)){
                    $(fechaFin).attr("disabled",true);
                    fechaFin.value = '';
                    $(btnRegistro).removeAttr('disabled');
                } else if(tipoDescuento.value === 'pDeterminado' || !strFilter.test(tipoDescuento.value)){
                    $(btnRegistro).removeAttr('disabled');
                }
            }
        }

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

        function conceptoFuncPromo(concepto,cuotaPorc){
            if (concepto.value == '' || !strFilter.test(concepto.value) || concepto.value.length < 5) {
                concepto.classList.add("error");
                $(cuotaPorc).attr("disabled",true);
                $(cuotaPorc).material_select();
            } else {
                concepto.classList.remove("error");
                $(cuotaPorc).removeAttr('disabled');
                $(cuotaPorc).material_select();
            }
        }

        function cuotaPorcPromo(cuotaPorc,cantidadBase){
            cantidadBase.value = '';
            var inpSlect = $(cuotaPorc).parent("div").find("input.select-dropdown");
            if (cuotaPorc.value == '' || !strFilter.test(cuotaPorc.value)) {
                inpSlect.addClass("error");
                $(cantidadBase).attr("disabled",true);
            } else {
                inpSlect.removeClass("error");
                $(cantidadBase).removeAttr('disabled');
            }
        }

        function cantBasePromoKeyup(cuotaPorc,cantidadBase,tipoPromocion){
            if (cuotaPorc.value == 'cuota') {
                if (cantidadBase.value == '' || !filtroCosto.test(cantidadBase.value)) {
                    cantidadBase.classList.add("error");
                    $(tipoPromocion).attr("disabled",true);
                    $(tipoPromocion).material_select();
                } else {
                    $(tipoPromocion).removeAttr('disabled');
                    cantidadBase.classList.remove("error");
                    $(tipoPromocion).material_select();
                }
            }
            
            if (cuotaPorc.value == 'porcentaje') {
                if (cantidadBase.value == '' || !filtroPorc.test(cantidadBase.value)) {
                    cantidadBase.classList.add("error");
                    $(tipoPromocion).attr("disabled",true);
                    $(tipoPromocion).material_select();
                } else {
                    cantidadBase.classList.remove("error");
                    $(tipoPromocion).removeAttr('disabled');
                    $(tipoPromocion).material_select();
                }
            }
        }

        function cantBasePromoChange(cuotaPorc,cantidadBase){
            if (cuotaPorc.value == 'porcentaje') {
                if (cantidadBase.value === '' || !filtroPorc.test(cantidadBase.value)) {
                    cantidadBase.classList.add("error");
                } else {
                    cantidadBase.classList.remove("error");
                    cantidadBase.value = cantidadBase.value+"%";
                }
            }
        
            if (cuotaPorc.value == 'cuota') {
                if (cantidadBase.value === '' || !filtroCosto.test(cantidadBase.value)) {
                    cantidadBase.classList.add("error");
                } else {
                    cantidadBase.classList.remove("error");
                    var nuMoneda = numeral(cantidadBase.value);
                    cantidadBase.value = nuMoneda.format('$0,0.00');
                    console.log(nuMoneda.format('$0,0.00'));
                }
            }
        }

        function tipoPromoAlta(tipoPromocion,fechaInicio,fechaFin,btnRegistro){
            fechaInicio.value = '';
            fechaFin.value = '';
            //alert(fechaInicio.value+" "+fechaFin.value);
            if (tipoPromocion.value === 'eventual' || !strFilter.test(tipoPromocion.value)) {
                $(fechaInicio).attr("disabled",true);
                $(fechaFin).attr("disabled",true);
                $(btnRegistro).removeAttr('disabled'); 
            } else if(tipoPromocion.value === 'pIndeterminado' || !strFilter.test(tipoPromocion.value)){
                $(fechaInicio).removeAttr("disabled");
                $(fechaFin).attr("disabled",true);
                $(btnRegistro).attr("disabled",true);
            } else if(tipoPromocion.value === 'pDeterminado' || !strFilter.test(tipoPromocion.value)){
                $(fechaInicio).removeAttr("disabled");
                $(fechaFin).attr("disabled",true);
                $(btnRegistro).attr("disabled",true);
            }
        }

        function fechaInicioPromoServ(tipoPromocion,fechaInicio,fechaFin,btnRegistro){
            if (fechaInicio.value == '' || !filtroFecha.test(fechaInicio.value)) {
                fechaInicio.classList.add("error");
            } else {
                fechaInicio.classList.remove("error");
                if (tipoPromocion.value === 'eventual' || !strFilter.test(tipoPromocion.value)) {
                    $(fechaInicio).attr("disabled",true);
                    fechaInicio.value = '';
                    $(btnRegistro).removeAttr('disabled'); 
                } else if(tipoPromocion.value === 'pIndeterminado' || !strFilter.test(tipoPromocion.value)){
                    $(fechaFin).attr("disabled",true);
                    $(btnRegistro).removeAttr('disabled');
                } else if(tipoPromocion.value === 'pDeterminado' || !strFilter.test(tipoPromocion.value)){
                    $(fechaFin).removeAttr("disabled");
                    $(btnRegistro).attr("disabled",true);
                }
            }
        }

        function fechaInicioPromoValServ(tipoPromocion,fechaInicio,fechaFin,btnRegistro){
            alert(tipoPromocion.val());
            if (fechaInicio.value == '' || !filtroFecha.test(fechaInicio.value)) {
                fechaInicio.classList.add("error");
            } else {
                fechaInicio.classList.remove("error");
                if (tipoPromocion.val() === 'eventual' || !strFilter.test(tipoPromocion.val())) {
                    $(fechaInicio).attr("disabled",true);
                    fechaInicio.value = '';
                    $(btnRegistro).removeAttr('disabled'); 
                } else if(tipoPromocion.val() === 'pIndeterminado' || !strFilter.test(tipoPromocion.val())){
                    $(fechaFin).attr("disabled",true);
                    $(btnRegistro).removeAttr('disabled');
                } else if(tipoPromocion.val() === 'pDeterminado' || !strFilter.test(tipoPromocion.val())){
                    $(fechaFin).removeAttr("disabled");
                    $(btnRegistro).attr("disabled",true);
                }
            }
        }

        function fechaFinPromoServ(tipoPromocion,fechaFin,btnRegistro){
            if (fechaFin.value == '' || !filtroFecha.test(fechaFin.value)) {
                fechaFin.classList.add("error");
            } else {
                fechaFin.classList.remove("error");
                if (tipoPromocion.value === 'eventual' || !strFilter.test(tipoPromocion.value)) {
                    $(fechaFin).attr("disabled",true);
                    fechaFin.value = '';
                    $(btnRegistro).removeAttr('disabled'); 
                } else if(tipoPromocion.value === 'pIndeterminado' || !strFilter.test(tipoPromocion.value)){
                    $(fechaFin).attr("disabled",true);
                    fechaFin.value = '';
                    $(btnRegistro).removeAttr('disabled');
                } else if(tipoPromocion.value === 'pDeterminado' || !strFilter.test(tipoPromocion.value)){
                    $(btnRegistro).removeAttr('disabled');
                }
            }
        }

        function fechaFinPromoValServ(tipoPromocion,fechaFin,btnRegistro){
            if (fechaFin.value == '' || !filtroFecha.test(fechaFin.value)) {
                fechaFin.classList.add("error");
            } else {
                fechaFin.classList.remove("error");
                if (tipoPromocion.val() === 'eventual' || !strFilter.test(tipoPromocion.val())) {
                    $(fechaFin).attr("disabled",true);
                    fechaFin.value = '';
                    $(btnRegistro).removeAttr('disabled'); 
                } else if(tipoPromocion.val() === 'pIndeterminado' || !strFilter.test(tipoPromocion.val())){
                    $(fechaFin).attr("disabled",true);
                    fechaFin.value = '';
                    $(btnRegistro).removeAttr('disabled');
                } else if(tipoPromocion.val() === 'pDeterminado' || !strFilter.test(tipoPromocion.val())){
                    $(btnRegistro).removeAttr('disabled');
                }
            }
        }

    //clientes
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
                        if (splitCorreo[0].length <= 64 && splitCorreo[1].length <= 255  && 
                            (splitCorreo[1].includes('gmail.com') || splitCorreo[1].includes('hotmail.com') || 
                            splitCorreo[1].includes('outlook.com') || splitCorreo[1].includes('yahoo.com'))) {
                            correctoInput(valor,'Email');
                            correctoInputMail(valor);
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
                    if (valor.value.length !=10) {
                        errorInput(valor,'Número de caracteres invalido');
                    } else {
                        correctoInput(valor,'Teléfono'); 
                    }
                }
            }
        }

//expresiones regulares 
    var media = window.matchMedia("(max-width: 400px)");
    var strFilterPass = /^[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ0-9,;.:-_/*+]*$/;
    var filtroRfc = /^[A-Za-z0-9]*$/; 
    var filtroUrl = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/; 
    var strFilter = /^[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ0-9.,:-]*$/;
    var filtroClave = /^[A-Za-z0-9ƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ-]*$/;
    var filtroLetras = /^[A-Za-zƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:()]*$/;
    var filtroDom = /^[A-Za-z0-9ƒŠŒŽšœžŸÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèé êëìíîïðñòóôõöøùúûüýþÿ.,;:-]*$/; 
    var filtroDomNum = /^[A-Za-z0-9 .,-/]*$/; 
    var correoRegex = /^(([^<>()[\]\.,;:\s@\"]+(\.[^<>()[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
    var filtroFecha = /^\d{1,2}\-\d{1,2}\-\d{2,4}$/;
    var filtroCosto  = /^[0-9.,$]*$/;
    var filtroNum = /^[0-9]*$/;
    var filtroPorc= /^[0-9.%]*$/;
    var filtroClasificacion = /^[0-9-]*$/;

//funciones extras
    function rfcInexistente(){
        Push.create("NO EXISTE RFC", {
            body: "SOS-México",
            icon: "vista/media/landing/logotipo/314g.jpg",
            timeout: 3000,
        });
    };

    function fechaInvalida() {
        Push.create("TU FECHA DE NACIMIENTO CON COINCIDE CON TU RFC", {
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

    function solicitudEnviada(){
        Push.create("SOLICITUD ENVIADA", {
            body: "SOS-México",
            icon: "vista/media/landing/logotipo/314g.jpg",
            timeout: 3000,
        });
    };

    function camposVacios(){
        Push.create("COMPLETA LOS CAMPOS VACIOS", {
            body: "SOS-México",
            icon: "vista/media/landing/logotipo/314g.jpg",
            timeout: 3000,
        });
    };


