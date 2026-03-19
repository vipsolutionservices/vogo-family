<?php

 // Added by WP Rocket

 // Added by WP Rocket


/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

define('TWILIO_ACCOUNT_SID', 'ACd0d4e20bffea3bace8cc727d12371759');
define('TWILIO_AUTH_TOKEN', 'd22c82273a7e409aa75f63aeeec2d9a6');
define('TWILIO_VERIFY_SERVICE_SID', 'VAdbd00432a5dce3ffacbe4b52d1e9b14c');

define('STRIPE_SECRET_KEY', 'sk_test_51SKcCYPEjpwbBHYQagVVPDWQaPIO4HYunKWG0Pdaet20PXzDBK2XED7y81dauMIJFa9EpHCUnlOviZcROVeXnFDb00BPVkcjiN');


define('DISABLE_WP_CRON', false);
// 🔇 Suppress all errors from being shown on frontend
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING);

define( 'DB_NAME', 'u597251641_V3y7h' );

/** Database username */
define( 'DB_USER', 'u597251641_hq60V' );

/** Database password */
define( 'DB_PASSWORD', 'dTnzgMEcyc' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '2~lvYI :TypJ8z-*i[gO;]8C|?z8Rt2F9G[jQ_EqbWBP]v9KB5bXXR}54&?uMLT7' );
define( 'SECURE_AUTH_KEY',  '/*~y7{#ld/`;~(J[#N+o{Mq)a4Sf_%FpwAw=f0<[<w,_aV#!oJ&5_Nn:ouzLHo[&' );
define( 'LOGGED_IN_KEY',    'yT6W-KuWz21%c;+Z-W%(2a&)k8w?86(h2UGWnr%2`43-%Xmz)Kb&}enmI%X3d:gO' );
define( 'NONCE_KEY',        'xr]m&udFp~2)#qT@*Qv#DrIa[6+sZLx1KB(P>WUx_o~Zl#=j@Y81D_8L#Os`R2-s' );
define( 'AUTH_SALT',        '}T|nNoS#e*p /|,ZD$f*!zOTxN8O<rIq#ocVuF OQMjJ=%I%:*ZB`2uC?D0ui~uZ' );
define( 'SECURE_AUTH_SALT', 'Y97$}}!Y;mB:S}Z@&U=E3h+Az1QG)V$&jP.]c.YucaVr1T$J+J(qxdeTC2(1M44.' );
define( 'LOGGED_IN_SALT',   ')Z0w#X@dLS6g+U5W7ib}$60}%-5{?~G,Q;GA]8pQl_#5ccZN|35UK?:DU=C YOFj' );
define( 'NONCE_SALT',       '?OyipgM~nJNot5Rscw4O,iJCzZd>v|=IJ;_L0&_4@(-7[&EE`qes|RXb^o2sI,d,' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';
define('WP_MEMORY_LIMIT', '2048M'); // Increase memory limit
define('WP_MAX_MEMORY_LIMIT', '2048M');

define('REQUEST_NEW_PRODUCT', 'https://test07.vogo.family/solicitati-un-produs-nou/');
define('NEW_SERVICES', 'https://test07.vogo.family/recommend-new-service/');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
/* Add any custom values between this line and the "stop editing" line. */

define('JWT_AUTH_SECRET_KEY', 'mcFX<|s!tEYt(7vTQFJB}F|Y|6]>/a_W6|vBi-j?7pE>b0-eHuQT;,?5)mY$2ou1');
define('JWT_AUTH_CORS_ENABLE', true);
define('JWT_ALGO', 'HS256');

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

//FIREBASE
//https://console.firebase.google.com/u/1/project/vogo-family/overview
//vogofamily740@gmail.com
//https://console.firebase.google.com/u/1/project/vogo-family/settings/general/android:com.vogo.family
//https://console.cloud.google.com/iam-admin/serviceaccounts?authuser=1&project=vogo-family&hl=en-GB

define('FCM_USE_HTTP_V1', true);
define('FCM_PROJECT_ID', 'vogo-family'); // Project ID
define('GOOGLE_APPLICATION_CREDENTIALS', __DIR__ . '/secrets/vogo-family-e623e006c85b.json');

//AGORA - https://console.agora.io/ - google account vogofamily740@gmail.com
//https://github.com/AgoraIO/Tools/tree/master/DynamicKey/AgoraDynamicKey/php/src
//SSH cd /home/u597251641/domains/vogo.family/public_html/test07/wp-content/plugins/vogo-api
// mkdir -p lib/agora
// SSH: git clone https://github.com/AgoraIO/Tools.git _agora_tools
//cp -r _agora_tools/DynamicKey/AgoraDynamicKey/php/src lib/agora/
//rm -rf _agora_tools
define('AGORA_APP_ID', '56c90354366043839a2451e8d4e1f52c');
define('AGORA_APP_CERTIFICATE', 'c1a7f690209f43e8a14ac2cd42e82ab2');
// opțional, TTL-ul tokenului (secunde)
define('AGORA_TOKEN_TTL', 3600);

/* That's all, stop editing! Happy publishing. */