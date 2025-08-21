<?php
/**
 * OrderSender
 *
 * @copyright Copyright © 2025 Staempfli AG. All rights reserved.
 * @author    juan.alonso@staempfli.com
 */

namespace Mageserv\UPayments\Plugin\Email\Sender;

class OrderSender
{
    /**
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $subject
     * @param callable $proceed
     * @param \Magento\Sales\Model\Order $order
     * @param $forceSyncMode
     * @return bool
     */
    public function aroundSend(
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $subject,
        callable $proceed,
        $order,
        $forceSyncMode = false
    ) {
        $paymentMethod = $order->getPayment()->getMethodInstance();
        if ($paymentMethod && stripos($paymentMethod->getCode(), "upayments_") === 0) {
            if ($order->getState() == \Magento\Sales\Model\Order::STATE_NEW || $order->getStatus() == $paymentMethod->getConfigData('order_status')) {
                return false;
            }
        }
        return $proceed($order, $forceSyncMode);
    }
}
