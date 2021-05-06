<?php
namespace WHMCS\Module\Gateway\Scanpay;
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
use Illuminate\Database\Capsule\Manager as Capsule;

class ShopSeqDB
{
    protected $tablename = 'scanpay_seq';

    public function __construct()
    {
        $this->pdo = Capsule::connection()->getPdo();
        $result = $this->pdo->query("SHOW TABLES LIKE '$this->tablename'");
        $row = $result->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            $sql = "CREATE TABLE IF NOT EXISTS `$this->tablename` (
                shopid BIGINT UNSIGNED NOT NULL PRIMARY KEY UNIQUE COMMENT 'Shop Id',
                seq BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Scanpay Events Sequence Number',
                mtime BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Modification Time'
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
            $this->pdo->exec($sql);
        }
    }

    public function updateMtime($shopid)
    {
        $stmt = $this->pdo->prepare("UPDATE `$this->tablename` SET `mtime` = :mtime " .
            'WHERE `shopid` = :shopid');
        $stmt->bindValue(':mtime', time(), \PDO::PARAM_INT);
        $stmt->bindValue(':shopid', $shopid, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function insert($shopid)
    {
        if (!is_int($shopid) || $shopid <= 0) {
            logActivity('Scanpay: ShopId argument is not an unsigned int');
            return false;
        }
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO `$this->tablename`" .
            'SET `shopid` = :shopid, `seq` = 0, `mtime` = 0 ');
        $stmt->bindValue(':shopid', $shopid, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function save($shopid, $seq)
    {
        if (!is_int($shopid) || $shopid <= 0) {
            logActivity('Scanpay: ShopId argument is not an unsigned int');
            return false;
        }

        if (!is_int($seq) || $seq < 0) {
            logActivity('Scanpay: Seq argument is not an unsigned int');
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE `$this->tablename` SET `seq` = :seq, `mtime` = :mtime " .
            'WHERE `shopid` = :shopid AND `seq` < :seqcpy');
        $stmt->bindValue(':seq', $seq, \PDO::PARAM_INT);
        $stmt->bindValue(':seqcpy', $seq, \PDO::PARAM_INT);
        $stmt->bindValue(':mtime', time(), \PDO::PARAM_INT);
        $stmt->bindValue(':shopid', $shopid, \PDO::PARAM_INT);
        $ret = $stmt->execute();
        if ($ret == false) {
            logActivity('Scanpay: Failed saving seq to database');
            return false;
        }
        if ($ret === 0) {
            $this->updateMtime($shopid);
        }
        return (int)!!$ret;
    }

    public function load($shopid, $inserted = false)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `$this->tablename` WHERE `shopid` = :shopid");
        $stmt->bindValue(':shopid', $shopid, \PDO::PARAM_INT);
        $ret = $stmt->execute();
        if ($ret == false) {
            logActivity('Scanpay: Failed loading seq from database');
            return false;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            if ($inserted) { return false; }
            $this->insert($shopid);
            return $this->load($shopid, true);
        }
        return [ 'shopid' => (int)$row['shopid'], 'seq' => (int)$row['seq'], 'mtime' => (int)$row['mtime'] ];
    }

}
