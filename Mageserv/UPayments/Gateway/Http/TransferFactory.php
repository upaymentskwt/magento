<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Mageserv\UPayments\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use \Magento\Payment\Helper\Data as PaymentHelper;
use Mageserv\UPayments\Gateway\Http\Client\Api;
use Mageserv\UPayments\Model\Ui\ConfigProvider;
class TransferFactory implements TransferFactoryInterface
{
    /**
     * @var TransferBuilder
     */
    private $transferBuilder;
    /**
     * @var PaymentHelper
     */
    private $paymentHelper;
    /**
     * @param TransferBuilder $transferBuilder
     */
    public function __construct(
        TransferBuilder $transferBuilder,
        PaymentHelper $paymentHelper
    )
    {
        $this->paymentHelper = $paymentHelper;
        $this->transferBuilder = $transferBuilder;
    }

    /**
     * Builds gateway transfer object
     *
     * @param array $request
     * @return TransferInterface
     */
    public function create(array $request)
    {
        $isLive =  $this->paymentHelper->getMethodInstance(ConfigProvider::CODE_UPAYMENTS)->getConfigData("enable_live_mode");
        $apiUrl =  $isLive ? Api::API_LIVE_URL : Api::API_STATING_URL;
        return $this->transferBuilder
            ->setBody($request)
            ->setMethod('POST')
            ->setHeaders([
                'Authorization' => 'Bearer ' . $this->paymentHelper->getMethodInstance(ConfigProvider::CODE_UPAYMENTS)->getConfigData("api_token"),
                'Uplugin-Request' => 1,
                'X-Signature'     => $this->paymentHelper->getMethodInstance(ConfigProvider::CODE_UPAYMENTS)->getConfigData("api_secret")
            ])
            ->setUri($apiUrl)
            ->build();
    }
}
