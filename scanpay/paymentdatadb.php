<?php
namespace WHMCS\Module\Gateway\Scanpay;
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
use Illuminate\Database\Capsule\Manager as Capsule;

class PaymentDataDB
{
    protected $tablename = 'scanpay_paymentdata';

    public function __construct()
    {
        $this->pdo = Capsule::connection()->getPdo();
        $result = $this->pdo->query("SHOW TABLES LIKE '$this->tablename'");
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            $sql = "CREATE TABLE IF NOT EXISTS `$this->tablename` (
                invoiceid BIGINT UNSIGNED NOT NULL PRIMARY KEY UNIQUE COMMENT 'Invoice Id',
                querykey VARCHAR(255) NOT NULL COMMENT 'Query Validation Key',
                apikey VARCHAR(255) NOT NULL COMMENT 'Scanpay API-key',
                data MEDIUMBLOB NOT NULL COMMENT 'Payment Data'
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
            $this->pdo->exec($sql);
        }
    }

    public function insert($invoiceid, $key, $apikey, $data)
    {
        if (!is_int($invoiceid) || $invoiceid <= 0) {
            logActivity('Scanpay: ShopId argument is not an unsigned int');
            return false;
        }
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO `$this->tablename`" .
            'SET `invoiceid` = :invoiceid, `querykey` = :querykey, `apikey` = :apikey, `data` = :data ');
        $stmt->bindValue(':invoiceid', $invoiceid, \PDO::PARAM_INT);
        $stmt->bindValue(':querykey', $key, \PDO::PARAM_STR);
        $stmt->bindValue(':apikey', $apikey, \PDO::PARAM_STR);
        $stmt->bindValue(':data', $data, \PDO::PARAM_LOB);
        $ret = $stmt->execute();
        return (int)!!$ret;
    }

    public function load($invoiceid)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `$this->tablename` WHERE `invoiceid` = :invoiceid");// AND `querykey` = :querykey");
        $stmt->bindValue(':invoiceid', $invoiceid, \PDO::PARAM_INT);
        $ret = $stmt->execute();
        if ($ret == false) {
            logActivity('Scanpay: Failed to load payment data');
            return false;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            logActivity('Scanpay: Failed to fetch payment data');
            return NULL;
        }
        return [
            'invoiceid' => (int)$row['invoiceid'],
            'querykey' => $row['querykey'],
            'apikey' => $row['apikey'],
            'data' => $row['data'],
        ];
    }

}
