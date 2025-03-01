<?php

namespace AJUR\FSNews\Media\Helpers;

use AJUR\FSNews\Media;
use AJUR\FSNews\MediaInterface;
use AJUR\Wrappers\GDWrapper;
use Arris\Entity\Result;
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
     * @param $source
     * @param $target
     * @param array $params
     * @param $logger
     * @return bool
     */
    public static function resizePreview($source, $target, array $params = [], $logger = null): bool
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
        )->isValid();

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
     * Вычисляет расширение файла на основании MIME-типа
     * (без точки)
     *
     * @param $filepath - путь к файлу
     * @return string
     */
    public static function detectFileExtension($filepath): string
    {
        return MimeTypes::getExtension( self::getMimeType($filepath) );
    }



}