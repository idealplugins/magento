<?php
// here are the table creation for this module e.g.:
$this->startSetup();
$this->run("
	CREATE TABLE IF NOT EXISTS `targetpay` (
	`order_id` VARCHAR(64) DEFAULT NULL,
    `method` VARCHAR(6) DEFAULT NULL,
	`targetpay_txid` VARCHAR(64) DEFAULT NULL,
    `targetpay_response` VARCHAR(128) DEFAULT NULL,
    `paid` DATETIME DEFAULT NULL,
	PRIMARY KEY (`order_id`));
	");

$this->endSetup();
