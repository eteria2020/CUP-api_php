<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL ^ E_NOTICE);

require('inc/include.php');


$out = array();
$out['result']=0;
$out['message']='';


  $event_time = $_REQUEST['event_time'];
  $event_time_str = validateDateTime($event_time,false);
  $event_id = $_REQUEST['event_id'];
  $customer_id = $_REQUEST['customer_id']?:0;
  $car_plate = $_REQUEST['car_plate'];
  $trip_id = $_REQUEST['trip_id']?:0;
  $label = $_REQUEST['label'];
  $intval = $_REQUEST['intval'];
  $txtval  = $_REQUEST['txtval'];
  $level = $_REQUEST['level']?:0;
  $lat = $_REQUEST['lat'];
  $lon = $_REQUEST['lon'];
  $km  = $_REQUEST['km']?:0;
  $battery = $_REQUEST['battery'];
  $mac = $_REQUEST['mac'];
  $imei = $_REQUEST['imei'];
  $json_data = $_REQUEST['json_data'];

  $dbh = getDb();
  $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

       try {

       $sql = "UPDATE  cars  SET last_contact = now() WHERE plate = ?";
       $queryUpdate = $dbh->prepare($sql);
       $queryUpdate->execute(array($car_plate));



         $sql = "SELECT count(*) FROM  WHERE timestamp = ? AND id_veicolo=? AND id_evento=?";
         $stm1 = $dbh->prepare($sql);
         $res = $stm1->execute(array($timestampstr,$id_veicolo,$id_evento));

         if ($res && $stm1->fetchColumn()>0) {
             printOutput($out,1,'Already sent');
             exit();
         }


         $sql = "INSERT INTO events (event_time,server_time,car_plate, event_id, label, level, customer_id, trip_id, intval, txtval, lon,lat, geo ,km,battery,mac,imei,data) " .
                "VALUES (:event_time,now(),:car_plate,:event_id,:label,:level, :customer_id, :trip_id, :intval, :txtval, cast(:lon as numeric),cast(:lat as numeric), ST_SetSRID(ST_MakePoint(:lon,:lat),4326), :km, :battery, :mac, :imei, :data) RETURNING id";
         $stm = $dbh->prepare($sql);

         $stm->bindParam(':event_time',$timestampstr,PDO::PARAM_STR);
         $stm->bindParam(':event_id',$id_evento,PDO::PARAM_INT);
         $stm->bindParam(':car_plate',$id_veicolo,PDO::PARAM_STR);
         $stm->bindParam(':customer_id',$id_cliente,PDO::PARAM_INT);
         $stm->bindParam(':trip_id',$id_corsa,PDO::PARAM_STR);
         $stm->bindParam(':label',$etichetta,PDO::PARAM_STR);
         $stm->bindParam(':txtval',$txtval,PDO::PARAM_STR);
         $stm->bindParam(':intval',$intval,PDO::PARAM_INT);
         $stm->bindParam(':level',$gravita,PDO::PARAM_INT);
         $stm->bindParam(':lon',$lon,PDO::PARAM_STR);
         $stm->bindParam(':lat',$lat,PDO::PARAM_STR);
         $stm->bindParam(':km',$km,PDO::PARAM_INT);
         $stm->bindParam(':battery',$fuel,PDO::PARAM_INT);
         $stm->bindParam(':mac',$mac,PDO::PARAM_STR);
         $stm->bindParam(':imei',$imei,PDO::PARAM_STR);

         echo $sql;


         $dbh->beginTransaction();
         $result = $stm->execute();

         $row = $stm->fetchColumn();

         if ($result) {
           $dbh->commit();
           printOutput($out,$row,'OK');
         } else {
           $dbh->rollBack();
           printOutput($out,-10,$dbh->errorInfo());
         }

         /*
         if ($etichetta=='SW_BOOT') {
            $stm1 = $dbh->prepare("INSERT INTO comandi (targa,comando,txtarg1, emesso,da_inviare) VALUES (:targa,'SET_DAMAGES', (select vettura_danni FROM tbl_vettura WHERE vettura_targa=:targa) , now(),TRUE);");
            $stm1->bindParam(':targa', $id_veicolo, PDO::PARAM_STR);
            $stm1->execute();

            $stm1 = $dbh->prepare("INSERT INTO comandi (targa,comando,txtarg1, emesso,da_inviare) VALUES (:targa,'SET_FUELCARD_PIN', (SELECT  carburante_pin FROM tbl_card_carburante WHERE carburante_targa = :targa AND carburante_attivo = TRUE) , now(),TRUE);");
            $stm1->bindParam(':targa', $id_veicolo, PDO::PARAM_STR);
            $stm1->execute();

//            echo var_dump($dbh->errorInfo());
         } else  if ($etichetta=='CLEANLINESS') {
            $sql = "UPDATE tbl_vettura SET vettura_pulizia_int = :pulizia_int , vettura_pulizia_ext = :pulizia_ext  WHERE vettura_targa =:id";

            $pulizia = explode(';',$txtval);
            $stm1 = $dbh->prepare($sql);
            $stm1->bindParam(':pulizia_int', $pulizia[0], PDO::PARAM_INT);
            $stm1->bindParam(':pulizia_ext', $pulizia[1], PDO::PARAM_INT);
            $stm1->bindParam(':id', $id_veicolo, PDO::PARAM_STR);
            $stm1->execute();
//            echo var_dump($dbh->errorInfo());
         } else   if ($etichetta=='PARK') {
            $sql = "UPDATE tbl_vettura SET vettura_in_sosta = :sosta  WHERE vettura_targa =:id";

            $stm1 = $dbh->prepare($sql);
            $sosta = ($intval==1?1:0);
            $stm1->bindParam(':sosta', $sosta, PDO::PARAM_INT);
            $stm1->bindParam(':id', $id_veicolo, PDO::PARAM_STR);
            $stm1->execute();
//            echo var_dump($dbh->errorInfo());
         }
         */

       } catch (PDOException $e) {
         printOutput($out,-11,$e->getMessage());
       }




  $dbh=null;

?>