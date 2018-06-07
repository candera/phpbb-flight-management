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

            $sql = "INSERT INTO {$ato_table_prefix}admittance (Name, Active) VALUES ('Change Me', b'0');";

            $db->sql_freeresult($this->execute_sql($sql));
        }
        else if ($request->is_set_post('update'))
        {
            if (!check_form_key('VFW440/flight_management'))
            {
                trigger_error('FORM_INVALID');
            }

            error_log("updating admittance");

            $categories_submitted = $request->variable("category", array(0 => array("" => "")));
            $categories_initial = $request->variable("category-initial", array(0 => array("" => "")));

            // This is a little gross - so many separate updates - but
            // the alternative is a giant SQL statement with a lot of
            // CASE clauses.
            foreach ($categories_initial as $adm_id => $init_info)
            {
                $initial_name = $init_info["Name"];
                $submitted_name = $categories_submitted[$adm_id]["Name"];

                error_log("Comparing name '{$initial_name}' with '{$submitted_name}' for {$adm_id}");

                if ($initial_name != $submitted_name)
                {
                    $db->sql_freeresult(
                        $this->execute_sql(
                            "UPDATE {$ato_table_prefix}admittance SET Name = '" .
                            $db->sql_escape($submitted_name) .
                            "' WHERE Id = {$adm_id}"));
                }

                $initial_active = $init_info["Active"] == "true" ? true : false;
                $submitted_active = $categories_submitted[$adm_id]["Active"] == "on" ? true : false;

                error_log("Comparing active '{$initial_active}' with '{$submitted_active}' for {$adm_id}");

                if ($initial_active != $submitted_active)
                {
                    $newval = $submitted_active ? "1" : "0";
                    $db->sql_freeresult(
                        $this->execute_sql(
                            "UPDATE {$ato_table_prefix}admittance SET Active = b'{$newval}' WHERE Id = {$adm_id}"));
                }

            }

            $admittance_submitted = $request->variable("admittances", array(0 => array(0 => "")));
            $admittance_initial = $request->variable("admittance-initial", array(0 => array(0 => "")));

            foreach ($admittance_initial as $adm_id => $init_info)
            {
                foreach ($init_info as $group_id => $group_initial)
                {
                    $initial_val = $group_initial == "true" ? true : false;
                    $submitted_val = $admittance_submitted[$adm_id][$group_id] == "on" ? true : false;

                    if ($initial_val != $submitted_val)
                    {
                        if ($submitted_val)
                        {
                            $db->sql_freeresult(
                                $this->execute_sql(
                                    "INSERT INTO {$ato_table_prefix}admittance_groups (AdmittanceId, GroupId) VALUES ({$adm_id}, {$group_id})"));
                        }
                        else
                        {
                            $db->sql_freeresult(
                                $this->execute_sql(
                                    "DELETE FROM {$ato_table_prefix}admittance_groups WHERE AdmittanceId = {$adm_id} AND GroupId = {$group_id}"));
                        }
                    }
                }
            }
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

        $sql = "SELECT group_id as Id, group_name as Name FROM " . GROUPS_TABLE . " ORDER BY Name";
        $result = $this->execute_sql($sql);
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars("groups", $row);
        }
        $db->sql_freeresult($result);

        $sql = "SELECT AdmittanceId, GroupId FROM {$ato_table_prefix}admittance_groups";
        $result = $this->execute_sql($sql);
        $admittance_groups = [];
        while ($row = $db->sql_fetchrow($result))
        {
            $admittance_groups[$row["AdmittanceId"]][$row["GroupId"]] = true;
        }
        $db->sql_freeresult($result);

        $template->assign_var("admittance_groups", $admittance_groups);

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
