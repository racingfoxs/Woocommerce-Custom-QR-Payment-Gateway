<?php
/**
 * Plugin Name: Custom QR Payment Gateway
 * Description: Custom WooCommerce manual payment gateway for QR code.
 * Version: 1.0
 * Author: Divyashwar Raj Gurung
 */

add_action('wp_enqueue_scripts', 'enqueue_esewa_scripts');
add_action('wp_ajax_esewa_upload', 'handle_esewa_upload');
add_action('wp_ajax_nopriv_esewa_upload', 'handle_esewa_upload');
add_action('wp_enqueue_scripts', 'enqueue_custom_plugin_styles');

function enqueue_custom_plugin_styles() {
    if (is_checkout() || is_order_received_page()) {
        wp_enqueue_style('custom-plugin-style', plugins_url('/css/style.css', __FILE__));
    }
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('plugins_loaded', 'init_custom_esewa_gateway');

    function init_custom_esewa_gateway()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        class WC_Gateway_Custom_eSewa extends WC_Payment_Gateway
        {
            public function __construct()
            {
                $this->id                 = 'custom_esewa';
                $this->has_fields         = true;
                $this->method_title       = 'Pay via QR (eSewa)';
                $this->method_description = 'Custom payment gateway for paying via eSewa QR code.';

                $this->init_form_fields();
                $this->init_settings();

                $this->title        = $this->get_option('title');
                $this->description  = $this->get_option('description');
                $this->qr_image_url = $this->get_option('qr_image_url');

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_esewa_screenshot_in_admin'));
                add_action('woocommerce_checkout_update_order_meta', array($this, 'save_esewa_payment_screenshot'));
            }

            public function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => 'Enable/Disable',
                        'type'    => 'checkbox',
                        'label'   => 'Enable Pay via QR code',
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title'       => 'Title',
                        'type'        => 'text',
                        'description' => 'This controls the title which the user sees during checkout.',
                        'default'     => 'Pay online via QR code(eSewa/Phonepe/Banking/etc)',
                        'desc_tip'    => true,
                    ),
                    'description' => array(
                        'title'       => 'Description',
                        'type'        => 'textarea',
                        'description' => 'This controls the description which the user sees during checkout.',
                        'default'     => 'Please scan the QR code below to pay with QR code.',
                    ),
                    'qr_image_url' => array(
                        'title'       => 'QR Code Image',
                        'type'        => 'text',
                        'description' => 'Enter the URL to the QR code image.',
                        'default'     => '',
                    ),
                );
            }

            public function payment_fields()
            {
               
                if ($this->description) {
                    echo wpautop(wp_kses_post($this->description));

                }
                $order_total = WC()->cart->get_total();
                echo '<p>Total amount: '. $order_total .'</p><br>';

                if ($this->qr_image_url) {
                    echo '<img src="' . esc_url($this->qr_image_url) . '" alt="QR Code" style="max-width:200px;"><br><br>';
                }

                echo '<p>Upload Screenshot of Payment : </p>';
                echo '<input type="file" id="esewa_payment_screenshot" name="esewa_payment_screenshot" accept=".jpg, .jpeg, .png">';
                echo '<div id="uploaded_image" class="screenshot_upload_after"></div>';
            }
            

            public function process_admin_options()
            {
                parent::process_admin_options();
            }

            public function process_payment($order_id)
            {
                $order = wc_get_order($order_id);
                $order->update_status('on-hold', __('Awaiting eSewa payment verification', 'woocommerce'));
                wc_reduce_stock_levels($order_id);
                WC()->cart->empty_cart();
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            }

            public function save_esewa_payment_screenshot($order_id)
            {
                if (isset($_POST['esewa_screenshot_url'])) {
                    $file_url = esc_url($_POST['esewa_screenshot_url']);
                    update_post_meta($order_id, '_esewa_payment_screenshot', $file_url);
                }
            }

            public function display_esewa_screenshot_in_admin($order)
            {
                $order_id = $order->get_id();
                $file_url = get_post_meta($order_id, '_esewa_payment_screenshot', true);
                if ($file_url) {
                    echo '<p class="form-field form-field-wide wc-customer-screenshot" style="margin-top: 16px;"><strong>Payment Screenshot:</strong><br><img src="' . esc_url($file_url) . '" style="max-width:300px;"></p>';
                }
                
            }
        }

        function add_custom_esewa_gateway($methods)
        {
            $methods[] = 'WC_Gateway_Custom_eSewa';
            return $methods;
        }
        add_filter('woocommerce_payment_gateways', 'add_custom_esewa_gateway');
    }
}

function enqueue_esewa_scripts()
{
    if (is_checkout() || is_order_received_page()) {
        wp_enqueue_script('jquery');
        wp_enqueue_script('ajax-upload-script', plugins_url('/js/ajax-upload.js', __FILE__), array('jquery'), null, true);
        wp_localize_script('ajax-upload-script', 'ajax_upload_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ajax-upload-nonce')
        ));
    }
}
function handle_esewa_upload()
{
    check_ajax_referer('ajax-upload-nonce', 'security');

    if (!empty($_FILES['esewa_payment_screenshot']['name'])) {
        $uploaded_file = $_FILES['esewa_payment_screenshot'];

        // Check file size (10 MB limit)
        $max_upload_size = wp_max_upload_size();
        if ($uploaded_file['size'] > $max_upload_size) {
            echo json_encode(array('error' => 'File size exceeds the limit (' . size_format($max_upload_size, 2) . ')'));
            wp_die();
        }

        // Allowed mime types
        $allowed_mime_types = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif'          => 'image/gif',
            'png'          => 'image/png',
        );

        // Validate the file type
        $file_type = wp_check_filetype($_FILES['esewa_payment_screenshot']['name'], $allowed_mime_types);

        if (!$file_type['ext'] || !$file_type['type']) {
            echo json_encode(array('error' => 'Invalid file type. Only JPEG, PNG, and GIF formats are allowed.'));
            wp_die();
        }

        // Handle the upload
        $upload_overrides = array(
            'test_form' => false,
            'mimes'     => $allowed_mime_types,
        );

        $upload = wp_handle_upload($uploaded_file, $upload_overrides);

        if (isset($upload['error'])) {
            echo json_encode(array('error' => $upload['error']));
            wp_die();
        }

        echo json_encode(array('url' => $upload['url']));
    } else {
        echo json_encode(array('error' => 'No file uploaded.'));
    }

    wp_die();
}



?>
