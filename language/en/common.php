<?php

if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}

$lang = array_merge($lang, array(
    'ACP_VFW440_FM_CODE_TABLES_TITLE' => 'Flight Management Data',
    'ACP_VFW440_FM_THEATERS_TITLE' => 'Theaters',
    'ACP_VFW440_FM_MISSIONTYPES_TITLE' => 'Mission Types',
    'ACP_VFW440_FM_ROLES_TITLE' => 'Roles',
    'ACP_VFW440_FM_FLIGHT_CALLSIGNS_TITLE' => 'Flight Callsigns',
    'ACP_VFW440_FM_AIRCRAFT_TITLE' => 'Aircraft Types',
    'ACP_VFW440_ATO_ADMITTANCE_TITLE' => 'ATO Admittance',
    'ACP_VFW440_ATO_DISCORD_TITLE' => 'ATO Discord Integration'
));
