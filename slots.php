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

$activities = null;
$res = Api::sendSync('GET', 'v1/activities/active-lite', null);
if ($res->getStatusCode() == 200) {
    $activities = json_decode($res->getBody(), false);
}

// Isolate the VOY database include so it cannot overwrite VaBase page variables.
try {
    $mfoActivityIds = (static function (): array {
        require __DIR__ . '/voy/secure_config/conn_voy.php';
        if (!isset($conn) || !($conn instanceof mysqli)) {
            throw new RuntimeException('Connessione VOY non disponibile');
        }
        $ids = [];
        $result = $conn->query('SELECT activity_id FROM voy_mfo_programs');
        if ($result === false) {
            throw new RuntimeException('Elenco programmi MFO non disponibile');
        }
        while ($row = $result->fetch_assoc()) {
            $ids[(int)$row['activity_id']] = true;
        }
        $result->free();
        return $ids;
    })();
    if (is_array($activities)) {
        $activities = array_values(array_filter($activities, static function ($activity) use ($mfoActivityIds) {
            return $activity->type === 'Event' && isset($mfoActivityIds[(int)$activity->id]);
        }));
    }
} catch (Throwable $e) {
    error_log('[activities.php] Filtro MFO non disponibile: ' . get_class($e));
    http_response_code(503);
    exit('Elenco attività temporaneamente non disponibile.');
}
?>
<?php include 'includes/header.php'; ?>
<section id="content" class="cp section offset-header">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
			  <p>Scopri i programmi MFO attivi e scegli le tratte da volare.</p>
                <h2>Programmi MFO</h2>
				
                <hr />
                <?php if (!empty($activities)) { ?>
                    <?php
                    $noEvents = true;
                    foreach ($activities as $key => $activity) {
                        if ($activity->type == "Event") {
                            $noEvents = false; ?>
                            <div class="activity-card-container" data-activityid="<?php echo $activity->id; ?>"
                                style="background-image:url(<?php echo website_base_url; ?>uploads/activities/<?php echo $activity->banner; ?>);background-color:#ccc;">
                                <div class="activity-card-hidden">
                                    <div>
                                        <p><a href="<?php echo website_base_url; ?>slot.php?id=<?php echo $activity->id; ?>"
                                                class="js_showloader"><?php echo $activity->title; ?></a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                    <?php
                        }
                    } ?>
                    <?php if ($noEvents) { ?>
                        <p>Al momento non ci sono programmi MFO attivi</p><?php } ?>
                <?php } else { ?>
                    <p>There are currently no events.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</section>
<?php include 'includes/footer.php'; ?>
<script type="text/javascript">
    $(document).ready(function() {
        $(".activity-card-container").hover(function() {
            $(this).children('.activity-card-hidden').fadeIn();
        }, function() {
            $(this).children('.activity-card-hidden').hide();
        });
        $(".activity-card-container").click(function() {
            Loader.start();
            window.location.href = "/slot.php?id=" + $(this).data("activityid");
        });
    });
</script>