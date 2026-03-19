
<?php
function display_provider_settings_page() {
    global $wpdb;

    // Fetch providers from the database
    // $providers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}provider_feeds");
    $providers = $wpdb->get_results("
        SELECT * 
        FROM {$wpdb->prefix}provider_feeds
        WHERE status = 'active'
    ");
    echo '<div class="wrap">';
    $current_page = $_GET['page'] ?? '';
    echo '<nav class="mb-6 border-b border-gray-200">';
    echo '<ul class="flex space-x-4 text-sm font-medium">';
    echo '<li><a href="' . admin_url('admin.php?page=provider-management') . '" class="pb-2 ' . ($current_page === 'provider-management' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">1. Manage Providers</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=provider-settings') . '" class="pb-2 ' . ($current_page === 'provider-settings' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">2. Field Mapping</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=provider-coefficients') . '" class="pb-2 ' . ($current_page === 'provider-coefficients' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">3. Coefficients / Slabs</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=test-provider-prices') . '" class="pb-2 ' . ($current_page === 'test-provider-prices' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">4. Test Price Modification</a></li>';
    echo '</ul>';
    echo '</nav>';
    echo '<h1 class="text-3xl font-bold mb-4">Provider Feed Settings</h1>';

    if (empty($providers)) {
        echo '<p class="text-red-500">No providers found. Please add providers first.</p>';
        return;
    }

    // Provider Selection Dropdown
    echo '<label for="provider_select" class="block text-lg font-semibold">Select Provider:</label>';
    echo '<select id="provider_select" class="w-full p-2 border rounded-md shadow-sm">';
    echo '<option value="">-- Select Provider --</option>';
    foreach ($providers as $provider) {
        echo "<option value='{$provider->id}'>" . esc_html($provider->provider_name) . "</option>";
    }
    echo '</select>';

    // Mapping Section (Hidden Initially)
    echo '<div id="mapping_section" style="display:none; margin-top: 20px;">';
    echo '<h3 class="text-xl font-semibold mt-4">Field Mapping</h3>';
    echo '<p class="text-gray-500">Map feed fields to Product Name, Category, and Price:</p>';
    
    // Placeholder for mapping
    echo '<div id="mapping_fields" class="mt-4"></div>';
    echo '<button id="save_mapping" class="bg-blue-500 text-white p-2 rounded-md mt-4 hover:bg-blue-600">Save Mapping</button>';
    echo '</div>';

    // Default WooCommerce Category Section (Hidden Initially)
    echo '<div id="default_category_section" style="display:none;" class="mt-8 p-6 bg-white border border-gray-200 rounded-lg shadow">';
    echo '<h3 class="text-2xl font-bold text-gray-800 mb-1">Set Default WooCommerce Category</h3>';
    echo '<p class="text-sm text-gray-600 mb-4">If no WooCommerce category is mapped, this default category will be used.</p>';
    echo '<div id="selected_category_label" class="text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 px-4 py-2 rounded mb-3">Saved Default Category: —</div>';
    echo '<div id="category_tree" class="border border-gray-300 rounded-md px-4 py-3 bg-gray-50 shadow-inner max-h-72 overflow-y-auto">';
    $woocommerce_categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    echo '<input type="text" id="category_search" placeholder="🔍 Search categories..." class="w-full px-4 py-2 mb-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:border-blue-300" />';
    echo '<ul id="category_tree_list" class="space-y-1 text-sm text-gray-700">';
    function render_collapsible_categories($categories, $provider, $parent = 0, $level = 0) {
        foreach ($categories as $category) {
            if ($category->parent != $parent) continue;
 
            $has_children = false;
            foreach ($categories as $child) {
                if ($child->parent == $category->term_id) {
                    $has_children = true;
                    break;
                }
            }
 
            $is_selected = (isset($provider) && $provider->default_woocommerce_category == $category->term_id) ? 'checked' : '';
            $item_id = 'cat_' . $category->term_id;
 
            $indent_class = 'pl-' . (2 + $level * 2);
            echo '<li class="category-item ' . $indent_class . '" data-name="' . strtolower(esc_attr($category->name)) . '">';
            echo '<div class="flex items-center gap-2 py-1">';
            echo '<span class="inline-block w-4"></span>';
            echo '<label class="flex items-center gap-2 cursor-pointer hover:bg-blue-50 px-2 py-1 rounded">';
            echo '<input type="radio" class="form-radio text-blue-600" name="default_woocommerce_category_radio" value="' . $category->term_id . '" ' . $is_selected . ' />';
            echo '<span>' . esc_html($category->name) . '</span>';
            echo '</label>';
            echo '</div>';
 
            if ($has_children) {
                echo '<ul id="' . $item_id . '" class="ml-6">';
                render_collapsible_categories($categories, $provider, $category->term_id, $level + 1);
                echo '</ul>';
            }
 
            echo '</li>';
        }
    }
    render_collapsible_categories($woocommerce_categories, $provider, 0);
    echo '</ul>';
    echo '<input type="hidden" id="default_woocommerce_category" />';
    echo '<input type="hidden" id="default_woocommerce_category" />';
    echo '</div>'; // end of #category_tree
    echo '<button id="save_default_category" class="bg-blue-500 text-white p-2 rounded-md mt-4 hover:bg-blue-600">Save Default Category</button>';

    echo '</div>'; // Closing wrapper div
    ?>

    <script>
   document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('category_search');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase().trim();
            const allItems = document.querySelectorAll('#category_tree_list .category-item');
            
            allItems.forEach(item => {
                const name = item.dataset.name || '';
                const label = item.querySelector('label');
                const isMatch = name.includes(searchTerm);

                if (isMatch) {
                    item.style.display = '';

                    // Unhide all ancestors
                    let parent = item.parentElement;
                    while (parent && parent.id !== 'category_tree_list') {
                        if (parent.tagName === 'UL') {
                            parent.style.display = '';
                        }
                        if (parent.tagName === 'LI') {
                            parent.style.display = '';
                        }
                        parent = parent.parentElement;
                    }
                } else {
                    item.style.display = 'none';
                }
            });

            // Also make sure any top-level parents that contain matching children are visible
            document.querySelectorAll('#category_tree_list > li').forEach(li => {
                const hasVisibleChild = li.querySelector('li[style="display:"]');
                if (hasVisibleChild) {
                    li.style.display = '';
                }
            });
        });
    }
    document.getElementById('provider_select').addEventListener('change', function() {
        var providerId = this.value;
        var mappingSection = document.getElementById('mapping_section');
        var mappingDiv = document.getElementById('mapping_fields');
        var categorySection = document.getElementById('default_category_section');

        if (!providerId) {
            mappingSection.style.display = "none";
            categorySection.style.display = "none";
            return;
        }

        mappingDiv.innerHTML = '<p class="text-gray-500">Loading headers...</p>';
        
        // Fetch feed headers for mapping
        fetch(ajaxurl + '?action=get_provider_feed_headers&provider_id=' + providerId)
        .then(response => response.json())
        .then(data => {
            if (data.success && Array.isArray(data.data.headers)) {
                var headers = data.data.headers;
                mappingDiv.innerHTML = ''; // Clear previous mappings

                var fields = ['product_name', 'category', 'price']; // Fixed fields

                fetch(ajaxurl + '?action=get_provider_mapping&provider_id=' + providerId)
                    .then(response => response.json())
                        .then(mappingData => {
            fields.forEach(function(field) {
                var label = document.createElement('label');
                label.innerHTML = field.replace('_', ' ').toUpperCase();
                label.classList.add("block", "font-medium", "text-gray-700", "mt-2");

                var select = document.createElement('select');
                select.name = 'mapping[' + field + ']';
                select.classList.add("w-full", "p-2", "border", "rounded-md", "shadow-sm");
                select.innerHTML = '<option value="">-- Select Field --</option>';

                headers.forEach(function(header) {
                    select.innerHTML += '<option value="' + header + '">' + header + '</option>';
                });

                // Prepopulate select with stored mapping if it exists
                if (mappingData.success && mappingData.data && mappingData.data.data && mappingData.data.data[field]) {
                    select.value = mappingData.data.data[field];  // Prepopulate with existing mapping
                }

                mappingDiv.appendChild(label);
                mappingDiv.appendChild(select);
            });
        })
    .catch(error => console.error('Error fetching mapping data:', error));

                mappingSection.style.display = "block";
            } else {
                mappingDiv.innerHTML = '<p class="text-red-500">No headers found in the feed.</p>';
            }
            
        });
                
        // Fetch default WooCommerce category for the provider
        fetch(ajaxurl + '?action=get_provider_default_category&provider_id=' + providerId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.default_category) {
                const defaultVal = data.data.default_category;
                const hiddenInput = document.getElementById('default_woocommerce_category');
                hiddenInput.value = defaultVal;

                const radio = document.querySelector(`input[name="default_woocommerce_category_radio"][value="${defaultVal}"]`);
                if (radio) {
                    radio.checked = true;
                }

                const label = radio?.closest('label');
                const name = label ? label.textContent.trim() : '';
                const displayEl = document.getElementById('selected_category_label');
                if (displayEl) displayEl.textContent = 'Selected: ' + name;
            }
        });
 

        // Show default category section
        categorySection.style.display = "block";
        
    });

    // Save Field Mapping
    document.getElementById('save_mapping').addEventListener('click', function() {
        var providerId = document.getElementById('provider_select').value;
        var formData = new FormData();

        formData.append('action', 'save_field_mapping');
        formData.append('provider_id', providerId);

        var selects = document.querySelectorAll("#mapping_fields select");
        selects.forEach(function(select) {
            if (select.value) {
                formData.append(select.name, select.value);
            }
        });

        fetch(ajaxurl, { method: "POST", body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("✅ Mapping saved successfully!");
            } else {
                alert("❌ Error saving mapping: " + data.message);
            }
        });
    });
        });
    
    // Removed select2 initialization as the element is no longer a <select>
              const saveBtn = document.getElementById('save_default_category');
              if (saveBtn) {
                  saveBtn.addEventListener('click', function () {
            var providerId = document.getElementById('provider_select').value;
            var defaultCategory = document.getElementById('default_woocommerce_category').value;
            console.log(providerId);
            console.log(defaultCategory);
            
            var formData = new FormData();
            formData.append('action', 'save_provider_default_category');
            formData.append('provider_id', providerId);
            formData.append('default_category', defaultCategory);

            fetch(ajaxurl, { method: "POST", body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("✅ Default category saved successfully!");
                } else {
                    alert("❌ Error saving category: " + data.message);
                }
            });
        });
    const displaySelectedCategory = () => {
        const selectedValue = document.getElementById('default_woocommerce_category')?.value;
        if (!selectedValue) return;

        const selectedRadio = document.querySelector(`input[name="default_woocommerce_category_radio"][value="${selectedValue}"]`);
        const displayEl = document.getElementById('selected_category_label');

        if (selectedRadio && displayEl) {
            const label = selectedRadio.closest('label');
            const name = label ? label.textContent.trim() : '';
            displayEl.textContent = 'Selected: ' + name;
        }
    };

    document.querySelectorAll('input[name="default_woocommerce_category_radio"]').forEach(el => {
        el.addEventListener('change', function () {
            document.getElementById('default_woocommerce_category').value = this.value;
        });
    });
    displaySelectedCategory();
    // Show selected category on initial load (if already pre-selected)
    displaySelectedCategory();
    }
    
    </script>

    <?php
}


// Add Provider Settings Page inside WooCommerce Menu
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce', 
        'Provider Settings', 
        'Provider Settings', 
        'manage_options', 
        'provider-settings', 
        'display_provider_settings_page'
       //  'display_provider_default_category_setting'
    );
});

function display_provider_coefficients_page() {
    global $wpdb;

   // $providers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}provider_feeds");
        $providers = $wpdb->get_results("
        SELECT * 
        FROM {$wpdb->prefix}provider_feeds
        WHERE status = 'active'
        ");
    echo '<div class="wrap max-w-4xl mx-auto">';
    $current_page = $_GET['page'] ?? '';
    echo '<nav class="mb-6 border-b border-gray-200">';
    echo '<ul class="flex space-x-4 text-sm font-medium">';
    echo '<li><a href="' . admin_url('admin.php?page=provider-management') . '" class="pb-2 ' 
         . ($current_page === 'provider-management' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') 
         . '">1. Manage Providers</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=provider-settings') . '" class="pb-2 ' 
         . ($current_page === 'provider-settings' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') 
         . '">2. Field Mapping</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=provider-coefficients') . '" class="pb-2 ' 
         . ($current_page === 'provider-coefficients' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') 
         . '">3. Coefficients / Slabs</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=test-provider-prices') . '" class="pb-2 ' 
         . ($current_page === 'test-provider-prices' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') 
         . '">4. Test Price Modification</a></li>';
    echo '</ul>';
    echo '</nav>';
    echo '<h1 class="text-4xl font-bold mb-6 text-center text-gray-800">Category Coefficients</h1>';

    // ====== Global Default Coefficients Section ======
    // (We leave this in place. If you want a complete revert, remove this block.)
    echo '<div id="default_coefficients_wrapper" class="hidden mt-6 bg-white p-6 rounded-lg shadow">';
      echo '<h3 class="text-2xl font-semibold mb-4 text-gray-800">Global Default Coefficient & Slabs</h3>';
      echo '<p class="text-gray-600 mb-4">Set a default coefficient and slabs for this provider. Any category with no explicit override will use these values.</p>';
      echo '<div id="default_coefficient_area" class="bg-gray-100 p-4 rounded shadow-inner mb-4"></div>';
    echo '</div>';

    if (empty($providers)) {
        echo '<p class="text-red-500 text-center">No providers found. Please add providers first.</p>';
        return;
    }

    echo '<div class="bg-white shadow-lg rounded-lg p-6">';
    echo '<label for="provider_select" class="block text-lg font-semibold mb-2 text-gray-700">Select Provider:</label>';
    echo '<select id="provider_select" class="w-full p-3 border rounded-md shadow-sm focus:ring focus:ring-blue-300">';
    echo '<option value="">-- Select Provider --</option>';
    foreach ($providers as $provider) {
        echo "<option value='{$provider->id}'>" . esc_html($provider->provider_name) . "</option>";
    }
    echo '</select>';

    echo '<div id="category_coefficients_section" class="hidden mt-6 bg-gray-100 p-6 rounded-lg shadow-inner">';
    echo '<h3 class="text-2xl font-semibold mb-4 text-gray-800">Set Coefficients & Woo Categories</h3>';
    echo '<p class="text-gray-600 mb-4">Enter a coefficient for each category and manage WooCommerce mapping:</p>';
    echo '<div id="category_fields" class="space-y-4"></div>';
    echo '</div>'; // End category section
    echo '</div>'; // End main wrapper

    ?>
    <script>
    function renderSlabEditor(slabs, category, providerId) {
        let html = `<table class="w-full text-sm border mt-2">
            <thead>
                <tr>
                    <th class="border px-2 py-1">Min Price</th>
                    <th class="border px-2 py-1">Max Price</th>
                    <th class="border px-2 py-1">Coefficient</th>
                    <th class="border px-2 py-1">Actions</th>
                </tr>
            </thead>
            <tbody>`;

        slabs.forEach(slab => {
            html += `
            <tr>
                <td><input type="number" class="min-price border w-full px-1" value="${slab.min_price}" /></td>
                <td><input type="number" class="max-price border w-full px-1" value="${slab.max_price || ''}" /></td>
                <td><input type="number" step="0.01" class="coefficient border w-full px-1" value="${slab.coefficient}" /></td>
                <td>
                    <button class="update-slab bg-blue-500 text-white px-2 py-1 rounded text-xs"
                            data-id="${slab.id}"
                            data-provider="${providerId}"
                            data-category="${category}">
                        Save
                    </button>
                    <button class="delete-slab bg-red-500 text-white px-2 py-1 rounded text-xs"
                            data-id="${slab.id}">
                        Delete
                    </button>
                </td>
            </tr>`;
        });

        // Row for adding a new slab
        html += `
            <tr>
                <td><input type="number" class="min-price border w-full px-1" placeholder="Min" /></td>
                <td><input type="number" class="max-price border w-full px-1" placeholder="Max or empty" /></td>
                <td><input type="number" class="coefficient border w-full px-1" placeholder="e.g. 1.2" /></td>
                <td><button class="add-slab bg-green-600 text-white px-2 py-1 rounded text-xs"
                            data-category="${category}"
                            data-provider="${providerId}">
                    Add
                </button></td>
            </tr>
        </tbody></table>`;

        return html;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const providerSelect = document.getElementById('provider_select');
        const categorySection = document.getElementById('category_coefficients_section');
        const categoryFields = document.getElementById('category_fields');

        // For default (global) area
        const defaultWrapper = document.getElementById('default_coefficients_wrapper');
        const defaultArea = document.getElementById('default_coefficient_area');

        providerSelect.addEventListener('change', function() {
            const providerId = this.value;
            if (!providerId) {
                defaultWrapper.classList.add('hidden');
                categorySection.classList.add('hidden');
                return;
            }

            // Show a loading message
            categoryFields.innerHTML = '<p class="text-gray-500 animate-pulse">Loading categories...</p>';

            // ====== Revert to the older method that calls get_provider_categories ======
            Promise.all([
                // 1) get_provider_categories
                fetch(`${ajaxurl}?action=get_provider_categories&provider_id=${providerId}`)
                    .then(res => res.json()),

                // 2) get_woocommerce_categories
                fetch(`${ajaxurl}?action=get_woocommerce_categories`)
                    .then(res => res.json()),

                // 3) get_provider_coefficients
                fetch(`${ajaxurl}?action=get_provider_coefficients&provider_id=${providerId}`)
                    .then(res => res.json())
            ])
            .then(([categoriesData, wooData, coeffData]) => {
                if (!categoriesData.success || !Array.isArray(categoriesData.data.categories)) {
                    categoryFields.innerHTML = '<p class="text-red-500">No categories found.</p>';
                    return;
                }

                const wooCategories = wooData.success ? wooData.data.categories : [];
                const existingCoefficients = coeffData.data?.data?.coefficients || {};
                const existingMappings = coeffData.data?.data?.mappings || {};

                // Optionally warn if no categories are mapped
                const allCategories = categoriesData.data.categories.map(cat => cat.trim());
                const mappedCategoryCount = allCategories.filter(cat =>
                    Array.isArray(existingMappings[cat]) && existingMappings[cat].length > 0
                ).length;
                if (mappedCategoryCount === 0) {
                //    alert('⚠️ This provider has no categories mapped to WooCommerce. Please map at least one.');
                }

                categoryFields.innerHTML = '';

                // Now build the UI for each category
                categoriesData.data.categories.forEach(category => {
                    const trimmedCategory = category.trim();

                    const wrapper = document.createElement('div');
                    wrapper.className = 'bg-white border rounded-lg p-4 shadow space-y-3';

                    const topRow = document.createElement('div');
                    topRow.className = 'flex items-center justify-between gap-4';

                    const label = document.createElement('span');
                    label.textContent = trimmedCategory;
                    label.className = 'font-semibold text-gray-800 flex-grow';

                    const select = document.createElement('select');
                    select.name = `woocommerce_mapping[${trimmedCategory}][]`;
                    select.className = 'woocommerce-category w-48 p-2 border rounded-md shadow-sm';
                    select.multiple = true;

                    // Build the options from wooCategories
                    wooCategories.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat.id;
                        option.textContent = cat.name;
                        // If existing mapping includes this cat ID, select it
                        if (existingMappings[trimmedCategory]?.includes(String(cat.id))) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });

                    // Coefficient input
                    const input = document.createElement('input');
                    input.type = 'number';
                    input.name = `coefficients[${trimmedCategory}]`;
                    input.className = 'w-24 p-2 border rounded-md shadow-sm text-center focus:ring focus:ring-blue-300';
                    input.placeholder = '1.0';
                    input.value = existingCoefficients[trimmedCategory] || '1';

                    // Mark changed fields for unsaved data
                    input.addEventListener('input', () => {
                        input.classList.add('border-yellow-400', 'ring-2', 'ring-yellow-300');
                        saveButton.classList.remove('bg-green-500', 'hover:bg-green-600');
                        saveButton.classList.add('bg-yellow-500', 'hover:bg-yellow-600');
                        saveButton.textContent = 'Save*';
                    });

                    const saveButton = document.createElement('button');
                    saveButton.textContent = 'Save';
                    saveButton.className = 'bg-green-500 text-white px-3 py-2 rounded-md hover:bg-green-600 ml-2';
                    saveButton.addEventListener('click', () => {
                        const coefficient = input.value.trim();
                        const selectedCategories = Array.from(select.selectedOptions).map(option => option.value);

                        const formData = new FormData();
                        formData.append('action', 'save_single_provider_coefficient');
                        formData.append('provider_id', providerId);
                        formData.append('category', trimmedCategory);
                        formData.append('coefficient', coefficient);
                        formData.append('woocommerce_categories', JSON.stringify(selectedCategories));

                        fetch(ajaxurl, { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => {
                                alert(data.success
                                    ? `✅ Saved for ${trimmedCategory}`
                                    : `❌ Error: ${data.message}`);
                                if (data.success) {
                                    input.classList.remove('border-yellow-400', 'ring-2', 'ring-yellow-300');
                                    saveButton.textContent = 'Save';
                                    select.classList.remove('border-yellow-400', 'ring-2', 'ring-yellow-300');
                                }
                            });
                    });

                    select.addEventListener('change', () => {
                        select.classList.add('border-yellow-400', 'ring-2', 'ring-yellow-300');
                        saveButton.classList.remove('bg-green-500', 'hover:bg-green-600');
                        saveButton.classList.add('bg-yellow-500', 'hover:bg-yellow-600');
                        saveButton.textContent = 'Save*';
                    });

                    topRow.appendChild(label);
                    topRow.appendChild(select);
                    topRow.appendChild(input);
                    topRow.appendChild(saveButton);
                    wrapper.appendChild(topRow);

                    // Slab toggle
                    const slabToggle = document.createElement('button');
                    slabToggle.textContent = '➕ Manage Slabs';
                    slabToggle.className = 'text-blue-600 hover:underline text-sm';
                    slabToggle.dataset.category = trimmedCategory;
                    slabToggle.dataset.providerId = providerId;
                    slabToggle.addEventListener('click', function() {
                        const containerId = `slabs-${trimmedCategory.replace(/\s+/g, '_')}`;
                        const slabContainer = document.getElementById(containerId);

                        if (!slabContainer) return;

                        slabContainer.classList.toggle('hidden');

                        if (!slabContainer.classList.contains('loaded')) {
                            slabContainer.innerHTML = '<p class="text-sm text-gray-500">Loading slabs...</p>';

                            fetch(`${ajaxurl}?action=get_category_slabs&provider_id=${providerId}&category=${encodeURIComponent(trimmedCategory)}`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    slabContainer.innerHTML = renderSlabEditor(data.data.slabs, trimmedCategory, providerId);
                                    slabContainer.classList.add('loaded');
                                } else {
                                    slabContainer.innerHTML = '<p class=\"text-red-500 text-sm\">Failed to load slabs.</p>';
                                }
                            });
                        }
                    });

                    wrapper.appendChild(slabToggle);

                    const slabWrapper = document.createElement('div');
                    slabWrapper.id = `slabs-${trimmedCategory.replace(/\s+/g, '_')}`;
                    slabWrapper.className = 'slabs-container hidden mt-4';
                    wrapper.appendChild(slabWrapper);

                    categoryFields.appendChild(wrapper);
                });

                categorySection.classList.remove('hidden');

                // Initialize select2 after the HTML is built
                setTimeout(() => {
                    jQuery('.woocommerce-category').select2({
                        placeholder: 'Select WooCommerce Categories',
                        allowClear: true
                    });
                }, 100);
            });

            // ========== Load Global Default Coefficients & Slabs ==========
            fetch(`${ajaxurl}?action=get_provider_coefficients&provider_id=${providerId}&category=__DEFAULT__`)
              .then(res => res.json())
              .then(defaultCoeffs => {
                let globalCoeff = '1.0';
                if (defaultCoeffs.success && defaultCoeffs.data && defaultCoeffs.data.data && defaultCoeffs.data.data.coefficients) {
                  globalCoeff = defaultCoeffs.data.data.coefficients['__DEFAULT__'] || '1.0';
                }

                // Build minimal UI for default coefficient
                let html = `<div class="flex items-center mb-2">`
                         +   `<span class="font-semibold mr-2">Default Coefficient:</span>`
                         +   `<input type="number" step="0.01" id="global_coefficient_input" class="border p-2 rounded" value="${globalCoeff}" />`
                         +   `<button id="save_default_coefficient" class="ml-2 bg-green-500 text-white px-3 py-1 rounded">Save Default</button>`
                         + `</div>`;

                // Add default slab editor
                html += `<div id="slabs-__DEFAULT__" class="slabs-container mt-4 bg-white p-3 border rounded">`
                      +   `<p class="font-semibold text-gray-800">Default Slabs</p>`
                      +   `<div id="default_slabs_editor" class="mt-2"><span class="text-gray-500">Loading slabs...</span></div>`
                      + `</div>`;

                defaultArea.innerHTML = html;
                defaultWrapper.classList.remove('hidden');

                // fetch default slabs
                fetch(`${ajaxurl}?action=get_category_slabs&provider_id=${providerId}&category=__DEFAULT__`)
                  .then(r => r.json())
                  .then(slabData => {
                    if (slabData.success) {
                      document.getElementById('default_slabs_editor').innerHTML =
                        renderSlabEditor(slabData.data.slabs, '__DEFAULT__', providerId);
                    } else {
                      document.getElementById('default_slabs_editor').innerHTML =
                        '<p class="text-red-500">Failed to load default slabs.</p>';
                    }
                  });

                // handle saving default coefficient
                document.getElementById('save_default_coefficient').addEventListener('click', () => {
                  let newVal = document.getElementById('global_coefficient_input').value.trim();
                  if (!newVal) {
                    alert('Please enter a valid default coefficient');
                    return;
                  }
                  const formData = new FormData();
                  formData.append('action', 'save_single_provider_coefficient');
                  formData.append('provider_id', providerId);
                  formData.append('category', '__DEFAULT__');
                  formData.append('coefficient', newVal);
                  formData.append('woocommerce_categories', JSON.stringify([])); // Not used for default
                  fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                      if (data.success) {
                        alert('✅ Default coefficient saved!');
                      } else {
                        alert('❌ Could not save default: ' + data.message);
                      }
                    });
                });
              });
        });

        // Slab event delegation
        if (!window.__slabEventBound) {
            document.addEventListener('click', function (e) {
                // Handle Add Slab
                if (e.target && e.target.classList.contains('add-slab')) {
                    const btn = e.target;
                    const row = btn.closest('tr');
                    const providerId = btn.dataset.provider;
                    const category = btn.dataset.category;
                    const min = row.querySelector('.min-price')?.value.trim();
                    const max = row.querySelector('.max-price')?.value.trim();
                    const coeff = row.querySelector('.coefficient')?.value.trim();

                    if (!min || !coeff) {
                        alert('❌ Please enter minimum price and coefficient.');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'save_category_slab');
                    formData.append('provider_id', providerId);
                    formData.append('category', category);
                    formData.append('min_price', min);
                    formData.append('max_price', max);
                    formData.append('coefficient', coeff);

                    const container = btn.closest('.slabs-container');
                    container.innerHTML = '<p class="text-gray-500 text-sm">Saving...</p>';

                    fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // Reload slabs
                            fetch(`${ajaxurl}?action=get_category_slabs&provider_id=${providerId}&category=${encodeURIComponent(category)}`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    container.innerHTML = renderSlabEditor(data.data.slabs, category, providerId);
                                    container.classList.add('loaded');
                                } else {
                                    container.innerHTML = '<p class="text-red-500 text-sm">Failed to reload slabs.</p>';
                                }
                            });
                        } else {
                            const errorMsg = data.data?.message || 'Unknown error while saving.';
                            const errorDiv = document.createElement('p');
                            errorDiv.className = 'text-red-500 text-sm error-message';
                            errorDiv.textContent = '❌ ' + errorMsg;
                            if (!container.querySelector('.error-message')) {
                                container.appendChild(errorDiv);
                            }
                        }
                    });
                }

                // Handle Delete Slab
                if (e.target && e.target.classList.contains('delete-slab')) {
                    const btn = e.target;
                    const slabId = btn.dataset.id;
                    const container = btn.closest('.slabs-container');
                    const category = container.id.replace('slabs-', '').replace(/_/g, ' ');
                    const providerId = document.getElementById('provider_select')?.value;

                    if (!confirm('Are you sure you want to delete this slab?')) return;

                    const formData = new FormData();
                    formData.append('action', 'delete_category_slab');
                    formData.append('id', slabId);

                    fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            fetch(`${ajaxurl}?action=get_category_slabs&provider_id=${providerId}&category=${encodeURIComponent(category)}`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    container.innerHTML = renderSlabEditor(data.data.slabs, category, providerId);
                                    container.classList.add('loaded');
                                } else {
                                    container.innerHTML = '<p class="text-red-500 text-sm">Failed to reload slabs.</p>';
                                }
                            });
                        } else {
                            alert('❌ ' + (data.data?.message || 'Failed to delete slab.'));
                        }
                    });
                }

                // Handle Update Slab
                if (e.target && e.target.classList.contains('update-slab')) {
                    const btn = e.target;
                    const row = btn.closest('tr');
                    const slabId = btn.dataset.id;
                    const providerId = btn.dataset.provider;
                    const category = btn.dataset.category;
                    const min = row.querySelector('.min-price')?.value.trim();
                    const max = row.querySelector('.max-price')?.value.trim();
                    const coeff = row.querySelector('.coefficient')?.value.trim();

                    if (!min || !coeff) {
                        alert('❌ Min price and coefficient are required.');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'update_category_slab');
                    formData.append('id', slabId);
                    formData.append('min_price', min);
                    formData.append('max_price', max);
                    formData.append('coefficient', coeff);
                    formData.append('provider_id', providerId);
                    formData.append('category', category);

                    btn.textContent = 'Saving...';
                    btn.disabled = true;

                    fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        btn.textContent = 'Save';
                        btn.disabled = false;

                        if (data.success) {
                            // Refresh slabs
                            fetch(`${ajaxurl}?action=get_category_slabs&provider_id=${providerId}&category=${encodeURIComponent(category)}`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    const container = document.getElementById(`slabs-${category.replace(/\s+/g, '_')}`);
                                    container.innerHTML = renderSlabEditor(data.data.slabs, category, providerId);
                                    container.classList.add('loaded');
                                }
                            });
                        } else {
                            alert('❌ ' + (data.data?.message || 'Failed to update slab.'));
                        }
                    });
                }
            });
            window.__slabEventBound = true;
        }
    });
    </script>
    <?php
}


function display_provider_default_category_setting() {
    global $wpdb;

    $providers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}provider_feeds");

    echo '<div class="wrap">';
    echo '<h1 class="text-3xl font-bold mb-4">Default WooCommerce Category Settings</h1>';

    if (empty($providers)) {
        echo '<p class="text-red-500">No providers found. Please add providers first.</p>';
        return;
    }

    echo '<label for="provider_select" class="block text-lg font-semibold">Select Provider:</label>';
    echo '<select id="provider_select" class="w-full p-2 border rounded-md shadow-sm">';
    echo '<option value="">-- Select Provider --</option>';
    foreach ($providers as $provider) {
        echo "<option value='{$provider->id}'>" . esc_html($provider->provider_name) . "</option>";
    }
    echo '</select>';

    echo '<div id="default_category_section" style="display:none; margin-top: 20px;">';
    echo '<h3 class="text-xl font-semibold mt-4">Set Default WooCommerce Category</h3>';
    echo '<p class="text-gray-500">If a provider category has no mapped WooCommerce category, this category will be used.</p>';
    
    echo '<div id="selected_category_label" class="text-sm text-gray-700 mb-2"></div>';
    echo '<select id="default_woocommerce_category" class="w-full p-2 border rounded-md shadow-sm">';
    echo '<option value="">-- Select Default Category --</option>';

    $woocommerce_categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    foreach ($woocommerce_categories as $category) {
        echo "<option value='{$category->term_id}'>" . esc_html($category->name) . "</option>";
    }

    echo '</select>';
    echo '<button id="save_default_category" class="bg-blue-500 text-white p-2 rounded-md mt-4 hover:bg-blue-600">Save Default Category</button>';
    echo '</div>';
    echo '</div>';
    ?>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('provider_select').addEventListener('change', function() {
            var providerId = this.value;
            var section = document.getElementById('default_category_section');
            if (!providerId) {
                section.style.display = "none";
                return;
            }
            section.style.display = "block";
        });

    //     document.getElementById('save_default_category').addEventListener('click', function() {
    //         var providerId = document.getElementById('provider_select').value;
    //         var defaultCategory = document.getElementById('default_woocommerce_category').value;

    //         var formData = new FormData();
    //         formData.append('action', 'save_provider_default_category');
    //         formData.append('provider_id', providerId);
    //         formData.append('default_category', defaultCategory);

    //         fetch(ajaxurl, { method: "POST", body: formData })
    //         .then(response => response.json())
    //         .then(data => {
    //             if (data.success) {
    //                 alert("✅ Default category saved successfully!");
    //             } else {
    //                 alert("❌ Error saving category: " + data.message);
    //             }
    //         });
    //     });
    // });

</script>
<?php
}




add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce', 
        'Category Coefficients', 
        'Category Coefficients', 
        'manage_options', 
        'provider-coefficients', 
        'display_provider_coefficients_page'
    );
});




function detect_feed_type($file_path) {
    $first_line = '';
    if (is_readable($file_path) && ($handle = fopen($file_path, 'r'))) {
        $first_line = fgets($handle);
        fclose($handle);
    }

    if (strpos($first_line, "\t") !== false) {
        return 'tsv';
    }

    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    if (in_array($extension, ['xls', 'xlsx'])) {
        return 'excel';
    }

    return 'csv';
}

function test_provider_price_modification_page() {
    global $wpdb;

    $providers = $wpdb->get_results("SELECT id, provider_name FROM {$wpdb->prefix}provider_feeds WHERE mapping IS NOT NULL AND mapping != '' AND status = 'active'", ARRAY_A);
    
    echo '<div class="wrap">';
    $current_page = $_GET['page'] ?? '';
    echo '<nav class="mb-6 border-b border-gray-200">';
    echo '<ul class="flex space-x-4 text-sm font-medium">';
    echo '<li><a href="' . admin_url('admin.php?page=provider-management') . '" class="pb-2 ' . ($current_page === 'provider-management' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">1. Manage Providers</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=provider-settings') . '" class="pb-2 ' . ($current_page === 'provider-settings' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">2. Field Mapping</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=provider-coefficients') . '" class="pb-2 ' . ($current_page === 'provider-coefficients' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">3. Coefficients / Slabs</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=test-provider-prices') . '" class="pb-2 ' . ($current_page === 'test-provider-prices' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">4. Test Price Modification</a></li>';
    echo '</ul>';
    echo '</nav>';
    echo '<h1 class="text-3xl font-bold mb-4">Test Price Modification</h1>';

    if (empty($providers)) {
        echo '<p class="text-red-500">❌ No providers found with field mapping.</p>';
        return;
    }

    echo '<form method="post">';
    echo '<label for="provider_select" class="block text-lg font-semibold">Select Provider:</label>';
    echo '<select name="provider_id" class="w-full p-2 border mr-2 rounded-md shadow-sm">';
    echo '<option value="">-- Select Provider --</option>';
    foreach ($providers as $provider) {
        echo "<option value='{$provider['id']}'>" . esc_html($provider['provider_name']) . "</option>";
    }
    echo '</select>';
    echo '<button type="submit" name="test_provider_feed" class="bg-green-500 text-white p-2 rounded-md mt-4 hover:bg-blue-600 hover:text-white">Test Price Modification</button>';
    echo '</form>';
    echo '</div>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_provider_feed'])) {
        $provider_id = intval($_POST['provider_id']);
        if (!$provider_id) {
            echo "<p class='text-red-500 mt-4'>❌ Invalid provider selected.</p>";
            return;
        }

        $provider = $wpdb->get_row($wpdb->prepare(
            "SELECT feed_url, provider_name, mapping, default_woocommerce_category FROM {$wpdb->prefix}provider_feeds WHERE id = %d",
            $provider_id
        ));

        $coefficients = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}provider_coefficients WHERE provider_id = %d",
            $provider_id
        ));
        $slabs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}provider_category_slabs WHERE provider_id = %d",
            $provider_id
        ));

        if (empty($coefficients) && empty($slabs)) {
            echo "<p class='text-red-500 mt-4'>❌ This provider does not have coefficients or slabs defined. Please add them first.</p>";
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $temp_file = download_remote_file($provider->feed_url);
        if (!$temp_file) {
            echo "<p class='text-red-500 mt-4'>❌ Failed to download feed file.</p>";
            return;
        }

        $feed_type = detect_feed_type($temp_file);

        if ($feed_type === 'tsv') {
            
            $csv_url = process_and_modify_texacom_feed($provider_id);
            error_log("✅ Detected TSV feed → Running modify_provider_feed_prices({$provider_id})");
        } else {
            $csv_url = modify_provider_feed_prices($provider_id);
            error_log("✅ Detected Excel/CSV → Running process_and_modify_texacom_feed({$provider_id})");
        }

        if ($csv_url) {
            echo "<p class='text-green-600 mt-4'>✅ Feed processed successfully.</p>";
            // echo "<p class='mt-4'><a href='$csv_url' class='inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700' download>⬇️ Download Modified CSV</a></p>";

            //$import_url = admin_url('admin.php?page=mp-import-progress&provider_index=' . $provider_id . '&processed_csv=' . urlencode(str_replace(site_url('/'), ABSPATH, $csv_url)));
          //  echo "<p class='mt-4'><a href='$import_url' class='inline-block bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700'>🚀 Import This Feed to WooCommerce</a></p>";
          echo "<p class='mt-4'><a href='$csv_url' class='inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 hover:text-white' download>⬇️ Download Modified CSV</a></p>";

//$import_url = admin_url('admin.php?page=mp-import-progress&provider_index=' . $provider_id . '&processed_csv=' . urlencode(str_replace(site_url('/'), ABSPATH, $csv_url)));
//$relative_csv_path = str_replace(site_url('/'), '', $csv_url);
//$import_url = admin_url('admin.php?page=mp-import-progress&provider_index=' . $provider_id . '&processed_csv=' . urlencode($relative_csv_path));
$wp_import_url = 'https://test07.vogo.family/wp-admin/admin.php?page=pmxi-admin-import';
echo "<div class='mt-4'>";
echo "<div class='mt-4'>";
echo "<label class='block font-semibold mb-1'>Import URL:</label>";
echo "<div class='flex items-center space-x-2'>";
echo "<input type='hidden' id='importUrl' value='" . esc_attr($csv_url) . "' class='w-[50%] bg-white p-2 border rounded' readonly />";
echo "<button type='button' onclick='copyImportUrl()' class='bg-gray-800 text-white px-3 py-1 rounded hover:bg-gray-900'>📋 Copy URL</button>";
//echo "<button type='button' onclick='window.open(\"$wp_import_url\", \"_blank\")' class='bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700'>🌐 Open WP Import Page</button>";
echo "</div>";
echo "</div>";

echo "<script>
function copyImportUrl() {
    const input = document.getElementById('importUrl');
    navigator.clipboard.writeText(input.value).then(() => {
        alert('✅ Import URL copied to clipboard!');
        window.open('$wp_import_url', '_blank');
    }).catch(err => {
        alert('❌ Failed to copy URL: ' + err);
    });
}
</script>";
        }
    }
}



add_action('admin_menu', function() {
    add_menu_page('Test Price Modification', 'Test Price Modification', 'manage_options', 'test-provider-prices', 'test_provider_price_modification_page');
});

function display_provider_management_page() {
    global $wpdb;
    $table_providers = $wpdb->prefix . 'provider_feeds';

    // ✅ Handle inline update
    if (isset($_POST['update_provider_inline'], $_POST['provider_name'], $_POST['feed_url'], $_POST['cron_schedule'], $_POST['cron_time'])) {
        $id = intval($_POST['provider_id']);
        $wpdb->update(
            $table_providers,
            [
                'provider_name' => sanitize_text_field($_POST['provider_name']),
                'feed_url'      => esc_url_raw($_POST['feed_url']),
                'cron_schedule' => sanitize_text_field($_POST['cron_schedule']),
                'cron_time'     => sanitize_text_field($_POST['cron_time']),
            ],
            ['id' => $id]
        );
        if (!empty($_FILES['new_feed_file']['tmp_name'])) {
            $file      = $_FILES['new_feed_file'];
            $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $upload_dir = wp_upload_dir();
            $filename  = 'provider_' . $id . '.' . $ext;
            $dest_path = $upload_dir['basedir'] . '/provider_feeds/' . $filename;

            if (!file_exists(dirname($dest_path))) {
                mkdir(dirname($dest_path), 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                $feed_url = $upload_dir['baseurl'] . '/provider_feeds/' . $filename;
                $wpdb->update($table_providers, ['feed_url' => $feed_url], ['id' => $id]);
                echo '<div class="notice notice-success"><p>✅ Provider feed file updated successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Failed to update feed file.</p></div>';
            }
        }
        echo '<div class="notice notice-success"><p>✅ Provider updated successfully!</p></div>';
    }

    // ✅ Handle activate/deactivate toggle
    if (!empty($_GET['toggle_provider_status']) && in_array($_GET['new_status'], ['active', 'inactive'])) {
        $wpdb->update(
            $table_providers,
            ['status' => sanitize_text_field($_GET['new_status'])],
            ['id' => intval($_GET['toggle_provider_status'])]
        );
        echo '<div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 my-4">✅ Provider status updated successfully!</div>';
    }

    // ✅ Handle form submission (Add Provider)
    if (isset($_POST['add_new_provider']) && !empty($_POST['provider_name']) && !empty($_POST['cron_schedule'])) {
        $provider_name  = sanitize_text_field($_POST['provider_name']);
        $cron_schedule  = sanitize_text_field($_POST['cron_schedule']);
        $cron_time      = sanitize_text_field($_POST['cron_time']);

        $feed_url   = '';
        $feed_mode  = $_POST['feed_mode'] ?? 'url';

        if ($feed_mode === 'url' && !empty($_POST['feed_url'])) {
            $feed_url = esc_url_raw($_POST['feed_url']);
        } elseif ($feed_mode === 'upload' && !empty($_FILES['feed_file']['tmp_name'])) {
            $file       = $_FILES['feed_file'];
            $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $upload_dir = wp_upload_dir();
            $filename   = 'uploaded_provider_' . time() . '.' . $ext;
            $dest_path  = $upload_dir['basedir'] . '/provider_feeds/' . $filename;

            if (!file_exists(dirname($dest_path))) {
                mkdir(dirname($dest_path), 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                $feed_url = $dest_path;
            } else {
                echo '<div class="bg-red-100 text-red-800 p-4 rounded my-4">❌ File upload failed.</div>';
                return;
            }
        }

        if (empty($feed_url)) {
            echo '<div class="bg-yellow-100 text-yellow-800 p-4 rounded my-4">⚠️ Please provide a feed URL or upload a file.</div>';
            return;
        }

        $wpdb->insert(
            $table_providers,
            [
                'provider_name' => $provider_name,
                'feed_url'      => $feed_url,
                'cron_schedule' => $cron_schedule,
                'cron_time'     => $cron_time
            ],
            ['%s', '%s', '%s', '%s']
        );

        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 my-4">✅ Provider added successfully!</div>';
    }

    // ✅ Handle delete
    if (!empty($_GET['delete_provider'])) {
        $wpdb->delete($table_providers, ['id' => intval($_GET['delete_provider'])], ['%d']);
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 my-4">Provider deleted successfully!</div>';
    }

    // Fetch providers
    $providers = $wpdb->get_results("SELECT * FROM $table_providers");

    ?>
    <div class="wrap">
        <?php
        // Navigation
        $current_page = $_GET['page'] ?? '';
        echo '<nav class="mb-6 border-b border-gray-200">';
        echo '<ul class="flex space-x-4 text-sm font-medium">';
        echo '<li><a href="' . admin_url('admin.php?page=provider-management') . '" class="pb-2 ' . ($current_page === 'provider-management' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">1. Manage Providers</a></li>';
        echo '<li><a href="' . admin_url('admin.php?page=provider-settings') . '" class="pb-2 ' . ($current_page === 'provider-settings' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">2. Field Mapping</a></li>';
        echo '<li><a href="' . admin_url('admin.php?page=provider-coefficients') . '" class="pb-2 ' . ($current_page === 'provider-coefficients' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">3. Coefficients / Slabs</a></li>';
        echo '<li><a href="' . admin_url('admin.php?page=test-provider-prices') . '" class="pb-2 ' . ($current_page === 'test-provider-prices' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">4. Test Price Modification</a></li>';
        echo '</ul>';
        echo '</nav>';
        ?>

        <h1 class="text-3xl font-bold mb-4">Manage Providers</h1>

        <!-- List of Providers (moved to the top) -->
        <div class="bg-white shadow-md rounded-lg p-6 w-full md:w-2/3">
            <h2 class="text-xl font-semibold mb-4">Existing Providers</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100 border-b">
                            <th class="px-4 py-2 text-left text-gray-600">Name</th>
                            <th class="px-4 py-2 text-left text-gray-600">Feed URL</th>
                            <!-- <th class="px-4 py-2 text-left text-gray-600">Schedule</th>
                            <th class="px-4 py-2 text-left text-gray-600">Cron Time</th>
                            <th class="px-4 py-2 text-left text-gray-600">Last Run</th> -->
                            <th class="flex px-4 py-2 space-x-2 justify-center items-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($providers) : ?>
                            <?php foreach ($providers as $provider) : ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-2"><?php echo esc_html($provider->provider_name); ?></td>
                                    <td class="px-4 py-2">
                                        <a href="<?php echo esc_url($provider->feed_url); ?>" target="_blank" class="text-blue-500 hover:underline">View</a>
                                    </td>
                                   <!-- <td class="px-4 py-2"><?php //echo esc_html(ucfirst($provider->cron_schedule)); ?></td>
                                    <td class="px-4 py-2"><?php // echo esc_html($provider->cron_time ?? '—'); ?></td>
                                    <td class="px-4 py-2"><?php //echo $provider->last_processed_at ? esc_html(date('Y-m-d H:i', strtotime($provider->last_processed_at))) : 'Never'; ?></td> -->
                                    <td class="flex px-4 py-2 space-x-2 justify-center items-center">
                                        <!-- Run Now 
                                        <a href="<?php //echo admin_url('admin.php?page=test-provider-prices&provider_id=' . $provider->id); ?>"
                                            class="flex items-center justify-content-center bg-blue-500 text-white px-3 py-1 text-sm rounded" style="color:white;">
                                            Run
                                        </a> -->

                                        <!-- Inline Edit -->
                                        <form method="post" class="inline-edit-form grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-gray-50 rounded border mt-2"
                                              style="display:none;" data-provider-id="<?php echo $provider->id; ?>" enctype="multipart/form-data">
                                            <input type="hidden" name="provider_id" value="<?php echo $provider->id; ?>" />
                                            <input type="text" name="provider_name" value="<?php echo esc_attr($provider->provider_name); ?>"
                                                   class="border px-2 py-1 rounded text-sm w-full" placeholder="Name" />
                                            <input type="text" name="feed_url" value="<?php echo esc_attr($provider->feed_url); ?>"
                                                   class="border px-2 py-1 rounded text-sm w-full bg-gray-100" placeholder="Feed URL or File Path" />
                                            <p class="text-xs text-gray-500 italic">This will update automatically when you upload a new file.</p>
                                            <select name="cron_schedule" class="border px-2 py-1 rounded text-sm w-full">
                                                <option value="daily"   <?php selected($provider->cron_schedule, 'daily'); ?>>Daily</option>
                                                <option value="weekly"  <?php selected($provider->cron_schedule, 'weekly'); ?>>Weekly</option>
                                                <option value="monthly" <?php selected($provider->cron_schedule, 'monthly'); ?>>Monthly</option>
                                            </select> 
                                            <input type="time" name="cron_time" value="<?php // echo esc_attr($provider->cron_time); ?>"
                                                   class="border px-2 py-1 rounded text-sm w-full" /> 
                                            
                                            <?php if (strpos($provider->feed_url, '/wp-content/') !== false) : ?>
                                                <div class="col-span-2">
                                                    <label class="block text-sm font-medium text-gray-700">Replace Feed File</label>
                                                    <input type="file" name="new_feed_file" accept=".csv,.xls,.xlsx" class="border px-2 py-1 rounded text-sm w-full" />
                                                </div>
                                            <?php else : ?>
                                                <p class="text-sm text-gray-600 col-span-2 italic">This provider uses a remote URL feed. You cannot upload a replacement file here.</p>
                                            <?php endif; ?>
                                            
                                            <div class="col-span-2 flex justify-end">
                                                <button type="submit" name="update_provider_inline" class="bg-green-500 text-white px-3 py-1 rounded">Update</button>
                                            </div>
                                        </form>
                                        <button class="toggle-edit bg-yellow-500 text-white px-3 py-1 text-sm rounded hover:bg-yellow-600">Edit</button>

                                        <!-- Logs 
                                        <a href="<?php //echo admin_url('admin.php?page=provider-logs&provider_id=' . $provider->id); ?>"
                                            class="flex items-center justify-content-center bg-gray-700 text-white px-3 py-1 text-sm rounded hover:bg-gray-800" style="color:white;">
                                            Logs
                                        </a> -->

                                        <!-- Activate / Deactivate -->
                                        <?php if ($provider->status === 'active') : ?>
                                            <a href="<?php echo esc_url(add_query_arg(['toggle_provider_status' => $provider->id, 'new_status' => 'inactive'])); ?>"
                                               class="flex items-center justify-content-center bg-yellow-600 text-white px-3 py-1 text-sm rounded hover:bg-yellow-700" style="color:white;">
                                                Deactivate
                                            </a>
                                        <?php else : ?>
                                            <a href="<?php echo esc_url(add_query_arg(['toggle_provider_status' => $provider->id, 'new_status' => 'active'])); ?>"
                                               class="flex items-center justify-content-center bg-green-600 text-white px-3 py-1 text-sm rounded hover:bg-green-700" style="color:white;">
                                                Activate
                                            </a>
                                        <?php endif; ?>

                                        <!-- Delete (if desired)
                                        <a href="<?php echo esc_url(add_query_arg('delete_provider', $provider->id)); ?>" 
                                           class="flex items-center justify-content-center bg-red-500 text-white px-3 py-1 text-sm rounded hover:bg-red-600"
                                           onclick="return confirm('Are you sure you want to delete this provider?');" style="color:white;">
                                           Delete
                                        </a>
                                        -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" class="px-4 py-2 text-center text-gray-500">No providers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Provider Form (moved to the bottom) -->
        <div class="bg-white shadow-md rounded-lg p-6 w-full md:w-2/3 mt-6">
            <h2 class="text-xl font-semibold mb-4">Add New Provider</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block font-medium text-gray-700">Provider Name</label>
                    <input type="text" name="provider_name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                </div>

                <div>
                    <label class="block font-medium text-gray-700 mb-1">Feed Source</label>
                    <label class="inline-flex items-center mr-4">
                        <input type="radio" name="feed_mode" value="url" checked class="form-radio text-blue-500 toggle-source" />
                        <span class="ml-2 text-sm">Feed URL</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="feed_mode" value="upload" class="form-radio text-blue-500 toggle-source" />
                        <span class="ml-2 text-sm">Upload File</span>
                    </label>
                </div>

                <div id="url_input_wrapper">
                    <label class="block font-medium text-gray-700">Feed URL</label>
                    <input type="url" name="feed_url" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                </div>

                <div id="upload_input_wrapper" class="hidden">
                    <label class="block font-medium text-gray-700">Upload File (.csv, .xls, .xlsx)</label>
                    <input type="file" name="feed_file" accept=".csv,.xls,.xlsx" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                </div>

                <div>
                    <label class="block font-medium text-gray-700">Cron Schedule</label>
                    <select name="cron_schedule" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-gray-700">Cron Time (24h format)</label>
                    <input type="time" name="cron_time" value="03:00" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                </div>

                <button type="submit" name="add_new_provider" class="w-full bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600">Save Provider</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // Toggle feed source (URL / Upload)
        const radioButtons = document.querySelectorAll('input.toggle-source[name="feed_mode"]');
        const urlWrapper    = document.getElementById('url_input_wrapper');
        const uploadWrapper = document.getElementById('upload_input_wrapper');

        radioButtons.forEach(radio => {
            radio.addEventListener('change', () => {
                const mode = radio.value;
                if (mode === 'url') {
                    urlWrapper.classList.remove('hidden');
                    uploadWrapper.classList.add('hidden');
                } else {
                    uploadWrapper.classList.remove('hidden');
                    urlWrapper.classList.add('hidden');
                }
            });
        });

        // Toggle inline edit form
        document.querySelectorAll('.toggle-edit').forEach(button => {
            button.addEventListener('click', () => {
                const form = button.previousElementSibling;
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            });
        });
    });
    </script>
    <?php
}


// Add Providers Management Page under WooCommerce
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce', 
        'Manage Providers', 
        'Manage Providers', 
        'manage_options', 
        'provider-management', 
        'display_provider_management_page'
    );
});

function add_categories_column_to_provider_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'provider_feeds';

    // Check if the column exists
    $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'categories'");
    if (!$column_exists) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN categories TEXT DEFAULT NULL");
        error_log("✅ 'categories' column added to $table_name");
    }
}
add_action('admin_init', 'add_categories_column_to_provider_table');

function render_provider_feed_upload_page() {
    global $wpdb;
    $providers = $wpdb->get_results("SELECT id, provider_name FROM {$wpdb->prefix}provider_feeds");

    echo '<div class="wrap">';
    $current_page = $_GET['page'] ?? '';
    echo '<nav class="mb-6 border-b border-gray-200">';
    echo '<ul class="flex space-x-4 text-sm font-medium">';
    echo '<li><a href="' . admin_url('admin.php?page=provider-settings') . '" class="pb-2 ' . ($current_page === 'provider-settings' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">1. Field Mapping</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=provider-coefficients') . '" class="pb-2 ' . ($current_page === 'provider-coefficients' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">2. Coefficients / Slabs</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=provider-management') . '" class="pb-2 ' . ($current_page === 'provider-management' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">3. Manage Providers</a></li>';
    echo '<li><a href="' . admin_url('admin.php?page=test-provider-prices') . '" class="pb-2 ' . ($current_page === 'test-provider-prices' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-blue-600') . '">4. Test Price Modification</a></li>';
    echo '</ul>';
    echo '</nav>';
    echo '<h1 class="text-3xl mb-4">Upload & Process Feed File</h1>';

    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('upload_provider_file');

    echo '<label class="block mb-1 font-semibold">Select Provider:</label>';
    echo '<select name="provider_id" class="w-1/2 p-2 border mb-4">';
    foreach ($providers as $provider) {
        echo "<option value='{$provider->id}'>{$provider->provider_name}</option>";
    }
    echo '</select><br><br>';

    echo '<label class="block mb-1 font-semibold">Choose CSV or Excel File:</label>';
    echo '<input type="file" name="provider_file" class="mb-4" required accept=".csv,.xls,.xlsx" /><br><br>';

    echo '<button type="submit" name="upload_provider_feed" class="button button-primary">Upload & Process</button>';
    echo '</form>';
    echo '</div>';

    // Handle upload here or from a helper file...
}




add_action('wp_ajax_save_all_provider_coefficients', 'save_all_provider_coefficients_handler');
function save_all_provider_coefficients_handler() {
    global $wpdb;
    $provider_id = intval($_POST['provider_id']);
    $data = json_decode(stripslashes($_POST['data']), true);

    if (!$provider_id || !is_array($data)) {
        wp_send_json_error(['message' => 'Invalid input.']);
    }

    $table = $wpdb->prefix . 'provider_coefficients';

    foreach ($data as $item) {
        $category = sanitize_text_field($item['category']);
        $coefficient = floatval($item['coefficient']);
        $woo = isset($item['woo']) && is_array($item['woo']) ? array_map('intval', $item['woo']) : [];
        $woo_json = wp_json_encode($woo);

        // Check if entry exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE provider_id = %d AND category_name = %s",
            $provider_id, $category
        ));

        if ($exists) {
            // Update
            $wpdb->update(
                $table,
                [
                    'coefficient' => $coefficient,
                    'woocommerce_category_ids' => $woo_json
                ],
                [
                    'provider_id' => $provider_id,
                    'category_name' => $category
                ]
            );
        } else {
            // Insert
            $wpdb->insert(
                $table,
                [
                    'provider_id' => $provider_id,
                    'category_name' => $category,
                    'coefficient' => $coefficient,
                    'woocommerce_category_ids' => $woo_json
                ]
            );
        }
    }

    wp_send_json_success();
}