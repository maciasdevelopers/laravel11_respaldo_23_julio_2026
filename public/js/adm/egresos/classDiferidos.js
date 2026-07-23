$(document).ready(function(){
    //catalogo
    //formulario
        //lista de clasificacion de activos
            var tableClassAct = document.getElementById("tableClassAct");

            //inputs
                var classActDif = document.getElementById("classActDif"); 
                var amortContable = document.getElementById("amortContable"); 
                var amortFiscal = document.getElementById("amortFiscal");

            //labels
                var lblClassActDif = document.getElementById("lblClassActDif");
                var lblAmortContable = document.getElementById("lblAmortContable");
                var lblAmortFiscal = document.getElementById("lblAmortFiscal");

            $(".selectClassActivoDif").click(function(){
                var tdClass = $(this).parent("td").parent("tr").find("td").eq(1).html();
                var tdContable = $(this).parent("td").parent("tr").find("td").eq(2).html();
                var tdFiscal = $(this).parent("td").parent("tr").find("td").eq(3).html();

                if (lblClassActDif.classList.contains("errorlabel")) {
                    lblClassActDif.classList.remove("errorlabel");
                    lblClassActDif.innerHTML = "Clasificaci&oacute;n incorrecta";
                }
                classActDif.value = tdClass;

                if (lblAmortContable.classList.contains("errorlabel")) {
                    lblAmortContable.classList.remove("errorlabel");
                    lblAmortContable.innerHTML = "Amortizaci&oacute;n contable";
                }
                amortContable.value = tdContable;

                if (lblAmortFiscal.classList.contains("errorlabel")) {
                    lblAmortFiscal.classList.remove("errorlabel");
                    lblAmortFiscal.innerHTML = "Amortizaci&oacute;n fiscal";
                }
                amortFiscal.value = tdFiscal;
                
            });
});