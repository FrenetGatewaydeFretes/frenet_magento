<?php
/**
 * This source file is subject to the MIT License.
 * It is also available through http://opensource.org/licenses/MIT
 *
 * @category  Akhilleus
 * @package   LithiumSoftware_Akhilleus
 * @author    LithiumSoftware <contato@lithiumsoftware.com.br>
 * @copyright 2015 Lithium Software
 * @license   http://opensource.org/licenses/MIT MIT
 */

class LithiumSoftware_Akhilleus_Model_Observer  extends Mage_Core_Model_Abstract {

    public function SaveShippingResultLog($observer){
        try
        {
            $shipment = $observer->getEvent()->getShipment();
            if ($shipment) {
                $order = $shipment->getOrder();
                if ($order) {

                    $this->_log('SaveShippingResultLog - ShippingMethod: ' . $order->getShippingMethod());
                    $this->_log('SaveShippingResultLog - ShippingDescription: ' . $order->getShippingDescription());

                    $orderID = $order->getIncrementId();
                    $sellerCEP = Mage::getStoreConfig('shipping/origin/postcode');
                    $recipientCEP = $order->getShippingAddress()->getPostcode();
                    $shipmentInvoiceValue = $order->getSubtotal();
                    $shippingAmount = $order->getShippingAmount();
                    $shipmentWeight = $order->getWeight();
                    $deliveryTime = preg_replace("/[^0-9]/","",$order->getShippingDescription());
                    $shippingMethod = str_replace("akhilleus_", "",$order->getShippingMethod());

                    // Call Webservices
                    $this->saveShippingResult($orderID, $sellerCEP,
                        $recipientCEP, $shipmentInvoiceValue, $shippingAmount, $deliveryTime, $shipmentWeight, $shippingMethod);

                }
            }
        } catch (Exception $e) {
            $this->_log('SaveShippingResultLog - Error: ' . $e->getMessage(), __LINE__);
        }

        return $this;
    }

    protected function _log($msg) {
        Mage::log('Akhilleus: ' . $msg);
    }

    protected function saveShippingResult($orderID, $sellerCEP, $recipientCEP, $shipmentInvoiceValue, $shippingAmount, $deliveryTime, $shipmentWeight, $shippingMethod)
    {
        $url = Mage::getStoreConfig('carriers/akhilleus/url_ws');

        try {
            $client = new SoapClient($url, array("soap_version" => SOAP_1_1,"trace" => 1));

            $login = Mage::getStoreConfig('carriers/akhilleus/login');
            $password = Mage::getStoreConfig('carriers/akhilleus/password');

            $service_param = array (
                'saveRequest' => array(
                    'Username' => $login,
                    'Password' => $password,
                    'OrderID' => $orderID,
                    'SellerCEP' => $sellerCEP,
                    'RecipientCEP' => $recipientCEP,
                    'ShipmentInvoiceValue' => $shipmentInvoiceValue,
                    'ShippingPrice' => $shippingAmount,
                    'DeliveryTime' => $deliveryTime,
                    'ShipmentWeight' => $shipmentWeight,
                    'ShippingMethod' => $shippingMethod
                )
            );

            $content = $client->__soapCall("SaveShippingResultLog", array($service_param));

            $this->_log($client->__getLastRequest());
            $this->_log($client->__getLastResponse());

            if ($content == "") {
                throw new Exception("No XML returned [" . __LINE__ . "]");
            }
            return $content;

        } catch (Exception $e) {
            $this->_log('_getWebServicesReturn - Error: ' . $e->getMessage(), __LINE__);

            //URL Error
            $this->_throwError('urlerror', 'URL Error - ' . $e->getMessage(), __LINE__);

            return false;
        }
    }


} 