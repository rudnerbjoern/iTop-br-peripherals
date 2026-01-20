<?php

/**
 * @copyright   Copyright (C) 2024-2026 Björn Rudner
 * @license     https://www.gnu.org/licenses/agpl-3.0.en.html
 * @version     2026-01-16
 */

namespace BR\Extension\Peripherals\Model;

use BR\Extension\Peripherals\Util\PeripheralsUtils;
use PhysicalDevice;
use Combodo\iTop\Service\Events\EventData;
use MetaModel;
use UserRights;
use DBSearch;
use DBObjectSet;

/**
 * Workstation extension class.
 *
 * Purpose:
 * - Automatically set default org on creation (based on logged-in user)
 * - Maintain relationships between a Workstation and assigned peripherals:
 *   - workstation_id on peripherals
 *   - (optional) propagate location_id
 *   - (optional) propagate costcenter_id
 *   - (optional) maintain lnkContactToFunctionalCI between user and workstation/peripherals
 *
 * Notes about flags:
 * - During BeforeWrite we analyze what changed and store it in boolean properties.
 * - During AfterWrite we apply changes to related objects based on those flags.
 * - Using declared properties avoids PHP 8.2+ dynamic-property deprecation warnings.
 */
class _Workstation extends PhysicalDevice
{

    /**
     * Change-tracking flags (set in BeforeWrite, consumed in AfterWrite).
     * These are intentionally "local memory" only; they are NOT persisted.
     */
    protected bool $bUserChanged = false;

    /** @var array<string,bool> */
    protected array $aChangedFlags = [];

    protected bool $bLocationChanged = false;
    protected bool $bCostCenterChanged = false;

    /**
     * Prefill creation form values.
     *
     * This runs when creating a new Workstation record. We keep the parent behavior,
     * then set org_id to the organization of the currently authenticated iTop user.
     *
     * @param array $aContextParam iTop context parameters (passed by reference)
     */
    public function PrefillCreationForm(&$aContextParam)
    {
        // Keep the standard iTop behavior first (important for compatibility)
        parent::PrefillCreationForm($aContextParam);

        // Get the currently authenticated iTop user object (may be null in some contexts)
        $oUser = UserRights::GetUserObject();
        if ($oUser !== null) {
            // Assign the user's organization to the record
            $this->Set('org_id', (int) $oUser->Get('org_id'));
        }
    }

    /**
     * Connect a target device to this workstation.
     *
     * - Sets workstation_id on the target device
     * - Optionally propagates location_id
     * - Minimizes DB writes by updating only when data actually changes
     *
     * @param int  $targetId       ID of the target PhysicalDevice to connect
     * @param bool $updateLocation If true, propagate this workstation's location_id to the target
     */
    public function ConnectTargetToWorkstation(int $targetId, bool $updateLocation): void
    {
        // Guard against invalid keys
        if ($targetId === 0) {
            return;
        }

        // Fetch target object; "false" => no exception if not found
        $oTargetObject = MetaModel::GetObject('PhysicalDevice', $targetId, false);
        if (!$oTargetObject) {
            return;
        }

        // Track whether we need to perform a DB update
        $bDoUpdate = false;

        // Ensure the target points to this workstation
        if ((int) $oTargetObject->Get('workstation_id') !== (int) $this->GetKey()) {
            $oTargetObject->Set('workstation_id', $this->GetKey());
            $bDoUpdate = true;
        }

        // Optionally propagate location to the target
        if ($updateLocation && (int) $oTargetObject->Get('location_id') !== (int) $this->Get('location_id')) {
            $oTargetObject->Set('location_id', $this->Get('location_id'));
            $bDoUpdate = true;
        }

        // Persist only if something changed
        if ($bDoUpdate) {
            $oTargetObject->DBUpdate();
        }
    }

    /**
     * Disconnect a target device from any workstation.
     *
     * - Sets workstation_id to 0 on the target device
     *
     * @param int $targetId ID of the target PhysicalDevice to disconnect
     */
    public function DisconnectTargetFromWorkstation(int $targetId): void
    {
        // Guard against invalid keys
        if ($targetId === 0) {
            return;
        }

        $oTargetObject = MetaModel::GetObject('PhysicalDevice', $targetId, false);
        if (!$oTargetObject) {
            return;
        }

        // Remove workstation assignment
        $oTargetObject->Set('workstation_id', 0);
        $oTargetObject->DBUpdate();
    }

    /**
     * Ensure a contact link exists between a contact and a target FunctionalCI.
     *
     * This creates an lnkContactToFunctionalCI entry if it does not already exist.
     *
     * @param int $targetId  FunctionalCI ID (workstation or peripheral)
     * @param int $contactId Contact ID
     */
    public function ConnectTargetToContact(int $targetId, int $contactId): void
    {
        if ($targetId === 0 || $contactId === 0) {
            return;
        }

        // Search for an existing link
        $oSearch = DBSearch::FromOQL('SELECT lnkContactToFunctionalCI WHERE contact_id = :contact AND functionalci_id = :functionalci');

        $oSet = new DBObjectSet($oSearch, [], [
            'contact' => $contactId,
            'functionalci' => $targetId
        ]);

        // Create link only if missing
        if ($oSet->Count() === 0) {
            $oLnk = MetaModel::NewObject('lnkContactToFunctionalCI', [
                'contact_id' => $contactId,
                'functionalci_id' => $targetId
            ]);
            $oLnk->DBInsert();
        }
    }

    /**
     * Remove any contact link(s) between a contact and a target FunctionalCI.
     *
     * @param int $targetId  FunctionalCI ID (workstation or peripheral)
     * @param int $contactId Contact ID
     */
    public function DisconnectTargetFromContact(int $targetId, int $contactId): void
    {
        if ($targetId === 0 || $contactId === 0) {
            return;
        }

        $oSearch = DBSearch::FromOQL('SELECT lnkContactToFunctionalCI WHERE contact_id = :contact AND functionalci_id = :functionalci');
        $oSet = new DBObjectSet($oSearch, [], ['contact' => $contactId, 'functionalci' => $targetId]);

        // Delete all matching links (normally 0 or 1, but we clean up all to be safe)
        while ($oLnk = $oSet->Fetch()) {
            $oLnk->DBDelete();
        }
    }

    /**
     * Propagate a cost center to a target FunctionalCI object.
     *
     * The cost center attribute is optional (depends on whether the cost center extension is installed).
     *
     * @param int $targetId      FunctionalCI ID
     * @param int $costcenterId  Cost center ID to apply
     */
    public function UpdateCostCenterOnTarget(int $targetId, int $costcenterId): void
    {
        if ($targetId === 0 || $costcenterId === 0) {
            return;
        }

        // Only proceed if the target class supports costcenter_id
        if (!MetaModel::IsValidAttCode('FunctionalCI', 'costcenter_id')) {
            return;
        }

        $oTargetObject = MetaModel::GetObject('FunctionalCI', $targetId, false);
        if (is_object($oTargetObject)) {
            $oTargetObject->Set('costcenter_id', $costcenterId);
            $oTargetObject->DBUpdate();
        }
    }

    /**
     * "Before write" handler for Workstation.
     *
     * This method is intended to be called by your event wiring (custom event hook).
     * It analyzes changes on update and sets flags so that AfterWrite can do the actual
     * relationship updates efficiently.
     *
     * Key behaviors on update:
     * - If user changed: remove old contact links (workstation and peripherals)
     * - If a peripheral field changed: disconnect the old target from workstation_id
     * - If location/costcenter changed: set flags for later propagation
     *
     * @param EventData $oEventData Event payload from iTop
     */
    public function OnWorkstationBeforeWrite(EventData $oEventData): void
    {
        $aEventData = $oEventData->GetEventData();

        // Only handle updates (not inserts)
        if ($aEventData['is_new']) {
            return;
        }

        $this->aChangedFlags = [];
        $this->bLocationChanged = false;
        $this->bCostCenterChanged = false;
        $this->bUserChanged = false;

        // List of changed attributes for this update
        $aChanges = $this->ListChanges();

        // List of all peripheral attribute codes configured by this extension
        $aPeripherals = PeripheralsUtils::GetPeripherals();

        // If the assigned user changed, remove old contact links (workstation + old peripherals)
        // array_key_exists is used (not isset) because we also want to detect "unset" changes.
        if (array_key_exists('user_id', $aChanges)) {
            // record in the local memory object that the User was changed
            $this->bUserChanged = true;

            $iOriginalUserId = (int) $this->GetOriginal('user_id');

            // Remove old user link from workstation itself
            $this->DisconnectTargetFromContact((int) $this->GetKey(), $iOriginalUserId);

            // Remove old user link from all peripherals previously assigned to this workstation
            foreach ($aPeripherals as $sPeripheralAttCode) {
                $iOriginalPeripheralId = (int) $this->GetOriginal($sPeripheralAttCode);
                if ($iOriginalPeripheralId !== 0) {
                    $this->DisconnectTargetFromContact($iOriginalPeripheralId, $iOriginalUserId);
                }
            }
        }

        // For each changed peripheral assignment: mark a flag and disconnect the old target from workstation_id
        foreach ($aPeripherals as $sAttCode) {
            if (isset($aChanges[$sAttCode])) {
                // Remember that this assignment changed
                $this->aChangedFlags[$sAttCode] = true;
                // Disconnect previously assigned device from this workstation
                $this->DisconnectTargetFromWorkstation((int)$this->GetOriginal($sAttCode));
            }
        }

        // If location changed: we may need to propagate it to peripherals in AfterWrite
        if (isset($aChanges['location_id'])) {
            $this->bLocationChanged = true;
        }

        // If cost center changed: we may need to propagate it to peripherals in AfterWrite
        if (isset($aChanges['costcenter_id'])) {
            $this->bCostCenterChanged = true;
        }
    }

    /**
     * "After write" handler for Workstation.
     *
     * This method is intended to be called by your event wiring (custom event hook).
     * It applies relationship updates after the workstation has been inserted/updated in DB.
     *
     * Behavior:
     * - AfterInsert:
     *   - Assign workstation contact link to user (optional)
     *   - Assign all peripherals to workstation (and optionally propagate location/contacts/costcenter)
     *
     * - AfterUpdate:
     *   - Reconnect only peripherals that changed (based on flags set in BeforeWrite)
     *   - If location changed: propagate to all assigned peripherals (optional)
     *   - Ensure contact links exist for workstation and peripherals (optional)
     *   - If cost center changed: propagate to all assigned peripherals (optional, and only if extension exists)
     *
     * @param EventData $oEventData Event payload from iTop
     */
    public function OnWorkstationAfterWrite(EventData $oEventData): void
    {
        $aEventData = $oEventData->GetEventData();

        // Feature toggles from extension configuration
        $bUpdateLocations   = (bool) PeripheralsUtils::GetUpdateLocations();
        $bUpdateContacts    = (bool) PeripheralsUtils::GetUpdateContacts();
        $bUpdateCostCenters = (bool) PeripheralsUtils::GetUpdateCostCenters();

        // List of all peripheral attribute codes configured by this extension
        $aPeripherals = PeripheralsUtils::GetPeripherals();

        // Current assigned user (0 => none)
        $iUserId = (int) $this->Get('user_id');

        // CostCenter is optional:
        // - only if configured to update
        // - only if Workstation class has the attribute
        // - value 0 means "not set"
        $iCostCenterId = 0;
        if ($bUpdateCostCenters && MetaModel::IsValidAttCode('Workstation', 'costcenter_id')) {
            $iCostCenterId = (int) $this->Get('costcenter_id');
        }

        if ($aEventData['is_new']) {
            /**
             * ---------- AfterInsert ----------
             * Newly created workstation:
             * - Link workstation to contact (user), if enabled
             * - Link all assigned peripherals to workstation
             * - Optionally propagate location, contacts, and cost center
             */

            if ($bUpdateContacts && $iUserId !== 0) {
                // Ensure user is linked to the workstation itself
                $this->ConnectTargetToContact((int) $this->GetKey(), $iUserId);
            }

            // Link peripherals to workstation, optionally update location/contacts/costcenter
            foreach ($aPeripherals as $sPeripheralAttCode) {
                $iPeripheralId = (int) $this->Get($sPeripheralAttCode);
                if ($iPeripheralId === 0) {
                    continue; // no device assigned in this slot
                }

                // Always link peripheral to workstation; optionally propagate location
                $this->ConnectTargetToWorkstation($iPeripheralId, $bUpdateLocations);

                // Optionally link peripheral to the same user
                if ($bUpdateContacts && $iUserId !== 0) {
                    $this->ConnectTargetToContact($iPeripheralId, $iUserId);
                }

                // Optionally propagate cost center (only if value is set)
                if ($bUpdateCostCenters && $iCostCenterId !== 0) {
                    $this->UpdateCostCenterOnTarget($iPeripheralId, $iCostCenterId);
                }
            }
        } else {
            /**
             * ---------- AfterUpdate ----------
             * Workstation was updated:
             * - Reconnect only peripherals that changed (fast path)
             * - If location changed: propagate to all assigned peripherals
             * - Ensure contact links exist for workstation + peripherals
             * - If cost center changed: propagate to all assigned peripherals
             */

            // 1) Peripheral assignment changes: reconnect only those peripherals
            foreach (array_keys($this->aChangedFlags) as $sAttCode) {
                $iPeripheralId = (int) $this->Get($sAttCode);
                if ($iPeripheralId === 0) {
                    continue;
                }
                // Always (re)connect the peripheral to the workstation
                $this->ConnectTargetToWorkstation($iPeripheralId, $bUpdateLocations);

                // If enabled and workstation has a cost center set, propagate it to the changed peripheral as well
                if ($bUpdateCostCenters && $iCostCenterId !== 0) {
                    $this->UpdateCostCenterOnTarget($iPeripheralId, $iCostCenterId);
                }
            }

            // 2) Location propagation: if workstation location changed, update all assigned peripherals (optional)
            if ($this->bLocationChanged && $bUpdateLocations) {
                foreach ($aPeripherals as $sPeripheralAttCode) {
                    $iPeripheralId = (int) $this->Get($sPeripheralAttCode);
                    if ($iPeripheralId === 0) {
                        continue;
                    }

                    // Force location update (workstation_id is also ensured)
                    $this->ConnectTargetToWorkstation($iPeripheralId, true);
                }
            }

            // 3) Contact link maintenance: ensure links exist after updates (optional)
            if ($bUpdateContacts && $iUserId !== 0) {
                // Always ensure user is linked to workstation
                $this->ConnectTargetToContact((int) $this->GetKey(), $iUserId);

                // Ensure user is linked to each assigned peripheral
                foreach ($aPeripherals as $sPeripheralAttCode) {
                    $iPeripheralId = (int) $this->Get($sPeripheralAttCode);
                    if ($iPeripheralId === 0) {
                        continue;
                    }

                    $this->ConnectTargetToContact($iPeripheralId, $iUserId);
                }
            }

            // 4) Cost center propagation: only when cost center changed (optional and extension-dependent)
            if ($this->bCostCenterChanged && $bUpdateCostCenters && $iCostCenterId !== 0) {
                // Only run update when the cost center attribute exists on targets
                if (MetaModel::IsValidAttCode('FunctionalCI', 'costcenter_id')) {
                    foreach ($aPeripherals as $sPeripheralAttCode) {
                        $iPeripheralId = (int) $this->Get($sPeripheralAttCode);
                        if ($iPeripheralId === 0) {
                            continue;
                        }

                        $this->UpdateCostCenterOnTarget($iPeripheralId, $iCostCenterId);
                    }
                }
            }
        }
    }

    /**
     * "About to delete" handler for Workstation.
     *
     * This method is intended to be called by your event wiring (custom event hook).
     * It cleans up references before deleting the workstation:
     * - Remove user contact links from workstation and peripherals (optional)
     * - Disconnect all peripherals from workstation_id
     */
    public function OnWorkstationAboutToDelete()
    {
        $bUpdateContacts = (bool) PeripheralsUtils::GetUpdateContacts();
        $aPeripherals    = PeripheralsUtils::GetPeripherals();

        // Current assigned user (0 => none)
        $iUserId = (int) $this->Get('user_id');

        // 1) Remove contact links (optional)
        if ($bUpdateContacts && $iUserId !== 0) {
            // Remove link between user and workstation
            $this->DisconnectTargetFromContact((int) $this->GetKey(), $iUserId);

            // Remove link between user and each assigned peripheral
            foreach ($aPeripherals as $sPeripheralAttCode) {
                $iPeripheralId = (int) $this->Get($sPeripheralAttCode);
                if ($iPeripheralId !== 0) {
                    $this->DisconnectTargetFromContact($iPeripheralId, $iUserId);
                }
            }
        }

        // 2) Disconnect all peripherals from this workstation
        foreach ($aPeripherals as $sPeripheralAttCode) {
            $iPeripheralId = (int) $this->Get($sPeripheralAttCode);
            if ($iPeripheralId !== 0) {
                $this->DisconnectTargetFromWorkstation($iPeripheralId);
            }
        }
    }
}
