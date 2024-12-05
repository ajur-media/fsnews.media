<?php

namespace AJUR\FSNews\Media\Workers;

use AJUR\FSNews\Media\Constants\ContentDirs;
use AJUR\FSNews\Media\Constants\ConvertSizes;
use AJUR\FSNews\Media\Helpers\MediaHelpers;
use AJUR\FSNews\MediaException;
use AJUR\FSNews\MediaInterface;
use Arris\Path;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Arris\Entity\Result;

/**
 * Воркер для файлов любого (неизвестного) типа и общие обработчики
 */
class Any
{
    // deleteAbstractFile(type, cdate, radix name)

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

    public function upload($fn_source, LoggerInterface $logger = null):Result
    {
        $logger = $logger ?? $this->logger ?? new NullLogger();

        $logger->debug('[FILE] Обрабатываем как абстрактный файл (audio/*)');

        $path = ContentDirs::getAbsoluteResourcePath('files', 'now');
        ContentDirs::validatePath($path);
        $radix = MediaHelpers::getRandomFilename(20);
        $source_extension = MediaHelpers::detectFileExtension($fn_source);
        $filename_original = "{$radix}.{$source_extension}";

        $logger->debug("[FILE] Загруженный файл будет иметь корень имени:", [ $filename_original ]);

        $prefix = ConvertSizes::getConvertSizes('files._.prefix');

        // никаких действий над файлом не совершается
        $fn_target = Path::create($path)->joinName("{$prefix}{$filename_original}")->toString(); // ПРЕФИКС УЖЕ СОДЕРЖИТ `_`

        if (!move_uploaded_file($fn_source, $fn_target)) {
            $logger->error("[FILE] Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_target}", [ $fn_source, $fn_target ]);
            throw new MediaException("Не удалось сохранить сохранить загруженный файл {$fn_source} как файл оригинала {$fn_target}", -1);
        }

        $logger->debug("[FILE] Загруженный файл {$fn_source} сохранён как оригинал в файл {$fn_target}: ", [ $fn_source, $fn_target ]);

        $logger->debug('[FILE] Stored as', [ $fn_target ]);
        $logger->debug('[FILE] Returned', [ $fn_target]);

        return (new Result())->setData([
            'filename'      =>  "{$prefix}{$filename_original}",
            'path'          =>  $path,
            'radix'         =>  $radix,
            'status'        =>  'ready',
            'type'          =>  MediaInterface::MEDIA_TYPE_FILE
        ]);

    }



}