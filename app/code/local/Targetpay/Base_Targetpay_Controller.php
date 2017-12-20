<?php
require_once (BP . DS . 'app' . DS . 'code' . DS . 'local' . DS . "Targetpay" . DS . "Base_Targetpay_Model.php");

class Base_Targetpay_Controller extends Mage_Core_Controller_Front_Action
{

    public $errorMsg;

    public $message;

    /**
     * Handle redirect that starts TargetPay payment
     */
    public function redirectAction()
    {
        $payModel = Mage::getSingleton($this->_code . '/' . $this->_code);
        $payUrl = $payModel->setupPayment($this->getRequest()
            ->get('bank_id'));
        if (! empty($payUrl)) {
            header('Location: ' . $payUrl);
            exit();
        }
        if (! $payUrl) { // Fail and restore cart
            $this->_redirect('checkout/onepage');
        }
    }

    /**
     * Handle return URL
     */
    public function returnAction()
    {
        switch ($this->_tp_method) {
            case 'PYP':
                $trxid = $this->getRequest()->get('paypalid');
                break;
            case 'AFP':
                $trxid = $this->getRequest()->get('invoiceID');
                break;
            default:
                $trxid = $this->getRequest()->get('trxid');
        }
        
        $orderId = (int) $this->getRequest()->get('order_id');
        
        // Call report first
        if (! $this->execReport($orderId, $trxid)) {
            // Fail case
            Mage::getSingleton('core/session')->addError($this->errorMsg);
            
            $obj = new Base_Targetpay_Model();
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            $obj->restoreCart($order);
            
            $this->_redirect('checkout/cart');
        } else {
            Mage::getSingleton('core/session')->addSuccess($this->message);
            $this->_redirect('checkout/onepage/success');
        }
    }

    private function execReport($orderId, $trxid)
    {
        if (empty($trxid)) {
            $this->errorMsg = 'Transaction txid missing';
            return false;
        }
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $sql = "SELECT `paid` FROM `targetpay` WHERE `targetpay_txid` = " . $write->quote($trxid) . " AND method=" . $write->quote($this->_tp_method);
        
        $result = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($sql);
        
        if (! count($result)) {
            $this->errorMsg = 'Transaction not found';
            return false;
        }
        if (! empty($result[0]['paid'])) {
            $this->message = 'Callback already processed';
            return true;
        }
        
        $language = (Mage::app()->getLocale()->getLocaleCode() == 'nl_NL') ? "nl" : "en";
        $targetPay = new TargetPayCore($this->_tp_method, Mage::getStoreConfig("payment/$this->_code/rtlo"), $language, false);
        
        $targetPay->checkPayment($trxid);
        
        $paymentStatus = (bool) $targetPay->getPaidStatus();
        $testMode = (bool) Mage::getStoreConfig("payment/$this->_code/testmode");
        if ($testMode) {
            $paymentStatus = true; // Always OK if in testmode
        }
        
        if ($paymentStatus) {
            $sql = "UPDATE `targetpay` SET `paid` = now() WHERE `order_id` = '" . $orderId . "' AND method='" . $this->_tp_method . "' AND `targetpay_txid` = '" . $trxid . "'";
            Mage::getSingleton('core/resource')->getConnection('core_write')->query($sql);
            
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if (!in_array($order->getState(), [Mage_Sales_Model_Order::STATE_PROCESSING, Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW])) {
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                $newState = Mage_Sales_Model_Order::STATE_PROCESSING;
                $orderGrandTotal = $order->getGrandTotal();
                if ($this->_tp_method == 'BW' && $targetPay->getBankwireAmountPaid() < $targetPay->getBankwireAmountDue()) {
                    $invoice->setBaseGrandTotal($targetPay->getBankwireAmountPaid() / 100);
                    $invoice->setGrandTotal($targetPay->getBankwireAmountPaid() / 100);
                    $newState = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
                    $orderGrandTotal = $targetPay->getBankwireAmountPaid() / 100; // Make sure a Refund is not done for full amount if less was paid
                }

                if (! $invoice->getTotalQty()) {
                    Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
                }
                
                $invoice->register();
                $invoice->sendEmail();
                
                $transaction = Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder());
                $transaction->save();
                
                // Add transaction for refund.
                $payment = $order->getPayment();
                $payment->setTransactionId($trxid)
                    ->setCurrencyCode()
                    ->setPreparedMessage('message')
                    ->setShouldCloseParentTransaction(true)
                    ->setIsTransactionClosed(0)
                    ->registerCaptureNotification($orderGrandTotal);
                
                $order->setStatus('Processing');
                $order->setIsInProcess(true);
                $order->setState($newState, true, 'Invoice #' . $invoice->getIncrementId() . ' created.');
                $order->sendNewOrderEmail();
                $order->setEmailSent(true);
                $order->save();
                
                $this->message = 'Callback has been processed';
            } else {
                $this->message = "Already completed, skipped... ";
            }
            
            return true;
        } else {
            $this->errorMsg = "Error in payment processing " . $targetPay->getErrorMessage();
            return false;
        }
    }

    /**
     * Handle report URL
     */
    public function reportAction()
    {
        $orderId = (int) $this->getRequest()->get('order_id');
        
        switch ($this->_tp_method) {
            case 'AFP':
                $trxid = (string) $this->getRequest()->getPost('invoiceID', null);
                break;
            case 'PYP':
                $trxid = (string) $this->getRequest()->getPost('acquirerID', null);
                break;
            default:
                $trxid = (string) $this->getRequest()->getPost('trxid', null);
        }

        if (! $this->execReport($orderId, $trxid)) {
            echo $this->errorMsg;
        }
        
        echo $this->message;
        echo "(Magento, 06-09-2016)";
    }
}