<?php
/**
 * MFO payout worker. Invoke from a trusted server-side job only.
 * One pilot per invocation; never retry PROCESSING/UNCERTAIN automatically.
 * Caller supplies a VaBase credit sender: fn(array $payload): array
 * returning ['http_code'=>int,'body'=>string].
 */
function mfo_settle_one_pilot(mysqli $conn, int $settlementId, callable $sendCredits): array
{
    if ($settlementId < 1) throw new InvalidArgumentException('ID liquidazione non valido');
    $conn->query("SET time_zone = '+00:00'");
    $conn->begin_transaction();
    try {
        $stmt=$conn->prepare(
            "SELECT s.id,s.program_id,s.pilot_id,s.total_xp,s.cash_amount,s.status,
                    p.status_mfo
             FROM voy_mfo_settlements s
             JOIN voy_mfo_programs p ON p.id=s.program_id
             WHERE s.id=? FOR UPDATE"
        );
        $stmt->bind_param('i',$settlementId); $stmt->execute();
        $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$row) throw new RuntimeException('Liquidazione inesistente');
        if ($row['status']!=='PENDING' || $row['status_mfo']!=='READY') {
            $conn->commit();
            return ['status'=>$row['status'],'sent'=>false];
        }
        $stmt=$conn->prepare(
            "UPDATE voy_mfo_settlements SET status='PROCESSING', processing_at=UTC_TIMESTAMP()
             WHERE id=? AND status='PENDING'"
        );
        $stmt->bind_param('i',$settlementId); $stmt->execute();
        if ($stmt->affected_rows!==1) throw new RuntimeException('Impossibile riservare liquidazione');
        $stmt->close(); $conn->commit();
    } catch(Throwable $e) { $conn->rollback(); throw $e; }

    // The remote API has no idempotency key. From this point on, any
    // ambiguous result must remain UNCERTAIN for manual reconciliation.
    $code=null; $body=null; $error=null;
    try {
        $response=$sendCredits([
            'PilotId'=>(int)$row['pilot_id'],
            'Hours'=>null,
            'Xp'=>(int)$row['total_xp'],
            'Cash'=>(int)$row['cash_amount']
        ]);
        if (!is_array($response) || !isset($response['http_code'])) {
            throw new RuntimeException('Risposta API priva di codice HTTP');
        }
        $code=(int)$response['http_code'];
        $body=substr((string)($response['body']??''),0,60000);
    } catch(Throwable $e) { $error=substr($e->getMessage(),0,60000); }

    // Only explicit HTTP 200 means confirmed success. Other outcomes are
    // uncertain, never safe to retry without checking VaBase first.
    $status=($code===200 && $error===null)?'SETTLED':'UNCERTAIN';
    try {
        $stmt=$conn->prepare(
            "UPDATE voy_mfo_settlements
             SET status=?, settled_at=IF(?='SETTLED',UTC_TIMESTAMP(),NULL),
                 api_http_code=?,api_response=?,last_error=?
             WHERE id=? AND status='PROCESSING'"
        );
        $stmt->bind_param('ssissi',$status,$status,$code,$body,$error,$settlementId);
        $stmt->execute();
        if ($stmt->affected_rows!==1) throw new RuntimeException('Esito API non registrabile: verifica manuale necessaria');
        $stmt->close();
        if ($status==='SETTLED') {
            $programId=(int)$row['program_id'];
            $stmt=$conn->prepare(
                "UPDATE voy_mfo_programs SET status_mfo='SETTLED',settled_at=UTC_TIMESTAMP()
                 WHERE id=? AND status_mfo='READY'
                 AND NOT EXISTS (SELECT 1 FROM voy_mfo_settlements
                                 WHERE program_id=? AND status<>'SETTLED')"
            );
            $stmt->bind_param('ii',$programId,$programId); $stmt->execute(); $stmt->close();
        }
    } catch(Throwable $e) {
        error_log('[MFO payout] settlement '.$settlementId.' needs manual reconciliation: '.$e->getMessage());
        throw $e;
    }
    return ['status'=>$status,'sent'=>true,'http_code'=>$code];
}
