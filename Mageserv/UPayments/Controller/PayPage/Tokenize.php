<?php
/**
 * Tokenize
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Controller\PayPage;


use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Mageserv\UPayments\Gateway\Http\Client\Api;
use Mageserv\UPayments\Helper\Data;
use Mageserv\UPayments\Observer\DataAssignObserver;
use Magento\Framework\App\Action\Action;
class Tokenize extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    protected $jsonResultFactory;
    protected $quoteRepository;
    protected $customerRepository;
    protected $helper;
    protected $api;
    public function __construct(
        Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        CustomerRepositoryInterface $customerRepository,
        Data $helper,
        Api $api
    )
    {
        parent::__construct($context);
        $this->jsonResultFactory = $jsonResultFactory;
        $this->quoteRepository = $quoteRepository;
        $this->customerRepository = $customerRepository;
        $this->helper = $helper;
        $this->api = $api;
    }

    public function execute()
    {
        $result = $this->jsonResultFactory->create();
        $params = $this->getRequest()->getParams();
        $additional_data = $params['additional_data'];
        if (empty($additional_data['quote_id'])) {
            $result->setData([
                'success' => false,
                'message' => 'Quote ID is missing!'
            ]);
            return $result;
        }
        $quoteId = $additional_data['quote_id'];

        $quote = $this->quoteRepository->get($quoteId);
        try{
            $customerId = $quote->getCustomerId();
            if($customerId){
                $customer = $this->customerRepository->getById($customerId);
                $uid = $customer->getCustomAttribute(\Mageserv\UPayments\Setup\InstallData::UPAYMENTS_TOKEN_ATTRIBUTE) ? $customer->getCustomAttribute(\Mageserv\UPayments\Setup\InstallData::UPAYMENTS_TOKEN_ATTRIBUTE)->getValue() : null;
                if(!$uid){
                    $uid = $this->helper->generateCustomerUid($customer->getEmail());

                    if (! empty($uid)){
                        $customer->setCustomAttribute(\Mageserv\UPayments\Setup\InstallData::UPAYMENTS_TOKEN_ATTRIBUTE, $uid);
                        $this->customerRepository->save($customer);
                    }
                }
            }

            // commented to ignore passing customerUniqueToken for the guests
            /*else{
                $uid = $this->helper->generateCustomerUid($quote->getCustomerEmail());
            }*/

            $exp_year = $additional_data[DataAssignObserver::CC_EXP_YEAR];
            if(strlen($exp_year) >= 4)
                $exp_year = $exp_year%100;
            $params =  [
                'endpoint' => Api::CREATE_CARD_TOKEN,
                "card" => [
                    "number" => $additional_data[DataAssignObserver::CC_NUMBER],
                    "expiry" => [
                        "month" => $additional_data[DataAssignObserver::CC_EXP_MONTH],
                        "year" => $exp_year
                    ],
                    "securityCode" => $additional_data[DataAssignObserver::CC_ID],
                    "nameOnCard" => ""
                ]
            ];

            // pass the $uid only for the login users
            if (!empty($customerId) && !empty($uid)) {
                $params["customerUniqueToken"] = $uid;
            }

            $publicHash = $this->helper->generatePublicHash($params['card']);

            $resp = $this->api->tokenize($params);
            if($resp['status'] && !empty($resp['data'])){
                $isValid = $resp['data']['cardData']['status'] == "VALID";
                if(!$isValid)
                    throw new \Exception(__("Invalid card details"));

                $success = true;
                $message = $resp['message'];
                if($customerId){
                    $paymentToken = $this->helper->saveToken($customerId, $resp['data']['cardData'], $publicHash);
                    $token = $paymentToken->getGatewayToken();
                }else{
                    $token = $resp['data']['cardData']['token'];
                }
            }else{
                throw new \Exception($resp['message']);
            }
        }catch (\Exception $exception){
            $success = false;
            $message = $exception->getMessage();
            $token = null;
            \Mageserv\UPayments\Logger\UPaymentsLogger::ulog($exception->getMessage());

        }
        $result->setData([
            'success' => $success,
            'message' => $message,
            'token' => $token
        ]);
        return $result;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
