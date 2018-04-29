DROP TABLE IF EXISTS vfw440_missiontypes;

CREATE TABlE vfw440_missiontypes (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DESCRIBE vfw440_missiontypes;

DROP TABLE IF EXISTS vfw440_theaters;

CREATE TABLE vfw440_theaters (
  Id INT UNSIGNED AUTO_INCREMENT,
  TheaterName NVARCHAR(1024) NOT NULL,
  TheaterVersion NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DESCRIBE vfw440_theaters;

DROP TABLE IF EXISTS vfw440_missions;

CREATE TABLE vfw440_missions (
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
  FOREIGN KEY (Type) REFERENCES vfw440_missiontypes(Id),
  FOREIGN KEY (Theater) REFERENCES vfw440_theaters(Id)
);

DROP TABLE IF EXISTS vfw440_packages;

CREATE TABLE vfw440_packages (
  Id INT UNSIGNED AUTO_INCREMENT,
  MissionId INT UNSIGNED NOT NULL,
  Number INT UNSIGNED,
  NAME nvarchar(1024),
  PRIMARY KEY (Id),
  FOREIGN KEY (MissionId) REFERENCES vfw440_missions(Id)
);

DROP TABLE IF EXISTS vfw440_roles;

CREATE TABLE vfw440_roles (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DROP TABLE IF EXISTS vfw440_aircraft;

CREATE TABLE vfw440_aircraft (
  Id INT UNSIGNED AUTO_INCREMENT,
  Name NVARCHAR(1024) NOT NULL,
  Active BIT DEFAULT b'1' NOT NULL,
  PRIMARY KEY (Id)
);

DROP TABLE IF EXISTS vfw440_flights;

CREATE TABLE vfw440_flights (
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
);

DROP TABLE IF EXISTS vfw440_scheduled_participants;

CREATE TABLE vfw440_scheduled_participants (
  SeatNum INT UNSIGNED NOT NULL,
  FlightId INT UNSIGNED NOT NULL,
  MemberPilot INT UNSIGNED,
  NonmemberPilot NVARCHAR(1024),
  ConfirmedFlown BIT DEFAULT b'0' NOT NULL,
  FOREIGN KEY (FlightId) REFERENCES vfw440_flights(Id),
  FOREIGN KEY (MemberPilot) REFERENCES phpbb_users(user_id),
  UNIQUE KEY (SeatNum, FlightId)
);
