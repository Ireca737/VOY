<?php
declare(strict_types=1);
require __DIR__.'/engine.php';
use MfoTest\{Engine,Vabase,Registry,ReconciliationNeeded};

final class FakeVabase implements Vabase {
    public int $nextActivity=100;
    public int $nextLeg=200;
    public array $lastActivity=[];
    public int $activityCalls=0;
    public int $legCalls=0;
    public function createActivity(array $data): int {
        $this->activityCalls++;
        $this->lastActivity=$data;
        return $this->nextActivity++;
    }
    public function createLeg(array $data): int { $this->legCalls++; return $this->nextLeg++; }
}
final class FakeRegistry implements Registry {
    public array $programs=[];
    public array $slots=[];
    public bool $failProgram=false;
    public bool $failSlot=false;
    public function addProgram(int $id): void {
        if ($this->failProgram) throw new RuntimeException('Simulated DB outage');
        if (isset($this->programs[$id])) throw new RuntimeException('Duplicate');
        $this->programs[$id]=true;
    }
    public function isProgram(int $id): bool { return isset($this->programs[$id]); }
    public function addSlot(int $activityId,int $legId): void {
        if ($this->failSlot) throw new RuntimeException('Simulated DB outage');
        if (isset($this->slots[$legId])) throw new RuntimeException('Duplicate');
        $this->slots[$legId]=['activity'=>$activityId,'status'=>'AVAILABLE'];
    }
    public function slotState(int $legId): ?string { return $this->slots[$legId]['status']??null; }
}
$tests=0;
function check(bool $condition,string $label): void {
    global $tests;
    if (!$condition) { fwrite(STDERR,"[FAIL] $label\n"); exit(1); }
    $tests++; echo "[OK] $label\n";
}
$api=new FakeVabase();
$db=new FakeRegistry();
$engine=new Engine($api,$db);
$activity=$engine->createProgram(['Title'=>'October']);
check($activity===100 && $db->isProgram(100),'Activity registered');
check($api->lastActivity['Type']==='Event' && $api->lastActivity['LegsInOrder']===false,'Fixed VaBase MFO flags');
$leg=$engine->createSlot($activity,['DepartureIcao'=>'LIRF','ArrivalIcao'=>'LIMC']);
check($leg===200 && $db->slotState($leg)==='AVAILABLE','Leg registered as AVAILABLE');
check($engine->canEditLeg($leg),'Available leg editable');
$db->slots[$leg]['status']='BOOKED';
check(!$engine->canEditLeg($leg),'Booked leg not editable');
$before=$api->legCalls;
try { $engine->createSlot(999,[]); check(false,'Non-MFO activity rejected'); }
catch (DomainException $e) { check($api->legCalls===$before,'Non-MFO activity rejected before API call'); }
$db->failProgram=true;
try { $engine->createProgram(['Title'=>'Fail']); check(false,'Program failure flagged'); }
catch (ReconciliationNeeded $e) {
    check($e->entity==='activity' && $e->id===101,'Activity orphan requires reconciliation');
}
$db->failProgram=false;
$db->failSlot=true;
try { $engine->createSlot($activity,[]); check(false,'Slot failure flagged'); }
catch (ReconciliationNeeded $e) {
    check($e->entity==='leg' && $e->id===201,'Leg orphan requires reconciliation');
}
echo "\nPASS ($tests isolated functional-model checks)\n";
