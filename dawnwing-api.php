<?php

/**
 * @link			  https://www.byronjacobs.co.za/
 * @since			  1.0.0
 * @package			  Byron Jacobs
 *
 * @wordpress-plugin
 * Plugin Name:		  Dawnwing Waybill API
 * Plugin URI:		  https://www.byronjacobs.co.za/
 * Description:		  Byron Jacobs Core Integrations
 * Version:			  1.0.0
 * Author:			  Byron Jacobs
 * Author URI:		  https://www.byronjacobs.co.za/
 * License:			  GPL-2.0+
 * License URI:		  http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/* Add Shipping Status */
function register_shipped_order_status()
{
    register_post_status('wc-shipped', array(
        'label'                     => 'Shipping',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Shipping (%s)', 'Shipping (%s)')
    ));
}

add_action('init', 'register_shipped_order_status');

/* Add to list of WC Order statuses On Order Admin Page */
function add_shipped_to_order_statuses($order_statuses)
{
    $new_order_statuses = array();

    // add new order status after processing
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;

        if ('wc-processing' === $key) {
            $new_order_statuses['wc-shipped'] = 'Shipping';
        }
    }

    return $new_order_statuses;
}

add_filter('wc_order_statuses', 'add_shipped_to_order_statuses');

/* Send Shipped Order to Dawnwing when order is marked as Shipped */
add_action('woocommerce_order_status_shipped', 'woocommerce_order_status_shipped', 999, 1);
function woocommerce_order_status_shipped($order_id)
{
    if (!get_post_meta($order_id, 'dawnwing_labels', true)) {
        $dawnwing_generate_waybill_options = get_option('dawnwing_generate_waybill_option_name'); // Array of All Options
        $account_number_2 = $dawnwing_generate_waybill_options['account_number_2']; // Account Number
        $send_company_3 = $dawnwing_generate_waybill_options['send_company_3']; // Send Company
        $send_address_1_4 = $dawnwing_generate_waybill_options['send_address_1_4']; // Send Address 1
        $send_address_2_5 = $dawnwing_generate_waybill_options['send_address_2_5']; // Send Address 2
        $send_address_3_6 = $dawnwing_generate_waybill_options['send_address_3_6']; // Send Address 3
        $send_address_4_7 = $dawnwing_generate_waybill_options['send_address_4_7']; // Send Address 4
        $send_address_5_8 = $dawnwing_generate_waybill_options['send_address_5_8']; // Send Address 5
        $send_contact_person_9 = $dawnwing_generate_waybill_options['send_contact_person_9']; // Send Contact Person
        $send_work_tel_10 = $dawnwing_generate_waybill_options['send_work_tel_10']; // Send Work Tel
        $parcel_description_11 = $dawnwing_generate_waybill_options['parcel_description_11']; // Parcel Description

        // get order object and order details
        $order = new WC_Order($order_id);
        $order_number = $order->get_order_number();


        foreach ($order->get_items('shipping') as $item_id => $item) {
            $shipping_method_title       = $item->get_method_title();
        }

        if ($shipping_method_title == 'Express') {
            $shipping_type = "ONX1";
        } elseif ($shipping_method_title == 'Economy') {
            $shipping_type = "ECON";
        }

        // Shipping address
        $shipping_address = $order->get_address('shipping');

        // Billing address
        $billing_address = $order->get_address('billing');
        $phone = $first_name = $last_name = '';
        extract($billing_address);

        $address_1 = $address_2 = $city = $state = $postcode = '';
        extract($shipping_address);
        !strlen($address_2) > 0 ? $address_2 = 'empty' : '';

        // setup the data which has to be sent//
        $datawaybill = [
            "WaybillNo" => "" . $order_number,
            "SendAccNo" => $account_number_2,
            "SendCompany" => $send_company_3,
            "SendAdd1" => $send_address_1_4,
            "SendAdd2" => $send_address_2_5,
            "SendAdd3" => $send_address_3_6,
            "SendAdd4" => $send_address_4_7,
            "SendAdd5" => $send_address_5_8,
            "SendContactPerson" => $send_contact_person_9,
            "SendWorkTel" => $send_work_tel_10,
            "RecCompany" => "",
            "RecAdd1" => "",
            "RecAdd2" => $address_1,
            "RecAdd3" => $city,
            "RecAdd4" => $address_2,
            "RecAdd5" => $postcode,
            "RecAdd7" => $company,
            "RecContactPerson" => $first_name . ' ' . $last_name,
            "RecHomeTel" => "",
            "RecWorkTel" => $phone,
            "RecCell" => $phone,
            'ParcelNo' => "" . $order_number . "_1",
            "customerRef" => "" . $order_number,
            "SpecialInstructions" => $order->get_customer_note(),
            "ServiceType" => $shipping_type,
            "parcels" => [
                [
                    "WaybillNo" => "" . $order_number,
                    "Length" => "23",
                    "Height" => "26",
                    "Width" => "1",
                    "Mass" => "1",
                    "ParcelDescription" => $parcel_description_11,
                    "ParcelNo" => "FTGC" . $order_number . "_1",
                    "ParcelCount" => 1,
                ],
            ],
            "CompleteWaybillAfterSave" => true,
        ];

        $token = '';
        $ch = curl_init('https://swatws.dawnwing.co.za/dwwebservices/V2/uat/api/waybill'); // Initialise cURL
        $authorization = "Authorization: Bearer " . $token; // Prepare the authorisation token
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization)); // Inject the token into the header
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datawaybill)); // Set the posted fields
        $response = curl_exec($ch); // Execute the cURL statement
        curl_close($ch); // Close the cURL connection
        $response_array = json_decode($response, true);
        update_post_meta($order_id, 'dawnwing_api_response', $response);
        update_post_meta($order_id, 'dawnwing_multi', $parcels);
        update_post_meta($order_id, 'dawnwing_data', $datawaybill);
        $response_array = json_decode($response, true);

        if (isset($response_array, $response_array['data'])) {
            update_post_meta($order_id, 'dawnwing_labels', json_encode($response_array['data']));
            update_post_meta($order_id, 'dawnwing_multi', $parcels);
        }
    }
}

/* Display field value on the order edit page **/
function my_custom_billing_fields_display_admin_order_meta($order)
{
    $waybills = get_post_meta($order->id, 'dawnwing_labels', true);
    $response = get_post_meta($order->id, 'dawnwing_api_response', true);


    if ($waybills) {
        $waybills = json_decode($waybills);
        $counter = 0;
        foreach ($waybills as $waybill) {
            $counter++;
            echo '<p><strong>' . __('Waybill') . ':</strong> <a href="' . $waybill . '" target="_blank">Download Waybill ' . $counter . '</a></p>';
        }
    } elseif ($response) {
        echo '<p><strong>' . __('Waybill Error') . ':</strong> ' . get_post_meta($order->id, 'dawnwing_api_response', true) . '</p>';
    }
}
add_action('woocommerce_admin_order_data_after_billing_address', 'my_custom_billing_fields_display_admin_order_meta', 10, 1);

/* Add Shipped Bulk Action in Drop Down **/
function register_bulk_action($bulk_actions)
{
    $bulk_actions['wc-shipped'] = 'Change status to shipping'; // <option value="wc-shipped">Mark awaiting shipment</option>
    return $bulk_actions;
}

add_filter('bulk_actions-edit-shop_order', 'register_bulk_action'); // edit-shop_order is the screen ID of the orders page

/* Bulk Action Handler **/
function bulk_process_custom_status()
{
    // if an array with order IDs is not presented, exit the function
    if (!isset($_REQUEST['post']) && !is_array($_REQUEST['post'])) {
        return;
    }

    foreach ($_REQUEST['post'] as $order_id) {
        $order = new WC_Order($order_id);
        $order_note = 'Order status changed by bulk edit:';
        $order->update_status('wc-shipped', $order_note, true); // "misha-shipment" is the order status name (do not use wc-misha-shipment)
    }

    // of course using add_query_arg() is not required, you can build your URL inline
    $location = add_query_arg(array(
        'post_type' => 'shop_order',
        'marked_shipped' => 1, // marked_shipped=1 is just the $_GET variable for notices
        'changed' => count($_REQUEST['post']), // number of changed orders
        'ids' => join($_REQUEST['post'], ','),
        'post_status' => 'all'
    ), 'edit.php');

    wp_redirect(admin_url($location));
    exit;
}

add_action('admin_action_wc-shipped', 'bulk_process_custom_status'); // admin_action_{action name}

/* Show Notices After Status Changed **/
function custom_order_status_notices()
{
    global $pagenow, $typenow;

    if (
        $typenow == 'shop_order'
        && $pagenow == 'edit.php'
        && isset($_REQUEST['marked_shipped'])
        && $_REQUEST['marked_shipped'] == 1
        && isset($_REQUEST['changed'])
    ) {
        $message = sprintf(_n('Order status changed.', '%s orders marked as shipping.', $_REQUEST['changed']), number_format_i18n($_REQUEST['changed']));
        echo "<div class=\"updated\"><p>{$message}</p></div>";
    }
}

add_action('admin_notices', 'custom_order_status_notices');
