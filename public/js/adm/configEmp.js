$(document).ready(function(){
    $("#table-empresas tr").click(function(){
        $empresa = $(this).find("td").eq(0).html();
        $.ajax({
            type: "POST",
            url: "clientessos-cambiaempresa",
            data: {empresa:$empresa},
            dataType: "html"
        }).done(function(respuesta) {
            console.log(respuesta);
            window.location.reload();
        }).fail(function(respuesta) {
            console.log(respuesta)
        });
    });
});