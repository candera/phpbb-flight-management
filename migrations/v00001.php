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
        $this->drop_tables();
        return array();
    }

    public function update_data()
    {
        error_log("update_data");

        return array(
            array('custom', array(array($this, 'create_tables'))),

            array('custom', array(array($this, 'populate_tables'))),

            // Add a parent module to the Extensions tab (ACP_CAT_DOT_MODS)
            array('module.add', array(
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_VFW440_FM_CODE_TABLES_TITLE'
            )),

            array('module.add', array(
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_VFW440_ATO_ADMITTANCE_TITLE'
            )),

            // Add our modules to the parent module
            array('module.add', array(
                'acp',
                "ACP_VFW440_FM_CODE_TABLES_TITLE",
                array(
                    'module_basename'       => '\VFW440\flight_management\acp\code_tables_module',
                    // TODO: Some day pull these from MODE_SCHEMAS
                    'modes'                 => array('theaters',
                                                     'missiontypes', 
                                                     'roles',
                                                     'flight-callsigns',
                    )
                ),
            )),

            array('module.add', array(
                'acp',
                "ACP_VFW440_ATO_ADMITTANCE_TITLE",
                array(
                    'module_basename'       => '\VFW440\flight_management\acp\ato_admittance_module',
                    'modes'                 => array('admittance')
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
        $ato_table_prefix = Util::$ato_table_prefix;

        $this->run_sql("CREATE TABLE {$ato_table_prefix}missiontypes (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE ${ato_table_prefix}flight_callsigns (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE {$ato_table_prefix}theaters (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Version NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE {$ato_table_prefix}missions (
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
  FOREIGN KEY (Type) REFERENCES ${ato_table_prefix}missiontypes(Id),
  FOREIGN KEY (Theater) REFERENCES ${ato_table_prefix}theaters(Id)
);");

        $this->run_sql("CREATE TABLE ${ato_table_prefix}packages (
  Id INT UNSIGNED AUTO_INCREMENT,
  MissionId INT UNSIGNED NOT NULL,
  Number INT UNSIGNED,
  NAME nvarchar(1024),
  PRIMARY KEY (Id),
  FOREIGN KEY (MissionId) REFERENCES ${ato_table_prefix}missions(Id)
);");

        $this->run_sql("CREATE TABLE ${ato_table_prefix}roles (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE ${ato_table_prefix}aircraft (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE ${ato_table_prefix}flights (
  Id INT UNSIGNED AUTO_INCREMENT,
  PackageId INT UNSIGNED NOT NULL,
  Callsign NVARCHAR(1024) NOT NULL,
  CallsignNum INT UNSIGNED NOT NULL,
  RoleId INT UNSIGNED NOT NULL,
  AircraftId INT UNSIGNED NOT NULL,
  TakeoffTime INT UNSIGNED, -- Falcon Time: minutes from midnight day 1
  PRIMARY KEY (Id),
  FOREIGN KEY (PackageId) REFERENCES ${ato_table_prefix}packages(Id),
  FOREIGN KEY (RoleId) REFERENCES ${ato_table_prefix}roles(Id),
  FOREIGN KEY (AircraftId) REFERENCES ${ato_table_prefix}aircraft(Id)    
);");

        $this->run_sql("CREATE TABLE ${ato_table_prefix}scheduled_participants (
  SeatNum INT UNSIGNED NOT NULL,
  FlightId INT UNSIGNED NOT NULL,
  MemberPilot INT UNSIGNED,
  NonmemberPilot NVARCHAR(1024),
  ConfirmedFlown BIT DEFAULT b'0' NOT NULL,
  FOREIGN KEY (FlightId) REFERENCES ${ato_table_prefix}flights(Id),
  FOREIGN KEY (MemberPilot) REFERENCES {$this->table_prefix}users(user_id),
  UNIQUE KEY (SeatNum, FlightId)
);");

        $this->run_sql("CREATE TABLE ${ato_table_prefix}admittance (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT Default b'1' NOT NULL,
  PRIMARY KEY (Id)
);");

        $this->run_sql("CREATE TABLE ${ato_table_prefix}admittance_groups (
  AdmittanceId INT UNSIGNED,
  GroupId MEDIUMINT UNSIGNED,
  FOREIGN KEY (AdmittanceId) REFERENCES ${ato_table_prefix}admittance(Id),
  FOREIGN KEY (GroupId) REFERENCES {$this->table_prefix}groups(group_id)
);");   

        error_log("tables created");
        return true;
    }

    public function populate_tables()
    {
        error_log("populate_tables");

        $ato_table_prefix = Util::$ato_table_prefix;
        $zeus_ato_table_prefix = "ato_";
        $zeus_fltlog_table_prefix = "fltlog_";

        $this->run_sql("INSERT INTO ${ato_table_prefix}flight_callsigns 
  (NAME, active)
  (
    SELECT callsign, callsign_active 
    FROM ${zeus_ato_table_prefix}callsigns
  );");

        $this->run_sql("INSERT INTO ${ato_table_prefix}aircraft 
  (NAME, active)
  (
    SELECT aircraft_model, aircraft_active 
    FROM ${zeus_ato_table_prefix}aircraft
  );");

        $this->run_sql("INSERT INTO ${ato_table_prefix}missiontypes 
  (NAME, active)
  (
    SELECT type_name, b'1'
    FROM ${zeus_ato_table_prefix}mission_types
  );");

        $this->run_sql("INSERT INTO ${ato_table_prefix}roles 
  (NAME, active)
  (
    SELECT rolename, b'1'
    FROM ${zeus_fltlog_table_prefix}roles
  );");

        $this->run_sql("INSERT INTO ${ato_table_prefix}theaters
  (Name, Version, Active)
  VALUES 
    ('KTO'           , 'v4.33 U5',     b'1'),
    ('KTO Strong'    , 'v4.33 U5',     b'1'),
    ('ITO'           , 'v1.0.3',       b'1'),
    ('Korea EM 1989' , 'Update v2.57', b'1'),
    ('Balkans'       , 'BMS 4.33 U5',  b'1')
  ;");

        $this->run_sql("INSERT INTO ${ato_table_prefix}admittance
  (Name, Active)
  VALUES 
    ('Wing Members Only',       b'1'),
    ('Wing Members and Cadets', b'1'),
    ('All Registered Users',    b'1')
  ;");

        return true;
    }
    
    public function drop_tables()
    {
        $ato_table_prefix = Util::$ato_table_prefix;
        error_log("drop_tables");
        $tables = array("{$ato_table_prefix}scheduled_participants",
                        "{$ato_table_prefix}flights",
                        "{$ato_table_prefix}aircraft",
                        "{$ato_table_prefix}roles",
                        "{$ato_table_prefix}packages",
                        "{$ato_table_prefix}missions",
                        "{$ato_table_prefix}theaters",
                        "{$ato_table_prefix}missiontypes",
                        "{$ato_table_prefix}flight_callsigns",
                        "{$ato_table_prefix}admittance_groups",
                        "{$ato_table_prefix}admittance"
        );
        foreach ($tables as $table) {
            $this->run_sql("DROP TABLE IF EXISTS {$table};");
        }
        error_log("tables were dropped");
        return true;
    }

}

