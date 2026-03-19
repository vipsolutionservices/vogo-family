<?php 
function custom_translate_shortcode() {
    ob_start();
    ?>
    <!-- Custom Language Dropdown -->
    <select id="custom-language-selector">
        <option value="en|en" selected>EN</option>
        <option value="en|fr">FR</option>
        <option value="en|ro">RO</option>
    </select>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            function loadGoogleTranslateScript(callback) {
                // Check if the script is already loaded
                const existingScript = document.querySelector('script[src*="translate_a/element.js"]');
                if (!existingScript) {
                    const script = document.createElement('script');
                    script.src = "https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit2";
                    script.async = true;
                    script.onload = callback; // Call the callback after the script is loaded
                    document.body.appendChild(script);
                    console.log("Google Translate script added.");
                } else {
                    console.log("Google Translate script already loaded.");
                    callback(); // If already loaded, execute the callback immediately
                }
            }

            function initializeGTranslate() {
                if (typeof window.google !== "undefined" && window.google.translate) {
                    console.log("Google Translate library loaded.");
                    const customDropdown = document.getElementById("custom-language-selector");

                    if (customDropdown) {
                        console.log("Custom dropdown initialized.");

                        // Trigger GTranslate for the default selected option (English)
                        if (typeof window.doGTranslate === "function") {
                            window.doGTranslate("en|en");
                            console.log("GTranslate function found. Default language set to English.");
                        }

                        // Add event listener for language switching
                        customDropdown.addEventListener("change", function () {
                            const selectedValue = this.value;
                            console.log("Selected value: ", selectedValue);

                            if (selectedValue && typeof window.doGTranslate === "function") {
                                window.doGTranslate(selectedValue); // Trigger language switch
                            } else {
                                console.error("GTranslate function is not available.");
                            }
                        });
                    } else {
                        console.error("Custom dropdown not found.");
                    }
                } else {
                    console.error("Google Translate library is not available.");
                }
            }

            // Load the GTranslate script and initialize
            loadGoogleTranslateScript(initializeGTranslate);
        });
    </script>
    <?php
    return ob_get_clean();
}
//add_shortcode('custom_translate', 'custom_translate_shortcode');
