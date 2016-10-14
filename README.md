Yii2 VK
================
[![PHP version](https://badge.fury.io/ph/jumper423%2Fyii2-vk.svg)](https://badge.fury.io/ph/jumper423%2Fyii2-vk)

Времнно используйте только вторую версию
================

Компонент для расширенной работы с ВК API в YII2. Загрузка изображений, распознавание капчи, постановка очередей и многое другое.

Сайт с подробным описанием [yii2 api vk](http://infoblog1.ru/learn/cms/yii/rabota-s-api-vk-v-yii2)

Особенности
------------
* Задержка выполнения
* Разделины post и get
* Загрузка изображений
* Добавление в очереди
* Запись задач и выполнению их по cron-у
* Интеграция с распознованием капчи
* Запись в атрибуты token-а

Установка
------------
Предпочтительный способ установить это расширение через [composer](http://getcomposer.org/download/).

Либо запустить

```
php composer.phar require --prefer-dist jumper423/yii2-vk "2.*"
```

или добавить

```
"jumper423/yii2-vk": "2.*"
```

в файл `composer.json`.

Конфигурация
------------
```php
'components' => [
    'vk' => [
        'class' => 'jumper423\VK',
        'clientId' => '11111',
        'clientSecret' => 'n9wsv98svSD867SA7dsda87',
        'delay' => 0.7, // Минимальная задержка между запросами
        'delayExecute' => 120, // Задержка между группами инструкций в очереди
        'limitExecute' => 1, // Количество инструкций на одно выполнении в очереди
        'captcha' => 'captcha', // Компонент по распознованию капчи
    ],
],
'aliases' => [
    '@actions' => '@backend/runtime/cron', // Папка куда будут складироваться очереди для cron-а
],
```

"Расшиненая" конфигурация

```php
'components' => [
    'captcha' => [
        'class' => 'jumper423\Captcha',
        'pathTmp' => '@imagescache/captcha',
        'apiKey' => '42eab4119020dbc729f657',
    ],
    'authClientCollection' => [
        'class' => 'yii\authclient\Collection',
        'clients' => [
            'vkontakte' => [
                'class' => 'jumper423\VK',
                'clientId' => '11111',
                'clientSecret' => 'n9wsv98svSD867SA7dsda87',
                'delay' => 0.7,
                'delayExecute' => 120,
                'limitExecute' => 1,
                'captcha' => 'captcha',
                'scope' => 'friends,photos,pages,wall,groups,email,stats,ads,offline,notifications', //,messages,nohttps
                'title' => 'ВКонтакте'
            ],
        ],
    ],
],
'aliases' => [
    '@actions' => '@backend/runtime/cron', // Папка куда будут складироваться очереди для cron-а
],
```


Использование
------------

Вызов следовательно 

```php
/**
* @var jumper423\VK $vk
*/

$vk = Yii::$app->vk;

или

$vk = Yii::$app->authClientCollection->getClient('vkontakte');
```

Создание альбома

```php
$response = $vk->post('photos.createAlbum', ['group_id' => $groupId, 'title' => $title, 'upload_by_admins_only' => 1]);
```

Добавление инструкции в очередь 

```php
foreach ($images as $image) {
    $vk->addAction('photos.edit', ['caption' => $caption, 'owner_id' => $ownerId, 'photo_id' => $image,]);
}
// Добавление в cron
$vk->addActionsInCron('photos.edit');
// Или начать выполнение очереди командой
// $vk->performAnAction();
```

Выполнение cron-а

```php
$vk->performAnActionFromCron('photos.edit');
```

Загрузка изображения в альбом пользователя или группы

```php
$imageId = $vk->loadImage($imagePath, $albumId, $groupId);
```

Авторизация со всеми провами с помощью selenium
------------

```php
$data = $webDriver->getData($this->api->getOauthUri());
if (!count($data)) {
    throw new Exception('Ошибка при авторизации');
}
$token = [
    'tokenParamKey' => 'access_token',
    'tokenSecretParamKey' => 'oauth_token_secret',
    'createTimestamp' => time(),
    'params' => $data,
];
$this->vk->vk_id = $data['user_id'];
$this->vk->token = $token;
$this->vk->save();
$this->api->setToken($this->vk->token);
```

```php
/**
 * @param $url string
 * @param $recursia bool
 */
public function getData($url, $recursia = true)
{
    $this->driver->get($url);
    $this->driver->findElement(WebDriverBy::name('email'))->sendKeys($this->vkTable->login);
    $this->driver->findElement(WebDriverBy::name('pass'))->sendKeys($this->vkTable->password);
    $this->driver->findElement(WebDriverBy::id('install_allow'))->click();
    sleep(3);
    while ($this->driver->findElements(WebDriverBy::xpath('//input[@name=\'captcha_key\']'))) {
        $this->captcha();
        $this->driver->findElement(WebDriverBy::name('pass'))->sendKeys($this->vkTable->password);
        $this->driver->findElement(WebDriverBy::id('install_allow'))->click();
        sleep(3);
    }
    $this->driver->wait(60, 1000)->until(
        WebDriverExpectedCondition::titleContains('VK | Request Access')
    );
    $this->driver->findElement(WebDriverBy::id('install_allow'))->click();
    $this->driver->wait(60, 1000)->until(
        WebDriverExpectedCondition::titleContains('OAuth Blank')
    );
    $urlCurrent = $this->driver->getCurrentURL();
    $parseUrl = parse_url($urlCurrent);
    if (!isset($parseUrl['fragment']) && $recursia == true) {
        return $this->getData($url, false);
    }
    $query = $parseUrl['fragment'];
    parse_str($query, $data);
    return $data;
}
```
