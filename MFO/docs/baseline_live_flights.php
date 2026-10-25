<?php
use Proxy\Api\Api;
require_once __DIR__ . '/proxy/api.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/functions.php';
Api::__constructStatic();
session_start();
$flights = null;
$res = Api::sendAsync('GET', 'v1/map/acars', null);
if ($res->getStatusCode() == 200) {
    $flights = json_decode($res->getBody(), true);
}
if (!empty($flights)) {
    foreach ($flights as &$flight) {
        $totalDistance = round(get_distance(floatval($flight["startLong"]), floatval($flight["startLat"]), floatval($flight["arrLong"]), floatval($flight["arrLat"])));
        $distanceFlown = round(get_distance(floatval($flight["startLong"]), floatval($flight["startLat"]), floatval($flight["presLong"]), floatval($flight["presLat"])));
        $distanceRemaining = get_distance(floatval($flight["presLong"]), floatval($flight["presLat"]), floatval($flight["arrLong"]), floatval($flight["arrLat"]));
        $etaMins = 0;
        $etaHours = 0;
        if ($flight["statSpeed"] > 30) {
            $etaMins = round($distanceRemaining) / $flight["statSpeed"] * 60;
            $etaHours = intdiv(intval($etaMins), 60);
            $etaMins -= ($etaHours * 60);
        }
        $flight["total_dist"] = $totalDistance;
        $flight["dist_flown"] = $distanceFlown;
        $flight["eta"] = str_pad($etaHours, 2, '0', STR_PAD_LEFT) . ':' . str_pad(round($etaMins), 2, '0', STR_PAD_LEFT);
        $flight["perc_complete"] = $totalDistance - $distanceFlown > 5 ? round($distanceFlown / $totalDistance * 100) : 100;
        $flight["dtg"] = round($distanceRemaining, 1) < 1 ? 0 : round($distanceRemaining, 1);
        $flight["statHdg"] = str_pad($flight["statHdg"], 3, '0', STR_PAD_LEFT);
    }
}
?>
<?php
$MetaPageTitle = "";
$MetaPageDescription = "";
$MetaPageKeywords = "";
?>
<?php include 'includes/header.php';?>
<section id="content" class="cp section offset-header">
    <div class="container">
        <div class="row">
            <div class="jumbotron text-center">
                <h2>in volo ora</h2>
                <hr />
                <p>In questo momento ci sono <strong><?php echo count($flights); ?> voli</strong> in corso.
                <p>Clicca nel <strong>Numero del volo</strong> della <strong>lista</strong> qui sotto per aprire il volo nella mappa
                    e vedere maggiori dettagli
                </p>
                <p> usa il controllo in alto a sx della mappa per zommare + o -.
            </div>
            <?php include_once 'site_widgets/map_acars.php';?>
            <div id="map"></div>
        </div>
        <div class="row">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">in volo ora</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12">
                            <?php
if (empty($flights)) {
    ?>
                            <p>Al momento non vi sono voli in corso, riprova più tardi.</p>

                            <?php
} else {
    ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Pilota</th>
                                        <th>Volo Numero</th>
                                        <th>Partenza da</th>
                                        <th>Arrivo a</th>
                                        <th>Aereo</th>
                                        <th>Stage</th>
                                        <th>DTG</th>
                                        <th>ETA</th>
                                        <th style="min-width:100px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($flights as &$flight) {?>
                                    <tr>
                                        <td><a href="<?php echo website_base_url; ?>profile.php?id=<?php echo $flight['pilotId']; ?>"
                                                target="_blank"><i class="fa fa-external-link"></i>
                                                <?php echo explode(" ", $flight['name'])[0]; ?>
                                                <span class="small">(ID: <?php echo $flight['callsign']; ?>)</span></a>
                                        </td>
                                        <td> <a href="" class="marker" data-id="<?php echo $flight['actmpId']; ?>"><i
                                                    class="fa fa-external-link"></i>
                                                <?php echo $flight['flightNumber']; ?></a>
                                        </td>
                                        <td><?php echo $flight['depIcao']; ?>
                                        </td>
                                        <td><?php echo $flight['arrIcao']; ?>
                                        </td>
                                        <td><span
                                                title="<?php echo $flight['aircraftTypeId']; ?>"><?php echo limit($flight['aircraftTypeId'], 20); ?></span>
                                        </td>
                                        <td><strong><?php echo $flight['statStage']; ?></strong>
                                        </td>
                                        <td><?php echo $flight['dtg']; ?>nm
                                        </td>
                                        <td><?php echo $flight['eta']; ?>
                                        </td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar"
                                                    style="width: <?php echo (round($flight['perc_complete'], 0)); ?>%"
                                                    aria-valuenow="<?php echo (round($flight['perc_complete'], 0)); ?>"
                                                    aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php }?>
                                </tbody>
                            </table>
                            <?php
}
?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include 'includes/footer.php';?>
<script type="text/javascript">
$(document).ready(function() {
    $('.marker').on('click', function(e) {
        e.preventDefault();
        var marker = map.getMarkerById($(this).data("id"));
        marker.fire('click');
        window.scrollTo(0, 0);
    });
});
</script>
