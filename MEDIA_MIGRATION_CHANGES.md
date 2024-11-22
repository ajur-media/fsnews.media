# 0.13.0

## `unlinkStoredTitleImages()`

Возвращает не `int`, а `Result` с полями:

- `KEY->deleted_count` - количество удаленных файлов

DATA содержит массив `[ file => путь, success => статус удаления]`

## `getYoutubeVideoTitle()` 

Возвращает не строку, а Result с полем `title`

В случае ошибки поля `video_info` и `player_response` содержат ответы сервера

## `prepareMediaProperties()`

Сигнатура метода в библиотеке:
```php
prepareMediaProperties(
        array $row = [],
        bool $is_report = false,
        bool $prepend_domain = false,
        bool $target_is_mobile = false,
        string $domain_prefix = ''):array
```

В админке сигнатура метода:
```php
prepareMediaProperties(
        $row, 
        $is_report = false, 
        $prepend_domain = false, 
        $domain_prefix = ''
        )
```
Таким образом, библиотечный метод нужно вызывать, передавая ему в `target_is_mobile` строго false.

## uploadImage()

Возвращает не строчку имени файла, а структуру Result, в которой нас интересует DATA:
```php
[
    'thumbnails'    =>  [[ $fn_target, $method, $max_width, $max_height, $quality ]] /* массив сгенерированных превью */
    'fn_resource'   => "{$radix}.{$source_extension}",
    'radix'         =>  $radix,
    'extension'     =>  $source_extension,
    'fn_origin'     =>  $fn_origin,
    'status'        =>  'pending',
    'type'          =>  self::MEDIA_TYPE_PHOTOS
]
```
Прежней строчке эквивалентно поле `fn_resource` (сумма `radix` + `extension`)

## uploadAudio()

Возвращает не строчку имени загруженного файла (`{$radix}.mp3`), а Result-структуру:
```php
[
    'filename'  =>  $fn_origin,  /* оригинальный файл с внутренним именем, сохраненный на диск  */
    'radix'     =>  $radix,     /* корень имени */
    'extension' =>  $source_extension,  /* расширение файла (на основе MIME-типа) */
    'status'    =>  'pending',
    'type'      =>  self::MEDIA_TYPE_AUDIO
]
```

## uploadVideo()

Уже используется Result.