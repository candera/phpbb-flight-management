<?php

namespace VFW440\flight_management\helper;

class Util
{
    public static $ato_table_prefix = "ato2_";
    
    public static function fm_table_name($basename)
    {
        $prefix = self::$ato_table_prefix;
        return "{$prefix}{$basename}"; 
    }
}
