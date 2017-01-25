<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Nosto
 * @package   Nosto_Tagging
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2016 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Nosto\Tagging\Model\Cart\Item;

use Exception;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote\Item;
use NostoLineItem;
use Psr\Log\LoggerInterface;

class Builder
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;
    /**
     * Event manager
     *
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger
     * @param ObjectManagerInterface $objectManager
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        LoggerInterface $logger,
        ObjectManagerInterface $objectManager,
        ManagerInterface $eventManager
    ) {
        $this->objectManager = $objectManager;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @param Item $item
     * @param $currencyCode
     * @return NostoLineItem
     * @internal param Store $store
     */
    public function build(Item $item, $currencyCode)
    {
        $cartItem = new NostoLineItem();
        $cartItem->setPriceCurrencyCode($currencyCode);
        $cartItem->setProductId($this->buildItemId($item));
        $cartItem->setQuantity($item->getQty());
        switch ($item->getProductType()) {
            case Simple::getType():
                $cartItem->setName(Simple::buildItemName($this->objectManager, $item));
                break;
            case Configurable::getType():
                $cartItem->setName(Configurable::buildItemName($item));
                break;
            case Bundle::getType():
                $cartItem->setName(Bundle::buildItemName($item));
                break;
            case Grouped::getType():
                $cartItem->setName(Grouped::buildItemName($this->objectManager, $item));
                break;
        }
        try {
            $cartItem->setPrice($item->getBasePriceInclTax());
        } catch (Exception $e) {
            $cartItem->setPrice(0);
        }

        $this->eventManager->dispatch(
            'nosto_cart_item_load_after',
            ['item' => $cartItem]
        );

        return $cartItem;
    }

    /**
     * @param Item $item
     * @return string
     */
    protected function buildItemId(Item $item)
    {
        /** @var Item $parentItem */
        $parentItem = $item->getOptionByCode('product_type');
        if (!is_null($parentItem)) {
            return $parentItem->getProduct()->getSku();
        } elseif ($item->getProductType() === Type::TYPE_SIMPLE) {
            $type = $item->getProduct()->getTypeInstance();
            $parentIds = $type->getParentIdsByChild($item->getItemId());
            $attributes = $item->getBuyRequest()->getData('super_attribute');
            // If the product has a configurable parent, we assume we should tag
            // the parent. If there are many parent IDs, we are safer to tag the
            // products own ID.
            if (count($parentIds) === 1 && !empty($attributes)) {
                return $parentIds[0];
            }
        }

        return $item->getProduct()->getSku();
    }
}
