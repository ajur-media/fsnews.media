<?php

namespace AJUR\FSNews;

use Arris\Entity\Result;
use Exception;
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


    public static function init(array $options = [], array $content_dirs = [], array $additional_mime_types = [], LoggerInterface $logger = null);

    public function upload($fn_source, $watermark_corner, LoggerInterface $logger = null):Result;

    public static function uploadImage($fn_source, $watermark_corner, LoggerInterface $logger);

    public static function uploadAudio($fn_source, LoggerInterface $logger = null);

    public static function uploadAnyFile($fn_source, LoggerInterface $logger = null);

    public static function uploadVideo($fn_source, LoggerInterface $logger = null);

    public static function unlinkStoredTitleImages($filename, $cdate, LoggerInterface $logger = null):Result;

    public static function prepareMediaProperties(array $row = [], bool $is_report = false, bool $prepend_domain = false, bool $target_is_mobile = false, string $domain_prefix = ''):array;
}