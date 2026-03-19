jQuery(document).ready(function ($) {
  $(document).on("click", ".add-to-reference", function (e) {
    e.preventDefault();

    let productId = $(this).data("product-id");

    $.ajax({
      url: addToReference.ajax_url,
      type: "POST",
      data: {
        action: "add_to_reference",
        product_id: productId,
        nonce: addToReference.nonce,
      },
      beforeSend: function () {
        Swal.fire({
          title: "Adding...",
          text: "Please wait while we add the product to reference.",
          timer: 1000,
          showConfirmButton: false,
          willOpen: () => {
            Swal.showLoading();
          },
        });
      },
      success: function (response) {
        if (response.success) {
          Swal.fire({
            toast: true,
            icon: "success",
            title: "Product added to reference!",
            position: "top-end",
            showConfirmButton: false,
            timer: 3000,
          });
        } else {
          Swal.fire({
            toast: true,
            icon: "info", // info icon
            title: response.data,
            position: "top-end",
            showConfirmButton: false,
            timer: 3000,
          });
        }
      },
      error: function () {
        Swal.fire({
          toast: true,
          icon: "error",
          title: "An error occurred. Please try again.",
          position: "top-end",
          showConfirmButton: false,
          timer: 3000,
        });
      },
    });
  });
});
