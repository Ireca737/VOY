<?php

use Proxy\Api\Api;

$MetaPageTitle = "";
$MetaPageDescription = "";
$MetaPageKeywords = "";
?>
<?php
include 'lib/functions.php';
include 'config.php';
session_start();
Api::__constructStatic();
$legId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$legId || $legId < 1) { http_response_code(404); exit('Leg MFO non trovata'); }
function mfo_leg_slot(int $legId): ?array {
    return (static function (int $legId): ?array {
        require __DIR__ . '/voy/secure_config/conn_voy.php';
        if (!isset($conn) || !($conn instanceof mysqli)) throw new RuntimeException('Database VOY non disponibile');
        $stmt = $conn->prepare("SELECT s.slot_id, s.activity_id, s.status_mfo FROM voy_mfo_slots s INNER JOIN voy_mfo_programs p ON p.activity_id=s.activity_id WHERE s.activity_leg_id=? AND s.status_mfo <> 'OBSOLETE' LIMIT 1");
        if (!$stmt) throw new RuntimeException('Query slot MFO non disponibile');
        $stmt->bind_param('i', $legId);
        $stmt->execute();
        $stmt->bind_result($slotId, $activityId, $state);
        $result = $stmt->fetch() ? ['slot_id'=>(int)$slotId,'activity_id'=>(int)$activityId,'status_mfo'=>$state] : null;
        $stmt->close();
        return $result;
    })($legId);
}
function mfo_leg_book(int $slotId, int $pilotId): bool {
    return (static function (int $slotId, int $pilotId): bool {
        require __DIR__ . '/voy/secure_config/conn_voy.php';
        if (!isset($conn) || !($conn instanceof mysqli)) throw new RuntimeException('Database VOY non disponibile');
        $stmt = $conn->prepare("UPDATE voy_mfo_slots SET status_mfo='BOOKED', pilot_id=?, booked_at=NOW() WHERE slot_id=? AND status_mfo='AVAILABLE'");
        if (!$stmt) throw new RuntimeException('Aggiornamento slot non disponibile');
        $stmt->bind_param('ii', $pilotId, $slotId);
        if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Aggiornamento slot fallito'); }
        $changed = $stmt->affected_rows === 1;
        $stmt->close();
        return $changed;
    })($slotId, $pilotId);
}

function mfo_leg_release_booking(int $slotId, int $pilotId): bool {
    return (static function (int $slotId, int $pilotId): bool {
        require __DIR__ . '/voy/secure_config/conn_voy.php';
        if (!isset($conn) || !($conn instanceof mysqli)) throw new RuntimeException('Database VOY non disponibile');
        $stmt = $conn->prepare("UPDATE voy_mfo_slots SET status_mfo='AVAILABLE', pilot_id=NULL, booked_at=NULL WHERE slot_id=? AND status_mfo='BOOKED' AND pilot_id=? AND bid_id IS NULL");
        if (!$stmt) throw new RuntimeException('Rilascio slot non disponibile');
        $stmt->bind_param('ii', $slotId, $pilotId);
        if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Rilascio slot fallito'); }
        $changed = $stmt->affected_rows === 1;
        $stmt->close();
        return $changed;
    })($slotId, $pilotId);
}
try { $mfoSlot = mfo_leg_slot((int)$legId); }
catch (Throwable $e) { error_log('[slot_leg.php] '. $e->getMessage()); http_response_code(503); exit('Servizio MFO temporaneamente non disponibile'); }
if (!$mfoSlot) { http_response_code(404); exit('Questa tratta non appartiene a un programma MFO'); }
if (empty($_SESSION['mfo_leg_csrf'])) $_SESSION['mfo_leg_csrf'] = bin2hex(random_bytes(32));
$mfoSyncWarning = false;
$mfoBidUncertain = false;
$settings = null;
$limitDepatureLocation = false;
$res = Api::sendSync('GET', 'v1/airline', null);
if ($res->getStatusCode() == 200) {
    $settings = json_decode($res->getBody(), false);
    $limitDepatureLocation = $settings->limitDepartureLocation;
}
$currentLocation = "";
if ($limitDepatureLocation) {
    $res = Api::sendAsync('GET', 'v1/pilot/location', null);
    if ($res->getStatusCode() == 200) {
        $response = json_decode($res->getBody());
        $currentLocation = $response->location;
    }
}
$activity = null;
$res = Api::sendAsync('GET', 'v1/activity/leg/' . $legId, null);
$status = "";
$responseMessage = null;
if ($res->getStatusCode() == 200) {
    $activity = json_decode($res->getBody());
} else {
    header('Location: ' . website_base_url);
    die();
}

if (isset($_POST["btnbook"])) {
    if (!isset($_POST['mfo_csrf']) || !hash_equals($_SESSION['mfo_leg_csrf'], (string)$_POST['mfo_csrf'])) {
        http_response_code(403); exit('Richiesta non valida');
    }
    // A stale booking page must not send a new BID for an already assigned slot.
    try { $mfoSlot = mfo_leg_slot((int)$legId); }
    catch (Throwable $e) { error_log('[slot_leg.php] '. $e->getMessage()); http_response_code(503); exit('Servizio MFO temporaneamente non disponibile'); }
    if (!$mfoSlot || $mfoSlot['status_mfo'] !== 'AVAILABLE') {
        http_response_code(409); exit('Slot MFO non più disponibile. Tornare alla Flight Board.');
    }
    if (!empty($_SESSION['pilotid'])) {
        $pilotId = (int)$_SESSION['pilotid'];

        // Atomic local reservation: only one request can move this slot
        // from AVAILABLE to BOOKED.
        try {
            if (!mfo_leg_book($mfoSlot['slot_id'], $pilotId)) {
                http_response_code(409);
                exit('Slot MFO non più disponibile. Tornare alla Flight Board.');
            }
            $mfoSlot['status_mfo'] = 'BOOKED';
        } catch (Throwable $e) {
            error_log('[slot_leg.php] Prenotazione atomica MFO fallita per slot '.$mfoSlot['slot_id'].': '.$e->getMessage());
            http_response_code(503);
            exit('Servizio MFO temporaneamente non disponibile');
        }

        Api::__constructStatic();
        $data = [
            'PilotId' => $pilotId,
            'DepartureIcao' => empty($activity->departureIcao) ? "" : $activity->departureIcao,
            'ArrivalIcao' => empty($activity->arrivalIcao) ? "" : $activity->arrivalIcao,
            'TotalPax' => 0,
            'Route' => empty($activity->route) ? "" : $activity->route,
            'FlightNumber' => empty($activity->flightNumber) ? "" : $activity->flightNumber,
            'AircraftIcao' => empty($activity->aircraft) ? "" : $activity->aircraft,
            'AircraftReg' => empty($activity->aircraftReg) ? "" : $activity->aircraftReg,
            'Cargo' => 0,
            'ActivityId' => $activity->activityId,
            'ActivityLegId' => $activity->id,
        ];

        try {
            $res = Api::sendSync('POST', 'v1/bid', $data);
            $httpStatus = $res->getStatusCode();

            if ($httpStatus === 200) {
                $status = "book-flight";
            } elseif ($httpStatus === 400) {
                // Explicit VaBase rejection: compensate the local reservation.
                try {
                    if (!mfo_leg_release_booking($mfoSlot['slot_id'], $pilotId)) {
                        $mfoSyncWarning = true;
                        error_log('[slot_leg.php] Rifiuto VaBase ma slot MFO non rilasciato: '.$mfoSlot['slot_id']);
                    } else {
                        $mfoSlot['status_mfo'] = 'AVAILABLE';
                    }
                } catch (Throwable $e) {
                    $mfoSyncWarning = true;
                    error_log('[slot_leg.php] Rifiuto VaBase, errore rilascio slot '.$mfoSlot['slot_id'].': '.$e->getMessage());
                }
                $status = "book-flight-fail";
                $responseMessage = $res->getBody();
            } else {
                // Unknown outcome: do not release automatically, because VaBase
                // may have created the BID even if the response is incomplete.
                $mfoBidUncertain = true;
                $status = "book-flight-fail";
                $responseMessage = 'Esito della prenotazione VaBase non determinabile. Non ripetere il BOOK; contattare lo staff.';
                error_log('[slot_leg.php] Esito VaBase incerto HTTP '.$httpStatus.' per slot '.$mfoSlot['slot_id']);
            }
        } catch (Throwable $e) {
            // Timeout/transport failure: keep the slot reserved until reconciliation.
            $mfoBidUncertain = true;
            $status = "book-flight-fail";
            $responseMessage = 'Esito della prenotazione VaBase non determinabile. Non ripetere il BOOK; contattare lo staff.';
            error_log('[slot_leg.php] Esito VaBase incerto per slot '.$mfoSlot['slot_id'].': '.$e->getMessage());
        }
    }
}
$mapData = $activity->airportInfo;
$path_data = null;
$hasActiveBid = false;
$canBookActivity = false;
if (!empty($_SESSION['pilotid'])) {
    $res = Api::sendAsync('GET', 'v1/activity/booking-status/' . $activity->activityId . '/' . $_SESSION['pilotid'] . '/' . $activity->id, null);
    if ($res->getStatusCode() == 200) {
        $response = json_decode($res->getBody());
        $hasActiveBid = $response->hasBookedFlight;
        $canBookActivity = $response->canBookActivity;
    }
}
?>
<?php include 'includes/header.php'; ?>
<section id="content" class="cp section offset-header">
    <div class="container">
        <div class="row">
            <?php if ($mfoSyncWarning) { ?><div class="col-md-12"><div class="alert alert-warning">La sincronizzazione tra VaBase e MFO non è completa. Non ripetere la prenotazione; contattare lo staff.</div></div><?php } ?>
            <?php if ($mfoBidUncertain) { ?><div class="col-md-12"><div class="alert alert-warning">Esito VaBase incerto: lo slot MFO resta riservato per evitare una doppia prenotazione. Non ripetere il BOOK; contattare lo staff.</div></div><?php } ?>
            <?php if (!empty($status)) { ?>
            <div class="col-md-12">
                <?php if ($status == "book-flight") { ?>
                <div class="alert alert-success text-center"><i class="fa fa-check" aria-hidden="true"></i> Flight has
                    been successfully booked. Please visit the <a
                        href="<?php echo website_base_url; ?>site_pilot_functions/dispatch.php"
                        class="js_showloader btn btn-success">Dispatch
                        Center</a> for a flight briefing.</div>
                <?php } ?>
                <?php if ($status == "book-flight-fail") { ?>
                <div class="alert alert-danger text-center">
                    <?php if (!empty($responseMessage)) {
                                echo $responseMessage;
                            } else {
                                echo 'Unable to book flight. Please try again later.';
                            } ?>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
            <div class="col-md-6">
                <a href="<?php echo website_base_url; ?>slot.php?id=<?php echo (int)$activity->activityId; ?>"><i
                        class="fa fa-angle-double-left js_showloader" aria-hidden="true"></i> Back to tour/event</a>
            </div>
            <div class="col-md-6 text-right">
                <?php if (isset($_SESSION['pilotid'])) { ?>
                <?php if ($limitDepatureLocation && $currentLocation != $activity->departureIcao && !(empty($activity->departureIcao)) && !empty($currentLocation)) { ?>
                <div class="alert alert-default text-center"><i class="fa fa-exclamation-triangle"
                        aria-hidden="true"></i> You can't book this flight as you are not currently located at this
                    departure airport. Head to the <a
                        href="<?php echo website_base_url; ?>site_pilot_functions/pilot_centre.php"
                        class="js_showloader btn btn-default">Dashboard</a> to take a jump seat flight.</div>
                <?php } else { ?>
                <?php if (!$hasActiveBid && $canBookActivity && $mfoSlot['status_mfo'] === 'AVAILABLE') { ?>
                <form method="post" class="form"><input type="hidden" name="mfo_csrf" value="<?php echo htmlspecialchars($_SESSION['mfo_leg_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                    <button name="btnbook" type="submit" id="btnbook" class="btn btn-success"><i class="fa fa-plane"
                            aria-hidden="true"></i> Dispatch Flight</button>
                </form>
                <?php } elseif ($hasActiveBid) { ?>
                <div class="col-md-12">
                    <button id="btnbook" class="btn btn-warning" disabled><i class="fa fa-exclamation-triangle"
                            aria-hidden="true"></i> Dispatch Flight</button>
                </div>
                <div class="col-md-12">
                    <small>You have a booked flight. Please visit the <a
                            href="<?php echo website_base_url; ?>site_pilot_functions/dispatch.php">dispatch</a>
                        center for a flight briefing.</small>
                </div>
                <?php } elseif (!$canBookActivity || $mfoSlot['status_mfo'] !== 'AVAILABLE') { ?>
                <div class="row">
                    <div class="col-md-12">
                        <button name="btnbook" type="submit" id="btnbook" class="btn btn-warning" disabled><i
                                class="fa fa-exclamation-triangle" aria-hidden="true"></i> Dispatch Flight</button>
                    </div>
                    <div class="col-md-12">
                        <small>Check tour/event is active and legs flown in order.</small>
                    </div>
                </div>
                <?php } ?>
                <?php } ?>
                <?php } ?>
            </div>
        </div>
        <hr />
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <?php echo empty($activity->departureIcao) ? "Depart Any" : htmlspecialchars($activity->departureIcao); ?>
                            to
                            <?php echo empty($activity->arrivalIcao) ? "Any Destination" : htmlspecialchars($activity->arrivalIcao); ?>
                        </h3>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="activity-content"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Leg Detail</h3>
                    </div>
                    <div class="panel-body row-space">
                        <div class="row">
                            <div class="col-md-6 col-xs-6">
                                <div class="row">
                                    <div class="col-md-4">
                                        Flight Number
                                    </div>
                                    <div class="col-md-8">
                                        <strong><?php echo isset($activity->flightNumber) ? htmlspecialchars($activity->flightNumber) : "Any"; ?></strong>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        Departure ICAO
                                    </div>
                                    <div class="col-md-8">
                                        <strong><?php echo empty($activity->departureIcao) ? "Any" : '<a href="airport_info.php?airport=' . $activity->departureIcao . '">' . $activity->departureIcao . '</a>'; ?></strong>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        Arrival ICAO
                                    </div>
                                    <div class="col-md-8">
                                        <strong><?php echo empty($activity->arrivalIcao) ? "Any" : '<a href="airport_info.php?airport=' . $activity->arrivalIcao . '">' . $activity->arrivalIcao . '</a>'; ?></strong>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        Duration approx.
                                    </div>
                                    <div class="col-md-8">
                                        <strong><?php echo ($activity->duration == null ? 'NA' : htmlspecialchars($activity->duration)) ?></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xs-6">
                                <div class="row">
                                    <div class="col-md-4">
                                        Aircraft
                                    </div>
                                    <div class="col-md-8">
                                        <strong><?php echo ($activity->aircraft == null ? 'Any' : htmlspecialchars($activity->aircraft)) ?></strong>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        Aircraft Registration
                                    </div>
                                    <div class="col-md-8">
                                        <strong><?php echo ($activity->aircraftReg == null ? 'Any' : htmlspecialchars($activity->aircraftReg)) ?></strong>
                                    </div>
                                </div>
                                <?php if (isset($activity->airportInfo[1])) { ?>
                                <div class="row">
                                    <div class="col-md-4">
                                        Distance approx.
                                    </div>
                                    <div class="col-md-8">
                                        <strong><?php echo number_format(get_distance($activity->airportInfo[0]->lng, $activity->airportInfo[0]->lat, $activity->airportInfo[1]->lng, $activity->airportInfo[1]->lat), 1); ?>nm</strong>
                                    </div>
                                </div>
                                <?php } ?>
                                <div class="row">
                                    <div class="col-md-4">
                                        Route
                                    </div>
                                    <div class="col-md-8">
                                        <strong><?php echo ($activity->route == null ? 'Any' : htmlspecialchars($activity->route)) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <?php include 'site_widgets/map_flight.php'; ?>
                <div id="map" style="height:600px;"></div>
            </div>
        </div>
    </div>
    </div>
</section>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/editorjs-parser@1/build/Parser.browser.min.js"></script>
<script type="text/javascript">
var descJson =
    '<?php echo $activity->description != null ? addslashes(preg_replace("/\r|\n/", "", $activity->description)) : ""; ?>';
$(document).ready(function() {
    try {
        var parser = new edjsParser({
            embed: {
                useProvidedLength: false,
            }
        });
        var html = parser.parse(JSON.parse(descJson));
        $(".activity-content").html(html)
        console.log(html);
    } catch (e) {
        $(".activity-content").html(descJson);
    }
});
</script>