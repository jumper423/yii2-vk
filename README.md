Yii2 VK
================


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
php composer.phar require --prefer-dist jumper423/yii2-vk "*"
```

или добавить

```
"jumper423/yii2-vk": "*"
```

в файл `composer.json`.

Конфигурация
------------
```
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

```
$config = [
    'components' => [
        'captcha' => [
            'class' => 'jumper423\Captcha',
            'pathTmp' => '@imagescache/captcha',
            'apiKey' => '42eab4119020dbc729f657fef270f521',
        ],
        'authClientCollection' => [
            'class' => 'yii\authclient\Collection',
            'clients' => [],
        ],
    ],
    'aliases' => [
        '@actions' => '@backend/runtime/cron', // Папка куда будут складироваться очереди для cron-а
    ],
];

Yii::$app->setComponents([
    'vk' => [
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
]);
$config['components']['authClientCollection']['clients']['vkontakte'] = Yii::$app->vk;

return $config;
```

Использование
------------

Создание альбома

```
$response = Yii::$app->vk->post('photos.createAlbum', ['group_id' => $groupId, 'title' => $title, 'upload_by_admins_only' => 1]);
```

Добавление инструкции в очередь 

```
foreach ($images as $image) {
    Yii::$app->vk->addAction('photos.edit', ['caption' => $caption, 'owner_id' => $ownerId, 'photo_id' => $image,]);
}
// Добавление в cron
Yii::$app->vk->addActionsInCron('photos.edit');
// Или начать выполнение очереди командой
// Yii::$app->vk->performAnAction();
```

Выполнение cron-а

```
Yii::$app->vk->performAnActionFromCron('photos.edit');
```

Загрузка изображения в альбом пользователя или группы

```
$imageId = Yii::$app->vk->loadImage($imagePath, $albumId, $groupId);
```

