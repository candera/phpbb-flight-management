<?php

namespace VFW440\flight_management\controller;

use \Symfony\Component\HttpFoundation\JsonResponse;
use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\HttpFoundation\RedirectResponse;
use \VFW440\flight_management\helper\Util;

class main
{
    /* @var \phpbb\config\config */
	protected $config;

	/* @var \phpbb\controller\helper */
	protected $helper;

	/* @var \phpbb\template\template */
	protected $template;

	/* @var \phpbb\user */
	protected $user;

    protected $db;

	/**
     * Constructor
     *
     * @param \phpbb\config\config		$config
     * @param \phpbb\controller\helper	$helper
     * @param \phpbb\template\template	$template
     * @param \phpbb\user				$user
     */
	public function __construct(\phpbb\config\config $config,
                                \phpbb\controller\helper $helper,
                                \phpbb\template\template $template,
                                \phpbb\user $user,
                                \phpbb\db\driver\driver_interface $db)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->template = $template;
		$this->user = $user;
        $this->db = $db;
	}

    private function execute_sql($sql)
    {
        global $db;
        
        error_log("sql: ". $sql);

        return $db->sql_query($sql);
    }

    private function read_code_table($table, $columns)
    {
        global $db, $template;
        
        $sql = "select "
             . implode(", ", $columns)
             . " from " . Util::fm_table_name($table) . " where active = b'1'";
        $result = $this->execute_sql($sql);

        $data = array();
        while ($row = $db->sql_fetchrow($result))
        {
            $data[] = $row;
        }

        $db->sql_freeresult($result);

        return $data;
    }
    
    private function populate_template_code_tables($table, $data)
    {
        global $template;
        
        $template->assign_block_vars($table, array("Id" => 0));
        
        foreach ($data as $row)
        {
            $template->assign_block_vars($table, $row);
        }
    }


	/**
     * Controller for route /ato
     *
     * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
     */
	public function handle_index()
	{
        global $template, $auth;
        
		// $l_message = !$this->config['acme_demo_goodbye'] ? 'DEMO_HELLO' : 'DEMO_GOODBYE';
		// $this->template->assign_var('DEMO_MESSAGE', $this->user->lang($l_message, $name));

        $template->assign_var("SHOW_SCHEDULE_MISSION", $auth->acl_get('u_schedule_mission'));
        
		return $this->helper->render('ato-index.html', '440th VFW ATO');
	}
    
    public function handle_show_mission($missionid)
    {
        return $this->helper->render('ato-show-mission.html', '440th VFW ATO');
    }

    public function read_db_missiondata($missionid)
    {
        global $db;
        
        $result = $this->execute_sql("select Published, Name, Theater, Type, Date, Description, ServerAddress, ScheduledDuration from " . Util::fm_table_name("missions") . " where Id = " . $db->sql_escape($missionid));

        $row = $db->sql_fetchrow($result);
        
        $missiondata = array(
            "PUBLISHED"   => $row["Published"],
            "MISSIONNAME" => $row["Name"],
            "THEATER"     => $row["Theater"],
            "MISSIONTYPE" => $row["Type"],
            "MISSIONTIME" => $row["Date"],
            "DESCRIPTION" => $row["Description"],
            "SERVER"      => $row["ServerAddress"],
            "DURATION"    => $row["ScheduledDuration"],
        );

        error_log($missiondata["MISSIONNAME"]);

        $db->sql_freeresult($result);

        return $missiondata;
            
    }

    private function param_to_sql_date($param_date)
    {
        $parsed = date_parse($param_date);

        return sprintf("%04d-%02d-%02d %02d:%02d",
                       $parsed["year"],
                       $parsed["month"],
                       $parsed["day"],
                       $parsed["hour"],
                       $parsed["minute"]);
    }
    
    public function handle_edit_mission($missionid)
    {
        global $auth, $config, $db, $request, $template, $user;

        if ($missionid == "new")
        {
            if (!$auth->acl_get('u_schedule_mission'))
            {
                trigger_error('NOT_AUTHORISED');
            }
        }
        else
        {
            $result = $this->execute_sql("select Creator from " . Util::fm_table_name("missions") . " where Id = {$missionid}");

            $creator = $db->sql_fetchfield("Creator");

            error_log("user_id: " . $user->data["user_id"]);
            error_log("creator: " . $creator);
            error_log("admin? " . $auth->acl_get("a_"));

            if (!($auth->acl_get("a_") || $creator == $user->data["user_id"]))
            {
                trigger_error('You must be the creator of this mission (or an administrator) to edit it.');
            }

            $db->sql_freeresult($result);
        }

        $theaters = $this->read_code_table("theaters", [ "Id", "Name", "Version" ]);
        $missiontypes = $this->read_code_table("missiontypes", [ "Id", "Name" ]);
        $roles = $this->read_code_table("roles", [ "Id", "Name" ]);

        $missiondata = null;

        $packagedata = array();
        
        if ($request->is_set_post("save") || $request->is_set_post("add-package"))
        {
            $packagedata = $request->variable("packages", array("" => array("" => "")));
            if ($request->is_set_post("add-package"))
            {
                $maxid = -1;
                foreach ($packagedata as $packageid => $data)
                {
                    $effectiveid = -1;
                    if (strpos($packageid, "new-") === 0)
                    {
                        $effectiveid = substr($packageid, 4);
                    }

                    $effectiveid = (int) $effectiveid;
                    
                    if ($effectiveid > $maxid)
                    {
                        $maxid = $effectiveid;
                    }
                }

                $nextid = $maxid + 1;
                
                $packagedata["new-{$nextid}"] = array("Name"   => "TODO: Package Name",
                                                      "Number" => "TODO: Package Number");
            }
            
            $valid = true;
            // TODO: Validate. If valid, save data. If not valid,
            // return posted data with error info.
            if (!in_array($request->variable("theater", ""), array_column($theaters, "Id")))
            {
                $valid = false;
                $template->assign_var("THEATER_ERROR", "Invalid theater");
            }

            if (!in_array($request->variable("missiontype", ""), array_column($missiontypes, "Id")))
            {
                $valid = false;
                $template->assign_var("MISSIONTYPE_ERROR", "Invalid mission type");
            }

            $timezone = null;
            try
            {
                $timezone = new \DateTimeZone($request->variable("mission-timezone", ""));
                $user->set_cookie("mission-timezone", $timezone->getName(), time()+10*365*24*60*60);
            }
            catch (\Exception $x)
            {
                $valid = false;
                $template->assign_var("MISSIONTIMEZONE_ERROR", "Invalid timezone");
            }

            $parseddate = null;
            try
            {
                $date = $request->variable("mission-date", "");
                $time = $request->variable("mission-time", "");
                
                if (empty($date) || empty($time))
                {
                    $valid = false;
                    $template->assign_var("MISSIONDATETIME_ERROR", "Invalid date/time");                        
                }
                else if ($timezone != null)
                {
                    $parseddate = new \DateTime("${date} ${time}", $timezone);
                }
            }
            catch (\Exception $x)
            {
                $valid = false;
                $template->assign_var("MISSIONDATETIME_ERROR", "Invalid date/time");
            }

            // TODO: More validation

            if ($valid)
            {
                $params = array(
                    "Published"         => $request->variable("published", "off") == "on" ? true : false,
                    "Name"              => $request->variable("missionname", ""),
                    "Theater"           => (int) $request->variable("theater", ""),
                    "Type"              => (int) $request->variable("missiontype", ""),
                    "Date"              => $this->param_to_sql_date($request->variable("mission-time", "")),
                    "Description"       => $request->variable("description", ""),
                    "ServerAddress"     => $request->variable("server", ""),
                    "ScheduledDuration" => (int) $request->variable("duration", ""),
                );
                // Insert or Update database
                if ($missionid == "new")
                {
                    $params["Creator"] = $user->data["user_id"];
                    $sql = "INSERT INTO "
                         . Util::fm_table_name("missions")
                         . " "
                         . $db->sql_build_array("INSERT", $params);
                    $result = $this->execute_sql($sql);
                    $missionid = $db->sql_nextid();
                    $db->sql_freeresult($result);

                    return new RedirectResponse($this->helper->route('ato_edit_mission_route', array('missionid' => $missionid)));
                }
                else
                {
                    $sql = "UPDATE "
                         . Util::fm_table_name("missions")
                         . " SET "
                         . $db->sql_build_array("UPDATE", $params)
                         . " WHERE Id = " . $db->sql_escape($missionid);
                    $db->sql_freeresult($this->execute_sql($sql));
                }

                $missiondata = $this->read_db_missiondata($missionid);
            }
            else
            {
                // Turn the existing data back around - it's invalid
                $missiondata = array(
                    "PUBLISHED" => $request->variable("published", "off") == "on" ? true : false,
                    "MISSIONNAME" => $request->variable("missionname", "Mission name"),
                    "THEATER" => (int) $request->variable("theater", 0),
                    "MISSIONTYPE" => (int) $request->variable("missiontype", 0),
                    "MISSIONTIME" => $request->variable("mission-time", date("Y-m-d\Th\:00:00\Z", strtotime("+1 week"))),
                    "MISSIONTIMEZONE" => $request->variable("mission-timezone", ""),
                    "DESCRIPTION" => $request->variable("description", ''),
                    "SERVER" => $request->variable("server", ''),
                    "DURATION" => (int) $request->variable("duration", 120),
                );
            }

        }
        else if ($missionid == "new")
        {
            // Data for a new mission
            $initialTimezone = request_var($config['cookie_name'] . '_mission-timezone', "", false, true);
            error_log("cookie name: " . $config['cookie_name'] . '_mission-timezone');
            error_log("initial timezone: " . $initialTimezone);
            if (empty($initialTimezone)) {
                $initialTimezone = $user->data["user_timezone"];
            }
            $missiondata = array(
                "PUBLISHED" => false,
                "MISSIONNAME" => "Mission name",
                "THEATER" => 0,
                "MISSIONTYPE" => 0,
                "MISSIONTIME" => "", // date("Y-m-d h\:00\Z", strtotime("+1 week")),
                "MISSIONTIMEZONE" => $initialTimezone,
                "DESCRIPTION" => '',
                "SERVER" => '',
                "DURATION" => 120,
            );
        }
        else
        {
            $missiondata = $this->read_db_missiondata($missionid);
        }

        foreach ($packagedata as $packageid => $packageinfo)
        {
            $packageinfo["Id"] = $packageid;
            $template->assign_block_vars("packages", $packageinfo);                
        }
        
        $template->assign_vars($missiondata);

        $template->assign_block_vars("timezones", array("" => ""));
        foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL) as $tzid => $tzname)
        {
            $tzoffset = (new \DateTimeZone($tzname))->getOffset(new \DateTime());
            $tzoffsetstr = sprintf("%s%02d%02d",
                                   $tzoffset < 0 ? "-" : "+",
                                   abs($tzoffset) / 60 / 60,
                                   (abs($tzoffset) / 60) % 60);
                                   
            $template->assign_block_vars("timezones", array("Id" => $tzname,
                                                            "Name" => "[{$tzoffsetstr}] {$tzname}"));
        }
        
        $this->populate_template_code_tables("theaters", $theaters);
        $this->populate_template_code_tables("missiontypes", $missiontypes);
        $this->populate_template_code_tables("roles", $roles);

        return $this->helper->render('ato-edit-mission.html', '440th VFW ATO');   
    }

    private function query_table_infos()
    {
        array(
            "MissionTypes" =>
            array("Table" => Util::fm_table_name("MissionTypes"),
                  "Columns" =>
                  array("Id" => "Id",
                        "Name" => "Name",
                        ),
                  "Filter" => "Active = true"),
            "Theaters" =>
            array("Table" => Util::fm_table_name("Theaters"),
                  "Columns" =>
                  array("Id" => "Id",
                        "Name" => "Name",
                        "Version" => "Version",
                        ),
                  "Filter" => "Active = true"));
    }
    
    public function handle_api_query()
    {
        try
        {
            if (!$_POST) {
                return new Response("Only POST is supported", 405);
            }
            $request = json_decode(file_get_contents('php://input'), true);

            $request_from = $request["from"];
            $request_select = $request["select"];
            // error_log("select: " . implode(', ', $request_select) . " from: {$request_from}");
        
            $table_info = $this->query_table_infos()[$request_from];
            $db_table = $table_info["Table"];

            if (!$db_table) {
                return new JsonResponse(array("Errors" => array("Unknown table <<{$request['from']}>>")), 400);
            }
        
            $data = array();

            $info_filter = $table_info["Filter"];

            $db_where = "";
            if ($info_filter) {
                $db_where = "WHERE {$info_filter}";
            }

            $info_columns = $table_info["Columns"];

            $db_columns = array();
            foreach ($request_select as $request_column) {
                $column = $info_columns[$request_column];
                if ($column) {
                    $db_columns[] = $info_columns[$request_column];
                }
                else
                {
                    return new JsonResponse(array("Errors" => array("Unknown attribute: {$request_column}")), 400);
                }
            }

            $db_select = implode(", ", $db_columns);

            $result = $this->db->sql_query("SELECT $db_select FROM {$db_table} ${db_where}");

            while ($row = $this->db->sql_fetchrow($result))
            {
                $data[] = $row;
            }

            $this->db->sql_freeresult($result);
                                   
            return new JsonResponse(array("Results" => $data));
        }
        catch (Throwable $t) {
            return new Response("Error", 500);
        }
    }

}
