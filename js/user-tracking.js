jQuery(document).ready(function ($) {
    // Track product views
    if ($('body').hasClass('single-product')) {
        let productId = $('.product').data('product-id'); // Adjust selector if necessary
        if (productId) {
            $.post(ajax_object.ajax_url, {
                action: 'track_user_activity',
                user_id: ajax_object.user_id,
                activity: 'view_product',
                product_id: productId,
            });
        }
    }

    // Example: Track Add to Cart clicks
    $('.add_to_cart_button').on('click', function () {
        let productId = $(this).data('product_id');
        if (productId) {
            $.post(ajax_object.ajax_url, {
                action: 'track_user_activity',
                user_id: ajax_object.user_id,
                activity: 'add_to_cart',
                product_id: productId,
            });
        }
    });
});
