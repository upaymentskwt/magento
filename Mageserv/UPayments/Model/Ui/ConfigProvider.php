<?php

namespace Mageserv\UPayments\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE_UPAYMENTS        = 'upayments_creditcard';
    const CODE_UPAYMENTS_ALL        = 'upayments_all';
    const CODE_UPAYMENTS_WHITELIST        = 'upayments_cc';
    const CODE_UPAYMENTS_SAMSUNGPAY       = 'upayments_samsungpay';
    const CODE_UPAYMENTS_APPLEPAY        = 'upayments_applepay';
    const CODE_UPAYMENTS_APPLEKNET        = 'upayments_appleknet';
    const CODE_UPAYMENTS_KNET        = 'upayments_knet';
    const CODE_VAULT_UPAYMENTS = 'upayments_vault';
    const CODE_UPAYMENTS_GOOGLEPAY = 'upayments_googlepay';
    const CODE_UPAYMENTS_AMEX = 'upayments_amex';

    protected $paymentHelper;
    private $assetRepo;
    private $ccConfig;

    public function __construct(
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Payment\Model\CcConfig $ccConfig
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->assetRepo = $assetRepo;
        $this->ccConfig = $ccConfig;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
       /* $allTypes = $this->ccConfig->getCcAvailableTypes();
        $availableTypes = explode(',', $this->paymentHelper->getMethodInstance(self::CODE_UPAYMENTS_WHITELIST)->getConfigData("cctypes") ?? []);*/
        $payments = [
            /*self::CODE_UPAYMENTS_WHITELIST => [
                'vault_code' => self::CODE_VAULT_UPAYMENTS,
                'availableTypes' => array_intersect_key($allTypes, array_flip($availableTypes)),
                'hasVerification' => $this->paymentHelper->getMethodInstance(self::CODE_UPAYMENTS_WHITELIST)->getConfigData("useccv"),
                'months' => $this->ccConfig->getCcMonths(),
                'years' =>  $this->ccConfig->getCcYears(),
                'cvvImageUrl' => $this->ccConfig->getCvvImageUrl(),
                'icon' => 'creditcard.svg'
            ],*/
            self::CODE_UPAYMENTS => [
                'icon' => 'creditcard.svg'
            ],
            self::CODE_UPAYMENTS_ALL => [
                'icon' => 'all.png'
            ],
            self::CODE_UPAYMENTS_KNET => [
                'icon' => 'knet.svg'
            ],
            self::CODE_UPAYMENTS_SAMSUNGPAY => [
                'icon' => 'samsungpay.svg'
            ],
            self::CODE_UPAYMENTS_APPLEPAY => [
                'icon' => 'applecc.svg'
            ],
            self::CODE_UPAYMENTS_APPLEKNET => [
                'icon' => 'appleknet.svg'
            ],
            self::CODE_UPAYMENTS_GOOGLEPAY => [
                'icon' => 'googlepay.png'
            ],
            self::CODE_UPAYMENTS_AMEX => [
                'icon' => 'amex.png'
            ],
            self::CODE_VAULT_UPAYMENTS => [
                'vault_code' => self::CODE_VAULT_UPAYMENTS
            ],
        ];

        $keys_bool = ['iframe_mode', 'payment_preorder'];
        $keys = ['currency_select'];

        $_icons_path = $this->assetRepo->getUrl("Mageserv_UPayments::images/");

        foreach ($payments as $code => &$values) {
            foreach ($keys_bool as $key) {
                $values[$key] = (bool) $this->paymentHelper->getMethodInstance($code)->getConfigData($key);
            }

            foreach ($keys as $key) {
                $values[$key] = $this->paymentHelper->getMethodInstance($code)->getConfigData($key);
            }

            if (isset($values['icon'])) {
                $values['icon'] = $_icons_path . '/' . $values['icon'];
            }
        }
        $logo_animation = $this->assetRepo->getUrl('Mageserv_UPayments::images/logo-animation.gif');

        return [
            'payment' => $payments,
            'upayments_icons' => [
                'logo_animation' => $logo_animation
            ]
        ];
    }
}
