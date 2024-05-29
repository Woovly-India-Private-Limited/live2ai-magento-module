<?php

namespace  Live2\Live2\Helper;

use Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Live2ApiCall
{
    protected $logger;
    protected $storeManager;
 

    public function __construct(
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,

    ) {
        $this->logger = $logger;
        $this->storeManager = $storeManager;

    }
  

    public  function getAccessToken()
    {   
        $result=[];
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scope = $objectManager->get( ScopeConfigInterface::class );

        $live2_url =  $scope->getValue( 'live2_token/access_token/url' )?$scope->getValue( 'live2_token/access_token/url' ):"https://alpha.live2.ai";
        $token = $scope->getValue( 'live2_token/access_token/token' );
        $result['live2_url']=$live2_url;
        $result['token']='Bearer '.$token;
        return $result ;
    }

    public  function getStoreDetails()
    {   
        $result=[];
        $storeId = $this->storeManager->getStore()->getId();
        $baseUrlMedia = $this->storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $storeUrl = $this->storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $result['baseUrlMedia']=$baseUrlMedia;
        $result['storeUrl']=$storeUrl;
        return $result ;
    }


    private static function  remove_http($url)
    {
        $disallowed = array('http://', 'https://');
        foreach ($disallowed as $d) {
            if (strpos($url, $d) === 0) {
                return str_replace($d, '', $url);
            }
        }
        return $url;
    }
}

