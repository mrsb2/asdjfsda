<?php
/**
 * PDF Pass: Body - Ticket Info - Attendee Details - Security Code
 */

if (empty($attendee['security_code'])) {
    return;
}

if (isset($attendee['event_id'])) {
    $event_id = $attendee['event_id'];
} elseif (isset($attendee['post_id'])) {
    $event_id = $attendee['post_id'];
} else {
    echo 'Error: Event ID not available.';
    return;
}

$event_start_time = tribe_get_start_time($event_id, 'g:i A');
$event_end_time = tribe_get_end_time($event_id, 'g:i A');

// Get ticket price
$ticket_price = '';
if (isset($attendee['product_id'])) {
    $product = wc_get_product($attendee['product_id']);
    if ($product) {
        $ticket_price = $product->get_regular_price();
    }
}
?>
<div style="font-family: Arial, sans-serif; margin: 10px 0; display:flex; flex-direction: column; justify-content:start; align-items:start">

            <?php $this->template('pdf/pass/body/ticket-info/attendee-details/ticket-title'); ?>
            <span style="color: #666; font-style: italic; display: inline-block; width: 120px;">Security code:</span>
            <span style="font-weight: 500;"><?php echo esc_html(trim($attendee['security_code'])); ?></span>
            <br>
        
        
            <span style="color: #666; font-style: italic; display: inline-block; width: 120px;">Serie:</span>
            <span style="font-weight: 500;"><?php echo esc_html(trim($attendee['ticket_id'])); ?></span>
            <br>
        
        
            <span style="color: #666; font-style: italic; display: inline-block; width: 120px;">Pret:</span>
            <span style="font-weight: 500;"><?php echo $ticket_price ? number_format($ticket_price, 2) . ' lei' : 'N/A'; ?></span>
            <br>

       
            <span style="color: #666; font-style: italic; display: inline-block; width: 120px;">Open Doors:</span>
            <span style="font-weight: 500;"><?php echo esc_html($event_start_time); ?></span>
            <br>
     
            <span style="color: #666; font-style: italic; display: inline-block; width: 120px;">Concert:</span>
            <span style="font-weight: 500;"><?php echo esc_html($event_end_time); ?></span>

</div>