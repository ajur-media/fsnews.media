<?php

namespace AJUR\FSNews\Media\Workers;

use AJUR\FSNews\Media\Constants\ContentDirs;
use AJUR\FSNews\Media\Constants\ConvertSizes;
use AJUR\FSNews\Media\Helpers\MediaHelpers;
use AJUR\FSNews\MediaException;
use AJUR\FSNews\MediaInterface;
use AJUR\Wrappers\GDWrapper;
use Arris\Path;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Arris\Entity\Result;

class Photo
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
     * @throws \Exception
     */
    public function upload($fn_source, $watermark_corner, LoggerInterface $logger = null):Result
    {
        $logger = $logger ?? $this->logger ?? new NullLogger();

        $logger->debug('[PHOTO] Обрабатываем как фото (image/*)');

        if (empty($fn_source) || !is_file($fn_source)) {
            $logger->error('Invalid source file for image upload.', ['fn_source' => $fn_source]);
            return new Result(false, 'Invalid source file.');
        }

        $result = new Result();
        $result->setData('thumbnails', []);

        $path = ContentDirs::getAbsoluteResourcePath('photos', 'now');
        ContentDirs::validatePath($path);
        $radix = MediaHelpers::getRandomFilename(20);
        $source_extension = MediaHelpers::detectFileExtension($fn_source);

        $resource_filename = "{$radix}.{$source_extension}";

        $logger->debug("[PHOTO] Загруженное изображение будет иметь корень имени:", [ $resource_filename ]);

        $available_photo_sizes = ConvertSizes::getConvertSizes('photos');

        foreach ($available_photo_sizes as $size => $params) {
            $method = $params['callback'] ?? $params['method'];
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
                throw new MediaException("Ошибка конвертации загруженного изображения в размер [$prefix]", -1);
            }
            $logger->debug('[PHOTO] Сгенерировано превью: ', [ $method, $max_width, $max_height, $quality, $fn_target ]);

            $result->addData('thumbnails', [[ $fn_target, $method, $max_width, $max_height, $quality ]]);

            if (!is_null($watermark_corner) && isset($params['wmFile']) && $watermark_corner > 0) {
                $fn_watermark = Path::create( $this->options['watermarks'] )->joinName($params['wmFile'])->toString();

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
            'filename'      =>  "{$radix}.{$source_extension}",
            'path'          =>  $path,
            'radix'         =>  $radix,
            'extension'     =>  $source_extension,
            'fn_origin'     =>  $fn_origin,
            'status'        =>  'ready',
            'type'          =>  MediaInterface::MEDIA_TYPE_PHOTOS
        ]);

        return $result;

    }

}