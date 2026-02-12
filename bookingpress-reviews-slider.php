<?php
/**
 * Plugin Name: BookingPress Reviews Slider
 * Plugin URI: https://awaxis.me/bookingpress-reviews-slider
 * Description: Display BookingPress staff reviews in a beautiful slider on your homepage
 * Author: Awaxis
 * Author URI: https://awaxis.me/
 * Author URI: https://friseur-nabha.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bookingpress-reviews-slider
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'BPR_SLIDER_VERSION', '1.0.0' );
define( 'BPR_SLIDER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BPR_SLIDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BPR_SLIDER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Plugin Class
 */
class BookingPress_Reviews_Slider {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain( 'bookingpress-reviews-slider', false, dirname( BPR_SLIDER_PLUGIN_BASENAME ) . '/languages' );

        // Register shortcodes
        add_shortcode( 'bookingpress_reviews_slider', array( $this, 'reviews_slider_shortcode' ) );
        add_shortcode( 'bookingpress_reviews_average', array( $this, 'reviews_average_shortcode' ) );
        add_shortcode( 'bookingpress_reviews_average_compact', array( $this, 'reviews_average_compact_shortcode' ) );
        add_shortcode( 'bookingpress_reviews_list', array( $this, 'reviews_list_shortcode' ) );

        // Enqueue assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX actions for reviews list
        add_action( 'wp_ajax_bpr_load_more_reviews', array( $this, 'ajax_load_more_reviews' ) );
        add_action( 'wp_ajax_nopriv_bpr_load_more_reviews', array( $this, 'ajax_load_more_reviews' ) );
        add_action( 'wp_ajax_bpr_filter_reviews', array( $this, 'ajax_filter_reviews' ) );
        add_action( 'wp_ajax_nopriv_bpr_filter_reviews', array( $this, 'ajax_filter_reviews' ) );

        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Enqueue plugin assets
     */
    public function enqueue_assets() {
        // Only load on pages that have the shortcode
        if ( ! is_singular() && ! is_front_page() ) {
            return;
        }

        global $post;
        $has_shortcode = false;

        if ( is_a( $post, 'WP_Post' ) ) {
            $has_shortcode = has_shortcode( $post->post_content, 'bookingpress_reviews_slider' ) ||
                           has_shortcode( $post->post_content, 'bookingpress_reviews_average' ) ||
                           has_shortcode( $post->post_content, 'bookingpress_reviews_average_compact' ) ||
                           has_shortcode( $post->post_content, 'bookingpress_reviews_list' );
        }

        if ( ! $has_shortcode ) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'bpr-slider-style',
            BPR_SLIDER_PLUGIN_URL . 'assets/css/reviews-slider.css',
            array(),
            BPR_SLIDER_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'bpr-slider-script',
            BPR_SLIDER_PLUGIN_URL . 'assets/js/reviews-slider.js',
            array( 'jquery' ),
            BPR_SLIDER_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script(
            'bpr-slider-script',
            'bprAjax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'bpr_reviews_nonce' ),
            )
        );
    }

    /**
     * Get reviews from database
     */
    private function get_reviews( $args = array() ) {
        global $wpdb;

        // Default arguments
        $defaults = array(
            'limit' => 10,
            'staff_id' => 0,
            'min_rating' => 0,
            'order' => 'DESC'
        );

        $args = wp_parse_args( $args, $defaults );

        // Build query
        $table_prefix = $wpdb->prefix;
        $table_name = $table_prefix . 'bookingpress_staff_review';

        $sql = "SELECT
                    r.review_id,
                    r.review_rating,
                    r.review_title,
                    r.review_comment,
                    r.bookingpress_customer_name,
                    r.updated_at,
                    s.bookingpress_staffmember_firstname,
                    s.bookingpress_staffmember_lastname
                FROM {$table_name} r
                LEFT JOIN {$table_prefix}bookingpress_staffmembers s
                    ON r.bookingpress_staffmember_id = s.bookingpress_staffmember_id
                WHERE 1=1";

        // Add filters
        if ( $args['staff_id'] > 0 ) {
            $sql .= $wpdb->prepare( " AND r.bookingpress_staffmember_id = %d", $args['staff_id'] );
        }

        if ( $args['min_rating'] > 0 ) {
            $sql .= $wpdb->prepare( " AND r.review_rating >= %d", $args['min_rating'] );
        }

        $sql .= " ORDER BY r.updated_at " . esc_sql( $args['order'] );
        $sql .= $wpdb->prepare( " LIMIT %d", $args['limit'] );

        return $wpdb->get_results( $sql );
    }

    /**
     * Reviews slider shortcode
     */
    public function reviews_slider_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'limit' => get_option( 'bpr_slider_limit', 10 ),
            'staff_id' => get_option( 'bpr_slider_staff_id', 0 ),
            'min_rating' => get_option( 'bpr_slider_min_rating', 4 ),
            'autoplay' => get_option( 'bpr_slider_autoplay', 'yes' ),
            'autoplay_speed' => get_option( 'bpr_slider_autoplay_speed', 5000 ),
            'show_date' => get_option( 'bpr_slider_show_date', 'yes' ),
            'show_title' => get_option( 'bpr_slider_show_title', 'yes' ),
            'show_arrows' => get_option( 'bpr_slider_show_arrows', 'no' ),
            'columns' => get_option( 'bpr_slider_columns', 3 ),
            'card_bg_color' => get_option( 'bpr_slider_card_bg_color', '#ffffff' ),
            'card_border_color' => get_option( 'bpr_slider_card_border_color', '#e5e5e5' ),
            'card_border_radius' => get_option( 'bpr_slider_card_border_radius', '8' ),
        ), $atts, 'bookingpress_reviews_slider' );

        // Get reviews
        $reviews = $this->get_reviews( array(
            'limit' => intval( $atts['limit'] ),
            'staff_id' => intval( $atts['staff_id'] ),
            'min_rating' => intval( $atts['min_rating'] ),
            'order' => 'DESC'
        ) );

        if ( empty( $reviews ) ) {
            return '<p>' . __( 'No reviews found.', 'bookingpress-reviews-slider' ) . '</p>';
        }

        // Start output buffering
        ob_start();

        // Data attributes for slider
        $slider_data = array(
            'autoplay' => ( $atts['autoplay'] === 'yes' ),
            'autoplay_speed' => intval( $atts['autoplay_speed'] ),
            'columns' => intval( $atts['columns'] )
        );

        // Wrapper classes
        $wrapper_classes = array( 'bpr-reviews-slider-wrapper' );
        if ( $atts['show_arrows'] !== 'yes' ) {
            $wrapper_classes[] = 'bpr-no-arrows';
        }

        // Custom styles for cards
        $card_styles = sprintf(
            'background-color: %s; border-color: %s; border-radius: %spx;',
            esc_attr( $atts['card_bg_color'] ),
            esc_attr( $atts['card_border_color'] ),
            esc_attr( $atts['card_border_radius'] )
        );

        ?>
        <div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" data-slider-config='<?php echo esc_attr( json_encode( $slider_data ) ); ?>'>
            <div class="bpr-reviews-slider">
                <?php foreach ( $reviews as $review ) : ?>
                    <div class="bpr-review-slide">
                        <div class="bpr-review-card" style="<?php echo esc_attr( $card_styles ); ?>">
                            <div class="bpr-review-header">
                                <div class="bpr-review-rating">
                                    <?php echo $this->get_stars_html( $review->review_rating ); ?>
                                </div>
                                <?php if ( $atts['show_date'] === 'yes' && ! empty( $review->updated_at ) ) : ?>
                                    <div class="bpr-review-date">
                                        <?php echo esc_html( $this->format_review_date( $review->updated_at ) ); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ( $atts['show_title'] === 'yes' && ! empty( $review->review_title ) ) : ?>
                                <h3 class="bpr-review-title"><?php echo esc_html( $review->review_title ); ?></h3>
                            <?php endif; ?>

                            <div class="bpr-review-content">
                                <?php echo wpautop( esc_html( $this->get_clean_comment( $review->review_comment ) ) ); ?>
                            </div>

                            <div class="bpr-review-footer">
                                <div class="bpr-reviewer-name">
                                    <?php echo esc_html( $review->bookingpress_customer_name ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ( $atts['show_arrows'] === 'yes' ) : ?>
                <!-- Navigation arrows -->
                <div class="bpr-slider-nav">
                    <button class="bpr-slider-prev" aria-label="<?php esc_attr_e( 'Previous', 'bookingpress-reviews-slider' ); ?>">
                        <span>&larr;</span>
                    </button>
                    <button class="bpr-slider-next" aria-label="<?php esc_attr_e( 'Next', 'bookingpress-reviews-slider' ); ?>">
                        <span>&rarr;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Pagination dots -->
            <div class="bpr-slider-dots"></div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Get stars HTML
     */
    private function get_stars_html( $rating ) {
        $rating = floatval( $rating );
        $full_stars = floor( $rating );
        $half_star = ( $rating - $full_stars ) >= 0.5;
        $empty_stars = 5 - $full_stars - ( $half_star ? 1 : 0 );

        $html = '<div class="bpr-stars">';

        // Full stars
        for ( $i = 0; $i < $full_stars; $i++ ) {
            $html .= '<span class="bpr-star bpr-star-full">&#9733;</span>';
        }

        // Half star
        if ( $half_star ) {
            $html .= '<span class="bpr-star bpr-star-half">&#9733;</span>';
        }

        // Empty stars
        for ( $i = 0; $i < $empty_stars; $i++ ) {
            $html .= '<span class="bpr-star bpr-star-empty">&#9734;</span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Format review date
     */
    private function format_review_date( $date ) {
        return human_time_diff( strtotime( $date ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'bookingpress-reviews-slider' );
    }

    /**
     * Clean review comment (remove metadata)
     */
    private function get_clean_comment( $comment ) {
        // Remove metadata like [Treatments: ...] and [ID: ...]
        $comment = preg_replace( '/\[ID:.*?\]/s', '', $comment );
        return trim( $comment );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Reviews Slider Settings', 'bookingpress-reviews-slider' ),
            __( 'Reviews Slider', 'bookingpress-reviews-slider' ),
            'manage_options',
            'bookingpress-reviews-slider',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'bpr_slider_settings', 'bpr_slider_limit' );
        register_setting( 'bpr_slider_settings', 'bpr_slider_staff_id' );
        register_setting( 'bpr_slider_settings', 'bpr_slider_min_rating' );
        register_setting( 'bpr_slider_settings', 'bpr_slider_autoplay' );
        register_setting( 'bpr_slider_settings', 'bpr_slider_autoplay_speed' );
        register_setting( 'bpr_slider_settings', 'bpr_slider_show_date' );
        register_setting( 'bpr_slider_settings', 'bpr_slider_show_title' );
        register_setting( 'bpr_slider_settings', 'bpr_slider_show_arrows' );
        register_setting( 'bpr_slider_settings', 'bpr_slider_columns' );
        register_setting( 'bpr_slider_settings', 'bpr_slider_card_bg_color' );
        register_setting( 'bpr_slider_settings', 'bpr_slider_card_border_color' );
        register_setting( 'bpr_slider_settings', 'bpr_slider_card_border_radius' );
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Get staff members for dropdown
        global $wpdb;
        $table_prefix = $wpdb->prefix;
        $staff_members = $wpdb->get_results(
            "SELECT bookingpress_staffmember_id, bookingpress_staffmember_firstname, bookingpress_staffmember_lastname
             FROM {$table_prefix}bookingpress_staffmembers
             WHERE bookingpress_staffmember_status = 1"
        );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <div class="bpr-settings-info" style="background: #fff; padding: 15px; border-left: 4px solid #2271b1; margin: 20px 0;">
                <h3>Usage</h3>
                <p>Use this shortcode to display the reviews slider:</p>
                <code>[bookingpress_reviews_slider]</code>
                <p style="margin-top: 10px;">You can also customize it with parameters:</p>
                <code>[bookingpress_reviews_slider limit="5" min_rating="5" columns="2"]</code>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields( 'bpr_slider_settings' );
                ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_limit"><?php _e( 'Number of Reviews', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="bpr_slider_limit" name="bpr_slider_limit" value="<?php echo esc_attr( get_option( 'bpr_slider_limit', 10 ) ); ?>" min="1" max="100" class="regular-text">
                            <p class="description"><?php _e( 'Maximum number of reviews to display', 'bookingpress-reviews-slider' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_staff_id"><?php _e( 'Staff Member', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <select id="bpr_slider_staff_id" name="bpr_slider_staff_id">
                                <option value="0"><?php _e( 'All Staff Members', 'bookingpress-reviews-slider' ); ?></option>
                                <?php foreach ( $staff_members as $staff ) : ?>
                                    <option value="<?php echo esc_attr( $staff->bookingpress_staffmember_id ); ?>" <?php selected( get_option( 'bpr_slider_staff_id', 0 ), $staff->bookingpress_staffmember_id ); ?>>
                                        <?php echo esc_html( $staff->bookingpress_staffmember_firstname . ' ' . $staff->bookingpress_staffmember_lastname ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Filter reviews by staff member', 'bookingpress-reviews-slider' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_min_rating"><?php _e( 'Minimum Rating', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <select id="bpr_slider_min_rating" name="bpr_slider_min_rating">
                                <option value="0" <?php selected( get_option( 'bpr_slider_min_rating', 4 ), 0 ); ?>><?php _e( 'All Ratings', 'bookingpress-reviews-slider' ); ?></option>
                                <option value="5" <?php selected( get_option( 'bpr_slider_min_rating', 4 ), 5 ); ?>>5 <?php _e( 'Stars', 'bookingpress-reviews-slider' ); ?></option>
                                <option value="4" <?php selected( get_option( 'bpr_slider_min_rating', 4 ), 4 ); ?>>4+ <?php _e( 'Stars', 'bookingpress-reviews-slider' ); ?></option>
                                <option value="3" <?php selected( get_option( 'bpr_slider_min_rating', 4 ), 3 ); ?>>3+ <?php _e( 'Stars', 'bookingpress-reviews-slider' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Show only reviews with this rating or higher', 'bookingpress-reviews-slider' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_columns"><?php _e( 'Columns', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <select id="bpr_slider_columns" name="bpr_slider_columns">
                                <option value="1" <?php selected( get_option( 'bpr_slider_columns', 3 ), 1 ); ?>>1</option>
                                <option value="2" <?php selected( get_option( 'bpr_slider_columns', 3 ), 2 ); ?>>2</option>
                                <option value="3" <?php selected( get_option( 'bpr_slider_columns', 3 ), 3 ); ?>>3</option>
                                <option value="4" <?php selected( get_option( 'bpr_slider_columns', 3 ), 4 ); ?>>4</option>
                            </select>
                            <p class="description"><?php _e( 'Number of reviews to show at once', 'bookingpress-reviews-slider' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_autoplay"><?php _e( 'Autoplay', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <select id="bpr_slider_autoplay" name="bpr_slider_autoplay">
                                <option value="yes" <?php selected( get_option( 'bpr_slider_autoplay', 'yes' ), 'yes' ); ?>><?php _e( 'Yes', 'bookingpress-reviews-slider' ); ?></option>
                                <option value="no" <?php selected( get_option( 'bpr_slider_autoplay', 'yes' ), 'no' ); ?>><?php _e( 'No', 'bookingpress-reviews-slider' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Automatically rotate reviews', 'bookingpress-reviews-slider' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_autoplay_speed"><?php _e( 'Autoplay Speed (ms)', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="bpr_slider_autoplay_speed" name="bpr_slider_autoplay_speed" value="<?php echo esc_attr( get_option( 'bpr_slider_autoplay_speed', 5000 ) ); ?>" min="1000" max="30000" step="100" class="regular-text">
                            <p class="description"><?php _e( 'Time between slides in milliseconds (1000ms = 1 second)', 'bookingpress-reviews-slider' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_show_date"><?php _e( 'Show Date', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <select id="bpr_slider_show_date" name="bpr_slider_show_date">
                                <option value="yes" <?php selected( get_option( 'bpr_slider_show_date', 'yes' ), 'yes' ); ?>><?php _e( 'Yes', 'bookingpress-reviews-slider' ); ?></option>
                                <option value="no" <?php selected( get_option( 'bpr_slider_show_date', 'yes' ), 'no' ); ?>><?php _e( 'No', 'bookingpress-reviews-slider' ); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_show_title"><?php _e( 'Show Title', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <select id="bpr_slider_show_title" name="bpr_slider_show_title">
                                <option value="yes" <?php selected( get_option( 'bpr_slider_show_title', 'yes' ), 'yes' ); ?>><?php _e( 'Yes', 'bookingpress-reviews-slider' ); ?></option>
                                <option value="no" <?php selected( get_option( 'bpr_slider_show_title', 'yes' ), 'no' ); ?>><?php _e( 'No', 'bookingpress-reviews-slider' ); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_show_arrows"><?php _e( 'Show Navigation Arrows', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <select id="bpr_slider_show_arrows" name="bpr_slider_show_arrows">
                                <option value="yes" <?php selected( get_option( 'bpr_slider_show_arrows', 'no' ), 'yes' ); ?>><?php _e( 'Yes', 'bookingpress-reviews-slider' ); ?></option>
                                <option value="no" <?php selected( get_option( 'bpr_slider_show_arrows', 'no' ), 'no' ); ?>><?php _e( 'No', 'bookingpress-reviews-slider' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Show left/right arrow buttons for navigation', 'bookingpress-reviews-slider' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php _e( 'Card Styling', 'bookingpress-reviews-slider' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_card_bg_color"><?php _e( 'Card Background Color', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="bpr_slider_card_bg_color" name="bpr_slider_card_bg_color" value="<?php echo esc_attr( get_option( 'bpr_slider_card_bg_color', '#ffffff' ) ); ?>" class="regular-text">
                            <p class="description"><?php _e( 'Hex color code (e.g., #ffffff for white)', 'bookingpress-reviews-slider' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_card_border_color"><?php _e( 'Card Border Color', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="bpr_slider_card_border_color" name="bpr_slider_card_border_color" value="<?php echo esc_attr( get_option( 'bpr_slider_card_border_color', '#e5e5e5' ) ); ?>" class="regular-text">
                            <p class="description"><?php _e( 'Hex color code (e.g., #e5e5e5 for light gray)', 'bookingpress-reviews-slider' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="bpr_slider_card_border_radius"><?php _e( 'Card Border Radius (px)', 'bookingpress-reviews-slider' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="bpr_slider_card_border_radius" name="bpr_slider_card_border_radius" value="<?php echo esc_attr( get_option( 'bpr_slider_card_border_radius', '8' ) ); ?>" min="0" max="50" class="regular-text">
                            <p class="description"><?php _e( 'Corner roundness in pixels (0 = square, 8 = slightly rounded, 20+ = very rounded)', 'bookingpress-reviews-slider' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Reviews average shortcode
     * Displays average rating with breakdown
     */
    public function reviews_average_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'staff_id' => get_option( 'bpr_slider_staff_id', 0 ),
            'show_recommend' => 'no',
            'show_rating_breakdown' => 'no',
            'style' => 'card',
            'bg_color' => '#ffffff',
            'border_color' => '#e5e5e5',
            'border_width' => '1px',
            'border_radius' => '12px',
            'box_shadow' => 'none',
            'margin' => '40px 0',
            'padding' => '30px',
        ), $atts, 'bookingpress_reviews_average' );

        // Get reviews statistics
        $stats = $this->get_reviews_statistics( intval( $atts['staff_id'] ) );

        if ( ! $stats || $stats['total'] === 0 ) {
            return '<p>' . __( 'No reviews found.', 'bookingpress-reviews-slider' ) . '</p>';
        }

        // Start output buffering
        ob_start();

        // Build custom styles
        $custom_styles = array();
        $custom_styles[] = 'background-color: ' . esc_attr( $atts['bg_color'] );
        $custom_styles[] = 'border-color: ' . esc_attr( $atts['border_color'] );
        $custom_styles[] = 'border-radius: ' . esc_attr( $atts['border_radius'] );
        $custom_styles[] = 'border-width: ' . esc_attr( $atts['border_width'] );
        $custom_styles[] = 'box-shadow: ' . esc_attr( $atts['box_shadow'] );
        $custom_styles[] = 'padding: ' . esc_attr( $atts['padding'] );
        $custom_styles[] = 'margin: ' . esc_attr( $atts['margin'] );
        $style_attr = implode( '; ', $custom_styles );

        ?>
        <div class="bpr-average-reviews <?php echo esc_attr( 'bpr-style-' . $atts['style'] ); ?>" style="<?php echo $style_attr; ?>">
            <div class="bpr-average-container">
                <div class="bpr-average-main">
                    <div class="bpr-average-number">
                        <?php echo number_format( $stats['average'], 1 ); ?>
                    </div>
                    <div class="bpr-average-details">
                        <div class="bpr-average-stars">
                            <?php echo $this->get_stars_html( $stats['average'] ); ?>
                        </div>
                        <div class="bpr-average-count">
                            <?php printf( __( 'Based on %d reviews', 'bookingpress-reviews-slider' ), $stats['total'] ); ?>
                        </div>
                    </div>
                </div>
                <?php if ( $atts['show_rating_breakdown'] === 'yes') : ?>
                    <div class="bpr-rating-breakdown">
                        <?php foreach ( array( 5, 4, 3, 2, 1 ) as $rating ) : ?>
                            <?php
                            $count = isset( $stats['breakdown'][$rating] ) ? $stats['breakdown'][$rating] : 0;
                            $percentage = $stats['total'] > 0 ? ( $count / $stats['total'] ) * 100 : 0;
                            ?>
                            <div class="bpr-rating-row">
                                <div class="bpr-rating-label">
                                    <?php echo $this->get_stars_html( $rating ); ?>
                                </div>
                                <div class="bpr-rating-bar-container">
                                    <div class="bpr-rating-bar" style="width: <?php echo esc_attr( $percentage ); ?>%;"></div>
                                </div>
                                <div class="bpr-rating-stats">
                                    <?php echo esc_html( $count ); ?> (<?php echo esc_html( round( $percentage ) ); ?>%)
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( $atts['show_recommend'] === 'yes' && $stats['recommend_percentage'] > 0 ) : ?>
                    <div class="bpr-recommend">
                        <?php printf( __( '%d%% of customers recommend us', 'bookingpress-reviews-slider' ), $stats['recommend_percentage'] ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Compact reviews average shortcode
     * Displays average rating in a single inline line
     */
    public function reviews_average_compact_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'staff_id' => get_option( 'bpr_slider_staff_id', 0 ),
            'show_count' => 'yes',
            'font_size' => '16px',
            'color' => '#f4b400',
            'margin' => '0',
            'padding' => '0',
            'gap' => '8px',
            'count_style' => '',
        ), $atts, 'bookingpress_reviews_average_compact' );

        // Get reviews statistics
        $stats = $this->get_reviews_statistics( intval( $atts['staff_id'] ) );

        if ( ! $stats || $stats['total'] === 0 ) {
            return '';
        }

        // Build custom styles
        $custom_styles = array();
        $custom_styles[] = 'font-size: ' . esc_attr( $atts['font_size'] );
        $custom_styles[] = 'color: ' . esc_attr( $atts['color'] );
        $custom_styles[] = 'margin: ' . esc_attr( $atts['margin'] );
        $custom_styles[] = 'padding: ' . esc_attr( $atts['padding'] );
        $custom_styles[] = 'gap: ' . esc_attr( $atts['gap'] );
        $style_attr = implode( '; ', $custom_styles );

        // Ensure CSS is enqueued when this shortcode is used in the Navigation Menu
        wp_enqueue_style(
            'bpr-slider-style',
            BPR_SLIDER_PLUGIN_URL . 'assets/css/reviews-slider.css',
            array(),
            BPR_SLIDER_VERSION
        );

        // Start output buffering
        ob_start();

        ?>
        <div class="bpr-average-compact">
            <span class="bpr-compact-number" style="<?php echo $style_attr; ?>"><?php echo number_format( $stats['average'], 1 ); ?></span>
            <span class="bpr-compact-stars" style="<?php echo $style_attr; ?>">
                <?php echo $this->get_stars_html( $stats['average'] ); ?>
            </span>
            <?php if ( $atts['show_count'] === 'yes' ) : ?>
                <span class="bpr-compact-count" style="<?php echo esc_attr( $atts['count_style'] ); ?>">
                    <?php printf( __( 'Based on %s reviews', 'bookingpress-reviews-slider' ), number_format( $stats['total'] ) ); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Reviews list shortcode
     * Displays filterable list of reviews
     */
    public function reviews_list_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'per_page' => 5,
            'show_title' => 'yes',
            'show_filter' => 'yes',
            'filter_position' => 'left',
            'staff_id' => get_option( 'bpr_slider_staff_id', 0 ),
            'default_ratings' => '5,4',
        ), $atts, 'bookingpress_reviews_list' );

        // Get initial reviews
        $default_ratings = array_map( 'intval', explode( ',', $atts['default_ratings'] ) );
        $reviews = $this->get_filtered_reviews( array(
            'staff_id' => intval( $atts['staff_id'] ),
            'ratings' => $default_ratings,
            'per_page' => intval( $atts['per_page'] ),
            'offset' => 0,
        ) );

        // Get rating counts for filter
        $rating_counts = $this->get_rating_counts( intval( $atts['staff_id'] ) );

        // Start output buffering
        ob_start();

        ?>
        <div class="bpr-reviews-list-container bpr-filter-<?php echo esc_attr( $atts['filter_position'] ); ?>" data-per-page="<?php echo esc_attr( $atts['per_page'] ); ?>" data-staff-id="<?php echo esc_attr( $atts['staff_id'] ); ?>" data-has-more="<?php echo count( $reviews ) >= intval( $atts['per_page'] ) ? 'true' : 'false'; ?>">
            <?php if ( $atts['show_title'] === 'yes' ) : ?>
                <div class="bpr-reviews-list-header">
                    <h2 class="bpr-reviews-list-title"><?php _e( 'Customer Reviews', 'bookingpress-reviews-slider' ); ?></h2>
                </div>
            <?php endif; ?>

            <div class="bpr-reviews-list-wrapper bpr-filter-<?php echo esc_attr( $atts['filter_position'] ); ?>">
                <?php if ( $atts['show_filter'] === 'yes' ) : ?>
                    <aside class="bpr-reviews-filter">
                        <h3 class="bpr-filter-title"><?php _e( 'FILTER BY', 'bookingpress-reviews-slider' ); ?></h3>
                        <div class="bpr-filter-options">
                            <?php foreach ( array( 5, 4, 3, 2, 1 ) as $rating ) : ?>
                                <?php
                                $count = isset( $rating_counts[$rating] ) ? $rating_counts[$rating] : 0;
                                $total = array_sum( $rating_counts );
                                $percentage = $total > 0 ? round( ( $count / $total ) * 100 ) : 0;
                                $checked = in_array( $rating, $default_ratings ) ? 'checked' : '';
                                ?>
                                <label class="bpr-filter-option">
                                    <input type="checkbox" class="bpr-filter-checkbox" name="bpr_rating_filter" value="<?php echo esc_attr( $rating ); ?>" <?php echo $checked; ?>>
                                    <span class="bpr-filter-stars">
                                        <?php echo $this->get_stars_html( $rating ); ?>
                                    </span>
                                    <span class="bpr-filter-count">
                                        <?php echo esc_html( $count ); ?> (<?php echo esc_html( $percentage ); ?>%)
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </aside>
                <?php endif; ?>

                <div class="bpr-reviews-list-content">
                    <div class="bpr-reviews-list" id="bpr-reviews-list">
                        <?php if ( ! empty( $reviews ) ) : ?>
                            <?php foreach ( $reviews as $review ) : ?>
                                <?php echo $this->render_review_card( $review ); ?>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="bpr-no-reviews"><?php _e( 'No reviews found matching your filters.', 'bookingpress-reviews-slider' ); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ( count( $reviews ) >= intval( $atts['per_page'] ) ) : ?>
                        <div class="bpr-load-more-container">
                            <button type="button" class="bpr-load-more-btn" id="bpr-load-more">
                                <span class="bpr-load-more-text"><?php _e( 'Load More', 'bookingpress-reviews-slider' ); ?></span>
                                <span class="bpr-load-more-icon">↓</span>
                            </button>
                            <div class="bpr-loading" style="display:none;">
                                <span class="bpr-spinner"></span>
                                <?php _e( 'Loading...', 'bookingpress-reviews-slider' ); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Get reviews statistics
     */
    private function get_reviews_statistics( $staff_id = 0 ) {
        global $wpdb;
        $table_prefix = $wpdb->prefix;
        $table_name = $table_prefix . 'bookingpress_staff_review';

        // Try to get from cache first
        $cache_key = 'bpr_stats_' . $staff_id;
        $stats = get_transient( $cache_key );

        if ( false !== $stats ) {
            return $stats;
        }

        // Build query
        $where = '1=1';
        if ( $staff_id > 0 ) {
            $where .= $wpdb->prepare( ' AND bookingpress_staffmember_id = %d', $staff_id );
        }

        $sql = "SELECT
                    AVG(review_rating) as average_rating,
                    COUNT(*) as total_reviews,
                    SUM(CASE WHEN review_rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN review_rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN review_rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN review_rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN review_rating = 1 THEN 1 ELSE 0 END) as one_star
                FROM {$table_name}
                WHERE {$where}";

        $result = $wpdb->get_row( $sql );

        if ( ! $result ) {
            return false;
        }

        $stats = array(
            'average' => floatval( $result->average_rating ),
            'total' => intval( $result->total_reviews ),
            'breakdown' => array(
                5 => intval( $result->five_star ),
                4 => intval( $result->four_star ),
                3 => intval( $result->three_star ),
                2 => intval( $result->two_star ),
                1 => intval( $result->one_star ),
            ),
            'recommend_percentage' => intval( $result->total_reviews ) > 0 ? round( ( ( intval( $result->five_star ) + intval( $result->four_star ) ) / intval( $result->total_reviews ) ) * 100 ) : 0,
        );

        // Cache for 1 hour
        set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

        return $stats;
    }

    /**
     * Get filtered reviews
     */
    private function get_filtered_reviews( $args ) {
        global $wpdb;
        $table_prefix = $wpdb->prefix;
        $table_name = $table_prefix . 'bookingpress_staff_review';

        $defaults = array(
            'staff_id' => 0,
            'ratings' => array( 5, 4, 3, 2, 1 ),
            'per_page' => 5,
            'offset' => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        // Build WHERE clause
        $where_clauses = array( '1=1' );

        if ( $args['staff_id'] > 0 ) {
            $where_clauses[] = $wpdb->prepare( 'bookingpress_staffmember_id = %d', $args['staff_id'] );
        }

        if ( ! empty( $args['ratings'] ) ) {
            $ratings_in = implode( ',', array_map( 'intval', $args['ratings'] ) );
            $where_clauses[] = "review_rating IN ({$ratings_in})";
        }

        $where = implode( ' AND ', $where_clauses );

        $sql = "SELECT
                    review_id,
                    review_rating,
                    review_title,
                    review_comment,
                    bookingpress_customer_name,
                    updated_at
                FROM {$table_name}
                WHERE {$where}
                ORDER BY updated_at DESC
                LIMIT %d OFFSET %d";

        return $wpdb->get_results( $wpdb->prepare( $sql, $args['per_page'], $args['offset'] ) );
    }

    /**
     * Get rating counts
     */
    private function get_rating_counts( $staff_id = 0 ) {
        global $wpdb;
        $table_prefix = $wpdb->prefix;
        $table_name = $table_prefix . 'bookingpress_staff_review';

        $where = '1=1';
        if ( $staff_id > 0 ) {
            $where .= $wpdb->prepare( ' AND bookingpress_staffmember_id = %d', $staff_id );
        }

        $sql = "SELECT review_rating, COUNT(*) as count
                FROM {$table_name}
                WHERE {$where}
                GROUP BY review_rating";

        $results = $wpdb->get_results( $sql );

        $counts = array();
        foreach ( $results as $row ) {
            $counts[ intval( $row->review_rating ) ] = intval( $row->count );
        }

        return $counts;
    }

    /**
     * Render a single review card
     */
    private function render_review_card( $review ) {
        ob_start();
        ?>
        <div class="bpr-review-card" data-review-id="<?php echo esc_attr( $review->review_id ); ?>">
            <div class="bpr-review-header">
                <div class="bpr-review-rating">
                    <?php echo $this->get_stars_html( $review->review_rating ); ?>
                    <span class="bpr-review-rating-number"><?php echo number_format( $review->review_rating, 1 ); ?></span>
                </div>
                <div class="bpr-review-meta">
                    <span class="bpr-review-author"><?php echo esc_html( $review->bookingpress_customer_name ); ?></span>
                    <span class="bpr-review-separator">•</span>
                    <span class="bpr-review-date"><?php echo $this->format_review_date( $review->updated_at ); ?></span>
                </div>
            </div>

            <?php if ( ! empty( $review->review_title ) ) : ?>
                <h4 class="bpr-review-title"><?php echo esc_html( $review->review_title ); ?></h4>
            <?php endif; ?>

            <div class="bpr-review-content">
                <?php echo wpautop( esc_html( $this->get_clean_comment( $review->review_comment ) ) ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Load more reviews
     */
    public function ajax_load_more_reviews() {
        check_ajax_referer( 'bpr_reviews_nonce', 'nonce' );

        $staff_id = isset( $_POST['staff_id'] ) ? intval( $_POST['staff_id'] ) : 0;
        $ratings = isset( $_POST['ratings'] ) ? array_map( 'intval', $_POST['ratings'] ) : array( 5, 4, 3, 2, 1 );
        $per_page = isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : 5;
        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;

        $reviews = $this->get_filtered_reviews( array(
            'staff_id' => $staff_id,
            'ratings' => $ratings,
            'per_page' => $per_page,
            'offset' => $offset,
        ) );

        if ( empty( $reviews ) ) {
            wp_send_json_success( array(
                'html' => '',
                'has_more' => false,
            ) );
        }

        $html = '';
        foreach ( $reviews as $review ) {
            $html .= $this->render_review_card( $review );
        }

        wp_send_json_success( array(
            'html' => $html,
            'has_more' => count( $reviews ) >= $per_page,
        ) );
    }

    /**
     * AJAX: Filter reviews
     */
    public function ajax_filter_reviews() {
        check_ajax_referer( 'bpr_reviews_nonce', 'nonce' );

        $staff_id = isset( $_POST['staff_id'] ) ? intval( $_POST['staff_id'] ) : 0;
        $ratings = isset( $_POST['ratings'] ) ? array_map( 'intval', $_POST['ratings'] ) : array();
        $per_page = isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : 5;

        // If no ratings selected, return empty
        if ( empty( $ratings ) ) {
            wp_send_json_success( array(
                'html' => '<p class="bpr-no-reviews">' . __( 'Please select at least one rating filter.', 'bookingpress-reviews-slider' ) . '</p>',
                'has_more' => false,
            ) );
        }

        $reviews = $this->get_filtered_reviews( array(
            'staff_id' => $staff_id,
            'ratings' => $ratings,
            'per_page' => $per_page,
            'offset' => 0,
        ) );

        if ( empty( $reviews ) ) {
            wp_send_json_success( array(
                'html' => '<p class="bpr-no-reviews">' . __( 'No reviews found matching your filters.', 'bookingpress-reviews-slider' ) . '</p>',
                'has_more' => false,
            ) );
        }

        $html = '';
        foreach ( $reviews as $review ) {
            $html .= $this->render_review_card( $review );
        }

        wp_send_json_success( array(
            'html' => $html,
            'has_more' => count( $reviews ) >= $per_page,
        ) );
    }
}

// Initialize plugin
BookingPress_Reviews_Slider::get_instance();
