<?php

namespace VFW440\flight_management\migrations;

use \VFW440\flight_management\helper\Util;

class v00001 extends \phpbb\db\migration\migration
{
    private function run_sql($sql)
    {
        error_log("run_sql: " . $sql);
        $rows = $this->sql_query($sql);
        if ($rows != 1)
        {
            throw new \Exception("failure executing SQL: " . json_encode($this->errors));
        }
    }

    static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v32x\v322');
	}

    // public function effectively_installed()
    // {
    //     error_log("Checking for installation of " . Util::fm_table_name("missions"));

    //     return $this->db_tools->sql_table_exists(Util::fm_table_name("missions"));
    // }

    public function update_schema()
    {
        error_log("update_schema for " . Util::fm_table_name("missions"));
    
        // No foreign key constraints. Sigh. Also, unbelievably, null
        // in the second position of a column definition means the
        // column is nullable. I.e. a NOT NULL column must specify a
        // default if it's going to also specify flags. So we'll do
        // everything as custom, since this is insane.
        return array(
		);

    }

    public function  revert_schema()
    {
        error_log("Revert schema");
        return array();
    }

    public function update_data()
    {
        error_log("update_data");

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
                    'modes'                 => array('theaters', 'missiontypes', 'roles', 'flight-callsigns'),
                ),
            )),

            // Permission to schedule a new mission
            array('permission.add', array('u_schedule_mission')),

            // Set permissions
			array('permission.permission_set', array('ROLE_ADMIN_FULL', 'u_schedule_mission')),
			array('permission.permission_set', array('ROLE_ADMIN_STANDARD', 'u_schedule_mission')),
        );
    }

    public function revert_data()
    {
        error_log("revert_data()");
        return array(
            array('custom', array(array($this, 'drop_tables'))),
        );
    }

    public function create_tables()
    {
        error_log("create_tables");

        $phpbb_table_prefix = $this->table_prefix;
        $fm_table_prefix = Util::$fm_table_prefix;

        // TODO: Convert these to use Util::fm_table_name
        $this->run_sql("CREATE TABLE {$fm_table_prefix}missiontypes (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE ${fm_table_prefix}flight_callsigns (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE {$fm_table_prefix}theaters (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Version NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE {$fm_table_prefix}missions (
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
  FOREIGN KEY (Creator) REFERENCES {$phpbb_table_prefix}users(user_id),
  FOREIGN KEY (Type) REFERENCES ${fm_table_prefix}missiontypes(Id),
  FOREIGN KEY (Theater) REFERENCES ${fm_table_prefix}theaters(Id)
);");

        $this->run_sql("CREATE TABLE ${fm_table_prefix}packages (
  Id INT UNSIGNED AUTO_INCREMENT,
  MissionId INT UNSIGNED NOT NULL,
  Number INT UNSIGNED,
  NAME nvarchar(1024),
  PRIMARY KEY (Id),
  FOREIGN KEY (MissionId) REFERENCES ${fm_table_prefix}missions(Id)
);");

        $this->run_sql("CREATE TABLE ${fm_table_prefix}roles (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE ${fm_table_prefix}aircraft (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE ${fm_table_prefix}flights (
  Id INT UNSIGNED AUTO_INCREMENT,
  PackageId INT UNSIGNED NOT NULL,
  Callsign NVARCHAR(1024) NOT NULL,
  CallsignNum INT UNSIGNED NOT NULL,
  RoleId INT UNSIGNED NOT NULL,
  AircraftId INT UNSIGNED NOT NULL,
  TakeoffTime INT UNSIGNED, -- Falcon Time: minutes from midnight day 1
  PRIMARY KEY (Id),
  FOREIGN KEY (PackageId) REFERENCES ${fm_table_prefix}packages(Id),
  FOREIGN KEY (RoleId) REFERENCES ${fm_table_prefix}roles(Id),
  FOREIGN KEY (AircraftId) REFERENCES ${fm_table_prefix}aircraft(Id)    
);");

        $this->run_sql("CREATE TABLE ${fm_table_prefix}scheduled_participants (
  SeatNum INT UNSIGNED NOT NULL,
  FlightId INT UNSIGNED NOT NULL,
  MemberPilot INT UNSIGNED,
  NonmemberPilot NVARCHAR(1024),
  ConfirmedFlown BIT DEFAULT b'0' NOT NULL,
  FOREIGN KEY (FlightId) REFERENCES ${fm_table_prefix}flights(Id),
  FOREIGN KEY (MemberPilot) REFERENCES {$this->table_prefix}users(user_id),
  UNIQUE KEY (SeatNum, FlightId)
);");

        error_log("tables created");
        return true;
    }

    public function drop_tables()
    {
        $fm_table_prefix = Util::$fm_table_prefix;
        error_log("drop_tables");
        $tables = array("{$fm_table_prefix}scheduled_participants",
                        "{$fm_table_prefix}flights",
                        "{$fm_table_prefix}aircraft",
                        "{$fm_table_prefix}roles",
                        "{$fm_table_prefix}packages",
                        "{$fm_table_prefix}missions",
                        "{$fm_table_prefix}theaters",
                        "{$fm_table_prefix}missiontypes",
                        "{$fm_table_prefix}flight_callsigns"
        );
        foreach ($tables as $table) {
            $this->run_sql("DROP TABLE {$table};");
        }
        error_log("tables were dropped");
        return true;
    }

}

