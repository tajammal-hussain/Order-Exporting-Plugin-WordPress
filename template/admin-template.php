    <div class="wrap">
    <h1><?php echo $title; ?> (<?php echo $total_processing_orders; ?>)</h1>

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
                        <?php foreach ($data as $key => $value): ?>
                            <?php 
                            var_dump($box_params);
                            $option_data = json_encode(['key' => $key, 'value' => $value]);
                            $selected = (!empty($box_params) && $box_params === $option_data) ? 'selected' : '';
                            ?>
                            <option value="<?php echo esc_attr($option_data); ?>" <?php echo $selected; ?>>
                                <?php echo esc_html($key); ?>
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
    $('.order-box').change(function() {
        var boxSize = $(this).val();
        var orderId = $(this).closest('tr.order-row').data('order-id');
        console.log(orderId);
        
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