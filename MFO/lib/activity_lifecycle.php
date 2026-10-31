<?php
/**
 * VOY MFO — on-demand reconciliation with VaBase Activity leg history.
 * Called before reading the flight board. Does not alter native VaBase data.
 */
function mfo_reconcile_activity_history(mysqli $conn, int $activityId): array
{
    if ($activityId < 1) {
        throw new InvalidArgumentException('Activity ID non valido');
    }

    $stmt = $conn->prepare("SELECT activity_leg_id, pilot_id FROM voy_mfo_slots WHERE activity_id = ? AND status_mfo = 'BOOKED' AND pilot_id IS NOT NULL AND completed_at IS NULL");
    if (!$stmt) throw new RuntimeException('SELECT lifecycle MFO non disponibile');
    try {
        $stmt->bind_param('i', $activityId);
        if (!$stmt->execute()) throw new RuntimeException('SELECT lifecycle MFO fallita');
        $stmt->bind_result($legId, $pilotId);
        $byPilot = [];
        while ($stmt->fetch()) {
            if ((int)$pilotId > 0 && (int)$legId > 0) {
                $byPilot[(int)$pilotId][(int)$legId] = true;
            }
        }
    } finally {
        $stmt->close();
    }

    $result = ['checked_pilots' => 0, 'completed' => 0, 'api_errors' => 0];
    if (!$byPilot) return $result;

    $update = $conn->prepare("UPDATE voy_mfo_slots SET status_mfo = 'COMPLETED', completed_at = UTC_TIMESTAMP() WHERE activity_id = ? AND activity_leg_id = ? AND pilot_id = ? AND status_mfo = 'BOOKED' AND completed_at IS NULL");
    if (!$update) throw new RuntimeException('UPDATE lifecycle MFO non disponibile');
    try {
        foreach ($byPilot as $pilotId => $bookedLegs) {
            try {
                $response = \Proxy\Api\Api::sendSync('GET', 'v1/activity/history/' . $activityId . '/' . $pilotId, null);
                if ($response->getStatusCode() !== 200) {
                    $result['api_errors']++;
                    error_log('[MFO activity lifecycle] Storico VaBase non disponibile per activity=' . $activityId . ' pilot=' . $pilotId . ' HTTP=' . $response->getStatusCode());
                    continue;
                }
                $history = json_decode($response->getBody());
                if (!is_object($history) || !isset($history->activityLegHistories) || !is_array($history->activityLegHistories)) {
                    $result['api_errors']++;
                    error_log('[MFO activity lifecycle] Risposta storico non valida per activity=' . $activityId . ' pilot=' . $pilotId);
                    continue;
                }
            } catch (Throwable $e) {
                $result['api_errors']++;
                error_log('[MFO activity lifecycle] Errore API: ' . get_class($e) . ': ' . $e->getMessage());
                continue;
            }
            $result['checked_pilots']++;
            foreach ($history->activityLegHistories as $legHistory) {
                    $completedLegId = (int)($legHistory->activityLegId ?? 0);
                    if ($completedLegId < 1 || !isset($bookedLegs[$completedLegId])) continue;
                    $update->bind_param('iii', $activityId, $completedLegId, $pilotId);
                    if (!$update->execute()) throw new RuntimeException('UPDATE lifecycle MFO fallito');
                    $result['completed'] += $update->affected_rows;
                }
        }
    } finally {
        $update->close();
    }
    return $result;
}

/**
 * Release cancelled VaBase BIDs only for the authenticated pilot and activity.
 * Call AFTER Activity History reconciliation so completed flights are preserved.
 * Fail closed on API errors or malformed responses.
 */
function mfo_reconcile_cancelled_bids(mysqli $conn, int $activityId, int $pilotId): array
{
    $result = ['released' => 0, 'api_errors' => 0];
    if ($activityId < 1 || $pilotId < 1) return $result;

    // Require a successful, well-formed VaBase response before changing local state.
    try {
        $response = \Proxy\Api\Api::sendSync('GET', 'v1/bids', null);
        if ($response->getStatusCode() !== 200) throw new RuntimeException('BID API HTTP ' . $response->getStatusCode());
        $bids = json_decode((string)$response->getBody(), true);
        if (!is_array($bids) || !array_is_list($bids)) throw new RuntimeException('Formato BID non valido');
        $active = [];
        foreach ($bids as $bid) {
            if (!is_array($bid)) throw new RuntimeException('Record BID non valido');
            if ((int)($bid['pilotId'] ?? 0) === $pilotId && (int)($bid['id'] ?? 0) > 0) {
                $active[(int)$bid['id']] = true;
            }
        }
    } catch (Throwable $e) {
        $result['api_errors']++;
        error_log('[MFO BID lifecycle] Riconciliazione saltata: ' . $e->getMessage());
        return $result;
    }

    $stmt = $conn->prepare("SELECT slot_id, bid_id FROM voy_mfo_slots WHERE activity_id=? AND pilot_id=? AND status_mfo='BOOKED' AND bid_id IS NOT NULL AND completed_at IS NULL");
    if (!$stmt) throw new RuntimeException('SELECT BID MFO non disponibile');
    $missing = [];
    try {
        $stmt->bind_param('ii', $activityId, $pilotId);
        if (!$stmt->execute()) throw new RuntimeException('SELECT BID MFO fallita');
        $stmt->bind_result($slotId, $bidId);
        while ($stmt->fetch()) {
            if (!isset($active[(int)$bidId])) $missing[] = [(int)$slotId, (string)$bidId];
        }
    } finally {
        $stmt->close();
    }
    if (!$missing) return $result;

    $release = $conn->prepare("UPDATE voy_mfo_slots SET status_mfo='AVAILABLE', pilot_id=NULL, callsign=NULL, bid_id=NULL, actmp_id=NULL, status_flight=NULL, booked_at=NULL WHERE slot_id=? AND activity_id=? AND pilot_id=? AND bid_id=? AND status_mfo='BOOKED' AND completed_at IS NULL");
    if (!$release) throw new RuntimeException('UPDATE BID MFO non disponibile');
    try {
        foreach ($missing as [$slotId, $bidId]) {
            $release->bind_param('iiis', $slotId, $activityId, $pilotId, $bidId);
            if (!$release->execute()) throw new RuntimeException('UPDATE BID MFO fallito');
            $result['released'] += $release->affected_rows;
        }
    } finally {
        $release->close();
    }
    return $result;
}
