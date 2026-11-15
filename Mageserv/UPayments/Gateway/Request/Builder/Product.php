<?php
/**
 * Product
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Gateway\Request\Builder;


use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

class Product implements BuilderInterface
{
    protected $productRepository;
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    )
    {
        $this->productRepository = $productRepository;
    }

    public function build(array $buildSubject)
    {
        if (
            !isset($buildSubject['order'])
            || !$buildSubject['order'] instanceof OrderInterface
        ) {
            throw new \InvalidArgumentException('order data object should be provided');
        }
        $order = $buildSubject['order'];
        $products = [];
        /** @var OrderItemInterface $orderItem */
        foreach ($order->getAllVisibleItems() as $orderItem) {
            $desc = $orderItem->getDescription();
            try{
                /** @var \Magento\Catalog\Api\Data\ProductInterface $product */
                $product = $this->productRepository->getById($orderItem->getProductId());
                if($product->getShortDescription())
                    $desc = $product->getShortDescription();
            }catch(\Exception $exception){}
            $name = $orderItem->getName() ?? "";
            $desc = $desc ?? $orderItem->getName();
            $products[] = [
                'name' => mb_substr($name, 0, 255, 'UTF-8'),
                'description' => mb_substr($desc, 0, 255, 'UTF-8'),
                'price' => (float) $orderItem->getBaseRowTotalInclTax(),
                'quantity' => intval($orderItem->getQtyOrdered())
            ];
        }
        return ['products' => $products];
    }
}
