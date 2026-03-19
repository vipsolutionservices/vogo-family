document.addEventListener("DOMContentLoaded", function () {
  // Select the parent container
  const container = document.querySelector(".recommended-products-container");
  if (!container) return;

  // Event delegation: listen for clicks on .remove-recommended-product
  container.addEventListener("click", function (e) {
    const target = e.target.closest(".remove-recommended-product");
    if (!target) return;

    e.preventDefault();

    // Get the product ID from the data attribute
    const productId = target.dataset.productId;

    fetch(vogoRecAjax.ajax_url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        action: "remove_recommended_product",
        nonce: vogoRecAjax.nonce,
        product_id: productId,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          // Remove ONLY the matching box
          const productBox = document.querySelector(
            `.recommended-product-box[data-product-id="${productId}"]`
          );
          if (productBox) {
            productBox.remove();
          }
        } else {
          alert(data.data);
        }
      })
      .catch(() => {
        alert("An error occurred. Please try again.");
      });
  });
});
