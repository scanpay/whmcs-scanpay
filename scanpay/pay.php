<?php
require __DIR__ . '/../../../init.php';

require_once(__DIR__ . '/paymentdatadb.php');
$paymentdatadb = new \WHMCS\Module\Gateway\Scanpay\PaymentDataDB();
$pd = $paymentdatadb->load((int)$_GET['invoiceid']);
if (!$pd) {
    echo 'invoice not found';
    return;
}
if ($_GET['key'] != $pd['querykey']) {
    echo 'invoice key not matching';
    return;
}
if (!is_array($data = @json_decode($pd['data'], true))) {
    echo "internal server error";
    return;
}

$options = [
    'debug' => false,
];
require_once(__DIR__ . '/libscanpay.php');
$scanpay = new Scanpay\Scanpay($pd['apikey']);

try {
    $url = $scanpay->newURL($data, $options);
    $url = str_replace('"', '', $url);
    header('Location: ' . $url);
} catch (\Exception $e) {
    echo 'Something went wrong: ' . $e;
    logActivity('scanpay newURL error:' . $e);
}
