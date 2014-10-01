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

class MercadoPago_Transparent_NotificacaoController extends Mage_Core_Controller_Front_Action{
    
    protected $_return = null;
    protected $_order = null;
    protected $_order_id = null;
    protected $_mpcartid = null;
    protected $_sendemail = false;
    protected $_hash = null;
    
    public function indexAction(){
	$params = $this->getRequest()->getParams();
	if (isset($params['id']) && isset($params['topic'])){
	    $model = Mage::getModel('mercadopago_transparent/transparent');
	    $response = $model->getPayment($params['id']);
	    
	    if($response['status'] == 200 || $response['status'] == 201):
		$payment = $response['response']['collection'];
		$order = Mage::getModel('sales/order')->loadByIncrementId($payment["external_reference"]);
		
	
		//adiciona informações sobre o comprador na order	
		if ($payment['payer']['first_name'])
		    $order->setCustomerFirstname($payment['payer']['first_name']);
		if ($payment['payer']['last_name'])
		    $order->setCustomerLastname($payment['payer']['last_name']);
		if ($payment['payer']['email'])
		    $order->setCustomerEmail($payment['payer']['email']);
		
		$order->save();
		
		switch ( $payment['status']) {
    
		    case 'approved':
			// Geração não automática de invoice    
			$message = 'MercadoPago automatically confirmed payment for this order.';           
			$status = $model->getConfigData('order_status_approved');
			$order->addStatusToHistory($status,$message,true);
			$order->sendOrderUpdateEmail(true, $message); 
			break;
		    case 'refunded':
			$status = $model->getConfigData('order_status_refunded');
			$message = 'Payment was refound. The vendor returned the values ​​of this operation.';	
			$order->cancel();
			$order->addStatusToHistory($status, $message);
			$order->sendOrderUpdateEmail(true, $message);
			break;
		    case 'pending':
			$status = $model->getConfigData('order_status_in_process');
			$message = 'The user has not completed the payment process yet.';
			$order->addStatusToHistory($status, $message);
			$order->sendOrderUpdateEmail(true, $message);
			break;
		    case 'in_process':
			$status = $model->getConfigData('order_status_in_process');
			$message = 'The payment is been analysing.';
			$order->addStatusToHistory($status, $message);
			$order->sendOrderUpdateEmail(true, $message);
			break;
		    case 'in_mediation':
			$status = $model->getConfigData('order_status_in_mediation');
			$message = 'It started a dispute for the payment.';
			$order->addStatusToHistory($status, $message);
			$order->sendOrderUpdateEmail(true, $message);
			break;
		    case 'cancelled':              
			$status = $model->getConfigData('order_status_cancelled');
			$message = 'Payment was canceled.';
			$order->addStatusToHistory($status, $message);
			$order->sendOrderUpdateEmail(true, $message);
			$order->cancel();
			break;
		    case 'rejected':              
			$status = $model->getConfigData('order_status_rejected');
			$message = 'Payment was Reject.';
			$order->addStatusToHistory($status, $message);
			$order->sendOrderUpdateEmail(true, $message);
			break;
		    default:
			$status = $model->getConfigData('order_status_in_process');
			$message = "";    
			$order->addStatusToHistory($status, $message);
			$order->sendOrderUpdateEmail(true, $message);
		}
		
		$order->save();
		echo "Success Update";
		
	    else:
	    
		//caso de algum erro na consulta da ipn
		header(' ', true, 404);
		exit;
	    endif;
	}
    
    } 

}
