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
 *  v2.1		Added pay by invoice
 */

require_once (BP . DS . 'app' . DS . 'code' . DS . 'local' . DS. "Targetpay" . DS . "targetpay.class.php");

class Targetpay_Paysafecard_PaysafecardController extends Mage_Core_Controller_Front_Action
	{
	protected $_code = 'paysafecard';
    protected $_tp_method = 'MRC';

    // Handle redirect that starts TargetPay payment

	public function redirectAction() {

		$paysafecardModel = Mage::getSingleton('paysafecard/paysafecard');
		$paysafecardUrl = $paysafecardModel->setupPayment();
		header('Location: ' . $paysafecardUrl);
		exit();
		}

	// Handle return URL

	public function returnAction() {

       	$paysafecardModel = Mage::getSingleton('paysafecard/paysafecard');

		$orderId = (int) $this->getRequest()->get('order_id');
		$write = Mage::getSingleton('core/resource')->getConnection('core_write');
		$sql = "SELECT `paid` FROM `targetpay` WHERE `order_id` = '".$write->quote($orderId)."' AND method='".$write->quote($this->_tp_method)."'";
		$result = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($sql);
    	$paid = $result[0]['paid'];

		if ($paid) {
			$this->_redirect('checkout/onepage/success', array('_secure' => true));
			} else {
			$order = Mage::getModel('sales/order')->loadByIncrementId($orderId);

			$session = Mage::getSingleton('checkout/session');
			$cart = Mage::getSingleton('checkout/cart');
			$orderItems = $order->getItemsCollection();

        	foreach($orderItems as $orderItem) {
				try {
					$cart->addOrderItem($orderItem);
					}
				catch(Exception $e) {
					}
				}
			$cart->save();

			$this->_redirect('checkout/cart');
			}
		}

	// Handle report URL

	public function reportAction() {

		$paysafecardModel = Mage::getSingleton('paysafecard/paysafecard');

		$orderId = (int) $this->getRequest()->get('order_id');
		$write = Mage::getSingleton('core/resource')->getConnection('core_write');
		$sql = "SELECT max(`targetpay_txid`) AS txid, `paid` FROM `targetpay` WHERE `order_id` = '".$write->quote($orderId)."' AND method='".$write->quote($this->_tp_method)."'";
		$result = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($sql);
		$txid = $result[0]['txid'];
		$alreadyPaid = ((!empty($result[0]['paid'])) ? true : false);
		if($alreadyPaid) {
			die('callback already processed');
		}

		$language = (Mage::app()->getLocale()->getLocaleCode() == 'nl_NL') ? "nl" : "en";
		$targetPay = new TargetPayCore ($this->_tp_method, Mage::getStoreConfig('payment/paysafecard/rtlo'), "f8ca4794a1792886bb88060ca0685c1e", $language, false);
		$targetPay->checkPayment($txid);

		$paymentStatus = (bool)$targetPay->getPaidStatus();
		$testMode = (bool) Mage::getStoreConfig('payment/paysafecard/testmode');
		if ($testMode) {
			$paymentStatus = true; // Always OK if in testmode
		}

		if ($paymentStatus) {
			$sql = "UPDATE `targetpay` SET `paid` = now() WHERE `order_id` = '".$orderId."' AND method='".$this->_tp_method."' AND `targetpay_txid` = '".$txid."'";
			Mage::getSingleton('core/resource')->getConnection('core_write')->query($sql);

			$order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
			if ($order->getState() != Mage_Sales_Model_Order::STATE_PROCESSING) {

				$invoice = $order->prepareInvoice();
				$invoice->register()->capture();
				Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();

				$order->setStatus('Processing');
				$order->setIsInProcess(true);
				$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Factuur #' . $invoice->getIncrementId() . ' aangemaakt.');

				$invoice->sendEmail();

				$order->sendNewOrderEmail();
				$order->setEmailSent(true);
				$order->save();
                }

			} else {
            $sql = "UPDATE `targetpay` SET `targetpay_response` = '".mysql_real_escape_string($targetPay->getErrorMessage())."' ".
            	   "WHERE `order_id` = '".$orderId."' AND method='".$this->_tp_method."'";
			Mage::getSingleton('core/resource')->getConnection('core_write')->query($sql);
            }

        echo "45000";
        die();
		}
	}

?>
