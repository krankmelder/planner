-- Table structure for table `planner`
CREATE TABLE planner (
  id integer NOT NULL PRIMARY KEY,
  user_id integer NOT NULL default '0',
  starred tinyint(1) NOT NULL default '0',
  datetime datetime default NULL,
  text text NOT NULL,
  done tinyint(1) NOT NULL default '0'
  CONSTRAINT user_id_fk_planner FOREIGN KEY (user_id)
  REFERENCES users(user_id)
);
