<?php

namespace  Live2\Live2\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;

class ProductDeleteAfter implements ObserverInterface
{

    public const WebHook = 'http://localhost:8080/webhooks';

    /**
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        // Get the product object
        $product = $observer->getEvent()->getProduct();

        // Send the product data to the webhook endpoint
        $access_token = \Live2\Live2\Helper\Live2ApiCall::getAccessToken();

        try {
            $jsonData = json_encode($product);
            $this->logger->info("outputDataLIVE2",$jsonData);

            $ch = curl_init(self::WebHook);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($jsonData), 'Store-Type: magento','AccessToken :'.$access_token));
            $result = curl_exec($ch);
            curl_close($ch);

        } catch (\Exception $e) {
            $this->logger->critical("outputDataLIVE2",$e->getMessage());
        }
    }
}
