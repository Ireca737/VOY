<?php
/**
 * VOY MFO - preparation of a program settlement (NO VaBase API calls).
 *
 * Caller must supply an authenticated/authorized administrator context.
 * All timestamps are UTC. Requires InnoDB and mysqli with exceptions enabled.
 */

function mfo_prepare_program_settlement(mysqli $conn, int $programId): array
{
    if ($programId < 1) {
        throw new InvalidArgumentException('ID programma MFO non valido');
    }

    $conn->query("SET time_zone = '+00:00'");
    $conn->begin_transaction();

    try {
        // Serializes preparation for the same program.
        $stmt = $conn->prepare(
            'SELECT id, activity_id, status_mfo, cash_prize
             FROM voy_mfo_programs WHERE id=? FOR UPDATE'
        );
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $program = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$program) {
            throw new RuntimeException('Programma MFO inesistente');
        }

        if ($program['status_mfo'] === 'READY') {
            // Read-only idempotent result: never recompute an already frozen settlement.
            $stmt = $conn->prepare(
                'SELECT pilot_id, total_xp, cash_amount, status, reference_code
                 FROM voy_mfo_settlements WHERE program_id=? ORDER BY pilot_id'
            );
            $stmt->bind_param('i', $programId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            if (!$rows) {
                throw new RuntimeException('Programma READY senza liquidazioni: verifica manuale necessaria');
            }
            $conn->commit();
            return ['status' => 'READY', 'already_prepared' => true, 'settlements' => $rows];
        }
        if ($program['status_mfo'] !== 'OPEN') {
            throw new RuntimeException('Preparazione non consentita nello stato ' . $program['status_mfo']);
        }

        // Lock slot rows so concurrent lifecycle updates cannot change the snapshot
        // while the settlement is being assembled.
        $stmt = $conn->prepare(
            'SELECT slot_id, pilot_id, status_mfo, bonus_xp
             FROM voy_mfo_slots WHERE activity_id=? ORDER BY slot_id FOR UPDATE'
        );
        $activityId = (int)$program['activity_id'];
        $stmt->bind_param('i', $activityId);
        $stmt->execute();
        $slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (!$slots) {
            throw new RuntimeException('Nessuno slot associato al programma');
        }

        $xpByPilot = [];
        foreach ($slots as $slot) {
            $status = (string)$slot['status_mfo'];
            if ($status === 'OBSOLETE') {
                continue;
            }
            if ($status !== 'COMPLETED') {
                throw new RuntimeException('Programma non concluso: slot ' . $slot['slot_id'] . ' in stato ' . $status);
            }
            $pilot = (int)$slot['pilot_id'];
            if ($pilot < 1 || $slot['bonus_xp'] === null || !ctype_digit((string)$slot['bonus_xp'])) {
                throw new RuntimeException('Slot COMPLETED privo di pilota o XP validi: ' . $slot['slot_id']);
            }
            $xp = (int)$slot['bonus_xp'];
            if ($xp > 4294967295) {
                throw new RuntimeException('XP fuori intervallo: slot ' . $slot['slot_id']);
            }
            $xpByPilot[$pilot] = ($xpByPilot[$pilot] ?? 0) + $xp;
            if ($xpByPilot[$pilot] > 4294967295) {
                throw new RuntimeException('XP cumulati fuori intervallo: pilota ' . $pilot);
            }
        }
        if (!$xpByPilot) {
            throw new RuntimeException('Nessuna tratta COMPLETED: nessun premio da liquidare');
        }

        // A settlement must not be regenerated if records already exist.
        $stmt = $conn->prepare('SELECT COUNT(*) AS n FROM voy_mfo_settlements WHERE program_id=?');
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        $existing = (int)$stmt->get_result()->fetch_assoc()['n'];
        $stmt->close();
        if ($existing !== 0) {
            throw new RuntimeException('Liquidazioni preesistenti su programma OPEN: verifica manuale necessaria');
        }

        $cashPrize = (int)$program['cash_prize'];
        $cashPerPilot = (int)round($cashPrize / count($xpByPilot), 0, PHP_ROUND_HALF_UP);
        ksort($xpByPilot, SORT_NUMERIC);
        $insert = $conn->prepare(
            "INSERT INTO voy_mfo_settlements
                (program_id, pilot_id, total_xp, cash_amount, status, reference_code)
             VALUES (?, ?, ?, ?, 'PENDING', ?)"
        );
        $rows = [];
        foreach ($xpByPilot as $pilot => $xp) {
            $reference = 'MFO-' . $programId . '-P' . $pilot;
            $insert->bind_param('iiiis', $programId, $pilot, $xp, $cashPerPilot, $reference);
            $insert->execute();
            $rows[] = [
                'pilot_id' => $pilot,
                'total_xp' => $xp,
                'cash_amount' => $cashPerPilot,
                'status' => 'PENDING',
                'reference_code' => $reference,
            ];
        }
        $insert->close();

        $stmt = $conn->prepare(
            "UPDATE voy_mfo_programs SET status_mfo='READY', ready_at=UTC_TIMESTAMP()
             WHERE id=? AND status_mfo='OPEN'"
        );
        $stmt->bind_param('i', $programId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('Impossibile portare il programma in READY');
        }
        $stmt->close();
        $conn->commit();

        return ['status' => 'READY', 'already_prepared' => false,
                'cash_prize' => $cashPrize, 'cash_per_pilot' => $cashPerPilot,
                'settlements' => $rows];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
