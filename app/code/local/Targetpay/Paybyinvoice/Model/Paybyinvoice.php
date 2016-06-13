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


require_once (BP . DS . 'app' . DS . 'code' . DS . 'local' . DS. "Targetpay" . DS . "targetpay.class.php");

class Targetpay_Paybyinvoice_Model_Paybyinvoice extends Mage_Payment_Model_Method_Abstract
	{

    protected $_code = 'paybyinvoice';
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = false;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc 				= false;

    protected $_tp_method 				= "AFT";

    /**
     * 	Prepare redirect that starts TargetPay payment
     */

	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('paybyinvoice/paybyinvoice/redirect', array('_secure' => true, 'country_id' => $_POST["payment"]["country_id"]));
		}

    /**
     *      Parse cart to order contents array
     */

    public function parseOrderContents ($order, $amountToPay) 
    {
        $return = array();

        // Cart items

        $products = $order->getAllItems();
        foreach ($products as $id => $product) {

			$tax_rate = $product->getTaxAmount() / $product->getPrice()* 100;
            $return[] = array(
                'type' => 1,
                'product' => $product->getId(),
                'description' => $product->getName(),
                'amount' => round($product->getPrice()*100), 
                'quantity' => $product->getQtyOrdered(),
                'amountvat' => $tax_rate,
                'discount' => 0, // Not available
                'discountvat' => 0 // Not available
            );
        }

        // Calculate shipping etc.

        $return[] = array(
            'type' => 2,
            'amount' => round(($order->getData('shipping_amount') + $order->getData('shipping_tax_amount'))*100), 
            'amountvat' => round($order->getData('shipping_tax_amount')*100),
            'discount' => 0, // Not available
            'discountvat' => 0 // Not available
        );

        // Rest?

        $rest = $amountToPay - $order->getData('shipping_amount') - $order->getData('shipping_tax_amount');

        if ($rest > 0.01) {
            $return[] = array(
                'type' => 4, // Actually we don't know...
                'amount' => round($rest*100),
                'amountvat' => 0, 
                'discount' => 0, // Not available
                'discountvat' => 0 // Not available
            );            
        }

        // var_dump ($return);
        // die();

        return $return;
    }		

    /**
     * 	Start payment
     */

	public function setupPayment($bankId = false) {

    	$lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();
		$order = Mage::getModel('sales/order')->load($lastOrderId);

		if (!$order->getId()) {
			Mage::throwException('Cannot load order #' . $lastOrderId);
			}

		if($order->getGrandTotal() < 0.75) {
			Mage::throwException('The total amount should be at least 0.75');
			}

        if($order->getGrandTotal() > 1000) {
            Mage::throwException('The total amount cannot exceed 1000 euro');
            }


		$orderId = $order->getRealOrderId();
		$language = (Mage::app()->getLocale()->getLocaleCode() == 'nl_NL') ? "nl" : "en";
		$targetPay = new TargetPayCore ($this->_tp_method, Mage::getStoreConfig('payment/paybyinvoice/rtlo'), "f8ca4794a1792886bb88060ca0685c1e", $language, false);
		$targetPay->setAmount ( round($order->getGrandTotal() * 100));
		$targetPay->setDescription ( "Order #". $orderId );
		$targetPay->setReturnUrl ( Mage::getUrl('paybyinvoice/paybyinvoice/return', array('_secure' => true, 'order_id' => $orderId) ));
		$targetPay->setReportUrl ( Mage::getUrl('paybyinvoice/paybyinvoice/report', array('_secure' => true, 'order_id' => $orderId) ));

		$billingAddress = $order->getBillingAddress();
		$shippingAddress = $order->getShippingAddress();

        $targetPay->setCurrency ($order->getData('order_currency_code')); 
        $targetPay->bindParam ("cgender", $order->getData('customer_gender')) // Resume
                ->bindParam ("cinitials", ucfirst(substr($order_info['customer_firstname'],0,1)).".") // Experimental
                ->bindParam ("clastname", $order->getData('customer_lastname')) 
                ->bindParam ("cbirthdate", "") // Resume
                ->bindParam ("cbank", "") // Resume
                ->bindParam ("cphone", $billingAddress->getTelephone()) 
                ->bindParam ("cmobilephone", $billingAddress->getTelephone()) 
                ->bindParam ("cemail", $order->getData('customer_email')) 

                ->bindParam ("order", $order->getId()) 
                ->bindParam ("ordercontents", json_encode($this->parseOrderContents($order, $order->getGrandTotal() ))) // todo

                ->bindParam ("invoiceaddress", implode(" ", $billingAddress->getStreet())) 
                ->bindParam ("invoicezip", $billingAddress->getPostcode()) 
                ->bindParam ("invoicecity", $billingAddress->getCity()) 
                ->bindParam ("invoicecountry", $billingAddress->getCountry())

                ->bindParam ("deliveryaddress", implode(" ", $shippingAddress->getStreet())) 
                ->bindParam ("deliveryzip", $shippingAddress->getPostcode())
                ->bindParam ("deliverycity", $shippingAddress->getCity()) 
                ->bindParam ("deliverycountry", $shippingAddress->getCountry());

		$bankUrl = $targetPay->startPayment();

		if (!$bankUrl) {
			Mage::throwException("TargetPay error: ". $targetPay->getErrorMessage() );
			}

		$write = Mage::getSingleton('core/resource')->getConnection('core_write');
		$write->query("INSERT INTO `targetpay` SET `order_id`='".$write->quote($orderId)."', `method`='".$write->quote($this->_tp_method)."', `targetpay_txid`='".$write->quote($targetPay->getTransactionId())."'");

		return $bankUrl;
		}


    /**
     * 	Not implemented here
     */

	public function validatePayment($sOrderId) {
		}
	}

?>
