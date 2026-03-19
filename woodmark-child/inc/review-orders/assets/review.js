jQuery(document).ready(function ($) {
  let rating = 0;

  $(".order-review-form .star").on("click", function () {
    rating = $(this).data("value");
    $(this).parent().find(".star").removeClass("selected");
    $(this)
      .parent()
      .find(".star")
      .each(function () {
        if ($(this).data("value") <= rating) {
          $(this).addClass("selected");
        }
      });
  });

  $(".submit-order-review").on("click", function () {
    let order_id = $(this).data("order");
    let comment = $("#order_review_comment_" + order_id).val();

    if (rating === 0 || comment.length < 3) {
      alert("Please select rating and enter a comment");
      return;
    }

    $.post(
      OrderReview.ajax_url,
      {
        action: "submit_order_review",
        nonce: OrderReview.nonce,
        order_id: order_id,
        rating: rating,
        comment: comment,
      },
      function (response) {
        if (response.success) {
          $(".order-review-message").text(response.data).css("color", "green");
          location.reload();
        } else {
          $(".order-review-message").text(response.data).css("color", "red");
        }
      }
    );
  });
});
