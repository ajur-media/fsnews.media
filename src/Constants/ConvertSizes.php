<?php

namespace AJUR\FSNews\Constants;

use Arris\Core\Dot;

trait ConvertSizes
{
    /**
     * Все возможные размеры превьюшек/картинок ко всем типам медиа - с размерами, функцией-обработчиком, файлом вотермарки, отступом вотермарки
     *
     * Внимание: префиксы уже содержат `_` и приклеиваются к корню имени без добавочных знаков. Ну, должны.
     *
     * @var array
     */
    public static array $convert_sizes = [
        /*
         * Используются размеры: 100x100, 440x300, 630x465, 1280x1024
         */
        "photos"    =>  [
            /*
             * Иконка превью в списке фотографий, входящих в фоторепортаж (десктоп и мобильный)
             */
            "100x100"   =>  [
                'maxWidth'  =>  100,
                'maxHeight' =>  100,
                'method'    =>  "getfixedpicture",
                'prefix'    =>  '100x100_',
                'quality'   =>  80,
            ],
            /*
             * sizes_full - для десктопного фоторепортажа в RSS-лентах
             * базовый размер для фото, вставляемого в МОБИЛЬНУЮ статью через [media id=] -- (считается, что 440 - это базовая ширина мобилки)
             */
            "440x300"   =>  [
                'maxWidth'  =>  440,
                'maxHeight' =>  999,
                'method'    =>  "resizepictureaspect",
                'wmFile'    =>  "l.png",
                'wmMargin'  =>  10,
                'prefix'    =>  '440x300_',
                'quality'   =>  80,
            ],
            /*
             * базовый размер для фото в фоторепортаже (десктоп и мобильный)
             * базовый размер для фото, вставляемого в десктопную статью через [media id=]
             */
            "630x465"   =>  [
                'maxWidth'  =>  630,
                'maxHeight' =>  465,
                'method'    =>  "resizepictureaspect",
                'wmFile'    =>  "l.png",
                'wmMargin'  =>  30,
                'prefix'    =>  '630x465_',
                'quality'   =>  80,
            ],
            /*
             * sizes_full - полноразмерная картинка, всплывающая при клике на вставленную в статью/страницу фото размера 'sizes' (630x465)
             * sizes_full для репортажей на мобиле
             * sizes_large для всех фото
             * + упоминается в шаблоне site/reports/reports_list.tpl
             */
            "1280x1024" =>  [
                'maxWidth'  =>  1280,
                'maxHeight' =>  1024,
                'method'    =>  "resizeimageaspect",
                'wmFile'    =>  "l.png",
                'wmMargin'  =>  30,
                'prefix'    =>  '1280x1024_',
                'quality'   =>  90,
            ],
        ],
        "videos"    =>  [
            "100x100"   =>  [
                /*
                 * Превью-иконка видео в админке
                 */
                'maxWidth'  =>  100,
                'maxHeight' =>  100,
                'method'    =>  "getfixedpicture",
                'prefix'    =>  '100x100_',
                'quality'   =>  80,
            ],
            /*
             * Превью видео, используется
             */
            "640x352"   =>  [
                'maxWidth'  =>  640,
                'maxHeight' =>  360,
                'method'    =>  "getfixedpicture",
                'prefix'    =>  '640x352_',
                'quality'   =>  80,
            ],
        ],

        "audios"    =>  [
            "_"   =>  [
                'prefix'    =>  '',
            ],
        ],

        "files"    =>  [
            "_"   =>  [
                'prefix'    =>  '',
            ],
        ],

        "youtube"   =>  [
            /*
             * Превью в админке
             */
            "100x100"   =>  [
                'maxWidth'  =>  100,
                'maxHeight' =>  100,
                'method'    =>  "getfixedpicture",
                'prefix'    =>  '100x100_',
                'quality'   =>  80
            ],
            /*
             * Превью видео, используется
             */
            "640x352"   =>  [
                'maxWidth'  =>  640,
                'maxHeight' =>  360,
                'method'    =>  "getfixedpicture",
                'prefix'    =>  '640x325_',
                'quality'   =>  80
            ],
        ],

        "titles"     =>  [
            /*
             * основное title изображение, (article.tpl)
             * Еще оно используется в админке, в редакторе статей
             * Нехай качество будет 92, разница между 90 и 92 по размеру около 2%, а качество должно различаться заметно
             */
            '608x406'   =>  [
                'maxWidth'      =>  608,
                'maxHeight'     =>  406,
                'method'        =>  '',
                'prefix'        =>  '',
                'quality'       =>  92
            ],
            /*
             * "квадратные" превью тайтлов статей на главной (widget.tpl, index_tres.tpl)
             */
            '300x266'   =>  [
                'maxWidth'      =>  300,
                'maxHeight'     =>  266,
                'method'        =>  'getFixedPicture',
                'prefix'        =>  'resize_',
                'quality'       =>  80
            ],
            /*
             * маленькие превью тайтлов статей на главной (3 в топе)
             * картинки в фиде "авторский материал" на главной
             */
            '205x150'   =>  [
                'maxWidth'      =>  205,
                'maxHeight'     =>  150,
                'method'        =>  'getFixedPicture',
                'prefix'        =>  'small_',
                'quality'       =>  70
            ],
        ]
    ];

    public static function getConvertSizes($path = null)
    {
        $repository = new Dot(self::$convert_sizes);

        return $repository->get($path);
    }



}