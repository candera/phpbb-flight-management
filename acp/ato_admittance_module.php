<?php

namespace VFW440\flight_management\acp;

use \VFW440\flight_management\helper\Util;

/* At some point I might want to merge the various column bits together. */

class ato_admittance_module
{
    var $u_action;

    function update_params($coltypes, $vals)
    {
        $retval = array();
        foreach ($coltypes as $name => $type)
        {
            switch ($type) {
            case "text" :
                $retval[$name] = $vals[$name];
                break;
            case "checkbox":
                $retval[$name] = ($vals[$name] == "on" ? true : false);
                break;
            }
        }
        return $retval;
    }

    function execute_sql($sql)
    {
        global $db;

        error_log("sql: ". $sql);

        return $db->sql_query($sql);
    }

    function admittance_main($id, $mode)
    {
        global $config, $db, $request, $template, $user;

        $schema = $MODE_SCHEMAS[$mode];

        $ato_table_prefix = Util::$ato_table_prefix;


        if ($request->is_set_post('addnew'))
        {
            if (!check_form_key('VFW440/flight_management'))
            {
                trigger_error('FORM_INVALID');
            }

            $sql = "INSERT INTO {$ato_table_prefix}admittance (Name, Active) VALUES ('Change Me', b'1');";

            $db->sql_freeresult($this->execute_sql($sql));
        }
        else if ($request->is_set_post('update'))
        {
            if (!check_form_key('VFW440/flight_management'))
            {
                trigger_error('FORM_INVALID');
            }

            // TODO
        }

        $user->add_lang('acp/common');
        // TODO: Eventually make the template generic
        $this->tpl_name = "ato_admittance_body";
        $this->page_title = $user->lang('ACP_VFW440_ATO_ADMITTANCE_TITLE');

        $sql = "SELECT Id, Name, Active FROM {$ato_table_prefix}admittance";

        $result = $this->execute_sql($sql);

        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars("admittance", $row);
        }

        $db->sql_freeresult($result);

        $sql = "SELECT group_id as Id, group_name as Name FROM " . GROUPS_TABLE;

        $result = $this->execute_sql($sql);

        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars("groups", $row);

            $result2 = $this->execute_sql("SELECT GroupId FROM {$ato_table_prefix}admittance_groups WHERE AdmittanceId = " . $row["Id"]);

            while ($row2 = $db->sql_fetchrow($result2))
            {
                $template->assign_block_vars("admittance.groups", $row2)
            }

            $db->sql_freeresult($result2);
        }

        $db->sql_freeresult($result);

        // $sql = "select "
        //      . implode(", ", $schema["columns"])
        //      . " from "
        //      . $schema["table"];

        // $result = $this->execute_sql($sql);

        // $template->assign_vars(["TITLE" =>  $schema["title"]]);

        // while ($row = $db->sql_fetchrow($result))
        // {
        //     $template->assign_block_vars('tablerow', $row);
        // }

        // $db->sql_freeresult($result);

        // foreach ($schema["column-types"] as $colname => $coltype)
        // {
        //     $template->assign_block_vars('tablecol', array("Name" => $colname,
        //                                                    "Type" => $coltype));
        // }

        add_form_key('VFW440/flight_management');
    }
    
    function main($id, $mode)
    {
        if ($mode == "admittance")
        {
            return $this->admittance_main($id, $mode);
        }
        else
        {
            trigger_error('Unknown mode {$mode}');
            return;
        }
            

    }

}
