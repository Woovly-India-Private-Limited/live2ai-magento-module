<?php

namespace Live2\Live2\Observer;

use Live2\Live2\Helper\Live2ApiCall;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class CatalogProductImportBunchSaveAfter implements ObserverInterface
{
    protected $logger;
    protected $storeManager;
    protected $productRepository;
    protected $searchCriteriaBuilder;
    protected $live2Api;

    public function __construct(
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Live2ApiCall $live2Api
    ) {
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->live2Api=$live2Api;
    }

    public function execute(Observer $observer)
    {

        try {
            $bunch = $observer->getBunch();
            $SKUs = $this->extractSKUs($bunch);
            $storeId = $this->storeManager->getStore()->getId();
            $baseUrlMedia = $this->storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            $storeUrl = $this->storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
            $live2Details=$this->live2Api->getAccessToken();
            $storeDetails=$this->live2Api->getStoreDetails();
            $url=$live2Details['live2_url'];
            $access_token=$live2Details['token'];
            $productDataArray = $this->fetchProductData($SKUs);
            $data = [
                'product' => $productDataArray,
                'image_url' => $storeDetails['storeUrl'],
                'store_url' => $storeDetails['baseUrlMedia'] . 'catalog/product'
            ];

            $jsonFile = BP . '/var/product_live2.json';
            file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

            $response = $this->uploadFileToApi($url,$jsonFile, $access_token);
            $this->logger->info('outputDataLIVE2' . json_encode($response));

            $response = json_decode($response, true);
            $response['shopUrl'] = $storeUrl;
            $this->updateBulkData($url,$response, $access_token);
        } catch (\Throwable $e) {
            $this->logger->critical('outputDataLIVE2' . json_encode($e->getMessage()));
        }
    }

    protected function extractSKUs($bunch)
    {
        $SKUs = [];
        foreach ($bunch as $item) {
            if (isset($item['sku'])) {
                $SKUs[] = $item['sku'];
            }
        }
        return $SKUs;
    }

    protected function fetchProductData($SKUs)
    {
        $searchCriteria = $this->searchCriteriaBuilder->setPageSize(5)->addFilter('sku', $SKUs, 'in')->create();
        $productList = $this->productRepository->getList($searchCriteria);
        $productDataArray = [];

        foreach ($productList->getItems() as $product) {
            $productDataArray[] = $product->getData();
        }
        return $productDataArray;
    }

    protected function uploadFileToApi($url,$jsonFile, $token)
    {
        $url = $url.'/api/live2/file-upload/magento';
        $headers = [
            'Authorization: ' . $token,
        ];
        $postData = [
            'file' => new \CURLFile($jsonFile, 'application/json'),
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    protected function updateBulkData($url,$response, $token)
    {
        $apiUrl = $url.'/api/live2/stores/magento/bulk-update';
        $headers = [
            'Content-Type: application/json',
            'Authorization: ' . $token,
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($response));
        $responseOutput = curl_exec($ch);
        curl_close($ch);

        $this->logger->info('outputDataLIVE2' . json_encode($responseOutput));
    }
}




