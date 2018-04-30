<?php

namespace VFW440\flight_management\controller;

use \Symfony\Component\HttpFoundation\JsonResponse;
use \Symfony\Component\HttpFoundation\Response;

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

	/**
     * Controller for route /ato
     *
     * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
     */
	public function handle_index()
	{
		// $l_message = !$this->config['acme_demo_goodbye'] ? 'DEMO_HELLO' : 'DEMO_GOODBYE';
		// $this->template->assign_var('DEMO_MESSAGE', $this->user->lang($l_message, $name));

		return $this->helper->render('ato-index.html', '440th VFW ATO');
	}

	/**
     * Controller for route /ato/new-mission
     *
     * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
     */
    public function handle_new_mission()
    {
        error_log("hi there");
		return $this->helper->render('ato-new-mission.html', '440th VFW ATO');
    }

    private $query_table_infos =
        array(
            "MissionTypes" =>
            array("Table" => "vfw440_MissionTypes",
                  "Columns" =>
                  array("Id" => "Id",
                        "Name" => "Name",
                        ),
                  "Filter" => "Active = true"),
            "Theaters" =>
            array("Table" => "vfw440_Theaters",
                  "Columns" =>
                  array("Id" => "Id",
                        "Name" => "Name",
                        "Version" => "Version",
                        ),
                  "Filter" => "Active = true"));
    
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
        
            $table_info = $this->query_table_infos[$request_from];
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
