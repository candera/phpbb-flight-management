<?php

namespace VFW440\flight_management\migrations;

class v00001 extends \phpbb\db\migration\migration
{
    // TODO: Factor out at some point
    private function table_name(string $base)
    {
        return "VFW440_" . $base;
    }

    static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v32x\v322');
	}

    public function effectively_installed()
    {
        error_log("Checking for installation of " . $this->table_name("missions"));

        return $this->db_tools->sql_table_exists($this->table_name("missions"));
    }

    public function update_schema()
    {
        error_log("update_schema for " . $this->table_name("missions"));
        
        // $missions_columns =
        //                   array('Id' => array('UINT', null, 'AUTO_INCREMENT'),
        //                         'MissionName' => array('NVARCHAR(1024)', null, 'NOT NULL'));

        // $missions_table = array($this->table_name("missions")
        //                         => array('COLUMNS' => $missions_columns,
        //                                  'PRIMARY_KEY' => 'Id'));

        // return array(
        //     'add_tables' => array(missions_table),
            
        // );

        // No foreign key constraints. Sigh. Also, unbelievably, null
        // in the second position of a column definition means the
        // column is nullable. I.e. a NOT NULL column must specify a
        // default if it's going to also specify flags.
        return array(
			// 'add_tables'		=> array(
            //     $this->table_name('theaters') => array(
            //         'COLUMNS' => array(
            //             'Id' => array('UINT', 0, 'AUTO_INCREMENT'),
            //             'TheaterName' => array('VCHAR_UNI:1024'),
            //             'TheaterVersion' => array('VCHAR_UNI:1024'),
            //             'Active' => array('BOOL', true)
            //         ),
            //         'PRIMARY_KEY' => 'Id',
            //         // This doesn't work either.
            //         /* 'KEYS' => array(
            //              'unique_theater_version' => array('UNIQUE', 'TheaterName', 'TheaterVersion')
            //         ), */
            //     ),
			// 	$this->table_name('missions')	=> array(
			// 		'COLUMNS'		=> array(
			// 			'Id'			=> array('UINT', 0, 'AUTO_INCREMENT'),
			// 			'MissionName'	=> array('VCHAR_UNI:255', null),
			// 		),
			// 		'PRIMARY_KEY'	=> 'Id',
			// 	),
			// )
		);

    }

    public function  revert_schema()
    {
        error_log("Revert schema");
        return array(
            'drop_tables' => array($this->table_name("missions"))
        );
    }

    public function update_data()
    {
        error_log("update_data");
        // return array(array('permission.add', array('u_schedule_mission')));
        return array(
            array('custom', array(array($this, 'create_tables'))),

            // Add a parent module to the Extensions tab (ACP_CAT_DOT_MODS)
            array('module.add', array(
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_VFW440_FM_CODE_TABLES_TITLE'
            )),

            // Add our modules to the parent module
            array('module.add', array(
                'acp',
                "ACP_VFW440_FM_CODE_TABLES_TITLE",
                array(
                    'module_basename'       => '\VFW440\flight_management\acp\code_tables_module',
                    // TODO: Some day pull these from MODE_SCHEMAS
                    'modes'                 => array('theaters', 'missiontypes', 'roles'),
                ),
            )),

        );
    }

    public function revert_data()
    {
        error_log("revert_data()");
        return array(
            array('custom', array(array($this, 'drop_tables'))),
        );
    }

    private function run_sql($sql)
    {
        $rows = $this->sql_query($sql);
        if ($rows != 1)
        {
            throw new \Exception("failure executing SQL: " . implode("\n", $this->errors));
        }
    }
    
    public function create_tables()
    {
        error_log("create_tables");

        $this->run_sql("CREATE TABLE vfw440_missiontypes (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE vfw440_theaters (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Version NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE vfw440_missions (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Date DATETIME NOT NULL,
  Type INT UNSIGNED NOT NULL,
  Description TEXT,
  ServerAddress NVARCHAR(1024),
  ScheduledDuration INT UNSIGNED NOT NULL,
  ActualDuration INT UNSIGNED,
  Theater INT UNSIGNED NOT NULL,
  Creator INT UNSIGNED NOT NULL,
  Published BIT DEFAULT b'0' NOT NULL,
  PRIMARY KEY (Id),
  FOREIGN KEY (Creator) REFERENCES {$this->table_prefix}users(user_id),
  FOREIGN KEY (Type) REFERENCES vfw440_missiontypes(Id),
  FOREIGN KEY (Theater) REFERENCES vfw440_theaters(Id)
);");

        $this->run_sql("CREATE TABLE vfw440_packages (
  Id INT UNSIGNED AUTO_INCREMENT,
  MissionId INT UNSIGNED NOT NULL,
  Number INT UNSIGNED,
  NAME nvarchar(1024),
  PRIMARY KEY (Id),
  FOREIGN KEY (MissionId) REFERENCES vfw440_missions(Id)
);");

        $this->run_sql("CREATE TABLE vfw440_roles (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE vfw440_aircraft (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE vfw440_flights (
  Id INT UNSIGNED AUTO_INCREMENT,
  PackageId INT UNSIGNED NOT NULL,
  Callsign NVARCHAR(1024) NOT NULL,
  CallsignNum INT UNSIGNED NOT NULL,
  RoleId INT UNSIGNED NOT NULL,
  AircraftId INT UNSIGNED NOT NULL,
  TakeoffTime INT UNSIGNED, -- Falcon Time: minutes from midnight day 1
  PRIMARY KEY (Id),
  FOREIGN KEY (PackageId) REFERENCES vfw440_packages(Id),
  FOREIGN KEY (RoleId) REFERENCES vfw440_roles(Id),
  FOREIGN KEY (AircraftId) REFERENCES vfw440_aircraft(Id)    
);");

        $this->run_sql("CREATE TABLE vfw440_scheduled_participants (
  SeatNum INT UNSIGNED NOT NULL,
  FlightId INT UNSIGNED NOT NULL,
  MemberPilot INT UNSIGNED,
  NonmemberPilot NVARCHAR(1024),
  ConfirmedFlown BIT DEFAULT b'0' NOT NULL,
  FOREIGN KEY (FlightId) REFERENCES vfw440_flights(Id),
  FOREIGN KEY (MemberPilot) REFERENCES {$this->table_prefix}users(user_id),
  UNIQUE KEY (SeatNum, FlightId)
);");

        error_log("tables created");
        return true;
    }

    public function drop_tables()
    {
        error_log("drop_tables");
        $tables = array('vfw440_scheduled_participants',
                        'vfw440_flights',
                        'vfw440_aircraft',
                        'vfw440_roles',
                        'vfw440_packages',
                        'vfw440_missions',
                        'vfw440_theaters',
                        'vfw440_missiontypes',
        );
        foreach ($tables as $table) {
            $this->sql_query("DROP TABLE {$table};");
        }
        error_log("tables were dropped");
        return true;
    }

}

