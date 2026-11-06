<?php

declare(strict_types=1);

namespace Combodo\iTop\Service\Events {
    // Only the event payload used by the real handlers is needed here.
    class EventData
    {
        public function __construct(private array $data) {}
        public function GetEventData(): array { return $this->data; }
    }
}

namespace {
    use BR\Extension\Peripherals\Model\_Workstation;
    use BR\Extension\Peripherals\Util\PeripheralsUtils;
    use Combodo\iTop\Service\Events\EventData;

    // Minimal in-memory iTop boundary; no database or installed iTop required.
    class PhysicalDevice
    {
        public array $original = [];
        public array $changes = [];
        public int $writes = 0;
        public function __construct(public array $values = [], private int $id = 1) {}
        public function Get(string $code) { return $this->values[$code] ?? 0; }
        public function Set(string $code, $value): void { $this->values[$code] = $value; }
        public function GetKey(): int { return $this->id; }
        public function GetOriginal(string $code) { return $this->original[$code] ?? 0; }
        public function ListChanges(): array { return $this->changes; }
        public function DBUpdate(): void { $this->writes++; }
    }

    class MetaModel
    {
        public static array $settings = [];
        public static array $devices = [];
        public static bool $costCenters = false;
        public static function GetConfig(): object { return new self(); }
        public function GetModuleSetting(string $module, string $key, $default)
        {
            if ($module !== 'br-peripherals') {
                throw new RuntimeException('Unexpected configuration module.');
            }
            return self::$settings[$key] ?? $default;
        }
        public static function GetObject(string $class, int $id, bool $required = true)
        {
            return self::$devices[$id] ?? null;
        }
        public static function IsValidAttCode(string $class, string $code): bool
        {
            return $code === 'costcenter_id' && self::$costCenters;
        }
    }

    require_once __DIR__ . '/../br-peripherals/src/Util/PeripheralsUtils.php';
    require_once __DIR__ . '/../br-peripherals/src/Model/_Workstation.php';

    // Record contact operations; contact-link SQL and persistence are outside this unit test.
    class TestWorkstation extends _Workstation
    {
        public array $contacts = [];
        public function ConnectTargetToContact(int $targetId, int $contactId): void
        {
            $this->contacts[] = ['add', $targetId, $contactId];
        }
        public function DisconnectTargetFromContact(int $targetId, int $contactId): void
        {
            $this->contacts[] = ['remove', $targetId, $contactId];
        }
    }

    $expect = static function (bool $condition): void {
        if (!$condition) {
            throw new RuntimeException('Unexpected module behavior.');
        }
    };
    $fixture = static function (bool $enabled = true): array {
        MetaModel::$settings = array_fill_keys([
            'update_contacts_from_workstation', 'update_locations_from_workstation',
            'update_costcenters_from_workstation',
        ], $enabled);
        MetaModel::$costCenters = $enabled;
        $device = new PhysicalDevice(['location_id' => 2], 20);
        MetaModel::$devices = [20 => $device];
        return [new TestWorkstation(['pc_id' => 20, 'location_id' => 7, 'user_id' => 8, 'costcenter_id' => 9], 10), $device];
    };

    return [
        'module settings parse boolean strings and default to enabled' => static function () use ($expect): void {
            foreach (['GetUpdateContacts' => 'update_contacts_from_workstation', 'GetUpdateLocations' => 'update_locations_from_workstation', 'GetUpdateCostCenters' => 'update_costcenters_from_workstation'] as $method => $key) {
                MetaModel::$settings = [];
                $expect(PeripheralsUtils::$method() === true);
                foreach (['false' => false, 'true' => true, '0' => false, '1' => true] as $value => $expected) {
                    MetaModel::$settings = [$key => (string) $value];
                    $expect(PeripheralsUtils::$method() === $expected);
                }
            }
        },
        'synchronized device slots match the workstation XML model' => static function () use ($expect): void {
            $model = simplexml_load_file(__DIR__ . '/../br-peripherals/datamodel.br-peripherals.xml');
            $slots = [];
            foreach ($model->xpath('/itop_design/classes/class[@id="Workstation"]/fields/field[target_class]') as $field) {
                if ((string) $field->target_class !== 'Person') {
                    $slots[] = (string) $field['id'];
                }
            }
            $actual = PeripheralsUtils::GetPeripherals();
            sort($slots);
            sort($actual);
            $expect($slots !== [] && $actual === $slots);
        },
        'device assignment propagates location and avoids redundant writes' => static function () use ($fixture, $expect): void {
            [$workstation, $device] = $fixture();
            $workstation->ConnectTargetToWorkstation(20, true);
            $workstation->ConnectTargetToWorkstation(20, true);
            $workstation->ConnectTargetToWorkstation(0, true);
            $workstation->ConnectTargetToWorkstation(999, true);
            $expect($device->Get('workstation_id') === 10 && $device->Get('location_id') === 7 && $device->writes === 1);
        },
        'creation synchronizes assigned devices contacts and cost center' => static function () use ($fixture, $expect): void {
            [$workstation, $device] = $fixture();
            $workstation->OnWorkstationAfterWrite(new EventData(['is_new' => true]));
            $expect($device->Get('workstation_id') === 10 && $device->Get('location_id') === 7 && $device->Get('costcenter_id') === 9);
            $expect($workstation->contacts === [['add', 10, 8], ['add', 20, 8]]);
        },
        'disabled synchronization still assigns devices without optional updates' => static function () use ($fixture, $expect): void {
            [$workstation, $device] = $fixture(false);
            $workstation->OnWorkstationAfterWrite(new EventData(['is_new' => true]));
            $expect($device->Get('workstation_id') === 10 && $device->Get('location_id') === 2 && $device->Get('costcenter_id') === 0);
            $expect($workstation->contacts === []);
        },
        'replacement disconnects the old device and connects the new device' => static function () use ($fixture, $expect): void {
            [$workstation, $device] = $fixture(false);
            $old = new PhysicalDevice(['workstation_id' => 10], 21);
            MetaModel::$devices[21] = $old;
            $workstation->original = ['pc_id' => 21];
            $workstation->changes = ['pc_id' => true];
            $event = new EventData(['is_new' => false]);
            $workstation->OnWorkstationBeforeWrite($event);
            $workstation->OnWorkstationAfterWrite($event);
            $expect($old->Get('workstation_id') === 0 && $device->Get('workstation_id') === 10);
        },
        'location and cost center changes propagate to existing devices' => static function () use ($fixture, $expect): void {
            [$workstation, $device] = $fixture();
            $workstation->changes = ['location_id' => true, 'costcenter_id' => true];
            $event = new EventData(['is_new' => false]);
            $workstation->OnWorkstationBeforeWrite($event);
            $workstation->OnWorkstationAfterWrite($event);
            $expect($device->Get('location_id') === 7 && $device->Get('costcenter_id') === 9);
        },
        'missing cost center extension does not prevent device synchronization' => static function () use ($fixture, $expect): void {
            [$workstation, $device] = $fixture();
            MetaModel::$costCenters = false;
            $workstation->OnWorkstationAfterWrite(new EventData(['is_new' => true]));
            $expect($device->Get('workstation_id') === 10 && $device->Get('costcenter_id') === 0);
        },
        'deleting a workstation disconnects devices and removes user contacts' => static function () use ($fixture, $expect): void {
            [$workstation, $device] = $fixture();
            $device->Set('workstation_id', 10);
            $workstation->OnWorkstationAboutToDelete();
            $expect($device->Get('workstation_id') === 0);
            $expect($workstation->contacts === [['remove', 10, 8], ['remove', 20, 8]]);
        },
    ];
}
