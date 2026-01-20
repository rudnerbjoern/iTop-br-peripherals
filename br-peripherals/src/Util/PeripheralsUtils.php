<?php

/**
 * @copyright   Copyright (C) 2024-2026 Björn Rudner
 * @license     https://www.gnu.org/licenses/agpl-3.0.en.html
 * @version     2026-01-16
 */

namespace BR\Extension\Peripherals\Util;

use MetaModel;

/**
 * Utility helper for the br-peripherals extension.
 *
 * This class centralizes:
 * - Access to module settings (feature toggles)
 * - The canonical list of workstation peripheral attribute codes
 *
 * Design notes:
 * - The class is `final` and has a private constructor to prevent instantiation and extension.
 * - All methods are static because the class only provides pure helpers / configuration access.
 */
final class PeripheralsUtils
{
    /**
     * iTop module code as used in `GetModuleSetting()`.
     * Keep this in sync with your extension name in the manifest.
     */
    private const MODULE = 'br-peripherals';

    /**
     * Prevent instantiation.
     */
    private function __construct() {}

    /**
     * Whether to propagate a workstation's location to its assigned peripherals.
     *
     * Reads the module setting `update_locations_from_workstation`.
     * The setting value is interpreted as a boolean (supports "true"/"false", "1"/"0", etc.).
     *
     * @return bool True if location updates are enabled, otherwise false.
     */
    public static function GetUpdateLocations(): bool
    {
        return filter_var(
            MetaModel::GetConfig()->GetModuleSetting(self::MODULE, 'update_locations_from_workstation', 'true'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Whether to maintain Contact-to-CI links for workstation and peripherals.
     *
     * Reads the module setting `update_contacts_from_workstation`.
     * If enabled, the code will create/remove lnkContactToFunctionalCI entries for:
     * - Workstation <-> User (contact)
     * - Peripheral <-> User (contact)
     *
     * @return bool True if contact updates are enabled, otherwise false.
     */
    public static function GetUpdateContacts(): bool
    {
        return filter_var(
            MetaModel::GetConfig()->GetModuleSetting(self::MODULE, 'update_contacts_from_workstation', 'true'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Whether to propagate a workstation's cost center to its assigned peripherals.
     *
     * Reads the module setting `update_costcenters_from_workstation`.
     * Note: This only has an effect if:
     * - Your Workstation class has a `costcenter_id` attribute, and
     * - The target class supports a `costcenter_id` attribute (often via a separate extension).
     *
     * @return bool True if cost center propagation is enabled, otherwise false.
     */
    public static function GetUpdateCostCenters(): bool
    {
        return filter_var(
            MetaModel::GetConfig()->GetModuleSetting(self::MODULE, 'update_costcenters_from_workstation', 'true'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Returns the list of attribute codes on the Workstation that represent peripherals.
     *
     * These are the attribute codes the extension will treat as "assigned devices"
     * and synchronize to (e.g. set workstation_id on the referenced PhysicalDevice).
     *
     * IMPORTANT:
     * - Keep this list in sync with your Workstation data model attributes.
     * - The codes must match exactly (case-sensitive).
     *
     * @return string[] List of Workstation attribute codes that store peripheral references.
     */
    public static function GetPeripherals(): array
    {
        return [
            'pc_id',
            'pcB_id',
            'monitorA_id',
            'monitorB_id',
            'dockingstation_id',
            'keyboard_id',
            'mouse_id',
            'headset_id',
            'mobilephone_id',
            'telephonyA_id',
            'telephonyB_id',
            'printerA_id',
            'printerB_id',
            'scanner_id',
            'barcodescanner_id',
        ];
    }
}
