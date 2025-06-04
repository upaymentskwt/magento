<?php
/**
 * Order
 *
 * @copyright Copyright © 2024 Magerserv LTD.. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Plugin;


class Order
{
    public function AfterGetCanSendNewEmailFlag(
        \Magento\Sales\Model\Order $subject,
        $canSendNewEmailFlag
    )
    {
        if($canSendNewEmailFlag){
            $paymentMethod = $subject->getPayment()->getMethod();
            if($paymentMethod && stripos($paymentMethod, "upayments_") === 0 ){
                $canSendNewEmailFlag = false;
            }
        }
        return $canSendNewEmailFlag;
    }
}
