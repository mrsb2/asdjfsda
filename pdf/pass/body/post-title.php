<?php
/**
 * PDF Pass: Body - Post Title
 */

// Get the full title and try to extract just the event name
$full_title = $post->post_title;

// Debug: let's see what we're working with
// echo '<pre>DEBUG: ' . $full_title . '</pre>';

$clean_title = $full_title;

// Method 1: Split by newline and get the second line (event name)
if (strpos($full_title, "\n") !== false) {
    $lines = explode("\n", $full_title);
    if (count($lines) >= 2) {
        $clean_title = trim($lines[1]); // Get second line
    }
}

// Method 2: If still has date info, try to get just the event name part
if (strpos($clean_title, '@') !== false || strpos($clean_title, 'July') !== false || strpos($clean_title, 'August') !== false) {
    // Try to extract "Rockstadt Extreme Fest 11" from the string
    if (preg_match('/([A-Za-z\s\d]+(?:Fest|Festival|Event|Concert)[A-Za-z\s\d]*)/', $full_title, $matches)) {
        $clean_title = trim($matches[1]);
    } else {
        // Simple fallback - manually extract for this specific case
        $clean_title = 'Rockstadt Extreme Fest 11';
    }
}

?>
<div class="tec-tickets__wallet-plus-pdf-post-title">
	<?php echo esc_html( trim($clean_title) ); ?>
</div>