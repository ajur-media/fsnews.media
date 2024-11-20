<?php

namespace AJUR\FSNews;

use Arris\Entity\Result;
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

    public static function uploadImage($fn_source, $watermark_corner, LoggerInterface $logger);

    /**
     * Upload Audio
     *
     * @param $fn_source
     * @param LoggerInterface $logger
     * @return Result<string filename, string radix, string status, string type>
     * @throws \Exception
     */
    public static function uploadAudio($fn_source, LoggerInterface $logger = null);

    /**
     * Upload Abstract File
     *
     * @param $fn_source
     * @param LoggerInterface $logger
     * @return Result
     * @throws \Exception
     */
    public static function uploadAnyFile($fn_source, LoggerInterface $logger = null);

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
    public static function uploadVideo($fn_source, LoggerInterface $logger = null);


    public static function getYoutubeVideoTitle(string $video_id, string $default = ''):Result;

    /**
     * Удаляет тайтловое изображение и все его превьюшки
     *
     * @param $radix
     * @param $cdate
     * @param LoggerInterface $logger
     * @return int
     */
    public static function unlinkStoredTitleImages($filename, $cdate, LoggerInterface $logger = null):int;


    public static function prepareMediaProperties(array $row = [], bool $is_report = false, bool $prepend_domain = false, bool $target_is_mobile = false, string $domain_prefix = ''):array;
}