$(document).ready(function(){
    //ul
        var ulCollection = document.getElementById("ulCollection");
        var ulCollectionCotDir = document.getElementById("ulCollectionCotDir");

    $("#listaCotOrdenCompra tr").click(function(){
        var id_detalle_Requisicion = $(this).find("td").eq(0).html();
        var id_desc_detalle_cotiza = $(this).find("td").eq(1).html(); 
        var detalle_cotizacion = $(this).find("td").eq(2).html();
        var idproveedor = $(this).find("td").eq(3).html();

        var necesidad = $(this).find("td").eq(4).html();
        var marca = $(this).find("td").eq(5).html();
        var cantidad = $(this).find("td").eq(6).html();
        var precio = $(this).find("td").eq(7).html();
        var documento = $(this).find("td").eq(8).html();
        var proveedor = $(this).find("td").eq(9).html();

        var id_cotizacion = $(this).find("td").eq(10).html();
        alert(proveedor);
        var renglon = document.createElement('li');
        ulCollection.appendChild(renglon);
        renglon.classList.add("collection-item"); 
        var fila = '<input class="" type="hidden" name="idOdRequisicion[]" value="'+id_detalle_Requisicion+'">'+
        '<input class="" type="hidden" name="idDescDetCotiza[]" value="'+id_desc_detalle_cotiza+'">'+
        '<input class="" type="hidden" name="detalle_cotizacion[]" value="'+detalle_cotizacion+'">'+
        '<input class="" type="hidden" name="proveedor[]" value="'+idproveedor+'">'+
        '<input class="" type="hidden" name="id_cotizacion[]" value="'+id_cotizacion+'">'+
        'Concepto: '+necesidad+
        'Marca: '+marca+
        'Cantidad: '+cantidad+
        'Precio: '+precio+
        'Proveedor: '+proveedor;
        renglon.innerHTML = fila;
    });

    $("#listaDirCotOrdenCompra tr").click(function(){
        var id_detalle_Requisicion = $(this).find("td").eq(0).html();
        var id_desc_detalle_cotiza = $(this).find("td").eq(1).html(); 
        var detalle_cotizacion = $(this).find("td").eq(2).html();
        var idproveedor = $(this).find("td").eq(3).html();

        var necesidad = $(this).find("td").eq(4).html();
        var marca = $(this).find("td").eq(5).html();
        var cantidad = $(this).find("td").eq(6).html();
        var precio = $(this).find("td").eq(7).html();
        var documento = $(this).find("td").eq(8).html();
        var proveedor = $(this).find("td").eq(9).html();

        var id_cotizacion = $(this).find("td").eq(10).html();
        alert(proveedor);
        var renglon = document.createElement('li');
        ulCollectionCotDir.appendChild(renglon);
        renglon.classList.add("collection-item"); 
        var fila = '<input class="" type="hidden" name="idOdRequisicion[]" value="'+id_detalle_Requisicion+'">'+
        '<input class="" type="hidden" name="idDescDetCotiza[]" value="'+id_desc_detalle_cotiza+'">'+
        '<input class="" type="hidden" name="detalle_cotizacion[]" value="'+detalle_cotizacion+'">'+
        '<input class="" type="hidden" name="proveedor[]" value="'+idproveedor+'">'+
        '<input class="" type="hidden" name="id_cotizacion[]" value="'+id_cotizacion+'">'+
        'Concepto: '+necesidad+
        'Marca: '+marca+
        'Cantidad: '+cantidad+
        'Precio: '+precio+
        'Proveedor: '+proveedor;
        renglon.innerHTML = fila;
    });

    
    $("#tableOrdenesSelCompra tr").click(function(){
        var detalle = $(this).find("td").eq(1).html();
        
        $.ajax({
            url: 'clientessos-egresos-buscaordencatprod',
            type: 'POST',
            datatype: 'html',
            data: {detalle:detalle}
        })
        .done(function(respuesta){
            if(respuesta == "producto no registrado"){
                alert("esta orden de compra no esta regustrada");
                //window.open (url:'localhost/sos-mexico/clientessos-egresos-catalogos',
                //nombreVentana:string,caracteristicas :string)

                window.open("https://www.w3schools.com", "", "width=200,height=100");
            }
            else{
                var mensaje = confirm("¿Desea autorizar esta orden de compra?");
                if (mensaje) {
                    $.ajax({
                        url: 'egresos-autorizacompra',
                        type: 'POST',
                        datatype: 'html',
                        data: {detalle:detalle}
                    })
                    .done(function(respuesta){
                        window.location="clientessos-egresos-compras";
                    })
                    .fail(function(respuesta){
                        console.log(respuesta)
                    });
                    compraAutorizada();
                }
                else{
                
                }
            }
        })
        .fail(function(respuesta){
            console.log(respuesta)
        });

    });

    $("#tableOrdenesSelCompraCot tr").click(function(){
        var detalle = $(this).find("td").eq(1).html();
        
        $.ajax({
            url: 'clientessos-egresos-buscaordencatprod',
            type: 'POST',
            datatype: 'html',
            data: {detalle:detalle}
        })
        .done(function(respuesta){
            if(respuesta == "producto no registrado"){
                alert("esta orden de compra no esta regustrada");
                
                //window.open (url:'localhost/sos-mexico/clientessos-egresos-catalogos',
                //nombreVentana:string,caracteristicas :string)

                window.open("clientessos-egresos-catalogos", "", "width=1500,height=650");
            }
            else{
                var mensaje = confirm("¿Desea autorizar esta orden de compra?");
                if (mensaje) {
                    $.ajax({
                        url: 'egresos-autorizacompra',
                        type: 'POST',
                        datatype: 'html',
                        data: {detalle:detalle}
                    })
                    .done(function(respuesta){
                        window.location="clientessos-egresos-compras";
                    })
                    .fail(function(respuesta){
                        console.log(respuesta)
                    });
                    compraAutorizada();
                }
                else{
                
                }
            }
        })
        .fail(function(respuesta){
            console.log(respuesta)
        });
    });

    $("#tableOrdenesSelDirCompra tr").click(function(){
        var orden = $(this).find("td").eq(0).html();
        var producto = $(this).find("td").eq(1).html();
        $.ajax({
            url: 'clientessos-egresos-buscaordcatproddir',
            type: 'POST',
            datatype: 'html',
            data: {detalle:producto}
        })
        .done(function(respuesta){
            if(respuesta == "producto no registrado"){
                alert("esta orden de compra no esta regustrada");

                window.open("clientessos-egresos-catalogos", "", "width=1500,height=650");
            }
            else{
                var mensaje = confirm("¿Desea autorizar esta orden de compra?");
                if (mensaje) {
                    alert(orden);
                    $.ajax({
                        url: 'egresos-autorizacompradir',
                        type: 'POST',
                        datatype: 'html',
                        data: {orden:orden}
                    })
                    .done(function(respuesta){
                        window.location="clientessos-egresos-compras";
                    })
                    .fail(function(respuesta){
                        console.log(respuesta)
                    });
                    //compraAutorizada();
                }
                else{
                
                }
            }
        })
        .fail(function(respuesta){
            console.log(respuesta)
        });

    });


});

function compraAutorizada() {
    Push.create("COMPRA AUTORIZADA", {
        body: "SOS-México",
        icon: "vista/media/landing/logotipo/314g.jpg",
        timeout: 3000,
    });
};
