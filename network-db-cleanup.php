<?php
/**
 * WordPress Multisite Network Database Optimization Tool
 * 
 * This tool manages ALL sites in your network from the Network Admin
 * Add this to a network-activated code snippet plugin or mu-plugins
 * 
 * Access via: Network Admin → Settings → Network DB Cleanup
 */

// ============================================
// NETWORK ADMIN MENU
// ============================================
add_action('network_admin_menu', function() {
    add_submenu_page(
        'settings.php',
        'Network Database Cleanup',
        'Network DB Cleanup',
        'manage_network',
        'network-db-cleanup',
        'render_network_database_cleanup_page'
    );
});

// ============================================
// MAIN NETWORK CLEANUP PAGE
// ============================================
function render_network_database_cleanup_page() {
    if (!current_user_can('manage_network')) {
        wp_die('Network Administrator access required.');
    }
    
    global $wpdb;
    
    // Handle form submissions
    $message = '';
    $message_type = 'success';
    
    if (isset($_POST['action']) && check_admin_referer('network_db_cleanup')) {
        $action = $_POST['action'];
        $site_id = isset($_POST['site_id']) ? intval($_POST['site_id']) : 0;
        
        if ($site_id === 0) {
            // Network-wide cleanup
            $message = perform_network_wide_cleanup($action);
        } else {
            // Single site cleanup
            switch_to_blog($site_id);
            $message = perform_site_cleanup($action, $site_id);
            restore_current_blog();
        }
    }
    
    // Get all sites in network
    $sites = get_sites(array('number' => 1000));
    
    ?>
    <div class="wrap">
        <h1>🌐 Network Database Cleanup Tool</h1>
        <p>Manage database optimization for all <?php echo count($sites); ?> sites in your network.</p>
        
        <?php if ($message): ?>
            <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Network Overview -->
        <div class="card">
            <h2>📊 Network Database Overview</h2>
            <?php display_network_stats(); ?>
        </div>
        
        <!-- Network-Wide Actions -->
        <div class="card">
            <h2>🚀 Network-Wide Cleanup Actions</h2>
            <p><strong>⚠️ These actions will affect ALL sites in the network!</strong></p>
            
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('network_db_cleanup'); ?>
                <input type="hidden" name="site_id" value="0">
                
                <table class="widefat">
                    <tr>
                        <td>Clean all expired transients across network</td>
                        <td>
                            <button type="submit" name="action" value="network_transients" 
                                    class="button" onclick="return confirm('Clean transients for ALL sites?')">
                                Clean Network Transients
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>Clean all spam comments across network</td>
                        <td>
                            <button type="submit" name="action" value="network_spam" 
                                    class="button" onclick="return confirm('Delete spam from ALL sites?')">
                                Clean Network Spam
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>Optimize all database tables</td>
                        <td>
                            <button type="submit" name="action" value="network_optimize" 
                                    class="button button-primary" onclick="return confirm('Optimize ALL database tables?')">
                                Optimize All Tables
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>Full network cleanup (all operations)</td>
                        <td>
                            <button type="submit" name="action" value="network_full" 
                                    class="button button-primary" onclick="return confirm('Run FULL cleanup on ALL sites? This may take several minutes.')">
                                🧹 Full Network Cleanup
                            </button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        
        <!-- Individual Site Stats -->
        <div class="card">
            <h2>📁 Individual Site Statistics</h2>
            <?php display_sites_table($sites); ?>
        </div>
        
        <!-- Problematic Sites -->
        <div class="card">
            <h2>⚠️ Sites Needing Attention</h2>
            <?php display_problematic_sites($sites); ?>
        </div>
        
        <!-- Network Settings Recommendations -->
        <div class="card">
            <h2>⚙️ Network Optimization Settings</h2>
            <?php display_network_recommendations(); ?>
        </div>
    </div>
    
    <style>
        .site-stats-table { width: 100%; margin-top: 20px; }
        .site-stats-table th { background: #2271b1; color: white; padding: 10px; }
        .site-stats-table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .site-stats-table tr:hover { background: #f0f0f0; }
        .warning { color: #d63638; font-weight: bold; }
        .success { color: #00a32a; font-weight: bold; }
        .button { margin: 2px; }
        .card { background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
    </style>
    <?php
}

// ============================================
// NETWORK STATISTICS DISPLAY
// ============================================
function display_network_stats() {
    global $wpdb;
    
    $sites = get_sites(array('number' => 1000));
    $total_db_size = 0;
    $total_posts = 0;
    $total_revisions = 0;
    $total_transients = 0;
    $total_spam = 0;
    $total_autoload = 0;
    
    // Aggregate stats from all sites
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        
        // Count posts
        $total_posts += $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'");
        $total_revisions += $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        
        // Count transients
        $total_transients += $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%transient%'");
        
        // Count spam
        $total_spam += $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
        
        // Autoload size
        $autoload = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) / 1024 / 1024 FROM {$wpdb->options} WHERE autoload = 'yes'");
        $total_autoload += $autoload ?: 0;
        
        restore_current_blog();
    }
    
    // Get total database size
    $total_db_size = $wpdb->get_var("
        SELECT SUM(data_length + index_length) / 1024 / 1024
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
    ");
    
    ?>
    <table class="widefat">
        <tr>
            <td><strong>Total Sites:</strong></td>
            <td><?php echo count($sites); ?></td>
            <td><strong>Database Size:</strong></td>
            <td><?php echo number_format($total_db_size, 2); ?> MB</td>
        </tr>
        <tr>
            <td><strong>Total Posts:</strong></td>
            <td><?php echo number_format($total_posts); ?></td>
            <td><strong>Total Revisions:</strong></td>
            <td class="<?php echo $total_revisions > $total_posts ? 'warning' : ''; ?>">
                <?php echo number_format($total_revisions); ?>
                <?php if ($total_revisions > $total_posts): ?>
                    ⚠️ High
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><strong>Total Transients:</strong></td>
            <td class="<?php echo $total_transients > 1000 ? 'warning' : ''; ?>">
                <?php echo number_format($total_transients); ?>
            </td>
            <td><strong>Total Spam:</strong></td>
            <td class="<?php echo $total_spam > 100 ? 'warning' : ''; ?>">
                <?php echo number_format($total_spam); ?>
            </td>
        </tr>
        <tr>
            <td><strong>Total Autoload Size:</strong></td>
            <td colspan="3" class="<?php echo $total_autoload > count($sites) * 2 ? 'warning' : ''; ?>">
                <?php echo number_format($total_autoload, 2); ?> MB
                (Average: <?php echo number_format($total_autoload / count($sites), 2); ?> MB per site)
            </td>
        </tr>
    </table>
    <?php
}

// ============================================
// DISPLAY INDIVIDUAL SITES TABLE
// ============================================
function display_sites_table($sites) {
    global $wpdb;
    
    ?>
    <table class="site-stats-table widefat">
        <thead>
            <tr>
                <th>Site</th>
                <th>Posts</th>
                <th>Revisions</th>
                <th>Transients</th>
                <th>Spam</th>
                <th>Autoload (MB)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sites as $site): 
                switch_to_blog($site->blog_id);
                
                $stats = array(
                    'posts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'"),
                    'revisions' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"),
                    'transients' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%transient%'"),
                    'spam' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"),
                    'autoload' => $wpdb->get_var("SELECT SUM(LENGTH(option_value)) / 1024 / 1024 FROM {$wpdb->options} WHERE autoload = 'yes'")
                );
                
                restore_current_blog();
                
                $needs_cleanup = ($stats['revisions'] > 100 || $stats['transients'] > 100 || $stats['spam'] > 50 || $stats['autoload'] > 2);
                ?>
                <tr class="<?php echo $needs_cleanup ? 'needs-cleanup' : ''; ?>">
                    <td>
                        <strong><?php echo esc_html($site->domain . $site->path); ?></strong><br>
                        <small>ID: <?php echo $site->blog_id; ?></small>
                    </td>
                    <td><?php echo number_format($stats['posts']); ?></td>
                    <td class="<?php echo $stats['revisions'] > 100 ? 'warning' : ''; ?>">
                        <?php echo number_format($stats['revisions']); ?>
                    </td>
                    <td class="<?php echo $stats['transients'] > 100 ? 'warning' : ''; ?>">
                        <?php echo number_format($stats['transients']); ?>
                    </td>
                    <td class="<?php echo $stats['spam'] > 50 ? 'warning' : ''; ?>">
                        <?php echo number_format($stats['spam']); ?>
                    </td>
                    <td class="<?php echo $stats['autoload'] > 2 ? 'warning' : ''; ?>">
                        <?php echo number_format($stats['autoload'], 2); ?>
                    </td>
                    <td>
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('network_db_cleanup'); ?>
                            <input type="hidden" name="site_id" value="<?php echo $site->blog_id; ?>">
                            <button type="submit" name="action" value="site_cleanup" class="button button-small">
                                Clean Site
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// ============================================
// DISPLAY PROBLEMATIC SITES
// ============================================
function display_problematic_sites($sites) {
    global $wpdb;
    $problematic = array();
    
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        
        $autoload_size = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) / 1024 / 1024 FROM {$wpdb->options} WHERE autoload = 'yes'");
        $transients = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%transient%'");
        $revisions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        
        $issues = array();
        if ($autoload_size > 3) $issues[] = "Autoload: " . round($autoload_size, 2) . " MB";
        if ($transients > 500) $issues[] = "Transients: " . $transients;
        if ($revisions > 500) $issues[] = "Revisions: " . $revisions;
        
        if (!empty($issues)) {
            $problematic[] = array(
                'site' => $site,
                'issues' => $issues
            );
        }
        
        restore_current_blog();
    }
    
    if (empty($problematic)) {
        echo "<p class='success'>✅ All sites are running efficiently!</p>";
    } else {
        echo "<p>The following sites need immediate attention:</p>";
        echo "<ul>";
        foreach ($problematic as $item) {
            echo "<li><strong>{$item['site']->domain}{$item['site']->path}</strong> - ";
            echo implode(", ", $item['issues']);
            echo "</li>";
        }
        echo "</ul>";
    }
}

// ============================================
// NETWORK-WIDE CLEANUP FUNCTIONS
// ============================================
function perform_network_wide_cleanup($action) {
    global $wpdb;
    $sites = get_sites(array('number' => 1000));
    $total_cleaned = 0;
    
    switch ($action) {
        case 'network_transients':
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                $cleaned = $wpdb->query("
                    DELETE FROM {$wpdb->options} 
                    WHERE option_name LIKE '_transient_timeout_%' 
                    AND option_value < UNIX_TIMESTAMP()
                ");
                $total_cleaned += $cleaned;
                restore_current_blog();
            }
            return "Cleaned {$total_cleaned} expired transients across " . count($sites) . " sites.";
            
        case 'network_spam':
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                $cleaned = $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
                $total_cleaned += $cleaned;
                restore_current_blog();
            }
            return "Deleted {$total_cleaned} spam comments across " . count($sites) . " sites.";
            
        case 'network_optimize':
            $tables = $wpdb->get_col("SHOW TABLES");
            foreach ($tables as $table) {
                $wpdb->query("OPTIMIZE TABLE {$table}");
                $total_cleaned++;
            }
            return "Optimized {$total_cleaned} database tables.";
            
        case 'network_full':
            $message = "Full network cleanup completed:\n";
            
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                
                // Clean transients
                $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
                
                // Clean spam
                $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
                
                // Clean auto-drafts
                $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_modified < DATE_SUB(NOW(), INTERVAL 7 DAY)");
                
                // Clean orphaned postmeta
                $wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL");
                
                restore_current_blog();
            }
            
            // Optimize all tables
            $tables = $wpdb->get_col("SHOW TABLES");
            foreach ($tables as $table) {
                $wpdb->query("OPTIMIZE TABLE {$table}");
            }
            
            return "Full cleanup completed for " . count($sites) . " sites and " . count($tables) . " tables.";
    }
    
    return "Unknown action.";
}

// ============================================
// SINGLE SITE CLEANUP
// ============================================
function perform_site_cleanup($action, $site_id) {
    global $wpdb;
    
    $cleaned = 0;
    
    // Clean transients
    $cleaned += $wpdb->query("
        DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_timeout_%' 
        AND option_value < UNIX_TIMESTAMP()
    ");
    
    // Clean spam
    $cleaned += $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
    
    // Clean auto-drafts
    $cleaned += $wpdb->query("
        DELETE FROM {$wpdb->posts} 
        WHERE post_status = 'auto-draft' 
        AND post_modified < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    
    // Clean revisions (keep last 5)
    $wpdb->query("
        DELETE FROM {$wpdb->posts} 
        WHERE post_type = 'revision' 
        AND post_modified < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    // Optimize site tables
    $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");
    foreach ($tables as $table) {
        $wpdb->query("OPTIMIZE TABLE {$table}");
    }
    
    return "Cleaned site #{$site_id} - removed {$cleaned} items and optimized " . count($tables) . " tables.";
}

// ============================================
// NETWORK RECOMMENDATIONS
// ============================================
function display_network_recommendations() {
    $recommendations = array();
    
    // Check WP_CACHE
    if (!defined('WP_CACHE') || !WP_CACHE) {
        $recommendations[] = "Enable WP_CACHE in wp-config.php";
    }
    
    // Check post revisions
    if (!defined('WP_POST_REVISIONS') || WP_POST_REVISIONS === true) {
        $recommendations[] = "Limit post revisions: define('WP_POST_REVISIONS', 5);";
    }
    
    // Check memory limit
    if (defined('WP_MEMORY_LIMIT')) {
        $memory = wp_convert_hr_to_bytes(WP_MEMORY_LIMIT);
        if ($memory < 268435456) { // Less than 256MB
            $recommendations[] = "Increase memory limit: define('WP_MEMORY_LIMIT', '256M');";
        }
    }
    
    // Check if object cache is present
    if (!wp_using_ext_object_cache()) {
        $recommendations[] = "Install Redis or Memcached for object caching";
    }
    
    if (empty($recommendations)) {
        echo "<p class='success'>✅ Your network configuration looks good!</p>";
    } else {
        echo "<p>Recommended configuration changes for wp-config.php:</p>";
        echo "<ol>";
        foreach ($recommendations as $rec) {
            echo "<li><code>{$rec}</code></li>";
        }
        echo "</ol>";
    }
    
    ?>
    <h3>Quick wp-config.php Optimizations</h3>
    <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">
// Add these to wp-config.php for better performance:

// Cache settings
define('WP_CACHE', true);

// Limit revisions
define('WP_POST_REVISIONS', 5);

// Increase memory
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Autosave interval
define('AUTOSAVE_INTERVAL', 120); // seconds

// Empty trash
define('EMPTY_TRASH_DAYS', 7);

// Multisite specific
define('WP_ALLOW_MULTISITE', true);
define('NOBLOGREDIRECT', '<?php echo get_site_url(1); ?>');

// Disable file editing
define('DISALLOW_FILE_EDIT', true);
    </pre>
    <?php
}

// ============================================
// NETWORK-WIDE AUTOMATED CLEANUP (CRON)
// ============================================
if (!wp_next_scheduled('network_database_optimization')) {
    wp_schedule_event(time(), 'weekly', 'network_database_optimization');
}

add_action('network_database_optimization', function() {
    if (!is_multisite()) return;
    
    global $wpdb;
    $sites = get_sites(array('number' => 1000));
    
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        
        // Basic cleanup for each site
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_timeout_%' 
            AND option_value < UNIX_TIMESTAMP()
        ");
        
        $wpdb->query("
            DELETE FROM {$wpdb->posts} 
            WHERE post_status = 'auto-draft' 
            AND post_modified < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        restore_current_blog();
    }
});

// ============================================
// ADMIN BAR QUICK STATS (OPTIONAL)
// ============================================
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!is_super_admin() || !is_multisite()) return;
    
    global $wpdb;
    
    // Quick database size check
    $db_size = $wpdb->get_var("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1)
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
    ");
    
    $wp_admin_bar->add_node(array(
        'id'    => 'network-db-size',
        'title' => '💾 DB: ' . $db_size . ' MB',
        'href'  => network_admin_url('settings.php?page=network-db-cleanup'),
        'meta'  => array(
            'title' => 'Network Database Size - Click to optimize'
        )
    ));
}, 100);
