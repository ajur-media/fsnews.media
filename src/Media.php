<?php

namespace AJUR\FSNews;

use AJUR\FSNews\Media\Constants\AllowedMimeTypes;
use AJUR\FSNews\Media\Constants\ContentDirs;
use AJUR\FSNews\Media\Constants\ConvertSizes;
use AJUR\FSNews\Media\Helpers\DTHelper;
use AJUR\FSNews\Media\Helpers\MediaHelpers;
use AJUR\Wrappers\GDWrapper;
use Arris\Entity\Result;
use Arris\Path;
use Arris\Toolkit\MimeTypes;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

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
    private static array $options = [];

    public static function init(array $options = [], array $content_dirs = [], LoggerInterface $logger = null)
    {
        if (!empty($content_dirs)) {
            foreach ($content_dirs as $from => $to) {
                self::$content_dirs[ $from ] = $to;
            }
        }

        self::$options['storage.root'] = $options['path.storage'] ?? '/'; //@required
        self::$options['watermarks'] = $options['path.watermarks'] ?? ''; //@required
        self::$options['exec.ffprobe'] = $options['exec.ffprobe'] ?? 'ffprobe';
        self::$options['exec.ffmpeg'] = $options['exec.ffmpeg'] ?? 'ffmpeg';
        self::$options['domain.storage.default'] = $options['domain.storage.default'] ?? ''; //@required

        self::$logger = is_null($logger) ? new NullLogger() : $logger;
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

        $logger->debug('[PHOTO] Обрабатываем как фото (image/*)');

        if (empty($fn_source) || !is_file($fn_source)) {
            $logger->error('Invalid source file for image upload.', ['fn_source' => $fn_source]);
            return new Result(false, 'Invalid source file.');
        }

        $result = new Result();
        $result->setData('thumbnails', []);

        $path = self::getAbsoluteResourcePath('photos', 'now');
        self::validatePath($path);
        $radix = self::getRandomFilename(20);
        $source_extension = MimeTypes::fromExtension( MimeTypes::fromFilename($fn_source) );

        $resource_filename = "{$radix}.{$source_extension}";

        $logger->debug("[PHOTO] Загруженное изображение будет иметь корень имени:", [ $resource_filename ]);

        $available_photo_sizes = self::getConvertSizes('photos');

        foreach ($available_photo_sizes as $size => $params) {
            $method = $params['method'];
            $max_width = $params['maxWidth'];
            $max_height = $params['maxHeight'];
            $quality = $params['quality'];
            $prefix = $params['prefix'];

            $fn_target = Path::create($path)->joinName("{$prefix}{$resource_filename}")->toString(); // ПРЕФИКС УЖЕ СОДЕРЖИТ `_`

            if (!call_user_func_array($method, [ $fn_source, $fn_target, $max_width, $max_height, $quality ])) {
                foreach ($available_photo_sizes as $inner_size => $inner_params) {
                    @unlink( Path::create($path)->joinName("{$inner_params['prefix']}{$resource_filename}"));
                }
                $logger->error('[PHOTO] Не удалось сгенерировать превью с параметрами: ', [ $method, $max_width, $max_height, $quality, $fn_target ]);
                throw new RuntimeException("Ошибка конвертации загруженного изображения в размер [$prefix]", -1);
            }
            $logger->debug('[PHOTO] Сгенерировано превью: ', [ $method, $max_width, $max_height, $quality, $fn_target ]);

            $result->addData('thumbnails', [[ $fn_target, $method, $max_width, $max_height, $quality ]]);

            if (!is_null($watermark_corner) && isset($params['wmFile']) && $watermark_corner > 0) {
                $fn_watermark = Path::create( self::$options['watermarks'] )->joinName($params['wmFile'])->toString();

                GDWrapper::addWaterMark($fn_target, [
                    'watermark' =>  $fn_watermark,
                    'margin'    =>  $params['wmMargin'] ?? 10
                ], $watermark_corner);

                $logger->debug("[PHOTO] Сгенерирована вотермарка в {$watermark_corner} углу для файла {$fn_target}");
            }
        }

        // сохраняем оригинал (в конфиг?)
        $prefix_origin = "origin_";
        $fn_origin = Path::create($path)->joinName("{$prefix_origin}{$resource_filename}")->toString();
        if (!move_uploaded_file($fn_source, $fn_origin)) {
            $logger->error("[PHOTO] Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_origin}", [ $fn_source, $fn_origin ]);

            // но тогда нужно удалить и все превьюшки
            foreach ($available_photo_sizes as $inner_size => $inner_params) {
                @unlink( Path::create($path)->joinName("{$inner_params['prefix']}{$resource_filename}"));
            }

            throw new MediaException("Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_origin}", -1);
        }

        $logger->debug("[PHOTO] Загруженный файл {$fn_source} сохранён как оригинал в файл {$fn_origin}: ", [ $fn_source, $fn_origin ]);

        $result->setData([
            'fn_resource'   => "{$radix}.{$source_extension}",
            'radix'         =>  $radix,
            'extension'     =>  $source_extension,
            'fn_origin'     =>  $fn_origin,
            'status'        =>  'pending',
            'type'          =>  self::MEDIA_TYPE_PHOTOS
        ]);

        return $result;
    }

    /**
     * Upload Audio
     *
     * @param $fn_source
     * @param LoggerInterface $logger
     * @return Result<string filename, string radix, string status, string type>
     * @throws Exception
     */
    public static function uploadAudio($fn_source, LoggerInterface $logger = null)
    {
        $logger = $logger ?? self::$logger ?? new NullLogger();

        $logger->debug('[AUDIO] Обрабатываем как аудио (audio/*)');

        if (empty($fn_source) || !is_file($fn_source)) {
            $logger->error('Invalid source file for image upload.', ['fn_source' => $fn_source]);
            return new Result(false, 'Invalid source file.');
        }

        $path = self::getAbsoluteResourcePath('audios', 'now');
        self::validatePath($path);
        $radix = self::getRandomFilename(20);

        $source_extension = MimeTypes::fromExtension( MimeTypes::fromFilename($fn_source) );

        $filename_original = "{$radix}.{$source_extension}";

        $logger->debug("[AUDIO] Загруженный аудиофайл будет иметь корень имени:", [ $filename_original ]);

        // $prefix = current(self::$convert_sizes['audios'])['prefix'];
        $prefix = ConvertSizes::getConvertSizes('audios._.prefix');

        // ничего не конвертируем, этим займется крон-скрипт
        $fn_origin = Path::create($path)->joinName("{$prefix}{$filename_original}")->toString(); // ПРЕФИКС УЖЕ СОДЕРЖИТ `_`

        if (!move_uploaded_file($fn_source, $fn_origin)) {
            $logger->error("[AUDIO] Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_origin}", [ $fn_source, $fn_origin ]);
            throw new MediaException("Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_origin}", -1);
        }

        $logger->debug("[AUDIO] Загруженный файл {$fn_source} сохранён в файл {$fn_origin}: ", [ $fn_source, $fn_origin ]);

        $logger->debug('[AUDIO] Stored as', [ $fn_origin ]);
        $logger->debug('[AUDIO] Returned', [ $fn_origin ]);

        return (new Result())->setData([
            'filename'  =>  $fn_origin,
            'radix'     =>  $radix,
            'extension' =>  $source_extension,
            'status'    =>  'pending',
            'type'      =>  self::MEDIA_TYPE_AUDIO
        ]);
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

        $logger->debug('[FILE] Обрабатываем как аудио (audio/*)');

        $path = self::getAbsoluteResourcePath('audios', 'now');
        self::validatePath($path);
        $radix = self::getRandomFilename(20);
        $source_extension = MimeTypes::fromExtension( MimeTypes::fromFilename($fn_source) );
        $filename_origin = "{$radix}.{$source_extension}";

        $logger->debug("[FILE] Загруженный аудиофайл будет иметь корень имени:", [ $filename_origin ]);

        // $prefix = current(self::$convert_sizes['files'])['prefix'];
        $prefix = ConvertSizes::getConvertSizes('files._.prefix');

        // никаких действий над файлом не совершается
        $fn_target = Path::create($path)->joinName("{$prefix}{$filename_origin}")->toString(); // ПРЕФИКС УЖЕ СОДЕРЖИТ `_`

        if (!move_uploaded_file($fn_source, $fn_target)) {
            $logger->error("[FILE] Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_target}", [ $fn_source, $fn_target ]);
            throw new MediaException("Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_target}", -1);
        }

        $logger->debug("[FILE] Загруженный файл {$fn_source} сохранён как оригинал в файл {$fn_target}: ", [ $fn_source, $fn_target ]);

        $logger->debug('[FILE] Stored as', [ $fn_target ]);
        $logger->debug('[FILE] Returned', [ $fn_target]);

        return (new Result())->setData([
            'filename'  =>  $fn_target,
            'radix'     =>  $radix,
            'status'    =>  'ready',
            'type'      =>  self::MEDIA_TYPE_FILE
        ]);
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
     * @throws Exception
     */
    public static function uploadVideo($fn_source, LoggerInterface $logger = null): Result
    {
        $logger = $logger ?? self::$logger ?? new NullLogger();

        $logger->debug('[VIDEO] Обрабатываем как видео (video/*)');

        $ffprobe = self::$options['exec.ffprobe'];

        $json = shell_exec("{$ffprobe} -v quiet -print_format json -show_format -show_streams {$fn_source} 2>&1");
        $json = json_decode($json, true);

        if (!array_key_exists('format', $json)) {
            $message = "[VIDEO] Это не видеофайл: отсутствует секция FORMAT";
            $logger->debug($message);
            throw new MediaException($message);
        }

        $json_format = $json['format'];

        if (!array_key_exists('streams', $json)) {
            $message = "[VIDEO] Это не видеофайл: отсутствует секция STREAMS";
            $logger->debug($message);
            throw new MediaException($message);
        }

        $json_stream_video = current(array_filter($json['streams'], function ($v) {
            return ($v['codec_type'] == 'video');
        }));

        if (empty($json_stream_video)) {
            $message = "[VIDEO] Это не видеофайл: отсутствует видеопоток";
            $logger->debug($message);
            throw new MediaException($message);
        }

        $video_duration = round($json_stream_video['duration']) ?: round($json_format['duration']);
        $video_bitrate = round($json_stream_video['bit_rate']) ?: round($json_format['bit_rate']);

        if ($video_duration <= 0) {
            throw new RuntimeException("[VIDEO] Видеофайл не содержит видеопоток или видеопоток имеет нулевую длительность");
        }

        $logger->debug("[VIDEO] Длина потока видео {$video_duration}");

        // готовим имя основного файла

        $path = self::getAbsoluteResourcePath('videos', 'now');
        self::validatePath($path);
        $radix = self::getRandomFilename(20);
        $source_extension = MimeTypes::fromExtension( MimeTypes::fromFilename($fn_source) );

        $fn_original = Path::create( $path )->joinName("{$radix}.{$source_extension}")->toString();

        if (!move_uploaded_file($fn_source, $fn_original)) {
            $logger->error("[VIDEO] Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_original}", [ $fn_source, $fn_original ]);
            throw new RuntimeException("Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_original}", -1);
        }

        $logger->debug("[VIDEO] Загруженный файл {$fn_source} сохранён как оригинал в файл {$fn_original}", [ $fn_original ]);

        // теперь генерируем файл-превью
        $params_640x352 = ConvertSizes::$convert_sizes['videos']['640x352'];

        if (!empty($params_640x352)) {
            $prefix_640x352 = $params_640x352['prefix'];
            $fn_preview_640x352 = Path::create($path)->joinName("{$prefix_640x352}{$radix}.jpg")->toString();

            MediaHelpers::makePreviewFromVideo(
                $fn_original,
                $fn_preview_640x352,
                round($video_duration / 2),
                $params_640x352,
                $logger
            );
        }

        // генерируем малые превьюшки. Для наглядности я развернул цикл из двух итераций на два вызова:

        // 100x100
        $params = ConvertSizes::$convert_sizes['videos']['100x100'];
        MediaHelpers::resizePreview(
            $fn_preview_640x352,
            Path::create($path)->joinName("{$params['prefix']}{$radix}.jpg")->toString(),
            $params,
            $logger
        );

        // 440x248
        $params = self::$convert_sizes['videos']['440x248'];
        MediaHelpers::resizePreview(
            $fn_preview_640x352,
            Path::create($path)->joinName("{$params['prefix']}{$radix}.jpg")->toString(),
            $params,
            $logger
        );

        $logger->debug('[VIDEO] Превью сделаны, файл видео сохранён');

        return (new Result())->setData([
            'filename'  =>  "{$radix}.{$source_extension}",
            'radix'     =>  $radix,
            'bitrate'   =>  $video_bitrate,
            'duration'  =>  $video_duration,
            'status'    =>  'pending',
            'type'      =>  self::MEDIA_TYPE_VIDEO
        ]);
    } // uploadVideo

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

        if (empty($domain_prefix)) {
            $domain_prefix = self::$options['domain.storage.default'];
        }
        $type = $row['type'];

        $path = self::getRelativeResourcePath($type, $row['cdate']);
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

        if (is_null($row['descr'])) {
            $row['descr'] = '';
        }

        return $row;
    }

}

# -end- #