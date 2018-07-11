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
    'ACL_U_SCHEDULE_MISSION' => 'Can schedule missions in the ATO',
    'ACL_U_ATO_ASSIGN_SEATS' => 'Can sign others in and out on the ATO',
));
