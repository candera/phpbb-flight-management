<?php

namespace VFW440\flight_management\acp;

class ato_discord_module
{
    var $u_action;
   
    function main($id, $mode)
    {
        global $config, $request, $template, $user;

        $this->tpl_name = 'ato_discord_body';

        if ($request->is_set_post('submit'))
        {
			if (!check_form_key('VFW440/flight_management'))
			{
				trigger_error('FORM_INVALID');
			}

			$config->set('ato_discord_url', $request->variable('ato-discord-url', ""));

			trigger_error("Information Saved " . adm_back_link($this->u_action));
        }

        add_form_key('VFW440/flight_management');
        
        $template->assign_vars(array(
			'U_ACTION'				=> $this->u_action,
			'ATO_DISCORD_URL'		=> $config['ato_discord_url'],
		));
    }

}
