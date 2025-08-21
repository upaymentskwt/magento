<?php
/**
 * Ipn
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Controller\PayPage;


use Mageserv\UPayments\Model\Ui\StatusEnum;

class Ipn extends Checkout
{

    public function execute()
    {
        try{
            $params = $this->getRequest()->getParams();
            if($params['order_id']){
                $order = $this->orderRepository->get($params['order_id']);
                $success = strtoupper($params['result']) == StatusEnum::CAPTURED;
                if($success){
                    $this->helper->successOrder($order, $params);
                }
            }
        }catch (\Exception $exception){}
    }
}
