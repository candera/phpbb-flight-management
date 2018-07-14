<?php
/**
*
* @package phpBB Extension - 440th VFW Flight Management
* @copyright (c) 2018 Craig Andera
*
*/

namespace VFW440\flight_management\acp;

class ato_discord_info
{
	function module()
	{
		return array(
			'filename'	=> '\VFW440\flight_management\acp\ato_discord_module',
			'title'		=> 'ACP_VFW440_ATO_DISCORD_TITLE',
			'modes'		=> array(
				'discord'	=> array(
					'title'	=> 'ACP_VFW440_ATO_DISCORD_TITLE',
					'auth'	=> 'ext_VFW440/flight_management',
					'cat'	=> array('ACP_VFW440_ATO_DISCORD_TITLE')
				),
			),
		);
	}
}
