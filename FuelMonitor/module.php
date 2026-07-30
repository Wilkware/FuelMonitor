<?php

declare(strict_types=1);

/** Generell funktions */
require_once __DIR__ . '/../libs/_traits.php';

/** Namespaced traits */
use Wilkware\FuelMonitor\DebugHelper;
use Wilkware\FuelMonitor\VariableHelper;

/**
 * Class FuelMonitor
 */
class FuelMonitor extends IPSModuleStrict
{
    // -------------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------------

    use DebugHelper;
    use VariableHelper;

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** @var string Archive GUID  */
    private const ARCHIVE_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';

    /** @var int Archive Default Aggregation  */
    private const ARCHIVE_DEFAULT = 0;

    /** @var int Archive Counter Aggregation  */
    private const ARCHIVE_COUNTER = 1;

    // -------------------------------------------------------------------------
    // Constants (Min/Max)
    // -------------------------------------------------------------------------

    /** @var string Date reset constants */
    private const DATE_RESET = '{"year": -1, "month": -1, "day": -1 }';

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Car data ...
        $this->RegisterPropertyString('BrandModel', '');
        $this->RegisterPropertyString('LicensePlate', '');
        $this->RegisterPropertyString('FirstRegistration', self::DATE_RESET);
        $this->RegisterPropertyInteger('InitialMileage', 0);
        $this->RegisterPropertyInteger('VehicleImage', 1);

        // Service ...
        $this->RegisterPropertyBoolean('EnableTuev', false);
        $this->RegisterPropertyInteger('TuevReminderDays', 0);
        $this->RegisterPropertyBoolean('EnableService', false);
        $this->RegisterPropertyBoolean('ServiceByMileage', false);
        $this->RegisterPropertyInteger('ServiceReminderKm', 0);
        $this->RegisterPropertyBoolean('ServiceByDate', false);
        $this->RegisterPropertyInteger('ServiceReminderDays', 0);

        // Visualisation ...
        $this->RegisterPropertyBoolean('ShowBrandModel', true);
        $this->RegisterPropertyInteger('ColorNormal', 0x4CAF50);
        $this->RegisterPropertyInteger('ColorWarning', 0xFFC107);
        $this->RegisterPropertyInteger('ColorCritical', 0xF44336);

        // Advanced ...
        $this->RegisterPropertyBoolean('FutureDate', true);
        $this->RegisterPropertyBoolean('MileageDecreases', true);
        $this->RegisterPropertyBoolean('FirstInitial', true);

        // Archive ID
        $ilm = IPS_GetInstanceListByModuleID(self::ARCHIVE_GUID);
        $aid = $ilm[0];
        // Register status variables + statistics
        if ($this->RegisterVariableInteger('kilometers', $this->Translate('Kilometers'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT, 'SUFFIX' => ' km'], 0)) {
            $vid = @$this->GetIDForIdent('kilometers');
            $this->ArchiveVariable($aid, $vid, true, self::ARCHIVE_COUNTER, true);
        }
        if ($this->RegisterVariableFloat('liters', $this->Translate('Liters'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT, 'SUFFIX' => ' l', 'DIGITS' => 2], 1)) {
            $vid = @$this->GetIDForIdent('liters');
            $this->ArchiveVariable($aid, $vid, true, self::ARCHIVE_COUNTER, true);
        }
        if ($this->RegisterVariableFloat('price', $this->Translate('Price'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT, 'SUFFIX' => ' €', 'DIGITS' => 3], 2)) {
            $vid = @$this->GetIDForIdent('price');
            $this->ArchiveVariable($aid, $vid, true, self::ARCHIVE_DEFAULT, false);
        }
        if ($this->RegisterVariableFloat('average', $this->Translate('Average fuel consumption'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT, 'SUFFIX' => ' l/100km', 'DIGITS' => 2], 3)) {
            $vid = @$this->GetIDForIdent('average');
            $this->ArchiveVariable($aid, $vid, true, self::ARCHIVE_DEFAULT, false);
        }
        if ($this->RegisterVariableFloat('costs', $this->Translate('Costs'), ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT, 'SUFFIX' => ' €/100km', 'DIGITS' => 2], 4)) {
            $vid = @$this->GetIDForIdent('costs');
            $this->ArchiveVariable($aid, $vid, true, self::ARCHIVE_DEFAULT, false);
        }

        // Attributes for partial-refuel consumption tracking
        $this->RegisterAttributeString('History', '[]');
        // LastFullTankKM = odometer reading at the last full refuel (reference point)
        $this->RegisterAttributeInteger('LastFullTankKM', 0);
        // LiterSinceLastFullTank = liters accumulated since that reference point (partial refuels add up here)
        $this->RegisterAttributeFloat('LiterSinceLastFullTank', 0.0);

        // Activate HTML-SDK visualization for this instance (custom tile via GetVisualizationTile()/module.html)
        $this->SetVisualizationType(1);
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     *
     * @return void
     */
    public function Destroy(): void
    {
        parent::Destroy();
    }

    /**
     * The content can be overwritten in order to transfer a self-created configuration page.
     * This way, content can be generated dynamically.
     * In this case, the "form.json" on the file system is completely ignored.
     *
     * @return string Content of the configuration page.
     */
    public function GetConfigurationForm(): string
    {
        // Get Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Extract Version
        $ins = IPS_GetInstance($this->InstanceID);
        $mod = IPS_GetModule($ins['ModuleInfo']['ModuleID']);
        $lib = IPS_GetLibrary($mod['LibraryID']);
        $form['actions'][1]['items'][2]['caption'] = sprintf('v%s.%d', $lib['Version'], $lib['Build']);

        // Read Setup
        $tuev = $this->ReadPropertyBoolean('EnableTuev');
        $service = $this->ReadPropertyBoolean('EnableService');
        $milage = $this->ReadPropertyBoolean('ServiceByMileage');
        $date = $this->ReadPropertyBoolean('ServiceByDate');

        // Enable or disable tuev
        $form['elements'][2]['items'][0]['items'][0]['enabled'] = $tuev;
        $form['elements'][2]['items'][1]['items'][1]['enabled'] = $tuev;

        // Enable or disable service
        $form['elements'][2]['items'][2]['items'][0]['enabled'] = $service;
        $form['elements'][2]['items'][3]['items'][1]['enabled'] = $service;
        $form['elements'][2]['items'][4]['items'][1]['enabled'] = $service && $milage;
        $form['elements'][2]['items'][5]['items'][1]['enabled'] = $service;
        $form['elements'][2]['items'][6]['items'][1]['enabled'] = $service && $date;

        // return form
        return json_encode($form);
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        // If this is still the very first setup (archive completely empty) and an initial
        // mileage was configured (e.g. when buying a used car), seed the archive with an
        // "Erstbefüllung" automatically - no need to enter it manually via the tile first.
        // NOTE: this only fires once - as soon as the archive holds any entry, $first is
        // false and nothing happens here anymore, even if InitialMileage is edited again.
        $mi = $this->ReadPropertyInteger('InitialMileage');
        if ($mi > 0) {
            $ks = @$this->GetIDForIdent('kilometers');
            $aid = $this->GetArchiveID();
            if ($ks != false && $aid !== 0) {
                $lastValue = AC_GetLoggedValues($aid, $ks, 0, 0, 1);
                if (empty($lastValue)) {
                    $this->OnSaveInput(json_encode([
                        'date'     => date('Y-m-d'),
                        'type'     => 0,
                        'mileage'  => $mi,
                        'quantity' => 0,
                        'price'    => 0,
                        'invoice'  => 0,
                    ]));
                    $this->LogDebug(__FUNCTION__, 'Auto-seeded Erstbefüllung from InitialMileage: ' . $mi);
                }
            }
        }

        // Service/TÜV due dates
        $tuev = $this->ReadPropertyBoolean('EnableTuev');
        $this->MaintainVariable('tuev_due_date', $this->Translate('TÜV due date'), VARIABLETYPE_INTEGER, ['PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME], 10, $tuev);
        $this->MaintainAction('tuev_due_date', $tuev);

        // Suggest an initial TÜV due date from FirstRegistration + 3 years (first inspection in
        // Germany), but only ONCE - as soon as tuev_due_date has a real value, never overwrite it
        if ($tuev) {
            $tuevDue = $this->GetValue('tuev_due_date');
            if ($tuevDue <= 0) {
                $firstReg = $this->GetFirstRegistrationTimestamp();
                if ($firstReg > 0) {
                    $suggested = strtotime('+3 years', $firstReg);
                    $this->SetValueInteger('tuev_due_date', $suggested);
                    $this->LogDebug(__FUNCTION__, 'Auto-suggested tuev_due_date: ' . date('Y-m-d', $suggested));
                }
            }
        }

        $service = $this->ReadPropertyBoolean('EnableService');
        $milage = $this->ReadPropertyBoolean('ServiceByMileage');
        $date = $this->ReadPropertyBoolean('ServiceByDate');
        $this->MaintainVariable('service_due_mileage', $this->Translate('Service due mileage'), VARIABLETYPE_INTEGER, ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT, 'SUFFIX' => ' km'], 11, $service && $milage);
        $this->MaintainAction('service_due_mileage', $service && $milage);
        $this->MaintainVariable('service_due_date', $this->Translate('Service due date'), VARIABLETYPE_INTEGER, ['PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME], 12, $service && $date);
        $this->MaintainAction('service_due_date', $service && $date);

        // Send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     * @param string $ident Ident of the variable
     * @param mixed $value The value to be set
     *
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $value);
        switch ($ident) {
            case 'SaveTankEntry':
                $this->OnSaveInput($value);
                break;
            case 'tuev_due_date':
            case 'service_due_date':
                $this->SetValueInteger($ident, (int) $value);
                $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
                break;
            case 'service_due_mileage':
                $this->SetValueInteger($ident, (int) $value);
                $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
                break;
            default:
                eval('$this->' . $ident . '(\'' . $value . '\');');
        }
    }

    /**
     * If the HTML-SDK is to be used, this function must be overwritten in order to return the HTML content.
     *
     * @return string Initial display of a representation via HTML SDK
     */
    public function GetVisualizationTile(): string
    {
        // Add a script to set the values when loading, analogous to changes at runtime
        // Although the return from GetFullUpdateMessage is already JSON-encoded, json_encode is still executed a second time
        // This adds quotation marks to the string and any quotation marks within it are escaped correctly
        $handling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        // Add static HTML from file
        $module = file_get_contents(__DIR__ . '/module.html');
        // Important: $initialHandling at the end, as the handleMessage function is only defined in the HTML
        return $module . $handling;
    }

    /**
     * Modify TÜV reminder.
     *
     * @param bool $value Selection values
     *
     * @return void
     */
    protected function OnChangeTuev(bool $value): void
    {
        $this->LogDebug(__FUNCTION__, var_export($value, true));
        $this->UpdateFormField('TuevReminderDays', 'enabled', $value);
    }

    /**
     * Modify service remminder.
     *
     * @param bool $value Selection values
     *
     * @return void
     */
    protected function OnChangeService(bool $value): void
    {
        $this->LogDebug(__FUNCTION__, var_export($value, true));
        $this->UpdateFormField('ServiceByMileage', 'enabled', $value);
        $this->UpdateFormField('ServiceByDate', 'enabled', $value);
    }

    /**
     * Modify service my milage remminder.
     *
     * @param bool $value Selection values
     *
     * @return void
     */
    protected function OnChangeServiceByMileage(bool $value): void
    {
        $this->UpdateFormField('ServiceReminderKm', 'enabled', $value);
    }

    /**
     * Modify service by date remminder.
     *
     * @param bool $value Selection value
     *
     * @return void
     */
    protected function OnChangeServiceByDate(bool $value): void
    {
        $this->UpdateFormField('ServiceReminderDays', 'enabled', $value);
    }

    /**
     * Writes a value for one of the 5 archived status variables, choosing the right path:
     * - Today's date: plain SetValue() - Archive Control logs this automatically and correctly
     *   (with real time-of-day), AND GetValue() stays live. No manual archive call needed.
     * - Backdated date (Gestern/Vorgestern): SetValue() would log at "now", not at the chosen
     *   date, so we write directly into the archive via AC_AddLoggedValues() instead. GetValue()
     *   intentionally stays behind in this case (the backdated entry isn't "the current one").
     *
     * @param int $aid Archive Control instance ID.
     * @param int $varId Variable ID of the status variable.
     * @param string $ident Ident of the status variable.
     * @param mixed $value Value to write.
     * @param bool $isFloat True if the variable is a float, false if integer.
     * @param int $ts Timestamp of the refuelling entry.
     *
     * @return void
     */
    private function WriteValue(int $aid, int $varId, string $ident, $value, bool $isFloat, int $ts): void
    {
        if (date('Y-m-d', $ts) === date('Y-m-d')) {
            if ($isFloat) {
                $this->SetValueFloat($ident, $value);
            } else {
                $this->SetValueInteger($ident, $value);
            }
        } else {
            AC_AddLoggedValues($aid, $varId, [['TimeStamp' => $ts, 'Value' => $value]]);
        }
    }

    /**
     * User has saved a tank entry via the HTML tile (single combined JSON payload).
     *
     * Expected payload (JSON): {
     *   "date": "YYYY-MM-DD",   // refuelling date
     *   "mileage": int,         // absolute odometer reading
     *   "quantity": float,      // litres
     *   "price": float,         // price per litre
     *   "invoice": float,       // total invoice amount
     *   "type": int             // 0 = Erstbefuellung, 1 = Teilbetankung, 2 = Volltankung
     * }
     *
     * @param string $value JSON payload as described above.
     *
     * @return void
     */
    private function OnSaveInput(string $value): void
    {
        // decode payload
        $data = json_decode($value, true);
        if (!is_array($data)) {
            $this->LogMessage('SaveTankEntry: invalid JSON payload!', KL_ERROR);
            return;
        }

        // get data
        $ts = strtotime((string) ($data['date'] ?? 'now'));
        $mi = (int) ($data['mileage'] ?? 0);
        $tq = (float) ($data['quantity'] ?? 0);
        $pl = (float) ($data['price'] ?? 0);
        $iv = (float) ($data['invoice'] ?? 0);
        $rt = (int) ($data['type'] ?? 2); // 0 = initial, 1 = partial, 2 = full

        // Plausibility check instead of server-side recalculation: the HTML tile already computes
        // a self-consistent triangle (see module.html), this only guards against broken/incomplete
        // data reaching the archive. Erstbefüllung (rt=0) is exempt - 0/0/0 is valid there (reference
        // point only, e.g. the auto-seed from InitialMileage in ApplyChanges()).
        if ($rt !== 0) {
            $expected = round($tq * $pl, 2);
            if ($tq <= 0 || $pl <= 0 || $iv <= 0 || abs($expected - $iv) > 0.05) {
                $this->LogDebug(__FUNCTION__, 'Plausibility check failed: Quantity=' . $tq . ', Price=' . $pl . ', Invoice=' . $iv);
                echo $this->Translate('Quantity, price and invoice do not match!');
                return;
            }
        }

        $this->LogDebug(__FUNCTION__, 'Date: ' . $ts . ',Mileage: ' . $mi . ',Quantity: ' . $tq . ',Price: ' . $pl . ',Invoice: ' . $iv . ',Typ: ' . $rt);

        // future date check (previously handled in the old 'date' RequestAction case)
        if ($ts > time()) {
            if ($this->ReadPropertyBoolean('FutureDate')) {
                echo $this->Translate('Date is in the future!');
            }
            return;
        }

        // get IDs
        $ks = $this->GetIDForIdent('kilometers');
        $ls = $this->GetIDForIdent('liters');
        $ps = $this->GetIDForIdent('price');
        $as = $this->GetIDForIdent('average');
        $cs = $this->GetIDForIdent('costs');

        // get archive
        $ilm = IPS_GetInstanceListByModuleID(self::ARCHIVE_GUID);
        $aid = @$ilm[0];
        if (!isset($aid)) {
            $this->LogMessage('Archive Control not found!', KL_ERROR);
            return;
        }

        // check logging status
        $status = $this->ArchiveCheck($aid, $ks);
        $status = $status && $this->ArchiveCheck($aid, $ls);
        $status = $status && $this->ArchiveCheck($aid, $ps);
        $status = $status && $this->ArchiveCheck($aid, $as);
        $status = $status && $this->ArchiveCheck($aid, $cs);
        if (!$status) {
            $this->LogMessage('Archive Logging Status not valid!', KL_WARNING);
            return;
        }

        // first save?
        $lastValue = AC_GetLoggedValues($aid, $ks, 0, 0, 1);
        $first = empty($lastValue);
        $this->LogDebug(__FUNCTION__, 'First Save: ' . boolval($first));

        // then also selected?
        if ($first && $rt != 0) {
            echo $this->Translate('Initially please start with a first filling!');
            return;
        }

        // advanced check
        $decrease = $this->ReadPropertyBoolean('MileageDecreases');
        $initial = $this->ReadPropertyBoolean('FirstInitial');

        if (!$first && $initial && ($rt == 0)) {
            echo $this->Translate('First filling only allowed when saving for the first time!');
            return;
        }

        // last known mileage comes straight from the archive (not GetValue(), see design notes
        // on why 'kilometers' is no longer kept live in sync) - reuses $lastValue from above
        $km = !empty($lastValue) ? $lastValue[0]['Value'] : 0;
        if ($km >= $mi) {
            if (($rt > 0) || $initial) {
                if ($decrease) {
                    echo $this->Translate('Mileage is less than or unchanged from the last registration!');
                }
                $this->LogDebug(__FUNCTION__, 'Mileage is less than or unchanged from the last registration!');
                return;
            }
        }

        // Initial Filling (delete all old values)
        if (!$first && !$initial && ($rt == 0)) {
            // Kilometers
            AC_DeleteVariableData($aid, $ks, 0, 0);
            $this->ArchiveVariable($aid, $ks, true, self::ARCHIVE_COUNTER, false);
            $this->ArchiveCheck($aid, $ks);
            // Liters
            AC_DeleteVariableData($aid, $ls, 0, 0);
            $this->ArchiveVariable($aid, $ls, true, self::ARCHIVE_COUNTER, false);
            $this->ArchiveCheck($aid, $ls);
            // Price
            AC_DeleteVariableData($aid, $ps, 0, 0);
            $this->ArchiveVariable($aid, $ps, true, self::ARCHIVE_DEFAULT, false);
            $this->ArchiveCheck($aid, $ps);
            // Average
            AC_DeleteVariableData($aid, $as, 0, 0);
            $this->ArchiveVariable($aid, $as, true, self::ARCHIVE_DEFAULT, false);
            $this->ArchiveCheck($aid, $as);
            // Costs
            AC_DeleteVariableData($aid, $cs, 0, 0);
            $this->ArchiveVariable($aid, $cs, true, self::ARCHIVE_DEFAULT, false);
            $this->ArchiveCheck($aid, $cs);
            $this->LogDebug(__FUNCTION__, 'ReInit Archive - delete all old values!');
            // Reset History Attribute
            $this->WriteAttributeString('History', '[]');
        }

        // calculate
        if ($rt == 0) {
            // Initial filling: establishes the reference point, no consumption possible yet
            $this->WriteValue($aid, $ks, 'kilometers', $mi, false, $ts);
            $this->WriteValue($aid, $ls, 'liters', $tq, true, $ts);
            $this->WriteValue($aid, $ps, 'price', $pl, true, $ts);
            $this->WriteValue($aid, $as, 'average', 0, true, $ts);
            $this->WriteValue($aid, $cs, 'costs', 0, true, $ts);
            $this->WriteAttributeInteger('LastFullTankKM', $mi);
            $this->WriteAttributeFloat('LiterSinceLastFullTank', 0.0);
            $this->LogDebug(__FUNCTION__, 'Log Values for initial filling!');
        } elseif ($rt == 1) {
            // Partial refuelling: log raw data, no consumption calculation yet -
            // just accumulate the litres for the next full refuel's calculation.
            $this->WriteValue($aid, $ks, 'kilometers', $mi, false, $ts);
            $this->WriteValue($aid, $ls, 'liters', $tq, true, $ts);
            $this->WriteValue($aid, $ps, 'price', $pl, true, $ts);
            $this->WriteValue($aid, $as, 'average', 0, true, $ts);
            $this->WriteValue($aid, $cs, 'costs', 0, true, $ts);
            $literSum = $this->ReadAttributeFloat('LiterSinceLastFullTank') + $tq;
            $this->WriteAttributeFloat('LiterSinceLastFullTank', $literSum);
            $this->LogDebug(__FUNCTION__, 'Log Values for partial filling! LiterSinceLastFullTank => ' . $literSum);
        } else {
            // Full refuelling: closes the measurement interval since the last full refuel (Volltankmethode),
            // including any partial refuels logged in between.
            $literSum = $this->ReadAttributeFloat('LiterSinceLastFullTank') + $tq;
            $lastFullTankKM = $this->ReadAttributeInteger('LastFullTankKM');
            $distance = $mi - $lastFullTankKM;
            $this->LogDebug(__FUNCTION__, 'Distance since last full tank: ' . $distance . ', Liters since last full tank: ' . $literSum);
            $consumption = $distance > 0 ? ($literSum * 100) / $distance : 0;
            $this->LogDebug(__FUNCTION__, 'Consumption: ' . $consumption);
            $cost = $pl * $consumption;
            $this->LogDebug(__FUNCTION__, 'Costs: ' . $cost);
            $this->WriteValue($aid, $ks, 'kilometers', $mi, false, $ts);
            $this->WriteValue($aid, $ls, 'liters', $tq, true, $ts);
            $this->WriteValue($aid, $ps, 'price', $pl, true, $ts);
            $this->WriteValue($aid, $as, 'average', $consumption, true, $ts);
            $this->WriteValue($aid, $cs, 'costs', $cost, true, $ts);
            // Reset consumption-tracking attributes: this full refuel becomes the new reference point
            $this->WriteAttributeInteger('LastFullTankKM', $mi);
            $this->WriteAttributeFloat('LiterSinceLastFullTank', 0.0);
            $this->LogDebug(__FUNCTION__, 'Log Values for full filling!');
        }

        // AC_ReAggregateVariable
        $status = AC_ReAggregateVariable($aid, $ks);
        $status = $status && AC_ReAggregateVariable($aid, $ls);
        $status = $status && AC_ReAggregateVariable($aid, $ps);
        $status = $status && AC_ReAggregateVariable($aid, $as);
        $status = $status && AC_ReAggregateVariable($aid, $cs);
        $this->LogDebug(__FUNCTION__, 'Status ReAggregate: ' . boolval($status));
        // Refresh the tile (new history entry, updated last-known values)
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * ArchiveVariable
     *
     * @param int $ac
     * @param int $var
     * @param bool $state
     * @param int $type
     * @param bool $zero
     *
     * @return void
     */
    private function ArchiveVariable(int $ac, int $var, bool $state, int $type, bool $zero): void
    {
        AC_SetLoggingStatus($ac, $var, $state);
        if ($state) {
            AC_SetAggregationType($ac, $var, $type);
            if ($zero) {
                AC_SetCounterIgnoreZeros($ac, $var, $zero);
            }
        }
    }

    /**
     * Check Archive Variables
     *
     * @param int $ac Arcive Control ID
     * @param int $var Variable ID
     *
     * @return bool True if enabled, otherwise false.
     */
    private function ArchiveCheck(int $ac, int $var): bool
    {
        $state = @AC_GetLoggingStatus($ac, $var);
        if ($state) {
            $lastValue = AC_GetLoggedValues($ac, $var, 0, 0, 1);
            if (!empty($lastValue) && (count($lastValue) == 1) && ($lastValue[0]['Value'] == 0)) {
                $ret = AC_DeleteVariableData($ac, $var, $lastValue[0]['TimeStamp'], 0);
                $this->LogDebug(__FUNCTION__, 'Null values (#' . $var . ') - removed!');
            }
        }
        return $state;
    }

    /**
     * Get the (first) Archive Control instance ID.
     *
     * @return int Archive Control instance ID, or 0.
     */
    private function GetArchiveID(): int
    {
        $ilm = IPS_GetInstanceListByModuleID(self::ARCHIVE_GUID);
        return @$ilm[0] ?? 0;
    }

    /**
     * Parses the FirstRegistration property (SelectDate JSON: {"year":Y,"month":M,"day":D})
     * into a Unix timestamp.
     *
     * @return int Timestamp, or 0 if not set.
     */
    private function GetFirstRegistrationTimestamp(): int
    {
        $raw = $this->ReadPropertyString('FirstRegistration');
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['year'] ?? -1) < 0) {
            return 0;
        }
        return mktime(0, 0, 0, $data['month'], $data['day'], $data['year']);
    }

    /**
     * Computes the TÜV/Service badge status (normal/warning/critical), using the configured
     * badge colors. Returns null for a badge if it is disabled in the configuration.
     *
     * @return array<mixed> Badge data for the HTML tile.
     */
    private function GetServiceBadges(): array
    {
        $colors = [
            'normal'   => $this->ReadPropertyInteger('ColorNormal'),
            'warning'  => $this->ReadPropertyInteger('ColorWarning'),
            'critical' => $this->ReadPropertyInteger('ColorCritical'),
        ];

        $badges = [
            'colors'  => $colors,
            'tuev'    => null,
            'service' => null,
            // Raw values + which fields are actually in use, so the tile can offer manual
            // correction (e.g. TÜV appointment missed or done later than suggested).
            'edit' => [
                'enableTuev'           => $this->ReadPropertyBoolean('EnableTuev'),
                'tuevDate'             => @$this->GetValue('tuev_due_date') ?? null,
                'enableServiceDate'    => $this->ReadPropertyBoolean('EnableService') && $this->ReadPropertyBoolean('ServiceByDate'),
                'serviceDate'          => @$this->GetValue('service_due_date') ?? null,
                'enableServiceMileage' => $this->ReadPropertyBoolean('EnableService') && $this->ReadPropertyBoolean('ServiceByMileage'),
                'serviceMileage'       => @$this->GetValue('service_due_mileage') ?? null,
            ],
        ];
        $now = time();

        // TÜV - date based only
        if ($this->ReadPropertyBoolean('EnableTuev')) {
            $due = $this->GetValue('tuev_due_date');
            if ($due > 0) {
                $daysLeft = (int) floor(($due - $now) / 86400);
                $reminder = $this->ReadPropertyInteger('TuevReminderDays');
                $status = $daysLeft <= 0 ? 'critical' : ($daysLeft <= $reminder ? 'warning' : 'normal');
                $badges['tuev'] = [
                    'status' => $status,
                    'label'  => 'TÜV',
                    'text'   => $daysLeft >= 0 ? $daysLeft . ' Tage' : 'überfällig',
                ];
            }
        }

        // Service - by mileage and/or date, whichever is closer/more urgent wins the badge
        if ($this->ReadPropertyBoolean('EnableService')) {
            $candidates = [];

            if ($this->ReadPropertyBoolean('ServiceByDate')) {
                $due = $this->GetValue('service_due_date');
                if ($due > 0) {
                    $daysLeft = (int) floor(($due - $now) / 86400);
                    $reminder = $this->ReadPropertyInteger('ServiceReminderDays');
                    $status = $daysLeft <= 0 ? 'critical' : ($daysLeft <= $reminder ? 'warning' : 'normal');
                    $candidates[] = [
                        'status' => $status,
                        'rank'   => $daysLeft,
                        'text'   => $daysLeft >= 0 ? $daysLeft . ' Tage' : 'überfällig',
                    ];
                }
            }
            if ($this->ReadPropertyBoolean('ServiceByMileage')) {
                $due = $this->GetValue('service_due_mileage');
                if ($due > 0) {
                    $ks = @$this->GetIDForIdent('kilometers');
                    $current = $ks != false ? $this->GetValue('kilometers') : 0;
                    $kmLeft = $due - $current;
                    $reminder = $this->ReadPropertyInteger('ServiceReminderKm');
                    $status = $kmLeft <= 0 ? 'critical' : ($kmLeft <= $reminder ? 'warning' : 'normal');
                    $candidates[] = [
                        'status' => $status,
                        'rank'   => $kmLeft,
                        'text'   => $kmLeft >= 0 ? $kmLeft . ' km' : 'überfällig',
                    ];
                }
            }

            if (!empty($candidates)) {
                // Rank by status severity first (critical beats warning beats normal) - the raw
                // "rank" values are in different units (days vs. km) and can't be compared
                // directly against each other, only used to order within the same status.
                $severity = ['critical' => 0, 'warning' => 1, 'normal' => 2];
                usort($candidates, function ($a, $b) use ($severity)
                {
                    $sa = $severity[$a['status']];
                    $sb = $severity[$b['status']];
                    if ($sa !== $sb) {
                        return $sa <=> $sb;
                    }
                    return $a['rank'] <=> $b['rank'];
                });
                $winner = $candidates[0];
                $badges['service'] = [
                    'status' => $winner['status'],
                    'label'  => 'Service',
                    'text'   => $winner['text'],
                ];
            }
        }

        return $badges;
    }

    /**
     * Returns the vehicle image (from the VehicleImage media property) as a data URI,
     * so it can be used directly as a CSS background-image without a separate HTTP route.
     *
     * NOTE: relies on IPS_GetMediaContent() returning base64-encoded content and on the
     * media object existing - please double check this against your Symcon version, I
     * could not verify the exact behaviour with certainty.
     *
     * @return string Data URI, or empty string if no image is configured.
     */
    private function GetVehicleImageDataUri(): string
    {
        $mediaId = $this->ReadPropertyInteger('VehicleImage');
        if ($mediaId <= 1) {
            return '';
        }
        $media = @IPS_GetMedia($mediaId);
        if ($media === false) {
            return '';
        }
        $ext = strtolower(pathinfo($media['MediaFile'], PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            default => 'image/jpeg',
        };
        $content = @IPS_GetMediaContent($mediaId);
        if ($content === false || $content === '') {
            return '';
        }
        return 'data:' . $mime . ';base64,' . $content;
    }

    /**
     * Fetches one exact logged value at a given timestamp (used to line up the 5 archived
     * variables, which are always written together with the same timestamp per entry).
     *
     * @param int $aid Archive Control instance ID.
     * @param int $varId Variable ID.
     * @param int $ts Exact timestamp to look up.
     *
     * @return float The logged value, or 0 if none found.
     */
    private function GetLoggedValueAt(int $aid, int $varId, int $ts): float
    {
        $rows = AC_GetLoggedValues($aid, $varId, $ts, $ts, 1);
        return !empty($rows) ? (float) $rows[0]['Value'] : 0.0;
    }

    /**
     * Assembles the last $limit refuelling entries (correlated across the 5 archived
     * variables by timestamp) for display in the tile's history list.
     *
     * @param int $aid Archive Control instance ID.
     * @param int $limit Maximum number of entries.
     *
     * @return array<int,array{timestamp:int,mileage:int,liters:float,price:float,average:float,costs:float}> List of entries, most recent first.
     *
     */
    private function GetHistory(int $aid, int $limit): array
    {
        $ks = $this->GetIDForIdent('kilometers');
        $ls = $this->GetIDForIdent('liters');
        $ps = $this->GetIDForIdent('price');
        $as = $this->GetIDForIdent('average');
        $cs = $this->GetIDForIdent('costs');

        $rows = AC_GetLoggedValues($aid, $ks, 0, 0, $limit);
        $history = [];
        foreach ($rows as $row) {
            $ts = $row['TimeStamp'];
            $history[] = [
                'timestamp' => $ts,
                'mileage'   => (int) $row['Value'],
                'liters'    => $this->GetLoggedValueAt($aid, $ls, $ts),
                'price'     => $this->GetLoggedValueAt($aid, $ps, $ts),
                'average'   => $this->GetLoggedValueAt($aid, $as, $ts),
                'costs'     => $this->GetLoggedValueAt($aid, $cs, $ts),
            ];
        }
        return $history;
    }

    /**
     * Generate a message that updates all elements in the HTML display.
     *
     * @return string JSON encoded message information
     */
    private function GetFullUpdateMessage(): string
    {
        $result = [
            'vehicleImage'   => $this->GetVehicleImageDataUri(),
            'licensePlate'   => $this->ReadPropertyString('LicensePlate'),
            'brandModel'     => $this->ReadPropertyString('BrandModel'),
            'showBrandModel' => $this->ReadPropertyBoolean('ShowBrandModel'),
            'badges'         => $this->GetServiceBadges(),
            'history'        => [],
        ];

        $aid = $this->GetArchiveID();
        if ($aid !== 0) {
            $result['history'] = $this->GetHistory($aid, 20);
        }

        $this->LogDebug(__FUNCTION__, json_encode($result));

        // send it
        return json_encode($result);
    }
}
