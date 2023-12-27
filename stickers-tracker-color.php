<?php
/*
Plugin Name: Stickers Tracker
Description: A plugin to track your sticker collection. Admins can mark stickers as missing or double.
Version: 1.0
Author: Aleksandar Smilkovski
*/

// Activation hook to create database table
function stickers_tracker_activation() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'stickers_tracker';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        sticker_number mediumint(9) NOT NULL,
        missing VARCHAR(3) NOT NULL DEFAULT '',
        dvojno VARCHAR(3) NOT NULL DEFAULT '',
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    for ($i = 1; $i <= 250; $i++) {
        $wpdb->insert(
            $table_name,
            array(
                'sticker_number' => $i,
                'missing' => 'yes',
                'dvojno' => '',  // You can set default values for other columns if needed
            )
        );
    }
}
register_activation_hook(__FILE__, 'stickers_tracker_activation');

// Deactivation hook to drop database table
function stickers_tracker_deactivation() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'stickers_tracker';

    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_deactivation_hook(__FILE__, 'stickers_tracker_deactivation');


// Enqueue styles and scripts
function stickers_tracker_enqueue_assets() {
    wp_enqueue_style('sticker-tracker-styles', plugin_dir_url(__FILE__) . 'styles.css');
}
add_action('wp_enqueue_scripts', 'stickers_tracker_enqueue_assets');

// Add a shortcode to display the stickers tracker
function stickers_tracker_shortcode() {
    global $wpdb;

    ob_start();
    ?>
    <?php 
       $missing = 0;
       $dvojno = 0;
    ?>
    <div class="sticker-tracker">
        <h2 style="color: red;">Missing</h2>
        <h2 style="color: green;">Double</h2>
        <div class="sticker-grid">
            <?php
            // Note: Use ORDER BY to make sure stickers are displayed in order
            $stickers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}stickers_tracker ORDER BY sticker_number ASC");

            foreach ($stickers as $sticker) {
                $is_missing = $sticker->missing;
                $is_dvojno = $sticker->dvojno;

                $class = '';

                if ($is_missing) {
                    $class = 'missing';
                    $missing++;
                } elseif ($is_dvojno) {
                    $class = 'double';
                    $dvojno++;
                }

                echo '<div class="sticker-cell ' . $class . '">';
                echo '<span class="sticker-number">' . $sticker->sticker_number . '</span>';
                echo '</div>';
            }
            ?>
        </div>
        <h3 class="red-text">Missing <?php echo $missing?></h3>
        <h3 class="green-text">Double <?php echo $dvojno?></h3>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('stickers_tracker', 'stickers_tracker_shortcode');

// Add a custom menu page for marking stickers
function add_sticker_edit_menu_page() {
    add_menu_page('Edit Stickers', 'Edit Stickers', 'manage_options', 'edit-stickers', 'sticker_edit_page');
}
add_action('admin_menu', 'add_sticker_edit_menu_page');

// Callback function for the sticker edit page
function sticker_edit_page() {
    global $wpdb;

    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['update-stickers'])) {
        $stickers = $wpdb->get_col("SELECT sticker_number FROM {$wpdb->prefix}stickers_tracker");

        foreach ($stickers as $sticker_number) {
            update_sticker_status($sticker_number);
        }
        echo '<p>Stickers updated successfully.</p>';
    }

    ?>
    <div class="sticker-edit-page">
        <h2>Edit Stickers</h2>
        <form method="post">
            <?php
            $stickers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}stickers_tracker");

            foreach ($stickers as $sticker) {
                echo '<input type="checkbox" name="missing_' . $sticker->sticker_number . '" ' . ($sticker->missing ? 'checked' : '') . '>'. $sticker->sticker_number .' Missing ';
                echo '<input type="checkbox" name="dvojno_' . $sticker->sticker_number . '" ' . ($sticker->dvojno ? 'checked' : '') . '>'. $sticker->sticker_number .' Double |||||';
            }
            ?>
            <input type="submit" name="update-stickers" value="Update Stickers">
        </form>
    </div>
    <?php
}

// Helper function to update sticker status
function update_sticker_status($sticker_number) {
    global $wpdb;

    $missing = isset($_POST['missing_' . $sticker_number]) ? 'yes' : '';
    $dvojno = isset($_POST['dvojno_' . $sticker_number]) ? 'yes' : '';

    // Make sure to use correct column names and table name
    $wpdb->update(
        $wpdb->prefix . 'stickers_tracker',
        array('missing' => $missing, 'dvojno' => $dvojno),
        array('sticker_number' => $sticker_number)
    );
}