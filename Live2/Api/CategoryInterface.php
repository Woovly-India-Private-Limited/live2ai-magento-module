<?php
namespace Live2\Live2\Api;

interface CategoryInterface
{
    /**
     * Retrieve category tree
     *
     * @param int|null $rootCategoryId
     * @param int|null $depth
     * @return \Magento\Catalog\Api\CategoryRepositoryInterface
     * 
     */
    public function getCategoryTree($rootCategoryId = null, $depth = null);
}
