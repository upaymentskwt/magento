<?php
/**
 * OrderSender
 *
 * @copyright Copyright © 2024 Staempfli AG. All rights reserved.
 * @author    juan.alonso@staempfli.com
 */

namespace Mageserv\UPayments\Plugin;


use Magento\Sales\Model\Order;

class OrderSender
{
    protected $helper;
    public function __construct(
        \Mageserv\UPayments\Helper\Data $helper
    )
    {
        $this->helper = $helper;
    }

    public function aroundSend(
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        callable $proceed,
        Order $order,
        $forceSyncMode = false
    )
    {
        if($order->getState() == Order::STATE_NEW || $order->getStatus() == $this->helper->getOrderPendingStatus()){
            $paymentMethod = $order->getPayment()->getMethod();
            if($paymentMethod && stripos($paymentMethod, "upayments_") === 0 )
                return false;
        }
        return $proceed($order, $forceSyncMode);
    }
}
