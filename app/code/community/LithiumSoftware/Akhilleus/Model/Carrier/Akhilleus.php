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
    protected $_recipientDocument	= NULL; // CPF / CNPJ do destinatario
    protected $_packageWeight		= NULL; // valor ajustado do pacote
    protected $_showDelivery        = NULL; // Determina exibição de prazo de entrega
    protected $_addDeliveryDays     = NULL; // Adiciona n dias ao prazo de entrega


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

        if ($wsReturn !== false) {

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

        try {

            $client = new SoapClient($url, array("soap_version" => SOAP_1_1,"trace" => 1));

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
            foreach($request->getAllItems() as $item){
                $shippingItem = new stdClass();
                $shippingItem->Weight = $item->getWeight();
                if ($this->getConfigFlag('use_default'))
                {
                    $shippingItem->Length = $this->getConfigData('default_length'); //16
                    $shippingItem->Width =  $this->getConfigData('default_width'); //11
                    $shippingItem->Height = $this->getConfigData('default_height'); //2
                }
                else{
                    $shippingItem->Length = $item->getProduct()->getData('volume_comprimento');
                    $shippingItem->Height = $item->getProduct()->getData('volume_altura');
                    $shippingItem->Width = $item->getProduct()->getData('volume_largura');
                }
                $shippingItem->Diameter = 0;
                $shippingItem->SKU = $item->getProduct()->getSku();

                $categoryIds = $item->getProduct()->getCategoryIds();
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

                if($item->getProduct()->getData('fragile'))
                    $shippingItem->isFragile = $item->getProduct()->getData('fragile');
                else
                    $shippingItem->isFragile=false;

                $shippingItemArray[$count] = $shippingItem;
                $count++;
            }

            $service_param = array (
                'quoteRequest' => array(
                    'Username' => $this->getConfigData('login'),
                    'Password' => $this->getConfigData('password'),
                    'SellerCEP' => $this->_from,
                    'RecipientCEP' => $this->_to,
                    'RecipientDocument' => $this->_recipientDocument,
                    'ShipmentInvoiceValue' => $this->_value,
                    'ShippingItemArray' => $shippingItemArray
                )
            );

            /*
            $this->_log('Chamada do webservices - ');
            foreach($service_param as $key => $value)
            {
                $this->_log($key." service_param ". $value);
            }

            foreach($shippingItemArray as $shippingItem)
            {
                $this->_log("Peso ". $shippingItem->Weight);
                $this->_log("Comprimento ". $shippingItem->Length);
                $this->_log("Altura ". $shippingItem->Height);
                $this->_log("Largura ". $shippingItem->Width);
                $this->_log("Diametro ". $shippingItem->Diameter);
                $this->_log("SKU ". $shippingItem->SKU);
                $this->_log("Categoria ". $shippingItem->Category);
                $this->_log("Fragil ". $shippingItem->isFragile);
            }
            */

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
    protected function _getCategories(){

    }


    /**
     * Get Webservices return
     *
     * @return bool|SimpleXMLElement[]
     */
    protected function _getWebServicesReturn(Mage_Shipping_Model_Rate_Request $request)
    {
        $url    = $this->getConfigData('url_ws');

        try {

            $client = new SoapClient($url, array("soap_version" => SOAP_1_1,"trace" => 1));

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

            $this->_log('altura ' . $this->_height);
            $this->_log('largura ' . $this->_width);
            $this->_log('comprimento ' . $this->_length);

            $service_param = array (
                'userName' => $this->getConfigData('login'),
                'password' => $this->getConfigData('password'),
                'sellerCEP' => $this->_from,
                'recipientCEP' => $this->_to,
                'shipmentInvoiceValue' => $this->_value,
                'shipmentWeight' => $this->_weight,
                'shipmentLength' => $this->_length,
                'shipmentHeight' => $this->_height,
                'shipmentWidth' => $this->_width,
                'shipmentDiameter' => $this->_diameter
            );

            $this->_log('Chamada do webservices - ' .
                'Origem: ' .$this->_from . 'Destino: ' .$this->_to . 'Peso: ' . $this->_weight . 'ValorDeclarado: ' . $this->_value .
                'Tamanho: ' .$this->_length . 'Altura: ' . $this->_height . 'Largura: ' . $this->_width . 'Diametro: ' . $this->_diameter);

            $content = $client->__soapCall("Rate", array($service_param));

            $this->_log($client->__getLastRequest());

            if ($content == "") {
                throw new Exception("No XML returned [" . __LINE__ . "]");
            }

            return $content;

        } catch (Exception $e) {
            //URL Error
            $this->_throwError('urlerror', 'URL Error - ' . $e->getMessage(), __LINE__);
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
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    foreach ($item->getChildren() as $child) {
                        if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                            $product_id = $child->getProductId();
                            $productObj = Mage::getModel('catalog/product')->load($product_id);

                            //verificar se a propriedade Leadtime existe
                            if($productObj->offsetExists('leadtime'))
                                $productLeadTime = $productObj->getleadtime();
                        }
                    }
                } else {
                    $productId = $item->getProductId();
                    $productObj = Mage::getModel('catalog/product')->load($productId);

                    //verificar se a propriedade Leadtime existe
                    if($productObj->offsetExists('leadtime'))
                        $productLeadTime = $productObj->getleadtime();
                }

                if($cartLeadTime < $productLeadTime)
                    $cartLeadTime=$productLeadTime;
            }
        }

        $this->_log('Leadtime: ' . $cartLeadTime);

        if ($this->_showDelivery && $delivery > 0){
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
        $this->_to = $this->_formatZip($request->getDestPostcode());

        if(!$this->_from){
            $this->_log('Erro com CEP de origem');
            return false;
        }

        if(!$this->_to){
            $this->_log('Erro com CEP de destino');
            $this->_throwError('zipcodeerror', 'CEP Inválido', __LINE__);
            return false;
        }

        return true;
    }

    /**
     * Verifica se o país está dentro da área atendida
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return boolean
     */
    protected function _checkCountry(Mage_Shipping_Model_Rate_Request $request) {
        $from = Mage::getStoreConfig('shipping/origin/country_id', $this->getStore());
        $to = $request->getDestCountryId();
        if ($from != "BR" || $to != "BR"){
            $this->_log('Fora da área de atendimento');
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
}
