$(document).ready(function(){
    (verlistaCaja());
    function verlistaCaja(){
        $.ajax({
            type: "post",
            url: "tesoreria-control-listaCaja",
            dataType: "html",
            success: function (response) {
                $("#verlistaCaja").html(response);
            }
        });
    }
});