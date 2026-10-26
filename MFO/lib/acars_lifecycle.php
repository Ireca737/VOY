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
 * On Disembarking, a caller-supplied resolver provides the theoretical distance
 * according to the existing VOY get_distance() convention.
 */

function mfo_reconcile_acars_lifecycle(
    mysqli $conn,
    int $pilotId,
    array $bids,
    array $acars,
    ?callable $distanceResolver = null,
    ?callable $onProgramCompleted = null
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

        $distanceNm = null;
        $bonusXp = null;
        if ($isCompleted) {
            // Do not fetch VaBase leg data for already-closed or unrelated slots.
            $check = $conn->prepare("SELECT slot_id FROM voy_mfo_slots
                WHERE activity_leg_id=? AND pilot_id=? AND bid_id=?
                  AND status_mfo='BOOKED' AND completed_at IS NULL
                  AND (actmp_id IS NULL OR actmp_id=?) LIMIT 1");
            if (!$check) throw new RuntimeException('Prepare verifica slot MFO fallita');
            $check->bind_param('iiis', $match['activity_leg_id'], $pilotId, $match['bid_id'], $match['actmp_id']);
            try {
                if (!$check->execute()) throw new RuntimeException('Verifica slot MFO fallita');
                $check->store_result();
                $eligible = $check->num_rows === 1;
            } finally {
                $check->close();
            }
            if (!$eligible) continue;
            if ($distanceResolver === null) {
                throw new RuntimeException('Resolver distanza MFO non configurato');
            }
            $distanceNm = $distanceResolver($match['activity_leg_id']);
            if (!is_numeric($distanceNm) || !is_finite((float)$distanceNm)
                || (float)$distanceNm < 0 || (float)$distanceNm > 999999999.9) {
                throw new RuntimeException('Distanza teorica MFO non valida');
            }
            $distanceNm = round((float)$distanceNm, 1);
            $bonusXp = (int)floor($distanceNm / 10);
        }

        $sql = $isCompleted
            ? "UPDATE voy_mfo_slots
               SET actmp_id=?,
                   status_flight=?,
                   status_mfo='COMPLETED',
                   completed_at=UTC_TIMESTAMP(),
                   distance_nm=?,
                   bonus_xp=?
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
            if ($isCompleted) {
                $stmt->bind_param(
                    'ssdiiiis',
                    $match['actmp_id'],
                    $match['stage'],
                    $distanceNm,
                    $bonusXp,
                    $legId,
                    $pilotId,
                    $bidId,
                    $match['actmp_id']
                );
            } else {
                $stmt->bind_param(
                    'ssiiis',
                    $match['actmp_id'],
                    $match['stage'],
                    $legId,
                    $pilotId,
                    $bidId,
                    $match['actmp_id']
                );
            }

            if (!$stmt->execute()) {
                throw new RuntimeException('UPDATE lifecycle MFO fallito');
            }

            $result[] = [
                'activity_leg_id' => $legId,
                'flight' => $match['flight_number'],
                'stage' => $match['stage'],
                'actmpId' => $match['actmp_id'],
                'completed' => $isCompleted && $stmt->affected_rows === 1,
                'distance_nm' => $isCompleted && $stmt->affected_rows === 1 ? $distanceNm : null,
                'bonus_xp' => $isCompleted && $stmt->affected_rows === 1 ? $bonusXp : null,
                'rows' => $stmt->affected_rows,
            ];
        } finally {
            $stmt->close();
        }
    }

    // Optional production hook: invoked only when a newly completed slot is
    // the last unfinished slot in its program. The caller supplies the trusted
    // settlement orchestrator. FT-02 passes no hook and remains unchanged.
    if ($onProgramCompleted !== null) {
        $completedLegIds = [];
        foreach ($result as $item) {
            if ($item['completed']) {
                $completedLegIds[(int)$item['activity_leg_id']] = true;
            }
        }
        foreach (array_keys($completedLegIds) as $legId) {
            $check = $conn->prepare(
                "SELECT p.id FROM voy_mfo_programs p
                 JOIN voy_mfo_slots s ON s.activity_id=p.activity_id
                 WHERE s.activity_leg_id=? AND p.status_mfo='OPEN'
                   AND EXISTS (SELECT 1 FROM voy_mfo_slots c
                               WHERE c.activity_id=p.activity_id AND c.status_mfo='COMPLETED')
                   AND NOT EXISTS (SELECT 1 FROM voy_mfo_slots x
                                   WHERE x.activity_id=p.activity_id
                                     AND x.status_mfo NOT IN ('COMPLETED','OBSOLETE'))
                 LIMIT 1"
            );
            $check->bind_param('i', $legId);
            $check->execute();
            $ready = $check->get_result()->fetch_assoc();
            $check->close();
            if ($ready) {
                $onProgramCompleted((int)$ready['id']);
            }
        }
    }

    return $result;
}
