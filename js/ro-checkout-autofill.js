jQuery(document).ready(function ($) {
  $("#billing_postcode").on("blur", function () {
    const postcode = $(this).val().trim();
    const country = $("#billing_country").val();

    console.log("Postcode blur triggered");
    console.log("Postcode:", postcode);
    console.log("Country:", country);

    if (country === "RO" && postcode.length >= 4) {
      $.ajax({
        url: "https://app.zipcodebase.com/api/v1/search",
        method: "GET",
        data: {
          apikey: "b544f090-3ec6-11f0-95ee-734d7e7c86ae",
          codes: postcode,
          country: "ro",
        },
        success: function (response) {
          if (response?.results?.[postcode]?.length) {
            const location = response.results[postcode][0];
            const city = location.city;
            const countyName = location.state;

            $("#billing_city").val(city);

            // Match county name to <option> text in billing_state
            const $state = $("#billing_state");
            let matchedValue = null;

            $state.find("option").each(function () {
              const text = $(this).text().trim().toLowerCase();
              if (text === countyName.trim().toLowerCase()) {
                matchedValue = $(this).val();
                return false;
              }
            });

            console.log("Matching county:", countyName);
            console.log("Matched value:", matchedValue);
            console.log("All billing_state options:");
            $state.find("option").each(function () {
              console.log($(this).val(), $(this).text());
            });

            if (matchedValue !== null) {
              setTimeout(() => {
                $state
                  .val(matchedValue)
                  .trigger("change")
                  .trigger("change.select2");
              }, 200);
            } else {
              console.warn(
                "County not found in billing_state options:",
                countyName
              );
            }
          }
        },
        error: function (err) {
          console.warn("Could not auto-fill city/county from postcode:", err);
        },
      });
    }
  });
});
