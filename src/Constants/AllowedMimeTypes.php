<?php

namespace AJUR\FSNews\Constants;

trait AllowedMimeTypes
{
    /**
     * Доступные для аплоада майм-типы
     *
     * @var string[]
     */
    public static array $allowed_mime_types = [
        'audio/',
        'image/',
        'video/',
        'application/pdf',
        'application/msword',
        'application/vnd.ms-powerpoint',
        'application/rtf',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

}