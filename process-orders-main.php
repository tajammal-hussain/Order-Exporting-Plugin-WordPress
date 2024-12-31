<?php
/**
 * Plugin Name: WooCommerce Custom Order Processing
 * Description: Displays processing orders in a table with categorization, webhook functionality, and a page to manage box sizes.
 * Version: 2.5
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


// Add a menu item in WooCommerce admin
add_action('admin_menu', 'order_exporter_menu');

// Create Admin Menu
function order_exporter_menu() {
    add_menu_page(
        'Order Exporter', // Page title
        'Order Exporter', // Menu title
        'manage_woocommerce', // WooCommerce administrator capability
        'order-exporter', // Menu slug
        'order_exporter_page', // Callback to display the page
        'dashicons-cart',
        6 // Menu position
    );
    add_submenu_page(
        'Box Sizes', // Page title
        'Box Sizes', // Menu title
        null, // Hidden submenu (not directly shown in the menu)
        'manage_woocommerce', // WooCommerce administrator capability
        'wc-box-sizes', // Menu slug
        'render_box_management_page' // Function to render the page
    );

    add_submenu_page(
        'Box Sizes', // Page title
        'Box Sizes', // Menu title
        null, // Hidden submenu (not directly shown in the menu)
        'manage_woocommerce', // WooCommerce administrator capability
        'wc-order-settings', // Menu slug
        'render_settings_page' // Function to render the page
    );
}



// Helper function to detect PO Box addresses
function is_po_box_address($address) {
    return preg_match('/\b(PO BOX|P\.O\. BOX|P\.O\.BOX|BOX)\b/i', $address);
}



function my_get_template( $template_name, $args = array() ) {
    // Get the path to the template file inside the plugin's directory
    $template_path = plugin_dir_path( __FILE__ ) . 'template/' . $template_name;

    // Check if the template exists
    if ( file_exists( $template_path ) ) {
        // Extract the arguments to use them in the template
        extract( $args );

        // Include the template
        include( $template_path );
    } else {
        // If the template doesn't exist, output an error message
        echo 'Template not found: ' . esc_html( $template_name );
    }
}


// Main Plugin Page with Tabs
function order_exporter_page() {
    ?>
    <div class="wrap">
        <h1>Order Exporter</h1>

        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=order-exporter&tab=processing" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'processing') || !isset($_GET['tab']) ? 'nav-tab-active' : ''; ?>">All Processing</a>
            <a href="?page=order-exporter&tab=exported" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'exported' ? 'nav-tab-active' : ''; ?>">Exported Orders</a>
            <a href="?page=order-exporter&tab=pobox" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'pobox' ? 'nav-tab-active' : ''; ?>">PO Box Orders</a>

        </h2>
        <div style="margin-bottom: 15px; margin-top:15px; display: flex; align-items: center; gap:20px;  justify-content: space-between;">
            <div class="left-buttons">
            <button id="automate-orders" class="button button-primary">AUTOMATE</button>    
            <button id="export-shipstation" class="button button-primary">Export for ShipStation</button>
                <button id="export-sendle" class="button button-primary">Export for Sendle</button>
            </div>
            <div class="right-button">
                <a href="<?php echo admin_url('admin.php?page=wc-box-sizes'); ?>" class="button">Manage Box Sizes</a>
                <a href="<?php echo admin_url('admin.php?page=wc-order-settings'); ?>" class="button">Settings</a>
            </div>

        </div>
        <div id="csv-message"></div>
        <?php
        // Determine which tab to showac
        if (isset($_GET['tab']) && $_GET['tab'] == 'processing') {
            processing_orders_page();
        } elseif (isset($_GET['tab']) && $_GET['tab'] == 'exported') {
            exported_orders_page();
        } elseif (isset($_GET['tab']) && $_GET['tab'] == 'pobox') {
            po_boxes_page();
        } else {
            processing_orders_page();
        }
        ?>
    </div>

    <script type="text/javascript">
        document.getElementById('export-sendle').addEventListener('click', function() {
            // Collect the selected order IDs
            var selectedOrders = [];
            var selectWeight = [];
            var selectedBox = [];
            document.querySelectorAll('.order-checkbox:checked').forEach(function(checkbox) {
                  // Find the closest <tr> to the checkbox
                  var row = checkbox.closest('tr');
                
                // Find the <select> element within that row with class 'order-box'
                var selectElement = row.querySelector('select.order-box');
                var weightElement = row.querySelector('input.order-weight');
                
                // Check if the value is null or an empty string (""), meaning no selection has been made
                if (selectElement.value === '' || selectElement.value == null) {
                    return; // Exit if no valid selection
                }
               // Push an object containing the select value, weight value, and order ID

                    selectedOrders.push(
                        checkbox.closest('tr').getAttribute('data-order-id')
                    );
                    selectWeight.push(
                        weightElement.value
                    );

                    selectedBox.push(
                        JSON.parse(selectElement.value)
                    );

            });

            if (selectedOrders.length === 0) {
                //alert("Please select at least one order.");
                return;
            }
            console.log(selectedOrders);
            

            // Show a message that the process is ongoing
            document.getElementById('csv-message').innerHTML = 'Generating CSV file, please wait...';

            // Send the selected orders to the backend for CSV generation
            var data = {
                action: 'generate_csv_sendle',
                orderId: selectedOrders,
                orderBox: selectedBox,
                orderWeight: selectWeight
            };

            jQuery.post(ajaxurl, data, function(response) {
                // Handle response from the server
                if (response.success) {
                    document.getElementById('csv-message').innerHTML = 'CSV file generated successfully! <a href="' + response.data.file_url + '" target="_blank">Download Now</a>';
                } else {
                    document.getElementById('csv-message').innerHTML = 'An error occurred while generating the CSV file.';
                }
            });
        });

        document.getElementById('export-shipstation').addEventListener('click', function() {
            // Collect the selected order IDs
            var selectedOrders = [];
            var selectWeight = [];
            var selectedBox = [];
            document.querySelectorAll('.order-checkbox:checked').forEach(function(checkbox) {
                   // Find the closest <tr> to the checkbox
                   var row = checkbox.closest('tr');
                
                // Find the <select> element within that row with class 'order-box'
                var selectElement = row.querySelector('select.order-box');
                var weightElement = row.querySelector('input.order-weight');
                
                // Check if the value is null or an empty string (""), meaning no selection has been made
                if (selectElement.value === '' || selectElement.value == null) {
                    return; // Exit if no valid selection
                }
               // Push an object containing the select value, weight value, and order ID

                    selectedOrders.push(
                        checkbox.closest('tr').getAttribute('data-order-id')
                    );
                    selectWeight.push(
                        weightElement.value
                    );

                    selectedBox.push(
                        JSON.parse(selectElement.value)
                    );
            });

            if (selectedOrders.length === 0) {
               // alert("Please select at least one order.");
                return;
            }

            // Show a message that the process is ongoing
            document.getElementById('csv-message').innerHTML = 'Generating CSV file, please wait...';

            // Send the selected orders to the backend for CSV generation
            var data = {
                action: 'generate_csv_shipstation',
                orderId: selectedOrders,
                orderBox: selectedBox,
                orderWeight: selectWeight               
            };

            jQuery.post(ajaxurl, data, function(response) {
                // Handle response from the server
                if (response.success) {
                    document.getElementById('csv-message').innerHTML = 'CSV file generated successfully! <a href="' + response.data.file_url + '" target="_blank">Download Now</a>';
                } else {
                    document.getElementById('csv-message').innerHTML = 'An error occurred while generating the CSV file.';
                }
            });
        });
// On click Automate button
        document.getElementById('automate-orders').addEventListener('click', function() {
            // Collect the selected order IDs
            var selectedOrders = [];
            var selectWeight = [];
            var selectedBox = [];
            document.querySelectorAll('.order-checkbox:checked').forEach(function(checkbox) {
                   // Find the closest <tr> to the checkbox
                   var row = checkbox.closest('tr');
                
                // Find the <select> element within that row with class 'order-box'
                var selectElement = row.querySelector('select.order-box');
                var weightElement = row.querySelector('input.order-weight');
                
                // Check if the value is null or an empty string (""), meaning no selection has been made
                if (selectElement.value === '' || selectElement.value == null) {
                    return; // Exit if no valid selection
                }
               // Push an object containing the select value, weight value, and order ID

                    selectedOrders.push(
                        checkbox.closest('tr').getAttribute('data-order-id')
                    );
                    selectWeight.push(
                        weightElement.value
                    );

                    selectedBox.push(
                        JSON.parse(selectElement.value)
                    );
            });

            if (selectedOrders.length === 0) {
               // alert("Please select at least one order.");
                return;
            }


            // Show a message that the process is ongoing
            document.getElementById('csv-message').innerHTML = 'Generating CSV file, please wait...';

            // Send the selected orders to the backend for CSV generation
            var data = {
                action: 'generate_csv_automate',
                orderId: selectedOrders,
                orderBox: selectedBox,
                orderWeight: selectWeight                };

            jQuery.post(ajaxurl, data, function(response) {
                // Handle response from the server
                if (response.success) {
                    document.getElementById('csv-message').innerHTML = 'CSV file generated successfully! <a href="' + response.data.file_url + '" target="_blank">Download Now</a>';
                } else {
                    document.getElementById('csv-message').innerHTML = 'An error occurred while generating the CSV file.';
                }
            });
        });
    </script>

    <?php
}


function processing_orders_page() {

        global $wpdb;
        
        $query = "
            SELECT p.ID  
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key = '_order_exported'
            WHERE p.post_type = 'shop_order'
            AND p.post_status = 'wc-processing'
            AND (pm.meta_value IS NULL OR pm.meta_value != '1')
        ";

        $queryParams = $wpdb->get_results($query);

        // Ensure we have results and handle invalid IDs
        $results = array_filter($queryParams, function($orders) {
            $order = wc_get_order($orders->ID); // Retrieve order
            if (!$order) {
                // Log or handle cases where the order object is invalid
                error_log("Invalid order ID: " . $orders->ID);
                return false;
            }

            // Prepare the shipping address and check for PO Box
            $full_address = "{$order->get_shipping_address_1()}, {$order->get_shipping_address_2()}, {$order->get_shipping_city()}, {$order->get_shipping_state()}";
            return true; // Include all orders, including PO Box orders
        });

        $total_processing_orders = count($results);

        my_get_template('admin-template.php', [
            'title' => 'All Processing Orders',
            'total_processing_orders' => $total_processing_orders,
            'results' => $results,
        ]);
    ?>

    <?php
}


add_action('wp_ajax_save_box_size', 'save_box_size');
add_action('wp_ajax_nopriv_save_box_size', 'save_box_size');

function save_box_size() {
    if (isset($_POST['order_id']) && isset($_POST['box_size'])) {
        $order_id = intval($_POST['order_id']);
        $box_size = sanitize_text_field($_POST['box_size']);
        
        update_post_meta($order_id, 'box_size', $box_size);
        
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}

add_action('wp_ajax_save_box_weight', 'save_box_weight');
add_action('wp_ajax_nopriv_save_box_weight', 'save_box_weight');

function save_box_weight() {
    if (isset($_POST['order_id']) && isset($_POST['weight'])) {
        $order_id = intval($_POST['order_id']);
        $box_weight = sanitize_text_field($_POST['weight']);
        
        update_post_meta($order_id, 'box_weight', $box_weight);
        
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}

function exported_orders_page() {
   global $wpdb;

    $query = "
        SELECT p.ID 
        FROM {$wpdb->posts} AS p
        LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key = '_order_exported'
        WHERE p.post_type = 'shop_order'
        AND p.post_status = 'wc-processing'
        AND (pm.meta_value IS NOT NULL OR pm.meta_value = '1')
    ";

    $queryParams = $wpdb->get_results($query);
    $results = array_filter($queryParams, function($orders) {
        $order = wc_get_order($orders);
        $full_address = "{$order->get_shipping_address_1()}, {$order->get_shipping_address_2()}, {$order->get_shipping_city()}, {$order->get_shipping_state()}";
        return !is_po_box_address($full_address);
    });
    
    $total_processing_orders = count($results);

    my_get_template('admin-template.php', [
        'title' => 'Exported Orders',
        'total_processing_orders' => $total_processing_orders,
        'results' => $results,
    ]);
    
}
function po_boxes_page(){
    global $wpdb;

    $query = "
        SELECT p.ID  
        FROM {$wpdb->posts} AS p
        LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key = '_order_exported'
        WHERE p.post_type = 'shop_order'
        AND p.post_status = 'wc-processing'
        AND (pm.meta_value IS NULL OR pm.meta_value != '1')
    ";

    $queryParams = $wpdb->get_results($query);
    $results = array_filter($queryParams, function($orders) {
        $order = wc_get_order($orders->ID);
        $full_address = "{$order->get_shipping_address_1()}, {$order->get_shipping_address_2()}, {$order->get_shipping_city()}, {$order->get_shipping_state()}";
        return is_po_box_address($full_address);
    });

    $total_processing_orders = count($results);
    $boxes = get_option('wc_package_boxes', []);

    my_get_template('admin-template.php', [
        'title' => 'PO Box Orders',
        'total_processing_orders' => $total_processing_orders,
        'results' => $results,
    ]);
}


/**
 * Manage Settings Page
 */

 function render_settings_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('Sorry, you are not allowed to access this page.'));
    }

    ?>
    <div class="wrap">
        <h1 style="font-size: 1.3em;">Order Exporter Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wc_order_settings');
            do_settings_sections('wc-order-settings');
            submit_button();
            ?>
        </form>
        <a href="<?php echo admin_url('admin.php?page=order-exporter'); ?>" class="button">Back</a>
    </div>
    <?php
 }
/**
 * Manage Boxes
 */
// Render the box management page
function render_box_management_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('Sorry, you are not allowed to access this page.'));
    }

    ?>
    <div class="wrap">
        <h1 style="font-size: 1.3em;">Manage Box Sizes</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wc_box_settings');
            do_settings_sections('wc-box-sizes');
            submit_button();
            ?>
        </form>
        <a href="<?php echo admin_url('admin.php?page=order-exporter'); ?>" class="button">Back to Orders</a>
    </div>
    <?php
}
// Register settings for the box sizes
add_action('admin_init', function () {
    register_setting('wc_box_settings', 'wc_package_boxes');
    register_setting('wc_order_settings', 'wc_order_settings');


    add_settings_section(
        'wc_order_settings',
        'Settings',
        null,
        'wc-order-settings'
    );
    
    add_settings_section(
        'wc_box_section',
        'Box Sizes',
        null,
        'wc-box-sizes'
    );

    add_settings_field(
        'order_settings',
        'Webhook URL',
        'render_order_settings_field',
        'wc-order-settings',
        'wc_order_settings'
    );

    add_settings_field(
        'package_boxes',
        'Boxes',
        'render_box_settings_field',
        'wc-box-sizes',
        'wc_box_section'
    );
});

function render_order_settings_field (){
    $settings = get_option('wc_order_settings', '');
    if (empty($settings)) {
        $settings = '';
    }
    ?>
    <div id="box-container">
        <input type="text" name="wc_order_settings" value="<?php echo esc_attr($settings); ?>" placeholder="Webhook URL">
    </div>
    
    <?php
}

function render_box_settings_field() {
    $boxes = get_option('wc_package_boxes', []);
    if (!is_array($boxes)) {
        $boxes = [];
    }
    ?>
    <div id="box-container">
        <?php foreach ($boxes as $index => $box): ?>
            <div>
                <input type="text" name="wc_package_boxes[<?php echo $index; ?>][name]" value="<?php echo esc_attr($box['name']); ?>" placeholder="Box Name">
                <input type="number" name="wc_package_boxes[<?php echo $index; ?>][length]" value="<?php echo esc_attr($box['length']); ?>" placeholder="Length (cm)">
                <input type="number" name="wc_package_boxes[<?php echo $index; ?>][width]" value="<?php echo esc_attr($box['width']); ?>" placeholder="Width (cm)">
                <input type="number" name="wc_package_boxes[<?php echo $index; ?>][height]" value="<?php echo esc_attr($box['height']); ?>" placeholder="Height (cm)">
                <button type="button" class="delete-box">Delete Box</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" id="add-box">Add Box</button>
    <script>
        document.getElementById('add-box').addEventListener('click', function() {
            const container = document.getElementById('box-container');
            const index = container.children.length;
            const div = document.createElement('div');
            div.innerHTML = `
                <input type="text" name="wc_package_boxes[${index}][name]" placeholder="Box Name">
                <input type="number" name="wc_package_boxes[${index}][length]" placeholder="Length (cm)">
                <input type="number" name="wc_package_boxes[${index}][width]" placeholder="Width (cm)">
                <input type="number" name="wc_package_boxes[${index}][height]" placeholder="Height (cm)">
                <button type="button" class="delete-box">Delete Box</button>
            `;
            container.appendChild(div);
            attachDeleteEvent(div.querySelector('.delete-box'));
        });

        function attachDeleteEvent(button) {
            button.addEventListener('click', function() {
                this.parentElement.remove();
            });
        }
        document.querySelectorAll('.delete-box').forEach(function(button) {
            attachDeleteEvent(button);
        });
    </script>
    <?php
}




/**
 * Helper function to extract order details.
 *
 * @param int $order_id Order ID.
 * @return array Order details.
 */
function get_order_details($order_id) {
    $order = wc_get_order($order_id);
    
    $first_name = $order->get_shipping_first_name();
    $last_name = $order->get_shipping_last_name();
    $address_1 = $order->get_shipping_address_1();
    $address_2 = $order->get_shipping_address_2();
    $city = $order->get_shipping_city();
    $shipping_state_code = $order->get_shipping_state();
    $shipping_country_code = $order->get_shipping_country();
    $postcode = $order->get_shipping_postcode();
    $full_name = trim("$first_name $last_name");
    $phone = $order->get_shipping_phone();

    $box_params = get_post_meta($order->get_id(), 'box_size', true);
    $box_params = json_decode($box_params, true);
    $centimetre_length = empty($box_params) ? '' : $box_params['length'];
    $centimetre_width = empty($box_params) ? '' : $box_params['width'];
    $centimetre_height = empty($box_params) ? '' : $box_params['height'];

    $box_weight = get_post_meta($order->get_id(), 'box_weight', true);
    $weight = empty($box_weight) ? '750' : $box_weight; // Default weight
    
    return [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'address_1' => $address_1,
        'address_2' => $address_2,
        'city' => $city,
        'shipping_state_code' => $shipping_state_code,
        'shipping_country_code' => $shipping_country_code,
        'postcode' => $postcode,
        'full_name' => $full_name,
        'phone' => $phone,
        'centimetre_length' => $centimetre_length,
        'centimetre_width' => $centimetre_width,
        'centimetre_height' => $centimetre_height,
        'weight' => $weight,
        'order_number' => $order->get_order_number(),
        'order_id' => $order->get_id()
    ];
}

/**
 * Helper function to generate CSV file.
 *
 * @param array $csv_data Array of data to be written to CSV.
 * @param string $filename Name for the CSV file.
 * @return string URL to the generated CSV file.
 */
function create_csv_file($csv_data, $filename = 'orders.csv') {
    // Create a CSV file
    $upload_dir = wp_upload_dir();
    $csv_file = $upload_dir['path'] . '/' . $filename;
    $file = fopen($csv_file, 'w');

    // Add CSV headers
    fputcsv($file, array_keys($csv_data[0]));

    // Add data rows
    foreach ($csv_data as $row) {
        fputcsv($file, $row);
    }

    fclose($file);

    return $upload_dir['url'] . '/' . basename($csv_file);
}

/**
 * Helper function to mark orders as exported.
 *
 * @param array $order_ids Array of order IDs.
 */
function mark_orders_as_exported($order_ids) {
    foreach ($order_ids as $order_id) {
        // Check if the order has not already been marked as exported
        if (!get_post_meta($order_id, '_order_exported', true)) {
            // Add the '_order_exported' meta key to the order
            update_post_meta($order_id, '_order_exported', true);
        }
    }
}

/***
 * CRON JOB
 */

// Sandle of scheduling a cron job
function schedule_csv_generation_cron() {
    if (!wp_next_scheduled('generate_csv_sendle_cron_job')) {
        wp_schedule_event(time(), 'hourly', 'generate_csv_sendle_cron_job');
    }
    if (!wp_next_scheduled('generate_csv_shipstation_cron_job')) {
        wp_schedule_event(time(), 'hourly', 'generate_csv_shipstation_cron_job');
    }
    if (!wp_next_scheduled('generate_csv_automate_cron_job')) {
        wp_schedule_event(time(), 'hourly', 'generate_csv_automate_cron_job');
    }
}
add_action('wp', 'schedule_csv_generation_cron');

// Hook for generating CSV via cron job
add_action('generate_csv_sendle_cron_job', 'generate_csv_sendle_file');

// Clear scheduled event on plugin deactivation
function deactivate_cron_job() {
    wp_clear_scheduled_hook('generate_csv_sendle_cron_job');
    wp_clear_scheduled_hook('generate_csv_shipstation_cron_job');
}
register_deactivation_hook(__FILE__, 'deactivate_cron_job');

// Hook for AJAX action
add_action('wp_ajax_generate_csv_sendle', 'generate_csv_sendle_file');

function generate_csv_sendle_file() {
    
    // Get the order IDs from the request
    $orderId = isset($_POST['orderId']) ? $_POST['orderId'] : [];
    $selectBox = isset($_POST['orderBox']) ? $_POST['orderBox'] : [];
    $selectWeight = isset($_POST['orderWeight']) ? $_POST['orderWeight'] : [];


    if (empty($orderId) || empty($selectBox) || empty($selectWeight)) {
        wp_send_json_error(['message' => 'No orders selected.']);
    }

    // Prepare the CSV data
    $csv_data = [];
    $i = 0;
    foreach ($orderId as $order_id) {
        $order_details = get_order_details($order_id);
        $box_params = $selectBox[$i];
        $csv_data[] = [
            'receiver_name' => $order_details['full_name'],
            'receiver_address_line1' => $order_details['address_1'],
            'receiver_address_line2' => $order_details['address_2'],
            'receiver_suburb' => $order_details['city'],
            'receiver_state_name' => $order_details['shipping_state_code'],
            'receiver_postcode' => $order_details['postcode'],
            'receiver_country' => $order_details['shipping_country_code'],
            'receiver_contact_number' => $order_details['phone'],
            'delivery_instructions' => '',
            'customer_reference' => $order_details['order_number'],
            'kilogram_weight' => $selectWeight[$i],
            'centimetre_length' => $box_params['length'],
            'centimetre_width' => $box_params['width'],
            'centimetre_height' => $box_params['height'],
            'pickup_date' => '',
        ];
        $i++;
    }
    // Create a CSV file
    $file_url = create_csv_file($csv_data, 'sendle_orders_' . time() . '.csv');

    // Mark orders as exported
    mark_orders_as_exported($orderId);
    // Return the file URL
    wp_send_json_success(['file_url' => $file_url]);
}



/**
 * Shipstation
 */

// Hook for generating CSV via cron job
add_action('generate_csv_shipstation_cron_job', 'generate_csv_shipstation_file');
// Hook Shipstation for AJAX action
add_action('wp_ajax_generate_csv_shipstation', 'generate_csv_shipstation_file');

function generate_csv_shipstation_file() {

    // Get the order IDs from the request
    $orderId = isset($_POST['orderId']) ? $_POST['orderId'] : [];
    $selectBox = isset($_POST['orderBox']) ? $_POST['orderBox'] : [];
    $selectWeight = isset($_POST['orderWeight']) ? $_POST['orderWeight'] : [];


    if (empty($orderId) || empty($selectBox) || empty($selectWeight)) {
        wp_send_json_error(['message' => 'No orders selected.']);
    }

    // Prepare the CSV data
    $csv_data = [];
    $i = 0;
    foreach ($orderId as $order_id) {
        $order_details = get_order_details($order_id);
        $box_params = $selectBox[$i];
        $csv_data[] = [
            'Order #' => $order_details['order_number'],
            'Height(cm)' => $box_params['height'],
            'Length(cm)' => $box_params['length'],
            'Width(cm)' => $box_params['width'],
            'Weight(gr)' => $selectWeight[$i],
            'Custom Field 1' => '',
            'Custom Field 2' => '',
            'Custom Field 3' => $order_details['order_id'],
            'Recipient First Name' => $order_details['first_name'],
            'Recipient Last Name' => $order_details['last_name'],
            'Recipient Phone' => $order_details['phone'],
            'Address Line 1' => $order_details['address_1'],
            'Address Line 2' => $order_details['address_2'],
            'City' => $order_details['city'],
            'State' => $order_details['shipping_state_code'],
            'Postal Code' => $order_details['postcode'],
            'Country Code' => $order_details['shipping_country_code'],
        ];
        $i++;
    }


    // Create a CSV file
    $file_url = create_csv_file($csv_data, 'shipstation_orders_' . time() . '.csv');

    // Mark orders as exported
    mark_orders_as_exported($orderId);

    // Return the file URL
    wp_send_json_success(['file_url' => $file_url]);
}

// Hook for generating CSV via cron job
add_action('generate_csv_automate_cron_job', 'generate_csv_automate_file');

// Hook Automate for AJAX action
add_action('wp_ajax_generate_csv_automate', 'generate_csv_automate_file');

function generate_csv_automate_file() {
  
    // Get the order IDs from the request
    $orderId = isset($_POST['orderId']) ? $_POST['orderId'] : [];
    $selectBox = isset($_POST['orderBox']) ? $_POST['orderBox'] : [];
    $selectWeight = isset($_POST['orderWeight']) ? $_POST['orderWeight'] : [];


    if (empty($orderId) || empty($selectBox) || empty($selectWeight)) {
        wp_send_json_error(['message' => 'No orders selected.']);
    }

    // Prepare the CSV data
    $csv_data = [];
    $i = 0;
    foreach ($orderId as $order_id) {
        $order_details = get_order_details($order_id);
        $box_params = $selectBox[$i];
        $order_details['postcode'] = str_replace(' ', '', $order_details['postcode']);

        // Get the custom field PG_Shipping_Speed
        $shipping_speed = get_post_meta($order_id, 'PG_Shipping_Speed', true);

        // Get shipping address details
        $shipping_first_name = get_post_meta($order_id, '_shipping_first_name', true);
        $shipping_last_name = get_post_meta($order_id, '_shipping_last_name', true);
        $shipping_address_1 = get_post_meta($order_id, '_shipping_address_1', true);
        $shipping_address_2 = get_post_meta($order_id, '_shipping_address_2', true);
        $shipping_city = get_post_meta($order_id, '_shipping_city', true);
        $shipping_state = get_post_meta($order_id, '_shipping_state', true);

        // Get customer phone number (fallback to default if not available)
        $customer_phone = get_post_meta($order_id, '_billing_phone', true);
        if (empty($customer_phone)) {
            $customer_phone = '9999999999';
        }

        $csv_data[] = [
            'order_id' => $order_details['order_id'],
            'order_number' => $order_details['order_number'],
            'centimetre_length' => $box_params['length'],
            'centimetre_width' => $box_params['width'],
            'centimetre_height' => $box_params['height'],
            'grams_weight' => $selectWeight[$i],
            'postal_code' => $order_details['postcode'],
            'shipping_speed' => $shipping_speed, // Custom field
            'shipping_name' => $shipping_first_name . ' ' . $shipping_last_name, // Shipping full name
            'shipping_address_1' => $shipping_address_1,
            'shipping_address_2' => $shipping_address_2,
            'shipping_city' => $shipping_city,
            'shipping_state' => $shipping_state,
            'customer_phone' => $customer_phone, // Customer phone
        ];
        $i++;
    }


    // Create a CSV file
    $file_url = create_csv_file($csv_data, 'automate_orders_' . time() . '.csv');

    // Mark orders as exported
    mark_orders_as_exported($orderId);

    //Get Options 
    $webhook_url = get_option('wc_order_settings', '');

    if(!empty($webhook_url))
    {
        $response = wp_remote_post($webhook_url, [
            'method'    => 'POST',
            'body'      => json_encode( $csv_data),
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
        ]);
    
        if (is_wp_error($response)) {
            error_log('Webhook request failed: ' . $response->get_error_message());
        } else {
            error_log('Webhook request succeeded: ' . wp_remote_retrieve_body($response));
        }
    }
    

    // Return the file URL
    wp_send_json_success(['file_url' => $file_url]);
}

/**
 * add scripts
 */
add_action('admin_footer', 'load_custom_wp_admin_style');

function load_custom_wp_admin_style() {
    $screen = get_current_screen();
    if ($screen->id !== 'order-exporter') {
        return;
    }
    ?>
       <!-- JavaScript to Handle Select All Functionality -->
       <script type="text/javascript">
    document.getElementById('select-all').addEventListener('change', function() {
        // Get all checkboxes with class "order-checkbox"
        var checkboxes = document.querySelectorAll('.order-checkbox');
        // Loop through all checkboxes and set their checked state
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = document.getElementById('select-all').checked;
        });
    });
    jQuery(document).ready(function($) {
    $('.order-box').change(function() {
        var boxSize = $(this).val();
        var orderId = $(this).closest('.order-row').data('order-id');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'save_box_size',
                order_id: orderId,
                box_size: boxSize
            },
            success: function(response) {
               // alert('Box size saved successfully.');
            }
        });
        });

        $('.order-weight').change(function() {
            var weight = $(this).val();
            var orderId = $(this).closest('.order-row').data('order-id');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'save_box_weight',
                    order_id: orderId,
                    weight: weight
                },
                success: function(response) {
                    //alert('Box size saved successfully.');
                }
            });
        });
    });
    </script>
<?php
}