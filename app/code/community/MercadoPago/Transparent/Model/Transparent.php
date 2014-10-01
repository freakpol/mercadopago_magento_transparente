<?php
/**
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL).
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
*
* @category   	Payment Gateway
* @package    	MercadoPago
* @author      	Gabriel Matsuoka (gabriel.matsuoka@gmail.com)
* @copyright  	Copyright (c) MercadoPago [http://www.mercadopago.com]
* @license    	http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

require_once(Mage::getBaseDir('lib') . '/mercadopago/mercadopago.php');

class MercadoPago_Transparent_Model_Transparent extends Mage_Payment_Model_Method_Abstract{
    
    //configura o lugar do arquivo para listar meios de pagamento
    protected $_formBlockType = 'mercadopago_transparent/form';
    
    protected $_code = 'mercadopago_transparent';

    protected $_isGateway                   = true;
    protected $_canOrder                    = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canUseInternal              = true;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = true;
    protected $_canFetchTransactionInfo     = true;
    protected $_canCreateBillingAgreement   = true;
    protected $_canReviewPayment            = true;

    protected function _construct(){
        $this->_init('mercadopago_transparent/transparent');
    }
    
    public function assignData($data){
        
        // route /checkout/onepage/savePayment
        if(!($data instanceof Varien_Object)){
            $data = new Varien_Object($data);
        }
        
        //get array info
        $info_form = $data->getData();
        
        $info = $this->getInfoInstance();
        $info->setAdditionalInformation('card_token_id', $info_form['card_token_id']);
        $info->setAdditionalInformation('payment_method', $info_form['payment_method']);
        $info->setAdditionalInformation('installments', $info_form['installments']);
        $info->setAdditionalInformation('doc_number', $info_form['doc_number']);
        
        //verifica se o pagamento não é boleto, caso seja não tem card_token_id
        if($info_form['payment_method'] != "bolbradesco" && $info_form['card_token_id'] == ""):
	    Mage::throwException('Corrija os dados do formulario de pagamento para prosseguir.');
	    return false;
	endif;
	
	
        return $this;
    }
    
    public function getOrderPlaceRedirectUrl() {
        
        // requisicao vem da pagina de finalizacao de pedido
        return Mage::getUrl('mercadopago_transparent/post', array('_secure' => true));
    
    }


    public function postPago(){
        
        $model = $this; //Mage::getModel('mercadopago_transparent/transparent');
        
        //seta sdk php mercadopago
        $this->client_id = $model->getConfigData('client_id');
        $this->client_secret = $model->getConfigData('client_secret');
        $mp = new MP($this->client_id, $this->client_secret);
        
	$accessToken = $mp->get_access_token();
	
        //pega a order atual
        $orderIncrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $model = Mage::getModel('catalog/product');
    
         
        //pega payment dentro da order para pegar as informacoes adicionadas pela funcao assignData()
	$payment = $order->getPayment();
        
        //pega valor total da compra
        $item_price = $order->getBaseGrandTotal();
        if (!$item_price) {
            $item_price = $order->getBasePrice() + $order->getBaseShippingAmount();
        }

        //pega o valor total da compra somando o frete
        $item_price = number_format($item_price, 2, '.', '');
        
        //setta informaçnoes
        $arr = array();
        $arr['external_reference'] = $orderIncrementId;
        $arr['amount'] = (float) $item_price;
        $arr['reason'] = "Pedido #12124 realizado na loja localhost";
        $arr['currency_id'] = "BRL";
        $arr['installments'] = (int) $payment['additional_information']['installments'];
        $arr['payment_method_id'] = $payment['additional_information']['payment_method'];
        $arr['payer_email'] = htmlentities($customer->getEmail());
        
        if($payment['additional_information']['card_token_id'] != ""){
	    $arr['card_token_id'] = $payment['additional_information']['card_token_id'];
	}
        
        //monta array de produtos 
        $arr['items'] = array();
        foreach ($order->getAllVisibleItems() as $item) {

            if (strpos($item->getSku(), '-') !== false) {
                $skus = explode("-", $item->getSku());
                $prod = $model->loadByAttribute('sku', $skus[0]);
            } else {
                $prod = $model->loadByAttribute('sku', $item->getSku());
            }

            //get methods and each find getImage
            $imagem = "";
            $methods = get_class_methods($prod);
            foreach($methods as $method):
                if($method == "getImageUrl"):
                    $imagem = $prod->getImageUrl();
                endif;
            endforeach;
            
            $arr['items'][] = array(
                "id" => $item->getSku(),
                "title" => $item->getName(),
                "description" => $item->getName(),
                "picture_url" => $imagem,
                "category_id" => $this->getConfigData('category_id'),
                "quantity" => (int) number_format($item->getQtyOrdered(), 0, '.', ''),
                "unit_price" => (float) number_format($prod->getPrice(), 2, '.', '')
            );
            
        }
        
        //pega dados de envio
        if(method_exists($order->getShippingAddress(), "getData")){
            $shipping = $order->getShippingAddress()->getData();
            $arr['shipments']['receiver_address'] = array(
                "receiver_address" => array(
                    "floor" => "-",
                    "zip_code" => $shipping['postcode'],
                    "street_name" => $shipping['street'] . " - " . $shipping['city'] . " - " . $shipping['country_id'],
                    "apartment" => "-",
                    "street_number" => "-"
                )
            );
            $arr['customer']['phone'] = array(
                "area_code" => "-",
                "number" => $shipping['telephone']
            );
        }
        
        //formata a data do usuario para o padrao do mercado pago YYYY-MM-DDTHH:MM:SS
        $date_creation_user = date('Y-m-d',$customer->getCreatedAtTimestamp()) . "T" . date('H:i:s',$customer->getCreatedAtTimestamp());
        
        //pega informaçoes de cadastro do usuario
        $billing_address = $order->getBillingAddress();
        $billing_address = $billing_address->getData();
        
        //set informaçoes do usuario
        $arr['customer']['registration_date'] = $date_creation_user;
        $arr['customer']['email'] = htmlentities($customer->getEmail());
        $arr['customer']['first_name'] = htmlentities($customer->getFirstname());
        $arr['customer']['last_name'] = htmlentities($customer->getLastname());
        
        //set o documento do usuario
	if($payment['additional_information']['doc_number'] != ""){
	    $arr['customer']['identification'] = array(
		"type" => "CPF",
		"number" => $payment['additional_information']['doc_number']
	    );
	}
        
        //set endereco do usuario
        $arr['customer']['address'] = array(
            "zip_code" => $billing_address['postcode'],
            "street_name" => $billing_address['street'] . " - " . $billing_address['city'] . " - " . $billing_address['country_id'],
            "street_number" => "-"
        );
        
	//define a url de notificacao 
	$arr['notification_url'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK,true) . "/mercadopago_transparent/notificacao";
	
        return $mp->create_custon_payment($arr);
        
    }
    
    
    public function getPayment($payment_id){
	$model = $this;
	$this->client_id = $model->getConfigData('client_id');
        $this->client_secret = $model->getConfigData('client_secret');
        $mp = new MP($this->client_id, $this->client_secret);
	return $mp->get_payment($payment_id);
    }
    
}

?>
