<?php
/**
*
* @package phpBB Extension - 440th VFW Flight Management
* @copyright (c) 2018 Craig Andera
*
*/

namespace VFW440\flight_management\acp;

class code_tables_info
{
	function module()
	{
		return array(
			'filename'	=> '\VFW440\flight_management\acp\code_tables_module',
			'title'		=> 'ACP_VFW440_FM_CODE_TABLES_TITLE',
			'modes'		=> array(
				'theaters'	=> array(
					'title'	=> 'ACP_VFW440_FM_THEATERS_TITLE',
					'auth'	=> 'ext_VFW440/flight_management',
					'cat'	=> array('ACP_VFW440_FM_CODE_TABLES_TITLE')
				),
                'missiontypes'	=> array(
					'title'	=> 'ACP_VFW440_FM_MISSIONTYPES_TITLE',
					'auth'	=> 'ext_VFW440/flight_management',
					'cat'	=> array('ACP_VFW440_FM_CODE_TABLES_TITLE')
				),
                'roles'	=> array(
					'title'	=> 'ACP_VFW440_FM_ROLES_TITLE',
					'auth'	=> 'ext_VFW440/flight_management',
					'cat'	=> array('ACP_VFW440_FM_CODE_TABLES_TITLE')
				),

			),
		);
	}
}
