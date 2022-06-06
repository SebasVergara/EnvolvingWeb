<?php
// Silence is golden.
if (!defined('WPINC')) {
    die;
}

/**
 * Add Chilean States
 */
add_filter('woocommerce_states', 'custom_woocommerce_states');

/**
 * Get custom states from an WP_OPTION value, this value is set by a JSON file initially
 */
function custom_woocommerce_states($states)
{
    $chileStates = unserialize(get_option('swa_chileStates', array()));
    if (!$chileStates) {
        $chileStates = swa_getStates();
    }
    $states = array();
    foreach ($chileStates as $key => $state) {
        $states[$state['r_id'] . '-' . $state['c_id']] = $state['c_name'];
    }
    $states['CL'] = $states;

    return $states;
}

/**
 * Function that reads the Chilean states from a flat file and inserts them into the database so that they can be retrieved through data serialization
 */
function swa_getStates()
{
    $regions = swa_sendu_curl('/api/regions.json');
    $arr_comuna = array();
    foreach ($regions as $key => $region) {
        $comunas = swa_sendu_curl('/api/comunas_by_region.json?region_id=' . $region[0]);
        foreach ($comunas as $key => $comuna) {
            $arr_comuna[] = array(
                "r_id" => $region[0],
                "c_id" => $comuna[0],
                "c_name" => $comuna[1],
            );
        }
    }

    usort($arr_comuna, 'sortByComuna');
    update_option('swa_chileStates', serialize($arr_comuna), false);

    return $arr_comuna;
}

/**
 * Basic function that consults the SendU Rest API with the data that is configured in the Backend menu
 */
function swa_sendu_curl($endPoint)
{
    include 'constants.php';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sendu_base_url . $endPoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $headers = array();
    $headers[] = 'X-User-Email: ' . $sendu_auth_user;
    $headers[] = 'X-User-Token: ' . $sendu_auth_token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($result, true);
    return ($result);
}

/**
 * Function that orders the communes by name
 */
function sortByComuna($a, $b)
{
    return strnatcmp($a['c_name'], $b['c_name']);
}

/**
 * Function for AJAX call that, when the user fills out the place of delivery, can select the State of the country
 */
add_action('wp_ajax_swa_sendu_ajaxStateFun', 'swa_sendu_ajaxStateFun');

function swa_sendu_ajaxStateFun()
{
    $states = swa_getStates();
    if ($states) {
        echo true;
    } else {
        echo false;
    }
    wp_die();
}

/*
 * Check for WC and extend Shipping Method, add menu on WooCommerce Shipping with SendU options
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    function swa_sendu_shipping_method()
    {
        if (!class_exists('swa_sendu_shipping_method')) {
            class swa_sendu_shipping_method extends WC_Shipping_Method
            {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct()
                {
                    $this->id = 'swa_sendu';
                    $this->method_title = __('Sendu', 'swa_sendu');
                    $this->method_description = __('Do you have an e-commerce? We pick up and deliver for you. We make it very easy.', 'swa_sendu');

                    $this->init();

                    $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
                    $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Sendu', 'swa_sendu');

                    /**
                     * Add Chile as available Country
                     */
                    $this->availability = 'including';
                    $this->countries = array(
                        'CL', // Chile
                    );

                    $this->init();

                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init()
                {
                    // Load the settings API
                    $this->init_form_fields();
                    $this->init_settings();

                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                }

                /**
                 * Define settings field for this shipping
                 * @return void
                 */
                function init_form_fields()
                {

                    $this->form_fields = array(

                        'enabled' => array(
                            'title' => __('Enable', 'swa_sendu'),
                            'type' => 'checkbox',
                            'description' => __('Enable this shipping.', 'swa_sendu'),
                            'default' => 'yes',
                        ),

                        'title' => array(
                            'title' => __('Title', 'swa_sendu'),
                            'type' => 'text',
                            'description' => __('Title to be display on site', 'swa_sendu'),
                            'default' => __('Sendu', 'swa_sendu'),
                        ),

                        'senduUrl' => array(
                            'title' => __('SendU URL', 'swa_sendu'),
                            'type' => 'url',
                            'description' => __('Set the SendU API URL', 'swa_sendu'),
                            'default' => 'https://app.sendu.cl',
                        ),

                        'senduEmail' => array(
                            'title' => __('SendU Email', 'swa_sendu'),
                            'type' => 'email',
                            'description' => __('Set SendU email', 'swa_sendu'),
                            'default' => 'hola@sendu.cl',
                        ),

                        'senduToken' => array(
                            'title' => __('Token SendU', 'swa_sendu'),
                            'type' => 'text',
                            'description' => __('Set the SendU token', 'swa_sendu'),
                            'default' => 'ivDLta1k6n-YpzRsRgPk',
                        ),

                        'senduStatus' => array(
                            'title' => __('Status to generate order', 'swa_sendu'),
                            'type' => 'select',
                            'description' => __('In what order status should a work order be generated', 'swa_sendu'),
                            'options' => wc_get_order_statuses(),
                        ),

                        'senduProtection' => array(
                            'title' => __('Charge shipping protection', 'swa_sendu'),
                            'type' => 'checkbox',
                            'default' => 'no',
                        ),

                        'typeAlert' => array(
                            'title' => __('Alert dimensionless products', 'swa_sendu'),
                            'type' => 'select',
                            'description' => __('Alert dimensionless products, please note that the Noty bookstore does not display on the entire site', 'swa_sendu'),
                            'default' => 'yes',
                            'options' => array(
                                'disable' => __('Disabled', 'swa_sendu'),
                                'notice' => __('Notice', 'swa_sendu'),
                                'noty' => __('Noty Lib', 'swa_sendu'),
                            ),
                        ),
                        'senduNotQuotation' => array(
                            'title' => __('Action to take if there is no quote', 'swa_sendu'),
                            'type' => 'select',
                            'description' => __('If you choose to hide shipping method, the SendU shipping method for that area is not shown, if you want to add the text "No-Coverage" the shipping will not be charged and you must add it manually when you have a quote or agree with your client.', 'swa_sendu'),
                            'default' => 'label',
                            'options' => array(
                                'label' => __('Allow purchase with alert message', 'swa_sendu'),
                                'turnOff' => __('Inactivate carrier', 'swa_sendu'),
                            ),
                        ),
                        'senduNotQuotationLabel' => array(
                            'title' => __('Warning message', 'swa_sendu'),
                            'type' => 'text',
                            'description' => __('Warning message on non coverage', 'swa_sendu'),
                            'default' => __('--No Coverage--', 'swa_sendu'),
                        ),

                    );

                }

                /**
                 * This function is used to calculate the shipping cost. Within this function we can check for weights, dimensions and other parameters.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping($package = array())
                {
                    global $woocommerce;
                    $weight = 0;
                    $height = 0;
                    $cost = 0;
                    $cubage = 0;
                    $country = $package["destination"]["country"];
                    $state = $package["destination"]["state"];
                    $measures = array();
                    foreach ($package['contents'] as $item_id => $values) {
                        $_product = $values['data'];
                        if ( count($package['contents']) == 1 && intval($values['quantity']) == 1) {
                            $noCubage = true;
                            $noCubageD1 = $_product->get_height();
                            $noCubageD2 = $_product->get_length();
                            $noCubageD3 = $_product->get_width();
                        }
                        $weight = floatval($weight) + floatval($_product->get_weight()) * floatval($values['quantity']);
                        $cubage = $cubage + ($_product->get_height() * $_product->get_length() * $_product->get_width() * $values['quantity']);
                        array_push($measures, $_product->get_height(), $_product->get_length(), $_product->get_width());
                        if ($_product->get_height() == 0 || $_product->get_length() == 0 || $_product->get_width() == 0) {
                            $message = __('Sorry, some products do not have shipping measures therefore the shipping value cannot be fully quoted, keep in mind that the shipping value may vary', 'swa_sendu');
                            switch ($this->settings['typeAlert']) {
                                case 'notice':
                                    $messageType = "notice";
                                    // if (!wc_has_notice($message, $messageType)) {
                                    wc_add_notice($message, $messageType);
                                    // }
                                    break;
                                case 'noty':
                                    ?>
                                    <script>
                                        document.addEventListener('DOMContentLoaded', function(){
                                            console.log('Noty');
                                            new Noty({
                                                theme: 'light',
                                                type: 'warning',
                                                text: '<?php echo $message; ?>',
                                            }).show();
                                        }, false);
                                    </script>
                                    <?php
                                    break;
                                default:
                                    # code...
                                    break;
                            }
                        }
                    }
                    // $message = "Error";
                    // $messageType = "error";
                    // wc_add_notice($message, $messageType);

                    update_post_meta(11, 'state', $state);
                    $stateArr = explode('-', $state);
                    $stateComuna = $stateArr[1];
                    if ( $noCubage == true ) {
                        $dimension_1 = $noCubageD1;
                        $dimension_2 = $noCubageD2;
                        $dimension_3 = $noCubageD3;
                    } else {
                        $dimension_1 = max($measures);
                        $dimension_2 = sqrt(2 / 3 * $cubage / $dimension_1);
                        $dimension_3 = $cubage / $dimension_1 / $dimension_2;
                    }
                    $weight = wc_get_weight($weight, 'kg');
                    $subtotal = WC()->cart->get_subtotal();
                    $subtotalTax = WC()->cart->get_subtotal_tax();
                    $total = $subtotal + $subtotalTax;
                    // Do calculation
                    $calculation = $this->swa_sendu_calculation($dimension_1, $dimension_2, $dimension_3, $stateComuna, $weight, $total);
                    if (intval($calculation->customer_cost) < 0 ) {
                        $calculationCost = 1;
                        switch ($this->settings['senduNotQuotation']) {
                            case 'label':
                                wc_add_notice($this->settings['senduNotQuotationLabel'], 'notice');
                                $rate = array(
                                    'id' => $this->id,
                                    'label' => $this->title . ' ' . __('--No Coverage--', 'swa_sendu'),
                                    'cost' => 0,
                                );
                                $this->add_rate($rate);
                                break;
                            case 'turnOff':
                                break;
                        }
                    } else {
                        $calculationCost = $calculation->customer_cost;
                        $cost = $calculationCost;
                        $rate = array(
                            'id' => $this->id,
                            'label' => $this->title,
                            'cost' => $cost,
                        );
                        $this->add_rate($rate);
                    }
                    // End calculation
                }

                /**
                 * After the dimensions are calculated, a request is sent to the Sendu Rest API to obtain all the shipping information.
                 */
                public function swa_sendu_calculation($d1, $d2, $d3, $comuna, $weight, $total)
                {
                    $weight = floatval($weight);
                    $d1 = floatval($d1);
                    $d2 = floatval($d2);
                    $d3 = floatval($d3);
                    update_post_meta(60, 'borrar', $d1 . '-' . $d2 . '-' . $d3 . '-' . $weight);
                    $body = array(
                        "to" => intval($comuna),
                        "weight" => $weight,
                        "price_products" => intval($total),
                        "dimensions" => array(
                            "height" => $d1,
                            "large" => $d2,
                            "deep" => $d3,
                        ),
                    );
                    if ($this->settings['senduUrl']) {
                        $sendu_base_url = $this->settings['senduUrl'];
                    } else {
                        $sendu_base_url = 'https://app.sendu.cl';
                    }
                    if ($this->settings['senduEmail']) {
                        $sendu_auth_user = $this->settings['senduEmail'];
                    } else {
                        $sendu_auth_user = 'hola@sendu.cl';
                    }
                    if ($this->settings['senduToken']) {
                        $sendu_auth_token = $this->settings['senduToken'];
                    } else {
                        $sendu_auth_token = 'ivDLta1k6n-YpzRsRgPk';
                    }

                    $urlPath = '/api/calculator.json';

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $sendu_base_url . $urlPath,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "GET",
                        CURLOPT_POSTFIELDS => json_encode($body, JSON_PRESERVE_ZERO_FRACTION),
                        CURLOPT_HTTPHEADER => array(
                            "X-User-Email: " . $sendu_auth_user,
                            "X-User-Token: " . $sendu_auth_token,
                            "Content-Type: application/json",
                        ),
                    ));

                    $calculation = json_decode(curl_exec($curl));
                    if(curl_errno($curl)){
                        $calculation = json_decode(json_encode(array(
                            "courier_id" => 1,
                            "customer_cost" => -2,
                            "message" => ""
                        )));
                    }
                    curl_close($curl);
                    return $calculation;
                }

                /**
                 * Once the order is confirmed, a new work order is requested to be picked up by SendU
                 */
                public function swa_sendu_create_work_order($ID, $post, $update)
                {
                    if ($post->post_type == 'shop_order') {
                        $sendu_options = get_option('woocommerce_swa_sendu_settings');
                        $haveWorkOrder = get_post_meta($ID, 'work_order', true);
                        if (get_post_status($ID) == $sendu_options['senduStatus'] && strlen($haveWorkOrder) == 0) {
                            //create work order
                            $curl = curl_init();

                            if ($sendu_options['senduUrl']) {
                                $sendu_base_url = $sendu_options['senduUrl'];
                            } else {
                                $sendu_base_url = 'https://app.sendu.cl';
                            }
                            if ($sendu_options['senduEmail']) {
                                $sendu_auth_user = $sendu_options['senduEmail'];
                            } else {
                                $sendu_auth_user = 'hola@sendu.cl';
                            }
                            if ($sendu_options['senduToken']) {
                                $sendu_auth_token = $sendu_options['senduToken'];
                            } else {
                                $sendu_auth_token = 'ivDLta1k6n-YpzRsRgPk';
                            }
                            if ($sendu_options['senduProtection']) {
                                $sendu_protection = true;
                            } else {
                                $sendu_protection = false;
                            }
                            $urlPath = '/api/work_orders.json';
                            $email = get_post_meta($ID, '_billing_email', true);
                            $phone = get_post_meta($ID, '_billing_phone', true);
                            $order = wc_get_order($ID);
                            $weight = 0;
                            $cubage = 0;
                            $measures = array();
                            foreach ($order->get_items() as $item_id => $item) {
                                $product = $item->get_product();
                                if ( count($order->get_items()) == 1 && intval($item->get_quantity()) == 1) {
                                    $noCubage = true;
                                    $noCubageD1 = $product->get_height();
                                    $noCubageD2 = $product->get_length();
                                    $noCubageD3 = $product->get_width();
                                }
                                $weight = floatval($weight) + floatval($product->get_weight()) * floatval($item->get_quantity());
                                $cubage = $cubage + ($product->get_height() * $product->get_length() * $product->get_width() * $item->get_quantity());
                                array_push($measures, $product->get_height(), $product->get_length(), $product->get_width());
                                $terms = get_the_terms( $item->get_product_id(), 'product_cat' );
                                foreach ($terms as $key => $term) {
                                    $termsArray[] = $term->name;
                                }
                            }
                            $category = (implode(',',array_unique($termsArray)));
                            if ($noCubage == true) {
                                $dimension_1 = $noCubageD1;
                                $dimension_2 = $noCubageD2;
                                $dimension_3 = $noCubageD3;
                            } else {
                                $dimension_1 = max($measures);
                                $dimension_2 = sqrt(2 / 3 * $cubage / $dimension_1);
                                $dimension_3 = $cubage / $dimension_1 / $dimension_2;
                            }
                            $order_state = get_post_meta($ID, '_shipping_state', true);
                            $order_state = explode('-', $order_state);
                            $billing_rut = get_post_meta($ID, 'billing_rut', true);
                            $shippingAddress = get_post_meta($ID, '_shipping_address_1', true);
                            $shippingAddressStreet = get_post_meta($ID, 'shipping_address_1_street', true);
                            $shippingAddressNumeration = get_post_meta($ID, 'shipping_address_1_numeration', true);
                            $shippingAddressComplement = get_post_meta($ID, 'shipping_address_1_complement', true);
                            $body = array(
                                "work_order" => array(
                                    "order" => 'WC' . $ID,
                                    "category" => $category,
                                    "name" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                                    "email" => $email,
                                    "phone" => $phone,
                                    "weight" => $weight,
                                    "height" => $dimension_1,
                                    "large" => number_format($dimension_2, 1),
                                    "deep" => number_format($dimension_3, 1),
                                    "lost_coverage" => $sendu_protection,
                                    "price_products" => intval($order->get_total()) - intval($order->get_shipping_total()) - intval($order->get_shipping_tax()),
                                    // "price_products" => number_format($order->get_total(), 0) - number_format($order->get_shipping_total(), 0),
                                    "rut" => $billing_rut,
                                    "direction" => array(
                                        "region_id" => $order_state[0],
                                        "comuna_id" => $order_state[1],
                                        "street" => $shippingAddressStreet,
                                        "numeration" => $shippingAddressNumeration,
                                        "complement" => $shippingAddressComplement ?: '0',
                                    ),
                                ),
                            );
                            curl_setopt_array($curl, array(
                                CURLOPT_URL => $sendu_base_url . $urlPath,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => "POST",
                                CURLOPT_POSTFIELDS => json_encode($body, JSON_PRESERVE_ZERO_FRACTION),
                                CURLOPT_HTTPHEADER => array(
                                    "X-User-Email: " . $sendu_auth_user,
                                    "X-User-Token: " . $sendu_auth_token,
                                    "Content-Type: application/json",
                                ),
                            ));

                            $workOrder = json_decode(curl_exec($curl));

                            curl_close($curl);

                            // update_post_meta($ID, 'work_order', serialize($workOrder));
                            // update_post_meta($ID, 'work_order', intval($order->get_total()) );
                            if (intval($workOrder->id) > 0) {
                                update_post_meta($ID, 'work_order', $workOrder->id);
                            } else {
                                $curlGet = curl_init();
                                $urlPathGetOrder = '/api/work_orders.json?keywords=WC'. $ID;
                                curl_setopt_array($curlGet, array(
                                    CURLOPT_URL => $sendu_base_url . $urlPathGetOrder,
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => "",
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => "GET",
                                    CURLOPT_POSTFIELDS => json_encode($body, JSON_PRESERVE_ZERO_FRACTION),
                                    CURLOPT_HTTPHEADER => array(
                                        "X-User-Email: " . $sendu_auth_user,
                                        "X-User-Token: " . $sendu_auth_token,
                                        "Content-Type: application/json",
                                    ),
                                ));
                                $workOrderGet = json_decode(curl_exec($curlGet));

                                update_post_meta($ID, 'work_order', ($workOrderGet[0]->id));
                            }
                        }
                    }
                }
            }
        }
    }

    add_action('init', 'swa_sendu_shipping_method');
    // add_action('woocommerce_shipping_init', 'swa_sendu_shipping_method');

    function add_swa_sendu_shipping_method($methods)
    {
        $methods[] = 'swa_sendu_shipping_method';
        return $methods;
    }

    add_filter('woocommerce_shipping_methods', 'add_swa_sendu_shipping_method');
}
function swa_sendu_update_woocommerce_shipping_region_change()
{
    if (function_exists('is_checkout') && is_checkout()) {
        ?>
      <script>
        window.addEventListener('load', function(){
          var el = document.getElementById("billing_state_field");
          el.className += ' update_totals_on_change';
          var elShipping = document.getElementById("shipping_state_field");
          elShipping.className += ' update_totals_on_change';
        });
      </script>
      <?php
}
}
add_action('wp_print_footer_scripts', 'swa_sendu_update_woocommerce_shipping_region_change');

/*
 * Add action for save status
 */
add_action('save_post', array('swa_sendu_shipping_method', 'swa_sendu_create_work_order'), 10, 3);

add_action( 'admin_action_mark', 'swa_sendu_process_custom_status' ); // admin_action_{action name}

function misha_bulk_process_custom_status() {
    foreach( $_REQUEST['post'] as $order_id ) {
        $post = get_post( $order_id );
        $sh = new swa_sendu_shipping_method;
        $sh->swa_sendu_create_work_order($order_id, $post, null);
    }
}

/*
* Modify Address Fields
*/
add_filter('woocommerce_checkout_fields','swa_override_checkout_fields');

function swa_override_checkout_fields( $fields ) {
    unset($fields['billing']['billing_address_1']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['shipping']['shipping_address_1']);
    unset($fields['shipping']['shipping_address_2']);
    $fields['billing']['billing_address_1_street'] = [
        'type' => 'text',
        'label' => __('Street', 'swa_sendu'),
        'placeholder' => _x('Street', 'placeholder', 'swa_sendu'),
        'priority' => 51,
        'required' => true,
    ];
    $fields['billing']['billing_address_1_numeration'] = [
        'type' => 'number',
        'label' => __('Numeration', 'swa_sendu'),
        'placeholder' => _x('Numeration', 'placeholder', 'swa_sendu'),
        'priority' => 52,
        'required' => true,
    ];
    $fields['billing']['billing_address_1_complement'] = [
        'type' => 'text',
        'label' => __('Complement', 'swa_sendu'),
        'placeholder' => _x('Complement', 'placeholder', 'swa_sendu'),
        'priority' => 53,
        'required' => false,
    ];
    $fields['shipping']['shipping_address_1_street'] = [
        'type' => 'text',
        'label' => __('Street', 'swa_sendu'),
        'placeholder' => _x('Street', 'placeholder', 'swa_sendu'),
        'priority' => 51,
        'required' => true,
    ];
    $fields['shipping']['shipping_address_1_numeration'] = [
        'type' => 'number',
        'label' => __('Numeration', 'swa_sendu'),
        'placeholder' => _x('Numeration', 'placeholder', 'swa_sendu'),
        'priority' => 52,
        'required' => true,
    ];
    $fields['shipping']['shipping_address_1_complement'] = [
        'type' => 'text',
        'label' => __('Complement', 'swa_sendu'),
        'placeholder' => _x('Complement', 'placeholder', 'swa_sendu'),
        'priority' => 53,
        'required' => false,
    ];
    return $fields;
}

add_action( 'woocommerce_thankyou', 'swa_override_checkout_fields_thankyou');

function swa_override_checkout_fields_thankyou( $order_id ) {
    $billingStreet = get_post_meta($order_id, 'billing_address_1_street', true);
    $billingNumeration = get_post_meta($order_id, 'billing_address_1_numeration', true);
    $billingComplement = get_post_meta($order_id, 'billing_address_1_complement', true);
    $billing_address = $billingStreet . ' ' . $billingNumeration . ' ' . $billingComplement;

    $shippingStreet = get_post_meta($order_id, 'shipping_address_1_street', true);
    $shippingNumeration = get_post_meta($order_id, 'shipping_address_1_numeration', true);
    $shippingComplement = get_post_meta($order_id, 'shipping_address_1_complement', true);
    $shipping_address = $shippingStreet . ' ' . $shippingNumeration . ' ' . $shippingComplement;
    
    update_post_meta( $order_id, '_billing_address_1', $billing_address);
    if (strlen($shippingStreet) > 0 && strlen($shippingNumeration) > 0 ) {
        update_post_meta( $order_id, '_shipping_address_1', $shipping_address);
    } else {
        update_post_meta( $order_id, '_shipping_address_1', $billing_address);
    }

}

add_action('woocommerce_checkout_update_order_meta', 'swa_override_checkout_fields_order_meta');

function swa_override_checkout_fields_order_meta($order_id)
{
    if (!empty($_POST['billing_address_1_street'])) {
        update_post_meta($order_id, 'billing_address_1_street', sanitize_text_field($_POST['billing_address_1_street']));
    }
    if (!empty($_POST['billing_address_1_numeration'])) {
        update_post_meta($order_id, 'billing_address_1_numeration', sanitize_text_field($_POST['billing_address_1_numeration']));
    }
    if (!empty($_POST['billing_address_1_complement'])) {
        update_post_meta($order_id, 'billing_address_1_complement', sanitize_text_field($_POST['billing_address_1_complement']));
    }

    if (!empty($_POST['shipping_address_1_street'])) {
        update_post_meta($order_id, 'shipping_address_1_street', sanitize_text_field($_POST['shipping_address_1_street']));
    } else {
        update_post_meta($order_id, 'shipping_address_1_street', sanitize_text_field($_POST['billing_address_1_street']));
    }
    if (!empty($_POST['shipping_address_1_numeration'])) {
        update_post_meta($order_id, 'shipping_address_1_numeration', sanitize_text_field($_POST['shipping_address_1_numeration']));
    } else {
        update_post_meta($order_id, 'shipping_address_1_numeration', sanitize_text_field($_POST['billing_address_1_numeration']));
    }
    if (!empty($_POST['shipping_address_1_complement'])) {
        update_post_meta($order_id, 'shipping_address_1_complement', sanitize_text_field($_POST['shipping_address_1_complement']));
    } else {
        update_post_meta($order_id, 'shipping_address_1_complement', sanitize_text_field($_POST['billing_address_1_complement']));
    }

    if (!empty($_POST['billing_address_1_street']) && !empty($_POST['billing_address_1_numeration']) && empty($_POST['shipping_address_1_street']) && empty($_POST['shipping_address_1_numeration']) ) {
        $order = wc_get_order( $order_id );
        $order->set_billing_address_1( sanitize_text_field($_POST['billing_address_1_street']) . ' ' . sanitize_text_field($_POST['billing_address_1_numeration']) . ' ' . sanitize_text_field($_POST['billing_address_1_complement']) );
        $order->set_shipping_address_1( sanitize_text_field($_POST['billing_address_1_street']) . ' ' . sanitize_text_field($_POST['billing_address_1_numeration']) . ' ' . sanitize_text_field($_POST['billing_address_1_complement']) );
    } else if (!empty($_POST['billing_address_1_street']) && !empty($_POST['billing_address_1_numeration']) && !empty($_POST['shipping_address_1_street']) && !empty($_POST['shipping_address_1_numeration']) ) {
        $order = wc_get_order( $order_id );
        $order->set_billing_address_1( sanitize_text_field($_POST['billing_address_1_street']) . ' ' . sanitize_text_field($_POST['billing_address_1_numeration']) . ' ' . sanitize_text_field($_POST['billing_address_1_complement']) );
        $order->set_shipping_address_1( sanitize_text_field($_POST['shipping_address_1_street']) . ' ' . sanitize_text_field($_POST['shipping_address_1_numeration']) . ' ' . sanitize_text_field($_POST['shipping_address_1_complement']) );
    }
}
    
/*
 * Add RUT to Checkout
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'rut.php';

add_filter('woocommerce_checkout_fields', 'swa_addRutCheckout');

function swa_addRutCheckout($fields)
{
    $fields['billing']['billing_rut'] = [
        'type' => 'text',
        'label' => __('RUT', 'woocommerce'),
        'placeholder' => _x('RUT', 'placeholder', 'woocommerce'),
        'priority' => 25,
        'required' => true,
    ];
    return $fields;
}

add_action('woocommerce_checkout_process', 'swa_addRutCheckout_process');

function swa_addRutCheckout_process()
{
    if (!$_POST['billing_rut']) {
        wc_add_notice(__('Please enter the RUT field', 'swa_sendu'), 'error');
    } else {
        $validador = new swa_ChileRut();
        $rut = preg_replace('/[^0-9kK]/', '', $_POST['billing_rut']);
        if (!$validador->swa_rutCheck($rut)) {
            wc_add_notice(__('El RUT ingresado no es correcto.'), 'error');
        }
    }
}

add_action('woocommerce_checkout_update_order_meta', 'swa_addRutCheckout_order_meta');

function swa_addRutCheckout_order_meta($order_id)
{
    if (!empty($_POST['billing_rut'])) {
        update_post_meta($order_id, 'billing_rut', sanitize_text_field($_POST['billing_rut']));
    }
}
add_action('woocommerce_admin_order_data_after_billing_address', 'swa_addRutCheckout_admin_order_meta', 10, 1);

function swa_addRutCheckout_admin_order_meta($order)
{
    echo '<p><strong>' . __('RUT', 'woocommerce') . ':</strong> ' . get_post_meta($order->id, 'billing_rut', true) . '</p>';
}
add_filter( 'woocommerce_admin_order_actions', 'add_swa_getWorkOrder', 100, 2 );
function add_swa_getWorkOrder( $actions, $order ) {
    $work_order = get_post_meta($order->id, 'work_order', true);
    $sendu_options = get_option('woocommerce_swa_sendu_settings');

    // if ( intval($work_order) <= 0 ) {
    if ( $order->get_status() == str_replace('wc-','', $sendu_options['senduStatus']) && intval($work_order) <= 0 ) {

        // The key slug defined for your action button
        $action_slug = 'work_order';
         $status = $_GET['status'];
         $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
        //  $order_id = method_exists($the_order, 'get_id') ? $the_order->get_id() : $the_order->id;
        // Set the action button
        update_post_meta($order_id, 'button', serialize($order->get_status()));
        $actions[$action_slug] = array(
            'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=swa_getWorkOrder&order_id=' . $order_id ), 'woocommerce'),
            'name'      => __( 'Get ID of work order', 'swa_sendu' ),
            'action'    => $action_slug,
        );
    }
    return $actions;
}

add_action( 'wp_ajax_swa_getWorkOrder', 'swa_getWorkOrder', 1 );
function swa_getWorkOrder( $order_id ) {
    $sendu_options = get_option('woocommerce_swa_sendu_settings');
    if ($sendu_options['senduUrl']) {
        $sendu_base_url = $sendu_options['senduUrl'];
    } else {
        $sendu_base_url = 'https://app.sendu.cl';
    }
    if ($sendu_options['senduEmail']) {
        $sendu_auth_user = $sendu_options['senduEmail'];
    } else {
        $sendu_auth_user = 'hola@sendu.cl';
    }
    if ($sendu_options['senduToken']) {
        $sendu_auth_token = $sendu_options['senduToken'];
    } else {
        $sendu_auth_token = 'ivDLta1k6n-YpzRsRgPk';
    }

    $curlGet = curl_init();
    $urlPathGetOrder = '/api/work_orders.json?keywords=WC'. $_GET['order_id'];
    curl_setopt_array($curlGet, array(
        CURLOPT_URL => $sendu_base_url . $urlPathGetOrder,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "X-User-Email: " . $sendu_auth_user,
            "X-User-Token: " . $sendu_auth_token,
            "Content-Type: application/json",
        ),
    ));
    $workOrderGet = json_decode(curl_exec($curlGet));

    update_post_meta($_GET['order_id'], 'work_order', $workOrderGet[0]->id );
    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
	exit;
}

add_action( 'admin_head', 'add_custom_order_status_actions_button_css' );
function add_custom_order_status_actions_button_css() {
    $action_slug = "work_order"; // The key slug defined for your action button

    echo '<style>.wc-action-button-'.$action_slug.'::after { font-family: woocommerce !important; content: "\e019" !important; }</style>';
}

add_action( 'woocommerce_order_details_after_order_table', 'swa_sendu_view_order_detail_func', 5, 1); // Email notifications
function swa_sendu_view_order_detail_func( $order ){
    do_shortcode('[swa_sendu_tracking order_id="'. $order->get_id() . '"]' );
}

add_shortcode( 'swa_sendu_tracking', 'swa_sendu_tracking_func' );
function swa_sendu_tracking_func( $atts ) {
    // return "foo = {$atts['foo']}";   
    $order_id = $atts['order_id'];
    $sendu_options = get_option('woocommerce_swa_sendu_settings');
    if ($sendu_options['senduUrl']) {
        $sendu_base_url = $sendu_options['senduUrl'];
    } else {
        $sendu_base_url = 'https://app.sendu.cl';
    }
    if ($sendu_options['senduEmail']) {
        $sendu_auth_user = $sendu_options['senduEmail'];
    } else {
        $sendu_auth_user = 'hola@sendu.cl';
    }
    if ($sendu_options['senduToken']) {
        $sendu_auth_token = $sendu_options['senduToken'];
    } else {
        $sendu_auth_token = 'ivDLta1k6n-YpzRsRgPk';
    }
    $curlStates = curl_init();
    $urlPathGetStates = '/api/tracking_states.json';
    curl_setopt_array($curlStates, array(
        CURLOPT_URL => $sendu_base_url . $urlPathGetStates,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "X-User-Email: " . $sendu_auth_user,
            "X-User-Token: " . $sendu_auth_token,
            "Content-Type: application/json",
        ),
    ));
    $senduStatuses = json_decode(curl_exec($curlStates));
    foreach ($senduStatuses as $key => $status) {
        $statuses[$status->id] = array(
            "name" => $status->name,
            "description" => $status->description
        );
    }
    $curlGet = curl_init();
    $urlPathGetOrder = '/api/work_orders.json?keywords=WC'. $order_id;
    curl_setopt_array($curlGet, array(
        CURLOPT_URL => $sendu_base_url . $urlPathGetOrder,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "X-User-Email: " . $sendu_auth_user,
            "X-User-Token: " . $sendu_auth_token,
            "Content-Type: application/json",
        ),
    ));
    $orderTracking = json_decode(curl_exec($curlGet));
    foreach ($orderTracking as $key => $orderSendu) {
        $work_order = get_post_meta($order_id, 'work_order', true);
        if (intval($work_order) == intval($orderSendu->id) && count($orderSendu->tracking) > 0) {

            echo '<h2>';
            _e('Tracking Info', 'swa_sendu');
            echo '</h2>';
            
            $htmlTrackingStart = '<section class="root">';
            $htmlTrackingStart .= '<div class="swa_sendu-order-track">';
            echo $htmlTrackingStart;
            foreach ($orderSendu->tracking as $keyOrder => $tracking) {
                $timeEvent = new DateTime($tracking->event_date);
                $tracking2Print[] = array(
                    "name" => $statuses[$tracking->work_order_state_id]['name'],
                    "description" => $statuses[$tracking->work_order_state_id]['description'],
                    "time" => $timeEvent->format('d-m-Y H:i')
                );
                $htmlTrackingInfo = '<div class="swa_sendu-order-track-step">';
                $htmlTrackingInfo .= '<div class="swa_sendu-order-track-status">';
                $htmlTrackingInfo .= '<span class="swa_sendu-order-track-status-dot"></span>';
                $htmlTrackingInfo .= '<span class="swa_sendu-order-track-status-line"></span>';
                $htmlTrackingInfo .= '</div>';
                $htmlTrackingInfo .= '<div class="swa_sendu-order-track-text">';
                $htmlTrackingInfo .= '<p class="swa_sendu-order-track-text-stat">' . $statuses[$tracking->work_order_state_id]['name'] . '</p>';
                $htmlTrackingInfo .= '<div><span class="swa_sendu-order-track-text-sub">' . $statuses[$tracking->work_order_state_id]['description'] . '</span></div>';
                $htmlTrackingInfo .= '<div><span class="swa_sendu-order-track-text-sub swa_sendu-order-track-text-date">' . $timeEvent->format('d-m-Y H:i') . '</span></div>';
                $htmlTrackingInfo .= '</div>';
                $htmlTrackingInfo .= '</div>';
                echo $htmlTrackingInfo;
            }
            $htmlTrackingEnd = '</div>';
            $htmlTrackingEnd = '</section>';
            echo $htmlTrackingEnd;
        }
    }
}