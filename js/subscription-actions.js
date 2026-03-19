jQuery(document).ready(function ($) {
  // Handle Unsubscribe Action
  $(document).on("click", ".unsubscribe-btn", function (e) {
    e.preventDefault();
    let subscriptionId = $(this).data("id");
    let endDate = $(this).data("end-date");

    Swal.fire({
      title: "Are you sure?",
      text: `You are about to unsubscribe. Your subscription will remain active until ${endDate}.`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#f44336",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Yes, unsubscribe",
    }).then((result) => {
      if (result.isConfirmed) {
        let form = $("<form>", {
          method: "POST",
          action: window.location.href,
        });
        form.append(
          $("<input>", {
            type: "hidden",
            name: "subscription_id",
            value: subscriptionId,
          })
        );
        form.append(
          $("<input>", {
            type: "hidden",
            name: "unsubscribe",
            value: "1",
          })
        );
        $("body").append(form);
        form.submit();
      }
    });
  });

  // Handle Cancel Unsubscribe Action
  $(document).on("click", ".cancel-unsubscribe-btn", function (e) {
    e.preventDefault();
    let subscriptionId = $(this).data("id");

    Swal.fire({
      title: "Cancel Unsubscribe?",
      text: "This will reactivate your subscription and prevent it from being cancelled.",
      icon: "info",
      showCancelButton: true,
      confirmButtonColor: "#4caf50",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Yes, keep my subscription",
    }).then((result) => {
      if (result.isConfirmed) {
        let form = $("<form>", {
          method: "POST",
          action: window.location.href,
        });
        form.append(
          $("<input>", {
            type: "hidden",
            name: "subscription_id",
            value: subscriptionId,
          })
        );
        form.append(
          $("<input>", {
            type: "hidden",
            name: "cancel_unsubscribe",
            value: "1",
          })
        );
        $("body").append(form);
        form.submit();
      }
    });
  });
});
