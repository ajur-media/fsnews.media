<?php

namespace AJUR\FSNews\Media\Workers;

use AJUR\FSNews\Media;
use AJUR\FSNews\MediaInterface;
use AJUR\Wrappers\GDWrapper;
use Arris\Entity\Result;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Заготовка класса для работы с RuTube.
 * Предоставляет методы
 *
 */
class RuTube
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct($logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function getVideoTitle(string $video_id, string $default = ''):Result
    {
        $result = new Result();
        $result->title = $default;
        return $result;
    }

    /**
     * Скачивает превьюшку указанного видео с RuTube
     *
     * @param $url
     * @param $fn_default_preview
     * @return Result
     */
    public function getVideoThumbnail($url, $fn_default_preview):Result
    {
        $result = new Result();

        $result->addMessage("Для загрузки передан корректный URL [{$url}]");

        $storage_path = Media::getAbsoluteResourcePath('rutube');
        Media::validatePath($storage_path);

        $target_filename = Media::generateNewFile($storage_path); //@todo: если мы решим добавлять суффикс к имени файла - то можно указать его при вызове
        $target_file = "{$storage_path}/{$target_filename}";

        $result->addMessage("Сгенерировано новое уникальное имя файла: " . $target_file);

        //@todo: вот тут мы должны получить видео и превьюшку к нему
        $source_url = $fn_default_preview;

        // Но вместо этого возвращаем дефолтную превьюшку

        $result->setData('thumbnails', []);

        foreach (Media::$convert_sizes['rutube'] as $params) {
            $prefix = $params['prefix'];
            GDWrapper::getFixedPicture($source_url, "{$storage_path}/{$prefix}{$target_filename}", $params['maxWidth'], $params['maxHeight'], $params['quality']);
            $this->logger->debug('Generating image', [$source_url, "{$storage_path}/{$prefix}{$target_filename}", $params['maxWidth'], $params['maxHeight'], $params['quality']]);

            $result->addData('thumbnails', [[
                'file'      =>  "{$storage_path}/{$prefix}{$target_filename}",
                'width'     =>  $params['maxWidth'],
                'height'    =>  $params['maxHeight'],
                'quality'   =>  $params['quality']
            ]]);
        }

        $result->setData([
            'url'               =>  $url,
            'url_hash'          =>  $url,
            'target_filename'   =>  '',
            'target_file'       =>  '',
            'status'            =>  'ready',
            'mimetype'          =>  '*/rutube',
            'type'              =>  MediaInterface::MEDIA_TYPE_YOUTUBE
        ]);

        return $result;
    }

}