<?php
/**
 * PDF Pass: Body - Ticket Info - Attendee Details
 */

// Get event details
if (isset($attendee['event_id'])) {
    $event_id = $attendee['event_id'];
} elseif (isset($attendee['post_id'])) {
    $event_id = $attendee['post_id'];
} else {
    $event_id = null;
}

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

if (isset($attendee['event_id'])) {
    $event_id = $attendee['event_id'];
} elseif (isset($attendee['post_id'])) {
    $event_id = $attendee['post_id'];
} else {
    $event_id = null;
}

if ($event_id) {
    $event_start_date = tribe_get_start_date($event_id, false, 'F j');
    $event_end_date = tribe_get_end_date($event_id, false, 'F j');
    $event_start_time = tribe_get_start_time($event_id, 'g:i a');
    $event_end_time = tribe_get_end_time($event_id, 'g:i a');
    
    $date_display = $event_start_date;
    if ($event_start_date !== $event_end_date) {
        $date_display .= ' - ' . $event_end_date;
    }
    $date_display .= ' @ ' . $event_start_time;
    if ($event_end_time && $event_end_time !== $event_start_time) {
        $date_display .= ' - ' . $event_end_time;
    }
} else {
    $date_display = 'Date TBD';
}


?>

<tr>
	<td class="tec-tickets__wallet-plus-pdf-attendee-details-wrapper">
		<table class="tec-tickets__wallet-plus-pdf-attendee-details-table">
			<tr>
				<td>

                        
                        
                        <span class="tec-tickets__wallet-plus-pdf-event-date">
                            <?php echo esc_html($date_display); ?>
                        </span>
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
					
					<div class="tec-tickets__wallet-plus-pdf-company-info">
						FIRMA X CU NUME SRL CUI: 5122254 J2021/862/1224
					</div>
				</td>
			</tr>
		</table>
	</td>
</tr>

<?php 
// Hook for sponsors - this is where your sponsor plugin will inject content
do_action( 'tribe_tickets_plus_wallet_plus_pdf_pass_footer_after_credit', $attendee['event_id'] ); 
?>