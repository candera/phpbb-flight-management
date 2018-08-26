<?php

namespace VFW440\flight_management\controller;

use \Symfony\Component\HttpFoundation\JsonResponse;
use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\HttpFoundation\RedirectResponse;

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

    /** @var string phpBB root path */
    protected $root_path;

    /** @var string phpEx */
    protected $php_ext;

    protected $db;

    // I hate that I have to put this in every file that uses it, but
    // PHP has so far thwarted my every attempt to reuse code.
    private static $ato_table_prefix = "ato2_";
    private static function fm_table_name($basename)
    {
        $prefix = self::$ato_table_prefix;
        return "{$prefix}{$basename}";
    }

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
                                \phpbb\db\driver\driver_interface $db,
                                $root_path,
                                $php_ext)
    {
        $this->config = $config;
        $this->helper = $helper;
        $this->template = $template;
        $this->user = $user;
        $this->db = $db;
        $this->root_path = $root_path;
        $this->php_ext = $php_ext;

        /* It's pretty gross that I have to hard-code the name of the
         * extension here, but I couldn't figure out how to get stupid
         * PHP to load this library otherwise. */
        include('./ext/VFW440/flight_management/vendor/rmccue/requests/library/Requests.php');
    }

    private function post_discord_message($content)
    {
        global $config;

        if ($config['ato_discord_url'])
        {
            try
            {
                error_log('Attempting to post to discord via webhook');
                \Requests::post($config['ato_discord_url'],
                                array(),
                                json_encode(array("content" => $content)));
            }
            catch (\Exception $x)
            {
                error_log("Exception thrown while posting to discord webhook (sign-in)");
            }
        }
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
             . " from " . self::fm_table_name($table) . " where active = b'1'";
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

    /* Returns an array of the group IDs to which $userid belongs */
    private function group_memberships($userid)
    {
        global $db;

        $user_group_table = USER_GROUP_TABLE;
        $safe_userid = $db->sql_escape($userid);

        $sql = "SELECT group_id
FROM {$user_group_table}
WHERE user_id = {$safe_userid}";

        $result = $this->execute_sql($sql);

        $groups = array();

        while ($row = $db->sql_fetchrow($result))
        {
            $groups[] = $row["group_id"];
        }

        $db->sql_freeresult($result);

        return $groups;
    }

    public function iCalResponse($missiondata)
    {
        /* I wound up having to do it this way because I couldn't get
         * the templating system to do the CRLF line endings that are
         * required by the iCal spec. */
        $body = "BEGIN:VCALENDAR\r
PRODID:-//VFW440//ATO//EN\r
VERSION:2.0\r
CALSCALE:GREGORIAN\r
NAME:440th VFW ATO\r
X-WR-CALNAME:440th VFW ATO\r
X-WR-CALDESC:440th VFW ATO\r
REFRESH-INTERVAL;VALUE=DURATION:PT15M\r
METHOD:PUBLISH\r
";
        $board_url = generate_board_url(true);
        foreach ($missiondata as $mission)
        {
            $title = $mission["Title"];
            $url = $mission["Url"];
            $clean_url = $this->strip_query_string($url);
            $start = $mission["StartICS"];
            $end = $mission["EndICS"];
            $missionid = $mission["Id"];

            $body .= "BEGIN:VEVENT\r
UID:{$board_url}/missions/{$missionid}\r
DTSTAMP:{$start}\r
DTSTART:{$start}\r
DTEND:{$end}\r
TRANSP:TRANSPARENT\r
DESCRIPTION:{$board_url}{$clean_url}\r
URL:{$board_url}{$clean_url}\r
SUMMARY:{$title}\r
SEQUENCE:1\r
END:VEVENT\r
";
        }

        $body .= "END:VCALENDAR\r\n";

        $response = new Response($body, 200, array("Content-Type" => "text/calendar"));
        return $response;
    }

    /**
     * Controller for route /ato
     *
     * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
     */
    public function handle_index()
    {
        global $auth, $db, $request, $template, $user;

        $template->assign_var("SHOW_SCHEDULE_MISSION", $auth->acl_get('u_schedule_mission'));

        $userid = $user->data["user_id"];
        $user_is_admin = $auth->acl_get("a_");

        $admin_sees_all_clause = $user_is_admin ? "OR 1=1" : "";

        $missions_table = self::fm_table_name("missions");
        $packages_table = self::fm_table_name("packages");
        $flights_table = self::fm_table_name("flights");
        $participants_table = self::fm_table_name("scheduled_participants");
        $users_table = USERS_TABLE;

        $results = $this->execute_sql("SELECT
  st.TotalSeats as TotalSeats,
  sf.FilledSeats as FilledSeats,
  m.Id as Id,
  m.Name,
  m.Date as Start,
  m.Creator,
  m.Published,
  u.username as CreatorName,
  DATE_ADD(m.Date, INTERVAL m.ScheduledDuration MINUTE) AS End
FROM
(SELECT
  m.Id as MissionId,
  SUM(Seats) as TotalSeats
FROM {$flights_table} as f
INNER JOIN {$packages_table} as p
on f.PackageId = p.Id
INNER JOIN {$missions_table} as m
on p.MissionId = m.Id
GROUP BY m.Id
) as st
LEFT JOIN
(SELECT
  m.Id as MissionId,
  COUNT(*) as FilledSeats
FROM {$participants_table} as sp
INNER JOIN {$flights_table} as f
ON sp.FlightId = f.Id
INNER JOIN {$packages_table} as p
on f.PackageId = p.Id
INNER JOIN {$missions_table} as m
on p.MissionId = m.Id
GROUP BY m.Id
) as sf
ON sf.MissionId = st.MissionId
INNER JOIN {$missions_table} AS m
ON m.Id = st.MissionId
INNER JOIN {$users_table} AS u
ON m.Creator = u.user_id
WHERE
m.Published = b'1'
OR m.Creator = {$userid}
{$admin_sees_all_clause}");

        $missiondata = [];
        while ($row = $db->sql_fetchrow($results))
        {
            $db_start = new \DateTime($row["Start"], new \DateTimeZone("UTC"));
            $db_end = new \DateTime($row["End"], new \DateTimeZone("UTC"));
            $missionid = $row["Id"];
            $view_link = $this->helper->route('ato_display_mission_route',
                                              array('missionid' => $missionid));
            $missioninfo = array("Id" => $missionid,
                                 "Title" => $row["Name"],
                                 "Creator" => $row["CreatorName"],
                                 "Published" => $row["Published"],
                                 "TotalSeats" => $row["TotalSeats"],
                                 "FilledSeats" => $row["FilledSeats"],
                                 "Start" => $db_start->format(DATE_ATOM),
                                 "End" => $db_end->format(DATE_ATOM),
                                 "StartICS" => $db_start->format("Ymd\THis\Z"),
                                 "EndICS" => $db_end->format("Ymd\THis\Z"),
                                 "Url" => $view_link);
            $template->assign_block_vars("missions", $missioninfo);
            $missiondata[] = $missioninfo;
        }

        $db->sql_freeresult($results);

        $this->assign_timezones_var("timezones", true);

        if ($request->variable("format", "html") == "ics")
        {
            return $this->iCalResponse($missiondata);
        }
        else
        {
            return $this->helper->render('ato-index.html', '440th VFW ATO');
        }
    }

    private function get_username($user_id)
    {
        global $db;

        $safe_user_id = $db->sql_escape($user_id);
        $result = $this->execute_sql("SELECT `username` FROM `phpbb_users` WHERE `user_id` = {$safe_user_id} LIMIT 1");

        $val = null;
        if ($row = $db->sql_fetchrow($result))
        {
            $val = $row["username"];
        }

        $db->sql_freeresult($result);
        return $val;
    }

    private function get_flight_callsign($flight_id)
    {
        global $db;

        $flights_table = self::fm_table_name("flights");
        $flight_callsigns_table = self::fm_table_name("flight_callsigns");

        $safe_flight_id = $db->sql_escape($flight_id);
        $result = $this->execute_sql("SELECT cs.Name as Name, f.CallsignNum as Num
FROM {$flights_table} AS f
INNER JOIN {$flight_callsigns_table} AS cs
ON f.CallsignId = cs.Id
WHERE f.Id = {$safe_flight_id}");

        $val = null;

        if ($row = $db->sql_fetchrow($result))
        {
            $val = $row["Name"] . " " . $row["Num"];
        }

        $db->sql_freeresult($result);
        return $val;

    }
    private function get_signed_in_pilot_username($flight_id, $seat)
    {
        global $db;

        $flights_table = self::fm_table_name("flights");
        $flight_callsigns_table = self::fm_table_name("flight_callsigns");
        $scheduled_participants_table = self::fm_table_name("scheduled_participants");
        $users_table = USERS_TABLE;

        $safe_flight_id = $db->sql_escape($flight_id);
        $safe_seat = $db->sql_escape($seat);
        $result = $this->execute_sql("SELECT
  u.username AS username
FROM {$users_table} AS u
INNER JOIN {$scheduled_participants_table} AS sp
ON sp.FlightId = {$safe_flight_id}
WHERE sp.SeatNum = {$safe_seat}
AND sp.MemberPilot = u.user_id");

        $val = null;

        if ($row = $db->sql_fetchrow($result))
        {
            $val = $row["username"];
        }

        $db->sql_freeresult($result);
        return $val;
    }

    private function strip_query_string($url)
    {
        $pos = strpos($url, "?");

        if ($pos === false)
        {
            return $url;
        }
        else
        {
            return substr($url, 0, $pos);
        }
    }

    public function handle_display_mission($missionid)
    {
        global $auth, $config, $db, $request, $template, $user;

        $form_key = "ato-display-mission";

        add_form_key($form_key);

        $userid = $user->data["user_id"];
        $user_is_admin = $auth->acl_get("a_");
        $can_sign_others_in = $auth->acl_get("u_ato_assign_seats");

        $missions_table = self::fm_table_name("missions");
        $packages_table = self::fm_table_name("packages");
        $flights_table = self::fm_table_name("flights");
        $participants_table = self::fm_table_name("scheduled_participants");
        $admittance_table = self::fm_table_name("admittance");
        $admittance_groups_table = self::fm_table_name("admittance_groups");
        $missiontypes_table = self::fm_table_name("missiontypes");
        $theaters_table = self::fm_table_name("theaters");
        $scheduled_participants_table = self::fm_table_name("scheduled_participants");
        $users_table = USERS_TABLE;

        $missionid = $db->sql_escape($missionid);
        $result = $this->execute_sql("SELECT
  m.Published         AS Published,
  m.Name              AS Name,
  m.OpenTo            AS OpenToId,
  adm.Name            AS OpenTo,
  theater.Name        AS Theater,
  types.Name          AS Type,
  m.Date              AS Date,
  m.ScheduledDuration AS ScheduledDuration,
  m.Description       AS Description,
  m.ServerAddress     AS ServerAddress,
  m.Creator           AS Creator,
  u.username          AS CreatorName,
  st.TotalSeats       AS TotalSeats,
  sf.FilledSeats      AS FilledSeats
FROM {$missions_table} as m
LEFT JOIN (
  SELECT
    m.Id as MissionId,
    SUM(Seats) as TotalSeats
  FROM {$flights_table} as f
  INNER JOIN {$packages_table} as p
  on f.PackageId = p.Id
  INNER JOIN {$missions_table} as m
  on p.MissionId = m.Id
  GROUP BY m.Id
) as st
ON st.MissionId = m.Id
LEFT JOIN (
  SELECT
    m.Id as MissionId,
    COUNT(*) as FilledSeats
  FROM {$participants_table} as sp
  INNER JOIN {$flights_table} as f
  ON sp.FlightId = f.Id
  INNER JOIN {$packages_table} as p
  on f.PackageId = p.Id
  INNER JOIN {$missions_table} as m
  on p.MissionId = m.Id
  GROUP BY m.Id
) as sf
ON sf.MissionId = m.Id
INNER JOIN {$admittance_table} as adm
ON adm.Id = m.OpenTo
INNER JOIN {$theaters_table} as theater
ON theater.Id = m.Theater
INNER JOIN {$missiontypes_table} as types
ON types.Id = m.Type
INNER JOIN {$users_table} as u
ON m.Creator = u.user_id
WHERE m.Id = {$missionid}");

        $row = $db->sql_fetchrow($result);

        $db->sql_freeresult($result);

        if ( ! $row )
        {
            return $this->helper->render('ato-mission-not-found.html', '440th VFW ATO');
        }

        $missiondata = $row;
        $total_seats = $missiondata["TotalSeats"];
        $filled_seats = $missiondata["FilledSeats"];

        $user_is_editor = $user_is_admin || ($userid == $missiondata["Creator"]);

        // Only editors can see unpublished missions
        if ( ! $missiondata["Published"] && ! $user_is_editor )
        {
            return $this->helper->render('ato-mission-not-found.html', '440th VFW ATO');
        }

        $template->assign_var("SHOW_EDIT_MISSION", $user_is_editor);
        $template->assign_var("ATO_EDIT_MISSION_PAGE", $this->helper->route('ato_edit_mission_route',
                                                                            array('missionid' => $missionid)));
        $template->assign_var("SHOW_SIGNIN_USERS", $can_sign_others_in);
        $template->assign_var("USER_ID", $user->data["user_id"]);

        $user_can_sign_in = false;

        if ($user_is_editor)
        {
            $user_can_sign_in = true;
        }
        else
        {
            $opento_admittance_id = $missiondata["OpenToId"];
            $result = $this->execute_sql("SELECT GroupId
FROM {$admittance_groups_table}
WHERE AdmittanceId = {$opento_admittance_id}");

            $user_groups = $this->group_memberships($userid);

            $admitted_groups = array();
            while ($row = $db->sql_fetchrow($result))
            {
                $admitted_groups[] = $row["GroupId"];
            }

            $db->sql_freeresult($result);

            if (! empty(array_intersect($admitted_groups, $user_groups)))
            {
                $user_can_sign_in = true;
            }
        }

        if ($request->is_set_post("sign-in"))
        {
            if (!check_form_key($form_key))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            if (! $user_can_sign_in)
            {
                trigger_error('NOT_AUTHORISED');
            }

            $signin_data = $request->variable("sign-in", array(0 => array(0 => "")));
            $signin_flight = array_keys($signin_data)[0];
            $signin_seat = array_keys($signin_data[$signin_flight])[0];

            $signin_flight = $db->sql_escape($signin_flight);
            $signin_seat = $db->sql_escape($signin_seat);
            $signin_pilots = $request->variable("sign-in-pilot", array(0 => array(0 => "")));
            $signin_userid = $can_sign_others_in ? $signin_pilots[$signin_flight][$signin_seat] : $userid;

            $sql = "INSERT INTO {$scheduled_participants_table}
(FlightId, SeatNum, MemberPilot)
VALUES ({$signin_flight}, {$signin_seat}, {$signin_userid})";
            $db->sql_freeresult($this->execute_sql($sql));

            if ($config['ato_discord_url'] && $missiondata["Published"])
            {
                $board_url = generate_board_url(true);
                $mission_url = $this->helper->route('ato_display_mission_route',
                                                    array('missionid' => $missionid));
                $clean_url = $this->strip_query_string($mission_url);
                $mission_name = $missiondata["Name"];
                $initiator_username = $this->get_username($userid);
                $flight_callsign = $this->get_flight_callsign($signin_flight);
                $seats_remaining = $total_seats - $filled_seats - 1;
                $pilot_username = $signin_userid == $userid ? "" : ($this->get_username($signin_userid) . " ");
                $this->post_discord_message("{$initiator_username} signed {$pilot_username}in to {$flight_callsign}-{$signin_seat} for mission _{$mission_name}_ ({$board_url}{$clean_url}). There are now {$seats_remaining} of {$total_seats} seats open.");
            }
        }

        if ($request->is_set_post("sign-out"))
        {
            if (!check_form_key($form_key))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            if (! $user_can_sign_in)
            {
                trigger_error('NOT_AUTHORISED');
            }

            $signout_data = $request->variable("sign-out", array(0 => array(0 => "")));
            $signout_flight = array_keys($signout_data)[0];
            $signout_seat = array_keys($signout_data[$signout_flight])[0];
            $userid = $user->data["user_id"];

            $signout_flight = $db->sql_escape($signout_flight);
            $signout_seat = $db->sql_escape($signout_seat);
            $user_clause = $can_sign_others_in ? "" : "AND MemberPilot = {$userid}";

            $signout_username = $this->get_signed_in_pilot_username($signout_flight, $signout_seat);

            $sql = "DELETE FROM {$scheduled_participants_table}
WHERE FlightId = {$signout_flight}
AND SeatNum = {$signout_seat}
{$user_clause}";
            $db->sql_freeresult($this->execute_sql($sql));

            if ($config['ato_discord_url'] && $missiondata["Published"])
            {
                $board_url = generate_board_url(true);
                $mission_url = $this->helper->route('ato_display_mission_route',
                                                    array('missionid' => $missionid));
                $clean_url = $this->strip_query_string($mission_url);
                $mission_name = $missiondata["Name"];
                $initiator_username = $this->get_username($userid);
                $flight_callsign = $this->get_flight_callsign($signout_flight);
                $seats_remaining = $total_seats - $filled_seats + 1;
                $pilot_username = $initiator_username == $signout_username ? "" : ($signout_username . " ");
                $this->post_discord_message("{$initiator_username} signed {$pilot_username}out of {$flight_callsign}-{$signout_seat} for mission _{$mission_name}_ ({$board_url}{$clean_url}). There are now {$seats_remaining} of {$total_seats} seats open.");
            }

        }

        $duration_mins = (int) $row["ScheduledDuration"];
        $missiondata["DurationHours"] = floor($duration_mins / 60);
        $missiondata["DurationMins"] = sprintf("%02d", $duration_mis % 60);

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
                $signed_in_pilot = (int) $flightinfo["Participants"][$i]["MemberPilotId"];
                if ($user_can_sign_in)
                {
                    if ($signed_in_pilot != null)
                    {
                        if ($can_sign_others_in || ($signed_in_pilot == $user->data["user_id"]))
                        {
                            $flightinfo["Participants"][$i]["Action"] = "sign-out";
                        }
                    }
                    else
                    {
                        $flightinfo["Participants"][$i] = array("Action" => "sign-in");
                    }
                }
            }
            $template->assign_block_vars("flights", $flightinfo);
        }

        $template->assign_vars($missiondata);
        $this->assign_timezones_var("timezones");
        if ($can_sign_others_in)
        {
            $this->assign_pilots_var("pilots", $missiondata["OpenToId"]);
        }

        return $this->helper->render('ato-display-mission.html', '440th VFW ATO');
    }

    public function read_db_missiondata($missionid, $tzName)
    {
        global $db;

        $missions_table = self::fm_table_name("missions");
        $packages_table = self::fm_table_name("packages");
        $flights_table = self::fm_table_name("flights");
        $participants_table = self::fm_table_name("scheduled_participants");

        $safe_mission_id = $db->sql_escape($missionid);

        $result = $this->execute_sql("SELECT
  m.Published         AS Published,
  m.Name              as Name,
  m.Theater           as Theater,
  m.Type              as Type,
  m.Date              as Date,
  m.Description       as Description,
  m.ServerAddress     as ServerAddress,
  m.ScheduledDuration as ScheduledDuration,
  m.OpenTo            as OpenTo,
  st.TotalSeats       as TotalSeats,
  sf.FilledSeats      as FilledSeats
FROM {$missions_table} as m
LEFT JOIN
(
  SELECT
    m.Id as MissionId,
    SUM(Seats) as TotalSeats
  FROM {$flights_table} as f
  INNER JOIN {$packages_table} as p
  on f.PackageId = p.Id
  INNER JOIN {$missions_table} as m
  on p.MissionId = m.Id
  GROUP BY m.Id
) as st
ON st.MissionId = m.Id
LEFT JOIN
(
  SELECT
    m.Id as MissionId,
    COUNT(*) as FilledSeats
  FROM {$participants_table} as sp
  INNER JOIN {$flights_table} as f
  ON sp.FlightId = f.Id
  INNER JOIN {$packages_table} as p
  on f.PackageId = p.Id
  INNER JOIN {$missions_table} as m
  on p.MissionId = m.Id
  GROUP BY m.Id
) as sf
ON sf.MissionId = m.Id
WHERE m.Id = {$safe_mission_id}");

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
            "MISSIONUTCDATE" => $row["Date"],
            "DESCRIPTION" => $row["Description"],
            "SERVER"      => $row["ServerAddress"],
            "DURATION"    => $row["ScheduledDuration"],
            "OPENTO"      => $row["OpenTo"],
            "MISSIONLINK" => $this->helper->route('ato_display_mission_route',
                                                  array('missionid' => $missionid)),
            "TotalSeats"  => $row["TotalSeats"],
            "FilledSeats" => $row["FilledSeats"]
        );

        $db->sql_freeresult($result);

        $db_date = new \DateTime($missiondata["MISSIONUTCDATE"], new \DateTimeZone("UTC"));
        $missiondata["MISSIONUTCDATE"] = $db_date->format("D Y-m-d H:i");

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
                                     . self::fm_table_name("packages")
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

        $packages_table = self::fm_table_name("packages");
        $flights_table = self::fm_table_name("flights");
        $flight_callsigns_table = self::fm_table_name("flight_callsigns");
        $roles_table = self::fm_table_name("roles");
        $aircraft_table = self::fm_table_name("aircraft");
        $users_table = USERS_TABLE;
        $scheduled_participants_table = self::fm_table_name("scheduled_participants");
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

    private function assign_timezones_var($varname, $exclude_blank = false)
    {
        global $template;

        if (! $exclude_blank)
        {
            $template->assign_block_vars($varname, array("" => ""));
        }

        foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL) as $tzid => $tzname)
        {
            $tzoffset = (new \DateTimeZone($tzname))->getOffset(new \DateTime());
            $tzoffsetstr = sprintf("%s%02d%02d",
                                   $tzoffset < 0 ? "-" : "+",
                                   abs($tzoffset) / 60 / 60,
                                   (abs($tzoffset) / 60) % 60);

            $template->assign_block_vars($varname, array("Id" => $tzname,
                                                         "Name" => "[{$tzoffsetstr}] {$tzname}"));
        }

    }

    private function assign_pilots_var($varname, $opento)
    {
        global $db, $template;

        $admittance_groups_table = self::fm_table_name("admittance_groups");

        $sql = "SELECT DISTINCT
  u.user_id as Id,
  u.username as Name
FROM phpbb_users AS u
INNER JOIN phpbb_user_group AS ug
ON ug.user_id = u.user_id
INNER JOIN phpbb_groups as g
ON ug.group_id = g.group_id
INNER JOIN {$admittance_groups_table} AS ag
ON ag.GroupId = g.group_id
WHERE ag.AdmittanceId = {$opento}
ORDER BY LOWER(u.username)";
        $result = $this->execute_sql($sql);

        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars($varname, $row);
        }

        $db->sql_freeresult($result);
    }


    public function handle_edit_mission($missionid)
    {
        global $auth, $config, $db, $request, $template, $user;

        $form_key = "ato-display-mission";

        add_form_key($form_key);

        $missions_table = self::fm_table_name("missions");

        $is_published = false;

        if ($missionid == "new")
        {
            if (!$auth->acl_get('u_schedule_mission'))
            {
                trigger_error('NOT_AUTHORISED');
            }
        }
        else
        {
            $result = $this->execute_sql("select Creator from " . self::fm_table_name("missions") . " where Id = {$missionid}");

            $creator = $db->sql_fetchfield("Creator");
            $db->sql_freeresult($result);

            if (!($auth->acl_get("a_") || $creator == $user->data["user_id"]))
            {
                trigger_error('You must be the creator of this mission (or an administrator) to edit it.');
            }

            $missionid = $db->sql_escape($missionid);
            $result = $this->execute_sql("SELECT Id, Published from {$missions_table} where Id = {$missionid}");
            $row = $db->sql_fetchrow($result);

            if ( ! $row )
            {
                return $this->helper->render('ato-mission-not-found.html', '440th VFW ATO');
            }

            $is_published = $row["Published"];

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

        if ($missionid != "new" && $request->is_set_post("delete-mission"))
        {
            $deletestage = $request->variable("deletestage", "");
            if ($deletestage == "Confirming")
            {
                // check mode
                if (confirm_box(true))
                {
                    $missionid = $db->sql_escape($missionid);

                    $sql = "DELETE FROM "
                         . $missions_table
                         . " "
                         . " WHERE Id = {$missionid}";
                    $db->sql_freeresult($this->execute_sql($sql));

                    return new RedirectResponse($this->helper->route('ato_index_route',
                                                                     array()));
                }
                else
                {
                    return new RedirectResponse($this->helper->route('ato_edit_mission_route',
                                                                     array("missionid" => $missionid)));
                }
            }
            else
            {
                $s_hidden_fields = build_hidden_fields(array(
                    'submit'         => true,
                    'missionid'      => $missionid,
                    'delete-mission' => "Yes",
                    'deletestage'    => 'Confirming'
                )
                );

                //display mode
                confirm_box(false, 'This action cannot be undone. Really delete this mission?', $s_hidden_fields);
            }
        }

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
                             . self::fm_table_name("packages")
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
            $packagedata[$packageid] = array("Name"   => "",
                                             "Number" => "");
            $flightdata[$this->get_new_flightid($flightdata)]
                = $this->new_flight_data($packageid);
        }

        if ($request->is_set_post("add-flight"))
        {
            $should_attempt_save = true;
            $packageid_for_flight = array_keys($request->variable("add-flight", array("" => "")))[0];
            $maxid = -1;

            $flightid = $this->get_new_flightid($flightdata);
            $flightdata[$flightid] = $this->new_flight_data($packageid_for_flight);
        }

        if ($request->is_set_post("delete-flight"))
        {
            $newflightdata = array();
            $should_attempt_save = true;
            $deleted_flight_id = array_keys($request->variable("delete-flight", array("" => "")))[0];

            foreach ($flightdata as $flightid => $flightinfo)
            {
                if ($flightid == $deleted_flight_id)
                {
                    if (! (strpos($flightid, "new-") === 0))
                    {
                        $sql = "DELETE FROM "
                             . self::fm_table_name("flights")
                             . " "
                             . " WHERE Id = "
                             . $db->sql_escape($flightid);
                        $db->sql_freeresult($this->execute_sql($sql));
                    }
                }
                else
                {
                    $newflightdata[$flightid] = $flightinfo;
                }
            }

            $flightdata = $newflightdata;
        }

        if ($request->is_set_post("save"))
        {
            $should_attempt_save = true;
        }

        if ($should_attempt_save)
        {
            if (!check_form_key($form_key))
            {
                trigger_error($user->lang['FORM_INVALID']);
            }

            $valid = true;

            if (trim($request->variable("missionname", "")) == false)
            {
                $valid = false;
                $template->assign_var("NAME_ERROR", "Mission name is required");
            }

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

                if ($flightinfo["Seats"] < 0)
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
                    "Name"              => htmlspecialchars_decode($request->variable("missionname", "")),
                    "Theater"           => (int) $request->variable("theater", ""),
                    "Type"              => (int) $request->variable("missiontype", ""),
                    "Date"              => $this->format_sql_date($parseddate),
                    "Description"       => htmlspecialchars_decode($request->variable("description", "")),
                    "ServerAddress"     => htmlspecialchars_decode($request->variable("server", "")),
                    "ScheduledDuration" => (int) $request->variable("duration", ""),
                    "OpenTo"            => (int) $request->variable("opento", 0),
                );
                // Insert or Update database
                if ($missionid == "new")
                {
                    $params["Creator"] = $user->data["user_id"];
                    $sql = "INSERT INTO "
                         . self::fm_table_name("missions")
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
                         . self::fm_table_name("missions")
                         . " SET "
                         . $db->sql_build_array("UPDATE", $params)
                         . " WHERE Id = " . $db->sql_escape($missionid);
                    $db->sql_freeresult($this->execute_sql($sql));
                }

                $newpackagedata = [];
                foreach ($packagedata as $packageid => $packageinfo)
                {
                    $packagenumber = $packageinfo["Number"] == "" ? null : (int) $packageinfo["Number"];
                    $packagename = htmlspecialchars_decode($packageinfo["Name"]);
                    $params = array("MissionId" => $missionid,
                                    "Name" => $packagename,
                                    "Number" => $packagenumber);
                    if (strpos($packageid, "new-") === 0)
                    {
                        $sql = "INSERT INTO "
                             . self::fm_table_name("packages")
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
                             . self::fm_table_name("packages")
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
                    $takeoff = htmlspecialchars_decode($flightinfo["TakeoffTime"]);
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
                             . self::fm_table_name("flights")
                             . " "
                             . $db->sql_build_array("INSERT", $params);
                        $db->sql_freeresult($this->execute_sql($sql));

                        $newflightid = $db->sql_nextid();
                    }
                    else
                    {
                        $newflightid = (int) $flightid;
                        $sql = "UPDATE "
                             . self::fm_table_name("flights")
                             . " SET "
                             . $db->sql_build_array("UPDATE", $params)
                             . " WHERE Id = "
                             . $db->sql_escape($flightid);
                        $db->sql_freeresult($this->execute_sql($sql));
                    }
                    $newflightdata[$newflightid] = $params;

                }

                $flightdata = $newflightdata;

                $tzName = $request->variable("mission-timezone", $defaultTimezone);
                $missiondata = $this->read_db_missiondata($missionid, $tzName);

                // Notify when state goes from not published (including nonexistent) to published
                if (!$is_published && $missiondata["PUBLISHED"])
                {
                    if ($config['ato_discord_url'])
                    {
                        $board_url = generate_board_url(true);
                        $mission_url = $this->helper->route('ato_display_mission_route',
                                                        array('missionid' => $missionid));
                        $clean_url = $this->strip_query_string($mission_url);
                        $mission_name = $missiondata["MISSIONNAME"];
                        $mission_utc_date = $missiondata["MISSIONUTCDATE"];
                        $initiator_username = $this->get_username($user->data["user_id"]);
                        $total_seats = $missiondata["TotalSeats"];
                        $available_seats = $total_seats - $missiondata["FilledSeats"];
                        $this->post_discord_message("{$initiator_username} created mission _{$mission_name}_, to be flown {$mission_utc_date} (GMT). See details at {$board_url}{$clean_url}. There are {$available_seats} seats available of {$total_seats} total.");
                    }
                }

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
                    "MISSIONNAME" => htmlspecialchars_decode($request->variable("missionname", "")),
                    "THEATER" => (int) $request->variable("theater", 0),
                    "MISSIONTYPE" => (int) $request->variable("missiontype", 0),
                    "MISSIONDATE" => $request->variable("mission-date", date("Y-m-d 12:00", strtotime("+1 week"))),
                    "MISSIONTIMEZONE" => $request->variable("mission-timezone", ""),
                    "DESCRIPTION" => htmlspecialchars_decode($request->variable("description", '')),
                    "SERVER" => htmlspecialchars_decode($request->variable("server", '')),
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
                "MISSIONNAME" => "",
                "THEATER" => 0,
                "MISSIONTYPE" => 0,
                "MISSIONDATE" => "",
                "MISSIONTIMEZONE" => $defaultTimezone,
                "DESCRIPTION" => '',
                "SERVER" => '',
                "DURATION" => 120,
                "OPENTO" => 0
            );

            $packagedata["new-0"] = array("Name"   => "",
                                          "Number" => "");
            $flightdata["new-0"] = $this->new_flight_data("new-0");
        }
        // It's a GET for an existing mission
        else
        {
            $tzName = $request->variable("mission-timezone", $defaultTimezone);
            $missiondata = $this->read_db_missiondata($missionid, $tzName);
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

        $template->assign_var("SHOW_DELETE_BUTTON", $missionid != "new");

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
}
