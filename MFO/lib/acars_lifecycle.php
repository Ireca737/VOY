<?php
/**
 * VOY MFO — ACARS lifecycle reconciliation.
 *
 * Reusable module extracted from FT-02 after the 25/10/2026 flight test.
 * It does not poll VaBase by itself: callers provide the already decoded
 * v1/bids and v1/map/acars payloads plus the authenticated pilot ID.
 *
 * Validated completion rule:
 *   BOOKED + matching BID + same ACARS attempt + statStage=Disembarking
 *   -> COMPLETED + completed_at (UTC)
 *
 * The update is idempotent and never reassigns a different actmp_id.
 */

function mfo_reconcile_acars_lifecycle(
    mysqli $conn,
    int $pilotId,
    array $bids,
    array $acars
): array {
    if ($pilotId < 1) {
        throw new InvalidArgumentException('Pilot ID non valido');
    }

    $matches = [];

    foreach ($acars as $flight) {
        if (!is_array($flight) || (int)($flight['pilotId'] ?? 0) !== $pilotId) {
            continue;
        }

        $attempt = trim((string)($flight['actmpId'] ?? ''));
        $flightNumber = trim((string)($flight['flightNumber'] ?? ''));
        $stage = trim((string)($flight['statStage'] ?? ''));

        if ($attempt === '' || $flightNumber === '' || $stage === '') {
            continue;
        }

        foreach ($bids as $bid) {
            if (!is_array($bid)
                || (int)($bid['pilotId'] ?? 0) !== $pilotId
                || trim((string)($bid['flightNumber'] ?? '')) !== $flightNumber
                || (string)($bid['bidType'] ?? '') !== 'activity'
                || (int)($bid['activityLegId'] ?? 0) < 1
                || (int)($bid['id'] ?? 0) < 1) {
                continue;
            }

            $matches[] = [
                'bid_id' => (int)$bid['id'],
                'activity_leg_id' => (int)$bid['activityLegId'],
                'flight_number' => $flightNumber,
                'actmp_id' => $attempt,
                'stage' => $stage,
            ];
        }
    }

    $conn->query("SET time_zone = '+00:00'");

    $result = [];

    foreach ($matches as $match) {
        $isCompleted = $match['stage'] === 'Disembarking';

        $sql = $isCompleted
            ? "UPDATE voy_mfo_slots
               SET actmp_id=?,
                   status_flight=?,
                   status_mfo='COMPLETED',
                   completed_at=UTC_TIMESTAMP()
               WHERE activity_leg_id=?
                 AND pilot_id=?
                 AND bid_id=?
                 AND status_mfo='BOOKED'
                 AND completed_at IS NULL
                 AND (actmp_id IS NULL OR actmp_id=?)"
            : "UPDATE voy_mfo_slots
               SET actmp_id=?,
                   status_flight=?
               WHERE activity_leg_id=?
                 AND pilot_id=?
                 AND bid_id=?
                 AND status_mfo='BOOKED'
                 AND completed_at IS NULL
                 AND (actmp_id IS NULL OR actmp_id=?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare lifecycle MFO fallita');
        }

        $legId = $match['activity_leg_id'];
        $bidId = $match['bid_id'];

        try {
            $stmt->bind_param(
                'ssiiis',
                $match['actmp_id'],
                $match['stage'],
                $legId,
                $pilotId,
                $bidId,
                $match['actmp_id']
            );

            if (!$stmt->execute()) {
                throw new RuntimeException('UPDATE lifecycle MFO fallito');
            }

            $result[] = [
                'flight' => $match['flight_number'],
                'stage' => $match['stage'],
                'actmpId' => $match['actmp_id'],
                'completed' => $isCompleted,
                'rows' => $stmt->affected_rows,
            ];
        } finally {
            $stmt->close();
        }
    }

    return $result;
}
