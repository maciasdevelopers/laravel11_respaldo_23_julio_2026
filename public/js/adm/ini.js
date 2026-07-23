$(document).ready(function(){
    $('.parallax').parallax();
    $('.slider').slider();
    $('.modal').modal();
    $('.tabs').tabs();
    $('select').material_select();
    $('.collapsible').collapsible();
    $(".button-collapse").sideNav();
    $('.tooltipped').tooltip();
    /*$('.carousel.carousel-slider').carousel({
        fullWidth: true,
        indicators: true
    });*/
    $('.scrollspy').scrollSpy();
    $('.dropdown-trigger').dropdown();
    //$('.fixed-action-btn').floatingActionButton();

    window.addEventListener('load', cargando,false);
    function cargando(){
        var porcentajeCarga = 0;
        var porcenDiv = '';

        var intervalo = setInterval(() => {
            porcentajeCarga = porcentajeCarga+1;
            var porcenDiv = porcentajeCarga+'%';
            $(".h6Carga").html('cargando... '+porcenDiv); 
            $("#progressbar").css('width',porcenDiv);
            if (porcentajeCarga == 100) {
                clearInterval(intervalo);
                setTimeout(function() {
                    $("#loadingpage").fadeOut("slow");
                }, 1000); // WAIT 5 milliseconds
            }
        }, 30);

        function clearCarga(){
            console.log("carga "+porcentajeCarga);
            clearInterval();
        }
    }
    
    function todoAjaxIniMenu(){
        //var resolvedOptions = Intl.DateTimeFormat().resolvedOptions()
        //console.log('El nombre de tu zona horaria es ', resolvedOptions.timeZone);
        $.when($.ajax({url: 'funcionamiento-principal'})
            ).then(function (respuesta) {
                //console.log(respuesta);
                var arrayresp = JSON.parse(respuesta);
            //total de notificaciones  
                var totalNotif = $("#spanNotificaciones").html();
                if (arrayresp[0] == 'noNotif') {
                $("#spanNotificaciones").addClass("noneView");
                $("#spanNotificaciones").html('0');
                } else {
                    $("#spanNotificaciones").removeClass("noneView");
                    if (arrayresp[0] > totalNotif) {
                        var $toastContent = $('<div class="btnCorrecto">registrado exitosamente</div>');
                        Materialize.toast($toastContent,5000);  
                    }
                    $("#spanNotificaciones").html(arrayresp[0]);
                }

            //notificaciones por area
                $("#dropdownNotificationes").html(arrayresp[1]);

            //reloj automatico
                $("#dvhusohora").html(arrayresp[2]);

            //menu lateral
                $("#collapsibleMenuLateralPerm").html(arrayresp[3]);
        });
    }
    //$(todoAjaxIniMenu());
    setInterval(todoAjaxIniMenu,1000);

    $("#dropdownNotificationes").on("click","div#btningresosNotif",function(){ verNotif('cW9RTHNQM2VlMmp6sosMnVrRermxmMVnigF2Ukc0NjcxaHBHYVNtS0NrWlQ'); });
    $("#dropdownNotificationes").on("click","div#btnegresosNotif",function(){ verNotif('cW9RTHNQM2VlMmp6sosMnVrRermxmMVgeF2Ukc0NjcxaHBHYVNtS0NrWlQ'); });
    $("#dropdownNotificationes").on("click","div#btntesoreriaNotif",function(){ verNotif('cW9RTHNQM2VlMmp6airMnVrRmxmMVeroF2Ukcset0NjcxaHBHYVNtS0NrWlQ'); });
    $("#dropdownNotificationes").on("click","div#btnvHumanoNotif",function(){ verNotif('cW9RTHNQM2VlMmp6muhMnVrRlavmxmMVF2Ukc0NjcxaHBHYVNtS0NrWlQ'); });
    $("#dropdownNotificationes").on("click","div#btncontabilidadNotif",function(){ verNotif('cW9RTHNQM2VlMmp6dadiMnVrRlibamxmMVtnocF2Ukc0NjcxaHBHYVNtS0NrWlQ'); });
    $("#dropdownNotificationes").on("click","div#btntecInformaciónNotif",function(){ verNotif('cW9RTHNQM2VlMmp6rofMnVrRnimxmMVcetF2Ukc0NjcxaHBHYVNtS0NrWlQ'); });

    function verNotif(area){
        //alert(area);
        $.ajax({
            url: "ver-notificaciones",
            type: "post",
            data: {
                    tokenVerArea:area,
                },
            dataType: "html",
            success: function (respuesta) {
                //alert(respuesta);
                $("#noNotifH6").addClass("noneView");
                $("#divViewNoitif").removeClass("noneView");
                $("#divViewNoitif").html(respuesta);
            }
        });

        $.ajax({
            url: "v-notificacionesdel",
            type: "post",
            data: {
                    tokenVerArea:area,
                },
            dataType: "html",
            success: function (respuesta) {
                //alert(respuesta);
                $("#noNotifH6").addClass("noneView");
                $("#notifLeidas").removeClass("noneView");
                $("#notifLeidas").html(respuesta);
            }
        });
    }

    $("#divViewNoitif").on("click","div#btnmdalNotifcard a#btnEjectVentaSos",function(){
        //card-content
        var tknNotifhidden = $(this).parents("div#btnmdalNotifcard").find("#tknNotifhidden").val();
        var tokenVerServicios = $(this).parents("div#btnmdalNotifcard").find("#tokenVerServicios").val();
        var tknNotifCliente = $(this).parents("div#btnmdalNotifcard").find("#tknNotifCliente").val();
        //alert(tknNotifhidden+"////"+tokenVerServicios+"///7/"+tknNotifCliente);
        //console.log(tknNotifhidden+"////"+tokenVerServicios+"////"+tknNotifCliente);

        cuteAlert({
            type: "question",
            title: "Alerta",
            message: "¿Deseas cambiar a leido?",
            confirmText: "Si",
            cancelText: "No"
        }).then((e)=>{
            if (e){
                var partData = $(this).closest('div').serialize();
                var data = new FormData();
                data.append('data',partData);
                data.append('tknNotifhidden',tknNotifhidden);
                data.append('tknServicios',tokenVerServicios);
                data.append('tknCliente',tknNotifCliente);
                $.ajax({
                    url: "sos-ingresos-ventas",
                    type: "post",
                    data: data,
                    dataType: 'html',
                    processData: false,
                    contentType: false,
                    success: function (respuesta) {
                        window.location.href = "sos-ingresos-ventas";
                    }
                });
            } else {
                $(this).removeAttr("checked");
            }
        })

    });

    $("#divViewNoitif").on("click","div#btnmdalNotifcard a#btnEjectVentaClient",function(){
        //card-content
        var tknNotifhidden = $(this).parents("div#btnmdalNotifcard").find("#tknNotifhidden").val();
        var tokenVerServicios = $(this).parents("div#btnmdalNotifcard").find("#tokenVerServicios").val();
        var tknNotifCliente = $(this).parents("div#btnmdalNotifcard").find("#tknNotifCliente").val();
        //alert(tknNotifhidden+"////"+tokenVerServicios+"///7/"+tknNotifCliente);
        //console.log(tknNotifhidden+"////"+tokenVerServicios+"////"+tknNotifCliente);

        cuteAlert({
            type: "question",
            title: "Alerta",
            message: "¿Deseas cambiar a leido?",
            confirmText: "Si",
            cancelText: "No"
        }).then((e)=>{
            if (e){
                var partData = $(this).closest('div').serialize();
                var data = new FormData();
                data.append('data',partData);
                data.append('tknNotifhidden',tknNotifhidden);
                data.append('tknServicios',tokenVerServicios);
                data.append('tknCliente',tknNotifCliente);
                $.ajax({
                    url: "clientessos-ingresos-ventas",
                    type: "post",
                    data: data,
                    dataType: 'html',
                    processData: false,
                    contentType: false,
                    success: function (respuesta) {
                        window.location.href = "sos-ingresos-ventas";
                    }
                });
            } else {
                $(this).removeAttr("checked");
            }
        })

    });

    $("#divViewNoitif").on("click","div#btnmdalNotifcard a#btnLeerNotif",function(){
        //card-content
        var cardNotificacion = $(this).parents("div#btnmdalNotifcard");
        var tknNotifhidden = $(this).parents("div#btnmdalNotifcard").find("#tknNotifhidden").val();
        var tokenVerServicios = $(this).parents("div#btnmdalNotifcard").find("#tokenVerServicios").val();
        var tknNotifCliente = $(this).parents("div#btnmdalNotifcard").find("#tknNotifCliente").val();
        alert(tknNotifhidden+"////"+tokenVerServicios+"////"+tknNotifCliente);
        console.log(tknNotifhidden+"////"+tokenVerServicios+"////"+tknNotifCliente);

        cuteAlert({
            type: "question",
            title: "Alerta",
            message: "¿Deseas cambiar a leido?",
            confirmText: "Si",
            cancelText: "No"
        }).then((e)=>{
            if (e){
                $.ajax({
                    url: "notifreadchangestatus",
                    type: "post",
                    data: {tknNotifhidden:tknNotifhidden},
                    dataType: 'html',
                    success: function (respuesta) {
                        alert(respuesta);
                        if (respuesta == 'errorUser') {
                            toastError('Usuario no autorizado');
                            $(this).removeAttr("checked");
                        }

                        if (respuesta == 'tknNotifhidden') {
                            toastError('Servicio no encontrado');
                            $(this).removeAttr("checked");
                        }
                         
                        if(respuesta == 'servNounVincDesc'){
                            toastError('operacion no realizada, intente nuevamente o comuniquese con soporte');  
                        }

                        if(respuesta == 'changeStateNotifalse'){
                            var $toastContent = $('<div class="btnCorrecto">registrado exitosamente</div>');
                            Materialize.toast($toastContent,5000); 
                            todoAjaxIniMenu();
                            cardNotificacion.remove();
                        }
                    }
                });
            } else {
                $(this).removeAttr("checked");
            }
        })

    });

    $('input#verif_rfc, textarea#textarea2').characterCounter();
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

    $("#vSideNav").click(function () {
        var btnMenu = document.getElementById("vSideNav");
        var navlat = document.getElementById("slide-out");
        var marco = document.getElementById("vContent");
        var marcoSup = document.getElementById("navegadorAdmin");

        if (navlat.classList.contains("side-lateral")) {
            btnMenu.classList.add("active-sidebar");

            navlat.classList.remove("side-lateral");
            navlat.classList.add("menu-none");

            marco.classList.add("contenido100");
            marcoSup.classList.add("contenido100");
        } else {
            btnMenu.classList.remove("active-sidebar");

            navlat.classList.remove("menu-none");
            navlat.classList.add("side-lateral");

            marco.classList.remove("contenido100");
            marcoSup.classList.remove("contenido100");
        }
    });
    
    $("#vSideNav2").click(function () {
        var btnMenu = document.getElementById("vSideNav2");
        var navlat = document.getElementById("slide-out");
        var marco = document.getElementById("vContent");
        var marcoSup = document.getElementById("navegadorAdmin");

        if (navlat.classList.contains("side-lateralM")) {
            btnMenu.classList.remove("active-sidebar");
        
            navlat.classList.remove("side-lateralM");
            navlat.classList.add("menu-none");
        } else {
            btnMenu.classList.add("active-sidebar");
            navlat.classList.remove("menu-none");
            navlat.classList.add("side-lateralM");
        }
    });
}); 
$(document).ready(function(){
//setInterval(totalNotificaciones,1000);
//setInterval(listAreaNotificaciones,1000);
//setInterval(llenaMenuLat,1000);
//setInterval(relojautomatico,1000);
//setInterval(totalNotifServicios,5000);
}); 

