<?php
/**
 * Success
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Controller\PayPage;



use Magento\Sales\Model\Order;
use Mageserv\UPayments\Model\Ui\StatusEnum;

class Success extends Checkout
{
    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $this->checkoutSession->setPaymentData($this->json->serialize($params));
        $message = null;
        try{
            if(!empty($params['requested_order_id']) && !empty($params['track_id'])){
                /** @var Order $order */
                $order = $this->orderFactory->create()->loadByIncrementId($params['requested_order_id']);
                $success = $this->api->checkPayment($params['track_id']);
                if($success){
                    $this->helper->successOrder($order, $params);
                    try{
                        $this->helper->fetchSavedCardsByOrder($order);
                    }catch (\Exception $ex){}

                    return $this->_redirect('checkout/onepage/success');
                }
            }
        }catch (\Exception $exception){
            $message = $exception->getMessage();
        }
        return $this->failAndRestoreQuote($message);
    }
}
