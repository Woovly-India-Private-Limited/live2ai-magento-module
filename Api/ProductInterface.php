<?php
namespace Live2\Live2\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface ProductInterface
{
    /**
     * Retrieve products based on search criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Magento\Catalog\Api\Data\ProductSearchResultsInterface
     */
    public function getProducts(SearchCriteriaInterface $searchCriteria);
}
