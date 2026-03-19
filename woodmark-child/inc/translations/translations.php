<?php
add_filter('gettext', 'custom_translate_woocommerce_tabs', 30, 4);
function custom_translate_woocommerce_tabs($translated, $text, $domain) {
    if ($domain === 'woocommerce') {
        switch ($text) {
            case 'Orders':
                $translated = 'Comenzi';
                break;
            case 'Products':
                $translated = 'Produse';
                break;
            case 'Coupons':
                $translated = 'Cupone';
                break;
                 case 'Coupons':
                $translated = 'Cupone';
                break;
                case 'Addresses':
                $translated = 'Adrese';
                break;
            case 'Downloads':
                $translated = 'Descărcări';
                break;
            case 'Dashboard':
                $translated = 'Panou de Control';
                break;
            case 'Account details':
                $translated = 'Detalii Cont';
                break;
            case 'Payment methods':
                $translated = 'Metode de plată';
                break;
                 case 'First name':
                $translated = 'Nume';
                break;
            case 'Last name':
                $translated = 'Prenume';
                break;
            case 'Display name':
                $translated = 'Nume afișat';
                break;
            case 'Email address':
                $translated = 'Adresă de email';
                break;
            case 'This will be how your name will be displayed in the account section and in reviews':
                $translated = 'Așa va apărea numele tău în cont și în recenzii';
                break;
            case 'Password change':
                $translated = 'Schimbare parolă';
                break;
            case 'Current password (leave blank to leave unchanged)':
                $translated = 'Parolă actuală (lasă necompletat dacă nu dorești schimbarea)';
                break;
            case 'New password (leave blank to leave unchanged)':
                $translated = 'Parolă nouă (lasă necompletat dacă nu dorești schimbarea)';
                break;
            case 'Confirm new password':
                $translated = 'Confirmă noua parolă';
                break;
                case 'Order':
                $translated = 'Comanda';
                break;
            case 'Date':
                $translated = 'Data';
                break;
            case 'Status':
                $translated = 'Stare';
                break;
            case 'Actions':
                $translated = 'Acțiuni';
                break;
                 case 'View':
                $translated = 'Vedere';
                break;
                 case 'Downloads remaining':
                $translated = 'Descarcari ramase';
                break;
                 case 'Expires':
                $translated = 'Expira';
                break;
                 case 'Download':
                $translated = 'Descărcare';
                break;
                case 'Billing address':
                $translated = 'Adresă de facturare';
                break;
            case 'Shipping address':
                $translated = 'Adresă de livrare';
                break;
            case 'Edit Billing Address':
                $translated = 'Editează adresa de facturare';
                break;
            case 'Edit Shipping Address':
                $translated = 'Editează adresa de livrare';
                break;
            case 'The following addresses will be used on the checkout page by default.':
                $translated = 'Adresele de mai jos vor fi utilizate implicit la finalizarea comenzii.';
                break;
                 case 'ADDITIONAL SAVED ADDRESSES':
                $translated = 'Adrese suplimentare salvate';
                break;
            case 'No additional addresses saved.':
                $translated = 'Nicio adresă suplimentară salvată.';
                break;
                case 'No saved methods found.':
                $translated = 'Nicio metodă salvată.';
                break;
            case 'Add payment method':
                $translated = 'Adaugă metodă de plată';
                break;

            // Add more translations here
        }
    }
    return $translated;
}

add_filter( 'gettext', 'custom_woocommerce_translate', 60, 3 );
function custom_woocommerce_translate( $translated_text, $text, $domain ) {
    if ( $domain === 'woocommerce' ) {
        if ( $text === 'Shipping' || $text === 'Shipping:' ) {
            $translated_text = 'Livrare';
        } elseif ( $text === 'Subtotal' || $text === 'Subtotal:' ) {
            $translated_text = 'Subtotal';  // Usually same, but you can customize if needed
        } elseif ( $text === 'Payment method:' ) {
            $translated_text = 'Metoda de plată:';
        }
    }
    return $translated_text;
}


add_filter('gettext', 'custom_woocommerce_reset_email_message', 20, 3);
function custom_woocommerce_reset_email_message($translated_text, $text, $domain) {
    if ($text === 'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox. Please wait at least 10 minutes before attempting another reset.') {
        $translated_text = 'Un e-mail de resetare a parolei a fost trimis la adresa de e-mail înregistrată pentru contul dvs., dar poate dura câteva minute să apară în căsuța dvs. de e-mail. Vă rugăm să așteptați cel puțin 10 minute înainte de a încerca o altă resetare.';
    }
    return $translated_text;
}

add_filter('gettext','custom_woocommerce_password_change_notify',20,3);
function custom_woocommerce_password_change_notify($translated_text, $text, $domain) {
    if ($text === 'Password reset email has been sent.') {
        $translated_text = 'E-mailul de resetare a parolei a fost trimis.';
    }
    return $translated_text;
}
function custom_woocommerce_lost_password_text($translated_text, $text, $domain) {
    if ($text === 'Lost your password? Please enter your username or email address. You will receive a link to create a new password via email.') {
        $translated_text = 'Ți-ai pierdut parola? Vă rugăm să introduceți numele dvs. de utilizator sau adresa de e-mail. Veți primi un link pentru a crea o nouă parolă prin e-mail.';
    }
    return $translated_text;
}
add_filter('gettext', 'custom_woocommerce_lost_password_text', 20, 3);

add_filter('gettext','custom_reset_password_button_text',20,3);
function custom_reset_password_button_text($translated_text, $text, $domain) {
    if ($text === 'Reset password') {
        $translated_text = 'Resetare parola';
    }
    return $translated_text;
}

function custom_lost_password_username_label($translated_text, $text, $domain) {
    // Handles default WooCommerce string

    // Handles customized theme/plugin version
    if ($text === 'Username or email *') {
        return 'Nume utilizator sau email *';
    }

    return $translated_text;
}
add_filter('gettext', 'custom_lost_password_username_label', 20, 3);

// function custom_woocommerce_text( $translated_text, $text, $domain ) {
//     if ( $domain === 'woocommerce' ) {
//         // Change the "Description" tab label
//         if ( $text === 'Description' ) {
//             $translated_text = 'Descriere';
//         }
//         // Change the "Additional Information" tab label
//         elseif ( $text === 'Additional information' ) {
//             $translated_text = 'Informații suplimentare';
//         }
//         elseif ( $text === 'Reviews' ) {
//             $translated_text = 'Recenzii';
//         }
//     }
//     return $translated_text;
// }
// add_filter( 'gettext', 'custom_woocommerce_text', 10, 3 );

function custom_woocommerce_text( $translated_text, $text, $domain ) {
    if ( $domain === 'woocommerce' ) {
        // Translate tabs
        if ( $text === 'Description' ) {
            return 'Descriere';
        } elseif ( $text === 'Additional information' ) {
            return 'Informații suplimentare';
        } elseif ( $text === 'Reviews (%d)' ) {
            return 'Recenzii (%d)';
        }
    }

    // Fallback: detect dynamic "Reviews (X)" pattern
    if ( preg_match( '/^Reviews \(\d+\)$/', $text ) ) {
        $translated_text = preg_replace( '/^Reviews/', 'Recenzii', $text );
    }

    return $translated_text;
}
add_filter( 'gettext', 'custom_woocommerce_text', 10, 3 );


function custom_translate_cart_buttons( $translated_text, $text, $domain ) {
    switch ( $text ) {
        case 'Related products':
            $translated_text = 'Produse similare';
            break;
        case 'View cart':
            $translated_text = 'Vezi coșul';
            break;
            case 'Product':
            $translated_text = 'Produs';
            break;
            case 'Price':
            $translated_text = 'Preţ';
            break;
            case 'Quantity':
            $translated_text = 'Cantitate';
            break;
             case 'Proceed to checkout':
            $translated_text = 'Finalizeaza';
            break;
        case 'Checkout':
            $translated_text = 'Finalizare comandă';
            break;
               case 'Shopping cart':
            $translated_text = 'Cărucior de cumpărături';
            break;

              case 'No products in the cart.':
            $translated_text = 'Nu există produse în coș.';
            break;
                case 'Your cart is currently empty.':
            $translated_text = 'Coșul tău este momentan gol.';
            break;
            case 'Apply coupon':
            $translated_text = 'Aplicați cuponul';
            break;
            case 'Coupon code':
            $translated_text = 'Cod cupon';
            break;
            case 'Change address':
            $translated_text = 'Schimbați adresa';
            break;
            case 'If you have a coupon code, please apply it below.':
            $translated_text = 'Dacă aveți un cod de cupon, vă rugăm să îl aplicați mai jos.';
            break;
             case 'Have a coupon?':
            $translated_text = 'Ai un cupon?';
            break;
             case 'Click here to enter your code':
            $translated_text = 'Faceți clic aici pentru a vă introduce codul';
            break;
             case 'Billing Details':
            $translated_text = 'Detalii de facturare';
            break;
             case 'Your Order':
            $translated_text = 'Comanda dvs';
            break;
           case 'Payment Information':
            $translated_text = 'Informații de plată';
            break;
             case 'Place order':
            $translated_text = 'Plasați comanda';
            break;
            case 'Ship to a different address?':
            $translated_text = 'Expediați la o altă adresă?';
            break;
            case 'Edit Your Details':
            $translated_text = 'Detalii cont';
            break;
  case 'Default sorting':
            $translated_text = 'Sortare implicită';
            break;
              case 'Sort by popularity':
            $translated_text = 'Sortare după popularitate';
            break;
              case 'Sort by average rating':
            $translated_text = 'Sortare după evaluare medie';
            break;
              case 'Sort by price: low to high':
            $translated_text = 'Sortare după preț: crescător';
            break;
              case 'Sort by price: high to low':
            $translated_text = 'Sortare după preț: descrescător';
            break;
              case 'Sort by latest':
            $translated_text = 'Sortare după cele mai noi';
            break;
			    case 'Reviews':
            $translated_text = 'Recenzii';
            break;
			    case 'There are no reviews yet.':
            $translated_text = 'Nu există încă recenzii.';
            break;
			    case 'Your review':
            $translated_text = 'Recenzia ta';
            break;
			  case 'Submit':
            $translated_text = 'Trimite';
            break;
			 case 'Your rating':
            $translated_text = 'Evaluarea ta';
            break;
			 case 'Your email address will not be published.':
            $translated_text = 'Adresa ta de email nu va fi publicată.';
            break;
			case 'Required fields are marked':
            $translated_text = 'câmpurile obligatorii sunt marcate';
            break;
			 case 'Save my name, email, and website in this browser for the next time I comment.':
            $translated_text = 'Salvează-mi numele, adresa de email și site-ul în acest browser pentru data viitoare când comentez.';
            break;    
    }
    return $translated_text;
}
add_filter( 'gettext', 'custom_translate_cart_buttons', 20, 3 );
add_action('wp_footer', function () {
?>
<script>
// TAG#POST-TRANSLATE
document.addEventListener('DOMContentLoaded', () => {

    /* ------------------------------------------------------------------
       1️⃣  Put every language-specific replacement map here.
           Use the **final text you want to show** as the value.
           The key should be the raw word/phrase that G-Translate
           tends to produce in that target language, but *without
           accents and in lower-case* for consistency.
    ------------------------------------------------------------------ */
    const fixesByLang = {
        /* English ---------------------------------------------------------------- */
        en: {
            'pharma'              : 'Pharma',
            'boys'                : 'Kids',
            'shops'               : 'Shops',
            'vip assistance'      : '24x7 Assist',
            'travel'              : 'Turism',
            'centre commercial'   : 'Mall',
            'pharmaceutique'      : 'Pharma',
            'garcons'             : 'XXX2',
            'bienvenue chez vogo' : 'Bienvenue a VOGO',
			'registration'		  : 'Register'
        },

        /* French  (example – tweak as needed) ------------------------------------ */
        fr: {
            'pharma'              : 'Pharma',
            'boys'                : 'Enfants',
            'shops'               : 'Magasins',
			'magasins':'Shop',
           // 'vip assist'      : 'Assistance24x7',
			//'assistance 24h/24 et 7j/7' : 'VIP Assist',
			'assistance vip': 'Assistance24x7',
            'travel'              : 'Travel3',
            'voyage2'              : 'Travel',			
            'centre commercial'   : 'Mall',
            'pharmaceutique'      : 'Pharma',
            'garcons'             : 'Kids',			
            'bienvenue chez vogo' : 'Bienvenue en VOGO'
        },

        /* Spanish (example) ------------------------------------------------------- */
        es: {
            'pharma'              : 'Farma',
            'boys'                : 'Niños',
            'shops'               : 'Tiendas',
            'vip assistance'      : 'Asistencia 24×7',
            'vip assist' : 'Asistencia 24×7',
            'travel'              : 'Turismo',
            'centre commercial'   : 'Centro comercial',
            'pharmaceutique'      : 'Farma',
            'garcons'             : 'Niños',
            'bienvenue chez vogo' : 'Bienvenido a VOGO'
        }

        /* ➜ Add other languages exactly the same way (de, it, ru, …) */
    };

    /* 2️⃣  Normalise helper: trim, lower-case, strip accents -------------------- */
    const norm = str =>
        str
          .trim()
          .toLowerCase()
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '');

    /* 3️⃣  Get current target-language from the googtrans cookie ---------------- */
    const getTargetLang = () => {
        const m = document.cookie.match(/(?:^|;\s*)googtrans=\/[^\/]+\/([^;]+)/);
        return m ? m[1] : null;            // e.g. "en", "fr", "es"
    };

    /* 4️⃣  Replace text nodes only (keeps all markup intact) */
    const fixGTranslateWords = () => {
        const lang  = getTargetLang() || 'en';
        const fixes = fixesByLang[lang] || fixesByLang.en;
        if (!fixes) return;

        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode(node) {
                    /* Only process text that lives inside menu / nav elements */
                    if (
                        !node.parentElement?.closest(
                            'nav, .main-navigation, .elementor-nav-menu, a, li, span, font'
                        )
                    ) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return fixes[norm(node.nodeValue)]
                        ? NodeFilter.FILTER_ACCEPT
                        : NodeFilter.FILTER_REJECT;
                }
            }
        );

        for (let n = walker.nextNode(); n; n = walker.nextNode()) {
            n.nodeValue = fixes[norm(n.nodeValue)];
        }
    };

    /* 5️⃣  Wait a moment for G-Translate to finish, then patch ------------------ */
    setTimeout(fixGTranslateWords, 500);

    /* 6️⃣  Watch for later DOM updates (menu opens, AJAX fragments, …) ---------- */
    new MutationObserver(() => {
        clearTimeout(window._gtranslateFixTimeout);
        window._gtranslateFixTimeout = setTimeout(fixGTranslateWords, 500);
    }).observe(document.body, {childList: true, subtree: true});
});
</script>
<?php
});


add_action('wp_footer', function () {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const titleEl = document.querySelector("#reply-title");
        if (titleEl && titleEl.textContent.includes("Be the first to review")) {
            titleEl.innerHTML = titleEl.innerHTML.replace("Be the first to review", "Fii primul care scrie o recenzie");
        }

        const reviewElements = document.querySelectorAll(".wd-rating-summary-total, .woocommerce-review-link, .woocommerce-review-count");
        reviewElements.forEach(function (el) {
            const text = el.textContent.trim();
            if (text === "reviews") {
                el.textContent = "recenzii";
            } else if (text === "review") {
                el.textContent = "recenzie";
            } else if (/^\d+ reviews$/.test(text)) {
                const number = text.match(/^(\d+)/)[0];
                el.textContent = `${number} recenzii`;
            }
        });
    });
    </script>
    <?php
});

add_filter('woocommerce_checkout_fields', 'custom_translate_checkout_fields');
function custom_translate_checkout_fields($fields) {

    // Billing fields
    $fields['billing']['billing_first_name']['label'] = 'Nume';
    $fields['billing']['billing_last_name']['label'] = 'Prenume';
    $fields['billing']['billing_company']['label'] = 'Nume companie';
    $fields['billing']['billing_country']['label'] = 'Țară / Regiune';
    $fields['billing']['billing_address_1']['label'] = 'Adresă (stradă și număr)';
    $fields['billing']['billing_address_2']['label'] = 'Apartament, etaj etc. (opțional)';
    $fields['billing']['billing_city']['label'] = 'Oraș';
    $fields['billing']['billing_state']['label'] = 'Județ';
    $fields['billing']['billing_postcode']['label'] = 'Cod poștal';
    $fields['billing']['billing_phone']['label'] = 'Telefon';
    $fields['billing']['billing_email']['label'] = 'Adresă de email';

    // Shipping fields (if you allow shipping to different address)
    $fields['shipping']['shipping_first_name']['label'] = 'Nume';
    $fields['shipping']['shipping_last_name']['label'] = 'Prenume';
    $fields['shipping']['shipping_company']['label'] = 'Nume companie';
    $fields['shipping']['shipping_country']['label'] = 'Țară / Regiune';
    $fields['shipping']['shipping_address_1']['label'] = 'Adresă (stradă și număr)';
    $fields['shipping']['shipping_address_2']['label'] = 'Apartament, etaj etc. (opțional)';
    $fields['shipping']['shipping_city']['label'] = 'Oraș';
    $fields['shipping']['shipping_state']['label'] = 'Județ';
    $fields['shipping']['shipping_postcode']['label'] = 'Cod poștal';

    return $fields;
}

add_action('wp_footer', 'translate_checkout_fields_with_jquery', 100);
function translate_checkout_fields_with_jquery() {
    if (!is_checkout()) return;
    ?>
    <script>
    jQuery(document).ready(function($) {

        // Delay a bit to ensure all elements are loaded
        setTimeout(function () {

            // Placeholder translations
            $('#billing_address_1').attr('placeholder', 'Adresă (stradă și număr)');
            $('#billing_address_2').attr('placeholder', 'Apartament, etaj etc. (opțional)');
            $('#billing_city').attr('placeholder', 'Oraș');
            $('#billing_postcode').attr('placeholder', 'Cod poștal');
               $('#order_comments').attr('placeholder', 'Note comandă (opțional)');

            // Label translations
            $('label[for="billing_address_1"]').text('Adresă (stradă și număr) *');
            $('label[for="billing_address_2"]').text('Apartament, etaj etc. (opțional)');
            $('label[for="billing_state"]').text('Județ *');
            $('label[for="billing_city"]').text('Oraș *');
            $('label[for="billing_postcode"]').text('Cod poștal *');
                $('label[for="order_comments"]').text('Note comandă (opțional)');


        }, 500); // adjust delay if needed

    });
    </script>
    <?php
}


add_filter( 'wc_order_statuses', 'translate_order_statuses' );
function translate_order_statuses( $order_statuses ) {
    $order_statuses['wc-processing'] = _x( 'În procesare', 'Order status', 'woocommerce' );
    $order_statuses['wc-completed'] = _x( 'Finalizată', 'Order status', 'woocommerce' );
    $order_statuses['wc-on-hold'] = _x( 'În așteptare', 'Order status', 'woocommerce' );
    return $order_statuses;
}

add_action('wp_footer', 'custom_translate_order_notes_placeholder', 100);
function custom_translate_order_notes_placeholder() {
    if (is_checkout()) : ?>
        <script>
        document.addEventListener("DOMContentLoaded", function () {
            const textarea = document.getElementById("order_comments");
            if (textarea && textarea.placeholder.trim() === "Notes about your order, e.g. special notes for delivery.") {
                textarea.placeholder = "Note despre comanda ta, de ex. instrucțiuni speciale pentru livrare.";
            }
        });
        </script>
    <?php endif;
}

add_action('wp_footer', 'custom_translate_order_details_comment', 100);
function custom_translate_order_details_comment() {
    // Only run on "View Order" page (order details)
    global $wp;
    if (is_account_page() && isset($wp->query_vars['view-order'])) :
    ?>
        <script>
        document.addEventListener("DOMContentLoaded", function () {
            const textareas = document.querySelectorAll("textarea");

            textareas.forEach(function (textarea) {
                if (textarea.placeholder.trim() === "Your Comment") {
                    textarea.placeholder = "Comentariul tău";
                }
            });
        });
        </script>
    <?php
    endif;
}
function vogo_translate_login_form_text( $translated_text, $text, $domain ) {
    switch ( $translated_text ) {
        case 'Username or Email Address':
        case 'Username or email address':
            $translated_text = 'Adresa de email';
            break;

        case 'Password':
            $translated_text = 'Parolă';
            break;

        case 'Log in':
        case 'Log in':
            $translated_text = 'Autentificare';
            break;

        case 'Remember Me':
            $translated_text = 'Ține-mă minte';
            break;

        case 'Lost your password?':
            $translated_text = 'Ți-ai uitat parola?';
            break;
    }

    return $translated_text;
}
add_filter( 'gettext', 'vogo_translate_login_form_text', 20, 3 );

add_action('wp_footer', function() {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const createAccountBtn = document.querySelector('.create-account-button');
            if (createAccountBtn) {
                createAccountBtn.textContent = 'Creează un cont';
            }

            const noAccountText = document.evaluate(
                "//text()[contains(., 'No account yet?')]",
                document,
                null,
                XPathResult.FIRST_ORDERED_NODE_TYPE,
                null
            ).singleNodeValue;
            
            if (noAccountText) {
                noAccountText.textContent = 'Nu ai încă un cont?';
            }
        });
    </script>
    <?php
});

add_filter( 'woocommerce_default_address_fields', function( $fields ) {
    $fields['company']['label']       = 'Nume companie';
    $fields['country']['label']       = 'Țară / Regiune';
    $fields['address_1']['label']     = 'Adresă stradală';
    $fields['address_2']['label']     = ''; // optional line 2
    $fields['state']['label']         = 'Județ';
    $fields['city']['label']          = 'Oraș';
    $fields['postcode']['label']      = 'Cod poștal';
    return $fields;
});

add_action('wp_footer', function () {
    if (is_account_page()) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const button = document.querySelector('button[name="save_address"]');
            if (button && button.innerText.trim() === 'Save address') {
                button.innerText = 'Salvează adresa';
                button.value = 'Salvează adresa';
            }
        });
        </script>
        <?php
    }
});
// Translate Country and Phone labels in billing/shipping
function custom_translate_wc_address_fields( $fields ) {
    if ( isset($fields['billing_phone']) ) {
        $fields['billing_phone']['label'] = 'Telefon';
    }
    return $fields;
}
add_filter( 'woocommerce_billing_fields', 'custom_translate_wc_address_fields' );
add_filter( 'woocommerce_shipping_fields', 'custom_translate_wc_address_fields' );


add_action('wp_footer', function () {
    if (is_checkout() || is_account_page()) {
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Use a mutation observer to detect label changes
            const labelSelector = 'label[for="billing_state"]';
            const shippingLabelSelector = 'label[for="shipping_state"]';
            const targetNode = document.body;
            const observer = new MutationObserver(function () {
                const label = document.querySelector(labelSelector);
                if (label && label.textContent.includes("County")) {
                    label.innerHTML = 'Județ <abbr class="required" title="required">*</abbr>';
                }
                const labelShipping = document.querySelector(shippingLabelSelector);
                if (labelShipping && labelShipping.textContent.includes("County")) {
                   labelShipping.innerHTML = 'Județ <abbr class="required" title="required">*</abbr>';
                }
            });

            observer.observe(targetNode, {
                childList: true,
                subtree: true
            });

            // Also do an initial run just in case
            const label = document.querySelector(labelSelector);
            if (label && label.textContent.includes("County")) {
                label.innerHTML = 'Județ <abbr class="required" title="required">*</abbr>';
            }
            const labelShipping = document.querySelector(shippingLabelSelector);
                if (labelShipping && labelShipping.textContent.includes("County")) {
                   labelShipping.innerHTML = 'Județ <abbr class="required" title="required">*</abbr>';
                }
        });
        </script>
        <?php
    }
});

// For Checkout Page
add_filter('woocommerce_checkout_fields', function($fields) {
    if (isset($fields['billing']['billing_state'])) {
        $fields['billing']['billing_state']['class'][] = 'notranslate';
    }
    if (isset($fields['shipping']['shipping_state'])) {
        $fields['shipping']['shipping_state']['class'][] = 'notranslate';
    }
    return $fields;
});

// For My Account > Edit Address Page
add_filter('woocommerce_checkout_fields', function($fields) {
    if (isset($fields['billing']['billing_state'])) {
        $fields['billing']['billing_state']['input_class'][] = 'notranslate';
    }
    if (isset($fields['shipping']['shipping_state'])) {
        $fields['shipping']['shipping_state']['input_class'][] = 'notranslate';
    }
    return $fields;
});

add_filter('woocommerce_default_address_fields', function($fields) {
    if (isset($fields['state'])) {
        $fields['state']['input_class'][] = 'notranslate';
    }
    return $fields;
});


function vogo_translate_words_to_romanian_script() {
    // Only load this script on the "My Account > Orders" page
    if (is_account_page()) {
        ?>
        <script type="text/javascript">
            (function() {
                function translateWordsToRomanian() {
                    const replacements = {
                        "for": "pentru",
                        "items": "produse",
                        "item": "produse"
                        
                    };

                    function translateTextNode(node) {
                        const regex = new RegExp(`\\b(${Object.keys(replacements).join('|')})\\b`, 'gi');
                        node.nodeValue = node.nodeValue.replace(regex, (matched) => {
                            return replacements[matched.toLowerCase()];
                        });
                    }

                    function walkAndTranslate(node) {
                        if (node.nodeType === Node.TEXT_NODE) {
                            translateTextNode(node);
                        } else if (node.nodeType === Node.ELEMENT_NODE && node.nodeName !== "SCRIPT" && node.nodeName !== "STYLE") {
                            for (let child of node.childNodes) {
                                walkAndTranslate(child);
                            }
                        }
                    }

                    walkAndTranslate(document.body);
                }

                document.addEventListener("DOMContentLoaded", translateWordsToRomanian);
            })();
        </script>
        <?php
    }
}
add_action('wp_footer', 'vogo_translate_words_to_romanian_script');

add_filter('login_errors', 'custom_login_error_messages');
function custom_login_error_messages($error) {
    $translations = [
        'Username is required' => 'Numele de utilizator este obligatoriu',
        'Invalid username' => 'Nume de utilizator invalid',
        'Incorrect password' => 'Parolă incorectă',
        'Username is not registered' => 'Numele de utilizator nu este înregistrat',
        'Unknown email address. check again or try your username' => 'Adresă de email necunoscută. Verifică din nou sau încearcă cu numele de utilizator.',
        'The password field is empty' => 'Câmpul pentru parolă este gol'
        

    ];

    foreach ($translations as $en => $ro) {
        if (strpos($error, $en) !== false) {
            $error = str_replace($en, $ro, $error);
        }
    }

    return $error;
}
add_filter( 'woocommerce_checkout_required_field_notice', 'custom_checkout_required_field_notice', 10, 2 );
function custom_checkout_required_field_notice( $notice, $field_label ) {
    return sprintf( '%s este un câmp obligatoriu.', $field_label );
}
add_filter( 'woocommerce_add_error', 'custom_invalid_email_translation', 10, 1 );
function custom_invalid_email_translation( $error ) {
    if ( strpos( $error, 'Invalid billing email address' ) !== false ) {
        return str_replace(
            'Invalid billing email address',
            'Adresa de email de facturare nu este validă',
            $error
        );
    }
    return $error;
}

add_filter('gettext', 'custom_translate_order_received_texts', 20, 3);
function custom_translate_order_received_texts($translated_text, $text, $domain) {
    // Only target WooCommerce strings
    if ($domain === 'woocommerce') {
        switch ($translated_text) {
            case 'Order number:':
                return 'Număr comandă:';
            case 'Date:':
                return 'Data:';
            case 'Email:':
                return 'Email:';
            case 'Total:':
                return 'Total:';
            case 'Payment method:':
                return 'Metoda de plată:';
            case 'Cash on delivery':
                return 'Ramburs la livrare';
            case 'Thank you. Your order has been received.':
                return 'Mulțumim. Comanda ta a fost primită.';
            case 'Product':
                return 'Produs';
            case 'Subtotal':
                return 'Subtotal';
            case 'Details':
                return 'Detalii';
            case 'Order details':
                return 'Detalii comandă';
        }
    }
    return $translated_text;
}
