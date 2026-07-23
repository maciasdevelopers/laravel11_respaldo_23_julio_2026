$(document).ready(function(){
    $('.dropdown-trigger').dropdown();

    var licollapsible1 = document.getElementById("liactive1");
    var licollapsible2= document.getElementById("liactive2");
    var licollapsible3 = document.getElementById("liactive3");
    var divhead = document.getElementById("headegresos");
    var divbody = document.getElementById("bodyegresos");
    var itemli = document.getElementById("clientessos-egresos-catalogos");             

    $("#liactive1").click(function(){
        licollapsible1.classList.remove("liView");
        divhead.classList.remove("active");
        divbody.classList.remove("active");
        //itemli.classList.remove("active");    
    });

    $("#liactive2").click(function(){
        licollapsible2.classList.remove("liView");
        divhead.classList.remove("active");
        divbody.classList.remove("active");
        //itemli.classList.remove("active");    
    });

    $("#liactive3").click(function(){
        licollapsible3.classList.remove("liView");
        divhead.classList.remove("active");
        divbody.classList.remove("active");
        //itemli.classList.remove("active");    
    });

    //menu catalogos
        var catalogoEgresos = new Vue({
            mounted() {
                $('.tooltipped').tooltip();
              },
            el:"#menuCatEg",
            data:{
                catEgMenu:[
                    {imagen:'vista/media/adm/usuarios/egresos/catalogos/productos/productos.jpg',
                        btn1:{id:'btnAbreCatProd',letrero:'Catalogo de productos',icon:'fas fa-box-open'},
                        btn2:{id:'btnAbreAltaProd',letrero:'Alta de productos',icon:'fas fa-tasks'}},
    
                    {imagen:'vista/media/adm/usuarios/egresos/catalogos/servicios/servicios.jpg',
                        btn1:{id:'btnAbreCatServ',letrero:'Catalogo de servicios',icon:'fas fa-money-check-alt'},
                        btn2:{id:'btnAbreAltaServ',letrero:'Alta de servicios',icon:'fas fa-tasks'}},
    
                    {imagen:'vista/media/adm/usuarios/egresos/catalogos/activos/activos.jpg',
                        btn1:{id:'btnAbreCatAct',letrero:'Catalogo de activos fijos',icon:'fas fa-hand-holding-usd'},
                        btn2:{id:'btnAbreAltaAct',letrero:'Alta de activos fijos',icon:'fas fa-tasks'}},
    
                    {imagen:'vista/media/adm/usuarios/egresos/catalogos/activos/activos-di.jpg',
                        btn1:{id:'btnAbreCatActDif',letrero:'Catalogo de activos diferidos',icon:'fas fa-donate'},
                        btn2:{id:'btnAbreAltaActDif',letrero:'Alta de activos diferidos',icon:'fas fa-tasks'}},

                    {imagen:'vista/media/adm/usuarios/egresos/catalogos/proveedores/proveedores.jpg',
                        btn1:{id:'btnAbreCatProv',letrero:'Catalogo de proveedores',icon:'fas fa-users'},
                        btn2:{id:'btnAbreAltaProv',letrero:'Alta de proveedores',icon:'fas fa-tasks'}},

                    {imagen:'vista/media/adm/usuarios/egresos/catalogos/almacenes/almacenes.jpg',
                        btn1:{id:'btnAbreCatalogoww',letrero:'Catalogo de almacenes',icon:'fas fa-warehouse'},
                        btn2:{id:'btnAbreAltaProdww',letrero:'Alta de almacenes',icon:'fas fa-tasks'}},
                ]
            }
        });
        var menuCatEg = document.getElementById("menuCatEg");
        var listas_ps = document.getElementById("listas_ps");
        var dropdownProv = document.getElementById("dropdownProv");
        var listaProd = document.getElementById("listaProd");
        var formProd = document.getElementById("formProd");
        var listaServ = document.getElementById("listaServ");
        var formServ = document.getElementById("formServ");
        var listaActivos = document.getElementById("listaActivos");
        var formActivos = document.getElementById("formActivos");
        var listaActivosDif = document.getElementById("listaActivosDif");
        var formActivosDif = document.getElementById("formActivosDif");
        var listaProveedores = document.getElementById("listaProveedores");
        var formProvNacional = document.getElementById("formProvNacional");

        //productos
            //catalogo
                var btnAbreCatProd = document.getElementById("btnAbreCatProd");
                $(btnAbreCatProd).click(function(){
                    abreCatalogo();
                    listaProd.classList.remove("noneView");
                });

            //alta
                var btnAbreAltaProd = document.getElementById("btnAbreAltaProd");
                $(btnAbreAltaProd).click(function(){
                    abreCatalogo();
                    formProd.classList.remove("noneView");
                });

        //servicios
            //catalogo de servivios
                var btnAbreCatServ = document.getElementById("btnAbreCatServ");
                $(btnAbreCatServ).click(function(){
                    abreCatalogo();
                    listaServ.classList.remove("noneView");
                });
            
            /*abrir formulario de alta de productos*/
                var btnAbreAltaServ = document.getElementById("btnAbreAltaServ");
                $(btnAbreAltaServ).click(function(){
                    abreCatalogo();
                    formServ.classList.remove("noneView");
                });

        //activos
            //activos fijos
                //catalogo de activos
                    var btnAbreCatAct = document.getElementById("btnAbreCatAct");
                    $(btnAbreCatAct).click(function(){
                        abreCatalogo();
                        listaActivos.classList.remove("noneView");
                    });
                
                /*abrir formulario de alta de activos*/
                    var btnAbreAltaAct = document.getElementById("btnAbreAltaAct");
                    $(btnAbreAltaAct).click(function(){
                        abreCatalogo();
                        formActivos.classList.remove("noneView");
                    });
                        
            //activos diferidos
                //catalogo
                    var btnAbreCatActDif = document.getElementById("btnAbreCatActDif");
                    $(btnAbreCatActDif).click(function(){
                        abreCatalogo();
                        listaActivosDif.classList.remove("noneView");
                    });

                    var btnAbreAltaActDif = document.getElementById("btnAbreAltaActDif");
                    $(btnAbreAltaActDif).click(function(){
                        abreCatalogo();
                        formActivosDif.classList.remove("noneView");
                    });
        //proveedores
            //catalogo de proveedores
                var btnAbreCatProv = document.getElementById("btnAbreCatProv");
                $(btnAbreCatProv).click(function(){
                    abreCatalogo();
                    listaProveedores.classList.remove("noneView");
                });
            
            //formulario de proveedor
                var btnAbreAltaProv = document.getElementById("btnAbreAltaProv");
                $(btnAbreAltaProv).click(function(){
                    abreCatalogo();
                    formProvNacional.classList.remove("noneView");
                    //var height = $(window).height();
                    //$(formProvNacional).height(height);
                });

        //almacenes

        function abreCatalogo(){
            menuCatEg.classList.add("menuCatReducido");
            listas_ps.classList.remove("noneView");
            listaProd.classList.add("noneView");
            formProd.classList.add("noneView");
            listaServ.classList.add("noneView");
            formServ.classList.add("noneView");
            listaActivos.classList.add("noneView");
            formActivos.classList.add("noneView");
            listaActivosDif.classList.add("noneView");
            formActivosDif.classList.add("noneView");
            listaProveedores.classList.add("noneView");
            formProvNacional.classList.add("noneView");
        }
});