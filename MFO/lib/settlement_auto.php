<?php
/**
 * MFO automated close-and-pay orchestration.
 *
 * Call only from a trusted server-side lifecycle/periodic worker with a
 * verified VaBase credit sender. Not a public HTTP endpoint.
 *
 * A separate periodic invocation is REQUIRED to recover PENDING payouts if
 * a worker stops after preparing READY. PROCESSING/UNCERTAIN are never retried.
 */
require_once __DIR__ . '/settlement_prepare.php';
require_once __DIR__ . '/settlement_pay.php';

function mfo_run_program_settlement(mysqli $conn, int $programId, callable $sendCredits): array
{
    if ($programId < 1) throw new InvalidArgumentException('Programma non valido');

    $stmt=$conn->prepare('SELECT status_mfo FROM voy_mfo_programs WHERE id=?');
    $stmt->bind_param('i',$programId); $stmt->execute();
    $program=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$program) throw new RuntimeException('Programma inesistente');

    if ($program['status_mfo']==='OPEN') {
        // Readiness failures are normal while flights are still ongoing.
        // This operation never sends credits until preparation succeeds.
        mfo_prepare_program_settlement($conn,$programId);
    } elseif ($program['status_mfo']!=='READY') {
        return ['program_id'=>$programId,'status'=>$program['status_mfo'],'payments'=>[]];
    }

    $stmt=$conn->prepare(
        "SELECT id FROM voy_mfo_settlements
         WHERE program_id=? AND status='PENDING' ORDER BY id"
    );
    $stmt->bind_param('i',$programId); $stmt->execute();
    $ids=array_map('intval',array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC),'id'));
    $stmt->close();

    $payments=[];
    foreach ($ids as $id) {
        try {
            $payments[$id]=mfo_settle_one_pilot($conn,$id,$sendCredits);
        } catch (Throwable $e) {
            // A DB failure after an API request may mean credit was applied.
            // Never re-send it here; continue with other pilots.
            error_log('[MFO orchestration] settlement '.$id.': '.$e->getMessage());
            $payments[$id]=['status'=>'CHECK_REQUIRED','sent'=>'unknown'];
        }
    }
    $stmt=$conn->prepare('SELECT status_mfo FROM voy_mfo_programs WHERE id=?');
    $stmt->bind_param('i',$programId); $stmt->execute();
    $status=$stmt->get_result()->fetch_assoc()['status_mfo']; $stmt->close();
    return ['program_id'=>$programId,'status'=>$status,'payments'=>$payments];
}

/** Find all programs with pending payouts, for an authenticated scheduled job. */
function mfo_resume_pending_settlements(mysqli $conn, callable $sendCredits): array
{
    $result=$conn->query(
        "SELECT DISTINCT p.id FROM voy_mfo_programs p
         JOIN voy_mfo_settlements s ON s.program_id=p.id
         WHERE p.status_mfo='READY' AND s.status='PENDING' ORDER BY p.id"
    );
    $out=[];
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
        $id=(int)$row['id'];
        $out[$id]=mfo_run_program_settlement($conn,$id,$sendCredits);
    }
    return $out;
}

/**
 * Daily payout-only runner: prepare completed OPEN programs, then pay READY.
 * ACARS collection is deliberately out of scope.
 * Errors on one program must not block other programs.
 */
function mfo_run_daily_payouts(mysqli $conn, callable $sendCredits): array
{
    $result = $conn->query(
        "SELECT p.id
         FROM voy_mfo_programs p
         WHERE p.status_mfo='READY'
            OR (p.status_mfo='OPEN'
                AND EXISTS (SELECT 1 FROM voy_mfo_slots s
                            WHERE s.activity_id=p.activity_id AND s.status_mfo='COMPLETED')
                AND NOT EXISTS (SELECT 1 FROM voy_mfo_slots s
                                WHERE s.activity_id=p.activity_id
                                  AND s.status_mfo NOT IN ('COMPLETED','OBSOLETE')))
         ORDER BY p.id"
    );
    $out = [];
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
        $id = (int)$row['id'];
        try {
            $out[$id] = mfo_run_program_settlement($conn, $id, $sendCredits);
        } catch (Throwable $e) {
            error_log('[MFO daily payout] program '.$id.': '.$e->getMessage());
            $out[$id] = ['status'=>'CHECK_REQUIRED','error'=>'Consultare error_log'];
        }
    }
    return $out;
}
