<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL ^ E_NOTICE);

require('inc/include.php');

define('CMD_INFO', 0);
define('CMD_APERTURA', 1);
define('CMD_CHIUSURA', 2);
define('CMD_OFFLINE', 3);

define('N_COMPANY_PIN', 100);

function getAddressFromCoordinates($latitude, $longitude) {

    $url = "http://maps.sharengo.it/reverse.php?format=json&zoom=18&addressdetails=1&lon=" . $longitude . "&lat=" . $latitude;

    $ctx = stream_context_create(array('http' =>
        array(
            'timeout' => 5, //1200 Seconds is 20 Minutes
        )
    ));


    $data = @file_get_contents($url, false, $ctx);
    $jsondata = json_decode($data, true);

    $road = (isset($jsondata['address']['road']) ?
            $jsondata['address']['road'] :
            (isset($jsondata['address']['pedestrian']) ? $jsondata['address']['pedestrian'] : ''));

    $city = (isset($jsondata['address']['town']) ?
            $jsondata['address']['town'] :
            $jsondata['address']['city']);

    return
            (($road != '') ? $road . ', ' : '') .
            (($city != '') ? $city . ', ' : '') .
            $jsondata['address']['county'];
}

function getTripPayable($dbh, $plate, $customerId) {
	$result = true;

	try {
		$sql = "SELECT  (SELECT gold_list FROM customers WHERE id = :customer_id) OR EXISTS(SELECT 1 FROM cars WHERE plate= :plate AND fleet_id > 100) gold_list ";
		$stm = $dbh->prepare($sql);
		$stm->bindParam(':customer_id', $customerId, PDO::PARAM_STR);
		$stm->bindParam(':plate', $plate, PDO::PARAM_STR);
		$res = $stm->execute();
		$row = $stm->fetch();

		if ($row) {
			$result = !$row[0];
		}

	} catch (Exception $e) {
	}

	return $result;
}

//print_r($_REQUEST);

$out = array();
$out['result'] = 0;
$out['message'] = '';


$cmd = $_REQUEST['cmd'] ?: CMD_INFO;
$id = $_REQUEST['id'] ?: 0;
$id_veicolo = $_REQUEST['id_veicolo'];
$id_cliente = $_REQUEST['id_cliente'] ?: 0;
$ora = $_REQUEST['ora'] ?: 0;
$km = $_REQUEST['km'] ?: 0;
$carburante = $_REQUEST['carburante'];
$lon = (string) $_REQUEST['lon'] ?: 0;
$lat = (string) $_REQUEST['lat'] ?: 0;
$warning = $_REQUEST['warning'];
$mac = $_REQUEST['mac'];
$imei = $_REQUEST['imei'];
$sosta_secondi = $_REQUEST['park_seconds'] ?: 0;
$n_pin = $_REQUEST['n_pin'] ?: 1;
$id_parent = $_REQUEST['id_parent'] ?: NULL;
$ip = $_SERVER['REMOTE_ADDR'];

$orastr = validateDateTime($ora, false);




if ($cmd < 0 || $cmd > 2 || !isset($id_veicolo)) {
    printOutput($out, 0, 'Invalid request');
    exit(1);
}


$dbh = getDb();
$dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

$error_code = 0;

switch ($cmd) {

    case CMD_INFO:
        $sql = "SELECT * FROM trips WHERE timestamp_end is NULL AND car_plate = :id_veicolo ORDER BY timestamp_beginning ASC";
        $stm = $dbh->prepare($sql);
        $stm->bindParam(':id_veicolo', $id_veicolo, PDO::PARAM_STR);
        $stm->execute();

        $result = $stm->fetchAll(PDO::FETCH_ASSOC);

        $num = count($result);

        $result['nrows'] = $num;
        printOutput($result, $num, "OK");
        break;

    case CMD_APERTURA:


        try {
            $sql = "SELECT id, pin_type FROM trips WHERE car_plate = :targa AND timestamp_beginning=:ora";
            $stm = $dbh->prepare($sql);
            $stm->bindParam(':targa', $id_veicolo, PDO::PARAM_STR);
            $stm->bindParam(':ora', $orastr, PDO::PARAM_STR);
            $res = $stm->execute();
            $row = $stm->fetch();


            if ($row) {
                if (isset($row) && $row[0] > 0) {
                    if ($n_pin == N_COMPANY_PIN && $row[1] != "company") {

                        $is_business_trip = $n_pin == N_COMPANY_PIN;
                        $pin_type = $is_business_trip ? "company" : null;

                        $sql = "UPDATE trips SET pin_type = :pin_type " .
                                " WHERE id = :id";
                        $stm = $dbh->prepare($sql);
                        $dbh->beginTransaction();
                        $result = $stm->execute(array(':id' => $row[0], ':pin_type' => $pin_type));





                        $businessTripResult = false;
                        if ($is_business_trip) {
                            $sql = "INSERT INTO business.business_trip (business_code, group_id, trip_id) 
                                        SELECT business_employee.business_code, business_employee.group_id, :trip_id 
                                        FROM business.business_employee 
                                        WHERE business_employee.employee_id = :employee_id AND business_employee.status = 'approved'";
                            $stm = $dbh->prepare($sql);
                            $stm->bindParam(':trip_id', $row[0]);
                            $stm->bindParam(':employee_id', $id_cliente, PDO::PARAM_STR);
                            $businessTripResult = $stm->execute();
                        }


                        if ($result && (!$is_business_trip || $businessTripResult)) {
                            $dbh->commit();
                            printOutput($out, $row[0], 'updated business');
                        } else {
                            $dbh->rollBack();
                            printOutput($out, -10, $dbh->errorInfo());
                        }
                        exit();
                    } else {
                        printOutput($out, $row[0], 'Already sent');
                        exit();
                    }
                } else {
                    printOutput($out, $row[0], 'Already sent');
                    exit();
                }
            }

            $sql = "SELECT count(*)  FROM trips WHERE car_plate <> :targa AND customer_id = :id_cliente AND timestamp_end is null";
            $stm = $dbh->prepare($sql);
            $stm->bindParam(':targa', $id_veicolo, PDO::PARAM_STR);
            $stm->bindParam(':id_cliente', $id_cliente, PDO::PARAM_STR);
            $res = $stm->execute();

            if ($res) {
                $opentrips = $stm->fetchColumn();
                if (isset($opentrips) && $opentrips > 0) {
                    $error_code = 15;
                }
            }

            $pagabile = getTripPayable($dbh, $id_veicolo, $id_cliente);

            if ($lat && $lon)
                $address_beginning = getAddressFromCoordinates($lat, $lon);
            else
                $address_beginning = NULL;

            $sql = "INSERT INTO trips ( customer_id,car_plate,timestamp_beginning,km_beginning,battery_beginning,longitude_beginning,latitude_beginning,geo_beginning,beginning_tx,payable, error_code,parent_id, address_beginning, fleet_id)" .
                    " VALUES (:id_cliente,:targa,:ora,:km,:carburante, cast(:lon as numeric),cast(:lat as numeric), ST_SetSRID(ST_MakePoint(:lon,:lat),4326), now(), :pagabile, :error_code, :id_parent, :address_beginning, " .
                    "(SELECT fleet_id FROM cars WHERE plate=:targa)) RETURNING id";
            $stm = $dbh->prepare($sql);
            $stm->bindParam(':id_cliente', $id_cliente, PDO::PARAM_STR);
            $stm->bindParam(':targa', $id_veicolo, PDO::PARAM_STR);
            $stm->bindParam(':ora', $orastr, PDO::PARAM_STR);
            $stm->bindParam(':km', $km, PDO::PARAM_INT);
            $stm->bindParam(':carburante', $carburante, PDO::PARAM_INT);
            $stm->bindParam(':lon', $lon, PDO::PARAM_STR);
            $stm->bindParam(':lat', $lat, PDO::PARAM_STR);
            //$stm->bindParam(':warning',$warning,PDO::PARAM_STR);
            //$stm->bindParam(':mac',$mac,PDO::PARAM_STR);
            //$stm->bindParam(':imei',$imei,PDO::PARAM_STR);
            //$stm->bindParam(':ipaddress',$ip,PDO::PARAM_STR);
            //$stm->bindParam(':n_pin',$n_pin,PDO::PARAM_STR);
            $stm->bindParam(':pagabile', $pagabile, PDO::PARAM_INT);
            $stm->bindParam(':error_code', $error_code, PDO::PARAM_INT);
            $stm->bindParam(':id_parent', $id_parent, PDO::PARAM_INT);
            $stm->bindParam(':address_beginning', $address_beginning, PDO::PARAM_STR);

            $dbh->beginTransaction();
            $result = $stm->execute();

            $row = $stm->fetchColumn();

            if ($result) {
                $dbh->commit();
                if ($error_code == 15) {
                    printOutput($out, -15, 'Open trips', $row);
                } else {
<<<<<<< HEAD
                    // preautorizzazione, la gestione resta ad OBC in caso di messaggio negativo SELFCLOSE
                    if (abs(time()-$ora) <= 30 && $pagabile && $PreautEnable && (($id_veicolo == 'EH43571' && $id_cliente == 39096) || $id_cliente == 26740)) { //targa e id_cliente per test Milano 06/11/17
                        $out['preaut_done']=false;
                        try{
                            $response = exec('bash ' . $Path . '/scripts/preauthorization.sh ' . $id_cliente . ' ' .$row); //valutare di implementare timeout lato pushcorsa
                            //error_log($response);
							$response = json_decode($response, true);
							if (abs($response["response"]) == 22){
								$preauth_enable = true;
								printOutput($out,$row,'OK',NULL);
								exit();
							} else if (abs($response["response"]) < 26){ // preaut successfully
                                printOutput($out,$row,'OK',NULL);
                                exit();
                            } else {
                                //stop trip
                                printOutput($out,$response["response"],'Preaut fail',$row);
                                exit();
                            }
                        } catch (Exception $e) {
                            //something went wrong
                            printOutput($out,$row,'OK',NULL);
                            exit();
                        }
                    }
                    printOutput($out,$row,'OK',NULL);
=======
                    printOutput($out, $row, 'OK');
>>>>>>> 39d4e1e83320561163585cda5ae05ea815eabb8c
                }
            } else {
                $dbh->rollBack();
                printOutput($out, -10, $dbh->errorInfo());
            }
        } catch (PDOException $e) {
            printOutput($out, -11, $e->getMessage());
        }

        break;

    case CMD_CHIUSURA:

        try {

            $sql = "SELECT count(*) FROM trips WHERE id = ? AND car_plate = ? AND customer_id = ?";
            $stm = $dbh->prepare($sql);
            $res = $stm->execute(array($id, $id_veicolo, $id_cliente));

            if (!$res || $stm->fetchColumn() == 0) {
                printOutput($out, -3, 'No match');
                exit();
            }

            $sql = "SELECT count(*) FROM trips WHERE id = ? AND timestamp_end IS NOT NULL";
            $stm = $dbh->prepare($sql);
//         $res = $stm->execute(array($id,$orastr));
            $res = $stm->execute(array($id));

            if ($res && $stm->fetchColumn() > 0) {   //Già chiusa aggiorna solo i dati e tieni buona l'ora di chiusura già presente
                $sql = "UPDATE trips SET  km_end = :km ,battery_end = :carburante,  longitude_end = cast(:lon as numeric), latitude_end = cast(:lat as numeric), geo_end = ST_SetSRID(ST_MakePoint(:lon,:lat),4326), end_tx = now(), park_seconds = :sosta_secondi WHERE id = :id";
                $stm = $dbh->prepare($sql);
                $dbh->beginTransaction();
                $result = $stm->execute(array(':id' => $id, ':km' => $km, ':carburante' => $carburante, ':lon' => $lon, ':lat' => $lat, ':sosta_secondi' => $sosta_secondi));
                $dbh->commit();
                printOutput($out, $id, 'OK');
                exit();
            }

            if ($lat && $lon)
                $address_end = getAddressFromCoordinates($lat, $lon);
            else
                $address_end = NULL;


            $is_business_trip = $n_pin == N_COMPANY_PIN;
            $pin_type = $is_business_trip ? "company" : null;

            $sql = "UPDATE trips SET timestamp_end = :ora ,km_end = :km ,battery_end = :carburante,  longitude_end = cast(:lon as numeric), latitude_end = cast(:lat as numeric), geo_end = ST_SetSRID(ST_MakePoint(:lon,:lat),4326)," .
                    " end_tx = now(), park_seconds = :sosta_secondi, parent_id = :id_parent, address_end = :address_end, pin_type = :pin_type " .
                    " WHERE id = :id";
            $stm = $dbh->prepare($sql);
            $dbh->beginTransaction();
            $result = $stm->execute(array(':id' => $id, ':ora' => $orastr, ':km' => $km, ':carburante' => $carburante, ':lon' => $lon, ':lat' => $lat, ':sosta_secondi' => $sosta_secondi, ':id_parent' => $id_parent, ':address_end' => $address_end, ':pin_type' => $pin_type));

            $businessTripResult = false;
            if ($is_business_trip) {
                $business = null;
                $skip = false;

                $sql = "SELECT b.* FROM business.business b
                    INNER JOIN business.business_trip bt ON (bt.business_code=b.code)
                    WHERE bt.trip_id = :trip_id";
                $stm = $dbh->prepare($sql);
                $stm->bindParam(':trip_id', $id);
                $res = $stm->execute();
                if ($res) {
                    $business = $stm->fetch();
                }

                $sql = "SELECT business.business_trip.trip_id FROM business.business_trip WHERE business.business_trip.trip_id = :trip_id";
                $stm = $dbh->prepare($sql);
                $stm->bindParam(':trip_id', $id);
                $res = $stm->execute();
                $row = $stm->fetch();

                if ($row) {
                    if ($row[0] > 0) {

                        $skip = true;
                    }
                }
                var_dump($id);
                if (!$skip) {
                    $sql = "INSERT INTO business.business_trip (business_code, group_id, trip_id) 
                                SELECT business_employee.business_code, business_employee.group_id, :trip_id 
                                FROM business.business_employee 
                                WHERE business_employee.employee_id = :employee_id AND business_employee.status = 'approved'";
                    $stm = $dbh->prepare($sql);
                    $stm->bindParam(':trip_id', $id);
                    $stm->bindParam(':employee_id', $id_cliente, PDO::PARAM_STR);
                    $businessTripResult = $stm->execute();
                } else {
                    $businessTripResult = true;
                }

                $businessPaymentTypeResult = true;
                if (is_null($business['payment_type'])) {    // if payment_type is null, trip is not payable
                    $sql = "UPDATE trips SET payable=false WHERE id = :trip_id";
                    $stm = $dbh->prepare($sql);
                    $businessPaymentTypeResult = $stm->execute(array(':trip_id' => $id));
                }
            }

            if ($result && (!$is_business_trip || ($businessTripResult && $businessPaymentTypeResult))) {
                $dbh->commit();
                printOutput($out, $id, 'OK');
            } else {
                $dbh->rollBack();
                printOutput($out, -10, $dbh->errorInfo());
            }
        } catch (PDOException $e) {
            printOutput($out, -11, $e->getMessage());
        }

        break;
}


$dbh = null;
?>
