<?php

namespace AJUR\FSNews\Media\Constants;

use Arris\Path;
use RuntimeException;

trait ContentDirs
{
    /**
     * @var int Маска прав доступа по-умолчанию
     */
    private static int $DIR_PERMISSIONS = 0777;

    /**
     * Mapping: тип контента - каталог хранения
     *
     * Используется в методе getRelResourcePath() <-- оно реально используется где-то?
     *
     * @var string[]
     */
    public static array $content_dirs = [
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

    /**
     * Возвращает абсолютный путь к ресурсу относительно корня FS.
     * Заканчивается на / или является экземпляром Path
     *
     * Используется: сайт (getAbsResourcePath)
     *
     * @param string $type
     * @param string $creation_date
     * @param bool $stringify_path
     * @return Path|string
     */
    public static function getAbsoluteResourcePath(string $type = 'photos', string $creation_date = 'now', bool $stringify_path = true)
    {
        $creation_date = $creation_date == 'now' ? time() : strtotime($creation_date);

        $path = Path::create( self::$options['storage.root'])
            ->join( self::getContentDir($type) )
            ->join( date('Y', $creation_date) )
            ->join( date('m', $creation_date) )
            ->setOptions(['isAbsolute'=>true]);

        return $stringify_path ? $path->toString(true) : $path;
    }


    /**
     * Возвращает путь к ресурсу относительно корня STORAGE (и только путь). Начинается с /, заканчивается на /
     *
     * Для определения каталога к типу контента используется mapping
     *
     * Используется: сайт (getRelResourcePath)
     *
     * @param string $type
     * @param string $creation_date
     * @param bool $stringify_path
     * @return Path|string
     */
    public static function getRelativeResourcePath(string $type = 'photos', string $creation_date = 'now', bool $stringify_path = true)
    {
        $creation_date = $creation_date == 'now' ? time() : strtotime($creation_date);

        $path = Path::create( self::getContentDir($type), true )
            ->join( date('Y', $creation_date) )
            ->join( date('m', $creation_date) );

        return $stringify_path ? $path->toString(true) : $path;
    }

    /**
     * Проверяет существование пути
     *
     * @param $path
     * @return bool
     */
    public static function validatePath($path): bool
    {
        if ($path instanceof Path) {
            $path = $path->toString();
        }

        if (!is_dir($path) && ( !mkdir($path, self::$DIR_PERMISSIONS, true) && !is_dir($path)) ) {
            throw new RuntimeException( sprintf( "Directory [%s] can't be created", $path ) );
        }

        return true;
    }



}