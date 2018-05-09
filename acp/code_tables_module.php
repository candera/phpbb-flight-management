<?php

namespace VFW440\flight_management\acp;

const MODE_SCHEMAS = [
    "missiontypes" => [
        "table" => "vfw440_missiontypes",
        "addnew-data" => ["Name" => "Change Me"],
        "parse-update" => "parse_update_missiontypes",
        "columns" => ["Id", "Name", "Active"],
    ],
];

class code_tables_module
{
    var $u_action;

    function missiontypes_update_params()
    {
        global $request;
        
        return array(
            "Name" => $request->variable("name", ""),
            "Active" => ($request->variable("active", "off") == "on" ? true : false)
        );
    }

    function execute_sql($sql)
    {
        global $db;
        
        error_log("sql: ". $sql);

        return $db->sql_query($sql);
    }
    
	function main($id, $mode)
	{
		global $config, $db, $request, $template, $user;

        $schema = MODE_SCHEMAS[$mode];

        // error_log("mode: " . $mode);
        // error_log("schema: " . $schema);
        // error_log("MODE_SCHEMAS: " . MODE_SCHEMAS);
        // error_log("MODE_SCHEMAS['missiontypes']: " . MODE_SCHEMAS["missiontypes"]);
        // error_log("MODE_SCHEMAS['foobar']: " . MODE_SCHEMAS["foobar"]);
        // error_log("table: " . $schema["table"]);

        if ($schema == null)
        {
            trigger_error("Unknown mode " . $mode);
        }

        if ($request->is_set_post('addnew'))
        {
			if (!check_form_key('VFW440/flight_management'))
			{
				trigger_error('FORM_INVALID');
			}

            $sql = "insert into " 
                 . $schema["table"]
                 . $db->sql_build_array("INSERT", $schema["addnew-data"]);

            $this->execute_sql($sql);
        }
        else if ($request->is_set_post('update'))
        {
            if (!check_form_key('VFW440/flight_management'))
			{
				trigger_error('FORM_INVALID');
			}

            $parse_method = $mode . "_update_params";
            $params = $this->$parse_method();
            
            $sql = "update " 
                 . $schema["table"]
                 . " set "
                 . $db->sql_build_array("UPDATE", $params)
                 . " WHERE Id = "
                 . $db->sql_escape($request->variable("id", "invalid"))
                 ;

            $this->execute_sql($sql);
        }

		$user->add_lang('acp/common');
        // TODO: Eventually make the template generic
		$this->tpl_name = "code_table_{$mode}_body";
		$this->page_title = $user->lang('ACP_VFW440_FM_CODE_TABLES_TITLE');

        $sql = "select " 
             . implode(", ", $schema["columns"])                                    
             . " from "
             . $schema["table"];
        
        $result = $this->execute_sql($sql);

        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('tablerow', $row);
        }
        
        add_form_key('VFW440/flight_management');

	}

}
