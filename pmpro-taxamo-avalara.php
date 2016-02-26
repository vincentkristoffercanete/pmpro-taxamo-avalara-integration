<?php
/*
Plugin Name: PMPro Taxamo and Avalara Integration
Description: Adding taxes feature for EU and Canada using Taxamo and Avalara API for Paid Memberships Pro. Plugin Requirement: Paid Membership pro and Register Helper Add-on https://github.com/strangerstudios/pmpro-register-helper
Version: .1
Author: Vincent Kristoffer Cañete
Author URI: 
*/


	require_once 'includes/geoiploc.php';
	require_once 'includes/Taxamo.php';

	//Pre-calculating tax
	function pre_calculate_tax_with_taxamo($tax, $values, $order){ 
		$ip_address = get_ip_address();
		//$ip_address = "178.32.248.125";
		//$ip_address = '168.144.251.106';
		
		$real_country_code = getCountryFromIP($ip_address);
		$tax_number = $_REQUEST['tax_number'];

		$taxamo = new Taxamo(new APIClient('priv_test_KSHc9YL1SUN_9B9XSfNSRPVPnEKdJo-O1x27irg-Rtw', 'https://api.taxamo.com'));
		$geoip = $taxamo->locateGivenIP($ip_address);

		if( $real_country_code == $order->billing->country || $order->gateway == 'free' ){
			if($geoip->country->tax_supported){

				if($tax_number){
					$validation = $taxamo->validateTaxNumber($real_country_code,$tax_number);
					if(!$validation->buyer_tax_number_valid){
						echo 'TAX Number '. $tax_number .' est non vérifié';
						//$pmpro_msg = __("VAT Number {$tax_number} is not verified", 'pmpro');
		       	 		//$pmpro_msgt = "pmpro_error";
						die();
					}
				}

				$transaction_line1 = new Input_transaction_line();
				$transaction_line1->amount = $values['price'];
				$transaction_line1->custom_id = 'line1';
				$transaction_line1->product_type = 'default';

				$transaction = new Input_transaction();
				$transaction->currency_code = 'CAD';
				
				$transaction->buyer_ip = $ip_address;
				$transaction->billing_country_code = $order->billing->country;
				//$transaction->force_country_code = 'IE';

				$transaction->buyer_name = $order->billing->name;
				$transaction->buyer_tax_number = $tax_number;

				$transaction->transaction_lines = array($transaction_line1);

				$resp = $taxamo->calculateTax(array('transaction' => $transaction));
				$tax = number_format($resp->transaction->tax_amount, 2);

				//Uncomment this code if you want store data to Taxamo transaction.
				/*if(!isset($_SESSION['tax_sesion'])){
					$_SESSION['tax_sesion'] = 'J8xoSpdsT8Du0EzTNNTzFMsrXZq1Eyv5';
					$resp = $taxamo->createTransaction(array('transaction' => $transaction));
					$taxamo->confirmTransaction($resp->transaction->key, array('transaction' => $transaction)); 
				}*/

			}else{

				if( $real_country_code == 'CA'){
					if( $real_country_code == $order->billing->country ){

						$tax_rates_api = "bditm5zUTC9ChL55eMb7v1kKWEswo4qb3SvBndNQ%2Bl0aksHnVllOjzNyTCD%2F2zmgaYWqFd8PYNfmIp82NAIfCg%3D%3D";
						$taxes = get_non_eu_tax_rates($geoip->country_code, $order->billing->zip , $tax_rates_api);
						$tax_rate = $taxes['totalRate'];
						
						$tax = number_format($values['price'] * ( $taxes['totalRate'] / 100),2) ;

					}
				}
			}
		}else{
			echo "Votre adresse IP et l'adresse de facturation ne sont pas correspondent.";
			die();
		}
		return $tax;
	}
	add_filter("pmpro_tax", "pre_calculate_tax_with_taxamo", 10, 3);

	function customtax_pmpro_after_checkout(){
		if(isset($_SESSION['tax_sesion']))
			unset($_SESSION['tax_sesion']);
	}
	add_action("pmpro_after_checkout", "customtax_pmpro_after_checkout");

	function get_non_eu_tax_rates($country_code, $postal, $api) {
		$postal = str_replace(' ', '', $postal);
		$url = "https://taxrates.api.avalara.com/postal?country={$country_code}&postal={$postal}&apikey={$api}";
		$json = file_get_contents($url);
		$json_data = json_decode($json, true);
		return $json_data;
	}

	function get_ip_address() {
	    // check for shared internet/ISP IP
	    if (!empty($_SERVER['HTTP_CLIENT_IP']) && validate_ip($_SERVER['HTTP_CLIENT_IP'])) {
	        return $_SERVER['HTTP_CLIENT_IP'];
	    }

	    // check for IPs passing through proxies
	    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	        // check if multiple ips exist in var
	        if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false) {
	            $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
	            foreach ($iplist as $ip) {
	                if (validate_ip($ip))
	                    return $ip;
	            }
	        } else {
	            if (validate_ip($_SERVER['HTTP_X_FORWARDED_FOR']))
	                return $_SERVER['HTTP_X_FORWARDED_FOR'];
	        }
	    }
	    if (!empty($_SERVER['HTTP_X_FORWARDED']) && validate_ip($_SERVER['HTTP_X_FORWARDED']))
	        return $_SERVER['HTTP_X_FORWARDED'];
	    if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && validate_ip($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
	        return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
	    if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && validate_ip($_SERVER['HTTP_FORWARDED_FOR']))
	        return $_SERVER['HTTP_FORWARDED_FOR'];
	    if (!empty($_SERVER['HTTP_FORWARDED']) && validate_ip($_SERVER['HTTP_FORWARDED']))
	        return $_SERVER['HTTP_FORWARDED'];

	    // return unreliable ip since all else failed
	    return $_SERVER['REMOTE_ADDR'];
	}

	//we have to put everything in a function called on init, so we are sure Register Helper is loaded
	function my_pmprorh_init(){
	  //don't break if Register Helper is not loaded
	  if(!function_exists("pmprorh_add_registration_field")){
	    return false;
	  }
	  $fields = array();
	  $fields[] = new PMProRH_Field("tax_number", "text", array("label"=>"Numéro de taxe (optionnel)", "size"=>40, "profile" => true, "class"=>"tax_number", "required"=>false, 'levels' => array(2,3) ));

	  //add the fields into a new checkout_boxes are of the checkout page
	  foreach($fields as $field){
	    pmprorh_add_registration_field("after_billing_fields", $field);
	  }
	}
	add_action("init", "my_pmprorh_init");

	function AjaxScript(){ 
		global $pmpro_level;
	?>

	<script type="text/javascript">
	jQuery(document).ready(function($){
		$.ajax({
            url: "<?php echo plugins_url( 'includes/ajax-taxes.php', __FILE__ ) ?>",
            method: "POST",
            data: { 
                country_code : $('select[name="bcountry"]').val(), 
                tax_number : $('#tax_number').val(),
                price: "<?php echo $pmpro_level->initial_payment; ?>",
                postal_code: $('#bzipcode').val(),
            },
            success: function (dataCheck) {
                $('#taxes').remove();
                $('#pmpro_billing_address_fields').append(dataCheck);
            }
        });
        
	   $('form.pmpro_form select[name="bcountry"]').change(function(){
	        $.ajax({
	            url: "<?php echo plugins_url( 'includes/ajax-taxes.php', __FILE__ ) ?>",
	            method: "POST",
	            data: { 
	                country_code : $('select[name="bcountry"]').val(), 
	                tax_number : $('#tax_number').val(),
	                price: "<?php echo $pmpro_level->initial_payment; ?>",
	                postal_code: $('#bzipcode').val(),
	            },
	            success: function (dataCheck) {
	                $('#taxes').remove();
	                $('#pmpro_billing_address_fields').append(dataCheck);
	            }
	        });
	    });
	   $('#bzipcode').keyup(function(){
	        $.ajax({
	            url: "<?php echo plugins_url( 'includes/ajax-taxes.php', __FILE__ ) ?>",
	            method: "POST",
	            data: { 
	                country_code : $('select[name="bcountry"]').val(), 
	                tax_number : $('#tax_number').val(),
	                price: "<?php echo $pmpro_level->initial_payment; ?>",
	                postal_code: $('#bzipcode').val(),
	            },
	            success: function (dataCheck) {
	                $('#taxes').remove();
	                $('#pmpro_billing_address_fields').append(dataCheck);
	            }
	        });
	    });

	});
	</script>

<?php }
	add_action('pmpro_checkout_after_billing_fields','AjaxScript');
?>

