<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Nosto\Tagging\Model\Indexer\Product;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Nosto\Tagging\Model\Product\Service as ProductService;
use Nosto\Tagging\Logger\Logger as NostoLogger;


/**
 * An indexer for Nosto product sync
 *
 */
class Indexer implements IndexerActionInterface, MviewActionInterface
{
    const HARD_LIMIT_FOR_PRODUCTS = 10000000;
    const INDEXER_ID = 'nosto_product_sync';

    private $productService;
    private $productRepository;
    private $searchCriteriaBuilder;
    private $logger;


    /**
     * @param NostoLogger $logger
     * @param ProductService $productService
     * @param ProductRepository $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        NostoLogger $logger,
        ProductService $productService,
        ProductRepository $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->productService = $productService;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function executeFull()
    {
        // Fetch all enabled products
//        $searchCriteria = $this->searchCriteriaBuilder
//            ->addFilter('status', Status::STATUS_ENABLED, 'eq')
//            ->setPageSize(self::HARD_LIMIT_FOR_PRODUCTS)
//            ->setCurrentPage(1)
//            ->create();
//        $products = $this->productRepository->getList($searchCriteria);
//        $this->productService->update($products->getItems());
        $this->logger->info('executeFull() has been called');
        //todo should clean up the queue table
        //todo the deleted product not getting update
    }

    /**
     * @inheritdoc
     */
    public function executeList(array $ids)
    {
        $this->logger->info('executeList() has been called,' . implode($ids));
        $this->execute($ids);
    }

    /**
     * @inheritdoc
     */
    public function executeRow($id)
    {
        $this->logger->info('executeRow() has been called,' . $id);
        $this->execute([$id]);
    }

    /**
     * @inheritdoc
     */
    public function execute($ids)
    {
        $this->logger->info('execute() has been called,' . implode($ids));
        $this->productService->updateByIds($ids);
    }
}
