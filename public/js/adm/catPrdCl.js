$(document).ready(function (){
    const datclass= "#datClass"; 
    const datGenero= "#sendGenero"; 
    let vistaClass = document.getElementById("vistaClass");
    let vistaGenero = document.getElementById("vistaGenero");
    let vistaForm = document.getElementById("vistaForm");
    
    let selclasls = document.getElementById("selClass");
    let selGenero = document.getElementById("selGenero");

    let txtClass = document.getElementById("vClass");
    let txtGenero = document.getElementById("vGenero");
    $(datclass).click(function(){
        alert(selclasls.value);
        vistaClass.setAttribute("style","display:none");
        vistaGenero.setAttribute("style","display:flex");
        txtClass.setAttribute("value", selclasls.value);
    });

    $(datGenero).click(function(){
        alert(selGenero.value);
        vistaGenero.setAttribute("style","display:none");
        vistaForm.setAttribute("style","display:flex");
        txtGenero.setAttribute("value", selclasls.value);
    });

});