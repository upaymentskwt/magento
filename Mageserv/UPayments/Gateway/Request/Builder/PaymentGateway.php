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

class PaymentGateway implements BuilderInterface
{
    const UPAYMENTS_METHODS_MAPPING = [
        'creditcard' => 'cc',
        'knet' => 'knet',
        'samsungpay' => 'samsung-pay',
        'applepay' => 'apple-pay',
        'appleknet' => 'apple-pay-knet',
        'googlepay' => 'google-pay',
        'amex' => 'amex'
    ];
    public function build(array $buildSubject)
    {
        if (
            !isset($buildSubject['order'])
            || !$buildSubject['order'] instanceof OrderInterface
        ) {
            throw new \InvalidArgumentException('order data object should be provided');
        }
        $order = $buildSubject['order'];
        $method_code =  str_replace("upayments_", "", $order->getPayment()->getMethodInstance()->getCode());
        $src = "knet";
        if(in_array($method_code, array_keys(self::UPAYMENTS_METHODS_MAPPING)))
            $src = self::UPAYMENTS_METHODS_MAPPING[$method_code];

        return [
            'paymentGateway' => [
                'src' => $src
            ]
        ];
    }
}
