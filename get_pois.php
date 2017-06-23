<?php
//ini_set('display_errors', 'On');
//error_reporting(E_ALL ^ E_NOTICE);


require('inc/include.php');


 if (isset($_REQUEST['update'])) {
    $update = $_REQUEST['update'];
    $where = " WHERE update > :aupdate AND lon is not null AND lat is not null ";
 }  else {
    $update=null;
    $where = 'WHERE lon is not null AND lat is not null';
  }



  $dbh = getDb();

  $tms_format = "YYYY-MM-DD HH24:MI:SS.MS";
  $sql = "SELECT  id, type, code, name, brand,address,town,zip_code,province, lon, lat, update  FROM  pois " . $where . " ORDER BY update";



  $stm = $dbh->prepare($sql);
  if ($where!="") {
    if (isset($update))  $stm->bindParam(':update', $update, PDO::PARAM_INT);
  }
  $stm->execute();

  $result=$stm->fetchAll(PDO::FETCH_ASSOC);
  $json=json_encode($result);
  echo $json;

/*  $stm = $dbh->prepare ("SELECT now();");
  $stm->execute();
  $data['timestamp'] = $stm->fetchColumn();
  echo json_encode($data);*/

  $dbh=null;

?>