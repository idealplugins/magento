<?php

/**
 *
 *  iDEALplugins.nl
 *  TargetPay plugin v2.1 for Magento 1.4+
 *
 *  (C) Copyright Yellow Melon 2014
 *
 *  @file     iDEAL Model
 *  @author   Yellow Melon B.V. / www.idealplugins.nl
 *  
 *  v2.1  Added pay by invoice
 */
require_once (BP . DS . 'app' . DS . 'code' . DS . 'local' . DS . "Targetpay" . DS . "targetpay.class.php");
require_once (BP . DS . 'app' . DS . 'code' . DS . 'local' . DS . "Targetpay" . DS . "Base_Targetpay_Controller.php");

class Targetpay_Bankwire_BankwireController extends Base_Targetpay_Controller
{

    protected $_code = 'bankwire';

    protected $_tp_method = 'BW';

    private $urlKey;

    public function getUrlKey()
    {
        $this->urlKey = hash('sha256', md5(md5(Mage::getSingleton('customer/session')->getCustomer()->getId()) . $this->_code));
    }
    
    // Handle redirect that starts TargetPay payment
    public function redirectAction()
    {
        $bankwireModel = Mage::getSingleton('bankwire/bankwire');
        $orderId = $bankwireModel->setupPayment();
        $urlEncode = base64_encode(openssl_encrypt($orderId, "AES-256-CBC", $this->getUrlKey()));
        
        $this->_redirectUrl(Mage::getBaseUrl() . 'bankwire/bankwire/thankyou/code/' . $urlEncode);
    }

    /**
     * Output the Payment Instruction for the order received page.
     */
    public function thankyouAction()
    {
        $urlEncode = $this->getRequest()->get('code');
        $orderId = openssl_decrypt(base64_decode($urlEncode), "AES-256-CBC", $this->getUrlKey());
        
        if ($orderId) {
            $order = Mage::getModel('sales/order')->load($orderId);
            $readConnection = Mage::getSingleton('core/resource')->getConnection('core_read');
            $query = 'select `more` from `targetpay` WHERE order_id = ' . $readConnection->quote($order->getRealOrderId()) . ' LIMIT 1';
            $dataTargetpay = $readConnection->fetchAll($query);
            
            if (empty($dataTargetpay[0]['more'])) {
                $this->_redirectUrl(Mage::getBaseUrl());
            }
            
            $email = $order->customer_email;
            
            $string = strstr($email, '@', true);
            $n = (preg_replace('/[a-zA-Z0-9-_]{1,1}/', '*', $string));
            
            $order->customer_email = substr($email, 0, 1) . $n . '@***' . strstr(strstr($email, '@'), '.');
            
            Mage::getSingleton('core/session')->setSomeSessionVar([
                'order' => $order, 
                'data' => $dataTargetpay[0]['more']]); // In the Controller
            
            $this->loadLayout();
            $this->renderLayout();
        } else {
            $this->_redirectUrl(Mage::getBaseUrl());
        }
    }
}

?>
