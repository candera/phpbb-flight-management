DROP TABLE IF EXISTS fm_missiontypes;

CREATE TABlE fm_missiontypes (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DESCRIBE fm_missiontypes;

DROP TABLE IF EXISTS fm_theaters;

CREATE TABLE fm_theaters (
  Id INT UNSIGNED AUTO_INCREMENT,
  TheaterName NVARCHAR(1024) NOT NULL,
  TheaterVersion NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DESCRIBE fm_theaters;

DROP TABLE IF EXISTS fm_missions;

CREATE TABLE fm_missions (
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
  FOREIGN KEY (Type) REFERENCES fm_missiontypes(Id),
  FOREIGN KEY (Theater) REFERENCES fm_theaters(Id)
);

DROP TABLE IF EXISTS fm_packages;

CREATE TABLE fm_packages (
  Id INT UNSIGNED AUTO_INCREMENT,
  MissionId INT UNSIGNED NOT NULL,
  Number INT UNSIGNED,
  NAME nvarchar(1024),
  PRIMARY KEY (Id),
  FOREIGN KEY (MissionId) REFERENCES fm_missions(Id)
);

DROP TABLE IF EXISTS fm_roles;

CREATE TABLE fm_roles (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DROP TABLE IF EXISTS fm_aircraft;

CREATE TABLE fm_aircraft (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DROP TABLE IF EXISTS fm_flights;

CREATE TABLE fm_flights (
  Id INT UNSIGNED AUTO_INCREMENT,
  PackageId INT UNSIGNED NOT NULL,
  Callsign NVARCHAR(1024) NOT NULL,
  CallsignNum INT UNSIGNED NOT NULL,
  RoleId INT UNSIGNED NOT NULL,
  AircraftId INT UNSIGNED NOT NULL,
  TakeoffTime INT UNSIGNED, -- Falcon Time: minutes from midnight day 1
  PRIMARY KEY (Id),
  FOREIGN KEY (PackageId) REFERENCES fm_packages(Id),
  FOREIGN KEY (RoleId) REFERENCES fm_roles(Id),
  FOREIGN KEY (AircraftId) REFERENCES fm_aircraft(Id)    
);

DROP TABLE IF EXISTS fm_scheduled_participants;

CREATE TABLE fm_scheduled_participants (
  SeatNum INT UNSIGNED NOT NULL,
  FlightId INT UNSIGNED NOT NULL,
  MemberPilot INT UNSIGNED,
  NonmemberPilot NVARCHAR(1024),
  ConfirmedFlown BIT DEFAULT b'0' NOT NULL,
  FOREIGN KEY (FlightId) REFERENCES fm_flights(Id),
  FOREIGN KEY (MemberPilot) REFERENCES phpbb_users(user_id),
  UNIQUE KEY (SeatNum, FlightId)
);

------------------------------------------

INSERT INTO 
