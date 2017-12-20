<?php

/**
 
 iDEALplugins.nl
 TargetPay plugin v0.0.1 for Magento 1.4+
 
 (C) Copyright Yellow Melon 2013
 
 @file 		iDEAL Bank selector
 @author		Yellow Melon B.V. / www.idealplugins.nl
 
 */
require_once (BP . DS . 'app' . DS . 'code' . DS . 'local' . DS . "Targetpay" . DS . "targetpay.class.php");

class Targetpay_Ideal_Block_Payment_Form_Ideal extends Mage_Payment_Block_Form_Cc
{

    protected $_tp_method = "IDE";

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ideal/payment/form/ideal.phtml');
    }

    public function getBanks()
    {
        $language = (Mage::app()->getLocale()->getLocaleCode() == 'nl_NL') ? "nl" : "en";
        $targetPay = new TargetPayCore($this->_tp_method, Mage::getStoreConfig('payment/ideal/rtlo'), "f8ca4794a1792886bb88060ca0685c1e", $language, false);
        return $targetPay->getBankList();
    }
}
