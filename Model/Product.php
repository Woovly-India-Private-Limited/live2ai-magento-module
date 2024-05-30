<?php
namespace Live2\Live2\Model;

use Live2\Live2\Helper\Live2ApiCall;
use Live2\Live2\Api\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class Product implements ProductInterface
{
    protected $productRepository;
    protected $request;
    protected $storeManager;
    protected $scopeConfig;
    protected $live2Api;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Live2ApiCall $live2Api
    ) {
        $this->productRepository = $productRepository;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->live2Api=$live2Api;
    }

    public function getProducts(SearchCriteriaInterface $searchCriteria)
    {
        $headers = $this->request->getHeader("Authorization");
        $baseUrlMedia = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $storeUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $token = $this->scopeConfig->getValue('live2_token/access_token/token');
        $products=$this->productRepository->getList($searchCriteria);
        $live2Details=$this->live2Api->getAccessToken();
        $storeDetails=$this->live2Api->getStoreDetails();
        $productDataArray = [];
        foreach ($products->getItems() as $product) {
            $productDataArray[] = $product->getData();
        }
        
        $result = [
            [
                "products" => $productDataArray,
                "store_url" => $storeDetails['storeUrl'],
                "image_url" => $storeDetails['baseUrlMedia'].'catalog/product'
            ]
        ];
        return $result;
    }
}
