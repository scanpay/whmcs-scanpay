<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use Illuminate\Database\Capsule\Manager as Capsule;

function spInvoiceExists($pdo, $invoiceid)
{
    $stmt = $pdo->prepare("SELECT `id` FROM `tblinvoices` WHERE `id` = :id");
    $stmt->bindValue(':id', $invoiceid, \PDO::PARAM_INT);
    $ret = $stmt->execute();
    if ($ret == false) {
        respond('failed to check invoice existance');
    }
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row) {
        logtransaction('scanpay', $_REQUEST, 'Invoice ID Not Found');
        return false;
    }
    return true;
}

function spTrnIdExists($pdo, $trnid)
{
    $stmt = $pdo->prepare("SELECT `id` FROM `tblaccounts` WHERE `transid` = :trnid");
    $stmt->bindValue(':trnid', $trnid, \PDO::PARAM_INT);
    $ret = $stmt->execute();
    if ($ret == false) {
        respond('failed to check transaction existance');
    }
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    return true;
}

function respond($err)
{
    if (is_null($err)) {
        echo '{"success":true}';
    } else {
        logActivity('Scanpay ping err:' . $err);
        echo json_encode(['error' => $err]);
    }
    exit();
}

$gateway = getGatewayVariables('scanpay');
if (!$gateway['type']) {
    die("Module Not Activated");
}

require_once(dirname(__DIR__) . '/scanpay/libscanpay.php');
$options = [
    'debug' => false,
];
$scanpay = new Scanpay\Scanpay($gateway['apikey'], $options);
try {
    $ping = $scanpay->handlePing();
} catch (\Exception $e) {
    respond($e->getMessage());
}
$shopid = $ping['shopid'];

require_once(dirname(__DIR__) . '/scanpay/shopseqdb.php');
$shopSeqDB = new \WHMCS\Module\Gateway\Scanpay\ShopSeqDB();
$localSeqObj = $shopSeqDB->load($shopid);
if (!$localSeqObj) {
    respond('unable to load scanpay sequence number');
}
$localSeq = $localSeqObj['seq'];
if ($localSeq >= $ping['seq']) {
    $shopSeqDB->updateMtime($shopid);
    respond(null);
}
$pdo = Capsule::connection()->getPdo();
while (1) {
    try {
        $res = $scanpay->seq($localSeq);
    } catch (\Exception $e) {
        respond('scanpay client exception: ' . $e->getMessage());
    }
    if (count($res['changes']) == 0) {
        break;
    }
    foreach ($res['changes'] as $c) {
        $id = 'scanpay:' . $shopid . ':' . $c['id'];
        if (!spInvoiceExists($pdo, $c['orderid']) || spTrnIdExists($pdo, $id)) {
            continue;
        }
        $auth = explode(' ', $c['totals']['authorized'])[0];
        addInvoicePayment($c['orderid'], $id, (float)$auth, 0, 'scanpay');
        logTransaction('scanpay', $c, 'Succesful');
    }
    $r = $shopSeqDB->save($shopid, $res['seq']);
    if (!$r) {
        if ($r == false) {
            respond('unable to save scanpay sequence number');
        }
        break;
    }
    $localSeq = $res['seq'];
}
respond(null);
