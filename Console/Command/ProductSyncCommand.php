<?php

namespace Live2\Live2\Console\Command;

use Live2\Live2\Helper\Live2ApiCall;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ProductSyncCommand extends Command
{
    protected $productCollectionFactory;
    protected $storeManager;
    protected $productRepository;
    protected $searchCriteriaBuilder;
    protected $logger;
    protected $live2Api;

    public function __construct(
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        Live2ApiCall $live2Api
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
        $this->live2Api=$live2Api;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('live2:productSync')
            ->setDescription('Synchronize product data and save as JSON.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $storeManager = $objectManager->get(StoreManagerInterface::class);
            $productRepository = $objectManager->get(ProductRepositoryInterface::class);
            $searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);


            $live2Details=$this->live2Api->getAccessToken();
            $storeDetails=$this->live2Api->getStoreDetails();

            // $output->writeln('Product data Sync to Live2 Done'.$live2Details['live2_url'].$live2Details['token']);
            $url=$live2Details['live2_url'];
            $token=$live2Details['token'];

            $storeId = $storeManager->getStore()->getId();
            $baseUrlMedia = $storeDetails['baseUrlMedia'];
            $storeUrl = $storeDetails['storeUrl'];

            // Code to save category data and upload it to API
            $categoryData = $this->fetchCategoryData($objectManager, $storeUrl, $baseUrlMedia);
            $this->saveCategoryDataToJsonFile($categoryData);
            
            $responseCat = $this->uploadCategoryFileToApi($url,$token);
            $responseCat=json_decode($responseCat, true);
            $responseCat['shopUrl'] = $storeUrl;
            $data=$responseCat;
            $this->updateBulkCollectionData($url,$responseCat, $token);

            $output->writeln('Product data Sync to Live2 Donedfbafbsf'.json_encode($responseCat));

            // Code to save product data and upload it to API
            $productDataArray = $this->fetchProductData($searchCriteriaBuilder, $productRepository);
            $jsonFile = 'var/product_live2.json';
            $this->saveDataToJsonFile($jsonFile, $productDataArray, $storeUrl, $baseUrlMedia);

            $response = $this->uploadFileToApi($url,$jsonFile, $token);
            $this->logger->info('outputDataLIVE2' . json_encode($response));

            $response = json_decode($response, true);
            $response['shopUrl'] = $storeUrl;
            $data = $response;

            $this->updateBulkData($url,$response, $token);

            // // Code to save category data and upload it to API
            // $categoryData = $this->fetchCategoryData($objectManager, $storeUrl, $baseUrlMedia);
            // $this->saveCategoryDataToJsonFile($categoryData);

            // $responseCat = $this->uploadCategoryFileToApi($url,$token);
            // $responseCa["shopUrl"]=$storeUrl;
            // $this->updateBulkCollectionData($url,$data, $token);
            $output->writeln('Product data Sync to Live2 Done');
        } catch (\Throwable $e) {
            $this->logger->critical('outputDataLIVE2' . json_encode($e->getMessage()));
            $output->writeln('Error: ' . $e->getMessage());
        }
    }

    protected function fetchProductData($searchCriteriaBuilder, $productRepository)
    {
        $searchCriteria = $searchCriteriaBuilder->setPageSize(600)->create();
        $productList = $productRepository->getList($searchCriteria);
        $productDataArray = [];
        foreach ($productList->getItems() as $product) {
            $productDataArray[] = $product->getData();
        }
        return $productDataArray;
    }

    protected function saveDataToJsonFile($jsonFile, $productDataArray, $storeUrl, $baseUrlMedia)
    {
        $data = [
            'product' => $productDataArray,
            'store_url' => $storeUrl,
            'image_url' => $baseUrlMedia . 'catalog/product'
        ];
        file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    protected function uploadFileToApi($url,$jsonFile, $token)
    {
        $url = $url.'/api/live2/file-upload/magento';
        $headers = ['Authorization: ' . $token];
        $postData = ['file' => new \CURLFile(BP . '/' . $jsonFile, 'application/json')];

        return $this->sendRequest($url, $headers, $postData);
    }

    protected function updateBulkData($apiUrl,$data, $token)
    {
        $apiUrl = $apiUrl.'/api/live2/stores/magento/bulk-update';
        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];

        $this->sendRequest($apiUrl, $headers, json_encode($data));
    }

    protected function updateBulkCollectionData($apiUrl,$data, $token)
    {
        $apiUrl = $apiUrl.'/api/live2/stores/magento/collection';
        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];

        $this->sendRequest($apiUrl, $headers, json_encode($data));
    }

    protected function fetchCategoryData($objectManager, $storeUrl, $baseUrlMedia)
    {
        $categoryCollection = $objectManager->create('\Magento\Catalog\Model\ResourceModel\Category\Collection');
        $categoryCollection->addAttributeToSelect('*');
        $categoryData = [];
        foreach ($categoryCollection as $category) {
            $categoryData[] = $category->getData();
        }
        return [
            'category_Data' => $categoryData,
            'store_url' => $storeUrl,
            'image_url' => $baseUrlMedia
        ];
    }

    protected function saveCategoryDataToJsonFile($categoryData)
    {
        file_put_contents('var/categories.json', json_encode($categoryData, JSON_PRETTY_PRINT));
    }

    protected function uploadCategoryFileToApi($url,$token)
    {
        $url = $url.'/api/live2/file-upload/magento';
        $headers = ['Authorization: ' . $token];
        $postData = ['file' => new \CURLFile(BP . '/var/categories.json', 'application/json')];

        return $this->sendRequest($url, $headers, $postData);
    }

    protected function sendRequest($url, $headers, $postData)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}



