<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require('../inc/include.php');

  $dbh = getDb();
  $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

   if (isset($_GET['id'])) {

     $sql = "select st_askml(area_use) from zone where id=:id";

     $id=$_GET['id'];

     $stm = $dbh->prepare($sql);
     $stm->bindParam(':id',$id,PDO::PARAM_INT);
     $result = $stm->execute();

     $row = $stm->fetchColumn();

     header("Content-Disposition: attachment; filename=\"area_$id.kml\"");
     header("Content-Type: application/vnd.google-earth.kml");

     echo '<?xml version="1.0" encoding="UTF-8"?>';
     echo '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">';
     echo '<Document>';
     echo "  <name>area_$id.kml</name>";
     echo '<Placemark><name>area</name>';
     echo($row);
     echo '</Placemark></Document></kml>';

   } else if (isset($_GET['all'])){
     $sql = "select id,nome,ST_NPoints(area) as npunti, st_askml(area) as kml from zone";

     $id=$_GET['id'];

     $stm = $dbh->prepare($sql);

     $result = $stm->execute();

     header("Content-Disposition: attachment; filename=\"area_all.kml\"");
     header("Content-Type: application/vnd.google-earth.kml");

     echo '<?xml version="1.0" encoding="UTF-8"?>';
     echo '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">';
     echo '<Document>';
     echo "  <name>area_all.kml</name>";

     echo '<Style id="mystyle"><PolyStyle><color>99ffffff</color><colorMode>random</colorMode></PolyStyle></Style>';

     while ($row = $stm->fetch(PDO::FETCH_OBJ)) {
     echo "<Placemark><name>$row->id - $row->nome ($row->npunti)</name><styleUrl>#mystyle</styleUrl>";
     echo($row->kml);
     echo '</Placemark>';
     }
     echo '</Document></kml>';

   } else {

       $sql = "select id,name,ST_NPoints(area_use) as npunti  from zone";

       $stm = $dbh->prepare($sql);
       $result = $stm->execute();
       echo '<TABLE border=1 width=512px>';
       echo '<TR><TH>ID</TH><TH>NOME AREA</TH><TH>NUM. VERTICI</TH><TH>KML</TH></TR>';
       while ($row = $stm->fetch(PDO::FETCH_OBJ)) {
         $link = "<A HREF='kml.php?id=$row->id'>kml</A>";
         echo "<TR><TD align='center'>$row->id</TD><TD>$row->nome</TD><TD align='right'>$row->npunti</TD><TD align='center'>$link</TD></TR>";
       }
       echo '<TABLE><BR>';
       echo "<A HREF='kml.php?all=1'>Scarica tutti</A>";



   }
