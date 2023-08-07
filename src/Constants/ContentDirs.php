<?php

namespace AJUR\FSNews\Constants;

trait ContentDirs
{
    /**
     * Mapping: тип контента - каталог хранения
     *
     * Используется в методе getRelResourcePath()
     *
     * @var string[]
     */
    public static $content_dirs = [
        "title"     =>  "titles",
        "titles"    =>  "titles",
        "photos"    =>  "photos",
        "videos"    =>  "videos",
        "audios"    =>  "audios",
        "youtube"   =>  "youtube",
        "files"     =>  "files",
        "_"         =>  ""
    ];

    public static function getContentDir($type = 'photos'):string
    {
        return  array_key_exists($type, self::$content_dirs)
                ? self::$content_dirs[$type]
                : self::$content_dirs['_'];
    }

}