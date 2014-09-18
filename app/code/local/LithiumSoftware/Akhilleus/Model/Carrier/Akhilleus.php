<?php
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
    protected $_packageWeight		= NULL; // valor ajustado do pacote


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
        $wsReturn = $this->_getWebServicesReturn($request);

        if ($wsReturn !== false) {

            // Check if exist return from Webservices
            $existReturn = false;

            foreach($wsReturn->RateResult->ShippingSevicesArray->ShippingSevices as $servicos){

                // Get Webservices error

                $this->_log("Percorrendo os serviços retornados");

                $shippingPrice    = floatval(str_replace(",", ".", (string) $servicos->ShippingPrice));
                $delivery = (int) $servicos->DeliveryTime;
                $shipping_method = $servicos->ServiceCode;
                $shipping_method_name = $servicos->ServiceDescription;

                if ($shippingPrice <= 0) {
                    continue;
                }

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
    protected function _getWebServicesReturn(Mage_Shipping_Model_Rate_Request $request)
    {

        //$filename = $this->getConfigData('url_ws_lai');
        $url    = 'http://services.lithiumsoftware.com.br/logistics/ShippingQuoteWS.asmx?wsdl';

        try {

            $client = new SoapClient($url, array("soap_version" => SOAP_1_1,"trace" => 1));

            /*
             *          $client->setParameterGet('userName', $this->getConfigData('login'));
                        $client->setParameterGet('password', $this->getConfigData('password'));

             */
            $this->_log('ConfigData : ' . $this->getConfigData('password'));

            /*
            // TODO: Está pegando as dimensões somente do primeiro produto do carrinho!
            foreach($request->getAllItems() as $item){
                $this->_length = $item->getLength();
                $this->_width = $item->getWidth();
                $this->_height = $item->getHeight();
                $this->_diameter = 0;

                break;
            }
            */

            $this->_length = 16;
            $this->_width = 11;
            $this->_height = 2;
            $this->_diameter = 0;

            $service_param = array (
                'userName' => "mania",
                'password' => ".M@n1@",
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


    /*
        TODO: Retirar texto fixo! Definir LeadTime
    */
    protected function _appendShippingMethod($shipping_method, $shippingPrice = 0, $delivery = 0, $shipping_method_name, Mage_Shipping_Model_Rate_Request $request){
        $method = Mage::getModel('shipping/rate_result_method');
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->_title);

        $method->setMethod($shipping_method);

        /*
        //Obter o maior LEADTIME dos produtos do request
        $cartLeadTime = 0;
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
                            //$productObj->offsetExists('Leadtime');
                            $productLeadTime = $productObj->getLeadtime();
                        }
                    }
                } else {
                    $productId = $item->getProductId();
                    $product = Mage::getModel('catalog/product')->load($productId);
                    $productLeadTime = $product->getLeadtime();
                }

                if($cartLeadTime < $productLeadTime)
                    $cartLeadTime=$productLeadTime;
            }
        }
        */

        if($delivery  <= 0)
            $method->setMethodTitle($shipping_method_name);
        else
            $method->setMethodTitle(sprintf('%s - Em média %d dia(s)',$shipping_method_name, $delivery));

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);

        $this->_result->append($method);
    }

    protected function _init(Mage_Shipping_Model_Rate_Request $request){
        if (!$this->isActive()) {
            $this->_log('Módulo Desabilitado');
            return false;
        }

        //if (!$this->_checkCountry($request)) return false;
        if (!$this->_checkZipCode($request)) return false;

        $this->_title = $this->getConfigData('title');
        $this->_weightType = $this->getConfigData('weight_type');
        $this->_result = Mage::getModel('shipping/rate_result');

        $this->_value = $request->getBaseCurrency()->convert($request->getPackageValue(), $request->getPackageCurrency());

        $this->_updatePackageWeight($request);

        $this->_weight = $this->_fixWeight($request->getPackageWeight());
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

        if(!preg_match("/^[0-9]{7,8}$/", $new)){
            return false;
        } elseif(preg_match("/^[0-9]{7}$/", $new)){ // tratamento para CEP com 7 d�gitos
            $new = "0" . $new;
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
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array($this->_code=>$this->getConfigData('name'));
    }
}
