<?php
/**
 * PDF Pass: Body - Event Date
 *
 * Create this file at:
 * [your-theme]/tribe/tickets-plus/tickets-wallet-plus/pdf/pass/body/event-date.php
 *
 * @since 1.0.0
 * @version 1.0.0
 */

// Get event details
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

<div class="tec-tickets__wallet-plus-pdf-event-date">

</div>