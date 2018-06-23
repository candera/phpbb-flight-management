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
     * @param \phpbb\config\config      $config
     * @param \phpbb\controller\helper  $helper
     * @param \phpbb\template\template  $template
     * @param \phpbb\user               $user
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

        error_log("populate_template_code_tables {$table} : " . count($data));

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
        global $template, $auth, $db;

        // $l_message = !$this->config['acme_demo_goodbye'] ? 'DEMO_HELLO' : 'DEMO_GOODBYE';
        // $this->template->assign_var('DEMO_MESSAGE', $this->user->lang($l_message, $name));

        $template->assign_var("SHOW_SCHEDULE_MISSION", $auth->acl_get('u_schedule_mission'));

        $missions_table = Util::fm_table_name("missions");

        $results = $this->execute_sql("SELECT
  m.Id,
  m.Name,
  m.Date as Start,
  DATE_ADD(m.Date, INTERVAL m.ScheduledDuration MINUTE) AS End
FROM {$missions_table} AS m
WHERE
m.Date > DATE_SUB(NOW(), INTERVAL 1 WEEK)
AND m.Published = b'1'");


        while ($row = $db->sql_fetchrow($results))
        {
            error_log("mission row : " . json_encode($row));
            $db_start = new \DateTime($row["Start"], new \DateTimeZone("UTC"));
            $db_end = new \DateTime($row["End"], new \DateTimeZone("UTC"));
            $missionid = $row["Id"];
            $view_link = $this->helper->route('ato_display_mission_route',
                                              array('missionid' => $missionid));
            $template->assign_block_vars("missions", array("Title" => $row["Name"],
                                                           "Start" => $db_start->format(DATE_ATOM),
                                                           "End" => $db_end->format(DATE_ATOM),
                                                           "Url" => $view_link));
        }

        $db->sql_freeresult($result);

        return $this->helper->render('ato-index.html', '440th VFW ATO');
    }

    public function handle_display_mission($missionid)
    {
        global $db, $request, $template, $user;

        $missions_table = Util::fm_table_name("missions");
        $admittance_table = Util::fm_table_name("admittance");
        $missiontypes_table = Util::fm_table_name("missiontypes");
        $theaters_table = Util::fm_table_name("theaters");
        $missionid = $db->sql_escape($missionid);
        $result = $this->execute_sql("SELECT
  m.Published         AS Published,
  m.Name              AS Name,
  adm.Name            AS OpenTo,
  theater.Name        AS Theater,
  types.Name          AS Type,
  m.Date              AS Date,
  m.ScheduledDuration AS ScheduledDuration,
  m.Description       AS Description,
  m.ServerAddress     AS ServerAddress
FROM {$missions_table} as m
INNER JOIN {$admittance_table} as adm
ON adm.Id = m.OpenTo
INNER JOIN {$theaters_table} as theater
ON theater.Id = m.Theater
INNER JOIN {$missiontypes_table} as types
ON types.Id = m.Type
WHERE m.Id = {$missionid}");

        $row = $db->sql_fetchrow($result);

        if ( ! $row )
        {
            return $this->helper->render('ato-mission-not-found.html', '440th VFW ATO');
        }

        $scheduled_participants_table = Util::fm_table_name("scheduled_participants");

        if ($request->is_set_post("sign-in"))
        {
            // TODO: Validate permission to sign in
            $signin_data = $request->variable("sign-in", array(0 => array(0 => "")));
            $signin_flight = array_keys($signin_data)[0];
            $signin_seat = array_keys($signin_data[$signin_flight])[0];
            $userid = $user->data["user_id"];

            $signin_flight = $db->sql_escape($signin_flight);
            $signin_seat = $db->sql_escape($signin_seat);

            $sql = "INSERT INTO {$scheduled_participants_table}
(FlightId, SeatNum, MemberPilot)
VALUES ({$signin_flight}, {$signin_seat}, {$userid})";
            $db->sql_freeresult($this->execute_sql($sql));
        }

        if ($request->is_set_post("sign-out"))
        {
            // TODO: Validate permission to sign out
            $signout_data = $request->variable("sign-out", array(0 => array(0 => "")));
            $signout_flight = array_keys($signout_data)[0];
            $signout_seat = array_keys($signout_data[$signout_flight])[0];
            $userid = $user->data["user_id"];

            $signout_flight = $db->sql_escape($signout_flight);
            $signout_seat = $db->sql_escape($signout_seat);

            $sql = "DELETE FROM {$scheduled_participants_table}
WHERE FlightId = {$signout_flight}
AND SeatNum = {$signout_seat}";
            $db->sql_freeresult($this->execute_sql($sql));
        }


        // TODO: If mission is not published, display or don't display as approproate
        // TODO: What is "appropriate"?

        $missiondata = $row;

        $duration_mins = (int) $row["ScheduledDuration"];
        $missiondata["DurationHours"] = floor($duration_mins / 60);
        $missiondata["DurationMins"] = sprintf("%02d", $duration_mis % 60);

        $db->sql_freeresult($result);

        $packagedata = $this->read_db_packagedata($missionid);
        $flightdata = $this->read_db_flightdata($missionid);

        foreach ($packagedata as $packageid => $packageinfo)
        {
            $packageinfo["Id"] = $packageid;
            $template->assign_block_vars("packages", $packageinfo);
        }

        foreach ($flightdata as $flightid => $flightinfo)
        {
            $flightinfo["Id"] = $flightid;
            for ($i = 1; $i <= (int) $flightinfo["Seats"]; $i++)
            {
                // TODO: Also check if participant is self and for
                // permission (via admittance) to join and to sign out
                // other people.

                // TODO: Also do not display sign-in if user is
                // already signed in to some seat.
                if ($flightinfo["Participants"][$i] != null)
                {
                    $flightinfo["Participants"][$i]["Action"] = "sign-out";
                }
                else
                {
                    $flightinfo["Participants"][$i] = array("Action" => "sign-in");
                }
            }
            $template->assign_block_vars("flights", $flightinfo);

            error_log("flightinfo (flightdata loop) : " . json_encode($flightinfo));
        }

        $template->assign_vars($missiondata);
        $this->assign_timezones_var("timezones");

        return $this->helper->render('ato-display-mission.html', '440th VFW ATO');
    }

    public function read_db_missiondata($missionid, $tzName)
    {
        global $db;

        $result = $this->execute_sql("SELECT
  Published,
  Name,
  Theater,
  Type,
  Date,
  Description,
  ServerAddress,
  ScheduledDuration,
  OpenTo
FROM "
                                     . Util::fm_table_name("missions")
                                     . " WHERE Id = "
                                     . $db->sql_escape($missionid));

        $row = $db->sql_fetchrow($result);

        if ( ! $row )
        {
            return null;
        }

        $missiondata = array(
            "PUBLISHED"   => $row["Published"],
            "MISSIONNAME" => $row["Name"],
            "THEATER"     => $row["Theater"],
            "MISSIONTYPE" => $row["Type"],
            "MISSIONDATE" => $row["Date"],
            "DESCRIPTION" => $row["Description"],
            "SERVER"      => $row["ServerAddress"],
            "DURATION"    => $row["ScheduledDuration"],
            "OPENTO"      => $row["OpenTo"],
            "MISSIONLINK" => $this->helper->route('ato_display_mission_route',
                                                  array('missionid' => $missionid))
        );

        $db->sql_freeresult($result);

        $db_date = new \DateTime($missiondata["MISSIONDATE"], new \DateTimeZone("UTC"));

        $db_date->setTimezone(new \DateTimeZone($tzName));


        $missiondata["MISSIONDATE"] = $db_date->format("Y-m-d H:i");
        $missiondata["MISSIONTIMEZONE"] = $tzName;

        return $missiondata;

    }

    public function read_db_packagedata($missionid)
    {
        global $db;

        $result = $this->execute_sql("SELECT
  Id,
  Name,
  Number
FROM "
                                     . Util::fm_table_name("packages")
                                     . " WHERE MissionId = "
                                     . $db->sql_escape($missionid));

        $packagedata = [];
        while ($row = $db->sql_fetchrow($result))
        {
            $packagedata[$row["Id"]] = $row;
        }

        $db->sql_freeresult($result);

        return $packagedata;
    }

    public function read_db_flightdata($missionid)
    {
        global $db;

        $packages_table = Util::fm_table_name("packages");
        $flights_table = Util::fm_table_name("flights");
        $flight_callsigns_table = Util::fm_table_name("flight_callsigns");
        $roles_table = Util::fm_table_name("roles");
        $aircraft_table = Util::fm_table_name("aircraft");
        $users_table = USERS_TABLE;
        $scheduled_participants_table = Util::fm_table_name("scheduled_participants");
        $safe_mission_id = $db->sql_escape($missionid);

        $result = $this->execute_sql("SELECT
  f.Id,
  f.PackageId,
  f.CallsignId,
  c.Name as CallsignName,
  f.CallsignNum,
  f.RoleId,
  r.Name as RoleName,
  f.AircraftId,
  a.Name as AircraftName,
  f.TakeoffTime,
  f.Seats
FROM {$flights_table} as f
INNER JOIN {$packages_table} as p
ON f.PackageId = p.Id
LEFT JOIN {$flight_callsigns_table} as c
ON f.CallsignId = c.Id
LEFT JOIN {$roles_table} as r
ON f.RoleId = r.Id
LEFT JOIN {$aircraft_table} as a
ON f.AircraftId = a.Id
WHERE p.MissionId = {$safe_mission_id}");

        $flightdata = [];
        $flight_ids = [];
        while ($row = $db->sql_fetchrow($result))
        {
            $flightid = $row["Id"];
            $flightdata[$flightid] = $row;
            $flight_ids[] = $flightid;
        }

        $db->sql_freeresult($result);

        $result = $this->execute_sql("SELECT
  sp.SeatNum,
  sp.FlightId,
  sp.MemberPilot as MemberPilotId,
  u.username as MemberPilot,
  sp.NonmemberPilot,
  sp.ConfirmedFlown
FROM {$scheduled_participants_table} as sp
LEFT JOIN {$users_table} as u
ON sp.MemberPilot = u.user_id
WHERE FlightId IN (" . implode($flight_ids, ", ") . ")");

        while ($row = $db->sql_fetchrow($result))
        {
            $flightdata[$row["FlightId"]]["Participants"][$row["SeatNum"]] = $row;
        }

        $db->sql_freeresult($result);

        return $flightdata;
    }

    private function format_sql_date($date)
    {
        $utc = $date->setTimezone(new \DateTimeZone("UTC"));

        return $utc->format("Y-m-d H:i");
    }

    private function get_new_flightid($flightdata)
    {
        foreach ($flightdata as $flightid => $data)
        {
            error_log("Examining existing flight {$flightid}");
            $effectiveid = -1;
            if (strpos($flightid, "new-") === 0)
            {
                $effectiveid = substr($flightid, 4);
            }

            $effectiveid = (int) $effectiveid;

            if ($effectiveid > $maxid)
            {
                $maxid = $effectiveid;
            }
        }

        $nextid = $maxid + 1;

        $flightid = "new-{$nextid}";

        return $flightid;
    }

    private function new_flight_data($packageid)
    {
        return array("PackageId" => $packageid,
                     "CallsignNum" => 1,
                     "Seats" => 4,
                     "TakeoffTime" => "");
    }

    private function assign_timezones_var($varname)
    {
        global $template;

        $template->assign_block_vars($varname, array("" => ""));
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

            if (!($auth->acl_get("a_") || $creator == $user->data["user_id"]))
            {
                trigger_error('You must be the creator of this mission (or an administrator) to edit it.');
            }

            $db->sql_freeresult($result);
        }

        $defaultTimezone = request_var($config['cookie_name'] . '_mission-timezone', "", false, true);
        if (empty($defaultTimezone)) {
            $defaultTimezone = $user->data["user_timezone"];
        }

        $theaters = $this->read_code_table("theaters", [ "Id", "Name", "Version" ]);
        $missiontypes = $this->read_code_table("missiontypes", [ "Id", "Name" ]);
        $roles = $this->read_code_table("roles", [ "Id", "Name" ]);
        $opento = $this->read_code_table("admittance", [ "Id", "Name" ]);
        $flight_callsigns = $this->read_code_table("flight_callsigns", [ "Id", "Name" ]);
        $aircraft = $this->read_code_table("aircraft", [ "Id", "Name" ]);

        $missiondata = null;

        $packagedata = $request->variable("packages", array("" => array("" => "")));

        // It sucks not to have this be nested inside the package, but
        // I couldn't figure out how to make it work.
        $flightdata = $request->variable("flights", array("" => array("" => "")));

        error_log("submitted packagedata " . json_encode($packagedata));
        error_log("submitted flightdata " . json_encode($flightdata));

        $should_attempt_save = false;
        if ($request->is_set_post("delete-package"))
        {
            $newpackagedata = array();
            $should_attempt_save = true;
            $deleting_packages = $request->variable("delete-package", array("" => ""));

            $deleted_package_id = array_keys($deleting_packages)[0];

            foreach ($packagedata as $packageid => $packageinfo)
            {
                if ($packageid == $deleted_package_id)
                {
                    if (! (strpos($packageid, "new-") === 0))
                    {
                        $sql = "DELETE FROM "
                             . Util::fm_table_name("packages")
                             . " "
                             . " WHERE MissionId = {$missionid} AND Id = "
                             . $db->sql_escape($packageid);
                        $db->sql_freeresult($this->execute_sql($sql));
                    }
                }
                else
                {
                    $newpackagedata[$packageid] = $packageinfo;
                }
            }
            $packagedata = $newpackagedata;
        }

        if ($request->is_set_post("add-package"))
        {
            $should_attempt_save = true;
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

            $packageid = "new-{$nextid}";
            $packagedata[$packageid] = array("Name"   => "New Package",
                                             "Number" => "");
            $flightdata[$this->get_new_flightid($flightdata)]
                = $this->new_flight_data($packageid);
        }


        if ($request->is_set_post("add-flight"))
        {
            $should_attempt_save = true;
            $packageid_for_flight = array_keys($request->variable("add-flight", array("" => "")))[0];
            $maxid = -1;

            error_log("Adding a flight to package {$packageid_for_flight}");

            $flightid = $this->get_new_flightid($flightdata);

            error_log("New flight ID is {$flightid}");
            $flightdata[$flightid] = $this->new_flight_data($packageid_for_flight);
        }

        if ($request->is_set_post("save"))
        {
            $should_attempt_save = true;
        }

        if ($should_attempt_save)
        {
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

                if (empty($date))
                {
                    $valid = false;
                    $template->assign_var("MISSIONDATETIME_ERROR", "Invalid date/time");
                }
                else if ($timezone != null)
                {
                    $parseddate = new \DateTime($date, $timezone);
                }
            }
            catch (\Exception $x)
            {
                $valid = false;
                $template->assign_var("MISSIONDATETIME_ERROR", "Invalid date/time");
            }

            if (!in_array($request->variable("opento", 0), array_column($opento, "Id")))
            {
                $valid = false;
                $template->assign_var("OPENTO_ERROR", "A valid selection is required.");
            }

            foreach ($flightdata as $flightid => $flightinfo)
            {
                if (!in_array($flightinfo["CallsignId"],
                              array_column($flight_callsigns, "Id")))
                {
                    $valid = false;
                    $flightdata[$flightid]["CallsignError"] = "Valid callsign required";
                }

                if ($flightinfo["CallsignNum"] <= 0)
                {
                    $valid = false;
                    $flightdata[$flightid]["CallsignNumError"] = "Valid callsign number required";
                }

                if (!in_array($flightinfo["AircraftId"],
                              array_column($aircraft, "Id")))
                {
                    $valid = false;
                    $flightdata[$flightid]["AircraftError"] = "Valid aircraft required";
                }

                if ($flightinfo["Seats"] <= 0)
                {
                    $valid = false;
                    $flightdata[$flightid]["SeatsError"] = "Valid number of seats required";
                }

                if (!in_array($flightinfo["RoleId"],
                              array_column($roles, "Id")))
                {
                    $valid = false;
                    $flightdata[$flightid]["RoleError"] = "Valid role required";
                }

                // I decided not to validate takeoff time. I don't see
                // a ton of value in it, so I left it as freeform
                // text.

            }

            // TODO: More validation

            if ($valid)
            {
                $redirect = false;
                $params = array(
                    "Published"         => $request->variable("published", "off") == "on" ? true : false,
                    "Name"              => $request->variable("missionname", ""),
                    "Theater"           => (int) $request->variable("theater", ""),
                    "Type"              => (int) $request->variable("missiontype", ""),
                    "Date"              => $this->format_sql_date($parseddate),
                    "Description"       => $request->variable("description", ""),
                    "ServerAddress"     => $request->variable("server", ""),
                    "ScheduledDuration" => (int) $request->variable("duration", ""),
                    "OpenTo"            => (int) $request->variable("opento", 0),
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

                    $redirect = true;
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

                $newpackagedata = [];
                foreach ($packagedata as $packageid => $packageinfo)
                {
                    $packagenumber = $packageinfo["Number"] == "" ? null : (int) $packageinfo["Number"];
                    $packagename = $db->sql_escape($packageinfo["Name"]);
                    $params = array("MissionId" => $missionid,
                                    "Name" => $packagename,
                                    "Number" => $packagenumber);
                    if (strpos($packageid, "new-") === 0)
                    {
                        $sql = "INSERT INTO "
                             . Util::fm_table_name("packages")
                             . " "
                             . $db->sql_build_array("INSERT", $params);
                        $db->sql_freeresult($this->execute_sql($sql));
                        $newpackageid = $db->sql_nextid();

                        foreach ($flightdata as $flightid => $flightinfo)
                        {
                            if ($flightinfo["PackageId"] == $packageid)
                            {
                                $flightdata[$flightid]["PackageId"] = $newpackageid;
                            }
                        }
                        $packageid = $newpackageid;
                    }
                    else
                    {
                        $packageid = (int) $packageid;
                        $sql = "UPDATE "
                             . Util::fm_table_name("packages")
                             . " SET "
                             . $db->sql_build_array("UPDATE", $params)
                             . " WHERE Id = "
                             . $db->sql_escape($packageid);
                        $db->sql_freeresult($this->execute_sql($sql));
                    }
                    $newpackagedata[$packageid] = $params;

                }

                $packagedata = $newpackagedata;

                $newflightdata = [];
                foreach ($flightdata as $flightid => $flightinfo)
                {
                    $callsign = (int) $flightinfo["CallsignId"];
                    $callsign_num = (int) $flightinfo["CallsignNum"];
                    $aircraftid = (int) $flightinfo["AircraftId"];
                    $seats = (int) $flightinfo["Seats"];
                    $role = (int) $flightinfo["RoleId"];
                    $takeoff = $db->sql_escape($flightinfo["TakeoffTime"]);
                    $flight_package = (int) $flightinfo["PackageId"];

                    $params = array("CallsignId" => $callsign,
                                    "CallsignNum" => $callsign_num,
                                    "AircraftId" => $aircraftid,
                                    "Seats" => $seats,
                                    "RoleId" => $role,
                                    "TakeoffTime" => $takeoff,
                                    "PackageId" => $flight_package);

                    if (strpos($flightid, "new-") === 0)
                    {
                        $sql = "INSERT INTO "
                             . Util::fm_table_name("flights")
                             . " "
                             . $db->sql_build_array("INSERT", $params);
                        $db->sql_freeresult($this->execute_sql($sql));

                        $newflightid = $db->sql_nextid();
                    }
                    else
                    {
                        $flightid = (int) $flightid;
                        $sql = "UPDATE "
                             . Util::fm_table_name("flights")
                             . " SET "
                             . $db->sql_build_array("UPDATE", $params)
                             . " WHERE Id = "
                             . $db->sql_escape($flightid);
                        $db->sql_freeresult($this->execute_sql($sql));
                    }
                    $newflightdata[$flightid] = $params;

                }

                $flightdata = $newflightdata;

                $tzName = $request->variable("mission-timezone", $defaultTimezone);
                $missiondata = $this->read_db_missiondata($missionid, $tzName);

                if ($redirect)
                {
                    return new RedirectResponse($this->helper->route('ato_edit_mission_route',
                                                                     array('missionid' => $missionid)));
                }
            }
            else
            {
                // Turn the existing data back around - it's invalid
                $missiondata = array(
                    "PUBLISHED" => $request->variable("published", "off") == "on" ? true : false,
                    "MISSIONNAME" => $request->variable("missionname", "Mission name"),
                    "THEATER" => (int) $request->variable("theater", 0),
                    "MISSIONTYPE" => (int) $request->variable("missiontype", 0),
                    "MISSIONDATE" => $request->variable("mission-date", date("Y-m-d 12:00", strtotime("+1 week"))),
                    "MISSIONTIMEZONE" => $request->variable("mission-timezone", ""),
                    "DESCRIPTION" => $request->variable("description", ''),
                    "SERVER" => $request->variable("server", ''),
                    "DURATION" => (int) $request->variable("duration", 120),
                    "OPENTO" => (int) $request->variable("opento", 0),
                );
            }

        }
        // It's a GET for a new mission
        else if ($missionid == "new")
        {
            $missiondata = array(
                "PUBLISHED" => false,
                "MISSIONNAME" => "Mission name",
                "THEATER" => 0,
                "MISSIONTYPE" => 0,
                "MISSIONDATE" => "",
                "MISSIONTIMEZONE" => $defaultTimezone,
                "DESCRIPTION" => '',
                "SERVER" => '',
                "DURATION" => 120,
                "OPENTO" => 0
            );

            $packagedata["new-0"] = array("Name"   => "New Package",
                                          "Number" => "");
            $flightdata["new-0"] = $this->new_flight_data("new-0");
        }
        // It's a GET for an existing mission
        else
        {
            $tzName = $request->variable("mission-timezone", $defaultTimezone);
            $missiondata = $this->read_db_missiondata($missionid, $tzName);
            // TODO: Return 404 for nonexistant mission
            $packagedata = $this->read_db_packagedata($missionid);
            $flightdata = $this->read_db_flightdata($missionid);
        }

        if ( ! $missiondata )
        {
            return $this->helper->render('ato-mission-not-found.html', '440th VFW ATO');
        }

        foreach ($packagedata as $packageid => $packageinfo)
        {
            $packageinfo["Id"] = $packageid;
            $template->assign_block_vars("packages", $packageinfo);
        }

        foreach ($flightdata as $flightid => $flightinfo)
        {
            $flightinfo["Id"] = $flightid;
            $template->assign_block_vars("flights", $flightinfo);
        }

        $template->assign_vars($missiondata);

        $this->assign_timezones_var("timezones");
        $this->populate_template_code_tables("aircraft", $aircraft);
        $this->populate_template_code_tables("theaters", $theaters);
        $this->populate_template_code_tables("missiontypes", $missiontypes);
        $this->populate_template_code_tables("roles", $roles);
        $this->populate_template_code_tables("opento", $opento);
        $this->populate_template_code_tables("callsigns", $flight_callsigns);

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
