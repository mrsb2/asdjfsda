<?php
/**
 * PDF Pass - Complete Template
 */

// Include styles
$this->template( 'pass/styles' );

// Get event details
$event_id = isset($attendee['event_id']) ? $attendee['event_id'] : (isset($attendee['post_id']) ? $attendee['post_id'] : null);
$event_start_time = $event_id ? tribe_get_start_time($event_id, 'H:i') : '';

// Get ticket price
$ticket_price = '';
if (isset($attendee['product_id'])) {
    $product = wc_get_product($attendee['product_id']);
    if ($product) {
        $ticket_price = $product->get_regular_price();
    }
}

// Get venue information
$venue_name = $event_id ? tribe_get_venue($event_id) : '';
$venue_address = $event_id ? tribe_get_address($event_id) : '';
$venue_city = $event_id ? tribe_get_city($event_id) : '';
$venue_zip = $event_id ? tribe_get_zip($event_id) : '';
$full_venue = trim($venue_name . ', ' . $venue_address . ', ' . $venue_city . ' ' . $venue_zip, ', ');

// Clean title (remove date part)
$full_title = $post->post_title;
$clean_title = $full_title;

if (strpos($full_title, "\n") !== false) {
    $lines = explode("\n", $full_title);
    if (count($lines) >= 2) {
        $clean_title = trim($lines[1]);
    }
}

// If still contains date info, extract event name
if (strpos($clean_title, '@') !== false || strpos($clean_title, 'July') !== false || strpos($clean_title, 'August') !== false) {
    if (preg_match('/([A-Za-z\s\d]+(?:Fest|Festival|Event|Concert)[A-Za-z\s\d]*)/', $full_title, $matches)) {
        $clean_title = trim($matches[1]);
    }
}

?>

<table class="tec-tickets__wallet-plus-pdf-table">
	<tr class="tec-tickets__wallet-plus-pdf-main-row">
		<!-- Event Image -->
		<td class="tec-tickets__wallet-plus-pdf-image-cell">
		<img class="tec-tickets__wallet-plus-pdf-event-image" src="<?php echo esc_url( $post_image_url ); ?>"  />
		</td>
		
		<!-- Ticket Info -->
		<td class="tec-tickets__wallet-plus-pdf-info-cell">
			<!-- Event Title -->
			<span class="tec-tickets__wallet-plus-pdf-post-title">
				<?php echo esc_html( trim($clean_title) ); ?>
			</span>
			<br>
			<!-- Ticket Information -->
            <br>
			<span class="tec-tickets__wallet-plus-pdf-info-label">Ticket ID:</span>
			<span class="tec-tickets__wallet-plus-pdf-info-value">
				<?php echo esc_html( $attendee['ticket_id'] ?? 'N/A' ); ?>
			</span>
            <br>
		

			<span class="tec-tickets__wallet-plus-pdf-info-label">Security code:</span>
			<span class="tec-tickets__wallet-plus-pdf-info-value">
				<?php echo esc_html( $attendee['security_code'] ?? 'N/A' ); ?>
			</span>
            <br>		
		
			<span class="tec-tickets__wallet-plus-pdf-info-label">Venue:</span>
			<span class="tec-tickets__wallet-plus-pdf-info-value">
				<?php echo esc_html( $full_venue ?: 'TBD' ); ?>
			</span>
	        <br>
		
		
			<span class="tec-tickets__wallet-plus-pdf-info-label">Price:</span>
			<span class="tec-tickets__wallet-plus-pdf-info-value">
				<?php echo $ticket_price ? number_format($ticket_price, 2) . ' Lei' : 'N/A'; ?>
			</span>
            <br>
		
	
			<span class="tec-tickets__wallet-plus-pdf-info-label">Open door:</span>
			<span class="tec-tickets__wallet-plus-pdf-info-value">
				<?php echo esc_html( $event_start_time ?: 'TBD' ); ?>
			</span>
            <br>
		
			<!-- Company Info -->
			<div class="tec-tickets__wallet-plus-pdf-company-info">
				FTESTIRMA X CU NUME SRL CUI: 5122254 J2021/862/1224
			</div>
		</td>
		
		<!-- QR Code -->
		<td class="tec-tickets__wallet-plus-pdf-qr-cell">

				<img class="tec-tickets__wallet-plus-pdf-qr-image" src="<?php echo esc_url( $qr_image_url ); ?>" style="border-left: 1pt solid #ccc; background: white;" />

		</td>
	</tr>
	
	<!-- Footer Row -->
	<tr class="tec-tickets__wallet-plus-pdf-footer-row">
		<td colspan="3" class="tec-tickets__wallet-plus-pdf-footer-cell">
		<?php	
		do_action( 'tribe_tickets_plus_wallet_plus_pdf_pass_footer_after_credit', $attendee['event_id'] ); 
		?>
		</td>
	</tr>
</table>

