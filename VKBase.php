<?php

namespace jumper423;

use Yii;
use yii\authclient\clients\VKontakte;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class VKBase extends VKontakte
{
    public $delay = 0.7;
    public $delayExecute2 = 120;
    public $limitExecute = 1;
    /**
     * @var null|string|Captcha
     */
    public $captcha = 'captcha';

    private $lastRequest = null;
    private $sleepRandMin = 0;
    private $sleepRandMax = 500;
    private $execute = [];

    const MILLISECOND = 1000;

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
        if (is_string($this->captcha)) {
            if (Yii::$app->has($this->captcha) && Yii::$app->get($this->captcha) instanceof CaptchaInterface) {
                $this->captcha = Yii::$app->get($this->captcha);
            } else {
                $this->captcha = null;
            }
        } elseif (is_object($this->captcha)) {
            if (!($this->captcha instanceof CaptchaInterface)) {
                $this->captcha = null;
            }
        } else {
            $this->captcha = null;
        }
    }

    /**
     * @param bool|false $delay
     */
    private function sleep($delay = false)
    {
        if ($delay === false) {
            $delay = $this->delay;
        }
        if ($this->lastRequest === null) {
            $this->lastRequest = microtime(true);
        } else {
            $microtime = microtime(true);
            if ($this->lastRequest + $delay > $microtime) {
                sleep($this->lastRequest + $delay - $microtime);
                $this->lastRequest = microtime(true);
            }
        }
        sleep(rand($this->sleepRandMin, $this->sleepRandMax) / self::MILLISECOND);
    }

    /**
     * @inheritdoc
     */
    public function api($apiSubUrl, $method = 'GET', $params = [], $headers = [], $delay = false)
    {
        $this->sleep($delay);
        if (preg_match('/^https?:\\/\\//is', $apiSubUrl)) {
            $url = $apiSubUrl;
        } else {
            $url = $this->apiBaseUrl . '/' . $apiSubUrl;
        }
        $accessToken = $this->getAccessToken();
        /*if (!is_object($accessToken) || !$accessToken->getIsValid()) {
            throw new Exception('Invalid access token.');
        }*/
        $response = $this->apiInternal($accessToken, $url, $method, $params, $headers);
        if (ArrayHelper::getValue($response, 'error.error_code') == 14 && $this->captcha) {
            if ($this->captcha->run(ArrayHelper::getValue($response, 'error.captcha_img'))) {
                $response = self::api($apiSubUrl, $method, ArrayHelper::merge($params,
                    [
                        'captcha_sid' => ArrayHelper::getValue($response, 'error.captcha_sid'),
                        'captcha_key' => $this->captcha->result()
                    ])
                    , $headers);
            } else {
                throw new Exception($this->captcha->error());
            }
        } elseif (ArrayHelper::getValue($response, 'error')) {
            throw new Exception(ArrayHelper::getValue($response, 'error.error_msg'), ArrayHelper::getValue($response, 'error.error_code'));
        }
        return $response;
    }

    /**
     * @inheritdoc
     */
    protected function apiInternal($accessToken, $url, $method, array $params, array $headers)
    {
        if (is_object($accessToken)) {
            $params['uids'] = $accessToken->getParam('user_id');
            $params['access_token'] = $accessToken->getToken();
        }
        return $this->sendRequest($method, $url, $params, $headers);
    }

    /**
     * @param $apiSubUrl
     * @param array $params
     */
    public function addAction($apiSubUrl, array $params = [])
    {
        $this->execute[] = "API.{$apiSubUrl}(" . Json::encode($params) . ")";
    }

    /**
     * @param $method
     */
    public function addActionsInCron($method)
    {
        $json = Json::decode(file_get_contents(Yii::getAlias("@actions/{$method}.json")));
        $json = ArrayHelper::merge($json, $this->execute);
        file_put_contents(Yii::getAlias("@actions/{$method}.json"), Json::encode($json));
    }

    /**
     * @param $method
     * @return array
     */
    public function performAnActionFromCron($method)
    {
        $response = [];
        do {
            $execute = Json::decode(file_get_contents(Yii::getAlias("@actions/{$method}.json")));
            if (count($execute)) {
                $executeRow = array_shift($execute);
                $response = ArrayHelper::merge($response, $this->post('execute', ['code' => "return \n [" . $executeRow . "];"]));
                $execute = Json::decode(file_get_contents(Yii::getAlias("@actions/{$method}.json")));
                $r = array_search($executeRow, $execute);
                if ($r !== false) {
                    unset($execute[$r]);
                    file_put_contents(Yii::getAlias("@actions/{$method}.json"), Json::encode($execute));
                }
                sleep($this->delayExecute2);
            } else {
                break;
            }
        } while (true);
        return $response;
    }

    /**
     * @return array
     */
    public function performAnAction()
    {
        $executeCount = count($this->execute);
        $response = [];
        for ($i = 0; $executeCount > $i; $i += $this->limitExecute) {
            $response = ArrayHelper::merge($response, $this->post('execute', ['code' => "return \n [" . implode(",\n", array_slice($this->execute, $i, $this->limitExecute)) . "];"]));
            sleep($this->delayExecute2);
        }
        $this->execute = [];
        return $response;
    }

    /**
     * @param $imagePath
     * @param $albumId
     * @param null|int $groupId
     * @return int
     * @throws Exception
     */
    public function loadImage($imagePath, $albumId, $groupId = null)
    {
        $params = ['album_id' => $albumId];
        if ($groupId) {
            $params['group_id'] = $groupId;
        }
        $request = $this->post('photos.getUploadServer', $params);
        $uploadUrl = ArrayHelper::getValue($request, 'response.upload_url');
        if ($uploadUrl) {
            $ch = curl_init($uploadUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'file1' => "@" . $imagePath
            ]);
            $paramsSave = Json::decode(curl_exec($ch));
            curl_close($ch);
            if (count($paramsSave)) {
                return ArrayHelper::getValue($this->post('photos.save', $paramsSave), 'response.0.pid');
            } else {
                throw new Exception('Empty params save');
            }
        } else {
            throw new Exception('Not upload_url');
        }
    }

    /**
     * @inheritdoc
     */
    protected function initUserAttributes()
    {
        $response = $this->api('users.get.json', 'GET', [
            'fields' => implode(',', $this->attributeNames),
        ]);
        $attributes = array_shift($response['response']);

        $accessToken = $this->getAccessToken();
        if (is_object($accessToken)) {
            $accessTokenParams = $accessToken->getParams();
            $attributes = array_merge($accessTokenParams, $attributes);
        }

        return $attributes;
    }
}
