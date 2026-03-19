jQuery(document).ready(function ($) {
  $("#address_select").change(function () {
    var address = $("#address_select").val();

    if (address) {
      $.ajax({
        url: ajaxurl,
        type: "GET",
        data: {
          action: "filter_categories",
          address: address, // Only send address, city is fetched from the cookie in PHP
        },
        success: function (response) {
          // Show the categories in the container
          $("#category_grid_container").html(response);
        },
      });
    }
  });
});
