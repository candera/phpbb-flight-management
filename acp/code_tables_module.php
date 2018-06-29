<?php

namespace VFW440\flight_management\acp;

use \VFW440\flight_management\helper\Util;

/* At some point I might want to merge the various column bits together. */

class code_tables_module
{
    // I hate that I have to put this in every file that uses it, but
    // PHP has so far thwarted my every attempt to reuse code.
    private static $ato_table_prefix = "ato2_";
    private static function fm_table_name($basename)
    {
        $prefix = self::$ato_table_prefix;
        return "{$prefix}{$basename}"; 
    }

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

    function main($id, $mode)
    {
        global $config, $db, $request, $template, $user;

        $MODE_SCHEMAS = [
            "missiontypes" => [
                "title" => "Mission Types",
                "table" => self::fm_table_name("MissionTypes"),
                "addnew-data" => ["Name" => "Change Me"],
                "columns" => ["Id", "Name", "Active"],
                "column-types" => ["Name" => "text",
                                   "Active" => "checkbox" ]
            ],
            "theaters" => [
                "title" => "Theaters",
                "table" => self::fm_table_name("theaters"),
                "addnew-data" => ["Name" => "Change Me",
                                  "Version" => "Change Me"],
                "columns" => ["Id", "Name", "Version", "Active"],
                "column-types" => ["Name" => "text",
                                   "Version" => "text",
                                   "Active" => "checkbox"],
            ],
            "roles" => [
                "title" => "Roles",
                "table" => self::fm_table_name("roles"),
                "addnew-data" => ["Name" => "Change Me"],
                "columns" => ["Id", "Name", "Active"],
                "column-types" => ["Name" => "text",
                                   "Active" => "checkbox" ]
            ],
            "flight-callsigns" => [
                "title" => "Flight Callsigns",
                "table" => self::fm_table_name("Flight_Callsigns"),
                "addnew-data" => ["Name" => "Change Me"],
                "columns" => ["Id", "Name", "Active"],
                "column-types" => ["Name" => "text",
                                   "Active" => "checkbox" ]
            ],
            "aircraft" => [
                "title" => "Aircraft",
                "table" => self::fm_table_name("aircraft"),
                "addnew-data" => ["Name" => "Change Me"],
                "columns" => ["Id", "Name", "Active"],
                "column-types" => ["Name" => "text",
                                   "Active" => "checkbox" ]
            ],

        ];

        $schema = $MODE_SCHEMAS[$mode];

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

            $db->sql_freeresult($this->execute_sql($sql));

        }
        else if ($request->is_set_post('update'))
        {
            if (!check_form_key('VFW440/flight_management'))
            {
                trigger_error('FORM_INVALID');
            }

            // error_log("variable_names:" . implode(", ", $request->variable_names()));

            $input_map = array();
            foreach ($request->variable_names() as $variable)
            {
                if (strpos($variable, "field-") === 0)
                {
                    [$nop, $id, $column] = explode("-", $variable);
                    $input_map[$id][$column] = $request->variable($variable, "");
                }
            }

            foreach ($input_map as $id => $vals)
            {
                $params = $this->update_params($schema["column-types"], $vals);
                $sql = "update "
                     . $schema["table"]
                     . " set "
                     . $db->sql_build_array("UPDATE", $params)
                     . " WHERE Id = "
                     . $db->sql_escape($id)
                     ;

                $db->sql_freeresult($this->execute_sql($sql));
            }
        }

        $user->add_lang('acp/common');
        // TODO: Eventually make the template generic
        $this->tpl_name = "code_table_body";
        $this->page_title = $user->lang('ACP_VFW440_FM_CODE_TABLES_TITLE');

        $sql = "select "
             . implode(", ", $schema["columns"])
             . " from "
             . $schema["table"];

        $result = $this->execute_sql($sql);

        $template->assign_vars(["TITLE" =>  $schema["title"]]);

        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('tablerow', $row);
        }

        $db->sql_freeresult($result);

        foreach ($schema["column-types"] as $colname => $coltype)
        {
            $template->assign_block_vars('tablecol', array("Name" => $colname,
                                                           "Type" => $coltype));
        }

        add_form_key('VFW440/flight_management');

    }

}
