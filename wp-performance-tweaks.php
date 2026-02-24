// Remove unnecessary WordPress bloat
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wp_shortlink_wp_head');

// Disable emojis
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('admin_print_scripts', 'print_emoji_detection_script');

// Limit post revisions
define('WP_POST_REVISIONS', 5); // Already added

// Disable heartbeat on frontend
add_action('init', function() {
    if (!is_admin()) {
        wp_deregister_script('heartbeat');
    }
});
