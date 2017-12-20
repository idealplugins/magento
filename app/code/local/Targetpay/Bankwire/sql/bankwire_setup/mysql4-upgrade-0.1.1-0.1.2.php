<?php
// here are the table creation for this module e.g.:
$this->startSetup();

$this->run("ALTER TABLE  `targetpay` ADD  `more` VARCHAR( 255 ) NOT NULL AFTER  `targetpay_txid`");

$this->endSetup();
