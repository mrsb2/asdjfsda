<?php
/**
 * Plugin Name: Custom Events Grid
 * Description: Display events from The Events Calendar in a customizable grid with tag filtering, ticket prices, and sold-out status
 * Version: 1.9.0
 * Author: Vasiliu Ilie-Cristian - CODEVT SRL
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Custom_Events_Grid {
    /**
     * Constructor
     */
    public function __construct() {
        // Register shortcode
        add_shortcode('custom_events_grid', array($this, 'render_events_grid'));
        
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
    }
    
    /**
     * Register required CSS and JS
     */
    public function register_assets() {
        wp_register_style(
            'custom-events-grid-css',
            plugin_dir_url(__FILE__) . 'assets/css/custom-events-grid.css',
            array(),
            '1.2.0' . time() // Force browser to reload CSS
        );
        
        wp_register_script(
            'custom-events-grid-js',
            plugin_dir_url(__FILE__) . 'assets/js/custom-events-grid.js',
            array('jquery'),
            '1.2.0' . time(), // Force browser to reload JS
            true
        );
    }
    
    /**
     * Format price with the appropriate currency
     * 
     * @param float $price The price to format
     * @return string Formatted price with currency symbol
     */
    private function format_price($price) {
        // Format the number without decimals
        $formatted_price = number_format($price, 0);
        
        // Default currency
        $currency_symbol = 'Lei';
        $currency_code = 'RON';
        
        // Check for WooCommerce Currency Switcher plugin
        if (class_exists('WOOCS')) {
            global $WOOCS;
            if ($WOOCS) {
                // Get current currency from WOOCS
                $current_currency = $WOOCS->current_currency;
                $currencies = $WOOCS->get_currencies();
                
                if (isset($currencies[$current_currency])) {
                    $currency_code = $current_currency;
                    $currency_symbol = $currencies[$current_currency]['symbol'];
                }
            }
        } else {
            // Fallback to standard WooCommerce if Currency Switcher is not active
            if (function_exists('get_woocommerce_currency')) {
                $currency_code = get_woocommerce_currency();
            }
        }
        
        // Format with the correct currency based on the currency code
        switch ($currency_code) {
            case 'EUR':
                return $formatted_price . ' €';
            case 'USD':
                return '$' . $formatted_price;
            case 'GBP':
                return '£' . $formatted_price;
            case 'RON':
            default:
                return $formatted_price . ' Lei';
        }
    }
    
    
    public function is_event_valid_for_display($event_id) {
        // Set timezone to Romania/Bucharest
        $timezone = new DateTimeZone('Europe/Bucharest');
        
        // Create current time with correct timezone
        $current_datetime = new DateTime('now', $timezone);
        
        // Get event start date
        $event_start_datetime = new DateTime(tribe_get_start_date($event_id, false, 'Y-m-d H:i:s'), $timezone);
        
        // Get event categories
        $event_cats = wp_get_post_terms($event_id, 'tribe_events_cat', array('fields' => 'names'));
        
        // Check if event is a concert
        $is_concert = in_array('Concert', $event_cats);
        
        // For concerts, allow display for 3 hours after start time
        if ($is_concert) {
            $concert_display_end = clone $event_start_datetime;
            $concert_display_end->modify('+3 hours');
            
            return $current_datetime <= $concert_display_end;
        }
        
        // For other event types, use existing logic (end date >= current date)
        $event_end_datetime = new DateTime(tribe_get_end_date($event_id, false, 'Y-m-d H:i:s'), $timezone);
        return $current_datetime <= $event_end_datetime;
    }
    
    
    /**
     * Get ticket price for an event
     * 
     * @param int $event_id The event ID
     * @return array Ticket info with price and availability
     */
    public function is_ticket_quantity_hidden($ticket_id) {
        // Get all custom CSS
        $custom_css = wp_get_custom_css();
        
        // Create the selector to check
        $selector = '#tribe-block-tickets-item-' . $ticket_id . 
            ' .tribe-common-b3.tribe-tickets__tickets-item-extra-available';
        
        // Look for exact display: none rule for this selector
        $hide_pattern = preg_quote($selector, '/') . '\s*\{\s*display:\s*none\s*[;}\s]';
        
        // Use regex to check if the rule exists
        return preg_match('/' . $hide_pattern . '/i', $custom_css) === 1;
    }

    public function get_event_ticket_info($event_id) {
        $ticket_info = array(
            'has_tickets' => false,
            'price' => '',
            'min_price' => 0,
            'is_sold_out' => false,
            'tickets_at_location' => false,
            'available_ticket_found' => false,
            'status_message' => '',
            'tickets_left' => null
        );
        
        // Check if Event Tickets or Event Tickets Plus is active
        if (!function_exists('tribe_get_tickets') && !class_exists('Tribe__Tickets__Tickets')) {
            return $ticket_info;
        }
        
        // Set timezone to Romania/Bucharest
        $timezone = new DateTimeZone('Europe/Bucharest');
        
        // Create current time with correct timezone
        $current_datetime = new DateTime('now', $timezone);
        
        // Get event start and end dates
        $event_start_date = new DateTime(tribe_get_start_date($event_id, false, 'Y-m-d H:i:s'), $timezone);
        $event_end_date = new DateTime(tribe_get_end_date($event_id, false, 'Y-m-d H:i:s'), $timezone);
        
        // Check if Event Tickets or Event Tickets Plus is active
        if (class_exists('Tribe__Tickets__Tickets')) {
            $tickets = Tribe__Tickets__Tickets::get_event_tickets($event_id);
            
            if (!empty($tickets)) {
                $ticket_info['has_tickets'] = true;
                
                // Categorize tickets
                $ticket_categories = array(
                    'available' => array(),
                    'unavailable' => array(),
                    'sold_out' => array()
                );
                
                $total_tickets_left = 0;
                $tickets_quantity_visible = false;
                $all_sold_out = true;
                $online_tickets_available = false;
                
                foreach ($tickets as $ticket) {
                    // Check if this ticket's quantity is hidden
                    $is_quantity_hidden = $this->is_ticket_quantity_hidden($ticket->ID);
                    
                    // Get ticket details
                    $qty = property_exists($ticket, 'qty_sold') ? $ticket->qty_sold : 0;
                    $capacity = property_exists($ticket, 'capacity') ? $ticket->capacity : 0;
                    
                    // Get price
                    $price = property_exists($ticket, 'price') ? (float) $ticket->price : 0;
                    
                    // Check if this specific ticket is completely sold out
                    $is_ticket_sold_out = ($qty >= $capacity && $capacity > 0);
                    
                    // If at least one ticket is not sold out, set all_sold_out to false
                    if (!$is_ticket_sold_out) {
                        $all_sold_out = false;
                    }
                    
                    // Calculate tickets left
                    if ($capacity > 0 && !$is_quantity_hidden) {
                        $tickets_left = max(0, $capacity - $qty);
                        $total_tickets_left += $tickets_left;
                        $tickets_quantity_visible = true;
                    }
                    
                    // Check ticket sales period
                    $ticket_end_date = property_exists($ticket, 'end_date') ? $ticket->end_date : '';
                    $ticket_end_time = property_exists($ticket, 'end_time') ? $ticket->end_time : '';
                    
                    $online_tickets_available = true;
                    if (!empty($ticket_end_date) && !empty($ticket_end_time)) {
                        $ticket_end_time_date = $ticket_end_date . " " . $ticket_end_time;
                        
                        try {
                            $ticket_end_datetime = new DateTime($ticket_end_time_date, $timezone);
                            
                            // Check if ticket sales have ended
                            if ($current_datetime > $ticket_end_datetime) {
                                $online_tickets_available = false;
                            }
                        } catch (Exception $e) {
                            // In case of date parsing error, assume tickets are available
                            $online_tickets_available = true;
                        }
                    }
                    
                    // Categorize the ticket
                    if ($is_ticket_sold_out) {
                        $ticket_categories['sold_out'][] = $price;
                    } elseif (!$online_tickets_available) {
                        $ticket_categories['unavailable'][] = $price;
                    } else {
                        $ticket_categories['available'][] = $price;
                    }
                }
                
                // Set tickets left if quantity is visible
                if ($tickets_quantity_visible) {
                    $ticket_info['tickets_left'] = $total_tickets_left;
                }
                
                // Sort prices in descending order for each category
                foreach ($ticket_categories as &$category) {
                    rsort($category);
                }
                unset($category);
                
                // Determine final ticket status
                if ($all_sold_out) {
                    // If all tickets are sold out, mark as sold out regardless of event date
                    $ticket_info['is_sold_out'] = true;
                    $ticket_info['status_message'] = "SOLD OUT";
                } elseif (!$online_tickets_available && $current_datetime >= $event_start_date) {
                    // If event has started and no online tickets are available
                    $ticket_info['tickets_at_location'] = true;
                    $ticket_info['status_message'] = "Tickets at the venue!";
                }
                
                // Determine price based on ticket availability
                if (!empty($ticket_categories['available'])) {
                    // If any tickets are available, use the highest available price
                    $ticket_info['min_price'] = $ticket_categories['available'][0];
                    $ticket_info['price'] = $this->format_price($ticket_info['min_price']);
                } elseif (!empty($ticket_categories['unavailable'])) {
                    // If no available tickets, but some are unavailable, use the highest unavailable price
                    $ticket_info['min_price'] = $ticket_categories['unavailable'][0];
                    $ticket_info['price'] = $this->format_price($ticket_info['min_price']);
                } elseif (!empty($ticket_categories['sold_out'])) {
                    // If all tickets are sold out, use the highest sold out price
                    $ticket_info['min_price'] = $ticket_categories['sold_out'][0];
                    $ticket_info['price'] = $this->format_price($ticket_info['min_price']);
                    $ticket_info['is_sold_out'] = true;
                    $ticket_info['status_message'] = "SOLD OUT";
                }
            }
        }
        
        // Additional check for WooCommerce-based tickets (similar logic)
        if (class_exists('WC_Product') && function_exists('tribe_woo_tickets_get_tickets_ids')) {
            $woo_ticket_ids = tribe_woo_tickets_get_tickets_ids($event_id);
            
            if (!empty($woo_ticket_ids)) {
                $available_prices = array();
                $all_prices = array();
                $all_truly_sold_out = true;
                $online_tickets_available = false;
                
                foreach ($woo_ticket_ids as $product_id) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $price = $product->get_price();
                        
                        if ($price) {
                            $all_prices[] = (float) $price;
                        }
                        
                        // Check product stock and availability
                        if ($product->is_in_stock() && $product->is_purchasable()) {
                            $all_truly_sold_out = false;
                            $online_tickets_available = true;
                            
                            if ($price) {
                                $available_prices[] = (float) $price;
                            }
                        }
                    }
                }
                
                // Update ticket info based on WooCommerce tickets
                if ($all_truly_sold_out) {
                    $ticket_info['is_sold_out'] = true;
                    $ticket_info['status_message'] = "SOLD OUT";
                } elseif (!$online_tickets_available && $current_datetime >= $event_start_date) {
                    $ticket_info['tickets_at_location'] = true;
                    $ticket_info['status_message'] = "Tickets at the venue!";
                }
                
                // Update pricing if needed
                if (!empty($available_prices)) {
                    $ticket_info['min_price'] = min($available_prices);
                    $ticket_info['price'] = $this->format_price(min($available_prices));
                } elseif (!empty($all_prices)) {
                    $ticket_info['min_price'] = min($all_prices);
                    $ticket_info['price'] = $this->format_price(min($all_prices));
                }
            }
        }
        
        return $ticket_info;
    }
    
    /**
     * Render the events grid
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_events_grid($atts) {
        // Enqueue assets
        wp_enqueue_style('custom-events-grid-css');
        wp_enqueue_script('custom-events-grid-js');
        
        // Parse shortcode attributes
        $atts = shortcode_atts(
            array(
                'posts_per_page' => 30,
                'tag' => '',
                'category' => '',
                'featured' => false,
            ),
            $atts,
            'custom_events_grid'
        );
        
        // Start output buffering
        ob_start();
        
        // Check if The Events Calendar is active
        if (!class_exists('Tribe__Events__Main')) {
            return '<p>This shortcode requires The Events Calendar plugin to be installed and activated.</p>';
        }
        
        // Get ALL event categories (not just Concert and Festival)
        $event_cats = get_terms(array(
            'taxonomy' => 'tribe_events_cat',
            'hide_empty' => true, // Only show categories that have events
        ));
        
        // Display filter bar with ALL categories
        ?>
        <div class="event-filter">
            <span class="filter-label">Filter by:</span>
            <ul class="event-tags-filter">
                <li><a href="#" class="active filter-button" data-category="all">All</a></li>
                <?php if (!empty($event_cats) && !is_wp_error($event_cats)) : ?>
                    <?php foreach ($event_cats as $category) : ?>
                        <li>
                            <a href="#" class="filter-button" data-category="<?php echo esc_attr(strtolower($category->slug)); ?>">
                                <?php echo esc_html($category->name); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        
        <?php
        // Get current date to filter out past events
        $current_date = date('Y-m-d');
        
        // Get ALL events - ONLY FUTURE EVENTS (removed category filtering)
        $events_args = array(
            'posts_per_page' => -1,
            'post_type' => 'tribe_events',
            'eventDisplay' => 'custom',
            'order' => 'ASC',
            'orderby' => 'meta_value',
            'meta_key' => '_EventStartDate',
            'meta_query' => array(
                array(
                    'key' => '_EventEndDate',
                    'value' => $current_date,
                    'compare' => '>=',
                    'type' => 'DATE'
                )
            ),
        );
        
        // Apply shortcode filters if specified
        if (!empty($atts['tag'])) {
            $events_args['tag'] = $atts['tag'];
        }
        
        // Filter by specific category if specified in shortcode
        if (!empty($atts['category'])) {
            $events_args['tax_query'] = array(
                array(
                    'taxonomy' => 'tribe_events_cat',
                    'field' => 'slug',
                    'terms' => $atts['category'],
                ),
            );
        }
        
        // Filter by featured events if specified
        if (filter_var($atts['featured'], FILTER_VALIDATE_BOOLEAN)) {
            $events_args['featured'] = true;
        }
        
        // Get all events
        $all_events = tribe_get_events($events_args);
        
        // Filter events using the validation function
        $filtered_events = array();
        foreach ($all_events as $event) {
            if ($this->is_event_valid_for_display($event->ID)) {
                $filtered_events[] = $event;
            }
        }
        $events = $filtered_events;

        // Limit to the specified number of posts
        if (count($events) > $atts['posts_per_page']) {
            $events = array_slice($events, 0, $atts['posts_per_page']);
        }
        
        if (empty($events)) {
            echo '<p>No events found.</p>';
        } else {
            ?>
            <div class="custom-events-grid">
                <div class="events-grid-container">
                    <?php
                    foreach ($events as $event) {
                        // Get event categories
                        $event_cats = wp_get_post_terms($event->ID, 'tribe_events_cat', array('fields' => 'all'));
                        $category_classes = '';
                        $category_list = array();
                        
                        if (!empty($event_cats) && !is_wp_error($event_cats)) {
                            foreach ($event_cats as $cat) {
                                $slug = strtolower(esc_attr($cat->slug));
                                $category_classes .= ' category-' . $slug;
                                $category_list[] = $cat->name;
                            }
                        }
                        
                        // Get event date
                        $start_date = tribe_get_start_date($event->ID, false, 'M j, Y');
                        
                        // Get event image (16:9 ratio)
                        $image_id = get_post_thumbnail_id($event->ID);
                        $image_url = wp_get_attachment_image_url($image_id, 'large');
                        if (!$image_url) {
                            $image_url = plugin_dir_url(__FILE__) . 'assets/images/placeholder.jpg';
                        }
                        
                        // Get venue information
                        $venue_name = tribe_get_venue($event->ID);
                        
                        // Get ticket information
                        $ticket_info = $this->get_event_ticket_info($event->ID);
                        ?>
                        
                        <div class="event-card<?php echo esc_attr($category_classes); ?>">
                            <div class="event-header">
                                <a href="<?php echo esc_url(get_permalink($event->ID)); ?>" class="event-image-link">
                                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr(get_the_title($event->ID)); ?>" class="event-image">
                                    <?php if ($ticket_info['is_sold_out']) : ?>
                                        <div class="event-sold-out">SOLD OUT</div>
                                    <?php elseif ($ticket_info['tickets_at_location']) : ?>
                                        <div class="event-tickets-at-location">Tickets at the venue!</div>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="event-details">
                                <div class="event-date"><?php echo esc_html($start_date); ?></div>
                                
                                <h3 class="event-title">
                                    <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                                        <?php echo esc_html(get_the_title($event->ID)); ?>
                                    </a>
                                </h3>
                                
                                <?php if ($venue_name) : ?>
                                    <div class="event-venue">
                                        <span class="venue-name"><?php echo esc_html($venue_name); ?></span>
                                        <?php if ($ticket_info['tickets_left'] !== null) : ?>
                                            <div class="event-tickets-left">
                                                Tickets left: <?php echo esc_html($ticket_info['tickets_left']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="event-footer">
                                    <a href="<?php echo esc_url(get_permalink($event->ID)); ?>" class="event-link">View Details</a>
                                    <?php if ($ticket_info['has_tickets'] && !empty($ticket_info['price'])): ?>
                                        <div class="event-price"><?php echo esc_html($ticket_info['price']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php
        }
        
        // Return output buffer content
        return ob_get_clean();
    }
}

// Initialize the plugin
new Custom_Events_Grid();

// Create plugin directory structure on activation
register_activation_hook(__FILE__, 'custom_events_grid_activate');

function custom_events_grid_activate() {
    // Create assets directories if they don't exist
    $dirs = array(
        plugin_dir_path(__FILE__) . 'assets',
        plugin_dir_path(__FILE__) . 'assets/css',
        plugin_dir_path(__FILE__) . 'assets/js',
        plugin_dir_path(__FILE__) . 'assets/images',
    );
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
    
    // Create CSS file
    $css = custom_events_grid_get_default_css();
    file_put_contents(plugin_dir_path(__FILE__) . 'assets/css/custom-events-grid.css', $css);
    
    // Create JS file
    $js = custom_events_grid_get_default_js();
    file_put_contents(plugin_dir_path(__FILE__) . 'assets/js/custom-events-grid.js', $js);
}

/**
 * Default CSS for the grid
 */
function custom_events_grid_get_default_css() {
    return '/* Custom Events Grid CSS */
.custom-events-grid {
    width: 100%;
    margin: 20px 0;
}

.event-filter {
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}

.filter-label {
    margin-right: 10px;
    font-weight: bold;
}

.event-tags-filter {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    flex-wrap: wrap;
}

.event-tags-filter li {
    margin-right: 8px;
    margin-bottom: 8px;
}

.event-tags-filter a {
    display: inline-block;
    padding: 8px 16px;
    background-color: #f5f5f5;
    border-radius: 4px;
    text-decoration: none;
    color: #666;
    transition: all 0.3s ease;
    font-weight: 500;
    cursor: pointer;
}

.event-tags-filter a:hover,
.event-tags-filter a.active {
    background-color: var(--theme-color-text_link);
    color: white;
}

.events-grid-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.event-card {
    background-color: #fff;
    border-radius: 0;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border: 1px solid #eee;
    display: flex;
    flex-direction: column;
}

.event-card:hover {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.event-header {
    position: relative;
    width: 100%;
    padding-top: 56.25%; /* 16:9 aspect ratio */
    overflow: hidden;
    z-index: 1;
    margin-bottom: 0;
}

.event-image-link {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: block;
    z-index: 2;
}

.event-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

.event-details {
    padding: 12px 15px;
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.event-date {
    color: #333;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 5px;
}

.event-title {
    margin: 0 0 15px;
    font-size: 18px;
    line-height: 1.3;
    font-weight: 600;
}

.event-title a {
    color: #333;
    text-decoration: none;
}

.event-title a:hover {
    color: var(--theme-color-text_link);
}

.event-venue {
    font-size: 14px;
    color: #666;
    margin-bottom: 15px;
}

.event-footer {
    margin-top: auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
}

.event-link {
    display: inline-block;
    padding: 8px 16px;
    background-color: var(--theme-color-text_link);
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
    transition: background-color 0.3s ease;
    text-align: center;
}

.event-link:hover {
    background-color: var(--theme-color-text_link);
    opacity: 0.9;
}

.event-price {
    font-weight: bold;
    color: var(--theme-color-text_link);
    font-size: 16px;
    text-align: right;
}

/* Sold Out Flag */
.event-sold-out {
    position: absolute;
    bottom: 0;
    left: 0;
    background-color: #e74c3c;
    color: white;
    padding: 8px 20px;
    font-weight: bold;
    font-size: 16px;
    z-index: 3;
}

/* Tickets at Location Flag */
.event-tickets-at-location {
    position: absolute;
    bottom: 0;
    left: 0;
    background-color: #f39c12;
    color: white;
    padding: 8px 20px;
    font-weight: bold;
    font-size: 14px;
    z-index: 3;
}

/* Responsive styles */
@media (max-width: 992px) {
    .events-grid-container {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .events-grid-container {
        grid-template-columns: 1fr;
    }
}';
}

/**
 * Default JS for the grid
 */
function custom_events_grid_get_default_js() {
    return "/* Custom Events Grid JS */
jQuery(document).ready(function($) {
    console.log('Custom Events Grid JS loaded');
    
    // Category filtering - make sure class names and categories match
    $('.filter-button').on('click', function(e) {
        e.preventDefault();
        var selectedCategory = $(this).data('category');
        console.log('Filter clicked:', selectedCategory);
        
        // Update active class
        $('.filter-button').removeClass('active');
        $(this).addClass('active');
        
        if (selectedCategory === 'all') {
            // Show all events
            $('.event-card').show();
            console.log('Showing all events');
        } else {
            // Hide all events then show only filtered ones
            $('.event-card').hide();
            $('.event-card.category-' + selectedCategory).show();
            console.log('Filtering to:', '.event-card.category-' + selectedCategory);
            console.log('Matching elements found:', $('.event-card.category-' + selectedCategory).length);
        }
    });
});";
    
}