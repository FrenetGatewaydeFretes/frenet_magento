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
class LithiumSoftware_Akhilleus_Model_Carrier_Akhilleus
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'akhilleus';

    protected $_result = null;

    protected $_value				= NULL; // Valor do pedido
    protected $_weightType			= NULL; // Unidade de medida
    protected $_weight				= NULL; // Peso total do pedido
    protected $_length				= NULL; // Tamanho
    protected $_height				= NULL; // Altura
    protected $_width				= NULL; // Largura
    protected $_diameter		    = NULL; // Diametro
    protected $_title				= NULL; // Título do método de envio
    protected $_from				= NULL; // CEP de origem
    protected $_to					= NULL; // CEP de destino
    protected $_destCountry         = NULL; // IATA do pais destino
    protected $_recipientDocument	= NULL; // CPF / CNPJ do destinatario
    protected $_packageWeight		= NULL; // valor ajustado do pacote
    protected $_showDelivery        = NULL; // Determina exibição de prazo de entrega
    protected $_addDeliveryDays     = NULL; // Adiciona n dias ao prazo de entrega

    protected $_simpleProducts = array();
    protected $_productsQty = array();

    /**
     * Collect rates for this shipping method based on information in $request
     *
     * @param Mage_Shipping_Model_Rate_Request $data
     * @return Mage_Shipping_Model_Rate_Result
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request){
        $this->_init($request);

        $this->_getQuotes($request);

        return $this->_result;
    }

    /**
     * Get shipping quote
     *
     * @return Mage_Shipping_Model_Rate_Result|Mage_Shipping_Model_Tracking_Result
     */
    protected function _getQuotes(Mage_Shipping_Model_Rate_Request $request)
    {
        // Call Webservices
        $wsReturn = $this->_getWebServicesQuoteReturn($request);

        if ($wsReturn !== false && isset($wsReturn->GetShippingQuoteResult) && isset($wsReturn->GetShippingQuoteResult->ShippingSevicesArray)) {

            $this->_log("Qtd serviços: " . count($wsReturn->GetShippingQuoteResult->ShippingSevicesArray->ShippingSevices));

            // Check if exist return from Webservices
            $existReturn = false;

            if(count($wsReturn->GetShippingQuoteResult->ShippingSevicesArray->ShippingSevices)==1)
                $servicosArray[0] = $wsReturn->GetShippingQuoteResult->ShippingSevicesArray->ShippingSevices;
            else
                $servicosArray = $wsReturn->GetShippingQuoteResult->ShippingSevicesArray->ShippingSevices;

            foreach($servicosArray as $servicos){

                // Get Webservices error

                $this->_log("Percorrendo os serviços retornados");

                if (!isset($servicos->ServiceCode) || $servicos->ServiceCode . '' == '' || !isset($servicos->ShippingPrice)) {
                    continue;
                }

                $shippingPrice = floatval(str_replace(",", ".", (string) $servicos->ShippingPrice));
                $delivery = (int) $servicos->DeliveryTime;
                $shipping_method = $servicos->ServiceCode;
                $shipping_method_name = $servicos->ServiceDescription;

                $this->_log("Preço " . $shippingPrice);

                // Append shipping methods
                $this->_appendShippingMethod($shipping_method, $shippingPrice, $delivery, $shipping_method_name, $request);

                $existReturn = true;
            }

            // All services are ignored
            if ($existReturn === false) {
                $this->_throwError('urlerror', 'URL Error, all services return with error', __LINE__);
                return $this->_result;
            }
        } else {
            // Error on HTTP Webservices
            return $this->_result;
        }

        // Success
        return $this->_result;
    }

    /**
     * Get Webservices return
     *
     * @return bool|SimpleXMLElement[]
     */
    protected function _getWebServicesQuoteReturn(Mage_Shipping_Model_Rate_Request $request)
    {
        $url    = $this->getConfigData('url_ws');
        $client = new SoapClient($url, array("soap_version" => SOAP_1_1,"trace" => 1, "cache_wsdl" => WSDL_CACHE_NONE));

        try {
            if ($this->getConfigFlag('use_default'))
            {
                $this->_length = $this->getConfigData('default_length'); //16
                $this->_width = $this->getConfigData('default_width'); //11
                $this->_height = $this->getConfigData('default_height'); //2
                $this->_diameter = 0;
            }
            else
            {
                $this->_length = 0;
                $this->_width = 0;
                $this->_height = 0;
                $this->_diameter = 0;

                // Pega as maiores dimensões dos produtos do carrinho
                foreach($request->getAllItems() as $item){
                    if($item->getProduct()->getData('volume_comprimento') > $this->_length)
                        $this->_length = $item->getProduct()->getData('volume_comprimento');

                    if($item->getProduct()->getData('volume_largura') > $this->_width)
                        $this->_width = $item->getProduct()->getData('volume_largura');

                    if($item->getProduct()->getData('volume_altura') > $this->_height)
                        $this->_height = $item->getProduct()->getData('volume_altura');

                    $this->_diameter = 0;
                }
            }

            // gerar o array de produtos
            $shippingItemArray = array();
            $count = 0;
            $this->getSimpleProducts($request->getAllItems());
            $productsCount = count ($this->_simpleProducts);
            $j = 0;
            for ($i = 0; $i < $productsCount; $i ++)
            {
                $productObj = $this->_simpleProducts[$i];

                //$this->_log(json_encode($productObj->getData()));
                //$this->_log('Quantidade: ' . $this->_productsQty[$i]);

                $shippingItem = new stdClass();
                $shippingItem->Weight = $this->_fixWeight($productObj->getWeight());
                if ($this->getConfigFlag('use_default'))
                {
                    $shippingItem->Length = $this->getConfigData('default_length'); //16
                    $shippingItem->Width =  $this->getConfigData('default_width'); //11
                    $shippingItem->Height = $this->getConfigData('default_height'); //2
                }
                else{
                    $shippingItem->Length = ($productObj->getVolume_comprimento() > 0 ? $productObj->getVolume_comprimento() : $this->getConfigData('default_length') );
                    $shippingItem->Height = ($productObj->getVolume_altura() > 0 ? $productObj->getVolume_altura() : $this->getConfigData('default_height'));
                    $shippingItem->Width = ($productObj->getVolume_largura()>0 ? $productObj->getVolume_largura() : $this->getConfigData('default_width'));
                }
                $shippingItem->Diameter = 0;
                $shippingItem->SKU = $productObj->getSku();

                $categoryIds = $productObj->getCategoryIds();
                $result = '';

                foreach ($categoryIds as $catId) {
                    $category = Mage::getModel('catalog/category')->load($catId);

                    if($category)
                    {
                        $coll = $category->getResourceCollection();
                        $pathIds = $category->getPathIds();
                        $coll->addAttributeToSelect('name');
                        $coll->addAttributeToFilter('entity_id', array('in' => $pathIds));

                        foreach ($coll as $cat) {
                            if(strpos($result, $cat->getName())=== false)
                                $result .= $cat->getName().'|';
                        }
                    }
                }

                $shippingItem->Category = $result;

                if($productObj->getFragile())
                    $shippingItem->isFragile = $productObj->getFragile();
                else
                    $shippingItem->isFragile=false;

                $shippingItem->Quantity = $this->_productsQty[$i];
                $shippingItemArray[$count] = $shippingItem;
                $count++;
            }

            $service_param = array (
                'quoteRequest' => array(
                    'Username' => $this->getConfigData('login'),
                    'Password' => $this->getConfigData('password'),
                    'SellerCEP' => $this->_from,
                    'RecipientCEP' => $this->_to,
                    'RecipientCountry' => $this->_destCountry,
                    'RecipientDocument' => $this->_recipientDocument,
                    'ShipmentInvoiceValue' => $this->_value,
                    'ShippingItemArray' => $shippingItemArray
                )
            );

            $content = $client->__soapCall("GetShippingQuote", array($service_param));

            $this->_log($client->__getLastRequest());
            $this->_log($client->__getLastResponse());

            if ($content == "") {
                throw new Exception("No XML returned [" . __LINE__ . "]");
            }

            return $content;

        } catch (Exception $e) {
            //URL Error
            $this->_throwError('urlerror', 'URL Error - ' . $e->getMessage() . ' request ' . $client->__getLastRequest(), __LINE__);
            return false;
        };
    }

    protected function _appendShippingMethod($shipping_method, $shippingPrice = 0, $delivery = 0, $shipping_method_name, Mage_Shipping_Model_Rate_Request $request){
        $method = Mage::getModel('shipping/rate_result_method');
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->_title);

        $method->setMethod($shipping_method);


        //Obter o maior LEADTIME dos produtos do request
        $cartLeadTime = 0;
        $productLeadTime=0;

        if(!isset($this->_simpleProducts) || count ($this->_simpleProducts) == 0)
            $this->getSimpleProducts($request->getAllItems());

        $productsCount = count ($this->_simpleProducts);
        $j = 0;
        for ($i = 0; $i < $productsCount; $i ++)
        {
            $productObj = $this->_simpleProducts[$i];
            //verificar se a propriedade Leadtime existe
            if($productObj->offsetExists('leadtime'))
                $productLeadTime = $productObj->getleadtime();

            if($cartLeadTime < $productLeadTime)
                $cartLeadTime=$productLeadTime;
        }
        
        $this->_log('Leadtime: ' . $cartLeadTime);

        if ($this->_showDelivery && ((int)($delivery + $this->_addDeliveryDays + $cartLeadTime) > 0)){
            $this->_log('Delivery: ' . $delivery);
            $this->_log('Show Delivery: ' . $this->_showDelivery);

            $method->setMethodTitle(sprintf($this->getConfigData('msgprazo'), $shipping_method_name, (int)($delivery + $this->_addDeliveryDays + $cartLeadTime)));
        }
        else {
            $method->setMethodTitle($shipping_method_name);
        }

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);

        $this->_result->append($method);
    }

    protected function _init(Mage_Shipping_Model_Rate_Request $request){
        if (!$this->isActive()) {
            $this->_log('Módulo Desabilitado');
            return false;
        }

        if (!$this->_checkZipCode($request)) return false;

        $this->_title = $this->getConfigData('title');
        $this->_weightType = $this->getConfigData('weight_type');
        $this->_result = Mage::getModel('shipping/rate_result');
        $this->_showDelivery = $this->getConfigData('show_delivery');
        $this->_addDeliveryDays = $this->getConfigData('add_delivery_days');

        $this->_value = $request->getBaseCurrency()->convert($request->getPackageValue(), $request->getPackageCurrency());

        $this->_updatePackageWeight($request);

        $this->_weight = $this->_fixWeight($request->getPackageWeight());

        // obter o documento do destinatário - algumas transportadoras exigem
        $this->_recipientDocument = '';
    }

    protected function _updatePackageWeight(Mage_Shipping_Model_Rate_Request $request)
    {
        $this->_packageWeight = 0;

        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren()) {
                    foreach ($item->getChildren() as $child) {
                        $product_id = $child->getProductId();
                        $productObj = Mage::getModel('catalog/product')->load($product_id);

                        $this->_packageWeight += $productObj->getWeight();
                    }
                } else {
                    $this->_packageWeight +=  $item->getRowWeight();
                }
            }

            if($request->getPackageWeight() > $this->_packageWeight)
                $this->_packageWeight = $request->getPackageWeight();

        }
        $request->setPackageWeight($this->_packageWeight);
    }

    /**
     * Retorna mensagem de erro
     *
     * @param $message string
     * @param $log     string
     * @param $line    int
     * @param $custom  string
     */
    protected function _throwError($message, $log = null, $line = 'NO LINE', $custom = null){

        $this->_result = null;
        $this->_result = Mage::getModel('shipping/rate_result');

        // Get error model
        $error = Mage::getModel('shipping/rate_result_error');
        $error->setCarrier($this->_code);
        $error->setCarrierTitle($this->getConfigData('title'));

        if(is_null($custom)){
            //Log error
            Mage::log($this->_code . ' [' . $line . ']: ' . $log);
            $error->setErrorMessage($this->getConfigData($message));
        }else{
            //Log error
            Mage::log($this->_code . ' [' . $line . ']: ' . $log);
            $error->setErrorMessage(sprintf($this->getConfigData($message), $custom));
        }

        // Apend error
        $this->_result->append($error);
    }

    /**
     * Registra as mensagens do módulo no padrão estabelecido
     *
     * @param string $msg
     */
    protected function _log($msg) {
        Mage::log('Akhilleus: ' . $msg);
    }

    /**
     * Formata um CEP informado
     *
     * @param string $zipcode
     * @return boolean|Ambigous <string, mixed>
     */
    protected function _formatZip($zipcode) {
        $new = trim($zipcode);
        $new = preg_replace('/[^0-9\s]/', '', $new);

        if(!preg_match("/^[0-9]{8}$/", $new)){
            return false;
        }

        return $new;
    }

    /**
     * Recupera, formata e verifica se os CEPs de origem e destino estão
     * dentro do padrão correto
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return boolean
     */
    protected function _checkZipCode(Mage_Shipping_Model_Rate_Request $request) {
        $this->_from = $this->_formatZip(Mage::getStoreConfig('shipping/origin/postcode', $this->getStore()));


        if ($request->getDestCountryId()) {
            $this->_destCountry = $request->getDestCountryId();
        } else {
            $this->_destCountry = 'BR';
        }

        $this->_log('Country ID ' . $this->_destCountry);

        if(!$this->_to && $this->_destCountry == 'BR'){
            $this->_to = $this->_formatZip($request->getDestPostcode());
        } else {$this->_to = $request->getDestPostcode();}

        if(!$this->_from){
            $this->_log('Erro com CEP de origem');
            return false;
        }

        if(!$this->_to && $this->_destCountry == 'BR'){
            $this->_log('Erro com CEP de destino');
            $this->_throwError('zipcodeerror', 'CEP Inválido', __LINE__);
            return false;
        }

        return true;
    }

    /**
     * Corrige o peso informado com base na medida de peso configurada
     * @param string|int|float $weight
     * @return double
     */
    protected function _fixWeight($weight) {
        $result = $weight;
        if ($this->_weightType == 'gr') {
            $result = number_format($weight/1000, 2, '.', '');
        }

        return $result;
    }

    /**
     * Returns the allowed carrier methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array($this->_code => $this->getConfigData('name'));
    }

    /**
     * Check if current carrier offer support to tracking
     *
     * @return bool true
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    public function getTrackingInfo($tracking)
    {
        $result = $this->getTracking($tracking);
        if ($result instanceof Mage_Shipping_Model_Tracking_Result) {
            if ($trackings = $result->getAllTrackings()) {
                return $trackings[0];
            }
        } elseif (is_string($result) && !empty($result)) {
            return $result;
        }

        return false;
    }

    public function getTracking($trackings)
    {
        $this->_result = Mage::getModel('shipping/tracking_result');
        foreach ((array) $trackings as $code) {
            $this->_getTrackingFromWS($code);
        }
        return $this->_result;
    }

    protected function _getTrackingFromWS($tracking)
    {
        $sales_flat_shipment_track = Mage::getSingleton('core/resource')->getTableName('sales_flat_shipment_track');
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sql = 'SELECT order_id FROM ' . $sales_flat_shipment_track . ' WHERE track_number = ?';
        $tracking_table = $connection->fetchAll($sql, $tracking);
        $orderId='';
        foreach ($tracking_table as $track){
            $orderId = $track['order_id'];
            break;
        }

        $url    = $this->getConfigData('url_ws');
        $client = new SoapClient($url, array("soap_version" => SOAP_1_1,"trace" => 1));
        //$orderId = Mage::getModel("sales/order")->getCollection()->getLastItem()->getIncrementId();
        $order = Mage::getModel('sales/order')->load($orderId);

        $shippingServiceCode = str_replace($this->_code . '_','', $order->getShippingMethod());

        $invoiceNumber='';
        if ($order->hasInvoices()) {
            foreach ($order->getInvoiceCollection() as $inv) {
                $invoiceNumber .= $inv->getIncrementId() . '|';
            }
        }
        
        $customer_id = $order->getCustomerId();
        $customer = Mage::getModel('customer/customer')->load($customer_id);
        $recipientDocument = $customer->getData('taxvat');

        $this->_log('Shipping Service Code: ' . $shippingServiceCode);
        $this->_log('CPF: ' . $recipientDocument);

        $service_param = array (
            'trackingRequest' => array(
                'Username' => $this->getConfigData('login'),
                'Password' => $this->getConfigData('password'),
                'TrackingNumber' => $tracking,
                'InvoiceNumber' => $invoiceNumber,
                'RecipientDocument' => $recipientDocument,
                'OrderNumber' => $order->getIncrementId(),
                'ShippingServiceCode' => $shippingServiceCode
            )
        );
        $this->_log(json_encode($service_param));
        $wsReturn = $client->__soapCall("GetTrackingInfo", array($service_param));

        $this->_log($client->__getLastRequest());
        $this->_log($client->__getLastResponse());

        if ($wsReturn == "") {
            throw new Exception("No XML returned [" . __LINE__ . "]");
        }

        if ($wsReturn !== false) {
            if(isset($wsReturn->GetTrackingInfoResult->TrackingEvents))
            {
                if(count($wsReturn->GetTrackingInfoResult->TrackingEvents->TrackingEvent)==1)
                    $trackingEventArray[0] = $wsReturn->GetTrackingInfoResult->TrackingEvents->TrackingEvent;
                else
                    $trackingEventArray = $wsReturn->GetTrackingInfoResult->TrackingEvents->TrackingEvent;


                $progress = array();
                foreach($trackingEventArray as $trackingEvent){
                    $this->_log("Percorrendo os eventos");

                    if(isset($trackingEvent->EventDateTime))
                    {
                        $datetime = explode(' ',$trackingEvent->EventDateTime);
                    }
                    else
                    {
                        $datetime = explode(' ',Zend_Date::now()->toString('dd-MM-yyyy HH:mm:ss'));
                    }
                    $locale   = new Zend_Locale('pt_BR');
                    $date     = '';
                    $date     = new Zend_Date($datetime[0], 'dd/MM/YYYY', $locale);

                    $trackingProgress = array(
                        'deliverydate'     => $date->toString('YYYY-MM-dd'),
                        'deliverytime'     => $datetime[1],
                        'deliverylocation' => $trackingEvent->EventLocation,
                        'activity'         => $trackingEvent->EventDescription
                    );
                    $progress[] = $trackingProgress;
                }

                $trackData                   = $progress[0];
                $trackData['progressdetail'] = $progress;

                $carrierTitle='';
                if(isset($wsReturn->GetTrackingInfoResult->ServiceDescrition))
                    $carrierTitle = $wsReturn->GetTrackingInfoResult->ServiceDescrition;

                $track = Mage::getModel('shipping/tracking_result_status');
                $track->setTracking($tracking)
                    ->setCarrierTitle($carrierTitle)
                    ->addData($trackData);

                $this->_result->append($track);
                return true;
            }
            else{
                $error = Mage::getModel('shipping/tracking_result_error');
                $this->_result->append($error);
                return false;
            }
        }
    }

    private function getSimpleProducts($items)
    {
        $j = 0;
        foreach ($items as $child)
        {
            $product_id = $child->getProductId ();
            $product = Mage::getModel ('catalog/product')->load ($product_id);
            $type_id = $product->getTypeId ();

            if (strcmp ($type_id, 'simple')) {
                //$this->_log("continue " . $product->getTypeId());
                continue;
            }

            $parentItem = $child->getParentItem ();
            if(!empty ($parentItem))
            {
                $this->_log('Parent:' . $parentItem->getId ());

                //VERIFICA SE O PRODUTO É "FILHO" DE UM BUNDLE
                $parent_ids = Mage::getModel('bundle/product_type')->getParentIdsByChild($child->getProductId());

                if(!empty($parent_ids))
                {
                    $product_bundle = Mage::getModel ('catalog/product')->load ($parent_ids[0]);

                    $selections = $product_bundle->getTypeInstance(true)
                        ->getSelectionsCollection($product_bundle->getTypeInstance(true)
                            ->getOptionsIds($product_bundle), $product_bundle);

                    foreach($selections as $selection){
                        if($product_id == $selection->getProductId())
                        {
                            $product = Mage::getModel ('catalog/product')->load ($selection->getProductId());
                            $qty = $selection->getSelectionQty();

                            $qty_bundle = 1;
                            foreach ($items as $child) {
                                if($parent_ids[0] == $child->getProductId ()){
                                    $qty_bundle= $this->_getQty ($child);
                                    break;
                                }
                            }

                            //$this->_log("qty_bundle: " . $qty_bundle);

                            $this->_simpleProducts [$j] = $product;
                            $this->_productsQty [$j] = (int) $qty * $qty_bundle;
                            $j = $j + 1;

                            //$this->_log(json_encode($product->getData()));
                            //$this->_log("Loop Selections qty: " . $qty);
                        }
                    }
                }
                else
                {
                    $qty = $this->_getQty ($child);

                    $product = Mage::getModel ('catalog/product')->load ($child->getProductId());

                    $this->_simpleProducts [$j] = $product;
                    $this->_productsQty [$j] = (int)$qty;
                    $j = $j + 1;
                }
            }
            else
            {
                $qty = $this->_getQty ($child);

                $product = Mage::getModel ('catalog/product')->load ($child->getProductId());

                $this->_simpleProducts [$j] = $product;
                $this->_productsQty [$j] = (int)$qty;
                $j = $j + 1;
            }
        }

        return $this;
    }

    private function _getQty ($item)
    {
        $qty = 0;

        $parentItem = $item->getParentItem ();
        $targetItem = !empty ($parentItem) && $parentItem->getId () > 0 ? $parentItem : $item;

        if ($targetItem instanceof Mage_Sales_Model_Quote_Item)
        {
            $qty = $targetItem->getQty ();
        }
        elseif ($targetItem instanceof Mage_Sales_Model_Order_Item)
        {
            $qty = $targetItem->getShipped () ? $targetItem->getShipped () : $targetItem->getQtyInvoiced ();
            if ($qty == 0) {
                $qty = $targetItem->getQtyOrdered();
            }
        }

        return $qty;
    }


}
