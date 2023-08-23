<?php

namespace WS\B2CplApi;

use Bitrix\Main\Web\HttpClient;

/**
 * Class Client
 * @package WS\B2CplApi
 */
class Client {

    /** @var string */
    private $url;

    private $apiParams;

    /** @var HttpClient  $httpClient */
    private $httpClient;

    /**
     * Client constructor.
     * @param HttpClient $httpClient
     * @param $url
     * @param $apiClient
     * @param $apiKey
     */
    public function __construct(HttpClient $httpClient, $url, $apiClient, $apiKey) {
        $this->apiParams = array(
            'client' => $apiClient,
            'key' => $apiKey,
        );
        $this->httpClient = $httpClient;
        $this->url = $url;
    }

    public function send($url, $params) {
        $this->httpClient->clearHeaders();
        return $this->httpClient->post($url, $params);
    }

    /**
     * @param $region
     * @return string
     */
    public function getInfoStore($region) {
        $params = array_merge($this->apiParams, array('func' => 'info_store', 'region' => $region));
        return $this->send($this->url, json_encode($params));
    }

    /**
     * @return string
     */
    public function getPing() {
        $params = array_merge($this->apiParams, array('func' => 'ping'));
        return $this->send($this->url, json_encode($params));
    }
}