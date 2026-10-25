<?php
use Proxy\Api\Api;
include '../lib/functions.php';
include '../config.php';
Api::__constructStatic();
session_start();
validateSession();

$pid = $_SESSION['pilotid'];
$hasactivebid = false;
$pilot = null;
$res = Api::sendAsync('GET', 'v1/pilot/' . $pid, null);
if ($res->getStatusCode() == 200) {
    $pilot = json_decode($res->getBody());
} else {
    header('Location: ' . website_base_url);
    die();
}
$settings = null;
$limitDepatureLocation = false;
$res = Api::sendSync('GET', 'v1/airline', null);
if ($res->getStatusCode() == 200) {
    $settings = json_decode($res->getBody(), false);
    $limitDepatureLocation = $settings->limitDepartureLocation;
}
$bestLanding = null;
$res = Api::sendAsync('GET', 'v1/operations/pirep/pilot/best-landing/' . $pid, null);
if ($res->getStatusCode() == 200) {
    $bestLanding = json_decode($res->getBody(), false);
}
$latestFlight = null;
$mapData = [];
$res = Api::sendAsync('GET', 'v1/operations/pirep/pilot/latest', null);
if ($res->getStatusCode() == 200) {
    $latestFlight = json_decode($res->getBody(), false);
    array_push($mapData, $latestFlight->departure);
    array_push($mapData, $latestFlight->arrival);
    array_push($mapData, $latestFlight->alternate);
    $path_data = $latestFlight->pathData;
}
$hasactivebid = false;
$res = Api::sendAsync('GET', 'v1/bid/' . $pid, null);
$status = "";
if ($res->getStatusCode() == 200) {
    $bid = json_decode($res->getBody());
    $hasactivebid = true;
    $bidexpires = new DateTime($bid->dateBooked);
    $bidexpires->modify('+ ' . (int) $_SESSION['booking_expire_hours'] . ' hour');
    $bdepicao = $bid->departureIcao;
    $barricao = $bid->arrivalIcao;
}

$function = null;
if (isset($_GET['function'])) {
    $function = cleanString($_GET['function']);
}

if ($function == 'logout') {
    session_destroy();
    header('Location: ' . website_base_url . 'authentication/login.php?logout=yes');
    exit();
}
$activities = null;
$res = Api::sendSync('GET', 'v1/activities/active-lite', null);
if ($res->getStatusCode() == 200) {
    $activities = json_decode($res->getBody(), false);
}
// VOY MFO TEST: load connection once in isolated scope, but expose
// $conn to news_feed_voy.php, which uses require_once and expects it.
$mfoActivityIds = [];
$mfoLookupOk = false;
try {
    $mfoData = (static function (): array {
        require __DIR__ . '/../voy/secure_config/conn_voy.php';
        if (!isset($conn) || !($conn instanceof mysqli)) {
            throw new RuntimeException('Connessione VOY non disponibile');
        }
        $ids = [];
        $query = $conn->query('SELECT activity_id FROM voy_mfo_programs');
        if ($query === false) {
            throw new RuntimeException('Elenco programmi MFO non disponibile');
        }
        while ($row = $query->fetch_assoc()) {
            $ids[(int)$row['activity_id']] = true;
        }
        $query->free();
        return ['conn' => $conn, 'ids' => $ids];
    })();
    $conn = $mfoData['conn'];
    $mfoActivityIds = $mfoData['ids'];
    $mfoLookupOk = true;
} catch (Throwable $e) {
    error_log('[pilot_centre_mfo_test.php] MFO: ' . $e->getMessage());
    // Fail closed: do not expose MFO events as ordinary events.
    $activities = [];
}

?>
<?php
$MetaPageTitle = "";
$MetaPageDescription = "";
$MetaPageKeywords = "";
?>
<?php include '../includes/header.php'; ?>
<section id="content" class="cp section offset-header">
    <div class="container">
        <div class="row">
            <div class="col-md-12 col-xs-12">
                <?php if ($hasactivebid) { ?>
                    <p class="text-center">You have a
                        flight scheduled from <strong><?php echo empty($bdepicao) ? 'Any' : $bdepicao; ?></strong>
                        to
                        <strong><?php echo empty($barricao) ? 'Any' : $barricao; ?></strong>.
                        Visit
                        the <a href="<?php echo website_base_url; ?>site_pilot_functions/dispatch.php"
                            class="btn btn-default js_showloader">Dispatch
                            Center</a>
                        for your flight briefing.
                        <hr />
                    </p>
                <?php } ?>
                <div class="jumbotron">
                    <?php if ($pilot->profileImage != "") { ?>
                        <img src="<?php echo website_base_url; ?>uploads/profiles/<?php echo $pilot->profileImage ?>"
                            width="200" style="float:right;" class="img-circle pilot-profile-image" />
                    <?php } else { ?>
                        <i class="fa fa-user-circle profile" aria-hidden="true" style="float:right;"></i>
                    <?php } ?>
                    <h1>Hello, <?php echo explode(" ", $_SESSION['name'])[0]; ?>.
                    </h1>
					<?php
// ====== MODIFICA VOY - Messaggio inattività pilota ======
$lastFlightDate = null;
$daysSinceLastFlight = null;
if (!empty($latestFlight)) {
    $lastFlightDate = new DateTime($latestFlight->date);
    $today = new DateTime();
    $interval = $today->diff($lastFlightDate);
    $daysSinceLastFlight = $interval->days;
}
?>

<?php
// ====== MODIFICA VOY - Messaggio inattività pilota ======
$lastFlightDate = null;
$daysSinceLastFlight = null;
$signupDate = isset($pilot->dateRegistered) ? new DateTime($pilot->dateRegistered) : null;

if (!empty($latestFlight) && !empty($latestFlight->date)) {
    $lastFlightDate = new DateTime($latestFlight->date);
    $today = new DateTime();
    $interval = $today->diff($lastFlightDate);
    $daysSinceLastFlight = $interval->days;
}
?>

<?php if ($daysSinceLastFlight !== null) { ?>
    <p>Non voli da <strong><?php echo $daysSinceLastFlight; ?> giorni</strong>, esattamente dal <strong><?php echo $lastFlightDate->format('d/m/Y'); ?></strong>.</p>

    <?php if ($daysSinceLastFlight <= 30) { ?>
        <p>✈️ <strong>CONGRATULAZIONI!</strong> hai registrato almeno un volo negli ultimi 30 giorni. <strong>Continua così, Comandante!</strong></p>


    <?php } elseif ($daysSinceLastFlight > 30 && $daysSinceLastFlight <= 60) { ?>
        <p>🌴 Cosa ne pensi di terminare le tue vacanze e registrare almeno un volo? Sali ai comandi con l'aeroporto del mese!</p>

    <?php } elseif ($daysSinceLastFlight > 60) { ?>
        <p>🛑 E' passato davvero tanto tempo ! gli altri comandanti sentono la tua assenza in Over Italy! Se hai problemi o impedimenti a registrare voli, <strong>contatta lo staff</strong>. Grazie! Ti aspettiamo nei cieli di Virtual Over Italy!</p>
    <?php } ?>
<?php } elseif ($signupDate !== null) { ?>
    <p>Risulta che sei iscritto dal <strong><?php echo $signupDate->format('d/m/Y'); ?></strong> e non hai ancora registrato alcun volo.</p>
    <p>📬 Ti invitiamo a <strong>contattare lo staff</strong> per qualsiasi necessità. Grazie!</p>
<?php } ?>
<!-- ====== FINE MODIFICA VOY - Messaggio inattività pilota ====== -->


					
					
					
                    <p>Your Pilot ID: <strong><?php echo $_SESSION['callsign']; ?></strong>
                        | <?php if (!empty($pilot->rank->imageUrl)) { ?><img
                                src="<?php echo website_base_url; ?>uploads/ranks/<?php echo $pilot->rank->imageUrl; ?>"
                                width="80" /> <strong><?php echo $pilot->rank->name; ?></strong><?php } else { ?>No
                            Rank<?php } ?>
                    </p>
                    <p>
                        Base: <strong><?php echo $pilot->hub; ?></strong>
                    </p>
                    <p>
                        Total Hours:
                        <strong><?php echo empty($pilot->totalHours) ? "No Flights" : $pilot->totalHours; ?></strong> |
                        XP: <strong><?php echo number_format($pilot->xp); ?></strong>
                    </p>
                    <p>Wallet Balance: <strong>$<span
                                class="balance"><?php echo number_format($pilot->wallet, 2); ?></span></strong></p>
                    <p>
                        <strong><i class="fa fa-trophy gold" aria-hidden="true"></i></strong> Best Landing:

                        <?php if (!empty($bestLanding)) {
                            echo '<strong>' . $bestLanding->landingRate . 'fpm</strong> on ' . (new DateTime($bestLanding->date))->format('d M Y') . ' at <i class="fa fa-map-marker" aria-hidden="true"></i> <a href="' . website_base_url . 'pirep_info.php?id=' . $bestLanding->id . '">' . $bestLanding->arrivalIcao . '</a>';
                        } else {
                            echo '<strong>NA</strong>';
                        } ?>
                    </p>
                    <p><i class="fa fa-map-marker" aria-hidden="true"></i> Virtual Location: <strong><span
                                class="virlocation"><?php echo empty($pilot->currentLocation) ? "NA" : $pilot->currentLocation; ?></span></strong>
                        <?php if ($limitDepatureLocation) { ?>| <a href="" class="btn btn-default jump">Jump Seat <i
                                class="fa fa-arrow-right" aria-hidden="true"></i><i class="fa fa-map-marker"
                                aria-hidden="true"></i></a><?php } ?>
                </div>
            </div>
        </div>
        <!-- VOY MFO: compact active programs, same source as slots.php -->
        <style>
            .voy-mfo-grid { display:flex; flex-wrap:wrap; gap:12px; }
            .voy-mfo-card.activity-card-container {
                float:none; display:block; flex:0 0 145px;
                width:145px; height:105px; margin:0;
                position:relative; background-size:cover; background-position:center;
                border-radius:5px; overflow:hidden; cursor:pointer;
            }
            .voy-mfo-card .voy-mfo-title {
                position:absolute; left:0; right:0; bottom:0;
                padding:6px 8px; background:rgba(0,0,0,.76);
                color:#fff; font-size:12px; font-weight:600;
                line-height:1.25; overflow-wrap:anywhere;
            }
            .voy-mfo-card a { color:#fff; text-decoration:none; }
            .voy-mfo-card a:hover { color:#fff; text-decoration:underline; }
            @media(max-width:480px) {
                .voy-mfo-card.activity-card-container { flex-basis:calc(50% - 6px); width:calc(50% - 6px); }
            }
        </style>
        <div class="row">
            <div class="col-md-12 col-xs-12">
                <div class="panel panel-default">
                    <div class="panel-heading"><h3 class="panel-title">Programmi MFO</h3></div>
                    <div class="panel-body">
                        <div class="voy-mfo-grid">
                            <?php
                            $hasMfoPrograms = false;
                            if ($mfoLookupOk && is_array($activities)) {
                                foreach ($activities as $activity) {
                                    if (($activity->type ?? '') !== 'Event' || !isset($mfoActivityIds[(int)$activity->id])) {
                                        continue;
                                    }
                                    $hasMfoPrograms = true;
                                    $slotUrl = website_base_url . 'slot.php?id=' . (int)$activity->id;
                                    $bannerUrl = website_base_url . 'uploads/activities/' . rawurlencode((string)($activity->banner ?? ''));
                                    ?>
                                    <div class="activity-card-container voy-mfo-card"
                                         data-sloturl="<?php echo htmlspecialchars($slotUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                         style="background-image:url('<?php echo htmlspecialchars($bannerUrl, ENT_QUOTES, 'UTF-8'); ?>');">
                                        <div class="voy-mfo-title">
                                            <a href="<?php echo htmlspecialchars($slotUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars((string)$activity->title, ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </div>
                                    </div>
                                <?php }
                            }
                            ?>
                        </div>
                        <?php if (!$hasMfoPrograms) { ?>
                            <p>Al momento non ci sono programmi MFO attivi.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12 col-xs-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <?php if (!empty($latestFlight)) { ?>
                            <span class="text-right" style="clear:left;float:right;"><a
                                    href="<?php echo website_base_url; ?>pirep_info.php?id=<?php echo $latestFlight->id; ?>">View
                                    Flight Report <i class="fa fa-arrow-right" aria-hidden="true"></i></a>
                            </span>
                        <?php } ?>
                        <h3 class="panel-title">Previous Flight</h3>

                    </div>
                    <div class="panel-body">
                        <?php if (!empty($latestFlight)) { ?>
                            <?php include_once '../site_widgets/map_flight.php'; ?>
                            <div id="map" style="height:400px;"></div>
                        <?php } else { ?>
                            <p>No recorded flights.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
	
<!-- VOY news -->
       <div class="row">
            <?php include_once '../site_widgets/news_feed_voy.php'; ?>
        </div>

	
				
        
		<!-- 🌐 Eventi Settimanali VOY -->
<div class="row">
    <div class="col-md-12 col-xs-12">
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title">📅 Programma Eventi Settimanali</h3>
            </div>
            <div class="panel-body">
                <?php include_once '../site_widgets/eventi_pilot_dashboard.php'; ?>
            </div>
        </div>
    </div>
</div> 

<!-- vecchio test 
	<div class="row">
		<div class="col-md-12 col-xs-12">
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title">📅 Programma Eventi Settimanali</h3>
            </div>
            <div class="panel-body">
                <p style="color: green;">[TEST] Widget caricato correttamente</p>
            </div>
        </div>
    </div>
</div> -->

		
	
		<div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Upcoming Events</h3>
                    </div>
                    <div class="panel-body">
                        <?php if (!empty($activities)) { ?>
                            <?php
                            $noEvents = true;
                            foreach ($activities as $key => $activity) {
                                if ($activity->type == "Event" && !isset($mfoActivityIds[(int)$activity->id])) {
                                    $noEvents = false; ?>
                                    <div class="activity-card-container" data-activityid="<?php echo $activity->id; ?>"
                                        style="background-image:url(<?php echo website_base_url; ?>uploads/activities/<?php echo $activity->banner; ?>);background-color:#ccc;">
                                        <div class="activity-card-hidden">
                                            <div>
                                                <p><a
                                                        href="<?php echo website_base_url; ?>activity.php?id=<?php echo $activity->id; ?>"><?php echo $activity->title; ?></a>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                            <?php
                                }
                            } ?>
                            <?php if ($noEvents) { ?>
                                <p>
                                    There are currently no events.
                                </p>
                            <?php } ?>
                        <?php } else { ?>
                            <p>There are currently no events.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
 <!-- VOY Flight Academy -->
<div class="row">
    <div class="col-md-12 col-xs-12">
        <div class="panel panel-info">
            <div class="panel-heading" style="background-color:#d9edf7; color:#31708f; display:flex; align-items:center;">
                <img src="../tutorial/assets/images/academy200.png" alt="VOY Flight Academy"
                     style="height:40px; margin-right:10px; border-radius:50%;">
                <h3 class="panel-title" style="margin:0;">VOY Academy</h3>
            </div>
            <div class="panel-body" style="display:flex; align-items:center; justify-content:center; gap:30px;">
                <img src="../tutorial/assets/images/academy200.png" alt="Flight Academy Logo"
                     style="height:100px; border-radius:50%; box-shadow:0 0 6px rgba(0,0,0,0.2);">
                <div class="text-center">
                    <p class="lead"> 🎓 Accedi ai tutorial e alle guide di addestramento 🎓 </p>
                    <a href="../tutorial/main_tutorial.php" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                        <i class="fa fa-graduation-cap"></i> Accedi a VOY Academy
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

    </div>
    <?php include_once '../site_widgets/jump_seat_modal.php'; ?>
</section>
<?php include '../includes/footer.php'; ?>
<script type="text/javascript">
    $(document).ready(function() {
        $(".activity-card-container").hover(function() {
            $(this).children('.activity-card-hidden').fadeIn();
        }, function() {
            $(this).children('.activity-card-hidden').hide();
        });
        $(".activity-card-container:not(.voy-mfo-card)").click(function() {
            Loader.start();
            window.location.href = "/activity.php?id=" + $(this).data("activityid");
        });
        $(".voy-mfo-card").click(function(e) {
            if ($(e.target).closest("a").length) return;
            Loader.start();
            window.location.href = $(this).data("sloturl");
        });
    });
</script>