<?php
/**
 * Plugin Name: Advanced Auction System
 * Plugin URI: https://example.com
 * Description: A comprehensive auction system with bidding, timer, and admin management
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AUCTION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AUCTION_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AUCTION_VERSION', '1.0.0');

class AdvancedAuctionSystem {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        $this->create_tables();
        $this->add_shortcodes();
        $this->add_ajax_hooks();
        $this->add_admin_menu();
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Auction items table
        $table_auctions = $wpdb->prefix . 'auction_items';
        $sql_auctions = "CREATE TABLE $table_auctions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            starting_price decimal(10,2) NOT NULL,
            current_price decimal(10,2) NOT NULL,
            end_time datetime NOT NULL,
            status enum('active','ended','paused') DEFAULT 'active',
            winner_user_id int(11),
            gallery_images text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Bids table
        $table_bids = $wpdb->prefix . 'auction_bids';
        $sql_bids = "CREATE TABLE $table_bids (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            auction_id mediumint(9) NOT NULL,
            user_id int(11) NOT NULL,
            nickname varchar(100) NOT NULL,
            bid_amount decimal(10,2) NOT NULL,
            bid_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY auction_id (auction_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // User nicknames table
        $table_nicknames = $wpdb->prefix . 'auction_user_nicknames';
        $sql_nicknames = "CREATE TABLE $table_nicknames (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL UNIQUE,
            nickname varchar(100) NOT NULL UNIQUE,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_auctions);
        dbDelta($sql_bids);
        dbDelta($sql_nicknames);
    }
    
    public function add_shortcodes() {
        add_shortcode('auction_display', array($this, 'auction_shortcode'));
    }
    
    public function auction_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts);
        
        if (!$atts['id']) {
            return '<p>Please specify an auction ID.</p>';
        }
        
        return $this->render_auction_display($atts['id']);
    }
    
    public function render_auction_display($auction_id) {
        global $wpdb;
        
        $auction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}auction_items WHERE id = %d", 
            $auction_id
        ));
        
        if (!$auction) {
            return '<p>Auction not found.</p>';
        }
        
        // Get top 10 bids
        $top_bids = $wpdb->get_results($wpdb->prepare(
            "SELECT nickname, bid_amount, bid_time 
             FROM {$wpdb->prefix}auction_bids 
             WHERE auction_id = %d 
             ORDER BY bid_amount DESC, bid_time ASC 
             LIMIT 10", 
            $auction_id
        ));
        
        ob_start();
        ?>
        <div class="container-both">
        <div class="auction-gallery">
                <?php 
                $gallery_images = array();
                if (!empty($auction->gallery_images)) {
                    $decoded_images = json_decode($auction->gallery_images, true);
                    if (is_array($decoded_images)) {
                        $gallery_images = $decoded_images;
                    }
                }
                
                // Debug output
                error_log('Frontend - Raw gallery data: ' . $auction->gallery_images);
                error_log('Frontend - Decoded gallery images: ' . print_r($gallery_images, true));
                
                if (!empty($gallery_images)): 
                ?>
                    <div class="gallery-container">
                        <div class="main-image-container">
                            <img id="main-image-<?php echo $auction_id; ?>" 
                                 src="<?php echo esc_url($gallery_images[0]); ?>" 
                                 alt="<?php echo esc_attr($auction->title); ?>"
                                 class="main-image">
                            <div class="zoom-overlay">
                                <i class="zoom-icon">🔍</i>
                            </div>
                        </div>
                        
                        <?php if (count($gallery_images) > 1): ?>
                            <div class="thumbnail-container">
                                <?php foreach ($gallery_images as $index => $image): ?>
                                    <div class="thumbnail-wrapper <?php echo $index === 0 ? 'active' : ''; ?>" 
                                         data-auction-id="<?php echo $auction_id; ?>" 
                                         data-image="<?php echo esc_url($image); ?>"
                                         data-index="<?php echo $index; ?>">
                                        <img src="<?php echo esc_url($image); ?>" 
                                             alt="<?php echo esc_attr($auction->title); ?> - Image <?php echo $index + 1; ?>"
                                             class="thumbnail-image">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Modal for zoomed image -->
                    <div id="image-modal-<?php echo $auction_id; ?>" class="image-modal">
                        <div class="modal-content">
                            <span class="modal-close">&times;</span>
                            <img id="modal-image-<?php echo $auction_id; ?>" src="" alt="">
                            <div class="modal-nav">
                                <button class="modal-prev">‹</button>
                                <button class="modal-next">›</button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-gallery">
                        <div class="placeholder-image">
                            <i class="placeholder-icon">📷</i>
                            <p>No images available</p>
                            <!-- Debug info -->
                            <small style="color: #999;">Debug: Gallery data = "<?php echo esc_html($auction->gallery_images); ?>"</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <div id="auction-container-<?php echo $auction_id; ?>" class="auction-container">
            <div class="auction-header">
                <h3><?php echo esc_html($auction->title); ?></h3>
                <div class="auction-description"><?php echo wp_kses_post($auction->description); ?></div>
            </div>
            
            <!-- Gallery Section -->
            
            
            <div class="auction-info">
                <div class="price-info">
                    <span class="starting-price">Starting Price: <?php echo number_format($auction->starting_price, 2); ?> RON</span>
                    <span class="current-price">Current Price: <strong><?php echo number_format($auction->current_price, 2); ?> RON</strong></span>
                </div>
                
                <div class="timer-container">
                    <div id="auction-timer-<?php echo $auction_id; ?>" class="auction-timer" 
                         data-end-time="<?php echo strtotime($auction->end_time); ?>">
                        <span class="timer-label">Time Remaining:</span>
                        <span class="timer-display"></span>
                    </div>
                </div>
            </div>
            
            <?php if (is_user_logged_in() && $auction->status === 'active'): ?>
                <div class="bidding-section">
                    <div class="nickname-setup">
                        <label for="user-nickname-<?php echo $auction_id; ?>">Your Display Nickname:</label>
                        <?php 
                        $user_nickname = $this->get_user_nickname(get_current_user_id());
                        $has_nickname = !empty($user_nickname);
                        ?>
                        
                        <?php if ($has_nickname): ?>
                            <div class="nickname-display">
                                <span class="current-nickname"><?php echo esc_html($user_nickname); ?></span>
                                <button type="button" class="edit-nickname-btn" data-auction-id="<?php echo $auction_id; ?>">
                                    Edit
                                </button>
                            </div>
                            <div class="nickname-edit-form" style="display: none;">
                                <input type="text" id="user-nickname-<?php echo $auction_id; ?>" 
                                       value="<?php echo esc_attr($user_nickname); ?>"
                                       placeholder="Enter your display nickname">
                                <button type="button" class="save-nickname-btn" data-auction-id="<?php echo $auction_id; ?>">
                                    Save
                                </button>
                                <button type="button" class="cancel-nickname-btn" data-auction-id="<?php echo $auction_id; ?>">
                                    Cancel
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="nickname-input-form">
                                <input type="text" id="user-nickname-<?php echo $auction_id; ?>" 
                                       placeholder="Enter your display nickname">
                                <button type="button" class="save-nickname-btn" data-auction-id="<?php echo $auction_id; ?>">
                                    Save Nickname
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bid-form">
                        <label for="bid-amount-<?php echo $auction_id; ?>">Your Bid Amount (RON):</label>
                        <input type="number" id="bid-amount-<?php echo $auction_id; ?>" 
                               min="<?php echo $auction->current_price + 1; ?>" 
                               step="0.01" placeholder="Enter bid amount" 
                               <?php echo !$has_nickname ? 'disabled' : ''; ?>>
                        
                        <!-- Rules Acceptance Section (moved after bid amount) -->
                        <div class="rules-section" <?php echo !$has_nickname ? 'style="display: none;"' : ''; ?>>
                            <div class="rules-header">
                                <h4>Auction Rules & Terms</h4>
                            </div>
                            <div class="rules-content">
                                <div class="rules-text">
                                    <p><strong>Important Auction Rules:</strong></p>
                                    <ul>
                                        <li>All bids are final and cannot be withdrawn</li>
                                        <li>You must be 18+ years old to participate</li>
                                        <li>Bidding currency is RON (Romanian Leu)</li>
                                        <li>Winner will be contacted within 24 hours after auction ends</li>
                                        <li>Payment must be completed within 48 hours of winning</li>
                                        <li>By bidding, you agree to our terms and conditions</li>
                                    </ul>
                                </div>
                                <div class="rules-acceptance">
                                    <label class="rules-checkbox-label">
                                        <input type="checkbox" id="accept-rules-<?php echo $auction_id; ?>" 
                                               class="rules-checkbox" required>
                                        <span class="checkmark"></span>
                                        I have read and accept the auction rules and terms & conditions
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="captcha-container" style="<?php echo !$has_nickname ? 'display: none;' : ''; ?>">
                            <label class="captcha-label">Security Verification:</label>
                            <div class="captcha-trigger">
                                <label class="robot-checkbox-label">
                                    <input type="checkbox" id="not-robot-<?php echo $auction_id; ?>" class="robot-checkbox">
                                    <span class="checkbox-custom"></span>
                                    I'm not a robot
                                </label>
                            </div>
                            <div id="slider-captcha-<?php echo $auction_id; ?>" class="slider-captcha" style="display: none;"></div>
                        </div>
                        
                        <button type="button" class="place-bid-btn" data-auction-id="<?php echo $auction_id; ?>" 
                                <?php echo !$has_nickname ? 'disabled' : ''; ?>>
                            Place Bid
                        </button>
                    </div>
                </div>
            <?php elseif (!is_user_logged_in()): ?>
                <div class="login-required">
                    <p>Please <a href="<?php echo wp_login_url(get_permalink()); ?>">login</a> to place bids.</p>
                </div>
            <?php endif; ?>
            
            <div class="top-bids">
                <h4>Top 10 Bids</h4>
                <div class="bids-list">
                    <?php if ($top_bids): ?>
                        <table class="bids-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Bidder</th>
                                    <th>Amount</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_bids as $index => $bid): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo esc_html($bid->nickname); ?></td>
                                        <td><?php echo number_format($bid->bid_amount, 2); ?> RON</td>
                                        <td><?php echo date('H:i:s', strtotime($bid->bid_time)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No bids yet. Be the first to bid!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            // Initialize auction functionality
            initializeAuction(<?php echo $auction_id; ?>);
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function get_user_nickname($user_id) {
        global $wpdb;
        
        $nickname = $wpdb->get_var($wpdb->prepare(
            "SELECT nickname FROM {$wpdb->prefix}auction_user_nicknames WHERE user_id = %d",
            $user_id
        ));
        
        return $nickname ? $nickname : '';
    }
    
    public function add_ajax_hooks() {
        add_action('wp_ajax_save_nickname', array($this, 'ajax_save_nickname'));
        add_action('wp_ajax_place_bid', array($this, 'ajax_place_bid'));
        add_action('wp_ajax_get_auction_updates', array($this, 'ajax_get_auction_updates'));
        add_action('wp_ajax_nopriv_get_auction_updates', array($this, 'ajax_get_auction_updates'));
    }
    
    public function ajax_save_nickname() {
        check_ajax_referer('auction_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Not logged in');
        }
        
        $nickname = sanitize_text_field($_POST['nickname']);
        $user_id = get_current_user_id();
        
        if (strlen($nickname) < 3 || strlen($nickname) > 50) {
            wp_send_json_error('Nickname must be between 3-50 characters');
        }
        
        global $wpdb;
        
        // Check if nickname is already taken
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}auction_user_nicknames WHERE nickname = %s AND user_id != %d",
            $nickname, $user_id
        ));
        
        if ($existing) {
            wp_send_json_error('Nickname already taken');
        }
        
        // Insert or update nickname
        $result = $wpdb->replace(
            $wpdb->prefix . 'auction_user_nicknames',
            array(
                'user_id' => $user_id,
                'nickname' => $nickname
            ),
            array('%d', '%s')
        );
        
        if ($result !== false) {
            wp_send_json_success('Nickname saved');
        } else {
            wp_send_json_error('Failed to save nickname');
        }
    }
    
    public function ajax_place_bid() {
        check_ajax_referer('auction_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Not logged in');
        }
        
        $auction_id = intval($_POST['auction_id']);
        $bid_amount = floatval($_POST['bid_amount']);
        $captcha_verified = isset($_POST['captcha_verified']) ? $_POST['captcha_verified'] === 'true' : false;
        $rules_accepted = isset($_POST['rules_accepted']) ? $_POST['rules_accepted'] === 'true' : false;
        
        // Verify captcha completion
        if (!$captcha_verified) {
            wp_send_json_error('Please complete the security verification');
        }
        
        // Verify rules acceptance
        if (!$rules_accepted) {
            wp_send_json_error('Please accept the auction rules and terms');
        }
        
        global $wpdb;
        
        // Get auction details (with fresh data)
        $auction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}auction_items WHERE id = %d",
            $auction_id
        ));
        
        if (!$auction || $auction->status !== 'active') {
            wp_send_json_error('Auction not available');
        }
        
        // Check if auction has ended
        if (strtotime($auction->end_time) <= time()) {
            wp_send_json_error('Auction has ended');
        }
        
        // Get the absolute latest current price to prevent race conditions
        $latest_bid = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(bid_amount) FROM {$wpdb->prefix}auction_bids WHERE auction_id = %d",
            $auction_id
        ));
        
        $current_price = $latest_bid ? $latest_bid : $auction->starting_price;
        
        // Validate bid amount against latest price
        if ($bid_amount <= $current_price) {
            wp_send_json_error(array(
                'message' => 'Someone just placed a higher bid! Current price is now ' . number_format($current_price, 2) . ' RON',
                'new_price' => $current_price,
                'outbid' => true
            ));
        }
        
        $user_id = get_current_user_id();
        $nickname = $this->get_user_nickname($user_id);
        
        if (!$nickname) {
            wp_send_json_error('Please set your nickname first');
        }
        
        // Place the bid
        $bid_result = $wpdb->insert(
            $wpdb->prefix . 'auction_bids',
            array(
                'auction_id' => $auction_id,
                'user_id' => $user_id,
                'nickname' => $nickname,
                'bid_amount' => $bid_amount
            ),
            array('%d', '%d', '%s', '%f')
        );
        
        if ($bid_result !== false) {
            // Update current price
            $wpdb->update(
                $wpdb->prefix . 'auction_items',
                array('current_price' => $bid_amount),
                array('id' => $auction_id),
                array('%f'),
                array('%d')
            );
            
            wp_send_json_success(array(
                'message' => 'Bid placed successfully',
                'new_price' => $bid_amount,
                'your_bid' => true
            ));
        } else {
            wp_send_json_error('Failed to place bid');
        }
    }
    
    public function ajax_get_auction_updates() {
        $auction_id = intval($_GET['auction_id']);
        $last_update = isset($_GET['last_update']) ? intval($_GET['last_update']) : 0;
        
        global $wpdb;
        
        $auction = $wpdb->get_row($wpdb->prepare(
            "SELECT current_price, end_time, status FROM {$wpdb->prefix}auction_items WHERE id = %d",
            $auction_id
        ));
        
        // Get top 10 bids
        $top_bids = $wpdb->get_results($wpdb->prepare(
            "SELECT nickname, bid_amount, bid_time, user_id 
             FROM {$wpdb->prefix}auction_bids 
             WHERE auction_id = %d 
             ORDER BY bid_amount DESC, bid_time ASC 
             LIMIT 10",
            $auction_id
        ));
        
        // Check for new bids since last update
        $new_bids = array();
        if ($last_update > 0) {
            $new_bids = $wpdb->get_results($wpdb->prepare(
                "SELECT nickname, bid_amount, bid_time, user_id 
                 FROM {$wpdb->prefix}auction_bids 
                 WHERE auction_id = %d AND UNIX_TIMESTAMP(bid_time) > %d
                 ORDER BY bid_time DESC",
                $auction_id, $last_update
            ));
        }
        
        wp_send_json_success(array(
            'current_price' => $auction->current_price,
            'end_time' => strtotime($auction->end_time),
            'status' => $auction->status,
            'top_bids' => $top_bids,
            'new_bids' => $new_bids,
            'timestamp' => time()
        ));
    }
    
    public function add_admin_menu() {
        add_action('admin_menu', array($this, 'create_admin_menu'));
    }
    
    public function create_admin_menu() {
        add_menu_page(
            'Auction System',
            'Auctions',
            'manage_options',
            'auction-system',
            array($this, 'admin_page'),
            'dashicons-hammer',
            30
        );
        
        add_submenu_page(
            'auction-system',
            'All Auctions',
            'All Auctions',
            'manage_options',
            'auction-system',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'auction-system',
            'Add New Auction',
            'Add New',
            'manage_options',
            'auction-add-new',
            array($this, 'admin_add_new_page')
        );
        
        add_submenu_page(
            'auction-system',
            'Bidders Management',
            'Bidders',
            'manage_options',
            'auction-bidders',
            array($this, 'admin_bidders_page')
        );
        
        add_submenu_page(
            'auction-system',
            'Export Data',
            'Export',
            'manage_options',
            'auction-export',
            array($this, 'admin_export_page')
        );
        
        add_submenu_page(
            'auction-system',
            'Debug Tools',
            'Debug',
            'manage_options',
            'auction-debug',
            array($this, 'admin_debug_page')
        );
    }
    
    public function admin_page() {
        global $wpdb;
        
        // Handle actions
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $auction_id = intval($_GET['id']);
            $wpdb->delete($wpdb->prefix . 'auction_items', array('id' => $auction_id));
            $wpdb->delete($wpdb->prefix . 'auction_bids', array('auction_id' => $auction_id));
            echo '<div class="notice notice-success"><p>Auction deleted successfully.</p></div>';
        }
        
        $auctions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}auction_items ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Auctions</h1>
            <a href="<?php echo admin_url('admin.php?page=auction-add-new'); ?>" class="page-title-action">Add New</a>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Starting Price</th>
                        <th>Current Price</th>
                        <th>End Time</th>
                        <th>Status</th>
                        <th>Shortcode</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auctions as $auction): ?>
                        <?php 
                        // Get bid count for this auction
                        $bid_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}auction_bids WHERE auction_id = %d", 
                            $auction->id
                        ));
                        ?>
                        <tr>
                            <td><?php echo $auction->id; ?></td>
                            <td><?php echo esc_html($auction->title); ?></td>
                            <td><?php echo number_format($auction->starting_price, 2); ?> RON</td>
                            <td><?php echo number_format($auction->current_price, 2); ?> RON</td>
                            <td><?php echo date('Y-m-d H:i', strtotime($auction->end_time)); ?></td>
                            <td>
                                <span class="status-<?php echo $auction->status; ?>">
                                    <?php echo ucfirst($auction->status); ?>
                                </span>
                                <?php if ($bid_count > 0): ?>
                                    <br><small>(<?php echo $bid_count; ?> bids)</small>
                                <?php endif; ?>
                            </td>
                            <td><code>[auction_display id="<?php echo $auction->id; ?>"]</code></td>
                            <td>
                                <a href="?page=auction-add-new&edit=<?php echo $auction->id; ?>">Edit</a> |
                                <a href="?page=auction-bidders&auction_id=<?php echo $auction->id; ?>">View Bids</a> |
                                <a href="?page=auction-system&action=delete&id=<?php echo $auction->id; ?>" 
                                   onclick="return confirm('Are you sure? This will delete all associated bids.')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .status-active { color: #28a745; font-weight: bold; }
        .status-ended { color: #dc3545; font-weight: bold; }
        .status-paused { color: #ffc107; font-weight: bold; }
        </style>
        <?php
    }
    
    public function admin_bidders_page() {
        global $wpdb;
        
        // Get auction filter
        $auction_filter = isset($_GET['auction_id']) ? intval($_GET['auction_id']) : 0;
        
        // Handle export action
        if (isset($_GET['action']) && $_GET['action'] === 'export' && isset($_GET['auction_id'])) {
            $this->export_bidders_csv(intval($_GET['auction_id']));
            return;
        }
        
        // Build query
        $where_clause = "";
        $query_params = array();
        
        if ($auction_filter > 0) {
            $where_clause = "WHERE b.auction_id = %d";
            $query_params[] = $auction_filter;
        }
        
        // Get bidders with user information
        $bidders_query = "
            SELECT 
                b.id as bid_id,
                b.auction_id,
                b.bid_amount,
                b.bid_time,
                b.nickname,
                u.ID as user_id,
                u.user_email,
                u.display_name,
                u.user_login,
                a.title as auction_title,
                a.status as auction_status,
                (SELECT COUNT(*) FROM {$wpdb->prefix}auction_bids WHERE user_id = b.user_id AND auction_id = b.auction_id) as total_bids_by_user,
                (SELECT MAX(bid_amount) FROM {$wpdb->prefix}auction_bids WHERE user_id = b.user_id AND auction_id = b.auction_id) as max_bid_by_user
            FROM {$wpdb->prefix}auction_bids b
            LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}auction_items a ON b.auction_id = a.id
            {$where_clause}
            ORDER BY b.bid_time DESC
        ";
        
        if (!empty($query_params)) {
            $bidders = $wpdb->get_results($wpdb->prepare($bidders_query, $query_params));
        } else {
            $bidders = $wpdb->get_results($bidders_query);
        }
        
        // Get all auctions for filter dropdown
        $auctions = $wpdb->get_results("SELECT id, title, status FROM {$wpdb->prefix}auction_items ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Bidders Management</h1>
            
            <!-- Filter Form -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="auction-bidders">
                        <select name="auction_id" id="auction-filter">
                            <option value="0">All Auctions</option>
                            <?php foreach ($auctions as $auction): ?>
                                <option value="<?php echo $auction->id; ?>" 
                                        <?php selected($auction_filter, $auction->id); ?>>
                                    <?php echo esc_html($auction->title); ?> 
                                    (<?php echo ucfirst($auction->status); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" class="button" value="Filter">
                        
                        <?php if ($auction_filter > 0): ?>
                            <a href="?page=auction-bidders&action=export&auction_id=<?php echo $auction_filter; ?>" 
                               class="button button-secondary">Export CSV</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <?php if (empty($bidders)): ?>
                <div class="notice notice-info">
                    <p>No bidders found<?php echo $auction_filter > 0 ? ' for the selected auction' : ''; ?>.</p>
                </div>
            <?php else: ?>
                
                <!-- Summary Stats -->
                <?php if ($auction_filter > 0): ?>
                    <?php
                    $stats = $wpdb->get_row($wpdb->prepare("
                        SELECT 
                            COUNT(DISTINCT user_id) as unique_bidders,
                            COUNT(*) as total_bids,
                            MAX(bid_amount) as highest_bid,
                            AVG(bid_amount) as average_bid
                        FROM {$wpdb->prefix}auction_bids 
                        WHERE auction_id = %d
                    ", $auction_filter));
                    ?>
                    <div class="auction-stats">
                        <h3>Auction Statistics</h3>
                        <p>
                            <strong>Unique Bidders:</strong> <?php echo $stats->unique_bidders; ?> | 
                            <strong>Total Bids:</strong> <?php echo $stats->total_bids; ?> | 
                            <strong>Highest Bid:</strong> <?php echo number_format($stats->highest_bid, 2); ?> RON | 
                            <strong>Average Bid:</strong> <?php echo number_format($stats->average_bid, 2); ?> RON
                        </p>
                    </div>
                <?php endif; ?>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Bid ID</th>
                            <th>Auction</th>
                            <th>Bidder Info</th>
                            <th>Email</th>
                            <th>Nickname</th>
                            <th>Bid Amount</th>
                            <th>Bid Time</th>
                            <th>User Stats</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bidders as $bidder): ?>
                            <tr>
                                <td><?php echo $bidder->bid_id; ?></td>
                                <td>
                                    <strong><?php echo esc_html($bidder->auction_title); ?></strong>
                                    <br><small>ID: <?php echo $bidder->auction_id; ?></small>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($bidder->display_name ?: $bidder->user_login); ?></strong>
                                    <br><small>User ID: <?php echo $bidder->user_id; ?></small>
                                </td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($bidder->user_email); ?>">
                                        <?php echo esc_html($bidder->user_email); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($bidder->nickname); ?></td>
                                <td>
                                    <strong><?php echo number_format($bidder->bid_amount, 2); ?> RON</strong>
                                    <?php if ($bidder->bid_amount == $bidder->max_bid_by_user): ?>
                                        <br><small style="color: #28a745;">🏆 Highest by user</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('Y-m-d H:i:s', strtotime($bidder->bid_time)); ?>
                                    <br><small><?php echo human_time_diff(strtotime($bidder->bid_time), current_time('timestamp')); ?> ago</small>
                                </td>
                                <td>
                                    <small>
                                        Total bids: <?php echo $bidder->total_bids_by_user; ?><br>
                                        Max bid: <?php echo number_format($bidder->max_bid_by_user, 2); ?> RON
                                    </small>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $bidder->user_id); ?>" 
                                       target="_blank">View User</a>
                                    <?php if (current_user_can('delete_users')): ?>
                                        <br><a href="?page=auction-bidders&action=delete_bid&bid_id=<?php echo $bidder->bid_id; ?>&auction_id=<?php echo $auction_filter; ?>" 
                                               onclick="return confirm('Are you sure you want to delete this bid?')"
                                               style="color: #dc3545;">Delete Bid</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php endif; ?>
        </div>
        
        <style>
        .auction-stats {
            background: #f1f1f1;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            border-left: 4px solid #0073aa;
        }
        .auction-stats h3 {
            margin-top: 0;
        }
        .tablenav.top {
            margin: 20px 0;
        }
        </style>
        <?php
        
        // Handle delete bid action
        if (isset($_GET['action']) && $_GET['action'] === 'delete_bid' && isset($_GET['bid_id'])) {
            $bid_id = intval($_GET['bid_id']);
            $deleted = $wpdb->delete($wpdb->prefix . 'auction_bids', array('id' => $bid_id));
            if ($deleted) {
                echo '<div class="notice notice-success"><p>Bid deleted successfully.</p></div>';
                echo '<script>setTimeout(function(){ window.location.href = "?page=auction-bidders' . ($auction_filter ? '&auction_id=' . $auction_filter : '') . '"; }, 2000);</script>';
            }
        }
    }
    
    public function admin_export_page() {
        global $wpdb;
        
        if (isset($_POST['export_auction_data']) && isset($_POST['auction_id'])) {
            $auction_id = intval($_POST['auction_id']);
            $this->export_bidders_csv($auction_id);
            return;
        }
        
        // Get all auctions
        $auctions = $wpdb->get_results("SELECT id, title, status, created_at FROM {$wpdb->prefix}auction_items ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1>Export Auction Data</h1>
            
            <div class="export-options">
                <h2>Export Bidders Data</h2>
                <p>Select an auction to export all bidders data including their email addresses and bid information.</p>
                
                <form method="post" action="">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Select Auction</th>
                            <td>
                                <select name="auction_id" required>
                                    <option value="">Choose an auction...</option>
                                    <?php foreach ($auctions as $auction): ?>
                                        <option value="<?php echo $auction->id; ?>">
                                            <?php echo esc_html($auction->title); ?> 
                                            (<?php echo ucfirst($auction->status); ?>) - 
                                            <?php echo date('Y-m-d', strtotime($auction->created_at)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Select which auction's bidder data you want to export.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Export to CSV', 'primary', 'export_auction_data'); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    private function export_bidders_csv($auction_id) {
        global $wpdb;
        
        $auction = $wpdb->get_row($wpdb->prepare(
            "SELECT title FROM {$wpdb->prefix}auction_items WHERE id = %d", 
            $auction_id
        ));
        
        if (!$auction) {
            wp_die('Auction not found');
        }
        
        $bidders = $wpdb->get_results($wpdb->prepare("
            SELECT 
                b.id as bid_id,
                b.bid_amount,
                b.bid_time,
                b.nickname,
                u.ID as user_id,
                u.user_email,
                u.display_name,
                u.user_login,
                u.user_registered
            FROM {$wpdb->prefix}auction_bids b
            LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
            WHERE b.auction_id = %d
            ORDER BY b.bid_amount DESC, b.bid_time ASC
        ", $auction_id));
        
        $filename = 'auction_' . $auction_id . '_bidders_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, array(
            'Bid ID',
            'Rank',
            'User ID', 
            'Username',
            'Display Name',
            'Email',
            'Nickname',
            'Bid Amount (RON)',
            'Bid Time',
            'User Registered',
            'Auction Title'
        ));
        
        // CSV Data
        $rank = 1;
        foreach ($bidders as $bidder) {
            fputcsv($output, array(
                $bidder->bid_id,
                $rank,
                $bidder->user_id,
                $bidder->user_login,
                $bidder->display_name,
                $bidder->user_email,
                $bidder->nickname,
                number_format($bidder->bid_amount, 2),
                date('Y-m-d H:i:s', strtotime($bidder->bid_time)),
                date('Y-m-d H:i:s', strtotime($bidder->user_registered)),
                $auction->title
            ));
            $rank++;
        }
        
        fclose($output);
        exit;
    }
    
    public function admin_debug_page() {
        global $wpdb;
        
        // Handle database fix action
        if (isset($_POST['fix_database'])) {
            $table_name = $wpdb->prefix . 'auction_items';
            
            // Check if gallery_images column exists
            $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'gallery_images'");
            
            if (!$column_exists) {
                $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN gallery_images TEXT AFTER winner_user_id");
                if ($result !== false) {
                    echo '<div class="notice notice-success"><p>✓ Added gallery_images column successfully</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>✗ Failed to add gallery_images column: ' . $wpdb->last_error . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-info"><p>gallery_images column already exists</p></div>';
            }
        }
        
        ?>
        <div class="wrap">
            <h1>Debug Tools</h1>
            
            <div class="debug-section">
                <h2>Database Structure Check</h2>
                
                <?php
                $table_name = $wpdb->prefix . 'auction_items';
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
                
                if ($table_exists) {
                    echo '<p style="color: green;">✓ Table exists: ' . $table_name . '</p>';
                    
                    // Check columns
                    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
                    
                    echo '<h3>Table Structure:</h3>';
                    echo '<table class="wp-list-table widefat fixed striped" style="max-width: 600px;">';
                    echo '<thead><tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr></thead>';
                    echo '<tbody>';
                    
                    $gallery_column_exists = false;
                    foreach ($columns as $column) {
                        if ($column->Field === 'gallery_images') {
                            $gallery_column_exists = true;
                        }
                        echo '<tr>';
                        echo '<td><strong>' . $column->Field . '</strong></td>';
                        echo '<td>' . $column->Type . '</td>';
                        echo '<td>' . $column->Null . '</td>';
                        echo '<td>' . ($column->Default ? $column->Default : 'NULL') . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    
                    if ($gallery_column_exists) {
                        echo '<p style="color: green;">✓ gallery_images column exists</p>';
                        
                        // Show sample data
                        $sample_data = $wpdb->get_results("SELECT id, title, gallery_images FROM $table_name ORDER BY id DESC LIMIT 5");
                        
                        if ($sample_data) {
                            echo '<h3>Sample Gallery Data:</h3>';
                            echo '<table class="wp-list-table widefat fixed striped">';
                            echo '<thead><tr><th>ID</th><th>Title</th><th>Gallery Data</th><th>Status</th></tr></thead>';
                            echo '<tbody>';
                            
                            foreach ($sample_data as $row) {
                                echo '<tr>';
                                echo '<td>' . $row->id . '</td>';
                                echo '<td>' . esc_html($row->title) . '</td>';
                                echo '<td style="max-width: 300px; word-break: break-all;">' . esc_html($row->gallery_images ? $row->gallery_images : 'NULL') . '</td>';
                                
                                if ($row->gallery_images) {
                                    $decoded = json_decode($row->gallery_images, true);
                                    if (is_array($decoded) && !empty($decoded)) {
                                        echo '<td style="color: green;">✓ Valid (' . count($decoded) . ' images)</td>';
                                    } else {
                                        echo '<td style="color: orange;">⚠ Invalid JSON</td>';
                                    }
                                } else {
                                    echo '<td style="color: red;">✗ Empty</td>';
                                }
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                        }
                    } else {
                        echo '<p style="color: red;">✗ gallery_images column NOT found</p>';
                        echo '<form method="post">';
                        echo '<input type="submit" name="fix_database" class="button button-primary" value="Fix Database Structure">';
                        echo '</form>';
                    }
                } else {
                    echo '<p style="color: red;">✗ Table does not exist: ' . $table_name . '</p>';
                }
                ?>
            </div>
            
            <div class="debug-section" style="margin-top: 30px;">
                <h2>WordPress Environment</h2>
                <ul>
                    <li><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></li>
                    <li><strong>PHP Version:</strong> <?php echo phpversion(); ?></li>
                    <li><strong>MySQL Version:</strong> <?php echo $wpdb->db_version(); ?></li>
                    <li><strong>Plugin Version:</strong> <?php echo AUCTION_VERSION; ?></li>
                    <li><strong>WP Debug:</strong> <?php echo defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled'; ?></li>
                </ul>
            </div>
        </div>
        
        <style>
        .debug-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #0073aa;
        }
        </style>

    }
        <?php
    }
    
    public function admin_add_new_page() {
        global $wpdb;
        
        $editing = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $auction = null;
        
        if ($editing) {
            $auction = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}auction_items WHERE id = %d",
                $editing
            ));
        }
        
        if (isset($_POST['submit'])) {
            $title = sanitize_text_field($_POST['title']);
            $description = wp_kses_post($_POST['description']);
            $starting_price = floatval($_POST['starting_price']);
            $end_time = sanitize_text_field($_POST['end_time']);
            $gallery_images_raw = isset($_POST['gallery_images']) ? $_POST['gallery_images'] : '';
            
            // Debug raw input
            error_log('Raw gallery images received: ' . $gallery_images_raw);
            error_log('POST data keys: ' . implode(', ', array_keys($_POST)));
            
            // Clean and validate gallery images JSON
            $gallery_images = '';
            if (!empty($gallery_images_raw)) {
                // Remove any potential encoding issues
                $cleaned_json = trim($gallery_images_raw);
                $cleaned_json = stripslashes($cleaned_json);
                
                error_log('Cleaned JSON: ' . $cleaned_json);
                
                // Validate JSON
                $decoded = json_decode($cleaned_json, true);
                $json_error = json_last_error();
                
                if ($json_error === JSON_ERROR_NONE && is_array($decoded)) {
                    // Further validate URLs
                    $valid_urls = array();
                    foreach ($decoded as $url) {
                        if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                            $valid_urls[] = esc_url_raw($url);
                        }
                    }
                    
                    if (!empty($valid_urls)) {
                        $gallery_images = wp_json_encode($valid_urls);
                        error_log('Valid gallery images encoded: ' . $gallery_images);
                    } else {
                        error_log('No valid URLs found in gallery data');
                    }
                } else {
                    error_log('JSON validation failed. Error: ' . json_last_error_msg());
                    error_log('JSON error code: ' . $json_error);
                    
                    // Try to fix common JSON issues
                    if (strpos($cleaned_json, '"') === false && strpos($cleaned_json, '[') === false) {
                        // Might be a single URL without proper JSON formatting
                        if (filter_var($cleaned_json, FILTER_VALIDATE_URL)) {
                            $gallery_images = wp_json_encode(array(esc_url_raw($cleaned_json)));
                            error_log('Fixed single URL to JSON: ' . $gallery_images);
                        }
                    } else {
                        echo '<div class="notice notice-error"><p>Invalid gallery data format. JSON Error: ' . json_last_error_msg() . '<br>Received data: ' . esc_html($cleaned_json) . '</p></div>';
                    }
                }
            }
            
            $data = array(
                'title' => $title,
                'description' => $description,
                'starting_price' => $starting_price,
                'current_price' => $starting_price,
                'end_time' => $end_time,
                'gallery_images' => $gallery_images
            );
            
            if ($editing) {
                $result = $wpdb->update(
                    $wpdb->prefix . 'auction_items',
                    $data,
                    array('id' => $editing),
                    array('%s', '%s', '%f', '%f', '%s', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    $gallery_count = 0;
                    if (!empty($gallery_images)) {
                        $decoded = json_decode($gallery_images, true);
                        $gallery_count = is_array($decoded) ? count($decoded) : 0;
                    }
                    
                    echo '<div class="notice notice-success"><p>Auction updated successfully. Gallery images: ' . ($gallery_count > 0 ? $gallery_count . ' images saved' : 'None') . '</p></div>';
                    
                    // Reload auction data to verify save
                    $auction = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}auction_items WHERE id = %d", 
                        $editing
                    ));
                    error_log('After save - Gallery images in DB: ' . $auction->gallery_images);
                } else {
                    echo '<div class="notice notice-error"><p>Failed to update auction. Database error: ' . $wpdb->last_error . '</p></div>';
                }
            } else {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'auction_items', 
                    $data,
                    array('%s', '%s', '%f', '%f', '%s', '%s')
                );
                if ($result !== false) {
                    $new_id = $wpdb->insert_id;
                    $gallery_count = 0;
                    if (!empty($gallery_images)) {
                        $decoded = json_decode($gallery_images, true);
                        $gallery_count = is_array($decoded) ? count($decoded) : 0;
                    }
                    
                    echo '<div class="notice notice-success"><p>Auction created successfully. Shortcode: <code>[auction_display id="' . $new_id . '"]</code><br>Gallery images: ' . ($gallery_count > 0 ? $gallery_count . ' images saved' : 'None') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Failed to create auction. Database error: ' . $wpdb->last_error . '</p></div>';
                }
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo $editing ? 'Edit' : 'Add New'; ?> Auction</h1>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Title</th>
                        <td><input type="text" name="title" class="regular-text" 
                                   value="<?php echo $auction ? esc_attr($auction->title) : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row">Description</th>
                        <td><textarea name="description" rows="5" cols="50"><?php echo $auction ? esc_textarea($auction->description) : ''; ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row">Starting Price (RON)</th>
                        <td><input type="number" name="starting_price" step="0.01" min="0" 
                                   value="<?php echo $auction ? $auction->starting_price : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row">End Time</th>
                        <td><input type="datetime-local" name="end_time" 
                                   value="<?php echo $auction ? date('Y-m-d\TH:i', strtotime($auction->end_time)) : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row">Gallery Images</th>
                        <td>
                            <div id="gallery-images-container">
                                <div class="gallery-upload-area">
                                    <button type="button" id="upload-gallery-btn" class="button">Add Images</button>
                                    <p class="description">Upload multiple images for the auction gallery. First image will be the main image.</p>
                                </div>
                                <div id="gallery-preview" class="gallery-preview">
                                    <?php 
                                    if ($editing && !empty($auction->gallery_images)) {
                                        $gallery_images = json_decode($auction->gallery_images, true);
                                        if (is_array($gallery_images)) {
                                            foreach ($gallery_images as $index => $image_url) {
                                                echo '<div class="gallery-item" data-url="' . esc_attr($image_url) . '">';
                                                echo '<img src="' . esc_url($image_url) . '" alt="Gallery Image">';
                                                echo '<button type="button" class="remove-image">×</button>';
                                                echo '<div class="image-order">' . ($index + 1) . '</div>';
                                                echo '</div>';
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                                <input type="hidden" name="gallery_images" id="gallery_images_input" 
                                       value="<?php echo $auction ? esc_attr($auction->gallery_images) : ''; ?>">
                            </div>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($editing ? 'Update Auction' : 'Create Auction'); ?>
            </form>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        
        // Enqueue slider captcha
        wp_enqueue_script(
            'longbow-slider-captcha',
            AUCTION_PLUGIN_URL . 'assets/longbow.slidercaptcha.min.js',
            array(),
            AUCTION_VERSION,
            true
        );
        
        wp_enqueue_script(
            'auction-frontend',
            AUCTION_PLUGIN_URL . 'assets/auction-frontend.js',
            array('jquery', 'longbow-slider-captcha'),
            AUCTION_VERSION,
            true
        );
        
        wp_localize_script('auction-frontend', 'auction_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('auction_nonce'),
            'user_id' => is_user_logged_in() ? get_current_user_id() : 0
        ));
        
        wp_enqueue_style(
            'auction-frontend',
            AUCTION_PLUGIN_URL . 'assets/auction-frontend.css',
            array(),
            AUCTION_VERSION
        );
        
        // Enqueue slider captcha CSS
        wp_enqueue_style(
            'longbow-slider-captcha',
            AUCTION_PLUGIN_URL . 'assets/longbow.slidercaptcha.css',
            array(),
            AUCTION_VERSION
        );
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'auction') !== false) {
            wp_enqueue_script('jquery');
            wp_enqueue_media(); // For WordPress media uploader
            wp_enqueue_script('jquery-ui-sortable'); // For drag and drop reordering
            
            // Admin-specific scripts
            wp_enqueue_script(
                'auction-admin',
                AUCTION_PLUGIN_URL . 'assets/auction-admin.js',
                array('jquery', 'jquery-ui-sortable'),
                AUCTION_VERSION,
                true
            );
            
            wp_enqueue_style(
                'auction-admin',
                AUCTION_PLUGIN_URL . 'assets/auction-admin.css',
                array(),
                AUCTION_VERSION
            );
        }
    }
    
    public function activate() {
        $this->create_tables();
        
        // Check if gallery_images column exists, if not add it
        global $wpdb;
        $table_name = $wpdb->prefix . 'auction_items';
        
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'gallery_images'");
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN gallery_images TEXT AFTER winner_user_id");
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new AdvancedAuctionSystem();
?>