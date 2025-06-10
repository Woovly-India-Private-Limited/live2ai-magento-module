<?php
namespace Live2\Live2\Observer;

use Live2\Live2\Helper\Live2ApiCall;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;

class ProductSaveAfter implements ObserverInterface  {

    protected $logger;
    protected $productRepository;
    protected $searchCriteriaBuilder;
    protected $storeManager;
    protected $live2Api;
    protected $stockRegistry;

    public function __construct(
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StoreManagerInterface $storeManager,
        Live2ApiCall $live2Api, 
        StockRegistryInterface $stockRegistry
    ) {
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->live2Api = $live2Api;
        $this->stockRegistry = $stockRegistry;
    }

    public function execute( Observer $observer ) {

        try {

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $configurableProductModel = $objectManager->get(\Magento\ConfigurableProduct\Model\Product\Type\Configurable::class);
            $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
            $store = $storeManager->getDefaultStoreView();
            $storeId = $store->getId();
            $baseCurrency = $store->getBaseCurrency();

            $product = $observer->getEvent()->getProduct();
            $storeId = $this->storeManager->getStore()->getId();
            $baseUrlMedia = $this->storeManager->getStore( $storeId )->getBaseUrl( \Magento\Framework\UrlInterface::URL_TYPE_MEDIA );
            $storeUrl = $this->storeManager->getStore( $storeId )->getBaseUrl( \Magento\Framework\UrlInterface::URL_TYPE_WEB );
            $live2Details = $this->live2Api->getAccessToken();
            $storeDetails = $this->live2Api->getStoreDetails();
            $allowedCurrencies = $storeDetails['allowedCurrencies'];
            $this->logger->info( 'outputDataLIVE2' . json_encode( $storeDetails ) );
            $searchCriteria = $this->searchCriteriaBuilder->addFilter( 'sku', [ $product->getSku() ], 'in' )->addFilter('store_id', $storeId, 'eq')->create();
            $productList = $this->productRepository->getList( $searchCriteria );
            $productData = [];
            foreach ( $productList->getItems() as $data ) {
                $product->setStoreId($storeId);
                $typeInstance = $data->getTypeInstance();

                $stockItem = $this->stockRegistry->getStockItemBySku($data->getSku());
                $isInStock = $stockItem->getIsInStock(); // Check if the product is in stock
                $stockQty = $stockItem->getQty() > 0 ? true : false;

                $products = $data->getData();
                $products['quantity_and_stock_status'] = $isInStock && $stockQty ? true : false;

                $variants = [];
                $attribute = [];
                $price = 0;
                
                // Check if the product is configurable
                if ($product->getTypeId() === 'configurable') {
                    $childProducts = $configurableProductModel->getUsedProducts($product);
                
                    $attribute = $typeInstance->getConfigurableAttributesAsArray($product);

                    $price = 0;

                    foreach ($childProducts as $childProduct) {
                        $childStockItem = $this->stockRegistry->getStockItemBySku($childProduct->getSku());
                        $childIsInStock = $childStockItem->getIsInStock();
                        $childStockQty = $childStockItem->getQty() > 0 ? true : false;

                        $price = $price !== 0 ? $price : $childProduct->getPrice();

                        $variant = $childProduct->getData();
                        $variant['quantity_and_stock_status'] = $childIsInStock && $childStockQty ? true : false;

                        $variantAttributes = [];
                        foreach ($attribute as $attr) {
                            $attrCode = $attr['attribute_code'];
                            $optionValue = $childProduct->getAttributeText($attrCode);
                        
                            $variantAttributes[] = [
                                'key' => $attrCode,
                                'value' => $optionValue
                            ];
                        }

                        $convertedPrices = [];
                        foreach ($allowedCurrencies as $currencyCode) {
                            $convertedPrices[$currencyCode] = $baseCurrency->convert($childProduct->getPrice(), $currencyCode);
                        }

                        $variant['attributes'] = $variantAttributes;
                        $variant['prices'] = $convertedPrices;
                        $variants[] = $variant;
                    }
                    $products['price'] = $price;
                }

                $finalPrice = $price !== 0 ? $price : $product->getPrice();
                $convertedProductPrices = [];
                foreach ($allowedCurrencies as $currencyCode) {
                    $convertedProductPrices[$currencyCode] = $baseCurrency->convert($finalPrice, $currencyCode);
                }
                $products['prices'] = $convertedProductPrices;

                $products['variants'] = $variants;
                $products['options'] = $attribute;

                $productData[] = $products;
            }
            $productDataArray = [
                'shopUrl' => $storeDetails[ 'storeUrl' ],
                'currency' => $storeDetails['currency'],
                'desc' => '',
                'shopName' => $storeDetails[ 'name' ],
                'baseUrl' => $storeDetails[ 'baseUrlMedia' ].'catalog/product',
                'products' => $productData
            ];
            $url = $live2Details[ 'live2_url' ].'/api/live2-public/stores/magento';
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
