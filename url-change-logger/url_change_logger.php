<?php
/**
 * Plugin Name:       URL Change Logger
 * Plugin URI:        https://github.com/remarkablecloud/URL-Change-Logger
 * Description:       Logs URL changes with timestamp, author, and provides an admin interface to manage logs, useful to purge external caches
 * Version:           1.0
 * Author:            RemarkableCloud
 * Author URI:        https://remarkablecloud.com/
 * License:           GPLv3
 * License URI:       http://www.gnu.org/licenses/gpl.html
 * Text Domain:       url-change-logger
 * Domain Path:       /lang
 */

// Hook to detect when a post or page is saved or updated
add_action('save_post', 'url_change_log_on_save', 10, 3);

function url_change_log_on_save($post_id, $post, $update) {
    // Check if the post type is 'post' or 'page'
    if ($post->post_type === 'post' || $post->post_type === 'page') {
        // Get the currently logged-in user
        $current_user = wp_get_current_user();

        // Use a transient to check if the URL has been logged during this save event
        $transient_key = 'url_change_logged_' . $post_id;
        $already_logged = get_transient($transient_key);

        // If the URL has not been logged during this save event, log it
        if (!$already_logged) {
            // Log the URL change with timestamp and the username of the last change
            url_change_log(get_permalink($post_id), current_time('mysql'), $current_user->user_login);

            // Set the transient to prevent logging the same URL again during this save event
            set_transient($transient_key, true, 60); // Set expiration time to 60 seconds
        }
    }
}


// Function to log URL changes
function url_change_log($url, $timestamp, $author) {
    $log_file = plugin_dir_path(__FILE__) . 'url_change_log.txt';

    // Append the URL change with timestamp and author to the log file
    file_put_contents($log_file, "$timestamp | $author | $url" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Hook to add a menu item in the admin dashboard
add_action('admin_menu', 'url_change_logger_menu');

function url_change_logger_menu() {
    add_menu_page(
        'URL Change Logger',
        'URL Change Logger',
        'manage_options',
        'url_change_logger',
        'url_change_logger_page',
        'dashicons-feedback' // Custom icon
    );
}

// Function to display the main admin page
function url_change_logger_page() {
    ?>
    <div class="wrap">
        <h2>URL Change Logger</h2>
        <?php
            // Handle log deletion
            if (isset($_POST['delete_logs'])) {
                url_change_logger_delete_logs();
                echo '<div class="updated"><p>Logs deleted successfully!</p></div>';
            }
        ?>

        <form method="post" action="">
            <p>Logs are stored in the file: <strong><?php echo plugin_dir_path(__FILE__) . 'url_change_log.txt'; ?></strong></p>
            <p><input type="submit" name="delete_logs" class="button button-primary" value="Delete Logs"></p>
        </form>

        <h2>View Logs</h2>

        <form method="post" action="">
            <label for="url_search">Search URL:</label>
            <input type="text" id="url_search" name="url_search" value="<?php echo esc_attr(isset($_POST['url_search']) ? $_POST['url_search'] : ''); ?>">
            <input type="submit" class="button button-secondary" value="Search">
        </form>

        <?php
            // Display logs based on search criteria
            $logs = url_change_logger_get_logs(isset($_POST['url_search']) ? $_POST['url_search'] : '');

            if ($logs) {
                echo '<pre>' . esc_html($logs) . '</pre>';
            } else {
                echo '<p>No logs found.</p>';
            }
        ?>
    </div>
    <?php
}

// Function to display and filter logs based on search criteria
function url_change_logger_get_logs($search = '') {
    $log_file = plugin_dir_path(__FILE__) . 'url_change_log.txt';

    if (file_exists($log_file)) {
        $logs = file_get_contents($log_file);

        // Filter logs based on search criteria
        if ($search) {
            $filtered_logs = '';
            $log_lines = explode(PHP_EOL, $logs);

            foreach ($log_lines as $line) {
                if (stripos($line, $search) !== false) {
                    $filtered_logs .= $line . PHP_EOL;
                }
            }

            return $filtered_logs;
        }

        return $logs;
    }

    return false;
}

// Function to delete logs
function url_change_logger_delete_logs() {
    $log_file = plugin_dir_path(__FILE__) . 'url_change_log.txt';
    // Check if the log file exists, then delete it
    if (file_exists($log_file)) {
        unlink($log_file);
    }
}
