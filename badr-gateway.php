<?php
/**
 * @package   badr gatway
 */
 /**
 * Plugin Name: badr gateway
 * Plugin URI: https://github.com/badr-r
 * Description: A plugin for woocommerce
 * Author URI: https://github.com/badr-r
 * Version: 1.0
 */

 if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'plugins_loaded', 'init_your_gateway_class' );

function init_your_gateway_class() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_badr_Gateway extends WC_Payment_Gateway {
      public function __construct(){
        $this->id = 'badr';
        // $this->icon =
        $this->has_fields = false;
        $this->method_title = 'badr-gatway';
        // $this->order_button_text = __('Pay with bitcoin', 'pay with badr-bitcoin');
        $this->method_description = 'bitcoin payment gateway';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option( 'title' );

        add_option('blockonomics_orders', array());

        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action('woocommerce_api_badr_webhook', array($this, 'webhook'));
}


function process_payment( $order_id ) {

    $order = wc_get_order( $order_id );
    return array(
        'result' => 'success',
        'redirect' => $order->get_checkout_payment_url( true )
    );

}

public function receipt_page( $order_id ) {
  $blockonomics_orders = get_option('blockonomics_orders');
  $priceurl = "https://www.blockonomics.co/api/price?currency=USD";
  $pricejson = file_get_contents($priceurl);
  $price = json_decode($pricejson, true);


  $order = wc_get_order( $order_id );
  $order->update_status('on-hold', __( 'Awaiting bitcoin payment', 'woocommerce' ));

  $api_key = 'put your Blockonomics api here';
  $url = 'https://www.blockonomics.co/api/new_address';

  $options = array(
    'http' => array(
        'header'  => 'Authorization: Bearer '.$api_key,
        'method'  => 'POST',
        'content' => '',
        'ignore_errors' => true
    )
);

$context = stream_context_create($options);
$contents = file_get_contents($url, false, $context);
$object = json_decode($contents);
$sat = 1.0e8*substr($order->get_total()/$price["price"], 0, 10);

$order_save = array(
    'value'              => $order->get_total(),
    'satoshi'            => $sat,
    'currency'           => get_woocommerce_currency(),
    'order_id'           => $order_id,
    'status'             => -1,
    'timestamp'          => time(),
    'txid'               => ''
);

$blockonomics_orders[$object->address] = $order_save;
update_option('blockonomics_orders', $blockonomics_orders);

  echo '<style>
* {
  margin: 0;
  padding: 0;
}

.flex-box{
  display: flex;
  justify-content: center;
  align-items: center;
  height: 60vh;
}

.box {
  border-style:solid;
  border-width: thin;
  width: 50vh;
  height: 100%;
  background-color: #333533;
  -webkit-box-shadow: 6px 7px 23px -8px rgba(0,0,0,0.52);
  -moz-box-shadow: 6px 7px 23px -8px rgba(0,0,0,0.52);
  box-shadow: 6px 7px 23px -8px rgba(0,0,0,0.52);
}

.qrcode{
  display: flex;
  justify-content: center;
  background-color: #e8eddf;
  height: 50%;
}
.container{
  text-align: center;
  padding-top: 20px
}

.data{
  text-align: center;
  position: relative;
  padding-top: 15%;
  color: white;
}
</style>';


echo '<div class="flex-box">
<div class="box">
  <div class="qrcode">
  <div class="container">
  <img src="https://apirone.com/api/v1/qr?message='. $object->address . '&format=svg" style="width: 250px;" alt="">
  </div>
  </div>
  <div class="data">
  <p>Send the exact amount of bitcoin to the address below to confirm your payment</p>
  <p>Amount: '. substr($order->get_total()/$price["price"], 0, 10) . ' BTC</p>
  <p> Address: '. $object->address . '</p>
  </div>
</div>
</div>';
}




public function webhook() {

  $orders = get_option('blockonomics_orders');

  // $secret = '7qN4mClhhgGojYOKQFvSbCDt';
  // $txid = $_GET['txid'];
  $value = $_GET['value'];
  $status = $_GET['status'];
  $addr = $_GET['addr'];

  //uncommwnt if you want to add secret key with the callback
// if ($_GET['secret'] != $secret) {
//     return;
// }

if ($status != 2) {
//Only accept confirmed transactions
return;
}

$order = $orders[$addr];
$wc_order = new WC_Order($order['order_id']);


if($order){
  $req_value = $order['satoshi'];

  if($status == 2 && $value >= $req_value){
    $wc_order->payment_complete();
    die();
  }else{
    die();
  }
}

 }

    }

    function badr_add_gateway_class( $methods ) {
    $methods[] = 'WC_badr_Gateway';
    return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'badr_add_gateway_class' );
}
