<?php
/**
 * PaymentMethodAvailable
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Observer;


use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\InfoInterface;
use Mageserv\UPayments\Gateway\Http\Client\Api;
use Mageserv\UPayments\Gateway\Request\Builder\PaymentGateway;
use Mageserv\UPayments\Model\Ui\ConfigProvider;

class PaymentMethodAvailable implements ObserverInterface
{
    protected $scopeConfig;
    protected $api;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Api $api
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->api = $api;
    }
    public function execute(Observer $observer)
    {
        try {
            /** @var \Magento\Payment\Model\MethodInterface $paymentMethod */
            $paymentMethod = $observer->getEvent()->getMethodInstance();
            $code = $paymentMethod->getCode();


            // Only handle UPayments methods
            if (stripos($code, "upayments_") !== 0) {
                return;
            }
            $checkResult = $observer->getEvent()->getResult();

            if(!$paymentMethod->getConfigData('active')){
                $checkResult->setData('is_available', false);
                return;
            }
            static $isWhiteLabeled = null;
            static $availableMethods = null;

            if ($isWhiteLabeled === null) {
                $isWhiteLabeled = $this->api->isWhiteLabeled(); // Cached inside API
                \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("Is whitelabeled::" . (int) $isWhiteLabeled);
            }

            if ($availableMethods === null) {
                $availableMethods = array_filter(
                    $this->api->checkAvailableMethods()
                );
                \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("methods::" . json_encode($availableMethods));
            }

            // Enable by default, conditionally disable
            $checkResult->setData('is_available', true);

            if (!$isWhiteLabeled) {
                // Only allow master method
                if ($code !== ConfigProvider::CODE_UPAYMENTS_ALL) {
                    $checkResult->setData('is_available', false);
                }
            } else {
                $method_code = str_replace("upayments_", "", $code);
                if (isset(PaymentGateway::UPAYMENTS_METHODS_MAPPING[$method_code])) {
                    $method_code = PaymentGateway::UPAYMENTS_METHODS_MAPPING[$method_code];
                }

                \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("method::" . $method_code);

                if ($code === ConfigProvider::CODE_UPAYMENTS_ALL || !isset($availableMethods[$method_code])) {
                    $checkResult->setData('is_available', false);
                }
            }

            \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("method::$code status::" . (int) $checkResult->getData('is_available'));

        } catch (\Exception $exception) {
            \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("Exception in is_available observer: " . $exception->getMessage());
        }
    }
}
