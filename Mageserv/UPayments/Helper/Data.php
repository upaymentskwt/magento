<?php
/**
 * Uid
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Helper;


use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Invoice\NotifierInterface as InvoiceSenderInterface;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilder;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Mageserv\UPayments\Gateway\Http\Client\Api;
use Mageserv\UPayments\Setup\InstallData;
use Mageserv\UPayments\Setup\UpgradeData;
use Mageserv\UPayments\Logger\UPaymentsLogger;

class Data extends AbstractHelper
{
    const PAYPAGE_LANG_XML_PATH = "payment/upayments/language";
    protected $apiClient;
    protected $invoiceSender;
    protected $invoiceService;
    protected $transaction;
    protected $transactionBuilder;
    protected $scopeConfig;
    protected $customerRepository;
    protected $paymentTokenFactory;
    protected $paymentTokenRepository;
    protected $paymentTokenManagement;
    protected $encryptor;

    public function __construct(
        Api                                                  $apiClient,
        Context                                              $context,
        Transaction                                          $transaction,
        InvoiceService                                       $invoiceService,
        TransactionBuilder                                   $transactionBuilder,
        EncryptorInterface                                   $encryptor,
        ScopeConfigInterface                                 $scopeConfig,
        InvoiceSenderInterface                               $invoiceSender,
        CustomerRepositoryInterface                          $customerRepository,
        PaymentTokenFactoryInterface                         $paymentTokenFactory,
        PaymentTokenRepositoryInterface                      $paymentTokenRepository,
        PaymentTokenManagementInterface                      $paymentTokenManagement
    )
    {
        $this->apiClient = $apiClient;
        $this->invoiceSender = $invoiceSender;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->transactionBuilder = $transactionBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->customerRepository = $customerRepository;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->encryptor = $encryptor;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->paymentTokenManagement = $paymentTokenManagement;
        parent::__construct($context);
    }

    public function getConfigData($field)
    {
        return $this->scopeConfig->getValue($field);
    }

    public function generateCustomerUid($email = null, $isLoggedin = false)
    {
        // Tokenization enabled check + User Logged in check
        $isTokenized = $this->scopeConfig->getValue('payment/upayments_vault/active');
        $uid = null;

        UPaymentsLogger::ulog("DEBUG:KHALID");
        UPaymentsLogger::ulog("isTokenized Data::" . (bool) $isTokenized);

        if ($isTokenized && $email && $isLoggedin) {

            // Comment to dis-allow to create customerUniqueTokens without email address
            // if (!$email)
                // $email = $this->generateRandomString() . '@upayments.com';
            
            $uid = crc32($email);
            //check if UID previously generated else return new token
            $this->apiClient->createCustomerToken($uid);
        }

        return $uid;
    }

    private function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function fetchSavedCardsByOrder($order)
    {
        $customerId = $order->getCustomerId();
        if($customerId){
            try{
                $this->fetchSavedCards($customerId);
            }catch (\Exception $ex){}
        }
    }
    public function fetchSavedCards($customerId)
    {
        $customer = $this->customerRepository->getById($customerId);
        if ($customer->getId()) {
            $uid = $customer->getCustomAttribute(InstallData::UPAYMENTS_TOKEN_ATTRIBUTE) ?
            $customer->getCustomAttribute(InstallData::UPAYMENTS_TOKEN_ATTRIBUTE)->getValue() :
            null;

            // Commented to allow only the pre created tokens to fetch the saved cards
            // if(!$uid)
            //     $uid = $this->generateCustomerUid($customer->getEmail());

            if ($uid) {
                $tokens = $this->apiClient->fetchCards($uid);
                if (is_array($tokens)){
                    $this->saveToken($customerId, $tokens);
                }
            }
        }
    }
    public function getTokenByPublicHash($customerId, $publicHash)
    {
        return $this->paymentTokenManagement->getByPublicHash($publicHash, $customerId);
    }
    public function generatePublicHash($params)
    {
        if(is_array($params)){
            $params = json_encode($params);
        }
        return md5($params);
    }
    /**
     * @param int $customerId
     * @param array $token
     * @return void
     */
    public function saveToken($customerId, $tokens)
    {
        if(!is_array($tokens)){
            $tokens = [$tokens];
        }
        foreach ($tokens as $token) {
            $sof = $token['sourceOfFunds'];
            $isCard = strtoupper($sof['type']) == 'CARD';
            $tokenType = $isCard
                ? \Magento\Vault\Api\Data\PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
                : \Magento\Vault\Api\Data\PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT;

            $token_details = $sof['provided']['card'];
            $expiry = str_split($token_details['expiry'], 2);
            $str_token_details = json_encode($token_details);
            $gateway_token = $token['token'];
            $paymentToken = $this->paymentTokenFactory->create($tokenType);
            $paymentToken
                ->setGatewayToken($gateway_token)
                ->setCustomerId($customerId)
                ->setPaymentMethodCode(\Mageserv\UPayments\Model\Ui\ConfigProvider::CODE_UPAYMENTS_WHITELIST)
                ->setPublicHash($this->encryptor->getHash($token_details))
                ->setExpiresAt("{$expiry[1]}-{$expiry[0]}-01 00:00:00")
                ->setIsActive(true)
                ->setIsVisible(true)
                ->setTokenDetails($str_token_details);
            $this->paymentTokenRepository->save($paymentToken);
        }
    }

    /**
     * @param OrderInterface $order
     * @return void
     */
    public function cancelOrder($order, $comment)
    {
        if ($order->getState() != \Magento\Sales\Model\Order::STATE_CANCELED) {
            try{
                $order->registerCancellation($comment)->save();
            }catch (\Exception $exception){
                $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
                $order->addStatusToHistory( \Magento\Sales\Model\Order::STATE_CANCELED , 'UPayments::' . $comment, true );
                $order->save();
            }
        }
    }

    /**
     * @param OrderInterface $order
     * @param $params
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function successOrder($order, $params, $createTransaction = true)
    {
        if ($order->getState() != Order::STATE_PROCESSING) {
            if ($order->canInvoice()) {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
                $invoice->setIsUsedForRefund(0);
                $invoice->register()->capture();
                $invoice->save();
                $transactionSave = $this->transaction->addObject(
                    $invoice
                )->addObject(
                    $invoice->getOrder()
                );
                $transactionSave->save();
                $this->invoiceSender->notify($order, $invoice);

                if($createTransaction){
                    $this->addTransactionToOrder($order, $params);
                }
            }
            $order->setStatus($this->scopeConfig->getValue('order_success_status') ?? Order::STATE_PROCESSING);
            $order->setState(Order::STATE_PROCESSING);
            $order->addStatusToHistory(Order::STATE_PROCESSING, 'UPayments :: Order has been paid.', true);
            if (!empty($params['upay_order_id'])){
                $order->setCustomAttribute(UpgradeData::UPAY_ORDER_ID, $params['upay_order_id']);
            }
            if (!empty($params['track_id'])){
                $order->setCustomAttribute(UpgradeData::UPAY_TRACK_ID, $params['track_id']);
            }
            if($order->getId()){
                $order->save();
            }
            return true;
        }
        return false;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param array $paymentData
     * @return void
     */
    public function addTransactionToOrder($order, $paymentData = array())
    {
        try {
            $transactionId = $paymentData['tran_id'] ?? $paymentData['payment_id'];
            // Prepare payment object
            $payment = $order->getPayment();
            $payment->setLastTransId($transactionId);
            $payment->setTransactionId($transactionId);
            $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData]);
            $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());

            // Prepare transaction
            $transaction = $this->transactionBuilder->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($transactionId)
                ->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData])
                ->setFailSafe(true)
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

            // Add transaction to payment
            $payment->addTransactionCommentsToOrder($transaction, __('The authorized amount is %1.', $formatedPrice));
            $payment->setParentTransactionId(null);

            // Save payment, transaction and order
            $payment->save();
            if($order->getId()){
                $order->save();
            }
            $transaction->save();
        } catch (\Exception $e) {
            //code here
        }
    }
}
