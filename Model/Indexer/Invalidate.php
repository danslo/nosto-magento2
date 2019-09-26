<?php
/**
 * Copyright (c) 2019, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Indexer;

use Exception;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionFactory as ProductCollectionFactory;
use Nosto\Tagging\Model\Service\Index as NostoServiceIndex;
use Nosto\Tagging\Util\Indexer as IndexerUtil;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class Invalidate
 * This class is responsible for listening to product changes
 * and setting the `is_dirty` value in `nosto_product_index` table
 * @package Nosto\Tagging\Model\Indexer
 */
class Invalidate implements IndexerActionInterface, MviewActionInterface
{
    const INDEXER_ID = 'nosto_index_product_invalidate';

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoServiceIndex */
    private $nostoServiceIndex;

    /** @var ProductCollectionFactory */
    private $productCollectionFactory;

    /** @var InputInterface */
    private $input;

    /**
     * Dirty constructor.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoServiceIndex $nostoServiceIndex
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoServiceIndex $nostoServiceIndex,
        ProductCollectionFactory $productCollectionFactory,
        InputInterface $input
    ) {
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoServiceIndex = $nostoServiceIndex;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->input = $input;
    }

    /**
     * @param int[] $ids
     * @throws Exception
     */
    public function execute($ids)
    {
        if (!empty($ids)) {
            $ids = array_unique($ids);
            $idsSize = count($ids);
            $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
            foreach ($storesWithNosto as $store) {
                $productCollection = $this->getCollection($store, $ids);
                $this->nostoServiceIndex->invalidateOrCreate($productCollection, $store);
                $collectionSize = $productCollection->getSize();

                //In case for this specific set of ids
                //there are more entries of products in the indexer table than the magento product collection
                //it means that some products were deleted
                if ($idsSize > $collectionSize) {
                    $this->nostoServiceIndex->markProductsAsDeletedByDiff($productCollection, $ids, $store);
                }
            }
        }
    }

    /**
     * @inheritDoc
     * @throws NostoException
     */
    public function executeFull()
    {
        if ($this->allowFullExecution() === true) {
            $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
            foreach ($storesWithNosto as $store) {
                $productCollection = $this->getCollection($store);
                $this->nostoServiceIndex->invalidateOrCreate($productCollection, $store);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @inheritDoc
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @param Store $store
     * @param array $ids
     * @return ProductCollection
     */
    public function getCollection(Store $store, array $ids = [])
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStore($store);
        if (!empty($ids)) {
            $collection->addIdsToFilter($ids);
        } else {
            $collection->addActiveAndVisibleFilter();
        }
        return $collection;
    }

    /**
     * @return bool
     */
    public function allowFullExecution()
    {
        return IndexerUtil::isCalledFromSetupUpgrade($this->input) === false;
    }
}
