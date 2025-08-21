<?php
/**
 * Adapter
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Plugin\Method;


use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Mageserv\UPayments\Model\Ui\ConfigProvider;

class Adapter
{
    protected $scopeConfig;
    public function __construct(
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function aroundGetConfigData(
        \Magento\Payment\Model\Method\Adapter $method,
        \Closure $proceed,
        $field,
        $storeId = null
    )
    {
        if(stripos($method->getCode(), 'upayments_') === 0){
            $configValue = $proceed($field, $storeId);
            if(!$configValue || $field == "active" || $field == "order_status")
                $configValue =  $this->scopeConfig->getValue(
                    'payment/upayments/' . $field,
                    ScopeInterface::SCOPE_STORES,
                    $storeId
                );

            if($field == 'title' && $method->getCode() == ConfigProvider::CODE_UPAYMENTS_WHITELIST)
                $configValue =  $this->scopeConfig->getValue(
                    'payment/upayments_creditcard/' . $field,
                    ScopeInterface::SCOPE_STORES,
                    $storeId
                );
            return $configValue;
        }
        return $proceed($field, $storeId);
    }
}
