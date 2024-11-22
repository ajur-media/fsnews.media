<?php

namespace AJUR\FSNews\Media\Helpers;

class DTHelper
{
    const ruMonths = array(
        1 => 'января', 2 => 'февраля',
        3 => 'марта', 4 => 'апреля', 5 => 'мая',
        6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября',
        12 => 'декабря'
    );

    const yearSuffux = 'г.';

    /**
     * Преобразует переданную дату в русифицированную дату
     *
     * @param string $datetime - заполненную нулями возвращает как "-", "today"|"NOW()"
     * @param bool $is_show_time
     * @param null $year_suffix - можно передать пустую строчку, чтобы подавить вывод годового суффикса
     * @return string
     */
    public static function convertDate(string $datetime, bool $is_show_time = false, $year_suffix = null):string
    {
        $datetime = strtoupper($datetime);
        if ($datetime === "0000-00-00 00:00:00" || $datetime === "0000-00-00") {
            return "-";
        }

        if ($datetime === 'TODAY' || $datetime === 'NOW()') {
            $datetime = date("Y-m-d H:i:s");
        }

        if (is_null($year_suffix)) {
            $year_suffix = self::yearSuffux;
        }

        list( $y, $m, $d, $h, $i, $s ) = sscanf( $datetime, "%d-%d-%d %d:%d:%d" );

        $rusdate = sprintf("%s %s %s", $d, self::ruMonths[$m], $y ? "{$y} {$year_suffix}" : "");

        if ($is_show_time) {
            $rusdate .= sprintf(" %02d:%02d", $h, $i);
        }
        return $rusdate;
    }

}