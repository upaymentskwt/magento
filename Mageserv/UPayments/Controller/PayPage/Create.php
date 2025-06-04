<?php

// declare(strict_types=1);

namespace Mageserv\UPayments\Controller\PayPage;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use Mageserv\UPayments\Gateway\Http\Client\Api;
use stdClass;

/**
 * Class Index
 */
class Create extends Action
{
    /**
     * @var PageFactory
     */
    private $pageFactory;

    private $jsonResultFactory;
    protected $orderRepository;
    protected $_orderFactory;
    protected $quoteRepository;
    protected $checkoutSession;
    protected $_customerSession;
    protected $apiClient;


    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Mageserv\UPayments\Gateway\Http\Client\Api $apiClient,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->_orderFactory = $orderFactory;
        $this->pageFactory = $pageFactory;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->apiClient = $apiClient;
        $this->_logger = $logger;
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $startTime = microtime(true);
        $result = $this->jsonResultFactory->create();

        // Get the params that were passed from our Router
        $quoteId = $this->getRequest()->getParam('quote', null);
        if (!$quoteId) {
            $this->_logger->critical('UPayments:: Missing Quote Id');
            \Mageserv\UPayments\Logger\UPaymentsLogger::ulog('UPayments:: Missing Quote Id');

            $result->setData([
                'result' => 'Quote ID is missing!'
            ]);
            return $result;
        }

        // Create PayPage
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->getOrder();
        if (!$order) {
            $this->_logger->critical("UPayments::Order is missing!, Quote [{$quoteId}]");
            \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("UPayments::Order is missing!, Quote [{$quoteId}]");

            $result->setData([
                'result' => 'Order is missing!'
            ]);
            return $result;
        }
        $order->setStatus("pending_payment");
        $order->setState("pending_payment");
        $order->setCanSendNewEmailFlag(false);
        $order->save();

        try{
            $paypage = $this->apiClient->charge($order);
            if ($paypage->success) {
                $this->_logger->info("UPayments::  create paypage success!, Order [{$order->getIncrementId()}]");

                $res = new stdClass();
                $res->success = true;
                $res->payment_url = $paypage->link;
                $res->tran_ref = $paypage->track_id;
                $paypage = $res;
            } else {
                $this->_logger->critical("UPayments: create paypage failed!, Order [{$order->getIncrementId()}] - " . json_encode($paypage));
                try {
                    $quote = $this->quoteRepository->get($quoteId);
                    $quote->setIsActive(true)->removePayment()->save();
                } catch (\Throwable $th) {
                    $this->_logger->critical("UPayments: load Quote by ID failed!, QuoteId [{$quoteId}]");
                    \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("UPayments: load Quote by ID failed!, QuoteId [{$quoteId}]");
                }
                $order->cancel()->save();
            }
        }catch (\Exception $e){
            $this->_logger->critical("UPayments: create paypage failed!, Order [{$order->getIncrementId()}] - " . $e->getMessage());
            \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("UPayments: create paypage failed!, Order [{$order->getIncrementId()}] - " . $e->getMessage());
            try {
                $quote = $this->quoteRepository->get($quoteId);
                $quote->setIsActive(true)->removePayment()->save();
            } catch (\Throwable $th) {
                $this->_logger->critical("UPayments: load Quote by ID failed!, QuoteId [{$quoteId}]");
                \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("UPayments: load Quote by ID failed!, QuoteId [{$quoteId}]");

            }
            $order->cancel()->save();
            $paypage = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }


        /*if (Api::hadPaid($order)) {
            $paypage->had_paid = true;
            $paypage->order_id = $order->getId();
        }*/

        $result->setData($paypage);
        $endTime = microtime(true);
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/upayments_time.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info(print_r([
            'service' => 'create payment link',
            'observer_time' => $endTime - $startTime,
        ], true));
        return $result;
    }



    public function getOrder()
    {
        $lastRealOrderId = $this->checkoutSession->getLastRealOrderId();
        if ($lastRealOrderId) {
            $order = $this->_orderFactory->create()->loadByIncrementId($lastRealOrderId);
            return $order;
        }
        return false;
    }
}
