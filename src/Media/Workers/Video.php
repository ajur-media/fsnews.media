<?php

namespace AJUR\FSNews\Media\Workers;

use AJUR\FSNews\Media;
use AJUR\FSNews\Media\Constants\ContentDirs;
use AJUR\FSNews\Media\Constants\ConvertSizes;
use AJUR\FSNews\Media\Helpers\MediaHelpers;
use AJUR\FSNews\MediaException;
use AJUR\FSNews\MediaInterface;
use Arris\Path;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Arris\Entity\Result;

class Video
{
    /**
     * @var NullLogger|null
     */
    public $logger;

    public array $options = [];

    public function __construct(array $options = [], $logger = null)
    {
        $this->options = $options;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param $fn_source
     * @param LoggerInterface|null $logger
     * @return Result
     * @throws \Exception
     */
    public function upload($fn_source, LoggerInterface $logger = null):Result
    {
        $logger = $logger ?? $this->logger ?? new NullLogger();

        $result = new Result();

        $logger->debug('[VIDEO] Обрабатываем как видео (video/*)');

        if (!is_readable($fn_source)) {
            $result->error("{$fn_source} unreadable");
            return $result;
        }

        $json = $this->getVideoInfo($fn_source);

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
            throw new MediaException("[VIDEO] Видеофайл не содержит видеопоток или видеопоток имеет нулевую длительность");
        }

        $logger->debug("[VIDEO] Длина потока видео {$video_duration}");

        // готовим имя основного файла

        $path = ContentDirs::getAbsoluteResourcePath('videos', 'now');
        ContentDirs::validatePath($path);

        $radix = MediaHelpers::getRandomFilename(20);
        $source_extension = MediaHelpers::detectFileExtension($fn_source);

        $fn_original = Path::create( $path )->joinName("{$radix}.{$source_extension}")->toString();

        if (!move_uploaded_file($fn_source, $fn_original)) {
            $logger->error("[VIDEO] Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_original}", [ $fn_source, $fn_original ]);
            throw new MediaException("Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_original}", -1);
        }

        $logger->debug("[VIDEO] Загруженный файл {$fn_source} сохранён как оригинал в файл {$fn_original}", [ $fn_original ]);

        $result->setData('thumbnails', []);

        foreach (ConvertSizes::getConvertSizes('videos') as $tn_params) {
            $prefix = $tn_params['prefix'];
            $tn_file = Path::create($path)->joinName("{$prefix}{$radix}.jpg")->toString();

            $r = $this->getVideoThumbnail(
                $fn_original,
                $tn_file,
                round($video_duration / 2),
                $tn_params,
                $logger
            );

            $result->addData('thumbnails', [[
                'target'    =>  $tn_file,
                'time'      =>  $r->getData('execution_time'),
                'cmd'       =>  $r->getData('shell_command'),
                '_'         =>  $r->serialize()
            ]]);
        }

        $logger->debug('[VIDEO] Превью сделаны, файл видео сохранён');

        $result->setData([
            'filename'      =>  "{$radix}.{$source_extension}",
            'path'          =>  $path,
            'radix'         =>  $radix,
            'bitrate'       =>  $video_bitrate,
            'duration'      =>  $video_duration,
            'status'        =>  'pending',
            'type'          =>  MediaInterface::MEDIA_TYPE_VIDEO
        ]);

        return $result;
    }


    /**
     * Генерирует превью для видео
     *
     * @param string $source    - исходное видео, имя файла с путём
     * @param string $target    - сгенерированное имя файда с путём для превью
     * @param float $timestamp  - деление на 2 делается вне функции
     * @param array $sizes      - параметры
     * @param LoggerInterface $logger
     * @return Result
     */
    public function getVideoThumbnail(string $source, string $target, float $timestamp, array $sizes, LoggerInterface $logger):Result
    {
        $logger = $logger ?? $this->logger;
        $result = new Result();

        $tn_timestamp = sprintf( "%02d:%02d:%02d", $timestamp / 3600, ($timestamp / 60) % 60, $timestamp % 60 );

        $logger->debug("[VIDEO] Таймштамп для превью:", [ $tn_timestamp ]);

        $w = $sizes['maxWidth'] ?? 1;
        $h = $sizes['maxHeight'] ?? 1;

        $vfscale = "-vf \"scale=iw*min({$w}/iw\,{$h}/ih):ih*min({$w}/iw\,{$h}/ih), pad={$w}:{$h}:({$w}-iw*min({$w}/iw\,{$h}/ih))/2:({$h}-ih*min({$w}/iw\,{$h}/ih))/2\" ";

        $cmd = [
            'bin'       =>  Media::$options['exec.ffmpeg'],
                            '-hide_banner',
                            '-y',
            'ss'        =>  "-ss {$tn_timestamp}",
            'accurate'  =>  Media::$options['no_accurate_seek'] ? "-noaccurate_seek" : ' ',
            'source'    =>  "-i {$source}",
                            "-an",
            // 'ss'        =>  "-ss {$tn_timestamp}",
                            "-r 1",
                            "-vframes 1",
            'sizes'     =>  "-s {$w}x{$h}",
            'scale'     =>  $vfscale,
            'imagetype' =>  "-f mjpeg",
            'target'    =>  $target,
            'verbose'   =>  '2>/dev/null 1>/dev/null'
        ];

        $logger->debug("[VIDEO] Файл превью: {$target}");
        $cmd = implode(' ', $cmd);

        $logger->debug("[VIDEO] FFMpeg команда для генерации превью: ", [ $cmd ]);

        $t = microtime(true);
        shell_exec( $cmd );
        $tdiff = microtime(true) - $t;

        while (!is_file( $target )) {
            sleep( 1 );
            $logger->debug("[VIDEO] Секунду спустя превью не готово");
        }

        $result->setData([
            'filename'      =>  $target,
            'timestamp'     =>  $tn_timestamp,
            'vfscale'       =>  $vfscale,
            'shell_command' =>  $cmd,
            'execution_time'=>  round($tdiff, 6)
        ]);

        return $result;
    }

    /**
     * Возвращает инфо о видеофайле в JSON-deserialized
     *
     * @param $fn_source
     * @return array
     */
    public function getVideoInfo($fn_source): array
    {
        $ffprobe = $this->options['exec.ffprobe'];

        $cmd = "{$ffprobe} -v quiet -print_format json -show_format -show_streams {$fn_source} 2>&1";

        $json = shell_exec($cmd);
        $json = json_decode($json, true);
        return $json;
    }

}