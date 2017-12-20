<?php

/**
 *
 *	iDEALplugins.nl
 *  TargetPay plugin v2.1 for Magento 1.4+
 *
 *  (C) Copyright Yellow Melon 2014
 *
 * 	@file 		iDEAL Model
 *	@author		Yellow Melon B.V. / www.idealplugins.nl
 *  
 *  v2.1	Added pay by invoice
 */
require_once (BP . DS . 'app' . DS . 'code' . DS . 'local' . DS . "Targetpay" . DS . "targetpay.class.php");
require_once (BP . DS . 'app' . DS . 'code' . DS . 'local' . DS . "Targetpay" . DS . "Base_Targetpay_Model.php");

class Targetpay_Paypaltm_Model_Paypaltm extends Base_Targetpay_Model
{

    protected $_code = 'paypaltm';

    protected $_isGateway = true;

    protected $_canAuthorize = true;

    protected $_canCapture = true;

    protected $_canCapturePartial = false;

    protected $_canRefund = true;

    protected $_canVoid = true;

    protected $_canUseInternal = true;

    protected $_canUseCheckout = true;

    protected $_canUseForMultishipping = true;

    protected $_canSaveCc = false;

    protected $_tp_method = "PYP";

    /**
     * Prepare redirect that starts TargetPay payment
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('paypaltm/paypaltm/redirect', array(
            '_secure' => true,
            'bank_id' => $_POST["payment"]["bank_id"]
        ));
    }
}

?>
