<?php

namespace AJUR\FSNews;

use AJUR\FSNews\Media\Constants\AllowedMimeTypes;
use AJUR\FSNews\Media\Constants\ContentDirs;
use AJUR\FSNews\Media\Constants\ConvertSizes;
use AJUR\FSNews\Media\Helpers\DTHelper;
use AJUR\FSNews\Media\Helpers\MediaHelpers;
use AJUR\FSNews\Media\Workers\Any;
use AJUR\FSNews\Media\Workers\Audio;
use AJUR\FSNews\Media\Workers\Photo;
use AJUR\FSNews\Media\Workers\Video;
use Arris\Entity\Result;
use Arris\Path;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


class Media implements MediaInterface
{
    use ConvertSizes, ContentDirs, AllowedMimeTypes, MediaHelpers;

    /**
     * @var LoggerInterface
     */
    protected static LoggerInterface $logger;

    /**
     * @var array
     */
    public static array $options = [];

    /**
     * @param array $options
     * @param array $content_dirs
     * @param array $additional_mime_types
     * @param LoggerInterface|null $logger
     *
     * Must unit:
     *
     * Media::init([
     *   'path.storage'     =>  Path::create( getenv('PATH.INSTALL'), true )->join('www')->join('i')->toString(true),
     *   'path.watermarks'  =>  Path::create( getenv('PATH.INSTALL'), true)->join('www/frontend/images/watermarks/')->toString(true)
     * ], [], [], $logger)
     */
    public static function init(array $options = [], array $content_dirs = [], array $additional_mime_types = [], LoggerInterface $logger = null)
    {
        if (!empty($content_dirs)) {
            foreach ($content_dirs as $from => $to) {
                self::$content_dirs[ $from ] = $to;
            }
        }

        if (!empty($additional_mime_types)) {
            self::$allowed_mime_types = array_merge(self::$allowed_mime_types, $additional_mime_types);
            self::$allowed_mime_types = array_unique(self::$allowed_mime_types);
        }

        self::$options['storage.root'] = $options['path.storage'] ?? '/'; //@required
        self::$options['watermarks'] = $options['path.watermarks'] ?? ''; //@required
        self::$options['exec.ffprobe'] = $options['exec.ffprobe'] ?? 'ffprobe';
        self::$options['exec.ffmpeg'] = $options['exec.ffmpeg'] ?? 'ffmpeg';
        self::$options['domain.storage.default'] = $options['domain.storage.default'] ?? ''; //@required

        self::$options['no_accurate_seek'] = $options['no_accurate_seek'] ?: false;

        self::$logger = is_null($logger) ? new NullLogger() : $logger;
    }

    /**
     * Добавляет mime-типы или переопределяет их
     *
     * @param array $mimetypes
     * @param bool $append
     * @return array
     */
    public static function setMimeTypes(array $mimetypes, bool $append = true):array
    {
        if ($append) {
            self::$allowed_mime_types = array_merge(self::$allowed_mime_types, $mimetypes);
        } else {
            self::$allowed_mime_types = $mimetypes;
        }
        self::$allowed_mime_types = array_unique(self::$allowed_mime_types);

        return self::$allowed_mime_types;
    }

    /**
     * Универсальная точка входа - upload
     * Тип определяется внутри
     *
     * @param $fn_source
     * @param $watermark_corner
     * @param LoggerInterface|null $logger
     * @return Result
     * @throws Exception
     * @throws MediaException
     */
    public function upload($fn_source, $watermark_corner, LoggerInterface $logger = null):Result
    {
        $logger = $logger ?? self::$logger ?? new NullLogger();

        $f_info = finfo_open( FILEINFO_MIME_TYPE );
        $f_info_mimetype = finfo_file( $f_info, $fn_source);
        $allow = false;

        $logger->debug( '[UPLOAD] MIME-тип загруженного файла: ', [ $f_info_mimetype ] );
        $logger->debug( '[UPLOAD] Размер   загруженного файла: ', [ filesize($fn_source) ] );

        foreach (AllowedMimeTypes::$allowed_mime_types as $mimetype) {
            if (stripos( $f_info_mimetype, $mimetype ) === 0) {
                $allow = true;
                break;
            }
        }

        if (!$allow) {
            return new Result(false, "[UPLOAD] Попытка загрузить файл с неразрешенным MIME-типом `{$f_info_mimetype}`" );
        }

        if (stripos($f_info_mimetype, 'image/') !== false) {

            $worker = new Photo(self::$options, $logger);
            $result = $worker->upload($fn_source, $watermark_corner);

        } elseif (stripos( $f_info_mimetype, 'audio/' ) !== false) {

            $worker = new Audio(self::$options, $logger);
            $result = $worker->upload($fn_source);

        } elseif (stripos( $f_info_mimetype, 'video/' ) !== false) {

            $worker = new Video(self::$options, $logger);
            $result = $worker->upload($fn_source);

        } else {
            $worker = new Any(self::$options, $logger);
            $result = $worker->upload($fn_source);
        }

        return $result;

    }

    /**
     * upload & create thumbnails for Embedded Photo
     *
     * @param string|Path $fn_source
     * @param $watermark_corner
     * @param LoggerInterface|null $logger
     * @return Result
     * @throws Exception
     */
    public static function uploadImage($fn_source, $watermark_corner, LoggerInterface $logger = null):Result
    {
        $logger = $logger ?? self::$logger ?? new NullLogger();

        $worker = new Photo(self::$options, $logger);

        return $worker->upload($fn_source, $watermark_corner, $logger);
    }

    /**
     * Upload Audio
     *
     * @param $fn_source
     * @param LoggerInterface|null $logger
     * @return Result
     * @throws Exception
     */
    public static function uploadAudio($fn_source, LoggerInterface $logger = null): Result
    {
        $logger = $logger ?? self::$logger ?? new NullLogger();

        $worker = new Audio(self::$options, $logger);
        return $worker->upload($fn_source);
    }

    /**
     * Upload Abstract File
     *
     * @param $fn_source
     * @param LoggerInterface|null $logger
     * @return Result
     * @throws Exception
     */
    public static function uploadAnyFile($fn_source, LoggerInterface $logger = null):Result
    {
        $logger = $logger ?? self::$logger ?? new NullLogger();

        $worker = new Any(self::$options, $logger);
        return $worker->upload($fn_source);
    }

    /**
     * Загружает видео и строит превью
     *
     * Можно использовать https://packagist.org/packages/php-ffmpeg/php-ffmpeg,
     * но я предпочел нативный метод, через прямые вызовы shell_exec()
     *
     * @param $fn_source
     * @param LoggerInterface|null $logger
     * @return Result
     * @throws MediaException|Exception
     */
    public static function uploadVideo($fn_source, LoggerInterface $logger = null): Result
    {
        $logger = $logger ?? self::$logger ?? new NullLogger();

        $worker = new Video(self::$options, $logger);

        return $worker->upload($fn_source);
    }

    /**
     * Удаляет тайтловое изображение и все его превьюшки
     *
     * @param $filename
     * @param $cdate
     * @param LoggerInterface|null $logger
     * @return Result
     */
    public static function unlinkStoredTitleImages($filename, $cdate, LoggerInterface $logger = null):Result
    {
        $logger = $logger ?? self::$logger ?? new NullLogger();

        $r = new Result();

        $path = self::getAbsoluteResourcePath('titles', $cdate, false);

        $prefixes = array_map(static function($v) {
            return $v['prefix'];
        }, ConvertSizes::$convert_sizes['titles']);

        $deleted_count = 0;

        foreach ($prefixes as $prefix) {
            $fn = $path->joinName($prefix . $filename)->toString();
            $success = @unlink($fn);
            $logger->debug("[unlinkStoredTitleImages] Удалятся файл:", [ $fn, $success ]);

            $r->addData('list', [[
                'file'      =>  $fn,
                'success'   =>  $success
            ]]);

            $deleted_count++;
        }
        $r->deleted_count = $deleted_count;

        return $r;
    }

    /**
     * Для указанного медиа-файла генерирует новое имя для превью и прочего
     * Используется: сайт
     *
     * @param array $row
     * @param bool $is_report - Является ли медиаресурс частью фоторепортажа (FALSE)
     * @param bool $prepend_domain - Добавлять ли домен перед путём к ресурсу (FALSE)
     * @param bool $target_is_mobile - Просматривают ли ресурс с мобильного устройства (FALSE) - замена строки `LegacyTemplate::$use_mobile_template` или `$CONFIG['AREA'] === "m"`
     * @param string $domain_prefix - Подставляемый домен (передается через $options['domain.storage']), `config('domains.storage.default')` или `global $CONFIG['domains']['storage']['default']`
     *
     * @return array
     */
    public static function prepareMediaProperties(
        array $row = [],
        bool $is_report = false,
        bool $prepend_domain = false,
        bool $target_is_mobile = false,
        string $domain_prefix = ''):array
    {
        if (empty($row)) {
            return [];
        }

        if (empty($row['type'])) {
            return [];
        }

        if (empty($domain_prefix)) {
            $domain_prefix = self::$options['domain.storage.default'];
        }

        $path = self::getRelativeResourcePath($row['type'], $row['cdate'] ?? 'now');
        if ($prepend_domain === true) {
            $path = $domain_prefix . $path;
        }
        $row['path'] = $path;

        if (isset($row['cdate'])) {
            $row['cdate_rus'] = DTHelper::convertDate($row['cdate']);
        }

        // префикс идет без `_` и в /var/www/47news_v1_admin/www/frontend/js/jquery.file-manager.js он тоже без `_`
        // (подставляется в коде)
        $row['prefix'] = $is_report ? "440x300" : "100x100";
        $row['report_prefix'] = "590x440";

        // $is_report используется только на сайте

        if ($row['type'] === "photos") {
            if ($is_report) {
                if ($target_is_mobile) {
                    $row['sizes'] = [590, 440];          // "590x440" -- mobile_report_tn
                    $row['sizes_full'] = [1280, 1024];     // "1280x1024"  -- mobile_report_full
                } else {
                    $row['sizes'] = [100, 100];          // "100x100"    -- desktop_report_tn
                    $row['sizes_full'] = [440, 300];     // "440x300"    -- desktop_report_full
                }
            } else {
                if ($target_is_mobile) {
                    $row['sizes'] = [440, 300];          // "440x300"
                    $row['sizes_full'] = [1280, 1024];     // "1280x1024"
                } else {
                    $row['sizes'] = [630, 465];          // "630x465"
                    $row['sizes_full'] = [1280, 1024];     // "1280x1024"
                }
            }

            $row['sizes_prev'] = [150, 100];   // "150x100"
            $row['sizes_large'] = [1280, 1024];  // "1280x1024"
            // $row['orig_file'] = "590x440_" . $row['file'];     // было удалено
            $row['orig_file'] = ConvertSizes::$convert_sizes['photos']["630x465"]['prefix'] . $row['file'];
        }

        if ($row['type'] === "videos") {
            $row['thumb'] = substr($row['file'], 0, -4) . ".jpg"; //отрезаем расширение и добавляем .jpg (@todo: переделать?)
            $row['sizes'] = [640, 352];
            $row['sizes_thumb'] = "640x352"; // $k[2]
            $row['sizes_video'] = array(640, 352 + 25); //minWidth, minHeight
            // $row['orig_file'] = "440x248_" . $row['thumb'];
            $row['orig_file'] = ConvertSizes::$convert_sizes['videos']["640x352"]['prefix'] . $row['thumb'];
        }

        if ($row['type'] === "audios") {
            $row['sizes'] = [440, 24];
            $row['orig_file'] = $row['file'];
        }

        if ($row['type'] === "files") {
            $row['orig_file'] = $row['file'];
        }

        if ($row['type'] === "youtube") {
            $row['thumb'] = $row['file'];
            $row['orig_file'] = $row['href'];
            $row['sizes'] = [640, 352];
            $matches = parse_url($row['href']);
            if (preg_match("/v=([A-Za-z0-9\_\-]{11})/i", $matches["query"], $res)) {
                $row['yt'] = $res[1];
            }
            $row['sizes_youtube'] = [640, 360]; // да, именно так, несмотря на то, что ключ - 640x352, то есть значимы параметры, а не ключ
        }

        $row['tags'] = empty($row['tags']) ? [] : MediaHelpers::deserialize($row['tags']);

        if (!isset($row['descr']) || is_null($row['descr'])) {
            $row['descr'] = '';
        }

        return $row;
    }

}

# -end- #