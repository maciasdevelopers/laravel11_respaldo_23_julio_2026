$(document).ready(function(){
    var backPage = document.getElementById("backPage");
    $(backPage).click(function(){
        history.go(-1);
    });
});