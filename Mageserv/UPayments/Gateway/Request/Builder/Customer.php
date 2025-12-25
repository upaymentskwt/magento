<?php
/**
 * Product
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Gateway\Request\Builder;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Mageserv\UPayments\Helper\Data;

class Customer implements BuilderInterface
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
        $customerId = $uid = $order->getCustomerId();
        if($customerId){
            $customer = $this->customerRepository->getById($customerId);
            $uid = $customer->getCustomAttribute(\Mageserv\UPayments\Setup\InstallData::UPAYMENTS_TOKEN_ATTRIBUTE) ? $customer->getCustomAttribute(\Mageserv\UPayments\Setup\InstallData::UPAYMENTS_TOKEN_ATTRIBUTE)->getValue() : null;
            if(!$uid){
                // generate and save uid + Customer Found with Login Status true
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

        return [
            'customer' => [
                'uniqueId' => (string) $uid,
                'name' => $order->getBillingAddress()->getFirstname() . " " . $order->getBillingAddress()->getLastname(),
                'email' => $order->getCustomerEmail(),
                'mobile' => intval(substr($order->getBillingAddress()->getTelephone(), '-8'))
            ]
        ];
    }
}
