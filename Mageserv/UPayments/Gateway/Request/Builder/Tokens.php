<?php
/**
 * Tokens
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Gateway\Request\Builder;


use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Mageserv\UPayments\Helper\Data;
use Mageserv\UPayments\Logger\UPaymentsLogger;

class Tokens implements BuilderInterface
{
    protected $customerRepository;
    protected $uidHelper;
    protected $scopeConfig;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        Data $uidHelper,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->customerRepository = $customerRepository;
        $this->uidHelper = $uidHelper;
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
        $customerId = $order->getCustomerId();
        if($customerId){
            $customer = $this->customerRepository->getById($customerId);
            $uid = $customer->getCustomAttribute(\Mageserv\UPayments\Setup\InstallData::UPAYMENTS_TOKEN_ATTRIBUTE) ? $customer->getCustomAttribute(\Mageserv\UPayments\Setup\InstallData::UPAYMENTS_TOKEN_ATTRIBUTE)->getValue() : null;
            if(!$uid){
                $uid = $this->uidHelper->generateCustomerUid($customer->getEmail(), true);

                if (!empty($uid)){
                    $customer->setCustomAttribute(\Mageserv\UPayments\Setup\InstallData::UPAYMENTS_TOKEN_ATTRIBUTE, $uid);
                    $this->customerRepository->save($customer);
                }
            }
        }

        // commented to ignore passing customerUniqueToken for the guests
        /*else{
            $uid = $this->uidHelper->generateCustomerUid($order->getCustomerEmail());
        }*/

        // Tokenization enabled check
        $isTokenized = $this->scopeConfig->getValue('payment/upayments_vault/active');
        
        //old code
        // if(!empty($buildSubject['payment'])){
        //     $paymentDO = $buildSubject['payment'];
        //     $isTokenized = $paymentDO->getPayment()->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
        // }else{
        //     $isTokenized = $order->getPayment()->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
        // }

        UPaymentsLogger::ulog("DEBUG:KHALID");
        UPaymentsLogger::ulog("isTokenized Tokens::" . $isTokenized);

        if (! empty($uid) && $isTokenized) {
            return [
                'tokens' => [
                    'customerUniqueToken' => $uid
                ]
            ];
        }else{
            return [];
        }
    }
}
