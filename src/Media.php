<?php

namespace AJUR\FSNews;

use AJUR\FSNews\Constants\AllowedMimeTypes;
use AJUR\FSNews\Constants\ContentDirs;
use AJUR\FSNews\Constants\ConvertSizes;
use AJUR\FSNews\Helpers\DTHelper;
use AJUR\FSNews\Helpers\MediaHelpers;
use AJUR\Wrappers\GDWrapper;
use Arris\Entity\Result;
use Arris\Path;
use Arris\Toolkit\MimeTypes;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class Media implements MediaInterface
{
    use ConvertSizes, ContentDirs, AllowedMimeTypes, MediaHelpers;

    /**
     * @var LoggerInterface
     */
    protected static $logger;

    /**
     * @var array
     */
    private static $options = [];


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
        self::$options['domain.storage'] = $options['domains.storage'] ?? ''; //@required


        self::$logger = is_null($logger) ? new NullLogger() : $logger;
    }

    /**
     * upload & create thumbnails for Embedded Photo
     *
     * @todo: return Result<radix, type>
     *
     * @param string|Path $fn_source
     * @param $watermark_corner
     * @param LoggerInterface $logger
     * @return string
     * @throws \Exception
     */
    public static function uploadImage($fn_source, $watermark_corner, LoggerInterface $logger)
    {
        $logger->debug('[PHOTO] Обрабатываем как фото (image/*)');

        $path = self::getAbsoluteResourcePath('photos', 'now');

        self::validatePath($path);

        $resource_name = self::getRandomFilename(20);

        $source_extension = MimeTypes::fromExtension( MimeTypes::fromFilename($fn_source) );

        $radix = "{$resource_name}.{$source_extension}";

        $logger->debug("[PHOTO] Загруженное изображение будет иметь корень имени:", [ $radix ]);

        $available_photo_sizes = self::$convert_sizes['photos'];

        foreach ($available_photo_sizes as $size => $params) {
            $method = $params['method'];
            $max_width = $params['maxWidth'];
            $max_height = $params['maxHeight'];
            $quality = $params['quality'];
            $prefix = $params['prefix'];

            $fn_target = Path::create($path)->joinName("{$prefix}{$radix}")->toString(); // ПРЕФИКС УЖЕ СОДЕРЖИТ `_`

            if (!call_user_func_array($method, [ $fn_source, $fn_target, $max_width, $max_height, $quality ])) {
                foreach ($available_photo_sizes as $inner_size => $inner_params) {
                    @unlink( Path::create($path)->joinName("{$inner_params['prefix']}{$radix}"));
                }
                $logger->error('[PHOTO] Не удалось сгенерировать превью с параметрами: ', [ $method, $max_width, $max_height, $quality, $fn_target ]);
                throw new RuntimeException("Ошибка конвертации загруженного изображения в размер [$prefix]", -1);
            }

            $logger->debug('[PHOTO] Сгенерировано превью: ', [ $method, $max_width, $max_height, $quality, $fn_target ]);

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
        $fn_origin = Path::create($path)->joinName("origin_{$radix}")->toString();
        if (!move_uploaded_file($fn_source, $fn_origin)) {
            $logger->error("[PHOTO] Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_origin}", [ $fn_source, $fn_origin ]);

            // но тогда нужно удалить и все превьюшки
            foreach ($available_photo_sizes as $inner_size => $inner_params) {
                @unlink( Path::create($path)->joinName("{$inner_params['prefix']}{$radix}"));
            }

            throw new MediaException("Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_origin}", -1);
        }

        $logger->debug("[PHOTO] Загруженный файл {$fn_source} сохранён как оригинал в файл {$fn_origin}: ", [ $fn_source, $fn_origin ]);

        return $radix;
    }

    /**
     * Upload Audio
     *
     * @todo: return Result<radix, type>
     *
     * @param $fn_source
     * @param LoggerInterface $logger
     * @return string
     * @throws \Exception
     */
    public static function uploadAudio($fn_source, LoggerInterface $logger)
    {
        $logger->debug('[AUDIO] Обрабатываем как аудио (audio/*)');

        $path = self::getAbsoluteResourcePath('audios', 'now');

        self::validatePath($path);

        $resource_name = self::getRandomFilename(20);

        $source_extension = MimeTypes::fromExtension( MimeTypes::fromFilename($fn_source) );

        $radix = "{$resource_name}.{$source_extension}";

        $logger->debug("[AUDIO] Загруженный аудиофайл будет иметь корень имени:", [ $radix ]);

        $prefix = current(self::$convert_sizes['audios'])['prefix'];

        // ничего не конвертируем, этим займется крон-скрипт
        $fn_target = Path::create($path)->joinName("{$prefix}{$radix}")->toString(); // ПРЕФИКС УЖЕ СОДЕРЖИТ `_`

        if (!move_uploaded_file($fn_source, $fn_target)) {
            $logger->error("[AUDIO] Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_target}", [ $fn_source, $fn_target ]);
            throw new MediaException("Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_target}", -1);
        }

        $logger->debug("[AUDIO] Загруженный файл {$fn_source} сохранён в файл {$fn_target}: ", [ $fn_source, $fn_target ]);

        $logger->debug('[AUDIO] Stored as', [ $fn_target ]);
        $logger->debug('[AUDIO] Returned', [ $fn_target ]);

        return $radix;
    }

    /**
     * Upload Abstract File
     *
     * @todo: return Result<radix, type>
     *
     * @param $fn_source
     * @param LoggerInterface $logger
     * @return void
     * @throws \Exception
     */
    public static function uploadAnyFile($fn_source, LoggerInterface $logger)
    {
        $logger->debug('[FILE] Обрабатываем как аудио (audio/*)');

        $path = self::getAbsoluteResourcePath('audios', 'now');

        self::validatePath($path);

        $resource_name = self::getRandomFilename(20);

        $source_extension = MimeTypes::fromExtension( MimeTypes::fromFilename($fn_source) );

        $radix = "{$resource_name}.{$source_extension}";

        $logger->debug("[FILE] Загруженный аудиофайл будет иметь корень имени:", [ $radix ]);

        $prefix = current(self::$convert_sizes['audios'])['prefix'];

        // ничего не конвертируем, этим займется крон-скрипт
        $fn_target = Path::create($path)->joinName("{$prefix}{$radix}")->toString(); // ПРЕФИКС УЖЕ СОДЕРЖИТ `_`


        if (!move_uploaded_file($fn_source, $fn_target)) {
            $logger->error("[FILE] Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_target}", [ $fn_source, $fn_target ]);
            throw new MediaException("Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_target}", -1);
        }
        $logger->debug("[FILE] Загруженный файл {$fn_source} сохранён как оригинал в файл {$fn_target}: ", [ $fn_source, $fn_target ]);

        $logger->debug('[FILE] Stored as', [ $fn_target ]);
        $logger->debug('[FILE] Returned', [ $fn_target]);
    }

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
    public static function uploadVideo($fn_source, LoggerInterface $logger)
    {
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

        return new Result([
            'filename'  =>  "{$radix}.{$source_extension}",
            'bitrate'   =>  $video_bitrate,
            'duration'  =>  $video_duration,
            'status'    =>  'pending',
            'type'      =>  self::MEDIA_TYPE_VIDEO
        ]);
    } // uploadVideo

    /**
     * Загружает с ютуба название видео. Точно работает с видео, с shorts не проверялось.
     *
     * @param string $video_id
     * @param string $default
     * @return string
     */
    public static function getYoutubeVideoTitle(string $video_id, string $default = ''):string
    {
        //@todo: curl?
        $video_info = @file_get_contents("http://youtube.com/get_video_info?video_id={$video_id}");

        if (!$video_info) {
            return $default;
        }

        parse_str($video_info, $vi_array);

        if (!array_key_exists('player_response', $vi_array)) {
            return $default;
        }

        $video_info = json_decode($vi_array['player_response']);

        if (is_null($video_info)) {
            return $default;
        }

        return $video_info->videoDetails->title ?: $default;
    }

    /**
     * Удаляет тайтловое изображение и все его превьюшки
     *
     * @param $radix
     * @param $cdate
     * @param LoggerInterface $logger
     * @return int
     */
    public static function unlinkStoredTitleImages($filename, $cdate, LoggerInterface $logger):int
    {
        $path = MediaHelpers::getAbsoluteResourcePath('titles', $cdate, false);

        $prefixes = array_map(static function($v) {
            return $v['prefix'];
        }, ConvertSizes::$convert_sizes['titles']);

        $deleted_count = 0;

        foreach ($prefixes as $prefix) {
            $fn = $path->joinName($prefix . $filename)->toString();
            $logger->debug("[unlinkStoredTitleImage] Удалятся файл:", [ $fn, @unlink($fn) ]);
            $deleted_count++;
        }

        return $deleted_count;
    }

    /**
     * @param $row
     * @param $target_is_mobile - замена строки `LegacyTemplate::$use_mobile_template` или `$CONFIG['AREA'] === "m"`
     * @param $is_report
     * @param $prepend_domain
     * @param $domain_prefix - домен - `config('domains.storage.default')` или `global $CONFIG['domains']['storage']['default']`
     *                          заменено на self::$options['domain.storage']
     * @return mixed
     */
    public static function prepareMediaProperties($row, $target_is_mobile = false, $is_report = false, $prepend_domain = false, $domain_prefix = '')
    {
        if (empty($domain_prefix)) {
            $domain_prefix = self::$options['domain.storage'];
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

        $row['prefix'] = $is_report ? "440x300" : "100x100";
        $row['report_prefix'] = "590x440";

        if ($row['type'] === "photos") {
            // $this->report NEVER DECLARED (в админке уж точно)
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
            $row['orig_file'] = "590x440_" . $row['file'];     // "590x440_"
        }

        if ($row['type'] === "videos") {
            $row['thumb'] = substr($row['file'], 0, -4) . ".jpg"; //отрезаем расширение и добавляем .jpg (@todo: переделать?)
            $row['sizes'] = [640, 352];
            $row['sizes_thumb'] = "640x352"; // $k[2]
            $row['sizes_video'] = array(640, 352 + 25); //minWidth, minHeight
            $row['orig_file'] = "440x248_" . $row['thumb'];
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

    /* == HELPERS == */




}