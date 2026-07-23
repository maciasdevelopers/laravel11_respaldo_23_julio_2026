$(document).ready(function(){
    $('.tabs').tabs();
    const menuIng = new Vue({
        mounted(){
            $('.tooltipped').tooltip();
        },
        el: "#menuCatIng",
        data:{
            ingresosMenu:[
                {
                    imagen:'vista/media/adm/usuarios/ingresos/catalogos/mercancias/mercancias.jpg',
                    botones:[
                        {id:'btnAbreCatMerc',letrero:'Catalogo de mercancias',icon:'fas fa-user-tag'},
                        {id:'btnAbreAltaMerc',letrero:'Lista de precios',icon:'fas fa-file-signature'},
                        {id:'btnAbreAltaProm',letrero:'Promociones',icon:'fas fa-gifts'}
                    ]
                },
                            
                {
                    imagen:'vista/media/adm/usuarios/ingresos/catalogos/servicios/servicios.jpg',
                    botones:[
                        {id:'btnAbreCatServ',letrero:'Catalogo de servicios',icon:'fas fa-headset'},
                        {id:'btnAbreAltaServ',letrero:'Alta de servicios',icon:'fas fa-tasks'},
                        {id:'btnAbreAltaDesc',letrero:'Descuentos',icon:'fas fa-dolly-flatbed'}
                    ]
                },
                
                {
                    imagen:'vista/media/adm/usuarios/ingresos/catalogos/impuestos/impuestos.jpg',
                    botones:[
                        {id:'btnAbreAltaImp',letrero:'Alta de impuestos',icon:'fas fa-tasks'}
                    ]
                },

                {
                    imagen:'vista/media/adm/usuarios/ingresos/catalogos/clientes/clientes.jpg',
                    botones:[
                        {id:'btnAbreCatClie',letrero:'Catalogo de clientes',icon:'fas fa-address-card'},
                        {id:'btnAbreAltaClie',letrero:'Alta de clientes',icon:'fas fa-tasks'}
                    ]
                }
            ]
        }

    });

    //menu catalogos
    var menuCatIng = document.getElementById("menuCatIng");
    var listas_ingresos = document.getElementById("listas_ingresos");
    var listaMer = document.getElementById("listaMer");
    var formMerc = document.getElementById("formMerc");
    var listaServicios = document.getElementById("listaServicios");
    var formServicios = document.getElementById("formServicios");
    var formDesc = document.getElementById("formDesc");
    var listaClientes = document.getElementById("listaClientes");
    var formClient = document.getElementById("formClient");
    var formImpuestos = document.getElementById("formImpuestos");
    var formProm = document.getElementById("formProm");

    //mercancias
        //catalogo
            var btnAbreCatMerc = document.getElementById("btnAbreCatMerc");
            $(btnAbreCatMerc).click(function(){
                abreCatalogo(); 
                listaMer.classList.remove("noneView");
            });

        //alta
            var btnAbreAltaMerc = document.getElementById("btnAbreAltaMerc");
            $(btnAbreAltaMerc).click(function() {
                abreCatalogo(); 
                formMerc.classList.remove("noneView");
            });

        //promociones
            var btnAbreAltaProm = document.getElementById("btnAbreAltaProm");
            $(btnAbreAltaProm).click(function() {
                abreCatalogo(); 
                formProm.classList.remove("noneView");
            });

    //servicios
        //Catalogo
            var btnAbreCatServ = document.getElementById("btnAbreCatServ");
            $(btnAbreCatServ).click(function(){
                abreCatalogo();
                listaServicios.classList.remove("noneView");
            });

        //Alta
        var btnAbreAltaServ = document.getElementById("btnAbreAltaServ");
            $(btnAbreAltaServ).click(function(){
                abreCatalogo();
                formServicios.classList.remove("noneView");
        });

        //descuentos, devoluciones y promociones
        var btnAbreAltaDesc = document.getElementById("btnAbreAltaDesc");
            $(btnAbreAltaDesc).click(function(){
                abreCatalogo();
                formDesc.classList.remove("noneView");
            });

    //impuestos
        //Alta
            var btnAbreAltaImp = document.getElementById("btnAbreAltaImp");
            $(btnAbreAltaImp).click(function(){
                abreCatalogo();
                formImpuestos.classList.remove("noneView");
            });

    //clientes
        //Catalogo
            var btnAbreCatClie = document.getElementById("btnAbreCatClie");
            $(btnAbreCatClie).click(function(){
                abreCatalogo();
                listaClientes.classList.remove("noneView");
            });

        //Alta
            var btnAbreAltaClie = document.getElementById("btnAbreAltaClie");
            $(btnAbreAltaClie).click(function(){
                abreCatalogo();
                formClient.classList.remove("noneView");
            });

    
    function abreCatalogo(){
        menuCatIng.classList.add("menuCatReducido");
        listas_ingresos.classList.remove("noneView");
        listaMer.classList.add("noneView");
        formMerc.classList.add("noneView");
        formProm.classList.add("noneView");
        listaServicios.classList.add("noneView");
        formServicios.classList.add("noneView");
        formDesc.classList.add("noneView");
        listaClientes.classList.add("noneView");
        formClient.classList.add("noneView");
        formImpuestos.classList.add("noneView");   
    } 

   //impuestos
        var btnAddOtroImp = document.getElementById("btnAddOtroImp");
        var txtOtroImpu = document.getElementById("txtOtroImpu");
        $(btnAddOtroImp).click(function(){
            alert("ok");
            $.ajax({
                url: "ingresos-otroImpuesto",
                type: "post",
                data: {tipoImpuesto:'001',
                    enviaOtro:txtOtroImpu},
                dataType: "html",
                success: function (respuesta) {
                    console.log(respuesta);
                }
            });
        });

});