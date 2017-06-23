<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL ^ E_NOTICE);

require('inc/include.php');


$out = array();
$out['result']=0;
$out['message']='';

  $event_time =  intval($_REQUEST['event_time']);
  $obj = new stdClass();
  $obj->event_time = validateDateTime($_REQUEST['event_time'],false);
  $obj->event_id = (int)intval($_REQUEST['event_id']);
  $obj->customer_id = intval($_REQUEST['customer_id']?:0);
  $obj->car_plate = $_REQUEST['car_plate'];
  $obj->trip_id = $_REQUEST['trip_id']?:0;
  $obj->label = $_REQUEST['label'];
  $obj->intval = intval($_REQUEST['intval']);
  $obj->txtval  = $_REQUEST['txtval'];
  $obj->level = $_REQUEST['level']?:0;
  $obj->lat = (float)floatval($_REQUEST['lat']);
  $obj->lon = floatval($_REQUEST['lon']);
  $obj->km  = intval($_REQUEST['km']?:0);
  $obj->battery = intval($_REQUEST['battery']);
  $obj->imei = $_REQUEST['imei']?:NULL;
  $obj->json_data = $_REQUEST['json_data']?:NULL;

  $id = 1;
  $id2 = (int)intval($_REQUEST['event_id']);

try{
   $mdb = getMongodb();

         if ($mdb) {
           $obj->event_time = new MongoDate($event_time);
           $obj->server_time  =new MongoDate();
           $obj->geo = new stdClass();
           $obj->geo->type='Point';
           $obj->geo->coordinates= array(floatval($obj->lon), floatval($obj->lat));
           $events = $mdb->sharengo->events;
           //$events->createIndex(array('geo' => '2dsphere'));
           if ($events->insert($obj))
               printOutput($out,1,'OK');
           else
               printOutput($out,-11,'MongoDB error');
         } else {
           printOutput($out,-10,"General error");
         }

}catch(Exception $e){
}




  $dbh = getDb();
  $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);


       $obj->event_time = validateDateTime($_REQUEST['event_time'],false);
       $sql = "UPDATE  cars  SET last_contact = now() WHERE plate = ?";
       $queryUpdate = $dbh->prepare($sql);
       $queryUpdate->execute(array($obj->car_plate));


       /******* EXCLUDE Postgres write ***********

         $sql = "INSERT INTO events (event_time,server_time,car_plate, event_id, label, level, customer_id, trip_id, intval, txtval, lon,lat, geo ,km,battery,imei,data) " .
                "VALUES (:event_time,now(),:car_plate,:event_id,:label,:level, :customer_id, :trip_id, :intval, :txtval, cast(:lon as numeric),cast(:lat as numeric), ST_SetSRID(ST_MakePoint(:lon,:lat),4326), :km, :battery, :imei, :data) RETURNING id";


         $stm = $dbh->prepare($sql);

         $stm->bindParam(':event_time',$obj->event_time,PDO::PARAM_STR);
         $stm->bindParam(':event_id',$obj->event_id,PDO::PARAM_INT);
         $stm->bindParam(':car_plate',$obj->car_plate,PDO::PARAM_STR);
         $stm->bindParam(':customer_id',$obj->customer_id,PDO::PARAM_INT);
         $stm->bindParam(':trip_id',$obj->trip_id,PDO::PARAM_STR);
         $stm->bindParam(':label',$obj->label,PDO::PARAM_STR);
         $stm->bindParam(':txtval',$obj->txtval,PDO::PARAM_STR);
         $stm->bindParam(':intval',$obj->intval,PDO::PARAM_INT);
         $stm->bindParam(':level',$obj->level,PDO::PARAM_INT);
         $stm->bindParam(':lon',$obj->lon,PDO::PARAM_STR);
         $stm->bindParam(':lat',$obj->lat,PDO::PARAM_STR);
         $stm->bindParam(':km',$obj->km,PDO::PARAM_INT);
         $stm->bindParam(':battery',$obj->battery,PDO::PARAM_INT);
         $stm->bindParam(':imei',$obj->imei,PDO::PARAM_STR);
         $stm->bindParam(':data',$obj->json_data,PDO::PARAM_STR);

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

  */


         if ($obj->label=='CHARGE' && $obj->intval==1) {
           $sql = "UPDATE cars SET charging = TRUE , plug = TRUE WHERE plate = :plate";
           $stm1 = $dbh->prepare($sql);
           $stm1->bindParam(':plate',$obj->car_plate,PDO::PARAM_STR);
           $stm1->execute();
         }
         else if ($obj->label=='CHARGE' && $obj->intval==0) {
           $sql = "UPDATE cars SET plug = FALSE WHERE plate = :plate";
           $stm1 = $dbh->prepare($sql);
           $stm1->bindParam(':plate',$obj->car_plate,PDO::PARAM_STR);
           $stm1->execute();
         } else  if ($obj->label=='SW_BOOT') {
            $stm1 = $dbh->prepare("INSERT INTO commands (car_plate,command,txtarg1, queued,to_send) VALUES (:plate,'SET_DAMAGES', (select damages FROM cars WHERE plate=:plate) , now(),TRUE);");
            $stm1->bindParam(':plate', $obj->car_plate, PDO::PARAM_STR);
            $stm1->execute();
         } else if   ($obj->label=='SELFCLOSE' && $obj->txtval=="NOPAY" ) {
           $trip_id = $obj->trip_id;
           if ($trip_id!=0) {
             $stm1 = $dbh->prepare("UPDATE trips SET payable = FALSE WHERE id = :trip_id");
             $stm1->bindParam(':trip_id', $obj->trip_id, PDO::PARAM_INT);
             $stm1->execute();
           }
         } else if ($obj->label=='CLEANLINESS' ) {
             $enumMap = array('0'=>'clean', '1'=>'average', '2'=>'dirty');
             $values = explode(';',$obj->txtval);
             if ($obj->txtval && count($values)==2 && array_key_exists($values[0],$enumMap) && array_key_exists($values[1],$enumMap) ) {
                 $int = $enumMap[$values[0]];
                 $ext = $enumMap[$values[1]];
                 $sql = "UPDATE cars SET int_cleanliness = :int , ext_cleanliness = :ext WHERE plate=:plate";
                 $stm1 = $dbh->prepare($sql);
                 $stm1->bindParam(':int', $int, PDO::PARAM_STR);
                 $stm1->bindParam(':ext', $ext, PDO::PARAM_STR);
                 $stm1->bindParam(':plate', $obj->car_plate, PDO::PARAM_STR);
                 $stm1->execute();
             }
         } else if ($obj->label=='SOS') {
             $sql="INSERT INTO messages_outbox  (destination,type,subject,submitted,meta) VALUES ('support','SOS','SOS call',now(),:meta)";
             $meta = json_encode($obj);
             $stm1 = $dbh->prepare($sql);
             $stm1->bindParam(':meta',$meta, PDO::PARAM_STR);
             $stm1->execute();
         } else if ($obj->label=='SHUTDOWN' && $obj->txtval=='Shutting Down') {
            $sql = "INSERT INTO events (event_time,server_time,car_plate, event_id, label, customer_id, trip_id, intval, txtval, lon,lat, geo ,km,battery,imei,data) " .
                "VALUES (:event_time,now(),:car_plate,:event_id,:label,:customer_id, :trip_id, :intval, :txtval, cast(:lon as numeric),cast(:lat as numeric), ST_SetSRID(ST_MakePoint(:lon,:lat),4326), :km, :battery, :imei, :data) RETURNING id";

			 $stm1 = $dbh->prepare($sql);

			$stm1->bindParam(':event_time',$obj->event_time,PDO::PARAM_STR);
			$stm1->bindParam(':event_id',$obj->event_id,PDO::PARAM_INT);
			$stm1->bindParam(':car_plate',$obj->car_plate,PDO::PARAM_STR);
			$stm1->bindParam(':customer_id',$obj->customer_id,PDO::PARAM_INT);
			$stm1->bindParam(':trip_id',$obj->trip_id,PDO::PARAM_STR);
			$stm1->bindParam(':label',$obj->label,PDO::PARAM_STR);
			$stm1->bindParam(':txtval',$obj->txtval,PDO::PARAM_STR);
			$stm1->bindParam(':intval',$obj->intval,PDO::PARAM_INT);
			$stm1->bindParam(':lon',$obj->lon,PDO::PARAM_STR);
			$stm1->bindParam(':lat',$obj->lat,PDO::PARAM_STR);
			$stm1->bindParam(':km',$obj->km,PDO::PARAM_INT);
			$stm1->bindParam(':battery',$obj->battery,PDO::PARAM_INT);
			$stm1->bindParam(':imei',$obj->imei,PDO::PARAM_STR);
			$stm1->bindParam(':data',$obj->json_data,PDO::PARAM_STR);
            $stm1->execute();
         } else if ($obj->label=='CAN_ANOMALIES' ){
            $sql = "INSERT INTO events (event_time,server_time,car_plate, event_id, label, customer_id, trip_id, intval, txtval, lon,lat, geo ,km,battery,imei,data) " .
                "VALUES (:event_time,now(),:car_plate,:event_id,:label,:customer_id, :trip_id, :intval, :txtval, cast(:lon as numeric),cast(:lat as numeric), ST_SetSRID(ST_MakePoint(:lon,:lat),4326), :km, :battery, :imei, :data) RETURNING id";

			 $stm1 = $dbh->prepare($sql);

			$stm1->bindParam(':event_time',$obj->event_time,PDO::PARAM_STR);
			$stm1->bindParam(':event_id',$obj->event_id,PDO::PARAM_INT);
			$stm1->bindParam(':car_plate',$obj->car_plate,PDO::PARAM_STR);
			$stm1->bindParam(':customer_id',$obj->customer_id,PDO::PARAM_INT);
			$stm1->bindParam(':trip_id',$obj->trip_id,PDO::PARAM_STR);
			$stm1->bindParam(':label',$obj->label,PDO::PARAM_STR);
			$stm1->bindParam(':txtval',$obj->txtval,PDO::PARAM_STR);
			$stm1->bindParam(':intval',$obj->intval,PDO::PARAM_INT);
			$stm1->bindParam(':lon',$obj->lon,PDO::PARAM_STR);
			$stm1->bindParam(':lat',$obj->lat,PDO::PARAM_STR);
			$stm1->bindParam(':km',$obj->km,PDO::PARAM_INT);
			$stm1->bindParam(':battery',$obj->battery,PDO::PARAM_INT);
			$stm1->bindParam(':imei',$obj->imei,PDO::PARAM_STR);
			$stm1->bindParam(':data',$obj->json_data,PDO::PARAM_STR);
            $stm1->execute();
         }

         /*  else  if ($etichetta=='CLEANLINESS') {
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


       } catch (PDOException $e) {
         printOutput($out,-11,$e->getMessage());
       }

         */

  $dbh=null;


?>
