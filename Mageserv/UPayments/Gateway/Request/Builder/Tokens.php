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
use Mageserv\UPayments\Helper\Data;

class Tokens implements BuilderInterface
{
    protected $customerRepository;
    protected $uidHelper;
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        Data $uidHelper
    )
    {
        $this->customerRepository = $customerRepository;
        $this->uidHelper = $uidHelper;
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
                $uid = $this->uidHelper->generateCustomerUid($customer->getEmail());

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

        if(!empty($buildSubject['payment'])){
            $paymentDO = $buildSubject['payment'];
            $isTokenized = $paymentDO->getPayment()->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
        }else{
            $isTokenized = $order->getPayment()->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
        }

        \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("DEBUG:KHALID");
        \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("isTokenizedFromTokens::" . $isTokenized);

        if (! empty($uid)){
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
