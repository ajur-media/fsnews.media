<?php

namespace AJUR\FSNews\Media\Helpers;

use AJUR\FSNews\Media;
use AJUR\FSNews\MediaInterface;
use AJUR\Wrappers\GDWrapper;
use Arris\Toolkit\MimeTypes;
use Exception;
use Psr\Log\NullLogger;

trait MediaHelpers
{
    /**
     * Возвращает абсолютный URL к ресурсу
     *
     * @param string $type
     * @param string $creation_date
     * @param bool $stringify
     * @return void
     */
    public static function getAbsoluteResourceURI(string $type = 'photos', string $creation_date = 'now', bool $stringify = true)
    {
        $creation_date = $creation_date == 'now' ? time() : strtotime($creation_date);

        //@todo ...

        /*
         * Должно заменить такое:
         *
        $photo['path'] = Media::getAbsoluteResourcePath("titles", $photo['cdate']);
        $photo['path_rel'] = Media::getRelativeResourcePath("titles", $photo['cdate']);

        $photo['url'] = str_replace(
            config('PATH.STORAGE'),
            '',
            $photo['path']
        );
        */


    }



    /**
     * @throws Exception
     */
    public static function getRandomFilename(int $length = 20, string $suffix = '', $prefix_format = 'Ymd'):string
    {
        $dictionary = MediaInterface::DICTIONARY;
        $dictionary_len = MediaInterface::DICTIONARY_LENGTH;

        // если суффикс не NULL, то _суффикс иначе пустая строка
        $suffix = !empty($suffix) ? '_' . $suffix : '';

        $salt = '';
        for ($i = 0; $i < $length; $i++) {
            $salt .= $dictionary[random_int(0, $dictionary_len - 1)];
        }

        return (date_format(date_create(), $prefix_format)) . '_' . $salt . $suffix;
    }

    /**
     * Генерирует имя для нового (еще точно не существующего) файла
     *
     * @param $path
     * @param int $length
     * @param string $extension
     * @param string $suffix - кастомный суффикс-хэш (например, для файлов, созданных на основе видео)
     * @return string
     * @throws Exception
     */
    public static function generateNewFile($path, int $length = 20, string $extension = '.jpg', string $suffix = ''): string
    {
        self::validatePath($path); // проверяем существование пути и создаем при необходимости
        do {
            $new_filename = self::getRandomFilename( $length, $suffix ) . $extension;
        } while (is_file( "{$path}/{$new_filename}" ));
        return $new_filename;
    }

    /**
     * Генерирует картинку из видео по временнОй метке.
     *
     * @param $source    - исходное видео, имя файла с путём
     * @param $target    - сгенерированное имя файда с путём для превью
     * @param $timestamp - деление на 2 делается вне функции
     * @param $sizes     - параметры
     * @param $logger
     * @return string
     */
    public static function makePreviewFromVideo($source, $target, $timestamp, $sizes, $logger):string
    {
        $logger = $logger ?? self::$logger ?? new NullLogger();

        $tn_timestamp = sprintf( "%02d:%02d:%02d", $timestamp / 3600, ($timestamp / 60) % 60, $timestamp % 60 );

        $logger->debug("[VIDEO] Таймштамп для превью:", [ $tn_timestamp ]);

        $w = $sizes['maxWidth'] ?? 1;
        $h = $sizes['maxHeight'] ?? 1;

        $vfscale = "-vf \"scale=iw*min({$w}/iw\,{$h}/ih):ih*min({$w}/iw\,{$h}/ih), pad={$w}:{$h}:({$w}-iw*min({$w}/iw\,{$h}/ih))/2:({$h}-ih*min({$w}/iw\,{$h}/ih))/2\" ";

        $cmd = [
            'bin'       =>  Media::$options['exec.ffmpeg'],
                            '-hide_banner',
                            '-y',
            'source'    =>  "-i {$source}",
                            "-an",
            'ss'        =>  "-ss {$tn_timestamp}",
                            "-r 1",
                            "vframes 1",
            'sizes'     =>  "-s {$w}x{$h}",
            'scale'     =>  $vfscale,
            'imagetype' =>  "-f mjpeg",
            'target'    =>  $target,
            'verbose'   =>  '2>/dev/null 1>/dev/null'
        ];

        $logger->debug("[VIDEO] Файл превью: {$target}");
        $cmd = implode(' ', $cmd);

        $logger->debug("[VIDEO] FFMpeg команда для генерации превью: ", [ $cmd ]);

        shell_exec( $cmd );

        while (!is_file( $target )) {
            sleep( 1 );
            $logger->debug("[VIDEO] Секунду спустя превью не готово");
        }

        return $target;
    }

    /**
     * @param $source
     * @param $target
     * @param array $params
     * @param $logger
     * @return \AJUR\Wrappers\GDImageInfo|false
     */
    public static function resizePreview($source, $target, array $params = [], $logger = null)
    {
        if (empty($params)) {
            return false;
        }

        $logger = $logger ?? self::$logger ?? new NullLogger();

        $logger->debug("[VIDEO] Генерируем превью {$params['prefix']} ({$target}) на основе {$source}", [ $params ]);

        $generate_result = GDWrapper::getFixedPicture(
            $source,
            $target,
            $params['maxWidth'],
            $params['maxHeight'],
            $params['quality']
        )->valid;
        $logger->debug("[VIDEO] Результат генерации превью {$params['prefix']}: ", [ $generate_result ]);

        return $generate_result;
    }

    /**
     * Deserialize JSON data with default value
     *
     * @param $data
     * @param array $default
     * @return array|mixed
     */
    public static function deserialize($data, array $default = [])
    {
        if (empty($data)) {
            return $default;
        }

        $decoded = json_decode($data, true);
        if ($decoded === null) {
            return $default;
        }
        return $decoded;
    }

    /**
     * LEGACY!!!
     * Получает MIME-тип файла
     *
     * @param string $filepath
     * @return string
     */
    public static function getMimeType(string $filepath)
    {
        return mime_content_type($filepath);
    }

    /**
     * LEGACY!!!
     * Получает расширение по MIME-типу
     * (без точки)
     *
     * @param $mime
     * @return mixed|string|null
     */
    public static function getFileExtension($mime)
    {
        return MimeTypes::getExtension($mime);
    }

    /**
     * LEGACY!!!
     * Вычисляет расширение файла по MIME-типу
     * (без точки)
     *
     * @param $filepath
     * @return mixed|string|null
     */
    public static function detectFileExtension($filepath)
    {
        return MimeTypes::getExtension( mime_content_type($filepath) );
    }



}