<?php
/**
 * PDF Pass: Body - Ticket Info - Attendee Details - QR Image
 */

if ( empty( $qr_enabled ) || empty( $qr_image_url ) ) {
	echo '<div style="width: 33mm; height: 33mm; border: 1pt solid #ccc; background: white; display: table-cell; vertical-align: middle; text-align: center; color: #999; font-size: 8pt;">QR Code<br>Disabled</div>';
	return;
}

?>
<img 
	class="tec-tickets__wallet-plus-pdf-qr-image"
	width="33mm" 
	height="33mm" 
	src="<?php echo esc_url( $qr_image_url ); ?>" 
	style="border: 1pt solid #ccc; background: white;" 
/>