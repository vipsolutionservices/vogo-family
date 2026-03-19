<?php
/**
 * The template for displaying the footer
 */

if ( woodmart_get_opt( 'collapse_footer_widgets' ) && ( ! woodmart_get_opt( 'mobile_optimization', 0 ) || ( wp_is_mobile() && woodmart_get_opt( 'mobile_optimization' ) ) ) ) {
	woodmart_enqueue_inline_style( 'widget-collapse' );
	woodmart_enqueue_js_script( 'widget-collapse' );
}

$page_id                 = woodmart_page_ID();
$disable_prefooter       = get_post_meta( $page_id, '_woodmart_prefooter_off', true );
$disable_footer_page     = get_post_meta( $page_id, '_woodmart_footer_off', true );
$disable_copyrights_page = get_post_meta( $page_id, '_woodmart_copyrights_off', true );
$footer_classes          = '';

if ( woodmart_get_opt( 'footer-style' ) ) {
	$footer_classes .= ' color-scheme-' . woodmart_get_opt( 'footer-style' );
}
?>
<?php if ( woodmart_needs_footer() && ! woodmart_is_woo_ajax() ) : ?>
	<?php woodmart_page_bottom_part(); ?>
<?php endif; ?>

</div>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<style type="text/css">
	.fixed-audio-icon {
    position: fixed;
    top: 60px;
    left: 20px;
    z-index: 9999;
}

.fixed-audio-icon .vogo-audio-button {
    width: 48px;
    height: 48px;
    background: #30653e; /* or keep original */
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    cursor: pointer;
    
}

</style>
<?php if ( woodmart_needs_footer() ) : ?>
		<?php if ( ! $disable_prefooter && ( 'text' === woodmart_get_opt( 'prefooter_content_type', 'text' ) && woodmart_get_opt( 'prefooter_area' ) || 'html_block' === woodmart_get_opt( 'prefooter_content_type' ) && woodmart_get_opt( 'prefooter_html_block' ) ) ) : ?>
			<?php woodmart_enqueue_inline_style( 'footer-base' ); ?>
			<div class="wd-prefooter<?php echo woodmart_get_old_classes( ' woodmart-prefooter' ); ?>">
				<div class="container wd-entry-content">
					<?php if ( 'text' === woodmart_get_opt( 'prefooter_content_type', 'text' ) ) : ?>
						<?php echo do_shortcode( woodmart_get_opt( 'prefooter_area' ) ); ?>
					<?php else : ?>
						<?php echo woodmart_get_html_block( woodmart_get_opt( 'prefooter_html_block' ) ); ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endif ?>
		<?php if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'footer' ) ) : ?>
			<footer class="wd-footer footer-container<?php echo esc_attr( $footer_classes ); ?>">
				<?php if ( ! $disable_footer_page && woodmart_get_opt( 'disable_footer' ) ) : ?>
					<?php woodmart_enqueue_inline_style( 'footer-base' ); ?>
					<?php if ( 'widgets' === woodmart_get_opt( 'footer_content_type', 'widgets' ) ) : ?>
						<?php get_sidebar( 'footer' ); ?>
					<?php else : ?>
						<div class="container main-footer wd-entry-content">
							<?php echo woodmart_get_html_block( woodmart_get_opt( 'footer_html_block' ) ); ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( ! $disable_copyrights_page && woodmart_get_opt( 'disable_copyrights' ) ) : ?>
					<?php woodmart_enqueue_inline_style( 'footer-base' ); ?>
					<div class="wd-copyrights copyrights-wrapper wd-layout-<?php echo esc_attr( woodmart_get_opt( 'copyrights-layout' ) ); ?>">
						<div class="container wd-grid-g">
							<div class="wd-col-start reset-last-child">
								<?php if ( woodmart_get_opt( 'copyrights' ) != '' ) : ?>
									<?php echo do_shortcode( woodmart_get_opt( 'copyrights' ) ); ?>
								<?php else : ?>
									<p>&copy; <?php echo date( 'Y' ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>. <?php esc_html_e( 'All rights reserved', 'woodmart' ); ?></p>
								<?php endif ?>
							</div>
							<?php if ( woodmart_get_opt( 'copyrights2' ) != '' ) : ?>
								<div class="wd-col-end reset-last-child">
									<?php echo do_shortcode( woodmart_get_opt( 'copyrights2' ) ); ?>
								</div>
							<?php endif ?>
						</div>
					</div>
				<?php endif ?>
<div class="vogo-audio-button" id="audio-sidebar"><a href="#site-footer"></a></div> 

				<script>
document.addEventListener("DOMContentLoaded", function () {
    const icon = document.querySelector('.vogo-audio-button'); // Replace with actual class or ID
    if (icon) {
        let container = document.createElement('div');
        container.className = 'fixed-audio-icon';
        container.appendChild(icon);
        document.body.appendChild(container);
    }
});
</script>

<!--
<div class="open-app-bar">
   
				<div class="app-buttons"> <span class="open-text">Open app</span><a class="store-badge store-badge--google" href="https://play.google.com/store/apps?hl=ro" target="_blank"> <img width="81px" src="https://play.google.com/intl/en_us/badges/static/images/badges/en_badge_web_generic.png" alt="Google Play" decoding="async"></a>
   <a class="store-badge store-badge--apple" href="https://apps.apple.com/ro/" target="_blank"> <img width="90px" src="https://developer.apple.com/assets/elements/badges/download-on-the-app-store.svg" alt="App Store" decoding="async"></a></div>
</div>
							-->
			

			</footer>
		<?php endif ?>
	</div>
<?php endif ?>
<div class="wd-close-side wd-fill<?php echo woodmart_get_old_classes( ' woodmart-close-side' ); ?>"></div>
<?php do_action( 'woodmart_before_wp_footer' ); ?>
<?php wp_footer(); ?>


</body>
</html>
