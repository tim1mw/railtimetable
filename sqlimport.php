<?php
 
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

$sql = array();

$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_dates (
  id int(11) NOT NULL AUTO_INCREMENT,
  timetableid int(11) NOT NULL,
  date date NOT NULL,
  PRIMARY KEY (id),
  KEY date_index (date)
)
".$charset_collate;

$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_eventdays (
  id int(11) NOT NULL AUTO_INCREMENT,
  date date NOT NULL,
  event int(11) NOT NULL,
  PRIMARY KEY (id),
  KEY event_index (date)
)
".$charset_collate;

$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_eventdetails (
  id int(11) NOT NULL AUTO_INCREMENT,
  title varchar(100) NOT NULL,
  description text NOT NULL,
  page int(20) NOT NULL,
  link text NOT NULL,
  buylink text NOT NULL,
  background varchar(6) NOT NULL,
  colour varchar(6) NOT NULL,
  PRIMARY KEY (id)
)
".$charset_collate;

$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_stations (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(50) NOT NULL,
  description varchar(255) NOT NULL,
  sequence int(2) NOT NULL,
  requeststop int(1) NOT NULL,
  closed int(1) NOT NULL,
  hidden int(1) NOT NULL,
  principal int(1) NOT NULL,

  PRIMARY KEY (id),
  KEY sequence (sequence)
)
".$charset_collate;

$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_stntimes (
  id int(11) NOT NULL AUTO_INCREMENT,
  station tinyint(4) NOT NULL,
  timetableid int(11) NOT NULL,
  down_deps text NOT NULL,
  down_arrs text NOT NULL,
  up_deps text NOT NULL,
  up_arrs text NOT NULL,
  PRIMARY KEY (id),
  KEY timetable (timetableid)
)
".$charset_collate;


$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_timetables (
  id int(11) NOT NULL AUTO_INCREMENT,
  timetable varchar(12) NOT NULL,
  background varchar(6) NOT NULL,
  colour varchar(6) NOT NULL,
  html text NOT NULL,
  totaltrains tinyint(4) NOT NULL,
  colsmeta text NOT NULL,
  buylink varchar(255) NOT NULL,
  hidden int(1) NOT NULL,
  PRIMARY KEY (id),
  KEY timetable (timetable)
)
".$charset_collate;



