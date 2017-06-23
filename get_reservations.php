<?php
//ini_set('display_errors', 'On');
//error_reporting(E_ALL ^ E_NOTICE);


require('inc/include.php');

$plate=$_REQUEST['car_plate'];
$consumed=$_REQUEST['consumed'];

 if (isset($plate) && !isset($consumed)) {



  $dbh = getDb();

  $stm = $dbh->prepare("SELECT  id, cards , extract(epoch from beginning_ts) as time,  length  , active  FROM reservations WHERE  car_plate = :plate");

  $stm->bindParam(':plate', $plate, PDO::PARAM_STR);

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


  if ($id>0) {
      $stmw = $dbh->prepare("UPDATE reservations SET to_send = FALSE , sent_ts = now() WHERE id= :id AND to_send = TRUE");
      $stmw->bindParam(':id',$id);
      $stmw->execute();
  }


/*  $stm = $dbh->prepare ("SELECT now();");
  $stm->execute();
  $data['timestamp'] = $stm->fetchColumn();
  echo json_encode($data);*/

  $dbh=null;

  } else if (isset($consumed)) {

      $dbh = getDb();

      $stm = $dbh->prepare("UPDATE reservations SET consumed_ts = now() , active = false WHERE id = :id");

      $stm->bindParam(':id', $consumed, PDO::PARAM_INT);
      $stm->execute();

  $result=$stm->fetchAll(PDO::FETCH_ASSOC);
  }
?>