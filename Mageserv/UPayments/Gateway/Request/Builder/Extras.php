<?php
/**
 * Product
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Gateway\Request\Builder;


use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;

class Extras implements BuilderInterface
{
    protected $url;
    protected $scopeConfig;
    public function __construct(
        UrlInterface $url,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->url = $url;
        $this->scopeConfig = $scopeConfig;
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
        if(!empty($buildSubject['payment'])){
            /** @var PaymentDataObjectInterface $paymentDO */
            $paymentDO = $buildSubject['payment'];
            $instance = $paymentDO->getPayment()->getMethodInstance();
            $isTokenized = $paymentDO->getPayment()->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
        }else{
            $instance = $order->getPayment()->getMethodInstance();
            $isTokenized = $order->getPayment()->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
        }

        \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("DEBUG:KHALID");
        \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("isTokenizedFromExtra::" . $isTokenized);

        return [
            "language" => $this->scopeConfig->getValue('payment/upayments/language'),
            // 'isSaveCard'=> (bool) ($isTokenized ?:0),
            //'is_whitelabled' => 1,
            'returnUrl' => $this->url->getUrl('upayments/paypage/success'),
            'cancelUrl' => $this->url->getUrl('upayments/paypage/fail'),
            'notificationUrl' => $this->url->getUrl('upayments/paypage/ipn')
        ];
    }
}
