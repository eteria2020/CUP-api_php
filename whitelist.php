<?php
//ini_set('display_errors', 'On');
//error_reporting(E_ALL ^ E_NOTICE);

require('inc/include.php');

 if (isset($_REQUEST['cardcode'])) {
   $timestamp=null;
   $cardcode = $_REQUEST['cardcode'];
   $where = 'WHERE codice_card = :cardcode';
 } else  if (isset($_REQUEST['lastupdate'])) {
    $cardcode = null;
    $lastupdate = $_REQUEST['lastupdate'];
    $where = " WHERE update_id > :lastupdate ";
 }  else {
    $cardcode = null;
    $timestamp=null;
    $where = '';
  }


  $dbh = getDb();

  $tms_format = "YYYY-MM-DD HH24:MI:SS.MS";
 $sql=              "SELECT  id,
                                name as nome ,
                                surname as cognome ,
                                language as lingua,
                                mobile as cellulare ,
                                enabled as abilitato,
                                CASE WHEN maintainer=true THEN 'sharengo' ELSE ''  END as info_display,
                                substring(md5(cast(pin->'primary' as text)) for 8) as pin,
                                 '' as pin2,
                                 card_code as codice_card,
                                 update_id  as tms
                      FROM customers  "
                      . $where .
                    " ORDER BY update_id " .
                    " LIMIT 10000";


  $stm = $dbh->prepare($sql);
  if ($where!="") {
    if (isset($lastupdate))  $stm->bindParam(':lastupdate', $lastupdate, PDO::PARAM_INT);
    if (isset($cardcode))   $stm->bindParam(':cardcode', $cardcode, PDO::PARAM_STR);
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