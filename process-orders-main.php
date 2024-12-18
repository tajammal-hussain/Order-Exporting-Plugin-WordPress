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
add_action('admin_enqueue_scripts', 'order_exporter_styles_and_scripts');

// Create Admin Menu
function order_exporter_menu() {
    add_menu_page(
        'Order Exporter', // Page title
        'Order Exporter', // Menu title
        'manage_options', // Capability required
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
}



// Helper function to detect PO Box addresses
function is_po_box_address($address) {
    return preg_match('/\b(PO BOX|P\.O\. BOX|P\.O\.BOX|BOX)\b/i', $address);
}



// Main Plugin Page with Tabs
function order_exporter_page() {
    ?>
    <div class="wrap">
        <h1>Order Exporter</h1>

        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=order-exporter&tab=processing" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'processing') || !isset($_GET['tab']) ? 'nav-tab-active' : ''; ?>">Processing Orders</a>
            <a href="?page=order-exporter&tab=exported" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'exported' ? 'nav-tab-active' : ''; ?>">Exported Orders</a>
            <a href="?page=order-exporter&tab=pobox" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'pobox' ? 'nav-tab-active' : ''; ?>">PO Box Orders</a>
        </h2>
        <div style="margin-bottom: 15px; margin-top:15px; display: flex; align-items: center; gap:20px;  justify-content: space-between;">
            <div class="left-buttons">
                <button id="export-shipstation" class="button button-primary">Export for ShipStation</button>
                <button id="export-sendle" class="button button-primary">Export for Sendle</button>
            </div>
            <a href="<?php echo admin_url('admin.php?page=wc-box-sizes'); ?>" class="button">Manage Box Sizes</a>
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
            document.querySelectorAll('.order-checkbox:checked').forEach(function(checkbox) {
                selectedOrders.push(checkbox.closest('tr').getAttribute('data-order-id'));
            });

            if (selectedOrders.length === 0) {
                alert("Please select at least one order.");
                return;
            }

            // Show a message that the process is ongoing
            document.getElementById('csv-message').innerHTML = 'Generating CSV file, please wait...';

            // Send the selected orders to the backend for CSV generation
            var data = {
                action: 'generate_csv_sendle',
                orders: selectedOrders
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
            document.querySelectorAll('.order-checkbox:checked').forEach(function(checkbox) {
                selectedOrders.push(checkbox.closest('tr').getAttribute('data-order-id'));
            });

            if (selectedOrders.length === 0) {
                alert("Please select at least one order.");
                return;
            }

            // Show a message that the process is ongoing
            document.getElementById('csv-message').innerHTML = 'Generating CSV file, please wait...';

            // Send the selected orders to the backend for CSV generation
            var data = {
                action: 'generate_csv_shipstation',
                orders: selectedOrders
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
        $results = array_filter($queryParams, function($orders) {
            $order = wc_get_order($orders->ID);
            $full_address = "{$order->get_shipping_address_1()}, {$order->get_shipping_address_2()}, {$order->get_shipping_city()}, {$order->get_shipping_state()}";
            return !is_po_box_address($full_address);
        });

        $total_processing_orders = count($results);
        $boxes = get_option('wc_package_boxes', []);

    ?>
    <div class="wrap">
        <h1>Processing Orders (<?php echo $total_processing_orders; ?>)</h1>

        <table class="wp-list-table widefat fixed striped posts" style="margin-top:15px;">
            <thead>
                    <th scope="col" class="manage-column"><input type="checkbox" id="select-all"></th>
                    <th scope="col" class="manage-column"><strong>Order</strong></th>
                    <th scope="col" class="manage-column"><strong>Date</strong></th>
                    <th scope="col" class="manage-column"><strong>Box Size</strong></th>
                    <th scope="col" class="manage-column"><strong>Weight (gr)</strong></th>
                    <th scope="col" class="manage-column"><strong>Name</strong></th>
                    <th scope="col" class="manage-column"><strong>Shipping State</strong></th>
                    <th scope="col" class="manage-column"><strong>Shipping Address</strong></th>
            </thead>
            <tbody>
                <?php
                if(!empty($results)):
                  foreach ($results as $result):
                    $order = wc_get_order($result->ID);
                    $order_id = $order->get_id();
                    $first_name = $order->get_shipping_first_name();
                    $last_name = $order->get_shipping_last_name();
                    $address_1 = $order->get_shipping_address_1();
                    $address_2 = $order->get_shipping_address_2();
                    $city = $order->get_shipping_city();
                    $shipping_state_code = $order->get_shipping_state();
                    $shipping_country_code = $order->get_shipping_country();
                    $states = WC()->countries->get_states($shipping_country_code);
                    $shipping_state_full_name = isset($states[$shipping_state_code]) ? $states[$shipping_state_code] : $shipping_state_code;
                    
                    $box_params = get_post_meta($order_id, 'box_size', true);
                    $box_params = json_decode($box_params, true);
                    
                    $box_weight = get_post_meta($order_id, 'box_weight', true);
                    $weight = empty($box_weight) ? '750' : $box_weight; // Default weight
                    
                    $postcode = $order->get_shipping_postcode();
                    $full_name = trim("$first_name $last_name");
                    
                     // Calculate and format the date
                     $order_date = $order->get_date_created();
                     $current_date = new DateTime();
                     $interval = $current_date->diff($order_date);
                     $formatted_date = $interval->d > 0
                         ? $order_date->format('M d Y')
                         : ($interval->h > 0
                             ? $interval->h . ' hours ago'
                             : $interval->i . ' minutes ago');
 
                     // Check if the address is a PO Box
                     $full_address = "$address_1, $address_2, $city, $shipping_state_code, $postcode";
                    
                     ?>

                     <tr class="order-row"
                     data-order-id="<?php echo esc_attr($order_id); ?>"
                     data-is-po-box="<?php echo $is_po_box ? 'true' : 'false'; ?>"
                     data-first-name="<?php echo esc_attr($first_name); ?>"
                     data-last-name="<?php echo esc_attr($last_name); ?>"
                     data-address-line-1="<?php echo esc_attr($address_1); ?>"
                     data-address-line-2="<?php echo esc_attr($address_2); ?>"
                     data-city="<?php echo esc_attr($city); ?>"
                     data-state="<?php echo esc_attr($shipping_state_full_name); ?>"
                     data-postcode="<?php echo esc_attr($postcode); ?>"
                     data-shippingcart = "<?php echo get_post_meta($order_id, 'pg_shipping_method_cart', true); ?>"
                     >
                     <td><input type="checkbox" class="order-checkbox" /></td>
                     <td><strong><?php echo esc_html($order->get_order_number()); ?></strong></td>
                     <td><?php echo esc_html($formatted_date); ?></td>
                     <td>
                         <select class="order-box" id="order-box">
                             <option value="">Select a box</option>
                             <?php foreach ($boxes as $box): ?>
                                 <option value="<?php echo esc_attr(json_encode($box)); ?>" 
                                         <?php 
                                         if(!empty($box_params)):
                                            echo in_array($box['name'], $box_params) ? 'selected' : ''; 
                                         endif;
                                         ?>
                                 >
                                     <?php echo esc_html($box['name']); ?>
                                 </option>
                             <?php endforeach; ?>
                         </select>
                     </td>
                     <td><input type="number" class="order-weight" id="order-weight" value="<?php echo esc_attr($weight); ?>"></td>
                     <td><?php echo esc_html($full_name); ?></td>
                     <td><?php echo esc_html($shipping_state_full_name); ?></td>
                     <td><?php echo esc_html($full_address); ?></td>
                 </tr>

                <?php endforeach; 
                endif;
                ?>
            </tbody>
        </table>
    </div>

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
        $('#order-box').change(function() {
            var boxSize = $(this).val();
            var orderId = <?php echo $order->get_id(); ?>;
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'save_box_size',
                    order_id: orderId,
                    box_size: boxSize
                },
                success: function(response) {
                    alert('Box size saved successfully.');
                }
            });
            });

            $('#order-weight').change(function() {
                var weight = $(this).val();
                var orderId = <?php echo $order->get_id(); ?>;
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'save_box_weight',
                        order_id: orderId,
                        weight: weight
                    },
                    success: function(response) {
                        alert('Box size saved successfully.');
                    }
                });
            });
        });
    </script>
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

    $boxes = get_option('wc_package_boxes', []);
    ?>
    <div class="wrap">
    <h1>Exported Orders (<?php echo $total_processing_orders; ?>)</h1>

    <table class="wp-list-table widefat fixed striped posts" style="margin-top:15px;">
        <thead>
                <th scope="col" class="manage-column"><input type="checkbox" id="select-all"></th>
                <th scope="col" class="manage-column"><strong>Order</strong></th>
                <th scope="col" class="manage-column"><strong>Date</strong></th>
                <th scope="col" class="manage-column"><strong>Box Size</strong></th>
                <th scope="col" class="manage-column"><strong>Weight (gr)</strong></th>
                <th scope="col" class="manage-column"><strong>Name</strong></th>
                <th scope="col" class="manage-column"><strong>Shipping State</strong></th>
                <th scope="col" class="manage-column"><strong>Shipping Address</strong></th>
        </thead>
        <tbody>
        <?php
        if(!empty($results)):

            foreach ($results as $result):
                $order = wc_get_order($result->ID);
                $order_id = $order->get_id();
                $first_name = $order->get_shipping_first_name();
                $last_name = $order->get_shipping_last_name();
                $address_1 = $order->get_shipping_address_1();
                $address_2 = $order->get_shipping_address_2();
                $city = $order->get_shipping_city();
                $shipping_state_code = $order->get_shipping_state();
                $shipping_country_code = $order->get_shipping_country();
                $states = WC()->countries->get_states($shipping_country_code);
                $shipping_state_full_name = isset($states[$shipping_state_code]) ? $states[$shipping_state_code] : $shipping_state_code;
                
                $box_params = get_post_meta($order_id, 'box_size', true);
                $box_params = json_decode($box_params, true);
                
                $box_weight = get_post_meta($order_id, 'box_weight', true);
                $weight = empty($box_weight) ? '750' : $box_weight; // Default weight
                
                $postcode = $order->get_shipping_postcode();
                $full_name = trim("$first_name $last_name");
                
                // Calculate and format the date
                $order_date = $order->get_date_created();
                $current_date = new DateTime();
                $interval = $current_date->diff($order_date);
                $formatted_date = $interval->d > 0
                    ? $order_date->format('M d Y')
                    : ($interval->h > 0
                        ? $interval->h . ' hours ago'
                        : $interval->i . ' minutes ago');

                // Check if the address is a PO Box
                $full_address = "$address_1, $address_2, $city, $shipping_state_code, $postcode";
                
                ?>

                <tr class="order-row"
                data-order-id="<?php echo esc_attr($order_id); ?>"
                data-is-po-box="<?php echo $is_po_box ? 'true' : 'false'; ?>"
                data-first-name="<?php echo esc_attr($first_name); ?>"
                data-last-name="<?php echo esc_attr($last_name); ?>"
                data-address-line-1="<?php echo esc_attr($address_1); ?>"
                data-address-line-2="<?php echo esc_attr($address_2); ?>"
                data-city="<?php echo esc_attr($city); ?>"
                data-state="<?php echo esc_attr($shipping_state_full_name); ?>"
                data-postcode="<?php echo esc_attr($postcode); ?>"
                data-shippingcart = "<?php echo get_post_meta($order_id, 'pg_shipping_method_cart', true); ?>"
                >
                <td><input type="checkbox" class="order-checkbox" /></td>
                <td><strong><?php echo esc_html($order->get_order_number()); ?></strong></td>
                <td><?php echo esc_html($formatted_date); ?></td>
                <td>
                    <select class="order-box" id="order-box">
                        <option value="">Select a box</option>
                        <?php foreach ($boxes as $box): ?>
                            <option value="<?php echo esc_attr(json_encode($box)); ?>" 
                                    <?php 
                                    if(!empty($box_params)):
                                        echo in_array($box['name'], $box_params) ? 'selected' : ''; 
                                    endif;
                                    ?>
                            >
                                <?php echo esc_html($box['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" class="order-weight" id="order-weight" value="<?php echo esc_attr($weight); ?>"></td>
                <td><?php echo esc_html($full_name); ?></td>
                <td><?php echo esc_html($shipping_state_full_name); ?></td>
                <td><?php echo esc_html($full_address); ?></td>
            </tr>

            <?php endforeach;  
    endif;

            ?>
        </tbody>
    </table>
    </div>

 
    <?php
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

?>
<div class="wrap">
    <h1>PO Box Orders (<?php echo $total_processing_orders; ?>)</h1>

    <table class="wp-list-table widefat fixed striped posts" style="margin-top:15px;">
        <thead>
                <th scope="col" class="manage-column"><input type="checkbox" id="select-all"></th>
                <th scope="col" class="manage-column"><strong>Order</strong></th>
                <th scope="col" class="manage-column"><strong>Date</strong></th>
                <th scope="col" class="manage-column"><strong>Box Size</strong></th>
                <th scope="col" class="manage-column"><strong>Weight (gr)</strong></th>
                <th scope="col" class="manage-column"><strong>Name</strong></th>
                <th scope="col" class="manage-column"><strong>Shipping State</strong></th>
                <th scope="col" class="manage-column"><strong>Shipping Address</strong></th>
        </thead>
        <tbody>
            <?php
            if(!empty($results)):
              foreach ($results as $result):
                $order = wc_get_order($result->ID);
                $order_id = $order->get_id();
                $first_name = $order->get_shipping_first_name();
                $last_name = $order->get_shipping_last_name();
                $address_1 = $order->get_shipping_address_1();
                $address_2 = $order->get_shipping_address_2();
                $city = $order->get_shipping_city();
                $shipping_state_code = $order->get_shipping_state();
                $shipping_country_code = $order->get_shipping_country();
                $states = WC()->countries->get_states($shipping_country_code);
                $shipping_state_full_name = isset($states[$shipping_state_code]) ? $states[$shipping_state_code] : $shipping_state_code;
                
                $box_params = get_post_meta($order_id, 'box_size', true);
                $box_params = json_decode($box_params, true);
                
                $box_weight = get_post_meta($order_id, 'box_weight', true);
                $weight = empty($box_weight) ? '750' : $box_weight; // Default weight
                
                $postcode = $order->get_shipping_postcode();
                $full_name = trim("$first_name $last_name");
                
                 // Calculate and format the date
                 $order_date = $order->get_date_created();
                 $current_date = new DateTime();
                 $interval = $current_date->diff($order_date);
                 $formatted_date = $interval->d > 0
                     ? $order_date->format('M d Y')
                     : ($interval->h > 0
                         ? $interval->h . ' hours ago'
                         : $interval->i . ' minutes ago');

                 // Check if the address is a PO Box
                 $full_address = "$address_1, $address_2, $city, $shipping_state_code, $postcode";
                
                 ?>

                 <tr class="order-row"
                 data-order-id="<?php echo esc_attr($order_id); ?>"
                 data-is-po-box="<?php echo $is_po_box ? 'true' : 'false'; ?>"
                 data-first-name="<?php echo esc_attr($first_name); ?>"
                 data-last-name="<?php echo esc_attr($last_name); ?>"
                 data-address-line-1="<?php echo esc_attr($address_1); ?>"
                 data-address-line-2="<?php echo esc_attr($address_2); ?>"
                 data-city="<?php echo esc_attr($city); ?>"
                 data-state="<?php echo esc_attr($shipping_state_full_name); ?>"
                 data-postcode="<?php echo esc_attr($postcode); ?>"
                 data-shippingcart = "<?php echo get_post_meta($order_id, 'pg_shipping_method_cart', true); ?>"
                 >
                 <td><input type="checkbox" class="order-checkbox" /></td>
                 <td><strong><?php echo esc_html($order->get_order_number()); ?></strong></td>
                 <td><?php echo esc_html($formatted_date); ?></td>
                 <td>
                     <select class="order-box" id="order-box">
                         <option value="">Select a box</option>
                         <?php foreach ($boxes as $box): ?>
                             <option value="<?php echo esc_attr(json_encode($box)); ?>" 
                                     <?php 
                                     if(!empty($box_params)):
                                        echo in_array($box['name'], $box_params) ? 'selected' : ''; 
                                     endif;
                                     ?>
                             >
                                 <?php echo esc_html($box['name']); ?>
                             </option>
                         <?php endforeach; ?>
                     </select>
                 </td>
                 <td><input type="number" class="order-weight" id="order-weight" value="<?php echo esc_attr($weight); ?>"></td>
                 <td><?php echo esc_html($full_name); ?></td>
                 <td><?php echo esc_html($shipping_state_full_name); ?></td>
                 <td><?php echo esc_html($full_address); ?></td>
             </tr>

            <?php endforeach; 
            endif;
            ?>
        </tbody>
    </table>
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

    add_settings_section(
        'wc_box_section',
        'Box Sizes',
        null,
        'wc-box-sizes'
    );

    add_settings_field(
        'package_boxes',
        'Boxes',
        'render_box_settings_field',
        'wc-box-sizes',
        'wc_box_section'
    );
});

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
            `;
            container.appendChild(div);
        });
    </script>
    <?php
}



/***
 * CRON JOB
 */

// Sandle of scheduling a cron job
function schedule_csv_generation_cron() {
    if (!wp_next_scheduled('generate_csv_sendle_cron_job')) {
        wp_schedule_event(time(), 'hourly', 'generate_csv_sendle_cron_job');
    }
}
add_action('wp', 'schedule_csv_generation_cron');

// Hook for generating CSV via cron job
add_action('generate_csv_sendle_cron_job', 'generate_csv_sendle_file');

// Clear scheduled event on plugin deactivation
function deactivate_cron_job() {
    wp_clear_scheduled_hook('generate_csv_sendle_cron_job');
}
register_deactivation_hook(__FILE__, 'deactivate_cron_job');

// Hook for AJAX action
add_action('wp_ajax_generate_csv_sendle', 'generate_csv_sendle_file');

function generate_csv_sendle_file() {
    // Check nonce for security (if needed)
    // if ( ! isset( $_POST['nonce_field'] ) || ! wp_verify_nonce( $_POST['nonce_field'], 'nonce_action' ) ) {
    //    die('Permission denied');
    // }

    // Get the order IDs from the request
    $order_ids = isset($_POST['orders']) ? $_POST['orders'] : [];

    if (empty($order_ids)) {
        wp_send_json_error(['message' => 'No orders selected.']);
    }

    // Prepare the CSV data
    $csv_data = [];
    foreach ($order_ids as $order_id) {
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


        $csv_data[] = [
            'receiver_name' => $full_name,
            'receiver_address_line1' => $address_1,
            'receiver_address_line2' => $address_2,
            'receiver_suburb' => $city,
            'receiver_state_name' => $shipping_state_code,
            'receiver_postcode' => $postcode,
            'receiver_country' => $shipping_country_code,
            'receiver_contact_number' => $phone,
            'delivery_instructions' => '',
            'customer_reference' => $order->get_order_number(),
            'kilogram_weight' => $weight,
            'centimetre_length' => $centimetre_length,
            'centimetre_width' => $centimetre_width,
            'centimetre_height' => $centimetre_height,
            'pickup_date' => '',
        ];
    }

    // Create a CSV file
    $upload_dir = wp_upload_dir();
    $csv_file = $upload_dir['path'] . '/orders_' . time() . '.csv';
    $file = fopen($csv_file, 'w');

    // Add CSV headers
    fputcsv($file, array_keys($csv_data[0]));

    // Add data rows
    foreach ($csv_data as $row) {
        fputcsv($file, $row);
    }

    fclose($file);

    // Now, mark these orders as exported
    foreach ($order_ids as $order_id) {
        // Check if the order has not already been marked as exported
        if (!get_post_meta($order_id, '_order_exported', true)) {
            // Add the '_order_exported' meta key to the order
            update_post_meta($order_id, '_order_exported', true);
        }
    }

    // Return the file URL
    wp_send_json_success(['file_url' => $upload_dir['url'] . '/' . basename($csv_file)]);
}



/**
 * Shipstation
 */

 function schedule_shipstation_csv_generation_cron() {
    if (!wp_next_scheduled('generate_csv_shipstation_cron_job')) {
        wp_schedule_event(time(), 'hourly', 'generate_csv_shipstation_cron_job');
    }
}
add_action('wp', 'schedule_shipstation_csv_generation_cron');

// Hook for generating CSV via cron job
add_action('generate_csv_shipstation_cron_job', 'generate_csv_shipstation_file');

// Clear scheduled event on plugin deactivation
function deactivate_shipstation_cron_job() {
    wp_clear_scheduled_hook('generate_csv_shipstation_cron_job');
}
register_deactivation_hook(__FILE__, 'deactivate_shipstation_cron_job');


// Hook Shipstation for AJAX action
add_action('wp_ajax_generate_csv_shipstation', 'generate_csv_shipstation_file');

function generate_csv_shipstation_file() {
    // Check nonce for security (if needed)
    // if ( ! isset( $_POST['nonce_field'] ) || ! wp_verify_nonce( $_POST['nonce_field'], 'nonce_action' ) ) {
    //    die('Permission denied');
    // }

    // Get the order IDs from the request
    $order_ids = isset($_POST['orders']) ? $_POST['orders'] : [];

    if (empty($order_ids)) {
        wp_send_json_error(['message' => 'No orders selected.']);
    }

    // Prepare the CSV data
    $csv_data = [];
    foreach ($order_ids as $order_id) {
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


        $csv_data[] = [
            'Order #' => $order->get_order_number(),
            'Height(cm)' => $centimetre_height,
            'Length(cm)' => $centimetre_length,
            'Width(cm)' => $centimetre_width,
            'Weight(gr)' => $weight,
            'Custom Field 1' => '',
            'Custom Field 2' => '',
            'Custom Field 3' => $phone,
            'Recipient First Name' => $first_name,
            'Recipient Last Name' => $last_name,
            'Recipient Phone' => $phone,
            'Address Line 1' => $centimetre_length,
            'Address Line 2' => $centimetre_width,
            'City' => $centimetre_height,
            'State' => $shipping_state_code,
            'Postal Code' => $postcode,
            'Country Code' => $shipping_country_code,
        ];
    }

    // Create a CSV file
    $upload_dir = wp_upload_dir();
    $csv_file = $upload_dir['path'] . '/orders_' . time() . '.csv';
    $file = fopen($csv_file, 'w');

    // Add CSV headers
    fputcsv($file, array_keys($csv_data[0]));

    // Add data rows
    foreach ($csv_data as $row) {
        fputcsv($file, $row);
    }

    fclose($file);

    // Now, mark these orders as exported
    foreach ($order_ids as $order_id) {
        // Check if the order has not already been marked as exported
        if (!get_post_meta($order_id, '_order_exported', true)) {
            // Add the '_order_exported' meta key to the order
            update_post_meta($order_id, '_order_exported', true);
        }
    }

    // Return the file URL
    wp_send_json_success(['file_url' => $upload_dir['url'] . '/' . basename($csv_file)]);
}



/**
 * add scripts
 */
add_action('admin_footer', 'load_custom_wp_admin_style');

function load_custom_wp_admin_style() {
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
                alert('Box size saved successfully.');
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
                    alert('Box size saved successfully.');
                }
            });
        });
    });
    </script>
<?php
}
