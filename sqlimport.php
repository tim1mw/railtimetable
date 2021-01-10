<?php
 
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

$sql = array();

$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_dates (
  id int(11) NOT NULL,
  timetableid int(11) NOT NULL,
  date date NOT NULL
);
".$charset_collate;

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_dates
  ADD PRIMARY KEY (id),
  ADD KEY date (date);
";

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_dates
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
";

$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_eventdays (
  id int(11) NOT NULL,
  date date NOT NULL,
  event int(11) NOT NULL
)
".$charset_collate;

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_eventdays
  ADD PRIMARY KEY (id),
  ADD KEY event_index (date);
";

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_eventdays
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;
";

$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_eventdetails (
  id int(11) NOT NULL,
  title varchar(100) NOT NULL,
  description text NOT NULL,
  page int(20) NOT NULL,
  link text NOT NULL,
  buylink text NOT NULL,
  background varchar(6) NOT NULL,
  colour varchar(6) NOT NULL
)
".$charset_collate;

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_eventdetails
  ADD PRIMARY KEY (id);
";

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_eventdetails
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;
";

$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_stations (
  id int(11) NOT NULL,
  name varchar(50) NOT NULL,
  description varchar(255) NOT NULL,
  sequence int(2) NOT NULL,
  requeststop int(11) NOT NULL,
  closed int(11) NOT NULL,
  hidden int(11) NOT NULL
)
".$charset_collate;

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_stations
  ADD PRIMARY KEY (id);
";

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_stations
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;
";

$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_stntimes (
  id int(11) NOT NULL,
  station tinyint(4) NOT NULL,
  timetableid int(11) NOT NULL,
  down_deps text NOT NULL,
  down_arrs text NOT NULL,
  up_deps text NOT NULL,
  up_arrs text NOT NULL
)
".$charset_collate;

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_stntimes
  ADD PRIMARY KEY (id);
";

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_stntimes
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;
";

$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_timetables (
  id int(11) NOT NULL,
  timetable varchar(8) NOT NULL,
  background varchar(6) NOT NULL,
  colour varchar(6) NOT NULL,
  html text NOT NULL,
  totaltrains tinyint(4) NOT NULL,
  colsmeta text NOT NULL,
  buylink varchar(255) NOT NULL,
  hidden int(1) NOT NULL
)
".$charset_collate;

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_timetables
  ADD PRIMARY KEY (id);
";

$sql[] = "
ALTER TABLE {$wpdb->prefix}railtimetable_timetables
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;
";

