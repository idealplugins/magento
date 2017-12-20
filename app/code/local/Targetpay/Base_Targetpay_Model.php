<?php

class Base_Targetpay_Model extends Mage_Payment_Model_Method_Abstract
{
    public $rtlo;
    
    public function __construct()
    {
        $this->rtlo = Mage::getStoreConfig('payment/' . $this->_code . '/rtlo');
    }
    /**
     *
     * @param string $bankId
     * @return boolean
     */
    public function setupPayment($bankId = false)
    {
        $lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        $order = Mage::getModel('sales/order')->load($lastOrderId);
        $errMsg = '';
        
        if (! $order->getId()) {
            Mage::throwException('Cannot load order #' . $lastOrderId);
        }
        
        $orderId = $order->getRealOrderId();
        $language = (Mage::app()->getLocale()->getLocaleCode() == 'nl_NL') ? "nl" : "en";
        $targetPay = new TargetPayCore($this->_tp_method, $this->rtlo, $language, false);
        $targetPay->setAmount(round($order->getGrandTotal() * 100));
        $targetPay->setDescription("Order #" . $orderId);
        
        if($bankId) {
            if($this->_code == 'sofort') {
                $targetPay->setCountryId($bankId);
            }
            $targetPay->setBankId($bankId);

        }
        
        $targetPay->setReturnUrl(Mage::getUrl("$this->_code/$this->_code/return", array(
            '_secure' => true,
            'order_id' => $orderId
        )));
        $targetPay->setReportUrl(Mage::getUrl("$this->_code/$this->_code/report", array(
            '_secure' => true,
            'order_id' => $orderId
        )));

        if($this->_code == 'bankwire' || $this->_code == 'afterpay') {
            $this->additionalParameters($order, $targetPay); // Adding extra info for Bankwire startAPI
        }
        
        $bankUrl = $targetPay->startPayment();
        
        if (! $bankUrl) {
            $errMsg .= "TargetPay error: " . $targetPay->getErrorMessage();
        }
        
        if ($errMsg) {
            $this->restoreCart($order); // restore cart because magento automatically clear cart after place order
            Mage::getSingleton('core/session')->addError($errMsg);
            return false;
        }
        
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        
        $write->query("INSERT INTO `targetpay`
            SET `order_id`=" . $write->quote($orderId) . ",
                `method`=" . $write->quote($this->_tp_method) . ",
                `targetpay_txid`=" . $write->quote($targetPay->getTransactionId()) . ",
                `more` = " . $write->quote($targetPay->getMoreInformation()));
        
        if($this->_code == 'bankwire') {
            return $lastOrderId;
        }
        
        return $bankUrl;
    }

    /**
     * Restore cart when processing fail
     *
     * @param object $order
     */
    public function restoreCart($order)
    {
        $cart = Mage::getSingleton('checkout/cart');
        $items = $order->getItemsCollection();
        foreach ($items as $item) {
            try {
                $cart->addOrderItem($item);
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('core/session')->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::helper('checkout')->__('Cannot add the item to shopping cart.');
            }
        }
        
        $cart->save();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see Mage_Payment_Model_Method_Abstract::refund()
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();
        $creditmemo = Mage::app()->getRequest()->getParam("creditmemo");
        $dataRefund = array(
            'paymethodID' => $this->_tp_method,
            'transactionID' => $payment->getLastTransId(),
            'amount' => intval(floatval($amount)),
            'description' => $creditmemo["comment_text"],
            'internalNote' => 'Internal note - OrderId: ' . $order->getIncrement_id() . ', Amount: ' . $amount . ', InvoiceID: ' . $payment->getCreditmemo()
                ->getInvoice()
                ->getIncrement_id() . ', Customer Email: ' . $order->getCustomer_email(),
            'consumerName' => $payment->getOrder()->getCustomer_firstname() . ' ' . $payment->getOrder()->getCustomer_lastname()
        );
        
        $targetPay = new TargetPayCore($this->_tp_method, $this->rtlo);
        
        if (! $targetPay->refund(Mage::getStoreConfig("payment/$this->_code/token"), $dataRefund)) {
            Mage::throwException($targetPay->getErrorMessage());
        }
        
        return $this;
    }
}