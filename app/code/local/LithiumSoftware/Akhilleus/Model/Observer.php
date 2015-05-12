<?php
/**
 * Created by PhpStorm.
 * User: LithiumMAC
 * Date: 07/05/15
 * Time: 14:38
 */

class LithiumSoftware_Akhilleus_Model_Observer extends Varien_Object {

    public function SaveShippingData($observer)
    {
        $event = $observer->getEvent();
        $order = $event->getOrder();

        Mage::log('Akhilleus: SaveShippingData - ShippingMethod: ' . $order->getShippingMethod());
        Mage::log('Akhilleus: SaveShippingData - ShippingDescription: ' . $order->getShippingDescription());

        $sellerCEP = Mage::getStoreConfig('shipping/origin/postcode');
        $recipientCEP = $order->getShippingAddress()->getPostcode();
        $shipmentInvoiceValue = $order->getSubtotal();
        $shippingAmount = $order->getShippingAmount();
        $shipmentWeight = $order->getWeight();
        $deliveryTime = preg_replace("/[^0-9]/","",$order->getShippingDescription());
        $shippingMethod = str_replace("akhilleus_", "",$order->getShippingMethod());

        // Call Webservices
        $wsReturn = $this->_getWebServicesReturn($sellerCEP, $recipientCEP, $shipmentInvoiceValue, $shippingAmount, $deliveryTime, $shipmentWeight, $shippingMethod);
    }

    /**
     * Get Webservices return
     *
     * @return bool|SimpleXMLElement[]
     */
    protected function _getWebServicesReturn($sellerCEP, $recipientCEP, $shipmentInvoiceValue, $shippingAmount, $deliveryTime, $shipmentWeight, $shippingMethod)
    {
        $url = 'http://services.lithiumsoftware.com.br/logistics/ShippingQuoteWS.asmx?wsdl';

        try {

            $client = new SoapClient($url, array("soap_version" => SOAP_1_1,"trace" => 1));

            $login = Mage::getStoreConfig('carriers/akhilleus/login');
            $password = Mage::getStoreConfig('carriers/akhilleus/password');

            $service_param = array (
                'userName' => $login,
                'password' => $password,
                'sellerCEP' => $sellerCEP,
                'recipientCEP' => $recipientCEP,
                'shipmentInvoiceValue' => $shipmentInvoiceValue,
                'shippingPrice' => $shippingAmount,
                'deliveryTime' => $deliveryTime,
                'shipmentWeight' => $shipmentWeight,
                'shippingMethod' => $shippingMethod
            );

            $this->_log('Chamada do webservices - ' .
                'userName' . $login . 'password' . $password .
                'Origem: ' .$sellerCEP . ' Destino: ' . $recipientCEP . ' Peso: ' . $shipmentWeight . ' ValorDeclarado: ' . $shipmentInvoiceValue .
                ' Valor do Frete: ' . $shippingAmount . ' Prazo de entrega: ' . $deliveryTime . ' Metodo de entrega: ' . $shippingMethod);

            $content = $client->__soapCall("SaveShippingResultLog", array($service_param));

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

    protected function _log($msg) {
        Mage::log('Akhilleus: ' . $msg);
    }


} 