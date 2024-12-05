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

class Audio
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
    public function upload($fn_source, LoggerInterface $logger = null): Result
    {
        $logger = $logger ?? $this->logger ?? new NullLogger();

        $logger->debug('[AUDIO] Обрабатываем как аудио (audio/*)');

        if (empty($fn_source) || !is_file($fn_source)) {
            $logger->error('Invalid source file for image upload.', ['fn_source' => $fn_source]);
            return new Result(false, 'Invalid source file.');
        }

        $path = ContentDirs::getAbsoluteResourcePath('audios', 'now');
        ContentDirs::validatePath($path);

        $radix = MediaHelpers::getRandomFilename(20);
        $source_extension = MediaHelpers::detectFileExtension($fn_source);

        $filename_original = "{$radix}.{$source_extension}";

        $logger->debug("[AUDIO] Загруженный аудиофайл будет иметь корень имени:", [ $filename_original ]);

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
            'filename'      =>  "{$prefix}{$filename_original}",
            'path'          =>  $path,
            'radix'         =>  $radix,
            'extension'     =>  $source_extension,
            'status'        =>  'pending',
            'type'          =>  MediaInterface::MEDIA_TYPE_AUDIO
        ]);
    }

}