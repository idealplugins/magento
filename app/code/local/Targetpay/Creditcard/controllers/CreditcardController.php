<?php

/**
 *
 *	iDEALplugins.nl
 *  TargetPay plugin v2.2 for Magento 1.4+
 *
 *  (C) Copyright Yellow Melon 2014
 *
 * 	@file 		iDEAL Model
 *	@author		Yellow Melon B.V. / www.idealplugins.nl
 *  
 *  v2.1	Added pay by invoice
 *  v2.2 	Added creditcards 
 */
require_once (BP . DS . 'app' . DS . 'code' . DS . 'local' . DS . "Targetpay" . DS . "targetpay.class.php");
require_once (BP . DS . 'app' . DS . 'code' . DS . 'local' . DS . "Targetpay" . DS . "Base_Targetpay_Controller.php");

class Targetpay_Creditcard_CreditcardController extends Base_Targetpay_Controller
{

    protected $_code = 'creditcard';

    protected $_tp_method = 'CC';
}

?>
