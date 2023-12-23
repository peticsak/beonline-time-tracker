<?php
/**
 * Plugin Name: BeOnline Feladat Tracker
 * Description: Egy plugin az elvégzett feladatok követésére.
 * Version: 1.0
 * Author: <a href="https://beonline.cloud">BeOnline Technologies</a> | <a href="admin.php?page=beonline">Feladatok</a>
 */


// Enqueue the style file
function beonline_enqueue_styles() {
    wp_enqueue_style('beonline-styles', plugin_dir_url(__FILE__) . 'style.css');
}

add_action('admin_enqueue_scripts', 'beonline_enqueue_styles');


// Add menu to the admin dashboard
function beonline_menu() {
    add_menu_page(
        'BeOnline',
        'BeOnline',
        'manage_options',
        'beonline',
        'beonline_page',
        'dashicons-admin-plugins',
        20
    );

    // Add sub-menu under the main menu
    add_submenu_page(
        'beonline',
        'Settings',
        'Settings',
        'manage_options',
        'beonline-settings',
        'beonline_settings_page'
    );
}

// Callback function for the main menu page
function beonline_page() {
    ?>
<style>
#filter-form {
    margin-top: 20px;
}

#filter-form label {
    margin-right: 10px;
}
</style>
<div class="wrap">
    <h2>BeOnline Fejlesztési feladatok</h2>

    <!-- Display a message if data is saved successfully -->
    <?php if (isset($_GET['message']) && $_GET['message'] == 1) : ?>
    <div class="updated notice is-dismissible">
        <p>Sikeres mentés!</p>
    </div>
    <?php endif; ?>

    <div class="fejlec">
        <p>Az alábbi űrlap kitöltésével add meg az elvégzett feladatot, és hogy mennyi időt fordítottál rá:</p>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <!-- Add multiple rows of input fields -->
        <?php for ($i = 1; $i <= 1; $i++) : ?>
        <div class="bo-form">
            <input type="date" name="beonline_date[]" class="datepicker" value="" />
            <label>Feladat:</label>
            <input type="text" name="beonline_text[]" value="" />
            <label>Munkaidő (x10 perc):</label>
            <input type="number" name="beonline_hours[]" value="" />
        </div>
        <?php endfor; ?>

        <?php wp_nonce_field('beonline_save_data', 'beonline_nonce'); ?>
        <input type="hidden" name="action" value="save_beonline_data" class="bo-save">
        <div class="bo-button">
            <?php submit_button('Mentés'); ?></div>
    </form>


    <!-- Display the saved data -->
    <h2 class="bo-finished">Elvégzett munkák</h2>

    <!-- Display the filter form for year -->
    <form id="filter-form" method="post" action="">
        <label for="filter-year">Év szerinti szűrés:</label>
        <select name="filter_year" id="filter-year">
            <?php
        // Get unique years from saved entries
        $years = array_unique(array_map(function ($entry) {
            return date('Y', strtotime($entry['date']));
        }, get_beonline_data()));

        foreach ($years as $year) {
            echo "<option value=\"$year\">$year</option>";
        }
        ?>
        </select>
        <input type="submit" value="Szűrés" class="bo-filter">
    </form>

    <!-- Display the saved data -->


    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Dátum</th>
                <th>Feladat</th>
                <th>Munkaidő (x10 perc)</th>
                <th>Törlés</th>
            </tr>
        </thead>
        <tbody>
            <?php
                // Retrieve and display saved data based on the filter
                $filter_year = isset($_POST['filter_year']) ? $_POST['filter_year'] : null;
                $saved_data = get_beonline_data($filter_year);
                $total_hours = 0; // Initialize the total hours variable

                foreach ($saved_data as $data) :
                    $total_hours += $data['hours']; // Update total hours
                ?>
            <tr>
                <td><?php echo esc_html($data['date']); ?></td>
                <td><?php echo esc_html($data['text']); ?></td>
                <td><?php echo esc_html($data['hours']); ?></td>
                <td>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('beonline_delete_data', 'beonline_nonce_delete'); ?>
                        <input type="hidden" name="action" value="delete_beonline_data">
                        <input type="hidden" name="entry_id" value="<?php echo esc_attr($data['id']); ?>">
                        <button type="submit" class="button">Törlés</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">Összesen</th>
                <th><?php echo esc_html($total_hours); ?></th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</div>
<?php
}

// Function to retrieve saved data from the database with optional year filter
function get_beonline_data($filter_year = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'beonline_tracker_entries';

    // Add a WHERE clause for the year if it's specified
    $where_clause = $filter_year ? "WHERE YEAR(date) = $filter_year" : '';

    $query = "SELECT * FROM $table_name $where_clause ORDER BY date DESC";
    $saved_data = $wpdb->get_results($query, ARRAY_A);

    return $saved_data;
}

// Hook to save data when the form is submitted
add_action('admin_post_save_beonline_data', 'save_beonline_data');

function save_beonline_data() {
    // Check if the user has the capability to save data
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }

    // Process and save the form data
    if (isset($_POST['beonline_date']) && isset($_POST['beonline_text']) && isset($_POST['beonline_hours'])) {
        // Check the nonce
        check_admin_referer('beonline_save_data', 'beonline_nonce');

        $dates = $_POST['beonline_date'];
        $texts = $_POST['beonline_text'];
        $hours = $_POST['beonline_hours'];

        global $wpdb;
        $table_name = $wpdb->prefix . 'beonline_tracker_entries';

        // Loop through the data and save it to the database
        for ($i = 0; $i < count($dates); $i++) {
            $data = array(
                'date'  => sanitize_text_field($dates[$i]),
                'text'  => sanitize_text_field($texts[$i]),
                'hours' => intval($hours[$i]),
            );

            $wpdb->insert($table_name, $data);
        }

        // Redirect after saving data
        wp_safe_redirect(add_query_arg('message', '1', admin_url('admin.php?page=beonline')));
        exit;
    }
}

// Hook to handle form submission
add_action('admin_init', 'handle_beonline_form_submission');

function handle_beonline_form_submission() {
    if (isset($_POST['action']) && $_POST['action'] === 'save_beonline_data') {
        // Check the nonce
        check_admin_referer('beonline_save_data', 'beonline_nonce');

        // Save the data
        do_action('admin_post_save_beonline_data');
    }
}

// Hook to delete data when the delete button is pressed
add_action('admin_post_delete_beonline_data', 'delete_beonline_data');

function delete_beonline_data() {
    // Check if the user has the capability to delete data
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }

    // Process and delete the data
    if (isset($_POST['entry_id'])) {
        // Check the nonce
        check_admin_referer('beonline_delete_data', 'beonline_nonce_delete');

        $entry_id = intval($_POST['entry_id']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'beonline_tracker_entries';

        // Delete the entry from the database
        $wpdb->delete($table_name, array('id' => $entry_id));

        // Redirect after deleting data
        wp_safe_redirect(admin_url('admin.php?page=beonline'));
        exit;
    }
}

// Callback function for the settings sub-menu page
function beonline_settings_page() {
    echo '<div class="wrap">';
    echo '<h2>My Plugin Settings Page</h2>';
    echo '<p>This is the settings page content.</p>';
    echo '</div>';
}

// Hook to add menu
add_action('admin_menu', 'beonline_menu');
?>