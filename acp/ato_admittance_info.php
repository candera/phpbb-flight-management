<?php
/**
*
* @package phpBB Extension - 440th VFW Flight Management
* @copyright (c) 2018 Craig Andera
*
*/

namespace VFW440\flight_management\acp;

class ato_admittance_info
{
	function module()
	{
		return array(
			'filename'	=> '\VFW440\flight_management\acp\ato_admittance_module',
			'title'		=> 'ACP_VFW440_ATO_ADMITTANCE_TITLE',
			'modes'		=> array(
				'admittance'	=> array(
					'title'	=> 'ACP_VFW440_ATO_ADMITTANCE_TITLE',
					'auth'	=> 'ext_VFW440/flight_management',
					'cat'	=> array('ACP_VFW440_ATO_ADMITTANCE_TITLE')
				),
			),
		);
	}
}
