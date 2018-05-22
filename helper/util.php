<?php

namespace VFW440\flight_management\helper;

class Util
{
    public static $fm_table_prefix = "fm_";
    
    public static function fm_table_name($basename)
    {
        $prefix = self::$fm_table_prefix;
        return "{$prefix}{$basename}"; 
    }
}
