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

class Targetpay_Afterpay_Model_Afterpay extends Base_Targetpay_Model
{

    protected $_code = 'afterpay';

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

    protected $_tp_method = "AFP";

    public $errorMsg;

    /**
     * Prepare redirect that starts TargetPay payment
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('afterpay/afterpay/redirect', array(
            '_secure' => true,
            'bank_id' => $_POST["payment"]["bank_id"]
        ));
    }

    /**
     *
     * @param unknown $country
     * @param unknown $phone
     * @return unknown
     */
    private static function format_phone($country, $phone)
    {
        $function = 'format_phone_' . strtolower($country);
        if (method_exists('Targetpay_Afterpay_Model_Afterpay', $function)) {
            return self::$function($phone);
        } else {
            echo "unknown phone formatter for country: " . $function;
            exit();
        }
        return $phone;
    }

    /**
     *
     * @param unknown $phone
     * @return string|mixed
     */
    private static function format_phone_nld($phone)
    {
        // note: making sure we have something
        if (! isset($phone{3})) {
            return '';
        }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch ($length) {
            case 9:
                return "+31" . $phone;
                break;
            case 10:
                return "+31" . substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+" . $phone;
                break;
            default:
                return $phone;
                break;
        }
    }

    /**
     *
     * @param unknown $phone
     * @return string|mixed
     */
    private static function format_phone_bel($phone)
    {
        // note: making sure we have something
        if (! isset($phone{3})) {
            return '';
        }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch ($length) {
            case 9:
                return "+32" . $phone;
                break;
            case 10:
                return "+32" . substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+" . $phone;
                break;
            default:
                return $phone;
                break;
        }
    }

    /**
     *
     * @param unknown $street
     * @return NULL[]|string[]|unknown[]
     */
    private static function breakDownStreet($street)
    {
        $out = [];
        $addressResult = null;
        preg_match("/(?P<address>\D+) (?P<number>\d+) (?P<numberAdd>.*)/", $street, $addressResult);
        if (! $addressResult) {
            preg_match("/(?P<address>\D+) (?P<number>\d+)/", $street, $addressResult);
        }
        $out['street'] = array_key_exists('address', $addressResult) ? $addressResult['address'] : null;
        $out['houseNumber'] = array_key_exists('number', $addressResult) ? $addressResult['number'] : null;
        $out['houseNumberAdd'] = array_key_exists('numberAdd', $addressResult) ? trim(strtoupper($addressResult['numberAdd'])) : null;
        return $out;
    }

    /**
     *
     * @param unknown $order
     * @param TargetPayCore $targetPay
     */
    public function additionalParameters($order, TargetPayCore $targetPay)
    {
        $billingData = $order->getBillingAddress()->getData();
        $shippingData = $order->getShippingAddress()->getData();
        
        // Supported countries are: Netherlands (NLD) and in Belgium (BEL)
        $billingCountry = (strtoupper($billingData['country_id']) == 'BE' ? 'BEL' : 'NLD');
        $shippingCountry = (strtoupper($shippingData['country_id']) == 'BE' ? 'BEL' : 'NLD');
        
        $streetParts = self::breakDownStreet($billingData['street']);
        
        $targetPay->bindParam('billingstreet', $streetParts['street']);
        $targetPay->bindParam('billinghousenumber', $streetParts['houseNumber'] . $streetParts['houseNumberAdd']);
        $targetPay->bindParam('billingpostalcode', $billingData['postcode']);
        $targetPay->bindParam('billingcity', $billingData['city']);
        $targetPay->bindParam('billingpersonemail', $billingData['email']);
        $targetPay->bindParam('billingpersoninitials', "");
        $targetPay->bindParam('billingpersongender', "");
        $targetPay->bindParam('billingpersonsurname', $billingData['lastname']);
        $targetPay->bindParam('billingcountrycode', $billingCountry);
        $targetPay->bindParam('billingpersonlanguagecode', $billingCountry);
        $targetPay->bindParam('billingpersonbirthdate', "");
        $targetPay->bindParam('billingpersonphonenumber', self::format_phone($billingCountry, $billingData['telephone']));
        
        $streetParts = self::breakDownStreet($shippingData['street']);
        
        $targetPay->bindParam('shippingstreet', $streetParts['street']);
        $targetPay->bindParam('shippinghousenumber', $streetParts['houseNumber'] . $streetParts['houseNumberAdd']);
        $targetPay->bindParam('shippingpostalcode', $shippingData['postcode']);
        $targetPay->bindParam('shippingcity', $shippingData['city']);
        $targetPay->bindParam('shippingpersonemail', $shippingData['email']);
        $targetPay->bindParam('shippingpersoninitials', "");
        $targetPay->bindParam('shippingpersongender', "");
        $targetPay->bindParam('shippingpersonsurname', $shippingData['lastname']);
        $targetPay->bindParam('shippingcountrycode', $shippingCountry);
        $targetPay->bindParam('shippingpersonlanguagecode', $shippingCountry);
        $targetPay->bindParam('shippingpersonbirthdate', "");
        $targetPay->bindParam('shippingpersonphonenumber', self::format_phone($shippingCountry, $shippingData['telephone']));
        
        $targetPay->bindParam('test', (bool) Mage::getStoreConfig('payment/afterpay/testmode'));
        
        // Getting the items in the order
        $order_items = $order->getAllItems();
        $invoicelines = [];
        $store = Mage::app()->getStore('default');
        $total_amount_by_products = 0;
        // Iterating through each item in the order
        foreach ($order_items as $item_data) {
            $product_name = $item_data->getName();
            $item_quantity = $item_data->getQtyOrdered();
            $item_total = (float) $item_data->getPrice();
            
            $invoicelines[] = [
                'productCode' => (string) $item_data->getSku(),
                'productDescription' => $product_name,
                'quantity' => (int) $item_quantity,
                'price' => $item_total,
                'taxCategory' => $targetPay->getTax(Mage::getSingleton('tax/calculation')->getStoreRateForItem($item_data, $store))
            ];
            
            $total_amount_by_products += $item_total;
        }
        $invoicelines[] = [
            'productCode' => '000000',
            'productDescription' => "Other fees (shipping, additional fees)",
            'quantity' => 1,
            'price' => $order->getGrandTotal() - $total_amount_by_products,
            'taxCategory' => 1
        ];
        
        $targetPay->bindParam('invoicelines', json_encode($invoicelines));
        $targetPay->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
    }
}

?>
