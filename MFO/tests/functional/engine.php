<?php
declare(strict_types=1);
namespace MfoTest;

/**
 * Isolated model of the MFO/VaBase coordination protocol.
 * No includes from admin/, no network, no database connections.
 * These interfaces represent adapters to be implemented for production.
 */
interface Vabase {
    public function createActivity(array $data): int;
    public function createLeg(array $data): int;
}
interface Registry {
    public function addProgram(int $activityId): void;
    public function isProgram(int $activityId): bool;
    public function addSlot(int $activityId, int $legId): void;
    public function slotState(int $legId): ?string;
}
final class ReconciliationNeeded extends \RuntimeException {
    public function __construct(public readonly string $entity, public readonly int $id, \Throwable $previous) {
        parent::__construct("VaBase created $entity #$id but local registry failed; do not retry blindly", 0, $previous);
    }
}
final class Engine {
    public function __construct(private Vabase $api, private Registry $registry) {}
    public function createProgram(array $input): int {
        $input['Type']='Event';
        $input['LegsInOrder']=false;
        $id=$this->api->createActivity($input);
        if ($id <= 0) throw new \RuntimeException('Invalid Activity ID');
        try { $this->registry->addProgram($id); }
        catch (\Throwable $e) { throw new ReconciliationNeeded('activity',$id,$e); }
        return $id;
    }
    public function createSlot(int $activityId, array $input): int {
        if (!$this->registry->isProgram($activityId)) throw new \DomainException('Not an MFO program');
        $input['ActivityId']=$activityId;
        $id=$this->api->createLeg($input);
        if ($id <= 0) throw new \RuntimeException('Invalid leg ID');
        try { $this->registry->addSlot($activityId,$id); }
        catch (\Throwable $e) { throw new ReconciliationNeeded('leg',$id,$e); }
        return $id;
    }
    public function canEditLeg(int $legId): bool {
        return $this->registry->slotState($legId)==='AVAILABLE';
    }
}
