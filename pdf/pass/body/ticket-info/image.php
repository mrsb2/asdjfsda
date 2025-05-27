<?php
/**
 * PDF Pass: Body - Ticket Info - Image
 */

if ( empty( $post_image_url ) ) {
	echo '<div style="width: 65mm; height: 60mm; background: linear-gradient(135deg, #4a90e2, #7b68ee); display: table-cell; vertical-align: middle; text-align: center; color: white; font-size: 12pt; font-weight: bold;">Image of event</div>';
	return;
}

?>
<img 
	class="tec-tickets__wallet-plus-pdf-event-image" 
	src="<?php echo esc_url( $post_image_url ); ?>" 
	style="width: 65mm; height: 60mm; object-fit: cover; object-position: center;"
/>