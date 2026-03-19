<div class="wrap">
    <h1>Important Admin Links</h1>

    <style>
        .vogo-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .vogo-links-grid a {
            background: #fff;
            border-left: 4px solid #0073aa;
            padding: 15px;
            text-decoration: none;
            color: #000;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: all 0.2s ease-in-out;
        }
        .vogo-links-grid a:hover {
            border-left-color: #00a0d2;
            background: #f9f9f9;
        }
        .vogo-links-group h2 {
            margin-top: 40px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
    </style>

    <div class="vogo-links-group">
        <h2>User & Role Management</h2>
        <div class="vogo-links-grid">
            <a href="users.php">Manage Users</a>
            <a href="profile.php">Edit My Account</a>
            <a href="admin.php?page=add-provider">Add Provider Role</a>
            <a href="admin.php?page=add-expert">Add Expert Role</a>
            <a href="admin.php?page=add-transporter">Add Transporter Role</a>
        </div>
    </div>

    <div class="vogo-links-group">
        <h2>WooCommerce & Shop</h2>
        <div class="vogo-links-grid">
            <a href="edit.php?post_type=product">Products</a>
            <a href="edit-tags.php?taxonomy=product_cat&post_type=product">Categories</a>
            <a href="admin.php?page=wc-settings">Shop Settings</a>
            <a href="admin.php?page=wc-settings&tab=shipping">Shipping</a>
            <a href="admin.php?page=wc-settings&tab=checkout">Payments</a>
        </div>
    </div>

    <div class="vogo-links-group">
        <h2>Content & Design</h2>
        <div class="vogo-links-grid">
            <a href="edit.php?post_type=page">Pages</a>
            <a href="upload.php">Media Library</a>
            <a href="edit.php">Blog Posts</a>
            <a href="customize.php">Customizer</a>
            <a href="admin.php?page=slider-settings">Slider Images</a>
        </div>
    </div>

    <div class="vogo-links-group">
        <h2>Settings & Tools</h2>
        <div class="vogo-links-grid">
            <a href="admin.php?page=wp-rocket">WP Rocket</a>
            <a href="admin.php?page=gtranslate">Languages</a>
            <a href="options-general.php">General Settings</a>
            <a href="admin.php?page=notification-settings">Notifications</a>
            <a href="admin.php?page=mail-template">Mail Templates</a>
            <a href="admin.php?page=whatsapp-settings">WhatsApp</a>
            <a href="admin.php?page=integration-settings">Integrations</a>
        </div>
    </div>

</div>