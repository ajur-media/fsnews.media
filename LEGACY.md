```php
    /**
     * @deprecated  ?
     *
     * + Возвращает ОТНОСИТЕЛЬНЫЙ путь к ресурсу относительно корня STORAGE (и только путь). Начинается с /, заканчивается на /
     *
     * Для определения каталога к типу контента используется mapping
     *
     * @param string $type
     * @param null $cdate
     * @param bool $has_trailing_separator
     * @return string
     */
    public static function getRelResourcePath($type = 'photos', $cdate = null, $has_trailing_separator = true):string
    {
        $cdate = is_null($cdate) ? time() : strtotime($cdate);

        return Path::create([
            self::$content_dirs[$type],
            date('Y', $cdate),
            date('m', $cdate )
        ])->setOptions([ 'isAbsolute' => true ])->toString($has_trailing_separator);
    }

    /**
     * @deprecated  ?
     *
     * + Возвращает абсолютный путь к ресурсу относительно корня FS (с учетом PATH.INSTALL и симлинка в www-folder)
     * Заканчивается на /
     *
     * Для определения каталога к типу контента используется mapping
     *
     * @param string $type
     * @param null $creation_date
     * @return string|Path
     */
    public static function getAbsResourcePath($type = 'photos', $creation_date = null, $stringify_path = true)
    {
        $creation_date = \is_null($creation_date) ? \time() : \strtotime($creation_date);

        $path = Path::create( \getenv('PATH.INSTALL'))
            ->join('www')
            ->join('i')
            ->join( self::$content_dirs[$type] )
            ->join( \date('Y', $creation_date) )
            ->join( \date('m', $creation_date) )
            ->setOptions(['isAbsolute'=>true]);

        $path->makePath();

        return $stringify_path ? $path->toString(true) : $path;
    }


```