<?php
/**
 * Api
 *
 * @copyright Copyright © 2023 Mageserv LTD. All rights reserved.
 * @author    mageserv.ltd@gmail.com
 */

namespace Mageserv\UPayments\Gateway\Http\Client;


use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order;
use Mageserv\UPayments\Api\Data\ChargeResponseInterface;
use Mageserv\UPayments\Model\Ui\ConfigProvider;
use Mageserv\UPayments\Model\Ui\StatusEnum;
use Psr\Log\LoggerInterface;
use \StdClass;
use Magento\Framework\App\CacheInterface;

class Api
{
    const API_STATING_URL = "https://sandboxapi.upayments.com/api/v1/";
    const API_LIVE_URL = "https://apiv2api.upayments.com/api/v1/";
    const CHARGE_ENDPOINT = "charge";
    const CREATE_CARD_TOKEN = "create-token-from-card";
    const AUTO_DEDUCT_ENDPOINT = "auto-deduct";
    const CREATE_REFUND = 'create-refund';
    const CHECK_PAYMENT = 'get-payment-status';
    const REVOKE_CARD_ENDPOINT = 'remove-card';
    const IS_WHITELABELED = 'check-merchant-api-key';
    const CHECK_PAYMENT_BUTTONS_STATUSES = "check-payment-button-status";
    protected $paymentHelper;
    protected $json;
    protected $logger;
    protected $requestBuilder;
    /** @var \Magento\Framework\HTTP\Client\Curl */
    protected $clientFactory;
    protected $helper;
    private $cache;

    public function __construct(
        PaymentHelper    $paymentHelper,
        Json             $json,
        LoggerInterface  $logger,
        CurlFactory      $clientFactory,
        BuilderInterface $requestBuilder,
        CacheInterface   $cache
    )
    {
        $this->paymentHelper = $paymentHelper;
        $this->json = $json;
        $this->logger = $logger;
        $this->clientFactory = $clientFactory;
        $this->requestBuilder = $requestBuilder;
        $this->cache = $cache;
    }

    /**
     * @param Order $order
     * @return StdClass
     */
    public function charge($order)
    {
        $response = new StdClass();
        $response->success = false;
        $response->link = "";
        $response->track_id = "";
        $request = $this->requestBuilder->build([
            'order' => $order
        ]);
        $result = $this->json->unserialize($this->sendRequest(self::CHARGE_ENDPOINT, 'POST', $request));
        if (!empty($result['status'])) {
            $response->success = $result['status'];
            $response->message = $result['message'] ?? "";
            if (!empty($result['data']) && !empty($result['data']['link']))
                $response->link = str_replace("http://", "https://", $result['data']['link']);
            if (!empty($result['data']) && !empty($result['data']['trackId']))
                $response->track_id = $result['data']['trackId'];
        } else {
            $response->message = $result['message'];
        }
        return $response;
    }

    public function checkPayment($track_id)
    {
        $endpoint = self::CHECK_PAYMENT . '/' . $track_id;
        $resp = $this->json->unserialize($this->sendRequest($endpoint, 'GET'));
        return !empty($resp['status']) && !empty($resp['data']) && !empty($resp['data']['transaction']) && strtoupper($resp['data']['transaction']['result']) == StatusEnum::CAPTURED;
    }

    public function createCustomerToken($uid)
    {
        $this->sendRequest('create-customer-unique-token', 'POST', [
            'customerUniqueToken' => $uid
        ]);
    }

    public function fetchCards($uid)
    {
        $params = [
            'customerUniqueToken' => $uid
        ];
        $resp = $this->json->unserialize(
            $this->sendRequest(
                'retrieve-customer-cards',
                'POST',
                $params
            )
        );
        if (!empty($resp['data']) && !empty($resp['data']['customerCards']))
            return $resp['data']['customerCards'];
        return [];
    }

    public function sendRequest($endpoint, $type = "POST", $params = [])
    {
        $methodStartTime = microtime(true);
        $token = $this->paymentHelper->getMethodInstance(ConfigProvider::CODE_UPAYMENTS)->getConfigData("api_token");
        if (!$token)
            throw new LocalizedException(__("UPayments module is not setup correctly, Please add your token!"));

        $isLive = $this->paymentHelper->getMethodInstance(ConfigProvider::CODE_UPAYMENTS)->getConfigData("enable_live_mode");
        $apiUrl = $isLive ? self::API_LIVE_URL : self::API_STATING_URL;

        $headers = [
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$token}",
            "Accept" => "application/json"
        ];
        $gatewayUrl = $apiUrl . ltrim($endpoint, '/');
        $client = $this->clientFactory->create();
        $client->setHeaders($headers);
		$client->setOption(CURLOPT_USERAGENT, $this->getUserAgent($isLive));

        $requestStartTime = microtime(true);

        if (strtoupper($type) == "POST") {
            $client->post($gatewayUrl, $this->json->serialize($params));
        } elseif (strtoupper($type) == "GET") {
            if (is_array($params))
                $gatewayUrl .= "?" . http_build_query($params);
            $client->get($gatewayUrl);
        }
        $requestEndTime = microtime(true);
        $response = $client->getBody();
        \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("New Request");
        \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("URL::" . $gatewayUrl);
        \Mageserv\UPayments\Logger\UPaymentsLogger::ulog("Params::" . json_encode($params));
        \Mageserv\UPayments\Logger\UPaymentsLogger::ulog($response);
        json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE)
            throw new LocalizedException(__("Invalid response from UPayments Gateway, Please make sure that you have whitelisted your server IP"));
        $methodEndTime = microtime(true);
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/upayments_time.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info(print_r([
            'endpoint' => $endpoint,
            'method_time' => $methodEndTime - $methodStartTime,
            'request_time' => $requestEndTime - $methodStartTime,
        ], true));
        return $response;
    }

    public function isWhiteLabeled()
    {
        $cacheKey = 'upayments_is_white_labeled';
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return (bool) $cached;
        }
        try {
            $token = $this->paymentHelper->getMethodInstance(ConfigProvider::CODE_UPAYMENTS)->getConfigData("api_token");
            $resp = $this->json->unserialize(
                $this->sendRequest(self::IS_WHITELABELED, 'POST', [
                    'apiKey' => $token
                ])
            );
            $isWhiteLabel = $resp['data']['isWhiteLabel'];
            $this->cache->save($isWhiteLabel ? '1' : '0', $cacheKey, [], 365*24*60*60);
        } catch (\Exception $exception) {
            \Mageserv\UPayments\Logger\UPaymentsLogger::ulog($exception->getMessage());
        }
        return false;
    }

    public function tokenize(array $params)
    {
        return $this->json->unserialize(
            $this->sendRequest(self::CREATE_CARD_TOKEN, 'POST', $params)
        );
    }

    public function checkAvailableMethods()
    {
        $cacheKey = 'upayments_buttons_status';
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return $this->json->unserialize($cached);
        }

        try {
            $resp = $this->json->unserialize(
                $this->sendRequest(self::CHECK_PAYMENT_BUTTONS_STATUSES, 'GET', [
                    'source' => 'sdk'
                ])
            );
            $response = $resp['data']['payButtons'] ?? [];
            $this->cache->save($this->json->serialize($response), $cacheKey, [], 24 * 60 * 60);
            return $response;
        } catch (\Exception $exception) {
            \Mageserv\UPayments\Logger\UPaymentsLogger::ulog($exception->getMessage());
        }
        return [];
    }

    public function revokeCardToken($token)
    {
        return $this->sendRequest(self::REVOKE_CARD_ENDPOINT, 'POST', ['token' => $token]);
    }

	public function getUserAgent($isLive)
	{
		$userAgent = 'SandboxUpaymentsMagentoPlugin/3.3.6';
		if ($isLive) {
			$userAgent = 'UpaymentsMagentoPlugin/3.3.6';
		}
		return $userAgent;
	}
}
