<?php 
/********* Api to access wp-json ******/

error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Headers: access_token");
use Twilio\Rest\Client;
require_once ABSPATH. 'vendor/autoload.php';


//composer require twilio/sdk
add_action( 'rest_api_init', 'register_api_hooks' );
// API custom endpoints for WP-REST API
function register_api_hooks() {
  // Register a custom REST API route for user login
    register_rest_route(
        'VOGOFAMILY.OLD', '/login/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_login',
        )
    );
	// Register a custom REST API route for Mobile Auth
	register_rest_route(
        'VOGOFAMILY.OLD', '/mobile-auth/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_mobile_auth',
			'permission_callback' => '__return_true',
        )
    );
	// Register a custom REST API route for Resend otp
	register_rest_route(
        'VOGOFAMILY.OLD', '/resend-otp/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_resend_otp_api',
			'permission_callback' => '__return_true',
        )
    );
	// Register a custom REST API route for Signup
	register_rest_route(
        'VOGOFAMILY.OLD', '/SignUp/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_register',
        )
    );
	// custom REST API route for Forgot Password	
	register_rest_route(
        'VOGOFAMILY.OLD', '/forgot/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_forgot'
        )
    );
	// REST API route for View Profile
	register_rest_route(
        'VOGOFAMILY.OLD', '/view-profile/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_view_profile',
        )
    );
	// Product REST API route for Category List
	register_rest_route(
        'VOGOFAMILY.OLD', '/category-list/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_category_list',
        )
    );
    // Register a custom REST API route for fetching order details
	register_rest_route(
        'VOGOFAMILY.OLD', '/order-detail/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_order_detail',
        )
    );
	// Register a custom REST API route to retrieve a list of notifications
	register_rest_route(
        'VOGOFAMILY.OLD', '/notification-list/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_notification_list',
        )
    );
	// Register a custom REST API route to Social Login
	register_rest_route(
        'VOGOFAMILY.OLD', '/Social_login/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_social_login_register',
        )
    );
	// Register a custom REST API route to Change Password
	register_rest_route(
        'VOGOFAMILY.OLD', '/change-password/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_change_password',
        )
    );
	// Register a custom REST API route to handle "Add to Cart" functionality
	register_rest_route(
        'VOGOFAMILY.OLD', '/addtocart/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_addtocart',
        )
    );
	// Register a custom REST API route to retrieve the user's cart items
	register_rest_route(
        'VOGOFAMILY.OLD', '/cartlist/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_cartlist',
        )
    );
	// Register a custom REST API route to remove a product from the cart
	register_rest_route(
        'VOGOFAMILY.OLD', '/remove-product-cart/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_remove_product_cart',
        )
    );
	// Register a custom REST API route to update product quantity or details in the cart
	register_rest_route(
        'VOGOFAMILY.OLD', '/update-cart/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_update_cart',
        )
    );
	
	// Register a custom REST API route to empty the entire cart
	register_rest_route(
        'VOGOFAMILY.OLD', '/empty-cart/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_empty_cart',
        )
    );
	// Register a custom REST API route to update the user's address
	register_rest_route(
        'VOGOFAMILY.OLD', '/Update-Address/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_update_address',
        )
    );
	// Register a custom REST API route to fetch the list of saved addresses for a user
	register_rest_route(
        'VOGOFAMILY.OLD', '/Address-List/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_address_list',
        )
    );
	// Register a custom REST API route to handle the checkout process
	register_rest_route(
        'VOGOFAMILY.OLD', '/checkout/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_checkout',
        )
    );
	
	// Register a custom REST API route to add a product to the user's wishlist
	register_rest_route(
        'VOGOFAMILY.OLD', '/add-to-wishlist/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_add_to_wishlist',
        )
    );
	
	// Register a custom REST API route to fetch the user's wishlist items
	register_rest_route(
        'VOGOFAMILY.OLD', '/showwishlist/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_show_wishlist',
        )
    );
	// Register a custom REST API route to remove a product from the user's wishlist
	register_rest_route(
        'VOGOFAMILY.OLD', '/product-remove-wishlist/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_product_remove_wishlist',
        )
    );
	// Register a custom REST API route to retrieve a list of brands
	register_rest_route(
        'VOGOFAMILY.OLD', '/brands/',
        array(
            'methods'  => 'GET',
            'callback' => 'brands_listing',
        )
    );
	// Register a custom REST API route to retrieve a list of products
	register_rest_route(
        'VOGOFAMILY.OLD', '/product-list/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_products',
        )
    );
	// Register a custom REST API route to log out the current user
	register_rest_route(
        'VOGOFAMILY.OLD', '/logout/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_logout',
        )
    );
	// Register a custom REST API route to retrieve detailed information for a single product
	register_rest_route(
        'VOGOFAMILY.OLD', '/product-detail/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_product_detail',
        )
    );
	// Register a custom REST API route to submit a product rating and review
	register_rest_route(
        'VOGOFAMILY.OLD', '/product-rate-and-review/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_product_rate_and_review',
        )
    );
	// Register a custom REST API route to fetch reviews for a specific product
	register_rest_route(
        'VOGOFAMILY.OLD', '/product-review/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_product_review',
        )
    );
	// Register a custom REST API route to submit a review for a completed order
	register_rest_route(
        'VOGOFAMILY.OLD', '/order-review/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_order_review',
        )
    );
	// Register a custom REST API route to allow users to submit a review for an order
	register_rest_route(
        'VOGOFAMILY.OLD', '/order-review/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_order_review',
        )
    );
	// Register a custom REST API route to allow users to submit a product recommendation
	register_rest_route(
        'VOGOFAMILY.OLD', '/add-product-recommendation/',
        array(
            'methods'  => 'POST',
            'callback' => 'custom_add_product_recommendation',
        )
    );
	// Register a custom REST API route to fetch a list of product recommendations
	register_rest_route(
        'VOGOFAMILY.OLD', '/product-recommendation-list/',
        array(
            'methods'  => 'GET',
            'callback' => 'custom_get_product_recommendation',
        )
    );
	
    // REST API route for get city
	register_rest_route(
        'VOGOFAMILY.OLD', '/cities/',
        array(
            'methods'  => 'GET',
            'callback' => 'get_custom_city_data',
			'permission_callback' => '__return_true',
        )
    );
	
}
// Get all city data
function get_custom_city_data() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cities';

    $results = $wpdb->get_results("SELECT id, city_name, status, created_at FROM $table_name", ARRAY_A);

    if (empty($results)) {
		return [
                'status'  => false,
                'code'  => 400,
                'message' => 'No cities found.',
            ];
    }
	
	$status['status'] = true;
	$status['code'] = 200;
	$status['message'] = 'Cities retrieved successfully.';
	$status['data'] = $results;
	return $status;
}


// Product Recommendation Funcations
function custom_get_product_recommendation() {
	try {
		$data = json_decode(file_get_contents('php://input'), true);

        if (empty($data)) {
            $data = $_REQUEST;
        }
		
		$user_id = isset($data['access_token']) ? intval($data['access_token']) : 0;
		if (!$user_id) {
			return [
                'status'  => false,
                'code'  => 400,
                'message' => 'Missing required fields.',
            ];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'product_recommendations';
		$results = $wpdb->get_results($wpdb->prepare(
			"SELECT product_id FROM $table WHERE user_id = %d",
			$user_id
		));

		if (!$results) {
			return [
                'status'  => false,
                'code'  => 404,
                'message' => 'No recommendations found for this user.',
            ];
		}

		$product_data = [];
		$current_currency = get_woocommerce_currency_symbol();
		$currency_code = html_entity_decode($current_currency);
		foreach ($results as $result) {
			$product = wc_get_product($result->product_id);
			
			if ($product) {
				$product_data[] = [
					'id'          => $product->get_id(),
					'name'        => $product->get_name(),
					'price'       => $product->get_price(),
					'description' => $product->get_description(),
					'image'       => wp_get_attachment_url($product->get_image_id()),
					'permalink'   => get_permalink($product->get_id()),
					'sale_price' => $product->get_sale_price(),
					'currency_code' => $currency_code,
				];
			}
		}
		$status['status'] = true;
		$status['code'] = 200;
		$status['message'] = 'Data fetch successfully.';
		$status['data'] = $product_data;
		
		return $status;
	} catch (Exception $e) {
		return [
                'status'  => false,
                'code'  => 500,
                'message' => 'Error: ' . $e->getMessage(),
            ];
    }
}


// Product Add to recommendation
function custom_add_product_recommendation() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data)) {
            $data = $_REQUEST;
        }

        $user_id    = isset($data['access_token']) ? intval($data['access_token']) : 0;
        $product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;

        if (!$user_id || !$product_id) {
            return [
                'status'  => false,
                'code'  => 400,
                'message' => 'Missing required fields.',
            ];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'product_recommendations';

        // Check if already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND product_id = %d",
            $user_id, $product_id
        ));

        if ($exists) {
            return [
                'status'  => false,
				'code'  => 409,
                'message' => 'This product is already in your recommendation list.',
            ];
        }

        $inserted = $wpdb->insert($table, [
            'user_id'    => $user_id,
            'product_id' => $product_id,
        ]);

        if ($inserted) {
            return [
                'status'  => true,
				'code'  => 200,
                'message' => 'Product recommendation added successfully.',
            ];
        } else {
            return [
                'status'  => false,
				'code'  => 500,
                'message' => 'Failed to add recommendation.',
            ];
        }

    } catch (Exception $e) {
        return [
            'status'  => false,
			'code'  => 500,
            'message' => 'Error: ' . $e->getMessage(),
        ];
    }
}

?>