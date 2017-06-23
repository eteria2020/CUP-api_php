<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require('../inc/include.php');

  $dbh = getDb();
  $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);


   //select id, nome, ST_AsText((ST_DumpPoints(area)).geom) from zone;

   if (isset($_GET['md5']))
     $md5 =  $_GET['md5'];
   else
     $md5=null;

   if (isset($_GET['targa']))
     $targa =  $_GET['targa'];
   else
     $targa=null;


    $filter = 'AND zone.active=true';


   $flotta =1;

   $sql= "SELECT close_trip,0 as costo_apertura,0 as costo_chiusura,name,ST_NPoints(area_use) as npunti, St_asText(area_use) as dump FROM  zone_groups  INNER JOIN zone ON zone.id = ANY(zone_groups.id_zone) WHERE fleet_id=:flotta " . $filter;

   // $sql = "SELECT is_chiusura_permessa,costo_apertura,costo_chiusura,nome,ST_NPoints(area) as npunti, St_asText(area) as dump  FROM gruppi_zone INNER JOIN zone ON zone.id=gruppi_zone.id_zona WHERE id_flotta=:flotta";

   $stm = $dbh->prepare($sql);

   $result = $stm->execute(array(':flotta' => $flotta));
   $jarray = array();

   while ($row = $stm->fetch(PDO::FETCH_OBJ)) {
     $jp = new stdClass();

     $jp->close_trip=$row->is_chiusura_permessa;
     $jp->costo_apertura=$row->costo_apertura;
     $jp->costo_chiusura=$row->costo_chiusura;
     $jp->coordinates = array();
     $str = substr($row->dump,9,strlen($row->dump)-11);
     $points = explode(',',$str);
     foreach($points as $point) {
       $c = explode(' ',$point);
       if ($c[0]!=0 && $c[1]!=0) {
         $jp->coordinates[] =(double)$c[0];
         $jp->coordinates[] =(double)$c[1];
         $jp->coordinates[] =0;
       }

     }
     $jarray[] = $jp;

   }

   $json = json_encode($jarray);

   $json_md5 = md5($json);

   if (!isset($md5) || $md5==null || $md5!=$json_md5) {
     echo $json;
   }

?>
