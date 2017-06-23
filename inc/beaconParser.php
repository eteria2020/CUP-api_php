<?php
function beaconParser($dbh, $json) {

{"VIN":"ED06247","km":0,"SOC":63,"lon":0,"lat":0,"on_trip":1,"ChargeCommStatus":false,"id_trip":2814,"cputemp":63,"uptime":0,"IMEI":"861311004330717","Speed":0,"gspeed":0,"KeyStatus":"ON","gps_info":null,"detail":null,"keyOn":true,"GearStatus":"N","ReverseStatus":"OUT","PackV":80,"Km":0,"BrakesOn":false,"MotorTemp":28,"BmsFault":true,"MotoroDcVoltage":80,"ArmPowerDown":true,"LcdOn":false,"MotorTempHigh":false,"vcuFault":true,"KMStatus":false,"AccStatus":true,"ReadyOn":true,"WakeupSource":"ACC","PackA":3487,"PreChargeFault":true,"PPStatus":false,"ChargeHeatFault":true,"PackStatus":"SHELVE","MotorFault":true,"ChargeFault":true,"CHARGER_STATUS":0,"KSStatus":false,"VER":"V1.1","MotorWorkStatus":"FW","closeEnabled":false,"parkEnabled":false,"json":"{\"keyOn\":true,\"GearStatus\":\"N\",\"KeyStatus\":\"ON\",\"ReverseStatus\":\"OUT\",\"VIN\":\"ED06247\",\"PackV\":80,\"Km\":0,\"BrakesOn\":false,\"MotorTemp\":28,\"BmsFault\":true,\"MotoroDcVoltage\":80.0,\"ArmPowerDown\":true,\"LcdOn\":false,\"MotorTempHigh\":false,\"vcuFault\":true,\"KMStatus\":false,\"ReadyOn\":true,\"AccStatus\":true,\"WakeupSource\":\"ACC\",\"PackA\":3487,\"SOC\":63,\"PreChargeFault\":true,\"ChargeCommStatus\":false,\"PPStatus\":false,\"ChargeHeatFault\":true,\"Speed\":0.0,\"PackStatus\":\"SHELVE\",\"MotorFault\":true,\"ChargeFault\":true,\"CHARGER_STATUS\":0,\"KSStatus\":false,\"VER\":\"V1.1\",\"MotorWorkStatus\":\"FW\",\"lon\":0.0,\"lat\":0.0,\"cputemp\":63,\"on_trip\":1,\"closeEnabled\":false,\"parkEnabled\":false,\"id_trip\":2814}","fwVer":"V1.1","swVer":"0.24-beta","clock":"22/06/2015 17:41:22","SIM_SN":null,"wlsize":3644,"offLineTrips":0,"openTrips":0,"MotV":0,"MotFault":true,"MotT":0,"MotTempHigh":false}


		$data = json_decode($json, true);

		$id = isset ($data['VIN']) ? $data['VIN'] : '';
		$lon = isset ($data['lon']) ? $data['lon'] : 0;
		$lat = isset ($data['lat']) ? $data['lat'] : 0;
		$volt = isset ($data['avolt']) ? $data['avolt'] : 0;
		$mvolt = isset ($data['mvolt']) ? $data['mvolt'] : 0;
		$fw = isset ($data['ver']) ? $data['ver'] : NULL;
		$sw = isset ($data['swver']) ? $data['swver'] : NULL;
		$mac = isset ($data['MAC']) ? $data['MAC'] : NULL;
		$imei = isset ($data['IMEI']) ? $data['IMEI'] : NULL;
		$speed = isset ($data['speed']) ? $data['speed'] : 0;
		$gspeed = isset ($data['gspeed']) ? $data['gspeed'] : 0;
		$rpm = isset ($data['rpm']) ? $data['rpm'] : 0;
		$quadro = isset ($data['Q']) ? $data['Q'] : 0;
		$fuel = isset ($data['fuelLevel']) ? $data['fuelLevel'] : 0;
		$km = isset ($data['km']) ? $data['km'] : 0;
		$ontrip = isset ($data['ontrip']) ? $data['ontrip'] : 0;
		$id_corsa = isset ($data['id_corsa']) ? $data['id_corsa'] : null;
		$tosend = isset ($data['tosend']) ? $data['tosend'] : 0;
		$opened = isset ($data['open']) ? $data['open'] : 0;
		$wlsize = isset ($data['wlsize']) ? $data['wlsize'] : 0;
		$temp = isset ($data['temp']) ? $data['temp'] / 10 : 0;
		$cputemp = isset ($data['cputemp']) ? $data['cputemp'] : 0;
		$batt = isset ($data['batt']) ? $data['batt'] : 0;
		$sim_sn = isset ($data['SIM_SN']) ? $data['SIM_SN'] : null;
        $i2c  = isset ($data['i2c']) ? $data['i2c'] : null;
        $uptime = isset ($data['uptime']) ? $data['uptime'] : 0;
        $chiudibile = isset ($data['chiudibile']) ? ($data['chiudibile']?1:0) : 1;
        $parkenabled = isset($data['parkenabled']) ? ($data['parkenabled']?1:0) : 1;
		$gspeed = round($gspeed);
        $gps_info = isset($data['gps_info'])?$data['gps_info']:NULL;

		$targa = $id;

/*        if (strpos($id, 'DEMO') === 0) {
          $fuel=30;
          $volt=999;
        }*/


		try {
//Aggiorna tabella vetture
                $base = "vettura_ultimo_contatto = now() , vettura_tensione_batteria=vettura_offset_batteria + :avolt, vettura_rpm = :rpm, vettura_velocita = :velocita, vettura_quadro_acceso = :quadro, vettura_livello_carburante= vettura_offset_carburante+:fuel, vettura_km=:km, vettura_in_corsa=:ontrip, vettura_corsachiudibile=:chiudibile,vettura_sostaabilitata=:parkenabled";
                $baseValues = array(':id' => $id,':avolt' => $volt, ':velocita' => $speed, ':rpm' => $rpm, ':quadro' => $quadro, ':fuel' => $fuel, ':km' => $km, ':ontrip' => $ontrip, ':chiudibile' => $chiudibile,':parkenabled'=>$parkenabled);

                if ($imei) { //Long version
                  $extra = ",vettura_versione_fw = :fw, vettura_versione_sw = :sw, vettura_tablet_mac = :mac , vettura_tablet_imei = :imei, vettura_corse_aperte=:opened, vettura_corse_da_inviare=:tosend, vettura_wlsize=:wlsize, vettura_sim_sn=:sim_sn, gps_info=:gps_info";
                  $extraValues = array(':fw' => $fw, ':sw' => $sw, ':mac' => $mac, ':imei' => $imei,':opened' => $opened, ':tosend' => $tosend, ':wlsize' => $wlsize, ':sim_sn' => $sim_sn, ':gps_info' => $gps_info);
                } else {
                  $extra='';
                  $extraValues=array();
                }

				if ($lon != 0 && $lat != 0) {
                   $location = ",vettura_invio_locazione = now() , vettura_lon = cast(:lon as numeric) , vettura_lat =cast(:lat as numeric) , vettura_locazione = ST_SetSRID(ST_MakePoint(:lon,:lat),4326)";
                   $locationValues = array(':lon' => $lon, ':lat' => $lat);
				} else {
                  $location='';
                  $locationValues=array();
                }

                $sql = "UPDATE tbl_vettura SET " . $base . $extra . $location . "  WHERE vettura_targa =:id" ;
				$stm = $dbh->prepare($sql);

                $args = array_merge($baseValues,$extraValues,$locationValues);

				$dbh->beginTransaction();
                $result = $stm->execute($args);

				if ($result) {
						$dbh->commit();
				} else {
						$dbh->rollBack();
						echo "Errore:";
						var_dump($dbh->errorInfo());
//loggare
				}
// Aggiungi ai log
				$sql = "INSERT INTO log (ora,targa,km,fuel,avolt,rpm,speed,gspeed,key_on,on_trip,mvolt,id_corsa,temperature,cputemp,tablet_battery,lat,lon,geo,sim_sn,i2c,uptime,imei,gps_info) VALUES" . "(now(), :id, :km, :fuel, :avolt, :rpm, :speed , :gspeed, :quadro, :ontrip, :mvolt, :id_corsa, :temp, :cputemp, :batt, :lat, :lon, ST_SetSRID(ST_MakePoint(:lon,:lat),4326), :sim_sn, :i2c , :uptime, :imei, :gps_info)";
				$stm2 = $dbh->prepare($sql);
				$result = $stm2->execute(array(':id' => $id, ':lon' => $lon, ':lat' => $lat, ':avolt' => $volt, ':mvolt' => $mvolt, ':speed' => $speed, ':gspeed' => $gspeed,
                                               ':rpm' => $rpm, ':quadro' => $quadro, ':fuel' => $fuel, ':km' => $km, ':ontrip' => $ontrip, ':id_corsa' => $id_corsa, ':temp' => $temp,
                                               ':cputemp' => $cputemp, ':batt' => $batt, ':sim_sn' => $sim_sn,':i2c' => $i2c , ':uptime' => $uptime, ':imei' => $imei,':gps_info' => $gps_info));
				if (!$result) {
						echo var_dump($dbh->errorInfo());
				}
//Controlla se rifornimenti da confermare
				$sql = "SELECT id,fuel1 FROM rifornimenti WHERE targa=:targa AND is_confermato=FALSE AND data + interval '1 hour' > now() ORDER BY data DESC";
				$stm3 = $dbh->prepare($sql);
				$stm3->execute(array(':targa' => $id));
				$result = $stm3->fetchAll(PDO :: FETCH_ASSOC);
				if ($result && count($result) > 0) {
						$row = $result[0];
						if ($fuel > $row['fuel1']) {
								$sql = "UPDATE rifornimenti SET fuel2=:fuel, is_confermato=TRUE, data_conferma=now() WHERE targa=:targa";
								$stm4 = $dbh->prepare($sql);
								$stm4->execute(array(':fuel' => $fuel, ':targa' => $id));
						}
				}
		}
		catch (PDOException $e) {
				echo $e->getMessage();
//loggare
		}

        return $targa;
}

?>


