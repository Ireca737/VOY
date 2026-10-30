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
