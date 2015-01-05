<?php

/**

	iDEALplugins.nl
    TargetPay plugin v0.0.1 for Magento 1.4+

    (C) Copyright Yellow Melon 2013

 	@file 		Sofort country selector
	@author		Yellow Melon B.V. / www.idealplugins.nl

 */

require_once (BP . DS . 'app' . DS . 'code' . DS . 'local' . DS. "Targetpay" . DS . "targetpay.class.php");

class Targetpay_Sofort_Block_Payment_Form_Sofort extends Mage_Payment_Block_Form_Cc
	{
    protected $_tp_method = "DEB";

    protected function _construct() {
        parent::_construct();
		$this->setTemplate('sofort/payment/form/sofort.phtml');
		}

	}
