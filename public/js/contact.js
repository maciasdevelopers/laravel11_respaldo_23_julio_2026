$(document).ready(function(){
  $('.parallax').parallax();
  $('.slider').slider();
}); 
function initMap() {
    var uluru = {lat:18.9585129, lng: -98.7526902};
    var map = new google.maps.Map(document.getElementById('map'), {
      zoom: 15,
      center: uluru
    });
    var marker = new google.maps.Marker({
      position: uluru,
      map: map
});
}