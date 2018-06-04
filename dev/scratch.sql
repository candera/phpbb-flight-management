DROP TABLE IF EXISTS ato2_missiontypes;

CREATE TABlE ato2_missiontypes (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DESCRIBE ato2_missiontypes;

DROP TABLE IF EXISTS ato2_theaters;

CREATE TABLE ato2_theaters (
  Id INT UNSIGNED AUTO_INCREMENT,
  TheaterName NVARCHAR(1024) NOT NULL,
  TheaterVersion NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DESCRIBE ato2_theaters;

DROP TABLE IF EXISTS ato2_missions;

CREATE TABLE ato2_missions (
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
  FOREIGN KEY (Creator) REFERENCES phpbb_users(user_id),
  FOREIGN KEY (Type) REFERENCES ato2_missiontypes(Id),
  FOREIGN KEY (Theater) REFERENCES ato2_theaters(Id)
);

DROP TABLE IF EXISTS ato2_packages;

CREATE TABLE ato2_packages (
  Id INT UNSIGNED AUTO_INCREMENT,
  MissionId INT UNSIGNED NOT NULL,
  Number INT UNSIGNED,
  NAME nvarchar(1024),
  PRIMARY KEY (Id),
  FOREIGN KEY (MissionId) REFERENCES ato2_missions(Id)
);

DROP TABLE IF EXISTS ato2_roles;

CREATE TABLE ato2_roles (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DROP TABLE IF EXISTS ato2_aircraft;

CREATE TABLE ato2_aircraft (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DROP TABLE IF EXISTS ato2_flights;

CREATE TABLE ato2_flights (
  Id INT UNSIGNED AUTO_INCREMENT,
  PackageId INT UNSIGNED NOT NULL,
  Callsign NVARCHAR(1024) NOT NULL,
  CallsignNum INT UNSIGNED NOT NULL,
  RoleId INT UNSIGNED NOT NULL,
  AircraftId INT UNSIGNED NOT NULL,
  TakeoffTime INT UNSIGNED, -- Falcon Time: minutes from midnight day 1
  PRIMARY KEY (Id),
  FOREIGN KEY (PackageId) REFERENCES ato2_packages(Id),
  FOREIGN KEY (RoleId) REFERENCES ato2_roles(Id),
  FOREIGN KEY (AircraftId) REFERENCES ato2_aircraft(Id)    
);

DROP TABLE IF EXISTS ato2_scheduled_participants;

CREATE TABLE ato2_scheduled_participants (
  SeatNum INT UNSIGNED NOT NULL,
  FlightId INT UNSIGNED NOT NULL,
  MemberPilot INT UNSIGNED,
  NonmemberPilot NVARCHAR(1024),
  ConfirmedFlown BIT DEFAULT b'0' NOT NULL,
  FOREIGN KEY (FlightId) REFERENCES ato2_flights(Id),
  FOREIGN KEY (MemberPilot) REFERENCES phpbb_users(user_id),
  UNIQUE KEY (SeatNum, FlightId)
);

------------------------------------------
n
INSERT INTO


drop table if exists ato2_scheduled_participants;
drop table if exists ato2_flights;
drop table if exists ato2_aircraft;
drop table if exists ato2_roles;
drop table if exists ato2_packages;
drop table if exists ato2_missions;              
drop table if exists ato2_theaters;
drop table if exists ato2_missiontypes;
drop table if exists ato2_flight_callsigns;
drop table if exists ato2_admittance_groups;
drop table if exists ato2_admittance;

show tables LIKE 'ato2%';

show tables LIKE 'ato%';

describe ato_callsigns;

DESCRIBE ato2_flight_callsigns;

INSERT INTO ato2_flight_callsigns (NAME, active) (SELECT callsign, callsign_active FROM ato_callsigns);

SELECT CAST(active AS unsigned) FROM ato2_flight_callsigns;

DELETE FROM ato2_flight_callsigns;

DESCRIBE ato2_aircraft;

SELECT * FROM ato_aircraft;

DESCRIBE ato_mission_types;

DESCRIBE ato2_missiontypes;

SELECT a.NAME, a.Id
FROM ato2_admittance AS a
LEFT OUTER JOIN ag.
