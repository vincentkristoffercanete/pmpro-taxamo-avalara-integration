<?php
error_reporting(0);
require_once('Taxamo.php');

$real_country_code = $_REQUEST['country_code'];
$tax_number = $_REQUEST['tax_number'];
$price = $_REQUEST['price'];
$postal_code = $_REQUEST['postal_code'];

$messages = array();

$taxamo = new Taxamo(new APIClient('priv_test_KSHc9YL1SUN_9B9XSfNSRPVPnEKdJo-O1x27irg-Rtw', 'https://api.taxamo.com'));

if($tax_number){
	$validation = $taxamo->validateTaxNumber($real_country_code, $tax_number);
	if(!$validation->buyer_tax_number_valid){
		$messages[] = 'The Tax Number '. $tax_number .' is not verified';
	}
}

$transaction_line1 = new Input_transaction_line();
$transaction_line1->amount = $price;
$transaction_line1->custom_id = 'line1';
$transaction_line1->product_type = 'default';

$transaction = new Input_transaction();
$transaction->currency_code = 'CAD';
$transaction->buyer_ip = $_SERVER['REMOTE_ADDR'];
$transaction->billing_country_code = $real_country_code;
$transaction->force_country_code = $real_country_code;
$transaction->buyer_tax_number = $tax_number;
$transaction->transaction_lines = array($transaction_line1);

$resp = $taxamo->calculateTax(array('transaction' => $transaction));
$tax_rate = $resp->transaction->transaction_lines[0]->tax_rate;
$tax_amount = number_format($resp->transaction->tax_amount, 2);
$ttotal = $price + $tax_amount;

if(empty($tax_rate)){
	$tax_rates_api = "bditm5zUTC9ChL55eMb7v1kKWEswo4qb3SvBndNQ%2Bl0aksHnVllOjzNyTCD%2F2zmgaYWqFd8PYNfmIp82NAIfCg%3D%3D";
	$taxes = get_non_eu_tax_rates($real_country_code, $postal_code , $tax_rates_api);
	$tax_rate = $taxes['totalRate'];
	$tax = number_format($price * ( $taxes['totalRate'] / 100), 2 );
	$total = $price + $tax;

	$messages[] = "<div id='taxes'> <strong>Montant facture</strong> <br> Sous-total: &dollar;{$price} <br> Taxes en vigueur: &dollar;{$tax} <br> Total: &dollar;{$total} </div>";
}else{
	//$messages[] = "The tax rate for {$real_country_code} is {$tax_rate}%";
	$messages[] = "<div id='taxes'> <strong>Montant facture</strong> <br> Sous-total: &dollar;{$price} <br> Taxes en vigueur: &dollar;{$tax_amount} <br> Total: &dollar;{$ttotal} </div>";
}
 
if($messages){
	foreach ($messages as $message) {
		echo $message. "\n";
	}
}

function get_non_eu_tax_rates($country_code, $postal, $api) {
	$postal = str_replace(' ', '', $postal);
	$url = "https://taxrates.api.avalara.com/postal?country={$country_code}&postal={$postal}&apikey={$api}";
	$json = file_get_contents($url);
	$json_data = json_decode($json, true);
	return $json_data;
}