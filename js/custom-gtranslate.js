document.addEventListener("DOMContentLoaded", function () {
  // Target the wrapper containing the GTranslate dropdown
  const wrapper = document.querySelector(".gtranslate_wrapper");

  if (wrapper) {
    // Hide the original dropdown
    const originalSelect = wrapper.querySelector(
      "select.gt_selector.notranslate"
    );
    if (originalSelect) {
      originalSelect.style.display = "none";
    }

    // Create the custom dropdown
    const customDropdown = document.createElement("select");
    customDropdown.id = "custom-language-selector";

    // Add default "Select Language" option
    const defaultOption = document.createElement("option");
    defaultOption.value = "";
    defaultOption.textContent = "Select Language";
    customDropdown.appendChild(defaultOption);

    // Add custom language options
    const languages = {
      "en|en": "English",
      "en|fr": "French",
      "en|ro": "Romanian",
    };

    for (const [value, label] of Object.entries(languages)) {
      const option = document.createElement("option");
      option.value = value;
      option.textContent = label;
      customDropdown.appendChild(option);
    }

    // Append the custom dropdown to the wrapper
    wrapper.appendChild(customDropdown);

    // Add functionality to switch languages on selection
    customDropdown.addEventListener("change", function () {
      const selectedValue = this.value;
      if (selectedValue) {
        const gtranslateFunction = window.doGTranslate;
        if (typeof gtranslateFunction === "function") {
          gtranslateFunction(selectedValue); // Trigger GTranslate
        }
      }
    });
  } else {
    console.error("GTranslate wrapper not found. Ensure the correct selector.");
  }
});
