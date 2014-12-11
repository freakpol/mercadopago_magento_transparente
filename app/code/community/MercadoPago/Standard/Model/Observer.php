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

class MercadoPago_Standard_Model_Observer{
    
    public function checkAndValidData($observer){

        $this->checkBanner('mercadopago_transparentticket', 'transparent');
        $this->checkBanner('mercadopago_transparent', 'transparent');
        $this->checkBanner('mercadopago_standard', 'checkout');
    
    }
    
    
    function checkBanner($model_path, $file){
        //pega o model/file
        $model = Mage::getModel($model_path . '/' . $file);
        
        //pega o banner do tipo de checkout
        $banner = $model->getConfigData('banner_checkout');
        
        //pega o pais configurado
        $country = Mage::getStoreConfig('payment/mercadopago_configuration/country');
        
        if($model_path == "mercadopago_transparentticket"){
            if( $country != 'mlb' && $banner == "http://imgmp.mlstatic.com/org-img/MLB/MP/BANNERS/2014/230x60.png"){
                $this->setNewBanner($model_path, $country);
            }elseif($country != 'mla' && $banner == "https://a248.e.akamai.net/secure.mlstatic.com/components/resources/mp/css/assets/desktop-logo-mercadopago.png"){
                $this->setNewBanner($model_path, $country);
            }    
        }else{
            //verifica se o banner salvo condiz com o pais
            if( $country != 'mlb' && $banner == "http://imgmp.mlstatic.com/org-img/MLB/MP/BANNERS/tipo2_468X60.jpg"){
                $this->setNewBanner($model_path, $country);
            }elseif($country != 'mla' && $banner == "http://imgmp.mlstatic.com/org-img/banners/ar/medios/468X60.jpg"){
                $this->setNewBanner($model_path, $country);
            }    
        }
        
        
    }
    public function setNewBanner($model, $country){
        //instacia model do core para atualiza os dados no banco de dados
        //no model n‹o existe fun‹o para fazer isso, por esse motivo foi feito assim
        $core = new Mage_Core_Model_Resource_Setup('core_setup');
        $core->setConfigData('payment/' . $model . '/banner_checkout', $this->getBannerByCountry($model, $country));
    }
    
    public function getBannerByCountry($model, $country){
        $banner = "";
        
        //caso seja boleto o banner Ž diferente
        if($model == "mercadopago_transparentticket"){
            switch($country){
                case 'mlb':
                    $banner = "http://imgmp.mlstatic.com/org-img/MLB/MP/BANNERS/2014/230x60.png";
                    break;
                case 'mla':
                    $banner = "https://a248.e.akamai.net/secure.mlstatic.com/components/resources/mp/css/assets/desktop-logo-mercadopago.png";
                    break;
                default:
                    $banner = "https://a248.e.akamai.net/secure.mlstatic.com/components/resources/mp/css/assets/desktop-logo-mercadopago.png";
                    break;
            }   
        }else{
            switch($country){
                case 'mlb':
                    $banner = "http://imgmp.mlstatic.com/org-img/MLB/MP/BANNERS/tipo2_468X60.jpg";
                    break;
                case 'mla':
                    $banner = "http://imgmp.mlstatic.com/org-img/banners/ar/medios/468X60.jpg";
                    break;
                default:
                    $banner = "https://a248.e.akamai.net/secure.mlstatic.com/components/resources/mp/css/assets/desktop-logo-mercadopago.png";
                    break;
            }   
        }
        
        
        return $banner;
    }
    
}