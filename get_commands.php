<?php
//ini_set('display_errors', 'On');
//error_reporting(E_ALL ^ E_NOTICE);


require('inc/include.php');

$car_plate=$_REQUEST['car_plate'];


 if (isset($car_plate)) {

  $dbh = getDb();

  $stm = $dbh->prepare("SELECT  id, command , intarg1, intarg2, txtarg1, txtarg2, extract(epoch from queued) as queued, ttl, payload FROM commands WHERE to_send = TRUE AND car_plate = :car_plate");

  $stm->bindParam(':car_plate', $car_plate, PDO::PARAM_STR);

  $stm->execute();

  $result=$stm->fetchAll(PDO::FETCH_ASSOC);

 if ($result && count($result)>0)  {
         $json=json_encode($result);
         $id = $result[0]['id'];
 }  else {
         $json="";
         $id = -1;
 }

  echo $json;


  if (count($result)>0) {
      $stmw = $dbh->prepare("UPDATE commands  SET to_send = FALSE , received = now() WHERE to_send = TRUE AND car_plate = :car_plate");
      $stmw->bindParam(':car_plate', $car_plate, PDO::PARAM_STR);
      $stmw->execute();
  }


  $dbh=null;

  }
?>