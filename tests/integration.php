<?php

declare(strict_types=1);

use Combodo\iTop\Setup\ModuleDependency\DependencyExpression;
use Combodo\iTop\Setup\ModuleDiscovery\ModuleFileReader;

if (getenv('ITOP_TEST_MODE') !== '1') {
    throw new RuntimeException('Run only in a disposable iTop test environment.');
}

require '/var/www/html/approot.inc.php';
require_once APPROOT.'core/config.class.inc.php';
$config = new Config(APPROOT.'conf/production/config-itop.php');
$database = [$config->Get('db_host'), $config->Get('db_name'), $config->Get('db_user')];
if (!in_array($database, [['test-db', 'itop_test', 'itop_test'], ['db', 'itop_peripherals_fixture', 'itop']], true)) {
    throw new RuntimeException('Refusing to use a database outside the isolated test fixtures.');
}
require APPROOT.'application/startup.inc.php';

function check(bool $expected, string $message): void
{
    if (!$expected) {
        throw new RuntimeException($message);
    }
}

$adminPassword = getenv('ITOP_TEST_ADMIN_PASSWORD') ?: getenv('ITOP_ADMIN_PASSWORD');
check(is_string($adminPassword) && UserRights::CheckCredentials('admin', $adminPassword)
    && UserRights::Login('admin') && UserRights::IsAdministrator(), 'Could not authenticate the fixture administrator.');

$expectCostCenter = in_array('--costcenter', $argv, true);
$expectTeemIp = in_array('--teemip', $argv, true);
$modules = GetModulesInfo();
check(isset($modules['br-peripherals']), 'The peripherals module was not installed.');
check(isset($modules['br-costcenter-bridge-for-br-peripherals']) === $expectCostCenter, 'Unexpected cost center bridge selection.');
check(isset($modules['br-peripherals-bridge-for-teemip-ip-mgmt']) === $expectTeemIp, 'Unexpected TeemIP bridge selection.');

require_once APPROOT.'setup/modulediscovery/ModuleFileReader.php';
require_once APPROOT.'setup/moduledependency/dependencyexpression.class.inc.php';
$root = dirname(__DIR__);
$manifest = simplexml_load_file($root.'/extension.xml', SimpleXMLElement::class, LIBXML_NONET);
$bridge = 'br-costcenter-bridge-for-br-peripherals';
$bridgeModule = ModuleFileReader::GetInstance()->ReadModuleFileInformation($root."/$bridge/module.$bridge.php");
$costBridge = $bridgeModule[ModuleFileReader::MODULE_INFO_CONFIG];
foreach ([null, '3.2.5', '3.3.0'] as $version) {
    $versions = ['br-peripherals' => trim((string) $manifest->version)];
    if ($version !== null) {
        $versions['br-costcenter'] = $version;
    }
    $resolved = true;
    foreach ($costBridge['dependencies'] as $requirement) {
        $dependency = new DependencyExpression($requirement);
        $dependency->UpdateModuleResolutionState($versions, $versions);
        $resolved = $resolved && $dependency->IsResolved();
    }
    check($resolved === ($version !== '3.2.5'), 'The cost center bridge did not enforce its optional minimum version.');
}

foreach (['PC', 'Printer', 'MobilePhone', 'IPPhone', 'Monitor'] as $class) {
    $attribute = MetaModel::GetAttributeDef($class, 'workstation_id');
    check($attribute->GetTargetClass() === 'Workstation', "$class does not link back to Workstation.");
    $details = MetaModel::FlattenZList(MetaModel::GetZListItems($class, 'details'));
    check(count(array_keys($details, 'workstation_id', true)) === 1, "$class must display one workstation field.");
    if ($class === 'MobilePhone' || $class === 'IPPhone') {
        foreach (['phonenumber', 'model_end_of_support', 'groups_list'] as $field) {
            check(in_array($field, $details, true), "$class lost its core $field field.");
        }
        if ($class === 'MobilePhone') {
            check(in_array('imei', $details, true) && in_array('hw_pin', $details, true), 'Mobile phone identifiers are missing.');
        }
        foreach (['macaddress', 'ipaddress_id'] as $field) {
            check(count(array_keys($details, $field, true)) === (int) $expectTeemIp, "$class has unexpected $field presentation.");
        }
    }
}

if ($expectCostCenter) {
    foreach (['Workstation', 'Monitor', 'MobilePhone', 'IPPhone'] as $class) {
        foreach (['details', 'standard_search'] as $list) {
            $fields = MetaModel::FlattenZList(MetaModel::GetZListItems($class, $list));
            check(count(array_keys($fields, 'costcenter_id', true)) === 1, "$class must display one cost center in $list.");
        }
    }
}

if ($expectTeemIp) {
    foreach (['MobilePhone', 'IPPhone', 'DockingStation', 'ConferenceSystem'] as $class) {
        $mac = MetaModel::GetAttributeDef($class, 'macaddress');
        $ip = MetaModel::GetAttributeDef($class, 'ipaddress_id');
        check($mac instanceof AttributeMacAddress && $ip->GetTargetClass() === 'IPAddress', "$class has invalid TeemIP attributes.");
        $details = MetaModel::FlattenZList(MetaModel::GetZListItems($class, 'details'));
        foreach (['macaddress', 'ipaddress_id'] as $field) {
            check(count(array_keys($details, $field, true)) === 1, "$class must display one $field.");
        }
    }
}

function insertFixture(string $class, array $attributes): int
{
    $object = MetaModel::NewObject($class, $attributes);
    $object->DBInsert();
    return (int) $object->GetKey();
}

function hasContactLink(int $contact, int $device): bool
{
    $links = new DBObjectSet(DBObjectSearch::FromOQL(
        'SELECT lnkContactToFunctionalCI WHERE contact_id = :contact AND functionalci_id = :device',
        ['contact' => $contact, 'device' => $device]
    ));
    return $links->Count() === 1;
}

$prefix = 'Peripherals fixture '.bin2hex(random_bytes(4));
CMDBSource::Query('START TRANSACTION');
try {
    $org = insertFixture('Organization', ['name' => $prefix, 'status' => 'active']);
    $common = ['org_id' => $org];
    $location = insertFixture('Location', $common + ['name' => "$prefix location"]);
    $user = insertFixture('Person', $common + ['name' => "$prefix user", 'first_name' => 'Test']);
    $pc = insertFixture('PC', $common + ['name' => "$prefix PC"]);
    $monitor = insertFixture('Monitor', $common + ['name' => "$prefix monitor"]);
    $replacement = insertFixture('Monitor', $common + ['name' => "$prefix spare"]);
    $attributes = $common + [
        'name' => "$prefix workstation", 'pc_id' => $pc, 'monitorA_id' => $monitor,
        'user_id' => $user, 'location_id' => $location,
    ];
    if ($expectCostCenter) {
        $attributes['costcenter_id'] = insertFixture('CostCenter', $common + ['name' => "$prefix cost center", 'code' => 'FIXTURE']);
    }
    if ($expectTeemIp) {
        $ip = insertFixture('IPv4Address', $common + ['ip' => '192.0.2.73', 'status' => 'unassigned']);
        $dock = insertFixture('DockingStation', $common + [
            'name' => "$prefix dock", 'macaddress' => '02-00-00-00-00-01', 'ipaddress_id' => $ip,
        ]);
        $device = MetaModel::GetObject('DockingStation', $dock);
        check($device->Get('macaddress') === '02:00:00:00:00:01', 'The docking station MAC did not persist.');
        check((int) $device->Get('ipaddress_id') === $ip, 'The docking station IP address did not persist.');
    }
    $workstation = insertFixture('Workstation', $attributes);
    foreach (['PC' => $pc, 'Monitor' => $monitor] as $class => $id) {
        $device = MetaModel::GetObject($class, $id);
        check((int) $device->Get('workstation_id') === $workstation, "$class assignment did not persist.");
        check((int) $device->Get('location_id') === $location, "$class did not inherit the location.");
        check(hasContactLink($user, $id), "$class did not inherit the contact.");
        if ($expectCostCenter) {
            check((int) $device->Get('costcenter_id') === $attributes['costcenter_id'], "$class did not inherit the cost center.");
        }
    }

    $object = MetaModel::GetObject('Workstation', $workstation);
    $object->Set('monitorA_id', $replacement);
    $object->DBUpdate();
    check((int) MetaModel::GetObject('Monitor', $monitor)->Get('workstation_id') === 0, 'The original monitor is still linked.');
    $device = MetaModel::GetObject('Monitor', $replacement);
    check((int) $device->Get('workstation_id') === $workstation, 'The replacement monitor was not linked.');
    if ($expectCostCenter) {
        check((int) $device->Get('costcenter_id') === $attributes['costcenter_id'], 'The replacement monitor did not inherit the cost center.');
        $newCostCenter = insertFixture('CostCenter', $common + ['name' => "$prefix new cost center", 'code' => 'FIXTURE2']);
        $object->Set('costcenter_id', $newCostCenter);
        $object->DBUpdate();
        check((int) MetaModel::GetObject('PC', $pc)->Get('costcenter_id') === $newCostCenter, 'A cost center change did not propagate.');
    }
    $object->DBDelete();
    check((int) MetaModel::GetObject('PC', $pc)->Get('workstation_id') === 0, 'Workstation deletion did not clear the PC assignment.');
    check(!hasContactLink($user, $pc), 'Workstation deletion did not clear the PC contact.');
} finally {
    CMDBSource::Query('ROLLBACK');
}

echo "Installed module selection, device layouts, and workstation persistence passed.\n";
