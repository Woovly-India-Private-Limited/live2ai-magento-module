<?php
namespace Live2\Live2\Observer;

use Live2\Live2\Helper\Live2ApiCall;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\StoreManagerInterface;

class ProductSaveAfter implements ObserverInterface  {

    protected $logger;
    protected $productRepository;
    protected $searchCriteriaBuilder;
    protected $storeManager;
    protected $live2Api;

    public function __construct(
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StoreManagerInterface $storeManager,
        Live2ApiCall $live2Api
    ) {
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->live2Api = $live2Api;
    }

    public function execute( Observer $observer ) {

        try {
            $product = $observer->getEvent()->getProduct();
            $storeId = $this->storeManager->getStore()->getId();
            $baseUrlMedia = $this->storeManager->getStore( $storeId )->getBaseUrl( \Magento\Framework\UrlInterface::URL_TYPE_MEDIA );
            $storeUrl = $this->storeManager->getStore( $storeId )->getBaseUrl( \Magento\Framework\UrlInterface::URL_TYPE_WEB );
            $live2Details = $this->live2Api->getAccessToken();
            $storeDetails = $this->live2Api->getStoreDetails();
            $this->logger->info( 'outputDataLIVE2' . json_encode( $storeDetails ) );
            $searchCriteria = $this->searchCriteriaBuilder->addFilter( 'sku', [ $product->getSku() ], 'in' )->create();
            $productList = $this->productRepository->getList( $searchCriteria );
            $productData = [];
            foreach ( $productList->getItems() as $data ) {
                $productData[] = $data->getData();
            }
            $productDataArray = [
                'shopUrl' => $storeDetails[ 'storeUrl' ],
                'currency' => '',
                'desc' => '',
                'shopName' => $storeDetails[ 'storeUrl' ],
                'baseUrl' => $storeDetails[ 'baseUrlMedia' ].'catalog/product',
                'products' => $productData
            ];
            $url = $live2Details[ 'live2_url' ].'/api/live2/stores/magento';
            $token = $live2Details[ 'token' ];

            $ch = curl_init( $url );
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $productDataArray ) );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Authorization: ' . $token, 'Content-Type: application/json', 'Store-Type: magento' ] );
            $result = curl_exec( $ch );
            $this->logger->info( 'outputDataLIVE2' . json_encode( $result ) );
        } catch ( \Throwable $e ) {
            $this->logger->critical( 'outputDataLIVE2', [ 'error' => $e->getMessage() ] );
        }
    }
}
