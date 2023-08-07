<?php

namespace AJUR\FSNews\Helpers;

use AJUR\FSNews\MediaInterface;
use AJUR\Wrappers\GDWrapper;
use Arris\Path;
use Arris\Toolkit\MimeTypes;

trait MediaHelpers
{
    /**
     * Возвращает абсолютный путь к ресурсу относительно корня FS.
     * Заканчивается на / или является экземпляром Path
     *
     * @param $type
     * @param string $creation_date
     * @param bool $stringify_path
     * @return Path|string
     */
    public static function getAbsoluteResourcePath($type = 'photos', $creation_date = 'now', bool $stringify_path = true)
    {
        $creation_date = $creation_date == 'now' ? time() : strtotime($creation_date);

        $path = Path::create( self::$options['storage.root'])
            ->join( self::getContentDir($type) )
            ->join( date('Y', $creation_date) )
            ->join( date('m', $creation_date) )
            ->setOptions(['isAbsolute'=>true]);

        return $stringify_path ? $path->toString(true) : $path;
    }

    /**
     * Возвращает путь к ресурсу относительно корня STORAGE (и только путь). Начинается с /, заканчивается на /
     *
     * Для определения каталога к типу контента используется mapping
     *
     * @param $type
     * @param $creation_date
     * @param $stringify_path
     * @return Path|string
     */
    public static function getRelativeResourcePath($type = 'photos', $creation_date = 'now', $stringify_path = true)
    {
        $creation_date = $creation_date == 'now' ? time() : strtotime($creation_date);

        $path = Path::create( self::getContentDir($type), true )
            ->join( date('Y', $creation_date) )
            ->join( date('m', $creation_date) );

        return $stringify_path ? $path->toString(true) : $path;
    }

    /**
     * Возвращает абсолютный URL к ресурсу
     *
     * @param $type
     * @param $creation_date
     * @param $stringify
     * @return void
     */
    public static function getAbsoluteResourceURI($type = 'photos', $creation_date = 'now', $stringify = true)
    {
        $creation_date = $creation_date == 'now' ? time() : strtotime($creation_date);

        //@todo ...


    }

    /**
     * Проверяет существование пути
     *
     * @param $path
     * @return bool
     */
    public static function validatePath($path)
    {
        if ($path instanceof Path) {
            $path = $path->toString();
        }

        if (!is_dir($path) && ( !mkdir($path, 0777, true) && !is_dir($path)) ) {
            throw new \RuntimeException( sprintf( 'Directory "%s" can\'t be created', $path ) );
        }

        return true;
    }

    /**
     * @throws \Exception
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
     * @return string
     * @throws \Exception
     */
    public static function generateNewFile($path, int $length = 20, string $extension = '.jpg'): string
    {
        MediaHelpers::validatePath($path); // проверяем существование пути и создаем при необходимости
        do {
            $newfname = MediaHelpers::getRandomFilename( $length ) . $extension;
        } while (is_file( "{$path}/{$newfname}" ));
        return $newfname;
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
        $tn_timestamp = sprintf( "%02d:%02d:%02d", $timestamp / 3600, ($timestamp / 60) % 60, $timestamp % 60 );

        $logger->debug("[VIDEO] Таймштамп для превью:", [ $tn_timestamp ]);

        $w = $sizes['maxWidth'] ?? 1;
        $h = $sizes['maxHeight'] ?? 1;

        $vfscale = "-vf \"scale=iw*min({$w}/iw\,{$h}/ih):ih*min({$w}/iw\,{$h}/ih), pad={$w}:{$h}:({$w}-iw*min({$w}/iw\,{$h}/ih))/2:({$h}-ih*min({$w}/iw\,{$h}/ih))/2\" ";

        $cmd = [
            'bin'       =>  self::$paths['exec.ffmpeg'],
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
     * @param $params
     * @param $logger
     * @return \AJUR\Wrappers\GDImageInfo|false
     */
    public static function resizePreview($source, $target, $params = [], $logger = null)
    {
        if (empty($params)) {
            return false;
        }

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