<?php

namespace AJUR\FSNews;

use Arris\Entity\Result;
use Arris\Path;
use Psr\Log\LoggerInterface;

interface MediaInterface
{
    const DICTIONARY_FULL = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz';

    const DICTIONARY = '0123456789abcdefghijklmnopqrstuvwxyz';

    const DICTIONARY_LENGTH = 36;

    const MEDIA_TYPE_TITLE = 'titles';
    const MEDIA_TYPE_VIDEO = 'videos';
    const MEDIA_TYPE_PHOTOS = 'photos';
    const MEDIA_TYPE_AUDIO = 'audios';
    const MEDIA_TYPE_FILE = 'files';
    const MEDIA_TYPE_YOUTUBE = 'youtube';

    /**
     * @param array $content_dirs
     * @param array $options
     * @param LoggerInterface|null $logger
     *
     * Must unit:
     *
     * Media::init([
     *   'path.storage'     =>  Path::create( getenv('PATH.INSTALL'), true )->join('www')->join('i')->toString(true),
     *   'path.watermarks'  =>   Path::create( getenv('PATH.INSTALL'), true)->join('www/frontend/images/watermarks/')->toString(true)
     * ], [], $logger)
     */
    public static function init(array $options = [], array $content_dirs = [], LoggerInterface $logger = null);

    /**
     * upload & create thumbnails for Embedded Photo
     *
     * @param string|Path $fn_source
     * @param $watermark_corner
     * @param LoggerInterface $logger
     * @return Result
     * @throws \Exception
     */
    public static function uploadImage($fn_source, $watermark_corner, LoggerInterface $logger);

    /**
     * Upload Audio
     *
     * @param $fn_source
     * @param LoggerInterface $logger
     * @return Result<string filename, string radix, string status, string type>
     * @throws \Exception
     */
    public static function uploadAudio($fn_source, LoggerInterface $logger);

    /**
     * Upload Abstract File
     *
     * @param $fn_source
     * @param LoggerInterface $logger
     * @return Result
     * @throws \Exception
     */
    public static function uploadAnyFile($fn_source, LoggerInterface $logger);

    /**
     * Загружает видео и строит превью
     *
     * Можно использовать https://packagist.org/packages/php-ffmpeg/php-ffmpeg , но я предпочел нативный метод, через
     * прямые вызовы shell_exec()
     *
     * @param $fn_source
     * @param LoggerInterface $logger
     * @return Result
     * @throws \Exception
     */
    public static function uploadVideo($fn_source, LoggerInterface $logger);

    /**
     * Загружает с ютуба название видео. Точно работает с видео, с shorts не проверялось.
     *
     * @param string $video_id
     * @param string $default
     * @return string
     */
    public static function getYoutubeVideoTitle(string $video_id, string $default = ''):string;

    /**
     * Удаляет тайтловое изображение и все его превьюшки
     *
     * @param $radix
     * @param $cdate
     * @param LoggerInterface $logger
     * @return int
     */
    public static function unlinkStoredTitleImages($filename, $cdate, LoggerInterface $logger):int;

    /**
     * Для указанного медиа-файла генерирует новое имя для превью и прочего
     *
     * @param $row
     * @param $target_is_mobile - замена строки `LegacyTemplate::$use_mobile_template` или `$CONFIG['AREA'] === "m"`
     * @param $is_report
     * @param $prepend_domain
     * @param $domain_prefix - домен - `config('domains.storage.default')` или `global $CONFIG['domains']['storage']['default']`
     *                          заменено на self::$options['domain.storage']
     * @return mixed
     */
    public static function prepareMediaProperties($row, $target_is_mobile = false, $is_report = false, $prepend_domain = false, $domain_prefix = '');
}