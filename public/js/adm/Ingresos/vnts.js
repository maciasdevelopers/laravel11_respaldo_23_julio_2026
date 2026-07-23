$(document).ready(function () {
    $('.tabs').tabs();
    const menuVentas = new Vue({
        mounted(){
            $('.tooltipped').tooltip();
        },
        el: "#menuCatVentas",
        data:{
            ventasMenu:[
                {
                    imagen:'vista/media/adm/usuarios/ingresos/catalogos/mercancias/mercancias.jpg',
                    botones:[
                        {id:'btnAbreCatPedido',letrero:'Catalogo de pedidos',icon:'fas fa-boxes'},
                        {id:'btnAbreAltaPedido',letrero:'Alta de pedidos',icon:'fas fa-pallet'}
                    ]
                },
                {  
                    imagen:'vista/media/adm/usuarios/ingresos/catalogos/mercancias/mercancias.jpg',
                    botones:[
                        {id:'btnAbreListaVentas',letrero:'Servicios',icon:'fas fa-headset'},
                        {id:'btnAbreAltaVenta',letrero:'Productos',icon:'fas fa-box-open'}
                    ]
                },
                {
                    imagen:'vista/media/adm/usuarios/ingresos/catalogos/mercancias/mercancias.jpg',
                    botones:[
                        {id:'btnAbreCatSeg',letrero:'Catalogo de seguimientos',icon:'fas fa-map-signs'},
                        {id:'btnAbreAltaSeg',letrero:'Alta de seguimientos',icon:'fas fa-paste'}
                    ]
                },
                {  
                    imagen:'vista/media/adm/usuarios/ingresos/catalogos/mercancias/mercancias.jpg',
                    botones:[
                        {id:'btnAbreCatDevol',letrero:'Catalogo de devoluciones',icon:'fas fa-cart-arrow-down'},
                        {id:'btnAbreAltaDevol',letrero:'Alta de devolución',icon:'fas fa-file-contract'}
                    ]
                },
            ]
        }

    });

    //menu catalogo
    var menuCatVentas = document.getElementById("menuCatVentas");
    var listas_ventas = document.getElementById("listas_ventas");
    var listaPedido = document.getElementById("listaPedido");
    var formPedido = document.getElementById("formPedido");
    var formDataVentas = document.getElementById("formDataVentas");
    var formAltaVenta = document.getElementById("formAltaVenta");
    var listaSeg = document.getElementById("listaSeg");
    var formSeg = document.getElementById("formSeg");
    var listaDevol = document.getElementById("listaDevol");
    var formDevol = document.getElementById("formDevol");
    var listaDevol = document.getElementById("listaDevol");
    var formDevol = document.getElementById("formDevol");

    //pedidos 
        //catalogos
        var btnAbreCatPedido = document.getElementById("btnAbreCatPedido");
        $(btnAbreCatPedido).click(function() {
            abreCatalogo(); 
            formPedido.classList.remove("noneView");
        });

        //alta
        var btnAbreAltaPedido = document.getElementById("btnAbreAltaPedido");
        $(btnAbreAltaPedido).click(function() {
            abreCatalogo(); 
            listaPedido.classList.remove("noneView");
        });

    //ventas
        //productos
            //alta
            var btnAbreAltaVenta = document.getElementById("btnAbreAltaVenta");
            $(btnAbreAltaVenta).click(function() {
                abreCatalogo(); 
                formAltaVenta.classList.remove("noneView");
            });

        //servicios
            //alta
            var btnAbreListaVentas = document.getElementById("btnAbreListaVentas");
            $(btnAbreListaVentas).click(function() {
                abreCatalogo(); 
                formDataVentas.classList.remove("noneView");
            });
    
    //seguimiento de ventas
        //catalogo
        var btnAbreCatSeg = document.getElementById("btnAbreCatSeg");
        $(btnAbreCatSeg).click(function() {
            abreCatalogo(); 
            formSeg.classList.remove("noneView");
        });

        //alta
        var btnAbreAltaSeg = document.getElementById("btnAbreAltaSeg");
        $(btnAbreAltaSeg).click(function() {
            abreCatalogo(); 
            listaSeg.classList.remove("noneView");
        });

    //devoluciones
        //catalogo
        var btnAbreCatDevol = document.getElementById("btnAbreCatDevol");
        $(btnAbreCatDevol).click(function() {
            abreCatalogo(); 
            formDevol.classList.remove("noneView");
        });

        //alta
        var btnAbreAltaDevol = document.getElementById("btnAbreAltaDevol");
        $(btnAbreAltaDevol).click(function() {
            abreCatalogo(); 
            listaDevol.classList.remove("noneView");
        });

    function abreCatalogo(){
        menuCatVentas.classList.add("menuCatReducido");
        listas_ventas.classList.remove("noneView");
        listaPedido.classList.add("noneView");
        formPedido.classList.add("noneView");
        formDataVentas.classList.add("noneView");
        formAltaVenta.classList.add("noneView");
        listaSeg.classList.add("noneView");
        formSeg.classList.add("noneView");
        listaDevol.classList.add("noneView");
        formDevol.classList.add("noneView");
        listaDevol.classList.add("noneView");
        formDevol.classList.add("noneView");
    }

});