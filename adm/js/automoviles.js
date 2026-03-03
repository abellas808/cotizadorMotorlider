function get_modelo() {
  var marca = $("#marca").val();

  $.ajax({
    type: "POST",
    url: "ajax/get_modelo_marca.php",
    data: {
      marca: marca,
    },
    success: function (response) {
      if (response != "") {
        $("#select_marca").html(response);
        $("#div_marca").show();
      } else {
        $("#div_marca").hide();
      }
    },
  });
}
