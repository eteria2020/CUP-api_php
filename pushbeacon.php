<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL ^ E_NOTICE);

require('inc/include.php');
require('inc/beaconParser.php');

if (isset($_REQUEST['car_plate'])) {
   $plate = $_REQUEST['car_plate'];
} else {
  echo "Missing plate ";
  exit();
}

if (isset($_REQUEST['beaconText'])) {
    $beaconText = $_REQUEST['beaconText'];
} else {
  echo "Missing payload";
  exit();
}


 $dbh = getDb();
 $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

echo  beaconParser($dbh,$beaconText);

 $dbh = null;

?>


