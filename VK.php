<?php

namespace jumper423;

use yii\authclient\OAuthToken;
use yii\base\Exception;
use yii\behaviors\AttributeBehavior;
use yii\helpers\Json;
use jumper423\behaviors\СallableBehavior;

class VK extends VKBase
{
    const SPEED_FAST = 'fast';
    const SPEED_LONG = 'long';

    const EVENT_INIT = 'init';

    /** @var string */
    public $redirectUri = 'https://oauth.vk.com/blank.html';

    /** @var string */
    public $scope;

    /** @var string */
    public $speed = self::SPEED_LONG;

    /** @var string */
    private $token;

    private $big = false;

    public function init()
    {
        parent::init();
        $this->trigger(self::EVENT_INIT);
    }

    public function behaviors()
    {
        return [
            [
                'class' => СallableBehavior::className(),
                'attributes' => [
                    self::EVENT_INIT => ['clientId','clientSecret'],
                ],
            ],
        ];
    }

    /**
     * @return string
     */
    public function getOauthUri()
    {
        return "https://oauth.vk.com/authorize?client_id={$this->clientId}&display=popup&redirect_uri={$this->redirectUri}&scope={$this->scope}&response_type=token";
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function isToken()
    {
        if (!$this->token) {
            return false;
        }
        try {
            $this->api('messages.getDialogs', ['count' => 0]);
            return true;
        } catch (Exception $e){
            return false;
        }
    }

    /**
     * @param string $apiSubUrl
     * @param array $params
     * @param array $headers
     * @param bool $delay
     * @param bool $error
     * @return array
     * @throws \Exception
     */
    public function api($apiSubUrl, $params = [], $headers = [], $delay = false, $error = false)
    {
        $params['lang'] = 'ru';
        $countError = 0;
        $e = new \Exception();
        while ($countError < 5) {
            try {
                return parent::api($apiSubUrl, 'POST', $params, $headers, $delay)['response'];
            } catch (\Exception $e) {
                if ($error) {
                    throw $e;
                }
                if ($e->getCode()) {
                    if ($e->getCode() == 6) {
                        sleep(2);
                        return $this->api($apiSubUrl, $params, $headers, $delay, true);
                    } else {
                        throw $e;
                    }
                } elseif ($countError > 0) {
                    $this->big = true;
                }
                ++$countError;
            }
        }
        throw $e;
    }

    /** Позволять выполнять долгие запросы */
    public function long(){
        $this->speed = self::SPEED_LONG;
    }

    /** Стараться по возможности выполнять быстрые запросы, если в вк происходят сбои */
    public function fast(){
        $this->speed = self::SPEED_FAST;
    }

    /**
     * Returns default cURL options.
     * @return array cURL options.
     */
    protected function defaultCurlOptions()
    {
        $result = parent::defaultCurlOptions();
        $result[CURLOPT_NOSIGNAL] = 1;
        if ($this->speed == self::SPEED_LONG || $this->big) {
            $result[CURLOPT_CONNECTTIMEOUT_MS] = 60000;
            $result[CURLOPT_TIMEOUT_MS] = 60000;
            $this->big = false;
        } else {
            $result[CURLOPT_CONNECTTIMEOUT_MS] = 500;
            $result[CURLOPT_TIMEOUT_MS] = 500;
        }
        return $result;
    }

    public function setAccessToken($token)
    {
        if (is_string($token)) {
            $token = Json::decode($token);
        }
        if (is_array($token)) {
            $token = new OAuthToken($token);
        }
        parent::setAccessToken($token);
    }
}
