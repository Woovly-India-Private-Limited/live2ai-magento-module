<?php
namespace Live2\Live2\Model;

use Live2\Live2\Helper\Live2ApiCall;
use Live2\Live2\Api\CategoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\CategoryManagement as CategoryTree;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class Category implements CategoryInterface
{
    protected $categoryRepository;
    protected $categoryTree;
    protected $categoryCollectionFactory;
    protected $request;
    protected $storeManager;
    protected $scopeConfig;
    protected $live2Api;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        CategoryTree $categoryTree,
        CollectionFactory $categoryCollectionFactory,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Live2ApiCall $live2Api
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->categoryTree = $categoryTree;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->live2Api=$live2Api;
    }

    /**
     * Retrieve category tree
     *
     * @param int|null $rootCategoryId
     * @param int|null $depth
     * @return mixed
     */
    public function getCategoryTree($rootCategoryId = null, $depth = null)
    {   

        $headers = $this->request->getHeader("Authorization");
        $baseUrlMedia = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $storeUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $token = $this->scopeConfig->getValue('live2_token/access_token/token');
        $categoryData = [];
        $categoryCollection = $this->categoryCollectionFactory->create()->addFieldToFilter('parent_id', ['eq' => $rootCategoryId ?? 1])->addAttributeToSelect('*')->setPageSize($depth ?? 10);
        $live2Details=$this->live2Api->getAccessToken();
        $storeDetails=$this->live2Api->getStoreDetails();
        foreach ($categoryCollection as $category) {
            $categoryData[] = $category->getData();
        }
        $result=[];
        $result=[[
            "category_Data"=>$categoryData,
            "store_url" => $storeDetails['storeUrl'],
            "image_url" => $storeDetails['baseUrlMedia']
        ]];

        return $result;
    }
}
