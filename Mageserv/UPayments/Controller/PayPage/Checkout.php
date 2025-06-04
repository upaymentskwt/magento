<?php
/**
 * Checkout
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Controller\PayPage;


use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Mageserv\UPayments\Gateway\Http\Client\Api;
use Mageserv\UPayments\Helper\Data;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

abstract class Checkout extends Action implements CsrfAwareActionInterface
{
    protected $orderRepository;
    protected $checkoutSession;
    protected $messageManager;
    protected $helper;
    protected $orderFactory;
    protected $api;
    protected $logger;
    protected $json;
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        OrderFactory $orderFactory,
        Session $checkoutSession,
        ManagerInterface $messageManager,
        Data $helper,
        Api $api,
        LoggerInterface $logger,
        Json $json
    )
    {
        parent::__construct($context);
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->helper = $helper;
        $this->orderFactory = $orderFactory;
        $this->api = $api;
        $this->logger = $logger;
        $this->json = $json;
    }

    public function failAndRestoreQuote($message = null)
    {
        $params = $this->getRequest()->getParams();
        try{
            if($this->checkoutSession->getLastRealOrderId()){
                $order = $this->orderFactory->create()->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
                $this->helper->cancelOrder($order, $message);
            }
        }catch(\Exception $exception){
            if(!empty($params['order_id'])){
                $order = $this->orderFactory->create()->loadByIncrementId($params['order_id']);
                $this->helper->cancelOrder($order, $message);
            }
        }
        if($this->checkoutSession->restoreQuote()){
            if(!$message)
                $message = __('Error: Payment Failed, Invalid response from gateway');
            $this->messageManager->addErrorMessage(
                $message
            );
        }
        return $this->_redirect('checkout/cart');
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
