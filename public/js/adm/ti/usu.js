$(document).ready(function(){
    function toastErrorCorrecto(texto){
        var $toastContent = $('<div class="btncorrecto">'+texto+'</div>');
        Materialize.toast($toastContent,5000);    
    }

    function toastError(texto){
        var $toastContent = $('<div class="btnError">'+texto+'</div>');
        Materialize.toast($toastContent,5000);    
    }

    var catalogoEgresos = new Vue({
        mounted() {
            $('.tooltipped').tooltip();
          },
        el:"#menuCatSopUs",
        data:{
            catSopUsMenu:[
                {imagen:'vista/media/adm/usuarios/egresos/catalogos/productos/productos.jpg',
                    btn1:{id:'btnAbreCatProd',letrero:'Catalogo de permisos',icon:'fas fa-box-open'},
                    btn2:{id:'btnAbreAltaProd',letrero:'Alta de permisos',icon:'fas fa-tasks'}},

                {imagen:'vista/media/adm/usuarios/egresos/catalogos/servicios/servicios.jpg',
                    btn1:{id:'btnAbreCatServ',letrero:'Catalogo de usuarios en clientes',icon:'fas fa-money-check-alt'},
                    btn2:{id:'btnAbreAltaServ',letrero:'Alta y baja de usuarios en clientes',icon:'fas fa-tasks'}},

                {imagen:'vista/media/adm/usuarios/egresos/catalogos/activos/activos.jpg',
                    btn1:{id:'btnAbreCatAct',letrero:'Catalogo de usuarios en SOS',icon:'fas fa-hand-holding-usd'},
                    btn2:{id:'btnAbreAltaAct',letrero:'Alta y baja de usuarios en SOS',icon:'fas fa-tasks'}},

                {imagen:'vista/media/adm/usuarios/egresos/catalogos/activos/activos-di.jpg',
                    btn1:{id:'btnAbreCatSolReg',letrero:'Catalogo de solicitudes de registro',icon:'fas fa-donate'},
                    btn2:{id:'btnAbreAltaSolReg',letrero:'Alta de solicitudes de registro',icon:'fas fa-tasks'}},
            ]
        }
    });


    //menu 
        var menuCatSopUs = document.getElementById("menuCatSopUs");
        var listas_tiusu = document.getElementById("listas_tiusu");
        var divCatSoliReg = document.getElementById("divCatSoliReg"); 
        var formnewSoliReg = document.getElementById("formnewSoliReg"); 

        $("#btnAbreCatSolReg").click(function(){
            abreMenu();
            divCatSoliReg.classList.remove("noneView");
        });
        $("#btnAbreAltaSolReg").click(function(){
            abreMenu();
            formnewSoliReg.classList.remove("noneView");
        });

        function abreMenu(){
            menuCatSopUs.classList.add("menuCatReducido");
            listas_tiusu.classList.remove("noneView");
            divCatSoliReg.classList.add("noneView");
            formnewSoliReg.classList.add("noneView");
        }

        $("#validarRegistro tr a#btnValidaRegistro").hover(
            function() {
                $(this).addClass("green");
                $(this).addClass("darken-2");
            }, function() {
                $(this).removeClass("green");
                $(this).removeClass("darken-2");
            }
        );

        $("#validarRegistro tr a#btnDeleteRegistro").hover(
            function() {
                $(this).addClass("red");
                $(this).addClass("darken-2");
            }, function() {
                $(this).removeClass("red");
                $(this).removeClass("darken-2");
            }
        );

        $("#validarRegistro").on("click","td a#btnValidaRegistro",function(){
            this.classList.add("tr_active");
            var token = $(this).parents("tr").find("td").eq(0).html();

            $.ajax({
                url: 'sos-soporte-validarregistros',
                type: 'POST',
                datatype: 'html',
                data:{  
                    idempresa:token
                }
            })
            .success(function(respuesta){
                alert(respuesta);
                var mensaje2 = 'intentalo nuevamente ó revisa todos los datos del registro';
                if (respuesta == 'validado') {
                    //Push.create("Registro validado", {
                    //    body: "SOS-MEXICO",
                    //    icon: "vista/media/landing/logotipo/314g.jpg",
                    //    timeout: 3000,
                    //});
                    toastError('¡esta solicitud ya ha sido registrada anteriormente!');
                } 

                if (respuesta == 'registroAprobado') {
                    //console.log(respuesta);
                    //Push.create("Validacion exitosa", {
                    //    body: "SOS-MEXICO",
                    //    icon: "vista/media/landing/logotipo/314g.jpg",
                    //    timeout: 3000,
                    //});
                    toastErrorCorrecto('¡Aprobaste el registro de esta solicitud como cliente de SOS!');
                    //window.location="sos-soporte-usuarios";
                }

                if (respuesta == 'errorRegistroStatus') {
                    toastError('¡El status de esta solicitud no fue actualizado,'+mensaje2+' !');
                }
                if (respuesta == 'errorRegistroProveedor') {
                    toastError('¡El proveedor de esta solicitud no fue registrado,'+mensaje2+' !');
                }
                if (respuesta == 'errorRegistroCliente') {
                    toastError('¡Esta solicitud no fue registrada en la lista de clientes,'+mensaje2+' !');
                }
                if (respuesta == 'errorRegistroPermisos') {
                    toastError('¡Esta solicitud no fue registrada en la lista de permisos,'+mensaje2+' !');
                }
                if (respuesta == 'errorRegistroEmpresa') {
                    toastError('¡Esta solicitud no fue registrada en la lista de empresas,'+mensaje2+' !');
                }
                if (respuesta == 'errorRegistroVUser') {
                    toastError('¡Esta solicitud no fue registrada en la lista de usuarios,'+mensaje2+' !');
                }
            })
            .fail(function(){
                console.log("error");
            })
            //window.history.back();

        });

        $("#validarRegistro").on("click","td a#btnDeleteRegistro",function(){
            this.classList.add("tr_active");
            var token = $(this).parents("tr").find("td").eq(0).html();

            $.ajax({
                url: 'sos-soporte-eliminaregistrosoli',
                type: 'POST',
                datatype: 'html',
                data:{tokenSoli:token}
            })
            .success(function(respuesta){
                alert(respuesta);
                var mensaje2 = 'intentalo nuevamente ó revisa todos los datos del registro';
                if (respuesta == 'validado') {
                    toastError('¡esta solicitud ya ha sido registrada anteriormente!');
                }

                if (respuesta == 'eliminado') {
                    toastError('¡El status de esta solicitud no fue actualizado,'+mensaje2+' !');
                }
            })
            .fail(function(){
                console.log("error");
            })
            //window.history.back();

        });
});